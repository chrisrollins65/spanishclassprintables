/* Room shell.
 *
 * A room is one published packet plus whatever games its data supports. The URL
 * carries the code printed on the worksheet — /j/DEMO1 — and everything after
 * that is a single fetch of the room's payload. Nothing here needs a session,
 * an account or a round trip once the payload is in hand.
 *
 * Asset paths are absolute under /game, where this folder lives, and the
 * payload path is absolute under /j, where Laravel routes it. Relative paths
 * would break the moment a room URL picked up a trailing slash.
 */
(function () {
  'use strict';

  const MODES = {
    jeopardy: { label: 'Concurso por equipos', mount: (root, room) => window.Jeopardy.mount(root, room) },
    bingo: { label: 'Bingo', mount: (root, room) => window.Bingo.mount(root, room) },
  };

  const app = document.getElementById('app');

  function codeFromUrl() {
    const fromPath = location.pathname.replace(/\/+$/, '').split('/').pop();
    if (fromPath && /^[A-Z0-9]{4,8}$/i.test(fromPath) && fromPath.toLowerCase() !== 'j') {
      return fromPath.toUpperCase();
    }
    const q = new URLSearchParams(location.search).get('code');
    return q ? q.toUpperCase() : null;
  }

  async function start() {
    // A teacher's own game (/my-games/{id}/play) names its payload on the page.
    // It is private to a logged-in teacher, so there is no code to type, and it
    // is not kept offline: the worker only looks after /j.
    const ownGameUrl = app.dataset.payloadUrl;
    if (ownGameUrl) return startOwnGame(ownGameUrl);

    const code = codeFromUrl();
    if (!code) return renderCodeEntry();

    const payloadUrl = `/j/rooms/${encodeURIComponent(code)}.json`;
    let room;
    try {
      const res = await fetch(payloadUrl);
      if (res.status === 404) {
        return renderCodeEntry(`No encontramos la sala "${code}". Revisa el código de tu hoja.`);
      }
      if (!res.ok) throw new Error(String(res.status));
      room = await res.json();
    } catch {
      // Not a wrong code: the site or the connection is down, and this room has
      // never been opened on this device, so there is no saved copy to fall back on.
      return renderCodeEntry('No pudimos conectar con el sitio. Revisa la conexión a internet e inténtalo de nuevo.');
    }
    keepOffline(payloadUrl);
    mountRoom(room);
  }

  async function startOwnGame(payloadUrl) {
    let room;
    try {
      const res = await fetch(payloadUrl, { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) throw new Error(String(res.status));
      room = await res.json();
    } catch {
      return renderMessage('No pudimos abrir este juego. Revisa la conexión a internet y vuelve a tus juegos.', '/my-games', 'Mis juegos');
    }
    // So the setup screen does not offer to customize a game that already is
    // the teacher's own customized copy.
    room.own = true;
    mountRoom(room);
  }

  function mountRoom(room) {
    const available = Object.keys(room.games || {}).filter(k => MODES[k]);
    if (!available.length) return renderCodeEntry('Esta sala no tiene juegos todavía.');
    if (available.length === 1) return MODES[available[0]].mount(app, room);
    renderModePicker(room, available);
  }

  /* Keep this room on the device for when the connection drops (/room-sw.js).
   *
   * Only once a room has loaded, so a mistyped code is never saved. A page the
   * worker already controls had everything saved on the way in; a first visit
   * came before the worker existed, so it hands over the list of what it loaded.
   */
  function keepOffline(payloadUrl) {
    if (!('serviceWorker' in navigator)) return;
    const sw = navigator.serviceWorker;
    const firstVisit = !sw.controller;

    sw.register('/room-sw.js', { scope: '/j' })
      .then(() => sw.ready)
      .then(reg => {
        if (!firstVisit) return;
        const urls = [...document.querySelectorAll('script[src], link[rel="stylesheet"]')]
          .map(node => node.src || node.href);
        // The logo is named by ui.js rather than by the shell, so it is in
        // neither list above and would be the one broken image on a room opened
        // without a connection. Everything else a screen draws is CSS or text.
        reg.active.postMessage({ type: 'keep', urls: [...urls, '/game/logo.png', payloadUrl] });
      })
      .catch(() => {});
  }

  function renderMessage(message, href, label) {
    app.innerHTML = '';
    const wrap = document.createElement('section');
    wrap.className = 'centered setup';
    const p = document.createElement('p');
    p.className = 'error';
    p.textContent = message;
    // A button, not a link: the game's styles dress buttons only.
    const back = document.createElement('button');
    back.className = 'primary';
    back.textContent = label;
    back.onclick = () => { location.href = href; };
    wrap.append(window.RoomUI.brandMark('full'), p, back);
    app.append(wrap);
  }

  function renderCodeEntry(message) {
    app.innerHTML = '';
    const wrap = document.createElement('section');
    wrap.className = 'centered setup';

    // The store's name is the logo here rather than an h1 beside it: this screen
    // is a logo, one line and a box, and it is the only one with the room to
    // give the mark its full size.
    const h1 = window.RoomUI.brandMark('full');
    const p = document.createElement('p');
    p.className = message ? 'error' : 'muted';
    p.textContent = message || 'Escribe el código que aparece en tu hoja.';

    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = 'CÓDIGO';
    input.maxLength = 8;
    input.autocapitalize = 'characters';
    input.style.textTransform = 'uppercase';

    const go = document.createElement('button');
    go.className = 'primary';
    go.textContent = 'Entrar';
    go.onclick = () => {
      const code = input.value.trim().toUpperCase();
      // From /j itself, not relative: on /j/WRONG a bare `?code=` keeps the path,
      // and the code in the path wins, so the wrong room just loads again.
      if (code) location.href = `/j?code=${encodeURIComponent(code)}`;
    };
    input.onkeydown = e => { if (e.key === 'Enter') go.click(); };

    wrap.append(h1, p, input, go, window.RoomUI.moreGames());
    app.append(wrap);
    input.focus();
  }

  function renderModePicker(room, available) {
    app.innerHTML = '';
    const wrap = document.createElement('section');
    wrap.className = 'centered setup';

    wrap.append(window.RoomUI.brandMark());

    const h1 = document.createElement('h1');
    h1.textContent = room.theme;
    wrap.append(h1);

    available.forEach(key => {
      const btn = document.createElement('button');
      btn.className = 'primary';
      btn.textContent = MODES[key].label;
      btn.onclick = () => MODES[key].mount(app, room);
      wrap.append(btn);
    });

    wrap.append(window.RoomUI.moreGames());
    app.append(wrap);
  }

  start();
})();
