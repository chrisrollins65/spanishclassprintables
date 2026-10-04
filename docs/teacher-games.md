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
5. For bingo only: presses **Print the 4×4 cards** and downloads a PDF of
   cards for the edited words.

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
- **"Change the words or questions"** sits beside the existing `moreGames()`
  link on both setup screens (`customizeGame` in `ui.js`), carrying the room
  code so the claim box on the other side is already filled in. Never on a
  teacher's own game, where it would point at itself: the shell marks those
  with `room.own`. It says nothing about money — what a buyer of that pack gets
  is free. The **"Create your own game"** link is a Phase 2 thing and goes up
  with the sales page.
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

### Things to update when Phase 1 ships — done

In the builder, which has one rule: change the site, change these —

- `GAME_LISTING_FACTS`: both kinds now carry a fact saying the questions are
  the teacher's to change with a free account, and that the printed pages stay
  as they are (bingo's adds that new cards can be printed).
- The How to Play pages (`*ComoJugar.html`) carry it as a tip, in both kinds.
- **Not** the site's ❓ panel, which is the rules read out to the class in
  Spanish — customizing is a teacher's business, like the score-overwrite
  control before it.
- TpT listings already live get the line on their next manual rewrite; the
  builder never rewrites a listing on its own.

### Printable cards — done

- **The PDF is rendered by Browsershot** from `resources/views/games/cards.blade.php`,
  which is laid out like the builder's own card sheets (`renderCardSet` and
  `cardStyles` in `gamePack.js`): six to a sheet, two across and three down,
  each bounded by the dashed line it is cut along. Two columns rather than
  three keeps the cell *width*, which is the dimension a long word needs. A
  48-card set renders in about six seconds locally.
- **No room code on a card, ever.** A card is photocopied, cut up and taken
  home; the site's address is worth printing on it and the code never is
  (`CARRIES_THE_CODE` in the builder says the same). A test asserts it.
- **The file is named after the cards themselves** — a short hash of the theme
  and the cards. Asking twice for an unedited game is a download rather than a
  second render, and an edit makes a new name rather than serving yesterday's
  words. Nothing has to be invalidated by hand.
- **Rendering is queued** onto the one worker, and the browser polls for the
  file. A whole Chrome for every teacher who presses print is exactly what the
  2GB droplet cannot take twice at once.
- **Chrome comes from Google's apt repository** (see the server repo's
  `server.yml`), not Ubuntu's chromium, which is a snap and will not run
  headless from a service. `puppeteer` is in the site's `package.json` because
  Browsershot's runner requires it, with `PUPPETEER_SKIP_DOWNLOAD` in `.npmrc`
  so no second copy of Chromium is downloaded on deploy;
  `BROWSERSHOT_CHROME_PATH` in the production `.env` names Chrome's path.

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
  - **Making a game is three steps, and the credit goes on the third.** The
    form (`store`) only reads what kind of practice the description asks for
    and redirects to a confirm screen (`confirm`); nothing exists and nothing
    is charged until `begin`. The deck type was always worked out before
    anything was written — this stops in between and SAYS so, because it is
    the one decision a teacher cannot discover any other way: "the subjunctive"
    is as true of a board of verb forms as of a board of whole sentences to
    translate, and a teacher who did not know the second exists used to find
    out after the credit was spent. The screen costs no extra model call (it
    carries `store`'s classification in the session, and reflashes so a refresh
    keeps it). `classified` rides along so `begin` can drop the classifier's
    stated reason when the teacher picks something else — a reason given for a
    deck nobody chose would be a lie on the edit screen.
    **The screen is skipped unless there is really something to choose.** It
    exists for one question — does the class recall items, or produce whole
    sentences — and that is the only choice a teacher cannot discover any other
    way. Words versus verb forms versus little words is not that question; it
    is our own distinction about how a pack is BUILT, which this form
    deliberately stopped asking about. So a **bingo never sees the screen**:
    every deck it can play asks the same thing of the class, and it would be
    shown three cards it has no basis to choose between on the way to what it
    asked for. The test is read off the decks (`answerIsOpen`, which is what
    makes an answer something the teacher judges rather than a bank face to
    match) and not hardcoded, so a quiz-only deck added later turns the screen
    on by itself. Fewer than two decks skips it too — the registry comes over
    the node bridge, which can be down, and a confirm screen with an empty
    picker is a dead end whose one button posts a `type` that is required and
    absent.
    **The confirm form carries `data-once`, and the create form still does.**
    The credit moved to the second press, so the guard had to move with it;
    without it the button posted once per click with nothing on screen to say
    the first press worked, which is exactly what invites the second. `once.js`
    also swaps the label to "Making it…", which is the only progress the page
    shows. Measured: three clicks in one tick, one submit reaches the server.
  - **A sentence keeps the article it starts with.** `TeacherGame::tidyItems`
    splits "la camisa" into an article and a face, which is right for a word
    and wrong for a whole sentence: "La enfermera trabaja en el hospital" came
    back as article "la" and a face beginning "enfermera", which is not what
    the board answers with and matches nothing in the bank. The deck decides,
    through `stripsArticles` on the payload — stored only when FALSE, because
    splitting is what every deck did before sentences existed and absent has to
    keep meaning "split". It surfaced as `Undefined array key "article"` (the
    old code read `$item['article'] ?: …`, which does not guard a missing key),
    and that crash is the only reason the corruption was never saved: every
    sentence generated before it began "Si", so the regex never matched.
  - **A game's kind is "jeopardy"; a deck type calls the same thing "quiz".**
    `decksFor()` does the rename, as `classifyPrompt` does on the way into the
    model, and the editor does in `deckTypeRow`. Without it nothing matches a
    quiz at all and every quiz silently falls back to vocabulary — which is
    exactly what happened, and what the confirm screen's own test caught.
  - **Switching the deck type of a game that is already WRITTEN is not free,
    and says so.** The clues stay as they are and are then read under another
    deck's rules: a vocabulary board switched to whole sentences keeps
    twenty-five one-word clues and passes every check, because the sentence
    deck exempts three rows from the bank test. Two things now happen.
    `dropOrphanedEnglish` removes `promptEn` from rows the new deck has no box
    for — the text does not go away by being hidden, and the game shows *Ver en
    inglés* whenever a clue HAS one, so a teacher would watch the English
    vanish from their screen and the class would read it mid-game. Then
    `switchedNote` says the clues were written for the other activity, and on a
    DRAFT offers to rewrite the board (a quiet save first, then `write`, which
    replaces the whole pack). On a published game it says what to fix by hand
    instead, because `write` is refused there and a button that cannot work is
    worse than none.
  - **Which rows are read aloud is a strip above the board, one switch per
    ROW** — never a switch on a square. A row is heard in every category or in
    none (`validateBoard` refuses anything else: the money is a promise about
    difficulty, so a row heard in two columns and shown in three breaks it), so
    a per-square control would mostly build boards the checks reject. Five
    controls rather than twenty-five, and an invalid board is unreachable; the
    square keeps a read-only **heard** badge.
  - **A switch is labelled by its money alone — "Row $400", never "dictation".**
    The first version printed each rung's form from the deck registry, which is
    a fact about the pack AS GENERATED and not about the board in front of the
    teacher: every clue here is editable, so the row the registry calls a
    dictation may have been rewritten into anything. The money is the one label
    that stays true, and it is already printed on all five squares of the row.
    To answer "which squares am I about to change?" the switch LIGHTS its row
    (`.row-lit`, from `data-value` on each cell) while the pointer or the
    keyboard is on it — the board scrolls sideways, so the question is fair
    even with the money on every square.
  - **It is folded shut, and the summary IS the setting** — "Read aloud, not
    shown: rows $400 and $500", or "nothing — every clue is on screen". Most
    teachers come to fix a clue and never open it, and open it costs a line on
    a laptop but a third of the screen on a phone, above the board they came
    for. Folded it costs one line everywhere, and the common case — wanting to
    KNOW which rows are heard rather than change them — needs no tap at all.
    It stays ABOVE the board rather than moving below it: below costs nothing
    to scroll past but hides it, since on a phone the board is some eight
    hundred pixels of squares and the only hint the feature exists is the
    `heard` badge ON a square, pointing at a control nowhere near it. Row
    lighting needs the switch and its row on screen together, too. The open
    state is held in `heardFoldOpen` for the same reason as
    `shownWhatAWordHolds` — an Ask AI reply re-renders the page, and a teacher
    who opened it should not find it shut underneath them.
  - **The strip has its own breakpoint at 660px, not the board's 720**, because
    660 is where the five switches were measured to run out of room; between
    the two they would otherwise sit in a ragged wrapped line. (It was 780
    while the strip carried its own inline label, before the fold's summary
    took that text — worth re-measuring if the switches ever change.)
    Below it they become a grid
    (`repeat(auto-fit, minmax(104px, 1fr))`) rather than a wrapped flex row, so
    a short last line stays in column, and each one is padded to a finger —
    the desktop layout's hit area is a 13px checkbox. Two columns on an
    ordinary phone is forced, not a preference: 390px leaves 280px of card and
    three columns of that are 88px against text needing 90. The alternative was
    shrinking the type, which is wrong for a control read at arm's length over
    a desk.
  - Turning one OFF warns instead of blocking: the rungs come in pairs —
    vocabulary's heard $400 is the $200 definition minus the reading — so
    showing it can leave two rows asking the same thing the same way. A teacher
    with no speakers may want exactly that. The warning is driven by
    `ladderForms` from the deck registry, paired with the board's own values by
    position, so it knows a sentence pack's dictation duplicates nothing. It is
    worded **"As written, $400 was $200 without the text… check the two rows
    don't now ask the same thing"** for the same reason the labels are generic:
    how the pack was written is all this can know, so it says that and asks the
    teacher to look, rather than asserting what the rows hold today.
    **The editor is the only place this is asked.** The quiz setup screen
    briefly offered the same choice per period (*Sin audio* / *Como está
    escrito* / *Todo en voz alta*) and it was removed: silencing a row at play
    time quietly collapses it onto another, in front of a class, with nothing
    on screen to say so, and it did not reach the printed script either. The
    one case that screen had to answer — a browser with no Spanish voice —
    needs no control, because `heard()` falls back to showing the clue.
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

- **Payments: Paddle**, as merchant of record, from the start. Stripe is
  cheaper per sale and already runs in the other apps here, but at this volume
  that is about **$0.20 a sale — $20 a year at 100 sales**, and what it buys
  back is the part that actually costs time:
  - Spain's filings are aggregate (modelo 130, modelo 303), but the books
    behind them are **per sale**: a *libro registro de facturas emitidas* with
    one numbered invoice per sale. As merchant of record Paddle is the seller
    to the teacher, so we issue **one invoice a month to Paddle** — twelve
    entries a year, one customer, one country.
  - Every non-US buyer otherwise raises a VAT question of its own (EU from the
    first sale, UK from the first sale). Paddle answers all of them.
  - **Verifactu** reaches autónomos in 2027. Software issuing our own invoices
    to consumers would have to comply; twelve invoices a month to one company
    do not have that problem.
  - Revisit only if the fee difference becomes real money — around $1–2k a
    month — and the bookkeeping is by then worth paying someone to do.
  - **Hand-rolled, not Laravel Cashier.** Cashier is built around
    subscriptions — billables, plans, swaps, grace periods — and a credit pack
    is a one-off charge, so nearly all of it would sit unused while still being
    a dependency to keep current. What we actually need Cashier does not do
    anyway: a credit ledger, and a refund that takes credits back and locks
    what they paid for. What it would have given us is a signed-webhook check
    (about twenty lines here) and a checkout helper (Paddle.js with a price
    id). Revisit only if subscriptions ever appear.
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

### How money becomes credits — built

- **`credit_entries` is a ledger of MONEY**, one row per movement, never
  edited or deleted: `purchase`, `refund`, `admin`. A unique index on
  (reason, reference) is what makes a webhook delivered twice credit once —
  Paddle retries anything it does not hear back from.
- **The balance is not the ledger's sum**, and `credit_units` is why: a credit
  is a row there pointing at the game it went on, so spending one writes
  nothing to the ledger. The sum of the deltas is what a teacher has BOUGHT.
  **Anything that shows a teacher or an admin "your credits" reads
  `availableCredits()`** — units with no game — and names credits held by
  drafts separately. The buy page and both admin screens once showed the
  ledger total, so a teacher who had spent all ten was told they had ten and
  then sent to the buy page when they pressed create. `creditsPurchased()` is
  named for what it is and is for reconciling with Paddle, nothing else.
- **The webhook is the only way money reaches the site** (`PaddleWebhookController`),
  whoever started it: a refund pressed in Paddle's dashboard and one started
  from our own admin screens both arrive here, so an account ends in the same
  state either way. The signature is checked against the raw body with
  `hash_equals`, and its timestamp must be recent — a signature is otherwise
  valid forever and a captured body could be replayed.
- **What a price is worth is decided here** (`config/paddle.php`), not read
  from the transaction, so a price edited in Paddle's dashboard cannot quietly
  change what a teacher receives.
- **A refund takes the credits back and locks what they paid for**, newest
  first — a teacher who bought ten and refunded one keeps the nine they have
  been using. A game claimed from a TpT pack was never paid for here
  (`paid_with` is null) and a refund never touches it.
- **Locked, never deleted.** Refunds get reversed, cards get half printed, and
  a teacher who pays again should find their own writing where they left it. A
  locked game stays on the list saying why, refuses to play, edit, save or
  print, and can still be removed by its owner.
- **Only an approved refund acts.** One Paddle is still reviewing has taken
  nothing from anybody, and locking a game mid-lesson over a decision that may
  not be made is the wrong way round.

### The back office — built

- **`/admin`, gated by `can:admin`** on the whole group. Who has it is a list
  of email addresses in `config/site.php` (from `ADMIN_EMAILS`), not a column:
  nothing a teacher can write to their own row can promote them, and taking the
  rights away is an environment change rather than a database write somebody
  has to remember.
- **A teacher's page** shows the ledger, every game (including ones they have
  removed), and the balance.
- **Credits can be given or taken by hand**, with a reason that is required and
  recorded — an apology, a test account, a sale whose webhook never arrived.
  Never for undoing a purchase: that owes money back, which is a refund.
- **A refund asks Paddle and waits for its word.** The admin screen calls
  Paddle's API; Paddle decides (a refund can sit in review) and tells us
  through the webhook, which is where credits come off and games get locked.
  Nothing writes a refund straight into the ledger, or the money and the
  account could disagree.
- **Locking and unlocking a game by hand** for the cases a refund does not
  cover: a chargeback, a mistake, a teacher who paid again.

### When a webhook is missed

- **`php artisan paddle:sync` reads Paddle and puts right whatever the webhook
  did not deliver** — a machine asleep, a deploy mid-delivery, a tunnel that
  was not running, a retry budget that ran out (Paddle gives up after ~3 days,
  and after 3 attempts in sandbox). It grants missing purchases and takes back
  missing refunds.
- **It is safe to run at any time**, and the scheduler runs it hourly. Every
  row it writes is keyed by the Paddle id that caused it and guarded by the
  ledger's unique index, so an hour in which nothing was missed does nothing at
  all. `--pretend` reports without writing; `--days=90` reaches as far back as
  Paddle keeps notifications.
- **This is why the ledger is shaped the way it is.** Credits are not a number
  on the user; they are rows keyed by what caused them, which is what makes
  Paddle the source of truth and this site a copy that can always be rebuilt
  from it. A balance column could only ever be *corrected*, never *derived*.
- **A sale with no `custom_data.user_id` is reported, not guessed at** — it is
  usually a test made in Paddle's own dashboard, and attaching it to a teacher
  would be inventing a fact.

### Setting Paddle up

Two settings in the dashboard have nothing to do with our code and stop the
checkout dead if they are missed. Both are per environment, so **production
needs them again**:

- **A default payment link** (Paddle → Checkout → Checkout settings). Without
  it, `Paddle.Checkout.open()` fails with "Something went wrong" and a 405 from
  `…/transaction-checkout` — an error that says nothing about the cause. This
  is what fixed it in sandbox: the checkout-domains API lists nothing even now,
  so the domain approval below changed nothing there.
- **Domain approval** (Paddle → Checkout → Website approval). Sandbox approves
  automatically and appears not to record domains at all. **Production will
  not**: `spanishclassprintables.com` has to be approved before a real teacher
  can buy anything.

Worth knowing: `Paddle.PricePreview()` keeps working when both of these are
wrong, so a pricing page that looks right proves nothing about the checkout.

- **The catalog is one product with two one-off prices** — "1 game" and
  "10 games" — under the tax category **saas**: a credit buys the use of an
  online tool, not an ebook, and the category decides the rate of tax Paddle
  charges. Their ids, the client token and the webhook secret live in each
  environment's `.env` (`.env.example` lists them); nothing but
  `config/paddle.php` reads them.
- **Sandbox and production are separate accounts** with their own keys, prices
  and webhook secret, so the ids differ per environment and none of them belong
  in the repo.
- **Webhooks cannot reach a `.test` domain**, so testing the whole loop locally
  needs a tunnel (ngrok or cloudflared) as the notification destination.
  Sandbox refunds approve themselves about ten minutes after they are asked
  for, which is the wait before a locked game proves the refund path works.

### Ask AI — built

A box under the words and another under the board, not a chat. The credit's AI
budget exists so a teacher can keep going until the game suits their class, and
a teacher who dislikes three of the thirty words should be able to say so
rather than regenerate and lose the twenty-seven they were happy with.

Why a box beat a chat, having considered both:

- **Context is better, not worse.** Every request carries the game exactly as
  it stands — the bank numbered, the board category by category — so the model
  sees what is on the teacher's screen, hand edits included. A chat carries its
  own memory of the game, which goes stale the moment a row is edited by hand.
- **It is cheaper per go.** A chat re-sends its history, including every
  earlier state of the bank, so the tenth tweak costs several times the first.
  Stateless edits cost the same each time, and the budget buys more of them.
- **The teacher can see what moved.** `applyItemEdit` returns which entries
  were rewritten and the editor outlines them. A chat returns a paragraph
  claiming what it did. Before printing 48 cards, an outline beats a claim.
- **It cannot quietly reword the rest.** Changes come back addressed by number
  and are merged into the teacher's own copy, so the untouched words are
  untouched by construction rather than by the model's restraint.

The one thing a chat would have given is a follow-up that refers back — "I
still don't like it, change it again", where by then the word being complained
about is already out of the bank and "it" points at nothing. That is handled by
`EditGame::historyOf`: the last three `{request, note}` pairs travel with the
next prompt. Bounded on purpose, so the tenth tweak costs what the first did.
The note is asked to name what changed *and what it used to be* ("Changed #12
from 'el granjero' to 'el ratón'"), which is what stops the next go handing
back a word the teacher has already turned down. Verified end to end against
the live model: `el granjero` → `el ratón` → (a bare "change it again") →
`las botas`, with the other twenty-nine words untouched.

Mechanically: `POST /my-games/{game}/ask` queues `EditGame`, the page polls
`/asking`, and both the prompt and the merge come from the builder through
`App\Ai\Builder` → `resources/scripts/game-ai.cjs`. An edit is allowed on a
published game as well as a draft — a teacher who finds a bad clue the night
before a lesson should be able to fix it — and because bingo cards are dealt
*from* the words, a published game has them re-dealt at once
(`TeacherGame::dealCardsNow`), or the next press of "Print the cards" would
render a set with nothing on it.

### What gets a PDF, and what does not — built

A TpT pack ships seven or so printed pages. A game made here offers two: the
bingo cards, and the quiz's team answer sheet. The line between them is not
"cards vs everything else", it is:

> **A blank page students physically hold gets a PDF. A page that reproduces
> the pack's writing does not.**

By that rule the caller sheet, the teacher script and the printed board stay
out. They are the no-projector fallback for a product sold to people who may
not use the website — and a teacher who *built* their game here is using the
website. The Play Online sheet is meaningless without a room code, How to Play
is replaced by the in-game ❓ panel, and the score sheet contradicts the site,
which keeps score on screen. It is also the cheap side of the line: both sheets
we do render need the payload's structure only, not `brand.js` page furniture,
so neither is a second home for the builder's page pipeline.

The two are offered on **different** rules, and the difference is what is
printed on them:

| | Made here | Claimed from TpT |
| --- | --- | --- |
| Bingo cards | Always — they exist nowhere else | Once a word changes: the words are printed ON the card, so the set in their download is now wrong |
| Team answer sheet | Always | **Never.** The sheet is blank, so a renamed category does not make their copy wrong |

`TeacherGame::cardsAreWorthPrinting()` and `answerSheetIsAvailable()` hold those
two rules. The cards compare against the room they were claimed from rather
than tracking a flag, so changing a word back correctly stops offering them.

The answer sheet is a port of the builder's `jeopardyHoja.html`, and keeps its
two deliberate blanks: no clue text, and **no final category** — that is
announced when the board is empty, and a team reading it off their own sheet
all game has had the one thing the bet is meant to turn on. It is not a
convenience page either: the final wager asks every team to write a bet down
before the clue is shown, so without it that round runs on scrap paper.

### The public face — built

- `/pricing`, `/terms`, `/privacy`, `/refunds`, linked from both footers.
  Paddle's review checks the live site for these before approving an account,
  so they go up before applying. They share one layout, and the operator's
  details come from `config('site.legal')` rather than being written into four
  files — a move changes one line.
- The homepage's **Make a game** section, which is the only thing on the site
  that tells a visitor the product exists. Before it, the pricing page was
  reachable by one footer link and nothing else: everything was built and
  nobody could find it.
- The terms and the refund policy are linked from the **buy page** as well as
  the footer. That is the placement that matters — a consumer is entitled to
  them before they are bound, and someone who cannot find how to ask for a
  refund files a chargeback instead, which costs more than the refund and
  counts against us with Paddle.

Naming the operator is not optional. Spain's LSSI (Ley 34/2002, art. 10) wants
a commercial site to make its operator findable, so one block at the foot of
each document does that and the brand is used everywhere else. The NIF is left
out deliberately — for an autónomo it is essentially a personal ID number, and
`legal-details.blade.php` renders nothing at all rather than a gap when
`LEGAL_NIF` is unset.

### Before it can take money

- **Paddle dashboard, by hand**: domain approval, the default payment link, the
  client-side token, and an API key for the app with exactly
  `Transactions: Read`, `Adjustments: Read`, `Adjustments: Write` — the only
  three the code uses. The webhook controller verifies an HMAC and never calls
  the API; `paddle:sync` reads; the admin Refund button writes an adjustment.
- **Ansible first, then deploy.** The playbook installs Node 22, Chrome, the
  fonts Chrome draws Spanish with, and the supervisor worker. A deploy without
  it has no PDFs and no AI.
- **Production `.env`**: `QUEUE_CONNECTION=database`,
  `DB_QUEUE_RETRY_AFTER=600`, `BROWSERSHOT_CHROME_PATH=/usr/bin/google-chrome`,
  `BROWSERSHOT_NO_SANDBOX=true`, `CARDS_NODE_BINARY=/usr/bin/node`, the AI keys,
  and the live Paddle ids. `EnvExampleTest` keeps `.env.example` honest about
  what this app reads, so that file is the list.
- **One real purchase, end to end**, before telling anyone: buy a single credit,
  make a game, print it, then refund it from the admin screen. That is the only
  thing that exercises the webhook, the credit grant, the queue worker, Chrome
  and `revokeCredits` together.

A model choice for the site's generation is settled: Gemini 3 Flash writes,
GPT-5.4 is the fallback, and the cost is measured from token usage rather than
assumed (`App\Ai\Writer`).

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

### Letting a substitute run the game

A teacher is out and wants a sub — or a colleague — to play their game with
the class, without handing over an account password. Today there is no way
to do it: a teacher's game is reachable only from their own login.

The shape that fits what teachers already know is a **short-lived play code**,
the same mental model as the codes printed in the TpT packs. Tap "Share for a
sub", get a code, the sub enters it and plays.

**The trap to design around first, not bolt on afterwards.** `claim()` looks up
`Room::find($code)`. If a shared game were published as a Room to give it a
code, another teacher could claim it into their own account — a free game, and
the sharing feature becomes a free-games pipeline. A share code therefore has
to be a different kind of object from a room, or be explicitly unclaimable in
the way `site.unclaimable_codes` makes the demo games unclaimable.

Scope it narrowly:

- **Play only.** No editor, no printables, no claiming. Seeing the answers is
  not the leak — that is true of every TpT code already sold.
- **Expires**, a week or so, which covers an absence.
- **Revocable**, and **one active share per game**, so nobody mints dozens.

Worth being honest about how much this protects: a teacher who wants to share
can already project their screen or hand over a laptop, and TpT codes circulate
freely by design. The realistic risk is a link posted to a teacher group, and
expiry plus play-only handles that — anyone who wants to *keep* the game still
needs credits.
