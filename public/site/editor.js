/* The game editor: a teacher changing the game they bought.
 *
 * It edits the payload itself — the same object the room shell plays and the
 * builder wrote — and hands it back whole. Nothing here knows what a clue
 * means; the judging is done by the builder's own validators, copied into
 * /game/shared by its scripts/sync-site-shared.js, so the site can never tell a
 * teacher something is fine that the builder would refuse.
 *
 * The two halves are laid out the way the builder's own editor lays them out
 * (src/public/games.js): the words as one compact line each, opened when a
 * teacher wants the clues; the board as the board, a column per category. A
 * teacher scanning thirty words should not have to scroll through thirty
 * expanded forms to find the one they want to change.
 *
 * Plain JS on purpose: the rest of this site has no build step.
 */
(function () {
  'use strict';

  const root = document.getElementById('editor');
  if (!root) return;

  const urls = {
    payload: root.dataset.payloadUrl,
    save: root.dataset.saveUrl,
    play: root.dataset.playUrl,
    games: root.dataset.gamesUrl,
  };
  const gameId = root.dataset.gameId;

  let game = null;          // the payload being edited
  let originalFaces = '';   // the bank as it was loaded, to spot word changes
  let dirty = false;
  let saving = false;
  let checkTimer = null;

  const state = el('span', 'state');

  start();

  async function start() {
    let loaded;
    try {
      const res = await fetch(urls.payload, { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) throw new Error(String(res.status));
      loaded = await res.json();
    } catch {
      root.innerHTML = '';
      root.append(el('p', 'error', 'We could not open this game. Reload the page to try again.'));
      return;
    }

    game = loaded;
    originalFaces = faceList();
    render();
  }

  /* ---------- the page ---------- */

  /* What a teacher came here to change goes first, and nothing else is put in
   * front of it.
   *
   * A bingo pack opens on its words: they are the game, and the cards are built
   * from them, and each word carries the three ways it can be called out.
   *
   * A quiz pack opens on the board and has no word list at all. The quiz shows
   * the class only two things about a word — the word and its English
   * (`openVocab` in ui.js) — and the word is already in the square, as its
   * answer. So the English sits under it, in the square, and a thirty-row list
   * of everything else disappears. Only the words no square uses need a list of
   * their own, and there are usually four of them.
   */
  function render() {
    root.innerHTML = '';
    root.append(bar(), checksPanel());

    const games = game.games || {};
    const hasWords = Array.isArray(game.items) && game.items.length;

    if (hasWords && games.bingo) root.append(wordsCard());
    if (games.jeopardy) root.append(boardCard());
    if (games.bingo) root.append(bingoNote());
    if (hasWords && !games.bingo) root.append(unusedWordsCard());

    check();
  }

  /* The bank words no square and no final wager answers.
   *
   * They are still in the list the class reviews, so they have to be editable
   * somewhere — but they are the exception, not the page. A quiz pack carries
   * about four.
   */
  function unusedWordsCard() {
    const spare = game.items.filter(item => !answerFor(item));
    if (!spare.length) return document.createComment('');

    const fold = el('details', 'card fold');
    fold.append(el('summary', '', 'Words your class can review that no square uses (' + spare.length + ')'));

    const body = el('div', 'words');
    body.append(el('p', 'lead', 'These are in the list your class sees when you press Vocabulario, but no clue answers to them.'));

    const list = el('div', 'word-list compact');
    spare.forEach(item => list.append(wordRow(item, game.items.indexOf(item), null, list)));
    body.append(list);

    fold.append(body);
    return fold;
  }

  /* The clue, if any, whose answer names this word. */
  function answerFor(item) {
    const board = (game.games && game.games.jeopardy) || {};
    const face = window.SharedJeopardyBoard.bare(item.face);
    let found = null;
    (board.categories || []).forEach(cat => {
      (cat.clues || []).forEach(clue => {
        if (!found && face && window.SharedJeopardyBoard.bare(clue.answer) === face) found = clue;
      });
    });
    if (!found && board.final && face && window.SharedJeopardyBoard.bare(board.final.answer) === face) found = board.final;
    return found;
  }

  /* The bank word an answer names, which is the word the class reviews. */
  function wordFor(answer) {
    const key = window.SharedJeopardyBoard.bare(answer);
    if (!key) return null;
    return (game.items || []).find(item => window.SharedJeopardyBoard.bare(item.face) === key) || null;
  }

  function bar() {
    const wrap = el('div', 'bar');

    const theme = el('input', 'theme');
    theme.value = game.theme || '';
    theme.setAttribute('aria-label', 'Game name');
    theme.oninput = () => { game.theme = theme.value; touched(); };

    const save = el('button', 'btn btn-primary', 'Save');
    save.type = 'button';
    save.onclick = () => saveGame(save);

    const play = el('a', 'btn btn-ghost', 'Play ▸');
    play.href = urls.play;

    const back = el('a', 'btn btn-ghost', 'My games');
    back.href = urls.games;

    wrap.append(theme, state, save, play, back);
    return wrap;
  }

  /* ---------- the words ---------- */

  function wordsCard(folded) {
    const card = el('div', folded ? 'words' : 'card words');
    if (!folded) card.append(el('h2', '', 'Words'));
    card.append(el('p', 'lead', wordsPurpose()));

    const list = el('div', 'word-list compact');

    // Compact by default, because the point of this list is to scan it. The
    // switch opens every word at once for a teacher going through the lot.
    const tools = el('div', 'list-tools');
    const count = el('span', 'count', game.items.length + ' words');
    const openAll = el('label', 'check');
    const box = el('input');
    box.type = 'checkbox';
    box.onchange = () => {
      list.classList.toggle('compact', !box.checked);
      if (box.checked) closeAll(list);
    };
    openAll.append(box, document.createTextNode('Open every word'));
    tools.append(count, openAll);

    game.items.forEach((item, i) => list.append(wordRow(item, i, count, list)));

    const add = el('button', 'btn btn-ghost add', '+ Add a word');
    add.type = 'button';
    add.onclick = () => {
      game.items.push({ face: '', article: '', en: '' });
      touched();
      render();
      focusLast('.word-row .face');
    };

    card.append(tools, list, add);
    return card;
  }

  /* A word list is not obviously part of a quiz, and a teacher who only bought
   * the quiz wonders what it is doing here. Say which game it feeds. */
  function wordsPurpose() {
    const has = game.games || {};
    if (has.bingo && has.jeopardy) {
      return 'The words both games are built from. Open one to see the sentence and definition the game reads out.';
    }
    if (has.bingo) {
      return 'The words on the bingo cards. Open one to see the three ways the website can call it out: the English, a sentence with a gap, or a definition.';
    }
    return 'The board’s answers all come from this list, and it is what your class sees when you press Vocabulario during the game. Change it if you rename a word or add an answer that is not here yet.';
  }

  function wordRow(item, index, count, list) {
    const row = el('div', 'word-row');

    const head = el('div', 'word-head');

    /* The chevron leads the row rather than trailing it: it is the one control
     * that says this line has more behind it, and at the end of a line of
     * inputs nobody saw it. Clicking anywhere in the row opens it too — and so
     * does typing in one of its fields, since a teacher who has reached a word
     * is working on that word. */
    const open = el('button', 'word-open');
    open.type = 'button';
    open.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
      + '<path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.5" '
      + 'stroke-linecap="round" stroke-linejoin="round"/></svg>';
    open.title = 'Show or hide this word’s clues';
    open.setAttribute('aria-expanded', 'false');

    head.append(open, el('span', 'n', String(index + 1)));

    head.append(articleField(item));
    head.append(textField('face', 'Spanish word', item.face || '', v => { item.face = v; touched(); }));
    head.append(textField('en', 'English', item.en || '', v => { item.en = v; touched(); }));

    open.onclick = () => (row.classList.contains('open') ? close(row) : openOnly(row, list));

    // Anywhere in the row that is not a control of its own.
    row.onclick = event => {
      if (event.target.closest('input, select, textarea, button')) return;
      if (!row.classList.contains('open')) openOnly(row, list);
    };
    /* A field opens the row; a button never does.
     *
     * A button focuses on the way down and fires its click on the way up, so
     * opening here would leave the chevron's own click to close it again — and
     * would open a collapsed row just as a teacher pressed remove on it. */
    row.addEventListener('focusin', event => {
      if (!event.target.closest('button')) openOnly(row, list);
    });

    /* Removing a word is one click and thirty rows look alike, so it asks
     * first — in the row rather than in a browser dialog, which on a phone
     * covers the word it is asking about. */
    const drop = el('button', 'drop');
    drop.type = 'button';
    drop.title = 'Remove this word';
    drop.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
      + '<path d="M4 7h16M10 4h4M9 7v13m6-13v13M6 7l1 14h10l1-14" fill="none" stroke="currentColor" '
      + 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    drop.onclick = () => row.classList.add('confirming');

    const confirm = el('div', 'confirm');
    confirm.append(el('span', '', 'Remove this word?'));
    const yes = el('button', 'confirm-yes', 'Remove');
    yes.type = 'button';
    yes.onclick = () => {
      game.items.splice(index, 1);
      touched();
      render();
    };
    const no = el('button', 'confirm-no', 'Keep it');
    no.type = 'button';
    no.onclick = () => row.classList.remove('confirming');
    confirm.append(yes, no);

    head.append(drop);

    // Each Spanish field beside its own English: they are read together when
    // checking one, and stacking them makes the row twice as tall for no gain.
    const detail = el('div', 'word-detail');
    if ('sentence' in item || 'sentenceEn' in item) {
      detail.append(pair(
        areaField('Sentence with a gap (___)', item.sentence || '', v => { item.sentence = v; touched(); }),
        areaField('… in English', item.sentenceEn || '', v => { item.sentenceEn = v; touched(); }),
      ));
    }
    if ('definition' in item || 'definitionEn' in item) {
      detail.append(pair(
        areaField('Definition in Spanish', item.definition || '', v => { item.definition = v; touched(); }),
        areaField('… in English', item.definitionEn || '', v => { item.definitionEn = v; touched(); }),
      ));
    }

    row.append(head, confirm, detail);
    if (count) count.textContent = game.items.length + ' words';
    return row;
  }

  /* One word open at a time, unless the teacher asked for all of them. Two
   * half-open rows in a list of thirty is how a teacher loses their place. */
  function openOnly(row, list) {
    if (!list.classList.contains('compact')) return;
    Array.prototype.forEach.call(list.children, other => { if (other !== row) close(other); });
    row.classList.add('open');
    const chevron = row.querySelector('.word-open');
    if (chevron) chevron.setAttribute('aria-expanded', 'true');
  }

  function close(row) {
    row.classList.remove('open');
    const chevron = row.querySelector('.word-open');
    if (chevron) chevron.setAttribute('aria-expanded', 'false');
  }

  function closeAll(list) {
    Array.prototype.forEach.call(list.children, close);
  }

  /* ---------- the board ---------- */

  function boardCard() {
    const board = game.games.jeopardy;
    const card = el('div', 'card');
    card.append(el('h2', '', 'Quiz board'));
    card.append(el('p', 'lead', 'The board as your class sees it: a column per category, cheapest clue at the top. The money is a promise about difficulty, so clues get harder down a column.'));

    const grid = el('div', 'board-grid');
    const cheapest = lowestValue(board);
    (board.categories || []).forEach(cat => grid.append(categoryColumn(cat, cheapest)));

    // On a phone the columns are wider than the screen, so the board scrolls
    // sideways one column at a time and this jumps between them.
    if ((board.categories || []).length > 1) {
      const jump = el('select', 'column-jump');
      jump.setAttribute('aria-label', 'Go to a category');
      board.categories.forEach((cat, i) => {
        const option = el('option', '', cat.name || 'Category ' + (i + 1));
        option.value = String(i);
        jump.append(option);
      });
      /* Sets the strip's own scroll rather than calling scrollIntoView, which
       * also scrolls the page vertically to reach the element and left the
       * phone jumping up and down on every pick. */
      jump.onchange = () => {
        const column = grid.children[Number(jump.value)];
        if (column) grid.scrollTo({ left: column.offsetLeft - grid.offsetLeft, behavior: 'smooth' });
      };
      card.append(jump);
    }

    card.append(topScroller(grid), grid);
    card.append(finalSection(board));
    return card;
  }

  /* A second scrollbar above the board.
   *
   * Five columns rarely fit a laptop, and the only scrollbar is under the
   * board — past the bottom of the screen when a teacher is looking at the
   * $100 row. This is an empty strip whose scrollbar drives the real one, the
   * usual trick for a wide table. It measures itself after layout, keeps
   * itself in step with the strip below, and hides when everything fits.
   */
  function topScroller(grid) {
    const rail = el('div', 'board-rail');
    const inner = el('div', 'board-rail-inner');
    rail.append(inner);

    /* Whichever one the pointer is over is the one driving.
     *
     * Mirroring both ways on every scroll event has the two chasing each other
     * — each one's correction is another scroll event — and the board ends up
     * somewhere neither was asked for. The board drives by default, so a
     * sideways swipe on it, or tabbing into a clue off-screen, moves the rail.
     */
    let driver = grid;
    rail.addEventListener('pointerenter', () => { driver = rail; });
    rail.addEventListener('pointerleave', () => { driver = grid; });
    rail.addEventListener('scroll', () => { if (driver === rail) grid.scrollLeft = rail.scrollLeft; });
    grid.addEventListener('scroll', () => { if (driver !== rail) rail.scrollLeft = grid.scrollLeft; });

    const measure = () => {
      inner.style.width = grid.scrollWidth + 'px';
      rail.classList.toggle('needed', grid.scrollWidth > grid.clientWidth + 1);
    };
    requestAnimationFrame(measure);
    window.addEventListener('resize', measure);

    return rail;
  }

  /* The final wager, and the offer to write one when a board has none.
   *
   * Boards built before the final existed have no `final`, and the game simply
   * ends on the board. A teacher who wants one should not have to rebuy the
   * pack to get it. */
  function finalSection(board) {
    if (board.final) return finalBlock(board.final);

    const empty = el('div', 'final final-empty');
    empty.append(el('h3', '', 'La Apuesta Final'));
    empty.append(el('p', 'muted-note', 'This board has none, so the game ends when the last square is gone. Add one and every team bets what they have on a last clue — the team in last place can still win.'));

    const add = el('button', 'btn btn-ghost add', '+ Add a final wager');
    add.type = 'button';
    add.onclick = () => {
      // Shown, never heard: every team has money on it and has to re-read it.
      board.final = { category: '', prompt: '', promptEn: '', answer: '' };
      touched();
      render();
      const first = document.querySelector('.final textarea, .final input');
      if (first) first.focus();
    };
    empty.append(add);
    return empty;
  }

  function categoryColumn(cat, cheapest) {
    const col = el('div', 'board-col');

    // A textarea, not an input: a long name in an input scrolls out of sight,
    // which is how a name too wide for the printed column goes unnoticed.
    const name = el('textarea', 'board-cat');
    name.rows = 2;
    name.value = cat.name || '';
    name.setAttribute('aria-label', 'Category name');
    name.oninput = () => { cat.name = name.value; touched(); };
    col.append(name);

    (cat.clues || []).forEach(clue => col.append(clueCell(clue, cheapest)));
    return col;
  }

  function clueCell(clue, cheapest) {
    const cell = el('div', 'board-cell');

    const head = el('div', 'board-cell-head');
    head.append(el('span', 'board-value', '$' + (clue.value != null ? clue.value : '?')));

    /* A heard clue is read aloud with nothing on screen. It is shown as a badge
     * rather than a switch on purpose: a row is heard in every category or in
     * none — the value has to mean the same thing across the board — so one
     * teacher toggling one square would quietly break that. */
    if (clue.audio) {
      const heard = el('span', 'board-heard', 'heard');
      heard.title = 'Read aloud by the screen; the text is never shown to the class';
      head.append(heard);
    }
    cell.append(head);

    const prompt = el('textarea', 'board-prompt');
    prompt.rows = 3;
    prompt.value = clue.prompt || '';
    prompt.setAttribute('aria-label', 'Clue');
    prompt.oninput = () => { clue.prompt = prompt.value; touched(); };
    cell.append(prompt);

    /* The English the teacher can reveal mid-game, and which is printed on the
     * script. The cheapest row has none: that row IS the English question. */
    if (clue.value !== cheapest) {
      const promptEn = el('textarea', 'board-prompt board-prompt-en');
      promptEn.rows = 2;
      promptEn.placeholder = '… in English';
      promptEn.value = clue.promptEn || '';
      promptEn.setAttribute('aria-label', 'The clue in English');
      promptEn.oninput = () => { clue.promptEn = promptEn.value; touched(); };
      cell.append(promptEn);
    }

    const answer = el('input', 'board-answer');
    answer.value = clue.answer || '';
    answer.setAttribute('aria-label', 'Answer');

    /* The answer's English, edited where the answer is.
     *
     * It is the same word the class reads in the Vocabulario list, and the
     * quiz shows them nothing else about it, so a separate list of every word
     * would be a second place to keep one field in step. Only shown when this
     * pack is the quiz alone; with bingo in the pack the words have their own
     * section, and two boxes for one value drift apart on screen.
     *
     * The article rides beside the answer, not beside the English: it is
     * Spanish, and "la" in front of "Christmas" reads as a mistake. */
    const english = showsEnglishInSquares() ? englishRow(answer) : null;

    if (english) {
      const line = el('div', 'board-answer-line');
      line.append(english.article, answer);
      cell.append(line, english.node);
    } else {
      cell.append(answer);
    }

    answer.oninput = () => {
      clue.answer = answer.value;
      if (english) english.follow();
      touched();
    };

    return cell;
  }

  function showsEnglishInSquares() {
    const games = game.games || {};
    return !!games.jeopardy && !games.bingo && Array.isArray(game.items);
  }

  /* The English of whatever word the answer beside it names.
   *
   * It follows the answer: retyping it points at a different word, and a word
   * the list has never heard of is offered as a new one rather than silently
   * doing nothing — the class's review list would otherwise lose a word the
   * board asks for.
   */
  function englishRow(answer) {
    const node = el('div', 'board-en');
    const article = el('select', 'board-article');
    ['', 'el', 'la', 'los', 'las'].forEach(option => {
      const choice = el('option', '', option === '' ? '—' : option);
      choice.value = option;
      article.append(choice);
    });
    article.setAttribute('aria-label', 'Article');

    function follow() {
      node.innerHTML = '';
      const item = wordFor(answer.value);

      article.disabled = !item;
      article.value = (item && item.article) || '';
      article.onchange = () => { if (item) { item.article = article.value; touched(); } };

      if (!item) {
        if (!String(answer.value).trim()) return;
        const add = el('button', 'board-add', '+ add “' + answer.value.trim() + '” to the vocabulary list');
        add.type = 'button';
        add.title = 'Your class reviews this list; a word that is not in it cannot be reviewed';
        add.onclick = () => {
          game.items.push({ face: answer.value.trim(), article: '', en: '' });
          touched();
          follow();
          const box = node.querySelector('input');
          if (box) box.focus();
        };
        node.append(add);
        return;
      }

      const en = el('input', 'board-english');
      en.value = item.en || '';
      en.placeholder = 'in English';
      en.setAttribute('aria-label', 'The answer in English');
      en.oninput = () => { item.en = en.value; touched(); };

      node.append(en);
    }

    follow();
    return { node: node, article: article, follow: follow };
  }

  /* The final wager, below the grid rather than inside it: it has no column and
   * no value, and dropping it in as a sixth cell would say it belongs to that
   * category — the one thing it must not say. */
  function finalBlock(final) {
    const block = el('div', 'final');
    block.append(el('h3', '', 'La Apuesta Final'));
    block.append(el('p', 'muted-note', 'Teams see only the category, write a bet, and then this clue decides the game. It is always shown on screen.'));

    const fields = el('div', 'fields');
    fields.append(areaField('Category', final.category || '', v => { final.category = v; touched(); }));

    // The final's answer is a bank word like any square's, so it carries the
    // same English underneath it.
    const answerField = el('div', 'field');
    const answer = el('input', 'board-answer');
    answer.value = final.answer || '';
    const english = showsEnglishInSquares() ? englishRow(answer) : null;
    answerField.append(labelFor('Answer', answer));
    if (english) {
      const line = el('div', 'board-answer-line');
      line.append(english.article, answer);
      answerField.append(line, english.node);
    } else {
      answerField.append(answer);
    }
    answer.oninput = () => {
      final.answer = answer.value;
      if (english) english.follow();
      touched();
    };
    fields.append(answerField);
    fields.append(wide(areaField('Clue', final.prompt || '', v => { final.prompt = v; touched(); })));
    if ('promptEn' in final) {
      fields.append(wide(areaField('… in English', final.promptEn || '', v => { final.promptEn = v; touched(); })));
    }

    block.append(fields);
    return block;
  }

  function lowestValue(board) {
    const values = [];
    (board.categories || []).forEach(cat => {
      (cat.clues || []).forEach(clue => { if (typeof clue.value === 'number') values.push(clue.value); });
    });
    return values.length ? Math.min.apply(null, values) : null;
  }

  /* What a bingo teacher has to know before they change a word: the cards in
   * their download were printed with the old ones. */
  function bingoNote() {
    const card = el('div', 'card');
    card.append(el('h2', '', 'Bingo cards'));
    card.append(el('p', 'lead', 'Change a word and the website builds new cards to match it, so the game you play online is always right. The cards in your TpT download still have the words they were printed with.'));
    return card;
  }

  /* ---------- checking ---------- */

  function checksPanel() {
    const panel = el('div', 'checks clear');
    panel.id = 'checks';
    return panel;
  }

  function check() {
    const panel = document.getElementById('checks');
    if (!panel) return;

    const problems = [];
    const items = Array.isArray(game.items) ? game.items : [];

    if (game.games && game.games.bingo && items.length) {
      const sizes = (game.games.bingo.cardSets || []).map(set => set.size);
      const biggest = sizes.length ? Math.max.apply(null, sizes) : 4;
      push(problems, window.SharedBingoCards.validateDeck({ clueTypes: game.clueTypes || [], items: items }, biggest));
    }

    if (game.games && game.games.jeopardy) {
      push(problems, window.SharedJeopardyBoard.validateBoard(game.games.jeopardy, { items: items }));
    }

    panel.className = 'checks ' + (problems.length ? 'problems' : 'clear');
    panel.innerHTML = '';

    if (!problems.length) {
      panel.append(el('h3', '', '✓ This game is ready to play'));
      return;
    }

    panel.append(el('h3', '', 'Worth a look before your lesson'));
    const list = el('ul');
    problems.forEach(problem => list.append(el('li', '', problem)));
    panel.append(list);
    panel.append(el('p', 'muted-note', 'You can still save and play — these are for you to judge, not rules we enforce.'));
  }

  function push(into, problems) {
    (problems || []).forEach(problem => into.push(problem));
  }

  /* ---------- saving ---------- */

  async function saveGame(button) {
    if (saving) return;
    saving = true;
    button.disabled = true;
    setState('Saving…', '');

    rebuildCardsIfWordsChanged();

    try {
      const res = await fetch(urls.save, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token(),
        },
        body: JSON.stringify({ payload: game }),
      });

      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        setState(body.message || 'We could not save that. Try again.', 'failed');
        return;
      }

      dirty = false;
      originalFaces = faceList();
      setState('Saved', '');
    } catch {
      setState('We could not reach the site. Your changes are still on this page.', 'failed');
    } finally {
      saving = false;
      button.disabled = false;
    }
  }

  /* The cards are part of the payload, built when the pack was, so a changed
   * word would otherwise leave the website calling a word that is on no card.
   * Seeded from this game's id, so the same words always give the same cards:
   * pressing save twice must not reshuffle a set a teacher has printed.
   */
  function rebuildCardsIfWordsChanged() {
    const bingo = game.games && game.games.bingo;
    if (!bingo || !Array.isArray(game.items) || faceList() === originalFaces) return;

    const sets = bingo.cardSets || [{ size: 4, count: 48 }, { size: 3, count: 48 }];
    bingo.cardSets = sets;
    bingo.cards = window.SharedBingoCards.generateCardSets({
      faces: game.items.map(window.SharedBingoCards.displayFace),
      sets: sets,
      rng: window.SharedRng.makeRng(gameId),
    });
  }

  /* ---------- fields ---------- */

  function textField(className, label, value, onChange) {
    const wrap = el('div', 'field ' + className);
    const input = el('input');
    input.type = 'text';
    input.value = value;
    input.oninput = () => onChange(input.value);
    wrap.append(labelFor(label, input), input);
    return wrap;
  }

  function areaField(label, value, onChange) {
    const wrap = el('div', 'field');
    const area = el('textarea');
    area.rows = 2;
    area.value = value;
    area.oninput = () => onChange(area.value);
    wrap.append(labelFor(label, area), area);
    return wrap;
  }

  function articleField(item) {
    const wrap = el('div', 'field article');
    const select = el('select');
    ['', 'el', 'la', 'los', 'las'].forEach(option => {
      const node = el('option', '', option === '' ? '—' : option);
      node.value = option;
      select.append(node);
    });
    select.value = item.article || '';
    select.onchange = () => { item.article = select.value; touched(); };
    wrap.append(labelFor('Art.', select), select);
    return wrap;
  }

  function labelFor(text, control) {
    const id = 'f' + Math.random().toString(36).slice(2, 9);
    control.id = id;
    const label = el('label', '', text);
    label.htmlFor = id;
    return label;
  }

  function pair(left, right) {
    const row = el('div', 'field-pair');
    row.append(left, right);
    return row;
  }

  function wide(field) {
    field.classList.add('wide');
    return field;
  }

  /* ---------- plumbing ---------- */

  function touched() {
    dirty = true;
    setState('Not saved yet', 'unsaved');
    clearTimeout(checkTimer);
    checkTimer = setTimeout(check, 400);
  }

  function setState(message, kind) {
    state.textContent = message;
    state.className = 'state' + (kind ? ' ' + kind : '');
  }

  // The teacher's work only exists in this page until it is saved.
  window.addEventListener('beforeunload', event => {
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });

  function faceList() {
    return (game.items || []).map(item => window.SharedBingoCards.displayFace(item)).join('|');
  }

  function focusLast(selector) {
    const all = document.querySelectorAll(selector);
    if (all.length) all[all.length - 1].focus();
  }

  function token() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
  }
})();
