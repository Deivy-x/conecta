<?php
// Php/get_empleos_empresa.php — Convocatorias activas de una empresa
// Columnas reales: empresa_id, salario_texto, tipo_contrato, modalidad
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
        SELECT
            e.id,
            e.titulo,
            COALESCE(e.modalidad, e.tipo_contrato, '') AS modalidad,
            e.ciudad,
            COALESCE(e.salario_texto, '') AS salario,
            e.creado_en AS fecha_publicacion,
            LEFT(e.descripcion, 120) AS descripcion
        FROM empleos e
        WHERE e.empresa_id = :uid
          AND e.activo = 1
        ORDER BY e.creado_en DESC
        LIMIT 10
    ");
    $stmt->execute([':uid' => $uid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);
} catch (Exception $ex) {
    echo json_encode(['_error' => $ex->getMessage()]);
}