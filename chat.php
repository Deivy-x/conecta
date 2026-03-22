<?php
// ============================================================
// chat.php — Chat interno QuibdóConecta (todo en uno)
// InfinityFree-compatible: sin fetch externo
// ============================================================
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
            $conv['ultima_fecha']   = $ultimo ? $ultimo['creado_en'] : $conv['ultima_fecha'];
            $conv['yo_envie_ultimo'] = $ultimo && (int)$ultimo['de_usuario'] === $yo;
        }

        $total = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario = ? AND leido = 0");
        $total->execute([$yo]);
        echo json_encode(['ok' => true, 'conversaciones' => $conversaciones, 'total_no_leidos' => (int)$total->fetchColumn()]);
        exit;
    }

    // ── MENSAJES ────────────────────────────────────────────
    if ($action === 'mensajes') {
        $con = (int) ($_GET['con'] ?? 0);
        if (!$con) { echo json_encode(['ok' => false, 'msg' => 'Falta ID.']); exit; }

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
        $para    = (int) ($_POST['para_usuario'] ?? 0);
        $mensaje = trim($_POST['mensaje'] ?? '');

        if (!$para || $para === $yo)    { echo json_encode(['ok' => false, 'msg' => 'Destinatario inválido.']); exit; }
        if ($mensaje === '')            { echo json_encode(['ok' => false, 'msg' => 'Mensaje vacío.']); exit; }
        if (mb_strlen($mensaje) > 2000) { echo json_encode(['ok' => false, 'msg' => 'Mensaje muy largo.']); exit; }

        $check = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND activo = 1");
        $check->execute([$para]);
        if (!$check->fetch()) { echo json_encode(['ok' => false, 'msg' => 'Usuario no existe.']); exit; }

        $db->prepare("INSERT INTO mensajes (de_usuario, para_usuario, mensaje) VALUES (?, ?, ?)")
           ->execute([$yo, $para, $mensaje]);

        echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId(), 'creado_en' => date('Y-m-d H:i:s')]);
        exit;
    }

    // ── BUSCAR USUARIOS ─────────────────────────────────────
    if ($action === 'buscar') {
        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'usuarios' => []]); exit; }

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

if (!$usuario) { session_destroy(); header('Location: inicio_sesion.php'); exit; }

$inicial = strtoupper(mb_substr($usuario['nombre'], 0, 1));
$tipo    = $usuario['tipo'] ?? 'candidato';

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
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { font-family:'DM Sans',sans-serif; background:#f0f4f8; color:#111; min-height:100vh; display:flex; }

        .sidebar { width:260px; flex-shrink:0; background:linear-gradient(180deg,#0f172a 0%,#1a2e1a 60%,#0a2010 100%); min-height:100vh; position:sticky; top:0; height:100vh; display:flex; flex-direction:column; overflow-y:auto; z-index:100; }
        .sidebar-brand { display:flex; align-items:center; gap:10px; padding:28px 24px 24px; border-bottom:1px solid rgba(255,255,255,.07); }
        .sidebar-brand img { width:38px; }
        .sidebar-brand span { font-size:18px; font-weight:800; color:white; }
        .sidebar-brand span em { color:#2ecc71; font-style:normal; }
        .sidebar-user { padding:20px 24px; border-bottom:1px solid rgba(255,255,255,.06); display:flex; align-items:center; gap:12px; }
        .s-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#1f9d55,#2ecc71); display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:800; color:white; flex-shrink:0; border:2px solid rgba(46,204,113,.4); }
        .s-user-info { overflow:hidden; }
        .s-user-name { font-size:13px; font-weight:700; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .s-user-tipo { font-size:11px; color:rgba(255,255,255,.45); }
        .sidebar-nav { padding:16px 12px; flex:1; }
        .nav-section-title { font-size:10px; font-weight:700; color:rgba(255,255,255,.3); text-transform:uppercase; letter-spacing:1.2px; padding:14px 12px 6px; }
        .nav-item { display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:12px; color:rgba(255,255,255,.58); text-decoration:none; font-size:14px; font-weight:500; transition:all .2s; margin-bottom:2px; position:relative; }
        .nav-item:hover { background:rgba(255,255,255,.07); color:white; }
        .nav-item.active { background:rgba(46,204,113,.15); color:#2ecc71; border:1px solid rgba(46,204,113,.2); }
        .nav-item .ni { font-size:17px; }
        .nav-badge { position:absolute; right:12px; background:#e74c3c; color:white; font-size:10px; font-weight:800; padding:2px 7px; border-radius:10px; min-width:18px; text-align:center; }
        .sidebar-bottom { padding:16px 12px; border-top:1px solid rgba(255,255,255,.06); }
        .btn-logout-s { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:12px; color:rgba(255,107,107,.8); text-decoration:none; font-size:14px; font-weight:600; transition:all .2s; }
        .btn-logout-s:hover { background:rgba(231,76,60,.12); color:#ff6b6b; }

        .main { flex:1; display:flex; flex-direction:column; min-height:100vh; overflow:hidden; }
        .topbar { background:white; height:68px; display:flex; align-items:center; justify-content:space-between; padding:0 36px; border-bottom:1px solid rgba(0,0,0,.07); box-shadow:0 2px 8px rgba(0,0,0,.04); position:sticky; top:0; z-index:50; }
        .topbar-left h2 { font-family:'Syne',sans-serif; font-size:20px; font-weight:800; }
        .topbar-left p { font-size:13px; color:#888; margin-top:1px; }
        .topbar-right { display:flex; align-items:center; gap:14px; }
        .topbar-btn { width:40px; height:40px; border-radius:50%; background:#f1f5f9; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:18px; transition:background .2s; text-decoration:none; }
        .topbar-btn:hover { background:#e2e8f0; }
        .t-avatar { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#1f9d55,#2ecc71); display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:800; color:white; cursor:pointer; }

        .chat-container { flex:1; display:flex; overflow:hidden; }

        .chat-list { width:340px; flex-shrink:0; background:white; border-right:1px solid rgba(0,0,0,.08); display:flex; flex-direction:column; overflow:hidden; }
        .chat-list-header { padding:20px 24px 16px; border-bottom:1px solid rgba(0,0,0,.06); }
        .chat-list-header h3 { font-family:'Syne',sans-serif; font-size:17px; font-weight:800; margin-bottom:10px; }
        .chat-search { width:100%; padding:10px 16px; border:1px solid rgba(0,0,0,.1); border-radius:12px; font-size:13px; font-family:'DM Sans',sans-serif; outline:none; transition:border .2s; background:#f8fafc; }
        .chat-search:focus { border-color:#2ecc71; background:white; }
        .chat-list-body { flex:1; overflow-y:auto; }
        .conv-empty { padding:40px 24px; text-align:center; color:#aaa; font-size:13px; }
        .conv-empty .ce-icon { font-size:48px; margin-bottom:12px; display:block; }

        .conv-item { display:flex; align-items:center; gap:14px; padding:16px 24px; border-bottom:1px solid rgba(0,0,0,.04); cursor:pointer; transition:background .15s; position:relative; }
        .conv-item:hover { background:#f8fafc; }
        .conv-item.active { background:#edfaf3; border-left:3px solid #1f9d55; }
        .conv-avatar { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; font-size:17px; font-weight:800; color:white; flex-shrink:0; }
        .conv-avatar.empresa { background:linear-gradient(135deg,#1f9d55,#2ecc71); }
        .conv-avatar.artista { background:linear-gradient(135deg,#d4a017,#f0c040); }
        .conv-info { flex:1; overflow:hidden; }
        .conv-name { font-size:14px; font-weight:700; margin-bottom:2px; display:flex; align-items:center; gap:6px; }
        .conv-tipo-badge { font-size:10px; font-weight:600; padding:1px 6px; border-radius:8px; background:#edfaf3; color:#1f9d55; }
        .conv-preview { font-size:12px; color:#888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .conv-preview.yo::before { content:'Tú: '; font-weight:600; color:#aaa; }
        .conv-meta { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
        .conv-time { font-size:10px; color:#bbb; white-space:nowrap; }
        .conv-unread { background:#e74c3c; color:white; font-size:10px; font-weight:800; padding:2px 7px; border-radius:10px; min-width:18px; text-align:center; }

        .chat-area { flex:1; display:flex; flex-direction:column; background:#f0f4f8; }
        .chat-area-empty { flex:1; display:flex; align-items:center; justify-content:center; flex-direction:column; color:#aaa; gap:12px; }
        .chat-area-empty .cae-icon { font-size:64px; }
        .chat-area-empty h3 { font-family:'Syne',sans-serif; font-size:20px; color:#666; }
        .chat-area-empty p { font-size:13px; max-width:300px; text-align:center; line-height:1.6; }

        .chat-header { padding:16px 28px; background:white; border-bottom:1px solid rgba(0,0,0,.07); display:flex; align-items:center; gap:14px; box-shadow:0 1px 4px rgba(0,0,0,.03); }
        .chat-header .ch-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:800; color:white; flex-shrink:0; }
        .chat-header .ch-info h4 { font-size:15px; font-weight:700; }
        .chat-header .ch-info p { font-size:12px; color:#888; }
        .chat-header .ch-back { display:none; background:none; border:none; font-size:22px; cursor:pointer; padding:8px; margin-right:4px; }

        .chat-messages { flex:1; overflow-y:auto; padding:24px 28px; display:flex; flex-direction:column; gap:8px; }
        .msg-date-sep { text-align:center; font-size:11px; color:#aaa; padding:12px 0 6px; font-weight:600; }
        .msg-bubble { max-width:70%; padding:12px 18px; border-radius:20px; font-size:14px; line-height:1.55; position:relative; word-wrap:break-word; animation:msgIn .25s ease both; }
        @keyframes msgIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
        .msg-bubble.sent { align-self:flex-end; background:linear-gradient(135deg,#1f9d55,#2ecc71); color:white; border-bottom-right-radius:6px; }
        .msg-bubble.received { align-self:flex-start; background:white; color:#222; border-bottom-left-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.06); }
        .msg-time { font-size:10px; margin-top:4px; display:block; }
        .msg-bubble.sent .msg-time { color:rgba(255,255,255,.65); text-align:right; }
        .msg-bubble.received .msg-time { color:#bbb; }

        .chat-input-area { padding:16px 28px; background:white; border-top:1px solid rgba(0,0,0,.07); display:flex; align-items:center; gap:12px; }
        .chat-input { flex:1; padding:13px 20px; border:1px solid rgba(0,0,0,.1); border-radius:25px; font-size:14px; font-family:'DM Sans',sans-serif; outline:none; transition:border .2s; background:#f8fafc; }
        .chat-input:focus { border-color:#2ecc71; background:white; }
        .chat-send { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,#1f9d55,#2ecc71); border:none; color:white; font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:transform .2s,box-shadow .2s; box-shadow:0 4px 12px rgba(31,157,85,.4); flex-shrink:0; }
        .chat-send:hover { transform:scale(1.08); }
        .chat-send:disabled { opacity:.5; cursor:not-allowed; transform:none; }

        .new-chat-btn { display:flex; align-items:center; gap:8px; padding:10px 18px; background:linear-gradient(135deg,#1f9d55,#2ecc71); color:white; border:none; border-radius:12px; font-size:13px; font-weight:700; cursor:pointer; font-family:'DM Sans',sans-serif; transition:transform .2s; box-shadow:0 3px 10px rgba(31,157,85,.35); margin-top:10px; width:100%; justify-content:center; }
        .new-chat-btn:hover { transform:translateY(-1px); }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:500; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
        .modal-overlay.open { display:flex; }
        .modal-box { background:white; border-radius:24px; max-width:480px; width:100%; padding:32px; box-shadow:0 30px 80px rgba(0,0,0,.2); animation:fadeUp .3s ease both; max-height:80vh; overflow-y:auto; position:relative; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .modal-box h3 { font-family:'Syne',sans-serif; font-size:20px; font-weight:800; margin-bottom:6px; }
        .modal-box .modal-sub { color:#888; font-size:13px; margin-bottom:16px; }
        .modal-close { position:absolute; top:18px; right:20px; background:none; border:none; font-size:22px; cursor:pointer; color:#aaa; }
        .user-search-input { width:100%; padding:12px 16px; border:1px solid rgba(0,0,0,.1); border-radius:14px; font-size:14px; font-family:'DM Sans',sans-serif; outline:none; margin-bottom:12px; }
        .user-search-input:focus { border-color:#2ecc71; }
        .user-list { max-height:300px; overflow-y:auto; }
        .user-item { display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:12px; cursor:pointer; transition:background .15s; }
        .user-item:hover { background:#edfaf3; }
        .user-item .ui-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:800; color:white; flex-shrink:0; }
        .user-item .ui-info h4 { font-size:14px; font-weight:600; }
        .user-item .ui-info p { font-size:12px; color:#888; }

        @media(max-width:900px) {
            .sidebar { width:70px; }
            .sidebar-brand span, .s-user-info, .nav-item span:not(.ni), .nav-section-title, .btn-logout-s span { display:none; }
            .sidebar-brand { justify-content:center; padding:20px 0; }
            .nav-item { justify-content:center; padding:14px; }
            .sidebar-bottom { padding:12px 0; }
            .btn-logout-s { justify-content:center; padding:14px; }
            .chat-list { width:280px; }
        }
        @media(max-width:768px) {
            .chat-list { width:100%; }
            .chat-area { display:none; }
            .chat-area.mobile-show { display:flex; position:fixed; inset:0; z-index:200; }
            .chat-list.mobile-hide { display:none; }
            .chat-header .ch-back { display:block; }
            .topbar { padding:0 20px; }
        }
        @media(max-width:640px) {
            .sidebar { position:fixed; bottom:0; left:0; width:100%; height:60px; flex-direction:row; min-height:unset; border-radius:20px 20px 0 0; top:auto; }
            .sidebar-brand, .sidebar-user, .sidebar-bottom { display:none; }
            .sidebar-nav { display:flex; flex-direction:row; justify-content:space-around; align-items:center; padding:0; }
            .nav-item { flex-direction:column; font-size:10px; padding:8px 10px; gap:3px; border-radius:8px; }
            .nav-item span:not(.ni) { display:block; font-size:9px; }
            .main { padding-bottom:60px; }
            .topbar { padding:0 14px; height:58px; }
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
        <a href="chat.php" class="nav-item active"><span class="ni">💬</span><span>Mensajes</span><?php if($totalNoLeidos > 0): ?><span class="nav-badge"><?= $totalNoLeidos ?></span><?php endif; ?></a>
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
                    <input type="text" class="chat-input" id="chatInput" placeholder="Escribe un mensaje..." maxlength="2000" autocomplete="off">
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
        <input type="text" class="user-search-input" id="userSearchInput" placeholder="Escribe un nombre..." oninput="buscarUsuarios()">
        <div class="user-list" id="userSearchResults">
            <div style="padding:20px; text-align:center; color:#aaa; font-size:13px;">Escribe para buscar usuarios</div>
        </div>
    </div>
</div>

<script>
const YO = <?= (int)$usuario['id'] ?>;
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
    document.getElementById('modalNuevo').addEventListener('click', e => { if(e.target === e.currentTarget) cerrarNuevoChat(); });
    document.addEventListener('keydown', e => { if(e.key === 'Escape') cerrarNuevoChat(); });
});

async function cargarConversaciones() {
    try {
        const res = await fetch(API('action=conversaciones'));
        const data = await res.json();
        if (!data.ok) return;
        conversaciones = data.conversaciones;
        renderConversaciones();
    } catch(e) { console.error('Error cargando conversaciones', e); }
}

function renderConversaciones() {
    const body = document.getElementById('convListBody');
    const empty = document.getElementById('convEmpty');
    const search = document.getElementById('searchConv').value.toLowerCase();
    const filtered = conversaciones.filter(c => (c.nombre + ' ' + (c.apellido||'')).toLowerCase().includes(search));

    if (!filtered.length) {
        body.innerHTML = '';
        empty.style.display = 'block';
        body.appendChild(empty);
        return;
    }
    empty.style.display = 'none';
    body.innerHTML = filtered.map(c => {
        const nombre = c.nombre + ' ' + (c.apellido||'');
        const ini = c.nombre.charAt(0).toUpperCase();
        const avatarClass = c.tipo === 'empresa' ? 'empresa' : c.tipo === 'artista' ? 'artista' : '';
        const previewClass = c.yo_envie_ultimo ? 'yo' : '';
        const preview = c.ultimo_mensaje ? (c.ultimo_mensaje.length > 40 ? c.ultimo_mensaje.substring(0,40)+'...' : c.ultimo_mensaje) : 'Sin mensajes';
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
    if (window.innerWidth <= 768) {
        document.getElementById('chatList').classList.add('mobile-hide');
        document.getElementById('chatArea').classList.add('mobile-show');
    }
    await cargarMensajes(userId);
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(() => cargarMensajes(userId, true), 5000);
    document.getElementById('chatInput').focus();
}

async function cargarMensajes(userId, silent = false) {
    try {
        const res = await fetch(API(`action=mensajes&con=${userId}`));
        const data = await res.json();
        if (!data.ok) return;
        if (data.usuario) {
            document.getElementById('chNombre').textContent = data.usuario.nombre + ' ' + (data.usuario.apellido||'');
            document.getElementById('chTipo').textContent = data.usuario.tipo === 'empresa' ? '🏢 Empresa' : data.usuario.tipo === 'artista' ? '🎵 Artista' : '👤 Candidato';
            const av = document.getElementById('chAvatar');
            av.textContent = data.usuario.nombre.charAt(0).toUpperCase();
            av.style.background = data.usuario.tipo === 'empresa' ? 'linear-gradient(135deg,#1f9d55,#2ecc71)' : data.usuario.tipo === 'artista' ? 'linear-gradient(135deg,#d4a017,#f0c040)' : 'linear-gradient(135deg,#667eea,#764ba2)';
        }
        renderMensajes(data.mensajes);
        if (!silent) cargarConversaciones();
    } catch(e) { console.error(e); }
}

function renderMensajes(mensajes) {
    const container = document.getElementById('chatMessages');
    let html = '', lastDate = '';
    mensajes.forEach(m => {
        const fecha = m.creado_en.split(' ')[0];
        if (fecha !== lastDate) { lastDate = fecha; html += `<div class="msg-date-sep">${formatFechaLarga(fecha)}</div>`; }
        const tipo = parseInt(m.de_usuario) === YO ? 'sent' : 'received';
        const hora = m.creado_en.split(' ')[1].substring(0,5);
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
        const res = await fetch(API('action=enviar'), { method:'POST', body:data });
        const json = await res.json();
        if (json.ok) {
            const container = document.getElementById('chatMessages');
            const hora = new Date().toTimeString().substring(0,5);
            container.innerHTML += `<div class="msg-bubble sent">${esc(msg)}<span class="msg-time">${hora}</span></div>`;
            container.scrollTop = container.scrollHeight;
            cargarConversaciones();
        } else { alert(json.msg || 'Error al enviar'); input.value = msg; }
    } catch(e) { alert('Error de conexión'); input.value = msg; }
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
                        <h4>${esc(u.nombre + ' ' + (u.apellido||''))}</h4>
                        <p>${u.tipo === 'empresa' ? '🏢 Empresa' : u.tipo === 'artista' ? '🎵 Artista' : '👤 Candidato'}</p>
                    </div>
                </div>`).join('');
        } catch(e) { console.error(e); }
    }, 300);
}

function seleccionarUsuario(id) { cerrarNuevoChat(); abrirChat(id); }

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
    const d = new Date(fechaStr.replace(' ','T'));
    const hoy = new Date();
    const diff = Math.floor((hoy - d) / 86400000);
    if (diff === 0) return d.toTimeString().substring(0,5);
    if (diff === 1) return 'Ayer';
    if (diff < 7) return ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'][d.getDay()];
    return `${d.getDate()}/${d.getMonth()+1}`;
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
</body>
</html>
