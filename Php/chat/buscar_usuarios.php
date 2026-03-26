<?php

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión.']);
    exit;
}

$yo = (int) $_SESSION['usuario_id'];
$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'usuarios' => []]);
    exit;
}

$db = getDB();
$busqueda = "%{$q}%";

$stmt = $db->prepare("
    SELECT id, nombre, apellido, foto, tipo
    FROM usuarios
    WHERE activo = 1
      AND id != ?
      AND (nombre LIKE ? OR apellido LIKE ? OR correo LIKE ?)
    ORDER BY nombre ASC
    LIMIT 20
");
$stmt->execute([$yo, $busqueda, $busqueda, $busqueda]);
$usuarios = $stmt->fetchAll();

echo json_encode(['ok' => true, 'usuarios' => $usuarios]);
