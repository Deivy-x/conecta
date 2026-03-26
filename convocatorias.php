<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/Php/db.php';

try {
    $pdo       = getDB();
    $categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : 'todas';
    $hoy       = date('Y-m-d');

    if ($categoria === 'todas') {
        $stmt = $pdo->prepare("
            SELECT id, entidad, categoria, titulo, vacantes,
                   modalidad, nivel, salario, lugar, requisito,
                   estado, icono,
                   DATE_FORMAT(vence_en, '%Y-%m-%d') AS vence
            FROM convocatorias
            WHERE activo = 1
              AND (vence_en IS NULL OR vence_en >= :hoy)
            ORDER BY
                CASE estado WHEN 'urgente' THEN 0 ELSE 1 END,
                vence_en ASC
            LIMIT 20
        ");
        $stmt->bindValue(':hoy', $hoy);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, entidad, categoria, titulo, vacantes,
                   modalidad, nivel, salario, lugar, requisito,
                   estado, icono,
                   DATE_FORMAT(vence_en, '%Y-%m-%d') AS vence
            FROM convocatorias
            WHERE activo    = 1
              AND categoria = :cat
              AND (vence_en IS NULL OR vence_en >= :hoy)
            ORDER BY
                CASE estado WHEN 'urgente' THEN 0 ELSE 1 END,
                vence_en ASC
            LIMIT 20
        ");
        $stmt->bindValue(':cat', $categoria);
        $stmt->bindValue(':hoy', $hoy);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['vacantes'] = (int)$r['vacantes'];
    }

    echo json_encode($rows);

} catch (Exception $e) {
    
    echo json_encode([]);
}
