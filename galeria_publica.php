<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/Php/db.php';

$uid = (int)($_GET['id'] ?? 0);
if (!$uid) { echo json_encode([]); exit; }

try {
    $pdo = getDB();

    $chk = $pdo->prepare("SELECT id FROM usuarios WHERE id=? AND activo=1");
    $chk->execute([$uid]);
    if (!$chk->fetch()) { echo json_encode([]); exit; }

    $pdo->exec("CREATE TABLE IF NOT EXISTS talento_galeria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo ENUM('foto','video') NOT NULL DEFAULT 'foto',
        archivo VARCHAR(255) NOT NULL DEFAULT '',
        url_video VARCHAR(500) DEFAULT NULL,
        titulo VARCHAR(150) DEFAULT NULL,
        descripcion TEXT DEFAULT NULL,
        orden TINYINT NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("
        SELECT id, tipo, archivo, url_video, titulo, descripcion
        FROM talento_galeria
        WHERE usuario_id=? AND activo=1
        ORDER BY orden ASC, id ASC
        LIMIT 30
    ");
    $stmt->execute([$uid]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($items ?: []);

} catch (Exception $e) {
    echo json_encode([]);
}