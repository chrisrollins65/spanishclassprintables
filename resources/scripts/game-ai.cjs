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

/* How many words a bingo bank is written with.
 *
 * The same number the builder's own packs use, so a teacher's game is the size
 * of one that was bought. A 4x4 card needs 16 and the biggest needs more than
 * it prints, so the bank is deliberately larger than any one card.
 */
const BANK_SIZE = 30;

const board = payload => (payload.games && payload.games.jeopardy) || {};
const items = payload => (Array.isArray(payload.items) ? payload.items : []);

function generatePrompt({ kind, topic }) {
  if (kind === 'bingo') return prompts.buildGameItemsPrompt(topic, BANK_SIZE);
  if (kind === 'jeopardy') return prompts.buildQuizGamePrompt(topic);
  throw new Error(`no prompt for a ${kind} game`);
}

/* The edit prompts take the game as it stands, which is the whole reason this
 * runs through node rather than filling in a template: the bank is numbered
 * into the prompt so the model can answer "change #12", and the board goes in
 * category by category. */
function editPrompt({ kind, topic, payload, request, history }) {
  if (kind === 'bingo') {
    return prompts.buildGameItemsEditPrompt(topic, items(payload), request, history);
  }

  if (kind === 'jeopardy') {
    const game = board(payload);

    return prompts.buildBoardEditPrompt(
      topic, items(payload), game.categories || [], game.final || null, request, history,
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

const MODES = { generate: r => ({ prompt: generatePrompt(r) }), edit: r => ({ prompt: editPrompt(r) }), merge };

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
