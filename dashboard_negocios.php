<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once __DIR__ . '/Php/db.php';
if (file_exists(__DIR__ . '/Php/planes_helper.php')) require_once __DIR__ . '/Php/planes_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: inicio_sesion.php'); exit;
}
$db = getDB();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
if (!$usuario || $usuario['tipo'] !== 'negocio') {
    header('Location: dashboard.php'); exit;
}

// ── POST ACTIONS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['_action'] ?? '';

    if ($action === 'editar_negocio') {
        $nombreNeg   = trim($_POST['nombre_negocio'] ?? '');
        $categoria   = trim($_POST['categoria']      ?? '');
        $descripcion = trim($_POST['descripcion']    ?? '');
        $direccion   = trim($_POST['direccion']      ?? '');
        $barrio      = trim($_POST['barrio']         ?? '');
        $ciudad      = trim($_POST['ciudad']         ?? '');
        $municipio   = trim($_POST['municipio']      ?? '');
        $whatsapp    = trim($_POST['whatsapp']       ?? '');
        $instagram   = trim($_POST['instagram']      ?? '');
        $horario     = trim($_POST['horario']        ?? '');
        $sitio_web   = trim($_POST['sitio_web']      ?? '');

        if (!$nombreNeg) { echo json_encode(['ok'=>false,'msg'=>'El nombre del negocio es obligatorio.']); exit; }

        $db->prepare("UPDATE usuarios SET nombre=?, telefono=?, ciudad=? WHERE id=?")
           ->execute([$nombreNeg, $whatsapp, $ciudad, $usuario['id']]);

        $existe = $db->prepare("SELECT id FROM negocios_locales WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
        $existe->execute([$usuario['id']]);
        $fila = $existe->fetchColumn();

        if ($fila) {
            $db->prepare("UPDATE negocios_locales SET
                nombre_negocio=?, categoria=?, descripcion=?, direccion=?, barrio=?,
                municipio=?, whatsapp=?, instagram=?, horario=?, sitio_web=?,
                actualizado_en=NOW()
              WHERE usuario_id=? ORDER BY id DESC LIMIT 1")
               ->execute([$nombreNeg, $categoria, $descripcion, $direccion, $barrio,
                          $municipio, $whatsapp, $instagram, $horario, $sitio_web, $usuario['id']]);
        } else {
            $db->prepare("INSERT INTO negocios_locales
                (usuario_id, nombre_negocio, categoria, descripcion, direccion, barrio,
                 municipio, whatsapp, instagram, horario, sitio_web)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$usuario['id'], $nombreNeg, $categoria, $descripcion, $direccion,
                          $barrio, $municipio, $whatsapp, $instagram, $horario, $sitio_web]);
        }
        // Releer foto actual para devolverla al frontend y que no se pierda del DOM
        $fotoStmt = $db->prepare('SELECT foto FROM usuarios WHERE id=? LIMIT 1');
        $fotoStmt->execute([$usuario['id']]);
        $fotoActualResp = $fotoStmt->fetchColumn() ?: '';
        echo json_encode(['ok'=>true,'nombre_negocio'=>$nombreNeg,'categoria'=>$categoria,'ciudad'=>$ciudad,'foto'=>$fotoActualResp]);
        exit;
    }

    if ($action === 'toggle_visibilidad') {
        $visible = (int)($_POST['visible'] ?? 1);
        try {
            $db->prepare("UPDATE negocios_locales SET visible=? WHERE usuario_id=? ORDER BY id DESC LIMIT 1")
               ->execute([$visible, $usuario['id']]);
        } catch(Exception $e) {}
        echo json_encode(['ok'=>true,'visible'=>$visible]);
        exit;
    }

    if ($action === 'subir_foto') {
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== 0) { echo json_encode(['ok'=>false,'msg'=>'No se recibió imagen.']); exit; }
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'])) { echo json_encode(['ok'=>false,'msg'=>'Solo JPG, PNG o WEBP.']); exit; }
        if ($_FILES['foto']['size'] > 2*1024*1024) { echo json_encode(['ok'=>false,'msg'=>'Máximo 2 MB.']); exit; }
        require_once __DIR__ . '/Php/cloudinary_upload.php';
        $result = cloudinary_upload($_FILES['foto']['tmp_name'], 'quibdoconecta/negocios');
        if (!$result['ok']) { echo json_encode(['ok'=>false,'msg'=>$result['msg']]); exit; }
        $db->prepare("UPDATE usuarios SET foto=? WHERE id=?")->execute([$result['url'], $usuario['id']]);
        echo json_encode(['ok'=>true,'foto'=>$result['url']]);
        exit;
    }

    if ($action === 'eliminar_foto') {
        $db->prepare("UPDATE usuarios SET foto='' WHERE id=?")->execute([$usuario['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'subir_banner') {
        if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== 0) { echo json_encode(['ok'=>false,'msg'=>'No se recibió imagen.']); exit; }
        $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'])) { echo json_encode(['ok'=>false,'msg'=>'Solo JPG, PNG o WEBP.']); exit; }
        if ($_FILES['banner']['size'] > 5*1024*1024) { echo json_encode(['ok'=>false,'msg'=>'Máximo 5 MB.']); exit; }
        try { $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}
        require_once __DIR__ . '/Php/cloudinary_upload.php';
        $result = cloudinary_upload($_FILES['banner']['tmp_name'], 'quibdoconecta/banners');
        if (!$result['ok']) { echo json_encode(['ok'=>false,'msg'=>$result['msg']]); exit; }
        $db->prepare("UPDATE usuarios SET banner=? WHERE id=?")->execute([$result['url'], $usuario['id']]);
        echo json_encode(['ok'=>true,'banner'=>$result['url']]);
        exit;
    }

    if ($action === 'eliminar_banner') {
        try { $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}
        $db->prepare("UPDATE usuarios SET banner='' WHERE id=?")->execute([$usuario['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'eliminar_cuenta') {
        $confirmar = trim($_POST['confirmar'] ?? '');
        if ($confirmar !== $usuario['correo']) { echo json_encode(['ok'=>false,'msg'=>'El correo no coincide.']); exit; }
        try {
            foreach (['negocios_locales','talento_galeria','sesiones','perfil_vistas'] as $tabla) {
                try { $db->prepare("DELETE FROM $tabla WHERE usuario_id=?")->execute([$usuario['id']]); } catch(Exception $e){}
            }
            $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$usuario['id']]);
            $_SESSION = []; session_destroy();
            echo json_encode(['ok'=>true]);
        } catch(Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']); exit;
}

if (isset($_GET['salir'])) {
    if (isset($_COOKIE['qc_remember'])) {
        try { $db->prepare("DELETE FROM sesiones WHERE token=?")->execute([$_COOKIE['qc_remember']]); } catch(Exception $e){}
        setcookie('qc_remember','',time()-3600,'/');
    }
    $_SESSION = []; session_destroy();
    header('Location: inicio_sesion.php'); exit;
}

// ── DATOS ─────────────────────────────────────────────────────
$npStmt = $db->prepare("SELECT * FROM negocios_locales WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
$npStmt->execute([$usuario['id']]);
$np = $npStmt->fetch() ?: [];

try { $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}
$uRe = $db->prepare("SELECT * FROM usuarios WHERE id=?"); $uRe->execute([$usuario['id']]); $uRe = $uRe->fetch();
$bannerUrl = !empty($uRe['banner']) ? htmlspecialchars($uRe['banner']) : '';
$fotoUrl   = !empty($uRe['foto'])   ? htmlspecialchars($uRe['foto'])   : '';

require_once __DIR__ . '/Php/badges_helper.php';
$badgesUsuario  = getBadgesUsuario($db, $usuario['id']);
$badgesHTML     = renderBadges($badgesUsuario);
$tieneVerificado= (bool)($usuario['verificado']??false)||tieneBadge($badgesUsuario,'Verificado')||tieneBadge($badgesUsuario,'Negocio Verificado');
$tienePremium   = tieneBadge($badgesUsuario,'Premium');
$tieneDestacado = tieneBadge($badgesUsuario,'Destacado')||(int)($np['destacado']??0);
$tieneTop       = tieneBadge($badgesUsuario,'Top');

$datosPlan  = function_exists('getDatosPlan') ? getDatosPlan($db,$usuario['id']) : [];
$planActual = $datosPlan['plan'] ?? 'semilla';

$nrChat = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario=? AND leido=0");
$nrChat->execute([$usuario['id']]); $chatNoLeidos = (int)$nrChat->fetchColumn();

$vistasTotal = 0; $vistas7dias = 0;
try {
    $vt = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=?"); $vt->execute([$usuario['id']]); $vistasTotal=(int)$vt->fetchColumn();
    $v7 = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=? AND creado_en>=DATE_SUB(NOW(),INTERVAL 7 DAY)"); $v7->execute([$usuario['id']]); $vistas7dias=(int)$v7->fetchColumn();
} catch(Exception $e){}

$stmtV = $db->prepare("SELECT estado FROM verificaciones WHERE usuario_id=? ORDER BY creado_en DESC LIMIT 1");
$stmtV->execute([$usuario['id']]); $verifDoc=$stmtV->fetch(); $estadoVerif=$verifDoc?$verifDoc['estado']:null;

// Galería
$galeriaItems=[]; $galeriaTotal=0;
try {
    $gStmt=$db->prepare("SELECT * FROM talento_galeria WHERE usuario_id=? AND activo=1 ORDER BY orden ASC,id ASC"); $gStmt->execute([$usuario['id']]); $galeriaItems=$gStmt->fetchAll(); $galeriaTotal=count($galeriaItems);
} catch(Exception $e){}

$extras=[];
try {
    $solStmt=$db->prepare("SELECT nota_admin FROM solicitudes_ingreso WHERE correo=? ORDER BY creado_en DESC LIMIT 1");
    $solStmt->execute([$usuario['correo']]); $solRow=$solStmt->fetch();
    if ($solRow && !empty($solRow['nota_admin'])) $extras=json_decode($solRow['nota_admin'],true)?:[];
} catch(Exception $e){}

$tipoNegocio = $extras['tipo_negocio_reg'] ?? ($np['tipo']??'');
$esCC = ($tipoNegocio === 'cc');

$nombreNegocio  = htmlspecialchars($np['nombre_negocio'] ?? $extras['nombre_negocio'] ?? $usuario['nombre'] ?? 'Mi Negocio');
$iniciales       = strtoupper(mb_substr($nombreNegocio,0,2));
$categoria       = htmlspecialchars($np['categoria'] ?? $extras['categoria_neg'] ?? '');
$ciudad          = htmlspecialchars($uRe['ciudad']??'');
$whatsapp        = htmlspecialchars($np['whatsapp']??$extras['whatsapp_neg']??'');
$direccion       = htmlspecialchars($np['direccion']??$extras['direccion_neg']??'');
$horario         = htmlspecialchars($np['horario']??'');
$correo          = htmlspecialchars($uRe['correo']);
$fechaRegistro   = date('d \de F Y',strtotime($usuario['creado_en']));
$visibleEnWeb    = (int)($np['visible']??1)&&(int)($np['visible_admin']??1);

$campos = ['nombre_negocio','categoria','descripcion','direccion','whatsapp','horario'];
$llenos = array_filter($campos,fn($c)=>!empty($np[$c]));
$pct = (int)(count($llenos)/count($campos)*100);
if ($fotoUrl)        $pct=min(100,$pct+8);
if ($tieneVerificado)$pct=min(100,$pct+7);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Panel Negocio – QuibdóConecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,900;1,9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root{
      --ink:#0d1a0f;--ink2:#2e5035;--ink3:#5a7a60;
      --bg:#f2f7f3;--card:#fff;--borde:rgba(0,0,0,.08);
      --sombra:0 2px 16px rgba(0,0,0,.06);
      --verde:#1c6b32;--verde2:#2d9e50;--hoja:#4dba74;--lima:#a8e063;
      --dorado:#e6a800;--arena:#fff9e6;
      --azul:#1a56db;--azul2:#3b82f6;--azulc:#eff6ff;
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh}

    /* NAVBAR */
    .navbar{position:sticky;top:0;z-index:200;background:#0a1a0f;display:flex;align-items:center;justify-content:space-between;padding:0 36px;height:56px;border-bottom:1px solid rgba(255,255,255,.06)}
    .nav-marca{display:flex;align-items:center;gap:10px;text-decoration:none}
    .nav-marca img{width:28px}
    .nav-marca-texto{font-family:'Fraunces',serif;font-size:18px;color:white}
    .nav-marca-texto em{color:#4dba74;font-style:italic}
    .nav-links{display:flex;align-items:center;gap:3px}
    .nl{display:flex;align-items:center;gap:5px;padding:6px 12px;border-radius:8px;color:rgba(255,255,255,.5);text-decoration:none;font-size:12.5px;font-weight:600;transition:.2s;position:relative}
    .nl:hover{background:rgba(255,255,255,.07);color:white}
    .nl.on{background:rgba(77,186,116,.15);color:#a3f0b5}
    .nl-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:#e74c3c;border:1.5px solid #0a1a0f}
    .nav-usuario{display:flex;align-items:center;gap:10px}
    .nav-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--verde),var(--verde2));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;color:white;overflow:hidden;flex-shrink:0;cursor:pointer;border:2px solid rgba(77,186,116,.3)}
    .nav-nombre{font-size:13px;font-weight:600;color:rgba(255,255,255,.8);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .nav-salir{padding:5px 12px;border-radius:8px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.5);font-size:12px;font-weight:600;text-decoration:none;transition:.2s}
    .nav-salir:hover{background:rgba(231,76,60,.15);color:#ff8080}

    /* HERO */
    .hero{background:linear-gradient(135deg,#0a2010 0%,#1a4025 60%,#0a2010 100%);padding:44px 36px 32px;position:relative;overflow:hidden}
    .hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(77,186,116,.12) 0%,transparent 60%);pointer-events:none}
    .hero-inner{position:relative;z-index:2;display:flex;align-items:center;gap:22px;flex-wrap:wrap}
    .hero-av{width:70px;height:70px;border-radius:16px;background:linear-gradient(135deg,var(--verde),var(--verde2));display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:white;flex-shrink:0;cursor:pointer;border:3px solid rgba(77,186,116,.3);overflow:hidden;transition:.2s}
    .hero-av:hover{border-color:#4dba74;transform:scale(1.03)}
    .hero-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px}
    .hchip{display:inline-flex;align-items:center;gap:4px;padding:3px 11px;border-radius:20px;font-size:11.5px;font-weight:700}
    .hc-tipo{background:rgba(77,186,116,.18);color:#a3f0b5}
    .hc-cc{background:rgba(245,200,0,.18);color:#ffd94d}
    .hc-veri{background:rgba(16,185,129,.18);color:#6ee7b7}
    .hc-prem{background:rgba(245,158,11,.18);color:#fcd34d}
    .hc-dest{background:rgba(168,85,247,.18);color:#c4b5fd}
    .hc-top{background:rgba(239,68,68,.18);color:#fca5a5}
    .hero-nombre{font-family:'Fraunces',serif;font-size:25px;color:white;margin-bottom:5px}
    .hero-sub{font-size:13px;color:rgba(255,255,255,.5);display:flex;align-items:center;gap:14px;flex-wrap:wrap}
    .hero-stats{display:flex;gap:18px;flex-wrap:wrap;margin-top:18px}
    .hs{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:12px 18px;text-align:center;min-width:100px}
    .hs-val{font-size:20px;font-weight:900;color:white}
    .hs-lab{font-size:10.5px;color:rgba(255,255,255,.4);margin-top:2px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
    .hero-deco{position:absolute;right:36px;bottom:-8px;font-size:110px;opacity:.05;pointer-events:none}

    /* ALERTAS */
    .alerta{display:flex;align-items:center;gap:14px;padding:14px 18px;border-radius:12px;margin-bottom:14px;border:1px solid}
    .alerta .a-ico{font-size:20px;flex-shrink:0}
    .alerta .a-txt{flex:1;font-size:12.5px;line-height:1.5}
    .alerta .a-txt strong{display:block;font-size:13px;font-weight:700;margin-bottom:2px}
    .alerta .a-btn{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;background:var(--verde);color:white}
    .as{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
    .ap{background:#fffbeb;border-color:#fcd34d;color:#92400e}
    .ar{background:#fff1f2;border-color:#fca5a5;color:#991b1b}
    .av{background:#f0fdf4;border-color:#bbf7d0;color:#166534}

    /* GRID */
    .contenido{padding:28px 36px}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .span2{grid-column:span 2}
    .span3{grid-column:span 3}
    .card{background:var(--card);border:1px solid var(--borde);border-radius:16px;padding:20px;box-shadow:var(--sombra)}

    /* MINI STATS */
    .mini{display:flex;align-items:center;gap:12px}
    .m-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
    .ig{background:#dcfce7}.ia{background:#d1fae5}.im{background:#fef9c3}.io{background:#fef3c7}
    .m-val{font-size:24px;font-weight:900;color:var(--ink)}
    .m-lab{font-size:12px;color:var(--ink3);font-weight:600;margin-top:1px}
    .m-sub{font-size:11px;color:var(--verde);font-weight:700;margin-top:2px;cursor:pointer}

    /* ACCIONES RÁPIDAS */
    .ca-tit{font-size:12px;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px}
    .ac-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(105px,1fr));gap:9px}
    .ac{display:flex;flex-direction:column;align-items:center;gap:5px;padding:12px 8px;border-radius:12px;background:var(--bg);border:1px solid var(--borde);text-decoration:none;transition:.2s;position:relative}
    .ac:hover{background:#f0fdf4;border-color:#bbf7d0;transform:translateY(-2px);box-shadow:0 5px 14px rgba(28,107,50,.1)}
    .ac-ico{font-size:20px}
    .ac-tit{font-size:11.5px;font-weight:700;color:var(--ink2);text-align:center}
    .ac-desc{font-size:10.5px;color:var(--ink3);text-align:center}
    .ac-badge{position:absolute;top:-6px;right:-6px;background:#e74c3c;color:white;font-size:9px;font-weight:800;padding:2px 6px;border-radius:10px}

    /* GALERÍA */
    .galeria-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:8px;margin-top:12px}
    .gal-item{aspect-ratio:1;border-radius:10px;overflow:hidden;background:#e5e7eb;cursor:pointer;position:relative}
    .gal-item img{width:100%;height:100%;object-fit:cover}
    .gal-item:hover .gal-del{opacity:1}
    .gal-del{position:absolute;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;opacity:0;transition:.2s;cursor:pointer;font-size:18px}
    .gal-add{aspect-ratio:1;border-radius:10px;border:2px dashed #bbf7d0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;transition:.2s;background:transparent}
    .gal-add:hover{background:#f0fdf4;border-color:#4dba74}
    .gal-add-ico{font-size:22px}
    .gal-add-txt{font-size:10.5px;font-weight:700;color:var(--verde)}

    /* PERFIL CARD */
    .cp-head{display:flex;flex-direction:column;align-items:center;text-align:center;padding-bottom:14px;border-bottom:1px solid var(--borde)}
    .cp-av{width:76px;height:76px;border-radius:16px;background:linear-gradient(135deg,var(--verde),var(--verde2));display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;color:white;margin-bottom:10px;cursor:pointer;border:3px solid rgba(28,107,50,.2);overflow:hidden;transition:.2s}
    .cp-av:hover{border-color:var(--verde)}
    .cp-nom{font-size:16px;font-weight:800;margin-bottom:2px}
    .cp-pro{font-size:12.5px;color:var(--verde);font-weight:600}
    .cp-body{padding:12px 0;display:flex;flex-direction:column;gap:7px;font-size:12.5px;color:var(--ink2)}
    .cp-fil{display:flex;align-items:center;gap:7px}
    .cp-ico{font-size:14px;flex-shrink:0}

    /* TOGGLE */
    .vis-row{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg);border-radius:10px;border:1px solid var(--borde);margin-top:4px}
    .vis-lab{font-size:12.5px;font-weight:700;color:var(--ink2)}
    .vis-sub{font-size:10.5px;color:var(--ink3);margin-top:1px}
    .toggle{position:relative;width:42px;height:22px;cursor:pointer}
    .toggle input{opacity:0;width:0;height:0}
    .toggle-slider{position:absolute;inset:0;border-radius:11px;background:#cbd5e1;transition:.3s}
    .toggle-slider::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;border-radius:50%;background:white;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
    .toggle input:checked + .toggle-slider{background:var(--verde)}
    .toggle input:checked + .toggle-slider::before{transform:translateX(20px)}
    .pv-chip{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px}
    .pv-chip.ok{background:#dcfce7;color:#166534}
    .pv-chip.off{background:#fef9c3;color:#92400e}

    /* PROGRESO */
    .prog-w{margin:10px 0}
    .prog-h{display:flex;justify-content:space-between;font-size:11.5px;font-weight:700;margin-bottom:5px;color:var(--ink3)}
    .prog-t{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden}
    .prog-f{height:100%;background:linear-gradient(90deg,var(--verde),var(--hoja));border-radius:4px;transition:width 1s ease}

    /* BOTONES */
    .btn-edit{width:100%;padding:10px;border-radius:10px;background:linear-gradient(135deg,var(--verde),var(--verde2));color:white;border:none;font-size:12.5px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:12px;transition:.2s;box-shadow:0 3px 10px rgba(28,107,50,.25)}
    .btn-edit:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(28,107,50,.35)}
    .btn-sec{width:100%;padding:10px;border-radius:10px;background:transparent;color:var(--verde);border:2px solid var(--verde);font-size:12.5px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:6px;transition:.2s;text-decoration:none;display:block;text-align:center}
    .btn-sec:hover{background:#f0fdf4}

    /* PLAN CARD */
    .plan-card{background:linear-gradient(135deg,#f0fdf4,#e8f5e9);border:1.5px solid #a5d6a7;border-radius:14px;padding:18px 22px}
    .plan-barra{background:var(--bg);border:1px solid #c5e1a5;border-radius:12px;padding:13px}

    /* BADGES */
    .bdg{display:inline-flex;align-items:center;gap:4px;padding:4px 11px;border-radius:20px;font-size:11.5px;font-weight:700}
    .bdg-v{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
    .bdg-p{background:#fffbeb;color:#92400e;border:1px solid #fcd34d}
    .bdg-d{background:#f3e8ff;color:#6b21a8;border:1px solid #d8b4fe}
    .bdg-t{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}

    /* MODAL */
    .modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:500;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
    .modal-ov.open{display:flex}
    .modal-box{background:var(--card);border-radius:20px;max-width:560px;width:100%;box-shadow:0 30px 80px rgba(0,0,0,.2);animation:fadeUp .3s ease;max-height:90vh;overflow-y:auto;position:relative}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .mcerrar{position:absolute;top:14px;right:16px;background:none;border:none;font-size:18px;cursor:pointer;color:var(--ink3)}
    .mcerrar:hover{color:var(--ink)}
    .modal-pad{padding:28px}
    .mtit{font-family:'Fraunces',serif;font-size:20px;margin-bottom:5px}
    .msub{font-size:12.5px;color:var(--ink3);margin-bottom:18px;line-height:1.5}
    .msec{font-size:11px;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:.7px;margin:14px 0 7px}
    .mmsg{display:none;padding:9px 13px;border-radius:9px;font-size:12.5px;font-weight:700;margin-bottom:12px}
    .mmsg.success{background:#dcfce7;color:#166534}
    .mmsg.error{background:#fee2e2;color:#991b1b}
    .mfila{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:7px}
    .mgr{display:flex;flex-direction:column;gap:4px}
    .mgr.full{grid-column:1/-1}
    .mgr label{font-size:11.5px;font-weight:700;color:var(--ink3)}
    .mgr input,.mgr select,.mgr textarea{border:1.5px solid var(--borde);border-radius:9px;padding:9px 11px;font-size:12.5px;font-family:'DM Sans',sans-serif;color:var(--ink);background:var(--bg);transition:border-color .2s;outline:none;resize:none}
    .mgr input:focus,.mgr select:focus,.mgr textarea:focus{border-color:var(--verde)}
    .btn-save{width:100%;padding:12px;border-radius:10px;background:linear-gradient(135deg,var(--verde),var(--verde2));color:white;border:none;font-size:13.5px;font-weight:800;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:16px;box-shadow:0 3px 12px rgba(28,107,50,.3);transition:.2s}
    .btn-save:hover{transform:translateY(-1px);box-shadow:0 5px 18px rgba(28,107,50,.4)}
    .btn-save:disabled{opacity:.6;cursor:not-allowed;transform:none}

    /* BANNER CROP */
    #cropBannerModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:99999;align-items:center;justify-content:center;padding:20px}

    @media(max-width:900px){.grid{grid-template-columns:1fr 1fr}.span3{grid-column:1/-1}.span2{grid-column:1/-1}}
    @media(max-width:640px){
      .navbar{padding:0 14px}.nav-links{display:none}
      .hero{padding:28px 16px 24px}.hero-deco{display:none}
      .contenido{padding:16px}.grid{grid-template-columns:1fr}
      .span2,.span3{grid-column:1/-1}.mfila{grid-template-columns:1fr}
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar">
  <a href="index.html" class="nav-marca">
    <img src="Imagenes/Quibdo.png" alt="Logo">
    <span class="nav-marca-texto">Quibdó<em>Conecta</em></span>
  </a>
  <nav class="nav-links">
    <a href="dashboard_negocios.php" class="nl on">🏠 Panel</a>
    <a href="negocios.php" class="nl">🏪 Negocios</a>
    <a href="chat.php" class="nl">
      💬 Mensajes
      <?php if ($chatNoLeidos>0): ?><span class="nl-dot"></span><?php endif; ?>
    </a>
    <a href="buscar.php" class="nl">🔍 Buscar</a>
    <a href="Ayuda.html" class="nl">❓ Ayuda</a>
  </nav>
  <div class="nav-usuario">
    <div class="nav-avatar" id="navAvatar" onclick="abrirModal()">
      <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?=$iniciales?><?php endif; ?>
    </div>
    <span class="nav-nombre"><?=$nombreNegocio?></span>
    <a href="?salir=1" class="nav-salir">Salir</a>
  </div>
</header>

<!-- HERO -->
<div class="hero">
  <div class="hero-inner">
    <div class="hero-av" id="heroAvatar" onclick="abrirModal()" title="Cambiar foto">
      <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?=$iniciales?><?php endif; ?>
    </div>
    <div>
      <div class="hero-chips">
        <span class="hchip <?=$esCC?'hc-cc':'hc-tipo'?>"><?=$esCC?'🏬 C.C. El Caraño':'🏪 Negocio Local'?></span>
        <?php if ($tieneTop): ?><span class="hchip hc-top">👑 Top</span>
        <?php elseif ($tienePremium): ?><span class="hchip hc-prem">⭐ Premium</span>
        <?php elseif ($tieneDestacado): ?><span class="hchip hc-dest">🏅 Destacado</span><?php endif; ?>
        <?php if ($tieneVerificado): ?><span class="hchip hc-veri">✓ Verificado</span><?php endif; ?>
      </div>
      <div class="hero-nombre"><?=$nombreNegocio?></div>
      <div class="hero-sub">
        <?php if ($categoria): ?><span>🏷️ <?=$categoria?></span><?php endif; ?>
        <?php if ($ciudad): ?><span>📍 <?=$ciudad?></span><?php endif; ?>
        <span>📅 Desde <?=$fechaRegistro?></span>
      </div>
    </div>
  </div>
  <div class="hero-stats">
    <div class="hs"><div class="hs-val"><?=$vistasTotal?></div><div class="hs-lab">Visitas totales</div></div>
    <div class="hs"><div class="hs-val"><?=$vistas7dias>0?'+'.$vistas7dias:'0'?></div><div class="hs-lab">Esta semana</div></div>
    <div class="hs"><div class="hs-val"><?=$pct?>%</div><div class="hs-lab">Perfil completado</div></div>
    <div class="hs"><div class="hs-val"><?=$chatNoLeidos?:'0'?></div><div class="hs-lab">Mensajes nuevos</div></div>
    <div class="hs"><div class="hs-val"><?=$visibleEnWeb?'🟢':'🟡'?></div><div class="hs-lab"><?=$visibleEnWeb?'Visible':'Oculto'?></div></div>
  </div>
  <div class="hero-deco">🏪</div>
</div>

<!-- ALERTAS -->
<div style="padding:20px 36px 0">
  <?php if (!$tieneVerificado): ?>
    <?php if ($estadoVerif==='pendiente'): ?>
      <div class="alerta ap"><div class="a-ico">⏳</div><div class="a-txt"><strong>Documentos en revisión</strong>El administrador revisará tu RUT o registro mercantil pronto.</div></div>
    <?php elseif ($estadoVerif==='rechazado'): ?>
      <div class="alerta ar"><div class="a-ico">❌</div><div class="a-txt"><strong>Verificación rechazada</strong>Sube los documentos con mejor calidad.</div><a href="verificar_cuenta.php" class="a-btn">Reintentar</a></div>
    <?php else: ?>
      <div class="alerta as"><div class="a-ico">🏪</div><div class="a-txt"><strong>Verifica tu negocio</strong>Sube tu RUT o registro mercantil y obtén el badge de Negocio Verificado.</div><a href="verificar_cuenta.php" class="a-btn">Verificar</a></div>
    <?php endif; ?>
  <?php else: ?>
    <div class="alerta av"><div class="a-ico">✅</div><div class="a-txt"><strong>Negocio verificado</strong>Los clientes ven tu badge de Negocio Verificado.</div></div>
  <?php endif; ?>
</div>

<!-- CONTENIDO -->
<div class="contenido">
  <div class="grid">

    <!-- BANNER + FOTO -->
    <div class="card span3" style="padding:0;overflow:visible;border:1.5px solid #bbf7d0">
      <div style="overflow:hidden;border-radius:15px">
        <!-- Banner -->
        <div id="bannerZone" style="position:relative;height:150px;background:linear-gradient(135deg,#0a2010,#1c6b32);cursor:pointer;overflow:hidden" onclick="document.getElementById('bannerInput').click()" title="Cambiar banner">
          <?php if ($bannerUrl): ?>
            <img id="bannerImg" src="<?=$bannerUrl?>" style="width:100%;height:100%;object-fit:cover;display:block">
          <?php else: ?>
            <div id="bannerPlaceholder" style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;color:rgba(255,255,255,.5)">
              <div style="font-size:32px">🖼️</div>
              <div style="font-size:12.5px;font-weight:600">Haz clic para subir el banner de tu negocio</div>
              <div style="font-size:11px;opacity:.7">1200×300px · JPG/PNG/WEBP · máx 5MB</div>
            </div>
          <?php endif; ?>
          <div style="position:absolute;inset:0;background:rgba(0,0,0,0);transition:.2s;display:flex;align-items:center;justify-content:center"
               onmouseover="this.style.background='rgba(0,0,0,.35)';this.querySelector('span').style.opacity='1'"
               onmouseout="this.style.background='rgba(0,0,0,0)';this.querySelector('span').style.opacity='0'">
            <span style="opacity:0;color:#fff;font-size:12.5px;font-weight:700;background:rgba(0,0,0,.5);padding:7px 16px;border-radius:20px;transition:.2s">✏️ Cambiar banner</span>
          </div>
          <?php if ($bannerUrl): ?>
          <button onclick="event.stopPropagation();eliminarBanner()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700;cursor:pointer">🗑 Quitar</button>
          <?php endif; ?>
        </div>
        <input type="file" id="bannerInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirBanner(this)">

        <!-- Foto + info -->
        <div style="padding:0 22px 20px;margin-top:-46px;position:relative;z-index:2">
          <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div style="position:relative;display:inline-block">
              <div onclick="abrirModal()" style="width:92px;height:92px;border-radius:16px;border:4px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,.16);background:linear-gradient(135deg,#1c6b32,#2d9e50);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900;color:#fff;cursor:pointer;overflow:hidden">
                <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?=$iniciales?><?php endif; ?>
              </div>
              <div onclick="abrirModal()" style="position:absolute;bottom:2px;right:2px;width:26px;height:26px;border-radius:50%;background:#1c6b32;border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px">✏️</div>
            </div>
            <div style="flex:1;min-width:160px;padding-top:50px">
              <div style="font-size:18px;font-weight:800;color:#0a2010" id="dNombreNeg"><?=$nombreNegocio?></div>
              <div style="font-size:12.5px;color:#5a7a60;margin-top:2px" id="dCategoria"><?=$categoria?:''?><?php if ($ciudad): ?> · 📍 <?=$ciudad?><?php endif; ?></div>
            </div>
            <div style="display:flex;gap:8px;padding-top:50px;flex-wrap:wrap">
              <a href="perfil.php?id=<?=$usuario['id']?>" target="_blank" style="display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:#f0fdf4;color:#1c6b32;border:1.5px solid #bbf7d0;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none">👁 Ver perfil</a>
              <button onclick="abrirModal()" style="display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:#1c6b32;color:#fff;border:none;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer">✏️ Editar</button>
            </div>
          </div>
          <div id="bannerMsg" style="font-size:12px;color:#e53935;margin-top:6px;display:none"></div>
        </div>
      </div>
    </div>

    <!-- MINI STATS -->
    <div class="card mini">
      <div class="m-ico ig">👁️</div>
      <div>
        <div class="m-val"><?=$vistasTotal?></div>
        <div class="m-lab">Visitas al negocio</div>
        <div class="m-sub" onclick="location.href='negocios.php'"><?=$vistas7dias>0?'+'.$vistas7dias.' esta semana →':'Ver directorio →'?></div>
      </div>
    </div>
    <div class="card mini" onclick="location.href='chat.php'" style="cursor:pointer">
      <div class="m-ico io">💬</div>
      <div>
        <div class="m-val"><?=$chatNoLeidos?:'0'?></div>
        <div class="m-lab">Mensajes</div>
        <div class="m-sub">Ir al chat →</div>
      </div>
    </div>
    <div class="card mini">
      <div class="m-ico im">⭐</div>
      <div>
        <div class="m-val"><?=$pct?>%</div>
        <div class="m-lab">Perfil completado</div>
        <div class="m-sub" onclick="abrirModal()"><?=$pct<100?'Mejorar perfil →':'¡Perfecto! ✓'?></div>
      </div>
    </div>

    <!-- ACCIONES RÁPIDAS -->
    <div class="card span3">
      <div class="ca-tit">⚡ Acciones rápidas</div>
      <div class="ac-row">
        <a href="chat.php" class="ac">
          <div class="ac-ico">💬</div><div class="ac-tit">Mensajes</div>
          <?php if ($chatNoLeidos>0): ?><span class="ac-badge"><?=$chatNoLeidos?></span><?php else: ?><div class="ac-desc">Sin nuevos</div><?php endif; ?>
        </a>
        <a href="negocios.php" class="ac"><div class="ac-ico">🏪</div><div class="ac-tit">Ver directorio</div><div class="ac-desc">Negocios del Chocó</div></a>
        <a href="javascript:void(0)" onclick="abrirModal()" class="ac"><div class="ac-ico">✏️</div><div class="ac-tit">Editar negocio</div><div class="ac-desc">Actualizar datos</div></a>
        <a href="verificar_cuenta.php" class="ac"><div class="ac-ico">🪪</div><div class="ac-tit">Verificación</div><div class="ac-desc"><?=$tieneVerificado?'✅ Verificado':'Subir docs'?></div></a>
        <a href="buscar.php" class="ac"><div class="ac-ico">🔍</div><div class="ac-tit">Buscar</div><div class="ac-desc">Talentos / empresas</div></a>
        <a href="javascript:void(0)" onclick="abrirModalGaleria()" class="ac"><div class="ac-ico">📸</div><div class="ac-tit">Galería</div><div class="ac-desc"><?=$galeriaTotal?> fotos</div></a>
        <a href="Ayuda.html" class="ac"><div class="ac-ico">❓</div><div class="ac-tit">Ayuda</div><div class="ac-desc">Soporte y guías</div></a>
      </div>
    </div>

    <!-- PLAN ACTIVO -->
    <?php if (!empty($datosPlan)): ?>
    <?php $usados=$datosPlan['usados']??[];$cfg=$datosPlan['config']??[]; ?>
    <div class="card span3" style="background:linear-gradient(135deg,#f0fdf4,#e8f5e9);border:1.5px solid #a5d6a7">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:12px">
          <div style="width:42px;height:42px;border-radius:12px;background:#2e7d32;display:flex;align-items:center;justify-content:center;font-size:20px">⭐</div>
          <div>
            <div style="font-size:10px;font-weight:700;color:#558b6e;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:2px">Plan activo</div>
            <div style="font-size:20px;font-weight:800;color:#1b5e20"><?=htmlspecialchars($datosPlan['nombre']??'Semilla')?></div>
          </div>
        </div>
        <a href="empresas.php#planes" style="display:inline-flex;align-items:center;gap:5px;padding:8px 16px;background:#2e7d32;color:#fff;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;box-shadow:0 2px 8px rgba(46,125,50,.3)">✦ Mejorar plan</a>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px">
        <?php foreach (['mensajes'=>['💬','Mensajes'],'vacantes'=>['💼','Vacantes']] as $key=>[$ico,$lab]):
          $lim=$cfg[$key]??0; if ($lim===0) continue;
          $uso=$usados[$key]??0; $inf=($lim===-1);
          $pctB=$inf?10:min(100,($uso/max(1,$lim))*100);
          $col=$pctB>=90?'#e53935':($pctB>=70?'#fb8c00':'#43a047');
          $bg=$pctB>=90?'#fff5f5':($pctB>=70?'#fff8f0':'#f9fbe7');
          $bd=$pctB>=90?'#ef9a9a':($pctB>=70?'#ffcc80':'#c5e1a5');
          $nc=$pctB>=90?'#c62828':($pctB>=70?'#e65100':'#2e7d32');
        ?>
        <div style="background:<?=$bg?>;border:1px solid <?=$bd?>;border-radius:12px;padding:13px">
          <div style="font-size:11px;font-weight:600;color:#546e7a;margin-bottom:5px"><?=$ico?> <?=$lab?></div>
          <div style="font-size:19px;font-weight:800;color:<?=$nc?>;margin-bottom:7px"><?=$uso?><span style="font-size:11px;font-weight:500;color:#90a4ae"> / <?=$inf?'∞':$lim?></span></div>
          <div style="height:5px;background:rgba(0,0,0,.07);border-radius:4px"><div style="height:5px;width:<?=$pctB?>%;background:<?=$col?>;border-radius:4px"></div></div>
          <?php if (!$inf&&$pctB>=70): ?><div style="font-size:10px;color:<?=$nc?>;margin-top:5px;font-weight:700"><?=$pctB>=90?'⚠️ Límite':'⚡ Casi al límite'?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- GALERÍA DEL NEGOCIO -->
    <div class="card span2">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
        <div class="ca-tit" style="margin-bottom:0">📸 Galería del negocio <span style="background:#dcfce7;color:#166534;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:700;margin-left:6px"><?=$galeriaTotal?> fotos</span></div>
        <button onclick="abrirModalGaleria()" style="padding:6px 13px;border-radius:8px;background:#1c6b32;color:#fff;border:none;font-size:11.5px;font-weight:700;cursor:pointer">+ Añadir</button>
      </div>
      <div class="galeria-grid" id="galeriaGrid">
        <?php foreach ($galeriaItems as $g): ?>
          <div class="gal-item">
            <?php if ($g['tipo']==='video'): ?>
              <div style="width:100%;height:100%;background:#111;display:flex;align-items:center;justify-content:center;font-size:28px">▶️</div>
            <?php else: ?>
              <img src="<?=htmlspecialchars($g['archivo'])?>" alt="<?=htmlspecialchars($g['titulo']??'')?>">
            <?php endif; ?>
            <div class="gal-del" onclick="eliminarEvidencia(<?=$g['id']?>,this)">🗑️</div>
          </div>
        <?php endforeach; ?>
        <div class="gal-add" onclick="abrirModalGaleria()">
          <div class="gal-add-ico">📷</div>
          <div class="gal-add-txt">Añadir foto</div>
        </div>
      </div>
      <?php if ($galeriaTotal===0): ?>
        <div style="text-align:center;padding:20px;color:var(--ink3)">
          <div style="font-size:36px;margin-bottom:8px">📷</div>
          <div style="font-size:13px;font-weight:700;margin-bottom:4px">Sin fotos aún</div>
          <div style="font-size:12px">Muestra tu negocio con fotos del local, productos y ambiente</div>
        </div>
      <?php endif; ?>
    </div>

    <!-- PERFIL LATERAL -->
    <div class="card" style="display:flex;flex-direction:column">
      <div class="cp-head">
        <div class="cp-av" onclick="abrirModal()">
          <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" style="width:100%;height:100%;object-fit:cover;border-radius:14px"><?php else: ?><?=$iniciales?><?php endif; ?>
        </div>
        <div class="cp-nom" id="dNombre2"><?=$nombreNegocio?></div>
        <div class="cp-pro"><?=$categoria?:($esCC?'C.C. El Caraño':'Negocio local')?></div>
      </div>
      <div class="cp-body">
        <?php if ($ciudad): ?><div class="cp-fil"><span class="cp-ico">📍</span><span><?=$ciudad?></span></div><?php endif; ?>
        <?php if ($direccion): ?><div class="cp-fil"><span class="cp-ico">🗺️</span><span><?=$direccion?></span></div><?php endif; ?>
        <?php if ($whatsapp): ?><div class="cp-fil"><span class="cp-ico">💬</span><span><?=$whatsapp?></span></div><?php endif; ?>
        <?php if ($horario): ?><div class="cp-fil"><span class="cp-ico">🕐</span><span><?=$horario?></span></div><?php endif; ?>
        <div class="cp-fil"><span class="cp-ico">✉️</span><span><?=$correo?></span></div>
        <div class="cp-fil"><span class="cp-ico">📅</span><span><?=$fechaRegistro?></span></div>
        <?php if (!empty($badgesHTML)): ?><div style="margin-top:7px"><?=$badgesHTML?></div><?php endif; ?>
      </div>
      <div class="vis-row">
        <div><div class="vis-lab">Visible en directorio</div><div class="vis-sub">Aparece en Negocios públicos</div></div>
        <div style="display:flex;align-items:center;gap:7px">
          <span id="visChip" class="pv-chip <?=$visibleEnWeb?'ok':'off'?>"><?=$visibleEnWeb?'🟢 Visible':'🟡 Oculto'?></span>
          <label class="toggle">
            <input type="checkbox" id="toggleVisible" <?=$visibleEnWeb?'checked':''?> onchange="toggleVisibilidad(this.checked)">
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>
      <div class="prog-w">
        <div class="prog-h"><span>Perfil completado</span><span><?=$pct?>%</span></div>
        <div class="prog-t"><div class="prog-f" id="progBar" style="width:0%"></div></div>
      </div>
      <button class="btn-edit" onclick="abrirModal()">✏️ Editar perfil negocio</button>
      <a href="negocios.php" class="btn-sec">🌐 Ver en directorio</a>
    </div>

    <!-- ACTIVIDAD RECIENTE -->
    <div class="card span2">
      <div class="ca-tit">🕐 Actividad reciente</div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:11px;background:var(--bg);border:1px solid var(--borde)">
          <div style="width:34px;height:34px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;font-size:16px">🎉</div>
          <div><div style="font-size:12.5px;font-weight:700">Negocio registrado</div><div style="font-size:11px;color:var(--ink3)"><?=$fechaRegistro?></div></div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:11px;background:var(--bg);border:1px solid var(--borde)">
          <div style="width:34px;height:34px;border-radius:10px;background:#fef9c3;display:flex;align-items:center;justify-content:center;font-size:16px">👁️</div>
          <div><div style="font-size:12.5px;font-weight:700"><?=$vistasTotal?> visitas acumuladas</div><div style="font-size:11px;color:var(--ink3)"><?=$vistas7dias>0?'+'.$vistas7dias.' en los últimos 7 días':'Sin visitas recientes'?></div></div>
        </div>
        <?php if ($galeriaTotal>0): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:11px;background:var(--bg);border:1px solid var(--borde)">
          <div style="width:34px;height:34px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;font-size:16px">📸</div>
          <div><div style="font-size:12.5px;font-weight:700"><?=$galeriaTotal?> fotos en galería</div><div style="font-size:11px;color:var(--ink3)">Muestra tu negocio a los clientes</div></div>
        </div>
        <?php endif; ?>
        <?php if ($tieneVerificado): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:11px;background:var(--bg);border:1px solid var(--borde)">
          <div style="width:34px;height:34px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;font-size:16px">✅</div>
          <div><div style="font-size:12.5px;font-weight:700">Negocio Verificado</div><div style="font-size:11px;color:var(--ink3)">Badge asignado por el administrador</div></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- BADGES -->
    <div class="card">
      <div class="ca-tit">🏆 Badges activos</div>
      <div style="display:flex;flex-wrap:wrap;gap:7px;margin-top:6px">
        <?php if ($tieneTop): ?><span class="bdg bdg-t">👑 Top</span><?php endif; ?>
        <?php if ($tienePremium): ?><span class="bdg bdg-p">⭐ Premium</span><?php endif; ?>
        <?php if ($tieneDestacado): ?><span class="bdg bdg-d">🏅 Destacado</span><?php endif; ?>
        <?php if ($tieneVerificado): ?><span class="bdg bdg-v">✓ Verificado</span><?php endif; ?>
        <?php if (!$tieneVerificado&&!$tienePremium&&!$tieneDestacado&&!$tieneTop): ?>
          <div style="font-size:12.5px;color:var(--ink3);padding:6px 0">
            Sin badges aún. <a href="verificar_cuenta.php" style="color:var(--verde);font-weight:700">Verifica tu negocio →</a>
          </div>
        <?php endif; ?>
      </div>
      <div style="margin-top:14px;padding:13px;background:#f0fdf4;border-radius:11px;border:1px solid #bbf7d0">
        <div style="font-size:11.5px;font-weight:800;color:var(--verde);margin-bottom:5px">¿Más visibilidad?</div>
        <div style="font-size:11.5px;color:#166534;line-height:1.5">Badges <strong>Premium</strong> y <strong>Destacado</strong> disponibles desde el plan <strong>Verde Selva</strong>.</div>
      </div>
    </div>

  </div>
</div>

<!-- MODAL CROP BANNER -->
<div id="cropBannerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:99999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:18px;padding:22px;max-width:680px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.5)">
    <div style="font-size:15px;font-weight:800;color:#1c6b32;margin-bottom:12px;text-align:center">🖼️ Encuadra el banner</div>
    <div style="position:relative;width:100%;height:200px;overflow:hidden;border-radius:10px;background:#000;display:flex;align-items:center;justify-content:center">
      <img id="cropBannerImg" style="max-width:100%;display:block">
    </div>
    <p style="font-size:11.5px;color:#64748b;text-align:center;margin:9px 0">Arrastra y ajusta · Proporción 4:1</p>
    <div style="display:flex;gap:9px;margin-top:4px">
      <button onclick="cancelarCropBanner()" style="flex:1;padding:10px;border-radius:9px;border:1px solid #e0e0e0;background:#f5f5f5;font-size:12.5px;font-weight:700;cursor:pointer">Cancelar</button>
      <button onclick="confirmarCropBanner()" id="btnConfirmarCropBanner" style="flex:2;padding:10px;border-radius:9px;border:none;background:linear-gradient(135deg,#1c6b32,#2d9e50);color:#fff;font-size:12.5px;font-weight:800;cursor:pointer">✅ Usar banner</button>
    </div>
  </div>
</div>

<!-- MODAL GALERÍA -->
<div class="modal-ov" id="modalGaleria">
  <div class="modal-box">
    <button class="mcerrar" onclick="cerrarModalGaleria()">✕</button>
    <div class="modal-pad">
      <div class="mtit">📸 Subir foto al negocio</div>
      <p class="msub">Muestra tu local, productos y ambiente. Máx. 15 fotos en plan básico.</p>
      <div id="galMsg" class="mmsg"></div>
      <div class="mfila">
        <div class="mgr full"><label>Título de la foto</label><input type="text" id="galTitulo" placeholder="ej: Interior del local, Menú del día…"></div>
        <div class="mgr full"><label>Descripción (opcional)</label><textarea id="galDesc" rows="2" placeholder="Descripción breve…"></textarea></div>
      </div>
      <div style="border:2px dashed #bbf7d0;border-radius:12px;padding:24px;text-align:center;cursor:pointer;background:#f0fdf4;transition:.2s" onclick="document.getElementById('galFile').click()" onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
        <div style="font-size:32px;margin-bottom:7px">📷</div>
        <div style="font-size:13px;font-weight:700;color:#1c6b32">Haz clic para seleccionar imagen</div>
        <div style="font-size:11.5px;color:#5a7a60;margin-top:3px">JPG, PNG, WEBP · máx 5 MB</div>
      </div>
      <input type="file" id="galFile" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirEvidencia(this)">
      <div id="galProgress" style="display:none;margin-top:12px;font-size:12.5px;color:#1c6b32;font-weight:600;text-align:center">⏳ Subiendo…</div>
    </div>
  </div>
</div>

<!-- MODAL EDITAR NEGOCIO -->
<div class="modal-ov" id="modalEditar">
  <div class="modal-box">
    <button class="mcerrar" onclick="cerrarModal()">✕</button>
    <div class="modal-pad">
      <div class="mtit">✏️ Editar perfil del negocio</div>
      <p class="msub">Mantén tu información actualizada para que los clientes te encuentren fácilmente.</p>
      <div class="mmsg" id="editMsg"></div>

      <!-- FOTO -->
      <div class="msec">Foto principal</div>
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
        <div id="fotoPreview" style="width:68px;height:68px;border-radius:12px;background:linear-gradient(135deg,#1c6b32,#2d9e50);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:white;overflow:hidden;flex-shrink:0;cursor:pointer" onclick="document.getElementById('fotoInput').click()">
          <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" id="fotoImgPreview" style="width:100%;height:100%;object-fit:cover;border-radius:12px"><?php else: ?><span><?=$iniciales?></span><?php endif; ?>
        </div>
        <div>
          <input type="file" id="fotoInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirFoto(this)">
          <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center">
            <button onclick="document.getElementById('fotoInput').click()" style="padding:7px 13px;border-radius:8px;background:var(--verde);color:white;border:none;font-size:12.5px;font-weight:700;cursor:pointer">📷 Cambiar foto</button>
            <?php if ($fotoUrl): ?><button onclick="eliminarFoto()" id="btnEliminarFoto" style="padding:7px 13px;border-radius:8px;background:transparent;color:#e74c3c;border:1.5px solid #e74c3c;font-size:12.5px;font-weight:700;cursor:pointer">🗑 Quitar</button><?php endif; ?>
          </div>
          <div style="font-size:11px;color:var(--ink3);margin-top:4px">JPG, PNG o WEBP · máx 2 MB</div>
          <div id="fotoMsg" style="font-size:11.5px;margin-top:3px"></div>
        </div>
      </div>

      <!-- DATOS -->
      <div class="msec">Información del negocio</div>
      <div class="mfila">
        <div class="mgr full"><label>Nombre del negocio *</label><input type="text" id="editNombre" value="<?=htmlspecialchars($np['nombre_negocio']??$usuario['nombre']??'')?>"></div>
      </div>
      <div class="mfila">
        <div class="mgr"><label>Categoría</label>
          <select id="editCategoria">
            <option value="">Selecciona…</option>
            <?php foreach (['Restaurante & Comidas','Tienda & Abarrotes','Ropa & Accesorios','Tecnología & Electrónica','Salud & Belleza','Servicios Hogar','Mecánica & Autos','Educación & Librería','Arte & Cultura','Agro & Campo','Otro'] as $c): ?>
            <option value="<?=$c?>" <?=($np['categoria']??'')===$c?'selected':''?>><?=$c?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mgr"><label>Ciudad</label><input type="text" id="editCiudad" value="<?=htmlspecialchars($uRe['ciudad']??'')?>" placeholder="Quibdó"></div>
      </div>
      <div class="mfila">
        <div class="mgr"><label>Dirección</label><input type="text" id="editDireccion" value="<?=htmlspecialchars($np['direccion']??'')?>" placeholder="Calle 27 # 4-15"></div>
        <div class="mgr"><label>Barrio</label><input type="text" id="editBarrio" value="<?=htmlspecialchars($np['barrio']??'')?>" placeholder="ej: Alameda Reyes"></div>
      </div>
      <div class="mfila">
        <div class="mgr"><label>WhatsApp</label><input type="tel" id="editWhatsapp" value="<?=htmlspecialchars($np['whatsapp']??'')?>" placeholder="3001234567"></div>
        <div class="mgr"><label>Horario de atención</label><input type="text" id="editHorario" value="<?=htmlspecialchars($np['horario']??'')?>" placeholder="Lun-Sab 8am-8pm"></div>
      </div>
      <div class="mfila">
        <div class="mgr"><label>Instagram</label><input type="text" id="editInstagram" value="<?=htmlspecialchars($np['instagram']??'')?>" placeholder="@minegocio"></div>
        <div class="mgr"><label>Sitio web</label><input type="url" id="editSitioWeb" value="<?=htmlspecialchars($np['sitio_web']??'')?>" placeholder="https://…"></div>
      </div>
      <div class="mfila">
        <div class="mgr full"><label>Descripción del negocio</label><textarea id="editDescripcion" rows="3" placeholder="¿Qué ofreces? ¿Qué hace especial a tu negocio?"><?=htmlspecialchars($np['descripcion']??'')?></textarea></div>
      </div>

      <button class="btn-save" id="btnGuardar" onclick="guardarNegocio()">💾 Guardar cambios</button>

      <!-- ZONA PELIGRO -->
      <div style="margin-top:28px;padding-top:20px;border-top:1px solid rgba(239,68,68,.2)">
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:9px"><span>⚠️</span><span style="font-size:12px;font-weight:700;color:#f87171;text-transform:uppercase;letter-spacing:.8px">Zona de peligro</span></div>
        <p style="font-size:12.5px;color:var(--ink3);margin-bottom:12px;line-height:1.5">Eliminar tu cuenta es <strong style="color:#f87171">permanente e irreversible</strong>.</p>
        <button onclick="abrirEliminarCuenta()" style="padding:9px 18px;border-radius:9px;background:transparent;border:1.5px solid #e74c3c;color:#e74c3c;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;transition:.2s" onmouseover="this.style.background='rgba(231,76,60,.1)'" onmouseout="this.style.background='transparent'">🗑 Eliminar mi cuenta</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL ELIMINAR CUENTA -->
<div id="modalEliminarCuenta" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:20px">
  <div style="background:#0f172a;border:1.5px solid rgba(239,68,68,.35);border-radius:18px;padding:32px 28px;max-width:420px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.6)">
    <div style="text-align:center;margin-bottom:20px">
      <div style="font-size:44px;margin-bottom:10px">⚠️</div>
      <h3 style="font-size:18px;font-weight:800;color:#f87171;margin-bottom:7px">Eliminar cuenta permanentemente</h3>
      <p style="font-size:13px;color:rgba(255,255,255,.6);line-height:1.6">Se eliminarán el negocio, galería, mensajes y todos los datos asociados.</p>
    </div>
    <div style="margin-bottom:18px">
      <label style="display:block;font-size:12.5px;font-weight:600;color:rgba(255,255,255,.7);margin-bottom:7px">Escribe tu correo: <strong style="color:#f87171"><?=$correo?></strong></label>
      <input type="text" id="inputConfirmarCuenta" placeholder="Escribe tu correo exacto" style="width:100%;padding:10px 13px;border-radius:9px;border:1.5px solid rgba(239,68,68,.4);background:rgba(239,68,68,.06);color:white;font-size:13px;outline:none;font-family:inherit">
    </div>
    <div id="msgEliminarCuenta" style="display:none;margin-bottom:10px;padding:9px 13px;border-radius:8px;font-size:12.5px"></div>
    <div style="display:flex;gap:9px">
      <button onclick="cerrarEliminarCuenta()" style="flex:1;padding:11px;border-radius:9px;border:1.5px solid rgba(255,255,255,.15);background:transparent;color:rgba(255,255,255,.7);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit">Cancelar</button>
      <button id="btnConfirmarEliminar" onclick="confirmarEliminarCuenta()" style="flex:1;padding:11px;border-radius:9px;border:none;background:#e74c3c;color:white;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit">🗑 Sí, eliminar</button>
    </div>
  </div>
</div>

<script>
function abrirModal(){document.getElementById('modalEditar').classList.add('open')}
function cerrarModal(){document.getElementById('modalEditar').classList.remove('open')}
function abrirModalGaleria(){document.getElementById('modalGaleria').classList.add('open')}
function cerrarModalGaleria(){document.getElementById('modalGaleria').classList.remove('open')}
document.getElementById('modalEditar').addEventListener('click',e=>{if(e.target===document.getElementById('modalEditar'))cerrarModal()});
document.getElementById('modalGaleria').addEventListener('click',e=>{if(e.target===document.getElementById('modalGaleria'))cerrarModalGaleria()});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){cerrarModal();cerrarModalGaleria();cerrarEliminarCuenta()}});

window.addEventListener('load',()=>{const b=document.getElementById('progBar');if(b)setTimeout(()=>{b.style.width='<?=$pct?>%'},400)});

function mostrarMsg(t,c){const e=document.getElementById('editMsg');e.textContent=t;e.className='mmsg '+c;e.style.display='block'}

async function guardarNegocio(){
  const btn=document.getElementById('btnGuardar');
  const n=document.getElementById('editNombre').value.trim();
  if(!n){mostrarMsg('El nombre es obligatorio.','error');return;}
  btn.disabled=true;btn.textContent='⏳ Guardando…';
  const fd=new FormData();
  fd.append('_action','editar_negocio');
  fd.append('nombre_negocio',n);
  fd.append('categoria',document.getElementById('editCategoria').value);
  fd.append('ciudad',document.getElementById('editCiudad').value.trim());
  fd.append('direccion',document.getElementById('editDireccion').value.trim());
  fd.append('barrio',document.getElementById('editBarrio').value.trim());
  fd.append('whatsapp',document.getElementById('editWhatsapp').value.trim());
  fd.append('horario',document.getElementById('editHorario').value.trim());
  fd.append('instagram',document.getElementById('editInstagram').value.trim());
  fd.append('sitio_web',document.getElementById('editSitioWeb').value.trim());
  fd.append('descripcion',document.getElementById('editDescripcion').value.trim());
  try{
    const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});
    const j=await r.json();
    if(j.ok){
      mostrarMsg('¡Perfil actualizado!','success');
      document.getElementById('dNombreNeg').textContent=j.nombre_negocio;
      document.getElementById('dNombre2').textContent=j.nombre_negocio;
      if(j.foto){
        const imgTag=`<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:12px">`;
        ['fotoPreview','navAvatar','heroAvatar'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=imgTag;});
      }
      setTimeout(cerrarModal,1500);
    }else{mostrarMsg(j.msg||'Error al guardar.','error');}
  }catch(e){mostrarMsg('Error de conexión.','error');}
  btn.disabled=false;btn.textContent='💾 Guardar cambios';
}

async function toggleVisibilidad(visible){
  const chip=document.getElementById('visChip');
  const fd=new FormData();fd.append('_action','toggle_visibilidad');fd.append('visible',visible?'1':'0');
  try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
    if(j.ok){chip.textContent=visible?'🟢 Visible':'🟡 Oculto';chip.className='pv-chip '+(visible?'ok':'off');}
  }catch(e){}
}

async function subirFoto(input){
  const file=input.files[0];if(!file)return;
  const msg=document.getElementById('fotoMsg');msg.textContent='⏳ Subiendo…';msg.style.color='var(--ink3)';
  const fd=new FormData();fd.append('_action','subir_foto');fd.append('foto',file);
  try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
    if(j.ok){msg.textContent='✅ Foto actualizada';msg.style.color='var(--verde2)';
      const img=`<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:12px">`;
      ['fotoPreview','navAvatar','heroAvatar'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=img;});
    }else{msg.textContent='❌ '+(j.msg||'Error');msg.style.color='#e74c3c';}
  }catch(e){msg.textContent='❌ Error de conexión';msg.style.color='#e74c3c';}
  input.value='';
}

async function eliminarFoto(){
  if(!confirm('¿Quitar la foto principal?'))return;
  const fd=new FormData();fd.append('_action','eliminar_foto');
  try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
    if(j.ok)location.reload();
  }catch(e){}
}

async function subirEvidencia(input){
  const file=input.files[0];if(!file)return;
  const msg=document.getElementById('galMsg');const progress=document.getElementById('galProgress');
  progress.style.display='block';msg.style.display='none';
  const fd=new FormData();fd.append('_action','subir_evidencia');fd.append('tipo_media','foto');
  fd.append('archivo',file);fd.append('titulo',document.getElementById('galTitulo').value.trim());
  fd.append('descripcion',document.getElementById('galDesc').value.trim());
  try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
    progress.style.display='none';
    if(j.ok){msg.textContent='✅ Foto añadida';msg.className='mmsg success';msg.style.display='block';setTimeout(()=>{cerrarModalGaleria();location.reload();},1200);}
    else{msg.textContent='❌ '+(j.msg||'Error');msg.className='mmsg error';msg.style.display='block';}
  }catch(e){progress.style.display='none';msg.textContent='❌ Error de conexión';msg.className='mmsg error';msg.style.display='block';}
  input.value='';
}

async function eliminarEvidencia(id,el){
  if(!confirm('¿Eliminar esta foto?'))return;
  const fd=new FormData();fd.append('_action','eliminar_evidencia');fd.append('id',id);
  try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
    if(j.ok){const item=el.closest('.gal-item');if(item){item.style.opacity='0';item.style.transition='.3s';setTimeout(()=>item.remove(),320);}}
  }catch(e){}
}

// Banner crop
let cropperBannerInstance=null;
function subirBanner(input){
  const file=input.files[0];if(!file)return;input.value='';
  const reader=new FileReader();
  reader.onload=e=>{
    const img=document.getElementById('cropBannerImg');
    if(cropperBannerInstance){cropperBannerInstance.destroy();cropperBannerInstance=null;}
    img.src=e.target.result;
    document.getElementById('cropBannerModal').style.display='flex';
    img.onload=()=>{cropperBannerInstance=new Cropper(img,{aspectRatio:4,viewMode:1,dragMode:'move',autoCropArea:1,cropBoxResizable:false,cropBoxMovable:false,background:false,responsive:true});};
  };
  reader.readAsDataURL(file);
}
function cancelarCropBanner(){document.getElementById('cropBannerModal').style.display='none';if(cropperBannerInstance){cropperBannerInstance.destroy();cropperBannerInstance=null;}}
async function confirmarCropBanner(){
  if(!cropperBannerInstance)return;
  const btn=document.getElementById('btnConfirmarCropBanner');btn.textContent='⏳ Guardando…';btn.disabled=true;
  const canvas=cropperBannerInstance.getCroppedCanvas({width:1200,height:300,imageSmoothingQuality:'high'});
  const dataUrl=canvas.toDataURL('image/jpeg',.92);
  let img=document.getElementById('bannerImg');const ph=document.getElementById('bannerPlaceholder');
  if(ph)ph.style.display='none';
  if(!img){img=document.createElement('img');img.id='bannerImg';img.style.cssText='width:100%;height:100%;object-fit:cover;display:block;position:absolute;inset:0';document.getElementById('bannerZone').insertBefore(img,document.getElementById('bannerZone').firstChild);}
  img.src=dataUrl;
  document.getElementById('cropBannerModal').style.display='none';
  if(cropperBannerInstance){cropperBannerInstance.destroy();cropperBannerInstance=null;}
  canvas.toBlob(async blob=>{
    const fd=new FormData();fd.append('_action','subir_banner');fd.append('banner',new File([blob],'banner.jpg',{type:'image/jpeg'}));
    try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
      if(!j.ok){document.getElementById('bannerMsg').textContent='❌ '+(j.msg||'Error');document.getElementById('bannerMsg').style.display='block';}
    }catch(e){}
    btn.textContent='✅ Usar banner';btn.disabled=false;
  },'image/jpeg',.92);
}
async function eliminarBanner(){
  if(!confirm('¿Quitar el banner?'))return;
  const fd=new FormData();fd.append('_action','eliminar_banner');
  try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
    if(j.ok){const img=document.getElementById('bannerImg');if(img)img.remove();const ph=document.getElementById('bannerPlaceholder');if(ph)ph.style.display='flex';}
  }catch(e){}
}

// Eliminar cuenta
function abrirEliminarCuenta(){const m=document.getElementById('modalEliminarCuenta');m.style.display='flex';document.getElementById('inputConfirmarCuenta').value='';document.getElementById('msgEliminarCuenta').style.display='none';}
function cerrarEliminarCuenta(){document.getElementById('modalEliminarCuenta').style.display='none';}
async function confirmarEliminarCuenta(){
  const correo=document.getElementById('inputConfirmarCuenta').value.trim();
  const msg=document.getElementById('msgEliminarCuenta');const btn=document.getElementById('btnConfirmarEliminar');
  if(!correo){msg.textContent='Escribe tu correo.';msg.style.cssText='display:block;padding:9px;border-radius:8px;font-size:12.5px;background:rgba(239,68,68,.15);color:#f87171';return;}
  btn.disabled=true;btn.textContent='⏳ Eliminando…';
  const fd=new FormData();fd.append('_action','eliminar_cuenta');fd.append('confirmar',correo);
  try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
    if(j.ok){msg.textContent='✅ Cuenta eliminada. Redirigiendo…';msg.style.cssText='display:block;padding:9px;border-radius:8px;font-size:12.5px;background:rgba(31,157,85,.15);color:#a7f3d0';setTimeout(()=>{window.location.href='index.html'},2000);}
    else{msg.textContent='❌ '+(j.msg||'Error');msg.style.cssText='display:block;padding:9px;border-radius:8px;font-size:12.5px;background:rgba(239,68,68,.15);color:#f87171';btn.disabled=false;btn.textContent='🗑 Sí, eliminar';}
  }catch(e){btn.disabled=false;btn.textContent='🗑 Sí, eliminar';}
}
document.getElementById('modalEliminarCuenta').addEventListener('click',e=>{if(e.target===document.getElementById('modalEliminarCuenta'))cerrarEliminarCuenta()});
</script>

<script src="js/sesion_widget.js"></script>
</body>
</html>