<?php
// reset_admin.php — BORRAR DESPUÉS DE USAR
require_once __DIR__ . '/Php/db.php';
$correo = 'cuestadeivy9@outlook.com';

try {
    $db = getDB();
    $cols = $db->query("DESCRIBE usuarios")->fetchAll(PDO::FETCH_ASSOC);

    $colPass = '';
    foreach ($cols as $c) {
        $f = strtolower($c['Field']);
        if (strpos($f,'pass') !== false || strpos($f,'clave') !== false || strpos($f,'pwd') !== false || strpos($f,'contrase') !== false) {
            $colPass = $c['Field'];
        }
    }

    $stmt = $db->prepare("SELECT * FROM usuarios WHERE correo = ?");
    $stmt->execute([$correo]);
    $usuario = $stmt->fetch();

    $resetOk = false;
    if ($colPass && $usuario) {
        $hash = password_hash('Admin2025*QBC', PASSWORD_DEFAULT);
        $db->prepare("UPDATE usuarios SET `$colPass` = ? WHERE correo = ?")->execute([$hash, $correo]);
        $rol = $db->prepare("SELECT * FROM admin_roles WHERE usuario_id = ?");
        $rol->execute([$usuario['id']]);
        if (!$rol->fetch()) {
            $db->prepare("INSERT INTO admin_roles (usuario_id, nivel) VALUES (?, 'superadmin')")->execute([$usuario['id']]);
        }
        $db->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?")->execute([$usuario['id']]);
        $resetOk = true;
    }
} catch (Exception $e) {
    die("<pre style='color:red;padding:20px'>Error: " . $e->getMessage() . "</pre>");
}
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Reset Admin</title>
<style>
body{font-family:monospace;background:#0d1117;color:#e6edf3;padding:40px;max-width:700px;margin:0 auto}
.card{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:24px;margin-bottom:16px}
h2{color:#3fb950;margin-bottom:14px;font-size:16px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#21262d;padding:8px 12px;text-align:left;color:#8b949e;font-size:11px}
td{padding:8px 12px;border-bottom:1px solid #21262d;word-break:break-all}
.hi td{background:rgba(63,185,80,.1);color:#3fb950}
.pass-box{background:#0d1117;border:1px solid #3fb950;border-radius:8px;padding:16px;text-align:center;margin:16px 0}
.pass{font-size:24px;color:#3fb950;letter-spacing:3px;margin:8px 0;font-weight:700}
.btn{display:inline-block;margin-top:12px;padding:12px 28px;background:#3fb950;color:#000;border-radius:8px;text-decoration:none;font-weight:700}
.warn{background:#3d1f00;border:1px solid #d29922;border-radius:8px;padding:12px 16px;color:#d29922;font-size:12px;margin-top:16px}
</style>
</head>
<body>
<div class="card">
  <h2>🔍 Columnas de tabla usuarios</h2>
  <table>
    <tr><th>Campo</th><th>Tipo</th><th>Key</th></tr>
    <?php foreach ($cols as $c): ?>
    <tr class="<?= $c['Field'] === $colPass ? 'hi' : '' ?>">
      <td><?= htmlspecialchars($c['Field']) ?><?= $c['Field'] === $colPass ? ' ✅ ← contraseña detectada' : '' ?></td>
      <td><?= htmlspecialchars($c['Type']) ?></td>
      <td><?= $c['Key'] ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php if ($usuario): ?>
<div class="card">
  <h2>👤 Tu usuario</h2>
  <table>
    <?php foreach ($usuario as $k => $v): ?>
    <tr>
      <td style="color:#8b949e;width:160px"><?= htmlspecialchars($k) ?></td>
      <td><?= htmlspecialchars(mb_strlen((string)$v) > 80 ? mb_substr($v,0,80).'...' : (string)$v) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php if ($resetOk): ?>
<div class="card">
  <h2>✅ Reset exitoso — columna: <?= htmlspecialchars($colPass) ?></h2>
  <div class="pass-box">
    <small style="color:#8b949e">Nueva contraseña para el panel admin:</small>
    <div class="pass">Admin2025*QBC</div>
  </div>
  <a href="gestion-qbc-2025.php" class="btn">→ Ir al panel de admin</a>
</div>
<?php else: ?>
<div class="card" style="border-color:#d29922">
  <h2 style="color:#d29922">⚠️ No se detectó columna de contraseña automáticamente</h2>
  <p style="font-size:13px;color:#8b949e">Manda captura de las columnas de arriba a Claude.</p>
</div>
<?php endif; ?>

<div class="warn">⚠️ BORRA este archivo del servidor después de usarlo.</div>
</body>
</html>