<?php
// ============================================================
// Php/chat_enviar_sub.php — Enviar mensaje (POST)
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../planes_helper.php';
require_once __DIR__ . '/../mail_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión.']);
    exit;
}

$de = (int) $_SESSION['usuario_id'];
$para = (int) ($_POST['para_usuario'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');

if (!$para) { echo json_encode(['ok' => false, 'msg' => 'Destinatario no válido.']); exit; }
if ($para === $de) { echo json_encode(['ok' => false, 'msg' => 'No puedes enviarte mensajes a ti mismo.']); exit; }
if ($mensaje === '') { echo json_encode(['ok' => false, 'msg' => 'El mensaje no puede estar vacío.']); exit; }
if (mb_strlen($mensaje) > 2000) { echo json_encode(['ok' => false, 'msg' => 'El mensaje es demasiado largo (máx 2000 caracteres).']); exit; }

$db = getDB();

// ── Datos del destinatario ───────────────────────────────────
$checkDest = $db->prepare("SELECT id, nombre, correo FROM usuarios WHERE id = ? AND activo = 1");
$checkDest->execute([$para]);
$destino = $checkDest->fetch();
if (!$destino) {
    echo json_encode(['ok' => false, 'msg' => 'El usuario destinatario no existe.']);
    exit;
}

// ── Datos del remitente ──────────────────────────────────────
$checkDe = $db->prepare("SELECT nombre FROM usuarios WHERE id = ?");
$checkDe->execute([$de]);
$remitente = $checkDe->fetch();

// ── Verificar límite de mensajes del plan ────────────────────
$lim = verificarLimite($db, $de, 'mensajes');
if (!$lim['puede']) {
    echo msgLimiteSuperado($lim['plan'], 'mensajes', $lim['limite']);
    exit;
}

// ── Detectar conversación activa (no spamear si están chateando) ──
$activo = false;
try {
    $stmtAct = $db->prepare("
        SELECT 1 FROM mensajes
        WHERE de_usuario = ? AND para_usuario = ?
          AND creado_en >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
        LIMIT 1
    ");
    $stmtAct->execute([$para, $de]);
    $activo = (bool) $stmtAct->fetch();
} catch (Exception $e) {}

// ── Guardar mensaje ──────────────────────────────────────────
$stmt = $db->prepare("INSERT INTO mensajes (de_usuario, para_usuario, mensaje) VALUES (?, ?, ?)");
$stmt->execute([$de, $para, $mensaje]);
$nuevoId = (int) $db->lastInsertId();

registrarAccion($db, $de, 'mensajes');

// ── Notificación por correo ──────────────────────────────────
if (!$activo && !empty($destino['correo'])) {
    $correoD  = $destino['correo'];
    $nombreD  = $destino['nombre'];
    $nombreDe = $remitente['nombre'] ?? 'Alguien';
    register_shutdown_function(function () use ($nombreDe, $correoD, $nombreD, $mensaje, $de) {
        notificarNuevoMensaje($nombreDe, $correoD, $nombreD, $mensaje, $de);
    });
}

echo json_encode([
    'ok'        => true,
    'id'        => $nuevoId,
    'creado_en' => date('Y-m-d H:i:s')
]);