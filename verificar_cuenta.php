<?php

session_start();
require_once __DIR__ . '/Php/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: inicio_sesion.php');
    exit;
}

$db     = getDB();
$userId = (int)$_SESSION['usuario_id'];

$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
$stmt->execute([$userId]);
$usuario = $stmt->fetch();

if (!$usuario) {
    session_destroy();
    header('Location: inicio_sesion.php');
    exit;
}

$stmt = $db->prepare("SELECT * FROM verificaciones WHERE usuario_id = ?");
$stmt->execute([$userId]);
$verificacion = $stmt->fetch();

$stmt2 = $db->prepare("SELECT verificado FROM talento_perfil WHERE usuario_id = ?");
$stmt2->execute([$userId]);
$tp = $stmt2->fetch();
$estaVerificado = $tp && $tp['verificado'] == 1;

$tipo = $usuario['tipo'] ?? 'candidato';
$esEmpresa = $tipo === 'empresa';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar mi cuenta – QuibdóConecta</title>
    <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --verde:  #1f9d55;
            --verde2: #2ecc71;
            --dorado: #d4a017;
            --azul:   #2563eb;
            --oscuro: #0a0f1e;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'DM Sans',sans-serif;
            background:linear-gradient(135deg,#0f172a 0%,#1a2e1a 50%,#0b3a7e 100%);
            min-height:100vh; color:white; padding:40px 20px;
        }
        .btn-back {
            display:inline-flex; align-items:center; gap:8px;
            background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2);
            color:white; text-decoration:none; padding:10px 18px;
            border-radius:30px; font-size:14px; font-weight:600;
            margin-bottom:32px; transition:all .3s;
        }
        .btn-back:hover { background:rgba(255,255,255,.2); transform:translateX(-3px); }
        .container { max-width:680px; margin:0 auto; }
        h1 { font-family:'Syne',sans-serif; font-size:32px; font-weight:800; margin-bottom:8px; }
        h1 span { color:var(--verde2); }
        .subtitulo { color:rgba(255,255,255,.6); font-size:15px; margin-bottom:40px; }

        .estado-card {
            border-radius:20px; padding:28px; margin-bottom:32px;
            border:1px solid; display:flex; align-items:center; gap:20px;
        }
        .estado-card.pendiente { background:rgba(212,160,23,.1); border-color:rgba(212,160,23,.3); }
        .estado-card.aprobado  { background:rgba(31,157,85,.1);  border-color:rgba(31,157,85,.35); }
        .estado-card.rechazado { background:rgba(239,68,68,.1);  border-color:rgba(239,68,68,.3); }
        .estado-card.ninguno   { background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.12); }
        .estado-icono { font-size:44px; flex-shrink:0; }
        .estado-info h3 { font-size:18px; font-weight:700; margin-bottom:4px; }
        .estado-info p  { font-size:14px; opacity:.75; line-height:1.55; }
        .badge-verificado {
            display:inline-flex; align-items:center; gap:6px;
            background:var(--verde); color:white; padding:6px 16px;
            border-radius:30px; font-size:13px; font-weight:700;
            margin-top:10px;
        }

        .pasos { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:36px; }
        .paso {
            background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
            border-radius:16px; padding:20px 16px; text-align:center;
        }
        .paso .paso-num {
            width:32px; height:32px; border-radius:50%;
            background:linear-gradient(135deg,var(--verde),var(--verde2));
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:14px; margin:0 auto 10px;
        }
        .paso h4 { font-size:13px; font-weight:700; margin-bottom:4px; }
        .paso p  { font-size:12px; opacity:.6; line-height:1.4; }

        .form-card {
            background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
            border-radius:22px; padding:36px;
        }
        .form-card h2 { font-family:'Syne',sans-serif; font-size:22px; margin-bottom:6px; }
        .form-card .form-sub { color:rgba(255,255,255,.55); font-size:14px; margin-bottom:28px; }
        .grupo { margin-bottom:20px; }
        label { display:block; font-size:13px; font-weight:600; color:rgba(255,255,255,.7); margin-bottom:8px; }
        .req { color:#f87171; }
        .upload-area {
            border:2px dashed rgba(255,255,255,.2); border-radius:14px;
            padding:28px; text-align:center; cursor:pointer; transition:all .3s;
            position:relative;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color:var(--verde2); background:rgba(31,157,85,.08);
        }
        .upload-area input[type=file] {
            position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
        }
        .upload-area .up-icono { font-size:36px; margin-bottom:8px; }
        .upload-area .up-texto { font-size:14px; color:rgba(255,255,255,.6); }
        .upload-area .up-texto strong { color:var(--verde2); }
        .upload-area .up-formatos { font-size:12px; color:rgba(255,255,255,.35); margin-top:4px; }
        .upload-preview {
            display:none; align-items:center; gap:12px; margin-top:12px;
            background:rgba(31,157,85,.1); border-radius:10px; padding:12px 16px;
        }
        .upload-preview .prev-nombre { font-size:13px; font-weight:600; flex:1; }
        .upload-preview .prev-quitar {
            background:none; border:none; color:#f87171; cursor:pointer; font-size:18px;
        }
        .tip {
            background:rgba(37,99,235,.12); border:1px solid rgba(37,99,235,.25);
            border-radius:12px; padding:14px 16px; font-size:13px;
            color:rgba(255,255,255,.75); line-height:1.6; margin-bottom:20px;
        }
        .tip strong { color:#93c5fd; }
        .btn-enviar {
            width:100%; padding:15px; border:none; border-radius:14px;
            background:linear-gradient(135deg,var(--verde),var(--verde2));
            color:white; font-size:15px; font-weight:700;
            font-family:'DM Sans',sans-serif; cursor:pointer; transition:all .3s;
            box-shadow:0 6px 20px rgba(31,157,85,.4);
        }
        .btn-enviar:hover { transform:translateY(-2px); }
        .btn-enviar:disabled { opacity:.6; cursor:not-allowed; transform:none; }
        .msg {
            display:none; padding:14px 18px; border-radius:12px;
            font-size:14px; font-weight:500; margin-top:16px;
        }
        .msg.ok    { background:rgba(31,157,85,.15);  border:1px solid rgba(31,157,85,.3);  color:#a7f3d0; }
        .msg.error { background:rgba(239,68,68,.15);  border:1px solid rgba(239,68,68,.3);  color:#fca5a5; }

        @media (max-width:600px) {
            .pasos { grid-template-columns:1fr; }
            .form-card { padding:24px 18px; }
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?= $esEmpresa ? 'dashboard_empresa.php' : 'dashboard.php' ?>" class="btn-back">← Volver a mi panel</a>

    <h1>Verifica tu <span>cuenta</span></h1>
    <p class="subtitulo">
        <?= $esEmpresa
            ? 'Sube tu NIT y Cámara de Comercio para obtener el badge de empresa verificada'
            : 'Sube tu cédula para obtener el badge de candidato verificado' ?>
    </p>

    <?php if ($estaVerificado): ?>
    <!-- YA VERIFICADO -->
    <div class="estado-card aprobado">
        <span class="estado-icono">✅</span>
        <div class="estado-info">
            <h3>¡Tu cuenta está verificada!</h3>
            <p>Ya tienes el badge de cuenta verificada en tu perfil. Las empresas y candidatos pueden confiar en que eres real.</p>
            <span class="badge-verificado">✓ Verificado</span>
        </div>
    </div>

    <?php elseif ($verificacion && $verificacion['estado'] === 'pendiente'): ?>
    <!-- PENDIENTE -->
    <div class="estado-card pendiente">
        <span class="estado-icono">⏳</span>
        <div class="estado-info">
            <h3>Solicitud en revisión</h3>
            <p>Recibimos tus documentos. El equipo de QuibdóConecta los está revisando — en máximo <strong>24 horas</strong> recibirás una respuesta.</p>
        </div>
    </div>

    <?php elseif ($verificacion && $verificacion['estado'] === 'rechazado'): ?>
    <!-- RECHAZADO — puede volver a intentar -->
    <div class="estado-card rechazado">
        <span class="estado-icono">❌</span>
        <div class="estado-info">
            <h3>Solicitud rechazada</h3>
            <p><strong>Motivo:</strong> <?= htmlspecialchars($verificacion['nota_rechazo'] ?? 'Documentos no legibles.') ?></p>
            <p style="margin-top:6px;">Puedes enviar una nueva solicitud con documentos corregidos.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$estaVerificado && (!$verificacion || $verificacion['estado'] === 'rechazado')): ?>
    <!-- PASOS -->
    <div class="pasos">
        <div class="paso">
            <div class="paso-num">1</div>
            <h4>Sube tu documento</h4>
            <p><?= $esEmpresa ? 'NIT y Cámara de Comercio' : 'Foto de tu cédula' ?></p>
        </div>
        <div class="paso">
            <div class="paso-num">2</div>
            <h4>Revisamos</h4>
            <p>El equipo verifica en máximo 24 horas</p>
        </div>
        <div class="paso">
            <div class="paso-num">3</div>
            <h4>Badge activado</h4>
            <p>✓ Verificado aparece en tu perfil</p>
        </div>
    </div>

    <!-- FORMULARIO -->
    <div class="form-card">
        <h2><?= $esEmpresa ? '📋 Documentos de la empresa' : '🪪 Documento de identidad' ?></h2>
        <p class="form-sub">
            <?= $esEmpresa
                ? 'Sube la Cámara de Comercio (máximo 30 días de vigencia) y el NIT'
                : 'Sube una foto clara de tu cédula de ciudadanía por ambas caras' ?>
        </p>

        <div class="tip">
            <strong>💡 Recomendación:</strong>
            <?= $esEmpresa
                ? 'El certificado de existencia y representación legal debe tener máximo 30 días de expedición. Acepta PDF o foto clara.'
                : 'La foto debe ser legible, sin reflejos y con todos los datos visibles. Acepta JPG, PNG o PDF.' ?>
        </div>

        <div class="grupo">
            <label><?= $esEmpresa ? 'Cámara de Comercio o NIT' : 'Foto de cédula' ?> <span class="req">*</span></label>
            <div class="upload-area" id="areaDoc">
                <input type="file" id="inputDoc" accept=".jpg,.jpeg,.png,.pdf"
                    onchange="mostrarPreview(this,'prevDoc','nombreDoc')">
                <div class="up-icono">📄</div>
                <div class="up-texto">Arrastra aquí o <strong>haz clic para seleccionar</strong></div>
                <div class="up-formatos">JPG, PNG o PDF · Máximo 5MB</div>
            </div>
            <div class="upload-preview" id="prevDoc">
                <span>📎</span>
                <span class="prev-nombre" id="nombreDoc"></span>
                <button class="prev-quitar" onclick="quitarArchivo('inputDoc','prevDoc')">✕</button>
            </div>
        </div>

        <div class="grupo">
            <label>Selfie sosteniendo el documento <span style="color:rgba(255,255,255,.4);font-weight:400">(recomendado)</span></label>
            <div class="upload-area" id="areaFoto">
                <input type="file" id="inputFoto" accept=".jpg,.jpeg,.png"
                    onchange="mostrarPreview(this,'prevFoto','nombreFoto')">
                <div class="up-icono">🤳</div>
                <div class="up-texto">Foto tuya con el documento en mano · <strong>Ayuda a aprobar más rápido</strong></div>
                <div class="up-formatos">Solo JPG o PNG · Máximo 5MB</div>
            </div>
            <div class="upload-preview" id="prevFoto">
                <span>🖼️</span>
                <span class="prev-nombre" id="nombreFoto"></span>
                <button class="prev-quitar" onclick="quitarArchivo('inputFoto','prevFoto')">✕</button>
            </div>
        </div>

        <button class="btn-enviar" id="btnEnviar" onclick="enviarSolicitud()">
            ✅ Enviar solicitud de verificación
        </button>
        <div class="msg" id="msg"></div>
    </div>
    <?php endif; ?>

</div>

<script>
function mostrarPreview(input, prevId, nombreId) {
    const archivo = input.files[0];
    if (!archivo) return;
    document.getElementById(nombreId).textContent = archivo.name;
    document.getElementById(prevId).style.display = 'flex';
}

function quitarArchivo(inputId, prevId) {
    document.getElementById(inputId).value = '';
    document.getElementById(prevId).style.display = 'none';
}

function mostrarMsg(texto, tipo) {
    const el = document.getElementById('msg');
    el.textContent = texto;
    el.className = 'msg ' + tipo;
    el.style.display = 'block';
    el.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

async function enviarSolicitud() {
    const doc = document.getElementById('inputDoc');
    if (!doc.files[0]) {
        mostrarMsg('Debes subir tu documento de identidad.', 'error');
        return;
    }

    const btn = document.getElementById('btnEnviar');
    btn.disabled = true;
    btn.textContent = '⏳ Enviando...';

    const data = new FormData();
    data.append('_action', 'solicitar');
    data.append('documento', doc.files[0]);

    const foto = document.getElementById('inputFoto');
    if (foto.files[0]) data.append('foto_documento', foto.files[0]);

    try {
        const res  = await fetch('Php/verificacion.php', { method:'POST', body:data });
        const json = await res.json();

        if (json.ok) {
            mostrarMsg(json.msg, 'ok');
            btn.textContent = '✓ Solicitud enviada';
            setTimeout(() => location.reload(), 2500);
        } else {
            mostrarMsg(json.msg, 'error');
            btn.disabled = false;
            btn.textContent = '✅ Enviar solicitud de verificación';
        }
    } catch(e) {
        mostrarMsg('Error de conexión. Intenta de nuevo.', 'error');
        btn.disabled = false;
        btn.textContent = '✅ Enviar solicitud de verificación';
    }
}

document.querySelectorAll('.upload-area').forEach(area => {
    area.addEventListener('dragover',  e => { e.preventDefault(); area.classList.add('dragover'); });
    area.addEventListener('dragleave', () => area.classList.remove('dragover'));
    area.addEventListener('drop',      e => { e.preventDefault(); area.classList.remove('dragover'); });
});
</script>
</body>
</html>