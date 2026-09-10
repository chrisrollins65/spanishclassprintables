/* Team quiz board, projected at the front of the room.
 *
 * The whole game lives on one screen for one class period, so there is no
 * server in this file and no network call after the payload loads: state is a
 * plain object mirrored into localStorage, keyed by room code. That mirror is
 * not a nicety — a teacher who bumps F5 forty minutes into a period with live
 * scores on the board has lost the lesson, and this is the entire fix.
 *
 * Answering runs the way the classroom does: every team writes on the printed
 * board sheet, then they read out in turn starting with whoever picked. The
 * teacher taps the team that got it. There are no student devices in this
 * design and no buzzers to sync.
 */
(function () {
  'use strict';

  // Enough hues to stay distinguishable on a washed-out projector; six teams is
  // already more groups than a class of thirty splits into comfortably.
  /* One colour per team, and so the team limit: setup stops adding rows when
   * these run out. Eight, for a class split into pairs or threes. The two added
   * last sit in the widest hue gaps the first six left — teal between the green
   * and the blue, gold between the amber and the green — so no two neighbours
   * on the score strip read as the same team from the back of the room.
   */
  const TEAM_COLORS = ['#B4744A', '#5F8FB4', '#6FA96F', '#C4954A', '#9B6FA9', '#B45F6F', '#3FA7A2', '#D9BE45'];
  const DEFAULT_SECONDS = 45;

  const { el, englishToggle, canSpeakSpanish, speak } = window.RoomUI;

  let root, room, game, state, timer;

  // Dollars rather than bare points: the stake reads higher to a ten-year-old,
  // and it matches the game show the teacher already has in mind, so nothing
  // needs explaining. Overridable per packet — a peso sign costs nothing.
  function money(value) {
    const symbol = game.currency != null ? game.currency : '$';
    return symbol + value;
  }

  function storageKey() {
    return `scp-jeopardy-${room.code}`;
  }

  function save() {
    try {
      localStorage.setItem(storageKey(), JSON.stringify(state));
    } catch {
      // A locked-down browser with storage disabled still plays fine; it just
      // can't survive a refresh. Never let that break the game itself.
    }
  }

  function load() {
    try {
      const raw = localStorage.getItem(storageKey());
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  }

  function clearSaved() {
    try { localStorage.removeItem(storageKey()); } catch {}
  }

  /* ---------- entry ---------- */

  function mount(container, payload) {
    root = container;
    room = payload;
    game = payload.games.jeopardy;
    state = load();
    if (state && state.teams && state.teams.length) renderBoard();
    else renderSetup();
  }

  /* ---------- setup ---------- */

  function renderSetup() {
    const saved = load();
    const names = (saved && saved.teams ? saved.teams.map(t => t.name) : null)
      || ['Equipo 1', 'Equipo 2', 'Equipo 3'];

    root.innerHTML = '';
    const wrap = el('section', 'centered setup');
    wrap.append(
      el('h1', null, game.title || room.theme),
      el('p', 'muted', 'Escribe los nombres de los equipos. Puedes cambiarlos después.')
    );

    const rows = el('div', 'team-rows');
    names.forEach((n, i) => rows.append(teamRow(n, i)));
    wrap.append(rows);

    const addBtn = el('button', 'ghost small', '+ Añadir equipo');
    addBtn.onclick = () => {
      if (rows.children.length >= TEAM_COLORS.length) return;
      rows.append(teamRow(`Equipo ${rows.children.length + 1}`, rows.children.length));
      addBtn.disabled = rows.children.length >= TEAM_COLORS.length;
    };

    const start = el('button', 'primary', '¡Empezar!');
    start.onclick = () => {
      const teams = [...rows.querySelectorAll('input')]
        .map((input, i) => ({
          name: input.value.trim() || `Equipo ${i + 1}`,
          color: TEAM_COLORS[i % TEAM_COLORS.length],
          score: 0,
        }));
      if (teams.length < 2) return;
      state = { teams, turn: 0, used: [], daily: pickDailyCell() };
      save();
      renderBoard();
    };

    wrap.append(addBtn, start);
    root.append(wrap);
  }

  /* One square is worth double.
   *
   * Kept deliberately simple: no wager, no deduction. A wager means stopping a
   * class of ten-year-olds to do arithmetic and argue, and nothing else in this
   * game subtracts points — a team that has just lost their score in front of
   * the room stops playing. Doubling is the whole of the surprise.
   *
   * Never on the cheapest row: finding it there is worth almost nothing, and
   * the moment should feel like a find.
   */
  function pickDailyCell() {
    const columns = game.categories.length;
    const rows = Math.max(...game.categories.map(c => c.clues.length));
    if (columns < 1 || rows < 2) return null;
    const c = Math.floor(Math.random() * columns);
    const r = 1 + Math.floor(Math.random() * (rows - 1));
    return `${c}-${r}`;
  }

  function isDaily(c, r) {
    return state.daily === `${c}-${r}`;
  }

  function teamRow(name, i) {
    const row = el('div', 'team-row');
    const dot = el('span', 'team-dot');
    dot.style.background = TEAM_COLORS[i % TEAM_COLORS.length];
    const input = document.createElement('input');
    input.type = 'text';
    input.value = name;
    input.maxLength = 24;
    row.append(dot, input);
    return row;
  }

  /* ---------- board ---------- */

  function renderBoard() {
    stopTimer();
    root.innerHTML = '';
    const screen = el('section', 'board-screen');

    const controls = el('div', 'award-row');
    controls.append(vocabButton(), resetButton());
    screen.append(window.RoomUI.topBar(turnPill(), game.title || room.theme, controls));

    const rowCount = Math.max(...game.categories.map(c => c.clues.length));
    const board = el('div', 'board');
    // minmax(0, 1fr) rather than a bare 1fr: a plain fr track carries an
    // implicit min-content floor, so with six teams wrapping the score strip to
    // two lines the grid refuses to shrink and the bottom row of the board ends
    // up underneath the scores.
    board.style.gridTemplateColumns = `repeat(${game.categories.length}, minmax(0, 1fr))`;
    board.style.gridTemplateRows = `auto repeat(${rowCount}, minmax(0, 1fr))`;

    game.categories.forEach(cat => board.append(el('div', 'cat', cat.name)));

    // Column-major data, row-major grid: walk rows on the outside so each value
    // band lands on one line across every category.
    for (let r = 0; r < rowCount; r++) {
      game.categories.forEach((cat, c) => {
        const clue = cat.clues[r];
        const cell = el('button', 'clue-cell');
        if (!clue) {
          cell.disabled = true;
          board.append(cell);
          return;
        }
        cell.textContent = money(clue.value);
        if (state.used.includes(`${c}-${r}`)) cell.disabled = true;
        else cell.onclick = () => openClue(c, r);
        board.append(cell);
      });
    }
    screen.append(board, scoreStrip());
    root.append(screen);

    if (state.used.length === countClues()) screen.append(finalBanner());
  }

  function turnPill() {
    const team = state.teams[state.turn];
    const pill = el('div', 'turn-pill');
    pill.style.borderColor = team.color;
    // 20% of the team's colour over the dark ground: a solid block of colour
    // from the back of the room, with the name still at full contrast.
    pill.style.background = team.color + '33';
    const dot = el('span', 'team-dot');
    dot.style.background = team.color;
    pill.append(dot, el('span', 'label', 'Le toca a'), el('span', 'name', team.name));
    return pill;
  }

  function scoreStrip() {
    const strip = el('div', 'scores');
    state.teams.forEach((t, i) => {
      const box = el('div', 'score' + (i === state.turn ? ' active' : ''));
      if (i === state.turn) {
        box.style.borderColor = t.color;
        box.style.background = t.color + '33';
      }
      const dot = el('span', 'team-dot');
      dot.style.background = t.color;
      box.append(dot, el('span', null, t.name), el('span', 'pts', money(t.score)));
      strip.append(box);
    });
    return strip;
  }

  /* The word list, flashed on the wall.
   *
   * The class already has these on paper — the reference page is the first
   * sheet of the pack — so this is not about access. It is about transience: a
   * list up for five seconds is a memory exercise, a sheet on the desk is a
   * lookup table, and only one of those is worth doing mid-game.
   *
   * English stays hidden until asked for. The Spanish alone is a reminder; the
   * English turns any clue into a thirty-way multiple choice, which is a much
   * bigger concession and should be a deliberate one.
   */
  function vocabButton(label) {
    const btn = el('button', 'small', label || 'Vocabulario');
    btn.disabled = !bankItems().length;
    btn.onclick = openVocab;
    return btn;
  }

  function bankItems() {
    return Array.isArray(room.items) ? room.items : [];
  }

  function openVocab() {
    const items = bankItems();
    if (!items.length) return;

    const screen = el('section', 'clue-screen vocab-screen');
    const head = el('div', 'clue-head');
    head.append(el('div', 'where', 'Vocabulario'),
      el('div', 'note', 'Míralo bien — desaparece enseguida'));

    const grid = el('div', 'vocab-grid');
    items.forEach(item => {
      const cell = el('div', 'vocab-item');
      cell.append(el('span', 'vocab-es', item.face));
      const en = el('span', 'vocab-en', item.en || '');
      en.hidden = true;
      cell.append(en);
      grid.append(cell);
    });

    const english = el('button', 'small', 'Ver en inglés');
    let showing = false;
    english.onclick = () => {
      showing = !showing;
      grid.querySelectorAll('.vocab-en').forEach(e => { e.hidden = !showing; });
      english.textContent = showing ? 'Ocultar el inglés' : 'Ver en inglés';
    };

    const close = el('button', 'primary', 'Cerrar');
    close.onclick = () => {
      // The capture flag has to match the one it was added with, or the
      // listener survives and swallows every later Escape.
      document.removeEventListener('keydown', vocabEsc, true);
      screen.remove();
    };

    function vocabEsc(e) {
      if (e.key === 'Escape') {
        e.stopImmediatePropagation();
        close.click();
      }
    }
    // Captured, so Escape closes the word list rather than the clue underneath.
    document.addEventListener('keydown', vocabEsc, true);

    screen.append(head, el('div', 'clue-body vocab-body', null, [grid]),
      el('div', 'clue-controls', null, [el('div', 'award-row', null, [english, close])]));
    root.append(screen);
  }

  // Two-click confirm rather than a browser dialog: a modal on a projector is a
  // dead session if it opens by accident mid-game.
  function resetButton() {
    const btn = el('button', 'ghost small', 'Reiniciar');
    let armed = false;
    btn.onclick = () => {
      if (!armed) {
        armed = true;
        btn.textContent = '¿Seguro? Toca otra vez';
        setTimeout(() => { armed = false; btn.textContent = 'Reiniciar'; }, 4000);
        return;
      }
      clearSaved();
      renderSetup();
    };
    return btn;
  }

  function countClues() {
    return game.categories.reduce((n, c) => n + c.clues.length, 0);
  }

  function finalBanner() {
    const best = Math.max(...state.teams.map(t => t.score));
    const winners = state.teams.filter(t => t.score === best).map(t => t.name);
    const banner = el('div', 'final-banner');
    banner.append(el('h2', null, `¡Se acabó! Ganador: ${winners.join(' y ')}`));
    return banner;
  }

  /* ---------- clue ---------- */

  function openClue(c, r) {
    const clue = game.categories[c].clues[r];
    const daily = isDaily(c, r);
    // What this square actually pays. The board still shows the plain value —
    // the double is the surprise, and printing it would give the square away.
    const value = daily ? clue.value * 2 : clue.value;
    const screen = el('section', 'clue-screen');

    if (daily) screen.append(el('div', 'daily-banner', '¡DOBLE DIARIO!'));

    const head = el('div', 'clue-head');
    const where = el('div', 'where');
    where.append(
      document.createTextNode(game.categories[c].name),
      document.createTextNode(' · '),
      el('span', 'value', money(value) + (daily ? ' ×2' : ''))
    );
    head.append(where, el('div', 'note', 'Escribe tu respuesta en esta casilla de tu hoja'));

    const body = el('div', 'clue-body');
    const answer = el('p', 'answer hidden', clue.answer);
    const clock = el('div', 'timer');

    /* The top row is heard, not read.
     *
     * That is the promise the dollar value makes, so the text stays off screen
     * until someone asks for it — showing it would turn the hardest clue on the
     * board into the same reading exercise as the $200.
     */
    if (clue.audio && canSpeakSpanish()) {
      const glyph = el('div', 'call-audio', '🔊');
      const note = el('p', 'call-note', 'Escuchen con atención');

      const hidden = el('p', 'prompt audio-text');
      hidden.hidden = true;
      hidden.textContent = clue.prompt;

      const again = el('button', 'small', 'Repetir');
      again.onclick = () => speak(clue.prompt);

      const showText = el('button', 'small', 'Ver el texto');
      showText.onclick = () => {
        hidden.hidden = !hidden.hidden;
        showText.textContent = hidden.hidden ? 'Ver el texto' : 'Ocultar el texto';
      };

      body.append(glyph, note, hidden, el('div', 'award-row', null, [again, showText]));
      speak(clue.prompt);
    } else {
      body.append(el('p', 'prompt', clue.prompt));
    }

    // A hint, not a rewrite: the clue keeps the difficulty the team accepted
    // when they picked its value, and the English only helps them read it.
    if (clue.promptEn) body.append(englishToggle(clue.promptEn));

    body.append(clock, answer);

    const controls = el('div', 'clue-controls');

    const timerBtn = el('button', 'small', `⏱ ${DEFAULT_SECONDS}s para escribir`);
    timerBtn.onclick = () => { timerBtn.disabled = true; startTimer(clock, clue.seconds || DEFAULT_SECONDS); };

    const revealBtn = el('button', 'small', 'Mostrar la respuesta');
    revealBtn.onclick = () => { answer.classList.remove('hidden'); revealBtn.disabled = true; };

    controls.append(
      el('p', 'hint', 'Todos escriben en su hoja. Empieza el equipo que eligió; si falla, pasa al siguiente.'),
      el('div', 'award-row', null, awardButtons(c, r, value, answer)),
      el('div', 'award-row', null, [timerBtn, vocabButton('Ver vocabulario'), revealBtn])
    );

    screen.append(head, body, controls);
    root.append(screen);

    document.addEventListener('keydown', escHandler);
  }

  // Teams are offered in answering order — the team that picked first, then
  // around the table — so the teacher taps down the row as they read out.
  function awardButtons(c, r, value, answerEl) {
    const buttons = [];
    for (let i = 0; i < state.teams.length; i++) {
      const idx = (state.turn + i) % state.teams.length;
      const team = state.teams[idx];
      const btn = el('button', null);
      const dot = el('span', 'team-dot');
      dot.style.background = team.color;
      btn.append(dot, document.createTextNode(`${team.name} +${money(value)}`));
      btn.onclick = () => { answerEl.classList.remove('hidden'); resolveClue(c, r, idx); };
      buttons.push(btn);
    }
    const none = el('button', 'ghost', 'Nadie acertó');
    none.onclick = () => { answerEl.classList.remove('hidden'); resolveClue(c, r, null); };
    buttons.push(none);
    return buttons;
  }

  function resolveClue(c, r, teamIndex) {
    const clue = game.categories[c].clues[r];
    // The doubled value goes to whoever answers it, not to whoever picked it:
    // the square is worth double, full stop, and that needs no adjudicating.
    const value = isDaily(c, r) ? clue.value * 2 : clue.value;
    if (teamIndex !== null) state.teams[teamIndex].score += value;
    state.used.push(`${c}-${r}`);
    // The pick rotates rather than staying with whoever scored: over a period
    // that hands every team the same number of choices, which matters more in a
    // classroom than rewarding the team that is already winning.
    state.turn = (state.turn + 1) % state.teams.length;
    save();

    const back = el('button', 'primary', 'Volver al tablero →');
    back.onclick = closeClue;
    const controls = root.querySelector('.clue-controls');
    controls.innerHTML = '';
    controls.append(back);
    back.focus();
    // Clear the clock as well as stopping it: a frozen countdown sitting next
    // to the revealed answer just reads as a second number to puzzle over.
    stopTimer();
    const clock = root.querySelector('.timer');
    if (clock) {
      clock.textContent = '';
      clock.classList.remove('done');
    }
  }

  function closeClue() {
    document.removeEventListener('keydown', escHandler);
    // A listening clue left mid-sentence would otherwise carry on talking over
    // the board while the next team is choosing.
    if (window.speechSynthesis) window.speechSynthesis.cancel();
    renderBoard();
  }

  function escHandler(e) {
    if (e.key === 'Escape') closeClue();
  }

  /* ---------- timer ---------- */

  function startTimer(node, seconds) {
    stopTimer();
    let left = seconds;
    node.textContent = String(left);
    timer = setInterval(() => {
      left -= 1;
      node.textContent = String(Math.max(left, 0));
      if (left <= 0) {
        node.classList.add('done');
        node.textContent = '¡Tiempo!';
        stopTimer();
      }
    }, 1000);
  }

  function stopTimer() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  window.Jeopardy = { mount };
})();
