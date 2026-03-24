<?php
// ============================================================
// perfil.php — Perfil público QuibdóConecta
// ?id=X&tipo=talento|empresa|negocio|candidato
// ============================================================
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");

// Safe includes
$dbFile = __DIR__ . '/Php/db.php';
$badgesFile = __DIR__ . '/Php/badges_helper.php';
if (!file_exists($dbFile)) { die('<p style="font-family:sans-serif;padding:40px">Error: no se pudo conectar a la base de datos.</p>'); }
require_once $dbFile;
if (file_exists($badgesFile)) require_once $badgesFile;

// Funciones de badges si no existen
if (!function_exists('getBadgesUsuario')) {
    function getBadgesUsuario($db, $id) { return []; }
    function renderBadges($b) { return ''; }
    function tieneBadge($b, $n) { return false; }
}

$uid  = (int)($_GET['id'] ?? 0);
$tipo = trim($_GET['tipo'] ?? '');
if (!$uid) { header('Location: index.html'); exit; }

try {
    $db = getDB();
} catch (Exception $e) {
    die('<p style="font-family:sans-serif;padding:40px;color:#555">No se pudo conectar a la base de datos. Intenta más tarde.</p>');
}

$u = null;
try {
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id=? AND activo=1");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
} catch(Exception $e) { $u = null; }

if (!$u) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Perfil no encontrado</title><style>body{font-family:sans-serif;background:#060e07;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:16px}</style></head><body><div style="font-size:48px">🌿</div><h2>Perfil no encontrado</h2><p style="color:rgba(255,255,255,.5)">Este perfil no existe o fue desactivado.</p><a href="index.html" style="padding:10px 24px;background:#27a855;color:white;border-radius:20px;text-decoration:none;font-weight:700">← Volver al inicio</a></body></html>';
    exit;
}
if (!$tipo) $tipo = $u['tipo'] ?? 'candidato';

$tp = $ep = $np = null;
try {
    $tpStmt = $db->prepare("SELECT * FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
    $tpStmt->execute([$uid]); $tp = $tpStmt->fetch() ?: null;
} catch(Exception $e) { $tp = null; }

if ($tipo === 'empresa') {
    try {
        $epStmt = $db->prepare("SELECT * FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
        $epStmt->execute([$uid]); $ep = $epStmt->fetch() ?: null;
    } catch(Exception $e) { $ep = null; }
}
if ($tipo === 'negocio') {
    try {
        $npStmt = $db->prepare("SELECT * FROM negocios_locales WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
        $npStmt->execute([$uid]); $np = $npStmt->fetch() ?: null;
    } catch(Exception $e) { $np = null; }
}

// Galería
$galeria = [];
try {
    $db->exec("CREATE TABLE IF NOT EXISTS talento_galeria (
        id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL,
        tipo ENUM('foto','video') NOT NULL DEFAULT 'foto',
        archivo VARCHAR(255) NOT NULL DEFAULT '', url_video VARCHAR(500) DEFAULT NULL,
        titulo VARCHAR(150) DEFAULT NULL, descripcion TEXT DEFAULT NULL,
        orden TINYINT NOT NULL DEFAULT 0, activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_u (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $gStmt = $db->prepare("SELECT * FROM talento_galeria WHERE usuario_id=? AND activo=1 ORDER BY orden ASC, id ASC LIMIT 30");
    $gStmt->execute([$uid]); $galeria = $gStmt->fetchAll();
} catch(Exception $e) { $galeria = []; }

// Badges
try { $badges = getBadgesUsuario($db, $uid); } catch(Exception $e) { $badges = []; }
$tieneVerif   = !empty($u['verificado']) || tieneBadge($badges,'Verificado') || tieneBadge($badges,'Usuario Verificado') || tieneBadge($badges,'Empresa Verificada');
$tienePremium = tieneBadge($badges,'Premium');
$tieneTop     = tieneBadge($badges,'Top');
$tieneDestacado = tieneBadge($badges,'Destacado') || (int)($tp['destacado']??0);

// Registrar vista
$visitorId = $_SESSION['usuario_id'] ?? null;
$esMiPerfil = $visitorId && $visitorId == $uid;
if (!$esMiPerfil) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ck = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=? AND ip=? AND creado_en>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");
        $ck->execute([$uid,$ip]);
        if ((int)$ck->fetchColumn() === 0) {
            $db->prepare("INSERT INTO perfil_vistas (usuario_id,visitante_id,ip,seccion) VALUES (?,?,?,?)")
               ->execute([$uid,$visitorId,$ip,$tipo]);
        }
    } catch(Exception $e) {}
}

// Vistas totales
$vistasTotal = 0;
try { $vt = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=?"); $vt->execute([$uid]); $vistasTotal = (int)$vt->fetchColumn(); } catch(Exception $e) {}

function ytId($url) { preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',$url,$m); return $m[1]??''; }

// Datos display
$nombre   = htmlspecialchars(trim($u['nombre'].' '.($u['apellido']??'')));
$ciudad   = htmlspecialchars($u['ciudad'] ?? 'Quibdó, Chocó');
$fotoUrl  = !empty($u['foto']) ? 'uploads/fotos/'.htmlspecialchars($u['foto']) : '';
$inicial  = strtoupper(mb_substr($u['nombre'],0,1).mb_substr($u['apellido']??'',0,1));

$titulo = $nombre; $subtitulo = ''; $descripcion = ''; $logoUrl = $fotoUrl;
$colorGrad = 'linear-gradient(135deg,#1f9d55,#2ecc71)';
$sitioWeb = ''; $nit = ''; $telefonoPub = ''; $whatsapp = '';
$skills = []; $generos = []; $precio = ''; $tipoServ = '';

if ($tipo === 'empresa' && $ep) {
    $titulo=$ep['nombre_empresa']?:$nombre; $subtitulo=$ep['sector']??''; $descripcion=$ep['descripcion']??'';
    $logoUrl=!empty($ep['logo'])?'uploads/logos/'.htmlspecialchars($ep['logo']):$fotoUrl;
    $colorGrad=$ep['avatar_color']??'linear-gradient(135deg,#1a56db,#3b82f6)';
    $telefonoPub=htmlspecialchars($ep['telefono_empresa']??''); $sitioWeb=htmlspecialchars($ep['sitio_web']??''); $nit=htmlspecialchars($ep['nit']??'');
} elseif ($tipo === 'negocio' && $np) {
    $titulo=$np['nombre_negocio']?:$nombre; $subtitulo=$np['categoria']??''; $descripcion=$np['descripcion']??'';
    $logoUrl=!empty($np['logo'])?'uploads/logos/'.htmlspecialchars($np['logo']):$fotoUrl;
    $colorGrad=$np['avatar_color']??'linear-gradient(135deg,#d4a017,#f0c040)';
    $whatsapp=preg_replace('/\D/','',$np['whatsapp']??'');
} elseif ($tp) {
    $subtitulo=htmlspecialchars($tp['profesion']??''); $descripcion=htmlspecialchars($tp['bio']??'');
    $colorGrad=$tp['avatar_color']??'linear-gradient(135deg,#1f9d55,#2ecc71)';
    $skills=array_filter(array_map('trim',explode(',',$tp['skills']??'')));
    $generos=array_filter(array_map('trim',explode(',',$tp['generos']??'')));
    $precio=$tp['precio_desde']?number_format((float)$tp['precio_desde'],0,',','.'):'' ;
    $tipoServ=htmlspecialchars($tp['tipo_servicio']??'');
}
$titulo = htmlspecialchars($titulo);
$subtitulo = htmlspecialchars($subtitulo);
$descripcion = htmlspecialchars($descripcion);

if ($tipo === 'empresa') {
    $tipoLabel = '🏢 Empresa';
} elseif ($tipo === 'negocio') {
    $tipoLabel = '🏪 Negocio local';
} elseif ($tp && !empty($tp['tipo_servicio'])) {
    $tipoLabel = '🎧 Servicios &amp; Eventos';
} else {
    $tipoLabel = '👤 Talento profesional';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $titulo ?> — QuibdóConecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,900;1,9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --v1:#0a4020;--v2:#1a7a3c;--v3:#27a855;--v4:#5dd882;--vlima:#a3f0b5;
      --a1:#b38000;--a2:#d4a017;--a3:#f5c800;--a4:#ffd94d;
      --r2:#0039a6;--r3:#1a56db;--r4:#5b8eff;
      --bg:#060e07;--bg2:#0c1a0e;--bg3:#111f13;
      --card:rgba(15,28,17,.95);--borde:rgba(255,255,255,.09);
      --ink:rgba(255,255,255,.9);--ink2:rgba(255,255,255,.6);--ink3:rgba(255,255,255,.32);
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh}

    /* ── FRANJA + NAVBAR ── */
    .franja{position:fixed;top:0;left:0;right:0;height:3px;display:flex;z-index:999}
    .franja span:nth-child(1){flex:1;background:var(--v3)}
    .franja span:nth-child(2){flex:1;background:var(--a3)}
    .franja span:nth-child(3){flex:1;background:var(--r3)}
    .navbar{position:fixed;top:3px;left:0;width:100%;height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 32px;background:rgba(6,14,7,.95);backdrop-filter:blur(20px);border-bottom:1px solid var(--borde);z-index:998}
    .brand{font-family:'Fraunces',serif;font-size:18px;font-weight:700;color:var(--ink);text-decoration:none}
    .brand em{color:var(--vlima);font-style:normal}
    .nav-r{display:flex;align-items:center;gap:10px}
    .btn-back{padding:7px 16px;border:1px solid var(--borde);border-radius:20px;color:var(--ink2);font-size:13px;font-weight:600;text-decoration:none;transition:all .2s;background:rgba(255,255,255,.04)}
    .btn-back:hover{background:rgba(255,255,255,.09);color:var(--ink)}
    .btn-msg{padding:7px 18px;background:linear-gradient(135deg,var(--v2),var(--v3));color:white;border-radius:20px;font-size:13px;font-weight:700;text-decoration:none;box-shadow:0 3px 10px rgba(39,168,85,.35);transition:all .2s}
    .btn-msg:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(39,168,85,.45)}

    /* ── COVER + HERO ── */
    .cover{height:200px;margin-top:59px;position:relative;overflow:hidden}
    .cover-bg{position:absolute;inset:0}
    .cover-overlay{position:absolute;inset:0;background:rgba(0,0,0,.45)}
    .hero{max-width:960px;margin:0 auto;padding:0 28px}
    .hero-card{background:var(--card);border:1px solid var(--borde);border-radius:24px;margin-top:-70px;position:relative;z-index:10;padding:28px 32px;display:flex;gap:24px;align-items:flex-start;box-shadow:0 8px 40px rgba(0,0,0,.5)}
    @media(max-width:640px){.hero-card{flex-direction:column;align-items:center;text-align:center;padding:22px 20px;margin-top:-50px}}
    .hero-av{width:90px;height:90px;border-radius:50%;flex-shrink:0;object-fit:cover;border:3px solid rgba(163,240,181,.3);box-shadow:0 4px 20px rgba(0,0,0,.4)}
    .hero-av-ph{width:90px;height:90px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-size:32px;font-weight:900;color:white;border:3px solid rgba(255,255,255,.2);box-shadow:0 4px 20px rgba(0,0,0,.4)}
    .hero-info{flex:1;min-width:0}
    .hero-tipo-tag{display:inline-block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:var(--ink3);margin-bottom:8px}
    .hero-nombre{font-family:'Fraunces',serif;font-size:clamp(20px,4vw,30px);font-weight:900;color:var(--ink);line-height:1.1;margin-bottom:5px}
    .hero-sub{font-size:15px;color:var(--vlima);font-weight:600;margin-bottom:5px}
    .hero-loc{font-size:13px;color:var(--ink3);margin-bottom:14px}
    .hero-badges{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
    .hb{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
    .hb-v{background:rgba(163,240,181,.12);border:1px solid rgba(163,240,181,.3);color:var(--vlima)}
    .hb-top{background:rgba(255,77,77,.1);border:1px solid rgba(255,77,77,.25);color:#ff7a7a}
    .hb-prem{background:rgba(255,211,77,.1);border:1px solid rgba(255,211,77,.25);color:var(--a4)}
    .hb-dest{background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.3);color:#a5b4fc}
    .hero-actions{display:flex;flex-wrap:wrap;gap:8px}
    .btn-contratar{padding:11px 24px;background:linear-gradient(135deg,var(--a1),var(--a3));color:#111;border:none;border-radius:24px;font-size:14px;font-weight:800;cursor:pointer;text-decoration:none;transition:all .2s;box-shadow:0 4px 14px rgba(212,160,23,.35)}
    .btn-contratar:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(212,160,23,.45)}
    .btn-wa{padding:11px 22px;background:#25d366;color:#fff;border-radius:24px;font-size:14px;font-weight:700;text-decoration:none;transition:transform .2s}
    .btn-wa:hover{transform:translateY(-1px)}
    .btn-web{padding:11px 20px;border:1px solid var(--borde);color:var(--ink2);border-radius:24px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s;background:rgba(255,255,255,.04)}
    .btn-web:hover{border-color:var(--vlima);color:var(--vlima)}

    /* ── STATS BAR ── */
    .stats-bar{max-width:960px;margin:16px auto 0;padding:0 28px}
    .stats-inner{background:var(--card);border:1px solid var(--borde);border-radius:16px;padding:16px 28px;display:flex;gap:0;flex-wrap:wrap}
    .sbar-item{flex:1;min-width:100px;text-align:center;padding:8px 16px;position:relative}
    .sbar-item:not(:last-child)::after{content:'';position:absolute;right:0;top:20%;bottom:20%;width:1px;background:var(--borde)}
    .sbar-val{font-family:'Fraunces',serif;font-size:22px;font-weight:900;color:var(--vlima)}
    .sbar-lab{font-size:11px;color:var(--ink3);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}

    /* ── MAIN LAYOUT ── */
    .perfil-wrap{max-width:960px;margin:24px auto 60px;padding:0 28px;display:grid;grid-template-columns:1fr 290px;gap:20px}
    @media(max-width:700px){.perfil-wrap{grid-template-columns:1fr}}

    /* ── SECCIÓN ── */
    .psec{background:var(--card);border:1px solid var(--borde);border-radius:20px;overflow:hidden;margin-bottom:18px;transition:box-shadow .25s}
    .psec:hover{box-shadow:0 6px 28px rgba(0,0,0,.35)}
    .psec-head{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between}
    .psec-tit{font-family:'Fraunces',serif;font-size:15px;font-weight:700;color:var(--ink)}
    .psec-count{font-size:11px;color:var(--ink3);font-weight:600;background:rgba(255,255,255,.05);padding:3px 9px;border-radius:12px;border:1px solid var(--borde)}
    .psec-body{padding:16px 24px 20px}

    /* ── ACERCA ── */
    .desc-txt{font-size:15px;color:var(--ink2);line-height:1.75}

    /* ── GALERÍA ── */
    .gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
    .gal-item{position:relative;border-radius:12px;overflow:hidden;background:rgba(255,255,255,.04);aspect-ratio:1;cursor:pointer;border:1px solid var(--borde);transition:all .25s}
    .gal-item:hover{transform:scale(1.03);border-color:rgba(163,240,181,.3);box-shadow:0 4px 16px rgba(0,0,0,.4)}
    .gal-item img,.gal-item video{width:100%;height:100%;object-fit:cover}
    .gal-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.35);font-size:26px}
    .gal-label{position:absolute;bottom:0;left:0;right:0;padding:5px 8px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

    /* ── HABILIDADES / TAGS ── */
    .tags{display:flex;flex-wrap:wrap;gap:7px}
    .tag{padding:6px 13px;background:rgba(39,168,85,.1);border:1px solid rgba(39,168,85,.2);border-radius:20px;font-size:12px;font-weight:700;color:var(--vlima)}
    .tag-gen{background:rgba(255,211,77,.08);border-color:rgba(255,211,77,.2);color:var(--a4)}
    .tag-neg{background:rgba(255,255,255,.05);border-color:var(--borde);color:var(--ink2)}

    /* ── INFO TABLE ── */
    .info-table{width:100%;font-size:14px;border-collapse:collapse}
    .info-table td{padding:10px 0;color:var(--ink2)}
    .info-table td:first-child{color:var(--ink3);width:42%;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.5px}
    .info-table tr:not(:last-child) td{border-bottom:1px solid rgba(255,255,255,.05)}
    .info-table td:last-child{font-weight:700;color:var(--ink)}

    /* ── PRECIO BOX ── */
    .precio-hero{background:linear-gradient(135deg,var(--v1),#0d4f28);border:1px solid rgba(163,240,181,.15);border-radius:18px;padding:22px;margin-bottom:14px;text-align:center}
    .precio-label{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--ink3);margin-bottom:6px}
    .precio-val{font-family:'Fraunces',serif;font-size:36px;font-weight:900;color:white;line-height:1}
    .precio-unidad{font-size:13px;color:rgba(255,255,255,.6);margin-top:4px}

    /* ── SIDEBAR CARD ── */
    .side-card{background:var(--card);border:1px solid var(--borde);border-radius:18px;padding:20px;margin-bottom:14px}
    .side-tit{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--ink3);margin-bottom:14px}
    .side-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;font-size:13px}
    .side-row:not(:last-child){border-bottom:1px solid rgba(255,255,255,.05)}
    .side-lab{color:var(--ink3)}
    .side-val{font-weight:700;color:var(--ink)}

    /* ── CTA REGISTRO ── */
    .cta-reg{background:linear-gradient(135deg,var(--v1) 0%,#0d4f28 100%);border:1px solid rgba(163,240,181,.15);border-radius:18px;padding:22px;text-align:center;margin-bottom:14px}
    .cta-reg-ico{font-size:30px;margin-bottom:10px}
    .cta-reg h4{font-family:'Fraunces',serif;font-size:16px;color:white;margin-bottom:6px}
    .cta-reg p{font-size:12px;color:rgba(255,255,255,.6);margin-bottom:14px;line-height:1.5}
    .btn-reg{display:inline-block;padding:10px 22px;background:var(--vlima);color:#0a3320;border-radius:20px;font-weight:800;font-size:13px;text-decoration:none;transition:all .2s}
    .btn-reg:hover{background:white;transform:translateY(-1px)}

    /* ── ES MI PERFIL BANNER ── */
    .miperfil-banner{background:rgba(163,240,181,.07);border:1px solid rgba(163,240,181,.2);border-radius:14px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;flex-wrap:wrap}
    .miperfil-txt{font-size:13px;color:var(--vlima);font-weight:600}
    .btn-editar{padding:7px 18px;background:var(--v3);color:white;border-radius:20px;font-size:13px;font-weight:700;text-decoration:none;border:none;cursor:pointer}

    /* ── LIGHTBOX ── */
    #lbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.94);z-index:9999;align-items:center;justify-content:center;flex-direction:column;padding:20px}
    #lbox img{max-width:90vw;max-height:85vh;border-radius:12px;object-fit:contain}
    #lbox-tit{color:rgba(255,255,255,.6);margin-top:10px;font-size:13px}
    .lbox-close{position:absolute;top:20px;right:24px;font-size:26px;color:rgba(255,255,255,.7);background:none;border:none;cursor:pointer}
    .lbox-close:hover{color:white}

    /* ── FOOTER ── */
    footer{background:var(--bg2);border-top:1px solid var(--borde);color:var(--ink3);text-align:center;padding:24px;font-size:13px;margin-top:20px}
    footer span{color:var(--vlima)}
  </style>
</head>
<body>

<div class="franja"><span></span><span></span><span></span></div>

<nav class="navbar">
  <a href="index.html" class="brand">Quibdó<em>Conecta</em></a>
  <div class="nav-r">
    <a href="javascript:history.back()" class="btn-back">← Volver</a>
    <?php if (!$esMiPerfil): ?>
      <a href="chat.php?con=<?= $uid ?>" class="btn-msg">💬 Mensaje</a>
    <?php else: ?>
      <a href="dashboard.php" class="btn-msg">🏠 Mi panel</a>
    <?php endif; ?>
  </div>
</nav>

<!-- COVER -->
<div class="cover">
  <div class="cover-bg" style="background:<?= htmlspecialchars($colorGrad) ?>;filter:blur(0px)"></div>
  <div class="cover-overlay"></div>
</div>

<!-- HERO CARD -->
<div class="hero">
  <div class="hero-card">
    <?php if ($logoUrl): ?>
      <img src="<?= $logoUrl ?>" alt="<?= $titulo ?>" class="hero-av">
    <?php else: ?>
      <div class="hero-av-ph" style="background:<?= htmlspecialchars($colorGrad) ?>"><?= $inicial ?></div>
    <?php endif; ?>

    <div class="hero-info">
      <span class="hero-tipo-tag"><?= $tipoLabel ?></span>
      <h1 class="hero-nombre"><?= $titulo ?></h1>
      <?php if ($subtitulo): ?><p class="hero-sub"><?= $subtitulo ?></p><?php endif; ?>
      <p class="hero-loc">📍 <?= $ciudad ?></p>

      <div class="hero-badges">
        <?php if ($tieneVerif):    ?><span class="hb hb-v">✅ Verificado</span><?php endif; ?>
        <?php if ($tieneTop):      ?><span class="hb hb-top">👑 Top</span><?php endif; ?>
        <?php if ($tienePremium):  ?><span class="hb hb-prem">⭐ Premium</span><?php endif; ?>
        <?php if ($tieneDestacado):?><span class="hb hb-dest">🏅 Destacado</span><?php endif; ?>
      </div>

      <?php if (!$esMiPerfil): ?>
      <div class="hero-actions">
        <?php if ($whatsapp): ?>
          <a href="https://wa.me/<?= $whatsapp ?>?text=Hola!+Vi+tu+perfil+en+QuibdóConecta" target="_blank" class="btn-wa">💬 WhatsApp</a>
        <?php endif; ?>
        <?php if (!empty($precio)): ?>
          <a href="chat.php?con=<?= $uid ?>" class="btn-contratar">💰 Contratar</a>
        <?php else: ?>
          <a href="chat.php?con=<?= $uid ?>" class="btn-msg" style="padding:11px 22px">💬 Enviar mensaje</a>
        <?php endif; ?>
        <?php if ($sitioWeb): ?>
          <a href="<?= $sitioWeb ?>" target="_blank" class="btn-web">🌐 Sitio web</a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="hero-actions">
        <a href="dashboard.php" class="btn-web">✏️ Editar mi perfil</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- STATS BAR -->
<div class="stats-bar">
  <div class="stats-inner">
    <div class="sbar-item">
      <div class="sbar-val"><?= $vistasTotal > 0 ? ($vistasTotal > 999 ? number_format($vistasTotal/1000,1).'K' : $vistasTotal) : '—' ?></div>
      <div class="sbar-lab">Vistas al perfil</div>
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

<!-- MAIN CONTENT -->
<div class="perfil-wrap">

  <!-- COLUMNA PRINCIPAL -->
  <div>

    <?php if ($esMiPerfil): ?>
    <div class="miperfil-banner">
      <span class="miperfil-txt">👁 Así ven tu perfil otros usuarios</span>
      <a href="dashboard.php" class="btn-editar">Editar perfil →</a>
    </div>
    <?php endif; ?>

    <!-- ACERCA DE -->
    <?php if ($descripcion): ?>
    <div class="psec">
      <div class="psec-head"><span class="psec-tit">📝 Acerca de</span></div>
      <div class="psec-body"><p class="desc-txt"><?= nl2br($descripcion) ?></p></div>
    </div>
    <?php endif; ?>

    <!-- GALERÍA -->
    <?php if (!empty($galeria)): ?>
    <div class="psec">
      <div class="psec-head">
        <span class="psec-tit">📸 Galería de evidencias</span>
        <span class="psec-count"><?= count($galeria) ?> items</span>
      </div>
      <div class="psec-body">
        <div class="gal-grid">
          <?php foreach($galeria as $gi):
            $isVid = $gi['tipo'] === 'video';
            $yid   = $isVid && $gi['url_video'] ? ytId($gi['url_video']) : '';
            $thumb = $yid ? "https://img.youtube.com/vi/{$yid}/mqdefault.jpg" : ($gi['archivo'] ? 'uploads/galeria/'.htmlspecialchars($gi['archivo']) : '');
          ?>
          <div class="gal-item" onclick="<?= $isVid && $gi['url_video'] ? "window.open('".htmlspecialchars($gi['url_video'])."','_blank')" : ($gi['archivo'] ? "abrirLbox('uploads/galeria/".htmlspecialchars($gi['archivo'])."','".htmlspecialchars($gi['titulo']??'')."')" : '') ?>">
            <?php if ($thumb): ?><img src="<?= $thumb ?>" alt="<?= htmlspecialchars($gi['titulo']??'') ?>" loading="lazy"><?php endif; ?>
            <?php if ($isVid && !$thumb && $gi['archivo']): ?><video src="uploads/galeria/<?= htmlspecialchars($gi['archivo']) ?>" preload="none"></video><?php endif; ?>
            <?php if ($isVid): ?><div class="gal-play">▶️</div><?php endif; ?>
            <?php if ($gi['titulo']): ?><div class="gal-label"><?= htmlspecialchars($gi['titulo']) ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- HABILIDADES -->
    <?php if (!empty($skills)): ?>
    <div class="psec">
      <div class="psec-head"><span class="psec-tit">⚡ Habilidades</span></div>
      <div class="psec-body">
        <div class="tags">
          <?php foreach($skills as $s): ?><span class="tag"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- GÉNEROS / ESPECIALIDADES -->
    <?php if (!empty($generos)): ?>
    <div class="psec">
      <div class="psec-head"><span class="psec-tit">🎵 Géneros &amp; Especialidades</span></div>
      <div class="psec-body">
        <div class="tags">
          <?php foreach($generos as $g): ?><span class="tag tag-gen"><?= htmlspecialchars($g) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- INFO EMPRESA -->
    <?php if ($tipo === 'empresa' && $ep): ?>
    <div class="psec">
      <div class="psec-head"><span class="psec-tit">🏢 Información empresarial</span></div>
      <div class="psec-body">
        <table class="info-table">
          <?php if ($nit):       ?><tr><td>NIT</td><td><?= $nit ?></td></tr><?php endif; ?>
          <?php if ($subtitulo): ?><tr><td>Sector</td><td><?= $subtitulo ?></td></tr><?php endif; ?>
          <?php if ($telefonoPub):?><tr><td>Teléfono</td><td><?= $telefonoPub ?></td></tr><?php endif; ?>
          <tr><td>Ciudad</td><td>📍 <?= $ciudad ?></td></tr>
          <?php if ($sitioWeb):  ?><tr><td>Sitio web</td><td><a href="<?= $sitioWeb ?>" target="_blank" style="color:var(--vlima);font-weight:700">🌐 Ver sitio →</a></td></tr><?php endif; ?>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- INFO NEGOCIO -->
    <?php if ($tipo === 'negocio' && $np): ?>
    <div class="psec">
      <div class="psec-head"><span class="psec-tit">🏪 Datos del negocio</span></div>
      <div class="psec-body">
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

  <!-- COLUMNA LATERAL -->
  <div>

    <!-- PRECIO -->
    <?php if ($precio): ?>
    <div class="precio-hero">
      <div class="precio-label">Precio desde</div>
      <div class="precio-val">$<?= $precio ?></div>
      <div class="precio-unidad"><?= $tipoServ ?: 'por evento / servicio' ?></div>
    </div>
    <a href="chat.php?con=<?= $uid ?>" class="btn-contratar" style="display:block;text-align:center;margin-bottom:14px;text-decoration:none">
      💰 Solicitar cotización
    </a>
    <?php endif; ?>

    <!-- WHATSAPP -->
    <?php if ($whatsapp): ?>
    <a href="https://wa.me/<?= $whatsapp ?>?text=Hola!+Vi+tu+perfil+en+QuibdóConecta" target="_blank"
       style="display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;background:#25d366;color:#fff;border-radius:14px;font-weight:700;font-size:14px;text-decoration:none;margin-bottom:10px">
      💬 Contactar por WhatsApp
    </a>
    <?php endif; ?>

    <?php if (!$esMiPerfil): ?>
    <a href="chat.php?con=<?= $uid ?>"
       style="display:flex;align-items:center;justify-content:center;gap:8px;padding:13px;border:1.5px solid rgba(163,240,181,.3);color:var(--vlima);border-radius:14px;font-weight:700;font-size:14px;text-decoration:none;margin-bottom:18px;transition:all .2s;background:rgba(163,240,181,.04)"
       onmouseover="this.style.background='rgba(163,240,181,.1)'" onmouseout="this.style.background='rgba(163,240,181,.04)'">
      ✉️ Enviar mensaje por chat
    </a>
    <?php endif; ?>

    <!-- INFO RÁPIDA -->
    <div class="side-card">
      <div class="side-tit">ℹ️ Info rápida</div>
      <div class="side-row"><span class="side-lab">Ciudad</span><span class="side-val">📍 <?= $ciudad ?></span></div>
      <div class="side-row"><span class="side-lab">Tipo</span><span class="side-val"><?= $tipoLabel ?></span></div>
      <div class="side-row"><span class="side-lab">Verificado</span><span class="side-val"><?= $tieneVerif ? '<span style="color:var(--vlima)">✅ Sí</span>' : '—' ?></span></div>
      <?php if (!empty($galeria)): ?>
      <div class="side-row"><span class="side-lab">Evidencias</span><span class="side-val">📸 <?= count($galeria) ?></span></div>
      <?php endif; ?>
      <?php if ($vistasTotal > 0): ?>
      <div class="side-row"><span class="side-lab">Vistas</span><span class="side-val">👁 <?= number_format($vistasTotal) ?></span></div>
      <?php endif; ?>
    </div>

    <!-- CTA REGISTRO si no logueado -->
    <?php if (!$visitorId): ?>
    <div class="cta-reg">
      <div class="cta-reg-ico">🌿</div>
      <h4>¿Eres talento o empresa del Chocó?</h4>
      <p>Regístrate gratis y conecta con oportunidades laborales de la región.</p>
      <a href="registro.php" class="btn-reg">Registrarme gratis →</a>
    </div>
    <?php endif; ?>

    <!-- EXPLORAR MÁS -->
    <div class="side-card" style="text-align:center">
      <div class="side-tit">🌿 Explorar más</div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="busqueda_talentos.php" style="padding:10px;background:rgba(163,240,181,.07);border:1px solid rgba(163,240,181,.15);border-radius:12px;color:var(--vlima);font-size:13px;font-weight:700;text-decoration:none;transition:background .2s" onmouseover="this.style.background='rgba(163,240,181,.13)'" onmouseout="this.style.background='rgba(163,240,181,.07)'">🌟 Ver talentos</a>
        <a href="empresas.php" style="padding:10px;background:rgba(91,142,255,.07);border:1px solid rgba(91,142,255,.15);border-radius:12px;color:var(--r4);font-size:13px;font-weight:700;text-decoration:none;transition:background .2s" onmouseover="this.style.background='rgba(91,142,255,.13)'" onmouseout="this.style.background='rgba(91,142,255,.07)'">🏢 Ver empresas</a>
        <a href="servicios.php" style="padding:10px;background:rgba(255,211,77,.06);border:1px solid rgba(255,211,77,.15);border-radius:12px;color:var(--a4);font-size:13px;font-weight:700;text-decoration:none;transition:background .2s" onmouseover="this.style.background='rgba(255,211,77,.12)'" onmouseout="this.style.background='rgba(255,211,77,.06)'">🎧 Ver servicios</a>
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
  <p>© 2026 <span>QuibdóConecta</span> — Conectando el talento del Chocó con el mundo.</p>
</footer>

<script>
  function abrirLbox(url, tit) {
    document.getElementById('lbox-img').src = url;
    document.getElementById('lbox-tit').textContent = tit || '';
    document.getElementById('lbox').style.display = 'flex';
  }
  function cerrarLbox() { document.getElementById('lbox').style.display = 'none'; }
  document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarLbox(); });
</script>
</body>
</html>