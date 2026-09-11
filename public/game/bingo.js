/* Bingo caller, projected at the front of the room.
 *
 * The cards are NOT generated here — they arrive in the payload exactly as the
 * printed cards were built (src/bingoCards.js), so what the screen thinks is on
 * card #17 is what card #17 actually says. Only the winning-line geometry is
 * computed browser-side, because that is derived from the grid size rather than
 * being data anyone printed.
 *
 * The teacher never says the Spanish word. Every clue mode asks for it
 * sideways — the English, a sentence with the word missing, a Spanish
 * definition, or the definition read aloud with nothing on screen — so finding
 * the square is comprehension rather than shape-matching. That is the same
 * trick the traditional lotería caller uses when they riddle a card instead of
 * naming it.
 */
(function () {
  'use strict';

  const { el, englishToggle, canSpeakSpanish, speakable } = window.RoomUI;
  const fx = window.RoomFX;

  // The drawn ball's colour steps through these, the way a real bingo drum's
  // balls are coloured by range: consecutive calls never look alike, so the
  // screen visibly changes even when two clues read much the same.
  const BALL_COLORS = ['#B4744A', '#5F8FB4', '#6FA96F', '#C4954A', '#9B6FA9', '#B45F6F', '#3FA7A2'];

  /* What a clue can be.
   *
   * The registry lives here rather than in the payload so labels and the
   * difficulty ordering stay editable in one place, and a room published years
   * ago keeps working as long as ids are never removed. A deck declares which
   * ids it carries; anything it does not carry simply never appears on screen.
   *
   * `lang` matters for one reason: a listening clue has to be spoken in
   * Spanish. Reading an English clue aloud with a Spanish voice teaches nothing.
   */
  const CLUE_TYPES = {
    prompt: {
      rank: 0, lang: 'es', short: 'Fórmula', label: 'Fórmula',
      hint: 'Lee la fórmula, por ejemplo «yo + hablar».',
    },
    en: {
      rank: 1, lang: 'en', short: 'Inglés', label: 'En inglés',
      hint: 'Lee la pista en inglés en voz alta.',
    },
    sentence: {
      rank: 2, lang: 'es', short: 'Frase', label: 'Frase con hueco',
      hint: 'Lee la frase; falta una palabra.',
    },
    definition: {
      rank: 3, lang: 'es', short: 'Definición', label: 'Definición en español',
      hint: 'Lee la definición en español.',
    },
  };

  /* The English of a clue, where one exists.
   *
   * It is a scaffold, not the answer: an English definition still leaves the
   * class to produce the Spanish word, which is the same work the "Inglés" clue
   * mode asks for openly. Only clue types that describe rather than name have a
   * counterpart here — there is nothing to reveal about an English word.
   */
  const CLUE_ENGLISH = {
    sentence: 'sentenceEn',
    definition: 'definitionEn',
  };

  const AUDIO_MODE = {
    key: 'audio', rank: 8, short: 'Escuchar', label: 'Solo escuchar',
    hint: 'La pantalla lo dice en voz alta. No se muestra nada.',
  };
  const MIXED_MODE = {
    key: 'mixed', rank: 9, short: 'Mezcla', label: 'Mezcla',
    hint: 'Cambia de tipo de pista en cada llamada.',
  };

  /* Slowest first, matching the direction of the clue dial below.
   *
   * There is no faster-than-normal option because there is no teacher who needs
   * a listening clue sped up. The slow end stops at half speed on purpose:
   * browser speech stretches the phonemes instead of adding pauses between
   * words, so below about 0.5 it slurs into a drone that is harder to follow
   * than normal speed — slower stops helping well before it stops being possible.
   */
  const RATES = [
    { key: 'slowest', label: 'Muy lento', value: 0.5 },
    { key: 'slow', label: 'Lento', value: 0.75 },
    { key: 'normal', label: 'Normal', value: 1 },
  ];
  const DEFAULT_RATE = 0.75;

  /* The article is a separate field on an item, so it can be left off a gap
   * sentence and off words that never take one. The CARD, though, prints the
   * article — gender is half of what a bingo card teaches — so anything that
   * has to match a card cell has to put it back the same way the printer did.
   */
  function displayFace(item) {
    if (!item) return '';
    return [item.article, item.face].filter(Boolean).join(' ').trim();
  }

  let root, room, game, state;
  // Set once the teacher touches the setup form, so the late-arriving voice
  // list can't redraw the screen out from under a choice they just made.
  let setupTouched = false;

  function storageKey() {
    return `scp-bingo-${room.code}`;
  }

  function save() {
    try { localStorage.setItem(storageKey(), JSON.stringify(state)); } catch {}
  }

  function load() {
    try {
      const raw = localStorage.getItem(storageKey());
      return raw ? JSON.parse(raw) : null;
    } catch { return null; }
  }

  function clearSaved() {
    try { localStorage.removeItem(storageKey()); } catch {}
  }

  /* ---------- entry ---------- */

  function mount(container, payload) {
    root = container;
    room = payload;
    game = payload.games.bingo;
    // The item bank sits on the room so a quiz-only product can carry it too;
    // older payloads still keep it inside the bingo game.
    if (!game.items && payload.items) game = { ...game, items: payload.items };
    if (!game.clueTypes && payload.clueTypes) game = { ...game, clueTypes: payload.clueTypes };
    primeVoices();
    state = load();
    if (state) {
      const snapped = normalizeRate(state.rate);
      if (snapped !== state.rate) {
        state.rate = snapped;
        save();
      }
    }
    if (state && state.calls) renderCaller();
    else renderSetup();
  }

  function title() {
    return game.title || room.theme;
  }

  /* Which clue ids this deck carries.
   *
   * A declared id must be present on EVERY item — the caller offers a clue type
   * for the whole game, so a half-populated field would silently swap to a
   * different kind of clue partway through. src/bingoCards.js refuses to build
   * such a deck; this is the matching belt on the browser side, and it is also
   * what lets a payload published before clueTypes existed still work.
   */
  function clueTypeIds() {
    const declared = Array.isArray(game.clueTypes) ? game.clueTypes : Object.keys(CLUE_TYPES);
    return declared.filter(id => CLUE_TYPES[id] && game.items.every(item => item[id]));
  }

  // The listening clue speaks the hardest Spanish clue the deck has: the
  // difficulty should come from the listening, not from thin content.
  function audioSourceId() {
    const spanish = clueTypeIds().filter(id => CLUE_TYPES[id].lang === 'es');
    if (game.audioClue && spanish.includes(game.audioClue)) return game.audioClue;
    return spanish.sort((a, b) => CLUE_TYPES[b].rank - CLUE_TYPES[a].rank)[0] || null;
  }

  function availableModes() {
    const modes = clueTypeIds().map(id => Object.assign({ key: id }, CLUE_TYPES[id]));
    if (canSpeakSpanish() && audioSourceId()) modes.push(AUDIO_MODE);
    // Nothing to mix when there is only one way to give a clue.
    if (modes.length > 1) modes.push(MIXED_MODE);
    return modes.sort((a, b) => a.rank - b.rank);
  }

  function modeFor(key) {
    if (key === 'audio') return AUDIO_MODE;
    if (key === 'mixed') return MIXED_MODE;
    return CLUE_TYPES[key] ? Object.assign({ key }, CLUE_TYPES[key]) : null;
  }

  /* ---------- setup ---------- */

  function renderSetup() {
    setupTouched = false;
    root.innerHTML = '';
    const wrap = el('section', 'centered setup');
    wrap.append(el('h1', null, title()), el('p', 'muted', '¿Cómo quieres cantar las palabras?'));

    let mode = 'en';
    let pattern = 'line';

    const modes = availableModes();
    if (!modes.some(m => m.key === mode)) mode = modes[0].key;

    // Everything is in the mix until the teacher takes something out, so the
    // quick path stays one tap on Mezcla.
    const mixable = modes.filter(m => m.key !== 'mixed');
    const mix = new Set(mixable.map(m => m.key));
    const mixBox = mixPicker(mixable, mix);

    const hint = el('p', 'hint');
    const modeBox = el('div', 'choice-list');
    const modeButtons = modes.map(m => {
      const btn = el('button', 'choice', m.label);
      btn.onclick = () => {
        setupTouched = true;
        mode = m.key;
        modeButtons.forEach(b => b.classList.remove('chosen'));
        btn.classList.add('chosen');
        hint.textContent = m.hint;
        // Progressive disclosure: the mix only exists as a concept once the
        // teacher has asked for a mix. Choosing a single clue type never has to
        // think about it.
        mixBox.hidden = m.key !== 'mixed';
      };
      modeBox.append(btn);
      return btn;
    });
    modeButtons[modes.findIndex(m => m.key === mode)].classList.add('chosen');
    hint.textContent = modes.find(m => m.key === mode).hint;
    mixBox.hidden = mode !== 'mixed';

    const patternBox = el('div', 'choice-list');
    const patterns = [
      { key: 'line', label: 'Línea', note: 'fila, columna o diagonal', lit: (r, c) => r === 1 },
      { key: 'blackout', label: 'Cartón lleno', note: 'todas las casillas', lit: () => true },
    ];
    const patternButtons = patterns.map(p => {
      // A drawing of the winning shape rather than a word for it: which squares
      // count is the entire difference between the two, and a four-by-four
      // diagram says it without a sentence or a legend.
      const btn = el('button', 'choice pattern-choice');
      btn.append(miniGrid(p.lit), el('span', 'pattern-name', p.label), el('span', 'pattern-note', p.note));
      btn.onclick = () => {
        setupTouched = true;
        pattern = p.key;
        patternButtons.forEach(b => b.classList.remove('chosen'));
        btn.classList.add('chosen');
      };
      patternBox.append(btn);
      return btn;
    });
    patternButtons[0].classList.add('chosen');

    const start = el('button', 'primary', '¡Empezar!');
    start.onclick = () => {
      state = { mode, pattern, rate: DEFAULT_RATE, mix: [...mix], calls: [], order: shuffledOrder() };
      save();
      fx.play('start');
      renderCaller();
    };

    wrap.append(el('h2', null, 'Pistas'), modeBox, hint, mixBox,
      el('h2', null, 'Para ganar'), patternBox, start);
    root.append(wrap);
  }

  /* Which clue types the mix draws from.
   *
   * The two subsets teachers actually ask for are "everything but listening",
   * because audio is a step up in difficulty from the reading clues, and
   * "no English", because a teacher running the room in Spanish only cannot
   * otherwise have variety at all — Mezcla would force English on them.
   */
  function mixPicker(mixable, mix) {
    const box = el('div', 'mix-box');
    box.append(el('p', 'rate-label', 'En la mezcla'));

    const list = el('div', 'choice-list');
    const buttons = mixable.map(m => {
      const btn = el('button', 'choice small chosen', m.short);
      btn.onclick = () => {
        setupTouched = true;
        // Never let it empty out: removing the last one would leave the caller
        // with nothing to draw, so the final selection is simply sticky.
        if (mix.has(m.key) && mix.size === 1) return;
        if (mix.has(m.key)) mix.delete(m.key);
        else mix.add(m.key);
        btn.classList.toggle('chosen', mix.has(m.key));
      };
      list.append(btn);
      return btn;
    });

    box.append(list, el('p', 'hint', 'Toca para quitar un tipo de pista de la mezcla.'));
    return box;
  }

  function miniGrid(lit) {
    const grid = el('div', 'mini-grid');
    for (let r = 0; r < 4; r++) {
      for (let c = 0; c < 4; c++) grid.append(el('span', 'mini-cell' + (lit(r, c) ? ' on' : '')));
    }
    return grid;
  }

  // Fixed up front rather than drawn one at a time: the call order is part of
  // the saved game, so a refresh mid-round resumes the same sequence instead of
  // re-rolling words that were already crossed off on thirty cards.
  function shuffledOrder() {
    const idx = game.items.map((_, i) => i);
    for (let i = idx.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [idx[i], idx[j]] = [idx[j], idx[i]];
    }
    return idx;
  }

  /* ---------- caller ---------- */

  // `fresh` only for a word that was drawn just now. Every other redraw — a
  // clue-type switch, coming back from a panel — shows the same call again, and
  // rolling the ball in a second time would read as a new word.
  function renderCaller(fresh = false) {
    root.innerHTML = '';
    const screen = el('section', 'board-screen');

    const progress = el('div', 'turn-pill');
    progress.append(
      el('span', 'label', 'Llamadas'),
      el('span', 'name', `${state.calls.length} / ${game.items.length}`)
    );

    const controls = el('div', 'award-row');
    controls.append(fx.muteButton(), calledButton(), checkButton(), resetButton());

    screen.append(window.RoomUI.topBar(progress, title(), controls));

    const stage = el('div', 'call-stage');
    stage.append(callView(fresh === true));
    screen.append(stage);

    screen.append(callerControls());
    root.append(screen);
  }

  function currentCall() {
    return state.calls.length ? state.calls[state.calls.length - 1] : null;
  }

  function callView(fresh) {
    const call = currentCall();
    const view = el('div', 'call-view');

    if (!call) {
      view.append(el('p', 'call-idle', 'Toca «Siguiente palabra» para empezar.'));
      return view;
    }

    const item = game.items[call.i];
    const mode = modeFor(call.mode);

    /* The ball carries the call's number, not its word: the number is already
     * on the counter up top, so it gives nothing away. A fresh one rolls in
     * while the drum rattles, and the clue rises only once it has landed. */
    const n = state.calls.length;
    const ball = el('div', 'bingo-ball', null, [el('span', null, String(n))]);
    ball.style.setProperty('--ball', BALL_COLORS[(n - 1) % BALL_COLORS.length]);
    view.append(ball);
    if (fresh) view.classList.add('fresh');
    const drawn = fresh ? fx.play('draw') : Promise.resolve();

    if (call.mode === 'audio') {
      const written = writtenClue(item);
      const spoken = spokenClue(item);
      view.classList.add('listening');
      view.append(el('div', 'call-audio', '🔊'), el('p', 'call-note', 'Escucha con atención'));

      const shown = el('p', 'call-text audio-text');
      shown.hidden = true;
      // The blank stays a blank on screen; only the speech engine gets the
      // comma, and showing it that way would look like a typo.
      shown.textContent = written;

      const again = el('button', 'small', 'Repetir');
      again.onclick = () => speakAt(spoken);

      // The escape hatch, and a teaching move rather than a giveaway: show the
      // text, play it again while they read along, then hide it and play it
      // once more unsupported. That only works if it goes away again.
      const showText = el('button', 'small', 'Ver el texto');
      showText.onclick = () => {
        shown.hidden = !shown.hidden;
        showText.textContent = shown.hidden ? 'Ver el texto' : 'Ocultar texto';
      };

      const controls = [again, showText];
      view.append(shown, el('div', 'award-row', null, controls), rateRow());
      const spokenEnglish = CLUE_ENGLISH[audioSourceId()];
      if (spokenEnglish && item[spokenEnglish]) {
        view.append(englishToggle(item[spokenEnglish]));
      }
      // After the rattle, never over it (see fx.js) — and not at all if the
      // teacher has already drawn past this word while it played.
      drawn.then(() => { if (view.isConnected) speakAt(spoken); });
      return view;
    }

    view.append(el('p', 'call-label', mode ? mode.label : ''),
      el('p', 'call-text', item[call.mode] || ''));

    // A Spanish clue can be read aloud and still lose the room. The English
    // toggles both ways on purpose: show it, read the clue again, hide it and
    // try once unaided.
    const englishField = CLUE_ENGLISH[call.mode];
    if (englishField && item[englishField]) {
      view.append(englishToggle(item[englishField]));
    }
    return view;
  }

  /* Restyled in place rather than re-rendered.
   *
   * A full redraw here would replay the clue the moment the speed changed and
   * quietly re-hide any text the teacher had just revealed. Neither is what
   * "make it slower" asks for: the new speed belongs to the next Repetir, which
   * the teacher presses when they are ready.
   */
  function rateRow() {
    const row = el('div', 'rate-row');
    row.append(el('span', 'rate-label', 'Velocidad'));
    const buttons = RATES.map(r => {
      const btn = el('button', 'choice small' + (nearly(state.rate, r.value) ? ' chosen' : ''), r.label);
      btn.onclick = () => {
        state.rate = r.value;
        save();
        buttons.forEach(b => b.classList.remove('chosen'));
        btn.classList.add('chosen');
      };
      row.append(btn);
      return btn;
    });
    return row;
  }

  function nearly(a, b) {
    return Math.abs((a == null ? DEFAULT_RATE : a) - b) < 0.01;
  }

  function normalizeRate(value) {
    if (value == null) return DEFAULT_RATE;
    return RATES.reduce(
      (best, r) => (Math.abs(r.value - value) < Math.abs(best - value) ? r.value : best),
      RATES[0].value
    );
  }

  function callerControls() {
    const bar = el('div', 'clue-controls');
    const call = currentCall();

    const next = el('button', 'primary big', state.calls.length ? 'Siguiente palabra →' : 'Empezar a cantar →');
    next.disabled = state.calls.length >= game.items.length;
    next.onclick = drawNext;

    const row = el('div', 'award-row');
    row.append(next);

    if (call) {
      // For the teacher only — the Spanish is what the class is supposed to be
      // working out, so it stays hidden until someone asks.
      const reveal = el('button', 'small', 'Ver la palabra');
      reveal.onclick = () => {
        reveal.replaceWith(el('span', 'revealed', displayFace(game.items[call.i])));
      };
      row.append(reveal);
    }

    bar.append(row);
    if (call) bar.append(modeSwitch());
    if (state.calls.length >= game.items.length) {
      bar.append(el('p', 'hint', 'Se acabaron las palabras.'));
    }
    return bar;
  }

  /* Re-pitch the game without restarting it.
   *
   * Changing the clue type re-renders the CURRENT call, not only the ones after
   * it: a teacher reaches for this because the room is stuck on the clue that
   * is on screen right now, and making them burn that word to get relief would
   * defeat the point of having it.
   */
  function modeSwitch() {
    const row = el('div', 'mode-switch');
    row.append(el('span', 'rate-label', 'Pista'));

    const modes = availableModes().slice().sort((a, b) => a.rank - b.rank);
    modes.forEach(m => {
      const btn = el('button', 'choice small' + (state.mode === m.key ? ' chosen' : ''), m.short);
      btn.onclick = () => {
        state.mode = m.key;
        const current = currentCall();
        if (current) current.mode = m.key === 'mixed' ? pickMode() : m.key;
        save();
        renderCaller();
      };
      row.append(btn);
    });

    row.append(el('span', 'rate-label soft', 'más fácil → más difícil'));
    return row;
  }

  function drawNext() {
    const next = state.order[state.calls.length];
    if (next == null) return;
    state.calls.push({ i: next, mode: pickMode() });
    save();
    renderCaller(true);
  }

  function pickMode() {
    if (state.mode !== 'mixed') return state.mode;
    const usable = availableModes().filter(m => m.key !== 'mixed');
    // A game saved before the mix existed, or one whose whole mix has become
    // unavailable (no Spanish voice for an audio-only mix), falls back to
    // everything rather than to nothing.
    const chosen = usable.filter(m => !state.mix || state.mix.includes(m.key));
    const pool = chosen.length ? chosen : usable;
    return pool[Math.floor(Math.random() * pool.length)].key;
  }

  /* ---------- verification ---------- */

  // The reason this exists: checking a card by hand while twenty-nine kids wait
  // is the part of classroom bingo teachers actually dread.
  /* Deliberately behind a button rather than along the edge of the board.
   *
   * A permanent list of called words is a permanent answer key: every clue mode
   * asks students to work out which Spanish word was meant, and a strip of them
   * on screen hands that to exactly the students who did not follow the clue.
   * On demand it settles disputes; on screen it removes the exercise.
   */
  function calledButton() {
    const btn = el('button', 'small', 'Cantadas');
    btn.onclick = openCalled;
    return btn;
  }

  function openCalled() {
    const screen = el('section', 'clue-screen');
    const head = el('div', 'clue-head');
    head.append(el('div', 'where', 'Palabras cantadas'),
      el('div', 'note', state.calls.length + ' de ' + game.items.length));

    const body = el('div', 'clue-body check called');
    if (!state.calls.length) {
      body.append(el('p', 'muted', 'Todavía no se ha cantado ninguna palabra.'));
    } else {
      const list = el('ol', 'called-list');
      state.calls.forEach((c, i) => {
        const last = i === state.calls.length - 1;
        list.append(el('li', 'called-item' + (last ? ' latest' : ''), displayFace(game.items[c.i])));
      });
      body.append(list);
    }

    const close = el('button', 'ghost', 'Volver');
    close.onclick = renderCaller;

    screen.append(head, body, el('div', 'clue-controls', null, [close]));
    root.append(screen);
  }

  function checkButton() {
    const btn = el('button', 'small', 'Comprobar cartón');
    btn.onclick = openCheck;
    return btn;
  }

  function openCheck() {
    const screen = el('section', 'clue-screen');
    const head = el('div', 'clue-head');
    head.append(el('div', 'where', 'Comprobar un cartón'),
      el('div', 'note', 'Escribe el número que aparece en el cartón'));

    // Its own layout rather than the clue screen's centred one: a card grid is
    // tall, and centring it pushes the number box up under the heading.
    const body = el('div', 'clue-body check');
    const input = document.createElement('input');
    input.type = 'number';
    input.min = '1';
    // The highest id, not the number of cards: ids run continuously across the
              // 4x4 and 3x3 sets, so the two are not the same number.
    input.max = String(game.cards.reduce((max, c) => Math.max(max, c.id), 0));
    input.placeholder = 'Nº';
    input.className = 'card-number';

    const result = el('div', 'check-result');

    const go = el('button', 'primary', 'Comprobar');
    go.onclick = () => {
      result.innerHTML = '';
      const card = game.cards.find(c => c.id === Number(input.value));
      if (!card) {
        result.append(el('p', 'error', `No hay ningún cartón con el número ${input.value || '—'}.`));
        return;
      }
      const size = card.grid.length;
      result.append(el('p', 'rate-label', `Cartón ${card.id} · ${size}×${size}`),
        verdict(card), cardGrid(card));
    };
    input.onkeydown = e => { if (e.key === 'Enter') go.click(); };

    body.append(el('div', 'award-row', null, [input, go]), result);

    const close = el('button', 'ghost', 'Volver');
    close.onclick = renderCaller;

    screen.append(head, body, el('div', 'clue-controls', null, [close]));
    root.append(screen);
    input.focus();
  }

  function calledWords() {
    // Must match the strings printed in the card cells exactly.
    return new Set(state.calls.map(c => displayFace(game.items[c.i])));
  }

  function verdict(card) {
    const called = calledWords();
    const size = card.grid.length;
    const marked = (r, c) => card.grid[r][c] === null || called.has(card.grid[r][c]);
    const win = winningLines(size, state.pattern).some(line => line.every(([r, c]) => marked(r, c)));

    const box = el('div', win ? 'verdict win' : 'verdict lose');
    box.textContent = win ? '¡BINGO VÁLIDO!' : 'TODAVÍA NO';
    if (win) {
      fx.play('fanfare');
      fx.confetti();
    } else {
      fx.play('notYet');
    }
    return box;
  }

  function cardGrid(card) {
    const called = calledWords();
    const size = card.grid.length;
    const grid = el('div', 'card-grid');
    grid.style.gridTemplateColumns = `repeat(${size}, minmax(0, 1fr))`;
    card.grid.forEach(row => row.forEach(word => {
      if (word === null) {
        grid.append(el('div', 'card-cell marked', 'GRATIS'));
        return;
      }
      grid.append(el('div', 'card-cell' + (called.has(word) ? ' marked' : ''), word));
    }));
    return grid;
  }

  // Geometry, not data: derived from the grid size rather than from anything
  // that was printed, so there is nothing here that can drift from the cards.
  function winningLines(size, pattern) {
    if (pattern === 'blackout') {
      const all = [];
      for (let r = 0; r < size; r++) for (let c = 0; c < size; c++) all.push([r, c]);
      return [all];
    }
    const lines = [];
    const idx = Array.from({ length: size }, (_, i) => i);
    for (let r = 0; r < size; r++) lines.push(idx.map(c => [r, c]));
    for (let c = 0; c < size; c++) lines.push(idx.map(r => [r, c]));
    lines.push(idx.map(i => [i, i]));
    lines.push(idx.map(i => [i, size - 1 - i]));
    return lines;
  }

  /* ---------- speech ---------- */

  /* The voice list fills in asynchronously, so asking during the first paint
   * always says "no Spanish voice" and quietly hides the listening mode. Redraw
   * the setup screen once the list arrives, if it is still untouched.
   */
  function primeVoices() {
    window.RoomUI.primeVoices(() => {
      if (!state && !setupTouched && root && root.querySelector('.choice-list')) renderSetup();
    });
  }

  function writtenClue(item) {
    const id = audioSourceId();
    return String((id ? item[id] : displayFace(item)) || '');
  }

  function spokenClue(item) {
    return speakable(writtenClue(item));
  }

  function speakAt(text) {
    window.RoomUI.speak(text, state && state.rate != null ? state.rate : window.RoomUI.DEFAULT_RATE);
  }

  /* ---------- reset ---------- */

  function resetButton() {
    const btn = el('button', 'ghost small', 'Reiniciar');
    let armed = false;
    btn.onclick = () => {
      if (!armed) {
        armed = true;
        btn.textContent = '¿Seguro?';
        setTimeout(() => { armed = false; btn.textContent = 'Reiniciar'; }, 4000);
        return;
      }
      clearSaved();
      renderSetup();
    };
    return btn;
  }

  window.Bingo = { mount };
})();
