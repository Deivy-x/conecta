(function () {
  'use strict';
  if (document.querySelector('.sidebar')) return;

  const WIDGET_CSS = `
    .qc-user-widget {
      display: flex;
      align-items: center;
      gap: 10px;
      position: relative;
    }
    .qc-notif-bell {
      position: relative;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: rgba(31,157,85,.1);
      border: 1.5px solid rgba(31,157,85,.25);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      text-decoration: none;
      font-size: 17px;
      transition: all .25s;
      color: #1f9d55;
    }
    .qc-notif-bell:hover {
      background: rgba(31,157,85,.2);
      border-color: #1f9d55;
      transform: scale(1.08);
    }
    .qc-notif-badge {
      position: absolute;
      top: -3px;
      right: -3px;
      min-width: 18px;
      height: 18px;
      padding: 0 4px;
      border-radius: 20px;
      background: #e74c3c;
      color: white;
      font-size: 10px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 2px solid white;
      animation: qc-pulse 2s infinite;
    }
    @keyframes qc-pulse {
      0%,100% { box-shadow: 0 0 0 0 rgba(231,76,60,.4); }
      50% { box-shadow: 0 0 0 5px rgba(231,76,60,0); }
    }
    .qc-avatar-btn {
      display: flex;
      align-items: center;
      gap: 9px;
      background: white;
      border: 1.5px solid rgba(31,157,85,.3);
      border-radius: 40px;
      padding: 5px 14px 5px 5px;
      cursor: pointer;
      transition: all .3s cubic-bezier(.34,1.56,.64,1);
      position: relative;
      box-shadow: 0 2px 12px rgba(31,157,85,.12);
    }
    .qc-avatar-btn:hover {
      border-color: #1f9d55;
      box-shadow: 0 4px 20px rgba(31,157,85,.22);
      transform: translateY(-1px);
    }
    .qc-avatar-btn.open {
      border-color: #1f9d55;
      box-shadow: 0 4px 20px rgba(31,157,85,.22);
    }
    .qc-avatar-img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid rgba(31,157,85,.3);
      flex-shrink: 0;
      background: linear-gradient(135deg, #1f9d55, #2ecc71);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 800;
      color: white;
      overflow: hidden;
    }
    .qc-avatar-img img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      display: block;
    }
    .qc-avatar-info {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
    }
    .qc-avatar-nombre {
      font-size: 13px;
      font-weight: 700;
      color: #111;
      max-width: 100px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .qc-avatar-sub {
      font-size: 10px;
      color: #888;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .qc-avatar-arrow {
      font-size: 9px;
      color: #1f9d55;
      transition: transform .3s;
    }
    .qc-avatar-btn.open .qc-avatar-arrow {
      transform: rotate(180deg);
    }
    .qc-online-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #2ecc71;
      border: 1.5px solid white;
      box-shadow: 0 0 0 0 rgba(46,204,113,.4);
      animation: qc-online 2.5s infinite;
    }
    @keyframes qc-online {
      0%,100% { box-shadow: 0 0 0 0 rgba(46,204,113,.4); }
      60% { box-shadow: 0 0 0 6px rgba(46,204,113,0); }
    }
    .qc-dropdown {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      width: 260px;
      background: white;
      border-radius: 18px;
      box-shadow: 0 20px 60px rgba(0,0,0,.15), 0 4px 16px rgba(0,0,0,.08);
      border: 1px solid rgba(0,0,0,.07);
      overflow: hidden;
      z-index: 9999;
      opacity: 0;
      transform: translateY(-10px) scale(.97);
      pointer-events: none;
      transition: all .25s cubic-bezier(.34,1.56,.64,1);
    }
    .qc-dropdown.visible {
      opacity: 1;
      transform: translateY(0) scale(1);
      pointer-events: all;
    }
    .qc-drop-header {
      padding: 18px 20px 14px;
      background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
      border-bottom: 1px solid rgba(31,157,85,.1);
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .qc-drop-avatar-big {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      background: linear-gradient(135deg, #1f9d55, #2ecc71);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      font-weight: 800;
      color: white;
      flex-shrink: 0;
      border: 3px solid white;
      box-shadow: 0 4px 12px rgba(31,157,85,.3);
      overflow: hidden;
    }
    .qc-drop-avatar-big img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
    }
    .qc-drop-nombre {
      font-size: 15px;
      font-weight: 700;
      color: #111;
    }
    .qc-drop-tipo {
      font-size: 11px;
      color: #1f9d55;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin-top: 1px;
    }
    .qc-drop-correo {
      font-size: 11px;
      color: #999;
      margin-top: 2px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 145px;
    }
    .qc-drop-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      padding: 10px 20px;
      border-bottom: 1px solid rgba(0,0,0,.05);
    }
    .qc-dbadge {
      font-size: 10px;
      font-weight: 700;
      padding: 3px 8px;
      border-radius: 20px;
      line-height: 1;
    }
    .qc-drop-menu {
      padding: 8px 0;
    }
    .qc-drop-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 11px 20px;
      color: #333;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: all .2s;
      cursor: pointer;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
      font-family: inherit;
    }
    .qc-drop-link:hover {
      background: #f8fffe;
      color: #1f9d55;
      padding-left: 24px;
    }
    .qc-dl-icon {
      width: 28px;
      height: 28px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      flex-shrink: 0;
      background: #f4f6f8;
      transition: all .2s;
    }
    .qc-drop-link:hover .qc-dl-icon {
      background: rgba(31,157,85,.1);
      transform: scale(1.1);
    }
    .qc-dl-badge {
      margin-left: auto;
      background: #e74c3c;
      color: white;
      font-size: 10px;
      font-weight: 800;
      padding: 2px 6px;
      border-radius: 12px;
    }
    .qc-drop-sep {
      height: 1px;
      background: rgba(0,0,0,.06);
      margin: 4px 0;
    }
    .qc-drop-logout {
      color: #e74c3c !important;
    }
    .qc-drop-logout .qc-dl-icon {
      background: #fff5f5 !important;
    }
    .qc-drop-logout:hover {
      background: #fff5f5 !important;
      color: #c0392b !important;
    }
    .qc-widget-skeleton {
      width: 130px;
      height: 38px;
      border-radius: 40px;
      background: linear-gradient(90deg, #e8ecf0 25%, #f4f6f8 50%, #e8ecf0 75%);
      background-size: 400px 100%;
      animation: qc-shimmer 1.3s infinite linear;
    }
    @keyframes qc-shimmer {
      0% { background-position: -400px 0 }
      100% { background-position: 400px 0 }
    }
    @media (max-width: 768px) {
      .qc-avatar-info { display: none; }
      .qc-avatar-btn { padding: 5px; }
      .qc-notif-bell { width: 34px; height: 34px; font-size: 15px; }
    }
  `;

  const TIPO_LABEL = {
    'admin': '⚙️ Administrador',
    'empresa': '🏢 Empresa',
    'talento': '🌟 Talento',
    'candidato': '👤 Candidato',
    'artista': '🎵 Artista',
    'chef': '🍽️ Chef',
    'negocio': '🏪 Negocio',
    'servicio': '🎧 Servicios',
  };

  const TIPO_DASHBOARD = {
    'admin': 'gestion-qbc-2025.php',
    'empresa': 'dashboard_empresa.php',
    'negocio': 'dashboard_negocios.php',
    'servicio': 'dashboard_servicios.php',
    'talento': 'dashboard.php',
    'candidato': 'dashboard.php',
    'artista': 'dashboard.php',
    'chef': 'dashboard.php',
  };

  function init() {
    const style = document.createElement('style');
    style.textContent = WIDGET_CSS;
    document.head.appendChild(style);

    const navRight = document.querySelector('.nav-right');
    if (!navRight) return;

    navRight.innerHTML = '<div class="qc-widget-skeleton"></div>';

    fetch('api_usuario.php?action=perfil', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok && data.usuario) {
          renderWidget(navRight, data.usuario);
          cargarNotificaciones();
        } else {
          navRight.innerHTML = '<a href="inicio_sesion.php" class="login">Iniciar sesión</a><a href="registro.php" class="register">Registrarse</a>';
        }
        actualizarMenuMovil(data.ok ? data.usuario : null);
      })
      .catch(function () {
        navRight.innerHTML = '<a href="inicio_sesion.php" class="login">Iniciar sesión</a><a href="registro.php" class="register">Registrarse</a>';
      });
  }

  function obtenerIniciales(nombre, apellido) {
    var n = (nombre || '').trim();
    var a = (apellido || '').trim();
    if (n && a) return (n[0] + a[0]).toUpperCase();
    if (n) {
      var parts = n.split(' ');
      if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
      return (n[0] || 'U').toUpperCase();
    }
    return 'U';
  }

  function escHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function renderWidget(navRight, u) {
    var iniciales = obtenerIniciales(u.nombre, u.apellido);
    var tipoLabel = TIPO_LABEL[u.tipo] || ('👤 ' + (u.tipo || 'Usuario'));
    var dashboard = TIPO_DASHBOARD[u.tipo] || 'dashboard.php';

    navRight.innerHTML = '<div class="qc-user-widget">'
      + '<a href="chat.php" class="qc-notif-bell" id="qcNotifBell" title="Mensajes">💬'
      + '<span class="qc-notif-badge" id="qcNotifBadge" style="display:none">0</span>'
      + '</a>'
      + '<div class="qc-avatar-btn" id="qcAvatarBtn">'
      + '<div class="qc-avatar-img">'
      + (u.foto ? '<img src="' + escHtml(u.foto) + '" alt="">' : '<span>' + iniciales + '</span>')
      + '</div>'
      + '<div class="qc-avatar-info">'
      + '<span class="qc-avatar-nombre">' + escHtml((u.nombre || '').split(' ')[0]) + '</span>'
      + '<span class="qc-avatar-sub"><span class="qc-online-dot"></span>En línea<span class="qc-avatar-arrow">▾</span></span>'
      + '</div>'
      + '</div>'
      + '<div class="qc-dropdown" id="qcDropdown">'
      + '<div class="qc-drop-header">'
      + '<div class="qc-drop-avatar-big">'
      + (u.foto ? '<img src="' + escHtml(u.foto) + '" alt="">' : '<span>' + iniciales + '</span>')
      + '</div>'
      + '<div>'
      + '<div class="qc-drop-nombre">' + escHtml((u.nombre || '') + (u.apellido ? ' ' + u.apellido : '')) + '</div>'
      + '<div class="qc-drop-tipo">' + escHtml(u.profesion || tipoLabel) + '</div>'
      + '<div class="qc-drop-correo">' + escHtml(u.correo || '') + '</div>'
      + '</div>'
      + '</div>'
      + '<div class="qc-drop-badges" id="qcDropBadges"></div>'
      + '<div class="qc-drop-menu">'
      + '<a href="' + dashboard + '" class="qc-drop-link"><span class="qc-dl-icon">🏠</span>Mi panel</a>'
      + '<a href="chat.php" class="qc-drop-link" id="qcChatLink"><span class="qc-dl-icon">💬</span>Mensajes<span class="qc-dl-badge" id="qcChatBadge" style="display:none">0</span></a>'
      + (u.tipo === 'empresa' || u.tipo === 'admin' ? '<a href="Publicar-empleo.html" class="qc-drop-link"><span class="qc-dl-icon">📢</span>Publicar empleo</a>' : '')
      + (u.tipo === 'admin' ? '<a href="gestion-qbc-2025.php" class="qc-drop-link"><span class="qc-dl-icon">⚙️</span>Administración</a>' : '')
      + '<div class="qc-drop-sep"></div>'
      + '<button class="qc-drop-link qc-drop-logout" id="qcLogoutBtn"><span class="qc-dl-icon">🚪</span>Cerrar sesión</button>'
      + '</div>'
      + '</div>'
      + '</div>';

    var btn = document.getElementById('qcAvatarBtn');
    var drop = document.getElementById('qcDropdown');

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = drop.classList.contains('visible');
      drop.classList.toggle('visible', !isOpen);
      btn.classList.toggle('open', !isOpen);
    });

    document.addEventListener('click', function (e) {
      if (!btn.contains(e.target) && !drop.contains(e.target)) {
        drop.classList.remove('visible');
        btn.classList.remove('open');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        drop.classList.remove('visible');
        btn.classList.remove('open');
      }
    });

    document.getElementById('qcLogoutBtn').addEventListener('click', function () {
      window.location.href = 'Php/logout.php';
    });
  }

  function cargarNotificaciones() {
    fetch('api_usuario.php?action=notificaciones', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        var n = data.notificaciones;
        var count = n.mensajes_noLeidos || 0;
        var bell = document.getElementById('qcNotifBadge');
        var chatB = document.getElementById('qcChatBadge');
        if (count > 0) {
          if (bell) { bell.textContent = count > 99 ? '99+' : count; bell.style.display = 'flex'; }
          if (chatB) { chatB.textContent = count > 99 ? '99+' : count; chatB.style.display = 'inline-block'; }
        }
        renderBadgesDropdown(n.badges || []);
      })
      .catch(function () { });
  }

  function renderBadgesDropdown(badges) {
    var container = document.getElementById('qcDropBadges');
    if (!container) return;
    if (!badges.length) { container.style.display = 'none'; return; }

    var colores = {
      'Verificado': { bg: '#e8f5e9', color: '#1f9d55' },
      'Usuario Verificado': { bg: '#e8f5e9', color: '#1f9d55' },
      'Empresa Verificada': { bg: '#e8f5e9', color: '#1f9d55' },
      'Premium': { bg: '#fef3c7', color: '#d97706' },
      'Top': { bg: '#ede9fe', color: '#7c3aed' },
      'Pro': { bg: '#dbeafe', color: '#2563eb' },
      'Destacado': { bg: '#fef9c3', color: '#b45309' },
    };

    container.innerHTML = badges.slice(0, 5).map(function (b) {
      var c = colores[b.nombre] || { bg: (b.color || '#666') + '22', color: b.color || '#666' };
      return '<span class="qc-dbadge" style="background:' + c.bg + ';color:' + c.color + '">'
        + (b.emoji || '') + ' ' + escHtml(b.nombre) + '</span>';
    }).join('');
  }

  function actualizarMenuMovil(u) {
    var mobileAuth = document.querySelector('.mobile-auth');
    if (!mobileAuth) return;

    if (!u) {
      mobileAuth.innerHTML = '<a href="inicio_sesion.php" class="m-login">Iniciar sesión</a><a href="registro.php" class="m-reg">Registrarse</a>';
      return;
    }

    var iniciales = obtenerIniciales(u.nombre, u.apellido);
    var dashboard = TIPO_DASHBOARD[u.tipo] || 'dashboard.php';

    mobileAuth.innerHTML = '<div style="display:flex;flex-direction:column;gap:10px;width:100%;padding:4px 0;">'
      + '<div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-radius:14px;border:1px solid rgba(31,157,85,.15);">'
      + '<div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#1f9d55,#2ecc71);display:flex;align-items:center;justify-content:center;font-weight:800;color:white;font-size:15px;flex-shrink:0;overflow:hidden;">'
      + (u.foto ? '<img src="' + escHtml(u.foto) + '" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">' : iniciales)
      + '</div>'
      + '<div>'
      + '<div style="font-size:15px;font-weight:700;color:#111;">' + escHtml(u.nombre || 'Usuario') + '</div>'
      + '<div style="font-size:12px;color:#1f9d55;font-weight:600;">' + escHtml(TIPO_LABEL[u.tipo] || '') + '</div>'
      + '</div>'
      + '<div style="margin-left:auto;display:flex;align-items:center;gap:5px;">'
      + '<div style="width:8px;height:8px;border-radius:50%;background:#2ecc71;"></div>'
      + '<span style="font-size:11px;color:#888;">En línea</span>'
      + '</div>'
      + '</div>'
      + '<div style="display:flex;gap:8px;">'
      + '<a href="' + dashboard + '" style="flex:1;text-align:center;padding:10px;border-radius:25px;background:#1f9d55;color:white;font-weight:600;font-size:14px;text-decoration:none;">Mi Panel</a>'
      + '<button onclick="window.location.href=\'Php/logout.php\'" style="flex:1;text-align:center;padding:10px;border-radius:25px;border:2px solid #e74c3c;color:#e74c3c;font-weight:600;font-size:14px;background:none;cursor:pointer;font-family:inherit;">Salir</button>'
      + '</div>'
      + '</div>';
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();