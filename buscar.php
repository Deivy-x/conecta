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
  <link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html{scroll-behavior:smooth}
    :root{
      --ink:#0a0e1a;--blue:#1648e8;--blue2:#4f80ff;--teal:#0d9488;
      --amber:#f59e0b;--violet:#7c3aed;--surface:#f4f6fb;
      --card:#ffffff;--border:#e1e6f0;--muted:#6b7a99;--radius:14px;
    }
    body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--surface);color:var(--ink);min-height:100vh;overflow-x:hidden}
    /* NAVBAR */
    .navbar{position:fixed;top:0;left:0;width:100%;height:66px;display:flex;align-items:center;justify-content:space-between;padding:0 32px;background:rgba(255,255,255,.96);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);z-index:1000;transition:box-shadow .3s}
    .nav-left{display:flex;align-items:center;gap:16px}
    .logo-navbar{height:40px;width:auto;object-fit:contain}
    .nav-search-wrap{position:relative;flex:1;max-width:360px;margin:0 16px}
    .nav-search-wrap input{width:100%;background:var(--surface);border:1.5px solid var(--border);border-radius:40px;padding:9px 16px 9px 40px;font-size:14px;font-family:inherit;color:var(--ink);outline:none;transition:border-color .2s,box-shadow .2s}
    .nav-search-wrap input:focus{border-color:var(--blue2);box-shadow:0 0 0 3px rgba(79,128,255,.12);background:#fff}
    .nav-search-wrap .ns-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;pointer-events:none}
    .nav-center{display:flex;align-items:center;gap:2px}
    .nav-link{display:flex;flex-direction:column;align-items:center;padding:8px 12px;border-radius:10px;color:var(--muted);text-decoration:none;font-size:11px;font-weight:700;gap:3px;transition:color .2s,background .2s;white-space:nowrap}
    .nav-link .nl-icon{font-size:17px}
    .nav-link:hover{color:var(--blue);background:rgba(22,72,232,.05)}
    .nav-link.active{color:var(--blue);border-bottom:2px solid var(--blue)}
    .nav-right{display:flex;align-items:center;gap:10px}
    .btn-login{color:var(--blue);border:1.5px solid var(--blue);padding:7px 16px;border-radius:30px;text-decoration:none;font-weight:700;font-size:13px;transition:all .2s}
    .btn-login:hover{background:var(--blue);color:#fff}
    .btn-reg{background:var(--blue);color:#fff;padding:8px 18px;border-radius:25px;text-decoration:none;font-weight:700;font-size:13px;box-shadow:0 4px 14px rgba(22,72,232,.3);transition:all .2s}
    .btn-reg:hover{background:#1038c0;transform:translateY(-1px)}
    .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;background:none;border:none;padding:4px}
    .hamburger span{display:block;width:22px;height:2px;background:var(--ink);border-radius:4px;transition:all .3s}
    .hamburger.open span:nth-child(1){transform:translateY(7px) rotate(45deg)}
    .hamburger.open span:nth-child(2){opacity:0}
    .hamburger.open span:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
    .mobile-menu{display:none;position:fixed;top:66px;left:0;width:100%;background:#fff;border-bottom:1px solid var(--border);box-shadow:0 12px 32px rgba(0,0,0,.1);flex-direction:column;padding:16px 24px;gap:4px;z-index:999}
    .mobile-menu.open{display:flex}
    .mobile-menu a{color:#333;text-decoration:none;font-size:15px;font-weight:500;padding:10px 0;border-bottom:1px solid rgba(0,0,0,.05)}
    .mobile-auth{display:flex;gap:10px;margin-top:12px}
    .mobile-auth a{flex:1;text-align:center;padding:10px;border-radius:25px;font-weight:700;text-decoration:none}
    .m-login{border:2px solid var(--blue);color:var(--blue)}
    .m-reg{background:var(--blue);color:#fff}
    /* HERO */
    .search-hero{padding:90px 24px 0;background:linear-gradient(150deg,#060b18 0%,#0d1530 50%,#0a1225 100%);position:relative;overflow:hidden;min-height:256px}
    .hero-bg-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(79,128,255,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(79,128,255,.07) 1px,transparent 1px);background-size:40px 40px;pointer-events:none}
    .hero-glow{position:absolute;width:600px;height:300px;border-radius:50%;background:radial-gradient(ellipse at center,rgba(22,72,232,.18) 0%,transparent 70%);top:-80px;left:50%;transform:translateX(-50%);pointer-events:none}
    .hero-inner{position:relative;z-index:2;max-width:660px;margin:0 auto;text-align:center;padding-bottom:0}
    .hero-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(79,128,255,.12);border:1px solid rgba(79,128,255,.3);color:#93c5fd;font-size:11px;font-weight:700;letter-spacing:.7px;text-transform:uppercase;padding:5px 14px;border-radius:30px;margin-bottom:16px}
    .hero-inner h1{font-size:clamp(28px,4.5vw,46px);font-weight:800;color:#fff;line-height:1.15;margin-bottom:10px}
    .hero-inner h1 em{color:#60a5fa;font-style:normal}
    .hero-inner .sub{color:rgba(255,255,255,.55);font-size:14px;margin-bottom:30px}
    /* MEGA SEARCH */
    .mega-search{position:relative;z-index:10;max-width:680px;margin:0 auto;transform:translateY(28px)}
    .ms-box{display:flex;background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.3),0 0 0 1px rgba(255,255,255,.1);overflow:hidden;transition:box-shadow .3s}
    .ms-box:focus-within{box-shadow:0 20px 60px rgba(0,0,0,.35),0 0 0 3px rgba(22,72,232,.25)}
    .ms-icon{padding:0 18px;font-size:18px;color:#94a3b8;flex-shrink:0;display:flex;align-items:center}
    #searchInput{flex:1;border:none;outline:none;font-size:16px;font-family:inherit;color:var(--ink);padding:17px 0;background:transparent}
    #searchInput::placeholder{color:#aab4c8}
    #clearBtn{padding:0 12px;font-size:16px;color:#aab4c8;background:none;border:none;cursor:pointer;display:none;align-items:center}
    #clearBtn.visible{display:flex}
    .ms-divider{width:1px;height:30px;background:#e8eef7;flex-shrink:0;align-self:center}
    #searchBtn{display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;border:none;padding:12px 24px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .2s;white-space:nowrap;margin:5px;border-radius:14px}
    #searchBtn:hover{opacity:.88}
    /* STATS */
    .stats-bar{position:relative;z-index:3;display:flex;justify-content:center;gap:36px;padding:52px 24px 18px;flex-wrap:wrap}
    .stat-item{text-align:center}
    .stat-n{font-size:22px;font-weight:800;color:var(--ink)}
    .stat-l{font-size:12px;color:var(--muted);font-weight:500;margin-top:2px}
    /* LAYOUT */
    .page-layout{max-width:1200px;margin:0 auto;padding:0 20px 80px;display:grid;grid-template-columns:250px 1fr 270px;gap:22px;align-items:start}
    /* SIDEBAR LEFT */
    .sidebar-left{position:sticky;top:80px}
    .sidebar-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:14px}
    .sc-header{padding:13px 16px 10px;border-bottom:1px solid var(--border);font-size:13px;font-weight:700;color:var(--ink);display:flex;align-items:center;justify-content:space-between}
    .sc-badge{background:var(--blue);color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px}
    .filter-group{padding:10px 16px}
    .filter-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
    .filter-chips{display:flex;flex-wrap:wrap;gap:6px}
    .fchip{background:var(--surface);border:1.5px solid var(--border);color:#475569;font-size:11px;font-weight:600;padding:4px 11px;border-radius:30px;cursor:pointer;transition:all .18s;user-select:none}
    .fchip:hover{border-color:var(--blue2);color:var(--blue);background:#eff4ff}
    .fchip.on{background:var(--blue);border-color:var(--blue);color:#fff}
    .fdivider{height:1px;background:var(--border);margin:0}
    .quick-list{padding:6px 0}
    .ql-item{display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:pointer;font-size:12px;color:#334155;font-weight:600;transition:background .15s;border-radius:8px;margin:0 4px}
    .ql-item:hover{background:var(--surface);color:var(--blue)}
    .ql-icon{font-size:14px;width:20px;text-align:center}
    /* FEED COL */
    .feed-col{min-width:0}
    .tabs-row{display:flex;gap:4px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:5px;margin-bottom:16px;overflow-x:auto}
    .tab-btn{display:flex;align-items:center;gap:6px;padding:8px 14px;border:none;background:transparent;font-size:12px;font-weight:700;color:var(--muted);cursor:pointer;font-family:inherit;border-radius:10px;white-space:nowrap;transition:all .18s;flex-shrink:0}
    .tab-btn:hover{color:var(--blue);background:rgba(22,72,232,.05)}
    .tab-btn.active{color:var(--blue);background:rgba(22,72,232,.08)}
    .tc{background:#eff4ff;color:var(--blue);font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;min-width:20px;text-align:center}
    .tab-btn.active .tc{background:var(--blue);color:#fff}
    .estado-inicial{text-align:center;padding:56px 20px;background:var(--card);border-radius:var(--radius);border:1px solid var(--border)}
    .estado-inicial .ei-icon{font-size:48px;display:block;margin-bottom:14px}
    .estado-inicial h2{font-size:20px;font-weight:800;margin-bottom:8px}
    .estado-inicial p{color:var(--muted);font-size:14px;max-width:360px;margin:0 auto 18px}
    .quick-tags{display:flex;gap:7px;flex-wrap:wrap;justify-content:center}
    .quick-tag{background:var(--surface);border:1.5px solid var(--border);color:#475569;padding:6px 13px;border-radius:25px;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s}
    .quick-tag:hover{border-color:var(--blue);color:var(--blue);background:#eff4ff}
    .loading-state{display:none;text-align:center;padding:48px 20px}
    .loading-state.visible{display:block}
    .spin{display:inline-block;width:42px;height:42px;border:3px solid var(--border);border-top-color:var(--blue);border-radius:50%;animation:spin .75s linear infinite;margin-bottom:12px}
    @keyframes spin{to{transform:rotate(360deg)}}
    .loading-state p{color:var(--muted);font-size:14px}
    .empty-state{display:none;text-align:center;padding:48px 20px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius)}
    .empty-state.visible{display:block}
    .empty-state .e-icon{font-size:44px;display:block;margin-bottom:12px}
    .empty-state h3{font-size:17px;font-weight:800;margin-bottom:6px}
    .empty-state p{color:var(--muted);font-size:13px}
    .results-info{display:none;font-size:13px;color:var(--muted);margin-bottom:13px;padding:0 2px}
    .results-info.visible{display:block}
    .results-info strong{color:var(--ink)}
    .panel{display:none}
    .panel.active{display:block}
    /* GRIDS */
    .grid-cands{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:13px}
    .grid-emps{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:13px}
    .grid-list{display:flex;flex-direction:column;gap:11px}
    /* CARD CANDIDATO */
    .card-candidato{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px;transition:all .22s;text-decoration:none;color:inherit;display:block;position:relative}
    .card-candidato:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.1);border-color:#c7d7ff}
    .cc-avatar{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;color:#fff;margin-bottom:11px;overflow:hidden}
    .cc-avatar img{width:100%;height:100%;object-fit:cover}
    .badge-v{position:absolute;top:12px;right:12px;font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px}
    .bv-blue{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd}
    .bv-purple{background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe}
    .cc-name{font-size:14px;font-weight:700;margin-bottom:2px}
    .cc-prof{color:var(--blue);font-size:11px;font-weight:600;margin-bottom:2px}
    .cc-loc{font-size:11px;color:var(--muted);margin-bottom:9px}
    .cc-skills{display:flex;flex-wrap:wrap;gap:4px}
    .skill-tag{background:var(--surface);color:#475569;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:500}
    /* CARD EMPRESA */
    .card-empresa{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px;transition:all .22s;text-decoration:none;color:inherit;display:block;position:relative}
    .card-empresa:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.1);border-color:#c7d7ff}
    .ce-avatar{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;color:#fff;margin-bottom:11px;overflow:hidden}
    .ce-avatar img{width:100%;height:100%;object-fit:cover}
    .ce-name{font-size:14px;font-weight:700;margin-bottom:2px}
    .ce-sector{color:var(--blue);font-size:11px;font-weight:600;margin-bottom:2px}
    .ce-loc{font-size:11px;color:var(--muted);margin-bottom:9px}
    .ce-chip{background:var(--surface);color:#475569;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:500;display:inline-block}
    /* CARD EMPLEO */
    .card-empleo{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;display:flex;align-items:center;gap:14px;transition:all .22s;text-decoration:none;color:inherit}
    .card-empleo:hover{box-shadow:0 6px 22px rgba(0,0,0,.08);border-color:#bfdbfe;transform:translateX(3px)}
    .emp-ico{width:44px;height:44px;flex-shrink:0;background:linear-gradient(135deg,#eff4ff,#dbeafe);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:19px}
    .emp-info{flex:1;min-width:0}
    .emp-title{font-size:14px;font-weight:700;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .emp-co{font-size:12px;color:var(--muted);margin-bottom:5px}
    .emp-chips{display:flex;flex-wrap:wrap;gap:4px}
    .chip{font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px}
    .ch-blue{background:#eff4ff;color:var(--blue)}
    .ch-green{background:#f0fdf4;color:#166534}
    .ch-gray{background:var(--surface);color:#475569}
    .ch-amber{background:#fffbeb;color:#92400e}
    .ch-violet{background:#f5f3ff;color:#6d28d9}
    .emp-arr{color:#cbd5e1;font-size:17px;flex-shrink:0}
    /* CARD CONVOCATORIA */
    .card-conv{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;display:flex;align-items:center;gap:14px;transition:all .22s;text-decoration:none;color:inherit}
    .card-conv:hover{box-shadow:0 6px 22px rgba(0,0,0,.08);border-color:#bbf7d0;transform:translateX(3px)}
    .conv-ico{width:44px;height:44px;flex-shrink:0;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px}
    .conv-info{flex:1;min-width:0}
    .conv-title{font-size:14px;font-weight:700;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .conv-co{font-size:12px;color:var(--muted);margin-bottom:5px}
    .conv-chips{display:flex;flex-wrap:wrap;gap:4px}
    .conv-arr{color:#cbd5e1;font-size:17px;flex-shrink:0}
    /* SECTION HEADER */
    .sec-hdr{display:flex;align-items:center;justify-content:space-between;margin:22px 0 12px;padding-bottom:9px;border-bottom:1px solid var(--border)}
    .sec-hdr h3{font-size:15px;font-weight:800}
    .sec-hdr a{font-size:12px;font-weight:700;color:var(--blue);text-decoration:none}
    .sec-hdr a:hover{text-decoration:underline}
    /* SIDEBAR RIGHT */
    .sidebar-right{position:sticky;top:80px}
    .sr-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:14px}
    .sr-card h4{font-size:13px;font-weight:800;margin-bottom:13px}
    .dest-item{display:flex;align-items:center;gap:11px;padding:9px 0;border-bottom:1px solid var(--border);cursor:pointer;text-decoration:none;color:inherit}
    .dest-item:last-child{border-bottom:none}
    .dest-item:hover .di-name{color:var(--blue)}
    .di-logo{width:38px;height:38px;border-radius:9px;overflow:hidden;background:linear-gradient(135deg,#1648e8,#4f80ff);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:13px;flex-shrink:0}
    .di-logo img{width:100%;height:100%;object-fit:cover}
    .di-name{font-size:13px;font-weight:700}
    .di-sub{font-size:11px;color:var(--muted)}
    .trend-item{padding:8px 0;border-bottom:1px solid var(--border);cursor:pointer}
    .trend-item:last-child{border-bottom:none}
    .trend-item:hover .tr-text{color:var(--blue)}
    .tr-cat{font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px}
    .tr-text{font-size:13px;font-weight:700;margin:2px 0}
    .tr-count{font-size:11px;color:var(--muted)}
    .cta-card{background:linear-gradient(135deg,#0a0e1a,#1a2440);border-radius:var(--radius);padding:20px;text-align:center}
    .cta-card h4{font-size:14px;font-weight:800;color:#fff;margin-bottom:6px}
    .cta-card p{font-size:12px;color:rgba(255,255,255,.5);margin-bottom:14px;line-height:1.5}
    .cta-card a{display:block;background:var(--blue);color:#fff;padding:9px;border-radius:25px;font-weight:700;font-size:12px;text-decoration:none;transition:opacity .2s;margin-bottom:7px}
    .cta-card a:hover{opacity:.88}
    .cta-card a.outline{background:transparent;border:1.5px solid rgba(255,255,255,.2);font-weight:600}
    .cta-card a.outline:hover{border-color:#60a5fa;color:#60a5fa}
    footer{background:#0a0e1a;border-top:1px solid rgba(255,255,255,.06);color:rgba(255,255,255,.4);text-align:center;padding:20px;font-size:13px}
    footer span{color:#60a5fa}
    @media(max-width:1100px){.page-layout{grid-template-columns:230px 1fr}.sidebar-right{display:none}}
    @media(max-width:768px){.navbar{padding:0 16px}.nav-center,.nav-right,.nav-search-wrap{display:none}.hamburger{display:flex}.search-hero{padding:78px 16px 0}.page-layout{grid-template-columns:1fr;padding:0 12px 60px}.sidebar-left{display:none}.stats-bar{gap:20px}}
  </style>
</head>
<body>
<header class="navbar" id="navbar">
  <div class="nav-left">
    <img src="Imagenes/quibdo_desco_new.png" alt="Quibdó Conecta" class="logo-navbar">
  </div>
  <div class="nav-search-wrap">
    <span class="ns-icon">🔍</span>
    <input type="text" placeholder="Buscar en QuibdóConecta…" id="navSearchInput">
  </div>
  <nav class="nav-center">
    <a href="index.html" class="nav-link"><span class="nl-icon">🏠</span>Inicio</a>
    <a href="Empleo.php" class="nav-link"><span class="nl-icon">💼</span>Empleos</a>
    <a href="talentos.php" class="nav-link"><span class="nl-icon">🌟</span>Talento</a>
    <a href="empresas.php" class="nav-link"><span class="nl-icon">🏢</span>Empresas</a>
    <a href="buscar.php" class="nav-link active"><span class="nl-icon">🔍</span>Buscar</a>
    <a href="convocatorias.php" class="nav-link"><span class="nl-icon">📋</span>Convocatorias</a>
  </nav>
  <div class="nav-right">
    <a href="inicio_sesion.php" class="btn-login">Iniciar sesión</a>
    <a href="registro.php" class="btn-reg">Registrarse</a>
  </div>
  <button class="hamburger" id="hamburger" aria-label="Menú"><span></span><span></span><span></span></button>
</header>
<div class="mobile-menu" id="mobileMenu">
  <a href="index.html">🏠 Inicio</a>
  <a href="Empleo.php">💼 Empleos</a>
  <a href="talentos.php">🌟 Talento</a>
  <a href="empresas.php">🏢 Empresas</a>
  <a href="buscar.php">🔍 Buscar</a>
  <a href="convocatorias.php">📋 Convocatorias</a>
  <a href="Ayuda.html">❓ Ayuda</a>
  <div class="mobile-auth">
    <a href="inicio_sesion.php" class="m-login">Iniciar sesión</a>
    <a href="registro.php" class="m-reg">Registrarse</a>
  </div>
</div>
<section class="search-hero">
  <div class="hero-bg-grid"></div>
  <div class="hero-glow"></div>
  <div class="hero-inner">
    <span class="hero-badge">🌐 Búsqueda Global · QuibdóConecta</span>
    <h1>Encuentra <em>talento, empleos</em><br>y oportunidades en el Chocó</h1>
    <p class="sub">Candidatos, empresas, convocatorias y más — todo en un solo lugar</p>
    <div class="mega-search">
      <div class="ms-box">
        <div class="ms-icon">🔍</div>
        <input type="text" id="searchInput" placeholder="Nombre, profesión, empresa, cargo, ciudad…" autocomplete="off" autofocus>
        <button id="clearBtn" aria-label="Limpiar">✕</button>
        <div class="ms-divider"></div>
        <button id="searchBtn">Buscar →</button>
      </div>
    </div>
  </div>
</section>
<div class="stats-bar">
  <div class="stat-item"><div class="stat-n" id="stat-n1">—</div><div class="stat-l">Candidatos</div></div>
  <div class="stat-item"><div class="stat-n" id="stat-n2">—</div><div class="stat-l">Empresas</div></div>
  <div class="stat-item"><div class="stat-n" id="stat-n3">—</div><div class="stat-l">Empleos activos</div></div>
  <div class="stat-item"><div class="stat-n" id="stat-n4">—</div><div class="stat-l">Convocatorias</div></div>
</div>
<div class="page-layout">
  <aside class="sidebar-left">
    <div class="sidebar-card">
      <div class="sc-header">Filtros <span class="sc-badge" id="filtros-n">0</span></div>
      <div class="filter-group">
        <div class="filter-label">Tipo de resultado</div>
        <div class="filter-chips">
          <span class="fchip on" data-filter="tipo" data-val="todos" onclick="toggleFilter(this)">Todos</span>
          <span class="fchip" data-filter="tipo" data-val="candidatos" onclick="toggleFilter(this)">👤 Candidatos</span>
          <span class="fchip" data-filter="tipo" data-val="empresas" onclick="toggleFilter(this)">🏢 Empresas</span>
          <span class="fchip" data-filter="tipo" data-val="empleos" onclick="toggleFilter(this)">💼 Empleos</span>
          <span class="fchip" data-filter="tipo" data-val="convocatorias" onclick="toggleFilter(this)">📋 Convocatorias</span>
        </div>
      </div>
      <div class="fdivider"></div>
      <div class="filter-group">
        <div class="filter-label">Ciudad</div>
        <div class="filter-chips">
          <span class="fchip" onclick="buscarRapido('Quibdó')">📍 Quibdó</span>
          <span class="fchip" onclick="buscarRapido('Istmina')">📍 Istmina</span>
          <span class="fchip" onclick="buscarRapido('Condoto')">📍 Condoto</span>
          <span class="fchip" onclick="buscarRapido('Tadó')">📍 Tadó</span>
        </div>
      </div>
      <div class="fdivider"></div>
      <div class="filter-group">
        <div class="filter-label">Sector</div>
        <div class="filter-chips">
          <span class="fchip" onclick="buscarRapido('tecnología')">💻 Tech</span>
          <span class="fchip" onclick="buscarRapido('salud')">🏥 Salud</span>
          <span class="fchip" onclick="buscarRapido('educación')">📚 Educación</span>
          <span class="fchip" onclick="buscarRapido('comercio')">🛒 Comercio</span>
          <span class="fchip" onclick="buscarRapido('construcción')">🏗️ Construcción</span>
          <span class="fchip" onclick="buscarRapido('gobierno')">🏛️ Gobierno</span>
        </div>
      </div>
    </div>
    <div class="sidebar-card">
      <div class="sc-header">Búsquedas populares</div>
      <div class="quick-list">
        <div class="ql-item" onclick="buscarRapido('desarrollador')"><span class="ql-icon">💻</span> Desarrollador</div>
        <div class="ql-item" onclick="buscarRapido('diseño gráfico')"><span class="ql-icon">🎨</span> Diseño gráfico</div>
        <div class="ql-item" onclick="buscarRapido('administración')"><span class="ql-icon">📊</span> Administración</div>
        <div class="ql-item" onclick="buscarRapido('contabilidad')"><span class="ql-icon">🧮</span> Contabilidad</div>
        <div class="ql-item" onclick="buscarRapido('ingeniería')"><span class="ql-icon">⚙️</span> Ingeniería</div>
        <div class="ql-item" onclick="buscarRapido('marketing')"><span class="ql-icon">📣</span> Marketing</div>
        <div class="ql-item" onclick="buscarRapido('emprendimiento')"><span class="ql-icon">🚀</span> Emprendimiento</div>
        <div class="ql-item" onclick="buscarRapido('remoto')"><span class="ql-icon">🌐</span> Trabajo remoto</div>
      </div>
    </div>
  </aside>
  <main class="feed-col">
    <div class="tabs-row" id="tabsWrap">
      <button class="tab-btn active" data-tab="todos">🌐 Todos <span class="tc" id="cnt-todos">—</span></button>
      <button class="tab-btn" data-tab="candidatos">👤 Candidatos <span class="tc" id="cnt-candidatos">—</span></button>
      <button class="tab-btn" data-tab="empresas">🏢 Empresas <span class="tc" id="cnt-empresas">—</span></button>
      <button class="tab-btn" data-tab="empleos">💼 Empleos <span class="tc" id="cnt-empleos">—</span></button>
      <button class="tab-btn" data-tab="convocatorias">📋 Convocatorias <span class="tc" id="cnt-convocatorias">—</span></button>
    </div>
    <div class="results-info" id="resultsInfo"></div>
    <div class="loading-state" id="loadingState"><div class="spin"></div><p>Buscando en toda la plataforma…</p></div>
    <div class="empty-state" id="emptyState"><span class="e-icon">🔍</span><h3>Sin resultados</h3><p>No encontramos nada para "<span id="emptyQuery"></span>".<br>Prueba con otro término.</p></div>
    <div class="estado-inicial" id="estadoInicial">
      <span class="ei-icon">✨</span>
      <h2>¿Qué buscas hoy?</h2>
      <p>Candidatos, empresas, empleos y convocatorias del Chocó te esperan.</p>
      <div class="quick-tags">
        <span class="quick-tag" onclick="buscarRapido('desarrollador')">💻 Dev</span>
        <span class="quick-tag" onclick="buscarRapido('diseño')">🎨 Diseño</span>
        <span class="quick-tag" onclick="buscarRapido('tecnología')">🚀 Tecnología</span>
        <span class="quick-tag" onclick="buscarRapido('salud')">🏥 Salud</span>
        <span class="quick-tag" onclick="buscarRapido('educación')">📚 Educación</span>
        <span class="quick-tag" onclick="buscarRapido('Quibdó')">📍 Quibdó</span>
        <span class="quick-tag" onclick="buscarRapido('administración')">📊 Admin</span>
        <span class="quick-tag" onclick="buscarRapido('convocatoria')">📋 Convocatorias</span>
      </div>
    </div>
    <div class="panel active" id="panel-todos"><div id="todos-cands-wrap"></div><div id="todos-emps-wrap"></div><div id="todos-empleos-wrap"></div><div id="todos-convs-wrap"></div></div>
    <div class="panel" id="panel-candidatos"><div class="grid-cands" id="grid-candidatos"></div></div>
    <div class="panel" id="panel-empresas"><div class="grid-emps" id="grid-empresas"></div></div>
    <div class="panel" id="panel-empleos"><div class="grid-list" id="grid-empleos"></div></div>
    <div class="panel" id="panel-convocatorias"><div class="grid-list" id="grid-convocatorias"></div></div>
  </main>
  <aside class="sidebar-right">
    <div class="sr-card">
      <h4>🏢 Empresas encontradas</h4>
      <div id="dest-empresas"><div class="dest-item" style="opacity:.35"><div class="di-logo">QC</div><div><div class="di-name">Busca algo</div><div class="di-sub">para ver empresas</div></div></div></div>
    </div>
    <div class="sr-card">
      <h4>📈 Tendencias en el Chocó</h4>
      <div class="trend-item" onclick="buscarRapido('tecnología')"><div class="tr-cat">Sector · Tecnología</div><div class="tr-text">Empleos en Tech 💻</div><div class="tr-count">Creciente demanda</div></div>
      <div class="trend-item" onclick="buscarRapido('convocatoria')"><div class="tr-cat">Oportunidades</div><div class="tr-text">Convocatorias abiertas 📋</div><div class="tr-count">Actualizadas esta semana</div></div>
      <div class="trend-item" onclick="buscarRapido('salud')"><div class="tr-cat">Sector · Salud</div><div class="tr-text">Profesionales de salud 🏥</div><div class="tr-count">Alta búsqueda</div></div>
      <div class="trend-item" onclick="buscarRapido('remoto')"><div class="tr-cat">Modalidad</div><div class="tr-text">Trabajo remoto 🌐</div><div class="tr-count">Tendencia global</div></div>
    </div>
    <div class="cta-card">
      <h4>¿Eres talento del Chocó?</h4>
      <p>Regístrate gratis y conecta con empresas que buscan tu perfil.</p>
      <a href="registro.php">✨ Crear perfil gratis</a>
      <a href="Empleo.php" class="outline">Ver empleos →</a>
    </div>
  </aside>
</div>
<footer><p>© 2026 <span>QuibdóConecta</span> — Conectando el talento del Chocó con el mundo.</p></footer>
<script>
window.addEventListener("scroll",()=>{document.getElementById("navbar").style.boxShadow=window.scrollY>30?"0 4px 24px rgba(0,0,0,.12)":"none"});
const ham=document.getElementById("hamburger"),mob=document.getElementById("mobileMenu");
ham.addEventListener("click",()=>{ham.classList.toggle("open");mob.classList.toggle("open")});
document.addEventListener("click",e=>{if(!ham.contains(e.target)&&!mob.contains(e.target)){ham.classList.remove("open");mob.classList.remove("open")}});
const navInp=document.getElementById("navSearchInput"),mainInp=document.getElementById("searchInput");
navInp.addEventListener("keydown",e=>{if(e.key==="Enter"&&navInp.value.trim()){mainInp.value=navInp.value;buscar(navInp.value)}});
navInp.addEventListener("input",()=>{mainInp.value=navInp.value});
let tabActual="todos",ultimaBusqueda="",datos={candidatos:[],empresas:[],empleos:[],convocatorias:[]};
function toggleFilter(el){document.querySelectorAll(".fchip[data-filter=tipo]").forEach(c=>c.classList.remove("on"));el.classList.add("on");const v=el.dataset.val;switchTab(v==="todos"?"todos":v)}
document.querySelectorAll(".tab-btn").forEach(btn=>{btn.addEventListener("click",()=>switchTab(btn.dataset.tab))});
function switchTab(tab){tabActual=tab;document.querySelectorAll(".tab-btn").forEach(b=>b.classList.toggle("active",b.dataset.tab===tab));document.querySelectorAll(".panel").forEach(p=>p.classList.remove("active"));document.getElementById("panel-"+tab).classList.add("active");actualizarInfo()}
function show(id){document.getElementById(id)?.classList.add("visible")}
function hide(id){document.getElementById(id)?.classList.remove("visible")}
function showEl(id){const el=document.getElementById(id);if(el)el.style.display=""}
function hideEl(id){const el=document.getElementById(id);if(el)el.style.display="none"}
function total(){return datos.candidatos.length+datos.empresas.length+datos.empleos.length+datos.convocatorias.length}
function esc(s){return String(s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;")}
function setLoading(on){if(on){show("loadingState");hide("emptyState");hide("resultsInfo");hideEl("estadoInicial")}else hide("loadingState")}
function actualizarCounts(){const t=total();document.getElementById("cnt-todos").textContent=t;document.getElementById("cnt-candidatos").textContent=datos.candidatos.length;document.getElementById("cnt-empresas").textContent=datos.empresas.length;document.getElementById("cnt-empleos").textContent=datos.empleos.length;document.getElementById("cnt-convocatorias").textContent=datos.convocatorias.length;document.getElementById("stat-n1").textContent=datos.candidatos.length;document.getElementById("stat-n2").textContent=datos.empresas.length;document.getElementById("stat-n3").textContent=datos.empleos.length;document.getElementById("stat-n4").textContent=datos.convocatorias.length}
function actualizarInfo(){const n=tabActual==="todos"?total():(datos[tabActual]?.length??0);document.getElementById("resultsInfo").innerHTML="<strong>"+n+"</strong> resultado"+(n!==1?"s":"")+" para "<strong>"+esc(ultimaBusqueda)+"</strong>"";if(n>0)show("resultsInfo")}
function timeAgo(d){if(!d)return"";const diff=Math.floor((Date.now()-new Date(d))/1000);if(diff<60)return"hace un momento";if(diff<3600)return"hace "+Math.floor(diff/60)+"min";if(diff<86400)return"hace "+Math.floor(diff/3600)+"h";if(diff<2592000)return"hace "+Math.floor(diff/86400)+" días";return"hace "+Math.floor(diff/2592000)+" meses"}
function cardCandidato(c){const ini=(c.nombre||"").substring(0,2).toUpperCase(),grad=c.avatar_color||"linear-gradient(135deg,#1f9d55,#2ecc71)",skills=(c.skills||"").split(",").filter(Boolean).slice(0,3),v=parseInt(c.verificado)?'<span class="badge-v bv-blue">✓ Verificado</span>':"",d=parseInt(c.destacado)?'<span class="badge-v bv-purple">🏅 Dest.</span>':"",foto=c.foto?'<img src="uploads/'+esc(c.foto)+'" alt="'+ini+'" onerror="this.style.display='none';this.parentNode.textContent=''+ini+'">':(ini);return'<a href="perfil.php?id='+c.id+'&tipo=candidato" class="card-candidato">'+( v||d)+'<div class="cc-avatar" style="background:'+grad+'">'+foto+'</div><div class="cc-name">'+esc(c.nombre)+" "+esc(c.apellido||"")+'</div><div class="cc-prof">🏷️ '+esc(c.profesion||"Profesional")+'</div><div class="cc-loc">📍 '+esc(c.ciudad||"Chocó")+'</div><div class="cc-skills">'+skills.map(s=>'<span class="skill-tag">'+esc(s.trim())+"</span>").join("")+"</div></a>"}
function cardEmpresa(e){const nombre=e.nombre_empresa||e.nombre||"",ini=nombre.substring(0,2).toUpperCase(),grad=e.avatar_color||"linear-gradient(135deg,#1648e8,#4f80ff)",v=parseInt(e.verificado)?'<span class="badge-v bv-blue">✓ Verificada</span>':"",logo=e.logo?'<img src="uploads/logos/'+esc(e.logo)+'" alt="'+ini+'" onerror="this.style.display='none';this.parentNode.textContent=''+ini+'">':(ini);return'<a href="perfil.php?id='+e.id+'&tipo=empresa" class="card-empresa">'+v+'<div class="ce-avatar" style="background:'+grad+'">'+logo+'</div><div class="ce-name">'+esc(nombre)+'</div><div class="ce-sector">🏷️ '+esc(e.sector||"Empresa")+'</div><div class="ce-loc">📍 '+esc(e.ciudad||"Chocó")+'</div><span class="ce-chip">'+esc(e.sector||"Local")+"</span></a>"}
function cardEmpleo(e){const m=e.modalidad||"",s=e.salario_texto||"",c=e.ciudad||"Quibdó",t=timeAgo(e.creado_en);return'<a href="Empleo.php" class="card-empleo"><div class="emp-ico">💼</div><div class="emp-info"><div class="emp-title">'+esc(e.titulo||"")+'</div><div class="emp-co">🏢 '+esc(e.empresa_nombre||"Empresa")+(t?" · "+t:"")+'</div><div class="emp-chips">'+( m?'<span class="chip ch-blue">'+esc(m)+"</span>":"")+( c?'<span class="chip ch-gray">📍 '+esc(c)+"</span>":"")+( s?'<span class="chip ch-green">💰 '+esc(s)+"</span>":"")+'</div></div><span class="emp-arr">›</span></a>'}
function cardConvocatoria(c){const icon=c.icono||"📋",chipE=c.estado==="abierta"?'<span class="chip ch-green">🟢 Abierta</span>':' <span class="chip ch-gray">⏸ Cerrada</span>',href=c.url_externa?(c.url_externa.startsWith("http")?c.url_externa:"https://"+c.url_externa):"#";return'<a href="'+esc(href)+'" class="card-conv" target="'+( c.url_externa?"_blank":"_self")+'"><div class="conv-ico" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7)">'+icon+'</div><div class="conv-info"><div class="conv-title">'+esc(c.titulo||"")+'</div><div class="conv-co">🏛️ '+esc(c.entidad||"")+'</div><div class="conv-chips">'+chipE+(c.modalidad?'<span class="chip ch-violet">'+esc(c.modalidad)+"</span>":"")+(c.lugar?'<span class="chip ch-gray">📍 '+esc(c.lugar)+"</span>":"")+(c.salario?'<span class="chip ch-amber">💰 '+esc(c.salario)+"</span>":"")+'</div></div><span class="conv-arr">›</span></a>'}
function seccion(titulo,items,renderFn,gridClass,wrapId,tabId){const wrap=document.getElementById(wrapId);if(!wrap||!items?.length){if(wrap)wrap.innerHTML="";return}wrap.innerHTML='<div class="sec-hdr"><h3>'+titulo+'</h3><a href="#" onclick="switchTab(\''+tabId+'\');return false">Ver todos →</a></div><div class="'+gridClass+'">'+items.slice(0,4).map(renderFn).join("")+"</div>"}
function renderResultados(){seccion("👤 Candidatos",datos.candidatos,cardCandidato,"grid-cands","todos-cands-wrap","candidatos");seccion("🏢 Empresas",datos.empresas,cardEmpresa,"grid-emps","todos-emps-wrap","empresas");seccion("💼 Empleos",datos.empleos,cardEmpleo,"grid-list","todos-empleos-wrap","empleos");seccion("📋 Convocatorias",datos.convocatorias,cardConvocatoria,"grid-list","todos-convs-wrap","convocatorias");document.getElementById("grid-candidatos").innerHTML=datos.candidatos.map(cardCandidato).join("")||'<p style="color:#94a3b8;padding:14px 0">Sin candidatos.</p>';document.getElementById("grid-empresas").innerHTML=datos.empresas.map(cardEmpresa).join("")||'<p style="color:#94a3b8;padding:14px 0">Sin empresas.</p>';document.getElementById("grid-empleos").innerHTML=datos.empleos.map(cardEmpleo).join("")||'<p style="color:#94a3b8;padding:14px 0">Sin empleos.</p>';document.getElementById("grid-convocatorias").innerHTML=datos.convocatorias.map(cardConvocatoria).join("")||'<p style="color:#94a3b8;padding:14px 0">Sin convocatorias.</p>';actualizarSidebarEmpresas()}
function actualizarSidebarEmpresas(){const wrap=document.getElementById("dest-empresas");if(!datos.empresas.length){wrap.innerHTML='<p style="color:#94a3b8;font-size:12px;padding:6px 0">No se encontraron empresas.</p>';return}wrap.innerHTML=datos.empresas.slice(0,4).map(e=>{const nombre=e.nombre_empresa||e.nombre||"?",ini=nombre.substring(0,2).toUpperCase(),grad=e.avatar_color||"linear-gradient(135deg,#1648e8,#4f80ff)",logo=e.logo?'<img src="uploads/logos/'+esc(e.logo)+'" alt="'+ini+'" onerror="this.style.display='none';this.parentNode.textContent=''+ini+'">':(ini);return'<a href="perfil.php?id='+e.id+'&tipo=empresa" class="dest-item"><div class="di-logo" style="background:'+grad+'">'+logo+'</div><div><div class="di-name">'+esc(nombre)+'</div><div class="di-sub">'+esc(e.sector||"Empresa")+" · "+esc(e.ciudad||"Chocó")+"</div></div></a>"}).join("")}
let abortCtrl=null;
async function buscar(query){query=(query||"").trim();if(!query||query===ultimaBusqueda)return;ultimaBusqueda=query;navInp.value=query;document.getElementById("clearBtn").classList.add("visible");if(abortCtrl)abortCtrl.abort();abortCtrl=new AbortController();setLoading(true);hide("emptyState");hide("resultsInfo");hideEl("estadoInicial");try{const r=await fetch("Php/search.php?q="+encodeURIComponent(query),{signal:abortCtrl.signal}),json=await r.json();datos={candidatos:json.candidatos||[],empresas:json.empresas||[],empleos:json.empleos||[],convocatorias:json.convocatorias||[]};setLoading(false);actualizarCounts();if(total()===0){show("emptyState");document.getElementById("emptyQuery").textContent=query;["todos-cands-wrap","todos-emps-wrap","todos-empleos-wrap","todos-convs-wrap","grid-candidatos","grid-empresas","grid-empleos","grid-convocatorias"].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=""})}else{renderResultados();actualizarInfo()}}catch(err){if(err.name==="AbortError")return;setLoading(false);show("emptyState");document.getElementById("emptyQuery").textContent=query}}
function buscarRapido(q){mainInp.value=q;ultimaBusqueda="";buscar(q)}
const inp=document.getElementById("searchInput"),clearBtn=document.getElementById("clearBtn");
inp.addEventListener("input",()=>{clearBtn.classList.toggle("visible",inp.value.length>0);navInp.value=inp.value});
clearBtn.addEventListener("click",()=>{inp.value="";navInp.value="";clearBtn.classList.remove("visible");ultimaBusqueda="";datos={candidatos:[],empresas:[],empleos:[],convocatorias:[]};hide("resultsInfo");hide("emptyState");hide("loadingState");showEl("estadoInicial");["cnt-todos","cnt-candidatos","cnt-empresas","cnt-empleos","cnt-convocatorias"].forEach(id=>{document.getElementById(id).textContent="—"});["stat-n1","stat-n2","stat-n3","stat-n4"].forEach(id=>{document.getElementById(id).textContent="—"});["todos-cands-wrap","todos-emps-wrap","todos-empleos-wrap","todos-convs-wrap","grid-candidatos","grid-empresas","grid-empleos","grid-convocatorias"].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=""});inp.focus()});
let dt;inp.addEventListener("keydown",e=>{if(e.key==="Enter"){clearTimeout(dt);buscar(inp.value)}else{clearTimeout(dt);dt=setTimeout(()=>{if(inp.value.length>=2)buscar(inp.value)},380)}});
document.getElementById("searchBtn").addEventListener("click",()=>buscar(inp.value));
const urlQ=new URLSearchParams(location.search).get("q");
if(urlQ){inp.value=urlQ;navInp.value=urlQ;document.getElementById("clearBtn").classList.add("visible");buscar(urlQ)}
</script>
<script src="js/sesion_widget.js"></script>
</body>
</html>