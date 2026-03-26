<?php
session_start();
require_once __DIR__ . '/Php/db.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Diagnóstico Chat</title>
<style>
body { font-family: monospace; background: #0d1117; color: #c9d1d9; padding: 20px; }
.ok  { color: #3fb950; }
.err { color: #f85149; }
.warn{ color: #d29922; }
.box { background: #161b22; border: 1px solid #30363d; padding: 16px; border-radius: 8px; margin: 12px 0; }
h2   { color: #58a6ff; }
button { background: #238636; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; margin: 4px; }
button.blue { background: #1f6feb; }
pre { background: #0d1117; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
</style>
</head>
<body>
<h1>🔍 Diagnóstico del Chat</h1>

<div class="box">
<h2>1. Sesión PHP</h2>
<?php if (isset($_SESSION['usuario_id'])): ?>
  <p class="ok">✅ Sesión activa — usuario_id = <?= (int)$_SESSION['usuario_id'] ?></p>
<?php else: ?>
  <p class="err">❌ NO hay sesión activa. Debes iniciar sesión primero.</p>
  <p><a href="inicio_sesion.php" style="color:#58a6ff">→ Ir a iniciar sesión</a></p>
<?php endif; ?>
</div>

<?php if (isset($_SESSION['usuario_id'])): 
$yo = (int)$_SESSION['usuario_id'];
$db = getDB();
?>

<div class="box">
<h2>2. Conexión a la Base de Datos</h2>
<?php
try {
    $test = $db->query("SELECT 1")->fetch();
    echo '<p class="ok">✅ Conexión OK</p>';
} catch(Exception $e) {
    echo '<p class="err">❌ Error BD: ' . $e->getMessage() . '</p>';
}
?>
</div>

<div class="box">
<h2>3. Tu usuario en BD</h2>
<?php
$u = $db->prepare("SELECT id, nombre, apellido, tipo, activo FROM usuarios WHERE id=?");
$u->execute([$yo]);
$usr = $u->fetch();
if ($usr) {
    echo '<p class="ok">✅ Usuario encontrado: <strong>' . htmlspecialchars($usr['nombre'].' '.$usr['apellido']) . '</strong> (tipo: '.$usr['tipo'].', activo: '.$usr['activo'].')</p>';
} else {
    echo '<p class="err">❌ Tu usuario ID='.$yo.' NO existe en la BD</p>';
}
?>
</div>

<div class="box">
<h2>4. Conversaciones en BD</h2>
<?php
$conv = $db->prepare("
    SELECT u.id, u.nombre, u.apellido, COUNT(m.id) as total
    FROM mensajes m
    INNER JOIN usuarios u ON u.id = CASE WHEN m.de_usuario=? THEN m.para_usuario ELSE m.de_usuario END
    WHERE m.de_usuario=? OR m.para_usuario=?
    GROUP BY u.id, u.nombre, u.apellido
");
$conv->execute([$yo, $yo, $yo]);
$convs = $conv->fetchAll();
if ($convs) {
    echo '<p class="ok">✅ Tienes ' . count($convs) . ' conversación(es):</p><ul>';
    foreach ($convs as $c) {
        echo '<li>' . htmlspecialchars($c['nombre'].' '.$c['apellido']) . ' (ID='.$c['id'].') — '.$c['total'].' mensajes</li>';
    }
    echo '</ul>';
} else {
    echo '<p class="warn">⚠️ No hay mensajes en la BD para tu usuario (ID='.$yo.')</p>';
}
?>
</div>

<div class="box">
<h2>5. Test AJAX en vivo</h2>
<button onclick="testConversaciones()" class="blue">Test: cargar conversaciones</button>
<button onclick="testMensajes()">Test: abrir chat con ID 3 (Johan)</button>
<pre id="resultado">Presiona un botón...</pre>

<script>
async function testConversaciones() {
    document.getElementById('resultado').textContent = 'Cargando...';
    try {
        const r = await fetch('chat.php?action=conversaciones');
        const text = await r.text();
        document.getElementById('resultado').textContent = 
            'Status HTTP: ' + r.status + '\n\nRespuesta:\n' + text;
    } catch(e) {
        document.getElementById('resultado').textContent = 'ERROR: ' + e.message;
    }
}

async function testMensajes() {
    document.getElementById('resultado').textContent = 'Cargando...';
    
    const convUsers = <?= json_encode(array_map(fn($c) => $c['id'], $convs ?: [])) ?>;
    const userId = convUsers.length > 0 ? convUsers[0] : 3;
    document.getElementById('resultado').textContent = 'Probando con usuario ID=' + userId + '...\n';
    try {
        const r = await fetch('chat.php?action=mensajes&con=' + userId);
        const text = await r.text();
        document.getElementById('resultado').textContent = 
            'Status HTTP: ' + r.status + '\n\nRespuesta:\n' + text;
    } catch(e) {
        document.getElementById('resultado').textContent = 'ERROR: ' + e.message;
    }
}
</script>
</div>

<div class="box">
<h2>6. Información de sesión PHP</h2>
<pre><?php
echo 'session.gc_maxlifetime = ' . ini_get('session.gc_maxlifetime') . "\n";
echo 'session.cookie_lifetime = ' . ini_get('session.cookie_lifetime') . "\n";
echo 'session_id = ' . session_id() . "\n";
echo 'SESSION data = '; print_r($_SESSION);
?></pre>
</div>

<?php endif; ?>
</body>
</html>
