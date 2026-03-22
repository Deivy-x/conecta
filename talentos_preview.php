<?php
/**
 * talentos_preview.php — Talentos para la sección "Conoce nuestros talentos" del index
 *
 * LÓGICA:
 *  1. Prioridad: talentos con destacado=1 (marcados en el panel admin)
 *  2. Si hay menos de 3 destacados, rellena con verificados visibles
 *  3. Máximo 3 tarjetas
 *
 * ADMIN: gestion-qbc-2025.php → Modal talento → toggle "⭐ Destacado en el inicio"
 *        o botón rápido "☆ Poner en index" en la tabla de candidatos
 *
 * BD: if0_41408419_quibdo — MariaDB 11.4
 * talento_perfil tiene UNIQUE KEY en usuario_id (una fila por usuario)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/Php/db.php';
require_once __DIR__ . '/Php/badges_helper.php';

try {
    $pdo = getDB();

    /*
     * JOIN directo — talento_perfil tiene UNIQUE KEY en usuario_id,
     * no se necesita MAX(id). Una fila por usuario.
     *
     * Condiciones para aparecer en el index:
     *   - usuario activo
     *   - perfil visible al público (visible=1) y al admin (visible_admin=1)
     *   - tiene profesión informada
     *
     * Orden: destacado DESC → verificado DESC → id ASC
     */
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

        // Badges sin verificación (para mostrar como badges de perfil)
        $badgesExtra = array_values(
            array_filter($badgesAll, fn($b) => ($b['tipo'] ?? '') !== 'verificacion')
        );

        $t['badges']          = $badgesExtra;
        $t['badge_principal'] = getBadgePrincipal($badgesExtra);

        // tiene_destacado: columna BD O badge "Destacado" del catálogo (id=4, nombre='Destacado')
        $t['tiene_destacado'] = (bool) $t['destacado']
                              || tieneBadge($badgesAll, 'Destacado');

        // tiene_verificado: usuarios.verificado O badge de verificación
        $t['tiene_verificado'] = (bool) $t['verificado']
                               || tieneBadge($badgesAll, 'Verificado')
                               || tieneBadge($badgesAll, 'Usuario Verificado')
                               || tieneBadge($badgesAll, 'Empresa Verificada');

        // Planes de pago según badges_catalog real de la BD
        $t['tiene_premium'] = tieneBadge($badgesAll, 'Amarillo Oro')
                            || tieneBadge($badgesAll, 'Azul Profundo')
                            || tieneBadge($badgesAll, 'Selva Verde');

        // Limpiar campos sensibles
        unset($t['badges_custom'], $t['verificado']);
    }
    unset($t);

    echo json_encode($talentos ?: [], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([]);
}