<?php

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
ini_set('display_errors', 1);
error_reporting(E_ALL);


function emojiServicio($tipo, $profesion = '')
{
  $t = strtolower($tipo . ' ' . $profesion);
  if (preg_match('/(dj|disc jockey|música|musica|champeta|salsa|afrobeat|reggaeton|electrónica)/i', $t))
    return '🎧';
  if (preg_match('/(fotograf|video|cinéma|cineasta|audiovisual|produccion)/i', $t))
    return '📸';
  if (preg_match('/(chirimía|chirimia|marimba|tambor|trompeta|banda|grupo musical|conjunto)/i', $t))
    return '🎺';
  if (preg_match('/(catering|gastronomia|gastronomía|cocinero|chef|pastelero|repostero)/i', $t))
    return '🍽️';
  if (preg_match('/(decoracion|decoración|florista|ambientacion|montaje)/i', $t))
    return '🌸';
  if (preg_match('/(maestro.*ceremonia|animador|presentador|locutor|mc\b)/i', $t))
    return '🎤';
  if (preg_match('/(iluminacion|iluminación|sonido|tecnico.*sonido|audio)/i', $t))
    return '💡';
  if (preg_match('/(maquillaje|maquillador|estilista|peinado)/i', $t))
    return '💄';
  if (preg_match('/(transporte|carro|van|bus|vehiculo|vehículo)/i', $t))
    return '🚐';
  if (preg_match('/(seguridad|vigilancia|guardia)/i', $t))
    return '🛡️';
  return '🎵';
}

function badgeCategoria($tipo, $profesion = '')
{
  $t = strtolower($tipo . ' ' . $profesion);
  if (preg_match('/(dj|música|musica|champeta|salsa|afrobeat|reggaeton|electrónica)/i', $t))
    return ['🎵 Música', 'dorado'];
  if (preg_match('/(fotograf|video|cinéma|audiovisual)/i', $t))
    return ['📸 Foto & Video', 'azul'];
  if (preg_match('/(chirimía|chirimia|marimba|banda|grupo musical)/i', $t))
    return ['🎺 Cultural', 'verde'];
  if (preg_match('/(catering|gastronomia|cocinero|chef)/i', $t))
    return ['🍽️ Gastronomía', 'tierra'];
  if (preg_match('/(decoracion|florista|ambientacion)/i', $t))
    return ['🌸 Decoración', 'rosa'];
  if (preg_match('/(maestro.*ceremonia|animador|presentador|locutor)/i', $t))
    return ['🎤 Animación', 'morado'];
  return ['⭐ Servicio', 'dorado'];
}


function estrellas($cal)
{
  $cal = (float) $cal;
  $out = '';
  for ($i = 1; $i <= 5; $i++) {
    $out .= $i <= round($cal) ? '★' : '☆';
  }
  return $out;
}

function unidadPrecio($tipo, $profesion = '')
{
  $t = strtolower($tipo . ' ' . $profesion);
  if (preg_match('/(catering|comida|chef|cocinero)/i', $t))
    return '/persona';
  if (preg_match('/(fotograf|video)/i', $t))
    return '/evento';
  if (preg_match('/(chirimia|chirimía|banda|grupo)/i', $t))
    return '/presentación';
  return '/evento';
}

$dbServicios = [];
if (file_exists(__DIR__ . '/Php/db.php')) {
  try {
    require_once __DIR__ . '/Php/db.php';
    require_once __DIR__ . '/Php/badges_helper.php';
    $db = getDB();


    try {
      $stmt = $db->query("
        SELECT u.id, u.nombre, u.apellido, u.ciudad, u.foto,
               u.verificado, u.badges_custom,
               tp.profesion, tp.bio, tp.skills, tp.generos,
               tp.precio_desde, tp.tipo_servicio,
               tp.avatar_color, tp.destacado,
               tp.calificacion, tp.total_resenas
        FROM usuarios u
        INNER JOIN talento_perfil tp ON tp.id = (
            SELECT MAX(id) FROM talento_perfil
            WHERE usuario_id = u.id
              AND visible = 1
              AND visible_admin = 1
              AND (
                (precio_desde IS NOT NULL AND precio_desde > 0)
                OR (tipo_servicio IS NOT NULL AND tipo_servicio != '')
              )
        )
        WHERE u.activo = 1
        ORDER BY tp.destacado DESC, u.verificado DESC, tp.calificacion DESC, u.id ASC
        LIMIT 60
      ");
    } catch (Exception $eq) {

      $stmt = $db->query("
        SELECT u.id, u.nombre, u.apellido, u.ciudad, u.foto,
               u.verificado, u.badges_custom,
               tp.profesion, tp.bio, tp.skills, tp.generos,
               NULL as precio_desde, tp.tipo_servicio,
               tp.avatar_color, tp.destacado,
               0 as calificacion, 0 as total_resenas
        FROM usuarios u
        INNER JOIN talento_perfil tp ON tp.id = (
            SELECT MAX(id) FROM talento_perfil
            WHERE usuario_id = u.id AND visible = 1 AND visible_admin = 1
        )
        WHERE u.activo = 1
        ORDER BY tp.destacado DESC, u.verificado DESC, u.id ASC
        LIMIT 60
      ");
    }
    $rawServicios = $stmt->fetchAll(PDO::FETCH_ASSOC);


    $vistos = [];
    foreach ($rawServicios as $row) {
      if (!isset($vistos[$row['id']])) {
        $vistos[$row['id']] = true;
        $dbServicios[] = $row;
      }
    }


    foreach ($dbServicios as &$s) {
      $badges = getBadgesUsuario($db, (int) $s['id']);
      $badgesExtras = array_values(array_filter($badges, fn($b) => ($b['tipo'] ?? '') !== 'verificacion'));
      $s['badges_html'] = renderBadges($badgesExtras, 'small');
      $s['tiene_verificado'] = (bool) $s['verificado'] || tieneBadge($badges, 'Verificado') || tieneBadge($badges, 'Usuario Verificado');
      $s['tiene_premium'] = tieneBadge($badges, 'Premium');
      $s['tiene_destacado'] = tieneBadge($badges, 'Destacado') || (int) ($s['destacado'] ?? 0);
      $s['tiene_top'] = tieneBadge($badges, 'Top');
    }
  } catch (Exception $e) {
    $dbServicios = [];
  }
}


$totalAll = count($dbServicios);
$catCounts = ['musica' => 0, 'foto' => 0, 'cultural' => 0, 'gastronomia' => 0, 'decoracion' => 0, 'animacion' => 0, 'otros' => 0];
foreach ($dbServicios as $s) {
  $t = strtolower(($s['tipo_servicio'] ?? '') . ' ' . ($s['profesion'] ?? ''));
  if (preg_match('/(dj|champeta|salsa|música|musica|afrobeat|reggaeton)/i', $t))
    $catCounts['musica']++;
  elseif (preg_match('/(fotograf|video|audiovisual)/i', $t))
    $catCounts['foto']++;
  elseif (preg_match('/(chirimía|chirimia|marimba|banda|grupo musical)/i', $t))
    $catCounts['cultural']++;
  elseif (preg_match('/(catering|gastronomia|chef|cocinero)/i', $t))
    $catCounts['gastronomia']++;
  elseif (preg_match('/(decoracion|florista|ambientacion)/i', $t))
    $catCounts['decoracion']++;
  elseif (preg_match('/(animador|maestro.*ceremonia|locutor)/i', $t))
    $catCounts['animacion']++;
  else
    $catCounts['otros']++;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Servicios para Eventos – Quibdó Conecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link
    href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800;900&family=DM+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <style>
    :root {
      --verde: #1f9d55;
      --verde2: #2ecc71;
      --verde-o: #edfaf3;
      --dorado: #d4a017;
      --dorado2: #f0c040;
      --dorado-o: #fef9e7;
      --azul: #1a3a6b;
      --azul2: #2563eb;
      --oscuro: #0a0f1e;
      --gris: #f4f6f8;
      --texto: #111;
      --choco-selva: #0a3320;
      --choco-tierra: #6d4c2a;
      --choco-flor: #c0392b;
      --choco-rio: #1a5276;
    }

    *,
    *::before,
    *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box
    }

    html {
      scroll-behavior: smooth
    }

    body {
      font-family: 'DM Sans', Arial, sans-serif;
      background: var(--gris);
      color: var(--texto);
      overflow-x: hidden
    }


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
      border-bottom: 1px solid rgba(0, 0, 0, .08);
      box-shadow: 0 2px 12px rgba(0, 0, 0, .05);
      z-index: 1000;
      transition: box-shadow .3s
    }

    .navbar.abajo {
      box-shadow: 0 4px 20px rgba(0, 0, 0, .12)
    }

    .nav-left {
      display: flex;
      align-items: center;
      gap: 12px
    }

    .logo {
      width: 52px;
      height: auto;
      filter: drop-shadow(0 1px 1px rgba(0, 0, 0, .15))
    }

.logo-navbar{
height:45px;
width:auto;
object-fit:contain;
}

    .brand {
      font-size: 22px;
      font-weight: 800;
      color: #111
    }

    .brand span {
      color: var(--verde)
    }

    .nav-center {
      display: flex;
      align-items: center;
      gap: 22px;
      flex: 1;
      justify-content: center
    }

    .nav-center a {
      color: #333;
      text-decoration: none;
      font-size: 15px;
      font-weight: 500;
      padding: 6px 4px;
      position: relative
    }

    .nav-center a::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: -6px;
      width: 0%;
      height: 2px;
      background: var(--dorado);
      transition: width .3s
    }

    .nav-center a:hover::after,
    .nav-center a.active::after {
      width: 100%
    }

    .nav-center .highlight {
      background: linear-gradient(135deg, var(--dorado), var(--dorado2));
      color: #111 !important;
      padding: 9px 20px;
      border-radius: 25px;
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(212, 160, 23, .35)
    }

    .nav-center .highlight::after {
      display: none
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 14px
    }

    .login {
      color: var(--verde);
      text-decoration: none;
      font-size: 14.5px;
      font-weight: 600;
      padding: 8px 18px;
      border: 2px solid var(--verde);
      border-radius: 30px;
      transition: all .3s
    }

    .login:hover {
      background: var(--verde);
      color: white
    }

    .register {
      background: var(--verde);
      color: white;
      padding: 9px 20px;
      border-radius: 25px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14.5px;
      box-shadow: 0 4px 12px rgba(31, 157, 85, .35);
      transition: all .2s
    }

    .register:hover {
      background: #166f3d
    }

    .hamburger {
      display: none;
      flex-direction: column;
      gap: 5px;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px
    }

    .hamburger span {
      display: block;
      width: 26px;
      height: 2.5px;
      background: #111;
      border-radius: 4px;
      transition: all .3s
    }

    .hamburger.open span:nth-child(1) {
      transform: translateY(7.5px) rotate(45deg)
    }

    .hamburger.open span:nth-child(2) {
      opacity: 0;
      transform: scaleX(0)
    }

    .hamburger.open span:nth-child(3) {
      transform: translateY(-7.5px) rotate(-45deg)
    }

    .mobile-menu {
      display: none;
      position: fixed;
      top: 78px;
      left: 0;
      width: 100%;
      background: white;
      border-bottom: 1px solid rgba(0, 0, 0, .08);
      box-shadow: 0 12px 32px rgba(0, 0, 0, .12);
      flex-direction: column;
      padding: 20px 24px;
      gap: 6px;
      z-index: 999
    }

    .mobile-menu.open {
      display: flex
    }

    .mobile-menu a {
      color: #333;
      text-decoration: none;
      font-size: 16px;
      font-weight: 500;
      padding: 12px 0;
      border-bottom: 1px solid rgba(0, 0, 0, .06)
    }

    .mobile-auth {
      display: flex;
      gap: 12px;
      margin-top: 14px
    }

    .mobile-auth a {
      flex: 1;
      text-align: center;
      padding: 11px;
      border-radius: 25px;
      font-weight: 600;
      font-size: 15px;
      text-decoration: none
    }

    .mobile-auth .m-login {
      border: 2px solid var(--verde);
      color: var(--verde)
    }

    .mobile-auth .m-reg {
      background: var(--verde);
      color: white
    }


    .hero-servicios {
      padding: 150px 48px 100px;
      color: white;
      position: relative;
      overflow: hidden;
      background: url('Imagenes/quibdo 3.jpg') center/cover no-repeat;
    }

    .hero-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(160deg, rgba(13, 31, 13, .92) 0%, rgba(26, 46, 26, .89) 55%, rgba(10, 26, 58, .9) 100%);
    }

    .hero-servicios::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      height: 5px;
      background: linear-gradient(90deg, var(--verde) 33.3%, var(--dorado) 33.3% 66.6%, var(--choco-flor) 66.6%);
    }

    .hero-inner {
      position: relative;
      z-index: 2;
      max-width: 860px
    }

    .section-label-hero {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(212, 160, 23, .18);
      border: 1px solid rgba(240, 192, 64, .35);
      color: var(--dorado2);
      font-size: 12px;
      font-weight: 800;
      padding: 6px 18px;
      border-radius: 30px;
      margin-bottom: 20px;
      letter-spacing: .8px;
      text-transform: uppercase;
    }

    .hero-servicios h1 {
      font-family: 'Syne', sans-serif;
      font-size: 62px;
      font-weight: 900;
      line-height: 1.05;
      margin-bottom: 16px
    }

    .hero-servicios h1 .acento {
      color: var(--dorado2)
    }

    .hero-servicios .sub {
      font-size: 18px;
      color: rgba(255, 255, 255, .7);
      margin-bottom: 44px;
      line-height: 1.6;
      max-width: 580px
    }


    .serv-search {
      display: flex;
      align-items: center;
      background: rgba(255, 255, 255, .1);
      border: 1px solid rgba(255, 255, 255, .2);
      border-radius: 50px;
      padding: 6px;
      max-width: 640px;
      margin-bottom: 36px;
      backdrop-filter: blur(8px)
    }

    .serv-search .sf {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 18px;
      flex: 1
    }

    .serv-search .sf .icon {
      font-size: 17px;
      opacity: .6
    }

    .serv-search input {
      border: none;
      outline: none;
      font-size: 15px;
      width: 100%;
      font-family: 'DM Sans', sans-serif;
      color: white;
      background: transparent
    }

    .serv-search input::placeholder {
      color: rgba(255, 255, 255, .45)
    }

    .serv-search .divider {
      width: 1px;
      height: 30px;
      background: rgba(255, 255, 255, .2);
      flex-shrink: 0
    }

    .serv-search button {
      background: linear-gradient(135deg, var(--dorado), var(--dorado2));
      color: #111;
      border: none;
      border-radius: 40px;
      padding: 12px 26px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all .2s;
      white-space: nowrap
    }

    .serv-search button:hover {
      opacity: .9;
      transform: scale(1.03)
    }


    .hero-cats {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .hcat {
      display: flex;
      align-items: center;
      gap: 6px;
      background: rgba(255, 255, 255, .09);
      border: 1px solid rgba(255, 255, 255, .16);
      color: rgba(255, 255, 255, .8);
      font-size: 13px;
      font-weight: 600;
      padding: 8px 16px;
      border-radius: 25px;
      cursor: pointer;
      transition: all .22s;
      font-family: 'DM Sans', sans-serif;
    }

    .hcat:hover,
    .hcat.activo {
      background: rgba(212, 160, 23, .22);
      border-color: rgba(240, 192, 64, .4);
      color: var(--dorado2)
    }


    .stats-band {
      background: var(--choco-selva);
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      text-align: center;
      padding: 48px;
      border-bottom: 4px solid var(--dorado);
    }

    .stats-band .s h3 {
      font-family: 'Syne', sans-serif;
      font-size: 36px;
      font-weight: 900;
      color: var(--dorado2)
    }

    .stats-band .s p {
      font-size: 12px;
      color: rgba(255, 255, 255, .55);
      margin-top: 5px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .5px
    }


    .filtros-section {
      background: #fff;
      padding: 0 48px;
      border-bottom: 1px solid rgba(0, 0, 0, .07);
      position: sticky;
      top: 78px;
      z-index: 90;
      box-shadow: 0 4px 16px rgba(0, 0, 0, .05);
    }

    .filtros-inner {
      display: flex;
      align-items: center;
      gap: 8px;
      overflow-x: auto;
      padding: 18px 0;
      scrollbar-width: none
    }

    .filtros-inner::-webkit-scrollbar {
      display: none
    }

    .filtro-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      white-space: nowrap;
      padding: 9px 18px;
      border-radius: 25px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      border: 2px solid rgba(0, 0, 0, .08);
      background: white;
      color: #555;
      font-family: 'DM Sans', sans-serif;
      transition: all .22s;
      flex-shrink: 0;
    }

    .filtro-btn:hover,
    .filtro-btn.activo {
      background: linear-gradient(135deg, var(--dorado), var(--dorado2));
      border-color: transparent;
      color: #111;
      box-shadow: 0 4px 14px rgba(212, 160, 23, .3);
    }

    .filtro-count {
      font-size: 11px;
      background: rgba(0, 0, 0, .07);
      border-radius: 10px;
      padding: 1px 7px;
      font-weight: 600
    }

    .filtro-btn.activo .filtro-count {
      background: rgba(0, 0, 0, .15)
    }


    .servicios-section {
      padding: 70px 48px 100px;
      background:
        url('Imagenes/quibdo 3.jpg') center/cover fixed no-repeat;
      position: relative;
    }

    .servicios-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(160deg, rgba(13, 31, 13, .93) 0%, rgba(10, 26, 58, .91) 100%)
    }

    .servicios-inner {
      position: relative;
      z-index: 2
    }

    .servicios-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 32px;
      flex-wrap: wrap;
      gap: 12px
    }

    .servicios-header h2 {
      font-family: 'Syne', sans-serif;
      font-size: 32px;
      font-weight: 800;
      color: white
    }

    .res-count {
      font-size: 14px;
      color: rgba(255, 255, 255, .5);
      font-weight: 500
    }

    .eventos-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
      gap: 24px;
    }


    .evento-card {
      background: rgba(255, 255, 255, .07);
      border: 1px solid rgba(255, 255, 255, .12);
      border-radius: 20px;
      padding: 28px;
      transition: all .35s;
      backdrop-filter: blur(6px);
      cursor: pointer;
    }

    .evento-card:hover {
      background: rgba(255, 255, 255, .14);
      transform: translateY(-6px);
      border-color: rgba(212, 160, 23, .45);
      box-shadow: 0 24px 52px rgba(0, 0, 0, .35);
    }

    .ev-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 16px
    }

    .ev-icon {
      font-size: 42px;
      line-height: 1
    }

    .ev-badge {
      font-size: 11px;
      font-weight: 700;
      padding: 4px 12px;
      border-radius: 20px;
      background: rgba(212, 160, 23, .2);
      color: var(--dorado2);
      border: 1px solid rgba(212, 160, 23, .3);
      white-space: nowrap;
    }

    .ev-badge.verde {
      background: rgba(31, 157, 85, .2);
      color: #4ade80;
      border-color: rgba(46, 204, 113, .3)
    }

    .ev-badge.azul {
      background: rgba(37, 99, 235, .2);
      color: #93c5fd;
      border-color: rgba(59, 130, 246, .3)
    }

    .ev-badge.morado {
      background: rgba(139, 92, 246, .2);
      color: #c4b5fd;
      border-color: rgba(167, 139, 250, .3)
    }

    .ev-badge.tierra {
      background: rgba(180, 83, 9, .2);
      color: #fcd34d;
      border-color: rgba(217, 119, 6, .3)
    }

    .ev-badge.rosa {
      background: rgba(236, 72, 153, .2);
      color: #f9a8d4;
      border-color: rgba(244, 114, 182, .3)
    }

    .ev-badge.rojo {
      background: rgba(239, 68, 68, .2);
      color: #fca5a5;
      border-color: rgba(248, 113, 113, .3)
    }

    .ev-estrellas {
      color: var(--dorado2);
      font-size: 14px;
      margin-bottom: 8px;
      letter-spacing: 1px
    }

    .evento-card h3 {
      font-size: 18px;
      font-weight: 800;
      margin-bottom: 5px;
      color: white;
      line-height: 1.2
    }

    .ev-nombre {
      color: var(--dorado2);
      font-weight: 700;
      font-size: 14px;
      margin-bottom: 5px
    }

    .ev-lugar {
      font-size: 13px;
      color: rgba(255, 255, 255, .5);
      margin-bottom: 6px
    }

    .ev-precio {
      font-size: 14px;
      color: var(--dorado2);
      font-weight: 800;
      margin-bottom: 6px
    }

    .ev-generos {
      font-size: 12px;
      color: rgba(255, 255, 255, .4);
      margin-bottom: 16px;
      line-height: 1.5
    }

    .ev-btn {
      display: inline-block;
      color: var(--dorado2);
      font-weight: 700;
      font-size: 14px;
      text-decoration: none;
      border-bottom: 2px solid transparent;
      transition: border-color .2s;
    }

    .ev-btn:hover {
      border-color: var(--dorado2)
    }


    .no-results {
      grid-column: 1/-1;
      text-align: center;
      padding: 70px 20px;
      color: rgba(255, 255, 255, .5)
    }


    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .7);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      padding: 20px;
      backdrop-filter: blur(4px)
    }

    .modal-overlay.open {
      display: flex
    }

    .modal-box {
      background: #1a2635;
      border: 1px solid rgba(255, 255, 255, .1);
      border-radius: 24px;
      max-width: 560px;
      width: 100%;
      box-shadow: 0 30px 80px rgba(0, 0, 0, .5);
      animation: fadeUp .3s ease both;
      position: relative;
      max-height: 90vh;
      overflow-y: auto;
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(20px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .modal-close {
      position: absolute;
      top: 18px;
      right: 20px;
      background: none;
      border: none;
      font-size: 22px;
      cursor: pointer;
      color: rgba(255, 255, 255, .5)
    }

    .modal-close:hover {
      color: white
    }

    .modal-header {
      padding: 36px 36px 24px;
      display: flex;
      gap: 20px;
      align-items: flex-start;
      border-bottom: 1px solid rgba(255, 255, 255, .08);
    }

    .modal-icon {
      width: 80px;
      height: 80px;
      border-radius: 20px;
      background: rgba(212, 160, 23, .15);
      border: 1px solid rgba(212, 160, 23, .25);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 36px;
      flex-shrink: 0;
    }

    .modal-info h2 {
      font-family: 'Syne', sans-serif;
      font-size: 22px;
      font-weight: 800;
      color: white;
      margin-bottom: 4px
    }

    .modal-info .m-nombre {
      color: var(--dorado2);
      font-weight: 700;
      font-size: 14px;
      margin-bottom: 4px
    }

    .modal-info .m-lugar {
      color: rgba(255, 255, 255, .5);
      font-size: 13px
    }

    .modal-body {
      padding: 24px 36px 36px
    }

    .modal-precio {
      font-size: 20px;
      font-weight: 900;
      color: var(--dorado2);
      margin-bottom: 12px
    }

    .modal-estrellas {
      color: var(--dorado2);
      font-size: 15px;
      margin-bottom: 16px;
      letter-spacing: 1px
    }

    .modal-desc {
      font-size: 14px;
      color: rgba(255, 255, 255, .65);
      line-height: 1.7;
      margin-bottom: 20px
    }

    .modal-generos-titulo {
      font-size: 11px;
      font-weight: 800;
      color: rgba(255, 255, 255, .4);
      text-transform: uppercase;
      letter-spacing: .8px;
      margin-bottom: 10px
    }

    .modal-generos-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 24px
    }

    .modal-gtag {
      background: rgba(212, 160, 23, .12);
      border: 1px solid rgba(212, 160, 23, .2);
      color: rgba(255, 255, 255, .7);
      font-size: 12px;
      font-weight: 600;
      padding: 5px 14px;
      border-radius: 20px;
    }

    .modal-btn-contratar {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 15px;
      background: linear-gradient(135deg, var(--dorado), var(--dorado2));
      color: #111;
      border: none;
      border-radius: 14px;
      font-size: 15px;
      font-weight: 800;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      text-decoration: none;
      box-shadow: 0 6px 20px rgba(212, 160, 23, .45);
      transition: transform .2s;
    }

    .modal-btn-contratar:hover {
      transform: translateY(-2px)
    }

    .modal-btn-chat {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      padding: 13px;
      margin-top: 10px;
      background: rgba(255, 255, 255, .08);
      border: 1px solid rgba(255, 255, 255, .14);
      color: rgba(255, 255, 255, .8);
      border-radius: 14px;
      font-size: 14px;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      text-decoration: none;
      transition: all .2s;
    }

    .modal-btn-chat:hover {
      background: rgba(255, 255, 255, .14);
      color: white
    }


    .ev-cta-section {
      background: linear-gradient(160deg, rgba(13, 31, 13, .97) 0%, rgba(10, 26, 58, .97) 100%);
      padding: 80px 48px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .ev-cta-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, var(--verde) 33.3%, var(--dorado) 33.3% 66.6%, var(--choco-flor) 66.6%);
    }

    .ev-cta-section h2 {
      font-family: 'Syne', sans-serif;
      font-size: 38px;
      font-weight: 900;
      color: white;
      margin-bottom: 14px
    }

    .ev-cta-section p {
      font-size: 16px;
      color: rgba(255, 255, 255, .65);
      max-width: 520px;
      margin: 0 auto 40px;
      line-height: 1.65
    }

    .btn-dorado {
      display: inline-block;
      background: linear-gradient(135deg, var(--dorado), var(--dorado2));
      color: #111;
      padding: 16px 44px;
      border-radius: 30px;
      font-weight: 800;
      font-size: 16px;
      text-decoration: none;
      box-shadow: 0 8px 28px rgba(212, 160, 23, .4);
      transition: transform .2s;
    }

    .btn-dorado:hover {
      transform: translateY(-3px)
    }

    .btn-outline-w {
      display: inline-block;
      border: 2px solid rgba(255, 255, 255, .3);
      color: rgba(255, 255, 255, .8);
      padding: 15px 32px;
      border-radius: 30px;
      font-weight: 600;
      font-size: 15px;
      text-decoration: none;
      margin-left: 14px;
      transition: all .2s;
    }

    .btn-outline-w:hover {
      border-color: var(--dorado2);
      color: var(--dorado2)
    }


    footer {
      background: #0a0f1e;
      border-top: 1px solid rgba(255, 255, 255, .06);
      color: rgba(255, 255, 255, .5);
      text-align: center;
      padding: 28px 48px;
      font-size: 14px
    }

    footer span {
      color: var(--verde2)
    }


    .reveal {
      opacity: 0;
      transform: translateY(36px);
      transition: opacity .65s ease, transform .65s ease
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0)
    }


    @media(max-width:1200px) {
      .hero-servicios h1 {
        font-size: 48px
      }

      .navbar {
        padding: 0 32px
      }
    }

    @media(max-width:900px) {
      .hero-servicios {
        padding: 120px 32px 80px
      }

      .hero-servicios h1 {
        font-size: 38px
      }
    }

    @media(max-width:768px) {
      .navbar {
        padding: 0 20px
      }

      .nav-center,
      .nav-right {
        display: none
      }

      .hamburger {
        display: flex
      }

      .hero-servicios {
        padding: 110px 20px 70px
      }

      .hero-servicios h1 {
        font-size: 28px;
        line-height: 1.15
      }

      .serv-search {
        flex-wrap: wrap;
        border-radius: 18px;
        padding: 10px
      }

      .serv-search .sf {
        width: 100%
      }

      .serv-search .divider {
        width: 100%;
        height: 1px
      }

      .serv-search button {
        width: 100%;
        border-radius: 12px
      }

      .stats-band {
        padding: 36px 20px
      }

      .filtros-section {
        padding: 0 20px
      }

      .servicios-section {
        padding: 50px 20px 70px
      }

      .eventos-grid {
        grid-template-columns: 1fr
      }

      .ev-cta-section {
        padding: 60px 20px
      }

      .modal-header {
        flex-direction: column;
        gap: 14px
      }

      .btn-outline-w {
        margin: 12px 0 0;
        display: block;
        text-align: center
      }
    }

    @media(max-width:480px) {
      .hero-servicios h1 {
        font-size: 24px
      }

      .stats-band {
        grid-template-columns: 1fr 1fr
      }

      .ev-cta-section h2 {
        font-size: 28px
      }
    }
  </style>
</head>

<body>


  <header class="navbar" id="navbar">
        <div class="nav-left">
            <img src="Imagenes/quibdo_desco_new.png" alt="Quibdó Conecta" class="logo-navbar">
        </div>
    <nav class="nav-center">
      <a href="index.html">Inicio</a>
      <a href="Empleo.html">Empleos</a>
      <a href="talentos.php">Talento</a>
      <a href="empresas.php">Empresas</a>
      <a href="negocios.php">Negocios</a>
      <a href="servicios.php" class="active">Eventos</a>
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
    <a href="servicios.php">🎧 Eventos & Servicios</a>
    <div class="mobile-auth">
      <a href="inicio_sesion.php" class="m-login">Iniciar sesión</a>
      <a href="registro.php" class="m-reg">Registrarse</a>
    </div>
  </div>


  <section class="hero-servicios">
    <div class="hero-overlay"></div>
    <div class="hero-inner">
      <span class="section-label-hero">🎧 Nuevo</span>
      <h1>Servicios para <span class="acento">Eventos</span></h1>
      <p class="sub">DJs, artistas, fotógrafos, chirimías, catering y más — con acuerdo de pago seguro para Quibdó y el
        Chocó.</p>
      <div class="serv-search">
        <div class="sf">
          <span class="icon">🔍</span>
          <input type="text" id="searchNombre" placeholder="DJ, fotógrafo, chirimía, catering…" autocomplete="off">
        </div>
        <div class="divider"></div>
        <div class="sf">
          <span class="icon">📍</span>
          <input type="text" id="searchUbicacion" placeholder="Ciudad o municipio">
        </div>
        <button id="searchBtn">Buscar</button>
      </div>
      <div class="hero-cats">
        <button class="hcat activo" data-cat="todos" onclick="filtrarCat('todos',this)">🌐 Todos</button>
        <button class="hcat" data-cat="musica" onclick="filtrarCat('musica',this)">🎧 DJs & Música</button>
        <button class="hcat" data-cat="foto" onclick="filtrarCat('foto',this)">📸 Foto & Video</button>
        <button class="hcat" data-cat="cultural" onclick="filtrarCat('cultural',this)">🎺 Chirimía & Cultural</button>
        <button class="hcat" data-cat="gastronomia" onclick="filtrarCat('gastronomia',this)">🍽️ Catering</button>
        <button class="hcat" data-cat="decoracion" onclick="filtrarCat('decoracion',this)">🌸 Decoración</button>
        <button class="hcat" data-cat="animacion" onclick="filtrarCat('animacion',this)">🎤 Animación</button>
      </div>
    </div>
  </section>

  <div class="stats-band">
    <div class="s reveal">
      <h3><?= $totalAll ?: '+45' ?></h3>
      <p>Servicios disponibles</p>
    </div>
    <div class="s reveal">
      <h3><?= ($catCounts['musica'] + $catCounts['cultural']) ?: '+15' ?></h3>
      <p>DJs & Artistas musicales</p>
    </div>
    <div class="s reveal">
      <h3><?= $catCounts['foto'] ?: '+8' ?></h3>
      <p>Fotógrafos & Videógrafos</p>
    </div>
    <div class="s reveal">
      <h3>+50</h3>
      <p>Municipios del Chocó</p>
    </div>
    <div class="s reveal">
      <h3>💰</h3>
      <p>Acuerdo de pago seguro</p>
    </div>
  </div>

  <div class="filtros-section">
    <div class="filtros-inner">
      <button class="filtro-btn activo" data-cat="todos" onclick="filtrarCat('todos',this)">🌐 Todos <span
          class="filtro-count"><?= $totalAll ?></span></button>
      <button class="filtro-btn" data-cat="musica" onclick="filtrarCat('musica',this)">🎧 DJs & Música <span
          class="filtro-count"><?= $catCounts['musica'] ?></span></button>
      <button class="filtro-btn" data-cat="foto" onclick="filtrarCat('foto',this)">📸 Foto & Video <span
          class="filtro-count"><?= $catCounts['foto'] ?></span></button>
      <button class="filtro-btn" data-cat="cultural" onclick="filtrarCat('cultural',this)">🎺 Chirimía & Cultural <span
          class="filtro-count"><?= $catCounts['cultural'] ?></span></button>
      <button class="filtro-btn" data-cat="gastronomia" onclick="filtrarCat('gastronomia',this)">🍽️ Catering <span
          class="filtro-count"><?= $catCounts['gastronomia'] ?></span></button>
      <button class="filtro-btn" data-cat="decoracion" onclick="filtrarCat('decoracion',this)">🌸 Decoración <span
          class="filtro-count"><?= $catCounts['decoracion'] ?></span></button>
      <button class="filtro-btn" data-cat="animacion" onclick="filtrarCat('animacion',this)">🎤 Animación <span
          class="filtro-count"><?= $catCounts['animacion'] ?></span></button>
    </div>
  </div>


  <section class="servicios-section" id="servicios">
    <div class="servicios-overlay"></div>
    <div class="servicios-inner">
      <div class="servicios-header">
        <h2 class="reveal">Servicios del Chocó</h2>
        <span class="res-count" id="resCount"><?= $totalAll ?> disponible<?= $totalAll !== 1 ? 's' : '' ?></span>
      </div>

      <div class="eventos-grid" id="serviciosGrid">

        <?php if (!empty($dbServicios)): ?>
          <?php foreach ($dbServicios as $srv):
            $nb = htmlspecialchars(trim($srv['nombre'] . ' ' . $srv['apellido']));
            $pro = htmlspecialchars($srv['profesion'] ?: 'Servicio para eventos');
            $ciu = htmlspecialchars($srv['ciudad'] ?: 'Chocó');
            $bio = htmlspecialchars($srv['bio'] ?: '');
            $generos = htmlspecialchars($srv['generos'] ?: '');
            $precio = $srv['precio_desde'] ? number_format((float) $srv['precio_desde'], 0, ',', '.') : '';
            $tipo = htmlspecialchars($srv['tipo_servicio'] ?: '');
            $grd = htmlspecialchars($srv['avatar_color'] ?: 'linear-gradient(135deg,#1f9d55,#2ecc71)');
            $foto = !empty($srv['foto']) ? 'uploads/fotos/' . htmlspecialchars($srv['foto']) : '';
            $ini = strtoupper(mb_substr($srv['nombre'], 0, 1) . mb_substr($srv['apellido'] ?? '', 0, 1));
            $emoji = emojiServicio($tipo, $srv['profesion']);
            $cal = (float) ($srv['calificacion'] ?? 0);
            $stars = estrellas($cal ?: 5);
            $unidad = unidadPrecio($tipo, $srv['profesion']);
            [$badgeTxt, $badgeCls] = badgeCategoria($tipo, $srv['profesion']);

            if ($srv['tiene_top']) {
              $badgeTxt = '👑 Top';
              $badgeCls = 'rojo';
            } elseif ($srv['tiene_premium']) {
              $badgeTxt = '⭐ Premium';
              $badgeCls = 'dorado';
            } elseif ($srv['tiene_destacado']) {
              $badgeTxt = '🏅 Destacado';
              $badgeCls = 'morado';
            } elseif ($srv['tiene_verificado']) {
              $badgeTxt = '⭐ Verificado';
              $badgeCls = 'dorado';
            }


            $t = strtolower($tipo . ' ' . $srv['profesion']);
            if (preg_match('/(dj|champeta|salsa|música|musica|afrobeat|reggaeton|electrónica)/i', $t))
              $catData = 'musica';
            elseif (preg_match('/(fotograf|video|audiovisual)/i', $t))
              $catData = 'foto';
            elseif (preg_match('/(chirimía|chirimia|marimba|banda|grupo musical)/i', $t))
              $catData = 'cultural';
            elseif (preg_match('/(catering|gastronomia|chef|cocinero)/i', $t))
              $catData = 'gastronomia';
            elseif (preg_match('/(decoracion|florista|ambientacion)/i', $t))
              $catData = 'decoracion';
            elseif (preg_match('/(animador|maestro.*ceremonia|locutor)/i', $t))
              $catData = 'animacion';
            else
              $catData = 'otros';

            $generosDots = str_replace(',', ' · ', $generos);
            ?>
            <div class="evento-card reveal" data-cat="<?= $catData ?>" data-uid="<?= $srv['id'] ?>" data-nombre="<?= $nb ?>"
              data-pro="<?= $pro ?>" data-ciu="<?= $ciu ?>" data-bio="<?= $bio ?>" data-generos="<?= $generos ?>"
              data-precio="<?= $precio ?>" data-unidad="<?= $unidad ?>" data-emoji="<?= $emoji ?>"
              data-stars="<?= $stars ?>" data-foto="<?= $foto ?>" data-grad="<?= $grd ?>" data-ini="<?= $ini ?>"
              data-tipo="<?= $tipo === 'empresa' ? 'empresa' : ($tipo === 'negocio' ? 'negocio' : 'talento') ?>">
              <div class="ev-top">
                <span class="ev-icon"><?= $emoji ?></span>
                <span class="ev-badge <?= $badgeCls ?>"><?= $badgeTxt ?></span>
              </div>
              <div class="ev-estrellas"><?= $stars ?></div>
              <h3><?= $pro ?></h3>
              <p class="ev-nombre"><?= $nb ?></p>
              <p class="ev-lugar">📍 <?= $ciu ?></p>
              <?php if ($precio): ?>
                <p class="ev-precio">💰 Desde $<?= $precio ?><?= $unidad ?></p>
              <?php endif; ?>
              <?php if ($generosDots): ?>
                <p class="ev-generos"><?= $generosDots ?></p>
              <?php endif; ?>
              <?php if (!empty($srv['badges_html'])): ?>
                <div style="margin-bottom:10px"><?= $srv['badges_html'] ?></div>
              <?php endif; ?>
              <button class="ev-btn" onclick="abrirModal(this.closest('.evento-card'))">Ver perfil y contratar →</button>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($dbServicios)): ?>
          <div class="no-results">
            <div style="font-size:60px;margin-bottom:16px">🎧</div>
            <p style="font-size:16px;font-weight:700;color:rgba(255,255,255,.7);margin-bottom:8px">Aún no hay servicios
              registrados</p>
            <p style="font-size:14px;margin-bottom:24px">¡Sé el primero en ofrecer tu servicio para eventos!</p>
            <a href="registro.php"
              style="display:inline-block;padding:13px 32px;background:linear-gradient(135deg,var(--dorado),var(--dorado2));color:#111;border-radius:30px;text-decoration:none;font-weight:800">🎧
              Registrar mi servicio</a>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </section>


  <section class="ev-cta-section">
    <h2 class="reveal">¿Ofreces un servicio para eventos en el Chocó?</h2>
    <p>Regístrate gratis, configura tu perfil con tu precio y géneros, y empieza a recibir contratos con acuerdo de pago
      seguro.</p>
    <a href="registro.php" class="btn-dorado">🎧 Registrar mi servicio gratis →</a>
    <a href="talentos.php" class="btn-outline-w">🌟 Ver todos los talentos</a>
  </section>


  <div class="modal-overlay" id="modalOverlay">
    <div class="modal-box" style="max-width:680px">
      <button class="modal-close" id="modalClose">✕</button>
      <div class="modal-header">
        <div class="modal-icon" id="mIcon"></div>
        <div class="modal-info">
          <h2 id="mPro"></h2>
          <p class="m-nombre" id="mNombre"></p>
          <p class="m-lugar" id="mLugar"></p>
        </div>
      </div>
      <div class="modal-body">
        <div class="modal-estrellas" id="mStars"></div>
        <div class="modal-precio" id="mPrecio"></div>
        <p class="modal-desc" id="mDesc"></p>
        <div class="modal-generos-titulo">Géneros & Especialidades</div>
        <div class="modal-generos-tags" id="mGeneros"></div>

        <div id="mGaleriaWrap" style="display:none;margin-bottom:20px">
          <div
            style="font-size:11px;font-weight:800;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px">
            📸 Galería de evidencias</div>
          <div id="mGaleriaGrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px"></div>
        </div>
        <div id="mGaleriaLoading"
          style="display:none;text-align:center;padding:16px;color:rgba(255,255,255,.4);font-size:13px">⚙️ Cargando
          galería…</div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">
          <a href="#" id="mContratarBtn" class="modal-btn-contratar">💰 Contratar este servicio</a>
          <a href="#" id="mChatBtn" class="modal-btn-chat">💬 Enviar mensaje</a>
          <a href="#" id="mPerfilBtn"
            style="display:inline-flex;align-items:center;gap:6px;padding:12px 18px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:30px;color:rgba(255,255,255,.8);text-decoration:none;font-size:13px;font-weight:700;transition:all .2s"
            onmouseover="this.style.background='rgba(255,255,255,.18)'"
            onmouseout="this.style.background='rgba(255,255,255,.1)'">
            👤 Ver perfil completo
          </a>
        </div>
      </div>
    </div>
  </div>


  <footer>
    <p>© 2026 <span>QuibdóConecta</span> — Conectando el talento del Chocó con el mundo.</p>
  </footer>

  <script>

    window.addEventListener('scroll', () => {
      document.getElementById('navbar').classList.toggle('abajo', window.scrollY > 50);
    });


    const ham = document.getElementById('hamburger');
    const mob = document.getElementById('mobileMenu');
    ham.addEventListener('click', () => { ham.classList.toggle('open'); mob.classList.toggle('open'); });
    document.addEventListener('click', e => {
      if (!ham.contains(e.target) && !mob.contains(e.target)) { ham.classList.remove('open'); mob.classList.remove('open'); }
    });


    let catActiva = 'todos';
    let textoBusq = '';

    function filtrarCat(cat, btn) {
      catActiva = cat;

      document.querySelectorAll('[data-cat]').forEach(b => {
        if (b.tagName === 'BUTTON' || b.tagName === 'A') {
          b.classList.toggle('activo', b.dataset.cat === cat);
          b.classList.toggle('dim', b.dataset.cat !== cat && cat !== 'todos');
        }
      });
      aplicarFiltros();
      document.getElementById('servicios').scrollIntoView({ behavior: 'smooth' });
    }

    function aplicarFiltros() {
      const cards = document.querySelectorAll('#serviciosGrid .evento-card');
      let visible = 0;
      cards.forEach(c => {
        const matchCat = catActiva === 'todos' || c.dataset.cat === catActiva;
        const txt = (c.dataset.nombre + ' ' + c.dataset.pro + ' ' + c.dataset.generos + ' ' + c.dataset.ciu).toLowerCase();
        const matchTxt = !textoBusq || txt.includes(textoBusq);
        const show = matchCat && matchTxt;
        c.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      const rc = document.getElementById('resCount');
      if (rc) rc.textContent = visible + ' disponible' + (visible !== 1 ? 's' : '');
    }


    function buscar() {
      textoBusq = (
        document.getElementById('searchNombre').value.trim().toLowerCase() + ' ' +
        document.getElementById('searchUbicacion').value.trim().toLowerCase()
      ).trim();
      aplicarFiltros();
      document.getElementById('servicios').scrollIntoView({ behavior: 'smooth' });
    }
    document.getElementById('searchBtn').addEventListener('click', buscar);
    ['searchNombre', 'searchUbicacion'].forEach(id => {
      document.getElementById(id).addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });
    });


    const overlay = document.getElementById('modalOverlay');
    document.getElementById('modalClose').addEventListener('click', () => overlay.classList.remove('open'));
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') overlay.classList.remove('open'); });

    function abrirModal(card) {
      const d = card.dataset;

      const mi = document.getElementById('mIcon');
      if (d.foto) {
        mi.innerHTML = `<img src="${d.foto}" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
      } else {
        mi.style.background = d.grad || '#1f9d55';
        mi.textContent = d.ini || '?';
      }

      document.getElementById('mPro').textContent = d.pro || '';
      document.getElementById('mNombre').textContent = d.nombre || '';
      document.getElementById('mLugar').textContent = '📍 ' + (d.ciu || '');
      document.getElementById('mStars').textContent = d.stars || '★★★★★';
      document.getElementById('mDesc').textContent = d.bio || 'Profesional de eventos disponible para contratación en el Chocó.';

      const mp = document.getElementById('mPrecio');
      mp.textContent = d.precio ? '💰 Desde $' + d.precio + (d.unidad || '') : '';

      const genArr = (d.generos || '').split(',').filter(g => g.trim());
      document.getElementById('mGeneros').innerHTML =
        genArr.length
          ? genArr.map(g => `<span class="modal-gtag">${g.trim()}</span>`).join('')
          : '<span class="modal-gtag">Disponible para todo tipo de eventos</span>';

      const uid = d.uid || '';
      const tipo = d.tipo || 'talento';
      document.getElementById('mChatBtn').href = uid ? `chat.php?con=${uid}` : 'chat.php';
      document.getElementById('mContratarBtn').href = uid ? `chat.php?con=${uid}` : 'chat.php';
      document.getElementById('mPerfilBtn').href = uid ? `perfil.php?id=${uid}&tipo=${tipo}` : '#';


      const galeriaWrap = document.getElementById('mGaleriaWrap');
      const galeriaGrid = document.getElementById('mGaleriaGrid');
      const galeriaLoading = document.getElementById('mGaleriaLoading');
      galeriaWrap.style.display = 'none';
      galeriaLoading.style.display = 'block';
      galeriaGrid.innerHTML = '';

      overlay.classList.add('open');

      if (uid) {
        const fv = new FormData();
        fv.append('_action', 'registrar_vista'); fv.append('usuario_id', uid); fv.append('seccion', 'servicios');
        fetch('dashboard.php', { method: 'POST', body: fv }).catch(() => { });

        fetch(`galeria_publica.php?id=${uid}`)
          .then(r => r.json())
          .then(items => {
            galeriaLoading.style.display = 'none';
            if (!items.length) return;
            galeriaWrap.style.display = 'block';
            galeriaGrid.innerHTML = items.slice(0, 8).map(it => {
              const isVideo = it.tipo === 'video';
              const ytMatch = it.url_video && it.url_video.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
              const ytId = ytMatch ? ytMatch[1] : '';
              const thumb = ytId
                ? `https://img.youtube.com/vi/${ytId}/mqdefault.jpg`
                : (it.archivo ? `uploads/galeria/${it.archivo}` : '');
              if (!thumb) return '';
              return `<div style="position:relative;border-radius:8px;overflow:hidden;aspect-ratio:1;cursor:pointer;background:#1a1a2e"
                onclick="${isVideo && it.url_video ? `window.open('${it.url_video}','_blank')` : `abrirLightboxModal('uploads/galeria/${it.archivo}','${(it.titulo || '').replace(/'/g, "\\'")}') `}">
              <img src="${thumb}" style="width:100%;height:100%;object-fit:cover" loading="lazy">
              ${isVideo ? '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.4);font-size:24px">▶️</div>' : ''}
            </div>`;
            }).join('');
            if (items.length > 8) {
              galeriaGrid.innerHTML += `<div style="aspect-ratio:1;border-radius:8px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;font-weight:700;color:rgba(255,255,255,.6)"
              onclick="window.location='perfil.php?id=${uid}&tipo=${tipo}'">+${items.length - 8} más</div>`;
            }
          })
          .catch(() => { galeriaLoading.style.display = 'none'; });
      } else {
        galeriaLoading.style.display = 'none';
      }
    }

    function abrirLightboxModal(url, titulo) {
      let lb = document.getElementById('lbox-modal');
      if (!lb) {
        lb = document.createElement('div');
        lb.id = 'lbox-modal';
        lb.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.95);z-index:9999;align-items:center;justify-content:center;flex-direction:column;padding:20px;cursor:pointer';
        lb.innerHTML = '<button style="position:absolute;top:20px;right:24px;font-size:28px;color:#fff;background:none;border:none;cursor:pointer" onclick="document.getElementById(\'lbox-modal\').style.display=\'none\'">✕</button><img style="max-width:90vw;max-height:85vh;border-radius:10px;object-fit:contain"><p style="color:rgba(255,255,255,.7);margin-top:10px;font-size:13px"></p>';
        lb.addEventListener('click', e => { if (e.target === lb) lb.style.display = 'none'; });
        document.body.appendChild(lb);
      }
      lb.querySelector('img').src = url;
      lb.querySelector('p').textContent = titulo || '';
      lb.style.display = 'flex';
    }

    const observer = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.style.opacity = '1'; e.target.style.transform = 'translateY(0)';
          e.target.classList.add('visible');
        } else if (e.target.getBoundingClientRect().top < 0) {
          e.target.style.opacity = '0'; e.target.style.transform = 'translateY(24px)';
          e.target.classList.remove('visible');
        }
      });
    }, { threshold: 0.1 });

    document.querySelectorAll('.evento-card,.reveal').forEach(el => {
      el.style.opacity = '0'; el.style.transform = 'translateY(24px)';
      el.style.transition = 'opacity 0.5s ease,transform 0.5s ease';
      observer.observe(el);
    });
  </script>


  <script src="js/sesion_widget.js"></script>
</body>

</html>