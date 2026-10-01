/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/jeopardyBoard.js (commit 34674dd) by its
 * scripts/sync-site-shared.js. Edit it there and run that script again; an
 * edit made here is lost the next time anyone does.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.SharedJeopardyBoard = factory();
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';
  var module = { exports: {} };

/**
 * Quiz board checks. Pure: no I/O.
 *
 * The board is the one part of a pack a teacher reads aloud in front of a class
 * with no chance to fix it, so the failures worth catching are the ones that
 * only show up at that moment: two squares with the same answer, a $500 clue
 * that is easier than the $100, a category heading too long for its column.
 */

// Five categories across a 16:9 board. Measured in characters, not words:
// "¡Viva la Independencia!" is three words and still too wide for the column,
// which is how it ended up truncated on a printed header.
const MAX_CATEGORY_CHARS = 22;

// The final wager's category has the screen and the sheet to itself rather than
// a fifth of a column header, so it can say more than a square's can — but it is
// still one line under "¡LA APUESTA FINAL!", and it is the only thing a team has
// to bet on, so it may not run to a sentence.
const MAX_FINAL_CATEGORY_CHARS = 40;

/**
 * @param {object} board - { categories: [{ name, clues: [{ value, prompt, answer }] }],
 *   final: { category, prompt, promptEn, answer } }
 * @param {object} [expected] - { categoryCount, cluesPerCategory, items }
 *   `items` is the word bank; when given, every answer must be one of its faces.
 * @returns {string[]} Problems found; empty means the board is playable.
 */
function validateBoard(board, expected = {}) {
  const problems = [];
  const categories = (board && board.categories) || [];

  if (expected.categoryCount && categories.length !== expected.categoryCount) {
    problems.push(`expected ${expected.categoryCount} categories, got ${categories.length}`);
  }

  const names = new Set();
  const answers = new Map();

  categories.forEach((cat, ci) => {
    const label = cat.name || `category ${ci}`;

    if (!cat.name) problems.push(`category ${ci} has no name`);
    else if (names.has(cat.name)) problems.push(`duplicate category "${cat.name}"`);
    else names.add(cat.name);

    if (cat.name && cat.name.trim().length > MAX_CATEGORY_CHARS) {
      problems.push(`category "${cat.name}" is ${cat.name.trim().length} characters; the column header fits ${MAX_CATEGORY_CHARS}`);
    }

    const clues = cat.clues || [];
    if (expected.cluesPerCategory && clues.length !== expected.cluesPerCategory) {
      problems.push(`"${label}" has ${clues.length} clues, expected ${expected.cluesPerCategory}`);
    }

    let previous = null;
    clues.forEach((clue, i) => {
      if (!clue || !clue.prompt) problems.push(`"${label}" clue ${i + 1} has no prompt`);
      if (!clue || !clue.answer) problems.push(`"${label}" clue ${i + 1} has no answer`);
      if (!clue || typeof clue.value !== 'number') {
        problems.push(`"${label}" clue ${i + 1} has no numeric value`);
        return;
      }

      // Values must climb. The dollar amount is a promise about difficulty, and
      // a board that breaks it feels arbitrary to a class within two turns.
      if (previous !== null && clue.value <= previous) {
        problems.push(`"${label}" values do not increase: ${previous} then ${clue.value}`);
      }
      previous = clue.value;

      const key = normalize(clue.answer);
      if (!key) return;
      if (!answers.has(key)) answers.set(key, []);
      answers.get(key).push(`${label} ${clue.value}`);
    });
  });

  /* The final wager, which is a clue in every way the squares are.
   *
   * Checked here rather than alongside them because it is not on the grid: it
   * has no value and no row, so nothing in the loop above would ever look at
   * it — and a clue nothing looks at is the one that reaches a class broken.
   * Its answer goes into the same `answers` map, so a final that repeats a
   * square is caught by the duplicate check below and reads the same way.
   */
  const final = board && board.final;
  if (categories.length) {
    if (!final || !final.prompt) {
      problems.push('the board has no final wager clue ("final"); the game will end on the board instead');
    } else {
      if (!final.category) problems.push('the final wager has no category, which is all a team has to bet on');
      else if (final.category.trim().length > MAX_FINAL_CATEGORY_CHARS) {
        problems.push(`the final wager's category is ${final.category.trim().length} characters; it fits ${MAX_FINAL_CATEGORY_CHARS}`);
      }
      if (!final.answer) problems.push('the final wager has no answer');
      // Shown, never heard: every team has money on it and needs to re-read it.
      if (final.audio) problems.push('the final wager is marked as heard-only; it must be shown on screen');
      const key = normalize(final.answer);
      if (key) {
        if (!answers.has(key)) answers.set(key, []);
        answers.get(key).push('the final wager');
      }
    }
  }

  /* The listening rows are the same in every column.
   *
   * Difficulty comes from the value, and modality is half of what makes the
   * ladder climb: a team picking a heard clue has agreed to work without the
   * text. If one column marks a different row than the others, the value stops
   * meaning the same thing across the board.
   */
  const audioRows = new Map();
  categories.forEach(cat => {
    (cat.clues || []).forEach(clue => {
      if (!clue.audio || typeof clue.value !== 'number') return;
      if (!audioRows.has(clue.value)) audioRows.set(clue.value, []);
      audioRows.get(clue.value).push(cat.name);
    });
  });
  const columns = categories.length;
  audioRows.forEach((where, value) => {
    if (columns && where.length !== columns) {
      problems.push(`the ${value} row is a listening clue in ${where.length} of ${columns} categories; it must be all or none`);
    }
  });

  // One answer, one square. Two squares sharing an answer means a team that
  // wrote the right word can be told it is wrong, and the teacher has no way to
  // adjudicate it from the script.
  answers.forEach((where, key) => {
    if (where.length > 1) problems.push(`answer "${key}" appears in ${where.join(' and ')}`);
  });

  if (Array.isArray(expected.items) && expected.items.length) {
    answersOutsideBank(board, expected.items).forEach(({ category, value, answer }) => {
      problems.push(`"${category}" ${value}: answer "${answer}" is not in the word bank`);
    });
  }

  return problems;
}

/* Board answers the word bank does not contain.
 *
 * The bank is what the class studies — the printed word list and the on-screen
 * vocabulary panel are both built from it — so an answer outside it is a word no
 * team was given. The demo room once went out with a sports bank under a whole
 * board about the city ("fuente", "semáforo"…): the builder kept the previous
 * topic's board when a new bank was generated, and nothing compared the two.
 * Separate from validateBoard so a build can refuse on exactly this.
 */
function answersOutsideBank(board, items) {
  const faces = new Set((items || []).map(i => bare(i && i.face)).filter(Boolean));
  if (!faces.size) return [];
  const outside = [];
  ((board && board.categories) || []).forEach((cat, ci) => {
    (cat.clues || []).forEach(clue => {
      const answer = bare(clue && clue.answer);
      if (answer && !faces.has(answer)) {
        outside.push({ category: cat.name || `category ${ci}`, value: clue.value, answer: clue.answer });
      }
    });
  });
  const final = board && board.final;
  const finalAnswer = bare(final && final.answer);
  if (finalAnswer && !faces.has(finalAnswer)) {
    outside.push({ category: final.category || 'the final wager', value: 'final', answer: final.answer });
  }
  return outside;
}

function normalize(value) {
  if (typeof value !== 'string') return '';
  return value.trim().toLowerCase().replace(/\s+/g, ' ');
}

// Faces are stored bare and answers are asked for bare, but either may arrive
// with its article ("el casco"), and that is not a different word.
function bare(value) {
  return normalize(value).replace(/^(el|la|los|las)\s+/, '');
}

// `bare` is exported for the website's editor, which pairs each answer with the
// bank word it names; matching it any other way would drift from this file.
module.exports = { validateBoard, answersOutsideBank, bare, MAX_CATEGORY_CHARS, MAX_FINAL_CATEGORY_CHARS };

  return module.exports;
}));
