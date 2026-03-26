<?php

session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

$correo   = trim($_POST['correo'] ?? '');
$pass     = $_POST['contrasena'] ?? '';
$recordar = isset($_POST['recordar']);

if (!$correo || !$pass) {
    echo json_encode(['ok' => false, 'msg' => 'Ingresa tu correo y contraseña.']);
    exit;
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'El correo no es válido.']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE correo = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($pass, $usuario['contrasena'])) {
        
        try {
            $sol = $db->prepare("SELECT estado FROM solicitudes_ingreso WHERE correo = ? ORDER BY creado_en DESC LIMIT 1");
            $sol->execute([$correo]);
            $solicitud = $sol->fetch();
            if ($solicitud) {
                if ($solicitud['estado'] === 'pendiente') {
                    echo json_encode(['ok' => false, 'solicitud' => 'pendiente', 'msg' => '⏳ Tu solicitud está pendiente de aprobación. El administrador la revisará pronto.']);
                    exit;
                }
                if ($solicitud['estado'] === 'rechazado') {
                    echo json_encode(['ok' => false, 'solicitud' => 'rechazado', 'msg' => '❌ Tu solicitud fue rechazada. Contacta al administrador o regístrate de nuevo.']);
                    exit;
                }
            }
        } catch (Exception $e) {  }
        echo json_encode(['ok' => false, 'msg' => 'Correo o contraseña incorrectos.']);
        exit;
    }

    $db->prepare("UPDATE usuarios SET ultima_sesion = NOW(), activo = 1 WHERE id = ?")
       ->execute([$usuario['id']]);

    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_tipo']   = $usuario['tipo'];
    $_SESSION['usuario_correo'] = $usuario['correo'];

    if ($recordar) {
        $token  = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+30 days'));
        $db->prepare("INSERT INTO sesiones (usuario_id, token, ip, user_agent, expira_en) VALUES (?,?,?,?,?)")
            ->execute([$usuario['id'], $token, $ip, $userAgent, $expira]);
        setcookie('qc_remember', $token, time() + (30 * 24 * 3600), '/', '', false, true);
    }

    $tipo = $usuario['tipo'];
    if ($tipo === 'admin') {
        $redirect = 'gestion-qbc-2025.php';
    } elseif ($tipo === 'empresa') {
        $redirect = 'dashboard_empresa.php';
    } else {
        $redirect = 'dashboard.php';
    }

    echo json_encode([
        'ok'        => true,
        'msg'       => '¡Bienvenido, ' . htmlspecialchars($usuario['nombre']) . '!',
        'tipo'      => $tipo,
        'nombre'    => $usuario['nombre'],
        'dashboard' => $redirect
    ]);

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error al iniciar sesión. Inténtalo de nuevo.']);
}
?>