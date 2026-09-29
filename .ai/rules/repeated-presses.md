# A repeated press must not cost anything twice

Globs: `app/Http/Controllers/**`, `resources/views/**`, `public/site/**`, `public/game/**`

Ask what a **second press costs**, not whether the control is a button. The
three answers want three different things, and applying the wrong one is how
this rule got written: a double-tap on *Start this game* charged a teacher two
credits and left them a duplicate draft.

## It spends or creates — guard on the server

Anything that spends a credit, money or AI, or creates a row: starting a game,
claiming a code, publishing, buying credits, an admin credit grant or refund.

The guard belongs **on the server**. A disabled button does not survive a
refresh onto the POST, the back button, a second tab, a flaky connection's
retry, or the gap before the script has loaded — and on a phone both halves of
a double-tap can fire before any handler runs.

`TeacherGameController::justStarted()` is the worked example: the same teacher,
the same kind and the same topic within `REPEAT_PRESS_SECONDS` is treated as
one press and redirected to the game it already made. Matched on what the press
said rather than on a one-time token, because the failure is the same press
arriving twice — and a token has to survive a back button, where it turns a
teacher who wants a second game into an expired form.

**A check like that reads before it writes, so it must be serialised or it does
nothing.** Two simultaneous presses both read nothing and both create. This is
not theoretical: it is exactly what a phone's double-tap sends. `store()` holds
a `Cache::lock` keyed to the teacher and the press for the whole check-and-
create. Before the lock, eight parallel presses made two games and took two
credits — while the sequential test passed. **Test the guard with parallel
requests, not a loop**, or you will be testing the case that was never broken.

`ContactController::store()` is the other shape: where the form already
carries a nonce, claim it instead of inventing one. Its `started` token is one
per rendering, so `Cache::add($this->pressKey(...))` — atomic on the file and
database stores, unlike a `has()` then `put()` — makes the second press a no-op
behind the same thank-you. Claimed **after** validation, because the form keeps
its token across an error and a teacher fixing a typo is not a repeat.

Then add the front-end courtesy on top: `data-once` on the form, handled by
`public/site/once.js`, which disables the submit button as it goes and clears
itself when the page comes back from the bfcache. Give the button `data-idle`
and `data-busy` if the wait is long enough to need words. **Never let the
attribute stand in for the server guard.**

## It is an in-flight request — disable while pending

Save, print cards, Ask AI. A second one is wasted rather than expensive, so
disabling the button for the duration is the whole fix. `public/site/editor.js`
already does this.

## It is a classroom control — guard nothing

The score stepper (`public/game/jeopardy.js`, `up`/`down`), say-it-again
(`public/game/bingo.js`), the vocab arrows. A teacher standing in front of
thirty children taps these fast **on purpose**: five taps on `up` is a score
correction, not a mistake. A lockout — even 300ms — makes the control feel
broken at the worst possible moment.

`disabled` in the game means *this has no meaning right now* (no words left to
call, this square is used), never *you clicked too fast*. Keep it that way.

The honest middle case is bingo's `next` (draw the next word), where a
double-tap burns a word. If that ever needs fixing, the answer is undo, not a
delay.
