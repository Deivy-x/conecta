<?php
// ============================================================
// Php/badges_helper.php — Helper central de badges
// Incluir en cualquier página para obtener/mostrar badges
// ============================================================

/**
 * Obtiene los badges de un usuario desde la BD
 * Retorna array de badges con emoji, nombre, color
 */
function getBadgesUsuario(PDO $db, int $userId): array {
    try {
        // Obtener IDs de badges asignados
        $stmt = $db->prepare("SELECT badges_custom FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['badges_custom']) return [];

        $ids = json_decode($row['badges_custom'], true);
        if (!$ids || !is_array($ids)) return [];

        // Obtener info de cada badge del catálogo
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt2 = $db->prepare("SELECT id, nombre, emoji, color, tipo, descripcion FROM badges_catalog WHERE id IN ($placeholders) AND activo = 1");
        $stmt2->execute($ids);
        return $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Renderiza los badges de un usuario como HTML
 */
function renderBadges(array $badges, string $size = 'normal'): string {
    if (empty($badges)) return '';

    $esSmall = $size === 'small';
    $padding = $esSmall ? '2px 8px' : '4px 12px';
    $fontSize = $esSmall ? '11px' : '12px';
    $gap = $esSmall ? '4px' : '6px';

    $html = '<div style="display:flex;flex-wrap:wrap;gap:' . $gap . '">';
    foreach ($badges as $b) {
        $color  = htmlspecialchars($b['color'] ?? '#00e676');
        $emoji  = htmlspecialchars($b['emoji'] ?? '🏅');
        $nombre = htmlspecialchars($b['nombre'] ?? '');
        $desc   = htmlspecialchars($b['descripcion'] ?? '');
        $html .= '<span title="' . $desc . '" style="'
            . 'display:inline-flex;align-items:center;gap:4px;'
            . 'padding:' . $padding . ';'
            . 'background:' . $color . '22;'
            . 'border:1px solid ' . $color . '55;'
            . 'border-radius:20px;'
            . 'font-size:' . $fontSize . ';'
            . 'font-weight:700;'
            . 'color:' . $color . ';'
            . 'white-space:nowrap;'
            . '">' . $emoji . ' ' . $nombre . '</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Obtiene el badge más importante de un usuario (para mostrar uno solo)
 */
function getBadgePrincipal(array $badges): ?array {
    if (empty($badges)) return null;
    // Prioridad: verificacion > pago > manual
    $prioridad = ['verificacion' => 3, 'pago' => 2, 'manual' => 1];
    usort($badges, fn($a,$b) => ($prioridad[$b['tipo']]??0) - ($prioridad[$a['tipo']]??0));
    return $badges[0];
}

/**
 * Verifica si un usuario tiene un badge específico por nombre
 */
function tieneBadge(array $badges, string $nombre): bool {
    foreach ($badges as $b) {
        if (strtolower($b['nombre']) === strtolower($nombre)) return true;
    }
    return false;
}
?>
