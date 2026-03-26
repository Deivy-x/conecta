<?php

session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['usuario_id'])) {
    try {
        $db = getDB();
        $db->prepare("UPDATE usuarios SET ultima_salida = NOW() WHERE id = ?")
           ->execute([$_SESSION['usuario_id']]);
    } catch (Exception $e) {}
}

if (isset($_COOKIE['qc_remember'])) {
    try {
        $db = getDB();
        $db->prepare("DELETE FROM sesiones WHERE token = ?")->execute([$_COOKIE['qc_remember']]);
    } catch (Exception $e) {}
    setcookie('qc_remember', '', time() - 3600, '/');
}

$_SESSION = [];
session_destroy();
header('Location: ../inicio_sesion.php');
exit;
?>