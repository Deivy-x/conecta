<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/Php/db.php';

try {
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            TRIM(CONCAT(u.nombre, ' ', COALESCE(u.apellido,''))) AS nombre,
            u.apellido,
            u.ciudad,
            u.foto,
            u.verificado,
            tp.tipo_servicio,
            tp.generos,
            tp.precio_desde,
            tp.avatar_color AS color
        FROM usuarios u
        INNER JOIN talento_perfil tp ON tp.id = (
            SELECT MAX(id) FROM talento_perfil
            WHERE usuario_id = u.id
              AND visible        = 1
              AND visible_admin  = 1
              AND destacado      = 1
              AND (
                  (tipo_servicio IS NOT NULL AND tipo_servicio <> '')
                  OR precio_desde IS NOT NULL
              )
        )
        WHERE u.activo = 1
        ORDER BY u.verificado DESC, u.id ASC
        LIMIT 4
    ");
    $stmt->execute();
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($servicios ?: []);

} catch (Exception $e) {
    echo json_encode([]);
}
