<?php
$host = 'sql213.infinityfree.com';
$dbname = 'if0_41408419_XXX';
$user = 'if0_41408419';
$pass = 'quibdoconecta'; // En producción, usa una contraseña fuerte

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?> 