/* Sound effects and celebrations, shared by both games.
 *
 * Every sound is synthesised here with Web Audio rather than shipped as a file.
 * That is not about bytes: a sound file is a licence to check and keep checking,
 * and a chime built from three sine waves is nobody's recording.
 *
 * Effects are decoration, never content. The Spanish voice (RoomUI.speak) is
 * the game — so the mute here silences only these, and every caller that is
 * about to speak waits for its effect to finish first. A chime landing on top
 * of the first words of a listening clue is a clue the class never heard.
 */
(function () {
  'use strict';

  const MUTE_KEY = 'scp-fx-muted';
  // Quiet on purpose. A projector is often wired to the room's own speakers,
  // turned up for videos; these should sit under a teacher's voice, not over it.
  const VOLUME = 0.35;

  let ctx = null;
  let master = null;
  let muted = readMuted();

  // Global rather than per room: a teacher who wants silence wants it in every
  // pack, and should not have to find the button again next period.
  function readMuted() {
    try { return localStorage.getItem(MUTE_KEY) === '1'; } catch { return false; }
  }

  function setMuted(on) {
    muted = on;
    try {
      if (on) localStorage.setItem(MUTE_KEY, '1');
      else localStorage.removeItem(MUTE_KEY);
    } catch {}
    // Cut anything mid-flight too, so the button answers at once.
    if (master) master.gain.setValueAtTime(on ? 0 : VOLUME, ctx.currentTime);
  }

  /* Browsers refuse to start audio until the page has been touched. Every
   * effect in both games follows a click, but the context has to be created
   * inside one, so the first touch anywhere does it. */
  function audio() {
    if (!ctx) {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return null;
      ctx = new Ctx();
      master = ctx.createGain();
      master.gain.value = muted ? 0 : VOLUME;
      master.connect(ctx.destination);
    }
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }
  document.addEventListener('pointerdown', audio, { once: true, capture: true });

  /* ---------- building blocks ---------- */

  function tone(freq, at, dur, opts = {}) {
    const { type = 'sine', gain = 0.3, slideTo = null, attack = 0.01 } = opts;
    const t0 = ctx.currentTime + at;
    const osc = ctx.createOscillator();
    const env = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t0);
    if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, t0 + dur);
    env.gain.setValueAtTime(0.0001, t0);
    env.gain.exponentialRampToValueAtTime(gain, t0 + attack);
    env.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    osc.connect(env).connect(master);
    osc.start(t0);
    osc.stop(t0 + dur + 0.05);
  }

  // Two waves an octave apart: a lone sine sounds like a hearing test, and the
  // triangle on top gives a note enough body to carry across a room.
  function note(freq, at, dur, gain = 0.25) {
    tone(freq, at, dur, { type: 'triangle', gain });
    tone(freq * 2, at, dur * 0.8, { type: 'sine', gain: gain * 0.25 });
  }

  function noise(at, dur, opts = {}) {
    const { gain = 0.2, freq = 2000, q = 1, type = 'bandpass', sweepTo = null } = opts;
    const t0 = ctx.currentTime + at;
    const length = Math.max(1, Math.floor(ctx.sampleRate * dur));
    const buffer = ctx.createBuffer(1, length, ctx.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < length; i++) data[i] = Math.random() * 2 - 1;
    const src = ctx.createBufferSource();
    src.buffer = buffer;
    const filter = ctx.createBiquadFilter();
    filter.type = type;
    filter.Q.value = q;
    filter.frequency.setValueAtTime(freq, t0);
    if (sweepTo) filter.frequency.exponentialRampToValueAtTime(sweepTo, t0 + dur);
    const env = ctx.createGain();
    env.gain.setValueAtTime(0.0001, t0);
    env.gain.exponentialRampToValueAtTime(gain, t0 + Math.min(0.02, dur / 3));
    env.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    src.connect(filter).connect(env).connect(master);
    src.start(t0);
    src.stop(t0 + dur + 0.05);
  }

  const C5 = 523.25, D5 = 587.33, E5 = 659.25, G5 = 783.99, A5 = 880, B5 = 987.77;
  const C6 = 1046.5, E6 = 1318.5, G4 = 392, C4 = 261.63;

  /* ---------- the sounds ----------
   *
   * Each returns how long it lasts, in seconds, so play() can tell a caller
   * when the room is quiet again. */
  const SOUNDS = {
    // A clue opens.
    open() {
      note(A5, 0, 0.5, 0.2);
      note(E6, 0.09, 0.6, 0.16);
      return 0.55;
    },
    // The double square. Rising and held, so it reads as a find, not a warning.
    daily() {
      [C5, E5, G5, C6].forEach((f, i) => note(f, i * 0.09, 0.3, 0.22));
      [C5, E5, G5].forEach(f => note(f, 0.4, 0.9, 0.14));
      note(C6, 0.4, 0.9, 0.2);
      return 1.2;
    },
    // The last seconds of the writing timer.
    tick() {
      tone(1400, 0, 0.05, { type: 'square', gain: 0.08, attack: 0.002 });
      return 0.05;
    },
    // Time's up. A buzzer, but a short and low one: it ends the writing, it is
    // not a verdict on anybody.
    buzzer() {
      tone(180, 0, 0.55, { type: 'sawtooth', gain: 0.12 });
      tone(185, 0, 0.55, { type: 'sawtooth', gain: 0.12 });
      return 0.55;
    },
    // Money landing. The coin-pickup shape every child already knows means "got it".
    score() {
      tone(B5, 0, 0.09, { type: 'square', gain: 0.1, attack: 0.003 });
      tone(E6, 0.08, 0.45, { type: 'square', gain: 0.1, attack: 0.003 });
      note(E6, 0.08, 0.45, 0.1);
      return 0.55;
    },
    /* A right answer: the coin, then a quick rising "ta-da".
     *
     * Kept under a second on purpose. It plays twenty-odd times a game, and a
     * celebration that makes the room wait is one the teacher starts muting. The
     * long one (fanfare) is saved for the moments that happen once. */
    correct() {
      tone(B5, 0, 0.08, { type: 'square', gain: 0.09, attack: 0.003 });
      tone(E6, 0.07, 0.25, { type: 'square', gain: 0.09, attack: 0.003 });
      [C5, E5, G5].forEach((f, i) => note(f, 0.2 + i * 0.07, 0.2, 0.2));
      note(C6, 0.41, 0.5, 0.22);
      note(E5, 0.41, 0.5, 0.1);
      return 0.9;
    },
    // The lead changes hands: a two-note bugle call.
    leader() {
      note(G5, 0, 0.14, 0.22);
      note(C6, 0.13, 0.55, 0.24);
      tone(C6 / 2, 0.13, 0.55, { type: 'sawtooth', gain: 0.04 });
      return 0.7;
    },
    // A team on a streak: a whoosh of flame, then a spark on top.
    streak() {
      noise(0, 0.45, { gain: 0.2, freq: 300, sweepTo: 3500, q: 1.5 });
      note(E6, 0.3, 0.35, 0.14);
      note(G5, 0.3, 0.35, 0.1);
      return 0.65;
    },
    // A podium step rising into place, for every place but the winner's.
    step() {
      tone(330, 0, 0.18, { type: 'triangle', gain: 0.22, slideTo: 660 });
      return 0.2;
    },
    // Something heavy landing — the ×2 stamped onto the Daily Double's value.
    stamp() {
      tone(140, 0, 0.25, { type: 'sine', gain: 0.45, slideTo: 55 });
      noise(0, 0.12, { gain: 0.25, freq: 900, type: 'lowpass' });
      return 0.25;
    },
    /* Nobody got it. Deliberately not a fail sound: a whole room hearing a
     * wrong-answer honk is every team losing at once, and nothing else in this
     * game punishes a miss. A whoosh just clears the square. */
    none() {
      noise(0, 0.45, { gain: 0.18, freq: 400, sweepTo: 2400, q: 2 });
      return 0.45;
    },
    // Balls tumbling in the drum, then one pops out.
    draw() {
      for (let i = 0; i < 9; i++) {
        noise(i * 0.075 + Math.random() * 0.03, 0.04, { gain: 0.22, freq: 1800 + Math.random() * 1600, q: 6 });
      }
      tone(500, 0.72, 0.12, { type: 'sine', gain: 0.25, slideTo: 1100 });
      return 0.85;
    },
    // The big one, for what happens once: a card that really wins, a Daily
    // Double answered right.
    fanfare() {
      [C5, E5, G5, C6, G5, C6].forEach((f, i) => note(f, i * 0.1, 0.25, 0.22));
      [C5, E5, G5, C6].forEach(f => note(f, 0.62, 1.1, 0.13));
      noise(0.62, 0.5, { gain: 0.06, freq: 6000, type: 'highpass' });
      return 1.7;
    },
    /* A card does not win yet. A child is standing at the front holding it, so
     * this is a soft two-note "not quite", never a buzzer. */
    notYet() {
      tone(E5, 0, 0.22, { type: 'sine', gain: 0.18 });
      tone(C5, 0.2, 0.35, { type: 'sine', gain: 0.18 });
      return 0.55;
    },
    // The board is finished.
    victory() {
      [G4, C5, E5, G5].forEach((f, i) => note(f, i * 0.13, 0.22, 0.22));
      note(E5, 0.6, 0.18, 0.22);
      note(G5, 0.78, 1.1, 0.24);
      [C4, E5, C6].forEach(f => note(f, 0.78, 1.1, 0.1));
      return 1.9;
    },
    // A new game starts.
    start() {
      [C5, D5, E5, G5].forEach((f, i) => note(f, i * 0.07, 0.2, 0.18));
      return 0.4;
    },
  };

  /**
   * Play one effect.
   *
   * @returns {Promise<void>} Resolves once the effect has finished — at once when
   *   muted or when the browser has no audio — so speech can follow it.
   */
  function play(name) {
    const sound = SOUNDS[name];
    if (!sound || muted || !audio()) return Promise.resolve();
    let seconds = 0;
    try { seconds = sound(); } catch { return Promise.resolve(); }
    return new Promise(resolve => setTimeout(resolve, seconds * 1000));
  }

  /* The bell rather than a speaker: 🔊 already means "listening clue" on both
   * games' screens, and a second speaker in the corner would read as a way to
   * hear the clue again. */
  function muteButton() {
    const btn = document.createElement('button');
    btn.className = 'ghost small fx-mute';
    const paint = () => {
      btn.textContent = muted ? '🔕' : '🔔';
      btn.title = muted ? 'Efectos de sonido: apagados' : 'Efectos de sonido: encendidos';
      btn.setAttribute('aria-label', btn.title);
    };
    paint();
    btn.onclick = () => {
      setMuted(!muted);
      paint();
      if (!muted) play('open');
    };
    return btn;
  }

  /* ---------- motion ---------- */

  function reducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  const CONFETTI_COLORS = ['#B4744A', '#E8B461', '#5F8FB4', '#6FA96F', '#9B6FA9', '#FDF8F1'];

  /* Confetti: from both bottom corners, or — given `at` — a burst fountaining
   * up from one point on screen, for a celebration that belongs to one thing
   * (the team that just scored) rather than to the whole room.
   *
   * On a canvas of its own that is thrown away afterwards, and pointer-events
   * off, so a teacher can keep clicking straight through it. Skipped entirely
   * under reduced motion; the sound still plays. */
  function confetti(opts = {}) {
    if (reducedMotion()) return;
    const colors = opts.colors && opts.colors.length ? opts.colors : CONFETTI_COLORS;
    const at = opts.at || null;
    const count = opts.count || (at ? 60 : 180);
    const canvas = document.createElement('canvas');
    canvas.className = 'fx-confetti';
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const w = window.innerWidth, h = window.innerHeight;
    canvas.width = w * dpr;
    canvas.height = h * dpr;
    document.body.append(canvas);
    const g = canvas.getContext('2d');
    g.scale(dpr, dpr);

    const bits = [];
    for (let i = 0; i < count; i++) {
      const left = i % 2 === 0;
      // A burst fans out over the whole upper half; the corners each aim a
      // narrow jet in towards the middle of the screen.
      const angle = at
        ? -90 + (Math.random() * 130 - 65)
        : (left ? -60 : -120) + (Math.random() * 30 - 15);
      const speed = at
        ? h * (0.008 + Math.random() * 0.014)
        : h * (0.022 + Math.random() * 0.014);
      bits.push({
        x: at ? at.x : (left ? w * 0.05 : w * 0.95),
        y: at ? at.y : h + 10,
        vx: Math.cos(angle * Math.PI / 180) * speed,
        vy: Math.sin(angle * Math.PI / 180) * speed,
        size: 6 + Math.random() * 8,
        spin: Math.random() * Math.PI,
        vspin: (Math.random() - 0.5) * 0.35,
        color: colors[i % colors.length],
        delay: at ? 0 : Math.floor(Math.random() * 18),
      });
    }

    const start = performance.now();
    const LIFE = at ? 2600 : 4200;
    function frame(now) {
      const elapsed = now - start;
      g.clearRect(0, 0, w, h);
      const fade = elapsed > LIFE - 800 ? Math.max(0, (LIFE - elapsed) / 800) : 1;
      g.globalAlpha = fade;
      for (const b of bits) {
        if (b.delay > 0) { b.delay--; continue; }
        b.vy += h * 0.0006;
        b.vx *= 0.985;
        b.vy *= 0.985;
        b.x += b.vx;
        b.y += b.vy;
        b.spin += b.vspin;
        g.save();
        g.translate(b.x, b.y);
        g.rotate(b.spin);
        g.fillStyle = b.color;
        // Squashed by the spin so each piece flutters rather than slides.
        g.fillRect(-b.size / 2, -b.size / 4, b.size, b.size / 2 * Math.abs(Math.cos(b.spin * 2)) + 1);
        g.restore();
      }
      if (elapsed < LIFE) requestAnimationFrame(frame);
      else canvas.remove();
    }
    requestAnimationFrame(frame);
  }

  /* Open an overlay out of the thing that was clicked.
   *
   * A clip rather than a scale: scaling the whole clue screen up from a board
   * cell would stretch its text through the animation, and a clip keeps every
   * word at its final size while the window around it grows. */
  function growFrom(node, rect) {
    if (reducedMotion() || !rect || !node.animate) return;
    const w = window.innerWidth, h = window.innerHeight;
    const from = `inset(${rect.top}px ${w - rect.right}px ${h - rect.bottom}px ${rect.left}px round 10px)`;
    node.animate(
      [{ clipPath: from }, { clipPath: 'inset(0px 0px 0px 0px round 0px)' }],
      { duration: 420, easing: 'cubic-bezier(.2,.8,.2,1)' }
    );
  }

  /* A number climbing to its new value instead of jumping there. The jump is
   * over before anyone at the back has looked; the climb is what they see. */
  function countUp(node, from, to, format, ms = 900) {
    const show = v => { node.textContent = format ? format(v) : String(v); };
    if (reducedMotion() || from === to) return show(to);
    const start = performance.now();
    show(from);
    function frame(now) {
      const t = Math.min(1, (now - start) / ms);
      const eased = 1 - Math.pow(1 - t, 3);
      show(Math.round(from + (to - from) * eased));
      if (t < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  window.RoomFX = { play, muteButton, confetti, growFrom, countUp, reducedMotion };
})();
