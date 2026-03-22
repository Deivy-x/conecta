<?php
// diagnostico.php — Sube este archivo al servidor y abre la URL
// BORRA este archivo después de usarlo
require_once __DIR__ . '/Php/db.php';
$db = getDB();

echo "<style>body{font-family:monospace;padding:20px;background:#f9f9f9}
table{border-collapse:collapse;width:100%}
td,th{border:1px solid #ccc;padding:6px 10px;text-align:left}
th{background:#1f9d55;color:white}
.ok{color:green;font-weight:bold}
.err{color:red;font-weight:bold}
h2{margin-top:30px;border-bottom:2px solid #1f9d55;padding-bottom:6px}
</style>";

echo "<h1>🔍 Diagnóstico QuibdóConecta</h1>";

// ── 1. ESTADO DE talento_perfil ──────────────────────────────
echo "<h2>1. Todas las filas en talento_perfil</h2>";
$rows = $db->query("SELECT id, usuario_id, visible, visible_admin, profesion, creado_en FROM talento_perfil ORDER BY usuario_id, id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>id</th><th>usuario_id</th><th>visible</th><th>visible_admin</th><th>profesion</th><th>creado_en</th></tr>";
$prev = null; $dup = false;
foreach ($rows as $r) {
    $isDup = ($prev === $r['usuario_id']);
    if ($isDup) $dup = true;
    $style = $isDup ? 'style="background:#ffe0e0"' : '';
    echo "<tr $style><td>{$r['id']}</td><td>{$r['usuario_id']}</td><td>{$r['visible']}</td><td>{$r['visible_admin']}</td><td>{$r['profesion']}</td><td>{$r['creado_en']}</td></tr>";
    $prev = $r['usuario_id'];
}
echo "</table>";
echo $dup ? "<p class='err'>❌ HAY FILAS DUPLICADAS POR usuario_id</p>" : "<p class='ok'>✅ Sin duplicados en talento_perfil</p>";

// ── 2. DUPLICADOS EXPLÍCITOS ─────────────────────────────────
echo "<h2>2. Usuarios con más de 1 fila en talento_perfil</h2>";
$dups = $db->query("SELECT usuario_id, COUNT(*) as total FROM talento_perfil GROUP BY usuario_id HAVING COUNT(*) > 1")->fetchAll(PDO::FETCH_ASSOC);
if ($dups) {
    echo "<table><tr><th>usuario_id</th><th>filas</th></tr>";
    foreach ($dups as $d) echo "<tr><td>{$d['usuario_id']}</td><td class='err'>{$d['total']}</td></tr>";
    echo "</table>";
    echo "<p class='err'>❌ Hay duplicados — ejecuta fix_duplicados.sql de nuevo</p>";
} else {
    echo "<p class='ok'>✅ Sin duplicados</p>";
}

// ── 3. RESULTADO EXACTO DE LA QUERY DE talentos.php ─────────
echo "<h2>3. Resultado de la query de talentos.php (lo que realmente se muestra)</h2>";
$final = $db->query("
    SELECT u.id, CONCAT(u.nombre,' ',u.apellido) AS nombre,
           tp.id AS tp_id, tp.visible, tp.visible_admin, tp.profesion
    FROM usuarios u
    INNER JOIN talento_perfil tp ON tp.id = (
        SELECT MAX(id) FROM talento_perfil
        WHERE usuario_id = u.id
          AND visible = 1
          AND visible_admin = 1
    )
    WHERE u.activo = 1
    ORDER BY u.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "<table><tr><th>u.id</th><th>nombre</th><th>tp_id</th><th>visible</th><th>visible_admin</th><th>profesion</th></tr>";
$ids = [];
foreach ($final as $r) {
    $isDup = in_array($r['id'], $ids);
    $style = $isDup ? 'style="background:#ffe0e0"' : '';
    echo "<tr $style><td>{$r['id']}</td><td>{$r['nombre']}</td><td>{$r['tp_id']}</td><td>{$r['visible']}</td><td>{$r['visible_admin']}</td><td>{$r['profesion']}</td></tr>";
    $ids[] = $r['id'];
}
echo "</table>";
$hay_dup = count($ids) !== count(array_unique($ids));
echo $hay_dup ? "<p class='err'>❌ LA QUERY DEVUELVE DUPLICADOS</p>" : "<p class='ok'>✅ Sin duplicados en la query (".count($final)." talentos)</p>";

// ── 4. ÍNDICES DE talento_perfil ─────────────────────────────
echo "<h2>4. Índices de talento_perfil</h2>";
$idx = $db->query("SHOW INDEX FROM talento_perfil")->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>Key_name</th><th>Column</th><th>Non_unique</th></tr>";
foreach ($idx as $i) {
    $isUnique = $i['Non_unique'] == 0;
    echo "<tr><td>{$i['Key_name']}</td><td>{$i['Column_name']}</td><td>".($isUnique ? "<span class='ok'>UNIQUE</span>" : "NO")."</td></tr>";
}
echo "</table>";

// ── 5. USUARIOS ACTIVOS ──────────────────────────────────────
echo "<h2>5. Usuarios activos con su talento_perfil</h2>";
$us = $db->query("
    SELECT u.id, CONCAT(u.nombre,' ',u.apellido) AS nombre, u.activo,
           COUNT(tp.id) AS filas_tp,
           SUM(tp.visible=1 AND tp.visible_admin=1) AS filas_visibles
    FROM usuarios u
    LEFT JOIN talento_perfil tp ON tp.usuario_id = u.id
    WHERE u.activo = 1
    GROUP BY u.id
    ORDER BY u.id
")->fetchAll(PDO::FETCH_ASSOC);
echo "<table><tr><th>u.id</th><th>nombre</th><th>filas en talento_perfil</th><th>filas visibles</th></tr>";
foreach ($us as $u) {
    $style = $u['filas_tp'] > 1 ? 'style="background:#ffe0e0"' : '';
    echo "<tr $style><td>{$u['id']}</td><td>{$u['nombre']}</td><td>{$u['filas_tp']}</td><td>{$u['filas_visibles']}</td></tr>";
}
echo "</table>";

echo "<hr><p style='color:#888;font-size:12px'>⚠️ Borra este archivo del servidor después de usarlo.</p>";
