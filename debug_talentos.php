<?php
require_once __DIR__ . '/Php/db.php';
$db = getDB();

echo "<h2>Usuarios que se llaman Johan</h2><pre>";
$rows = $db->query("SELECT id, nombre, apellido, activo, tipo FROM usuarios WHERE nombre LIKE '%Johan%' OR apellido LIKE '%Santos%'")->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) print_r($r);
echo "</pre>";

echo "<h2>TODOS los usuarios con su talento_perfil</h2><pre>";
$rows = $db->query("
    SELECT u.id, u.nombre, u.apellido, u.activo, tp.id as tp_id, tp.visible, tp.visible_admin, tp.profesion
    FROM usuarios u
    LEFT JOIN talento_perfil tp ON tp.usuario_id = u.id
    ORDER BY u.id
")->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $r) {
    echo "u.id={$r['id']} nombre={$r['nombre']} {$r['apellido']} activo={$r['activo']} | tp_id={$r['tp_id']} visible={$r['visible']} visible_admin={$r['visible_admin']} profesion={$r['profesion']}\n";
}
echo "</pre>";

echo "<h2>Resultado FINAL query talentos.php</h2><pre>";
$final = $db->query("
    SELECT u.id, u.nombre, u.apellido, tp.profesion, tp.id as tp_id
    FROM usuarios u
    INNER JOIN talento_perfil tp ON tp.id = (
        SELECT MAX(id) FROM talento_perfil
        WHERE usuario_id = u.id AND visible = 1 AND visible_admin = 1
    )
    WHERE u.activo = 1
    ORDER BY u.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
foreach($final as $r) {
    echo "u.id={$r['id']} tp_id={$r['tp_id']} nombre={$r['nombre']} {$r['apellido']} profesion={$r['profesion']}\n";
}
echo "Total: ".count($final)."\n";
echo "</pre>";