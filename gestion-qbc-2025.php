<?php
// ============================================================
// gestion-qbc-2025.php — Panel de Administrador QuibdóConecta
// URL secreta: /gestion-qbc-2025.php
// Todo en un archivo — compatible con InfinityFree
// ============================================================
session_start();
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/Php/db.php';
if (file_exists(__DIR__ . '/Php/planes_helper.php'))
  require_once __DIR__ . '/Php/planes_helper.php';

// ─── CÓDIGO DE EMERGENCIA (solo Deivy-x lo sabe) ───────────
define('EMERGENCY_CODE', 'QuibdoAdmin#2026!');
define('EMERGENCY_ADMIN_ID', 2);        // ID de Deivy-x en la BD
define('EMERGENCY_ADMIN_NAME', 'Oscar David');
define('EMERGENCY_NIVEL', 'superadmin');

// ─── AUTENTICACIÓN ADMIN ────────────────────────────────────
$error = '';
$logueado = false;

// Logout
if (isset($_GET['salir'])) {
  unset($_SESSION['admin_id'], $_SESSION['admin_nivel'], $_SESSION['admin_nombre'], $_SESSION['admin_emergencia']);
  header('Location: gestion-qbc-2025.php');
  exit;
}

// Login normal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_login'])) {
  $correo = trim($_POST['correo'] ?? '');
  $pass = trim($_POST['pass'] ?? '');

  // ── Acceso de emergencia ──────────────────────────────────
  if ($pass === EMERGENCY_CODE) {
    $_SESSION['admin_id'] = EMERGENCY_ADMIN_ID;
    $_SESSION['admin_nivel'] = EMERGENCY_NIVEL;
    $_SESSION['admin_nombre'] = EMERGENCY_ADMIN_NAME;
    $_SESSION['admin_emergencia'] = true;
    header('Location: gestion-qbc-2025.php');
    exit;
  }

  // ── Login normal ─────────────────────────────────────────
  try {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.id, u.nombre, u.correo, u.contrasena, u.activo, u.tipo, ar.nivel FROM usuarios u INNER JOIN admin_roles ar ON ar.usuario_id = u.id WHERE u.correo = ? AND u.activo = 1");
    $stmt->execute([$correo]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($pass, $admin['contrasena'])) {
      $_SESSION['admin_id'] = $admin['id'];
      $_SESSION['admin_nivel'] = $admin['nivel'];
      $_SESSION['admin_nombre'] = $admin['nombre'];
      header('Location: gestion-qbc-2025.php');
      exit;
    } else {
      $error = 'Correo o contraseña incorrectos.';
    }
  } catch (Exception $e) {
    $error = 'Error de conexión: ' . $e->getMessage();
  }
}

// Verificar sesión admin
if (isset($_SESSION['admin_id'])) {
  // Sesión de emergencia — no necesita BD
  if (!empty($_SESSION['admin_emergencia'])) {
    $adminUser = [
      'id' => EMERGENCY_ADMIN_ID,
      'nombre' => EMERGENCY_ADMIN_NAME,
      'nivel' => EMERGENCY_NIVEL,
    ];
    $logueado = true;
  } else {
    try {
      $db = getDB();
      // Query base — siempre funciona aunque falten columnas nuevas
      $chk = $db->prepare("SELECT u.id, u.nombre, ar.nivel
                FROM usuarios u INNER JOIN admin_roles ar ON ar.usuario_id = u.id
                WHERE u.id = ? AND u.activo = 1");
      $chk->execute([$_SESSION['admin_id']]);
      $adminUser = $chk->fetch();
      if ($adminUser) {
        $logueado = true;
        // Intentar cargar permisos granulares (pueden no existir en BD antigua)
        try {
          $chk2 = $db->prepare("SELECT ar.perm_usuarios, ar.perm_empleos,
                        ar.perm_verificar, ar.perm_mensajes, ar.perm_pagos, ar.perm_stats,
                        ar.perm_solicitudes, ar.perm_artistas, ar.perm_badges,
                        ar.perm_convocatorias, ar.perm_actividad, ar.perm_auditoria,
                        ar.perm_documentos, ar.perm_talentos, ar.perm_simulador
                        FROM admin_roles ar WHERE ar.usuario_id = ?");
          $chk2->execute([$_SESSION['admin_id']]);
          $permsRow = $chk2->fetch();
          if ($permsRow) {
            $adminUser = array_merge($adminUser, $permsRow);
          }
        } catch (Exception $e2) {
          // Algunas columnas no existen aún — cargar solo las básicas
          try {
            $chk3 = $db->prepare("SELECT ar.perm_usuarios, ar.perm_empleos,
                          ar.perm_verificar, ar.perm_mensajes, ar.perm_stats,
                          ar.perm_badges, ar.perm_convocatorias
                          FROM admin_roles ar WHERE ar.usuario_id = ?");
            $chk3->execute([$_SESSION['admin_id']]);
            $permsRow = $chk3->fetch();
            if ($permsRow) {
              $adminUser = array_merge($adminUser, $permsRow);
            }
          } catch (Exception $e3) {
            // Sin permisos granulares — superadmin igual tiene acceso por $esSA
          }
        }
      }
    } catch (Exception $e) {
    }
  }
}

// ─── ACCIONES AJAX ──────────────────────────────────────────
$action = $_GET['action'] ?? '';
if ($action && $logueado) {
  header('Content-Type: application/json; charset=utf-8');
  $db = getDB();
  $nivel = $_SESSION['admin_nivel'];

  // Cargar permisos granulares para el AJAX
  $ajaxSA = $nivel === 'superadmin';
  $ajaxAD = $nivel === 'admin';
  $ajaxRow = [];
  try {
    $stmtP = $db->prepare("SELECT * FROM admin_roles WHERE usuario_id=?");
    $stmtP->execute([$_SESSION['admin_id']]);
    $ajaxRow = $stmtP->fetch() ?: [];
  } catch (Exception $e) {
    $ajaxRow = [];
  }
  // Todos los permisos — superadmin tiene todo, admin delegado usa sus permisos guardados en BD
  // Si el admin delegado no tiene ninguno configurado aún, se le dan todos por defecto
  $ajaxSinPerms = $ajaxAD && empty(array_filter([
    $ajaxRow['perm_usuarios'] ?? null,
    $ajaxRow['perm_empleos'] ?? null,
    $ajaxRow['perm_badges'] ?? null,
    $ajaxRow['perm_talentos'] ?? null,
  ], fn($v) => $v !== null));
  $ajaxPerms = [
    'usuarios' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_usuarios'])),
    'empleos' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_empleos'])),
    'verificar' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_verificar'])),
    'solicitudes' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_solicitudes'])),
    'mensajes' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_mensajes'])),
    'stats' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_stats'])),
    'badges' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_badges'])),
    'convocatorias' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_convocatorias'])),
    'actividad' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_actividad'])),
    'auditoria' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_auditoria'])),
    'documentos' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_documentos'])),
    'destacar' => $ajaxSA || $ajaxAD, // ambos pueden destacar
    'talentos' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_talentos'])),
    'simulador' => $ajaxSA || $ajaxSinPerms || (!$ajaxSA && $ajaxAD && !empty($ajaxRow['perm_simulador'])),
  ];

  // ── CATÁLOGO DE BADGES ──────────────────────────────────
  if ($action === 'badges_catalogo') {
    // Auto-migrar columna beneficios si no existe
    try {
      $db->query("ALTER TABLE badges_catalog ADD COLUMN beneficios TEXT NULL DEFAULT NULL");
    } catch (Exception $e) { /* ya existe */
    }
    $stmt = $db->query("SELECT * FROM badges_catalog ORDER BY tipo, nombre");
    echo json_encode(['ok' => true, 'badges' => $stmt->fetchAll()]);
    exit;
  }

  // Crear badge
  if ($action === 'badge_crear' && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($nivel, ['superadmin', 'admin'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $emoji = trim($_POST['emoji'] ?? '⭐');
    $desc = trim($_POST['descripcion'] ?? '');
    $color = trim($_POST['color'] ?? '#00e676');
    $tipo = $_POST['tipo'] ?? 'manual';
    $beneficios = trim($_POST['beneficios'] ?? '');
    if (!$nombre) {
      echo json_encode(['ok' => false, 'msg' => 'Nombre requerido']);
      exit;
    }
    try {
      $db->query("ALTER TABLE badges_catalog ADD COLUMN beneficios TEXT NULL DEFAULT NULL");
    } catch (Exception $e) {
    }
    $db->prepare("INSERT INTO badges_catalog (nombre,emoji,descripcion,color,tipo,beneficios) VALUES (?,?,?,?,?,?)")
      ->execute([$nombre, $emoji, $desc, $color, $tipo, $beneficios ?: null]);
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'badge_crear', "Badge '$nombre' creado"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true, 'id' => (int) $db->lastInsertId()]);
    exit;
  }

  // Editar badge
  if ($action === 'badge_editar' && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($nivel, ['superadmin', 'admin'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $emoji = trim($_POST['emoji'] ?? '⭐');
    $desc = trim($_POST['descripcion'] ?? '');
    $color = trim($_POST['color'] ?? '#00e676');
    $tipo = $_POST['tipo'] ?? 'manual';
    $beneficios = trim($_POST['beneficios'] ?? '');
    if (!$id || !$nombre) {
      echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
      exit;
    }
    try {
      $db->query("ALTER TABLE badges_catalog ADD COLUMN beneficios TEXT NULL DEFAULT NULL");
    } catch (Exception $e) {
    }
    $db->prepare("UPDATE badges_catalog SET nombre=?,emoji=?,descripcion=?,color=?,tipo=?,beneficios=? WHERE id=?")
      ->execute([$nombre, $emoji, $desc, $color, $tipo, $beneficios ?: null, $id]);
    echo json_encode(['ok' => true]);
    exit;
  }

  // Eliminar badge del catálogo
  if ($action === 'badge_eliminar' && $_SERVER['REQUEST_METHOD'] === 'POST' && $nivel === 'superadmin') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id)
      $db->prepare("DELETE FROM badges_catalog WHERE id=?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
  }

  // Obtener badges de un usuario
  if ($action === 'usuario_badges') {
    $uid = (int) ($_GET['id'] ?? 0);
    $u = $db->prepare("SELECT badges_custom FROM usuarios WHERE id=?");
    $u->execute([$uid]);
    $row = $u->fetch();
    $asignados = $row && $row['badges_custom'] ? json_decode($row['badges_custom'], true) : [];
    $todos = $db->query("SELECT * FROM badges_catalog WHERE activo=1 ORDER BY tipo,nombre")->fetchAll();
    echo json_encode(['ok' => true, 'asignados' => $asignados, 'catalogo' => $todos]);
    exit;
  }

  // Asignar/quitar badge a usuario
  if ($action === 'badge_toggle' && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($nivel, ['superadmin', 'admin'])) {
    $uid = (int) ($_POST['usuario_id'] ?? 0);
    $badge_id = (int) ($_POST['badge_id'] ?? 0);
    $asignar = (int) ($_POST['asignar'] ?? 0);
    if (!$uid || !$badge_id) {
      echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
      exit;
    }

    $u = $db->prepare("SELECT badges_custom, nombre, apellido FROM usuarios WHERE id=?");
    $u->execute([$uid]);
    $row = $u->fetch();
    $asignados = $row && $row['badges_custom'] ? json_decode($row['badges_custom'], true) : [];

    $b = $db->prepare("SELECT nombre, emoji FROM badges_catalog WHERE id=?");
    $b->execute([$badge_id]);
    $badge = $b->fetch();

    if ($asignar) {
      if (!in_array($badge_id, $asignados))
        $asignados[] = $badge_id;
      $accion = "Asignó badge '{$badge['emoji']} {$badge['nombre']}' a #{$uid} ({$row['nombre']})";
    } else {
      $asignados = array_values(array_filter($asignados, fn($id) => $id !== $badge_id));
      $accion = "Quitó badge '{$badge['emoji']} {$badge['nombre']}' de #{$uid} ({$row['nombre']})";
    }

    $db->prepare("UPDATE usuarios SET badges_custom=? WHERE id=?")->execute([json_encode($asignados), $uid]);
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'badge_toggle', $accion]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true, 'asignados' => $asignados]);
    exit;
  }

  // ── ASIGNAR PLAN DE PAGO (crea/reemplaza badge de plan) ──────
  // Llamar cuando el admin confirma un pago de suscripción.
  // POST: usuario_id, plan ('verde_selva'|'amarillo_oro'|'azul_profundo'|'microempresa'|'semilla')
  if ($action === 'asignar_plan' && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($nivel, ['superadmin', 'admin'])) {
    $uid = (int) ($_POST['usuario_id'] ?? 0);
    $planKey = trim($_POST['plan'] ?? '');
    $planesValidos = ['semilla', 'verde_selva', 'amarillo_oro', 'azul_profundo', 'microempresa'];

    if (!$uid || !in_array($planKey, $planesValidos)) {
      echo json_encode(['ok' => false, 'msg' => 'Datos inválidos (usuario_id o plan).']);
      exit;
    }

    // Nombres exactos de badges de plan en el catálogo
    $planNombres = [
      'semilla' => null,          // sin badge
      'verde_selva' => 'Verde Selva',
      'amarillo_oro' => 'Amarillo Oro',
      'azul_profundo' => 'Azul Profundo',
      'microempresa' => 'Microempresa',
    ];
    $todosPlanesNombres = array_filter(array_values($planNombres));

    // Obtener badges actuales del usuario
    $u = $db->prepare("SELECT badges_custom, nombre FROM usuarios WHERE id=?");
    $u->execute([$uid]);
    $uRow = $u->fetch();
    if (!$uRow) {
      echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado.']);
      exit;
    }
    $asignados = $uRow['badges_custom'] ? json_decode($uRow['badges_custom'], true) : [];
    if (!is_array($asignados))
      $asignados = [];

    // Quitar todos los badges de plan anteriores
    if (!empty($asignados)) {
      $phStr = implode(',', array_fill(0, count($asignados), '?'));
      $bStmt = $db->prepare("SELECT id, nombre FROM badges_catalog WHERE id IN ($phStr)");
      $bStmt->execute($asignados);
      $bRows = $bStmt->fetchAll(PDO::FETCH_ASSOC);
      $idsAQuitar = [];
      foreach ($bRows as $br) {
        if (in_array($br['nombre'], $todosPlanesNombres)) {
          $idsAQuitar[] = $br['id'];
        }
      }
      $asignados = array_values(array_diff($asignados, $idsAQuitar));
    }

    // Asignar nuevo badge de plan (si no es semilla)
    $nuevoBadgeNombre = $planNombres[$planKey] ?? null;
    if ($nuevoBadgeNombre) {
      $bFind = $db->prepare("SELECT id FROM badges_catalog WHERE nombre=? AND activo=1 LIMIT 1");
      $bFind->execute([$nuevoBadgeNombre]);
      $bId = (int) $bFind->fetchColumn();
      if (!$bId) {
        // Crear badge de plan automáticamente si no existe
        $coloresPlan = [
          'Verde Selva' => '#00e676',
          'Amarillo Oro' => '#ffc107',
          'Azul Profundo' => '#2196f3',
          'Microempresa' => '#9c27b0',
        ];
        $color = $coloresPlan[$nuevoBadgeNombre] ?? '#00e676';
        $db->prepare("INSERT INTO badges_catalog (nombre, emoji, descripcion, color, tipo, activo) VALUES (?,?,?,?,'pago',1)")
          ->execute([$nuevoBadgeNombre, '⭐', "Plan $nuevoBadgeNombre activo", $color]);
        $bId = (int) $db->lastInsertId();
      }
      if (!in_array($bId, $asignados))
        $asignados[] = $bId;
    }

    $db->prepare("UPDATE usuarios SET badges_custom=? WHERE id=?")->execute([json_encode($asignados), $uid]);
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")
        ->execute([$_SESSION['admin_id'], 'asignar_plan', "Plan '$planKey' asignado a #{$uid} ({$uRow['nombre']})"]);
    } catch (Exception $e) {
    }

    echo json_encode(['ok' => true, 'plan' => $planKey, 'badges' => $asignados, 'msg' => "Plan $planKey asignado correctamente."]);
    exit;
  }


  // Métricas del dashboard
  // Subir foto de perfil del admin
  if ($action === 'subir_foto_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== 0) {
      echo json_encode(['ok' => false, 'msg' => 'No se recibió imagen']);
      exit;
    }
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
      echo json_encode(['ok' => false, 'msg' => 'Solo JPG, PNG o WEBP']);
      exit;
    }
    if ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
      echo json_encode(['ok' => false, 'msg' => 'Máximo 2MB']);
      exit;
    }
    $dir = __DIR__ . '/uploads/fotos/';
    if (!is_dir($dir))
      mkdir($dir, 0755, true);
    $nombre = 'admin_' . $_SESSION['admin_id'] . '_' . time() . '.' . $ext;
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $nombre)) {
      $db->prepare("UPDATE usuarios SET foto = ? WHERE id = ?")->execute([$nombre, $_SESSION['admin_id']]);
      echo json_encode(['ok' => true, 'foto' => $nombre]);
    } else {
      echo json_encode(['ok' => false, 'msg' => 'Error al guardar la imagen']);
    }
    exit;
  }

  // Metricas del dashboard
  if ($action === 'metricas') {
    $stats = [];
    $stats['total_usuarios'] = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    $stats['usuarios_hoy'] = $db->query("SELECT COUNT(*) FROM usuarios WHERE DATE(creado_en) = CURDATE()")->fetchColumn();
    $stats['total_empleos'] = $db->query("SELECT COUNT(*) FROM empleos")->fetchColumn();
    $stats['empleos_activos'] = $db->query("SELECT COUNT(*) FROM empleos WHERE activo = 1")->fetchColumn();
    $stats['total_candidatos'] = $db->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'candidato'")->fetchColumn();
    $stats['total_empresas'] = $db->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'empresa'")->fetchColumn();
    $stats['verificaciones_pendientes'] = $db->query("SELECT COUNT(*) FROM verificaciones WHERE estado = 'pendiente'")->fetchColumn();
    try {
      $stats['solicitudes_pendientes'] = $db->query("SELECT COUNT(*) FROM solicitudes_ingreso WHERE estado = 'pendiente'")->fetchColumn();
    } catch (Exception $e) {
      $stats['solicitudes_pendientes'] = 0;
    }
    $stats['total_mensajes'] = $db->query("SELECT COUNT(*) FROM mensajes")->fetchColumn();
    $stats['convocatorias'] = $db->query("SELECT COUNT(*) FROM convocatorias")->fetchColumn();
    // Registros últimos 7 días
    $stats['registros_semana'] = [];
    $reg = $db->query("SELECT DATE(creado_en) as dia, COUNT(*) as total FROM usuarios WHERE creado_en >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(creado_en) ORDER BY dia ASC");
    foreach ($reg->fetchAll() as $r)
      $stats['registros_semana'][$r['dia']] = (int) $r['total'];
    try {
      $stats['total_servicios'] = $db->query("SELECT COUNT(DISTINCT usuario_id) FROM talento_perfil WHERE (tipo_servicio IS NOT NULL AND tipo_servicio<>'') OR precio_desde IS NOT NULL")->fetchColumn();
    } catch (Exception $e) {
      $stats['total_servicios'] = 0;
    }
    try {
      $stats['total_negocios'] = $db->query("SELECT COUNT(DISTINCT usuario_id) FROM negocios_locales WHERE visible_admin=1")->fetchColumn();
    } catch (Exception $e) {
      $stats['total_negocios'] = 0;
    }
    echo json_encode(['ok' => true, 'stats' => $stats]);
    exit;
  }

  // Listar usuarios
  if ($action === 'usuarios') {
    if (!$ajaxPerms['usuarios']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $buscar = trim($_GET['q'] ?? '');
    $tipo = $_GET['tipo'] ?? '';
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $where = "WHERE 1=1";
    $params = [];
    if ($buscar) {
      $where .= " AND (nombre LIKE ? OR apellido LIKE ? OR correo LIKE ? OR cedula LIKE ?)";
      $b = "%$buscar%";
      $params = array_merge($params, [$b, $b, $b, $b]);
    }
    if ($tipo) {
      $where .= " AND tipo = ?";
      $params[] = $tipo;
    }
    $total = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM usuarios u LEFT JOIN talento_perfil tp ON tp.id = (SELECT MAX(id) FROM talento_perfil WHERE usuario_id = u.id) $where");
    $total->execute($params);
    $stmt = $db->prepare("SELECT u.id, u.nombre, u.apellido, u.correo, u.tipo, u.activo, u.verificado, u.cedula, u.telefono, u.fecha_nacimiento, u.ciudad, u.ultima_sesion, u.ultima_salida, u.creado_en, u.foto, COALESCE(tp.visible_admin,1) AS en_talentos, tp.profesion FROM usuarios u LEFT JOIN talento_perfil tp ON tp.id = (SELECT MAX(id) FROM talento_perfil WHERE usuario_id = u.id) $where ORDER BY u.creado_en DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'usuarios' => $stmt->fetchAll(), 'total' => (int) $total->fetchColumn(), 'page' => $page]);
    exit;
  }

  // Activar/desactivar usuario
  if ($action === 'toggle_usuario' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['usuarios']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $uid = (int) ($_POST['id'] ?? 0);
    $activo = (int) ($_POST['activo'] ?? 0);
    if ($uid) {
      $db->prepare("UPDATE usuarios SET activo = ? WHERE id = ?")->execute([$activo, $uid]);
      // Auditoría
      $db->prepare("INSERT INTO admin_auditoria (admin_id, accion, detalle) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE accion=accion")->execute([$_SESSION['admin_id'], 'toggle_usuario', "Usuario $uid activo=$activo"]);
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // Eliminar usuario permanentemente
  if ($action === 'eliminar_usuario' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['usuarios'] || $nivel !== 'superadmin') {
      echo json_encode(['ok' => false, 'msg' => 'Solo el superadmin puede eliminar usuarios.']);
      exit;
    }
    $uid = (int) ($_POST['id'] ?? 0);
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
      exit;
    }
    // Proteger: no eliminar admins ni al propio admin logueado
    $tipoU = $db->prepare("SELECT tipo FROM usuarios WHERE id=?");
    $tipoU->execute([$uid]);
    $rowU = $tipoU->fetch();
    if (!$rowU) {
      echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado.']);
      exit;
    }
    if ($rowU['tipo'] === 'admin') {
      echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar una cuenta de admin desde aquí.']);
      exit;
    }
    if ($uid === (int) $_SESSION['admin_id']) {
      echo json_encode(['ok' => false, 'msg' => 'No puedes eliminar tu propia cuenta.']);
      exit;
    }
    try {
      // Borrar tablas sin CASCADE
      foreach (['perfiles_empresa', 'sesiones', 'negocios_locales', 'talento_galeria', 'talento_educacion', 'talento_certificaciones', 'talento_experiencia', 'perfil_vistas'] as $tabla) {
        try {
          $db->prepare("DELETE FROM $tabla WHERE usuario_id=?")->execute([$uid]);
        } catch (Exception $e) {
        }
      }
      // Borrar fotos/logos del disco
      $fotoRow = $db->prepare("SELECT foto FROM usuarios WHERE id=?");
      $fotoRow->execute([$uid]);
      $fotoFile = $fotoRow->fetchColumn();
      if ($fotoFile && !str_starts_with($fotoFile, 'http') && file_exists(__DIR__ . '/uploads/fotos/' . $fotoFile)) {
        @unlink(__DIR__ . '/uploads/fotos/' . $fotoFile);
      }
      $logoRow = $db->prepare("SELECT logo FROM perfiles_empresa WHERE usuario_id=? ORDER BY id DESC LIMIT 1");
      $logoRow->execute([$uid]);
      $logoFile = $logoRow->fetchColumn();
      if ($logoFile && file_exists(__DIR__ . '/uploads/logos/' . $logoFile)) {
        @unlink(__DIR__ . '/uploads/logos/' . $logoFile);
      }
      // Borrar usuario (CASCADE limpia el resto)
      $db->prepare("DELETE FROM usuarios WHERE id=?")->execute([$uid]);
      // Auditoría
      try {
        $db->prepare("INSERT INTO admin_auditoria (admin_id, accion, detalle) VALUES (?,?,?)")->execute([$_SESSION['admin_id'], 'eliminar_usuario', "Usuario $uid eliminado permanentemente"]);
      } catch (Exception $e) {
      }
      echo json_encode(['ok' => true]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
    }
    exit;
  }

  // Verificaciones pendientes
  if ($action === 'verificaciones') {
    if (!$ajaxPerms['verificar']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $estado = $_GET['estado'] ?? 'pendiente';
    $stmt = $db->prepare("
            SELECT v.id, v.usuario_id, v.estado,
                   v.doc_url     AS archivo,
                   v.foto_doc_url AS foto_doc,
                   v.tipo_doc    AS tipo_documento,
                   v.nota_rechazo AS nota_admin,
                   v.actualizado AS revisado_en,
                   v.revisado_por,
                   v.creado_en,
                   u.nombre, u.apellido, u.correo, u.tipo
            FROM verificaciones v
            INNER JOIN usuarios u ON u.id = v.usuario_id
            WHERE v.estado = ?
            ORDER BY v.creado_en DESC LIMIT 50
        ");
    $stmt->execute([$estado]);
    echo json_encode(['ok' => true, 'verificaciones' => $stmt->fetchAll()]);
    exit;
  }

  // Aprobar/rechazar verificación
  if ($action === 'resolver_verificacion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['verificar']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $vid = (int) ($_POST['id'] ?? 0);
    $estado = trim($_POST['estado'] ?? '');
    $nota = trim($_POST['nota'] ?? '');
    if (!$vid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    if (!in_array($estado, ['aprobado', 'rechazado'])) {
      echo json_encode(['ok' => false, 'msg' => 'Estado inválido']);
      exit;
    }
    try {
      // 1. Actualizar estado de la verificación
      $db->prepare("UPDATE verificaciones SET estado=?, nota_rechazo=?, revisado_por=?, actualizado=NOW() WHERE id=?")
        ->execute([$estado, $nota, $_SESSION['admin_id'], $vid]);

      // 2. Si aprobado: marcar usuario verificado y asignar badge
      if ($estado === 'aprobado') {
        $stmtUid = $db->prepare("SELECT usuario_id FROM verificaciones WHERE id=?");
        $stmtUid->execute([$vid]);
        $rowUid = $stmtUid->fetch();
        if ($rowUid) {
          $uidTarget = $rowUid['usuario_id'];
          // Marcar verificado
          $db->prepare("UPDATE usuarios SET verificado=1 WHERE id=?")->execute([$uidTarget]);
          // Intentar asignar badge Verificado (si existe la tabla y el badge)
          try {
            $stmtB = $db->prepare("SELECT id FROM badges_catalog WHERE nombre='Verificado' LIMIT 1");
            $stmtB->execute();
            $badge = $stmtB->fetch();
            if ($badge) {
              $stmtCur = $db->prepare("SELECT badges_custom FROM usuarios WHERE id=?");
              $stmtCur->execute([$uidTarget]);
              $cur = $stmtCur->fetchColumn();
              $arr = ($cur && $cur !== 'null') ? json_decode($cur, true) : [];
              if (!is_array($arr))
                $arr = [];
              if (!in_array((int) $badge['id'], $arr)) {
                $arr[] = (int) $badge['id'];
                $db->prepare("UPDATE usuarios SET badges_custom=? WHERE id=?")
                  ->execute([json_encode($arr), $uidTarget]);
              }
            }
          } catch (Exception $eBadge) { /* badge assignment failed silently */
          }
        }
      }
      // 3. Auditoría (opcional, no bloquea)
      try {
        $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")
          ->execute([$_SESSION['admin_id'], 'resolver_verificacion', "Verificacion #$vid -> $estado"]);
      } catch (Exception $eAud) {
      }

      echo json_encode(['ok' => true, 'estado' => $estado]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => 'Error BD: ' . $e->getMessage()]);
    }
    exit;
  }

  // ── REPOSITORIO DE DOCUMENTOS (solo superadmin y admin) ───────
  if ($action === 'documentos' && $ajaxPerms['documentos']) {
    $buscar = trim($_GET['q'] ?? '');
    $tipo = trim($_GET['tipo'] ?? '');
    $estado = trim($_GET['estado'] ?? '');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Asegurar columna eliminado
    try {
      $db->exec("ALTER TABLE verificaciones ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {
    }

    $where = "WHERE (v.eliminado IS NULL OR v.eliminado=0)";
    $params = [];
    if ($buscar) {
      $where .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.correo LIKE ?)";
      $b = "%$buscar%";
      $params = array_merge($params, [$b, $b, $b]);
    }
    if ($tipo) {
      $where .= " AND v.tipo_doc = ?";
      $params[] = $tipo;
    }
    if ($estado) {
      $where .= " AND v.estado = ?";
      $params[] = $estado;
    }

    // Total
    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM verificaciones v INNER JOIN usuarios u ON u.id = v.usuario_id $where");
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();

    // Registros
    $stmtDocs = $db->prepare("
            SELECT v.id, v.usuario_id, v.estado,
                   v.doc_url, v.foto_doc_url, v.tipo_doc,
                   v.nota_rechazo, v.revisado_por, v.actualizado, v.creado_en,
                   u.nombre, u.apellido, u.correo, u.tipo AS user_tipo
            FROM verificaciones v
            INNER JOIN usuarios u ON u.id = v.usuario_id
            $where
            ORDER BY v.creado_en DESC
            LIMIT $limit OFFSET $offset
        ");
    $stmtDocs->execute($params);
    $docs = $stmtDocs->fetchAll();

    // Stats globales
    $stats = [];
    $stmtStats = $db->query("SELECT estado, COUNT(*) as total FROM verificaciones WHERE (eliminado IS NULL OR eliminado=0) GROUP BY estado");
    foreach ($stmtStats->fetchAll() as $row)
      $stats[$row['estado']] = (int) $row['total'];
    $stats['total'] = array_sum($stats);
    $stats['papelera'] = (int) $db->query("SELECT COUNT(*) FROM verificaciones WHERE eliminado=1")->fetchColumn();

    echo json_encode(['ok' => true, 'docs' => $docs, 'total' => $total, 'page' => $page, 'limit' => $limit, 'stats' => $stats]);
    exit;
  }

  // ── ELIMINAR DOCUMENTO (soft-delete) ────────────────────────
  if ($action === 'eliminar_documento' && $_SERVER['REQUEST_METHOD'] === 'POST' && $ajaxPerms['documentos']) {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    // Añadir columna si no existe
    try {
      $db->exec("ALTER TABLE verificaciones ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {
    }
    try {
      $db->exec("ALTER TABLE verificaciones ADD COLUMN IF NOT EXISTS eliminado_en DATETIME DEFAULT NULL");
    } catch (Exception $e) {
    }
    try {
      $db->exec("ALTER TABLE verificaciones ADD COLUMN IF NOT EXISTS eliminado_por INT DEFAULT NULL");
    } catch (Exception $e) {
    }
    $db->prepare("UPDATE verificaciones SET eliminado=1, eliminado_en=NOW(), eliminado_por=? WHERE id=?")->execute([$_SESSION['admin_id'], $id]);
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'eliminar_documento', "Documento #$id enviado a papelera"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // ── PAPELERA: listar documentos eliminados ───────────────────
  if ($action === 'papelera_documentos' && $ajaxPerms['documentos']) {
    try {
      $db->exec("ALTER TABLE verificaciones ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) DEFAULT 0");
    } catch (Exception $e) {
    }
    try {
      $db->exec("ALTER TABLE verificaciones ADD COLUMN IF NOT EXISTS eliminado_en DATETIME DEFAULT NULL");
    } catch (Exception $e) {
    }
    try {
      $db->exec("ALTER TABLE verificaciones ADD COLUMN IF NOT EXISTS eliminado_por INT DEFAULT NULL");
    } catch (Exception $e) {
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $stmt = $db->prepare("SELECT v.id, v.usuario_id, v.estado, v.doc_url, v.foto_doc_url, v.tipo_doc, v.creado_en, v.eliminado_en,
                          u.nombre, u.apellido, u.correo
                          FROM verificaciones v INNER JOIN usuarios u ON u.id=v.usuario_id
                          WHERE v.eliminado=1 ORDER BY v.eliminado_en DESC LIMIT $limit OFFSET $offset");
    $stmt->execute();
    $docs = $stmt->fetchAll();
    $total = (int) $db->query("SELECT COUNT(*) FROM verificaciones WHERE eliminado=1")->fetchColumn();
    echo json_encode(['ok' => true, 'docs' => $docs, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    exit;
  }

  // ── RESTAURAR DOCUMENTO ──────────────────────────────────────
  if ($action === 'restaurar_documento' && $_SERVER['REQUEST_METHOD'] === 'POST' && $ajaxPerms['documentos']) {
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    $db->prepare("UPDATE verificaciones SET eliminado=0, eliminado_en=NULL, eliminado_por=NULL WHERE id=?")->execute([$id]);
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'restaurar_documento', "Documento #$id restaurado"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // ── VACIAR PAPELERA ──────────────────────────────────────────
  if ($action === 'vaciar_papelera' && $_SERVER['REQUEST_METHOD'] === 'POST' && $nivel === 'superadmin') {
    $count = (int) $db->query("SELECT COUNT(*) FROM verificaciones WHERE eliminado=1")->fetchColumn();
    $db->exec("DELETE FROM verificaciones WHERE eliminado=1");
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'vaciar_papelera', "Papelera vaciada — $count documentos eliminados permanentemente"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true, 'eliminados' => $count]);
    exit;
  }

  // ── BUSCAR USUARIO POR CORREO (para asignar roles) ───────────
  if ($action === 'buscar_usuario_correo' && $nivel === 'superadmin') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 3) {
      echo json_encode(['ok' => true, 'usuarios', []]);
      exit;
    }
    $stmt = $db->prepare("SELECT u.id, u.nombre, u.apellido, u.correo, u.tipo,
                          (SELECT ar.nivel FROM admin_roles ar WHERE ar.usuario_id=u.id LIMIT 1) as rol_actual
                          FROM usuarios u WHERE (u.correo LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ?) AND u.activo=1 LIMIT 8");
    $b = "%$q%";
    $stmt->execute([$b, $b, $b]);
    echo json_encode(['ok' => true, 'usuarios' => $stmt->fetchAll()]);
    exit;
  }

  // ── SOLICITUDES DE INGRESO ──────────────────────────────────
  if ($action === 'solicitudes') {
    if (!$ajaxPerms['solicitudes']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $estado = $_GET['estado'] ?? 'pendiente';
    $stmt = $db->prepare("SELECT * FROM solicitudes_ingreso WHERE estado=? ORDER BY creado_en DESC LIMIT 100");
    $stmt->execute([$estado]);
    echo json_encode(['ok' => true, 'solicitudes' => $stmt->fetchAll()]);
    exit;
  }

  if ($action === 'resolver_solicitud' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['solicitudes']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $sid = (int) ($_POST['id'] ?? 0);
    $estado = trim($_POST['estado'] ?? '');
    $nota = trim($_POST['nota'] ?? '');
    if (!$sid || !in_array($estado, ['aprobado', 'rechazado'])) {
      echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
      exit;
    }
    // Leer datos ANTES de sobrescribir nota_admin con la nota del admin
    $s = $db->prepare("SELECT * FROM solicitudes_ingreso WHERE id=?");
    $s->execute([$sid]);
    $sol = $s->fetch();

    // Marcar solicitud
    $db->prepare("UPDATE solicitudes_ingreso SET estado=?, nota_admin=?, revisado_en=NOW(), revisado_por=? WHERE id=?")
      ->execute([$estado, $nota, $_SESSION['admin_id'], $sid]);

    if ($estado === 'aprobado') {
      if ($sol) {
        // Verificar que el correo no exista ya
        $chk = $db->prepare("SELECT id FROM usuarios WHERE correo=?");
        $chk->execute([$sol['correo']]);
        if ($chk->fetch()) {
          echo json_encode(['ok' => false, 'msg' => 'El correo ya tiene una cuenta activa']);
          exit;
        }
        // Crear la cuenta
        $db->prepare("INSERT INTO usuarios (nombre,apellido,correo,contrasena,telefono,ciudad,tipo,fecha_nacimiento,activo,creado_en) VALUES (?,?,?,?,?,?,?,?,1,NOW())")
          ->execute([$sol['nombre'], $sol['apellido'], $sol['correo'], $sol['contrasena_hash'], $sol['telefono'], $sol['ciudad'], $sol['tipo'], $sol['fecha_nacimiento']]);
        $newId = $db->lastInsertId();
        if ($sol['tipo'] === 'candidato' || $sol['tipo'] === 'servicio') {
          $db->prepare("INSERT INTO perfiles_candidato (usuario_id) VALUES (?)")->execute([$newId]);
          if ($sol['tipo'] === 'servicio') {
            $extras = json_decode($sol['nota_admin'] ?? '{}', true);
            $db->prepare("INSERT INTO talento_perfil (usuario_id, profesion, precio_desde, descripcion, visible, visible_admin) VALUES (?,?,?,?,1,1)")
              ->execute([$newId, $extras['profesion_tipo'] ?? '', $extras['precio_desde_neg'] ?? null, $extras['descripcion_neg'] ?? '']);
          }
        } elseif ($sol['tipo'] === 'empresa') {
          $db->prepare("INSERT INTO perfiles_empresa (usuario_id,nombre_empresa,sector,nit) VALUES (?,?,?,?)")
            ->execute([$newId, $sol['nombre_empresa'], $sol['sector'], $sol['nit']]);
        } elseif ($sol['tipo'] === 'negocio') {
          $extras = json_decode($sol['nota_admin'] ?? '{}', true);
          $db->prepare("INSERT INTO negocios_locales (usuario_id,nombre_negocio,categoria,whatsapp,descripcion,tipo_negocio,visible,visible_admin) VALUES (?,?,?,?,?,?,1,1)")
            ->execute([$newId, $sol['nombre_empresa'], $extras['categoria_neg'] ?? '', $extras['whatsapp_neg'] ?? '', $extras['descripcion_neg'] ?? '', $extras['tipo_negocio_reg'] ?? 'emp']);
        }
      }
    }
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'solicitud', "Solicitud #$sid -> $estado"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true, 'estado' => $estado]);
    exit;
  }

  if ($action === 'solicitudes_count') {
    $n = $db->query("SELECT COUNT(*) FROM solicitudes_ingreso WHERE estado='pendiente'")->fetchColumn();
    echo json_encode(['ok' => true, 'pendientes' => (int) $n]);
    exit;
  }

  // Empleos
  if ($action === 'empleos') {
    if (!$ajaxPerms['empleos']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $stmt = $db->query("
      SELECT e.*,
             COALESCE(pe.nombre_empresa, u.nombre) AS empresa_nombre,
             COALESCE(e.destacado, 0)               AS destacado
      FROM empleos e
      LEFT JOIN usuarios u           ON u.id = e.empresa_id
      LEFT JOIN perfiles_empresa pe  ON pe.usuario_id = e.empresa_id
      ORDER BY e.destacado DESC, e.creado_en DESC
      LIMIT 50
    ");
    echo json_encode(['ok' => true, 'empleos' => $stmt->fetchAll()]);
    exit;
  }

  // Toggle empleo activo/inactivo
  if ($action === 'toggle_empleo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $eid = (int) ($_POST['id'] ?? 0);
    $activo = (int) ($_POST['activo'] ?? 0);
    if ($eid)
      $db->prepare("UPDATE empleos SET activo = ? WHERE id = ?")->execute([$activo, $eid]);
    echo json_encode(['ok' => true]);
    exit;
  }

  // Toggle empleo destacado en index
  if ($action === 'toggle_empleo_destacado' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['destacar']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $eid = (int) ($_POST['id'] ?? 0);
    $valor = (int) ($_POST['valor'] ?? 0);
    if (!$eid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    // Verifica si la columna existe antes de actualizar (compatibilidad)
    try {
      $db->prepare("UPDATE empleos SET destacado = ? WHERE id = ?")->execute([$valor, $eid]);
      try {
        $db->prepare("INSERT INTO admin_auditoria (admin_id, accion, detalle, creado_en) VALUES (?,?,?,NOW())")
          ->execute([
            $_SESSION['admin_id'],
            'toggle_empleo_destacado',
            ($valor ? "Destacó" : "Quitó destacado de") . " empleo #$eid en index"
          ]);
      } catch (Exception $e) {
      }
      echo json_encode(['ok' => true]);
    } catch (Exception $e) {
      // Columna no existe todavía — guiar al admin
      echo json_encode(['ok' => false, 'msg' => 'Ejecuta migracion_empleos_destacado.sql primero']);
    }
    exit;
  }

  // Convocatorias
  if ($action === 'convocatorias') {
    if (!$ajaxPerms['convocatorias']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $origen = trim($_GET['origen'] ?? 'todas');
    $where = "WHERE 1=1";
    $params = [];
    if ($origen === 'pendiente') {
      $where .= " AND activo=0 AND origen='empresa'";
    } elseif ($origen === 'empresa') {
      $where .= " AND origen='empresa'";
    } elseif ($origen === 'admin') {
      $where .= " AND origen='admin'";
    }
    $stmt = $db->prepare("SELECT c.*, u.nombre AS empresa_nombre, u.correo AS empresa_correo
        FROM convocatorias c
        LEFT JOIN usuarios u ON u.id = c.usuario_id
        $where ORDER BY c.activo ASC, c.creado_en DESC LIMIT 60");
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'convocatorias' => $stmt->fetchAll()]);
    exit;
  }

  // ── APROBAR / RECHAZAR convocatoria de empresa ───────────────
  if ($action === 'conv_aprobar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['convocatorias']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $cid = (int) ($_POST['id'] ?? 0);
    $activo = (int) ($_POST['activo'] ?? 1); // 1=aprobar, -1=rechazar(borrar)
    if (!$cid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    if ($activo === -1) {
      $db->prepare("DELETE FROM convocatorias WHERE id=? AND origen='empresa'")->execute([$cid]);
      try {
        $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'conv_rechazar', "Rechazó convocatoria #$cid"]);
      } catch (Exception $e) {
      }
    } else {
      $db->prepare("UPDATE convocatorias SET activo=1, aprobado_por=?, aprobado_en=NOW() WHERE id=?")->execute([$_SESSION['admin_id'], $cid]);
      try {
        $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'conv_aprobar', "Aprobó convocatoria #$cid"]);
      } catch (Exception $e) {
      }
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // ── TOGGLE activo de convocatoria (admin directo) ─────────────
  if ($action === 'conv_toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['convocatorias']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $cid = (int) ($_POST['id'] ?? 0);
    $valor = (int) ($_POST['activo'] ?? 0);
    if (!$cid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    $db->prepare("UPDATE convocatorias SET activo=? WHERE id=?")->execute([$valor, $cid]);
    echo json_encode(['ok' => true]);
    exit;
  }

  // Ver contraseña de usuario (solo superadmin y admin delegado)
  if ($action === 'ver_contrasena' && in_array($nivel, ['superadmin', 'admin'])) {
    $uid = (int) ($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT nombre, apellido, correo, contrasena FROM usuarios WHERE id = ?");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();
    if (!$u) {
      echo json_encode(['ok' => false, 'msg' => 'Usuario no encontrado']);
      exit;
    }
    // Registrar en auditoría
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'ver_contrasena', "Vio hash de usuario #$uid ({$u['correo']})"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true, 'hash' => $u['contrasena'], 'nombre' => $u['nombre'] . ' ' . $u['apellido'], 'correo' => $u['correo']]);
    exit;
  }

  // Cambiar contraseña de usuario (solo superadmin y admin delegado)
  if ($action === 'cambiar_contrasena' && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($nivel, ['superadmin', 'admin'])) {
    $uid = (int) ($_POST['id'] ?? 0);
    $nueva = trim($_POST['nueva'] ?? '');
    $confirma = trim($_POST['confirma'] ?? '');
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    if (strlen($nueva) < 8) {
      echo json_encode(['ok' => false, 'msg' => 'Mínimo 8 caracteres']);
      exit;
    }
    if ($nueva !== $confirma) {
      echo json_encode(['ok' => false, 'msg' => 'Las contraseñas no coinciden']);
      exit;
    }
    $hash = password_hash($nueva, PASSWORD_BCRYPT);
    $db->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?")->execute([$hash, $uid]);
    $info = $db->prepare("SELECT correo FROM usuarios WHERE id = ?");
    $info->execute([$uid]);
    $correoU = $info->fetchColumn();
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'cambiar_contrasena', "Cambió contraseña de usuario #$uid ($correoU)"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // Obtener permisos de un admin delegado
  if ($action === 'get_permisos' && $nivel === 'superadmin') {
    $uid = (int) ($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT ar.*, u.nombre, u.apellido, u.correo FROM admin_roles ar INNER JOIN usuarios u ON u.id = ar.usuario_id WHERE ar.usuario_id = ?");
    $stmt->execute([$uid]);
    echo json_encode(['ok' => true, 'permisos' => $stmt->fetch()]);
    exit;
  }

  // Actualizar permisos granulares (solo superadmin)
  if ($action === 'actualizar_permisos' && $_SERVER['REQUEST_METHOD'] === 'POST' && $nivel === 'superadmin') {
    $uid = (int) ($_POST['usuario_id'] ?? 0);
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }

    // Asegurar que existan todas las columnas (por si no se ejecutó el SQL de migración)
    $colsNuevas = [
      'perm_solicitudes' => "ALTER TABLE admin_roles ADD COLUMN IF NOT EXISTS perm_solicitudes TINYINT(1) DEFAULT 0",
      'perm_actividad' => "ALTER TABLE admin_roles ADD COLUMN IF NOT EXISTS perm_actividad TINYINT(1) DEFAULT 0",
      'perm_auditoria' => "ALTER TABLE admin_roles ADD COLUMN IF NOT EXISTS perm_auditoria TINYINT(1) DEFAULT 0",
      'perm_documentos' => "ALTER TABLE admin_roles ADD COLUMN IF NOT EXISTS perm_documentos TINYINT(1) DEFAULT 0",
      'perm_simulador' => "ALTER TABLE admin_roles ADD COLUMN IF NOT EXISTS perm_simulador TINYINT(1) DEFAULT 0",
    ];
    foreach ($colsNuevas as $col => $sql) {
      try {
        $cols = $db->query("SHOW COLUMNS FROM admin_roles LIKE '$col'")->fetchAll();
        if (empty($cols)) {
          $db->exec($sql);
        }
      } catch (Exception $e) {
      }
    }

    // Solo actualizar columnas que existen en la BD
    $todosLosCampos = [
      'perm_solicitudes',
      'perm_usuarios',
      'perm_empleos',
      'perm_verificar',
      'perm_mensajes',
      'perm_pagos',
      'perm_stats',
      'perm_artistas',
      'perm_badges',
      'perm_convocatorias',
      'perm_talentos',
      'perm_actividad',
      'perm_auditoria',
      'perm_documentos',
      'perm_simulador'
    ];
    $colsExistentes = [];
    try {
      $colsDB = $db->query("SHOW COLUMNS FROM admin_roles")->fetchAll(PDO::FETCH_COLUMN);
      $colsExistentes = array_intersect($todosLosCampos, $colsDB);
    } catch (Exception $e) {
      $colsExistentes = [
        'perm_usuarios',
        'perm_empleos',
        'perm_verificar',
        'perm_mensajes',
        'perm_pagos',
        'perm_stats',
        'perm_artistas',
        'perm_badges',
        'perm_convocatorias'
      ];
    }

    $sets = [];
    $vals = [];
    foreach ($colsExistentes as $c) {
      $sets[] = "`$c` = ?";
      $vals[] = isset($_POST[$c]) ? 1 : 0;
    }
    if (!empty($sets)) {
      $vals[] = $uid;
      $db->prepare("UPDATE admin_roles SET " . implode(',', $sets) . " WHERE usuario_id = ?")->execute($vals);
    }
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'actualizar_permisos', "Actualizó permisos de admin #$uid"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // Ver código de emergencia actual (solo superadmin)
  if ($action === 'get_emergency_code' && $nivel === 'superadmin') {
    echo json_encode(['ok' => true, 'code' => EMERGENCY_CODE]);
    exit;
  }

  // Cambiar código de emergencia (reescribe el archivo)
  if ($action === 'cambiar_emergency_code' && $_SERVER['REQUEST_METHOD'] === 'POST' && $nivel === 'superadmin') {
    $nuevo = trim($_POST['codigo'] ?? '');
    if (strlen($nuevo) < 10) {
      echo json_encode(['ok' => false, 'msg' => 'Mínimo 10 caracteres']);
      exit;
    }
    $archivo = __FILE__;
    $contenido = file_get_contents($archivo);
    // Reemplazar solo la línea del define
    $contenido = preg_replace(
      "/define\('EMERGENCY_CODE',\s*'[^']*'\);/",
      "define('EMERGENCY_CODE', '" . addslashes($nuevo) . "');",
      $contenido
    );
    if (file_put_contents($archivo, $contenido) !== false) {
      try {
        $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'cambiar_emergency_code', 'Código de emergencia actualizado']);
      } catch (Exception $e) {
      }
      echo json_encode(['ok' => true]);
    } else {
      echo json_encode(['ok' => false, 'msg' => 'No se pudo escribir el archivo. Verifica permisos.']);
    }
    exit;
  }

  // Quitar rol
  if ($action === 'quitar_rol' && $_SERVER['REQUEST_METHOD'] === 'POST' && $nivel === 'superadmin') {
    $uid = (int) ($_POST['usuario_id'] ?? 0);
    // No puede quitarse a sí mismo ni al superadmin original
    $chkSup = $db->prepare("SELECT nivel FROM admin_roles WHERE usuario_id = ?");
    $chkSup->execute([$uid]);
    $rolActual = $chkSup->fetchColumn();
    if ($rolActual === 'superadmin') {
      echo json_encode(['ok' => false, 'msg' => 'No puedes quitar el rol superadmin']);
      exit;
    }
    if ($uid)
      $db->prepare("DELETE FROM admin_roles WHERE usuario_id = ?")->execute([$uid]);
    echo json_encode(['ok' => true]);
    exit;
  }

  // Listar roles (solo superadmin)
  if ($action === 'roles' && $nivel === 'superadmin') {
    $stmt = $db->query("SELECT ar.*, u.nombre, u.apellido, u.correo FROM admin_roles ar INNER JOIN usuarios u ON u.id = ar.usuario_id ORDER BY ar.creado_en DESC");
    echo json_encode(['ok' => true, 'roles' => $stmt->fetchAll()]);
    exit;
  }

  // Asignar rol (solo superadmin)
  if ($action === 'asignar_rol' && $_SERVER['REQUEST_METHOD'] === 'POST' && $nivel === 'superadmin') {
    $uid = (int) ($_POST['usuario_id'] ?? 0);
    $nuevo = $_POST['nivel'] ?? '';
    if ($uid && in_array($nuevo, ['admin', 'dev'])) {
      $db->prepare("INSERT INTO admin_roles (usuario_id, nivel) VALUES (?, ?) ON DUPLICATE KEY UPDATE nivel = ?")->execute([$uid, $nuevo, $nuevo]);
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // Editar usuario completo
  if ($action === 'editar_usuario' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int) ($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $ciudad = trim($_POST['ciudad'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $fnac = trim($_POST['fecha_nacimiento'] ?? '');
    $enTalentos = (int) ($_POST['en_talentos'] ?? 0);
    $destacado = (int) ($_POST['destacado'] ?? 0);
    if ($uid && $nombre && in_array($tipo, ['candidato', 'empresa', 'negocio', 'servicio'])) {
      if ($tipo === 'empresa') {
        $db->prepare("UPDATE usuarios SET nombre=?, apellido=?, correo=?, tipo=?, ciudad=?, telefono=?, cedula=?, fecha_empresa=?, fecha_nacimiento=NULL WHERE id=?")
          ->execute([$nombre, $apellido, $correo, $tipo, $ciudad, $telefono, $cedula, $fnac ?: null, $uid]);
      } else {
        $db->prepare("UPDATE usuarios SET nombre=?, apellido=?, correo=?, tipo=?, ciudad=?, telefono=?, cedula=?, fecha_nacimiento=?, fecha_empresa=NULL WHERE id=?")
          ->execute([$nombre, $apellido, $correo, $tipo, $ciudad, $telefono, $cedula, $fnac ?: null, $uid]);
      }
      // Solo superadmin y admin delegado pueden cambiar visibilidad y destacado
      if ($ajaxPerms['destacar']) {
        // UPSERT — el admin es el único que puede crear/editar talento_perfil
        $db->prepare(
          "INSERT INTO talento_perfil (usuario_id, visible, visible_admin, destacado)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE
               visible       = VALUES(visible),
               visible_admin = VALUES(visible_admin),
               destacado     = VALUES(destacado)"
        )->execute([$uid, $enTalentos, $enTalentos, $destacado]);
      }
      try {
        $db->prepare("INSERT INTO admin_auditoria (admin_id, accion, detalle, creado_en) VALUES (?,?,?,NOW())")->execute([$_SESSION['admin_id'], 'editar_usuario', "Usuario #$uid ($tipo) editado"]);
      } catch (Exception $e) {
      }
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // Obtener un usuario por ID
  if ($action === 'get_usuario') {
    $uid = (int) ($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT u.id, u.nombre, u.apellido, u.correo, u.tipo, u.ciudad, u.telefono, u.cedula, u.fecha_nacimiento, u.fecha_empresa, u.activo, u.ultima_sesion, u.ultima_salida, u.creado_en, COALESCE(tp.visible_admin,1) AS en_talentos, COALESCE(tp.destacado,0) AS destacado FROM usuarios u LEFT JOIN talento_perfil tp ON tp.id = (SELECT MAX(id) FROM talento_perfil WHERE usuario_id = u.id) WHERE u.id = ?");
    $stmt->execute([$uid]);
    echo json_encode(['ok' => true, 'usuario' => $stmt->fetch()]);
    exit;
  }

  // Auditoría
  if ($action === 'auditoria') {
    if (!$ajaxPerms['auditoria']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $filtro = trim($_GET['filtro'] ?? '');
    $limit = 30;
    $offset = ($page - 1) * $limit;
    try {
      $where = $filtro ? "WHERE a.accion = " . getDB()->quote($filtro) : "";
      $stmt = $db->prepare("
                SELECT a.*, u.nombre as admin_nombre
                FROM admin_auditoria a
                LEFT JOIN usuarios u ON u.id = a.admin_id
                $where
                ORDER BY a.creado_en DESC
                LIMIT $limit OFFSET $offset
            ");
      $stmt->execute();
      $total = $db->query("SELECT COUNT(*) FROM admin_auditoria" . ($filtro ? " WHERE accion = " . $db->quote($filtro) : ""))->fetchColumn();
      echo json_encode(['ok' => true, 'logs' => $stmt->fetchAll(), 'total' => (int) $total]);
    } catch (Exception $e) {
      $db->exec("CREATE TABLE IF NOT EXISTS admin_auditoria (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                accion VARCHAR(100) NOT NULL,
                detalle TEXT,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
      echo json_encode(['ok' => true, 'logs' => [], 'total' => 0]);
    }
    exit;
  }

  // Stats detalladas
  if ($action === 'stats_detalladas') {
    if (!$ajaxPerms['stats']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $data = [];
    // Usuarios por tipo
    $data['por_tipo'] = $db->query("SELECT tipo, COUNT(*) as total FROM usuarios GROUP BY tipo")->fetchAll();
    // Usuarios por ciudad
    $data['por_ciudad'] = $db->query("SELECT ciudad, COUNT(*) as total FROM usuarios WHERE ciudad IS NOT NULL AND ciudad != '' GROUP BY ciudad ORDER BY total DESC LIMIT 8")->fetchAll();
    // Registros por mes (últimos 6 meses)
    $data['por_mes'] = $db->query("SELECT DATE_FORMAT(creado_en,'%Y-%m') as mes, COUNT(*) as total FROM usuarios WHERE creado_en >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY mes ORDER BY mes ASC")->fetchAll();
    // Empleos por estado
    try {
      $data['empleos_estado'] = $db->query("SELECT activo, COUNT(*) as total FROM empleos GROUP BY activo")->fetchAll();
    } catch (Exception $e) {
      $data['empleos_estado'] = [];
    }
    // Verificaciones por estado
    try {
      $data['verif_estado'] = $db->query("SELECT estado, COUNT(*) as total FROM verificaciones GROUP BY estado")->fetchAll();
    } catch (Exception $e) {
      $data['verif_estado'] = [];
    }
    // Mensajes por día última semana
    try {
      $data['mensajes_semana'] = $db->query("SELECT DATE(creado_en) as dia, COUNT(*) as total FROM mensajes WHERE creado_en >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY dia ORDER BY dia")->fetchAll();
    } catch (Exception $e) {
      $data['mensajes_semana'] = [];
    }
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
  }

  // Mensajes recientes
  if ($action === 'mensajes_recientes') {
    if (!$ajaxPerms['mensajes']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $q = trim($_GET['q'] ?? '');
    $where = '1=1';
    $params = [];
    if ($q !== '') {
      $where = "(m.mensaje LIKE ? OR u1.nombre LIKE ? OR u1.apellido LIKE ? OR u2.nombre LIKE ? OR u2.apellido LIKE ?)";
      $lq = "%$q%";
      $params = [$lq, $lq, $lq, $lq, $lq];
    }
    $total = $db->prepare("SELECT COUNT(*) FROM mensajes m INNER JOIN usuarios u1 ON u1.id=m.de_usuario INNER JOIN usuarios u2 ON u2.id=m.para_usuario WHERE $where");
    $total->execute($params);
    $totalRows = (int) $total->fetchColumn();
    $stmt = $db->prepare("
      SELECT m.id, m.mensaje, m.creado_en, m.leido,
             m.de_usuario, m.para_usuario,
             u1.nombre as de_nombre, u1.apellido as de_apellido,
             u2.nombre as para_nombre, u2.apellido as para_apellido
      FROM mensajes m
      INNER JOIN usuarios u1 ON u1.id = m.de_usuario
      INNER JOIN usuarios u2 ON u2.id = m.para_usuario
      WHERE $where
      ORDER BY m.creado_en DESC LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'mensajes' => $stmt->fetchAll(), 'total' => $totalRows, 'page' => $page, 'pages' => ceil($totalRows / $limit)]);
    exit;
  }

  if ($action === 'chat_conversacion') {
    if (!$ajaxPerms['mensajes']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $u1 = (int) ($_GET['u1'] ?? 0);
    $u2 = (int) ($_GET['u2'] ?? 0);
    if (!$u1 || !$u2) {
      echo json_encode(['ok' => false, 'msg' => 'Faltan IDs']);
      exit;
    }
    $stmt = $db->prepare("
      SELECT m.id, m.mensaje, m.creado_en, m.leido, m.de_usuario,
             u1.nombre as de_nombre, u1.apellido as de_apellido
      FROM mensajes m
      INNER JOIN usuarios u1 ON u1.id = m.de_usuario
      WHERE (m.de_usuario=? AND m.para_usuario=?) OR (m.de_usuario=? AND m.para_usuario=?)
      ORDER BY m.creado_en ASC
    ");
    $stmt->execute([$u1, $u2, $u2, $u1]);
    $infoU1 = $db->prepare("SELECT id,nombre,apellido,tipo FROM usuarios WHERE id=?");
    $infoU1->execute([$u1]);
    $user1 = $infoU1->fetch();
    $infoU2 = $db->prepare("SELECT id,nombre,apellido,tipo FROM usuarios WHERE id=?");
    $infoU2->execute([$u2]);
    $user2 = $infoU2->fetch();
    echo json_encode(['ok' => true, 'mensajes' => $stmt->fetchAll(), 'user1' => $user1, 'user2' => $user2]);
    exit;
  }

  if ($action === 'chat_stats') {
    if (!$ajaxPerms['mensajes']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $total = $db->query("SELECT COUNT(*) FROM mensajes")->fetchColumn();
    $hoy = $db->query("SELECT COUNT(*) FROM mensajes WHERE DATE(creado_en)=CURDATE()")->fetchColumn();
    $semana = $db->query("SELECT COUNT(*) FROM mensajes WHERE creado_en >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
    $convs = $db->query("SELECT COUNT(DISTINCT LEAST(de_usuario,para_usuario)*100000+GREATEST(de_usuario,para_usuario)) FROM mensajes")->fetchColumn();
    $top = $db->query("SELECT u.nombre,u.apellido,COUNT(*) as total FROM mensajes m INNER JOIN usuarios u ON u.id=m.de_usuario GROUP BY m.de_usuario ORDER BY total DESC LIMIT 5")->fetchAll();
    echo json_encode(['ok' => true, 'total' => $total, 'hoy' => $hoy, 'semana' => $semana, 'conversaciones' => $convs, 'top_usuarios' => $top]);
    exit;
  }

  // ── TALENTOS (Gestión de perfiles de talento) ──────────────────────────────────
  // Listar talentos con paginación y filtros
  if ($action === 'talentos') {
    if (!$ajaxPerms['usuarios']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $buscar = trim($_GET['q'] ?? '');
    $categoria = trim($_GET['cat'] ?? '');
    $visible = isset($_GET['visible']) ? (int) $_GET['visible'] : -1;
    $destacado = isset($_GET['destacado']) ? (int) $_GET['destacado'] : -1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $where = "WHERE u.activo = 1";
    $params = [];

    if ($buscar) {
      $where .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR tp.profesion LIKE ? OR tp.skills LIKE ?)";
      $b = "%$buscar%";
      $params = array_merge($params, [$b, $b, $b, $b]);
    }

    if ($visible === 0 || $visible === 1) {
      $where .= " AND COALESCE(tp.visible_admin, 1) = ?";
      $params[] = $visible;
    }

    if ($destacado === 0 || $destacado === 1) {
      $where .= " AND COALESCE(tp.destacado, 0) = ?";
      $params[] = $destacado;
    }

    // Filtro por categoría (detectada por profesión/skills)
    $categoriasMap = [
      'salud' => ['medic', 'enferm', 'hospital', 'clinica', 'odontolog', 'farmaceut', 'nutricion', 'fisioterap', 'psiquiatr', 'pediatr', 'cardiolog', 'dermatolog', 'salud'],
      'educacion' => ['docente', 'profesor', 'tutor', 'educacion', 'psicolog', 'pedagog', 'maestro', 'colegio', 'escuela', 'orientador'],
      'tecnologia' => ['sistem', 'software', 'programador', 'desarrollador', 'web developer', 'php', 'javascript', 'react', 'python', 'java', 'tecnolog', 'informatic'],
      'musica' => ['dj', 'musico', 'cantante', 'music', 'productor musical', 'chirimia', 'salsa', 'reggaeton', 'vallenato', 'banda musical'],
      'arte' => ['diseñador', 'diseño grafic', 'ilustrador', 'fotograf', 'video', 'creativo', 'branding', 'ux', 'ui', 'animador', 'arte'],
      'administrativo' => ['administrador', 'contador', 'financiero', 'recursos humanos', 'rrhh', 'secretari', 'gerente', 'coordinador', 'asistente', 'marketing'],
      'gastronomia' => ['cociner', 'chef', 'gastronomia', 'reposter', 'panader', 'bartender', 'meser', 'restaurante', 'pasteler', 'barista'],
      'tecnico' => ['electricista', 'plomero', 'mecanic', 'tecnico', 'soldador', 'construccion', 'albañil', 'carpintero', 'conductor', 'instalador']
    ];

    if ($categoria && isset($categoriasMap[$categoria])) {
      $catConds = [];
      foreach ($categoriasMap[$categoria] as $term) {
        $catConds[] = "(LOWER(CONCAT(COALESCE(tp.profesion,''), ' ', COALESCE(tp.skills,''))) LIKE ?)";
        $params[] = '%' . strtolower($term) . '%';
      }
      $where .= " AND (" . implode(' OR ', $catConds) . ")";
    }

    $countSql = "SELECT COUNT(DISTINCT u.id) FROM usuarios u LEFT JOIN talento_perfil tp ON tp.id = (SELECT MAX(id) FROM talento_perfil WHERE usuario_id = u.id) $where";
    $stmtTotal = $db->prepare($countSql);
    $stmtTotal->execute($params);
    $total = (int) $stmtTotal->fetchColumn();

    $sql = "SELECT u.id, u.nombre, u.apellido, u.correo, u.ciudad, u.foto, u.verificado,
                   tp.profesion, tp.bio, tp.skills, tp.visible, tp.visible_admin, tp.destacado,
                   tp.avatar_color, tp.generos, tp.precio_desde, tp.tipo_servicio
            FROM usuarios u
            LEFT JOIN talento_perfil tp ON tp.id = (SELECT MAX(id) FROM talento_perfil WHERE usuario_id = u.id)
            $where
            ORDER BY tp.destacado DESC, u.verificado DESC, u.id DESC
            LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $talentos = $stmt->fetchAll();

    echo json_encode(['ok' => true, 'talentos' => $talentos, 'total' => $total, 'page' => $page]);
    exit;
  }

  // ── GET EMPRESA ──────────────────────────────────────────
  if ($action === 'get_empresa') {
    $uid = (int) ($_GET['id'] ?? 0);
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    $stmt = $db->prepare("
      SELECT u.id, u.nombre, u.correo, u.ciudad, u.telefono,
             ep.nombre_empresa, ep.sector, ep.nit, ep.descripcion,
             ep.sitio_web, ep.telefono_empresa, ep.municipio,
             ep.logo, ep.avatar_color,
             ep.visible, ep.visible_admin, ep.destacado
      FROM usuarios u
      LEFT JOIN perfiles_empresa ep ON ep.id = (
          SELECT MAX(id) FROM perfiles_empresa WHERE usuario_id = u.id
      )
      WHERE u.id = ?
    ");
    $stmt->execute([$uid]);
    $empresa = $stmt->fetch();
    if (!$empresa) {
      echo json_encode(['ok' => false, 'msg' => 'Empresa no encontrada']);
      exit;
    }
    echo json_encode(['ok' => true, 'empresa' => $empresa]);
    exit;
  }

  // ── EDITAR EMPRESA ───────────────────────────────────────
  if ($action === 'editar_empresa' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['talentos']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $uid = (int) ($_POST['id'] ?? 0);
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }

    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $sector = trim($_POST['sector'] ?? '');
    $nit = trim($_POST['nit'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $sitio_web = trim($_POST['sitio_web'] ?? '');
    $telefono_empresa = trim($_POST['telefono_empresa'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $avatar_color = trim($_POST['avatar_color'] ?? '');
    $visible_admin = (int) ($_POST['visible_admin'] ?? 1);
    $destacado = (int) ($_POST['destacado'] ?? 0);

    if ($ciudad) {
      $db->prepare("UPDATE usuarios SET ciudad = ? WHERE id = ?")->execute([$ciudad, $uid]);
    }

    $db->prepare("
      INSERT INTO perfiles_empresa
        (usuario_id, nombre_empresa, sector, nit, descripcion,
         sitio_web, telefono_empresa, municipio, avatar_color,
         visible, visible_admin, destacado)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        nombre_empresa   = VALUES(nombre_empresa),
        sector           = VALUES(sector),
        nit              = VALUES(nit),
        descripcion      = VALUES(descripcion),
        sitio_web        = VALUES(sitio_web),
        telefono_empresa = VALUES(telefono_empresa),
        municipio        = VALUES(municipio),
        avatar_color     = VALUES(avatar_color),
        visible          = VALUES(visible),
        visible_admin    = VALUES(visible_admin),
        destacado        = VALUES(destacado)
    ")->execute([
          $uid,
          $nombre_empresa,
          $sector,
          $nit,
          $descripcion,
          $sitio_web,
          $telefono_empresa,
          $municipio,
          $avatar_color,
          $visible_admin,
          $visible_admin,
          $destacado
        ]);

    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id, accion, detalle, creado_en) VALUES (?,?,?,NOW())")
        ->execute([$_SESSION['admin_id'], 'editar_empresa', "Editó perfil de empresa #$uid"]);
    } catch (Exception $e) {
    }

    echo json_encode(['ok' => true]);
    exit;
  }

  // Obtener un talento específico
  if ($action === 'get_talento') {
    $uid = (int) ($_GET['id'] ?? 0);
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    $stmt = $db->prepare("SELECT u.id, u.nombre, u.apellido, u.correo, u.ciudad, u.foto, u.verificado,
                                  tp.profesion, tp.bio, tp.skills, tp.visible, tp.visible_admin, tp.destacado,
                                  tp.avatar_color, tp.generos, tp.precio_desde, tp.tipo_servicio
                           FROM usuarios u
                           LEFT JOIN talento_perfil tp ON tp.id = (SELECT MAX(id) FROM talento_perfil WHERE usuario_id = u.id)
                           WHERE u.id = ?");
    $stmt->execute([$uid]);
    $talento = $stmt->fetch();
    if (!$talento) {
      echo json_encode(['ok' => false, 'msg' => 'Talento no encontrado']);
      exit;
    }
    echo json_encode(['ok' => true, 'talento' => $talento]);
    exit;
  }

  // Editar talento
  if ($action === 'editar_talento' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['talentos']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $uid = (int) ($_POST['id'] ?? 0);
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }

    $profesion = trim($_POST['profesion'] ?? '');
    $bio = trim($_POST['bio'] ?? '') ?: '';
    $skills = trim($_POST['skills'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $visible_admin = (int) ($_POST['visible_admin'] ?? 1);
    $destacado = (int) ($_POST['destacado'] ?? 0);
    $avatar_color = trim($_POST['avatar_color'] ?? '');
    $generos = trim($_POST['generos'] ?? '');
    $precio_desde = trim($_POST['precio_desde'] ?? '');
    $tipo_servicio = trim($_POST['tipo_servicio'] ?? '');

    // Actualizar ciudad del usuario
    if ($ciudad) {
      $db->prepare("UPDATE usuarios SET ciudad = ? WHERE id = ?")->execute([$ciudad, $uid]);
    }

    // Upsert — evita duplicados aunque no exista constraint UNIQUE en la BD
    $db->prepare("
      INSERT INTO talento_perfil
        (usuario_id, profesion, bio, skills, visible, visible_admin, destacado, avatar_color, generos, precio_desde, tipo_servicio)
      VALUES (?,?,COALESCE(?,''),?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        profesion     = VALUES(profesion),
        bio           = VALUES(bio),
        skills        = VALUES(skills),
        visible       = VALUES(visible),
        visible_admin = VALUES(visible_admin),
        destacado     = VALUES(destacado),
        avatar_color  = VALUES(avatar_color),
        generos       = VALUES(generos),
        precio_desde  = VALUES(precio_desde),
        tipo_servicio = VALUES(tipo_servicio)
    ")->execute([$uid, $profesion, $bio, $skills, $visible_admin, $visible_admin, $destacado, $avatar_color, $generos, $precio_desde ?: null, $tipo_servicio]);

    // Auditoría
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id, accion, detalle, creado_en) VALUES (?,?,?,NOW())")
        ->execute([$_SESSION['admin_id'], 'editar_talento', "Editó perfil de talento #$uid"]);
    } catch (Exception $e) {
    }

    echo json_encode(['ok' => true]);
    exit;
  }

  // ── CANDIDATOS (usuarios tipo candidato con talento_perfil) ──────────
  if ($action === 'candidatos') {
    if (!$ajaxPerms['usuarios']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $buscar = trim($_GET['q'] ?? '');
    $visible = isset($_GET['visible']) ? (int) $_GET['visible'] : -1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $where = "WHERE u.activo=1 AND u.tipo='candidato'";
    $params = [];
    if ($buscar) {
      $where .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR tp.profesion LIKE ? OR tp.skills LIKE ?)";
      $b = "%$buscar%";
      $params = array_merge($params, [$b, $b, $b, $b]);
    }
    if ($visible === 0 || $visible === 1) {
      $where .= " AND COALESCE(tp.visible_admin,1)=?";
      $params[] = $visible;
    }
    $stmtT = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM usuarios u LEFT JOIN talento_perfil tp ON tp.id=(SELECT MAX(id) FROM talento_perfil WHERE usuario_id=u.id) $where");
    $stmtT->execute($params);
    $total = (int) $stmtT->fetchColumn();
    $sql = "SELECT u.id,u.nombre,u.apellido,u.ciudad,u.foto,u.verificado,
                   tp.profesion,tp.skills,tp.visible_admin,tp.destacado,tp.avatar_color
            FROM usuarios u
            LEFT JOIN talento_perfil tp ON tp.id=(SELECT MAX(id) FROM talento_perfil WHERE usuario_id=u.id)
            $where ORDER BY tp.destacado DESC,u.verificado DESC,u.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'candidatos' => $stmt->fetchAll(), 'total' => $total, 'page' => $page]);
    exit;
  }

  // ── EMPRESAS DIRECTORIO ───────────────────────────────────────────────
  if ($action === 'empresas_dir') {
    if (!$ajaxPerms['usuarios']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $buscar = trim($_GET['q'] ?? '');
    $visible = isset($_GET['visible']) ? (int) $_GET['visible'] : -1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $where = "WHERE u.activo=1 AND u.tipo='empresa'";
    $params = [];
    if ($buscar) {
      $where .= " AND (u.nombre LIKE ? OR ep.nombre_empresa LIKE ? OR ep.sector LIKE ?)";
      $b = "%$buscar%";
      $params = array_merge($params, [$b, $b, $b]);
    }
    if ($visible === 0 || $visible === 1) {
      $where .= " AND COALESCE(ep.visible_admin,1)=?";
      $params[] = $visible;
    }
    $stmtT = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM usuarios u LEFT JOIN perfiles_empresa ep ON ep.id=(SELECT MAX(id) FROM perfiles_empresa WHERE usuario_id=u.id) $where");
    $stmtT->execute($params);
    $total = (int) $stmtT->fetchColumn();
    $sql = "SELECT u.id,u.nombre,u.ciudad,u.verificado,
                   ep.nombre_empresa,ep.sector,ep.nit,ep.logo,ep.avatar_color,
                   COALESCE(ep.visible_admin,1) as visible_admin,COALESCE(ep.destacado,0) as destacado
            FROM usuarios u
            LEFT JOIN perfiles_empresa ep ON ep.id=(SELECT MAX(id) FROM perfiles_empresa WHERE usuario_id=u.id)
            $where ORDER BY ep.destacado DESC,u.verificado DESC,u.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'empresas' => $stmt->fetchAll(), 'total' => $total, 'page' => $page]);
    exit;
  }

  // ── SERVICIOS DIRECTORIO (candidatos con tipo_servicio o precio_desde) ──
  if ($action === 'servicios_dir') {
    if (!$ajaxPerms['usuarios']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $buscar = trim($_GET['q'] ?? '');
    $visible = isset($_GET['visible']) ? (int) $_GET['visible'] : -1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $where = "WHERE u.activo=1 AND (tp.tipo_servicio IS NOT NULL AND tp.tipo_servicio<>'' OR tp.precio_desde IS NOT NULL)";
    $params = [];
    if ($buscar) {
      $where .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR tp.tipo_servicio LIKE ?)";
      $b = "%$buscar%";
      $params = array_merge($params, [$b, $b, $b]);
    }
    if ($visible === 0 || $visible === 1) {
      $where .= " AND COALESCE(tp.visible_admin,1)=?";
      $params[] = $visible;
    }
    $stmtT = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM usuarios u LEFT JOIN talento_perfil tp ON tp.id=(SELECT MAX(id) FROM talento_perfil WHERE usuario_id=u.id) $where");
    $stmtT->execute($params);
    $total = (int) $stmtT->fetchColumn();
    $sql = "SELECT u.id,u.nombre,u.apellido,u.foto,u.ciudad,u.verificado,
                   tp.tipo_servicio,tp.generos,tp.precio_desde,
                   tp.avatar_color,COALESCE(tp.visible_admin,1) as visible_admin,COALESCE(tp.destacado,0) as destacado
            FROM usuarios u
            LEFT JOIN talento_perfil tp ON tp.id=(SELECT MAX(id) FROM talento_perfil WHERE usuario_id=u.id)
            $where ORDER BY tp.destacado DESC,u.verificado DESC,u.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'servicios' => $stmt->fetchAll(), 'total' => $total, 'page' => $page]);
    exit;
  }

  // ── NEGOCIOS DIRECTORIO ──────────────────────────────────────────────
  if ($action === 'negocios_dir') {
    if (!$ajaxPerms['usuarios']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $buscar = trim($_GET['q'] ?? '');
    $visible = isset($_GET['visible']) ? (int) $_GET['visible'] : -1;
    $tipo_neg = trim($_GET['tipo'] ?? '');
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $where = "WHERE u.activo=1 AND nl.id IS NOT NULL";
    $params = [];
    if ($buscar) {
      $where .= " AND (nl.nombre_negocio LIKE ? OR nl.categoria LIKE ? OR u.nombre LIKE ?)";
      $b = "%$buscar%";
      $params = array_merge($params, [$b, $b, $b]);
    }
    if ($visible === 0 || $visible === 1) {
      $where .= " AND COALESCE(nl.visible_admin,1)=?";
      $params[] = $visible;
    }
    if ($tipo_neg === 'cc' || $tipo_neg === 'emp') {
      $where .= " AND nl.tipo_negocio=?";
      $params[] = $tipo_neg;
    }
    $stmtT = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM usuarios u LEFT JOIN negocios_locales nl ON nl.id=(SELECT MAX(id) FROM negocios_locales WHERE usuario_id=u.id) $where");
    $stmtT->execute($params);
    $total = (int) $stmtT->fetchColumn();
    $sql = "SELECT u.id,u.nombre,u.ciudad,u.verificado,
                   nl.id as negocio_id,nl.nombre_negocio,nl.categoria,nl.whatsapp,
                   nl.tipo_negocio,nl.logo,nl.avatar_color,nl.descripcion,nl.ubicacion,
                   COALESCE(nl.visible_admin,1) as visible_admin,COALESCE(nl.destacado,0) as destacado
            FROM usuarios u
            LEFT JOIN negocios_locales nl ON nl.id=(SELECT MAX(id) FROM negocios_locales WHERE usuario_id=u.id)
            $where ORDER BY nl.destacado DESC,u.verificado DESC,u.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'negocios' => $stmt->fetchAll(), 'total' => $total, 'page' => $page]);
    exit;
  }

  // ── GET NEGOCIO (para modal de edición) ─────────────────────────────
  if ($action === 'get_negocio') {
    $uid = (int) ($_GET['id'] ?? 0);
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    $stmt = $db->prepare("
      SELECT u.id,u.nombre,u.ciudad,
             nl.id as negocio_id,nl.nombre_negocio,nl.categoria,nl.descripcion,
             nl.whatsapp,nl.ubicacion,nl.tipo_negocio,nl.logo,nl.avatar_color,
             COALESCE(nl.visible_admin,1) as visible_admin,COALESCE(nl.destacado,0) as destacado
      FROM usuarios u
      LEFT JOIN negocios_locales nl ON nl.id=(SELECT MAX(id) FROM negocios_locales WHERE usuario_id=u.id)
      WHERE u.id=?");
    $stmt->execute([$uid]);
    $negocio = $stmt->fetch();
    if (!$negocio) {
      echo json_encode(['ok' => false, 'msg' => 'Negocio no encontrado']);
      exit;
    }
    echo json_encode(['ok' => true, 'negocio' => $negocio]);
    exit;
  }

  // ── EDITAR NEGOCIO ───────────────────────────────────────────────────
  if ($action === 'editar_negocio' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['talentos']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $uid = (int) ($_POST['id'] ?? 0);
    if (!$uid) {
      echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
      exit;
    }
    $nombre_negocio = trim($_POST['nombre_negocio'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $tipo_negocio = trim($_POST['tipo_negocio'] ?? 'emp');
    $visible_admin = (int) ($_POST['visible_admin'] ?? 1);
    $destacado = (int) ($_POST['destacado'] ?? 0);
    $db->prepare("
      INSERT INTO negocios_locales (usuario_id,nombre_negocio,categoria,whatsapp,descripcion,ubicacion,tipo_negocio,visible,visible_admin,destacado)
      VALUES (?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        nombre_negocio=VALUES(nombre_negocio),categoria=VALUES(categoria),
        whatsapp=VALUES(whatsapp),descripcion=VALUES(descripcion),
        ubicacion=VALUES(ubicacion),tipo_negocio=VALUES(tipo_negocio),
        visible=VALUES(visible),visible_admin=VALUES(visible_admin),destacado=VALUES(destacado)
    ")->execute([$uid, $nombre_negocio, $categoria, $whatsapp, $descripcion, $ubicacion, $tipo_negocio, $visible_admin, $visible_admin, $destacado]);
    try {
      $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")
        ->execute([$_SESSION['admin_id'], 'editar_negocio', "Editó negocio de usuario #$uid"]);
    } catch (Exception $e) {
    }
    echo json_encode(['ok' => true]);
    exit;
  }

  // ── TOGGLE VISIBLE/DESTACADO inline (para las 4 nuevas tablas) ───────
  if ($action === 'toggle_dir_campo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ajaxPerms['talentos']) {
      echo json_encode(['ok' => false, 'msg' => 'Sin permisos']);
      exit;
    }
    $uid = (int) ($_POST['id'] ?? 0);
    $tabla = $_POST['tabla'] ?? '';
    $campo = $_POST['campo'] ?? '';
    $valor = (int) ($_POST['valor'] ?? 0);
    $tablas_ok = ['talento_perfil', 'perfiles_empresa', 'negocios_locales'];
    $campos_ok = ['visible_admin', 'destacado'];
    if (!$uid || !in_array($tabla, $tablas_ok) || !in_array($campo, $campos_ok)) {
      echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos']);
      exit;
    }
    try {
      if ($tabla === 'talento_perfil') {
        // Asegurar que bio tiene DEFAULT '' para evitar error strict mode
        try {
          $db->exec("ALTER TABLE talento_perfil ALTER COLUMN bio SET DEFAULT ''");
        } catch (Exception $e2) {
        }
        // UPSERT: incluir bio='' para satisfacer strict mode cuando es insert nuevo
        $db->prepare("
          INSERT INTO talento_perfil (usuario_id, bio, $campo)
          VALUES (?, '', ?)
          ON DUPLICATE KEY UPDATE $campo = VALUES($campo)
        ")->execute([$uid, $valor]);
      } else {
        $db->prepare("UPDATE $tabla SET $campo=? WHERE usuario_id=?")->execute([$valor, $uid]);
      }
      echo json_encode(['ok' => true]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
  }

  // ── SISTEMA: obtener modo de registro ────────────────────────────────
  if ($action === 'get_modo_registro' && $nivel === 'superadmin') {
    try {
      $db->exec("CREATE TABLE IF NOT EXISTS sistema_config (
        clave VARCHAR(80) PRIMARY KEY,
        valor TEXT NOT NULL,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )");
      $row = $db->query("SELECT valor FROM sistema_config WHERE clave='modo_registro'")->fetch();
      $modo = $row ? $row['valor'] : 'solicitud';
    } catch (Exception $e) {
      $modo = 'solicitud';
    }
    echo json_encode(['ok' => true, 'modo' => $modo]);
    exit;
  }

  // ── SISTEMA: cambiar modo de registro ─────────────────────────────────
  if ($action === 'set_modo_registro' && $_SERVER['REQUEST_METHOD'] === 'POST' && $nivel === 'superadmin') {
    $modo = trim($_POST['modo'] ?? '');
    if (!in_array($modo, ['solicitud', 'directo'])) {
      echo json_encode(['ok' => false, 'msg' => 'Modo inválido']);
      exit;
    }
    try {
      $db->exec("CREATE TABLE IF NOT EXISTS sistema_config (
        clave VARCHAR(80) PRIMARY KEY,
        valor TEXT NOT NULL,
        actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )");
      $db->prepare("INSERT INTO sistema_config (clave, valor) VALUES ('modo_registro', ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$modo]);
      try {
        $db->prepare("INSERT INTO admin_auditoria (admin_id,accion,detalle,creado_en) VALUES (?,?,?,NOW())")
          ->execute([$_SESSION['admin_id'], 'set_modo_registro', "Modo de registro cambiado a '$modo'"]);
      } catch (Exception $e) {
      }
      echo json_encode(['ok' => true, 'modo' => $modo]);
    } catch (Exception $e) {
      echo json_encode(['ok' => false, 'msg' => 'Error BD: ' . $e->getMessage()]);
    }
    exit;
  }

  echo json_encode(['ok' => false, 'msg' => 'Acción desconocida.']);
  exit;
}

// Si hay acción pero no está logueado
if ($action) {
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'msg' => 'No autorizado.']);
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Panel Admin — QuibdóConecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link
    href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap"
    rel="stylesheet">
  <style>
    :root {
      --bg: #080c14;
      --bg2: #0d1421;
      --bg3: #111a2e;
      --bg4: #162035;
      --border: rgba(255, 255, 255, .07);
      --border2: rgba(255, 255, 255, .12);
      --text: #e8edf5;
      --text2: #8899bb;
      --text3: #4a5a7a;
      --green: #00e676;
      --green2: #00c853;
      --green-bg: rgba(0, 230, 118, .08);
      --blue: #4488ff;
      --blue-bg: rgba(68, 136, 255, .1);
      --amber: #ffab00;
      --amber-bg: rgba(255, 171, 0, .1);
      --red: #ff4444;
      --red-bg: rgba(255, 68, 68, .1);
      --purple: #aa44ff;
      --purple-bg: rgba(170, 68, 255, .1);
      --radius: 12px;
      --sidebar: 240px;
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
      font-family: 'Space Grotesk', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      overflow-x: hidden
    }

    /* ── LOGIN ── */
    .login-wrap {
      min-height: 100vh;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(ellipse at 30% 20%, rgba(0, 230, 118, .06) 0%, transparent 60%), radial-gradient(ellipse at 80% 80%, rgba(68, 136, 255, .06) 0%, transparent 60%), var(--bg)
    }

    .login-box {
      background: var(--bg2);
      border: 1px solid var(--border2);
      border-radius: 24px;
      padding: 48px 40px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 40px 80px rgba(0, 0, 0, .5)
    }

    .login-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 32px;
      justify-content: center
    }

    .login-logo img {
      width: 40px
    }

    .login-logo span {
      font-size: 20px;
      font-weight: 700;
      color: var(--text)
    }

    .login-logo span em {
      color: var(--green);
      font-style: normal
    }

    .login-box h2 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 6px;
      text-align: center
    }

    .login-box p {
      color: var(--text2);
      font-size: 13px;
      text-align: center;
      margin-bottom: 28px
    }

    .field {
      margin-bottom: 16px
    }

    .field label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--text2);
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: .8px
    }

    .field input {
      width: 100%;
      padding: 13px 16px;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-size: 14px;
      font-family: 'Space Grotesk', sans-serif;
      outline: none;
      transition: border .2s
    }

    .field input:focus {
      border-color: var(--green)
    }

    .btn-login {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, var(--green2), var(--green));
      border: none;
      border-radius: 10px;
      color: #000;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      font-family: 'Space Grotesk', sans-serif;
      transition: transform .2s, box-shadow .2s;
      box-shadow: 0 4px 20px rgba(0, 230, 118, .3)
    }

    .btn-login:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 30px rgba(0, 230, 118, .4)
    }

    .login-error {
      background: var(--red-bg);
      border: 1px solid rgba(255, 68, 68, .3);
      border-radius: 10px;
      padding: 12px 16px;
      font-size: 13px;
      color: var(--red);
      margin-bottom: 16px;
      text-align: center
    }

    /* ── SIDEBAR ── */
    .sidebar {
      width: var(--sidebar);
      flex-shrink: 0;
      background: var(--bg2);
      border-right: 1px solid var(--border);
      min-height: 100vh;
      position: sticky;
      top: 0;
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
      z-index: 100
    }

    .sb-brand {
      padding: 24px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 10px
    }

    .sb-brand img {
      width: 32px
    }

    .sb-brand-text {
      font-size: 16px;
      font-weight: 700
    }

    .sb-brand-text em {
      color: var(--green);
      font-style: normal
    }

    .sb-brand-sub {
      font-size: 10px;
      color: var(--text3);
      font-family: 'JetBrains Mono', monospace;
      margin-top: 2px
    }

    .sb-admin {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 10px
    }

    .sb-avatar {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--green2), var(--blue));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 700;
      color: #000;
      flex-shrink: 0
    }

    .sb-admin-info .name {
      font-size: 13px;
      font-weight: 600
    }

    .sb-admin-info .nivel {
      font-size: 10px;
      color: var(--amber);
      font-family: 'JetBrains Mono', monospace;
      text-transform: uppercase
    }

    .sb-nav {
      padding: 12px 10px;
      flex: 1
    }

    .sb-section {
      font-size: 9px;
      font-weight: 700;
      color: var(--text3);
      text-transform: uppercase;
      letter-spacing: 1.5px;
      padding: 14px 10px 6px
    }

    .sb-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 10px;
      color: var(--text2);
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all .15s;
      margin-bottom: 1px;
      border: none;
      background: none;
      width: 100%;
      text-align: left
    }

    .sb-item:hover {
      background: var(--bg3);
      color: var(--text)
    }

    .sb-item.active {
      background: var(--green-bg);
      color: var(--green);
      border: 1px solid rgba(0, 230, 118, .15)
    }

    .sb-item .ic {
      width: 18px;
      text-align: center;
      font-size: 15px;
      flex-shrink: 0
    }

    .sb-badge {
      margin-left: auto;
      background: var(--red);
      color: white;
      font-size: 9px;
      font-weight: 700;
      padding: 2px 6px;
      border-radius: 6px
    }

    .sb-bottom {
      padding: 12px 10px;
      border-top: 1px solid var(--border)
    }

    .sb-logout {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 10px;
      color: rgba(255, 68, 68, .7);
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      transition: all .15s
    }

    .sb-logout:hover {
      background: var(--red-bg);
      color: var(--red)
    }

    /* ── MAIN ── */
    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
      overflow: hidden
    }

    .topbar {
      height: 60px;
      background: var(--bg2);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 28px;
      position: sticky;
      top: 0;
      z-index: 50
    }

    .topbar-title {
      font-size: 16px;
      font-weight: 700
    }

    .topbar-sub {
      font-size: 11px;
      color: var(--text3);
      font-family: 'JetBrains Mono', monospace;
      margin-top: 1px
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 10px
    }

    .tb-time {
      font-size: 11px;
      color: var(--text3);
      font-family: 'JetBrains Mono', monospace
    }

    .tb-btn {
      padding: 6px 14px;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--text2);
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'Space Grotesk', sans-serif;
      transition: all .15s
    }

    .tb-btn:hover {
      border-color: var(--border2);
      color: var(--text)
    }

    /* ── CONTENT ── */
    .content {
      flex: 1;
      padding: 28px;
      overflow-y: auto
    }

    .section {
      display: none
    }

    .section.active {
      display: block
    }

    /* ── CARDS MÉTRICAS ── */
    .metric-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 18px 20px;
      position: relative;
      overflow: hidden;
      transition: all .2s;
      cursor: default
    }

    .metric-card:hover {
      border-color: var(--border2);
      transform: translateY(-1px)
    }

    .metric-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px
    }

    .metric-card.green::before {
      background: linear-gradient(90deg, var(--green2), var(--green))
    }

    .metric-card.blue::before {
      background: linear-gradient(90deg, #2255cc, var(--blue))
    }

    .metric-card.amber::before {
      background: linear-gradient(90deg, #cc8800, var(--amber))
    }

    .metric-card.red::before {
      background: linear-gradient(90deg, #cc2222, var(--red))
    }

    .metric-card.purple::before {
      background: linear-gradient(90deg, #7722cc, var(--purple))
    }

    .mc-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px
    }

    .mc-icon {
      font-size: 20px;
      width: 38px;
      height: 38px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center
    }

    .metric-card.green .mc-icon {
      background: var(--green-bg)
    }

    .metric-card.blue .mc-icon {
      background: var(--blue-bg)
    }

    .metric-card.amber .mc-icon {
      background: var(--amber-bg)
    }

    .metric-card.red .mc-icon {
      background: var(--red-bg)
    }

    .metric-card.purple .mc-icon {
      background: rgba(170, 68, 255, .1)
    }

    .mc-trend {
      font-size: 11px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 20px
    }

    .mc-trend.up {
      background: var(--green-bg);
      color: var(--green)
    }

    .mc-trend.neutral {
      background: var(--bg3);
      color: var(--text3)
    }

    .mc-value {
      font-size: 30px;
      font-weight: 700;
      font-family: 'JetBrains Mono', monospace;
      line-height: 1
    }

    .mc-label {
      font-size: 12px;
      color: var(--text2);
      margin-top: 5px
    }

    .mc-sub {
      font-size: 11px;
      color: var(--text3);
      margin-top: 3px
    }

    /* Botones de acción rápida */
    .db-action-btn {
      width: 100%;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 12px;
      color: var(--text);
      cursor: pointer;
      transition: all .15s;
      font-family: 'Space Grotesk', sans-serif;
      text-align: left
    }

    .db-action-btn:hover {
      border-color: var(--ac, var(--green));
      background: rgba(255, 255, 255, .03);
      transform: translateX(2px)
    }

    .db-action-btn>span:first-child {
      font-size: 20px;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 10px;
      background: var(--bg2);
      flex-shrink: 0
    }

    /* Alerta urgente */
    .db-alerta {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 18px;
      border-radius: 14px;
      margin-bottom: 10px;
      cursor: pointer;
      transition: opacity .2s
    }

    .db-alerta:hover {
      opacity: .85
    }

    .db-alerta.urgente {
      background: rgba(255, 171, 0, .1);
      border: 1px solid rgba(255, 171, 0, .3)
    }

    .db-alerta.info {
      background: rgba(68, 136, 255, .08);
      border: 1px solid rgba(68, 136, 255, .25)
    }

    /* ── TABLA ── */
    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      gap: 12px;
      flex-wrap: wrap
    }

    .stat-mini {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 16px 18px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .stat-mini .sm-val {
      font-size: 24px;
      font-weight: 800;
      color: var(--green);
      font-family: 'JetBrains Mono', monospace;
    }

    .stat-mini .sm-lbl {
      font-size: 11px;
      color: var(--text3);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .8px;
    }

    .section-header h2 {
      font-size: 18px;
      font-weight: 700
    }

    .search-box {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 8px 14px;
      flex: 1;
      max-width: 320px
    }

    .search-box input {
      background: none;
      border: none;
      outline: none;
      color: var(--text);
      font-size: 13px;
      font-family: 'Space Grotesk', sans-serif;
      width: 100%
    }

    .search-box input::placeholder {
      color: var(--text3)
    }

    .filter-btns {
      display: flex;
      gap: 6px
    }

    .filter-btn {
      padding: 7px 14px;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--text2);
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'Space Grotesk', sans-serif;
      transition: all .15s
    }

    .filter-btn:hover,
    .filter-btn.active {
      background: var(--green-bg);
      border-color: rgba(0, 230, 118, .3);
      color: var(--green)
    }

    .table-wrap {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden
    }

    table {
      width: 100%;
      border-collapse: collapse
    }

    thead {
      background: var(--bg3)
    }

    th {
      padding: 12px 16px;
      font-size: 11px;
      font-weight: 700;
      color: var(--text3);
      text-transform: uppercase;
      letter-spacing: .8px;
      text-align: left;
      border-bottom: 1px solid var(--border)
    }

    td {
      padding: 13px 16px;
      font-size: 13px;
      border-bottom: 1px solid var(--border);
      vertical-align: middle
    }

    tr:last-child td {
      border-bottom: none
    }

    tr:hover td {
      background: rgba(255, 255, 255, .02)
    }

    /* ── BADGES ── */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      font-family: 'JetBrains Mono', monospace
    }

    .badge.green {
      background: var(--green-bg);
      color: var(--green);
      border: 1px solid rgba(0, 230, 118, .2)
    }

    .badge.red {
      background: var(--red-bg);
      color: var(--red);
      border: 1px solid rgba(255, 68, 68, .2)
    }

    .badge.amber {
      background: var(--amber-bg);
      color: var(--amber);
      border: 1px solid rgba(255, 171, 0, .2)
    }

    .badge.blue {
      background: var(--blue-bg);
      color: var(--blue);
      border: 1px solid rgba(68, 136, 255, .2)
    }

    .badge.purple {
      background: var(--purple-bg);
      color: var(--purple);
      border: 1px solid rgba(170, 68, 255, .2)
    }

    .badge.gray {
      background: rgba(255, 255, 255, .05);
      color: var(--text2);
      border: 1px solid var(--border)
    }

    /* ── BOTONES ACCIÓN ── */
    .btn-sm {
      padding: 5px 12px;
      border-radius: 7px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--text2);
      font-size: 11px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'Space Grotesk', sans-serif;
      transition: all .15s
    }

    .btn-sm:hover {
      border-color: var(--border2);
      color: var(--text)
    }

    .btn-sm.green {
      border-color: rgba(0, 230, 118, .3);
      color: var(--green);
      background: var(--green-bg)
    }

    .btn-sm.green:hover {
      background: rgba(0, 230, 118, .15)
    }

    .btn-sm.red {
      border-color: rgba(255, 68, 68, .3);
      color: var(--red);
      background: var(--red-bg)
    }

    .btn-sm.red:hover {
      background: rgba(255, 68, 68, .2)
    }

    .btn-sm.amber {
      border-color: rgba(255, 171, 0, .3);
      color: var(--amber);
      background: var(--amber-bg)
    }

    /* ── VERIFICACIONES ── */
    .verif-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
      margin-bottom: 12px;
      transition: border .2s
    }

    .verif-card:hover {
      border-color: var(--border2)
    }

    .verif-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px
    }

    .verif-user {
      display: flex;
      align-items: center;
      gap: 12px
    }

    .verif-avatar {
      width: 40px;
      height: 40px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--blue), var(--purple));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 700;
      color: white;
      flex-shrink: 0
    }

    .verif-info .name {
      font-size: 14px;
      font-weight: 600
    }

    .verif-info .meta {
      font-size: 12px;
      color: var(--text2)
    }

    .verif-actions {
      display: flex;
      gap: 8px;
      margin-top: 12px
    }

    .verif-nota {
      width: 100%;
      padding: 8px 12px;
      background: var(--bg3);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--text);
      font-size: 12px;
      font-family: 'Space Grotesk', sans-serif;
      outline: none;
      resize: none;
      margin-top: 8px
    }

    .verif-nota:focus {
      border-color: var(--amber)
    }

    .verif-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 20px
    }

    /* ── ROLES ── */
    .rol-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 16px 20px;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      justify-content: space-between
    }

    .rol-info .name {
      font-size: 14px;
      font-weight: 600
    }

    .rol-info .email {
      font-size: 12px;
      color: var(--text2)
    }

    .superadmin-crown {
      color: var(--amber);
      font-size: 18px
    }

    /* ── LOADING ── */
    .loading {
      text-align: center;
      padding: 48px;
      color: var(--text3)
    }

    .loading .spin {
      font-size: 32px;
      display: inline-block;
      animation: spin 1s linear infinite
    }

    @keyframes spin {
      to {
        transform: rotate(360deg)
      }
    }

    /* ── CHART ── */
    .chart-wrap {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
      margin-bottom: 24px
    }

    .chart-wrap h3 {
      font-size: 14px;
      font-weight: 600;
      color: var(--text2);
      margin-bottom: 16px
    }

    .chart-bars {
      display: flex;
      align-items: flex-end;
      gap: 8px;
      height: 80px
    }

    .chart-bar-col {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px
    }

    .chart-bar {
      width: 100%;
      background: linear-gradient(180deg, var(--green), var(--green2));
      border-radius: 4px 4px 0 0;
      min-height: 4px;
      transition: height .5s ease
    }

    .chart-bar-label {
      font-size: 9px;
      color: var(--text3);
      font-family: 'JetBrains Mono', monospace
    }

    .chart-bar-val {
      font-size: 10px;
      color: var(--green);
      font-family: 'JetBrains Mono', monospace
    }

    /* ── PAGINACIÓN ── */
    .pagination {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      margin-top: 16px
    }

    .page-btn {
      padding: 6px 12px;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--text2);
      font-size: 12px;
      cursor: pointer;
      font-family: 'Space Grotesk', sans-serif;
      transition: all .15s
    }

    .page-btn:hover,
    .page-btn.active {
      background: var(--green-bg);
      border-color: rgba(0, 230, 118, .3);
      color: var(--green)
    }

    .page-btn:disabled {
      opacity: .3;
      cursor: not-allowed
    }

    /* ── EMPTY STATE ── */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--text3)
    }

    .empty-state .ei {
      font-size: 48px;
      margin-bottom: 12px;
      display: block;
      opacity: .4
    }

    .empty-state p {
      font-size: 14px
    }

    /* ── USER CARDS ── */
    .user-card {
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 18px;
      transition: border .2s;
      position: relative
    }

    .user-card:hover {
      border-color: var(--border2)
    }

    .user-card-top {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px
    }

    .user-card-avatar {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 17px;
      font-weight: 800;
      color: white;
      flex-shrink: 0;
      overflow: hidden
    }

    .user-card-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover
    }

    .user-card-name {
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 2px;
      line-height: 1.2
    }

    .user-card-email {
      font-size: 11px;
      color: var(--text3);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 200px
    }

    .user-card-id {
      position: absolute;
      top: 12px;
      right: 14px;
      font-family: 'JetBrains Mono', monospace;
      font-size: 10px;
      color: var(--text3);
      background: var(--bg3);
      padding: 2px 8px;
      border-radius: 6px
    }

    .uc-badges {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      margin-bottom: 10px
    }

    .uc-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      margin-bottom: 10px
    }

    .uc-field {
      background: var(--bg3);
      border-radius: 8px;
      padding: 7px 10px
    }

    .uc-field .uf-label {
      font-size: 9px;
      color: var(--text3);
      text-transform: uppercase;
      letter-spacing: .8px;
      font-weight: 700;
      margin-bottom: 2px
    }

    .uc-field .uf-val {
      font-size: 12px;
      color: var(--text);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      font-family: 'JetBrains Mono', monospace
    }

    .uc-sesion {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 6px;
      margin-bottom: 10px
    }

    .uc-chip {
      background: var(--bg3);
      border-radius: 8px;
      padding: 6px 10px
    }

    .uc-chip .uc-cl {
      font-size: 9px;
      color: var(--text3);
      display: block;
      margin-bottom: 1px;
      text-transform: uppercase;
      letter-spacing: .6px
    }

    .uc-chip .uc-cv {
      font-size: 11px;
      font-family: 'JetBrains Mono', monospace
    }

    .uc-actions {
      display: flex;
      gap: 6px;
      flex-wrap: wrap
    }

    /* ── ACTIVIDAD ── */
    .act-feed {
      display: flex;
      flex-direction: column
    }

    .act-item {
      display: flex;
      gap: 14px;
      padding: 14px 0;
      border-bottom: 1px solid var(--border)
    }

    .act-item:last-child {
      border-bottom: none
    }

    .act-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      flex-shrink: 0
    }

    .act-body {
      flex: 1
    }

    .act-admin {
      font-size: 13px;
      font-weight: 600
    }

    .act-desc {
      font-size: 12px;
      color: var(--text2);
      margin-top: 2px;
      line-height: 1.4
    }

    .act-time {
      font-size: 10px;
      color: var(--text3);
      font-family: 'JetBrains Mono', monospace;
      margin-top: 4px
    }

    /* ── RESPONSIVE ── */
    /* ══ RESPONSIVE ══ */

    /* Fluid type */
    html {
      font-size: clamp(13px, 1.3vw, 15px)
    }

    /* ── Laptop pequeño ── */
    @media(max-width:1200px) {
      :root {
        --sidebar: 200px
      }

      .content {
        padding: 18px
      }

      .metrics-grid {
        grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
        gap: 12px
      }
    }

    /* ── Tablet: sidebar colapsado solo íconos ── */
    @media(max-width:960px) and (min-width:641px) {
      :root {
        --sidebar: 64px
      }

      .sb-brand-text,
      .sb-brand-sub,
      .sb-admin-info,
      .sb-section,
      .sb-item span:not(.ic),
      .sb-logout span {
        display: none
      }

      .sb-brand {
        justify-content: center;
        padding: 14px 0
      }

      .sb-admin {
        justify-content: center;
        padding: 10px 0
      }

      .sb-item {
        justify-content: center;
        padding: 13px;
        position: relative
      }

      .sb-item .ic {
        font-size: 20px
      }

      .sb-item[data-tip]:hover::after {
        content: attr(data-tip);
        position: absolute;
        left: calc(100% + 8px);
        top: 50%;
        transform: translateY(-50%);
        background: var(--bg3);
        border: 1px solid var(--border2);
        color: var(--text);
        font-size: 12px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 8px;
        white-space: nowrap;
        z-index: 9999;
        pointer-events: none;
        box-shadow: 0 4px 16px rgba(0, 0, 0, .5);
      }

      .sb-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        font-size: 9px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px
      }

      .sb-bottom {
        padding: 8px 0
      }

      .sb-logout {
        justify-content: center;
        padding: 12px
      }

      .topbar {
        padding: 0 16px
      }

      .content {
        padding: 16px
      }

      #db-bottom-row {
        grid-template-columns: 1fr !important
      }
    }

    /* ── Móvil: drawer lateral + hamburguesa ── */
    @media(max-width:640px) {
      :root {
        --sidebar: 0px
      }

      body {
        flex-direction: column
      }

      /* Sidebar → drawer oculto por defecto */
      .sidebar {
        position: fixed !important;
        top: 0;
        left: 0;
        bottom: 0;
        width: 260px !important;
        min-height: 100vh;
        z-index: 400;
        transform: translateX(-100%);
        transition: transform .28s cubic-bezier(.4, 0, .2, 1);
        box-shadow: 4px 0 32px rgba(0, 0, 0, .5);
      }

      .sidebar.open {
        transform: translateX(0) !important
      }

      /* Overlay oscuro detrás del drawer */
      .sb-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, .55);
        z-index: 399;
        backdrop-filter: blur(4px);
        opacity: 0;
        transition: opacity .28s;
      }

      .sb-overlay.open {
        display: block;
        opacity: 1
      }

      /* Botón hamburguesa en topbar */
      .btn-hamburger {
        display: flex !important;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        align-items: center;
        justify-content: center;
        background: var(--bg3);
        border: 1px solid var(--border);
        cursor: pointer;
        flex-direction: column;
        gap: 4px;
        flex-shrink: 0;
      }

      .btn-hamburger span {
        display: block;
        width: 18px;
        height: 2px;
        background: var(--text);
        border-radius: 2px;
        transition: all .25s;
      }

      .btn-hamburger.open span:nth-child(1) {
        transform: translateY(6px) rotate(45deg)
      }

      .btn-hamburger.open span:nth-child(2) {
        opacity: 0;
        transform: scaleX(0)
      }

      .btn-hamburger.open span:nth-child(3) {
        transform: translateY(-6px) rotate(-45deg)
      }

      .main {
        min-height: 100dvh;
        width: 100%
      }

      .topbar {
        padding: 0 14px;
        height: 54px
      }

      .topbar-sub {
        display: none
      }

      .tb-time {
        display: none
      }

      .topbar-left h2 {
        font-size: 15px
      }

      .tb-btn {
        width: 36px;
        height: 36px;
        font-size: 14px
      }

      .content {
        padding: 14px 12px 24px
      }

      .section-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px
      }

      .section-header h2 {
        font-size: 16px
      }

      .search-box {
        max-width: 100%
      }

      .filter-btns {
        flex-wrap: wrap;
        gap: 5px
      }

      .filter-btn {
        font-size: 11px;
        padding: 6px 10px
      }

      .metrics-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px
      }

      #db-bottom-row {
        grid-template-columns: 1fr !important
      }

      .metric-card {
        padding: 14px
      }

      .mc-value {
        font-size: 22px
      }

      .mc-icon {
        font-size: 18px;
        margin-bottom: 8px
      }

      .table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch
      }

      table {
        min-width: 480px
      }

      th,
      td {
        padding: 9px 11px;
        font-size: 12px
      }

      .verif-card {
        padding: 14px
      }

      .verif-header {
        flex-wrap: wrap;
        gap: 10px
      }

      .verif-actions {
        flex-wrap: wrap;
        gap: 8px
      }

      .verif-actions button {
        flex: 1;
        min-width: 110px
      }

      .rol-card {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start
      }

      .rol-card>div:last-child {
        width: 100%;
        flex-wrap: wrap
      }

      .uc-grid,
      .uc-sesion {
        grid-template-columns: 1fr !important
      }

      #docs-grid {
        grid-template-columns: 1fr !important
      }

      /* Modales → bottom sheet */
      #modal-usuario,
      #modal-pass-user,
      #modal-permisos,
      #modal-pass {
        align-items: flex-end !important;
        padding: 0 !important;
      }

      #modal-usuario>div,
      #modal-pass-user>div,
      #modal-permisos>div,
      #modal-pass>div {
        max-width: 100% !important;
        width: 100% !important;
        border-radius: 20px 20px 0 0 !important;
        position: fixed !important;
        bottom: 0;
        left: 0;
        right: 0;
        margin: 0 !important;
        max-height: 90dvh;
        overflow-y: auto;
      }

      #modal-usuario>div::before,
      #modal-permisos>div::before {
        content: '';
        display: block;
        width: 36px;
        height: 4px;
        background: var(--border2);
        border-radius: 4px;
        margin: 0 auto 18px;
      }

      #permisos-grid {
        grid-template-columns: 1fr !important
      }
    }

    @media(max-width:400px) {
      .metrics-grid {
        grid-template-columns: 1fr 1fr
      }

      .metric-card {
        padding: 12px
      }

      .mc-value {
        font-size: 20px
      }
    }

    /* Touch targets */
    @media(pointer:coarse) {

      .sb-item,
      .btn-sm,
      .filter-btn,
      .tb-btn {
        min-height: 44px
      }
    }

    @media(prefers-reduced-motion:reduce) {
      * {
        animation-duration: .01ms !important;
        transition-duration: .01ms !important
      }
    }
  </style>
</head>

<body>

  <?php if (!$logueado): ?>
    <!-- ═══ LOGIN ═══ -->
    <div class="login-wrap">
      <div class="login-box">
        <div class="login-logo">
          <img src="Imagenes/Quibdo.png" alt="Logo">
          <span>Quibdó<em>Conecta</em></span>
        </div>
        <h2>Panel de Administración</h2>
        <p>Acceso restringido — solo personal autorizado</p>
        <?php if ($error): ?>
          <div class="login-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="_login" value="1">
          <div class="field">
            <label>Correo electrónico</label>
            <input type="email" name="correo" placeholder="admin@quibdoconecta.com" required autofocus>
          </div>
          <div class="field">
            <label>Contraseña</label>
            <input type="password" name="pass" placeholder="••••••••" required>
          </div>
          <button type="submit" class="btn-login">Ingresar al panel →</button>
        </form>
        <p style="text-align:center;margin-top:16px;font-size:11px;color:var(--text3)">
          ¿Problemas de acceso? Usa tu código de emergencia en el campo de contraseña.
        </p>
      </div>
    </div>

  <?php else: ?>
    <!-- ═══ PANEL ADMIN ═══ -->
    <?php
    $nivel = $adminUser['nivel'];
    $nombre = $adminUser['nombre'];
    $ini = strtoupper(mb_substr($nombre, 0, 1));

    // Calcular permisos efectivos
    $esSA = $nivel === 'superadmin';
    $esAD = $nivel === 'admin';
    // Si el admin delegado no tiene NINGÚN permiso configurado (BD vacía), darle todos por defecto
    $sinPermsConfig = $esAD && empty(array_filter([
      $adminUser['perm_usuarios'] ?? null,
      $adminUser['perm_empleos'] ?? null,
      $adminUser['perm_verificar'] ?? null,
      $adminUser['perm_mensajes'] ?? null,
      $adminUser['perm_badges'] ?? null,
      $adminUser['perm_talentos'] ?? null,
      $adminUser['perm_stats'] ?? null,
      $adminUser['perm_simulador'] ?? null,
    ], fn($v) => $v !== null));
    $perms = [
      'usuarios' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_usuarios'])),
      'empleos' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_empleos'])),
      'verificar' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_verificar'])),
      'solicitudes' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_solicitudes'])),
      'mensajes' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_mensajes'])),
      'stats' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_stats'])),
      'badges' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_badges'])),
      'convocatorias' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_convocatorias'])),
      'actividad' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_actividad'])),
      'auditoria' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_auditoria'])),
      'documentos' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_documentos'])),
      'talentos' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_talentos'])),
      'roles' => $esSA,
      'simulador' => $esSA || $sinPermsConfig || (!$esSA && $esAD && !empty($adminUser['perm_simulador'])),
    ];
    ?>

    <div class="sb-overlay" id="sbOverlay" onclick="cerrarSidebar()"></div>
    <aside class="sidebar" id="sidebarEl">
      <div class="sb-brand">
        <img src="Imagenes/Quibdo.png" alt="Logo">
        <div>
          <div class="sb-brand-text">Quibdó<em>Conecta</em></div>
          <div class="sb-brand-sub">admin panel</div>
        </div>
      </div>
      <div class="sb-admin">
        <div class="sb-avatar-wrap" onclick="document.getElementById('foto-input').click()" title="Cambiar foto"
          style="cursor:pointer;position:relative">
          <?php
          try {
            $fotoStmt = getDB()->prepare("SELECT foto FROM usuarios WHERE id = ?");
            $fotoStmt->execute([$adminUser['id'] ?? 2]);
            $fotoRow = $fotoStmt->fetch();
            $fotoAdmin = $fotoRow['foto'] ?? '';
          } catch (Exception $e) {
            $fotoAdmin = '';
          }
          ?>
          <?php if ($fotoAdmin && file_exists(__DIR__ . '/uploads/fotos/' . $fotoAdmin)): ?>
            <img src="uploads/fotos/<?= htmlspecialchars($fotoAdmin) ?>"
              style="width:40px;height:40px;border-radius:10px;object-fit:cover;border:2px solid var(--green)">
          <?php else: ?>
            <div class="sb-avatar"><?= $ini ?></div>
          <?php endif; ?>
          <div
            style="position:absolute;bottom:-2px;right:-2px;width:16px;height:16px;background:var(--green);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px">
            📷</div>
        </div>
        <input type="file" id="foto-input" accept="image/*" style="display:none" onchange="subirFotoAdmin(this)">
        <div class="sb-admin-info">
          <div class="name"><?= htmlspecialchars($nombre) ?></div>
          <div class="nivel"><?= $nivel ?></div>
        </div>
      </div>
      <nav class="sb-nav">
        <div class="sb-section">Principal</div>
        <button class="sb-item active" data-tip="Dashboard" onclick="irA('dashboard')" id="nav-dashboard">
          <span class="ic">📊</span><span>Dashboard</span>
        </button>
        <?php if ($perms['solicitudes']): ?>
          <button class="sb-item" data-tip="Solicitudes" onclick="irA('solicitudes')" id="nav-solicitudes">
            <span class="ic">📋</span><span>Solicitudes</span>
            <span class="sb-badge" id="badge-solic" style="display:none">0</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['verificar']): ?>
          <button class="sb-item" data-tip="Verificaciones" onclick="irA('verificaciones')" id="nav-verificaciones">
            <span class="ic">✅</span><span>Verificaciones</span>
            <span class="sb-badge" id="badge-verif" style="display:none">0</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['documentos']): ?>
          <button class="sb-item" data-tip="Documentos" onclick="irA('documentos')" id="nav-documentos">
            <span class="ic">🗂️</span><span>Documentos</span>
          </button>
          <button class="sb-item" data-tip="Papelera" onclick="irA('papelera')" id="nav-papelera">
            <span class="ic">🗑️</span><span>Papelera <span id="badge-papelera"
                style="display:none;background:var(--red);color:white;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:4px">0</span></span>
          </button>
        <?php endif; ?>
        <div class="sb-section">Gestión</div>
        <?php if ($perms['usuarios']): ?>
          <button class="sb-item" data-tip="Usuarios" onclick="irA('usuarios')" id="nav-usuarios">
            <span class="ic">👥</span><span>Usuarios</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['empleos']): ?>
          <button class="sb-item" data-tip="Empleos" onclick="irA('empleos')" id="nav-empleos">
            <span class="ic">💼</span><span>Empleos</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['convocatorias']): ?>
          <button class="sb-item" data-tip="Convocatorias" onclick="irA('convocatorias')" id="nav-convocatorias">
            <span class="ic">📋</span><span>Convocatorias</span>
          </button>
        <?php endif; ?>
        <div class="sb-section">Sistema</div>
        <?php if ($perms['mensajes']): ?>
          <button class="sb-item" data-tip="Mensajes" onclick="irA('mensajes')" id="nav-mensajes">
            <span class="ic">💬</span><span>Mensajes</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['actividad']): ?>
          <button class="sb-item" data-tip="Actividad" onclick="irA('actividad')" id="nav-actividad">
            <span class="ic">📋</span><span>Actividad</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['badges']): ?>
          <button class="sb-item" data-tip="Badges" onclick="irA('badges')" id="nav-badges">
            <span class="ic">🏅</span><span>Badges</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['stats']): ?>
          <button class="sb-item" data-tip="Estadísticas" onclick="irA('estadisticas')" id="nav-estadisticas">
            <span class="ic">📈</span><span>Estadísticas</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['auditoria']): ?>
          <button class="sb-item" data-tip="Auditoría" onclick="irA('auditoria')" id="nav-auditoria">
            <span class="ic">🕵️</span><span>Auditoría</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['simulador']): ?>
          <button class="sb-item" data-tip="Simulador" onclick="irA('simulador')" id="nav-simulador">
            <span class="ic">💹</span><span>Simulador</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['roles']): ?>
          <button class="sb-item" data-tip="Sistema" onclick="irA('sistema')" id="nav-sistema">
            <span class="ic">⚙️</span><span>Sistema</span>
          </button>
          <button class="sb-item" data-tip="Roles" onclick="irA('roles')" id="nav-roles">
            <span class="ic">👑</span><span>Roles</span>
          </button>
        <?php endif; ?>
        <?php if ($perms['talentos']): ?>
          <button class="sb-item" data-tip="Candidatos" onclick="irA('candidatos')" id="nav-candidatos">
            <span class="ic">👤</span><span>Candidatos</span>
          </button>
          <button class="sb-item" data-tip="Empresas Dir." onclick="irA('empresas_dir')" id="nav-empresas_dir">
            <span class="ic">🏢</span><span>Empresas Dir.</span>
          </button>
          <button class="sb-item" data-tip="Servicios" onclick="irA('servicios_dir')" id="nav-servicios_dir">
            <span class="ic">🛠️</span><span>Servicios</span>
          </button>
          <button class="sb-item" data-tip="Negocios" onclick="irA('negocios_dir')" id="nav-negocios_dir">
            <span class="ic">🏪</span><span>Negocios</span>
          </button>
        <?php endif; ?>
      </nav>
      <div class="sb-bottom">
        <a href="?salir=1" class="sb-logout">
          <span class="ic">🚪</span><span>Salir</span>
        </a>
      </div>
    </aside>

    <div class="main">
      <header class="topbar">
        <div>
          <div class="topbar-title" id="topbar-title">Dashboard</div>
          <div class="topbar-sub" id="topbar-sub">Vista general del sistema</div>
        </div>
        <div class="topbar-right">
          <button class="btn-hamburger" id="btnHamburger" onclick="toggleSidebar()" style="display:none"
            aria-label="Menú">
            <span></span><span></span><span></span>
          </button>
          <?php if (!empty($_SESSION['admin_emergencia'])): ?>
            <span
              style="background:var(--amber-bg);border:1px solid rgba(255,171,0,.3);color:var(--amber);font-size:11px;font-weight:700;padding:4px 10px;border-radius:8px;font-family:'JetBrains Mono',monospace">⚡
              EMERGENCIA</span>
          <?php endif; ?>
          <span class="tb-time" id="reloj"></span>
          <button class="tb-btn" onclick="recargarSeccion()">↻ Actualizar</button>
        </div>
      </header>

      <div class="content">

        <!-- ═══ DASHBOARD ═══ -->
        <div class="section active" id="section-dashboard">
          <div class="loading" id="loading-dashboard"><span class="spin">⚙️</span><br>Cargando...</div>
          <div id="dashboard-content" style="display:none">

            <!-- ALERTAS URGENTES -->
            <div id="db-alertas" style="display:none;margin-bottom:24px"></div>

            <!-- MÉTRICAS PRINCIPALES -->
            <div
              style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:28px"
              id="metrics-grid"></div>

            <!-- FILA INFERIOR: gráfica + acciones rápidas -->
            <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start" id="db-bottom-row">

              <!-- GRÁFICA -->
              <div style="background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:24px">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                  <div>
                    <div style="font-weight:700;font-size:15px">📈 Nuevos registros</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px">Últimos 7 días</div>
                  </div>
                  <div id="db-total-semana"
                    style="font-size:22px;font-weight:700;font-family:'JetBrains Mono',monospace;color:var(--green)">
                  </div>
                </div>
                <div class="chart-bars" id="chart-bars" style="height:90px"></div>
              </div>

              <!-- ACCIONES RÁPIDAS -->
              <div style="background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:20px">
                <div style="font-weight:700;font-size:14px;margin-bottom:16px;color:var(--text2)">⚡ Acciones rápidas</div>
                <div style="display:flex;flex-direction:column;gap:8px">
                  <button onclick="irA('solicitudes')" class="db-action-btn" style="--ac:#10b981">
                    <span>📋</span>
                    <div>
                      <div style="font-weight:600;font-size:13px">Ver solicitudes</div>
                      <div style="font-size:11px;color:var(--text3)" id="db-btn-solic">–</div>
                    </div>
                    <span style="margin-left:auto;color:var(--text3)">›</span>
                  </button>
                  <button onclick="irA('verificaciones')" class="db-action-btn" style="--ac:#f59e0b">
                    <span>✅</span>
                    <div>
                      <div style="font-weight:600;font-size:13px">Verificaciones</div>
                      <div style="font-size:11px;color:var(--text3)" id="db-btn-verif">–</div>
                    </div>
                    <span style="margin-left:auto;color:var(--text3)">›</span>
                  </button>
                  <button onclick="irA('usuarios')" class="db-action-btn" style="--ac:#3b82f6">
                    <span>👥</span>
                    <div>
                      <div style="font-weight:600;font-size:13px">Usuarios</div>
                      <div style="font-size:11px;color:var(--text3)" id="db-btn-usuarios">–</div>
                    </div>
                    <span style="margin-left:auto;color:var(--text3)">›</span>
                  </button>
                  <button onclick="irA('empleos')" class="db-action-btn" style="--ac:#8b5cf6">
                    <span>💼</span>
                    <div>
                      <div style="font-weight:600;font-size:13px">Empleos</div>
                      <div style="font-size:11px;color:var(--text3)" id="db-btn-empleos">–</div>
                    </div>
                    <span style="margin-left:auto;color:var(--text3)">›</span>
                  </button>
                  <?php if (in_array($nivel, ['superadmin', 'admin'])): ?>
                    <button onclick="irA('documentos')" class="db-action-btn" style="--ac:#06b6d4">
                      <span>🗂️</span>
                      <div>
                        <div style="font-weight:600;font-size:13px">Documentos</div>
                        <div style="font-size:11px;color:var(--text3)">Repositorio</div>
                      </div>
                      <span style="margin-left:auto;color:var(--text3)">›</span>
                    </button>
                  <?php endif; ?>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ═══ DOCUMENTOS ═══ (solo superadmin y admin) -->
        <?php if (in_array($nivel, ['superadmin', 'admin'])): ?>
          <div class="section" id="section-documentos">
            <div class="section-header">
              <h2>🗂️ Repositorio de documentos</h2>
              <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <input type="text" id="docs-buscar" placeholder="🔍 Buscar por nombre o correo..."
                  oninput="debounce(cargarDocumentos,400)()"
                  style="padding:8px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:13px;outline:none;width:240px">
                <select id="docs-tipo" onchange="cargarDocumentos()"
                  style="padding:8px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:13px;outline:none">
                  <option value="">Todos los tipos</option>
                  <option value="cedula">Cédula</option>
                  <option value="camara_comercio">Cámara de comercio</option>
                </select>
                <select id="docs-estado" onchange="cargarDocumentos()"
                  style="padding:8px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;color:var(--text);font-size:13px;outline:none">
                  <option value="">Todos los estados</option>
                  <option value="pendiente">Pendiente</option>
                  <option value="aprobado">Aprobado</option>
                  <option value="rechazado">Rechazado</option>
                </select>
              </div>
            </div>
            <div id="docs-stats" style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap"></div>
            <div id="docs-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
            </div>
            <div id="docs-empty" style="display:none" class="empty-state"><span class="ei">🗂️</span>
              <p>No hay documentos</p>
            </div>
            <div id="docs-loading" class="loading"><span class="spin">⚙️</span></div>
            <div id="docs-pagination" style="display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap">
            </div>
          </div>
        <?php endif; ?>

        <!-- ═══ PAPELERA DE DOCUMENTOS ═══ -->
        <?php if ($perms['documentos']): ?>
          <div class="section" id="section-papelera">
            <div class="section-header">
              <h2>🗑️ Papelera de documentos</h2>
              <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <span id="papelera-count-label" style="font-size:13px;color:var(--text2)">Cargando...</span>
                <?php if ($adminUser['nivel'] === 'superadmin'): ?>
                  <button onclick="vaciarPapelera()" id="btn-vaciar-papelera"
                    style="padding:8px 16px;background:var(--red-bg);border:1px solid rgba(255,68,68,.3);border-radius:10px;color:var(--red);font-size:13px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">
                    🔥 Vaciar papelera
                  </button>
                <?php endif; ?>
              </div>
            </div>
            <div id="papelera-empty" style="display:none" class="empty-state">
              <span class="ei">🗑️</span>
              <p>La papelera está vacía</p>
            </div>
            <div id="papelera-loading" class="loading"><span class="spin">⚙️</span></div>
            <div id="papelera-grid"
              style="display:none;display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px"></div>
            <div id="papelera-pagination"
              style="display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap"></div>
          </div>
        <?php endif; ?>

        <!-- ═══ SOLICITUDES DE INGRESO ═══ -->
        <div class="section" id="section-solicitudes">
          <div class="section-header">
            <h2>📋 Solicitudes de ingreso</h2>
            <div class="verif-tabs">
              <button class="filter-btn active" onclick="cargarSolicitudes('pendiente',this)">⏳ Pendientes</button>
              <button class="filter-btn" onclick="cargarSolicitudes('aprobado',this)">✅ Aprobadas</button>
              <button class="filter-btn" onclick="cargarSolicitudes('rechazado',this)">❌ Rechazadas</button>
            </div>
          </div>
          <div id="solic-list">
            <div class="loading"><span class="spin">⚙️</span></div>
          </div>
        </div>

        <!-- ═══ VERIFICACIONES ═══ -->
        <div class="section" id="section-verificaciones">
          <div class="section-header">
            <h2>✅ Verificaciones de cuenta</h2>
            <div class="verif-tabs">
              <button class="filter-btn active" onclick="cargarVerificaciones('pendiente',this)">⏳ Pendientes</button>
              <button class="filter-btn" onclick="cargarVerificaciones('aprobado',this)">✅ Aprobadas</button>
              <button class="filter-btn" onclick="cargarVerificaciones('rechazado',this)">❌ Rechazadas</button>
            </div>
          </div>
          <div id="verif-list">
            <div class="loading"><span class="spin">⚙️</span></div>
          </div>
        </div>

        <!-- ═══ USUARIOS ═══ -->
        <div class="section" id="section-usuarios">
          <div class="section-header">
            <h2>👥 Gestión de usuarios</h2>
            <div class="search-box">
              <span>🔍</span>
              <input type="text" id="buscar-usuario" placeholder="Buscar por nombre o correo..."
                oninput="debounce(cargarUsuarios,400)()">
            </div>
            <div class="filter-btns">
              <button class="filter-btn active" onclick="filtroTipo('',this)">Todos</button>
              <button class="filter-btn" onclick="filtroTipo('candidato',this)">Candidatos</button>
              <button class="filter-btn" onclick="filtroTipo('empresa',this)">Empresas</button>
            </div>
          </div>
          <!-- Cards de usuarios -->
          <div id="usuarios-cards"
            style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px"></div>
          <div id="usuarios-empty" style="display:none" class="empty-state"><span class="ei">👥</span>
            <p>Sin resultados</p>
          </div>
          <div id="usuarios-loading" class="loading"><span class="spin">⚙️</span></div>
          <div class="pagination" id="usuarios-pagination"></div>
        </div>

        <!-- MODAL EDITAR USUARIO -->
        <div id="modal-usuario"
          style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
          <div
            style="background:var(--bg2);border:1px solid var(--border2);border-radius:20px;padding:32px;width:100%;max-width:560px;position:relative;margin:auto">
            <button onclick="cerrarModal()"
              style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer">✕</button>
            <h3 style="font-size:18px;font-weight:700;margin-bottom:20px">✏️ Editar usuario <span id="modal-uid-label"
                style="color:var(--text3);font-size:14px"></span></h3>
            <input type="hidden" id="modal-uid">

            <!-- Info de sesiones (solo lectura) -->
            <div id="modal-sesion-info"
              style="background:var(--bg3);border-radius:10px;padding:12px 16px;margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px">
              <div><span style="color:var(--text3)">Último ingreso:</span><br><span id="modal-ultima-sesion"
                  style="color:var(--green);font-family:'JetBrains Mono',monospace">—</span></div>
              <div><span style="color:var(--text3)">Última salida:</span><br><span id="modal-ultima-salida"
                  style="color:var(--text2);font-family:'JetBrains Mono',monospace">—</span></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
              <div>
                <label id="modal-nombre-label"
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Nombre</label>
                <input type="text" id="modal-nombre"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
              <div>
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Apellido</label>
                <input type="text" id="modal-apellido"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
            </div>
            <div style="margin-bottom:12px">
              <label
                style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Correo</label>
              <input type="email" id="modal-correo"
                style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
              <div>
                <label id="modal-cedula-label"
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Cédula</label>
                <input type="text" id="modal-cedula"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'JetBrains Mono',monospace;outline:none">
              </div>
              <div>
                <label id="modal-fnac-label"
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Fecha
                  de nacimiento</label>
                <input type="date" id="modal-fnac"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
              <div>
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Ciudad</label>
                <input type="text" id="modal-ciudad"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
              <div>
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Teléfono</label>
                <input type="text" id="modal-telefono"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
            </div>
            <div style="margin-bottom:20px">
              <label
                style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Tipo
                de cuenta</label>
              <select id="modal-tipo"
                style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                <option value="candidato">👤 Candidato</option>
                <option value="empresa">🏢 Empresa</option>
              </select>
            </div>
            <div id="toggles-visibilidad" style="display:flex;gap:10px;margin-bottom:16px">
              <div
                style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none"
                onclick="toggleSwitch('sw-talentos')">
                <div>
                  <div style="font-size:12px;font-weight:700;color:var(--text)">🌟 En Talentos</div>
                  <div style="font-size:11px;color:var(--text2);margin-top:2px">Aparece en talentos.php</div>
                </div>
                <div id="sw-talentos" data-on="0"
                  style="width:44px;height:24px;border-radius:24px;background:rgba(255,255,255,0.15);position:relative;transition:background .25s;flex-shrink:0">
                  <div id="sw-talentos-dot"
                    style="position:absolute;width:18px;height:18px;border-radius:50%;background:white;top:3px;left:3px;transition:transform .25s;pointer-events:none">
                  </div>
                </div>
              </div>
              <div
                style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none"
                onclick="toggleSwitch('sw-destacado')">
                <div>
                  <div style="font-size:12px;font-weight:700;color:var(--text)">⭐ Destacado</div>
                  <div style="font-size:11px;color:var(--text2);margin-top:2px">Primero en búsquedas</div>
                </div>
                <div id="sw-destacado" data-on="0"
                  style="width:44px;height:24px;border-radius:24px;background:rgba(255,255,255,0.15);position:relative;transition:background .25s;flex-shrink:0">
                  <div id="sw-destacado-dot"
                    style="position:absolute;width:18px;height:18px;border-radius:50%;background:white;top:3px;left:3px;transition:transform .25s;pointer-events:none">
                  </div>
                </div>
              </div>
            </div>
            <div style="display:flex;gap:10px">
              <button onclick="guardarUsuario()"
                style="flex:1;padding:12px;background:linear-gradient(135deg,var(--green2),var(--green));border:none;border-radius:10px;color:#000;font-size:14px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">💾
                Guardar cambios</button>
              <button onclick="cerrarModal()"
                style="padding:12px 20px;background:transparent;border:1px solid var(--border);border-radius:10px;color:var(--text2);font-size:14px;cursor:pointer;font-family:'Space Grotesk',sans-serif">Cancelar</button>
            </div>
            <p id="modal-msg" style="font-size:12px;margin-top:10px;text-align:center;display:none"></p>
          </div>
        </div>

        <!-- ═══ EMPLEOS ═══ -->
        <div class="section" id="section-empleos">
          <div class="section-header">
            <h2>💼 Gestión de empleos</h2>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Título</th>
                  <th>Empresa</th>
                  <th>Estado</th>
                  <th>En index</th>
                  <th>Fecha</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody id="empleos-tbody">
                <tr>
                  <td colspan="7" class="loading"><span class="spin">⚙️</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ═══ CONVOCATORIAS ═══ -->
        <div class="section" id="section-convocatorias">
          <div class="section-header">
            <h2>📋 Convocatorias públicas</h2>
          </div>
          <!-- Filtros de origen -->
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
            <button onclick="cargarConvocatorias('todas',this)" id="conv-tab-todas"
              style="padding:7px 16px;border-radius:20px;border:1px solid var(--green);background:var(--green2);color:#000;font-size:12px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">
              Todas
            </button>
            <button onclick="cargarConvocatorias('pendiente',this)" id="conv-tab-pendiente"
              style="padding:7px 16px;border-radius:20px;border:1px solid var(--border2);background:var(--bg2);color:var(--text);font-size:12px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">
              ⏳ Pendientes de empresa
            </button>
            <button onclick="cargarConvocatorias('empresa',this)" id="conv-tab-empresa"
              style="padding:7px 16px;border-radius:20px;border:1px solid var(--border2);background:var(--bg2);color:var(--text);font-size:12px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">
              🏢 De empresas
            </button>
            <button onclick="cargarConvocatorias('admin',this)" id="conv-tab-admin"
              style="padding:7px 16px;border-radius:20px;border:1px solid var(--border2);background:var(--bg2);color:var(--text);font-size:12px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">
              🛡️ Del admin
            </button>
          </div>
          <div id="conv-loading" class="loading" style="display:none"><span class="spin">⚙️</span></div>
          <div class="table-wrap" id="conv-table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Título</th>
                  <th>Entidad</th>
                  <th>Origen</th>
                  <th>Empresa / Creador</th>
                  <th>Vacantes</th>
                  <th>Estado</th>
                  <th>Vence</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody id="convocatorias-tbody">
                <tr>
                  <td colspan="8" class="loading"><span class="spin">⚙️</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <?php if (in_array($nivel, ['superadmin', 'admin'])): ?>
          <!-- ═══ ACTIVIDAD ═══ -->
          <div class="section" id="section-actividad">
            <div class="section-header">
              <h2>📋 Registro de actividad</h2>
              <div class="filter-btns" id="act-filtros">
                <button class="filter-btn active" onclick="filtroActividad('',this)">Todas</button>
                <button class="filter-btn" onclick="filtroActividad('editar_usuario',this)">Ediciones</button>
                <button class="filter-btn" onclick="filtroActividad('badge_toggle',this)">Badges</button>
                <button class="filter-btn" onclick="filtroActividad('toggle_usuario',this)">Activaciones</button>
                <button class="filter-btn" onclick="filtroActividad('cambiar_contrasena',this)">Contraseñas</button>
              </div>
            </div>
            <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:24px">
              <div class="act-feed" id="act-feed">
                <div class="loading"><span class="spin">⚙️</span></div>
              </div>
              <div class="pagination" id="act-pagination"></div>
            </div>
          </div>
        <?php endif; ?>

        <!-- ═══ BADGES ═══ -->
        <div class="section" id="section-badges">
          <div class="section-header">
            <h2>🏅 Sistema de Badges</h2>
            <button onclick="abrirCrearBadge()"
              style="padding:10px 18px;background:linear-gradient(135deg,var(--green2),var(--green));border:none;border-radius:10px;color:#000;font-size:13px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">➕
              Crear badge</button>
          </div>

          <!-- Catálogo de badges -->
          <div id="badges-catalogo-wrap" style="margin-bottom:28px">
            <div class="loading"><span class="spin">⚙️</span></div>
          </div>

          <!-- Asignar badge a usuario -->
          <div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:24px">
            <h3 style="font-size:15px;font-weight:700;margin-bottom:16px">🎯 Asignar/quitar badge a usuario</h3>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
              <input type="number" id="badge-uid" placeholder="ID del usuario"
                style="padding:10px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none;width:160px">
              <button onclick="cargarBadgesUsuario()"
                style="padding:10px 18px;background:var(--blue-bg);border:1px solid rgba(68,136,255,.3);border-radius:8px;color:var(--blue);font-size:13px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">🔍
                Cargar usuario</button>
            </div>
            <div id="badges-usuario-extra"></div>
            <div id="badges-usuario-panel" style="display:none">
              <div id="badges-usuario-info"
                style="margin-bottom:16px;padding:12px 16px;background:var(--bg3);border-radius:10px;font-size:13px">
              </div>
              <div id="badges-usuario-grid"
                style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px"></div>
            </div>
          </div>
        </div>

        <!-- MODAL CREAR/EDITAR BADGE -->
        <div id="modal-badge"
          style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;padding:20px">
          <div
            style="background:var(--bg2);border:1px solid var(--border2);border-radius:20px;padding:32px;width:100%;max-width:480px;position:relative">
            <button onclick="cerrarModalBadge()"
              style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer">✕</button>
            <h3 style="font-size:17px;font-weight:700;margin-bottom:20px" id="modal-badge-titulo">➕ Crear badge</h3>
            <input type="hidden" id="badge-edit-id">
            <div style="display:grid;grid-template-columns:80px 1fr;gap:12px;margin-bottom:12px">
              <div>
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Emoji</label>
                <input type="text" id="badge-emoji" maxlength="4" placeholder="🏅"
                  style="width:100%;padding:10px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:22px;text-align:center;outline:none">
              </div>
              <div>
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Nombre</label>
                <input type="text" id="badge-nombre" placeholder="Ej: Premium Gold"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
            </div>
            <div style="margin-bottom:12px">
              <label
                style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Descripción</label>
              <input type="text" id="badge-desc" placeholder="Descripción breve del badge"
                style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
              <div>
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Color</label>
                <div style="display:flex;gap:8px;align-items:center">
                  <input type="color" id="badge-color" value="#00e676"
                    style="width:44px;height:40px;border:none;background:none;cursor:pointer;border-radius:8px">
                  <input type="text" id="badge-color-text" value="#00e676" placeholder="#00e676"
                    style="flex:1;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'JetBrains Mono',monospace;outline:none"
                    oninput="document.getElementById('badge-color').value=this.value">
                </div>
              </div>
              <div>
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Tipo</label>
                <select id="badge-tipo"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none;height:40px">
                  <option value="manual">🏅 Manual</option>
                  <option value="pago">💰 Pago</option>
                  <option value="verificacion">✅ Verificación</option>
                </select>
              </div>
            </div>
            <!-- Beneficios -->
            <div style="margin-bottom:12px">
              <label
                style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">💡
                Beneficios <span style="font-weight:400;color:var(--text3)">(uno por línea)</span></label>
              <textarea id="badge-beneficios" rows="5"
                placeholder="Escribe un beneficio por línea, ej:&#10;✅ Perfil destacado en el directorio&#10;📊 Estadísticas de visitas&#10;💬 Mensajes directos"
                style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:12px;font-family:'Space Grotesk',sans-serif;outline:none;resize:vertical;line-height:1.6;box-sizing:border-box"></textarea>
              <p style="font-size:10px;color:var(--text3);margin-top:4px">Se mostrarán como lista en la tarjeta del badge.
              </p>
            </div>
            <!-- Preview -->
            <div style="margin-bottom:20px;padding:14px;background:var(--bg3);border-radius:10px;text-align:center">
              <p style="font-size:11px;color:var(--text3);margin-bottom:8px">PREVIEW</p>
              <div id="badge-preview"
                style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;border:1px solid">
                ⭐ Badge</div>
            </div>
            <button onclick="guardarBadge()"
              style="width:100%;padding:12px;background:linear-gradient(135deg,var(--green2),var(--green));border:none;border-radius:10px;color:#000;font-size:14px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">💾
              Guardar badge</button>
            <p id="badge-msg" style="font-size:12px;margin-top:10px;text-align:center;display:none"></p>
          </div>
        </div>

        <!-- ═══ ESTADÍSTICAS ═══ -->
        <div class="section" id="section-estadisticas">
          <div class="section-header">
            <h2>📈 Estadísticas detalladas</h2>
          </div>
          <div id="stats-content">
            <div class="loading"><span class="spin">⚙️</span></div>
          </div>
        </div>

        <!-- ═══ AUDITORÍA ═══ -->
        <div class="section" id="section-auditoria">
          <div class="section-header">
            <h2>🕵️ Historial de auditoría</h2>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Admin</th>
                  <th>Acción</th>
                  <th>Detalle</th>
                  <th>Fecha</th>
                </tr>
              </thead>
              <tbody id="auditoria-tbody">
                <tr>
                  <td colspan="5" class="loading"><span class="spin">⚙️</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="pagination" id="auditoria-pagination"></div>
        </div>

        <!-- ═══ MENSAJES / HISTORIAL BACKUP ═══ -->
        <div class="section" id="section-mensajes">
          <div class="section-header">
            <h2>💬 Historial de Chat — Backup</h2>
          </div>

          <!-- Stats cards -->
          <div id="chat-stats-row"
            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
            <div class="stat-mini" id="cs-total"><span class="sm-val">…</span><span class="sm-lbl">Total mensajes</span>
            </div>
            <div class="stat-mini" id="cs-hoy"><span class="sm-val">…</span><span class="sm-lbl">Hoy</span></div>
            <div class="stat-mini" id="cs-semana"><span class="sm-val">…</span><span class="sm-lbl">Esta semana</span>
            </div>
            <div class="stat-mini" id="cs-convs"><span class="sm-val">…</span><span class="sm-lbl">Conversaciones</span>
            </div>
          </div>

          <!-- Buscador -->
          <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
            <input id="msg-search" type="text" placeholder="🔍 Buscar por usuario o contenido…"
              oninput="clearTimeout(window._msgT);window._msgT=setTimeout(()=>cargarMensajes(1),400)"
              style="flex:1;min-width:200px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border);border-radius:10px;color:var(--text1);font-size:13px;outline:none;">
            <button onclick="exportarCSV()"
              style="padding:10px 16px;background:var(--green);color:white;border:none;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;">⬇
              Exportar CSV</button>
          </div>

          <!-- Tabla principal -->
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th style="width:60px">ID</th>
                  <th>De</th>
                  <th>Para</th>
                  <th>Mensaje</th>
                  <th style="width:90px">Leído</th>
                  <th style="width:130px">Fecha</th>
                  <th style="width:80px">Ver chat</th>
                </tr>
              </thead>
              <tbody id="mensajes-tbody">
                <tr>
                  <td colspan="7" class="loading"><span class="spin">⚙️</span></td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginación -->
          <div id="msg-pagination" style="display:flex;gap:8px;justify-content:center;margin-top:16px;flex-wrap:wrap;">
          </div>

          <!-- Top usuarios -->
          <div style="margin-top:28px;">
            <h3 style="font-size:14px;font-weight:700;color:var(--text2);margin-bottom:12px;">🏆 Usuarios más activos</h3>
            <div id="top-usuarios" style="display:flex;gap:10px;flex-wrap:wrap;"></div>
          </div>
        </div>

        <!-- Modal conversación completa -->
        <div id="modal-conv"
          style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:900;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);">
          <div
            style="background:var(--bg2);border:1px solid var(--border);border-radius:18px;width:100%;max-width:600px;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 40px 100px rgba(0,0,0,.6);">
            <div
              style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
              <div>
                <div id="conv-title" style="font-size:15px;font-weight:800;color:var(--text1);">Conversación</div>
                <div id="conv-sub" style="font-size:12px;color:var(--text3);margin-top:2px;"></div>
              </div>
              <div style="display:flex;gap:8px;align-items:center;">
                <button id="conv-export-btn" onclick="exportarConvCSV()"
                  style="padding:7px 13px;background:var(--green);color:white;border:none;border-radius:9px;font-size:12px;font-weight:700;cursor:pointer;">⬇
                  CSV</button>
                <button onclick="document.getElementById('modal-conv').style.display='none'"
                  style="background:rgba(255,255,255,.08);border:1px solid var(--border);border-radius:50%;width:32px;height:32px;color:var(--text2);font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
              </div>
            </div>
            <div id="conv-messages"
              style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:8px;"></div>
          </div>
        </div>

        <?php if ($nivel === 'superadmin'): ?>
          <!-- ═══ ROLES ═══ -->
          <div class="section" id="section-roles">
            <div class="section-header">
              <h2>👑 Gestión de roles y permisos</h2>
            </div>
            <div id="roles-list">
              <div class="loading"><span class="spin">⚙️</span></div>
            </div>

            <!-- Asignar nuevo rol -->
            <div
              style="margin-top:24px;background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:24px">
              <h3 style="font-size:15px;margin-bottom:16px;color:var(--amber)">➕ Asignar nuevo rol</h3>
              <!-- Buscador por correo/nombre -->
              <div style="position:relative;margin-bottom:14px">
                <input type="text" id="rol-buscar" placeholder="🔍 Buscar por nombre o correo..."
                  oninput="buscarUsuarioRol(this.value)"
                  style="width:100%;padding:10px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                <div id="rol-sugerencias"
                  style="display:none;position:absolute;top:100%;left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:10px;z-index:99;max-height:220px;overflow-y:auto;margin-top:4px;box-shadow:0 8px 24px rgba(0,0,0,.4)">
                </div>
              </div>
              <!-- Preview usuario seleccionado -->
              <div id="rol-preview"
                style="display:none;padding:12px 14px;background:var(--bg3);border:1px solid rgba(0,230,118,.2);border-radius:10px;margin-bottom:14px">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                  <div id="rol-preview-info" style="flex:1;font-size:13px"></div>
                  <button onclick="limpiarSeleccionRol()"
                    style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:18px;line-height:1">×</button>
                </div>
              </div>
              <input type="hidden" id="rol-uid">
              <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                <select id="rol-nivel"
                  style="padding:10px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                  <option value="admin">Admin Delegado</option>
                  <option value="dev">Dev</option>
                </select>
                <button onclick="asignarRol()" class="btn-sm green" style="padding:10px 20px;font-size:13px">Asignar
                  rol</button>
              </div>
              <p style="font-size:11px;color:var(--text3);margin-top:10px">⚠️ El rol Superadmin (Deivy-x) no puede ser
                modificado.</p>
            </div>

            <!-- Código de emergencia — solo superadmin -->
            <div
              style="margin-top:24px;background:var(--bg2);border:1px solid rgba(255,68,68,.25);border-radius:var(--radius);padding:24px">
              <h3 style="font-size:15px;margin-bottom:6px;color:var(--red)">🚨 Código de acceso de emergencia</h3>
              <p style="font-size:12px;color:var(--text3);margin-bottom:16px">Úsalo en el campo contraseña del login (con
                cualquier correo) cuando no puedas acceder normalmente. Solo tú puedes verlo y cambiarlo.</p>
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap">
                <div
                  style="flex:1;min-width:200px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:12px;font-family:'JetBrains Mono',monospace;font-size:14px;color:var(--amber);letter-spacing:2px"
                  id="emergency-display">••••••••••••••••</div>
                <button onclick="toggleVerEmergencia()" class="btn-sm amber" id="btn-ver-emergency">👁 Ver</button>
                <button onclick="copiarEmergencia()" class="btn-sm" style="border-color:var(--border2)">📋 Copiar</button>
              </div>
              <div style="display:flex;gap:10px;flex-wrap:wrap">
                <input type="text" id="nuevo-emergency" placeholder="Nuevo código (mín. 10 caracteres)"
                  style="flex:1;min-width:180px;padding:10px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                <button onclick="cambiarEmergencia()" class="btn-sm red" style="padding:10px 18px;font-size:13px">🔄 Cambiar
                  código</button>
              </div>
              <p id="emergency-msg" style="font-size:12px;margin-top:10px;display:none"></p>
            </div>
          </div>

        <?php endif; // fin superadmin - roles ?>
        <?php if (in_array($nivel, ['superadmin', 'admin'])): ?>
          <!-- ══ SECTION: CANDIDATOS ══ -->
          <div class="section" id="section-candidatos">
            <div class="section-header">
              <h2>👤 Candidatos</h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
              <input type="text" id="cand-buscar" placeholder="🔍 Buscar nombre, profesión, skills…"
                style="flex:1;min-width:200px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none"
                oninput="cargarCandidatos(1)">
              <select id="cand-visible" onchange="cargarCandidatos(1)"
                style="padding:10px 12px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                <option value="-1">Todos</option>
                <option value="1">Visibles</option>
                <option value="0">Ocultos</option>
              </select>
            </div>
            <div id="cand-loading" class="loading" style="display:none"><span class="spin">⚙️</span></div>
            <div id="cand-table"></div>
            <div id="cand-pagination" style="display:flex;gap:8px;justify-content:center;margin-top:16px;flex-wrap:wrap">
            </div>
          </div>

          <!-- ══ SECTION: EMPRESAS DIRECTORIO ══ -->
          <div class="section" id="section-empresas_dir">
            <div class="section-header">
              <h2>🏢 Directorio de Empresas</h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
              <input type="text" id="empdir-buscar" placeholder="🔍 Buscar nombre, sector…"
                style="flex:1;min-width:200px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none"
                oninput="cargarEmpresasDir(1)">
              <select id="empdir-visible" onchange="cargarEmpresasDir(1)"
                style="padding:10px 12px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                <option value="-1">Todos</option>
                <option value="1">Visibles</option>
                <option value="0">Ocultos</option>
              </select>
            </div>
            <div id="empdir-loading" class="loading" style="display:none"><span class="spin">⚙️</span></div>
            <div id="empdir-table"></div>
            <div id="empdir-pagination" style="display:flex;gap:8px;justify-content:center;margin-top:16px;flex-wrap:wrap">
            </div>
          </div>

          <!-- ══ SECTION: SERVICIOS DIRECTORIO ══ -->
          <div class="section" id="section-servicios_dir">
            <div class="section-header">
              <h2>🛠️ Directorio de Servicios</h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
              <input type="text" id="srvdir-buscar" placeholder="🔍 Buscar nombre, tipo de servicio…"
                style="flex:1;min-width:200px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none"
                oninput="cargarServiciosDir(1)">
              <select id="srvdir-visible" onchange="cargarServiciosDir(1)"
                style="padding:10px 12px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                <option value="-1">Todos</option>
                <option value="1">Visibles</option>
                <option value="0">Ocultos</option>
              </select>
            </div>
            <div id="srvdir-loading" class="loading" style="display:none"><span class="spin">⚙️</span></div>
            <div id="srvdir-table"></div>
            <div id="srvdir-pagination" style="display:flex;gap:8px;justify-content:center;margin-top:16px;flex-wrap:wrap">
            </div>
          </div>

          <!-- ══ SECTION: NEGOCIOS DIRECTORIO ══ -->
          <div class="section" id="section-negocios_dir">
            <div class="section-header">
              <h2>🏪 Directorio de Negocios</h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center">
              <input type="text" id="negdir-buscar" placeholder="🔍 Buscar negocio, categoría…"
                style="flex:1;min-width:200px;padding:10px 14px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none"
                oninput="cargarNegociosDir(1)">
              <select id="negdir-tipo" onchange="cargarNegociosDir(1)"
                style="padding:10px 12px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                <option value="">Todos</option>
                <option value="cc">Centro Comercial</option>
                <option value="emp">Emprendedor</option>
              </select>
              <select id="negdir-visible" onchange="cargarNegociosDir(1)"
                style="padding:10px 12px;background:var(--bg2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                <option value="-1">Todos</option>
                <option value="1">Visibles</option>
                <option value="0">Ocultos</option>
              </select>
            </div>
            <div id="negdir-loading" class="loading" style="display:none"><span class="spin">⚙️</span></div>
            <div id="negdir-table"></div>
            <div id="negdir-pagination" style="display:flex;gap:8px;justify-content:center;margin-top:16px;flex-wrap:wrap">
            </div>
          </div>
        <?php endif; // fin admin|superadmin - directorios ?>
        <?php if ($nivel === 'superadmin'): // modales y contraseña solo superadmin ?>
          <!-- MODAL PERMISOS -->
          <div id="modal-permisos"
            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;padding:20px">
            <div
              style="background:var(--bg2);border:1px solid var(--border2);border-radius:20px;padding:32px;width:100%;max-width:560px;position:relative;max-height:90vh;overflow-y:auto">
              <button onclick="cerrarPermisos()"
                style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer">✕</button>
              <h3 style="font-size:17px;font-weight:700;margin-bottom:4px">🔐 Configurar permisos</h3>
              <p id="permisos-nombre" style="color:var(--text2);font-size:13px;margin-bottom:20px"></p>
              <input type="hidden" id="permisos-uid">
              <!-- Seleccionar todos -->
              <div
                style="display:flex;align-items:center;justify-content:space-between;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:14px">
                <label
                  style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;font-weight:600;color:var(--text)">
                  <input type="checkbox" id="perm-select-all" onchange="toggleTodosPermisos(this.checked)"
                    style="width:16px;height:16px;accent-color:var(--green);cursor:pointer">
                  <span>Seleccionar todos los permisos</span>
                </label>
                <span style="font-size:11px;color:var(--text3)" id="perm-count-label">0 / 0</span>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px" id="permisos-grid"></div>
              <button onclick="guardarPermisos()"
                style="width:100%;padding:12px;background:linear-gradient(135deg,var(--green2),var(--green));border:none;border-radius:10px;color:#000;font-size:14px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">💾
                Guardar permisos</button>
              <p id="permisos-msg" style="font-size:12px;margin-top:10px;text-align:center;display:none"></p>
            </div>
          </div>

          <!-- MODAL VER/CAMBIAR CONTRASEÑA -->
          <div id="modal-pass"
            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;padding:20px">
            <div
              style="background:var(--bg2);border:1px solid var(--border2);border-radius:20px;padding:32px;width:100%;max-width:460px;position:relative">
              <button onclick="cerrarPass()"
                style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer">✕</button>
              <h3 style="font-size:17px;font-weight:700;margin-bottom:4px">🔑 Contraseña de usuario</h3>
              <p id="pass-nombre" style="color:var(--text2);font-size:13px;margin-bottom:20px"></p>
              <input type="hidden" id="pass-uid">
              <div
                style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px">
                <p style="font-size:11px;color:var(--text3);margin-bottom:6px;text-transform:uppercase;letter-spacing:.8px">
                  Hash actual en BD</p>
                <p id="pass-hash"
                  style="font-size:11px;color:var(--amber);font-family:'JetBrains Mono',monospace;word-break:break-all"></p>
              </div>
              <div style="margin-bottom:12px">
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Nueva
                  contraseña</label>
                <input type="password" id="pass-nueva" placeholder="Mínimo 8 caracteres"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
              <div style="margin-bottom:20px">
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Confirmar
                  contraseña</label>
                <input type="password" id="pass-confirma" placeholder="Repite la contraseña"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
              <button onclick="cambiarContrasena()"
                style="width:100%;padding:12px;background:linear-gradient(135deg,#cc2200,var(--red));border:none;border-radius:10px;color:white;font-size:14px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">🔑
                Cambiar contraseña</button>
              <p id="pass-msg" style="font-size:12px;margin-top:10px;text-align:center;display:none"></p>
            </div>
          </div>
        <?php endif; ?>

        <!-- MODAL CONTRASEÑA para usuarios normales (superadmin y admin delegado) -->
        <?php if (in_array($nivel, ['superadmin', 'admin'])): ?>
          <div id="modal-pass-user"
            style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;padding:20px">
            <div
              style="background:var(--bg2);border:1px solid var(--border2);border-radius:20px;padding:32px;width:100%;max-width:460px;position:relative">
              <button onclick="cerrarPassUser()"
                style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer">✕</button>
              <h3 style="font-size:17px;font-weight:700;margin-bottom:4px">🔑 Gestión de contraseña</h3>
              <p id="pass-user-nombre" style="color:var(--text2);font-size:13px;margin-bottom:16px"></p>
              <input type="hidden" id="pass-user-uid">
              <div
                style="background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:16px">
                <p style="font-size:11px;color:var(--text3);margin-bottom:6px;text-transform:uppercase;letter-spacing:.8px">
                  Hash actual</p>
                <p id="pass-user-hash"
                  style="font-size:11px;color:var(--amber);font-family:'JetBrains Mono',monospace;word-break:break-all">
                  Cargando...</p>
              </div>
              <div style="margin-bottom:12px">
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Nueva
                  contraseña</label>
                <input type="password" id="pass-user-nueva" placeholder="Mínimo 8 caracteres"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
              <div style="margin-bottom:20px">
                <label
                  style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Confirmar</label>
                <input type="password" id="pass-user-confirma" placeholder="Repite la contraseña"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
              </div>
              <button onclick="cambiarContrasenaUser()"
                style="width:100%;padding:12px;background:linear-gradient(135deg,#cc2200,var(--red));border:none;border-radius:10px;color:white;font-size:14px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">🔑
                Cambiar contraseña</button>
              <p id="pass-user-msg" style="font-size:12px;margin-top:10px;text-align:center;display:none"></p>
            </div>
          </div>
        <?php endif; ?>

        <!-- ── SIMULADOR DE INGRESOS ── -->
        <?php if ($perms['simulador']): ?>
          <div class="section" id="section-simulador">
            <div style="background:#0d1a0d;min-height:100%;padding:32px;border-radius:16px">
              <div
                style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:28px">
                <div>
                  <div
                    style="display:inline-block;background:rgba(31,107,58,.3);color:#5cd98a;font-size:11px;font-weight:700;padding:4px 14px;border-radius:20px;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px">
                    🔒 Solo superadmin y admin</div>
                  <h2 style="font-family:'Space Grotesk',sans-serif;font-size:26px;font-weight:800;color:white;margin:0">💹
                    Simulador de ingresos</h2>
                  <p style="font-size:13px;color:rgba(255,255,255,.5);margin-top:4px">Proyecta cuánto puede generar
                    QuibdóConecta en 12 meses. Ajusta los supuestos y explora escenarios.</p>
                </div>
                <div style="font-size:11px;color:rgba(255,255,255,.3);text-align:right;line-height:1.6">Acceso
                  restringido<br><?= htmlspecialchars($nombre) ?> · <?= $nivel ?></div>
              </div>

              <!-- Etapas -->
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                <button
                  style="padding:9px 18px;border-radius:30px;border:1.5px solid #1D9E75;background:#1D9E75;cursor:pointer;font-size:13px;font-weight:600;color:white;font-family:'Space Grotesk',sans-serif;transition:all .2s"
                  onclick="simSetEtapa(1,this)" id="sim-e1">Etapa 1 — Lanzamiento</button>
                <button
                  style="padding:9px 18px;border-radius:30px;border:1.5px solid rgba(255,255,255,.15);background:transparent;cursor:pointer;font-size:13px;font-weight:600;color:rgba(255,255,255,.5);font-family:'Space Grotesk',sans-serif;transition:all .2s"
                  onclick="simSetEtapa(2,this)" id="sim-e2">Etapa 2 — Crecimiento</button>
                <button
                  style="padding:9px 18px;border-radius:30px;border:1.5px solid rgba(255,255,255,.15);background:transparent;cursor:pointer;font-size:13px;font-weight:600;color:rgba(255,255,255,.5);font-family:'Space Grotesk',sans-serif;transition:all .2s"
                  onclick="simSetEtapa(3,this)" id="sim-e3">Etapa 3 — Consolidación</button>
              </div>
              <div id="sim-etapa-info-admin"
                style="background:rgba(255,255,255,.06);border-left:3px solid #1D9E75;border-radius:0 10px 10px 0;padding:12px 16px;font-size:13px;color:rgba(255,255,255,.65);margin-bottom:24px;max-width:800px">
              </div>

              <!-- Pills precios por etapa -->
              <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px">
                <span
                  style="padding:8px 16px;border-radius:30px;font-size:13px;font-weight:700;background:#eef6f1;color:#4a7c59">🌱
                  Semilla — Gratis</span>
                <span
                  style="padding:8px 16px;border-radius:30px;font-size:13px;font-weight:700;background:#e8f5ee;color:#1f6b3a">🌿
                  Verde Selva — <span id="adm-sp-selva">$12.900</span>/mes</span>
                <span
                  style="padding:8px 16px;border-radius:30px;font-size:13px;font-weight:700;background:#fdf8e8;color:#b8860b">✦
                  Amarillo Oro — <span id="adm-sp-oro">$29.900</span>/mes</span>
                <span
                  style="padding:8px 16px;border-radius:30px;font-size:13px;font-weight:700;background:#e8f0fa;color:#1a3f6f">◆
                  Azul Profundo — <span id="adm-sp-azul">$49.900</span>/mes</span>
                <span
                  style="padding:8px 16px;border-radius:30px;font-size:13px;font-weight:700;background:#f3e8ff;color:#6d28d9">🏪
                  Microempresa — <span id="adm-sp-micro">$19.900</span>/mes</span>
              </div>

              <!-- Métricas -->
              <div
                style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px">
                <div
                  style="background:rgba(29,158,117,.15);border:1px solid rgba(29,158,117,.35);border-radius:14px;padding:18px 20px">
                  <div
                    style="font-size:11px;color:rgba(255,255,255,.5);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
                    Ingresos año 1</div>
                  <div
                    style="font-size:28px;font-weight:800;color:white;font-family:'JetBrains Mono',monospace;line-height:1"
                    id="adm-sm-anual">$0</div>
                  <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px">COP estimado</div>
                </div>
                <div
                  style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:18px 20px">
                  <div
                    style="font-size:11px;color:rgba(255,255,255,.5);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
                    Promedio mensual</div>
                  <div
                    style="font-size:28px;font-weight:800;color:white;font-family:'JetBrains Mono',monospace;line-height:1"
                    id="adm-sm-mensual">$0</div>
                  <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px">COP / mes</div>
                </div>
                <div
                  style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:18px 20px">
                  <div
                    style="font-size:11px;color:rgba(255,255,255,.5);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
                    Mejor mes (mes 12)</div>
                  <div
                    style="font-size:28px;font-weight:800;color:white;font-family:'JetBrains Mono',monospace;line-height:1"
                    id="adm-sm-mejor">$0</div>
                  <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px">Con crecimiento 5%</div>
                </div>
                <div
                  style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:18px 20px">
                  <div
                    style="font-size:11px;color:rgba(255,255,255,.5);font-weight:600;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">
                    Por día promedio</div>
                  <div
                    style="font-size:28px;font-weight:800;color:white;font-family:'JetBrains Mono',monospace;line-height:1"
                    id="adm-sm-dia">$0</div>
                  <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px">COP / día</div>
                </div>
              </div>

              <!-- Dos columnas: sliders + desglose -->
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px"
                class="sim-two-cols-admin">
                <div
                  style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px">
                  <div
                    style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.5);margin-bottom:16px">
                    ⚙️ Ajusta los supuestos</div>
                  <?php
                  $sliders = [
                    ['id' => 'visitas', 'label' => 'Visitas diarias', 'min' => 50, 'max' => 500, 'step' => 10, 'val' => 100, 'sfx' => '/día'],
                    ['id' => 'empresas', 'label' => 'Empresas registradas', 'min' => 5, 'max' => 100, 'step' => 1, 'val' => 20, 'sfx' => ''],
                    ['id' => 'semilla', 'label' => 'Usuarios plan Semilla', 'min' => 10, 'max' => 500, 'step' => 10, 'val' => 80, 'sfx' => ''],
                    ['id' => 'pctselva', 'label' => '% empresas Verde Selva', 'min' => 0, 'max' => 100, 'step' => 5, 'val' => 35, 'sfx' => '%'],
                    ['id' => 'pctoro', 'label' => '% empresas Amarillo Oro', 'min' => 0, 'max' => 60, 'step' => 5, 'val' => 15, 'sfx' => '%'],
                    ['id' => 'pctazul', 'label' => '% empresas Azul Profundo', 'min' => 0, 'max' => 30, 'step' => 5, 'val' => 5, 'sfx' => '%'],
                    ['id' => 'pctmicro', 'label' => '% empresas Microempresa', 'min' => 0, 'max' => 50, 'step' => 5, 'val' => 20, 'sfx' => '%'],
                    ['id' => 'comision', 'label' => 'Servicios con comisión/mes', 'min' => 0, 'max' => 30, 'step' => 1, 'val' => 5, 'sfx' => ''],
                    ['id' => 'valorserv', 'label' => 'Valor promedio servicio', 'min' => 50000, 'max' => 500000, 'step' => 10000, 'val' => 150000, 'sfx' => '$'],
                    ['id' => 'destacados', 'label' => 'Perfiles destacados/mes', 'min' => 0, 'max' => 50, 'step' => 1, 'val' => 10, 'sfx' => ''],
                    ['id' => 'alianzas', 'label' => 'Alianzas institucionales/mes', 'min' => 0, 'max' => 5, 'step' => 1, 'val' => 1, 'sfx' => ''],
                  ];
                  foreach ($sliders as $s): ?>
                    <div style="margin-bottom:14px">
                      <div
                        style="display:flex;justify-content:space-between;font-size:12px;color:rgba(255,255,255,.65);margin-bottom:6px">
                        <label><?= $s['label'] ?></label>
                        <span id="adm-sv-<?= $s['id'] ?>"
                          style="font-weight:700;color:#5cd98a;font-family:'JetBrains Mono',monospace"><?= $s['sfx'] === '$' ? '$' . number_format($s['val'], 0, ',', '.') : $s['val'] . $s['sfx'] ?></span>
                      </div>
                      <input type="range" min="<?= $s['min'] ?>" max="<?= $s['max'] ?>" step="<?= $s['step'] ?>"
                        value="<?= $s['val'] ?>" id="adm-ss-<?= $s['id'] ?>" oninput="admSimCalc()"
                        style="width:100%;accent-color:#1D9E75;cursor:pointer">
                    </div>
                  <?php endforeach; ?>
                </div>

                <div
                  style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px">
                  <div
                    style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.5);margin-bottom:16px">
                    📊 Desglose mensual</div>
                  <div id="adm-sim-fuentes"></div>
                  <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,.08)">
                    <div
                      style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.5);margin-bottom:12px">
                      Conversión</div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px"><span
                        style="color:rgba(255,255,255,.5)">Usuarios gratuitos (Semilla)</span><span
                        style="font-weight:700;color:white" id="adm-sm-semilla-n">0</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px"><span
                        style="color:rgba(255,255,255,.5)">Negocios en Microempresa</span><span
                        style="font-weight:700;color:white" id="adm-sm-micro-n">0</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px"><span
                        style="color:rgba(255,255,255,.5)">Empresas en planes de pago</span><span
                        style="font-weight:700;color:white" id="adm-sm-pago-n">0</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:13px"><span
                        style="color:rgba(255,255,255,.5)">Tasa de conversión</span><span
                        style="font-weight:700;color:#5cd98a" id="adm-sm-conv">0%</span></div>
                  </div>
                </div>
              </div>

              <!-- Gráfico -->
              <div
                style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:20px;margin-bottom:16px">
                <div
                  style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:rgba(255,255,255,.5);margin-bottom:12px">
                  📈 Proyección mes a mes — año 1</div>
                <div style="display:flex;gap:20px;margin-bottom:12px;flex-wrap:wrap">
                  <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,.5)"><span
                      style="width:10px;height:10px;border-radius:2px;background:#1D9E75;display:inline-block"></span>Lanzamiento
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,.5)"><span
                      style="width:10px;height:10px;border-radius:2px;background:#BA7517;display:inline-block"></span>Crecimiento
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,.5)"><span
                      style="width:10px;height:10px;border-radius:2px;background:#1a3f6f;display:inline-block"></span>Consolidación
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:rgba(255,255,255,.5)"><span
                      style="width:10px;height:10px;border-radius:50%;background:#534AB7;display:inline-block"></span>Acumulado
                  </div>
                </div>
                <div style="position:relative;width:100%;height:280px">
                  <canvas id="adm-simChart"></canvas>
                </div>
              </div>

              <p style="font-size:11px;color:rgba(255,255,255,.35);text-align:center;line-height:1.7">Asume crecimiento del
                5% mensual. Etapa 1: meses 1-6 · Etapa 2: meses 7-9 · Etapa 3: meses 10-12. Valores orientativos.</p>
            </div>
          </div>
        <?php endif; // fin simulador ?>

        <!-- ═══ SISTEMA ═══ (solo superadmin) -->
        <?php if ($nivel === 'superadmin'): ?>
          <div class="section" id="section-sistema">
            <div style="max-width:700px;margin:0 auto">
              <div style="display:flex;align-items:center;gap:14px;margin-bottom:28px">
                <div
                  style="width:48px;height:48px;border-radius:14px;background:rgba(68,136,255,.15);border:1px solid rgba(68,136,255,.25);display:flex;align-items:center;justify-content:center;font-size:22px">
                  ⚙️</div>
                <div>
                  <h2 style="font-size:20px;font-weight:800">Sistema</h2>
                  <p style="font-size:13px;color:var(--text2);margin-top:2px">Configuración general de QuibdóConecta.</p>
                </div>
              </div>

              <!-- Modo de registro -->
              <div
                style="background:var(--bg2);border:1px solid var(--border);border-radius:16px;padding:24px;margin-bottom:16px">
                <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px">
                  <span style="font-size:20px">📋</span>
                  <div>
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:8px">Modo de registro de cuentas</h3>
                    <p style="font-size:13px;color:var(--text2);line-height:1.6;margin-bottom:4px">
                      <strong style="color:var(--text)">Solicitud (recomendado):</strong> el usuario llena el formulario y
                      queda pendiente hasta que un admin lo apruebe.
                    </p>
                    <p style="font-size:13px;color:var(--text2);line-height:1.6">
                      <strong style="color:var(--text)">Directo:</strong> la cuenta se crea al instante sin revisión previa.
                      Útil para períodos de alta demanda.
                    </p>
                  </div>
                </div>
                <p style="font-size:12px;color:var(--text3);margin-bottom:14px">
                  Estado actual: <span id="sistema-modo-label"
                    style="color:var(--green);font-weight:700;font-family:'JetBrains Mono',monospace">cargando...</span>
                </p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                  <button onclick="setModoRegistro('solicitud')" id="btn-modo-solicitud"
                    style="display:flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;border:1px solid var(--border2);background:var(--bg3);color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:'Space Grotesk',sans-serif;transition:all .2s">
                    📋 Solicitud
                  </button>
                  <button onclick="setModoRegistro('directo')" id="btn-modo-directo"
                    style="display:flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;border:1px solid var(--border2);background:var(--bg3);color:var(--text);font-size:13px;font-weight:600;cursor:pointer;font-family:'Space Grotesk',sans-serif;transition:all .2s">
                    ⚡ Directo
                  </button>
                  <span id="sistema-msg" style="display:none;font-size:12px;font-weight:600"></span>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </div><!-- /content -->
    </div><!-- /main -->

    <script>
      const ADMIN_NIVEL = '<?= $nivel ?>';
      const ADMIN_PERM_TALENTOS = <?= ($perms['talentos'] ?? false) ? 'true' : 'false' ?>;
      const ADMIN_PERM_EMPRESAS = <?= ($perms['talentos'] ?? false) ? 'true' : 'false' ?>;

      // ══════════════════════════════════════════════════════
      // MODAL EDITAR EMPRESA — réplica de abrirModalTalento
      // ══════════════════════════════════════════════════════
      async function abrirModalEmpresa(uid, nombreUsuario) {
        let modal = document.getElementById('modal-empresa');
        if (!modal) {
          modal = document.createElement('div');
          modal.id = 'modal-empresa';
          modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;padding:20px;overflow-y:auto';
          modal.innerHTML = `
            <div style="background:var(--bg2);border:1px solid var(--border2);border-radius:20px;padding:32px;width:100%;max-width:600px;position:relative;margin:auto;max-height:90vh;overflow-y:auto">
              <button onclick="cerrarModalEmpresa()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer">✕</button>
              <h3 style="font-size:18px;font-weight:700;margin-bottom:4px">🏢 Editar perfil de empresa</h3>
              <p id="empresa-nombre-label" style="color:var(--text3);font-size:13px;font-family:'JetBrains Mono',monospace;margin-bottom:20px"></p>
              <input type="hidden" id="empresa-uid">

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Nombre de la empresa</label>
                  <input type="text" id="empresa-nombre" placeholder="ej: Tech Chocó S.A.S."
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                </div>
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Ciudad</label>
                  <input type="text" id="empresa-ciudad" placeholder="Quibdó, Chocó..."
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                </div>
              </div>

              <div style="margin-bottom:12px">
                <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Descripción / Actividad principal</label>
                <textarea id="empresa-descripcion" rows="3" placeholder="¿A qué se dedica esta empresa?"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none;resize:vertical"></textarea>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Sector / Industria</label>
                  <select id="empresa-sector"
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                    <option value="">Sin especificar</option>
                    <option value="Tecnología">💻 Tecnología</option>
                    <option value="Salud">🏥 Salud</option>
                    <option value="Educación">📚 Educación</option>
                    <option value="Construcción & Inmobiliaria">🏗️ Construcción</option>
                    <option value="Comercio & Retail">🛒 Comercio</option>
                    <option value="Servicios & Turismo">🎯 Servicios</option>
                    <option value="Finanzas & Banca">💰 Finanzas</option>
                    <option value="Agro & Medio Ambiente">🌿 Agro & Ambiente</option>
                    <option value="Transporte & Logística">🚌 Transporte</option>
                    <option value="Gastronomía">🍽️ Gastronomía</option>
                    <option value="Arte & Cultura">🎨 Arte & Cultura</option>
                    <option value="Gobierno">🏛️ Gobierno</option>
                    <option value="Otro">📦 Otro</option>
                  </select>
                </div>
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">NIT / Identificación</label>
                  <input type="text" id="empresa-nit" placeholder="900.123.456-7"
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'JetBrains Mono',monospace;outline:none">
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Teléfono empresa</label>
                  <input type="text" id="empresa-telefono" placeholder="(604) 123 4567"
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                </div>
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Municipio del Chocó</label>
                  <input type="text" id="empresa-municipio" placeholder="Istmina, Condoto..."
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Sitio web</label>
                  <input type="text" id="empresa-web" placeholder="https://miempresa.com"
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                </div>
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Color de avatar (hex)</label>
                  <div style="display:flex;gap:8px;align-items:center">
                    <input type="color" id="empresa-color-picker" value="#1a56db"
                      style="width:40px;height:38px;border:none;background:none;cursor:pointer;border-radius:6px"
                      oninput="document.getElementById('empresa-avatar-color').value=this.value">
                    <input type="text" id="empresa-avatar-color" placeholder="#1a56db"
                      style="flex:1;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'JetBrains Mono',monospace;outline:none"
                      oninput="document.getElementById('empresa-color-picker').value=this.value||'#1a56db'">
                  </div>
                </div>
              </div>

              <div style="display:flex;gap:10px;margin-bottom:20px">
                <div style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none" onclick="toggleSwitch('sw-empresa-visible')">
                  <div>
                    <div style="font-size:12px;font-weight:700;color:var(--text)">🏢 Visible en empresas.php</div>
                    <div style="font-size:11px;color:var(--text2);margin-top:2px">Aparece en el directorio público</div>
                  </div>
                  <div id="sw-empresa-visible" data-on="1" style="width:44px;height:24px;border-radius:24px;background:#1a56db;position:relative;transition:background .25s;flex-shrink:0">
                    <div id="sw-empresa-visible-dot" style="position:absolute;width:18px;height:18px;border-radius:50%;background:white;top:3px;left:3px;transform:translateX(20px);transition:transform .25s;pointer-events:none"></div>
                  </div>
                </div>
                <div style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none" onclick="toggleSwitch('sw-empresa-destacado')">
                  <div>
                    <div style="font-size:12px;font-weight:700;color:var(--text)">⭐ Destacada</div>
                    <div style="font-size:11px;color:var(--text2);margin-top:2px">Aparece primero en búsquedas</div>
                  </div>
                  <div id="sw-empresa-destacado" data-on="0" style="width:44px;height:24px;border-radius:24px;background:rgba(255,255,255,0.15);position:relative;transition:background .25s;flex-shrink:0">
                    <div id="sw-empresa-destacado-dot" style="position:absolute;width:18px;height:18px;border-radius:50%;background:white;top:3px;left:3px;transition:transform .25s;pointer-events:none"></div>
                  </div>
                </div>
              </div>

              <div style="display:flex;gap:10px">
                <button onclick="guardarEmpresa()"
                  style="flex:1;padding:12px;background:linear-gradient(135deg,#1a56db,#3b82f6);border:none;border-radius:10px;color:white;font-size:14px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">💾 Guardar perfil de empresa</button>
                <button onclick="cerrarModalEmpresa()"
                  style="padding:12px 20px;background:transparent;border:1px solid var(--border);border-radius:10px;color:var(--text2);font-size:14px;cursor:pointer;font-family:'Space Grotesk',sans-serif">Cancelar</button>
              </div>
              <p id="empresa-msg" style="font-size:12px;margin-top:10px;text-align:center;display:none"></p>
            </div>`;
          document.body.appendChild(modal);
          modal.addEventListener('click', e => { if (e.target === modal) cerrarModalEmpresa(); });
        }

        document.getElementById('empresa-uid').value = uid;
        document.getElementById('empresa-nombre-label').textContent = `${nombreUsuario} · #${uid}`;
        document.getElementById('empresa-msg').style.display = 'none';

        ['empresa-nombre', 'empresa-ciudad', 'empresa-descripcion', 'empresa-sector',
          'empresa-nit', 'empresa-telefono', 'empresa-municipio', 'empresa-web', 'empresa-avatar-color'
        ].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

        modal.style.display = 'flex';

        try {
          const r = await fetch(`gestion-qbc-2025.php?action=get_empresa&id=${uid}`);
          const d = await r.json();
          if (d.ok && d.empresa) {
            const e = d.empresa;
            document.getElementById('empresa-nombre').value = e.nombre_empresa || e.nombre || '';
            document.getElementById('empresa-ciudad').value = e.ciudad || '';
            document.getElementById('empresa-descripcion').value = e.descripcion || '';
            document.getElementById('empresa-sector').value = e.sector || '';
            document.getElementById('empresa-nit').value = e.nit || '';
            document.getElementById('empresa-telefono').value = e.telefono_empresa || e.telefono || '';
            document.getElementById('empresa-municipio').value = e.municipio || '';
            document.getElementById('empresa-web').value = e.sitio_web || '';
            const color = e.avatar_color || '#1a56db';
            document.getElementById('empresa-avatar-color').value = color;
            document.getElementById('empresa-color-picker').value = color.startsWith('#') ? color : '#1a56db';
            setSwitch('sw-empresa-visible', parseInt(e.visible_admin ?? 1));
            setSwitch('sw-empresa-destacado', parseInt(e.destacado || 0));
          } else {
            setSwitch('sw-empresa-visible', 1);
            setSwitch('sw-empresa-destacado', 0);
          }
        } catch (err) {
          const msg = document.getElementById('empresa-msg');
          msg.style.display = 'block'; msg.style.color = 'var(--red)';
          msg.textContent = '⚠️ Error al cargar datos: ' + err.message;
        }
      }

      function cerrarModalEmpresa() {
        const modal = document.getElementById('modal-empresa');
        if (modal) modal.style.display = 'none';
      }

      async function guardarEmpresa() {
        const uid = document.getElementById('empresa-uid').value;
        const msg = document.getElementById('empresa-msg');
        msg.style.display = 'none';
        const fd = new FormData();
        fd.append('id', uid);
        fd.append('nombre_empresa', document.getElementById('empresa-nombre').value.trim());
        fd.append('ciudad', document.getElementById('empresa-ciudad').value.trim());
        fd.append('descripcion', document.getElementById('empresa-descripcion').value.trim());
        fd.append('sector', document.getElementById('empresa-sector').value.trim());
        fd.append('nit', document.getElementById('empresa-nit').value.trim());
        fd.append('telefono_empresa', document.getElementById('empresa-telefono').value.trim());
        fd.append('municipio', document.getElementById('empresa-municipio').value.trim());
        fd.append('sitio_web', document.getElementById('empresa-web').value.trim());
        fd.append('avatar_color', document.getElementById('empresa-avatar-color').value.trim());
        fd.append('visible_admin', document.getElementById('sw-empresa-visible').dataset.on === '1' ? '1' : '0');
        fd.append('destacado', document.getElementById('sw-empresa-destacado').dataset.on === '1' ? '1' : '0');
        try {
          const r = await fetch('gestion-qbc-2025.php?action=editar_empresa', { method: 'POST', body: fd });
          if (!r.ok) throw new Error('HTTP ' + r.status);
          const text = await r.text();
          let d;
          try { d = JSON.parse(text); } catch (e) { throw new Error('Sesión expirada — recarga la página'); }
          msg.style.display = 'block';
          if (d.ok) {
            msg.style.color = 'var(--green)';
            msg.textContent = '✅ Perfil de empresa guardado correctamente';
            setTimeout(() => { cerrarModalEmpresa(); cargarUsuarios(); }, 1200);
          } else {
            msg.style.color = 'var(--red)';
            msg.textContent = '❌ ' + (d.msg || 'Error al guardar');
          }
        } catch (err) {
          msg.style.display = 'block'; msg.style.color = 'var(--red)';
          msg.textContent = '❌ ' + err.message;
        }
      }

      let seccionActual = 'dashboard';
      let tipoFiltro = '';
      let paginaUsuarios = 1;

      // ── RELOJ ──
      function actualizarReloj() {
        const ahora = new Date();
        document.getElementById('reloj').textContent =
          ahora.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      }
      setInterval(actualizarReloj, 1000);
      actualizarReloj();

      // ── NAVEGACIÓN ──
      const titulos = {
        dashboard: ['Dashboard', 'Vista general del sistema'],
        documentos: ['Repositorio de documentos', 'Todos los documentos subidos por usuarios'],
        papelera: ['Papelera de documentos', 'Documentos eliminados — se pueden restaurar o borrar definitivamente'],
        solicitudes: ['Solicitudes de ingreso', 'Nuevas solicitudes de cuenta pendientes de aprobación'],
        verificaciones: ['Verificaciones', 'Cola de documentos pendientes'],
        usuarios: ['Usuarios', 'Gestión de cuentas registradas'],
        empleos: ['Empleos', 'Vacantes publicadas en la plataforma'],
        convocatorias: ['Convocatorias', 'Convocatorias del sector público'],
        mensajes: ['Mensajes', 'Conversaciones recientes'],
        actividad: ['Actividad', 'Registro de acciones del panel'],
        badges: ['Badges', 'Gestión y asignación de insignias'],
        estadisticas: ['Estadísticas', 'Análisis detallado de la plataforma'],
        auditoria: ['Auditoría', 'Historial de acciones administrativas'],
        roles: ['Roles', 'Control de acceso y permisos'],
        candidatos: ['Candidatos', 'Perfiles de candidatos registrados en la plataforma'],
        empresas_dir: ['Directorio de Empresas', 'Empresas registradas en QuibdóConecta'],
        servicios_dir: ['Directorio de Servicios', 'Prestadores de servicios con precio o tipo definido'],
        negocios_dir: ['Directorio de Negocios', 'Negocios locales y emprendedores del Chocó'],
        simulador: ['Simulador de ingresos', 'Proyección financiera de QuibdóConecta — solo superadmin y admin'],
        sistema: ['Sistema', 'Configuración general de QuibdóConecta'],
      };

      function mostrarToast(msg, tipo) {
        let t = document.getElementById('admin-toast');
        if (!t) {
          t = document.createElement('div');
          t.id = 'admin-toast';
          t.style.cssText = 'position:fixed;bottom:28px;right:28px;z-index:9999;padding:14px 22px;border-radius:12px;font-size:14px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.4);transition:opacity .3s;pointer-events:none';
          document.body.appendChild(t);
        }
        t.textContent = msg;
        t.style.background = tipo === 'green' ? '#065f46' : tipo === 'red' ? '#7f1d1d' : '#1e3a5f';
        t.style.color = '#fff';
        t.style.opacity = '1';
        clearTimeout(t._timer);
        t._timer = setTimeout(() => { t.style.opacity = '0'; }, 3000);
      }

      function irA(seccion) {
        document.querySelectorAll('.sb-item').forEach(el => el.classList.remove('active'));
        document.getElementById('nav-' + seccion)?.classList.add('active');
        document.querySelectorAll('.section').forEach(el => el.classList.remove('active'));
        document.getElementById('section-' + seccion)?.classList.add('active');
        const [titulo, sub] = titulos[seccion] || [seccion, ''];
        document.getElementById('topbar-title').textContent = titulo;
        document.getElementById('topbar-sub').textContent = sub;
        seccionActual = seccion;
        cargarSeccion(seccion);
      }

      function recargarSeccion() { cargarSeccion(seccionActual); }

      function cargarSeccion(s) {
        if (s === 'dashboard') cargarDashboard();
        if (s === 'documentos') cargarDocumentos();
        if (s === 'papelera') cargarPapelera();
        if (s === 'solicitudes') cargarSolicitudes('pendiente');
        if (s === 'verificaciones') cargarVerificaciones('pendiente');
        if (s === 'usuarios') cargarUsuarios();
        if (s === 'empleos') cargarEmpleos();
        if (s === 'convocatorias') cargarConvocatorias();
        if (s === 'mensajes') { cargarMensajes(); cargarChatStats(); }
        if (s === 'actividad') cargarActividad();
        if (s === 'badges') cargarBadgesCatalogo();
        if (s === 'estadisticas') cargarEstadisticas();
        if (s === 'auditoria') cargarAuditoria();
        if (s === 'roles') cargarRoles();
        if (s === 'candidatos') cargarCandidatos(1);
        if (s === 'empresas_dir') cargarEmpresasDir(1);
        if (s === 'servicios_dir') cargarServiciosDir(1);
        if (s === 'negocios_dir') cargarNegociosDir(1);
        if (s === 'simulador') iniciarSimulador();
        if (s === 'sistema') cargarSistema();
      }

      // ── DASHBOARD ──
      async function cargarDashboard() {
        document.getElementById('loading-dashboard').style.display = 'block';
        document.getElementById('dashboard-content').style.display = 'none';
        try {
          const r = await fetch('gestion-qbc-2025.php?action=metricas');
          if (!r.ok) throw new Error('HTTP ' + r.status);
          const txt = await r.text();
          let d; try { d = JSON.parse(txt); } catch (e) { throw new Error('Sesión expirada'); }
          if (!d.ok) return;
          const s = d.stats;

          // ── Badges sidebar ──
          const bv = document.getElementById('badge-verif');
          const verPend = parseInt(s.verificaciones_pendientes) || 0;
          if (bv) { bv.textContent = verPend; bv.style.display = verPend > 0 ? 'inline-flex' : 'none'; }
          const bs = document.getElementById('badge-solic');
          const solPend = parseInt(s.solicitudes_pendientes) || 0;
          if (bs) { bs.textContent = solPend; bs.style.display = solPend > 0 ? 'inline-flex' : 'none'; }

          // ── Alertas urgentes ──
          const alertasEl = document.getElementById('db-alertas');
          let alertas = '';
          if (solPend > 0) alertas += `<div class="db-alerta urgente" onclick="irA('solicitudes')">
      <span style="font-size:24px">📋</span>
      <div style="flex:1"><strong style="color:var(--amber)">${solPend} solicitud${solPend > 1 ? 'es' : ''} pendiente${solPend > 1 ? 's' : ''}</strong>
      <div style="font-size:12px;color:var(--text2);margin-top:2px">Nuevos usuarios esperando aprobación — clic para revisar</div></div>
      <span style="color:var(--amber);font-size:18px">›</span></div>`;
          if (verPend > 0) alertas += `<div class="db-alerta info" onclick="irA('verificaciones')">
      <span style="font-size:24px">✅</span>
      <div style="flex:1"><strong style="color:var(--blue)">${verPend} verificación${verPend > 1 ? 'es' : ''} pendiente${verPend > 1 ? 's' : ''}</strong>
      <div style="font-size:12px;color:var(--text2);margin-top:2px">Documentos por revisar — clic para gestionar</div></div>
      <span style="color:var(--blue);font-size:18px">›</span></div>`;
          alertasEl.innerHTML = alertas;
          alertasEl.style.display = alertas ? 'block' : 'none';

          // ── Métricas ──
          const usuariosHoy = parseInt(s.usuarios_hoy) || 0;
          document.getElementById('metrics-grid').innerHTML = `
      ${mc('green', '👥', 'Usuarios totales', s.total_usuarios, usuariosHoy > 0 ? '+' + usuariosHoy + ' hoy' : 'Sin altas hoy', usuariosHoy > 0 ? 'up' : 'neutral')}
      ${mc('blue', '👤', 'Candidatos', s.total_candidatos, '', 'neutral')}
      ${mc('purple', '🏢', 'Empresas', s.total_empresas, '', 'neutral')}
      ${mc('amber', '💼', 'Empleos activos', s.empleos_activos, 'de ' + s.total_empleos + ' publicados', 'neutral')}
      ${mc('red', '⏳', 'Verif. pendientes', verPend, verPend > 0 ? 'Requieren atención' : 'Al día ✓', verPend > 0 ? 'up' : 'neutral')}
      ${mc('green', '💬', 'Mensajes', s.total_mensajes, '', 'neutral')}
      ${mc('blue', '📋', 'Convocatorias', s.convocatorias, '', 'neutral')}
      ${mc('purple', '🛠️', 'Servicios', s.total_servicios || 0, 'con precio/tipo definido', 'neutral')}
      ${mc('amber', '🏪', 'Negocios', s.total_negocios || 0, 'visibles en directorio', 'neutral')}
      ${solPend > 0 ? mc('amber', '📋', 'Solicitudes nuevas', solPend, 'esperando aprobación', 'up') : ''}
    `;

          // ── Botones acción rápida ──
          const elSolic = document.getElementById('db-btn-solic');
          const elVerif = document.getElementById('db-btn-verif');
          const elUsrs = document.getElementById('db-btn-usuarios');
          const elEmpl = document.getElementById('db-btn-empleos');
          if (elSolic) elSolic.textContent = solPend > 0 ? solPend + ' pendiente' + (solPend > 1 ? 's' : '') : 'Sin pendientes';
          if (elVerif) elVerif.textContent = verPend > 0 ? verPend + ' pendiente' + (verPend > 1 ? 's' : '') : 'Sin pendientes';
          if (elUsrs) elUsrs.textContent = s.total_usuarios + ' registrados';
          if (elEmpl) elEmpl.textContent = s.empleos_activos + ' activos';

          // ── Gráfica ──
          const dias = [];
          const hoy = new Date();
          for (let i = 6; i >= 0; i--) {
            const d2 = new Date(hoy); d2.setDate(d2.getDate() - i);
            dias.push(d2.toISOString().split('T')[0]);
          }
          const vals = dias.map(d2 => parseInt(s.registros_semana[d2] || 0));
          const maxVal = Math.max(...vals, 1);
          const totalSemana = vals.reduce((a, b) => a + b, 0);
          const semNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
          document.getElementById('db-total-semana').textContent = '+' + totalSemana;
          document.getElementById('chart-bars').innerHTML = dias.map((dia, i) => {
            const val = vals[i];
            const h = Math.max(6, (val / maxVal) * 80);
            const d2obj = new Date(dia + 'T12:00:00');
            const label = semNames[d2obj.getDay()];
            const esHoy = i === 6;
            return `<div class="chart-bar-col">
        <div class="chart-bar-val" style="color:${esHoy ? 'var(--green)' : 'var(--text3)'}">${val}</div>
        <div class="chart-bar" style="height:${h}px;background:${esHoy ? 'var(--green)' : 'rgba(255,255,255,.15)'};border-radius:4px 4px 0 0"></div>
        <div class="chart-bar-label" style="color:${esHoy ? 'var(--green)' : 'var(--text3)'};font-weight:${esHoy ? 700 : 400}">${label}</div>
      </div>`;
          }).join('');

          document.getElementById('loading-dashboard').style.display = 'none';
          document.getElementById('dashboard-content').style.display = 'block';
          actualizarBadgePapelera();
        } catch (e) {
          document.getElementById('loading-dashboard').innerHTML = '<span style="color:var(--red)">⚠️ Error: ' + e.message + '</span>';
        }
      }

      function mc(color, icon, label, value, sub, trend) {
        return `<div class="metric-card ${color}">
    <div class="mc-top">
      <div class="mc-icon">${icon}</div>
      ${trend ? `<span class="mc-trend ${trend}">${trend === 'up' ? '↑' : '–'} ${sub || ''}</span>` : ''}
    </div>
    <div class="mc-value">${value}</div>
    <div class="mc-label">${label}</div>
    ${sub && trend !== 'up' && trend !== 'neutral' ? `<div class="mc-sub">${sub}</div>` : ''}
    ${trend === 'neutral' && sub ? `<div class="mc-sub">${sub}</div>` : ''}
  </div>`;
      }

      // ── VERIFICACIONES ──
      let verifEstado = 'pendiente';
      // ── REPOSITORIO DE DOCUMENTOS ──
      let docsPage = 1;

      async function cargarDocumentos(page) {
        if (page) docsPage = page;
        const q = document.getElementById('docs-buscar')?.value || '';
        const tipo = document.getElementById('docs-tipo')?.value || '';
        const estado = document.getElementById('docs-estado')?.value || '';
        const grid = document.getElementById('docs-grid');
        const empty = document.getElementById('docs-empty');
        const loading = document.getElementById('docs-loading');
        const pag = document.getElementById('docs-pagination');
        const statsEl = document.getElementById('docs-stats');

        grid.style.display = 'none'; empty.style.display = 'none';
        loading.style.display = 'block'; pag.innerHTML = '';

        try {
          const url = `gestion-qbc-2025.php?action=documentos&q=${encodeURIComponent(q)}&tipo=${tipo}&estado=${estado}&page=${docsPage}`;
          const r = await fetch(url);
          if (!r.ok) throw new Error('HTTP ' + r.status);
          const text = await r.text();
          let d;
          try { d = JSON.parse(text); } catch (e) { throw new Error('Sesión expirada — recarga la página'); }

          loading.style.display = 'none';

          if (d.stats) {
            statsEl.innerHTML = `
              <div style="padding:10px 16px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;font-size:13px">
                📁 <strong>${d.stats.total || 0}</strong> total
              </div>
              <div style="padding:10px 16px;background:rgba(255,171,0,.1);border:1px solid rgba(255,171,0,.3);border-radius:10px;font-size:13px;color:var(--amber)">
                ⏳ <strong>${d.stats.pendiente || 0}</strong> pendientes
              </div>
              <div style="padding:10px 16px;background:var(--green-bg);border:1px solid rgba(0,230,118,.2);border-radius:10px;font-size:13px;color:var(--green)">
                ✅ <strong>${d.stats.aprobado || 0}</strong> aprobados
              </div>
              <div style="padding:10px 16px;background:var(--red-bg);border:1px solid rgba(255,68,68,.2);border-radius:10px;font-size:13px;color:var(--red)">
                ❌ <strong>${d.stats.rechazado || 0}</strong> rechazados
              </div>
              ${(d.stats.papelera > 0) ? `<div onclick="irA('papelera')" style="padding:10px 16px;background:rgba(120,60,60,.15);border:1px solid rgba(180,60,60,.25);border-radius:10px;font-size:13px;color:#ff7070;cursor:pointer" title="Ver papelera">
                🗑️ <strong>${d.stats.papelera}</strong> en papelera
              </div>` : ''}`;
          }

          if (!d.docs || !d.docs.length) {
            empty.style.display = 'block';
            return;
          }

          grid.style.display = 'grid';
          grid.innerHTML = d.docs.map(doc => {
            const docUrl = doc.doc_url ? (doc.doc_url.startsWith('uploads/') ? doc.doc_url : 'uploads/verificaciones/' + doc.doc_url) : null;
            const selfUrl = doc.foto_doc_url ? (doc.foto_doc_url.startsWith('uploads/') ? doc.foto_doc_url : 'uploads/verificaciones/' + doc.foto_doc_url) : null;
            const ext = docUrl ? docUrl.split('.').pop().toLowerCase() : '';
            const esImg = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
            const esPdf = ext === 'pdf';
            const estadoColor = doc.estado === 'aprobado' ? 'green' : doc.estado === 'pendiente' ? 'amber' : 'red';
            return `<div style="background:var(--bg2);border:1px solid var(--border);border-radius:16px;overflow:hidden">
              <div style="padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--border)">
                <div style="width:38px;height:38px;border-radius:50%;background:${doc.user_tipo === 'empresa' ? '#2255cc' : '#1f9d55'};display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0">
                  ${doc.nombre.charAt(0).toUpperCase()}
                </div>
                <div style="flex:1;min-width:0">
                  <div style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(doc.nombre + ' ' + (doc.apellido || ''))}</div>
                  <div style="font-size:11px;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(doc.correo)}</div>
                </div>
                <span class="badge ${estadoColor}" style="flex-shrink:0">${doc.estado}</span>
              </div>
              <div style="padding:14px 16px">
                <div style="font-size:11px;color:var(--text3);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">
                  📄 ${(doc.tipo_doc || 'Sin tipo').replace('_', ' ')} · ${fFecha(doc.creado_en)}
                </div>
                ${docUrl ? `<div style="margin-bottom:10px;border-radius:10px;overflow:hidden;border:1px solid var(--border2);background:#0a0a0a">
                  ${esImg ? `<a href="${docUrl}" target="_blank"><img src="${docUrl}" alt="Documento" style="width:100%;height:200px;object-fit:contain;background:#0a0a0a;display:block;cursor:zoom-in"></a>` : ''}
                  ${esPdf ? `<a href="${docUrl}" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:10px;height:80px;color:var(--blue);font-weight:600;font-size:14px;text-decoration:none">📄 Ver PDF del documento</a>` : ''}
                  ${!esImg && !esPdf ? `<a href="${docUrl}" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:10px;height:60px;color:var(--blue);font-size:13px;text-decoration:none">🔗 Ver archivo</a>` : ''}
                </div>` : `<p style="font-size:12px;color:rgba(255,100,100,.7);margin-bottom:10px">⚠️ Sin documento adjunto</p>`}
                ${selfUrl ? `<div style="margin-bottom:10px">
                  <div style="font-size:11px;color:var(--text3);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">📸 Selfie con doc</div>
                  <a href="${selfUrl}" target="_blank"><img src="${selfUrl}" alt="Selfie" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border2);cursor:zoom-in;background:#0a0a0a"></a>
                </div>` : ''}
                ${doc.nota_rechazo ? `<div style="padding:8px 12px;background:var(--red-bg);border:1px solid rgba(255,68,68,.2);border-radius:8px;font-size:12px;color:var(--red);margin-top:8px">💬 Nota: ${esc(doc.nota_rechazo)}</div>` : ''}
                <div style="margin-top:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                  <div style="font-size:11px;color:var(--text3)">👤 ID: #${doc.usuario_id} · 🆔 Doc: #${doc.id}</div>
                  <button onclick="eliminarDocumento(${doc.id})"
                    style="padding:5px 12px;background:var(--red-bg);border:1px solid rgba(255,68,68,.3);border-radius:8px;color:var(--red);font-size:12px;font-weight:600;cursor:pointer;font-family:'Space Grotesk',sans-serif;transition:all .2s"
                    onmouseover="this.style.background='var(--red)';this.style.color='white'"
                    onmouseout="this.style.background='var(--red-bg)';this.style.color='var(--red)'">
                    🗑️ Mover a papelera
                  </button>
                </div>
              </div>
            </div>`;
          }).join('');

          const totalPages = Math.ceil(d.total / d.limit);
          if (totalPages > 1) {
            let html = '';
            for (let i = 1; i <= totalPages; i++) {
              html += `<button onclick="cargarDocumentos(${i})" style="padding:6px 12px;border-radius:8px;border:1px solid ${i === docsPage ? 'var(--green)' : 'var(--border)'};background:${i === docsPage ? 'var(--green-bg)' : 'var(--bg3)'};color:${i === docsPage ? 'var(--green)' : 'var(--text)'};cursor:pointer;font-size:13px">${i}</button>`;
            }
            pag.innerHTML = `<div style="font-size:13px;color:var(--text2);margin-right:8px">${d.total} documentos</div>` + html;
          }

        } catch (e) {
          loading.style.display = 'none';
          grid.style.display = 'none';
          empty.style.display = 'block';
          document.getElementById('docs-empty').innerHTML = `<span class="ei">⚠️</span><p>${e.message}</p>`;
        }
      }

      // ── ELIMINAR DOCUMENTO (mover a papelera) ──────────────────
      async function eliminarDocumento(id) {
        if (!confirm('¿Mover este documento a la papelera? Podrás restaurarlo después.')) return;
        const fd = new FormData(); fd.append('id', id);
        const r = await fetch('gestion-qbc-2025.php?action=eliminar_documento', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          mostrarToast('🗑️ Documento movido a la papelera', 'green');
          cargarDocumentos();
          actualizarBadgePapelera();
        } else {
          mostrarToast('❌ ' + (d.msg || 'Error al eliminar'), 'red');
        }
      }

      // ── PAPELERA ───────────────────────────────────────────────
      let papeLeraPage = 1;
      async function cargarPapelera(page) {
        if (page) papeLeraPage = page;
        const grid = document.getElementById('papelera-grid');
        const empty = document.getElementById('papelera-empty');
        const loading = document.getElementById('papelera-loading');
        const pag = document.getElementById('papelera-pagination');
        const label = document.getElementById('papelera-count-label');
        if (!grid) return;

        grid.style.display = 'none'; empty.style.display = 'none';
        loading.style.display = 'block'; pag.innerHTML = '';

        try {
          const r = await fetch(`gestion-qbc-2025.php?action=papelera_documentos&page=${papeLeraPage}`);
          const text = await r.text();
          let d; try { d = JSON.parse(text); } catch (e) { throw new Error('Sesión expirada — recarga'); }
          loading.style.display = 'none';

          if (label) label.textContent = d.total + ' documento' + (d.total !== 1 ? 's' : '') + ' en papelera';

          // Actualizar badge sidebar
          const bp = document.getElementById('badge-papelera');
          if (bp) { bp.textContent = d.total; bp.style.display = d.total > 0 ? 'inline' : 'none'; }

          if (!d.docs || !d.docs.length) { empty.style.display = 'block'; return; }

          grid.style.display = 'grid';
          grid.innerHTML = d.docs.map(doc => {
            const docUrl = doc.doc_url ? (doc.doc_url.startsWith('uploads/') ? doc.doc_url : 'uploads/verificaciones/' + doc.doc_url) : null;
            const ext = docUrl ? docUrl.split('.').pop().toLowerCase() : '';
            const esImg = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
            const esPdf = ext === 'pdf';
            return `<div style="background:var(--bg2);border:1px solid rgba(255,68,68,.18);border-radius:14px;overflow:hidden;opacity:.92">
              <div style="padding:12px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border)">
                <div style="width:34px;height:34px;border-radius:50%;background:#7f1d1d;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0">${esc(doc.nombre.charAt(0).toUpperCase())}</div>
                <div style="flex:1;min-width:0">
                  <div style="font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(doc.nombre + ' ' + (doc.apellido || ''))}</div>
                  <div style="font-size:11px;color:var(--text2)">${esc(doc.correo)}</div>
                </div>
                <span style="font-size:10px;color:var(--red);background:var(--red-bg);padding:2px 8px;border-radius:8px;flex-shrink:0">🗑️ eliminado</span>
              </div>
              <div style="padding:12px 14px">
                <div style="font-size:11px;color:var(--text3);margin-bottom:8px">
                  📄 ${(doc.tipo_doc || 'Sin tipo').replace('_', ' ')} · Subido: ${fFecha(doc.creado_en)} · Eliminado: ${fFecha(doc.eliminado_en)}
                </div>
                ${docUrl ? (esImg
              ? `<a href="${docUrl}" target="_blank"><img src="${docUrl}" alt="Doc" style="width:100%;height:140px;object-fit:contain;border-radius:8px;border:1px solid var(--border);background:#0a0a0a;display:block"></a>`
              : esPdf
                ? `<a href="${docUrl}" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:8px;height:60px;color:var(--blue);font-size:13px;font-weight:600;text-decoration:none;border:1px solid var(--border);border-radius:8px">📄 Ver PDF</a>`
                : `<a href="${docUrl}" target="_blank" style="font-size:12px;color:var(--blue)">🔗 Ver archivo</a>`
            ) : '<p style="font-size:12px;color:rgba(255,100,100,.6)">⚠️ Sin archivo adjunto</p>'}
                <div style="margin-top:12px;display:flex;gap:8px">
                  <button onclick="restaurarDocumento(${doc.id})"
                    style="flex:1;padding:8px;background:var(--green-bg);border:1px solid rgba(0,230,118,.3);border-radius:8px;color:var(--green);font-size:12px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">
                    ♻️ Restaurar
                  </button>
                </div>
              </div>
            </div>`;
          }).join('');

          // Paginación
          const totalPages = Math.ceil(d.total / d.limit);
          if (totalPages > 1) {
            let html = '';
            for (let i = 1; i <= totalPages; i++) {
              html += `<button onclick="cargarPapelera(${i})"
                style="padding:6px 12px;border-radius:8px;border:1px solid ${i === papeLeraPage ? 'var(--red)' : 'var(--border)'};background:${i === papeLeraPage ? 'var(--red-bg)' : 'var(--bg3)'};color:${i === papeLeraPage ? 'var(--red)' : 'var(--text)'};cursor:pointer;font-size:13px">${i}</button>`;
            }
            pag.innerHTML = html;
          }
        } catch (e) {
          loading.style.display = 'none';
          empty.style.display = 'block';
          document.getElementById('papelera-empty').innerHTML = `<span class="ei">⚠️</span><p>${e.message}</p>`;
        }
      }

      async function restaurarDocumento(id) {
        const fd = new FormData(); fd.append('id', id);
        const r = await fetch('gestion-qbc-2025.php?action=restaurar_documento', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          mostrarToast('♻️ Documento restaurado', 'green');
          cargarPapelera();
          actualizarBadgePapelera();
        } else {
          mostrarToast('❌ ' + (d.msg || 'Error'), 'red');
        }
      }

      async function vaciarPapelera() {
        const countEl = document.getElementById('papelera-count-label');
        const total = countEl ? parseInt(countEl.textContent) : 0;
        if (!confirm(`⚠️ ¿Vaciar la papelera?\n\nEsto eliminará PERMANENTEMENTE todos los documentos (${total > 0 ? total : 'todos'}).\n\nEsta acción NO se puede deshacer.`)) return;
        const fd = new FormData();
        const r = await fetch('gestion-qbc-2025.php?action=vaciar_papelera', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          mostrarToast(`🔥 Papelera vaciada — ${d.eliminados} documento(s) borrados`, 'red');
          cargarPapelera();
        } else {
          mostrarToast('❌ ' + (d.msg || 'Sin permisos'), 'red');
        }
      }

      async function actualizarBadgePapelera() {
        try {
          const r = await fetch('gestion-qbc-2025.php?action=papelera_documentos&page=1');
          const d = await r.json();
          const bp = document.getElementById('badge-papelera');
          if (bp && d.ok) { bp.textContent = d.total; bp.style.display = d.total > 0 ? 'inline' : 'none'; }
        } catch (e) { }
      }

      // ── SOLICITUDES DE INGRESO ──
      async function cargarSolicitudes(estado, btn) {
        if (btn) { document.querySelectorAll('#section-solicitudes .filter-btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); }
        document.getElementById('solic-list').innerHTML = '<div class="loading"><span class="spin">⚙️</span></div>';
        try {
          const r = await fetch('gestion-qbc-2025.php?action=solicitudes&estado=' + estado);
          if (!r.ok) throw new Error('HTTP ' + r.status);
          const text = await r.text();
          let d;
          try { d = JSON.parse(text); } catch (e) { throw new Error('Sesión expirada — recarga la página'); }
          if (!d.ok || !d.solicitudes.length) {
            document.getElementById('solic-list').innerHTML = '<div class="empty-state"><span class="ei">📭</span><p>No hay solicitudes ' + estado + 's</p></div>';
            return;
          }
          document.getElementById('solic-list').innerHTML = d.solicitudes.map(s => `
      <div class="verif-card" id="sc-${s.id}">
        <div class="verif-header">
          <div class="verif-user">
            <div class="verif-avatar" style="background:${s.tipo === 'empresa' ? '#3b82f6' : s.tipo === 'servicio' ? '#f59e0b' : s.tipo === 'negocio' ? '#8b5cf6' : '#10b981'}">${s.nombre.charAt(0).toUpperCase()}</div>
            <div class="verif-info">
              <div class="name">${esc(s.nombre + ' ' + (s.apellido || ''))}</div>
              <div class="meta">
                ${esc(s.correo)} · ${s.tipo === 'empresa' ? '🏢 Empresa' : s.tipo === 'servicio' ? '🛠️ Servicio' : s.tipo === 'negocio' ? '🏪 Negocio' : '👤 Candidato'} · ${fFecha(s.creado_en)}
              </div>
              <div class="meta" style="margin-top:4px">
                ${s.telefono ? '📞 ' + esc(s.telefono) + ' · ' : ''} 
                ${s.ciudad ? '📍 ' + esc(s.ciudad) : ''} 
                ${s.fecha_nacimiento ? '🗓️ Nac: ' + esc(s.fecha_nacimiento) : ''}
              </div>
              ${s.tipo === 'empresa' ? `<div class="meta" style="margin-top:4px">🏢 ${esc(s.nombre_empresa || '')} ${s.nit ? '· NIT: ' + esc(s.nit) : ''}</div>` : ''}
              ${s.cedula ? `<div class="meta" style="margin-top:4px">🪪 Cédula: <strong style="color:var(--text)">${esc(s.cedula)}</strong></div>` : ''}
            </div>
          </div>
          <span class="badge ${s.estado === 'pendiente' ? 'amber' : s.estado === 'aprobado' ? 'green' : 'red'}">${s.estado}</span>
        </div>

        ${(() => {
            if (!s.doc_url) return '<div style="padding:10px 0;font-size:12px;color:rgba(255,100,100,.7)">⚠️ Sin documento adjunto</div>';
            const url = s.doc_url.startsWith('uploads/') ? s.doc_url : 'uploads/verificaciones/' + s.doc_url;
            const ext = url.split('.').pop().toLowerCase();
            const esImg = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
            return `<div style="margin:12px 0;border-radius:12px;overflow:hidden;border:1px solid var(--border2);background:#0a0a0a">
            ${esImg
                ? `<a href="${url}" target="_blank" title="Clic para ver completo">
                  <img src="${url}" alt="Cédula" style="width:100%;max-height:220px;object-fit:contain;display:block;cursor:zoom-in">
                </a>`
                : `<a href="${url}" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:10px;height:70px;color:var(--blue);font-weight:600;font-size:14px;text-decoration:none">
                  📄 Ver PDF de cédula
                </a>`}
          </div>`;
          })()}

        ${s.nota_admin && estado !== 'pendiente' ? `<div style="padding:8px 12px;background:var(--bg3);border-radius:8px;font-size:12px;color:var(--text2);margin-bottom:10px">💬 ${esc(s.nota_admin)}</div>` : ''}
        ${estado === 'pendiente' ? `
        <textarea class="verif-nota" id="snota-${s.id}" placeholder="Nota para el usuario (opcional)..." rows="2"></textarea>
        <div class="verif-actions">
          <button class="btn-sm green" onclick="resolverSolicitud(${s.id},'aprobado')">✅ Aprobar y crear cuenta</button>
          <button class="btn-sm red"   onclick="resolverSolicitud(${s.id},'rechazado')">❌ Rechazar</button>
        </div>` : ''}
      </div>`).join('');
        } catch (e) {
          document.getElementById('solic-list').innerHTML = '<div class="empty-state"><span class="ei">⚠️</span><p>' + e.message + '</p></div>';
        }
      }

      async function resolverSolicitud(id, estado) {
        const nota = document.getElementById('snota-' + id)?.value || '';
        const card = document.getElementById('sc-' + id);
        const btns = card?.querySelectorAll('button');
        btns?.forEach(b => { b.disabled = true; b.style.opacity = '0.5'; });
        try {
          const fd = new FormData();
          fd.append('id', id); fd.append('estado', estado); fd.append('nota', nota);
          const r = await fetch('gestion-qbc-2025.php?action=resolver_solicitud', { method: 'POST', body: fd });
          if (!r.ok) throw new Error('HTTP ' + r.status);
          const text = await r.text();
          let d;
          try { d = JSON.parse(text); } catch (e) { throw new Error('Sesión expirada — recarga la página'); }
          if (d.ok) {
            card?.remove();
            if (!document.querySelector('#solic-list .verif-card')) {
              document.getElementById('solic-list').innerHTML = '<div class="empty-state"><span class="ei">📭</span><p>No hay más solicitudes pendientes</p></div>';
            }
            // Actualizar badge del sidebar
            actualizarBadgeSolicitudes();
            mostrarToast(estado === 'aprobado' ? '✅ Cuenta creada y aprobada' : '❌ Solicitud rechazada', estado === 'aprobado' ? 'green' : 'red');
          } else {
            mostrarToast('Error: ' + (d.msg || 'No se pudo procesar'), 'red');
            btns?.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
          }
        } catch (e) {
          mostrarToast('Error: ' + e.message, 'red');
          btns?.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
        }
      }

      async function actualizarBadgeSolicitudes() {
        try {
          const r = await fetch('gestion-qbc-2025.php?action=solicitudes_count');
          const d = await r.json();
          const badge = document.getElementById('badge-solic');
          if (badge) {
            badge.textContent = d.pendientes;
            badge.style.display = d.pendientes > 0 ? 'inline-flex' : 'none';
          }
        } catch (e) { }
      }

      async function cargarVerificaciones(estado, btn) {
        verifEstado = estado;
        if (btn) {
          document.querySelectorAll('.verif-tabs .filter-btn').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
        }
        document.getElementById('verif-list').innerHTML = '<div class="loading"><span class="spin">⚙️</span></div>';
        try {
          const r = await fetch(`gestion-qbc-2025.php?action=verificaciones&estado=${estado}`);
          const d = await r.json();
          if (!d.ok || !d.verificaciones.length) {
            document.getElementById('verif-list').innerHTML = '<div class="empty-state"><span class="ei">📭</span><p>No hay verificaciones ' + estado + 's</p></div>';
            return;
          }
          document.getElementById('verif-list').innerHTML = d.verificaciones.map(v => `
      <div class="verif-card" id="vc-${v.id}">
        <div class="verif-header">
          <div class="verif-user">
            <div class="verif-avatar">${v.nombre.charAt(0).toUpperCase()}</div>
            <div class="verif-info">
              <div class="name">${esc(v.nombre + ' ' + (v.apellido || ''))}</div>
              <div class="meta">${esc(v.correo)} · ${v.tipo === 'empresa' ? '🏢 Empresa' : v.tipo === 'servicio' ? '🛠️ Servicio' : v.tipo === 'negocio' ? '🏪 Negocio' : '👤 Candidato'} · ${fFecha(v.creado_en)}</div>
            </div>
          </div>
          <span class="badge ${v.estado === 'pendiente' ? 'amber' : v.estado === 'aprobado' ? 'green' : 'red'}">${v.estado}</span>
        </div>
        ${v.tipo_documento ? `<p style="font-size:12px;color:var(--text2);margin-bottom:10px">📄 Tipo: <strong style="color:var(--text)">${esc(v.tipo_documento).replace('_', ' ').toUpperCase()}</strong></p>` : ''}
        ${v.archivo ? (() => {
            const url = v.archivo.startsWith('uploads/') ? v.archivo : 'uploads/verificaciones/' + v.archivo;
            const ext = url.split('.').pop().toLowerCase();
            const esImg = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);
            const esPdf = ext === 'pdf';
            return `<div style="margin-bottom:12px">
            ${esImg ? `<a href="${url}" target="_blank">
              <img src="${url}" alt="Documento" style="max-width:100%;max-height:280px;border-radius:10px;border:2px solid var(--border2);cursor:zoom-in;object-fit:contain;background:#111">
            </a>` : ''}
            ${esPdf ? `<a href="${url}" target="_blank" style="display:inline-flex;align-items:center;gap:8px;padding:10px 16px;background:rgba(68,136,255,.1);border:1px solid rgba(68,136,255,.3);border-radius:10px;color:var(--blue);font-size:13px;font-weight:600;text-decoration:none">
              📄 Ver PDF del documento
            </a>` : ''}
            ${!esImg && !esPdf ? `<a href="${url}" target="_blank" style="color:var(--blue);font-size:13px">🔗 Ver archivo adjunto</a>` : ''}
          </div>`;
          })() : '<p style="font-size:12px;color:rgba(255,100,100,.7);margin-bottom:10px">⚠️ Sin documento adjunto</p>'}
        ${v.foto_doc ? (() => {
            const url2 = v.foto_doc.startsWith('uploads/') ? v.foto_doc : 'uploads/verificaciones/' + v.foto_doc;
            const ext2 = url2.split('.').pop().toLowerCase();
            return ['jpg', 'jpeg', 'png'].includes(ext2) ? `<div style="margin-bottom:12px">
            <p style="font-size:11px;color:var(--text2);margin-bottom:4px">📸 Selfie con documento:</p>
            <a href="${url2}" target="_blank">
              <img src="${url2}" alt="Selfie" style="max-width:100%;max-height:200px;border-radius:10px;border:2px solid var(--border2);cursor:zoom-in;object-fit:contain;background:#111">
            </a>
          </div>` : '';
          })() : ''}
        ${estado === 'pendiente' ? `
        <textarea class="verif-nota" id="nota-${v.id}" placeholder="Nota para el usuario (opcional)..." rows="2"></textarea>
        <div class="verif-actions">
          <button class="btn-sm green" onclick="resolverVerif(${v.id},'aprobado')">✅ Aprobar</button>
          <button class="btn-sm red" onclick="resolverVerif(${v.id},'rechazado')">❌ Rechazar</button>
        </div>` : `${v.nota_admin ? `<p style="font-size:12px;color:var(--text2);margin-top:8px">💬 Nota: ${esc(v.nota_admin)}</p>` : ''}`}
      </div>`).join('');
        } catch (e) { console.error(e); }
      }

      async function resolverVerif(id, estado) {
        const nota = document.getElementById('nota-' + id)?.value || '';
        const card = document.getElementById('vc-' + id);
        const btns = card?.querySelectorAll('button');
        btns?.forEach(b => { b.disabled = true; b.style.opacity = '0.5'; });
        try {
          const fd = new FormData();
          fd.append('id', id); fd.append('estado', estado); fd.append('nota', nota);
          const r = await fetch('gestion-qbc-2025.php?action=resolver_verificacion', { method: 'POST', body: fd });
          if (!r.ok) throw new Error('HTTP ' + r.status);
          const text = await r.text();
          let d;
          try { d = JSON.parse(text); } catch (e) { throw new Error('Respuesta inválida del servidor (posible sesión expirada)'); }
          if (d.ok) {
            card?.remove();
            if (!document.querySelector('.verif-card')) {
              document.getElementById('verif-list').innerHTML = '<div class="empty-state"><span class="ei">📭</span><p>No hay más pendientes</p></div>';
            }
            mostrarToast(estado === 'aprobado' ? '✅ Verificación aprobada' : '❌ Verificación rechazada', estado === 'aprobado' ? 'green' : 'red');
          } else {
            mostrarToast('Error: ' + (d.msg || 'No se pudo procesar'), 'red');
            btns?.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
          }
        } catch (e) {
          mostrarToast('Error: ' + e.message, 'red');
          btns?.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
        }
      }

      // ── USUARIOS CON CARDS ──
      async function cargarUsuarios() {
        const q = document.getElementById('buscar-usuario')?.value || '';
        const cards = document.getElementById('usuarios-cards');
        const empty = document.getElementById('usuarios-empty');
        const loading = document.getElementById('usuarios-loading');
        cards.style.display = 'none'; empty.style.display = 'none'; loading.style.display = 'block';
        try {
          const r = await fetch(`gestion-qbc-2025.php?action=usuarios&q=${encodeURIComponent(q)}&tipo=${tipoFiltro}&page=${paginaUsuarios}`);
          const d = await r.json();
          loading.style.display = 'none';
          if (!d.usuarios.length) { empty.style.display = 'block'; return; }
          cards.style.display = 'grid';
          cards.innerHTML = d.usuarios.map(u => {
            const ini = (u.nombre || '?').charAt(0).toUpperCase();
            const color = u.tipo === 'empresa' ? 'linear-gradient(135deg,#1a56db,#4488ff)' : 'linear-gradient(135deg,#1f9d55,#2ecc71)';
            const fotoSrc = u.foto ? `uploads/fotos/${esc(u.foto)}` : '';
            const avatar = fotoSrc
              ? `<div class="user-card-avatar"><img src="${fotoSrc}" onerror="this.parentElement.innerHTML='<span style=font-size:18px;font-weight:800>${ini}</span>'"></div>`
              : `<div class="user-card-avatar" style="background:${color}">${ini}</div>`;
            return `
      <div class="user-card">
        <div class="user-card-id">#${u.id}</div>
        <div class="user-card-top">
          ${avatar}
          <div style="overflow:hidden">
            <div class="user-card-name">${esc(u.nombre + ' ' + (u.apellido || ''))}</div>
            <div class="user-card-email">${esc(u.correo)}</div>
          </div>
        </div>
        <div class="uc-badges">
          <span class="badge ${u.tipo === 'empresa' ? 'blue' : u.tipo === 'servicio' ? 'amber' : u.tipo === 'negocio' ? 'purple' : 'green'}">${u.tipo === 'empresa' ? '🏢' : u.tipo === 'servicio' ? '🛠️' : u.tipo === 'negocio' ? '🏪' : '👤'} ${u.tipo}</span>
          ${parseInt(u.verificado) ? '<span class="badge green">✓ Verificado</span>' : ''}
          ${parseInt(u.activo) ? '<span class="badge green">🟢 Activo</span>' : '<span class="badge red">🔴 Inactivo</span>'}
          ${parseInt(u.en_talentos) ? '<span class="badge" style="background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.3)">🌟 Aparece en talentos</span>' : ''}
        </div>
        <div class="uc-grid">
          <div class="uc-field"><div class="uf-label">Cédula</div><div class="uf-val">${u.cedula || '—'}</div></div>
          <div class="uc-field"><div class="uf-label">Teléfono</div><div class="uf-val">${u.telefono || '—'}</div></div>
          <div class="uc-field"><div class="uf-label">Ciudad</div><div class="uf-val">${u.ciudad || '—'}</div></div>
          <div class="uc-field"><div class="uf-label">Registro</div><div class="uf-val">${fFecha(u.creado_en)}</div></div>
        </div>
        <div class="uc-sesion">
          <div class="uc-chip"><span class="uc-cl">Último ingreso</span><span class="uc-cv" style="color:var(--green)">${u.ultima_sesion ? fFechaHora(u.ultima_sesion) : 'Nunca'}</span></div>
          <div class="uc-chip"><span class="uc-cl">Última salida</span><span class="uc-cv">${u.ultima_salida ? fFechaHora(u.ultima_salida) : '—'}</span></div>
        </div>
        <div class="uc-actions">
          <button class="btn-sm amber" onclick="abrirModal(${u.id})" style="flex:1">✏️ Editar</button>
          ${ADMIN_PERM_TALENTOS ? `<button class="btn-sm" onclick="abrirModalTalento(${u.id},'${esc(u.nombre + ' ' + (u.apellido || ''))}')" style="border-color:rgba(0,230,118,.3);color:var(--green);background:var(--green-bg)">🌟 Aparece en talentos</button>` : ''}
          ${u.tipo === 'empresa' && ADMIN_PERM_TALENTOS ? `<button class="btn-sm" onclick="abrirModalEmpresa(${u.id},'${esc(u.nombre)}')" style="border-color:rgba(59,130,246,.3);color:#60a5fa;background:rgba(59,130,246,.08)">🏢 Perfil empresa</button>` : ''}
          <button class="btn-sm" onclick="abrirPassUser(${u.id},'${esc(u.nombre)}')" style="border-color:rgba(170,68,255,.3);color:var(--purple);background:var(--purple-bg)">🔑</button>
          ${parseInt(u.activo)
              ? `<button class="btn-sm red" onclick="toggleUsuario(${u.id},0,this)">Desactivar</button>`
              : `<button class="btn-sm green" onclick="toggleUsuario(${u.id},1,this)">Activar</button>`}
          <button class="btn-sm" onclick="eliminarUsuario(${u.id},'${esc(u.nombre + ' ' + (u.apellido || ''))}',this)" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.45);color:#f87171;" title="Eliminar permanentemente">🗑 Eliminar</button>
        </div>
      </div>`;
          }).join('');
          // Paginación
          const pages = Math.ceil(d.total / 20);
          let pags = '';
          if (pages > 1) {
            pags += `<button class="page-btn" onclick="cambiarPagina(${paginaUsuarios - 1})" ${paginaUsuarios <= 1 ? 'disabled' : ''}>←</button>`;
            for (let i = 1; i <= pages; i++) pags += `<button class="page-btn ${i === paginaUsuarios ? 'active' : ''}" onclick="cambiarPagina(${i})">${i}</button>`;
            pags += `<button class="page-btn" onclick="cambiarPagina(${paginaUsuarios + 1})" ${paginaUsuarios >= pages ? 'disabled' : ''}>→</button>`;
          }
          document.getElementById('usuarios-pagination').innerHTML = pags;
        } catch (e) { loading.style.display = 'none'; console.error(e); }
      }

      function cambiarPagina(p) { paginaUsuarios = p; cargarUsuarios(); }
      function filtroTipo(tipo, btn) {
        tipoFiltro = tipo;
        document.querySelectorAll('#section-usuarios .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        paginaUsuarios = 1;
        cargarUsuarios();
      }

      async function toggleUsuario(id, activo, btn) {
        btn.disabled = true;
        const fd = new FormData(); fd.append('id', id); fd.append('activo', activo);
        const r = await fetch('gestion-qbc-2025.php?action=toggle_usuario', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) cargarUsuarios();
        else btn.disabled = false;
      }

      async function eliminarUsuario(id, nombre, btn) {
        if (!confirm(`⚠️ ¿Eliminar PERMANENTEMENTE la cuenta de "${nombre}"?\n\nEsto borrará todos sus datos, mensajes, empleos y perfil. Esta acción NO se puede deshacer.`)) return;
        btn.disabled = true; btn.textContent = '⏳';
        const fd = new FormData(); fd.append('id', id);
        try {
          const r = await fetch('gestion-qbc-2025.php?action=eliminar_usuario', { method: 'POST', body: fd });
          const d = await r.json();
          if (d.ok) {
            mostrarToast(`🗑 Cuenta de "${nombre}" eliminada permanentemente`, 'red');
            cargarUsuarios();
          } else {
            mostrarToast('❌ ' + (d.msg || 'Error al eliminar'), 'red');
            btn.disabled = false; btn.textContent = '🗑 Eliminar';
          }
        } catch (e) {
          mostrarToast('❌ Error de conexión', 'red');
          btn.disabled = false; btn.textContent = '🗑 Eliminar';
        }
      }

      // ── EMPLEOS ──
      async function cargarEmpleos() {
        const tbody = document.getElementById('empleos-tbody');
        tbody.innerHTML = '<tr><td colspan="7" class="loading"><span class="spin">⚙️</span></td></tr>';
        try {
          const r = await fetch('gestion-qbc-2025.php?action=empleos');
          const d = await r.json();
          if (!d.empleos.length) { tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><span class="ei">💼</span><p>Sin empleos</p></td></tr>'; return; }
          tbody.innerHTML = d.empleos.map(e => {
            const dest = parseInt(e.destacado || 0);
            return `
      <tr>
        <td style="font-family:'JetBrains Mono',monospace;color:var(--text3);font-size:11px">#${e.id}</td>
        <td style="font-weight:600">${esc(e.titulo || e.cargo || 'Sin título')}</td>
        <td style="color:var(--text2);font-size:12px">${esc(e.empresa_nombre || '—')}</td>
        <td>${parseInt(e.activo) ? '<span class="badge green">Activo</span>' : '<span class="badge red">Inactivo</span>'}</td>
        <td>${dest ? '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px;background:#fef9c3;color:#b45309;border:1px solid #fde68a;">⭐ En index</span>' : '<span style="font-size:11px;color:var(--text3)">—</span>'}</td>
        <td style="font-size:11px;color:var(--text3)">${fFecha(e.creado_en)}</td>
        <td style="display:flex;gap:6px;flex-wrap:wrap">
          ${parseInt(e.activo)
              ? `<button class="btn-sm red" onclick="toggleEmpleo(${e.id},0,this)">Desactivar</button>`
              : `<button class="btn-sm green" onclick="toggleEmpleo(${e.id},1,this)">Activar</button>`}
          <button onclick="toggleEmpleoDestacado(${e.id},${dest ? 0 : 1},this)"
            style="padding:4px 10px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:${dest ? 'var(--amber)' : 'var(--text2)'};font-size:11px;font-family:'Space Grotesk',sans-serif;cursor:pointer"
            title="${dest ? 'Quitar del index' : 'Mostrar en inicio (index)'}">
            ${dest ? '⭐ Quitar index' : '☆ Poner en index'}
          </button>
        </td>
      </tr>`;
          }).join('');
        } catch (e) { console.error(e); }
      }

      async function toggleEmpleo(id, activo, btn) {
        btn.disabled = true;
        const fd = new FormData(); fd.append('id', id); fd.append('activo', activo);
        await fetch('gestion-qbc-2025.php?action=toggle_empleo', { method: 'POST', body: fd });
        cargarEmpleos();
      }

      async function toggleEmpleoDestacado(id, valor, btn) {
        btn.disabled = true;
        btn.textContent = '…';
        const fd = new FormData(); fd.append('id', id); fd.append('valor', valor);
        try {
          const r = await fetch('gestion-qbc-2025.php?action=toggle_empleo_destacado', { method: 'POST', body: fd });
          const d = await r.json();
          if (d.ok) {
            mostrarToast(valor ? '⭐ Empleo puesto en el index' : '✅ Quitado del index', 'green');
            cargarEmpleos();
          } else {
            mostrarToast('❌ ' + (d.msg || 'Error'), 'red');
            btn.disabled = false;
            btn.textContent = '?';
          }
        } catch (e) {
          mostrarToast('❌ Error de red', 'red');
          btn.disabled = false;
          btn.textContent = '?';
        }
      }

      // ── CONVOCATORIAS ──
      let convOrigenActual = 'todas';
      async function cargarConvocatorias(origen, btnEl) {
        convOrigenActual = origen || 'todas';
        // Actualizar tabs
        ['todas', 'pendiente', 'empresa', 'admin'].forEach(t => {
          const b = document.getElementById('conv-tab-' + t);
          if (!b) return;
          const activo = t === convOrigenActual;
          b.style.background = activo ? 'var(--green2)' : 'var(--bg2)';
          b.style.color = activo ? '#000' : 'var(--text)';
          b.style.border = activo ? '1px solid var(--green)' : '1px solid var(--border2)';
        });
        const tbody = document.getElementById('convocatorias-tbody');
        tbody.innerHTML = '<tr><td colspan="8" class="loading"><span class="spin">⚙️</span></td></tr>';
        try {
          const r = await fetch(`gestion-qbc-2025.php?action=convocatorias&origen=${convOrigenActual}`);
          const d = await r.json();
          if (!d.ok || !d.convocatorias.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-state"><p>Sin convocatorias en este filtro</p></td></tr>';
            return;
          }
          tbody.innerHTML = d.convocatorias.map(c => {
            const esPendiente = !parseInt(c.activo) && c.origen === 'empresa';
            const estadoBadge = parseInt(c.activo)
              ? `<span class="badge green">✅ Activa</span>`
              : esPendiente
                ? `<span class="badge amber" style="background:rgba(245,158,11,.18);color:#d97706">⏳ Pendiente</span>`
                : `<span class="badge gray">Inactiva</span>`;
            const origenBadge = c.origen === 'empresa'
              ? `<span class="badge blue">🏢 Empresa</span>`
              : `<span class="badge" style="background:rgba(99,102,241,.15);color:#6366f1">🛡️ Admin</span>`;
            const empresa = c.empresa_nombre ? `<span style="font-size:12px;color:var(--text2)">${esc(c.empresa_nombre)}</span>` : '—';
            const acciones = esPendiente
              ? `<div style="display:flex;gap:6px">
                   <button onclick="aprobarConv(${c.id},1,this)"
                     style="padding:4px 10px;border-radius:6px;border:1px solid var(--green);background:rgba(16,185,129,.15);color:var(--green);font-size:11px;cursor:pointer;font-family:'Space Grotesk',sans-serif;font-weight:700">
                     ✅ Aprobar
                   </button>
                   <button onclick="aprobarConv(${c.id},-1,this)"
                     style="padding:4px 10px;border-radius:6px;border:1px solid var(--red);background:rgba(239,68,68,.1);color:var(--red);font-size:11px;cursor:pointer;font-family:'Space Grotesk',sans-serif;font-weight:700">
                     ❌ Rechazar
                   </button>
                 </div>`
              : parseInt(c.activo)
                ? `<button onclick="toggleConv(${c.id},0,this)"
                     style="padding:4px 10px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:var(--text2);font-size:11px;cursor:pointer">
                     🔒 Desactivar
                   </button>`
                : `<button onclick="toggleConv(${c.id},1,this)"
                     style="padding:4px 10px;border-radius:6px;border:1px solid var(--green);background:rgba(16,185,129,.1);color:var(--green);font-size:11px;cursor:pointer">
                     👁 Activar
                   </button>`;
            return `<tr>
              <td style="font-weight:600;max-width:220px">${esc(c.titulo || '—')}</td>
              <td style="color:var(--text2);font-size:12px">${esc(c.entidad || '—')}</td>
              <td>${origenBadge}</td>
              <td>${empresa}</td>
              <td style="text-align:center">${c.vacantes || '—'}</td>
              <td>${estadoBadge}</td>
              <td style="font-size:11px;color:var(--text3)">${c.vence_en ? c.vence_en : '—'}</td>
              <td>${acciones}</td>
            </tr>`;
          }).join('');
        } catch (e) { tbody.innerHTML = `<tr><td colspan="8" style="color:var(--red);padding:16px">❌ ${e.message}</td></tr>`; }
      }

      async function aprobarConv(cid, activo, btn) {
        btn.disabled = true; btn.textContent = '…';
        const fd = new FormData();
        fd.append('id', cid); fd.append('activo', activo);
        try {
          const r = await fetch('gestion-qbc-2025.php?action=conv_aprobar', { method: 'POST', body: fd });
          const d = await r.json();
          if (d.ok) {
            mostrarToast(activo === 1 ? '✅ Convocatoria aprobada y publicada' : '🗑 Convocatoria rechazada', activo === 1 ? 'green' : 'red');
            cargarConvocatorias(convOrigenActual);
          } else { mostrarToast('❌ ' + (d.msg || 'Error'), 'red'); btn.disabled = false; btn.textContent = '?'; }
        } catch (e) { mostrarToast('❌ Error de red', 'red'); btn.disabled = false; btn.textContent = '?'; }
      }

      async function toggleConv(cid, activo, btn) {
        btn.disabled = true; btn.textContent = '…';
        const fd = new FormData();
        fd.append('id', cid); fd.append('activo', activo);
        try {
          const r = await fetch('gestion-qbc-2025.php?action=conv_toggle', { method: 'POST', body: fd });
          const d = await r.json();
          if (d.ok) { mostrarToast('✅ Actualizado', 'green'); cargarConvocatorias(convOrigenActual); }
          else { mostrarToast('❌ ' + (d.msg || 'Error'), 'red'); btn.disabled = false; btn.textContent = '?'; }
        } catch (e) { mostrarToast('❌ Error de red', 'red'); btn.disabled = false; btn.textContent = '?'; }
      }

      // ── MENSAJES / HISTORIAL BACKUP ──
      let _convData = null;

      async function cargarMensajes(page = 1) {
        const tbody = document.getElementById('mensajes-tbody');
        const q = document.getElementById('msg-search')?.value.trim() || '';
        tbody.innerHTML = '<tr><td colspan="7" class="loading"><span class="spin">⚙️</span></td></tr>';
        try {
          const r = await fetch(`gestion-qbc-2025.php?action=mensajes_recientes&page=${page}&q=${encodeURIComponent(q)}`);
          const d = await r.json();
          if (!d.ok || !d.mensajes.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><p>Sin mensajes</p></td></tr>';
            document.getElementById('msg-pagination').innerHTML = '';
            return;
          }
          tbody.innerHTML = d.mensajes.map(m => `
            <tr>
              <td style="font-family:'JetBrains Mono',monospace;color:var(--text3);font-size:11px">#${m.id}</td>
              <td style="font-weight:600;font-size:12px">${esc(m.de_nombre)} ${esc(m.de_apellido || '')}</td>
              <td style="font-size:12px;color:var(--text2)">${esc(m.para_nombre)} ${esc(m.para_apellido || '')}</td>
              <td style="font-size:12px;color:var(--text2);max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(m.mensaje)}">${esc(m.mensaje)}</td>
              <td style="text-align:center;font-size:14px">${parseInt(m.leido) ? '✅' : '🔵'}</td>
              <td style="font-size:11px;color:var(--text3)">${fFecha(m.creado_en)}</td>
              <td style="text-align:center"><button onclick="verConversacion(${m.de_usuario},${m.para_usuario})" style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:#a5b4fc;padding:5px 10px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;">👁 Ver</button></td>
            </tr>`).join('');

          // Paginación
          const pg = document.getElementById('msg-pagination');
          pg.innerHTML = '';
          for (let i = 1; i <= d.pages; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.onclick = () => cargarMensajes(i);
            btn.style.cssText = `padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:1px solid var(--border);${i === d.page ? 'background:var(--green);color:white;border-color:var(--green)' : 'background:var(--bg2);color:var(--text2)'}`;
            pg.appendChild(btn);
          }
        } catch (e) { console.error(e); }
      }

      async function cargarChatStats() {
        try {
          const r = await fetch('gestion-qbc-2025.php?action=chat_stats');
          const d = await r.json();
          if (!d.ok) return;
          document.querySelector('#cs-total .sm-val').textContent = d.total.toLocaleString();
          document.querySelector('#cs-hoy .sm-val').textContent = d.hoy.toLocaleString();
          document.querySelector('#cs-semana .sm-val').textContent = d.semana.toLocaleString();
          document.querySelector('#cs-convs .sm-val').textContent = d.conversaciones.toLocaleString();
          const top = document.getElementById('top-usuarios');
          top.innerHTML = d.top_usuarios.map((u, i) => `
            <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:10px 16px;display:flex;align-items:center;gap:10px;">
              <span style="font-size:18px">${['🥇', '🥈', '🥉', '4️⃣', '5️⃣'][i]}</span>
              <div>
                <div style="font-size:13px;font-weight:700;color:var(--text1)">${esc(u.nombre)} ${esc(u.apellido || '')}</div>
                <div style="font-size:11px;color:var(--text3)">${u.total} mensajes</div>
              </div>
            </div>`).join('');
        } catch (e) { console.error(e); }
      }

      async function verConversacion(u1, u2) {
        const modal = document.getElementById('modal-conv');
        const msgs = document.getElementById('conv-messages');
        modal.style.display = 'flex';
        msgs.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text3)"><span class="spin">⚙️</span></div>';
        try {
          const r = await fetch(`gestion-qbc-2025.php?action=chat_conversacion&u1=${u1}&u2=${u2}`);
          const d = await r.json();
          if (!d.ok) { msgs.innerHTML = '<p style="color:var(--red);padding:20px">Error cargando</p>'; return; }
          _convData = d;
          document.getElementById('conv-title').textContent = `${d.user1.nombre} ${d.user1.apellido || ''} ↔ ${d.user2.nombre} ${d.user2.apellido || ''}`;
          document.getElementById('conv-sub').textContent = `${d.mensajes.length} mensajes totales`;
          let lastDate = '';
          msgs.innerHTML = d.mensajes.map(m => {
            const esDe1 = parseInt(m.de_usuario) === parseInt(u1);
            const fecha = m.creado_en.split(' ')[0];
            const hora = m.creado_en.split(' ')[1]?.substring(0, 5) || '';
            let sep = '';
            if (fecha !== lastDate) { lastDate = fecha; sep = `<div style="text-align:center;font-size:10px;color:var(--text3);padding:10px 0;font-weight:600">${fecha}</div>`; }
            return `${sep}<div style="display:flex;flex-direction:column;align-items:${esDe1 ? 'flex-end' : 'flex-start'};gap:2px;margin-bottom:2px;">
              <div style="font-size:10px;color:var(--text3);padding:0 4px">${esc(m.de_nombre)}</div>
              <div style="max-width:75%;padding:9px 14px;border-radius:16px;font-size:13px;line-height:1.5;word-wrap:break-word;${esDe1 ? 'background:linear-gradient(135deg,#1a7a3c,#27a855);color:white;border-bottom-right-radius:4px' : 'background:var(--bg3,#1a1a2e);border:1px solid var(--border);color:var(--text1);border-bottom-left-radius:4px'}">
                ${esc(m.mensaje)}<span style="font-size:10px;opacity:.6;margin-left:8px">${hora}</span>
              </div>
            </div>`;
          }).join('');
          msgs.scrollTop = msgs.scrollHeight;
        } catch (e) { msgs.innerHTML = '<p style="color:var(--red);padding:20px">Error de red</p>'; }
      }

      function exportarCSV() {
        window.location.href = 'gestion-qbc-2025.php?action=mensajes_recientes&page=1&q=&export=csv';
      }

      function exportarConvCSV() {
        if (!_convData) return;
        const rows = [['ID', 'De', 'Para', 'Mensaje', 'Fecha', 'Leido']];
        _convData.mensajes.forEach(m => {
          const esU1 = parseInt(m.de_usuario) === parseInt(_convData.user1.id);
          rows.push([m.id, esc(m.de_nombre), esU1 ? `${_convData.user2.nombre} ${_convData.user2.apellido || ''}` : `${_convData.user1.nombre} ${_convData.user1.apellido || ''}`, `"${m.mensaje.replace(/"/g, '""')}"`, m.creado_en, m.leido ? 'Sí' : 'No']);
      });
      const csv = rows.map(r => r.join(',')).join('\n');
      const a = document.createElement('a');
      a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent('\uFEFF' + csv);
      a.download = `chat_${_convData.user1.nombre}_${_convData.user2.nombre}.csv`;
      a.click();
    }

    // ── CONTRASEÑA USUARIOS (superadmin + admin delegado) ──
    async function abrirPassUser(id, nombre) {
      document.getElementById('pass-user-uid').value = id;
      document.getElementById('pass-user-nombre').textContent = nombre + ' · #' + id;
      document.getElementById('pass-user-hash').textContent = 'Cargando...';
      document.getElementById('pass-user-nueva').value = '';
      document.getElementById('pass-user-confirma').value = '';
      document.getElementById('pass-user-msg').style.display = 'none';
      document.getElementById('modal-pass-user').style.display = 'flex';
      // Cargar hash
      try {
        const r = await fetch(`gestion-qbc-2025.php?action=ver_contrasena&id=${id}`);
        const d = await r.json();
        document.getElementById('pass-user-hash').textContent = d.ok ? d.hash : '❌ Sin acceso';
      } catch (e) { document.getElementById('pass-user-hash').textContent = 'Error'; }
    }
    function cerrarPassUser() { document.getElementById('modal-pass-user').style.display = 'none'; }

    async function cambiarContrasenaUser() {
      const uid = document.getElementById('pass-user-uid').value;
      const nueva = document.getElementById('pass-user-nueva').value;
      const confirma = document.getElementById('pass-user-confirma').value;
      const msg = document.getElementById('pass-user-msg');
      msg.style.display = 'block';
      if (nueva.length < 8) { msg.style.color = 'var(--red)'; msg.textContent = '❌ Mínimo 8 caracteres'; return; }
      if (nueva !== confirma) { msg.style.color = 'var(--red)'; msg.textContent = '❌ Las contraseñas no coinciden'; return; }
      const fd = new FormData();
      fd.append('id', uid); fd.append('nueva', nueva); fd.append('confirma', confirma);
      const r = await fetch('gestion-qbc-2025.php?action=cambiar_contrasena', { method: 'POST', body: fd });
      const d = await r.json();
      if (d.ok) {
        msg.style.color = 'var(--green)'; msg.textContent = '✅ Contraseña cambiada correctamente';
        setTimeout(cerrarPassUser, 1500);
      } else { msg.style.color = 'var(--red)'; msg.textContent = '❌ ' + (d.msg || 'Error'); }
    }

    // ── PERMISOS GRANULARES (solo superadmin) ──
    const PERMISOS_LABELS = {
      perm_usuarios: '👥 Gestión de usuarios',
      perm_empleos: '💼 Gestión de empleos',
      perm_solicitudes: '📋 Solicitudes de ingreso',
      perm_verificar: '✅ Verificar cuentas',
      perm_mensajes: '💬 Ver mensajes',
      perm_pagos: '💰 Historial de pagos',
      perm_stats: '📊 Ver estadísticas',
      perm_artistas: '🎵 Gestión de artistas',
      perm_badges: '🏅 Asignar badges',
      perm_convocatorias: '📋 Gestión convocatorias',
      perm_talentos: '🌟 Editar perfiles de talento',
      perm_actividad: '📋 Ver actividad del panel',
      perm_auditoria: '🕵️ Ver auditoría',
      perm_documentos: '🗂️ Repositorio documentos',
      perm_simulador: '💹 Simulador de ingresos',
    };

    async function abrirPermisos(uid) {
      document.getElementById('permisos-uid').value = uid;
      document.getElementById('permisos-msg').style.display = 'none';
      document.getElementById('permisos-grid').innerHTML = '<div style="color:var(--text3);font-size:13px">Cargando...</div>';
      document.getElementById('perm-select-all').checked = false;
      document.getElementById('perm-count-label').textContent = '0 / 0';
      document.getElementById('modal-permisos').style.display = 'flex';
      try {
        const r = await fetch(`gestion-qbc-2025.php?action=get_permisos&id=${uid}`);
        const d = await r.json();
        const p = d.permisos || {};
        document.getElementById('permisos-nombre').textContent = (p.nombre || '') + ' ' + (p.apellido || '') + ' · ' + (p.correo || '') + ' · ' + (p.nivel || '');
        document.getElementById('permisos-grid').innerHTML = Object.entries(PERMISOS_LABELS).map(([key, label]) => `
          <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:var(--bg3);border:1px solid var(--border);border-radius:10px;cursor:pointer;transition:border .15s" onmouseover="this.style.borderColor='var(--border2)'" onmouseout="this.style.borderColor='var(--border)'">
            <input type="checkbox" id="perm-${key}" name="${key}" ${parseInt(p[key] || 0) ? 'checked' : ''} onchange="actualizarContadorPermisos()" style="width:16px;height:16px;accent-color:var(--green);cursor:pointer">
            <span style="font-size:13px">${label}</span>
          </label>`).join('');
        actualizarContadorPermisos();
      } catch (e) { document.getElementById('permisos-grid').innerHTML = '<p style="color:var(--red)">Error al cargar</p>'; }
    }

    function actualizarContadorPermisos() {
      const total = Object.keys(PERMISOS_LABELS).length;
      const marcados = Object.keys(PERMISOS_LABELS).filter(k => document.getElementById('perm-' + k)?.checked).length;
      document.getElementById('perm-count-label').textContent = marcados + ' / ' + total;
      const chkAll = document.getElementById('perm-select-all');
      if (chkAll) {
        chkAll.checked = marcados === total;
        chkAll.indeterminate = marcados > 0 && marcados < total;
      }
    }

    function toggleTodosPermisos(checked) {
      Object.keys(PERMISOS_LABELS).forEach(key => {
        const el = document.getElementById('perm-' + key);
        if (el) el.checked = checked;
      });
      actualizarContadorPermisos();
    }
    function cerrarPermisos() { document.getElementById('modal-permisos').style.display = 'none'; }

    async function guardarPermisos() {
      const uid = document.getElementById('permisos-uid').value;
      const fd = new FormData();
      fd.append('usuario_id', uid);
      Object.keys(PERMISOS_LABELS).forEach(key => {
        if (document.getElementById('perm-' + key)?.checked) fd.append(key, '1');
      });
      const r = await fetch('gestion-qbc-2025.php?action=actualizar_permisos', { method: 'POST', body: fd });
      const d = await r.json();
      const msg = document.getElementById('permisos-msg');
      msg.style.display = 'block';
      if (d.ok) {
        msg.style.color = 'var(--green)'; msg.textContent = '✅ Permisos actualizados';
        setTimeout(cerrarPermisos, 1200);
      } else { msg.style.color = 'var(--red)'; msg.textContent = '❌ ' + (d.msg || 'Error'); }
    }

    // ── ROLES (actualizado con botones permisos y quitar) ──
    async function cargarRoles() {
      document.getElementById('roles-list').innerHTML = '<div class="loading"><span class="spin">⚙️</span></div>';
      try {
        const r = await fetch('gestion-qbc-2025.php?action=roles');
        const d = await r.json();
        if (!d.roles.length) { document.getElementById('roles-list').innerHTML = '<div class="empty-state"><span class="ei">👑</span><p>Sin roles asignados</p></div>'; return; }
        document.getElementById('roles-list').innerHTML = d.roles.map(r2 => `
          <div class="rol-card">
            <div class="rol-info">
              <div class="name">${esc(r2.nombre + ' ' + (r2.apellido || ''))} ${r2.nivel === 'superadmin' ? '<span class="superadmin-crown">👑</span>' : ''}</div>
              <div class="email">${esc(r2.correo)} · <span style="color:var(--amber)">${r2.nivel}</span> · ID: ${r2.usuario_id}</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              ${r2.nivel !== 'superadmin' ? `
            <button class="btn-sm" onclick="abrirPermisos(${r2.usuario_id})" style="border-color:rgba(68,136,255,.3);color:var(--blue);background:var(--blue-bg)">🔐 Permisos</button>
            <button class="btn-sm" onclick="abrirPassUser(${r2.usuario_id},'${esc(r2.nombre)}')" style="border-color:rgba(170,68,255,.3);color:var(--purple);background:var(--purple-bg)">🔑 Pass</button>
            <button class="btn-sm red" onclick="quitarRol(${r2.usuario_id})">Quitar rol</button>
          ` : '<span class="badge amber">Inamovible</span>'}
            </div>
          </div>`).join('');
      } catch (e) { console.error(e); }
    }

    async function asignarRol() {
      const uid = document.getElementById('rol-uid').value;
      const nivel = document.getElementById('rol-nivel').value;
      if (!uid) { mostrarToast('Selecciona un usuario primero', 'red'); return; }
      const fd = new FormData(); fd.append('usuario_id', uid); fd.append('nivel', nivel);
      const r = await fetch('gestion-qbc-2025.php?action=asignar_rol', { method: 'POST', body: fd });
      const d = await r.json();
      if (d.ok) {
        limpiarSeleccionRol();
        mostrarToast('✅ Rol asignado correctamente', 'green');
        cargarRoles();
      } else {
        mostrarToast('❌ ' + (d.msg || 'Error al asignar rol'), 'red');
      }
    }

    let rolBuscarTimer = null;
    function buscarUsuarioRol(q) {
      clearTimeout(rolBuscarTimer);
      const sug = document.getElementById('rol-sugerencias');
      if (!q || q.length < 2) { sug.style.display = 'none'; return; }
      rolBuscarTimer = setTimeout(async () => {
        try {
          const r = await fetch('gestion-qbc-2025.php?action=buscar_usuario_correo&q=' + encodeURIComponent(q));
          const d = await r.json();
          if (!d.usuarios || !d.usuarios.length) { sug.innerHTML = '<div style="padding:12px 14px;font-size:13px;color:var(--text3)">Sin resultados</div>'; sug.style.display = 'block'; return; }
          sug.innerHTML = d.usuarios.map(u => {
            const tieneRol = u.rol_actual ? `<span style="font-size:10px;color:var(--amber);background:var(--amber-bg);padding:2px 7px;border-radius:8px;margin-left:6px">${u.rol_actual}</span>` : '';
            return `<div onclick="seleccionarUsuarioRol(${u.id},'${esc(u.nombre + ' ' + (u.apellido || ''))}','${esc(u.correo)}','${esc(u.tipo || '')}','${esc(u.rol_actual || '')}')"
                    style="padding:11px 14px;cursor:pointer;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:2px;transition:background .15s"
                    onmouseover="this.style.background='var(--bg3)'" onmouseout="this.style.background='transparent'">
                    <div style="font-size:13px;font-weight:600">${esc(u.nombre + ' ' + (u.apellido || ''))}${tieneRol}</div>
                    <div style="font-size:11px;color:var(--text2)">${esc(u.correo)} · ${esc(u.tipo || '')}</div>
                  </div>`;
          }).join('');
          sug.style.display = 'block';
        } catch (e) { sug.style.display = 'none'; }
      }, 300);
    }

    function seleccionarUsuarioRol(id, nombre, correo, tipo, rolActual) {
      document.getElementById('rol-uid').value = id;
      document.getElementById('rol-buscar').value = nombre + ' — ' + correo;
      document.getElementById('rol-sugerencias').style.display = 'none';
      const prev = document.getElementById('rol-preview');
      const info = document.getElementById('rol-preview-info');
      info.innerHTML = `<strong style="color:var(--green)">${esc(nombre)}</strong> <span style="color:var(--text2);font-size:12px">· ${esc(correo)} · ${esc(tipo)}</span>`
        + (rolActual ? ` <span style="font-size:11px;color:var(--amber);background:var(--amber-bg);padding:2px 8px;border-radius:8px;margin-left:6px">Ya tiene rol: ${esc(rolActual)}</span>` : '');
      prev.style.display = 'block';
      // Click fuera cierra sugerencias
      document.addEventListener('click', function handler(e) {
        if (!document.getElementById('rol-buscar')?.contains(e.target)) {
          document.getElementById('rol-sugerencias').style.display = 'none';
          document.removeEventListener('click', handler);
        }
      });
    }

    function limpiarSeleccionRol() {
      document.getElementById('rol-uid').value = '';
      document.getElementById('rol-buscar').value = '';
      document.getElementById('rol-preview').style.display = 'none';
      document.getElementById('rol-sugerencias').style.display = 'none';
    }

    // ── UTILIDADES ──
    function esc(str) { const d2 = document.createElement('div'); d2.textContent = str || ''; return d2.innerHTML; }
    function fFecha(f) {
      if (!f) return '—';
      const d2 = new Date(f.replace(' ', 'T'));
      return d2.toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function fFechaHora(f) {
      if (!f) return '—';
      const d2 = new Date(f.replace(' ', 'T'));
      return d2.toLocaleDateString('es-CO', { day: '2-digit', month: 'short' }) + ' ' +
        d2.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
    }

    let debounceTimer = null;
    function debounce(fn, ms) { return () => { clearTimeout(debounceTimer); debounceTimer = setTimeout(fn, ms); }; }

    // ── MODAL EDITAR USUARIO ──
    // Toggle switch — data-on stores state, no checkbox needed
    function toggleSwitch(id) {
      const sw = document.getElementById(id);
      const dot = document.getElementById(id + '-dot');
      const on = sw.dataset.on === '1';
      sw.dataset.on = on ? '0' : '1';
      sw.style.background = on ? 'rgba(255,255,255,0.15)' : '#1f9d55';
      dot.style.transform = on ? 'translateX(0)' : 'translateX(20px)';
    }

    function setSwitch(id, val) {
      const sw = document.getElementById(id);
      const dot = document.getElementById(id + '-dot');
      sw.dataset.on = val ? '1' : '0';
      sw.style.background = val ? '#1f9d55' : 'rgba(255,255,255,0.15)';
      dot.style.transform = val ? 'translateX(20px)' : 'translateX(0)';
    }

    async function abrirModal(id) {
      const r = await fetch(`gestion-qbc-2025.php?action=get_usuario&id=${id}`);
      const d = await r.json();
      if (!d.ok || !d.usuario) return;
      const u = d.usuario;
      document.getElementById('modal-uid').value = u.id;
      document.getElementById('modal-uid-label').textContent = `#${u.id}`;
      document.getElementById('modal-nombre').value = u.nombre || '';
      document.getElementById('modal-apellido').value = u.apellido || '';
      document.getElementById('modal-correo').value = u.correo || '';
      document.getElementById('modal-cedula').value = u.cedula || '';
      document.getElementById('modal-fnac').value = u.tipo === 'empresa' ? (u.fecha_empresa || '') : (u.fecha_nacimiento || '');
      document.getElementById('modal-ciudad').value = u.ciudad || '';
      document.getElementById('modal-telefono').value = u.telefono || '';
      document.getElementById('modal-tipo').value = u.tipo || 'candidato';
      document.getElementById('modal-ultima-sesion').textContent = u.ultima_sesion ? fFechaHora(u.ultima_sesion) : 'Nunca';
      document.getElementById('modal-ultima-salida').textContent = u.ultima_salida ? fFechaHora(u.ultima_salida) : '—';
      // Toggles
      setSwitch('sw-talentos', parseInt(u.en_talentos));
      setSwitch('sw-destacado', parseInt(u.destacado));
      // Permisos: solo superadmin y admin delegado
      const puedeDestacar = ADMIN_NIVEL === 'superadmin' || ADMIN_NIVEL === 'admin';
      const togWrapper = document.getElementById('toggles-visibilidad');
      if (togWrapper) {
        togWrapper.style.opacity = puedeDestacar ? '1' : '0.45';
        togWrapper.style.pointerEvents = puedeDestacar ? 'auto' : 'none';
        togWrapper.title = puedeDestacar ? '' : 'Solo superadmin y admin delegado';
      }
      // Cambiar labels según tipo
      const esEmpresa = u.tipo === 'empresa';
      document.getElementById('modal-fnac-label').textContent = esEmpresa ? 'Fecha de fundación' : 'Fecha de nacimiento';
      document.getElementById('modal-cedula-label').textContent = esEmpresa ? 'NIT' : 'Cédula';
      document.getElementById('modal-nombre-label').textContent = esEmpresa ? 'Nombre empresa' : 'Nombre';
      document.getElementById('modal-apellido').parentElement.style.display = esEmpresa ? 'none' : 'block';
      document.getElementById('modal-msg').style.display = 'none';
      document.getElementById('modal-usuario').style.display = 'flex';
    }

    function cerrarModal() {
      document.getElementById('modal-usuario').style.display = 'none';
    }

    async function guardarUsuario() {
      const fd = new FormData();
      fd.append('id', document.getElementById('modal-uid').value);
      fd.append('nombre', document.getElementById('modal-nombre').value);
      fd.append('apellido', document.getElementById('modal-apellido').value);
      fd.append('correo', document.getElementById('modal-correo').value);
      fd.append('cedula', document.getElementById('modal-cedula').value);
      fd.append('fecha_nacimiento', document.getElementById('modal-fnac').value);
      fd.append('ciudad', document.getElementById('modal-ciudad').value);
      fd.append('telefono', document.getElementById('modal-telefono').value);
      fd.append('tipo', document.getElementById('modal-tipo').value);
      fd.append('en_talentos', document.getElementById('sw-talentos').dataset.on === '1' ? '1' : '0');
      fd.append('destacado', document.getElementById('sw-destacado').dataset.on === '1' ? '1' : '0');
      const r = await fetch('gestion-qbc-2025.php?action=editar_usuario', { method: 'POST', body: fd });
      const d = await r.json();
      const msg = document.getElementById('modal-msg');
      msg.style.display = 'block';
      if (d.ok) {
        msg.style.color = 'var(--green)';
        msg.textContent = '✅ Guardado correctamente';
        setTimeout(() => { cerrarModal(); cargarUsuarios(); }, 1200);
      } else {
        msg.style.color = 'var(--red)';
        msg.textContent = '❌ ' + (d.msg || 'Error al guardar');
      }
    }

    // ── ESTADÍSTICAS DETALLADAS ──
    async function cargarEstadisticas() {
      document.getElementById('stats-content').innerHTML = '<div class="loading"><span class="spin">⚙️</span></div>';
      try {
        const r = await fetch('gestion-qbc-2025.php?action=stats_detalladas');
        const d = await r.json();
        if (!d.ok) return;
        const s = d.data;

        // Barras ciudades
        const maxCiudad = Math.max(...(s.por_ciudad.map(c => parseInt(c.total))), 1);
        const ciudadBars = s.por_ciudad.map(c => `
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
            <div style="width:100px;font-size:12px;color:var(--text2);text-align:right;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(c.ciudad || 'Sin ciudad')}</div>
            <div style="flex:1;height:28px;background:var(--bg3);border-radius:6px;overflow:hidden">
              <div style="height:100%;width:${Math.max(4, (parseInt(c.total) / maxCiudad) * 100)}%;background:linear-gradient(90deg,var(--blue),var(--green));border-radius:6px;transition:width .5s"></div>
            </div>
            <div style="font-size:12px;font-family:'JetBrains Mono',monospace;color:var(--green);width:30px">${c.total}</div>
          </div>`).join('');

        // Barras meses
        const maxMes = Math.max(...(s.por_mes.map(m => parseInt(m.total))), 1);
        const mesBars = s.por_mes.map(m => {
          const h = Math.max(4, (parseInt(m.total) / maxMes) * 80);
          return `<div class="chart-bar-col">
            <div class="chart-bar-val">${m.total}</div>
            <div class="chart-bar" style="height:${h}px;background:linear-gradient(180deg,var(--blue),var(--green))"></div>
            <div class="chart-bar-label">${m.mes.slice(5)}</div>
          </div>`;
        }).join('');

        // Verificaciones donut text
        const verifTexto = s.verif_estado.map(v => `
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
            <span style="color:var(--text2)">${v.estado}</span>
            <span class="badge ${v.estado === 'aprobado' ? 'green' : v.estado === 'pendiente' ? 'amber' : 'red'}">${v.total}</span>
          </div>`).join('') || '<p style="color:var(--text3);font-size:13px">Sin datos</p>';

        document.getElementById('stats-content').innerHTML = `
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div class="chart-wrap">
              <h3>🗓️ Registros por mes</h3>
              <div class="chart-bars" style="height:100px">${mesBars || '<p style="color:var(--text3);font-size:12px">Sin datos</p>'}</div>
            </div>
            <div class="chart-wrap">
              <h3>✅ Estado verificaciones</h3>
              ${verifTexto}
            </div>
          </div>
          <div class="chart-wrap">
            <h3>🌍 Usuarios por ciudad</h3>
            ${ciudadBars || '<p style="color:var(--text3);font-size:13px">Sin datos de ciudad</p>'}
          </div>`;
      } catch (e) { console.error(e); }
    }

    // ── AUDITORÍA ──
    let paginaAuditoria = 1;
    async function cargarAuditoria() {
      const tbody = document.getElementById('auditoria-tbody');
      tbody.innerHTML = '<tr><td colspan="5" class="loading"><span class="spin">⚙️</span></td></tr>';
      try {
        const r = await fetch(`gestion-qbc-2025.php?action=auditoria&page=${paginaAuditoria}`);
        const d = await r.json();
        if (!d.logs.length) {
          tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><span class="ei">🕵️</span><p>Sin registros de auditoría aún</p></td></tr>';
          return;
        }
        tbody.innerHTML = d.logs.map(l => `
          <tr>
            <td style="font-family:'JetBrains Mono',monospace;color:var(--text3);font-size:11px">#${l.id}</td>
            <td style="font-weight:600;font-size:12px">${esc(l.admin_nombre || 'Sistema')}</td>
            <td><span class="badge blue">${esc(l.accion)}</span></td>
            <td style="font-size:12px;color:var(--text2)">${esc(l.detalle || '—')}</td>
            <td style="font-size:11px;color:var(--text3)">${fFecha(l.creado_en)}</td>
          </tr>`).join('');
        const pages = Math.ceil(d.total / 30);
        let pags = '';
        if (pages > 1) {
          pags += `<button class="page-btn" onclick="cambiarPagAudit(${paginaAuditoria - 1})" ${paginaAuditoria <= 1 ? 'disabled' : ''}>←</button>`;
          for (let i = 1; i <= Math.min(pages, 7); i++) pags += `<button class="page-btn ${i === paginaAuditoria ? 'active' : ''}" onclick="cambiarPagAudit(${i})">${i}</button>`;
          pags += `<button class="page-btn" onclick="cambiarPagAudit(${paginaAuditoria + 1})" ${paginaAuditoria >= pages ? 'disabled' : ''}>→</button>`;
        }
        document.getElementById('auditoria-pagination').innerHTML = pags;
      } catch (e) { console.error(e); }
    }
    function cambiarPagAudit(p) { paginaAuditoria = p; cargarAuditoria(); }

    // ── SISTEMA DE BADGES ──
    let badgesCatalogo = [];
    let badgesUsuarioActual = null;

    async function cargarBadgesCatalogo() {
      document.getElementById('badges-catalogo-wrap').innerHTML = '<div class="loading"><span class="spin">⚙️</span></div>';
      try {
        const r = await fetch('gestion-qbc-2025.php?action=badges_catalogo');
        const d = await r.json();
        badgesCatalogo = d.badges || [];
        if (!badgesCatalogo.length) {
          document.getElementById('badges-catalogo-wrap').innerHTML = '<div class="empty-state"><span class="ei">🏅</span><p>No hay badges en el catálogo</p></div>';
          return;
        }
        // Beneficios por plan (basado en planes de precios QuibdoConecta)
        const PLAN_BENEFICIOS = {
          'Verde SELVA': {
            precio: '$12.900/mes', slug: 'verde_selva', color: '#4caf50',
            beneficios: [
              '📋 Perfil de empresa en el directorio',
              '🔍 Visible en búsquedas de la plataforma',
              '📞 Datos de contacto visibles',
              '🖼️ Hasta 3 fotos del negocio',
              '🏷️ 1 categoría de servicio',
              '⭐ Badge "Verde Selva" en perfil',
            ]
          },
          'Amarillo Oro': {
            precio: '$29.900/mes', slug: 'amarillo_oro', color: '#ffc107',
            beneficios: [
              '✅ Todo lo del plan Verde Selva',
              '🌟 Posición destacada en directorio',
              '🖼️ Hasta 10 fotos del negocio',
              '🏷️ Hasta 3 categorías de servicio',
              '📊 Estadísticas de visitas al perfil',
              '💬 Mensajes directos desde el perfil',
              '🎯 Aparece en sección "Destacados"',
              '⭐ Badge "Amarillo Oro" en perfil',
            ]
          },
          'Azul Profundo': {
            precio: '$49.900/mes', slug: 'azul_profundo', color: '#2196f3',
            beneficios: [
              '✅ Todo lo del plan Amarillo Oro',
              '🏆 Prioridad máxima en resultados',
              '🖼️ Fotos ilimitadas del negocio',
              '🏷️ Categorías ilimitadas',
              '📈 Estadísticas avanzadas y reportes',
              '🤝 Soporte prioritario del equipo',
              '🎪 Aparece en banner de inicio',
              '📣 Publicaciones en redes del proyecto',
              '💎 Badge "Azul Profundo" en perfil',
            ]
          },
          'Microempresa': {
            precio: '$19.900/mes', slug: 'microempresa', color: '#9c27b0',
            beneficios: [
              '📋 Perfil completo de microempresa',
              '🔍 Visible en directorio de negocios',
              '🖼️ Hasta 6 fotos del negocio',
              '🏷️ Hasta 2 categorías',
              '📞 Contacto visible',
              '⭐ Badge "Microempresa" en perfil',
            ]
          }
        };

        // Agrupar por tipo
        const grupos = { manual: '🏅 Manuales', pago: '💰 De pago', verificacion: '✅ Verificación' };
        let html = '';
        for (const [tipo, label] of Object.entries(grupos)) {
          const del_tipo = badgesCatalogo.filter(b => b.tipo === tipo);
          if (!del_tipo.length) continue;
          html += `<h3 style="font-size:13px;font-weight:700;color:var(--text2);margin:0 0 12px;text-transform:uppercase;letter-spacing:.8px">${label}</h3>`;
          const minW = tipo === 'pago' ? '300px' : '240px';
          html += `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(${minW},1fr));gap:12px;margin-bottom:24px">`;
          html += del_tipo.map(b => {
            const planInfo = PLAN_BENEFICIOS[b.nombre] || null;
            // Beneficios: primero usa el campo guardado en BD, si no, usa el mapa de planes predefinidos
            let beneficiosHtml = '';
            if (b.beneficios && b.beneficios.trim()) {
              const lineas = b.beneficios.split('\n').map(l => l.trim()).filter(Boolean);
              beneficiosHtml = `
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
                  <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">💡 Beneficios incluidos</div>
                  <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:4px">
                    ${lineas.map(ben => `<li style="font-size:11px;color:var(--text2)">${esc(ben)}</li>`).join('')}
                  </ul>
                  ${planInfo ? `<div style="margin-top:10px;display:inline-block;padding:4px 10px;background:${b.color}18;border:1px solid ${b.color}44;border-radius:20px;font-size:11px;font-weight:700;color:${b.color}">${planInfo.precio}</div>` : ''}
                </div>`;
            } else if (planInfo) {
              beneficiosHtml = `
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
                  <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">💡 Beneficios incluidos</div>
                  <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:4px">
                    ${planInfo.beneficios.map(ben => `<li style="font-size:11px;color:var(--text2)">${ben}</li>`).join('')}
                  </ul>
                  <div style="margin-top:10px;display:inline-block;padding:4px 10px;background:${b.color}18;border:1px solid ${b.color}44;border-radius:20px;font-size:11px;font-weight:700;color:${b.color}">${planInfo.precio}</div>
                </div>`;
            }
            return `
              <div style="background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                  <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:38px;height:38px;border-radius:10px;background:${b.color}22;border:1px solid ${b.color}44;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">${b.emoji}</div>
                    <div>
                      <div style="font-size:13px;font-weight:700;color:${b.color}">${esc(b.nombre)}</div>
                      <div style="font-size:11px;color:var(--text3)">${esc(b.descripcion || '—')}</div>
                    </div>
                  </div>
                  <div style="display:flex;gap:6px;flex-shrink:0">
                    <button onclick="editarBadge(${b.id})" class="btn-sm amber" style="font-size:11px">✏️</button>
                    ${ADMIN_NIVEL === 'superadmin' ? `<button onclick="eliminarBadge(${b.id},'${esc(b.nombre)}')" class="btn-sm red" style="font-size:11px">🗑️</button>` : ''}
                  </div>
                </div>
                ${beneficiosHtml}
              </div>`;
          }).join('');
          html += '</div>';
        }
        document.getElementById('badges-catalogo-wrap').innerHTML = `<div style="background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:24px;margin-bottom:0">${html}</div>`;
      } catch (e) { console.error(e); }
    }

    // Cargar badges de un usuario específico
    async function cargarBadgesUsuario() {
      const uid = document.getElementById('badge-uid').value;
      if (!uid) { alert('Escribe el ID del usuario'); return; }
      try {
        const r = await fetch(`gestion-qbc-2025.php?action=usuario_badges&id=${uid}`);
        const d = await r.json();
        if (!d.ok) { alert('Usuario no encontrado'); return; }
        badgesUsuarioActual = { uid: parseInt(uid), asignados: d.asignados || [] };
        badgesCatalogo = d.catalogo || [];

        document.getElementById('badges-usuario-info').innerHTML =
          `<strong style="color:var(--green)">Usuario #${uid}</strong> — Badges asignados: <strong>${d.asignados.length}</strong>`;

        document.getElementById('badges-usuario-grid').innerHTML = badgesCatalogo.map(b => {
          const tiene = d.asignados.includes(b.id);
          return `
            <div id="badge-card-${b.id}" style="background:var(--bg3);border:2px solid ${tiene ? b.color : 'var(--border)'};border-radius:12px;padding:14px;display:flex;flex-direction:column;align-items:center;gap:8px;transition:border .2s">
              <div style="font-size:28px">${b.emoji}</div>
              <div style="font-size:12px;font-weight:700;color:${b.color};text-align:center">${esc(b.nombre)}</div>
              <div style="font-size:10px;color:var(--text3);text-align:center">${esc(b.descripcion || '')}</div>
              <button onclick="toggleBadge(${uid},${b.id},${tiene ? 0 : 1})" 
                id="badge-btn-${b.id}"
                style="width:100%;padding:6px;border-radius:8px;border:1px solid ${tiene ? 'rgba(255,68,68,.3)' : 'rgba(0,230,118,.3)'};background:${tiene ? 'var(--red-bg)' : 'var(--green-bg)'};color:${tiene ? 'var(--red)' : 'var(--green)'};font-size:11px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">
                ${tiene ? '❌ Quitar' : '✅ Asignar'}
              </button>
            </div>`;
        }).join('');

        document.getElementById('badges-usuario-extra').innerHTML = `          <div style="margin-top:12px;background:rgba(0,230,118,.06);border:1px solid rgba(0,230,118,.2);border-radius:10px;padding:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span style="font-size:12px;color:rgba(255,255,255,.6);font-weight:600">⚡ Asignar plan de pago:</span>
                <select id="plan-selector" style="background:var(--bg3);border:1px solid var(--border);border-radius:8px;padding:6px 12px;color:#fff;font-family:'Space Grotesk',sans-serif;font-size:12px">
                  <option value="semilla">🌱 Semilla (gratis)</option>
                  <option value="verde_selva">🌿 Verde Selva</option>
                  <option value="amarillo_oro">⭐ Amarillo Oro</option>
                  <option value="azul_profundo">💎 Azul Profundo</option>
                  <option value="microempresa">🏪 Microempresa</option>
                </select>
                <button onclick="asignarPlan(badgesUsuarioActual?.uid, document.getElementById('plan-selector').value)" 
                  class="btn-sm green" style="font-size:12px">Aplicar plan</button>
              </div>`;
        document.getElementById('badges-usuario-panel').style.display = 'block';
      } catch (e) { console.error(e); }
    }

    async function toggleBadge(uid, badgeId, asignar) {
      const btn = document.getElementById('badge-btn-' + badgeId);
      btn.disabled = true;
      const fd = new FormData();
      fd.append('usuario_id', uid);
      fd.append('badge_id', badgeId);
      fd.append('asignar', asignar);
      try {
        const r = await fetch('gestion-qbc-2025.php?action=badge_toggle', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          // Recargar panel del usuario
          badgesUsuarioActual.asignados = d.asignados;
          document.getElementById('badge-uid').value = uid;
          cargarBadgesUsuario();
        }
      } catch (e) { btn.disabled = false; }
    }


    // ── ASIGNAR PLAN (atajo desde panel de badges) ──────────────
    async function asignarPlan(uid, plan) {
      if (!uid || !plan) { alert('Selecciona un usuario y un plan.'); return; }
      if (!confirm(`¿Asignar plan "${plan}" al usuario #${uid}? Se reemplazará el plan anterior.`)) return;
      const fd = new FormData();
      fd.append('usuario_id', uid);
      fd.append('plan', plan);
      try {
        const r = await fetch('gestion-qbc-2025.php?action=asignar_plan', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          mostrarToast('✅ ' + d.msg, 'green');
          document.getElementById('badge-uid').value = uid;
          cargarBadgesUsuario();
        } else {
          mostrarToast('❌ ' + (d.msg || 'Error'), 'red');
        }
      } catch (e) { mostrarToast('Error de red', 'red'); }
    }

    // CREAR badge
    function abrirCrearBadge() {
      document.getElementById('badge-edit-id').value = '';
      document.getElementById('modal-badge-titulo').textContent = '➕ Crear badge';
      document.getElementById('badge-emoji').value = '🏅';
      document.getElementById('badge-nombre').value = '';
      document.getElementById('badge-desc').value = '';
      document.getElementById('badge-color').value = '#00e676';
      document.getElementById('badge-color-text').value = '#00e676';
      document.getElementById('badge-tipo').value = 'manual';
      document.getElementById('badge-beneficios').value = '';
      document.getElementById('badge-msg').style.display = 'none';
      actualizarPreviewBadge();
      document.getElementById('modal-badge').style.display = 'flex';
    }

    function editarBadge(id) {
      const b = badgesCatalogo.find(x => x.id == id);
      if (!b) return;
      document.getElementById('badge-edit-id').value = id;
      document.getElementById('modal-badge-titulo').textContent = '✏️ Editar badge';
      document.getElementById('badge-emoji').value = b.emoji;
      document.getElementById('badge-nombre').value = b.nombre;
      document.getElementById('badge-desc').value = b.descripcion || '';
      document.getElementById('badge-color').value = b.color;
      document.getElementById('badge-color-text').value = b.color;
      document.getElementById('badge-tipo').value = b.tipo;
      document.getElementById('badge-beneficios').value = b.beneficios || '';
      document.getElementById('badge-msg').style.display = 'none';
      actualizarPreviewBadge();
      document.getElementById('modal-badge').style.display = 'flex';
    }

    function cerrarModalBadge() { document.getElementById('modal-badge').style.display = 'none'; }

    function actualizarPreviewBadge() {
      const emoji = document.getElementById('badge-emoji')?.value || '⭐';
      const nombre = document.getElementById('badge-nombre')?.value || 'Badge';
      const color = document.getElementById('badge-color')?.value || '#00e676';
      const prev = document.getElementById('badge-preview');
      if (prev) {
        prev.textContent = `${emoji} ${nombre}`;
        prev.style.color = color;
        prev.style.background = color + '22';
        prev.style.borderColor = color + '44';
      }
      const ct = document.getElementById('badge-color-text');
      if (ct) ct.value = color;
    }

    // Actualizar preview en tiempo real
    document.addEventListener('input', e => {
      if (['badge-emoji', 'badge-nombre', 'badge-color', 'badge-color-text'].includes(e.target?.id)) {
        actualizarPreviewBadge();
      }
    });

    async function guardarBadge() {
      const id = document.getElementById('badge-edit-id').value;
      const nombre = document.getElementById('badge-nombre').value.trim();
      const emoji = document.getElementById('badge-emoji').value.trim();
      const desc = document.getElementById('badge-desc').value.trim();
      const color = document.getElementById('badge-color').value;
      const tipo = document.getElementById('badge-tipo').value;
      const beneficios = document.getElementById('badge-beneficios').value.trim();
      const msg = document.getElementById('badge-msg');

      if (!nombre) { msg.style.display = 'block'; msg.style.color = 'var(--red)'; msg.textContent = '❌ El nombre es obligatorio'; return; }

      const fd = new FormData();
      fd.append('nombre', nombre); fd.append('emoji', emoji);
      fd.append('descripcion', desc); fd.append('color', color); fd.append('tipo', tipo);
      fd.append('beneficios', beneficios);

      const action = id ? 'badge_editar' : 'badge_crear';
      if (id) fd.append('id', id);

      const r = await fetch(`gestion-qbc-2025.php?action=${action}`, { method: 'POST', body: fd });
      const d = await r.json();
      msg.style.display = 'block';
      if (d.ok) {
        msg.style.color = 'var(--green)';
        msg.textContent = id ? '✅ Badge actualizado' : '✅ Badge creado';
        setTimeout(() => { cerrarModalBadge(); cargarBadgesCatalogo(); }, 1000);
      } else { msg.style.color = 'var(--red)'; msg.textContent = '❌ ' + (d.msg || 'Error'); }
    }

    async function eliminarBadge(id, nombre) {
      if (!confirm(`¿Eliminar el badge "${nombre}"?\nSe quitará de todos los usuarios que lo tengan.`)) return;
      const fd = new FormData(); fd.append('id', id);
      await fetch('gestion-qbc-2025.php?action=badge_eliminar', { method: 'POST', body: fd });
      cargarBadgesCatalogo();
    }

    // ── ACTIVIDAD ──
    let actFiltro = '';
    let actPagina = 1;

    function filtroActividad(tipo, btn) {
      actFiltro = tipo;
      actPagina = 1;
      document.querySelectorAll('#act-filtros .filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      cargarActividad();
    }

    const ACT_ICONS = {
      editar_usuario: { ic: '✏️', color: 'var(--amber-bg)' },
      badge_toggle: { ic: '🏅', color: 'var(--purple-bg)' },
      badge_crear: { ic: '➕', color: 'var(--green-bg)' },
      toggle_usuario: { ic: '🔄', color: 'var(--blue-bg)' },
      cambiar_contrasena: { ic: '🔑', color: 'var(--red-bg)' },
      ver_contrasena: { ic: '👁', color: 'var(--amber-bg)' },
      asignar_rol: { ic: '👑', color: 'var(--amber-bg)' },
      quitar_rol: { ic: '🚫', color: 'var(--red-bg)' },
      resolver_verificacion: { ic: '✅', color: 'var(--green-bg)' },
      cambiar_emergency_code: { ic: '🚨', color: 'var(--red-bg)' },
      actualizar_permisos: { ic: '🔐', color: 'var(--blue-bg)' },
    };

    async function cargarActividad() {
      document.getElementById('act-feed').innerHTML = '<div class="loading"><span class="spin">⚙️</span></div>';
      try {
        const r = await fetch(`gestion-qbc-2025.php?action=auditoria&page=${actPagina}&filtro=${actFiltro}`);
        const d = await r.json();
        if (!d.logs || !d.logs.length) {
          document.getElementById('act-feed').innerHTML = '<div class="empty-state"><span class="ei">📋</span><p>Sin actividad registrada</p></div>';
          return;
        }
        document.getElementById('act-feed').innerHTML = '<div class="act-feed">' + d.logs.map(l => {
          const meta = ACT_ICONS[l.accion] || { ic: '⚙️', color: 'var(--bg3)' };
          return `
          <div class="act-item">
            <div class="act-icon" style="background:${meta.color}">${meta.ic}</div>
            <div class="act-body">
              <div class="act-admin">${esc(l.admin_nombre || 'Sistema')}</div>
              <div class="act-desc">${esc(l.detalle || l.accion)}</div>
              <div class="act-time">${fFechaHora(l.creado_en)}</div>
            </div>
          </div>`;
        }).join('') + '</div>';
        const pages = Math.ceil(d.total / 30);
        let pags = '';
        if (pages > 1) {
          pags += `<button class="page-btn" onclick="cambiarPagAct(${actPagina - 1})" ${actPagina <= 1 ? 'disabled' : ''}>←</button>`;
          for (let i = 1; i <= Math.min(pages, 7); i++) pags += `<button class="page-btn ${i === actPagina ? 'active' : ''}" onclick="cambiarPagAct(${i})">${i}</button>`;
          pags += `<button class="page-btn" onclick="cambiarPagAct(${actPagina + 1})" ${actPagina >= pages ? 'disabled' : ''}>→</button>`;
        }
        document.getElementById('act-pagination').innerHTML = pags;
      } catch (e) { console.error(e); }
    }
    function cambiarPagAct(p) { actPagina = p; cargarActividad(); }

    // ── FOTO DE PERFIL ADMIN ──
    async function subirFotoAdmin(input) {
      if (!input.files[0]) return;
      const fd = new FormData();
      fd.append('foto', input.files[0]);
      try {
        const r = await fetch('gestion-qbc-2025.php?action=subir_foto_admin', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          // Recargar página para mostrar foto en sidebar
          location.reload();
        } else {
          alert('Error: ' + (d.msg || 'No se pudo subir la foto'));
        }
      } catch (e) { alert('Error de conexión'); }
    }

    // ── CÓDIGO DE EMERGENCIA ──
    let emergencyCodeReal = null;
    let emergencyVisible = false;

    async function toggleVerEmergencia() {
      const display = document.getElementById('emergency-display');
      const btn = document.getElementById('btn-ver-emergency');
      if (!emergencyVisible) {
        if (!emergencyCodeReal) {
          try {
            const r = await fetch('gestion-qbc-2025.php?action=get_emergency_code');
            const d = await r.json();
            if (d.ok) emergencyCodeReal = d.code;
            else { alert('Error al obtener el código'); return; }
          } catch (e) { alert('Error de conexión'); return; }
        }
        display.textContent = emergencyCodeReal;
        display.style.color = 'var(--green)';
        btn.textContent = '🙈 Ocultar';
        emergencyVisible = true;
      } else {
        display.textContent = '••••••••••••••••';
        display.style.color = 'var(--amber)';
        btn.textContent = '👁 Ver';
        emergencyVisible = false;
      }
    }

    async function copiarEmergencia() {
      if (!emergencyCodeReal) {
        try {
          const r = await fetch('gestion-qbc-2025.php?action=get_emergency_code');
          const d = await r.json();
          if (d.ok) emergencyCodeReal = d.code;
          else { alert('Error al obtener el código'); return; }
        } catch (e) { alert('Error de conexión'); return; }
      }
      try {
        await navigator.clipboard.writeText(emergencyCodeReal);
        const msg = document.getElementById('emergency-msg');
        msg.style.display = 'block';
        msg.style.color = 'var(--green)';
        msg.textContent = '✅ Código copiado al portapapeles';
        setTimeout(() => msg.style.display = 'none', 2000);
      } catch (e) { alert('No se pudo copiar. Código: ' + emergencyCodeReal); }
    }

    async function cambiarEmergencia() {
      const nuevo = document.getElementById('nuevo-emergency').value.trim();
      const msg = document.getElementById('emergency-msg');
      msg.style.display = 'block';
      if (nuevo.length < 10) {
        msg.style.color = 'var(--red)';
        msg.textContent = '❌ Mínimo 10 caracteres';
        return;
      }
      if (!confirm(`¿Cambiar el código de emergencia a:\n"${nuevo}"\n\nGuarda este código en un lugar seguro antes de confirmar.`)) return;
      const fd = new FormData();
      fd.append('codigo', nuevo);
      try {
        const r = await fetch('gestion-qbc-2025.php?action=cambiar_emergency_code', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          emergencyCodeReal = nuevo;
          msg.style.color = 'var(--green)';
          msg.textContent = '✅ Código actualizado correctamente';
          document.getElementById('nuevo-emergency').value = '';
          // Actualizar display si estaba visible
          if (emergencyVisible) {
            document.getElementById('emergency-display').textContent = nuevo;
          }
        } else {
          msg.style.color = 'var(--red)';
          msg.textContent = '❌ ' + (d.msg || 'Error al guardar');
        }
      } catch (e) {
        msg.style.color = 'var(--red)';
        msg.textContent = '❌ Error de conexión';
      }
    }

    // ── QUITAR ROL ──
    async function quitarRol(uid) {
      if (!confirm('¿Quitar el rol de este usuario?\n\nPerderá el acceso al panel de administración.')) return;
      const fd = new FormData();
      fd.append('usuario_id', uid);
      const r = await fetch('gestion-qbc-2025.php?action=quitar_rol', { method: 'POST', body: fd });
      const d = await r.json();
      if (d.ok) {
        mostrarToast('✅ Rol eliminado correctamente', 'green');
        cargarRoles();
      } else {
        mostrarToast('❌ ' + (d.msg || 'Error al quitar rol'), 'red');
      }
    }

    // ── CERRAR MODALES CON ESC ──
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        cerrarModal?.();
        cerrarPassUser?.();
        cerrarPermisos?.();
        cerrarPass?.();
      }
    });

    // ── INICIO ──
    // ── SISTEMA ──
    async function cargarSistema() {
      const label = document.getElementById('sistema-modo-label');
      const msg = document.getElementById('sistema-msg');
      const btnS = document.getElementById('btn-modo-solicitud');
      const btnD = document.getElementById('btn-modo-directo');
      if (!label) return;
      label.textContent = 'cargando...';
      label.style.color = 'var(--text3)';
      if (msg) msg.style.display = 'none';
      try {
        const r = await fetch('gestion-qbc-2025.php?action=get_modo_registro');
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch (e) { throw new Error('Sesión expirada — recarga'); }
        if (!d.ok) throw new Error(d.msg || 'Error');
        aplicarModoUI(d.modo);
      } catch (e) {
        label.textContent = 'error: ' + e.message;
        label.style.color = 'var(--red)';
      }
    }

    function aplicarModoUI(modo) {
      const label = document.getElementById('sistema-modo-label');
      const btnS = document.getElementById('btn-modo-solicitud');
      const btnD = document.getElementById('btn-modo-directo');
      if (!label) return;
      label.textContent = modo;
      label.style.color = modo === 'solicitud' ? 'var(--green)' : 'var(--amber)';
      if (btnS) {
        btnS.style.background = modo === 'solicitud' ? 'var(--green-bg)' : 'var(--bg3)';
        btnS.style.borderColor = modo === 'solicitud' ? 'rgba(0,230,118,.4)' : 'var(--border2)';
        btnS.style.color = modo === 'solicitud' ? 'var(--green)' : 'var(--text)';
      }
      if (btnD) {
        btnD.style.background = modo === 'directo' ? 'var(--amber-bg)' : 'var(--bg3)';
        btnD.style.borderColor = modo === 'directo' ? 'rgba(255,171,0,.4)' : 'var(--border2)';
        btnD.style.color = modo === 'directo' ? 'var(--amber)' : 'var(--text)';
      }
    }

    async function setModoRegistro(modo) {
      const msg = document.getElementById('sistema-msg');
      if (msg) { msg.style.display = 'none'; }
      const fd = new FormData();
      fd.append('modo', modo);
      try {
        const r = await fetch('gestion-qbc-2025.php?action=set_modo_registro', { method: 'POST', body: fd });
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch (e) { throw new Error('Sesión expirada — recarga'); }
        if (!d.ok) throw new Error(d.msg || 'Error al guardar');
        aplicarModoUI(d.modo);
        if (msg) {
          msg.style.display = 'inline';
          msg.style.color = 'var(--green)';
          msg.textContent = '✅ Guardado correctamente';
          setTimeout(() => { msg.style.display = 'none'; }, 2500);
        }
        mostrarToast('✅ Modo de registro: ' + d.modo, 'green');
      } catch (e) {
        if (msg) {
          msg.style.display = 'inline';
          msg.style.color = 'var(--red)';
          msg.textContent = '❌ Error al guardar';
        }
        mostrarToast('❌ ' + e.message, 'red');
      }
    }

    // ── INICIO ──
    cargarDashboard();
    actualizarBadgeSolicitudes();

    // ── HAMBURGUESA / DRAWER ──
    function toggleSidebar() {
      const sb = document.getElementById('sidebarEl');
      const ov = document.getElementById('sbOverlay');
      const btn = document.getElementById('btnHamburger');
      const open = sb.classList.toggle('open');
      ov.classList.toggle('open', open);
      btn.classList.toggle('open', open);
      document.body.style.overflow = open ? 'hidden' : '';
    }
    function cerrarSidebar() {
      const sb = document.getElementById('sidebarEl');
      const ov = document.getElementById('sbOverlay');
      const btn = document.getElementById('btnHamburger');
      sb.classList.remove('open');
      ov.classList.remove('open');
      btn.classList.remove('open');
      document.body.style.overflow = '';
    }
    // Cerrar con Escape
    document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarSidebar(); });

    // Mostrar/ocultar hamburguesa según ancho
    function checkHamburger() {
      const btn = document.getElementById('btnHamburger');
      if (!btn) return;
      btn.style.display = window.innerWidth <= 640 ? 'flex' : 'none';
      if (window.innerWidth > 640) cerrarSidebar();
    }
    window.addEventListener('resize', checkHamburger);
    checkHamburger();

    // Cerrar drawer al hacer clic en un item del sidebar (solo móvil)
    document.querySelectorAll('.sb-item, .sb-logout').forEach(el => {
      el.addEventListener('click', () => {
        if (window.innerWidth <= 640) cerrarSidebar();
      });
    });

    // ══════════════════════════════════════════════════════
    // MODAL EDITAR TALENTO — solo superadmin y admin delegado
    // ══════════════════════════════════════════════════════
    async function abrirModalTalento(uid, nombreUsuario) {
      // Crear modal si no existe
      let modal = document.getElementById('modal-talento');
      if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modal-talento';
        modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;padding:20px;overflow-y:auto';
        modal.innerHTML = `
                <div style="background:var(--bg2);border:1px solid var(--border2);border-radius:20px;padding:32px;width:100%;max-width:600px;position:relative;margin:auto;max-height:90vh;overflow-y:auto">
                  <button onclick="cerrarModalTalento()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer">✕</button>
                  <h3 style="font-size:18px;font-weight:700;margin-bottom:4px">🌟 Editar perfil de talento</h3>
                  <p id="talento-nombre-label" style="color:var(--text3);font-size:13px;font-family:'JetBrains Mono',monospace;margin-bottom:20px"></p>
                  <input type="hidden" id="talento-uid">

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                    <div>
                      <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Profesión / Título</label>
                      <input type="text" id="talento-profesion" placeholder="ej: Músico, Desarrollador..."
                        style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                    </div>
                    <div>
                      <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Ciudad</label>
                      <input type="text" id="talento-ciudad" placeholder="Quibdó, Chocó..."
                        style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                    </div>
                  </div>

                  <div style="margin-bottom:12px">
                    <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Biografía / Descripción</label>
                    <textarea id="talento-bio" rows="3" placeholder="Descripción del talento..."
                      style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none;resize:vertical"></textarea>
                  </div>

                  <div style="margin-bottom:12px">
                    <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Habilidades / Skills <span style="color:var(--text3);font-weight:400">(separadas por coma)</span></label>
                    <input type="text" id="talento-skills" placeholder="PHP, Diseño, Música, Fotografía..."
                      style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                  </div>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                    <div>
                      <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Géneros / Especialidad</label>
                      <input type="text" id="talento-generos" placeholder="Salsa, Chirimía, Vallenato..."
                        style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                    </div>
                    <div>
                      <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Precio desde (COP)</label>
                      <input type="number" id="talento-precio" placeholder="50000"
                        style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'JetBrains Mono',monospace;outline:none">
                    </div>
                  </div>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                    <div>
                      <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Tipo de servicio</label>
                      <select id="talento-tipo-servicio"
                        style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                        <option value="">Sin especificar</option>
                        <option value="presencial">Presencial</option>
                        <option value="remoto">Remoto</option>
                        <option value="ambos">Presencial y remoto</option>
                      </select>
                    </div>
                    <div>
                      <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Color de avatar (hex)</label>
                      <div style="display:flex;gap:8px;align-items:center">
                        <input type="color" id="talento-color-picker" value="#10b981"
                          style="width:40px;height:38px;border:none;background:none;cursor:pointer;border-radius:6px"
                          oninput="document.getElementById('talento-avatar-color').value=this.value">
                        <input type="text" id="talento-avatar-color" placeholder="#10b981"
                          style="flex:1;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'JetBrains Mono',monospace;outline:none"
                          oninput="document.getElementById('talento-color-picker').value=this.value||'#10b981'">
                      </div>
                    </div>
                  </div>

                  <!-- Toggles visibilidad y destacado -->
                  <div style="display:flex;gap:10px;margin-bottom:20px">
                    <div style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none" onclick="toggleSwitch('sw-talento-visible')">
                      <div>
                        <div style="font-size:12px;font-weight:700;color:var(--text)">👁 Visible en talentos.php</div>
                        <div style="font-size:11px;color:var(--text2);margin-top:2px">Aparece en la sección pública</div>
                      </div>
                      <div id="sw-talento-visible" data-on="1" style="width:44px;height:24px;border-radius:24px;background:#1f9d55;position:relative;transition:background .25s;flex-shrink:0">
                        <div id="sw-talento-visible-dot" style="position:absolute;width:18px;height:18px;border-radius:50%;background:white;top:3px;left:3px;transform:translateX(20px);transition:transform .25s;pointer-events:none"></div>
                      </div>
                    </div>
                    <div style="flex:1;background:var(--bg3);border:1px solid var(--border);border-radius:10px;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none" onclick="toggleSwitch('sw-talento-destacado')">
                      <div>
                        <div style="font-size:12px;font-weight:700;color:var(--text)">⭐ Destacado en el inicio</div>
                        <div style="font-size:11px;color:var(--text2);margin-top:2px">Aparece en "Conoce nuestros talentos" (index)</div>
                      </div>
                      <div id="sw-talento-destacado" data-on="0" style="width:44px;height:24px;border-radius:24px;background:rgba(255,255,255,0.15);position:relative;transition:background .25s;flex-shrink:0">
                        <div id="sw-talento-destacado-dot" style="position:absolute;width:18px;height:18px;border-radius:50%;background:white;top:3px;left:3px;transition:transform .25s;pointer-events:none"></div>
                      </div>
                    </div>
                  </div>

                  <div style="display:flex;gap:10px">
                    <button onclick="guardarTalento()"
                      style="flex:1;padding:12px;background:linear-gradient(135deg,#1f9d55,var(--green));border:none;border-radius:10px;color:#000;font-size:14px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">💾 Guardar perfil de talento</button>
                    <button onclick="cerrarModalTalento()"
                      style="padding:12px 20px;background:transparent;border:1px solid var(--border);border-radius:10px;color:var(--text2);font-size:14px;cursor:pointer;font-family:'Space Grotesk',sans-serif">Cancelar</button>
                  </div>
                  <p id="talento-msg" style="font-size:12px;margin-top:10px;text-align:center;display:none"></p>
                </div>`;
        document.body.appendChild(modal);

        // Cerrar al click fuera
        modal.addEventListener('click', e => { if (e.target === modal) cerrarModalTalento(); });
      }

      // Cargar datos del talento
      document.getElementById('talento-uid').value = uid;
      document.getElementById('talento-nombre-label').textContent = `${nombreUsuario} · #${uid}`;
      document.getElementById('talento-msg').style.display = 'none';

      // Limpiar campos mientras carga
      ['talento-profesion', 'talento-ciudad', 'talento-bio', 'talento-skills',
        'talento-generos', 'talento-precio', 'talento-tipo-servicio', 'talento-avatar-color'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.value = '';
        });

      modal.style.display = 'flex';

      try {
        const r = await fetch(`gestion-qbc-2025.php?action=get_talento&id=${uid}`);
        const d = await r.json();
        if (d.ok && d.talento) {
          const t = d.talento;
          document.getElementById('talento-profesion').value = t.profesion || '';
          document.getElementById('talento-ciudad').value = t.ciudad || '';
          document.getElementById('talento-bio').value = t.bio || '';
          document.getElementById('talento-skills').value = t.skills || '';
          document.getElementById('talento-generos').value = t.generos || '';
          document.getElementById('talento-precio').value = t.precio_desde || '';
          document.getElementById('talento-tipo-servicio').value = t.tipo_servicio || '';
          const color = t.avatar_color || '#10b981';
          document.getElementById('talento-avatar-color').value = color;
          document.getElementById('talento-color-picker').value = color;
          setSwitch('sw-talento-visible', parseInt(t.visible_admin ?? 1));
          setSwitch('sw-talento-destacado', parseInt(t.destacado || 0));
        } else {
          // Sin perfil aún — valores por defecto
          setSwitch('sw-talento-visible', 1);
          setSwitch('sw-talento-destacado', 0);
        }
      } catch (e) {
        document.getElementById('talento-msg').style.display = 'block';
        document.getElementById('talento-msg').style.color = 'var(--red)';
        document.getElementById('talento-msg').textContent = '⚠️ Error al cargar datos: ' + e.message;
      }
    }

    function cerrarModalTalento() {
      const modal = document.getElementById('modal-talento');
      if (modal) modal.style.display = 'none';
    }

    async function guardarTalento() {
      const uid = document.getElementById('talento-uid').value;
      const msg = document.getElementById('talento-msg');
      msg.style.display = 'none';

      const fd = new FormData();
      fd.append('id', uid);
      fd.append('profesion', document.getElementById('talento-profesion').value.trim());
      fd.append('bio', document.getElementById('talento-bio').value.trim());
      fd.append('skills', document.getElementById('talento-skills').value.trim());
      fd.append('ciudad', document.getElementById('talento-ciudad').value.trim());
      fd.append('visible_admin', document.getElementById('sw-talento-visible').dataset.on === '1' ? '1' : '0');
      fd.append('destacado', document.getElementById('sw-talento-destacado').dataset.on === '1' ? '1' : '0');
      fd.append('avatar_color', document.getElementById('talento-avatar-color').value.trim());
      fd.append('generos', document.getElementById('talento-generos').value.trim());
      fd.append('precio_desde', document.getElementById('talento-precio').value.trim());
      fd.append('tipo_servicio', document.getElementById('talento-tipo-servicio').value.trim());

      try {
        const r = await fetch('gestion-qbc-2025.php?action=editar_talento', { method: 'POST', body: fd });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const text = await r.text();
        let d;
        try { d = JSON.parse(text); } catch (e) { throw new Error('Sesión expirada — recarga la página'); }

        msg.style.display = 'block';
        if (d.ok) {
          msg.style.color = 'var(--green)';
          msg.textContent = '✅ Perfil de talento guardado correctamente';
          setTimeout(() => { cerrarModalTalento(); cargarUsuarios(); }, 1200);
        } else {
          msg.style.color = 'var(--red)';
          msg.textContent = '❌ ' + (d.msg || 'Error al guardar');
        }
      } catch (e) {
        msg.style.display = 'block';
        msg.style.color = 'var(--red)';
        msg.textContent = '❌ ' + e.message;
      }
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS COMPARTIDOS — tablas de directorios
    // ══════════════════════════════════════════════════════════════════
    function avatarHtml(foto, nombre, color) {
      if (foto) return `<img src="${foto}" style="width:36px;height:36px;border-radius:50%;object-fit:cover">`;
      const letra = (nombre || '?')[0].toUpperCase();
      const bg = color || '#1e3a5f';
      return `<div style="width:36px;height:36px;border-radius:50%;background:${bg};display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#fff;flex-shrink:0">${letra}</div>`;
    }

    function toggleChip(val, label1, label0) {
      const on = parseInt(val) === 1;
      return `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:${on ? 'rgba(16,185,129,.18)' : 'rgba(255,68,68,.15)'};color:${on ? 'var(--green)' : 'var(--red)'}">
              ${on ? '✅ ' + (label1 || 'Visible') : '🚫 ' + (label0 || 'Oculto')}
            </span>`;
    }

    function btnToggle(uid, tabla, campo, valorActual, label) {
      const nuevoValor = parseInt(valorActual) === 1 ? 0 : 1;
      return `<button onclick="toggleDirCampo(${uid},'${tabla}','${campo}',${nuevoValor},this)"
              style="padding:4px 10px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:var(--text2);font-size:11px;font-family:'Space Grotesk',sans-serif;cursor:pointer;white-space:nowrap"
              title="Cambiar ${label}">${parseInt(valorActual) ? '🔒 Ocultar' : '👁 Mostrar'}</button>`;
    }

    function btnDestacado(uid, tabla, valorActual) {
      const nuevoValor = parseInt(valorActual) === 1 ? 0 : 1;
      return `<button onclick="toggleDirCampo(${uid},'${tabla}','destacado',${nuevoValor},this)"
              style="padding:4px 10px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:${parseInt(valorActual) ? 'var(--amber)' : 'var(--text2)'};font-size:11px;font-family:'Space Grotesk',sans-serif;cursor:pointer"
              title="${parseInt(valorActual) ? 'Quitar del inicio (index)' : 'Mostrar en inicio (index)'}">${parseInt(valorActual) ? '⭐ Quitar index' : '☆ Poner en index'}</button>`;
    }

    async function toggleDirCampo(uid, tabla, campo, valor, btn) {
      btn.disabled = true;
      btn.textContent = '…';
      try {
        const fd = new FormData();
        fd.append('id', uid); fd.append('tabla', tabla);
        fd.append('campo', campo); fd.append('valor', valor);
        const r = await fetch('gestion-qbc-2025.php?action=toggle_dir_campo', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          mostrarToast('✅ Actualizado', 'green');
          recargarSeccion();
        } else {
          mostrarToast('❌ ' + (d.msg || 'Error'), 'red');
          btn.disabled = false; btn.textContent = '?';
        }
      } catch (e) {
        mostrarToast('❌ Error de red', 'red');
        btn.disabled = false; btn.textContent = '?';
      }
    }

    function paginacionHtml(total, page, limit, fnName) {
      const pages = Math.ceil(total / limit);
      if (pages <= 1) return '';
      let html = '';
      const btnStyle = 'padding:6px 14px;border-radius:8px;border:1px solid var(--border2);background:var(--bg2);color:var(--text);font-size:13px;cursor:pointer;font-family:\'Space Grotesk\',sans-serif';
      const btnActiveStyle = btnStyle + ';background:var(--green2);color:#000;font-weight:700;border-color:var(--green)';
      for (let i = 1; i <= pages; i++) {
        html += `<button onclick="${fnName}(${i})" style="${i === page ? btnActiveStyle : btnStyle}">${i}</button>`;
      }
      return `<div style="font-size:12px;color:var(--text3);text-align:center;width:100%;margin-bottom:4px">${total} registros · pág. ${page}/${pages}</div>` + html;
    }

    function tableWrap(inner) {
      return `<div style="overflow-x:auto;border-radius:12px;border:1px solid var(--border)">
              <table style="width:100%;border-collapse:collapse;font-size:13px;font-family:'Space Grotesk',sans-serif">
                ${inner}
              </table></div>`;
    }

    function th(label) {
      return `<th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);background:var(--bg2);border-bottom:1px solid var(--border)">${label}</th>`;
    }

    function td(content, extra) {
      return `<td style="padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle;${extra || ''}">${content}</td>`;
    }

    // ══════════════════════════════════════════════════════════════════
    // CANDIDATOS
    // ══════════════════════════════════════════════════════════════════
    let candPage = 1;
    async function cargarCandidatos(page) {
      if (page) candPage = page;
      const q = document.getElementById('cand-buscar')?.value || '';
      const vis = document.getElementById('cand-visible')?.value ?? '-1';
      const loading = document.getElementById('cand-loading');
      const tableEl = document.getElementById('cand-table');
      const pagEl = document.getElementById('cand-pagination');
      loading.style.display = 'block'; tableEl.innerHTML = ''; pagEl.innerHTML = '';
      try {
        const r = await fetch(`gestion-qbc-2025.php?action=candidatos&page=${candPage}&q=${encodeURIComponent(q)}&visible=${vis}`);
        const d = await r.json();
        loading.style.display = 'none';
        if (!d.ok) { tableEl.innerHTML = `<p style="color:var(--red);padding:16px">${d.msg}</p>`; return; }
        if (!d.candidatos.length) { tableEl.innerHTML = '<p style="color:var(--text3);padding:20px;text-align:center">Sin resultados</p>'; return; }

        let rows = '';
        for (const c of d.candidatos) {
          const nombre = (c.nombre || '') + ' ' + (c.apellido || '');
          rows += `<tr>
                  ${td(`<div style="display:flex;align-items:center;gap:10px">${avatarHtml(c.foto, nombre, c.avatar_color)}<span style="font-weight:600">${nombre}</span></div>`)}
                  ${td(c.ciudad || '—')}
                  ${td(c.profesion || '—')}
                  ${td(c.skills ? `<span style="font-size:11px;color:var(--text2)">${c.skills.substring(0, 60)}${c.skills.length > 60 ? '…' : ''}</span>` : '—')}
                  ${td(toggleChip(c.visible_admin ?? 1))}
                  ${td(toggleChip(c.destacado ?? 0, 'Destacado', 'Normal'))}
                  ${td(`<div style="display:flex;gap:6px;flex-wrap:wrap">
                ${btnToggle(c.id, 'talento_perfil', 'visible_admin', c.visible_admin ?? 1, 'visibilidad')}
                ${btnDestacado(c.id, 'talento_perfil', c.destacado ?? 0)}
                <button onclick="abrirModalTalento(${c.id},'${(nombre).replace(/'/g, "\\'")}',false)"
                  style="padding:4px 10px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:var(--text);font-size:11px;cursor:pointer">✏️ Editar</button>
              </div>`)}
            </tr>`;
          }
          tableEl.innerHTML = tableWrap(`<thead><tr>${th('Candidato')}${th('Ciudad')}${th('Profesión')}${th('Skills')}${th('Visible')}${th('En index')}${th('Acciones')}</tr></thead><tbody>${rows}</tbody>`);
          pagEl.innerHTML = paginacionHtml(d.total, candPage, 20, 'cargarCandidatos');
        } catch (e) {
          loading.style.display = 'none';
          tableEl.innerHTML = `<p style="color:var(--red);padding:16px">❌ ${e.message}</p>`;
        }
      }

      // ══════════════════════════════════════════════════════════════════
      // EMPRESAS DIRECTORIO
      // ══════════════════════════════════════════════════════════════════
      let empdirPage = 1;
      async function cargarEmpresasDir(page) {
        if (page) empdirPage = page;
        const q = document.getElementById('empdir-buscar')?.value || '';
        const vis = document.getElementById('empdir-visible')?.value ?? '-1';
        const loading = document.getElementById('empdir-loading');
        const tableEl = document.getElementById('empdir-table');
        const pagEl = document.getElementById('empdir-pagination');
        loading.style.display = 'block'; tableEl.innerHTML = ''; pagEl.innerHTML = '';
        try {
          const r = await fetch(`gestion-qbc-2025.php?action=empresas_dir&page=${empdirPage}&q=${encodeURIComponent(q)}&visible=${vis}`);
          const d = await r.json();
          loading.style.display = 'none';
          if (!d.ok) { tableEl.innerHTML = `<p style="color:var(--red);padding:16px">${d.msg}</p>`; return; }
          if (!d.empresas.length) { tableEl.innerHTML = '<p style="color:var(--text3);padding:20px;text-align:center">Sin resultados</p>'; return; }

          let rows = '';
          for (const e of d.empresas) {
            const nombre = e.nombre_empresa || e.nombre || '—';
            rows += `<tr>
              ${td(`<div style="display:flex;align-items:center;gap:10px">${avatarHtml(e.logo, nombre, e.avatar_color)}<span style="font-weight:600">${nombre}</span></div>`)}
              ${td(e.sector || '—')}
              ${td(e.nit || '—')}
              ${td(e.ciudad || '—')}
              ${td(toggleChip(e.visible_admin ?? 1))}
              ${td(toggleChip(e.destacado ?? 0, 'Destacado', 'Normal'))}
              ${td(`<div style="display:flex;gap:6px;flex-wrap:wrap">
                ${btnToggle(e.id, 'perfiles_empresa', 'visible_admin', e.visible_admin ?? 1, 'visibilidad')}
                ${btnDestacado(e.id, 'perfiles_empresa', e.destacado ?? 0)}
                <button onclick="abrirModalEmpresa(${e.id},'${nombre.replace(/'/g, "\\'")}') "
                      style="padding:4px 10px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:var(--text);font-size:11px;cursor:pointer">✏️ Editar</button>
                  </div>`)}
                </tr>`;
          }
          tableEl.innerHTML = tableWrap(`<thead><tr>${th('Empresa')}${th('Sector')}${th('NIT')}${th('Ciudad')}${th('Visible')}${th('Destacado')}${th('Acciones')}</tr></thead><tbody>${rows}</tbody>`);
          pagEl.innerHTML = paginacionHtml(d.total, empdirPage, 20, 'cargarEmpresasDir');
        } catch (e) {
          loading.style.display = 'none';
          tableEl.innerHTML = `<p style="color:var(--red);padding:16px">❌ ${e.message}</p>`;
        }
      }

      // ══════════════════════════════════════════════════════════════════
      // SERVICIOS DIRECTORIO
      // ══════════════════════════════════════════════════════════════════
      let srvdirPage = 1;
      async function cargarServiciosDir(page) {
        if (page) srvdirPage = page;
        const q = document.getElementById('srvdir-buscar')?.value || '';
        const vis = document.getElementById('srvdir-visible')?.value ?? '-1';
        const loading = document.getElementById('srvdir-loading');
        const tableEl = document.getElementById('srvdir-table');
        const pagEl = document.getElementById('srvdir-pagination');
        loading.style.display = 'block'; tableEl.innerHTML = ''; pagEl.innerHTML = '';
        try {
          const r = await fetch(`gestion-qbc-2025.php?action=servicios_dir&page=${srvdirPage}&q=${encodeURIComponent(q)}&visible=${vis}`);
          const d = await r.json();
          loading.style.display = 'none';
          if (!d.ok) { tableEl.innerHTML = `<p style="color:var(--red);padding:16px">${d.msg}</p>`; return; }
          if (!d.servicios.length) { tableEl.innerHTML = '<p style="color:var(--text3);padding:20px;text-align:center">Sin resultados</p>'; return; }

          let rows = '';
          for (const s of d.servicios) {
            const nombre = (s.nombre || '') + ' ' + (s.apellido || '');
            rows += `<tr>
                  ${td(`<div style="display:flex;align-items:center;gap:10px">${avatarHtml(s.foto, nombre, s.avatar_color)}<span style="font-weight:600">${nombre}</span></div>`)}
                  ${td(s.tipo_servicio || '—')}
                  ${td(s.generos || '—')}
                  ${td(s.precio_desde ? `<span style="color:var(--green);font-weight:700">$${s.precio_desde}</span>` : '—')}
                  ${td(toggleChip(s.visible_admin ?? 1))}
                  ${td(toggleChip(s.destacado ?? 0, 'Destacado', 'Normal'))}
                  ${td(`<div style="display:flex;gap:6px;flex-wrap:wrap">
                    ${btnToggle(s.id, 'talento_perfil', 'visible_admin', s.visible_admin ?? 1, 'visibilidad')}
                    ${btnDestacado(s.id, 'talento_perfil', s.destacado ?? 0)}
                    <button onclick="abrirModalTalento(${s.id},'${nombre.replace(/'/g, "\\'")}',false)"
                  style="padding:4px 10px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:var(--text);font-size:11px;cursor:pointer">✏️ Editar</button>
              </div>`)}
                </tr>`;
        }
        tableEl.innerHTML = tableWrap(`<thead><tr>${th('Prestador')}${th('Tipo servicio')}${th('Géneros')}${th('Precio desde')}${th('Visible')}${th('Destacado')}${th('Acciones')}</tr></thead><tbody>${rows}</tbody>`);
        pagEl.innerHTML = paginacionHtml(d.total, srvdirPage, 20, 'cargarServiciosDir');
      } catch (e) {
        loading.style.display = 'none';
        tableEl.innerHTML = `<p style="color:var(--red);padding:16px">❌ ${e.message}</p>`;
      }
    }

    // ══════════════════════════════════════════════════════════════════
    // NEGOCIOS DIRECTORIO
    // ══════════════════════════════════════════════════════════════════
    let negdirPage = 1;
    async function cargarNegociosDir(page) {
      if (page) negdirPage = page;
      const q = document.getElementById('negdir-buscar')?.value || '';
      const tipo = document.getElementById('negdir-tipo')?.value || '';
      const vis = document.getElementById('negdir-visible')?.value ?? '-1';
      const loading = document.getElementById('negdir-loading');
      const tableEl = document.getElementById('negdir-table');
      const pagEl = document.getElementById('negdir-pagination');
      loading.style.display = 'block'; tableEl.innerHTML = ''; pagEl.innerHTML = '';
      try {
        const r = await fetch(`gestion-qbc-2025.php?action=negocios_dir&page=${negdirPage}&q=${encodeURIComponent(q)}&tipo=${tipo}&visible=${vis}`);
        const d = await r.json();
        loading.style.display = 'none';
        if (!d.ok) { tableEl.innerHTML = `<p style="color:var(--red);padding:16px">${d.msg}</p>`; return; }
        if (!d.negocios.length) { tableEl.innerHTML = '<p style="color:var(--text3);padding:20px;text-align:center">Sin resultados</p>'; return; }

        let rows = '';
        for (const n of d.negocios) {
          const nombre = n.nombre_negocio || n.nombre || '—';
          const tipoBadge = n.tipo_negocio === 'cc'
            ? `<span style="padding:2px 8px;border-radius:10px;background:rgba(59,130,246,.18);color:#60a5fa;font-size:10px;font-weight:700">CC</span>`
            : `<span style="padding:2px 8px;border-radius:10px;background:rgba(16,185,129,.18);color:var(--green);font-size:10px;font-weight:700">EMP</span>`;
          rows += `<tr>
                  ${td(`<div style="display:flex;align-items:center;gap:10px">${avatarHtml(n.logo, nombre, n.avatar_color)}<span style="font-weight:600">${nombre}</span></div>`)}
                  ${td(n.categoria || '—')}
                  ${td(n.whatsapp ? `<a href="https://wa.me/${n.whatsapp.replace(/\D/g, '')}" target="_blank" style="color:var(--green);text-decoration:none">📱 ${n.whatsapp}</a>` : '—')}
                  ${td(tipoBadge)}
                  ${td(toggleChip(n.visible_admin ?? 1))}
                  ${td(toggleChip(n.destacado ?? 0, 'Destacado', 'Normal'))}
                  ${td(`<div style="display:flex;gap:6px;flex-wrap:wrap">
                ${btnToggle(n.id, 'negocios_locales', 'visible_admin', n.visible_admin ?? 1, 'visibilidad')}
                ${btnDestacado(n.id, 'negocios_locales', n.destacado ?? 0)}
                <button onclick="abrirModalNegocio(${n.id},'${nombre.replace(/'/g, "\\'")}') "
                  style="padding:4px 10px;border-radius:6px;border:1px solid var(--border2);background:var(--bg3);color:var(--text);font-size:11px;cursor:pointer">✏️ Editar</button>
              </div>`)}
            </tr>`;
          }
          tableEl.innerHTML = tableWrap(`<thead><tr>${th('Negocio')}${th('Categoría')}${th('WhatsApp')}${th('Tipo')}${th('Visible')}${th('Destacado')}${th('Acciones')}</tr></thead><tbody>${rows}</tbody>`);
          pagEl.innerHTML = paginacionHtml(d.total, negdirPage, 20, 'cargarNegociosDir');
        } catch (e) {
          loading.style.display = 'none';
          tableEl.innerHTML = `<p style="color:var(--red);padding:16px">❌ ${e.message}</p>`;
        }
      }

      // ══════════════════════════════════════════════════════════════════
      // MODAL EDITAR NEGOCIO
      // ══════════════════════════════════════════════════════════════════
      async function abrirModalNegocio(uid, nombreUsuario) {
        let modal = document.getElementById('modal-negocio');
        if (!modal) {
          modal = document.createElement('div');
          modal.id = 'modal-negocio';
          modal.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;padding:20px;overflow-y:auto';
          modal.innerHTML = `
            <div style="background:var(--bg2);border:1px solid var(--border2);border-radius:20px;padding:32px;width:100%;max-width:600px;position:relative;margin:auto;max-height:90vh;overflow-y:auto">
              <button onclick="cerrarModalNegocio()" style="position:absolute;top:16px;right:16px;background:none;border:none;color:var(--text2);font-size:20px;cursor:pointer">✕</button>
              <h3 style="font-size:18px;font-weight:700;margin-bottom:4px">🏪 Editar negocio</h3>
              <p id="negocio-nombre-label" style="color:var(--text3);font-size:13px;font-family:'JetBrains Mono',monospace;margin-bottom:20px"></p>
              <input type="hidden" id="negocio-uid">

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Nombre del negocio</label>
                  <input type="text" id="negocio-nombre" placeholder="ej: Tienda El Chocoano"
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none;box-sizing:border-box">
                </div>
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Categoría</label>
                  <select id="negocio-categoria"
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                    <option value="">Sin especificar</option>
                    <option value="Gastronomía">🍽️ Gastronomía</option>
                    <option value="Ropa & Moda">👗 Ropa & Moda</option>
                    <option value="Belleza & Estética">✂️ Belleza & Estética</option>
                    <option value="Salud & Farmacia">💊 Salud & Farmacia</option>
                    <option value="Ferretería & Construcción">🔧 Ferretería</option>
                    <option value="Tecnología & Celulares">💻 Tecnología</option>
                    <option value="Joyería & Accesorios">💍 Joyería</option>
                    <option value="Librería & Papelería">📚 Librería</option>
                    <option value="Artesanía & Arte">🎨 Artesanía</option>
                    <option value="Ecoturismo & Turismo">🌿 Turismo</option>
                    <option value="Música & Sonido">🎵 Música</option>
                    <option value="Otro">🏪 Otro</option>
                  </select>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">WhatsApp</label>
                  <input type="text" id="negocio-whatsapp" placeholder="ej: 3001234567"
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none;box-sizing:border-box">
                </div>
                <div>
                  <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Tipo de negocio</label>
                  <select id="negocio-tipo"
                    style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none">
                    <option value="emp">🌱 Emprendedor independiente</option>
                    <option value="cc">🏬 Local en Centro Comercial</option>
                  </select>
                </div>
              </div>

              <div style="margin-bottom:12px">
                <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Descripción</label>
                <textarea id="negocio-descripcion" rows="3" placeholder="¿Qué vende o hace este negocio?"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none;resize:vertical;box-sizing:border-box"></textarea>
              </div>

              <div style="margin-bottom:16px">
                <label style="font-size:11px;color:var(--text2);font-weight:700;text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:4px">Dirección / Ubicación</label>
                <input type="text" id="negocio-ubicacion" placeholder="ej: Calle 26 #4-15, Quibdó"
                  style="width:100%;padding:10px 12px;background:var(--bg3);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;font-family:'Space Grotesk',sans-serif;outline:none;box-sizing:border-box">
              </div>

              <div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text2)">
                  <input type="checkbox" id="negocio-visible" style="width:16px;height:16px;accent-color:var(--green)">
                  <span>👁 Visible en directorio</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text2)">
                  <input type="checkbox" id="negocio-destacado" style="width:16px;height:16px;accent-color:#f59e0b">
                  <span>⭐ Destacado</span>
                </label>
              </div>

              <button onclick="guardarNegocio()"
                style="width:100%;padding:13px;background:linear-gradient(135deg,var(--green2),var(--green));border:none;border-radius:10px;color:#000;font-size:14px;font-weight:700;cursor:pointer;font-family:'Space Grotesk',sans-serif">
                💾 Guardar cambios
              </button>
              <p id="negocio-msg" style="font-size:12px;margin-top:10px;text-align:center;display:none"></p>
            </div>`;
          document.body.appendChild(modal);
        }

        document.getElementById('negocio-nombre-label').textContent = 'Cargando…';
        modal.style.display = 'flex';

        try {
          const r = await fetch(`gestion-qbc-2025.php?action=get_negocio&id=${uid}`);
          const d = await r.json();
          if (!d.ok) { mostrarToast('❌ ' + d.msg, 'red'); cerrarModalNegocio(); return; }
          const n = d.negocio;
          document.getElementById('negocio-uid').value = uid;
          document.getElementById('negocio-nombre-label').textContent = nombreUsuario + ' #' + uid;
          document.getElementById('negocio-nombre').value = n.nombre_negocio || '';
          document.getElementById('negocio-whatsapp').value = n.whatsapp || '';
          document.getElementById('negocio-descripcion').value = n.descripcion || '';
          document.getElementById('negocio-ubicacion').value = n.ubicacion || '';
          document.getElementById('negocio-tipo').value = n.tipo_negocio || 'emp';
          document.getElementById('negocio-visible').checked = parseInt(n.visible_admin) !== 0;
          document.getElementById('negocio-destacado').checked = parseInt(n.destacado) === 1;
          // Seleccionar categoría
          const catSel = document.getElementById('negocio-categoria');
          for (let opt of catSel.options) {
            if (opt.value === n.categoria) { opt.selected = true; break; }
          }
        } catch (e) {
          mostrarToast('❌ Error cargando negocio', 'red');
          cerrarModalNegocio();
        }
      }

      function cerrarModalNegocio() {
        const m = document.getElementById('modal-negocio');
        if (m) m.style.display = 'none';
      }

      async function guardarNegocio() {
        const uid = document.getElementById('negocio-uid').value;
        const msg = document.getElementById('negocio-msg');
        const fd = new FormData();
        fd.append('id', uid);
        fd.append('nombre_negocio', document.getElementById('negocio-nombre').value);
        fd.append('categoria', document.getElementById('negocio-categoria').value);
        fd.append('whatsapp', document.getElementById('negocio-whatsapp').value);
        fd.append('descripcion', document.getElementById('negocio-descripcion').value);
        fd.append('ubicacion', document.getElementById('negocio-ubicacion').value);
        fd.append('tipo_negocio', document.getElementById('negocio-tipo').value);
        fd.append('visible_admin', document.getElementById('negocio-visible').checked ? 1 : 0);
        fd.append('destacado', document.getElementById('negocio-destacado').checked ? 1 : 0);
        msg.style.display = 'none';
        try {
          const r = await fetch('gestion-qbc-2025.php?action=editar_negocio', { method: 'POST', body: fd });
          const d = await r.json();
          if (d.ok) {
            mostrarToast('✅ Negocio guardado', 'green');
            cerrarModalNegocio();
            if (seccionActual === 'negocios_dir') cargarNegociosDir(negdirPage);
          } else {
            msg.textContent = '❌ ' + (d.msg || 'Error al guardar');
            msg.style.color = 'var(--red)'; msg.style.display = 'block';
          }
        } catch (e) {
          msg.textContent = '❌ ' + e.message;
          msg.style.color = 'var(--red)'; msg.style.display = 'block';
        }
      }

    </script>

    <!-- Simulador JS -->
    <?php if ($perms['simulador']): ?>
      <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
      <style>
        @media(max-width:900px) {
          .sim-two-cols-admin {
            grid-template-columns: 1fr !important;
          }
        }
      </style>
      <script>
        let admSimEtapa = 1;
        let admSimChart = null;
        let admSimInited = false;

        const admSimEtapas = {
          1: { selva: 12900, oro: 29900, azul: 49900, micro: 19900, info: 'Etapa 1 (meses 1-6): Lanzamiento. Verde Selva $12.900 · Amarillo Oro $29.900 · Azul Profundo $49.900 · Microempresa $19.900/mes.' },
          2: { selva: 12900, oro: 29900, azul: 49900, micro: 19900, info: 'Etapa 2 (meses 7-9): Crecimiento. Mismos precios base con mayor base de usuarios establecida en el Chocó.' },
          3: { selva: 12900, oro: 29900, azul: 49900, micro: 19900, info: 'Etapa 3 (meses 10-12): Consolidación. Plataforma validada con reputación en el Chocó.' },
        };

        function admFmt(n) {
          return '$' + Math.round(n).toLocaleString('es-CO');
        }

        function simSetEtapa(n, btn) {
          admSimEtapa = n;
          [1, 2, 3].forEach(i => {
            const b = document.getElementById('sim-e' + i);
            if (!b) return;
            const colors = { 1: '#1D9E75', 2: '#BA7517', 3: '#1a3f6f' };
            if (i === n) {
              b.style.background = colors[i];
              b.style.borderColor = colors[i];
              b.style.color = 'white';
            } else {
              b.style.background = 'transparent';
              b.style.borderColor = 'rgba(255,255,255,.15)';
              b.style.color = 'rgba(255,255,255,.5)';
            }
          });
          admSimCalc();
        }

        function admCalcMes(i, emp, pS, pO, pA, pM, serv, valS, dest, ali) {
          const factor = 1 + (i * 0.05);
          const e = i < 6 ? admSimEtapas[1] : i < 9 ? admSimEtapas[2] : admSimEtapas[3];
          return Math.round(
            Math.round(emp * factor * pS) * e.selva +
            Math.round(emp * factor * pO) * e.oro +
            Math.round(emp * factor * pA) * e.azul +
            Math.round(emp * factor * pM) * e.micro +
            serv * valS * 0.075 * factor +
            dest * 30000 * factor +
            ali * 500000
          );
        }

        function admSimCalc() {
          const e = admSimEtapas[admSimEtapa];
          const visitas = +document.getElementById('adm-ss-visitas').value;
          const empresas = +document.getElementById('adm-ss-empresas').value;
          const semilla = +document.getElementById('adm-ss-semilla').value;
          const pS = +document.getElementById('adm-ss-pctselva').value / 100;
          const pO = +document.getElementById('adm-ss-pctoro').value / 100;
          const pA = +document.getElementById('adm-ss-pctazul').value / 100;
          const pM = +document.getElementById('adm-ss-pctmicro').value / 100;
          const serv = +document.getElementById('adm-ss-comision').value;
          const valS = +document.getElementById('adm-ss-valorserv').value;
          const dest = +document.getElementById('adm-ss-destacados').value;
          const ali = +document.getElementById('adm-ss-alianzas').value;

          document.getElementById('adm-sv-visitas').textContent = visitas + '/día';
          document.getElementById('adm-sv-empresas').textContent = empresas;
          document.getElementById('adm-sv-semilla').textContent = semilla;
          document.getElementById('adm-sv-pctselva').textContent = (pS * 100) + '%';
          document.getElementById('adm-sv-pctoro').textContent = (pO * 100) + '%';
          document.getElementById('adm-sv-pctazul').textContent = (pA * 100) + '%';
          document.getElementById('adm-sv-pctmicro').textContent = (pM * 100) + '%';
          document.getElementById('adm-sv-comision').textContent = serv;
          document.getElementById('adm-sv-valorserv').textContent = admFmt(valS);
          document.getElementById('adm-sv-destacados').textContent = dest;
          document.getElementById('adm-sv-alianzas').textContent = ali;

          document.getElementById('adm-sp-selva').textContent = admFmt(e.selva);
          document.getElementById('adm-sp-oro').textContent = admFmt(e.oro);
          document.getElementById('adm-sp-azul').textContent = admFmt(e.azul);
          document.getElementById('adm-sp-micro').textContent = admFmt(e.micro);
          const infoEl = document.getElementById('sim-etapa-info-admin');
          if (infoEl) infoEl.textContent = e.info;

          const nS = Math.round(empresas * pS);
          const nO = Math.round(empresas * pO);
          const nA = Math.round(empresas * pA);
          const nM = Math.round(empresas * pM);
          const nPago = nS + nO + nA + nM;
          const conv = semilla > 0 ? ((nPago / semilla) * 100).toFixed(1) : 0;

          const subS = nS * e.selva;
          const subO = nO * e.oro;
          const subA = nA * e.azul;
          const subM = nM * e.micro;
          const com = serv * valS * 0.075;
          const dp = dest * 30000;
          const al = ali * 500000;
          const pub = Math.round(visitas * 30 * 0.001) * 20000;
          const totalMes = subS + subO + subA + subM + com + dp + al + pub;

          const mensuales = [], acumulados = [];
          let acum = 0;
          for (let i = 0; i < 12; i++) {
            const m = admCalcMes(i, empresas, pS, pO, pA, pM, serv, valS, dest, ali);
            mensuales.push(m); acum += m; acumulados.push(acum);
          }

          document.getElementById('adm-sm-anual').textContent = admFmt(acumulados[11]);
          document.getElementById('adm-sm-mensual').textContent = admFmt(Math.round(acumulados[11] / 12));
          document.getElementById('adm-sm-mejor').textContent = admFmt(mensuales[11]);
          document.getElementById('adm-sm-dia').textContent = admFmt(Math.round(acumulados[11] / 12 / 30));
          document.getElementById('adm-sm-semilla-n').textContent = semilla;
          document.getElementById('adm-sm-micro-n').textContent = nM;
          document.getElementById('adm-sm-pago-n').textContent = nPago;
          document.getElementById('adm-sm-conv').textContent = conv + '%';

          const fuentes = [
            { label: 'Verde Selva (' + nS + ' emp.)', valor: subS, color: '#1D9E75' },
            { label: 'Amarillo Oro (' + nO + ' emp.)', valor: subO, color: '#BA7517' },
            { label: 'Azul Profundo (' + nA + ' emp.)', valor: subA, color: '#1a3f6f' },
            { label: 'Microempresa (' + nM + ' neg.)', valor: subM, color: '#7c3aed' },
            { label: 'Comisiones por servicios', valor: com, color: '#534AB7' },
            { label: 'Perfiles destacados', valor: dp, color: '#854F0B' },
            { label: 'Alianzas institucionales', valor: al, color: '#4a7c59' },
            { label: 'Publicidad local', valor: pub, color: '#607060' },
          ];

          document.getElementById('adm-sim-fuentes').innerHTML = fuentes.map(f => {
            const pct = totalMes > 0 ? Math.round(f.valor / totalMes * 100) : 0;
            return `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:13px">
          <div style="display:flex;align-items:center;gap:8px;color:rgba(255,255,255,.75)">
            <span style="width:10px;height:10px;border-radius:3px;background:${f.color};flex-shrink:0;display:inline-block"></span>${f.label}
          </div>
          <div>
            <span style="font-weight:700;color:white;font-family:'JetBrains Mono',monospace;font-size:12px">${admFmt(f.valor)}</span>
            <span style="color:rgba(255,255,255,.4);font-size:11px;margin-left:8px">${pct}%</span>
          </div>
        </div>`;
          }).join('');

          const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
          const barColors = mensuales.map((_, i) => i < 6 ? '#1D9E75' : i < 9 ? '#BA7517' : '#1a3f6f');

          if (admSimChart) admSimChart.destroy();
          const ctx = document.getElementById('adm-simChart');
          if (!ctx) return;
          admSimChart = new Chart(ctx, {
            type: 'bar',
            data: {
              labels: meses,
              datasets: [
                { label: 'Ingreso mensual', data: mensuales, backgroundColor: barColors, borderRadius: 8, yAxisID: 'y', order: 2 },
                { label: 'Acumulado', data: acumulados, type: 'line', borderColor: '#534AB7', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#534AB7', pointBorderColor: '#0d1a0d', pointBorderWidth: 2, yAxisID: 'y2', order: 1 }
              ]
            },
            options: {
              responsive: true, maintainAspectRatio: false,
              interaction: { mode: 'index', intersect: false },
              plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.dataset.label + ': ' + admFmt(c.raw) } } },
              scales: {
                x: { grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.5)', font: { size: 12 } } },
                y: { position: 'left', grid: { color: 'rgba(255,255,255,.06)' }, ticks: { color: 'rgba(255,255,255,.5)', font: { size: 11 }, callback: v => '$' + (v >= 1e6 ? (v / 1e6).toFixed(1) + 'M' : v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v) } },
                y2: { position: 'right', grid: { display: false }, ticks: { color: '#534AB7', font: { size: 11 }, callback: v => '$' + (v >= 1e6 ? (v / 1e6).toFixed(1) + 'M' : v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v) } }
              }
            }
          });
        }

        function iniciarSimulador() {
          if (!admSimInited) { admSimInited = true; setTimeout(admSimCalc, 50); }
        }
      </script>
    <?php endif; // fin simulador JS ?>

  <?php endif; ?>

</body>

</html>