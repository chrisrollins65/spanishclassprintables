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

  /* Every team gets an animal, dealt at random.
   *
   * Dealt, not chosen: a class asked to pick mascots spends ten minutes voting
   * and the teacher never gets them back. Setup opens with the teams already
   * named, so the quick path is still one press of ¡Empezar!; a tap on the
   * animal deals another, for the group that really minds.
   *
   * The names are real Spanish — article, plural, gender — so even the team
   * name is a bit of vocabulary. Left out on purpose: any animal a child gets
   * called as an insult (cerdos, burros, vacas, ratas, monos, ballenas). A team
   * name is shouted across the room all period.
   */
  const MASCOTS = [
    { emoji: '🐯', name: 'Los Tigres' },
    { emoji: '🦊', name: 'Los Zorros' },
    { emoji: '🐸', name: 'Las Ranas' },
    { emoji: '🐙', name: 'Los Pulpos' },
    { emoji: '🦁', name: 'Los Leones' },
    { emoji: '🐢', name: 'Las Tortugas' },
    { emoji: '🐧', name: 'Los Pingüinos' },
    { emoji: '🦉', name: 'Los Búhos' },
    { emoji: '🐬', name: 'Los Delfines' },
    { emoji: '🐼', name: 'Los Pandas' },
    { emoji: '🦄', name: 'Los Unicornios' },
    { emoji: '🦈', name: 'Los Tiburones' },
    { emoji: '🦒', name: 'Las Jirafas' },
    { emoji: '🦋', name: 'Las Mariposas' },
    { emoji: '🐨', name: 'Los Koalas' },
    { emoji: '🐉', name: 'Los Dragones' },
    { emoji: '🐝', name: 'Las Abejas' },
    { emoji: '🐺', name: 'Los Lobos' },
  ];

  // How long the "last seconds" of the writing timer are: ticking, and red.
  const URGENT_SECONDS = 5;

  const { el, englishToggle, canSpeakSpanish, speak } = window.RoomUI;
  const fx = window.RoomFX;

  let root, room, game, state, timer;
  // A score that has just changed, to be counted up the next time the board
  // draws. Memory only: after a refresh there is nothing to animate.
  let pendingScore = null;
  // Set when the last square is resolved, so the winner is celebrated once —
  // not every time a finished board is redrawn or reloaded.
  let celebrateFinish = false;
  // Lead changes and streaks from the award just made, announced when the board
  // comes back. Memory only, like pendingScore.
  let pendingMoments = [];

  /* How many right answers in a row put a team "en racha".
   *
   * A right answer adds one — on any team's clue, stolen or not. Missing your
   * OWN clue ends it; missing someone else's does not, because a team is not
   * expected to answer those, and losing a flame for another team's question
   * would feel like a rule nobody explained. Three, because with turns rotating
   * that is a real run but still happens most games.
   */
  const STREAK = 3;
  // How long a lead-change or streak callout stays over the board.
  const MOMENT_MS = 2600;

  /* The podium reveal, in beats: third place rises, then second, then first,
   * each step followed by its teams dropping onto it. These times drive the
   * sounds; styles.css carries the same numbers as animation delays. */
  const PODIUM_BEATS = { 3: 300, 2: 800, 1: 1300 };
  const PODIUM_WINNER_AT = 1600;

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
    // A saved game's teams keep their names; a team saved before mascots
    // existed is dealt one now.
    const teams = saved && saved.teams
      ? saved.teams.map(t => ({ name: t.name, mascot: t.mascot }))
      : [null, null, null];

    root.innerHTML = '';
    const wrap = el('section', 'centered setup');
    wrap.append(
      el('h1', null, game.title || room.theme),
      el('p', 'muted', 'Escribe los nombres de los equipos. Toca el animal para cambiarlo.')
    );

    const rows = el('div', 'team-rows');
    wrap.append(rows);
    teams.forEach((t, i) => rows.append(teamRow(rows, i, t)));

    const addBtn = el('button', 'ghost small', '+ Añadir equipo');
    addBtn.onclick = () => {
      if (rows.children.length >= TEAM_COLORS.length) return;
      rows.append(teamRow(rows, rows.children.length, null));
      addBtn.disabled = rows.children.length >= TEAM_COLORS.length;
    };

    const start = el('button', 'primary', '¡Empezar!');
    start.onclick = () => {
      const teams = [...rows.children]
        .map((row, i) => {
          const mascot = mascotFor(row.dataset.mascot);
          return {
            name: row.querySelector('input').value.trim() || (mascot ? mascot.name : `Equipo ${i + 1}`),
            color: TEAM_COLORS[i % TEAM_COLORS.length],
            mascot: row.dataset.mascot,
            score: 0,
          };
        });
      if (teams.length < 2) return;
      state = { teams, turn: 0, used: [], daily: pickDailyCell() };
      save();
      fx.play('start');
      renderBoard({ intro: true });
    };

    wrap.append(addBtn, start);
    root.append(wrap);
  }

  function mascotFor(emoji) {
    return MASCOTS.find(m => m.emoji === emoji) || null;
  }

  // Any animal no other row on the setup screen already has.
  function dealMascot(rows, except) {
    const taken = new Set([...rows.children].map(r => r.dataset.mascot));
    const free = MASCOTS.filter(m => !taken.has(m.emoji) && m.emoji !== except);
    const pool = free.length ? free : MASCOTS;
    return pool[Math.floor(Math.random() * pool.length)];
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

  /* One team on the setup screen. `team` is a saved team, or null to deal a
   * fresh one. The row is dealt before it joins the list so the deal can see
   * which animals the other rows already hold. */
  function teamRow(rows, i, team) {
    const row = el('div', 'team-row');
    const mascot = (team && mascotFor(team.mascot)) || dealMascot(rows);
    row.dataset.mascot = mascot.emoji;

    const badge = el('button', 'team-badge reroll', mascot.emoji);
    badge.style.background = TEAM_COLORS[i % TEAM_COLORS.length];
    badge.title = 'Otro animal';
    badge.setAttribute('aria-label', 'Otro animal');

    const input = document.createElement('input');
    input.type = 'text';
    input.value = team ? team.name : mascot.name;
    input.maxLength = 24;

    badge.onclick = () => {
      const old = mascotFor(row.dataset.mascot);
      const next = dealMascot(rows, row.dataset.mascot);
      row.dataset.mascot = next.emoji;
      badge.textContent = next.emoji;
      // A name the teacher typed is theirs; only the dealt name follows the
      // animal, or "Los Tigres" would end up with a frog on it.
      if (old && input.value.trim() === old.name) input.value = next.name;
      badge.classList.remove('spin');
      void badge.offsetWidth;
      badge.classList.add('spin');
    };

    row.append(badge, input);
    return row;
  }

  /* A team's colour with its animal on it, everywhere the team is named. A
   * team saved before mascots existed still gets its plain colour dot. */
  function teamBadge(team) {
    const badge = el('span', 'team-badge', team.mascot || '');
    badge.style.background = team.color;
    return badge;
  }

  /* ---------- board ---------- */

  function renderBoard(opts = {}) {
    stopTimer();
    root.innerHTML = '';
    const screen = el('section', 'board-screen');

    const controls = el('div', 'award-row');
    controls.append(fx.muteButton(), vocabButton(), resetButton());

    // A finished game's board is all empty squares, so the standings take its
    // place — and whose turn it is no longer means anything.
    if (state.used.length === countClues()) {
      const reveal = celebrateFinish;
      celebrateFinish = false;
      pendingMoments = [];
      pendingScore = null;
      screen.append(window.RoomUI.topBar(el('div'), game.title || room.theme, controls), podium(reveal));
      root.append(screen);
      if (reveal) revealPodium();
      return;
    }

    screen.append(window.RoomUI.topBar(turnPill(), game.title || room.theme, controls));

    const rowCount = Math.max(...game.categories.map(c => c.clues.length));
    // The board deals itself onto the screen once, when the game starts — not
    // on every return from a clue, when the class wants it back at once.
    const board = el('div', 'board' + (opts.intro ? ' intro' : ''));
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
        else cell.onclick = () => openClue(c, r, cell);
        cell.style.setProperty('--i', String(r * game.categories.length + c));
        board.append(cell);
      });
    }
    screen.append(board, scoreStrip());
    root.append(screen);
    if (pendingMoments.length) showMoments(screen);
  }

  /* The team strictly ahead of everyone, or null for a tie at the top or a
   * board where nobody has scored yet. Crowns go to every team sharing the
   * top score; "¡Nuevo líder!" needs one team to have taken it. */
  function soleLeader() {
    const best = Math.max(...state.teams.map(t => t.score));
    if (best <= 0) return null;
    const top = state.teams.map((t, i) => i).filter(i => state.teams[i].score === best);
    return top.length === 1 ? top[0] : null;
  }

  function isLeading(team) {
    const best = Math.max(...state.teams.map(t => t.score));
    return best > 0 && team.score === best;
  }

  /* Lead changes and streaks, called out over the board as it comes back.
   *
   * Not on the clue screen: that already has the team's "+$200" and its
   * confetti, and a second message there would be read past. Back on the board
   * the room is looking up anyway, waiting to see the scores move.
   *
   * pointer-events off, so the next team can pick a square straight through
   * it — a callout must never be something the teacher has to dismiss.
   */
  function showMoments(screen) {
    const moments = pendingMoments;
    pendingMoments = [];
    const card = el('div', 'moment-card');
    moments.forEach(m => {
      const team = state.teams[m.team];
      const line = el('div', 'moment-line');
      line.append(
        el('span', 'moment-head', m.kind === 'leader' ? '👑 ¡Nuevo líder!' : '🔥 ¡En racha!'),
        teamBadge(team),
        el('span', 'moment-name', team.name)
      );
      if (m.kind === 'streak') line.append(el('span', 'moment-note', `¡${team.streak} seguidas!`));
      card.append(line);
    });
    const overlay = el('div', 'moment', null, [card]);
    screen.append(overlay);
    fx.play(moments.some(m => m.kind === 'leader') ? 'leader' : 'streak');
    setTimeout(() => overlay.classList.add('leaving'), MOMENT_MS);
    setTimeout(() => overlay.remove(), MOMENT_MS + 400);
  }

  /* The final standings, on a podium.
   *
   * Dense ranking: teams on the same score share a step, and the next score
   * down is the next step — so a tie for first still leaves a second and a
   * third place, and more children end the lesson on the podium. Anyone below
   * third is still named, with their place, underneath: every team finishes
   * somewhere, and nobody's name simply vanishes.
   *
   * `reveal` is the once-only moment the last square is resolved; a reload of a
   * finished game shows the podium already built.
   */
  function podium(reveal) {
    const levels = [...new Set(state.teams.map(t => t.score))].sort((a, b) => b - a);
    const placeOf = t => levels.indexOf(t.score) + 1;

    const wrap = el('div', 'podium-screen' + (reveal ? ' reveal' : ''));
    // The class will ask for the confetti again.
    wrap.title = 'Toca para celebrar otra vez';
    wrap.onclick = celebrate;
    wrap.append(el('h2', 'podium-title', '🏆 ¡Se acabó!'));

    const stage = el('div', 'podium');
    // Second, first, third, left to right: the shape everyone knows.
    [2, 1, 3].filter(place => place <= levels.length).forEach(place => {
      const col = el('div', `podium-col place-${place}`);
      const who = el('div', 'podium-teams');
      state.teams.filter(t => placeOf(t) === place).forEach(t => {
        const entry = el('div', 'podium-team');
        if (place === 1) entry.append(el('span', 'podium-crown', '👑'));
        const badge = teamBadge(t);
        badge.classList.add('podium-badge');
        entry.append(badge, el('span', 'podium-name', t.name), el('span', 'podium-pts', money(t.score)));
        who.append(entry);
      });
      col.append(who, el('div', 'podium-step', null, [el('span', null, `${place}º`)]));
      stage.append(col);
    });
    wrap.append(stage);

    const rest = state.teams.filter(t => placeOf(t) > 3).sort((a, b) => b.score - a.score);
    if (rest.length) {
      const list = el('div', 'podium-rest');
      rest.forEach(t => {
        list.append(el('div', 'podium-rest-item', null, [
          el('span', 'podium-rest-place', `${placeOf(t)}º`),
          teamBadge(t),
          el('span', null, t.name),
          el('span', 'pts', money(t.score)),
        ]));
      });
      wrap.append(list);
    }
    return wrap;
  }

  // A sound for each step as it rises, then the whole celebration for first.
  function revealPodium() {
    if (fx.reducedMotion()) return celebrate();
    const places = new Set(state.teams.map(t => t.score)).size;
    [3, 2].filter(p => p <= places).forEach(p => setTimeout(() => fx.play('step'), PODIUM_BEATS[p]));
    setTimeout(celebrate, PODIUM_WINNER_AT);
  }

  function celebrate() {
    const best = Math.max(...state.teams.map(t => t.score));
    fx.play('victory');
    fx.confetti({ colors: state.teams.filter(t => t.score === best).map(t => t.color).concat('#E8B461', '#FDF8F1') });
  }

  function turnPill() {
    const team = state.teams[state.turn];
    const pill = el('div', 'turn-pill');
    pill.style.borderColor = team.color;
    // 20% of the team's colour over the dark ground: a solid block of colour
    // from the back of the room, with the name still at full contrast.
    pill.style.background = team.color + '33';
    pill.append(teamBadge(team), el('span', 'label', 'Le toca a'), el('span', 'name', team.name));
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
      const pts = el('span', 'pts', money(t.score));
      // The crown sits on whoever is ahead, ties included; it drops in fresh
      // on a team that has just taken the lead.
      if (isLeading(t)) {
        const tookLead = pendingMoments.some(m => m.kind === 'leader' && m.team === i);
        box.append(el('span', 'crown' + (tookLead ? ' drop' : ''), '👑'));
      }
      box.append(teamBadge(t), el('span', null, t.name), pts);
      if ((t.streak || 0) >= STREAK) box.append(el('span', 'streak', `🔥${t.streak}`));
      // The team that just scored: its box jumps and its total climbs from the
      // old score, so the room sees whose points those were.
      if (pendingScore && pendingScore.index === i) {
        const { from, to } = pendingScore;
        box.classList.add('bump');
        requestAnimationFrame(() => fx.countUp(pts, from, to, money));
      }
      strip.append(box);
    });
    pendingScore = null;
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

  /* ---------- clue ---------- */

  function openClue(c, r, cell) {
    const clue = game.categories[c].clues[r];
    const daily = isDaily(c, r);
    // What this square actually pays. The board still shows the plain value —
    // the double is the surprise, and printing it would give the square away.
    const value = daily ? clue.value * 2 : clue.value;
    const screen = el('section', 'clue-screen' + (daily ? ' daily' : ''));

    if (daily) screen.append(el('div', 'daily-banner', '¡DOBLE DIARIO!'));

    const head = el('div', 'clue-head');
    const where = el('div', 'where');
    where.append(
      document.createTextNode(game.categories[c].name),
      document.createTextNode(' · '),
      // The whole sum, not the result with "×2" after it: "$800 ×2" reads as
      // $1600, and a class that thinks it is playing for $1600 feels robbed.
      el('span', 'value', daily ? `${money(clue.value)} × 2 = ${money(value)}` : money(value))
    );
    head.append(where, el('div', 'note', 'Escribe tu respuesta en esta casilla de tu hoja'));

    const body = el('div', 'clue-body');
    const answer = el('p', 'answer hidden', clue.answer);
    const clock = el('div', 'timer');

    // The clue waits for its opening — the chime, or the Daily Double's whole
    // splash — and a spoken clue waits for the clue: never two sounds at once.
    const opened = daily ? dailySplash(screen, clue.value, value) : fx.play('open');

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
      // Unless the clue was closed while the chime played: a clue read out over
      // the board is one nobody can see or answer.
      opened.then(() => { if (screen.isConnected) speak(clue.prompt); });
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
    if (cell) fx.growFrom(screen, cell.getBoundingClientRect());

    document.addEventListener('keydown', escHandler);
  }

  /* The Daily Double, announced before the clue.
   *
   * A banner along the top was easy to read past, and "doble" alone does not
   * tell a ten-year-old what they are playing for. So the whole screen stops
   * for it, and the sum is acted out in beats — the value, ×2 stamped on, the
   * new total — because watching $400 become $800 explains the rule without a
   * sentence of it being understood.
   *
   * It waits for the teacher: the clue, and a listening clue's voice, only
   * arrive on ¡Vamos!, so the room can be wound up first. Resolves then.
   */
  function dailySplash(screen, base, doubled) {
    const splash = el('div', 'dd-splash');
    const go = el('button', 'primary big dd-go', '¡Vamos! →');
    splash.append(
      el('div', 'dd-burst'),
      el('h2', 'dd-title', '¡DOBLE DIARIO!'),
      el('p', 'dd-line', '¡Si aciertan, ganan el doble!'),
      el('div', 'dd-math', null, [
        el('span', 'dd-base', money(base)),
        el('span', 'dd-times', '× 2'),
        el('span', 'dd-eq', '='),
        el('span', 'dd-total', money(doubled)),
      ]),
      go
    );
    screen.append(splash);

    fx.play('daily');
    fx.confetti({ colors: ['#E8B461', '#FDF8F1', '#C4954A', '#B45F6F'], count: 120 });
    // Each beat of the sum gets its sound, timed to the CSS delays on
    // .dd-times and .dd-total. Under reduced motion the sum is simply there.
    if (!fx.reducedMotion()) {
      setTimeout(() => { if (splash.isConnected) fx.play('stamp'); }, 1300);
      setTimeout(() => { if (splash.isConnected) fx.play('score'); }, 1900);
    }
    // After openClue has put the screen on the page, so Enter or the space bar
    // can dismiss it — the teacher's hand is often on the keyboard, not the mouse.
    setTimeout(() => go.focus(), 0);

    return new Promise(resolve => {
      go.onclick = () => {
        go.disabled = true;
        screen.classList.add('dd-revealed');
        const done = () => { splash.remove(); resolve(); };
        if (fx.reducedMotion()) return done();
        splash.classList.add('leaving');
        setTimeout(done, 300);
      };
    });
  }

  // Teams are offered in answering order — the team that picked first, then
  // around the table — so the teacher taps down the row as they read out.
  function awardButtons(c, r, value, answerEl) {
    const buttons = [];
    for (let i = 0; i < state.teams.length; i++) {
      const idx = (state.turn + i) % state.teams.length;
      const team = state.teams[idx];
      const btn = el('button', null);
      btn.append(teamBadge(team), document.createTextNode(`${team.name} +${money(value)}`));
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
    const daily = isDaily(c, r);
    const value = daily ? clue.value * 2 : clue.value;
    let pop = null;
    let team = null;
    if (teamIndex !== null) {
      team = state.teams[teamIndex];
      pendingScore = { index: teamIndex, from: team.score, to: team.score + value };
      team.score += value;
      pop = el('div', 'award-pop', null, [teamBadge(team), el('span', null, `${team.name} +${money(value)}`)]);
      pop.style.borderColor = team.color;
    } else {
      fx.play('none');
    }
    noteMoments(teamIndex);
    state.used.push(`${c}-${r}`);
    if (state.used.length === countClues()) celebrateFinish = true;
    // The pick rotates rather than staying with whoever scored: over a period
    // that hands every team the same number of choices, which matters more in a
    // classroom than rewarding the team that is already winning.
    state.turn = (state.turn + 1) % state.teams.length;
    save();

    const back = el('button', 'primary', 'Volver al tablero →');
    back.onclick = closeClue;
    const controls = root.querySelector('.clue-controls');
    controls.innerHTML = '';
    if (pop) controls.append(pop);
    controls.append(back);
    back.focus();
    if (team) celebrateAnswer(team, pop, daily, r);
    // Clear the clock as well as stopping it: a frozen countdown sitting next
    // to the revealed answer just reads as a second number to puzzle over.
    stopTimer();
    const clock = root.querySelector('.timer');
    if (clock) {
      clock.textContent = '';
      clock.classList.remove('done', 'running', 'urgent');
    }
  }

  /* Streaks and the lead, updated for the award just made (see STREAK), with
   * anything worth announcing queued for the board. Runs before the turn
   * rotates, while state.turn is still the team that picked.
   *
   * "¡Nuevo líder!" is an overtake: a team taking the lead from the team that
   * last held it on its own. The very first points of the game make a leader
   * without a callout, and a team winning back a lead it had only tied does
   * not get one either — it never lost it.
   */
  function noteMoments(teamIndex) {
    if (teamIndex !== null) {
      const team = state.teams[teamIndex];
      team.streak = (team.streak || 0) + 1;
      if (team.streak === STREAK) pendingMoments.push({ kind: 'streak', team: teamIndex });
    }
    if (teamIndex !== state.turn) state.teams[state.turn].streak = 0;

    const leader = soleLeader();
    if (leader === null) return;
    if (state.leader != null && state.leader !== leader) pendingMoments.unshift({ kind: 'leader', team: leader });
    state.leader = leader;
  }

  /* A right answer, celebrated in proportion.
   *
   * Twenty-odd of these a game, so an ordinary one is short: a quick ta-da and
   * a burst out of the team's own "+$200", in its colour, so the party plainly
   * belongs to them. The burst grows down the board — a $500 is a bigger find
   * than a $100. The Daily Double happens once, and gets the whole screen.
   */
  function celebrateAnswer(team, pop, daily, row) {
    const colors = [team.color, '#E8B461', '#FDF8F1'];
    if (daily) {
      fx.play('fanfare');
      fx.confetti({ colors });
      return;
    }
    fx.play('correct');
    const rect = pop.getBoundingClientRect();
    fx.confetti({ colors, at: { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 }, count: 40 + row * 18 });
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

  /* A ring that drains, with the seconds in the middle.
   *
   * The ring is always heading for where it will be one second from now, and
   * CSS eases it there over that second, so it drains smoothly instead of in
   * jerks. A number counting down is read; a ring running out is seen, by the
   * students with their heads down over the answer sheet too.
   */
  const RING = 2 * Math.PI * 45;

  function startTimer(node, seconds) {
    stopTimer();
    let left = seconds;
    const svgNS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('viewBox', '0 0 100 100');
    svg.classList.add('timer-ring');
    const track = document.createElementNS(svgNS, 'circle');
    const bar = document.createElementNS(svgNS, 'circle');
    [track, bar].forEach(c => {
      c.setAttribute('cx', '50');
      c.setAttribute('cy', '50');
      c.setAttribute('r', '45');
    });
    track.classList.add('track');
    bar.classList.add('bar');
    bar.style.strokeDasharray = String(RING);
    bar.style.strokeDashoffset = '0';
    svg.append(track, bar);
    const num = el('span', 'timer-num', String(left));
    node.innerHTML = '';
    node.classList.remove('done', 'urgent');
    node.classList.add('running');
    node.append(svg, num);

    const aim = () => { bar.style.strokeDashoffset = String(RING * (1 - Math.max(left - 1, 0) / seconds)); };
    // One frame late, so the transition has a starting value to ease from.
    requestAnimationFrame(aim);

    timer = setInterval(() => {
      left -= 1;
      num.textContent = String(Math.max(left, 0));
      if (left <= 0) {
        node.classList.remove('running', 'urgent');
        node.classList.add('done');
        node.textContent = '¡Tiempo!';
        fx.play('buzzer');
        stopTimer();
        return;
      }
      if (left <= URGENT_SECONDS) {
        node.classList.add('urgent');
        fx.play('tick');
      }
      aim();
    }, 1000);
  }

  function stopTimer() {
    if (timer) clearInterval(timer);
    timer = null;
  }

  window.Jeopardy = { mount };
})();
