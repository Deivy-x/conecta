<?php

$resultados = [];

if (!is_dir(__DIR__ . '/Php')) {
    mkdir(__DIR__ . '/Php', 0755, true);
    $resultados[] = '✅ Carpeta Php/ creada';
} else {
    $resultados[] = '📁 Carpeta Php/ ya existe';
}

if (!is_dir(__DIR__ . '/uploads/verificaciones')) {
    mkdir(__DIR__ . '/uploads/verificaciones', 0755, true);
    $resultados[] = '✅ Carpeta uploads/verificaciones/ creada';
}
if (!is_dir(__DIR__ . '/uploads/fotos')) {
    mkdir(__DIR__ . '/uploads/fotos', 0755, true);
    $resultados[] = '✅ Carpeta uploads/fotos/ creada';
}

$badges_helper = <<<'PHPCODE'
<?php

function getUserBadges(PDO $db, int $userId): array {
    $stmt = $db->prepare("SELECT badges_custom FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['badges_custom'])) {
        return [];
    }

    $badgeIds = json_decode($row['badges_custom'], true);
    if (!is_array($badgeIds) || empty($badgeIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($badgeIds), '?'));
    $stmt = $db->prepare(
        "SELECT id, nombre, emoji, color, descripcion, tipo 
         FROM badges_catalog 
         WHERE id IN ($placeholders) AND activo = 1
         ORDER BY id ASC"
    );
    $stmt->execute($badgeIds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function renderBadgesHTML(array $badges, string $size = 'sm'): string {
    if (empty($badges)) return '';

    $sizes = [
        'sm' => ['font' => '10px', 'pad' => '2px 8px', 'gap' => '4px'],
        'md' => ['font' => '12px', 'pad' => '4px 12px', 'gap' => '6px'],
        'lg' => ['font' => '14px', 'pad' => '6px 16px', 'gap' => '8px'],
    ];
    $s = $sizes[$size] ?? $sizes['sm'];

    $html = '<span style="display:inline-flex;flex-wrap:wrap;gap:'.$s['gap'].';align-items:center;">';
    foreach ($badges as $b) {
        $color = htmlspecialchars($b['color'] ?? '#1f9d55');
        $emoji = htmlspecialchars($b['emoji'] ?? '🏅');
        $nombre = htmlspecialchars($b['nombre'] ?? '');
        $html .= '<span style="display:inline-flex;align-items:center;gap:3px;'
            . 'background:' . $color . '18;'
            . 'color:' . $color . ';'
            . 'border:1px solid ' . $color . '35;'
            . 'font-size:' . $s['font'] . ';'
            . 'font-weight:700;'
            . 'padding:' . $s['pad'] . ';'
            . 'border-radius:20px;'
            . 'white-space:nowrap;"'
            . ' title="' . $nombre . '">'
            . $emoji . ' ' . $nombre
            . '</span>';
    }
    $html .= '</span>';
    return $html;
}

function getUserBadgesJSON(PDO $db, int $userId): array {
    return getUserBadges($db, $userId);
}

function assignBadge(PDO $db, int $userId, int $badgeId): bool {
    $check = $db->prepare("SELECT id FROM badges_catalog WHERE id = ? AND activo = 1");
    $check->execute([$badgeId]);
    if (!$check->fetch()) return false;

    $stmt = $db->prepare("SELECT badges_custom FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return false;

    $current = json_decode($row['badges_custom'] ?? '[]', true) ?: [];
    if (in_array($badgeId, $current)) return true;

    $current[] = $badgeId;
    $update = $db->prepare("UPDATE usuarios SET badges_custom = ? WHERE id = ?");
    return $update->execute([json_encode($current), $userId]);
}

function removeBadge(PDO $db, int $userId, int $badgeId): bool {
    $stmt = $db->prepare("SELECT badges_custom FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return false;

    $current = json_decode($row['badges_custom'] ?? '[]', true) ?: [];
    $current = array_values(array_filter($current, fn($id) => $id != $badgeId));

    $update = $db->prepare("UPDATE usuarios SET badges_custom = ? WHERE id = ?");
    return $update->execute([json_encode($current), $userId]);
}
PHPCODE;

$path = __DIR__ . '/Php/badges_helper.php';
if (file_put_contents($path, $badges_helper) !== false) {
    $resultados[] = '✅ Php/badges_helper.php CREADO (' . strlen($badges_helper) . ' bytes)';
} else {
    $resultados[] = '❌ ERROR al crear Php/badges_helper.php';
}

if (file_exists(__DIR__ . '/Php/db.php')) {
    $resultados[] = '✅ Php/db.php existe';
} else {
    $resultados[] = '⚠️ Php/db.php NO existe (necesario para la BD)';
}

if (file_exists(__DIR__ . '/talentos.php')) {
    $resultados[] = '✅ talentos.php existe';
} else {
    $resultados[] = '⚠️ talentos.php NO existe';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador QuibdóConecta</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { color: #2ecc71; margin-bottom: 20px; }
        .result { padding: 12px 16px; margin: 8px 0; background: rgba(255,255,255,0.05); border-radius: 10px; font-size: 14px; }
        .warning { background: rgba(255,200,50,0.1); border: 1px solid rgba(255,200,50,0.3); color: #ffd700; padding: 16px; border-radius: 10px; margin-top: 30px; }
        a { color: #2ecc71; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛠️ Instalador QuibdóConecta</h1>
        <p style="color:rgba(255,255,255,0.6);margin-bottom:24px;">Resultados de la instalación:</p>
        
        <?php foreach ($resultados as $r): ?>
            <div class="result"><?= $r ?></div>
        <?php endforeach; ?>

        <div style="margin-top:24px;">
            <p>👉 <a href="talentos.php">Probar talentos.php ahora</a></p>
            <p>👉 <a href="dashboard.php">Ir al dashboard</a></p>
        </div>

        <div class="warning">
            ⚠️ <strong>IMPORTANTE:</strong> Borra este archivo (<code>instalar_badges.php</code>) después de usarlo por seguridad.
        </div>
    </div>
</body>
</html>
