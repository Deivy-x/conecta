<?php

$path = __DIR__ . '/Php/badges_helper.php';
$resultados = [];

if (!file_exists($path)) {
    $resultados[] = '❌ Php/badges_helper.php NO existe. Ejecuta instalar_badges.php primero.';
} else {
    $contenido = file_get_contents($path);
    
    if (strpos($contenido, 'getBadgesUsuario') !== false) {
        $resultados[] = '✅ getBadgesUsuario() ya existe en el archivo';
    } else {
        
        $aliases = <<<'PHPCODE'

function getBadgesUsuario(PDO $db, int $userId): array {
    return getUserBadges($db, $userId);
}

function renderBadgesUsuario(array $badges, string $size = 'sm'): string {
    return renderBadgesHTML($badges, $size);
}

function obtenerBadgesJSON(PDO $db, int $userId): array {
    return getUserBadgesJSON($db, $userId);
}
PHPCODE;

        if (file_put_contents($path, $contenido . $aliases) !== false) {
            $resultados[] = '✅ Alias getBadgesUsuario(), renderBadgesUsuario(), obtenerBadgesJSON() AGREGADOS';
        } else {
            $resultados[] = '❌ ERROR al escribir el archivo';
        }
    }
    
    $resultados[] = '📄 Tamaño actual: ' . filesize($path) . ' bytes';
    
    $content = file_get_contents($path);
    preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);
    $resultados[] = '🔧 Funciones disponibles: ' . implode(', ', $matches[1]);
}

$talentosPath = __DIR__ . '/talentos.php';
if (file_exists($talentosPath)) {
    $talentosContent = file_get_contents($talentosPath);
    preg_match_all('/(?:getBadges|renderBadges|obtenerBadges|getUserBadges|renderBadgesHTML)\w*\s*\(/', $talentosContent, $matches2);
    $resultados[] = '📋 talentos.php usa funciones: ' . implode(', ', array_unique($matches2[0]));
    
    $lines = explode("\n", $talentosContent);
    $start = max(0, 65);
    $end = min(count($lines), 75);
    $context = '';
    for ($i = $start; $i < $end; $i++) {
        $lineNum = $i + 1;
        $prefix = ($lineNum == 70) ? '>>> ' : '    ';
        $context .= $prefix . $lineNum . ': ' . trim($lines[$i]) . "\n";
    }
    $resultados[] = "📝 talentos.php líneas 66-75:\n<pre>" . htmlspecialchars($context) . "</pre>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parche Badges — QuibdóConecta</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: white; padding: 40px; }
        .container { max-width: 700px; margin: 0 auto; }
        h1 { color: #2ecc71; margin-bottom: 20px; }
        .result { padding: 12px 16px; margin: 8px 0; background: rgba(255,255,255,0.05); border-radius: 10px; font-size: 14px; line-height: 1.6; }
        pre { background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; font-size: 12px; overflow-x: auto; margin-top: 8px; }
        .warning { background: rgba(255,200,50,0.1); border: 1px solid rgba(255,200,50,0.3); color: #ffd700; padding: 16px; border-radius: 10px; margin-top: 30px; }
        a { color: #2ecc71; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Parche Badges Helper</h1>
        <?php foreach ($resultados as $r): ?>
            <div class="result"><?= $r ?></div>
        <?php endforeach; ?>

        <div style="margin-top:24px;">
            <p>👉 <a href="talentos.php">Probar talentos.php ahora</a></p>
            <p>👉 <a href="dashboard.php">Ir al dashboard</a></p>
        </div>

        <div class="warning">
            ⚠️ <strong>IMPORTANTE:</strong> Borra <code>parche_badges.php</code> e <code>instalar_badges.php</code> después de usarlos.
        </div>
    </div>
</body>
</html>
