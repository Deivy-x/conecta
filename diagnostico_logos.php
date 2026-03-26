<?php
// ============================================================
// diagnostico_logos.php — Diagnóstico de logos de empresas
// INSTRUCCIONES: Sube este archivo a la raíz del proyecto
// Ábrelo en el navegador: tudominio.com/diagnostico_logos.php
// BORRARLO después de usarlo por seguridad
// ============================================================
session_start();

// Protección básica — solo admin o sin sesión
// Puedes quitar esto si no tienes login de admin
// if (empty($_SESSION['admin'])) die('Acceso denegado');

require_once __DIR__ . '/Php/db.php';
$db = getDB();

// ── 1. Leer todas las empresas con su logo ──────────────────
$stmt = $db->query("
    SELECT u.id, u.nombre, 
           ep.nombre_empresa, ep.logo, ep.avatar_color
    FROM usuarios u
    INNER JOIN perfiles_empresa ep ON ep.id = (
        SELECT MAX(id) FROM perfiles_empresa
        WHERE usuario_id = u.id AND visible = 1 AND visible_admin = 1
    )
    WHERE u.activo = 1 AND u.tipo = 'empresa'
    ORDER BY u.id ASC
    LIMIT 30
");
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 2. Verificar carpeta uploads/logos ─────────────────────
$carpeta = __DIR__ . '/uploads/logos/';
$carpetaExiste = is_dir($carpeta);
$archivosEnCarpeta = $carpetaExiste ? scandir($carpeta) : [];
$archivosEnCarpeta = array_filter($archivosEnCarpeta, fn($f) => !in_array($f, ['.', '..']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Diagnóstico Logos Empresas</title>
<style>
  body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; padding: 32px; color: #222; }
  h1 { color: #1a56db; }
  h2 { margin-top: 40px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
  .ok { color: #15803d; font-weight: 700; }
  .error { color: #dc2626; font-weight: 700; }
  .warn { color: #d97706; font-weight: 700; }
  table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
  th { background: #1a56db; color: white; padding: 12px 16px; text-align: left; font-size: 13px; }
  td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
  tr:last-child td { border-bottom: none; }
  .preview { width: 48px; height: 48px; border-radius: 10px; object-fit: cover; display: block; background: #e2e8f0; }
  .initials { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; color: white; font-size: 14px; }
  .tag { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .tag-ok { background: #dcfce7; color: #15803d; }
  .tag-err { background: #fee2e2; color: #dc2626; }
  .tag-empty { background: #fef3c7; color: #92400e; }
  .tag-url { background: #dbeafe; color: #1e40af; }
  .info-box { background: white; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
  .info-box p { margin: 6px 0; }
  code { background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
</style>
</head>
<body>

<h1>🔍 Diagnóstico de Logos de Empresas</h1>
<p style="color:#666">Generado el <?= date('d/m/Y H:i:s') ?> — <strong style="color:#dc2626">Borra este archivo después de usarlo</strong></p>

<!-- CARPETA -->
<h2>1. Carpeta uploads/logos/</h2>
<div class="info-box">
  <p><strong>Ruta absoluta:</strong> <code><?= htmlspecialchars($carpeta) ?></code></p>
  <p><strong>¿Existe?</strong> 
    <?php if ($carpetaExiste): ?>
      <span class="ok">✅ SÍ existe</span>
    <?php else: ?>
      <span class="error">❌ NO existe — los logos locales no pueden guardarse</span>
    <?php endif; ?>
  </p>
  <p><strong>Archivos en la carpeta:</strong> <?= count($archivosEnCarpeta) ?></p>
  <?php if ($archivosEnCarpeta): ?>
    <details>
      <summary style="cursor:pointer;color:#1a56db;font-weight:600">Ver archivos (<?= count($archivosEnCarpeta) ?>)</summary>
      <ul style="margin-top:8px;font-size:12px">
        <?php foreach (array_slice(array_values($archivosEnCarpeta), 0, 50) as $f): ?>
          <li><code><?= htmlspecialchars($f) ?></code></li>
        <?php endforeach; ?>
      </ul>
    </details>
  <?php endif; ?>
</div>

<!-- EMPRESAS -->
<h2>2. Campo "logo" en la BD (<?= count($empresas) ?> empresas)</h2>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Empresa</th>
      <th>Valor en BD (logo)</th>
      <th>Tipo detectado</th>
      <th>Ruta construida</th>
      <th>¿Archivo existe?</th>
      <th>Preview</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($empresas as $e):
    $logo = $e['logo'] ?? '';
    $nombre = trim($e['nombre_empresa'] ?: $e['nombre']);
    $ini = strtoupper(mb_substr($nombre, 0, 2));
    $grd = $e['avatar_color'] ?: 'linear-gradient(135deg,#1a56db,#3b82f6)';

    // Detectar tipo y construir ruta
    if (empty($logo)) {
      $tipo = 'empty';
      $rutaWeb = '';
      $rutaAbs = '';
      $existe = false;
    } elseif (str_starts_with($logo, 'http')) {
      $tipo = 'cloudinary';
      $rutaWeb = $logo;
      $rutaAbs = '(URL remota)';
      $existe = true; // asumimos que existe si hay URL
    } elseif (str_starts_with($logo, 'uploads/')) {
      $tipo = 'path_completo';
      $rutaWeb = $logo;
      $rutaAbs = __DIR__ . '/' . $logo;
      $existe = file_exists($rutaAbs);
    } else {
      $tipo = 'solo_nombre';
      $rutaWeb = 'uploads/logos/' . $logo;
      $rutaAbs = __DIR__ . '/uploads/logos/' . $logo;
      $existe = file_exists($rutaAbs);
    }
  ?>
    <tr>
      <td><?= $e['id'] ?></td>
      <td><strong><?= htmlspecialchars($nombre) ?></strong></td>
      <td><code><?= $logo ? htmlspecialchars(mb_substr($logo, 0, 60)) . (mb_strlen($logo) > 60 ? '…' : '') : '(vacío)' ?></code></td>
      <td>
        <?php if ($tipo === 'empty'): ?>
          <span class="tag tag-empty">Sin logo</span>
        <?php elseif ($tipo === 'cloudinary'): ?>
          <span class="tag tag-url">☁️ Cloudinary</span>
        <?php elseif ($tipo === 'path_completo'): ?>
          <span class="tag tag-ok">Path completo</span>
        <?php else: ?>
          <span class="tag tag-ok">Nombre archivo</span>
        <?php endif; ?>
      </td>
      <td>
        <?= $rutaWeb ? '<code>' . htmlspecialchars(mb_substr($rutaWeb, 0, 50)) . '</code>' : '—' ?>
      </td>
      <td>
        <?php if ($tipo === 'empty'): ?>
          <span class="warn">⚠️ No aplica</span>
        <?php elseif ($tipo === 'cloudinary'): ?>
          <span class="ok">☁️ URL remota</span>
        <?php elseif ($existe): ?>
          <span class="ok">✅ SÍ</span>
        <?php else: ?>
          <span class="error">❌ NO (archivo no encontrado)</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($rutaWeb && ($tipo === 'cloudinary' || $existe)): ?>
          <img src="<?= htmlspecialchars($rutaWeb) ?>" class="preview"
               onerror="this.style.display='none';this.nextSibling.style.display='flex'">
          <div class="initials" style="background:<?= htmlspecialchars($grd) ?>;display:none"><?= $ini ?></div>
        <?php else: ?>
          <div class="initials" style="background:<?= htmlspecialchars($grd) ?>"><?= $ini ?></div>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<!-- RESUMEN -->
<h2>3. Resumen del problema</h2>
<div class="info-box">
<?php
  $sinLogo = count(array_filter($empresas, fn($e) => empty($e['logo'])));
  $cloudinary = count(array_filter($empresas, fn($e) => str_starts_with($e['logo'] ?? '', 'http')));
  $localOk = 0; $localRoto = 0;
  foreach ($empresas as $e) {
    $logo = $e['logo'] ?? '';
    if (empty($logo) || str_starts_with($logo, 'http')) continue;
    $ruta = str_starts_with($logo, 'uploads/') ? __DIR__ . '/' . $logo : __DIR__ . '/uploads/logos/' . $logo;
    if (file_exists($ruta)) $localOk++; else $localRoto++;
  }
?>
  <p>📊 Total empresas analizadas: <strong><?= count($empresas) ?></strong></p>
  <p>⚪ Sin logo (campo vacío): <strong class="<?= $sinLogo > 0 ? 'warn' : 'ok' ?>"><?= $sinLogo ?></strong></p>
  <p>☁️ Con logo Cloudinary (URL): <strong class="ok"><?= $cloudinary ?></strong></p>
  <p>✅ Con logo local y archivo encontrado: <strong class="ok"><?= $localOk ?></strong></p>
  <p>❌ Con logo local pero archivo NO encontrado: <strong class="<?= $localRoto > 0 ? 'error' : 'ok' ?>"><?= $localRoto ?></strong></p>

  <?php if ($localRoto > 0): ?>
    <hr style="margin:16px 0;border:none;border-top:1px solid #e2e8f0">
    <p class="error">⚠️ PROBLEMA DETECTADO: Hay <?= $localRoto ?> empresa(s) con logo guardado en BD pero el archivo no existe en el servidor.</p>
    <p>Esto significa que los archivos fueron subidos a un servidor diferente o fueron borrados. La solución es que esas empresas suban su logo de nuevo desde el dashboard.</p>
  <?php elseif ($sinLogo === count($empresas)): ?>
    <p class="warn">⚠️ Todas las empresas tienen el campo logo vacío — nunca subieron un logo.</p>
  <?php elseif ($cloudinary > 0 && $localRoto === 0): ?>
    <p class="ok">✅ Los logos de Cloudinary deberían verse bien. Si no aparecen, puede ser un problema de red o de CORS.</p>
  <?php else: ?>
    <p class="ok">✅ No se detectaron problemas con los archivos.</p>
  <?php endif; ?>
</div>

</body>
</html>
