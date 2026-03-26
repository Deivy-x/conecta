<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión – Quibdó Conecta</title>
    <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
    <link
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,700;0,9..144,900;1,9..144,700&family=DM+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
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
            --bg: #060e07;
            --card: rgba(10, 28, 13, .82);
            --borde: rgba(255, 255, 255, .1)
        }

        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        html,
        body {
            height: 100%;
            overflow: hidden
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            display: flex
        }

        .izq {
            flex: 1.1;
            position: relative;
            overflow: hidden;
            background: #060e07
        }

        @media(max-width:860px) {
            .izq {
                display: none
            }
        }

        .izq-foto {
            position: absolute;
            inset: 0;
            background: url('Imagenes/quibdo 3.jpg') center/cover no-repeat;
            animation: zoom 18s ease-in-out infinite alternate;
            transform-origin: center
        }

        @keyframes zoom {
            from {
                transform: scale(1)
            }

            to {
                transform: scale(1.08)
            }
        }

        .izq-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(165deg, rgba(10, 64, 32, .96) 0%, rgba(10, 64, 32, .88) 35%, rgba(0, 0, 0, .3) 50%, rgba(0, 41, 128, .78) 70%, rgba(0, 41, 128, .94) 100%)
        }

        #cvs {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 2
        }

        .izq-body {
            position: relative;
            z-index: 3;
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 44px 52px
        }

        .bandera-choco {
            width: 128px;
            height: 76px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .6), 0 2px 8px rgba(0, 0, 0, .4);
            display: flex;
            flex-direction: column;
            border: 2px solid rgba(255, 255, 255, .18);
            position: relative
        }

        .banda {
            flex: 1;
            position: relative
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

        .bandera-choco::after {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(120deg, rgba(255, 255, 255, .22) 0%, rgba(255, 255, 255, .04) 50%, transparent 100%);
            border-radius: 10px
        }

        .bandera-label {
            margin-top: 12px;
            font-family: 'Fraunces', serif;
            font-size: 12px;
            font-weight: 700;
            color: rgba(255, 255, 255, .45);
            letter-spacing: 1.5px;
            text-transform: uppercase
        }

        .izq-hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center
        }

        .izq-h1 {
            font-family: 'Fraunces', serif;
            font-size: clamp(36px, 3.5vw, 56px);
            font-weight: 900;
            color: #fff;
            line-height: 1.06;
            margin-bottom: 20px
        }

        .izq-h1 .oro {
            color: var(--a4)
        }

        .izq-h1 .verde {
            color: var(--v4)
        }

        .izq-sub {
            font-size: 15px;
            color: rgba(255, 255, 255, .6);
            line-height: 1.72;
            max-width: 360px;
            margin-bottom: 44px
        }

        .izq-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap
        }

        .istat {
            padding: 14px 20px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .09);
            backdrop-filter: blur(8px);
            min-width: 100px
        }

        .istat-n {
            font-family: 'Fraunces', serif;
            font-size: 26px;
            font-weight: 900;
            line-height: 1
        }

        .istat-n.c-v {
            color: var(--v4)
        }

        .istat-n.c-a {
            color: var(--a4)
        }

        .istat-n.c-r {
            color: var(--r4)
        }

        .istat-l {
            font-size: 11px;
            color: rgba(255, 255, 255, .4);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-top: 4px
        }

        .izq-pie {
            font-size: 12px;
            color: rgba(255, 255, 255, .3);
            letter-spacing: .4px
        }

        .der {
            width: 440px;
            flex-shrink: 0;
            background: var(--bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 52px 48px;
            position: relative;
            overflow: hidden;
            overflow-y: auto
        }

        @media(max-width:860px) {
            .der {
                width: 100%;
                padding: 44px 28px
            }
        }

        .der-borde {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--v3) 33.3%, var(--a3) 33.3% 66.6%, var(--r3) 66.6%)
        }

        @media(max-width:860px) {
            .der-borde {
                top: 0;
                left: 0;
                right: 0;
                bottom: auto;
                height: 3px;
                width: 100%;
                background: linear-gradient(to right, var(--v3) 33.3%, var(--a3) 33.3% 66.6%, var(--r3) 66.6%)
            }
        }

        .der::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(ellipse at 15% 10%, rgba(26, 120, 60, .14) 0%, transparent 55%), radial-gradient(ellipse at 85% 90%, rgba(0, 57, 166, .12) 0%, transparent 55%)
        }

        .der-inner {
            position: relative;
            z-index: 2
        }

        .mobile-logo {
            display: none;
            align-items: center;
            gap: 12px;
            margin-bottom: 36px
        }

        @media(max-width:860px) {
            .mobile-logo {
                display: flex
            }
        }

        .mobile-logo img {
            width: 38px;
            filter: drop-shadow(0 2px 8px rgba(245, 200, 0, .4))
        }

        .mobile-logo-txt {
            font-family: 'Fraunces', serif;
            font-size: 20px;
            color: #fff
        }

        .mobile-logo-txt em {
            color: var(--vlima);
            font-style: normal
        }

        .bandera-mini {
            display: none;
            width: 56px;
            height: 34px;
            border-radius: 8px;
            overflow: hidden;
            flex-direction: column;
            border: 1.5px solid rgba(255, 255, 255, .15);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .5);
            margin-left: auto
        }

        @media(max-width:860px) {
            .bandera-mini {
                display: flex
            }
        }

        .bandera-mini .banda {
            flex: 1
        }

        .form-h1 {
            font-family: 'Fraunces', serif;
            font-size: 34px;
            font-weight: 900;
            color: #fff;
            line-height: 1.08;
            margin-bottom: 6px
        }

        .form-h1 em {
            color: var(--vlima);
            font-style: normal
        }

        .form-sub {
            font-size: 14px;
            color: rgba(255, 255, 255, .4);
            margin-bottom: 38px
        }

        .g {
            margin-bottom: 18px
        }

        .g label {
            display: block;
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: 700;
            color: rgba(255, 255, 255, .4);
            text-transform: uppercase;
            letter-spacing: .8px
        }

        .g input {
            width: 100%;
            padding: 15px 18px;
            background: rgba(255, 255, 255, .05);
            border: 1.5px solid rgba(255, 255, 255, .1);
            border-radius: 16px;
            color: #fff;
            font-size: 15px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color .25s, background .25s, box-shadow .25s
        }

        .g input:focus {
            border-color: var(--v3);
            background: rgba(39, 168, 85, .09);
            box-shadow: 0 0 0 4px rgba(39, 168, 85, .12)
        }

        .g input::placeholder {
            color: rgba(255, 255, 255, .18)
        }

        .pw {
            position: relative
        }

        .pw input {
            padding-right: 52px
        }

        .tp {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255, 255, 255, .3);
            cursor: pointer;
            font-size: 18px;
            transition: color .2s;
            line-height: 1
        }

        .tp:hover {
            color: var(--vlima)
        }

        .opts {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2px 0 24px;
            font-size: 13px
        }

        .rec {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, .45);
            cursor: pointer
        }

        .rec input {
            width: 15px;
            height: 15px;
            accent-color: var(--v3)
        }

        .olv {
            color: var(--vlima);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px
        }

        .olv:hover {
            text-decoration: underline
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--v1) 0%, var(--v2) 50%, var(--v3) 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            letter-spacing: .2px;
            box-shadow: 0 8px 28px rgba(27, 122, 60, .45);
            transition: all .25s;
            position: relative;
            overflow: hidden
        }

        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, .12), transparent);
            border-radius: 16px;
            transition: opacity .25s;
            opacity: 0
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 40px rgba(27, 122, 60, .6)
        }

        .btn-login:hover::before {
            opacity: 1
        }

        .btn-login:disabled {
            opacity: .5;
            cursor: not-allowed;
            transform: none
        }

        .div-or {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 22px 0;
            font-size: 12px;
            color: rgba(255, 255, 255, .22);
            text-transform: uppercase;
            letter-spacing: .8px
        }

        .div-or::before,
        .div-or::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, .08)
        }

        .btn-g {
            width: 100%;
            padding: 14px;
            border: 1.5px solid rgba(255, 255, 255, .1);
            border-radius: 16px;
            background: rgba(255, 255, 255, .04);
            color: rgba(255, 255, 255, .75);
            font-size: 14px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all .25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px
        }

        .btn-g:hover {
            background: rgba(255, 255, 255, .09);
            border-color: rgba(255, 255, 255, .2);
            color: #fff
        }

        .btn-g img {
            width: 20px
        }

        .msg {
            display: none;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 13px;
            margin-top: 16px;
            font-weight: 500;
            line-height: 1.55
        }

        .msg.error {
            background: rgba(255, 70, 70, .1);
            border: 1px solid rgba(255, 70, 70, .25);
            color: #ffaeae
        }

        .msg.success {
            background: rgba(163, 240, 181, .1);
            border: 1px solid rgba(163, 240, 181, .25);
            color: #a3f0b5
        }

        .msg.solicitud-pendiente {
            background: rgba(245, 200, 0, .09);
            border: 1.5px solid rgba(245, 200, 0, .28);
            color: #fde68a;
            text-align: left
        }

        .msg.solicitud-rechazada {
            background: rgba(239, 68, 68, .09);
            border: 1.5px solid rgba(239, 68, 68, .25);
            color: #fca5a5;
            text-align: left
        }

        .link-r {
            text-align: center;
            margin-top: 28px;
            font-size: 14px;
            color: rgba(255, 255, 255, .38)
        }

        .link-r a {
            color: var(--vlima);
            font-weight: 700;
            text-decoration: none
        }

        .link-r a:hover {
            text-decoration: underline
        }

        .modal-ov {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .85);
            z-index: 200;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(6px)
        }

        .modal-ov.open {
            display: flex
        }

        .modal-box {
            background: #0a1e0d;
            border: 1px solid rgba(39, 168, 85, .25);
            border-radius: 24px;
            padding: 36px;
            max-width: 400px;
            width: 90%;
            color: #fff;
            animation: fadeUp .3s ease both
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

        .modal-box h3 {
            font-family: 'Fraunces', serif;
            font-size: 22px;
            color: var(--vlima);
            margin-bottom: 10px
        }

        .modal-box p {
            color: rgba(255, 255, 255, .5);
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.65
        }

        .modal-box input {
            width: 100%;
            padding: 13px 15px;
            border: 1.5px solid rgba(255, 255, 255, .1);
            border-radius: 14px;
            background: rgba(255, 255, 255, .05);
            color: #fff;
            font-size: 14px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            margin-bottom: 14px
        }

        .modal-box input:focus {
            border-color: var(--v3)
        }

        .modal-btns {
            display: flex;
            gap: 10px
        }

        .btn-can {
            flex: 1;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 12px;
            background: transparent;
            color: rgba(255, 255, 255, .55);
            font-size: 14px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            transition: all .2s
        }

        .btn-can:hover {
            background: rgba(255, 255, 255, .07);
            color: #fff
        }

        .btn-send {
            flex: 2;
            padding: 12px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--v1), var(--v3));
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer
        }

        .btn-back {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, .07);
            border: 1px solid rgba(255, 255, 255, .12);
            color: rgba(255, 255, 255, .65);
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            backdrop-filter: blur(12px);
            transition: all .3s
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, .14);
            color: #fff;
            transform: translateX(-3px)
        }
    </style>
</head>

<body>
    <a href="index.html" class="btn-back">&larr; Inicio</a>

    <div class="izq">
        <div class="izq-foto"></div>
        <div class="izq-overlay"></div>
        <canvas id="cvs"></canvas>
        <div class="izq-body">
            <div>
                <div class="bandera-choco">
                    <div class="banda banda-v"></div>
                    <div class="banda banda-a"></div>
                    <div class="banda banda-r"></div>
                </div>
                <div class="bandera-label">Dpto. del Choc&oacute; &middot; Colombia</div>
            </div>
            <div class="izq-hero">
                <div class="izq-h1">El talento<br>del <span class="oro">Choc&oacute;</span><br>tiene <span
                        class="verde">nombre</span></div>
                <p class="izq-sub">Empleos, talentos, empresas y servicios para eventos &mdash; todo el ecosistema
                    laboral de Quibd&oacute; y el Pac&iacute;fico colombiano en un solo lugar.</p>
                <div class="izq-stats">
                    <div class="istat">
                        <div class="istat-n c-v" id="stat-talentos">+1</div>
                        <div class="istat-l">Talentos</div>
                    </div>
                    <div class="istat">
                        <div class="istat-n c-a" id="stat-empresas">+1</div>
                        <div class="istat-l">Empresas</div>
                    </div>
                    <div class="istat">
                        <div class="istat-n c-r" id="stat-vacantes">+1</div>
                        <div class="istat-l">Vacantes</div>
                    </div>
                </div>
            </div>
            <div class="izq-pie">&#127807; Hecho para Quibd&oacute; y todo el Choc&oacute;</div>
        </div>
    </div>

    <div class="der">
        <div class="der-borde"></div>
        <div class="der-inner">
            <div class="mobile-logo">
                <img src="Imagenes/Quibdo.png" alt="Logo">
                <span class="mobile-logo-txt">Quibd&oacute;<em>Conecta</em></span>
                <div class="bandera-mini">
                    <div class="banda banda-v"></div>
                    <div class="banda banda-a"></div>
                    <div class="banda banda-r"></div>
                </div>
            </div>
            <div class="form-h1">Bienvenido<br>de <em>vuelta</em></div>
            <p class="form-sub">Inicia sesi&oacute;n en tu cuenta de Quibd&oacute;Conecta</p>
            <div class="g">
                <label>Correo electr&oacute;nico</label>
                <input type="email" id="correo" placeholder="correo@ejemplo.com">
            </div>
            <div class="g">
                <label>Contrase&ntilde;a</label>
                <div class="pw">
                    <input type="password" id="contrasena" placeholder="Tu contrase&ntilde;a">
                    <button type="button" class="tp" onclick="togglePass('contrasena',this)">&#128065;</button>
                </div>
            </div>
            <div class="opts">
                <label class="rec"><input type="checkbox" id="recordar"> Recordarme</label>
                <a href="#" class="olv" onclick="abrirModal()">&iquest;Olvidaste tu contrase&ntilde;a?</a>
            </div>
            <button class="btn-login" id="btnLogin" onclick="login()">&#128272; Iniciar sesi&oacute;n</button>
            <div class="div-or">o contin&uacute;a con</div>
            <button class="btn-g" onclick="alert('Pr&oacute;ximamente disponible.')">
                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" alt="Google">
                Continuar con Google
            </button>
            <div class="msg" id="msg"></div>
            <div class="link-r">&iquest;No tienes cuenta? <a href="registro.php">Reg&iacute;strate gratis</a></div>
        </div>
    </div>

    <div class="modal-ov" id="modalOverlay">
        <div class="modal-box">
            <h3>&#128273; Recuperar contrase&ntilde;a</h3>
            <p>Ingresa tu correo registrado y te enviaremos un enlace para restablecer tu contrase&ntilde;a.</p>
            <input type="email" id="correoReset" placeholder="correo@ejemplo.com">
            <div class="msg" id="msgReset"></div>
            <div class="modal-btns">
                <button class="btn-can" onclick="cerrarModal()">Cancelar</button>
                <button class="btn-send" onclick="enviarReset()">Enviar enlace</button>
            </div>
        </div>
    </div>

    <script>
        const cvs = document.getElementById('cvs');
        if (cvs) {
            const cx = cvs.getContext('2d');
            const par = cvs.parentElement;
            function resize() { cvs.width = par.offsetWidth; cvs.height = par.offsetHeight; }
            resize(); window.addEventListener('resize', resize);
            const syms = ['🌿', '🍃', '🌺', '🌸', '🦋', '✨', '⭐', '🎺', '🥁', '🌊', '🎧', '🌴', '💫', '🍀'];
            const pts = Array.from({ length: 55 }, () => ({ x: Math.random() * 1200, y: Math.random() * 900 - 900, e: syms[Math.floor(Math.random() * syms.length)], s: Math.random() * 16 + 8, vy: Math.random() * 1.2 + 0.4, vx: (Math.random() - .5) * .6, r: Math.random() * Math.PI * 2, rs: (Math.random() - .5) * .025, o: Math.random() * .4 + .08, sw: Math.random() * Math.PI * 2, sws: Math.random() * .02 + .01 }));
            (function loop() { cx.clearRect(0, 0, cvs.width, cvs.height); pts.forEach(p => { p.sw += p.sws; p.x += p.vx + Math.sin(p.sw) * .4; p.y += p.vy; p.r += p.rs; cx.save(); cx.globalAlpha = p.o; cx.translate(p.x, p.y); cx.rotate(p.r); cx.font = p.s + 'px serif'; cx.fillText(p.e, 0, 0); cx.restore(); if (p.y > cvs.height + 30) { p.y = -30; p.x = Math.random() * cvs.width; p.e = syms[Math.floor(Math.random() * syms.length)]; } }); requestAnimationFrame(loop); })();
        }
        function togglePass(id, btn) { const i = document.getElementById(id); i.type = i.type === 'password' ? 'text' : 'password'; btn.textContent = i.type === 'password' ? '👁' : '🙈'; }
        function showMsg(t, c, id = 'msg') { const e = document.getElementById(id); e.textContent = t; e.className = 'msg ' + c; e.style.display = 'block'; }
        async function login() {
            const correo = document.getElementById('correo').value.trim();
            const pass = document.getElementById('contrasena').value;
            const rec = document.getElementById('recordar').checked;
            if (!correo || !pass) { showMsg('Ingresa tu correo y contraseña.', 'error'); return; }
            const btn = document.getElementById('btnLogin'); btn.disabled = true; btn.textContent = '⏳ Verificando...';
            const fd = new FormData(); fd.append('correo', correo); fd.append('contrasena', pass); if (rec) fd.append('recordar', '1');
            try {
                const r = await fetch('Php/login.php', { method: 'POST', body: fd }); const j = await r.json();
                if (j.ok) { showMsg('¡Bienvenido, ' + j.nombre + '! Redirigiendo…', 'success'); setTimeout(() => { window.location.href = j.dashboard || 'dashboard.php'; }, 1200); }
                else if (j.solicitud === 'pendiente') { const e = document.getElementById('msg'); e.innerHTML = '<div style="display:flex;gap:12px;align-items:flex-start"><span style="font-size:24px;flex-shrink:0">⏳</span><div><strong style="display:block;margin-bottom:4px">Solicitud en revisión</strong><span style="font-size:13px;opacity:.85">El administrador está revisando tu solicitud.</span><a href="registro.php" style="display:block;margin-top:8px;font-size:12px;color:inherit;opacity:.65;text-decoration:underline">¿Actualizar solicitud?</a></div></div>'; e.className = 'msg solicitud-pendiente'; e.style.display = 'block'; btn.disabled = false; btn.textContent = '🔐 Iniciar sesión'; }
                else if (j.solicitud === 'rechazado') { const e = document.getElementById('msg'); e.innerHTML = '<div style="display:flex;gap:12px;align-items:flex-start"><span style="font-size:24px;flex-shrink:0">❌</span><div><strong style="display:block;margin-bottom:4px">Solicitud rechazada</strong><span style="font-size:13px;opacity:.85">Tu solicitud fue rechazada.</span><a href="registro.php" style="display:block;margin-top:8px;font-size:12px;color:inherit;opacity:.65;text-decoration:underline">Registrarme de nuevo →</a></div></div>'; e.className = 'msg solicitud-rechazada'; e.style.display = 'block'; btn.disabled = false; btn.textContent = '🔐 Iniciar sesión'; }
                else { showMsg(j.msg, 'error'); btn.disabled = false; btn.textContent = '🔐 Iniciar sesión'; }
            } catch (e) { showMsg('Error de conexión.', 'error'); btn.disabled = false; btn.textContent = '🔐 Iniciar sesión'; }
        }
        function abrirModal() { document.getElementById('modalOverlay').classList.add('open'); }
        function cerrarModal() { document.getElementById('modalOverlay').classList.remove('open'); }
        async function enviarReset() {
            const c = document.getElementById('correoReset').value.trim();
            if (!c) { showMsg('Ingresa tu correo.', 'error', 'msgReset'); return; }
            const fd = new FormData(); fd.append('correo', c);
            try { const r = await fetch('Php/reset_password.php', { method: 'POST', body: fd }); const j = await r.json(); showMsg(j.msg, j.ok ? 'success' : 'error', 'msgReset'); } catch (e) { showMsg('Error de conexión.', 'error', 'msgReset'); }
        }
        document.addEventListener('keypress', e => { if (e.key === 'Enter') login(); });
        document.getElementById('modalOverlay').addEventListener('click', e => { if (e.target === document.getElementById('modalOverlay')) cerrarModal(); });

        function animarContador(el, desde, hasta, duracion) {
            if (desde === hasta) return;
            const inicio = performance.now();
            const easeOut = t => 1 - Math.pow(1 - t, 3);
            function tick(ahora) {
                const progreso = Math.min((ahora - inicio) / duracion, 1);
                el.textContent = '+' + Math.round(desde + (hasta - desde) * easeOut(progreso));
                if (progreso < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
        }

        async function cargarStatsLogin() {
            try {
                const res = await fetch('stats.php');
                if (!res.ok) return;
                const d = await res.json();
                const mapa = {
                    'stat-talentos': d.total_talentos,
                    'stat-empresas': d.total_empresas,
                    'stat-vacantes': d.total_empleos
                };
                Object.entries(mapa).forEach(([id, valor]) => {
                    if (!valor) return;
                    const el = document.getElementById(id);
                    if (!el) return;
                    
                    const actual = parseInt(el.textContent.replace('+', '')) || 0;
                    animarContador(el, actual, valor, 1200);
                });
            } catch (e) {  }
        }

        cargarStatsLogin();
    </script>
</body>

</html>