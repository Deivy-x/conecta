<?php

$resultados = [];

$base      = __DIR__ . '/uploads/';
$logosDir  = __DIR__ . '/uploads/logos/';
$fotosDir  = __DIR__ . '/uploads/fotos/';

if (!is_dir($base)) {
    if (@mkdir($base, 0755, true)) {
        $resultados[] = ['ok', "✅ Carpeta <code>uploads/</code> creada"];
    } else {
        $resultados[] = ['error', "❌ No se pudo crear <code>uploads/</code> — permisos insuficientes"];
    }
} else {
    $resultados[] = ['ok', "✅ Carpeta <code>uploads/</code> ya existe"];
}

if (!is_dir($logosDir)) {
    if (@mkdir($logosDir, 0755, true)) {
        $resultados[] = ['ok', "✅ Carpeta <code>uploads/logos/</code> creada correctamente"];
    } else {
        $resultados[] = ['error', "❌ No se pudo crear <code>uploads/logos/</code> — debes crearla manualmente en el panel de hosting"];
    }
} else {
    $resultados[] = ['ok', "✅ Carpeta <code>uploads/logos/</code> ya existe"];
}

if (!is_dir($fotosDir)) {
    if (@mkdir($fotosDir, 0755, true)) {
        $resultados[] = ['ok', "✅ Carpeta <code>uploads/fotos/</code> creada correctamente"];
    } else {
        $resultados[] = ['warn', "⚠️ No se pudo crear <code>uploads/fotos/</code>"];
    }
} else {
    $resultados[] = ['ok', "✅ Carpeta <code>uploads/fotos/</code> ya existe"];
}

if (is_dir($logosDir)) {
    $testFile = $logosDir . 'test_write_' . time() . '.tmp';
    if (@file_put_contents($testFile, 'test') !== false) {
        @unlink($testFile);
        $resultados[] = ['ok', "✅ Escritura en <code>uploads/logos/</code> funciona correctamente"];
    } else {
        $resultados[] = ['error', "❌ La carpeta existe pero NO tiene permisos de escritura — cambia los permisos a 755 en tu panel de hosting (cPanel, Plesk, etc.)"];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Crear carpeta logos</title>
<style>
  body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; padding: 40px; }
  .card { background: white; border-radius: 14px; padding: 32px; max-width: 620px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,.1); }
  h1 { color: #1a56db; margin-top: 0; }
  .row { padding: 12px 16px; border-radius: 8px; margin: 8px 0; font-size: 15px; }
  .row.ok    { background: #dcfce7; color: #15803d; }
  .row.error { background: #fee2e2; color: #dc2626; }
  .row.warn  { background: #fef3c7; color: #92400e; }
  code { background: rgba(0,0,0,.08); padding: 2px 6px; border-radius: 4px; font-size: 13px; }
  .delete-notice { margin-top: 28px; padding: 16px; background: #fff7ed; border: 2px solid #fb923c; border-radius: 10px; color: #9a3412; font-weight: 600; }
  .next-steps { margin-top: 24px; background: #eff6ff; border-radius: 10px; padding: 16px 20px; }
  .next-steps h3 { margin: 0 0 10px; color: #1a56db; }
  .next-steps ol { margin: 0; padding-left: 20px; }
  .next-steps li { margin: 6px 0; font-size: 14px; }
</style>
</head>
<body>
<div class="card">
  <h1>🗂️ Crear carpetas de uploads</h1>
  <p style="color:#666">Servidor: <code><?= htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'localhost') ?></code> — <?= date('d/m/Y H:i:s') ?></p>

  <?php foreach ($resultados as [$tipo, $msg]): ?>
    <div class="row <?= $tipo ?>"><?= $msg ?></div>
  <?php endforeach; ?>

  <?php
  $todoOk = !in_array('error', array_column($resultados, 0));
  if ($todoOk): ?>
    <div class="next-steps">
      <h3>✅ ¡Listo! ¿Qué sigue?</h3>
      <ol>
        <li>Borra este archivo del servidor</li>
        <li>Las empresas que no tienen logo deben ir a su dashboard y subir su logo</li>
        <li>La empresa <strong>QuibdoConecta</strong> (ID 15) necesita volver a subir su logo porque el archivo anterior se perdió</li>
      </ol>
    </div>
  <?php else: ?>
    <div class="next-steps">
      <h3>⚠️ Acción manual requerida</h3>
      <ol>
        <li>Ve al <strong>panel de hosting</strong> (cPanel, Plesk o similar)</li>
        <li>Abre el <strong>Administrador de Archivos</strong></li>
        <li>Navega a la carpeta raíz del proyecto <code>/app/</code></li>
        <li>Crea la carpeta <code>uploads</code> y dentro de ella <code>logos</code></li>
        <li>Asigna permisos <strong>755</strong> a ambas carpetas</li>
        <li>Vuelve a abrir este archivo para verificar</li>
      </ol>
    </div>
  <?php endif; ?>

  <div class="delete-notice">
    🗑️ IMPORTANTE: Borra <code>crear_carpeta_logos.php</code> del servidor después de usarlo
  </div>
</div>
</body>
</html>
