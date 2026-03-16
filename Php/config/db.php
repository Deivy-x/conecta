<?php
$host = 'localhost';
$dbname = 'mi_app';
$user = 'root';
$pass = ''; // En producción, usa una contraseña fuerte

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?> 