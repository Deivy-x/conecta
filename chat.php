<?php

ini_set('session.gc_maxlifetime', 604800);
ini_set('session.cookie_lifetime', 604800);
session_set_cookie_params(['lifetime' => 604800, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
session_start();
require_once __DIR__ . '/Php/db.php';

$action = $_GET['action'] ?? '';

if ($action) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'Sesión expirada.']);
        exit;
    }
    $yo = (int) $_SESSION['usuario_id'];
    $db = getDB();

    if ($action === 'conversaciones') {
        $stmt = $db->prepare("SELECT u.id,u.nombre,u.apellido,u.foto,u.tipo,sub.ultima_fecha,COALESCE(nr.no_leidos,0) AS no_leidos FROM (SELECT CASE WHEN de_usuario=? THEN para_usuario ELSE de_usuario END AS otro_id,MAX(creado_en) AS ultima_fecha FROM mensajes WHERE de_usuario=? OR para_usuario=? GROUP BY otro_id) AS sub INNER JOIN usuarios u ON u.id=sub.otro_id LEFT JOIN (SELECT de_usuario,COUNT(*) AS no_leidos FROM mensajes WHERE para_usuario=? AND leido=0 GROUP BY de_usuario) AS nr ON nr.de_usuario=u.id ORDER BY sub.ultima_fecha DESC");
        $stmt->execute([$yo, $yo, $yo, $yo]);
        $conversaciones = $stmt->fetchAll();
        foreach ($conversaciones as &$conv) {
            $msg = $db->prepare("SELECT mensaje,de_usuario,creado_en FROM mensajes WHERE (de_usuario=? AND para_usuario=?) OR (de_usuario=? AND para_usuario=?) ORDER BY creado_en DESC LIMIT 1");
            $msg->execute([$yo, $conv['id'], $conv['id'], $yo]);
            $ultimo = $msg->fetch();
            $conv['ultimo_mensaje'] = $ultimo ? $ultimo['mensaje'] : '';
            $conv['ultima_fecha'] = $ultimo ? $ultimo['creado_en'] : $conv['ultima_fecha'];
            $conv['yo_envie_ultimo'] = $ultimo && (int) $ultimo['de_usuario'] === $yo;
        }
        $total = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario=? AND leido=0");
        $total->execute([$yo]);
        echo json_encode(['ok' => true, 'conversaciones' => $conversaciones, 'total_no_leidos' => (int) $total->fetchColumn()]);
        exit;
    }

    if ($action === 'mensajes') {
        $con = (int) ($_GET['con'] ?? 0);
        if (!$con) {
            echo json_encode(['ok' => false, 'msg' => 'Falta ID.']);
            exit;
        }
        $db->prepare("UPDATE mensajes SET leido=1 WHERE de_usuario=? AND para_usuario=? AND leido=0")->execute([$con, $yo]);
        $stmt = $db->prepare("SELECT m.id,m.de_usuario,m.para_usuario,m.mensaje,m.leido,m.creado_en FROM mensajes m WHERE (m.de_usuario=? AND m.para_usuario=?) OR (m.de_usuario=? AND m.para_usuario=?) ORDER BY m.creado_en ASC LIMIT 100");
        $stmt->execute([$yo, $con, $con, $yo]);
        $info = $db->prepare("SELECT id,nombre,apellido,foto,tipo FROM usuarios WHERE id=? AND activo=1");
        $info->execute([$con]);
        echo json_encode(['ok' => true, 'usuario' => $info->fetch() ?: null, 'mensajes' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'enviar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $para = (int) ($_POST['para_usuario'] ?? 0);
        $mensaje = trim($_POST['mensaje'] ?? '');
        if (!$para || $para === $yo) {
            echo json_encode(['ok' => false, 'msg' => 'Destinatario inválido.']);
            exit;
        }
        if ($mensaje === '') {
            echo json_encode(['ok' => false, 'msg' => 'Mensaje vacío.']);
            exit;
        }
        if (mb_strlen($mensaje) > 2000) {
            echo json_encode(['ok' => false, 'msg' => 'Mensaje muy largo.']);
            exit;
        }
        $check = $db->prepare("SELECT id FROM usuarios WHERE id=? AND activo=1");
        $check->execute([$para]);
        if (!$check->fetch()) {
            echo json_encode(['ok' => false, 'msg' => 'Usuario no existe.']);
            exit;
        }
        $db->prepare("INSERT INTO mensajes (de_usuario,para_usuario,mensaje) VALUES (?,?,?)")->execute([$yo, $para, $mensaje]);
        echo json_encode(['ok' => true, 'id' => (int) $db->lastInsertId(), 'creado_en' => date('Y-m-d H:i:s')]);
        exit;
    }

    if ($action === 'buscar') {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            echo json_encode(['ok' => true, 'usuarios' => []]);
            exit;
        }
        $b = "%{$q}%";
        $stmt = $db->prepare("SELECT id,nombre,apellido,foto,tipo FROM usuarios WHERE activo=1 AND id!=? AND (nombre LIKE ? OR apellido LIKE ? OR correo LIKE ?) ORDER BY nombre ASC LIMIT 20");
        $stmt->execute([$yo, $b, $b, $b]);
        echo json_encode(['ok' => true, 'usuarios' => $stmt->fetchAll()]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Acción desconocida.']);
    exit;
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: inicio_sesion.php');
    exit;
}
$db = getDB();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id=? AND activo=1");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
if (!$usuario) {
    session_destroy();
    header('Location: inicio_sesion.php');
    exit;
}

$inicial = strtoupper(mb_substr($usuario['nombre'], 0, 1));
$tipo = $usuario['tipo'] ?? 'candidato';
$nrStmt = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario=? AND leido=0");
$nrStmt->execute([$usuario['id']]);
$totalNoLeidos = (int) $nrStmt->fetchColumn();
$conUsuario = (int) ($_GET['con'] ?? 0);
?><!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes — QuibdóConecta</title>
    <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,400&display=swap"
        rel="stylesheet">
    <style>
        
        :root {
            
            --g900: #052e16;
            --g700: #15803d;
            --g500: #22c55e;
            --g300: #86efac;
            --g100: #dcfce7;
            --g50: #f0fdf4;

            --n950: #0c0d0e;
            --n800: #1e2124;
            --n600: #434750;
            --n400: #878c99;
            --n200: #d4d7de;
            --n100: #eceef2;
            --n50: #f6f7f9;
            --white: #ffffff;

            --amber: #f59e0b;
            --amber-light: #fef3c7;

            --blue: #3b82f6;
            --blue-light: #eff6ff;

            --danger: #ef4444;

            --surface-app: var(--n50);
            --surface-panel: var(--white);
            --surface-sidebar: var(--white);
            --surface-input: var(--n50);

            --border-subtle: #eceef2;
            --border-default: #d4d7de;

            --shadow-xs: 0 1px 2px rgba(0, 0, 0, .05);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, .06);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, .09);
            --shadow-lg: 0 24px 64px rgba(0, 0, 0, .13);

            --r-sm: 8px;
            --r-md: 14px;
            --r-lg: 20px;
            --r-xl: 28px;
            --r-full: 999px;

            --font-body: 'Plus Jakarta Sans', system-ui, sans-serif;
            --font-display: 'Fraunces', Georgia, serif;
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            height: 100%;
        }

        body {
            font-family: var(--font-body);
            background: var(--surface-app);
            color: var(--n800);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }

        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--n200);
            border-radius: var(--r-full);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--n400);
        }

        .sidebar {
            width: 232px;
            flex-shrink: 0;
            background: var(--surface-sidebar);
            border-right: 1px solid var(--border-subtle);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
        }

        .sb-brand {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 22px 18px 18px;
            border-bottom: 1px solid var(--border-subtle);
        }

        .sb-brand img {
            width: 30px;
        }

        .sb-brand-name {
            font-family: var(--font-display);
            font-size: 16px;
            font-weight: 600;
            color: var(--n950);
            letter-spacing: -.2px;
        }

        .sb-brand-name em {
            color: var(--g700);
            font-style: normal;
        }

        .sb-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-subtle);
        }

        .sb-ava {
            width: 34px;
            height: 34px;
            border-radius: var(--r-full);
            background: linear-gradient(135deg, var(--g700), var(--g500));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px var(--g100);
        }

        .sb-user-name {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--n800);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sb-user-role {
            font-size: 10.5px;
            color: var(--n400);
            margin-top: 1px;
        }

.logo-navbar{
height:30px;
width:auto;
object-fit:contain;
margin-left: 20px;
}
        
        .sb-nav {
            padding: 10px 8px;
            flex: 1;
        }

        .sb-nav-label {
            font-size: 9px;
            font-weight: 700;
            color: var(--n400);
            text-transform: uppercase;
            letter-spacing: 1.6px;
            padding: 12px 10px 4px;
        }

        .sb-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8.5px 10px;
            border-radius: var(--r-sm);
            color: var(--n600);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background .15s, color .15s;
            margin-bottom: 1px;
            position: relative;
        }

        .sb-link:hover {
            background: var(--n50);
            color: var(--n950);
        }

        .sb-link.active {
            background: var(--g50);
            color: var(--g700);
            font-weight: 700;
        }

        .sb-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 20%;
            bottom: 20%;
            width: 3px;
            background: var(--g500);
            border-radius: 0 var(--r-full) var(--r-full) 0;
        }

        .sb-icon {
            font-size: 15px;
            width: 18px;
            text-align: center;
        }

        .sb-badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: 9.5px;
            font-weight: 800;
            padding: 1.5px 6px;
            border-radius: var(--r-full);
            min-width: 16px;
            text-align: center;
        }

        .sb-bottom {
            padding: 10px 8px;
            border-top: 1px solid var(--border-subtle);
        }

        .sb-logout {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8.5px 10px;
            border-radius: var(--r-sm);
            color: var(--danger);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: background .15s;
            opacity: .75;
        }

        .sb-logout:hover {
            background: #fef2f2;
            opacity: 1;
        }

        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            min-width: 0;
        }

        .topbar {
            background: var(--white);
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            border-bottom: 1px solid var(--border-subtle);
            flex-shrink: 0;
        }

        .topbar-title {
            font-family: var(--font-display);
            font-size: 18px;
            font-weight: 600;
            color: var(--n950);
            letter-spacing: -.3px;
        }

        .topbar-sub {
            font-size: 11px;
            color: var(--n400);
            margin-top: 1px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tb-btn {
            width: 32px;
            height: 32px;
            border-radius: var(--r-full);
            background: var(--n50);
            border: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
            color: var(--n600);
        }

        .tb-btn:hover {
            background: var(--n100);
        }

        .tb-ava {
            width: 32px;
            height: 32px;
            border-radius: var(--r-full);
            background: linear-gradient(135deg, var(--g700), var(--g500));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 0 0 2px var(--g100);
        }

        .chat-wrap {
            flex: 1;
            display: flex;
            overflow: hidden;
            min-height: 0;
        }

        .conv-panel {
            width: 300px;
            flex-shrink: 0;
            background: var(--white);
            border-right: 1px solid var(--border-subtle);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .conv-head {
            padding: 16px 16px 12px;
            border-bottom: 1px solid var(--border-subtle);
        }

        .conv-head-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .conv-head-top h3 {
            font-size: 14px;
            font-weight: 800;
            color: var(--n950);
            letter-spacing: -.2px;
        }

        .btn-new {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            background: var(--g700);
            color: #fff;
            border: none;
            border-radius: var(--r-full);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-body);
            transition: background .15s, transform .15s, box-shadow .15s;
            box-shadow: 0 2px 8px rgba(21, 128, 61, .3);
        }

        .btn-new:hover {
            background: var(--g900);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(21, 128, 61, .4);
        }

        .conv-search {
            width: 100%;
            padding: 9px 13px 9px 34px;
            border: 1.5px solid var(--border-subtle);
            border-radius: var(--r-full);
            font-size: 12.5px;
            font-family: var(--font-body);
            background: var(--surface-input);
            color: var(--n800);
            outline: none;
            transition: border .15s, box-shadow .15s;
            position: relative;
        }

        .conv-search:focus {
            border-color: var(--g500);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
            background: var(--white);
        }

        .conv-search-wrap {
            position: relative;
        }

        .conv-search-wrap::before {
            content: '🔍';
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            pointer-events: none;
            opacity: .5;
        }

        .conv-search::placeholder {
            color: var(--n400);
        }

        .conv-list {
            flex: 1;
            overflow-y: auto;
        }

        .conv-empty {
            padding: 48px 20px;
            text-align: center;
            color: var(--n400);
            font-size: 13px;
            line-height: 1.7;
        }

        .conv-empty-icon {
            font-size: 38px;
            display: block;
            margin-bottom: 10px;
            opacity: .4;
        }

        .conv-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 12px 16px;
            cursor: pointer;
            transition: background .13s;
            border-bottom: 1px solid var(--border-subtle);
            position: relative;
        }

        .conv-item:hover {
            background: var(--n50);
        }

        .conv-item.active {
            background: var(--g50);
        }

        .conv-item.active::after {
            content: '';
            position: absolute;
            right: 0;
            top: 20%;
            bottom: 20%;
            width: 3px;
            background: var(--g500);
            border-radius: var(--r-full) 0 0 var(--r-full);
        }

        .cav {
            width: 42px;
            height: 42px;
            border-radius: var(--r-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            position: relative;
        }

        .cav-candidato {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }

        .cav-empresa {
            background: linear-gradient(135deg, #0891b2, #06b6d4);
        }

        .cav-artista {
            background: linear-gradient(135deg, #d97706, #f59e0b);
        }

        .cav-online::after {
            content: '';
            position: absolute;
            bottom: 1px;
            right: 1px;
            width: 9px;
            height: 9px;
            background: var(--g500);
            border-radius: var(--r-full);
            border: 2px solid var(--white);
        }

        .conv-body {
            flex: 1;
            min-width: 0;
        }

        .conv-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--n950);
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
        }

        .conv-tag {
            font-size: 9px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: var(--r-full);
            background: var(--g100);
            color: var(--g700);
            flex-shrink: 0;
        }

        .conv-preview {
            font-size: 11.5px;
            color: var(--n400);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-preview.unread {
            color: var(--n800);
            font-weight: 600;
        }

        .conv-preview.yo::before {
            content: 'Tú: ';
            font-weight: 600;
            color: var(--n400);
        }

        .conv-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
            flex-shrink: 0;
        }

        .conv-time {
            font-size: 10px;
            color: var(--n400);
        }

        .conv-unread {
            background: var(--g700);
            color: #fff;
            font-size: 9.5px;
            font-weight: 800;
            min-width: 16px;
            height: 16px;
            padding: 0 5px;
            border-radius: var(--r-full);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .msg-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--surface-app);
            min-width: 0;
            
            background-image: radial-gradient(circle, var(--n200) 1px, transparent 1px);
            background-size: 24px 24px;
            background-color: var(--n50);
        }

        .msg-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            background: var(--white);
        }

        .msg-empty-icon {
            width: 72px;
            height: 72px;
            background: var(--g50);
            border-radius: var(--r-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            box-shadow: 0 0 0 8px var(--g100);
        }

        .msg-empty h3 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            color: var(--n800);
        }

        .msg-empty p {
            font-size: 13px;
            color: var(--n400);
            max-width: 240px;
            text-align: center;
            line-height: 1.6;
        }

        .msg-header {
            background: var(--white);
            border-bottom: 1px solid var(--border-subtle);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            box-shadow: var(--shadow-xs);
        }

        .msg-header .mh-back {
            display: none;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: var(--n600);
            padding: 6px;
            border-radius: var(--r-sm);
            transition: background .14s;
        }

        .msg-header .mh-back:hover {
            background: var(--n50);
        }

        .mh-av {
            width: 38px;
            height: 38px;
            border-radius: var(--r-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
        }

        .mh-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--n950);
        }

        .mh-role {
            font-size: 11px;
            color: var(--n400);
            margin-top: 1px;
        }

        .msg-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .date-chip {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 0 4px;
        }

        .date-chip span {
            font-size: 10.5px;
            font-weight: 600;
            color: var(--n400);
            background: var(--white);
            border: 1px solid var(--border-subtle);
            padding: 3px 12px;
            border-radius: var(--r-full);
            box-shadow: var(--shadow-xs);
        }

        @keyframes bubbleIn {
            from {
                opacity: 0;
                transform: scale(.94) translateY(6px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .bubble {
            max-width: 62%;
            padding: 10px 15px 8px;
            border-radius: 18px;
            font-size: 13.5px;
            line-height: 1.55;
            word-wrap: break-word;
            animation: bubbleIn .18s cubic-bezier(.34, 1.56, .64, 1) both;
            position: relative;
        }

        .bubble.sent {
            align-self: flex-end;
            background: linear-gradient(135deg, var(--g700) 0%, var(--g500) 100%);
            color: #fff;
            border-bottom-right-radius: 4px;
            box-shadow: 0 3px 12px rgba(21, 128, 61, .22);
        }

        .bubble.recv {
            align-self: flex-start;
            background: var(--white);
            color: var(--n800);
            border-bottom-left-radius: 4px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-subtle);
        }

        .bubble-time {
            display: block;
            font-size: 9.5px;
            margin-top: 4px;
        }

        .bubble.sent .bubble-time {
            color: rgba(255, 255, 255, .55);
            text-align: right;
        }

        .bubble.recv .bubble-time {
            color: var(--n400);
        }

        .bubble.sent+.bubble.sent {
            border-top-right-radius: 6px;
        }

        .bubble.recv+.bubble.recv {
            border-top-left-radius: 6px;
        }

        .msg-input-bar {
            background: var(--white);
            border-top: 1px solid var(--border-subtle);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .msg-input {
            flex: 1;
            padding: 11px 18px;
            border: 1.5px solid var(--border-subtle);
            border-radius: var(--r-full);
            font-size: 13.5px;
            font-family: var(--font-body);
            background: var(--surface-input);
            color: var(--n800);
            outline: none;
            transition: border .15s, box-shadow .15s, background .15s;
        }

        .msg-input::placeholder {
            color: var(--n400);
        }

        .msg-input:focus {
            border-color: var(--g500);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
            background: var(--white);
        }

        .send-btn {
            width: 42px;
            height: 42px;
            border-radius: var(--r-full);
            background: linear-gradient(135deg, var(--g700), var(--g500));
            border: none;
            color: #fff;
            font-size: 17px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .18s, box-shadow .18s, opacity .18s;
            box-shadow: 0 4px 14px rgba(21, 128, 61, .3);
            flex-shrink: 0;
        }

        .send-btn:hover {
            transform: scale(1.07);
            box-shadow: 0 6px 18px rgba(21, 128, 61, .42);
        }

        .send-btn:active {
            transform: scale(.95);
        }

        .send-btn:disabled {
            opacity: .35;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(12, 13, 14, .35);
            backdrop-filter: blur(5px);
            z-index: 500;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .overlay.open {
            display: flex;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(.94) translateY(12px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal {
            background: var(--white);
            border-radius: var(--r-xl);
            max-width: 420px;
            width: 100%;
            padding: 28px;
            box-shadow: var(--shadow-lg);
            animation: modalIn .24s cubic-bezier(.34, 1.2, .64, 1) both;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            border: 1px solid var(--border-subtle);
        }

        .modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 26px;
            height: 26px;
            background: var(--n50);
            border: 1px solid var(--border-subtle);
            border-radius: var(--r-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            cursor: pointer;
            color: var(--n600);
            transition: background .14s;
        }

        .modal-close:hover {
            background: var(--n100);
        }

        .modal h3 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 600;
            color: var(--n950);
            margin-bottom: 4px;
        }

        .modal-sub {
            font-size: 12.5px;
            color: var(--n400);
            margin-bottom: 16px;
        }

        .modal-input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border-subtle);
            border-radius: var(--r-md);
            font-size: 13px;
            font-family: var(--font-body);
            background: var(--surface-input);
            color: var(--n800);
            outline: none;
            margin-bottom: 10px;
            transition: border .15s, box-shadow .15s;
        }

        .modal-input::placeholder {
            color: var(--n400);
        }

        .modal-input:focus {
            border-color: var(--g500);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
            background: var(--white);
        }

        .user-results {
            max-height: 260px;
            overflow-y: auto;
        }

        .user-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 10px;
            border-radius: var(--r-md);
            cursor: pointer;
            transition: background .13s;
        }

        .user-row:hover {
            background: var(--g50);
        }

        .user-row-av {
            width: 36px;
            height: 36px;
            border-radius: var(--r-full);
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }

        .user-row-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--n950);
        }

        .user-row-type {
            font-size: 11px;
            color: var(--n400);
        }

        .modal-hint {
            padding: 24px 16px;
            text-align: center;
            color: var(--n400);
            font-size: 12.5px;
        }

        @media (max-width:960px) {
            .sidebar {
                width: 60px;
            }

            .sb-brand-name,
            .sb-user-name,
            .sb-user-role,
            .sb-link span:not(.sb-icon),
            .sb-nav-label,
            .sb-logout span {
                display: none;
            }

            .sb-brand {
                justify-content: center;
                padding: 16px 0;
            }

            .sb-user {
                justify-content: center;
                padding: 12px 0;
            }

            .sb-link {
                justify-content: center;
                padding: 12px;
            }

            .sb-bottom {
                padding: 8px 0;
            }

            .sb-logout {
                justify-content: center;
                padding: 12px;
            }

            .conv-panel {
                width: 260px;
            }
        }

        @media (max-width:768px) {
            .conv-panel {
                width: 100%;
            }

            .msg-panel {
                display: none;
            }

            .msg-panel.mobile-open {
                display: flex;
                position: fixed;
                inset: 0;
                z-index: 200;
            }

            .conv-panel.mobile-hide {
                display: none;
            }

            .msg-header .mh-back {
                display: flex;
            }

            .topbar {
                padding: 0 16px;
            }
        }

        @media (max-width:600px) {
            .sidebar {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 54px;
                flex-direction: row;
                border-right: none;
                border-top: 1px solid var(--border-subtle);
                border-radius: 16px 16px 0 0;
                top: auto;
                min-height: unset;
                box-shadow: 0 -4px 16px rgba(0, 0, 0, .06);
                overflow: hidden;
            }

            .sb-brand,
            .sb-user,
            .sb-bottom {
                display: none;
            }

            .sb-nav {
                display: flex;
                flex-direction: row;
                justify-content: space-around;
                align-items: center;
                padding: 0;
                width: 100%;
            }

            .sb-link {
                flex-direction: column;
                font-size: 8.5px;
                padding: 6px 8px;
                gap: 2px;
                border-radius: var(--r-sm);
            }

            .sb-link span:not(.sb-icon) {
                display: block;
                font-size: 8.5px;
            }

            .main {
                padding-bottom: 54px;
            }

            .topbar {
                padding: 0 12px;
                height: 52px;
            }

            .msg-body {
                padding: 14px 14px;
            }

            .bubble {
                max-width: 80%;
            }
        }
    </style>
</head>

<body>

    <!-- ═══ SIDEBAR ═══ -->
    <aside class="sidebar">

    <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="Imagenes/quibdo_desco_new.png" alt="Quibdó Conecta" class="logo-navbar">
        </div>
        <div class="sb-user">
            <div class="sb-ava"><?= $inicial ?></div>
            <div>
                <div class="sb-user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                <div class="sb-user-role"><?= $tipo === 'empresa' ? '🏢 Empresa' : '👤 Candidato' ?></div>
            </div>
        </div>
        <nav class="sb-nav">
            <div class="sb-nav-label">Principal</div>
            <a href="dashboard.php" class="sb-link"><span class="sb-icon">🏠</span><span>Panel</span></a>
            <a href="Empleo.html" class="sb-link"><span class="sb-icon">💼</span><span>Empleos</span></a>
            <a href="talentos.html" class="sb-link"><span class="sb-icon">🎭</span><span>Talentos</span></a>
            <a href="Empresas.html" class="sb-link"><span class="sb-icon">🏢</span><span>Empresas</span></a>
            <a href="chat.php" class="sb-link active">
                <span class="sb-icon">💬</span><span>Mensajes</span>
                <?php if ($totalNoLeidos > 0): ?><span class="sb-badge"><?= $totalNoLeidos ?></span><?php endif; ?>
            </a>
            <a href="verificar_cuenta.php" class="sb-link"><span class="sb-icon">✅</span><span>Verificar</span></a>
            <div class="sb-nav-label">Soporte</div>
            <a href="Ayuda.html" class="sb-link"><span class="sb-icon">❓</span><span>Ayuda</span></a>
        </nav>
        <div class="sb-bottom">
            <a href="Php/logout.php" class="sb-logout"><span class="sb-icon">🚪</span><span>Salir</span></a>
        </div>
    </aside>

    <!-- ═══ MAIN ═══ -->
    <div class="main">

        <!-- topbar -->
        <header class="topbar">
            <div>
                <div class="topbar-title">Mensajes</div>
                <div class="topbar-sub">Conecta con empresas, candidatos y artistas</div>
            </div>
            <div class="topbar-right">
                <a href="dashboard.php" class="tb-btn" title="Mi panel">🏠</a>
                <a href="index.html" class="tb-btn" title="Inicio">🌿</a>
                <div class="tb-ava"><?= $inicial ?></div>
            </div>
        </header>

        <!-- chat wrap -->
        <div class="chat-wrap">

            <!-- panel izquierdo -->
            <div class="conv-panel" id="convPanel">
                <div class="conv-head">
                    <div class="conv-head-top">
                        <h3>Conversaciones</h3>
                        <button class="btn-new" onclick="abrirNuevoChat()">
                            <span>✏️</span> Nuevo
                        </button>
                    </div>
                    <div class="conv-search-wrap">
                        <input type="text" class="conv-search" id="searchConv" placeholder="Buscar conversación...">
                    </div>
                </div>
                <div class="conv-list" id="convList">
                    <div class="conv-empty">
                        <span class="conv-empty-icon">💬</span>
                        Aún no tienes mensajes.<br>¡Envía el primero!
                    </div>
                </div>
            </div>

            <!-- panel derecho -->
            <div class="msg-panel" id="msgPanel">

                <!-- estado vacío -->
                <div class="msg-empty" id="msgEmpty">
                    <div class="msg-empty-icon">💬</div>
                    <h3>Tus mensajes</h3>
                    <p>Selecciona una conversación o inicia una nueva para comenzar a chatear</p>
                </div>

                <!-- chat activo -->
                <div id="msgActive" style="display:none; flex-direction:column; height:100%;">
                    <div class="msg-header">
                        <button class="mh-back" onclick="volverALista()">←</button>
                        <div class="mh-av" id="mhAv">?</div>
                        <div>
                            <div class="mh-name" id="mhName">Cargando…</div>
                            <div class="mh-role" id="mhRole">…</div>
                        </div>
                    </div>

                    <div class="msg-body" id="msgBody"></div>

                    <div class="msg-input-bar">
                        <input type="text" class="msg-input" id="msgInput" placeholder="Escribe un mensaje…"
                            maxlength="2000" autocomplete="off">
                        <button class="send-btn" id="sendBtn" onclick="enviar()">➤</button>
                    </div>
                </div>

            </div><!-- /msg-panel -->
        </div><!-- /chat-wrap -->
    </div><!-- /main -->

    <!-- ═══ MODAL NUEVO CHAT ═══ -->
    <div class="overlay" id="overlayNuevo">
        <div class="modal">
            <button class="modal-close" onclick="cerrarNuevoChat()">✕</button>
            <h3>Nuevo mensaje</h3>
            <p class="modal-sub">Busca a alguien para comenzar una conversación</p>
            <input type="text" class="modal-input" id="modalSearch" placeholder="Nombre o correo…"
                oninput="buscarUsuarios()">
            <div class="user-results" id="userResults">
                <div class="modal-hint">Escribe para buscar usuarios</div>
            </div>
        </div>
    </div>

    <script>
        const YO = <?= (int) $usuario['id'] ?>;
        let chatActivo = null;
        let polling = null;
        let conversaciones = [];
        const API = q => 'chat.php?' + q;

        document.addEventListener('DOMContentLoaded', () => {
            cargarConvs();
            <?php if ($conUsuario > 0): ?>
                setTimeout(() => abrirChat(<?= $conUsuario ?>), 400);
            <?php endif; ?>
            document.getElementById('msgInput').addEventListener('keydown', e => {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviar(); }
            });
            document.getElementById('searchConv').addEventListener('input', renderConvs);
            document.getElementById('overlayNuevo').addEventListener('click', e => {
                if (e.target === e.currentTarget) cerrarNuevoChat();
            });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarNuevoChat(); });
        });

        async function cargarConvs() {
            try {
                const r = await fetch(API('action=conversaciones'));
                if (r.status === 401) { location.href = 'inicio_sesion.php'; return; }
                const d = await r.json();
                if (!d.ok) return;
                conversaciones = d.conversaciones;
                renderConvs();
            } catch (e) { console.error(e); }
        }

        function renderConvs() {
            const box = document.getElementById('convList');
            const q = document.getElementById('searchConv').value.toLowerCase();
            const lista = conversaciones.filter(c =>
                (c.nombre + ' ' + (c.apellido || '')).toLowerCase().includes(q)
            );

            if (!lista.length) {
                box.innerHTML = `<div class="conv-empty"><span class="conv-empty-icon">🔍</span>Sin resultados</div>`;
                return;
            }

            box.innerHTML = lista.map(c => {
                const nombre = c.nombre + ' ' + (c.apellido || '');
                const ini = c.nombre.charAt(0).toUpperCase();
                const tipo = c.tipo || 'candidato';
                const unread = parseInt(c.no_leidos) > 0;
                const preview = c.ultimo_mensaje
                    ? (c.ultimo_mensaje.length > 42 ? c.ultimo_mensaje.slice(0, 42) + '…' : c.ultimo_mensaje)
                    : 'Sin mensajes';
                const tipoLabel = tipo === 'empresa' ? 'Empresa' : tipo === 'artista' ? 'Artista' : '';
                return `
    <div class="conv-item${chatActivo === parseInt(c.id) ? ' active' : ''}" onclick="abrirChat(${c.id})">
      <div class="cav cav-${tipo}">${ini}</div>
      <div class="conv-body">
        <div class="conv-name">
          ${esc(nombre)}
          ${tipoLabel ? `<span class="conv-tag">${tipoLabel}</span>` : ''}
        </div>
        <div class="conv-preview${unread ? ' unread' : ''}${c.yo_envie_ultimo ? ' yo' : ''}">
          ${esc(preview)}
        </div>
      </div>
      <div class="conv-meta">
        <span class="conv-time">${fmtFecha(c.ultima_fecha)}</span>
        ${unread ? `<span class="conv-unread">${c.no_leidos}</span>` : ''}
      </div>
    </div>`;
            }).join('');
        }

        async function abrirChat(uid) {
            chatActivo = uid;
            renderConvs();
            document.getElementById('msgEmpty').style.display = 'none';
            document.getElementById('msgActive').style.display = 'flex';
            document.getElementById('mhName').textContent = 'Cargando…';
            document.getElementById('mhRole').textContent = '…';
            document.getElementById('msgBody').innerHTML = '';
            if (window.innerWidth <= 768) {
                document.getElementById('convPanel').classList.add('mobile-hide');
                document.getElementById('msgPanel').classList.add('mobile-open');
            }
            if (polling) { clearInterval(polling); polling = null; }
            await cargarMensajes(uid);
            polling = setInterval(() => cargarMensajes(uid, true), 5000);
            document.getElementById('msgInput').focus();
        }

        async function cargarMensajes(uid, silent = false) {
            try {
                const r = await fetch(API(`action=mensajes&con=${uid}`));
                if (r.status === 401) { location.href = 'inicio_sesion.php'; return; }
                const d = await r.json();
                if (!d.ok) { if (!silent) toast('No se pudieron cargar los mensajes.'); return; }
                if (d.usuario) {
                    document.getElementById('mhName').textContent =
                        d.usuario.nombre + ' ' + (d.usuario.apellido || '');
                    document.getElementById('mhRole').textContent =
                        d.usuario.tipo === 'empresa' ? '🏢 Empresa'
                            : d.usuario.tipo === 'artista' ? '🎭 Artista' : '👤 Candidato';
                    const av = document.getElementById('mhAv');
                    av.textContent = d.usuario.nombre.charAt(0).toUpperCase();
                    av.className = 'mh-av cav-' + (d.usuario.tipo || 'candidato');
                }
                renderMensajes(d.mensajes);
                if (!silent) cargarConvs();
            } catch (e) { if (!silent) toast('Error de conexión.'); }
        }

        function renderMensajes(msgs) {
            const box = document.getElementById('msgBody');
            let html = '', lastDate = '';
            msgs.forEach(m => {
                const d = m.creado_en.split(' ')[0];
                if (d !== lastDate) {
                    lastDate = d;
                    html += `<div class="date-chip"><span>${fmtFechaLarga(d)}</span></div>`;
                }
                const tipo = parseInt(m.de_usuario) === YO ? 'sent' : 'recv';
                const hora = m.creado_en.split(' ')[1].slice(0, 5);
                html += `<div class="bubble ${tipo}">${esc(m.mensaje)}<span class="bubble-time">${hora}</span></div>`;
            });
            const atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 120;
            box.innerHTML = html;
            if (atBottom || !msgs.length) box.scrollTop = box.scrollHeight;
        }

        async function enviar() {
            const inp = document.getElementById('msgInput');
            const btn = document.getElementById('sendBtn');
            const msg = inp.value.trim();
            if (!msg || !chatActivo) return;
            btn.disabled = true;
            inp.value = '';
            try {
                const fd = new FormData();
                fd.append('para_usuario', chatActivo);
                fd.append('mensaje', msg);
                const r = await fetch(API('action=enviar'), { method: 'POST', body: fd });
                if (r.status === 401) { location.href = 'inicio_sesion.php'; return; }
                const j = await r.json();
                if (j.ok) {
                    const box = document.getElementById('msgBody');
                    const hora = new Date().toTimeString().slice(0, 5);
                    box.innerHTML += `<div class="bubble sent">${esc(msg)}<span class="bubble-time">${hora}</span></div>`;
                    box.scrollTop = box.scrollHeight;
                    cargarConvs();
                } else { toast(j.msg || 'Error al enviar'); inp.value = msg; }
            } catch (e) { toast('Error de conexión'); inp.value = msg; }
            btn.disabled = false;
            inp.focus();
        }

        function abrirNuevoChat() { document.getElementById('overlayNuevo').classList.add('open'); document.getElementById('modalSearch').focus(); }
        function cerrarNuevoChat() { document.getElementById('overlayNuevo').classList.remove('open'); }

        let searchT = null;
        async function buscarUsuarios() {
            const q = document.getElementById('modalSearch').value.trim();
            const box = document.getElementById('userResults');
            if (q.length < 2) { box.innerHTML = '<div class="modal-hint">Escribe al menos 2 caracteres</div>'; return; }
            clearTimeout(searchT);
            searchT = setTimeout(async () => {
                try {
                    const r = await fetch(API(`action=buscar&q=${encodeURIComponent(q)}`));
                    const d = await r.json();
                    if (!d.ok || !d.usuarios.length) { box.innerHTML = '<div class="modal-hint">Sin resultados</div>'; return; }
                    box.innerHTML = d.usuarios.map(u => `
        <div class="user-row" onclick="seleccionar(${u.id})">
          <div class="user-row-av cav-${u.tipo || 'candidato'}">${u.nombre.charAt(0).toUpperCase()}</div>
          <div>
            <div class="user-row-name">${esc(u.nombre + ' ' + (u.apellido || ''))}</div>
            <div class="user-row-type">${u.tipo === 'empresa' ? '🏢 Empresa' : u.tipo === 'artista' ? '🎭 Artista' : '👤 Candidato'}</div>
          </div>
        </div>`).join('');
                } catch (e) { }
            }, 280);
        }

        function seleccionar(id) { cerrarNuevoChat(); abrirChat(id); }

        function volverALista() {
            document.getElementById('convPanel').classList.remove('mobile-hide');
            document.getElementById('msgPanel').classList.remove('mobile-open');
            if (polling) { clearInterval(polling); polling = null; }
            chatActivo = null;
            cargarConvs();
        }

        function toast(msg) {
            let t = document.getElementById('_toast');
            if (!t) {
                t = document.createElement('div'); t.id = '_toast';
                t.style.cssText = 'position:fixed;bottom:22px;left:50%;transform:translateX(-50%);background:#ef4444;color:#fff;padding:10px 22px;border-radius:999px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.14);transition:opacity .3s;font-family:var(--font-body, sans-serif);white-space:nowrap;';
                document.body.appendChild(t);
            }
            t.textContent = msg; t.style.opacity = '1';
            clearTimeout(t._t);
            t._t = setTimeout(() => { t.style.opacity = '0'; }, 3500);
        }

        function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

        function fmtFecha(s) {
            if (!s) return '';
            const d = new Date(s.replace(' ', 'T')), h = new Date();
            const df = Math.floor((h - d) / 86400000);
            if (df === 0) return d.toTimeString().slice(0, 5);
            if (df === 1) return 'Ayer';
            if (df < 7) return ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][d.getDay()];
            return `${d.getDate()}/${d.getMonth() + 1}`;
        }

        function fmtFechaLarga(s) {
            const d = new Date(s + 'T00:00:00'), h = new Date();
            const df = Math.floor((h - d) / 86400000);
            if (df === 0) return 'Hoy';
            if (df === 1) return 'Ayer';
            const M = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            return `${d.getDate()} de ${M[d.getMonth()]}`;
        }
    </script>

    <!-- Widget de sesión activa — QuibdóConecta -->
    <script src="js/sesion_widget.js"></script>
</body>

</html>