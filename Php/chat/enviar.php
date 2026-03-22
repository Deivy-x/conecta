<?php
// ============================================================
// Php/chat/enviar.php — Enviar mensaje (POST)
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión.']);
    exit;
}

$de = (int) $_SESSION['usuario_id'];
$para = (int) ($_POST['para_usuario'] ?? 0);
$mensaje = trim($_POST['mensaje'] ?? '');

// Validaciones
if (!$para) {
    echo json_encode(['ok' => false, 'msg' => 'Destinatario no válido.']);
    exit;
}
if ($para === $de) {
    echo json_encode(['ok' => false, 'msg' => 'No puedes enviarte mensajes a ti mismo.']);
    exit;
}
if ($mensaje === '') {
    echo json_encode(['ok' => false, 'msg' => 'El mensaje no puede estar vacío.']);
    exit;
}
if (mb_strlen($mensaje) > 2000) {
    echo json_encode(['ok' => false, 'msg' => 'El mensaje es demasiado largo (máx 2000 caracteres).']);
    exit;
}

$db = getDB();

// Verificar que el destinatario existe y está activo
$check = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND activo = 1");
$check->execute([$para]);
if (!$check->fetch()) {
    echo json_encode(['ok' => false, 'msg' => 'El usuario destinatario no existe.']);
    exit;
}

// Insertar mensaje
$stmt = $db->prepare("INSERT INTO mensajes (de_usuario, para_usuario, mensaje) VALUES (?, ?, ?)");
$stmt->execute([$de, $para, $mensaje]);

echo json_encode([
    'ok' => true,
    'id' => (int) $db->lastInsertId(),
    'creado_en' => date('Y-m-d H:i:s')
]);
