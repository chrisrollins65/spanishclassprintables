/* One press, one submit.
 *
 * A form marked `data-once` disables its submit button as it goes, so the
 * second half of a double-tap — or an impatient second click on a slow
 * connection — does not post twice. A teacher who double-taps "Start this
 * game" was charged two credits and left with a duplicate draft.
 *
 * This is the courtesy layer, never the guarantee: it is gone if the script
 * has not loaded yet, if the page is refreshed onto a POST, or if a second tab
 * is open. Anything that spends a credit, money or AI is also guarded on the
 * server — see TeacherGameController::store(). Do not let a button like this
 * stand in for that.
 *
 * Deliberately NOT applied to the game itself: the score stepper, say-it-again
 * and the vocab arrows are tapped fast on purpose by a teacher in front of a
 * class, and a lockout there is a control that feels broken.
 *
 * Plain JS, no build step, like the rest of this site.
 */
(function () {
  'use strict';

  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-once')) return;

    // Let the browser's own validation have its say first: a form stopped for
    // a missing field must stay usable.
    if (event.defaultPrevented) return;

    if (form.dataset.submitted === '1') {
      event.preventDefault();

      return;
    }

    form.dataset.submitted = '1';

    const button = form.querySelector('button[type="submit"], button:not([type])');
    if (!button) return;

    // After the event, so the button's own name/value still reaches the
    // server — a disabled control is not submitted.
    setTimeout(function () {
      button.disabled = true;
      if (button.dataset.busy) button.textContent = button.dataset.busy;
    }, 0);
  });

  /* A page reached with the back button is served from the bfcache with the
   * form exactly as it was left — button disabled, already-submitted flag set.
   * Without this, going back to make a second game meets a dead button.
   */
  window.addEventListener('pageshow', function (event) {
    if (!event.persisted) return;

    document.querySelectorAll('form[data-once]').forEach(function (form) {
      delete form.dataset.submitted;
      form.querySelectorAll('button[disabled]').forEach(function (button) {
        button.disabled = false;
        if (button.dataset.idle) button.textContent = button.dataset.idle;
      });
    });
  });
})();
