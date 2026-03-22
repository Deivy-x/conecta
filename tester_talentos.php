<?php
// ============================================================
// tester_talentos.php — Diagnóstico completo QuibdóConecta
// Sube este archivo a la raíz del proyecto y ábrelo
// en el navegador. ELIMÍNALO después de usarlo.
// ============================================================
define('DB_HOST',    'sql213.infinityfree.com');
define('DB_NAME',    'if0_41408419_quibdo');
define('DB_USER',    'if0_41408419');
define('DB_PASS',    'quibdoconecta');
define('DB_CHARSET', 'utf8mb4');

// ── Seguridad mínima: solo accessible localmente o con clave ──
// Descomenta y cambia la clave si quieres protegerlo:
// if (($_GET['clave'] ?? '') !== 'miclaveSecreta') { http_response_code(403); die('Acceso denegado'); }

function getConn(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

// ── Acción: limpiar duplicados ─────────────────────────────
$accion_msg = '';
if (isset($_POST['limpiar_duplicados'])) {
    try {
        $pdo = getConn();
        $deleted = $pdo->exec("
            DELETE tp1 FROM talento_perfil tp1
            INNER JOIN talento_perfil tp2
              ON tp1.usuario_id = tp2.usuario_id
              AND tp1.id < tp2.id
        ");
        $accion_msg = "✅ Duplicados eliminados. Filas borradas: $deleted";
        // Intentar agregar UNIQUE
        try {
            $pdo->exec("ALTER TABLE talento_perfil ADD UNIQUE KEY uq_usuario_id (usuario_id)");
            $accion_msg .= " — UNIQUE KEY agregado ✅";
        } catch (Exception $e) {
            $accion_msg .= " — UNIQUE KEY ya existe o no se pudo agregar (ok igual)";
        }
    } catch (Exception $e) {
        $accion_msg = "❌ Error: " . $e->getMessage();
    }
}

// ── Recolectar datos ───────────────────────────────────────
$tests = [];
$pdo = null;
$conn_ok = false;

// TEST 1: Conexión
try {
    $pdo = getConn();
    $pdo->query("SELECT 1");
    $tests[] = ['ok' => true, 'titulo' => '1. Conexión a la BD', 'detalle' => 'Conectado a ' . DB_HOST . ' / ' . DB_NAME];
    $conn_ok = true;
} catch (Exception $e) {
    $tests[] = ['ok' => false, 'titulo' => '1. Conexión a la BD', 'detalle' => $e->getMessage()];
}

if ($conn_ok) {

    // TEST 2: Tablas requeridas
    $tablas_req = ['usuarios','talento_perfil','badges_catalog','admin_roles','admin_auditoria','empleos','mensajes','verificaciones'];
    $tablas_existentes = array_column($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM), 0);
    $faltantes = array_diff($tablas_req, $tablas_existentes);
    $tests[] = [
        'ok'     => empty($faltantes),
        'titulo' => '2. Tablas requeridas',
        'detalle'=> empty($faltantes)
            ? 'Todas las tablas existen (' . implode(', ', $tablas_req) . ')'
            : 'Faltan: ' . implode(', ', $faltantes)
    ];

    // TEST 3: UNIQUE KEY en talento_perfil
    try {
        $keys = $pdo->query("SHOW INDEX FROM talento_perfil WHERE Non_unique = 0 AND Column_name = 'usuario_id'")->fetchAll();
        $tiene_unique = count($keys) > 0;
        $tests[] = [
            'ok'     => $tiene_unique,
            'titulo' => '3. UNIQUE KEY en talento_perfil.usuario_id',
            'detalle'=> $tiene_unique
                ? 'UNIQUE KEY presente — duplicados imposibles a nivel BD ✅'
                : '⚠️ No hay UNIQUE KEY — pueden crearse duplicados. Usa el botón de abajo para limpiar y agregar.'
        ];
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '3. UNIQUE KEY talento_perfil', 'detalle' => $e->getMessage()];
    }

    // TEST 4: Duplicados actuales en talento_perfil
    try {
        $dups = $pdo->query("
            SELECT tp.usuario_id,
                   u.nombre, u.apellido,
                   COUNT(*) AS total_filas,
                   GROUP_CONCAT(tp.id ORDER BY tp.id) AS ids
            FROM talento_perfil tp
            LEFT JOIN usuarios u ON u.id = tp.usuario_id
            GROUP BY tp.usuario_id
            HAVING COUNT(*) > 1
        ")->fetchAll();

        if (empty($dups)) {
            $tests[] = ['ok' => true, 'titulo' => '4. Duplicados en talento_perfil', 'detalle' => 'Sin duplicados ✅'];
        } else {
            $lista = array_map(fn($d) => "{$d['nombre']} {$d['apellido']} (usuario_id={$d['usuario_id']}, {$d['total_filas']} filas, ids={$d['ids']})", $dups);
            $tests[] = [
                'ok'     => false,
                'titulo' => '4. Duplicados en talento_perfil',
                'detalle'=> '❌ ' . count($dups) . ' usuario(s) con filas duplicadas:<br>• ' . implode('<br>• ', $lista),
                'mostrar_boton_limpiar' => true
            ];
        }
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '4. Duplicados en talento_perfil', 'detalle' => $e->getMessage()];
    }

    // TEST 5: Query VIEJA — cuántos resultados devuelve (detecta el bug)
    try {
        $vieja = $pdo->query("
            SELECT u.id, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) AS nombre, tp.profesion
            FROM usuarios u
            INNER JOIN talento_perfil tp ON tp.usuario_id = u.id
            WHERE u.activo = 1 AND tp.visible = 1 AND tp.visible_admin = 1
            ORDER BY u.id ASC
        ")->fetchAll();

        $ids_vistos = [];
        $hay_dup = false;
        foreach ($vieja as $r) {
            if (isset($ids_vistos[$r['id']])) $hay_dup = true;
            $ids_vistos[$r['id']] = true;
        }

        $lista = array_map(fn($r) => "#{$r['id']} {$r['nombre']} ({$r['profesion']})", $vieja);
        $tests[] = [
            'ok'     => !$hay_dup,
            'titulo' => '5. Query VIEJA (JOIN directo)',
            'detalle'=> ($hay_dup ? '❌ Devuelve duplicados — ' : '✅ Sin duplicados — ')
                       . count($vieja) . " filas:<br>• " . implode('<br>• ', $lista)
        ];
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '5. Query VIEJA', 'detalle' => $e->getMessage()];
    }

    // TEST 6: Query NUEVA — MAX(id) subquery
    try {
        $nueva = $pdo->query("
            SELECT u.id, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) AS nombre,
                   tp.profesion, tp.skills, tp.visible, tp.visible_admin, tp.destacado
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
        ")->fetchAll();

        $ids_nueva = array_column($nueva, 'id');
        $hay_dup_nueva = count($ids_nueva) !== count(array_unique($ids_nueva));
        $lista = array_map(fn($r) => "#{$r['id']} {$r['nombre']} — {$r['profesion']} | skills: {$r['skills']}", $nueva);
        $tests[] = [
            'ok'     => !$hay_dup_nueva,
            'titulo' => '6. Query NUEVA (MAX id subquery) — la que va en talentos.php',
            'detalle'=> ($hay_dup_nueva ? '❌ Aún hay duplicados — ' : '✅ Sin duplicados — ')
                       . count($nueva) . " talentos visibles:<br>• " . implode('<br>• ', $lista)
        ];
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '6. Query NUEVA', 'detalle' => $e->getMessage()];
    }

    // TEST 7: Query talentos_preview (3 para el index)
    try {
        $preview = $pdo->query("
            SELECT u.id, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) AS nombre,
                   tp.profesion
            FROM usuarios u
            INNER JOIN talento_perfil tp ON tp.id = (
                SELECT MAX(id) FROM talento_perfil
                WHERE usuario_id = u.id
                  AND visible = 1
                  AND visible_admin = 1
                  AND profesion IS NOT NULL
                  AND profesion != ''
            )
            WHERE u.activo = 1
            ORDER BY u.verificado DESC, u.id ASC
            LIMIT 3
        ")->fetchAll();

        $lista = array_map(fn($r) => "#{$r['id']} {$r['nombre']} — {$r['profesion']}", $preview);
        $tests[] = [
            'ok'     => count($preview) <= 3,
            'titulo' => '7. Query talentos_preview (LIMIT 3 para index)',
            'detalle'=> count($preview) . " resultados:<br>• " . implode('<br>• ', $lista ?: ['(sin talentos visibles)'])
        ];
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '7. Query talentos_preview', 'detalle' => $e->getMessage()];
    }

    // TEST 8: Estado general de talento_perfil
    try {
        $stats = $pdo->query("
            SELECT
                COUNT(*) AS total_filas,
                COUNT(DISTINCT usuario_id) AS usuarios_unicos,
                SUM(visible=1 AND visible_admin=1) AS visibles,
                SUM(visible=0 OR visible_admin=0) AS ocultos,
                SUM(destacado=1) AS destacados
            FROM talento_perfil
        ")->fetch();

        $tests[] = [
            'ok'     => true,
            'titulo' => '8. Estado de talento_perfil',
            'detalle'=> "Total filas: {$stats['total_filas']} | Usuarios únicos: {$stats['usuarios_unicos']} | Visibles: {$stats['visibles']} | Ocultos: {$stats['ocultos']} | Destacados: {$stats['destacados']}"
                       . ($stats['total_filas'] > $stats['usuarios_unicos']
                           ? " <strong style='color:#ef4444'>⚠️ Hay " . ($stats['total_filas'] - $stats['usuarios_unicos']) . " fila(s) duplicada(s)</strong>"
                           : " ✅ Sin duplicados")
        ];
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '8. Estado talento_perfil', 'detalle' => $e->getMessage()];
    }

    // TEST 9: Tabla usuarios
    try {
        $usrs = $pdo->query("
            SELECT u.id, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) AS nombre,
                   u.tipo, u.activo, u.verificado,
                   CASE WHEN tp.usuario_id IS NOT NULL THEN 'Sí' ELSE 'No' END AS tiene_perfil,
                   COALESCE(tp.visible_admin, 0) AS en_talentos
            FROM usuarios u
            LEFT JOIN (SELECT DISTINCT usuario_id, MAX(visible_admin) as visible_admin FROM talento_perfil GROUP BY usuario_id) tp
              ON tp.usuario_id = u.id
            ORDER BY u.id ASC
        ")->fetchAll();

        $filas = array_map(fn($u) =>
            "#{$u['id']} {$u['nombre']} | tipo:{$u['tipo']} | activo:" . ($u['activo'] ? '✅' : '❌')
            . " | verificado:" . ($u['verificado'] ? '✅' : '—')
            . " | perfil:{$u['tiene_perfil']}"
            . " | en_talentos:" . ($u['en_talentos'] ? '✅' : '—'),
            $usrs
        );
        $tests[] = [
            'ok'     => true,
            'titulo' => '9. Usuarios registrados (' . count($usrs) . ')',
            'detalle'=> implode('<br>• ', array_merge([''], $filas))
        ];
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '9. Usuarios', 'detalle' => $e->getMessage()];
    }

    // TEST 10: Badges catalog
    try {
        $badges = $pdo->query("SELECT id, emoji, nombre, tipo, activo FROM badges_catalog ORDER BY tipo, nombre")->fetchAll();
        if (empty($badges)) {
            $tests[] = ['ok' => true, 'titulo' => '10. Badges catalog', 'detalle' => 'Catálogo vacío (sin badges creados aún)'];
        } else {
            $lista = array_map(fn($b) => "{$b['emoji']} {$b['nombre']} (tipo:{$b['tipo']}, activo:" . ($b['activo'] ? '✅' : '❌') . ")", $badges);
            $tests[] = ['ok' => true, 'titulo' => '10. Badges catalog (' . count($badges) . ')', 'detalle' => implode('<br>• ', array_merge([''], $lista))];
        }
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '10. Badges catalog', 'detalle' => $e->getMessage()];
    }

    // TEST 11: admin_roles
    try {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM admin_roles")->fetchAll(), 'Field');
        $necesarias = ['perm_usuarios','perm_empleos','perm_badges','perm_talentos'];
        $falta = array_diff($necesarias, $cols);
        $tests[] = [
            'ok'     => empty($falta),
            'titulo' => '11. Columnas admin_roles',
            'detalle'=> empty($falta)
                ? 'Todas las columnas de permisos presentes ✅ (' . implode(', ', $cols) . ')'
                : '⚠️ Faltan columnas: ' . implode(', ', $falta) . '. Corre las migraciones pendientes.'
        ];
    } catch (Exception $e) {
        $tests[] = ['ok' => false, 'titulo' => '11. admin_roles', 'detalle' => $e->getMessage()];
    }

}

// ── Contar ok/fail ─────────────────────────────────────────
$total_ok   = count(array_filter($tests, fn($t) => $t['ok']));
$total_fail = count($tests) - $total_ok;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🔧 Tester QuibdóConecta</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box }
  body { font-family: 'Segoe UI', sans-serif; background:#0f172a; color:#e2e8f0; padding:32px 20px; min-height:100vh }
  .container { max-width:900px; margin:0 auto }
  h1 { font-size:24px; font-weight:800; margin-bottom:6px; color:#fff }
  .sub { font-size:13px; color:#64748b; margin-bottom:28px; font-family:monospace }
  .summary {
    display:flex; gap:16px; margin-bottom:28px; flex-wrap:wrap
  }
  .sum-card {
    flex:1; min-width:140px; padding:16px 20px; border-radius:12px;
    font-size:13px; font-weight:700; text-align:center
  }
  .sum-ok  { background:#052e16; border:1px solid #16a34a; color:#4ade80 }
  .sum-fail{ background:#2d0a0a; border:1px solid #dc2626; color:#f87171 }
  .sum-tot { background:#1e293b; border:1px solid #334155; color:#94a3b8 }

  .accion-msg {
    padding:14px 18px; border-radius:10px; margin-bottom:20px;
    font-size:14px; font-weight:600;
    background:#052e16; border:1px solid #16a34a; color:#4ade80
  }
  .accion-msg.error { background:#2d0a0a; border-color:#dc2626; color:#f87171 }

  .test {
    background:#1e293b; border:1px solid #334155; border-radius:14px;
    margin-bottom:14px; overflow:hidden
  }
  .test-header {
    display:flex; align-items:center; gap:12px; padding:14px 18px;
    cursor:pointer; user-select:none
  }
  .test-header:hover { background:#263347 }
  .dot {
    width:10px; height:10px; border-radius:50%; flex-shrink:0
  }
  .dot.ok   { background:#22c55e; box-shadow:0 0 6px #22c55e88 }
  .dot.fail { background:#ef4444; box-shadow:0 0 6px #ef444488 }
  .test-title { font-size:14px; font-weight:700; flex:1 }
  .test-arrow { color:#475569; font-size:12px; transition:transform .2s }
  .test-body {
    padding:0 18px 16px 40px; font-size:13px; color:#94a3b8;
    line-height:1.7; display:none
  }
  .test-body.open { display:block }
  .test.fail .test-header { border-left:3px solid #ef4444 }
  .test.ok   .test-header { border-left:3px solid #22c55e }

  .btn-limpiar {
    margin-top:12px; padding:10px 22px;
    background:#7f1d1d; border:1px solid #dc2626;
    color:#fca5a5; border-radius:8px; font-size:13px; font-weight:700;
    cursor:pointer; font-family:inherit
  }
  .btn-limpiar:hover { background:#991b1b }

  .warning-box {
    background:#1c1400; border:1px solid #ca8a04; border-radius:10px;
    padding:12px 16px; margin-top:24px; font-size:13px; color:#fbbf24
  }
  footer { margin-top:40px; text-align:center; font-size:12px; color:#334155 }
</style>
</head>
<body>
<div class="container">

  <h1>🔧 Tester — QuibdóConecta</h1>
  <div class="sub">BD: <?= DB_NAME ?> @ <?= DB_HOST ?> — <?= date('d/m/Y H:i:s') ?></div>

  <!-- Resumen -->
  <div class="summary">
    <div class="sum-card sum-ok">✅ <?= $total_ok ?> pasaron</div>
    <div class="sum-card sum-fail">❌ <?= $total_fail ?> fallaron</div>
    <div class="sum-card sum-tot">📋 <?= count($tests) ?> total</div>
  </div>

  <!-- Mensaje de acción -->
  <?php if ($accion_msg): ?>
  <div class="accion-msg <?= str_starts_with($accion_msg,'❌') ? 'error' : '' ?>">
    <?= $accion_msg ?>
  </div>
  <?php endif; ?>

  <!-- Tests -->
  <?php foreach ($tests as $i => $t): ?>
  <div class="test <?= $t['ok'] ? 'ok' : 'fail' ?>">
    <div class="test-header" onclick="toggle(<?= $i ?>)">
      <div class="dot <?= $t['ok'] ? 'ok' : 'fail' ?>"></div>
      <div class="test-title"><?= htmlspecialchars($t['titulo']) ?></div>
      <div class="test-arrow" id="arrow-<?= $i ?>">▼</div>
    </div>
    <div class="test-body <?= !$t['ok'] ? 'open' : '' ?>" id="body-<?= $i ?>">
      <?= $t['detalle'] ?>
      <?php if (!empty($t['mostrar_boton_limpiar'])): ?>
      <form method="POST" style="margin-top:12px">
        <button type="submit" name="limpiar_duplicados" class="btn-limpiar"
                onclick="return confirm('¿Eliminar filas duplicadas y agregar UNIQUE KEY?')">
          🗑️ Limpiar duplicados ahora
        </button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="warning-box">
    ⚠️ <strong>Recuerda eliminar este archivo</strong> después de usarlo —
    contiene las credenciales de tu BD y no debe quedar público en producción.
  </div>

  <footer>QuibdóConecta · tester_talentos.php · Solo uso interno</footer>

</div>
<script>
function toggle(i) {
  const body  = document.getElementById('body-' + i);
  const arrow = document.getElementById('arrow-' + i);
  const open  = body.classList.toggle('open');
  arrow.style.transform = open ? 'rotate(180deg)' : '';
}
// Abrir automáticamente los que fallaron
document.querySelectorAll('.test.fail .test-body').forEach(el => el.classList.add('open'));
document.querySelectorAll('.test.fail .test-arrow').forEach(el => el.style.transform = 'rotate(180deg)');
</script>
</body>
</html>
