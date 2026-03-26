<?php
// ============================================================
// Php/planes_helper.php — Sistema central de planes y límites
// QuibdóConecta 2026
// ============================================================
// Usar en cualquier endpoint: require_once __DIR__ . '/planes_helper.php';
// ============================================================

// ── Definición de planes con sus límites ─────────────────────
const PLANES = [
    'semilla' => [
        'nombre'         => 'Semilla',
        'aplicaciones'   => 3,
        'vacantes'       => 1,
        'mensajes'       => 10,
        'ver_candidatos' => 5,   // perfiles de candidatos que puede ver la empresa
        'ver_empresas'   => 5,   // perfiles de empresas que puede ver el candidato
        'visitantes'     => 0,   // cuántos visitantes puede ver
        'candidatos_por_vacante' => 3,
        'logo'           => false,
        'alertas'        => false,
        'portafolio'     => false,
        'verificado'     => false,
        'banner'         => false,
        'reporte_pdf'    => false,
        'redes'          => false,
        'estadisticas'   => 'ninguna',
        'soporte'        => 'comunidad',
        'posicion'       => 'al_final',
        'historial_dias' => 0,
    ],
    'verde_selva' => [
        'nombre'         => 'Verde Selva',
        'aplicaciones'   => 8,
        'vacantes'       => 5,
        'mensajes'       => 40,
        'ver_candidatos' => 30,
        'ver_empresas'   => 20,
        'visitantes'     => 0,
        'candidatos_por_vacante' => 15,
        'logo'           => true,
        'alertas'        => true,
        'portafolio'     => true,
        'verificado'     => false,
        'banner'         => false,
        'reporte_pdf'    => false,
        'redes'          => false,
        'estadisticas'   => 'basicas',
        'soporte'        => 'email',
        'posicion'       => 'normal',
        'historial_dias' => 0,
    ],
    'amarillo_oro' => [
        'nombre'         => 'Amarillo Oro',
        'aplicaciones'   => -1,  // ilimitado
        'vacantes'       => -1,
        'mensajes'       => 150,
        'ver_candidatos' => -1,
        'ver_empresas'   => -1,
        'visitantes'     => 5,
        'candidatos_por_vacante' => -1,
        'logo'           => true,
        'alertas'        => true,
        'portafolio'     => true,
        'verificado'     => true,
        'banner'         => false,
        'reporte_pdf'    => false,
        'redes'          => false,
        'estadisticas'   => 'avanzadas',
        'soporte'        => 'whatsapp',
        'posicion'       => 'destacado',
        'historial_dias' => 0,
    ],
    'azul_profundo' => [
        'nombre'         => 'Azul Profundo',
        'aplicaciones'   => -1,
        'vacantes'       => -1,
        'mensajes'       => -1,
        'ver_candidatos' => -1,
        'ver_empresas'   => -1,
        'visitantes'     => -1,  // todas
        'candidatos_por_vacante' => -1,
        'logo'           => true,
        'alertas'        => true,
        'portafolio'     => true,
        'verificado'     => true,
        'banner'         => true,  // 15 días/mes
        'reporte_pdf'    => true,
        'redes'          => true,
        'estadisticas'   => 'completas',
        'soporte'        => 'dedicado',
        'posicion'       => 'primero_siempre',
        'historial_dias' => 90,
    ],
    'microempresa' => [
        'nombre'         => 'Microempresa',
        'aplicaciones'   => 0,   // no aplica para empresas
        'vacantes'       => 2,
        'mensajes'       => 40,
        'ver_candidatos' => 15,
        'ver_empresas'   => 0,
        'visitantes'     => 0,
        'candidatos_por_vacante' => 8,
        'logo'           => true,
        'alertas'        => false,
        'portafolio'     => false,
        'verificado'     => false,
        'banner'         => false,
        'reporte_pdf'    => false,
        'redes'          => false,
        'estadisticas'   => 'basicas',
        'soporte'        => 'email',
        'posicion'       => 'normal',
        'historial_dias' => 0,
    ],
];

// ── Mapeo badge nombre → clave de plan ───────────────────────
// Incluye variantes y aliases chocoanos
const BADGE_A_PLAN = [
    // Verde Selva
    'verde selva'        => 'verde_selva',
    'verde_selva'        => 'verde_selva',
    'selva verde'        => 'verde_selva',
    'plan verde'         => 'verde_selva',
    // Amarillo Oro
    'amarillo oro'       => 'amarillo_oro',
    'amarillo_oro'       => 'amarillo_oro',
    'oro amarillo'       => 'amarillo_oro',
    'plan oro'           => 'amarillo_oro',
    // Azul Profundo
    'azul profundo'      => 'azul_profundo',
    'azul_profundo'      => 'azul_profundo',
    'profundo azul'      => 'azul_profundo',
    'plan azul'          => 'azul_profundo',
    'azul'               => 'azul_profundo',
    // Microempresa
    'microempresa'       => 'microempresa',
    'micro empresa'      => 'microempresa',
    'micro'              => 'microempresa',
    'plan micro'         => 'microempresa',
];

// ── Orden de prioridad de planes (mayor = mejor) ─────────────
const PLAN_PRIORIDAD = [
    'semilla'      => 0,
    'microempresa' => 1,
    'verde_selva'  => 2,
    'amarillo_oro' => 3,
    'azul_profundo'=> 4,
];

/**
 * Detecta el plan activo del usuario según sus badges
 * Retorna clave de plan ('semilla', 'verde_selva', etc.)
 */
function getPlanUsuario(PDO $db, int $userId): string {
    try {
        $stmt = $db->prepare("SELECT badges_custom FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['badges_custom']) return 'semilla';

        $ids = json_decode($row['badges_custom'], true);
        if (!$ids || !is_array($ids)) return 'semilla';

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt2 = $db->prepare("SELECT nombre FROM badges_catalog WHERE id IN ($placeholders) AND activo = 1");
        $stmt2->execute($ids);
        $badgeNames = $stmt2->fetchAll(PDO::FETCH_COLUMN);

        $planActual = 'semilla';
        $prioActual = 0;
        foreach ($badgeNames as $nombre) {
            $key = strtolower(trim($nombre));
            if (isset(BADGE_A_PLAN[$key])) {
                $planKey = BADGE_A_PLAN[$key];
                $prio = PLAN_PRIORIDAD[$planKey] ?? 0;
                if ($prio > $prioActual) {
                    $planActual = $planKey;
                    $prioActual = $prio;
                }
            }
        }
        return $planActual;
    } catch (Exception $e) {
        return 'semilla';
    }
}

/**
 * Crea la tabla uso_acciones si no existe
 */
function crearTablaUsoSiNoExiste(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS uso_acciones (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id  INT NOT NULL,
        accion      VARCHAR(50) NOT NULL,
        periodo     CHAR(7) NOT NULL COMMENT 'YYYY-MM',
        cantidad    INT NOT NULL DEFAULT 0,
        UNIQUE KEY uk_uso (usuario_id, accion, periodo),
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Verifica si el usuario puede realizar una acción según su plan
 * Retorna array: ['puede' => bool, 'usado' => int, 'limite' => int, 'plan' => string]
 *
 * @param string $accion  'mensajes' | 'aplicaciones' | 'vacantes' | 'ver_candidatos' | 'ver_empresas'
 */
function verificarLimite(PDO $db, int $userId, string $accion): array {
    $plan   = getPlanUsuario($db, $userId);
    $config = PLANES[$plan] ?? PLANES['semilla'];
    $limite = $config[$accion] ?? 0;

    // -1 = ilimitado
    if ($limite === -1) {
        return ['puede' => true, 'usado' => 0, 'limite' => -1, 'plan' => $plan];
    }

    $periodo = date('Y-m');
    try {
        crearTablaUsoSiNoExiste($db);
        $stmt = $db->prepare("SELECT cantidad FROM uso_acciones WHERE usuario_id=? AND accion=? AND periodo=?");
        $stmt->execute([$userId, $accion, $periodo]);
        $usado = (int) ($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        $usado = 0;
    }

    return [
        'puede'  => $usado < $limite,
        'usado'  => $usado,
        'limite' => $limite,
        'plan'   => $plan,
    ];
}

/**
 * Registra el uso de una acción (llama después del INSERT exitoso)
 */
function registrarAccion(PDO $db, int $userId, string $accion): void {
    $periodo = date('Y-m');
    try {
        crearTablaUsoSiNoExiste($db);
        $db->prepare("INSERT INTO uso_acciones (usuario_id, accion, periodo, cantidad)
                      VALUES (?, ?, ?, 1)
                      ON DUPLICATE KEY UPDATE cantidad = cantidad + 1")
           ->execute([$userId, $accion, $periodo]);
    } catch (Exception $e) {
        // silencioso — no bloquear la operación principal
    }
}

/**
 * Retorna el JSON de error estándar cuando se supera el límite
 * Incluye el plan siguiente recomendado
 */
function msgLimiteSuperado(string $plan, string $accion, int $limite): string {
    $planesOrden = ['semilla', 'microempresa', 'verde_selva', 'amarillo_oro', 'azul_profundo'];
    $idx = array_search($plan, $planesOrden);
    $siguiente = ($idx !== false && $idx < count($planesOrden) - 1)
        ? PLANES[$planesOrden[$idx + 1]]['nombre']
        : null;

    $accionLabel = [
        'mensajes'       => 'mensajes',
        'aplicaciones'   => 'aplicaciones a empleos',
        'vacantes'       => 'vacantes publicadas',
        'ver_candidatos' => 'perfiles de candidatos vistos',
        'ver_empresas'   => 'perfiles de empresas vistos',
    ][$accion] ?? $accion;

    $msg = "Alcanzaste el límite de $limite $accionLabel este mes con el plan " . (PLANES[$plan]['nombre'] ?? $plan) . ".";
    if ($siguiente) $msg .= " Mejora al plan $siguiente para continuar.";

    return json_encode(['ok' => false, 'msg' => $msg, 'limite_plan' => true, 'plan_actual' => $plan, 'plan_siguiente' => $siguiente]);
}

/**
 * Verifica si el usuario tiene un beneficio booleano según su plan
 * @param string $beneficio  'logo' | 'alertas' | 'portafolio' | 'verificado' | 'banner' | 'reporte_pdf' | 'redes'
 */
function tieneBeneficio(PDO $db, int $userId, string $beneficio): bool {
    $plan   = getPlanUsuario($db, $userId);
    $config = PLANES[$plan] ?? PLANES['semilla'];
    return !empty($config[$beneficio]);
}

/**
 * Obtiene todos los datos del plan del usuario (para mostrar en dashboard)
 */
function getDatosPlan(PDO $db, int $userId): array {
    $plan   = getPlanUsuario($db, $userId);
    $config = PLANES[$plan] ?? PLANES['semilla'];
    $periodo = date('Y-m');

    $acciones = ['mensajes', 'aplicaciones', 'vacantes', 'ver_candidatos', 'ver_empresas'];
    $usados = [];

    try {
        crearTablaUsoSiNoExiste($db);
        foreach ($acciones as $acc) {
            $stmt = $db->prepare("SELECT cantidad FROM uso_acciones WHERE usuario_id=? AND accion=? AND periodo=?");
            $stmt->execute([$userId, $acc, $periodo]);
            $usados[$acc] = (int) ($stmt->fetchColumn() ?: 0);
        }
    } catch (Exception $e) {
        foreach ($acciones as $acc) $usados[$acc] = 0;
    }

    return [
        'plan'    => $plan,
        'nombre'  => $config['nombre'],
        'config'  => $config,
        'usados'  => $usados,
        'periodo' => $periodo,
    ];
}
