<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require 'Php/config/db.php';

$errores = [];
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $rol      = trim($_POST['rol'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validaciones
    if (empty($nombre))                             $errores[] = "El nombre es obligatorio.";
    if (empty($apellido))                           $errores[] = "El apellido es obligatorio.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "El email no es válido.";
    if (empty($rol))                                $errores[] = "Selecciona un tipo de usuario.";
    if (strlen($password) < 8)                      $errores[] = "La contraseña debe tener mínimo 8 caracteres.";

    // Verificar email duplicado
    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errores[] = "Ese email ya está registrado.";
    }

    // Manejar foto de perfil
    $foto_path = '';
    if (!empty($_FILES['photo']['name'])) {
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $foto_nombre = uniqid('foto_') . '.' . $ext;
        $foto_path = 'uploads/' . $foto_nombre;
        move_uploaded_file($_FILES['photo']['tmp_name'], $foto_path);
    }

    // Manejar hoja de vida
    $cv_path = '';
    if (!empty($_FILES['cv']['name'])) {
        $ext_cv = pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION);
        $cv_nombre = uniqid('cv_') . '.' . $ext_cv;
        $cv_path = 'uploads/' . $cv_nombre;
        move_uploaded_file($_FILES['cv']['tmp_name'], $cv_path);
    }

    // Guardar en base de datos
    if (empty($errores)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nombre, apellido, email, password, rol, foto, cv)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $apellido, $email, $hash, $rol, $foto_path, $cv_path]);
        $exito = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro | Quibdó Conecta</title>
<link rel="stylesheet" href="css/registro.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="container">

    <div class="logo">
        <img src="Imagenes/Quibdo.png">
    </div>

    <h1>Crear cuenta</h1>

    <?php if ($exito): ?>
        <p style="color:green; text-align:center;">✅ ¡Cuenta creada exitosamente!</p>
        <p style="text-align:center;"><a href="inicio_sesion.php">Iniciar sesión</a></p>

    <?php else: ?>

        <?php foreach ($errores as $e): ?>
            <p style="color:red; text-align:center;">⚠️ <?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>

        <!-- enctype necesario para subir archivos -->
        <form id="registerForm" method="POST" action="" enctype="multipart/form-data">

            <div class="profile-upload">
                <img id="preview" src="https://cdn-icons-png.flaticon.com/512/149/149071.png">
                <br>
                <label>Foto de perfil <span class="required">*</span></label>
                <input type="file" id="photo" name="photo" accept="image/*">
            </div>

            <div class="row">
                <div class="input-group">
                    <label>Nombres <span class="required">*</span></label>
                    <input type="text" id="name" name="nombre" required>
                </div>
                <div class="input-group">
                    <label>Apellidos <span class="required">*</span></label>
                    <input type="text" id="lastname" name="apellido" required>
                </div>
            </div>

            <div class="input-group">
                <label>Correo electrónico <span class="required">*</span></label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="input-group">
                <label>Tipo de usuario <span class="required">*</span></label>
                <select id="role" name="rol" required>
                    <option value="">Seleccione...</option>
                    <option value="talento">Talento</option>
                    <option value="empresa">Empresa</option>
                </select>
            </div>

            <div class="input-group">
                <label>Contraseña <span class="required">*</span></label>
                <input type="password" id="password" name="password" required>
                <div class="strength">
                    <span id="strengthBar"></span>
                </div>
            </div>

            <div class="input-group">
                <label>Subir hoja de vida <span class="required">*</span></label>
                <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx">
            </div>

            <button class="btn" type="submit">Crear cuenta</button>

        </form>

        <div class="divider">o registrarse con</div>

        <button class="social" onclick="googleLogin()">
            <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg">
            Continuar con Google
        </button>

        <button class="social facebook" onclick="facebookLogin()">
            <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/facebook/facebook-original.svg">
            Continuar con Facebook
        </button>

        <div class="extra">
            ¿Ya tienes cuenta?
            <a href="inicio_sesion.php">Iniciar sesión</a>
        </div>

    <?php endif; ?>

</div>

<script>
photo.onchange = e => {
    const file = e.target.files[0]
    if(file){
        const reader = new FileReader()
        reader.onload = e => { preview.src = e.target.result }
        reader.readAsDataURL(file)
    }
}

password.oninput = () => {
    let val = password.value
    let strength = 0
    if(val.length > 7) strength++
    if(/[A-Z]/.test(val)) strength++
    if(/[0-9]/.test(val)) strength++
    if(/[^A-Za-z0-9]/.test(val)) strength++
    let width = strength * 25
    strengthBar.style.width = width + "%"
    if(width < 50) strengthBar.style.background = "red"
    else if(width < 75) strengthBar.style.background = "orange"
    else strengthBar.style.background = "lime"
}

registerForm.onsubmit = e => {
    if(password.value.length < 8){
        alert("La contraseña debe tener mínimo 8 caracteres")
        e.preventDefault()
    }
}

function googleLogin(){ alert("Aquí se conectará con Google OAuth") }
function facebookLogin(){ alert("Aquí se conectará con Facebook Login") }
</script>

</body>
</html>