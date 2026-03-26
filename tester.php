<?php
session_start();
date_default_timezone_set("America/Bogota");

$dbOk = false;
$dbMsg = '';
$tablas = [];
$columnasMensajes = [];

try {
    $pdo = new PDO(
        'mysql:host=sql213.infinityfree.com;dbname=if0_41408419_quibdo;charset=utf8mb4',
        'if0_41408419',
        'quibdoconecta',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    $dbOk = true;
    $dbMsg = 'Conexión exitosa';

    $tablas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('mensajes', $tablas)) {
        $cols = $pdo->query("DESCRIBE mensajes")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) $columnasMensajes[] = $c['Field'] . ' (' . $c['Type'] . ')';
    }
} catch (Exception $e) {
    $dbMsg = $e->getMessage();
}

$archivos = [
    'Php/db.php'              => 'BD: conexión',
    'chat.php'                => 'Chat: página principal',
    'chat_conversaciones.php' => 'Chat: conversaciones',
    'chat_mensajes.php'       => 'Chat: mensajes',
    'chat_enviar.php'         => 'Chat: enviar',
    'chat_buscar.php'         => 'Chat: buscar usuarios',
    'Php/chat/conversaciones.php' => 'Chat (ruta vieja): conversaciones',
    'Php/chat/mensajes.php'   => 'Chat (ruta vieja): mensajes',
    'Php/chat/enviar.php'     => 'Chat (ruta vieja): enviar',
    'dashboard.php'           => 'Dashboard',
    'inicio_sesion.php'       => 'Login',
    'verificar_cuenta.php'    => 'Verificación',
];

$sesionActiva = isset($_SESSION['usuario_id']);
$sesionId     = $_SESSION['usuario_id'] ?? null;

$uploadDir = 'uploads/verificaciones/';
$uploadWritable = is_dir($uploadDir) && is_writable($uploadDir);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>🔧 Tester — QuibdóConecta</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; padding: 24px; }
  h1 { font-size: 22px; margin-bottom: 4px; color: #fff; }
  .sub { color: #94a3b8; font-size: 13px; margin-bottom: 28px; }
  .card { background: #1e293b; border-radius: 14px; padding: 20px 24px; margin-bottom: 16px; border: 1px solid #334155; }
  .card h2 { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #94a3b8; margin-bottom: 14px; }
  .row { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid #1e3a5f22; font-size: 14px; }
  .row:last-child { border-bottom: none; }
  .label { flex: 1; color: #cbd5e1; }
  .val { font-size: 13px; color: #94a3b8; max-width: 340px; text-align: right; word-break: break-all; }
  .ok  { color: #4ade80; font-weight: 700; }
  .err { color: #f87171; font-weight: 700; }
  .warn { color: #fbbf24; font-weight: 700; }
  .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; }
  .badge.ok  { background: #14532d; color: #4ade80; }
  .badge.err { background: #450a0a; color: #f87171; }
  .badge.warn { background: #451a03; color: #fbbf24; }
  .tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
  .tag { background: #0f2a4a; color: #7dd3fc; font-size: 12px; padding: 3px 10px; border-radius: 8px; }
  .tag.missing { background: #3b0f0f; color: #fca5a5; }
  .footer { margin-top: 28px; color: #475569; font-size: 12px; text-align: center; }
  .test-btn { margin-top: 12px; background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; width: 100%; }
  .test-btn:hover { background: #1d4ed8; }
  #ajax-result { margin-top: 12px; font-size: 13px; color: #94a3b8; white-space: pre-wrap; background: #0f172a; padding: 12px; border-radius: 8px; display: none; }
</style>
</head>
<body>

<h1>🔧 Tester — QuibdóConecta</h1>
<p class="sub">Diagnóstico en tiempo real del servidor · <?= date('d/m/Y H:i:s') ?></p>

<!-- BASE DE DATOS -->
<div class="card">
  <h2>🗄️ Base de datos</h2>
  <div class="row">
    <span class="label">Conexión a MySQL</span>
    <span class="<?= $dbOk ? 'ok' : 'err' ?>"><?= $dbOk ? '✅ OK' : '❌ FALLO' ?></span>
  </div>
  <div class="row">
    <span class="label">Mensaje</span>
    <span class="val"><?= htmlspecialchars($dbMsg) ?></span>
  </div>
  <?php if ($dbOk): ?>
  <div class="row">
    <span class="label">Tablas encontradas (<?= count($tablas) ?>)</span>
  </div>
  <div class="tags">
    <?php
    $requeridas = ['usuarios','mensajes','talento_perfil','convocatorias','verificaciones','admin_roles'];
    foreach ($tablas as $t):
      $clase = in_array($t, $requeridas) ? 'tag' : 'tag';
    ?>
      <span class="tag"><?= htmlspecialchars($t) ?></span>
    <?php endforeach; ?>
    <?php foreach ($requeridas as $r): ?>
      <?php if (!in_array($r, $tablas)): ?>
        <span class="tag missing">❌ falta: <?= $r ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php if ($columnasMensajes): ?>
  <div class="row" style="margin-top:12px">
    <span class="label">Columnas de tabla <code>mensajes</code></span>
  </div>
  <div class="tags">
    <?php
    $colsReq = ['id','de_usuario','para_usuario','mensaje','leido','creado_en'];
    foreach ($columnasMensajes as $col):
      $nombre = explode(' ', $col)[0];
      $falta = !in_array($nombre, $colsReq);
    ?>
      <span class="tag <?= $falta ? '' : '' ?>"><?= htmlspecialchars($col) ?></span>
    <?php endforeach; ?>
    <?php foreach ($colsReq as $cr): ?>
      <?php $tiene = array_filter($columnasMensajes, fn($c) => str_starts_with($c, $cr)); ?>
      <?php if (!$tiene): ?>
        <span class="tag missing">❌ falta columna: <?= $cr ?></span>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php elseif ($dbOk): ?>
  <div class="row"><span class="label err">❌ La tabla <code>mensajes</code> NO existe</span></div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- SESIÓN -->
<div class="card">
  <h2>🔐 Sesión PHP</h2>
  <div class="row">
    <span class="label">Estado sesión</span>
    <span class="<?= $sesionActiva ? 'ok' : 'warn' ?>"><?= $sesionActiva ? '✅ Activa' : '⚠️ Sin sesión (no estás logueado)' ?></span>
  </div>
  <?php if ($sesionActiva): ?>
  <div class="row">
    <span class="label">usuario_id en sesión</span>
    <span class="ok"><?= $sesionId ?></span>
  </div>
  <?php endif; ?>
  <div class="row">
    <span class="label">session_start() funciona</span>
    <span class="ok">✅ Sí</span>
  </div>
</div>

<!-- ARCHIVOS -->
<div class="card">
  <h2>📁 Archivos del servidor</h2>
  <?php foreach ($archivos as $path => $desc): ?>
  <div class="row">
    <span class="label"><?= $desc ?></span>
    <span class="val" style="color:#64748b"><?= $path ?></span>
    <?php if (file_exists(__DIR__ . '/' . $path)): ?>
      <span class="badge ok">✅ existe</span>
    <?php else: ?>
      <span class="badge err">❌ falta</span>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- UPLOADS -->
<div class="card">
  <h2>📂 Carpeta uploads</h2>
  <div class="row">
    <span class="label">uploads/verificaciones/ existe</span>
    <span class="<?= is_dir('uploads/verificaciones/') ? 'ok' : 'err' ?>"><?= is_dir('uploads/verificaciones/') ? '✅ Sí' : '❌ No' ?></span>
  </div>
  <div class="row">
    <span class="label">uploads/verificaciones/ tiene permisos de escritura</span>
    <span class="<?= $uploadWritable ? 'ok' : 'warn' ?>"><?= $uploadWritable ? '✅ Sí' : '⚠️ No (o no existe)' ?></span>
  </div>
</div>

<!-- TEST AJAX EN VIVO -->
<div class="card">
  <h2>🌐 Test AJAX en vivo</h2>
  <p style="font-size:13px;color:#94a3b8;margin-bottom:8px">Prueba si los endpoints del chat responden correctamente desde el navegador.</p>
  <button class="test-btn" onclick="testAjax('chat.php?action=conversaciones')">▶ Probar chat.php?action=conversaciones</button>
  <button class="test-btn" style="margin-top:8px;background:#065f46" onclick="testAjax('chat.php?action=buscar&q=test')">▶ Probar chat.php?action=buscar</button>
  <div id="ajax-result"></div>
</div>

<!-- PHP INFO BÁSICO -->
<div class="card">
  <h2>⚙️ Entorno PHP</h2>
  <div class="row"><span class="label">Versión PHP</span><span class="ok"><?= PHP_VERSION ?></span></div>
  <div class="row"><span class="label">PDO MySQL disponible</span><span class="<?= extension_loaded('pdo_mysql') ? 'ok' : 'err' ?>"><?= extension_loaded('pdo_mysql') ? '✅ Sí' : '❌ No' ?></span></div>
  <div class="row"><span class="label">FileInfo (uploads)</span><span class="<?= extension_loaded('fileinfo') ? 'ok' : 'warn' ?>"><?= extension_loaded('fileinfo') ? '✅ Sí' : '⚠️ No' ?></span></div>
  <div class="row"><span class="label">mbstring</span><span class="<?= extension_loaded('mbstring') ? 'ok' : 'err' ?>"><?= extension_loaded('mbstring') ? '✅ Sí' : '❌ No' ?></span></div>
  <div class="row"><span class="label">Zona horaria</span><span class="val"><?= date_default_timezone_get() ?></span></div>
  <div class="row"><span class="label">Directorio raíz</span><span class="val"><?= htmlspecialchars(__DIR__) ?></span></div>
</div>

<p class="footer">⚠️ Borra este archivo del servidor cuando termines el diagnóstico.</p>

<script>
async function testAjax(url) {
  const el = document.getElementById('ajax-result');
  el.style.display = 'block';
  el.textContent = '⏳ Cargando ' + url + '...';
  try {
    const res = await fetch(url);
    const status = res.status;
    const text = await res.text();
    let parsed = '';
    try { parsed = JSON.stringify(JSON.parse(text), null, 2); } catch(e) { parsed = text.substring(0, 500); }
    el.textContent = `HTTP ${status} — ${url}\n\n${parsed}`;
    el.style.color = res.ok ? '#4ade80' : '#f87171';
  } catch(e) {
    el.textContent = '❌ Error de red: ' + e.message;
    el.style.color = '#f87171';
  }
}
</script>
</body>
</html>