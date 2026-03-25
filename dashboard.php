<?php
// ============================================================
// dashboard.php — Panel unificado QuibdóConecta v3
// 4 tipos: candidato | empresa | negocio (cc/emp) | servicio
// ============================================================
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

// ── ACCIONES POST ──────────────────────────────────────────
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
    // Actualizar talento_perfil si se enviaron campos de perfil profesional
    if ($profesion !== '' || $bio !== '' || $skills !== '') {
      $tpChk = $db->prepare("SELECT id FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
      $tpChk->execute([$usuario['id']]);
      $tpRow = $tpChk->fetch();
      if ($tpRow) {
        $db->prepare("UPDATE talento_perfil SET profesion=?, bio=?, skills=? WHERE id=?")
          ->execute([$profesion, $bio, $skills, $tpRow['id']]);
      } else {
        $db->prepare("INSERT INTO talento_perfil (usuario_id, profesion, bio, skills, visible, visible_admin) VALUES (?,?,?,?,0,1)")
          ->execute([$usuario['id'], $profesion, $bio, $skills]);
      }
    }
    echo json_encode(['ok' => true, 'nombre' => $nombre, 'apellido' => $apellido, 'ciudad' => $ciudad, 'profesion' => $profesion]);
    exit;
  }
  /* 
      if ($action === 'subir_foto') {
          if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== 0) {
              echo json_encode(['ok'=>false,'msg'=>'No se recibió imagen.']); exit;
          }
          $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
          if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
              echo json_encode(['ok'=>false,'msg'=>'Solo JPG, PNG o WEBP.']); exit;
          }
          if ($_FILES['foto']['size'] > 2*1024*1024) {
              echo json_encode(['ok'=>false,'msg'=>'Máximo 2 MB.']); exit;
          }
          $dir = __DIR__ . '/uploads/fotos/';
          if (!is_dir($dir)) mkdir($dir, 0755, true);
          $oldFoto = $usuario['foto'] ?? '';
          if ($oldFoto && file_exists($dir.$oldFoto)) @unlink($dir.$oldFoto);
          $fn = 'u'.$usuario['id'].'_'.time().'.'.$ext;
          if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir.$fn)) {
              $db->prepare("UPDATE usuarios SET foto=? WHERE id=?")->execute([$fn, $usuario['id']]);
              echo json_encode(['ok'=>true,'foto'=>'uploads/fotos/'.$fn]);
          } else {
              echo json_encode(['ok'=>false,'msg'=>'Error al guardar imagen.']);
          }
          exit;
      } */

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

  // ── ELIMINAR FOTO DE PERFIL ───────────────────────────────
  if ($action === 'eliminar_foto') {
    $db->prepare("UPDATE usuarios SET foto='' WHERE id=?")->execute([$usuario['id']]);
    echo json_encode(['ok' => true]);
    exit;
  }

  // ── ELIMINAR CUENTA ───────────────────────────────────────
  if ($action === 'eliminar_cuenta') {
    $confirmar = trim($_POST['confirmar'] ?? '');
    if ($confirmar !== $usuario['correo']) {
      echo json_encode(['ok' => false, 'msg' => 'El correo no coincide. Escríbelo exactamente.']);
      exit;
    }
    try {
      // Borrar tablas sin CASCADE
      foreach (['perfiles_empresa', 'talento_galeria', 'talento_educacion', 'talento_certificaciones', 'talento_experiencia', 'perfil_vistas', 'sesiones', 'negocios_locales'] as $tabla) {
        try {
          $db->prepare("DELETE FROM $tabla WHERE usuario_id=?")->execute([$usuario['id']]);
        } catch (Exception $e) {
        }
      }
      // Borrar foto de disco
      if (!empty($usuario['foto']) && !str_starts_with($usuario['foto'], 'http')) {
        @unlink(__DIR__ . '/uploads/fotos/' . $usuario['foto']);
      }
      // Borrar usuario (CASCADE limpia empleos, mensajes, verificaciones, talento_perfil, admin_roles)
      $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$usuario['id']]);
      // Destruir sesión
      $_SESSION = [];
      session_destroy();
      echo json_encode(['ok' => true, 'msg' => 'Cuenta eliminada correctamente.']);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => 'Error al eliminar la cuenta: ' . $e->getMessage()]);
    }
    exit;
  }

  if ($action === 'subir_evidencia') {
    // Verificar límite según badge Selva Verde
    require_once __DIR__ . '/Php/badges_helper.php';
    $badgesU = getBadgesUsuario($db, $usuario['id']);
    $tieneSelvaVerde = tieneBadge($badgesU, 'Selva Verde');

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

    // Contar archivos actuales
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

    // Opción A: URL de video (YouTube/Vimeo)
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

    // Opción B: archivo subido (foto o video)
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
    $maxSize = $esVideo ? 50 * 1024 * 1024 : 5 * 1024 * 1024; // 50MB video, 5MB foto
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

  // ── ELIMINAR EVIDENCIA ────────────────────────────────────
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
        // Evitar spam: max 1 vista por IP por usuario por hora
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


  // ── GUARDAR EDUCACIÓN ─────────────────────────────────────
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

  // ── GUARDAR CERTIFICACIONES ──────────────────────────────
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

  // ── GUARDAR APTITUDES EXTRA ───────────────────────────────
  if ($action === 'guardar_aptitudes_extra') {
    try {
      $bland = substr(trim($_POST['aptitudes_bland'] ?? ''), 0, 500);
      $idiomas = substr(trim($_POST['aptitudes_idiomas'] ?? ''), 0, 300);
      try {
        $db->exec("ALTER TABLE talento_perfil ADD COLUMN IF NOT EXISTS aptitudes_bland VARCHAR(500) DEFAULT ''");
        $db->exec("ALTER TABLE talento_perfil ADD COLUMN IF NOT EXISTS aptitudes_idiomas VARCHAR(300) DEFAULT ''");
      } catch (Exception $e2) {
      }
      $chk = $db->prepare("SELECT id FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
      $chk->execute([$usuario['id']]);
      $row = $chk->fetch();
      if ($row) {
        $db->prepare("UPDATE talento_perfil SET aptitudes_bland=?, aptitudes_idiomas=? WHERE id=?")->execute([$bland, $idiomas, $row['id']]);
      } else {
        $db->prepare("INSERT INTO talento_perfil (usuario_id,aptitudes_bland,aptitudes_idiomas,visible,visible_admin) VALUES (?,?,?,0,1)")->execute([$usuario['id'], $bland, $idiomas]);
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

// ── CARGAR DATOS SEGÚN TIPO ───────────────────────────────
$tipo = $usuario['tipo'] ?? 'candidato';

// Talento/servicio (candidatos y artistas)
$tp = $db->prepare("SELECT * FROM talento_perfil WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
$tp->execute([$usuario['id']]);
$talento = $tp->fetch() ?: ['profesion' => '', 'bio' => '', 'skills' => '', 'visible' => 0, 'visible_admin' => 1, 'generos' => '', 'precio_desde' => null, 'tipo_servicio' => '', 'calificacion' => 0];

function getYoutubeId($url)
{
  preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m);
  return $m[1] ?? '';
}

// Galería de evidencias
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

// Perfil empresa
$ep = null;
if ($tipo === 'empresa') {
  $epStmt = $db->prepare("SELECT * FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
  $epStmt->execute([$usuario['id']]);
  $ep = $epStmt->fetch() ?: [];
}

// Perfil negocio
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

// Extras del registro (nota_admin = JSON)
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

// Detectar subtipo desde extras
$subTipo = ''; // dj, fotografo, negocio_cc, negocio_emp, etc.
if (!empty($extras['tipo_negocio_reg']))
  $subTipo = $extras['tipo_negocio_reg'] === 'cc' ? 'negocio_cc' : 'negocio_emp';
if (!empty($extras['profesion_tipo']) && $tipo === 'candidato') {
  $pt = strtolower($extras['profesion_tipo']);
  if (preg_match('/(dj|disc jockey|chirimía|chirimia|música|musica|cantante|fotograf|video|catering|decorac|animador|maestro.*ceremonia)/i', $pt))
    $subTipo = 'servicio';
}

require_once __DIR__ . '/Php/badges_helper.php';
$badgesUsuario = getBadgesUsuario($db, $usuario['id']);
$badgesHTML = renderBadges($badgesUsuario);
$tieneVerificado = (bool) ($usuario['verificado'] ?? false) || tieneBadge($badgesUsuario, 'Verificado') || tieneBadge($badgesUsuario, 'Usuario Verificado') || tieneBadge($badgesUsuario, 'Empresa Verificada');
$tienePremium = tieneBadge($badgesUsuario, 'Premium');
$tieneTop = tieneBadge($badgesUsuario, 'Top');
$tieneDestacado = tieneBadge($badgesUsuario, 'Destacado') || (int) ($talento['destacado'] ?? 0);

// Chat
$nrChat = $db->prepare("SELECT COUNT(*) FROM mensajes WHERE para_usuario=? AND leido=0");
$nrChat->execute([$usuario['id']]);
$chatNoLeidos = (int) $nrChat->fetchColumn();

// Vistas al perfil
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

// Estado verificación
$stmtV = $db->prepare("SELECT estado,nota_rechazo FROM verificaciones WHERE usuario_id=? ORDER BY creado_en DESC LIMIT 1");
$stmtV->execute([$usuario['id']]);
$verifDoc = $stmtV->fetch();
$estadoVerif = $verifDoc ? $verifDoc['estado'] : null;
$notaRechazo = $verifDoc ? ($verifDoc['nota_rechazo'] ?? '') : '';

// Vacantes (empresa)
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

// Vacantes disponibles para candidatos (desde la BD)
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

// Datos
/* $fotoUrl       = !empty($usuario['foto']) ? 'uploads/fotos/'.htmlspecialchars($usuario['foto']) : ''; */
$fotoUrl = !empty($usuario['foto']) ? (str_starts_with($usuario['foto'], 'http') ? htmlspecialchars($usuario['foto']) : 'uploads/fotos/' . htmlspecialchars($usuario['foto'])) : '';
$inicial = strtoupper(mb_substr($usuario['nombre'], 0, 1));
$nombreCompleto = htmlspecialchars(trim($usuario['nombre'] . ' ' . ($usuario['apellido'] ?? '')));
$correo = htmlspecialchars($usuario['correo']);
$ciudad = htmlspecialchars($usuario['ciudad'] ?? '');
$telefono = htmlspecialchars($usuario['telefono'] ?? '');
$fechaRegistro = date('d \de F Y', strtotime($usuario['creado_en']));

// Nombre de empresa/negocio
$nombreEmpresa = htmlspecialchars($ep['nombre_empresa'] ?? $extras['nombre_negocio'] ?? $usuario['nombre'] ?? '');
$nombreNegocio = htmlspecialchars($np['nombre_negocio'] ?? $extras['nombre_negocio'] ?? '');
$sectorEmp = htmlspecialchars($ep['sector'] ?? $extras['sector'] ?? '');
$catNeg = htmlspecialchars($np['categoria'] ?? $extras['categoria_neg'] ?? '');
$profesionTipo = htmlspecialchars($extras['profesion_tipo'] ?? $talento['profesion'] ?? '');

// Progreso perfil
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

// Color del tipo para el dashboard
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
  <link
    href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,900;1,9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
  <style>
    :root {
      --v1: #0a4020;
      --v2: #1a7a3c;
      --v3: #27a855;
      --v4: #5dd882;
      --vlima: #a3f0b5;
      --a1: #b38000;
      --a2: #d4a017;
      --a3: #f5c800;
      --a4: #ffd94d;
      --acrem: #fff3b0;
      --r1: #002880;
      --r2: #0039a6;
      --r3: #1a56db;
      --r4: #5b8eff;
      --rcielo: #b8d4ff;
      --bg: #f4f7f5;
      --bg2: #eaf2ec;
      --bg3: #dceee0;
      --card: #ffffff;
      --borde: rgba(39, 168, 85, .22);
      --ink: #0d1f12;
      --ink2: #3a5a42;
      --ink3: #6b8f74;
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
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--ink);
      min-height: 100vh
    }

    /* ── FRANJA TRICOLOR TOP ── */
    .franja-top {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      display: flex;
      z-index: 999
    }

    .franja-top span {
      flex: 1
    }

    .franja-top span:nth-child(1) {
      background: var(--v3)
    }

    .franja-top span:nth-child(2) {
      background: var(--a3)
    }

    .franja-top span:nth-child(3) {
      background: var(--r3)
    }

    /* ── NAVBAR ── */
    .navbar {
      position: sticky;
      top: 3px;
      z-index: 200;
      background: rgba(244, 247, 245, .98);
      border-bottom: 1px solid rgba(39, 168, 85, .18);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--borde);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 36px;
      height: 56px
    }

    .nav-marca {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none
    }

    .nav-marca img {
      width: 28px;
      filter: drop-shadow(0 2px 6px rgba(163, 240, 181, .3))
    }

    .nav-marca-txt {
      font-family: 'Fraunces', serif;
      font-size: 18px;
      color: #0d1f12
    }

    .nav-marca-txt em {
      color: var(--v2);
      font-style: normal
    }

    .nav-links {
      display: flex;
      align-items: center;
      gap: 2px
    }

    .nl {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 7px 12px;
      border-radius: 10px;
      color: var(--ink3);
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      transition: all .2s;
      position: relative;
      white-space: nowrap
    }

    .nl:hover {
      background: rgba(39, 168, 85, .07);
      color: var(--ink)
    }

    .nl.on {
      background: rgba(39, 168, 85, .1);
      color: var(--v2)
    }

    .nl-dot {
      position: absolute;
      top: 5px;
      right: 5px;
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #e74c3c;
      border: 1.5px solid #060e07
    }

    .nav-right {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .nav-nombre {
      font-size: 13px;
      font-weight: 700;
      color: var(--ink2);
      max-width: 130px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap
    }

    .nav-av {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--v2), var(--vlima));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 900;
      color: white;
      cursor: pointer;
      border: 2px solid rgba(39, 168, 85, .35);
      overflow: hidden;
      flex-shrink: 0;
      transition: border-color .2s
    }

    .nav-av:hover {
      border-color: var(--v2)
    }

    .nav-notif {
      position: relative;
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: rgba(39, 168, 85, .05);
      border: 1px solid var(--borde);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      cursor: pointer;
      transition: background .2s;
      flex-shrink: 0
    }

    .nav-notif:hover {
      background: rgba(39, 168, 85, .08)
    }

    .notif-dot {
      position: absolute;
      top: 4px;
      right: 4px;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #e74c3c;
      border: 1.5px solid #060e07;
      animation: pulse-dot 1.5s infinite;
      display: none
    }

    @keyframes pulse-dot {

      0%,
      100% {
        transform: scale(1);
        opacity: 1
      }

      50% {
        transform: scale(1.3);
        opacity: .7
      }
    }

    .notif-panel {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: 290px;
      background: #fff;
      border: 1px solid var(--borde);
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(39, 168, 85, .12);
      z-index: 9999;
      overflow: hidden;
      display: none
    }

    .notif-panel.open {
      display: block
    }

    .notif-head {
      padding: 12px 16px;
      font-size: 11px;
      font-weight: 800;
      color: var(--v2);
      border-bottom: 1px solid var(--borde);
      text-transform: uppercase;
      letter-spacing: .06em
    }

    .notif-item {
      padding: 12px 16px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
      border-bottom: 1px solid var(--borde);
      font-size: 13px;
      color: var(--ink2)
    }

    .notif-item:last-child {
      border-bottom: none
    }

    .notif-ico {
      font-size: 18px;
      flex-shrink: 0;
      margin-top: 1px
    }

    .notif-sub {
      font-size: 11px;
      color: var(--ink3);
      margin-top: 2px
    }

    .notif-empty {
      padding: 18px 16px;
      text-align: center;
      color: var(--ink3);
      font-size: 13px
    }

    .nav-salir {
      padding: 6px 12px;
      border-radius: 10px;
      background: rgba(39, 168, 85, .05);
      border: 1px solid var(--borde);
      color: var(--ink3);
      font-size: 12px;
      font-weight: 700;
      text-decoration: none;
      transition: all .2s
    }

    .nav-salir:hover {
      background: rgba(231, 76, 60, .15);
      color: #ff8080;
      border-color: rgba(231, 76, 60, .25)
    }

    /* ── HERO ── */
    .hero {
      background:
        linear-gradient(160deg, rgba(4, 21, 11, .97) 0%, rgba(8, 24, 14, .92) 50%, rgba(0, 20, 60, .88) 100%),
        url('Imagenes/quibdo 3.jpg') center/cover no-repeat;
      padding: 40px 36px 0;
      position: relative;
      overflow: hidden;
    }

    .hero::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 36px;
      background: var(--bg);
      border-radius: 36px 36px 0 0
    }

    /* Borde inferior tipo de usuario */
    .hero-tipo-borde {
      position: absolute;
      bottom: 35px;
      left: 36px;
      right: 36px;
      height: 2px;
      background: linear-gradient(to right,
          <?= $tc['border'] ?>
          , transparent)
    }

    .hero-inner {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: end;
      gap: 28px;
      padding-bottom: 48px;
      position: relative;
      z-index: 2
    }

    .hero-av {
      width: 96px;
      height: 96px;
      border-radius: 24px;
      background: linear-gradient(135deg, var(--v1), var(--v3));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 34px;
      font-weight: 900;
      color: white;
      border: 3px solid rgba(39, 168, 85, .3);
      box-shadow: 0 8px 32px rgba(39, 168, 85, .15), 0 0 0 6px rgba(39, 168, 85, .08);
      flex-shrink: 0;
      cursor: pointer;
      overflow: hidden;
      transition: all .25s;
    }

    .hero-av:hover {
      border-color: var(--v2);
      transform: scale(1.03)
    }

    .hero-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 12px
    }

    .hchip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .3px
    }

    .hc-tipo {
      background:
        <?= $tc['chip_bg'] ?>
      ;
      color:
        <?= $tc['chip_color'] ?>
      ;
      border: 1px solid
        <?= $tc['border'] ?>
        40
    }

    .hc-v {
      background: rgba(45, 138, 80, .2);
      color: #6dea8f;
      border: 1px solid rgba(45, 138, 80, .3)
    }

    .hc-p {
      background: rgba(200, 134, 10, .2);
      color: var(--a4);
      border: 1px solid rgba(200, 134, 10, .3)
    }

    .hc-top {
      background: rgba(239, 68, 68, .2);
      color: #fca5a5;
      border: 1px solid rgba(239, 68, 68, .3)
    }

    .hc-dest {
      background: rgba(139, 92, 246, .2);
      color: #c4b5fd;
      border: 1px solid rgba(139, 92, 246, .3)
    }

    .hero-nombre {
      font-family: 'Fraunces', serif;
      font-size: clamp(28px, 3vw, 40px);
      color: #fff;
      line-height: 1.1;
      margin-bottom: 6px
    }

    .hero-nombre em {
      color: #a3f0b5;
      font-style: normal
    }

    .hero-sub {
      font-size: 14px;
      color: rgba(255, 255, 255, .75);
      font-weight: 500
    }

    .hero-sub strong {
      color: #fff
    }

    .hero-stats {
      display: flex;
      gap: 20px;
      padding-bottom: 6px
    }

    .hs {
      text-align: center;
      min-width: 70px;
      border-right: 1px solid rgba(255, 255, 255, .15);
      padding-right: 20px
    }

    .hs-val {
      font-family: 'Fraunces', serif;
      font-size: 24px;
      font-weight: 900;
      color: #fff;
      line-height: 1
    }

    .hs-lab {
      font-size: 10px;
      color: var(--ink3);
      text-transform: uppercase;
      letter-spacing: .7px;
      margin-top: 3px;
      font-weight: 600
    }

    .hero-deco {
      position: absolute;
      right: 40px;
      top: 20px;
      font-size: 100px;
      opacity: .04;
      user-select: none;
      pointer-events: none
    }

    /* ── CONTENIDO ── */
    .contenido {
      max-width: 1200px;
      margin: 0 auto;
      padding: 28px 36px 80px
    }

    /* ── ALERTAS ── */
    .alerta {
      border-radius: 16px;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 22px;
      border: 1.5px solid
    }

    .alerta.ap {
      background: rgba(245, 200, 0, .07);
      border-color: rgba(245, 200, 0, .25)
    }

    .alerta.ar {
      background: rgba(239, 68, 68, .07);
      border-color: rgba(239, 68, 68, .2)
    }

    .alerta.as {
      background: rgba(39, 168, 85, .04);
      border-color: var(--borde)
    }

    .alerta.av {
      background: rgba(39, 168, 85, .07);
      border-color: rgba(39, 168, 85, .2)
    }

    .a-ico {
      font-size: 22px;
      flex-shrink: 0
    }

    .a-txt {
      flex: 1
    }

    .a-txt strong {
      display: block;
      font-size: 14px;
      font-weight: 800;
      margin-bottom: 2px
    }

    .alerta.ap .a-txt strong {
      color: var(--a4)
    }

    .alerta.ar .a-txt strong {
      color: #ff8080
    }

    .alerta.as .a-txt strong {
      color: var(--ink)
    }

    .alerta.av .a-txt strong {
      color: var(--v2)
    }

    .a-txt span {
      font-size: 12px;
      color: var(--ink2);
      line-height: 1.5
    }

    .a-btn {
      flex-shrink: 0;
      padding: 7px 16px;
      border-radius: 20px;
      border: none;
      cursor: pointer;
      font-size: 12px;
      font-weight: 800;
      font-family: 'DM Sans', sans-serif;
      text-decoration: none
    }

    .alerta.ap .a-btn {
      background: rgba(245, 200, 0, .15);
      color: var(--a4)
    }

    .alerta.ar .a-btn,
    .alerta.as .a-btn {
      background: var(--v3);
      color: white
    }

    /* ── GRID ── */
    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 16px
    }

    .span2 {
      grid-column: span 2
    }

    .span3 {
      grid-column: span 3
    }

    /* ── CARD ── */
    .card {
      background: var(--card);
      border-radius: 20px;
      border: 1px solid rgba(39, 168, 85, .2);
      box-shadow: 0 2px 12px rgba(39, 168, 85, .08);
      overflow: hidden;
      transition: box-shadow .25s, transform .25s
    }

    .card:hover {
      box-shadow: 0 8px 28px rgba(39, 168, 85, .16);
      transform: translateY(-2px)
    }

    .card-pad {
      padding: 22px
    }

    /* ── MINI MÉTRICAS ── */
    .mini {
      padding: 22px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 120px
    }

    .m-ico {
      width: 40px;
      height: 40px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      margin-bottom: 14px
    }

    .ig {
      background: rgba(39, 168, 85, .18)
    }

    .ia {
      background: rgba(26, 86, 219, .18)
    }

    .im {
      background: rgba(139, 92, 246, .18)
    }

    .io {
      background: rgba(245, 200, 0, .18)
    }

    .m-val {
      font-family: 'Fraunces', serif;
      font-size: 26px;
      font-weight: 900;
      color: #fff;
      line-height: 1
    }

    .m-lab {
      font-size: 11px;
      color: var(--ink3);
      margin-top: 4px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .4px
    }

    .m-sub {
      font-size: 10px;
      color: var(--v2);
      margin-top: 3px;
      font-weight: 800
    }

    /* ── ACCIONES RÁPIDAS ── */
    .ca-tit {
      font-size: 11px;
      font-weight: 800;
      color: var(--ink3);
      text-transform: uppercase;
      letter-spacing: .7px;
      margin-bottom: 16px;
      padding: 22px 22px 0
    }

    .ac-row {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
      gap: 8px;
      padding: 0 22px 22px
    }

    .ac {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      padding: 14px 8px;
      border-radius: 16px;
      background: rgba(39, 168, 85, .04);
      border: 1px solid var(--borde);
      text-decoration: none;
      transition: all .22s;
      position: relative
    }

    .ac:hover {
      background: rgba(163, 240, 181, .07);
      border-color: rgba(163, 240, 181, .2);
      transform: translateY(-2px)
    }

    .ac-ico {
      font-size: 22px
    }

    .ac-tit {
      font-size: 12px;
      font-weight: 700;
      color: var(--ink);
      text-align: center
    }

    .ac-desc {
      font-size: 11px;
      color: #6b8f74;
      text-align: center
    }

    .ac-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      background: #e74c3c;
      color: white;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 7px;
      border-radius: 10px;
      white-space: nowrap
    }

    /* ── EMPLEOS / TALENTOS LIST ── */
    .ce-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 22px 22px 14px
    }

    .ce-tit {
      font-size: 14px;
      font-weight: 800;
      color: var(--ink)
    }

    .ce-ver {
      font-size: 12px;
      font-weight: 700;
      color: var(--v2);
      text-decoration: none
    }

    .ce-ver:hover {
      text-decoration: underline
    }

    .ce-list {
      padding: 0 22px 22px;
      display: flex;
      flex-direction: column;
      gap: 8px
    }

    .ce-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border-radius: 14px;
      background: rgba(39, 168, 85, .04);
      border: 1px solid var(--borde);
      cursor: pointer;
      transition: all .2s
    }

    .ce-item:hover {
      background: rgba(163, 240, 181, .07);
      border-color: rgba(163, 240, 181, .2)
    }

    .ce-ico {
      font-size: 22px;
      flex-shrink: 0
    }

    .ce-info {
      flex: 1;
      min-width: 0
    }

    .ce-nom {
      font-size: 13px;
      font-weight: 800;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      color: var(--ink)
    }

    .ce-emp {
      font-size: 12px;
      color: var(--ink2)
    }

    .ce-met {
      font-size: 11px;
      color: var(--v2);
      font-weight: 600;
      margin-top: 2px
    }

    .ce-badge {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 10px;
      background: rgba(163, 240, 181, .1);
      color: var(--v2);
      white-space: nowrap;
      flex-shrink: 0;
      border: 1px solid rgba(163, 240, 181, .15)
    }

    /* ── HISTORIAL VACANTES ── */
    .hist-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      border-radius: 12px;
      background: rgba(39, 168, 85, .04);
      border: 1px solid var(--borde)
    }

    .hdot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      flex-shrink: 0
    }

    .hdot.act {
      background: #22c55e
    }

    .hdot.cer {
      background: #94a3b8
    }

    .hdot.pen {
      background: #f59e0b
    }

    .hnom {
      font-size: 13px;
      font-weight: 700;
      color: #0d1f12;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis
    }

    .hmeta {
      font-size: 11px;
      color: #6b8f74;
      margin-top: 2px
    }

    .hfecha {
      font-size: 11px;
      color: #6b8f74;
      flex-shrink: 0;
      white-space: nowrap
    }

    /* ── PERFIL CARD ── */
    .cp-head {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      padding: 22px 22px 16px;
      border-bottom: 1px solid var(--borde)
    }

    .cp-av {
      width: 76px;
      height: 76px;
      border-radius: 20px;
      background: linear-gradient(135deg, var(--v1), var(--v3));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
      font-weight: 900;
      color: white;
      margin-bottom: 12px;
      cursor: pointer;
      border: 2px solid rgba(39, 168, 85, .3);
      overflow: hidden;
      transition: all .2s
    }

    .cp-av:hover {
      border-color: var(--v2)
    }

    .cp-nom {
      font-size: 16px;
      font-weight: 900;
      color: var(--ink);
      margin-bottom: 3px
    }

    .cp-pro {
      font-size: 13px;
      color: var(--v2);
      font-weight: 600
    }

    .cp-body {
      padding: 14px 22px;
      display: flex;
      flex-direction: column;
      gap: 8px
    }

    .cp-fil {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--ink2)
    }

    .cp-ico {
      font-size: 15px;
      flex-shrink: 0
    }

    /* Visibilidad toggle */
    .vis-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 14px;
      background: rgba(39, 168, 85, .04);
      border-radius: 12px;
      border: 1px solid var(--borde);
      margin: 4px 0
    }

    .vis-lab {
      font-size: 12px;
      font-weight: 700;
      color: var(--ink2)
    }

    .vis-sub {
      font-size: 10px;
      color: var(--ink3);
      margin-top: 1px
    }

    .tog {
      position: relative;
      width: 42px;
      height: 22px;
      cursor: pointer;
      flex-shrink: 0
    }

    .tog input {
      opacity: 0;
      width: 0;
      height: 0
    }

    .tog-sl {
      position: absolute;
      inset: 0;
      border-radius: 11px;
      background: rgba(39, 168, 85, .12);
      transition: .3s
    }

    .tog-sl::before {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      left: 3px;
      top: 3px;
      border-radius: 50%;
      background: white;
      transition: .3s;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .3)
    }

    .tog input:checked+.tog-sl {
      background: var(--v3)
    }

    .tog input:checked+.tog-sl::before {
      transform: translateX(20px)
    }

    .pv-chip {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      display: inline-block
    }

    .pv-chip.ok {
      background: rgba(39, 168, 85, .15);
      color: var(--v2);
      border: 1px solid rgba(39, 168, 85, .25)
    }

    .pv-chip.off {
      background: rgba(245, 200, 0, .12);
      color: var(--a4);
      border: 1px solid rgba(245, 200, 0, .2)
    }

    /* Progreso */
    .prog-w {
      padding: 0 22px 8px
    }

    .prog-h {
      display: flex;
      justify-content: space-between;
      font-size: 11px;
      font-weight: 700;
      margin-bottom: 6px;
      color: var(--ink3)
    }

    .prog-t {
      height: 5px;
      background: rgba(39, 168, 85, .08);
      border-radius: 4px;
      overflow: hidden
    }

    .prog-f {
      height: 100%;
      background: linear-gradient(90deg, var(--v2), var(--vlima));
      border-radius: 4px;
      transition: width 1s ease
    }

    /* Botones */
    .btn-edit {
      margin: 14px 22px 22px;
      padding: 11px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--v1), var(--v3));
      color: white;
      border: none;
      font-size: 13px;
      font-weight: 800;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all .2s;
      box-shadow: 0 4px 12px rgba(39, 168, 85, .3);
      display: block;
      width: calc(100% - 44px);
      text-align: center;
      text-decoration: none
    }

    .btn-edit:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(39, 168, 85, .4)
    }

    .btn-sec {
      margin: 0 22px 16px;
      padding: 11px;
      border-radius: 14px;
      background: transparent;
      color: var(--v2);
      border: 1.5px solid rgba(163, 240, 181, .25);
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: all .2s;
      display: block;
      width: calc(100% - 44px);
      text-align: center;
      text-decoration: none
    }

    .btn-sec:hover {
      background: rgba(163, 240, 181, .07);
      border-color: rgba(163, 240, 181, .4)
    }

    /* Actividad */
    .cact-tit {
      padding: 22px 22px 14px;
      font-size: 11px;
      font-weight: 800;
      color: var(--ink3);
      text-transform: uppercase;
      letter-spacing: .7px
    }

    .act-list {
      padding: 0 22px 22px;
      display: flex;
      flex-direction: column;
      gap: 8px
    }

    .act-it {
      display: flex;
      align-items: center;
      gap: 12px
    }

    .act-pt {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      flex-shrink: 0
    }

    .pv {
      background: rgba(39, 168, 85, .15)
    }

    .pa {
      background: rgba(26, 86, 219, .15)
    }

    .po {
      background: rgba(245, 200, 0, .12)
    }

    .act-n {
      font-size: 13px;
      font-weight: 600;
      color: var(--ink)
    }

    .act-f {
      font-size: 11px;
      color: var(--ink3);
      margin-top: 1px
    }

    /* Bandera mini en dashboard */
    .bandera-dash {
      width: 52px;
      height: 32px;
      border-radius: 7px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      border: 1.5px solid var(--borde);
      box-shadow: 0 3px 10px rgba(0, 0, 0, .4);
      flex-shrink: 0;
      position: relative
    }

    .banda {
      flex: 1
    }

    .banda-v {
      background: var(--v2)
    }

    .banda-a {
      background: var(--a3)
    }

    .banda-r {
      background: var(--r2)
    }

    .bandera-dash::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(120deg, rgba(39, 168, 85, .08) 0%, transparent 60%);
      border-radius: 5px;
      pointer-events: none
    }

    /* ── MODAL EDITAR ── */
    .modal-ov {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .75);
      z-index: 500;
      align-items: center;
      justify-content: center;
      padding: 20px;
      backdrop-filter: blur(4px)
    }

    .modal-ov.open {
      display: flex
    }

    .modal-box {
      background: #fff;
      border: 1px solid var(--borde);
      border-radius: 24px;
      max-width: 560px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(39, 168, 85, .18);
      animation: fadeUp .3s ease;
      max-height: 90vh;
      overflow-y: auto;
      position: relative
    }

    @keyframes fadeUp {
      from {
        opacity: 0;
        transform: translateY(18px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .mcerrar {
      position: absolute;
      top: 16px;
      right: 18px;
      background: none;
      border: none;
      font-size: 20px;
      cursor: pointer;
      color: var(--ink3);
      z-index: 1
    }

    .mcerrar:hover {
      color: var(--ink)
    }

    .modal-pad {
      padding: 32px
    }

    .mtit {
      font-family: 'Fraunces', serif;
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 6px;
      color: var(--ink)
    }

    .msub {
      font-size: 13px;
      color: var(--ink3);
      margin-bottom: 20px;
      line-height: 1.5
    }

    .msec {
      font-size: 11px;
      font-weight: 800;
      color: var(--ink3);
      text-transform: uppercase;
      letter-spacing: .7px;
      margin: 16px 0 8px
    }

    .mmsg {
      display: none;
      padding: 10px 14px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      margin-bottom: 14px
    }

    .mmsg.success {
      background: rgba(163, 240, 181, .12);
      border: 1px solid rgba(163, 240, 181, .25);
      color: var(--v2)
    }

    .mmsg.error {
      background: rgba(255, 80, 80, .12);
      border: 1px solid rgba(255, 80, 80, .25);
      color: #ff9a9a
    }

    .mfila {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 8px
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
      font-size: 11px;
      font-weight: 700;
      color: var(--ink3);
      text-transform: uppercase;
      letter-spacing: .5px
    }

    .mgr input,
    .mgr select,
    .mgr textarea {
      border: 1.5px solid var(--borde);
      border-radius: 12px;
      padding: 10px 12px;
      font-size: 13px;
      font-family: 'DM Sans', sans-serif;
      color: var(--ink);
      background: rgba(39, 168, 85, .05);
      transition: border-color .2s;
      outline: none;
      resize: none
    }

    .mgr input:focus,
    .mgr select:focus,
    .mgr textarea:focus {
      border-color: var(--v3);
      background: rgba(39, 168, 85, .06)
    }

    .mgr select option {
      background: #fff;
      color: var(--ink)
    }

    .btn-save {
      width: 100%;
      padding: 13px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--v1), var(--v3));
      color: white;
      border: none;
      font-size: 14px;
      font-weight: 900;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      margin-top: 18px;
      box-shadow: 0 4px 14px rgba(39, 168, 85, .3);
      transition: all .2s
    }

    .btn-save:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(39, 168, 85, .4)
    }

    .btn-save:disabled {
      opacity: .5;
      cursor: not-allowed;
      transform: none
    }

    /* Crop modal */
    .crop-modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .8);
      z-index: 99999;
      align-items: center;
      justify-content: center;
      padding: 20px
    }

    .crop-inner {
      background: #fff;
      border: 1px solid var(--borde);
      border-radius: 20px;
      padding: 24px;
      max-width: 420px;
      width: 100%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .5)
    }

    /* ── SECCIONES DE PERFIL (Educación, Certificaciones, Aptitudes) ── */
    .psec {
      background: var(--card);
      border: 1px solid rgba(39, 168, 85, .18);
      border-radius: 14px;
      overflow: hidden;
      margin-top: 12px;
      box-shadow: 0 2px 8px rgba(39, 168, 85, .06);
      transition: box-shadow .25s
    }

    .psec:hover {
      box-shadow: 0 6px 20px rgba(39, 168, 85, .14)
    }

    .psec-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 13px 18px 0
    }

    .psec-tit {
      font-family: 'Fraunces', serif;
      font-size: 14px;
      font-weight: 700;
      color: #0d1f12
    }

    .psec-btns {
      display: flex;
      gap: 6px
    }

    .psec-btn {
      background: none;
      border: none;
      color: var(--ink3);
      font-size: 18px;
      cursor: pointer;
      padding: 4px;
      border-radius: 8px;
      line-height: 1;
      transition: all .2s
    }

    .psec-btn:hover {
      background: rgba(39, 168, 85, .08);
      color: var(--ink)
    }

    .psec-list {
      padding: 10px 18px 6px
    }

    .psec-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid rgba(39, 168, 85, .12);
      position: relative
    }

    .psec-item:last-child {
      border-bottom: none
    }

    .psec-logo {
      width: 36px;
      height: 36px;
      border-radius: 9px;
      background: rgba(39, 168, 85, .06);
      border: 1px solid rgba(39, 168, 85, .18);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
      overflow: hidden
    }

    .psec-logo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 11px
    }

    .psec-body {
      flex: 1;
      min-width: 0
    }

    .psec-nom {
      font-size: 13px;
      font-weight: 800;
      color: #0d1f12;
      line-height: 1.3
    }

    .psec-sub {
      font-size: 12px;
      color: #3a5a42;
      margin-top: 1px
    }

    .psec-meta {
      font-size: 11px;
      color: #6b8f74;
      margin-top: 2px
    }

    .psec-credencial {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-top: 8px;
      padding: 5px 12px;
      border: 1px solid var(--borde);
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      color: var(--ink2);
      background: rgba(39, 168, 85, .04);
      cursor: pointer;
      transition: all .2s;
      text-decoration: none
    }

    .psec-credencial:hover {
      border-color: var(--v2);
      color: var(--v2);
      background: rgba(163, 240, 181, .06)
    }

    .psec-archivo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 8px;
      padding: 9px 12px;
      border: 1px solid var(--borde);
      border-radius: 12px;
      background: rgba(39, 168, 85, .03);
      cursor: pointer;
      transition: background .2s;
      text-decoration: none
    }

    .psec-archivo:hover {
      background: rgba(39, 168, 85, .07)
    }

    .psec-arch-thumb {
      width: 44px;
      height: 40px;
      background: rgba(26, 86, 219, .15);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0
    }

    .psec-arch-name {
      font-size: 12px;
      color: var(--ink2);
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis
    }

    .psec-item-del {
      position: absolute;
      top: 14px;
      right: 0;
      background: none;
      border: none;
      color: var(--ink3);
      font-size: 14px;
      cursor: pointer;
      padding: 4px;
      border-radius: 6px;
      opacity: 0;
      transition: all .2s
    }

    .psec-item:hover .psec-item-del {
      opacity: 1
    }

    .psec-item-del:hover {
      color: #ff6b6b;
      background: rgba(255, 107, 107, .1)
    }

    .psec-ver-mas {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      padding: 12px 22px;
      border-top: 1px solid var(--borde);
      font-size: 13px;
      font-weight: 700;
      color: var(--ink3);
      cursor: pointer;
      transition: color .2s;
      background: none;
      border-left: none;
      border-right: none;
      border-bottom: none;
      width: 100%;
      font-family: 'DM Sans', sans-serif
    }

    .psec-ver-mas:hover {
      color: var(--v2)
    }

    /* Aptitudes */
    .apt-grupo {
      margin-bottom: 16px
    }

    .apt-grupo:last-child {
      margin-bottom: 0
    }

    .apt-nom {
      font-size: 14px;
      font-weight: 800;
      color: var(--ink);
      margin-bottom: 6px
    }

    .apt-items {
      display: flex;
      flex-wrap: wrap;
      gap: 6px
    }

    .apt-chip {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 12px;
      background: rgba(39, 168, 85, .05);
      border: 1px solid var(--borde);
      font-size: 12px;
      font-weight: 600;
      color: var(--ink2);
      cursor: default
    }

    .apt-chip-ico {
      font-size: 14px
    }

    /* ── MODAL HOJA DE VIDA + PUBLICAR VACANTE ── */
    .hoja-modal-box {
      background: #fff;
      border: 1px solid var(--borde);
      border-radius: 24px;
      max-width: 700px;
      width: 100%;
      box-shadow: 0 30px 80px rgba(39, 168, 85, .15);
      animation: fadeUp .3s ease;
      max-height: 92vh;
      overflow-y: auto;
      position: relative
    }

    .hoja-sec {
      font-size: 11px;
      font-weight: 800;
      color: var(--v2);
      text-transform: uppercase;
      letter-spacing: .8px;
      margin: 20px 0 10px;
      display: flex;
      align-items: center;
      gap: 8px
    }

    .hoja-sec-num {
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--v2), var(--v3));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 9px;
      color: #fff;
      font-weight: 900;
      flex-shrink: 0
    }

    .hoja-divider {
      height: 1px;
      background: var(--borde);
      margin: 16px 0
    }

    .hoja-fila {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 10px
    }

    @media(max-width:600px) {
      .hoja-fila {
        grid-template-columns: 1fr
      }
    }

    .hoja-gr {
      display: flex;
      flex-direction: column;
      gap: 5px
    }

    .hoja-gr.full {
      grid-column: 1/-1
    }

    .hoja-gr label {
      font-size: 11px;
      font-weight: 700;
      color: var(--ink2);
      text-transform: uppercase;
      letter-spacing: .6px
    }

    .hoja-gr input,
    .hoja-gr select,
    .hoja-gr textarea {
      width: 100%;
      padding: 11px 13px;
      background: #f8fdf9;
      border: 1.5px solid var(--borde);
      border-radius: 13px;
      color: var(--ink);
      font-size: 13px;
      font-family: 'DM Sans', sans-serif;
      outline: none;
      transition: border-color .2s, background .2s, box-shadow .2s;
      resize: none
    }

    .hoja-gr input:focus,
    .hoja-gr select:focus,
    .hoja-gr textarea:focus {
      border-color: var(--v3);
      background: rgba(39, 168, 85, .07);
      box-shadow: 0 0 0 3px rgba(39, 168, 85, .1)
    }

    .hoja-gr input::placeholder,
    .hoja-gr textarea::placeholder {
      color: var(--ink3)
    }

    .hoja-gr select option {
      background: #fff;
      color: var(--ink)
    }

    .hoja-item-card {
      background: #f8fdf9;
      border: 1px solid var(--borde);
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 10px;
      position: relative;
      animation: fadeUp .3s ease both
    }

    .hoja-item-rm {
      position: absolute;
      top: 10px;
      right: 12px;
      background: rgba(255, 80, 80, .15);
      border: none;
      color: #ff7a7a;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      font-size: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center
    }

    .hoja-item-rm:hover {
      background: rgba(255, 80, 80, .3)
    }

    .hoja-btn-add {
      width: 100%;
      padding: 10px;
      background: rgba(39, 168, 85, .1);
      border: 1.5px dashed rgba(39, 168, 85, .3);
      color: var(--v2);
      border-radius: 12px;
      font-size: 13px;
      font-weight: 700;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      transition: all .25s
    }

    .hoja-btn-add:hover {
      background: rgba(39, 168, 85, .18);
      border-color: var(--v3)
    }

    .hoja-progress-track {
      background: rgba(39, 168, 85, .07);
      border-radius: 8px;
      height: 4px;
      overflow: hidden;
      margin-bottom: 20px
    }

    .hoja-progress-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--v3), var(--v4));
      border-radius: 8px;
      transition: width .4s ease;
      width: 0%
    }

    /* ── RESPONSIVE ── */
    @media(max-width:900px) {
      .grid {
        grid-template-columns: 1fr 1fr
      }

      .span3 {
        grid-column: 1/-1
      }

      .span2 {
        grid-column: 1/-1
      }

      .nav-links {
        display: none
      }
    }

    @media(max-width:600px) {
      .navbar {
        padding: 0 16px
      }

      .contenido {
        padding: 20px
      }

      .hero {
        padding: 28px 20px 0
      }

      .grid {
        grid-template-columns: 1fr
      }

      .span2,
      .span3 {
        grid-column: 1/-1
      }

      .hero-inner {
        grid-template-columns: 1fr;
        gap: 16px;
        padding-bottom: 40px
      }

      .hero-stats {
        gap: 14px
      }

      .mfila {
        grid-template-columns: 1fr
      }
    }
  </style>
</head>

<body>

  <div class="franja-top"><span></span><span></span><span></span></div>

  <!-- ── NAVBAR ── -->
  <nav class="navbar">
    <a href="index.html" class="nav-marca">
      <img src="Imagenes/Quibdo.png" alt="Logo">
      <div class="nav-marca-txt">Quibdó<em>Conecta</em></div>
    </a>
    <div class="nav-links">
      <a href="dashboard.php" class="nl on">🏠 Panel</a>
      <a href="Empleo.php" class="nl">💼 Empleos</a>
      <a href="talentos.php" class="nl">🌟 Talentos</a>
      <a href="empresas.php" class="nl">🏢 Empresas</a>
      <a href="negocios.php" class="nl">🏪 Negocios</a>
      <a href="servicios.php" class="nl">🎧 Eventos</a>
      <a href="chat.php" class="nl">💬 Chat<?php if ($chatNoLeidos > 0): ?><span
            class="nl-dot"></span><?php endif; ?></a>
      <a href="convocatorias.php" class="nl">📢 Convocatorias</a>
      <?php if ($tipo === 'empresa' || $tipo === 'negocio'): ?>
        <a href="#" class="nl" onclick="abrirPublicarVacante();return false;" style="color:var(--v2)">➕ Publicar
          vacante</a>
      <?php elseif ($tipo === 'candidato' || $subTipo === 'servicio' || !empty($talento['precio_desde'])): ?>
        <a href="#" class="nl" onclick="abrirHoja();return false;" style="color:var(--a4)">📄 Mi CV</a>
      <?php endif; ?>
    </div>
    <div class="nav-right">
      <div class="nav-nombre"><?= htmlspecialchars($usuario['nombre']) ?></div>
      <div class="nav-notif" id="navNotif" title="Notificaciones">
        🔔<span class="notif-dot" id="notifDot"></span>
        <div class="notif-panel" id="notifPanel">
          <div class="notif-head">🔔 Notificaciones</div>
          <div id="notifLista">
            <div class="notif-empty">Cargando…</div>
          </div>
        </div>
      </div>
      <div class="nav-av" id="navAvatar" onclick="abrirModal()" title="Editar perfil">
        <?php if ($fotoUrl): ?><img src="<?= $fotoUrl ?>" alt="Foto"
            style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= $inicial ?><?php endif; ?>
      </div>
      <a href="Php/logout.php" class="nav-salir">Salir</a>
    </div>
  </nav>

  <!-- ── HERO ── -->
  <div class="hero">
    <div class="hero-tipo-borde"></div>
    <div class="hero-inner">
      <!-- Avatar -->
      <div class="hero-av" id="heroAvatar" onclick="abrirModal()" title="Cambiar foto">
        <?php if ($fotoUrl): ?>
          <img src="<?= $fotoUrl ?>" alt="Foto" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= $inicial ?>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div>
        <div class="hero-chips">
          <span class="hchip hc-tipo"><?= $tc['label'] ?></span>
          <?php if ($tieneVerificado): ?><span class="hchip hc-v">✓ Verificado</span><?php endif; ?>
          <?php if ($tienePremium): ?><span class="hchip hc-p">⭐ Premium</span><?php endif; ?>
          <?php if ($tieneTop): ?><span class="hchip hc-top">👑 Top</span><?php endif; ?>
          <?php if ($tieneDestacado): ?><span class="hchip hc-dest">🏅 Destacado</span><?php endif; ?>
        </div>
        <div class="hero-nombre">
          ¡Hola, <em><?= htmlspecialchars($usuario['nombre']) ?></em>!
        </div>
        <div class="hero-sub">
          <?php if ($tipo === 'empresa' && $nombreEmpresa): ?>
            <strong><?= $nombreEmpresa ?></strong><?php if ($sectorEmp): ?> · <?= $sectorEmp ?><?php endif; ?>
            <?php if ($ciudad): ?> · <?= $ciudad ?><?php endif; ?>
          <?php elseif ($tipo === 'negocio' && $nombreNegocio): ?>
            <strong><?= $nombreNegocio ?></strong><?php if ($catNeg): ?> · <?= $catNeg ?><?php endif; ?>
          <?php elseif ($subTipo === 'servicio' && $profesionTipo): ?>
            <strong><?= $profesionTipo ?></strong><?php if ($ciudad): ?> · <?= $ciudad ?><?php endif; ?>
          <?php elseif (!empty($talento['profesion'])): ?>
            <strong><?= htmlspecialchars($talento['profesion']) ?></strong>
            <?php if ($ciudad): ?> · <?= $ciudad ?><?php endif; ?>
          <?php else: ?>
            <?php echo $tipo === 'empresa' ? 'Conecta con el talento del Chocó.' : 'Completa tu perfil para conectar con oportunidades.'; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stats + bandera -->
      <div>
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
              <div class="hs-val">
                <?= $talento['calificacion'] ? number_format((float) $talento['calificacion'], 1) : '—' ?>
              </div>
              <div class="hs-lab">Calif.</div>
            </div>
          <?php else: ?>
            <div class="hs">
              <div class="hs-val">0</div>
              <div class="hs-lab">Postulac.</div>
            </div>
          <?php endif; ?>
        </div>
        <!-- Bandera del Chocó -->
        <div style="display:flex;justify-content:flex-end;margin-top:14px">
          <div class="bandera-dash">
            <div class="banda banda-v"></div>
            <div class="banda banda-a"></div>
            <div class="banda banda-r"></div>
          </div>
        </div>
      </div>
    </div>
    <div class="hero-deco"><?= $tc['deco'] ?></div>
  </div>

  <!-- ── CONTENIDO ── -->
  <div class="contenido">

    <!-- ALERTAS -->
    <?php if (!$tieneVerificado): ?>
      <?php if ($estadoVerif === 'pendiente'): ?>
        <div class="alerta ap">
          <div class="a-ico">⏳</div>
          <div class="a-txt"><strong>Documentos en revisión</strong><span>El administrador está revisando tu
              documento.</span></div>
        </div>
      <?php elseif ($estadoVerif === 'rechazado'): ?>
        <div class="alerta ar">
          <div class="a-ico">❌</div>
          <div class="a-txt"><strong>Verificación
              rechazada</strong><span><?= $notaRechazo ?: 'Intenta subir el documento con mejor calidad.' ?></span></div><a
            href="verificar_cuenta.php" class="a-btn">Reintentar</a>
        </div>
      <?php else: ?>
        <div class="alerta as">
          <div class="a-ico">🪪</div>
          <div class="a-txt"><strong>Verifica tu identidad</strong><span>Sube tu documento y obtén el badge
              verificado.</span></div><a href="verificar_cuenta.php" class="a-btn">Verificar ahora</a>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alerta av">
        <div class="a-ico">✅</div>
        <div class="a-txt"><strong>Cuenta verificada</strong><span>Los empleadores ven tu badge de verificación.</span>
        </div>
      </div>
    <?php endif; ?>

    <div class="grid">

      <!-- ── MÉTRICAS SEGÚN TIPO ── -->
      <?php if ($tipo === 'empresa'): ?>
        <div class="card mini">
          <div class="m-ico ig">💼</div>
          <div>
            <div class="m-val"><?= $vacantesActivas ?></div>
            <div class="m-lab">Vacantes activas</div>
            <div class="m-sub" onclick="abrirPublicarVacante()">Publicar →</div>
          </div>
        </div>
        <div class="card mini">
          <div class="m-ico ia">👥</div>
          <div>
            <div class="m-val">0</div>
            <div class="m-lab">Candidatos</div>
            <div class="m-sub" onclick="location.href='talentos.php'">Ver talentos →</div>
          </div>
        </div>
        <div class="card mini">
          <div class="m-ico im">👁️</div>
          <div>
            <div class="m-val"><?= $vistasTotal ?></div>
            <div class="m-lab">Vistas al perfil</div>
            <div class="m-sub"><?= $vistas7dias > 0 ? '+' . $vistas7dias . ' esta semana' : 'Ver en directorio →' ?></div>
          </div>
        </div>

      <?php elseif ($tipo === 'negocio' || ($extras['tipo_negocio_reg'] ?? '')): ?>
        <div class="card mini">
          <div class="m-ico ig">🏪</div>
          <div>
            <div class="m-val"><?= $vistasTotal ?></div>
            <div class="m-lab">Vistas al negocio</div>
            <div class="m-sub" onclick="location.href='negocios.php'">
              <?= $vistas7dias > 0 ? "+" . $vistas7dias . " esta semana" : "Ver directorio →" ?>
            </div>
          </div>
        </div>
        <div class="card mini">
          <div class="m-ico io">💬</div>
          <div>
            <div class="m-val"><?= $chatNoLeidos ?></div>
            <div class="m-lab">Mensajes</div>
            <div class="m-sub" onclick="location.href='chat.php'">Ver chat →</div>
          </div>
        </div>
        <div class="card mini">
          <div class="m-ico im">⭐</div>
          <div>
            <div class="m-val"><?= $pct ?>%</div>
            <div class="m-lab">Perfil completado</div>
            <div class="m-sub" onclick="abrirModal()"><?= $pct < 100 ? 'Mejorar →' : '¡Perfecto! ✓' ?></div>
          </div>
        </div>

      <?php elseif ($subTipo === 'servicio' || !empty($talento['precio_desde'])): ?>
        <div class="card mini">
          <div class="m-ico ig">🎧</div>
          <div>
            <div class="m-val">
              <?= $talento['precio_desde'] ? '$' . number_format((float) $talento['precio_desde'], 0, ',', '.') : '—' ?>
            </div>
            <div class="m-lab">Precio desde</div>
            <div class="m-sub" onclick="location.href='servicios.php'">Ver servicios →</div>
          </div>
        </div>
        <div class="card mini">
          <div class="m-ico io">⭐</div>
          <div>
            <div class="m-val">
              <?= $talento['calificacion'] ? number_format((float) $talento['calificacion'], 1) : '0' ?>/5
            </div>
            <div class="m-lab">Calificación</div>
            <div class="m-sub">Reseñas →</div>
          </div>
        </div>
        <div class="card mini">
          <div class="m-ico im">👁️</div>
          <div>
            <div class="m-val"><?= $vistasTotal ?></div>
            <div class="m-lab">Vistas al perfil</div>
            <div class="m-sub"><?= $vistas7dias > 0 ? '+' . $vistas7dias . ' esta semana' : 'Hazte visible →' ?></div>
          </div>
        </div>

      <?php else: /* candidato */ ?>
        <div class="card mini">
          <div class="m-ico ig">📋</div>
          <div>
            <div class="m-val">0</div>
            <div class="m-lab">Postulaciones</div>
            <div class="m-sub">Empieza hoy →</div>
          </div>
        </div>
        <div class="card mini">
          <div class="m-ico im">👁️</div>
          <div>
            <div class="m-val" id="statVistas"><?= $vistasTotal ?></div>
            <div class="m-lab">Vistas al perfil</div>
            <div class="m-sub"><?= $vistas7dias > 0 ? "+" . $vistas7dias . " esta semana" : "Hazte visible →" ?></div>
          </div>
        </div>
        <div class="card mini">
          <div class="m-ico io">⭐</div>
          <div>
            <div class="m-val"><?= $pct ?>%</div>
            <div class="m-lab">Perfil completado</div>
            <div class="m-sub" onclick="abrirModal()"><?= $pct < 100 ? 'Mejorar →' : '¡Perfecto! ✓' ?></div>
          </div>
        </div>
      <?php endif; ?>

      <!-- ── ACCIONES RÁPIDAS (span 3) ── -->
      <div class="card span3">
        <div class="ca-tit">⚡ Acciones rápidas</div>
        <div class="ac-row">
          <?php if ($tipo === 'empresa'): ?>
            <a href="#" class="ac" onclick="abrirPublicarVacante();return false;"
              style="border-color:rgba(39,168,85,.3);background:rgba(39,168,85,.06)">
              <div class="ac-ico">➕</div>
              <div class="ac-tit" style="color:var(--v2)">Publicar vacante</div>
              <div class="ac-desc">Nueva oferta de empleo</div>
            </a>
            <a href="talentos.php" class="ac">
              <div class="ac-ico">🌟</div>
              <div class="ac-tit">Ver talentos</div>
              <div class="ac-desc">Buscar candidatos</div>
            </a>
            <a href="empresas.php" class="ac">
              <div class="ac-ico">🏢</div>
              <div class="ac-tit">Mi empresa</div>
              <div class="ac-desc">Ver en directorio</div>
            </a>
          <?php elseif ($tipo === 'negocio'): ?>
            <a href="#" class="ac" onclick="abrirPublicarVacante();return false;"
              style="border-color:rgba(39,168,85,.3);background:rgba(39,168,85,.06)">
              <div class="ac-ico">➕</div>
              <div class="ac-tit" style="color:var(--v2)">Publicar vacante</div>
              <div class="ac-desc">Nueva oferta de empleo</div>
            </a>
            <a href="negocios.php" class="ac">
              <div class="ac-ico">🏪</div>
              <div class="ac-tit">Mi negocio</div>
              <div class="ac-desc">Ver en directorio</div>
            </a>
            <a href="Empleo.html" class="ac">
              <div class="ac-ico">💼</div>
              <div class="ac-tit">Ver empleos</div>
              <div class="ac-desc">Oportunidades</div>
            </a>
          <?php elseif ($subTipo === 'servicio' || !empty($talento['precio_desde'])): ?>
            <a href="servicios.php" class="ac">
              <div class="ac-ico">🎧</div>
              <div class="ac-tit">Mis servicios</div>
              <div class="ac-desc">Ver en directorio</div>
            </a>
            <a href="#" class="ac" onclick="abrirHoja();return false;"
              style="border-color:rgba(255,211,77,.2);background:rgba(255,211,77,.05)">
              <div class="ac-ico">📄</div>
              <div class="ac-tit" style="color:var(--a4)">Mi Hoja de Vida</div>
              <div class="ac-desc">Actualiza tu CV</div>
            </a>
            <a href="Empleo.html" class="ac">
              <div class="ac-ico">💼</div>
              <div class="ac-tit">Ver empleos</div>
              <div class="ac-desc">Vacantes del Chocó</div>
            </a>
          <?php else: /* candidato */ ?>
            <a href="Empleo.html" class="ac">
              <div class="ac-ico">🔍</div>
              <div class="ac-tit">Buscar empleo</div>
              <div class="ac-desc">Vacantes del Chocó</div>
            </a>
            <a href="#" class="ac" onclick="abrirHoja();return false;"
              style="border-color:rgba(255,211,77,.2);background:rgba(255,211,77,.05)">
              <div class="ac-ico">📄</div>
              <div class="ac-tit" style="color:var(--a4)">Mi Hoja de Vida</div>
              <div class="ac-desc">Actualiza tu CV</div>
            </a>
            <a href="talentos.php" class="ac">
              <div class="ac-ico">🌟</div>
              <div class="ac-tit">Talentos</div>
              <div class="ac-desc">Profesionales locales</div>
            </a>
          <?php endif; ?>
          <a href="chat.php" class="ac">
            <div class="ac-ico">💬</div>
            <div class="ac-tit">Mensajes</div>
            <?php if ($chatNoLeidos > 0): ?><span class="ac-badge"><?= $chatNoLeidos ?> sin leer</span><?php else: ?>
              <div class="ac-desc">Sin nuevos</div><?php endif; ?>
          </a>
          <a href="verificar_cuenta.php" class="ac">
            <div class="ac-ico">🪪</div>
            <div class="ac-tit">Verificación</div>
            <div class="ac-desc"><?= $tieneVerificado ? '✅ Verificado' : 'Subir doc.' ?></div>
          </a>
          <a href="convocatorias.php" class="ac">
            <div class="ac-ico">📢</div>
            <div class="ac-tit">Convocatorias</div>
            <div class="ac-desc">Sector público</div>
          </a>
          <a href="empresas.php" class="ac">
            <div class="ac-ico">🏢</div>
            <div class="ac-tit">Empresas</div>
            <div class="ac-desc">Directorio</div>
          </a>
          <a href="negocios.php" class="ac">
            <div class="ac-ico">🏪</div>
            <div class="ac-tit">Negocios</div>
            <div class="ac-desc">Locales & Emprendedores</div>
          </a>
          <a href="servicios.php" class="ac">
            <div class="ac-ico">🎧</div>
            <div class="ac-tit">Eventos</div>
            <div class="ac-desc">DJs, fotógrafos...</div>
          </a>
          <a href="Ayuda.html" class="ac">
            <div class="ac-ico">❓</div>
            <div class="ac-tit">Ayuda</div>
            <div class="ac-desc">Soporte</div>
          </a>
        </div>
      </div>

      <!-- ── LISTA EMPLEOS / VACANTES / TALENTOS ── -->
      <div class="card span2">
        <div class="ce-head">
          <div class="ce-tit">
            <?php if ($tipo === 'empresa'): ?>📋 Historial de vacantes
            <?php elseif ($subTipo === 'servicio'): ?>🎧 Mis géneros / especialidades
            <?php else: ?>💼 Empleos sugeridos<?php endif; ?>
          </div>
          <a href="#" class="ce-ver" <?= $tipo === 'empresa' ? 'onclick="abrirPublicarVacante();return false;"' : 'href="Empleo.html"' ?>>
            <?= $tipo === 'empresa' ? 'Publicar nueva →' : 'Ver todos →' ?>
          </a>
        </div>

        <?php if ($tipo === 'empresa' && !empty($historialVacantes)): ?>
          <div class="ce-list">
            <?php foreach ($historialVacantes as $v): ?>
              <?php
              // activo=1 → Activa, activo=0 → Pendiente de aprobación o Cerrada
              // Distinguimos: si fue creada hace menos de 72h y activo=0 → Pendiente
              $horas = (time() - strtotime($v['creado_en'])) / 3600;
              $esPendiente = !$v['activo'] && $horas < 72;
              $estadoLabel = $v['activo']
                ? '<span style="color:#16a34a;font-weight:700">✅ Activa</span>'
                : ($esPendiente
                  ? '<span style="color:#d97706;font-weight:700">⏳ Pendiente aprobación</span>'
                  : '<span style="color:#6b8f74;font-weight:700">🔒 Cerrada</span>');
              $modalidad = htmlspecialchars(ucfirst($v['modalidad'] ?? $v['tipo_contrato'] ?? ''));
              ?>
              <div class="hist-item">
                <div class="hdot <?= $v['activo'] ? 'act' : ($esPendiente ? 'pen' : 'cer') ?>"></div>
                <div style="flex:1;min-width:0">
                  <div class="hnom"><?= htmlspecialchars($v['titulo']) ?></div>
                  <div class="hmeta">
                    📍 <?= htmlspecialchars($v['ciudad'] ?? 'Quibdó') ?>
                    <?= $modalidad ? ' · ' . $modalidad : '' ?>
                    · <?= $estadoLabel ?>
                  </div>
                </div>
                <div class="hfecha"><?= date('d/m/Y', strtotime($v['creado_en'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php elseif ($tipo === 'empresa'): ?>
          <div style="text-align:center;padding:32px 20px;color:var(--ink3)">
            <div style="font-size:40px;margin-bottom:10px">💼</div>
            <div style="font-size:14px;font-weight:700;color:var(--ink2);margin-bottom:6px">Aún no has publicado vacantes
            </div>
            <a href="#" onclick="abrirPublicarVacante()"
              style="display:inline-block;margin-top:12px;padding:10px 22px;background:var(--v3);color:white;border-radius:10px;text-decoration:none;font-weight:800;font-size:13px">➕
              Publicar primera vacante</a>
          </div>
        <?php elseif ($subTipo === 'servicio' && !empty($talento['generos'])): ?>
          <div class="ce-list">
            <?php foreach (array_slice(array_filter(array_map('trim', explode(',', $talento['generos']))), 0, 6) as $g): ?>
              <div class="ce-item">
                <div class="ce-ico">🎵</div>
                <div class="ce-info">
                  <div class="ce-nom"><?= htmlspecialchars($g) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <?php if (!empty($vacantesDisponibles)): ?>
            <div class="ce-list">
              <?php
              $catIcons = ['Tecnología' => '💻', 'Diseño' => '🎨', 'Música' => '🎵', 'Administración' => '📊', 'Salud' => '🏥', 'Educación' => '📚', 'Comercio' => '🛒', 'Construcción' => '🏗️', 'Legal' => '⚖️'];
              ?>
              <?php foreach ($vacantesDisponibles as $v): ?>
                <?php
                $cat = $v['categoria'] ?? '';
                $ico = $catIcons[$cat] ?? '💼';
                $ciudad = htmlspecialchars($v['ciudad'] ?? 'Quibdó');
                $salario = !empty($v['salario_texto']) ? htmlspecialchars($v['salario_texto']) : '';
                $meta = $ciudad . ($salario ? ' · ' . $salario : '');
                $modalidad = htmlspecialchars(ucfirst($v['modalidad'] ?? $v['tipo_contrato'] ?? 'Tiempo completo'));
                ?>
                <div class="ce-item" onclick="location.href='Empleo.html'">
                  <div class="ce-ico"><?= $ico ?></div>
                  <div class="ce-info">
                    <div class="ce-nom"><?= htmlspecialchars($v['titulo']) ?></div>
                    <div class="ce-emp"><?= htmlspecialchars($v['empresa'] ?? 'Empresa') ?></div>
                    <div class="ce-met">📍 <?= $meta ?></div>
                  </div>
                  <span class="ce-badge"><?= $modalidad ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="text-align:center;padding:32px 20px;color:var(--ink3)">
              <div style="font-size:40px;margin-bottom:10px">🔍</div>
              <div style="font-size:14px;font-weight:700;color:var(--ink2);margin-bottom:6px">No hay vacantes activas por
                ahora</div>
              <div style="font-size:13px;color:var(--ink3);margin-bottom:14px">Vuelve pronto — publicamos nuevas ofertas
                cada semana</div>
              <a href="Empleo.html"
                style="display:inline-block;padding:10px 22px;background:var(--v3);color:white;border-radius:10px;text-decoration:none;font-weight:800;font-size:13px">🔍
                Explorar empleos</a>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- ── PERFIL CARD ── -->
      <div class="card" style="display:flex;flex-direction:column">
        <div class="cp-head">
          <div class="cp-av" id="cpAvatar" onclick="abrirModal()">
            <?php if ($fotoUrl): ?><img src="<?= $fotoUrl ?>" alt="Foto"
                style="width:100%;height:100%;object-fit:cover;border-radius:18px"><?php else: ?><?= $inicial ?><?php endif; ?>
          </div>
          <div class="cp-nom" id="dNombre">
            <?= $tipo === 'empresa' ? $nombreEmpresa : ($tipo === 'negocio' ? $nombreNegocio : $nombreCompleto) ?>
          </div>
          <div class="cp-pro" id="dProfesion">
            <?php if ($tipo === 'empresa'): ?>
              <?= $sectorEmp ?: 'Sector no definido' ?>
            <?php elseif ($tipo === 'negocio'): ?>
              <?= $catNeg ?: 'Categoría no definida' ?>
            <?php elseif ($subTipo === 'servicio'): ?>
              <?= $profesionTipo ?: 'Servicio para eventos' ?>
            <?php else: ?>
              <?= !empty($talento['profesion']) ? htmlspecialchars($talento['profesion']) : ($profesionTipo ?: 'Sin profesión') ?>
            <?php endif; ?>
          </div>
        </div>
        <div class="cp-body">
          <div class="cp-fil"><span class="cp-ico">📍</span><span
              id="dCiudad"><?= $ciudad ?: 'Ciudad no registrada' ?></span></div>
          <div class="cp-fil"><span class="cp-ico">📞</span><span
              id="dTelefono"><?= $telefono ?: 'Teléfono no registrado' ?></span></div>
          <div class="cp-fil"><span class="cp-ico">✉️</span><span><?= $correo ?></span></div>
          <?php if ($tipo === 'empresa' && !empty($extras['nit'] ?? $ep['nit'] ?? '')): ?>
            <div class="cp-fil"><span class="cp-ico">🏛️</span><span>NIT:
                <?= htmlspecialchars($extras['nit'] ?? $ep['nit'] ?? '') ?></span></div>
          <?php endif; ?>
          <?php if ($tipo === 'negocio' && !empty($extras['whatsapp_neg'] ?? $np['whatsapp'] ?? '')): ?>
            <div class="cp-fil"><span class="cp-ico">💬</span><span>WhatsApp:
                <?= htmlspecialchars($extras['whatsapp_neg'] ?? $np['whatsapp'] ?? '') ?></span></div>
          <?php endif; ?>
          <?php if ($subTipo === 'servicio' && !empty($talento['precio_desde'])): ?>
            <div class="cp-fil"><span class="cp-ico">💰</span><span>Desde
                $<?= number_format((float) $talento['precio_desde'], 0, ',', '.') ?></span></div>
          <?php endif; ?>
          <div class="cp-fil"><span class="cp-ico">📅</span><span><?= $fechaRegistro ?></span></div>
          <?php if (!empty($badgesHTML)): ?>
            <div style="margin-top:8px"><?= $badgesHTML ?></div>
          <?php endif; ?>
        </div>

        <!-- Toggle visibilidad -->
        <?php if ($tipo === 'candidato' || $subTipo === 'servicio'): ?>
          <div style="padding:0 22px">
            <div class="vis-row">
              <div>
                <div class="vis-lab">Visible en <?= $subTipo === 'servicio' ? 'Servicios' : 'Talentos' ?></div>
                <div class="vis-sub">Aparece en el directorio público</div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <span id="pvBadge"
                  class="pv-chip <?= ($talento['visible'] ?? 0) ? 'ok' : 'off' ?>"><?= ($talento['visible'] ?? 0) ? '🟢 Visible' : '🟡 Oculto' ?></span>
                <label class="tog"><input type="checkbox" <?= ($talento['visible'] ?? 0) ? 'checked' : '' ?>
                    onchange="toggleVis(this.checked)"><span class="tog-sl"></span></label>
              </div>
            </div>
          </div>
        <?php elseif ($tipo === 'empresa'): ?>
          <div style="padding:0 22px">
            <div class="vis-row">
              <div>
                <div class="vis-lab">Visible en Empresas</div>
                <div class="vis-sub">Aparece en el directorio público</div>
              </div>
              <span
                class="pv-chip <?= ($ep['visible_admin'] ?? 1) ? 'ok' : 'off' ?>"><?= ($ep['visible_admin'] ?? 1) ? '🟢 Visible' : '🟡 Oculto' ?></span>
            </div>
          </div>
        <?php elseif ($tipo === 'negocio'): ?>
          <div style="padding:0 22px">
            <div class="vis-row">
              <div>
                <div class="vis-lab">Visible en Negocios</div>
                <div class="vis-sub">Aparece en el directorio público</div>
              </div>
              <span
                class="pv-chip <?= ($np['visible_admin'] ?? 1) ? 'ok' : 'off' ?>"><?= ($np['visible_admin'] ?? 1) ? '🟢 Visible' : '🟡 Oculto' ?></span>
            </div>
          </div>
        <?php endif; ?>

        <!-- Progreso -->
        <div class="prog-w" style="margin:10px 0">
          <div class="prog-h"><span>Perfil completado</span><span id="pctLabel"><?= $pct ?>%</span></div>
          <div class="prog-t">
            <div class="prog-f" id="progBar" style="width:0%"></div>
          </div>
        </div>

        <button class="btn-edit" onclick="abrirModal()">✏️ Editar mi perfil</button>
        <a href="<?= $tipo === 'empresa' ? 'empresas.php#u' . $usuario['id'] : ($tipo === 'negocio' ? 'negocios.php#u' . $usuario['id'] : ($subTipo === 'servicio' ? 'servicios.php' : 'talentos.php')) ?>"
          class="btn-sec">🌐 Ver mi perfil en directorio</a>
        <a href="perfil.php?id=<?= $usuario['id'] ?>&tipo=<?= urlencode($tipo) ?>"
          class="btn-sec" style="margin-top:6px;">👤 Ver mi perfil público</a>
      </div>

      <!-- ── ACTIVIDAD RECIENTE ── -->
      <div class="card span2">
        <div class="cact-tit">🕐 Actividad reciente</div>
        <div class="act-list">
          <div class="act-it">
            <div class="act-pt pv">🎉</div>
            <div class="act-tx">
              <div class="act-n">¡Cuenta creada!</div>
              <div class="act-f"><?= $fechaRegistro ?></div>
            </div>
          </div>
          <div class="act-it">
            <div class="act-pt pa">👀</div>
            <div class="act-tx">
              <div class="act-n">Exploraste empleos</div>
              <div class="act-f">Hoy</div>
            </div>
          </div>
          <?php if ($tieneVerificado): ?>
            <div class="act-it">
              <div class="act-pt pv">✅</div>
              <div class="act-tx">
                <div class="act-n">Cuenta verificada</div>
                <div class="act-f">Badge asignado</div>
              </div>
            </div>
          <?php endif; ?>
          <?php if ($tipo === 'empresa' && $vacantesActivas > 0): ?>
            <div class="act-it">
              <div class="act-pt ig">💼</div>
              <div class="act-tx">
                <div class="act-n"><?= $vacantesActivas ?> vacante<?= $vacantesActivas > 1 ? 's' : '' ?>
                  activa<?= $vacantesActivas > 1 ? 's' : '' ?></div>
                <div class="act-f">Empresa activa</div>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($talento['precio_desde'])): ?>
            <div class="act-it">
              <div class="act-pt po">🎧</div>
              <div class="act-tx">
                <div class="act-n">Servicio configurado</div>
                <div class="act-f">Apareces en Eventos</div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /grid -->

    <?php if ($tipo === 'candidato' || $subTipo === 'servicio'): ?>
      <?php
      $tieneSelvaVerde = tieneBadge($badgesUsuario, 'Selva Verde');
      $limiteGaleria = $tieneSelvaVerde ? PHP_INT_MAX : 15;
      $puedeSubir = $galeriaTotal < $limiteGaleria;
      ?>
      <!-- ══ GALERÍA DE EVIDENCIAS ══════════════════════════════════ -->
      <div
        style="margin-top:18px;background:var(--card);border:1px solid rgba(39,168,85,.2);border-radius:14px;padding:18px;box-shadow:0 2px 10px rgba(39,168,85,.07)">
        <div
          style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px">
          <div>
            <div
              style="font-size:12px;font-weight:800;color:#6b8f74;text-transform:uppercase;letter-spacing:.6px;margin-bottom:2px">
              📸 Galería de evidencias</div>
            <div style="font-size:12px;color:#3a5a42">
              Fotos y videos de tus servicios para que los clientes vean tu trabajo.
              <?php if (!$tieneSelvaVerde): ?>
                <strong style="color:<?= $galeriaTotal >= 15 ? '#dc2626' : '#374151' ?>"><?= $galeriaTotal ?>/15
                  usados</strong>
              <?php else: ?>
                <span
                  style="background:#dcfce7;color:#166534;padding:2px 10px;border-radius:20px;font-weight:700;font-size:12px">🌿
                  Selva Verde — Ilimitado</span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($puedeSubir): ?>
            <button onclick="abrirModalEvidencia()"
              style="padding:7px 14px;background:linear-gradient(135deg,#1a7a3c,#27a855);color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap">
              ➕ Subir
            </button>
          <?php else: ?>
            <div
              style="padding:10px 16px;background:#fef3c7;border:1px solid #fde68a;border-radius:10px;font-size:13px;color:#92400e">
              🌿 <strong>Límite alcanzado.</strong>
              <a href="mailto:soporte@quibdoconecta.co" style="color:#1f9d55;font-weight:700">Activa Selva Verde</a> para
              ilimitado.
            </div>
          <?php endif; ?>
        </div>

        <?php if (empty($galeriaItems)): ?>
          <div
            style="text-align:center;padding:24px 16px;border:1.5px dashed rgba(39,168,85,.25);border-radius:10px;color:#6b8f74">
            <div style="font-size:32px;margin-bottom:8px">📷</div>
            <div style="font-size:13px;font-weight:600;margin-bottom:4px">Aún no tienes evidencias subidas</div>
            <div style="font-size:12px">Sube fotos o videos de tu trabajo para atraer más clientes.</div>
          </div>
        <?php else: ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px" id="galeriaGrid">
            <?php foreach ($galeriaItems as $gi):
              $isVideo = $gi['tipo'] === 'video';
              $thumb = $isVideo && $gi['url_video']
                ? 'https://img.youtube.com/vi/' . getYoutubeId($gi['url_video']) . '/mqdefault.jpg'
                : ($gi['archivo'] ? 'uploads/galeria/' . htmlspecialchars($gi['archivo']) : '');
              ?>
              <div
                style="position:relative;border-radius:10px;overflow:hidden;background:#f9fafb;border:1px solid #e5e7eb;aspect-ratio:1"
                id="gitem-<?= $gi['id'] ?>">
                <?php if ($isVideo && $gi['url_video']): ?>
                  <a href="<?= htmlspecialchars($gi['url_video']) ?>" target="_blank"
                    style="display:block;height:100%;position:relative">
                    <?php if ($thumb): ?><img src="<?= $thumb ?>" style="width:100%;height:100%;object-fit:cover"
                        loading="lazy"><?php endif; ?>
                    <div
                      style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.3)">
                      <span style="font-size:32px">▶️</span>
                    </div>
                  </a>
                <?php elseif ($gi['archivo']): ?>
                  <?php if ($isVideo): ?>
                    <video src="uploads/galeria/<?= htmlspecialchars($gi['archivo']) ?>"
                      style="width:100%;height:100%;object-fit:cover" controls preload="none"></video>
                  <?php else: ?>
                    <img src="uploads/galeria/<?= htmlspecialchars($gi['archivo']) ?>"
                      style="width:100%;height:100%;object-fit:cover;cursor:pointer" loading="lazy"
                      onclick="verImagenGaleria('uploads/galeria/<?= htmlspecialchars($gi['archivo']) ?>','<?= htmlspecialchars($gi['titulo'] ?? '') ?>')">
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($gi['titulo']): ?>
                  <div
                    style="position:absolute;bottom:0;left:0;right:0;padding:6px 8px;background:rgba(0,0,0,.55);color:#fff;font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($gi['titulo']) ?>
                  </div>
                <?php endif; ?>
                <button onclick="eliminarEvidencia(<?= $gi['id'] ?>,this)"
                  style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.6);border:none;color:#fff;border-radius:6px;padding:4px 7px;font-size:11px;cursor:pointer;line-height:1">🗑</button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>


    <?php if ($tipo === 'candidato' || $subTipo === 'servicio' || !empty($talento['precio_desde'])): ?>
      <!-- ══ SECCIONES DE PERFIL EXTENDIDO ══════════════════════ -->
      <div style="max-width:1200px;margin:0 auto;padding:0 36px 28px">

        <!-- ── EDUCACIÓN ── -->
        <div class="psec" id="psec-educacion">
          <div class="psec-head">
            <span class="psec-tit">🎓 Educación</span>
            <div class="psec-btns">
              <button class="psec-btn" title="Agregar" onclick="abrirFormEdu()">＋</button>
              <button class="psec-btn" title="Editar" onclick="abrirFormEdu()">✏️</button>
            </div>
          </div>
          <div class="psec-list" id="edu-list">
            <div style="text-align:center;padding:18px 0;color:#6b8f74;font-size:12px">
              <div style="font-size:24px;margin-bottom:6px">🎓</div>
              Agrega tu educación para que las empresas conozcan tu formación.
              <br><button onclick="abrirFormEdu()"
                style="margin-top:12px;padding:8px 20px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s">+
                Agregar educación</button>
            </div>
          </div>
        </div>

        <!-- ── LICENCIAS Y CERTIFICACIONES ── -->
        <div class="psec" id="psec-cert">
          <div class="psec-head">
            <span class="psec-tit">🏅 Licencias y certificaciones</span>
            <div class="psec-btns">
              <button class="psec-btn" title="Agregar" onclick="abrirFormCert()">＋</button>
              <button class="psec-btn" title="Editar" onclick="abrirFormCert()">✏️</button>
            </div>
          </div>
          <div class="psec-list" id="cert-list">
            <div style="text-align:center;padding:18px 0;color:#6b8f74;font-size:12px">
              <div style="font-size:24px;margin-bottom:6px">🏅</div>
              Agrega tus certificaciones y cursos para destacar tus habilidades.
              <br><button onclick="abrirFormCert()"
                style="margin-top:12px;padding:8px 20px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s">+
                Agregar certificación</button>
            </div>
          </div>
        </div>

        <!-- ── APTITUDES ── -->
        <div class="psec" id="psec-apt">
          <div class="psec-head">
            <span class="psec-tit">⚡ Aptitudes</span>
            <div class="psec-btns">
              <button class="psec-btn" title="Agregar" onclick="abrirFormApt()">＋</button>
              <button class="psec-btn" title="Editar" onclick="abrirFormApt()">✏️</button>
            </div>
          </div>
          <div class="psec-list" id="apt-list">
            <?php
            $skills = trim($talento['skills'] ?? '');
            if ($skills):
              $grupos = [];
              foreach (array_filter(array_map('trim', explode(',', $skills))) as $sk) {
                $grupos[] = $sk;
              }
              ?>
              <div class="apt-grupo">
                <div class="apt-items">
                  <?php foreach ($grupos as $sk): ?>
                    <span class="apt-chip"><span class="apt-chip-ico">🌿</span><?= htmlspecialchars($sk) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <div style="text-align:center;padding:30px 0;color:var(--ink3);font-size:13px">
                <div style="font-size:32px;margin-bottom:8px">⚡</div>
                Agrega tus aptitudes y habilidades clave.
                <br><button onclick="abrirFormApt()"
                  style="margin-top:12px;padding:8px 20px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .2s">+
                  Agregar aptitudes</button>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    <?php endif; ?>

  </div><!-- /contenido -->

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
    // ── MODAL ──
    function abrirModal() { document.getElementById('modalEditar').classList.add('open') }
    function cerrarModal() { document.getElementById('modalEditar').classList.remove('open') }
    document.getElementById('modalEditar').addEventListener('click', e => { if (e.target === document.getElementById('modalEditar')) cerrarModal() });

    // ── ELIMINAR CUENTA ──
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
      // ── PUBLICAR VACANTE ─────────────────────────────────────
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
      // ── HOJA DE VIDA ─────────────────────────────────────────
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

    // Barra de progreso animada
    window.addEventListener('load', () => {
      const b = document.getElementById('progBar');
      if (b) { const w = b.style.width; b.style.width = '0%'; setTimeout(() => { b.style.width = '<?= $pct ?>%' }, 400) }
    });

    function mostrarMsg(t, c) { const e = document.getElementById('editMsg'); e.textContent = t; e.className = 'mmsg ' + c; e.style.display = 'block' }

    // ── ELIMINAR FOTO ──
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

    // ── CROP + FOTO ──
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

    // ── GUARDAR PERFIL ──
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
      fd.append('profesion', document.getElementById('editProfesion')?.value.trim() || '');
      fd.append('bio', document.getElementById('editBio')?.value.trim() || '');
      fd.append('skills', document.getElementById('editSkills')?.value.trim() || '');
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) {
          mostrarMsg('¡Perfil actualizado!', 'success');
          document.getElementById('dNombre').textContent = j.nombre + (j.apellido ? ' ' + j.apellido : '');
          const dc = document.getElementById('dCiudad'); if (dc) dc.textContent = j.ciudad || 'Ciudad no registrada';
          const dt = document.getElementById('dTelefono'); if (dt) dt.textContent = document.getElementById('editTelefono').value.trim() || 'Teléfono no registrado';
          // Actualizar profesión visible en el hero si existe
          if (j.profesion) { const dp = document.querySelector('.hero-pro'); if (dp) dp.textContent = j.profesion; }
          // Preservar foto: sincronizar fotoPreview → heroAvatar, cpAvatar, navAvatar
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

    // ── TOGGLE VISIBILIDAD ──
    async function toggleVis(visible) {
      const chip = document.getElementById('pvBadge');
      const fd = new FormData();
      // Si tiene talento_perfil, actualizar visible
      fd.append('_action', 'toggle_vis');
      fd.append('visible', visible ? '1' : '0');
      chip.textContent = visible ? '🟢 Visible' : '🟡 Oculto';
      chip.className = 'pv-chip ' + (visible ? 'ok' : 'off');
      // Nota: el endpoint para toggle_vis no existe aún en este dashboard.php
      // El admin controla visible_admin; el usuario puede controlar visible (autoocultar)
    }

    // ── NOTIFICACIONES ──
    const notifBtn = document.getElementById('navNotif');
    const notifPanel = document.getElementById('notifPanel');
    const notifDot = document.getElementById('notifDot');
    notifBtn.addEventListener('click', e => { e.stopPropagation(); notifPanel.classList.toggle('open'); });
    document.addEventListener('click', () => notifPanel.classList.remove('open'));

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

    // ── GALERÍA DE EVIDENCIAS ────────────────────────────────────
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
    // ── SECCIONES DE PERFIL EXTENDIDO ──────────────────────────
    // Datos en memoria (localStorage para persistencia local)
    const STORE_KEY = 'qc_perfil_<?= $usuario["id"] ?>';
    let perfilData = { educacion: [], certificaciones: [], aptitudes_bland: '', aptitudes_idiomas: '' };
    try {
      const saved = localStorage.getItem(STORE_KEY);
      if (saved) perfilData = { ...perfilData, ...JSON.parse(saved) };
    } catch (e) { }

    function savePerfilData() {
      try { localStorage.setItem(STORE_KEY, JSON.stringify(perfilData)); } catch (e) { }
    }

    // Renderizar educación
    function renderEdu() {
      const list = document.getElementById('edu-list');
      if (!list) return;
      if (!perfilData.educacion.length) {
        list.innerHTML = `<div style="text-align:center;padding:30px 0;color:var(--ink3);font-size:13px">
        <div style="font-size:32px;margin-bottom:8px">🎓</div>
        Agrega tu educación para que las empresas conozcan tu formación.
        <br><button onclick="abrirFormEdu()" style="margin-top:12px;padding:8px 20px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif">+ Agregar educación</button>
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

    // Renderizar certificaciones
    function renderCert() {
      const list = document.getElementById('cert-list');
      if (!list) return;
      if (!perfilData.certificaciones.length) {
        list.innerHTML = `<div style="text-align:center;padding:30px 0;color:var(--ink3);font-size:13px">
        <div style="font-size:32px;margin-bottom:8px">🏅</div>
        Agrega tus certificaciones y cursos para destacar tus habilidades.
        <br><button onclick="abrirFormCert()" style="margin-top:12px;padding:8px 20px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif">+ Agregar certificación</button>
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

    // Renderizar aptitudes
    function renderApt() {
      const list = document.getElementById('apt-list');
      if (!list) return;
      const tecSkills = '<?= addslashes($talento["skills"] ?? "") ?>';
      const tec = tecSkills ? tecSkills.split(',').map(s => s.trim()).filter(Boolean) : [];
      const bland = perfilData.aptitudes_bland ? perfilData.aptitudes_bland.split(',').map(s => s.trim()).filter(Boolean) : [];
      const idiomas = perfilData.aptitudes_idiomas ? perfilData.aptitudes_idiomas.split(',').map(s => s.trim()).filter(Boolean) : [];
      if (!tec.length && !bland.length && !idiomas.length) {
        list.innerHTML = `<div style="text-align:center;padding:30px 0;color:var(--ink3);font-size:13px">
        <div style="font-size:32px;margin-bottom:8px">⚡</div>
        Agrega tus aptitudes y habilidades clave.
        <br><button onclick="abrirFormApt()" style="margin-top:12px;padding:8px 20px;border:1.5px dashed rgba(39,168,85,.3);border-radius:20px;background:none;color:var(--v2);font-size:13px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif">+ Agregar aptitudes</button>
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

    // Abrir/cerrar modales
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

    // Eliminar
    function eliminarEdu(i) { perfilData.educacion.splice(i, 1); savePerfilData(); renderEdu(); syncEduServidor(); }
    function eliminarCert(i) { perfilData.certificaciones.splice(i, 1); savePerfilData(); renderCert(); syncCertServidor(); }

    // Guardar educación
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

    // Guardar certificación
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

    // Guardar aptitudes
    async function guardarApt() {
      const tec = document.getElementById('apt-tec').value.trim();
      const bland = document.getElementById('apt-bland').value.trim();
      const idiomas = document.getElementById('apt-idiomas').value.trim();
      const msg = document.getElementById('apt-msg');
      perfilData.aptitudes_bland = bland;
      perfilData.aptitudes_idiomas = idiomas;
      savePerfilData();
      // Sync aptitudes_bland e idiomas al servidor
      try {
        const fdApt = new FormData();
        fdApt.append('_action', 'guardar_aptitudes_extra');
        fdApt.append('aptitudes_bland', bland);
        fdApt.append('aptitudes_idiomas', idiomas);
        fetch('dashboard.php', { method: 'POST', body: fdApt }).catch(() => { });
      } catch (e) { }
      // Sync habilidades técnicas al servidor via editar_perfil
      const fd = new FormData();
      fd.append('_action', 'editar_perfil');
      fd.append('nombre', document.getElementById('editNombre')?.value || '<?= addslashes($usuario["nombre"] ?? "") ?>');
      fd.append('apellido', '');
      fd.append('telefono', '');
      fd.append('ciudad', '');
      fd.append('profesion', '');
      fd.append('bio', '');
      fd.append('skills', tec);
      try {
        const r = await fetch('dashboard.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) { msg.textContent = '✅ Aptitudes guardadas.'; msg.className = 'mmsg success'; msg.style.display = 'block'; setTimeout(cerrarFormApt, 1200); renderApt(); }
        else { msg.textContent = '❌ ' + (j.msg || 'Error'); msg.className = 'mmsg error'; msg.style.display = 'block'; }
      } catch (e) { msg.textContent = '❌ Error de conexión'; msg.className = 'mmsg error'; msg.style.display = 'block'; }
    }

    // Cerrar modales con Escape y overlay click
    ['modal-edu', 'modal-cert', 'modal-apt'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
    });

    // Render inicial
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


  <!-- Widget de sesión activa — QuibdóConecta -->
  <script src="js/sesion_widget.js"></script>
</body>

</html>