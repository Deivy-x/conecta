<?php
session_start();
date_default_timezone_set('America/Bogota');

try {
    $pdo = new PDO(
        'mysql:host=sql213.infinityfree.com;dbname=if0_41408419_quibdo;charset=utf8mb4',
        'if0_41408419',
        'quibdoconecta',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch(Exception $e) {
    die("Error BD: " . $e->getMessage());
}

$correo = 'cuestadeivy9@outlook.com';
$passIntento = $_POST['pass'] ?? '';
$resultado = '';

if ($passIntento) {
    $stmt = $pdo->prepare("SELECT u.*, ar.nivel FROM usuarios u INNER JOIN admin_roles ar ON ar.usuario_id = u.id WHERE u.correo = ?");
    $stmt->execute([$correo]);
    $u = $stmt->fetch();

    $resultado .= "<p>Usuario encontrado: " . ($u ? '✅ Sí' : '❌ No') . "</p>";
    if ($u) {
        $resultado .= "<p>Hash en BD: <code>" . htmlspecialchars(substr($u['contrasena'],0,30)) . "...</code></p>";
        $resultado .= "<p>password_verify resultado: " . (password_verify($passIntento, $u['contrasena']) ? '✅ CORRECTO' : '❌ INCORRECTO') . "</p>";
        $resultado .= "<p>nivel: <strong>" . $u['nivel'] . "</strong></p>";
        $resultado .= "<p>activo: <strong>" . $u['activo'] . "</strong></p>";

        if (password_verify($passIntento, $u['contrasena'])) {
            $_SESSION['admin_id']     = $u['id'];
            $_SESSION['admin_nivel']  = $u['nivel'];
            $_SESSION['admin_nombre'] = $u['nombre'];
            $resultado .= "<p style='color:lime;font-size:18px'>✅ LOGIN EXITOSO — <a href='gestion-qbc-2025.php' style='color:lime'>Ir al panel →</a></p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Test Login Admin</title>
<style>
body{font-family:monospace;background:#0d1117;color:#e6edf3;padding:40px;max-width:500px;margin:0 auto}
input{width:100%;padding:12px;background:#161b22;border:1px solid #30363d;border-radius:8px;color:#e6edf3;font-size:14px;margin-bottom:12px;font-family:monospace}
button{width:100%;padding:12px;background:#3fb950;color:#000;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer}
p{margin:8px 0;font-size:14px}
code{background:#21262d;padding:2px 6px;border-radius:4px}
</style>
</head>
<body>
<h2 style="color:#3fb950">🔐 Test Login Admin</h2>
<p style="color:#8b949e">Correo fijo: <?= $correo ?></p>
<form method="POST">
  <input type="password" name="pass" placeholder="Contraseña a probar..." autofocus required>
  <button type="submit">Probar →</button>
</form>
<?php if ($resultado): ?>
<div style="margin-top:20px;padding:16px;background:#161b22;border-radius:8px;border:1px solid #30363d">
<?= $resultado ?>
</div>
<?php endif; ?>
<p style="margin-top:20px;color:#d29922;font-size:11px">⚠️ Borrar después de usar</p>
</body>
</html>
