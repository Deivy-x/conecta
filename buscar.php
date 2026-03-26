<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();
$logueado = isset($_SESSION['usuario_id']);

// Si no está logueado, mostrar pantalla de acceso y salir
if (!$logueado):
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buscar — Quibdó Conecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="margin:0;font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg,#040810 0%,#0b1428 60%,#060f22 100%);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;text-align:center">
  <img src="Imagenes/quibdo_desco_new.png" alt="QuibdóConecta" style="height:52px;margin-bottom:36px;opacity:.92">
  <div style="background:#fff;border-radius:24px;max-width:440px;width:100%;padding:40px 36px;box-shadow:0 32px 80px rgba(0,0,0,.4)">
    <div style="font-size:54px;margin-bottom:16px">🔒</div>
    <h1 style="font-size:22px;font-weight:800;color:#0a0e1a;margin-bottom:10px;line-height:1.3">Inicia sesión para usar el buscador</h1>
    <p style="color:#6b7a99;font-size:14px;line-height:1.6;margin-bottom:28px">Encuentra candidatos, empresas, empleos y convocatorias de Quibdó y el Chocó. ¡Tu cuenta es gratis!</p>
    <a href="inicio_sesion.php?redirect=<?= urlencode('buscar.php') ?>" style="display:block;background:linear-gradient(135deg,#1648e8,#4f80ff);color:#fff;padding:15px 24px;border-radius:40px;font-weight:700;font-size:15px;text-decoration:none;box-shadow:0 4px 20px rgba(22,72,232,.4);margin-bottom:12px">🔑 Iniciar sesión</a>
    <a href="registro.php" style="display:block;background:#f2f5fb;color:#1648e8;padding:14px 24px;border-radius:40px;font-weight:700;font-size:15px;text-decoration:none;border:1.5px solid #dbe4ff;margin-bottom:20px">✨ Crear cuenta gratis</a>
    <a href="index.html" style="color:#aab4c8;font-size:13px;text-decoration:none">← Volver al inicio</a>
  </div>
</body>
</html>
<?php
exit;
endif;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#060b18">
  <title>Buscar — Quibdó Conecta</title>
  <link rel="icon" href="Imagenes/quibdo1-removebg-preview.png">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
    :root{
      --ink:#0a0e1a;--blue:#1648e8;--blue2:#4f80ff;
      --amber:#f59e0b;--violet:#7c3aed;--surface:#f2f5fb;
      --card:#ffffff;--border:#e1e6f0;--muted:#6b7a99;--radius:14px;
    }
    body{font-family:"Plus Jakarta Sans",sans-serif;background:var(--surface);color:var(--ink);min-height:100vh;overflow-x:hidden}

    /* ── NAVBAR ── */
    .navbar{
      position:fixed;top:0;left:0;width:100%;height:64px;
      display:flex;align-items:center;justify-content:space-between;
      padding:0 28px;
      background:rgba(255,255,255,.97);
      backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
      border-bottom:1px solid var(--border);
      z-index:1000;transition:box-shadow .3s;
      gap:12px;
    }
    .nav-logo{height:38px;width:auto;object-fit:contain;flex-shrink:0}
    .nav-search-wrap{
      position:relative;flex:1;max-width:340px;
    }
    .nav-search-wrap input{
      width:100%;background:var(--surface);
      border:1.5px solid var(--border);border-radius:40px;
      padding:8px 14px 8px 38px;font-size:14px;font-family:inherit;
      color:var(--ink);outline:none;transition:border-color .2s,box-shadow .2s;
    }
    .nav-search-wrap input:focus{border-color:var(--blue2);box-shadow:0 0 0 3px rgba(79,128,255,.12);background:#fff}
    .nav-search-wrap .ns-ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none}
    .nav-links{display:flex;align-items:center;gap:2px}
    .nl{
      display:flex;flex-direction:column;align-items:center;
      padding:6px 10px;border-radius:8px;
      color:var(--muted);text-decoration:none;
      font-size:10px;font-weight:700;gap:2px;
      transition:color .2s,background .2s;white-space:nowrap;
    }
    .nl .ico{font-size:16px}
    .nl:hover{color:var(--blue);background:rgba(22,72,232,.05)}
    .nl.active{color:var(--blue)}
    .nl.active::after{content:'';display:block;width:100%;height:2px;background:var(--blue);border-radius:2px;margin-top:2px}
    .nav-auth{display:flex;align-items:center;gap:8px;flex-shrink:0}
    .btn-in{color:var(--blue);border:1.5px solid var(--blue);padding:7px 15px;border-radius:30px;text-decoration:none;font-weight:700;font-size:12px;transition:all .2s;white-space:nowrap}
    .btn-in:hover{background:var(--blue);color:#fff}
    .btn-reg{background:var(--blue);color:#fff;padding:7px 16px;border-radius:25px;text-decoration:none;font-weight:700;font-size:12px;box-shadow:0 3px 10px rgba(22,72,232,.3);transition:all .2s;white-space:nowrap}
    .btn-reg:hover{background:#1038c0}
    .ham{display:none;flex-direction:column;gap:4px;cursor:pointer;background:none;border:none;padding:6px;border-radius:8px;transition:background .2s}
    .ham:hover{background:var(--surface)}
    .ham span{display:block;width:20px;height:2px;background:var(--ink);border-radius:3px;transition:all .3s}
    .ham.open span:nth-child(1){transform:translateY(6px) rotate(45deg)}
    .ham.open span:nth-child(2){opacity:0;transform:scaleX(0)}
    .ham.open span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}

    /* ── MOBILE MENU ── */
    .mob-menu{
      display:none;position:fixed;top:64px;left:0;width:100%;
      background:#fff;border-bottom:1px solid var(--border);
      box-shadow:0 16px 40px rgba(0,0,0,.12);
      flex-direction:column;z-index:998;
      max-height:calc(100vh - 64px);overflow-y:auto;
    }
    .mob-menu.open{display:flex}
    .mob-nav{padding:8px 12px}
    .mob-link{
      display:flex;align-items:center;gap:12px;
      padding:12px 14px;border-radius:10px;
      color:#334155;text-decoration:none;font-size:15px;font-weight:600;
      transition:background .15s;
    }
    .mob-link:hover,.mob-link.active{background:rgba(22,72,232,.06);color:var(--blue)}
    .mob-link .m-ico{font-size:18px;width:24px;text-align:center}
    .mob-divider{height:1px;background:var(--border);margin:4px 0}
    .mob-auth{padding:12px;display:flex;gap:8px}
    .mob-auth a{flex:1;text-align:center;padding:11px;border-radius:25px;font-weight:700;text-decoration:none;font-size:14px}
    .mob-in{border:2px solid var(--blue);color:var(--blue)}
    .mob-reg{background:var(--blue);color:#fff}

    /* ── HERO ── */
    .hero{
      padding-top:64px;
      background:linear-gradient(160deg,#040810 0%,#0b1428 45%,#060f22 100%);
      position:relative;overflow:hidden;
    }
    .hero-grid{
      position:absolute;inset:0;
      background-image:
        linear-gradient(rgba(79,128,255,.06) 1px,transparent 1px),
        linear-gradient(90deg,rgba(79,128,255,.06) 1px,transparent 1px);
      background-size:44px 44px;pointer-events:none;
    }
    .hero-glow1{position:absolute;width:500px;height:260px;border-radius:50%;background:radial-gradient(ellipse,rgba(22,72,232,.16) 0%,transparent 70%);top:-60px;left:50%;transform:translateX(-50%);pointer-events:none}
    .hero-glow2{position:absolute;width:300px;height:200px;border-radius:50%;background:radial-gradient(ellipse,rgba(124,58,237,.08) 0%,transparent 70%);top:40px;right:10%;pointer-events:none}
    .hero-inner{
      position:relative;z-index:2;
      max-width:700px;margin:0 auto;
      text-align:center;
      padding:56px 24px 0;
    }
    .hero-badge{
      display:inline-flex;align-items:center;gap:6px;
      background:rgba(79,128,255,.1);border:1px solid rgba(79,128,255,.28);
      color:#93c5fd;font-size:10px;font-weight:700;letter-spacing:.6px;
      text-transform:uppercase;padding:5px 13px;border-radius:30px;margin-bottom:18px;
    }
    .hero-inner h1{
      font-size:clamp(26px,5vw,52px);font-weight:800;
      color:#fff;line-height:1.12;margin-bottom:10px;
      letter-spacing:-0.5px;
    }
    .hero-inner h1 em{color:#60a5fa;font-style:normal}
    .hero-inner .hero-sub{color:rgba(255,255,255,.5);font-size:clamp(13px,2vw,15px);margin-bottom:36px;line-height:1.6}

    /* ── SEARCH BOX ── */
    .hero-search-wrap{
      position:relative;z-index:10;
      max-width:680px;margin:0 auto;
      padding-bottom:40px;
    }
    .hs-box{
      display:flex;align-items:center;
      background:#fff;border-radius:18px;
      box-shadow:0 24px 60px rgba(0,0,0,.35),0 0 0 1px rgba(255,255,255,.08);
      overflow:hidden;transition:box-shadow .3s;
    }
    .hs-box:focus-within{box-shadow:0 24px 60px rgba(0,0,0,.4),0 0 0 3px rgba(22,72,232,.22)}
    .hs-icon{padding:0 16px;font-size:17px;color:#94a3b8;flex-shrink:0;display:flex;align-items:center}
    #searchInput{
      flex:1;border:none;outline:none;
      font-size:clamp(14px,3vw,16px);font-family:inherit;
      color:var(--ink);padding:16px 4px;background:transparent;min-width:0;
    }
    #searchInput::placeholder{color:#aab4c8}
    #clearBtn{padding:0 10px;font-size:15px;color:#aab4c8;background:none;border:none;cursor:pointer;display:none;align-items:center;flex-shrink:0}
    #clearBtn.visible{display:flex}
    .hs-div{width:1px;height:28px;background:#e8eef7;flex-shrink:0}
    #searchBtn{
      display:flex;align-items:center;gap:6px;
      background:linear-gradient(135deg,var(--blue),var(--blue2));
      color:#fff;border:none;
      padding:clamp(10px,2vw,13px) clamp(16px,3vw,24px);
      font-size:clamp(12px,2vw,14px);font-weight:700;
      cursor:pointer;font-family:inherit;transition:opacity .2s,transform .1s;
      white-space:nowrap;margin:5px;border-radius:13px;flex-shrink:0;
    }
    #searchBtn:hover{opacity:.88}
    #searchBtn:active{transform:scale(.97)}

    /* ── STATS BAR ── */
    .stats-bar{
      background:#fff;border-bottom:1px solid var(--border);
      display:flex;justify-content:center;
      flex-wrap:wrap;gap:0;
    }
    .stat-it{
      text-align:center;padding:18px 32px;
      border-right:1px solid var(--border);
      cursor:default;transition:background .2s;
    }
    .stat-it:last-child{border-right:none}
    .stat-it:hover{background:rgba(22,72,232,.03)}
    .stat-n{font-size:20px;font-weight:800;color:var(--ink)}
    .stat-l{font-size:11px;color:var(--muted);font-weight:500;margin-top:1px}

    /* ── LAYOUT 3 COLS ── */
    .page-wrap{max-width:1240px;margin:0 auto;padding:24px 20px 80px;display:grid;grid-template-columns:256px 1fr 272px;gap:22px;align-items:start}

    /* ── SIDEBAR LEFT ── */
    .sl{position:sticky;top:80px}
    .s-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:14px}
    .s-head{padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;font-weight:700;color:var(--ink);display:flex;align-items:center;justify-content:space-between}
    .s-badge{background:var(--blue);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px}
    .fg{padding:10px 14px}
    .fl{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
    .fchips{display:flex;flex-wrap:wrap;gap:5px}
    .fc{
      background:var(--surface);border:1.5px solid var(--border);
      color:#475569;font-size:11px;font-weight:600;
      padding:4px 10px;border-radius:30px;cursor:pointer;
      transition:all .18s;user-select:none;-webkit-tap-highlight-color:transparent;
    }
    .fc:hover{border-color:var(--blue2);color:var(--blue);background:#eff4ff}
    .fc.on{background:var(--blue);border-color:var(--blue);color:#fff}
    .fdiv{height:1px;background:var(--border)}
    .ql{padding:5px 0}
    .qi{
      display:flex;align-items:center;gap:10px;
      padding:9px 12px;cursor:pointer;font-size:12px;
      color:#334155;font-weight:600;
      transition:background .15s;border-radius:8px;margin:0 4px;
      -webkit-tap-highlight-color:transparent;
    }
    .qi:hover{background:var(--surface);color:var(--blue)}
    .qi .q-ic{font-size:14px;width:18px;text-align:center}

    /* ── FEED ── */
    .feed{min-width:0}
    .tabs-row{
      display:flex;gap:4px;background:var(--card);
      border:1px solid var(--border);border-radius:var(--radius);
      padding:5px;margin-bottom:16px;overflow-x:auto;
      -webkit-overflow-scrolling:touch;scrollbar-width:none;
    }
    .tabs-row::-webkit-scrollbar{display:none}
    .tb{
      display:flex;align-items:center;gap:5px;
      padding:8px 13px;border:none;background:transparent;
      font-size:12px;font-weight:700;color:var(--muted);
      cursor:pointer;font-family:inherit;border-radius:10px;
      white-space:nowrap;transition:all .18s;flex-shrink:0;
      -webkit-tap-highlight-color:transparent;
    }
    .tb:hover{color:var(--blue);background:rgba(22,72,232,.05)}
    .tb.active{color:var(--blue);background:rgba(22,72,232,.08)}
    .tc{background:#eff4ff;color:var(--blue);font-size:10px;font-weight:700;padding:1px 6px;border-radius:20px;min-width:18px;text-align:center}
    .tb.active .tc{background:var(--blue);color:#fff}

    /* ── ESTADOS ── */
    .ei{text-align:center;padding:52px 20px;background:var(--card);border-radius:var(--radius);border:1px solid var(--border)}
    .ei .e-ico{font-size:48px;display:block;margin-bottom:14px}
    .ei h2{font-size:19px;font-weight:800;margin-bottom:8px}
    .ei p{color:var(--muted);font-size:13px;max-width:340px;margin:0 auto 18px}
    .qtags{display:flex;gap:6px;flex-wrap:wrap;justify-content:center}
    .qtag{
      background:var(--surface);border:1.5px solid var(--border);
      color:#475569;padding:6px 12px;border-radius:25px;
      font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;
      -webkit-tap-highlight-color:transparent;
    }
    .qtag:hover,.qtag:active{border-color:var(--blue);color:var(--blue);background:#eff4ff}
    .ls{display:none;text-align:center;padding:48px 20px}
    .ls.v{display:block}
    .spin{display:inline-block;width:40px;height:40px;border:3px solid var(--border);border-top-color:var(--blue);border-radius:50%;animation:spin .7s linear infinite;margin-bottom:12px}
    @keyframes spin{to{transform:rotate(360deg)}}
    .ls p{color:var(--muted);font-size:14px}
    .es{display:none;text-align:center;padding:48px 20px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius)}
    .es.v{display:block}
    .es .e-ico{font-size:44px;display:block;margin-bottom:12px}
    .es h3{font-size:17px;font-weight:800;margin-bottom:5px}
    .es p{color:var(--muted);font-size:13px}
    .ri{display:none;font-size:13px;color:var(--muted);margin-bottom:12px;padding:0 2px}
    .ri.v{display:block}
    .ri strong{color:var(--ink)}
    .panel{display:none}
    .panel.active{display:block}

    /* ── GRIDS ── */
    .gcands{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px}
    .gemps{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
    .glist{display:flex;flex-direction:column;gap:11px}

    /* ── CARD CANDIDATO ── */
    .cc{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:17px;transition:all .22s;text-decoration:none;color:inherit;display:block;position:relative}
    .cc:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,.1);border-color:#c7d7ff}
    .cc-av{width:50px;height:50px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:#fff;margin-bottom:11px;overflow:hidden;flex-shrink:0}
    .cc-av img{width:100%;height:100%;object-fit:cover}
    .bv{position:absolute;top:11px;right:11px;font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px}
    .bv-b{background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe}
    .bv-p{background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe}
    .cc-name{font-size:14px;font-weight:700;margin-bottom:2px}
    .cc-prof{color:var(--blue);font-size:11px;font-weight:600;margin-bottom:2px}
    .cc-loc{font-size:11px;color:var(--muted);margin-bottom:8px}
    .cc-sk{display:flex;flex-wrap:wrap;gap:4px}
    .sk{background:var(--surface);color:#475569;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:500}

    /* ── CARD EMPRESA ── */
    .ce{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:17px;transition:all .22s;text-decoration:none;color:inherit;display:block;position:relative}
    .ce:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(0,0,0,.1);border-color:#c7d7ff}
    .ce-av{width:50px;height:50px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:800;color:#fff;margin-bottom:11px;overflow:hidden}
    .ce-av img{width:100%;height:100%;object-fit:cover}
    .ce-name{font-size:14px;font-weight:700;margin-bottom:2px}
    .ce-sec{color:var(--blue);font-size:11px;font-weight:600;margin-bottom:2px}
    .ce-loc{font-size:11px;color:var(--muted);margin-bottom:8px}
    .ce-chip{background:var(--surface);color:#475569;font-size:10px;padding:2px 8px;border-radius:20px;font-weight:500;display:inline-block}

    /* ── CARD EMPLEO ── */
    .cemp{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:15px 17px;display:flex;align-items:center;gap:13px;transition:all .22s;text-decoration:none;color:inherit}
    .cemp:hover{box-shadow:0 6px 20px rgba(0,0,0,.08);border-color:#bfdbfe;transform:translateX(3px)}
    .cemp-ico{width:42px;height:42px;flex-shrink:0;background:linear-gradient(135deg,#eff4ff,#dbeafe);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px}
    .cemp-info{flex:1;min-width:0}
    .cemp-title{font-size:13px;font-weight:700;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .cemp-co{font-size:11px;color:var(--muted);margin-bottom:5px}
    .cemp-chips{display:flex;flex-wrap:wrap;gap:4px}
    .chip{font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px}
    .ch-b{background:#eff4ff;color:var(--blue)}
    .ch-g{background:#f0fdf4;color:#166534}
    .ch-gr{background:var(--surface);color:#475569}
    .ch-a{background:#fffbeb;color:#92400e}
    .ch-v{background:#f5f3ff;color:#6d28d9}
    .arr{color:#cbd5e1;font-size:16px;flex-shrink:0}

    /* ── CARD CONVOCATORIA ── */
    .cconv{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:15px 17px;display:flex;align-items:center;gap:13px;transition:all .22s;text-decoration:none;color:inherit}
    .cconv:hover{box-shadow:0 6px 20px rgba(0,0,0,.08);border-color:#bbf7d0;transform:translateX(3px)}
    .cconv-ico{width:42px;height:42px;flex-shrink:0;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:19px}
    .cconv-info{flex:1;min-width:0}
    .cconv-title{font-size:13px;font-weight:700;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .cconv-co{font-size:11px;color:var(--muted);margin-bottom:5px}
    .cconv-chips{display:flex;flex-wrap:wrap;gap:4px}

    /* ── SEC HEADER ── */
    .sh{display:flex;align-items:center;justify-content:space-between;margin:20px 0 12px;padding-bottom:9px;border-bottom:1px solid var(--border)}
    .sh h3{font-size:15px;font-weight:800}
    .sh a{font-size:12px;font-weight:700;color:var(--blue);text-decoration:none}
    .sh a:hover{text-decoration:underline}

    /* ── SIDEBAR RIGHT ── */
    .sr{position:sticky;top:80px}
    .sr-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:14px}
    .sr-card h4{font-size:13px;font-weight:800;margin-bottom:12px}
    .di{display:flex;align-items:center;gap:11px;padding:9px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit}
    .di:last-child{border-bottom:none}
    .di:hover .di-n{color:var(--blue)}
    .di-logo{width:36px;height:36px;border-radius:9px;overflow:hidden;background:linear-gradient(135deg,#1648e8,#4f80ff);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:12px;flex-shrink:0}
    .di-logo img{width:100%;height:100%;object-fit:cover}
    .di-n{font-size:12px;font-weight:700;transition:color .2s}
    .di-s{font-size:11px;color:var(--muted)}
    .trend{padding:8px 0;border-bottom:1px solid var(--border);cursor:pointer}
    .trend:last-child{border-bottom:none}
    .trend:hover .tr-t{color:var(--blue)}
    .tr-c{font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px}
    .tr-t{font-size:12px;font-weight:700;margin:2px 0;transition:color .2s}
    .tr-n{font-size:10px;color:var(--muted)}
    .cta-card{background:linear-gradient(135deg,#080d1a,#182038);border-radius:var(--radius);padding:18px;text-align:center}
    .cta-card h4{font-size:14px;font-weight:800;color:#fff;margin-bottom:6px}
    .cta-card p{font-size:11px;color:rgba(255,255,255,.5);margin-bottom:14px;line-height:1.6}
    .cta-card a{display:block;background:var(--blue);color:#fff;padding:9px;border-radius:25px;font-weight:700;font-size:12px;text-decoration:none;transition:opacity .2s;margin-bottom:7px}
    .cta-card a:hover{opacity:.88}
    .cta-card a.out{background:transparent;border:1.5px solid rgba(255,255,255,.2);color:rgba(255,255,255,.7);font-weight:600}
    .cta-card a.out:hover{border-color:#60a5fa;color:#60a5fa}

    /* ── MODAL LOGIN ── */
    .modal-overlay{
      display:none;position:fixed;inset:0;
      background:rgba(0,0,0,.55);backdrop-filter:blur(4px);
      z-index:2000;align-items:center;justify-content:center;padding:20px;
    }
    .modal-overlay.open{display:flex}
    .modal-box{
      background:#fff;border-radius:20px;
      padding:32px 28px;max-width:380px;width:100%;
      box-shadow:0 24px 60px rgba(0,0,0,.3);
      animation:slideUp .3s ease;
    }
    @keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    .modal-close{float:right;background:none;border:none;font-size:20px;cursor:pointer;color:var(--muted);line-height:1;margin-top:-4px}
    .modal-close:hover{color:var(--ink)}
    .modal-icon{font-size:40px;text-align:center;display:block;margin-bottom:12px}
    .modal-box h3{font-size:20px;font-weight:800;text-align:center;margin-bottom:6px}
    .modal-box p{font-size:13px;color:var(--muted);text-align:center;margin-bottom:24px;line-height:1.6}
    .modal-box p strong{color:var(--ink)}
    .modal-btns{display:flex;flex-direction:column;gap:10px}
    .modal-btns a{display:block;text-align:center;padding:13px;border-radius:25px;font-weight:700;font-size:14px;text-decoration:none;transition:all .2s}
    .m-primary{background:var(--blue);color:#fff;box-shadow:0 4px 14px rgba(22,72,232,.3)}
    .m-primary:hover{background:#1038c0}
    .m-secondary{border:1.5px solid var(--border);color:#475569}
    .m-secondary:hover{border-color:var(--blue);color:var(--blue)}
    .modal-sep{display:flex;align-items:center;gap:8px;margin:4px 0}
    .modal-sep span{font-size:11px;color:var(--muted);white-space:nowrap}
    .modal-sep::before,.modal-sep::after{content:'';flex:1;height:1px;background:var(--border)}

    footer{background:#0a0e1a;border-top:1px solid rgba(255,255,255,.06);color:rgba(255,255,255,.4);text-align:center;padding:20px;font-size:12px}
    footer span{color:#60a5fa}

    /* ── RESPONSIVE TABLET ── */
    @media(max-width:1100px){
      .page-wrap{grid-template-columns:240px 1fr}
      .sr{display:none}
    }
    /* ── RESPONSIVE MOBILE 2026 ── */
    @media(max-width:768px){
      .navbar{padding:0 14px;height:60px;gap:8px}
      .nav-links,.nav-auth,.nav-search-wrap{display:none}
      .ham{display:flex}
      .mob-menu{top:60px;max-height:calc(100vh - 60px)}
      .hero{padding-top:60px}
      .hero-inner{padding:40px 16px 0}
      .hero-inner h1{font-size:clamp(24px,7vw,36px)}
      .hero-search-wrap{padding:0 12px 32px}
      .hs-icon{padding:0 12px;font-size:15px}
      #searchInput{padding:14px 4px;font-size:15px}
      #searchBtn{padding:10px 16px;font-size:13px}
      .stats-bar{gap:0}
      .stat-it{padding:14px 16px;flex:1;min-width:0}
      .stat-n{font-size:17px}
      .stat-l{font-size:10px}
      .page-wrap{grid-template-columns:1fr;padding:16px 12px 60px;gap:14px}
      .sl{display:none}
      /* Mobile tabs más grandes y scrollables */
      .tabs-row{padding:4px}
      .tb{padding:9px 14px;font-size:13px}
      /* Cards full width en móvil */
      .gcands,.gemps{grid-template-columns:1fr 1fr;gap:10px}
      .cc,.ce{padding:14px}
      .cemp,.cconv{padding:13px 14px}
      .modal-box{padding:24px 20px}
    }
    @media(max-width:480px){
      .gcands,.gemps{grid-template-columns:1fr}
      .stats-bar{display:grid;grid-template-columns:1fr 1fr}
      .stat-it{border-right:1px solid var(--border) !important;border-bottom:1px solid var(--border)}
      .stat-it:nth-child(2n){border-right:none !important}
      .stat-it:nth-child(3),.stat-it:nth-child(4){border-bottom:none}
    }
    @media(max-width:360px){
      .tb{padding:8px 10px;font-size:11px}
      .hero-inner h1{font-size:22px}
    }
  </style>
</head>
<body>

<!-- ── MODAL LOGIN REQUERIDO ── -->
<div class="modal-overlay" id="loginModal">
  <div class="modal-box">
    <button class="modal-close" onclick="cerrarModal()">✕</button>
    <span class="modal-icon">🔐</span>
    <h3>Inicia sesión para buscar</h3>
    <p>Para ver resultados completos y conectar con <strong>talento y empresas del Chocó</strong>, necesitas una cuenta.</p>
    <div class="modal-btns">
      <a href="inicio_sesion.php" class="m-primary">🚀 Iniciar sesión</a>
      <div class="modal-sep"><span>¿No tienes cuenta?</span></div>
      <a href="registro.php" class="m-secondary">✨ Crear cuenta gratis</a>
    </div>
  </div>
</div>

<!-- ── NAVBAR ── -->
<header class="navbar" id="navbar">
  <img src="Imagenes/quibdo_desco_new.png" alt="Quibdó Conecta" class="nav-logo">
  <div class="nav-search-wrap">
    <span class="ns-ico">🔍</span>
    <input type="text" placeholder="Buscar en QuibdóConecta…" id="navSearchInput" autocomplete="off">
  </div>
  <nav class="nav-links">
    <a href="index.html" class="nl"><span class="ico">🏠</span>Inicio</a>
    <a href="Empleo.php" class="nl"><span class="ico">💼</span>Empleos</a>
    <a href="talentos.php" class="nl"><span class="ico">🌟</span>Talento</a>
    <a href="empresas.php" class="nl"><span class="ico">🏢</span>Empresas</a>
    <a href="buscar.php" class="nl active"><span class="ico">🔍</span>Buscar</a>
    <a href="convocatorias.php" class="nl"><span class="ico">📋</span>Convocatorias</a>
  </nav>
  <div class="nav-auth">
    <a href="inicio_sesion.php" class="btn-in">Iniciar sesión</a>
    <a href="registro.php" class="btn-reg">Registrarse</a>
  </div>
  <button class="ham" id="ham" aria-label="Menú"><span></span><span></span><span></span></button>
</header>

<!-- ── MOBILE MENU ── -->
<div class="mob-menu" id="mobMenu">
  <nav class="mob-nav">
    <a href="index.html" class="mob-link"><span class="m-ico">🏠</span>Inicio</a>
    <a href="Empleo.php" class="mob-link"><span class="m-ico">💼</span>Empleos</a>
    <a href="talentos.php" class="mob-link"><span class="m-ico">🌟</span>Talento</a>
    <a href="empresas.php" class="mob-link"><span class="m-ico">🏢</span>Empresas</a>
    <a href="buscar.php" class="mob-link active"><span class="m-ico">🔍</span>Buscar</a>
    <a href="convocatorias.php" class="mob-link"><span class="m-ico">📋</span>Convocatorias</a>
    <a href="Ayuda.html" class="mob-link"><span class="m-ico">❓</span>Ayuda</a>
  </nav>
  <div class="mob-divider"></div>
  <div class="mob-auth">
    <a href="inicio_sesion.php" class="mob-in">Iniciar sesión</a>
    <a href="registro.php" class="mob-reg">Registrarse</a>
  </div>
</div>

<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-grid"></div>
  <div class="hero-glow1"></div>
  <div class="hero-glow2"></div>
  <div class="hero-inner">
    <span class="hero-badge">🌐 Búsqueda Global · QuibdóConecta</span>
    <h1>Encuentra <em>talento, empleos</em><br>y oportunidades en el Chocó</h1>
    <p class="hero-sub">Candidatos, empresas, convocatorias y más — todo en un solo lugar</p>
    <div class="hero-search-wrap">
      <div class="hs-box">
        <div class="hs-icon">🔍</div>
        <input type="text" id="searchInput" placeholder="Nombre, profesión, empresa, cargo, ciudad…" autocomplete="off">
        <button id="clearBtn" aria-label="Limpiar">✕</button>
        <div class="hs-div"></div>
        <button id="searchBtn">Buscar →</button>
      </div>
    </div>
  </div>
</section>

<!-- ── STATS BAR ── -->
<div class="stats-bar">
  <div class="stat-it"><div class="stat-n" id="sn1">—</div><div class="stat-l">Candidatos</div></div>
  <div class="stat-it"><div class="stat-n" id="sn2">—</div><div class="stat-l">Empresas</div></div>
  <div class="stat-it"><div class="stat-n" id="sn3">—</div><div class="stat-l">Empleos</div></div>
  <div class="stat-it"><div class="stat-n" id="sn4">—</div><div class="stat-l">Convocatorias</div></div>
</div>

<!-- ── PAGE LAYOUT ── -->
<div class="page-wrap">

  <!-- SIDEBAR LEFT -->
  <aside class="sl">
    <div class="s-card">
      <div class="s-head">Filtros <span class="s-badge">0</span></div>
      <div class="fg">
        <div class="fl">Tipo de resultado</div>
        <div class="fchips">
          <span class="fc on" data-v="todos" onclick="filtrar(this)">Todos</span>
          <span class="fc" data-v="candidatos" onclick="filtrar(this)">👤 Candidatos</span>
          <span class="fc" data-v="empresas" onclick="filtrar(this)">🏢 Empresas</span>
          <span class="fc" data-v="empleos" onclick="filtrar(this)">💼 Empleos</span>
          <span class="fc" data-v="convocatorias" onclick="filtrar(this)">📋 Convocatorias</span>
        </div>
      </div>
      <div class="fdiv"></div>
      <div class="fg">
        <div class="fl">Ciudad</div>
        <div class="fchips">
          <span class="fc" onclick="qbuscar('Quibdó')">📍 Quibdó</span>
          <span class="fc" onclick="qbuscar('Istmina')">📍 Istmina</span>
          <span class="fc" onclick="qbuscar('Condoto')">📍 Condoto</span>
          <span class="fc" onclick="qbuscar('Tadó')">📍 Tadó</span>
        </div>
      </div>
      <div class="fdiv"></div>
      <div class="fg">
        <div class="fl">Sector</div>
        <div class="fchips">
          <span class="fc" onclick="qbuscar('tecnología')">💻 Tech</span>
          <span class="fc" onclick="qbuscar('salud')">🏥 Salud</span>
          <span class="fc" onclick="qbuscar('educación')">📚 Educación</span>
          <span class="fc" onclick="qbuscar('comercio')">🛒 Comercio</span>
          <span class="fc" onclick="qbuscar('ingeniería')">⚙️ Ingeniería</span>
          <span class="fc" onclick="qbuscar('gobierno')">🏛️ Gobierno</span>
        </div>
      </div>
    </div>
    <div class="s-card">
      <div class="s-head">Búsquedas populares</div>
      <div class="ql">
        <div class="qi" onclick="qbuscar('desarrollador')"><span class="q-ic">💻</span>Desarrollador</div>
        <div class="qi" onclick="qbuscar('diseño gráfico')"><span class="q-ic">🎨</span>Diseño gráfico</div>
        <div class="qi" onclick="qbuscar('administración')"><span class="q-ic">📊</span>Administración</div>
        <div class="qi" onclick="qbuscar('contabilidad')"><span class="q-ic">🧮</span>Contabilidad</div>
        <div class="qi" onclick="qbuscar('marketing')"><span class="q-ic">📣</span>Marketing</div>
        <div class="qi" onclick="qbuscar('remoto')"><span class="q-ic">🌐</span>Trabajo remoto</div>
      </div>
    </div>
  </aside>

  <!-- FEED -->
  <main class="feed">
    <div class="tabs-row">
      <button class="tb active" data-tab="todos">🌐 Todos <span class="tc" id="ct-todos">—</span></button>
      <button class="tb" data-tab="candidatos">👤 Candidatos <span class="tc" id="ct-cands">—</span></button>
      <button class="tb" data-tab="empresas">🏢 Empresas <span class="tc" id="ct-emps">—</span></button>
      <button class="tb" data-tab="empleos">💼 Empleos <span class="tc" id="ct-empleos">—</span></button>
      <button class="tb" data-tab="convocatorias">📋 Convocatorias <span class="tc" id="ct-convs">—</span></button>
    </div>
    <div class="ri" id="ri"></div>
    <div class="ls" id="ls"><div class="spin"></div><p>Buscando en toda la plataforma…</p></div>
    <div class="es" id="es"><span class="e-ico">🔍</span><h3>Sin resultados</h3><p>No encontramos nada para "<span id="eq"></span>".<br>Intenta con otro término.</p></div>
    <!-- ESTADO INICIAL -->
    <div class="ei" id="ei">
      <span class="e-ico">✨</span>
      <h2>¿Qué buscas hoy?</h2>
      <p>Candidatos, empresas, empleos y convocatorias del Chocó te esperan.</p>
      <div class="qtags">
        <span class="qtag" onclick="qbuscar('desarrollador')">💻 Dev</span>
        <span class="qtag" onclick="qbuscar('diseño')">🎨 Diseño</span>
        <span class="qtag" onclick="qbuscar('tecnología')">🚀 Tech</span>
        <span class="qtag" onclick="qbuscar('salud')">🏥 Salud</span>
        <span class="qtag" onclick="qbuscar('educación')">📚 Edu</span>
        <span class="qtag" onclick="qbuscar('Quibdó')">📍 Quibdó</span>
        <span class="qtag" onclick="qbuscar('administración')">📊 Admin</span>
        <span class="qtag" onclick="qbuscar('convocatoria')">📋 Convocatorias</span>
      </div>
    </div>
    <!-- PANELS -->
    <div class="panel active" id="p-todos"><div id="w-cands"></div><div id="w-emps"></div><div id="w-empleos"></div><div id="w-convs"></div></div>
    <div class="panel" id="p-candidatos"><div class="gcands" id="g-cands"></div></div>
    <div class="panel" id="p-empresas"><div class="gemps" id="g-emps"></div></div>
    <div class="panel" id="p-empleos"><div class="glist" id="g-empleos"></div></div>
    <div class="panel" id="p-convocatorias"><div class="glist" id="g-convs"></div></div>
  </main>

  <!-- SIDEBAR RIGHT -->
  <aside class="sr">
    <div class="sr-card">
      <h4>🏢 Empresas encontradas</h4>
      <div id="sr-emps">
        <div class="di" style="opacity:.35">
          <div class="di-logo">QC</div>
          <div><div class="di-n">Busca algo</div><div class="di-s">para ver empresas aquí</div></div>
        </div>
      </div>
    </div>
    <div class="sr-card">
      <h4>📈 Tendencias en el Chocó</h4>
      <div class="trend" onclick="qbuscar('tecnología')"><div class="tr-c">Sector</div><div class="tr-t">Empleos en Tech 💻</div><div class="tr-n">Creciente demanda</div></div>
      <div class="trend" onclick="qbuscar('convocatoria')"><div class="tr-c">Oportunidades</div><div class="tr-t">Convocatorias abiertas 📋</div><div class="tr-n">Actualizadas esta semana</div></div>
      <div class="trend" onclick="qbuscar('salud')"><div class="tr-c">Sector</div><div class="tr-t">Profesionales de salud 🏥</div><div class="tr-n">Alta búsqueda</div></div>
      <div class="trend" onclick="qbuscar('remoto')"><div class="tr-c">Modalidad</div><div class="tr-t">Trabajo remoto 🌐</div><div class="tr-n">Tendencia global</div></div>
    </div>
    <div class="cta-card">
      <h4>¿Eres talento del Chocó?</h4>
      <p>Regístrate gratis y conecta con empresas que buscan tu perfil.</p>
      <a href="registro.php">✨ Crear perfil gratis</a>
      <a href="Empleo.php" class="out">Ver empleos →</a>
    </div>
  </aside>
</div>

<footer><p>© 2026 <span>QuibdóConecta</span> — Conectando el talento del Chocó con el mundo.</p></footer>

<script>
// ── VARS GLOBALES ──
const LOGUEADO = <?php echo $logueado ? 'true' : 'false'; ?>;
let tabActual = 'todos', ultimaQ = '', datos = {candidatos:[],empresas:[],empleos:[],convocatorias:[]};
let pendingQ = '';

// ── NAVBAR SCROLL ──
window.addEventListener('scroll', () => {
  document.getElementById('navbar').style.boxShadow = window.scrollY > 20 ? '0 4px 20px rgba(0,0,0,.1)' : 'none';
}, {passive:true});

// ── HAMBURGER ──
const ham = document.getElementById('ham'), mob = document.getElementById('mobMenu');
ham.addEventListener('click', () => { ham.classList.toggle('open'); mob.classList.toggle('open'); });
document.addEventListener('click', e => {
  if (!ham.contains(e.target) && !mob.contains(e.target)) { ham.classList.remove('open'); mob.classList.remove('open'); }
});

// ── SYNC BUSCADORES ──
const navInp = document.getElementById('navSearchInput');
const inp = document.getElementById('searchInput');
navInp.addEventListener('input', () => { inp.value = navInp.value; });
navInp.addEventListener('keydown', e => {
  if (e.key === 'Enter' && navInp.value.trim()) iniciarBusqueda(navInp.value);
});
inp.addEventListener('input', () => {
  navInp.value = inp.value;
  document.getElementById('clearBtn').classList.toggle('visible', inp.value.length > 0);
});

// ── MODAL LOGIN ──
function mostrarModal(q) {
  pendingQ = q || '';
  document.getElementById('loginModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function cerrarModal() {
  document.getElementById('loginModal').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('loginModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) cerrarModal();
});
// Redirigir a inicio_sesion con redirect de vuelta
document.querySelectorAll('#loginModal .m-primary').forEach(a => {
  a.addEventListener('click', e => {
    if (pendingQ) {
      e.preventDefault();
      window.location.href = 'inicio_sesion.php?redirect=' + encodeURIComponent('buscar.php?q=' + encodeURIComponent(pendingQ));
    }
  });
});

// ── INICIAR BÚSQUEDA (verifica login) ──
function iniciarBusqueda(q) {
  q = (q || '').trim();
  if (!q) return;
  if (!LOGUEADO) {
    mostrarModal(q);
    return;
  }
  buscar(q);
}

// ── TABS ──
document.querySelectorAll('.tb').forEach(btn => {
  btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});
function switchTab(tab) {
  tabActual = tab;
  document.querySelectorAll('.tb').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.getElementById('p-' + tab).classList.add('active');
  updInfo();
}

// ── FILTER CHIPS ──
function filtrar(el) {
  document.querySelectorAll('.fc[data-v]').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  switchTab(el.dataset.v === 'todos' ? 'todos' : el.dataset.v);
}

// ── HELPERS ──
function v(id) { document.getElementById(id)?.classList.add('v'); }
function hv(id) { document.getElementById(id)?.classList.remove('v'); }
function se(id) { const el=document.getElementById(id); if(el) el.style.display=''; }
function he(id) { const el=document.getElementById(id); if(el) el.style.display='none'; }
function total() { return datos.candidatos.length+datos.empresas.length+datos.empleos.length+datos.convocatorias.length; }
function x(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function ago(d) {
  if(!d) return '';
  const diff = Math.floor((Date.now()-new Date(d))/1000);
  if(diff<60) return 'hace un momento';
  if(diff<3600) return 'hace '+Math.floor(diff/60)+'min';
  if(diff<86400) return 'hace '+Math.floor(diff/3600)+'h';
  if(diff<2592000) return 'hace '+Math.floor(diff/86400)+' días';
  return 'hace '+Math.floor(diff/2592000)+' meses';
}
function setLoad(on) {
  if(on){ v('ls');hv('es');hv('ri');he('ei'); } else hv('ls');
}
function updCounts() {
  const t=total();
  document.getElementById('ct-todos').textContent=t;
  document.getElementById('ct-cands').textContent=datos.candidatos.length;
  document.getElementById('ct-emps').textContent=datos.empresas.length;
  document.getElementById('ct-empleos').textContent=datos.empleos.length;
  document.getElementById('ct-convs').textContent=datos.convocatorias.length;
  document.getElementById('sn1').textContent=datos.candidatos.length;
  document.getElementById('sn2').textContent=datos.empresas.length;
  document.getElementById('sn3').textContent=datos.empleos.length;
  document.getElementById('sn4').textContent=datos.convocatorias.length;
}
function updInfo() {
  const n = tabActual==='todos' ? total() : (datos[tabActual]?.length ?? 0);
  document.getElementById('ri').innerHTML = '<strong>'+n+'</strong> resultado'+(n!==1?'s':'')+' para "<strong>'+x(ultimaQ)+'</strong>"';
  if(n>0) v('ri');
}

// ── CARDS ──
function cCand(c) {
  const ini=(c.nombre||'').substring(0,2).toUpperCase();
  const g=c.avatar_color||'linear-gradient(135deg,#1f9d55,#2ecc71)';
  const sk=(c.skills||'').split(',').filter(Boolean).slice(0,3);
  const bv=parseInt(c.verificado)?'<span class="bv bv-b">✓ Verificado</span>':'';
  const bd=parseInt(c.destacado)?'<span class="bv bv-p">🏅 Dest.</span>':'';
  const foto=c.foto?`<img src="uploads/${x(c.foto)}" alt="${ini}" loading="lazy" onerror="this.style.display='none';this.parentNode.textContent='${ini}'">`:(ini);
  return `<a href="perfil.php?id=${c.id}&tipo=candidato" class="cc">${bv||bd}<div class="cc-av" style="background:${g}">${foto}</div><div class="cc-name">${x(c.nombre)} ${x(c.apellido||'')}</div><div class="cc-prof">🏷️ ${x(c.profesion||'Profesional')}</div><div class="cc-loc">📍 ${x(c.ciudad||'Chocó')}</div><div class="cc-sk">${sk.map(s=>`<span class="sk">${x(s.trim())}</span>`).join('')}</div></a>`;
}
function cEmp(e) {
  const n=e.nombre_empresa||e.nombre||'';
  const ini=n.substring(0,2).toUpperCase();
  const g=e.avatar_color||'linear-gradient(135deg,#1648e8,#4f80ff)';
  const bv=parseInt(e.verificado)?'<span class="bv bv-b">✓ Verificada</span>':'';
  const logo=e.logo?`<img src="uploads/logos/${x(e.logo)}" alt="${ini}" loading="lazy" onerror="this.style.display='none';this.parentNode.textContent='${ini}'">`:(ini);
  return `<a href="perfil.php?id=${e.id}&tipo=empresa" class="ce">${bv}<div class="ce-av" style="background:${g}">${logo}</div><div class="ce-name">${x(n)}</div><div class="ce-sec">🏷️ ${x(e.sector||'Empresa')}</div><div class="ce-loc">📍 ${x(e.ciudad||'Chocó')}</div><span class="ce-chip">${x(e.sector||'Local')}</span></a>`;
}
function cEmpleo(e) {
  const m=e.modalidad||'', s=e.salario_texto||'', ci=e.ciudad||'Quibdó', t=ago(e.creado_en);
  return `<a href="Empleo.php" class="cemp"><div class="cemp-ico">💼</div><div class="cemp-info"><div class="cemp-title">${x(e.titulo||'')}</div><div class="cemp-co">🏢 ${x(e.empresa_nombre||'Empresa')}${t?' · '+t:''}</div><div class="cemp-chips">${m?`<span class="chip ch-b">${x(m)}</span>`:''}${ci?`<span class="chip ch-gr">📍 ${x(ci)}</span>`:''}${s?`<span class="chip ch-g">💰 ${x(s)}</span>`:''}</div></div><span class="arr">›</span></a>`;
}
function cConv(c) {
  const icon=c.icono||'📋';
  const chipE=c.estado==='abierta'?'<span class="chip ch-g">🟢 Abierta</span>':'<span class="chip ch-gr">⏸ Cerrada</span>';
  const href=c.url_externa?(c.url_externa.startsWith('http')?c.url_externa:'https://'+c.url_externa):'#';
  return `<a href="${x(href)}" class="cconv" target="${c.url_externa?'_blank':'_self'}"><div class="cconv-ico" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7)">${icon}</div><div class="cconv-info"><div class="cconv-title">${x(c.titulo||'')}</div><div class="cconv-co">🏛️ ${x(c.entidad||'')}</div><div class="cconv-chips">${chipE}${c.modalidad?`<span class="chip ch-v">${x(c.modalidad)}</span>`:''}${c.lugar?`<span class="chip ch-gr">📍 ${x(c.lugar)}</span>`:''}${c.salario?`<span class="chip ch-a">💰 ${x(c.salario)}</span>`:''}</div></div><span class="arr">›</span></a>`;
}
function seccion(titulo, items, renderFn, gridCls, wrapId, tabId) {
  const w=document.getElementById(wrapId);
  if(!w||!items?.length){if(w)w.innerHTML='';return;}
  w.innerHTML=`<div class="sh"><h3>${titulo}</h3><a href="#" onclick="switchTab('${tabId}');return false">Ver todos →</a></div><div class="${gridCls}">${items.slice(0,4).map(renderFn).join('')}</div>`;
}
function renderAll() {
  seccion('👤 Candidatos',datos.candidatos,cCand,'gcands','w-cands','candidatos');
  seccion('🏢 Empresas',datos.empresas,cEmp,'gemps','w-emps','empresas');
  seccion('💼 Empleos',datos.empleos,cEmpleo,'glist','w-empleos','empleos');
  seccion('📋 Convocatorias',datos.convocatorias,cConv,'glist','w-convs','convocatorias');
  const empty = '<p style="color:#94a3b8;padding:12px 0;font-size:13px">Sin resultados.</p>';
  document.getElementById('g-cands').innerHTML=datos.candidatos.map(cCand).join('')||empty;
  document.getElementById('g-emps').innerHTML=datos.empresas.map(cEmp).join('')||empty;
  document.getElementById('g-empleos').innerHTML=datos.empleos.map(cEmpleo).join('')||empty;
  document.getElementById('g-convs').innerHTML=datos.convocatorias.map(cConv).join('')||empty;
  updSidebarEmps();
}
function updSidebarEmps() {
  const w=document.getElementById('sr-emps');
  if(!datos.empresas.length){w.innerHTML='<p style="color:#94a3b8;font-size:12px;padding:4px 0">No se encontraron empresas.</p>';return;}
  w.innerHTML=datos.empresas.slice(0,4).map(e=>{
    const n=e.nombre_empresa||e.nombre||'?', ini=n.substring(0,2).toUpperCase();
    const g=e.avatar_color||'linear-gradient(135deg,#1648e8,#4f80ff)';
    const logo=e.logo?`<img src="uploads/logos/${x(e.logo)}" alt="${ini}" loading="lazy" onerror="this.style.display='none';this.parentNode.textContent='${ini}'">`:(ini);
    return `<a href="perfil.php?id=${e.id}&tipo=empresa" class="di"><div class="di-logo" style="background:${g}">${logo}</div><div><div class="di-n">${x(n)}</div><div class="di-s">${x(e.sector||'Empresa')} · ${x(e.ciudad||'Chocó')}</div></div></a>`;
  }).join('');
}

// ── BÚSQUEDA AJAX ──
let abortCtrl = null;
async function buscar(query) {
  query = (query||'').trim();
  if(!query || query===ultimaQ) return;
  ultimaQ = query;
  navInp.value = query;
  document.getElementById('clearBtn').classList.add('visible');
  if(abortCtrl) abortCtrl.abort();
  abortCtrl = new AbortController();
  setLoad(true); hv('es'); hv('ri'); he('ei');
  try {
    const r = await fetch('Php/search.php?q='+encodeURIComponent(query), {signal:abortCtrl.signal});
    const json = await r.json();
    datos = {
      candidatos: json.candidatos||[], empresas: json.empresas||[],
      empleos: json.empleos||[], convocatorias: json.convocatorias||[]
    };
    setLoad(false); updCounts();
    if(total()===0) {
      v('es'); document.getElementById('eq').textContent=query;
      ['w-cands','w-emps','w-empleos','w-convs','g-cands','g-emps','g-empleos','g-convs'].forEach(id=>{
        const el=document.getElementById(id); if(el) el.innerHTML='';
      });
    } else { renderAll(); updInfo(); }
  } catch(err) {
    if(err.name==='AbortError') return;
    setLoad(false); v('es'); document.getElementById('eq').textContent=query;
  }
}
function qbuscar(q) { inp.value=q; navInp.value=q; ultimaQ=''; iniciarBusqueda(q); }

// ── EVENTOS BUSCADOR ──
const clearBtn = document.getElementById('clearBtn');
clearBtn.addEventListener('click', () => {
  inp.value=''; navInp.value='';
  clearBtn.classList.remove('visible');
  ultimaQ='';
  datos={candidatos:[],empresas:[],empleos:[],convocatorias:[]};
  hv('ri'); hv('es'); hv('ls'); se('ei');
  ['ct-todos','ct-cands','ct-emps','ct-empleos','ct-convs'].forEach(id=>document.getElementById(id).textContent='—');
  ['sn1','sn2','sn3','sn4'].forEach(id=>document.getElementById(id).textContent='—');
  ['w-cands','w-emps','w-empleos','w-convs','g-cands','g-emps','g-empleos','g-convs'].forEach(id=>{
    const el=document.getElementById(id); if(el) el.innerHTML='';
  });
  inp.focus();
});
let debTimer;
inp.addEventListener('keydown', e => {
  if(e.key==='Enter') { clearTimeout(debTimer); iniciarBusqueda(inp.value); }
  else { clearTimeout(debTimer); debTimer=setTimeout(()=>{ if(inp.value.length>=2) iniciarBusqueda(inp.value); }, 420); }
});
document.getElementById('searchBtn').addEventListener('click', () => iniciarBusqueda(inp.value));

// ── URL PARAMS ──
const urlQ = new URLSearchParams(location.search).get('q');
if(urlQ) { inp.value=urlQ; navInp.value=urlQ; clearBtn.classList.add('visible'); iniciarBusqueda(urlQ); }
</script>

<script src="js/sesion_widget.js"></script>
</body>
</html>