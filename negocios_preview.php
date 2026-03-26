<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/Php/db.php';

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.nombre,
            u.ciudad,
            u.verificado,
            nl.nombre_negocio,
            nl.categoria,
            nl.descripcion,
            nl.logo,
            nl.ubicacion,
            nl.whatsapp,
            nl.tipo_negocio,
            nl.avatar_color,
            nl.destacado
        FROM usuarios u
        INNER JOIN negocios_locales nl ON nl.id = (
            SELECT MAX(id) FROM negocios_locales
            WHERE usuario_id = u.id
              AND visible       = 1
              AND visible_admin = 1
        )
        WHERE u.activo = 1
        ORDER BY nl.destacado DESC, u.verificado DESC, u.id ASC
        LIMIT 6
    ");
    $stmt->execute();
    $negocios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($negocios ?: []);

} catch (Exception $e) {
    echo json_encode([]);
}
