<?php
session_start();
require 'Php/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($password, $usuario['password'])) {
        $_SESSION['usuario_id']     = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        header('Location: Php/dashboard.php');
        exit;
    } else {
        $error = "Correo o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar Sesión | Quibdó Conecta</title>
<link rel="stylesheet" href="css/inicio_sesion.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<canvas id="particles"></canvas>

<div class="container">

    <div class="logo">
        <img src="Imagenes/Quibdo.png">
    </div>

    <h1>Iniciar Sesión</h1>

    <?php if ($error): ?>
        <p style="color:red; text-align:center;">⚠️ <?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- CAMBIO CLAVE: method POST y action vacío para que envíe al mismo archivo -->
    <form id="loginForm" method="POST" action="">

        <div class="input-group">
            <label>Correo electrónico</label>
            <input type="email" name="email" id="email" placeholder="correo@email.com" required>
        </div>

        <div class="input-group">
            <label>Contraseña</label>
            <input type="password" name="password" id="password" placeholder="********" required>
            <span class="toggle" onclick="togglePassword()">Mostrar</span>
        </div>

        <button class="btn" type="submit">Ingresar</button>

    </form>

    <div class="extra">
        ¿No tienes cuenta?
        <a href="Php/Registro.php">Regístrate</a>
    </div>

</div>

<script>
function togglePassword(){
    let input = document.getElementById("password")
    input.type = input.type === "password" ? "text" : "password"
}

const canvas = document.getElementById("particles")
const ctx = canvas.getContext("2d")
canvas.width = window.innerWidth
canvas.height = window.innerHeight

let particles = []
for(let i = 0; i < 80; i++){
    particles.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 2,
        dx: (Math.random() - 0.5) * 0.5,
        dy: (Math.random() - 0.5) * 0.5
    })
}

function draw(){
    ctx.clearRect(0, 0, canvas.width, canvas.height)
    ctx.fillStyle = "white"
    particles.forEach(p => {
        ctx.beginPath()
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2)
        ctx.fill()
        p.x += p.dx
        p.y += p.dy
    })
    requestAnimationFrame(draw)
}
draw()
</script>

</body>
</html>