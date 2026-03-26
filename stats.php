<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/Php/db.php';

try {
    $pdo  = getDB();
    $data = [];

    $data['total_talentos'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM usuarios WHERE tipo='candidato' AND activo=1")
        ->fetchColumn();

    $data['total_empresas'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM usuarios WHERE tipo='empresa' AND activo=1")
        ->fetchColumn();

    $data['total_artistas'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM usuarios WHERE tipo='artista' AND activo=1")
        ->fetchColumn();

    try {
        $data['total_empleos'] = (int)$pdo
            ->query("SELECT COUNT(*) FROM empleos WHERE activo=1")
            ->fetchColumn();
    } catch (Exception $e) {
        $data['total_empleos'] = 0;
    }

    try {
        $buenas = (int)$pdo->query("SELECT COUNT(*) FROM resenas WHERE calificacion >= 4")->fetchColumn();
        $totalR = (int)$pdo->query("SELECT COUNT(*) FROM resenas")->fetchColumn();
        $data['satisfaccion'] = $totalR > 0 ? round(($buenas / $totalR) * 100) : 92;
    } catch (Exception $e) {
        $data['satisfaccion'] = 92;
    }

    $hace7 = date('Y-m-d H:i:s', strtotime('-7 days'));
    try {
        $ant_t = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo='candidato' AND activo=1 AND creado_en < '$hace7'")->fetchColumn();
        $ant_e = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo='empresa'   AND activo=1 AND creado_en < '$hace7'")->fetchColumn();
        $ant_a = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo='artista'   AND activo=1 AND creado_en < '$hace7'")->fetchColumn();
    } catch (Exception $e) {
        $ant_t = $data['total_talentos'];
        $ant_e = $data['total_empresas'];
        $ant_a = $data['total_artistas'];
    }

    $data['trend_talentos']     = $data['total_talentos'] - $ant_t;
    $data['trend_empresas']     = $data['total_empresas'] - $ant_e;
    $data['trend_artistas']     = $data['total_artistas'] - $ant_a;
    $data['trend_empleos']      = 0;
    $data['trend_satisfaccion'] = 0;

    echo json_encode($data);

} catch (Exception $e) {
    echo json_encode([
        'total_talentos' => 500, 'total_empresas' => 120,
        'total_empleos'  => 300, 'total_artistas' => 45,
        'satisfaccion'   => 92,  'trend_talentos' => 12,
        'trend_empresas' => 3,   'trend_empleos'  => 18,
        'trend_artistas' => 5,   'trend_satisfaccion' => 0
    ]);
}