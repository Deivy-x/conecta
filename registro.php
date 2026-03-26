<?php

session_start();
require_once 'Php/db.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $nombre   = trim($_POST['nombre']   ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo   = trim($_POST['correo']   ?? '');
    $pass     = $_POST['contrasena']    ?? '';
    $pass2    = $_POST['contrasena2']   ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $ciudad   = trim($_POST['ciudad']   ?? '');
    $tipo     = trim($_POST['tipo']     ?? 'candidato');

    $profesion_tipo   = trim($_POST['profesion_tipo']  ?? '');
    $fecha_nac_val    = trim($_POST['fecha_nacimiento']?? '');

    $nombre_empresa   = trim($_POST['nombre_empresa']  ?? '');
    $sector           = trim($_POST['sector']          ?? '');
    $nit              = trim($_POST['nit']             ?? '');
    $razon_social     = trim($_POST['razon_social']    ?? '');
    $rep_legal        = trim($_POST['rep_legal']       ?? '');
    $fecha_fundacion  = trim($_POST['fecha_fundacion'] ?? '') ?: null;
    $municipio_empresa= trim($_POST['municipio_empresa']?? '');
    $sitio_web        = trim($_POST['sitio_web']       ?? '');
    $empleados        = trim($_POST['num_empleados']   ?? '');
    $tipo_empresa     = trim($_POST['tipo_empresa_reg']?? '');
    $camara_comercio  = trim($_POST['camara_comercio'] ?? '');

    $nombre_negocio   = trim($_POST['nombre_negocio']  ?? '');
    $categoria_neg    = trim($_POST['categoria_neg']   ?? '');
    $tipo_negocio_reg = trim($_POST['tipo_negocio_reg']?? 'emp');
    $nombre_cc        = trim($_POST['nombre_cc']       ?? '');
    $local_numero     = trim($_POST['local_numero']    ?? '');
    $barrio_negocio   = trim($_POST['barrio_negocio']  ?? '');
    $whatsapp_neg     = preg_replace('/\D/', '', trim($_POST['whatsapp_neg'] ?? ''));
    $descripcion_neg  = trim($_POST['descripcion_neg'] ?? '');
    $precio_desde_neg = trim($_POST['precio_desde_neg']?? '');
    $link_neg_virtual = trim($_POST['link_neg_virtual'] ?? '');

    if (!$nombre || !$correo || !$pass) {
        echo json_encode(['ok'=>false,'msg'=>'Completa todos los campos obligatorios.']); exit;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok'=>false,'msg'=>'El correo no es válido.']); exit;
    }
    if (strlen($pass) < 8) {
        echo json_encode(['ok'=>false,'msg'=>'La contraseña debe tener al menos 8 caracteres.']); exit;
    }
    if ($pass !== $pass2) {
        echo json_encode(['ok'=>false,'msg'=>'Las contraseñas no coinciden.']); exit;
    }
    if (!in_array($tipo, ['candidato','empresa','negocio'])) {
        echo json_encode(['ok'=>false,'msg'=>'Tipo no válido.']); exit;
    }
    if ($tipo === 'candidato' && !$fecha_nac_val) {
        echo json_encode(['ok'=>false,'msg'=>'La fecha de nacimiento es obligatoria.']); exit;
    }
    
    if ($tipo === 'candidato' && $fecha_nac_val) {
        $nacimiento = new DateTime($fecha_nac_val);
        $hoy = new DateTime();
        $edad = $hoy->diff($nacimiento)->y;
        if ($edad < 16) {
            echo json_encode(['ok'=>false,'msg'=>'Debes tener al menos 16 años para registrarte.']); exit;
        }
    }
    if ($tipo === 'empresa' && !$nombre_empresa) {
        echo json_encode(['ok'=>false,'msg'=>'El nombre de la empresa es obligatorio.']); exit;
    }
    if ($tipo === 'negocio' && !$nombre_negocio) {
        echo json_encode(['ok'=>false,'msg'=>'El nombre del negocio es obligatorio.']); exit;
    }

    try {
        $db = getDB();

        $st = $db->prepare("SELECT id FROM usuarios WHERE correo = ?");
        $st->execute([$correo]); 
        if ($st->fetch()) {
            echo json_encode(['ok'=>false,'msg'=>'Este correo ya está registrado.']); exit;
        }

        $st2 = $db->prepare("SELECT id, estado FROM solicitudes_ingreso WHERE correo = ? ORDER BY creado_en DESC LIMIT 1");
        $st2->execute([$correo]);
        $solEx = $st2->fetch();
        if ($solEx) {
            if ($solEx['estado'] === 'pendiente') {
                echo json_encode(['ok'=>false,'msg'=>'Ya tienes una solicitud pendiente. El administrador la revisará pronto.']); exit;
            }
            if ($solEx['estado'] === 'rechazado') {
                echo json_encode(['ok'=>false,'msg'=>'Tu solicitud anterior fue rechazada. Contacta al administrador.']); exit;
            }
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $cedula = trim($_POST['cedula'] ?? '');
        $tipo_doc = trim($_POST['tipo_documento_hidden'] ?? 'cedula') ?: 'cedula';

        if ($tipo === 'candidato') {
            if (!$cedula) { echo json_encode(['ok'=>false,'msg'=>'El número de documento es obligatorio.']); exit; }
            if (empty($_FILES['doc_cedula']['name'])) { echo json_encode(['ok'=>false,'msg'=>'Debes subir la foto o PDF de tu documento.']); exit; }
        }

        $docUrl = null;
        if (!empty($_FILES['doc_cedula']['name'])) {
            $upDir = __DIR__ . '/uploads/verificaciones/';
            if (!is_dir($upDir)) mkdir($upDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['doc_cedula']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','pdf'])) {
                echo json_encode(['ok'=>false,'msg'=>'Formato no permitido. Usa JPG, PNG o PDF.']); exit;
            }
            if ($_FILES['doc_cedula']['size'] > 5*1024*1024) {
                echo json_encode(['ok'=>false,'msg'=>'El archivo no debe superar 5MB.']); exit;
            }
            $fn = $tipo_doc . '_' . preg_replace('/[^a-z0-9]/','',strtolower($correo)) . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($_FILES['doc_cedula']['tmp_name'], $upDir.$fn)) {
                echo json_encode(['ok'=>false,'msg'=>'Error al subir el archivo.']); exit;
            }
            $docUrl = 'uploads/verificaciones/' . $fn;
        }

        $fecha_nac_final = null;
        if ($tipo === 'candidato') $fecha_nac_final = $fecha_nac_val ?: null;

        $nombreResp = $tipo === 'empresa' ? $nombre_empresa : ($tipo === 'negocio' ? $nombre_negocio : $nombre);

        $extras = json_encode([
            'profesion_tipo'   => $profesion_tipo,
            'tipo_empresa'     => $tipo_empresa,
            'razon_social'     => $razon_social,
            'rep_legal'        => $rep_legal,
            'fecha_fundacion'  => $fecha_fundacion,
            'municipio_empresa'=> $municipio_empresa,
            'sitio_web'        => $sitio_web,
            'num_empleados'    => $empleados,
            'camara_comercio'  => $camara_comercio,
            'nombre_negocio'   => $nombre_negocio,
            'categoria_neg'    => $categoria_neg,
            'tipo_negocio_reg' => $tipo_negocio_reg,
            'nombre_cc'        => $nombre_cc,
            'local_numero'     => $local_numero,
            'barrio_negocio'   => $barrio_negocio,
            'whatsapp_neg'     => $whatsapp_neg,
            'descripcion_neg'  => $descripcion_neg,
            'precio_desde_neg' => $precio_desde_neg,
            'link_neg_virtual' => $link_neg_virtual,
        ]);

        $tipoBD = $tipo === 'negocio' ? 'empresa' : $tipo;
        $nombreEmpresaBD = $tipo === 'empresa' ? $nombre_empresa : ($tipo === 'negocio' ? $nombre_negocio : '');

        $db->prepare("
            INSERT INTO solicitudes_ingreso
              (nombre, apellido, correo, contrasena_hash, telefono, ciudad,
               tipo, nombre_empresa, sector, nit, fecha_nacimiento,
               cedula, doc_url, tipo_documento, nota_admin)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $nombre, $apellido ?: '', $correo, $hash, $telefono, $ciudad,
            $tipoBD, $nombreEmpresaBD, $sector, $nit, $fecha_nac_final,
            $cedula, $docUrl, $tipo_doc, $extras
        ]);

        echo json_encode([
            'ok'       => true,
            'pendiente'=> true,
            'msg'      => '¡Solicitud enviada! El administrador la revisará y recibirás acceso una vez aprobada.',
            'tipo'     => $tipo,
            'nombre'   => $nombreResp,
        ]);

    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>'Error al crear la cuenta. Inténtalo de nuevo.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registrarse – Quibdó Conecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,900;1,9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--verde:#0d5c2e;--verde2:#1e8c45;--lima:#4ade80;--oro:#f5c800;--oro2:#ffd94d;--rio:#0039a6;--rio2:#1a56db;--oscuro:#04150b}
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html,body{min-height:100%}
    body{font-family:'DM Sans',sans-serif;background:#060e07;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;position:relative;overflow-x:hidden}
    
    body::before{content:'';position:fixed;inset:0;background:url('Imagenes/quibdo 3.jpg') center/cover no-repeat;z-index:0}
    body::after{content:'';position:fixed;inset:0;background:linear-gradient(160deg,rgba(4,21,11,.93) 0%,rgba(13,92,46,.8) 40%,rgba(0,57,166,.75) 100%);z-index:0}
    
    .bandera-bot{position:fixed;bottom:0;left:0;right:0;height:5px;display:flex;z-index:99}
    .bandera-bot span{flex:1}
    .bandera-bot span:nth-child(1){background:var(--verde2)}
    .bandera-bot span:nth-child(2){background:var(--oro)}
    .bandera-bot span:nth-child(3){background:var(--rio2)}
    #canvas-bg{position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1}
    .btn-back{position:fixed;top:22px;left:22px;z-index:100;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.7);text-decoration:none;padding:9px 18px;border-radius:30px;font-size:13px;font-weight:600;backdrop-filter:blur(12px);transition:all .3s}
    .btn-back:hover{background:rgba(255,255,255,.15);color:white;transform:translateX(-3px)}
    .container{position:relative;z-index:10;width:100%;max-width:600px;background:rgba(4,21,11,.75);backdrop-filter:blur(28px);border:1px solid rgba(255,255,255,.1);border-left:4px solid;border-image:linear-gradient(to bottom,var(--verde2),var(--oro),var(--rio2)) 1;border-radius:0 28px 28px 0;padding:52px 48px 44px;box-shadow:0 32px 80px rgba(0,0,0,.7);color:white;animation:fadeUp .6s ease both;margin:40px 0 60px;min-height:auto}
    @media(max-width:640px){.container{border-left:none;border-top:4px solid;border-image:linear-gradient(to right,var(--verde2),var(--oro),var(--rio2)) 1;border-radius:0 0 24px 24px;margin:0;padding:44px 24px 40px}}
    @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}

    .header{display:flex;align-items:center;gap:14px;margin-bottom:32px}
    .header img{width:44px;filter:drop-shadow(0 2px 8px rgba(245,200,0,.35))}
    .header-txt{}
    .header h1{font-family:'Fraunces',serif;font-size:30px;font-weight:900;line-height:1.1;margin:0}
    .header h1 span{color:var(--lima)}
    .header p{color:rgba(255,255,255,.45);font-size:13px;margin-top:2px}
    .progress-bar{background:rgba(255,255,255,.08);border-radius:10px;height:4px;margin-bottom:28px;overflow:hidden}
    .progress-fill{height:100%;background:linear-gradient(90deg,var(--verde2),var(--oro));border-radius:10px;transition:width .4s ease;width:0%}

    .tipo-selector{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:24px}
    .tipo-btn{padding:12px 8px;border-radius:18px;border:1.5px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:rgba(255,255,255,.55);font-size:12px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .25s;text-align:center;letter-spacing:.2px}
    .tipo-btn .tipo-icon{font-size:20px;display:block;margin-bottom:5px}
    .tipo-btn.active{border-color:var(--verde2);background:rgba(30,140,69,.18);color:white;box-shadow:0 0 0 3px rgba(30,140,69,.12)}
    .tipo-btn.active-dorado{border-color:var(--oro);background:rgba(245,200,0,.15);color:white;box-shadow:0 0 0 3px rgba(245,200,0,.1)}
    .tipo-btn.active-azul{border-color:var(--rio2);background:rgba(26,86,219,.18);color:white;box-shadow:0 0 0 3px rgba(26,86,219,.12)}
    .tipo-btn.active-tierra{border-color:#b45309;background:rgba(180,83,9,.15);color:white;box-shadow:0 0 0 3px rgba(180,83,9,.1)}

    .row{display:flex;gap:14px}
    .grupo{flex:1;margin-bottom:14px}
    .grupo.full{flex:0 0 100%;width:100%}
    label{display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.7px}
    .req{color:#f87171}
    input,select,textarea{width:100%;padding:12px 15px;border:1.5px solid rgba(255,255,255,.1);border-radius:16px;background:rgba(255,255,255,.06);color:white;font-size:14px;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .25s,background .25s,box-shadow .25s;resize:none}
    input:focus,select:focus,textarea:focus{border-color:var(--verde2);background:rgba(30,140,69,.1);box-shadow:0 0 0 3px rgba(30,140,69,.1)}
    input::placeholder,textarea::placeholder{color:rgba(255,255,255,.2)}
    select option{background:#0d2a0d;color:white}
    .pass-wrap{position:relative}
    .pass-wrap input{padding-right:46px}
    .toggle-pass{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,.35);cursor:pointer;font-size:16px;transition:color .2s}
    .toggle-pass:hover{color:var(--lima)}
    .divider{height:1px;background:rgba(255,255,255,.07);margin:16px 0}
    .sec-title{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.35);margin:20px 0 12px;display:flex;align-items:center;gap:10px}
    .sec-title::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.07)}

    .upload-doc-area{border:2px dashed rgba(255,255,255,.15);border-radius:14px;padding:22px;text-align:center;cursor:pointer;transition:all .25s;background:rgba(255,255,255,.03);color:rgba(255,255,255,.5);font-size:14px}
    .upload-doc-area:hover{border-color:var(--lima);background:rgba(74,222,128,.05);color:white}
    .upload-doc-area.tiene-archivo{border-color:rgba(74,222,128,.4);background:rgba(74,222,128,.06);text-align:left;cursor:default;display:flex;align-items:center}

    .campos-tipo{display:none}
    .campos-tipo.show{display:block}

    .terminos{display:flex;align-items:flex-start;gap:10px;margin:16px 0;font-size:13px;color:rgba(255,255,255,.5)}
    .terminos input[type="checkbox"]{width:16px;height:16px;flex-shrink:0;margin-top:1px;accent-color:var(--verde2)}
    .terminos a{color:var(--lima);text-decoration:none}
    .btn-registro{width:100%;padding:15px;border:none;border-radius:16px;background:linear-gradient(135deg,var(--verde),var(--verde2));color:white;font-size:15px;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .25s;box-shadow:0 8px 28px rgba(30,140,69,.4);position:relative;overflow:hidden}
    .btn-registro::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.1),transparent);opacity:0;transition:opacity .25s}
    .btn-registro:hover{transform:translateY(-2px);box-shadow:0 12px 36px rgba(30,140,69,.55)}
    .btn-registro:hover::after{opacity:1}
    .btn-registro:disabled{opacity:.5;cursor:not-allowed;transform:none}
    .msg{display:none;padding:12px 16px;border-radius:12px;font-size:13px;margin-top:14px;font-weight:500;line-height:1.5}
    .msg.error{background:rgba(255,80,80,.12);border:1px solid rgba(255,80,80,.3);color:#ff9a9a}
    .msg.success{background:rgba(74,222,128,.12);border:1px solid rgba(74,222,128,.3);color:#a7f3d0}
    .link-abajo{text-align:center;margin-top:22px;font-size:14px;color:rgba(255,255,255,.4)}
    .link-abajo a{color:var(--lima);font-weight:700;text-decoration:none}

    .success-screen{display:none;text-align:center;padding:20px 0;animation:fadeUp .5s ease both}
    .success-screen .big-icon{font-size:60px;margin-bottom:16px}
    .success-screen h2{font-family:'Fraunces',serif;font-size:28px;color:var(--lima);margin-bottom:10px}
    .success-screen p{color:rgba(255,255,255,.6);font-size:15px;margin-bottom:28px;line-height:1.6}
    .btns-success{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
    .btn-ir{display:inline-block;padding:13px 28px;background:linear-gradient(135deg,var(--verde),var(--verde2));color:white;border-radius:25px;text-decoration:none;font-weight:700;font-size:14px;box-shadow:0 6px 20px rgba(30,140,69,.4);transition:transform .2s}
    .btn-ir:hover{transform:translateY(-2px)}
    .btn-ir.secundario{background:rgba(255,255,255,.08);box-shadow:none}

    .info-box{background:rgba(245,200,0,.07);border:1px solid rgba(245,200,0,.2);border-radius:12px;padding:12px 16px;font-size:12px;color:rgba(255,255,255,.5);line-height:1.6;margin-bottom:14px}
    .info-box strong{color:var(--oro2)}

    @media(max-width:500px){.row{flex-direction:column;gap:0}.tipo-selector{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body>
<div class="bandera-bot"><span></span><span></span><span></span></div>
<!-- Franja tricolor bandera Chocó -->
<div style="position:fixed;top:0;left:0;right:0;height:4px;display:flex;z-index:999"><span style="flex:1;background:#27a855"></span><span style="flex:1;background:#f5c800"></span><span style="flex:1;background:#1a56db"></span></div>
<canvas id="canvas-bg"></canvas>
<a href="index.html" class="btn-back">← Inicio</a>

<div class="container">
  <div class="header" style="justify-content:space-between">
    <div style="display:flex;align-items:center;gap:14px">
      <img src="Imagenes/Quibdo.png" alt="Logo">
      <div class="header-txt">
        <h1>Crear <span>cuenta</span></h1>
        <p>Únete a QuibdóConecta gratis</p>
      </div>
    </div>
    <!-- Bandera del Chocó en miniatura -->
    <div style="width:60px;height:36px;border-radius:8px;overflow:hidden;display:flex;flex-direction:column;border:1.5px solid rgba(255,255,255,.18);box-shadow:0 4px 14px rgba(0,0,0,.5);flex-shrink:0;position:relative">
      <div style="flex:1;background:#1a7a3c"></div>
      <div style="flex:1;background:#f5c800"></div>
      <div style="flex:1;background:#0039a6"></div>
      <div style="position:absolute;inset:0;background:linear-gradient(120deg,rgba(255,255,255,.18) 0%,transparent 60%);border-radius:6px;pointer-events:none"></div>
    </div>
  </div>

  <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>

  <div id="formSection">

    <!-- ── SELECTOR 4 TIPOS ── -->
    <div class="tipo-selector">
      <button class="tipo-btn active" onclick="setTipo('candidato',this,'active')">
        <span class="tipo-icon">👤</span>Soy candidato
      </button>
      <button class="tipo-btn" onclick="setTipo('empresa',this,'active-azul')">
        <span class="tipo-icon">🏢</span>Soy empresa
      </button>
      <button class="tipo-btn" onclick="setTipo('negocio',this,'active-tierra')">
        <span class="tipo-icon">🏪</span>Tengo un negocio
      </button>
      <button class="tipo-btn" onclick="setTipo('servicio',this,'active-dorado')">
        <span class="tipo-icon">🎪</span>Ofrezco servicios
      </button>
    </div>
    <input type="hidden" id="tipo" value="candidato">

    <!-- ── DATOS COMUNES (todos los tipos) ── -->
    <div class="sec-title">👤 Datos personales</div>
    <div class="row">
      <div class="grupo"><label>Nombre <span class="req">*</span></label>
        <input type="text" id="nombre" placeholder="Tu nombre" oninput="updateProgress()"></div>
      <div class="grupo" id="grupoApellido"><label>Apellido <span class="req">*</span></label>
        <input type="text" id="apellido" placeholder="Tu apellido" oninput="updateProgress()"></div>
    </div>
    <div class="grupo full"><label>Correo electrónico <span class="req">*</span></label>
      <input type="email" id="correo" placeholder="correo@ejemplo.com" oninput="updateProgress()"></div>
    <div class="row">
      <div class="grupo"><label>Teléfono</label>
        <input type="tel" id="telefono" placeholder="300 123 4567"></div>
      <div class="grupo"><label>Ciudad / Municipio</label>
        <input type="text" id="ciudad" placeholder="Ej: Quibdó"></div>
    </div>
    <div class="grupo full">
      <label>Contraseña <span class="req">*</span></label>
      <div class="pass-wrap">
        <input type="password" id="contrasena" placeholder="Mínimo 8 caracteres" oninput="updateProgress()">
        <button type="button" class="toggle-pass" onclick="togglePass('contrasena',this)">👁</button>
      </div>
    </div>
    <div class="grupo full">
      <label>Confirmar contraseña <span class="req">*</span></label>
      <div class="pass-wrap">
        <input type="password" id="contrasena2" placeholder="Repite tu contraseña" oninput="updateProgress()">
        <button type="button" class="toggle-pass" onclick="togglePass('contrasena2',this)">👁</button>
      </div>
    </div>

    <!-- ══════════════════════════════════════════
         CAMPOS CANDIDATO
    ══════════════════════════════════════════ -->
    <div id="camposCandidato" class="campos-tipo show">
      <div class="sec-title">🪪 Identidad & perfil</div>
      <div class="grupo full">
        <label>Fecha de nacimiento <span class="req">*</span></label>
        <input type="date" id="fecha_nacimiento" max="2009-03-23" style="color-scheme:dark">
      </div>
      <div class="row">
        <div class="grupo">
          <label>Tipo de documento <span class="req">*</span></label>
          <select id="tipo_documento" onchange="actualizarPlaceholderDoc()">
            <option value="">Selecciona</option>
            <option value="cedula">🪪 Cédula de ciudadanía</option>
            <option value="tarjeta_identidad">🪪 Tarjeta de identidad</option>
            <option value="pasaporte">🌍 Pasaporte</option>
            <option value="licencia_conduccion">🚗 Licencia de conducción</option>
          </select>
        </div>
        <div class="grupo">
          <label>Número de documento <span class="req">*</span></label>
          <input type="text" id="cedula" placeholder="Selecciona tipo primero" maxlength="20" oninput="limpiarNumeroDoc(this)">
        </div>
      </div>
      <div class="grupo full">
        <label>Foto o PDF del documento <span class="req">*</span></label>
        <div class="upload-doc-area" id="uploadDocArea" onclick="document.getElementById('doc_cedula').click()">
          <div id="uploadDocPlaceholder">
            <div style="font-size:36px;margin-bottom:8px" id="uploadDocEmoji">🪪</div>
            <div style="font-weight:600;font-size:14px" id="uploadDocTexto">Sube tu documento</div>
            <div style="font-size:12px;color:rgba(255,255,255,.4);margin-top:4px">JPG, PNG o PDF · máx 5MB</div>
          </div>
          <div id="uploadDocPreview" style="display:none;align-items:center;gap:10px">
            <span style="font-size:24px" id="docIcon">📄</span>
            <div><div style="font-weight:600;font-size:13px" id="docNombre"></div>
              <div style="font-size:11px;color:rgba(255,255,255,.4)" id="docTamanio"></div></div>
            <button type="button" onclick="quitarDoc(event)" style="margin-left:auto;background:rgba(255,68,68,.2);border:none;color:#ff4444;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12px">✕</button>
          </div>
        </div>
        <input type="file" id="doc_cedula" accept="image/jpeg,image/png,image/webp,application/pdf" style="display:none" onchange="previsualizarDoc(this)">
        <input type="hidden" id="tipo_documento_hidden" name="tipo_documento">
      </div>

      <!-- Perfil profesional del candidato -->
      <div class="sec-title">💼 Perfil profesional</div>
      <div class="grupo full">
        <label>¿Cuál es tu área o perfil?</label>
        <select id="profesion_tipo">
          <option value="">— Selecciona tu área —</option>
          <optgroup label="🎓 Carreras universitarias & técnicas">
            <option>Ingeniería de Sistemas / Software</option>
            <option>Ingeniería Civil</option>
            <option>Ingeniería Industrial</option>
            <option>Medicina / Ciencias de la Salud</option>
            <option>Enfermería</option>
            <option>Odontología</option>
            <option>Bacteriología / Laboratorio Clínico</option>
            <option>Farmacia</option>
            <option>Psicología</option>
            <option>Derecho / Jurisprudencia</option>
            <option>Administración de Empresas</option>
            <option>Contaduría Pública</option>
            <option>Economía / Finanzas</option>
            <option>Licenciatura en Educación</option>
            <option>Trabajo Social</option>
            <option>Comunicación Social / Periodismo</option>
            <option>Diseño Gráfico / Publicidad</option>
            <option>Arquitectura</option>
            <option>Agronomía / Ingeniería Forestal</option>
            <option>Biología / Ciencias Ambientales</option>
            <option>Tecnología en Sistemas</option>
            <option>Tecnología en Construcción</option>
            <option>Técnico en Electricidad</option>
            <option>Técnico en Electrónica</option>
            <option>Técnico en Mecánica</option>
            <option>Técnico en Salud Ocupacional</option>
            <option>Técnico en Gastronomía</option>
          </optgroup>
          <optgroup label="🎵 Arte, Música & Cultura del Chocó">
            <option>DJ / Disc Jockey</option>
            <option>Músico – Chirimía</option>
            <option>Músico – Marimba / Percusión</option>
            <option>Músico – Trompeta / Vientos</option>
            <option>Cantante / Vocalista</option>
            <option>Cantautor(a)</option>
            <option>Productor(a) Musical</option>
            <option>Sonidista / Técnico de Audio</option>
            <option>Bailarín(a) – Currulao / Danzas afro</option>
            <option>Coreógrafo(a)</option>
            <option>Actor / Actriz</option>
            <option>Artista Plástico(a) / Pintor(a)</option>
            <option>Escultor(a) / Ceramista</option>
            <option>Artesano(a) – Tagua, Madera, Fibras</option>
            <option>Fotógrafo(a)</option>
            <option>Videógrafo(a) / Camarógrafo(a)</option>
            <option>Animador(a) de eventos / Maestro(a) de ceremonias</option>
            <option>Locutor(a) / Presentador(a)</option>
            <option>Narrador(a) / Poeta / Escritor(a)</option>
            <option>Gestor(a) Cultural</option>
            <option>Promotor(a) de Eventos</option>
            <option>Tatuador(a)</option>
            <option>Maquillador(a) Artístico</option>
          </optgroup>
          <optgroup label="🔧 Oficios & Técnicos">
            <option>Electricista</option>
            <option>Plomero(a)</option>
            <option>Albañil / Constructor</option>
            <option>Carpintero(a) / Ebanista</option>
            <option>Mecánico(a)</option>
            <option>Conductor(a) / Transportador(a)</option>
            <option>Vigilante / Guardia de Seguridad</option>
            <option>Agricultor(a)</option>
            <option>Pescador(a)</option>
            <option>Minero(a)</option>
          </optgroup>
          <optgroup label="💅 Belleza & Bienestar">
            <option>Peluquero(a) / Estilista</option>
            <option>Barbero(a)</option>
            <option>Esteticista / Cosmetóloga</option>
            <option>Masajista / Terapista</option>
            <option>Especialista en uñas</option>
          </optgroup>
          <optgroup label="📱 Digital & Tecnología">
            <option>Desarrollador(a) Web / Móvil</option>
            <option>Community Manager</option>
            <option>Diseñador(a) UX/UI</option>
            <option>Soporte Técnico / Helpdesk</option>
            <option>Analista de Datos</option>
            <option>Editor(a) de Video</option>
          </optgroup>
          <option value="otro">Otro (especificar)</option>
        </select>
      </div>
      <!-- Campo "Otro" visible solo cuando se elige Otro -->
      <div class="grupo full" id="grupoOtraArea" style="display:none">
        <label>Especifica tu área o perfil <span class="req">*</span></label>
        <input type="text" id="otra_area" placeholder="Escribe tu área o profesión">
      </div>
    </div>

    <!-- ══════════════════════════════════════════
         CAMPOS EMPRESA
    ══════════════════════════════════════════ -->
    <div id="camposEmpresa" class="campos-tipo">
      <div class="sec-title">🏢 Datos de la empresa</div>
      <div class="grupo full">
        <label>Razón social / Nombre legal <span class="req">*</span></label>
        <input type="text" id="nombre_empresa" placeholder="Nombre oficial ante la DIAN">
      </div>
      <div class="grupo full">
        <label>Nombre comercial (si es diferente)</label>
        <input type="text" id="razon_social" placeholder="Nombre con el que te conocen">
      </div>
      <div class="row">
        <div class="grupo">
          <label>Tipo de empresa</label>
          <select id="tipo_empresa_reg">
            <option value="">Selecciona</option>
            <optgroup label="Por naturaleza jurídica">
              <option>Persona Natural</option>
              <option>Persona Jurídica</option>
              <option>Sociedad S.A.S.</option>
              <option>Sociedad Limitada (S.R.L.)</option>
              <option>Empresa Unipersonal (E.U.)</option>
              <option>Cooperativa</option>
              <option>Fundación / ONG</option>
              <option>Entidad Pública</option>
            </optgroup>
            <optgroup label="Por tamaño">
              <option>Microempresa (1–10 empleados)</option>
              <option>Pequeña empresa (11–50 empleados)</option>
              <option>Mediana empresa (51–200 empleados)</option>
              <option>Gran empresa (200+ empleados)</option>
            </optgroup>
          </select>
        </div>
        <div class="grupo">
          <label>NIT</label>
          <input type="text" id="nit" placeholder="900.123.456-7">
        </div>
      </div>
      <div class="row">
        <div class="grupo">
          <label>Sector económico <span class="req">*</span></label>
          <select id="sector">
            <option value="">Selecciona</option>
            <option>Tecnología & Sistemas</option>
            <option>Salud & Medicina</option>
            <option>Educación & Formación</option>
            <option>Construcción & Infraestructura</option>
            <option>Comercio & Retail</option>
            <option>Servicios & Consultoría</option>
            <option>Finanzas & Banca</option>
            <option>Agro & Medio Ambiente</option>
            <option>Minería</option>
            <option>Transporte & Logística</option>
            <option>Gastronomía & Turismo</option>
            <option>Arte & Cultura</option>
            <option>Medios & Comunicación</option>
            <option>Gobierno & Sector Público</option>
            <option>Otro</option>
          </select>
        </div>
        <div class="grupo">
          <label>N.º de empleados</label>
          <select id="num_empleados">
            <option value="">Selecciona</option>
            <option>1 (solo yo)</option>
            <option>2–5</option>
            <option>6–10</option>
            <option>11–50</option>
            <option>51–200</option>
            <option>200+</option>
          </select>
        </div>
      </div>
      <div class="row">
        <div class="grupo">
          <label>Representante legal</label>
          <input type="text" id="rep_legal" placeholder="Nombre completo">
        </div>
        <div class="grupo">
          <label>Fecha de fundación</label>
          <input type="date" id="fecha_fundacion" style="color-scheme:dark">
        </div>
      </div>
      <div class="row">
        <div class="grupo">
          <label>Municipio del Chocó</label>
          <input type="text" id="municipio_empresa" placeholder="Ej: Quibdó, Istmina…">
        </div>
        <div class="grupo">
          <label>Sitio web / Red social <span class="req">*</span></label>
          <input type="url" id="sitio_web" placeholder="https://miempresa.com">
        </div>
      </div>
      <div class="grupo full">
        <label>N.º Cámara de Comercio <span class="req">*</span></label>
        <input type="text" id="camara_comercio" placeholder="Matrícula mercantil">
      </div>
      <div class="info-box">
        📋 <strong>Documentos requeridos para verificación:</strong><br>
        RUT, Cámara de Comercio (si aplica) o cédula del representante legal. El administrador los solicitará al aprobar tu solicitud.
      </div>
    </div>

    <!-- ══════════════════════════════════════════
         CAMPOS NEGOCIO (independiente o C.C.)
    ══════════════════════════════════════════ -->
    <div id="camposNegocio" class="campos-tipo">
      <div class="sec-title">🏪 Datos del negocio</div>
      <!-- Tipo de negocio -->
      <div class="grupo full">
        <label>¿Qué tipo de negocio tienes? <span class="req">*</span></label>
        <div style="display:flex;gap:10px;margin-top:4px;flex-wrap:wrap">
          <label style="flex:1;cursor:pointer;background:rgba(255,255,255,.05);border:2px solid rgba(255,255,255,.12);border-radius:12px;padding:14px;text-align:center;transition:all .25s" id="lbl-neg-emp">
            <input type="radio" name="tipo_negocio_reg" id="neg_emp" value="emp" checked onchange="toggleNegocioTipo()" style="display:none">
            <span style="font-size:22px;display:block;margin-bottom:4px">🏪</span>
            <span style="font-size:13px;font-weight:700;color:rgba(255,255,255,.8)">Negocio independiente</span>
            <span style="font-size:11px;color:rgba(255,255,255,.4);display:block;margin-top:3px">Tienda, local propio, emprendimiento</span>
          </label>
          <label style="flex:1;cursor:pointer;background:rgba(255,255,255,.05);border:2px solid rgba(255,255,255,.12);border-radius:12px;padding:14px;text-align:center;transition:all .25s" id="lbl-neg-cc">
            <input type="radio" name="tipo_negocio_reg" id="neg_cc" value="cc" onchange="toggleNegocioTipo()" style="display:none">
            <span style="font-size:22px;display:block;margin-bottom:4px">🏬</span>
            <span style="font-size:13px;font-weight:700;color:rgba(255,255,255,.8)">Local C.C. El Caraño</span>
            <span style="font-size:11px;color:rgba(255,255,255,.4);display:block;margin-top:3px">Tienes un local dentro del C.C.</span>
          </label>
          <label style="flex:1;cursor:pointer;background:rgba(255,255,255,.05);border:2px solid rgba(255,255,255,.12);border-radius:12px;padding:14px;text-align:center;transition:all .25s" id="lbl-neg-virtual">
            <input type="radio" name="tipo_negocio_reg" id="neg_virtual" value="virtual" onchange="toggleNegocioTipo()" style="display:none">
            <span style="font-size:22px;display:block;margin-bottom:4px">💻</span>
            <span style="font-size:13px;font-weight:700;color:rgba(255,255,255,.8)">Negocio Virtual</span>
            <span style="font-size:11px;color:rgba(255,255,255,.4);display:block;margin-top:3px">Solo online / redes sociales</span>
          </label>
        </div>
      </div>
      <!-- Campos C.C. (solo si cc) -->
      <div id="camposCC" style="display:none">
        <div class="row">
          <div class="grupo">
            <label>Nombre del C.C.</label>
            <input type="text" id="nombre_cc" value="C.C. El Caraño" placeholder="C.C. El Caraño">
          </div>
          <div class="grupo">
            <label>N.º Local / Piso</label>
            <input type="text" id="local_numero" placeholder="Ej: Local 12 – Piso 1">
          </div>
        </div>
      </div>
      <div class="grupo full">
        <label>Nombre del negocio <span class="req">*</span></label>
        <input type="text" id="nombre_negocio" placeholder="Ej: Asados Don Ramón">
      </div>
      <div class="grupo full">
        <label>Categoría del negocio <span class="req">*</span></label>
        <select id="categoria_neg">
          <option value="">— Selecciona categoría —</option>
          <optgroup label="🍽️ Gastronomía">
            <option>Restaurante / Asadero</option>
            <option>Comida rápida</option>
            <option>Panadería / Pastelería</option>
            <option>Cafetería / Heladería</option>
            <option>Comida chocoana tradicional</option>
            <option>Catering / Banquetes</option>
          </optgroup>
          <optgroup label="👗 Moda & Accesorios">
            <option>Ropa & Moda</option>
            <option>Calzado</option>
            <option>Joyería / Bisutería</option>
            <option>Bolsos & Accesorios</option>
          </optgroup>
          <optgroup label="💊 Salud & Bienestar">
            <option>Droguería / Farmacia</option>
            <option>Óptica</option>
            <option>Centro médico / Consultorio</option>
            <option>Gym / Centro de bienestar</option>
          </optgroup>
          <optgroup label="✂️ Belleza & Estética">
            <option>Peluquería / Barbería</option>
            <option>Centro de estética</option>
            <option>Spa / Masajes</option>
            <option>Manicure / Pedicure</option>
          </optgroup>
          <optgroup label="🔧 Ferretería & Construcción">
            <option>Ferretería</option>
            <option>Materiales de construcción</option>
            <option>Pinturas & Acabados</option>
            <option>Electricidad / Fontanería</option>
          </optgroup>
          <optgroup label="📱 Tecnología & Servicios">
            <option>Tecnología / Celulares</option>
            <option>Papelería / Miscelánea</option>
            <option>Librería / Útiles escolares</option>
            <option>Fotocopiadora / Impresión</option>
            <option>Servicio de internet</option>
          </optgroup>
          <optgroup label="🎨 Arte & Cultura">
            <option>Artesanías del Chocó</option>
            <option>Galería de arte</option>
            <option>Música / Instrumentos</option>
            <option>Estudio fotográfico</option>
          </optgroup>
          <optgroup label="🚌 Transporte & Logística">
            <option>Transporte de pasajeros</option>
            <option>Mensajería / Domicilios</option>
            <option>Alquiler de vehículos</option>
          </optgroup>
          <optgroup label="🌿 Agro & Ecoturismo">
            <option>Productos agrícolas / Tienda verde</option>
            <option>Ecoturismo / Guía turístico</option>
            <option>Pesca / Mariscos frescos</option>
          </optgroup>
          <option value="otro">Otro</option>
        </select>
      </div>
      <div class="row">
        <div class="grupo" id="grupoBarrioNegocio">
          <label>Barrio / Dirección</label>
          <input type="text" id="barrio_negocio" placeholder="Barrio Kennedy, Calle 27…">
        </div>
        <div class="grupo">
          <label>WhatsApp del negocio</label>
          <input type="tel" id="whatsapp_neg" placeholder="3001234567">
        </div>
      </div>
      <div class="grupo full">
        <label>Descripción breve</label>
        <textarea id="descripcion_neg" rows="3" placeholder="¿Qué ofreces? ¿Qué te hace especial?"></textarea>
      </div>
      <!-- Fotos del local (solo para negocios físicos) -->
      <div id="camposFotoLocal">
        <div class="sec-title">📸 Fotos del negocio</div>
        <div class="info-box">📌 <strong>Para negocios físicos:</strong> sube una foto de la fachada y una del interior del local. Son obligatorias para la verificación.</div>
        <div class="grupo full">
          <label>Foto de la fachada (frente del negocio) <span class="req">*</span></label>
          <div class="upload-doc-area" id="uploadFachadaArea" onclick="document.getElementById('foto_fachada').click()" style="cursor:pointer">
            <div id="uploadFachadaPlaceholder" style="text-align:center">
              <div style="font-size:32px;margin-bottom:6px">🏪</div>
              <div style="font-weight:600;font-size:14px">Foto del frente</div>
              <div style="font-size:12px;color:rgba(255,255,255,.4);margin-top:4px">JPG, PNG · máx 5MB</div>
            </div>
            <div id="uploadFachadaPreview" style="display:none;align-items:center;gap:10px">
              <span style="font-size:22px">🖼️</span>
              <div><div style="font-weight:600;font-size:13px" id="fachadaNombre"></div></div>
              <button type="button" onclick="quitarFoto('fachada',event)" style="margin-left:auto;background:rgba(255,68,68,.2);border:none;color:#ff4444;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12px">✕</button>
            </div>
          </div>
          <input type="file" id="foto_fachada" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previsualizarFoto(this,'fachada')">
        </div>
        <div class="grupo full">
          <label>Foto del interior del local <span class="req">*</span></label>
          <div class="upload-doc-area" id="uploadInteriorArea" onclick="document.getElementById('foto_interior').click()" style="cursor:pointer">
            <div id="uploadInteriorPlaceholder" style="text-align:center">
              <div style="font-size:32px;margin-bottom:6px">🏬</div>
              <div style="font-weight:600;font-size:14px">Foto del interior</div>
              <div style="font-size:12px;color:rgba(255,255,255,.4);margin-top:4px">JPG, PNG · máx 5MB</div>
            </div>
            <div id="uploadInteriorPreview" style="display:none;align-items:center;gap:10px">
              <span style="font-size:22px">🖼️</span>
              <div><div style="font-weight:600;font-size:13px" id="interiorNombre"></div></div>
              <button type="button" onclick="quitarFoto('interior',event)" style="margin-left:auto;background:rgba(255,68,68,.2);border:none;color:#ff4444;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12px">✕</button>
            </div>
          </div>
          <input type="file" id="foto_interior" accept="image/jpeg,image/png,image/webp" style="display:none" onchange="previsualizarFoto(this,'interior')">
        </div>
      </div>
      <!-- Info negocio virtual (solo para virtual) -->
      <div id="camposNegVirtual" style="display:none">
        <div class="sec-title">🌐 Negocio virtual</div>
        <div class="info-box">💻 <strong>Negocio 100% virtual:</strong> ingresa el link de tu red social o tienda online principal.</div>
        <div class="grupo full">
          <label>Link de red social / tienda online <span class="req">*</span></label>
          <input type="url" id="link_neg_virtual" placeholder="https://instagram.com/minegocio">
        </div>
      </div>
      <!-- Documento del dueño -->
      <div class="sec-title">🪪 Documento del propietario</div>
      <div class="row">
        <div class="grupo">
          <label>Tipo de documento <span class="req">*</span></label>
          <select id="tipo_documento_neg" onchange="actualizarDocNeg()">
            <option value="">Selecciona</option>
            <option value="cedula">🪪 Cédula de ciudadanía</option>
            <option value="nit">🏢 NIT</option>
            <option value="pasaporte">🌍 Pasaporte</option>
          </select>
        </div>
        <div class="grupo">
          <label>Número <span class="req">*</span></label>
          <input type="text" id="cedula_neg" placeholder="Número de documento">
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════
         CAMPOS SERVICIO (DJ, fotógrafo, etc.)
         Usa el mismo backend que candidato
         pero con perfil de servicios pre-rellenado
    ══════════════════════════════════════════ -->
    <div id="camposServicio" class="campos-tipo">
      <div class="sec-title">🎧 Mi servicio para eventos</div>
      <div class="grupo full">
        <label>Tipo de servicio <span class="req">*</span></label>
        <select id="profesion_tipo_servicio">
          <option value="">— Selecciona tu servicio —</option>
          <optgroup label="🎵 Música & Entretenimiento">
            <option value="DJ / Disc Jockey">🎧 DJ / Disc Jockey</option>
            <option value="Chirimía – Música tradicional del Chocó">🎺 Chirimía – Música tradicional del Chocó</option>
            <option value="Marimba / Percusión afro">🥁 Marimba / Percusión afro</option>
            <option value="Grupo musical – Salsa / Vallenato / Cumbia">🎸 Grupo musical – Salsa / Vallenato / Cumbia</option>
            <option value="Grupo musical – Champeta / Urbano">🎤 Grupo musical – Champeta / Urbano</option>
            <option value="Cantante / Solista">🎙️ Cantante / Solista</option>
            <option value="Mariachi / Boleros">🎻 Mariachi / Boleros</option>
            <option value="Banda de viento">🎺 Banda de viento</option>
          </optgroup>
          <optgroup label="📸 Foto & Video">
            <option value="Fotógrafo de eventos">📸 Fotógrafo de eventos</option>
            <option value="Videógrafo / Camarógrafo">🎥 Videógrafo / Camarógrafo</option>
            <option value="Fotografía & Video combo">📸🎥 Fotografía & Video combo</option>
            <option value="Drone / Fotografía aérea">🚁 Drone / Fotografía aérea</option>
          </optgroup>
          <optgroup label="🍽️ Gastronomía">
            <option value="Catering – Comida chocoana">🍖 Catering – Comida chocoana</option>
            <option value="Catering – Mariscos / Pescados">🐟 Catering – Mariscos / Pescados</option>
            <option value="Catering – Internacional">🌮 Catering – Internacional</option>
            <option value="Repostería / Tortas para eventos">🎂 Repostería / Tortas para eventos</option>
            <option value="Bartender / Coctelería">🍹 Bartender / Coctelería</option>
          </optgroup>
          <optgroup label="🌸 Decoración & Ambientación">
            <option value="Decoración de eventos">🌸 Decoración de eventos</option>
            <option value="Florería / Arreglos florales">💐 Florería / Arreglos florales</option>
            <option value="Alquiler de carpas / Mobiliario">⛺ Alquiler de carpas / Mobiliario</option>
            <option value="Iluminación de eventos">💡 Iluminación de eventos</option>
          </optgroup>
          <optgroup label="🎤 Animación & Protocolo">
            <option value="Maestro(a) de ceremonias">🎤 Maestro(a) de ceremonias</option>
            <option value="Animador(a) infantil">🎈 Animador(a) infantil</option>
            <option value="Show de comedia / Humor">🤣 Show de comedia / Humor</option>
            <option value="Mago / Ilusionista">🎩 Mago / Ilusionista</option>
          </optgroup>
          <optgroup label="💄 Belleza para eventos">
            <option value="Maquillaje artístico / de bodas">💄 Maquillaje artístico / de bodas</option>
            <option value="Peinados para eventos">💇 Peinados para eventos</option>
          </optgroup>
          <optgroup label="🚐 Transporte & Logística">
            <option value="Transporte de invitados">🚐 Transporte de invitados</option>
            <option value="Seguridad de eventos">🛡️ Seguridad de eventos</option>
          </optgroup>
        </select>
      </div>
      <div class="row">
        <div class="grupo">
          <label>Precio desde (COP) <span class="req">*</span></label>
          <input type="number" id="precio_desde_serv" placeholder="Ej: 250000">
        </div>
        <div class="grupo">
          <label>Unidad de precio</label>
          <select id="unidad_precio">
            <option value="/evento">/evento</option>
            <option value="/hora">/hora</option>
            <option value="/presentación">/presentación</option>
            <option value="/persona">/persona</option>
            <option value="/día">/día</option>
          </select>
        </div>
      </div>
      <div class="grupo full">
        <label>Géneros / Especialidades <span style="color:rgba(255,255,255,.4);font-size:11px">(separados por coma)</span></label>
        <input type="text" id="generos_serv" placeholder="Ej: Champeta · Salsa · Afrobeats · Chirimía">
      </div>
      <!-- Documento -->
      <div class="sec-title">🪪 Documento de identidad</div>
      <div class="row">
        <div class="grupo">
          <label>Tipo de documento <span class="req">*</span></label>
          <select id="tipo_doc_serv" onchange="actualizarDocServ()">
            <option value="">Selecciona</option>
            <option value="cedula">🪪 Cédula de ciudadanía</option>
            <option value="pasaporte">🌍 Pasaporte</option>
          </select>
        </div>
        <div class="grupo">
          <label>Número <span class="req">*</span></label>
          <input type="text" id="cedula_serv" placeholder="Número de documento">
        </div>
      </div>
      <div class="grupo full">
        <label>Fecha de nacimiento <span class="req">*</span></label>
        <input type="date" id="fecha_nac_serv" max="2009-03-23" style="color-scheme:dark">
      </div>
      <div class="info-box">
        🎧 <strong>¿Cómo funciona?</strong> Regístrate, el administrador revisará tu perfil y activará tu tarjeta en la sección "Servicios para Eventos" con tu precio y géneros.
      </div>
    </div>

    <!-- ── TÉRMINOS Y ENVÍO ── -->
    <div class="terminos">
      <input type="checkbox" id="terminos">
      <label for="terminos" style="margin:0;color:rgba(255,255,255,.6)">
        Acepto los <a href="#">Términos de uso</a> y la <a href="#">Política de privacidad</a>
      </label>
    </div>

    <button class="btn-registro" id="btnRegistro" onclick="registrar()">📋 Enviar solicitud</button>
    <div class="msg" id="msg"></div>
    <div class="link-abajo">¿Ya tienes cuenta? <a href="inicio_sesion.php">Inicia sesión</a></div>
  </div>

  <!-- SUCCESS -->
  <div class="success-screen" id="successScreen">
    <div class="big-icon" id="successIcon">⏳</div>
    <h2 id="successNombre">¡Solicitud enviada!</h2>
    <p id="successMsg">Tu solicitud fue recibida. El administrador la revisará y recibirás acceso una vez aprobada.</p>
    <div class="btns-success">
      <a href="index.html" class="btn-ir">Ir al inicio →</a>
      <a href="inicio_sesion.php" class="btn-ir secundario">Ya fui aprobado</a>
    </div>
  </div>
</div>

<script>
  
  const canvas = document.getElementById('canvas-bg'), ctx = canvas.getContext('2d');
  canvas.width = window.innerWidth; canvas.height = window.innerHeight;
  window.addEventListener('resize',()=>{canvas.width=window.innerWidth;canvas.height=window.innerHeight;});
  const emojis=['🌿','🌊','⭐','🎺','🥁','🌴','🦋','🌸','✨','🍃','🎧','🌺','💼','🎵'];
  const parts=[];
  for(let i=0;i<50;i++) parts.push({x:Math.random()*canvas.width,y:Math.random()*canvas.height-canvas.height,e:emojis[Math.floor(Math.random()*emojis.length)],s:Math.random()*18+8,vy:Math.random()*1.1+0.3,vx:(Math.random()-.5)*.5,r:Math.random()*Math.PI*2,rs:(Math.random()-.5)*.04,o:Math.random()*.45+.1});
  (function anim(){ctx.clearRect(0,0,canvas.width,canvas.height);parts.forEach(p=>{ctx.save();ctx.globalAlpha=p.o;ctx.translate(p.x,p.y);ctx.rotate(p.r);ctx.font=p.s+'px serif';ctx.fillText(p.e,0,0);ctx.restore();p.y+=p.vy;p.x+=p.vx;p.r+=p.rs;if(p.y>canvas.height+20){p.y=-20;p.x=Math.random()*canvas.width;}});requestAnimationFrame(anim);})();

  const activeClasses = {candidato:'active', empresa:'active-azul', negocio:'active-tierra', servicio:'active-dorado'};
  function setTipo(tipo, btn, cls) {
    document.querySelectorAll('.tipo-btn').forEach(b=>b.className='tipo-btn');
    btn.classList.add(cls);
    document.getElementById('tipo').value = tipo === 'servicio' ? 'candidato' : tipo; 
    
    document.getElementById('camposCandidato').classList.toggle('show', tipo==='candidato');
    document.getElementById('camposEmpresa').classList.toggle('show',   tipo==='empresa');
    document.getElementById('camposNegocio').classList.toggle('show',   tipo==='negocio');
    document.getElementById('camposServicio').classList.toggle('show',  tipo==='servicio');
    
    document.getElementById('grupoApellido').style.display = tipo==='empresa' ? 'none' : 'block';
    updateProgress();
  }

  function toggleNegocioTipo() {
    const esCC      = document.getElementById('neg_cc').checked;
    const esVirtual = document.getElementById('neg_virtual').checked;
    const esFisico  = !esVirtual; 

    document.getElementById('camposCC').style.display = esCC ? 'block' : 'none';
    document.getElementById('camposFotoLocal').style.display  = esFisico ? 'block' : 'none';
    document.getElementById('camposNegVirtual').style.display = esVirtual ? 'block' : 'none';
    document.getElementById('grupoBarrioNegocio').style.display = esVirtual ? 'none' : 'block';

    document.getElementById('lbl-neg-emp').style.borderColor     = (!esCC && !esVirtual) ? '#b45309' : 'rgba(255,255,255,.12)';
    document.getElementById('lbl-neg-cc').style.borderColor      = esCC      ? '#b45309' : 'rgba(255,255,255,.12)';
    document.getElementById('lbl-neg-virtual').style.borderColor = esVirtual ? '#1a56db' : 'rgba(255,255,255,.12)';
  }
  toggleNegocioTipo(); 

  document.getElementById('profesion_tipo').addEventListener('change', function() {
    const esOtro = this.value === 'otro';
    document.getElementById('grupoOtraArea').style.display = esOtro ? 'block' : 'none';
    if (!esOtro) document.getElementById('otra_area').value = '';
  });

  function previsualizarFoto(input, tipo) {
    const file = input.files[0]; if (!file) return;
    if (file.size > 5*1024*1024) { showMsg('La foto no debe superar 5MB.','error'); input.value=''; return; }
    const nombre = file.name;
    if (tipo === 'fachada') {
      document.getElementById('uploadFachadaPlaceholder').style.display = 'none';
      document.getElementById('uploadFachadaPreview').style.display = 'flex';
      document.getElementById('fachadaNombre').textContent = nombre;
      document.getElementById('uploadFachadaArea').classList.add('tiene-archivo');
    } else {
      document.getElementById('uploadInteriorPlaceholder').style.display = 'none';
      document.getElementById('uploadInteriorPreview').style.display = 'flex';
      document.getElementById('interiorNombre').textContent = nombre;
      document.getElementById('uploadInteriorArea').classList.add('tiene-archivo');
    }
  }
  function quitarFoto(tipo, e) {
    e.stopPropagation();
    if (tipo === 'fachada') {
      document.getElementById('foto_fachada').value = '';
      document.getElementById('uploadFachadaPlaceholder').style.display = 'block';
      document.getElementById('uploadFachadaPreview').style.display = 'none';
      document.getElementById('uploadFachadaArea').classList.remove('tiene-archivo');
    } else {
      document.getElementById('foto_interior').value = '';
      document.getElementById('uploadInteriorPlaceholder').style.display = 'block';
      document.getElementById('uploadInteriorPreview').style.display = 'none';
      document.getElementById('uploadInteriorArea').classList.remove('tiene-archivo');
    }
  }

  const docConfig = {
    cedula:            {emoji:'🪪',texto:'Sube tu cédula',           placeholder:'Ej: 1234567890', soloNum:true},
    tarjeta_identidad: {emoji:'🪪',texto:'Sube tu tarjeta de identidad',placeholder:'Ej: 1234567890', soloNum:true},
    pasaporte:         {emoji:'🌍',texto:'Sube tu pasaporte',         placeholder:'Ej: AA123456',  soloNum:false},
    licencia_conduccion:{emoji:'🚗',texto:'Sube tu licencia',         placeholder:'Ej: 123456789', soloNum:true},
  };
  function actualizarPlaceholderDoc() {
    const t = document.getElementById('tipo_documento').value;
    const c = document.getElementById('cedula');
    const cfg = docConfig[t];
    if(cfg){
      c.placeholder = cfg.placeholder;
      document.getElementById('uploadDocEmoji').textContent = cfg.emoji;
      document.getElementById('uploadDocTexto').textContent = cfg.texto;
      document.getElementById('tipo_documento_hidden').value = t;
      c.disabled = false;
    } else { c.placeholder='Selecciona tipo primero'; c.disabled=true; }
    c.value='';
  }
  function actualizarDocNeg() {
    const t = document.getElementById('tipo_documento_neg').value;
    document.getElementById('tipo_documento_hidden').value = t;
  }
  function actualizarDocServ() {
    document.getElementById('tipo_documento_hidden').value = document.getElementById('tipo_doc_serv').value;
  }
  function limpiarNumeroDoc(input) {
    const t = document.getElementById('tipo_documento').value;
    const cfg = docConfig[t];
    if(cfg && cfg.soloNum) input.value = input.value.replace(/[^0-9]/g,'');
  }
  function previsualizarDoc(input) {
    const file = input.files[0]; if(!file) return;
    if(file.size > 5*1024*1024){ showMsg('El archivo no debe superar 5MB.','error'); input.value=''; return; }
    document.getElementById('uploadDocPlaceholder').style.display='none';
    document.getElementById('uploadDocPreview').style.display='flex';
    document.getElementById('docNombre').textContent=file.name;
    document.getElementById('docTamanio').textContent=(file.size/1024).toFixed(0)+' KB';
    document.getElementById('docIcon').textContent=file.name.split('.').pop().toLowerCase()==='pdf'?'📄':'🖼️';
    document.getElementById('uploadDocArea').classList.add('tiene-archivo');
  }
  function quitarDoc(e) {
    e.stopPropagation();
    document.getElementById('doc_cedula').value='';
    document.getElementById('uploadDocPlaceholder').style.display='block';
    document.getElementById('uploadDocPreview').style.display='none';
    document.getElementById('uploadDocArea').classList.remove('tiene-archivo');
  }

  function togglePass(id,btn){const i=document.getElementById(id);i.type=i.type==='password'?'text':'password';btn.textContent=i.type==='password'?'👁':'🙈';}
  function updateProgress(){
    const base=['nombre','correo','contrasena','contrasena2'];
    const n=base.filter(id=>document.getElementById(id)?.value?.trim()!=='').length;
    document.getElementById('progressFill').style.width=(n/base.length*100)+'%';
  }
  function showMsg(t,c){const e=document.getElementById('msg');e.textContent=t;e.className='msg '+c;e.style.display='block';}

  async function registrar() {
    if(!document.getElementById('terminos').checked){showMsg('Debes aceptar los términos.','error');return;}
    const tipoReal = document.getElementById('tipo').value;
    const tipoUI = document.querySelector('.tipo-btn[class*="active"]')?.textContent?.includes('servicio')?'servicio':tipoReal;

    if(tipoReal==='candidato'){
      if(!document.getElementById('tipo_documento').value){showMsg('Selecciona el tipo de documento.','error');return;}
      if(!document.getElementById('cedula').value.trim()){showMsg('El número de documento es obligatorio.','error');return;}
      if(!document.getElementById('doc_cedula').files[0]){showMsg('Debes subir la foto o PDF de tu documento.','error');return;}
      
      if(document.getElementById('profesion_tipo').value==='otro' && !document.getElementById('otra_area').value.trim()){
        showMsg('Especifica tu área o perfil.','error');return;
      }
    }
    if(tipoReal==='negocio'){
      if(!document.getElementById('nombre_negocio').value.trim()){showMsg('El nombre del negocio es obligatorio.','error');return;}
      if(!document.getElementById('categoria_neg').value){showMsg('Selecciona la categoría del negocio.','error');return;}
      const esVirtualNeg = document.getElementById('neg_virtual').checked;
      if(!esVirtualNeg){
        if(!document.getElementById('foto_fachada').files[0]){showMsg('Sube la foto de la fachada del negocio.','error');return;}
        if(!document.getElementById('foto_interior').files[0]){showMsg('Sube la foto del interior del local.','error');return;}
      } else {
        if(!document.getElementById('link_neg_virtual').value.trim()){showMsg('El link del negocio virtual es obligatorio.','error');return;}
      }
    }
    if(tipoReal==='empresa'){
      if(!document.getElementById('nombre_empresa').value.trim()){showMsg('El nombre de la empresa es obligatorio.','error');return;}
      if(!document.getElementById('sector').value){showMsg('Selecciona el sector económico.','error');return;}
      if(!document.getElementById('camara_comercio').value.trim()){showMsg('El N.º de Cámara de Comercio es obligatorio.','error');return;}
      if(!document.getElementById('sitio_web').value.trim()){showMsg('El sitio web o red social es obligatorio para empresas.','error');return;}
    }

    const btn = document.getElementById('btnRegistro');
    btn.disabled=true; btn.textContent='⏳ Enviando...';

    const data = new FormData();
    
    const campos=['nombre','apellido','correo','contrasena','contrasena2','telefono','ciudad','tipo','tipo_documento_hidden'];
    campos.forEach(id=>{const el=document.getElementById(id);if(el) data.append(id,el.value);});

    if(tipoReal==='candidato'){
      ['fecha_nacimiento','cedula'].forEach(id=>{const el=document.getElementById(id);if(el) data.append(id,el.value);});
      
      const areaVal = document.getElementById('profesion_tipo').value;
      if(areaVal==='otro'){
        data.append('profesion_tipo', document.getElementById('otra_area').value.trim());
      } else {
        data.append('profesion_tipo', areaVal);
      }
      const f=document.getElementById('doc_cedula'); if(f&&f.files[0]) data.append('doc_cedula',f.files[0]);
    }
    if(tipoReal==='empresa'){
      ['nombre_empresa','razon_social','sector','nit','tipo_empresa_reg','num_empleados','rep_legal',
       'fecha_fundacion','municipio_empresa','sitio_web','camara_comercio'
      ].forEach(id=>{const el=document.getElementById(id);if(el) data.append(id,el.value);});
    }
    if(tipoReal==='negocio'){
      const esVirtualNeg = document.getElementById('neg_virtual').checked;
      const esCC_neg     = document.getElementById('neg_cc').checked;
      const tipoNeg = esVirtualNeg ? 'virtual' : (esCC_neg ? 'cc' : 'emp');
      data.append('tipo_negocio_reg', tipoNeg);
      ['nombre_negocio','categoria_neg','nombre_cc','local_numero','barrio_negocio',
       'whatsapp_neg','descripcion_neg'
      ].forEach(id=>{const el=document.getElementById(id);if(el) data.append(id,el.value);});
      
      if(!esVirtualNeg){
        const ff=document.getElementById('foto_fachada'); if(ff&&ff.files[0]) data.append('foto_fachada',ff.files[0]);
        const fi=document.getElementById('foto_interior'); if(fi&&fi.files[0]) data.append('foto_interior',fi.files[0]);
      } else {
        const lv=document.getElementById('link_neg_virtual'); if(lv) data.append('link_neg_virtual',lv.value);
      }
      
      if(document.getElementById('profesion_tipo')?.value==='otro'){
        data.append('profesion_tipo', document.getElementById('otra_area').value.trim());
      }
      
      const tipDocNeg=document.getElementById('tipo_documento_neg').value;
      const cedulaNeg=document.getElementById('cedula_neg').value;
      data.set('tipo_documento_hidden',tipDocNeg);
      data.append('cedula',cedulaNeg);
    }
    
    if(document.getElementById('camposServicio').classList.contains('show')){
      const profServ=document.getElementById('profesion_tipo_servicio').value;
      const precioServ=document.getElementById('precio_desde_serv').value;
      const unidad=document.getElementById('unidad_precio').value;
      const generosServ=document.getElementById('generos_serv').value;
      const fechaNacServ=document.getElementById('fecha_nac_serv').value;
      const cedulaServ=document.getElementById('cedula_serv').value;
      const tipDocServ=document.getElementById('tipo_doc_serv').value;
      data.append('profesion_tipo', profServ + (unidad?' ('+unidad+')':''));
      data.append('precio_desde_neg', precioServ + unidad);
      data.append('descripcion_neg', generosServ);
      data.append('fecha_nacimiento', fechaNacServ);
      data.append('cedula', cedulaServ);
      data.set('tipo_documento_hidden', tipDocServ);
    }

    try {
      const res = await fetch('registro.php',{method:'POST',body:data});
      const json = await res.json();
      if(json.ok){
        document.getElementById('formSection').style.display='none';
        document.getElementById('successIcon').textContent='⏳';
        document.getElementById('successNombre').textContent='¡Solicitud enviada, '+json.nombre+'!';
        document.getElementById('successMsg').textContent='Tu solicitud fue recibida. El administrador la revisará y te notificará cuando tu cuenta esté lista.';
        document.getElementById('successScreen').style.display='block';
      } else {
        showMsg(json.msg,'error');
        btn.disabled=false; btn.textContent='📋 Enviar solicitud';
      }
    } catch(e){
      showMsg('Error de conexión.','error');
      btn.disabled=false; btn.textContent='📋 Enviar solicitud';
    }
  }
  document.addEventListener('keypress',e=>{if(e.key==='Enter') registrar();});
</script>
</body>
</html>