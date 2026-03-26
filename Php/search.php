<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['candidatos'=>[],'empresas'=>[],'empleos'=>[],'convocatorias'=>[]]);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    $db  = getDB();
    $like = '%' . $q . '%';

    $stmt = $db->prepare("
        SELECT u.id, u.nombre, u.apellido, u.ciudad, u.foto,
               u.verificado,
               tp.profesion, tp.bio, tp.skills,
               tp.avatar_color, tp.destacado
        FROM usuarios u
        INNER JOIN talento_perfil tp ON tp.id = (
            SELECT MAX(id) FROM talento_perfil
            WHERE usuario_id = u.id
              AND visible = 1
              AND visible_admin = 1
        )
        WHERE u.activo = 1
          AND (
            u.nombre      LIKE :q OR
            u.apellido    LIKE :q OR
            u.ciudad      LIKE :q OR
            tp.profesion  LIKE :q OR
            tp.bio        LIKE :q OR
            tp.skills     LIKE :q
          )
        ORDER BY tp.destacado DESC, u.verificado DESC
        LIMIT 20
    ");
    $stmt->execute([':q' => $like]);
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT u.id, u.nombre, u.ciudad, u.verificado,
               ep.nombre_empresa, ep.sector, ep.descripcion,
               ep.logo, ep.sitio_web, ep.avatar_color, ep.destacado
        FROM usuarios u
        INNER JOIN perfiles_empresa ep ON ep.id = (
            SELECT MAX(id) FROM perfiles_empresa
            WHERE usuario_id = u.id
              AND visible = 1
              AND visible_admin = 1
        )
        WHERE u.activo = 1
          AND u.tipo = 'empresa'
          AND (
            ep.nombre_empresa LIKE :q OR
            ep.sector         LIKE :q OR
            ep.descripcion    LIKE :q OR
            u.ciudad          LIKE :q
          )
        ORDER BY ep.destacado DESC, u.verificado DESC
        LIMIT 20
    ");
    $stmt->execute([':q' => $like]);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT e.id, e.titulo, e.descripcion, e.categoria,
               e.ciudad, e.creado_en,
               COALESCE(e.modalidad, e.tipo_contrato, '')   AS modalidad,
               COALESCE(e.salario_texto, '')                AS salario_texto,
               COALESCE(ep.nombre_empresa, u.nombre, '')    AS empresa_nombre
        FROM empleos e
        LEFT JOIN usuarios u ON u.id = e.empresa_id
        LEFT JOIN perfiles_empresa ep ON ep.id = (
            SELECT MAX(id) FROM perfiles_empresa
            WHERE usuario_id = e.empresa_id LIMIT 1
        )
        WHERE e.activo = 1
          AND (
            e.titulo       LIKE :q OR
            e.descripcion  LIKE :q OR
            e.categoria    LIKE :q OR
            e.ciudad       LIKE :q OR
            ep.nombre_empresa LIKE :q
          )
        ORDER BY e.creado_en DESC
        LIMIT 20
    ");
    $stmt->execute([':q' => $like]);
    $empleos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("
        SELECT id, entidad, titulo, modalidad, nivel,
               salario, lugar, estado, icono,
               url_externa, vence_en, creado_en
        FROM convocatorias
        WHERE activo = 1
          AND (
            titulo    LIKE :q OR
            entidad   LIKE :q OR
            nivel     LIKE :q OR
            lugar     LIKE :q OR
            modalidad LIKE :q
          )
        ORDER BY
          CASE WHEN estado = 'abierta' THEN 0 ELSE 1 END,
          creado_en DESC
        LIMIT 20
    ");
    $stmt->execute([':q' => $like]);
    $convocatorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'candidatos'    => $candidatos,
        'empresas'      => $empresas,
        'empleos'       => $empleos,
        'convocatorias' => $convocatorias,
        'query'         => $q,
    ]);

} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode([
        'candidatos'    => [],
        'empresas'      => [],
        'empleos'       => [],
        'convocatorias' => [],
        '_error'        => $ex->getMessage()
    ]);
}
