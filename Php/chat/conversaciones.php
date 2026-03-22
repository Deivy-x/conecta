<?php
// ============================================================
// Php/chat/conversaciones.php — Listar conversaciones del usuario (GET)
// ============================================================
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión.']);
    exit;
}

$yo = (int) $_SESSION['usuario_id'];
$db = getDB();

// Obtener todos los usuarios con los que he tenido conversación
// Para cada uno: último mensaje, fecha, y cuántos no leídos
$stmt = $db->prepare("
    SELECT 
        u.id,
        u.nombre,
        u.apellido,
        u.foto,
        u.tipo,
        sub.ultimo_mensaje,
        sub.ultima_fecha,
        COALESCE(nr.no_leidos, 0) AS no_leidos
    FROM (
        SELECT 
            CASE 
                WHEN de_usuario = ? THEN para_usuario
                ELSE de_usuario
            END AS otro_id,
            MAX(creado_en) AS ultima_fecha
        FROM mensajes
        WHERE de_usuario = ? OR para_usuario = ?
        GROUP BY otro_id
    ) AS sub
    INNER JOIN usuarios u ON u.id = sub.otro_id
    LEFT JOIN (
        SELECT de_usuario, COUNT(*) AS no_leidos
        FROM mensajes
        WHERE para_usuario = ? AND leido = 0
        GROUP BY de_usuario
    ) AS nr ON nr.de_usuario = u.id
    LEFT JOIN mensajes um ON um.creado_en = sub.ultima_fecha 
        AND ((um.de_usuario = ? AND um.para_usuario = u.id) 
          OR (um.de_usuario = u.id AND um.para_usuario = ?))
    ORDER BY sub.ultima_fecha DESC
");
$stmt->execute([$yo, $yo, $yo, $yo, $yo, $yo]);
$conversaciones = $stmt->fetchAll();

// Obtener el último mensaje para cada conversación
foreach ($conversaciones as &$conv) {
    $msg = $db->prepare("
        SELECT mensaje, de_usuario, creado_en 
        FROM mensajes 
        WHERE (de_usuario = ? AND para_usuario = ?) 
           OR (de_usuario = ? AND para_usuario = ?)
        ORDER BY creado_en DESC 
        LIMIT 1
    ");
    $msg->execute([$yo, $conv['id'], $conv['id'], $yo]);
    $ultimo = $msg->fetch();
    $conv['ultimo_mensaje'] = $ultimo ? $ultimo['mensaje'] : '';
    $conv['ultima_fecha'] = $ultimo ? $ultimo['creado_en'] : $conv['ultima_fecha'];
    $conv['yo_envie_ultimo'] = $ultimo && (int)$ultimo['de_usuario'] === $yo;
}

// Contar total no leídos
$totalStmt = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario = ? AND leido = 0");
$totalStmt->execute([$yo]);
$totalNoLeidos = (int) $totalStmt->fetchColumn();

echo json_encode([
    'ok' => true,
    'conversaciones' => $conversaciones,
    'total_no_leidos' => $totalNoLeidos
]);
