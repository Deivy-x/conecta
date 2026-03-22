<?php
// ============================================================
// Php/verificacion.php — Sistema de verificación de usuarios
// POST _action=solicitar  → candidato/empresa sube documentos
// POST _action=aprobar    → admin aprueba verificación
// POST _action=rechazar   → admin rechaza con nota
// GET  _action=estado     → consulta estado actual
// ============================================================
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'No autorizado.']);
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['usuario_id'];
$action = $_POST['_action'] ?? $_GET['_action'] ?? '';

// ── Leer usuario ─────────────────────────────────────────────
$stmt = $db->prepare("SELECT id, nombre, tipo, activo FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado.']);
    exit;
}

// ────────────────────────────────────────────────────────────
// GET: estado de verificación
// ────────────────────────────────────────────────────────────
if ($action === 'estado') {
    $stmt = $db->prepare("SELECT * FROM verificaciones WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $ver = $stmt->fetch();

    // También verificar badge en talento_perfil
    $stmt2 = $db->prepare("SELECT verificado FROM talento_perfil WHERE usuario_id = ?");
    $stmt2->execute([$userId]);
    $tp = $stmt2->fetch();

    echo json_encode([
        'ok'          => true,
        'verificacion'=> $ver ?: null,
        'badge'       => $tp ? (int)$tp['verificado'] : 0
    ]);
    exit;
}

// ────────────────────────────────────────────────────────────
// POST: solicitar verificación (sube documentos)
// ────────────────────────────────────────────────────────────
if ($action === 'solicitar') {

    // Verificar que no tenga ya una solicitud aprobada
    $stmt = $db->prepare("SELECT estado FROM verificaciones WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $existente = $stmt->fetch();

    if ($existente && $existente['estado'] === 'aprobado') {
        echo json_encode(['ok' => false, 'msg' => 'Tu cuenta ya está verificada.']);
        exit;
    }

    // ── Validar archivos subidos ──────────────────────────
    $errores = [];
    $docUrl     = null;
    $fotoDocUrl = null;

    $uploadDir = __DIR__ . '/../uploads/verificaciones/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    // Archivo principal: cédula o cámara de comercio
    if (!empty($_FILES['documento']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION));
        $permitidos = ['jpg','jpeg','png','pdf'];

        if (!in_array($ext, $permitidos)) {
            $errores[] = 'Formato no permitido. Usa JPG, PNG o PDF.';
        } elseif ($_FILES['documento']['size'] > 5 * 1024 * 1024) {
            $errores[] = 'El documento no debe superar 5MB.';
        } else {
            $nombre   = 'doc_' . $userId . '_' . time() . '.' . $ext;
            $destino  = $uploadDir . $nombre;
            if (move_uploaded_file($_FILES['documento']['tmp_name'], $destino)) {
                $docUrl = 'uploads/verificaciones/' . $nombre;
            } else {
                $errores[] = 'Error al subir el documento.';
            }
        }
    } else {
        $errores[] = 'Debes subir tu documento de identidad o NIT.';
    }

    // Selfie con documento (opcional pero recomendado)
    if (!empty($_FILES['foto_documento']['name'])) {
        $ext2 = strtolower(pathinfo($_FILES['foto_documento']['name'], PATHINFO_EXTENSION));
        if (in_array($ext2, ['jpg','jpeg','png']) && $_FILES['foto_documento']['size'] <= 5 * 1024 * 1024) {
            $nombre2  = 'selfie_' . $userId . '_' . time() . '.' . $ext2;
            $destino2 = $uploadDir . $nombre2;
            if (move_uploaded_file($_FILES['foto_documento']['tmp_name'], $destino2)) {
                $fotoDocUrl = 'uploads/verificaciones/' . $nombre2;
            }
        }
    }

    if (!empty($errores)) {
        echo json_encode(['ok' => false, 'msg' => implode(' ', $errores)]);
        exit;
    }

    $tipoDoc = $usuario['tipo'] === 'empresa' ? 'camara_comercio' : 'cedula';

    // ── Insertar o actualizar solicitud ───────────────────
    if ($existente) {
        $stmt = $db->prepare("
            UPDATE verificaciones
            SET doc_url=?, foto_doc_url=?, estado='pendiente',
                nota_rechazo=NULL, actualizado=NOW()
            WHERE usuario_id=?
        ");
        $stmt->execute([$docUrl, $fotoDocUrl, $userId]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO verificaciones
                (usuario_id, tipo_doc, doc_url, foto_doc_url, estado)
            VALUES (?, ?, ?, ?, 'pendiente')
        ");
        $stmt->execute([$userId, $tipoDoc, $docUrl, $fotoDocUrl]);
    }

    echo json_encode([
        'ok'  => true,
        'msg' => '¡Solicitud enviada! El equipo de QuibdóConecta la revisará en máximo 24 horas.'
    ]);
    exit;
}

// ────────────────────────────────────────────────────────────
// POST: aprobar (solo admin)
// ────────────────────────────────────────────────────────────
if ($action === 'aprobar') {
    // Verificar que quien aprueba sea admin
    $stmt = $db->prepare("SELECT nivel FROM admin_roles WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $rol = $stmt->fetch();

    if (!$rol || !in_array($rol['nivel'], ['superadmin','delegado','dev'])) {
        // Verificar permisos específicos
        $stmt2 = $db->prepare("SELECT perm_verificar FROM admin_roles WHERE usuario_id = ?");
        $stmt2->execute([$userId]);
        $perms = $stmt2->fetch();
        if (!$perms || !$perms['perm_verificar']) {
            echo json_encode(['ok' => false, 'msg' => 'Sin permisos para aprobar verificaciones.']);
            exit;
        }
    }

    $targetId = (int)($_POST['usuario_id'] ?? 0);
    if (!$targetId) {
        echo json_encode(['ok' => false, 'msg' => 'Usuario no especificado.']);
        exit;
    }

    // Aprobar verificación
    $stmt = $db->prepare("
        UPDATE verificaciones
        SET estado='aprobado', revisado_por=?, actualizado=NOW()
        WHERE usuario_id=?
    ");
    $stmt->execute([$userId, $targetId]);

    // Activar verificado en talento_perfil SOLO si ya existe la fila
    // (la fila la crea únicamente el admin desde el panel)
    $db->prepare("UPDATE talento_perfil SET verificado=1 WHERE usuario_id=?")
       ->execute([$targetId]);

    echo json_encode(['ok' => true, 'msg' => 'Usuario verificado correctamente. Badge activado.']);
    exit;
}

// ────────────────────────────────────────────────────────────
// POST: rechazar (solo admin)
// ────────────────────────────────────────────────────────────
if ($action === 'rechazar') {
    $stmt = $db->prepare("SELECT perm_verificar FROM admin_roles WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $perms = $stmt->fetch();

    if (!$perms || !$perms['perm_verificar']) {
        echo json_encode(['ok' => false, 'msg' => 'Sin permisos.']);
        exit;
    }

    $targetId = (int)($_POST['usuario_id'] ?? 0);
    $nota     = trim($_POST['nota'] ?? 'Documentos no válidos. Por favor sube documentos legibles.');

    $stmt = $db->prepare("
        UPDATE verificaciones
        SET estado='rechazado', revisado_por=?, nota_rechazo=?, actualizado=NOW()
        WHERE usuario_id=?
    ");
    $stmt->execute([$userId, $nota, $targetId]);

    // Quitar badge si tenía
    $db->prepare("UPDATE talento_perfil SET verificado=0 WHERE usuario_id=?")
       ->execute([$targetId]);

    echo json_encode(['ok' => true, 'msg' => 'Verificación rechazada.']);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida.']);