<?php
session_start();
require 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($_POST['password'], $usuario['password'])) {
        $_SESSION['usuario_id']     = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Email o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>
</head>
<body>
    <h2>Iniciar sesión</h2>

    <?php if (isset($error)): ?>
        <p style="color:red">⚠️ <?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="email"    name="email"    placeholder="Email"      required><br><br>
        <input type="password" name="password" placeholder="Contraseña" required><br><br>
        <button type="submit">Entrar</button>
    </form>

    <p>¿No tienes cuenta? <a href="Registro.php">Regístrate</a></p>
</body>
</html>