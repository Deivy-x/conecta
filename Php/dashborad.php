<?php
// dashboard.php
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

<h1>Hola, <?= htmlspecialchars($usuario['nombre']) ?></h1>
<a href="logout.php">Cerrar sesión</a>

