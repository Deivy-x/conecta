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
        try { $db->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}
        require_once __DIR__ . '/Php/cloudinary_upload.php';
        $result = cloudinary_upload($_FILES['banner']['tmp_name'], 'quibdoconecta/banners');
        if (!$result['ok']) { echo json_encode(['ok'=>false,'msg'=>$result['msg']]); exit; }
        $db->prepare("UPDATE usuarios SET banner=? WHERE id=?")->execute([$result['url'], $usuario['id']]);
        echo json_encode(['ok'=>true,'banner'=>$result['url']]);
        exit;
    }

    if ($action === 'eliminar_banner') {
        try { $db->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}
        $db->prepare("UPDATE usuarios SET banner='' WHERE id=?")->execute([$usuario['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'subir_evidencia') {
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== 0) { echo json_encode(['ok'=>false,'msg'=>'No se recibió archivo.']); exit; }
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'])) { echo json_encode(['ok'=>false,'msg'=>'Solo JPG/PNG/WEBP.']); exit; }
        if ($_FILES['archivo']['size'] > 5*1024*1024) { echo json_encode(['ok'=>false,'msg'=>'Máximo 5 MB.']); exit; }
        try { $db->exec("CREATE TABLE IF NOT EXISTS talento_galeria(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT NOT NULL,tipo ENUM('foto','video') NOT NULL DEFAULT 'foto',archivo VARCHAR(255) NOT NULL DEFAULT '',url_video VARCHAR(500) DEFAULT NULL,titulo VARCHAR(150) DEFAULT NULL,descripcion TEXT DEFAULT NULL,orden TINYINT NOT NULL DEFAULT 0,activo TINYINT(1) NOT NULL DEFAULT 1,creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_usuario(usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
        require_once __DIR__.'/Php/cloudinary_upload.php';
        $result = cloudinary_upload($_FILES['archivo']['tmp_name'],'quibdoconecta/portafolio');
        if (!$result['ok']) { echo json_encode(['ok'=>false,'msg'=>$result['msg']]); exit; }
        $titulo = trim($_POST['titulo']??''); $desc = trim($_POST['descripcion']??'');
        $db->prepare("INSERT INTO talento_galeria (usuario_id,tipo,archivo,titulo,descripcion,activo) VALUES (?,?,?,?,?,1)")
           ->execute([$usuario['id'],'foto',$result['url'],$titulo,$desc]);
        echo json_encode(['ok'=>true,'url'=>$result['url']]);
        exit;
    }

    if ($action === 'eliminar_evidencia') {
        $id = (int)($_POST['id']??0);
        $db->prepare("UPDATE talento_galeria SET activo=0 WHERE id=? AND usuario_id=?")->execute([$id,$usuario['id']]);
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

try { $db->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}
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
$inicial         = strtoupper(mb_substr($nombreNegocio,0,1));
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
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root {
      --brand: #b45309;
      --brand2: #d97706;
      --brand-light: #fffbeb;
      --brand-mid: #fcd34d;
      --accent: #1f9d55;
      --accent-light: #f0fdf4;
      --danger: #e53935;
      --ink: #1c1007;
      --ink2: #3d2c0a;
      --ink3: #78611a;
      --ink4: #a89460;
      --surface: #ffffff;
      --surface2: #fffdf5;
      --surface3: #fef3c7;
      --border: rgba(180,83,9,.10);
      --border2: rgba(180,83,9,.22);
      --shadow: 0 1px 3px rgba(0,0,0,.06),0 4px 16px rgba(0,0,0,.05);
      --shadow2: 0 2px 8px rgba(0,0,0,.08),0 8px 32px rgba(0,0,0,.07);
      --radius: 14px;
      --radius-sm: 8px;
      --radius-lg: 20px;
      --nav-w: 240px;
      --top-h: 60px;
      --font: 'Plus Jakarta Sans','DM Sans',system-ui,sans-serif;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{font-size:15px;scroll-behavior:smooth}
    body{font-family:var(--font);background:#fdf8ee;color:var(--ink);min-height:100vh;display:flex}
    a{text-decoration:none;color:inherit}

    /* ── BANDERA CHOCÓ ── */
    .barra-bandera{position:fixed;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#1f9d55 33.3%,#d4a017 33.3% 66.6%,#1a3a6b 66.6%);z-index:9999}

    /* ── SIDEBAR ── */
    .sidebar{width:var(--nav-w);background:linear-gradient(180deg,#fff 0%,#fffdf5 100%);border-right:2px solid rgba(180,83,9,.10);display:flex;flex-direction:column;position:fixed;top:4px;left:0;bottom:0;z-index:150;transition:transform .3s ease;overflow-y:auto;overflow-x:hidden}
    .sidebar-logo{padding:14px 18px 12px;border-bottom:1px solid var(--border);display:none;align-items:center;gap:10px}
    .sidebar-logo img{height:32px}
    .sidebar-logo-txt{font-size:13px;font-weight:700;color:var(--brand);letter-spacing:-.2px;line-height:1.2}
    .sidebar-logo-sub{font-size:11px;color:var(--ink4);font-weight:400}
    .sidebar-user{margin:14px 14px 10px;background:var(--brand-light);border-radius:var(--radius);padding:14px;display:flex;align-items:center;gap:10px}
    .su-av{width:44px;height:44px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0;overflow:hidden;cursor:pointer;border:2px solid var(--brand-mid)}
    .su-av img{width:100%;height:100%;object-fit:cover}
    .su-name{font-size:13px;font-weight:700;color:var(--ink);line-height:1.2;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .su-role{font-size:11px;color:var(--brand);font-weight:600;margin-top:2px}
    .sidebar-nav{flex:1;padding:6px 10px;overflow-y:auto}
    .nav-section{margin-bottom:10px}
    .nav-section-label{font-size:10px;font-weight:700;color:var(--ink4);text-transform:uppercase;letter-spacing:.8px;padding:8px 10px 4px}
    .nav-item{display:flex;align-items:center;gap:9px;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:500;color:var(--ink3);transition:all .15s;position:relative;text-decoration:none}
    .nav-item:hover{background:var(--brand-light);color:var(--brand)}
    .nav-item.active{background:var(--brand-light);color:var(--brand);font-weight:700}
    .ni-ico{font-size:16px;flex-shrink:0;width:20px;text-align:center}
    .ni-badge{margin-left:auto;background:#e74c3c;color:#fff;font-size:10px;font-weight:800;padding:2px 6px;border-radius:10px}
    .sidebar-bottom{padding:12px 14px;border-top:1px solid var(--border)}
    .sidebar-plan{background:linear-gradient(135deg,#b45309 0%,#d97706 60%,#1a3a6b 100%);border-radius:var(--radius);padding:14px;color:#fff;margin-bottom:10px}
    .sp-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;opacity:.75;margin-bottom:2px}
    .sp-name{font-size:16px;font-weight:800;margin-bottom:10px}
    .sp-btn{display:block;text-align:center;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:7px;font-size:12px;font-weight:700;color:#fff;text-decoration:none;transition:.2s}
    .sp-btn:hover{background:rgba(255,255,255,.3)}
    .nav-salir{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:10px;font-size:13px;font-weight:600;color:#e53935;text-decoration:none;transition:.15s}
    .nav-salir:hover{background:#fff5f5}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:199}

    /* ── TOPBAR ── */
    .topbar{height:var(--top-h);background:linear-gradient(135deg,#fff 60%,#fffbeb 100%);border-bottom:2px solid rgba(180,83,9,.12);position:fixed;top:4px;left:var(--nav-w);right:0;z-index:250;display:flex;align-items:center;padding:0 28px;gap:16px;box-shadow:0 2px 12px rgba(180,83,9,.08)}
    .hamburger{display:none;flex-direction:column;gap:5px;background:none;border:none;cursor:pointer;padding:6px}
    .hamburger span{display:block;width:22px;height:2px;background:var(--ink);border-radius:2px;transition:.3s}
    .topbar-logo{display:none}
    .topbar-logo img{height:28px}
    .topbar-title{flex:1;font-size:17px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:6px;letter-spacing:-.3px}
    .topbar-title span{color:var(--brand)}
    .topbar-actions{display:flex;align-items:center;gap:10px;margin-left:auto}

    /* topbar notif panel */
    .tb-btn{position:relative;width:38px;height:38px;border-radius:10px;background:var(--brand-light);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;transition:.2s;flex-shrink:0}
    .tb-btn:hover{background:var(--surface3)}
    .tb-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:#e74c3c;border:2px solid #fff}
    .tb-notif-panel{display:none;position:absolute;top:48px;right:0;width:300px;background:#fff;border:1.5px solid var(--border2);border-radius:var(--radius);box-shadow:var(--shadow2);z-index:400}
    .tb-btn:focus-within .tb-notif-panel,.tb-btn.open .tb-notif-panel{display:block}
    .tnp-head{padding:12px 16px;font-size:13px;font-weight:700;border-bottom:1px solid var(--border);color:var(--ink)}
    .tnp-body{max-height:280px;overflow-y:auto}
    .tnp-item{padding:10px 16px;border-bottom:1px solid var(--border);font-size:12.5px;color:var(--ink2);line-height:1.4}
    .tnp-item:last-child{border-bottom:none}
    .tnp-empty{padding:20px 16px;text-align:center;font-size:12.5px;color:var(--ink4)}

    /* topbar avatar dropdown */
    .dash-avatar-btn{display:flex;align-items:center;gap:9px;padding:5px 10px 5px 5px;border-radius:var(--radius);border:1.5px solid var(--border);background:#fff;cursor:pointer;transition:.2s;user-select:none}
    .dash-avatar-btn:hover,.dash-avatar-btn.open{background:var(--brand-light);border-color:var(--brand-mid)}
    .dash-avatar-img{width:32px;height:32px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;overflow:hidden;flex-shrink:0}
    .dash-avatar-img img{width:100%;height:100%;object-fit:cover}
    .dash-avatar-nombre{font-size:13px;font-weight:700;color:var(--ink);max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .dash-avatar-sub{font-size:11px;color:var(--ink4);display:flex;align-items:center;gap:4px}
    .dash-online-dot{width:7px;height:7px;border-radius:50%;background:#22c55e;flex-shrink:0}
    .dash-avatar-arrow{font-size:10px;color:var(--ink4)}
    .dash-dropdown{display:none;position:absolute;top:52px;right:0;width:240px;background:#fff;border:1.5px solid var(--border2);border-radius:var(--radius);box-shadow:var(--shadow2);z-index:400;overflow:hidden}
    .dash-dropdown.visible{display:block}
    .dash-drop-header{padding:14px 16px;background:var(--brand-light);border-bottom:1px solid var(--border)}
    .dash-drop-av{width:40px;height:40px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;overflow:hidden;margin-bottom:8px}
    .dash-drop-av img{width:100%;height:100%;object-fit:cover}
    .dash-drop-nombre{font-size:13px;font-weight:700;color:var(--ink)}
    .dash-drop-tipo{font-size:11px;color:var(--brand);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
    .dash-drop-correo{font-size:11px;color:#999;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:150px}
    .dash-drop-menu{padding:6px}
    .dash-drop-link{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:8px;font-size:12.5px;font-weight:500;color:var(--ink2);text-decoration:none;transition:.15s}
    .dash-drop-link:hover{background:var(--brand-light);color:var(--brand)}
    .dash-dl-icon{font-size:15px;width:22px;text-align:center}
    .dash-dl-badge{margin-left:auto;background:#e74c3c;color:#fff;font-size:10px;font-weight:800;padding:2px 6px;border-radius:10px}
    .dash-drop-sep{height:1px;background:rgba(0,0,0,.06);margin:4px 0}
    .dash-drop-logout{color:#e74c3c!important}
    .dash-drop-logout .dash-dl-icon{background:#fff5f5!important}
    .dash-drop-logout:hover{background:#fff5f5!important;color:#c0392b!important}
    .dash-drop-badges{padding:8px 16px;border-bottom:1px solid var(--border)}

    /* ── MAIN ── */
    .main{margin-left:var(--nav-w);margin-top:var(--top-h);flex:1;min-width:0;padding:28px;background:#fdf8ee}

    /* ── ALERT BAR ── */
    .alert-bar{display:flex;align-items:center;gap:12px;padding:12px 18px;border-radius:var(--radius);margin-bottom:20px;border:1.5px solid}
    .a-ico{font-size:20px;flex-shrink:0}
    .a-txt{flex:1;font-size:12.5px;line-height:1.5}
    .a-txt strong{display:block;font-size:13px;font-weight:700;margin-bottom:1px}
    .a-btn{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;background:var(--brand);color:#fff;transition:.2s}
    .a-btn:hover{background:var(--brand2)}
    .as{background:#fffbeb;border-color:#fcd34d;color:#92400e}
    .ap{background:#fffbeb;border-color:#fcd34d;color:#92400e}
    .ar{background:#fff1f2;border-color:#fca5a5;color:#991b1b}
    .av{background:#f0fdf4;border-color:#bbf7d0;color:#166534}

    /* ── HERO STRIP ── */
    .hero-strip{background:linear-gradient(135deg,#fff 0%,#fffbeb 60%,#f0fdf4 100%);border:1px solid rgba(180,83,9,.13);border-radius:var(--radius-lg);padding:24px 28px;display:flex;align-items:center;gap:20px;margin-bottom:24px;position:relative;overflow:hidden}
    .hero-strip::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#d97706 33.3%,#1f9d55 33.3% 66.6%,#1a3a6b 66.6%)}
    .hero-av{width:72px;height:72px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:800;flex-shrink:0;overflow:hidden;cursor:pointer;border:3px solid var(--brand-light);transition:border-color .2s}
    .hero-av:hover{border-color:var(--brand)}
    .hero-av img{width:100%;height:100%;object-fit:cover}
    .hero-info{flex:1;min-width:0}
    .hero-chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:7px}
    .hchip{display:inline-flex;align-items:center;gap:4px;padding:3px 11px;border-radius:20px;font-size:11px;font-weight:700}
    .hc-tipo{background:#fef3c7;color:#92400e}
    .hc-cc{background:#fef9c3;color:#78350f}
    .hc-v{background:#dcfce7;color:#166534}
    .hc-p{background:#fef3c7;color:#92400e}
    .hc-top{background:#fee2e2;color:#991b1b}
    .hc-dest{background:#f3e8ff;color:#6b21a8}
    .hero-name{font-size:22px;font-weight:800;color:var(--ink);letter-spacing:-.3px}
    .hero-sub{font-size:13px;color:var(--ink3);margin-top:3px}
    .hero-top-row{display:flex;align-items:center;gap:16px;flex:1;min-width:0}
    .hero-stats{display:flex;gap:20px;flex-shrink:0}
    .hs{text-align:center;padding:0 10px;border-left:1px solid var(--border)}
    .hs:first-child{border-left:none}
    .hs-val{font-size:20px;font-weight:800;color:var(--brand)}
    .hs-lab{font-size:11px;color:var(--ink4);margin-top:2px;font-weight:500}
    .hero-actions{display:flex;gap:8px;flex-shrink:0}

    /* ── BUTTONS ── */
    .btn-primary{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--radius-sm);background:var(--brand);color:#fff;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:var(--font);text-decoration:none;transition:.2s;white-space:nowrap}
    .btn-primary:hover{background:var(--brand2);transform:translateY(-1px);box-shadow:0 4px 12px rgba(180,83,9,.25)}
    .btn-secondary{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--radius-sm);background:#fff;color:var(--brand);font-size:13px;font-weight:700;border:1.5px solid var(--border2);cursor:pointer;font-family:var(--font);text-decoration:none;transition:.2s;white-space:nowrap}
    .btn-secondary:hover{background:var(--brand-light)}

    /* ── DASHBOARD GRID ── */
    .dashboard-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:18px}
    .col-3{grid-column:span 3}.col-4{grid-column:span 4}.col-6{grid-column:span 6}.col-8{grid-column:span 8}.col-12{grid-column:span 12}

    /* ── CARDS ── */
    .card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow)}
    .card-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 0}
    .card-title{font-size:14px;font-weight:700;color:var(--ink)}
    .card-body{padding:18px 20px}

    /* ── METRIC CARDS ── */
    .metric-card{padding:18px 20px;display:flex;align-items:center;gap:14px;height:100%}
    .mc-ico{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
    .mc-ico.g{background:#fef3c7}.mc-ico.a{background:#fef3c7}.mc-ico.o{background:#fef3c7}.mc-ico.b{background:#eff6ff}
    .mc-val{font-size:24px;font-weight:800;color:var(--ink)}
    .mc-lab{font-size:12px;color:var(--ink3);margin-top:2px}
    .mc-sub{font-size:11px;color:var(--brand);font-weight:600;margin-top:4px;cursor:pointer}

    /* ── PROFILE CARD ── */
    .profile-card{padding:0 20px 20px;display:flex;flex-direction:column;gap:0}
    .pc-av{width:68px;height:68px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;overflow:hidden;cursor:pointer;border:3px solid var(--brand-light);margin:16px auto 10px;transition:border-color .2s;flex-shrink:0}
    .pc-av:hover{border-color:var(--brand)}
    .pc-av img{width:100%;height:100%;object-fit:cover}
    .pc-name{font-size:15px;font-weight:800;color:var(--ink);text-align:center;margin-bottom:2px}
    .pc-role{font-size:12px;color:var(--brand);font-weight:600;text-align:center;margin-bottom:12px}
    .pc-info{display:flex;flex-direction:column;gap:7px;padding-top:12px;border-top:1px solid var(--border)}
    .pc-row{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--ink2)}
    .pc-ico{font-size:14px;flex-shrink:0}

    /* ── TOGGLE ── */
    .vis-row{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--surface2);border-radius:10px;border:1px solid var(--border);margin-top:12px}
    .vis-lab{font-size:12.5px;font-weight:700;color:var(--ink2)}
    .vis-sub{font-size:10.5px;color:var(--ink3);margin-top:1px}
    .toggle{position:relative;width:42px;height:22px;cursor:pointer;display:inline-block}
    .toggle input{opacity:0;width:0;height:0}
    .toggle-slider{position:absolute;inset:0;border-radius:11px;background:#cbd5e1;transition:.3s}
    .toggle-slider::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;border-radius:50%;background:white;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
    .toggle input:checked + .toggle-slider{background:var(--brand)}
    .toggle input:checked + .toggle-slider::before{transform:translateX(20px)}
    .pv-chip{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px}
    .pv-chip.ok{background:#fef3c7;color:#92400e}
    .pv-chip.off{background:#f1f5f9;color:#64748b}

    /* ── PROGRESS BAR ── */
    .prog-w{padding:14px 20px 0}
    .prog-h{display:flex;justify-content:space-between;font-size:11.5px;font-weight:700;margin-bottom:5px;color:var(--ink3)}
    .prog-t{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden}
    .prog-f{height:100%;background:linear-gradient(90deg,var(--brand),var(--brand2));border-radius:4px;transition:width 1s ease}

    /* ── QUICK ACTIONS ── */
    .actions-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:9px;padding:16px 20px}
    .action-btn{display:flex;flex-direction:column;align-items:center;gap:5px;padding:14px 8px;border-radius:var(--radius-sm);background:var(--surface2);border:1px solid var(--border);text-decoration:none;transition:.2s;cursor:pointer;position:relative}
    .action-btn:hover{background:var(--brand-light);border-color:var(--brand-mid);transform:translateY(-2px);box-shadow:0 4px 12px rgba(180,83,9,.10)}
    .ab-ico{font-size:22px}
    .ab-label{font-size:11.5px;font-weight:700;color:var(--ink2);text-align:center}
    .ab-desc{font-size:10.5px;color:var(--ink3);text-align:center}
    .ab-badge{position:absolute;top:-6px;right:-6px;background:#e74c3c;color:#fff;font-size:9px;font-weight:800;padding:2px 6px;border-radius:10px}

    /* ── GALLERY ── */
    .galeria-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(85px,1fr));gap:8px;padding:12px 20px 20px}
    .gal-item{aspect-ratio:1;border-radius:10px;overflow:hidden;background:#e5e7eb;cursor:pointer;position:relative}
    .gal-item img{width:100%;height:100%;object-fit:cover}
    .gal-item:hover .gal-del{opacity:1}
    .gal-del{position:absolute;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;opacity:0;transition:.2s;cursor:pointer;font-size:18px}
    .gal-add{aspect-ratio:1;border-radius:10px;border:2px dashed var(--brand-mid);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;transition:.2s}
    .gal-add:hover{background:var(--brand-light);border-color:var(--brand)}
    .gal-add-ico{font-size:20px}
    .gal-add-txt{font-size:10.5px;font-weight:700;color:var(--brand)}

    /* ── PLAN CARD ── */
    .plan-bar-item{background:#fff;border:1px solid var(--border);border-radius:10px;padding:12px 14px}

    /* ── ACTIVITY ── */
    .activity-item{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
    .activity-item:last-child{border-bottom:none}
    .act-dot-w{width:8px;height:8px;border-radius:50%;background:var(--brand-mid);margin-top:5px;flex-shrink:0}
    .act-body{flex:1;font-size:12.5px;color:var(--ink2)}
    .act-time{font-size:11px;color:var(--ink4);flex-shrink:0;margin-top:2px}

    /* ── BANNER ZONE ── */
    .banner-zone{position:relative;height:140px;background:linear-gradient(135deg,#78350f,#b45309);cursor:pointer;overflow:hidden;border-radius:var(--radius) var(--radius) 0 0}
    .banner-zone img{width:100%;height:100%;object-fit:cover;display:block}
    .banner-overlay{position:absolute;inset:0;background:rgba(0,0,0,0);transition:.2s;display:flex;align-items:center;justify-content:center}
    .banner-overlay:hover{background:rgba(0,0,0,.3)}
    .banner-overlay span{opacity:0;color:#fff;font-size:12px;font-weight:700;background:rgba(0,0,0,.5);padding:6px 14px;border-radius:20px;transition:.2s}
    .banner-overlay:hover span{opacity:1}
    .banner-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;color:rgba(255,255,255,.55)}
    .bp-ico{font-size:28px}
    .bp-txt{font-size:12px;font-weight:600}
    .bp-sub{font-size:10.5px;opacity:.7}
    .logo-row{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:0 22px 18px;margin-top:-42px;position:relative;z-index:2}
    .logo-av{width:84px;height:84px;border-radius:16px;border:4px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,.16);background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;color:#fff;cursor:pointer;overflow:hidden}
    .logo-av img{width:100%;height:100%;object-fit:cover}
    .logo-edit-btn{position:absolute;bottom:2px;right:2px;width:26px;height:26px;border-radius:50%;background:var(--brand);border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px}

    /* ── MODAL ── */
    .modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:500;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
    .modal-ov.open{display:flex}
    .modal-box{background:#fff;border-radius:20px;max-width:560px;width:100%;box-shadow:0 30px 80px rgba(0,0,0,.2);animation:fadeUp .3s ease;max-height:90vh;overflow-y:auto;position:relative}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .mcerrar{position:absolute;top:14px;right:16px;background:none;border:none;font-size:18px;cursor:pointer;color:var(--ink3)}
    .mcerrar:hover{color:var(--ink)}
    .modal-pad{padding:28px}
    .mtit{font-size:18px;font-weight:800;color:var(--ink);margin-bottom:4px}
    .msub{font-size:12.5px;color:var(--ink3);margin-bottom:18px;line-height:1.5}
    .msec{font-size:11px;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:.7px;margin:14px 0 7px}
    .mmsg{display:none;padding:9px 13px;border-radius:9px;font-size:12.5px;font-weight:700;margin-bottom:12px}
    .mmsg.success{background:#dcfce7;color:#166534}
    .mmsg.error{background:#fee2e2;color:#991b1b}
    .mfila{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:7px}
    .mgr{display:flex;flex-direction:column;gap:4px}
    .mgr.full{grid-column:1/-1}
    .mgr label{font-size:11.5px;font-weight:700;color:var(--ink3)}
    .mgr input,.mgr select,.mgr textarea{border:1.5px solid var(--border);border-radius:9px;padding:9px 11px;font-size:12.5px;font-family:var(--font);color:var(--ink);background:var(--surface2);transition:border-color .2s;outline:none;resize:none}
    .mgr input:focus,.mgr select:focus,.mgr textarea:focus{border-color:var(--brand)}
    .btn-save{display:block;width:100%;padding:13px;border-radius:var(--radius);background:var(--brand);color:#fff;font-size:14px;font-weight:800;border:none;cursor:pointer;font-family:var(--font);margin-top:18px;transition:background .2s}
    .btn-save:hover{background:var(--brand2)}
    .btn-save:disabled{opacity:.6;cursor:not-allowed}

    /* ── BANNER CROP MODAL ── */
    #cropBannerModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:99999;align-items:center;justify-content:center;padding:20px}

    /* ── RESPONSIVE ── */
    @media(max-width:1100px){.col-4{grid-column:span 6}.col-3{grid-column:span 6}}
    @media(max-width:900px){
      :root{--nav-w:0px}
      .hamburger{display:flex}
      .topbar-logo{display:block}
      .sidebar{transform:translateX(-240px);top:0;z-index:350;width:240px}
      .sidebar-logo{display:flex}
      .sidebar.open{transform:translateX(0)}
      .sidebar-overlay.open{display:block}
      .topbar{left:0}
      .main{padding:18px 16px;margin-left:0}
      .hero-stats{display:none}
      .hero-actions .btn-primary,.hero-actions .btn-secondary{flex:1;justify-content:center;font-size:12px;padding:9px 10px}
      .col-4,.col-6,.col-8,.col-3{grid-column:span 12}
    }
    @media(max-width:600px){.mfila{grid-template-columns:1fr}.modal-pad{padding:20px 16px 18px}}
  </style>
</head>
<body>
  <div class="barra-bandera"></div>
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="Imagenes/quibdo_desco_new.png" alt="QuibdóConecta">
      <div>
        <div class="sidebar-logo-txt">QuibdóConecta</div>
        <div class="sidebar-logo-sub">Conectando el Chocó</div>
      </div>
    </div>
    <div class="sidebar-user">
      <div class="su-av" id="sidebarAvatar" onclick="abrirModal()" title="Editar foto">
        <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Logo"><?php else: ?><?=$inicial?><?php endif; ?>
      </div>
      <div>
        <div class="su-name"><?=$nombreNegocio?></div>
        <div class="su-role">🏪 Negocio Local</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard_negocios.php" class="nav-item active"><span class="ni-ico">🏠</span> Panel</a>
        <a href="chat.php" class="nav-item"><span class="ni-ico">💬</span> Mensajes<?php if ($chatNoLeidos>0): ?><span class="ni-badge"><?=$chatNoLeidos?></span><?php endif; ?></a>
      </div>
      <div class="nav-section">
        <div class="nav-section-label">Directorio</div>
        <a href="Empleo.php" class="nav-item"><span class="ni-ico">💼</span> Empleos</a>
        <a href="talentos.php" class="nav-item"><span class="ni-ico">🌟</span> Talentos</a>
        <a href="empresas.php" class="nav-item"><span class="ni-ico">🏢</span> Empresas</a>
        <a href="negocios.php" class="nav-item"><span class="ni-ico">🏪</span> Negocios</a>
        <a href="servicios.php" class="nav-item"><span class="ni-ico">🎧</span> Eventos</a>
        <a href="convocatorias.php" class="nav-item"><span class="ni-ico">📢</span> Convocatorias</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-label">Mi cuenta</div>
        <a href="verificar_cuenta.php" class="nav-item"><span class="ni-ico">🪪</span> Verificación</a>
        <a href="Ayuda.html" class="nav-item"><span class="ni-ico">❓</span> Ayuda</a>
      </div>
    </nav>
    <div class="sidebar-bottom">
      <?php if (!empty($datosPlan)): ?>
      <div class="sidebar-plan">
        <div class="sp-label">Plan activo</div>
        <div class="sp-name"><?=htmlspecialchars($datosPlan['nombre']??'Semilla')?></div>
        <a href="empresas.php#precios" class="sp-btn">✦ Mejorar plan</a>
      </div>
      <?php endif; ?>
      <a href="Php/logout.php" class="nav-salir"><span>🚪</span> Cerrar sesión</a>
    </div>
  </aside>

  <!-- TOPBAR -->
  <div class="topbar">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Menú"><span></span><span></span><span></span></button>
    <a href="index.html" class="topbar-logo" title="Ir al inicio">
      <img src="Imagenes/quibdo_desco_new.png" alt="QuibdóConecta">
    </a>
    <div class="topbar-title">
      <span title="Departamento del Chocó">
        <svg width="22" height="15" viewBox="0 0 22 15" xmlns="http://www.w3.org/2000/svg" style="border-radius:2px;vertical-align:middle;margin-right:6px;box-shadow:0 1px 3px rgba(0,0,0,.2)">
          <rect width="22" height="5" y="0" fill="#1f9d55"/>
          <rect width="22" height="5" y="5" fill="#d4a017"/>
          <rect width="22" height="5" y="10" fill="#1a3a6b"/>
        </svg>
      </span>Mi <span>Panel de Negocios</span>
    </div>
    <div class="topbar-actions">
      <button class="btn-primary" onclick="abrirModal()" style="display:flex;align-items:center;gap:5px">✏️ Editar negocio</button>
      <div style="position:relative">
        <div class="tb-btn" id="navNotif" title="Notificaciones">🔔<div class="tb-dot" id="notifDot" style="display:none"></div>
          <div class="tb-notif-panel" id="notifPanel">
            <div class="tnp-head">🔔 Notificaciones</div>
            <div class="tnp-body"><div id="notifLista"><div class="tnp-empty">Cargando…</div></div></div>
          </div>
        </div>
      </div>
      <div style="position:relative" id="dashUserWidget">
        <div class="dash-avatar-btn" id="dashAvatarBtn" title="Mi cuenta">
          <div class="dash-avatar-img" id="dashAvatarImg">
            <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Logo"><?php else: ?><?=$inicial?><?php endif; ?>
          </div>
          <div class="dash-avatar-info">
            <span class="dash-avatar-nombre"><?=$nombreNegocio?></span>
            <span class="dash-avatar-sub"><span class="dash-online-dot"></span>En línea <span class="dash-avatar-arrow">▾</span></span>
          </div>
        </div>
        <div class="dash-dropdown" id="dashDropdown">
          <div class="dash-drop-header">
            <div class="dash-drop-av" id="dropAv"><?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Logo"><?php else: ?><?=$inicial?><?php endif; ?></div>
            <div>
              <div class="dash-drop-nombre"><?=$nombreNegocio?></div>
              <div class="dash-drop-tipo">🏪 Negocio Local</div>
              <div class="dash-drop-correo"><?=$correo?></div>
            </div>
          </div>
          <?php if (!empty($badgesHTML)): ?><div class="dash-drop-badges"><?=$badgesHTML?></div><?php endif; ?>
          <div class="dash-drop-menu">
            <a href="dashboard_negocios.php" class="dash-drop-link"><span class="dash-dl-icon">🏠</span> Mi panel</a>
            <a href="chat.php" class="dash-drop-link"><?php if ($chatNoLeidos>0): ?><span class="dash-dl-badge"><?=$chatNoLeidos?></span><?php endif; ?><span class="dash-dl-icon">💬</span> Mensajes</a>
            <a href="verificar_cuenta.php" class="dash-drop-link"><span class="dash-dl-icon">🪪</span> Verificación</a>
            <div class="dash-drop-sep"></div>
            <a href="Php/logout.php" class="dash-drop-link dash-drop-logout"><span class="dash-dl-icon">🚪</span> Cerrar sesión</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN -->
  <main class="main">

    <!-- ALERT -->
    <?php if (!$tieneVerificado): ?>
      <?php if ($estadoVerif==='pendiente'): ?>
        <div class="alert-bar ap"><div class="a-ico">⏳</div><div class="a-txt"><strong>Documentos en revisión</strong><span>El administrador está revisando tu RUT o registro mercantil.</span></div></div>
      <?php elseif ($estadoVerif==='rechazado'): ?>
        <div class="alert-bar ar"><div class="a-ico">❌</div><div class="a-txt"><strong>Verificación rechazada</strong><span>Sube los documentos con mejor calidad.</span></div><a href="verificar_cuenta.php" class="a-btn">Reintentar</a></div>
      <?php else: ?>
        <div class="alert-bar as"><div class="a-ico">🏪</div><div class="a-txt"><strong>Verifica tu negocio</strong><span>Sube tu RUT o registro mercantil y obtén el badge de Negocio Verificado.</span></div><a href="verificar_cuenta.php" class="a-btn">Verificar</a></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert-bar av"><div class="a-ico">✅</div><div class="a-txt"><strong>Negocio verificado</strong><span>Los clientes ven tu badge de Negocio Verificado.</span></div></div>
    <?php endif; ?>

    <!-- HERO STRIP -->
    <div class="hero-strip">
      <div class="hero-top-row">
        <div class="hero-av" id="heroAvatar" onclick="abrirModal()" title="Cambiar foto">
          <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Logo"><?php else: ?><?=$inicial?><?php endif; ?>
        </div>
        <div class="hero-info">
          <div class="hero-chips">
            <span class="hchip <?=$esCC?'hc-cc':'hc-tipo'?>"><?=$esCC?'🏬 C.C. El Caraño':'🏪 Negocio Local'?></span>
            <?php if ($tieneVerificado): ?><span class="hchip hc-v">✓ Verificado</span><?php endif; ?>
            <?php if ($tienePremium): ?><span class="hchip hc-p">⭐ Premium</span><?php endif; ?>
            <?php if ($tieneTop): ?><span class="hchip hc-top">👑 Top</span><?php endif; ?>
            <?php if ($tieneDestacado): ?><span class="hchip hc-dest">🏅 Destacado</span><?php endif; ?>
          </div>
          <div class="hero-name" id="dNombreHero"><?=$nombreNegocio?></div>
          <div class="hero-sub" id="dCatHero"><?=$categoria?:''?><?=$ciudad?' · 📍 '.$ciudad:''?></div>
        </div>
      </div>
      <div class="hero-stats">
        <div class="hs"><div class="hs-val"><?=$vistasTotal?></div><div class="hs-lab">Visitas</div></div>
        <div class="hs"><div class="hs-val"><?=$vistas7dias>0?'+'.$vistas7dias:'0'?></div><div class="hs-lab">Esta semana</div></div>
        <div class="hs"><div class="hs-val"><?=$chatNoLeidos?></div><div class="hs-lab">Mensajes</div></div>
        <div class="hs"><div class="hs-val"><?=$pct?>%</div><div class="hs-lab">Perfil</div></div>
      </div>
      <div class="hero-actions">
        <button class="btn-primary" onclick="abrirModal()">✏️ Editar</button>
        <a href="negocios.php" class="btn-secondary">🌐 Directorio</a>
      </div>
    </div>

    <!-- DASHBOARD GRID -->
    <div class="dashboard-grid">

      <!-- MÉTRICAS -->
      <div class="col-4">
        <div class="card metric-card">
          <div class="mc-ico g">👁️</div>
          <div>
            <div class="mc-val"><?=$vistasTotal?></div>
            <div class="mc-lab">Visitas al negocio</div>
            <div class="mc-sub" onclick="location.href='negocios.php'"><?=$vistas7dias>0?'+'.$vistas7dias.' esta semana →':'Ver directorio →'?></div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="card metric-card" onclick="location.href='chat.php'" style="cursor:pointer">
          <div class="mc-ico o">💬</div>
          <div>
            <div class="mc-val"><?=$chatNoLeidos?:'0'?></div>
            <div class="mc-lab">Mensajes sin leer</div>
            <div class="mc-sub">Ir al chat →</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="card metric-card">
          <div class="mc-ico a">📸</div>
          <div>
            <div class="mc-val"><?=$galeriaTotal?></div>
            <div class="mc-lab">Fotos en galería</div>
            <div class="mc-sub" onclick="abrirModalGaleria()">Añadir fotos →</div>
          </div>
        </div>
      </div>

      <!-- BANNER + LOGO CARD -->
      <div class="col-8">
        <div class="card" style="padding:0;overflow:hidden">
          <!-- Banner -->
          <div class="banner-zone" id="bannerZone" onclick="document.getElementById('bannerInput').click()" title="Cambiar banner">
            <?php if ($bannerUrl): ?>
              <img id="bannerImg" src="<?=$bannerUrl?>" alt="Banner">
            <?php else: ?>
              <div class="banner-placeholder" id="bannerPlaceholder">
                <div class="bp-ico">🖼️</div>
                <div class="bp-txt">Haz clic para subir el banner</div>
                <div class="bp-sub">1200×300px · JPG/PNG/WEBP · máx 5MB</div>
              </div>
            <?php endif; ?>
            <div class="banner-overlay"><span>✏️ Cambiar banner</span></div>
            <?php if ($bannerUrl): ?>
            <button onclick="event.stopPropagation();eliminarBanner()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:20px;padding:4px 11px;font-size:11px;font-weight:700;cursor:pointer">🗑 Quitar</button>
            <?php endif; ?>
          </div>
          <input type="file" id="bannerInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirBanner(this)">
          <!-- Logo row -->
          <div class="logo-row">
            <div style="position:relative;display:inline-block">
              <div class="logo-av" id="logoAvCard" onclick="abrirModal()">
                <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Logo"><?php else: ?><?=$iniciales?><?php endif; ?>
              </div>
              <div class="logo-edit-btn" onclick="abrirModal()">✏️</div>
            </div>
            <div style="flex:1;min-width:120px;padding-top:46px">
              <div style="font-size:17px;font-weight:800;color:var(--ink)" id="dNombreNeg"><?=$nombreNegocio?></div>
              <div style="font-size:12.5px;color:var(--ink3);margin-top:2px" id="dCategoria"><?=$categoria?:''?><?php if ($ciudad): ?> · 📍 <?=$ciudad?><?php endif; ?></div>
            </div>
            <div style="display:flex;gap:8px;padding-top:46px;flex-wrap:wrap">
              <a href="perfil.php?id=<?=$usuario['id']?>" target="_blank" class="btn-secondary" style="font-size:12px;padding:7px 13px">👁 Ver perfil</a>
              <button onclick="abrirModal()" class="btn-primary" style="font-size:12px;padding:7px 13px">✏️ Editar</button>
            </div>
          </div>
          <div id="bannerMsg" style="font-size:12px;color:#e53935;margin:0 22px 14px;display:none"></div>
        </div>
      </div>

      <!-- PERFIL CARD -->
      <div class="col-4">
        <div class="card" style="height:100%">
          <div class="card-header">
            <div class="card-title">🏪 Mi negocio</div>
            <button class="btn-secondary" onclick="abrirModal()" style="padding:5px 12px;font-size:12px">Editar</button>
          </div>
          <div class="profile-card">
            <div class="pc-av" id="cpAvatarCard" onclick="abrirModal()">
              <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Logo"><?php else: ?><?=$iniciales?><?php endif; ?>
            </div>
            <div class="pc-name" id="dNombre2"><?=$nombreNegocio?></div>
            <div class="pc-role"><?=$categoria?:($esCC?'C.C. El Caraño':'Negocio local')?></div>
            <div class="pc-info">
              <?php if ($ciudad): ?><div class="pc-row"><span class="pc-ico">📍</span><span><?=$ciudad?></span></div><?php endif; ?>
              <?php if ($direccion): ?><div class="pc-row"><span class="pc-ico">🗺️</span><span><?=$direccion?></span></div><?php endif; ?>
              <?php if ($whatsapp): ?><div class="pc-row"><span class="pc-ico">💬</span><span><?=$whatsapp?></span></div><?php endif; ?>
              <?php if ($horario): ?><div class="pc-row"><span class="pc-ico">🕐</span><span><?=$horario?></span></div><?php endif; ?>
              <div class="pc-row"><span class="pc-ico">✉️</span><span><?=$correo?></span></div>
              <?php if (!empty($badgesHTML)): ?><div style="margin-top:8px"><?=$badgesHTML?></div><?php endif; ?>
            </div>
          </div>
          <div class="vis-row" style="margin:0 20px 12px">
            <div><div class="vis-lab">Visible en directorio</div><div class="vis-sub">Aparece en Negocios públicos</div></div>
            <div style="display:flex;align-items:center;gap:7px">
              <span id="visChip" class="pv-chip <?=$visibleEnWeb?'ok':'off'?>"><?=$visibleEnWeb?'🟢 Visible':'🟡 Oculto'?></span>
              <label class="toggle">
                <input type="checkbox" id="toggleVisible" <?=$visibleEnWeb?'checked':''?> onchange="toggleVisibilidad(this.checked)">
                <span class="toggle-slider"></span>
              </label>
            </div>
          </div>
          <div class="prog-w" style="padding:0 20px 16px">
            <div class="prog-h"><span>Perfil completado</span><span><?=$pct?>%</span></div>
            <div class="prog-t"><div class="prog-f" id="progBar" style="width:0%"></div></div>
          </div>
        </div>
      </div>

      <!-- ACCIONES RÁPIDAS -->
      <div class="col-12">
        <div class="card">
          <div class="card-header"><div class="card-title">⚡ Acciones rápidas</div></div>
          <div class="actions-grid">
            <a href="chat.php" class="action-btn">
              <div class="ab-ico">💬</div><div class="ab-label">Mensajes</div>
              <?php if ($chatNoLeidos>0): ?><span class="ab-badge"><?=$chatNoLeidos?></span><?php else: ?><div class="ab-desc">Sin nuevos</div><?php endif; ?>
            </a>
            <a href="negocios.php" class="action-btn"><div class="ab-ico">🏪</div><div class="ab-label">Ver directorio</div><div class="ab-desc">Negocios del Chocó</div></a>
            <a href="javascript:void(0)" onclick="abrirModal()" class="action-btn"><div class="ab-ico">✏️</div><div class="ab-label">Editar negocio</div><div class="ab-desc">Actualizar datos</div></a>
            <a href="javascript:void(0)" onclick="abrirModalGaleria()" class="action-btn"><div class="ab-ico">📸</div><div class="ab-label">Galería</div><div class="ab-desc"><?=$galeriaTotal?> fotos</div></a>
            <a href="verificar_cuenta.php" class="action-btn"><div class="ab-ico">🪪</div><div class="ab-label">Verificación</div><div class="ab-desc"><?=$tieneVerificado?'✅ Verificado':'Subir docs'?></div></a>
            <a href="buscar.php" class="action-btn"><div class="ab-ico">🔍</div><div class="ab-label">Buscar</div><div class="ab-desc">Talentos / empresas</div></a>
            <a href="Ayuda.html" class="action-btn"><div class="ab-ico">❓</div><div class="ab-label">Ayuda</div><div class="ab-desc">Soporte y guías</div></a>
          </div>
        </div>
      </div>

      <!-- GALERÍA -->
      <div class="col-8">
        <div class="card">
          <div class="card-header">
            <div class="card-title">📸 Galería del negocio <span style="background:var(--surface3);color:var(--brand);font-size:10px;padding:2px 8px;border-radius:20px;font-weight:700;margin-left:6px"><?=$galeriaTotal?> fotos</span></div>
            <button onclick="abrirModalGaleria()" class="btn-primary" style="font-size:12px;padding:6px 13px">+ Añadir</button>
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
            <div style="text-align:center;padding:20px 20px 28px;color:var(--ink3)">
              <div style="font-size:36px;margin-bottom:8px">📷</div>
              <div style="font-size:13px;font-weight:700;margin-bottom:4px">Sin fotos aún</div>
              <div style="font-size:12px">Muestra tu negocio con fotos del local, productos y ambiente</div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ACTIVIDAD + PLAN -->
      <div class="col-4" style="display:flex;flex-direction:column;gap:18px">

        <!-- ACTIVIDAD -->
        <div class="card">
          <div class="card-header"><div class="card-title">🕐 Actividad reciente</div></div>
          <div class="card-body" style="padding-top:12px;display:flex;flex-direction:column">
            <div class="activity-item">
              <div class="act-dot-w"></div>
              <div class="act-body"><strong>Negocio registrado</strong></div>
              <div class="act-time"><?=$fechaRegistro?></div>
            </div>
            <div class="activity-item">
              <div class="act-dot-w"></div>
              <div class="act-body"><?=$vistasTotal?> visitas acumuladas<?=$vistas7dias>0?' · +'.$vistas7dias.' esta semana':''?></div>
              <div class="act-time">Hoy</div>
            </div>
            <?php if ($galeriaTotal>0): ?>
            <div class="activity-item">
              <div class="act-dot-w"></div>
              <div class="act-body"><?=$galeriaTotal?> fotos en galería</div>
              <div class="act-time">Activo</div>
            </div>
            <?php endif; ?>
            <?php if ($tieneVerificado): ?>
            <div class="activity-item">
              <div class="act-dot-w" style="background:#22c55e"></div>
              <div class="act-body"><strong style="color:#166534">✅ Negocio Verificado</strong></div>
              <div class="act-time">Badge</div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- PLAN ACTIVO -->
        <?php if (!empty($datosPlan)): ?>
        <?php $usados=$datosPlan['usados']??[];$cfg=$datosPlan['config']??[]; ?>
        <div class="card" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1.5px solid #fcd34d">
          <div class="card-header" style="padding:16px 18px 0">
            <div style="display:flex;align-items:center;gap:10px">
              <div style="width:40px;height:40px;border-radius:12px;background:var(--brand);display:flex;align-items:center;justify-content:center;font-size:18px">⭐</div>
              <div>
                <div style="font-size:10px;font-weight:700;color:var(--brand);text-transform:uppercase;letter-spacing:1px">Plan activo</div>
                <div style="font-size:18px;font-weight:800;color:var(--ink)"><?=htmlspecialchars($datosPlan['nombre']??'Semilla')?></div>
              </div>
            </div>
            <a href="empresas.php#planes" class="btn-primary" style="font-size:11px;padding:6px 12px">✦ Mejorar</a>
          </div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:8px;padding-top:14px">
            <?php foreach (['mensajes'=>['💬','Mensajes'],'vacantes'=>['💼','Vacantes']] as $key=>[$ico,$lab]):
              $lim=$cfg[$key]??0; if ($lim===0) continue;
              $uso=$usados[$key]??0; $inf=($lim===-1);
              $pctB=$inf?10:min(100,($uso/max(1,$lim))*100);
              $col=$pctB>=90?'#e53935':($pctB>=70?'#fb8c00':'var(--brand)');
            ?>
            <div class="plan-bar-item">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
                <span style="font-size:12px;font-weight:600;color:var(--ink3)"><?=$ico?> <?=$lab?></span>
                <span style="font-size:13px;font-weight:800;color:var(--ink)"><?=$uso?><span style="font-size:10px;font-weight:400;color:var(--ink4)"> / <?=$inf?'∞':$lim?></span></span>
              </div>
              <div style="height:5px;background:rgba(0,0,0,.07);border-radius:4px"><div style="height:5px;width:<?=$pctB?>%;background:<?=$col?>;border-radius:4px"></div></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /col-4 -->

    </div><!-- /dashboard-grid -->
  </main>

  <!-- MODAL CROP BANNER -->
  <div id="cropBannerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:99999;align-items:center;justify-content:center;padding:20px">
    <div style="background:#fff;border-radius:18px;padding:22px;max-width:680px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.5)">
      <div style="font-size:15px;font-weight:800;color:var(--brand);margin-bottom:12px;text-align:center">🖼️ Encuadra el banner</div>
      <div style="position:relative;width:100%;height:200px;overflow:hidden;border-radius:10px;background:#000;display:flex;align-items:center;justify-content:center">
        <img id="cropBannerImg" style="max-width:100%;display:block">
      </div>
      <p style="font-size:11.5px;color:#64748b;text-align:center;margin:9px 0">Arrastra y ajusta · Proporción 4:1</p>
      <div style="display:flex;gap:9px;margin-top:4px">
        <button onclick="cancelarCropBanner()" style="flex:1;padding:10px;border-radius:9px;border:1px solid #e0e0e0;background:#f5f5f5;font-size:12.5px;font-weight:700;cursor:pointer">Cancelar</button>
        <button onclick="confirmarCropBanner()" id="btnConfirmarCropBanner" class="btn-primary" style="flex:2;justify-content:center">✅ Usar banner</button>
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
        <div style="border:2px dashed var(--brand-mid);border-radius:12px;padding:24px;text-align:center;cursor:pointer;background:var(--brand-light);transition:.2s" onclick="document.getElementById('galFile').click()" onmouseover="this.style.background='#fef3c7'" onmouseout="this.style.background='var(--brand-light)'">
          <div style="font-size:32px;margin-bottom:7px">📷</div>
          <div style="font-size:13px;font-weight:700;color:var(--brand)">Haz clic para seleccionar imagen</div>
          <div style="font-size:11.5px;color:var(--ink3);margin-top:3px">JPG, PNG, WEBP · máx 5 MB</div>
        </div>
        <input type="file" id="galFile" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirEvidencia(this)">
        <div id="galProgress" style="display:none;margin-top:12px;font-size:12.5px;color:var(--brand);font-weight:600;text-align:center">⏳ Subiendo…</div>
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
        <div class="msec">Foto / Logo principal</div>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
          <div id="fotoPreview" style="width:68px;height:68px;border-radius:12px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:white;overflow:hidden;flex-shrink:0;cursor:pointer" onclick="document.getElementById('fotoInput').click()">
            <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" id="fotoImgPreview" style="width:100%;height:100%;object-fit:cover;border-radius:12px"><?php else: ?><span><?=$iniciales?></span><?php endif; ?>
          </div>
          <div>
            <input type="file" id="fotoInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirFoto(this)">
            <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:center">
              <button onclick="document.getElementById('fotoInput').click()" class="btn-primary" style="font-size:12px;padding:7px 13px">📷 Cambiar foto</button>
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
  // ── SIDEBAR ──
  function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open')}
  function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open')}

  // ── MODALS ──
  function abrirModal(){document.getElementById('modalEditar').classList.add('open')}
  function cerrarModal(){document.getElementById('modalEditar').classList.remove('open')}
  function abrirModalGaleria(){document.getElementById('modalGaleria').classList.add('open')}
  function cerrarModalGaleria(){document.getElementById('modalGaleria').classList.remove('open')}
  document.getElementById('modalEditar').addEventListener('click',e=>{if(e.target===document.getElementById('modalEditar'))cerrarModal()});
  document.getElementById('modalGaleria').addEventListener('click',e=>{if(e.target===document.getElementById('modalGaleria'))cerrarModalGaleria()});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){cerrarModal();cerrarModalGaleria();cerrarEliminarCuenta()}});

  // ── PROGRESS BAR ──
  window.addEventListener('load',()=>{const b=document.getElementById('progBar');if(b)setTimeout(()=>{b.style.width='<?=$pct?>%'},400)});

  // ── MSG HELPER ──
  function mostrarMsg(t,c){const e=document.getElementById('editMsg');e.textContent=t;e.className='mmsg '+c;e.style.display='block'}

  // ── GUARDAR NEGOCIO ── (Bug 2+3 fix: devuelve foto y reasigna avatares)
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
        mostrarMsg('¡Negocio actualizado!','success');
        document.getElementById('dNombreHero').textContent=j.nombre_negocio;
        document.getElementById('dNombreNeg').textContent=j.nombre_negocio;
        document.getElementById('dNombre2').textContent=j.nombre_negocio;
        if(j.foto){
          const imgTag=`<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
          const imgTagSq=`<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:12px">`;
          ['heroAvatar','sidebarAvatar','dashAvatarImg','dropAv'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=imgTag;});
          ['fotoPreview','cpAvatarCard','logoAvCard'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=imgTagSq;});
        }
        setTimeout(cerrarModal,1500);
      }else{mostrarMsg(j.msg||'Error al guardar.','error');}
    }catch(e){mostrarMsg('Error de conexión.','error');}
    btn.disabled=false;btn.textContent='💾 Guardar cambios';
  }

  // ── TOGGLE VISIBILIDAD ──
  async function toggleVisibilidad(visible){
    const chip=document.getElementById('visChip');
    const fd=new FormData();fd.append('_action','toggle_visibilidad');fd.append('visible',visible?'1':'0');
    try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
      if(j.ok){chip.textContent=visible?'🟢 Visible':'🟡 Oculto';chip.className='pv-chip '+(visible?'ok':'off');}
    }catch(e){}
  }

  // ── SUBIR FOTO ── (Bug 1 fix: Cloudinary / Bug 3 fix: reasigna todos los avatares)
  async function subirFoto(input){
    const file=input.files[0];if(!file)return;
    const msg=document.getElementById('fotoMsg');msg.textContent='⏳ Subiendo…';msg.style.color='var(--ink3)';
    const fd=new FormData();fd.append('_action','subir_foto');fd.append('foto',file);
    try{const r=await fetch('dashboard_negocios.php',{method:'POST',body:fd});const j=await r.json();
      if(j.ok){msg.textContent='✅ Foto actualizada';msg.style.color='var(--brand)';
        const imgTag=`<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
        const imgTagSq=`<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:12px">`;
        ['heroAvatar','sidebarAvatar','dashAvatarImg','dropAv'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=imgTag;});
        ['fotoPreview','cpAvatarCard','logoAvCard'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=imgTagSq;});
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

  // ── GALERÍA ──
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

  // ── BANNER CROP ──
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
    if(!img){img=document.createElement('img');img.id='bannerImg';img.style.cssText='width:100%;height:100%;object-fit:cover;display:block';document.getElementById('bannerZone').insertBefore(img,document.getElementById('bannerZone').firstChild);}
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

  // ── ELIMINAR CUENTA ──
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

  // ── NOTIFICACIONES ──
  const navNotif=document.getElementById('navNotif');
  navNotif.addEventListener('click',e=>{e.stopPropagation();navNotif.classList.toggle('open');cargarNotificaciones();});
  document.addEventListener('click',e=>{if(!navNotif.contains(e.target))navNotif.classList.remove('open');});
  async function cargarNotificaciones(){
    try{const r=await fetch('api_usuario.php?_action=notificaciones');const j=await r.json();
      const lista=document.getElementById('notifLista');const dot=document.getElementById('notifDot');
      if(j.ok&&j.items&&j.items.length){
        dot.style.display='block';
        lista.innerHTML=j.items.map(n=>`<div class="tnp-item"><strong>${n.titulo||''}</strong><br>${n.mensaje||''}<br><span style="font-size:10.5px;color:#999">${n.hace||''}</span></div>`).join('');
      }else{dot.style.display='none';lista.innerHTML='<div class="tnp-empty">Todo al día 🎉<br><small>No hay notificaciones nuevas</small></div>';}
    }catch(e){document.getElementById('notifLista').innerHTML='<div class="tnp-empty">No se pudieron cargar</div>';}
  }

  // ── TOPBAR DROPDOWN ──
  const dashAvatarBtn=document.getElementById('dashAvatarBtn');
  const dashDropdown=document.getElementById('dashDropdown');
  dashAvatarBtn.addEventListener('click',e=>{e.stopPropagation();dashAvatarBtn.classList.toggle('open');dashDropdown.classList.toggle('visible');});
  document.addEventListener('click',e=>{if(!document.getElementById('dashUserWidget').contains(e.target)){dashAvatarBtn.classList.remove('open');dashDropdown.classList.remove('visible');}});
  </script>
  <script src="js/sesion_widget.js"></script>
</body>
</html>