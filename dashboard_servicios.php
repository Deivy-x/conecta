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
if (!$usuario) { session_destroy(); header('Location: inicio_sesion.php'); exit; }

$extras = [];
try {
    $solStmt = $db->prepare("SELECT nota_admin FROM solicitudes_ingreso WHERE correo=? ORDER BY creado_en DESC LIMIT 1");
    $solStmt->execute([$usuario['correo']]); $solRow = $solStmt->fetch();
    if ($solRow && !empty($solRow['nota_admin'])) $extras = json_decode($solRow['nota_admin'],true)?:[];
} catch(Exception $e){}

$profesionTipo = strtolower($extras['profesion_tipo'] ?? '');
$esServicioUser = ($usuario['tipo'] === 'servicio');
$esCandidateWithService = ($usuario['tipo'] === 'candidato' && preg_match('/(dj|disc jockey|chirimía|chirimia|música|musica|cantante|fotograf|video|catering|decorac|animador|maestro.*ceremonia)/i',$profesionTipo));

if (!$esServicioUser && !$esCandidateWithService) {
    header('Location: dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['_action'] ?? '';

    if ($action === 'editar_servicio') {
        $nombre      = trim($_POST['nombre']       ?? '');
        $apellido    = trim($_POST['apellido']      ?? '');
        $telefono    = trim($_POST['telefono']      ?? '');
        $ciudad      = trim($_POST['ciudad']        ?? '');
        $tipoSvc     = trim($_POST['tipo_servicio'] ?? '');
        $generos     = trim($_POST['generos']       ?? '');
        $bio         = trim($_POST['bio']           ?? '');
        $precioDes   = trim($_POST['precio_desde']  ?? '');
        $disponib    = trim($_POST['disponibilidad']?? '');
        $instagram   = trim($_POST['instagram']     ?? '');
        $skills      = trim($_POST['skills']        ?? '');

        if (!$nombre) { echo json_encode(['ok'=>false,'msg'=>'El nombre es obligatorio.']); exit; }

        $db->prepare("UPDATE usuarios SET nombre=?,apellido=?,telefono=?,ciudad=? WHERE id=?")
           ->execute([$nombre,$apellido,$telefono,$ciudad,$usuario['id']]);

        $tpChk = $db->prepare("SELECT id FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
        $tpChk->execute([$usuario['id']]); $tpRow = $tpChk->fetch();
        if ($tpRow) {
            $db->prepare("UPDATE talento_perfil SET profesion=?,bio=?,skills=?,generos=?,precio_desde=?,tipo_servicio=?,disponibilidad=? WHERE id=?")
               ->execute([$tipoSvc,$bio,$skills,$generos,$precioDes?:null,$tipoSvc,$disponib,$tpRow['id']]);
        } else {
            $db->prepare("INSERT INTO talento_perfil (usuario_id,profesion,bio,skills,generos,precio_desde,tipo_servicio,disponibilidad,visible,visible_admin) VALUES (?,?,?,?,?,?,?,?,0,1)")
               ->execute([$usuario['id'],$tipoSvc,$bio,$skills,$generos,$precioDes?:null,$tipoSvc,$disponib]);
        }
        try { $db->exec("ALTER TABLE talento_perfil ADD COLUMN IF NOT EXISTS instagram VARCHAR(100) DEFAULT ''"); } catch(Exception $e){}
        try { $db->prepare("UPDATE talento_perfil SET instagram=? WHERE usuario_id=? ORDER BY id DESC LIMIT 1")->execute([$instagram,$usuario['id']]); } catch(Exception $e){}

        // Releer foto actual para devolverla al frontend y que no se pierda del DOM
        $fotoStmt = $db->prepare('SELECT foto FROM usuarios WHERE id=? LIMIT 1');
        $fotoStmt->execute([$usuario['id']]);
        $fotoActualResp = $fotoStmt->fetchColumn() ?: '';
        echo json_encode(['ok'=>true,'nombre'=>$nombre,'tipo_servicio'=>$tipoSvc,'ciudad'=>$ciudad,'foto'=>$fotoActualResp]);
        exit;
    }

    if ($action === 'toggle_vis') {
        $visible = (int)($_POST['visible']??0);
        $tpChk = $db->prepare("SELECT id FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
        $tpChk->execute([$usuario['id']]); $tpRow = $tpChk->fetch();
        if ($tpRow) $db->prepare("UPDATE talento_perfil SET visible=? WHERE id=?")->execute([$visible,$tpRow['id']]);
        echo json_encode(['ok'=>true,'visible'=>$visible]);
        exit;
    }

    if ($action === 'subir_foto') {
        if (!isset($_FILES['foto'])||$_FILES['foto']['error']!==0){echo json_encode(['ok'=>false,'msg'=>'No se recibió imagen.']);exit;}
        $ext=strtolower(pathinfo($_FILES['foto']['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'])){echo json_encode(['ok'=>false,'msg'=>'Solo JPG/PNG/WEBP.']);exit;}
        if ($_FILES['foto']['size']>2*1024*1024){echo json_encode(['ok'=>false,'msg'=>'Máximo 2 MB.']);exit;}
        require_once __DIR__ . '/Php/cloudinary_upload.php';
        $result=cloudinary_upload($_FILES['foto']['tmp_name'],'quibdoconecta/fotos');
        if (!$result['ok']){echo json_encode(['ok'=>false,'msg'=>$result['msg']]);exit;}
        $db->prepare("UPDATE usuarios SET foto=? WHERE id=?")->execute([$result['url'],$usuario['id']]);
        echo json_encode(['ok'=>true,'foto'=>$result['url']]);
        exit;
    }

    if ($action === 'eliminar_foto') {
        $db->prepare("UPDATE usuarios SET foto='' WHERE id=?")->execute([$usuario['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'subir_banner') {
        if (!isset($_FILES['banner'])||$_FILES['banner']['error']!==0){echo json_encode(['ok'=>false,'msg'=>'No se recibió imagen.']);exit;}
        $ext=strtolower(pathinfo($_FILES['banner']['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'])){echo json_encode(['ok'=>false,'msg'=>'Solo JPG/PNG/WEBP.']);exit;}
        if ($_FILES['banner']['size']>5*1024*1024){echo json_encode(['ok'=>false,'msg'=>'Máximo 5 MB.']);exit;}
        try{$db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto");}catch(Exception $e){}
        require_once __DIR__ . '/Php/cloudinary_upload.php';
        $result=cloudinary_upload($_FILES['banner']['tmp_name'],'quibdoconecta/banners');
        if (!$result['ok']){echo json_encode(['ok'=>false,'msg'=>$result['msg']]);exit;}
        $db->prepare("UPDATE usuarios SET banner=? WHERE id=?")->execute([$result['url'],$usuario['id']]);
        echo json_encode(['ok'=>true,'banner'=>$result['url']]);
        exit;
    }

    if ($action === 'eliminar_banner') {
        try{$db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto");}catch(Exception $e){}
        $db->prepare("UPDATE usuarios SET banner='' WHERE id=?")->execute([$usuario['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'subir_evidencia') {
        if (file_exists(__DIR__.'/Php/badges_helper.php')) require_once __DIR__.'/Php/badges_helper.php';
        $tienePortafolio = function_exists('tieneBeneficio') ? tieneBeneficio($db,$usuario['id'],'portafolio') : false;
        try{$db->exec("CREATE TABLE IF NOT EXISTS talento_galeria(id INT AUTO_INCREMENT PRIMARY KEY,usuario_id INT NOT NULL,tipo ENUM('foto','video') NOT NULL DEFAULT 'foto',archivo VARCHAR(255) NOT NULL DEFAULT '',url_video VARCHAR(500) DEFAULT NULL,titulo VARCHAR(150) DEFAULT NULL,descripcion TEXT DEFAULT NULL,orden TINYINT NOT NULL DEFAULT 0,activo TINYINT(1) NOT NULL DEFAULT 1,creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_usuario(usuario_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}catch(Exception $e){}
        $cnt=$db->prepare("SELECT COUNT(*) FROM talento_galeria WHERE usuario_id=? AND activo=1");$cnt->execute([$usuario['id']]);$total=(int)$cnt->fetchColumn();
        $limite=$tienePortafolio?PHP_INT_MAX:15;
        if ($total>=$limite){echo json_encode(['ok'=>false,'msg'=>'Alcanzaste el límite de 15 archivos. Activa el plan Selva Verde 🌿 para galería ilimitada.']);exit;}
        if (!isset($_FILES['archivo'])||$_FILES['archivo']['error']!==0){echo json_encode(['ok'=>false,'msg'=>'No se recibió archivo.']);exit;}
        require_once __DIR__.'/Php/cloudinary_upload.php';
        $result=cloudinary_upload($_FILES['archivo']['tmp_name'],'quibdoconecta/portafolio');
        if (!$result['ok']){echo json_encode(['ok'=>false,'msg'=>$result['msg']]);exit;}
        $titulo=trim($_POST['titulo']??'');$desc=trim($_POST['descripcion']??'');
        $db->prepare("INSERT INTO talento_galeria(usuario_id,tipo,archivo,titulo,descripcion,activo) VALUES(?,?,?,?,?,1)")
           ->execute([$usuario['id'],'foto',$result['url'],$titulo,$desc]);
        echo json_encode(['ok'=>true,'url'=>$result['url']]);
        exit;
    }

    if ($action === 'eliminar_evidencia') {
        $id=(int)($_POST['id']??0);
        $db->prepare("UPDATE talento_galeria SET activo=0 WHERE id=? AND usuario_id=?")->execute([$id,$usuario['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'eliminar_cuenta') {
        $confirmar=trim($_POST['confirmar']??'');
        if ($confirmar!==$usuario['correo']){echo json_encode(['ok'=>false,'msg'=>'El correo no coincide.']);exit;}
        try {
            foreach (['talento_galeria','talento_perfil','talento_educacion','talento_certificaciones','talento_experiencia','perfil_vistas','sesiones'] as $tabla) {
                try{$db->prepare("DELETE FROM $tabla WHERE usuario_id=?")->execute([$usuario['id']]);}catch(Exception $e){}
            }
            $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$usuario['id']]);
            $_SESSION=[];session_destroy();
            echo json_encode(['ok'=>true]);
        }catch(Exception $e){echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);}
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']); exit;
}

if (isset($_GET['salir'])) {
    if (isset($_COOKIE['qc_remember'])){try{$db->prepare("DELETE FROM sesiones WHERE token=?")->execute([$_COOKIE['qc_remember']]);}catch(Exception $e){}setcookie('qc_remember','',time()-3600,'/');}
    $_SESSION=[];session_destroy();header('Location: inicio_sesion.php');exit;
}

$tp = $db->prepare("SELECT * FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
$tp->execute([$usuario['id']]);
$talento = $tp->fetch() ?: ['profesion'=>'','bio'=>'','skills'=>'','visible'=>0,'visible_admin'=>1,'generos'=>'','precio_desde'=>null,'tipo_servicio'=>'','calificacion'=>0,'disponibilidad'=>''];

try{$db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto");}catch(Exception $e){}
$uRe=$db->prepare("SELECT * FROM usuarios WHERE id=?");$uRe->execute([$usuario['id']]);$uRe=$uRe->fetch();
$bannerUrl=!empty($uRe['banner'])?htmlspecialchars($uRe['banner']):'';
$fotoUrl=!empty($uRe['foto'])?htmlspecialchars($uRe['foto']):'';

require_once __DIR__ . '/Php/badges_helper.php';
$badgesUsuario = getBadgesUsuario($db,$usuario['id']);
$badgesHTML    = renderBadges($badgesUsuario);
$tieneVerificado = (bool)($usuario['verificado']??false)||tieneBadge($badgesUsuario,'Verificado');
$tienePremium    = tieneBadge($badgesUsuario,'Premium');
$tieneDestacado  = tieneBadge($badgesUsuario,'Destacado')||(int)($talento['destacado']??0);
$tieneTop        = tieneBadge($badgesUsuario,'Top');

$datosPlan  = function_exists('getDatosPlan')?getDatosPlan($db,$usuario['id']):[];
$planActual = $datosPlan['plan']??'semilla';

$nrChat=$db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario=? AND leido=0");
$nrChat->execute([$usuario['id']]);$chatNoLeidos=(int)$nrChat->fetchColumn();

$vistasTotal=0;$vistas7dias=0;
try{
    $vt=$db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=?");$vt->execute([$usuario['id']]);$vistasTotal=(int)$vt->fetchColumn();
    $v7=$db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=? AND creado_en>=DATE_SUB(NOW(),INTERVAL 7 DAY)");$v7->execute([$usuario['id']]);$vistas7dias=(int)$v7->fetchColumn();
}catch(Exception $e){}

$stmtV=$db->prepare("SELECT estado FROM verificaciones WHERE usuario_id=? ORDER BY creado_en DESC LIMIT 1");
$stmtV->execute([$usuario['id']]);$verifDoc=$stmtV->fetch();$estadoVerif=$verifDoc?$verifDoc['estado']:null;

$galeriaItems=[];$galeriaTotal=0;
try{
    $gStmt=$db->prepare("SELECT * FROM talento_galeria WHERE usuario_id=? AND activo=1 ORDER BY orden ASC,id ASC");
    $gStmt->execute([$usuario['id']]);$galeriaItems=$gStmt->fetchAll();$galeriaTotal=count($galeriaItems);
}catch(Exception $e){}

$nombreCompleto = htmlspecialchars(trim($uRe['nombre'].' '.($uRe['apellido']??'')));
$inicial        = strtoupper(mb_substr($uRe['nombre'],0,1));
$tipoServicio   = htmlspecialchars($talento['tipo_servicio']??$extras['profesion_tipo']??'');
$generos        = htmlspecialchars($talento['generos']??'');
$precioDes      = $talento['precio_desde']?'$'.number_format((float)$talento['precio_desde'],0,',','.'):'';
$calificacion   = $talento['calificacion']?number_format((float)$talento['calificacion'],1):'0';
$disponib       = htmlspecialchars($talento['disponibilidad']??'');
$ciudad         = htmlspecialchars($uRe['ciudad']??'');
$telefono       = htmlspecialchars($uRe['telefono']??'');
$correo         = htmlspecialchars($uRe['correo']);
$fechaRegistro  = date('d \de F Y',strtotime($usuario['creado_en']));
$visible        = (int)($talento['visible']??0);

$campos=['profesion','bio','skills','generos','precio_desde'];
$llenos=array_filter($campos,fn($c)=>!empty($talento[$c]));
$pct=(int)(count($llenos)/count($campos)*100);
if (!empty($uRe['ciudad'])) $pct=min(100,$pct+10);
if ($fotoUrl) $pct=min(100,$pct+10);
if ($tieneVerificado) $pct=min(100,$pct+5);
?><!DOCTYPE html>
<html lang="es">

if ($fotoUrl) $pct=min(100,$pct+10);
if ($tieneVerificado) $pct=min(100,$pct+5);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Panel Servicios – QuibdóConecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root {
      --brand: #6d28d9;
      --brand2: #8b5cf6;
      --brand-light: #f5f3ff;
      --brand-mid: #c4b5fd;
      --accent: #f59e0b;
      --accent-light: #fffbeb;
      --danger: #e53935;
      --ink: #0f0a1e;
      --ink2: #2d1f5a;
      --ink3: #6b5fa0;
      --ink4: #9d94c0;
      --surface: #ffffff;
      --surface2: #f8f5ff;
      --surface3: #ede9fe;
      --border: rgba(109,40,217,.10);
      --border2: rgba(109,40,217,.20);
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
    body{font-family:var(--font);background:#f0ecff;color:var(--ink);min-height:100vh;display:flex}
    a{text-decoration:none;color:inherit}

    /* ── BANDERA CHOCÓ ── */
    .barra-bandera{position:fixed;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#1f9d55 33.3%,#d4a017 33.3% 66.6%,#1a3a6b 66.6%);z-index:9999}

    /* ── SIDEBAR ── */
    .sidebar{width:var(--nav-w);background:linear-gradient(180deg,#fff 0%,#faf8ff 100%);border-right:2px solid rgba(109,40,217,.10);display:flex;flex-direction:column;position:fixed;top:4px;left:0;bottom:0;z-index:150;transition:transform .3s ease;overflow-y:auto;overflow-x:hidden}
    .sidebar-logo{padding:14px 18px 12px;border-bottom:1px solid var(--border);display:none;align-items:center;gap:10px}
    .sidebar-logo img{height:32px}
    .sidebar-logo-txt{font-size:13px;font-weight:700;color:var(--brand);letter-spacing:-.2px;line-height:1.2}
    .sidebar-logo-sub{font-size:11px;color:var(--ink4);font-weight:400}
    .sidebar-user{margin:14px 14px 10px;background:var(--brand-light);border-radius:var(--radius);padding:14px;display:flex;align-items:center;gap:10px}
    .su-av{width:38px;height:38px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;flex-shrink:0;overflow:hidden;border:2px solid #fff;cursor:pointer}
    .su-av img{width:100%;height:100%;object-fit:cover}
    .su-name{font-size:13px;font-weight:700;color:var(--ink);line-height:1.3}
    .su-role{font-size:11px;color:var(--ink3)}
    .sidebar-nav{flex:1;padding:6px 10px;overflow-y:auto}
    .nav-section{margin-bottom:4px}
    .nav-section-label{font-size:10px;font-weight:700;color:var(--ink4);text-transform:uppercase;letter-spacing:1.2px;padding:10px 10px 4px}
    .nav-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:13.5px;font-weight:500;color:var(--ink2);transition:all .18s;cursor:pointer;position:relative;text-decoration:none}
    .nav-item:hover{background:var(--surface2);color:var(--brand)}
    .nav-item.active{background:var(--brand-light);color:var(--brand);font-weight:700}
    .nav-item .ni-ico{font-size:15px;width:20px;text-align:center;flex-shrink:0}
    .nav-item .ni-badge{margin-left:auto;background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:20px;min-width:18px;text-align:center}
    .sidebar-bottom{padding:12px 14px;border-top:1px solid var(--border)}
    .sidebar-plan{background:linear-gradient(135deg,#6d28d9 0%,#8b5cf6 60%,#1a3a6b 100%);border-radius:var(--radius);padding:14px;color:#fff;margin-bottom:10px}
    .sp-label{font-size:10px;font-weight:600;opacity:.75;text-transform:uppercase;letter-spacing:1px}
    .sp-name{font-size:15px;font-weight:800;margin:2px 0 8px}
    .sp-btn{display:block;text-align:center;background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);border-radius:8px;padding:7px 12px;font-size:12px;font-weight:700;transition:background .2s}
    .sp-btn:hover{background:rgba(255,255,255,.32)}
    .nav-salir{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:10px;font-size:13px;color:var(--ink3);transition:all .18s}
    .nav-salir:hover{background:#fef2f2;color:var(--danger)}

    /* ── TOPBAR ── */
    .topbar{height:var(--top-h);background:linear-gradient(135deg,#fff 60%,#f5f3ff 100%);border-bottom:2px solid rgba(109,40,217,.12);position:fixed;top:4px;left:var(--nav-w);right:0;z-index:250;display:flex;align-items:center;padding:0 28px;gap:16px;box-shadow:0 2px 12px rgba(109,40,217,.08)}
    .topbar-logo{display:flex;align-items:center;gap:8px;flex-shrink:0;text-decoration:none;cursor:pointer}
    .topbar-logo img{height:34px}
    .topbar-title{font-size:16px;font-weight:700;color:var(--ink);flex:1}
    .topbar-title span{color:var(--brand)}
    .topbar-actions{display:flex;align-items:center;gap:10px}
    .tb-btn{width:38px;height:38px;border-radius:10px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:16px;cursor:pointer;position:relative;transition:all .18s}
    .tb-btn:hover{background:var(--brand-light);border-color:var(--brand-mid)}
    .tb-dot{position:absolute;top:6px;right:6px;width:8px;height:8px;background:var(--danger);border-radius:50%;border:2px solid var(--surface)}
    .tb-notif-panel{position:absolute;top:calc(100% + 8px);right:0;width:300px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow2);display:none;z-index:300}
    .tb-notif-panel.open{display:block}
    .tnp-head{padding:12px 16px;font-size:13px;font-weight:700;border-bottom:1px solid var(--border)}
    .tnp-body{max-height:280px;overflow-y:auto}
    .tnp-empty{padding:20px;text-align:center;color:var(--ink4);font-size:13px}
    .hamburger{display:none;width:38px;height:38px;border-radius:10px;background:var(--surface2);border:1px solid var(--border);flex-direction:column;align-items:center;justify-content:center;gap:5px;cursor:pointer}
    .hamburger span{width:18px;height:2px;background:var(--ink2);border-radius:2px;transition:all .2s}

    /* ── DASH AVATAR WIDGET ── */
    .dash-avatar-btn{display:flex;align-items:center;gap:9px;background:white;border:1.5px solid rgba(109,40,217,.3);border-radius:40px;padding:5px 14px 5px 5px;cursor:pointer;transition:all .3s cubic-bezier(.34,1.56,.64,1);box-shadow:0 2px 12px rgba(109,40,217,.12);user-select:none}
    .dash-avatar-btn:hover,.dash-avatar-btn.open{border-color:var(--brand);box-shadow:0 4px 20px rgba(109,40,217,.22);transform:translateY(-1px)}
    .dash-avatar-img{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#6d28d9,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:white;overflow:hidden;flex-shrink:0;border:2px solid rgba(109,40,217,.3)}
    .dash-avatar-img img{width:100%;height:100%;object-fit:cover;border-radius:50%}
    .dash-avatar-info{display:flex;flex-direction:column;line-height:1.2}
    .dash-avatar-nombre{font-size:13px;font-weight:700;color:#111;max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .dash-avatar-sub{font-size:10px;color:#888;display:flex;align-items:center;gap:4px}
    .dash-avatar-arrow{font-size:9px;color:var(--brand);transition:transform .3s}
    .dash-avatar-btn.open .dash-avatar-arrow{transform:rotate(180deg)}
    .dash-online-dot{width:7px;height:7px;border-radius:50%;background:#8b5cf6;animation:dash-online 2.5s infinite}
    @keyframes dash-online{0%,100%{box-shadow:0 0 0 0 rgba(139,92,246,.4)}60%{box-shadow:0 0 0 5px rgba(139,92,246,0)}}
    .dash-dropdown{position:absolute;top:calc(100% + 10px);right:0;width:270px;background:white;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.15),0 4px 16px rgba(0,0,0,.08);border:1px solid rgba(0,0,0,.07);overflow:hidden;z-index:9999;opacity:0;transform:translateY(-10px) scale(.97);pointer-events:none;transition:all .25s cubic-bezier(.34,1.56,.64,1)}
    .dash-dropdown.visible{opacity:1;transform:translateY(0) scale(1);pointer-events:all}
    .dash-drop-header{padding:18px 20px 14px;background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-bottom:1px solid rgba(109,40,217,.1);display:flex;align-items:center;gap:12px}
    .dash-drop-av{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#6d28d9,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:white;flex-shrink:0;border:3px solid white;box-shadow:0 4px 12px rgba(109,40,217,.3);overflow:hidden}
    .dash-drop-av img{width:100%;height:100%;border-radius:50%;object-fit:cover}
    .dash-drop-nombre{font-size:15px;font-weight:700;color:#111}
    .dash-drop-tipo{font-size:11px;color:var(--brand);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:2px}
    .dash-drop-correo{font-size:11px;color:#999;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:150px}
    .dash-drop-badges{padding:10px 20px;border-bottom:1px solid rgba(0,0,0,.05);display:flex;flex-wrap:wrap;gap:4px}
    .dash-drop-menu{padding:8px 0}
    .dash-drop-link{display:flex;align-items:center;gap:10px;padding:11px 20px;color:#333;text-decoration:none;font-size:14px;font-weight:500;transition:all .2s;cursor:pointer}
    .dash-drop-link:hover{background:#f8f5ff;color:var(--brand);padding-left:24px}
    .dash-dl-icon{width:28px;height:28px;border-radius:8px;background:#f4f6f8;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;transition:all .2s}
    .dash-drop-link:hover .dash-dl-icon{background:rgba(109,40,217,.1);transform:scale(1.1)}
    .dash-dl-badge{margin-left:auto;background:#e74c3c;color:white;font-size:10px;font-weight:800;padding:2px 6px;border-radius:12px}
    .dash-drop-sep{height:1px;background:rgba(0,0,0,.06);margin:4px 0}
    .dash-drop-logout{color:#e74c3c!important}
    .dash-drop-logout .dash-dl-icon{background:#fff5f5!important}
    .dash-drop-logout:hover{background:#fff5f5!important;color:#c0392b!important}

    /* ── MAIN ── */
    .main{margin-left:var(--nav-w);margin-top:var(--top-h);flex:1;min-width:0;padding:28px;background:#f0ecff}

    /* ── HERO STRIP ── */
    .hero-strip{background:linear-gradient(135deg,#fff 0%,#f5f3ff 60%,#fffbeb 100%);border:1px solid rgba(109,40,217,.13);border-radius:var(--radius-lg);padding:24px 28px;display:flex;align-items:center;gap:20px;margin-bottom:24px;position:relative;overflow:hidden}
    .hero-strip::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#6d28d9 33.3%,#f59e0b 33.3% 66.6%,#1a3a6b 66.6%)}
    .hero-av{width:72px;height:72px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;flex-shrink:0;overflow:hidden;cursor:pointer;border:3px solid var(--brand-light);transition:border-color .2s}
    .hero-av:hover{border-color:var(--brand)}
    .hero-av img{width:100%;height:100%;object-fit:cover}
    .hero-info{flex:1;min-width:0}
    .hero-chips{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:6px}
    .hchip{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;white-space:nowrap}
    .hc-tipo{background:var(--brand-light);color:var(--brand)}
    .hc-v{background:#e8f5e9;color:#2e7d32}
    .hc-p{background:#fff8e1;color:#f57f17}
    .hc-top{background:#fce4ec;color:#c62828}
    .hc-dest{background:#f3e5f5;color:#6a1b9a}
    .hero-name{font-size:22px;font-weight:800;color:var(--ink);letter-spacing:-.5px}
    .hero-sub{font-size:13px;color:var(--ink3);margin-top:2px}
    .hero-stats{display:flex;gap:24px;flex-shrink:0}
    .hs{text-align:center}
    .hs-val{font-size:24px;font-weight:800;color:var(--brand);line-height:1}
    .hs-lab{font-size:11px;color:var(--ink4);margin-top:2px;font-weight:500}
    .hero-actions{display:flex;flex-direction:column;gap:8px;flex-shrink:0}

    /* ── BUTTONS ── */
    .btn-primary{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--radius-sm);background:var(--brand);color:#fff;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:var(--font);transition:all .2s;white-space:nowrap;text-decoration:none}
    .btn-primary:hover{background:#5b21b6;transform:translateY(-1px)}
    .btn-secondary{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:var(--radius-sm);background:var(--surface2);color:var(--ink2);font-size:13px;font-weight:600;border:1px solid var(--border2);cursor:pointer;font-family:var(--font);transition:all .2s;white-space:nowrap;text-decoration:none}
    .btn-secondary:hover{background:var(--brand-light);color:var(--brand);border-color:var(--brand-mid)}

    /* ── ALERT ── */
    .alert-bar{display:flex;align-items:center;gap:12px;padding:12px 18px;border-radius:var(--radius);margin-bottom:18px;font-size:13px}
    .alert-bar.as{background:#fff8e1;border:1px solid #ffe082;color:#7c5000}
    .alert-bar.ap{background:#e3f2fd;border:1px solid #90caf9;color:#1565c0}
    .alert-bar.ar{background:#fce8e8;border:1px solid #f5a5a5;color:#b71c1c}
    .alert-bar.av{background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1px solid #c4b5fd;color:#5b21b6}
    .alert-bar .a-ico{font-size:18px;flex-shrink:0}
    .alert-bar .a-txt{flex:1}
    .alert-bar .a-txt strong{font-weight:700}
    .alert-bar .a-txt span{margin-left:6px;opacity:.8}
    .alert-bar .a-btn{padding:6px 14px;border-radius:8px;background:var(--brand);color:#fff;font-size:12px;font-weight:700;white-space:nowrap;flex-shrink:0;text-decoration:none}

    /* ── GRID ── */
    .dashboard-grid{display:grid;grid-template-columns:repeat(12,1fr);gap:18px}
    .col-3{grid-column:span 3}.col-4{grid-column:span 4}.col-6{grid-column:span 6}.col-8{grid-column:span 8}.col-12{grid-column:span 12}

    /* ── CARDS ── */
    .card{background:#fff;border:1px solid rgba(109,40,217,.08);border-radius:var(--radius);overflow:hidden;box-shadow:0 1px 4px rgba(109,40,217,.06)}
    .card-header{padding:16px 20px 14px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
    .card-title{font-size:13px;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:7px}
    .card-link{font-size:12px;color:var(--brand);font-weight:600}
    .card-body{padding:18px 20px}

    /* ── METRIC CARDS ── */
    .metric-card{background:#fff;border:1px solid rgba(109,40,217,.08);border-radius:var(--radius);padding:18px 20px;display:flex;align-items:center;gap:14px;transition:all .2s;box-shadow:0 1px 4px rgba(109,40,217,.06)}
    .metric-card:hover{border-color:var(--brand-mid);box-shadow:0 4px 16px rgba(109,40,217,.12);transform:translateY(-1px)}
    .mc-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
    .mc-ico.g{background:var(--brand-light)}.mc-ico.a{background:#fff8e1}.mc-ico.o{background:#fff3e0}.mc-ico.m{background:#f3e5f5}
    .mc-val{font-size:26px;font-weight:800;color:var(--ink);line-height:1}
    .mc-lab{font-size:12px;color:var(--ink3);margin-top:2px}
    .mc-sub{font-size:11px;color:var(--brand);font-weight:600;margin-top:4px;cursor:pointer}

    /* ── PROFILE CARD ── */
    .profile-card{padding:20px}
    .pc-av{width:56px;height:56px;border-radius:50%;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;overflow:hidden;cursor:pointer;margin-bottom:12px;border:3px solid var(--brand-light)}
    .pc-av img{width:100%;height:100%;object-fit:cover}
    .pc-name{font-size:16px;font-weight:800;color:var(--ink)}
    .pc-role{font-size:12px;color:var(--ink3);margin-bottom:14px}
    .pc-rows{display:flex;flex-direction:column;gap:8px}
    .pc-row{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--ink2)}
    .pc-row-ico{font-size:14px;flex-shrink:0;width:18px;text-align:center}

    /* ── PROGRESS ── */
    .prog-wrap{margin:14px 0}
    .prog-header{display:flex;justify-content:space-between;margin-bottom:6px;font-size:12px;color:var(--ink3);font-weight:600}
    .prog-track{height:6px;background:var(--surface3);border-radius:6px;overflow:hidden}
    .prog-fill{height:100%;background:linear-gradient(90deg,var(--brand),var(--brand2));border-radius:6px;transition:width 1s ease}

    /* ── VISIBILITY ── */
    .vis-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-top:1px solid var(--border);margin-top:4px}
    .vis-label{font-size:12.5px;font-weight:700;color:var(--ink2)}
    .vis-sub{font-size:11px;color:var(--ink4)}
    .tog{position:relative;display:inline-block;width:40px;height:22px}
    .tog input{opacity:0;width:0;height:0}
    .tog-sl{position:absolute;inset:0;background:#ddd;border-radius:22px;cursor:pointer;transition:.3s}
    .tog-sl::before{content:'';position:absolute;width:16px;height:16px;background:#fff;border-radius:50%;left:3px;top:3px;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
    input:checked+.tog-sl{background:var(--brand)}
    input:checked+.tog-sl::before{transform:translateX(18px)}
    .pv-chip{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px}
    .pv-chip.ok{background:#e8f5e9;color:#2e7d32}
    .pv-chip.off{background:#fff8e1;color:#f57f17}

    /* ── QUICK ACTIONS ── */
    .actions-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;padding:16px 20px}
    .action-item{display:flex;flex-direction:column;align-items:center;gap:7px;padding:14px 8px;border-radius:var(--radius-sm);border:1px solid var(--border);background:var(--surface2);cursor:pointer;transition:all .18s;text-decoration:none;text-align:center;position:relative}
    .action-item:hover{background:var(--brand-light);border-color:var(--brand-mid);transform:translateY(-2px);box-shadow:0 4px 12px rgba(109,40,217,.1)}
    .action-item .ai-ico{font-size:22px}
    .action-item .ai-label{font-size:11.5px;font-weight:700;color:var(--ink2);line-height:1.2}
    .action-item .ai-badge{position:absolute;top:6px;right:6px;background:var(--danger);color:#fff;font-size:9px;font-weight:700;padding:2px 5px;border-radius:20px}

    /* ── GALLERY ── */
    .gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;padding:14px 20px}
    .gallery-item{position:relative;border-radius:10px;overflow:hidden;background:var(--surface2);border:1px solid var(--border);aspect-ratio:1}
    .gallery-item img{width:100%;height:100%;object-fit:cover}
    .gallery-item-del{position:absolute;top:5px;right:5px;background:rgba(0,0,0,.55);border:none;color:#fff;border-radius:6px;padding:3px 7px;font-size:11px;cursor:pointer;line-height:1}
    .gallery-empty{text-align:center;padding:16px;border:1.5px dashed var(--border2);border-radius:10px;color:var(--ink4);margin:10px 16px}

    /* ── ACTIVITY LIST ── */
    .activity-list{padding:4px 20px 16px}
    .act-item{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
    .act-item:last-child{border-bottom:none}
    .act-dot{width:8px;height:8px;border-radius:50%;background:var(--brand-mid);margin-top:5px;flex-shrink:0}
    .act-txt{font-size:12.5px;color:var(--ink2);flex:1}
    .act-txt strong{color:var(--ink)}
    .act-time{font-size:11px;color:var(--ink4);flex-shrink:0;margin-top:2px}

    /* ── PLAN BARS ── */
    .plan-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;padding:16px 20px}
    .plan-bar{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px}
    .pb-label{font-size:12px;font-weight:600;color:var(--ink3);margin-bottom:6px}
    .pb-count{font-size:20px;font-weight:800;color:var(--ink);line-height:1;margin-bottom:8px}
    .pb-count span{font-size:13px;font-weight:400;color:var(--ink4)}
    .pb-track{height:5px;background:rgba(0,0,0,.07);border-radius:5px}
    .pb-fill{height:5px;border-radius:5px;transition:width .5s}
    .pb-fill.low{background:var(--brand)}.pb-fill.mid{background:var(--accent)}.pb-fill.high{background:var(--danger)}

    /* ── SERVICE CHIPS ── */
    .svc-chips{display:flex;flex-wrap:wrap;gap:7px;padding:14px 20px}
    .svc-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;background:var(--brand-light);color:var(--brand);font-size:12px;font-weight:600;border:1px solid var(--border)}

    /* ── MODALS ── */
    .modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:flex-start;justify-content:center;padding:40px 16px;overflow-y:auto}
    .modal-ov.open{display:flex}
    .modal-box{background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:520px;box-shadow:0 24px 64px rgba(0,0,0,.18);position:relative;overflow:hidden}
    .modal-pad{padding:28px 28px 24px}
    .mcerrar{position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:50%;background:var(--surface2);border:1px solid var(--border);font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:10;transition:all .18s;font-family:var(--font)}
    .mcerrar:hover{background:#fef2f2;color:var(--danger)}
    .mtit{font-size:20px;font-weight:800;color:var(--ink);margin-bottom:4px}
    .msub{font-size:13px;color:var(--ink3);margin-bottom:18px}
    .msec{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--ink4);margin:18px 0 10px}
    .mmsg{display:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:12px}
    .mmsg.success{background:#e8f5e9;color:#2e7d32}
    .mmsg.error{background:#fce8e8;color:#b71c1c}
    .mfila{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
    .mgr{display:flex;flex-direction:column;gap:5px}
    .mgr.full{grid-column:1/-1}
    .mgr label{font-size:12px;font-weight:700;color:var(--ink2)}
    .mgr input,.mgr select,.mgr textarea{padding:9px 12px;border-radius:10px;border:1.5px solid var(--border2);font-size:13px;font-family:var(--font);color:var(--ink);background:var(--surface2);width:100%;box-sizing:border-box;transition:border-color .18s}
    .mgr input:focus,.mgr select:focus,.mgr textarea:focus{outline:none;border-color:var(--brand);background:var(--surface)}
    .btn-save{display:block;width:100%;padding:13px;border-radius:var(--radius);background:var(--brand);color:#fff;font-size:14px;font-weight:800;border:none;cursor:pointer;font-family:var(--font);margin-top:18px;transition:background .2s}
    .btn-save:hover{background:#5b21b6}
    .btn-save:disabled{opacity:.6;cursor:not-allowed}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:199}

    /* ── NOTIF ITEMS ── */
    .notif-item{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;color:var(--ink2)}
    .notif-item:last-child{border-bottom:none}
    .notif-ico{font-size:18px;flex-shrink:0}
    .notif-sub{font-size:11px;color:var(--ink4);margin-top:2px}

    /* ── RESPONSIVE ── */
    @media(max-width:1100px){.col-4{grid-column:span 6}.col-3{grid-column:span 6}}
    @media(max-width:820px){
      :root{--nav-w:0px}
      .sidebar{transform:translateX(-240px);top:0;z-index:350;width:240px}
      .sidebar-logo{display:flex}
      .sidebar.open{transform:translateX(0)}
      .sidebar-overlay.open{display:block}
      .topbar{left:0;padding:0 12px;gap:8px}
      .topbar-title{display:none}
      .hamburger{display:flex}
      .topbar-actions{gap:6px}
      .dash-avatar-info{display:none}
      .dash-avatar-btn{padding:5px}
      .main{padding:18px 16px;margin-left:0}
      .hero-strip{flex-direction:column;align-items:flex-start;gap:12px;padding:16px}
      .hero-top-row{display:flex;align-items:center;gap:12px;width:100%}
      .hero-av{width:56px;height:56px;font-size:22px}
      .hero-stats{display:flex;gap:0;width:100%;background:var(--surface2);border-radius:12px;padding:10px 0}
      .hs{flex:1;text-align:center;border-right:1px solid var(--border);padding:0 8px}
      .hs:last-child{border-right:none}
      .hs-val{font-size:20px}
      .hero-actions{flex-direction:row;flex-wrap:nowrap;gap:8px;width:100%}
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
        <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Foto"><?php else: ?><?=$inicial?><?php endif; ?>
      </div>
      <div>
        <div class="su-name"><?=$nombreCompleto?></div>
        <div class="su-role">🎧 Prestador de Servicios</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard_servicios.php" class="nav-item active"><span class="ni-ico">🏠</span> Panel</a>
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
      </span>Mi <span>Panel de Servicios</span>
    </div>
    <div class="topbar-actions">
      <button class="btn-primary" onclick="abrirModal()" style="display:flex;align-items:center;gap:5px">✏️ Editar perfil</button>
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
            <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Foto"><?php else: ?><?=$inicial?><?php endif; ?>
          </div>
          <div class="dash-avatar-info">
            <span class="dash-avatar-nombre"><?=htmlspecialchars($uRe['nombre'])?></span>
            <span class="dash-avatar-sub"><span class="dash-online-dot"></span>En línea <span class="dash-avatar-arrow">▾</span></span>
          </div>
        </div>
        <div class="dash-dropdown" id="dashDropdown">
          <div class="dash-drop-header">
            <div class="dash-drop-av"><?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Foto"><?php else: ?><?=$inicial?><?php endif; ?></div>
            <div>
              <div class="dash-drop-nombre"><?=$nombreCompleto?></div>
              <div class="dash-drop-tipo">🎧 Servicios para eventos</div>
              <div class="dash-drop-correo"><?=$correo?></div>
            </div>
          </div>
          <?php if (!empty($badgesHTML)): ?><div class="dash-drop-badges"><?=$badgesHTML?></div><?php endif; ?>
          <div class="dash-drop-menu">
            <a href="dashboard_servicios.php" class="dash-drop-link"><span class="dash-dl-icon">🏠</span> Mi panel</a>
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
        <div class="alert-bar ap"><div class="a-ico">⏳</div><div class="a-txt"><strong>Documentos en revisión</strong><span>El administrador está revisando tu documento.</span></div></div>
      <?php elseif ($estadoVerif==='rechazado'): ?>
        <div class="alert-bar ar"><div class="a-ico">❌</div><div class="a-txt"><strong>Verificación rechazada</strong><span>Intenta subir el documento con mejor calidad.</span></div><a href="verificar_cuenta.php" class="a-btn">Reintentar</a></div>
      <?php else: ?>
        <div class="alert-bar as"><div class="a-ico">🪪</div><div class="a-txt"><strong>Verifica tu identidad</strong><span>Sube tu documento y obtén el badge verificado.</span></div><a href="verificar_cuenta.php" class="a-btn">Verificar ahora</a></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert-bar av"><div class="a-ico">✅</div><div class="a-txt"><strong>Cuenta verificada</strong><span>Los clientes ven tu badge de verificación.</span></div></div>
    <?php endif; ?>

    <!-- HERO STRIP -->
    <div class="hero-strip">
      <div class="hero-top-row">
        <div class="hero-av" id="heroAvatar" onclick="abrirModal()" title="Cambiar foto">
          <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Foto"><?php else: ?><?=$inicial?><?php endif; ?>
        </div>
        <div class="hero-info">
          <div class="hero-chips">
            <span class="hchip hc-tipo">🎧 Servicios para eventos</span>
            <?php if ($tieneVerificado): ?><span class="hchip hc-v">✓ Verificado</span><?php endif; ?>
            <?php if ($tienePremium): ?><span class="hchip hc-p">⭐ Premium</span><?php endif; ?>
            <?php if ($tieneTop): ?><span class="hchip hc-top">👑 Top</span><?php endif; ?>
            <?php if ($tieneDestacado): ?><span class="hchip hc-dest">🏅 Destacado</span><?php endif; ?>
          </div>
          <div class="hero-name" id="dNombreHero">¡Hola, <em><?=htmlspecialchars($uRe['nombre'])?></em>!</div>
          <div class="hero-sub"><?=$tipoServicio?:''?><?=$ciudad?' · '.$ciudad:''?></div>
        </div>
      </div>
      <div class="hero-stats">
        <div class="hs"><div class="hs-val"><?=$pct?>%</div><div class="hs-lab">Perfil</div></div>
        <div class="hs"><div class="hs-val"><?=$chatNoLeidos?></div><div class="hs-lab">Mensajes</div></div>
        <div class="hs"><div class="hs-val"><?=$calificacion?>/5</div><div class="hs-lab">Calific.</div></div>
        <div class="hs"><div class="hs-val"><?=$vistasTotal?></div><div class="hs-lab">Vistas</div></div>
      </div>
      <div class="hero-actions">
        <button class="btn-primary" onclick="abrirModal()">✏️ Editar perfil</button>
        <a href="servicios.php" class="btn-secondary">🌐 Ver en directorio</a>
      </div>
    </div>

    <!-- DASHBOARD GRID -->
    <div class="dashboard-grid">

      <!-- MÉTRICAS -->
      <div class="col-4">
        <div class="metric-card">
          <div class="mc-ico g">🎧</div>
          <div>
            <div class="mc-val"><?=$precioDes?:($talento['precio_desde']?'$'.number_format((float)$talento['precio_desde'],0,',','.'):'—')?></div>
            <div class="mc-lab">Precio desde</div>
            <div class="mc-sub" onclick="location.href='servicios.php'">Ver servicios →</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="metric-card">
          <div class="mc-ico a">⭐</div>
          <div>
            <div class="mc-val"><?=$calificacion?>/5</div>
            <div class="mc-lab">Calificación</div>
            <div class="mc-sub">Reseñas de clientes</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="metric-card" onclick="location.href='chat.php'" style="cursor:pointer">
          <div class="mc-ico o">💬</div>
          <div>
            <div class="mc-val"><?=$chatNoLeidos?></div>
            <div class="mc-lab">Mensajes sin leer</div>
            <div class="mc-sub">Ir al chat →</div>
          </div>
        </div>
      </div>

      <!-- PERFIL CARD -->
      <div class="col-4">
        <div class="card" style="height:100%">
          <div class="card-header">
            <div class="card-title">🎧 Mi perfil</div>
            <button class="btn-secondary" onclick="abrirModal()" style="padding:5px 12px;font-size:12px">Editar</button>
          </div>
          <div class="profile-card">
            <div class="pc-av" id="cpAvatarCard" onclick="abrirModal()">
              <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" alt="Foto"><?php else: ?><?=$inicial?><?php endif; ?>
            </div>
            <div class="pc-name"><?=$nombreCompleto?></div>
            <div class="pc-role"><?=$tipoServicio?:'Prestador de servicios'?></div>
            <div class="pc-rows">
              <?php if ($precioDes): ?><div class="pc-row"><span class="pc-row-ico">💰</span><span>Desde <?=$precioDes?></span></div><?php endif; ?>
              <?php if ($ciudad): ?><div class="pc-row"><span class="pc-row-ico">📍</span><span><?=$ciudad?></span></div><?php endif; ?>
              <?php if ($telefono): ?><div class="pc-row"><span class="pc-row-ico">📞</span><span><?=$telefono?></span></div><?php endif; ?>
              <div class="pc-row"><span class="pc-row-ico">✉️</span><span><?=$correo?></span></div>
              <?php if (!empty($badgesHTML)): ?><div style="margin-top:6px"><?=$badgesHTML?></div><?php endif; ?>
            </div>
            <div class="prog-wrap">
              <div class="prog-header"><span>Perfil completado</span><span><?=$pct?>%</span></div>
              <div class="prog-track"><div class="prog-fill" id="progBar" style="width:0%"></div></div>
            </div>
            <div class="vis-row">
              <div>
                <div class="vis-label">Visible en Servicios</div>
                <div class="vis-sub">Directorio público</div>
              </div>
              <div style="display:flex;align-items:center;gap:7px">
                <span id="visChip" class="pv-chip <?=$visible?'ok':'off'?>"><?=$visible?'🟣 Visible':'🟡 Oculto'?></span>
                <label class="tog"><input type="checkbox" <?=$visible?'checked':''?> onchange="toggleVis(this.checked)"><span class="tog-sl"></span></label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ACCIONES RÁPIDAS -->
      <div class="col-8">
        <div class="card">
          <div class="card-header">
            <div class="card-title">⚡ Acciones rápidas</div>
          </div>
          <div class="actions-grid">
            <a href="chat.php" class="action-item">
              <span class="ai-ico">💬</span>
              <span class="ai-label">Mensajes</span>
              <?php if ($chatNoLeidos>0): ?><span class="ai-badge"><?=$chatNoLeidos?></span><?php endif; ?>
            </a>
            <a href="javascript:void(0)" class="action-item" onclick="abrirModalPortafolio()">
              <span class="ai-ico">📸</span>
              <span class="ai-label">Portafolio</span>
            </a>
            <a href="javascript:void(0)" class="action-item" onclick="abrirModal()">
              <span class="ai-ico">✏️</span>
              <span class="ai-label">Editar perfil</span>
            </a>
            <a href="verificar_cuenta.php" class="action-item">
              <span class="ai-ico">🪪</span>
              <span class="ai-label">Verificación</span>
            </a>
            <a href="servicios.php" class="action-item">
              <span class="ai-ico">🌐</span>
              <span class="ai-label">Directorio</span>
            </a>
            <a href="Ayuda.html" class="action-item">
              <span class="ai-ico">❓</span>
              <span class="ai-label">Ayuda</span>
            </a>
          </div>
        </div>
      </div>

      <!-- PORTAFOLIO -->
      <div class="col-8">
        <div class="card">
          <div class="card-header">
            <div class="card-title">📸 Portafolio <span style="background:var(--brand-light);color:var(--brand);font-size:10px;padding:2px 8px;border-radius:20px;font-weight:700;margin-left:6px"><?=$galeriaTotal?> fotos</span></div>
            <button class="btn-primary" onclick="abrirModalPortafolio()" style="padding:6px 14px;font-size:12px">+ Añadir</button>
          </div>
          <?php if ($galeriaTotal>0): ?>
          <div class="gallery-grid">
            <?php foreach ($galeriaItems as $g): ?>
            <div class="gallery-item">
              <img src="<?=htmlspecialchars($g['archivo'])?>" alt="<?=htmlspecialchars($g['titulo']??'')?>">
              <button class="gallery-item-del" onclick="eliminarEvidencia(<?=$g['id']?>,this)">🗑</button>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <div class="gallery-empty" style="margin:16px">
            <div style="font-size:32px;margin-bottom:8px">🎨</div>
            <div style="font-size:13px;font-weight:700;color:var(--ink2);margin-bottom:4px">Sin fotos de portafolio</div>
            <div style="font-size:12px;color:var(--ink4)">Muestra tu trabajo: eventos, montajes, presentaciones…</div>
            <button class="btn-primary" onclick="abrirModalPortafolio()" style="margin-top:12px;font-size:12px">📷 Añadir primera foto</button>
          </div>
          <?php endif; ?>
          <?php if ($tipoServicio||$generos): ?>
          <div style="padding:14px 20px;border-top:1px solid var(--border)">
            <div style="font-size:11px;font-weight:700;color:var(--ink4);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px">🎵 Tipo de servicio</div>
            <div class="svc-chips" style="padding:0">
              <?php if ($tipoServicio): ?><span class="svc-chip">🎧 <?=$tipoServicio?></span><?php endif; ?>
              <?php if ($generos): foreach (explode(',',$talento['generos']) as $g) { $g=trim($g); if ($g) echo "<span class='svc-chip'>🎶 ".htmlspecialchars($g)."</span>"; } endif; ?>
              <?php if ($disponib): ?><span class="svc-chip">📅 <?=$disponib?></span><?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ACTIVIDAD RECIENTE -->
      <div class="col-4">
        <div class="card">
          <div class="card-header"><div class="card-title">🕐 Actividad reciente</div></div>
          <div class="activity-list">
            <div class="act-item">
              <div class="act-dot"></div>
              <div class="act-txt"><strong>Perfil de servicios creado</strong></div>
              <div class="act-time"><?=$fechaRegistro?></div>
            </div>
            <div class="act-item">
              <div class="act-dot"></div>
              <div class="act-txt"><strong><?=$vistasTotal?> visitas totales</strong><br><?=$vistas7dias>0?'+'.$vistas7dias.' esta semana':'Sin visitas recientes'?></div>
            </div>
            <?php if ($galeriaTotal>0): ?>
            <div class="act-item">
              <div class="act-dot"></div>
              <div class="act-txt"><strong><?=$galeriaTotal?> fotos en portafolio</strong></div>
            </div>
            <?php endif; ?>
            <?php if ($tieneVerificado): ?>
            <div class="act-item">
              <div class="act-dot" style="background:#27a855"></div>
              <div class="act-txt"><strong>✅ Perfil verificado</strong></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- BADGES -->
      <div class="col-4">
        <div class="card">
          <div class="card-header"><div class="card-title">🏆 Mis badges</div></div>
          <div class="card-body">
            <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px">
              <?php if ($tieneTop): ?><span class="hchip hc-top">👑 Top</span><?php endif; ?>
              <?php if ($tienePremium): ?><span class="hchip hc-p">⭐ Premium</span><?php endif; ?>
              <?php if ($tieneDestacado): ?><span class="hchip hc-dest">🏅 Destacado</span><?php endif; ?>
              <?php if ($tieneVerificado): ?><span class="hchip hc-v">✓ Verificado</span><?php endif; ?>
              <?php if (!$tieneVerificado&&!$tienePremium&&!$tieneDestacado&&!$tieneTop): ?>
              <div style="font-size:12.5px;color:var(--ink3)">Sin badges aún. <a href="verificar_cuenta.php" style="color:var(--brand);font-weight:700">Verifica tu perfil →</a></div>
              <?php endif; ?>
            </div>
            <div style="background:var(--brand-light);border-radius:10px;padding:13px;border:1px solid var(--border)">
              <div style="font-size:11.5px;font-weight:800;color:var(--brand);margin-bottom:5px">¿Más contrataciones?</div>
              <div style="font-size:11.5px;color:var(--ink3);line-height:1.5">Badges <strong>Premium</strong> y <strong>Destacado</strong> desde el plan <strong>Verde Selva</strong>.</div>
            </div>
          </div>
        </div>
      </div>

      <!-- USO DEL PLAN -->
      <?php
      $cfg=[]; $usados=[];
      if (function_exists('getDatosPlan')) {
        $dp = getDatosPlan($db,$usuario['id']);
        $cfg=$dp['limites']??[]; $usados=$dp['usados']??[];
      }
      if (!empty($cfg)): ?>
      <div class="col-4">
        <div class="card">
          <div class="card-header">
            <div class="card-title">📊 Uso del plan</div>
            <a href="empresas.php#precios" class="card-link">✦ Mejorar</a>
          </div>
          <div class="plan-grid">
            <?php foreach (['mensajes'=>['💬','Mensajes'],'vacantes'=>['💼','Vacantes']] as $key=>[$ico,$lab]):
              $lim=$cfg[$key]??0; if ($lim===0) continue;
              $uso=$usados[$key]??0; $inf=($lim===-1);
              $pctB=$inf?10:min(100,($uso/max(1,$lim))*100);
              $cls=$pctB>=90?'high':($pctB>=70?'mid':'low');
            ?>
            <div class="plan-bar">
              <div class="pb-label"><?=$ico?> <?=$lab?></div>
              <div class="pb-count"><?=$uso?><span> / <?=$inf?'∞':$lim?></span></div>
              <div class="pb-track"><div class="pb-fill <?=$cls?>" style="width:<?=$pctB?>%"></div></div>
              <?php if (!$inf&&$pctB>=70): ?><div class="pb-warn"><?=$pctB>=90?'⚠️ Límite':'⚡ Casi al límite'?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /dashboard-grid -->
  </main>

  <!-- MODAL BANNER CROP -->
  <div id="cropBannerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:99999;align-items:center;justify-content:center;padding:20px">
    <div style="background:#fff;border-radius:18px;padding:22px;max-width:680px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.5)">
      <div style="font-size:15px;font-weight:800;color:var(--brand);margin-bottom:12px;text-align:center">🖼️ Encuadra tu banner</div>
      <div style="position:relative;width:100%;height:200px;overflow:hidden;border-radius:10px;background:#000;display:flex;align-items:center;justify-content:center"><img id="cropBannerImg" style="max-width:100%;display:block"></div>
      <p style="font-size:11.5px;color:#64748b;text-align:center;margin:9px 0">Arrastra y ajusta · Proporción 4:1</p>
      <div style="display:flex;gap:9px;margin-top:4px">
        <button onclick="cancelarCropBanner()" style="flex:1;padding:10px;border-radius:9px;border:1px solid #e0e0e0;background:#f5f5f5;font-size:12.5px;font-weight:700;cursor:pointer">Cancelar</button>
        <button onclick="confirmarCropBanner()" id="btnConfirmarCropBanner" style="flex:2;padding:10px;border-radius:9px;border:none;background:var(--brand);color:#fff;font-size:12.5px;font-weight:800;cursor:pointer">✅ Usar banner</button>
      </div>
    </div>
  </div>

  <!-- MODAL PORTAFOLIO -->
  <div class="modal-ov" id="modalPortafolio">
    <div class="modal-box">
      <button class="mcerrar" onclick="cerrarModalPortafolio()">✕</button>
      <div class="modal-pad">
        <div class="mtit">📸 Subir al portafolio</div>
        <p class="msub">Añade fotos de tus presentaciones, eventos y trabajos realizados. Máx. 15 fotos en plan básico.</p>
        <div id="portMsg" class="mmsg"></div>
        <div class="mfila">
          <div class="mgr full"><label>Título</label><input type="text" id="portTitulo" placeholder="ej: Presentación boda, DJ set festival…"></div>
          <div class="mgr full"><label>Descripción (opcional)</label><textarea id="portDesc" rows="2" placeholder="Cuéntanos sobre este trabajo…"></textarea></div>
        </div>
        <div style="border:2px dashed var(--border2);border-radius:12px;padding:24px;text-align:center;cursor:pointer;background:var(--brand-light);transition:.2s" onclick="document.getElementById('portFile').click()" onmouseover="this.style.background='var(--surface3)'" onmouseout="this.style.background='var(--brand-light)'">
          <div style="font-size:32px;margin-bottom:7px">📷</div>
          <div style="font-size:13px;font-weight:700;color:var(--brand)">Haz clic para seleccionar imagen</div>
          <div style="font-size:11.5px;color:var(--ink3);margin-top:3px">JPG, PNG, WEBP · máx 5 MB</div>
        </div>
        <input type="file" id="portFile" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirEvidencia(this)">
        <div id="portProgress" style="display:none;margin-top:12px;font-size:12.5px;color:var(--brand);font-weight:600;text-align:center">⏳ Subiendo…</div>
      </div>
    </div>
  </div>

  <!-- MODAL EDITAR PERFIL -->
  <div class="modal-ov" id="modalEditar">
    <div class="modal-box">
      <button class="mcerrar" onclick="cerrarModal()">✕</button>
      <div class="modal-pad">
        <div class="mtit">✏️ Editar perfil de servicios</div>
        <p class="msub">Actualiza tu información para que los clientes te contraten más fácilmente.</p>
        <div class="mmsg" id="editMsg"></div>

        <div class="msec">Foto de perfil</div>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
          <div id="fotoPreview" style="width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg,#6d28d9,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:white;overflow:hidden;flex-shrink:0;cursor:pointer" onclick="document.getElementById('fotoInput').click()">
            <?php if ($fotoUrl): ?><img src="<?=$fotoUrl?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else: ?><?=$inicial?><?php endif; ?>
          </div>
          <div>
            <input type="file" id="fotoInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirFoto(this)">
            <div style="display:flex;gap:7px;flex-wrap:wrap">
              <button onclick="document.getElementById('fotoInput').click()" style="padding:7px 13px;border-radius:8px;background:var(--brand);color:white;border:none;font-size:12.5px;font-weight:700;cursor:pointer">📷 Cambiar foto</button>
              <?php if ($fotoUrl): ?><button onclick="eliminarFoto()" style="padding:7px 13px;border-radius:8px;background:transparent;color:#e74c3c;border:1.5px solid #e74c3c;font-size:12.5px;font-weight:700;cursor:pointer">🗑 Quitar</button><?php endif; ?>
            </div>
            <div id="fotoMsg" style="font-size:11.5px;margin-top:4px"></div>
          </div>
        </div>

        <div class="msec">Información personal</div>
        <div class="mfila">
          <div class="mgr"><label>Nombre *</label><input type="text" id="editNombre" value="<?=htmlspecialchars($uRe['nombre']??'')?>"></div>
          <div class="mgr"><label>Apellido</label><input type="text" id="editApellido" value="<?=htmlspecialchars($uRe['apellido']??'')?>"></div>
        </div>
        <div class="mfila">
          <div class="mgr"><label>Ciudad</label><input type="text" id="editCiudad" value="<?=$ciudad?>" placeholder="Quibdó"></div>
          <div class="mgr"><label>Teléfono / WhatsApp</label><input type="tel" id="editTelefono" value="<?=$telefono?>" placeholder="3001234567"></div>
        </div>

        <div class="msec">Información del servicio</div>
        <div class="mfila">
          <div class="mgr"><label>Tipo de servicio</label>
            <select id="editTipoSvc">
              <option value="">Selecciona…</option>
              <?php foreach (['DJ','Chirimía','Banda de música','Fotografía','Video / Filmación','Catering','Decoración de eventos','Animador / MC','Maestro de ceremonias','Sonido e iluminación','Otro'] as $s): ?>
              <option value="<?=$s?>" <?=($talento['tipo_servicio']??'')===$s?'selected':''?>><?=$s?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mgr"><label>Géneros / Estilos</label><input type="text" id="editGeneros" value="<?=htmlspecialchars($talento['generos']??'')?>" placeholder="Salsa, Vallenato, Electrónica…"></div>
        </div>
        <div class="mfila">
          <div class="mgr"><label>Precio desde (COP)</label><input type="number" id="editPrecio" value="<?=htmlspecialchars($talento['precio_desde']??'')?>" placeholder="150000"></div>
          <div class="mgr"><label>Disponibilidad</label><input type="text" id="editDisponib" value="<?=$disponib?>" placeholder="Fines de semana…"></div>
        </div>
        <div class="mfila">
          <div class="mgr"><label>Instagram</label><input type="text" id="editInstagram" value="<?=htmlspecialchars($talento['instagram']??'')?>" placeholder="@miservicio"></div>
          <div class="mgr"><label>Habilidades / Skills</label><input type="text" id="editSkills" value="<?=htmlspecialchars($talento['skills']??'')?>" placeholder="Mezcla, producción, edición…"></div>
        </div>
        <div class="mfila">
          <div class="mgr full"><label>Sobre mí / Descripción</label><textarea id="editBio" rows="3" placeholder="Cuéntanos tu experiencia, equipos que usas, tipos de eventos que cubres…"><?=htmlspecialchars($talento['bio']??'')?></textarea></div>
        </div>
        <button class="btn-save" id="btnGuardar" onclick="guardarServicio()">💾 Guardar cambios</button>

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
        <p style="font-size:13px;color:rgba(255,255,255,.6);line-height:1.6">Se eliminarán tu perfil, portafolio, mensajes y todos los datos asociados.</p>
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
  function abrirModalPortafolio(){document.getElementById('modalPortafolio').classList.add('open')}
  function cerrarModalPortafolio(){document.getElementById('modalPortafolio').classList.remove('open')}
  document.getElementById('modalEditar').addEventListener('click',e=>{if(e.target===document.getElementById('modalEditar'))cerrarModal()});
  document.getElementById('modalPortafolio').addEventListener('click',e=>{if(e.target===document.getElementById('modalPortafolio'))cerrarModalPortafolio()});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){cerrarModal();cerrarModalPortafolio();cerrarEliminarCuenta()}});

  // ── PROGRESS BAR ──
  window.addEventListener('load',()=>{const b=document.getElementById('progBar');if(b)setTimeout(()=>{b.style.width='<?=$pct?>%'},400)});

  function mostrarMsg(t,c){const e=document.getElementById('editMsg');e.textContent=t;e.className='mmsg '+c;e.style.display='block'}

  // ── GUARDAR SERVICIO ──
  async function guardarServicio(){
    const btn=document.getElementById('btnGuardar');
    const n=document.getElementById('editNombre').value.trim();
    if(!n){mostrarMsg('El nombre es obligatorio.','error');return;}
    btn.disabled=true;btn.textContent='⏳ Guardando…';
    const fd=new FormData();
    fd.append('_action','editar_servicio');
    ['nombre','apellido','ciudad','telefono'].forEach(id=>{fd.append(id,document.getElementById('edit'+id.charAt(0).toUpperCase()+id.slice(1)).value.trim())});
    fd.append('tipo_servicio',document.getElementById('editTipoSvc').value);
    fd.append('generos',document.getElementById('editGeneros').value.trim());
    fd.append('precio_desde',document.getElementById('editPrecio').value.trim());
    fd.append('disponibilidad',document.getElementById('editDisponib').value.trim());
    fd.append('instagram',document.getElementById('editInstagram').value.trim());
    fd.append('skills',document.getElementById('editSkills').value.trim());
    fd.append('bio',document.getElementById('editBio').value.trim());
    try{
      const r=await fetch('dashboard_servicios.php',{method:'POST',body:fd});
      const j=await r.json();
      if(j.ok){
        mostrarMsg('¡Perfil actualizado!','success');
        const dNom=document.getElementById('dNombreHero');if(dNom)dNom.textContent=j.nombre;
        if(j.foto){
          const imgTag=`<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
          ['fotoPreview','sidebarAvatar','heroAvatar','dashAvatarImg','cpAvatarCard','navAvatar'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=imgTag;});
        }
        setTimeout(cerrarModal,1500);
      } else mostrarMsg(j.msg||'Error al guardar.','error');
    }catch(e){mostrarMsg('Error de conexión.','error');}
    btn.disabled=false;btn.textContent='💾 Guardar cambios';
  }

  // ── TOGGLE VISIBILIDAD ──
  async function toggleVis(visible){
    const chip=document.getElementById('visChip');
    const fd=new FormData();fd.append('_action','toggle_vis');fd.append('visible',visible?'1':'0');
    try{const r=await fetch('dashboard_servicios.php',{method:'POST',body:fd});const j=await r.json();
      if(j.ok){chip.textContent=visible?'🟣 Visible':'🟡 Oculto';chip.className='pv-chip '+(visible?'ok':'off');}
    }catch(e){}
  }

  // ── FOTO ──
  async function subirFoto(input){
    const file=input.files[0];if(!file)return;
    const msg=document.getElementById('fotoMsg');msg.textContent='⏳ Subiendo…';msg.style.color='var(--ink3)';
    const fd=new FormData();fd.append('_action','subir_foto');fd.append('foto',file);
    try{const r=await fetch('dashboard_servicios.php',{method:'POST',body:fd});const j=await r.json();
      if(j.ok){msg.textContent='✅ Foto actualizada';msg.style.color='var(--brand)';
        const img=`<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
        ['fotoPreview','sidebarAvatar','heroAvatar','dashAvatarImg','cpAvatarCard','navAvatar'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=img;});
      }else{msg.textContent='❌ '+(j.msg||'Error');msg.style.color='#e74c3c';}
    }catch(e){msg.textContent='❌ Error de conexión';msg.style.color='#e74c3c';}
    input.value='';
  }
  async function eliminarFoto(){
    if(!confirm('¿Quitar la foto?'))return;
    const fd=new FormData();fd.append('_action','eliminar_foto');
    try{const r=await fetch('dashboard_servicios.php',{method:'POST',body:fd});const j=await r.json();if(j.ok)location.reload();}catch(e){}
  }

  // ── PORTAFOLIO ──
  async function subirEvidencia(input){
    const file=input.files[0];if(!file)return;
    const msg=document.getElementById('portMsg');const prog=document.getElementById('portProgress');
    prog.style.display='block';msg.style.display='none';
    const fd=new FormData();fd.append('_action','subir_evidencia');fd.append('tipo_media','foto');
    fd.append('archivo',file);
    fd.append('titulo',document.getElementById('portTitulo').value.trim());
    fd.append('descripcion',document.getElementById('portDesc').value.trim());
    try{const r=await fetch('dashboard_servicios.php',{method:'POST',body:fd});const j=await r.json();
      prog.style.display='none';
      if(j.ok){msg.textContent='✅ Foto añadida';msg.className='mmsg success';msg.style.display='block';setTimeout(()=>{cerrarModalPortafolio();location.reload();},1200);}
      else{msg.textContent='❌ '+(j.msg||'Error');msg.className='mmsg error';msg.style.display='block';}
    }catch(e){prog.style.display='none';msg.textContent='❌ Error de conexión';msg.className='mmsg error';msg.style.display='block';}
    input.value='';
  }
  async function eliminarEvidencia(id,el){
    if(!confirm('¿Eliminar esta foto del portafolio?'))return;
    const fd=new FormData();fd.append('_action','eliminar_evidencia');fd.append('id',id);
    try{const r=await fetch('dashboard_servicios.php',{method:'POST',body:fd});const j=await r.json();
      if(j.ok){const item=el.closest('.gallery-item');if(item){item.style.opacity='0';item.style.transition='.3s';setTimeout(()=>item.remove(),320);}}
    }catch(e){}
  }

  // ── BANNER ──
  let cropperBannerInstance=null;
  function subirBanner(input){
    const file=input.files[0];if(!file)return;input.value='';
    const reader=new FileReader();
    reader.onload=e=>{
      const img=document.getElementById('cropBannerImg');
      if(cropperBannerInstance){cropperBannerInstance.destroy();cropperBannerInstance=null;}
      img.src=e.target.result;document.getElementById('cropBannerModal').style.display='flex';
      img.onload=()=>{cropperBannerInstance=new Cropper(img,{aspectRatio:4,viewMode:1,dragMode:'move',autoCropArea:1,cropBoxResizable:false,cropBoxMovable:false,background:false,responsive:true});};
    };reader.readAsDataURL(file);
  }
  function cancelarCropBanner(){document.getElementById('cropBannerModal').style.display='none';if(cropperBannerInstance){cropperBannerInstance.destroy();cropperBannerInstance=null;}}
  async function confirmarCropBanner(){
    if(!cropperBannerInstance)return;
    const btn=document.getElementById('btnConfirmarCropBanner');btn.textContent='⏳ Guardando…';btn.disabled=true;
    const canvas=cropperBannerInstance.getCroppedCanvas({width:1200,height:300,imageSmoothingQuality:'high'});
    document.getElementById('cropBannerModal').style.display='none';
    if(cropperBannerInstance){cropperBannerInstance.destroy();cropperBannerInstance=null;}
    canvas.toBlob(async blob=>{
      const fd=new FormData();fd.append('_action','subir_banner');fd.append('banner',new File([blob],'banner.jpg',{type:'image/jpeg'}));
      try{await fetch('dashboard_servicios.php',{method:'POST',body:fd});}catch(e){}
      btn.textContent='✅ Usar banner';btn.disabled=false;
    },'image/jpeg',.92);
  }

  // ── ELIMINAR CUENTA ──
  function abrirEliminarCuenta(){const m=document.getElementById('modalEliminarCuenta');m.style.display='flex';document.getElementById('inputConfirmarCuenta').value='';document.getElementById('msgEliminarCuenta').style.display='none';}
  function cerrarEliminarCuenta(){document.getElementById('modalEliminarCuenta').style.display='none';}
  async function confirmarEliminarCuenta(){
    const correo=document.getElementById('inputConfirmarCuenta').value.trim();
    const msg=document.getElementById('msgEliminarCuenta');const btn=document.getElementById('btnConfirmarEliminar');
    const fd=new FormData();fd.append('_action','eliminar_cuenta');fd.append('confirmar',correo);
    btn.disabled=true;btn.textContent='⏳ Eliminando…';
    try{const r=await fetch('dashboard_servicios.php',{method:'POST',body:fd});const j=await r.json();
      if(j.ok){msg.style.cssText='display:block;color:#4ade80;font-size:13px;font-weight:700';msg.textContent='✅ Cuenta eliminada. Redirigiendo…';setTimeout(()=>{window.location.href='index.html'},2000);}
      else{msg.style.cssText='display:block;color:#f87171;font-size:13px;font-weight:700';msg.textContent='❌ '+(j.msg||'Error');btn.disabled=false;btn.textContent='🗑 Sí, eliminar';}
    }catch(e){msg.style.cssText='display:block;color:#f87171;font-size:13px;font-weight:700';msg.textContent='❌ Error de conexión';btn.disabled=false;btn.textContent='🗑 Sí, eliminar';}
  }

  // ── NOTIFICACIONES ──
  const notifBtn=document.getElementById('navNotif');
  const notifPanel=document.getElementById('notifPanel');
  const notifDot=document.getElementById('notifDot');
  notifBtn.addEventListener('click',e=>{e.stopPropagation();notifPanel.classList.toggle('open')});
  document.addEventListener('click',e=>{if(!notifBtn.contains(e.target))notifPanel.classList.remove('open')});
  async function cargarNotificaciones(){
    try{const r=await fetch('api_usuario.php?action=notificaciones');const j=await r.json();
      if(j.ok){const n=j.notificaciones;let items=[];let urg=false;
        if(n.mensajes_noLeidos>0){urg=true;items.push(`<a href="chat.php" style="text-decoration:none;color:inherit"><div class="notif-item"><div class="notif-ico">💬</div><div><strong>${n.mensajes_noLeidos}</strong> mensaje${n.mensajes_noLeidos>1?'s':''} sin leer<div class="notif-sub">Ir al chat</div></div></div></a>`);}
        if(n.verificacion_estado==='pendiente'){urg=true;items.push('<div class="notif-item"><div class="notif-ico">⏳</div><div>Verificación en revisión<div class="notif-sub">Te avisamos pronto</div></div></div>');}
        else if(n.verificacion_estado==='aprobado'){items.push('<div class="notif-item"><div class="notif-ico">✅</div><div>¡Cuenta verificada!<div class="notif-sub">Ya tienes el badge</div></div></div>');}
        document.getElementById('notifLista').innerHTML=items.length?items.join(''):'<div class="tnp-empty">Todo al día 🎉<br><small>No hay notificaciones nuevas</small></div>';
        notifDot.style.display=urg?'block':'none';
      }
    }catch(e){}
  }
  cargarNotificaciones();setInterval(cargarNotificaciones,30000);

  // ── DROPDOWN AVATAR ──
  const dashAvatarBtn=document.getElementById('dashAvatarBtn');
  const dashDropdown=document.getElementById('dashDropdown');
  dashAvatarBtn.addEventListener('click',e=>{e.stopPropagation();dashAvatarBtn.classList.toggle('open');dashDropdown.classList.toggle('visible');});
  document.addEventListener('click',e=>{if(!document.getElementById('dashUserWidget').contains(e.target)){dashAvatarBtn.classList.remove('open');dashDropdown.classList.remove('visible');}});
  </script>
</body>
</html>