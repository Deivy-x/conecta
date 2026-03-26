<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/Php/db.php';
require_once __DIR__ . '/Php/badges_helper.php';

try {
    $pdo = getDB();

    $stmt = $pdo->query("
        SELECT
            u.id,
            TRIM(CONCAT(u.nombre, ' ', u.apellido)) AS nombre,
            u.tipo,
            u.ciudad,
            u.foto,
            u.verificado,
            u.badges_custom,
            tp.profesion,
            tp.skills           AS habilidades,
            tp.avatar_color     AS color,
            tp.bio,
            tp.destacado,
            tp.calificacion,
            tp.total_resenas,
            tp.tipo_servicio,
            tp.precio_desde,
            tp.generos
        FROM usuarios u
        INNER JOIN talento_perfil tp ON tp.usuario_id = u.id
        WHERE u.activo        = 1
          AND tp.visible       = 1
          AND tp.visible_admin = 1
          AND tp.profesion     IS NOT NULL
          AND tp.profesion     != ''
        ORDER BY
            tp.destacado  DESC,
            u.verificado  DESC,
            u.id          ASC
        LIMIT 3
    ");
    $talentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($talentos as &$t) {
        $uid       = (int) $t['id'];
        $badgesAll = getBadgesUsuario($pdo, $uid);

        $badgesExtra = array_values(
            array_filter($badgesAll, fn($b) => ($b['tipo'] ?? '') !== 'verificacion')
        );

        $t['badges']          = $badgesExtra;
        $t['badge_principal'] = getBadgePrincipal($badgesExtra);

        $t['tiene_destacado'] = (bool) $t['destacado']
                              || tieneBadge($badgesAll, 'Destacado');

        $t['tiene_verificado'] = (bool) $t['verificado']
                               || tieneBadge($badgesAll, 'Verificado')
                               || tieneBadge($badgesAll, 'Usuario Verificado')
                               || tieneBadge($badgesAll, 'Empresa Verificada');

        $t['tiene_premium'] = tieneBadge($badgesAll, 'Amarillo Oro')
                            || tieneBadge($badgesAll, 'Azul Profundo')
                            || tieneBadge($badgesAll, 'Selva Verde');

        unset($t['badges_custom'], $t['verificado']);
    }
    unset($t);

    echo json_encode($talentos ?: [], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([]);
}