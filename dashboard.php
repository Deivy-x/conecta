<?php

session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once __DIR__ . '/Php/db.php';

if (!isset($_SESSION['usuario_id'])) {
  header('Location: inicio_sesion.php');
  exit;
}
$db = getDB();
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
if (!$usuario) {
  session_destroy();
  header('Location: inicio_sesion.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['_action'] ?? '';

  if ($action === 'editar_perfil') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $profesion = trim($_POST['profesion'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    if (!$nombre) {
      echo json_encode(['ok' => false, 'msg' => 'Nombre obligatorio.']);
      exit;
    }
    $db->prepare("UPDATE usuarios SET nombre=?,apellido=?,telefono=?,ciudad=? WHERE id=?")
      ->execute([$nombre, $apellido, $telefono, $ciudad, $usuario['id']]);
    $_SESSION['usuario_nombre'] = $nombre;

    // Siempre actualizar talento_perfil para no perder datos existentes del usuario.
    // Solo sobreescribir campos profesionales si el frontend indicó que estaban visibles (flag _edita_pro=1).
    $editaPro = ($_POST['_edita_pro'] ?? '0') === '1';
    $tpChk = $db->prepare("SELECT id, profesion, bio, skills FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
    $tpChk->execute([$usuario['id']]);
    $tpRow = $tpChk->fetch();
    if ($tpRow) {
      $nuevaProfesion = $editaPro ? $profesion : $tpRow['profesion'];
      $nuevaBio = $editaPro ? $bio : $tpRow['bio'];
      $nuevaSkills = $editaPro ? $skills : $tpRow['skills'];
      $db->prepare("UPDATE talento_perfil SET profesion=?, bio=?, skills=? WHERE id=?")
        ->execute([$nuevaProfesion, $nuevaBio, $nuevaSkills, $tpRow['id']]);
      $profesion = $nuevaProfesion;
    } else {
      // Solo crear registro si al menos hay un campo con contenido
      if ($profesion !== '' || $bio !== '' || $skills !== '') {
        $db->prepare("INSERT INTO talento_perfil (usuario_id, profesion, bio, skills, visible, visible_admin) VALUES (?,?,?,?,0,1)")
          ->execute([$usuario['id'], $profesion, $bio, $skills]);
      }
    }
    echo json_encode(['ok' => true, 'nombre' => $nombre, 'apellido' => $apellido, 'ciudad' => $ciudad, 'profesion' => $profesion]);
    exit;
  }

  if ($action === 'toggle_vis') {
    $visible = ($_POST['visible'] ?? '0') === '1' ? 1 : 0;
    // Verificar que el plan sea verde_selva o superior
    if (file_exists(__DIR__ . '/Php/planes_helper.php'))
      require_once __DIR__ . '/Php/planes_helper.php';
    $planActualTv = 'semilla';
    if (function_exists('getDatosPlan')) {
      $dpTv = getDatosPlan($db, $usuario['id']);
      $planActualTv = $dpTv['plan'] ?? 'semilla';
    }
    $prioridadTv = PLAN_PRIORIDAD[$planActualTv] ?? 0;
    if ($prioridadTv < 2) {
      echo json_encode(['ok' => false, 'msg' => 'Necesitas el plan Verde Selva o superior para activar la visibilidad.']);
      exit;
    }
    $tpChkV = $db->prepare("SELECT id FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
    $tpChkV->execute([$usuario['id']]);
    $tpRowV = $tpChkV->fetch();
    if ($tpRowV) {
      $db->prepare("UPDATE talento_perfil SET visible=? WHERE id=?")->execute([$visible, $tpRowV['id']]);
    } else {
      $db->prepare("INSERT INTO talento_perfil (usuario_id, visible, visible_admin) VALUES (?,?,1)")->execute([$usuario['id'], $visible]);
    }
    echo json_encode(['ok' => true, 'visible' => $visible]);
    exit;
  }

  if ($action === 'subir_foto') {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== 0) {
      echo json_encode(['ok' => false, 'msg' => 'No se recibió imagen.']);
      exit;
    }
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
      echo json_encode(['ok' => false, 'msg' => 'Solo JPG, PNG o WEBP.']);
      exit;
    }
    if ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
      echo json_encode(['ok' => false, 'msg' => 'Máximo 2 MB.']);
      exit;
    }
    require_once __DIR__ . '/Php/cloudinary_upload.php';
    $result = cloudinary_upload($_FILES['foto']['tmp_name'], 'quibdoconecta/fotos');
    if (!$result['ok']) {
      echo json_encode(['ok' => false, 'msg' => $result['msg']]);
      exit;
    }
    $url = $result['url'];
    $db->prepare("UPDATE usuarios SET foto=? WHERE id=?")->execute([$url, $usuario['id']]);
    echo json_encode(['ok' => true, 'foto' => $url]);
    exit;
  }

  if ($action === 'eliminar_foto') {
    $db->prepare("UPDATE usuarios SET foto='' WHERE id=?")->execute([$usuario['id']]);
    echo json_encode(['ok' => true]);
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
    $url = $result['url'];
    $db->prepare("UPDATE usuarios SET banner=? WHERE id=?")->execute([$url, $usuario['id']]);
    echo json_encode(['ok' => true, 'banner' => $url]);
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

  if ($action === 'eliminar_cuenta') {
    $confirmar = trim($_POST['confirmar'] ?? '');
    if ($confirmar !== $usuario['correo']) {
      echo json_encode(['ok' => false, 'msg' => 'El correo no coincide. Escríbelo exactamente.']);
      exit;
    }
    try {

      foreach (['perfiles_empresa', 'talento_galeria', 'talento_educacion', 'talento_certificaciones', 'talento_experiencia', 'perfil_vistas', 'sesiones', 'negocios_locales'] as $tabla) {
        try {
          $db->prepare("DELETE FROM $tabla WHERE usuario_id=?")->execute([$usuario['id']]);
        } catch (Exception $e) {
        }
      }

      if (!empty($usuario['foto']) && !str_starts_with($usuario['foto'], 'http')) {
        @unlink(__DIR__ . '/uploads/fotos/' . $usuario['foto']);
      }

      $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$usuario['id']]);

      $_SESSION = [];
      session_destroy();
      echo json_encode(['ok' => true, 'msg' => 'Cuenta eliminada correctamente.']);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => 'Error al eliminar la cuenta: ' . $e->getMessage()]);
    }
    exit;
  }

  if ($action === 'subir_evidencia') {

    if (file_exists(__DIR__ . '/Php/planes_helper.php'))
      require_once __DIR__ . '/Php/planes_helper.php';
    if (file_exists(__DIR__ . '/Php/badges_helper.php'))
      require_once __DIR__ . '/Php/badges_helper.php';
    $badgesU = function_exists('getBadgesUsuario') ? getBadgesUsuario($db, $usuario['id']) : [];

    $tienePortafolio = function_exists('tieneBeneficio')
      ? tieneBeneficio($db, $usuario['id'], 'portafolio')
      : (function_exists('tieneBadge') && tieneBadge($badgesU, 'Selva Verde'));
    $tieneSelvaVerde = $tienePortafolio;

    try {
      $db->exec("CREATE TABLE IF NOT EXISTS talento_galeria (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                tipo ENUM('foto','video') NOT NULL DEFAULT 'foto',
                archivo VARCHAR(255) NOT NULL DEFAULT '',
                url_video VARCHAR(500) DEFAULT NULL,
                titulo VARCHAR(150) DEFAULT NULL,
                descripcion TEXT DEFAULT NULL,
                orden TINYINT NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_usuario (usuario_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
    }

    $contStmt = $db->prepare("SELECT COUNT(*) FROM talento_galeria WHERE usuario_id=? AND activo=1");
    $contStmt->execute([$usuario['id']]);
    $totalActual = (int) $contStmt->fetchColumn();

    $limite = $tieneSelvaVerde ? PHP_INT_MAX : 15;
    if ($totalActual >= $limite) {
      echo json_encode([
        'ok' => false,
        'msg' => $tieneSelvaVerde
          ? 'Error interno de límite.'
          : 'Alcanzaste el límite de 15 archivos. Activa el plan Selva Verde 🌿 para galería ilimitada.'
      ]);
      exit;
    }

    $tipoMedia = $_POST['tipo_media'] ?? 'foto';
    $titulo = trim($_POST['titulo'] ?? '');
    $desc = trim($_POST['descripcion'] ?? '');

    if ($tipoMedia === 'video_url') {
      $urlVideo = trim($_POST['url_video'] ?? '');
      if (!$urlVideo) {
        echo json_encode(['ok' => false, 'msg' => 'URL de video requerida']);
        exit;
      }
      $db->prepare("INSERT INTO talento_galeria (usuario_id,tipo,archivo,url_video,titulo,descripcion,orden)
                VALUES (?,?,?,?,?,?,?)")
        ->execute([$usuario['id'], 'video', '', $urlVideo, $titulo, $desc, $totalActual]);
      echo json_encode(['ok' => true, 'tipo' => 'video_url', 'url' => $urlVideo, 'titulo' => $titulo]);
      exit;
    }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== 0) {
      echo json_encode(['ok' => false, 'msg' => 'No se recibió archivo.']);
      exit;
    }
    $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
    $fotosExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $videosExt = ['mp4', 'mov', 'webm', 'avi'];
    $esFoto = in_array($ext, $fotosExt);
    $esVideo = in_array($ext, $videosExt);
    if (!$esFoto && !$esVideo) {
      echo json_encode(['ok' => false, 'msg' => 'Formato no permitido. Usa JPG, PNG, MP4 o MOV.']);
      exit;
    }
    $maxSize = $esVideo ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
    if ($_FILES['archivo']['size'] > $maxSize) {
      echo json_encode(['ok' => false, 'msg' => $esVideo ? 'Máximo 50 MB para videos.' : 'Máximo 5 MB para fotos.']);
      exit;
    }
    $dir = __DIR__ . '/uploads/galeria/';
    if (!is_dir($dir))
      mkdir($dir, 0755, true);
    $fn = 'g' . $usuario['id'] . '_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $dir . $fn)) {
      echo json_encode(['ok' => false, 'msg' => 'Error al guardar archivo.']);
      exit;
    }
    $tipoGuardar = $esFoto ? 'foto' : 'video';
    $db->prepare("INSERT INTO talento_galeria (usuario_id,tipo,archivo,titulo,descripcion,orden)
            VALUES (?,?,?,?,?,?)")
      ->execute([$usuario['id'], $tipoGuardar, $fn, $titulo, $desc, $totalActual]);
    $nuevaUrl = 'uploads/galeria/' . $fn;
    echo json_encode(['ok' => true, 'tipo' => $tipoGuardar, 'url' => $nuevaUrl, 'titulo' => $titulo, 'id' => $db->lastInsertId()]);
    exit;
  }

  if ($action === 'eliminar_evidencia') {
    $gid = (int) ($_POST['galeria_id'] ?? 0);
    if (!$gid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    $chk = $db->prepare("SELECT archivo FROM talento_galeria WHERE id=? AND usuario_id=?");
    $chk->execute([$gid, $usuario['id']]);
    $row = $chk->fetch();
    if (!$row) {
      echo json_encode(['ok' => false, 'msg' => 'No encontrado']);
      exit;
    }
    if ($row['archivo']) {
      $path = __DIR__ . '/uploads/galeria/' . $row['archivo'];
      if (file_exists($path))
        @unlink($path);
    }
    $db->prepare("DELETE FROM talento_galeria WHERE id=? AND usuario_id=?")->execute([$gid, $usuario['id']]);
    echo json_encode(['ok' => true]);
    exit;
  }

  if ($action === 'registrar_vista') {
    $uid = (int) ($_POST['usuario_id'] ?? 0);
    if ($uid && $uid !== $usuario['id']) {
      try {
        $db->exec("CREATE TABLE IF NOT EXISTS perfil_vistas (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    visitante_id INT DEFAULT NULL,
                    ip VARCHAR(64) DEFAULT NULL,
                    seccion VARCHAR(32) DEFAULT 'talentos',
                    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_usuario_id (usuario_id),
                    INDEX idx_creado_en (creado_en)
                )");
        $seccion = htmlspecialchars($_POST['seccion'] ?? 'talentos');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $ck = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=? AND ip=? AND creado_en >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $ck->execute([$uid, $ip]);
        if ((int) $ck->fetchColumn() === 0) {
          $ins = $db->prepare("INSERT INTO perfil_vistas (usuario_id, visitante_id, ip, seccion) VALUES (?,?,?,?)");
          $ins->execute([$uid, $usuario['id'], $ip, $seccion]);
        }
        echo json_encode(['ok' => true]);
        exit;
      } catch (Exception $e) {
        echo json_encode(['ok' => false]);
        exit;
      }
    }
    echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
    exit;
  }

  if ($action === 'guardar_educacion') {
    try {
      $db->exec("CREATE TABLE IF NOT EXISTS talento_educacion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        institucion VARCHAR(200) NOT NULL DEFAULT '',
        titulo VARCHAR(200) NOT NULL DEFAULT '',
        fecha_inicio VARCHAR(20) DEFAULT '',
        fecha_fin VARCHAR(20) DEFAULT '',
        logo_url TEXT DEFAULT NULL,
        orden TINYINT DEFAULT 0,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_u (usuario_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $items = json_decode($_POST['items'] ?? '[]', true);
      if (!is_array($items)) {
        echo json_encode(['ok' => false, 'msg' => 'Datos invalidos']);
        exit;
      }
      $db->prepare("DELETE FROM talento_educacion WHERE usuario_id=?")->execute([$usuario['id']]);
      $stmt = $db->prepare("INSERT INTO talento_educacion (usuario_id,institucion,titulo,fecha_inicio,fecha_fin,logo_url,orden) VALUES (?,?,?,?,?,?,?)");
      foreach (array_values($items) as $i => $e) {
        $stmt->execute([$usuario['id'], substr(trim($e['inst'] ?? ''), 0, 200), substr(trim($e['titulo'] ?? ''), 0, 200), substr(trim($e['inicio'] ?? ''), 0, 20), substr(trim($e['fin'] ?? ''), 0, 20), $e['logo'] ?? null, $i]);
      }
      echo json_encode(['ok' => true, 'total' => count($items)]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
  }

  if ($action === 'guardar_certificaciones') {
    try {
      $db->exec("CREATE TABLE IF NOT EXISTS talento_certificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        nombre VARCHAR(250) NOT NULL DEFAULT '',
        organizacion VARCHAR(200) NOT NULL DEFAULT '',
        fecha_expedicion VARCHAR(20) DEFAULT '',
        url_credencial TEXT DEFAULT NULL,
        archivo_url TEXT DEFAULT NULL,
        archivo_nombre VARCHAR(200) DEFAULT NULL,
        orden TINYINT DEFAULT 0,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_u (usuario_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $items = json_decode($_POST['items'] ?? '[]', true);
      if (!is_array($items)) {
        echo json_encode(['ok' => false, 'msg' => 'Datos invalidos']);
        exit;
      }
      $db->prepare("DELETE FROM talento_certificaciones WHERE usuario_id=?")->execute([$usuario['id']]);
      $stmt = $db->prepare("INSERT INTO talento_certificaciones (usuario_id,nombre,organizacion,fecha_expedicion,url_credencial,archivo_url,archivo_nombre,orden) VALUES (?,?,?,?,?,?,?,?)");
      foreach (array_values($items) as $i => $c) {
        $stmt->execute([$usuario['id'], substr(trim($c['nom'] ?? ''), 0, 250), substr(trim($c['org'] ?? ''), 0, 200), substr(trim($c['fecha'] ?? ''), 0, 20), $c['url'] ?? null, $c['archivo'] ?? null, substr($c['archivoNom'] ?? '', 0, 200), $i]);
      }
      echo json_encode(['ok' => true, 'total' => count($items)]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
  }

  if ($action === 'solicitar_vacante') {
    try {
      if ($usuario['tipo'] !== 'candidato') {
        echo json_encode(['ok' => false, 'msg' => 'Solo candidatos pueden solicitar vacantes.']);
        exit;
      }
      $empleo_id = (int) ($_POST['empleo_id'] ?? 0);
      if (!$empleo_id) {
        echo json_encode(['ok' => false, 'msg' => 'Vacante no válida.']);
        exit;
      }

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

      $chkV = $db->prepare("SELECT id, titulo FROM empleos WHERE id=? AND activo=1 LIMIT 1");
      $chkV->execute([$empleo_id]);
      $vacante = $chkV->fetch();
      if (!$vacante) {
        echo json_encode(['ok' => false, 'msg' => 'La vacante no está disponible.']);
        exit;
      }

      $chkS = $db->prepare("SELECT id FROM solicitudes_empleo WHERE empleo_id=? AND candidato_id=?");
      $chkS->execute([$empleo_id, $usuario['id']]);
      if ($chkS->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Ya aplicaste a esta vacante.', 'ya_aplicado' => true]);
        exit;
      }
      $mensaje = substr(trim($_POST['mensaje'] ?? ''), 0, 1000);
      $db->prepare("INSERT INTO solicitudes_empleo (empleo_id, candidato_id, mensaje) VALUES (?,?,?)")
        ->execute([$empleo_id, $usuario['id'], $mensaje ?: null]);
      echo json_encode(['ok' => true, 'msg' => '✅ ¡Solicitud enviada! La empresa revisará tu perfil.']);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }

  if ($action === 'mis_solicitudes') {
    try {
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
      $stmt = $db->prepare("
        SELECT se.id, se.estado, se.creado_en,
               e.titulo, e.ciudad, e.modalidad, e.tipo_contrato,
               COALESCE(pe.nombre_empresa, u.nombre) AS empresa
        FROM solicitudes_empleo se
        JOIN empleos e ON e.id = se.empleo_id
        LEFT JOIN usuarios u ON u.id = e.empresa_id
        LEFT JOIN perfiles_empresa pe ON pe.usuario_id = e.empresa_id
        WHERE se.candidato_id = ?
        ORDER BY se.creado_en DESC
        LIMIT 20
      ");
      $stmt->execute([$usuario['id']]);
      echo json_encode(['ok' => true, 'solicitudes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
  }

  if ($action === 'guardar_aptitudes_extra') {
    try {
      $bland = substr(trim($_POST['aptitudes_bland'] ?? ''), 0, 500);
      $idiomas = substr(trim($_POST['aptitudes_idiomas'] ?? ''), 0, 300);
      $skillsTec = isset($_POST['skills_tec']) ? substr(trim($_POST['skills_tec']), 0, 500) : null;
      try {
        $db->exec("ALTER TABLE talento_perfil ADD COLUMN IF NOT EXISTS aptitudes_bland VARCHAR(500) DEFAULT ''");
        $db->exec("ALTER TABLE talento_perfil ADD COLUMN IF NOT EXISTS aptitudes_idiomas VARCHAR(300) DEFAULT ''");
      } catch (Exception $e2) {
      }
      $chk = $db->prepare("SELECT id FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
      $chk->execute([$usuario['id']]);
      $row = $chk->fetch();
      if ($row) {
        if ($skillsTec !== null) {
          $db->prepare("UPDATE talento_perfil SET aptitudes_bland=?, aptitudes_idiomas=?, skills=? WHERE id=?")->execute([$bland, $idiomas, $skillsTec, $row['id']]);
        } else {
          $db->prepare("UPDATE talento_perfil SET aptitudes_bland=?, aptitudes_idiomas=? WHERE id=?")->execute([$bland, $idiomas, $row['id']]);
        }
      } else {
        $skVal = $skillsTec ?? '';
        $db->prepare("INSERT INTO talento_perfil (usuario_id,aptitudes_bland,aptitudes_idiomas,skills,visible,visible_admin) VALUES (?,?,?,?,0,1)")->execute([$usuario['id'], $bland, $idiomas, $skVal]);
      }
      echo json_encode(['ok' => true]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
  }

  echo json_encode(['ok' => false, 'msg' => 'Acción desconocida.']);
  exit;
}

$tipo = $usuario['tipo'] ?? 'candidato';

// Redirigir a su dashboard correspondiente si no es candidato
if ($tipo === 'servicio') {
  header('Location: dashboard_servicios.php');
  exit;
}
if ($tipo === 'negocio') {
  header('Location: dashboard_negocios.php');
  exit;
}
if ($tipo === 'empresa') {
  header('Location: dashboard_empresa.php');
  exit;
}

$tp = $db->prepare("SELECT * FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
$tp->execute([$usuario['id']]);
$talento = $tp->fetch() ?: ['profesion' => '', 'bio' => '', 'skills' => '', 'visible' => 0, 'visible_admin' => 1, 'generos' => '', 'precio_desde' => null, 'tipo_servicio' => '', 'calificacion' => 0];

function getYoutubeId($url)
{
  preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
  return $m[1] ?? '';
}

$galeriaItems = [];
$galeriaTotal = 0;
try {
  $db->exec("CREATE TABLE IF NOT EXISTS talento_galeria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo ENUM('foto','video') NOT NULL DEFAULT 'foto',
        archivo VARCHAR(255) NOT NULL DEFAULT '',
        url_video VARCHAR(500) DEFAULT NULL,
        titulo VARCHAR(150) DEFAULT NULL,
        descripcion TEXT DEFAULT NULL,
        orden TINYINT NOT NULL DEFAULT 0,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $gStmt = $db->prepare("SELECT * FROM talento_galeria WHERE usuario_id=? AND activo=1 ORDER BY orden ASC, id ASC");
  $gStmt->execute([$usuario['id']]);
  $galeriaItems = $gStmt->fetchAll();
  $galeriaTotal = count($galeriaItems);
} catch (Exception $e) {
  $galeriaItems = [];
  $galeriaTotal = 0;
}

$ep = null;
if ($tipo === 'empresa') {
  $epStmt = $db->prepare("SELECT * FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
  $epStmt->execute([$usuario['id']]);
  $ep = $epStmt->fetch() ?: [];
}

$np = null;
if ($tipo === 'negocio' || $tipo === 'empresa') {
  try {
    $npStmt = $db->prepare("SELECT * FROM negocios_locales WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
    $npStmt->execute([$usuario['id']]);
    $np = $npStmt->fetch() ?: null;
  } catch (Exception $e) {
    $np = null;
  }
}

$extras = [];
try {
  $solStmt = $db->prepare("SELECT nota_admin FROM solicitudes_ingreso WHERE correo=? ORDER BY creado_en DESC LIMIT 1");
  $solStmt->execute([$usuario['correo']]);
  $solRow = $solStmt->fetch();
  if ($solRow && !empty($solRow['nota_admin'])) {
    $extras = json_decode($solRow['nota_admin'], true) ?: [];
  }
} catch (Exception $e) {
  $extras = [];
}

$subTipo = '';
if (!empty($extras['tipo_negocio_reg']))
  $subTipo = $extras['tipo_negocio_reg'] === 'cc' ? 'negocio_cc' : 'negocio_emp';
if (!empty($extras['profesion_tipo']) && $tipo === 'candidato') {
  $pt = strtolower($extras['profesion_tipo']);
  if (preg_match('/(dj|disc jockey|chirimía|chirimia|música|musica|cantante|fotograf|video|catering|decorac|animador|maestro.*ceremonia)/i', $pt))
    $subTipo = 'servicio';
}

require_once __DIR__ . '/Php/badges_helper.php';
if (file_exists(__DIR__ . '/Php/planes_helper.php'))
  require_once __DIR__ . '/Php/planes_helper.php';
$badgesUsuario = getBadgesUsuario($db, $usuario['id']);
$badgesHTML = renderBadges($badgesUsuario);
$tieneVerificado = (bool) ($usuario['verificado'] ?? false) || tieneBadge($badgesUsuario, 'Verificado') || tieneBadge($badgesUsuario, 'Usuario Verificado') || tieneBadge($badgesUsuario, 'Empresa Verificada');
$tienePremium = tieneBadge($badgesUsuario, 'Premium');
$tieneTop = tieneBadge($badgesUsuario, 'Top');
$tieneDestacado = tieneBadge($badgesUsuario, 'Destacado') || (int) ($talento['destacado'] ?? 0);

$nrChat = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario=? AND leido=0");
$nrChat->execute([$usuario['id']]);
$chatNoLeidos = (int) $nrChat->fetchColumn();

$vistasTotal = 0;
$vistas7dias = 0;
try {
  $db->exec("CREATE TABLE IF NOT EXISTS perfil_vistas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        visitante_id INT DEFAULT NULL,
        ip VARCHAR(64) DEFAULT NULL,
        seccion VARCHAR(32) DEFAULT 'talentos',
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario_id (usuario_id),
        INDEX idx_creado_en (creado_en)
    )");
  $vt = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=?");
  $vt->execute([$usuario['id']]);
  $vistasTotal = (int) $vt->fetchColumn();
  $v7 = $db->prepare("SELECT COUNT(*) FROM perfil_vistas WHERE usuario_id=? AND creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
  $v7->execute([$usuario['id']]);
  $vistas7dias = (int) $v7->fetchColumn();
} catch (Exception $e) {
  $vistasTotal = 0;
  $vistas7dias = 0;
}

$datosPlan = [];
$planActual = 'semilla';
$maxVisitantes = 0;
if (function_exists('getDatosPlan')) {
  $datosPlan = getDatosPlan($db, $usuario['id']);
  $planActual = $datosPlan['plan'];
  $maxVisitantes = $datosPlan['config']['visitantes'] ?? 0;
}

$visitantesRecientes = [];
if ($maxVisitantes !== 0) {
  try {
    $limVis = ($maxVisitantes === -1) ? 50 : (int) $maxVisitantes;
    $vvStmt = $db->prepare("
      SELECT pv.visitante_id, pv.creado_en,
             u.nombre, u.apellido, u.tipo
      FROM perfil_vistas pv
      JOIN usuarios u ON u.id = pv.visitante_id AND u.activo = 1
      WHERE pv.usuario_id = ? AND pv.visitante_id IS NOT NULL
      ORDER BY pv.creado_en DESC
      LIMIT $limVis
    ");
    $vvStmt->execute([$usuario['id']]);
    $visitantesRecientes = $vvStmt->fetchAll();
  } catch (Exception $e) {
    $visitantesRecientes = [];
  }
}

$stmtV = $db->prepare("SELECT estado,nota_rechazo FROM verificaciones WHERE usuario_id=? ORDER BY creado_en DESC LIMIT 1");
$stmtV->execute([$usuario['id']]);
$verifDoc = $stmtV->fetch();
$estadoVerif = $verifDoc ? $verifDoc['estado'] : null;
$notaRechazo = $verifDoc ? ($verifDoc['nota_rechazo'] ?? '') : '';

$vacantesActivas = 0;
$historialVacantes = [];
if ($tipo === 'empresa') {
  try {
    $vs = $db->prepare("SELECT COUNT(*) FROM empleos WHERE empresa_id=? AND activo=1");
    $vs->execute([$usuario['id']]);
    $vacantesActivas = (int) $vs->fetchColumn();
    $vh = $db->prepare("SELECT id, titulo, ciudad, activo, modalidad, tipo_contrato, creado_en FROM empleos WHERE empresa_id=? ORDER BY creado_en DESC LIMIT 10");
    $vh->execute([$usuario['id']]);
    $historialVacantes = $vh->fetchAll();
  } catch (Exception $e) {
  }
}

$vacantesDisponibles = [];
if ($tipo === 'candidato' || $subTipo === 'servicio') {
  try {
    $vq = $db->prepare("
      SELECT
        e.id,
        e.titulo,
        e.ciudad,
        e.modalidad,
        e.tipo_contrato,
        e.salario_texto,
        e.categoria,
        e.creado_en,
        COALESCE(pe.nombre_empresa, u.nombre) AS empresa
      FROM empleos e
      LEFT JOIN perfiles_empresa pe ON pe.usuario_id = e.empresa_id
      LEFT JOIN usuarios u ON u.id = e.empresa_id
      WHERE e.activo = 1
        AND (e.vence_en IS NULL OR e.vence_en >= CURDATE())
      ORDER BY e.creado_en DESC
      LIMIT 5
    ");
    $vq->execute();
    $vacantesDisponibles = $vq->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $vacantesDisponibles = [];
  }
}

$fotoUrl = !empty($usuario['foto']) ? (str_starts_with($usuario['foto'], 'http') ? htmlspecialchars($usuario['foto']) : 'uploads/fotos/' . htmlspecialchars($usuario['foto'])) : '';

try {
  $db->exec("ALTER TABLE usuarios ADD COLUMN banner VARCHAR(500) DEFAULT '' AFTER foto");
} catch (Exception $e) {
}

$usuario = $db->prepare("SELECT * FROM usuarios WHERE id=?");
$usuario->execute([$_SESSION['usuario_id']]);
$usuario = $usuario->fetch();
$bannerUrl = !empty($usuario['banner']) ? (str_starts_with($usuario['banner'], 'http') ? htmlspecialchars($usuario['banner']) : 'uploads/banners/' . htmlspecialchars($usuario['banner'])) : '';
$inicial = strtoupper(mb_substr($usuario['nombre'], 0, 1));
$nombreCompleto = htmlspecialchars(trim($usuario['nombre'] . ' ' . ($usuario['apellido'] ?? '')));
$correo = htmlspecialchars($usuario['correo']);
$ciudad = htmlspecialchars($usuario['ciudad'] ?? '');
$telefono = htmlspecialchars($usuario['telefono'] ?? '');
$fechaRegistro = date('d \de F Y', strtotime($usuario['creado_en']));

$nombreEmpresa = htmlspecialchars($ep['nombre_empresa'] ?? $extras['nombre_negocio'] ?? $usuario['nombre'] ?? '');
$nombreNegocio = htmlspecialchars($np['nombre_negocio'] ?? $extras['nombre_negocio'] ?? '');
$sectorEmp = htmlspecialchars($ep['sector'] ?? $extras['sector'] ?? '');
$catNeg = htmlspecialchars($np['categoria'] ?? $extras['categoria_neg'] ?? '');
$profesionTipo = htmlspecialchars($extras['profesion_tipo'] ?? $talento['profesion'] ?? '');

function calcPct(array $usuario, array $talento, string $tipo, array $extras): int
{
  $base = ['nombre', 'correo', 'telefono', 'ciudad'];
  $llenos = count(array_filter($base, fn($c) => !empty($usuario[$c])));
  $pct = (int) ($llenos / count($base) * 100);
  if ($tipo === 'candidato') {
    if (!empty($talento['profesion']))
      $pct = min(100, $pct + 15);
    if (!empty($talento['bio']))
      $pct = min(100, $pct + 10);
    if (!empty($talento['skills']))
      $pct = min(100, $pct + 5);
  } elseif ($tipo === 'empresa') {
    if (!empty($extras['sector'] ?? ''))
      $pct = min(100, $pct + 15);
    if (!empty($extras['rep_legal'] ?? ''))
      $pct = min(100, $pct + 10);
  }
  if (!empty($usuario['foto']))
    $pct = min(100, $pct + 10);
  return $pct;
}
$pct = calcPct($usuario, $talento, $tipo, $extras);

$tipoColors = [
  'candidato' => ['border' => 'var(--v3)', 'chip_bg' => 'rgba(39,168,85,.18)', 'chip_color' => 'var(--vlima)', 'label' => '👤 Candidato', 'deco' => '🌿'],
  'empresa' => ['border' => 'var(--r3)', 'chip_bg' => 'rgba(26,86,219,.18)', 'chip_color' => 'var(--rcielo)', 'label' => '🏢 Empresa', 'deco' => '🏢'],
  'negocio' => ['border' => 'var(--a3)', 'chip_bg' => 'rgba(245,200,0,.18)', 'chip_color' => 'var(--acrem)', 'label' => '🏪 Negocio', 'deco' => '🏪'],
];
$tc = $tipoColors[$tipo] ?? $tipoColors['candidato'];
if ($subTipo === 'negocio_cc')
  $tc['label'] = '🏬 C.C. El Caraño';
if ($subTipo === 'servicio') {
  $tc['label'] = '🎧 Servicios';
  $tc['deco'] = '🎧';
  $tc['border'] = 'var(--a3)';
  $tc['chip_color'] = 'var(--acrem)';
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Mi Panel – QuibdóConecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root {
      --brand: #1a7a3c;
      --brand2: #27a855;
      --brand-light: #e8f5ee;
      --brand-mid: #a5d6a7;
      --accent: #f9a825;
      --accent-light: #fff8e1;
      --danger: #e53935;
      --ink: #0f1a14;
      --ink2: #2d3f35;
      --ink3: #5a7363;
      --ink4: #8fa898;
      --surface: #ffffff;
      --surface2: #f6faf7;
      --surface3: #edf5ef;
      --border: rgba(27, 122, 60, .12);
      --border2: rgba(27, 122, 60, .22);
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
      padding: 0;
    }

    html {
      font-size: 15px;
      scroll-behavior: smooth;
    }

    body {
      font-family: var(--font);
      background: var(--surface2);
      color: var(--ink);
      min-height: 100vh;
      display: flex;
    }

    a {
      text-decoration: none;
      color: inherit;
    }

    /* ──── SIDEBAR ──────────────────────────────── */
    .sidebar {
      width: var(--nav-w);
      background: var(--surface);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: var(--top-h);
      left: 0;
      bottom: 0;
      z-index: 150;
      transition: transform .3s ease;
      overflow-y: auto;
      overflow-x: hidden;
    }

    .sidebar-logo {
      padding: 14px 18px 12px;
      border-bottom: 1px solid var(--border);
      display: none;
      align-items: center;
      gap: 10px;
    }

    .sidebar-logo img {
      height: 32px;
    }

    .sidebar-logo-txt {
      font-size: 13px;
      font-weight: 700;
      color: var(--brand);
      letter-spacing: -.2px;
      line-height: 1.2;
    }

    .sidebar-logo-sub {
      font-size: 11px;
      color: var(--ink4);
      font-weight: 400;
    }

    .sidebar-user {
      margin: 14px 14px 10px;
      background: var(--brand-light);
      border-radius: var(--radius);
      padding: 14px;
      display: flex;
      align-items: center;
      gap: 10px;
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
      font-size: 16px;
      flex-shrink: 0;
      overflow: hidden;
      border: 2px solid #fff;
    }

    .su-av img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .su-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      line-height: 1.3;
    }

    .su-role {
      font-size: 11px;
      color: var(--ink3);
    }

    .sidebar-nav {
      flex: 1;
      padding: 6px 10px;
      overflow-y: auto;
    }

    .nav-section {
      margin-bottom: 4px;
    }

    .nav-section-label {
      font-size: 10px;
      font-weight: 700;
      color: var(--ink4);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      padding: 10px 10px 4px;
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
    }

    .nav-item:hover {
      background: var(--surface2);
      color: var(--brand);
    }

    .nav-item.active {
      background: var(--brand-light);
      color: var(--brand);
      font-weight: 700;
    }

    .nav-item .ni-ico {
      font-size: 15px;
      width: 20px;
      text-align: center;
      flex-shrink: 0;
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
      text-align: center;
    }

    .sidebar-bottom {
      padding: 12px 14px;
      border-top: 1px solid var(--border);
    }

    .sidebar-plan {
      background: linear-gradient(135deg, var(--brand) 0%, #1a9e4d 100%);
      border-radius: var(--radius);
      padding: 14px;
      color: #fff;
      margin-bottom: 10px;
    }

    .sp-label {
      font-size: 10px;
      font-weight: 600;
      opacity: .75;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .sp-name {
      font-size: 15px;
      font-weight: 800;
      margin: 2px 0 8px;
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
      transition: background .2s;
    }

    .sp-btn:hover {
      background: rgba(255, 255, 255, .32);
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
    }

    .nav-salir:hover {
      background: #fef2f2;
      color: var(--danger);
    }

    /* ──── TOPBAR ──────────────────────────────── */
    .topbar {
      height: var(--top-h);
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      position: fixed;
      top: 0;
      left: var(--nav-w);
      right: 0;
      z-index: 250;
      display: flex;
      align-items: center;
      padding: 0 28px;
      gap: 16px;
    }

    .topbar-logo {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
      text-decoration: none;
      cursor: pointer;
    }

    .topbar-logo img {
      height: 34px;
    }

    .topbar-logo-txt {
      display: none;
    }

    .topbar-logo-sub {
      display: none;
    }

    .topbar-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--ink);
      flex: 1;
    }

    .topbar-title span {
      color: var(--brand);
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 10px;
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
      transition: all .18s;
    }

    .tb-btn:hover {
      background: var(--brand-light);
      border-color: var(--brand-mid);
    }

    .tb-dot {
      position: absolute;
      top: 6px;
      right: 6px;
      width: 8px;
      height: 8px;
      background: var(--danger);
      border-radius: 50%;
      border: 2px solid var(--surface);
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
      z-index: 300;
    }

    .tb-notif-panel.open {
      display: block;
    }

    .tnp-head {
      padding: 12px 16px;
      font-size: 13px;
      font-weight: 700;
      border-bottom: 1px solid var(--border);
    }

    .tnp-body {
      max-height: 280px;
      overflow-y: auto;
    }

    .tnp-empty {
      padding: 20px;
      text-align: center;
      color: var(--ink4);
      font-size: 13px;
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
      cursor: pointer;
    }

    .hamburger span {
      width: 18px;
      height: 2px;
      background: var(--ink2);
      border-radius: 2px;
      transition: all .2s;
    }

    /* ──── MAIN CONTENT ──────────────────────────── */
    .main {
      margin-left: var(--nav-w);
      margin-top: calc(var(--top-h) + 4px);
      flex: 1;
      min-width: 0;
      padding: 28px;
    }

    /* ──── HERO PROFILE STRIP ─────────────────── */
    .hero-strip {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 24px 28px;
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
    }

    .hero-strip::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--brand), var(--brand2), var(--accent));
    }

    .hero-av {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: var(--brand);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      font-weight: 800;
      flex-shrink: 0;
      overflow: hidden;
      cursor: pointer;
      border: 3px solid var(--brand-light);
      transition: border-color .2s;
    }

    .hero-av:hover {
      border-color: var(--brand);
    }

    .hero-av img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .hero-info {
      flex: 1;
      min-width: 0;
    }

    .hero-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      margin-bottom: 6px;
    }

    .hchip {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      white-space: nowrap;
    }

    .hc-tipo {
      background: var(--brand-light);
      color: var(--brand);
    }

    .hc-v {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .hc-p {
      background: #fff8e1;
      color: #f57f17;
    }

    .hc-top {
      background: #fce4ec;
      color: #c62828;
    }

    .hc-dest {
      background: #f3e5f5;
      color: #6a1b9a;
    }

    .hero-name {
      font-size: 22px;
      font-weight: 800;
      color: var(--ink);
      letter-spacing: -.5px;
    }

    .hero-sub {
      font-size: 13px;
      color: var(--ink3);
      margin-top: 2px;
    }

    .hero-stats {
      display: flex;
      gap: 24px;
      flex-shrink: 0;
    }

    .hs {
      text-align: center;
    }

    .hs-val {
      font-size: 24px;
      font-weight: 800;
      color: var(--brand);
      line-height: 1;
    }

    .hs-lab {
      font-size: 11px;
      color: var(--ink4);
      margin-top: 2px;
      font-weight: 500;
    }

    .hero-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
      flex-shrink: 0;
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
      white-space: nowrap;
    }

    .btn-primary:hover {
      background: #16692f;
      transform: translateY(-1px);
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
      white-space: nowrap;
    }

    .btn-secondary:hover {
      background: var(--brand-light);
      color: var(--brand);
      border-color: var(--brand-mid);
    }

    /* ──── ALERT BANNER ──────────────────────────── */
    .alert-bar {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 18px;
      border-radius: var(--radius);
      margin-bottom: 18px;
      font-size: 13px;
    }

    .alert-bar.as {
      background: #fff8e1;
      border: 1px solid #ffe082;
      color: #7c5000;
    }

    .alert-bar.ap {
      background: #e3f2fd;
      border: 1px solid #90caf9;
      color: #1565c0;
    }

    .alert-bar.ar {
      background: #fce8e8;
      border: 1px solid #f5a5a5;
      color: #b71c1c;
    }

    .alert-bar.av {
      background: #e8f5e9;
      border: 1px solid #a5d6a7;
      color: #1b5e20;
    }

    .alert-bar .a-ico {
      font-size: 18px;
      flex-shrink: 0;
    }

    .alert-bar .a-txt {
      flex: 1;
    }

    .alert-bar .a-txt strong {
      font-weight: 700;
    }

    .alert-bar .a-txt span {
      margin-left: 6px;
      opacity: .8;
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
    }

    /* ──── GRID LAYOUT ──────────────────────────── */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 18px;
    }

    .col-4 {
      grid-column: span 4;
    }

    .col-6 {
      grid-column: span 6;
    }

    .col-8 {
      grid-column: span 8;
    }

    .col-12 {
      grid-column: span 12;
    }

    .col-3 {
      grid-column: span 3;
    }

    /* ──── CARDS ──────────────────────────────── */
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }

    .card-header {
      padding: 16px 20px 14px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .card-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .card-link {
      font-size: 12px;
      color: var(--brand);
      font-weight: 600;
    }

    .card-body {
      padding: 18px 20px;
    }

    /* ──── METRIC CARDS ──────────────────────────── */
    .metric-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 18px 20px;
      display: flex;
      align-items: center;
      gap: 14px;
      transition: all .2s;
      cursor: default;
    }

    .metric-card:hover {
      border-color: var(--brand-mid);
      box-shadow: var(--shadow);
      transform: translateY(-1px);
    }

    .mc-ico {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      flex-shrink: 0;
    }

    .mc-ico.g {
      background: var(--brand-light);
    }

    .mc-ico.a {
      background: #fff8e1;
    }

    .mc-ico.o {
      background: #fff3e0;
    }

    .mc-ico.m {
      background: #f3e5f5;
    }

    .mc-val {
      font-size: 26px;
      font-weight: 800;
      color: var(--ink);
      line-height: 1;
    }

    .mc-lab {
      font-size: 12px;
      color: var(--ink3);
      margin-top: 2px;
    }

    .mc-sub {
      font-size: 11px;
      color: var(--brand);
      font-weight: 600;
      margin-top: 4px;
      cursor: pointer;
    }

    /* ──── PROFILE CARD ──────────────────────────── */
    .profile-card {
      padding: 20px;
    }

    .pc-av {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--brand);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 22px;
      font-weight: 800;
      overflow: hidden;
      cursor: pointer;
      margin-bottom: 12px;
      border: 3px solid var(--brand-light);
    }

    .pc-av img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .pc-name {
      font-size: 16px;
      font-weight: 800;
      color: var(--ink);
    }

    .pc-role {
      font-size: 12px;
      color: var(--ink3);
      margin-bottom: 14px;
    }

    .pc-rows {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .pc-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: var(--ink2);
    }

    .pc-row-ico {
      font-size: 14px;
      flex-shrink: 0;
      width: 18px;
      text-align: center;
    }

    /* Progress bar */
    .prog-wrap {
      margin: 14px 0;
    }

    .prog-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
      font-size: 12px;
      color: var(--ink3);
      font-weight: 600;
    }

    .prog-track {
      height: 6px;
      background: var(--surface3);
      border-radius: 6px;
      overflow: hidden;
    }

    .prog-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      border-radius: 6px;
      transition: width 1s ease;
    }

    /* Visibility toggle */
    .vis-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 0;
      border-top: 1px solid var(--border);
      margin-top: 4px;
    }

    .vis-label {
      font-size: 12.5px;
      font-weight: 700;
      color: var(--ink2);
    }

    .vis-sub {
      font-size: 11px;
      color: var(--ink4);
    }

    .tog {
      position: relative;
      display: inline-block;
      width: 40px;
      height: 22px;
    }

    .tog input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .tog-sl {
      position: absolute;
      inset: 0;
      background: #ddd;
      border-radius: 22px;
      cursor: pointer;
      transition: .3s;
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
      box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
    }

    input:checked+.tog-sl {
      background: var(--brand);
    }

    input:checked+.tog-sl::before {
      transform: translateX(18px);
    }

    .pv-chip {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
    }

    .pv-chip.ok {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .pv-chip.off {
      background: #fff8e1;
      color: #f57f17;
    }

    /* ──── QUICK ACTIONS GRID ──────────────────── */
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 10px;
      padding: 16px 20px;
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
      position: relative;
    }

    .action-item:hover {
      background: var(--brand-light);
      border-color: var(--brand-mid);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(27, 122, 60, .1);
    }

    .action-item .ai-ico {
      font-size: 22px;
    }

    .action-item .ai-label {
      font-size: 11.5px;
      font-weight: 700;
      color: var(--ink2);
      line-height: 1.2;
    }

    .action-item .ai-sub {
      font-size: 10px;
      color: var(--ink4);
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
      border-radius: 20px;
    }

    /* ──── VISITOR CARDS ──────────────────────── */
    .visitor-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      padding: 14px 20px;
    }

    .visitor-card {
      display: flex;
      align-items: center;
      gap: 10px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 10px 14px;
      min-width: 180px;
    }

    .vc-av {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      font-size: 14px;
      flex-shrink: 0;
    }

    .vc-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
    }

    .vc-meta {
      font-size: 11px;
      color: var(--ink4);
    }

    /* ──── PLAN USAGE BARS ──────────────────────── */
    .plan-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 12px;
      padding: 16px 20px;
    }

    .plan-bar {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 14px;
    }

    .pb-label {
      font-size: 12px;
      font-weight: 600;
      color: var(--ink3);
      margin-bottom: 6px;
    }

    .pb-count {
      font-size: 20px;
      font-weight: 800;
      color: var(--ink);
      line-height: 1;
      margin-bottom: 8px;
    }

    .pb-count span {
      font-size: 13px;
      font-weight: 400;
      color: var(--ink4);
    }

    .pb-track {
      height: 5px;
      background: rgba(0, 0, 0, .07);
      border-radius: 5px;
    }

    .pb-fill {
      height: 5px;
      border-radius: 5px;
      transition: width .5s;
    }

    .pb-fill.low {
      background: var(--brand);
    }

    .pb-fill.mid {
      background: var(--accent);
    }

    .pb-fill.high {
      background: var(--danger);
    }

    .pb-warn {
      font-size: 10px;
      font-weight: 700;
      margin-top: 5px;
      color: var(--danger);
    }

    /* ──── JOB LIST ──────────────────────────── */
    .job-list {
      padding: 0 20px 16px;
    }

    .job-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
    }

    .job-item:last-child {
      border-bottom: none;
    }

    .job-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .job-dot.act {
      background: var(--brand2);
    }

    .job-dot.pen {
      background: var(--accent);
    }

    .job-dot.cer {
      background: var(--ink4);
    }

    .job-info {
      flex: 1;
      min-width: 0;
    }

    .job-name {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .job-meta {
      font-size: 11px;
      color: var(--ink4);
      margin-top: 2px;
    }

    .job-date {
      font-size: 11px;
      color: var(--ink4);
      flex-shrink: 0;
    }

    .job-empty {
      text-align: center;
      padding: 28px 16px;
    }

    .job-empty-ico {
      font-size: 36px;
      margin-bottom: 8px;
    }

    .job-empty-txt {
      font-size: 13px;
      font-weight: 600;
      color: var(--ink2);
      margin-bottom: 4px;
    }

    /* ──── PROFILE SECTIONS (edu/cert/aptitudes) ─── */
    .psec {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      margin-bottom: 16px;
      overflow: hidden;
    }

    .psec-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 16px;
      border-bottom: 1px solid var(--border);
    }

    .psec-title {
      font-size: 13.5px;
      font-weight: 700;
      color: var(--ink);
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .psec-btns {
      display: flex;
      gap: 6px;
    }

    .psec-btn {
      width: 30px;
      height: 30px;
      border-radius: 8px;
      background: var(--surface2);
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      cursor: pointer;
      transition: all .18s;
      font-family: var(--font);
    }

    .psec-btn:hover {
      background: var(--brand-light);
      border-color: var(--brand-mid);
      color: var(--brand);
    }

    .psec-body {
      padding: 6px 16px 10px;
    }

    .psec-empty {
      text-align: center;
      padding: 12px 16px;
      color: var(--ink3);
      font-size: 13px;
    }

    .psec-empty-ico {
      font-size: 22px;
      margin-bottom: 4px;
    }

    .btn-dashed {
      display: inline-block;
      margin-top: 12px;
      padding: 8px 20px;
      border: 1.5px dashed var(--border2);
      border-radius: 20px;
      background: none;
      color: var(--brand);
      font-size: 12.5px;
      font-weight: 700;
      cursor: pointer;
      font-family: var(--font);
      transition: all .2s;
    }

    .btn-dashed:hover {
      background: var(--brand-light);
      border-color: var(--brand);
    }

    /* Aptitudes chips */
    .apt-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 7px;
    }

    .apt-chip {
      display: flex;
      align-items: center;
      gap: 5px;
      padding: 5px 12px;
      border-radius: 20px;
      background: var(--brand-light);
      color: var(--brand);
      font-size: 12px;
      font-weight: 600;
    }

    /* ──── GALLERY ──────────────────────────── */
    .gallery-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
      gap: 8px;
      padding: 14px 20px;
    }

    .gallery-item {
      position: relative;
      border-radius: 10px;
      overflow: hidden;
      background: var(--surface2);
      border: 1px solid var(--border);
      aspect-ratio: 1;
    }

    .gallery-item img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .gallery-item-del {
      position: absolute;
      top: 5px;
      right: 5px;
      background: rgba(0, 0, 0, .55);
      border: none;
      color: #fff;
      border-radius: 6px;
      padding: 3px 7px;
      font-size: 11px;
      cursor: pointer;
      line-height: 1;
    }

    .gallery-caption {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 5px 8px;
      background: rgba(0, 0, 0, .55);
      color: #fff;
      font-size: 10px;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .gallery-empty {
      text-align: center;
      padding: 14px 16px;
      border: 1.5px dashed var(--border2);
      border-radius: 10px;
      color: var(--ink4);
      margin: 10px 16px;
    }

    .gallery-empty-ico {
      font-size: 22px;
      margin-bottom: 4px;
    }

    /* ──── RECENT ACTIVITY ──────────────────────── */
    .activity-list {
      padding: 4px 20px 16px;
    }

    .act-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid var(--border);
    }

    .act-item:last-child {
      border-bottom: none;
    }

    .act-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--brand-mid);
      margin-top: 5px;
      flex-shrink: 0;
    }

    .act-txt {
      font-size: 12.5px;
      color: var(--ink2);
      flex: 1;
    }

    .act-txt strong {
      color: var(--ink);
    }

    .act-time {
      font-size: 11px;
      color: var(--ink4);
      flex-shrink: 0;
      margin-top: 2px;
    }

    /* ──── MODALS (keep existing logic) ───────── */
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
    }

    .modal-ov.open {
      display: flex;
    }

    /* ──── OVERLAY SIDEBAR ────────────────────── */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .35);
      z-index: 199;
    }

    /* ──── RESPONSIVE ──────────────────────────── */
    @media (max-width: 1100px) {
      .col-4 {
        grid-column: span 6;
      }

      .col-3 {
        grid-column: span 6;
      }
    }

    @media (max-width: 820px) {
      :root {
        --nav-w: 0px;
      }

      .sidebar {
        transform: translateX(-240px);
        top: 0;
        z-index: 350;
        width: 240px;
      }

      .sidebar-logo {
        display: flex;
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .sidebar-overlay {
        z-index: 340;
      }

      .sidebar-overlay.open {
        display: block;
      }

      .topbar {
        left: 0;
        padding: 0 16px;
        z-index: 300;
      }

      .topbar-title {
        display: none;
      }

      .hamburger {
        display: flex;
      }

      .main {
        padding: 18px 16px;
        margin-left: 0;
      }

      .hero-strip {
        flex-wrap: wrap;
        gap: 14px;
        padding: 18px;
      }

      .hero-stats {
        gap: 14px;
      }

      .hero-actions {
        flex-direction: row;
        flex-wrap: wrap;
      }

      .col-4,
      .col-6,
      .col-8,
      .col-3 {
        grid-column: span 12;
      }

      .dashboard-grid {
        gap: 14px;
      }
    }

    @media (max-width: 480px) {
      .hero-av {
        width: 56px;
        height: 56px;
        font-size: 22px;
      }

      .hero-name {
        font-size: 18px;
      }

      .hero-stats {
        gap: 12px;
      }

      .hs-val {
        font-size: 20px;
      }

      .main {
        padding: 14px 12px;
      }
    }

    /* ──── BANDERA CHOCÓ ──────────────────────── */
    .barra-bandera {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #1f9d55 33.3%, #d4a017 33.3% 66.6%, #1a3a6b 66.6%);
      z-index: 9999;
    }

    /* ──── IDENTIDAD CHOCOANA ─────────────────── */
    :root {
      --choco-verde: #1f9d55;
      --choco-dorado: #d4a017;
      --choco-azul: #1a3a6b;
      --choco-bg: #f0faf4;
      --choco-gold-light: #fef9e7;
    }

    body {
      font-family: var(--font);
      background: var(--choco-bg);
      color: var(--ink);
      min-height: 100vh;
      display: flex;
    }

    .main {
      margin-left: var(--nav-w);
      margin-top: var(--top-h);
      flex: 1;
      min-width: 0;
      padding: 28px;
      background: var(--choco-bg);
    }

    .topbar {
      height: var(--top-h);
      background: linear-gradient(135deg, #ffffff 60%, #f0faf4 100%);
      border-bottom: 2px solid rgba(31, 157, 85, .15);
      position: fixed;
      top: 4px;
      left: var(--nav-w);
      right: 0;
      z-index: 250;
      display: flex;
      align-items: center;
      padding: 0 28px;
      gap: 16px;
      box-shadow: 0 2px 12px rgba(31, 157, 85, .08);
    }

    .sidebar {
      width: var(--nav-w);
      background: linear-gradient(180deg, #ffffff 0%, #f8fffe 100%);
      border-right: 2px solid rgba(31, 157, 85, .12);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 4px;
      left: 0;
      bottom: 0;
      z-index: 150;
      transition: transform .3s ease;
      overflow-y: auto;
      overflow-x: hidden;
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 18px;
    }

    .hero-strip {
      background: linear-gradient(135deg, #ffffff 0%, #f0faf4 60%, #fef9e7 100%);
      border: 1px solid rgba(31, 157, 85, .15);
      border-radius: var(--radius-lg);
      padding: 24px 28px;
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
    }

    .hero-strip::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #1f9d55 33.3%, #d4a017 33.3% 66.6%, #1a3a6b 66.6%);
    }

    .card {
      background: #ffffff;
      border: 1px solid rgba(31, 157, 85, .1);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(31, 157, 85, .06);
    }

    .metric-card {
      background: #ffffff;
      border: 1px solid rgba(31, 157, 85, .1);
      border-radius: var(--radius);
      padding: 18px 20px;
      display: flex;
      align-items: center;
      gap: 14px;
      transition: all .2s;
      cursor: default;
      box-shadow: 0 1px 4px rgba(31, 157, 85, .06);
    }

    .metric-card:hover {
      border-color: var(--brand-mid);
      box-shadow: 0 4px 16px rgba(31, 157, 85, .12);
      transform: translateY(-1px);
    }

    .alert-bar.av {
      background: linear-gradient(135deg, #e8f5e9, #f0faf4);
      border: 1px solid #a5d6a7;
      color: #1b5e20;
    }

    .sidebar-plan {
      background: linear-gradient(135deg, #1f9d55 0%, #27a855 60%, #1a3a6b 100%);
      border-radius: var(--radius);
      padding: 14px;
      color: #fff;
      margin-bottom: 10px;
    }

    .modal-box,
    .hoja-modal-box {
      background: var(--surface);
      border-radius: var(--radius-lg);
      width: 100%;
      max-width: 520px;
      box-shadow: 0 24px 64px rgba(0, 0, 0, .18);
      position: relative;
      overflow: hidden;
    }

    .hoja-modal-box {
      max-width: 680px;
    }

    .modal-pad {
      padding: 28px 28px 24px;
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
      font-family: var(--font);
    }

    .mcerrar:hover {
      background: #fef2f2;
      color: var(--danger);
    }

    .mtit {
      font-size: 20px;
      font-weight: 800;
      color: var(--ink);
      margin-bottom: 4px;
    }

    .msub {
      font-size: 13px;
      color: var(--ink3);
      margin-bottom: 18px;
    }

    .msec {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--ink4);
      margin: 18px 0 10px;
    }

    .mmsg {
      display: none;
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 12px;
    }

    .mmsg.success {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .mmsg.error {
      background: #fce8e8;
      color: #b71c1c;
    }

    .mfila {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 12px;
    }

    .mgr {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .mgr.full {
      grid-column: 1 / -1;
    }

    .mgr label {
      font-size: 12px;
      font-weight: 700;
      color: var(--ink2);
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
      transition: border-color .18s;
    }

    .mgr input:focus,
    .mgr select:focus,
    .mgr textarea:focus,
    .minput:focus {
      outline: none;
      border-color: var(--brand);
      background: var(--surface);
    }

    .mlabel {
      font-size: 12px;
      font-weight: 700;
      color: var(--ink2);
      margin-bottom: 5px;
      display: block;
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
    }

    .btn-save:hover {
      background: #16692f;
    }

    .btn-save:disabled {
      opacity: .6;
      cursor: not-allowed;
    }

    /* ──── HOJA DE VIDA / VACANTE PROGRESS ──── */
    .hoja-progress-track {
      height: 5px;
      background: var(--surface3);
      border-radius: 5px;
      overflow: hidden;
      margin-bottom: 16px;
    }

    .hoja-progress-fill {
      height: 5px;
      background: linear-gradient(90deg, var(--brand), var(--brand2));
      border-radius: 5px;
      width: 0%;
      transition: width .5s;
    }

    .hoja-sec {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
      margin: 18px 0 12px;
    }

    .hoja-sec-num {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      background: var(--brand);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 800;
      flex-shrink: 0;
    }

    .hoja-divider {
      height: 1px;
      background: var(--border);
      margin: 16px 0;
    }

    .hoja-fila {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 12px;
    }

    .hoja-gr {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .hoja-gr.full {
      grid-column: 1 / -1;
    }

    .hoja-gr label {
      font-size: 12px;
      font-weight: 700;
      color: var(--ink2);
    }

    .hoja-gr input,
    .hoja-gr select,
    .hoja-gr textarea {
      padding: 9px 12px;
      border-radius: 10px;
      border: 1.5px solid var(--border2);
      font-size: 13px;
      font-family: var(--font);
      color: var(--ink);
      background: var(--surface2);
      width: 100%;
      box-sizing: border-box;
      transition: border-color .18s;
    }

    .hoja-gr input:focus,
    .hoja-gr select:focus,
    .hoja-gr textarea:focus {
      outline: none;
      border-color: var(--brand);
      background: var(--surface);
    }

    .hoja-btn-add {
      display: block;
      width: 100%;
      padding: 9px 16px;
      border-radius: 10px;
      border: 1.5px dashed var(--border2);
      background: none;
      color: var(--brand);
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      font-family: var(--font);
      transition: all .2s;
      margin-bottom: 4px;
    }

    .hoja-btn-add:hover {
      background: var(--brand-light);
      border-color: var(--brand);
    }

    .hoja-item-card {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 14px;
      margin-bottom: 10px;
      position: relative;
    }

    .hoja-item-rm {
      position: absolute;
      top: 10px;
      right: 10px;
      background: none;
      border: none;
      color: var(--ink4);
      cursor: pointer;
      font-size: 13px;
      font-family: var(--font);
    }

    .hoja-item-rm:hover {
      color: var(--danger);
    }

    /* ──── CROP MODAL ──────────────────────────── */
    .crop-modal {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .7);
      z-index: 2000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .crop-inner {
      background: var(--surface);
      border-radius: var(--radius-lg);
      padding: 24px;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 24px 64px rgba(0, 0, 0, .2);
    }

    /* ──── PERFIL SECCIONES ITEMS ──────────────── */
    .psec-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
    }

    .psec-item:last-child {
      border-bottom: none;
    }

    .psec-logo {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: var(--brand-light);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
      overflow: hidden;
    }

    .psec-logo img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .psec-body {
      flex: 1;
      min-width: 0;
    }

    .psec-nom {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink);
    }

    .psec-sub {
      font-size: 12px;
      color: var(--ink3);
      margin-top: 2px;
    }

    .psec-meta {
      font-size: 11px;
      color: var(--ink4);
      margin-top: 2px;
    }

    .psec-credencial {
      display: inline-block;
      margin-top: 4px;
      font-size: 11px;
      font-weight: 700;
      color: var(--brand);
    }

    .psec-archivo {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-top: 6px;
      padding: 6px 10px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      text-decoration: none;
    }

    .psec-arch-name {
      font-size: 12px;
      color: var(--ink2);
    }

    .psec-item-del {
      background: none;
      border: none;
      color: var(--ink4);
      cursor: pointer;
      font-size: 14px;
      flex-shrink: 0;
      padding: 4px;
      border-radius: 6px;
      font-family: var(--font);
    }

    .psec-item-del:hover {
      color: var(--danger);
      background: #fef2f2;
    }

    .psec-ver-mas {
      display: block;
      width: 100%;
      padding: 8px;
      background: none;
      border: none;
      color: var(--brand);
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      text-align: center;
      margin-top: 6px;
      font-family: var(--font);
    }

    /* ──── APTITUDES GRUPOS ─────────────────────── */
    .apt-grupo {
      margin-bottom: 12px;
    }

    .apt-nom {
      font-size: 11px;
      font-weight: 700;
      color: var(--ink4);
      text-transform: uppercase;
      letter-spacing: .8px;
      margin-bottom: 6px;
    }

    .apt-items {
      display: flex;
      flex-wrap: wrap;
      gap: 7px;
    }

    .apt-chip-ico {
      margin-right: 3px;
    }

    /* ──── NOTIF ITEMS ─────────────────────────── */
    .notif-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 12px 16px;
      border-bottom: 1px solid var(--border);
      font-size: 13px;
      color: var(--ink2);
    }

    .notif-item:last-child {
      border-bottom: none;
    }

    .notif-ico {
      font-size: 18px;
      flex-shrink: 0;
    }

    .notif-sub {
      font-size: 11px;
      color: var(--ink4);
      margin-top: 2px;
    }

    .notif-empty {
      padding: 20px;
      text-align: center;
      color: var(--ink4);
      font-size: 13px;
      line-height: 1.6;
    }

    /* ──── COMPACT EMPTY STATES (imagen 4) ────── */
    .psec-empty {
      text-align: center;
      padding: 14px 16px;
      color: var(--ink3);
      font-size: 13px;
    }

    .psec-empty-ico {
      font-size: 22px;
      margin-bottom: 4px;
    }

    .gallery-empty {
      text-align: center;
      padding: 16px 16px 14px;
      border: 1.5px dashed var(--border2);
      border-radius: 10px;
      color: var(--ink4);
      margin: 10px 16px;
    }

    .gallery-empty-ico {
      font-size: 22px;
      margin-bottom: 4px;
    }

    /* ──── CSS VARS COMPAT (modals use var(--v1) etc.) ─ */
    :root {
      --v1: #1a7a3c;
      --v2: #27a855;
      --v3: #4caf72;
      --a1: #f9a825;
      --a3: #ffd54f;
      --r2: #1e56d8;
      --r3: #3b82f6;
      --vlima: #27a855;
      --rcielo: #3b82f6;
      --acrem: #b97a00;
      --borde: rgba(27, 122, 60, .15);
      --brd: rgba(27, 122, 60, .15);
      --ink1: var(--ink);
      --ink2: #2d3f35;
      --bg2: var(--surface2);
      --PLAN_PRIORIDAD: 0;
    }

    @media (max-width: 600px) {

      .mfila,
      .hoja-fila {
        grid-template-columns: 1fr;
      }

      .modal-pad {
        padding: 20px 16px 18px;
      }
    }
  </style>
</head>

<body>

  <div class="barra-bandera"></div>
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- ──── SIDEBAR ──────────────────────────────────────────────── -->
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
        <?php if ($fotoUrl): ?><img src="<?= $fotoUrl ?>" alt="Foto"><?php else: ?><?= $inicial ?><?php endif; ?>
      </div>
      <div>
        <div class="su-name" id="sidebarNombre"><?= htmlspecialchars($usuario['nombre']) ?></div>
        <div class="su-role">
          <?php if ($tipo === 'empresa'): ?>🏢 Empresa
          <?php elseif ($tipo === 'negocio'): ?>🏪 Negocio
          <?php elseif ($subTipo === 'servicio'): ?>🎧 Servicios
          <?php else: ?>👤 Candidato<?php endif; ?>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section">
        <div class="nav-section-label">Principal</div>
        <a href="dashboard.php" class="nav-item active">
          <span class="ni-ico">🏠</span> Panel
        </a>
        <a href="chat.php" class="nav-item">
          <span class="ni-ico">💬</span> Mensajes
          <?php if ($chatNoLeidos > 0): ?><span class="ni-badge"><?= $chatNoLeidos ?></span><?php endif; ?>
        </a>
        <a href="buscar.php" class="nav-item">
          <span class="ni-ico">🔍</span> Buscar
        </a>
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
          <div class="sp-name"><?= htmlspecialchars($datosPlan['nombre'] ?? 'Semilla') ?></div>
          <a href="empresas.php#precios" class="sp-btn">✦ Mejorar plan</a>
        </div>
      <?php endif; ?>
      <a href="Php/logout.php" class="nav-salir">
        <span>🚪</span> Cerrar sesión
      </a>
    </div>
  </aside>

  <!-- ──── TOPBAR ──────────────────────────────────────────────── -->
  <div class="topbar">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Menú">
      <span></span><span></span><span></span>
    </button>
    <a href="index.html" class="topbar-logo" title="Ir al inicio">
      <img src="Imagenes/quibdo_desco_new.png" alt="QuibdóConecta">
    </a>
    <div class="topbar-title">
      <span class="choco-flag" title="Departamento del Chocó">
        <svg width="22" height="15" viewBox="0 0 22 15" xmlns="http://www.w3.org/2000/svg"
          style="border-radius:2px;vertical-align:middle;margin-right:6px;box-shadow:0 1px 3px rgba(0,0,0,.2)">
          <rect width="22" height="5" y="0" fill="#1f9d55" />
          <rect width="22" height="5" y="5" fill="#d4a017" />
          <rect width="22" height="5" y="10" fill="#1a3a6b" />
        </svg>
      </span>Mi <span>Panel</span>
    </div>
    <div class="topbar-actions">
      <?php if ($tipo === 'empresa' || $tipo === 'negocio'): ?>
        <button class="btn-primary" onclick="abrirPublicarVacante()" style="display:flex;align-items:center;gap:5px">
          <span>➕</span> Publicar vacante
        </button>
      <?php elseif ($tipo === 'candidato' || $subTipo === 'servicio'): ?>
        <button class="btn-primary" onclick="abrirHoja()" style="display:flex;align-items:center;gap:5px">
          <span>📄</span> Mi CV
        </button>
      <?php endif; ?>
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
    </div>
  </div>

  <!-- ──── MAIN ──────────────────────────────────────────────── -->
  <main class="main">

    <!-- ALERT -->
    <?php if (!$tieneVerificado): ?>
      <?php if ($estadoVerif === 'pendiente'): ?>
        <div class="alert-bar ap">
          <div class="a-ico">⏳</div>
          <div class="a-txt"><strong>Documentos en revisión</strong><span>El administrador está revisando tu
              documento.</span></div>
        </div>
      <?php elseif ($estadoVerif === 'rechazado'): ?>
        <div class="alert-bar ar">
          <div class="a-ico">❌</div>
          <div class="a-txt"><strong>Verificación
              rechazada</strong><span><?= $notaRechazo ?: 'Intenta subir el documento con mejor calidad.' ?></span></div><a
            href="verificar_cuenta.php" class="a-btn">Reintentar</a>
        </div>
      <?php else: ?>
        <div class="alert-bar as">
          <div class="a-ico">🪪</div>
          <div class="a-txt"><strong>Verifica tu identidad</strong><span>Sube tu documento y obtén el badge
              verificado.</span></div><a href="verificar_cuenta.php" class="a-btn">Verificar ahora</a>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert-bar av">
        <div class="a-ico">✅</div>
        <div class="a-txt"><strong>Cuenta verificada</strong><span>Los empleadores ven tu badge de verificación.</span>
        </div>
      </div>
    <?php endif; ?>

    <!-- HERO STRIP -->
    <div class="hero-strip">
      <div class="hero-av" id="heroAvatar" onclick="abrirModal()" title="Cambiar foto">
        <?php if ($fotoUrl): ?><img src="<?= $fotoUrl ?>" alt="Foto"><?php else: ?><?= $inicial ?><?php endif; ?>
      </div>
      <div class="hero-info">
        <div class="hero-chips">
          <span class="hchip hc-tipo"><?= $tc['label'] ?></span>
          <?php if ($tieneVerificado): ?><span class="hchip hc-v">✓ Verificado</span><?php endif; ?>
          <?php if ($tienePremium): ?><span class="hchip hc-p">⭐ Premium</span><?php endif; ?>
          <?php if ($tieneTop): ?><span class="hchip hc-top">👑 Top</span><?php endif; ?>
          <?php if ($tieneDestacado): ?><span class="hchip hc-dest">🏅 Destacado</span><?php endif; ?>
        </div>
        <div class="hero-name" id="dNombreHero">
          ¡Hola, <em><?= htmlspecialchars($usuario['nombre']) ?></em>!
        </div>
        <div class="hero-sub">
          <?php if ($tipo === 'empresa' && $nombreEmpresa): ?>
            <?= $nombreEmpresa ?>   <?php if ($sectorEmp): ?> · <?= $sectorEmp ?><?php endif; ?><?php if ($ciudad): ?> ·
              <?= $ciudad ?>   <?php endif; ?>
          <?php elseif ($tipo === 'negocio' && $nombreNegocio): ?>
            <?= $nombreNegocio ?>   <?php if ($catNeg): ?> · <?= $catNeg ?><?php endif; ?>
          <?php elseif (!empty($talento['profesion'])): ?>
            <?= htmlspecialchars($talento['profesion']) ?>   <?php if ($ciudad): ?> · <?= $ciudad ?><?php endif; ?>
          <?php else: ?>
            <?= $tipo === 'empresa' ? 'Conecta con el talento del Chocó.' : 'Completa tu perfil para conectar con oportunidades.' ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="hero-stats">
        <div class="hs">
          <div class="hs-val"><?= $pct ?>%</div>
          <div class="hs-lab">Perfil</div>
        </div>
        <div class="hs">
          <div class="hs-val"><?= $chatNoLeidos ?></div>
          <div class="hs-lab">Mensajes</div>
        </div>
        <?php if ($tipo === 'empresa'): ?>
          <div class="hs">
            <div class="hs-val"><?= $vacantesActivas ?></div>
            <div class="hs-lab">Vacantes</div>
          </div>
        <?php elseif ($subTipo === 'servicio'): ?>
          <div class="hs">
            <div class="hs-val"><?= $talento['calificacion'] ? number_format((float) $talento['calificacion'], 1) : '—' ?>
            </div>
            <div class="hs-lab">Calific.</div>
          </div>
        <?php else: ?>
          <div class="hs">
            <div class="hs-val">0</div>
            <div class="hs-lab">Postulac.</div>
          </div>
        <?php endif; ?>
      </div>
      <div class="hero-actions">
        <button class="btn-primary" onclick="abrirModal()">✏️ Editar perfil</button>
        <a href="<?= $tipo === 'empresa' ? 'empresas.php#u' . $usuario['id'] : ($tipo === 'negocio' ? 'negocios.php#u' . $usuario['id'] : ($subTipo === 'servicio' ? 'servicios.php' : 'talentos.php')) ?>"
          class="btn-secondary">🌐 Ver en directorio</a>
      </div>
    </div>

    <!-- DASHBOARD GRID -->
    <div class="dashboard-grid">

      <!-- ── MÉTRICAS ── -->
      <?php if ($tipo === 'empresa'): ?>
        <div class="col-4">
          <div class="metric-card" onclick="abrirPublicarVacante()" style="cursor:pointer">
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
      <?php elseif ($tipo === 'negocio'): ?>
        <div class="col-4">
          <div class="metric-card">
            <div class="mc-ico g">🏪</div>
            <div>
              <div class="mc-val"><?= $vistasTotal ?></div>
              <div class="mc-lab">Vistas al negocio</div>
              <div class="mc-sub" onclick="location.href='negocios.php'">
                <?= $vistas7dias > 0 ? '+' . $vistas7dias . ' esta semana' : 'Ver directorio →' ?>
              </div>
            </div>
          </div>
        </div>
        <div class="col-4">
          <div class="metric-card" onclick="location.href='chat.php'" style="cursor:pointer">
            <div class="mc-ico o">💬</div>
            <div>
              <div class="mc-val"><?= $chatNoLeidos ?></div>
              <div class="mc-lab">Mensajes</div>
              <div class="mc-sub">Ver chat →</div>
            </div>
          </div>
        </div>
        <div class="col-4">
          <div class="metric-card" onclick="abrirModal()" style="cursor:pointer">
            <div class="mc-ico m">⭐</div>
            <div>
              <div class="mc-val"><?= $pct ?>%</div>
              <div class="mc-lab">Perfil completado</div>
              <div class="mc-sub"><?= $pct < 100 ? 'Mejorar →' : '¡Perfecto! ✓' ?></div>
            </div>
          </div>
        </div>
      <?php elseif ($subTipo === 'servicio'): ?>
        <div class="col-4">
          <div class="metric-card">
            <div class="mc-ico g">🎧</div>
            <div>
              <div class="mc-val">
                <?= $talento['precio_desde'] ? '$' . number_format((float) $talento['precio_desde'], 0, ',', '.') : '—' ?>
              </div>
              <div class="mc-lab">Precio desde</div>
              <div class="mc-sub" onclick="location.href='servicios.php'">Ver servicios →</div>
            </div>
          </div>
        </div>
        <div class="col-4">
          <div class="metric-card">
            <div class="mc-ico a">⭐</div>
            <div>
              <div class="mc-val">
                <?= $talento['calificacion'] ? number_format((float) $talento['calificacion'], 1) : '0' ?>/5
              </div>
              <div class="mc-lab">Calificación</div>
              <div class="mc-sub">Reseñas →</div>
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
      <?php else: ?>
        <div class="col-4">
          <div class="metric-card">
            <div class="mc-ico g">📋</div>
            <div>
              <div class="mc-val">0</div>
              <div class="mc-lab">Postulaciones</div>
              <div class="mc-sub">Empieza hoy →</div>
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
        <div class="col-4">
          <div class="metric-card" onclick="abrirModal()" style="cursor:pointer">
            <div class="mc-ico m">⭐</div>
            <div>
              <div class="mc-val"><?= $pct ?>%</div>
              <div class="mc-lab">Perfil completado</div>
              <div class="mc-sub"><?= $pct < 100 ? 'Mejorar →' : '¡Perfecto! ✓' ?></div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ── PERFIL CARD ── -->
      <div class="col-4">
        <div class="card" style="height:100%">
          <div class="card-header">
            <div class="card-title">👤 Mi perfil</div>
            <button class="btn-secondary" onclick="abrirModal()" style="padding:5px 12px;font-size:12px">Editar</button>
          </div>
          <div class="profile-card">
            <div class="pc-av" id="cpAvatar" onclick="abrirModal()">
              <?php if ($fotoUrl): ?><img src="<?= $fotoUrl ?>" alt="Foto"><?php else: ?><?= $inicial ?><?php endif; ?>
            </div>
            <div class="pc-name" id="dNombre">
              <?= $tipo === 'empresa' ? $nombreEmpresa : ($tipo === 'negocio' ? $nombreNegocio : $nombreCompleto) ?>
            </div>
            <div class="pc-role" id="dProfesion">
              <?php if ($tipo === 'empresa'): ?>   <?= $sectorEmp ?: 'Sector no definido' ?>
              <?php elseif ($tipo === 'negocio'): ?>   <?= $catNeg ?: 'Categoría no definida' ?>
              <?php elseif ($subTipo === 'servicio'): ?>   <?= $profesionTipo ?: 'Servicio para eventos' ?>
              <?php else: ?>
                <?= !empty($talento['profesion']) ? htmlspecialchars($talento['profesion']) : ($profesionTipo ?: 'Sin profesión') ?>
              <?php endif; ?>
            </div>
            <div class="pc-rows">
              <div class="pc-row"><span class="pc-row-ico">📍</span><span
                  id="dCiudad"><?= $ciudad ?: 'Ciudad no registrada' ?></span></div>
              <div class="pc-row"><span class="pc-row-ico">📞</span><span
                  id="dTelefono"><?= $telefono ?: 'Teléfono no registrado' ?></span></div>
              <div class="pc-row"><span class="pc-row-ico">✉️</span><span><?= $correo ?></span></div>
              <?php if (!empty($badgesHTML)): ?>
                <div style="margin-top:6px"><?= $badgesHTML ?></div><?php endif; ?>
            </div>
            <div class="prog-wrap">
              <div class="prog-header"><span>Perfil completado</span><span id="pctLabel"><?= $pct ?>%</span></div>
              <div class="prog-track">
                <div class="prog-fill" id="progBar" style="width:0%"></div>
              </div>
            </div>

            <?php if ($tipo === 'candidato' || $subTipo === 'servicio'): ?>
              <?php $planPrioridad = PLAN_PRIORIDAD[$planActual] ?? 0;
              $tieneAccesoVisibilidad = $planPrioridad >= 2; ?>
              <div class="vis-row">
                <div>
                  <div class="vis-label">Visible en <?= $subTipo === 'servicio' ? 'Servicios' : 'Talentos' ?></div>
                  <?php if (!$tieneAccesoVisibilidad): ?>
                    <div class="vis-sub" style="color:#e65100">🔒 Desde el plan Verde Selva</div><?php else: ?>
                    <div class="vis-sub">Aparece en el directorio</div><?php endif; ?>
                </div>
                <?php if ($tieneAccesoVisibilidad): ?>
                  <label class="tog"><input type="checkbox" <?= ($talento['visible'] ?? 0) ? 'checked' : '' ?>
                      onchange="toggleVis(this.checked)"><span class="tog-sl"></span></label>
                <?php else: ?>
                  <a href="empresas.php#precios"
                    style="font-size:11px;font-weight:700;color:var(--brand);background:var(--brand-light);padding:5px 10px;border-radius:8px">✦
                    Mejorar</a>
                <?php endif; ?>
              </div>
            <?php elseif ($tipo === 'empresa'): ?>
              <div class="vis-row">
                <div>
                  <div class="vis-label">Visible en Empresas</div>
                  <div class="vis-sub">Directorio público</div>
                </div>
                <span
                  class="pv-chip <?= ($ep['visible_admin'] ?? 1) ? 'ok' : 'off' ?>"><?= ($ep['visible_admin'] ?? 1) ? '🟢 Visible' : '🟡 Oculto' ?></span>
              </div>
            <?php elseif ($tipo === 'negocio'): ?>
              <div class="vis-row">
                <div>
                  <div class="vis-label">Visible en Negocios</div>
                  <div class="vis-sub">Directorio público</div>
                </div>
                <span
                  class="pv-chip <?= ($np['visible_admin'] ?? 1) ? 'ok' : 'off' ?>"><?= ($np['visible_admin'] ?? 1) ? '🟢 Visible' : '🟡 Oculto' ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── ACCIONES RÁPIDAS ── -->
      <div class="col-8">
        <div class="card">
          <div class="card-header">
            <div class="card-title">⚡ Acciones rápidas</div>
          </div>
          <div class="actions-grid">
            <?php if ($tipo === 'empresa'): ?>
              <a href="#" class="action-item" onclick="abrirPublicarVacante();return false;"
                style="border-color:rgba(39,168,85,.3);background:rgba(39,168,85,.04)">
                <span class="ai-ico">➕</span><span class="ai-label" style="color:var(--brand)">Publicar vacante</span>
              </a>
              <a href="talentos.php" class="action-item"><span class="ai-ico">🌟</span><span
                  class="ai-label">Talentos</span></a>
              <a href="empresas.php" class="action-item"><span class="ai-ico">🏢</span><span class="ai-label">Mi
                  empresa</span></a>
            <?php elseif ($tipo === 'negocio'): ?>
              <a href="#" class="action-item" onclick="abrirPublicarVacante();return false;"
                style="border-color:rgba(39,168,85,.3);background:rgba(39,168,85,.04)">
                <span class="ai-ico">➕</span><span class="ai-label" style="color:var(--brand)">Publicar vacante</span>
              </a>
              <a href="negocios.php" class="action-item"><span class="ai-ico">🏪</span><span class="ai-label">Mi
                  negocio</span></a>
              <a href="Empleo.php" class="action-item"><span class="ai-ico">💼</span><span class="ai-label">Ver
                  empleos</span></a>
            <?php elseif ($subTipo === 'servicio' || !empty($talento['precio_desde'])): ?>
              <a href="servicios.php" class="action-item"><span class="ai-ico">🎧</span><span class="ai-label">Mis
                  servicios</span></a>
              <a href="#" class="action-item" onclick="abrirHoja();return false;"
                style="border-color:rgba(255,211,77,.25);background:rgba(255,211,77,.06)">
                <span class="ai-ico">📄</span><span class="ai-label" style="color:#b77d00">Mi Hoja de Vida</span>
              </a>
              <a href="Empleo.php" class="action-item"><span class="ai-ico">💼</span><span class="ai-label">Ver
                  empleos</span></a>
            <?php else: ?>
              <a href="Empleo.php" class="action-item"><span class="ai-ico">🔍</span><span class="ai-label">Buscar
                  empleo</span></a>
              <a href="#" class="action-item" onclick="abrirHoja();return false;"
                style="border-color:rgba(255,211,77,.25);background:rgba(255,211,77,.06)">
                <span class="ai-ico">📄</span><span class="ai-label" style="color:#b77d00">Mi Hoja de Vida</span>
              </a>
              <a href="talentos.php" class="action-item"><span class="ai-ico">🌟</span><span
                  class="ai-label">Talentos</span></a>
            <?php endif; ?>
            <a href="chat.php" class="action-item">
              <span class="ai-ico">💬</span><span class="ai-label">Mensajes</span>
              <?php if ($chatNoLeidos > 0): ?><span class="ai-badge"><?= $chatNoLeidos ?></span><?php endif; ?>
            </a>
            <a href="verificar_cuenta.php" class="action-item"><span class="ai-ico">🪪</span><span
                class="ai-label">Verificación</span><span
                class="ai-sub"><?= $tieneVerificado ? '✅ Activo' : 'Pendiente' ?></span></a>
            <a href="convocatorias.php" class="action-item"><span class="ai-ico">📢</span><span
                class="ai-label">Convocatorias</span></a>
            <a href="empresas.php" class="action-item"><span class="ai-ico">🏢</span><span
                class="ai-label">Empresas</span></a>
            <a href="negocios.php" class="action-item"><span class="ai-ico">🏪</span><span
                class="ai-label">Negocios</span></a>
            <a href="servicios.php" class="action-item"><span class="ai-ico">🎧</span><span
                class="ai-label">Eventos</span></a>
            <a href="Ayuda.html" class="action-item"><span class="ai-ico">❓</span><span
                class="ai-label">Ayuda</span></a>
          </div>
        </div>
      </div>

      <!-- ── QUIÉN ME VIO ── -->
      <?php if (!empty($visitantesRecientes) || $maxVisitantes === 0): ?>
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <div class="card-title">👁️ Quién visitó tu perfil</div>
            </div>
            <?php if ($maxVisitantes === 0): ?>
              <div style="text-align:center;padding:16px 20px">
                <div style="font-size:28px;margin-bottom:6px">🔒</div>
                <div style="font-size:13px;color:var(--ink3);margin-bottom:10px">Disponible desde el plan <strong
                    style="color:var(--accent)">Amarillo Oro</strong>.</div>
                <a href="empresas.php#precios" class="btn-primary" style="display:inline-flex">Ver planes →</a>
              </div>
            <?php else: ?>
              <div class="visitor-grid">
                <?php if (empty($visitantesRecientes)): ?>
                  <div style="color:var(--ink4);font-size:13px;padding:10px">Aún nadie ha visitado tu perfil.</div>
                <?php else: ?>
                  <?php foreach ($visitantesRecientes as $vis):
                    $vi = strtoupper(substr($vis['nombre'] ?? '?', 0, 1));
                    $cols = ['#43a047', '#fb8c00', '#1e88e5', '#e91e63', '#8e24aa'];
                    $col = $cols[abs(crc32($vis['visitante_id'] ?? 0)) % 5];
                    ?>
                    <div class="visitor-card">
                      <div class="vc-av" style="background:<?= $col ?>22;color:<?= $col ?>;border:2px solid <?= $col ?>">
                        <?= $vi ?>
                      </div>
                      <div>
                        <div class="vc-name">
                          <?= htmlspecialchars(trim(($vis['nombre'] ?? '') . ' ' . ($vis['apellido'] ?? ''))) ?>
                        </div>
                        <div class="vc-meta"><?= ucfirst($vis['tipo'] ?? '') ?> ·
                          <?= date('d M', strtotime($vis['creado_en'])) ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- ── PLAN ── -->
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
              <?php foreach (['mensajes' => ['💬', 'Mensajes'], 'aplicaciones' => ['📋', 'Aplicaciones'], 'vacantes' => ['💼', 'Vacantes']] as $key => [$ico, $label]):
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
                    <div class="pb-warn"><?= $pctBar >= 90 ? '⚠️ Límite alcanzado' : '⚡ Casi en el límite' ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ── VACANTES / EMPLEOS ── -->
      <div class="col-8">
        <div class="card">
          <div class="card-header">
            <div class="card-title">
              <?php if ($tipo === 'empresa'): ?>📋 Historial de vacantes
              <?php elseif ($subTipo === 'servicio'): ?>🎵 Géneros / especialidades
              <?php else: ?>💼 Empleos sugeridos<?php endif; ?>
            </div>
            <a href="#" class="card-link" <?= $tipo === 'empresa' ? 'onclick="abrirPublicarVacante();return false;"' : 'href="Empleo.php"' ?>>
              <?= $tipo === 'empresa' ? 'Publicar nueva →' : 'Ver todos →' ?>
            </a>
          </div>
          <?php if ($tipo === 'empresa' && !empty($historialVacantes)): ?>
            <div class="job-list">
              <?php foreach ($historialVacantes as $v):
                $horas = (time() - strtotime($v['creado_en'])) / 3600;
                $esPendiente = !$v['activo'] && $horas < 72;
                $dotCls = $v['activo'] ? 'act' : ($esPendiente ? 'pen' : 'cer');
                ?>
                <div class="job-item">
                  <div class="job-dot <?= $dotCls ?>"></div>
                  <div class="job-info">
                    <div class="job-name"><?= htmlspecialchars($v['titulo']) ?></div>
                    <div class="job-meta">📍
                      <?= htmlspecialchars($v['ciudad'] ?? 'Quibdó') ?>
                      <?= isset($v['modalidad']) ? ' · ' . ucfirst($v['modalidad']) : '' ?>
                    </div>
                  </div>
                  <div class="job-date"><?= date('d/m/Y', strtotime($v['creado_en'])) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php elseif ($tipo === 'empresa'): ?>
            <div class="job-empty">
              <div class="job-empty-ico">💼</div>
              <div class="job-empty-txt">Aún no has publicado vacantes</div><button class="btn-primary"
                onclick="abrirPublicarVacante()" style="margin-top:10px">➕ Publicar primera vacante</button>
            </div>
          <?php elseif ($subTipo === 'servicio' && !empty($talento['generos'])): ?>
            <div class="job-list">
              <?php foreach (array_slice(array_filter(array_map('trim', explode(',', $talento['generos']))), 0, 6) as $g): ?>
                <div class="job-item">
                  <div class="job-dot act"></div>
                  <div class="job-info">
                    <div class="job-name">🎵 <?= htmlspecialchars($g) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <?php if (!empty($vacantesDisponibles)): ?>
              <div class="job-list">
                <?php foreach ($vacantesDisponibles as $v): ?>
                  <div class="job-item">
                    <div class="job-dot act"></div>
                    <div class="job-info">
                      <div class="job-name"><?= htmlspecialchars($v['titulo']) ?></div>
                      <div class="job-meta">📍
                        <?= htmlspecialchars($v['ciudad'] ?? 'Quibdó') ?>
                        <?= !empty($v['salario_texto']) ? ' · ' . htmlspecialchars($v['salario_texto']) : '' ?>
                      </div>
                    </div>
                    <a href="Empleo.php" class="card-link" style="font-size:11px;flex-shrink:0">Ver →</a>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="job-empty">
                <div class="job-empty-ico">🔍</div>
                <div class="job-empty-txt">No hay empleos disponibles ahora</div><a href="Empleo.php" class="btn-primary"
                  style="display:inline-flex;margin-top:10px">Explorar empleos</a>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── ACTIVIDAD RECIENTE ── -->
      <div class="col-4">
        <div class="card">
          <div class="card-header">
            <div class="card-title">📌 Actividad reciente</div>
          </div>
          <div class="activity-list">
            <?php if (!empty($actReciente)): ?>
              <?php foreach (array_slice($actReciente, 0, 5) as $act): ?>
                <div class="act-item">
                  <div class="act-dot"></div>
                  <div class="act-txt"><?= htmlspecialchars($act['texto'] ?? $act['accion'] ?? 'Actividad') ?></div>
                  <div class="act-time"><?= isset($act['creado_en']) ? date('d/m', strtotime($act['creado_en'])) : '' ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="text-align:center;padding:20px 10px;color:var(--ink4);font-size:13px">
                <div style="font-size:24px;margin-bottom:6px">📋</div>
                Sin actividad reciente
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- /dashboard-grid -->

    <!-- ══ GALERÍA + PERFIL EXTENDIDO ══ -->
    <?php if ($tipo === 'candidato' || $subTipo === 'servicio'): ?>
      <?php
      $tieneSelvaVerde = tieneBadge($badgesUsuario, 'Selva Verde');
      $limiteGaleria = $tieneSelvaVerde ? PHP_INT_MAX : 15;
      $puedeSubir = $galeriaTotal < $limiteGaleria;
      ?>
      <div style="margin-top:18px">
        <div class="card">
          <div class="card-header">
            <div class="card-title">
              📸 Galería de evidencias
              <?php if (!$tieneSelvaVerde): ?>
                <span
                  style="font-size:11px;font-weight:500;color:<?= $galeriaTotal >= 15 ? 'var(--danger)' : 'var(--ink4)' ?>"><?= $galeriaTotal ?>/15
                  usados</span>
              <?php else: ?>
                <span
                  style="font-size:11px;background:#dcfce7;color:#166534;padding:2px 9px;border-radius:20px;font-weight:700">🌿
                  Ilimitado</span>
              <?php endif; ?>
            </div>
            <?php if ($puedeSubir): ?>
              <button onclick="abrirModalEvidencia()" class="btn-primary" style="padding:7px 14px;font-size:12px">➕
                Subir</button>
            <?php endif; ?>
          </div>
          <?php if (empty($galeriaItems)): ?>
            <div class="gallery-empty" style="padding:10px 16px;margin:8px 14px">
              <div class="gallery-empty-ico">📷</div>
              <div style="font-size:12px;font-weight:600;margin-bottom:2px">Aún no tienes evidencias subidas</div>
              <div style="font-size:11px">Sube fotos o videos de tu trabajo para atraer más clientes.</div>
            </div>
          <?php else: ?>
            <div class="gallery-grid" id="galeriaGrid">
              <?php foreach ($galeriaItems as $gi):
                $isVideo = $gi['tipo'] === 'video';
                $thumb = $isVideo && $gi['url_video'] ? 'https://img.youtube.com/vi/' . getYoutubeId($gi['url_video']) . '/mqdefault.jpg' : ($gi['archivo'] ? 'uploads/galeria/' . htmlspecialchars($gi['archivo']) : '');
                ?>
                <div class="gallery-item" id="gitem-<?= $gi['id'] ?>">
                  <?php if ($isVideo && $gi['url_video']): ?>
                    <a href="<?= htmlspecialchars($gi['url_video']) ?>" target="_blank"
                      style="display:block;height:100%;position:relative">
                      <?php if ($thumb): ?><img src="<?= $thumb ?>" loading="lazy"><?php endif; ?>
                      <div
                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3)">
                        <span style="font-size:28px">▶️</span>
                      </div>
                    </a>
                  <?php elseif ($gi['archivo']): ?>
                    <?php if ($isVideo): ?>
                      <video src="uploads/galeria/<?= htmlspecialchars($gi['archivo']) ?>"
                        style="width:100%;height:100%;object-fit:cover" controls preload="none"></video>
                    <?php else: ?>
                      <img src="uploads/galeria/<?= htmlspecialchars($gi['archivo']) ?>" loading="lazy"
                        onclick="verImagenGaleria('uploads/galeria/<?= htmlspecialchars($gi['archivo']) ?>','<?= htmlspecialchars($gi['titulo'] ?? '') ?>')">
                    <?php endif; ?>
                  <?php endif; ?>
                  <?php if ($gi['titulo']): ?>
                    <div class="gallery-caption"><?= htmlspecialchars($gi['titulo']) ?></div><?php endif; ?>
                  <button onclick="eliminarEvidencia(<?= $gi['id'] ?>,this)" class="gallery-item-del">🗑</button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($tipo === 'candidato' || $subTipo === 'servicio' || !empty($talento['precio_desde'])): ?>
      <div style="margin-top:16px">

        <!-- EDUCACIÓN -->
        <div class="psec" id="psec-educacion">
          <div class="psec-head">
            <div class="psec-title">🎓 Educación</div>
            <div class="psec-btns">
              <button class="psec-btn" title="Agregar" onclick="abrirFormEdu()">＋</button>
              <button class="psec-btn" title="Editar" onclick="abrirFormEdu()">✏️</button>
            </div>
          </div>
          <div class="psec-body" style="padding:8px 16px 10px">
            <div class="psec-list" id="edu-list">
              <div class="psec-empty" style="padding:8px 0 4px">
                <div class="psec-empty-ico">🎓</div>
                Agrega tu educación para que las empresas conozcan tu formación.
                <br><button onclick="abrirFormEdu()" class="btn-dashed"
                  style="margin-top:8px;padding:6px 16px;font-size:12px">+ Agregar educación</button>
              </div>
            </div>
          </div>
        </div>

        <!-- LICENCIAS Y CERTIFICACIONES -->
        <div class="psec" id="psec-cert">
          <div class="psec-head">
            <div class="psec-title">🏅 Licencias y certificaciones</div>
            <div class="psec-btns">
              <button class="psec-btn" title="Agregar" onclick="abrirFormCert()">＋</button>
              <button class="psec-btn" title="Editar" onclick="abrirFormCert()">✏️</button>
            </div>
          </div>
          <div class="psec-body" style="padding:8px 16px 10px">
            <div class="psec-list" id="cert-list">
              <div class="psec-empty" style="padding:8px 0 4px">
                <div class="psec-empty-ico">🏅</div>
                Agrega tus certificaciones y cursos para destacar tus habilidades.
                <br><button onclick="abrirFormCert()" class="btn-dashed"
                  style="margin-top:8px;padding:6px 16px;font-size:12px">+ Agregar certificación</button>
              </div>
            </div>
          </div>
        </div>

        <!-- APTITUDES -->
        <div class="psec" id="psec-apt">
          <div class="psec-head">
            <div class="psec-title">⚡ Aptitudes</div>
            <div class="psec-btns">
              <button class="psec-btn" title="Agregar" onclick="abrirFormApt()">＋</button>
              <button class="psec-btn" title="Editar" onclick="abrirFormApt()">✏️</button>
            </div>
          </div>
          <div class="psec-body">
            <div class="psec-list" id="apt-list">
              <?php $skills = trim($talento['skills'] ?? '');
              if ($skills): ?>
                <div class="apt-chips">
                  <?php foreach (array_filter(array_map('trim', explode(',', $skills))) as $sk): ?>
                    <span class="apt-chip"><span>🌿</span><?= htmlspecialchars($sk) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="psec-empty">
                  <div class="psec-empty-ico">⚡</div>
                  Agrega tus aptitudes y habilidades clave.
                  <br><button onclick="abrirFormApt()" class="btn-dashed">+ Agregar aptitudes</button>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    <?php endif; ?>

    <!-- JS: sidebar + notif toggle -->
    <script>
      function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
      }
      function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('open');
      }

      // Animate progress bar on load
      window.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
          const bar = document.getElementById('progBar');
          if (bar) bar.style.width = '<?= $pct ?>%';
        }, 300);
      });
    </script>

  </main>


  <!-- ══ MODAL PUBLICAR VACANTE (empresa y negocio) ══ -->
  <?php if ($tipo === 'empresa' || $tipo === 'negocio'): ?>
    <div class="modal-ov" id="modalPublicarVacante">
      <div class="hoja-modal-box" style="max-width:640px">
        <button class="mcerrar" onclick="cerrarPublicarVacante()">✕</button>
        <div class="modal-pad">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
            <div
              style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--v2),var(--v3));display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
              ➕</div>
            <div>
              <div class="mtit" style="margin:0;font-size:20px">Publicar <em
                  style="color:var(--v2);font-style:normal">Vacante</em></div>
              <p class="msub" style="margin:0;font-size:12px">Conecta con el mejor talento del Chocó en minutos</p>
            </div>
          </div>
          <div class="hoja-progress-track" style="margin-top:14px">
            <div class="hoja-progress-fill" id="vacProgressFill"></div>
          </div>
          <div class="mmsg" id="vacMsg"></div>
          <div class="hoja-sec">
            <div class="hoja-sec-num">1</div> Información de la vacante
          </div>
          <div class="hoja-fila" style="grid-template-columns:1fr">
            <div class="hoja-gr full"><label>Título del cargo <span style="color:#ff6b6b">*</span></label>
              <input type="text" id="vacTitulo" placeholder="Ej: Auxiliar Administrativo, Chef…" oninput="vacProgress()">
            </div>
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr"><label>Empresa <span style="color:#ff6b6b">*</span></label>
              <input type="text" id="vacEmpresa" placeholder="Nombre de tu empresa" oninput="vacProgress()"
                value="<?= htmlspecialchars($tipo === 'empresa' ? ($ep['nombre_empresa'] ?? $usuario['nombre'] ?? '') : ($np['nombre_negocio'] ?? $usuario['nombre'] ?? '')) ?>">
            </div>
            <div class="hoja-gr"><label>Ubicación <span style="color:#ff6b6b">*</span></label>
              <input type="text" id="vacUbicacion" placeholder="Ej: Quibdó, Chocó" oninput="vacProgress()"
                value="<?= htmlspecialchars($usuario['ciudad'] ?? 'Quibdó, Chocó') ?>">
            </div>
          </div>
          <div class="hoja-divider"></div>
          <div class="hoja-sec">
            <div class="hoja-sec-num">2</div> Condiciones laborales
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr"><label>Tipo de empleo <span style="color:#ff6b6b">*</span></label>
              <select id="vacTipo" onchange="vacProgress()">
                <option value="">Seleccione</option>
                <option>Tiempo completo</option>
                <option>Medio tiempo</option>
                <option>Remoto</option>
                <option>Freelance</option>
                <option>Prácticas</option>
              </select>
            </div>
            <div class="hoja-gr"><label>Salario</label><input type="text" id="vacSalario"
                placeholder="Ej: $2.000.000 / mes"></div>
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr"><label>Categoría</label>
              <select id="vacCategoria">
                <option value="">Seleccione</option>
                <option>Administrativo</option>
                <option>Tecnología</option>
                <option>Educación</option>
                <option>Salud</option>
                <option>Gastronomía</option>
                <option>Arte &amp; Música</option>
                <option>Transporte</option>
                <option>Técnico</option>
                <option>Otro</option>
              </select>
            </div>
            <div class="hoja-gr"><label>Fecha límite</label><input type="date" id="vacFecha"></div>
          </div>
          <div class="hoja-divider"></div>
          <div class="hoja-sec">
            <div class="hoja-sec-num">3</div> Descripción y requisitos
          </div>
          <div class="hoja-fila" style="grid-template-columns:1fr">
            <div class="hoja-gr full"><label>Descripción del cargo <span style="color:#ff6b6b">*</span></label>
              <textarea id="vacDesc" rows="3" placeholder="Funciones y responsabilidades del cargo…"
                oninput="vacProgress();vacCount(this,'vacDescC',500)"></textarea>
              <div style="text-align:right;font-size:11px;color:var(--ink3);margin-top:2px"><span
                  id="vacDescC">0</span>/500</div>
            </div>
          </div>
          <div class="hoja-fila" style="grid-template-columns:1fr">
            <div class="hoja-gr full"><label>Requisitos <span style="color:#ff6b6b">*</span></label>
              <textarea id="vacReq" rows="3" placeholder="Experiencia, estudios, habilidades requeridas…"
                oninput="vacProgress();vacCount(this,'vacReqC',500)"></textarea>
              <div style="text-align:right;font-size:11px;color:var(--ink3);margin-top:2px"><span
                  id="vacReqC">0</span>/500</div>
            </div>
          </div>
          <div style="display:flex;gap:10px;margin-top:20px">
            <button onclick="cerrarPublicarVacante()"
              style="flex:1;padding:13px;background:rgba(39,168,85,.06);border:1.5px solid var(--borde);color:var(--ink2);border-radius:14px;font-size:14px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer"
              onmouseover="this.style.background='rgba(39,168,85,.1)'"
              onmouseout="this.style.background='rgba(39,168,85,.06)'">Cancelar</button>
            <button id="btnGuardarVac" onclick="publicarVacante()"
              style="flex:2;padding:13px;background:linear-gradient(135deg,var(--v1),var(--v2) 50%,var(--v3));color:#fff;border:none;border-radius:14px;font-size:14px;font-weight:800;font-family:'DM Sans',sans-serif;cursor:pointer;box-shadow:0 6px 20px rgba(39,168,85,.4)">🚀
              Publicar vacante</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ══ MODAL HOJA DE VIDA (candidato y servicio) ══ -->
  <?php if ($tipo !== 'empresa' && $tipo !== 'negocio'): ?>
    <div class="modal-ov" id="modalHoja">
      <div class="hoja-modal-box">
        <button class="mcerrar" onclick="cerrarHoja()">✕</button>
        <div class="modal-pad">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
            <div
              style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--a1),var(--a3));display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">
              📄</div>
            <div>
              <div class="mtit" style="margin:0;font-size:20px">Mi Hoja de <em
                  style="color:var(--v2);font-style:normal">Vida</em></div>
              <p class="msub" style="margin:0;font-size:12px">Completa tu perfil y sé encontrado por empresas del Chocó
              </p>
            </div>
          </div>
          <div class="hoja-progress-track" style="margin-top:14px">
            <div class="hoja-progress-fill" id="hojaProgressFill"></div>
          </div>
          <div class="mmsg" id="hojaMsg"></div>
          <div class="hoja-sec">
            <div class="hoja-sec-num">1</div> Datos personales
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr"><label>Nombre completo <span style="color:#ff6b6b">*</span></label><input type="text"
                id="hNombre" oninput="hojaProgress()"
                value="<?= htmlspecialchars(trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''))) ?>">
            </div>
            <div class="hoja-gr"><label>Correo <span style="color:#ff6b6b">*</span></label><input type="email"
                id="hCorreo" oninput="hojaProgress()" value="<?= htmlspecialchars($usuario['correo'] ?? '') ?>"></div>
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr"><label>Teléfono <span style="color:#ff6b6b">*</span></label><input type="tel"
                id="hTelefono" placeholder="300 123 4567" oninput="hojaProgress()"
                value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>"></div>
            <div class="hoja-gr"><label>Ciudad</label><input type="text" id="hCiudad" placeholder="Quibdó, Chocó"
                value="<?= htmlspecialchars($usuario['ciudad'] ?? '') ?>"></div>
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr"><label>Profesión / Área</label><input type="text" id="hProfesion"
                placeholder="Ej: Contador, DJ, Diseñador…" value="<?= htmlspecialchars($talento['profesion'] ?? '') ?>">
            </div>
            <div class="hoja-gr"><label>Disponibilidad</label>
              <select id="hDisponibilidad">
                <option value="">Seleccione</option>
                <option>Inmediata</option>
                <option>En 15 días</option>
                <option>En 1 mes</option>
                <option>Solo fines de semana</option>
              </select>
            </div>
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr full"><label>Perfil profesional</label>
              <textarea id="hPerfil" rows="3" placeholder="Breve descripción de quién eres y qué buscas…"
                oninput="countHojaChars(this,'hPerfilCount',300)"><?= htmlspecialchars($talento['bio'] ?? '') ?></textarea>
              <div style="text-align:right;font-size:11px;color:var(--ink3)"><span
                  id="hPerfilCount"><?= mb_strlen($talento['bio'] ?? '') ?></span>/300</div>
            </div>
          </div>
          <div class="hoja-divider"></div>
          <div class="hoja-sec">
            <div class="hoja-sec-num">2</div> Experiencia laboral
          </div>
          <div id="listaExp"></div>
          <button class="hoja-btn-add" onclick="agregarExp()">+ Agregar experiencia laboral</button>
          <div class="hoja-divider"></div>
          <div class="hoja-sec">
            <div class="hoja-sec-num">3</div> Formación académica
          </div>
          <div id="listaForm"></div>
          <button class="hoja-btn-add" onclick="agregarForm()">+ Agregar formación académica</button>
          <div class="hoja-divider"></div>
          <div class="hoja-sec">
            <div class="hoja-sec-num">4</div> Habilidades e idiomas
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr"><label>Habilidades técnicas</label><input type="text" id="hHabTec"
                placeholder="Excel, Python, Photoshop…" value="<?= htmlspecialchars($talento['skills'] ?? '') ?>"></div>
            <div class="hoja-gr"><label>Habilidades blandas</label><input type="text" id="hHabBland"
                placeholder="Liderazgo, Trabajo en equipo…"></div>
          </div>
          <div class="hoja-fila">
            <div class="hoja-gr"><label>Idiomas</label><input type="text" id="hIdiomas"
                placeholder="Español (nativo), Inglés (básico)…"></div>
            <div class="hoja-gr"><label>Portafolio / LinkedIn</label><input type="url" id="hPortafolio"
                placeholder="https://miportafolio.com"></div>
          </div>
          <div class="hoja-divider"></div>
          <div class="hoja-sec">
            <div class="hoja-sec-num">5</div> Certificados y cursos
          </div>
          <div id="listaCert"></div>
          <button class="hoja-btn-add" onclick="agregarCert()">+ Agregar certificado o curso</button>
          <div style="display:flex;gap:10px;margin-top:24px">
            <button onclick="cerrarHoja()"
              style="flex:1;padding:13px;background:rgba(39,168,85,.06);border:1.5px solid var(--borde);color:var(--ink2);border-radius:14px;font-size:14px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer"
              onmouseover="this.style.background='rgba(39,168,85,.1)'"
              onmouseout="this.style.background='rgba(39,168,85,.06)'">Cancelar</button>
            <button id="btnGuardarHoja" onclick="guardarHoja()"
              style="flex:2;padding:13px;background:linear-gradient(135deg,var(--v1),var(--v2) 50%,var(--v3));color:#fff;border:none;border-radius:14px;font-size:14px;font-weight:800;font-family:'DM Sans',sans-serif;cursor:pointer;box-shadow:0 6px 20px rgba(39,168,85,.4)">💾
              Guardar hoja de vida</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ── MODAL EDITAR PERFIL ── -->
  <div class="modal-ov" id="modalEditar">
    <div class="modal-box">
      <button class="mcerrar" onclick="cerrarModal()">✕</button>
      <div class="modal-pad">
        <div class="mtit">✏️ Editar mi perfil</div>
        <p class="msub">Actualiza tu información personal y perfil profesional.</p>
        <div class="mmsg" id="editMsg"></div>

        <!-- Banner -->
        <div class="msec">Banner de perfil</div>
        <div style="margin-bottom:18px">
          <div id="bannerZone"
            style="position:relative;height:120px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);cursor:pointer;overflow:hidden;border-radius:14px;border:1.5px dashed rgba(39,168,85,.3)"
            onclick="document.getElementById('bannerInput').click()" title="Cambiar banner">
            <?php if ($bannerUrl): ?>
              <img id="bannerImg" src="<?= $bannerUrl ?>"
                style="width:100%;height:100%;object-fit:cover;display:block;border-radius:12px">
            <?php else: ?>
              <div id="bannerPlaceholder"
                style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;color:#81c784">
                <div style="font-size:28px">🖼️</div>
                <div style="font-size:12px;font-weight:600">Clic para subir banner</div>
                <div style="font-size:10px;opacity:.7">1200×300 px · JPG/PNG/WEBP · máx 5 MB</div>
              </div>
            <?php endif; ?>
            <div
              style="position:absolute;inset:0;background:rgba(0,0,0,0);transition:.2s;display:flex;align-items:center;justify-content:center;border-radius:12px"
              onmouseover="this.style.background='rgba(0,0,0,.3)';this.querySelector('span').style.opacity='1'"
              onmouseout="this.style.background='rgba(0,0,0,0)';this.querySelector('span').style.opacity='0'">
              <span
                style="opacity:0;color:#fff;font-size:12px;font-weight:700;background:rgba(0,0,0,.5);padding:6px 14px;border-radius:20px;transition:.2s">✏️
                Cambiar banner</span>
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;align-items:center">
            <input type="file" id="bannerInput" accept="image/jpeg,image/png,image/webp" style="display:none"
              onchange="subirBanner(this)">
            <button onclick="document.getElementById('bannerInput').click()" type="button"
              style="padding:7px 14px;border-radius:10px;background:rgba(39,168,85,.1);color:var(--v2);border:1.5px solid rgba(39,168,85,.25);font-size:12px;font-weight:700;cursor:pointer">🖼️
              Cambiar banner</button>
            <?php if ($bannerUrl): ?>
              <button id="btnEliminarBannerModal" onclick="eliminarBanner()" type="button"
                style="padding:7px 14px;border-radius:10px;background:transparent;color:#e74c3c;border:1.5px solid #e74c3c;font-size:12px;font-weight:700;cursor:pointer">🗑
                Quitar</button>
            <?php endif; ?>
          </div>
          <div id="bannerMsg" style="font-size:12px;color:#e53935;margin-top:6px;display:none"></div>
        </div>

        <!-- Foto -->
        <div class="msec">Foto de perfil</div>
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px">
          <div id="fotoPreview"
            style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,var(--v1),var(--v3));display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900;color:white;overflow:hidden;flex-shrink:0;cursor:pointer;border:2px solid rgba(163,240,181,.2)"
            onclick="document.getElementById('fotoInput').click()">
            <?php if ($fotoUrl): ?><img src="<?= $fotoUrl ?>" id="fotoImgPreview"
                style="width:100%;height:100%;object-fit:cover"><?php else: ?><span
                id="fotoInicialPreview"><?= $inicial ?></span><?php endif; ?>
          </div>
          <div>
            <input type="file" id="fotoInput" accept="image/jpeg,image/png,image/webp" style="display:none"
              onchange="abrirCrop(this)">
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <button onclick="document.getElementById('fotoInput').click()"
                style="padding:8px 14px;border-radius:10px;background:var(--v3);color:white;border:none;font-size:13px;font-weight:700;cursor:pointer">📷
                Cambiar foto</button>
              <button id="btnEliminarFoto" onclick="eliminarFoto()"
                style="padding:8px 14px;border-radius:10px;background:transparent;color:#e74c3c;border:1.5px solid #e74c3c;font-size:13px;font-weight:700;cursor:pointer;<?= $fotoUrl ? '' : 'display:none' ?>">🗑
                Eliminar</button>
            </div>
            <div style="font-size:11px;color:var(--ink3);margin-top:5px">JPG, PNG o WEBP · máx 2 MB</div>
            <div id="fotoMsg" style="font-size:12px;margin-top:4px"></div>
          </div>
        </div>

        <!-- Modal recorte -->
        <!-- ── MODAL CROP BANNER ── -->
        <div class="crop-modal" id="cropBannerModal" style="display:none">
          <div class="crop-inner" style="max-width:680px">
            <div style="font-size:16px;font-weight:800;color:#2e7d32;margin-bottom:14px;text-align:center">🖼️ Encuadra
              tu banner</div>
            <div
              style="position:relative;width:100%;height:220px;overflow:hidden;border-radius:12px;background:#000;display:flex;align-items:center;justify-content:center">
              <img id="cropBannerImg" style="max-width:100%;display:block">
            </div>
            <p style="font-size:12px;color:#78909c;text-align:center;margin:10px 0">Arrastra y haz zoom para encuadrar ·
              Proporción 4:1 (ideal para banners)</p>
            <div style="display:flex;gap:10px;margin-top:6px">
              <button onclick="cancelarCropBanner()"
                style="flex:1;padding:11px;border-radius:10px;border:1px solid #e0e0e0;background:#f5f5f5;font-size:13px;font-weight:700;cursor:pointer;color:#546e7a">Cancelar</button>
              <button onclick="confirmarCropBanner()" id="btnConfirmarCropBanner"
                style="flex:2;padding:11px;border-radius:10px;border:none;background:linear-gradient(135deg,#2e7d32,#43a047);color:#fff;font-size:13px;font-weight:800;cursor:pointer">✅
                Usar este banner</button>
            </div>
          </div>
        </div>

        <div class="crop-modal" id="cropModal" style="display:none">
          <div class="crop-inner">
            <div style="font-size:16px;font-weight:800;color:var(--v2);margin-bottom:14px;text-align:center">✂️
              Encuadra tu foto</div>
            <div
              style="position:relative;width:100%;height:280px;overflow:hidden;border-radius:12px;background:#000;display:flex;align-items:center;justify-content:center">
              <img id="cropImg" style="max-width:100%;display:block">
            </div>
            <p style="font-size:12px;color:var(--ink3);text-align:center;margin:10px 0">Arrastra y usa el zoom para
              encuadrar</p>
            <div style="display:flex;gap:10px;margin-top:6px">
              <button onclick="cancelarCrop()"
                style="flex:1;padding:11px;border-radius:10px;border:1px solid var(--borde);background:rgba(255,255,255,.05);font-size:13px;font-weight:700;cursor:pointer;color:var(--ink2);font-family:'DM Sans',sans-serif">Cancelar</button>
              <button onclick="confirmarCrop()" id="btnConfirmarCrop"
                style="flex:2;padding:11px;border-radius:10px;border:none;background:linear-gradient(135deg,var(--v1),var(--v3));color:white;font-size:13px;font-weight:800;cursor:pointer;font-family:'DM Sans',sans-serif">✅
                Usar esta foto</button>
            </div>
          </div>
        </div>

        <!-- Datos personales -->
        <div class="msec">Datos personales</div>
        <div class="mfila">
          <div class="mgr"><label>Nombre *</label><input type="text" id="editNombre"
              value="<?= htmlspecialchars($usuario['nombre']) ?>"></div>
          <div class="mgr"><label>Apellido</label><input type="text" id="editApellido"
              value="<?= htmlspecialchars($usuario['apellido'] ?? '') ?>"></div>
        </div>
        <div class="mfila">
          <div class="mgr"><label>Teléfono</label><input type="tel" id="editTelefono"
              value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>" placeholder="300 123 4567"></div>
          <div class="mgr"><label>Ciudad</label><input type="text" id="editCiudad"
              value="<?= htmlspecialchars($usuario['ciudad'] ?? '') ?>" placeholder="Quibdó"></div>
        </div>

        <!-- Info extra según tipo -->
        <?php if ($tipo === 'empresa'): ?>
          <div
            style="background:rgba(26,86,219,.08);border:1px solid rgba(26,86,219,.2);border-radius:12px;padding:12px 16px;font-size:12px;color:var(--r2);margin:8px 0">
            🏢 Los datos de empresa (razón social, NIT, sector, cámara de comercio) son actualizados por el administrador
            desde el panel de gestión.
          </div>
          <?php if (!empty($extras['razon_social'] ?? $ep['nombre_empresa'] ?? '')): ?>
            <div class="mfila">
              <div class="mgr full"><label>Empresa</label><input type="text"
                  value="<?= htmlspecialchars($extras['razon_social'] ?? $ep['nombre_empresa'] ?? $usuario['nombre'] ?? '') ?>"
                  readonly style="opacity:.5"></div>
            </div>
            <div class="mfila">
              <div class="mgr"><label>Sector</label><input type="text"
                  value="<?= htmlspecialchars($extras['sector'] ?? $ep['sector'] ?? '') ?>" readonly style="opacity:.5">
              </div>
              <div class="mgr"><label>NIT</label><input type="text"
                  value="<?= htmlspecialchars($extras['nit'] ?? $ep['nit'] ?? '') ?>" readonly style="opacity:.5"></div>
            </div>
          <?php endif; ?>
        <?php elseif ($tipo === 'negocio'): ?>
          <div
            style="background:rgba(245,200,0,.07);border:1px solid rgba(245,200,0,.2);border-radius:12px;padding:12px 16px;font-size:12px;color:#7a5e00;margin:8px 0">
            🏪 Los datos del negocio (nombre, categoría, WhatsApp) son actualizados desde el panel de gestión o por el
            administrador.
          </div>
        <?php elseif ($subTipo === 'servicio'): ?>
          <div
            style="background:rgba(245,200,0,.07);border:1px solid rgba(245,200,0,.2);border-radius:12px;padding:12px 16px;font-size:12px;color:#7a5e00;margin:8px 0">
            🎧 Tu precio, géneros y tipo de servicio son activados por el administrador. Para actualizar tu perfil de
            servicios contacta al equipo de QuibdóConecta.
          </div>
        <?php else: ?>
          <!-- Perfil profesional editable -->
          <div class="msec">Perfil profesional</div>
          <div class="mfila">
            <div class="mgr full"><label>Profesión / Área</label>
              <input type="text" id="editProfesion"
                value="<?= htmlspecialchars($talento['profesion'] ?? $profesionTipo ?? '') ?>"
                placeholder="Ej: Desarrollador Web, Contador, DJ…">
            </div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>Biografía / Descripción</label>
              <textarea id="editBio" rows="3" placeholder="Cuéntale a las empresas quién eres y qué ofreces…"
                style="resize:vertical;font-family:'DM Sans',sans-serif;font-size:14px;padding:10px 14px;border-radius:12px;background:rgba(255,255,255,.05);border:1px solid var(--borde);color:var(--ink1);width:100%;box-sizing:border-box"><?= htmlspecialchars($talento['bio'] ?? '') ?></textarea>
            </div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>Habilidades <span style="font-weight:400;opacity:.6">(separadas por
                  coma)</span></label>
              <input type="text" id="editSkills" value="<?= htmlspecialchars($talento['skills'] ?? '') ?>"
                placeholder="Ej: PHP, JavaScript, Diseño, Liderazgo…">
            </div>
          </div>
        <?php endif; ?>

        <button class="btn-save" id="btnGuardar" onclick="guardarPerfil()">💾 Guardar cambios</button>

        <!-- ── ZONA DE PELIGRO ── -->
        <div style="margin-top:32px;padding-top:24px;border-top:1px solid rgba(239,68,68,.2);">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <span style="font-size:15px">⚠️</span>
            <span style="font-size:13px;font-weight:700;color:#f87171;text-transform:uppercase;letter-spacing:.8px">Zona
              de peligro</span>
          </div>
          <p style="font-size:13px;color:var(--ink3);margin-bottom:14px;line-height:1.5;">
            Eliminar tu cuenta es <strong style="color:#f87171">permanente e irreversible</strong>. Se borrarán tu
            perfil, historial, mensajes y todos tus datos.
          </p>
          <button onclick="abrirEliminarCuenta()"
            style="padding:10px 20px;border-radius:10px;background:transparent;border:1.5px solid #e74c3c;color:#e74c3c;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;"
            onmouseover="this.style.background='rgba(231,76,60,.1)'" onmouseout="this.style.background='transparent'">
            🗑 Eliminar mi cuenta
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL ELIMINAR CUENTA -->
  <div id="modalEliminarCuenta"
    style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px;">
    <div
      style="background:#0f1a0f;border:1.5px solid rgba(239,68,68,.35);border-radius:20px;padding:36px 32px;max-width:440px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.5);">
      <div style="text-align:center;margin-bottom:20px;">
        <div style="font-size:48px;margin-bottom:12px;">⚠️</div>
        <h3 style="font-size:20px;font-weight:800;color:#f87171;margin-bottom:8px;">Eliminar cuenta permanentemente</h3>
        <p style="font-size:14px;color:var(--ink2);line-height:1.6;">Esta acción no se puede deshacer. Se eliminarán
          todos tus datos, perfil, mensajes e historial.</p>
      </div>
      <div style="margin-bottom:20px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--ink2);margin-bottom:8px;">Para confirmar,
          escribe tu correo: <strong style="color:#f87171"><?= htmlspecialchars($usuario['correo']) ?></strong></label>
        <input type="text" id="inputConfirmarCuenta" placeholder="Escribe tu correo exacto"
          style="width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid rgba(239,68,68,.4);background:rgba(239,68,68,.06);color:var(--ink);font-size:14px;outline:none;font-family:inherit;">
      </div>
      <div id="msgEliminarCuenta"
        style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
      <div style="display:flex;gap:10px;">
        <button onclick="cerrarEliminarCuenta()"
          style="flex:1;padding:12px;border-radius:10px;border:1.5px solid var(--borde);background:transparent;color:var(--ink2);font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">Cancelar</button>
        <button id="btnConfirmarEliminar" onclick="confirmarEliminarCuenta()"
          style="flex:1;padding:12px;border-radius:10px;border:none;background:#e74c3c;color:white;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;">🗑
          Sí, eliminar mi cuenta</button>
      </div>
    </div>
  </div>

  <script>

    function abrirModal() { document.getElementById('modalEditar').classList.add('open') }
    function cerrarModal() { document.getElementById('modalEditar').classList.remove('open') }
    document.getElementById('modalEditar').addEventListener('click', e => { if (e.target === document.getElementById('modalEditar')) cerrarModal() });

    function abrirEliminarCuenta() {
      document.getElementById('modalEliminarCuenta').style.display = 'flex';
      document.getElementById('inputConfirmarCuenta').value = '';
      document.getElementById('msgEliminarCuenta').style.display = 'none';
    }
    function cerrarEliminarCuenta() {
      document.getElementById('modalEliminarCuenta').style.display = 'none';
    }
    async function confirmarEliminarCuenta() {
      const correo = document.getElementById('inputConfirmarCuenta').value.trim();
      const msg = document.getElementById('msgEliminarCuenta');
      const btn = document.getElementById('btnConfirmarEliminar');
      if (!correo) { msg.textContent = 'Escribe tu correo para confirmar.'; msg.style.cssText = 'display:block;background:rgba(239,68,68,.15);color:#f87171;padding:10px 14px;border-radius:8px;font-size:13px;'; return; }
      btn.disabled = true; btn.textContent = '⏳ Eliminando...';
      const fd = new FormData();
      fd.append('_action', 'eliminar_cuenta');
      fd.append('confirmar', correo);
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          msg.textContent = '✅ Cuenta eliminada. Redirigiendo...';
          msg.style.cssText = 'display:block;background:rgba(31,157,85,.15);color:#a7f3d0;padding:10px 14px;border-radius:8px;font-size:13px;';
          setTimeout(() => { window.location.href = 'index.html'; }, 2000);
        } else {
          msg.textContent = '❌ ' + j.msg;
          msg.style.cssText = 'display:block;background:rgba(239,68,68,.15);color:#f87171;padding:10px 14px;border-radius:8px;font-size:13px;';
          btn.disabled = false; btn.textContent = '🗑 Sí, eliminar mi cuenta';
        }
      } catch (e) {
        msg.textContent = '❌ Error de conexión.';
        msg.style.cssText = 'display:block;background:rgba(239,68,68,.15);color:#f87171;padding:10px 14px;border-radius:8px;font-size:13px;';
        btn.disabled = false; btn.textContent = '🗑 Sí, eliminar mi cuenta';
      }
    }
    document.getElementById('modalEliminarCuenta').addEventListener('click', e => {
      if (e.target === document.getElementById('modalEliminarCuenta')) cerrarEliminarCuenta();
    });

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        cerrarModal();
        if (typeof cerrarHoja === 'function') cerrarHoja();
        if (typeof cerrarPublicarVacante === 'function') cerrarPublicarVacante();
      }
    });

    <?php if ($tipo === 'empresa' || $tipo === 'negocio'): ?>

      function abrirPublicarVacante() { document.getElementById('modalPublicarVacante').classList.add('open'); vacProgress(); }
      function cerrarPublicarVacante() { document.getElementById('modalPublicarVacante').classList.remove('open'); }
      document.getElementById('modalPublicarVacante').addEventListener('click', e => { if (e.target === document.getElementById('modalPublicarVacante')) cerrarPublicarVacante(); });
      function vacProgress() {
        const c = [document.getElementById('vacTitulo')?.value.trim(), document.getElementById('vacEmpresa')?.value.trim(), document.getElementById('vacUbicacion')?.value.trim(), document.getElementById('vacTipo')?.value, document.getElementById('vacDesc')?.value.trim(), document.getElementById('vacReq')?.value.trim()];
        document.getElementById('vacProgressFill').style.width = Math.round(c.filter(v => v && v !== '').length / c.length * 100) + '%';
      }
      function vacCount(el, id, max) { const n = el.value.length; document.getElementById(id).textContent = n; if (n > max) el.value = el.value.slice(0, max); }
      async function publicarVacante() {
        const titulo = document.getElementById('vacTitulo').value.trim(), empresa = document.getElementById('vacEmpresa').value.trim(), ubicacion = document.getElementById('vacUbicacion').value.trim(), tipo_ = document.getElementById('vacTipo').value, desc = document.getElementById('vacDesc').value.trim(), req = document.getElementById('vacReq').value.trim();
        const m = document.getElementById('vacMsg');
        if (!titulo || !empresa || !tipo_ || !desc || !req) { m.textContent = 'Completa los campos obligatorios (*).'; m.className = 'mmsg error'; m.style.display = 'block'; return; }
        const btn = document.getElementById('btnGuardarVac'); btn.disabled = true; btn.textContent = '⏳ Publicando...';
        const fd = new FormData();
        fd.append('titulo', titulo); fd.append('empresa', empresa); fd.append('ubicacion', ubicacion); fd.append('tipo', tipo_);
        fd.append('salario', document.getElementById('vacSalario').value); fd.append('categoria', document.getElementById('vacCategoria').value);
        fd.append('fecha', document.getElementById('vacFecha').value); fd.append('descripcion', desc); fd.append('requisitos', req);
        try {
          const r = await fetch('Php/publicar_empleo.php', { method: 'POST', body: fd }); const j = await r.json();
          if (j.ok) {
            m.textContent = '✅ ¡Vacante publicada!'; m.className = 'mmsg success'; m.style.display = 'block';
            ['vacTitulo', 'vacSalario', 'vacDesc', 'vacReq'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            document.getElementById('vacTipo').value = ''; document.getElementById('vacCategoria').value = ''; document.getElementById('vacFecha').value = '';
            document.getElementById('vacProgressFill').style.width = '0%';
            setTimeout(() => cerrarPublicarVacante(), 2000);
          } else { m.textContent = '❌ ' + (j.msg || 'Error al publicar.'); m.className = 'mmsg error'; m.style.display = 'block'; }
        } catch (e) { m.textContent = '❌ Error de conexión.'; m.className = 'mmsg error'; m.style.display = 'block'; }
        btn.disabled = false; btn.textContent = '🚀 Publicar vacante';
      }
    <?php endif; ?>

    <?php if ($tipo !== 'empresa' && $tipo !== 'negocio'): ?>

      function abrirHoja() { document.getElementById('modalHoja').classList.add('open'); hojaProgress(); }
      function cerrarHoja() { document.getElementById('modalHoja').classList.remove('open'); }
      document.getElementById('modalHoja').addEventListener('click', e => { if (e.target === document.getElementById('modalHoja')) cerrarHoja(); });
      function hojaProgress() {
        const c = [document.getElementById('hNombre')?.value.trim(), document.getElementById('hCorreo')?.value.trim(), document.getElementById('hTelefono')?.value.trim(), document.getElementById('hProfesion')?.value.trim()];
        document.getElementById('hojaProgressFill').style.width = Math.round(c.filter(v => v && v !== '').length / c.length * 100) + '%';
      }
      function countHojaChars(el, id, max) { const n = el.value.length; document.getElementById(id).textContent = n; if (n > max) el.value = el.value.slice(0, max); }
      let expIdx = 0, formIdx = 0, certIdx = 0;
      function agregarExp() { const i = expIdx++; const d = document.createElement('div'); d.className = 'hoja-item-card'; d.id = 'exp-' + i; d.innerHTML = `<button class="hoja-item-rm" onclick="document.getElementById('exp-${i}').remove()">✕</button><div class="hoja-fila"><div class="hoja-gr"><label>Cargo</label><input type="text" placeholder="Ej: Auxiliar contable"></div><div class="hoja-gr"><label>Empresa</label><input type="text" placeholder="Nombre empresa"></div></div><div class="hoja-fila"><div class="hoja-gr"><label>Desde</label><input type="month"></div><div class="hoja-gr"><label>Hasta</label><input type="month" placeholder="Actual si vacío"></div></div><div class="hoja-fila"><div class="hoja-gr full"><label>Funciones</label><textarea rows="2" placeholder="Describe tus responsabilidades…"></textarea></div></div>`; document.getElementById('listaExp').appendChild(d); }
      function agregarForm() { const i = formIdx++; const d = document.createElement('div'); d.className = 'hoja-item-card'; d.id = 'frm-' + i; d.innerHTML = `<button class="hoja-item-rm" onclick="document.getElementById('frm-${i}').remove()">✕</button><div class="hoja-fila"><div class="hoja-gr"><label>Título obtenido</label><input type="text" placeholder="Ej: Técnico en Sistemas"></div><div class="hoja-gr"><label>Institución</label><input type="text" placeholder="Nombre institución"></div></div><div class="hoja-fila"><div class="hoja-gr"><label>Año inicio</label><input type="number" min="1990" max="2030" placeholder="2020"></div><div class="hoja-gr"><label>Año grado</label><input type="number" min="1990" max="2030" placeholder="2024"></div></div>`; document.getElementById('listaForm').appendChild(d); }
      function agregarCert() { const i = certIdx++; const d = document.createElement('div'); d.className = 'hoja-item-card'; d.id = 'cert-' + i; d.innerHTML = `<button class="hoja-item-rm" onclick="document.getElementById('cert-${i}').remove()">✕</button><div class="hoja-fila"><div class="hoja-gr"><label>Certificado</label><input type="text" placeholder="Ej: Curso Excel Avanzado"></div><div class="hoja-gr"><label>Institución / Plataforma</label><input type="text" placeholder="Sena, Coursera…"></div></div><div class="hoja-fila"><div class="hoja-gr"><label>Año</label><input type="number" min="2000" max="2030" placeholder="2024"></div><div class="hoja-gr"><label>Link (opcional)</label><input type="text" placeholder="https://..."></div></div>`; document.getElementById('listaCert').appendChild(d); }
      async function guardarHoja() {
        const nombre = document.getElementById('hNombre').value.trim(), correo = document.getElementById('hCorreo').value.trim(), telefono = document.getElementById('hTelefono').value.trim();
        if (!nombre || !correo || !telefono) { const m = document.getElementById('hojaMsg'); m.textContent = 'Completa nombre, correo y teléfono.'; m.className = 'mmsg error'; m.style.display = 'block'; return; }
        const btn = document.getElementById('btnGuardarHoja'); btn.disabled = true; btn.textContent = '⏳ Guardando...';
        const fd = new FormData(); fd.append('_action', 'editar_perfil');
        fd.append('nombre', nombre.split(' ')[0] || nombre); fd.append('apellido', nombre.split(' ').slice(1).join(' ') || '');
        fd.append('telefono', telefono); fd.append('ciudad', document.getElementById('hCiudad').value.trim());
        fd.append('profesion', document.getElementById('hProfesion').value.trim()); fd.append('bio', document.getElementById('hPerfil').value.trim());
        fd.append('skills', document.getElementById('hHabTec').value.trim());
        try {
          const r = await fetch('dashboard.php', { method: 'POST', body: fd }); const j = await r.json();
          const m = document.getElementById('hojaMsg');
          if (j.ok) { m.textContent = '✅ Hoja de vida guardada.'; m.className = 'mmsg success'; m.style.display = 'block'; setTimeout(() => cerrarHoja(), 1800); }
          else { m.textContent = '❌ ' + (j.msg || 'Error al guardar.'); m.className = 'mmsg error'; m.style.display = 'block'; }
        } catch (e) { const m = document.getElementById('hojaMsg'); m.textContent = '❌ Error de conexión.'; m.className = 'mmsg error'; m.style.display = 'block'; }
        btn.disabled = false; btn.textContent = '💾 Guardar hoja de vida';
      }
    <?php endif; ?>

    window.addEventListener('load', () => {
      const b = document.getElementById('progBar');
      if (b) { const w = b.style.width; b.style.width = '0%'; setTimeout(() => { b.style.width = '<?= $pct ?>%' }, 400) }
    });

    function mostrarMsg(t, c) { const e = document.getElementById('editMsg'); e.textContent = t; e.className = 'mmsg ' + c; e.style.display = 'block' }

    async function eliminarFoto() {
      if (!confirm('¿Eliminar tu foto de perfil?')) return;
      const msg = document.getElementById('fotoMsg');
      msg.textContent = '⏳ Eliminando…'; msg.style.color = 'var(--ink3)';
      const fd = new FormData();
      fd.append('_action', 'eliminar_foto');
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          msg.textContent = '✅ Foto eliminada'; msg.style.color = 'var(--v3)';
          const inicial = document.querySelector('.hero-av')?.dataset?.inicial || '?';
          const inicialTag = `<span id="fotoInicialPreview">${inicial}</span>`;
          ['fotoPreview', 'heroAvatar'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = inicialTag;
          });
          document.getElementById('btnEliminarFoto').style.display = 'none';
          setTimeout(() => { msg.textContent = ''; }, 2000);
        } else {
          msg.textContent = '❌ Error al eliminar'; msg.style.color = '#e74c3c';
        }
      } catch (e) { msg.textContent = '❌ Error de conexión'; msg.style.color = '#e74c3c'; }
    }

    async function eliminarFotoBanner() {
      if (!confirm('¿Eliminar tu foto de perfil?')) return;
      const fd = new FormData();
      fd.append('_action', 'eliminar_foto');
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          const btnBanner = document.getElementById('btnEliminarFotoBanner');
          if (btnBanner) btnBanner.style.display = 'none';
          const av = document.getElementById('fotoCardAvatar');
          if (av) {
            av.innerHTML = `<?= $inicial ?>`;
          }
          location.reload();
        }
      } catch (e) { alert('Error de conexión'); }
    }

    let cropperInstance = null;
    function abrirCrop(input) {
      const file = input.files[0]; if (!file) return;
      input.value = '';
      const reader = new FileReader();
      reader.onload = e => {
        const img = document.getElementById('cropImg');
        if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
        img.src = e.target.result;
        document.getElementById('cropModal').style.display = 'flex';
        img.onload = () => {
          cropperInstance = new Cropper(img, { aspectRatio: 1, viewMode: 1, dragMode: 'move', autoCropArea: .85, cropBoxResizable: false, cropBoxMovable: false, toggleDragModeOnDblclick: false, background: false, responsive: true });
        };
      };
      reader.readAsDataURL(file);
    }
    function cancelarCrop() { document.getElementById('cropModal').style.display = 'none'; if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; } }
    async function confirmarCrop() {
      if (!cropperInstance) return;
      const btn = document.getElementById('btnConfirmarCrop');
      btn.textContent = '⏳ Guardando…'; btn.disabled = true;
      const msg = document.getElementById('fotoMsg');
      const canvas = cropperInstance.getCroppedCanvas({ width: 400, height: 400, imageSmoothingQuality: 'high' });
      canvas.toBlob(async blob => {
        const dataUrl = canvas.toDataURL('image/jpeg', .9);
        const imgTag = `<img src="${dataUrl}" style="width:100%;height:100%;object-fit:cover;border-radius:18px">`;
        document.getElementById('fotoPreview').innerHTML = imgTag;
        document.getElementById('cropModal').style.display = 'none';
        if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
        msg.textContent = '⏳ Subiendo…'; msg.style.color = 'var(--ink3)';
        const fd = new FormData(); fd.append('_action', 'subir_foto'); fd.append('foto', new File([blob], 'foto.jpg', { type: 'image/jpeg' }));
        try {
          const r = await fetch('dashboard.php', { method: 'POST', body: fd });
          const j = await r.json();
          if (j.ok) {
            msg.textContent = '✅ Foto actualizada'; msg.style.color = 'var(--vlima)';
            const finalImg = `<img src="${j.foto}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;border-radius:18px">`;
            ['heroAvatar', 'cpAvatar', 'navAvatar'].forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = finalImg; });
            document.getElementById('fotoPreview').innerHTML = finalImg;
            const btnEl = document.getElementById('btnEliminarFoto');
            if (btnEl) btnEl.style.display = 'inline-block';
          } else { msg.textContent = '❌ ' + (j.msg || 'Error'); msg.style.color = '#ff8080'; }
        } catch (e) { msg.textContent = '❌ Error de conexión'; msg.style.color = '#ff8080'; }
        btn.textContent = '✅ Usar esta foto'; btn.disabled = false;
      }, 'image/jpeg', .9);
    }

    async function guardarPerfil() {
      const btn = document.getElementById('btnGuardar');
      const n = document.getElementById('editNombre').value.trim();
      if (!n) { mostrarMsg('El nombre es obligatorio.', 'error'); return; }
      btn.disabled = true; btn.textContent = '⏳ Guardando…';
      const fd = new FormData();
      fd.append('_action', 'editar_perfil');
      fd.append('nombre', n);
      fd.append('apellido', document.getElementById('editApellido')?.value.trim() || '');
      fd.append('telefono', document.getElementById('editTelefono')?.value.trim() || '');
      fd.append('ciudad', document.getElementById('editCiudad')?.value.trim() || '');
      // Solo enviar campos pro si los inputs existen en el DOM (candidatos con perfil profesional editable)
      const _editPro = document.getElementById('editProfesion');
      const _editBio = document.getElementById('editBio');
      const _editSkills = document.getElementById('editSkills');
      if (_editPro && _editBio && _editSkills) {
        fd.append('_edita_pro', '1');
        fd.append('profesion', _editPro.value.trim());
        fd.append('bio', _editBio.value.trim());
        fd.append('skills', _editSkills.value.trim());
      } else {
        fd.append('_edita_pro', '0');
        fd.append('profesion', '');
        fd.append('bio', '');
        fd.append('skills', '');
      }
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          mostrarMsg('¡Perfil actualizado!', 'success');
          document.getElementById('dNombre').textContent = j.nombre + (j.apellido ? ' ' + j.apellido : '');
          const dc = document.getElementById('dCiudad'); if (dc) dc.textContent = j.ciudad || 'Ciudad no registrada';
          const dt = document.getElementById('dTelefono'); if (dt) dt.textContent = document.getElementById('editTelefono').value.trim() || 'Teléfono no registrado';

          if (j.profesion) { const dp = document.querySelector('.hero-pro'); if (dp) dp.textContent = j.profesion; }

          const fotoPreviewEl = document.getElementById('fotoPreview');
          if (fotoPreviewEl) {
            const imgEl = fotoPreviewEl.querySelector('img');
            if (imgEl) {
              const syncedImg = `<img src="${imgEl.src}" style="width:100%;height:100%;object-fit:cover;border-radius:18px">`;
              ['heroAvatar', 'cpAvatar', 'navAvatar'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.innerHTML = syncedImg;
              });
            }
          }
          setTimeout(cerrarModal, 1500);
        } else mostrarMsg(j.msg || 'Error al guardar.', 'error');
      } catch (e) { mostrarMsg('Error de conexión.', 'error'); }
      btn.disabled = false; btn.textContent = '💾 Guardar cambios';
    }

    async function toggleVis(visible) {
      const chip = document.getElementById('pvBadge');
      const toggle = document.querySelector('.tog input[type="checkbox"]');
      // Optimistic UI
      chip.textContent = visible ? '🟢 Visible' : '🟡 Oculto';
      chip.className = 'pv-chip ' + (visible ? 'ok' : 'off');
      const fd = new FormData();
      fd.append('_action', 'toggle_vis');
      fd.append('visible', visible ? '1' : '0');
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (!j.ok) {
          // Revertir si falla
          chip.textContent = visible ? '🟡 Oculto' : '🟢 Visible';
          chip.className = 'pv-chip ' + (visible ? 'off' : 'ok');
          if (toggle) toggle.checked = !visible;
          alert(j.msg || 'Error al cambiar visibilidad.');
        }
      } catch (e) {
        chip.textContent = visible ? '🟡 Oculto' : '🟢 Visible';
        chip.className = 'pv-chip ' + (visible ? 'off' : 'ok');
        if (toggle) toggle.checked = !visible;
      }
    }

    const notifBtn = document.getElementById('navNotif');
    const notifPanel = document.getElementById('notifPanel');
    const notifDot = document.getElementById('notifDot');
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = notifPanel.classList.contains('open');
      notifPanel.classList.toggle('open', !isOpen);
    });
    document.addEventListener('click', function (e) {
      if (!notifBtn.contains(e.target)) {
        notifPanel.classList.remove('open');
      }
    });

    function renderNotifs(data) {
      const n = data.notificaciones; let items = []; let urgente = false;
      if (n.mensajes_noLeidos > 0) { urgente = true; items.push(`<a href="chat.php" style="text-decoration:none;color:inherit"><div class="notif-item"><div class="notif-ico">💬</div><div><strong>${n.mensajes_noLeidos}</strong> mensaje${n.mensajes_noLeidos > 1 ? 's' : ''} sin leer<div class="notif-sub">Ir al chat</div></div></div></a>`); }
      if (n.verificacion_estado === 'pendiente') { urgente = true; items.push('<div class="notif-item"><div class="notif-ico">⏳</div><div>Verificación en revisión<div class="notif-sub">Te avisamos pronto</div></div></div>'); }
      else if (n.verificacion_estado === 'aprobado') { items.push('<div class="notif-item"><div class="notif-ico">✅</div><div>¡Cuenta verificada!<div class="notif-sub">Ya tienes el badge</div></div></div>'); }
      else if (n.verificacion_estado === 'rechazado') { urgente = true; items.push(`<div class="notif-item"><div class="notif-ico">❌</div><div>Verificación rechazada<div class="notif-sub">${n.verificacion_nota ? 'Motivo: ' + n.verificacion_nota : 'Revisa tus documentos'}</div></div></div>`); }
      if (n.total_badges > 0) { items.push(`<div class="notif-item"><div class="notif-ico">🏅</div><div>Tienes <strong>${n.total_badges}</strong> badge${n.total_badges > 1 ? 's' : ''}<div class="notif-sub">¡Sigue activo!</div></div></div>`); }
      document.getElementById('notifLista').innerHTML = items.length ? items.join('') : '<div class="notif-empty">Todo al día 🎉<br><small>No hay notificaciones nuevas</small></div>';
      notifDot.style.display = urgente ? 'block' : 'none';
    }
    async function cargarNotificaciones() {
      try { const r = await fetch('api_usuario.php?action=notificaciones'); const j = await r.json(); if (j.ok) renderNotifs(j); } catch (e) { }
    }
    cargarNotificaciones();
    setInterval(cargarNotificaciones, 30000);

    function abrirModalEvidencia() {
      document.getElementById('modal-evidencia').classList.add('open');
      document.getElementById('ev-msg').style.display = 'none';
      document.getElementById('form-evidencia').reset();
      toggleTipoMedia('archivo');
    }
    function cerrarModalEvidencia() {
      document.getElementById('modal-evidencia').classList.remove('open');
    }
    function toggleTipoMedia(tipo) {
      document.getElementById('ev-archivo-wrap').style.display = tipo === 'archivo' ? '' : 'none';
      document.getElementById('ev-url-wrap').style.display = tipo === 'url' ? '' : 'none';
      document.getElementById('ev-tipo-hidden').value = tipo === 'url' ? 'video_url' : 'archivo';
    }

    async function subirEvidencia() {
      const btn = document.getElementById('btn-ev-subir');
      const msg = document.getElementById('ev-msg');
      const fd = new FormData(document.getElementById('form-evidencia'));
      fd.append('_action', 'subir_evidencia');
      btn.disabled = true; btn.textContent = 'Subiendo…';
      msg.style.display = 'none';
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const d = await r.json();
        msg.style.display = 'block';
        if (d.ok) {
          msg.style.color = '#166534'; msg.style.background = '#dcfce7';
          msg.style.border = '1px solid #86efac'; msg.style.borderRadius = '8px';
          msg.style.padding = '10px 14px';
          msg.textContent = '✅ Subido correctamente.';
          setTimeout(() => { cerrarModalEvidencia(); location.reload(); }, 1200);
        } else {
          msg.style.color = '#dc2626'; msg.style.background = '#fef2f2';
          msg.style.border = '1px solid #fca5a5'; msg.style.borderRadius = '8px';
          msg.style.padding = '10px 14px';
          msg.textContent = '❌ ' + (d.msg || 'Error al subir');
          btn.disabled = false; btn.textContent = '⬆️ Subir';
        }
      } catch (e) {
        msg.textContent = '❌ Error de conexión'; msg.style.display = 'block';
        btn.disabled = false; btn.textContent = '⬆️ Subir';
      }
    }

    async function eliminarEvidencia(gid, btn) {
      if (!confirm('¿Eliminar este archivo de tu galería?')) return;
      btn.disabled = true;
      const fd = new FormData();
      fd.append('_action', 'eliminar_evidencia');
      fd.append('galeria_id', gid);
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          const el = document.getElementById('gitem-' + gid);
          if (el) el.remove();
        } else { alert(d.msg || 'Error'); btn.disabled = false; }
      } catch (e) { alert('Error de conexión'); btn.disabled = false; }
    }

    function verImagenGaleria(url, titulo) {
      let lbox = document.getElementById('lightbox-galeria');
      if (!lbox) {
        lbox = document.createElement('div');
        lbox.id = 'lightbox-galeria';
        lbox.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;align-items:center;justify-content:center;flex-direction:column;padding:20px;cursor:pointer';
        lbox.innerHTML = '<img id="lbox-img" style="max-width:90vw;max-height:85vh;border-radius:10px;object-fit:contain"><p id="lbox-titulo" style="color:rgba(255,255,255,.7);margin-top:12px;font-size:14px"></p>';
        lbox.addEventListener('click', () => { lbox.style.display = 'none'; });
        document.body.appendChild(lbox);
      }
      document.getElementById('lbox-img').src = url;
      document.getElementById('lbox-titulo').textContent = titulo || '';
      lbox.style.display = 'flex';
    }

    const STORE_KEY = 'qc_perfil_<?= $usuario["id"] ?>';
    let perfilData = { educacion: [], certificaciones: [], aptitudes_bland: '', aptitudes_idiomas: '' };
    try {
      const saved = localStorage.getItem(STORE_KEY);
      if (saved) perfilData = { ...perfilData, ...JSON.parse(saved) };
    } catch (e) { }

    function savePerfilData() {
      try { localStorage.setItem(STORE_KEY, JSON.stringify(perfilData)); } catch (e) { }
    }

    function renderEdu() {
      const list = document.getElementById('edu-list');
      if (!list) return;
      if (!perfilData.educacion.length) {
        list.innerHTML = `<div style="text-align:center;padding:14px 0;color:var(--ink3);font-size:13px">
        <div style="font-size:24px;margin-bottom:6px">🎓</div>
        Agrega tu educación para que las empresas conozcan tu formación.
        <br><button onclick="abrirFormEdu()" style="margin-top:10px;padding:6px 16px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:12px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif">+ Agregar educación</button>
      </div>`;
        return;
      }
      const mostrar = perfilData.educacion.slice(0, perfilData.edu_expandido ? 999 : 3);
      list.innerHTML = mostrar.map((e, i) => `
      <div class="psec-item">
        <div class="psec-logo">${e.logo ? `<img src="${e.logo}">` : '🎓'}</div>
        <div class="psec-body">
          <div class="psec-nom">${esc(e.inst)}</div>
          <div class="psec-sub">${esc(e.titulo)}</div>
          <div class="psec-meta">${esc(e.inicio || '')}${e.inicio && (e.fin || 'Actualidad') ? ' – ' : ''}${e.fin ? esc(e.fin) : (e.inicio ? 'Actualidad' : '')}</div>
        </div>
        <button class="psec-item-del" onclick="eliminarEdu(${i})" title="Eliminar">🗑</button>
      </div>`).join('');
      if (perfilData.educacion.length > 3) {
        const extra = perfilData.educacion.length - 3;
        const btn = document.createElement('button');
        btn.className = 'psec-ver-mas';
        btn.innerHTML = perfilData.edu_expandido ? '▲ Mostrar menos' : `Mostrar todo → (${extra} más)`;
        btn.onclick = () => { perfilData.edu_expandido = !perfilData.edu_expandido; savePerfilData(); renderEdu(); };
        list.appendChild(btn);
      }
    }

    function renderCert() {
      const list = document.getElementById('cert-list');
      if (!list) return;
      if (!perfilData.certificaciones.length) {
        list.innerHTML = `<div style="text-align:center;padding:14px 0;color:var(--ink3);font-size:13px">
        <div style="font-size:24px;margin-bottom:6px">🏅</div>
        Agrega tus certificaciones y cursos para destacar tus habilidades.
        <br><button onclick="abrirFormCert()" style="margin-top:10px;padding:6px 16px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:12px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif">+ Agregar certificación</button>
      </div>`;
        return;
      }
      const mostrar = perfilData.certificaciones.slice(0, perfilData.cert_expandido ? 999 : 3);
      list.innerHTML = mostrar.map((c, i) => `
      <div class="psec-item">
        <div class="psec-logo" style="background:rgba(163,240,181,.08);font-size:20px">🏅</div>
        <div class="psec-body">
          <div class="psec-nom">${esc(c.nom)}</div>
          <div class="psec-sub">${esc(c.org)}</div>
          ${c.fecha ? `<div class="psec-meta">Expedición: ${esc(formatMes(c.fecha))}</div>` : ''}
          ${c.url ? `<a href="${esc(c.url)}" target="_blank" class="psec-credencial">Mostrar credencial ↗</a>` : ''}
          ${c.archivo ? `<a href="${esc(c.archivo)}" target="_blank" class="psec-archivo">
            <div class="psec-arch-thumb">📄</div>
            <span class="psec-arch-name">${esc(c.archivoNom || 'Ver documento')}</span>
          </a>` : ''}
        </div>
        <button class="psec-item-del" onclick="eliminarCert(${i})" title="Eliminar">🗑</button>
      </div>`).join('');
      if (perfilData.certificaciones.length > 3) {
        const extra = perfilData.certificaciones.length - 3;
        const btn = document.createElement('button');
        btn.className = 'psec-ver-mas';
        btn.innerHTML = perfilData.cert_expandido ? '▲ Mostrar menos' : `Mostrar todo → (${extra} más)`;
        btn.onclick = () => { perfilData.cert_expandido = !perfilData.cert_expandido; savePerfilData(); renderCert(); };
        list.appendChild(btn);
      }
    }

    function renderApt() {
      const list = document.getElementById('apt-list');
      if (!list) return;
      const tecSkills = '<?= addslashes($talento["skills"] ?? "") ?>';
      const tec = tecSkills ? tecSkills.split(',').map(s => s.trim()).filter(Boolean) : [];
      const bland = perfilData.aptitudes_bland ? perfilData.aptitudes_bland.split(',').map(s => s.trim()).filter(Boolean) : [];
      const idiomas = perfilData.aptitudes_idiomas ? perfilData.aptitudes_idiomas.split(',').map(s => s.trim()).filter(Boolean) : [];
      if (!tec.length && !bland.length && !idiomas.length) {
        list.innerHTML = `<div style="text-align:center;padding:14px 0;color:var(--ink3);font-size:13px">
        <div style="font-size:24px;margin-bottom:6px">⚡</div>
        Agrega tus aptitudes y habilidades clave.
        <br><button onclick="abrirFormApt()" style="margin-top:10px;padding:6px 16px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:12px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif">+ Agregar aptitudes</button>
      </div>`;
        return;
      }
      let html = '';
      if (tec.length) html += `<div class="apt-grupo"><div class="apt-nom">Habilidades técnicas</div><div class="apt-items">${tec.map(s => `<span class="apt-chip"><span class="apt-chip-ico">🌿</span>${esc(s)}</span>`).join('')}</div></div>`;
      if (bland.length) html += `<div class="apt-grupo"><div class="apt-nom">Habilidades blandas</div><div class="apt-items">${bland.map(s => `<span class="apt-chip"><span class="apt-chip-ico">💡</span>${esc(s)}</span>`).join('')}</div></div>`;
      if (idiomas.length) html += `<div class="apt-grupo"><div class="apt-nom">Idiomas</div><div class="apt-items">${idiomas.map(s => `<span class="apt-chip"><span class="apt-chip-ico">🌐</span>${esc(s)}</span>`).join('')}</div></div>`;
      list.innerHTML = html;
    }

    function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
    function formatMes(m) { if (!m) return ''; const [y, mo] = m.split('-'); const meses = ['ene.', 'feb.', 'mar.', 'abr.', 'may.', 'jun.', 'jul.', 'ago.', 'sep.', 'oct.', 'nov.', 'dic.']; return (meses[parseInt(mo) - 1] || mo) + ' ' + y; }

    function abrirFormEdu() { document.getElementById('modal-edu').classList.add('open'); }
    function cerrarFormEdu() { document.getElementById('modal-edu').classList.remove('open'); }
    function abrirFormCert() { document.getElementById('modal-cert').classList.add('open'); }
    function cerrarFormCert() { document.getElementById('modal-cert').classList.remove('open'); }
    function abrirFormApt() {
      document.getElementById('apt-tec').value = '<?= addslashes($talento["skills"] ?? "") ?>';
      document.getElementById('apt-bland').value = perfilData.aptitudes_bland || '';
      document.getElementById('apt-idiomas').value = perfilData.aptitudes_idiomas || '';
      document.getElementById('modal-apt').classList.add('open');
    }
    function cerrarFormApt() { document.getElementById('modal-apt').classList.remove('open'); }

    async function syncEduServidor() {
      try {
        const fd = new FormData();
        fd.append('_action', 'guardar_educacion');
        fd.append('items', JSON.stringify(perfilData.educacion));
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (!j.ok) console.warn('syncEdu error:', j.msg);
      } catch (e) { console.warn('syncEdu red error:', e); }
    }

    async function syncCertServidor() {
      const certMsg = document.getElementById('cert-msg');
      try {
        const fd = new FormData();
        fd.append('_action', 'guardar_certificaciones');

        const itemsSync = perfilData.certificaciones.map(c => {
          const archEs64 = c.archivo && c.archivo.startsWith('data:');
          return { ...c, archivo: archEs64 ? '' : (c.archivo || ''), archivoNom: archEs64 ? c.archivoNom : (c.archivoNom || '') };
        });
        fd.append('items', JSON.stringify(itemsSync));
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          if (certMsg) { certMsg.textContent = '✅ Certificación guardada correctamente.'; certMsg.className = 'mmsg success'; certMsg.style.display = 'block'; setTimeout(() => { certMsg.style.display = 'none'; }, 2500); }
        } else {
          console.warn('syncCert error:', j.msg);
          if (certMsg) { certMsg.textContent = '⚠️ Guardado local OK, pero no se sincronizó al servidor: ' + (j.msg || 'error'); certMsg.className = 'mmsg error'; certMsg.style.display = 'block'; }
        }
      } catch (e) {
        console.warn('syncCert red error:', e);
        if (certMsg) { certMsg.textContent = '⚠️ Sin conexión — la certificación quedó guardada localmente.'; certMsg.className = 'mmsg error'; certMsg.style.display = 'block'; }
      }
    }

    function eliminarEdu(i) { perfilData.educacion.splice(i, 1); savePerfilData(); renderEdu(); syncEduServidor(); }
    function eliminarCert(i) { perfilData.certificaciones.splice(i, 1); savePerfilData(); renderCert(); syncCertServidor(); }

    function guardarEdu() {
      const inst = document.getElementById('edu-inst').value.trim();
      const titulo = document.getElementById('edu-titulo').value.trim();
      const inicio = document.getElementById('edu-inicio').value.trim();
      const fin = document.getElementById('edu-fin').value.trim();
      const msg = document.getElementById('edu-msg');
      if (!inst || !titulo) { msg.textContent = 'Institución y título son obligatorios.'; msg.className = 'mmsg error'; msg.style.display = 'block'; return; }
      const logoFile = document.getElementById('edu-logo').files[0];
      const guardar = (logoUrl) => {
        perfilData.educacion.push({ inst, titulo, inicio, fin, logo: logoUrl || '' });
        savePerfilData(); syncEduServidor(); renderEdu(); cerrarFormEdu();
        document.getElementById('edu-inst').value = ''; document.getElementById('edu-titulo').value = '';
        document.getElementById('edu-inicio').value = ''; document.getElementById('edu-fin').value = '';
        document.getElementById('edu-logo').value = '';
      };
      if (logoFile) {
        const reader = new FileReader();
        reader.onload = e => guardar(e.target.result);
        reader.readAsDataURL(logoFile);
      } else { guardar(''); }
    }

    function guardarCert() {
      const nom = document.getElementById('cert-nom').value.trim();
      const org = document.getElementById('cert-org').value.trim();
      const msg = document.getElementById('cert-msg');
      if (!nom || !org) { msg.textContent = 'Nombre y organización son obligatorios.'; msg.className = 'mmsg error'; msg.style.display = 'block'; return; }
      const fecha = document.getElementById('cert-fecha').value;
      const url = document.getElementById('cert-url').value.trim();
      const archFile = document.getElementById('cert-archivo').files[0];
      const guardar = (archivoUrl, archivoNom) => {
        perfilData.certificaciones.push({ nom, org, fecha, url, archivo: archivoUrl, archivoNom });
        savePerfilData(); syncCertServidor(); renderCert(); cerrarFormCert();
        document.getElementById('cert-nom').value = ''; document.getElementById('cert-org').value = '';
        document.getElementById('cert-fecha').value = ''; document.getElementById('cert-url').value = '';
        document.getElementById('cert-archivo').value = '';
      };
      if (archFile) {
        const reader = new FileReader();
        reader.onload = e => guardar(e.target.result, archFile.name);
        reader.readAsDataURL(archFile);
      } else { guardar('', ''); }
    }

    async function guardarApt() {
      const tec = document.getElementById('apt-tec').value.trim();
      const bland = document.getElementById('apt-bland').value.trim();
      const idiomas = document.getElementById('apt-idiomas').value.trim();
      const msg = document.getElementById('apt-msg');
      perfilData.aptitudes_bland = bland;
      perfilData.aptitudes_idiomas = idiomas;
      savePerfilData();

      try {
        const fdApt = new FormData();
        fdApt.append('_action', 'guardar_aptitudes_extra');
        fdApt.append('aptitudes_bland', bland);
        fdApt.append('aptitudes_idiomas', idiomas);
        fetch('dashboard.php', { method: 'POST', body: fdApt }).catch(() => { });
      } catch (e) { }

      // Usar guardar_aptitudes_extra para las habilidades técnicas también
      try {
        const fdSkills = new FormData();
        fdSkills.append('_action', 'guardar_aptitudes_extra');
        fdSkills.append('aptitudes_bland', bland);
        fdSkills.append('aptitudes_idiomas', idiomas);
        fdSkills.append('skills_tec', tec); // skills técnicas adicionales
        const r2 = await fetch('dashboard.php', { method: 'POST', body: fdSkills });
        const j2 = await r2.json();
        if (j2.ok) { msg.textContent = '✅ Aptitudes guardadas.'; msg.className = 'mmsg success'; msg.style.display = 'block'; setTimeout(cerrarFormApt, 1200); renderApt(); }
        else { msg.textContent = '❌ ' + (j2.msg || 'Error'); msg.className = 'mmsg error'; msg.style.display = 'block'; }
      } catch (e) { msg.textContent = '❌ Error de conexión'; msg.className = 'mmsg error'; msg.style.display = 'block'; }
    }

    ['modal-edu', 'modal-cert', 'modal-apt'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
    });

    renderEdu(); renderCert(); renderApt();

  </script>

  <!-- ── MODAL SUBIR EVIDENCIA ──────────────────────────────── -->
  <div class="modal-ov" id="modal-evidencia">
    <div class="modal-box" style="max-width:520px">
      <button class="mcerrar" onclick="cerrarModalEvidencia()">✕</button>
      <div class="modal-pad">
        <div class="mtit">📸 Subir evidencia de servicio</div>
        <p style="font-size:13px;color:#6b7280;margin-bottom:20px">Fotos de tus eventos, videos de presentaciones — lo
          que tus clientes necesitan ver para contratarte.</p>
        <form id="form-evidencia" enctype="multipart/form-data">
          <input type="hidden" id="ev-tipo-hidden" name="tipo_media" value="archivo">

          <!-- Selector foto / video archivo / video URL -->
          <div style="display:flex;gap:8px;margin-bottom:16px">
            <button type="button" onclick="toggleTipoMedia('archivo')"
              style="flex:1;padding:9px;border-radius:8px;border:2px solid #1f9d55;background:#dcfce7;color:#166534;font-size:13px;font-weight:700;cursor:pointer">
              📷 Foto / Video
            </button>
            <button type="button" onclick="toggleTipoMedia('url')"
              style="flex:1;padding:9px;border-radius:8px;border:2px solid #e5e7eb;background:#f9fafb;color:#374151;font-size:13px;font-weight:700;cursor:pointer">
              🎬 Link YouTube
            </button>
          </div>

          <div id="ev-archivo-wrap">
            <label class="mlabel">Archivo (foto JPG/PNG/WEBP hasta 5MB · video MP4/MOV hasta 50MB)</label>
            <input name="archivo" type="file" accept="image/*,video/mp4,video/quicktime,video/webm" class="minput"
              style="padding:8px;cursor:pointer">
          </div>
          <div id="ev-url-wrap" style="display:none">
            <label class="mlabel">URL de YouTube o Vimeo</label>
            <input name="url_video" type="url" class="minput" placeholder="https://youtube.com/watch?v=...">
          </div>

          <div style="margin-top:12px">
            <label class="mlabel">Título (opcional)</label>
            <input name="titulo" type="text" class="minput" placeholder="ej: Boda en Quibdó · Agosto 2025"
              maxlength="100">
          </div>
          <div style="margin-top:10px">
            <label class="mlabel">Descripción (opcional)</label>
            <textarea name="descripcion" class="minput" rows="2" style="resize:vertical"
              placeholder="Cuéntale al cliente qué muestra esta foto/video"></textarea>
          </div>

          <p id="ev-msg" style="display:none;font-size:13px;margin-top:12px;font-weight:600"></p>

          <button type="button" id="btn-ev-subir" onclick="subirEvidencia()" class="btn-save"
            style="width:100%;margin-top:16px">
            ⬆️ Subir
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ══ MODAL EDUCACIÓN ══ -->
  <?php if ($tipo === 'candidato' || $subTipo === 'servicio' || !empty($talento['precio_desde'])): ?>
    <div class="modal-ov" id="modal-edu">
      <div class="modal-box" style="max-width:520px">
        <button class="mcerrar" onclick="cerrarFormEdu()">✕</button>
        <div class="modal-pad">
          <div class="mtit">🎓 Agregar educación</div>
          <p class="msub">Agrega tu formación académica a tu perfil.</p>
          <div class="mmsg" id="edu-msg"></div>
          <div class="mfila">
            <div class="mgr full"><label>Institución *</label><input type="text" id="edu-inst"
                placeholder="Ej: Universidad Tecnológica del Chocó"></div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>Título / Carrera *</label><input type="text" id="edu-titulo"
                placeholder="Ej: Ingeniería de Sistemas, Bachillerato…"></div>
          </div>
          <div class="mfila">
            <div class="mgr"><label>Año inicio</label><input type="number" id="edu-inicio" min="1990" max="2030"
                placeholder="2020"></div>
            <div class="mgr"><label>Año fin</label><input type="number" id="edu-fin" min="1990" max="2030"
                placeholder="2024 (vacío = Actualidad)"></div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>Logo de la institución (opcional)</label>
              <input type="file" id="edu-logo" accept="image/*" style="padding:8px;cursor:pointer">
            </div>
          </div>
          <button class="btn-save" onclick="guardarEdu()">💾 Guardar educación</button>
        </div>
      </div>
    </div>

    <!-- ══ MODAL CERTIFICACIONES ══ -->
    <div class="modal-ov" id="modal-cert">
      <div class="modal-box" style="max-width:520px">
        <button class="mcerrar" onclick="cerrarFormCert()">✕</button>
        <div class="modal-pad">
          <div class="mtit">🏅 Agregar certificación</div>
          <p class="msub">Cursos, diplomas, licencias y certificados.</p>
          <div class="mmsg" id="cert-msg"></div>
          <div class="mfila">
            <div class="mgr full"><label>Nombre del certificado *</label><input type="text" id="cert-nom"
                placeholder="Ej: Técnico en Programación de Software"></div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>Institución / Organización *</label><input type="text" id="cert-org"
                placeholder="Ej: SENA, Coursera, Google…"></div>
          </div>
          <div class="mfila">
            <div class="mgr"><label>Fecha expedición</label><input type="month" id="cert-fecha"></div>
            <div class="mgr"><label>ID / Código credencial</label><input type="text" id="cert-id" placeholder="Opcional">
            </div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>URL de credencial</label><input type="url" id="cert-url"
                placeholder="https://... (opcional)"></div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>Adjuntar archivo (PDF o imagen)</label>
              <input type="file" id="cert-archivo" accept=".pdf,image/*" style="padding:8px;cursor:pointer">
            </div>
          </div>
          <button class="btn-save" onclick="guardarCert()">💾 Guardar certificación</button>
        </div>
      </div>
    </div>

    <!-- ══ MODAL APTITUDES ══ -->
    <div class="modal-ov" id="modal-apt">
      <div class="modal-box" style="max-width:480px">
        <button class="mcerrar" onclick="cerrarFormApt()">✕</button>
        <div class="modal-pad">
          <div class="mtit">⚡ Editar aptitudes</div>
          <p class="msub">Lista tus habilidades separadas por coma.</p>
          <div class="mmsg" id="apt-msg"></div>
          <div class="mfila">
            <div class="mgr full"><label>Habilidades técnicas (separadas por coma)</label>
              <input type="text" id="apt-tec" placeholder="PHP, JavaScript, Excel, Photoshop…"
                value="<?= htmlspecialchars($talento['skills'] ?? '') ?>">
            </div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>Habilidades blandas (separadas por coma)</label>
              <input type="text" id="apt-bland" placeholder="Liderazgo, Trabajo en equipo, Comunicación…">
            </div>
          </div>
          <div class="mfila">
            <div class="mgr full"><label>Idiomas</label>
              <input type="text" id="apt-idiomas" placeholder="Español (nativo), Inglés (básico)…">
            </div>
          </div>
          <button class="btn-save" onclick="guardarApt()">💾 Guardar aptitudes</button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ══ MODAL SOLICITAR VACANTE ══ -->
  <div class="modal-ov" id="modal-solicitud-vacante">
    <div class="modal-box" style="max-width:480px">
      <button class="mcerrar" onclick="cerrarModalSolicitud()">✕</button>
      <div class="modal-pad">
        <div class="mtit">🚀 Solicitar vacante</div>
        <p class="msub" id="sol-subtitulo">Envía tu solicitud a la empresa.</p>
        <div class="mmsg" id="sol-msg"></div>
        <div class="mgr full" style="margin-bottom:14px">
          <label style="font-size:13px;font-weight:700;color:var(--ink2);margin-bottom:6px;display:block">
            Mensaje opcional para la empresa
          </label>
          <textarea id="sol-mensaje" rows="4"
            placeholder="Ej: Estoy muy interesado en esta vacante porque tengo experiencia en… (opcional)"
            style="width:100%;border:1.5px solid var(--brd);border-radius:12px;padding:10px 14px;font-size:13px;font-family:'DM Sans',sans-serif;resize:vertical;color:var(--ink1);background:var(--bg2);box-sizing:border-box"></textarea>
        </div>
        <div id="sol-ya-aplicado" style="display:none;text-align:center;padding:18px 0">
          <div style="font-size:32px;margin-bottom:8px">✅</div>
          <div style="font-weight:700;color:var(--ink1);margin-bottom:4px">Ya enviaste tu solicitud</div>
          <div style="font-size:13px;color:var(--ink3)">La empresa revisará tu perfil y te contactará.</div>
        </div>
        <div id="sol-acciones">
          <button class="btn-save" onclick="enviarSolicitudVacante()" id="sol-btn-enviar">🚀 Enviar solicitud</button>
          <button onclick="cerrarModalSolicitud()"
            style="display:block;width:100%;margin-top:10px;padding:11px;background:none;border:1.5px solid var(--brd);border-radius:12px;font-size:14px;font-weight:700;color:var(--ink3);cursor:pointer;font-family:'DM Sans',sans-serif">
            Cancelar
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>

    let _solEmpId = 0;

    function abrirModalSolicitud(empleoId, titulo, empresa) {
      _solEmpId = empleoId;
      const msg = document.getElementById('sol-msg');
      const sub = document.getElementById('sol-subtitulo');
      const yaApl = document.getElementById('sol-ya-aplicado');
      const acciones = document.getElementById('sol-acciones');
      const txtArea = document.getElementById('sol-mensaje');
      if (sub) sub.textContent = '📋 ' + titulo + ' · ' + empresa;
      if (msg) { msg.style.display = 'none'; msg.textContent = ''; }
      if (yaApl) yaApl.style.display = 'none';
      if (acciones) acciones.style.display = '';
      if (txtArea) txtArea.value = '';
      document.getElementById('modal-solicitud-vacante').classList.add('open');
    }

    function cerrarModalSolicitud() {
      document.getElementById('modal-solicitud-vacante').classList.remove('open');
    }

    async function enviarSolicitudVacante() {
      const msg = document.getElementById('sol-msg');
      const btn = document.getElementById('sol-btn-enviar');
      const mensaje = document.getElementById('sol-mensaje').value.trim();
      if (!_solEmpId) return;
      btn.disabled = true;
      btn.textContent = 'Enviando…';
      msg.style.display = 'none';
      try {
        const fd = new FormData();
        fd.append('_action', 'solicitar_vacante');
        fd.append('empleo_id', _solEmpId);
        fd.append('mensaje', mensaje);
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          msg.textContent = j.msg || '✅ ¡Solicitud enviada correctamente!';
          msg.className = 'mmsg success';
          msg.style.display = 'block';
          document.getElementById('sol-acciones').style.display = 'none';
          document.getElementById('sol-ya-aplicado').style.display = 'block';
          setTimeout(cerrarModalSolicitud, 2200);
        } else if (j.ya_aplicado) {
          document.getElementById('sol-ya-aplicado').style.display = 'block';
          document.getElementById('sol-acciones').style.display = 'none';
        } else {
          msg.textContent = j.msg || '❌ Error al enviar solicitud.';
          msg.className = 'mmsg error';
          msg.style.display = 'block';
          btn.disabled = false;
          btn.textContent = '🚀 Enviar solicitud';
        }
      } catch (e) {
        msg.textContent = '❌ Error de conexión. Intenta de nuevo.';
        msg.className = 'mmsg error';
        msg.style.display = 'block';
        btn.disabled = false;
        btn.textContent = '🚀 Enviar solicitud';
      }
    }

    document.getElementById('modal-solicitud-vacante').addEventListener('click', function (e) {
      if (e.target === this) cerrarModalSolicitud();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') cerrarModalSolicitud();
    });

    let cropperBannerInstance = null;

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
          const r = await fetch('dashboard.php', { method: 'POST', body: fd });
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
              qbtn.onclick = (e) => { e.stopPropagation(); eliminarBanner(); };
              zone.appendChild(qbtn);
            }
          }
        } catch (e) {
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
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          const img = document.getElementById('bannerImg');
          if (img) img.remove();
          const zone = document.getElementById('bannerZone');

          let ph = zone.querySelector('#bannerPlaceholder');
          if (!ph) {
            ph = document.createElement('div');
            ph.id = 'bannerPlaceholder';
            ph.style.cssText = 'width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:#81c784';
            ph.innerHTML = '<div style="font-size:36px">🖼️</div><div style="font-size:13px;font-weight:600">Haz clic para subir tu banner</div><div style="font-size:11px;opacity:.7">Recomendado: 1200 × 300 px · JPG, PNG, WEBP · máx 5 MB</div>';
            zone.insertBefore(ph, zone.firstChild);
          } else {
            ph.style.display = 'flex';
          }

          zone.querySelectorAll('.btn-quitar-banner, button').forEach(b => b.remove());
        }
      } catch (e) { }
    }
  </script>

  <!-- Widget de sesión activa — QuibdóConecta -->
  <script src="js/sesion_widget.js"></script>
</body>

</html>