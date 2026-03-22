<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/Php/db.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión.']);
    exit;
}

$yo = (int) $_SESSION['usuario_id'];
$con = (int) ($_GET['con'] ?? 0);

if (!$con) {
    echo json_encode(['ok' => false, 'msg' => 'Falta el ID del usuario.']);
    exit;
}

$db = getDB();

$db->prepare("UPDATE mensajes SET leido = 1 WHERE de_usuario = ? AND para_usuario = ? AND leido = 0")
   ->execute([$con, $yo]);

$stmt = $db->prepare("
    SELECT m.id, m.de_usuario, m.para_usuario, m.mensaje, m.leido, m.creado_en,
           u.nombre AS nombre_remitente
    FROM mensajes m
    INNER JOIN usuarios u ON u.id = m.de_usuario
    WHERE (m.de_usuario = ? AND m.para_usuario = ?)
       OR (m.de_usuario = ? AND m.para_usuario = ?)
    ORDER BY m.creado_en ASC
    LIMIT 100
");
$stmt->execute([$yo, $con, $con, $yo]);
$mensajes = $stmt->fetchAll();

$info = $db->prepare("SELECT id, nombre, apellido, foto, tipo FROM usuarios WHERE id = ? AND activo = 1");
$info->execute([$con]);
$otro = $info->fetch();

echo json_encode(['ok' => true, 'usuario' => $otro ?: null, 'mensajes' => $mensajes]);
