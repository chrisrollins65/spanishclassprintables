/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/deckTypes.js (commit 34674dd) by its
 * scripts/sync-site-shared.js. Edit it there and run that script again; an
 * edit made here is lost the next time anyone does.
 */
/**
 * What KIND of thing a game pack tests. Pure: no I/O.
 *
 * A deck type is not a topic. "Los Deportes" and "La Casa" are different topics
 * and the same type: a bank of words, each clued by its English, a gap sentence
 * and a definition. A type is the SHAPE of the pair a game is built from — what
 * is printed in a bingo cell or stands as a board answer, and what kinds of clue
 * can point at it.
 *
 * Shapes, not grammar points, because Spanish grammar has dozens of the second
 * and perhaps four of the first. The pretérito of -ar verbs, adjective agreement
 * and the imperative are one shape — a form in a cell, clued by a formula and a
 * gap sentence — so they are one type with three topics rather than three types.
 * Enumerating grammar points instead would have produced a registry of
 * near-identical entries that all drift apart separately.
 *
 * `bingoCards.js` and `jeopardyBoard.js` are deliberately ignorant of all this:
 * they know faces and clues and nothing about what the faces mean. This file is
 * where that ignorance is paid for, and it is required from `ai/prompts.js`
 * rather than the other way round so the shared writing rules (LEARNER_RULES)
 * stay in one place and a type never has to reach back into the prompts.
 */

/* What an entry is and the rules it must meet, apart from how many to write.
 *
 * Reached through the type rather than named directly, because three prompts
 * need it: the one that generates a bank, the one a person copies out to have a
 * bank written elsewhere and pasted back, and the EDIT prompt that rewrites a
 * few entries of a bank that already exists. An edit not held to these rules
 * drifts straight back to the clues they were written to stop.
 */
const ITEM_RULES = `For each one provide:
- "face": the Spanish word on its own, WITHOUT any article — "luna", "bosque", "valiente", "saltar". It is printed in a small square on a bingo card, so keep it to three words at most.
- "article": "el", "la", "los" or "las" for a noun. Leave it out entirely for anything that is not a noun: an adjective like "valiente" or a verb like "saltar" does not take one.
- "en": the English meaning, equally short (e.g. "the moon").
- "sentence": one simple Spanish sentence with the word replaced by ___ (three underscores). The rest of the sentence must make the missing word unambiguous.
- "sentenceEn": the English translation of that sentence, keeping the ___ in the same place.
- "definition": a short Spanish description of the word, written to the rules above, that never contains the word itself.
- "definitionEn": the English translation of that definition. It must not contain the English word either — describe the thing, do not name it.

Hard rules:
- The Spanish must not be identical to the English. The point is translation practice, so a character or place name spelled the same in both languages ("Mario", "Bowser") is not vocabulary and must not be used. Names only count if the Spanish genuinely differs.
- Prefer everyday nouns, verbs and adjectives a child could reuse outside this topic.
- Every face must be different. No two entries may be the same word.
- No two entries may share a sentence or a definition. Each clue must point at exactly one face, or the game breaks: a child holding a card would have two right answers and no way to choose.
- The gap holds the bare word, so write the sentence as ordinary Spanish and let it supply its own articles: "La ___ le da sabor a la salsa" becomes "La cebolla le da sabor", and "Un hombre ___ no tiene miedo" becomes "Un hombre valiente no tiene miedo". Make the sentence agree with the word in gender and number.
- The sentence must point at the answer ON ITS OWN, for a reader who has never seen this word list. Do not lean on the list to narrow it down. "Voy al ___" is not good enough even if only one place appears on the list; "Voy al ___ a comprar pescado fresco" is, and it teaches three more words while it does it. Say what the thing is for, what happens there, what it is made of — give the child something to understand, not just a slot to fill.
- A gap sentence must be ruled in by its own content, not by grammar alone. "Yo ___ todos los días" is unusable: a dozen words fit it. Put something in the sentence that only the answer can go with — what it is made of, where it happens, what it is used for.
- Run the SWAP TEST on every sentence you write: the other 29 words are printed on the same card, so a sentence two of them fit is a square with two right answers. A sentence that merely puts the answer in a likely scene ("Mi ___ cocina para toda la familia") fails it; one built on what the answer is ("La mamá de mi mamá es mi ___") passes.
- A clue MAY use another word from this same list, and often must: 30 words on one topic describe each other, and a garden's "árbol" is hard to place without "rama". Where two clues are equally clear, prefer the one that does not — all 30 words are printed together on the bingo card, so a child can hear a word they can see — but never make a clue vaguer, and never reach for a word the child does not know, only to avoid one. The clue a child can use wins.
- The ENGLISH meaning is the exception: keep it clear of the other Spanish words on the list. "the frying pan" for "sartén" is read out while "pan" sits on the card, and that overlap teaches nothing because it is an accident of two languages rather than anything about the topic. Say "the pan for frying".
- A sentence must not give the word away by repeating it — use ___ only.
- An entry's OWN sentence and definition must be two different clues, not one clue written twice. "El ___ es rosa y le gusta jugar en el barro" with "Es rosa y le gusta jugar en el barro" is the gap sentence with the gap filled in: the website calls a word out by whichever clue type comes up, so a pack like that has three clue types on paper and two in the room. Come at the word from a different angle — the sentence puts it in a scene, so let the definition say what it IS: what it is made of, what it is for, what kind of thing it is.
- Keep every clue within the WHO THIS IS FOR rules above.`;

const ITEM_SHAPE = '{"face": "luna", "article": "la", "en": "the moon", "sentence": "La ___ brilla en el cielo por la noche.", "sentenceEn": "The ___ shines in the sky at night.", "definition": "Se ve en el cielo por la noche y es blanca.", "definitionEn": "It is seen in the sky at night and it is white."}';

/* Two axes, crossed: what kind of clue it is, and whether it is read or heard.
 *
 * The first version stretched one axis instead — "a definition", then "a harder
 * definition" — which is not a difficulty a model can act on, and produced two
 * rows that read the same. Modality is a real second axis: the same clue is
 * meaningfully harder without the text in front of you, and it buys the board
 * twice the listening practice, which is the thing paper cannot do at all.
 *
 * Within a modality the DEFINITION comes first, which is the opposite of how
 * this started. A gap sentence looks like the gentler clue because it hands the
 * child context, but that is only true with a bingo card in front of them: the
 * slot's gender, number and part of speech cut 24 candidates to a handful
 * before the sentence has even been understood. A quiz team has no card, and
 * without one the definition is the easier clue twice over. Every word of a
 * definition points at the answer, where most of a sentence is scenery — the
 * sauce in "La ___ le da sabor a la salsa" is not what tells you the answer.
 * And a definition asks the same meaning-to-word retrieval the $100 translation
 * just asked, where a gap asks the child to parse a line of Spanish FIRST and
 * infer second. Heard, the gap is harder still: a definition is capped at about
 * ten words, while a sentence with a hole in the middle has to be held in the
 * head whole, which is a memory task stacked on a vocabulary one.
 */
const CLUE_LADDER = [
  { text: 'a direct translation: ask how to say an English word in Spanish. The easiest rung.', audio: false },
  { text: 'a short Spanish definition that describes the answer without naming it. Every word of it points at the answer.', audio: false },
  { text: 'a Spanish sentence with the answer replaced by ___ (three underscores). A whole line of Spanish to read, of which only part narrows the answer.', audio: false },
  { text: 'a short Spanish definition, meant to be HEARD and never shown. The same clue as the $200, minus the reading.', audio: true },
  { text: 'a Spanish sentence with the answer replaced by ___, meant to be HEARD and never shown: a whole sentence to hold in the head with a hole in the middle. The hardest rung.', audio: true },
];

function bankListing(items) {
  return items.map(i => `${[i.article, i.face].filter(Boolean).join(' ')} = ${i.en}`).join('\n');
}

const CATEGORY_SHAPE = '{"name": "Gente de la historia", "clues": [{"value": 100, "prompt": "How do you say «the soldier» in Spanish?", "answer": "soldado"}, {"value": 500, "prompt": "Esta persona guía a los demás hacia la libertad.", "promptEn": "This person leads the others towards freedom.", "answer": "líder", "audio": true}]}';

const FINAL_SHAPE = '{"category": "La comida de la fiesta", "prompt": "En la mesa hay un ___ grande con velas para cantar el cumpleaños.", "promptEn": "On the table there is a big ___ with candles for singing happy birthday.", "answer": "pastel"}';

/* True of every type: a category is a theme, never a kind of question.
 *
 * Lives here rather than in the prompts because each type's own grouping prose
 * ends with it, and prompts.js imports it back for the one quiz prompt that
 * writes its own bank. */
const NOT_A_QUESTION_TYPE = 'Never name a category after a kind of question ("Translations", "Fill in the blank", "Definitions").';

/* The board's own prose, which turned out to be type-specific.
 *
 * Four chunks of the quiz-board prompt describe the BANK rather than the board:
 * what the list is, how to group it into categories, what an answer looks like,
 * and the worked examples behind the swap test. All four are about nouns with
 * articles grouped by meaning, and none of them survives a deck of verb forms —
 * "group these by meaning" has no answer when every face means "to speak".
 *
 * They are prose per type rather than one template with nouns substituted,
 * because a grammar deck does not say the same thing in different words: it has
 * a different thing to say about what makes two of its answers confusable.
 */
const BOARD_PROSE_VOCABULARIO = {
  boardBankIntro: 'Here is the vocabulary the class has been studying. Every answer on the board must come from THIS list, because the students have these words in front of them:',

  boardGrouping: (categoryCount, verb) => `CATEGORIES ARE THEMES, NOT QUESTION TYPES.
${verb} ${categoryCount} categories that group the vocabulary by meaning — people, places, objects, food, feelings, events, whatever this topic actually contains. ${NOT_A_QUESTION_TYPE}`,

  boardGroupingShort: `CATEGORIES ARE THEMES, NOT QUESTION TYPES. A category groups the vocabulary by meaning. ${NOT_A_QUESTION_TYPE}`,

  boardRowSummary: categoryCount =>
    `So the whole $100 row is translations, the whole $400 row is heard definitions, and so on across all ${categoryCount} categories.`,

  boardAnswerField: 'the Spanish word from the list, with its article',

  boardClueRules: () => `A gap clue must point at its answer ON ITS OWN. The teams have no word list in front of them — there is no card to eliminate against — so a sentence that only works by narrowing down a printed list does not work here at all. "Mario entra en ___" is not good enough; "Mario entra en ___ verde para viajar bajo tierra" is, and it teaches more on the way.

The SWAP TEST is stricter here than anywhere else, and it applies to the definitions as much as to the gaps. A bingo player who meets an ambiguous clue still has 25 printed words to narrow it down; a team staring at an empty screen has only what you wrote. So for every clue on this board, put the other bank words into it and make sure not one of them also fits — a clue that describes a situation several of them share ("Esta persona juega contigo en la escuela") has to be rebuilt on what the answer alone is.

Answers are the bare word, without an article — "fontanero", not "el fontanero". A gap therefore holds the bare word, so write the sentence as ordinary Spanish with its own articles: "Mario es un ___ muy famoso" becomes "Mario es un fontanero muy famoso". Make the sentence agree with the answer in gender and number.`,
};

/* The original type, and the only one until grammar decks exist.
 *
 * Its wording is the wording every pack in `output/` was generated from, so it
 * is moved here verbatim rather than rewritten: a pack rebuilt after this change
 * has to come out of the same prompts it came out of before.
 *
 * `roomLabel` is Spanish because it is written into the published room payload
 * beside the game's title; `label` is English because it names the type in the
 * builder, which only the seller ever sees.
 */
const VOCABULARIO = {
  id: 'vocabulario',
  label: 'Vocabulary words',
  roomLabel: 'Bingo de vocabulario',

  clueTypes: ['en', 'sentence', 'definition'],

  // The two halves of the bank prompt, either side of the shared LEARNER_RULES.
  // Split there rather than handed over whole because every type is held to
  // those rules in the same position, and a type that moved them would be
  // answering a different question than the audits ask.
  itemsIntro: topic =>
    `You are a Spanish teacher building a vocabulary bank for classroom games about "${topic}", for English-speaking children who are learning Spanish.`,
  itemsTask: (topic, count) =>
    `Give exactly ${count} Spanish words or short phrases connected to "${topic}".`,

  itemRules: ITEM_RULES,
  itemShape: ITEM_SHAPE,
  ladder: CLUE_LADDER,
  bankListing,
  categoryShape: CATEGORY_SHAPE,
  finalShape: FINAL_SHAPE,

  // Only vocabulary can write a bank from nothing but a topic — see
  // buildQuizGamePrompt, whose reduced entry shape is inlined there.
  quizFromTopic: true,

  /* Which fields the bank editor in step 2 puts on screen.
   *
   * The same question clueTypes asks, aimed at the form rather than the game: a
   * box for a field this type does not have is a box the seller can type into
   * and then lose, and a MISSING box is worse — the formula is a forma deck's
   * cheapest clue, and with no field for it, it would be generated, printed, and
   * never once reviewable. */
  editorFields: { article: true, formula: false, definition: true },

  ...BOARD_PROSE_VOCABULARIO,
};

/* ---------------- forma: a bank of inflected forms ---------------- */

/* The first grammar shape, and the one that covers the most curriculum.
 *
 * A face is a FORM — "hablé", "hablando", "altas", "pon" — and the topic says
 * which paradigm it is drawn from. The pretérito of -ar verbs, adjective
 * agreement, plurals, commands, comparatives, possessives, demonstratives,
 * reflexives, ser vs estar and pretérito vs imperfecto are all this one shape
 * with a different topic, which is the whole reason the registry is shapes.
 *
 * It carries no "definition": there is nothing to define about a verb form, and
 * a deck that tried produced a gloss of the infinitive, which is a different
 * word from the answer. The slot is taken by "prompt" — the Fórmula clue type
 * the site and the caller sheet already have — which names the ingredients and
 * asks for the assembled form.
 *
 * Two banks are both legal and they are not equally easy to clue:
 *   - one tense across many verbs (hablé, comí, escribí) — faces differ in
 *     meaning, so a gap sentence disambiguates the way a vocabulary one does;
 *   - one verb across its paradigm (hablo, hablas, hablé, hablaste) — every face
 *     means nearly the same thing, so a gap can only separate them by SUBJECT
 *     and TIME MARKER.
 * The second is the more useful drill and the easier one to write badly, which
 * is why the swap test below is stated in terms of person and tense rather than
 * meaning. Both are allowed on purpose: restricting to the first would have been
 * a guess, and the audit prompts are what will show whether the second holds up.
 */
const FORMA_ITEM_RULES = `For each one provide:
- "face": the form itself and nothing else — "hablé", "comemos", "pon", "altas". No subject pronoun: "hablé", never "yo hablé". It is printed in a small square on a bingo card, so two words at most (a compound tense like "he hablado" is two).
- "prompt": the FORMULA — the ingredients, not the answer. Person or number, the dictionary word, and the tense or mood, in the shortest form a teacher can read aloud: "yo + hablar (pretérito)", "nosotros + comer (presente)", "tú + poner (mandato)", "alto + femenino plural".
- "en": the English of that form, with the pronoun English needs — "I spoke", "we eat", "put!", "tall".
- "sentence": one simple Spanish sentence with the form replaced by ___ (three underscores), which admits THIS form and no other in the bank. See the swap test below; this is the field that goes wrong.
- "sentenceEn": the English translation of that sentence, keeping the ___ in the same place.

Leave out "article" and "definition" entirely. A form takes no article, and a form cannot be defined — any attempt describes the dictionary word instead, which is a different word from the answer.

Hard rules:
- Every face must be different. The same verb may appear in many persons and tenses, and many verbs may appear in one tense; what may never appear twice is the same form.
- Do not put the answer's own dictionary word in its sentence or its English. "Ayer yo hablé con mi abuela" gives the answer away even with the gap, because the reader has the verb.
- THE SWAP TEST, FOR FORMS. This is the rule this deck lives or dies by, and it is not the vocabulary one. Spanish drops its subject pronouns, so "___ español todos los días" fits hablo, hablas AND habla, and a child holding a card with two of them has no right answer and the teacher has no way to settle it. Put the other forms of this bank into every sentence you write, one at a time. If a second one still fits, the sentence is not finished.
  A sentence must therefore pin BOTH halves:
  - THE PERSON, by naming it. Give the sentence an explicit subject ("Mi hermano ___"), a pronoun ("Nosotros ___"), or a vocative that forces it ("Juan, ¿cuándo ___ tú?"). Leaving the subject to be inferred is what breaks this deck.
  - THE TENSE OR MOOD, by a marker that admits one. Pretérito: ayer, anoche, la semana pasada, el año pasado. Presente: todos los días, siempre, normalmente, ahora. Imperfecto: cuando era niño, antes, todos los veranos. Futuro: mañana, la próxima semana, algún día.
  Bad: "Yo ___ mucho." (fits hablo, hablé, hablaba, hablaré)
  Good: "Ayer yo ___ mucho por teléfono con mi tía." (ayer admits only the pretérito)
  Bad: "___ en el parque los sábados." (no subject at all)
  Good: "Mis primos ___ en el parque los sábados." (subject named, present habitual)
- Everything in the sentence APART from the missing form must be easy. The sentence tests the form, so a hard word anywhere else tests the wrong thing twice over.
- Where a bank holds many forms of ONE verb, the sentences will differ only by subject and time marker, and that is correct — do not reach for exotic situations to make them look different. Vary who is doing it and when; keep the rest plain.
- Keep every clue within the WHO THIS IS FOR rules above.`;

const FORMA_ITEM_SHAPE = '{"face": "hablé", "prompt": "yo + hablar (pretérito)", "en": "I spoke", "sentence": "Ayer yo ___ por teléfono con mi abuela.", "sentenceEn": "Yesterday I ___ on the phone with my grandmother."}';

/* The ladder for forms.
 *
 * Four of the five rungs are different KINDS of clue, not the same clue twice.
 * That is deliberate: for vocabulary the $200/$400 and $300/$500 pairs differ
 * only by modality, which works because hearing a ten-word definition is a real
 * second skill — but stacking two such pairs here would leave three distinct
 * clues across five rows, and a board whose values stop meaning anything.
 *
 * The formula is cheapest because it hands over both the verb and the person, so
 * only assembly is left. The English is dearer: the child has to choose the verb
 * AND inflect it. The transformation at $400 is the only rung that starts from
 * another form, which is the work a conjugation table actually asks for.
 *
 * Only one modality pair remains, $300 and $500, and it earns its place more
 * here than anywhere in a vocabulary deck: catching "ayer" by ear, with no text
 * to go back to, is the whole skill a tense test is after.
 */
const FORMA_LADDER = [
  { text: 'the FORMULA: give the person, the dictionary word and the tense, and ask for the form — "yo + hablar, pretérito". The easiest rung: the verb and the person are both handed over, so only the ending is left.', audio: false },
  { text: 'the ENGLISH of the form — "we were eating" — and ask for the Spanish. Harder than the formula, because the child has to choose the verb as well as inflect it.', audio: false },
  { text: 'a Spanish sentence with the form replaced by ___ (three underscores), whose subject and time marker leave exactly one form of this bank that fits.', audio: false },
  { text: 'a TRANSFORMATION: give a DIFFERENT form of the same word and ask for this one — "de «hablamos» a yo, mismo tiempo". The child has to read the form they are given, work out what it is, and move one part of it.', audio: false },
  { text: 'a Spanish sentence with the form replaced by ___, meant to be HEARD and never shown. The hardest rung: the subject and the time marker are the only things that narrow it, and both go past in one hearing.', audio: true },
];

const FORMA_CATEGORY_SHAPE = '{"name": "El pretérito", "clues": [{"value": 100, "prompt": "yo + hablar (pretérito)", "answer": "hablé"}, {"value": 500, "prompt": "Anoche mis primos ___ toda la pizza.", "promptEn": "Last night my cousins ___ the whole pizza.", "answer": "comieron", "audio": true}]}';

const FORMA_FINAL_SHAPE = '{"category": "El imperfecto de la niñez", "prompt": "Cuando era niño, yo ___ al parque todos los sábados.", "promptEn": "When I was a child, I ___ to the park every Saturday.", "answer": "iba"}';

// The formula travels with every entry: the board is grouped by tense and person,
// so a listing that gave only the form and its English would make the writer
// work out the grammar of all thirty again, and get some of them wrong.
function formaBankListing(items) {
  return items
    .map(i => `${i.face} = ${i.en}${i.prompt ? ` — ${i.prompt}` : ''}`)
    .join('\n');
}

const FORMA = {
  id: 'forma',
  label: 'Verb & word forms (grammar)',
  roomLabel: 'Bingo de formas',

  clueTypes: ['prompt', 'en', 'sentence'],

  itemsIntro: topic =>
    `You are a Spanish teacher building a bank of FORMS for classroom games about "${topic}", for English-speaking children who are learning Spanish. The bank drills one piece of grammar: every entry is a form, and the games ask the child to produce it.`,
  itemsTask: (topic, count) =>
    `Give exactly ${count} Spanish forms that belong to "${topic}". If the topic names one paradigm of one word, work through its persons and numbers; if it names a tense or a pattern across many words, use many words in it. Either is right, and the topic decides which.`,

  itemRules: FORMA_ITEM_RULES,
  itemShape: FORMA_ITEM_SHAPE,
  ladder: FORMA_LADDER,
  bankListing: formaBankListing,
  categoryShape: FORMA_CATEGORY_SHAPE,
  finalShape: FORMA_FINAL_SHAPE,

  // buildQuizGamePrompt inlines a reduced vocabulary entry; a form needs its
  // formula, so a quiz written from a bare topic would be missing the field its
  // own cheapest row asks for.
  quizFromTopic: false,

  // No article on a verb form, no definition of one — and the formula is this
  // deck's cheapest clue, so it needs a box of its own.
  editorFields: { article: false, formula: true, definition: false },

  boardBankIntro: 'Here are the forms the class has been studying. Every answer on the board must be one of THESE forms, because the students have been drilling this list:',

  boardGrouping: (categoryCount, verb) => `CATEGORIES ARE GRAMMATICAL GROUND, NOT QUESTION TYPES.
${verb} ${categoryCount} categories that group the forms by the grammar they share — a tense, a mood, a person, a verb family (-ar, -er, -ir), or one word's own paradigm. "El pretérito", "Mandatos", "Nosotros", "Verbos en -ir". ${NOT_A_QUESTION_TYPE}
A category's name is a promise about its five answers: every answer under "El pretérito" must be a pretérito form.`,

  boardGroupingShort: `CATEGORIES ARE GRAMMATICAL GROUND, NOT QUESTION TYPES. A category groups the forms by a tense, a mood, a person or a verb family, and every answer under it must really be one. ${NOT_A_QUESTION_TYPE}`,

  boardRowSummary: categoryCount =>
    `So the whole $100 row is formulas, the whole $400 row is transformations, the whole $500 row is heard sentences, and so on across all ${categoryCount} categories.`,

  boardAnswerField: 'the form itself, with no subject pronoun — "hablé", never "yo hablé"',

  boardClueRules: () => `THE SWAP TEST, FOR FORMS, and it is stricter here than on the bingo cards. A bingo player can see every form in the bank printed in front of them; a quiz team sees an empty screen. Spanish drops its subject pronouns, so a gap sentence has to pin the PERSON by naming a subject and the TENSE by a marker that admits one — ayer, anoche and la semana pasada for the pretérito; todos los días, siempre and ahora for the presente; cuando era niño and antes for the imperfecto; mañana and la próxima semana for the futuro. Put the other answers on this board into every sentence; if a second one fits, rebuild it.
Bad: "Nosotros ___ mucho." (fits comemos, comimos, comíamos)
Good: "Anoche nosotros ___ mucho en el restaurante." (anoche admits only the pretérito)

A TRANSFORMATION clue gives a different form of the same word and names the one move to make — "de «hablamos» a yo, mismo tiempo", "de «alto» a femenino plural". Change exactly one thing, say which, and never show a form that another square already uses as its answer.

A FORMULA clue names the ingredients and never the answer: the person or number, the dictionary word, and the tense or mood. It is the one row that may be written with no Spanish sentence around it.

Never put the answer's own dictionary word in its clue. A gap sentence holding the infinitive has handed over everything but the ending.`,
};

const DECK_TYPES = { [VOCABULARIO.id]: VOCABULARIO, [FORMA.id]: FORMA };

/**
 * The type a pack is written to.
 *
 * An ABSENT id means vocabulary: every pack built before types existed has no
 * `type` field, and they are all vocabulary decks. An UNKNOWN id throws instead
 * of falling back, because the quiet version of that bug is a grammar pack whose
 * id was mistyped on a Trello card being written to vocabulary rules — it would
 * generate, validate and print, and only read wrong.
 *
 * @param {string} [id]
 * @returns {object} the deck type
 */
function deckType(id) {
  if (!id) return VOCABULARIO;
  const type = DECK_TYPES[id];
  if (!type) {
    throw new Error(`unknown deck type "${id}"; known types: ${Object.keys(DECK_TYPES).join(', ')}`);
  }
  return type;
}

module.exports = { deckType, DECK_TYPES, NOT_A_QUESTION_TYPE };
