<?php
// ============================================================
// empresas.php — Carga empresas de BD + perfiles activos
// ============================================================
// BUILD: v20260320001200
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Surrogate-Control: no-store");
header("X-Accel-Expires: 0");
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ── Detectar sector automáticamente ──────────────────────────
function detectarSector($sector, $descripcion)
{
  $texto = strtolower($sector . ' ' . $descripcion);

  if (preg_match('/(tecnolog|software|sistemas|informatic|digital|web|app|startup|ti |it |cloud|datos|ia |inteligencia artificial|ciberseguridad|telecomunicacion)/i', $texto))
    return 'tecnologia';

  if (preg_match('/(salud|médic|medic|clinic|hospital|farmac|laboratorio|odontolog|veterinar|eps|ips|bienestar)/i', $texto))
    return 'salud';

  if (preg_match('/(educacion|educación|colegio|escuela|universidad|instituto|academia|formacion|formación|capacitacion|capacitación|liceo)/i', $texto))
    return 'educacion';

  if (preg_match('/(construcc|inmobiliar|inmobiliaria|arquitect|ingenieria|ingeniería|infraestructura|obra|urbanismo|diseño urbano)/i', $texto))
    return 'construccion';

  if (preg_match('/(comercio|retail|tienda|almacén|almacen|distribuidora|mayorista|minorista|supermercado|ferreteria|ferreter)/i', $texto))
    return 'comercio';

  if (preg_match('/(agrícol|agricola|ganadero|mineria|minería|forestal|pesca|agropecuar|ambiental|medio ambiente|biodiversidad|ecoturismo)/i', $texto))
    return 'agro';

  if (preg_match('/(turismo|hotel|hostal|restaurante|gastronom|eventos|logistica|logística|transporte|agencia de viajes)/i', $texto))
    return 'servicios';

  if (preg_match('/(financiero|financiera|banco|cooperativa|microfinanzas|aseguradora|contabilidad|auditoria|inversiones)/i', $texto))
    return 'finanzas';

  return 'todos';
}

$dbEmpresas = [];
if (file_exists(__DIR__ . '/Php/db.php')) {
  try {
    require_once __DIR__ . '/Php/db.php';
    require_once __DIR__ . '/Php/badges_helper.php';
    $db = getDB();

    // Traer todas las empresas activas con perfil visible
    // Subquery con MAX(id) garantiza UN solo registro por usuario,
    // sin depender de GROUP BY ni UNIQUE en la BD
    $stmt = $db->query("
            SELECT u.id, u.nombre, u.ciudad, u.foto,
                   u.verificado, u.badges_custom,
                   ep.nombre_empresa, ep.sector, ep.descripcion,
                   ep.logo, ep.sitio_web, ep.telefono_empresa,
                   ep.avatar_color, ep.destacado,
                   ep.visible_admin
            FROM usuarios u
            INNER JOIN perfiles_empresa ep ON ep.id = (
                SELECT MAX(id) FROM perfiles_empresa
                WHERE usuario_id = u.id
                  AND visible = 1
                  AND visible_admin = 1
            )
            WHERE u.activo = 1
              AND u.tipo = 'empresa'
            ORDER BY ep.destacado DESC, u.verificado DESC, u.id ASC
            LIMIT 50
        ");
    $rawEmpresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dedup en PHP como failsafe adicional
    $vistos = [];
    $dbEmpresas = [];
    foreach ($rawEmpresas as $row) {
      if (!isset($vistos[$row['id']])) {
        $vistos[$row['id']] = true;
        $dbEmpresas[] = $row;
      }
    }

    // Agregar badges a cada empresa
    foreach ($dbEmpresas as &$e) {
      $badges = getBadgesUsuario($db, (int) $e['id']);
      $e['badges'] = $badges;
      $badgesExtras = array_values(array_filter($badges, fn($b) => ($b['tipo'] ?? '') !== 'verificacion'));
      $e['badges_html'] = renderBadges($badgesExtras, 'small');
      $e['tiene_verificado'] = (bool) $e['verificado'] || tieneBadge($badges, 'Verificado') || tieneBadge($badges, 'Empresa Verificada');
      $e['tiene_premium'] = tieneBadge($badges, 'Premium');
      $e['tiene_destacado'] = tieneBadge($badges, 'Destacado') || (int) ($e['destacado'] ?? 0);
      $e['tiene_top'] = tieneBadge($badges, 'Top');
    }
  } catch (Exception $e) {
    $dbEmpresas = [];
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Empresas - Quibdó Conecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap"
    rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'DM Sans', Arial, sans-serif;
      background: #f9fafb;
      color: #111;
      overflow-x: hidden;
    }

    /* NAVBAR */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 78px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 48px;
      background: #fff;
      border-bottom: 1px solid rgba(0, 0, 0, 0.08);
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
      z-index: 1000;
      transition: box-shadow 0.3s;
    }

    .navbar.abajo {
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    }

    .nav-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo-navbar {
      height: 48px;
      width: auto;
      object-fit: contain;
    }

    .nav-left {
      display: flex;
      align-items: center;
      height: 100%;
    }

    .brand {
      font-size: 22px;
      font-weight: 800;
      color: #111;
    }

    .brand span {
      color: #1f9d55;
    }

    .nav-center {
      display: flex;
      align-items: center;
      gap: 22px;
      flex: 1;
      justify-content: center;
    }

    .nav-center a {
      color: #333;
      text-decoration: none;
      font-size: 15px;
      font-weight: 500;
      padding: 6px 4px;
      position: relative;
    }

    .nav-center a::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -6px;
      width: 0%;
      height: 2px;
      background: #1f9d55;
      transition: width 0.3s;
    }

    .nav-center a:hover::after,
    .nav-center a.active::after {
      width: 100%;
    }

    .nav-center .highlight {
      background: linear-gradient(135deg, #1f9d55, #2ecc71);
      color: white !important;
      padding: 9px 20px;
      border-radius: 25px;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(31, 157, 85, 0.35);
    }

    .nav-center .highlight::after {
      display: none;
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .login {
      color: #1f9d55;
      text-decoration: none;
      font-size: 14.5px;
      font-weight: 600;
      padding: 8px 18px;
      border: 2px solid #1f9d55;
      border-radius: 30px;
      transition: all 0.3s;
    }

    .login:hover {
      background: #1f9d55;
      color: white;
    }

    .register {
      background: #1f9d55;
      color: white;
      padding: 9px 20px;
      border-radius: 25px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14.5px;
      box-shadow: 0 4px 12px rgba(31, 157, 85, 0.35);
      transition: all 0.2s;
    }

    .register:hover {
      background: #166f3d;
    }

    .hamburger {
      display: none;
      flex-direction: column;
      gap: 5px;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px;
    }

    .hamburger span {
      display: block;
      width: 26px;
      height: 2.5px;
      background: #111;
      border-radius: 4px;
      transition: all 0.3s;
    }

    .hamburger.open span:nth-child(1) {
      transform: translateY(7.5px) rotate(45deg);
    }

    .hamburger.open span:nth-child(2) {
      opacity: 0;
      transform: scaleX(0);
    }

    .hamburger.open span:nth-child(3) {
      transform: translateY(-7.5px) rotate(-45deg);
    }

    .mobile-menu {
      display: none;
      position: fixed;
      top: 78px;
      left: 0;
      width: 100%;
      background: white;
      border-bottom: 1px solid rgba(0, 0, 0, 0.08);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
      flex-direction: column;
      padding: 20px 24px;
      gap: 6px;
      z-index: 999;
      animation: slideDown 0.3s ease;
    }

    .mobile-menu.open {
      display: flex;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-12px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .mobile-menu a {
      color: #333;
      text-decoration: none;
      font-size: 16px;
      font-weight: 500;
      padding: 12px 0;
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .mobile-menu a:last-child {
      border-bottom: none;
    }

    .mobile-menu a.highlight-m {
      color: #1f9d55;
      font-weight: 700;
    }

    .mobile-auth {
      display: flex;
      gap: 12px;
      margin-top: 14px;
    }

    .mobile-auth a {
      flex: 1;
      text-align: center;
      padding: 11px;
      border-radius: 25px;
      font-weight: 600;
      font-size: 15px;
      text-decoration: none;
    }

    .mobile-auth .m-login {
      border: 2px solid #1f9d55;
      color: #1f9d55;
    }

    .mobile-auth .m-reg {
      background: #1f9d55;
      color: white;
    }

    /* HERO */
    .hero-empresa {
      padding: 150px 48px 90px;
      background: linear-gradient(135deg, #0f172a 0%, #1a2540 60%, #0f172a 100%);
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .hero-empresa::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 30% 50%, rgba(26, 86, 219, 0.18) 0%, transparent 60%), radial-gradient(ellipse at 70% 50%, rgba(59, 130, 246, 0.12) 0%, transparent 60%);
    }

    .hero-empresa-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
      margin: 0 auto;
    }

    .hero-badge {
      display: inline-block;
      background: rgba(26, 86, 219, 0.2);
      border: 1px solid rgba(59, 130, 246, 0.4);
      color: #93c5fd;
      font-size: 13px;
      font-weight: 600;
      padding: 6px 20px;
      border-radius: 30px;
      margin-bottom: 24px;
      letter-spacing: 0.6px;
    }

    .hero-empresa h1 {
      font-family: 'Syne', sans-serif;
      font-size: 58px;
      font-weight: 800;
      color: white;
      line-height: 1.1;
      margin-bottom: 20px;
    }

    .hero-empresa h1 span {
      color: #60a5fa;
    }

    .hero-empresa>div>p {
      font-size: 18px;
      color: rgba(255, 255, 255, 0.75);
      margin-bottom: 40px;
      line-height: 1.6;
    }

    /* SEARCH BAR EN HERO */
    .empresa-search {
      display: flex;
      align-items: center;
      background: white;
      border-radius: 50px;
      padding: 8px;
      max-width: 680px;
      margin: 0 auto 36px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.28);
    }

    .empresa-search .sf {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 18px;
      flex: 1;
    }

    .empresa-search .sf .icon {
      font-size: 18px;
      opacity: 0.5;
    }

    .empresa-search input {
      border: none;
      outline: none;
      font-size: 15px;
      width: 100%;
      font-family: 'DM Sans', sans-serif;
      color: #222;
      background: transparent;
    }

    .empresa-search .divider {
      width: 1px;
      height: 34px;
      background: rgba(0, 0, 0, 0.1);
      flex-shrink: 0;
    }

    .empresa-search button {
      background: linear-gradient(135deg, #1a56db, #3b82f6);
      color: white;
      border: none;
      border-radius: 40px;
      padding: 14px 28px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: opacity 0.2s, transform 0.2s;
      white-space: nowrap;
    }

    .empresa-search button:hover {
      opacity: 0.9;
      transform: scale(1.03);
    }

    .hero-links {
      display: flex;
      justify-content: center;
      gap: 24px;
      flex-wrap: wrap;
    }

    .hero-link {
      color: rgba(255, 255, 255, 0.7);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      border-bottom: 1px solid rgba(255, 255, 255, 0.25);
      padding-bottom: 2px;
      transition: color 0.2s;
    }

    .hero-link:hover {
      color: #93c5fd;
      border-color: #93c5fd;
    }

    /* STATS */
    .stats-band {
      background: white;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      text-align: center;
      padding: 50px 48px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .stats-band .s h3 {
      font-family: 'Syne', sans-serif;
      font-size: 38px;
      font-weight: 800;
      color: #1a56db;
    }

    .stats-band .s p {
      font-size: 13px;
      color: #888;
      margin-top: 5px;
      font-weight: 500;
    }

    /* CATEGORÍAS / SECTORES */
    .categorias {
      padding: 80px 48px;
      background: #f9fafb;
      text-align: center;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .categorias h2 {
      font-family: 'Syne', sans-serif;
      font-size: 34px;
      margin-bottom: 10px;
    }

    .categorias .sub {
      color: #666;
      font-size: 15px;
      margin-bottom: 48px;
    }

    .categorias-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      justify-content: center;
      max-width: 1100px;
      margin: 0 auto;
    }

    .cat-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      background: white;
      border: 2px solid rgba(0, 0, 0, 0.07);
      border-radius: 50px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 600;
      color: #444;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.25s;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .cat-btn:hover,
    .cat-btn.activa {
      background: #eff6ff;
      border-color: #1a56db;
      color: #1a56db;
      box-shadow: 0 6px 20px rgba(26, 86, 219, 0.15);
    }

    .cat-btn .count {
      font-size: 11px;
      background: #f1f5f9;
      border-radius: 10px;
      padding: 2px 8px;
      color: #888;
      font-weight: 600;
    }

    .cat-btn.activa .count {
      background: rgba(26, 86, 219, 0.12);
      color: #1a56db;
    }

    /* FILTROS TIPO */
    .filtros-tipo {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 24px;
    }

    .filtro-btn {
      background: white;
      border: 1px solid rgba(0, 0, 0, 0.12);
      color: #444;
      padding: 8px 18px;
      border-radius: 25px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.2s;
    }

    .filtro-btn:hover,
    .filtro-btn.activo {
      background: #1a56db;
      border-color: #1a56db;
      color: white;
    }

    /* EMPRESAS SECTION */
    .empresas-section {
      padding: 80px 48px;
      background: white;
    }

    .empresas-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      flex-wrap: wrap;
      gap: 12px;
    }

    .empresas-header h2 {
      font-family: 'Syne', sans-serif;
      font-size: 34px;
    }

    .resultados-count {
      font-size: 14px;
      color: #888;
      font-weight: 500;
    }

    .empresas-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 24px;
      margin-top: 32px;
    }

    /* EMPRESA CARD */
    .empresa-card {
      background: #fafafa;
      border: 1px solid rgba(0, 0, 0, 0.07);
      border-radius: 20px;
      padding: 28px;
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
      cursor: pointer;
    }

    .empresa-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 44px rgba(0, 0, 0, 0.11);
      background: white;
      border-color: transparent;
    }

    .empresa-card .badge-e {
      position: absolute;
      top: 18px;
      right: 18px;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 20px;
      background: #eff6ff;
      color: #1a56db;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .badge-verificado-principal {
      background: #dbeafe !important;
      color: #1e40af !important;
      border: 1px solid #93c5fd !important;
    }

    .empresa-avatar {
      width: 68px;
      height: 68px;
      border-radius: 14px;
      background: linear-gradient(135deg, #1a56db, #3b82f6);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      margin-bottom: 16px;
      font-weight: 800;
      color: white;
      flex-shrink: 0;
    }

    .empresa-card h3 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .empresa-card .sector-label {
      color: #1a56db;
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 4px;
    }

    .empresa-card .ubicacion {
      font-size: 13px;
      color: #999;
      margin-bottom: 14px;
    }

    .empresa-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 7px;
      margin-bottom: 18px;
    }

    .tag {
      background: #f1f5f9;
      color: #444;
      font-size: 12px;
      padding: 4px 11px;
      border-radius: 20px;
      font-weight: 500;
    }

    .btn-perfil {
      display: block;
      text-align: center;
      background: transparent;
      border: 2px solid #1a56db;
      color: #1a56db;
      padding: 10px;
      border-radius: 25px;
      font-weight: 600;
      font-size: 14px;
      text-decoration: none;
      cursor: pointer;
      width: 100%;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.3s;
    }

    .btn-perfil:hover {
      background: #1a56db;
      color: white;
    }

    /* NO RESULTS */
    .no-results {
      grid-column: 1/-1;
      text-align: center;
      padding: 60px 20px;
      color: #999;
    }

    .no-results .nr-icon {
      font-size: 52px;
      display: block;
      margin-bottom: 14px;
    }

    /* ── MODAL PERFIL EMPRESA (profesional) ─────────────────── */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      padding: 20px;
      backdrop-filter: blur(6px);
    }

    .modal-overlay.open { display: flex; }

    .modal-box {
      background: #fff;
      border-radius: 24px;
      max-width: 620px;
      width: 100%;
      box-shadow: 0 40px 100px rgba(0, 0, 0, 0.25);
      animation: fadeUp 0.32s cubic-bezier(.22,.68,0,1.2) both;
      position: relative;
      max-height: 90vh;
      overflow-y: auto;
      overflow-x: hidden;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(28px) scale(.97) }
      to   { opacity: 1; transform: translateY(0)  scale(1) }
    }

    /* Cover */
    .modal-cover {
      height: 120px;
      border-radius: 24px 24px 0 0;
      background: linear-gradient(135deg, #1a56db, #3b82f6);
      position: relative;
    }

    /* Close */
    .modal-close {
      position: absolute;
      top: 14px;
      right: 16px;
      background: rgba(255,255,255,0.2);
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      font-size: 15px;
      cursor: pointer;
      color: white;
      z-index: 10;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background .2s;
      backdrop-filter: blur(4px);
    }
    .modal-close:hover { background: rgba(255,255,255,0.35); }

    /* Avatar flotante */
    .modal-avatar-wrap {
      display: flex;
      align-items: flex-end;
      gap: 12px;
      padding: 0 28px;
      margin-top: -44px;
      margin-bottom: 14px;
      position: relative;
      z-index: 2;
    }

    .modal-avatar {
      width: 80px;
      height: 80px;
      border-radius: 18px;
      background: linear-gradient(135deg, #1a56db, #3b82f6);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 800;
      color: white;
      flex-shrink: 0;
      border: 4px solid white;
      box-shadow: 0 4px 20px rgba(0,0,0,0.15);
      overflow: hidden;
    }

    .modal-badge-e {
      display: inline-flex;
      align-items: center;
      padding: 5px 13px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    /* Body */
    .modal-body {
      padding: 4px 28px 28px;
    }

    .modal-top-info {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 10px;
      flex-wrap: wrap;
    }

    .modal-nombre {
      font-family: 'Syne', sans-serif;
      font-size: 22px;
      font-weight: 800;
      color: #0f172a;
      line-height: 1.15;
      margin: 0 0 5px;
      letter-spacing: -.4px;
    }

    .modal-meta {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13.5px;
      color: #64748b;
      flex-wrap: wrap;
    }

    .modal-meta span:first-child { color: #1a56db; font-weight: 600; }
    .meta-sep { color: #cbd5e1; }

    .btn-ver-perfil {
      flex-shrink: 0;
      padding: 8px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 25px;
      font-size: 13px;
      font-weight: 700;
      color: #334155;
      text-decoration: none;
      transition: all .2s;
      white-space: nowrap;
    }
    .btn-ver-perfil:hover {
      border-color: #1a56db;
      color: #1a56db;
      background: #eff6ff;
    }

    .modal-desc {
      font-size: 14px;
      color: #475569;
      line-height: 1.7;
      margin: 0 0 20px;
    }

    /* Tabs */
    .modal-tabs {
      display: flex;
      gap: 4px;
      background: #f1f5f9;
      border-radius: 12px;
      padding: 4px;
      margin-bottom: 18px;
    }

    .modal-tab {
      flex: 1;
      padding: 9px;
      border: none;
      border-radius: 9px;
      background: transparent;
      font-size: 13px;
      font-weight: 600;
      color: #64748b;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all .2s;
    }
    .modal-tab.active {
      background: white;
      color: #0f172a;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    /* Panels */
    .modal-panel { min-height: 80px; }

    /* Loading spinner */
    .conv-loading {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 20px 0;
      color: #64748b;
      font-size: 14px;
    }
    .conv-spinner {
      width: 18px; height: 18px;
      border: 2px solid #e2e8f0;
      border-top-color: #1a56db;
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg) } }

    /* Lista convocatorias */
    .conv-lista { display: flex; flex-direction: column; gap: 10px; }

    .conv-item {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 14px 16px;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 14px;
      text-decoration: none;
      transition: all .2s;
    }
    .conv-item:hover {
      border-color: #1a56db;
      background: #eff6ff;
      transform: translateX(3px);
    }
    .conv-icon {
      width: 40px; height: 40px;
      background: linear-gradient(135deg, #eff6ff, #dbeafe);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
    }
    .conv-info { flex: 1; min-width: 0; }
    .conv-titulo {
      font-size: 14px;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 3px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .conv-meta {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #64748b;
      flex-wrap: wrap;
    }
    .conv-tag {
      background: #e0f2fe;
      color: #0369a1;
      padding: 2px 8px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 11px;
    }
    .conv-arrow {
      color: #94a3b8;
      font-size: 16px;
      flex-shrink: 0;
      align-self: center;
    }

    /* Empty state */
    .conv-empty {
      text-align: center;
      padding: 32px 20px;
      color: #94a3b8;
    }
    .conv-empty span { font-size: 36px; display: block; margin-bottom: 8px; }
    .conv-empty p { font-size: 14px; margin: 0; }

    /* Info grid */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .info-item {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px 14px;
    }
    .info-item .i-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .5px;
      color: #94a3b8;
      margin-bottom: 4px;
    }
    .info-item .i-val {
      font-size: 14px;
      font-weight: 600;
      color: #0f172a;
    }

    /* CTA */
    .modal-cta {
      display: block;
      width: 100%;
      margin-top: 20px;
      padding: 14px;
      background: linear-gradient(135deg, #1a56db, #3b82f6);
      color: white;
      border-radius: 14px;
      font-size: 15px;
      font-weight: 700;
      text-align: center;
      text-decoration: none;
      font-family: 'DM Sans', sans-serif;
      box-shadow: 0 6px 20px rgba(26, 86, 219, 0.35);
      transition: transform .2s, box-shadow .2s;
    }
    .modal-cta:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(26,86,219,.45); }

    @media(max-width:600px) {
      .modal-box { border-radius: 20px; }
      .modal-body { padding: 4px 18px 22px; }
      .modal-avatar-wrap { padding: 0 18px; }
      .info-grid { grid-template-columns: 1fr; }
    }

    /* CTA EMPRESA */
    .cta-empresa {
      padding: 90px 48px;
      background: linear-gradient(135deg, #0f172a, #1a2540);
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .cta-empresa::before {
      content: '';
      position: absolute;
      top: -80px;
      left: -80px;
      width: 450px;
      height: 450px;
      background: radial-gradient(circle, rgba(26, 86, 219, 0.12) 0%, transparent 70%);
      pointer-events: none;
    }

    .cta-empresa-inner {
      position: relative;
      z-index: 2;
      max-width: 1100px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
    }

    .cta-texto h2 {
      font-family: 'Syne', sans-serif;
      font-size: 40px;
      color: white;
      line-height: 1.15;
      margin-bottom: 18px;
    }

    .cta-texto h2 span {
      color: #60a5fa;
    }

    .cta-texto p {
      color: rgba(255, 255, 255, 0.7);
      font-size: 16px;
      line-height: 1.7;
      margin-bottom: 28px;
    }

    .cta-beneficios {
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-bottom: 32px;
    }

    .beneficio {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255, 255, 255, 0.85);
      font-size: 14px;
      font-weight: 500;
    }

    .beneficio span:first-child {
      font-size: 20px;
    }

    .btn-azul {
      background: linear-gradient(135deg, #1a56db, #3b82f6);
      color: white;
      padding: 14px 32px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 600;
      font-size: 15px;
      box-shadow: 0 6px 20px rgba(26, 86, 219, 0.4);
      transition: transform 0.2s;
      display: inline-block;
    }

    .btn-azul:hover {
      transform: translateY(-2px);
    }

    .cta-cards {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .cta-card {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 20px 22px;
      display: flex;
      align-items: center;
      gap: 16px;
      transition: all 0.3s;
    }

    .cta-card:hover {
      background: rgba(26, 86, 219, 0.12);
      border-color: rgba(59, 130, 246, 0.3);
      transform: translateX(6px);
    }

    .cta-card-icon {
      width: 50px;
      height: 50px;
      border-radius: 14px;
      background: linear-gradient(135deg, #1a56db, #3b82f6);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }

    .cta-card-info h4 {
      color: white;
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 2px;
    }

    .cta-card-info p {
      color: rgba(255, 255, 255, 0.55);
      font-size: 13px;
      margin: 0;
    }

    /* FINAL CTA */
    .final-cta {
      padding: 90px 48px;
      background: linear-gradient(135deg, #0f172a, #1a2e1a);
      text-align: center;
      color: white;
    }

    .final-cta h2 {
      font-family: 'Syne', sans-serif;
      font-size: 38px;
      margin-bottom: 14px;
    }

    .final-cta p {
      color: rgba(255, 255, 255, 0.7);
      font-size: 16px;
      max-width: 520px;
      margin: 0 auto 40px;
      line-height: 1.6;
    }

    .cta-btns {
      display: flex;
      justify-content: center;
      gap: 18px;
      flex-wrap: wrap;
    }

    .btn-outline-w {
      border: 2px solid rgba(255, 255, 255, 0.35);
      color: white;
      padding: 14px 32px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 600;
      font-size: 15px;
      transition: all 0.3s;
    }

    .btn-outline-w:hover {
      border-color: #60a5fa;
      color: #60a5fa;
    }

    /* FOOTER */
    footer {
      background: #0f172a;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      color: rgba(255, 255, 255, 0.5);
      text-align: center;
      padding: 28px 48px;
      font-size: 14px;
    }

    footer span {
      color: #60a5fa;
    }

    /* SCROLL REVEAL */
    .reveal {
      opacity: 0;
      transform: translateY(36px);
      transition: opacity .65s ease, transform .65s ease;
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    /* RESPONSIVE */
    @media(max-width: 1200px) {
      .hero-empresa h1 {
        font-size: 48px;
      }

      .navbar {
        padding: 0 32px;
      }
    }

    @media(max-width: 900px) {
      .hero-empresa {
        padding: 130px 32px 80px;
      }

      .hero-empresa h1 {
        font-size: 40px;
      }

      .cta-empresa-inner {
        grid-template-columns: 1fr;
        gap: 40px;
      }
    }

    @media(max-width: 768px) {
      .navbar {
        padding: 0 20px;
      }

      .nav-center,
      .nav-right {
        display: none;
      }

      .hamburger {
        display: flex;
      }

      .hero-empresa {
        padding: 110px 20px 70px;
      }

      .hero-empresa h1 {
        font-size: 30px;
        line-height: 1.15;
      }

      .empresa-search {
        flex-wrap: wrap;
        border-radius: 18px;
        padding: 10px;
      }

      .empresa-search .sf {
        width: 100%;
      }

      .empresa-search .divider {
        width: 100%;
        height: 1px;
      }

      .empresa-search button {
        width: 100%;
        border-radius: 12px;
      }

      .stats-band {
        padding: 36px 20px;
      }

      .categorias,
      .empresas-section,
      .final-cta {
        padding: 60px 20px;
      }

      .cta-empresa {
        padding: 60px 20px;
      }

      .cta-texto h2 {
        font-size: 26px;
      }

      .empresas-grid {
        grid-template-columns: 1fr;
      }

      .modal-header {
        flex-direction: column;
        gap: 14px;
      }

      .hero-links {
        gap: 14px;
      }
    }

    @media(max-width: 600px) {
      .hero-empresa h1 {
        font-size: 26px;
      }

      .stats-band {
        grid-template-columns: 1fr 1fr;
      }

      .categorias-grid {
        flex-direction: column;
      }

      .cat-btn {
        justify-content: center;
      }
    }

    @media(max-width: 480px) {
      .hero-empresa h1 {
        font-size: 22px;
      }

      .hero-badge {
        font-size: 11px;
      }

      .cta-btns {
        flex-direction: column;
        align-items: center;
      }

      .btn-azul,
      .btn-outline-w {
        width: 100%;
        text-align: center;
      }

      .empresas-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
    }
  </style>
</head>

<body>

  <!-- NAVBAR -->
  <header class="navbar" id="navbar">
    <div class="nav-left">
      <img src="Imagenes/quibdo_desco_new.png" alt="Quibdó Conecta" class="logo-navbar">
    </div>
    <nav class="nav-center">
      <a href="index.html">Inicio</a>
      <a href="Empleo.php">Empleos</a>
      <a href="talentos.php">Talento</a>
      <a href="empresas.php" class="active">Empresas</a>
      <a href="buscar.php" class="highlight">🔍 Buscar</a>
      <a href="Ayuda.html">Ayuda</a>
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
    <a href="Empleo.php">💼 Empleos</a>
    <a href="talentos.php">🌟 Talento</a>
    <a href="empresas.php">🏢 Empresas</a>
    <a href="buscar.php">🔍 Buscar</a>
    <a href="Ayuda.html">❓ Ayuda</a>
    <div class="mobile-auth">
      <a href="inicio_sesion.php" class="m-login">Iniciar sesión</a>
      <a href="registro.php" class="m-reg">Registrarse</a>
    </div>
  </div>

  <!-- HERO -->
  <section class="hero-empresa">
    <div class="hero-empresa-content reveal">
      <span class="hero-badge">🏢 +120 empresas registradas</span>
      <h1>Las <span>empresas</span> del Chocó que generan oportunidades</h1>
      <p>Conoce las empresas activas de la región, sus vacantes abiertas y cómo conectar con ellas para hacer crecer tu
        carrera.</p>
      <div class="empresa-search">
        <div class="sf">
          <span class="icon">🔍</span>
          <input type="text" id="searchNombre" placeholder="Nombre de empresa o sector…" autocomplete="off">
        </div>
        <div class="divider"></div>
        <div class="sf">
          <span class="icon">📍</span>
          <input type="text" id="searchUbicacion" placeholder="Ciudad (ej. Quibdó)">
        </div>
        <button id="searchBtn">Buscar empresa</button>
      </div>
      <div class="hero-links">
        <a href="registro.php" class="hero-link">✨ Registrar mi empresa gratis</a>
        <a href="#empresas" class="hero-link">👇 Ver todas las empresas</a>
      </div>
    </div>
  </section>

  <!-- STATS (datos reales desde BD) -->
  <div class="stats-band" id="statsBand">
    <div class="s reveal">
      <h3 id="stat-empresas">+120</h3>
      <p>Empresas registradas</p>
    </div>
    <div class="s reveal">
      <h3 id="stat-vacantes">+300</h3>
      <p>Vacantes publicadas</p>
    </div>
    <div class="s reveal">
      <h3 id="stat-talentos">+500</h3>
      <p>Talentos disponibles</p>
    </div>
    <div class="s reveal">
      <h3>+50</h3>
      <p>Municipios del Chocó</p>
    </div>
    <div class="s reveal">
      <h3 id="stat-satisfaccion">98%</h3>
      <p>Empresas satisfechas</p>
    </div>
  </div>

  <!-- SECTORES -->
  <section class="categorias">
    <h2 class="reveal">Filtra por sector</h2>
    <p class="sub">Encuentra empresas según el sector económico que necesitas</p>
    <div class="categorias-grid" id="catGrid">
      <button class="cat-btn activa" data-cat="todos">🌐 Todos <span class="count"
          id="cnt-todos"><?= count($dbEmpresas) ?></span></button>
      <button class="cat-btn" data-cat="tecnologia">💻 Tecnología <span class="count" id="cnt-tec">0</span></button>
      <button class="cat-btn" data-cat="salud">🏥 Salud <span class="count" id="cnt-sal">0</span></button>
      <button class="cat-btn" data-cat="educacion">📚 Educación <span class="count" id="cnt-edu">0</span></button>
      <button class="cat-btn" data-cat="construccion">🏗️ Construcción <span class="count"
          id="cnt-con">0</span></button>
      <button class="cat-btn" data-cat="comercio">🛒 Comercio <span class="count" id="cnt-com">0</span></button>
      <button class="cat-btn" data-cat="servicios">🎯 Servicios <span class="count" id="cnt-ser">0</span></button>
      <button class="cat-btn" data-cat="finanzas">💰 Finanzas <span class="count" id="cnt-fin">0</span></button>
      <button class="cat-btn" data-cat="agro">🌿 Agro &amp; Ambiente <span class="count" id="cnt-agr">0</span></button>
    </div>
    <div class="filtros-tipo" id="filtrosTipo">
      <button class="filtro-btn activo" data-tipo="todos">Todas</button>
      <button class="filtro-btn" data-tipo="verificada">✓ Verificada</button>
      <button class="filtro-btn" data-tipo="destacada">⭐ Destacada</button>
      <button class="filtro-btn" data-tipo="vacantes">Con vacantes abiertas</button>
    </div>
  </section>

  <!-- EMPRESAS -->
  <section class="empresas-section" id="empresas">
    <div class="empresas-header">
      <h2 class="reveal">Empresas del Chocó</h2>
      <span class="resultados-count" id="resCount"><?= count($dbEmpresas) ?>
        encontrada<?= count($dbEmpresas) !== 1 ? 's' : '' ?></span>
    </div>

    <div class="empresas-grid" id="empresasGrid">
      <?php if (!empty($dbEmpresas)): ?>
        <?php foreach ($dbEmpresas as $empresa):
          $nombreEmpresa = htmlspecialchars(trim($empresa['nombre_empresa'] ?: $empresa['nombre']));
          $ini = strtoupper(mb_substr($nombreEmpresa, 0, 2));
          $sec = htmlspecialchars($empresa['sector'] ?: 'Empresa local');
          $ciu = htmlspecialchars($empresa['ciudad'] ?: 'Chocó');
          $desc = htmlspecialchars($empresa['descripcion'] ?: 'Empresa del Chocó generando oportunidades para la región.');
          $grd = htmlspecialchars($empresa['avatar_color'] ?: 'linear-gradient(135deg,#1a56db,#3b82f6)');

          // Badge principal a mostrar
          if ($empresa['tiene_top'])
            $badgePrincipalLabel = '<span class="badge-e" style="background:#ff444422;color:#ff4444;border:1px solid #ff444455">👑 Top</span>';
          elseif ($empresa['tiene_premium'])
            $badgePrincipalLabel = '<span class="badge-e" style="background:#ffab0022;color:#ffab00;border:1px solid #ffab0055">⭐ Premium</span>';
          elseif ($empresa['tiene_destacado'])
            $badgePrincipalLabel = '<span class="badge-e" style="background:#aa44ff22;color:#aa44ff;border:1px solid #aa44ff55">🏅 Destacada</span>';
          elseif ($empresa['tiene_verificado'])
            $badgePrincipalLabel = '<span class="badge-e badge-verificado-principal">✓ Verificada</span>';
          else
            $badgePrincipalLabel = '';
          ?>

          <div class="empresa-card" id="u<?= $empresa['id'] ?>" data-uid="<?= $empresa['id'] ?>"
            data-cat="<?= detectarSector($empresa['sector'], $empresa['descripcion']) ?>"
            data-tipo="<?= $empresa['tiene_verificado'] ? 'verificada' : '' ?> <?= $empresa['tiene_destacado'] ? 'destacada' : '' ?>"
            data-nombre="<?= $nombreEmpresa ?>" data-sector="<?= $sec ?>" data-ubicacion="<?= $ciu ?>"
            data-desc="<?= $desc ?>" data-grad="<?= $grd ?>" data-initials="<?= $ini ?>"
            data-web="<?= htmlspecialchars($empresa['sitio_web'] ?? '') ?>"
            data-logo="<?= !empty($empresa['logo']) ? 'uploads/logos/' . htmlspecialchars($empresa['logo']) : '' ?>">

            <?= $badgePrincipalLabel ?>

            <div class="empresa-avatar" style="background:<?= $grd ?>;overflow:hidden">
              <?php if (!empty($empresa['logo'])): ?>
                <img src="uploads/logos/<?= htmlspecialchars($empresa['logo']) ?>" alt="<?= $ini ?>"
                  style="width:100%;height:100%;object-fit:cover;display:block">
              <?php else: ?>
                <?= $ini ?>
              <?php endif; ?>
            </div>
            <h3><?= $nombreEmpresa ?></h3>
            <p class="sector-label">🏷️ <?= $sec ?></p>
            <p class="ubicacion">📍 <?= $ciu ?></p>
            <?php if (!empty($empresa['badges_html'])): ?>
              <div style="margin:6px 0"><?= $empresa['badges_html'] ?></div>
            <?php endif; ?>
            <div class="empresa-tags">
              <?php
              $tags = array_filter(array_map('trim', explode(',', $empresa['sector'] ?? '')));
              foreach (array_slice($tags, 0, 3) as $tag):
                ?>
                <span class="tag"><?= htmlspecialchars($tag) ?></span>
              <?php endforeach; ?>
            </div>
            <button class="btn-perfil">Ver perfil de empresa</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (empty($dbEmpresas)): ?>
        <div class="no-results" style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#999">
          <span style="font-size:52px;display:block;margin-bottom:14px">🏢</span>
          <p style="font-size:16px;font-weight:600;color:#555;margin-bottom:8px">Aún no hay empresas registradas</p>
          <p style="font-size:14px">¡Sé el primero en registrar tu empresa!</p>
          <a href="registro.php"
            style="display:inline-block;margin-top:20px;padding:12px 28px;background:linear-gradient(135deg,#1a56db,#3b82f6);color:white;border-radius:30px;text-decoration:none;font-weight:700">✨
            Registrar mi empresa</a>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <!-- CTA REGISTRO EMPRESA -->
  <section class="cta-empresa">
    <div class="cta-empresa-inner">
      <div class="cta-texto">
        <h2 class="reveal">¿Tu empresa genera <span>oportunidades</span> en el Chocó?</h2>
        <p>Regístrate gratis, crea el perfil de tu empresa y conecta con el talento local que necesitas para crecer.</p>
        <div class="cta-beneficios">
          <div class="beneficio"><span>✅</span><span>Publica vacantes sin costo inicial</span></div>
          <div class="beneficio"><span>🌟</span><span>Accede a +500 talentos verificados</span></div>
          <div class="beneficio"><span>📊</span><span>Panel de gestión completo</span></div>
          <div class="beneficio"><span>🏆</span><span>Badge de Empresa Verificada</span></div>
        </div>
        <a href="registro.php" class="btn-azul">🏢 Registrar mi empresa gratis</a>
      </div>
      <div class="cta-cards">
        <div class="cta-card">
          <div class="cta-card-icon">📢</div>
          <div class="cta-card-info">
            <h4>Publica tus vacantes</h4>
            <p>Llega a cientos de candidatos del Chocó</p>
          </div>
        </div>
        <div class="cta-card">
          <div class="cta-card-icon">🔍</div>
          <div class="cta-card-info">
            <h4>Busca talentos activos</h4>
            <p>Filtra por profesión, habilidades y ciudad</p>
          </div>
        </div>
        <div class="cta-card">
          <div class="cta-card-icon">💬</div>
          <div class="cta-card-info">
            <h4>Chat directo</h4>
            <p>Comunícate con candidatos en tiempo real</p>
          </div>
        </div>
        <div class="cta-card">
          <div class="cta-card-icon">📊</div>
          <div class="cta-card-info">
            <h4>Gestión desde el panel</h4>
            <p>Administra todo desde tu dashboard</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="final-cta">
    <h2 class="reveal">¿Eres un talento buscando empresa?</h2>
    <p>Explora las empresas activas del Chocó y aplica a sus vacantes directamente desde la plataforma.</p>
    <div class="cta-btns">
      <a href="talentos.php" class="btn-azul">🌟 Ver talentos disponibles</a>
      <a href="Empleo.html" class="btn-outline-w">💼 Ver vacantes abiertas</a>
    </div>
  </section>

  <!-- MODAL PERFIL EMPRESA -->
  <div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">

      <!-- Cover con gradiente de la empresa -->
      <div class="modal-cover" id="mCover"></div>

      <!-- Cerrar -->
      <button class="modal-close" id="modalClose" aria-label="Cerrar">✕</button>

      <!-- Avatar flotante -->
      <div class="modal-avatar-wrap">
        <div class="modal-avatar" id="mAvatar"></div>
        <span class="modal-badge-e" id="mBadge"></span>
      </div>

      <!-- Cuerpo -->
      <div class="modal-body">

        <!-- Nombre + botón perfil -->
        <div class="modal-top-info">
          <div>
            <h2 class="modal-nombre" id="mNombre"></h2>
            <div class="modal-meta">
              <span id="mSector"></span>
              <span class="meta-sep">·</span>
              <span id="mUbicacion"></span>
            </div>
          </div>
          <a href="#" id="mBtnPerfilE" class="btn-ver-perfil">Ver perfil →</a>
        </div>

        <p class="modal-desc" id="mDesc"></p>

        <!-- Tabs -->
        <div class="modal-tabs">
          <button class="modal-tab active" data-tab="convocatorias">💼 Convocatorias</button>
          <button class="modal-tab" data-tab="info">📋 Info empresa</button>
        </div>

        <!-- Panel convocatorias -->
        <div class="modal-panel" id="panelConvocatorias">
          <div class="conv-loading" id="convLoading">
            <div class="conv-spinner"></div>
            <span>Cargando convocatorias…</span>
          </div>
          <div id="convLista" class="conv-lista"></div>
          <div id="convEmpty" class="conv-empty" style="display:none">
            <span>📭</span>
            <p>Sin convocatorias activas por ahora.</p>
          </div>
        </div>

        <!-- Panel info -->
        <div class="modal-panel" id="panelInfo" style="display:none">
          <div class="info-grid" id="mInfoGrid"></div>
        </div>

        <!-- CTA -->
        <a href="#" class="modal-cta" id="mBtnCta">🏢 Ver perfil completo</a>
      </div>
    </div>
  </div>

  <!-- ══ PLANES Y PRECIOS ══════════════════════════════════════ -->
  <section id="precios" style="background:#f8fafc;padding:72px 24px 80px;border-top:1px solid #e5e7eb">
    <div style="max-width:1100px;margin:0 auto">

      <!-- Encabezado -->
      <div style="text-align:center;margin-bottom:48px">
        <span
          style="display:inline-block;background:#e8f5ee;color:#1f6b3a;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;padding:5px 16px;border-radius:20px;margin-bottom:14px">💳
          Planes</span>
        <h2
          style="font-family:'Syne',sans-serif;font-size:clamp(26px,4vw,38px);font-weight:800;color:#111;margin-bottom:10px">
          Elige tu plan en QuibdóConecta</h2>
        <p style="font-size:15px;color:#6b7280;max-width:520px;margin:0 auto">Empieza gratis. Escala cuando necesites
          más visibilidad, conexiones y datos.</p>
      </div>

      <!-- Toggle Empresa / Candidato -->
      <div style="display:flex;justify-content:center;margin-bottom:28px">
        <div style="display:inline-flex;background:#f1f5f9;border-radius:30px;padding:4px;gap:4px">
          <button class="precio-tipo-btn" onclick="setPrecioTipo('empresa',this)"
            style="padding:9px 24px;border-radius:26px;border:none;cursor:pointer;font-size:13px;font-weight:700;font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,#1a56db,#3b82f6);color:white;transition:all .25s">
            🏢 Empresa
          </button>
          <button class="precio-tipo-btn" onclick="setPrecioTipo('candidato',this)"
            style="padding:9px 24px;border-radius:26px;border:none;cursor:pointer;font-size:13px;font-weight:700;font-family:'DM Sans',sans-serif;background:transparent;color:#666;transition:all .25s">
            👤 Candidato / Artista
          </button>
        </div>
      </div>

      <!-- Toggle Semanal / Mensual -->
      <div style="display:flex;justify-content:center;align-items:center;gap:12px;margin-bottom:40px">
        <span id="precio-lbl-semana" style="font-size:14px;font-weight:500;color:#111">Semanal</span>
        <div style="position:relative;width:48px;height:26px;cursor:pointer"
          onclick="setPrecioPeriodo(!document.getElementById('precio-track').dataset.checked)">
          <div id="precio-track" data-checked="false"
            style="width:48px;height:26px;border-radius:13px;background:#ddd;transition:background .3s;position:absolute;top:0;left:0">
          </div>
          <div id="precio-thumb"
            style="width:20px;height:20px;background:white;border-radius:50%;position:absolute;top:3px;left:3px;box-shadow:0 1px 4px rgba(0,0,0,.2);transition:transform .3s">
          </div>
        </div>
        <span id="precio-lbl-mes" style="font-size:14px;font-weight:400;color:#999">
          Mensual <span
            style="background:#dcfce7;color:#166534;font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:4px">Ahorra
            hasta 30%</span>
        </span>
      </div>

      <!-- Grid de planes -->
      <div id="precios-grid"
        style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:20px;align-items:start"></div>

      <!-- Nota al pie -->
      <p style="text-align:center;margin-top:32px;font-size:13px;color:#9ca3af">
        Pagos seguros · Sin contratos · Cancela cuando quieras ·
        <a href="mailto:soporte@quibdoconecta.co" style="color:#1a56db;text-decoration:none;font-weight:600">¿Dudas?
          Contáctanos</a>
      </p>
    </div>
  </section>

  <!-- FOOTER -->
  <footer>
    <p>© 2026 <span>QuibdóConecta</span> — Conectando el talento del Chocó con el mundo.</p>
  </footer>

  <script>
    // ── PLANES Y PRECIOS ────────────────────────────────────────
    (function () {
      let precioTipo = 'empresa';
      let precioPeriodo = false; // false=semanal, true=mensual

      const planesData = [
        {
          id: 'semilla', nombre: 'Semilla', icon: '🌱',
          color: '#4a7c59', bg: '#eef6f1', borderColor: 'rgba(74,124,89,0.2)',
          desc: 'Para comenzar sin costo alguno.',
          precioSemana: 0, precioMes: 0,
          candidato: [
            { ok: true, text: '2 aplicaciones a empleos/mes' },
            { ok: true, text: 'Hoja de vida completa' },
            { ok: true, text: 'Ver 5 perfiles de empresas/mes' },
            { ok: true, text: '10 mensajes de chat/mes' },
            { ok: false, text: 'Posición destacada', lock: 'Verde Selva+' },
            { ok: false, text: 'Ver quién visitó tu perfil', lock: 'Amarillo Oro+' },
          ],
          empresa: [
            { ok: true, text: '1 vacante por mes' },
            { ok: true, text: 'Ver 5 perfiles de candidatos/mes' },
            { ok: true, text: '10 mensajes de chat/mes' },
            { ok: false, text: 'Logo en perfil', lock: 'Verde Selva+' },
            { ok: false, text: 'Estadísticas de vacantes', lock: 'Verde Selva+' },
            { ok: false, text: 'Insignia empresa activa', lock: 'Verde Selva+' },
          ]
        },
        {
          id: 'selva', nombre: 'Verde Selva', icon: '🌿',
          color: '#1f6b3a', bg: '#e8f5ee', borderColor: 'rgba(31,107,58,0.35)',
          desc: 'Más visibilidad y conexiones reales.',
          precioSemana: 6900, precioMes: 15000, popular: true,
          candidato: [
            { ok: true, text: '4 aplicaciones a empleos/mes' },
            { ok: true, text: 'Ver 15 perfiles de empresas/mes' },
            { ok: true, text: '30 mensajes de chat/mes' },
            { ok: true, text: 'Estadísticas básicas del perfil' },
            { ok: true, text: 'Alertas de nuevos empleos' },
            { ok: false, text: 'Ver quién visitó tu perfil', lock: 'Amarillo Oro+' },
          ],
          empresa: [
            { ok: true, text: '3 vacantes por mes' },
            { ok: true, text: 'Ver 20 perfiles de candidatos/mes' },
            { ok: true, text: '50 mensajes de chat/mes' },
            { ok: true, text: 'Logo en perfil de empresa' },
            { ok: true, text: 'Insignia empresa activa' },
            { ok: true, text: 'Estadísticas básicas de vacantes' },
          ]
        },
        {
          id: 'oro', nombre: 'Amarillo Oro', icon: '✦',
          color: '#b8860b', bg: '#fdf8e8', borderColor: 'rgba(184,134,11,0.35)',
          desc: 'Datos exactos y conexiones directas.',
          precioSemana: 14900, precioMes: 35000,
          candidato: [
            { ok: true, text: 'Aplicaciones ilimitadas', star: true },
            { ok: true, text: 'Ver perfiles ilimitados', star: true },
            { ok: true, text: '100 mensajes de chat/mes' },
            { ok: true, text: 'Número exacto de visitas', star: true },
            { ok: true, text: 'Ver 3 cuentas que te visitaron', star: true },
            { ok: true, text: 'Insignia verificada', star: true },
          ],
          empresa: [
            { ok: true, text: 'Vacantes ilimitadas', star: true },
            { ok: true, text: 'Ver perfiles ilimitados', star: true },
            { ok: true, text: '200 mensajes de chat/mes' },
            { ok: true, text: 'Número exacto de visitas', star: true },
            { ok: true, text: 'Estadísticas avanzadas', star: true },
            { ok: true, text: 'Verificación legal', star: true },
          ]
        },
        {
          id: 'azul', nombre: 'Azul Profundo', icon: '◆',
          color: '#1a3f6f', bg: '#e8f0fa', borderColor: 'rgba(26,63,111,0.35)',
          desc: 'El máximo poder sin ningún límite.',
          precioSemana: 24900, precioMes: 55000,
          candidato: [
            { ok: true, text: 'Todo ilimitado', crown: true },
            { ok: true, text: 'Chat ilimitado', crown: true },
            { ok: true, text: 'Primero en búsquedas siempre', crown: true },
            { ok: true, text: 'Ver TODAS las cuentas visitantes', crown: true },
            { ok: true, text: 'Insignia oficial QuibdóConecta', crown: true },
            { ok: true, text: 'Soporte dedicado', crown: true },
          ],
          empresa: [
            { ok: true, text: 'Todo ilimitado', crown: true },
            { ok: true, text: 'Primero en búsquedas siempre', crown: true },
            { ok: true, text: 'Ver TODAS las cuentas visitantes', crown: true },
            { ok: true, text: 'Sello oficial QuibdóConecta', crown: true },
            { ok: true, text: 'Banner en inicio 7 días/mes', crown: true },
            { ok: true, text: 'Gestor de cuenta dedicado', crown: true },
          ]
        }
      ];

      function fmtPrecio(n) {
        return '$' + n.toLocaleString('es-CO');
      }

      function featureIcon(f) {
        if (!f.ok) return `<span style="color:#ccc;font-size:13px">✕</span>`;
        if (f.crown) return `<span style="color:#1a3f6f;font-size:12px">◆</span>`;
        if (f.star) return `<span style="color:#b8860b;font-size:13px">★</span>`;
        return `<span style="color:#1f6b3a;font-size:13px;font-weight:700">✓</span>`;
      }

      function renderPrecios() {
        const grid = document.getElementById('precios-grid');
        if (!grid) return;
        grid.innerHTML = planesData.map(p => {
          const features = precioTipo === 'candidato' ? p.candidato : p.empresa;
          const gratis = p.precioMes === 0;
          let precioHTML = '';
          if (gratis) {
            precioHTML = `<div style="font-size:34px;font-weight:800;color:${p.color};font-family:'Syne',sans-serif">Gratis</div><div style="font-size:12px;color:#999;margin-top:2px">para siempre</div>`;
          } else if (precioPeriodo) {
            const ahorro = (p.precioSemana * 4) - p.precioMes;
            precioHTML = `
            <div style="font-size:34px;font-weight:800;color:${p.color};font-family:'Syne',sans-serif">${fmtPrecio(p.precioMes)}</div>
            <div style="font-size:12px;color:#999;margin-top:2px">por mes</div>
            <span style="display:inline-block;background:${p.bg};color:${p.color};font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;margin-top:8px">Ahorras ${fmtPrecio(ahorro)}</span>`;
          } else {
            precioHTML = `
            <div style="font-size:34px;font-weight:800;color:${p.color};font-family:'Syne',sans-serif">${fmtPrecio(p.precioSemana)}</div>
            <div style="font-size:12px;color:#999;margin-top:2px">por semana</div>
            <span style="display:inline-block;background:${p.bg};color:${p.color};font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;margin-top:8px">o ${fmtPrecio(p.precioMes)}/mes</span>`;
          }

          const featuresHTML = features.map(f => `
          <div style="display:flex;align-items:flex-start;gap:10px;padding:7px 0;border-bottom:1px solid rgba(0,0,0,0.04)">
            <div style="width:20px;text-align:center;flex-shrink:0;margin-top:1px">${featureIcon(f)}</div>
            <span style="font-size:13px;color:${f.ok ? '#333' : '#bbb'};line-height:1.4">
              ${f.text}${f.lock ? ` <span style="font-size:11px;color:#999">(${f.lock})</span>` : ''}
            </span>
          </div>`).join('');

          const isPopular = p.popular === true;
          return `
          <div style="
            background:${isPopular ? p.color : 'white'};
            border:2px solid ${isPopular ? p.color : p.borderColor};
            border-radius:22px;
            padding:28px;
            position:relative;
            transition:transform .3s,box-shadow .3s;
            ${isPopular ? 'transform:scale(1.03);box-shadow:0 20px 50px ' + p.color + '44;' : ''}
          "
          onmouseover="this.style.transform='translateY(-6px)';this.style.boxShadow='0 16px 44px rgba(0,0,0,0.12)'"
          onmouseout="this.style.transform='${isPopular ? 'scale(1.03)' : 'none'}';this.style.boxShadow='${isPopular ? '0 20px 50px ' + p.color + '44' : 'none'}'">

            ${isPopular ? `<div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:${p.color};color:white;font-size:11px;font-weight:800;padding:4px 16px;border-radius:20px;white-space:nowrap;border:2px solid white">⭐ Más popular</div>` : ''}

            <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
              <div style="width:40px;height:40px;border-radius:10px;background:${isPopular ? 'rgba(255,255,255,0.2)' : p.bg};display:flex;align-items:center;justify-content:center;font-size:20px">${p.icon}</div>
              <div>
                <div style="font-family:'Syne',sans-serif;font-size:17px;font-weight:800;color:${isPopular ? 'white' : '#111'}">${p.nombre}</div>
                <div style="font-size:12px;color:${isPopular ? 'rgba(255,255,255,0.7)' : '#999'}">${p.desc}</div>
              </div>
            </div>

            <div style="margin-bottom:22px;padding-bottom:20px;border-bottom:1px solid ${isPopular ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.07)'}">
              ${isPopular
              ? precioHTML.replace(/color:${p.color}/g, 'color:white').replace(/color:#999/g, 'color:rgba(255,255,255,0.7)')
                .replace(new RegExp('color:' + p.color.replace('#', '\\#'), 'g'), 'color:white')
              : precioHTML}
            </div>

            <div style="margin-bottom:22px">
              ${isPopular
              ? featuresHTML
                .replace(/color:#333/g, 'color:rgba(255,255,255,0.9)')
                .replace(/color:#bbb/g, 'color:rgba(255,255,255,0.35)')
                .replace(/color:#999/g, 'color:rgba(255,255,255,0.5)')
                .replace(/border-bottom:1px solid rgba\(0,0,0,0.04\)/g, 'border-bottom:1px solid rgba(255,255,255,0.12)')
                .replace(/color:#1f6b3a/g, 'color:white')
                .replace(/color:#b8860b/g, 'color:rgba(255,255,255,0.9)')
                .replace(/color:#1a3f6f/g, 'color:white')
                .replace(/color:#ccc/g, 'color:rgba(255,255,255,0.3)')
              : featuresHTML}
            </div>

            <a href="registro.php" style="
              display:block;text-align:center;padding:12px;
              background:${isPopular ? 'white' : 'transparent'};
              border:2px solid ${isPopular ? 'white' : p.color};
              color:${isPopular ? p.color : p.color};
              border-radius:25px;font-weight:700;font-size:14px;text-decoration:none;
              font-family:'DM Sans',sans-serif;transition:all .3s
            "
            onmouseover="this.style.background='${isPopular ? 'rgba(255,255,255,0.9)' : p.color}';this.style.color='${isPopular ? p.color : 'white'}'"
            onmouseout="this.style.background='${isPopular ? 'white' : 'transparent'}';this.style.color='${p.color}'">
              ${gratis ? '✨ Comenzar gratis' : '→ Elegir ' + p.nombre}
            </a>
          </div>`;
        }).join('');
      }

      window.setPrecioTipo = function (tipo, btn) {
        precioTipo = tipo;
        document.querySelectorAll('.precio-tipo-btn').forEach(b => {
          b.style.background = 'transparent';
          b.style.color = '#666';
        });
        btn.style.background = 'linear-gradient(135deg,#1a56db,#3b82f6)';
        btn.style.color = 'white';
        renderPrecios();
      };

      window.setPrecioPeriodo = function (checked) {
        precioPeriodo = checked === true || checked === 'true';
        const track = document.getElementById('precio-track');
        const thumb = document.getElementById('precio-thumb');
        const lSem = document.getElementById('precio-lbl-semana');
        const lMes = document.getElementById('precio-lbl-mes');
        track.dataset.checked = String(precioPeriodo);
        if (precioPeriodo) {
          track.style.background = '#1a56db';
          thumb.style.transform = 'translateX(24px)';
          lSem.style.fontWeight = '400'; lSem.style.color = '#999';
          lMes.style.fontWeight = '500'; lMes.style.color = '#111';
        } else {
          track.style.background = '#ddd';
          thumb.style.transform = 'translateX(0)';
          lSem.style.fontWeight = '500'; lSem.style.color = '#111';
          lMes.style.fontWeight = '400'; lMes.style.color = '#999';
        }
        renderPrecios();
      };

      renderPrecios();
    })();
  </script>

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
      if (!ham.contains(e.target) && !mob.contains(e.target)) { ham.classList.remove('open'); mob.classList.remove('open'); }
    });

    // STATS EN TIEMPO REAL DESDE BD
    (async function () {
      try {
        const r = await fetch('stats.php');
        const d = await r.json();
        function animNum(el, target) {
          const step = Math.ceil(target / 60);
          let cur = 0;
          const iv = setInterval(() => {
            cur = Math.min(cur + step, target);
            el.textContent = '+' + cur.toLocaleString('es-CO');
            if (cur >= target) clearInterval(iv);
          }, 20);
        }
        const eE = document.getElementById('stat-empresas');
        const eV = document.getElementById('stat-vacantes');
        const eT = document.getElementById('stat-talentos');
        const eS = document.getElementById('stat-satisfaccion');
        if (eE && d.total_empresas) animNum(eE, d.total_empresas);
        if (eV && d.total_empleos) animNum(eV, d.total_empleos);
        if (eT && d.total_talentos) animNum(eT, d.total_talentos);
        if (eS && d.satisfaccion) eS.textContent = d.satisfaccion + '%';
      } catch (e) { /* mantener valores por defecto */ }
    })();

    // ALL CARDS
    const allCards = Array.from(document.querySelectorAll('.empresa-card'));

    // Calcular conteos por sector dinámicamente
    (function () {
      const map = {
        tecnologia: 'cnt-tec', salud: 'cnt-sal', educacion: 'cnt-edu',
        construccion: 'cnt-con', comercio: 'cnt-com', servicios: 'cnt-ser',
        finanzas: 'cnt-fin', agro: 'cnt-agr'
      };
      Object.entries(map).forEach(([cat, id]) => {
        const n = allCards.filter(c => c.dataset.cat === cat).length;
        const el = document.getElementById(id);
        if (el) el.textContent = n;
      });
    })();

    let catActiva = 'todos';
    let tipoActivo = 'todos';
    let textoBusqueda = '';

    function actualizarConteo(n) {
      document.getElementById('resCount').textContent = n + ' encontrada' + (n !== 1 ? 's' : '');
    }

    function aplicarFiltros() {
      let visible = 0;
      allCards.forEach(c => {
        const cat = c.dataset.cat || '';
        const tipo = c.dataset.tipo || '';
        const texto = (c.dataset.nombre + ' ' + c.dataset.sector + ' ' + c.dataset.ubicacion).toLowerCase();
        const matchCat = catActiva === 'todos' || cat === catActiva;
        const matchTipo = tipoActivo === 'todos' || tipo.includes(tipoActivo);
        const matchText = textoBusqueda === '' || texto.includes(textoBusqueda);
        const show = matchCat && matchTipo && matchText;
        c.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      actualizarConteo(visible);
      let nr = document.getElementById('noResults');
      if (!nr) {
        nr = document.createElement('div');
        nr.id = 'noResults'; nr.className = 'no-results';
        nr.innerHTML = '<span class="nr-icon">🔍</span><p>No encontramos empresas con esos criterios. Intenta con otra búsqueda.</p>';
        document.getElementById('empresasGrid').appendChild(nr);
      }
      nr.style.display = visible === 0 ? '' : 'none';
    }

    // SECTORES
    document.querySelectorAll('.cat-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('activa'));
        btn.classList.add('activa');
        catActiva = btn.dataset.cat;
        aplicarFiltros();
        document.getElementById('empresas').scrollIntoView({ behavior: 'smooth' });
      });
    });

    // FILTROS TIPO
    document.querySelectorAll('.filtro-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('activo'));
        btn.classList.add('activo');
        tipoActivo = btn.dataset.tipo;
        aplicarFiltros();
      });
    });

    // BÚSQUEDA
    async function buscar() {
      let logueado = false;
      try {
        const r = await fetch('api_usuario.php?action=perfil', { credentials: 'same-origin' });
        const d = await r.json();
        if (d.ok && d.usuario) logueado = true;
      } catch(e) {}
      if (!logueado) { abrirModalLogin(); return; }
      const nombre = document.getElementById('searchNombre').value.trim().toLowerCase();
      const ubicacion = document.getElementById('searchUbicacion').value.trim().toLowerCase();
      textoBusqueda = (nombre + ' ' + ubicacion).trim();
      aplicarFiltros();
      document.getElementById('empresas').scrollIntoView({ behavior: 'smooth' });
    }
    document.getElementById('searchBtn').addEventListener('click', buscar);
    document.getElementById('searchNombre').addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });
    document.getElementById('searchUbicacion').addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });

    // MODAL
    const overlay = document.getElementById('modalOverlay');
    document.getElementById('modalClose').addEventListener('click', () => overlay.classList.remove('open'));
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') overlay.classList.remove('open'); });

    // Tabs
    document.querySelectorAll('.modal-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const which = tab.dataset.tab;
        document.getElementById('panelConvocatorias').style.display = which === 'convocatorias' ? '' : 'none';
        document.getElementById('panelInfo').style.display = which === 'info' ? '' : 'none';
      });
    });

    async function cargarConvocatorias(uid, grad) {
      const loading = document.getElementById('convLoading');
      const lista = document.getElementById('convLista');
      const empty = document.getElementById('convEmpty');
      loading.style.display = 'flex';
      lista.innerHTML = '';
      empty.style.display = 'none';

      try {
        const r = await fetch(`Php/get_empleos_empresa.php?usuario_id=${uid}`);
        const empleos = await r.json();
        loading.style.display = 'none';

        if (!Array.isArray(empleos) || empleos.length === 0) {
          empty.style.display = 'block';
          return;
        }

        lista.innerHTML = empleos.map(e => {
          const titulo = e.titulo || 'Convocatoria';
          const modalidad = e.modalidad || '';
          const salario = e.salario ? `$${Number(e.salario).toLocaleString('es-CO')}` : '';
          const fecha = e.fecha_publicacion ? new Date(e.fecha_publicacion).toLocaleDateString('es-CO', { day:'numeric', month:'short' }) : '';
          const ciudad = e.ciudad || '';
          return `
            <a href="Empleo.php#empleo-${e.id}" class="conv-item" target="_blank">
              <div class="conv-icon">💼</div>
              <div class="conv-info">
                <p class="conv-titulo">${titulo}</p>
                <div class="conv-meta">
                  ${modalidad ? `<span class="conv-tag">${modalidad}</span>` : ''}
                  ${ciudad ? `<span>📍 ${ciudad}</span>` : ''}
                  ${salario ? `<span>💰 ${salario}</span>` : ''}
                  ${fecha ? `<span>🗓 ${fecha}</span>` : ''}
                </div>
              </div>
              <span class="conv-arrow">›</span>
            </a>`;
        }).join('');
      } catch (err) {
        loading.style.display = 'none';
        empty.style.display = 'block';
      }
    }

    function abrirModal(card) {
      const grad = card.dataset.grad || 'linear-gradient(135deg,#1a56db,#3b82f6)';
      const uid = card.dataset.uid || '';

      // Cover
      document.getElementById('mCover').style.background = grad;

      // Avatar
      const av = document.getElementById('mAvatar');
      av.style.background = grad;
      const logo = card.dataset.logo || '';
      if (logo) {
        av.innerHTML = `<img src="${logo}" alt="" style="width:100%;height:100%;object-fit:cover">`;
      } else {
        av.innerHTML = '';
        av.textContent = card.dataset.initials || '';
      }

      // Badge
      const badgeEl = card.querySelector('.badge-e');
      const mBadge = document.getElementById('mBadge');
      if (badgeEl) {
        mBadge.innerHTML = badgeEl.innerHTML;
        mBadge.style.cssText = badgeEl.style.cssText;
        mBadge.style.display = 'inline-flex';
      } else {
        mBadge.innerHTML = '';
        mBadge.style.display = 'none';
      }

      // Texto
      document.getElementById('mNombre').textContent = card.dataset.nombre || '';
      document.getElementById('mSector').textContent = '🏷️ ' + (card.dataset.sector || '');
      document.getElementById('mUbicacion').textContent = '📍 ' + (card.dataset.ubicacion || '');
      document.getElementById('mDesc').textContent = card.dataset.desc || '';

      // Info grid
      const web = card.dataset.web || '';
      document.getElementById('mInfoGrid').innerHTML = `
        <div class="info-item"><div class="i-label">Sector</div><div class="i-val">${card.dataset.sector || '—'}</div></div>
        <div class="info-item"><div class="i-label">Ciudad</div><div class="i-val">${card.dataset.ubicacion || '—'}</div></div>
        <div class="info-item" style="grid-column:1/-1"><div class="i-label">Sitio web</div><div class="i-val">${web ? `<a href="${web.startsWith('http')?web:'https://'+web}" target="_blank" style="color:#1a56db;text-decoration:none">${web}</a>` : '—'}</div></div>
      `;

      // Botón CTA → siempre al perfil de la empresa
      const cta = document.getElementById('mBtnCta');
      cta.href = uid ? `perfil.php?id=${uid}&tipo=empresa` : 'empresas.php';
      cta.target = '';
      cta.textContent = '🏢 Ver perfil completo';

      // Botón perfil completo
      const bpE = document.getElementById('mBtnPerfilE');
      if (bpE) bpE.href = uid ? `perfil.php?id=${uid}&tipo=empresa` : '#';

      // Resetear tabs a convocatorias
      document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
      document.querySelector('.modal-tab[data-tab="convocatorias"]').classList.add('active');
      document.getElementById('panelConvocatorias').style.display = '';
      document.getElementById('panelInfo').style.display = 'none';

      // Registrar vista
      if (uid) {
        const fd = new FormData();
        fd.append('_action', 'registrar_vista');
        fd.append('usuario_id', uid);
        fd.append('seccion', 'empresas');
        fetch('dashboard.php', { method: 'POST', body: fd }).catch(() => {});
      }

      overlay.classList.add('open');

      // Cargar convocatorias async
      if (uid) cargarConvocatorias(uid, grad);
    }

    allCards.forEach(card => {
      card.querySelector('.btn-perfil').addEventListener('click', () => abrirModal(card));
    });

    // SCROLL REVEAL BIDIRECCIONAL
    const observer = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.style.opacity = '1'; e.target.style.transform = 'translateY(0)';
          if (e.target.classList.contains('reveal')) e.target.classList.add('visible');
        } else {
          const r = e.target.getBoundingClientRect();
          if (r.top < 0) {
            e.target.style.opacity = '0'; e.target.style.transform = 'translateY(24px)';
            if (e.target.classList.contains('reveal')) e.target.classList.remove('visible');
          }
        }
      });
    }, { threshold: 0.1 });
    allCards.forEach(c => {
      c.style.opacity = '0'; c.style.transform = 'translateY(24px)'; c.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      observer.observe(c);
    });
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
  </script>

  <!-- Widget de sesión activa — QuibdóConecta -->
  <script src="js/sesion_widget.js"></script>
<!-- Modal: Iniciar sesión para buscar -->
<div id="qc-login-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10000;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px)" onclick="if(event.target===this)cerrarModalLogin()">
  <div style="background:#fff;border-radius:24px;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.22);overflow:hidden;animation:qcLoginIn .35s cubic-bezier(.34,1.56,.64,1)">
    <div style="background:linear-gradient(135deg,#1f9d55,#2ecc71);padding:28px 28px 22px;text-align:center;position:relative">
      <button onclick="cerrarModalLogin()" style="position:absolute;top:14px;right:16px;background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center">✕</button>
      <div style="font-size:40px;margin-bottom:8px">🏢</div>
      <h2 style="margin:0;color:#fff;font-size:22px;font-weight:800;font-family:'DM Sans',sans-serif">Inicia sesión para buscar</h2>
      <p style="margin:8px 0 0;color:rgba(255,255,255,.88);font-size:14px;font-family:'DM Sans',sans-serif">Descubre empresas y negocios de Quibdó y el Chocó</p>
    </div>
    <div style="padding:28px 28px 24px;text-align:center">
      <p style="margin:0 0 22px;color:#555;font-size:14.5px;line-height:1.6;font-family:'DM Sans',sans-serif">Para usar el buscador necesitas tener una cuenta. ¡Es gratis y solo toma un minuto!</p>
      <a href="inicio_sesion.php" style="display:block;background:linear-gradient(135deg,#1f9d55,#2ecc71);color:#fff;padding:15px 24px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;font-family:'DM Sans',sans-serif;box-shadow:0 4px 16px rgba(31,157,85,.35);margin-bottom:12px">🔑 Iniciar sesión</a>
      <a href="registro.php" style="display:block;background:#f0faf5;color:#1f9d55;padding:14px 24px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;font-family:'DM Sans',sans-serif;border:1.5px solid #c6ebd7">✨ Crear cuenta gratis</a>
      <button onclick="cerrarModalLogin()" style="margin-top:16px;background:none;border:none;color:#aaa;font-size:13px;cursor:pointer;font-family:'DM Sans',sans-serif">Cancelar</button>
    </div>
  </div>
</div>
<style>@keyframes qcLoginIn{from{opacity:0;transform:scale(.88) translateY(20px)}to{opacity:1;transform:scale(1) translateY(0)}}</style>
<script>
function abrirModalLogin(){document.getElementById("qc-login-modal").style.display="flex";document.body.style.overflow="hidden";}
function cerrarModalLogin(){document.getElementById("qc-login-modal").style.display="none";document.body.style.overflow="";}
</script>
</body>

</html>