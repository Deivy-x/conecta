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
if (!$usuario || $usuario['tipo'] !== 'empresa') {
    
    header('Location: dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['_action'] ?? '';

    if ($action === 'editar_empresa') {
        $nombreEmp  = trim($_POST['nombre_empresa'] ?? '');
        $sector     = trim($_POST['sector']         ?? '');
        $nit        = trim($_POST['nit']            ?? '');
        $descripcion= trim($_POST['descripcion']    ?? '');
        $sitioWeb   = trim($_POST['sitio_web']      ?? '');
        $telefonoEmp= trim($_POST['telefono_empresa']?? '');
        $ciudad     = trim($_POST['ciudad']         ?? '');
        $municipio  = trim($_POST['municipio']      ?? '');

        if (!$nombreEmp) {
            echo json_encode(['ok' => false, 'msg' => 'El nombre de la empresa es obligatorio.']); exit;
        }
        
        $db->prepare("UPDATE usuarios SET nombre=?, telefono=?, ciudad=? WHERE id=?")
           ->execute([$nombreEmp, $telefonoEmp, $ciudad, $usuario['id']]);

        $existeStmt = $db->prepare("SELECT id FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
        $existeStmt->execute([$usuario['id']]);
        $filaExistente = $existeStmt->fetchColumn();

        if ($filaExistente) {
            
            $db->prepare("UPDATE perfiles_empresa SET
                nombre_empresa   = ?,
                sector           = ?,
                nit              = ?,
                descripcion      = ?,
                sitio_web        = ?,
                telefono_empresa = ?,
                municipio        = ?,
                actualizado_en   = NOW()
              WHERE usuario_id = ? ORDER BY id DESC LIMIT 1")
               ->execute([$nombreEmp, $sector, $nit, $descripcion, $sitioWeb, $telefonoEmp, $municipio, $usuario['id']]);
        } else {
            
            $db->prepare("INSERT INTO perfiles_empresa
                (usuario_id, nombre_empresa, sector, nit, descripcion, sitio_web, telefono_empresa, municipio)
                VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$usuario['id'], $nombreEmp, $sector, $nit, $descripcion, $sitioWeb, $telefonoEmp, $municipio]);
        }
        echo json_encode(['ok' => true, 'nombre_empresa' => $nombreEmp, 'sector' => $sector, 'ciudad' => $ciudad]);
        exit;
    }

    if ($action === 'toggle_visibilidad') {
        $visible = (int) ($_POST['visible'] ?? 1);
        $db->prepare("UPDATE perfiles_empresa SET visible = ? WHERE usuario_id = ? ORDER BY id DESC LIMIT 1")
           ->execute([$visible, $usuario['id']]);
        echo json_encode(['ok' => true, 'visible' => $visible]);
        exit;
    }

    if ($action === 'eliminar_logo') {
        $epOld = $db->prepare("SELECT logo FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
        $epOld->execute([$usuario['id']]);
        $logoViejo = $epOld->fetchColumn();
        if ($logoViejo && file_exists(__DIR__ . '/uploads/logos/' . $logoViejo)) {
            @unlink(__DIR__ . '/uploads/logos/' . $logoViejo);
        }
        $db->prepare("UPDATE perfiles_empresa SET logo = '' WHERE usuario_id = ? ORDER BY id DESC LIMIT 1")
           ->execute([$usuario['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'subir_logo') {
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió ninguna imagen.']); exit;
        }
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','svg'])) {
            echo json_encode(['ok' => false, 'msg' => 'Solo JPG, PNG, WEBP o SVG.']); exit;
        }
        if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'msg' => 'Imagen demasiado grande (máx 2 MB).']); exit;
        }
        
        $dir = __DIR__ . '/uploads/logos/';
        if (!is_dir(__DIR__ . '/uploads/')) @mkdir(__DIR__ . '/uploads/', 0755, true);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            echo json_encode(['ok' => false, 'msg' => 'Error: no se puede escribir en uploads/logos/. Crea la carpeta manualmente en el servidor con permisos 755.']);
            exit;
        }
        $epOld = $db->prepare("SELECT logo FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
        $epOld->execute([$usuario['id']]);
        $oldLogo = $epOld->fetchColumn();
        if ($oldLogo && !str_starts_with($oldLogo, 'http') && file_exists($dir . $oldLogo)) {
            @unlink($dir . $oldLogo);
        }
        $nombre = 'emp' . $usuario['id'] . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $nombre)) {
            $db->prepare("UPDATE perfiles_empresa SET logo = ? WHERE usuario_id = ? ORDER BY id DESC LIMIT 1")
               ->execute([$nombre, $usuario['id']]);
            echo json_encode(['ok' => true, 'logo' => 'uploads/logos/' . $nombre]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Error al mover el archivo. Verifica permisos en uploads/logos/']);
        }
        exit;
    }

    if ($action === 'subir_banner') {
        if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se recibió imagen.']); exit;
        }
        $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            echo json_encode(['ok' => false, 'msg' => 'Solo JPG, PNG o WEBP.']); exit;
        }
        if ($_FILES['banner']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['ok' => false, 'msg' => 'Máximo 5 MB.']); exit;
        }
        try { $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}
        require_once __DIR__ . '/Php/cloudinary_upload.php';
        $result = cloudinary_upload($_FILES['banner']['tmp_name'], 'quibdoconecta/banners');
        if (!$result['ok']) {
            echo json_encode(['ok' => false, 'msg' => $result['msg']]); exit;
        }
        $url = $result['url'];
        $db->prepare("UPDATE usuarios SET banner=? WHERE id=?")->execute([$url, $usuario['id']]);
        echo json_encode(['ok' => true, 'banner' => $url]);
        exit;
    }

    if ($action === 'eliminar_banner') {
        try { $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}
        $db->prepare("UPDATE usuarios SET banner='' WHERE id=?")->execute([$usuario['id']]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'publicar_vacante') {
        $titulo      = trim($_POST['titulo']      ?? '');
        $tipo        = trim($_POST['tipo']        ?? '');
        $categoria   = trim($_POST['categoria']   ?? '');
        $ubicacion   = trim($_POST['ubicacion']   ?? 'Quibdó, Chocó');
        $salario     = trim($_POST['salario']     ?? '');
        $modalidad   = trim($_POST['modalidad']   ?? 'Presencial');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $requisitos  = trim($_POST['requisitos']  ?? '');
        $vence_en    = trim($_POST['vence_en']    ?? '') ?: null;

        if (!$titulo || !$tipo || !$descripcion || !$requisitos) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan campos obligatorios (título, tipo, descripción y requisitos).']); exit;
        }

        $peStmt = $db->prepare("SELECT nombre_empresa FROM perfiles_empresa WHERE usuario_id=? LIMIT 1");
        $peStmt->execute([$usuario['id']]);
        $peRow = $peStmt->fetch();
        $nombreEmp = $peRow ? $peRow['nombre_empresa'] : ($usuario['nombre'] ?? 'Empresa');

        if (function_exists('verificarLimite')) {
            $lim = verificarLimite($db, $usuario['id'], 'vacantes');
            if (!$lim['puede']) {
                echo msgLimiteSuperado($lim['plan'], 'vacantes', $lim['limite']);
                exit;
            }
        }

        try {
            $db->prepare("
                INSERT INTO empleos
                    (empresa_id, titulo, descripcion, categoria, barrio, ciudad,
                     modalidad, tipo, activo, vence_en, creado_en)
                VALUES (?, ?, ?, ?, '', ?, ?, 'privado', 0, ?, NOW())
            ")->execute([
                $usuario['id'],
                $titulo,
                $descripcion . "\n\nRequisitos:\n" . $requisitos . ($salario ? "\n\nSalario: " . $salario : "") . "\nModalidad de contrato: " . $tipo,
                $categoria,
                $ubicacion,
                $modalidad,
                $vence_en
            ]);
            if (function_exists('registrarAccion')) registrarAccion($db, $usuario['id'], 'vacantes');
            echo json_encode(['ok'=>true,'msg'=>'✅ Vacante enviada. El administrador la aprobará en 24–48 h.']);
        } catch (Exception $e) {
            echo json_encode(['ok'=>false,'msg'=>'Error al guardar: '.$e->getMessage()]);
        }
        exit;
    }

    if ($action === 'eliminar_vacante') {
        $vacanteId = (int)($_POST['vacante_id'] ?? 0);
        if (!$vacanteId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        // Solo puede eliminar sus propias vacantes
        $chk = $db->prepare("SELECT id FROM empleos WHERE id=? AND empresa_id=?");
        $chk->execute([$vacanteId, $usuario['id']]);
        if (!$chk->fetch()) { echo json_encode(['ok'=>false,'msg'=>'No autorizado.']); exit; }
        $db->prepare("DELETE FROM empleos WHERE id=? AND empresa_id=?")->execute([$vacanteId, $usuario['id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($action === 'eliminar_cuenta') {
        $confirmar = trim($_POST['confirmar'] ?? '');
        if ($confirmar !== $usuario['correo']) {
            echo json_encode(['ok' => false, 'msg' => 'El correo no coincide. Escríbelo exactamente.']);
            exit;
        }
        try {
            
            $logoViejo = $ep['logo'] ?? '';
            if ($logoViejo && file_exists(__DIR__ . '/uploads/logos/' . $logoViejo)) {
                @unlink(__DIR__ . '/uploads/logos/' . $logoViejo);
            }
            
            foreach (['perfiles_empresa','sesiones','negocios_locales'] as $tabla) {
                try { $db->prepare("DELETE FROM $tabla WHERE usuario_id=?")->execute([$usuario['id']]); } catch(Exception $e) {}
            }
            
            $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$usuario['id']]);
            $_SESSION = [];
            session_destroy();
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Acción desconocida.']); exit;
}

if (isset($_GET['salir'])) {
    if (isset($_COOKIE['qc_remember'])) {
        try { $db->prepare("DELETE FROM sesiones WHERE token=?")->execute([$_COOKIE['qc_remember']]); } catch(Exception $e) {}
        setcookie('qc_remember', '', time() - 3600, '/');
    }
    $_SESSION = [];
    session_destroy();
    header('Location: inicio_sesion.php');
    exit;
}

$epStmt = $db->prepare("SELECT * FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
$epStmt->execute([$usuario['id']]);
$ep = $epStmt->fetch();

if ($ep && empty($ep['logo'])) {
    $epLogo = $db->prepare("SELECT logo FROM perfiles_empresa WHERE usuario_id=? AND logo != '' AND logo IS NOT NULL ORDER BY id DESC LIMIT 1");
    $epLogo->execute([$usuario['id']]);
    $logoGuardado = $epLogo->fetchColumn();
    if ($logoGuardado) {
        $ep['logo'] = $logoGuardado;
        
        $db->prepare("UPDATE perfiles_empresa SET logo=? WHERE usuario_id=? ORDER BY id DESC LIMIT 1")->execute([$logoGuardado, $usuario['id']]);
    }
}

if (!$ep) {
    $ep = [
        'nombre_empresa' => $usuario['nombre'] ?? '',
        'sector' => '', 'nit' => '', 'descripcion' => '',
        'logo' => '', 'sitio_web' => '', 'telefono_empresa' => '',
        'municipio' => '', 'avatar_color' => 'linear-gradient(135deg,#1a56db,#3b82f6)',
        'visible' => 1, 'visible_admin' => 1, 'destacado' => 0, 'vacantes_activas' => 0
    ];
}

require_once __DIR__ . '/Php/badges_helper.php';
$badgesUsuario = getBadgesUsuario($db, $usuario['id']);
$badgesHTML    = renderBadges($badgesUsuario);

$datosPlan  = function_exists('getDatosPlan') ? getDatosPlan($db, $usuario['id']) : [];
$planActual = $datosPlan['plan'] ?? 'semilla';
$tieneVerificado = (bool)($usuario['verificado'] ?? false)
    || tieneBadge($badgesUsuario, 'Verificado')
    || tieneBadge($badgesUsuario, 'Empresa Verificada');
$tienePremium    = tieneBadge($badgesUsuario, 'Premium');
$tieneDestacado  = tieneBadge($badgesUsuario, 'Destacado') || (int)($ep['destacado'] ?? 0);
$tieneTop        = tieneBadge($badgesUsuario, 'Top');

$nrChat = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario=? AND leido=0");
$nrChat->execute([$usuario['id']]);
$chatNoLeidos = (int)$nrChat->fetchColumn();

$stmtV = $db->prepare("SELECT estado FROM verificaciones WHERE usuario_id=? ORDER BY creado_en DESC LIMIT 1");
$stmtV->execute([$usuario['id']]);
$verifDoc = $stmtV->fetch();
$estadoVerif = $verifDoc ? $verifDoc['estado'] : null;

$vacantesActivas = 0;
try {
    $vStmt = $db->prepare("SELECT COUNT(*) FROM empleos WHERE empresa_id=?");
    $vStmt->execute([$usuario['id']]);
    $vacantesActivas = (int)$vStmt->fetchColumn();
} catch(Exception $e) { $vacantesActivas = 0; }

$historialVacantes = [];
try {
    $hvStmt = $db->prepare("SELECT id, titulo, ciudad, modalidad, activo, creado_en FROM empleos WHERE empresa_id=? ORDER BY creado_en DESC LIMIT 10");
    $hvStmt->execute([$usuario['id']]);
    $historialVacantes = $hvStmt->fetchAll();
} catch(Exception $e) { $historialVacantes = []; }

$campos = ['nombre_empresa', 'sector', 'descripcion', 'sitio_web', 'telefono_empresa', 'municipio'];
$llenos = array_filter($campos, fn($c) => !empty($ep[$c]));
$pct = (int)(count($llenos) / count($campos) * 100);
if (!empty($ep['logo'])) $pct = min(100, $pct + 5);
if ($tieneVerificado)     $pct = min(100, $pct + 5);

$nombreEmpresa  = htmlspecialchars(trim($ep['nombre_empresa'] ?: $usuario['nombre'] ?? 'Mi Empresa'));
$iniciales      = strtoupper(mb_substr($nombreEmpresa, 0, 2));
$sector         = htmlspecialchars($ep['sector'] ?? '');
$ciudad         = htmlspecialchars($usuario['ciudad'] ?? '');
$logoUrl        = !empty($ep['logo'])
    ? (str_starts_with($ep['logo'], 'http') ? htmlspecialchars($ep['logo']) : 'uploads/logos/' . htmlspecialchars($ep['logo']))
    : '';

try { $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto"); } catch(Exception $e){}

$usuarioRe = $db->prepare("SELECT * FROM usuarios WHERE id=?"); $usuarioRe->execute([$usuario['id']]); $usuarioRe = $usuarioRe->fetch();
$bannerUrl = !empty($usuarioRe['banner']) ? (str_starts_with($usuarioRe['banner'], 'http') ? htmlspecialchars($usuarioRe['banner']) : 'uploads/banners/' . htmlspecialchars($usuarioRe['banner'])) : '';
$fechaRegistro  = date('d \de F Y', strtotime($usuario['creado_en']));
$correo         = htmlspecialchars($usuario['correo']);
$visibleEnWeb   = (int)($ep['visible'] ?? 1) && (int)($ep['visible_admin'] ?? 1);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Panel Empresa – QuibdóConecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Cabinet+Grotesk:wght@400;500;700;800;900&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root {
      --ink: #0e0e0e; --ink2: #3a3a3a; --ink3: #888;
      --papel: #f5f2ed; --blanco: #fff;
      --verde: #1c5c32; --verde2: #2d8a50; --hoja: #4dba74; --lima: #a8e063; --selva: #0a2e16;
      --azul: #1a56db; --azul2: #3b82f6; --azul-claro: #eff6ff;
      --oro: #c8860a; --dorado: #f0b429; --arena: #f9f3e3;
      --borde: rgba(0,0,0,.09); --sombra: 0 2px 16px rgba(0,0,0,.07)
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{font-family:'Cabinet Grotesk',sans-serif;background:var(--papel);color:var(--ink);min-height:100vh}

    .navbar{position:sticky;top:0;z-index:200;background:#0a1a3e;display:flex;align-items:center;justify-content:space-between;padding:0 40px;height:56px;border-bottom:1px solid rgba(255,255,255,.06)}
    .nav-marca{display:flex;align-items:center;gap:10px;text-decoration:none}
    .nav-marca img{width:28px;filter:drop-shadow(0 2px 6px rgba(96,165,250,.3))}
    .nav-marca-texto{font-family:'Instrument Serif',serif;font-size:18px;color:white}
    .nav-marca-texto em{color:#60a5fa;font-style:italic}
    .nav-links{display:flex;align-items:center;gap:4px}
    .nl{display:flex;align-items:center;gap:6px;padding:7px 13px;border-radius:8px;color:rgba(255,255,255,.55);text-decoration:none;font-size:13px;font-weight:700;transition:all .2s;position:relative}
    .nl:hover{background:rgba(255,255,255,.07);color:white}
    .nl.on{background:rgba(96,165,250,.15);color:#60a5fa}
    .nl-dot{position:absolute;top:5px;right:5px;width:7px;height:7px;border-radius:50%;background:#e74c3c;border:1.5px solid #0a1a3e}
    .nav-usuario{display:flex;align-items:center;gap:10px}
    .nav-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--azul),var(--azul2));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:900;color:white;cursor:pointer;border:2px solid rgba(96,165,250,.3);overflow:hidden;flex-shrink:0}
    .nav-nombre{font-size:13px;font-weight:700;color:rgba(255,255,255,.8);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .nav-salir{padding:6px 12px;border-radius:8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:rgba(255,255,255,.5);font-size:12px;font-weight:700;text-decoration:none;transition:all .2s}
    .nav-salir:hover{background:rgba(231,76,60,.15);color:#ff8080}
    .nav-notif{position:relative;width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:16px;cursor:pointer;transition:background .2s;flex-shrink:0}
    .nav-notif:hover{background:rgba(255,255,255,.12)}
    .notif-dot{position:absolute;top:4px;right:4px;width:9px;height:9px;border-radius:50%;background:#e74c3c;border:2px solid #0a1a3e;animation:pulse-dot 1.5s infinite}
    @keyframes pulse-dot{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.3);opacity:.7}}

    .hero{background:linear-gradient(135deg,#0a1a3e 0%,#1a3060 60%,#0a1a3e 100%);padding:48px 40px 36px;position:relative;overflow:hidden}
    .hero::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 80% 50%,rgba(26,86,219,.15) 0%,transparent 60%);pointer-events:none}
    .hero-inner{position:relative;z-index:2;display:flex;align-items:center;gap:24px;flex-wrap:wrap}
    .hero-av{width:72px;height:72px;border-radius:16px;background:linear-gradient(135deg,var(--azul),var(--azul2));display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;color:white;flex-shrink:0;cursor:pointer;border:3px solid rgba(96,165,250,.3);overflow:hidden;transition:all .2s}
    .hero-av:hover{border-color:#60a5fa;transform:scale(1.03)}
    .hero-info{flex:1;min-width:200px}
    .hero-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .hchip{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700}
    .hc-tipo{background:rgba(96,165,250,.18);color:#93c5fd}
    .hc-veri{background:rgba(16,185,129,.18);color:#6ee7b7}
    .hc-prem{background:rgba(245,158,11,.18);color:#fcd34d}
    .hc-dest{background:rgba(168,85,247,.18);color:#c4b5fd}
    .hc-top{background:rgba(239,68,68,.18);color:#fca5a5}
    .hero-nombre{font-family:'Instrument Serif',serif;font-size:26px;color:white;line-height:1.2;margin-bottom:6px}
    .hero-sub{font-size:14px;color:rgba(255,255,255,.55);display:flex;align-items:center;gap:16px;flex-wrap:wrap}
    .hero-stats{display:flex;gap:24px;flex-wrap:wrap;margin-top:20px}
    .hs{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:14px 20px;text-align:center;min-width:110px}
    .hs-val{font-size:22px;font-weight:900;color:white}
    .hs-lab{font-size:11px;color:rgba(255,255,255,.45);margin-top:2px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
    .hero-deco{position:absolute;right:40px;bottom:-10px;font-size:120px;opacity:.06;pointer-events:none;z-index:1}

    .alerta{display:flex;align-items:center;gap:16px;padding:16px 20px;border-radius:14px;margin:0 40px 24px;border:1px solid}
    .alerta .a-ico{font-size:22px;flex-shrink:0}
    .alerta .a-txt{flex:1;font-size:13px;line-height:1.5}
    .alerta .a-txt strong{display:block;font-size:14px;font-weight:800;margin-bottom:2px}
    .alerta .a-btn{padding:8px 16px;border-radius:8px;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap;background:var(--azul);color:white}
    .as{background:#eff6ff;border-color:#93c5fd;color:#1e40af}
    .ap{background:#fffbeb;border-color:#fcd34d;color:#92400e}
    .ar{background:#fff1f2;border-color:#fca5a5;color:#991b1b}
    .av{background:#f0fdf4;border-color:#bbf7d0;color:#166534}

    .contenido{padding:32px 40px}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
    .span2{grid-column:span 2}
    .span3{grid-column:span 3}
    .card{background:var(--blanco);border:1px solid var(--borde);border-radius:18px;padding:22px;box-shadow:var(--sombra)}

    .mini{display:flex;align-items:center;gap:14px}
    .m-ico{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
    .ig{background:#dbeafe}
    .ia{background:#d1fae5}
    .im{background:#ede9fe}
    .io{background:#fef3c7}
    .m-val{font-size:26px;font-weight:900;color:var(--ink)}
    .m-lab{font-size:12px;color:var(--ink3);font-weight:600;margin-top:1px}
    .m-sub{font-size:11px;color:var(--azul);font-weight:700;margin-top:2px;cursor:pointer}

    .ca-tit{font-size:13px;font-weight:800;color:var(--ink3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:16px}
    .ac-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px}
    .ac{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;border-radius:14px;background:var(--papel);border:1px solid var(--borde);text-decoration:none;transition:all .2s;position:relative}
    .ac:hover{background:#eff6ff;border-color:#93c5fd;transform:translateY(-2px);box-shadow:0 6px 16px rgba(26,86,219,.1)}
    .ac-ico{font-size:22px}
    .ac-tit{font-size:12px;font-weight:800;color:var(--ink2);text-align:center}
    .ac-desc{font-size:11px;color:var(--ink3);text-align:center}
    .ac-badge{position:absolute;top:-6px;right:-6px;background:#e74c3c;color:white;font-size:10px;font-weight:800;padding:2px 7px;border-radius:10px;white-space:nowrap}

    .ce-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
    .ce-tit{font-size:14px;font-weight:800;color:var(--ink)}
    .ce-ver{font-size:12px;font-weight:700;color:var(--azul);text-decoration:none}
    .ce-ver:hover{text-decoration:underline}
    .ce-list{display:flex;flex-direction:column;gap:10px}
    .ce-item{display:flex;align-items:center;gap:12px;padding:12px;border-radius:12px;background:var(--papel);border:1px solid var(--borde);cursor:pointer;transition:all .2s}
    .ce-item:hover{background:#eff6ff;border-color:#93c5fd}
    .ce-ico{font-size:22px;flex-shrink:0}
    .ce-info{flex:1;min-width:0}
    .ce-nom{font-size:13px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .ce-emp{font-size:12px;color:var(--ink3)}
    .ce-met{font-size:11px;color:var(--azul);font-weight:600;margin-top:2px}
    .ce-badge{font-size:11px;font-weight:700;padding:3px 9px;border-radius:10px;background:var(--azul-claro);color:var(--azul);white-space:nowrap;flex-shrink:0}

    .cp-head{display:flex;flex-direction:column;align-items:center;text-align:center;padding-bottom:16px;border-bottom:1px solid var(--borde)}
    .cp-av{width:80px;height:80px;border-radius:16px;background:linear-gradient(135deg,var(--azul),var(--azul2));display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:900;color:white;margin-bottom:12px;cursor:pointer;border:3px solid rgba(26,86,219,.2);overflow:hidden;transition:all .2s}
    .cp-av:hover{border-color:var(--azul)}
    .cp-nom{font-size:17px;font-weight:900;margin-bottom:3px}
    .cp-pro{font-size:13px;color:var(--azul);font-weight:600}
    .cp-body{padding:14px 0;display:flex;flex-direction:column;gap:8px;font-size:13px;color:var(--ink2)}
    .cp-fil{display:flex;align-items:center;gap:8px}
    .cp-ico{font-size:16px;flex-shrink:0}

    .vis-row{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:var(--papel);border-radius:12px;border:1px solid var(--borde);margin-top:4px}
    .vis-label{font-size:13px;font-weight:700;color:var(--ink2)}
    .vis-sub{font-size:11px;color:var(--ink3);margin-top:2px}
    .toggle-wrap{display:flex;align-items:center;gap:8px}
    .toggle{position:relative;width:44px;height:24px;cursor:pointer}
    .toggle input{opacity:0;width:0;height:0}
    .toggle-slider{position:absolute;inset:0;border-radius:12px;background:#cbd5e1;transition:.3s}
    .toggle-slider::before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;border-radius:50%;background:white;transition:.3s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
    .toggle input:checked + .toggle-slider{background:var(--azul)}
    .toggle input:checked + .toggle-slider::before{transform:translateX(20px)}
    .pv-chip{font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px;display:inline-block}
    .pv-chip.ok{background:#d1fae5;color:#065f46}
    .pv-chip.off{background:#fef3c7;color:#92400e}

    .prog-w{margin:12px 0}
    .prog-h{display:flex;justify-content:space-between;font-size:12px;font-weight:700;margin-bottom:6px;color:var(--ink3)}
    .prog-t{height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden}
    .prog-f{height:100%;background:linear-gradient(90deg,var(--azul),var(--azul2));border-radius:4px;transition:width 1s ease}

    .hist-tit{font-size:13px;font-weight:800;color:var(--ink3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px}
    .hist-list{display:flex;flex-direction:column;gap:8px}
    .hist-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;background:var(--papel);border:1px solid var(--borde)}
    .hist-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .dot-activo{background:#22c55e}
    .dot-inactivo{background:#94a3b8}
    .hist-info{flex:1;min-width:0}
    .hist-nom{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .hist-meta{font-size:11px;color:var(--ink3);margin-top:2px}
    .hist-fecha{font-size:11px;color:var(--ink3);flex-shrink:0;white-space:nowrap}

    .badge-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
    .bdg{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700}
    .bdg-v{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
    .bdg-p{background:#fffbeb;color:#92400e;border:1px solid #fcd34d}
    .bdg-d{background:#f3e8ff;color:#6b21a8;border:1px solid #d8b4fe}
    .bdg-t{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}

    .btn-edit{width:100%;padding:11px;border-radius:12px;background:linear-gradient(135deg,var(--azul),var(--azul2));color:white;border:none;font-size:13px;font-weight:800;cursor:pointer;font-family:'Cabinet Grotesk',sans-serif;margin-top:14px;transition:all .2s;box-shadow:0 4px 12px rgba(26,86,219,.3)}
    .btn-edit:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(26,86,219,.4)}
    .btn-sec{width:100%;padding:11px;border-radius:12px;background:transparent;color:var(--azul);border:2px solid var(--azul);font-size:13px;font-weight:800;cursor:pointer;font-family:'Cabinet Grotesk',sans-serif;margin-top:8px;transition:all .2s}
    .btn-sec:hover{background:var(--azul-claro)}

    .modal-ov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:500;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px)}
    .modal-ov.open{display:flex}
    .modal-box{background:var(--blanco);border-radius:22px;max-width:560px;width:100%;box-shadow:0 30px 80px rgba(0,0,0,.2);animation:fadeUp .3s ease;max-height:90vh;overflow-y:auto;position:relative}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .mcerrar{position:absolute;top:16px;right:18px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--ink3);z-index:1}
    .mcerrar:hover{color:var(--ink)}
    .modal-pad{padding:32px}
    .mtit{font-family:'Instrument Serif',serif;font-size:22px;font-weight:700;margin-bottom:6px}
    .msub{font-size:13px;color:var(--ink3);margin-bottom:20px;line-height:1.5}
    .msec{font-size:11px;font-weight:800;color:var(--ink3);text-transform:uppercase;letter-spacing:.7px;margin:16px 0 8px}
    .mmsg{display:none;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:700;margin-bottom:14px}
    .mmsg.success{background:#d1fae5;color:#065f46}
    .mmsg.error{background:#fee2e2;color:#991b1b}
    .mfila{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px}
    .mgr{display:flex;flex-direction:column;gap:5px}
    .mgr.full{grid-column:1/-1}
    .mgr label{font-size:12px;font-weight:700;color:var(--ink3)}
    .mgr input,.mgr select,.mgr textarea{border:1.5px solid var(--borde);border-radius:10px;padding:10px 12px;font-size:13px;font-family:'Cabinet Grotesk',sans-serif;color:var(--ink);background:var(--papel);transition:border-color .2s;outline:none;resize:none}
    .mgr input:focus,.mgr select:focus,.mgr textarea:focus{border-color:var(--azul)}
    .btn-save{width:100%;padding:13px;border-radius:12px;background:linear-gradient(135deg,var(--azul),var(--azul2));color:white;border:none;font-size:14px;font-weight:900;cursor:pointer;font-family:'Cabinet Grotesk',sans-serif;margin-top:18px;box-shadow:0 4px 14px rgba(26,86,219,.35);transition:all .2s}
    .btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(26,86,219,.45)}
    .btn-save:disabled{opacity:.6;cursor:not-allowed;transform:none}

    @media(max-width:900px){.grid{grid-template-columns:1fr 1fr}.span3{grid-column:1/-1}.span2{grid-column:1/-1}}
    @media(max-width:640px){
      .navbar{padding:0 16px}.nav-links{display:none}
      .hero{padding:32px 20px 28px}.hero-deco{display:none}
      .alerta{margin:0 20px 16px}.contenido{padding:20px}
      .grid{grid-template-columns:1fr}.span2,.span3{grid-column:1/-1}
      .hero-stats{gap:10px}.hs{min-width:80px;padding:10px 14px}
      .mfila{grid-template-columns:1fr}
    }
  </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<header class="navbar">
  <a href="index.html" class="nav-marca">
    <img src="Imagenes/Quibdo.png" alt="Logo">
    <span class="nav-marca-texto">Quibdó<em>Conecta</em></span>
  </a>
  <nav class="nav-links">
    <a href="dashboard_empresa.php" class="nl on">🏠 Panel</a>
    <a href="talentos.php" class="nl">🌟 Talentos</a>
    <a href="Empleo.php" class="nl">💼 Empleos</a>
    <a href="javascript:void(0)" onclick="abrirModalVacante()" class="nl">➕ Publicar vacante</a>
    <a href="chat.php" class="nl">
      💬 Mensajes
      <?php if ($chatNoLeidos > 0): ?><span class="nl-dot"></span><?php endif; ?>
    </a>
    <a href="Ayuda.html" class="nl">❓ Ayuda</a>
    <a href="buscar.php" class="nl">🔍 Buscar</a>
  </nav>
  <div class="nav-usuario">
    <div class="nav-avatar" id="navAvatar" onclick="abrirModal()">
      <?php if ($logoUrl): ?>
        <img src="<?= $logoUrl ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover">
      <?php else: ?>
        <?= $iniciales ?>
      <?php endif; ?>
    </div>
    <span class="nav-nombre"><?= $nombreEmpresa ?></span>
    <a href="?salir=1" class="nav-salir">Salir</a>
  </div>
</header>

<!-- ── HERO ── -->
<div class="hero">
  <div class="hero-inner">
    <div class="hero-av" id="heroAvatar" onclick="abrirModal()" title="Cambiar logo">
      <?php if ($logoUrl): ?>
        <img src="<?= $logoUrl ?>" alt="Logo empresa" style="width:100%;height:100%;object-fit:cover">
      <?php else: ?>
        <?= $iniciales ?>
      <?php endif; ?>
    </div>
    <div class="hero-info">
      <div class="hero-chips">
        <span class="hchip hc-tipo">🏢 Empresa</span>
        <?php if ($tieneTop): ?><span class="hchip hc-top">👑 Top</span>
        <?php elseif ($tienePremium): ?><span class="hchip hc-prem">⭐ Premium</span>
        <?php elseif ($tieneDestacado): ?><span class="hchip hc-dest">🏅 Destacada</span>
        <?php endif; ?>
        <?php if ($tieneVerificado): ?><span class="hchip hc-veri">✓ Verificada</span><?php endif; ?>
      </div>
      <div class="hero-nombre"><?= $nombreEmpresa ?></div>
      <div class="hero-sub">
        <?php if ($sector): ?><span>🏷️ <?= $sector ?></span><?php endif; ?>
        <?php if ($ciudad): ?><span>📍 <?= $ciudad ?></span><?php endif; ?>
        <span>📅 Desde <?= $fechaRegistro ?></span>
      </div>
    </div>
  </div>
  <div class="hero-stats">
    <div class="hs">
      <div class="hs-val"><?= $vacantesActivas ?></div>
      <div class="hs-lab">Vacantes activas</div>
    </div>
    <div class="hs">
      <div class="hs-val"><?= $pct ?>%</div>
      <div class="hs-lab">Perfil completado</div>
    </div>
    <div class="hs">
      <div class="hs-val"><?= $visibleEnWeb ? '🟢' : '🟡' ?></div>
      <div class="hs-lab"><?= $visibleEnWeb ? 'Visible en web' : 'Oculto en web' ?></div>
    </div>
    <div class="hs">
      <div class="hs-val"><?= $chatNoLeidos ?: '0' ?></div>
      <div class="hs-lab">Mensajes nuevos</div>
    </div>
  </div>
  <div class="hero-deco">🏢</div>
</div>

<!-- ── ALERTAS ── -->
<div style="padding:24px 40px 0">
  <?php if (!$tieneVerificado): ?>
    <?php if ($estadoVerif === 'pendiente'): ?>
      <div class="alerta ap">
        <div class="a-ico">⏳</div>
        <div class="a-txt"><strong>Documentos en revisión</strong><span>El administrador está revisando tu RUT o cámara de comercio.</span></div>
      </div>
    <?php elseif ($estadoVerif === 'rechazado'): ?>
      <div class="alerta ar">
        <div class="a-ico">❌</div>
        <div class="a-txt"><strong>Verificación rechazada</strong><span>Intenta subir los documentos con mejor calidad.</span></div>
        <a href="verificar_cuenta.php" class="a-btn">Reintentar</a>
      </div>
    <?php else: ?>
      <div class="alerta as">
        <div class="a-ico">🏢</div>
        <div class="a-txt"><strong>Verifica tu empresa</strong><span>Sube tu RUT o cámara de comercio y obtén el badge de Empresa Verificada.</span></div>
        <a href="verificar_cuenta.php" class="a-btn">Verificar ahora</a>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="alerta av">
      <div class="a-ico">✅</div>
      <div class="a-txt"><strong>Empresa verificada</strong><span>Los talentos ven tu badge de Empresa Verificada al ver tus vacantes.</span></div>
    </div>
  <?php endif; ?>
</div>

<!-- ── CONTENIDO ── -->
<div class="contenido">
  <div class="grid">

    <!-- ── LOGO + BANNER EMPRESA ── -->
    <div class="card span3" style="border:1.5px solid #dbeafe;padding:0;overflow:visible">
      <div style="overflow:hidden;border-radius:17px">

        <!-- BANNER -->
        <div id="bannerZone" style="position:relative;height:160px;background:linear-gradient(135deg,#1a3060,#1a56db);cursor:pointer;overflow:hidden" onclick="document.getElementById('bannerInput').click()" title="Cambiar banner">
          <?php if ($bannerUrl): ?>
            <img id="bannerImg" src="<?= $bannerUrl ?>" style="width:100%;height:100%;object-fit:cover;display:block">
          <?php else: ?>
            <div id="bannerPlaceholder" style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:rgba(255,255,255,.6)">
              <div style="font-size:36px">🖼️</div>
              <div style="font-size:13px;font-weight:600">Haz clic para subir el banner de tu empresa</div>
              <div style="font-size:11px;opacity:.7">Recomendado: 1200 × 300 px · JPG, PNG, WEBP · máx 5 MB</div>
            </div>
          <?php endif; ?>
          <div style="position:absolute;inset:0;background:rgba(0,0,0,0);transition:.2s;display:flex;align-items:center;justify-content:center"
               onmouseover="this.style.background='rgba(0,0,0,.35)';this.querySelector('span').style.opacity='1'"
               onmouseout="this.style.background='rgba(0,0,0,0)';this.querySelector('span').style.opacity='0'">
            <span style="opacity:0;color:#fff;font-size:13px;font-weight:700;background:rgba(0,0,0,.5);padding:8px 18px;border-radius:20px;transition:.2s">✏️ Cambiar banner</span>
          </div>
          <?php if ($bannerUrl): ?>
          <button onclick="event.stopPropagation();eliminarBanner()" style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:20px;padding:5px 12px;font-size:11px;font-weight:700;cursor:pointer">🗑 Quitar</button>
          <?php endif; ?>
        </div>
        <input type="file" id="bannerInput" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="subirBanner(this)">

        <!-- LOGO + INFO -->
        <div style="padding:0 26px 22px;margin-top:-50px;position:relative;z-index:2">
          <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:14px">
            <!-- Logo empresa -->
            <div style="position:relative;display:inline-block">
              <div id="cpAvatar" onclick="abrirModal()" title="Cambiar logo"
                   style="width:100px;height:100px;border-radius:18px;border:4px solid #fff;box-shadow:0 4px 18px rgba(0,0,0,.18);background:linear-gradient(135deg,#1a56db,#3b82f6);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:900;color:#fff;cursor:pointer;overflow:hidden;transition:.2s">
                <?php if ($logoUrl): ?>
                  <img src="<?= $logoUrl ?>" style="width:100%;height:100%;object-fit:cover" alt="Logo">
                <?php else: ?>
                  <?= $iniciales ?>
                <?php endif; ?>
              </div>
              <div onclick="abrirModal()" style="position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;background:#1a56db;border:2px solid #fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px">✏️</div>
            </div>
            <!-- Nombre + info -->
            <div style="flex:1;min-width:180px;padding-top:56px">
              <div style="font-size:20px;font-weight:800;color:#0e1a3e"><?= $nombreEmpresa ?></div>
              <div style="font-size:13px;color:#64748b;margin-top:3px">
                <?php if ($sector): ?><?= htmlspecialchars($sector) ?><?php endif; ?>
                <?php if ($ciudad): ?> · 📍 <?= $ciudad ?><?php endif; ?>
              </div>
            </div>
            <!-- Acciones -->
            <div style="display:flex;gap:10px;padding-top:56px;flex-wrap:wrap">
              <a href="perfil.php?id=<?= $usuario['id'] ?>" target="_blank" style="display:inline-flex;align-items:center;gap:5px;padding:9px 16px;background:#eff6ff;color:#1a56db;border:1.5px solid #bfdbfe;border-radius:12px;font-size:12px;font-weight:700;text-decoration:none">
                👁 Ver mi perfil
              </a>
              <button onclick="abrirModal()" style="display:inline-flex;align-items:center;gap:5px;padding:9px 16px;background:#1a56db;color:#fff;border:none;border-radius:12px;font-size:12px;font-weight:700;cursor:pointer">
                🖼 Cambiar logo
              </button>
              <?php if ($logoUrl): ?>
              <button onclick="eliminarLogoBanner()" id="btnEliminarLogoBanner" style="display:inline-flex;align-items:center;gap:5px;padding:9px 16px;background:transparent;color:#e53935;border:1.5px solid #e53935;border-radius:12px;font-size:12px;font-weight:700;cursor:pointer">
                🗑 Eliminar logo
              </button>
              <?php endif; ?>
            </div>
          </div>
          <div id="bannerMsg" style="font-size:12px;color:#e53935;margin-top:8px;display:none"></div>
        </div>

      </div>
    </div>

    <!-- MINI: VACANTES -->
    <div class="card mini">
      <div class="m-ico ig">💼</div>
      <div>
        <div class="m-val"><?= $vacantesActivas ?></div>
        <div class="m-lab">Vacantes activas</div>
        <div class="m-sub" onclick="abrirModalVacante()">Publicar nueva →</div>
      </div>
    </div>

    <!-- MINI: CANDIDATOS -->
    <div class="card mini">
      <div class="m-ico ia">👥</div>
      <div>
        <div class="m-val">0</div>
        <div class="m-lab">Candidatos recibidos</div>
        <div class="m-sub" onclick="location.href='talentos.php'">Ver talentos →</div>
      </div>
    </div>

    <!-- MINI: CHAT -->
    <div class="card mini" onclick="location.href='chat.php'" style="cursor:pointer">
      <div class="m-ico io">💬</div>
      <div>
        <div class="m-val"><?= $chatNoLeidos ?: '0' ?></div>
        <div class="m-lab">Mensajes</div>
        <div class="m-sub">Ir al chat →</div>
      </div>
    </div>

    <!-- ACCIONES RÁPIDAS (span 3) -->
    <div class="card span3">
      <div class="ca-tit">⚡ Acciones rápidas</div>
      <div class="ac-row">
        <a href="javascript:void(0)" onclick="abrirModalVacante()" class="ac">
          <div class="ac-ico">➕</div>
          <div class="ac-tit">Publicar vacante</div>
          <div class="ac-desc">Nueva oferta de empleo</div>
        </a>
        <a href="talentos.php" class="ac">
          <div class="ac-ico">🌟</div>
          <div class="ac-tit">Ver talentos</div>
          <div class="ac-desc">Profesionales locales</div>
        </a>
        <a href="chat.php" class="ac">
          <div class="ac-ico">💬</div>
          <div class="ac-tit">Mensajes</div>
          <?php if ($chatNoLeidos > 0): ?>
            <span class="ac-badge"><?= $chatNoLeidos ?> sin leer</span>
          <?php else: ?>
            <div class="ac-desc">Sin nuevos</div>
          <?php endif; ?>
        </a>
        <a href="empresas.php" class="ac">
          <div class="ac-ico">🏢</div>
          <div class="ac-tit">Mi empresa</div>
          <div class="ac-desc">Ver en directorio</div>
        </a>
        <a href="verificar_cuenta.php" class="ac">
          <div class="ac-ico">🪪</div>
          <div class="ac-tit">Verificación</div>
          <div class="ac-desc"><?= $tieneVerificado ? '✅ Verificada' : 'Subir documentos' ?></div>
        </a>
        <a href="Empleo.html" class="ac">
          <div class="ac-ico">🔍</div>
          <div class="ac-tit">Ver empleos</div>
          <div class="ac-desc">Mercado laboral</div>
        </a>
        <a href="Ayuda.html" class="ac">
          <div class="ac-ico">❓</div>
          <div class="ac-tit">Ayuda</div>
          <div class="ac-desc">Soporte y guías</div>
        </a>
      </div>
    </div>

    <!-- ── PLAN ACTIVO (span 3) ── -->
    <?php if (!empty($datosPlan)): ?>
    <?php
      $usados  = $datosPlan['usados'] ?? [];
      $cfg     = $datosPlan['config'] ?? [];
      $showBars = [
        'vacantes'  => ['💼', 'Vacantes'],
        'mensajes'  => ['💬', 'Mensajes'],
      ];
    ?>
    <div class="card span3" style="background:linear-gradient(135deg,#f0faf4,#e8f5e9);border:1.5px solid #a5d6a7">
      <div style="padding:20px 24px">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
          <div style="display:flex;align-items:center;gap:12px">
            <div style="width:42px;height:42px;border-radius:12px;background:#2e7d32;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">⭐</div>
            <div>
              <div style="font-size:10px;font-weight:700;color:#558b6e;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:2px">Plan activo</div>
              <div style="font-size:20px;font-weight:800;color:#1b5e20;line-height:1.1"><?= htmlspecialchars($datosPlan['nombre'] ?? 'Semilla') ?></div>
            </div>
          </div>
          <a href="empresas.php#planes" style="display:inline-flex;align-items:center;gap:5px;padding:9px 18px;background:#2e7d32;color:#fff;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;box-shadow:0 3px 10px rgba(46,125,50,.3)">
            ✦ Mejorar plan
          </a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
          <?php foreach ($showBars as $key => [$ico, $label]): ?>
            <?php
              $limite  = $cfg[$key] ?? 0;
              if ($limite === 0) continue;
              $usado   = $usados[$key] ?? 0;
              $esInf   = ($limite === -1);
              $pctBar  = $esInf ? 10 : min(100, ($usado / max(1, $limite)) * 100);
              $color   = $pctBar >= 90 ? '#e53935' : ($pctBar >= 70 ? '#fb8c00' : '#43a047');
              $bgCard  = $pctBar >= 90 ? '#fff5f5' : ($pctBar >= 70 ? '#fff8f0' : '#f9fbe7');
              $bdCard  = $pctBar >= 90 ? '#ef9a9a' : ($pctBar >= 70 ? '#ffcc80' : '#c5e1a5');
              $numCol  = $pctBar >= 90 ? '#c62828' : ($pctBar >= 70 ? '#e65100' : '#2e7d32');
              $limTxt  = $esInf ? '∞' : $limite;
            ?>
            <div style="background:<?= $bgCard ?>;border:1px solid <?= $bdCard ?>;border-radius:12px;padding:14px">
              <div style="font-size:11px;font-weight:600;color:#546e7a;margin-bottom:6px"><?= $ico ?> <?= $label ?></div>
              <div style="font-size:20px;font-weight:800;color:<?= $numCol ?>;line-height:1;margin-bottom:8px">
                <?= $usado ?><span style="font-size:12px;font-weight:500;color:#90a4ae"> / <?= $limTxt ?></span>
              </div>
              <div style="height:6px;background:rgba(0,0,0,.07);border-radius:4px">
                <div style="height:6px;width:<?= $pctBar ?>%;background:<?= $color ?>;border-radius:4px"></div>
              </div>
              <?php if (!$esInf && $pctBar >= 70): ?>
                <div style="font-size:10px;color:<?= $numCol ?>;margin-top:5px;font-weight:700">
                  <?= $pctBar >= 90 ? '⚠️ Límite alcanzado' : '⚡ Casi en el límite' ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- HISTORIAL VACANTES (span 2) -->
    <div class="card span2">
      <div class="ce-head">
        <div class="ce-tit">📋 Historial de vacantes</div>
        <a href="javascript:void(0)" onclick="abrirModalVacante()" class="ce-ver">Publicar nueva →</a>
      </div>
      <div class="hist-list" id="historialVacantes">
        <?php if (!empty($historialVacantes)): ?>
          <?php foreach ($historialVacantes as $v): ?>
            <div class="hist-item" id="vac-<?= $v['id'] ?>" style="padding:8px 12px;gap:8px">
              <div class="hist-dot <?= $v['activo'] ? 'dot-activo' : 'dot-inactivo' ?>" style="flex-shrink:0"></div>
              <div class="hist-info" style="min-width:0;flex:1">
                <div class="hist-nom" style="font-size:12px;font-weight:700"><?= htmlspecialchars($v['titulo']) ?></div>
                <div class="hist-meta" style="font-size:10px">
                  <?= htmlspecialchars($v['modalidad'] ?? '') ?> ·
                  <?= $v['activo'] ? '<span style="color:#22c55e;font-weight:700">Activa</span>' : '<span style="color:#f59e0b;font-weight:600">En revisión</span>' ?>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
                <div class="hist-fecha" style="font-size:10px"><?= date('d/m/y', strtotime($v['creado_en'])) ?></div>
                <button onclick="eliminarVacante(<?= $v['id'] ?>, this)" title="Eliminar vacante"
                  style="width:24px;height:24px;border-radius:6px;background:transparent;border:1px solid #fca5a5;color:#e74c3c;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;transition:.2s;padding:0"
                  onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='transparent'">🗑</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="text-align:center;padding:24px 16px;color:var(--ink3)">
            <div style="font-size:32px;margin-bottom:8px">💼</div>
            <div style="font-size:13px;font-weight:700;color:var(--ink2);margin-bottom:4px">Aún no has publicado vacantes</div>
            <div style="font-size:12px">Conecta con talentos del Chocó publicando tu primera oferta</div>
            <a href="javascript:void(0)" onclick="abrirModalVacante()" style="display:inline-block;margin-top:12px;padding:8px 18px;background:var(--azul);color:white;border-radius:10px;text-decoration:none;font-weight:800;font-size:12px">➕ Publicar primera vacante</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PERFIL EMPRESA (card lateral) -->
    <div class="card" style="display:flex;flex-direction:column">
      <div class="cp-head">
        <div class="cp-av" id="cpAvatar" onclick="abrirModal()" title="Cambiar logo">
          <?php if ($logoUrl): ?>
            <img src="<?= $logoUrl ?>" alt="Logo" style="width:100%;height:100%;object-fit:cover;border-radius:14px">
          <?php else: ?>
            <?= $iniciales ?>
          <?php endif; ?>
        </div>
        <div class="cp-nom" id="dNombreEmp"><?= $nombreEmpresa ?></div>
        <div class="cp-pro" id="dSector"><?= $sector ?: 'Sector no definido' ?></div>
      </div>
      <div class="cp-body">
        <div class="cp-fil"><span class="cp-ico">📍</span><span id="dCiudad"><?= $ciudad ?: 'Ciudad no registrada' ?></span></div>
        <div class="cp-fil"><span class="cp-ico">✉️</span><span><?= $correo ?></span></div>
        <?php if (!empty($ep['sitio_web'])): ?>
          <div class="cp-fil"><span class="cp-ico">🌐</span><a href="<?= htmlspecialchars($ep['sitio_web']) ?>" target="_blank" style="color:var(--azul);font-size:13px;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($ep['sitio_web']) ?></a></div>
        <?php endif; ?>
        <div class="cp-fil"><span class="cp-ico">📅</span><span><?= $fechaRegistro ?></span></div>
        <?php if (!empty($badgesHTML)): ?>
          <div style="margin-top:8px"><?= $badgesHTML ?></div>
        <?php endif; ?>
      </div>

      <!-- TOGGLE VISIBILIDAD -->
      <div class="vis-row" id="visRow">
        <div>
          <div class="vis-label">Aparecer en empresas activas</div>
          <div class="vis-sub">Tu empresa se mostrará en el directorio público</div>
        </div>
        <div class="toggle-wrap">
          <span id="visChip" class="pv-chip <?= $visibleEnWeb ? 'ok' : 'off' ?>"><?= $visibleEnWeb ? '🟢 Visible' : '🟡 Oculto' ?></span>
          <label class="toggle">
            <input type="checkbox" id="toggleVisible" <?= $visibleEnWeb ? 'checked' : '' ?> onchange="toggleVisibilidad(this.checked)">
            <span class="toggle-slider"></span>
          </label>
        </div>
      </div>

      <!-- PROGRESO -->
      <div class="prog-w">
        <div class="prog-h"><span>Perfil completado</span><span id="pctLabel"><?= $pct ?>%</span></div>
        <div class="prog-t"><div class="prog-f" id="progBar" style="width:0%"></div></div>
      </div>

      <button class="btn-edit" onclick="abrirModal()">✏️ Editar perfil empresa</button>
      <button class="btn-sec" onclick="location.href='empresas.php'">🌐 Ver en directorio</button>
    </div>

    <!-- ACTIVIDAD RECIENTE -->
    <div class="card span2">
      <div class="ca-tit">🕐 Actividad reciente</div>
      <div class="hist-list">
        <div class="hist-item">
          <div class="hist-dot dot-activo"></div>
          <div class="hist-info">
            <div class="hist-nom">🎉 Cuenta empresarial creada</div>
            <div class="hist-meta">Bienvenida a QuibdóConecta</div>
          </div>
          <div class="hist-fecha"><?= $fechaRegistro ?></div>
        </div>
        <div class="hist-item">
          <div class="hist-dot" style="background:#3b82f6"></div>
          <div class="hist-info">
            <div class="hist-nom">👁️ Exploraste talentos locales</div>
            <div class="hist-meta">+500 talentos disponibles en la plataforma</div>
          </div>
          <div class="hist-fecha">Hoy</div>
        </div>
        <?php if ($tieneVerificado): ?>
          <div class="hist-item">
            <div class="hist-dot dot-activo"></div>
            <div class="hist-info">
              <div class="hist-nom">✅ Empresa Verificada</div>
              <div class="hist-meta">Badge asignado por el administrador</div>
            </div>
            <div class="hist-fecha">—</div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- BADGES ACTIVOS -->
    <div class="card">
      <div class="ca-tit">🏆 Badges activos</div>
      <div class="badge-row">
        <?php if ($tieneTop): ?><span class="bdg bdg-t">👑 Top</span><?php endif; ?>
        <?php if ($tienePremium): ?><span class="bdg bdg-p">⭐ Premium</span><?php endif; ?>
        <?php if ($tieneDestacado): ?><span class="bdg bdg-d">🏅 Destacada</span><?php endif; ?>
        <?php if ($tieneVerificado): ?><span class="bdg bdg-v">✓ Verificada</span><?php endif; ?>
        <?php if (!$tieneVerificado && !$tienePremium && !$tieneDestacado && !$tieneTop): ?>
          <div style="font-size:13px;color:var(--ink3);padding:8px 0">
            Aún no tienes badges. <a href="verificar_cuenta.php" style="color:var(--azul);font-weight:700">Verifica tu empresa →</a>
          </div>
        <?php endif; ?>
      </div>
      <div style="margin-top:16px;padding:14px;background:var(--azul-claro);border-radius:12px;border:1px solid #bfdbfe">
        <div style="font-size:12px;font-weight:800;color:var(--azul);margin-bottom:6px">¿Quieres más visibilidad?</div>
        <div style="font-size:12px;color:#1e40af;line-height:1.5">Para adquirir Los badges de <strong>Pago</strong> y tener mas visibilidad debes comprar un plan apartir de <strong>Verde Selva</strong>.</div>
      </div>
    </div>

  </div><!-- /grid -->

</div><!-- /contenido -->

<!-- ── MODAL CROP BANNER EMPRESA ── -->
<div style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:99999;align-items:center;justify-content:center;padding:20px" id="cropBannerModal">
  <div style="background:#fff;border-radius:20px;padding:26px;max-width:700px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.5)">
    <div style="font-size:16px;font-weight:800;color:#1a56db;margin-bottom:14px;text-align:center">🖼️ Encuadra el banner de tu empresa</div>
    <div style="position:relative;width:100%;height:220px;overflow:hidden;border-radius:12px;background:#000;display:flex;align-items:center;justify-content:center">
      <img id="cropBannerImg" style="max-width:100%;display:block">
    </div>
    <p style="font-size:12px;color:#64748b;text-align:center;margin:10px 0">Arrastra y haz zoom para encuadrar · Proporción 4:1</p>
    <div style="display:flex;gap:10px;margin-top:6px">
      <button onclick="cancelarCropBanner()" style="flex:1;padding:11px;border-radius:10px;border:1px solid #e0e0e0;background:#f5f5f5;font-size:13px;font-weight:700;cursor:pointer;color:#546e7a">Cancelar</button>
      <button onclick="confirmarCropBanner()" id="btnConfirmarCropBanner" style="flex:2;padding:11px;border-radius:10px;border:none;background:linear-gradient(135deg,#1a56db,#3b82f6);color:#fff;font-size:13px;font-weight:800;cursor:pointer">✅ Usar este banner</button>
    </div>
  </div>
</div>

<!-- ── MODAL EDITAR EMPRESA ── -->
<div class="modal-ov" id="modalEditar">
  <div class="modal-box">
    <button class="mcerrar" onclick="cerrarModal()">✕</button>
    <div class="modal-pad">
      <div class="mtit">✏️ Editar perfil de empresa</div>
      <p class="msub">Administra tus vacantes, revisa candidatos y gestiona tus procesos desde un solo lugar.</p>
      <div class="mmsg" id="editMsg"></div>

      <!-- LOGO -->
      <div class="msec">Logo de la empresa</div>
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px">
        <div id="logoPreview" style="width:72px;height:72px;border-radius:14px;background:linear-gradient(135deg,var(--azul),var(--azul2));display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;color:white;overflow:hidden;flex-shrink:0;cursor:pointer;border:3px solid rgba(26,86,219,.2)" onclick="document.getElementById('logoInput').click()">
          <?php if ($logoUrl): ?>
            <img src="<?= $logoUrl ?>" id="logoImgPreview" style="width:100%;height:100%;object-fit:cover;border-radius:14px">
          <?php else: ?>
            <span><?= $iniciales ?></span>
          <?php endif; ?>
        </div>
        <div>
          <input type="file" id="logoInput" accept="image/jpeg,image/png,image/webp,image/svg+xml" style="display:none" onchange="subirLogo(this)">
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <button onclick="document.getElementById('logoInput').click()" style="padding:8px 14px;border-radius:8px;background:var(--azul);color:white;border:none;font-size:13px;font-weight:700;cursor:pointer">🖼️ Cambiar logo</button>
            <button id="btnEliminarLogo" onclick="eliminarLogo()" style="padding:8px 14px;border-radius:8px;background:transparent;color:#e74c3c;border:1.5px solid #e74c3c;font-size:13px;font-weight:700;cursor:pointer;<?= $logoUrl ? '' : 'display:none' ?>">🗑 Eliminar</button>
          </div>
          <div style="font-size:11px;color:var(--ink3);margin-top:5px">JPG, PNG, WEBP o SVG · máx 2 MB</div>
          <div id="logoMsg" style="font-size:12px;margin-top:4px"></div>
        </div>
      </div>

      <!-- DATOS EMPRESA -->
      <div class="msec">Datos de la empresa</div>
      <div class="mfila">
        <div class="mgr full">
          <label>Nombre de la empresa *</label>
          <input type="text" id="editNombreEmp" value="<?= htmlspecialchars($ep['nombre_empresa'] ?: $usuario['nombre'] ?? '') ?>" placeholder="Ej: Tech Chocó S.A.S.">
        </div>
      </div>
      <div class="mfila">
        <div class="mgr">
          <label>Sector / Industria</label>
          <select id="editSector">
            <option value="">Selecciona un sector</option>
            <?php
            $sectores = [
              'Tecnología','Salud','Educación','Construcción & Inmobiliaria',
              'Comercio & Retail','Servicios & Turismo','Finanzas & Banca',
              'Agro & Medio Ambiente','Minería','Transporte & Logística',
              'Gastronomía','Arte & Cultura','Otro'
            ];
            foreach ($sectores as $s):
              $sel = ($ep['sector'] === $s) ? 'selected' : '';
            ?>
              <option value="<?= $s ?>" <?= $sel ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mgr">
          <label>NIT / Identificación</label>
          <input type="text" id="editNit" value="<?= htmlspecialchars($ep['nit'] ?? '') ?>" placeholder="900.123.456-7">
        </div>
      </div>
      <div class="mfila">
        <div class="mgr">
          <label>Ciudad principal</label>
          <input type="text" id="editCiudad" value="<?= htmlspecialchars($usuario['ciudad'] ?? '') ?>" placeholder="Quibdó">
        </div>
        <div class="mgr">
          <label>Municipio del Chocó</label>
          <input type="text" id="editMunicipio" value="<?= htmlspecialchars($ep['municipio'] ?? '') ?>" placeholder="Ej: Istmina, Condoto…">
        </div>
      </div>
      <div class="mfila">
        <div class="mgr">
          <label>Teléfono empresa</label>
          <input type="tel" id="editTelefonoEmp" value="<?= htmlspecialchars($ep['telefono_empresa'] ?? '') ?>" placeholder="(604) 123 4567">
        </div>
        <div class="mgr">
          <label>Sitio web</label>
          <input type="url" id="editSitioWeb" value="<?= htmlspecialchars($ep['sitio_web'] ?? '') ?>" placeholder="https://miempresa.com">
        </div>
      </div>
      <div class="mfila">
        <div class="mgr full">
          <label>Descripción de la empresa</label>
          <textarea id="editDescripcion" rows="4" placeholder="Cuéntanos qué hace tu empresa, su misión y por qué los mejores talentos del Chocó deberían trabajar con ustedes…"><?= htmlspecialchars($ep['descripcion'] ?? '') ?></textarea>
        </div>
      </div>

      <button class="btn-save" id="btnGuardar" onclick="guardarEmpresa()">💾 Guardar cambios</button>

      <!-- ── ZONA DE PELIGRO ── -->
      <div style="margin-top:32px;padding-top:24px;border-top:1px solid rgba(239,68,68,.2);">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
          <span style="font-size:15px">⚠️</span>
          <span style="font-size:13px;font-weight:700;color:#f87171;text-transform:uppercase;letter-spacing:.8px">Zona de peligro</span>
        </div>
        <p style="font-size:13px;color:var(--ink3);margin-bottom:14px;line-height:1.5;">
          Eliminar tu cuenta es <strong style="color:#f87171">permanente e irreversible</strong>. Se borrarán la empresa, vacantes publicadas, mensajes y todos los datos asociados.
        </p>
        <button onclick="abrirEliminarCuenta()"
          style="padding:10px 20px;border-radius:10px;background:transparent;border:1.5px solid #e74c3c;color:#e74c3c;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;"
          onmouseover="this.style.background='rgba(231,76,60,.1)'" onmouseout="this.style.background='transparent'">
          🗑 Eliminar mi cuenta de empresa
        </button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL ELIMINAR CUENTA EMPRESA -->
<div id="modalEliminarCuenta" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#0f172a;border:1.5px solid rgba(239,68,68,.35);border-radius:20px;padding:36px 32px;max-width:440px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.6);">
    <div style="text-align:center;margin-bottom:22px;">
      <div style="font-size:48px;margin-bottom:12px;">⚠️</div>
      <h3 style="font-size:20px;font-weight:800;color:#f87171;margin-bottom:8px;">Eliminar cuenta permanentemente</h3>
      <p style="font-size:14px;color:rgba(255,255,255,.6);line-height:1.6;">Esta acción no se puede deshacer. Se eliminarán la empresa, todas las vacantes, mensajes y datos asociados.</p>
    </div>
    <div style="margin-bottom:20px;">
      <label style="display:block;font-size:13px;font-weight:600;color:rgba(255,255,255,.7);margin-bottom:8px;">Para confirmar, escribe tu correo: <strong style="color:#f87171"><?= htmlspecialchars($usuario['correo']) ?></strong></label>
      <input type="text" id="inputConfirmarCuenta" placeholder="Escribe tu correo exacto"
        style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid rgba(239,68,68,.4);background:rgba(239,68,68,.06);color:white;font-size:14px;outline:none;font-family:inherit;">
    </div>
    <div id="msgEliminarCuenta" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
    <div style="display:flex;gap:10px;">
      <button onclick="cerrarEliminarCuenta()" style="flex:1;padding:12px;border-radius:10px;border:1.5px solid rgba(255,255,255,.15);background:transparent;color:rgba(255,255,255,.7);font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">Cancelar</button>
      <button id="btnConfirmarEliminar" onclick="confirmarEliminarCuenta()" style="flex:1;padding:12px;border-radius:10px;border:none;background:#e74c3c;color:white;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;">🗑 Sí, eliminar cuenta</button>
    </div>
  </div>
</div>

<script>
  
  function abrirModal() { document.getElementById('modalEditar').classList.add('open') }
  function cerrarModal() { document.getElementById('modalEditar').classList.remove('open') }
  document.getElementById('modalEditar').addEventListener('click', e => {
    if (e.target === document.getElementById('modalEditar')) cerrarModal()
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') { cerrarModal(); cerrarEliminarCuenta(); } });

  function abrirEliminarCuenta() {
    const m = document.getElementById('modalEliminarCuenta');
    m.style.display = 'flex';
    document.getElementById('inputConfirmarCuenta').value = '';
    document.getElementById('msgEliminarCuenta').style.display = 'none';
    document.getElementById('btnConfirmarEliminar').disabled = false;
    document.getElementById('btnConfirmarEliminar').textContent = '🗑 Sí, eliminar cuenta';
  }
  function cerrarEliminarCuenta() {
    document.getElementById('modalEliminarCuenta').style.display = 'none';
  }
  async function confirmarEliminarCuenta() {
    const correo = document.getElementById('inputConfirmarCuenta').value.trim();
    const msg    = document.getElementById('msgEliminarCuenta');
    const btn    = document.getElementById('btnConfirmarEliminar');
    const mostrarMsg = (texto, ok) => {
      msg.textContent  = texto;
      msg.style.cssText = `display:block;padding:10px 14px;border-radius:8px;font-size:13px;background:${ok ? 'rgba(31,157,85,.15)' : 'rgba(239,68,68,.15)'};color:${ok ? '#a7f3d0' : '#f87171'};`;
    };
    if (!correo) { mostrarMsg('Escribe tu correo para confirmar.', false); return; }
    btn.disabled = true; btn.textContent = '⏳ Eliminando...';
    const fd = new FormData();
    fd.append('_action', 'eliminar_cuenta');
    fd.append('confirmar', correo);
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        mostrarMsg('✅ Cuenta eliminada. Redirigiendo...', true);
        setTimeout(() => { window.location.href = 'index.html'; }, 2000);
      } else {
        mostrarMsg('❌ ' + (j.msg || 'Error al eliminar.'), false);
        btn.disabled = false; btn.textContent = '🗑 Sí, eliminar cuenta';
      }
    } catch(e) {
      mostrarMsg('❌ Error de conexión.', false);
      btn.disabled = false; btn.textContent = '🗑 Sí, eliminar cuenta';
    }
  }
  document.getElementById('modalEliminarCuenta').addEventListener('click', e => {
    if (e.target === document.getElementById('modalEliminarCuenta')) cerrarEliminarCuenta();
  });

  window.addEventListener('load', () => {
    const b = document.getElementById('progBar');
    if (b) setTimeout(() => { b.style.width = '<?= $pct ?>%' }, 400);
  });

  function mostrarMsg(t, c) {
    const e = document.getElementById('editMsg');
    e.textContent = t; e.className = 'mmsg ' + c; e.style.display = 'block';
  }

  async function eliminarLogo() {
    if (!confirm('¿Eliminar el logo de la empresa?')) return;
    const msg = document.getElementById('logoMsg');
    msg.textContent = '⏳ Eliminando…'; msg.style.color = 'var(--ink3)';
    const fd = new FormData();
    fd.append('_action', 'eliminar_logo');
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        msg.textContent = '✅ Logo eliminado'; msg.style.color = 'var(--verde2)';
        const iniciales = `<span><?= $iniciales ?></span>`;
        ['logoPreview','heroAvatar','cpAvatar','navAvatar'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.innerHTML = iniciales;
        });
        document.getElementById('btnEliminarLogo').style.display = 'none';
        setTimeout(() => { msg.textContent = ''; }, 2000);
      } else {
        msg.textContent = '❌ Error al eliminar'; msg.style.color = '#e74c3c';
      }
    } catch(e) { msg.textContent = '❌ Error de conexión'; msg.style.color = '#e74c3c'; }
  }

  async function eliminarLogoBanner() {
    if (!confirm('¿Eliminar el logo de la empresa?')) return;
    const fd = new FormData();
    fd.append('_action', 'eliminar_logo');
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        const btn = document.getElementById('btnEliminarLogoBanner');
        if (btn) btn.style.display = 'none';
        location.reload();
      } else {
        alert('❌ Error al eliminar el logo');
      }
    } catch(e) { alert('Error de conexión'); }
  }

  async function subirLogo(input) {
    const file = input.files[0];
    if (!file) return;
    const msg = document.getElementById('logoMsg');
    msg.textContent = '⏳ Subiendo logo…'; msg.style.color = 'var(--ink3)';
    const fd = new FormData();
    fd.append('_action', 'subir_logo');
    fd.append('logo', file);
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        msg.textContent = '✅ Logo actualizado'; msg.style.color = 'var(--verde2)';
        const imgTag = `<img src="${j.logo}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:14px">`;
        ['logoPreview','heroAvatar','cpAvatar','navAvatar'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.innerHTML = imgTag;
        });
        const btnEl = document.getElementById('btnEliminarLogo');
        if (btnEl) btnEl.style.display = 'inline-block';
      } else {
        msg.textContent = '❌ ' + (j.msg || 'Error'); msg.style.color = '#e74c3c';
      }
    } catch(e) { msg.textContent = '❌ Error de conexión'; msg.style.color = '#e74c3c'; }
    input.value = '';
  }

  async function guardarEmpresa() {
    const btn = document.getElementById('btnGuardar');
    const n = document.getElementById('editNombreEmp').value.trim();
    if (!n) { mostrarMsg('El nombre de la empresa es obligatorio.', 'error'); return; }
    btn.disabled = true; btn.textContent = '⏳ Guardando…';
    const fd = new FormData();
    fd.append('_action',          'editar_empresa');
    fd.append('nombre_empresa',   n);
    fd.append('sector',           document.getElementById('editSector').value);
    fd.append('nit',              document.getElementById('editNit').value.trim());
    fd.append('descripcion',      document.getElementById('editDescripcion').value.trim());
    fd.append('sitio_web',        document.getElementById('editSitioWeb').value.trim());
    fd.append('telefono_empresa', document.getElementById('editTelefonoEmp').value.trim());
    fd.append('ciudad',           document.getElementById('editCiudad').value.trim());
    fd.append('municipio',        document.getElementById('editMunicipio').value.trim());
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        mostrarMsg('¡Perfil actualizado correctamente!', 'success');
        document.getElementById('dNombreEmp').textContent = j.nombre_empresa;
        const ds = document.getElementById('dSector');
        if (ds) ds.textContent = j.sector || 'Sector no definido';
        const dc = document.getElementById('dCiudad');
        if (dc) dc.textContent = j.ciudad || 'Ciudad no registrada';
        setTimeout(cerrarModal, 1600);
      } else {
        mostrarMsg(j.msg || 'Error al guardar.', 'error');
      }
    } catch(e) { mostrarMsg('Error de conexión.', 'error'); }
    btn.disabled = false; btn.textContent = '💾 Guardar cambios';
  }

  async function toggleVisibilidad(visible) {
    const chip = document.getElementById('visChip');
    const fd = new FormData();
    fd.append('_action', 'toggle_visibilidad');
    fd.append('visible', visible ? '1' : '0');
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        chip.textContent = visible ? '🟢 Visible' : '🟡 Oculto';
        chip.className = 'pv-chip ' + (visible ? 'ok' : 'off');
      }
    } catch(e) { console.error(e); }
  }

  function abrirModalVacante() {
    document.getElementById('modal-vacante').classList.add('open');
    document.getElementById('vacante-msg').style.display = 'none';
    document.getElementById('form-vacante').reset();
  }
  function cerrarModalVacante() {
    document.getElementById('modal-vacante').classList.remove('open');
  }
  document.getElementById('modal-vacante') && document.getElementById('modal-vacante').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-vacante')) cerrarModalVacante();
  });

  async function enviarVacante() {
    const btn = document.getElementById('btn-vacante-enviar');
    const msg = document.getElementById('vacante-msg');
    const fd  = new FormData(document.getElementById('form-vacante'));
    fd.append('_action', 'publicar_vacante');
    btn.disabled = true; btn.textContent = 'Enviando…';
    msg.style.display = 'none';
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const d = await r.json();
      msg.style.display = 'block';
      if (d.ok) {
        msg.style.cssText = 'display:block;color:#15803d;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:12px';
        msg.textContent = d.msg;
        setTimeout(() => { cerrarModalVacante(); location.reload(); }, 2200);
      } else {
        msg.style.cssText = 'display:block;color:#dc2626;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:12px';
        msg.textContent = '❌ ' + (d.msg || 'Error al enviar');
        btn.disabled = false; btn.textContent = '💼 Publicar vacante';
      }
    } catch(e) {
      msg.style.display = 'block';
      msg.style.cssText = 'display:block;color:#dc2626;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:12px';
      msg.textContent = '❌ Error de conexión';
      btn.disabled = false; btn.textContent = '💼 Publicar vacante';
    }
  }


  async function eliminarVacante(id, btn) {
    if (!confirm('¿Eliminar esta vacante? Esta acción no se puede deshacer.')) return;
    const originalHtml = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '⏳';
    const fd = new FormData();
    fd.append('_action', 'eliminar_vacante');
    fd.append('vacante_id', id);
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        const row = document.getElementById('vac-' + id);
        if (row) { row.style.opacity='0'; row.style.transform='translateX(20px)'; row.style.transition='.3s'; setTimeout(()=>row.remove(),320); }
      } else {
        alert('❌ ' + (j.msg || 'Error al eliminar'));
        btn.disabled = false; btn.innerHTML = originalHtml;
      }
    } catch(e) {
      alert('❌ Error de conexión');
      btn.disabled = false; btn.innerHTML = originalHtml;
    }
  }



  function subirBanner(input) {
    const file = input.files[0];
    if (!file) return;
    input.value = '';
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('cropBannerImg');
      if (cropperBannerInstance) { cropperBannerInstance.destroy(); cropperBannerInstance = null; }
      img.src = e.target.result;
      document.getElementById('cropBannerModal').style.display = 'flex';
      img.onload = () => {
        cropperBannerInstance = new Cropper(img, {
          aspectRatio: 4,
          viewMode: 1,
          dragMode: 'move',
          autoCropArea: 1,
          cropBoxResizable: false,
          cropBoxMovable: false,
          toggleDragModeOnDblclick: false,
          background: false,
          responsive: true
        });
      };
    };
    reader.readAsDataURL(file);
  }

  function cancelarCropBanner() {
    document.getElementById('cropBannerModal').style.display = 'none';
    if (cropperBannerInstance) { cropperBannerInstance.destroy(); cropperBannerInstance = null; }
  }

  async function confirmarCropBanner() {
    if (!cropperBannerInstance) return;
    const btn = document.getElementById('btnConfirmarCropBanner');
    btn.textContent = '⏳ Guardando…'; btn.disabled = true;
    const msg = document.getElementById('bannerMsg');

    const canvas = cropperBannerInstance.getCroppedCanvas({ width: 1200, height: 300, imageSmoothingQuality: 'high' });
    const dataUrl = canvas.toDataURL('image/jpeg', .92);

    const zone = document.getElementById('bannerZone');
    let img = document.getElementById('bannerImg');
    const ph = document.getElementById('bannerPlaceholder');
    if (ph) ph.style.display = 'none';
    if (!img) {
      img = document.createElement('img');
      img.id = 'bannerImg';
      img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;position:absolute;inset:0';
      zone.insertBefore(img, zone.firstChild);
    }
    img.src = dataUrl;

    document.getElementById('cropBannerModal').style.display = 'none';
    if (cropperBannerInstance) { cropperBannerInstance.destroy(); cropperBannerInstance = null; }

    canvas.toBlob(async blob => {
      const fd = new FormData();
      fd.append('_action', 'subir_banner');
      fd.append('banner', new File([blob], 'banner.jpg', { type: 'image/jpeg' }));
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (!j.ok) {
          msg.textContent = '❌ ' + (j.msg || 'Error al subir banner');
          msg.style.display = 'block';
        } else {
          msg.style.display = 'none';
          if (!zone.querySelector('.btn-quitar-banner')) {
            const qbtn = document.createElement('button');
            qbtn.className = 'btn-quitar-banner';
            qbtn.textContent = '🗑 Quitar';
            qbtn.style.cssText = 'position:absolute;top:10px;right:10px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:20px;padding:5px 12px;font-size:11px;font-weight:700;cursor:pointer;z-index:5';
            qbtn.onclick = (ev) => { ev.stopPropagation(); eliminarBanner(); };
            zone.appendChild(qbtn);
          }
        }
      } catch(e) {
        msg.textContent = '❌ Error de conexión.';
        msg.style.display = 'block';
      }
      btn.textContent = '✅ Usar este banner'; btn.disabled = false;
    }, 'image/jpeg', .92);
  }

  async function eliminarBanner() {
    if (!confirm('¿Quitar el banner?')) return;
    const fd = new FormData();
    fd.append('_action', 'eliminar_banner');
    try {
      const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd });
      const j = await r.json();
      if (j.ok) {
        const img = document.getElementById('bannerImg');
        if (img) img.remove();
        const zone = document.getElementById('bannerZone');
        let ph = document.getElementById('bannerPlaceholder');
        if (!ph) {
          ph = document.createElement('div');
          ph.id = 'bannerPlaceholder';
          ph.style.cssText = 'width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:rgba(255,255,255,.6)';
          ph.innerHTML = '<div style="font-size:36px">🖼️</div><div style="font-size:13px;font-weight:600">Haz clic para subir el banner de tu empresa</div>';
          zone.insertBefore(ph, zone.firstChild);
        } else { ph.style.display = 'flex'; }
        zone.querySelectorAll('.btn-quitar-banner,button').forEach(b => b.remove());
      }
    } catch(e) {}
  }

</script>

<!-- ── MODAL PUBLICAR VACANTE ───────────────────────────────── -->
<div class="modal-ov" id="modal-vacante">
  <div class="modal-box" style="max-width:600px">
    <button class="mcerrar" onclick="cerrarModalVacante()">✕</button>
    <div class="modal-pad">
      <div class="mtit">💼 Publicar nueva vacante</div>
      <p class="msub">Tu oferta será revisada por el administrador antes de aparecer en el sitio (24–48 h).</p>
      <div id="vacante-msg" style="display:none;margin-bottom:12px"></div>
      <form id="form-vacante">
        <div class="mfila">
          <div class="mgr full">
            <label>Título del cargo *</label>
            <input name="titulo" placeholder="ej: Asistente contable, Operario de planta…" required>
          </div>
        </div>
        <div class="mfila">
          <div class="mgr">
            <label>Tipo de contrato *</label>
            <select name="tipo" required>
              <option value="">Selecciona…</option>
              <option>Tiempo completo</option>
              <option>Medio tiempo</option>
              <option>Por obra o labor</option>
              <option>Temporal</option>
              <option>Prácticas / Pasantía</option>
            </select>
          </div>
          <div class="mgr">
            <label>Categoría</label>
            <select name="categoria">
              <option value="tecnologia">Tecnología</option>
              <option value="salud">Salud</option>
              <option value="educacion">Educación</option>
              <option value="comercio">Comercio</option>
              <option value="construccion">Construcción</option>
              <option value="servicios" selected>Servicios</option>
              <option value="finanzas">Finanzas</option>
              <option value="agro">Agro / Ambiente</option>
              <option value="otro">Otro</option>
            </select>
          </div>
        </div>
        <div class="mfila">
          <div class="mgr">
            <label>Modalidad</label>
            <select name="modalidad">
              <option>Presencial</option>
              <option>Remoto</option>
              <option>Mixta</option>
            </select>
          </div>
          <div class="mgr">
            <label>Salario</label>
            <input name="salario" placeholder="ej: $1.500.000 o A convenir">
          </div>
        </div>
        <div class="mfila">
          <div class="mgr">
            <label>Ubicación</label>
            <input name="ubicacion" value="Quibdó, Chocó">
          </div>
          <div class="mgr">
            <label>Fecha límite de postulación</label>
            <input name="vence_en" type="date">
          </div>
        </div>
        <div class="mfila">
          <div class="mgr full">
            <label>Descripción del cargo *</label>
            <textarea name="descripcion" rows="3" placeholder="¿Qué hará esta persona en el día a día? ¿Qué ofrece tu empresa?" required></textarea>
          </div>
        </div>
        <div class="mfila">
          <div class="mgr full">
            <label>Requisitos y cómo postularse *</label>
            <textarea name="requisitos" rows="3" placeholder="ej: Experiencia mínima 1 año · Enviar hoja de vida a rrhh@empresa.com" required></textarea>
          </div>
        </div>
        <button type="button" id="btn-vacante-enviar" onclick="enviarVacante()" class="btn-save">
          💼 Publicar vacante
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Widget de sesión activa — QuibdóConecta -->
<script src="js/sesion_widget.js"></script>
</body>
</html>