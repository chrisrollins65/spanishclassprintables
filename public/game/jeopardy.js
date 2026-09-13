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

  /* One colour per team, and so the team limit: setup stops adding rows when
   * these run out. Eight, for a class split into pairs or threes.
   *
   * The logo's pink, teal and gold lead, so the common case — two or three
   * teams — is the brand's own colours; the rest fill the hue gaps those leave.
   * The order matters as much as the list: adjacent entries land side by side on
   * the score strip, so no two neighbours may read as the same team from the
   * back of the room. None of them may stray into --bad's orange-red either,
   * which on this site means a wrong answer.
   */
  const TEAM_COLORS = ['#E71F69', '#00A89F', '#F8B31A', '#5B8DD9', '#BE0087', '#63BE5A', '#8C6FD9', '#C9713A'];
  /* How long the class gets to write, offered as a choice rather than fixed.
   *
   * The answer is one word on a line of a sheet, and 45 seconds of silence over
   * a one-word answer is a class that finished writing and started talking. 30
   * is the ordinary case; 45 is for the listening clues, where the time has to
   * cover hearing the clue again as well as writing it — so that is the one the
   * screen starts a listening clue on.
   */
  const SECONDS = [30, 45];
  const DEFAULT_SECONDS = 30;
  const AUDIO_SECONDS = 45;

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

  const { el, englishToggle, canSpeakSpanish, speak, openVocab, reviewButton, howToButton,
    brandMark, moreGames, afterGame, DEFAULT_RATE, normalizeRate, rateRow } = window.RoomUI;
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
    if (state) {
      const snapped = normalizeRate(state.rate);
      if (snapped !== state.rate) {
        state.rate = snapped;
        save();
      }
    }
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
      brandMark(),
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
      state = { teams, turn: 0, used: [], daily: pickDailyCell(), rate: DEFAULT_RATE };
      save();
      fx.play('start');
      renderBoard({ intro: true });
    };

    wrap.append(addBtn, el('div', 'award-row', null, [
      howToButton(root, () => howToSteps(null)), reviewButton(root, bankItems()), start,
    ]), moreGames());
    root.append(wrap);
  }

  /* The rules as this room plays them (see openHowTo in ui.js). `playing` is
   * the game in progress, or null on the setup screen, where there is none yet
   * and the Daily Double is only a question of whether the board has room for
   * one.
   *
   * The listening clues are left out on purpose: the clue screen says so itself
   * the moment one comes up, and a rule for it here is one more line to read
   * before anyone has played.
   */
  function howToSteps(playing) {
    const daily = playing ? playing.daily : pickDailyCell();
    return [
      {
        icon: '👥',
        // No word on how many sheets: one per team or one per student is the
        // teacher's call (the printed How to Play page offers both).
        es: `Formen equipos: de 2 a ${TEAM_COLORS.length}.`,
        en: `Make teams, 2 to ${TEAM_COLORS.length} of them.`,
      },
      {
        icon: '🎯',
        es: 'El equipo al que le toca elige una categoría y un valor.',
        en: 'The team whose turn it is picks a category and a value.',
      },
      {
        icon: '✏️',
        es: 'Todos los equipos escriben la respuesta en su hoja, no solo el equipo que eligió.',
        en: 'Every team writes the answer on its sheet, not just the team that picked.',
      },
      // Not something the site can enforce — the answers are on paper — but
      // without it the teams reading out later just copy whatever the first
      // team said.
      {
        icon: '✋',
        es: 'Cuando todos terminan de escribir, ¡lápices abajo! Ya nadie puede cambiar su respuesta.',
        en: 'When everyone has finished writing, pencils down! Nobody can change their answer after that.',
      },
      {
        icon: '🗣️',
        es: 'Primero lee el equipo que eligió. Si falla, lee el siguiente. ¡El primero que acierta gana los puntos!',
        en: 'The team that picked reads its answer first. If it is wrong, the next team reads theirs. The first team to get it right wins the points!',
      },
      daily && {
        icon: '💰',
        es: 'Una casilla secreta es el Doble Diario: ¡vale el doble!',
        en: 'One secret square is the Daily Double: it is worth double!',
      },
      // "Nobody loses points" is for the teacher who knows the TV show, where a
      // wrong answer costs its value. Nothing subtracts on the board here — the
      // final wager is the one place anything does, and it says so itself.
      {
        icon: '🔄',
        es: finalClue()
          ? 'Después de cada pista, le toca al siguiente equipo. En el tablero nadie pierde puntos.'
          : 'Después de cada pista, le toca al siguiente equipo. Nadie pierde puntos.',
        en: finalClue()
          ? 'After every clue it is the next team\'s turn, whoever got it right. Nobody loses points on the board.'
          : 'After every clue it is the next team\'s turn, whoever got it right. Nobody ever loses points.',
      },
      /* The whole round in one line, because that is all it needs: the screen
       * walks the class through it a step at a time when it happens, and this
       * panel is read before anyone has played. What it has to say here is
       * that the game does not end when the board does. */
      finalClue() && {
        icon: '💸',
        es: 'Al final, cada equipo apuesta parte de su dinero en una última pista. ¡Si acierta, lo gana; si falla, lo pierde!',
        en: 'At the end, every team bets some of its money on one last clue. Right: it wins the bet. Wrong: it loses it.',
      },
      {
        icon: '🏆',
        es: finalClue()
          ? 'Después de la apuesta final, gana el equipo con más dinero.'
          : 'Cuando no quedan casillas, gana el equipo con más dinero.',
        en: finalClue()
          ? 'After the final wager, the team with the most money wins.'
          : 'When the board is empty, the team with the most money wins.',
      },
    ].filter(Boolean);
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
    controls.append(fx.muteButton(), howToButton(root, () => howToSteps(state), { compact: true }),
      scoreButton(), vocabButton(), resetButton());

    // A finished game's board is all empty squares, so the standings take its
    // place — and whose turn it is no longer means anything.
    if (state.used.length === countClues()) {
      /* Except that the game is not over yet when there is a final wager to
       * play. It takes over the screen entirely: the board it would sit on is
       * empty, and the standings behind it would be answering the question the
       * round is about to ask. */
      if (finalClue() && wagerState().stage !== 'done') return renderWager();
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

  /* ---------- la apuesta final ---------- */

  /* The last clue of the game, which every team bets on.
   *
   * The Daily Double deliberately has no wager (see pickDailyCell), for
   * reasons that all stop applying here. Mid-game a wager is arithmetic the
   * class has to stop for and an argument the teacher has to settle, and a
   * team that loses its score with squares still on the board gives up for the
   * rest of the lesson. This round happens once, after the board is empty:
   * every team writes its bet at the same time on the sheet it has had all
   * game, the screen does the sums, and there is nothing left to give up on.
   *
   * What it buys is the half of the lesson the old ending threw away. Without
   * it the game is decided around the fifteenth square and the last ten
   * minutes are a formality for everyone but the leader; with it the team in
   * last place goes into the final clue able to win.
   */

  // The biggest square on the board, and so the smallest bet a team is always
  // allowed. src/gamePack.js in the builder works the same number out the same
  // way for the printed directions — a floor the paper promises and the screen
  // refuses would be worse than no floor at all.
  function topValue() {
    return Math.max(0, ...game.categories.flatMap(c => c.clues.map(q => Number(q.value) || 0)));
  }

  /* What a team may bet: everything it has, or the top square if that is more.
   *
   * The floor is the whole point. A team on nothing that can only bet nothing
   * has been told to watch the last question, and that is exactly the team the
   * round exists for. It costs the leader nothing — they can always bet more.
   */
  function wagerCap(team) {
    return Math.max(team.score, topValue());
  }

  // A pack written before the final round existed simply has none, and the
  // game ends on the board the way it always did.
  function finalClue() {
    return game.final && game.final.prompt ? game.final : null;
  }

  function wagerState() {
    if (!state.wager) {
      state.wager = {
        stage: 'intro',
        // null, not 0: "nothing entered yet" is what holds the screen on the
        // betting stage, and a prefilled 0 is a bet nobody chose.
        bets: state.teams.map(() => null),
        right: state.teams.map(() => null),
        before: null,
      };
    }
    return state.wager;
  }

  /* Past four teams a single column of rows is taller than the screen, and the
   * teacher ends up scrolling a list mid-game to find the team that just
   * answered. Two columns halve it; below about 26rem of width each they fold
   * back to one, which is a phone, where scrolling is the normal way to read.
   */
  function rowsClass(base) {
    return base + (state.teams.length > 4 ? ' two-up' : '');
  }

  function wagerScreen(stageClass) {
    root.innerHTML = '';
    const screen = el('section', 'board-screen wager-screen ' + stageClass);
    const controls = el('div', 'award-row');
    controls.append(fx.muteButton(), howToButton(root, () => howToSteps(state), { compact: true }),
      scoreButton(), vocabButton(), resetButton());
    screen.append(window.RoomUI.topBar(el('div'), game.title || room.theme, controls));
    return screen;
  }

  function renderWager() {
    stopTimer();
    const w = wagerState();
    if (w.stage === 'bets') return renderWagerBets();
    if (w.stage === 'clue') return renderWagerClue();
    if (w.stage === 'scored') return renderWagerResult();
    return renderWagerIntro();
  }

  /* The category, and nothing else.
   *
   * This screen is the whole of the bet: a team decides what to stake knowing
   * only what the clue is about. So the category gets the screen to itself,
   * and the clue is not merely hidden here — as far as the room is concerned
   * it does not exist yet.
   */
  /* What a bet is worth, in the two words that decide it.
   *
   * The round asks a class of ten-year-olds to stake their game on a sentence,
   * so the terms have to be ON the screen they are staring at while they
   * decide — not only in the teacher's line on the clue screen, which arrives
   * after the bets are locked and is addressed to the wrong person anyway.
   *
   * Green and red, ✓ and ✗, because that is exactly what the teacher taps at
   * the end: a child who read this screen already knows what the two buttons
   * on the last one mean.
   */
  function wagerStakes() {
    return el('div', 'wager-stakes', null, [
      el('span', 'stake win', '✓ Si aciertan, ganan su apuesta'),
      el('span', 'stake lose', '✗ Si fallan, la pierden'),
    ]);
  }

  function renderWagerIntro() {
    const screen = wagerScreen('stage-intro');
    const stage = el('div', 'wager-stage');
    stage.append(
      el('div', 'wager-burst'),
      el('h2', 'wager-title', '¡LA APUESTA FINAL!'),
      el('p', 'wager-label', 'La categoría es'),
      el('div', 'wager-cat', finalClue().category || ''),
      wagerStakes(),
      el('p', 'wager-hint', 'Cada equipo apunta su apuesta en su hoja. Todavía no se ve la pista.')
    );

    const go = el('button', 'primary big', '¿Cuánto apuestan? →');
    go.onclick = () => { wagerState().stage = 'bets'; save(); renderWager(); };

    // On its own row underneath, and quiet about it: a period that has run out
    // of time needs a way to the podium, but skipping is not the ordinary path.
    const skip = el('button', 'ghost small', 'Saltar la apuesta');
    skip.onclick = () => finishWager();

    screen.append(stage, el('div', 'clue-controls', null, [
      el('div', 'award-row', null, [go]),
      el('div', 'award-row', null, [skip]),
    ]));
    root.append(screen);
    fx.play('daily');
    fx.confetti({ colors: ['#F8B31A', '#FDF8F1', '#E71F69', '#00A89F'], count: 90 });
  }

  /* Every team's bet, typed in before the clue exists.
   *
   * The teacher types them rather than each team pressing something, because
   * there are no student devices in this game and never have been — the bets
   * are already written on paper, and this is the teacher reading them across.
   * Nothing can be entered that a team cannot afford, so a bet never has to be
   * taken back after the clue.
   */
  function renderWagerBets() {
    const w = wagerState();
    const screen = wagerScreen('stage-bets');

    const stage = el('div', 'wager-stage');
    stage.append(
      el('h2', 'wager-heading', '¿Cuánto apuestan?'),
      el('p', 'wager-label', finalClue().category || ''),
      // Again here, and not only on the screen before: this is the one the room
      // sits looking at while the teacher types eight numbers, and it is the
      // last moment a team can still change its mind.
      wagerStakes()
    );

    const rows = el('div', rowsClass('wager-rows'));
    const go = el('button', 'primary big', 'Ver la pista →');

    const ready = () => {
      go.disabled = w.bets.some(b => b === null || b === '' || Number.isNaN(Number(b)));
    };

    state.teams.forEach((team, i) => {
      const cap = wagerCap(team);
      const row = el('div', 'wager-row');
      row.style.borderColor = team.color;

      const who = el('div', 'wager-who');
      who.append(teamBadge(team), el('span', 'wager-name', team.name),
        el('span', 'wager-have', money(team.score)));

      const input = document.createElement('input');
      input.type = 'number';
      input.min = '0';
      input.max = String(cap);
      input.className = 'wager-input';
      input.placeholder = '0';
      // Re-clamped on the way in, not only on the way out: a score corrected
      // through the Puntos panel can lower a cap under a bet already typed
      // against the old one.
      if (w.bets[i] !== null) {
        if (w.bets[i] > cap) { w.bets[i] = cap; save(); }
        input.value = String(w.bets[i]);
      }

      const commit = () => {
        const raw = input.value.trim();
        if (raw === '') { w.bets[i] = null; save(); return ready(); }
        // Clamped rather than refused: a teacher mistyping 5000 for a team
        // holding 500 should see the 500, not an error to read and undo.
        const bet = Math.max(0, Math.min(cap, Math.round(Number(raw) || 0)));
        w.bets[i] = bet;
        if (String(bet) !== raw) input.value = String(bet);
        save();
        return ready();
      };
      input.oninput = commit;

      const all = el('button', 'small', 'Todo');
      all.onclick = () => { input.value = String(cap); commit(); };

      // money('') is the currency symbol on its own — the packet may carry a
      // peso sign, and a bare number box beside "$2400" would be the one
      // amount on the screen with no unit on it.
      row.append(who, el('div', 'wager-enter', null, [
        el('span', 'wager-currency', money('')), input, all,
      ]));
      /* The cap, but only where it is news.
       *
       * For most teams it is their own score, already sitting two inches to
       * the left, and printing it twice cost the room the team's NAME — eight
       * rows of "L…" and "Las …" at two columns wide. It earns its place only
       * on a team the floor is lifting, which is the one team that needs to
       * know it can bet more than it holds.
       */
      if (cap !== team.score) row.append(el('span', 'wager-max lifted', 'máx. ' + money(cap)));
      rows.append(row);
    });

    stage.append(rows);
    /* Only where it is actually doing something. With every team already ahead
     * of the top square the floor never comes up, and a line explaining a rule
     * nobody is standing on is a line that makes the round sound complicated. */
    if (state.teams.some(t => t.score < topValue())) {
      stage.append(el('p', 'wager-hint',
        `Un equipo con poco dinero puede apostar hasta ${money(topValue())}. Nadie baja de ${money(0)}.`));
    }

    go.onclick = () => { w.stage = 'clue'; save(); renderWager(); };
    ready();

    const back = el('button', 'ghost small', '← La categoría');
    back.onclick = () => { w.stage = 'intro'; save(); renderWager(); };

    screen.append(stage, el('div', 'clue-controls', null, [
      el('div', 'award-row', null, [go]),
      el('div', 'award-row', null, [back]),
    ]));
    root.append(screen);
  }

  /* The clue itself, and then who got it.
   *
   * Shown, never read aloud with the text hidden the way the top rows are: the
   * teams have money on this one and have to be able to read it again, and a
   * listening test is not what the round is for.
   *
   * Each team is marked on its own. This is the only clue in the game where
   * more than one team can be right, because each is playing against its own
   * bet rather than racing the others to the points.
   */
  function renderWagerClue() {
    const w = wagerState();
    const clue = finalClue();
    const screen = wagerScreen('stage-clue');

    const head = el('div', 'clue-head');
    head.append(
      el('div', 'where', null, [
        document.createTextNode(clue.category || ''),
        document.createTextNode(' · '),
        el('span', 'value', 'La Apuesta Final'),
      ]),
      el('div', 'note', 'Todos escriben. Las apuestas ya están hechas.')
    );

    const body = el('div', 'clue-body');
    body.append(el('p', 'prompt', clue.prompt));
    if (clue.promptEn) body.append(englishToggle(clue.promptEn));

    const answer = el('p', 'answer hidden', clue.answer || '');
    const clock = el('div', 'timer');

    const marks = el('div', rowsClass('wager-marks'));
    const go = el('button', 'primary big', 'Ver los resultados →');
    const ready = () => { go.disabled = w.right.some(r => r === null); };

    state.teams.forEach((team, i) => {
      const row = el('div', 'wager-mark');
      row.style.borderColor = team.color;
      row.append(teamBadge(team), el('span', 'wager-name', team.name),
        el('span', 'wager-bet', money(Number(w.bets[i]) || 0)));

      const yes = el('button', 'mark-yes', '✓');
      const no = el('button', 'mark-no', '✗');
      const paint = () => {
        yes.classList.toggle('chosen', w.right[i] === true);
        no.classList.toggle('chosen', w.right[i] === false);
        row.classList.toggle('is-right', w.right[i] === true);
        row.classList.toggle('is-wrong', w.right[i] === false);
      };
      yes.onclick = () => { w.right[i] = true; save(); paint(); ready(); };
      no.onclick = () => { w.right[i] = false; save(); paint(); ready(); };
      paint();

      row.append(el('div', 'award-row', null, [yes, no]));
      marks.append(row);
    });

    /* The same two lengths as a square, with the longer one suggested: this
     * clue is read, thought about and bet on, and every team in the room is
     * answering it rather than one team racing to it. */
    const timerBtns = SECONDS.map(secs => {
      const btn = el('button', 'small' + (secs === AUDIO_SECONDS ? ' suggested' : ''), `⏱ ${secs}s`);
      btn.onclick = () => {
        timerBtns.forEach(b => { b.disabled = true; });
        startTimer(clock, secs);
      };
      return btn;
    });
    const timerGroup = el('div', 'timer-choice', null, timerBtns);
    timerGroup.prepend(el('span', 'rate-label', 'Para escribir'));

    const revealBtn = el('button', 'small', 'Mostrar la respuesta');
    const showAnswer = () => {
      answer.classList.remove('hidden');
      screen.classList.add('answered');
      revealBtn.disabled = true;
    };
    revealBtn.onclick = showAnswer;

    go.onclick = () => { showAnswer(); scoreWager(); };
    ready();

    screen.append(head, clock, body, answer, el('div', 'clue-controls', null, [
      el('p', 'hint', 'Marca ✓ o ✗ para cada equipo. Acierta: gana su apuesta. Falla: la pierde.'),
      marks,
      el('div', 'award-row', null, [go]),
      el('div', 'award-row', null, [timerGroup, vocabButton('Ver vocabulario'), revealBtn]),
    ]));
    root.append(screen);
    fx.play('open');
  }

  /* The sums, done once.
   *
   * `before` is kept rather than recomputed, so the result screen can show the
   * climb and so a refresh in the middle of it cannot add a bet twice: the
   * stage moves before anything is drawn.
   */
  function scoreWager() {
    const w = wagerState();
    w.before = state.teams.map(t => t.score);
    state.teams.forEach((team, i) => {
      const bet = Number(w.bets[i]) || 0;
      /* The floor, and the one place this game ever subtracts anything. A bet
       * is capped at the top square rather than at what a team holds, so
       * without this a team that bet more than it had would finish behind
       * where it started — a number no child should be shown for trying. */
      team.score = w.right[i] ? team.score + bet : Math.max(0, team.score - bet);
    });
    w.stage = 'scored';
    save();
    renderWager();
  }

  function renderWagerResult() {
    const w = wagerState();
    const screen = wagerScreen('stage-result');

    const stage = el('div', 'wager-stage');
    stage.append(
      el('h2', 'wager-heading', 'La respuesta era'),
      el('div', 'wager-cat', finalClue().answer || '')
    );

    const rows = el('div', rowsClass('wager-rows'));
    state.teams.forEach((team, i) => {
      const bet = Number(w.bets[i]) || 0;
      const from = w.before ? w.before[i] : team.score;
      const row = el('div', 'wager-row result' + (w.right[i] ? ' is-right' : ' is-wrong'));
      row.style.borderColor = team.color;

      const swing = el('span', 'wager-swing', (w.right[i] ? '+' : '−') + money(bet));
      const total = el('span', 'wager-total', money(from));

      row.append(teamBadge(team), el('span', 'wager-name', team.name), swing, total);
      rows.append(row);
      // Each total climbs from where it was, staggered down the list, so the
      // room reads one change at a time instead of eight at once.
      setTimeout(() => {
        if (total.isConnected) fx.countUp(total, from, team.score, money);
      }, 250 + i * 260);
    });

    const go = el('button', 'primary big', 'Al podio →');
    go.onclick = () => finishWager();

    stage.append(rows);
    screen.append(stage, el('div', 'clue-controls', null, [el('div', 'award-row', null, [go])]));
    root.append(screen);
    fx.play(state.teams.some((t, i) => w.right[i]) ? 'fanfare' : 'none');
  }

  /* Done with the round, however it ended — played out or skipped.
   *
   * The podium celebrates either way: `celebrateFinish` is normally spent by
   * the board emptying, and the board emptied several screens ago.
   */
  function finishWager() {
    wagerState().stage = 'done';
    save();
    celebrateFinish = true;
    renderBoard();
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

    /* The game is over and the room is still looking at the screen — the one
     * moment in the lesson where asking the teacher for something costs the
     * class nothing. It sits under the standings, never over them. */
    wrap.append(el('div', 'podium-more', null, [afterGame(room)]));
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
    fx.confetti({ colors: state.teams.filter(t => t.score === best).map(t => t.color).concat('#F8B31A', '#FDF8F1') });
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
      /* The quickest way in, and the one a teacher finds without being told:
       * the wrong number is on the screen, so press the wrong number. It opens
       * the same panel the Puntos button does, with this team's box selected.
       */
      box.title = 'Ajustar la puntuación';
      box.onclick = () => openScores(i);
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

  // The word list as a mid-game reminder, from the board and from every clue
  // (see openVocab in ui.js). A room published without its item bank has no
  // list to show, and says so by greying the button rather than hiding it.
  function vocabButton(label) {
    const btn = el('button', 'small', label || 'Vocabulario');
    btn.disabled = !bankItems().length;
    btn.onclick = () => openVocab(root, bankItems(), { moment: 'reminder' });
    return btn;
  }

  function bankItems() {
    return Array.isArray(room.items) ? room.items : [];
  }

  /* ---------- correcting a score ---------- */

  /* The teacher's override, for when the screen and the room disagree.
   *
   * The board awards points by a tap, and the tap is sometimes the wrong team:
   * two teams reading at once, a name misheard, an answer accepted and then
   * argued out of. Until this existed the only way back was Reiniciar, which
   * throws away the period. It also covers the things the board cannot know
   * about at all — a bonus for the team that used the whole sentence, a point
   * docked for shouting over another team.
   *
   * Every team is editable whenever it opens, not only the one that was
   * tapped, because the usual correction is a pair: take it off one team and
   * put it on another.
   */
  function scoreButton() {
    const btn = el('button', 'small', 'Puntos');
    btn.onclick = () => openScores();
    return btn;
  }

  // The step the quick buttons move in: the cheapest square on the board, which
  // is the smallest amount this game ever deals in.
  function scoreStep() {
    const values = game.categories.flatMap(c => c.clues.map(q => Number(q.value) || 0)).filter(Boolean);
    return values.length ? Math.min(...values) : 100;
  }

  function openScores(focus) {
    const screen = el('section', 'clue-screen');
    const head = el('div', 'clue-head');
    head.append(el('div', 'where', 'Ajustar la puntuación'),
      el('div', 'note', '¿Tocaste el equipo equivocado? Corrígelo aquí.'));

    const body = el('div', 'clue-body check');
    const rows = el('div', rowsClass('wager-rows'));
    const step = scoreStep();
    let focused = null;

    state.teams.forEach((team, i) => {
      const row = el('div', 'wager-row');
      row.style.borderColor = team.color;

      const who = el('div', 'wager-who');
      who.append(teamBadge(team), el('span', 'wager-name', team.name));

      const input = document.createElement('input');
      input.type = 'number';
      input.min = '0';
      input.className = 'wager-input';
      input.value = String(team.score);
      if (i === focus) focused = input;

      /* Typed or stepped, one place applies it. Straight onto the team rather
       * than into a draft the panel saves on the way out: there is no Guardar
       * button to forget, and a teacher who closes this with the X has still
       * made the change they watched happen. */
      const set = value => {
        // Floored, like every other score in this game. Nothing here subtracts
        // past zero, so a correction must not be the one thing that does.
        team.score = Math.max(0, Math.round(Number(value) || 0));
        input.value = String(team.score);
        save();
      };
      input.oninput = () => {
        const raw = input.value.trim();
        // Mid-typing an empty box is not a score of zero; leave it until there
        // is a number in it.
        if (raw === '') return;
        team.score = Math.max(0, Math.round(Number(raw) || 0));
        save();
      };
      input.onblur = () => set(input.value);

      const down = el('button', 'small', '−' + money(step));
      down.onclick = () => set(team.score - step);
      const up = el('button', 'small', '+' + money(step));
      up.onclick = () => set(team.score + step);

      row.append(who, el('div', 'wager-enter', null, [
        down, el('span', 'wager-currency', money('')), input, up,
      ]));
      rows.append(row);
    });

    body.append(rows);

    const close = el('button', 'primary', 'Volver');
    // Back through renderBoard rather than by removing this screen: the score
    // strip, the crown and the standings are all drawn from what just changed.
    close.onclick = () => renderBoard();

    screen.append(head, body, el('div', 'clue-controls', null, [close]));
    root.append(screen);
    if (focused) { focused.focus(); focused.select(); }
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
    // Set by a listening clue: what it does when the clue is over (below).
    let onAnswered = null;

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
      again.onclick = () => speakAt(clue.prompt);

      /* Showing the text puts the screen in the other mode rather than adding
       * to this one: the 🔊 and "escuchen" stand in for words nobody can
       * see, so once the words are there they are decoration — and they are
       * the two biggest things on the screen, which on a 720p projector is
       * the difference between the clue fitting and the clue scrolling. */
      const setText = show => {
        hidden.hidden = !show;
        body.classList.toggle('reading', show);
        showText.textContent = show ? 'Ocultar el texto' : 'Ver el texto';
      };

      const showText = el('button', 'small', 'Ver el texto');
      showText.onclick = () => setText(hidden.hidden);

      /* Once the answer is up the clue has been asked and heard, so the text
       * comes out on its own: the class can see what it was listening to
       * against what it should have written. */
      onAnswered = () => setText(true);

      body.append(glyph, note, hidden, el('div', 'award-row', null, [again, showText]),
        // Same control as the bingo caller's, and for the same reason: a class
        // that missed it needs it slower, not louder. The choice is kept on the
        // game so it holds for the rest of the board — a teacher who slowed the
        // $400 down should not have to slow the $500 down again.
        rateRow(state.rate, r => { state.rate = r; save(); }));
      // Unless the clue was closed while the chime played: a clue read out over
      // the board is one nobody can see or answer.
      opened.then(() => { if (screen.isConnected) speakAt(clue.prompt); });
    } else {
      body.append(el('p', 'prompt', clue.prompt));
    }

    // A hint, not a rewrite: the clue keeps the difficulty the team accepted
    // when they picked its value, and the English only helps them read it.
    if (clue.promptEn) body.append(englishToggle(clue.promptEn));

    const controls = el('div', 'clue-controls');

    /* One press starts the clock, so the length is chosen by which button is
     * pressed rather than by setting it and then starting it: the teacher is
     * standing in front of a class, and the clue screen is not the place to
     * make them press twice. A pack may still pin a clue to its own length. */
    const lengths = clue.seconds ? [clue.seconds] : SECONDS;
    const suggested = clue.audio && canSpeakSpanish() ? AUDIO_SECONDS : DEFAULT_SECONDS;
    const timerBtns = lengths.map(secs => {
      const btn = el('button', 'small' + (lengths.length > 1 && secs === suggested ? ' suggested' : ''),
        `⏱ ${secs}s`);
      btn.onclick = () => {
        timerBtns.forEach(b => { b.disabled = true; });
        startTimer(clock, secs);
      };
      return btn;
    });
    const timerGroup = el('div', 'timer-choice', null, timerBtns);
    timerGroup.prepend(el('span', 'rate-label', 'Para escribir'));

    const revealBtn = el('button', 'small', 'Mostrar la respuesta');
    revealBtn.onclick = showAnswer;

    /* Every route to the answer goes through here — the reveal button, and
     * awarding the points — so the answer can never go up without the screen
     * also standing the clue down to leave room for it. */
    function showAnswer() {
      answer.classList.remove('hidden');
      screen.classList.add('answered');
      revealBtn.disabled = true;
      if (onAnswered) onAnswered();
    }

    controls.append(
      el('p', 'hint', 'Todos escriben en su hoja. Empieza el equipo que eligió; si falla, pasa al siguiente.'),
      el('div', 'award-row', null, awardButtons(c, r, value, showAnswer)),
      el('div', 'award-row', null, [timerGroup, vocabButton('Ver vocabulario'), revealBtn])
    );

    /* The answer is its own row between the clue and the controls, never the
     * last line inside the clue body: a centred body lets a long clue spill
     * past its own edges, and what it spilled onto was the award popping up.
     *
     * The clock hangs in the screen's own corner instead of queueing in the
     * body, for two reasons. It was last in that queue, so a long clue pushed
     * it out of sight — and the one thing a countdown has to be is looked at.
     * And starting it used to reflow the clue underneath the students reading
     * it, because an empty div became a 9rem ring. Out of the flow it costs
     * the clue nothing and moves nothing. */
    screen.append(head, clock, body, answer, controls);
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
    fx.confetti({ colors: ['#F8B31A', '#FDF8F1', '#E71F69', '#00A89F'], count: 120 });
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
  function awardButtons(c, r, value, showAnswer) {
    const buttons = [];
    for (let i = 0; i < state.teams.length; i++) {
      const idx = (state.turn + i) % state.teams.length;
      const team = state.teams[idx];
      const btn = el('button', null);
      btn.append(teamBadge(team), document.createTextNode(`${team.name} +${money(value)}`));
      btn.onclick = () => { showAnswer(); resolveClue(c, r, idx); };
      buttons.push(btn);
    }
    const none = el('button', 'ghost', 'Nadie acertó');
    none.onclick = () => { showAnswer(); resolveClue(c, r, null); };
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
    const colors = [team.color, '#F8B31A', '#FDF8F1'];
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

  // Every spoken clue goes through here so the speed the teacher picked on one
  // clue is the speed the next one is read at.
  function speakAt(text) {
    return speak(text, state && state.rate != null ? state.rate : DEFAULT_RATE);
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
