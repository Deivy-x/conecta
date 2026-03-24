<?php
// ============================================================
// busqueda_talentos.php — Búsqueda pública de perfiles
// QuibdóConecta — muestra todo lo que el talento subió
// ============================================================
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");

require_once __DIR__ . '/Php/db.php';
if (file_exists(__DIR__ . '/Php/badges_helper.php')) require_once __DIR__ . '/Php/badges_helper.php';

if (!function_exists('getBadgesUsuario')) {
    function getBadgesUsuario($db, $id) { return []; }
    function renderBadges($b) { return ''; }
    function tieneBadge($b, $n) { return false; }
}

try { $db = getDB(); } catch (Exception $e) { $db = null; }

// ── Parámetros de búsqueda ───────────────────────────────────
$q        = trim($_GET['q'] ?? '');
$ciudad   = trim($_GET['ciudad'] ?? '');
$tipo     = trim($_GET['tipo'] ?? '');   // talento|empresa|negocio
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

// ── Perfil individual ─────────────────────────────────────────
$verPerfil = (int)($_GET['ver'] ?? 0);
$perfilData  = null;
$perfilTp    = null;
$perfilGal   = [];
$perfilEp    = null;
$perfilNp    = null;
$perfilEdu   = [];
$perfilCert  = [];
$perfilAptBland   = '';
$perfilAptIdiomas = '';

if ($verPerfil && $db) {
    try {
        $s = $db->prepare("SELECT * FROM usuarios WHERE id=? AND activo=1");
        $s->execute([$verPerfil]);
        $perfilData = $s->fetch();
    } catch(Exception $e) { $perfilData = null; }

    if ($perfilData) {
        // talento_perfil
        try {
            $s = $db->prepare("SELECT * FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
            $s->execute([$verPerfil]); $perfilTp = $s->fetch() ?: null;
        } catch(Exception $e) { $perfilTp = null; }

        // empresa
        try {
            $s = $db->prepare("SELECT * FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
            $s->execute([$verPerfil]); $perfilEp = $s->fetch() ?: null;
        } catch(Exception $e) { $perfilEp = null; }

        // negocio
        try {
            $s = $db->prepare("SELECT * FROM negocios_locales WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
            $s->execute([$verPerfil]); $perfilNp = $s->fetch() ?: null;
        } catch(Exception $e) { $perfilNp = null; }

        // galería
        try {
            $s = $db->prepare("SELECT * FROM talento_galeria WHERE usuario_id=? AND activo=1 ORDER BY orden ASC, id ASC LIMIT 30");
            $s->execute([$verPerfil]); $perfilGal = $s->fetchAll();
        } catch(Exception $e) { $perfilGal = []; }

        // educación
        try {
            $s = $db->prepare("SELECT * FROM talento_educacion WHERE usuario_id=? ORDER BY orden ASC, id ASC");
            $s->execute([$verPerfil]); $perfilEdu = $s->fetchAll();
        } catch(Exception $e) { $perfilEdu = []; }

        // certificaciones
        try {
            $s = $db->prepare("SELECT * FROM talento_certificaciones WHERE usuario_id=? ORDER BY orden ASC, id ASC");
            $s->execute([$verPerfil]); $perfilCert = $s->fetchAll();
        } catch(Exception $e) { $perfilCert = []; }

        // aptitudes extra (bland + idiomas desde talento_perfil)
        try {
            $s = $db->prepare("SELECT aptitudes_bland, aptitudes_idiomas FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
            $s->execute([$verPerfil]);
            $aptRow = $s->fetch();
            $perfilAptBland   = $aptRow['aptitudes_bland']   ?? '';
            $perfilAptIdiomas = $aptRow['aptitudes_idiomas'] ?? '';
        } catch(Exception $e) { $perfilAptBland = ''; $perfilAptIdiomas = ''; }

        // vistas
        $visitorId = $_SESSION['usuario_id'] ?? null;
        if ($visitorId != $verPerfil) {
            try {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $ck = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=? AND ip=? AND creado_en>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");
                $ck->execute([$verPerfil, $ip]);
                if ((int)$ck->fetchColumn() === 0) {
                    $db->prepare("INSERT INTO perfil_vistas (usuario_id,visitante_id,ip,seccion) VALUES (?,?,?,?)")
                       ->execute([$verPerfil, $visitorId, $ip, 'busqueda']);
                }
            } catch(Exception $e) {}
        }
    }
}

// ── Resultados de búsqueda ────────────────────────────────────
$perfiles = [];
$totalPerfiles = 0;

if (!$verPerfil && $db) {
    $where  = ["u.activo = 1"];
    $params = [];

    if ($q !== '') {
        $where[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR tp.profesion LIKE ? OR tp.skills LIKE ? OR ep.nombre_empresa LIKE ? OR ep.sector LIKE ? OR np.nombre_negocio LIKE ? OR np.categoria LIKE ?)";
        $like = "%$q%";
        $params = array_merge($params, [$like,$like,$like,$like,$like,$like,$like,$like]);
    }
    if ($ciudad !== '') {
        $where[] = "u.ciudad LIKE ?";
        $params[] = "%$ciudad%";
    }
    if ($tipo !== '') {
        $where[] = "u.tipo = ?";
        $params[] = $tipo;
    }

    $whereSQL = implode(' AND ', $where);

    try {
        $countSQL = "SELECT COUNT(DISTINCT u.id) FROM usuarios u
            LEFT JOIN talento_perfil tp ON tp.id = (SELECT MAX(id) FROM talento_perfil WHERE usuario_id=u.id)
            LEFT JOIN perfiles_empresa ep ON ep.id = (SELECT MAX(id) FROM perfiles_empresa WHERE usuario_id=u.id)
            LEFT JOIN negocios_locales np ON np.id = (SELECT MAX(id) FROM negocios_locales WHERE usuario_id=u.id)
            WHERE $whereSQL";
        $cStmt = $db->prepare($countSQL);
        $cStmt->execute($params);
        $totalPerfiles = (int)$cStmt->fetchColumn();
    } catch(Exception $e) { $totalPerfiles = 0; }

    try {
        $sql = "SELECT DISTINCT u.id, u.nombre, u.apellido, u.tipo, u.ciudad, u.foto, u.verificado,
                    tp.profesion, tp.skills, tp.bio, tp.avatar_color, tp.precio_desde, tp.tipo_servicio,
                    ep.nombre_empresa, ep.sector, ep.logo AS logo_empresa,
                    np.nombre_negocio, np.categoria, np.logo AS logo_negocio,
                    (SELECT COUNT(*) FROM perfil_vistas pv WHERE pv.usuario_id=u.id) AS vistas
                FROM usuarios u
                LEFT JOIN talento_perfil tp ON tp.id = (SELECT MAX(id) FROM talento_perfil WHERE usuario_id=u.id)
                LEFT JOIN perfiles_empresa ep ON ep.id = (SELECT MAX(id) FROM perfiles_empresa WHERE usuario_id=u.id)
                LEFT JOIN negocios_locales np ON np.id = (SELECT MAX(id) FROM negocios_locales WHERE usuario_id=u.id)
                WHERE $whereSQL
                ORDER BY u.verificado DESC, u.creado_en DESC
                LIMIT $perPage OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $perfiles = $stmt->fetchAll();
    } catch(Exception $e) { $perfiles = []; }
}

$totalPages = $totalPerfiles > 0 ? ceil($totalPerfiles / $perPage) : 1;

function ytId($url) {
    preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',$url,$m);
    return $m[1] ?? '';
}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buscar Talentos — QuibdóConecta</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🌿</text></svg>">
<style>
  :root {
    --bg: #060e07;
    --bg2: #0d1f10;
    --bg3: #162418;
    --card: #111e13;
    --card2: #172118;
    --border: rgba(39,168,85,.18);
    --border2: rgba(39,168,85,.3);
    --green: #27a855;
    --green2: #2ecc71;
    --text: #e8f5e9;
    --muted: rgba(232,245,233,.5);
    --muted2: rgba(232,245,233,.3);
    --shadow: 0 4px 24px rgba(0,0,0,.5);
    --radius: 16px;
    --nav-h: 60px;
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Segoe UI', system-ui, sans-serif;
    min-height: 100vh;
  }

  /* ── NAV ── */
  .nav {
    position: sticky; top: 0; z-index: 100;
    background: rgba(6,14,7,.92);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    height: var(--nav-h);
    display: flex; align-items: center;
    padding: 0 24px; gap: 16px;
  }
  .nav-brand { font-weight: 800; font-size: 18px; color: var(--text); text-decoration: none; }
  .nav-brand span { color: var(--green2); }
  .nav-spacer { flex: 1; }
  .nav-btn {
    padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600;
    text-decoration: none; cursor: pointer; border: none; transition: .2s;
  }
  .nav-btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border2); }
  .nav-btn-ghost:hover { background: var(--bg3); color: var(--text); }
  .nav-btn-green { background: var(--green); color: #fff; }
  .nav-btn-green:hover { background: var(--green2); }

  /* ── HERO ── */
  .hero {
    background: linear-gradient(135deg, #0d2410 0%, #0a1f0d 60%, #060e07 100%);
    border-bottom: 1px solid var(--border);
    padding: 52px 24px 40px;
    text-align: center;
  }
  .hero h1 { font-size: clamp(24px,4vw,40px); font-weight: 800; line-height: 1.2; margin-bottom: 8px; }
  .hero h1 span { color: var(--green2); }
  .hero p { color: var(--muted); font-size: 15px; margin-bottom: 28px; }

  /* ── SEARCH BAR ── */
  .search-wrap {
    max-width: 780px; margin: 0 auto;
    display: flex; flex-wrap: wrap; gap: 8px;
  }
  .search-input {
    flex: 1; min-width: 200px;
    background: var(--card); border: 1px solid var(--border2);
    color: var(--text); border-radius: 12px;
    padding: 12px 16px; font-size: 15px;
    outline: none; transition: border .2s;
  }
  .search-input:focus { border-color: var(--green); }
  .search-input::placeholder { color: var(--muted2); }
  .search-select {
    background: var(--card); border: 1px solid var(--border2);
    color: var(--text); border-radius: 12px;
    padding: 12px 14px; font-size: 14px;
    outline: none; cursor: pointer;
  }
  .search-select option { background: #111; }
  .search-btn {
    background: var(--green); color: #fff;
    border: none; border-radius: 12px;
    padding: 12px 22px; font-size: 15px; font-weight: 700;
    cursor: pointer; transition: .2s;
  }
  .search-btn:hover { background: var(--green2); transform: translateY(-1px); }

  /* ── LAYOUT ── */
  .container { max-width: 1200px; margin: 0 auto; padding: 32px 20px; }

  .results-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; flex-wrap: wrap; gap: 10px;
  }
  .results-count { color: var(--muted); font-size: 14px; }
  .results-count strong { color: var(--text); }

  /* ── GRID ── */
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
  }

  /* ── CARD ── */
  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: transform .2s, border-color .2s, box-shadow .2s;
    cursor: pointer;
  }
  .card:hover {
    transform: translateY(-3px);
    border-color: var(--border2);
    box-shadow: 0 8px 32px rgba(39,168,85,.15);
  }
  .card-header {
    height: 80px;
    position: relative;
  }
  .card-avatar {
    position: absolute; bottom: -28px; left: 20px;
    width: 56px; height: 56px; border-radius: 50%;
    border: 3px solid var(--card);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; font-weight: 800; color: #fff;
    overflow: hidden; flex-shrink: 0;
  }
  .card-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .card-body { padding: 36px 20px 20px; }
  .card-name { font-size: 16px; font-weight: 700; margin-bottom: 2px; }
  .card-sub { font-size: 13px; color: var(--green2); margin-bottom: 4px; }
  .card-city { font-size: 12px; color: var(--muted); margin-bottom: 10px; }
  .card-bio {
    font-size: 13px; color: var(--muted); line-height: 1.5;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden; margin-bottom: 12px;
  }
  .card-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
  .tag {
    background: rgba(39,168,85,.12); color: var(--green2);
    border: 1px solid rgba(39,168,85,.25);
    border-radius: 20px; padding: 3px 10px; font-size: 11px; font-weight: 600;
  }
  .tag-city { background: rgba(255,255,255,.05); color: var(--muted); border-color: var(--border); }
  .card-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 20px; border-top: 1px solid var(--border);
  }
  .card-verif {
    display: flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 600;
    background: rgba(39,168,85,.12); color: var(--green2);
    padding: 4px 10px; border-radius: 20px;
    border: 1px solid rgba(39,168,85,.25);
  }
  .card-vistas { font-size: 11px; color: var(--muted2); }

  /* ── EMPTY ── */
  .empty {
    text-align: center; padding: 80px 20px;
    color: var(--muted);
  }
  .empty-icon { font-size: 56px; margin-bottom: 16px; }
  .empty h3 { font-size: 20px; color: var(--text); margin-bottom: 8px; }

  /* ── PAGINATION ── */
  .pagination {
    display: flex; gap: 8px; justify-content: center;
    margin-top: 36px; flex-wrap: wrap;
  }
  .page-btn {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 600; text-decoration: none;
    background: var(--card); border: 1px solid var(--border);
    color: var(--text); transition: .2s;
  }
  .page-btn:hover, .page-btn.active {
    background: var(--green); border-color: var(--green); color: #fff;
  }

  /* ── MODAL PERFIL ── */
  .modal-overlay {
    position: fixed; inset: 0; z-index: 200;
    background: rgba(0,0,0,.8); backdrop-filter: blur(6px);
    display: none; align-items: flex-start; justify-content: center;
    padding: 20px; overflow-y: auto;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--card); border: 1px solid var(--border2);
    border-radius: 20px; width: 100%; max-width: 860px;
    margin: auto; overflow: hidden;
    box-shadow: 0 24px 80px rgba(0,0,0,.6);
    animation: slideUp .3s ease;
  }
  @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } }
  .modal-close {
    position: absolute; top: 16px; right: 20px;
    background: rgba(0,0,0,.4); color: var(--text);
    border: none; border-radius: 50%; width: 36px; height: 36px;
    font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center;
    transition: .2s; z-index: 10;
  }
  .modal-close:hover { background: rgba(255,255,255,.15); }

  .modal-hero {
    height: 130px; position: relative;
  }
  .modal-hero-inner {
    position: absolute; bottom: -40px; left: 32px;
    display: flex; align-items: flex-end; gap: 16px;
  }
  .modal-avatar {
    width: 80px; height: 80px; border-radius: 50%;
    border: 4px solid var(--card);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; font-weight: 800; color: #fff;
    overflow: hidden; flex-shrink: 0; position: relative; z-index: 2;
  }
  .modal-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .modal-name-wrap { margin-bottom: 8px; }
  .modal-tipo-badge {
    font-size: 10px; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: var(--green2);
    display: block; margin-bottom: 2px;
  }
  .modal-name { font-size: 22px; font-weight: 800; line-height: 1.1; }
  .modal-sub { font-size: 14px; color: var(--green2); }

  .modal-body { padding: 60px 32px 32px; }

  .modal-stats {
    display: flex; gap: 0; border: 1px solid var(--border);
    border-radius: 12px; overflow: hidden; margin-bottom: 28px;
  }
  .modal-stat {
    flex: 1; text-align: center; padding: 14px 10px;
    border-right: 1px solid var(--border);
  }
  .modal-stat:last-child { border-right: none; }
  .modal-stat-val { font-size: 22px; font-weight: 800; color: var(--text); display: block; }
  .modal-stat-lbl { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }

  .section { margin-bottom: 28px; }
  .section-title {
    font-size: 13px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .08em; color: var(--green2);
    margin-bottom: 12px; display: flex; align-items: center; gap: 6px;
  }
  .section-title::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
  }

  .bio-text { font-size: 14px; color: var(--muted); line-height: 1.7; }

  .skills-wrap { display: flex; flex-wrap: wrap; gap: 8px; }
  .skill-tag {
    background: rgba(39,168,85,.1); border: 1px solid rgba(39,168,85,.25);
    color: var(--green2); border-radius: 20px; padding: 5px 14px;
    font-size: 13px; font-weight: 600;
  }

  .galeria-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
  }
  .gal-item {
    aspect-ratio: 1; border-radius: 10px; overflow: hidden;
    background: var(--bg3); cursor: pointer;
    border: 1px solid var(--border); transition: .2s;
    position: relative;
  }
  .gal-item:hover { border-color: var(--green); transform: scale(1.02); }
  .gal-item img, .gal-item video {
    width: 100%; height: 100%; object-fit: cover; display: block;
  }
  .gal-overlay {
    position: absolute; inset: 0; background: rgba(0,0,0,.5);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: .2s; font-size: 24px;
  }
  .gal-item:hover .gal-overlay { opacity: 1; }

  .info-table { width: 100%; border-collapse: collapse; }
  .info-table tr { border-bottom: 1px solid var(--border); }
  .info-table tr:last-child { border-bottom: none; }
  .info-table td { padding: 10px 0; font-size: 14px; }
  .info-table td:first-child { color: var(--muted); width: 40%; }
  .info-table td:last-child { font-weight: 600; }

  /* educacion/cert — tarjetas del perfil */
  .edu-card, .cert-card {
    background: var(--bg3); border: 1px solid var(--border);
    border-radius: 10px; padding: 14px 16px; margin-bottom: 8px;
  }
  .edu-card-title { font-size: 14px; font-weight: 700; }
  .edu-card-inst { font-size: 13px; color: var(--green2); margin-bottom: 4px; }
  .edu-card-fecha { font-size: 12px; color: var(--muted); }
  .cert-nom { font-size: 14px; font-weight: 700; }
  .cert-org { font-size: 12px; color: var(--green2); margin: 2px 0; }
  .cert-link {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; color: var(--muted); text-decoration: none; margin-top: 6px;
    padding: 4px 10px; border: 1px solid var(--border); border-radius: 8px;
    transition: .2s;
  }
  .cert-link:hover { border-color: var(--green); color: var(--green2); }

  .cta-bar {
    display: flex; gap: 10px; flex-wrap: wrap;
    padding-top: 12px; border-top: 1px solid var(--border); margin-top: 24px;
  }
  .btn-msg {
    flex: 1; min-width: 140px; padding: 12px 20px;
    background: var(--green); color: #fff; border: none; border-radius: 12px;
    font-size: 15px; font-weight: 700; cursor: pointer; transition: .2s;
    text-align: center; text-decoration: none;
  }
  .btn-msg:hover { background: var(--green2); transform: translateY(-1px); }
  .btn-outline {
    flex: 1; min-width: 140px; padding: 12px 20px;
    background: transparent; color: var(--text);
    border: 1px solid var(--border2); border-radius: 12px;
    font-size: 15px; font-weight: 600; cursor: pointer; transition: .2s;
    text-align: center; text-decoration: none;
  }
  .btn-outline:hover { background: var(--bg3); border-color: var(--green); }

  /* lightbox */
  #lbox {
    display: none; position: fixed; inset: 0; z-index: 999;
    background: rgba(0,0,0,.92); align-items: center; justify-content: center;
  }
  #lbox.open { display: flex; }
  #lbox img { max-width: 90vw; max-height: 90vh; border-radius: 10px; }
  #lbox-close {
    position: absolute; top: 20px; right: 24px;
    color: #fff; font-size: 32px; cursor: pointer; font-weight: 300; line-height: 1;
    background: none; border: none;
  }

  /* Responsive */
  @media (max-width: 600px) {
    .modal-body { padding: 52px 20px 24px; }
    .modal-hero-inner { left: 20px; }
    .grid { grid-template-columns: 1fr; }
    .galeria-grid { grid-template-columns: repeat(3, 1fr); }
  }

  .no-db {
    text-align: center; padding: 60px 20px; color: var(--muted);
  }
  .spinner {
    width: 40px; height: 40px; border: 3px solid var(--border);
    border-top-color: var(--green); border-radius: 50%;
    animation: spin .8s linear infinite; margin: 60px auto;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .verif-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(39,168,85,.12); color: var(--green2);
    border: 1px solid rgba(39,168,85,.3); border-radius: 20px;
    padding: 3px 10px; font-size: 11px; font-weight: 700;
  }
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <a class="nav-brand" href="index.html">Quibdó<span>Conecta</span></a>
  <span class="nav-spacer"></span>
  <a href="index.html" class="nav-btn nav-btn-ghost">← Volver</a>
  <?php if (!empty($_SESSION['usuario_id'])): ?>
    <a href="dashboard.php" class="nav-btn nav-btn-green">Mi panel</a>
  <?php else: ?>
    <a href="inicio_sesion.php" class="nav-btn nav-btn-green">Iniciar sesión</a>
  <?php endif; ?>
</nav>

<!-- HERO -->
<div class="hero">
  <h1>Descubre el <span>talento</span> de Quibdó</h1>
  <p>Busca profesionales, empresas y negocios locales de toda la región</p>
  <form method="GET" action="busqueda_talentos.php" class="search-wrap">
    <input class="search-input" type="text" name="q" placeholder="🔍  Nombre, habilidad, profesión…" value="<?= htmlspecialchars($q) ?>">
    <input class="search-input" style="max-width:180px" type="text" name="ciudad" placeholder="📍 Ciudad" value="<?= htmlspecialchars($ciudad) ?>">
    <select class="search-select" name="tipo">
      <option value="">Todos</option>
      <option value="talento" <?= $tipo==='talento'?'selected':'' ?>>👤 Talento</option>
      <option value="empresa" <?= $tipo==='empresa'?'selected':'' ?>>🏢 Empresa</option>
      <option value="negocio" <?= $tipo==='negocio'?'selected':'' ?>>🏪 Negocio</option>
      <option value="candidato" <?= $tipo==='candidato'?'selected':'' ?>>🎓 Candidato</option>
    </select>
    <button class="search-btn" type="submit">Buscar</button>
  </form>
</div>

<!-- MAIN -->
<div class="container">

<?php if (!$db): ?>
  <div class="no-db">
    <div style="font-size:48px;margin-bottom:12px">⚠️</div>
    <h3>Sin conexión a base de datos</h3>
    <p>No se pudo conectar. Intenta más tarde.</p>
  </div>

<?php elseif ($verPerfil && $perfilData): ?>
  <!-- ─── PERFIL INDIVIDUAL INLINE ─── -->
  <?php
    $tipo_u = $perfilData['tipo'] ?? 'candidato';
    $nombre = htmlspecialchars(trim($perfilData['nombre'].' '.($perfilData['apellido']??'')));
    $inicial = strtoupper(mb_substr($perfilData['nombre'],0,1).mb_substr($perfilData['apellido']??'',0,1));
    $fotoUrl = !empty($perfilData['foto']) ? 'uploads/fotos/'.htmlspecialchars($perfilData['foto']) : '';
    $ciudad_u = htmlspecialchars($perfilData['ciudad'] ?? 'Quibdó');

    $subtitulo = ''; $descripcion = ''; $colorGrad = 'linear-gradient(135deg,#1f9d55,#2ecc71)';
    $skills = []; $precio = ''; $tipoServ = '';
    $sitioWeb = ''; $telefono = ''; $whatsapp = '';

    if ($tipo_u === 'empresa' && $perfilEp) {
        $titulo2 = $perfilEp['nombre_empresa'] ?: $nombre;
        $subtitulo = htmlspecialchars($perfilEp['sector'] ?? '');
        $descripcion = htmlspecialchars($perfilEp['descripcion'] ?? '');
        $colorGrad = $perfilEp['avatar_color'] ?? 'linear-gradient(135deg,#1a56db,#3b82f6)';
        $logoDisp = !empty($perfilEp['logo']) ? 'uploads/logos/'.htmlspecialchars($perfilEp['logo']) : $fotoUrl;
        $sitioWeb = htmlspecialchars($perfilEp['sitio_web'] ?? '');
        $telefono = htmlspecialchars($perfilEp['telefono_empresa'] ?? '');
        $tipoLabel2 = '🏢 Empresa';
    } elseif ($tipo_u === 'negocio' && $perfilNp) {
        $titulo2 = $perfilNp['nombre_negocio'] ?: $nombre;
        $subtitulo = htmlspecialchars($perfilNp['categoria'] ?? '');
        $descripcion = htmlspecialchars($perfilNp['descripcion'] ?? '');
        $colorGrad = $perfilNp['avatar_color'] ?? 'linear-gradient(135deg,#d4a017,#f0c040)';
        $logoDisp = !empty($perfilNp['logo']) ? 'uploads/logos/'.htmlspecialchars($perfilNp['logo']) : $fotoUrl;
        $whatsapp = preg_replace('/\D/', '', $perfilNp['whatsapp'] ?? '');
        $tipoLabel2 = '🏪 Negocio local';
    } else {
        $titulo2 = $nombre;
        $subtitulo = htmlspecialchars($perfilTp['profesion'] ?? '');
        $descripcion = htmlspecialchars($perfilTp['bio'] ?? '');
        $colorGrad = $perfilTp['avatar_color'] ?? 'linear-gradient(135deg,#1f9d55,#2ecc71)';
        $logoDisp = $fotoUrl;
        $skills = $perfilTp ? array_filter(array_map('trim', explode(',', $perfilTp['skills'] ?? ''))) : [];
        $precio = $perfilTp && $perfilTp['precio_desde'] ? number_format((float)$perfilTp['precio_desde'], 0, ',', '.') : '';
        $tipoServ = htmlspecialchars($perfilTp['tipo_servicio'] ?? '');
        $tipoLabel2 = $tipoServ ? '🎧 Servicios & Eventos' : '👤 Talento profesional';
    }
    $titulo2 = htmlspecialchars($titulo2 ?? $nombre);
    $vistasTotal = 0;
    try { $vt = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=?"); $vt->execute([$verPerfil]); $vistasTotal = (int)$vt->fetchColumn(); } catch(Exception $e) {}
    $habilidades = count($skills);
    $tieneVerif = !empty($perfilData['verificado']);
  ?>
  <div style="margin-bottom:16px">
    <a href="busqueda_talentos.php?<?= http_build_query(['q'=>$q,'ciudad'=>$ciudad,'tipo'=>$tipo]) ?>" style="color:var(--muted);text-decoration:none;font-size:14px;">← Volver a resultados</a>
  </div>
  <div class="modal" style="max-width:860px;margin:0 auto;position:relative;">
    <!-- hero color -->
    <div class="modal-hero" style="background:<?= $colorGrad ?>; position:relative;">
      <div class="modal-hero-inner">
        <div class="modal-avatar" style="background:<?= $colorGrad ?>">
          <?php if ($logoDisp): ?>
            <img src="<?= $logoDisp ?>" alt="foto" onerror="this.style.display='none'">
          <?php else: ?>
            <?= $inicial ?>
          <?php endif; ?>
        </div>
        <div class="modal-name-wrap">
          <span class="modal-tipo-badge"><?= $tipoLabel2 ?></span>
          <div class="modal-name"><?= $titulo2 ?></div>
          <?php if ($subtitulo): ?><div class="modal-sub"><?= $subtitulo ?></div><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="modal-body">
      <!-- stats -->
      <div class="modal-stats">
        <div class="modal-stat">
          <span class="modal-stat-val"><?= $vistasTotal ?></span>
          <span class="modal-stat-lbl">👁 Vistas</span>
        </div>
        <div class="modal-stat">
          <span class="modal-stat-val"><?= count($perfilGal) ?></span>
          <span class="modal-stat-lbl">🖼 Galería</span>
        </div>
        <div class="modal-stat">
          <span class="modal-stat-val"><?= $habilidades ?: '—' ?></span>
          <span class="modal-stat-lbl">⚡ Habilidades</span>
        </div>
        <div class="modal-stat">
          <span class="modal-stat-val"><?= $tieneVerif ? '✅' : '—' ?></span>
          <span class="modal-stat-lbl">Verificado</span>
        </div>
      </div>

      <!-- info rápida -->
      <div class="section">
        <div class="section-title">📋 Información</div>
        <table class="info-table">
          <tr><td>📍 Ciudad</td><td><?= $ciudad_u ?></td></tr>
          <tr><td>🏷 Tipo</td><td><?= $tipoLabel2 ?></td></tr>
          <?php if ($tieneVerif): ?>
          <tr><td>✅ Estado</td><td><span class="verif-badge">✅ Verificado</span></td></tr>
          <?php endif; ?>
          <?php if ($precio): ?>
          <tr><td>💰 Precio desde</td><td>$<?= $precio ?> COP</td></tr>
          <?php endif; ?>
          <?php if ($sitioWeb): ?>
          <tr><td>🌐 Sitio web</td><td><a href="<?= $sitioWeb ?>" target="_blank" style="color:var(--green2)"><?= $sitioWeb ?></a></td></tr>
          <?php endif; ?>
          <?php if ($telefono): ?>
          <tr><td>📞 Teléfono</td><td><?= $telefono ?></td></tr>
          <?php endif; ?>
          <?php if ($whatsapp): ?>
          <tr><td>💬 WhatsApp</td><td><a href="https://wa.me/57<?= $whatsapp ?>" target="_blank" style="color:var(--green2)">+57 <?= $whatsapp ?></a></td></tr>
          <?php endif; ?>
        </table>
      </div>

      <!-- bio -->
      <?php if ($descripcion): ?>
      <div class="section">
        <div class="section-title">💬 Sobre mí</div>
        <p class="bio-text"><?= nl2br($descripcion) ?></p>
      </div>
      <?php endif; ?>

      <!-- habilidades DB -->
      <?php if (!empty($skills)): ?>
      <div class="section">
        <div class="section-title">⚡ Habilidades</div>
        <div class="skills-wrap">
          <?php foreach($skills as $sk): ?>
            <span class="skill-tag"><?= htmlspecialchars($sk) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- galería -->
      <?php if (!empty($perfilGal)): ?>
      <div class="section">
        <div class="section-title">🖼 Galería</div>
        <div class="galeria-grid">
          <?php foreach($perfilGal as $gi):
            $isVid = $gi['tipo'] === 'video';
            $yid = $gi['url_video'] ? ytId($gi['url_video']) : '';
            $thumb = $yid ? "https://img.youtube.com/vi/{$yid}/mqdefault.jpg"
                         : ($gi['archivo'] ? 'uploads/galeria/'.htmlspecialchars($gi['archivo']) : '');
            $titulo_g = htmlspecialchars($gi['titulo'] ?? '');
          ?>
          <div class="gal-item" onclick="<?= $isVid && $gi['url_video'] ? "window.open('".htmlspecialchars($gi['url_video'])."','_blank')" : ($gi['archivo'] ? "abrirLbox('uploads/galeria/".htmlspecialchars($gi['archivo'])."','{$titulo_g}')" : '') ?>">
            <?php if ($thumb): ?>
              <img src="<?= $thumb ?>" alt="<?= $titulo_g ?>" loading="lazy">
            <?php elseif ($isVid && $gi['archivo']): ?>
              <video src="uploads/galeria/<?= htmlspecialchars($gi['archivo']) ?>" preload="none"></video>
            <?php endif; ?>
            <div class="gal-overlay"><?= $isVid ? '▶' : '🔍' ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- EDUCACIÓN desde MySQL -->
      <?php if (!empty($perfilEdu)): ?>
      <div class="section">
        <div class="section-title">🎓 Educación</div>
        <?php foreach($perfilEdu as $edu): ?>
        <div class="edu-card">
          <?php if (!empty($edu['logo_url'])): ?>
            <img src="<?= htmlspecialchars($edu['logo_url']) ?>" alt="logo" style="width:36px;height:36px;border-radius:8px;object-fit:cover;margin-bottom:6px;">
          <?php endif; ?>
          <div class="edu-card-title"><?= htmlspecialchars($edu['titulo']) ?></div>
          <div class="edu-card-inst"><?= htmlspecialchars($edu['institucion']) ?></div>
          <?php if ($edu['fecha_inicio'] || $edu['fecha_fin']): ?>
          <div class="edu-card-fecha">
            <?= htmlspecialchars($edu['fecha_inicio']) ?>
            <?php if ($edu['fecha_inicio'] && ($edu['fecha_fin'] || true)): ?> —
              <?= $edu['fecha_fin'] ? htmlspecialchars($edu['fecha_fin']) : 'Actualidad' ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- CERTIFICACIONES desde MySQL -->
      <?php if (!empty($perfilCert)): ?>
      <div class="section">
        <div class="section-title">📜 Certificados y Cursos</div>
        <?php foreach($perfilCert as $cert): ?>
        <div class="cert-card">
          <div class="cert-nom"><?= htmlspecialchars($cert['nombre']) ?></div>
          <div class="cert-org">
            <?= htmlspecialchars($cert['organizacion']) ?>
            <?php if ($cert['fecha_expedicion']): ?>
              · <?= htmlspecialchars($cert['fecha_expedicion']) ?>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
            <?php if (!empty($cert['url_credencial'])): ?>
              <a class="cert-link" href="<?= htmlspecialchars($cert['url_credencial']) ?>" target="_blank">🔗 Ver credencial</a>
            <?php endif; ?>
            <?php if (!empty($cert['archivo_url'])): ?>
              <a class="cert-link" href="<?= htmlspecialchars($cert['archivo_url']) ?>" target="_blank">
                📄 <?= htmlspecialchars($cert['archivo_nombre'] ?: 'Ver documento') ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- APTITUDES EXTRA desde MySQL -->
      <?php
        $aptBlandArr   = array_filter(array_map('trim', explode(',', $perfilAptBland)));
        $aptIdiomasArr = array_filter(array_map('trim', explode(',', $perfilAptIdiomas)));
        $todasApt      = array_merge($aptBlandArr, $aptIdiomasArr);
      ?>
      <?php if (!empty($todasApt)): ?>
      <div class="section">
        <div class="section-title">🌟 Aptitudes adicionales</div>
        <div class="skills-wrap">
          <?php foreach($todasApt as $apt): ?>
            <span class="skill-tag"><?= htmlspecialchars($apt) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- CTA -->
      <div class="cta-bar">
        <?php if (!empty($_SESSION['usuario_id']) && $_SESSION['usuario_id'] != $verPerfil): ?>
          <a class="btn-msg" href="chat.php?con=<?= $verPerfil ?>">💬 Enviar mensaje</a>
        <?php elseif (empty($_SESSION['usuario_id'])): ?>
          <a class="btn-msg" href="inicio_sesion.php">💬 Enviar mensaje</a>
        <?php endif; ?>
        <a class="btn-outline" href="perfil.php?id=<?= $verPerfil ?>&tipo=<?= urlencode($tipo_u) ?>">Ver perfil completo</a>
        <a class="btn-outline" href="busqueda_talentos.php?<?= http_build_query(['q'=>$q,'ciudad'=>$ciudad,'tipo'=>$tipo]) ?>">← Volver</a>
      </div>
    </div>
  </div>

  <script>
    function abrirLbox(src, titulo) {
      document.getElementById('lbox-img').src = src;
      document.getElementById('lbox').classList.add('open');
    }
  </script>

<?php else: ?>
  <!-- ─── LISTADO ─── -->
  <div class="results-header">
    <div class="results-count">
      <?php if ($q || $ciudad || $tipo): ?>
        <strong><?= number_format($totalPerfiles) ?></strong> resultado<?= $totalPerfiles !== 1 ? 's' : '' ?>
        <?php if ($q): ?> para "<strong><?= htmlspecialchars($q) ?></strong>"<?php endif; ?>
      <?php else: ?>
        <strong><?= number_format($totalPerfiles) ?></strong> perfiles registrados
      <?php endif; ?>
    </div>
    <?php if ($q || $ciudad || $tipo): ?>
      <a href="busqueda_talentos.php" style="color:var(--muted);font-size:13px;text-decoration:none;">✕ Limpiar filtros</a>
    <?php endif; ?>
  </div>

  <?php if (empty($perfiles)): ?>
    <div class="empty">
      <div class="empty-icon">🔍</div>
      <h3>Sin resultados</h3>
      <p>Intenta con otros términos o quita los filtros.</p>
    </div>
  <?php else: ?>
  <div class="grid">
    <?php foreach($perfiles as $p):
      $p_tipo  = $p['tipo'] ?? 'candidato';
      $p_nombre = htmlspecialchars(trim($p['nombre'].' '.($p['apellido']??'')));
      $p_inicial = strtoupper(mb_substr($p['nombre'],0,1).mb_substr($p['apellido']??'',0,1));
      $p_color = 'linear-gradient(135deg,#1f9d55,#2ecc71)';

      if ($p_tipo === 'empresa') {
          $p_title = htmlspecialchars($p['nombre_empresa'] ?: $p_nombre);
          $p_sub   = htmlspecialchars($p['sector'] ?? '');
          $p_logo  = !empty($p['logo_empresa']) ? 'uploads/logos/'.htmlspecialchars($p['logo_empresa']) : (!empty($p['foto']) ? 'uploads/fotos/'.htmlspecialchars($p['foto']) : '');
          $p_color = 'linear-gradient(135deg,#1a56db,#3b82f6)';
          $p_tipo_label = '🏢 Empresa';
      } elseif ($p_tipo === 'negocio') {
          $p_title = htmlspecialchars($p['nombre_negocio'] ?: $p_nombre);
          $p_sub   = htmlspecialchars($p['categoria'] ?? '');
          $p_logo  = !empty($p['logo_negocio']) ? 'uploads/logos/'.htmlspecialchars($p['logo_negocio']) : (!empty($p['foto']) ? 'uploads/fotos/'.htmlspecialchars($p['foto']) : '');
          $p_color = 'linear-gradient(135deg,#d4a017,#f0c040)';
          $p_tipo_label = '🏪 Negocio';
      } else {
          $p_title = $p_nombre;
          $p_sub   = htmlspecialchars($p['profesion'] ?? '');
          $p_logo  = !empty($p['foto']) ? 'uploads/fotos/'.htmlspecialchars($p['foto']) : '';
          $p_color = $p['avatar_color'] ?? 'linear-gradient(135deg,#1f9d55,#2ecc71)';
          $p_tipo_label = $p['tipo_servicio'] ? '🎧 Servicios' : '👤 Talento';
      }
      $p_bio   = htmlspecialchars($p['bio'] ?? '');
      $p_city  = htmlspecialchars($p['ciudad'] ?? 'Quibdó');
      $p_verif = !empty($p['verificado']);
      $p_skills = $p['skills'] ? array_filter(array_map('trim', explode(',', $p['skills']))) : [];
      $link_params = http_build_query(['ver' => $p['id'], 'q' => $q, 'ciudad' => $ciudad, 'tipo' => $tipo]);
    ?>
    <div class="card" onclick="window.location='busqueda_talentos.php?<?= $link_params ?>'">
      <!-- cabecera color -->
      <div class="card-header" style="background:<?= $p_color ?>">
        <div class="card-avatar" style="background:<?= $p_color ?>">
          <?php if ($p_logo): ?>
            <img src="<?= $p_logo ?>" alt="foto" onerror="this.style.display='none'">
          <?php endif; ?>
          <?= $p_inicial ?>
        </div>
      </div>
      <div class="card-body">
        <div class="card-name"><?= $p_title ?></div>
        <?php if ($p_sub): ?><div class="card-sub"><?= $p_sub ?></div><?php endif; ?>
        <div class="card-city">📍 <?= $p_city ?></div>
        <?php if ($p_bio): ?><div class="card-bio"><?= $p_bio ?></div><?php endif; ?>
        <div class="card-tags">
          <span class="tag"><?= $p_tipo_label ?></span>
          <?php foreach(array_slice($p_skills, 0, 3) as $sk): ?>
            <span class="tag"><?= htmlspecialchars($sk) ?></span>
          <?php endforeach; ?>
          <?php if (count($p_skills) > 3): ?>
            <span class="tag tag-city">+<?= count($p_skills)-3 ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-footer">
        <?php if ($p_verif): ?>
          <span class="card-verif">✅ Verificado</span>
        <?php else: ?>
          <span style="font-size:11px;color:var(--muted2)">Sin verificar</span>
        <?php endif; ?>
        <span class="card-vistas">👁 <?= $p['vistas'] ?> vistas</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- paginación -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php
    $qs = ['q'=>$q,'ciudad'=>$ciudad,'tipo'=>$tipo];
    for ($i=1; $i<=$totalPages; $i++):
      $qsCopy = array_merge($qs, ['page'=>$i]);
    ?>
      <a class="page-btn <?= $i===$page?'active':'' ?>" href="busqueda_talentos.php?<?= http_build_query($qsCopy) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php endif; // empty perfiles ?>
<?php endif; // ver perfil ?>

</div><!-- /container -->

<!-- Lightbox -->
<div id="lbox">
  <button id="lbox-close" onclick="document.getElementById('lbox').classList.remove('open')">×</button>
  <img id="lbox-img" src="" alt="">
</div>

<script>
  document.getElementById('lbox').addEventListener('click', function(e){
    if (e.target === this) this.classList.remove('open');
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') document.getElementById('lbox').classList.remove('open');
  });
  function abrirLbox(src, titulo) {
    document.getElementById('lbox-img').src = src;
    document.getElementById('lbox').classList.add('open');
  }
</script>

</body>
</html>