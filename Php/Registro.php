<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'Php/config/db.php';

$errores = [];
$exito   = false;
$nombre  = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre    = trim($_POST['nombre'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    // Validaciones
    if (empty($nombre))                             $errores[] = "El nombre es obligatorio.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El email no es válido.";
    if (strlen($password) < 8)                      $errores[] = "La contraseña debe tener mínimo 8 caracteres.";
    if ($password !== $confirmar)                   $errores[] = "Las contraseñas no coinciden.";

    // Verificar que el email no exista
    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errores[] = "Ese email ya está registrado.";
    }

    // Guardar en la base de datos
    if (empty($errores)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $email, $hash]);
        $exito = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro</title>
</head>
<body>
    <h2>Crear cuenta</h2>

    <?php if ($exito): ?>
        <p style="color:green">✅ ¡Cuenta creada exitosamente!</p>
        <a href="login.php">Iniciar sesión</a>

    <?php else: ?>
        <?php foreach ($errores as $e): ?>
            <p style="color:red">⚠️ <?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>

        <form method="POST">
            <label>Nombre:</label><br>
            <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required><br><br>

            <label>Email:</label><br>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required><br><br>

            <label>Contraseña:</label><br>
            <input type="password" name="password" required><br><br>

            <label>Confirmar contraseña:</label><br>
            <input type="password" name="confirmar" required><br><br>

            <button type="submit">Registrarse</button>
        </form>
    <?php endif; ?>
</body>
</html>
```

---
