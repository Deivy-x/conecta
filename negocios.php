<?php
// ============================================================
// negocios.php — Directorio de Negocios & Emprendedores del Chocó
// Migración exacta de la sección "locales" del index.html
// BUILD: v20260320
// ============================================================
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ── Detectar tipo de negocio automáticamente ──────────────
function detectarTipoNegocio($categoria, $descripcion) {
  $texto = strtolower($categoria . ' ' . $descripcion);
  if (preg_match('/(caraño|carano|c\.c\.|centro comercial|local \d|piso \d)/i', $texto)) return 'cc';
  return 'emp';
}

// ── Detectar emoji por categoría ──────────────────────────
function emojiCategoria($cat) {
  $cat = strtolower($cat);
  if (preg_match('/(gastronomia|gastronomía|comida|restaurante|asado|pollo|panaderia|pasteleria)/i', $cat)) return '🍽️';
  if (preg_match('/(ropa|moda|accesor|calzado|boutique)/i', $cat))       return '👗';
  if (preg_match('/(barber|estética|estetica|cabello|belleza|peluquer)/i', $cat)) return '✂️';
  if (preg_match('/(salud|farmacia|drogueria|droguería|medicament)/i', $cat)) return '💊';
  if (preg_match('/(ferreteria|ferretería|construcc|materiales)/i', $cat)) return '🔧';
  if (preg_match('/(tecnolog|celular|computo|computador|electronica)/i', $cat)) return '💻';
  if (preg_match('/(joyeria|joyería|reloj|bisuter)/i', $cat))            return '💍';
  if (preg_match('/(libreria|librería|papeleria|papelería|escolar)/i', $cat)) return '📚';
  if (preg_match('/(artesania|artesanía|manualidad|arte)/i', $cat))      return '🎨';
  if (preg_match('/(ecoturismo|turismo|viaje|hotel|hostal)/i', $cat))    return '🌿';
  if (preg_match('/(musica|música|instrumento|dj|sonido)/i', $cat))      return '🎵';
  return '🏪';
}

$dbNegocios = [];
if (file_exists(__DIR__ . '/Php/db.php')) {
  try {
    require_once __DIR__ . '/Php/db.php';
    require_once __DIR__ . '/Php/badges_helper.php';
    $db = getDB();

    // ── Tabla: negocios_locales (créala con el SQL al final) ──
    // Misma lógica de MAX(id) que talentos.php y empresas.php
    $stmt = $db->query("
      SELECT u.id, u.nombre, u.ciudad, u.verificado, u.badges_custom,
             nl.nombre_negocio, nl.categoria, nl.descripcion,
             nl.logo, nl.banner, nl.ubicacion, nl.whatsapp,
             nl.tipo_negocio, nl.nombre_cc, nl.local_numero,
             nl.avatar_color, nl.visible_admin, nl.destacado,
             nl.tags, nl.precio_desde
      FROM usuarios u
      INNER JOIN negocios_locales nl ON nl.id = (
          SELECT MAX(id) FROM negocios_locales
          WHERE usuario_id = u.id
            AND visible = 1
            AND visible_admin = 1
      )
      WHERE u.activo = 1
      ORDER BY nl.destacado DESC, u.verificado DESC, u.id ASC
      LIMIT 50
    ");
    $rawNegocios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dedup en PHP — idéntico a talentos.php
    $vistos = [];
    foreach ($rawNegocios as $row) {
      if (!isset($vistos[$row['id']])) {
        $vistos[$row['id']] = true;
        $dbNegocios[] = $row;
      }
    }

    // Agregar badges
    foreach ($dbNegocios as &$n) {
      $badges = getBadgesUsuario($db, (int)$n['id']);
      $badgesExtras = array_values(array_filter($badges, fn($b) => ($b['tipo'] ?? '') !== 'verificacion'));
      $n['badges_html']     = renderBadges($badgesExtras, 'small');
      $n['tiene_verificado'] = (bool)$n['verificado'] || tieneBadge($badges, 'Verificado');
      $n['tiene_premium']    = tieneBadge($badges, 'Premium');
      $n['tiene_destacado']  = tieneBadge($badges, 'Destacado') || (int)($n['destacado'] ?? 0);
      $n['tiene_top']        = tieneBadge($badges, 'Top');
    }
  } catch (Exception $e) {
    $dbNegocios = [];
  }
}

$totalCC  = count(array_filter($dbNegocios, fn($n) => ($n['tipo_negocio'] ?? 'emp') === 'cc'));
$totalEmp = count(array_filter($dbNegocios, fn($n) => ($n['tipo_negocio'] ?? 'emp') === 'emp'));
$totalAll = count($dbNegocios);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Negocios & Emprendedores – Quibdó Conecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --verde:    #1f9d55;
      --verde2:   #2ecc71;
      --verde-o:  #edfaf3;
      --dorado:   #d4a017;
      --dorado2:  #f0c040;
      --dorado-o: #fef9e7;
      --azul:     #1a3a6b;
      --azul2:    #2563eb;
      --oscuro:   #0a0f1e;
      --gris:     #f4f6f8;
      --gris2:    #e8ecf0;
      --texto:    #111;
      /* Paleta chocoana */
      --choco-oro:    #c8860a;
      --choco-selva:  #0a3320;
      --choco-rio:    #1a5276;
      --choco-tierra: #6d4c2a;
      --choco-flor:   #c0392b;
      --choco-cielo:  #1abc9c;
    }

    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box }
    html { scroll-behavior:smooth }
    body { font-family:'DM Sans',Arial,sans-serif; background:var(--gris); color:var(--texto); overflow-x:hidden }

    /* ── NAVBAR ── */
    .navbar {
      position:fixed; top:0; left:0; width:100%; height:78px;
      display:flex; align-items:center; justify-content:space-between;
      padding:0 48px; background:#fff;
      border-bottom:1px solid rgba(0,0,0,.08);
      box-shadow:0 2px 12px rgba(0,0,0,.05); z-index:1000; transition:box-shadow .3s;
    }
    .navbar.abajo { box-shadow:0 4px 20px rgba(0,0,0,.12) }
    .nav-left { display:flex; align-items:center; gap:12px }
    .logo { width:52px; height:auto; filter:drop-shadow(0 1px 1px rgba(0,0,0,.15)) }
    .brand { font-size:22px; font-weight:800; color:#111 }
    .brand span { color:var(--verde) }
    .nav-center { display:flex; align-items:center; gap:22px; flex:1; justify-content:center }
    .nav-center a { color:#333; text-decoration:none; font-size:15px; font-weight:500; padding:6px 4px; position:relative }
    .nav-center a::after { content:""; position:absolute; left:0; bottom:-6px; width:0%; height:2px; background:var(--verde); transition:width .3s }
    .nav-center a:hover::after, .nav-center a.active::after { width:100% }
    .nav-center .highlight { background:linear-gradient(135deg,var(--verde),var(--verde2)); color:white!important; padding:9px 20px; border-radius:25px; font-weight:600; box-shadow:0 4px 12px rgba(31,157,85,.35) }
    .nav-center .highlight::after { display:none }
    .nav-right { display:flex; align-items:center; gap:14px }
    .login { color:var(--verde); text-decoration:none; font-size:14.5px; font-weight:600; padding:8px 18px; border:2px solid var(--verde); border-radius:30px; transition:all .3s }
    .login:hover { background:var(--verde); color:white }
    .register { background:var(--verde); color:white; padding:9px 20px; border-radius:25px; text-decoration:none; font-weight:600; font-size:14.5px; box-shadow:0 4px 12px rgba(31,157,85,.35); transition:all .2s }
    .register:hover { background:#166f3d }
    .hamburger { display:none; flex-direction:column; gap:5px; cursor:pointer; background:none; border:none; padding:4px }
    .hamburger span { display:block; width:26px; height:2.5px; background:#111; border-radius:4px; transition:all .3s }
    .hamburger.open span:nth-child(1) { transform:translateY(7.5px) rotate(45deg) }
    .hamburger.open span:nth-child(2) { opacity:0; transform:scaleX(0) }
    .hamburger.open span:nth-child(3) { transform:translateY(-7.5px) rotate(-45deg) }
    .mobile-menu { display:none; position:fixed; top:78px; left:0; width:100%; background:white; border-bottom:1px solid rgba(0,0,0,.08); box-shadow:0 12px 32px rgba(0,0,0,.12); flex-direction:column; padding:20px 24px; gap:6px; z-index:999 }
    .mobile-menu.open { display:flex }
    .mobile-menu a { color:#333; text-decoration:none; font-size:16px; font-weight:500; padding:12px 0; border-bottom:1px solid rgba(0,0,0,.06) }
    .mobile-auth { display:flex; gap:12px; margin-top:14px }
    .mobile-auth a { flex:1; text-align:center; padding:11px; border-radius:25px; font-weight:600; font-size:15px; text-decoration:none }
    .mobile-auth .m-login { border:2px solid var(--verde); color:var(--verde) }
    .mobile-auth .m-reg { background:var(--verde); color:white }

    /* ── HERO CHOCOANO ── */
    .hero-negocios {
      padding:150px 48px 90px;
      background:
        linear-gradient(160deg, rgba(10,51,32,.92) 0%, rgba(26,82,118,.85) 60%, rgba(10,15,30,.95) 100%),
        url('Imagenes/quibdo 3.jpg') center/cover no-repeat;
      text-align:center; position:relative; overflow:hidden;
    }
    .hero-negocios::before {
      content:''; position:absolute; inset:0;
      background:
        radial-gradient(ellipse at 20% 80%, rgba(212,160,23,.25) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 20%, rgba(31,157,85,.2)  0%, transparent 50%);
    }
    /* Franja tricolor chocoana */
    .hero-negocios::after {
      content:''; position:absolute; bottom:0; left:0; width:100%; height:5px;
      background:linear-gradient(90deg, var(--verde) 33.3%, var(--dorado) 33.3% 66.6%, var(--choco-flor) 66.6%);
    }
    .hero-content { position:relative; z-index:2; max-width:820px; margin:0 auto }
    .hero-badge {
      display:inline-flex; align-items:center; gap:8px;
      background:rgba(212,160,23,.2); border:1px solid rgba(240,192,64,.4);
      color:#f0c040; font-size:13px; font-weight:700;
      padding:7px 22px; border-radius:30px; margin-bottom:24px; letter-spacing:.5px;
    }
    .hero-negocios h1 {
      font-family:'Syne',sans-serif; font-size:58px; font-weight:900;
      color:white; line-height:1.05; margin-bottom:20px;
    }
    .hero-negocios h1 .acento-dorado { color:var(--dorado2) }
    .hero-negocios h1 .acento-verde  { color:#4ade80 }
    .hero-negocios > div > p {
      font-size:18px; color:rgba(255,255,255,.75);
      margin-bottom:40px; line-height:1.65;
    }

    /* Buscador hero */
    .negocio-search {
      display:flex; align-items:center; background:white; border-radius:50px;
      padding:8px; max-width:680px; margin:0 auto 36px;
      box-shadow:0 20px 50px rgba(0,0,0,.3);
    }
    .negocio-search .sf { display:flex; align-items:center; gap:10px; padding:12px 18px; flex:1 }
    .negocio-search .sf .icon { font-size:18px; opacity:.5 }
    .negocio-search input { border:none; outline:none; font-size:15px; width:100%; font-family:'DM Sans',sans-serif; color:#222; background:transparent }
    .negocio-search .divider { width:1px; height:34px; background:rgba(0,0,0,.1); flex-shrink:0 }
    .negocio-search button {
      background:linear-gradient(135deg,var(--dorado),var(--dorado2));
      color:#111; border:none; border-radius:40px; padding:14px 28px;
      font-size:15px; font-weight:700; cursor:pointer; font-family:'DM Sans',sans-serif;
      transition:all .2s; white-space:nowrap;
    }
    .negocio-search button:hover { opacity:.9; transform:scale(1.03) }
    .hero-links { display:flex; justify-content:center; gap:24px; flex-wrap:wrap }
    .hero-link { color:rgba(255,255,255,.7); text-decoration:none; font-size:14px; font-weight:500; border-bottom:1px solid rgba(255,255,255,.25); padding-bottom:2px; transition:color .2s }
    .hero-link:hover { color:#f0c040; border-color:#f0c040 }

    /* ── STATS BAND ── */
    .stats-band {
      background:var(--choco-selva);
      display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
      text-align:center; padding:50px 48px;
      border-bottom:4px solid var(--dorado);
    }
    .stats-band .s h3 { font-family:'Syne',sans-serif; font-size:38px; font-weight:900; color:var(--dorado2) }
    .stats-band .s p { font-size:13px; color:rgba(255,255,255,.6); margin-top:5px; font-weight:600 }

    /* ── SECCIÓN PRINCIPAL ── */
    .negocios-section {
      padding:100px 48px;
      background:linear-gradient(160deg,#fff8ee 0%,#fef3d0 100%);
      position:relative; overflow:hidden;
    }
    /* Franja tricolor */
    .negocios-section::before {
      content:''; position:absolute; top:0; left:0; width:100%; height:5px;
      background:linear-gradient(90deg,var(--verde) 33.3%,var(--dorado) 33.3% 66.6%,var(--choco-flor) 66.6%);
    }
    /* Decoración selva */
    .negocios-section::after {
      content:'🌿'; position:absolute; right:-20px; top:60px;
      font-size:200px; opacity:.04; pointer-events:none; line-height:1;
    }

    .section-header { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:16px; flex-wrap:wrap; gap:12px }
    .section-label {
      display:inline-block; font-size:12px; font-weight:800;
      text-transform:uppercase; letter-spacing:1.2px;
      color:var(--dorado); margin-bottom:8px;
    }
    .section-header h2 { font-family:'Syne',sans-serif; font-size:38px; font-weight:900; line-height:1.1 }
    .ver-todos { color:var(--verde); font-weight:700; text-decoration:none; font-size:14px; white-space:nowrap }
    .ver-todos:hover { text-decoration:underline }

    /* ── TABS ── */
    .negocios-tabs { display:flex; gap:10px; margin-bottom:48px; flex-wrap:wrap }
    .negocio-tab {
      padding:10px 24px; border-radius:30px; font-size:14px; font-weight:700;
      cursor:pointer; border:2px solid transparent; transition:all .25s; text-decoration:none;
    }
    .negocio-tab.verde  { background:var(--verde-o); color:var(--verde); border-color:var(--verde) }
    .negocio-tab.dorado { background:var(--dorado-o); color:var(--dorado); border-color:var(--dorado) }
    .negocio-tab.azul   { background:#e8eeff; color:var(--azul2); border-color:var(--azul2) }
    .negocio-tab:hover { opacity:.8; transform:translateY(-1px) }
    .negocio-tab.dim { opacity:.45 }

    /* ── GRID ── */
    .negocios-grid {
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(270px,1fr));
      gap:26px;
    }

    /* ── TARJETA LOCAL (C.C. El Caraño) ── */
    .local-card {
      background:white; border-radius:22px; overflow:hidden;
      border:1px solid rgba(0,0,0,.08); transition:all .35s;
      cursor:pointer; box-shadow:0 4px 16px rgba(0,0,0,.06);
    }
    .local-card:hover {
      transform:translateY(-8px);
      box-shadow:0 24px 52px rgba(37,99,235,.18);
      border-color:rgba(37,99,235,.25);
    }
    .local-cc-header {
      padding:14px 20px 10px; display:flex; align-items:center; gap:10px;
      border-bottom:1px solid rgba(0,0,0,.06);
      background:linear-gradient(135deg,#f0f4ff 0%,#e8eeff 100%);
    }
    .cc-logo {
      width:32px; height:32px; border-radius:8px; object-fit:contain; flex-shrink:0;
      background:rgba(37,99,235,.1); display:flex; align-items:center; justify-content:center; font-size:18px;
    }
    .cc-nombre { font-size:11px; font-weight:700; color:var(--azul2); text-transform:uppercase; letter-spacing:.8px }
    .local-body { padding:20px }
    .local-top { display:flex; align-items:flex-start; gap:14px; margin-bottom:12px }
    .local-logo {
      width:54px; height:54px; border-radius:14px; object-fit:cover; flex-shrink:0;
      background:var(--gris); border:1px solid rgba(0,0,0,.08);
      display:flex; align-items:center; justify-content:center; font-size:26px; overflow:hidden;
    }
    .local-logo img { width:100%; height:100%; object-fit:cover; border-radius:14px }
    .local-info h3 { font-size:16px; font-weight:700; margin-bottom:3px }
    .local-info .local-cat { font-size:12px; color:var(--azul2); font-weight:600; margin-bottom:2px }
    .local-info .local-piso { font-size:12px; color:#999 }
    .local-desc { font-size:13px; color:#666; line-height:1.55; margin-bottom:14px }
    .local-badge-row { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px }
    .local-badge { font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; background:var(--verde-o); color:var(--verde) }
    .local-badge.azul { background:#e8eeff; color:var(--azul2) }
    .local-badge.dorado { background:var(--dorado-o); color:var(--dorado) }
    .local-badge.rojo { background:#fee2e2; color:#b91c1c }
    .local-btn {
      display:block; text-align:center; background:var(--azul2);
      color:white; padding:10px; border-radius:20px; font-weight:600;
      font-size:13px; text-decoration:none; transition:all .25s;
    }
    .local-btn:hover { background:var(--azul); transform:translateY(-1px) }

    /* ── TARJETA EMPRENDEDOR (diseño chocoano vibrante) ── */
    .emp-card {
      background:white; border-radius:22px; overflow:hidden;
      border:1px solid rgba(0,0,0,.08); transition:all .35s;
      cursor:pointer; box-shadow:0 4px 16px rgba(0,0,0,.06);
    }
    .emp-card:hover {
      transform:translateY(-8px);
      box-shadow:0 24px 52px rgba(212,160,23,.22);
      border-color:var(--dorado2);
    }
    .emp-header-ind {
      padding:14px 20px 10px; display:flex; align-items:center; gap:10px;
      border-bottom:1px solid rgba(0,0,0,.06);
      background:linear-gradient(135deg,#fff8ee,var(--dorado-o));
    }
    .emp-cc-logo {
      width:32px; height:32px; border-radius:8px; background:var(--dorado-o);
      display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;
    }
    .emp-cc-nombre { font-size:11px; font-weight:700; color:var(--dorado); text-transform:uppercase; letter-spacing:.8px }
    /* Banner imagen del negocio */
    .emp-banner {
      width:100%; height:130px; background:linear-gradient(135deg,var(--dorado-o),#fdeaa8);
      display:flex; align-items:center; justify-content:center; font-size:52px;
      position:relative; overflow:hidden;
    }
    .emp-banner img { width:100%; height:130px; object-fit:cover; display:block }
    /* Raya decorativa chocoana sobre el banner */
    .emp-banner::after {
      content:''; position:absolute; bottom:0; left:0; right:0; height:3px;
      background:linear-gradient(90deg,var(--verde),var(--dorado),var(--choco-flor));
    }
    .emp-body { padding:20px }
    .emp-top { display:flex; align-items:center; gap:12px; margin-bottom:10px }
    .emp-avatar {
      width:46px; height:46px; border-radius:50%; object-fit:cover; flex-shrink:0;
      background:linear-gradient(135deg,var(--dorado),var(--dorado2));
      display:flex; align-items:center; justify-content:center;
      font-size:20px; color:#111; font-weight:700;
      border:2px solid var(--dorado-o); overflow:hidden;
    }
    .emp-avatar img { width:100%; height:100%; border-radius:50%; object-fit:cover }
    .emp-info h3 { font-size:15px; font-weight:700; margin-bottom:2px }
    .emp-info .emp-tipo { font-size:12px; color:var(--dorado); font-weight:600 }
    .emp-desc { font-size:13px; color:#666; line-height:1.55; margin-bottom:12px }
    .emp-precio { font-size:13px; color:var(--verde); font-weight:700; margin-bottom:10px }
    .emp-tags { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px }
    .emp-tags span { font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; background:var(--dorado-o); color:var(--dorado) }
    .emp-btn {
      display:block; text-align:center;
      background:linear-gradient(135deg,var(--dorado),var(--dorado2));
      color:#111; padding:10px; border-radius:20px; font-weight:700;
      font-size:13px; text-decoration:none; transition:all .25s;
    }
    .emp-btn:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(212,160,23,.35) }

    /* ── SIN RESULTADOS ── */
    .no-results {
      grid-column:1/-1; text-align:center; padding:70px 20px; color:#999;
    }

    /* ── MODAL NEGOCIO ── */
    .modal-overlay {
      display:none; position:fixed; inset:0; background:rgba(0,0,0,.6);
      z-index:2000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px);
    }
    .modal-overlay.open { display:flex }
    .modal-box {
      background:white; border-radius:24px; max-width:560px; width:100%;
      box-shadow:0 30px 80px rgba(0,0,0,.22); animation:fadeUp .3s ease both;
      position:relative; max-height:90vh; overflow-y:auto;
    }
    @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    .modal-close { position:absolute; top:18px; right:20px; background:none; border:none; font-size:22px; cursor:pointer; color:#888 }
    .modal-close:hover { color:#333 }
    .modal-banner {
      width:100%; height:160px; background:linear-gradient(135deg,var(--dorado-o),#fdeaa8);
      display:flex; align-items:center; justify-content:center; font-size:64px;
      border-radius:24px 24px 0 0; overflow:hidden; position:relative;
    }
    .modal-banner::after {
      content:''; position:absolute; bottom:0; left:0; right:0; height:4px;
      background:linear-gradient(90deg,var(--verde),var(--dorado),var(--choco-flor));
    }
    .modal-banner img { width:100%; height:100%; object-fit:cover }
    .modal-body { padding:28px }
    .modal-negocio-top { display:flex; gap:16px; align-items:flex-start; margin-bottom:16px }
    .modal-logo-wrap {
      width:68px; height:68px; border-radius:14px; background:linear-gradient(135deg,var(--dorado),var(--dorado2));
      display:flex; align-items:center; justify-content:center; font-size:28px; flex-shrink:0; overflow:hidden;
    }
    .modal-logo-wrap img { width:100%; height:100%; object-fit:cover; border-radius:14px }
    .modal-body h2 { font-family:'Syne',sans-serif; font-size:20px; font-weight:800; margin-bottom:4px }
    .modal-cat { color:var(--dorado); font-weight:700; font-size:13px; margin-bottom:4px }
    .modal-piso { color:#999; font-size:12px; margin-bottom:14px }
    .modal-desc-txt { font-size:14px; color:#555; line-height:1.7; margin-bottom:18px }
    .modal-badges { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:20px }
    .modal-wa {
      display:flex; align-items:center; justify-content:center; gap:10px;
      width:100%; padding:14px; background:linear-gradient(135deg,#25d366,#128c7e);
      color:white; border:none; border-radius:14px; font-size:15px; font-weight:700;
      font-family:'DM Sans',sans-serif; cursor:pointer; text-decoration:none;
      box-shadow:0 6px 20px rgba(37,211,102,.35); transition:transform .2s;
    }
    .modal-wa:hover { transform:translateY(-2px) }

    /* ── CTA REGISTRO ── */
    .negocios-cta {
      display:flex; gap:16px; flex-wrap:wrap; margin-top:56px;
      align-items:center; background:white; border-radius:22px;
      padding:28px 32px; box-shadow:0 6px 24px rgba(0,0,0,.08);
      border:1px solid rgba(0,0,0,.07); position:relative; overflow:hidden;
    }
    .negocios-cta::before {
      content:''; position:absolute; top:0; left:0; right:0; height:4px;
      background:linear-gradient(90deg,var(--verde),var(--dorado),var(--choco-flor));
    }
    .negocios-cta .lc-icono { font-size:42px; flex-shrink:0 }
    .negocios-cta h4 { font-family:'Syne',sans-serif; font-size:18px; font-weight:800; margin-bottom:5px }
    .negocios-cta p { font-size:13px; color:#666 }
    .lc-btns { display:flex; gap:10px; margin-left:auto; flex-wrap:wrap }
    .lc-btn-verde {
      background:var(--verde); color:white; padding:11px 22px; border-radius:25px;
      text-decoration:none; font-weight:700; font-size:13px; transition:all .2s;
      box-shadow:0 4px 12px rgba(31,157,85,.3);
    }
    .lc-btn-verde:hover { background:#166f3d; transform:translateY(-1px) }
    .lc-btn-dorado {
      background:linear-gradient(135deg,var(--dorado),var(--dorado2)); color:#111;
      padding:11px 22px; border-radius:25px; text-decoration:none;
      font-weight:700; font-size:13px; transition:all .2s;
      box-shadow:0 4px 12px rgba(212,160,23,.3);
    }
    .lc-btn-dorado:hover { transform:translateY(-1px) }

    /* ── FOOTER ── */
    footer {
      background:#0a0f1e; border-top:1px solid rgba(255,255,255,.06);
      color:rgba(255,255,255,.5); text-align:center; padding:28px 48px; font-size:14px;
    }
    footer span { color:var(--verde2) }

    /* ── SCROLL REVEAL ── */
    .reveal { opacity:0; transform:translateY(36px); transition:opacity .65s ease,transform .65s ease }
    .reveal.visible { opacity:1; transform:translateY(0) }

    /* ── RESPONSIVE ── */
    @media(max-width:1200px) { .hero-negocios h1{font-size:46px} .navbar{padding:0 32px} }
    @media(max-width:900px)  { .hero-negocios{padding:120px 32px 70px} .hero-negocios h1{font-size:38px} }
    @media(max-width:768px) {
      .navbar{padding:0 20px} .nav-center,.nav-right{display:none} .hamburger{display:flex}
      .hero-negocios{padding:110px 20px 70px} .hero-negocios h1{font-size:28px;line-height:1.15}
      .negocio-search{flex-wrap:wrap;border-radius:18px;padding:10px}
      .negocio-search .sf{width:100%} .negocio-search .divider{width:100%;height:1px}
      .negocio-search button{width:100%;border-radius:12px}
      .stats-band{padding:36px 20px} .negocios-section{padding:60px 20px}
      .negocios-grid{grid-template-columns:1fr}
      .modal-negocio-top{flex-direction:column;gap:10px}
      .lc-btns{margin-left:0;width:100%} .lc-btn-verde,.lc-btn-dorado{flex:1;text-align:center}
    }
    @media(max-width:480px) {
      .hero-negocios h1{font-size:24px} .section-header h2{font-size:28px}
      .stats-band{grid-template-columns:1fr 1fr} .negocios-cta{flex-direction:column;align-items:flex-start}
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar" id="navbar">
  <div class="nav-left">
    <img src="Imagenes/Quibdo.png" alt="Quibdó Conecta" class="logo">
    <span class="brand">Quibdó<span>Conecta</span></span>
  </div>
  <nav class="nav-center">
    <a href="index.html">Inicio</a>
    <a href="Empleo.html">Empleos</a>
    <a href="talentos.php">Talento</a>
    <a href="empresas.php">Empresas</a>
    <a href="negocios.php" class="active">Negocios</a>
      </nav>
  <div class="nav-right">
    <a href="inicio_sesion.php" class="login">Iniciar sesión</a>
    <a href="registro.php" class="register">Registrarse</a>
  </div>
  <button class="hamburger" id="hamburger" aria-label="Menú">
    <span></span><span></span><span></span>
  </button>
</header>

<div class="mobile-menu" id="mobileMenu">
  <a href="index.html">🏠 Inicio</a>
  <a href="Empleo.html">💼 Empleos</a>
  <a href="talentos.php">🌟 Talento</a>
  <a href="empresas.php">🏢 Empresas</a>
  <a href="negocios.php">🏪 Negocios</a>
  
  <div class="mobile-auth">
    <a href="inicio_sesion.php" class="m-login">Iniciar sesión</a>
    <a href="registro.php" class="m-reg">Registrarse</a>
  </div>
</div>

<!-- HERO -->
<section class="hero-negocios">
  <div class="hero-content reveal">
    <span class="hero-badge">🏪 Directorio Local del Chocó</span>
    <h1>
      Los <span class="acento-dorado">negocios</span> y<br>
      <span class="acento-verde">emprendedores</span> del Chocó
    </h1>
    <p>Locales del C.C. El Caraño, tiendas del barrio y emprendedores independientes de todo el Chocó — en un solo lugar.</p>
    <div class="negocio-search">
      <div class="sf">
        <span class="icon">🔍</span>
        <input type="text" id="searchNombre" placeholder="Busca un negocio, servicio o categoría…" autocomplete="off">
      </div>
      <div class="divider"></div>
      <div class="sf">
        <span class="icon">📍</span>
        <input type="text" id="searchUbicacion" placeholder="Barrio o ciudad">
      </div>
      <button id="searchBtn">Buscar</button>
    </div>
    <div class="hero-links">
      <a href="registro.php" class="hero-link">✨ Registrar mi negocio gratis</a>
      <a href="#negocios" class="hero-link">👇 Ver directorio completo</a>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats-band">
  <div class="s reveal">
    <h3 id="stat-negocios"><?= $totalAll ?: '+50' ?></h3>
    <p>Negocios registrados</p>
  </div>
  <div class="s reveal">
    <h3 id="stat-cc"><?= $totalCC ?: '+20' ?></h3>
    <p>Locales C.C. El Caraño</p>
  </div>
  <div class="s reveal">
    <h3 id="stat-emp"><?= $totalEmp ?: '+30' ?></h3>
    <p>Emprendedores independientes</p>
  </div>
  <div class="s reveal">
    <h3>+15</h3>
    <p>Categorías de servicio</p>
  </div>
</div>

<!-- SECCIÓN NEGOCIOS -->
<section class="negocios-section" id="negocios">
  <div class="section-header reveal">
    <div>
      <span class="section-label">🏪 Directorio Local</span>
      <h2>Negocios & Emprendedores del Chocó</h2>
      <p style="color:#666;font-size:15px;margin-top:8px">Locales del C.C. El Caraño y emprendedores independientes de Quibdó</p>
    </div>
    <a href="registro.php" class="ver-todos">Registrar mi negocio →</a>
  </div>

  <!-- TABS — idénticos a index.html -->
  <div class="negocios-tabs reveal">
    <a href="#" class="negocio-tab azul"   onclick="filtrarNegocios('cc',this);return false;">🏬 C.C. El Caraño <span id="cnt-cc"  style="font-size:11px;opacity:.7">(<?= $totalCC ?>)</span></a>
    <a href="#" class="negocio-tab dorado" onclick="filtrarNegocios('emp',this);return false;">🏪 Negocios independientes <span id="cnt-emp" style="font-size:11px;opacity:.7">(<?= $totalEmp ?>)</span></a>
    <a href="#" class="negocio-tab verde"  onclick="filtrarNegocios('todos',this);return false;">Ver todos</a>
  </div>

  <!-- GRID -->
  <div class="negocios-grid" id="negociosGrid">

    <?php if (!empty($dbNegocios)): ?>
      <?php foreach ($dbNegocios as $neg):
        $nombreNeg = htmlspecialchars(trim($neg['nombre_negocio'] ?? $neg['nombre'] ?? 'Negocio'));
        $cat       = htmlspecialchars($neg['categoria'] ?? '');
        $desc      = htmlspecialchars($neg['descripcion'] ?? '');
        $emoji     = emojiCategoria($cat);
        $tipo      = $neg['tipo_negocio'] ?? 'emp';
        $nombreCC  = htmlspecialchars($neg['nombre_cc'] ?? 'C.C. El Caraño');
        $localNum  = htmlspecialchars($neg['local_numero'] ?? '');
        $ubicacion = htmlspecialchars($neg['ubicacion'] ?? '');
        $logo      = !empty($neg['logo'])   ? 'uploads/logos/' . htmlspecialchars($neg['logo'])   : '';
        $banner    = !empty($neg['banner']) ? 'uploads/banners/' . htmlspecialchars($neg['banner']) : '';
        $tags      = array_filter(array_map('trim', explode(',', $neg['tags'] ?? '')));
        $precio    = $neg['precio_desde'] ?? '';
        $whatsapp  = preg_replace('/\D/','',$neg['whatsapp'] ?? '');
        $grd       = htmlspecialchars($neg['avatar_color'] ?? 'linear-gradient(135deg,#d4a017,#f0c040)');

        // Badge principal
        if ($neg['tiene_top'])        $badgePrincipal = '<span class="local-badge rojo">👑 Top</span>';
        elseif ($neg['tiene_premium']) $badgePrincipal = '<span class="local-badge dorado">⭐ Premium</span>';
        elseif ($neg['tiene_destacado']) $badgePrincipal = '<span class="local-badge dorado">⭐ Destacado</span>';
        elseif ($neg['tiene_verificado']) $badgePrincipal = '<span class="local-badge">✅ Verificado</span>';
        else $badgePrincipal = '';
      ?>

        <?php if ($tipo === 'cc'): ?>
          <!-- ══ TARJETA LOCAL C.C. ══ -->
          <div class="local-card reveal" id="u<?= $neg['id'] ?>" data-tipo="cc"
               data-nombre="<?= $nombreNeg ?>" data-cat="<?= $cat ?>" data-ubicacion="<?= $ubicacion ?>"
               data-desc="<?= $desc ?>" data-emoji="<?= $emoji ?>" data-logo="<?= $logo ?>"
               data-banner="<?= $banner ?>" data-tags="<?= htmlspecialchars($neg['tags'] ?? '') ?>"
               data-wa="<?= $whatsapp ?>" data-precio="<?= $precio ?>" data-grad="<?= $grd ?>"
               data-piso="<?= htmlspecialchars($localNum) ?>">
            <div class="local-cc-header">
              <div class="cc-logo">🏬</div>
              <span class="cc-nombre"><?= $nombreCC ?></span>
            </div>
            <div class="local-body">
              <div class="local-top">
                <div class="local-logo" style="background:<?= $grd ?>">
                  <?php if ($logo): ?><img src="<?= $logo ?>" alt="<?= $nombreNeg ?>"><?php else: ?><?= $emoji ?><?php endif; ?>
                </div>
                <div class="local-info">
                  <h3><?= $nombreNeg ?></h3>
                  <p class="local-cat"><?= $emoji ?> <?= $cat ?></p>
                  <p class="local-piso">📍 <?= $localNum ?: $ubicacion ?></p>
                </div>
              </div>
              <p class="local-desc"><?= mb_substr($desc,0,100) ?><?= mb_strlen($desc) > 100 ? '…' : '' ?></p>
              <div class="local-badge-row">
                <?= $badgePrincipal ?>
                <?php if (!empty($neg['badges_html'])): ?><?= $neg['badges_html'] ?><?php endif; ?>
                <?php foreach(array_slice($tags,0,2) as $tag): ?>
                  <span class="local-badge azul"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
              </div>
              <button class="local-btn" onclick="abrirModal(this.closest('.local-card'))">Ver local y contactar</button>
            </div>
          </div>

        <?php else: ?>
          <!-- ══ TARJETA EMPRENDEDOR ══ -->
          <div class="emp-card reveal" id="u<?= $neg['id'] ?>" data-tipo="emp"
               data-nombre="<?= $nombreNeg ?>" data-cat="<?= $cat ?>" data-ubicacion="<?= $ubicacion ?>"
               data-desc="<?= $desc ?>" data-emoji="<?= $emoji ?>" data-logo="<?= $logo ?>"
               data-banner="<?= $banner ?>" data-tags="<?= htmlspecialchars($neg['tags'] ?? '') ?>"
               data-wa="<?= $whatsapp ?>" data-precio="<?= $precio ?>" data-grad="<?= $grd ?>">
            <div class="emp-header-ind">
              <div class="emp-cc-logo">🏪</div>
              <span class="emp-cc-nombre">Negocio Independiente</span>
            </div>
            <?php if ($banner): ?>
              <div class="emp-banner"><img src="<?= $banner ?>" alt="<?= $nombreNeg ?>"></div>
            <?php else: ?>
              <div class="emp-banner" style="background:<?= $grd ?>"><?= $emoji ?></div>
            <?php endif; ?>
            <div class="emp-body">
              <div class="emp-top">
                <div class="emp-avatar" style="background:<?= $grd ?>">
                  <?php if ($logo): ?><img src="<?= $logo ?>" alt="<?= $nombreNeg ?>"><?php else: ?><?= $emoji ?><?php endif; ?>
                </div>
                <div class="emp-info">
                  <h3><?= $nombreNeg ?></h3>
                  <p class="emp-tipo"><?= $emoji ?> <?= $cat ?></p>
                </div>
              </div>
              <?php if ($ubicacion): ?><p style="font-size:12px;color:#999;margin-bottom:8px">📍 <?= $ubicacion ?></p><?php endif; ?>
              <p class="emp-desc"><?= mb_substr($desc,0,100) ?><?= mb_strlen($desc) > 100 ? '…' : '' ?></p>
              <?php if ($precio): ?><p class="emp-precio">Desde $<?= number_format($precio,0,',','.') ?></p><?php endif; ?>
              <div class="emp-tags">
                <?= $badgePrincipal ?>
                <?php foreach(array_slice($tags,0,3) as $tag): ?>
                  <span><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
              </div>
              <button class="emp-btn" onclick="abrirModal(this.closest('.emp-card'))">Ver negocio y contactar</button>
            </div>
          </div>
        <?php endif; ?>

      <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($dbNegocios)): ?>
      <div class="no-results">
        <div style="font-size:60px;margin-bottom:16px">🏪</div>
        <p style="font-size:16px;font-weight:700;color:#555;margin-bottom:8px">Aún no hay negocios registrados</p>
        <p style="font-size:14px;margin-bottom:20px">¡Sé el primero en publicar tu negocio del Chocó!</p>
        <a href="registro.php" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,var(--dorado),var(--dorado2));color:#111;border-radius:30px;text-decoration:none;font-weight:700">🏪 Registrar mi negocio</a>
      </div>
    <?php endif; ?>

  </div><!-- /negociosGrid -->

  <!-- CTA REGISTRO -->
  <div class="negocios-cta reveal">
    <span class="lc-icono">🌿</span>
    <div>
      <h4>¿Tienes un local o emprendimiento en el Chocó?</h4>
      <p>Regístrate gratis y llega a miles de personas. Sin comisiones para empezar. Desde Quibdó hasta el último rincón del Chocó.</p>
    </div>
    <div class="lc-btns">
      <a href="registro.php" class="lc-btn-verde">🏬 Registrar mi local</a>
      <a href="registro.php" class="lc-btn-dorado">🌟 Registrar mi emprendimiento</a>
    </div>
  </div>

</section>

<!-- MODAL DETALLE NEGOCIO -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box">
    <button class="modal-close" id="modalClose">✕</button>
    <div class="modal-banner" id="mBanner"></div>
    <div class="modal-body">
      <div class="modal-negocio-top">
        <div class="modal-logo-wrap" id="mLogo"></div>
        <div>
          <h2 id="mNombre"></h2>
          <p class="modal-cat"  id="mCat"></p>
          <p class="modal-piso" id="mPiso"></p>
        </div>
      </div>
      <p class="modal-desc-txt" id="mDesc"></p>
      <div class="modal-badges" id="mTags"></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a id="mWa" href="#" class="modal-wa" target="_blank">
          <span>💬</span><span>Contactar por WhatsApp</span>
        </a>
        <a href="#" id="mBtnPerfilN"
           style="display:inline-flex;align-items:center;gap:5px;padding:12px 18px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:30px;color:rgba(255,255,255,.85);text-decoration:none;font-size:13px;font-weight:700"
           onmouseover="this.style.background='rgba(255,255,255,.18)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
          🏪 Ver perfil completo
        </a>
      </div>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <p>© 2026 <span>QuibdóConecta</span> — Conectando el talento del Chocó con el mundo.</p>
</footer>

<script>
  // NAVBAR SCROLL
  window.addEventListener('scroll', () => {
    document.getElementById('navbar').classList.toggle('abajo', window.scrollY > 50);
  });

  // HAMBURGER
  const ham = document.getElementById('hamburger');
  const mob = document.getElementById('mobileMenu');
  ham.addEventListener('click', () => { ham.classList.toggle('open'); mob.classList.toggle('open'); });
  document.addEventListener('click', e => {
    if (!ham.contains(e.target) && !mob.contains(e.target)) {
      ham.classList.remove('open'); mob.classList.remove('open');
    }
  });

  // ── FILTRAR — idéntico a filtrarLocales() del index ──
  function filtrarNegocios(tipo, btn) {
    document.querySelectorAll('.negocio-tab').forEach(b => b.classList.add('dim'));
    btn.classList.remove('dim');
    document.querySelectorAll('#negociosGrid [data-tipo]').forEach(card => {
      card.style.display = (tipo === 'todos' || card.dataset.tipo === tipo) ? '' : 'none';
    });
    aplicarBusqueda();
  }

  // ── BÚSQUEDA ──
  let textoBusq = '';
  function aplicarBusqueda() {
    if (!textoBusq) return;
    document.querySelectorAll('#negociosGrid [data-tipo]').forEach(c => {
      if (c.style.display === 'none') return;
      const txt = (c.dataset.nombre + ' ' + c.dataset.cat + ' ' + c.dataset.ubicacion).toLowerCase();
      c.style.display = txt.includes(textoBusq) ? '' : 'none';
    });
  }
  function buscar() {
    const n = document.getElementById('searchNombre').value.trim().toLowerCase();
    const u = document.getElementById('searchUbicacion').value.trim().toLowerCase();
    textoBusq = (n + ' ' + u).trim();
    document.querySelectorAll('#negociosGrid [data-tipo]').forEach(c => {
      const txt = (c.dataset.nombre + ' ' + c.dataset.cat + ' ' + c.dataset.ubicacion).toLowerCase();
      c.style.display = (!textoBusq || txt.includes(textoBusq)) ? '' : 'none';
    });
    document.getElementById('negocios').scrollIntoView({ behavior:'smooth' });
  }
  document.getElementById('searchBtn').addEventListener('click', buscar);
  ['searchNombre','searchUbicacion'].forEach(id => {
    document.getElementById(id).addEventListener('keydown', e => { if(e.key==='Enter') buscar(); });
  });

  // ── MODAL ──
  const overlay = document.getElementById('modalOverlay');
  document.getElementById('modalClose').addEventListener('click', () => overlay.classList.remove('open'));
  overlay.addEventListener('click', e => { if(e.target===overlay) overlay.classList.remove('open'); });
  document.addEventListener('keydown', e => { if(e.key==='Escape') overlay.classList.remove('open'); });

  function abrirModal(card) {
    const d = card.dataset;
    // Banner
    const mBanner = document.getElementById('mBanner');
    if (d.banner) {
      mBanner.innerHTML = `<img src="${d.banner}" alt="">`;
    } else {
      mBanner.style.background = d.grad || 'linear-gradient(135deg,#d4a017,#f0c040)';
      mBanner.innerHTML = `<span style="font-size:64px">${d.emoji || '🏪'}</span>`;
    }
    // Logo
    const mLogo = document.getElementById('mLogo');
    mLogo.style.background = d.grad || 'linear-gradient(135deg,#d4a017,#f0c040)';
    mLogo.innerHTML = d.logo
      ? `<img src="${d.logo}" alt="">`
      : `<span style="font-size:28px">${d.emoji || '🏪'}</span>`;
    // Info
    document.getElementById('mNombre').textContent = d.nombre || '';
    document.getElementById('mCat').textContent    = (d.emoji||'🏪') + ' ' + (d.cat||'');
    document.getElementById('mPiso').textContent   = d.piso ? '📍 ' + d.piso : (d.ubicacion ? '📍 ' + d.ubicacion : '');
    document.getElementById('mDesc').textContent   = d.desc || '';
    // Tags
    const tags = (d.tags||'').split(',').filter(t=>t.trim());
    document.getElementById('mTags').innerHTML = tags.map(t=>`<span class="local-badge azul">${t.trim()}</span>`).join('');
    // WhatsApp
    const wa = document.getElementById('mWa');
    const num = d.wa || '';
    if (num) {
      wa.href = `https://wa.me/57${num}?text=Hola%2C%20vi%20tu%20negocio%20en%20Quibdó%20Conecta%20y%20quiero%20información`;
      wa.style.display = 'flex';
    } else {
      wa.style.display = 'none';
    }
    // Botón Ver perfil completo
    const uid = d.uid || '';
    const bpN = document.getElementById('mBtnPerfilN');
    if (bpN) bpN.href = uid ? `perfil.php?id=${uid}&tipo=negocio` : '#';
    if (uid) {
      const fd = new FormData();
      fd.append('_action','registrar_vista'); fd.append('usuario_id',uid); fd.append('seccion','negocios');
      fetch('dashboard.php',{method:'POST',body:fd}).catch(()=>{});
    }
    overlay.classList.add('open');
  }

  // SCROLL REVEAL BIDIRECCIONAL
  const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity = '1'; e.target.style.transform = 'translateY(0)';
        e.target.classList.add('visible');
      } else {
        if (e.target.getBoundingClientRect().top < 0) {
          e.target.style.opacity = '0'; e.target.style.transform = 'translateY(24px)';
          e.target.classList.remove('visible');
        }
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.local-card,.emp-card,.reveal').forEach(el => {
    el.style.opacity = '0'; el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
  });
</script>

<!-- Widget de sesión activa — QuibdóConecta -->
<script src="js/sesion_widget.js"></script>
</body>
</html>