<?php
// Empleo.php — Vacantes reales de BD + Trabajos Culturales del Chocó
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// ── Manejar POST: solicitar vacante desde Empleo.php ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'solicitar_vacante_pub') {
  header('Content-Type: application/json');
  if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión para solicitar esta vacante.', 'login' => true]);
    exit;
  }
  if (($_SESSION['usuario_tipo'] ?? '') !== 'candidato') {
    echo json_encode(['ok' => false, 'msg' => 'Solo candidatos pueden solicitar vacantes.']);
    exit;
  }
  $empleo_id = (int) ($_POST['empleo_id'] ?? 0);
  if (!$empleo_id) { echo json_encode(['ok' => false, 'msg' => 'Vacante no válida.']); exit; }
  try {
    if (file_exists(__DIR__ . '/Php/db.php')) require_once __DIR__ . '/Php/db.php';
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS solicitudes_empleo (
      id INT AUTO_INCREMENT PRIMARY KEY,
      empleo_id INT NOT NULL,
      candidato_id INT NOT NULL,
      mensaje TEXT DEFAULT NULL,
      estado ENUM('pendiente','vista','aceptada','rechazada') DEFAULT 'pendiente',
      creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_solicitud (empleo_id, candidato_id),
      INDEX idx_empleo (empleo_id),
      INDEX idx_candidato (candidato_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $chkS = $db->prepare("SELECT id FROM solicitudes_empleo WHERE empleo_id=? AND candidato_id=?");
    $chkS->execute([$empleo_id, $_SESSION['usuario_id']]);
    if ($chkS->fetch()) {
      echo json_encode(['ok' => false, 'msg' => 'Ya aplicaste a esta vacante.', 'ya_aplicado' => true]);
      exit;
    }
    $mensaje = substr(trim($_POST['mensaje'] ?? ''), 0, 1000);
    $db->prepare("INSERT INTO solicitudes_empleo (empleo_id, candidato_id, mensaje) VALUES (?,?,?)")
       ->execute([$empleo_id, $_SESSION['usuario_id'], $mensaje ?: null]);
    echo json_encode(['ok' => true, 'msg' => '✅ ¡Solicitud enviada! La empresa revisará tu perfil.']);
  } catch (Exception $ex) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $ex->getMessage()]);
  }
  exit;
}

// ── Detectar sesión para pasar al JS ──────────────────────────
$usuarioLogueado   = isset($_SESSION['usuario_id']);
$usuarioEsCandidato = $usuarioLogueado && ($_SESSION['usuario_tipo'] ?? '') === 'candidato';
$usuarioNombre     = htmlspecialchars($_SESSION['usuario_nombre'] ?? '');

$vacantesDB = [];
$totalVacantes = 0;
$totalEmpresas = 0;

if (file_exists(__DIR__ . '/Php/db.php')) {
    try {
        require_once __DIR__ . '/Php/db.php';
        $db = getDB();

        // Vacantes activas con info de empresa
        $stmt = $db->query("
            SELECT e.id, e.titulo, e.descripcion, e.categoria, e.ciudad,
                   e.salario_min, e.salario_max, e.modalidad, e.tipo,
                   e.creado_en, e.vence_en,
                   u.nombre AS empresa_nombre, u.verificado,
                   COALESCE(pe.nombre_empresa, u.nombre) AS nombre_empresa,
                   pe.logo, pe.avatar_color
            FROM empleos e
            INNER JOIN usuarios u ON u.id = e.empresa_id AND u.activo = 1
            LEFT JOIN perfiles_empresa pe ON pe.id = (
                SELECT MAX(id) FROM perfiles_empresa WHERE usuario_id = u.id
            )
            WHERE e.activo = 1
              AND (e.vence_en IS NULL OR e.vence_en >= CURDATE())
            ORDER BY e.creado_en DESC
            LIMIT 60
        ");
        $vacantesDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalVacantes = count($vacantesDB);

        // Total empresas
        $totalEmpresas = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE tipo='empresa' AND activo=1")->fetchColumn();
    } catch (Exception $e) {
        // DEBUG: mostrar error real
        die(json_encode(['ok' => false, 'msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]));
    }
}

function tiempoTranscurrido($fecha) {
    $diff = time() - strtotime($fecha);
    if ($diff < 3600)   return 'hace ' . floor($diff/60) . ' min';
    if ($diff < 86400)  return 'hace ' . floor($diff/3600) . ' h';
    if ($diff < 604800) return 'hace ' . floor($diff/86400) . ' días';
    return date('d/m/Y', strtotime($fecha));
}

function formatSalario($min, $max) {
    if (!$min && !$max) return 'A convenir';
    $fmt = fn($n) => '$' . number_format((float)$n, 0, ',', '.');
    if ($min && $max) return $fmt($min) . ' – ' . $fmt($max);
    return $min ? 'Desde ' . $fmt($min) : 'Hasta ' . $fmt($max);
}

$catIconos = [
    'administrativo' => '💼', 'tecnologia' => '💻', 'tecnología' => '💻',
    'educacion' => '📚', 'educación' => '📚', 'salud' => '🏥',
    'gastronomia' => '🍽️', 'gastronomía' => '🍽️', 'tecnico' => '🔧', 'técnico' => '🔧',
    'transporte' => '🚗', 'arte' => '🎵', 'arte & música' => '🎵',
    'construccion' => '🏗️', 'construcción' => '🏗️', 'default' => '💼'
];
function catIcono($cat, $iconos) {
    $k = strtolower(trim($cat ?? ''));
    return $iconos[$k] ?? $iconos['default'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Empleos & Cultura – Quibdó Conecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'DM Sans',Arial,sans-serif;background:#f9fafb;color:#111}

    /* ── NAVBAR ── */
    .navbar{position:fixed;top:0;left:0;width:100%;height:78px;display:flex;align-items:center;justify-content:space-between;padding:0 48px;background:#fff;border-bottom:1px solid rgba(0,0,0,.08);box-shadow:0 2px 12px rgba(0,0,0,.05);z-index:1000;transition:box-shadow .3s}
    .navbar.abajo{box-shadow:0 4px 20px rgba(0,0,0,.12)}
    .nav-left{display:flex;align-items:center;gap:12px}
    .logo{width:52px;height:auto;filter:drop-shadow(0 1px 1px rgba(0,0,0,.15))}
    .brand{font-size:22px;font-weight:800;color:#111}
    .brand span{color:#1f9d55}
    .nav-center{display:flex;align-items:center;gap:22px;flex:1;justify-content:center}
    .nav-center a{color:#333;text-decoration:none;font-size:15px;font-weight:500;padding:6px 4px;position:relative}
    .nav-center a::after{content:"";position:absolute;left:0;bottom:-6px;width:0%;height:2px;background:#1f9d55;transition:width .3s}
    .nav-center a:hover::after,.nav-center a.active::after{width:100%}
    .nav-right{display:flex;align-items:center;gap:18px}
    .login{color:#1f9d55;text-decoration:none;font-size:14.5px;font-weight:600;padding:8px 18px;border:2px solid #1f9d55;border-radius:30px;transition:all .3s}
    .login:hover{background:#1f9d55;color:white}
    .register{background:#1f9d55;color:white;padding:9px 20px;border-radius:25px;text-decoration:none;font-weight:600;font-size:14.5px;box-shadow:0 4px 12px rgba(31,157,85,.35)}
    .register:hover{background:#166f3d}
    .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:4px}
    .hamburger span{display:block;width:26px;height:2.5px;background:#111;border-radius:4px;transition:all .3s}
    .hamburger.open span:nth-child(1){transform:translateY(7.5px) rotate(45deg)}
    .hamburger.open span:nth-child(2){opacity:0;transform:scaleX(0)}
    .hamburger.open span:nth-child(3){transform:translateY(-7.5px) rotate(-45deg)}
    .mobile-menu{display:none;position:fixed;top:78px;left:0;width:100%;background:white;border-bottom:1px solid rgba(0,0,0,.08);box-shadow:0 12px 32px rgba(0,0,0,.12);flex-direction:column;padding:20px 24px;gap:6px;z-index:999}
    .mobile-menu.open{display:flex}
    .mobile-menu a{color:#333;text-decoration:none;font-size:16px;font-weight:500;padding:12px 0;border-bottom:1px solid rgba(0,0,0,.06)}
    .mobile-auth{display:flex;gap:12px;margin-top:14px}
    .mobile-auth a{flex:1;text-align:center;padding:11px;border-radius:25px;font-weight:600;font-size:15px;text-decoration:none}
    .mobile-auth .m-login{border:2px solid #1f9d55;color:#1f9d55}
    .mobile-auth .m-reg{background:#1f9d55;color:white}

    /* ── HERO ── */
    .hero{padding:160px 48px 100px;background:linear-gradient(135deg,#0b3a7e 0%,#0f172a 60%,#0b3a7e 100%);text-align:center;position:relative;overflow:hidden}
    .hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 30% 50%,rgba(31,157,85,.12) 0%,transparent 60%),radial-gradient(ellipse at 70% 50%,rgba(11,58,126,.2) 0%,transparent 60%)}
    .hero-content{position:relative;z-index:2;max-width:720px;margin:0 auto}
    .hero h1{font-family:'Syne',sans-serif;font-size:56px;font-weight:800;color:white;line-height:1.1;margin-bottom:18px}
    .hero h1 span{color:#2ecc71}
    .hero-subtitle{font-size:18px;color:rgba(255,255,255,.75);margin-bottom:40px;line-height:1.6}
    .search-bar{display:flex;align-items:center;background:white;border-radius:50px;padding:8px;max-width:700px;margin:0 auto;box-shadow:0 12px 35px rgba(0,0,0,.2)}
    .search-field{display:flex;align-items:center;gap:10px;padding:12px 18px;flex:1}
    .search-field .icon{font-size:18px;opacity:.6}
    .search-field input{border:none;outline:none;font-size:15px;width:100%;font-family:'DM Sans',sans-serif}
    .sdiv{width:1px;height:35px;background:rgba(0,0,0,.12)}
    .search-btn{background:linear-gradient(135deg,#0b3a7e,#1e6fd9);color:white;border:none;border-radius:40px;padding:14px 26px;font-size:15px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .2s}
    .search-btn:hover{opacity:.9}
    .hero-stats{display:flex;justify-content:center;gap:40px;margin-top:50px;flex-wrap:wrap}
    .stat{text-align:center;color:white}
    .stat-num{font-family:'Syne',sans-serif;font-size:34px;font-weight:800;color:#2ecc71}
    .stat-label{font-size:13px;color:rgba(255,255,255,.6);margin-top:4px}

    /* ── TABS ── */
    .tabs-nav{background:white;border-bottom:1px solid rgba(0,0,0,.08);position:sticky;top:78px;z-index:500;display:flex;justify-content:center;padding:0 48px}
    .tab-link{padding:18px 28px;font-size:15px;font-weight:600;color:#666;text-decoration:none;border-bottom:3px solid transparent;transition:all .25s;cursor:pointer;background:none;border-top:none;border-left:none;border-right:none;font-family:'DM Sans',sans-serif;display:flex;align-items:center;gap:8px}
    .tab-link:hover{color:#1f9d55}
    .tab-link.activo{color:#1f9d55;border-bottom-color:#1f9d55}
    .tab-panel{display:none}
    .tab-panel.activo{display:block}

    /* ── CATEGORÍAS ── */
    .categorias{padding:60px 48px;background:#f9fafb;text-align:center}
    .categorias h2{font-family:'Syne',sans-serif;font-size:34px;font-weight:800;margin-bottom:8px}
    .subtitulo{color:#666;font-size:15px;margin-bottom:40px}
    .categorias-grid{display:flex;flex-wrap:wrap;gap:12px;justify-content:center;max-width:900px;margin:0 auto}
    .categoria-card{background:white;border:2px solid rgba(0,0,0,.07);border-radius:16px;padding:18px 22px;text-align:center;cursor:pointer;transition:all .25s;display:flex;flex-direction:column;align-items:center;gap:8px;font-family:'DM Sans',sans-serif;min-width:110px}
    .categoria-card:hover,.categoria-card.activa{border-color:#1f9d55;background:#edfaf3;box-shadow:0 6px 20px rgba(31,157,85,.15)}
    .categoria-card .emoji{font-size:28px}
    .categoria-card h4{font-size:13px;font-weight:700;color:#333}
    .categoria-card .count{font-size:11px;color:#888;font-weight:500}
    .categoria-card.activa .count{color:#1f9d55}

    /* ── SECCIÓN EMPLEOS ── */
    .empleos{padding:60px 48px}
    .empleos-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
    .empleos-header h2{font-family:'Syne',sans-serif;font-size:32px;font-weight:800}
    .filtros{display:flex;gap:8px;flex-wrap:wrap}
    .filtro-btn{padding:8px 18px;border:1.5px solid rgba(0,0,0,.12);border-radius:25px;background:white;font-size:13px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s;color:#444}
    .filtro-btn.activo,.filtro-btn:hover{background:#1f9d55;border-color:#1f9d55;color:white}
    .resultado-count{font-size:14px;color:#888;font-weight:500;width:100%}
    .empleos-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:22px;margin-top:8px}
    .empleo-card{background:white;border:1px solid rgba(0,0,0,.07);border-radius:20px;padding:26px;transition:all .3s;position:relative;overflow:hidden}
    .empleo-card:hover{transform:translateY(-5px);box-shadow:0 14px 40px rgba(0,0,0,.1);border-color:rgba(31,157,85,.2)}
    .badge{position:absolute;top:16px;right:16px;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;background:#fff8e1;color:#b7791f}
    .badge.remoto{background:#e3f2fd;color:#1565c0}
    .badge.cultura{background:#f3e5f5;color:#6a1b9a}
    .empleo-icon{font-size:38px;display:block;margin-bottom:12px}
    .empleo-card h3{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;margin-bottom:4px;line-height:1.2}
    .empresa{color:#1f9d55;font-weight:600;font-size:14px;margin-bottom:3px}
    .ubicacion{color:#999;font-size:13px;margin-bottom:12px}
    .empleo-salario{font-size:13px;font-weight:700;color:#333;margin-bottom:12px}
    .empleo-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
    .tag{background:#f1f5f9;color:#444;font-size:12px;padding:4px 10px;border-radius:20px;font-weight:500}
    .empleo-fecha{font-size:12px;color:#bbb;margin-bottom:16px}
    .btn-ver{display:block;text-align:center;padding:10px;border:2px solid #1f9d55;color:#1f9d55;border-radius:25px;font-weight:700;font-size:14px;cursor:pointer;width:100%;font-family:'DM Sans',sans-serif;background:transparent;transition:all .3s}
    .btn-ver:hover{background:#1f9d55;color:white}
    .no-results{grid-column:1/-1;text-align:center;padding:60px 20px;color:#999}
    .no-results .nr-icon{font-size:48px;display:block;margin-bottom:14px}
    .empty-bd{grid-column:1/-1;text-align:center;padding:60px 20px;color:#999}
    .empty-bd .ei{font-size:48px;display:block;margin-bottom:12px}

    /* ── SECCIÓN CULTURAL ── */
    .cultural{padding:60px 48px;background:linear-gradient(135deg,#1a1a2e 0%,#16213e 50%,#0f3460 100%)}
    .cultural-header{text-align:center;margin-bottom:48px}
    .cultural-badge{display:inline-block;background:rgba(163,230,53,.15);border:1px solid rgba(163,230,53,.35);color:#a3e635;font-size:12px;font-weight:700;padding:5px 18px;border-radius:30px;letter-spacing:1px;text-transform:uppercase;margin-bottom:18px}
    .cultural h2{font-family:'Syne',sans-serif;font-size:38px;font-weight:800;color:white;margin-bottom:12px;line-height:1.1}
    .cultural h2 span{color:#a3e635}
    .cultural-sub{color:rgba(255,255,255,.6);font-size:15px;max-width:600px;margin:0 auto;line-height:1.6}
    .cultural-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;max-width:1200px;margin:0 auto}
    .cult-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:24px;transition:all .3s;cursor:pointer}
    .cult-card:hover{background:rgba(255,255,255,.1);border-color:rgba(163,230,53,.4);transform:translateY(-4px);box-shadow:0 12px 36px rgba(0,0,0,.3)}
    .cult-tipo{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px;padding:4px 10px;border-radius:20px;display:inline-block}
    .cult-tipo.convocatoria{background:rgba(163,230,53,.15);color:#a3e635}
    .cult-tipo.evento{background:rgba(99,102,241,.2);color:#818cf8}
    .cult-tipo.beca{background:rgba(245,158,11,.15);color:#fbbf24}
    .cult-tipo.residencia{background:rgba(236,72,153,.15);color:#f472b6}
    .cult-card h3{font-family:'Syne',sans-serif;font-size:17px;font-weight:800;color:white;margin-bottom:6px;line-height:1.2}
    .cult-org{color:rgba(255,255,255,.55);font-size:13px;font-weight:600;margin-bottom:8px}
    .cult-desc{font-size:13px;color:rgba(255,255,255,.6);line-height:1.6;margin-bottom:16px}
    .cult-meta{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
    .cult-chip{font-size:11px;font-weight:600;padding:4px 10px;border-radius:20px;background:rgba(255,255,255,.08);color:rgba(255,255,255,.7)}
    .cult-deadline{display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,.45);margin-bottom:16px}
    .btn-cult{display:block;text-align:center;padding:10px;border:1.5px solid rgba(163,230,53,.5);color:#a3e635;border-radius:25px;font-weight:700;font-size:13px;font-family:'DM Sans',sans-serif;background:transparent;cursor:pointer;transition:all .3s}
    .btn-cult:hover{background:rgba(163,230,53,.15);border-color:#a3e635}

    /* ── MODAL ── */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px)}
    .modal-overlay.open{display:flex}
    .modal-box{background:white;border-radius:24px;max-width:580px;width:100%;padding:36px;box-shadow:0 30px 80px rgba(0,0,0,.22);animation:fadeUp .3s ease both;position:relative;max-height:90vh;overflow-y:auto}
    @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
    .modal-close{position:absolute;top:16px;right:18px;background:#f1f5f9;border:none;width:32px;height:32px;border-radius:50%;font-size:16px;cursor:pointer;color:#666;display:flex;align-items:center;justify-content:center;transition:background .2s}
    .modal-close:hover{background:#e2e8f0;color:#333}
    .modal-badge-tag{display:inline-block;background:#edfaf3;color:#1f9d55;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:.3px}
    .modal-box h2{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#111;line-height:1.2}
    .modal-empresa-nm{color:#1f9d55;font-weight:700;font-size:14px;margin-bottom:3px;display:flex;align-items:center;gap:5px}
    .modal-loc{color:#888;font-size:13px;margin-bottom:0}
    .modal-info-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:0}
    .modal-chip{background:#f8fafc;border:1px solid #e2e8f0;border-radius:20px;padding:6px 14px;font-size:13px;font-weight:600;color:#444;display:inline-flex;align-items:center;gap:5px}
    /* Secciones de descripción */
    .md-seccion{margin-bottom:18px}
    .md-seccion-tit{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.8px;color:#94a3b8;margin-bottom:8px}
    .md-seccion-body{font-size:14px;color:#444;line-height:1.75}
    .md-req-item{display:flex;align-items:flex-start;gap:8px;margin-bottom:6px;font-size:14px;color:#444;line-height:1.5}
    .md-req-dot{width:6px;height:6px;border-radius:50%;background:#1f9d55;flex-shrink:0;margin-top:7px}
    .modal-btn{display:block;width:100%;padding:15px;background:linear-gradient(135deg,#1f9d55,#2ecc71);color:white;border:none;border-radius:14px;font-size:15px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;text-align:center;text-decoration:none;box-shadow:0 6px 20px rgba(31,157,85,.35);transition:transform .2s,box-shadow .2s}
    .modal-btn:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(31,157,85,.45)}

    /* ── MODAL CULTURAL ── */
    .modal-box.cult-modal{background:#16213e;color:white}
    .modal-box.cult-modal .modal-close{color:rgba(255,255,255,.5)}
    .modal-box.cult-modal .modal-close:hover{color:white}
    .modal-box.cult-modal h2{color:white}
    .modal-box.cult-modal .modal-empresa-nm{color:#a3e635}
    .modal-box.cult-modal .modal-loc{color:rgba(255,255,255,.5)}
    .modal-box.cult-modal .modal-chip{background:rgba(255,255,255,.08);color:rgba(255,255,255,.7)}
    .modal-box.cult-modal .modal-desc{color:rgba(255,255,255,.7)}
    .modal-box.cult-modal .modal-btn{background:linear-gradient(135deg,#374151,#a3e635 200%);border:1.5px solid rgba(163,230,53,.4);color:white}

    /* ── CTA FINAL ── */
    .final-cta{padding:80px 48px;background:linear-gradient(135deg,#0f172a,#1a2e1a);text-align:center;color:white}
    .final-cta h2{font-family:'Syne',sans-serif;font-size:36px;margin-bottom:12px}
    .final-cta p{color:rgba(255,255,255,.6);font-size:16px;max-width:500px;margin:0 auto 36px;line-height:1.6}
    .cta-buttons{display:flex;justify-content:center;gap:16px;flex-wrap:wrap}
    .cta-primary{background:linear-gradient(135deg,#1f9d55,#2ecc71);color:white;padding:14px 30px;border-radius:30px;text-decoration:none;font-weight:700;font-size:15px;box-shadow:0 6px 20px rgba(31,157,85,.4)}
    .cta-secondary{border:2px solid rgba(255,255,255,.3);color:white;padding:14px 30px;border-radius:30px;text-decoration:none;font-weight:600;font-size:15px;transition:all .3s}
    .cta-secondary:hover{border-color:#a3e635;color:#a3e635}

    /* ── FOOTER ── */
    footer{background:#0f172a;border-top:1px solid rgba(255,255,255,.06);color:rgba(255,255,255,.45);text-align:center;padding:24px 48px;font-size:14px}
    footer span{color:#2ecc71}

    /* ── REVEAL ── */
    .reveal{opacity:0;transform:translateY(30px);transition:opacity .6s ease,transform .6s ease}
    .reveal.visible{opacity:1;transform:translateY(0)}

    /* ── RESPONSIVE ── */
    @media(max-width:900px){
      .navbar{padding:0 24px}.nav-center,.nav-right{display:none}.hamburger{display:flex}
      .hero{padding:120px 24px 80px}.hero h1{font-size:36px}
      .tabs-nav{padding:0 16px}
      .categorias,.empleos{padding:50px 20px}.cultural{padding:50px 20px}.final-cta{padding:60px 20px}
      .empleos-grid,.cultural-grid{grid-template-columns:1fr}
      .cultural h2{font-size:28px}
    }
    @media(max-width:600px){
      .hero h1{font-size:28px}.search-bar{flex-wrap:wrap;border-radius:18px;padding:10px}
      .search-field{width:100%}.sdiv{width:100%;height:1px}.search-btn{width:100%;border-radius:12px}
      .hero-stats{gap:20px}
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar" id="navbar">
  <div class="nav-left">
    <img src="Imagenes/QuibConec.png" alt="QuibdóConecta" class="logo">
    <span class="brand">Quibdó<span>Conecta</span></span>
  </div>
  <nav class="nav-center">
    <a href="index.html">Inicio</a>
    <a href="Empleo.php" class="active">Empleos</a>
    <a href="talentos.php">Talento</a>
    <a href="empresas.php">Empresas</a>
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
  <a href="Ayuda.html">❓ Ayuda</a>
  <div class="mobile-auth">
    <a href="inicio_sesion.php" class="m-login">Iniciar sesión</a>
    <a href="registro.php" class="m-reg">Registrarse</a>
  </div>
</div>

<!-- HERO -->
<section class="hero">
  <div class="hero-content reveal">
    <h1>Empleos &amp; <span>Cultura</span><br>del Chocó</h1>
    <p class="hero-subtitle">Vacantes reales de empresas locales y oportunidades culturales de la región más biodiversa de Colombia.</p>
    <div class="search-bar">
      <div class="search-field">
        <span class="icon">💼</span>
        <input type="text" id="searchCargo" placeholder="Cargo, empresa o área…" autocomplete="off">
      </div>
      <div class="sdiv"></div>
      <div class="search-field">
        <span class="icon">📍</span>
        <input type="text" id="searchLugar" placeholder="Ciudad (ej. Quibdó)">
      </div>
      <button class="search-btn" id="searchBtn">🔍 Buscar</button>
    </div>
    <div class="hero-stats">
      <div class="stat"><div class="stat-num" id="statVacantes"><?= $totalVacantes > 0 ? '+' . $totalVacantes : '+300' ?></div><div class="stat-label">Vacantes activas</div></div>
      <div class="stat"><div class="stat-num"><?= $totalEmpresas > 0 ? '+' . $totalEmpresas : '+120' ?></div><div class="stat-label">Empresas registradas</div></div>
      <div class="stat"><div class="stat-num">18</div><div class="stat-label">Trabajos culturales</div></div>
    </div>
  </div>
</section>

<!-- TABS -->
<div class="tabs-nav">
  <button class="tab-link activo" onclick="switchTab('vacantes',this)">💼 Vacantes de empresas</button>
  <button class="tab-link" onclick="switchTab('cultural',this)">🎭 Trabajos culturales</button>
</div>

<!-- ═══════════════════════ TAB: VACANTES ═══════════════════════ -->
<div class="tab-panel activo" id="panel-vacantes">

  <!-- CATEGORÍAS -->
  <section class="categorias">
    <h2 class="reveal">Explora por categoría</h2>
    <p class="subtitulo">Filtra por área profesional</p>
    <div class="categorias-grid" id="catGrid">
      <button class="categoria-card activa" data-cat="todos"><span class="emoji">🌐</span><h4>Todos</h4><span class="count" id="cnt-todos"><?= $totalVacantes ?></span></button>
      <button class="categoria-card" data-cat="administrativo"><span class="emoji">💼</span><h4>Administrativo</h4><span class="count" id="cnt-adm">0</span></button>
      <button class="categoria-card" data-cat="tecnologia"><span class="emoji">💻</span><h4>Tecnología</h4><span class="count" id="cnt-tec">0</span></button>
      <button class="categoria-card" data-cat="arte"><span class="emoji">🎵</span><h4>Arte &amp; Música</h4><span class="count" id="cnt-art">0</span></button>
      <button class="categoria-card" data-cat="educacion"><span class="emoji">📚</span><h4>Educación</h4><span class="count" id="cnt-edu">0</span></button>
      <button class="categoria-card" data-cat="salud"><span class="emoji">🏥</span><h4>Salud</h4><span class="count" id="cnt-sal">0</span></button>
      <button class="categoria-card" data-cat="gastronomia"><span class="emoji">🍽️</span><h4>Gastronomía</h4><span class="count" id="cnt-gas">0</span></button>
      <button class="categoria-card" data-cat="tecnico"><span class="emoji">🔧</span><h4>Técnico</h4><span class="count" id="cnt-tec2">0</span></button>
      <button class="categoria-card" data-cat="transporte"><span class="emoji">🚗</span><h4>Transporte</h4><span class="count" id="cnt-tra">0</span></button>
    </div>
  </section>

  <!-- EMPLEOS -->
  <section class="empleos" id="sec-empleos">
    <div class="empleos-header">
      <h2 class="reveal">Vacantes disponibles</h2>
      <div class="filtros">
        <button class="filtro-btn activo" data-tipo="todos">Todos</button>
        <button class="filtro-btn" data-tipo="tiempo completo">Tiempo completo</button>
        <button class="filtro-btn" data-tipo="medio tiempo">Medio tiempo</button>
        <button class="filtro-btn" data-tipo="remoto">Remoto</button>
      </div>
      <span class="resultado-count" id="resCount"><?= $totalVacantes ?> vacante<?= $totalVacantes != 1 ? 's' : '' ?> encontrada<?= $totalVacantes != 1 ? 's' : '' ?></span>
    </div>

    <div class="empleos-grid" id="empleosGrid">
      <?php if (!empty($vacantesDB)): ?>
        <?php foreach ($vacantesDB as $v):
          $icono = catIcono($v['categoria'], $catIconos);
          $salario = formatSalario($v['salario_min'], $v['salario_max']);
          $empresa = htmlspecialchars($v['nombre_empresa'] ?: $v['empresa_nombre']);
          $titulo  = htmlspecialchars($v['titulo']);
          $ciudad  = htmlspecialchars($v['ciudad'] ?: 'Quibdó, Chocó');
          $desc    = htmlspecialchars($v['descripcion'] ?: 'Vacante publicada por empresa local del Chocó.');
          $cat     = strtolower(trim($v['categoria'] ?? 'administrativo'));
          $catFilter = str_replace(['á','é','í','ó','ú'],['a','e','i','o','u'], $cat);
          $catFilter = preg_replace('/[^a-z]/', '', $catFilter);
          $modalidad = strtolower($v['modalidad'] ?? 'presencial');
          $tiempo = tiempoTranscurrido($v['creado_en']);
        ?>
        <div class="empleo-card reveal"
          data-empid="<?= (int)$v['id'] ?>"
          data-tipo="<?= htmlspecialchars($v['tipo'] ?? 'tiempo completo') ?>"
          data-cat="<?= $catFilter ?>"
          data-titulo="<?= $titulo ?>"
          data-empresa="<?= $empresa ?>"
          data-lugar="<?= $ciudad ?>"
          data-salario="<?= htmlspecialchars($salario) ?>"
          data-verificada="<?= $v['verificado'] ? '1' : '0' ?>"
          data-desc="<?= $desc ?>"
          data-modalidad="<?= $modalidad ?>">
          <?php if ($v['verificado']): ?><span class="badge">✅ Verificada</span><?php endif; ?>
          <?php if (strtolower($modalidad) === 'remoto'): ?><span class="badge remoto">🌐 Remoto</span><?php endif; ?>
          <span class="empleo-icon"><?= $icono ?></span>
          <h3><?= $titulo ?></h3>
          <p class="empresa">🏢 <?= $empresa ?></p>
          <p class="ubicacion">📍 <?= $ciudad ?></p>
          <p class="empleo-salario">💰 <?= htmlspecialchars($salario) ?></p>
          <div class="empleo-tags">
            <span class="tag"><?= htmlspecialchars(ucfirst($v['tipo'] ?? 'Tiempo completo')) ?></span>
            <span class="tag"><?= htmlspecialchars(ucfirst($modalidad)) ?></span>
            <?php if ($v['categoria']): ?><span class="tag"><?= htmlspecialchars(ucfirst($v['categoria'])) ?></span><?php endif; ?>
          </div>
          <p class="empleo-fecha">Publicado <?= $tiempo ?></p>
          <button class="btn-ver">Ver detalles</button>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-bd">
          <span class="ei">💼</span>
          <p style="font-size:16px;font-weight:700;color:#555;margin-bottom:8px">Aún no hay vacantes publicadas</p>
          <p style="font-size:14px">Las empresas registradas podrán publicar vacantes desde su panel.</p>
          <a href="registro.php" style="display:inline-block;margin-top:20px;padding:12px 28px;background:linear-gradient(135deg,#1f9d55,#2ecc71);color:white;border-radius:30px;text-decoration:none;font-weight:700">✨ Registrar mi talento</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- ═══════════════════════ TAB: CULTURAL ═══════════════════════ -->
<div class="tab-panel" id="panel-cultural">
  <section class="cultural">
    <div class="cultural-header">
      <span class="cultural-badge">🌿 Identidad del Chocó</span>
      <h2>Trabajos <span>Culturales</span><br>del Chocó</h2>
      <p class="cultural-sub">Convocatorias, eventos, becas y residencias para artistas, músicos, fotógrafos y creadores del Pacífico colombiano.</p>
    </div>

    <div class="cultural-grid" id="cultGrid">

      <div class="cult-card" data-tipo="convocatoria"
        data-titulo="Músico de Chirimía"
        data-org="Ministerio de Cultura – Chocó"
        data-desc="Convocatoria para músicos tradicionales de chirimía que quieran representar al departamento del Chocó en el Festival Iberoamericano de Teatro de Bogotá. Se requiere experiencia mínima de 2 años en música tradicional del Pacífico."
        data-chips="Presencial|Quibdó|2–5 personas|Pagado"
        data-deadline="15 de agosto, 2026">
        <span class="cult-tipo convocatoria">🎺 Convocatoria</span>
        <h3>Músico de Chirimía</h3>
        <p class="cult-org">Ministerio de Cultura – Chocó</p>
        <p class="cult-desc">Representar al Chocó en el Festival Iberoamericano de Teatro de Bogotá con música tradicional del Pacífico.</p>
        <div class="cult-meta"><span class="cult-chip">Presencial</span><span class="cult-chip">Quibdó</span><span class="cult-chip">Pagado</span></div>
        <div class="cult-deadline">📅 Cierre: 15 de agosto, 2026</div>
        <button class="btn-cult">Ver convocatoria</button>
      </div>

      <div class="cult-card" data-tipo="beca"
        data-titulo="Beca Creación Literaria Afro"
        data-org="Alcaldía de Quibdó – Casa de Cultura"
        data-desc="Beca destinada a escritores y narradores afrocolombianos del Chocó para el desarrollo de obras literarias que recojan tradiciones orales, leyendas y memorias del territorio. Incluye publicación, taller y dotación económica de $3.500.000."
        data-chips="Escritura|Quibdó|Individual|$3.500.000"
        data-deadline="30 de julio, 2026">
        <span class="cult-tipo beca">✍️ Beca</span>
        <h3>Beca Creación Literaria Afro</h3>
        <p class="cult-org">Casa de Cultura – Alcaldía de Quibdó</p>
        <p class="cult-desc">Para escritores y narradores que quieran preservar las tradiciones orales afrocolombianas del Chocó.</p>
        <div class="cult-meta"><span class="cult-chip">Escritura</span><span class="cult-chip">Individual</span><span class="cult-chip">$3.500.000</span></div>
        <div class="cult-deadline">📅 Cierre: 30 de julio, 2026</div>
        <button class="btn-cult">Ver beca</button>
      </div>

      <div class="cult-card" data-tipo="evento"
        data-titulo="DJ Fiestas Patronales San Pacho"
        data-org="Comité San Francisco de Asís"
        data-desc="Se requieren DJs con repertorio de champeta, salsa choke, currulao electrónico y música regional para las Fiestas Patronales de San Pacho (Patrimonio Inmaterial de la UNESCO). 10 noches de presentación, pago por noche."
        data-chips="Por evento|Quibdó|Sep–Oct|$350.000/noche"
        data-deadline="Abierto">
        <span class="cult-tipo evento">🎧 Evento</span>
        <h3>DJ Fiestas Patronales San Pacho</h3>
        <p class="cult-org">Comité San Francisco de Asís</p>
        <p class="cult-desc">10 noches de música en las Fiestas Patronales de San Pacho — Patrimonio Inmaterial UNESCO.</p>
        <div class="cult-meta"><span class="cult-chip">Sep–Oct 2026</span><span class="cult-chip">$350.000/noche</span><span class="cult-chip">Quibdó</span></div>
        <div class="cult-deadline">📅 Postulación: Abierto</div>
        <button class="btn-cult">Ver oportunidad</button>
      </div>

      <div class="cult-card" data-tipo="residencia"
        data-titulo="Residencia Artística Baudó"
        data-org="Fundación Selva Viva"
        data-desc="Residencia de 4 semanas en el Alto Baudó para artistas visuales, fotógrafos y documentalistas que quieran explorar la biodiversidad del Chocó. Incluye alojamiento, alimentación y kit de materiales. Resultado: exposición en Quibdó."
        data-chips="4 semanas|Alto Baudó|5 cupos|Todo pago"
        data-deadline="1 de septiembre, 2026">
        <span class="cult-tipo residencia">🎨 Residencia</span>
        <h3>Residencia Artística Baudó</h3>
        <p class="cult-org">Fundación Selva Viva</p>
        <p class="cult-desc">4 semanas en el Alto Baudó para artistas visuales y fotógrafos explorando la biodiversidad chocoana.</p>
        <div class="cult-meta"><span class="cult-chip">4 semanas</span><span class="cult-chip">5 cupos</span><span class="cult-chip">Todo pago</span></div>
        <div class="cult-deadline">📅 Cierre: 1 de septiembre, 2026</div>
        <button class="btn-cult">Ver residencia</button>
      </div>

      <div class="cult-card" data-tipo="convocatoria"
        data-titulo="Fotógrafo Documental"
        data-org="Periódico El Chocoano"
        data-desc="El Chocoano busca fotógrafo documental freelance para coberturas de eventos culturales, sociales y comunitarios del departamento. Se requiere cámara propia y portafolio. Pago por entrega."
        data-chips="Freelance|Todo el Chocó|Portafolio|Pagado"
        data-deadline="Abierto permanente">
        <span class="cult-tipo convocatoria">📷 Convocatoria</span>
        <h3>Fotógrafo Documental</h3>
        <p class="cult-org">Periódico El Chocoano</p>
        <p class="cult-desc">Cobertura de eventos culturales, comunitarios y sociales en todo el departamento del Chocó.</p>
        <div class="cult-meta"><span class="cult-chip">Freelance</span><span class="cult-chip">Todo el Chocó</span><span class="cult-chip">Portafolio</span></div>
        <div class="cult-deadline">📅 Abierto permanente</div>
        <button class="btn-cult">Ver convocatoria</button>
      </div>

      <div class="cult-card" data-tipo="evento"
        data-titulo="Instructor de Danza Afro"
        data-org="SENA Regional Chocó"
        data-desc="El SENA Regional Chocó busca instructor de danzas tradicionales afrocolombianas (currulao, abozao, jota chocoana) para dictar talleres formativos a jóvenes de 14 a 25 años. Contrato de 6 meses. Requisito: experiencia y aval de comunidad."
        data-chips="6 meses|Quibdó|Contrato|Salario mínimo+"
        data-deadline="20 de julio, 2026">
        <span class="cult-tipo evento">💃 Evento</span>
        <h3>Instructor de Danza Afro</h3>
        <p class="cult-org">SENA Regional Chocó</p>
        <p class="cult-desc">Talleres de danza tradicional afrocolombiana para jóvenes chocoanos. Contrato 6 meses.</p>
        <div class="cult-meta"><span class="cult-chip">6 meses</span><span class="cult-chip">Quibdó</span><span class="cult-chip">Salario mínimo+</span></div>
        <div class="cult-deadline">📅 Cierre: 20 de julio, 2026</div>
        <button class="btn-cult">Ver oportunidad</button>
      </div>

      <div class="cult-card" data-tipo="beca"
        data-titulo="Beca Músicos Jóvenes Chocó"
        data-org="Gobernación del Chocó – Secretaría de Cultura"
        data-desc="Programa de becas para músicos jóvenes de 15 a 28 años, enfocado en géneros tradicionales del Pacífico. Incluye instrumento, clases durante 1 año y participación en el Festival de Músicas del Pacífico Petronio Álvarez."
        data-chips="1 año|15–28 años|Instrumento incluido|Petronio"
        data-deadline="10 de agosto, 2026">
        <span class="cult-tipo beca">🎵 Beca</span>
        <h3>Beca Músicos Jóvenes Chocó</h3>
        <p class="cult-org">Gobernación del Chocó – Secretaría de Cultura</p>
        <p class="cult-desc">Instrumento + clases por 1 año + participación en Petronio Álvarez para músicos de 15 a 28 años.</p>
        <div class="cult-meta"><span class="cult-chip">15–28 años</span><span class="cult-chip">Instrumento</span><span class="cult-chip">Petronio 2026</span></div>
        <div class="cult-deadline">📅 Cierre: 10 de agosto, 2026</div>
        <button class="btn-cult">Ver beca</button>
      </div>

      <div class="cult-card" data-tipo="convocatoria"
        data-titulo="Actor / Actriz Teatro Comunitario"
        data-org="Teatro Experimental del Chocó"
        data-desc="Compañía de teatro comunitario busca actores y actrices sin experiencia formal para nuevo montaje que aborda la historia de las comunidades del río Atrato. Ensayos 3 veces por semana. Presentaciones en Quibdó y Bojayá."
        data-chips="Sin exp. requerida|Quibdó + Bojayá|Ensayos x3/sem|Presentaciones"
        data-deadline="Abierto">
        <span class="cult-tipo convocatoria">🎭 Convocatoria</span>
        <h3>Actor / Actriz Teatro Comunitario</h3>
        <p class="cult-org">Teatro Experimental del Chocó</p>
        <p class="cult-desc">Montaje sobre las comunidades del río Atrato. Sin experiencia requerida. Presentaciones en Quibdó y Bojayá.</p>
        <div class="cult-meta"><span class="cult-chip">Sin exp. req.</span><span class="cult-chip">Quibdó + Bojayá</span></div>
        <div class="cult-deadline">📅 Abierto</div>
        <button class="btn-cult">Ver convocatoria</button>
      </div>

    </div>
  </section>
</div>

<!-- CTA FINAL -->
<section class="final-cta">
  <h2>¿Buscas talento o empleo?</h2>
  <p>Conecta con profesionales del Chocó o encuentra tu próxima oportunidad laboral.</p>
  <div class="cta-buttons">
    <a href="talentos.php" class="cta-primary">🌟 Explorar talentos</a>
    <a href="registro.php" class="cta-secondary">✨ Registrar mi talento</a>
  </div>
</section>

<!-- MODAL VACANTE -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box" id="modalBox">
    <button class="modal-close" id="modalClose">✕</button>

    <!-- CABECERA -->
    <div id="modalCabecera" style="display:flex;align-items:center;gap:16px;margin-bottom:18px">
      <div id="modalIconWrap" style="width:56px;height:56px;border-radius:14px;background:#edfaf3;display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0">💼</div>
      <div style="flex:1;min-width:0">
        <span class="modal-badge-tag" id="modalTipo">Tiempo completo</span>
        <h2 id="modalTitulo" style="margin-top:4px">Cargo</h2>
      </div>
    </div>

    <!-- EMPRESA + LUGAR -->
    <p class="modal-empresa-nm" id="modalEmpresa">Empresa</p>
    <p class="modal-loc" id="modalLoc">📍 Ubicación</p>

    <!-- BADGES VERIFICADA -->
    <div id="modalBadgesRow" style="display:flex;flex-wrap:wrap;gap:6px;margin:10px 0 14px"></div>

    <!-- CHIPS INFO -->
    <div class="modal-info-row" id="modalInfoRow">
      <span class="modal-chip" id="modalSalario">💰 A convenir</span>
      <span class="modal-chip" id="modalModalidad">Presencial</span>
    </div>

    <!-- SEPARADOR -->
    <div style="height:1px;background:#f1f5f9;margin:16px 0"></div>

    <!-- DESCRIPCIÓN ESTRUCTURADA -->
    <div id="modalDescBloque"></div>

    <!-- BTN -->
    <div id="modalBtnWrap" style="margin-top:8px">
      <?php if ($usuarioEsCandidato): ?>
        <!-- Candidato logueado: puede solicitar directo -->
        <button class="modal-btn" id="modalBtnSolicitar" onclick="solicitarDesdeModal()">
          🚀 Solicitar esta vacante
        </button>
      <?php elseif ($usuarioLogueado): ?>
        <!-- Logueado pero no candidato -->
        <a href="Empleo.php" class="modal-btn" style="text-align:center">💼 Ver todas las vacantes</a>
      <?php else: ?>
        <!-- No logueado -->
        <a href="registro.php" class="modal-btn" id="modalBtn">🚀 Postularme ahora</a>
        <p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:8px">
          ¿Ya tienes cuenta? <a href="inicio_sesion.php" style="color:#1f9d55;font-weight:700">Inicia sesión →</a>
        </p>
      <?php endif; ?>
    </div>
    <!-- Mensaje de respuesta al solicitar -->
    <div id="modalSolMsg" style="display:none;margin-top:10px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;text-align:center"></div>
    <!-- Área mensaje opcional (candidato logueado) -->
    <?php if ($usuarioEsCandidato): ?>
    <div id="modalMensajeWrap" style="margin-top:12px;display:none">
      <textarea id="modalMensajeTxt" rows="3" placeholder="Mensaje opcional para la empresa…"
        style="width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13px;font-family:'DM Sans',sans-serif;resize:vertical;box-sizing:border-box;color:#333"></textarea>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <p>© 2026 <span>QuibdóConecta</span> — Plataforma de empleos y cultura del Chocó.</p>
</footer>

<script>
// NAVBAR
window.addEventListener('scroll', () => document.getElementById('navbar').classList.toggle('abajo', window.scrollY > 50));
const ham = document.getElementById('hamburger'), mob = document.getElementById('mobileMenu');
ham.addEventListener('click', () => { ham.classList.toggle('open'); mob.classList.toggle('open'); });
document.addEventListener('click', e => { if (!ham.contains(e.target) && !mob.contains(e.target)) { ham.classList.remove('open'); mob.classList.remove('open'); } });

// TABS
function switchTab(tab, btn) {
  document.querySelectorAll('.tab-link').forEach(b => b.classList.remove('activo'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('activo'));
  btn.classList.add('activo');
  document.getElementById('panel-' + tab).classList.add('activo');
}

// CATEGORÍAS
const allCards = Array.from(document.querySelectorAll('.empleo-card'));
const catMap = { 'administrativo':'cnt-adm','tecnologia':'cnt-tec','arte':'cnt-art','educacion':'cnt-edu','salud':'cnt-sal','gastronomia':'cnt-gas','tecnico':'cnt-tec2','transporte':'cnt-tra' };
Object.entries(catMap).forEach(([cat, id]) => {
  const el = document.getElementById(id);
  if (el) el.textContent = allCards.filter(c => c.dataset.cat === cat).length;
});

let catActiva = 'todos', filtroActivo = 'todos', textoBusqueda = '';

function aplicarFiltros() {
  let visible = 0;
  allCards.forEach(c => {
    const matchCat  = catActiva === 'todos' || c.dataset.cat === catActiva;
    const matchTipo = filtroActivo === 'todos' || (c.dataset.tipo || '').toLowerCase().includes(filtroActivo);
    const txt = ((c.dataset.titulo||'') + ' ' + (c.dataset.empresa||'') + ' ' + (c.dataset.lugar||'')).toLowerCase();
    const matchTxt  = !textoBusqueda || txt.includes(textoBusqueda);
    const show = matchCat && matchTipo && matchTxt;
    c.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('resCount').textContent = visible + ' vacante' + (visible !== 1 ? 's' : '') + ' encontrada' + (visible !== 1 ? 's' : '');
  let nr = document.getElementById('noResults');
  if (!nr) {
    nr = document.createElement('div'); nr.id = 'noResults'; nr.className = 'no-results';
    nr.innerHTML = '<span class="nr-icon">🔍</span><p>No encontramos vacantes con esos criterios.</p>';
    document.getElementById('empleosGrid').appendChild(nr);
  }
  nr.style.display = visible === 0 ? '' : 'none';
}

document.querySelectorAll('.categoria-card').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.categoria-card').forEach(b => b.classList.remove('activa'));
    btn.classList.add('activa'); catActiva = btn.dataset.cat;
    aplicarFiltros();
    document.getElementById('sec-empleos').scrollIntoView({ behavior: 'smooth' });
  });
});

document.querySelectorAll('.filtro-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo'); filtroActivo = btn.dataset.tipo || 'todos';
    aplicarFiltros();
  });
});

function buscar() {
  textoBusqueda = (document.getElementById('searchCargo').value.trim() + ' ' + document.getElementById('searchLugar').value.trim()).trim().toLowerCase();
  aplicarFiltros();
  document.querySelector('.tab-link').click();
  document.getElementById('sec-empleos').scrollIntoView({ behavior: 'smooth' });
}
document.getElementById('searchBtn').addEventListener('click', buscar);
['searchCargo','searchLugar'].forEach(id => document.getElementById(id).addEventListener('keydown', e => { if (e.key === 'Enter') buscar(); }));

// URL params
(function() {
  const p = new URLSearchParams(window.location.search);
  if (p.get('cargo')) document.getElementById('searchCargo').value = p.get('cargo');
  if (p.get('lugar')) document.getElementById('searchLugar').value = p.get('lugar');
  if (p.get('cargo') || p.get('lugar')) { textoBusqueda = ((p.get('cargo')||'')+' '+(p.get('lugar')||'')).trim().toLowerCase(); aplicarFiltros(); }
})();

// MODAL VACANTE
const overlay = document.getElementById('modalOverlay');
document.getElementById('modalClose').addEventListener('click', () => overlay.classList.remove('open'));
overlay.addEventListener('click', e => { if (e.target === overlay) overlay.classList.remove('open'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') overlay.classList.remove('open'); });


function abrirModal(card) {
  const cat = (card.dataset.cat || '').toLowerCase();
  const iconosMap2 = { 'administrativo':'💼','tecnologia':'💻','tecnología':'💻','arte':'🎵','educacion':'📚','educación':'📚','salud':'🏥','gastronomia':'🍽️','gastronomía':'🍽️','tecnico':'🔧','técnico':'🔧','transporte':'🚗','comercio':'🛍️','construccion':'🏗️','finanzas':'📊','agro':'🌿','servicios':'⚙️' };
  const icono = iconosMap2[cat] || '💼';

  // Icono
  document.getElementById('modalIconWrap').textContent = icono;

  // Tipo badge
  document.getElementById('modalTipo').textContent = ucfirst(card.dataset.tipo || 'Vacante');

  // Titulo
  document.getElementById('modalTitulo').textContent = card.dataset.titulo || '';

  // Empresa y lugar
  document.getElementById('modalEmpresa').innerHTML = '🏢 ' + (card.dataset.empresa || '');
  document.getElementById('modalLoc').textContent = '📍 ' + (card.dataset.lugar || '');

  // Chips info
  const salario = card.dataset.salario || 'A convenir';
  const modalidad = ucfirst(card.dataset.modalidad || 'Presencial');
  document.getElementById('modalSalario').innerHTML = '💰 ' + salario;
  document.getElementById('modalModalidad').innerHTML = '📋 ' + modalidad;
  document.getElementById('modalInfoRow').style.display = 'flex';

  // Badge verificada
  const row = document.getElementById('modalBadgesRow');
  row.innerHTML = '';
  if (card.dataset.verificada === '1') {
    row.innerHTML = `<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:#e8f5e9;color:#1c5c32;border:1px solid #a5d6a7">✅ Empresa Verificada</span>`;
  }

  // Parsear descripción en bloques
  const rawDesc = card.dataset.desc || '';
  const bloque = document.getElementById('modalDescBloque');
  bloque.innerHTML = '';

  // Separar por los marcadores que metemos en el INSERT
  const partes = rawDesc.split(/\n\n/);
  let descripcion = '', requisitos = '', salarioTexto = '', tipoContrato = '';

  partes.forEach(p => {
    const t = p.trim();
    if (t.startsWith('Requisitos:')) {
      requisitos = t.replace('Requisitos:', '').trim();
    } else if (t.startsWith('Salario:')) {
      salarioTexto = t.replace('Salario:', '').trim();
      // también puede tener Modalidad de contrato en misma línea
      const lines = salarioTexto.split('\n');
      salarioTexto = lines[0].trim();
      if (lines[1] && lines[1].startsWith('Modalidad de contrato:')) {
        tipoContrato = lines[1].replace('Modalidad de contrato:', '').trim();
      }
    } else if (t) {
      descripcion = t;
    }
  });

  // Bloque descripción
  if (descripcion) {
    const d = document.createElement('div');
    d.className = 'md-seccion';
    d.innerHTML = `<div class="md-seccion-tit">📄 Descripción del cargo</div><div class="md-seccion-body">${descripcion.replace(/\n/g,'<br>')}</div>`;
    bloque.appendChild(d);
  }

  // Bloque requisitos — convertir en lista si tiene · o saltos
  if (requisitos) {
    const d = document.createElement('div');
    d.className = 'md-seccion';
    let reqHtml = '';
    const items = requisitos.split(/·|\n/).map(s => s.trim()).filter(Boolean);
    if (items.length > 1) {
      reqHtml = items.map(i => `<div class="md-req-item"><div class="md-req-dot"></div><span>${i}</span></div>`).join('');
    } else {
      reqHtml = `<div class="md-seccion-body">${requisitos.replace(/\n/g,'<br>')}</div>`;
    }
    d.innerHTML = `<div class="md-seccion-tit">✅ Requisitos y cómo postularse</div>${reqHtml}`;
    bloque.appendChild(d);
  }

  // Chips adicionales si vienen de descripcion
  if (salarioTexto || tipoContrato) {
    const chipRow = document.createElement('div');
    chipRow.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px';
    if (salarioTexto) chipRow.innerHTML += `<span class="modal-chip">💰 ${salarioTexto}</span>`;
    if (tipoContrato) chipRow.innerHTML += `<span class="modal-chip">📋 ${tipoContrato}</span>`;
    // Solo mostrar si difieren de lo que ya mostramos arriba
    if (salarioTexto !== salario || tipoContrato) {
      const d = document.createElement('div');
      d.className = 'md-seccion';
      d.innerHTML = `<div class="md-seccion-tit">💼 Condiciones</div>`;
      d.appendChild(chipRow);
      bloque.appendChild(d);
    }
  }

  // Si no había nada estructurado, mostrar el texto tal cual mejorado
  if (!descripcion && !requisitos) {
    const d = document.createElement('div');
    d.className = 'md-seccion';
    d.innerHTML = `<div class="md-seccion-body">${rawDesc.replace(/\n/g,'<br>') || 'Vacante publicada por empresa local del Chocó.'}</div>`;
    bloque.appendChild(d);
  }

  // Guardar empleo id actual para solicitud
  window._modalEmpId = parseInt(card.dataset.empid || '0');

  // Reset estado solicitud
  const solMsg = document.getElementById('modalSolMsg');
  if (solMsg) { solMsg.style.display = 'none'; solMsg.textContent = ''; }
  const btnSol = document.getElementById('modalBtnSolicitar');
  if (btnSol) { btnSol.disabled = false; btnSol.textContent = '🚀 Solicitar esta vacante'; }
  const mensajeWrap = document.getElementById('modalMensajeWrap');
  if (mensajeWrap) mensajeWrap.style.display = 'none';
  const mensajeTxt = document.getElementById('modalMensajeTxt');
  if (mensajeTxt) mensajeTxt.value = '';

  document.getElementById('modalBox').className = 'modal-box';
  overlay.classList.add('open');
}

function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }

// ── SOLICITAR VACANTE DESDE MODAL ─────────────────────────────
<?php if ($usuarioEsCandidato): ?>
async function solicitarDesdeModal() {
  const empId = window._modalEmpId || 0;
  if (!empId) return;
  const btn = document.getElementById('modalBtnSolicitar');
  const msg = document.getElementById('modalSolMsg');
  const mensajeWrap = document.getElementById('modalMensajeWrap');

  // Primera pulsación: mostrar área de mensaje
  if (mensajeWrap && mensajeWrap.style.display === 'none') {
    mensajeWrap.style.display = 'block';
    btn.textContent = '✅ Confirmar y enviar solicitud';
    btn.style.background = 'linear-gradient(135deg,#1648e8,#2563eb)';
    return;
  }

  const mensaje = document.getElementById('modalMensajeTxt')?.value.trim() || '';
  btn.disabled = true;
  btn.textContent = 'Enviando…';
  msg.style.display = 'none';

  try {
    const fd = new FormData();
    fd.append('_action', 'solicitar_vacante_pub');
    fd.append('empleo_id', empId);
    fd.append('mensaje', mensaje);
    const r = await fetch('Empleo.php', { method: 'POST', body: fd });
    const j = await r.json();
    msg.style.display = 'block';
    if (j.ok) {
      msg.textContent = j.msg || '✅ ¡Solicitud enviada!';
      msg.style.cssText = 'display:block;margin-top:10px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;text-align:center;background:#edfaf3;color:#1f9d55;border:1px solid #a3f0ba';
      btn.textContent = '✅ Solicitud enviada';
      if (mensajeWrap) mensajeWrap.style.display = 'none';
    } else if (j.ya_aplicado) {
      msg.textContent = '✅ Ya aplicaste a esta vacante anteriormente.';
      msg.style.cssText = 'display:block;margin-top:10px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;text-align:center;background:#fef9c3;color:#854d0e;border:1px solid #fde68a';
      btn.textContent = '✅ Ya aplicaste';
    } else {
      msg.textContent = j.msg || '❌ Error al enviar.';
      msg.style.cssText = 'display:block;margin-top:10px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;text-align:center;background:#fef2f2;color:#dc2626;border:1px solid #fecaca';
      btn.disabled = false;
      btn.textContent = '🚀 Solicitar esta vacante';
    }
  } catch (e) {
    msg.textContent = '❌ Error de conexión. Intenta de nuevo.';
    msg.style.cssText = 'display:block;margin-top:10px;padding:10px 14px;border-radius:10px;font-size:13px;font-weight:600;text-align:center;background:#fef2f2;color:#dc2626;border:1px solid #fecaca';
    btn.disabled = false;
    btn.textContent = '🚀 Solicitar esta vacante';
  }
}
<?php else: ?>
function solicitarDesdeModal() { window.location.href = 'registro.php'; }
<?php endif; ?>

document.querySelectorAll('.btn-ver').forEach(btn => btn.addEventListener('click', () => abrirModal(btn.closest('.empleo-card'))));

// MODAL CULTURAL
document.querySelectorAll('.btn-cult').forEach(btn => {
  btn.addEventListener('click', () => {
    const card = btn.closest('.cult-card');
    document.getElementById('modalIcon').textContent = '🎭';
    document.getElementById('modalTipo').textContent = (card.dataset.tipo || 'Cultural').charAt(0).toUpperCase() + (card.dataset.tipo || 'cultural').slice(1);
    document.getElementById('modalTitulo').textContent = card.dataset.titulo || '';
    document.getElementById('modalEmpresa').textContent = '🌿 ' + (card.dataset.org || '');
    document.getElementById('modalLoc').textContent = '📅 Cierre: ' + (card.dataset.deadline || 'Ver convocatoria');
    document.getElementById('modalSalario').textContent = '';
    document.getElementById('modalModalidad').textContent = '';
    document.getElementById('modalDesc').textContent = card.dataset.desc || '';
    const row = document.getElementById('modalBadgesRow');
    row.innerHTML = '';
    if (card.dataset.chips) {
      card.dataset.chips.split('|').forEach(chip => {
        row.innerHTML += `<span style="display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;background:rgba(163,230,53,.15);color:#a3e635">${chip}</span>`;
      });
    }
    document.getElementById('modalBox').className = 'modal-box cult-modal';
    overlay.classList.add('open');
  });
});

// SCROLL REVEAL
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('visible'); }
    else if (e.target.getBoundingClientRect().top < 0) { e.target.classList.remove('visible'); }
  });
}, { threshold: 0.1 });
document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
</script>
<!-- Widget de sesión activa — QuibdóConecta -->
<script src="js/sesion_widget.js"></script>
</body>
</html>