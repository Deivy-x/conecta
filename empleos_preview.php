<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/Php/db.php';

try {
    $pdo = getDB();

    $stmt = $pdo->query("
        SELECT
            e.id,
            e.titulo,
            e.categoria,
            e.barrio,
            e.ciudad,
            e.salario_min,
            e.salario_max,
            e.modalidad,
            e.tipo,
            e.destacado,
            e.creado_en,
            e.vence_en,
            COALESCE(pe.nombre_empresa, u.nombre) AS empresa,
            pe.logo AS empresa_logo
        FROM empleos e
        LEFT JOIN perfiles_empresa pe ON pe.usuario_id = e.empresa_id
        LEFT JOIN usuarios u          ON u.id = e.empresa_id
        WHERE e.activo = 1
          AND (e.vence_en IS NULL OR e.vence_en >= CURDATE())
        ORDER BY
            e.destacado DESC,
            e.creado_en DESC
        LIMIT 4
    ");
    $empleos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($empleos ?: [], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([]);
}
