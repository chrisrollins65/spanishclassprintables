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

  /* A clue with a gap read aloud.
   *
   * Speech engines either skip a run of underscores or read it as the word
   * "underscore", both of which wreck the sentence. A comma is the one thing
   * every engine turns into a pause, which is what a gap should sound like.
   */
  function speakable(text) {
    return String(text || '').replace(/_{2,}/g, ',');
  }

  function speak(text, rate) {
    if (!hasSpeech() || !text) return;
    window.speechSynthesis.cancel();
    const utter = new SpeechSynthesisUtterance(speakable(text));
    const voice = spanishVoice();
    if (voice) utter.voice = voice;
    utter.lang = voice ? voice.lang : 'es-ES';
    utter.rate = rate == null ? DEFAULT_RATE : rate;
    window.speechSynthesis.speak(utter);
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

  window.RoomUI = {
    el, fitText, topBar,
    hasSpeech, spanishVoice, canSpeakSpanish, primeVoices, speak, speakable, englishToggle,
    DEFAULT_RATE,
  };
})();
