# Teacher games: accounts, editing, and create-your-own

The plan for letting teachers edit the games they bought on TpT, and later buy
games they create themselves with AI. Decisions already made are recorded with
their reasons so they are not re-argued; open questions are listed at the end
of each phase.

## Why

- Teachers ask what is on a game before and after buying ("what questions are
  on the Jeopardy game?"). Being able to change it answers that better than any
  preview.
- A TpT buyer belongs to TpT: no email, no way back to them. An account here is
  a teacher we can reach again.
- Create-your-own is a revenue stream that does not depend on TpT.

## Ground rules that hold across every phase

- **Published rooms stay exactly as they are.** The `rooms` table, `/j/{code}`,
  and the rule that a room is never deleted are untouched. A teacher's game is
  a *copy* in its own table, so nothing a teacher does can change or break a
  code printed on a product someone else bought.
- **Students never log in.** The game on the projector is the teacher's screen.
  A teacher's own game opens only for its owner, logged in. TpT rooms keep
  opening with just the code.
- **One game shell.** `public/game` plays teacher games too; it only needs to be
  told where to fetch the payload from. No second copy of bingo.js/jeopardy.js.
- **No price inside a game.** The link out of a game says "Create your own
  game" and goes to a page of ours; the price is on that page, not on the game
  screen and never in a TpT listing.
- **The builder (`tptwsbuilder`) stays the source of the rules.** Validators,
  card generation and prompts live there. Where the site needs them it takes a
  copy through one sync step (see Phase 1, *Shared code*), never a rewrite.

## Phase 1 — Customize a game you bought (free, no AI, no payments)

The smallest thing that tests whether teachers actually edit, and something the
TpT listings can advertise the day it ships.

### What the teacher does

1. Clicks **Customize this game** (teacher menu in the game, or the homepage),
   signs up or logs in.
2. Enters the game's room code (printed on the Play Online sheet). The site
   makes a private copy of that room's payload under their account.
3. Edits it in the browser: the word bank (face, article, English, gap sentence,
   definition) and, for the quiz, the board and the final wager.
4. Plays it from **My games**.
5. For bingo only: presses **Make new cards** and downloads a PDF of cards for
   the edited words. (Not built yet — the website's own cards already follow an
   edit; this is the printable.)

### Decided

- **Claiming is by room code, with no proof of purchase.** TpT gives sellers no
  API to check buyers. Anyone with the code can already play the game and see
  every answer, so an editable copy gives away little. We count claims per code
  (`teacher_games.source_code`) and act only if one code is clearly passed
  around.
- **The demo room cannot be claimed.** It is free to everyone; a claimable copy
  of it would be a free game once Phase 2 sells them.
- **Edits only apply to the website.** The TpT printables stay as they were;
  a teacher who prefers paper uses the pages they bought. So Phase 1 generates
  **bingo cards only**: the cards are physical even when the website calls, and
  the site is the caller, so no caller sheet. The **quiz needs no paper**: the
  site shows every clue and reads the audio ones itself (`speakAt` in
  `jeopardy.js`). The editor says this plainly: "Your edits play on the website.
  Your TpT printables stay as they were."
- **New cards replace the room's cards.** Bingo checks a winning card against
  the cards in the payload (`game.cards`, looked up by id), so regenerating the
  printout and the payload happen together, seeded from the teacher game's id
  so a reprint is identical.
- **PDFs are rendered with Browsershot** (headless Chrome, driven from Laravel),
  reusing the builder's card template. Chrome uses roughly 150–300 MB for the
  few seconds a render takes, then exits. On a 2 GB droplet shared with two
  other sites: renders go through a queue with **one worker** so two can never
  run at once, and the droplet gets a swap file as a safety margin. Check
  `free -m` first.
- **"Create your own game" link** sits beside the existing `moreGames()` link
  (`ui.js`) and on the demo room. In Phase 1 it goes to a "coming soon / get
  notified" page. Not built yet.
- **Auth is Laravel Fortify.** Accounts will hold paid credits, so the auth back
  end is the maintained, widely reviewed one — login throttling, session
  regeneration, reset and verification tokens — rather than our own copy of it.
  Fortify has no views; the pages are ours, in the site's style. Card details
  never reach this server either way: Paddle's checkout takes them. Two-factor
  and passkeys are off until accounts hold credits; turning one on needs its
  migration back (`php artisan fortify:install` writes it) and its own pages.
- **The two account forms that email a stranger** — sign-up and forgot-password
  — are capped per connection (`ThrottleAccountMail`). Fortify throttles logins
  but not those, and a script looping on either burns the domain's sending
  reputation, which every reset link then depends on.
- **`spatie/browsershot`** is approved for the card PDFs; production needs Node
  and Chrome on the droplet.

### Data — built

- `users` — the default Laravel table, with Fortify on top.
- `teacher_games`
  - `id` (ULID — it goes in URLs, can't be guessed, and can't be mistaken for a
    room code)
  - `user_id`
  - `theme`
  - `games` — which games the payload holds ("bingo", "jeopardy"), comma
    separated, so the list page never loads a payload to say what a game is
  - `source_code` (nullable: the room it was claimed from; null for a game
    created in Phase 2, and how claims per code are counted)
  - `payload` (longText, a room payload whose `code` is this game's id)
  - timestamps, soft deletes (a teacher removing the wrong game can be helped)

### Routes — built

- Fortify's own: `/login`, `/register`, `/logout`, `/forgot-password`,
  `/reset-password`, `/email/verify`. `/account` is ours, and needs only a
  login, not a confirmed address: a teacher who mistyped their email has to be
  able to reach the page that fixes it. Everything else needs `verified` too.
  These pages are English — the teacher is the user; the game screens stay
  Spanish.
- `GET /my-games` — the list · `POST /my-games/claim` — claim by code
  (10/minute) · `GET /my-games/{game}/edit` · `PUT /my-games/{game}` — save ·
  `GET /my-games/{game}/play` — the room shell · `GET
  /my-games/{game}/payload.json` — owner only, never cached ·
  `DELETE /my-games/{game}`.
- Still to come: `POST /my-games/{game}/cards` and `GET
  /my-games/{game}/cards.pdf`.

**No policy class.** Every game is looked up through the logged-in teacher's own
relation, so another teacher's game is a 404 rather than a 403 — which also
says nothing about whether that id exists.

### Shell changes — done

- `app.js` reads `data-payload-url` on `#app` when there is one, and otherwise
  takes the room code out of the URL as before. Nothing else in the games knows
  whose game it is. `RoomController::shell()` serves both, so the two cannot
  drift.
- The service worker (`room-sw.js`) is scoped to `/j`. A teacher game plays
  under `/my-games`, so it is not kept offline in Phase 1. Revisit if teachers
  ask.

### Shared code — done

`public/game/shared/` holds generated copies of the builder's `rng.js`,
`bingoCards.js` and `jeopardyBoard.js`, written by the builder's
`scripts/sync-site-shared.js` (run it after changing any of those three). Each
copy carries a header naming its source and commit, so drift shows up in a
diff, and each is wrapped to load as a plain browser script (`window.SharedRng`,
`window.SharedBingoCards`, `window.SharedJeopardyBoard`) as well as in Node.

Note for anyone testing them in Node here: this package is `"type": "module"`,
so `require()` treats them as ESM and returns nothing. Run them the way the
browser does (`vm.runInContext`) instead.

The browser validates while the teacher types. The server checks structure and
size only (valid JSON, expected keys, `MAX_PAYLOAD_BYTES`) — a malformed payload
can only break the owner's own game, so a second PHP copy of every writing rule
is not worth its drift.

### Things to update when Phase 1 ships

In the builder, which has one rule: change the site, change these —

- `GAME_LISTING_FACTS`: the games are customizable once you make a free account.
- The How to Play pages (`*ComoJugar.html`) and the site's ❓ panel.
- TpT listings already live get the line on their next manual rewrite; the
  builder never rewrites a listing on its own.

### The editor — done

- **It edits the payload itself** — the same object the shell plays — and PUTs
  it whole. Sections appear according to what the payload holds: the word bank,
  the quiz board with its final wager, and a note for bingo.
- **The browser judges the writing, the server judges the shape.** The editor
  runs `validateDeck` and `validateBoard` as the teacher types and lists what
  they say, but never blocks a save: they are advice for a teacher, not rules to
  enforce on their own game. The server checks only that the payload is the
  right shape and within the room size limit, and forces `code` to the game's
  own id whatever the browser sent. **It saves the whole payload, never the
  validated subset**: a payload carries more than the editor shows —
  `clueTypes`, which decides how bingo calls a word, `reference`, `grades` — and
  keeping only the validated keys silently dropped every one of them on the
  first save. Validation says what must be present, not what may be kept.
- **Bingo cards are rebuilt in the browser on save, and only when a word
  changed**, seeded from the game's id so pressing save twice cannot reshuffle
  a set a teacher has printed. The editor says plainly that the cards in their
  TpT download still carry the words they were printed with.
- **It is laid out like the builder's own editor** (`src/public/games.js`),
  because the same two jobs are being done. The words are **one line each**
  (number, article, Spanish, English) and open on a chevron for the sentence and
  definition, with a switch to open them all: thirty open cards is several
  screens of scrolling when a teacher only wants to find one word. **A word
  opens three ways** — the chevron that leads its line, a click anywhere in the
  row, or typing in one of its fields — and **only one is open at a time**,
  since two half-open rows in a list of thirty is how a teacher loses their
  place. A switch opens them all for someone reading right through. (The
  chevron's own focus is excluded from the focus rule: it focuses on the way
  down and clicks on the way up, so opening on focus left the click to close it
  again and the row never opened.) The board is
  **the board** — a column per category, cheapest clue at the top — so a teacher
  reads it the way the class will see it. Narrower than 720px it scrolls
  sideways a column at a time, with a picker above it to jump.
- **A clue has three fields, not six.** The clue, its English (which the teacher
  can reveal mid-game and which is printed on the script), and the answer. The
  cheapest row has no English box, because that row *is* the English question.
  The three Spanish/English pairs a teacher sees under a word belong to the word
  list, not the board: they are what bingo calls out.
- **"Heard" is a badge, not a switch.** A row is read aloud in every category or
  in none — the value has to mean the same thing across the board — so a single
  square can't be toggled.
- **A quiz pack has no word list; the words are in the squares.** The quiz
  shows a class only two things about a word — the word and its English
  (`openVocab` in `ui.js`) — and the word is already on the board, as a
  square's answer. So the English sits under the answer, in the square, and
  editing it edits that word in the bank. That is what the class reads behind
  the **Vocabulario** button, so it has to be editable; it does not need thirty
  rows of sentences and definitions, which belong to bingo.
  - The article sits beside the answer, not beside the English: it is Spanish,
    and "la" in front of "Christmas" reads as a mistake.
  - Removing a word asks first, in the row rather than in a browser dialog,
    which on a phone covers the word it is asking about. Pressing the bin on a
    collapsed word must not open it: buttons are excluded from the rule that
    focusing a field opens its row.
  - The board carries **a second scrollbar above it** on desktop — an empty
    strip whose scrollbar drives the board's, the usual wide-table trick —
    because the real one is at the foot of a board taller than the screen.
    Whichever of the two the pointer is over is the one driving; mirroring both
    ways on every scroll event has them chasing each other. It hides when the
    board fits.
  - The phone's category picker sets the strip's own `scrollLeft` rather than
    calling `scrollIntoView`, which also scrolls the page vertically to reach
    the column and left the phone jumping about on every pick.
  - Every box in a square needs `min-width: 0` and a `minmax(0, 1fr)` column. A
    text input will not shrink below its default twenty characters, so without
    them each one hangs out past the side of its square.
  - Retyping an answer moves the English with it, and an answer the bank has
    never heard of offers **+ add “…” to the vocabulary list** — otherwise the
    list the class reviews quietly loses a word the board asks for.
  - The few bank words no square uses (usually four or five, since a 5×5 board
    plus a final needs 26 of 30) are the one list that remains, folded shut at
    the foot of the page.
  - **A missing final wager can be added.** Boards built before the final
    existed have none and simply end on the board; the editor offers to write
    one rather than leaving a teacher to rebuy the pack. The builder's own
    editor says "regenerate it, or ask AI" instead, because it has a model to
    hand; the site in Phase 1 does not.
  - **A bingo pack still opens on its words**: they are the game, the cards are
    built from them, and each carries the three ways it can be called out. A
    pack holding both games keeps the word list and drops the English from the
    squares — two boxes for one value drift apart on screen.
- **The list says which games a pack holds** ("Bingo and team quiz"), and the
  word list says which game it feeds: a teacher who bought only the quiz asks
  what a word list is doing there. A pack sold on TpT holds one game; a room can
  hold both.
- **Not editable in Phase 1:** adding or removing categories and clues (the
  board is a fixed grid, and a ragged one has no sensible screen), and anything
  about how bingo is called. Words can be added and removed.

### Open questions

- **Queue worker in production**: is one running? `.env.example` has
  `QUEUE_CONNECTION=sync`. A cron-run `queue:work --stop-when-empty` every
  minute avoids a resident process.
- Claim limit per code: none for now; decide a number if claims spike.

## Phase 2 — Create your own game (paid, with AI)

### What the teacher does

1. Lands on the sales page (from "Create your own game", the homepage, search,
   Pinterest — never from a TpT listing).
2. Buys one game ($4.50) or ten ($30). A purchase is **credits** on the account.
3. Types a topic, picks bingo or quiz, and AI writes the bank (and the board).
4. Reviews and edits it in the Phase 1 editor, with *Ask AI* for targeted
   changes.
5. Plays it on the website and downloads its PDFs.

### Decided

- **Payments: Paddle**, as merchant of record. It is the legal seller, handles
  sales tax and VAT everywhere, and issues the buyer's invoice; we invoice
  Paddle once a month. Chosen over Creem, which is cheaper per sale but charges
  at least €7 per payout, twice a month — more than it saves at our volume.
  **Laravel Cashier (Paddle)** is the official integration.
- **Payouts in EUR by SEPA to the CaixaBank account.** Free from Paddle; Paddle
  may add up to 1.5% converting USD to EUR. A USD payout to a European bank
  goes by SWIFT for a flat $15, so it is never cheaper at our volume.
- **Pricing: $4.50 a game, $30 for ten.** Cheaper than a finished TpT pack
  because the teacher supplies the effort. Paddle's fee is 5% + 50¢, so the
  fixed part takes ~16% of a single game and ~7% of the bundle — the sales
  page leads with the bundle.
- **A credit is spent when a game is first generated**, and is refundable until
  then. That is the refund policy Paddle's review asks to see.
- **AI budget per game ≈ $0.50**, measured, not estimated: each call's token
  usage from the API response × a price table per model. Most teachers never
  see it: the meter appears only past 60% used. Before an action that would go
  over, say so rather than failing halfway. A site-wide daily spending ceiling
  sits behind it.
- **Claimed TpT games get no AI.** Editing what you bought is manual.
- **PDFs for a created game**: bingo cards and the caller sheet; for the quiz,
  the teacher script (every clue and answer) and the printed board. They print
  the game's own link and QR, which open only for the owner, logged in — so
  sharing the paper shares nothing playable.
- **Rules come from the builder.** The prompts (`src/ai/prompts.js`),
  validators and audits are the product's writing rules; the site uses copies
  synced the same way as Phase 1's shared code, run as a short-lived `node`
  process per call (no resident service on the droplet). A PHP rewrite of
  1,400 lines of prompts would drift the first week.

### Also needed

- Legal pages: terms, privacy policy, refund policy, a public pricing page —
  Paddle's review checks the live site for them, so they go up before applying.
- Credit ledger table (purchases and spends, never a bare balance column), fed
  by Paddle webhooks.
- A model choice for the site's generation: cost per game, not only quality.

### Open questions (business, not code)

- Gestor: invoicing Paddle (a UK company) monthly for a service.
- Paddle, in writing: that a $4.50 product is billed at the standard 5% + 50¢,
  and what USD→EUR margin they actually apply.
- Check CaixaBank charges nothing for incoming SEPA on the account used.

## Later, if the first two phases earn it

- Email list from accounts (opt-in at sign-up) for new games and seasonal packs.
- Duplicate a game, share a game with a colleague who also has an account.
- Offline play for teacher games (service worker scope).
- More kinds of game on the same editor.
