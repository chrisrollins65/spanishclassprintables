/* The bridge between PHP and the packet builder's prompt machinery.
 *
 * The builder writes every prompt (resources/scripts/builder/ai/prompts.js,
 * copied by its scripts/sync-site-shared.js) and owns how an AI edit is merged
 * back in (gameEdits.js). This is how PHP reaches both. Porting either to PHP
 * would mean two sets of rules for the same product — and the merge in
 * particular decides what a teacher is left with, so two of it would sooner or
 * later disagree about the same reply.
 *
 * Reads one JSON request on stdin, writes one JSON reply on stdout. Three
 * modes, because the model call itself belongs to PHP, which holds the keys
 * and the teacher's budget:
 *
 *   generate  {kind, topic}                        -> {prompt}
 *   edit      {kind, topic, payload, request, history} -> {prompt}
 *   merge     {kind, payload, reply}               -> {payload, changed, note, ...}
 *
 * .cjs because this package is "type": "module".
 */
const path = require('path');

const builder = path.join(__dirname, 'builder');
const prompts = require(path.join(builder, 'ai', 'prompts.js'));
const { applyItemEdit, applyBoardEdit } = require(path.join(builder, 'gameEdits.js'));
const { deckType, DECK_TYPES } = require(path.join(builder, 'deckTypes.js'));

/* How many words a bingo bank is written with.
 *
 * The same number the builder's own packs use, so a teacher's game is the size
 * of one that was bought. A 4x4 card needs 16 and the biggest needs more than
 * it prints, so the bank is deliberately larger than any one card.
 */
const BANK_SIZE = 30;

const board = payload => (payload.games && payload.games.jeopardy) || {};
const items = payload => (Array.isArray(payload.items) ? payload.items : []);

/* What KIND of pack this is, from the room the teacher is editing.
 *
 * A pack of verb forms is held to different writing rules than a pack of words:
 * its entries have a formula and no article or definition, and its board climbs
 * a different ladder. Without this every edit was judged by vocabulary's rules,
 * which for a grammar pack means a reply shaped wrong — an article invented for
 * a verb form, a definition demanded for something that cannot be defined — and
 * the merge writes it straight back into the teacher's game.
 *
 * A room published before deck types existed carries no `type`, and absent means
 * vocabulary, which is what all of them are. An UNKNOWN type throws, and PHP
 * turns that into an error the teacher sees, rather than silently editing their
 * grammar pack by the wrong book.
 */
const typeOf = payload => deckType(payload && payload.type);

/* How many model calls this game takes, and what each one writes.
 *
 * PHP asks before it writes anything, so it never needs to know what a deck type
 * is: it runs the steps it is handed, in order, charging for each. All the type
 * knowledge stays here, next to the registry, which is what keeps a new deck
 * type from needing a change on the PHP side at all.
 *
 * Vocabulary writes a quiz in ONE call, because buildQuizGamePrompt asks for the
 * words and the board over them in the same reply. A grammar deck cannot: that
 * prompt inlines a reduced entry — a face, an article and an English — and a verb
 * form needs its formula, which that shape has no room for. So a grammar quiz is
 * written the way the builder writes one, bank first and board over it, which
 * costs a second call and reuses two prompts that already exist rather than
 * inventing a third per type.
 *
 * Bingo is always one call: its bank IS the game.
 */
function planSteps({ kind, payload }) {
  const type = typeOf(payload);

  /* A type may not support this game at all.
   *
   * A deck of whole sentences is quiz-only: a sentence does not go in a bingo
   * square. The classifier is told the kind and never offers one it cannot
   * build, so reaching here means the payload was assembled some other way —
   * worth failing loudly rather than printing a card nobody can read.
   */
  const played = type.games || ['bingo', 'quiz'];
  const asked = kind === 'jeopardy' ? 'quiz' : kind;
  if (!played.includes(asked)) {
    throw new Error(`deck type "${type.id}" cannot be played as ${kind}; it supports ${played.join(' and ')}`);
  }

  if (kind === 'bingo') return ['all'];
  if (kind === 'jeopardy') return type.quizFromTopic ? ['all'] : ['bank', 'board'];
  throw new Error(`no plan for a ${kind} game`);
}

function generatePrompt({ kind, topic, payload, step }) {
  const type = typeOf(payload);

  if (step === 'bank' || kind === 'bingo') {
    return prompts.buildGameItemsPrompt(topic, BANK_SIZE, type);
  }

  if (step === 'board') {
    // Written over the bank the previous step produced, which PHP hands back on
    // the payload — the same shape buildJeopardyPrompt takes in the builder.
    const bank = items(payload);
    if (!bank.length) throw new Error('the board step needs the bank the first step wrote');
    return prompts.buildJeopardyPrompt(topic, bank, 5, 5, type);
  }

  if (kind === 'jeopardy') return prompts.buildQuizGamePrompt(topic, 5, 5, type);
  throw new Error(`no prompt for a ${kind} game`);
}

/* The deck types a teacher may choose from, for the create form.
 *
 * Served from the registry rather than listed in PHP or in a Blade template, so
 * adding a type puts it on the form without anyone remembering to. Only the id
 * and the label travel: the writing rules are the product and stay here.
 */
/* The prompt that decides which deck a teacher's description needs.
 *
 * A mode of its own rather than part of `generate`, because PHP holds the model
 * keys: it asks for the prompt, makes the call, and checks the answer against
 * the ids it already has from `types`. An unknown or missing answer is not an
 * error — the site falls back to vocabulary and shows the teacher what it chose.
 */
function classifyPrompt({ topic, describe, kind }) {
  // The kind is passed so a bingo game is never offered a quiz-only deck.
  const asked = kind === 'jeopardy' ? 'quiz' : String(kind || '');
  return prompts.buildDeckTypePrompt(String(topic || ''), String(describe || ''), asked);
}

function listTypes() {
  return {
    types: Object.values(DECK_TYPES).map(t => ({
      id: t.id, label: t.label, blurb: t.blurb || '',
      // So a new game is born with the clue types its deck actually carries,
      // instead of PHP keeping its own copy of vocabulary's three.
      clueTypes: t.clueTypes,
      /* Which games this deck can be played as. The classifier is already
       * filtered by kind, but the pickers are not: without this the editor
       * offers a bingo game a quiz-only deck, and the confirm screen offers it
       * before the game exists. Absent on a deck means both. */
      games: t.games || ['bingo', 'quiz'],
      /* What the editor's checks need to judge a board the same way the builder
       * does: whether a leading article is part of an answer, and which rows
       * answer with something BUILT from a bank entry rather than copied from
       * it. Without these the sentence deck is told every other row is "not in
       * the word bank", which is true and not a problem. */
      stripsArticles: t.stripsArticles !== false,
      // What the deck tells the ROOM: see TeacherGame::blank.
      answerIsOpen: !!t.answerIsOpen,
      /* The values whose rung carries an English version of the clue. The
       * default is every row but the cheapest, which is already English; a
       * sentence board overrides it per rung, because there only the dictation
       * has an English worth showing. */
      promptEnValues: (t.ladder || [])
        .map((rung, i) => {
          const has = rung.promptEn === undefined ? i !== 0 : rung.promptEn;
          return has ? (i + 1) * 100 : null;
        })
        .filter(Boolean),
      bankExemptValues: (t.ladder || [])
        .map((rung, i) => (rung.fromBank === false ? (i + 1) * 100 : null))
        .filter(Boolean),
      /* What each rung ASKS FOR, in rung order — "gap", "definition",
       * "dictation". A list of forms rather than of values like the two above,
       * because the editor pairs it with the board's OWN values by position:
       * it is what the heard strip needs to warn that turning a row off would
       * leave two rows asking the same thing in the same way, and that is a
       * question about the form, not the money. */
      ladderForms: (t.ladder || []).map(rung => rung.form || ''),
    })),
  };
}

/* The edit prompts take the game as it stands, which is the whole reason this
 * runs through node rather than filling in a template: the bank is numbered
 * into the prompt so the model can answer "change #12", and the board goes in
 * category by category. */
function editPrompt({ kind, topic, payload, request, history }) {
  const type = typeOf(payload);

  if (kind === 'bingo') {
    return prompts.buildGameItemsEditPrompt(topic, items(payload), request, history, type);
  }

  if (kind === 'jeopardy') {
    const game = board(payload);

    return prompts.buildBoardEditPrompt(
      topic, items(payload), game.categories || [], game.final || null, request, history, type,
    );
  }

  throw new Error(`no edit prompt for a ${kind} game`);
}

/* Merge what the model sent back into the game.
 *
 * Only what it was asked to change comes back, so everything else is the
 * teacher's own copy carried over untouched — hand edits included. `changed`
 * travels with it so the editor can point at what moved: an edit nobody can
 * find is an edit nobody reviews.
 */
function merge({ kind, payload, reply }) {
  const next = JSON.parse(JSON.stringify(payload));
  const edit = reply && typeof reply === 'object' ? reply : {};

  if (kind === 'bingo') {
    const result = applyItemEdit(items(payload), edit);
    next.items = result.items;

    // The cards are dealt from the words, so a changed bank invalidates any
    // set already dealt. Publishing deals them again.
    if (next.games && next.games.bingo) delete next.games.bingo.cards;

    return { payload: next, changed: result.changed, removed: result.removed, note: edit.note || '' };
  }

  if (kind === 'jeopardy') {
    const game = board(payload);
    const result = applyBoardEdit(game.categories || [], game.final || null, edit);
    next.games.jeopardy.categories = result.categories;
    next.games.jeopardy.final = result.final;

    return {
      payload: next,
      changed: result.changed,
      renamed: result.renamed,
      finalChanged: result.finalChanged,
      note: edit.note || '',
    };
  }

  throw new Error(`cannot merge an edit into a ${kind} game`);
}

const MODES = {
  plan: r => ({ steps: planSteps(r) }),
  classify: r => ({ prompt: classifyPrompt(r) }),
  types: listTypes,
  generate: r => ({ prompt: generatePrompt(r) }),
  edit: r => ({ prompt: editPrompt(r) }),
  merge,
};

try {
  const request = JSON.parse(require('fs').readFileSync(0, 'utf8'));
  const run = MODES[request.mode];
  if (!run) throw new Error(`unknown mode "${request.mode}"`);

  process.stdout.write(JSON.stringify(run(request)));
} catch (e) {
  // Ours to read in a log, never the teacher's to read on a page.
  process.stderr.write(String((e && e.message) || e));
  process.exit(1);
}
