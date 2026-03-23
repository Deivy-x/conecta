<?php
// ============================================================
// chat.php — Chat interno QuibdóConecta (todo en uno)
// ============================================================
ini_set('session.gc_maxlifetime', 604800);  // 1 semana
ini_set('session.cookie_lifetime', 604800); // 1 semana
session_set_cookie_params([
    'lifetime' => 604800,
    'path'     => '/',
    'samesite' => 'Lax',
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
]);
session_start();
require_once __DIR__ . '/Php/db.php';

// ─── MANEJO DE ACCIONES AJAX ────────────────────────────────
// InfinityFree bloquea fetch() a archivos separados,
// así que todo va por ?action= dentro del mismo chat.php
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

    // ── CONVERSACIONES ──────────────────────────────────────
    if ($action === 'conversaciones') {
        $stmt = $db->prepare("
            SELECT u.id, u.nombre, u.apellido, u.foto, u.tipo,
                   sub.ultima_fecha,
                   COALESCE(nr.no_leidos, 0) AS no_leidos
            FROM (
                SELECT CASE WHEN de_usuario = ? THEN para_usuario ELSE de_usuario END AS otro_id,
                       MAX(creado_en) AS ultima_fecha
                FROM mensajes
                WHERE de_usuario = ? OR para_usuario = ?
                GROUP BY otro_id
            ) AS sub
            INNER JOIN usuarios u ON u.id = sub.otro_id
            LEFT JOIN (
                SELECT de_usuario, COUNT(*) AS no_leidos
                FROM mensajes WHERE para_usuario = ? AND leido = 0
                GROUP BY de_usuario
            ) AS nr ON nr.de_usuario = u.id
            ORDER BY sub.ultima_fecha DESC
        ");
        $stmt->execute([$yo, $yo, $yo, $yo]);
        $conversaciones = $stmt->fetchAll();

        foreach ($conversaciones as &$conv) {
            $msg = $db->prepare("
                SELECT mensaje, de_usuario, creado_en FROM mensajes
                WHERE (de_usuario = ? AND para_usuario = ?) OR (de_usuario = ? AND para_usuario = ?)
                ORDER BY creado_en DESC LIMIT 1
            ");
            $msg->execute([$yo, $conv['id'], $conv['id'], $yo]);
            $ultimo = $msg->fetch();
            $conv['ultimo_mensaje'] = $ultimo ? $ultimo['mensaje'] : '';
            $conv['ultima_fecha'] = $ultimo ? $ultimo['creado_en'] : $conv['ultima_fecha'];
            $conv['yo_envie_ultimo'] = $ultimo && (int) $ultimo['de_usuario'] === $yo;
        }

        $total = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario = ? AND leido = 0");
        $total->execute([$yo]);
        echo json_encode(['ok' => true, 'conversaciones' => $conversaciones, 'total_no_leidos' => (int) $total->fetchColumn()]);
        exit;
    }

    // ── MENSAJES ────────────────────────────────────────────
    if ($action === 'mensajes') {
        $con = (int) ($_GET['con'] ?? 0);
        if (!$con) {
            echo json_encode(['ok' => false, 'msg' => 'Falta ID.']);
            exit;
        }

        $db->prepare("UPDATE mensajes SET leido = 1 WHERE de_usuario = ? AND para_usuario = ? AND leido = 0")
            ->execute([$con, $yo]);

        $stmt = $db->prepare("
            SELECT m.id, m.de_usuario, m.para_usuario, m.mensaje, m.leido, m.creado_en
            FROM mensajes m
            WHERE (m.de_usuario = ? AND m.para_usuario = ?) OR (m.de_usuario = ? AND m.para_usuario = ?)
            ORDER BY m.creado_en ASC LIMIT 100
        ");
        $stmt->execute([$yo, $con, $con, $yo]);

        $info = $db->prepare("SELECT id, nombre, apellido, foto, tipo FROM usuarios WHERE id = ? AND activo = 1");
        $info->execute([$con]);

        echo json_encode(['ok' => true, 'usuario' => $info->fetch() ?: null, 'mensajes' => $stmt->fetchAll()]);
        exit;
    }

    // ── ENVIAR ──────────────────────────────────────────────
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

        $check = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND activo = 1");
        $check->execute([$para]);
        if (!$check->fetch()) {
            echo json_encode(['ok' => false, 'msg' => 'Usuario no existe.']);
            exit;
        }

        $db->prepare("INSERT INTO mensajes (de_usuario, para_usuario, mensaje) VALUES (?, ?, ?)")
            ->execute([$yo, $para, $mensaje]);

        echo json_encode(['ok' => true, 'id' => (int) $db->lastInsertId(), 'creado_en' => date('Y-m-d H:i:s')]);
        exit;
    }

    // ── BUSCAR USUARIOS ─────────────────────────────────────
    if ($action === 'buscar') {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            echo json_encode(['ok' => true, 'usuarios' => []]);
            exit;
        }

        $b = "%{$q}%";
        $stmt = $db->prepare("
            SELECT id, nombre, apellido, foto, tipo FROM usuarios
            WHERE activo = 1 AND id != ? AND (nombre LIKE ? OR apellido LIKE ? OR correo LIKE ?)
            ORDER BY nombre ASC LIMIT 20
        ");
        $stmt->execute([$yo, $b, $b, $b]);
        echo json_encode(['ok' => true, 'usuarios' => $stmt->fetchAll()]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Acción desconocida.']);
    exit;
}

// ─── CARGA NORMAL DE LA PÁGINA ──────────────────────────────
if (!isset($_SESSION['usuario_id'])) {
    header('Location: inicio_sesion.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

if (!$usuario) {
    session_destroy();
    header('Location: inicio_sesion.php');
    exit;
}

$inicial = strtoupper(mb_substr($usuario['nombre'], 0, 1));
$tipo = $usuario['tipo'] ?? 'candidato';

$nrStmt = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario = ? AND leido = 0");
$nrStmt->execute([$usuario['id']]);
$totalNoLeidos = (int) $nrStmt->fetchColumn();

$conUsuario = (int) ($_GET['con'] ?? 0);
?><!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes – QuibdóConecta</title>
    <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════
           VARIABLES — mismo sistema que dashboard.php
        ══════════════════════════════════════════ */
        :root {
            --v2: #1a7a3c;
            --v3: #27a855;
            --v4: #5dd882;
            --vlima: #a3f0b5;
            --a2: #d4a017;
            --a3: #f5c800;
            --r3: #1a56db;
            --r4: #5b8eff;
            --bg: #060e07;
            --bg2: #0c1a0e;
            --bg3: #111f13;
            --panel: rgba(12, 26, 14, .97);
            --card: rgba(15, 28, 17, .92);
            --borde: rgba(255, 255, 255, .08);
            --borde2: rgba(255, 255, 255, .05);
            --ink: rgba(255, 255, 255, .90);
            --ink2: rgba(255, 255, 255, .58);
            --ink3: rgba(255, 255, 255, .32);
            --sent-from: #1a7a3c;
            --sent-to: #2ecc71;
            --recv-bg: rgba(255, 255, 255, .06);
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
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
        }

        /* ── SCROLLBAR PERSONALIZADO ── */
        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .12);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, .22);
        }

        /* ══════════════════════════════════════════
           SIDEBAR
        ══════════════════════════════════════════ */
        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background: linear-gradient(180deg, var(--bg2) 0%, var(--bg) 100%);
            border-right: 1px solid var(--borde);
            min-height: 100vh;
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 26px 22px 22px;
            border-bottom: 1px solid var(--borde2);
        }

        .sidebar-brand img {
            width: 36px;
            filter: drop-shadow(0 0 8px rgba(163, 240, 181, .25));
        }

        .sidebar-brand span {
            font-size: 17px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: .2px;
        }

        .sidebar-brand span em {
            color: var(--vlima);
            font-style: normal;
        }

        .sidebar-user {
            padding: 18px 22px;
            border-bottom: 1px solid var(--borde2);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .s-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--v2), var(--vlima));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
            border: 2px solid rgba(163, 240, 181, .25);
            box-shadow: 0 0 12px rgba(163, 240, 181, .15);
        }

        .s-user-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .s-user-tipo {
            font-size: 11px;
            color: var(--ink3);
            margin-top: 2px;
        }

        .sidebar-nav {
            padding: 14px 10px;
            flex: 1;
        }

        .nav-section-title {
            font-size: 9.5px;
            font-weight: 700;
            color: var(--ink3);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 14px 12px 5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 13px;
            border-radius: 12px;
            color: var(--ink2);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: all .2s;
            margin-bottom: 2px;
            position: relative;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, .06);
            color: var(--ink);
        }

        .nav-item.active {
            background: rgba(163, 240, 181, .1);
            color: var(--vlima);
            border: 1px solid rgba(163, 240, 181, .18);
        }

        .nav-item .ni {
            font-size: 16px;
        }

        .nav-badge {
            position: absolute;
            right: 12px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .sidebar-bottom {
            padding: 14px 10px;
            border-top: 1px solid var(--borde2);
        }

        .btn-logout-s {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 13px;
            border-radius: 12px;
            color: rgba(255, 107, 107, .75);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            transition: all .2s;
        }

        .btn-logout-s:hover {
            background: rgba(231, 76, 60, .1);
            color: #ff6b6b;
        }

        /* ══════════════════════════════════════════
           MAIN AREA
        ══════════════════════════════════════════ */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow: hidden;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: rgba(6, 14, 7, .96);
            backdrop-filter: blur(20px);
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            border-bottom: 1px solid var(--borde);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-left h2 {
            font-family: 'Syne', sans-serif;
            font-size: 18px;
            font-weight: 800;
            color: var(--ink);
        }

        .topbar-left p {
            font-size: 12px;
            color: var(--ink3);
            margin-top: 2px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .06);
            border: 1px solid var(--borde);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: background .2s;
            text-decoration: none;
        }

        .topbar-btn:hover {
            background: rgba(255, 255, 255, .12);
        }

        .t-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--v2), var(--vlima));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: white;
            cursor: pointer;
            border: 2px solid rgba(163, 240, 181, .2);
        }

        /* ══════════════════════════════════════════
           CHAT LAYOUT
        ══════════════════════════════════════════ */
        .chat-container {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* ── LISTA DE CONVERSACIONES ── */
        .chat-list {
            width: 320px;
            flex-shrink: 0;
            background: var(--panel);
            border-right: 1px solid var(--borde);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-list-header {
            padding: 20px 20px 14px;
            border-bottom: 1px solid var(--borde2);
        }

        .chat-list-header h3 {
            font-family: 'Syne', sans-serif;
            font-size: 16px;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 10px;
        }

        .chat-search {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--borde);
            border-radius: 12px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            background: rgba(255, 255, 255, .04);
            color: var(--ink);
            transition: border .2s, background .2s;
        }

        .chat-search::placeholder {
            color: var(--ink3);
        }

        .chat-search:focus {
            border-color: rgba(163, 240, 181, .4);
            background: rgba(255, 255, 255, .07);
        }

        .chat-list-body {
            flex: 1;
            overflow-y: auto;
        }

        .conv-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--ink3);
            font-size: 13px;
        }

        .conv-empty .ce-icon {
            font-size: 44px;
            margin-bottom: 10px;
            display: block;
        }

        .conv-item {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--borde2);
            cursor: pointer;
            transition: background .15s;
            position: relative;
        }

        .conv-item:hover {
            background: rgba(255, 255, 255, .04);
        }

        .conv-item.active {
            background: rgba(163, 240, 181, .07);
            border-left: 3px solid var(--v3);
        }

        .conv-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5b8eff, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
        }

        .conv-avatar.empresa {
            background: linear-gradient(135deg, var(--v2), var(--vlima));
        }

        .conv-avatar.artista {
            background: linear-gradient(135deg, var(--a2), #f0c040);
        }

        .conv-info {
            flex: 1;
            overflow: hidden;
        }

        .conv-name {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .conv-tipo-badge {
            font-size: 9.5px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 8px;
            background: rgba(163, 240, 181, .12);
            color: var(--vlima);
        }

        .conv-preview {
            font-size: 12px;
            color: var(--ink3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conv-preview.yo::before {
            content: 'Tú: ';
            font-weight: 600;
            color: var(--ink3);
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
            color: var(--ink3);
            white-space: nowrap;
        }

        .conv-unread {
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        /* ══════════════════════════════════════════
           ÁREA DE CHAT
        ══════════════════════════════════════════ */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 20% 50%, rgba(26, 122, 60, .06) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(26, 86, 219, .04) 0%, transparent 50%);
        }

        .chat-area-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: var(--ink3);
            gap: 14px;
        }

        .chat-area-empty .cae-icon {
            font-size: 56px;
            opacity: .4;
        }

        .chat-area-empty h3 {
            font-family: 'Syne', sans-serif;
            font-size: 18px;
            color: var(--ink2);
            font-weight: 700;
        }

        .chat-area-empty p {
            font-size: 13px;
            max-width: 280px;
            text-align: center;
            line-height: 1.6;
            color: var(--ink3);
        }

        /* ── CABECERA DEL CHAT ── */
        .chat-header {
            padding: 14px 24px;
            background: rgba(6, 14, 7, .96);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--borde);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .chat-header .ch-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5b8eff, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, .12);
        }

        .chat-header .ch-info h4 {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--ink);
        }

        .chat-header .ch-info p {
            font-size: 11.5px;
            color: var(--ink3);
            margin-top: 1px;
        }

        .chat-header .ch-back {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            margin-right: 2px;
            color: var(--ink2);
        }

        /* ── MENSAJES ── */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 22px 28px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .msg-date-sep {
            text-align: center;
            font-size: 10.5px;
            color: var(--ink3);
            padding: 12px 0 4px;
            font-weight: 600;
            letter-spacing: .5px;
        }

        @keyframes msgIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg-bubble {
            max-width: 68%;
            padding: 11px 17px;
            border-radius: 18px;
            font-size: 13.5px;
            line-height: 1.55;
            position: relative;
            word-wrap: break-word;
            animation: msgIn .22s ease both;
        }

        .msg-bubble.sent {
            align-self: flex-end;
            background: linear-gradient(135deg, var(--sent-from), var(--sent-to));
            color: white;
            border-bottom-right-radius: 5px;
            box-shadow: 0 4px 16px rgba(39, 168, 85, .25);
        }

        .msg-bubble.received {
            align-self: flex-start;
            background: var(--card);
            border: 1px solid var(--borde);
            color: var(--ink);
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .25);
        }

        .msg-time {
            font-size: 10px;
            margin-top: 5px;
            display: block;
        }

        .msg-bubble.sent .msg-time {
            color: rgba(255, 255, 255, .55);
            text-align: right;
        }

        .msg-bubble.received .msg-time {
            color: var(--ink3);
        }

        /* ── INPUT ── */
        .chat-input-area {
            padding: 14px 24px;
            background: rgba(6, 14, 7, .96);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--borde);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-input {
            flex: 1;
            padding: 12px 20px;
            border: 1px solid var(--borde);
            border-radius: 24px;
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            background: rgba(255, 255, 255, .05);
            color: var(--ink);
            transition: border .2s, background .2s;
        }

        .chat-input::placeholder {
            color: var(--ink3);
        }

        .chat-input:focus {
            border-color: rgba(163, 240, 181, .4);
            background: rgba(255, 255, 255, .08);
        }

        .chat-send {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--v2), var(--v3));
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(39, 168, 85, .35);
            flex-shrink: 0;
        }

        .chat-send:hover {
            transform: scale(1.08);
            box-shadow: 0 6px 18px rgba(39, 168, 85, .5);
        }

        .chat-send:disabled {
            opacity: .4;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ── BOTÓN NUEVO CHAT ── */
        .new-chat-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            background: rgba(163, 240, 181, .1);
            border: 1px solid rgba(163, 240, 181, .2);
            color: var(--vlima);
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: all .2s;
            margin-top: 10px;
            width: 100%;
            justify-content: center;
        }

        .new-chat-btn:hover {
            background: rgba(163, 240, 181, .18);
            box-shadow: 0 0 14px rgba(163, 240, 181, .12);
        }

        /* ══════════════════════════════════════════
           MODAL NUEVO MENSAJE
        ══════════════════════════════════════════ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .7);
            z-index: 500;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(6px);
        }

        .modal-overlay.open {
            display: flex;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-box {
            background: var(--bg2);
            border: 1px solid var(--borde);
            border-radius: 22px;
            max-width: 460px;
            width: 100%;
            padding: 30px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, .6);
            animation: fadeUp .3s ease both;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-box h3 {
            font-family: 'Syne', sans-serif;
            font-size: 19px;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 5px;
        }

        .modal-box .modal-sub {
            color: var(--ink3);
            font-size: 13px;
            margin-bottom: 16px;
        }

        .modal-close {
            position: absolute;
            top: 16px;
            right: 18px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid var(--borde);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            color: var(--ink2);
            transition: background .2s;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, .12);
        }

        .user-search-input {
            width: 100%;
            padding: 11px 15px;
            border: 1px solid var(--borde);
            border-radius: 13px;
            font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            background: rgba(255, 255, 255, .05);
            color: var(--ink);
            margin-bottom: 10px;
            transition: border .2s;
        }

        .user-search-input::placeholder {
            color: var(--ink3);
        }

        .user-search-input:focus {
            border-color: rgba(163, 240, 181, .4);
        }

        .user-list {
            max-height: 280px;
            overflow-y: auto;
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 13px;
            border-radius: 12px;
            cursor: pointer;
            transition: background .15s;
        }

        .user-item:hover {
            background: rgba(163, 240, 181, .07);
        }

        .user-item .ui-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #5b8eff, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
        }

        .user-item .ui-info h4 {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--ink);
        }

        .user-item .ui-info p {
            font-size: 12px;
            color: var(--ink3);
        }

        /* ══════════════════════════════════════════
           RESPONSIVE
        ══════════════════════════════════════════ */
        @media(max-width:900px) {
            .sidebar {
                width: 66px;
            }

            .sidebar-brand span,
            .s-user-info,
            .nav-item span:not(.ni),
            .nav-section-title,
            .btn-logout-s span {
                display: none;
            }

            .sidebar-brand {
                justify-content: center;
                padding: 18px 0;
            }

            .nav-item {
                justify-content: center;
                padding: 13px;
            }

            .sidebar-bottom {
                padding: 10px 0;
            }

            .btn-logout-s {
                justify-content: center;
                padding: 13px;
            }

            .chat-list {
                width: 270px;
            }
        }

        @media(max-width:768px) {
            .chat-list {
                width: 100%;
            }

            .chat-area {
                display: none;
            }

            .chat-area.mobile-show {
                display: flex;
                position: fixed;
                inset: 0;
                z-index: 200;
            }

            .chat-list.mobile-hide {
                display: none;
            }

            .chat-header .ch-back {
                display: block;
            }

            .topbar {
                padding: 0 18px;
            }
        }

        @media(max-width:640px) {
            .sidebar {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 58px;
                flex-direction: row;
                min-height: unset;
                border-radius: 18px 18px 0 0;
                top: auto;
                border-right: none;
                border-top: 1px solid var(--borde);
            }

            .sidebar-brand,
            .sidebar-user,
            .sidebar-bottom {
                display: none;
            }

            .sidebar-nav {
                display: flex;
                flex-direction: row;
                justify-content: space-around;
                align-items: center;
                padding: 0;
            }

            .nav-item {
                flex-direction: column;
                font-size: 9px;
                padding: 7px 10px;
                gap: 3px;
                border-radius: 8px;
            }

            .nav-item span:not(.ni) {
                display: block;
                font-size: 9px;
            }

            .main {
                padding-bottom: 58px;
            }

            .topbar {
                padding: 0 14px;
                height: 56px;
            }
        }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="Imagenes/Quibdo.png" alt="Logo">
            <span>Quibdó<em>Conecta</em></span>
        </div>
        <div class="sidebar-user">
            <div class="s-avatar"><?= $inicial ?></div>
            <div class="s-user-info">
                <div class="s-user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                <div class="s-user-tipo"><?= $tipo === 'empresa' ? '🏢 Empresa' : '👤 Candidato' ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-title">Principal</div>
            <a href="dashboard.php" class="nav-item"><span class="ni">🏠</span><span>Panel</span></a>
            <a href="Empleo.html" class="nav-item"><span class="ni">💼</span><span>Empleos</span></a>
            <a href="talentos.html" class="nav-item"><span class="ni">🌟</span><span>Talentos</span></a>
            <a href="Empresas.html" class="nav-item"><span class="ni">🏢</span><span>Empresas</span></a>
            <a href="chat.php" class="nav-item active"><span
                    class="ni">💬</span><span>Mensajes</span><?php if ($totalNoLeidos > 0): ?><span
                        class="nav-badge"><?= $totalNoLeidos ?></span><?php endif; ?></a>
            <a href="verificar_cuenta.php" class="nav-item"><span class="ni">✅</span><span>Verificar cuenta</span></a>
            <div class="nav-section-title">Soporte</div>
            <a href="Ayuda.html" class="nav-item"><span class="ni">❓</span><span>Ayuda</span></a>
        </nav>
        <div class="sidebar-bottom">
            <a href="Php/logout.php" class="btn-logout-s"><span class="ni">🚪</span><span>Cerrar sesión</span></a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="topbar-left">
                <h2>💬 Mensajes</h2>
                <p>Chatea con empresas, candidatos y artistas</p>
            </div>
            <div class="topbar-right">
                <a href="dashboard.php" class="topbar-btn" title="Mi Panel">🏠</a>
                <a href="index.html" class="topbar-btn" title="Inicio">🌐</a>
                <div class="t-avatar"><?= $inicial ?></div>
            </div>
        </header>

        <div class="chat-container">
            <div class="chat-list" id="chatList">
                <div class="chat-list-header">
                    <h3>Conversaciones</h3>
                    <input type="text" class="chat-search" id="searchConv" placeholder="🔍 Buscar conversación...">
                    <button class="new-chat-btn" onclick="abrirNuevoChat()">✉️ Nuevo mensaje</button>
                </div>
                <div class="chat-list-body" id="convListBody">
                    <div class="conv-empty" id="convEmpty">
                        <span class="ce-icon">💬</span>
                        Aún no tienes conversaciones.<br>¡Envía tu primer mensaje!
                    </div>
                </div>
            </div>

            <div class="chat-area" id="chatArea">
                <div class="chat-area-empty" id="chatEmpty">
                    <span class="cae-icon">💬</span>
                    <h3>Selecciona una conversación</h3>
                    <p>Elige una conversación o envía un nuevo mensaje para comenzar</p>
                </div>
                <div id="chatActive" style="display:none; flex-direction:column; height:100%;">
                    <div class="chat-header" id="chatHeader">
                        <button class="ch-back" onclick="volverALista()">←</button>
                        <div class="ch-avatar" id="chAvatar">?</div>
                        <div class="ch-info">
                            <h4 id="chNombre">Cargando...</h4>
                            <p id="chTipo">...</p>
                        </div>
                    </div>
                    <div class="chat-messages" id="chatMessages"></div>
                    <div class="chat-input-area">
                        <input type="text" class="chat-input" id="chatInput" placeholder="Escribe un mensaje..."
                            maxlength="2000" autocomplete="off">
                        <button class="chat-send" id="chatSendBtn" onclick="enviarMensaje()" title="Enviar">➤</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalNuevo">
        <div class="modal-box">
            <button class="modal-close" onclick="cerrarNuevoChat()">✕</button>
            <h3>✉️ Nuevo mensaje</h3>
            <p class="modal-sub">Busca un usuario para iniciar una conversación</p>
            <input type="text" class="user-search-input" id="userSearchInput" placeholder="Escribe un nombre..."
                oninput="buscarUsuarios()">
            <div class="user-list" id="userSearchResults">
                <div style="padding:20px; text-align:center; color:#aaa; font-size:13px;">Escribe para buscar usuarios
                </div>
            </div>
        </div>
    </div>

    <script>
        const YO = <?= (int) $usuario['id'] ?>;
        let chatActivo = null;
        let pollingInterval = null;
        let conversaciones = [];

        // ── TODAS las llamadas usan ?action= dentro del mismo chat.php ──
        const API = url => 'chat.php?' + url;

        document.addEventListener('DOMContentLoaded', () => {
            cargarConversaciones();
            <?php if ($conUsuario > 0): ?>
                setTimeout(() => abrirChat(<?= $conUsuario ?>), 500);
            <?php endif; ?>

            document.getElementById('chatInput').addEventListener('keydown', e => {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); enviarMensaje(); }
            });
            document.getElementById('searchConv').addEventListener('input', filtrarConversaciones);
            document.getElementById('modalNuevo').addEventListener('click', e => { if (e.target === e.currentTarget) cerrarNuevoChat(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarNuevoChat(); });
        });

        async function cargarConversaciones() {
            try {
                const res = await fetch(API('action=conversaciones'));
                if (res.status === 401) { window.location.href = 'inicio_sesion.php'; return; }
                const data = await res.json();
                if (!data.ok) return;
                conversaciones = data.conversaciones;
                renderConversaciones();
            } catch (e) { console.error('Error cargando conversaciones', e); }
        }

        function renderConversaciones() {
            const body = document.getElementById('convListBody');
            const empty = document.getElementById('convEmpty');
            const search = document.getElementById('searchConv').value.toLowerCase();
            const filtered = conversaciones.filter(c => (c.nombre + ' ' + (c.apellido || '')).toLowerCase().includes(search));

            if (!filtered.length) {
                body.innerHTML = '';
                empty.style.display = 'block';
                body.appendChild(empty);
                return;
            }
            empty.style.display = 'none';
            body.innerHTML = filtered.map(c => {
                const nombre = c.nombre + ' ' + (c.apellido || '');
                const ini = c.nombre.charAt(0).toUpperCase();
                const avatarClass = c.tipo === 'empresa' ? 'empresa' : c.tipo === 'artista' ? 'artista' : '';
                const previewClass = c.yo_envie_ultimo ? 'yo' : '';
                const preview = c.ultimo_mensaje ? (c.ultimo_mensaje.length > 40 ? c.ultimo_mensaje.substring(0, 40) + '...' : c.ultimo_mensaje) : 'Sin mensajes';
                const active = chatActivo === parseInt(c.id) ? 'active' : '';
                const unread = parseInt(c.no_leidos) > 0 ? `<span class="conv-unread">${c.no_leidos}</span>` : '';
                const tipoLabel = c.tipo === 'empresa' ? 'Empresa' : c.tipo === 'artista' ? 'Artista' : '';
                const tipoBadge = tipoLabel ? `<span class="conv-tipo-badge">${tipoLabel}</span>` : '';
                return `
            <div class="conv-item ${active}" onclick="abrirChat(${c.id})">
                <div class="conv-avatar ${avatarClass}">${ini}</div>
                <div class="conv-info">
                    <div class="conv-name">${esc(nombre)} ${tipoBadge}</div>
                    <div class="conv-preview ${previewClass}">${esc(preview)}</div>
                </div>
                <div class="conv-meta">
                    <span class="conv-time">${formatFecha(c.ultima_fecha)}</span>
                    ${unread}
                </div>
            </div>`;
            }).join('');
        }

        function filtrarConversaciones() { renderConversaciones(); }

        async function abrirChat(userId) {
            chatActivo = userId;
            renderConversaciones();
            document.getElementById('chatEmpty').style.display = 'none';
            document.getElementById('chatActive').style.display = 'flex';
            document.getElementById('chNombre').textContent = 'Cargando...';
            document.getElementById('chTipo').textContent = '...';
            document.getElementById('chatMessages').innerHTML = '';
            if (window.innerWidth <= 768) {
                document.getElementById('chatList').classList.add('mobile-hide');
                document.getElementById('chatArea').classList.add('mobile-show');
            }
            if (pollingInterval) { clearInterval(pollingInterval); pollingInterval = null; }
            await cargarMensajes(userId);
            pollingInterval = setInterval(() => cargarMensajes(userId, true), 5000);
            document.getElementById('chatInput').focus();
        }

        async function cargarMensajes(userId, silent = false) {
            try {
                const res = await fetch(API(`action=mensajes&con=${userId}`));
                if (res.status === 401) { window.location.href = 'inicio_sesion.php'; return; }
                const data = await res.json();
                if (!data.ok) {
                    if (!silent) mostrarError('No se pudieron cargar los mensajes. Intenta de nuevo.');
                    return;
                }
                if (data.usuario) {
                    document.getElementById('chNombre').textContent = data.usuario.nombre + ' ' + (data.usuario.apellido || '');
                    document.getElementById('chTipo').textContent = data.usuario.tipo === 'empresa' ? '🏢 Empresa' : data.usuario.tipo === 'artista' ? '🎵 Artista' : '👤 Candidato';
                    const av = document.getElementById('chAvatar');
                    av.textContent = data.usuario.nombre.charAt(0).toUpperCase();
                    av.style.background = data.usuario.tipo === 'empresa' ? 'linear-gradient(135deg,#1f9d55,#2ecc71)' : data.usuario.tipo === 'artista' ? 'linear-gradient(135deg,#d4a017,#f0c040)' : 'linear-gradient(135deg,#667eea,#764ba2)';
                }
                renderMensajes(data.mensajes);
                if (!silent) cargarConversaciones();
            } catch (e) {
                if (!silent) mostrarError('Error de conexión. Verifica tu internet.');
                console.error(e);
            }
        }

        function renderMensajes(mensajes) {
            const container = document.getElementById('chatMessages');
            let html = '', lastDate = '';
            mensajes.forEach(m => {
                const fecha = m.creado_en.split(' ')[0];
                if (fecha !== lastDate) { lastDate = fecha; html += `<div class="msg-date-sep">${formatFechaLarga(fecha)}</div>`; }
                const tipo = parseInt(m.de_usuario) === YO ? 'sent' : 'received';
                const hora = m.creado_en.split(' ')[1].substring(0, 5);
                html += `<div class="msg-bubble ${tipo}">${esc(m.mensaje)}<span class="msg-time">${hora}</span></div>`;
            });
            const wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
            container.innerHTML = html;
            if (wasAtBottom || !mensajes.length) container.scrollTop = container.scrollHeight;
        }

        async function enviarMensaje() {
            const input = document.getElementById('chatInput');
            const btn = document.getElementById('chatSendBtn');
            const msg = input.value.trim();
            if (!msg || !chatActivo) return;
            btn.disabled = true;
            input.value = '';
            try {
                const data = new FormData();
                data.append('para_usuario', chatActivo);
                data.append('mensaje', msg);
                const res = await fetch(API('action=enviar'), { method: 'POST', body: data });
                if (res.status === 401) { window.location.href = 'inicio_sesion.php'; return; }
                const json = await res.json();
                if (json.ok) {
                    const container = document.getElementById('chatMessages');
                    const hora = new Date().toTimeString().substring(0, 5);
                    container.innerHTML += `<div class="msg-bubble sent">${esc(msg)}<span class="msg-time">${hora}</span></div>`;
                    container.scrollTop = container.scrollHeight;
                    cargarConversaciones();
                } else { mostrarError(json.msg || 'Error al enviar'); input.value = msg; }
            } catch (e) { mostrarError('Error de conexión'); input.value = msg; }
            btn.disabled = false;
            input.focus();
        }

        function abrirNuevoChat() { document.getElementById('modalNuevo').classList.add('open'); document.getElementById('userSearchInput').focus(); }
        function cerrarNuevoChat() { document.getElementById('modalNuevo').classList.remove('open'); }

        let searchTimeout = null;
        async function buscarUsuarios() {
            const q = document.getElementById('userSearchInput').value.trim();
            const container = document.getElementById('userSearchResults');
            if (q.length < 2) { container.innerHTML = '<div style="padding:20px;text-align:center;color:#aaa;font-size:13px;">Escribe al menos 2 caracteres</div>'; return; }
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(API(`action=buscar&q=${encodeURIComponent(q)}`));
                    const data = await res.json();
                    if (!data.ok || !data.usuarios.length) { container.innerHTML = '<div style="padding:20px;text-align:center;color:#aaa;font-size:13px;">No se encontraron usuarios</div>'; return; }
                    container.innerHTML = data.usuarios.map(u => `
                <div class="user-item" onclick="seleccionarUsuario(${u.id})">
                    <div class="ui-avatar">${u.nombre.charAt(0).toUpperCase()}</div>
                    <div class="ui-info">
                        <h4>${esc(u.nombre + ' ' + (u.apellido || ''))}</h4>
                        <p>${u.tipo === 'empresa' ? '🏢 Empresa' : u.tipo === 'artista' ? '🎵 Artista' : '👤 Candidato'}</p>
                    </div>
                </div>`).join('');
                } catch (e) { console.error(e); }
            }, 300);
        }

        function seleccionarUsuario(id) { cerrarNuevoChat(); abrirChat(id); }

        function mostrarError(msg) {
            let toast = document.getElementById('chat-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'chat-toast';
                toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:rgba(231,76,60,.95);color:white;padding:12px 22px;border-radius:12px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.4);backdrop-filter:blur(8px);transition:opacity .3s;';
                document.body.appendChild(toast);
            }
            toast.textContent = msg;
            toast.style.opacity = '1';
            clearTimeout(toast._t);
            toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 3500);
        }

        function volverALista() {
            document.getElementById('chatList').classList.remove('mobile-hide');
            document.getElementById('chatArea').classList.remove('mobile-show');
            if (pollingInterval) { clearInterval(pollingInterval); pollingInterval = null; }
            chatActivo = null;
            cargarConversaciones();
        }

        function esc(str) { const d = document.createElement('div'); d.textContent = str; return d.innerHTML; }

        function formatFecha(fechaStr) {
            if (!fechaStr) return '';
            const d = new Date(fechaStr.replace(' ', 'T'));
            const hoy = new Date();
            const diff = Math.floor((hoy - d) / 86400000);
            if (diff === 0) return d.toTimeString().substring(0, 5);
            if (diff === 1) return 'Ayer';
            if (diff < 7) return ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][d.getDay()];
            return `${d.getDate()}/${d.getMonth() + 1}`;
        }

        function formatFechaLarga(fechaStr) {
            const d = new Date(fechaStr + 'T00:00:00');
            const hoy = new Date();
            const diff = Math.floor((hoy - d) / 86400000);
            if (diff === 0) return 'Hoy';
            if (diff === 1) return 'Ayer';
            const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            return `${d.getDate()} de ${meses[d.getMonth()]}`;
        }
    </script>
</body>

</html>