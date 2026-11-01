/* What happened in this room, reported on the side.
 *
 * Everything here is fire and forget. A classroom's connection comes and goes,
 * and nothing a teacher presses may wait on this or fail because of it: track()
 * only adds to a list, the list goes out a few seconds later, and if it cannot
 * go it waits for the next try and is eventually dropped. Losing a report is
 * always better than a game noticing.
 *
 * The id is made fresh for each page load and kept in memory only. Nothing goes
 * in the browser's storage, so a room opened anonymously cannot be told from any
 * other, which is what lets a game be played without anyone being asked to
 * consent to anything. Pick what is sent with care: never a team name, which may
 * be a child's. The server drops any key it does not list (GameEvent.php), but
 * that is the backstop rather than the rule.
 *
 * Loaded first, so an error in any later script is caught here.
 */
(function () {
  'use strict';

  const ENDPOINT = '/api/game-events';
  const SEND_AFTER_MS = 3000;
  // Must not pass GameEventController::MAX_BATCH.
  const BATCH_SIZE = 30;
  const MAX_WAITING = 120;
  const MAX_ERRORS = 5;

  /* Built by hand because crypto.randomUUID is missing from older browsers and
   * from any page not on https, and a school laptop may well be both. The
   * feedback endpoint refuses anything that is not a real UUID. */
  function uuid() {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map(b => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
  }

  const playId = uuid();

  let room = null;
  let game = null;
  let waiting = [];
  let timer = null;
  let errors = 0;

  function batch() {
    return JSON.stringify({
      play_id: playId,
      room_code: room && !room.gameId ? room.code : null,
      own_game: room && room.gameId ? room.gameId : null,
      events: waiting.slice(0, BATCH_SIZE),
    });
  }

  function send() {
    timer = null;
    if (!waiting.length) return;
    const body = batch();
    const sent = waiting.splice(0, BATCH_SIZE);
    fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body,
    }).catch(() => {
      // Back on the list for the next report to carry, up to a limit: a whole
      // lesson offline must not end up as a list that grows without end.
      waiting = sent.concat(waiting).slice(-MAX_WAITING);
    });
    if (waiting.length) schedule();
  }

  function schedule() {
    if (!timer) timer = setTimeout(send, SEND_AFTER_MS);
  }

  function track(event, data) {
    waiting.push({ event, game, data: data || null });
    if (waiting.length > MAX_WAITING) waiting.shift();
    schedule();
  }

  // The last chance, as a tab closes or a phone switches away. A beacon is the
  // one send a closing page is sure to get out.
  addEventListener('pagehide', () => {
    if (!waiting.length || !navigator.sendBeacon) return;
    navigator.sendBeacon(ENDPOINT, new Blob([batch()], { type: 'application/json' }));
    waiting = [];
  });

  // Only our own scripts' errors: a browser extension's are noise, and a
  // cross-origin script reports nothing but "Script error."
  addEventListener('error', e => {
    if (!e.filename || new URL(e.filename, location.href).origin !== location.origin) return;
    reportError(e.message, e.filename, e.lineno);
  });

  addEventListener('unhandledrejection', e => {
    const reason = e.reason;
    reportError(reason && reason.message ? reason.message : String(reason), '', 0);
  });

  function reportError(message, file, line) {
    if (errors >= MAX_ERRORS) return;
    errors += 1;
    track('js_error', { message: String(message), source: file.split('/').pop().split('?')[0], line });
  }

  window.RoomAnalytics = {
    track,
    uuid,
    enterRoom(value) { room = value; },
    enterGame(key) { game = key; },
    currentGame: () => game,
  };
})();
