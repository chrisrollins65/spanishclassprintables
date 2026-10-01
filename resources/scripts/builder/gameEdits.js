/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/gameEdits.js (commit 1e0f315) by its
 * scripts/sync-site-shared.js. Edit it there and run that script again; an
 * edit made here is lost the next time anyone does.
 */
/* Merging an "Ask AI" edit into the game pack's bank or board.
 *
 * The edit prompts ask for the changes only, addressed by number (see
 * buildGameItemsEditPrompt), so everything the model was not asked to touch is
 * carried over from what the user already has rather than re-typed by it. Pure:
 * no I/O, no model calls.
 *
 * Both return `changed` — which entries in the RESULT were written by the
 * model — so the page can point at them. An edit nobody can find is an edit
 * nobody reviews.
 */

const ITEM_FIELDS = ['face', 'article', 'en', 'sentence', 'sentenceEn', 'definition', 'definitionEn'];

// Only the fields an entry is made of, so a stray "n" or a model's commentary
// field never reaches the room data. `base` keeps anything else the entry
// already carried.
function toItem(written, base = {}) {
  const item = { ...base };
  ITEM_FIELDS.forEach(f => {
    const value = typeof written[f] === 'string' ? written[f].trim() : '';
    if (value) item[f] = value;
    else delete item[f];
  });
  return item;
}

function itemIndex(n, length) {
  const i = Number(n) - 1;
  return Number.isInteger(i) && i >= 0 && i < length ? i : -1;
}

/* Changes and removals are both numbered against the bank the model was
 * shown, so they are applied against that same bank before anything moves:
 * removing #3 first would make "change #7" land on the old #8. */
function applyItemEdit(items, edit) {
  // A model that ignores the format and hands back the whole bank still gets
  // its edit applied, with the changes found by comparing.
  if (Array.isArray(edit.items) && !edit.changes && !edit.add && !edit.remove) {
    const next = edit.items.filter(i => i && i.face).map((w, i) => toItem(w, items[i]));
    const changed = next
      .map((item, i) => (JSON.stringify(item) === JSON.stringify(items[i]) ? -1 : i))
      .filter(i => i >= 0);
    return { items: next, changed, removed: Math.max(0, items.length - next.length) };
  }

  const rewritten = new Map();
  (Array.isArray(edit.changes) ? edit.changes : []).forEach(c => {
    const i = itemIndex(c && c.n, items.length);
    if (i >= 0 && c.face) rewritten.set(i, toItem(c, items[i]));
  });

  const gone = new Set(
    (Array.isArray(edit.remove) ? edit.remove : [])
      .map(n => itemIndex(n, items.length))
      .filter(i => i >= 0),
  );

  const next = [];
  const changed = [];
  items.forEach((item, i) => {
    if (gone.has(i)) return;
    if (rewritten.has(i)) changed.push(next.length);
    next.push(rewritten.get(i) || item);
  });
  (Array.isArray(edit.add) ? edit.add : []).forEach(w => {
    if (!w || !w.face) return;
    changed.push(next.length);
    next.push(toItem(w));
  });

  return { items: next, changed, removed: gone.size };
}

/* The board keeps its shape: the same categories, the same rows. A clue's
 * value and its heard flag belong to the row, so they are kept from the board
 * whatever the model sends — a clue that moved rows would break the ladder
 * every team has been promised. */
function applyBoardEdit(categories, final, edit) {
  const next = categories.map(cat => ({ ...cat, clues: (cat.clues || []).map(q => ({ ...q })) }));
  const renamed = [];
  const changed = [];

  (Array.isArray(edit.names) ? edit.names : []).forEach(r => {
    const ci = Number(r && r.category) - 1;
    const name = r && typeof r.name === 'string' ? r.name.trim() : '';
    if (!next[ci] || !name || name === next[ci].name) return;
    next[ci].name = name;
    renamed.push(ci);
  });

  (Array.isArray(edit.clues) ? edit.clues : []).forEach(w => {
    const ci = Number(w && w.category) - 1;
    if (!next[ci]) return;
    const qi = next[ci].clues.findIndex(q => Number(q.value) === Number(w.value));
    if (qi < 0) return;
    const clue = next[ci].clues[qi];
    // A clue without the text it needs is left as it was, not blanked.
    if (typeof w.prompt !== 'string' || !w.prompt.trim()) return;
    if (typeof w.answer !== 'string' || !w.answer.trim()) return;
    clue.prompt = w.prompt.trim();
    clue.answer = w.answer.trim();
    // Kept from the old clue it would be a translation of the wrong sentence,
    // so a rewrite that leaves it out leaves it blank for the user to see.
    if (typeof w.promptEn === 'string' && w.promptEn.trim()) clue.promptEn = w.promptEn.trim();
    else delete clue.promptEn;
    changed.push({ category: ci, clue: qi });
  });

  /* The final wager is one clue with no number to address it by, so the model
   * sends it whole or not at all. Whole means all four fields: a final missing
   * its category cannot be bet on, and one missing its English is the only
   * clue in the pack a stuck class could not be helped with.
   */
  const written = edit && edit.final;
  let nextFinal = final ? { ...final } : null;
  let finalChanged = false;
  if (written && typeof written.prompt === 'string' && written.prompt.trim()
    && typeof written.answer === 'string' && written.answer.trim()) {
    nextFinal = {
      category: String(written.category || (final && final.category) || '').trim(),
      prompt: written.prompt.trim(),
      answer: written.answer.trim(),
    };
    const en = typeof written.promptEn === 'string' ? written.promptEn.trim() : '';
    if (en) nextFinal.promptEn = en;
    finalChanged = true;
  }

  return { categories: next, final: nextFinal, renamed, changed, finalChanged };
}

module.exports = { applyItemEdit, applyBoardEdit };
