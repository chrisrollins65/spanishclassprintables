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
    write: root.dataset.writeUrl,
    writing: root.dataset.writingUrl,
    ask: root.dataset.askUrl,
    answerSheet: root.dataset.answerSheetUrl,
    answerSheetStatus: root.dataset.answerSheetStatusUrl,
    asking: root.dataset.askingUrl,
    publish: root.dataset.publishUrl,
    payload: root.dataset.payloadUrl,
    save: root.dataset.saveUrl,
    play: root.dataset.playUrl,
    games: root.dataset.gamesUrl,
    cards: root.dataset.cardsUrl,
    cardsStatus: root.dataset.cardsStatusUrl,
  };
  const gameId = root.dataset.gameId;
  const isDraft = root.dataset.draft === '1';

  let game = null;          // the payload being edited
  let originalFaces = '';   // the bank as it was loaded, to spot word changes
  let dirty = false;
  let saving = false;
  let checkTimer = null;

  /* What the last Ask AI rewrote, so the teacher can find it.
   *
   * By position, rebuilt on every reply: the whole card is re-rendered from
   * the payload each time, so there are no objects to hang a WeakSet off the
   * way the builder does. An edit nobody can find is an edit nobody reviews.
   */
  let aiChanged = { items: [], clues: [], names: [], final: false };

  /* How much of this credit's AI is gone, or null while it does not matter.
   * Comes with the page so a teacher returning to a spent credit meets a
   * closed box, not an open one that takes their request and refuses it. */
  let aiPercent = root.dataset.aiPercent === '' ? null : Number(root.dataset.aiPercent);

  /* Whether this game's cards are worth putting on paper — see the comment
   * where the section is built. Set again after an edit, because an edit is
   * exactly what makes a claimed pack's printed set out of date. */
  let cardsWorthPrinting = root.dataset.cardsWorthPrinting === '1';

  /* The quiz's team answer sheet, offered only for a game made here — a
   * claimed pack came with one. See TeacherGame::answerSheetIsAvailable. */
  const answerSheetAvailable = root.dataset.answerSheet === '1';

  /* Whether the first word has been shown open yet — see wordsCard. Once per
   * page, not once per render, or a teacher who closes it would have it
   * spring back open the next time anything redraws. */
  let shownWhatAWordHolds = false;

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

    // A model may still be writing this one; the page waits rather than
    // showing a teacher an empty game and letting them wonder.
    if (root.dataset.writing === 'working') watchWriting();

    // Likewise an Ask AI change left running when the tab was closed.
    if (root.dataset.asking === 'working') watchAsking();
  }

  /* Waiting for a model.
   *
   * Polled rather than pushed: one teacher, one game, a wait measured in
   * tens of seconds — a socket would be a lot of machinery for that.
   */
  function watchWriting() {
    renderWaiting();
    let tries = 0;

    (function check() {
      if (tries++ > 120) return;
      setTimeout(function () {
        fetch(urls.writing, { credentials: 'same-origin', cache: 'no-store' })
          .then(res => res.json())
          .then(body => {
            if (body.status === 'ready') return location.reload();
            if (body.status === 'failed') return renderWritingFailed(body.message);
            check();
          })
          .catch(check);
      }, tries < 10 ? 1500 : 3000);
    })();
  }

  function renderWaiting() {
    const waiting = el('div', 'writing');
    waiting.append(el('span', 'spinner'), el('p', '', 'Writing your game… this takes about a minute. You can leave this page and come back.'));
    root.insertBefore(waiting, root.children[1] || null);
  }

  function renderWritingFailed(message) {
    const failed = document.querySelector('.writing');
    if (!failed) return;
    failed.innerHTML = '';
    failed.className = 'checks problems';
    failed.append(el('p', '', message || 'We could not write this one.'));

    const again = el('button', 'btn btn-ghost', 'Try again');
    again.type = 'button';
    again.style.marginTop = '10px';
    again.onclick = () => askAi(again);
    failed.append(again);
  }

  /* Ask a model to write this draft — a second go after a failure, or the
   * first for a teacher who started by hand and changed their mind. */
  async function askAi(button) {
    button.disabled = true;
    try {
      const res = await fetch(urls.write, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() },
      });
      const body = await res.json().catch(() => ({}));

      if (!res.ok && res.status !== 202) {
        button.disabled = false;
        return renderWritingFailed(body.message);
      }

      const existing = document.querySelector('.writing');
      if (existing) existing.remove();
      watchWriting();
    } catch {
      button.disabled = false;
      renderWritingFailed('We could not reach the site. Please try again.');
    }
  }

  /* ---------- Ask AI ---------- */

  /* A box, not a chat.
   *
   * Every request carries the game exactly as it stands — the bank numbered,
   * or the board category by category — so the model always sees what is on
   * the teacher's screen, hand edits included. A chat would carry its own
   * memory of the game instead, which goes stale the moment a row is edited by
   * hand, and would re-send every earlier version of the bank every turn. The
   * budget buys tweaks; it should not be spent on a transcript re-reading
   * itself.
   *
   * What a chat WOULD give is a follow-up that refers back — "I still don't
   * like it, change it again". That is handled with a short window of what was
   * asked and what was done (EditGame::historyOf), which is bounded, so the
   * tenth tweak costs what the first did.
   */
  function askCard(half) {
    const wrap = el('div', 'ask-ai');
    const id = 'ask-' + half;

    // "the words" undersold it: a request reaches every part of an entry —
    // the Spanish, the English, the gap sentence, the definition, both
    // translations — so the label says what it can do, and the hint below
    // names the parts so a teacher knows what they may ask about.
    const label = el('label', 'ask-label', 'Ask AI to change something');
    label.setAttribute('for', id);

    const input = el('input', 'ask-input');
    input.id = id;
    input.type = 'text';
    input.maxLength = 500;
    input.placeholder = half === 'board'
      ? 'e.g. make the $500 clues easier, or rename category 2'
      : 'e.g. make the sentence for 7 simpler, or swap 12 for another word';

    const button = el('button', 'btn btn-primary', 'Ask');
    button.type = 'button';

    /* Out of AI: the box closes rather than staying open and refusing.
     * The game is still fully editable by hand, which is what the note says —
     * this is the end of the model's help, not the end of the teacher's. */
    const spent = aiPercent !== null && aiPercent >= 100;
    if (spent) {
      input.disabled = true;
      button.disabled = true;
    }

    const send = () => {
      const request = input.value.trim();
      if (request) askForChange(request, button, input);
    };
    button.onclick = send;
    input.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); send(); } };

    const row = el('div', 'ask-row');
    row.append(input, button);

    const note = el('p', 'ask-note');
    note.hidden = !spent;
    if (spent) {
      note.className = 'ask-note problems';
      note.textContent = 'This game has used all of its AI. You can still change anything here yourself.';
    }

    wrap.append(label, row, note);

    if (aiPercent !== null) wrap.append(meterNote());

    return wrap;
  }

  function meterNote() {
    const meter = el('div', 'ai-meter');
    meter.append(el('p', '', aiPercent >= 100
      ? 'AI used on this game: 100%.'
      : 'AI used on this game: ' + aiPercent + '%. After that you can still change it yourself.'));

    return meter;
  }

  async function askForChange(request, button, input) {
    // The model is sent the SAVED game, so an unsaved hand edit would be
    // written over by the reply. Save it first rather than lose it.
    if (dirty) await saveQuietly();

    button.disabled = true;
    input.disabled = true;
    showAskNote('Asking for that change…', '');

    try {
      const res = await fetch(urls.ask, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': token(),
        },
        body: JSON.stringify({ request }),
      });
      const body = await res.json().catch(() => ({}));

      if (!res.ok && res.status !== 202) {
        button.disabled = false;
        input.disabled = false;

        return showAskNote(body.message || askError(body), 'problems');
      }

      input.value = '';
      watchAsking();
    } catch {
      button.disabled = false;
      input.disabled = false;
      showAskNote('We could not reach the site. Please try again.', 'problems');
    }
  }

  function askError(body) {
    const errors = body && body.errors;
    const first = errors && Object.keys(errors)[0];

    return first ? errors[first][0] : 'We could not make that change.';
  }

  /* Polled, like the first writing is: one teacher, one game, a wait measured
   * in tens of seconds. */
  function watchAsking() {
    showAskNote('Making that change… this takes under a minute.', 'busy');
    let tries = 0;

    (function poll() {
      if (tries++ > 120) return;
      setTimeout(function () {
        fetch(urls.asking, { credentials: 'same-origin', cache: 'no-store' })
          .then(res => res.json())
          .then(body => {
            if (body.status === 'working') return poll();
            reloadThen(() => (body.status === 'failed'
              ? showAskNote(body.message || 'We could not make that change.', 'problems')
              : afterAsk(body)));
          })
          .catch(poll);
      }, tries < 8 ? 1500 : 3000);
    })();
  }

  /* The reply is merged on the server, so the page reads the game back rather
   * than applying the change itself — one merge, not two that can disagree. */
  async function reloadThen(done) {
    try {
      const res = await fetch(urls.payload, { credentials: 'same-origin', cache: 'no-store' });
      if (res.ok) {
        game = await res.json();
        originalFaces = faceList();
        dirty = false;
      }
    } catch { /* keep what is on screen */ }

    render();
    done();
  }

  function afterAsk(body) {
    const changed = Array.isArray(body.changed) ? body.changed : [];
    // The bank answers by position, the board by category and value.
    const onBoard = changed.length > 0 && typeof changed[0] === 'object';

    aiChanged = {
      items: onBoard ? [] : changed,
      clues: onBoard ? changed : [],
      names: body.renamed || [],
      final: !!body.final_changed,
    };

    markChanged();

    const done = [];
    if (aiChanged.items.length) done.push('Changed ' + aiChanged.items.map(i => '#' + (i + 1)).join(', ') + '.');
    if (aiChanged.clues.length) done.push('Rewrote ' + aiChanged.clues.length + ' clue' + (aiChanged.clues.length === 1 ? '' : 's') + '.');
    if (aiChanged.names.length) done.push('Renamed ' + aiChanged.names.length + ' categor' + (aiChanged.names.length === 1 ? 'y' : 'ies') + '.');
    if (aiChanged.final) done.push('Rewrote the final wager.');
    if (body.removed) done.push('Removed ' + body.removed + ' word' + (body.removed === 1 ? '' : 's') + '.');

    // The model's own note first: it is the only thing that explains a reply
    // that changed nothing.
    const parts = [body.message, done.length ? done.join(' ') : 'Nothing was changed.'];
    if (done.length) parts.push('Changes are highlighted — please check them.');

    // An edit to the words is exactly what makes a claimed pack's printed set
    // out of date, so the server says whether paper is now worth offering.
    if (typeof body.cards_worth_printing === 'boolean' && body.cards_worth_printing !== cardsWorthPrinting) {
      cardsWorthPrinting = body.cards_worth_printing;
      render();
      markChanged();
    }

    showAskNote(parts.filter(Boolean).join(' '), done.length ? 'ready' : '');
    showMeter(body.ai_percent_used);
  }

  /* Put on after the render, because the render is built from the payload and
   * knows nothing about who wrote what. */
  function markChanged() {
    aiChanged.items.forEach(i => {
      const row = document.querySelector('.word-row[data-index="' + i + '"]');
      if (row) row.classList.add('ai-changed');
    });
    aiChanged.clues.forEach(c => {
      const cell = document.querySelector('.board-cell[data-category="' + c.category + '"][data-clue="' + c.clue + '"]');
      if (cell) cell.classList.add('ai-changed');
    });
  }

  /* The one place Ask AI speaks, and deliberately the only one: it sits under
   * the box the teacher just used, where they are already looking. A banner at
   * the top of the page would be out of sight on a thirty-word bank. */
  function showAskNote(text, kind) {
    document.querySelectorAll('.ask-note').forEach(note => {
      note.hidden = !text;
      note.textContent = text;
      note.className = 'ask-note' + (kind ? ' ' + kind : '');
    });
  }

  /* Only once most of the credit's AI is gone: a teacher who never approaches
   * the limit never learns there is one. */
  function showMeter(percent) {
    if (percent == null) return;

    // Kept, so the boxes rebuilt by the next render start in the right state
    // — including closing themselves once the budget is gone.
    aiPercent = percent;
    document.querySelectorAll('.ai-meter').forEach(node => node.remove());
    document.querySelectorAll('.ask-ai').forEach(card => card.append(meterNote()));

    if (percent >= 100) render();
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
    if (isDraft) root.append(draftBar());

    const games = game.games || {};
    const hasWords = Array.isArray(game.items) && game.items.length;

    if (hasWords && games.bingo) root.append(wordsCard());
    if (games.jeopardy) root.append(boardCard());
    /* The online game always follows the words, so this is only about paper.
     * A claimed pack's printed cards are right until a word changes, and the
     * server says so (TeacherGame::cardsAreWorthPrinting) — offering a
     * download to a teacher who has changed nothing sends them to the printer
     * for the set already in their download. A game made here has no other
     * set, so it always offers one. */
    if (games.bingo && cardsWorthPrinting) {
      const cards = bingoNote();
      if (cards) root.append(cards);
    }

    if (games.jeopardy && answerSheetAvailable) root.append(answerSheetNote());
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

  /* A draft: what it is not yet, and the one button that changes that.
   *
   * Publishing is the moment a teacher gets what they paid for, so the bar
   * says plainly what is still missing from their side — it is not playable,
   * it is not printable, and the credit is still theirs to take back. */
  function draftBar() {
    const wrap = el('div', 'draft-bar');

    const words = el('div');
    words.append(el('strong', '', 'This is a draft'));
    words.append(el('p', '', 'Not playable or printable yet, and deleting it gives your credit back.'));
    wrap.append(words);

    const written = (game.items || []).length > 0;

    if (!written && urls.write) {
      const ask = el('button', 'btn btn-ghost', 'Let AI write it');
      ask.type = 'button';
      ask.onclick = () => askAi(ask);
      wrap.append(ask);
    }

    // A real form, not fetch: publishing can be refused for a reason the
    // teacher must read, and Laravel already knows how to send them back with
    // it.
    const form = document.createElement('form');
    form.method = 'post';
    form.action = urls.publish;

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = token();

    const publish = el('button', 'btn btn-primary', 'Publish');
    publish.type = 'submit';

    form.append(csrf, publish);
    wrap.append(form);

    return wrap;
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

    const back = el('a', 'btn btn-ghost', 'My games');
    back.href = urls.games;

    wrap.append(theme, state, save);

    if (!isDraft) {
      const play = el('a', 'btn btn-ghost', 'Play ▸');
      play.href = urls.play;
      wrap.append(play);
    }

    wrap.append(back);
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

    /* The first word starts open.
     *
     * A closed row shows the article, the Spanish and the English, which looks
     * like the whole entry — nothing on it says a gap sentence and a
     * definition are behind the chevron. The lead above says so, and the
     * chevron has a tooltip, but both need reading and a tooltip is nothing on
     * a tablet. One open row shows the shape of an entry instead of describing
     * it, and costs a single screen's worth of scrolling.
     */
    if (!shownWhatAWordHolds && game.items.length) {
      shownWhatAWordHolds = true;
      const first = list.querySelector('.word-row');
      if (first) {
        first.classList.add('open');
        const chevron = first.querySelector('.word-open');
        if (chevron) chevron.setAttribute('aria-expanded', 'true');
      }
    }

    const add = el('button', 'btn btn-ghost add', '+ Add a word');
    add.type = 'button';
    add.onclick = () => {
      game.items.push({ face: '', article: '', en: '' });
      touched();
      render();
      focusLast('.word-row .face');
    };

    card.append(tools, list, add);
    // The box goes with the half it changes: a teacher unhappy with a word
    // should not have to scroll past the board to say so.
    if (urls.ask) card.append(askCard('items'));

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
    // So an Ask AI change can be pointed at once the card is rebuilt.
    row.dataset.index = String(index);

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
    (board.categories || []).forEach((cat, ci) => grid.append(categoryColumn(cat, cheapest, ci)));

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
    if (urls.ask) card.append(askCard('board'));

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

  function categoryColumn(cat, cheapest, ci) {
    const col = el('div', 'board-col');

    // A textarea, not an input: a long name in an input scrolls out of sight,
    // which is how a name too wide for the printed column goes unnoticed.
    const name = el('textarea', 'board-cat');
    name.rows = 2;
    name.value = cat.name || '';
    name.setAttribute('aria-label', 'Category name');
    name.oninput = () => { cat.name = name.value; touched(); };
    col.append(name);

    (cat.clues || []).forEach((clue, qi) => col.append(clueCell(clue, cheapest, ci, qi)));
    return col;
  }

  function clueCell(clue, cheapest, ci, qi) {
    const cell = el('div', 'board-cell');
    // Addressed the way applyBoardEdit reports a change back.
    cell.dataset.category = String(ci);
    cell.dataset.clue = String(qi);

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

  /* What a bingo teacher has to know before they change a word — the cards in
   * their download were printed with the old ones — and the way to get new
   * ones on paper. */
  function bingoNote() {
    const sizes = [...new Set((game.games.bingo.cards || []).map(card => card.size))].sort((a, b) => b - a);
    if (!sizes.length) return null;

    const card = el('div', 'card');
    card.append(el('h2', '', 'Bingo cards'));
    card.append(el('p', 'lead', 'Download and print these bingo cards with the changes you have made.'));

    const row = el('div', 'print-row');
    sizes.forEach(size => row.append(printButton(size)));
    card.append(row);

    return card;
  }

  /* The quiz's equivalent of the bingo cards: the page each team writes on.
   *
   * Blank — the categories and the money, with a box under each — so it gives
   * nothing away and does not go stale when a clue is rewritten. The final's
   * own category is left blank on it for the same reason it is on the printed
   * pack: it is announced when the board is empty, and a team reading it all
   * game has had the thing the bet turns on. */
  function answerSheetNote() {
    const card = el('div', 'card');
    card.append(el('h2', '', 'Team answer sheet'));
    card.append(el('p', 'lead', 'One page per team to write their answers on, with a space for the final wager.'));

    const row = el('div', 'print-row');
    row.append(printableButton({
      label: 'Print the answer sheets',
      busy: 'Making the answer sheets…',
      url: urls.answerSheet,
      body: {},
      poll: () => urls.answerSheetStatus,
    }));
    card.append(row);

    return card;
  }

  /* One size of card, on paper.
   *
   * The site renders it with a real browser, which takes a few seconds and
   * runs one at a time for the whole site, so the button says what is
   * happening and then goes to fetch the file rather than pretending to be
   * instant. An unedited game that was printed before comes back at once: the
   * file is named after the cards themselves.
   */
  function printButton(size) {
    return printableButton({
      label: 'Print the ' + size + '×' + size + ' cards',
      busy: 'Making the ' + size + '×' + size + ' cards…',
      url: urls.cards,
      body: { size: size },
      poll: () => urls.cardsStatus.replace('SIZE', String(size)),
    });
  }

  /* One printable, asked for and waited on.
   *
   * The cards and the team answer sheet differ only in what they are called
   * and what the request says; the render is queued either way, because each
   * one is a whole browser. */
  function printableButton(printable) {
    const button = el('button', 'btn btn-ghost', printable.label);
    button.type = 'button';

    const settle = (label, enabled) => {
      button.textContent = label;
      button.disabled = !enabled;
    };

    button.onclick = async () => {
      settle(printable.busy, false);
      try {
        /* The cards are built from the SAVED words, so an unsaved edit would
         * print yesterday's set. This used to be a line of small print asking
         * the teacher to save first; saving for them is better than telling
         * them, and it is the same quiet save Ask AI does. */
        if (dirty) await saveQuietly();

        const res = await fetch(printable.url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token() },
          body: JSON.stringify(printable.body),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok && res.status !== 202) {
          settle(body.message || 'That did not work — try again', true);
          return;
        }

        const url = body.status === 'ready' ? body.url : await waitForPrintable(printable.poll());
        if (!url) {
          settle('That is taking too long — try again', true);
          return;
        }
        window.location.href = url;
        settle(printable.label, true);
      } catch {
        settle('We could not reach the site — try again', true);
      }
    };

    return button;
  }

  /* Waits for the worker to finish. Polled rather than pushed: one file, one
   * teacher, and a socket for this would be a lot of moving parts for a wait
   * that is usually over before the third check. */
  async function waitForPrintable(url) {
    for (let attempt = 0; attempt < 40; attempt++) {
      await new Promise(resolve => setTimeout(resolve, attempt < 5 ? 700 : 1500));
      const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) return null;
      const body = await res.json();
      if (body.status === 'ready') return body.url;
    }
    return null;
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

  function saveGame(button) {
    return persist(button);
  }

  /* Saving with nothing to click: Ask AI sends the SAVED game, so an unsaved
   * hand edit has to reach the server before the request does or the model's
   * reply would be merged into a game that never had it. */
  function saveQuietly() {
    return persist(null);
  }

  async function persist(button) {
    if (saving) return;
    saving = true;
    if (button) button.disabled = true;
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
      if (button) button.disabled = false;
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

    // The words moved, so whatever is printed no longer matches them.
    cardsWorthPrinting = true;

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
