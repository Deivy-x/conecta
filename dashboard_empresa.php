<?php

session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once __DIR__ . '/Php/db.php';
if (file_exists(__DIR__ . '/Php/planes_helper.php'))
  require_once __DIR__ . '/Php/planes_helper.php';

if (!isset($_SESSION['usuario_id'])) {
  header('Location: inicio_sesion.php');
  exit;
}
$db = getDB();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
if (!$usuario || $usuario['tipo'] !== 'empresa') {
  header('Location: dashboard.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['_action'] ?? '';

  if ($action === 'editar_empresa') {
    $nombreEmp = trim($_POST['nombre_empresa'] ?? '');
    $sector = trim($_POST['sector'] ?? '');
    $nit = trim($_POST['nit'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $sitioWeb = trim($_POST['sitio_web'] ?? '');
    $telefonoEmp = trim($_POST['telefono_empresa'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');

    if (!$nombreEmp) {
      echo json_encode(['ok' => false, 'msg' => 'El nombre de la empresa es obligatorio.']);
      exit;
    }

    $db->prepare("UPDATE usuarios SET nombre=?, telefono=?, ciudad=? WHERE id=?")
      ->execute([$nombreEmp, $telefonoEmp, $ciudad, $usuario['id']]);

    $existeStmt = $db->prepare("SELECT id FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
    $existeStmt->execute([$usuario['id']]);
    $filaExistente = $existeStmt->fetchColumn();

    if ($filaExistente) {
      $db->prepare("UPDATE perfiles_empresa SET nombre_empresa=?,sector=?,nit=?,descripcion=?,sitio_web=?,telefono_empresa=?,municipio=?,actualizado_en=NOW() WHERE usuario_id=? ORDER BY id DESC LIMIT 1")
        ->execute([$nombreEmp, $sector, $nit, $descripcion, $sitioWeb, $telefonoEmp, $municipio, $usuario['id']]);
    } else {
      $db->prepare("INSERT INTO perfiles_empresa (usuario_id,nombre_empresa,sector,nit,descripcion,sitio_web,telefono_empresa,municipio) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$usuario['id'], $nombreEmp, $sector, $nit, $descripcion, $sitioWeb, $telefonoEmp, $municipio]);
    }
    echo json_encode(['ok' => true, 'nombre_empresa' => $nombreEmp, 'sector' => $sector, 'ciudad' => $ciudad]);
    exit;
  }

  if ($action === 'toggle_visibilidad') {
    $visible = (int) ($_POST['visible'] ?? 1);
    $db->prepare("UPDATE perfiles_empresa SET visible=? WHERE usuario_id=? ORDER BY id DESC LIMIT 1")->execute([$visible, $usuario['id']]);
    echo json_encode(['ok' => true, 'visible' => $visible]);
    exit;
  }

  if ($action === 'eliminar_logo') {
    $epOld = $db->prepare("SELECT logo FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
    $epOld->execute([$usuario['id']]);
    $logoViejo = $epOld->fetchColumn();
    if ($logoViejo && file_exists(__DIR__ . '/uploads/logos/' . $logoViejo))
      @unlink(__DIR__ . '/uploads/logos/' . $logoViejo);
    $db->prepare("UPDATE perfiles_empresa SET logo='' WHERE usuario_id=? ORDER BY id DESC LIMIT 1")->execute([$usuario['id']]);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'subir_logo') {
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== 0) {
      echo json_encode(['ok' => false, 'msg' => 'No se recibió ninguna imagen.']);
      exit;
    }
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
      echo json_encode(['ok' => false, 'msg' => 'Solo JPG, PNG, WEBP o SVG.']);
      exit;
    }
    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
      echo json_encode(['ok' => false, 'msg' => 'Máximo 2 MB.']);
      exit;
    }
    $dir = __DIR__ . '/uploads/logos/';
    if (!is_dir(__DIR__ . '/uploads/'))
      @mkdir(__DIR__ . '/uploads/', 0755, true);
    if (!is_dir($dir))
      @mkdir($dir, 0755, true);
    if (!is_dir($dir) || !is_writable($dir)) {
      echo json_encode(['ok' => false, 'msg' => 'Error: no se puede escribir en uploads/logos/.']);
      exit;
    }
    $epOld = $db->prepare("SELECT logo FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
    $epOld->execute([$usuario['id']]);
    $oldLogo = $epOld->fetchColumn();
    if ($oldLogo && !str_starts_with($oldLogo, 'http') && file_exists($dir . $oldLogo))
      @unlink($dir . $oldLogo);
    $nombre = 'emp' . $usuario['id'] . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $nombre)) {
      $db->prepare("UPDATE perfiles_empresa SET logo=? WHERE usuario_id=? ORDER BY id DESC LIMIT 1")->execute([$nombre, $usuario['id']]);
      echo json_encode(['ok' => true, 'logo' => 'uploads/logos/' . $nombre]);
    } else {
      echo json_encode(['ok' => false, 'msg' => 'Error al mover el archivo.']);
    }
    exit;
  }

  if ($action === 'subir_banner') {
    if (!isset($_FILES['banner']) || $_FILES['banner']['error'] !== 0) {
      echo json_encode(['ok' => false, 'msg' => 'No se recibió imagen.']);
      exit;
    }
    $ext = strtolower(pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
      echo json_encode(['ok' => false, 'msg' => 'Solo JPG, PNG o WEBP.']);
      exit;
    }
    if ($_FILES['banner']['size'] > 5 * 1024 * 1024) {
      echo json_encode(['ok' => false, 'msg' => 'Máximo 5 MB.']);
      exit;
    }
    try {
      $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto");
    } catch (Exception $e) {
    }
    require_once __DIR__ . '/Php/cloudinary_upload.php';
    $result = cloudinary_upload($_FILES['banner']['tmp_name'], 'quibdoconecta/banners');
    if (!$result['ok']) {
      echo json_encode(['ok' => false, 'msg' => $result['msg']]);
      exit;
    }
    $db->prepare("UPDATE usuarios SET banner=? WHERE id=?")->execute([$result['url'], $usuario['id']]);
    echo json_encode(['ok' => true, 'banner' => $result['url']]);
    exit;
  }

  if ($action === 'eliminar_banner') {
    try {
      $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto");
    } catch (Exception $e) {
    }
    $db->prepare("UPDATE usuarios SET banner='' WHERE id=?")->execute([$usuario['id']]);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'publicar_vacante') {
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo = trim($_POST['tipo'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? 'Quibdó, Chocó');
    $salario = trim($_POST['salario'] ?? '');
    $modalidad = trim($_POST['modalidad'] ?? 'Presencial');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $requisitos = trim($_POST['requisitos'] ?? '');
    $vence_en = trim($_POST['vence_en'] ?? '') ?: null;

    if (!$titulo || !$tipo || !$descripcion || !$requisitos) {
      echo json_encode(['ok' => false, 'msg' => 'Faltan campos obligatorios.']);
      exit;
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
      $db->prepare("INSERT INTO empleos (empresa_id,titulo,descripcion,categoria,barrio,ciudad,modalidad,tipo,activo,vence_en,creado_en) VALUES (?,?,?,?,'',?,?,'privado',0,?,NOW())")
        ->execute([$usuario['id'], $titulo, $descripcion . "\n\nRequisitos:\n" . $requisitos . ($salario ? "\n\nSalario: " . $salario : "") . "\nModalidad de contrato: " . $tipo, $categoria, $ubicacion, $modalidad, $vence_en]);
      if (function_exists('registrarAccion'))
        registrarAccion($db, $usuario['id'], 'vacantes');
      echo json_encode(['ok' => true, 'msg' => '✅ Vacante enviada. El administrador la aprobará en 24–48 h.']);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()]);
    }
    exit;
  }

  if ($action === 'eliminar_vacante') {
    $vacanteId = (int) ($_POST['vacante_id'] ?? 0);
    if (!$vacanteId) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
      exit;
    }
    $chk = $db->prepare("SELECT id FROM empleos WHERE id=? AND empresa_id=?");
    $chk->execute([$vacanteId, $usuario['id']]);
    if (!$chk->fetch()) {
      echo json_encode(['ok' => false, 'msg' => 'No autorizado.']);
      exit;
    }
    $db->prepare("DELETE FROM empleos WHERE id=? AND empresa_id=?")->execute([$vacanteId, $usuario['id']]);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'eliminar_cuenta') {
    $confirmar = trim($_POST['confirmar'] ?? '');
    if ($confirmar !== $usuario['correo']) {
      echo json_encode(['ok' => false, 'msg' => 'El correo no coincide.']);
      exit;
    }
    try {
      $logoViejo = $ep['logo'] ?? '';
      if ($logoViejo && file_exists(__DIR__ . '/uploads/logos/' . $logoViejo))
        @unlink(__DIR__ . '/uploads/logos/' . $logoViejo);
      foreach (['perfiles_empresa', 'sesiones', 'negocios_locales'] as $tabla) {
        try {
          $db->prepare("DELETE FROM $tabla WHERE usuario_id=?")->execute([$usuario['id']]);
        } catch (Exception $e) {
        }
      }
      $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$usuario['id']]);
      $_SESSION = [];
      session_destroy();
      echo json_encode(['ok' => true]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }

  echo json_encode(['ok' => false, 'msg' => 'Acción desconocida.']);
  exit;
}

if (isset($_GET['salir'])) {
  if (isset($_COOKIE['qc_remember'])) {
    try {
      $db->prepare("DELETE FROM sesiones WHERE token=?")->execute([$_COOKIE['qc_remember']]);
    } catch (Exception $e) {
    }
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
if (!$ep) {
  $ep = ['nombre_empresa' => $usuario['nombre'] ?? '', 'sector' => '', 'nit' => '', 'descripcion' => '', 'logo' => '', 'sitio_web' => '', 'telefono_empresa' => '', 'municipio' => '', 'visible' => 1, 'visible_admin' => 1, 'destacado' => 0];
}

require_once __DIR__ . '/Php/badges_helper.php';
$badgesUsuario = getBadgesUsuario($db, $usuario['id']);
$badgesHTML = renderBadges($badgesUsuario);

$datosPlan = function_exists('getDatosPlan') ? getDatosPlan($db, $usuario['id']) : [];
$planActual = $datosPlan['plan'] ?? 'semilla';
$tieneVerificado = (bool) ($usuario['verificado'] ?? false) || tieneBadge($badgesUsuario, 'Verificado') || tieneBadge($badgesUsuario, 'Empresa Verificada');
$tienePremium = tieneBadge($badgesUsuario, 'Premium');
$tieneDestacado = tieneBadge($badgesUsuario, 'Destacado') || (int) ($ep['destacado'] ?? 0);
$tieneTop = tieneBadge($badgesUsuario, 'Top');

$nrChat = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario=? AND leido=0");
$nrChat->execute([$usuario['id']]);
$chatNoLeidos = (int) $nrChat->fetchColumn();

$stmtV = $db->prepare("SELECT estado,nota_rechazo FROM verificaciones WHERE usuario_id=? ORDER BY creado_en DESC LIMIT 1");
$stmtV->execute([$usuario['id']]);
$verifDoc = $stmtV->fetch();
$estadoVerif = $verifDoc ? $verifDoc['estado'] : null;
$notaRechazo = $verifDoc ? ($verifDoc['nota_rechazo'] ?? '') : '';

$vacantesActivas = 0;
try {
  $vStmt = $db->prepare("SELECT COUNT(*) FROM empleos WHERE empresa_id=? AND activo=1");
  $vStmt->execute([$usuario['id']]);
  $vacantesActivas = (int) $vStmt->fetchColumn();
} catch (Exception $e) {
}

$historialVacantes = [];
try {
  $hvStmt = $db->prepare("SELECT id,titulo,ciudad,modalidad,activo,creado_en FROM empleos WHERE empresa_id=? ORDER BY creado_en DESC LIMIT 10");
  $hvStmt->execute([$usuario['id']]);
  $historialVacantes = $hvStmt->fetchAll();
} catch (Exception $e) {
}

$campos = ['nombre_empresa', 'sector', 'descripcion', 'sitio_web', 'telefono_empresa', 'municipio'];
$llenos = array_filter($campos, fn($c) => !empty($ep[$c]));
$pct = (int) (count($llenos) / count($campos) * 100);
if (!empty($ep['logo']))
  $pct = min(100, $pct + 5);
if ($tieneVerificado)
  $pct = min(100, $pct + 5);

$nombreEmpresa = htmlspecialchars(trim($ep['nombre_empresa'] ?: $usuario['nombre'] ?? 'Mi Empresa'));
$iniciales = strtoupper(mb_substr($nombreEmpresa, 0, 2));
$sector = htmlspecialchars($ep['sector'] ?? '');
$ciudad = htmlspecialchars($usuario['ciudad'] ?? '');
$logoUrl = !empty($ep['logo']) ? (str_starts_with($ep['logo'], 'http') ? htmlspecialchars($ep['logo']) : 'uploads/logos/' . htmlspecialchars($ep['logo'])) : '';

try {
  $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto");
} catch (Exception $e) {
}
$usuarioRe = $db->prepare("SELECT * FROM usuarios WHERE id=?");
$usuarioRe->execute([$usuario['id']]);
$usuarioRe = $usuarioRe->fetch();
$bannerUrl = !empty($usuarioRe['banner']) ? (str_starts_with($usuarioRe['banner'], 'http') ? htmlspecialchars($usuarioRe['banner']) : 'uploads/banners/' . htmlspecialchars($usuarioRe['banner'])) : '';
$fechaRegistro = date('d \de F Y', strtotime($usuario['creado_en']));
$correo = htmlspecialchars($usuario['correo']);
$visibleEnWeb = (int) ($ep['visible'] ?? 1) && (int) ($ep['visible_admin'] ?? 1);
?><!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Panel Empresa – QuibdóConecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root {
      /* ── Azul corporativo ── */
      --brand: #1a56db;
      --brand2: #3b82f6;
      --brand-light: #eff6ff;
      --brand-mid: #93c5fd;
      --accent: #f59e0b;
      --accent-light: #fffbeb;
      --danger: #e53935;
      --ink: #0f172a;
      --ink2: #1e3a5f;
      --ink3: #5a7aa8;
      --ink4: #93afc8;
      --surface: #ffffff;
      --surface2: #f0f5ff;
      --surface3: #dbeafe;
      --border: rgba(26, 86, 219, .1);
      --border2: rgba(26, 86, 219, .2);
      --shadow: 0 1px 3px rgba(0, 0, 0, .06), 0 4px 16px rgba(0, 0, 0, .05);
      --shadow2: 0 2px 8px rgba(0, 0, 0, .08), 0 8px 32px rgba(0, 0, 0, .07);
      --radius: 14px;
      --radius-sm: 8px;
      --radius-lg: 20px;
      --nav-w: 240px;
      --top-h: 60px;
      --font: 'Plus Jakarta Sans', 'DM Sans', system-ui, sans-serif;
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    html {
      font-size: 15px;
      scroll-behavior: smooth
    }

    body {
      font-family: var(--font);
      background: var(--surface2);
      color: var(--ink);
      min-height: 100vh;
      display: flex
    }

    a {
      text-decoration: none;
      color: inherit
    }

    /* ── BARRA BANDERA ── */
    .barra-bandera {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #1f9d55 33.3%, #d4a017 33.3% 66.6%, #1a3a6b 66.6%);
      z-index: 9999
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--nav-w);
      background: linear-gradient(180deg, #ffffff 0%, #f5f8ff 100%);
      border-right: 2px solid rgba(26, 86, 219, .1);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 4px;
      left: 0;
      bottom: 0;
      z-index: 150;
      transition: transform .3s ease;
      overflow-y: auto;
      overflow-x: hidden
    }

    .sidebar-logo {
      padding: 14px 18px 12px;
      border-bottom: 1px solid var(--border);
      display: none;
      align-items: center;
      gap: 10px
    }

    .sidebar-logo img {
      height: 32px
    }

    .sidebar-logo-txt {
      font-size: 13px;
      font-weight: 700;
      color: var(--brand);
      letter-spacing: -.2px;
      line-height: 1.2
    }

    .sidebar-logo-sub {
      font-size: 11px;
      color: var(--ink4);
      font-weight: 400
    }

    .sidebar-user {
      margin: 14px 14px 10px;
      background: var(--brand-light);
      border-radius: var(--radius);
      padding: 14px;
      display: flex;
      align-items: center;
      gap: 10px
    }

    .su-av {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: var(--brand);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
      flex-shrink: 0;
      overflow: hidden;
      border: 2px solid #fff
    }

    .su-av img {
      width: 100%;
      height: 100%;
      object-fit: cover
    }

    .su-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3
    }

    .su-role {
      font-size: 11px;
      color: var(--ink3)
    }

    .sidebar-nav {
      flex: 1;
      padding: 6px 10px;
      overflow-y: auto
    }

    .nav-section {
      margin-bottom: 4px
    }

    .nav-section-label {
      font-size: 10px;
      font-weight: 700;
      color: var(--ink4);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      padding: 10px 10px 4px
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      border-radius: 10px;
      font-size: 13.5px;
      font-weight: 500;
      color: var(--ink2);
      transition: all .18s;
      cursor: pointer;
      position: relative;
      text-decoration: none
    }

    .nav-item:hover {
      background: var(--surface2);
      color: var(--brand)
    }

    .nav-item.active {
      background: var(--brand-light);
      color: var(--brand);
      font-weight: 700
    }

    .nav-item .ni-ico {
      font-size: 15px;
      width: 20px;
      text-align: center;
      flex-shrink: 0
    }

    .nav-item .ni-badge {
      margin-left: auto;
      background: var(--danger);
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      padding: 2px 6px;
      border-radius: 20px;
      min-width: 18px;
      text-align: center
    }

    .sidebar-bottom {
      padding: 12px 14px;
      border-top: 1px solid var(--border)
    }

    .sidebar-plan {
      background: linear-gradient(135deg, #1a56db 0%, #3b82f6 60%, #1a3a6b 100%);
      border-radius: var(--radius);
      padding: 14px;
      color: #fff;
      margin-bottom: 10px
    }

    .sp-label {
      font-size: 10px;
      font-weight: 600;
      opacity: .75;
      text-transform: uppercase;
      letter-spacing: 1px
    }

    .sp-name {
      font-size: 15px;
      font-weight: 800;
      margin: 2px 0 8px
    }

    .sp-btn {
      display: block;
      text-align: center;
      background: rgba(255, 255, 255, .2);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, .3);
      border-radius: 8px;
      padding: 7px 12px;
      font-size: 12px;
      font-weight: 700;
      transition: background .2s
    }

    .sp-btn:hover {
      background: rgba(255, 255, 255, .32)
    }

    .nav-salir {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 9px 12px;
      border-radius: 10px;
      font-size: 13px;
      color: var(--ink3);
      transition: all .18s;
      text-decoration: none
    }

    .nav-salir:hover {
      background: #fef2f2;
      color: var(--danger)
    }

    /* ── TOPBAR ── */
    .topbar {
      height: var(--top-h);
      background: linear-gradient(135deg, #ffffff 60%, #f0f5ff 100%);
      border-bottom: 2px solid rgba(26, 86, 219, .12);
      position: fixed;
      top: 4px;
      left: var(--nav-w);
      right: 0;
      z-index: 250;
      display: flex;
      align-items: center;
      padding: 0 28px;
      gap: 16px;
      box-shadow: 0 2px 12px rgba(26, 86, 219, .07)
    }

    .topbar-logo {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
      text-decoration: none;
      cursor: pointer
    }

    .topbar-logo img {
      height: 34px
    }

    .topbar-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--ink);
      flex: 1
    }

    .topbar-title span {
      color: var(--brand)
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .tb-btn {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: var(--surface2);
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      cursor: pointer;
      position: relative;
      transition: all .18s
    }

    .tb-btn:hover {
      background: var(--brand-light);
      border-color: var(--brand-mid)
    }

    .tb-dot {
      position: absolute;
      top: 6px;
      right: 6px;
      width: 8px;
      height: 8px;
      background: var(--danger);
      border-radius: 50%;
      border: 2px solid var(--surface)
    }

    .tb-notif-panel {
      position: absolute;
      top: calc(100% + 8px);
      right: 0;
      width: 300px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow2);
      display: none;
      z-index: 300
    }

    .tb-notif-panel.open {
      display: block
    }

    .tnp-head {
      padding: 12px 16px;
      font-size: 13px;
      font-weight: 700;
      border-bottom: 1px solid var(--border)
    }

    .tnp-body {
      max-height: 280px;
      overflow-y: auto
    }

    .tnp-empty {
      padding: 20px;
      text-align: center;
      color: var(--ink4);
      font-size: 13px
    }

    .hamburger {
      display: none;
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: var(--surface2);
      border: 1px solid var(--border);
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 5px;
      cursor: pointer
    }

    .hamburger span {
      width: 18px;
      height: 2px;
      background: var(--ink2);
      border-radius: 2px;
      transition: all .2s
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 18px;
      border-radius: var(--radius-sm);
      background: var(--brand);
      color: #fff;
      font-size: 13px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      font-family: var(--font);
      transition: all .2s;
      white-space: nowrap
    }

    .btn-primary:hover {
      background: #1345b5;
      transform: translateY(-1px)
    }

    .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 18px;
      border-radius: var(--radius-sm);
      background: var(--surface2);
      color: var(--ink2);
      font-size: 13px;
      font-weight: 600;
      border: 1px solid var(--border2);
      cursor: pointer;
      font-family: var(--font);
      transition: all .2s;
      white-space: nowrap
    }

    .btn-secondary:hover {
      background: var(--brand-light);
      color: var(--brand);
      border-color: var(--brand-mid)
    }

    /* ── MAIN ── */
    .main {
      margin-left: var(--nav-w);
      margin-top: var(--top-h);
      flex: 1;
      min-width: 0;
      padding: 28px;
      background: var(--surface2)
    }

    /* ── HERO STRIP ── */
    .hero-strip {
      background: linear-gradient(135deg, #ffffff 0%, #eff6ff 60%, #fffbeb 100%);
      border: 1px solid rgba(26, 86, 219, .12);
      border-radius: var(--radius-lg);
      padding: 24px 28px;
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden
    }

    .hero-strip::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #1f9d55 33.3%, #d4a017 33.3% 66.6%, #1a3a6b 66.6%)
    }

    .hero-av {
      width: 72px;
      height: 72px;
      border-radius: 16px;
      background: var(--brand);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 800;
      flex-shrink: 0;
      overflow: hidden;
      cursor: pointer;
      border: 3px solid var(--brand-light);
      transition: border-color .2s
    }

    .hero-av:hover {
      border-color: var(--brand)
    }

    .hero-av img {
      width: 100%;
      height: 100%;
      object-fit: cover
    }

    .hero-info {
      flex: 1;
      min-width: 0
    }

    .hero-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      margin-bottom: 6px
    }

    .hchip {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      white-space: nowrap
    }

    .hc-tipo {
      background: var(--brand-light);
      color: var(--brand)
    }

    .hc-v {
      background: #e8f5e9;
      color: #2e7d32
    }

    .hc-p {
      background: #fff8e1;
      color: #f57f17
    }

    .hc-top {
      background: #fce4ec;
      color: #c62828
    }

    .hc-dest {
      background: #ede9fe;
      color: #5b21b6
    }

    .hero-name {
      font-size: 22px;
      font-weight: 800;
      color: var(--ink);
      letter-spacing: -.5px
    }

    .hero-sub {
      font-size: 13px;
      color: var(--ink3);
      margin-top: 2px
    }

    .hero-stats {
      display: flex;
      gap: 24px;
      flex-shrink: 0
    }

    .hs {
      text-align: center
    }

    .hs-val {
      font-size: 24px;
      font-weight: 800;
      color: var(--brand);
      line-height: 1
    }

    .hs-lab {
      font-size: 11px;
      color: var(--ink4);
      margin-top: 2px;
      font-weight: 500
    }

    .hero-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex-shrink: 0
    }

    /* ── ALERT BAR ── */
    .alert-bar {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 18px;
      border-radius: var(--radius);
      margin-bottom: 18px;
      font-size: 13px
    }

    .alert-bar.as {
      background: #eff6ff;
      border: 1px solid var(--brand-mid);
      color: #1e40af
    }

    .alert-bar.ap {
      background: #fffbeb;
      border: 1px solid #fcd34d;
      color: #92400e
    }

    .alert-bar.ar {
      background: #fce8e8;
      border: 1px solid #fca5a5;
      color: #b71c1c
    }

    .alert-bar.av {
      background: linear-gradient(135deg, #eff6ff, #f0f5ff);
      border: 1px solid var(--brand-mid);
      color: #1e40af
    }

    .alert-bar .a-ico {
      font-size: 18px;
      flex-shrink: 0
    }

    .alert-bar .a-txt {
      flex: 1
    }

    .alert-bar .a-txt strong {
      font-weight: 700
    }

    .alert-bar .a-txt span {
      margin-left: 6px;
      opacity: .8
    }

    .alert-bar .a-btn {
      padding: 6px 14px;
      border-radius: 8px;
      background: var(--brand);
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      white-space: nowrap;
      flex-shrink: 0;
      text-decoration: none
    }

    /* ── DASHBOARD GRID ── */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 18px
    }

    .col-4 {
      grid-column: span 4
    }

    .col-6 {
      grid-column: span 6
    }

    .col-8 {
      grid-column: span 8
    }

    .col-12 {
      grid-column: span 12
    }

    .col-3 {
      grid-column: span 3
    }

    /* ── CARDS ── */
    .card {
      background: #ffffff;
      border: 1px solid rgba(26, 86, 219, .08);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(26, 86, 219, .05)
    }

    .card-header {
      padding: 16px 20px 14px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between
    }

    .card-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      display: flex;
      align-items: center;
      gap: 7px
    }

    .card-link {
      font-size: 12px;
      color: var(--brand);
      font-weight: 600;
      cursor: pointer
    }

    .card-body {
      padding: 18px 20px
    }

    /* ── METRIC CARDS ── */
    .metric-card {
      background: #ffffff;
      border: 1px solid rgba(26, 86, 219, .08);
      border-radius: var(--radius);
      padding: 18px 20px;
      display: flex;
      align-items: center;
      gap: 14px;
      transition: all .2s;
      cursor: default;
      box-shadow: 0 1px 4px rgba(26, 86, 219, .05)
    }

    .metric-card:hover {
      border-color: var(--brand-mid);
      box-shadow: 0 4px 16px rgba(26, 86, 219, .1);
      transform: translateY(-1px)
    }

    .mc-ico {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      flex-shrink: 0
    }

    .mc-ico.g {
      background: var(--brand-light)
    }

    .mc-ico.a {
      background: #fffbeb
    }

    .mc-ico.o {
      background: #fff7ed
    }

    .mc-ico.m {
      background: #ede9fe
    }

    .mc-val {
      font-size: 26px;
      font-weight: 800;
      color: var(--ink);
      line-height: 1
    }

    .mc-lab {
      font-size: 12px;
      color: var(--ink3);
      margin-top: 2px
    }

    .mc-sub {
      font-size: 11px;
      color: var(--brand);
      font-weight: 600;
      margin-top: 4px;
      cursor: pointer
    }

    /* ── PROFILE CARD ── */
    .profile-card {
      padding: 20px
    }

    .pc-av {
      width: 56px;
      height: 56px;
      border-radius: 14px;
      background: var(--brand);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      font-weight: 800;
      overflow: hidden;
      cursor: pointer;
      margin-bottom: 12px;
      border: 3px solid var(--brand-light)
    }

    .pc-av img {
      width: 100%;
      height: 100%;
      object-fit: cover
    }

    .pc-name {
      font-size: 16px;
      font-weight: 800;
      color: var(--ink)
    }

    .pc-role {
      font-size: 12px;
      color: var(--ink3);
      margin-bottom: 14px
    }

    .pc-rows {
      display: flex;
      flex-direction: column;
      gap: 8px
    }

    .pc-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: var(--ink2)
    }

    .pc-row-ico {
      font-size: 14px;
      flex-shrink: 0;
      width: 18px;
      text-align: center
    }

    /* ── PROGRESS BAR ── */
    .prog-wrap {
      margin: 14px 0
    }

    .prog-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
      font-size: 12px;
      color: var(--ink3);
      font-weight: 600
    }

    .prog-track {
      height: 6px;
      background: var(--surface3);
      border-radius: 6px;
      overflow: hidden
    }

    .prog-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      border-radius: 6px;
      transition: width 1s ease
    }

    /* ── VISIBILITY ROW ── */
    .vis-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 0;
      border-top: 1px solid var(--border);
      margin-top: 4px
    }

    .vis-label {
      font-size: 12.5px;
      font-weight: 700;
      color: var(--ink2)
    }

    .vis-sub {
      font-size: 11px;
      color: var(--ink4)
    }

    .tog {
      position: relative;
      display: inline-block;
      width: 40px;
      height: 22px
    }

    .tog input {
      opacity: 0;
      width: 0;
      height: 0
    }

    .tog-sl {
      position: absolute;
      inset: 0;
      background: #ddd;
      border-radius: 22px;
      cursor: pointer;
      transition: .3s
    }

    .tog-sl::before {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      background: #fff;
      border-radius: 50%;
      left: 3px;
      top: 3px;
      transition: .3s;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .2)
    }

    input:checked+.tog-sl {
      background: var(--brand)
    }

    input:checked+.tog-sl::before {
      transform: translateX(18px)
    }

    .pv-chip {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px
    }

    .pv-chip.ok {
      background: #dbeafe;
      color: #1e40af
    }

    .pv-chip.off {
      background: #fffbeb;
      color: #92400e
    }

    /* ── QUICK ACTIONS ── */
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 10px;
      padding: 16px 20px
    }

    .action-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 7px;
      padding: 14px 8px;
      border-radius: var(--radius-sm);
      border: 1px solid var(--border);
      background: var(--surface2);
      cursor: pointer;
      transition: all .18s;
      text-decoration: none;
      text-align: center;
      position: relative
    }

    .action-item:hover {
      background: var(--brand-light);
      border-color: var(--brand-mid);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(26, 86, 219, .1)
    }

    .action-item .ai-ico {
      font-size: 22px
    }

    .action-item .ai-label {
      font-size: 11.5px;
      font-weight: 700;
      color: var(--ink2);
      line-height: 1.2
    }

    .action-item .ai-sub {
      font-size: 10px;
      color: var(--ink4)
    }

    .action-item .ai-badge {
      position: absolute;
      top: 6px;
      right: 6px;
      background: var(--danger);
      color: #fff;
      font-size: 9px;
      font-weight: 700;
      padding: 2px 5px;
      border-radius: 20px
    }

    /* ── JOB LIST ── */
    .job-list {
      padding: 0 20px 16px
    }

    .job-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid var(--border)
    }

    .job-item:last-child {
      border-bottom: none
    }

    .job-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0
    }

    .job-dot.act {
      background: #22c55e
    }

    .job-dot.pen {
      background: var(--accent)
    }

    .job-dot.cer {
      background: var(--ink4)
    }

    .job-info {
      flex: 1;
      min-width: 0
    }

    .job-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis
    }

    .job-meta {
      font-size: 11px;
      color: var(--ink4);
      margin-top: 2px
    }

    .job-date {
      font-size: 11px;
      color: var(--ink4);
      flex-shrink: 0
    }

    .job-del {
      width: 24px;
      height: 24px;
      border-radius: 6px;
      background: transparent;
      border: 1px solid #fca5a5;
      color: #e74c3c;
      font-size: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: .2s;
      padding: 0;
      flex-shrink: 0
    }

    .job-del:hover {
      background: #fee2e2
    }

    .job-empty {
      text-align: center;
      padding: 28px 16px
    }

    .job-empty-ico {
      font-size: 36px;
      margin-bottom: 8px
    }

    .job-empty-txt {
      font-size: 13px;
      font-weight: 600;
      color: var(--ink2);
      margin-bottom: 4px
    }

    /* ── PLAN USAGE ── */
    .plan-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 12px;
      padding: 16px 20px
    }

    .plan-bar {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 14px
    }

    .pb-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--ink3);
      margin-bottom: 6px
    }

    .pb-count {
      font-size: 20px;
      font-weight: 800;
      color: var(--ink);
      line-height: 1;
      margin-bottom: 8px
    }

    .pb-count span {
      font-size: 13px;
      font-weight: 400;
      color: var(--ink4)
    }

    .pb-track {
      height: 5px;
      background: rgba(0, 0, 0, .07);
      border-radius: 5px
    }

    .pb-fill {
      height: 5px;
      border-radius: 5px;
      transition: width .5s
    }

    .pb-fill.low {
      background: var(--brand)
    }

    .pb-fill.mid {
      background: var(--accent)
    }

    .pb-fill.high {
      background: var(--danger)
    }

    .pb-warn {
      font-size: 10px;
      font-weight: 700;
      margin-top: 5px;
      color: var(--danger)
    }

    /* ── ACTIVITY ── */
    .activity-list {
      padding: 4px 20px 16px
    }

    .act-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid var(--border)
    }

    .act-item:last-child {
      border-bottom: none
    }

    .act-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--brand-mid);
      margin-top: 5px;
      flex-shrink: 0
    }

    .act-txt {
      font-size: 12.5px;
      color: var(--ink2);
      flex: 1
    }

    .act-txt strong {
      color: var(--ink)
    }

    .act-time {
      font-size: 11px;
      color: var(--ink4);
      flex-shrink: 0;
      margin-top: 2px
    }

    /* ── MODALS ── */
    .modal-ov {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .45);
      z-index: 1000;
      align-items: flex-start;
      justify-content: center;
      padding: 40px 16px;
      overflow-y: auto;
      backdrop-filter: blur(3px)
    }

    .modal-ov.open {
      display: flex
    }

    .modal-box {
      background: var(--surface);
      border-radius: var(--radius-lg);
      width: 100%;
      max-width: 520px;
      box-shadow: 0 24px 64px rgba(0, 0, 0, .18);
      position: relative;
      overflow: hidden
    }

    .modal-box.wide {
      max-width: 640px
    }

    .modal-pad {
      padding: 28px 28px 24px
    }

    .mcerrar {
      position: absolute;
      top: 14px;
      right: 14px;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--surface2);
      border: 1px solid var(--border);
      font-size: 14px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
      transition: all .18s;
      font-family: var(--font)
    }

    .mcerrar:hover {
      background: #fef2f2;
      color: var(--danger)
    }

    .mtit {
      font-size: 20px;
      font-weight: 800;
      color: var(--ink);
      margin-bottom: 4px
    }

    .msub {
      font-size: 13px;
      color: var(--ink3);
      margin-bottom: 18px
    }

    .msec {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--ink4);
      margin: 18px 0 10px
    }

    .mmsg {
      display: none;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 12px
    }

    .mmsg.success {
      background: #dbeafe;
      color: #1e40af
    }

    .mmsg.error {
      background: #fce8e8;
      color: #b71c1c
    }

    .mfila {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 12px
    }

    .mgr {
      display: flex;
      flex-direction: column;
      gap: 5px
    }

    .mgr.full {
      grid-column: 1/-1
    }

    .mgr label {
      font-size: 12px;
      font-weight: 700;
      color: var(--ink2)
    }

    .mgr input,
    .mgr select,
    .mgr textarea,
    .minput {
      padding: 9px 12px;
      border-radius: 10px;
      border: 1.5px solid var(--border2);
      font-size: 13px;
      font-family: var(--font);
      color: var(--ink);
      background: var(--surface2);
      width: 100%;
      box-sizing: border-box;
      transition: border-color .18s
    }

    .mgr input:focus,
    .mgr select:focus,
    .mgr textarea:focus,
    .minput:focus {
      outline: none;
      border-color: var(--brand);
      background: var(--surface)
    }

    .mlabel {
      font-size: 12px;
      font-weight: 700;
      color: var(--ink2);
      margin-bottom: 5px;
      display: block
    }

    .btn-save {
      display: block;
      width: 100%;
      padding: 13px;
      border-radius: var(--radius);
      background: var(--brand);
      color: #fff;
      font-size: 14px;
      font-weight: 800;
      border: none;
      cursor: pointer;
      font-family: var(--font);
      margin-top: 18px;
      transition: background .2s;
      box-shadow: 0 4px 14px rgba(26, 86, 219, .3)
    }

    .btn-save:hover {
      background: #1345b5
    }

    .btn-save:disabled {
      opacity: .6;
      cursor: not-allowed
    }

    /* ── CROP MODAL ── */
    .crop-modal {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .7);
      z-index: 2000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px
    }

    .crop-inner {
      background: var(--surface);
      border-radius: var(--radius-lg);
      padding: 24px;
      max-width: 500px;
      width: 100%;
      box-shadow: 0 24px 64px rgba(0, 0, 0, .2)
    }

    /* ── DASH AVATAR WIDGET ── */
    .dash-avatar-btn {
      display: flex;
      align-items: center;
      gap: 9px;
      background: white;
      border: 1.5px solid rgba(26, 86, 219, .25);
      border-radius: 40px;
      padding: 5px 14px 5px 5px;
      cursor: pointer;
      transition: all .3s cubic-bezier(.34, 1.56, .64, 1);
      box-shadow: 0 2px 12px rgba(26, 86, 219, .1);
      user-select: none
    }

    .dash-avatar-btn:hover,
    .dash-avatar-btn.open {
      border-color: var(--brand);
      box-shadow: 0 4px 20px rgba(26, 86, 219, .18);
      transform: translateY(-1px)
    }

    .dash-avatar-img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 800;
      color: white;
      overflow: hidden;
      flex-shrink: 0;
      border: 2px solid rgba(26, 86, 219, .25)
    }

    .dash-avatar-img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%
    }

    .dash-avatar-info {
      display: flex;
      flex-direction: column;
      line-height: 1.2
    }

    .dash-avatar-nombre {
      font-size: 13px;
      font-weight: 700;
      color: #111;
      max-width: 90px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap
    }

    .dash-avatar-sub {
      font-size: 10px;
      color: #888;
      display: flex;
      align-items: center;
      gap: 4px
    }

    .dash-avatar-arrow {
      font-size: 9px;
      color: var(--brand);
      transition: transform .3s
    }

    .dash-avatar-btn.open .dash-avatar-arrow {
      transform: rotate(180deg)
    }

    .dash-online-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #2ecc71;
      animation: dash-online 2.5s infinite
    }

    @keyframes dash-online {

      0%,
      100% {
        box-shadow: 0 0 0 0 rgba(46, 204, 113, .4)
      }

      60% {
        box-shadow: 0 0 0 5px rgba(46, 204, 113, 0)
      }
    }

    .dash-dropdown {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: 270px;
      background: white;
      border-radius: 18px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .12), 0 4px 16px rgba(0, 0, 0, .07);
      border: 1px solid rgba(0, 0, 0, .07);
      overflow: hidden;
      z-index: 9999;
      opacity: 0;
      transform: translateY(-10px) scale(.97);
      pointer-events: none;
      transition: all .25s cubic-bezier(.34, 1.56, .64, 1)
    }

    .dash-dropdown.visible {
      opacity: 1;
      transform: translateY(0) scale(1);
      pointer-events: all
    }

    .dash-drop-header {
      padding: 18px 20px 14px;
      background: linear-gradient(135deg, #eff6ff, #dbeafe);
      border-bottom: 1px solid rgba(26, 86, 219, .1);
      display: flex;
      align-items: center;
      gap: 12px
    }

    .dash-drop-av {
      width: 46px;
      height: 46px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 800;
      color: white;
      flex-shrink: 0;
      border: 3px solid white;
      box-shadow: 0 4px 12px rgba(26, 86, 219, .25);
      overflow: hidden
    }

    .dash-drop-av img {
      width: 100%;
      height: 100%;
      border-radius: 10px;
      object-fit: cover
    }

    .dash-drop-nombre {
      font-size: 15px;
      font-weight: 700;
      color: #111
    }

    .dash-drop-tipo {
      font-size: 11px;
      color: var(--brand);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-top: 2px
    }

    .dash-drop-correo {
      font-size: 11px;
      color: #999;
      margin-top: 2px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 150px
    }

    .dash-drop-menu {
      padding: 8px 0
    }

    .dash-drop-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 11px 20px;
      color: #333;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: all .2s;
      cursor: pointer
    }

    .dash-drop-link:hover {
      background: #f0f5ff;
      color: var(--brand);
      padding-left: 24px
    }

    .dash-dl-icon {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      background: #f4f6f8;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      flex-shrink: 0;
      transition: all .2s
    }

    .dash-drop-link:hover .dash-dl-icon {
      background: rgba(26, 86, 219, .1);
      transform: scale(1.1)
    }

    .dash-dl-badge {
      margin-left: auto;
      background: #e74c3c;
      color: white;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 12px
    }

    .dash-drop-sep {
      height: 1px;
      background: rgba(0, 0, 0, .06);
      margin: 4px 0
    }

    .dash-drop-logout {
      color: #e74c3c !important
    }

    .dash-drop-logout .dash-dl-icon {
      background: #fff5f5 !important
    }

    .dash-drop-logout:hover {
      background: #fff5f5 !important;
      color: #c0392b !important
    }

    /* ── SIDEBAR OVERLAY ── */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .35);
      z-index: 199
    }

    /* ── BANNER ZONE ── */
    .banner-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 18px
    }

    .banner-zone {
      position: relative;
      height: 140px;
      background: linear-gradient(135deg, #1a3060, #1a56db);
      cursor: pointer;
      overflow: hidden
    }

    .banner-zone:hover .banner-hover {
      opacity: 1
    }

    .banner-hover {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, .3);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: .2s
    }

    .banner-hover span {
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      background: rgba(0, 0, 0, .5);
      padding: 6px 14px;
      border-radius: 20px
    }

    .banner-info-row {
      padding: 0 20px 16px;
      margin-top: -40px;
      position: relative;
      z-index: 2;
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 14px;
      flex-wrap: wrap
    }

    .banner-logo-wrap {
      position: relative;
      display: inline-block
    }

    .banner-logo {
      width: 80px;
      height: 80px;
      border-radius: 16px;
      border: 4px solid #fff;
      box-shadow: 0 4px 18px rgba(0, 0, 0, .15);
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      font-weight: 900;
      color: #fff;
      cursor: pointer;
      overflow: hidden;
      transition: .2s
    }

    .banner-logo img {
      width: 100%;
      height: 100%;
      object-fit: cover
    }

    .banner-logo-edit {
      position: absolute;
      bottom: 2px;
      right: 2px;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: var(--brand);
      border: 2px solid #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 13px
    }

    .banner-name-wrap {
      flex: 1;
      min-width: 180px;
      padding-top: 44px
    }

    .banner-empresa-name {
      font-size: 19px;
      font-weight: 800;
      color: var(--ink)
    }

    .banner-empresa-sub {
      font-size: 13px;
      color: var(--ink3);
      margin-top: 2px
    }

    .banner-actions-wrap {
      display: flex;
      gap: 8px;
      padding-top: 44px;
      flex-wrap: wrap
    }

    /* ── NOTIF ITEMS ── */
    .notif-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 12px 16px;
      border-bottom: 1px solid var(--border);
      font-size: 13px;
      color: var(--ink2)
    }

    .notif-item:last-child {
      border-bottom: none
    }

    .notif-ico {
      font-size: 18px;
      flex-shrink: 0
    }

    .notif-sub {
      font-size: 11px;
      color: var(--ink4);
      margin-top: 2px
    }

    /* ── RESPONSIVE ── */
    @media(max-width:1100px) {
      .col-4 {
        grid-column: span 6
      }

      .col-3 {
        grid-column: span 6
      }
    }

    @media(max-width:820px) {
      :root {
        --nav-w: 0px
      }

      .sidebar {
        transform: translateX(-240px);
        top: 0;
        z-index: 350;
        width: 240px
      }

      .sidebar-logo {
        display: flex
      }

      .sidebar.open {
        transform: translateX(0)
      }

      .sidebar-overlay.open {
        display: block
      }

      .topbar {
        left: 0;
        padding: 0 12px;
        z-index: 300;
        gap: 8px
      }

      .topbar-title {
        display: none
      }

      .hamburger {
        display: flex
      }

      .topbar-actions {
        gap: 6px
      }

      .topbar-actions .btn-primary {
        padding: 8px 12px;
        font-size: 12px
      }

      .dash-avatar-info {
        display: none
      }

      .dash-avatar-btn {
        padding: 5px
      }

      .main {
        padding: 18px 16px;
        margin-left: 0
      }

      .hero-strip {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        padding: 16px
      }

      .hero-top-row {
        display: flex;
        align-items: center;
        gap: 12px;
        width: 100%
      }

      .hero-av {
        width: 56px;
        height: 56px;
        font-size: 20px;
        flex-shrink: 0
      }

      .hero-info {
        width: 100%
      }

      .hero-name {
        font-size: 18px
      }

      .hero-stats {
        display: flex;
        gap: 0;
        width: 100%;
        background: var(--surface2);
        border-radius: 12px;
        padding: 10px 0
      }

      .hs {
        flex: 1;
        text-align: center;
        border-right: 1px solid var(--border);
        padding: 0 8px
      }

      .hs:last-child {
        border-right: none
      }

      .hs-val {
        font-size: 20px
      }

      .hs-lab {
        font-size: 10px
      }

      .hero-actions {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        gap: 8px;
        width: 100%
      }

      .hero-actions .btn-primary,
      .hero-actions .btn-secondary {
        flex: 1;
        justify-content: center;
        font-size: 12px;
        padding: 9px 10px
      }

      .col-4,
      .col-6,
      .col-8,
      .col-3 {
        grid-column: span 12
      }

      .dashboard-grid {
        gap: 14px
      }

      .banner-info-row {
        flex-direction: column;
        align-items: flex-start;
        margin-top: -20px
      }

      .banner-logo {
        width: 68px;
        height: 68px;
        font-size: 22px
      }

      .banner-name-wrap,
      .banner-actions-wrap {
        padding-top: 0
      }
    }

    @media(max-width:480px) {
      .main {
        padding: 12px 10px
      }

      .hero-strip {
        padding: 14px 12px
      }

      .hero-name {
        font-size: 16px
      }

      .hs-val {
        font-size: 18px
      }
    }

    @media(max-width:600px) {
      .mfila {
        grid-template-columns: 1fr
      }

      .modal-pad {
        padding: 20px 16px 18px
      }
    }
  </style>
</head>

<body>

  <div class="barra-bandera"></div>
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- ── SIDEBAR ── -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="Imagenes/quibdo_desco_new.png" alt="QuibdóConecta">
      <div>
        <div class="sidebar-logo-txt">QuibdóConecta</div>
        <div class="sidebar-logo-sub">Conectando el Chocó</div>
      </div>
    </div>

    <div class="sidebar-user">
      <div class="su-av" id="sidebarAvatar" onclick="abrirModal()" title="Editar logo">
        <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>" alt="Logo"><?php else: ?><?= $iniciales ?><?php endif; ?>
      </div>
      <div>
        <div class="su-name" id="sidebarNombre"><?= $nombreEmpresa ?></div>
        <div class="su-role">🏢 Empresa</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard_empresa.php" class="nav-item active"><span class="ni-ico">🏠</span> Panel Empresa</a>
        <a href="chat.php" class="nav-item">
          <span class="ni-ico">💬</span> Mensajes
          <?php if ($chatNoLeidos > 0): ?><span class="ni-badge"><?= $chatNoLeidos ?></span><?php endif; ?>
        </a>
        <a href="buscar.php" class="nav-item"><span class="ni-ico">🔍</span> Buscar</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-label">Empresa</div>
        <a href="javascript:void(0)" onclick="abrirModalVacante()" class="nav-item"><span class="ni-ico">➕</span>
          Publicar vacante</a>
        <a href="talentos.php" class="nav-item"><span class="ni-ico">🌟</span> Talentos</a>
        <a href="Empleo.php" class="nav-item"><span class="ni-ico">💼</span> Empleos</a>
        <a href="empresas.php" class="nav-item"><span class="ni-ico">🏢</span> Empresas</a>
      </div>
      <div class="nav-section">
        <div class="nav-section-label">Directorio</div>
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
          <div class="sp-name"><?= htmlspecialchars($datosPlan['nombre'] ?? 'Semilla') ?></div>
          <a href="empresas.php#precios" class="sp-btn">✦ Mejorar plan</a>
        </div>
      <?php endif; ?>
      <a href="?salir=1" class="nav-salir"><span>🚪</span> Cerrar sesión</a>
    </div>
  </aside>

  <!-- ── TOPBAR ── -->
  <div class="topbar">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Menú">
      <span></span><span></span><span></span>
    </button>
    <a href="index.html" class="topbar-logo" title="Ir al inicio">
      <img src="Imagenes/quibdo_desco_new.png" alt="QuibdóConecta">
    </a>
    <div class="topbar-title">
      <svg width="22" height="15" viewBox="0 0 22 15" xmlns="http://www.w3.org/2000/svg"
        style="border-radius:2px;vertical-align:middle;margin-right:6px;box-shadow:0 1px 3px rgba(0,0,0,.2)">
        <rect width="22" height="5" y="0" fill="#1f9d55" />
        <rect width="22" height="5" y="5" fill="#d4a017" />
        <rect width="22" height="5" y="10" fill="#1a3a6b" />
      </svg>
      Panel <span>Empresa</span>
    </div>
    <div class="topbar-actions">
      <button class="btn-primary" onclick="abrirModalVacante()" style="display:flex;align-items:center;gap:5px">
        <span>➕</span> Publicar vacante
      </button>

      <div style="position:relative">
        <div class="tb-btn" id="navNotif" title="Notificaciones">
          🔔<div class="tb-dot" id="notifDot" style="display:none"></div>
          <div class="tb-notif-panel" id="notifPanel">
            <div class="tnp-head">🔔 Notificaciones</div>
            <div class="tnp-body">
              <div id="notifLista">
                <div class="tnp-empty">Cargando…</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div style="position:relative" id="dashUserWidget">
        <div class="dash-avatar-btn" id="dashAvatarBtn" title="Mi cuenta">
          <div class="dash-avatar-img" id="dashAvatarImg">
            <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>" alt="Logo"><?php else: ?><?= $iniciales ?><?php endif; ?>
          </div>
          <div class="dash-avatar-info">
            <span class="dash-avatar-nombre"><?= $nombreEmpresa ?></span>
            <span class="dash-avatar-sub"><span class="dash-online-dot"></span>En línea <span
                class="dash-avatar-arrow">▾</span></span>
          </div>
        </div>
        <div class="dash-dropdown" id="dashDropdown">
          <div class="dash-drop-header">
            <div class="dash-drop-av">
              <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>"
                  alt="Logo"><?php else: ?><?= $iniciales ?><?php endif; ?>
            </div>
            <div>
              <div class="dash-drop-nombre"><?= $nombreEmpresa ?></div>
              <div class="dash-drop-tipo">🏢 Empresa</div>
              <div class="dash-drop-correo"><?= $correo ?></div>
            </div>
          </div>
          <?php if (!empty($badgesHTML)): ?>
            <div style="padding:10px 20px;border-bottom:1px solid rgba(0,0,0,.05);display:flex;flex-wrap:wrap;gap:4px">
              <?= $badgesHTML ?></div><?php endif; ?>
          <div class="dash-drop-menu">
            <a href="dashboard_empresa.php" class="dash-drop-link"><span class="dash-dl-icon">🏠</span> Mi panel</a>
            <a href="chat.php" class="dash-drop-link">
              <span class="dash-dl-icon">💬</span> Mensajes
              <?php if ($chatNoLeidos > 0): ?><span class="dash-dl-badge"><?= $chatNoLeidos ?></span><?php endif; ?>
            </a>
            <a href="verificar_cuenta.php" class="dash-drop-link"><span class="dash-dl-icon">🪪</span> Verificación</a>
            <div class="dash-drop-sep"></div>
            <a href="?salir=1" class="dash-drop-link dash-drop-logout"><span class="dash-dl-icon">🚪</span> Cerrar
              sesión</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── MAIN ── -->
  <main class="main">

    <!-- ALERTAS -->
    <?php if (!$tieneVerificado): ?>
      <?php if ($estadoVerif === 'pendiente'): ?>
        <div class="alert-bar ap">
          <div class="a-ico">⏳</div>
          <div class="a-txt"><strong>Documentos en revisión</strong><span>El administrador está revisando tu RUT o cámara de
              comercio.</span></div>
        </div>
      <?php elseif ($estadoVerif === 'rechazado'): ?>
        <div class="alert-bar ar">
          <div class="a-ico">❌</div>
          <div class="a-txt"><strong>Verificación
              rechazada</strong><span><?= $notaRechazo ?: 'Intenta subir los documentos con mejor calidad.' ?></span></div>
          <a href="verificar_cuenta.php" class="a-btn">Reintentar</a>
        </div>
      <?php else: ?>
        <div class="alert-bar as">
          <div class="a-ico">🏢</div>
          <div class="a-txt"><strong>Verifica tu empresa</strong><span>Sube tu RUT o cámara de comercio y obtén el badge de
              Empresa Verificada.</span></div>
          <a href="verificar_cuenta.php" class="a-btn">Verificar ahora</a>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert-bar av">
        <div class="a-ico">✅</div>
        <div class="a-txt"><strong>Empresa verificada</strong><span>Los talentos ven tu badge de Empresa Verificada al ver
            tus vacantes.</span></div>
      </div>
    <?php endif; ?>

    <!-- HERO STRIP -->
    <div class="hero-strip">
      <div class="hero-top-row">
        <div class="hero-av" id="heroAvatar" onclick="abrirModal()" title="Cambiar logo">
          <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>" alt="Logo"><?php else: ?><?= $iniciales ?><?php endif; ?>
        </div>
        <div class="hero-info">
          <div class="hero-chips">
            <span class="hchip hc-tipo">🏢 Empresa</span>
            <?php if ($tieneVerificado): ?><span class="hchip hc-v">✓ Verificada</span><?php endif; ?>
            <?php if ($tienePremium): ?><span class="hchip hc-p">⭐ Premium</span><?php endif; ?>
            <?php if ($tieneTop): ?><span class="hchip hc-top">👑 Top</span><?php endif; ?>
            <?php if ($tieneDestacado): ?><span class="hchip hc-dest">🏅 Destacada</span><?php endif; ?>
          </div>
          <div class="hero-name" id="dNombreHero">¡Hola, <em><?= $nombreEmpresa ?></em>!</div>
          <div class="hero-sub">
            <?php if ($sector): ?>  <?= $sector ?><?php endif; ?>
            <?php if ($ciudad): ?> · 📍 <?= $ciudad ?><?php endif; ?>
            <?php if (!$sector && !$ciudad): ?>Gestiona tus vacantes y conecta con el talento del Chocó.<?php endif; ?>
          </div>
        </div>
      </div>
      <div class="hero-stats">
        <div class="hs">
          <div class="hs-val"><?= $vacantesActivas ?></div>
          <div class="hs-lab">Vacantes</div>
        </div>
        <div class="hs">
          <div class="hs-val"><?= $chatNoLeidos ?></div>
          <div class="hs-lab">Mensajes</div>
        </div>
        <div class="hs">
          <div class="hs-val"><?= $pct ?>%</div>
          <div class="hs-lab">Perfil</div>
        </div>
      </div>
      <div class="hero-actions">
        <button class="btn-primary" onclick="abrirModalVacante()">➕ Publicar vacante</button>
        <button class="btn-secondary" onclick="abrirModal()">✏️ Editar empresa</button>
      </div>
    </div>

    <!-- BANNER + LOGO CARD -->
    <div class="banner-card">
      <div class="banner-zone" id="bannerZone" onclick="document.getElementById('bannerInput').click()"
        title="Cambiar banner">
        <?php if ($bannerUrl): ?>
          <img id="bannerImg" src="<?= $bannerUrl ?>" style="width:100%;height:100%;object-fit:cover;display:block">
        <?php else: ?>
          <div id="bannerPlaceholder"
            style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:rgba(255,255,255,.65)">
            <div style="font-size:28px">🖼️</div>
            <div style="font-size:12px;font-weight:600">Haz clic para subir el banner de tu empresa</div>
            <div style="font-size:11px;opacity:.7">1200×300 px · JPG/PNG/WEBP · máx 5 MB</div>
          </div>
        <?php endif; ?>
        <div class="banner-hover"><span>✏️ Cambiar banner</span></div>
        <?php if ($bannerUrl): ?>
          <button onclick="event.stopPropagation();eliminarBanner()"
            style="position:absolute;top:10px;right:10px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:20px;padding:5px 12px;font-size:11px;font-weight:700;cursor:pointer">🗑
            Quitar</button>
        <?php endif; ?>
      </div>
      <input type="file" id="bannerInput" accept="image/jpeg,image/png,image/webp" style="display:none"
        onchange="subirBanner(this)">
      <div class="banner-info-row">
        <div class="banner-logo-wrap">
          <div class="banner-logo" id="cpAvatar" onclick="abrirModal()" title="Cambiar logo">
            <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>" alt="Logo"><?php else: ?><?= $iniciales ?><?php endif; ?>
          </div>
          <div class="banner-logo-edit" onclick="abrirModal()">✏️</div>
        </div>
        <div class="banner-name-wrap">
          <div class="banner-empresa-name" id="dNombreEmp"><?= $nombreEmpresa ?></div>
          <div class="banner-empresa-sub">
            <?php if ($sector): ?>  <?= htmlspecialchars($sector) ?><?php endif; ?>
            <?php if ($ciudad): ?> · 📍 <?= $ciudad ?><?php endif; ?>
          </div>
        </div>
        <div class="banner-actions-wrap">
          <a href="empresas.php"
            style="display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:var(--brand-light);color:var(--brand);border:1.5px solid var(--brand-mid);border-radius:10px;font-size:12px;font-weight:700;text-decoration:none">👁
            Ver en directorio</a>
          <button onclick="abrirModal()"
            style="display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:var(--brand);color:#fff;border:none;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer">🖼
            Cambiar logo</button>
          <?php if ($logoUrl): ?>
            <button onclick="eliminarLogoBanner()" id="btnEliminarLogoBanner"
              style="display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:transparent;color:#e53935;border:1.5px solid #e53935;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer">🗑
              Quitar logo</button>
          <?php endif; ?>
        </div>
      </div>
      <div id="bannerMsg" style="font-size:12px;color:#e53935;margin:0 20px 12px;display:none"></div>
    </div>

    <!-- DASHBOARD GRID -->
    <div class="dashboard-grid">

      <!-- MÉTRICAS -->
      <div class="col-4">
        <div class="metric-card" onclick="abrirModalVacante()" style="cursor:pointer">
          <div class="mc-ico g">💼</div>
          <div>
            <div class="mc-val"><?= $vacantesActivas ?></div>
            <div class="mc-lab">Vacantes activas</div>
            <div class="mc-sub">Publicar nueva →</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="metric-card" onclick="location.href='talentos.php'" style="cursor:pointer">
          <div class="mc-ico a">👥</div>
          <div>
            <div class="mc-val">0</div>
            <div class="mc-lab">Candidatos</div>
            <div class="mc-sub">Ver talentos →</div>
          </div>
        </div>
      </div>
      <div class="col-4">
        <div class="metric-card" onclick="location.href='chat.php'" style="cursor:pointer">
          <div class="mc-ico o">💬</div>
          <div>
            <div class="mc-val"><?= $chatNoLeidos ?></div>
            <div class="mc-lab">Mensajes</div>
            <div class="mc-sub">Ir al chat →</div>
          </div>
        </div>
      </div>

      <!-- PERFIL CARD -->
      <div class="col-4">
        <div class="card" style="height:100%">
          <div class="card-header">
            <div class="card-title">🏢 Mi empresa</div>
            <button class="btn-secondary" onclick="abrirModal()" style="padding:5px 12px;font-size:12px">Editar</button>
          </div>
          <div class="profile-card">
            <div class="pc-av" id="cpAvatarCard" onclick="abrirModal()">
              <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>"
                  alt="Logo"><?php else: ?><?= $iniciales ?><?php endif; ?>
            </div>
            <div class="pc-name" id="dNombreCard"><?= $nombreEmpresa ?></div>
            <div class="pc-role" id="dSector"><?= $sector ?: 'Sector no definido' ?></div>
            <div class="pc-rows">
              <div class="pc-row"><span class="pc-row-ico">📍</span><span
                  id="dCiudad"><?= $ciudad ?: 'Ciudad no registrada' ?></span></div>
              <div class="pc-row"><span class="pc-row-ico">✉️</span><span><?= $correo ?></span></div>
              <?php if (!empty($ep['sitio_web'])): ?>
                <div class="pc-row"><span class="pc-row-ico">🌐</span><a href="<?= htmlspecialchars($ep['sitio_web']) ?>"
                    target="_blank"
                    style="color:var(--brand);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($ep['sitio_web']) ?></a>
                </div>
              <?php endif; ?>
              <div class="pc-row"><span class="pc-row-ico">📅</span><span><?= $fechaRegistro ?></span></div>
              <?php if (!empty($badgesHTML)): ?>
                <div style="margin-top:6px"><?= $badgesHTML ?></div><?php endif; ?>
            </div>
            <div class="prog-wrap">
              <div class="prog-header"><span>Perfil completado</span><span id="pctLabel"><?= $pct ?>%</span></div>
              <div class="prog-track">
                <div class="prog-fill" id="progBar" style="width:0%"></div>
              </div>
            </div>
            <div class="vis-row">
              <div>
                <div class="vis-label">Visible en directorio</div>
                <div class="vis-sub">Los talentos pueden encontrarte</div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <span id="visChip"
                  class="pv-chip <?= $visibleEnWeb ? 'ok' : 'off' ?>"><?= $visibleEnWeb ? '🟢 Visible' : '🟡 Oculto' ?></span>
                <label class="tog">
                  <input type="checkbox" id="toggleVisible" <?= $visibleEnWeb ? 'checked' : '' ?>
                    onchange="toggleVisibilidad(this.checked)">
                  <span class="tog-sl"></span>
                </label>
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
            <a href="javascript:void(0)" class="action-item" onclick="abrirModalVacante()"
              style="border-color:rgba(26,86,219,.25);background:rgba(26,86,219,.04)">
              <span class="ai-ico">➕</span><span class="ai-label" style="color:var(--brand)">Publicar vacante</span>
            </a>
            <a href="talentos.php" class="action-item"><span class="ai-ico">🌟</span><span
                class="ai-label">Talentos</span></a>
            <a href="empresas.php" class="action-item"><span class="ai-ico">🏢</span><span class="ai-label">Mi
                empresa</span></a>
            <a href="chat.php" class="action-item">
              <span class="ai-ico">💬</span><span class="ai-label">Mensajes</span>
              <?php if ($chatNoLeidos > 0): ?><span class="ai-badge"><?= $chatNoLeidos ?></span><?php endif; ?>
            </a>
            <a href="verificar_cuenta.php" class="action-item">
              <span class="ai-ico">🪪</span><span class="ai-label">Verificación</span>
              <span class="ai-sub"><?= $tieneVerificado ? '✅ Activo' : 'Pendiente' ?></span>
            </a>
            <a href="Empleo.php" class="action-item"><span class="ai-ico">🔍</span><span class="ai-label">Ver
                empleos</span></a>
            <a href="convocatorias.php" class="action-item"><span class="ai-ico">📢</span><span
                class="ai-label">Convocatorias</span></a>
            <a href="negocios.php" class="action-item"><span class="ai-ico">🏪</span><span
                class="ai-label">Negocios</span></a>
            <a href="servicios.php" class="action-item"><span class="ai-ico">🎧</span><span
                class="ai-label">Eventos</span></a>
            <a href="Ayuda.html" class="action-item"><span class="ai-ico">❓</span><span
                class="ai-label">Ayuda</span></a>
          </div>
        </div>
      </div>

      <!-- PLAN -->
      <?php if (!empty($datosPlan)): ?>
        <?php $usados = $datosPlan['usados'] ?? [];
        $cfg = $datosPlan['config'] ?? []; ?>
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <div class="card-title">⭐ Plan <?= htmlspecialchars($datosPlan['nombre'] ?? 'Semilla') ?></div>
              <a href="empresas.php#precios" class="btn-primary" style="padding:7px 16px;font-size:12px">✦ Mejorar
                plan</a>
            </div>
            <div class="plan-grid">
              <?php foreach (['mensajes' => ['💬', 'Mensajes'], 'vacantes' => ['💼', 'Vacantes']] as $key => [$ico, $label]):
                $lim = $cfg[$key] ?? 0;
                if (!$lim)
                  continue;
                $usado = $usados[$key] ?? 0;
                $esInf = ($lim === -1);
                $pctBar = $esInf ? 12 : min(100, ($usado / max(1, $lim)) * 100);
                $fillCls = $pctBar >= 90 ? 'high' : ($pctBar >= 70 ? 'mid' : 'low');
                ?>
                <div class="plan-bar">
                  <div class="pb-label"><?= $ico ?>     <?= $label ?></div>
                  <div class="pb-count"><?= $usado ?><span> / <?= $esInf ? '∞' : $lim ?></span></div>
                  <div class="pb-track">
                    <div class="pb-fill <?= $fillCls ?>" style="width:<?= $pctBar ?>%"></div>
                  </div>
                  <?php if (!$esInf && $pctBar >= 70): ?>
                    <div class="pb-warn"><?= $pctBar >= 90 ? '⚠️ Límite alcanzado' : '⚡ Casi en el límite' ?></div><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- HISTORIAL VACANTES -->
      <div class="col-8">
        <div class="card">
          <div class="card-header">
            <div class="card-title">📋 Historial de vacantes</div>
            <a href="javascript:void(0)" class="card-link" onclick="abrirModalVacante()">Publicar nueva →</a>
          </div>
          <?php if (!empty($historialVacantes)): ?>
            <div class="job-list" id="historialVacantes">
              <?php foreach ($historialVacantes as $v):
                $horas = (time() - strtotime($v['creado_en'])) / 3600;
                $esPen = !$v['activo'] && $horas < 72;
                $dotCls = $v['activo'] ? 'act' : ($esPen ? 'pen' : 'cer');
                ?>
                <div class="job-item" id="vac-<?= $v['id'] ?>">
                  <div class="job-dot <?= $dotCls ?>"></div>
                  <div class="job-info">
                    <div class="job-name"><?= htmlspecialchars($v['titulo']) ?></div>
                    <div class="job-meta">
                      <?= htmlspecialchars($v['modalidad'] ?? '') ?> ·
                      <?= $v['activo'] ? '<span style="color:#22c55e;font-weight:700">Activa</span>' : '<span style="color:#f59e0b;font-weight:600">En revisión</span>' ?>
                    </div>
                  </div>
                  <div class="job-date"><?= date('d/m/Y', strtotime($v['creado_en'])) ?></div>
                  <button onclick="eliminarVacante(<?= $v['id'] ?>, this)" class="job-del" title="Eliminar">🗑</button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="job-empty">
              <div class="job-empty-ico">💼</div>
              <div class="job-empty-txt">Aún no has publicado vacantes</div>
              <button class="btn-primary" onclick="abrirModalVacante()" style="margin-top:10px">➕ Publicar primera
                vacante</button>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ACTIVIDAD RECIENTE -->
      <div class="col-4">
        <div class="card">
          <div class="card-header">
            <div class="card-title">📌 Actividad reciente</div>
          </div>
          <div class="activity-list">
            <div class="act-item">
              <div class="act-dot"></div>
              <div class="act-txt"><strong>Cuenta empresarial creada</strong></div>
              <div class="act-time"><?= $fechaRegistro ?></div>
            </div>
            <div class="act-item">
              <div class="act-dot"></div>
              <div class="act-txt">Exploraste talentos del Chocó</div>
              <div class="act-time">Hoy</div>
            </div>
            <?php if ($tieneVerificado): ?>
              <div class="act-item">
                <div class="act-dot" style="background:#22c55e"></div>
                <div class="act-txt"><strong>✅ Empresa verificada</strong></div>
                <div class="act-time">—</div>
              </div>
            <?php endif; ?>
            <?php if ($vacantesActivas > 0): ?>
              <div class="act-item">
                <div class="act-dot" style="background:var(--brand)"></div>
                <div class="act-txt"><?= $vacantesActivas ?> vacante<?= $vacantesActivas > 1 ? 's' : '' ?>
                  activa<?= $vacantesActivas > 1 ? 's' : '' ?></div>
                <div class="act-time">—</div>
              </div>
            <?php endif; ?>
            <?php if (empty($historialVacantes)): ?>
              <div style="text-align:center;padding:20px 10px;color:var(--ink4);font-size:13px">
                <div style="font-size:24px;margin-bottom:6px">📋</div>
                Sin actividad reciente
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- BADGES -->
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <div class="card-title">🏆 Badges activos</div>
          </div>
          <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
            <?php if ($tieneTop): ?><span
                style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5">👑
                Top</span><?php endif; ?>
            <?php if ($tienePremium): ?><span
                style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;background:#fffbeb;color:#92400e;border:1px solid #fcd34d">⭐
                Premium</span><?php endif; ?>
            <?php if ($tieneDestacado): ?><span
                style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd">🏅
                Destacada</span><?php endif; ?>
            <?php if ($tieneVerificado): ?><span
                style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;background:#dbeafe;color:#1e40af;border:1px solid #93c5fd">✓
                Verificada</span><?php endif; ?>
            <?php if (!$tieneVerificado && !$tienePremium && !$tieneDestacado && !$tieneTop): ?>
              <div style="font-size:13px;color:var(--ink3)">Aún no tienes badges. <a href="verificar_cuenta.php"
                  style="color:var(--brand);font-weight:700">Verifica tu empresa →</a></div>
            <?php endif; ?>
            <div
              style="margin-left:auto;padding:12px 16px;background:var(--brand-light);border-radius:10px;border:1px solid var(--brand-mid);font-size:12px;color:#1e40af">
              <strong>¿Quieres más visibilidad?</strong> Mejora tu plan desde <a href="empresas.php#precios"
                style="color:var(--brand);font-weight:700">Verde Selva</a> para obtener badges de pago.
            </div>
          </div>
        </div>
      </div>

    </div><!-- /dashboard-grid -->

    <!-- CROP BANNER MODAL -->
    <div
      style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:99999;align-items:center;justify-content:center;padding:20px"
      id="cropBannerModal">
      <div
        style="background:#fff;border-radius:20px;padding:26px;max-width:700px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.5)">
        <div style="font-size:16px;font-weight:800;color:var(--brand);margin-bottom:14px;text-align:center">🖼️ Encuadra
          el banner de tu empresa</div>
        <div
          style="position:relative;width:100%;height:220px;overflow:hidden;border-radius:12px;background:#000;display:flex;align-items:center;justify-content:center">
          <img id="cropBannerImg" style="max-width:100%;display:block">
        </div>
        <p style="font-size:12px;color:#64748b;text-align:center;margin:10px 0">Arrastra y haz zoom para encuadrar ·
          Proporción 4:1</p>
        <div style="display:flex;gap:10px;margin-top:6px">
          <button onclick="cancelarCropBanner()"
            style="flex:1;padding:11px;border-radius:10px;border:1px solid #e0e0e0;background:#f5f5f5;font-size:13px;font-weight:700;cursor:pointer;color:#546e7a">Cancelar</button>
          <button onclick="confirmarCropBanner()" id="btnConfirmarCropBanner"
            style="flex:2;padding:11px;border-radius:10px;border:none;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;font-size:13px;font-weight:800;cursor:pointer">✅
            Usar este banner</button>
        </div>
      </div>
    </div>

    <!-- MODAL EDITAR EMPRESA -->
    <div class="modal-ov" id="modalEditar">
      <div class="modal-box wide">
        <button class="mcerrar" onclick="cerrarModal()">✕</button>
        <div class="modal-pad">
          <div class="mtit">✏️ Editar perfil de empresa</div>
          <p class="msub">Mantén tu información actualizada para atraer el mejor talento del Chocó.</p>
          <div class="mmsg" id="editMsg"></div>

          <!-- LOGO -->
          <div class="msec">Logo de la empresa</div>
          <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px">
            <div id="logoPreview"
              style="width:72px;height:72px;border-radius:14px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:white;overflow:hidden;flex-shrink:0;cursor:pointer;border:3px solid var(--brand-light)"
              onclick="document.getElementById('logoInput').click()">
              <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>" id="logoImgPreview"
                  style="width:100%;height:100%;object-fit:cover;border-radius:14px"><?php else: ?><span><?= $iniciales ?></span><?php endif; ?>
            </div>
            <div>
              <input type="file" id="logoInput" accept="image/jpeg,image/png,image/webp,image/svg+xml"
                style="display:none" onchange="subirLogo(this)">
              <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <button onclick="document.getElementById('logoInput').click()"
                  style="padding:8px 14px;border-radius:8px;background:var(--brand);color:white;border:none;font-size:13px;font-weight:700;cursor:pointer">🖼️
                  Cambiar logo</button>
                <button id="btnEliminarLogo" onclick="eliminarLogo()"
                  style="padding:8px 14px;border-radius:8px;background:transparent;color:#e74c3c;border:1.5px solid #e74c3c;font-size:13px;font-weight:700;cursor:pointer;<?= $logoUrl ? '' : 'display:none' ?>">🗑
                  Eliminar</button>
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
              <input type="text" id="editNombreEmp"
                value="<?= htmlspecialchars($ep['nombre_empresa'] ?: $usuario['nombre'] ?? '') ?>"
                placeholder="Ej: Tech Chocó S.A.S.">
            </div>
          </div>
          <div class="mfila">
            <div class="mgr">
              <label>Sector / Industria</label>
              <select id="editSector">
                <option value="">Selecciona un sector</option>
                <?php foreach (['Tecnología', 'Salud', 'Educación', 'Construcción & Inmobiliaria', 'Comercio & Retail', 'Servicios & Turismo', 'Finanzas & Banca', 'Agro & Medio Ambiente', 'Minería', 'Transporte & Logística', 'Gastronomía', 'Arte & Cultura', 'Otro'] as $s): ?>
                  <option value="<?= $s ?>" <?= ($ep['sector'] === $s) ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mgr">
              <label>NIT / Identificación</label>
              <input type="text" id="editNit" value="<?= htmlspecialchars($ep['nit'] ?? '') ?>"
                placeholder="900.123.456-7">
            </div>
          </div>
          <div class="mfila">
            <div class="mgr">
              <label>Ciudad principal</label>
              <input type="text" id="editCiudad" value="<?= htmlspecialchars($usuario['ciudad'] ?? '') ?>"
                placeholder="Quibdó">
            </div>
            <div class="mgr">
              <label>Municipio del Chocó</label>
              <input type="text" id="editMunicipio" value="<?= htmlspecialchars($ep['municipio'] ?? '') ?>"
                placeholder="Ej: Istmina, Condoto…">
            </div>
          </div>
          <div class="mfila">
            <div class="mgr">
              <label>Teléfono empresa</label>
              <input type="tel" id="editTelefonoEmp" value="<?= htmlspecialchars($ep['telefono_empresa'] ?? '') ?>"
                placeholder="(604) 123 4567">
            </div>
            <div class="mgr">
              <label>Sitio web</label>
              <input type="url" id="editSitioWeb" value="<?= htmlspecialchars($ep['sitio_web'] ?? '') ?>"
                placeholder="https://miempresa.com">
            </div>
          </div>
          <div class="mfila">
            <div class="mgr full">
              <label>Descripción de la empresa</label>
              <textarea id="editDescripcion" rows="4"
                placeholder="Cuéntanos qué hace tu empresa, su misión y por qué los mejores talentos del Chocó deberían trabajar con ustedes…"><?= htmlspecialchars($ep['descripcion'] ?? '') ?></textarea>
            </div>
          </div>
          <button class="btn-save" id="btnGuardar" onclick="guardarEmpresa()">💾 Guardar cambios</button>

          <!-- ZONA DE PELIGRO -->
          <div style="margin-top:32px;padding-top:24px;border-top:1px solid rgba(239,68,68,.2)">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
              <span style="font-size:15px">⚠️</span>
              <span
                style="font-size:13px;font-weight:700;color:#f87171;text-transform:uppercase;letter-spacing:.8px">Zona
                de peligro</span>
            </div>
            <p style="font-size:13px;color:var(--ink3);margin-bottom:14px;line-height:1.5">Eliminar tu cuenta es <strong
                style="color:#f87171">permanente e irreversible</strong>. Se borrarán la empresa, vacantes, mensajes y
              todos los datos asociados.</p>
            <button onclick="abrirEliminarCuenta()"
              style="padding:10px 20px;border-radius:10px;background:transparent;border:1.5px solid #e74c3c;color:#e74c3c;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s"
              onmouseover="this.style.background='rgba(231,76,60,.1)'"
              onmouseout="this.style.background='transparent'">🗑 Eliminar mi cuenta de empresa</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL PUBLICAR VACANTE -->
    <div class="modal-ov" id="modal-vacante">
      <div class="modal-box wide">
        <button class="mcerrar" onclick="cerrarModalVacante()">✕</button>
        <div class="modal-pad">
          <div class="mtit">💼 Publicar nueva vacante</div>
          <p class="msub">Tu oferta será revisada por el administrador antes de aparecer en el sitio (24–48 h).</p>
          <div id="vacante-msg" style="display:none;margin-bottom:12px"></div>
          <form id="form-vacante">
            <div class="mfila">
              <div class="mgr full">
                <label>Título del cargo *</label>
                <input name="titulo" placeholder="Ej: Asistente contable, Operario de planta…" required>
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
                <input name="salario" placeholder="Ej: $1.500.000 o A convenir">
              </div>
            </div>
            <div class="mfila">
              <div class="mgr">
                <label>Ubicación</label>
                <input name="ubicacion" value="Quibdó, Chocó">
              </div>
              <div class="mgr">
                <label>Fecha límite</label>
                <input name="vence_en" type="date">
              </div>
            </div>
            <div class="mfila">
              <div class="mgr full">
                <label>Descripción del cargo *</label>
                <textarea name="descripcion" rows="3" placeholder="¿Qué hará esta persona en el día a día?"
                  required></textarea>
              </div>
            </div>
            <div class="mfila">
              <div class="mgr full">
                <label>Requisitos y cómo postularse *</label>
                <textarea name="requisitos" rows="3"
                  placeholder="Ej: Experiencia mínima 1 año · Enviar hoja de vida a rrhh@empresa.com"
                  required></textarea>
              </div>
            </div>
            <button type="button" id="btn-vacante-enviar" onclick="enviarVacante()" class="btn-save">💼 Publicar
              vacante</button>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL ELIMINAR CUENTA -->
    <div id="modalEliminarCuenta"
      style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center;padding:20px">
      <div
        style="background:#0f172a;border:1.5px solid rgba(239,68,68,.35);border-radius:20px;padding:36px 32px;max-width:440px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.6)">
        <div style="text-align:center;margin-bottom:22px">
          <div style="font-size:48px;margin-bottom:12px">⚠️</div>
          <h3 style="font-size:20px;font-weight:800;color:#f87171;margin-bottom:8px">Eliminar cuenta permanentemente
          </h3>
          <p style="font-size:14px;color:rgba(255,255,255,.6);line-height:1.6">Esta acción no se puede deshacer. Se
            eliminarán la empresa, todas las vacantes, mensajes y datos asociados.</p>
        </div>
        <div style="margin-bottom:20px">
          <label style="display:block;font-size:13px;font-weight:600;color:rgba(255,255,255,.7);margin-bottom:8px">Para
            confirmar, escribe tu correo: <strong
              style="color:#f87171"><?= htmlspecialchars($usuario['correo']) ?></strong></label>
          <input type="text" id="inputConfirmarCuenta" placeholder="Escribe tu correo exacto"
            style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid rgba(239,68,68,.4);background:rgba(239,68,68,.06);color:white;font-size:14px;outline:none;font-family:inherit">
        </div>
        <div id="msgEliminarCuenta"
          style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px"></div>
        <div style="display:flex;gap:10px">
          <button onclick="cerrarEliminarCuenta()"
            style="flex:1;padding:12px;border-radius:10px;border:1.5px solid rgba(255,255,255,.15);background:transparent;color:rgba(255,255,255,.7);font-size:14px;font-weight:600;cursor:pointer;font-family:inherit">Cancelar</button>
          <button id="btnConfirmarEliminar" onclick="confirmarEliminarCuenta()"
            style="flex:1;padding:12px;border-radius:10px;border:none;background:#e74c3c;color:white;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit">🗑
            Sí, eliminar cuenta</button>
        </div>
      </div>
    </div>

  </main>

  <script>
    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('sidebarOverlay').classList.toggle('open') }
    function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('open') }

    window.addEventListener('DOMContentLoaded', () => {
      setTimeout(() => { const b = document.getElementById('progBar'); if (b) b.style.width = '<?= $pct ?>%' }, 300);
    });

    function abrirModal() { document.getElementById('modalEditar').classList.add('open') }
    function cerrarModal() { document.getElementById('modalEditar').classList.remove('open') }
    document.getElementById('modalEditar').addEventListener('click', e => { if (e.target === document.getElementById('modalEditar')) cerrarModal() });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { cerrarModal(); cerrarModalVacante(); cerrarEliminarCuenta() } });

    function abrirEliminarCuenta() { document.getElementById('modalEliminarCuenta').style.display = 'flex'; document.getElementById('inputConfirmarCuenta').value = ''; document.getElementById('msgEliminarCuenta').style.display = 'none' }
    function cerrarEliminarCuenta() { document.getElementById('modalEliminarCuenta').style.display = 'none' }
    document.getElementById('modalEliminarCuenta').addEventListener('click', e => { if (e.target === document.getElementById('modalEliminarCuenta')) cerrarEliminarCuenta() });

    async function confirmarEliminarCuenta() {
      const correo = document.getElementById('inputConfirmarCuenta').value.trim();
      const msg = document.getElementById('msgEliminarCuenta');
      const btn = document.getElementById('btnConfirmarEliminar');
      const m = (t, ok) => { msg.textContent = t; msg.style.cssText = `display:block;padding:10px 14px;border-radius:8px;font-size:13px;background:${ok ? 'rgba(31,157,85,.15)' : 'rgba(239,68,68,.15)'};color:${ok ? '#a7f3d0' : '#f87171'}` };
      if (!correo) { m('Escribe tu correo para confirmar.', false); return }
      btn.disabled = true; btn.textContent = '⏳ Eliminando...';
      const fd = new FormData(); fd.append('_action', 'eliminar_cuenta'); fd.append('confirmar', correo);
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
        if (j.ok) { m('✅ Cuenta eliminada. Redirigiendo...', true); setTimeout(() => { window.location.href = 'index.html' }, 2000) }
        else { m('❌ ' + (j.msg || 'Error al eliminar.'), false); btn.disabled = false; btn.textContent = '🗑 Sí, eliminar cuenta' }
      } catch (e) { m('❌ Error de conexión.', false); btn.disabled = false; btn.textContent = '🗑 Sí, eliminar cuenta' }
    }

    function mostrarMsg(t, c) { const e = document.getElementById('editMsg'); e.textContent = t; e.className = 'mmsg ' + c; e.style.display = 'block' }

    async function eliminarLogo() {
      if (!confirm('¿Eliminar el logo de la empresa?')) return;
      const msg = document.getElementById('logoMsg');
      msg.textContent = '⏳ Eliminando…'; msg.style.color = 'var(--ink3)';
      const fd = new FormData(); fd.append('_action', 'eliminar_logo');
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
        if (j.ok) {
          msg.textContent = '✅ Logo eliminado'; msg.style.color = 'var(--brand)';
          const ini = '<span><?= $iniciales ?></span>';
          ['logoPreview', 'heroAvatar', 'cpAvatar', 'cpAvatarCard', 'sidebarAvatar', 'dashAvatarImg'].forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = ini });
          document.getElementById('btnEliminarLogo').style.display = 'none';
          setTimeout(() => { msg.textContent = '' }, 2000);
        } else { msg.textContent = '❌ Error'; msg.style.color = '#e74c3c' }
      } catch (e) { msg.textContent = '❌ Error de conexión'; msg.style.color = '#e74c3c' }
    }

    async function eliminarLogoBanner() {
      if (!confirm('¿Eliminar el logo de la empresa?')) return;
      const fd = new FormData(); fd.append('_action', 'eliminar_logo');
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
        if (j.ok) { const btn = document.getElementById('btnEliminarLogoBanner'); if (btn) btn.style.display = 'none'; location.reload() }
        else { alert('❌ Error al eliminar el logo') }
      } catch (e) { alert('Error de conexión') }
    }

    async function subirLogo(input) {
      const file = input.files[0]; if (!file) return;
      const msg = document.getElementById('logoMsg');
      msg.textContent = '⏳ Subiendo logo…'; msg.style.color = 'var(--ink3)';
      const fd = new FormData(); fd.append('_action', 'subir_logo'); fd.append('logo', file);
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
        if (j.ok) {
          msg.textContent = '✅ Logo actualizado'; msg.style.color = 'var(--brand)';
          const imgTag = `<img src="${j.logo}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:14px">`;
          ['logoPreview', 'heroAvatar', 'cpAvatar', 'cpAvatarCard', 'sidebarAvatar', 'dashAvatarImg'].forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = imgTag });
          const btnEl = document.getElementById('btnEliminarLogo'); if (btnEl) btnEl.style.display = 'inline-block';
        } else { msg.textContent = '❌ ' + (j.msg || 'Error'); msg.style.color = '#e74c3c' }
      } catch (e) { msg.textContent = '❌ Error de conexión'; msg.style.color = '#e74c3c' }
      input.value = '';
    }

    async function guardarEmpresa() {
      const btn = document.getElementById('btnGuardar');
      const n = document.getElementById('editNombreEmp').value.trim();
      if (!n) { mostrarMsg('El nombre de la empresa es obligatorio.', 'error'); return }
      btn.disabled = true; btn.textContent = '⏳ Guardando…';
      const fd = new FormData();
      fd.append('_action', 'editar_empresa');
      fd.append('nombre_empresa', n);
      fd.append('sector', document.getElementById('editSector').value);
      fd.append('nit', document.getElementById('editNit').value.trim());
      fd.append('descripcion', document.getElementById('editDescripcion').value.trim());
      fd.append('sitio_web', document.getElementById('editSitioWeb').value.trim());
      fd.append('telefono_empresa', document.getElementById('editTelefonoEmp').value.trim());
      fd.append('ciudad', document.getElementById('editCiudad').value.trim());
      fd.append('municipio', document.getElementById('editMunicipio').value.trim());
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
        if (j.ok) {
          mostrarMsg('¡Perfil actualizado correctamente!', 'success');
          ['dNombreEmp', 'dNombreHero', 'dNombreCard'].forEach(id => { const el = document.getElementById(id); if (el && id === 'dNombreHero') el.innerHTML = '¡Hola, <em>' + j.nombre_empresa + '</em>!'; else if (el) el.textContent = j.nombre_empresa });
          const ds = document.getElementById('dSector'); if (ds) ds.textContent = j.sector || 'Sector no definido';
          const dc = document.getElementById('dCiudad'); if (dc) dc.textContent = j.ciudad || 'Ciudad no registrada';
          setTimeout(cerrarModal, 1600);
        } else { mostrarMsg(j.msg || 'Error al guardar.', 'error') }
      } catch (e) { mostrarMsg('Error de conexión.', 'error') }
      btn.disabled = false; btn.textContent = '💾 Guardar cambios';
    }

    async function toggleVisibilidad(visible) {
      const chip = document.getElementById('visChip');
      const fd = new FormData(); fd.append('_action', 'toggle_visibilidad'); fd.append('visible', visible ? '1' : '0');
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
        if (j.ok) { chip.textContent = visible ? '🟢 Visible' : '🟡 Oculto'; chip.className = 'pv-chip ' + (visible ? 'ok' : 'off') }
      } catch (e) { console.error(e) }
    }

    function abrirModalVacante() { document.getElementById('modal-vacante').classList.add('open'); document.getElementById('vacante-msg').style.display = 'none'; document.getElementById('form-vacante').reset() }
    function cerrarModalVacante() { document.getElementById('modal-vacante').classList.remove('open') }
    document.getElementById('modal-vacante').addEventListener('click', e => { if (e.target === document.getElementById('modal-vacante')) cerrarModalVacante() });

    async function enviarVacante() {
      const btn = document.getElementById('btn-vacante-enviar');
      const msg = document.getElementById('vacante-msg');
      const fd = new FormData(document.getElementById('form-vacante'));
      fd.append('_action', 'publicar_vacante');
      btn.disabled = true; btn.textContent = 'Enviando…'; msg.style.display = 'none';
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const d = await r.json();
        msg.style.display = 'block';
        if (d.ok) { msg.style.cssText = 'display:block;color:#15803d;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:12px'; msg.textContent = d.msg; setTimeout(() => { cerrarModalVacante(); location.reload() }, 2200) }
        else { msg.style.cssText = 'display:block;color:#dc2626;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:12px'; msg.textContent = '❌ ' + (d.msg || 'Error al enviar'); btn.disabled = false; btn.textContent = '💼 Publicar vacante' }
      } catch (e) { msg.style.cssText = 'display:block;color:#dc2626;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:700;margin-bottom:12px'; msg.textContent = '❌ Error de conexión'; btn.disabled = false; btn.textContent = '💼 Publicar vacante' }
    }

    async function eliminarVacante(id, btn) {
      if (!confirm('¿Eliminar esta vacante? Esta acción no se puede deshacer.')) return;
      const orig = btn.innerHTML; btn.disabled = true; btn.innerHTML = '⏳';
      const fd = new FormData(); fd.append('_action', 'eliminar_vacante'); fd.append('vacante_id', id);
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
        if (j.ok) { const row = document.getElementById('vac-' + id); if (row) { row.style.opacity = '0'; row.style.transform = 'translateX(20px)'; row.style.transition = '.3s'; setTimeout(() => row.remove(), 320) } }
        else { alert('❌ ' + (j.msg || 'Error al eliminar')); btn.disabled = false; btn.innerHTML = orig }
      } catch (e) { alert('❌ Error de conexión'); btn.disabled = false; btn.innerHTML = orig }
    }

    // NOTIFICACIONES
    const notifBtn = document.getElementById('navNotif');
    const notifPanel = document.getElementById('notifPanel');
    const notifDot = document.getElementById('notifDot');
    notifBtn.addEventListener('click', e => { e.stopPropagation(); notifPanel.classList.toggle('open') });
    document.addEventListener('click', e => { if (!notifBtn.contains(e.target)) notifPanel.classList.remove('open') });

    async function cargarNotificaciones() {
      try {
        const r = await fetch('api_usuario.php?action=notificaciones'); const j = await r.json();
        if (j.ok) {
          const n = j.notificaciones; let items = []; let urg = false;
          if (n.mensajes_noLeidos > 0) { urg = true; items.push(`<a href="chat.php" style="text-decoration:none;color:inherit"><div class="notif-item"><div class="notif-ico">💬</div><div><strong>${n.mensajes_noLeidos}</strong> mensaje${n.mensajes_noLeidos > 1 ? 's' : ''} sin leer<div class="notif-sub">Ir al chat</div></div></div></a>`) }
          if (n.verificacion_estado === 'pendiente') { urg = true; items.push('<div class="notif-item"><div class="notif-ico">⏳</div><div>Verificación en revisión<div class="notif-sub">Te avisamos pronto</div></div></div>') }
          else if (n.verificacion_estado === 'aprobado') { items.push('<div class="notif-item"><div class="notif-ico">✅</div><div>¡Empresa verificada!<div class="notif-sub">Ya tienes el badge</div></div></div>') }
          if (n.total_badges > 0) { items.push(`<div class="notif-item"><div class="notif-ico">🏅</div><div>Tienes <strong>${n.total_badges}</strong> badge${n.total_badges > 1 ? 's' : ''}<div class="notif-sub">¡Sigue activo!</div></div></div>`) }
          document.getElementById('notifLista').innerHTML = items.length ? items.join('') : '<div class="tnp-empty">Todo al día 🎉<br><small>No hay notificaciones nuevas</small></div>';
          notifDot.style.display = urg ? 'block' : 'none';
        }
      } catch (e) { }
    }
    cargarNotificaciones(); setInterval(cargarNotificaciones, 30000);

    // DROPDOWN AVATAR
    const dashAvatarBtn = document.getElementById('dashAvatarBtn');
    const dashDropdown = document.getElementById('dashDropdown');
    dashAvatarBtn.addEventListener('click', e => { e.stopPropagation(); dashDropdown.classList.toggle('visible'); dashAvatarBtn.classList.toggle('open') });
    document.addEventListener('click', e => { if (!dashAvatarBtn.contains(e.target) && !dashDropdown.contains(e.target)) { dashDropdown.classList.remove('visible'); dashAvatarBtn.classList.remove('open') } });

    // BANNER CROP
    let cropperBannerInstance = null;
    function subirBanner(input) {
      const file = input.files[0]; if (!file) return; input.value = '';
      const reader = new FileReader();
      reader.onload = e => {
        const img = document.getElementById('cropBannerImg'); if (cropperBannerInstance) { cropperBannerInstance.destroy(); cropperBannerInstance = null }
        img.src = e.target.result; document.getElementById('cropBannerModal').style.display = 'flex';
        img.onload = () => { cropperBannerInstance = new Cropper(img, { aspectRatio: 4, viewMode: 1, dragMode: 'move', autoCropArea: 1, cropBoxResizable: false, cropBoxMovable: false, toggleDragModeOnDblclick: false, background: false, responsive: true }) }
      }; reader.readAsDataURL(file);
    }
    function cancelarCropBanner() { document.getElementById('cropBannerModal').style.display = 'none'; if (cropperBannerInstance) { cropperBannerInstance.destroy(); cropperBannerInstance = null } }
    async function confirmarCropBanner() {
      if (!cropperBannerInstance) return;
      const btn = document.getElementById('btnConfirmarCropBanner');
      btn.textContent = '⏳ Guardando…'; btn.disabled = true;
      const msg = document.getElementById('bannerMsg');
      const canvas = cropperBannerInstance.getCroppedCanvas({ width: 1200, height: 300, imageSmoothingQuality: 'high' });
      const dataUrl = canvas.toDataURL('image/jpeg', .92);
      const zone = document.getElementById('bannerZone'); let img = document.getElementById('bannerImg');
      const ph = document.getElementById('bannerPlaceholder'); if (ph) ph.style.display = 'none';
      if (!img) { img = document.createElement('img'); img.id = 'bannerImg'; img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;position:absolute;inset:0'; zone.insertBefore(img, zone.firstChild) }
      img.src = dataUrl;
      document.getElementById('cropBannerModal').style.display = 'none'; if (cropperBannerInstance) { cropperBannerInstance.destroy(); cropperBannerInstance = null }
      canvas.toBlob(async blob => {
        const fd = new FormData(); fd.append('_action', 'subir_banner'); fd.append('banner', new File([blob], 'banner.jpg', { type: 'image/jpeg' }));
        try {
          const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
          if (!j.ok) { msg.textContent = '❌ ' + (j.msg || 'Error al subir banner'); msg.style.display = 'block' }
          else { msg.style.display = 'none'; if (!zone.querySelector('.btn-quitar-banner')) { const qbtn = document.createElement('button'); qbtn.className = 'btn-quitar-banner'; qbtn.textContent = '🗑 Quitar'; qbtn.style.cssText = 'position:absolute;top:10px;right:10px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:20px;padding:5px 12px;font-size:11px;font-weight:700;cursor:pointer;z-index:5'; qbtn.onclick = ev => { ev.stopPropagation(); eliminarBanner() }; zone.appendChild(qbtn) } }
        } catch (e) { msg.textContent = '❌ Error de conexión.'; msg.style.display = 'block' }
        btn.textContent = '✅ Usar este banner'; btn.disabled = false;
      }, 'image/jpeg', .92);
    }
    async function eliminarBanner() {
      if (!confirm('¿Quitar el banner?')) return;
      const fd = new FormData(); fd.append('_action', 'eliminar_banner');
      try {
        const r = await fetch('dashboard_empresa.php', { method: 'POST', body: fd }); const j = await r.json();
        if (j.ok) {
          const img = document.getElementById('bannerImg'); if (img) img.remove();
          const zone = document.getElementById('bannerZone'); let ph = document.getElementById('bannerPlaceholder');
          if (!ph) { ph = document.createElement('div'); ph.id = 'bannerPlaceholder'; ph.style.cssText = 'width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:rgba(255,255,255,.65)'; ph.innerHTML = '<div style="font-size:28px">🖼️</div><div style="font-size:12px;font-weight:600">Haz clic para subir el banner de tu empresa</div>'; zone.insertBefore(ph, zone.firstChild) }
          else ph.style.display = 'flex'; zone.querySelectorAll('.btn-quitar-banner,button').forEach(b => b.remove());
        }
      } catch (e) { }
    }
  </script>

  <script src="js/sesion_widget.js"></script>
</body>

</html>