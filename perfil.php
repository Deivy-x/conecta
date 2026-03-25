<?php
// ============================================================
// perfil.php — Perfil público QuibdóConecta
// ?id=X&tipo=talento|empresa|negocio|candidato
// ============================================================
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");

$dbFile = __DIR__ . '/Php/db.php';
$badgesFile = __DIR__ . '/Php/badges_helper.php';
if (!file_exists($dbFile)) { die('<p style="font-family:sans-serif;padding:40px">Error: no se pudo conectar a la base de datos.</p>'); }
require_once $dbFile;
if (file_exists($badgesFile)) require_once $badgesFile;

if (!function_exists('getBadgesUsuario')) {
    function getBadgesUsuario($db, $id) { return []; }
    function renderBadges($b) { return ''; }
    function tieneBadge($b, $n) { return false; }
}

$uid  = (int)($_GET['id'] ?? 0);
$tipo = trim($_GET['tipo'] ?? '');
if (!$uid) { header('Location: index.html'); exit; }

try { $db = getDB(); } catch (Exception $e) {
    die('<p style="font-family:sans-serif;padding:40px;color:#555">No se pudo conectar a la base de datos.</p>');
}

$u = null;
try {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id=? AND activo=1");
    $stmt->execute([$uid]); $u = $stmt->fetch();
} catch(Exception $e) { $u = null; }

if (!$u) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Perfil no encontrado</title>
    <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:system-ui,sans-serif;background:#f8faf9;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:16px;color:#1a1a1a}.icon{font-size:64px}.btn{padding:12px 28px;background:#1f9d55;color:white;border-radius:24px;text-decoration:none;font-weight:700;font-size:15px}</style></head>
    <body><div class="icon">🌿</div><h2>Perfil no encontrado</h2><p style="color:#666">Este perfil no existe o fue desactivado.</p><a href="index.html" class="btn">← Volver al inicio</a><script src="js/sesion_widget.js"></script></body></html>';
    exit;
}
if (!$tipo) $tipo = $u['tipo'] ?? 'candidato';

$tp = $ep = $np = null;
try { $tpStmt = $db->prepare("SELECT * FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1"); $tpStmt->execute([$uid]); $tp = $tpStmt->fetch() ?: null; } catch(Exception $e) { $tp = null; }
if ($tipo === 'empresa') { try { $epStmt = $db->prepare("SELECT * FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1"); $epStmt->execute([$uid]); $ep = $epStmt->fetch() ?: null; } catch(Exception $e) { $ep = null; } }
if ($tipo === 'negocio') { try { $npStmt = $db->prepare("SELECT * FROM negocios_locales WHERE usuario_id=? ORDER BY id DESC LIMIT 1"); $npStmt->execute([$uid]); $np = $npStmt->fetch() ?: null; } catch(Exception $e) { $np = null; } }

$galeria = [];
try {
    $db->exec("CREATE TABLE IF NOT EXISTS talento_galeria (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, tipo ENUM('foto','video') NOT NULL DEFAULT 'foto', archivo VARCHAR(255) NOT NULL DEFAULT '', url_video VARCHAR(500) DEFAULT NULL, titulo VARCHAR(150) DEFAULT NULL, descripcion TEXT DEFAULT NULL, orden TINYINT NOT NULL DEFAULT 0, activo TINYINT(1) NOT NULL DEFAULT 1, creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_u (usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $gStmt = $db->prepare("SELECT * FROM talento_galeria WHERE usuario_id=? AND activo=1 ORDER BY orden ASC, id ASC LIMIT 30");
    $gStmt->execute([$uid]); $galeria = $gStmt->fetchAll();
} catch(Exception $e) { $galeria = []; }

try { $badges = getBadgesUsuario($db, $uid); } catch(Exception $e) { $badges = []; }
$tieneVerif    = !empty($u['verificado']) || tieneBadge($badges,'Verificado') || tieneBadge($badges,'Usuario Verificado') || tieneBadge($badges,'Empresa Verificada');
$tienePremium  = tieneBadge($badges,'Premium');
$tieneTop      = tieneBadge($badges,'Top');
$tieneDestacado= tieneBadge($badges,'Destacado') || (int)($tp['destacado']??0);

$visitorId  = $_SESSION['usuario_id'] ?? null;
$esMiPerfil = $visitorId && $visitorId == $uid;
if (!$esMiPerfil) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ck = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=? AND ip=? AND creado_en>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");
        $ck->execute([$uid,$ip]);
        if ((int)$ck->fetchColumn() === 0) {
            $db->prepare("INSERT INTO perfil_vistas (usuario_id,visitante_id,ip,seccion) VALUES (?,?,?,?)")->execute([$uid,$visitorId,$ip,$tipo]);
        }
    } catch(Exception $e) {}
}

$vistasTotal = 0;
try { $vt = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=?"); $vt->execute([$uid]); $vistasTotal = (int)$vt->fetchColumn(); } catch(Exception $e) {}

function ytId($url) { preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',$url,$m); return $m[1]??''; }

// ── FIX FOTO URL: Cloudinary returns full https:// URL, local returns just filename ──
function resolveUrl($val, $prefix) {
    if (empty($val)) return '';
    if (str_starts_with($val, 'http://') || str_starts_with($val, 'https://')) return htmlspecialchars($val);
    return $prefix . htmlspecialchars($val);
}

$nombre  = htmlspecialchars(trim($u['nombre'].' '.($u['apellido']??'')));
$ciudad  = htmlspecialchars($u['ciudad'] ?? 'Quibdó, Chocó');
$fotoUrl = resolveUrl($u['foto'] ?? '', 'uploads/fotos/');
$inicial = strtoupper(mb_substr($u['nombre'],0,1).mb_substr($u['apellido']??'',0,1));

$titulo = $nombre; $subtitulo = ''; $descripcion = ''; $logoUrl = $fotoUrl;
$colorAccent = '#1f9d55'; $colorAccent2 = '#2ecc71';
$sitioWeb = ''; $nit = ''; $telefonoPub = ''; $whatsapp = '';
$skills = []; $generos = []; $precio = ''; $tipoServ = '';

if ($tipo === 'empresa' && $ep) {
    $titulo = $ep['nombre_empresa'] ?: $nombre;
    $subtitulo = $ep['sector'] ?? ''; $descripcion = $ep['descripcion'] ?? '';
    $logoUrl = resolveUrl($ep['logo'] ?? '', 'uploads/logos/') ?: $fotoUrl;
    $colorAccent = '#1a56db'; $colorAccent2 = '#3b82f6';
    $telefonoPub = htmlspecialchars($ep['telefono_empresa'] ?? '');
    $sitioWeb = htmlspecialchars($ep['sitio_web'] ?? ''); $nit = htmlspecialchars($ep['nit'] ?? '');
} elseif ($tipo === 'negocio' && $np) {
    $titulo = $np['nombre_negocio'] ?: $nombre;
    $subtitulo = $np['categoria'] ?? ''; $descripcion = $np['descripcion'] ?? '';
    $logoUrl = resolveUrl($np['logo'] ?? '', 'uploads/logos/') ?: $fotoUrl;
    $colorAccent = '#d4a017'; $colorAccent2 = '#f0c040';
    $whatsapp = preg_replace('/\D/','',$np['whatsapp'] ?? '');
} elseif ($tp) {
    $subtitulo = htmlspecialchars($tp['profesion'] ?? ''); $descripcion = htmlspecialchars($tp['bio'] ?? '');
    $skills  = array_filter(array_map('trim', explode(',', $tp['skills'] ?? '')));
    $generos = array_filter(array_map('trim', explode(',', $tp['generos'] ?? '')));
    $precio  = $tp['precio_desde'] ? number_format((float)$tp['precio_desde'], 0, ',', '.') : '';
    $tipoServ = htmlspecialchars($tp['tipo_servicio'] ?? '');
}
$titulo      = htmlspecialchars($titulo);
$subtitulo   = htmlspecialchars($subtitulo);
$descripcion = htmlspecialchars($descripcion);

if ($tipo === 'empresa') { $tipoLabel = '🏢 Empresa'; }
elseif ($tipo === 'negocio') { $tipoLabel = '🏪 Negocio local'; }
elseif ($tp && !empty($tp['tipo_servicio'])) { $tipoLabel = '🎧 Servicios & Eventos'; }
else { $tipoLabel = '👤 Talento profesional'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $titulo ?> — QuibdóConecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800;900&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
  <style>
    :root {
      --accent:  <?= $colorAccent ?>;
      --accent2: <?= $colorAccent2 ?>;
      --bg:      #f5f7f5;
      --surface: #ffffff;
      --surface2:#f0f4f1;
      --border:  rgba(0,0,0,.08);
      --ink:     #0f1a12;
      --ink2:    #4a5c4e;
      --ink3:    #8a9e8e;
      --shadow:  0 2px 20px rgba(0,0,0,.07);
      --shadow-lg: 0 8px 40px rgba(0,0,0,.12);
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html { scroll-behavior:smooth; }
    body { font-family:'DM Sans',sans-serif; background:var(--bg); color:var(--ink); min-height:100vh; overflow-x:hidden; }

    /* ── ANIMACIONES ── */
    @keyframes fadeUp   { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
    @keyframes fadeIn   { from { opacity:0; } to { opacity:1; } }
    @keyframes scaleIn  { from { opacity:0; transform:scale(.92); } to { opacity:1; transform:scale(1); } }
    @keyframes slideRight { from { opacity:0; transform:translateX(-20px); } to { opacity:1; transform:translateX(0); } }
    @keyframes pulse    { 0%,100% { opacity:1; } 50% { opacity:.6; } }
    @keyframes shimmer  { 0% { background-position:-400px 0; } 100% { background-position:400px 0; } }
    @keyframes coverIn  { from { opacity:0; transform:scale(1.04); } to { opacity:1; transform:scale(1); } }

    .anim-fade-up   { animation:fadeUp .55s cubic-bezier(.22,1,.36,1) both; }
    .anim-fade-in   { animation:fadeIn .4s ease both; }
    .anim-scale-in  { animation:scaleIn .5s cubic-bezier(.34,1.56,.64,1) both; }
    .anim-slide-r   { animation:slideRight .5s cubic-bezier(.22,1,.36,1) both; }

    /* ── NAVBAR ── */
    .flag { position:fixed; top:0; left:0; right:0; height:3px; display:flex; z-index:1000; }
    .flag span:nth-child(1){ flex:1; background:#1f9d55; }
    .flag span:nth-child(2){ flex:1; background:#d4a017; }
    .flag span:nth-child(3){ flex:1; background:#1a3a6b; }

    .navbar {
      position:fixed; top:3px; left:0; width:100%; height:58px;
      background:rgba(255,255,255,.92); backdrop-filter:blur(24px);
      border-bottom:1px solid var(--border); z-index:999;
      display:flex; align-items:center; justify-content:space-between; padding:0 28px;
      box-shadow:0 1px 12px rgba(0,0,0,.06);
    }
    .brand { font-family:'DM Sans',sans-serif; font-size:18px; font-weight:900; color:var(--ink); text-decoration:none; letter-spacing:-.5px; }
    .brand em { color:var(--accent); font-style:normal; }
    .nav-r { display:flex; align-items:center; gap:10px; }
    .btn-back { padding:8px 18px; border:1.5px solid var(--border); border-radius:20px; color:var(--ink2); font-size:13px; font-weight:600; text-decoration:none; transition:all .2s; background:var(--surface); }
    .btn-back:hover { border-color:var(--accent); color:var(--accent); background:#f0fdf4; }
    .btn-panel { padding:8px 18px; background:var(--accent); color:#fff; border-radius:20px; font-size:13px; font-weight:700; text-decoration:none; box-shadow:0 3px 12px rgba(31,157,85,.3); transition:all .2s; }
    .btn-panel:hover { transform:translateY(-1px); box-shadow:0 5px 18px rgba(31,157,85,.4); }

    /* ── COVER ── */
    .cover {
      height:220px; margin-top:61px; position:relative; overflow:hidden;
      animation:coverIn .7s cubic-bezier(.22,1,.36,1) both;
    }
    .cover-bg {
      position:absolute; inset:0;
      background:linear-gradient(135deg, <?= $colorAccent ?>, <?= $colorAccent2 ?>);
    }
    .cover-pattern {
      position:absolute; inset:0; opacity:.12;
      background-image: radial-gradient(circle at 20% 50%, white 1px, transparent 1px),
                        radial-gradient(circle at 80% 20%, white 1px, transparent 1px),
                        radial-gradient(circle at 60% 80%, white 1px, transparent 1px);
      background-size: 40px 40px;
    }
    .cover-overlay { position:absolute; inset:0; background:linear-gradient(to bottom, transparent 40%, rgba(245,247,245,.6) 100%); }

    /* ── HERO CARD ── */
    .hero-wrap { max-width:980px; margin:0 auto; padding:0 24px; }
    .hero-card {
      background:var(--surface); border:1px solid var(--border); border-radius:28px;
      margin-top:-80px; position:relative; z-index:10; padding:32px;
      display:flex; gap:28px; align-items:flex-start;
      box-shadow:var(--shadow-lg);
      animation:fadeUp .6s .1s cubic-bezier(.22,1,.36,1) both;
    }
    @media(max-width:640px) {
      .hero-card { flex-direction:column; align-items:center; text-align:center; padding:24px 20px; margin-top:-60px; gap:16px; }
      .hero-actions { justify-content:center; }
    }

    .hero-av {
      width:100px; height:100px; border-radius:50%; flex-shrink:0;
      object-fit:cover; border:4px solid var(--surface);
      box-shadow:0 4px 20px rgba(0,0,0,.15);
      animation:scaleIn .5s .2s cubic-bezier(.34,1.56,.64,1) both;
    }
    .hero-av-ph {
      width:100px; height:100px; border-radius:50%; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      font-family:'DM Sans',sans-serif; font-size:34px; font-weight:900; color:#fff;
      border:4px solid var(--surface); box-shadow:0 4px 20px rgba(0,0,0,.15);
      background:linear-gradient(135deg, <?= $colorAccent ?>, <?= $colorAccent2 ?>);
      animation:scaleIn .5s .2s cubic-bezier(.34,1.56,.64,1) both;
    }

    .hero-info { flex:1; min-width:0; }
    .hero-tipo { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.8px; color:var(--accent); background:color-mix(in srgb, var(--accent) 10%, transparent); border:1px solid color-mix(in srgb, var(--accent) 20%, transparent); padding:4px 10px; border-radius:20px; margin-bottom:10px; animation:slideRight .4s .3s both; }
    .hero-nombre { font-family:'Playfair Display',serif; font-size:clamp(22px,4vw,32px); font-weight:800; color:var(--ink); line-height:1.1; margin-bottom:4px; letter-spacing:-.3px; animation:fadeUp .5s .35s both; }
    .hero-sub { font-size:15px; color:var(--accent); font-weight:600; margin-bottom:4px; animation:fadeUp .5s .4s both; }
    .hero-loc { font-size:13px; color:var(--ink3); margin-bottom:14px; animation:fadeUp .5s .42s both; }

    .hero-badges { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; animation:fadeUp .5s .45s both; }
    .hb { display:inline-flex; align-items:center; gap:4px; padding:4px 11px; border-radius:20px; font-size:11px; font-weight:700; }
    .hb-v    { background:#ecfdf5; border:1px solid #bbf7d0; color:#065f46; }
    .hb-top  { background:#fff7ed; border:1px solid #fed7aa; color:#c2410c; }
    .hb-prem { background:#fefce8; border:1px solid #fde68a; color:#92400e; }
    .hb-dest { background:#f5f3ff; border:1px solid #ddd6fe; color:#5b21b6; }

    .hero-actions { display:flex; flex-wrap:wrap; gap:8px; animation:fadeUp .5s .5s both; }
    .btn-contratar { padding:11px 24px; background:linear-gradient(135deg, <?= $colorAccent ?>, <?= $colorAccent2 ?>); color:#fff; border-radius:24px; font-size:14px; font-weight:700; text-decoration:none; box-shadow:0 4px 16px color-mix(in srgb, var(--accent) 40%, transparent); transition:all .25s; border:none; cursor:pointer; }
    .btn-contratar:hover { transform:translateY(-2px); box-shadow:0 7px 22px color-mix(in srgb, var(--accent) 50%, transparent); }
    .btn-wa { padding:11px 22px; background:#25d366; color:#fff; border-radius:24px; font-size:14px; font-weight:700; text-decoration:none; transition:all .25s; }
    .btn-wa:hover { transform:translateY(-1px); background:#1ebe5d; }
    .btn-outline { padding:11px 20px; border:1.5px solid var(--border); color:var(--ink2); border-radius:24px; font-size:13px; font-weight:600; text-decoration:none; transition:all .25s; background:transparent; }
    .btn-outline:hover { border-color:var(--accent); color:var(--accent); background:color-mix(in srgb, var(--accent) 5%, transparent); }

    /* ── STATS BAR ── */
    .stats-bar { max-width:980px; margin:18px auto 0; padding:0 24px; animation:fadeUp .5s .55s both; }
    .stats-inner { background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:0; display:flex; overflow:hidden; box-shadow:var(--shadow); }
    .sbar-item { flex:1; text-align:center; padding:16px 12px; position:relative; }
    .sbar-item:not(:last-child)::after { content:''; position:absolute; right:0; top:20%; bottom:20%; width:1px; background:var(--border); }
    .sbar-val { font-family:'DM Sans',sans-serif; font-size:22px; font-weight:900; color:var(--accent); }
    .sbar-lab { font-size:11px; color:var(--ink3); font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }

    /* ── MAIN LAYOUT ── */
    .perfil-wrap { max-width:980px; margin:22px auto 80px; padding:0 24px; display:grid; grid-template-columns:1fr 300px; gap:20px; }
    @media(max-width:720px) { .perfil-wrap { grid-template-columns:1fr; } }

    /* ── CARDS ── */
    .pcard {
      background:var(--surface); border:1px solid var(--border); border-radius:22px;
      overflow:hidden; margin-bottom:16px; box-shadow:var(--shadow);
      transition:box-shadow .25s, transform .25s;
      animation:fadeUp .6s cubic-bezier(.22,1,.36,1) both;
    }
    .pcard:hover { box-shadow:var(--shadow-lg); transform:translateY(-2px); }
    .pcard-head { padding:22px 24px 0; display:flex; align-items:center; justify-content:space-between; }
    .pcard-tit { font-family:'DM Sans',sans-serif; font-size:15px; font-weight:800; color:var(--ink); display:flex; align-items:center; gap:8px; }
    .pcard-tit-icon { width:28px; height:28px; border-radius:8px; background:color-mix(in srgb, var(--accent) 12%, transparent); display:flex; align-items:center; justify-content:center; font-size:14px; }
    .pcard-count { font-size:11px; color:var(--ink3); font-weight:700; background:var(--surface2); padding:3px 10px; border-radius:12px; }
    .pcard-body { padding:16px 24px 22px; }

    /* ── BANNER MI PERFIL ── */
    .miperfil-banner {
      background:linear-gradient(135deg, color-mix(in srgb, var(--accent) 8%, transparent), color-mix(in srgb, var(--accent) 3%, transparent));
      border:1.5px solid color-mix(in srgb, var(--accent) 20%, transparent);
      border-radius:16px; padding:14px 18px;
      display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap;
      animation:fadeUp .5s .6s both;
    }
    .miperfil-txt { font-size:13px; color:var(--accent); font-weight:600; }
    .btn-editar { padding:8px 20px; background:var(--accent); color:#fff; border-radius:20px; font-size:13px; font-weight:700; text-decoration:none; transition:all .2s; }
    .btn-editar:hover { transform:translateY(-1px); }

    /* ── DESCRIPCIÓN ── */
    .desc-txt { font-size:15px; color:var(--ink2); line-height:1.75; }

    /* ── GALERÍA ── */
    .gal-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:10px; }
    .gal-item {
      position:relative; border-radius:14px; overflow:hidden;
      aspect-ratio:1; cursor:pointer; border:1px solid var(--border);
      background:var(--surface2); transition:all .3s cubic-bezier(.34,1.56,.64,1);
    }
    .gal-item:hover { transform:scale(1.05); box-shadow:0 8px 24px rgba(0,0,0,.15); border-color:var(--accent); }
    .gal-item img, .gal-item video { width:100%; height:100%; object-fit:cover; }
    .gal-play { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,.3); font-size:24px; }
    .gal-label { position:absolute; bottom:0; left:0; right:0; padding:5px 8px; background:rgba(0,0,0,.55); color:#fff; font-size:10px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* ── TAGS / HABILIDADES ── */
    .tags { display:flex; flex-wrap:wrap; gap:8px; }
    .tag { padding:7px 14px; border-radius:20px; font-size:13px; font-weight:600; transition:all .2s; cursor:default; }
    .tag-skill { background:color-mix(in srgb, var(--accent) 10%, transparent); color:var(--accent); border:1px solid color-mix(in srgb, var(--accent) 25%, transparent); }
    .tag-skill:hover { background:color-mix(in srgb, var(--accent) 18%, transparent); transform:translateY(-1px); }
    .tag-gen { background:#fefce8; color:#92400e; border:1px solid #fde68a; }
    .tag-gen:hover { background:#fef08a; transform:translateY(-1px); }

    /* ── INFO TABLE ── */
    .info-table { width:100%; font-size:14px; border-collapse:collapse; }
    .info-table td { padding:11px 0; color:var(--ink2); }
    .info-table td:first-child { color:var(--ink3); width:40%; font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.5px; }
    .info-table tr:not(:last-child) td { border-bottom:1px solid var(--border); }
    .info-table td:last-child { font-weight:600; color:var(--ink); }

    /* ── SIDEBAR ── */
    .side-card { background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:22px; margin-bottom:14px; box-shadow:var(--shadow); animation:fadeUp .6s cubic-bezier(.22,1,.36,1) both; transition:box-shadow .25s; }
    .side-card:hover { box-shadow:var(--shadow-lg); }
    .side-tit { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.7px; color:var(--ink3); margin-bottom:16px; }

    /* ── PRECIO ── */
    .precio-card {
      border-radius:20px; padding:24px; margin-bottom:14px; text-align:center;
      background:linear-gradient(135deg, <?= $colorAccent ?>, <?= $colorAccent2 ?>);
      box-shadow:0 6px 24px color-mix(in srgb, var(--accent) 35%, transparent);
      animation:fadeUp .6s cubic-bezier(.22,1,.36,1) both;
    }
    .precio-label { font-size:11px; text-transform:uppercase; letter-spacing:.8px; color:rgba(255,255,255,.7); margin-bottom:4px; }
    .precio-val { font-family:'DM Sans',sans-serif; font-size:38px; font-weight:900; color:#fff; }
    .precio-unidad { font-size:13px; color:rgba(255,255,255,.7); margin-top:4px; }

    .side-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; font-size:13px; }
    .side-row:not(:last-child) { border-bottom:1px solid var(--border); }
    .side-lab { color:var(--ink3); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.3px; }
    .side-val { font-weight:700; color:var(--ink); }

    /* ── CTA ── */
    .cta-card {
      background:linear-gradient(135deg, #f0fdf4, #dcfce7);
      border:1.5px solid #bbf7d0; border-radius:20px; padding:22px; text-align:center; margin-bottom:14px;
      animation:fadeUp .6s cubic-bezier(.22,1,.36,1) both;
    }
    .cta-icon { font-size:32px; margin-bottom:10px; }
    .cta-card h4 { font-family:'DM Sans',sans-serif; font-size:16px; font-weight:800; color:#065f46; margin-bottom:6px; }
    .cta-card p { font-size:12px; color:#047857; margin-bottom:14px; line-height:1.55; }
    .btn-reg { display:inline-block; padding:10px 24px; background:#1f9d55; color:#fff; border-radius:20px; font-weight:700; font-size:13px; text-decoration:none; transition:all .2s; }
    .btn-reg:hover { background:#178a49; transform:translateY(-1px); }

    /* ── EXPLORAR ── */
    .explore-links { display:flex; flex-direction:column; gap:8px; }
    .explore-link { display:flex; align-items:center; gap:10px; padding:11px 14px; border-radius:14px; font-size:13px; font-weight:700; text-decoration:none; transition:all .25s; border:1px solid var(--border); color:var(--ink2); background:var(--surface2); }
    .explore-link:hover { transform:translateX(4px); border-color:var(--accent); color:var(--accent); background:color-mix(in srgb, var(--accent) 6%, transparent); }

    /* ── LIGHTBOX ── */
    #lbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,.9); z-index:9999; align-items:center; justify-content:center; flex-direction:column; padding:20px; backdrop-filter:blur(8px); }
    #lbox img { max-width:90vw; max-height:82vh; border-radius:16px; object-fit:contain; box-shadow:0 20px 60px rgba(0,0,0,.5); }
    #lbox-tit { color:rgba(255,255,255,.6); margin-top:12px; font-size:13px; }
    .lbox-close { position:absolute; top:20px; right:24px; font-size:28px; color:rgba(255,255,255,.8); background:rgba(255,255,255,.1); border:none; cursor:pointer; width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:background .2s; }
    .lbox-close:hover { background:rgba(255,255,255,.2); }

    /* ── FOOTER ── */
    footer { background:var(--surface); border-top:1px solid var(--border); color:var(--ink3); text-align:center; padding:28px; font-size:13px; }
    footer strong { color:var(--accent); }

    /* ── RESPONSIVE ── */
    @media(max-width:540px) {
      .navbar { padding:0 16px; }
      .hero-wrap, .stats-bar, .perfil-wrap { padding:0 14px; }
      .hero-card { border-radius:20px; }
      .cover { height:160px; }
    }
  </style>
</head>
<body>

<div class="flag"><span></span><span></span><span></span></div>

<nav class="navbar">
  <a href="index.html" class="brand">Quibdó<em>Conecta</em></a>
  <div class="nav-r">
    <a href="javascript:history.back()" class="btn-back">← Volver</a>
    <?php if (!$esMiPerfil): ?>
      <a href="chat.php?con=<?= $uid ?>" class="btn-panel">💬 Mensaje</a>
    <?php else: ?>
      <a href="dashboard.php" class="btn-panel">🏠 Mi panel</a>
    <?php endif; ?>
  </div>
</nav>

<!-- COVER -->
<div class="cover">
  <div class="cover-bg"></div>
  <div class="cover-pattern"></div>
  <div class="cover-overlay"></div>
</div>

<!-- HERO -->
<div class="hero-wrap">
  <div class="hero-card">
    <?php if ($logoUrl): ?>
      <img src="<?= $logoUrl ?>" alt="<?= $titulo ?>" class="hero-av" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="hero-av-ph" style="display:none"><?= $inicial ?></div>
    <?php else: ?>
      <div class="hero-av-ph"><?= $inicial ?></div>
    <?php endif; ?>

    <div class="hero-info">
      <div class="hero-tipo"><?= $tipoLabel ?></div>
      <h1 class="hero-nombre"><?= $titulo ?></h1>
      <?php if ($subtitulo): ?><p class="hero-sub"><?= $subtitulo ?></p><?php endif; ?>
      <p class="hero-loc">📍 <?= $ciudad ?></p>

      <div class="hero-badges">
        <?php if ($tieneVerif):     ?><span class="hb hb-v">✅ Verificado</span><?php endif; ?>
        <?php if ($tieneTop):       ?><span class="hb hb-top">👑 Top</span><?php endif; ?>
        <?php if ($tienePremium):   ?><span class="hb hb-prem">⭐ Premium</span><?php endif; ?>
        <?php if ($tieneDestacado): ?><span class="hb hb-dest">🏅 Destacado</span><?php endif; ?>
      </div>

      <?php if (!$esMiPerfil): ?>
      <div class="hero-actions">
        <?php if ($whatsapp): ?>
          <a href="https://wa.me/<?= $whatsapp ?>?text=Hola!+Vi+tu+perfil+en+QuibdóConecta" target="_blank" class="btn-wa">💬 WhatsApp</a>
        <?php endif; ?>
        <?php if ($precio): ?>
          <a href="chat.php?con=<?= $uid ?>" class="btn-contratar">💰 Solicitar cotización</a>
        <?php else: ?>
          <a href="chat.php?con=<?= $uid ?>" class="btn-contratar">✉️ Enviar mensaje</a>
        <?php endif; ?>
        <?php if ($sitioWeb): ?>
          <a href="<?= $sitioWeb ?>" target="_blank" class="btn-outline">🌐 Sitio web</a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="hero-actions">
        <a href="dashboard.php" class="btn-outline">✏️ Editar mi perfil</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- STATS BAR -->
<div class="stats-bar">
  <div class="stats-inner">
    <div class="sbar-item">
      <div class="sbar-val"><?= $vistasTotal > 999 ? number_format($vistasTotal/1000,1).'K' : ($vistasTotal ?: '—') ?></div>
      <div class="sbar-lab">Vistas</div>
    </div>
    <?php if (!empty($galeria)): ?>
    <div class="sbar-item">
      <div class="sbar-val"><?= count($galeria) ?></div>
      <div class="sbar-lab">Evidencias</div>
    </div>
    <?php endif; ?>
    <?php if (!empty($skills)): ?>
    <div class="sbar-item">
      <div class="sbar-val"><?= count($skills) ?></div>
      <div class="sbar-lab">Habilidades</div>
    </div>
    <?php endif; ?>
    <div class="sbar-item">
      <div class="sbar-val"><?= $tieneVerif ? '✅' : '—' ?></div>
      <div class="sbar-lab">Verificado</div>
    </div>
  </div>
</div>

<!-- MAIN -->
<div class="perfil-wrap">

  <!-- COLUMNA PRINCIPAL -->
  <div>

    <?php if ($esMiPerfil): ?>
    <div class="miperfil-banner">
      <span class="miperfil-txt">👁 Así ven tu perfil otros usuarios</span>
      <a href="dashboard.php" class="btn-editar">Editar perfil →</a>
    </div>
    <?php endif; ?>

    <?php if ($descripcion): ?>
    <div class="pcard" style="animation-delay:.05s">
      <div class="pcard-head">
        <span class="pcard-tit"><span class="pcard-tit-icon">📝</span> Acerca de</span>
      </div>
      <div class="pcard-body"><p class="desc-txt"><?= nl2br($descripcion) ?></p></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($galeria)): ?>
    <div class="pcard" style="animation-delay:.1s">
      <div class="pcard-head">
        <span class="pcard-tit"><span class="pcard-tit-icon">📸</span> Galería</span>
        <span class="pcard-count"><?= count($galeria) ?> items</span>
      </div>
      <div class="pcard-body">
        <div class="gal-grid">
          <?php foreach($galeria as $gi):
            $isVid = $gi['tipo'] === 'video';
            $yid   = $isVid && $gi['url_video'] ? ytId($gi['url_video']) : '';
            $rawArchivo = $gi['archivo'] ?? '';
            $archUrl = resolveUrl($rawArchivo, 'uploads/galeria/');
            $thumb = $yid ? "https://img.youtube.com/vi/{$yid}/mqdefault.jpg" : ($archUrl ?: '');
          ?>
          <div class="gal-item" onclick="<?= $isVid && $gi['url_video'] ? "window.open('".htmlspecialchars($gi['url_video'])."','_blank')" : ($archUrl ? "abrirLbox('$archUrl','".htmlspecialchars($gi['titulo']??'')."')" : '') ?>">
            <?php if ($thumb): ?><img src="<?= $thumb ?>" alt="<?= htmlspecialchars($gi['titulo']??'') ?>" loading="lazy"><?php endif; ?>
            <?php if ($isVid): ?><div class="gal-play">▶️</div><?php endif; ?>
            <?php if ($gi['titulo']): ?><div class="gal-label"><?= htmlspecialchars($gi['titulo']) ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($skills)): ?>
    <div class="pcard" style="animation-delay:.15s">
      <div class="pcard-head"><span class="pcard-tit"><span class="pcard-tit-icon">⚡</span> Habilidades</span></div>
      <div class="pcard-body">
        <div class="tags">
          <?php foreach($skills as $s): ?><span class="tag tag-skill"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($generos)): ?>
    <div class="pcard" style="animation-delay:.2s">
      <div class="pcard-head"><span class="pcard-tit"><span class="pcard-tit-icon">🎵</span> Géneros & Especialidades</span></div>
      <div class="pcard-body">
        <div class="tags">
          <?php foreach($generos as $g): ?><span class="tag tag-gen"><?= htmlspecialchars($g) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($tipo === 'empresa' && $ep): ?>
    <div class="pcard" style="animation-delay:.2s">
      <div class="pcard-head"><span class="pcard-tit"><span class="pcard-tit-icon">🏢</span> Información empresarial</span></div>
      <div class="pcard-body">
        <table class="info-table">
          <?php if ($nit):        ?><tr><td>NIT</td><td><?= $nit ?></td></tr><?php endif; ?>
          <?php if ($subtitulo):  ?><tr><td>Sector</td><td><?= $subtitulo ?></td></tr><?php endif; ?>
          <?php if ($telefonoPub):?><tr><td>Teléfono</td><td><?= $telefonoPub ?></td></tr><?php endif; ?>
          <tr><td>Ciudad</td><td>📍 <?= $ciudad ?></td></tr>
          <?php if ($sitioWeb):   ?><tr><td>Sitio web</td><td><a href="<?= $sitioWeb ?>" target="_blank" style="color:var(--accent);font-weight:700">🌐 Ver sitio →</a></td></tr><?php endif; ?>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($tipo === 'negocio' && $np): ?>
    <div class="pcard" style="animation-delay:.2s">
      <div class="pcard-head"><span class="pcard-tit"><span class="pcard-tit-icon">🏪</span> Datos del negocio</span></div>
      <div class="pcard-body">
        <table class="info-table">
          <tr><td>Tipo</td><td><?= ($np['tipo_negocio']??'emp') === 'cc' ? '🏬 C.C. El Caraño' : '🌱 Emprendedor' ?></td></tr>
          <?php if ($subtitulo): ?><tr><td>Categoría</td><td><?= $subtitulo ?></td></tr><?php endif; ?>
          <?php if (!empty($np['ubicacion'])): ?><tr><td>Dirección</td><td>📍 <?= htmlspecialchars($np['ubicacion']) ?></td></tr><?php endif; ?>
          <?php if ($whatsapp): ?><tr><td>WhatsApp</td><td><a href="https://wa.me/<?= $whatsapp ?>" target="_blank" style="color:#25d366;font-weight:700">📱 Contactar</a></td></tr><?php endif; ?>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- SIDEBAR -->
  <div>

    <?php if ($precio): ?>
    <div class="precio-card">
      <div class="precio-label">Precio desde</div>
      <div class="precio-val">$<?= $precio ?></div>
      <div class="precio-unidad"><?= $tipoServ ?: 'por evento / servicio' ?></div>
    </div>
    <a href="chat.php?con=<?= $uid ?>" class="btn-contratar" style="display:block;text-align:center;margin-bottom:16px;text-decoration:none">
      💰 Solicitar cotización
    </a>
    <?php endif; ?>

    <?php if ($whatsapp): ?>
    <a href="https://wa.me/<?= $whatsapp ?>?text=Hola!+Vi+tu+perfil+en+QuibdóConecta" target="_blank"
       style="display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;background:#25d366;color:#fff;border-radius:16px;font-weight:700;font-size:14px;text-decoration:none;margin-bottom:10px;transition:all .2s;box-shadow:0 4px 16px rgba(37,211,102,.3)"
       onmouseover="this.style.background='#1ebe5d';this.style.transform='translateY(-1px)'" onmouseout="this.style.background='#25d366';this.style.transform='translateY(0)'">
      💬 Contactar por WhatsApp
    </a>
    <?php endif; ?>

    <?php if (!$esMiPerfil && !$whatsapp): ?>
    <a href="chat.php?con=<?= $uid ?>"
       style="display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;background:color-mix(in srgb,var(--accent) 10%,transparent);border:1.5px solid color-mix(in srgb,var(--accent) 25%,transparent);color:var(--accent);border-radius:16px;font-weight:700;font-size:14px;text-decoration:none;margin-bottom:16px;transition:all .2s"
       onmouseover="this.style.background='color-mix(in srgb,var(--accent) 18%,transparent)'" onmouseout="this.style.background='color-mix(in srgb,var(--accent) 10%,transparent)'">
      ✉️ Enviar mensaje
    </a>
    <?php endif; ?>

    <div class="side-card" style="animation-delay:.1s">
      <div class="side-tit">ℹ️ Info rápida</div>
      <div class="side-row"><span class="side-lab">Ciudad</span><span class="side-val">📍 <?= $ciudad ?></span></div>
      <div class="side-row"><span class="side-lab">Tipo</span><span class="side-val"><?= $tipoLabel ?></span></div>
      <div class="side-row"><span class="side-lab">Verificado</span><span class="side-val"><?= $tieneVerif ? '<span style="color:#1f9d55">✅ Sí</span>' : '<span style="color:var(--ink3)">No</span>' ?></span></div>
      <?php if (!empty($galeria)): ?>
      <div class="side-row"><span class="side-lab">Evidencias</span><span class="side-val">📸 <?= count($galeria) ?></span></div>
      <?php endif; ?>
      <?php if ($vistasTotal > 0): ?>
      <div class="side-row"><span class="side-lab">Vistas</span><span class="side-val">👁 <?= number_format($vistasTotal) ?></span></div>
      <?php endif; ?>
    </div>

    <?php if (!$visitorId): ?>
    <div class="cta-card" style="animation-delay:.15s">
      <div class="cta-icon">🌿</div>
      <h4>¿Talento o empresa del Chocó?</h4>
      <p>Regístrate gratis y conecta con oportunidades laborales de la región.</p>
      <a href="registro.php" class="btn-reg">Registrarme gratis →</a>
    </div>
    <?php endif; ?>

    <div class="side-card" style="animation-delay:.2s">
      <div class="side-tit">🌿 Explorar más</div>
      <div class="explore-links">
        <a href="busqueda_talentos.php" class="explore-link">🌟 Ver talentos</a>
        <a href="empresas.php"          class="explore-link">🏢 Ver empresas</a>
        <a href="servicios.php"         class="explore-link">🎧 Ver servicios</a>
      </div>
    </div>

  </div>
</div>

<!-- LIGHTBOX -->
<div id="lbox" onclick="if(event.target===this)cerrarLbox()">
  <button class="lbox-close" onclick="cerrarLbox()">✕</button>
  <img id="lbox-img" src="" alt="">
  <p id="lbox-tit"></p>
</div>

<footer>
  <p>© 2026 <strong>QuibdóConecta</strong> — Conectando el talento del Chocó con el mundo.</p>
</footer>

<script>
  function abrirLbox(url, tit) {
    document.getElementById('lbox-img').src = url;
    document.getElementById('lbox-tit').textContent = tit || '';
    const lb = document.getElementById('lbox');
    lb.style.display = 'flex';
    lb.style.animation = 'fadeIn .25s ease both';
  }
  function cerrarLbox() {
    document.getElementById('lbox').style.display = 'none';
    document.getElementById('lbox-img').src = '';
  }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarLbox(); });

  // Scroll-triggered fade-up para cards
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.pcard, .side-card, .cta-card, .precio-card').forEach((el, i) => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = `opacity .5s ${i * 0.07}s ease, transform .5s ${i * 0.07}s cubic-bezier(.22,1,.36,1), box-shadow .25s, translateY .25s`;
    observer.observe(el);
  });
</script>
</body>
</html>