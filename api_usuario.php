<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/Php/db.php';
require_once __DIR__ . '/Php/badges_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
    exit;
}

$db     = getDB();
$uid    = (int)$_SESSION['usuario_id'];
$action = $_GET['action'] ?? 'perfil';

if ($action === 'perfil') {
    $stmt = $db->prepare("
        SELECT u.id, u.nombre, u.apellido, u.correo, u.tipo, u.ciudad,
               u.telefono, u.cedula, u.foto, u.verificado, u.badges_custom,
               u.fecha_nacimiento, u.fecha_empresa, u.activo, u.creado_en,
               tp.profesion, tp.bio, tp.skills, tp.visible, tp.avatar_color
        FROM usuarios u
        LEFT JOIN talento_perfil tp ON tp.id = (
            SELECT MAX(id) FROM talento_perfil WHERE usuario_id = u.id
        )
        WHERE u.id = ? AND u.activo = 1
    ");
    $stmt->execute([$uid]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) { echo json_encode(['ok'=>false,'msg'=>'Usuario no encontrado']); exit; }

    $badges = getBadgesUsuario($db, $uid);
    $usuario['badges']          = $badges;
    $usuario['badge_principal'] = getBadgePrincipal($badges);
    $usuario['tiene_verificado']= (bool)$usuario['verificado'] || tieneBadge($badges,'Verificado') || tieneBadge($badges,'Usuario Verificado') || tieneBadge($badges,'Empresa Verificada');
    $usuario['tiene_premium']   = tieneBadge($badges,'Premium');
    $usuario['tiene_top']       = tieneBadge($badges,'Top');
    $usuario['tiene_destacado'] = tieneBadge($badges,'Destacado');
    $usuario['tiene_pro']       = tieneBadge($badges,'Pro');

    echo json_encode(['ok'=>true, 'usuario'=>$usuario]);
    exit;
}

if ($action === 'badges') {
    $badges = getBadgesUsuario($db, $uid);
    echo json_encode(['ok'=>true, 'badges'=>$badges, 'html'=>renderBadges($badges)]);
    exit;
}

if ($action === 'notificaciones') {
    $notifs = [];

    try {
        $msgs = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario = ? AND leido = 0");
        $msgs->execute([$uid]);
        $notifs['mensajes_noLeidos'] = (int)$msgs->fetchColumn();
    } catch(Exception $e) { $notifs['mensajes_noLeidos'] = 0; }

    try {
        $verif = $db->prepare("SELECT estado, nota_admin FROM verificaciones WHERE usuario_id = ? ORDER BY creado_en DESC LIMIT 1");
        $verif->execute([$uid]);
        $vRow = $verif->fetch(PDO::FETCH_ASSOC);
        $notifs['verificacion_estado'] = $vRow['estado'] ?? null;
        $notifs['verificacion_nota']   = $vRow['nota_admin'] ?? null;
    } catch(Exception $e) { $notifs['verificacion_estado'] = null; }

    $notifs['badges'] = getBadgesUsuario($db, $uid);
    $notifs['total_badges'] = count($notifs['badges']);

    echo json_encode(['ok'=>true, 'notificaciones'=>$notifs]);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Acción desconocida']);
?>