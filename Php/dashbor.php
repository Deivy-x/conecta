<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require 'config/db.php';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - QuibdoConecta</title>
</head>
<body>
    <h1>Hola, <?= htmlspecialchars($usuario['nombre']) ?> 👋</h1>
    <p>Bienvenido a QuibdoConecta</p>
    <a href="logout.php">Cerrar sesión</a>
</body>
</html>