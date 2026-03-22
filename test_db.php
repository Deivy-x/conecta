<?php
// ============================================
// test_db.php — Diagnóstico de conexión
// ELIMINA ESTE ARCHIVO después de usarlo
// ============================================
$host = 'sql213.infinityfree.com';
$dbname = 'if0_41408419_quibdo';
$user = 'if0_41408419';
$pass = 'quibdoconecta';

echo "<h2>🔍 Diagnóstico QuibdóConecta</h2>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;} .ok{color:#2ecc71} .err{color:#e74c3c} .warn{color:#f39c12} table{border-collapse:collapse;width:100%} td{padding:8px 12px;border:1px solid #333}</style>";

// 1. Test conexión PDO
echo "<h3>1. Conexión a MySQL</h3>";
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p class='ok'>✅ Conexión exitosa a <b>$host</b></p>";
} catch (PDOException $e) {
    echo "<p class='err'>❌ Error de conexión: <b>" . htmlspecialchars($e->getMessage()) . "</b></p>";
    die("<p class='warn'>⚠️ No se puede continuar sin conexión.</p>");
}

// 2. Verificar tablas
echo "<h3>2. Tablas en la base de datos</h3>";
$tablas_requeridas = ['usuarios', 'perfiles_candidato', 'perfiles_empresa', 'sesiones'];
$stmt = $pdo->query("SHOW TABLES");
$tablas_existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "<table><tr><th>Tabla requerida</th><th>Estado</th></tr>";
foreach ($tablas_requeridas as $t) {
    $existe = in_array($t, $tablas_existentes);
    echo "<tr><td>$t</td><td class='" . ($existe ? 'ok' : 'err') . "'>" . ($existe ? '✅ Existe' : '❌ NO EXISTE') . "</td></tr>";
}
echo "</table>";

// 3. Verificar columnas de usuarios
echo "<h3>3. Columnas de la tabla 'usuarios'</h3>";
if (in_array('usuarios', $tablas_existentes)) {
    $stmt = $pdo->query("DESCRIBE usuarios");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $nombres = array_column($cols, 'Field');
    $requeridas = ['id', 'nombre', 'apellido', 'correo', 'contrasena', 'tipo', 'activo'];
    echo "<table><tr><th>Columna requerida</th><th>Estado</th></tr>";
    foreach ($requeridas as $c) {
        $existe = in_array($c, $nombres);
        echo "<tr><td>$c</td><td class='" . ($existe ? 'ok' : 'err') . "'>" . ($existe ? '✅ Existe' : '❌ FALTA') . "</td></tr>";
    }
    echo "</table>";
    echo "<p>Todas las columnas: <b>" . implode(', ', $nombres) . "</b></p>";
} else {
    echo "<p class='err'>❌ La tabla 'usuarios' no existe.</p>";
}

// 4. Test de INSERT real
echo "<h3>4. Test de inserción</h3>";
if (in_array('usuarios', $tablas_existentes)) {
    try {
        $hash = password_hash('test1234', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellido, correo, contrasena, telefono, ciudad, tipo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['TestNombre', 'TestApellido', 'test_diagnostico@quibdo.com', $hash, '3001234567', 'Quibdó', 'candidato']);
        $id = $pdo->lastInsertId();
        echo "<p class='ok'>✅ INSERT exitoso — ID creado: <b>$id</b></p>";

        // Crear perfil candidato
        $pdo->prepare("INSERT INTO perfiles_candidato (usuario_id) VALUES (?)")->execute([$id]);
        echo "<p class='ok'>✅ Perfil candidato creado correctamente</p>";

        // Limpiar test
        $pdo->prepare("DELETE FROM usuarios WHERE correo = ?")->execute(['test_diagnostico@quibdo.com']);
        echo "<p class='ok'>✅ Registro de prueba eliminado limpiamente</p>";

    } catch (PDOException $e) {
        echo "<p class='err'>❌ Error en INSERT: <b>" . htmlspecialchars($e->getMessage()) . "</b></p>";
        echo "<p class='warn'>👆 Este es el error exacto que causa el fallo en el registro</p>";
    }
}

// 5. Versión PHP
echo "<h3>5. Entorno del servidor</h3>";
echo "<p>PHP: <b>" . phpversion() . "</b></p>";
echo "<p>PDO drivers: <b>" . implode(', ', PDO::getAvailableDrivers()) . "</b></p>";

echo "<hr><p class='warn'>⚠️ <b>IMPORTANTE:</b> Elimina este archivo del servidor después de usarlo.</p>";
?>