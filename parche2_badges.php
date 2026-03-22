<?php
// ============================================================
// parche2_badges.php — Agrega renderBadges() y tieneBadge()
// ============================================================
// Sube a htdocs/ y visita. BORRAR DESPUÉS.
// ============================================================

$path = __DIR__ . '/Php/badges_helper.php';
$resultados = [];

if (!file_exists($path)) {
    $resultados[] = '❌ Php/badges_helper.php NO existe.';
} else {
    $contenido = file_get_contents($path);
    $agregados = [];
    
    // Agregar renderBadges() si no existe
    if (strpos($contenido, 'function renderBadges(') === false) {
        $contenido .= <<<'PHPCODE'

// ── renderBadges() — alias usado en talentos.php ──
function renderBadges(array $badges, string $size = 'small'): string {
    // Mapear tamaños del servidor a los del helper
    $map = ['small' => 'sm', 'medium' => 'md', 'large' => 'lg', 'sm' => 'sm', 'md' => 'md', 'lg' => 'lg'];
    $mappedSize = $map[$size] ?? 'sm';
    return renderBadgesHTML($badges, $mappedSize);
}
PHPCODE;
        $agregados[] = 'renderBadges()';
    }
    
    // Agregar tieneBadge() si no existe
    if (strpos($contenido, 'function tieneBadge(') === false) {
        $contenido .= <<<'PHPCODE'

// ── tieneBadge() — verifica si un usuario tiene un badge específico ──
function tieneBadge(array $badges, string $nombreBadge): bool {
    foreach ($badges as $b) {
        if (stripos($b['nombre'] ?? '', $nombreBadge) !== false) {
            return true;
        }
        if (stripos($b['tipo'] ?? '', $nombreBadge) !== false) {
            return true;
        }
    }
    return false;
}
PHPCODE;
        $agregados[] = 'tieneBadge()';
    }
    
    if (!empty($agregados)) {
        if (file_put_contents($path, $contenido) !== false) {
            $resultados[] = '✅ Funciones agregadas: ' . implode(', ', $agregados);
        } else {
            $resultados[] = '❌ Error al escribir';
        }
    } else {
        $resultados[] = '✅ Todas las funciones ya existían';
    }
    
    // Listar todas las funciones disponibles
    preg_match_all('/function\s+(\w+)\s*\(/', $contenido, $matches);
    $resultados[] = '🔧 Funciones disponibles: ' . implode(', ', $matches[1]);
    $resultados[] = '📄 Tamaño: ' . strlen($contenido) . ' bytes';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Parche 2 — QuibdóConecta</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; padding: 40px; }
        .container { max-width: 700px; margin: 0 auto; }
        h1 { color: #2ecc71; margin-bottom: 20px; }
        .result { padding: 12px 16px; margin: 8px 0; background: rgba(255,255,255,0.05); border-radius: 10px; font-size: 14px; }
        a { color: #2ecc71; display:block; margin:8px 0; }
        .warning { background: rgba(255,200,50,0.1); border: 1px solid rgba(255,200,50,0.3); color: #ffd700; padding: 16px; border-radius: 10px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Parche 2 — renderBadges + tieneBadge</h1>
        <?php foreach ($resultados as $r): ?>
            <div class="result"><?= $r ?></div>
        <?php endforeach; ?>
        <div style="margin-top:24px;">
            <a href="talentos.php">👉 Probar talentos.php</a>
            <a href="dashboard.php">👉 Ir al dashboard</a>
        </div>
        <div class="warning">⚠️ Borra parche2_badges.php, parche_badges.php e instalar_badges.php después.</div>
    </div>
</body>
</html>
