/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/bingoCards.js (commit 74c9a5a) by its
 * scripts/sync-site-shared.js. Edit it there and run that script again; an
 * edit made here is lost the next time anyone does.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.SharedBingoCards = factory();
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';
  var module = { exports: {} };

/**
 * Bingo deck generator. Pure: no I/O, no randomness of its own.
 *
 * A deck is a set of short FACES printed on the cards plus CLUES that each
 * identify exactly one face. Nothing here knows or cares whether the faces are
 * vocabulary words, conjugated verb forms, clock times or minimal pairs — that
 * is the calling generator's business. Keeping this file ignorant of it is what
 * lets a new deck type ship without touching the caller site.
 *
 * The cards run once, here, and are both printed and baked into the published
 * room payload. The site never generates a card — it reads the same array the
 * PDF was printed from. Two implementations that had to agree on what card #17
 * contains would eventually disagree, and the moment it showed would be a kid
 * shouting "¡Bingo!" in front of the class.
 */

/**
 * @param {object} params
 * @param {string[]} params.faces  - What goes in the cells.
 * @param {number} params.count    - How many distinct cards to make.
 * @param {number} params.size     - Grid edge; 3 and 4 are the two printed sets.
 * @param {function} params.rng    - The packet's seeded stream (see rng.js), so
 *                                   a reprint of a packet reprints the same cards.
 * @param {number} params.startId  - First id to hand out. See generateCardSets.
 * @returns {Array<{id:number, size:number, grid:string[][], free:boolean}>}
 */
function generateBingoCards({ faces, count = 30, size = 4, rng = Math.random, startId = 1 }) {
  /* No free centre, at either size.
   *
   * The tradition comes from 5x5, where the middle square is one cell in
   * twenty-five and sits on 4 of the 12 winning lines. On a 3x3 it is one cell
   * in nine and sits on 4 of the 8 lines -- half the ways to win would need two
   * words instead of three, and the card would teach eight words where it can
   * teach nine. At these sizes the give-away costs more than it is worth.
   *
   * The flag stays on the card because rooms published before this still carry
   * free cells, and the site marks a null cell automatically.
   */
  const free = false;
  const needed = size * size;

  if (!Array.isArray(faces) || faces.length < needed) {
    throw new Error(`generateBingoCards: need at least ${needed} faces for a ${size}x${size} card, got ${faces ? faces.length : 0}`);
  }

  const cards = [];
  const seen = new Set();
  // With 20 faces on a 4x4 there are far more layouts than any class needs, but
  // a deck whose face count exactly equals `needed` can only vary by
  // arrangement — cap the attempts so a pathological case ends rather than spins.
  const maxAttempts = count * 200;

  for (let attempt = 0; attempt < maxAttempts && cards.length < count; attempt++) {
    const picked = sample(faces, needed, rng);
    const key = picked.join(' ');
    if (seen.has(key)) continue;
    seen.add(key);

    const cells = picked.slice();
    if (free) cells.splice(Math.floor(size * size / 2), 0, null);

    const grid = [];
    for (let r = 0; r < size; r++) grid.push(cells.slice(r * size, (r + 1) * size));
    cards.push({ id: startId + cards.length, size, grid, free });
  }

  if (cards.length < count) {
    throw new Error(`generateBingoCards: could only build ${cards.length} distinct cards from ${faces.length} faces`);
  }
  return cards;
}

/**
 * Refuse a deck that cannot be played fairly.
 *
 * Vocabulary decks satisfy these rules by accident, which is exactly why the
 * check has to exist before any grammar deck is built. A conjugation clue that
 * drops the subject pronoun — "___ español todos los días" — fits hablo, hablas
 * AND habla, and on a card carrying two of them there is no right answer and no
 * way for the teacher to adjudicate. Catch it here, not in a classroom.
 *
 * @param {object} deck - { type, clueTypes, items:[{face, ...clues}] }
 * @param {number} size - Grid edge the deck will be printed at.
 * @returns {string[]} Problems found; empty means the deck is playable.
 */
function validateDeck(deck, size = 4) {
  const problems = [];
  const items = (deck && deck.items) || [];
  const clueTypes = (deck && deck.clueTypes) || [];

  if (!clueTypes.length) problems.push('deck declares no clue types');

  // Every cell carries a word now -- see generateBingoCards on the free centre.
  const needed = size * size;
  if (items.length < needed) {
    problems.push(`needs at least ${needed} faces for a ${size}x${size} card, has ${items.length}`);
  }

  /* Enough faces to make the cards DIFFERENT, which is a higher bar than
   * enough to fill one.
   *
   * The check above is printability. The cards are dealt by sampling the faces,
   * so a deck of exactly 16 gives every 4x4 card the same 16 words in a
   * different order -- generateBingoCards keys its duplicates on the
   * ARRANGEMENT, so it happily deals 30 "distinct" cards that are permutations
   * of one. Called in a random order, the class then reaches its lines all at
   * once: simulated over 30 cards on a 4x4, 16 faces yields ONE distinct word
   * set and 7.9 cards with bingo within two calls of the first, and the game is
   * over in twelve calls. 25 faces yields 30 distinct sets and 3.8; 30 yields
   * 3.1. That is the real floor, and it is why games.html asks for 25.
   *
   * Advisory rather than a refusal: a thin deck still plays, it just plays
   * badly, and nothing else tells the seller before the cards are printed.
   */
  const varied = Math.ceil(needed * 1.5);
  if (items.length >= needed && items.length < varied) {
    problems.push(`${items.length} faces fills a ${size}x${size} card but barely varies it: the cards will be near-shuffles of one another and much of the class will reach a line together. ${varied}+ is where that stops`);
  }

  const faces = new Set();
  items.forEach((item, i) => {
    if (!item.face) problems.push(`item ${i} has no face`);
    else if (faces.has(item.face)) problems.push(`duplicate face "${item.face}"`);
    else faces.add(item.face);

    // Every declared clue type on every item. The caller offers a clue type
    // whenever the deck declares it, so a half-populated field would silently
    // fall back to a different clue mid-game.
    clueTypes.forEach(key => {
      if (!item[key]) problems.push(`item "${item.face || i}" is missing clue "${key}"`);
    });
  });

  /* The article is its own field — on a deck of WORDS.
   *
   * Where an entry is a whole sentence the leading article belongs to it: "La
   * familia come pavo en el comedor." is the sentence, and there is no field
   * to move "La" into. Run anyway, this condemned every sentence that happened
   * to start with one, which on a Christmas bank was half the deck. The deck
   * says which it is, the same `stripsArticles` the board check and the
   * server's own tidy-up read. Absent means true, as it does everywhere else.
   */
  if (deck.stripsArticles !== false) {
    items.forEach(item => {
      if (item.article && !/^(el|la|los|las)$/i.test(String(item.article).trim())) {
        problems.push(`"${item.face}" has "${item.article}" as its article; expected el, la, los or las`);
      }
      if (/^(el|la|los|las)\s/i.test(item.face || '')) {
        problems.push(`face "${item.face}" still has its article glued on; the article belongs in its own field`);
      }
    });
  }

  // The rule vocabulary gives away free: one clue, one face.
  clueTypes.forEach(key => {
    const byClue = new Map();
    items.forEach(item => {
      const clue = normalizeClue(item[key]);
      if (!clue) return;
      if (!byClue.has(clue)) byClue.set(clue, []);
      byClue.get(clue).push(item.face);
    });
    byClue.forEach((matches, clue) => {
      if (matches.length > 1) {
        problems.push(`clue "${key}" is ambiguous: "${clue}" matches ${matches.join(', ')}`);
      }
    });
  });

  /* One word's own sentence and definition must not be the same clue twice.
   *
   * The clue types are the three ways the website can call a word out, and a
   * teacher playing "Mezcla" gets whichever one comes up. When the definition
   * is the gap sentence with the gap filled in — "El ___ es rosa y le gusta
   * jugar en el barro" against "Es rosa y le gusta jugar en el barro" — the
   * pack has two clue types on paper and one in the room, and the round that
   * was meant to make the child listen differently makes them listen again.
   *
   * Checked by overlap rather than equality, because the echo is never an
   * exact copy: the article moves, the gap closes, a word changes. A
   * definition built almost entirely out of its own sentence's words is the
   * shape this takes.
   */
  items.forEach(item => {
    const echo = clueEcho(item.sentence, item.definition);
    if (echo) {
      problems.push(`"${displayFace(item)}" has a definition that is its own gap sentence reworded (${echo}); write a different kind of clue`);
    }
  });

  /* A SPANISH clue may name another face on the cards. An ENGLISH one may not.
   *
   * This was once checked over every clue type, and a pack's own subject matter
   * argued it down. The 30 words are one topic, so describing one of them
   * reaches for another on its own — a garden's "árbol" wants "rama", a body's
   * "rodilla" wants "pierna" — and the rule's only escape was to write around
   * the word: vaguer, or a harder synonym the child does not know. That trades
   * a clue a child can use for protection against a trap the card itself
   * mostly defuses, since the answer still fits and the words they can see are
   * the words they are learning together.
   *
   * The English clue is the other way round, because an overlap there is a
   * coincidence rather than a relation: "the frying pan" read aloud for
   * "sartén" while "pan" sits on the card teaches nothing and costs a square.
   * Those are rare and always accidental, which is exactly what a review list
   * is for.
   */
  const faceKeys = new Map();
  items.forEach(item => {
    const key = String(item.face || '').toLowerCase().trim();
    if (key) faceKeys.set(key, displayFace(item));
  });
  items.forEach(item => {
    const text = normalizeClue(item.en);
    if (!text) return;
    const own = String(item.face || '').toLowerCase().trim();
    faceKeys.forEach((face, word) => {
      if (word === own) return;
      if (new RegExp(EDGE_LEFT + escapeRegExp(word) + EDGE_RIGHT, 'u').test(text)) {
        problems.push(`the English clue for "${displayFace(item)}" contains "${face}", another word on the cards`);
      }
    });
  });

  return problems;
}

/* What the word looks like when it is shown rather than slotted into a gap.
 *
 * The article is a separate field, not part of the face, for two reasons: a gap
 * sentence supplies its own determiners and reads as ordinary Spanish
 * ("Un hombre ___ no tiene miedo"), and plenty of words never take an article
 * at all — "valiente" is an adjective, "saltar" is a verb. Where gender IS the
 * teaching point, the card and the reference page put it back.
 */
function displayFace(item) {
  if (!item) return '';
  return [item.article, item.face].filter(Boolean).join(' ').trim();
}

/* A word boundary that understands accents.
 *
 * JS `\b` is ASCII-only, so `\bárbol\b` never matches "el árbol". Under the
 * older, wider version of the check above that quietly exempted every accented
 * face, and a garden pack full of "árbol" collisions read as clean. String.raw
 * because these go into a RegExp, not into text.
 */
const EDGE_LEFT = String.raw`(?<![\p{L}\p{N}])`;
const EDGE_RIGHT = String.raw`(?![\p{L}\p{N}])`;

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function normalizeClue(value) {
  if (typeof value !== 'string') return '';
  return value.trim().toLowerCase().replace(/\s+/g, ' ');
}

/* The words of a clue, for comparing one with another.
 *
 * Accents and punctuation go because the echo survives both, and the gap
 * itself goes because it is the one place the two clues are guaranteed to
 * differ. Short function words (de, la, en, y) are dropped: they are the words
 * two unrelated Spanish sentences share anyway, so counting them would flag
 * clues that have nothing in common but grammar.
 */
function clueWords(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/_+/g, ' ')
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9ñ\s]/g, ' ')
    .split(/\s+/)
    .filter(word => word.length > 2);
}

/* How much of the definition is just its own gap sentence, or '' if it is its
 * own clue. Deliberately a high bar — the two clues describe the same word, so
 * some shared words are expected and right. */
function clueEcho(sentence, definition) {
  const inSentence = new Set(clueWords(sentence));
  const inDefinition = clueWords(definition);

  // Too short to judge: a three-word definition shares words with anything.
  if (inDefinition.length < 4 || inSentence.size < 4) return '';

  const shared = inDefinition.filter(word => inSentence.has(word)).length;
  const overlap = shared / inDefinition.length;

  return overlap >= 0.8 ? `${shared} of its ${inDefinition.length} words` : '';
}

/**
 * Both printed card sets, numbered in one continuous run.
 *
 * The 4x4 set and the 3x3 set are separate PDFs so a teacher prints only the
 * one they want, but their ids must NOT restart — a child shouts a bare number
 * across the room ("¡Bingo! Sesenta y dos") and the teacher types exactly that.
 * Two cards numbered 17 would leave nothing to disambiguate them with, and the
 * moment it mattered would be mid-game in front of a class. One run of numbers
 * across both sets means the number alone identifies the card and, through it,
 * its grid size.
 *
 * @param {object} params
 * @param {string[]} params.faces
 * @param {Array<{size:number, count:number}>} params.sets - In print order.
 * @param {function} params.rng
 */
function generateCardSets({ faces, sets, rng = Math.random }) {
  const all = [];
  sets.forEach(spec => {
    const made = generateBingoCards({
      faces,
      count: spec.count,
      size: spec.size,
      rng,
      startId: all.length + 1,
    });
    all.push(...made);
  });
  return all;
}

function sample(items, n, rng) {
  const pool = items.slice();
  for (let i = pool.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [pool[i], pool[j]] = [pool[j], pool[i]];
  }
  return pool.slice(0, n);
}

/**
 * Every set of cells that wins, as [row, col] pairs. Rows, columns and both
 * diagonals for a line game; the whole grid for a blackout.
 */
function winningLines(size, pattern = 'line') {
  if (pattern === 'blackout') {
    const all = [];
    for (let r = 0; r < size; r++) for (let c = 0; c < size; c++) all.push([r, c]);
    return [all];
  }
  const lines = [];
  const idx = range(size);
  for (let r = 0; r < size; r++) lines.push(idx.map(c => [r, c]));
  for (let c = 0; c < size; c++) lines.push(idx.map(r => [r, c]));
  lines.push(idx.map(i => [i, i]));
  lines.push(idx.map(i => [i, size - 1 - i]));
  return lines;
}

function range(n) {
  return Array.from({ length: n }, (_, i) => i);
}

module.exports = { generateBingoCards, generateCardSets, validateDeck, winningLines, displayFace };

  return module.exports;
}));
