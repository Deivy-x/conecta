<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buscar — Quibdó Conecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    html { scroll-behavior: smooth; }

    :root {
      --blue:    #1a56db;
      --blue2:   #3b82f6;
      --green:   #1f9d55;
      --dark:    #0f172a;
      --surface: #f8fafc;
      --border:  #e2e8f0;
      --text:    #0f172a;
      --muted:   #64748b;
      --radius:  16px;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--surface);
      color: var(--text);
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── NAVBAR ── */
    .navbar {
      position: fixed; top: 0; left: 0; width: 100%; height: 72px;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 48px;
      background: rgba(255,255,255,.92);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
      box-shadow: 0 1px 12px rgba(0,0,0,.05);
      z-index: 1000;
    }
    .nav-left { display: flex; align-items: center; }
    .logo-navbar { height: 44px; width: auto; object-fit: contain; }
    .nav-center { display: flex; align-items: center; gap: 22px; flex: 1; justify-content: center; }
    .nav-center a { color: #334155; text-decoration: none; font-size: 15px; font-weight: 500; padding: 6px 4px; position: relative; }
    .nav-center a::after { content:""; position:absolute; left:0; bottom:-4px; width:0; height:2px; background:var(--blue); transition:width .3s; }
    .nav-center a:hover::after, .nav-center a.active::after { width: 100%; }
    .nav-right { display: flex; align-items: center; gap: 12px; }
    .btn-login { color: var(--blue); border: 2px solid var(--blue); padding: 8px 18px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all .2s; }
    .btn-login:hover { background: var(--blue); color: white; }
    .btn-reg { background: var(--blue); color: white; padding: 9px 20px; border-radius: 25px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 4px 12px rgba(26,86,219,.3); transition: all .2s; }
    .btn-reg:hover { background: #1344b8; }
    .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; background: none; border: none; padding: 4px; }
    .hamburger span { display: block; width: 24px; height: 2px; background: #111; border-radius: 4px; transition: all .3s; }
    .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    .hamburger.open span:nth-child(2) { opacity: 0; }
    .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
    .mobile-menu { display: none; position: fixed; top: 72px; left: 0; width: 100%; background: white; border-bottom: 1px solid var(--border); box-shadow: 0 12px 32px rgba(0,0,0,.1); flex-direction: column; padding: 16px 24px; gap: 4px; z-index: 999; }
    .mobile-menu.open { display: flex; }
    .mobile-menu a { color: #333; text-decoration: none; font-size: 15px; font-weight: 500; padding: 10px 0; border-bottom: 1px solid rgba(0,0,0,.05); }
    .mobile-auth { display: flex; gap: 10px; margin-top: 12px; }
    .mobile-auth a { flex: 1; text-align: center; padding: 10px; border-radius: 25px; font-weight: 600; text-decoration: none; }
    .m-login { border: 2px solid var(--blue); color: var(--blue); }
    .m-reg { background: var(--blue); color: white; }

    /* ── HERO SEARCH ── */
    .search-hero {
      padding: 110px 48px 0;
      background: linear-gradient(160deg, #0f172a 0%, #1e293b 55%, #0f172a 100%);
      position: relative;
      overflow: hidden;
      min-height: 280px;
    }
    .search-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(ellipse at 20% 60%, rgba(26,86,219,.22) 0%, transparent 55%),
        radial-gradient(ellipse at 80% 30%, rgba(59,130,246,.14) 0%, transparent 55%);
    }
    /* decorative dots */
    .search-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
      background-size: 32px 32px;
      pointer-events: none;
    }
    .search-hero-inner {
      position: relative;
      z-index: 2;
      max-width: 780px;
      margin: 0 auto;
      text-align: center;
      padding-bottom: 0;
    }
    .search-hero-inner .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(26,86,219,.18);
      border: 1px solid rgba(59,130,246,.35);
      color: #93c5fd;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .8px;
      text-transform: uppercase;
      padding: 5px 16px;
      border-radius: 30px;
      margin-bottom: 20px;
    }
    .search-hero-inner h1 {
      font-family: 'Syne', sans-serif;
      font-size: clamp(32px, 5vw, 52px);
      font-weight: 800;
      color: white;
      line-height: 1.1;
      margin-bottom: 14px;
      letter-spacing: -1px;
    }
    .search-hero-inner h1 span { color: #60a5fa; }
    .search-hero-inner .sub {
      color: rgba(255,255,255,.6);
      font-size: 16px;
      margin-bottom: 36px;
    }

    /* ── BARRA DE BÚSQUEDA ── */
    .search-bar-wrap {
      position: relative;
      z-index: 10;
      max-width: 720px;
      margin: 0 auto;
      transform: translateY(28px);
    }
    .search-bar {
      display: flex;
      align-items: center;
      background: white;
      border-radius: 18px;
      box-shadow: 0 24px 60px rgba(0,0,0,.28), 0 0 0 1px rgba(255,255,255,.08);
      overflow: hidden;
      transition: box-shadow .3s;
    }
    .search-bar:focus-within {
      box-shadow: 0 24px 60px rgba(0,0,0,.32), 0 0 0 3px rgba(26,86,219,.25);
    }
    .sb-icon {
      padding: 0 18px;
      font-size: 20px;
      color: #94a3b8;
      flex-shrink: 0;
    }
    #searchInput {
      flex: 1;
      border: none;
      outline: none;
      font-size: 17px;
      font-family: 'DM Sans', sans-serif;
      color: #0f172a;
      padding: 18px 0;
      background: transparent;
    }
    #searchInput::placeholder { color: #94a3b8; }
    #clearBtn {
      padding: 0 14px;
      font-size: 18px;
      color: #94a3b8;
      background: none;
      border: none;
      cursor: pointer;
      display: none;
      line-height: 1;
    }
    #clearBtn.visible { display: block; }
    .sb-divider { width: 1px; height: 32px; background: #e2e8f0; flex-shrink: 0; }
    #searchBtn {
      display: flex;
      align-items: center;
      gap: 8px;
      background: linear-gradient(135deg, var(--blue), var(--blue2));
      color: white;
      border: none;
      padding: 14px 28px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: opacity .2s;
      white-space: nowrap;
      margin: 6px;
      border-radius: 12px;
    }
    #searchBtn:hover { opacity: .88; }

    /* ── CONTENIDO PRINCIPAL ── */
    .main-content {
      max-width: 1180px;
      margin: 0 auto;
      padding: 72px 24px 80px;
    }

    /* ── TABS ── */
    .tabs-wrap {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
      margin-bottom: 32px;
      border-bottom: 2px solid var(--border);
      padding-bottom: 0;
    }
    .tab-btn {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 12px 20px;
      border: none;
      background: transparent;
      font-size: 14px;
      font-weight: 700;
      color: var(--muted);
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      transition: color .2s, border-color .2s;
      border-radius: 8px 8px 0 0;
    }
    .tab-btn:hover { color: var(--blue); background: rgba(26,86,219,.04); }
    .tab-btn.active { color: var(--blue); border-bottom-color: var(--blue); }
    .tab-count {
      background: #eff6ff;
      color: var(--blue);
      font-size: 11px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 20px;
      min-width: 22px;
      text-align: center;
    }
    .tab-btn.active .tab-count { background: var(--blue); color: white; }

    /* ── ESTADO INICIAL ── */
    .estado-inicial {
      text-align: center;
      padding: 80px 20px;
    }
    .estado-inicial .big-icon { font-size: 64px; margin-bottom: 20px; display: block; }
    .estado-inicial h2 { font-family: 'Syne', sans-serif; font-size: 26px; margin-bottom: 10px; }
    .estado-inicial p { color: var(--muted); font-size: 15px; max-width: 420px; margin: 0 auto; }
    .quick-tags { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; margin-top: 24px; }
    .quick-tag {
      background: white;
      border: 1.5px solid var(--border);
      color: #334155;
      padding: 7px 16px;
      border-radius: 25px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s;
    }
    .quick-tag:hover { border-color: var(--blue); color: var(--blue); background: #eff6ff; }

    /* ── LOADING ── */
    .loading-state {
      display: none;
      text-align: center;
      padding: 60px 20px;
    }
    .loading-state.visible { display: block; }
    .pulse-ring {
      display: inline-block;
      width: 52px; height: 52px;
      border: 3px solid #e2e8f0;
      border-top-color: var(--blue);
      border-radius: 50%;
      animation: spin .8s linear infinite;
      margin-bottom: 16px;
    }
    @keyframes spin { to { transform: rotate(360deg) } }
    .loading-state p { color: var(--muted); font-size: 15px; }

    /* ── SIN RESULTADOS ── */
    .empty-state {
      display: none;
      text-align: center;
      padding: 60px 20px;
    }
    .empty-state.visible { display: block; }
    .empty-state .e-icon { font-size: 52px; display: block; margin-bottom: 14px; }
    .empty-state h3 { font-family: 'Syne', sans-serif; font-size: 20px; margin-bottom: 8px; }
    .empty-state p { color: var(--muted); font-size: 14px; }

    /* ── RESULTADOS INFO ── */
    .results-info {
      display: none;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 8px;
    }
    .results-info.visible { display: flex; }
    .results-info .ri-text { font-size: 14px; color: var(--muted); }
    .results-info .ri-text strong { color: var(--text); }

    /* ── GRIDS ── */
    .panel { display: none; }
    .panel.active { display: block; }

    /* Candidatos grid */
    .grid-candidatos {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
      gap: 20px;
    }

    /* Empresas grid */
    .grid-empresas {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
      gap: 20px;
    }

    /* Empleos y convocatorias — lista */
    .grid-lista { display: flex; flex-direction: column; gap: 14px; }

    /* ── CARD: CANDIDATO ── */
    .card-candidato {
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 22px;
      transition: all .25s;
      position: relative;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      display: block;
    }
    .card-candidato:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 36px rgba(0,0,0,.1);
      border-color: transparent;
    }
    .cc-avatar {
      width: 60px; height: 60px;
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; font-weight: 800; color: white;
      margin-bottom: 14px;
      overflow: hidden;
    }
    .cc-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .cc-badge {
      position: absolute; top: 16px; right: 16px;
      font-size: 10px; font-weight: 700; padding: 3px 9px;
      border-radius: 20px; text-transform: uppercase; letter-spacing: .4px;
    }
    .cc-name { font-size: 16px; font-weight: 700; margin-bottom: 3px; }
    .cc-prof { color: var(--blue); font-size: 13px; font-weight: 600; margin-bottom: 3px; }
    .cc-loc { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
    .cc-skills { display: flex; flex-wrap: wrap; gap: 5px; }
    .skill-tag {
      background: #f1f5f9; color: #475569;
      font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 500;
    }

    /* ── CARD: EMPRESA ── */
    .card-empresa {
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 22px;
      transition: all .25s;
      position: relative;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      display: block;
    }
    .card-empresa:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 36px rgba(0,0,0,.1);
      border-color: transparent;
    }
    .ce-avatar {
      width: 60px; height: 60px;
      border-radius: 13px;
      display: flex; align-items: center; justify-content: center;
      font-size: 21px; font-weight: 800; color: white;
      margin-bottom: 14px;
      overflow: hidden;
    }
    .ce-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .ce-name { font-size: 16px; font-weight: 700; margin-bottom: 3px; }
    .ce-sector { color: var(--blue); font-size: 13px; font-weight: 600; margin-bottom: 3px; }
    .ce-loc { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
    .ce-tag { background: #f1f5f9; color: #475569; font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 500; }

    /* ── CARD: EMPLEO ── */
    .card-empleo {
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px 24px;
      display: flex;
      align-items: center;
      gap: 18px;
      transition: all .25s;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
    }
    .card-empleo:hover {
      box-shadow: 0 8px 28px rgba(0,0,0,.09);
      border-color: #bfdbfe;
      transform: translateX(4px);
    }
    .emp-icon {
      width: 50px; height: 50px; flex-shrink: 0;
      background: linear-gradient(135deg, #eff6ff, #dbeafe);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
    }
    .emp-info { flex: 1; min-width: 0; }
    .emp-titulo { font-size: 15px; font-weight: 700; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .emp-empresa { font-size: 13px; color: var(--muted); margin-bottom: 6px; }
    .emp-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .emp-chip {
      font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px;
    }
    .chip-blue { background: #eff6ff; color: var(--blue); }
    .chip-green { background: #f0fdf4; color: #166534; }
    .chip-gray { background: #f1f5f9; color: #475569; }
    .emp-arrow { color: #cbd5e1; font-size: 20px; flex-shrink: 0; }

    /* ── CARD: CONVOCATORIA ── */
    .card-conv {
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px 24px;
      display: flex;
      align-items: center;
      gap: 18px;
      transition: all .25s;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
    }
    .card-conv:hover {
      box-shadow: 0 8px 28px rgba(0,0,0,.09);
      border-color: #bbf7d0;
      transform: translateX(4px);
    }
    .conv-icon-wrap {
      width: 50px; height: 50px; flex-shrink: 0;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 24px;
    }
    .conv-info { flex: 1; min-width: 0; }
    .conv-titulo { font-size: 15px; font-weight: 700; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .conv-entidad { font-size: 13px; color: var(--muted); margin-bottom: 6px; }
    .conv-meta { display: flex; flex-wrap: wrap; gap: 6px; }
    .chip-pub { background: #faf5ff; color: #7c3aed; }
    .conv-arrow { color: #cbd5e1; font-size: 20px; flex-shrink: 0; }

    /* ── CTA BOTTOM ── */
    .cta-bottom {
      background: linear-gradient(135deg, #0f172a, #1e293b);
      border-radius: 24px;
      padding: 52px 48px;
      text-align: center;
      margin-top: 72px;
      position: relative;
      overflow: hidden;
    }
    .cta-bottom::before {
      content: '';
      position: absolute;
      top: -60px; left: -60px;
      width: 300px; height: 300px;
      background: radial-gradient(circle, rgba(26,86,219,.15) 0%, transparent 70%);
      pointer-events: none;
    }
    .cta-bottom h2 { font-family: 'Syne', sans-serif; font-size: 28px; color: white; margin-bottom: 10px; position: relative; z-index: 1; }
    .cta-bottom p { color: rgba(255,255,255,.6); font-size: 15px; margin-bottom: 28px; position: relative; z-index: 1; }
    .cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; position: relative; z-index: 1; }
    .btn-primary { background: linear-gradient(135deg, var(--blue), var(--blue2)); color: white; padding: 13px 28px; border-radius: 30px; text-decoration: none; font-weight: 700; font-size: 14px; box-shadow: 0 6px 20px rgba(26,86,219,.4); transition: transform .2s; display: inline-block; }
    .btn-primary:hover { transform: translateY(-2px); }
    .btn-outline-w { border: 2px solid rgba(255,255,255,.25); color: white; padding: 13px 28px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 14px; transition: all .2s; }
    .btn-outline-w:hover { border-color: #60a5fa; color: #60a5fa; }

    /* ── FOOTER ── */
    footer {
      background: #0f172a;
      border-top: 1px solid rgba(255,255,255,.06);
      color: rgba(255,255,255,.45);
      text-align: center;
      padding: 24px;
      font-size: 14px;
    }
    footer span { color: #60a5fa; }

    /* ── RESPONSIVE ── */
    @media(max-width: 768px) {
      .navbar { padding: 0 20px; }
      .nav-center, .nav-right { display: none; }
      .hamburger { display: flex; }
      .search-hero { padding: 100px 20px 0; }
      .main-content { padding: 60px 16px 60px; }
      .search-bar-wrap { transform: translateY(24px); }
      .cta-bottom { padding: 40px 24px; }
      .card-empleo, .card-conv { flex-direction: column; align-items: flex-start; gap: 12px; }
      .emp-arrow, .conv-arrow { display: none; }
    }
    @media(max-width: 480px) {
      .tab-btn { padding: 10px 12px; font-size: 12px; }
      .grid-candidatos, .grid-empresas { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar" id="navbar">
  <div class="nav-left">
    <img src="Imagenes/quibdo_desco_new.png" alt="Quibdó Conecta" class="logo-navbar">
  </div>
  <nav class="nav-center">
    <a href="index.html">Inicio</a>
    <a href="Empleo.php">Empleos</a>
    <a href="talentos.php">Talento</a>
    <a href="empresas.php">Empresas</a>
    <a href="buscar.php" class="active">Buscar</a>
    <a href="Ayuda.html">Ayuda</a>
  </nav>
  <div class="nav-right">
    <a href="inicio_sesion.php" class="btn-login">Iniciar sesión</a>
    <a href="registro.php" class="btn-reg">Registrarse</a>
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
  <a href="buscar.php">🔍 Buscar</a>
  <a href="Ayuda.html">❓ Ayuda</a>
  <div class="mobile-auth">
    <a href="inicio_sesion.php" class="m-login">Iniciar sesión</a>
    <a href="registro.php" class="m-reg">Registrarse</a>
  </div>
</div>

<!-- HERO + SEARCH -->
<section class="search-hero">
  <div class="search-hero-inner">
    <span class="eyebrow">🔍 Búsqueda global</span>
    <h1>Encuentra <span>talento, empresas</span><br>y oportunidades</h1>
    <p class="sub">Todo el ecosistema del Chocó en un solo lugar</p>
    <div class="search-bar-wrap">
      <div class="search-bar">
        <span class="sb-icon">🔍</span>
        <input type="text" id="searchInput" placeholder="Escribe un nombre, profesión, empresa, cargo…" autocomplete="off" autofocus>
        <button id="clearBtn" aria-label="Limpiar">✕</button>
        <span class="sb-divider"></span>
        <button id="searchBtn">Buscar</button>
      </div>
    </div>
  </div>
</section>

<!-- MAIN -->
<main class="main-content">

  <!-- TABS -->
  <div class="tabs-wrap" id="tabsWrap">
    <button class="tab-btn active" data-tab="todos">
      🌐 Todos <span class="tab-count" id="cnt-todos">—</span>
    </button>
    <button class="tab-btn" data-tab="candidatos">
      👤 Candidatos <span class="tab-count" id="cnt-candidatos">—</span>
    </button>
    <button class="tab-btn" data-tab="empresas">
      🏢 Empresas <span class="tab-count" id="cnt-empresas">—</span>
    </button>
    <button class="tab-btn" data-tab="empleos">
      💼 Empleos <span class="tab-count" id="cnt-empleos">—</span>
    </button>
    <button class="tab-btn" data-tab="convocatorias">
      📋 Convocatorias <span class="tab-count" id="cnt-convocatorias">—</span>
    </button>
  </div>

  <!-- INFO RESULTADOS -->
  <div class="results-info" id="resultsInfo">
    <span class="ri-text" id="riText"></span>
  </div>

  <!-- LOADING -->
  <div class="loading-state" id="loadingState">
    <div class="pulse-ring"></div>
    <p>Buscando en toda la plataforma…</p>
  </div>

  <!-- EMPTY -->
  <div class="empty-state" id="emptyState">
    <span class="e-icon">🔍</span>
    <h3>Sin resultados</h3>
    <p>No encontramos nada para "<span id="emptyQuery"></span>".<br>Intenta con otro término.</p>
  </div>

  <!-- ESTADO INICIAL -->
  <div class="estado-inicial" id="estadoInicial">
    <span class="big-icon">✨</span>
    <h2>¿Qué estás buscando?</h2>
    <p>Busca candidatos, empresas, empleos o convocatorias públicas del Chocó.</p>
    <div class="quick-tags">
      <span class="quick-tag" onclick="buscarRapido('desarrollador')">💻 Desarrollador</span>
      <span class="quick-tag" onclick="buscarRapido('diseño')">🎨 Diseño</span>
      <span class="quick-tag" onclick="buscarRapido('tecnología')">🚀 Tecnología</span>
      <span class="quick-tag" onclick="buscarRapido('salud')">🏥 Salud</span>
      <span class="quick-tag" onclick="buscarRapido('educación')">📚 Educación</span>
      <span class="quick-tag" onclick="buscarRapido('Quibdó')">📍 Quibdó</span>
      <span class="quick-tag" onclick="buscarRapido('administración')">📊 Administración</span>
    </div>
  </div>

  <!-- PANELS -->
  <!-- TODOS -->
  <div class="panel active" id="panel-todos">
    <div id="todos-candidatos-wrap"></div>
    <div id="todos-empresas-wrap"></div>
    <div id="todos-empleos-wrap"></div>
    <div id="todos-convocatorias-wrap"></div>
  </div>

  <!-- CANDIDATOS -->
  <div class="panel" id="panel-candidatos">
    <div class="grid-candidatos" id="grid-candidatos"></div>
  </div>

  <!-- EMPRESAS -->
  <div class="panel" id="panel-empresas">
    <div class="grid-empresas" id="grid-empresas"></div>
  </div>

  <!-- EMPLEOS -->
  <div class="panel" id="panel-empleos">
    <div class="grid-lista" id="grid-empleos"></div>
  </div>

  <!-- CONVOCATORIAS -->
  <div class="panel" id="panel-convocatorias">
    <div class="grid-lista" id="grid-convocatorias"></div>
  </div>

  <!-- CTA -->
  <div class="cta-bottom" id="ctaBottom" style="display:none">
    <h2>¿No encontraste lo que buscabas?</h2>
    <p>Regístrate gratis y accede a todo el talento y oportunidades del Chocó.</p>
    <div class="cta-btns">
      <a href="registro.php" class="btn-primary">✨ Crear cuenta gratis</a>
      <a href="talentos.php" class="btn-outline-w">🌟 Ver todos los talentos</a>
      <a href="empresas.php" class="btn-outline-w">🏢 Ver todas las empresas</a>
    </div>
  </div>

</main>

<footer>
  <p>© 2026 <span>QuibdóConecta</span> — Conectando el talento del Chocó con el mundo.</p>
</footer>

<script>
  // ── NAVBAR SCROLL ──
  window.addEventListener('scroll', () => {
    document.getElementById('navbar').style.boxShadow =
      window.scrollY > 40 ? '0 4px 24px rgba(0,0,0,.12)' : '0 1px 12px rgba(0,0,0,.05)';
  });

  // ── HAMBURGER ──
  const ham = document.getElementById('hamburger');
  const mob = document.getElementById('mobileMenu');
  ham.addEventListener('click', () => { ham.classList.toggle('open'); mob.classList.toggle('open'); });
  document.addEventListener('click', e => {
    if (!ham.contains(e.target) && !mob.contains(e.target)) {
      ham.classList.remove('open'); mob.classList.remove('open');
    }
  });

  // ── ESTADO ──
  let tabActual = 'todos';
  let ultimaBusqueda = '';
  let datos = { candidatos: [], empresas: [], empleos: [], convocatorias: [] };

  // ── HELPERS UI ──
  function show(id) { document.getElementById(id)?.classList.add('visible'); }
  function hide(id) { document.getElementById(id)?.classList.remove('visible'); }
  function showEl(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hideEl(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }

  function setLoading(on) {
    if (on) { show('loadingState'); hide('emptyState'); hide('resultsInfo'); hideEl('estadoInicial'); hideEl('ctaBottom'); }
    else { hide('loadingState'); }
  }

  function totalResultados() {
    return datos.candidatos.length + datos.empresas.length + datos.empleos.length + datos.convocatorias.length;
  }

  // ── TABS ──
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      tabActual = btn.dataset.tab;
      document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
      document.getElementById('panel-' + tabActual).classList.add('active');
      actualizarInfo();
    });
  });

  function actualizarCounts() {
    const total = totalResultados();
    document.getElementById('cnt-todos').textContent          = total;
    document.getElementById('cnt-candidatos').textContent     = datos.candidatos.length;
    document.getElementById('cnt-empresas').textContent       = datos.empresas.length;
    document.getElementById('cnt-empleos').textContent        = datos.empleos.length;
    document.getElementById('cnt-convocatorias').textContent  = datos.convocatorias.length;
  }

  function actualizarInfo() {
    const n = tabActual === 'todos' ? totalResultados()
      : datos[tabActual]?.length ?? 0;
    document.getElementById('riText').innerHTML =
      `<strong>${n}</strong> resultado${n !== 1 ? 's' : ''} para "<strong>${ultimaBusqueda}</strong>"`;
    if (n > 0) show('resultsInfo');
  }

  // ── CARDS HTML ──
  function cardCandidato(c) {
    const ini = (c.nombre || '').substring(0, 2).toUpperCase();
    const grad = c.avatar_color || 'linear-gradient(135deg,#1f9d55,#2ecc71)';
    const skills = (c.skills || '').split(',').filter(Boolean).slice(0, 3);
    const verified = parseInt(c.verificado) ? '<span class="cc-badge" style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd">✓ Verificado</span>' : '';
    const destacado = parseInt(c.destacado) ? '<span class="cc-badge" style="background:#fdf4ff;color:#7e22ce;border:1px solid #e9d5ff">🏅 Destacado</span>' : '';
    const foto = c.foto ? `<img src="uploads/${c.foto}" alt="${ini}" onerror="this.style.display='none';this.parentNode.textContent='${ini}'">` : ini;
    return `
      <a href="perfil.php?id=${c.id}&tipo=candidato" class="card-candidato">
        ${verified || destacado}
        <div class="cc-avatar" style="background:${grad}">${foto}</div>
        <div class="cc-name">${esc(c.nombre || '')} ${esc(c.apellido || '')}</div>
        <div class="cc-prof">🏷️ ${esc(c.profesion || 'Profesional')}</div>
        <div class="cc-loc">📍 ${esc(c.ciudad || 'Chocó')}</div>
        <div class="cc-skills">${skills.map(s => `<span class="skill-tag">${esc(s.trim())}</span>`).join('')}</div>
      </a>`;
  }

  function cardEmpresa(e) {
    const nombre = e.nombre_empresa || e.nombre || '';
    const ini = nombre.substring(0, 2).toUpperCase();
    const grad = e.avatar_color || 'linear-gradient(135deg,#1a56db,#3b82f6)';
    const verified = parseInt(e.verificado) ? '<span class="cc-badge" style="background:#dbeafe;color:#1e40af;border:1px solid #93c5fd">✓ Verificada</span>' : '';
    const logo = e.logo ? `<img src="uploads/logos/${e.logo}" alt="${ini}" onerror="this.style.display='none';this.parentNode.textContent='${ini}'">` : ini;
    return `
      <a href="perfil.php?id=${e.id}&tipo=empresa" class="card-empresa">
        ${verified}
        <div class="ce-avatar" style="background:${grad}">${logo}</div>
        <div class="ce-name">${esc(nombre)}</div>
        <div class="ce-sector">🏷️ ${esc(e.sector || 'Empresa')}</div>
        <div class="ce-loc">📍 ${esc(e.ciudad || 'Chocó')}</div>
        <span class="ce-tag">${esc(e.sector || 'Local')}</span>
      </a>`;
  }

  function cardEmpleo(e) {
    const modalidad = e.modalidad || e.tipo_contrato || '';
    const salario = e.salario_texto || '';
    const ciudad = e.ciudad || 'Quibdó';
    return `
      <a href="Empleo.php" class="card-empleo">
        <div class="emp-icon">💼</div>
        <div class="emp-info">
          <div class="emp-titulo">${esc(e.titulo || '')}</div>
          <div class="emp-empresa">🏢 ${esc(e.empresa_nombre || 'Empresa')}</div>
          <div class="emp-chips">
            ${modalidad ? `<span class="emp-chip chip-blue">${esc(modalidad)}</span>` : ''}
            ${ciudad ? `<span class="emp-chip chip-gray">📍 ${esc(ciudad)}</span>` : ''}
            ${salario ? `<span class="emp-chip chip-green">💰 ${esc(salario)}</span>` : ''}
          </div>
        </div>
        <span class="emp-arrow">›</span>
      </a>`;
  }

  function cardConvocatoria(c) {
    const icon = c.icono || '📋';
    const estado = c.estado || 'abierta';
    const chipEstado = estado === 'abierta'
      ? '<span class="emp-chip chip-green">🟢 Abierta</span>'
      : '<span class="emp-chip chip-gray">⏸ Cerrada</span>';
    return `
      <a href="${c.url_externa ? (c.url_externa.startsWith('http') ? c.url_externa : 'https://' + c.url_externa) : '#'}" class="card-conv" target="${c.url_externa ? '_blank' : '_self'}">
        <div class="conv-icon-wrap" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7)">${icon}</div>
        <div class="conv-info">
          <div class="conv-titulo">${esc(c.titulo || '')}</div>
          <div class="conv-entidad">🏛️ ${esc(c.entidad || '')}</div>
          <div class="conv-meta">
            ${chipEstado}
            ${c.modalidad ? `<span class="emp-chip chip-blue">${esc(c.modalidad)}</span>` : ''}
            ${c.lugar ? `<span class="emp-chip chip-gray">📍 ${esc(c.lugar)}</span>` : ''}
            ${c.salario ? `<span class="emp-chip chip-green">💰 ${esc(c.salario)}</span>` : ''}
          </div>
        </div>
        <span class="conv-arrow">›</span>
      </a>`;
  }

  function esc(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── SECCIÓN EN PANEL TODOS ──
  function seccionTodos(titulo, items, renderFn, gridClass, wrapId) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    if (!items || items.length === 0) { wrap.innerHTML = ''; return; }
    wrap.innerHTML = `
      <div style="margin-bottom:28px">
        <h3 style="font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#0f172a;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #e2e8f0">${titulo}</h3>
        <div class="${gridClass}">${items.slice(0, 4).map(renderFn).join('')}</div>
      </div>`;
  }

  // ── RENDERIZAR RESULTADOS ──
  function renderResultados() {
    // Panel TODOS
    seccionTodos('👤 Candidatos', datos.candidatos, cardCandidato, 'grid-candidatos', 'todos-candidatos-wrap');
    seccionTodos('🏢 Empresas',   datos.empresas,   cardEmpresa,   'grid-empresas',   'todos-empresas-wrap');
    seccionTodos('💼 Empleos',    datos.empleos,    cardEmpleo,    'grid-lista',       'todos-empleos-wrap');
    seccionTodos('📋 Convocatorias', datos.convocatorias, cardConvocatoria, 'grid-lista', 'todos-convocatorias-wrap');

    // Paneles individuales
    document.getElementById('grid-candidatos').innerHTML    = datos.candidatos.map(cardCandidato).join('') || '<p style="color:#94a3b8;padding:20px 0">Sin candidatos para esta búsqueda.</p>';
    document.getElementById('grid-empresas').innerHTML      = datos.empresas.map(cardEmpresa).join('')     || '<p style="color:#94a3b8;padding:20px 0">Sin empresas para esta búsqueda.</p>';
    document.getElementById('grid-empleos').innerHTML       = datos.empleos.map(cardEmpleo).join('')       || '<p style="color:#94a3b8;padding:20px 0">Sin empleos para esta búsqueda.</p>';
    document.getElementById('grid-convocatorias').innerHTML = datos.convocatorias.map(cardConvocatoria).join('') || '<p style="color:#94a3b8;padding:20px 0">Sin convocatorias para esta búsqueda.</p>';
  }

  // ── BÚSQUEDA AJAX ──
  let abortCtrl = null;
  async function buscar(query) {
    query = (query || '').trim();
    if (!query) return;
    if (query === ultimaBusqueda) return;
    ultimaBusqueda = query;

    if (abortCtrl) abortCtrl.abort();
    abortCtrl = new AbortController();

    setLoading(true);
    hide('emptyState');
    hide('resultsInfo');

    try {
      const r = await fetch(`Php/search.php?q=${encodeURIComponent(query)}`, { signal: abortCtrl.signal });
      const json = await r.json();
      datos = {
        candidatos:    json.candidatos    || [],
        empresas:      json.empresas      || [],
        empleos:       json.empleos       || [],
        convocatorias: json.convocatorias || []
      };

      setLoading(false);
      actualizarCounts();

      if (totalResultados() === 0) {
        show('emptyState');
        document.getElementById('emptyQuery').textContent = query;
        hideEl('ctaBottom');
      } else {
        renderResultados();
        actualizarInfo();
        showEl('ctaBottom');
      }
    } catch (err) {
      if (err.name === 'AbortError') return;
      setLoading(false);
      show('emptyState');
      document.getElementById('emptyQuery').textContent = query;
    }
  }

  function buscarRapido(q) {
    document.getElementById('searchInput').value = q;
    document.getElementById('clearBtn').classList.add('visible');
    buscar(q);
  }

  // ── EVENTOS BÚSQUEDA ──
  const inp = document.getElementById('searchInput');
  const clearBtn = document.getElementById('clearBtn');

  inp.addEventListener('input', () => {
    clearBtn.classList.toggle('visible', inp.value.length > 0);
  });

  clearBtn.addEventListener('click', () => {
    inp.value = '';
    clearBtn.classList.remove('visible');
    ultimaBusqueda = '';
    datos = { candidatos: [], empresas: [], empleos: [], convocatorias: [] };
    hide('resultsInfo');
    hide('emptyState');
    hide('loadingState');
    showEl('estadoInicial');
    hideEl('ctaBottom');
    document.getElementById('cnt-todos').textContent = '—';
    document.getElementById('cnt-candidatos').textContent = '—';
    document.getElementById('cnt-empresas').textContent = '—';
    document.getElementById('cnt-empleos').textContent = '—';
    document.getElementById('cnt-convocatorias').textContent = '—';
    ['todos-candidatos-wrap','todos-empresas-wrap','todos-empleos-wrap','todos-convocatorias-wrap',
     'grid-candidatos','grid-empresas','grid-empleos','grid-convocatorias'].forEach(id => {
      const el = document.getElementById(id); if (el) el.innerHTML = '';
    });
    inp.focus();
  });

  let debounceT;
  inp.addEventListener('keydown', e => {
    if (e.key === 'Enter') { clearTimeout(debounceT); buscar(inp.value); }
    else {
      clearTimeout(debounceT);
      debounceT = setTimeout(() => { if (inp.value.length >= 2) buscar(inp.value); }, 420);
    }
  });

  document.getElementById('searchBtn').addEventListener('click', () => buscar(inp.value));

  // ── URL PARAMS (busqueda desde otra página) ──
  const urlQ = new URLSearchParams(location.search).get('q');
  if (urlQ) {
    inp.value = urlQ;
    clearBtn.classList.add('visible');
    buscar(urlQ);
  }
</script>

<script src="js/sesion_widget.js"></script>
</body>
</html>
