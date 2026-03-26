<?php
// ============================================================
// talentos.php — Carga usuarios de BD + talentos demo
// ============================================================
// BUILD: v20260320001155
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Surrogate-Control: no-store");
header("X-Accel-Expires: 0");
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ── Detectar categoría automáticamente ──────────────────────
function detectarCategoria($profesion, $skills)
{
  $texto = strtolower($profesion . ' ' . $skills);

  // SALUD (primero para evitar falsos positivos con "auxilios")
  if (preg_match('/(médic|medico|médica|médico|enferm|enfermero|enfermera|hospital|clínic|clinica|odontolog|odontólogo|farmaceut|farmacéutico|nutricion|nutricionista|fisioterapia|fisioterapeuta|optometr|veterinar|veterinario|cirujano|radiólog|laboratorio|auxiliar de salud|promotor de salud|paramédico|camillero|bacteriólog|microbiólog|psiquiatra|psiquiatría|neurolog|pediatr|pediatra|ginecólog|cardiólog|dermatólog|traumatólog|ortopedista|urólog|oncólog|anestesiólog|intensivista|medicina|salud pública|epidemiolog|salud comunitaria|auxiliar dental|higienista oral|técnico dental|protésico|audiólog|fonoaudiólog|terapia física|terapia respiratoria|oxigenoterapia|vacunador|salud ocupacional|medicina laboral|primeros auxilios|cruz roja|bombero paramédico)\b/i', $texto))
    return 'salud';

  // EDUCACIÓN (antes de música para capturar psicólogo)
  if (preg_match('/(docente|profesor|profe|tutor|tutoría|educaci|licenciado|maestro|enseñanza|colegio|escuela|psicólog|psicolog|psicología|orientador|pedagog|pedagogía|capacitador|instructor|formador|coach educativo|universitario|académico|rector|coordinador académico|director escolar|educador|educadora|auxiliar pedagógico|monitor|catedrático|investigador|ciencias|matemática|física|química|biología|historia|geografía|inglés|español|literatura|filosofía|educación física|preescolar|primera infancia|jardín infantil|guardería|parvularia|fonoaudiología|terapia del lenguaje|logopeda|psicopedagog|educación especial|estimulación temprana|ludoteca|animador sociocultural|orientación vocacional|consejería|bienestar estudiantil|trabajo social escolar|desarrollo infantil)\b/i', $texto))
    return 'educacion';

  // TECNOLOGÍA
  if (preg_match('/(sistem|software|programador|programadora|web developer|desarrollador|desarrolladora|php|javascript|typescript|react|angular|vue|node|python|java|kotlin|swift|sql|mysql|mongodb|tecnolog|informátic|computaci|redes|soporte ti|cctv|windows server|linux|android developer|aplicaciones móviles|datos|base de datos|ciberseguridad|devops|cloud|aws|azure|backend|frontend|fullstack|api rest|git|docker|kubernetes|machine learning|inteligencia artificial|scrum|agile|wordpress developer|shopify|ecommerce técnico|hacker ético|pentesting|firewall|servidor|infraestructura|virtualización|blockchain|criptomonedas|realidad virtual|realidad aumentada|power bi|tableau|analista de datos|ciencia de datos|robótica|electrónica digital|automatización|iot|arduino|raspberry|telecomunicaciones|ingeniero sistemas|ingeniero software|ingeniero electrónico|técnico en sistemas|helpdesk)\b/i', $texto))
    return 'tecnologia';

  // MÚSICA & DJ
  if (preg_match('/(dj|disc jockey|músico|musico|musica|música|cantante|cantautor|productor musical|chirimía|chirimia|currulao|salsa|reggaeton|reggaetón|vallenato|artista musical|banda musical|grupo musical|locutor|locutora|saxofonista|saxofón|saxofon|trompetista|trompeta|guitarrista|guitarra|baterista|batería|bateria|pianista|piano|violinista|violín|bajista|bajo|percusionista|percusión|percusion|acordeonista|acordeón|flautista|flauta|clarinetista|clarinete|compositor|compositora|coro|solista|intérprete|interprete|urbano|hip hop|champeta|porro|cumbia|marimba|afrobeat|electrónica|techno|house|reggae|merengue|bachata|bolero|tango|rock|pop|jazz|blues|soul|funk|gospel|coral|director musical|arreglista|ingeniero de sonido|sonidista|mezclador|karaoke|música en vivo|orquesta|mariachi|ensamble|cuarteto|booking artístico|manager artístico|promotor musical|show musical|animador de eventos)\b/i', $texto))
    return 'musica';

  // ARTE & DISEÑO
  if (preg_match('/(diseñador|diseñadora|diseño gráfico|gráfic|ilustrador|ilustradora|ilustracion|ilustración|fotógrafo|fotógrafa|fotografía|videógrafo|videógrafa|videografía|creativo|creativa|branding|ux designer|ui designer|figma|photoshop|illustrator|after effects|premiere|indesign|corel draw|animador|animación|motion graphics|concept art|pintor|pintora|pintura|escultor|escultura|ceramista|cerámica|artesano|artesana|artesanía|caricaturista|tatuador|tatuadora|maquillador|maquilladora|estilista|modista|moda|fashion|diseño de modas|arquitecto|arquitecta|arquitectura|interiorismo|decorador|decoradora|diseño interior|render|autocad|sketchup|revit|modelado 3d|3d artist|diseño web|editor de video|edición de video|fotografía de bodas|retoque fotográfico|lightroom|cinematografía|producción audiovisual|director de arte|colorista|storyboard|cómic|manga|lettering|tipografía|serigrafía|rotulista|graffiti|muralista|street art|arte digital|nft|contenido visual|creador de contenido)\b/i', $texto))
    return 'arte';

  // ADMINISTRATIVO
  if (preg_match('/(administrador|administradora|contabilidad|contador|contadora|financiero|financiera|recursos humanos|rrhh|secretaria|secretario|gerente|coordinador|coordinadora|asistente administrativo|auxiliar administrativo|recepcionista|atención al cliente|atencion al cliente|vendedor|vendedora|mercadeo|marketing|publicidad|logística|logistica|compras|inventario|facturación|tesorero|tesorera|auditor|auditora|revisor fiscal|economista|economía|comercio|negocios|administración|gestión empresarial|emprendedor|emprendedora|consultor|consultora|asesor comercial|asesora comercial|asesor financiero|analista|planeación|presupuesto|costos|cartera|cobros|pagos|nómina|nomina|talento humano|bienestar laboral|relaciones laborales|abogado laboral|contratos|licitaciones|compras públicas|outsourcing|community manager|social media|redes sociales|marketing digital|comercio electrónico|importación|exportación|comercio exterior|aduana|agente aduanal|supply chain|cadena de suministro|operaciones|gestión de calidad|call center)\b/i', $texto))
    return 'administrativo';

  // GASTRONOMÍA
  if (preg_match('/(cocinero|cocinera|chef|gastronomía|gastronomia|repostero|repostería|panadero|panadería|bartender|barman|barmaid|mesero|mesera|catering|restaurante|pastelero|pastelería|heladero|heladería|cafetero|barista|sommelier|cocina|chef ejecutivo|sous chef|chef pastelero|chef panadero|cocina internacional|cocina colombiana|cocina típica|cocina fusión|cocina vegana|cocina vegetariana|comida saludable|preparación de alimentos|manipulación de alimentos|bromatología|cocina molecular|técnicas culinarias|carnicero|pescadero|chocolatero|bombonero|jugos naturales|smoothies|cocteles|coctelería|mixología|enología|cervecería artesanal|productor de alimentos|galletero|artesano de alimentos)\b/i', $texto))
    return 'gastronomia';

  // TÉCNICO & OFICIOS
  if (preg_match('/(electricista|plomero|plomería|mecánico|mecanico|técnico en|soldador|soldadura|construccion|construcción|albañil|albañilería|carpintero|carpintería|ebanista|conductor|chofer|operario|operador|fontanero|refrigeración|refrigeracion|aire acondicionado|cerrajero|cerrajería|pintor de obra|mantenimiento|instalador|instalaciones eléctricas|instalaciones hidráulicas|herrero|herrería|tornero|fresador|maquinaria pesada|excavadora|grúa|montacargas|camionero|tractorista|bodeguero|almacenista|mensajero|domiciliario|repartidor|vigilante|guardia de seguridad|escolta|portero|conserje|servicios generales|jardinero|jardinería|paisajista|fumigador|control de plagas|lavandero|tintorero)\b/i', $texto))
    return 'tecnico';

  return 'todos';
}

$dbTalentos = [];
if (file_exists(__DIR__ . '/Php/db.php')) {
  try {
    require_once __DIR__ . '/Php/db.php';
    require_once __DIR__ . '/Php/badges_helper.php';
    $db = getDB();
    // Traer todos los candidatos activos con perfil visible
    // Usamos subquery con id MAX para garantizar UN solo registro por usuario
    // Subquery con MAX(id) garantiza UN solo registro por usuario,
    // sin depender de GROUP BY ni UNIQUE en la BD
    $stmt = $db->query("
            SELECT u.id, u.nombre, u.apellido, u.ciudad, u.foto,
                   u.verificado, u.badges_custom,
                   tp.profesion, tp.bio, tp.skills, tp.avatar_color, tp.destacado
            FROM usuarios u
            INNER JOIN talento_perfil tp ON tp.id = (
                SELECT MAX(id) FROM talento_perfil
                WHERE usuario_id = u.id
                  AND visible = 1
                  AND visible_admin = 1
            )
            WHERE u.activo = 1
            ORDER BY tp.destacado DESC, u.verificado DESC, u.id ASC
            LIMIT 50
        ");
    $rawTalentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Dedup en PHP como failsafe adicional
    $vistos = [];
    $dbTalentos = [];
    foreach ($rawTalentos as $row) {
        if (!isset($vistos[$row['id']])) {
            $vistos[$row['id']] = true;
            $dbTalentos[] = $row;
        }
    }

    // Agregar badges a cada talento
    foreach ($dbTalentos as &$t) {
      $badges = getBadgesUsuario($db, (int) $t['id']);
      $t['badges'] = $badges;
      // Excluir badges tipo verificacion del inline (ya aparecen como etiqueta principal)
      $badgesExtras = array_values(array_filter($badges, fn($b) => ($b['tipo'] ?? '') !== 'verificacion'));
      $t['badges_html'] = renderBadges($badgesExtras, 'small');
      $t['tiene_verificado'] = (bool) $t['verificado'] || tieneBadge($badges, 'Verificado') || tieneBadge($badges, 'Usuario Verificado');
      $t['tiene_premium'] = tieneBadge($badges, 'Premium');
      $t['tiene_destacado'] = tieneBadge($badges, 'Destacado') || (int) ($t['destacado'] ?? 0);
      $t['tiene_top'] = tieneBadge($badges, 'Top');
    }
  } catch (Exception $e) {
    $dbTalentos = [];
  }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Talentos - Quibdó Conecta</title>
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

    .logo {
      width: 52px;
      height: auto;
      filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.15));
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
    .hero-talento {
      padding: 150px 48px 90px;
      background: linear-gradient(135deg, #0f172a 0%, #1a2e1a 60%, #0f172a 100%);
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .hero-talento::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 30% 50%, rgba(31, 157, 85, 0.18) 0%, transparent 60%), radial-gradient(ellipse at 70% 50%, rgba(46, 204, 113, 0.12) 0%, transparent 60%);
    }

    .hero-talento-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
      margin: 0 auto;
    }

    .hero-badge {
      display: inline-block;
      background: rgba(31, 157, 85, 0.2);
      border: 1px solid rgba(46, 204, 113, 0.4);
      color: #4ade80;
      font-size: 13px;
      font-weight: 600;
      padding: 6px 20px;
      border-radius: 30px;
      margin-bottom: 24px;
      letter-spacing: 0.6px;
    }

    .hero-talento h1 {
      font-family: 'Syne', sans-serif;
      font-size: 58px;
      font-weight: 800;
      color: white;
      line-height: 1.1;
      margin-bottom: 20px;
    }

    .hero-talento h1 span {
      color: #2ecc71;
    }

    .hero-talento>div>p {
      font-size: 18px;
      color: rgba(255, 255, 255, 0.75);
      margin-bottom: 40px;
      line-height: 1.6;
    }

    /* SEARCH BAR EN HERO */
    .talent-search {
      display: flex;
      align-items: center;
      background: white;
      border-radius: 50px;
      padding: 8px;
      max-width: 680px;
      margin: 0 auto 36px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.28);
    }

    .talent-search .sf {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 18px;
      flex: 1;
    }

    .talent-search .sf .icon {
      font-size: 18px;
      opacity: 0.5;
    }

    .talent-search input {
      border: none;
      outline: none;
      font-size: 15px;
      width: 100%;
      font-family: 'DM Sans', sans-serif;
      color: #222;
      background: transparent;
    }

    .talent-search .divider {
      width: 1px;
      height: 34px;
      background: rgba(0, 0, 0, 0.1);
      flex-shrink: 0;
    }

    .talent-search button {
      background: linear-gradient(135deg, #1f9d55, #2ecc71);
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

    .talent-search button:hover {
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
      color: #4ade80;
      border-color: #4ade80;
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
      color: #1f9d55;
    }

    .stats-band .s p {
      font-size: 13px;
      color: #888;
      margin-top: 5px;
      font-weight: 500;
    }

    /* CATEGORÍAS */
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
      background: #edfaf3;
      border-color: #1f9d55;
      color: #1f9d55;
      box-shadow: 0 6px 20px rgba(31, 157, 85, 0.15);
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
      background: rgba(31, 157, 85, 0.15);
      color: #1f9d55;
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
      background: #1f9d55;
      border-color: #1f9d55;
      color: white;
    }

    /* TALENTOS SECTION */
    .talentos-section {
      padding: 80px 48px;
      background: white;
    }

    .talentos-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      flex-wrap: wrap;
      gap: 12px;
    }

    .talentos-header h2 {
      font-family: 'Syne', sans-serif;
      font-size: 34px;
    }

    .resultados-count {
      font-size: 14px;
      color: #888;
      font-weight: 500;
    }

    .talentos-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 24px;
      margin-top: 32px;
    }

    /* TALENTO CARD */
    .talento-card {
      background: #fafafa;
      border: 1px solid rgba(0, 0, 0, 0.07);
      border-radius: 20px;
      padding: 28px;
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
      cursor: pointer;
    }

    .talento-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 16px 44px rgba(0, 0, 0, 0.11);
      background: white;
      border-color: transparent;
    }

    .talento-card .badge-t {
      position: absolute;
      top: 18px;
      right: 18px;
      font-size: 11px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 20px;
      background: #edfaf3;
      color: #1f9d55;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .badge-artista {
      background: #f3e8ff !important;
      color: #7c3aed !important;
    }

    .badge-disponible {
      background: #fef9c3 !important;
      color: #b45309 !important;
    }

    .badge-verificado-principal {
      background: #d1fae5 !important;
      color: #065f46 !important;
      border: 1px solid #6ee7b7 !important;
    }

    .talento-avatar {
      width: 68px;
      height: 68px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1f9d55, #2ecc71);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      margin-bottom: 16px;
      font-weight: 800;
      color: white;
      flex-shrink: 0;
    }

    .talento-card h3 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 4px;
    }

    .talento-card .profesion {
      color: #1f9d55;
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 4px;
    }

    .talento-card .ubicacion {
      font-size: 13px;
      color: #999;
      margin-bottom: 14px;
    }

    .talento-tags {
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
      border: 2px solid #1f9d55;
      color: #1f9d55;
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
      background: #1f9d55;
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

    /* MODAL PERFIL */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.55);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      padding: 20px;
      backdrop-filter: blur(6px);
    }

    .modal-overlay.open {
      display: flex;
    }

    .modal-box {
      background: white;
      border-radius: 24px;
      max-width: 560px;
      width: 100%;
      box-shadow: 0 30px 80px rgba(0, 0, 0, 0.22);
      animation: fadeUp 0.3s ease both;
      position: relative;
      max-height: 90vh;
      overflow-y: auto;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px) }
      to   { opacity: 1; transform: translateY(0) }
    }

    .modal-close {
      position: absolute;
      top: 16px;
      right: 16px;
      background: #f1f5f9;
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      font-size: 15px;
      cursor: pointer;
      color: #666;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background .2s;
      z-index: 1;
    }

    .modal-close:hover { background: #e2e8f0; color: #333; }

    .modal-header {
      padding: 32px 32px 22px;
      display: flex;
      gap: 18px;
      align-items: flex-start;
      border-bottom: 1px solid #f1f5f9;
    }

    .modal-avatar {
      width: 76px;
      height: 76px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 800;
      color: white;
      flex-shrink: 0;
      box-shadow: 0 4px 16px rgba(0,0,0,.15);
    }

    .modal-info h2 {
      font-family: 'Syne', sans-serif;
      font-size: 20px;
      font-weight: 800;
      margin-bottom: 4px;
      line-height: 1.2;
      color: #111;
    }

    .modal-info .m-profesion {
      color: #1f9d55;
      font-weight: 700;
      font-size: 14px;
      margin-bottom: 4px;
    }

    .modal-info .m-ubicacion {
      color: #888;
      font-size: 13px;
    }

    .modal-body {
      padding: 22px 32px 32px;
    }

    .modal-habilidades {
      margin-bottom: 18px;
    }

    .modal-habilidades h4 {
      font-size: 11px;
      font-weight: 800;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: .8px;
      margin-bottom: 10px;
    }

    .modal-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 7px;
    }

    .modal-tags .tag {
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
      color: #444;
      font-size: 12px;
      padding: 5px 13px;
      border-radius: 20px;
      font-weight: 600;
    }

    .modal-desc {
      font-size: 14px;
      color: #555;
      line-height: 1.75;
      margin-bottom: 0;
    }

    .modal-btn {
      display: block;
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #1f9d55, #2ecc71);
      color: white;
      border: none;
      border-radius: 14px;
      font-size: 15px;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      box-shadow: 0 6px 20px rgba(31, 157, 85, 0.35);
      transition: transform .2s, box-shadow .2s;
    }

    .modal-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(31,157,85,.45); }

    .modal-btn-sec {
      display: block;
      width: 100%;
      padding: 13px;
      background: transparent;
      color: #444;
      border: 2px solid #e2e8f0;
      border-radius: 14px;
      font-size: 14px;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      transition: border-color .2s, color .2s;
    }

    .modal-btn-sec:hover { border-color: #1f9d55; color: #1f9d55; }

    /* Badge items en el modal */
    .modal-badge-item {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      white-space: nowrap;
    }

    /* DJ SECTION */
    .dj-section {
      padding: 90px 48px;
      background: #0f172a;
      position: relative;
      overflow: hidden;
    }

    .dj-section::before {
      content: '';
      position: absolute;
      top: -80px;
      left: -80px;
      width: 450px;
      height: 450px;
      background: radial-gradient(circle, rgba(31, 157, 85, 0.12) 0%, transparent 70%);
      pointer-events: none;
    }

    .dj-inner {
      position: relative;
      z-index: 2;
      max-width: 1100px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
    }

    .dj-texto h2 {
      font-family: 'Syne', sans-serif;
      font-size: 40px;
      color: white;
      line-height: 1.15;
      margin-bottom: 18px;
    }

    .dj-texto h2 span {
      color: #2ecc71;
    }

    .dj-texto p {
      color: rgba(255, 255, 255, 0.7);
      font-size: 16px;
      line-height: 1.7;
      margin-bottom: 28px;
    }

    .dj-generos {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 32px;
    }

    .genero-tag {
      background: rgba(31, 157, 85, 0.15);
      border: 1px solid rgba(31, 157, 85, 0.3);
      color: #2ecc71;
      padding: 6px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
    }

    .btn-verde {
      background: linear-gradient(135deg, #1f9d55, #2ecc71);
      color: white;
      padding: 14px 32px;
      border-radius: 30px;
      text-decoration: none;
      font-weight: 600;
      font-size: 15px;
      box-shadow: 0 6px 20px rgba(31, 157, 85, 0.4);
      transition: transform 0.2s;
      display: inline-block;
    }

    .btn-verde:hover {
      transform: translateY(-2px);
    }

    .dj-cards {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .dj-card {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 18px;
      padding: 20px 22px;
      display: flex;
      align-items: center;
      gap: 16px;
      transition: all 0.3s;
    }

    .dj-card:hover {
      background: rgba(31, 157, 85, 0.1);
      border-color: rgba(31, 157, 85, 0.3);
      transform: translateX(6px);
    }

    .dj-card-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1f9d55, #2ecc71);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      flex-shrink: 0;
    }

    .dj-card-info h4 {
      color: white;
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 2px;
    }

    .dj-card-info p {
      color: rgba(255, 255, 255, 0.55);
      font-size: 13px;
      margin: 0;
    }

    .dj-card-info .disponible {
      color: #2ecc71;
      font-size: 12px;
      font-weight: 600;
      margin-top: 3px;
    }

    /* CTA */
    .cta-section {
      padding: 90px 48px;
      background: linear-gradient(135deg, #0f172a, #1a2e1a);
      text-align: center;
      color: white;
    }

    .cta-section h2 {
      font-family: 'Syne', sans-serif;
      font-size: 38px;
      margin-bottom: 14px;
    }

    .cta-section p {
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
      border-color: #2ecc71;
      color: #2ecc71;
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
      color: #2ecc71;
    }

    /* RESPONSIVE */
    @media(max-width:768px) {
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

      .hero-talento {
        padding: 110px 24px 70px;
      }

      .hero-talento h1 {
        font-size: 36px;
      }

      .talent-search {
        flex-wrap: wrap;
        border-radius: 20px;
        padding: 12px;
      }

      .talent-search .sf {
        width: 100%;
      }

      .talent-search .divider {
        width: 100%;
        height: 1px;
      }

      .talent-search button {
        width: 100%;
        border-radius: 12px;
      }

      .stats-band {
        padding: 40px 24px;
      }

      .categorias,
      .talentos-section,
      .cta-section {
        padding: 60px 24px;
      }

      .dj-inner {
        grid-template-columns: 1fr;
        gap: 40px;
      }

      .dj-section {
        padding: 70px 24px;
      }

      .dj-texto h2 {
        font-size: 28px;
      }

      .modal-header {
        flex-direction: column;
        gap: 14px;
      }
    }

    /* ── SCROLL REVEAL BIDIRECCIONAL ── */
    .reveal {
      opacity: 0;
      transform: translateY(36px);
      transition: opacity .65s ease, transform .65s ease;
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    /* ── RESPONSIVE HERO COMPLETO 2026 ── */
    @media (max-width: 1200px) {
      .hero-talento h1 {
        font-size: 48px;
      }

      .navbar {
        padding: 0 32px;
      }
    }

    @media (max-width: 900px) {
      .hero-talento {
        padding: 130px 32px 80px;
      }

      .hero-talento h1 {
        font-size: 40px;
      }

      .dj-inner {
        grid-template-columns: 1fr;
        gap: 40px;
      }
    }

    @media (max-width: 768px) {
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

      .hero-talento {
        padding: 110px 20px 70px;
      }

      .hero-talento h1 {
        font-size: 30px;
        line-height: 1.15;
      }

      .talent-search {
        flex-wrap: wrap;
        border-radius: 18px;
        padding: 10px;
      }

      .talent-search .sf {
        width: 100%;
      }

      .talent-search .divider {
        width: 100%;
        height: 1px;
      }

      .talent-search button {
        width: 100%;
        border-radius: 12px;
      }

      .stats-band {
        padding: 36px 20px;
      }

      .categorias,
      .talentos-section,
      .cta-section {
        padding: 60px 20px;
      }

      .dj-section {
        padding: 60px 20px;
      }

      .dj-texto h2 {
        font-size: 26px;
      }

      .talentos-grid {
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

    @media (max-width: 600px) {
      .hero-talento h1 {
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

      .dj-cards {
        gap: 10px;
      }
    }

    @media (max-width: 480px) {
      .hero-talento h1 {
        font-size: 22px;
      }

      .hero-badge {
        font-size: 11px;
      }

      .cta-btns {
        flex-direction: column;
        align-items: center;
      }

      .btn-verde,
      .btn-outline-w {
        width: 100%;
        text-align: center;
      }

      .talentos-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
    }
  </style>
</head>

<body>
<?php if (!isset($_SESSION['usuario_id'])): ?>
<!-- PANTALLA: SIN SESIÓN -->
<div style="min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(135deg,#f0faf5 0%,#e8f5e9 100%);padding:24px;text-align:center">
  <img src="Imagenes/Quibdo.png" alt="QuibdóConecta" style="height:56px;margin-bottom:32px;opacity:.9">
  <div style="background:#fff;border-radius:24px;max-width:440px;width:100%;padding:40px 36px;box-shadow:0 20px 60px rgba(31,157,85,.12);border:1px solid #e2f5ea">
    <div style="font-size:52px;margin-bottom:16px">🔒</div>
    <h1 style="font-size:24px;font-weight:800;color:#111;font-family:'DM Sans',sans-serif;margin-bottom:10px">Inicia sesión para ver los talentos</h1>
    <p style="color:#666;font-size:15px;line-height:1.6;font-family:'DM Sans',sans-serif;margin-bottom:28px">Descubre talentos, artistas y profesionales de Quibdó y el Chocó. Crea tu cuenta gratis y conecta con ellos.</p>
    <a href="inicio_sesion.php" style="display:block;background:linear-gradient(135deg,#1f9d55,#2ecc71);color:#fff;padding:15px 24px;border-radius:14px;font-weight:700;font-size:16px;text-decoration:none;font-family:'DM Sans',sans-serif;box-shadow:0 4px 20px rgba(31,157,85,.35);margin-bottom:12px">🔑 Iniciar sesión</a>
    <a href="registro.php" style="display:block;background:#f0faf5;color:#1f9d55;padding:14px 24px;border-radius:14px;font-weight:700;font-size:15px;text-decoration:none;font-family:'DM Sans',sans-serif;border:1.5px solid #c6ebd7;margin-bottom:16px">✨ Crear cuenta gratis</a>
    <a href="index.html" style="color:#aaa;font-size:13px;text-decoration:none;font-family:'DM Sans',sans-serif">← Volver al inicio</a>
  </div>
</div>
<?php exit; endif; ?>

  <!-- NAVBAR -->
  <header class="navbar" id="navbar">
    <div class="nav-left">
      <img src="Imagenes/Quibdo.png" alt="Quibdó Conecta" class="logo">
      <span class="brand">Quibdó<span>Conecta</span></span>
    </div>
    <nav class="nav-center">
      <a href="index.html">Inicio</a>
      <a href="Empleo.php">Empleos</a>
      <a href="talentos.php" class="active">Talento</a>
      <a href="empresas.php">Empresas</a>
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
  <section class="hero-talento">
    <div class="hero-talento-content reveal">
      <span class="hero-badge">🌟 +500 talentos registrados</span>
      <h1>El <span>talento</span> del Chocó tiene nombre propio</h1>
      <p>Descubre profesionales, artistas, músicos, DJs y expertos de toda la región listos para conectar con
        oportunidades reales.</p>
      <div class="talent-search">
        <div class="sf">
          <span class="icon">🔍</span>
          <input type="text" id="searchNombre" placeholder="Nombre, profesión o habilidad…" autocomplete="off">
        </div>
        <div class="divider"></div>
        <div class="sf">
          <span class="icon">📍</span>
          <input type="text" id="searchUbicacion" placeholder="Ciudad (ej. Quibdó)">
        </div>
        <button id="searchBtn">Buscar talento</button>
      </div>
      <div class="hero-links">
        <a href="registro.php" class="hero-link">✨ Publicar mi talento gratis</a>
        <a href="#talentos" class="hero-link">👇 Ver todos los talentos</a>
      </div>
    </div>
  </section>

  <!-- STATS -->
  <div class="stats-band">
    <div class="s reveal">
      <h3>+500</h3>
      <p>Talentos registrados</p>
    </div>
    <div class="s reveal">
      <h3>+8</h3>
      <p>Categorías de talento</p>
    </div>
    <div class="s reveal">
      <h3>+120</h3>
      <p>Empresas conectadas</p>
    </div>
    <div class="s reveal">
      <h3>Chocó</h3>
      <p>Región cubierta</p>
    </div>
  </div>

  <!-- CATEGORÍAS -->
  <section class="categorias">
    <h2 class="reveal">Filtra por categoría</h2>
    <p class="sub">Encuentra el talento perfecto según el área que necesitas</p>
    <div class="categorias-grid" id="catGrid">
      <button class="cat-btn activa" data-cat="todos">🌐 Todos <span class="count"
          id="cnt-todos"><?= count($dbTalentos) ?></span></button>
      <button class="cat-btn" data-cat="tecnologia">💻 Tecnología <span class="count" id="cnt-tec">0</span></button>
      <button class="cat-btn" data-cat="arte">🎨 Arte &amp; Diseño <span class="count" id="cnt-art">0</span></button>
      <button class="cat-btn" data-cat="musica">🎵 Música &amp; DJ <span class="count" id="cnt-mus">0</span></button>
      <button class="cat-btn" data-cat="educacion">📚 Educación <span class="count" id="cnt-edu">0</span></button>
      <button class="cat-btn" data-cat="salud">🏥 Salud <span class="count" id="cnt-sal">0</span></button>
      <button class="cat-btn" data-cat="administrativo">💼 Administrativo <span class="count"
          id="cnt-adm">0</span></button>
    </div>
    <div class="filtros-tipo" id="filtrosTipo">
      <button class="filtro-btn activo" data-tipo="todos">Todos</button>
      <button class="filtro-btn" data-tipo="disponible">Disponible ahora</button>
      <button class="filtro-btn" data-tipo="destacado">⭐ Destacado</button>
      <button class="filtro-btn" data-tipo="freelance">Freelance</button>
    </div>
  </section>

  <!-- TALENTOS -->
  <section class="talentos-section" id="talentos">
    <div class="talentos-header">
      <h2 class="reveal">Talentos del Chocó</h2>
      <span class="resultados-count" id="resCount"><?= count($dbTalentos) ?>
        encontrado<?= count($dbTalentos) !== 1 ? 's' : '' ?></span>
    </div>

    <div class="talentos-grid" id="talentosGrid">
      <?php if (!empty($dbTalentos)): ?>
        <?php foreach ($dbTalentos as $talento):
          $nb = htmlspecialchars(trim($talento['nombre'] . ' ' . $talento['apellido']));
          $ini = strtoupper(mb_substr($talento['nombre'], 0, 1) . mb_substr($talento['apellido'], 0, 1));
          $pro = htmlspecialchars($talento['profesion'] ?: 'Talento local');
          $ciu = htmlspecialchars($talento['ciudad'] ?: 'Chocó');
          $bio = htmlspecialchars($talento['bio'] ?: 'Profesional del Chocó disponible para nuevas oportunidades.');
          $ski = htmlspecialchars($talento['skills'] ?: '');
          $grd = htmlspecialchars($talento['avatar_color'] ?: 'linear-gradient(135deg,#1f9d55,#2ecc71)');
          $arr = array_filter(array_map('trim', explode(',', $talento['skills'] ?? '')));
          // Badge principal a mostrar
          $badgePrincipalLabel = '';
          if ($talento['tiene_top'])
            $badgePrincipalLabel = '<span class="badge-t" style="background:#ff444422;color:#ff4444;border:1px solid #ff444455">👑 Top</span>';
          elseif ($talento['tiene_premium'])
            $badgePrincipalLabel = '<span class="badge-t" style="background:#ffab0022;color:#ffab00;border:1px solid #ffab0055">⭐ Premium</span>';
          elseif ($talento['tiene_destacado'])
            $badgePrincipalLabel = '<span class="badge-t" style="background:#aa44ff22;color:#aa44ff;border:1px solid #aa44ff55">🏅 Destacado</span>';
          elseif ($talento['tiene_verificado'])
            $badgePrincipalLabel = '<span class="badge-t badge-verificado-principal">✓ Verificado</span>';
          else
            $badgePrincipalLabel = '';
          ?>

          <div class="talento-card" data-cat="<?= detectarCategoria($talento['profesion'], $talento['skills']) ?>"
            data-tipo="disponible" data-uid="<?= $talento['id'] ?>" data-nombre="<?= $nb ?>" data-profesion="<?= $pro ?>" data-ubicacion="<?= $ciu ?>"
            data-skills="<?= $ski ?>" data-grad="<?= $grd ?>" data-initials="<?= $ini ?>" data-desc="<?= $bio ?>"
            data-foto="<?= !empty($talento['foto']) ? 'uploads/fotos/' . htmlspecialchars($talento['foto']) : '' ?>"
            data-badges-json="<?= htmlspecialchars(json_encode($talento['badges'] ?? []), ENT_QUOTES) ?>"
            data-tiene-verificado="<?= $talento['tiene_verificado'] ? '1' : '0' ?>"
            data-tiene-premium="<?= $talento['tiene_premium'] ? '1' : '0' ?>"
            data-tiene-destacado="<?= $talento['tiene_destacado'] ? '1' : '0' ?>"
            data-tiene-top="<?= $talento['tiene_top'] ? '1' : '0' ?>">
            <?= $badgePrincipalLabel ?>

            <div class="talento-avatar" style="background:<?= $grd ?>;overflow:hidden">
              <?php if (!empty($talento['foto'])): ?>
                <img src="uploads/fotos/<?= htmlspecialchars($talento['foto']) ?>" alt="<?= $ini ?>"
                  style="width:100%;height:100%;object-fit:cover;display:block">
              <?php else: ?>
                <?= $ini ?>
              <?php endif; ?>
            </div>
            <h3><?= $nb ?></h3>
            <p class="profesion"><?= $pro ?></p>
            <p class="ubicacion">📍 <?= $ciu ?></p>
            <?php if (!empty($talento['badges_html'])): ?>
              <div style="margin:6px 0"><?= $talento['badges_html'] ?></div>
            <?php endif; ?>
            <div class="talento-tags">
              <?php foreach (array_slice($arr, 0, 3) as $sk): ?>
                <span class="tag"><?= htmlspecialchars($sk) ?></span>
              <?php endforeach; ?>
              <?php if (count($arr) > 3): ?><span class="tag">+<?= count($arr) - 3 ?> más</span><?php endif; ?>
            </div>
            <button class="btn-perfil">Ver perfil completo</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (empty($dbTalentos)): ?>
        <div class="no-results" style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#999">
          <span style="font-size:52px;display:block;margin-bottom:14px">🌟</span>
          <p style="font-size:16px;font-weight:600;color:#555;margin-bottom:8px">Aún no hay talentos registrados</p>
          <p style="font-size:14px">¡Sé el primero en publicar tu perfil!</p>
          <a href="registro.php"
            style="display:inline-block;margin-top:20px;padding:12px 28px;background:linear-gradient(135deg,#1f9d55,#2ecc71);color:white;border-radius:30px;text-decoration:none;font-weight:700">✨
            Publicar mi talento</a>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <!-- DJ SECTION -->
  <section class="dj-section">
    <div class="dj-inner">
      <div class="dj-texto">
        <h2 class="reveal">El ritmo del <span>Chocó</span> también es un talento</h2>
        <p>Quibdó tiene una de las escenas musicales más ricas de Colombia. DJs, productores y artistas locales están
          listos para animar tus eventos, bodas, fiestas y festivales.</p>
        <div class="dj-generos">
          <span class="genero-tag">🥁 Chirimía</span>
          <span class="genero-tag">💃 Salsa</span>
          <span class="genero-tag">🎵 Afrobeats</span>
          <span class="genero-tag">🎤 Reggaetón</span>
          <span class="genero-tag">🎧 Electrónica</span>
          <span class="genero-tag">🪗 Vallenato</span>
        </div>
        <a href="registro.php" class="btn-verde">Registrar mi talento musical</a>
      </div>
      <div class="dj-cards">
        <div class="dj-card">
          <div class="dj-card-avatar">🎧</div>
          <div class="dj-card-info">
            <h4>DJ Klave</h4>
            <p>Afrobeats y Electrónica</p>
            <p class="disponible">✅ Disponible para eventos</p>
          </div>
        </div>
        <div class="dj-card">
          <div class="dj-card-avatar">🎵</div>
          <div class="dj-card-info">
            <h4>DJ Pacífico</h4>
            <p>Salsa y Música del Litoral</p>
            <p class="disponible">✅ Disponible para eventos</p>
          </div>
        </div>
        <div class="dj-card">
          <div class="dj-card-avatar">🎤</div>
          <div class="dj-card-info">
            <h4>DJ Luna Negra</h4>
            <p>Reggaetón, Urbano y Pop Latino</p>
            <p class="disponible">✅ Disponible para eventos</p>
          </div>
        </div>
        <div class="dj-card">
          <div class="dj-card-avatar">🥁</div>
          <div class="dj-card-info">
            <h4>Chirimía Chocó</h4>
            <p>Música tradicional del Pacífico</p>
            <p class="disponible">✅ Disponible para eventos</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta-section">
    <h2 class="reveal">¿Eres un talento del Chocó?</h2>
    <p>Regístrate gratis, crea tu perfil profesional y conecta con empresas, organizadores y oportunidades reales en tu
      región.</p>
    <div class="cta-btns">
      <a href="registro.php" class="btn-verde">✨ Publicar mi talento gratis</a>
      <a href="Empleo.html" class="btn-outline-w">💼 Buscar empleos</a>
    </div>
  </section>

  <!-- MODAL PERFIL -->
  <div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">
      <button class="modal-close" id="modalClose">✕</button>

      <!-- HEADER con avatar + nombre -->
      <div class="modal-header">
        <div class="modal-avatar" id="mAvatar"></div>
        <div class="modal-info">
          <h2 id="mNombre"></h2>
          <p class="m-profesion" id="mProfesion"></p>
          <p class="m-ubicacion" id="mUbicacion"></p>
        </div>
      </div>

      <div class="modal-body">

        <!-- BADGES del admin (todos los asignados) -->
        <div id="mBadgesWrap" style="margin-bottom:16px;display:none">
          <div id="mBadgesRow" style="display:flex;flex-wrap:wrap;gap:7px"></div>
        </div>

        <!-- SEPARADOR -->
        <div style="height:1px;background:#f1f5f9;margin-bottom:18px"></div>

        <!-- HABILIDADES -->
        <div class="modal-habilidades" id="mHabilidadesWrap">
          <h4>🛠 Habilidades</h4>
          <div class="modal-tags" id="mTags"></div>
        </div>

        <!-- BIO -->
        <div id="mBioWrap" style="margin-bottom:20px">
          <h4 style="font-size:13px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">📝 Sobre mí</h4>
          <p class="modal-desc" id="mDesc"></p>
        </div>

        <!-- Galería rápida -->
        <div id="mGaleriaLoadT" style="display:none;color:#aaa;font-size:12px;margin-bottom:12px">⚙️ Cargando galería…</div>
        <div id="mGaleriaWrapT" style="display:none;margin-bottom:20px">
          <h4 style="font-size:13px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">📸 Evidencias</h4>
          <div id="mGaleriaGridT" style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px;border-radius:12px;overflow:hidden"></div>
        </div>

        <!-- BOTONES -->
        <div style="display:flex;flex-direction:column;gap:10px">
          <a href="chat.php" id="mContactar" class="modal-btn">💬 Contactar por chat</a>
          <a href="#" id="mBtnPerfil" class="modal-btn-sec">👤 Ver perfil completo</a>
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
      if (!ham.contains(e.target) && !mob.contains(e.target)) { ham.classList.remove('open'); mob.classList.remove('open'); }
    });

    // ALL CARDS
    const allCards = Array.from(document.querySelectorAll('.talento-card'));

    // Calcular conteos por categoría dinámicamente
    (function () {
      const map = { tecnologia: 'cnt-tec', arte: 'cnt-art', musica: 'cnt-mus', educacion: 'cnt-edu', salud: 'cnt-sal', administrativo: 'cnt-adm' };
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
      document.getElementById('resCount').textContent = n + ' encontrado' + (n !== 1 ? 's' : '');
    }

    function aplicarFiltros() {
      let visible = 0;
      allCards.forEach(c => {
        const cat = c.dataset.cat || '';
        const tipo = c.dataset.tipo || '';
        const texto = (c.dataset.nombre + ' ' + c.dataset.profesion + ' ' + c.dataset.skills + ' ' + c.dataset.ubicacion).toLowerCase();
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
        nr.innerHTML = '<span class="nr-icon">🔍</span><p>No encontramos talentos con esos criterios. Intenta con otra búsqueda.</p>';
        document.getElementById('talentosGrid').appendChild(nr);
      }
      nr.style.display = visible === 0 ? '' : 'none';
    }

    // CATEGORÍAS
    document.querySelectorAll('.cat-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('activa'));
        btn.classList.add('activa');
        catActiva = btn.dataset.cat;
        aplicarFiltros();
        document.getElementById('talentos').scrollIntoView({ behavior: 'smooth' });
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
      const logueado = <?php echo isset($_SESSION['usuario_id']) ? 'true' : 'false'; ?>;
      if (!logueado) { abrirModalLogin(); return; }
      const nombre = document.getElementById('searchNombre').value.trim().toLowerCase();
      const ubicacion = document.getElementById('searchUbicacion').value.trim().toLowerCase();
      textoBusqueda = (nombre + ' ' + ubicacion).trim();
      aplicarFiltros();
      document.getElementById('talentos').scrollIntoView({ behavior: 'smooth' });
    }
    document.getElementById('searchBtn').addEventListener('click', buscar);
    document.getElementById('searchNombre').addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });
    document.getElementById('searchUbicacion').addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); });

    // MODAL
    const overlay = document.getElementById('modalOverlay');
    document.getElementById('modalClose').addEventListener('click', () => overlay.classList.remove('open'));
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') overlay.classList.remove('open'); });

    function abrirModal(card) {
      const grad = card.dataset.grad || 'linear-gradient(135deg,#1f9d55,#2ecc71)';
      const av = document.getElementById('mAvatar');
      av.style.background = grad;
      const foto = card.dataset.foto || '';
      if (foto) {
        av.innerHTML = `<img src="${foto}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
      } else {
        av.innerHTML = '';
        av.textContent = card.dataset.initials || '';
      }
      document.getElementById('mNombre').textContent = card.dataset.nombre || '';
      document.getElementById('mProfesion').textContent = card.dataset.profesion || '';
      document.getElementById('mUbicacion').textContent = '📍 ' + (card.dataset.ubicacion || '');
      document.getElementById('mDesc').textContent = card.dataset.desc || '';

      // ── BADGES desde JSON del servidor ──────────────────────
      const badgesWrap = document.getElementById('mBadgesWrap');
      const badgesRow  = document.getElementById('mBadgesRow');
      badgesRow.innerHTML = '';
      let badgesData = [];
      try { badgesData = JSON.parse(card.dataset.badgesJson || '[]'); } catch(e) {}

      const esVerificado = card.dataset.tieneVerificado === '1';
      const esPremium    = card.dataset.tienePremium    === '1';
      const esDestacado  = card.dataset.tieneDestacado  === '1';
      const esTop        = card.dataset.tieneTop        === '1';

      if (badgesData.length > 0) {
        badgesData.forEach(b => {
          const color = b.color || '#1f9d55';
          const span = document.createElement('span');
          span.className = 'modal-badge-item';
          span.title = b.descripcion || '';
          span.style.cssText = `background:${color}22;border:1px solid ${color}55;color:${color}`;
          span.textContent = (b.emoji || '🏅') + ' ' + (b.nombre || '');
          badgesRow.appendChild(span);
        });
        badgesWrap.style.display = 'block';
      } else if (esVerificado || esPremium || esDestacado || esTop) {
        const fallbacks = [];
        if (esTop)        fallbacks.push({emoji:'👑', nombre:'Top',        color:'#ff4444'});
        if (esPremium)    fallbacks.push({emoji:'⭐', nombre:'Premium',    color:'#ffab00'});
        if (esDestacado)  fallbacks.push({emoji:'🏅', nombre:'Destacado',  color:'#aa44ff'});
        if (esVerificado) fallbacks.push({emoji:'✓',  nombre:'Verificado', color:'#1f9d55'});
        fallbacks.forEach(b => {
          const span = document.createElement('span');
          span.className = 'modal-badge-item';
          span.style.cssText = `background:${b.color}22;border:1px solid ${b.color}55;color:${b.color}`;
          span.textContent = b.emoji + ' ' + b.nombre;
          badgesRow.appendChild(span);
        });
        badgesWrap.style.display = 'block';
      } else {
        badgesWrap.style.display = 'none';
      }

      // ── HABILIDADES ──────────────────────────────────────────
      const skills = (card.dataset.skills || '').split(',').map(s=>s.trim()).filter(Boolean);
      const mTags = document.getElementById('mTags');
      if (skills.length > 0) {
        mTags.innerHTML = skills.map(s => `<span class="tag">${s}</span>`).join('');
        document.getElementById('mHabilidadesWrap').style.display = 'block';
      } else {
        document.getElementById('mHabilidadesWrap').style.display = 'none';
      }

      // ── ENLACES ──────────────────────────────────────────────
      const uid = card.dataset.uid || '';
      document.getElementById('mContactar').href = uid ? `chat.php?con=${uid}` : 'chat.php';
      const btnPerfil = document.getElementById('mBtnPerfil');
      if (btnPerfil) btnPerfil.href = uid ? `perfil.php?id=${uid}&tipo=talento` : '#';

      // ── GALERÍA ──────────────────────────────────────────────
      const galeriaWrap = document.getElementById('mGaleriaWrapT');
      const galeriaGrid = document.getElementById('mGaleriaGridT');
      const galeriaLoad = document.getElementById('mGaleriaLoadT');
      if (galeriaWrap) { galeriaWrap.style.display='none'; galeriaGrid.innerHTML=''; galeriaLoad.style.display='block'; }

      if (uid) {
        const fd = new FormData();
        fd.append('_action', 'registrar_vista');
        fd.append('usuario_id', uid);
        fd.append('seccion', 'talentos');
        fetch('dashboard.php', { method:'POST', body:fd }).catch(()=>{});
        if (galeriaWrap) {
          fetch(`galeria_publica.php?id=${uid}`)
            .then(r=>r.json())
            .then(items=>{
              galeriaLoad.style.display='none';
              if (!items.length) return;
              galeriaWrap.style.display='block';
              galeriaGrid.innerHTML = items.slice(0,6).map(it=>{
                const isV = it.tipo==='video';
                const ytM = it.url_video&&it.url_video.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
                const th  = ytM ? `https://img.youtube.com/vi/${ytM[1]}/mqdefault.jpg` : (it.archivo?`uploads/galeria/${it.archivo}`:'');
                if(!th) return '';
                return `<div style="border-radius:8px;overflow:hidden;aspect-ratio:1;cursor:pointer;position:relative" onclick="${isV&&it.url_video?`window.open('${it.url_video}','_blank')`:`window.open('uploads/galeria/${it.archivo}','_blank')`}">
                  <img src="${th}" style="width:100%;height:100%;object-fit:cover" loading="lazy">
                  ${isV?'<div style="position:absolute;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;font-size:22px">▶️</div>':''}
                </div>`;
              }).join('');
            }).catch(()=>{ galeriaLoad.style.display='none'; });
        }
      } else { if(galeriaLoad) galeriaLoad.style.display='none'; }

      overlay.classList.add('open');
    }

    allCards.forEach(card => {
      card.querySelector('.btn-perfil').addEventListener('click', () => abrirModal(card));
    });

    // Scroll reveal BIDIRECCIONAL (cards + .reveal elements)
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
    // Also observe .reveal elements
    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
  </script>

<!-- Widget de sesión activa — QuibdóConecta -->
<script src="js/sesion_widget.js"></script>
<!-- Modal: Iniciar sesión para buscar -->
<div id="qc-login-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10000;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px)" onclick="if(event.target===this)cerrarModalLogin()">
  <div style="background:#fff;border-radius:24px;max-width:420px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.22);overflow:hidden;animation:qcLoginIn .35s cubic-bezier(.34,1.56,.64,1)">
    <div style="background:linear-gradient(135deg,#1f9d55,#2ecc71);padding:28px 28px 22px;text-align:center;position:relative">
      <button onclick="cerrarModalLogin()" style="position:absolute;top:14px;right:16px;background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center">✕</button>
      <div style="font-size:40px;margin-bottom:8px">🌟</div>
      <h2 style="margin:0;color:#fff;font-size:22px;font-weight:800;font-family:'DM Sans',sans-serif">Inicia sesión para buscar</h2>
      <p style="margin:8px 0 0;color:rgba(255,255,255,.88);font-size:14px;font-family:'DM Sans',sans-serif">Descubre talentos de Quibdó y el Chocó</p>
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