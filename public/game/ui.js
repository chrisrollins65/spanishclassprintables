/* Bits both games share. Kept deliberately small — this is not a framework,
 * just the handful of things that would otherwise be written twice.
 */
(function () {
  'use strict';

  function el(tag, className, text, children) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    if (children) node.append(...children);
    return node;
  }

  const TITLE_MAX = 40;
  const TITLE_MIN = 13;

  /* Shrink a single line until it fits its column.
   *
   * The topic comes from the packet, so its length is not ours to control:
   * "Fruta" and "Cómo entrenar a tu dragón" both have to sit on the same bar
   * without wrapping or shoving the turn pill off the edge. CSS alone can't do
   * this — clamp() scales with the viewport, not with the length of the string
   * — so measure and step down. Cheap: a handful of layout reads, once per
   * render, on a bar that changes a few times a lesson.
   */
  function fitText(node, maxPx = TITLE_MAX, minPx = TITLE_MIN) {
    let size = maxPx;
    node.style.fontSize = size + 'px';
    while (size > minPx && node.scrollWidth > node.clientWidth) {
      size -= 1;
      node.style.fontSize = size + 'px';
    }
  }

  /* The strip across the top of every screen: status on the left, the packet's
   * topic centred, controls on the right. The side columns are equal fractions
   * so the topic sits on the true centre of the screen rather than in whatever
   * space its neighbours happen to leave.
   */
  function topBar(left, title, right) {
    const bar = el('div', 'topbar');
    const mid = el('div', 'topic-wrap');
    const topic = el('h1', 'topic', title || '');
    mid.append(topic);
    bar.append(left || el('div'), mid, right || el('div'));
    // Measured after the browser has laid the bar out; before that every width
    // reads as zero and the loop would exit at the maximum size.
    requestAnimationFrame(() => fitText(topic));
    return bar;
  }

  // Re-fit on resize — a teacher mirroring to a projector changes the window
  // size mid-lesson, and a title frozen at the laptop's width either overflows
  // or looks lost.
  window.addEventListener('resize', () => {
    document.querySelectorAll('.topic').forEach(node => fitText(node));
  });

  /* Speech, shared by both games.
   *
   * It started in the bingo caller, but the quiz board's top row is a listening
   * clue too, and a second copy of the voice-picking and the voices-arrive-late
   * dance would drift from the first the moment either was touched.
   */
  const DEFAULT_RATE = 0.75;

  /* The speeds a clue can be read at, slowest first — the direction of bingo's
   * clue dial, so the two rows on that screen do not run opposite ways.
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

  function nearlyRate(a, b) {
    return Math.abs((a == null ? DEFAULT_RATE : a) - b) < 0.01;
  }

  // A saved game carries whatever speed the list offered when it was saved, so
  // a room resumed after the list changes snaps to the nearest one still on it
  // rather than leaving every button unlit.
  function normalizeRate(value) {
    if (value == null) return DEFAULT_RATE;
    return RATES.reduce(
      (best, r) => (Math.abs(r.value - value) < Math.abs(best - value) ? r.value : best),
      RATES[0].value
    );
  }

  /* Restyled in place rather than re-rendered.
   *
   * A full redraw here would replay the clue the moment the speed changed and
   * quietly re-hide any text the teacher had just revealed. Neither is what
   * "make it slower" asks for: the new speed belongs to the next Repetir, which
   * the teacher presses when they are ready.
   */
  function rateRow(current, onPick) {
    const row = el('div', 'rate-row');
    row.append(el('span', 'rate-label', 'Velocidad'));
    const buttons = RATES.map(r => {
      const btn = el('button', 'choice small' + (nearlyRate(current, r.value) ? ' chosen' : ''), r.label);
      btn.onclick = () => {
        onPick(r.value);
        buttons.forEach(b => b.classList.remove('chosen'));
        btn.classList.add('chosen');
      };
      row.append(btn);
      return btn;
    });
    return row;
  }

  function hasSpeech() {
    return typeof window.speechSynthesis !== 'undefined';
  }

  function spanishVoice() {
    if (!hasSpeech()) return null;
    return window.speechSynthesis.getVoices().find(v => /^es\b|^es[-_]/i.test(v.lang)) || null;
  }

  /* Whether a listening clue can be offered at all.
   *
   * Not merely "the browser can speak" — it must speak SPANISH; a listening
   * exercise read by an English voice is worse than none. But the voice list
   * fills in asynchronously, so an empty list means "not yet known", not
   * "none": assume yes until it actually arrives.
   */
  function canSpeakSpanish() {
    if (!hasSpeech()) return false;
    if (!window.speechSynthesis.getVoices().length) return true;
    return !!spanishVoice();
  }

  function primeVoices(onReady) {
    if (!hasSpeech() || window.speechSynthesis.getVoices().length) return;
    window.speechSynthesis.addEventListener('voiceschanged', () => onReady && onReady(), { once: true });
  }

  /* A clue with a gap read aloud, where there is no beep to mark the gap with.
   *
   * Speech engines either skip a run of underscores or read it as the word
   * "underscore", both of which wreck the sentence. A comma is the one thing
   * every engine turns into a pause. It is the fallback, not the answer — see
   * speak(), which sounds the blank instead wherever the browser will let it.
   */
  function speakable(text) {
    return String(text || '').replace(/_{2,}/g, ',');
  }

  /* The pieces a sentence with blanks is read in.
   *
   * Every piece with a blank after it is given a comma it did not have. The
   * engine is about to reach the end of an utterance, and it lands the end of
   * an utterance the way it lands the end of a sentence — the falling, finished
   * tone that makes a class hear two sentences with a beep between them instead
   * of one sentence with a hole in it. A comma is the one mark every engine
   * reads as "still going". It costs about 40ms.
   */
  function gapPieces(text) {
    const parts = String(text).split(/_{2,}/);
    return parts.map((part, i) => {
      const said = part.replace(/\s+$/, '');
      if (i === parts.length - 1 || !said || /[,;:.!?…—-]$/.test(said)) return said;
      return said + ',';
    });
  }

  /* Bumped by every speak(). A sentence read in pieces has to be able to stop
   * between them: when the next clue starts — or the teacher presses Repetir —
   * the half this run had left to say must not arrive over the top of it.
   */
  let speechRun = 0;

  // The beep sounding right now, if any. A blank belongs to the sentence it is
  // in, so the next one silences it wherever it had got to.
  let ringing = null;

  function hushGap() {
    if (ringing) ringing.stop();
    ringing = null;
  }

  function utterance(text, rate) {
    const utter = new SpeechSynthesisUtterance(text);
    const voice = spanishVoice();
    if (voice) utter.voice = voice;
    utter.lang = voice ? voice.lang : 'es-ES';
    utter.rate = rate == null ? DEFAULT_RATE : rate;
    return utter;
  }

  /* One unbroken stretch of speech.
   *
   * Resolves when the voice has finished — or been cut off by the next speak(),
   * which is also an end. The ceiling is for Chrome, where some voices never
   * fire `end` at all: anything waiting on the word (the flashcards' automatic
   * run) would otherwise wait forever.
   */
  function sayPart(text, rate, run) {
    return new Promise(resolve => {
      if (!/\S/.test(text) || run !== speechRun) return resolve();
      const utter = utterance(text, rate);
      const ceiling = setTimeout(resolve, 1500 + text.length * 150);
      utter.onend = utter.onerror = () => { clearTimeout(ceiling); resolve(); };
      window.speechSynthesis.speak(utter);
    });
  }

  /* Say it, sounding any blank rather than pausing at it.
   *
   * The sentence has to be broken in two to get a sound into the middle of it:
   * an engine will not say where in an utterance it has got to (Chrome fires no
   * boundary events at all for the network voices this runs on), so the only
   * way to place anything at the blank is to stop speaking there. Everything
   * below is about making that break sound like a bleeped-out word rather than
   * a full stop — the comma in gapPieces for the tone of it, and the beep
   * filling the silence for the length of it.
   *
   * Resolves when the last piece has finished, so the callers that wait on a
   * word (the flashcards, the bingo caller) wait for the whole sentence.
   */
  function speak(text, rate) {
    if (!hasSpeech() || !text) return Promise.resolve();
    window.speechSynthesis.cancel();
    hushGap();
    const run = ++speechRun;
    const fx = window.RoomFX;
    const pieces = gapPieces(text);
    // No blank, or no way to sound one: one utterance, blanks flattened to a comma.
    if (pieces.length < 2 || !fx || !fx.gapTone) return sayPart(speakable(text), rate, run);

    /* The sentence as a run of steps: a piece to say, and null for each blank
     * between two of them. An empty piece — a blank at the very start or end of
     * the sentence — drops out, and with it any second beep in a row, so two
     * can never land back to back. */
    const steps = [];
    pieces.forEach((piece, i) => {
      if (i && steps[steps.length - 1] !== null) steps.push(null);
      if (/\S/.test(piece)) steps.push(piece);
    });
    const last = steps.length - 1;

    return new Promise(resolve => {
      const ring = at => {
        if (run !== speechRun) return;
        hushGap();
        ringing = fx.gapTone();
        if (at !== last) return;
        // A blank that ends the sentence has no voice coming back to stop it,
        // so it rings itself out and that is the end of the clue.
        if (ringing) ringing.done.then(resolve);
        else resolve();
      };

      /* Every piece is queued now rather than asked for when the one before it
       * ends. An engine leaves silence between two utterances whatever we do —
       * about a quarter of a second for the voice this is written for — and
       * waiting on `end` to queue the next one adds a round trip through the
       * browser to it. That silence is the full stop: the less of it there is,
       * and the more of it the beep fills, the more the two halves stay one
       * sentence. */
      steps.forEach((step, k) => {
        if (step === null) {
          if (!k) ring(0);
          return;
        }
        const utter = utterance(step, rate);
        // The voice is back, so the blank is over. Stopping the beep here and
        // not on a timer is what makes it exactly as long as the silence it has
        // to cover: any longer and it plays over the first word of the rest of
        // the sentence, which is the word the class can least afford to lose.
        utter.onstart = hushGap;
        utter.onend = utter.onerror = () => {
          if (k === last) return resolve();
          if (steps[k + 1] === null) ring(k + 1);
        };
        window.speechSynthesis.speak(utter);
      });

      // The ceiling, as in sayPart: a voice that never fires `end` must not
      // leave a caller waiting on the sentence forever.
      setTimeout(resolve, 2000 + String(text).length * 200);
    });
  }

  /* A translation shown on request.
   *
   * A scaffold, not the answer, so it toggles both ways: reveal it, read the
   * clue again, hide it and try once unaided.
   */
  function englishToggle(text) {
    const wrap = el('div', 'call-en-wrap');
    const line = el('p', 'call-en', text);
    line.hidden = true;
    const btn = el('button', 'small', 'Ver en inglés');
    btn.onclick = () => {
      line.hidden = !line.hidden;
      btn.textContent = line.hidden ? 'Ver en inglés' : 'Ocultar el inglés';
    };
    wrap.append(line, btn);
    return wrap;
  }

  /* A word as the printed pages show it: with its article.
   *
   * The article is a separate field on an item, so it can be left off a gap
   * sentence and off words that never take one. Everything that shows the word
   * itself puts it back — gender is half of what the word list teaches, and a
   * bingo card cell has to match the card the printer made.
   */
  function displayFace(item) {
    if (!item) return '';
    return [item.article, item.face].filter(Boolean).join(' ').trim();
  }

  /* The whole word list on the wall, in both games, at two moments.
   *
   * Before a game ('review') it is the lesson: the class goes through every
   * word, hears it, and says it back. That is the job the printed Lista de
   * Palabras used to do, and on the screen nobody has to print it. English shows
   * from the start, because this is where the meaning is being learned.
   *
   * During a game ('reminder') it is a nudge for a child who has forgotten a
   * word, and the Spanish alone does that. English stays one tap away: beside
   * the Spanish it answers an English clue outright, which the teacher may well
   * want for a class that is struggling — but as their call, not the default.
   *
   * Every word is a button that says itself. Hearing a word and repeating it
   * is how a class learns it, and that works as well mid-game as before it.
   * Words said once stay marked, so a teacher going round the list can see
   * where they got to.
   *
   * The review also offers the words one at a time, as flashcards (flashDeck
   * below). Only the review: mid-game the list is meant to be up and gone again
   * in seconds, and a deck of thirty cards is not that.
   */
  function openVocab(container, items, opts = {}) {
    if (!items || !items.length) return;
    const review = opts.moment === 'review';
    const audible = canSpeakSpanish();
    // Shared by both views, so a word said on a card is marked in the list too.
    const heard = new Set();
    let showing = review;
    // Built on first use and kept, so going to the list and back returns to the
    // same card rather than to the first one.
    let deck = null;

    const screen = el('section', 'clue-screen vocab-screen');
    const note = el('div', 'note');
    const head = el('div', 'clue-head');
    head.append(el('div', 'where', review ? 'Repasemos el vocabulario' : 'Vocabulario'), note);
    const body = el('div', 'clue-body vocab-body');
    const controls = el('div', 'clue-controls');

    function say(i) {
      heard.add(i);
      return speak(displayFace(items[i]));
    }

    const close = el('button', 'primary', 'Cerrar');
    close.onclick = () => {
      // The capture flag has to match the one it was added with, or the
      // listener survives and swallows every later key.
      document.removeEventListener('keydown', onKey, true);
      document.removeEventListener('keyup', onKey, true);
      if (deck) deck.stop();
      screen.remove();
    };

    function showList() {
      if (deck) deck.stop();
      note.textContent = review
        ? (audible ? 'Toca una palabra para oírla y repítanla juntos' : 'Lean cada palabra en voz alta')
        : 'Míralo bien — desaparece enseguida';

      const grid = el('div', 'vocab-grid');
      items.forEach((item, i) => {
        const cell = el(audible ? 'button' : 'div', 'vocab-item' + (heard.has(i) ? ' heard' : ''));
        cell.append(el('span', 'vocab-es', displayFace(item)));
        const en = el('span', 'vocab-en', item.en || '');
        en.hidden = !showing;
        cell.append(en);
        if (audible) {
          cell.onclick = () => {
            say(i);
            cell.classList.add('heard');
          };
        }
        grid.append(cell);
      });

      const english = el('button', 'small', showing ? 'Ocultar el inglés' : 'Ver en inglés');
      english.onclick = () => {
        showing = !showing;
        grid.querySelectorAll('.vocab-en').forEach(e => { e.hidden = !showing; });
        english.textContent = showing ? 'Ocultar el inglés' : 'Ver en inglés';
      };

      const row = el('div', 'award-row', null, [english]);
      if (review) {
        const toCards = el('button', 'small', '🃏 Una por una');
        toCards.onclick = showCards;
        row.append(toCards);
      }
      row.append(close);
      body.replaceChildren(grid);
      controls.replaceChildren(row);
    }

    function showCards() {
      deck = deck || flashDeck(items, {
        audible,
        say,
        onVoice: voiced => { note.textContent = voiced ? 'Escuchen y repitan' : 'Lean cada palabra en voz alta'; },
      });
      const toList = el('button', 'small', '☰ Ver la lista');
      toList.onclick = showList;
      body.replaceChildren(deck.node);
      controls.replaceChildren(el('div', 'award-row', null, [toList, close]));
      deck.show();
    }

    function onKey(e) {
      if (e.key === 'Escape') {
        if (e.type === 'keydown') {
          e.stopImmediatePropagation();
          close.click();
        }
        return;
      }
      if (deck && deck.node.isConnected) deck.key(e);
    }
    // Captured, so Escape closes the word list rather than the clue underneath.
    document.addEventListener('keydown', onKey, true);
    document.addEventListener('keyup', onKey, true);

    screen.append(head, body, controls);
    container.append(screen);
    showList();
  }

  /* How long the automatic run gives the class at each step.
   *
   * REPEAT is after the voice, for the whole room to say the word back — long
   * enough for "la bicicleta" in chorus without the next card landing on top of
   * it. THINK is before an answer, on a card that starts in English: a moment
   * to work out the Spanish and call it out before the card turns. LOOK is the
   * English showing under a Spanish word before moving on.
   */
  const FLASH_REPEAT_MS = 3000;
  const FLASH_THINK_MS = 4000;
  const FLASH_LOOK_MS = 2000;

  function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /* One word at a time, big enough for the back row, for the class to say back.
   *
   * A card starts on one side and turns to show the other. Starting in Spanish
   * is hear-it-and-say-it: the word is said as the card lands, and the turn
   * shows what it means. Starting in English is the harder direction and the
   * one that makes a word stick — the class has to produce the Spanish before
   * the card turns and says it. Either way the turned card is the same, the
   * Spanish large and the English under it, so the answer always looks alike.
   *
   * Automático runs the deck by itself, which frees the teacher to walk the
   * room. Anything the teacher presses by hand stops it: once they are driving,
   * a card turning or moving on underneath them is a fight over the remote.
   *
   * The voice that speaks on its own — as a Spanish card lands, as an English
   * one turns — can be switched off, for a teacher who would rather say the
   * words themselves or have the class read them cold. 🔊 Otra vez still says
   * the word on demand either way; only the unasked-for voice goes quiet.
   */
  function flashDeck(items, { audible, say, onVoice }) {
    const inOrder = items.map((_, i) => i);
    let order = inOrder;
    let at = 0;
    let front = 'es';
    let revealed = false;
    let auto = false;
    let voiced = readVoiced();
    // Bumped by every move. A step of the automatic run waiting on a timer or
    // the voice checks it on waking, and stops if the deck has moved on.
    let turn = 0;

    const node = el('div', 'flash');
    const count = el('div', 'flash-count');
    const main = el('span', 'flash-main');
    const sub = el('span', 'flash-sub');
    const card = el('button', 'flash-card', null, [main, sub]);
    card.onclick = () => {
      stopAuto();
      if (revealed) {
        // Turned back, not just left: "hide it and say it once more without
        // the help" is half of what a flashcard is for.
        revealed = false;
        paint();
      } else {
        reveal();
      }
    };

    const prev = el('button', null, '← Anterior');
    prev.onclick = () => { stopAuto(); go(at - 1); };
    const again = el('button', null, '🔊 Otra vez');
    again.hidden = !audible;
    again.onclick = () => say(order[at]);
    const next = el('button', 'primary', 'Siguiente →');
    next.onclick = () => { stopAuto(); go(at + 1 < order.length ? at + 1 : 0); };

    const fronts = [['es', 'Español'], ['en', 'Inglés']].map(([key, label]) => {
      const btn = el('button', 'choice small' + (front === key ? ' chosen' : ''), label);
      btn.onclick = () => {
        front = key;
        fronts.forEach(b => b.classList.toggle('chosen', b === btn));
        go(at);
      };
      return btn;
    });

    // Back to card one either way: a reshuffle part-way through would leave
    // the counter pointing into a deck the class has never seen in that order.
    const mix = el('button', 'choice small', '🔀 Mezclar');
    mix.onclick = () => {
      const on = order === inOrder;
      order = on ? shuffled(inOrder) : inOrder;
      mix.classList.toggle('chosen', on);
      go(0);
    };

    // Only where there is a voice to silence. Switching it off also cuts a word
    // already being said, so the button answers at once.
    const voiceBtn = el('button', 'choice small');
    voiceBtn.hidden = !audible;
    voiceBtn.onclick = () => {
      voiced = !voiced;
      writeVoiced(voiced);
      if (!voiced && hasSpeech()) window.speechSynthesis.cancel();
      paintVoice();
    };

    function paintVoice() {
      voiceBtn.textContent = voiced ? '🗣️ Con voz' : '🔇 Sin voz';
      voiceBtn.classList.toggle('chosen', voiced);
      if (onVoice) onVoice(audible && voiced);
    }

    // Offered with or without a voice: without one the run simply leaves the
    // saying to the class, which still keeps the teacher's hands free.
    const autoBtn = el('button', 'choice small', '▶ Automático');
    autoBtn.onclick = () => {
      if (auto) return stopAuto();
      setAuto(true);
      // From the top again if the last card is already done with.
      go(at === order.length - 1 && revealed ? 0 : at);
    };

    node.append(
      count,
      card,
      el('div', 'award-row', null, [prev, again, next]),
      el('div', 'flash-options', null, [el('span', 'rate-label', 'Empieza en'), ...fronts, mix, voiceBtn, autoBtn]),
      el('p', 'hint', '← → para pasar · espacio para dar la vuelta')
    );

    function paint() {
      const item = items[order[at]];
      const spanishUp = front === 'es' || revealed;
      count.textContent = `${at + 1} / ${order.length}`;
      main.textContent = spanishUp ? displayFace(item) : (item.en || '');
      main.classList.toggle('en', !spanishUp);
      // A turned card shows the English under the Spanish whichever side it
      // started on: having just said the Spanish, seeing both together is what
      // joins the two up.
      if (revealed) sub.textContent = item.en || '';
      else sub.textContent = front === 'es' ? 'Toca la tarjeta para ver el inglés' : '¿Cómo se dice en español?';
      sub.classList.toggle('ask', !revealed);
      // Hearing the word IS the answer when the card starts in English.
      again.disabled = front === 'en' && !revealed;
      prev.disabled = at === 0;
      next.textContent = at + 1 < order.length ? 'Siguiente →' : '↺ Desde el principio';
    }

    function reveal() {
      revealed = true;
      paint();
      replay(card, 'turned');
      return front === 'en' && audible && voiced ? say(order[at]) : Promise.resolve();
    }

    function go(i) {
      at = Math.max(0, Math.min(i, order.length - 1));
      revealed = false;
      paint();
      replay(card, 'arrive');
      const t = ++turn;
      const spoken = front === 'es' && audible && voiced ? say(order[at]) : Promise.resolve();
      if (auto) run(t, spoken);
    }

    async function run(t, spoken) {
      const live = () => auto && t === turn && node.isConnected;
      if (front === 'es') {
        await spoken;
        await wait(FLASH_REPEAT_MS);
        if (!live()) return;
        reveal();
        await wait(FLASH_LOOK_MS);
      } else {
        await wait(FLASH_THINK_MS);
        if (!live()) return;
        await reveal();
        if (!live()) return;
        await wait(FLASH_REPEAT_MS);
      }
      if (!live()) return;
      if (at + 1 >= order.length) return setAuto(false);
      go(at + 1);
    }

    function setAuto(on) {
      auto = on;
      autoBtn.textContent = on ? '⏸ Pausa' : '▶ Automático';
      autoBtn.classList.toggle('chosen', on);
    }

    function stopAuto() {
      if (!auto) return;
      setAuto(false);
      turn++;
    }

    // Arrows and the space bar, from a teacher at a laptop across the room.
    // Swallowed on the way up as well as down: a button still holding focus
    // from a click would otherwise take the space bar as a second press.
    function key(e) {
      const target = { ArrowRight: next, ArrowLeft: prev, ' ': card }[e.key];
      if (!target) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      if (e.type === 'keydown' && !e.repeat && !target.disabled) target.click();
    }

    return {
      node,
      key,
      show() {
        paintVoice();
        go(at);
      },
      stop() {
        setAuto(false);
        turn++;
        if (hasSpeech()) window.speechSynthesis.cancel();
      },
    };
  }

  // Kept across lessons and packs, like the sound-effects mute (fx.js): a
  // teacher who says the words themselves will do so every period, and should
  // not have to find the button again each time.
  const FLASH_VOICE_KEY = 'scp-flash-voice-off';

  function readVoiced() {
    try { return localStorage.getItem(FLASH_VOICE_KEY) !== '1'; } catch { return true; }
  }

  function writeVoiced(on) {
    try {
      if (on) localStorage.removeItem(FLASH_VOICE_KEY);
      else localStorage.setItem(FLASH_VOICE_KEY, '1');
    } catch {}
  }

  function shuffled(list) {
    const out = list.slice();
    for (let i = out.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [out[i], out[j]] = [out[j], out[i]];
    }
    return out;
  }

  // Remove and re-add, so the animation plays again on an element that
  // already has the class. Both card animations go first: whichever was left
  // on would outrank the other in the stylesheet and stop it playing.
  function replay(node, className) {
    node.classList.remove('arrive', 'turned');
    void node.offsetWidth;
    node.classList.add(className);
  }

  /* The way into the list before a game, on each game's setup screen.
   *
   * Never `primary`. The builder's screenshot pass (roomCapture.js) starts a
   * game by pressing the setup screen's one primary button; with a second one
   * here it could open the word list instead, and every screenshot after that
   * would be of the wrong screen.
   */
  function reviewButton(container, items) {
    const btn = el('button', null, '📖 Repasar el vocabulario');
    // Hidden rather than greyed: an older room published without its word list
    // would otherwise open every lesson on a button that does nothing.
    btn.hidden = !items || !items.length;
    btn.onclick = () => openVocab(container, items, { moment: 'review' });
    return btn;
  }

  /* How the game is played, for both games.
   *
   * Two readers at once. A teacher opening a pack for the first time needs the
   * rules in English; the class it is projected to can take the same rules in
   * Spanish, which is a little more of the lesson. So every step is a Spanish
   * line with its English under it. The English starts out showing — a rule
   * nobody understood is not a rule — and one tap hides it for a teacher who
   * wants to go through the rules in Spanish.
   *
   * `steps` is a function, called each time the panel opens, so the rules
   * describe THIS room right now: whether it has a Daily Double, the winning
   * pattern just picked. Where they describe the site they must match what the
   * code does; the rest is how the room runs around it (pencils down before
   * anyone reads out), which the site cannot enforce but must never contradict.
   * The same rules are written twice more in the builder (tptwsbuilder): the
   * printed How to Play page (src/templates/jeopardyComoJugar.html and
   * bingoComoJugar.html) and GAME_LISTING_FACTS in src/ai/prompts.js, which
   * tells buyers. A rule changed in a game has to be changed in all three.
   *
   * Numbered, because the list runs down one column and then the next, and
   * without numbers a reader goes across the rows instead.
   */
  function openHowTo(container, steps) {
    const screen = el('section', 'clue-screen howto-screen');
    let showing = true;

    const head = el('div', 'clue-head');
    head.append(el('div', 'where', 'Cómo se juega'), el('div', 'note howto-en', 'How to play'));

    const list = el('ol', 'howto-steps');
    steps().forEach(step => {
      list.append(el('li', 'howto-step', null, [
        el('span', 'howto-icon', step.icon),
        el('div', 'howto-text', null, [el('p', 'howto-es', step.es), el('p', 'howto-en', step.en)]),
      ]));
    });
    const body = el('div', 'clue-body howto-body', null, [list]);

    const english = el('button', 'small', 'Ocultar el inglés');
    english.onclick = () => {
      showing = !showing;
      screen.querySelectorAll('.howto-en').forEach(e => { e.hidden = !showing; });
      english.textContent = showing ? 'Ocultar el inglés' : 'Ver en inglés';
    };

    const close = el('button', 'primary', 'Cerrar');
    close.onclick = () => {
      document.removeEventListener('keydown', onKey, true);
      screen.remove();
    };

    // Captured, like the word list's, so Escape closes this panel and not the
    // screen under it. A panel wiped by a redraw of the screen beneath lets go
    // of the key rather than swallowing the next Escape.
    function onKey(e) {
      if (e.key !== 'Escape') return;
      if (!screen.isConnected) return document.removeEventListener('keydown', onKey, true);
      e.stopImmediatePropagation();
      close.click();
    }
    document.addEventListener('keydown', onKey, true);

    screen.append(head, body, el('div', 'clue-controls', null, [el('div', 'award-row', null, [english, close])]));
    container.append(screen);
    close.focus();
  }

  /* The way into the rules. Labelled on each setup screen, where a teacher new
   * to the game looks first; a bare ❓ in the top bar during play, where the
   * corner is already full. Never `primary`, for the reason reviewButton gives.
   */
  function howToButton(container, steps, opts = {}) {
    const btn = opts.compact
      ? el('button', 'ghost small howto-btn', '❓')
      : el('button', null, '❓ Cómo se juega');
    if (opts.compact) {
      btn.title = 'Cómo se juega · How to play';
      btn.setAttribute('aria-label', 'Cómo se juega');
    }
    btn.onclick = () => openHowTo(container, steps);
    return btn;
  }

  /* Where "more games" sends the teacher. One constant, because the same link
   * goes on four screens and a store that moves should move in one edit.
   *
   * The store rather than this site's own front page: the games are bought on
   * TpT, and sending a teacher who is mid-lesson to a homepage that then has to
   * hand them on again loses most of them. Not the same address as the QR code
   * printed on the worksheets, which points at the site on purpose. */
  const MORE_GAMES_URL = 'https://www.teacherspayteachers.com/store/spanish-class-printables';

  /* The logo, as the heading of a screen rather than decoration on it.
   *
   * `full` is for the screens that are only a logo and a prompt — the code entry
   * — where it stands in for the h1 outright. Everywhere else it is `compact`:
   * the setup screens are already measured in vh and shrink to fit (see
   * .centered in styles.css), so a banner across the top is bought straight out
   * of the controls below it.
   *
   * Decorative wherever a real heading follows it, so a screen reader is not made
   * to read the store's name before the topic the class is here for.
   */
  function brandMark(size = 'compact') {
    const img = el('img', 'brand-mark brand-' + size);
    img.src = '/game/logo.png';
    if (size === 'full') {
      img.alt = 'Spanish Class Printables';
    } else {
      img.alt = '';
      img.setAttribute('aria-hidden', 'true');
    }
    return img;
  }

  /* The way back to the store, for the teacher who has just watched the game
   * land. Only ever on the screens either side of a game — never during one,
   * where the one thing on screen has to be the class's, not the store's.
   *
   * English, like the printed How to Play page: the class plays in Spanish, but
   * this line is aimed over their heads at the person who does the buying.
   */
  function moreGames(label = 'Get more games and printables') {
    const link = el('a', 'more-games', label);
    link.href = MORE_GAMES_URL;
    link.target = '_blank';
    link.rel = 'noopener';
    return link;
  }

  /* Where a buyer leaves feedback: every purchase on one page, each with its own
   * Provide Feedback button, and the sign-in sends them straight back to it.
   *
   * Deliberately not a product page. The room has no idea which listing it was
   * sold as — the pack can be bought on its own or inside a bundle, and the
   * builder only learns the product URL after the upload, long after the room
   * was published — so a guessed link would send half of them somewhere they
   * cannot review from.
   */
  const REVIEW_URL = 'https://www.teacherspayteachers.com/My-Purchases';

  /* What the screen says once the game is over.
   *
   * The review ask leads because it is the one that expires: the teacher is
   * standing in front of a game that has just worked, with the class still in
   * the room. By tonight they are marking and the ask is worth nothing. The
   * store link stays under it, quieter, for the same teacher on the way out.
   *
   * Never on the demo room: nobody playing the demo has bought anything, and
   * asking them to review a purchase they never made reads as a scam. They get
   * the store link on its own, which is what the demo is for anyway.
   */
  function afterGame(room) {
    const wrap = el('div', 'after-game');
    if (!(room && room.demo)) {
      const ask = el('a', 'review-cta');
      ask.href = REVIEW_URL;
      ask.target = '_blank';
      ask.rel = 'noopener';
      ask.append(
        el('span', 'review-ask', '⭐ Did this work for your class?'),
        // Both halves of the ask, because only one of them is about us: TpT
        // really does pay credits for feedback, and a teacher who does not know
        // that is being asked for a favour rather than offered a trade.
        el('span', 'review-why', 'Leave a review on TpT — it helps us, and you earn credits toward your next purchase.')
      );
      wrap.append(ask);
    }
    wrap.append(moreGames());
    return wrap;
  }

  window.RoomUI = {
    el, fitText, topBar,
    hasSpeech, spanishVoice, canSpeakSpanish, primeVoices, speak, englishToggle,
    displayFace, openVocab, reviewButton, openHowTo, howToButton,
    brandMark, moreGames, afterGame, MORE_GAMES_URL,
    DEFAULT_RATE, RATES, normalizeRate, rateRow,
  };
})();
