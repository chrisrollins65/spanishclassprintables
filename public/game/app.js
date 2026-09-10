/* Room shell.
 *
 * A room is one published packet plus whatever games its data supports. The URL
 * carries the code printed on the worksheet — /j/DEMO1 — and everything after
 * that is a single static fetch. Nothing here needs a session, an account or a
 * round trip once the payload is in hand, which is what lets the whole site be
 * a folder copied into Eat Well Planner's public directory.
 *
 * Asset and payload paths are absolute under /j, which is where this folder
 * lives in the Laravel app's public directory. Relative paths would break the
 * moment a room URL picked up a trailing slash.
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
    const code = codeFromUrl();
    if (!code) return renderCodeEntry();

    let room;
    try {
      const res = await fetch(`/j/rooms/${encodeURIComponent(code)}.json`);
      if (!res.ok) throw new Error(String(res.status));
      room = await res.json();
    } catch {
      return renderCodeEntry(`No encontramos la sala "${code}". Revisa el código de tu hoja.`);
    }

    const available = Object.keys(room.games || {}).filter(k => MODES[k]);
    if (!available.length) return renderCodeEntry('Esta sala no tiene juegos todavía.');
    if (available.length === 1) return MODES[available[0]].mount(app, room);
    renderModePicker(room, available);
  }

  function renderCodeEntry(message) {
    app.innerHTML = '';
    const wrap = document.createElement('section');
    wrap.className = 'centered setup';

    const h1 = document.createElement('h1');
    h1.textContent = 'Spanish Class Printables';
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
      if (code) location.href = `?code=${encodeURIComponent(code)}`;
    };
    input.onkeydown = e => { if (e.key === 'Enter') go.click(); };

    wrap.append(h1, p, input, go);
    app.append(wrap);
    input.focus();
  }

  function renderModePicker(room, available) {
    app.innerHTML = '';
    const wrap = document.createElement('section');
    wrap.className = 'centered setup';

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

    app.append(wrap);
  }

  start();
})();
