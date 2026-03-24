<?php
// ============================================================
// chat.php — Chat interno QuibdóConecta (todo en uno)
// ============================================================
ini_set('session.gc_maxlifetime', 604800);
ini_set('session.cookie_lifetime', 604800);
session_set_cookie_params(['lifetime'=>604800,'path'=>'/','samesite'=>'Lax','httponly'=>true]);
session_start();
require_once __DIR__ . '/Php/db.php';

// ─── MANEJO DE ACCIONES AJAX ────────────────────────────────
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

    // ── CONVERSACIONES ───────────────────────────────────────
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

    // ── MENSAJES ─────────────────────────────────────────────
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

    // ── ENVIAR ───────────────────────────────────────────────
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

    // ── BUSCAR USUARIOS ──────────────────────────────────────
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

// ─── CARGA NORMAL DE LA PÁGINA ───────────────────────────────
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
    <title>Mensajes — QuibdóConecta</title>
    <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Lora:ital,wght@0,500;1,400&display=swap" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════════════════
           VARIABLES — TEMA CLARO QuibdóConecta
        ══════════════════════════════════════════════════════ */
        :root {
            --verde:       #1a9e4a;
            --verde-med:   #22c55e;
            --verde-light: #bbf7d0;
            --verde-pale:  #f0fdf4;
            --verde-mid:   #dcfce7;
            --acento:      #16a34a;
            --acento2:     #15803d;

            --bg:          #f8fafc;
            --bg2:         #ffffff;
            --sidebar-bg:  #ffffff;
            --panel:       #ffffff;

            --border:      #e2e8f0;
            --border2:     #f1f5f9;

            --ink:         #0f172a;
            --ink2:        #334155;
            --ink3:        #64748b;
            --ink4:        #94a3b8;

            --sent-bg:     linear-gradient(135deg, #16a34a, #22c55e);
            --recv-bg:     #f1f5f9;
            --recv-ink:    #1e293b;

            --shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md:   0 4px 16px rgba(0,0,0,.08);
            --shadow-lg:   0 20px 60px rgba(0,0,0,.12);

            --radius-sm:   10px;
            --radius-md:   16px;
            --radius-lg:   22px;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior:smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
        }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ══════════════════════════════════════════════════════
           SIDEBAR
        ══════════════════════════════════════════════════════ */
        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
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
            padding: 24px 20px 20px;
            border-bottom: 1px solid var(--border2);
        }

        .sidebar-brand img {
            width: 34px;
        }

        .sidebar-brand span {
            font-size: 16px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -.3px;
        }

        .sidebar-brand span em {
            color: var(--verde);
            font-style: normal;
        }

        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border2);
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .s-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--verde), var(--verde-med));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
            box-shadow: 0 0 0 3px var(--verde-light);
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
            color: var(--ink4);
            margin-top: 1px;
        }

        .sidebar-nav {
            padding: 12px 10px;
            flex: 1;
        }

        .nav-section-title {
            font-size: 9px;
            font-weight: 700;
            color: var(--ink4);
            text-transform: uppercase;
            letter-spacing: 1.8px;
            padding: 14px 12px 5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: var(--radius-sm);
            color: var(--ink3);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all .18s;
            margin-bottom: 2px;
            position: relative;
        }

        .nav-item:hover {
            background: var(--border2);
            color: var(--ink);
        }

        .nav-item.active {
            background: var(--verde-pale);
            color: var(--verde);
            font-weight: 700;
            border: 1px solid var(--verde-light);
        }

        .nav-item .ni { font-size: 15px; }

        .nav-badge {
            position: absolute;
            right: 10px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 17px;
            text-align: center;
        }

        .sidebar-bottom {
            padding: 12px 10px;
            border-top: 1px solid var(--border2);
        }

        .btn-logout-s {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: var(--radius-sm);
            color: #ef4444;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all .18s;
        }

        .btn-logout-s:hover {
            background: #fef2f2;
        }

        /* ══════════════════════════════════════════════════════
           MAIN
        ══════════════════════════════════════════════════════ */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow: hidden;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: var(--bg2);
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-left h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 17px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -.3px;
        }

        .topbar-left p {
            font-size: 11.5px;
            color: var(--ink4);
            margin-top: 1px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .topbar-btn {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--bg);
            border: 1px solid var(--border);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            transition: background .18s;
            text-decoration: none;
        }

        .topbar-btn:hover { background: var(--border2); }

        .t-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--verde), var(--verde-med));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 800;
            color: white;
            cursor: pointer;
            box-shadow: 0 0 0 2px var(--verde-light);
        }

        /* ══════════════════════════════════════════════════════
           CHAT LAYOUT
        ══════════════════════════════════════════════════════ */
        .chat-container {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* ── LISTA CONVERSACIONES ── */
        .chat-list {
            width: 310px;
            flex-shrink: 0;
            background: var(--bg2);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-list-header {
            padding: 18px 18px 12px;
            border-bottom: 1px solid var(--border2);
        }

        .chat-list-header h3 {
            font-size: 15px;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 10px;
            letter-spacing: -.2px;
        }

        .chat-search {
            width: 100%;
            padding: 9px 14px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 13px;
            font-family: 'Outfit', sans-serif;
            outline: none;
            background: var(--bg);
            color: var(--ink);
            transition: border .18s, box-shadow .18s;
        }

        .chat-search::placeholder { color: var(--ink4); }

        .chat-search:focus {
            border-color: var(--verde-med);
            box-shadow: 0 0 0 3px rgba(34,197,94,.12);
        }

        .chat-list-body {
            flex: 1;
            overflow-y: auto;
        }

        .conv-empty {
            padding: 44px 20px;
            text-align: center;
            color: var(--ink4);
            font-size: 13px;
            line-height: 1.6;
        }

        .conv-empty .ce-icon {
            font-size: 40px;
            margin-bottom: 10px;
            display: block;
            opacity: .5;
        }

        .conv-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 18px;
            border-bottom: 1px solid var(--border2);
            cursor: pointer;
            transition: background .14s;
            position: relative;
        }

        .conv-item:hover { background: var(--bg); }

        .conv-item.active {
            background: var(--verde-pale);
            border-left: 3px solid var(--verde);
        }

        .conv-avatar {
            width: 43px;
            height: 43px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }

        .conv-avatar.empresa { background: linear-gradient(135deg, #0ea5e9, #06b6d4); }
        .conv-avatar.artista { background: linear-gradient(135deg, #f59e0b, #fbbf24); }

        .conv-info { flex: 1; overflow: hidden; }

        .conv-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .conv-tipo-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 20px;
            background: var(--verde-mid);
            color: var(--acento2);
        }

        .conv-preview {
            font-size: 11.5px;
            color: var(--ink4);
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
            color: var(--ink4);
            white-space: nowrap;
        }

        .conv-unread {
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 17px;
            text-align: center;
        }

        /* ── BOTÓN NUEVO CHAT ── */
        .new-chat-btn {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            background: var(--verde);
            border: none;
            color: white;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            transition: all .2s;
            margin-top: 10px;
            width: 100%;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(22,163,74,.25);
        }

        .new-chat-btn:hover {
            background: var(--acento2);
            box-shadow: 0 4px 16px rgba(22,163,74,.35);
            transform: translateY(-1px);
        }

        /* ══════════════════════════════════════════════════════
           ÁREA DE CHAT
        ══════════════════════════════════════════════════════ */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 10% 20%, rgba(34,197,94,.05) 0%, transparent 50%),
                radial-gradient(ellipse at 90% 80%, rgba(99,102,241,.04) 0%, transparent 50%);
        }

        .chat-area-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: var(--ink4);
            gap: 12px;
        }

        .chat-area-empty .cae-icon {
            font-size: 52px;
            opacity: .3;
        }

        .chat-area-empty h3 {
            font-size: 17px;
            color: var(--ink2);
            font-weight: 700;
        }

        .chat-area-empty p {
            font-size: 13px;
            max-width: 260px;
            text-align: center;
            line-height: 1.6;
            color: var(--ink4);
        }

        /* ── CABECERA DEL CHAT ── */
        .chat-header {
            padding: 13px 22px;
            background: var(--bg2);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 13px;
            box-shadow: var(--shadow-sm);
        }

        .chat-header .ch-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
        }

        .chat-header .ch-info h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
        }

        .chat-header .ch-info p {
            font-size: 11px;
            color: var(--ink4);
            margin-top: 1px;
        }

        .chat-header .ch-back {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 6px;
            margin-right: 2px;
            color: var(--ink3);
            border-radius: 8px;
            transition: background .15s;
        }

        .chat-header .ch-back:hover { background: var(--bg); }

        /* ── MENSAJES ── */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 26px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .msg-date-sep {
            text-align: center;
            font-size: 10.5px;
            color: var(--ink4);
            padding: 10px 0 4px;
            font-weight: 600;
            letter-spacing: .5px;
        }

        .msg-date-sep span {
            background: var(--border2);
            padding: 3px 12px;
            border-radius: 20px;
        }

        @keyframes msgIn {
            from { opacity:0; transform: translateY(8px) scale(.98); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }

        .msg-bubble {
            max-width: 66%;
            padding: 10px 16px;
            border-radius: 18px;
            font-size: 13.5px;
            line-height: 1.55;
            position: relative;
            word-wrap: break-word;
            animation: msgIn .2s ease both;
        }

        .msg-bubble.sent {
            align-self: flex-end;
            background: var(--sent-bg);
            color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 3px 12px rgba(22,163,74,.22);
        }

        .msg-bubble.received {
            align-self: flex-start;
            background: var(--recv-bg);
            color: var(--recv-ink);
            border-bottom-left-radius: 4px;
            box-shadow: var(--shadow-sm);
        }

        .msg-time {
            font-size: 9.5px;
            margin-top: 4px;
            display: block;
        }

        .msg-bubble.sent .msg-time   { color: rgba(255,255,255,.6); text-align: right; }
        .msg-bubble.received .msg-time { color: var(--ink4); }

        /* ── INPUT ── */
        .chat-input-area {
            padding: 14px 22px;
            background: var(--bg2);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            padding: 11px 18px;
            border: 1.5px solid var(--border);
            border-radius: 24px;
            font-size: 13.5px;
            font-family: 'Outfit', sans-serif;
            outline: none;
            background: var(--bg);
            color: var(--ink);
            transition: border .18s, box-shadow .18s;
        }

        .chat-input::placeholder { color: var(--ink4); }

        .chat-input:focus {
            border-color: var(--verde-med);
            box-shadow: 0 0 0 3px rgba(34,197,94,.12);
            background: white;
        }

        .chat-send {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--verde), var(--verde-med));
            border: none;
            color: white;
            font-size: 17px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .2s, box-shadow .2s;
            box-shadow: 0 3px 12px rgba(22,163,74,.3);
            flex-shrink: 0;
        }

        .chat-send:hover {
            transform: scale(1.08);
            box-shadow: 0 5px 18px rgba(22,163,74,.45);
        }

        .chat-send:disabled {
            opacity: .4;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ══════════════════════════════════════════════════════
           MODAL NUEVO MENSAJE
        ══════════════════════════════════════════════════════ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.3);
            z-index: 500;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.open { display: flex; }

        @keyframes fadeUp {
            from { opacity:0; transform: translateY(16px) scale(.97); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }

        .modal-box {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            max-width: 440px;
            width: 100%;
            padding: 28px;
            box-shadow: var(--shadow-lg);
            animation: fadeUp .25s ease both;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-box h3 {
            font-size: 18px;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 4px;
            letter-spacing: -.3px;
        }

        .modal-box .modal-sub {
            color: var(--ink4);
            font-size: 12.5px;
            margin-bottom: 16px;
        }

        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            color: var(--ink3);
            transition: background .15s;
        }

        .modal-close:hover { background: var(--border2); }

        .user-search-input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 13px;
            font-family: 'Outfit', sans-serif;
            outline: none;
            background: var(--bg);
            color: var(--ink);
            margin-bottom: 10px;
            transition: border .18s, box-shadow .18s;
        }

        .user-search-input::placeholder { color: var(--ink4); }

        .user-search-input:focus {
            border-color: var(--verde-med);
            box-shadow: 0 0 0 3px rgba(34,197,94,.12);
            background: white;
        }

        .user-list { max-height: 280px; overflow-y: auto; }

        .user-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: background .14s;
        }

        .user-item:hover { background: var(--verde-pale); }

        .user-item .ui-avatar {
            width: 37px;
            height: 37px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
        }

        .user-item .ui-info h4 {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
        }

        .user-item .ui-info p {
            font-size: 11.5px;
            color: var(--ink4);
        }

        /* ══════════════════════════════════════════════════════
           RESPONSIVE
        ══════════════════════════════════════════════════════ */
        @media(max-width:900px) {
            .sidebar { width: 62px; }
            .sidebar-brand span, .s-user-info, .nav-item span:not(.ni),
            .nav-section-title, .btn-logout-s span { display: none; }
            .sidebar-brand { justify-content: center; padding: 16px 0; }
            .nav-item { justify-content: center; padding: 12px; }
            .sidebar-bottom { padding: 10px 0; }
            .btn-logout-s { justify-content: center; padding: 12px; }
            .chat-list { width: 260px; }
        }

        @media(max-width:768px) {
            .chat-list { width: 100%; }
            .chat-area { display: none; }
            .chat-area.mobile-show {
                display: flex;
                position: fixed;
                inset: 0;
                z-index: 200;
            }
            .chat-list.mobile-hide { display: none; }
            .chat-header .ch-back { display: block; }
            .topbar { padding: 0 16px; }
        }

        @media(max-width:640px) {
            .sidebar {
                position: fixed;
                bottom: 0; left: 0;
                width: 100%;
                height: 56px;
                flex-direction: row;
                min-height: unset;
                border-radius: 16px 16px 0 0;
                top: auto;
                border-right: none;
                border-top: 1px solid var(--border);
                box-shadow: 0 -4px 20px rgba(0,0,0,.06);
            }
            .sidebar-brand, .sidebar-user, .sidebar-bottom { display: none; }
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
                padding: 6px 10px;
                gap: 2px;
                border-radius: 8px;
            }
            .nav-item span:not(.ni) { display: block; font-size: 9px; }
            .main { padding-bottom: 56px; }
            .topbar { padding: 0 14px; height: 54px; }
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
            <a href="talentos.html" class="nav-item"><span class="ni">🎭</span><span>Talentos</span></a>
            <a href="Empresas.html" class="nav-item"><span class="ni">🏢</span><span>Empresas</span></a>
            <a href="chat.php" class="nav-item active"><span class="ni">💬</span><span>Mensajes</span><?php if ($totalNoLeidos > 0): ?><span class="nav-badge"><?= $totalNoLeidos ?></span><?php endif; ?></a>
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
                <a href="index.html" class="topbar-btn" title="Inicio">🌿</a>
                <div class="t-avatar"><?= $inicial ?></div>
            </div>
        </header>

        <div class="chat-container">
            <div class="chat-list" id="chatList">
                <div class="chat-list-header">
                    <h3>Conversaciones</h3>
                    <input type="text" class="chat-search" id="searchConv" placeholder="🔍 Buscar conversación...">
                    <button class="new-chat-btn" onclick="abrirNuevoChat()">✏️ Nuevo mensaje</button>
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
            <h3>✏️ Nuevo mensaje</h3>
            <p class="modal-sub">Busca un usuario para iniciar una conversación</p>
            <input type="text" class="user-search-input" id="userSearchInput" placeholder="Escribe un nombre..."
                oninput="buscarUsuarios()">
            <div class="user-list" id="userSearchResults">
                <div style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">Escribe para buscar usuarios</div>
            </div>
        </div>
    </div>

    <script>
        const YO = <?= (int) $usuario['id'] ?>;
        let chatActivo = null;
        let pollingInterval = null;
        let conversaciones = [];

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
            const search = document.getElementById('searchConv').value.toLowerCase();
            const filtered = conversaciones.filter(c => (c.nombre + ' ' + (c.apellido || '')).toLowerCase().includes(search));

            if (!filtered.length) {
                body.innerHTML = '<div class="conv-empty"><span class="ce-icon">💬</span>Sin conversaciones</div>';
                return;
            }
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
                    document.getElementById('chTipo').textContent = data.usuario.tipo === 'empresa' ? '🏢 Empresa' : data.usuario.tipo === 'artista' ? '🎭 Artista' : '👤 Candidato';
                    const av = document.getElementById('chAvatar');
                    av.textContent = data.usuario.nombre.charAt(0).toUpperCase();
                    av.style.background = data.usuario.tipo === 'empresa'
                        ? 'linear-gradient(135deg,#0ea5e9,#06b6d4)'
                        : data.usuario.tipo === 'artista'
                        ? 'linear-gradient(135deg,#f59e0b,#fbbf24)'
                        : 'linear-gradient(135deg,#6366f1,#8b5cf6)';
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
                if (fecha !== lastDate) { lastDate = fecha; html += `<div class="msg-date-sep"><span>${formatFechaLarga(fecha)}</span></div>`; }
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
            if (q.length < 2) { container.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px;">Escribe al menos 2 caracteres</div>'; return; }
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(API(`action=buscar&q=${encodeURIComponent(q)}`));
                    const data = await res.json();
                    if (!data.ok || !data.usuarios.length) { container.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:13px;">No se encontraron usuarios</div>'; return; }
                    container.innerHTML = data.usuarios.map(u => `
                <div class="user-item" onclick="seleccionarUsuario(${u.id})">
                    <div class="ui-avatar">${u.nombre.charAt(0).toUpperCase()}</div>
                    <div class="ui-info">
                        <h4>${esc(u.nombre + ' ' + (u.apellido || ''))}</h4>
                        <p>${u.tipo === 'empresa' ? '🏢 Empresa' : u.tipo === 'artista' ? '🎭 Artista' : '👤 Candidato'}</p>
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
                toast.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#ef4444;color:white;padding:11px 22px;border-radius:12px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.15);transition:opacity .3s;font-family:Outfit,sans-serif;';
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
            const meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
            return `${d.getDate()} de ${meses[d.getMonth()]}`;
        }
    </script>

<!-- Widget de sesión activa — QuibdóConecta -->
<script src="js/sesion_widget.js"></script>
</body>

</html>