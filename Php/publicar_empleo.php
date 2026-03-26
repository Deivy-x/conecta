<?php
// ============================================================
// Php/publicar_empleo.php — Publicar vacante de empleo
// QuibdóConecta 2026
// ============================================================
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/planes_helper.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión para publicar vacantes.']);
    exit;
}

// Verificar que sea empresa
if ($_SESSION['usuario_tipo'] !== 'empresa') {
    echo json_encode(['ok' => false, 'msg' => 'Solo las empresas pueden publicar vacantes.']);
    exit;
}

// Recoger campos
$titulo      = trim($_POST['titulo']      ?? '');
$empresa     = trim($_POST['empresa']     ?? '');
$ubicacion   = trim($_POST['ubicacion']   ?? '');
$tipo        = trim($_POST['tipo']        ?? '');
$salario     = trim($_POST['salario']     ?? '');
$categoria   = trim($_POST['categoria']   ?? '');
$fecha       = trim($_POST['fecha']       ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$requisitos  = trim($_POST['requisitos']  ?? '');

// Validar obligatorios
if (!$titulo || !$tipo || !$descripcion || !$requisitos) {
    echo json_encode(['ok' => false, 'msg' => 'Faltan campos obligatorios (título, tipo, descripción, requisitos).']);
    exit;
}

// vence_en: si viene fecha la usamos, si no null
$vence_en = ($fecha !== '') ? $fecha : null;

try {
    $db = getDB();

    // Obtener nombre de empresa desde perfiles_empresa si está vacío
    if (!$empresa) {
        $pe = $db->prepare("SELECT nombre_empresa FROM perfiles_empresa WHERE usuario_id = ? LIMIT 1");
        $pe->execute([$_SESSION['usuario_id']]);
        $peRow = $pe->fetch();
        $empresa = $peRow ? $peRow['nombre_empresa'] : ($_SESSION['usuario_nombre'] ?? 'Empresa');
    }


    // ── Verificar límite de vacantes del plan ────────────────────
    $lim = verificarLimite($db, $_SESSION['usuario_id'], 'vacantes');
    if (!$lim['puede']) {
        echo msgLimiteSuperado($lim['plan'], 'vacantes', $lim['limite']);
        exit;
    }

    $db->prepare("
        INSERT INTO empleos
            (empresa_id, titulo, descripcion, categoria, barrio, ciudad,
             salario_texto, tipo_contrato, modalidad, tipo, activo, vence_en, creado_en)
        VALUES
            (?, ?, ?, ?, '', ?,
             ?, ?, 'presencial', 'privado', 0, ?, NOW())
    ")->execute([
        $_SESSION['usuario_id'],
        $titulo,
        $descripcion . "\n\nRequisitos:\n" . $requisitos,
        $categoria,
        $ubicacion ?: 'Quibdó, Chocó',
        $salario,
        $tipo,   // tipo_contrato: tiempo completo, medio tiempo, etc.
        $vence_en
    ]);

    registrarAccion($db, $_SESSION['usuario_id'], 'vacantes');

    echo json_encode([
        'ok'  => true,
        'msg' => '✅ Vacante enviada. Quedará visible una vez el administrador la apruebe (24–48 h).'
    ]);

} catch (PDOException $e) {
    // Si falta alguna columna en la tabla, dar mensaje útil
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()]);
}
?>