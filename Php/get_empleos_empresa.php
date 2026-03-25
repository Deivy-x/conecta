<?php
// get_empleos_empresa.php — Devuelve las convocatorias activas de una empresa
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$uid = (int) ($_GET['usuario_id'] ?? 0);

if ($uid <= 0) {
    echo json_encode([]);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    $db = getDB();

    $stmt = $db->prepare("
        SELECT e.id, e.titulo, e.modalidad, e.ciudad,
               e.salario, e.fecha_publicacion, e.descripcion
        FROM empleos e
        WHERE e.usuario_id = :uid
          AND e.activo = 1
        ORDER BY e.fecha_publicacion DESC
        LIMIT 10
    ");
    $stmt->execute([':uid' => $uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
} catch (Exception $ex) {
    echo json_encode([]);
}
