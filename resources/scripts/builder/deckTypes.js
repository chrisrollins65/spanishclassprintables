/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/deckTypes.js (commit c7bc695) by its
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
 * where that ignorance is paid for.
 *
 * It is required FROM `ai/prompts.js` and never the other way round, so a type
 * never reaches back into the prompts. What stayed behind there is the one thing
 * every type shares: LEARNER_AUDIENCE, who the child is. What makes a good clue
 * came here as each type's `clueRules`, because it is not the same question for a
 * noun and for a verb form — see FORMA_CLUE_RULES.
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
  { text: 'a direct translation: ask how to say an English word in Spanish. The easiest rung.', form: 'translation', audio: false, audited: false },
  { text: 'a short Spanish definition that describes the answer without naming it. Every word of it points at the answer.', form: 'definition', audio: false },
  { text: 'a Spanish sentence with the answer replaced by ___ (three underscores). A whole line of Spanish to read, of which only part narrows the answer.', form: 'gap', audio: false },
  { text: 'a short Spanish definition, meant to be HEARD and never shown. The same clue as the $200, minus the reading.', form: 'definition', audio: true },
  { text: 'a Spanish sentence with the answer replaced by ___, meant to be HEARD and never shown: a whole sentence to hold in the head with a hole in the middle. The hardest rung.', form: 'gap', audio: true },
];

function bankListing(items) {
  return items.map(i => `${[i.article, i.face].filter(Boolean).join(' ')} = ${i.en}`).join('\n');
}

const CATEGORY_SHAPE = '{"name": "Gente de la historia", "clues": [{"value": 100, "prompt": "How do you say «the soldier» in Spanish?", "answer": "soldado"}, {"value": 500, "prompt": "Esta persona guía a los demás hacia la libertad.", "promptEn": "This person leads the others towards freedom.", "answer": "líder", "audio": true}]}';

const FINAL_SHAPE = '{"category": "La comida de la fiesta", "prompt": "En la mesa hay un ___ grande con velas para cantar el cumpleaños.", "promptEn": "On the table there is a big ___ with candles for singing happy birthday.", "answer": "pastel"}';

/* What makes a good clue for a bank of WORDS.
 *
 * Moved here from LEARNER_RULES unchanged. It opens on the two universal
 * principles — a clue must be easier than its answer, and easy words must still
 * point at one answer — and then says what that means for a noun you can
 * picture, which is the part no other type can reuse.
 */
const VOCABULARIO_CLUE_RULES = `- A clue must be EASIER than its answer. Apart from the answer, every word in a sentence or a definition must be one a beginner already knows: the body (cabeza, mano, pie, ojos, boca), colours, numbers, sizes (grande, pequeño), home, school, family, food, the weather, and everyday verbs (ser, estar, tener, ir, usar, llevar, poner, jugar, comer, ver, hacer). If a clue only works with a word harder than the answer, rewrite it.
- Describe it; do not define it. Say what you do with it, where you see it, what it looks like — and talk to the child. Never write a dictionary definition, and never open with a category word such as objeto, cosa, prenda, calzado, vehículo, instrumento, terreno, tejido or marco.
  Bad: "Objeto duro que se pone para no lastimarse el cráneo." (el casco)
  Good: "Te lo pones en la cabeza para no hacerte daño."
  Bad: "Calzado con ruedas para moverse por superficies lisas." (los patines)
  Good: "Te los pones en los pies y vas muy rápido."
- A definition is about ten words at most. Short, concrete, everyday.
- Gap sentences follow the same rule: the context that points at the answer must be made of words a beginner knows.
- SIMPLE IS NOT VAGUE. Easy words must still point at ONE answer. If a clue in easy words could fit two things, add another concrete detail in easy words — colour, size, where it is, when you use it — never a harder word.
  Too vague: "Te la pones en el cuerpo para jugar." (could be a shirt, a uniform, a coat)
  Better: "Te la pones arriba para jugar, y tiene tu número."
- THE SWAP TEST, on every clue before you keep it: put the OTHER words of this pack into it, one at a time. If a second one still makes sense, the clue is not finished — rewrite it, do not move on. A clue must rest on something true of the answer ALONE: what it is made of, what it does that nothing else on the list does, who it is to you. A scene the answer merely appears in is not enough, because everything else in the pack appears in that scene too.
  Too vague: "Mi ___ usa la herramienta en el taller los sábados." (the father fits — so does the uncle, the neighbour, the brother)
  Better: "El hermano de mi mamá es mi ___ y viene a comer los domingos."
  This bites hardest where the pack holds a family of similar words — people, places, foods — that share one scene. There, name the relation or the property, never the scene.`;

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

  // The final wager's two fields. Vocabulary's are the words that used to be
  // written into finalRules itself, so its prompts are unchanged.
  promptEnField: '- "promptEn": the English of the prompt, for a teacher to reveal if a class is stuck. Keep the ___ in place for a gap clue. Omit this for the $100 row, whose prompt is already English.',
  finalPromptEnField: '- "promptEn": the English of the sentence, with the ___ in the same place.',
  finalPromptField: '- "prompt": a Spanish sentence with the answer replaced by ___ (three underscores), written to all the rules above. It is shown on screen, never read aloud.',
  finalAnswerField: 'the Spanish word from the vocabulary list, bare and without its article.',

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
  // One line under the label on the website's create form. What a teacher is
  // choosing between, in their words rather than the registry's.
  blurb: 'Thirty words your class is learning — food, the house, animals. The usual choice.',
  roomLabel: 'Bingo de vocabulario',

  clueTypes: ['en', 'sentence', 'definition'],

  // Both games. A word fits a bingo square and stands as a board answer.
  games: ['bingo', 'quiz'],

  // The two halves of the bank prompt, either side of the shared LEARNER_RULES.
  // Split there rather than handed over whole because every type is held to
  // those rules in the same position, and a type that moved them would be
  // answering a different question than the audits ask.
  itemsIntro: topic =>
    `You are a Spanish teacher building a vocabulary bank for classroom games about "${topic}", for English-speaking children who are learning Spanish.`,
  itemsTask: (topic, count) =>
    `Give exactly ${count} Spanish words or short phrases connected to "${topic}".`,

  // The bank audit's second question. What "specific" means depends on what
  // kind of thing the answer is, so it belongs to the type.
  auditSpecific: `"specific": could someone who has NEVER SEEN the list still land on the intended word, from the clue alone? Judge this without the list. "Voy al ___" is not specific — a dozen places fit it — even when only one of them happens to be on the list. "Voy al ___ a comprar pescado fresco" is. The quiz game gives children no list at all, so a clue that only works by elimination does not work there.`,
  clueRules: VOCABULARIO_CLUE_RULES,
  itemRules: ITEM_RULES,
  itemShape: ITEM_SHAPE,
  ladder: CLUE_LADDER,
  bankListing,
  categoryShape: CATEGORY_SHAPE,
  finalShape: FINAL_SHAPE,

  // Only vocabulary can write a bank from nothing but a topic — see
  // buildQuizGamePrompt, whose reduced entry shape is inlined there.
  quizFromTopic: true,

  // Nothing to check: a word is not derived from anything.
  formAudit: false,

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
/* What makes a good clue for a bank of FORMS.
 *
 * The same two principles vocabulary opens on, and then a different answer to
 * "what could this clue also fit?" — which is the whole of what a clue has to
 * defend against, and it is not the same question here.
 *
 * For a noun, two answers are confusable when they share a SCENE: the father and
 * the uncle both come to dinner. For a form, they are confusable when they share
 * a MEANING, which in a paradigm is all of them — hablo, hablas and habla are one
 * meaning in three persons. So the vocabulary advice to rest a clue on "what it
 * is made of, what it does" is not merely unhelpful here, it points away from the
 * only two things that separate the answers: who is doing it and when.
 */
const FORMA_CLUE_RULES = `- A clue must be EASIER than its answer. Apart from the form you are asking for, every word in a sentence must be one a beginner already knows: the body, colours, numbers, sizes, home, school, family, food, the weather, and everyday verbs (ser, estar, tener, ir, hacer, jugar, comer, ver). The child is working out an ENDING; make everything else free.
- The clue tests the FORM, so nothing else in it may be hard. A sentence that makes a child decode two unknown words before they reach the blank has tested vocabulary and called it grammar.
- SIMPLE IS NOT VAGUE. Easy words must still point at ONE form. If a sentence could take two forms from this bank, do not reach for a harder word — add the two things that are free and decisive: name the SUBJECT, and put in a TIME MARKER.
  Too vague: "Yo ___ mucho." (hablo, hablé, hablaba and hablaré all fit)
  Better: "Ayer yo ___ mucho por teléfono con mi tía."
- THE SWAP TEST, on every clue before you keep it: put the OTHER forms of this pack into it, one at a time. If a second one still fits, the clue is not finished — rewrite it, do not move on. A clue must rest on what is true of this form ALONE, and for a form that is only ever two things:
  WHO — Spanish drops its subject pronouns, so a bare "___ español todos los días" fits hablo, hablas AND habla. Name the subject: a pronoun ("Nosotros ___"), a person ("Mi hermano ___"), or a vocative that forces it ("Juan, ¿cuándo ___ tú?").
  WHEN — the tense has to be forced by a marker that admits one. Pretérito: ayer, anoche, la semana pasada, el año pasado. Presente: todos los días, siempre, normalmente, ahora. Imperfecto: cuando era niño, antes, todos los veranos. Futuro: mañana, la próxima semana, algún día.
  Too vague: "Mis primos ___ en el parque." (juegan, jugaron, jugaban all fit)
  Better: "Los sábados mis primos ___ en el parque con nosotros."
  This bites hardest where the pack holds one word's whole paradigm, because then EVERY face means the same thing and who-and-when is all you have. There, vary the subject and the time marker and keep the rest of the sentence plain — do not invent exotic situations to make the sentences look different from each other.
- WHICH WORD — and this one is not optional. Give the dictionary word in brackets right after the blank: "Dudo que nosotros ___ (vivir) en una casa pequeña."
  Without it the clue is usually unanswerable. That sentence takes vivamos, but it takes quepamos, estemos and durmamos just as well, and a team with no card in front of them has no way to know which verb you had in mind. Person and tense they can work out; WHICH VERB they cannot, because nothing in the sentence says.
  Brackets even where the pack is one word's paradigm. It costs nothing there — the child already knows the verb — and it is the form that is being asked for, never the verb. The verb is what you are given; the ending is what you produce. This is how every conjugation exercise in every Spanish textbook is written, so it needs no explaining to a class.
  Keep the ___ as well as the brackets. The blank is where the answer goes and the brackets say which word it is built from.`;

const FORMA_ITEM_RULES = `For each one provide:
- "face": the form itself and nothing else — "hablé", "comemos", "pon", "altas". No subject pronoun: "hablé", never "yo hablé". It is printed in a small square on a bingo card, so two words at most (a compound tense like "he hablado" is two).
- "prompt": the FORMULA — the ingredients, not the answer. Person or number, the dictionary word, and the tense or mood, in the shortest form a teacher can read aloud: "yo + hablar (pretérito)", "nosotros + comer (presente)", "tú + poner (mandato)", "alto + femenino plural".
- "en": the English of that form, with the pronoun English needs — "I spoke", "we eat", "put!", "tall".
- "sentence": one simple Spanish sentence with the form replaced by ___ (three underscores), which admits THIS form and no other in the bank. See the swap test below; this is the field that goes wrong.
- "sentenceEn": the English translation of that sentence, keeping the ___ in the same place.

Leave out "article" and "definition" entirely. A form takes no article, and a form cannot be defined — any attempt describes the dictionary word instead, which is a different word from the answer.

Hard rules:
- Every face must be different. The same verb may appear in many persons and tenses, and many verbs may appear in one tense; what may never appear twice is the same form.
- GIVE THE DICTIONARY WORD IN BRACKETS, right after the blank: "Ayer yo ___ (hablar) por teléfono con mi abuela." Never write the finished form into the sentence — "Ayer yo hablé con mi abuela" has no question left in it — but the INFINITIVE is not the answer, it is what the answer is built from, and without it a sentence across several verbs has no single right answer. "Dudo que nosotros ___ en una casa pequeña" takes vivamos, quepamos and estemos equally.
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

const FORMA_ITEM_SHAPE = '{"face": "hablé", "prompt": "yo + hablar (pretérito)", "en": "I spoke", "sentence": "Ayer yo ___ (hablar) por teléfono con mi abuela.", "sentenceEn": "Yesterday I ___ (to speak) on the phone with my grandmother."}';

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
  { text: 'the FORMULA: give the person, the dictionary word and the tense, and ask for the form — "yo + hablar, pretérito". The easiest rung: the verb and the person are both handed over, so only the ending is left.', form: 'formula', audio: false, audited: false },
  { text: 'the ENGLISH of the form — "we were eating" — and ask for the Spanish. Harder than the formula, because the child has to choose the verb as well as inflect it.', form: 'english', audio: false },
  { text: 'a Spanish sentence with the form replaced by ___ (three underscores), whose subject and time marker leave exactly one form of this bank that fits.', form: 'gap', audio: false },
  { text: 'a TRANSFORMATION, written as a plain instruction a child can read: "Cambia «hablamos» a la forma de «yo»." or "Escribe «vamos» en subjuntivo." Give one form of a word and ask for another. The form you SHOW and the form you ASK FOR must be different strings. Take the one you show from a DIFFERENT person or tense of the same verb — and note that when the column itself is a person, that means reaching OUTSIDE this column for it, because everything inside the column is already in the person you are asking for. "Cambia «tenga» a la forma de «yo»" answered "tenga" is not a question; "Cambia «tengas» a la forma de «yo»" answered "tenga" is. Name ONLY what changes, and BOTH forms must be on the list: the one you show and the one you are asking for.', form: 'transformation', audio: false, audited: false },
  { text: 'a Spanish sentence with the form replaced by ___, meant to be HEARD and never shown. The hardest rung: the subject and the time marker are the only things that narrow it, and both go past in one hearing.', form: 'gap', audio: true },
];

const FORMA_CATEGORY_SHAPE = '{"name": "El pretérito", "clues": [{"value": 100, "prompt": "yo + hablar (pretérito)", "answer": "hablé"}, {"value": 400, "prompt": "Cambia «comimos» a la forma de «ellos».", "promptEn": "Change «comimos» to the «ellos» form.", "answer": "comieron"}, {"value": 500, "prompt": "Anoche mis primos ___ (comer) toda la pizza.", "promptEn": "Last night my cousins ___ (to eat) the whole pizza.", "answer": "comieron", "audio": true}]}';

const FORMA_FINAL_SHAPE = '{"category": "El imperfecto de la niñez", "prompt": "Cuando era niño, yo ___ (ir) al parque todos los sábados.", "promptEn": "When I was a child, I ___ (to go) to the park every Saturday.", "answer": "iba"}';

// The formula travels with every entry: the board is grouped by tense and person,
// so a listing that gave only the form and its English would make the writer
// work out the grammar of all thirty again, and get some of them wrong.
/* Grouped by the dictionary word the form is built from, not listed flat.
 *
 * The transformation row asks for one form of a word given another — and both
 * ends have to be on the list, because the board may only answer with what the
 * class was given. Told that as a rule and handed a flat list of thirty forms,
 * the model worked the answer out instead of looking it up, and a third of the
 * row came back as forms nobody had: "de «habría bebido» a ellos" answered
 * "habrían bebido", which is correct Spanish and not in the bank.
 *
 * Grouping fixes it by showing rather than asking. A word with two forms under
 * it is a transformation already written; a word with one cannot be used for
 * that row at all, and now that is visible at a glance instead of being a rule
 * to hold in mind while writing twenty-five clues.
 */
function formaBankListing(items) {
  const lemma = i => {
    // The dictionary word out of "yo + hablar (pretérito)". Falls back to the
    // whole formula, then to the form itself, so nothing is ever dropped.
    const m = /\+\s*([^(]+)/.exec(i.prompt || '');
    return (m ? m[1] : i.prompt || i.face).trim();
  };

  const groups = new Map();
  items.forEach(i => {
    const key = lemma(i);
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(i);
  });

  return [...groups.entries()]
    .map(([word, forms]) => {
      const line = forms
        .map(i => `${i.face} = ${i.en}${i.prompt ? ` (${i.prompt})` : ''}`)
        .join('; ');
      return `${word}: ${line}`;
    })
    .join('\n');
}

const FORMA = {
  id: 'forma',
  label: 'Verb & word forms (grammar)',
  blurb: 'One piece of grammar drilled: the pretérito, commands, plurals, adjective agreement, ser vs estar.',
  roomLabel: 'Bingo de formas',

  clueTypes: ['prompt', 'en', 'sentence'],

  // Both games: a form is one word, so it fits a square and a board answer alike.
  games: ['bingo', 'quiz'],

  itemsIntro: topic =>
    `You are a Spanish teacher building a bank of FORMS for classroom games about "${topic}", for English-speaking children who are learning Spanish. The bank drills one piece of grammar: every entry is a form, and the games ask the child to produce it.`,
  itemsTask: (topic, count) =>
    `Give exactly ${count} Spanish forms that belong to "${topic}".

HOW TO SPREAD THEM. Read the topic and decide which of these it is:
- ONE PARADIGM of one word — work through its persons and numbers.
- ONE TENSE OR PATTERN across many words — use many different words in it. AT LEAST TEN different dictionary words, not five worked through all their persons. A quiz board needs twenty-six different answers and takes them all from this list; five verbs leaves it nothing to reach for but the same five, and a class meets «hablar» in half the squares. Breadth of words first, persons second.
- SEVERAL STRUCTURES, NAMED TO BE COMPARED — "pretérito y imperfecto", "ser y estar", "los cuatro condicionales". COVER EVERY ONE OF THEM, in roughly equal numbers. Count them before you start and divide the ${count} between them.

The third is the one that goes wrong, and it goes wrong quietly. A bank that covers only one of the structures still looks like a finished bank — thirty good forms, every field filled — but the games built on it can only ever ask about that one structure, because every answer they are allowed to use comes from this list. The teacher asked for the contrast; a bank without it cannot be rescued later by rewriting clues.

WHERE A STRUCTURE IS A SENTENCE WITH TWO VERBS IN IT, the bank needs forms from BOTH halves or every clue must ask about the same half. "Si lloviera, me mojaría" is an imperfect subjunctive AND a conditional. So "los cuatro condicionales" is not four things to cover but six: the present and the future (si llueve, me mojaré), the imperfect subjunctive and the conditional (si lloviera, me mojaría), and the pluperfect subjunctive and the conditional perfect (si hubiera llovido, me habría mojado).

Name the structure each form belongs to in its "prompt", so the board can group by it: "yo + mojarse (condicional)", "él + llover (imperfecto de subjuntivo)".`,

  // A form's gap carries a whole situation, so the vocabulary question is the
  // right one and a real bank passed it.
  auditSpecific: VOCABULARIO.auditSpecific,
  clueRules: FORMA_CLUE_RULES,
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

  // A form IS derived — from a word, a person and a tense — so it can be wrong
  // in a way no string check sees. See buildFormAuditPrompt.
  formAudit: true,

  // No article on a verb form, no definition of one — and the formula is this
  // deck's cheapest clue, so it needs a box of its own.
  editorFields: { article: false, formula: true, definition: false },

  boardBankIntro: 'Here are the forms the class has been studying. Every answer on the board must be one of THESE forms, because the students have been drilling this list:',

  boardGrouping: (categoryCount, verb) => `CATEGORIES ARE GRAMMATICAL GROUND, NOT QUESTION TYPES.
${verb} ${categoryCount} categories that group the forms by the grammar they share — a tense, a mood, a person, a verb family (-ar, -er, -ir), or one word's own paradigm. "El pretérito", "Mandatos", "Nosotros", "Verbos en -ir". ${NOT_A_QUESTION_TYPE}
A category's name is a promise about its five answers: every answer under "El pretérito" must be a pretérito form.

COUNT BEFORE YOU CHOOSE THE CATEGORIES. Each column needs FIVE DIFFERENT forms from the list, and the final wager a twenty-sixth that no square used. So a category is only possible if the list holds five forms that belong under its name.

Which axis to group on depends on what the list actually spreads across:
- SEVERAL TENSES OR STRUCTURES on the list — group by those. "El pretérito", "El condicional", "Condicional 0". Safest, because the list was spread across them deliberately.
- ONE STRUCTURE ONLY, every form sharing a tense or mood — then that axis is gone, and the one that works is the PERSON: "La forma de «yo»", "Nosotros", "Ellos y ellas". Five persons, and each column takes a different verb in each row, so five columns of five different forms fall out of the list without strain.
- NEVER one dictionary word per column — "El verbo tener", "Verbo Hacer". That is the grouping that fails: a word with five forms on the list leaves no spare and a word with four cannot fill a column at all, so the squares end up repeating an answer. Verb families (-ar, -er, -ir) fail the same way when the list holds only two or three verbs of a family.

TWO PERSONS THAT SHARE A FORM CANNOT BE TWO COLUMNS. In the present subjunctive, the imperfect and the conditional, «yo» and «él/ella» are the SAME WORD — hable, hablaba, hablaría — so a board with a column for each is guaranteed to use the same answer twice however carefully it is filled. Where a tense collapses two persons like that, treat them as one column ("yo / él / ella") and find the fifth column elsewhere, or group on something other than the person.

ONE AXIS FOR ALL FIVE COLUMNS. Four columns of persons and a fifth called "Verbos útiles" is how the same form ends up in two columns: the catch-all overlaps every other category, because a useful verb has a «yo» form too. If the axis gives you only four columns the list can fill, it is the wrong axis.

Where you cannot fill a column without using an answer twice, you have chosen the wrong axis — change it rather than repeating the answer.`,

  boardGroupingShort: `CATEGORIES ARE GRAMMATICAL GROUND, NOT QUESTION TYPES. A category groups the forms by a tense, a mood, a person or a verb family, and every answer under it must really be one. ${NOT_A_QUESTION_TYPE}`,

  boardRowSummary: categoryCount =>
    `So the whole $100 row is formulas, the whole $400 row is transformations, the whole $500 row is heard sentences, and so on across all ${categoryCount} categories.`,

  boardAnswerField: 'the form itself, with no subject pronoun — "hablé", never "yo hablé"',

  promptEnField: '- "promptEn": the English of the prompt, for a teacher to reveal if a class is stuck. Keep the ___ in place for a gap clue. Omit this for the $100 row, which is already a formula.',
  finalPromptEnField: '- "promptEn": the English of the sentence, with the ___ in the same place.',
  finalPromptField: '- "prompt": a Spanish sentence with the form replaced by ___ (three underscores) and the dictionary word in brackets after it, written to all the rules above. It is shown on screen, never read aloud.',
  finalAnswerField: 'the FORM from the list, with no subject pronoun. Not the dictionary word and not a whole sentence — one form, exactly as it is written on the list.',

  boardClueRules: () => `THE SWAP TEST, FOR FORMS, and it is stricter here than on the bingo cards. A bingo player can see every form in the bank printed in front of them; a quiz team sees an empty screen. Spanish drops its subject pronouns, so a gap sentence has to pin the PERSON by naming a subject and the TENSE by a marker that admits one — ayer, anoche and la semana pasada for the pretérito; todos los días, siempre and ahora for the presente; cuando era niño and antes for the imperfecto; mañana and la próxima semana for the futuro. Put the other answers on this board into every sentence; if a second one fits, rebuild it.
Bad: "Nosotros ___ mucho." (fits comemos, comimos, comíamos)
Good: "Anoche nosotros ___ mucho en el restaurante." (anoche admits only the pretérito)

A TRANSFORMATION clue is an INSTRUCTION, written as a sentence a child reads and acts on:
  "Cambia «hablamos» a la forma de «yo»."   (the person changes)
  "Escribe «vamos» en subjuntivo."          (the mood changes)
  "Escribe «alto» en femenino plural."      (the agreement changes)
Not shorthand. "de «vamos» a nosotros, subjuntivo" is three pieces of grammar notation in a row, and it is wrong as well as hard to read — «vamos» is ALREADY nosotros, so naming the person says nothing and sends the class looking for a change that is not there. Name ONLY the thing that moves, in words, with the verb in «quotes» so it is clear what is being worked on.

THE TWO FORMS MUST BE DIFFERENT, and this is the way this row fails. "Cambia «sea» a la forma de «yo»" whose answer is «sea» asks the team to change nothing: the form was handed to them and handing it back is the answer. Pick the pair FIRST — two forms of one word, both on the list, that are not the same string — and only then write the instruction that gets from one to the other.
BOTH ENDS MUST BE ON THE LIST. Find two forms of the same word that are both there, show one and ask for the other. It is the only row where it is easy to forget, because the answer is something you WORK OUT rather than something you pick — and a worked-out form that nobody was given is a square the teams cannot win. If no two forms of one word are both on the list, do not force it: ask that square some other way.
Never show a form that another square already uses as its answer.

WHERE A SENTENCE HAS TWO VERBS IN IT, only ONE of them is the blank, and it must be a form from the list above. Write the other one out in full. "Si ___ más, sacarías mejores notas" is right when "estudiaras" is on the list; "Si estudiaras más, ___ mejores notas" is right when "sacarías" is. What you may never do is blank one verb and answer with a form that is not on the list — the teams are given these forms and no others, and a clue whose answer is not among them cannot be marked right.
Count the list before you write. Every answer on this board, and the final wager's, has to be a DIFFERENT form from it, so there are only a few to spare.

A FORMULA clue names the ingredients and never the answer: the person or number, the dictionary word, and the tense or mood. It is the one row that may be written with no Spanish sentence around it.

Never put the answer's own dictionary word in its clue. A gap sentence holding the infinitive has handed over everything but the ending.`,
};

/* ---------------- palabra: a bank of function words ---------------- */

/* The little words that hold a sentence together, and the hardest half of
 * beginner Spanish to drill on paper.
 *
 * Prepositions, question words, object and relative pronouns, conjunctions,
 * negation, articles. A child meets them constantly and can rarely say what any
 * one of them MEANS, because most of them do not mean anything on their own —
 * "por" is for, by, through, because of and per, and which one it is depends
 * entirely on the slot it sits in.
 *
 * That is why this is its own shape rather than vocabulary with a short word in
 * it. Two nouns are confusable when they share a SCENE and two forms when they
 * share a MEANING; two function words are confusable when they share a SLOT —
 * "Voy ___ el parque" takes a, para, hacia and hasta, and every one of them is
 * grammatical. A clue that only makes the grammar work has not narrowed
 * anything, which is the opposite of the vocabulary case, where grammar is
 * usually the thing that saves an ambiguous sentence.
 *
 * It carries no article and no formula, and it keeps "definition" — not to
 * define the word, which cannot be done, but to say what it DOES. That reuse is
 * deliberate: the caller sheet, the site's clue registry and the bank editor all
 * already have a Definición, so a third Spanish clue type costs nothing
 * anywhere.
 *
 * ONE TOPIC WILL USUALLY NOT FILL A BANK. There are about fifteen everyday
 * prepositions and eight question words, and a 4x4 card needs sixteen faces
 * while a 5x5 board needs twenty-six answers. A topic has to reach across
 * families to get there; the item rules say so, and the existing floors refuse
 * the bank that does not.
 */
const PALABRA_CLUE_RULES = `- A clue must be EASIER than its answer. The answer is a word of two or three letters, so this bites harder here than anywhere: everything AROUND the blank has to be words a beginner already knows — the body, colours, numbers, home, school, family, food, and everyday verbs (ser, estar, tener, ir, hacer, ver, comer, jugar, querer).
- Say what the word DOES, never what it means. These words mostly do not mean anything on their own. "Se usa para decir a dónde vas" is a clue a child can act on; "preposición de lugar" is a grammar label, and naming the part of speech is never a clue — half the bank is the same part of speech.
- SIMPLE IS NOT VAGUE. Easy words must still point at ONE word. If a sentence could take two of this bank's words, do not reach for a harder sentence — change the RELATION so that only one word expresses it.
  Too vague: "Voy ___ el parque." (a, para, hacia and hasta all fit)
  Better: "Camino ___ la escuela todos los días, pero nunca llego hasta la puerta."
- THE SWAP TEST, and here it is about the SLOT. Put the other words of this pack into every sentence, one at a time. Two function words are confusable when they fit the same hole, and most of them are GRAMMATICAL in the same hole — that is the whole difficulty. A sentence is only finished when the other candidates become WRONG IN MEANING, not merely odd.
  Ask of every sentence: what exactly is this word doing here? Showing where something is going, or why, or for whom, or for how long? Then make the sentence say that and nothing else.
  Too vague: "Este regalo es ___ mi hermana." (por and para both fit, and both are correct Spanish)
  Better: "Compré este regalo ___ mi hermana porque mañana es su cumpleaños." (it is the person who RECEIVES it, so only para)
- A question word is pinned by the ANSWER it expects, so put the answer in the sentence around it: "¿___ vive tu abuela? — En una casa azul." fixes dónde, where "¿___ es tu abuela?" would not.
- A pronoun is pinned by naming its referent and its job: "Mi mamá hizo galletas y ___ dio a mis hermanos" is clear because the galletas are named and they are what is given.
- Many of these words have SEVERAL uses, and that is the point of the pack rather than a problem with it. Pick the use a beginner meets first, write the clue for that one, and let the board's two-sentence row teach the range.
- Keep every clue within the WHO THIS IS FOR rules above.`;

const PALABRA_ITEM_RULES = `For each one provide:
- "face": the word itself — "por", "cuándo", "nos", "pero", "sin". Two words where the word really is two ("por qué", "a veces").
- "en": the English, which for these words is usually several — give the one or two that match the sentence you wrote, separated by a slash: "for / through", "when", "us / to us". Do not write a grammar label.
- "sentence": one simple Spanish sentence with the word replaced by ___ (three underscores), which only THIS word fits in meaning. This is the field that goes wrong; see the swap test above.
- "sentenceEn": the English translation of that sentence, keeping the ___ in the same place.
- "definition": a short Spanish line saying what the word DOES, never what it means and never naming its part of speech — "Se usa para decir a dónde vas", "Se usa para preguntar por un lugar".
- "definitionEn": the English of that line.

Leave out "article" and "prompt" entirely. These words take no article, and they are not built from anything, so there is no formula to give.

Hard rules:
- REACH ACROSS FAMILIES. There are only about fifteen everyday prepositions and eight question words, and this bank needs enough DIFFERENT words to fill a bingo card and a board. Draw on prepositions, question words, object pronouns, conjunctions, words of negation and words of frequency together unless the topic plainly names one family and can still reach the count.
- Every face must be different, and no two entries may share a sentence or a definition.
- The sentence must not contain the answer anywhere else in it. These words are common, so check the whole line: "Voy por el parque por la tarde" cannot be the clue for "por".
- A sentence must point at its word ON ITS OWN, for a reader who has never seen this list. Do not lean on the list to narrow it down.
- Prefer sentences a child could meet in a real classroom day. The vocabulary around the blank is doing no teaching work here, so it should at least be familiar.
- Keep every clue within the WHO THIS IS FOR rules above.`;

const PALABRA_ITEM_SHAPE = '{"face": "para", "en": "for / in order to", "sentence": "Compré este regalo ___ mi hermana porque mañana es su cumpleaños.", "sentenceEn": "I bought this gift ___ my sister because tomorrow is her birthday.", "definition": "Se usa para decir quién recibe algo o por qué lo haces.", "definitionEn": "It is used to say who receives something or why you do it."}';

/* The ladder for function words.
 *
 * The fourth rung is the one that belongs to this shape and to no other: TWO
 * sentences that take the SAME word. A function word's difficulty is its range —
 * a child who has learned "por" as "for" is undone the first time it means
 * "through" — and a single sentence can only ever teach one of its uses. Two at
 * once asks for the word that covers both, which is the real skill and is also
 * the only clue type here that gets HARDER by giving more context.
 *
 * It sits above the single gap sentence for that reason, and below the heard
 * one, which keeps the single modality pair at $300 and $500 as the other two
 * shapes do.
 */
const PALABRA_LADDER = [
  { text: 'the ENGLISH: give the English of the word and ask for the Spanish — "how do you say «when», as a question?". The easiest rung.', form: 'english', audio: false, audited: false },
  { text: 'what the word DOES, in one short Spanish line that never names a part of speech — "Se usa para decir a dónde vas".', form: 'function', audio: false },
  { text: 'a Spanish sentence with the word replaced by ___ (three underscores), in which only one word of this bank works IN MEANING.', form: 'gap', audio: false },
  { text: 'TWO short Spanish sentences, both with ___, that take the SAME word in two different uses — "Caminamos ___ el parque" and "Gracias ___ todo". The answer is the one word that fits both.', form: 'two-sentence', audio: false },
  { text: 'a Spanish sentence with the word replaced by ___, meant to be HEARD and never shown. The hardest rung: the blank is two letters long and everything that points at it goes past once.', form: 'gap', audio: true },
];

const PALABRA_CATEGORY_SHAPE = '{"name": "Preguntas", "clues": [{"value": 100, "prompt": "How do you say «where», as a question word?", "answer": "dónde"}, {"value": 500, "prompt": "¿___ vive tu abuela? Vive en una casa azul.", "promptEn": "___ does your grandmother live? She lives in a blue house.", "answer": "dónde", "audio": true}]}';

const PALABRA_FINAL_SHAPE = '{"category": "Las palabras de la pregunta", "prompt": "¿___ cuesta el helado? Cuesta dos dólares.", "promptEn": "___ does the ice cream cost? It costs two dollars.", "answer": "cuánto"}';

// No article and no formula, so the listing is the plainest of the three.
function palabraBankListing(items) {
  return items.map(i => `${i.face} = ${i.en}`).join('\n');
}

const PALABRA = {
  id: 'palabra',
  label: 'Little words (grammar)',
  blurb: 'The small words that hold a sentence together: por and para, question words, object pronouns.',
  roomLabel: 'Bingo de palabras',

  clueTypes: ['en', 'sentence', 'definition'],

  // Both games.
  games: ['bingo', 'quiz'],

  itemsIntro: topic =>
    `You are a Spanish teacher building a bank of FUNCTION WORDS for classroom games about "${topic}", for English-speaking children who are learning Spanish. These are the small words that hold a sentence together — prepositions, question words, pronouns, conjunctions — and the games ask the child to produce the right one for a slot.`,
  itemsTask: (topic, count) =>
    `Give exactly ${count} Spanish function words that belong to "${topic}". If the topic names one family and that family is too small to reach ${count}, widen to the neighbouring families rather than padding with rare words a beginner will never meet.`,

  /* The vocabulary question asked of a preposition fails every clue there is.
   *
   * "Could someone with no list land on this word?" is answerable for a noun —
   * one thing in the world matches "it is soft and you put your head on it".
   * For a two-letter function word it is answerable almost never, because
   * Spanish keeps several words for most slots: "Mañana voy ___ la escuela"
   * takes a, hacia, hasta and para, and no amount of rewriting changes that.
   * Asked straight, it flagged 60 clues out of 60 on a real bank — an audit
   * that condemns everything has said nothing, and the FITS check meanwhile
   * found no ambiguity inside the bank at all.
   *
   * So the question becomes the one that is actually worth asking here: does the
   * sentence force the JOB the word is doing? A near-synonym that does the same
   * job is a teacher's judgement call and the class will accept it; a word doing
   * a DIFFERENT job — con where sin also fits — is a square with two answers and
   * a team marked wrong for good Spanish. That is the defect, and this asks for
   * it directly.
   */
  auditSpecific: `"specific": does the sentence force the JOB this word is doing, so that a word doing a DIFFERENT job would be WRONG? Judge it without the list.
Do NOT mark it down because another word doing the SAME job would also fit — "Voy ___ la escuela" taking a, hacia or hasta is fine, because all three point the same way and a teacher will accept any of them.
DO mark it down when a word doing a different job fits just as naturally, because then the clue has two real answers: "Voy al parque ___ mi mejor amigo" is NOT specific, since con and sin both make a true sentence and mean opposite things. "Voy al parque ___ mi mejor amigo, y jugamos juntos toda la tarde" is specific, because "jugamos juntos" makes sin false.
The quiz game gives children no list at all, so a clue whose alternatives are only ruled out by looking at the card does not work there.`,

  clueRules: PALABRA_CLUE_RULES,
  itemRules: PALABRA_ITEM_RULES,
  itemShape: PALABRA_ITEM_SHAPE,
  ladder: PALABRA_LADDER,
  bankListing: palabraBankListing,
  categoryShape: PALABRA_CATEGORY_SHAPE,
  finalShape: PALABRA_FINAL_SHAPE,

  // Same reason as forma: buildQuizGamePrompt inlines a vocabulary entry with an
  // article, which is the one field these words never have.
  quizFromTopic: false,

  // Nothing is derived, so there is no formula to check a face against.
  formAudit: false,

  // No article, no formula; the definition stays, saying what the word does.
  editorFields: { article: false, formula: false, definition: true },

  boardBankIntro: 'Here are the words the class has been studying. Every answer on the board must be one of THESE words, because the students have been drilling this list:',

  boardGrouping: (categoryCount, verb) => `CATEGORIES ARE FAMILIES OF WORDS, NOT QUESTION TYPES.
${verb} ${categoryCount} categories that group the words by the job they do — "Preposiciones", "Preguntas", "Pronombres", "Palabras que unen", "Cuándo y cuánto". ${NOT_A_QUESTION_TYPE}
A category's name is a promise about its five answers: every answer under "Preguntas" must be a question word.`,

  boardGroupingShort: `CATEGORIES ARE FAMILIES OF WORDS, NOT QUESTION TYPES. A category groups the words by the job they do, and every answer under it must really do that job. ${NOT_A_QUESTION_TYPE}`,

  boardRowSummary: categoryCount =>
    `So the whole $100 row is English-to-Spanish, the whole $400 row is two sentences sharing one word, the whole $500 row is heard sentences, and so on across all ${categoryCount} categories.`,

  boardAnswerField: 'the word itself, exactly as it is spelled, accents included — "cuándo", never "cuando"',

  promptEnField: '- "promptEn": the English of the prompt, for a teacher to reveal if a class is stuck. Keep the ___ in place for a gap clue. Omit this for the $100 row, whose prompt is already English.',
  finalPromptEnField: '- "promptEn": the English of the sentence, with the ___ in the same place.',
  finalPromptField: '- "prompt": a Spanish sentence with the word replaced by ___ (three underscores), written to all the rules above. It is shown on screen, never read aloud.',
  finalAnswerField: 'the little word from the list, exactly as it is spelled, accents included.',

  boardClueRules: () => `THE SWAP TEST, AND IT IS ABOUT THE SLOT. Two of these words are confusable when they fit the same hole, and the trap is that most of them are GRAMMATICAL in the same hole: "Voy ___ el parque" takes a, para, hacia and hasta, and a team that answers any of them is not wrong about Spanish. Put the other answers on this board into every sentence; a clue is finished only when the others are wrong IN MEANING.
Ask what the word is actually doing — showing where something goes, or why, or for whom, or for how long — and then make the sentence say that and nothing else.
Bad: "Este regalo es ___ mi hermana." (por and para are both correct)
Good: "Compré este regalo ___ mi hermana porque mañana es su cumpleaños." (she RECEIVES it, so only para)

A question word is pinned by the answer it expects, so put that answer in the clue: "¿___ vive tu abuela? Vive en una casa azul." A pronoun is pinned by naming its referent and its job.

THE TWO-SENTENCE ROW gives two short sentences, each with its own ___, that take the SAME word in two DIFFERENT uses. Both must be true and both must be ordinary; if the word fits both in the same way, the clue has taught nothing and is really a $300 written twice.

Never name a part of speech as a clue. Half the board is prepositions, so "una preposición" narrows nothing, and a child who knows the grammar label still cannot pick the word.`,
};

/* ---------------- frase: a bank of whole sentences ---------------- */

/* The only shape that asks a team to PRODUCE connected Spanish.
 *
 * Every other type puts one word in the blank. This one makes the whole sentence
 * the answer: the board shows "The nurse works at the hospital" and a team has
 * to build the Spanish. That is a different and harder skill — word order,
 * agreement, the articles Spanish insists on and English drops — and nothing
 * else in the product reaches it.
 *
 * QUIZ ONLY, and not because of the engine. A face is printed in a small bingo
 * square and a sentence will not go in one; and on a card the task would be
 * RECOGNITION — find the Spanish that matches — where the whole point here is
 * production. So this type declares `games: ['quiz']`, which is the first time
 * that declaration carries any weight.
 *
 * The bank is the ANSWERS, which for every other type is also the study list.
 * Here they come apart: a printed sheet of the twenty-six sentences is a sheet
 * of the answers. So this type ships a `reference` instead — the words worth
 * knowing, drawn from the sentences — and the quiz's on-screen Vocabulario shows
 * that rather than the bank. `buildReferencePage` already prefers
 * `deck.reference` over the item list when one is there, for the conjugation
 * tables; this is the second thing to want it.
 *
 * MARKING IS THE TEACHER'S. "La enfermera trabaja en el hospital" and "...en un
 * hospital" are both defensible, and so is putting the place first. The game
 * cannot settle that and should not pretend to: the answer shown is A correct
 * translation, the teacher decides whether what a team said is another, and that
 * argument is a better Spanish lesson than the square was.
 */
const FRASE_CLUE_RULES = `- A sentence must be EASIER than it looks. Every word in it except the structure being practised should be one a beginner already knows: the body, colours, numbers, home, school, family, food, jobs, and everyday verbs (ser, estar, tener, ir, hacer, trabajar, vivir, comer, ver, querer). The work is building the sentence, not decoding its vocabulary.
- KEEP THEM SHORT. Six to ten words. A team has to hold the whole thing in their head and write it down inside a minute, and a sentence past about ten words stops testing Spanish and starts testing memory.
- ONE NEW THING AT A TIME. A sentence that needs a tense they have not met AND a pronoun they have not met teaches neither. Put the difficulty in one place and keep the rest plain.
- MAKE THE ENGLISH TRANSLATE CLEANLY. The English is what the team is given, so it has to point at the Spanish you wrote without being word-for-word odd. "The nurse works at the hospital" is clean. "There's a nurse that works at the hospital" invites five different Spanish sentences and makes the square an argument.
- ACCEPT THAT SPANISH HAS MORE THAN ONE RIGHT ANSWER, and write to keep the spread small. Prefer sentences whose natural order is fixed and whose articles are not optional. Avoid ones that hinge on a choice the class has not been taught — ser or estar where both work, the personal "a" where it is arguable, a subjunctive that is optional in speech.
- NO TWO SENTENCES THE SAME SHAPE. Twenty-six sentences all reading "El/La [job] trabaja en [place]" is one sentence written twenty-six times. Vary who does what, where, when, and which tense — within what the class has met.
- Keep every sentence within the WHO THIS IS FOR rules above.`;

/* The study sheet, which for this deck cannot be the bank.
 *
 * Every other type prints its bank as the page a class goes over before playing.
 * Here the bank IS the answers, so printing it hands out the game. The class
 * still needs something to study, and what they actually need is the WORDS the
 * sentences are built from — so the model writes a short glossary beside the
 * sentences and that is what gets printed and shown on screen.
 *
 * `buildReferencePage` already prefers `deck.reference` over the item list when
 * one is there; it was added for conjugation tables and this is the second thing
 * to want it.
 */
const FRASE_REFERENCE_RULES = `ALSO give a "reference": the short glossary the class studies before playing, INSTEAD of the sentences.

The sentences are the answers, so they are never printed for the class. What the class gets is the words to build them from:
- "columns": exactly ["Palabra", "English"].
- "rows": 20 to 30 pairs, each ["<Spanish>", "<English>"], drawn from the sentences you wrote.

Take the words that carry the meaning — the jobs, the places, the verbs, the nouns — and the handful of little words a child would otherwise stumble on. Give a verb in its dictionary form ("trabajar", "to work"), and a noun with its article ("el hospital", "the hospital"). Leave out words any beginner already has (y, de, un, muy). Never put a whole sentence in a row: a row is a word or a two-word phrase, and a row that is a sentence gives away an answer.`;

const FRASE_ITEM_RULES = `For each one provide:
- "face": the Spanish sentence, written out in full with its capital letter and its full stop. Six to ten words. This is the ANSWER a team has to produce.
- "en": the English of it, the sentence a team will be shown. Natural English, not a word-for-word gloss of the Spanish.

That is all. Leave out "article", "prompt", "sentence" and "definition" entirely — the sentence IS the entry, so there is nothing to put beside it.

Hard rules:
- Every sentence must be different, and different in SHAPE as well as in words. See the rules above.
- The English must not contain the Spanish, and the Spanish must not contain the English.
- Do not number them, do not label them, and do not write the two halves into one string. "face" is Spanish and "en" is English.
- A sentence is a whole sentence: a subject, a verb, and what the verb needs. Not a phrase, not a title, not a question fragment.
- Keep every sentence within the WHO THIS IS FOR rules above.

${FRASE_REFERENCE_RULES}`;

const FRASE_ITEM_SHAPE = '{"face": "La enfermera trabaja en el hospital.", "en": "The nurse works at the hospital."}';


/* The ladder for sentences.
 *
 * Five different KINDS of task, not one task at five lengths. Length is the
 * obvious way to grade a sentence and the wrong one: "a longer sentence" is not
 * a different skill, it is the same skill with more of it, and a board whose
 * values mean "more words" stops promising anything by the third square.
 *
 * So the climb runs: understand it, order it, build it, change it, build it
 * without seeing it. Only $300 and $500 share a form, which is the single
 * modality pair every type is allowed.
 */
const FRASE_LADDER = [
  { text: 'the Spanish sentence with a SHORT PHRASE missing — two or three words, side by side, one ___ each — and its English underneath: "La enfermera trabaja ___ ___ ___." / "The nurse works at the hospital." The team gives the missing phrase. The easiest rung, but not a vocabulary one: a phrase carries the agreement and the little words a single blank hides, so the team builds a piece of the sentence rather than recalling one word. Keep it to two or three words; blank more and this stops being the cheap square.', form: 'gap', audio: false, audited: false, fromBank: false, promptEn: false },
  { text: 'the SPANISH sentence, shown — the team says what it means in English. Understanding eight words, with nothing given.', form: 'comprehension', audio: false, audited: false, fromBank: false, promptEn: false },
  { text: 'the ENGLISH sentence, shown — the team gives the Spanish. The heart of the game: producing a whole sentence from nothing.', form: 'translation', audio: false, audited: false, promptEn: false },
  { text: 'a DICTATION: the SPANISH sentence read aloud and never shown. The team writes down what they hear, word for word. Give its English as "promptEn" — the teacher can show that if a class is stuck, and it hands over the meaning without handing over the spelling.', form: 'dictation', audio: true, audited: false, promptEn: true },
  { text: 'the ENGLISH sentence WITH ONE CHANGE ASKED FOR, as an instruction above it — "En plural:", "En femenino:", "En negativo:" — and the team gives the changed Spanish. They translate AND apply the change in one step, which is the most this board asks of them and why it is the dearest square. Do not show them the Spanish; showing it turns the square into copying. Choose changes that keep the sentence inside what the topic is practising, and vary them only as far as that allows; the board rules say which are worth asking.', form: 'translation-change', audio: false, audited: false, fromBank: false, promptEn: false },
];

const FRASE_CATEGORY_SHAPE = '{"name": "En el hospital", "clues": [{"value": 100, "prompt": "La enfermera trabaja ___ ___ ___.\nThe nurse works at the hospital.", "answer": "en el hospital"}, {"value": 400, "prompt": "El médico cura a los enfermos.", "promptEn": "The doctor cures the sick.", "answer": "El médico cura a los enfermos.", "audio": true}]}';

const FRASE_FINAL_SHAPE = '{"category": "El trabajo de cada día", "prompt": "The firefighters put out the fire in the city.", "answer": "Los bomberos apagan el fuego en la ciudad."}';

// Just the sentence and its English; there is nothing else on an entry.
function fraseBankListing(items) {
  return items.map(i => `${i.face}  =  ${i.en}`).join('\n');
}

const FRASE = {
  id: 'frase',
  label: 'Whole sentences (translation)',
  blurb: 'Teams build whole Spanish sentences from the English — word order and agreement, not single words. Quiz only.',
  roomLabel: 'Concurso de frases',

  // The English is the only thing an entry carries beside the sentence itself.
  clueTypes: ['en'],

  // Quiz only: a sentence will not fit in a bingo square, and on a card the task
  // would be recognition rather than production.
  games: ['quiz'],

  // An answer here is a sentence, so its leading article is part of it.
  stripsArticles: false,

  /* Twenty-six, where every other deck wants thirty.
   *
   * Thirty is sized for a deck of WORDS, which spends 26 on the board and keeps
   * the rest because the bank is also the list a class studies and the pool a
   * bingo card is dealt from - so a spare word is still printed and still
   * useful. Neither is true here: there is no card, and the study sheet is the
   * `reference` glossary because the bank is the answers. A twenty-seventh
   * sentence is a sentence nobody reads, and a sentence is the most expensive
   * entry in the app to write.
   *
   * Five columns of five plus the final wager is 26 exactly.
   */
  defaultCount: 26,

  /* More than one translation is right, and the teacher decides which.
   *
   * The board shows one Spanish sentence; "en un hospital" for "en el hospital"
   * is not wrong, and nor is putting the place first. Shown without a word, the
   * answer on screen reads as a verdict. The site says whose call it is at the
   * moment it reveals one — see `answerIsOpen` in the room payload.
   */
  answerIsOpen: true,

  /* The spoken row is a DICTATION, so what is read aloud is SPANISH.
   *
   * An earlier version read the ENGLISH aloud and asked for the Spanish, which
   * needed an English voice and taught nothing about Spanish: listening to your
   * own language is not listening practice, it just stops you re-reading. The
   * dictation asks the thing no other row does — hear Spanish, write Spanish —
   * so the voice is the Spanish one every other deck uses and no deck needs an
   * English one.
   *
   * Which clue carries an English version is now a property of the ROW, not the
   * deck: `promptEn` on the ladder above. Only the dictation has one, where the
   * English is a hint that gives the meaning without the spelling. */

  itemsIntro: topic =>
    `You are a Spanish teacher building a bank of SENTENCES for a classroom quiz about "${topic}", for English-speaking children who are learning Spanish. In the game a team is shown the English and has to produce the whole Spanish sentence, so every sentence here is an answer a class will be asked to build.`,
  itemsTask: (topic, count) =>
    `Give exactly ${count} Spanish sentences about "${topic}", each with its English. They are the whole of the game: the board takes its answers from them and nothing else, so write ${count} that a class could reasonably be asked to produce, not ${count} that show off what Spanish can do.`,

  clueRules: FRASE_CLUE_RULES,
  itemRules: FRASE_ITEM_RULES,
  /* The glossary rides back with the sentences, in the same reply.
   *
   * A call of its own would be a second charge for a list the model has just
   * finished writing the source of, and it would have to be sent the sentences
   * again to do it. */
  itemsAlso: ', "reference": {"columns": ["Palabra", "English"], "rows": [["el hospital", "the hospital"], ["trabajar", "to work"]]}',
  itemShape: FRASE_ITEM_SHAPE,
  ladder: FRASE_LADDER,
  bankListing: fraseBankListing,
  categoryShape: FRASE_CATEGORY_SHAPE,
  finalShape: FRASE_FINAL_SHAPE,

  auditSpecific: VOCABULARIO.auditSpecific,

  // The one-reply quiz prompt writes a reduced vocabulary entry; a sentence bank
  // is a different shape entirely, so this one is written in two steps.
  quizFromTopic: false,
  formAudit: false,

  // Nothing beside the sentence and its English.
  editorFields: { article: false, formula: false, definition: false },

  boardBankIntro: 'Here are the sentences the class has been practising. Every answer on the board must be one of THESE sentences, word for word:',

  boardGrouping: (categoryCount, verb) => `CATEGORIES GROUP THE SENTENCES BY WHAT THEY ARE ABOUT.
${verb} ${categoryCount} categories that gather the sentences by situation — "En el hospital", "En la escuela", "La familia en casa". ${NOT_A_QUESTION_TYPE}
Each column needs FIVE DIFFERENT sentences from the list and the final wager a twenty-sixth, so choose groupings the list can actually fill.`,

  boardGroupingShort: `CATEGORIES GROUP THE SENTENCES BY WHAT THEY ARE ABOUT — a situation, a place, a kind of person. ${NOT_A_QUESTION_TYPE}`,

  boardRowSummary: categoryCount =>
    `So the whole $100 row is a missing phrase, the whole $200 row is Spanish to understand, the whole $300 row is English to translate, the whole $400 row is a Spanish sentence to write down from hearing it, and the whole $500 row is English to translate WITH a change — across all ${categoryCount} categories.`,

  boardAnswerField: 'the Spanish sentence from the list, word for word, with its capital letter and full stop — except on the $100 row, where the answer is the missing phrase on its own, and the $200 row, where it is the ENGLISH of the sentence',

  /* The final is a WHOLE SENTENCE, and saying so is not optional.
   *
   * Left to the shared wording the final asked for "the Spanish word from the
   * vocabulary list", and a sentence deck duly answered it with one word —
   * "escribe" — three runs out of three. The final is written apart from the
   * squares and inherits nothing from them, so it has to be told on its own. */
  /* No promptEn anywhere on this board, and leaving it out is not a tidy-up.
   *
   * Every clue here is ALREADY in English or is a Spanish sentence whose English
   * is the thing being asked for. On the $200 row the clue is the Spanish and
   * the answer is its English — so a "promptEn" would be the answer, printed on
   * the screen behind a button labelled "Ver en inglés". The rest would simply
   * repeat the clue. */
  promptEnField: '- "promptEn": ONLY on the dictation row, where it is the English of the sentence being read out — a hint that gives the meaning without the spelling. Every other row must have none: three of them are already English, and the $200 row is a Spanish sentence whose English is the ANSWER, so a translation there would sit behind the Ver en inglés button and give the square away.',
  finalPromptEnField: '- Do NOT give a "promptEn". The prompt is already the English.',
  finalPromptField: '- "prompt": the ENGLISH sentence, shown on screen and never read aloud. The teams translate it.',
  finalAnswerField: 'the WHOLE SPANISH SENTENCE from the list, word for word. Never a single word — this row asks for the same thing the $300 squares did, with everything at stake.',

  boardClueRules: () => `EVERY ANSWER IS A SENTENCE FROM THE LIST, copied exactly — on the $300 and $400 rows. Do not improve one on its way onto the board, do not shorten one to fit, and do not invent a twenty-seventh. The teams were given these and no others.
Three rows answer with something built from a listed sentence rather than the sentence itself: $100 answers with a PHRASE out of one, $200 with the ENGLISH of one, and $500 with one after the change asked for. None of them invents a sentence that is not on the list. The $300 and $400 rows answer with a listed sentence exactly as written.

THERE IS NO WORD-ORDER ROW, and there must not be one. Spanish moves its parts around far more freely than English: "La secretaria contesta el teléfono en la oficina", "En la oficina contesta el teléfono la secretaria" and "Contesta el teléfono la secretaria en la oficina" are all correct. A square asking a class to put chunks in THE right order has half a dozen right orders and no way to choose between them, so the teacher ends up awarding it to everyone.

THE GAP ROW blanks a SHORT PHRASE of a listed sentence and prints the English underneath, so the team knows the meaning and builds the piece.

TWO OR THREE WORDS, never more. This is the cheapest square on the board and it has to stay that way: blanking four or five words leaves a subject and a full stop, which is the $300 square with a hint, not an easier one. "El cocinero ___ ___ ___ ___ ___." is too much; "El cocinero prepara ___ ___ ___." is right.
ONE ___ FOR EACH MISSING WORD, so the class can see how many they are building.
Blank words that SIT TOGETHER, never scattered ones, and choose a piece that is a thing in itself: a verb with its object ("prepara comida"), a noun with its adjective ("una casa nueva"), a preposition with its article and noun ("en la escuela primaria"). That is what makes this rung structure rather than vocabulary — the team has to get the agreement and the little words right, not just remember a noun.
Never blank only "el" or "en" on their own, and never leave the sentence with nothing but its subject.

THE DICTATION ROW's prompt is the Spanish sentence itself, exactly as it is on the list. The site reads it aloud and never shows it, so write nothing else into that field — no instruction, no "Escucha:", nothing but the sentence. Its answer is that same sentence, word for word, because what the team writes down IS the sentence. Give its English as "promptEn": that is the one hint on this board, and it hands over the meaning without the spelling.

THE CHANGE ROW gives the ENGLISH and names the change above it, and never shows the Spanish. Shown the Spanish, "put it in the negative" is copying the sentence and adding "no", which is not a Spanish exercise at all. Given the English, the team has to build the sentence AND change it, which is the step up the value promises.

CHOOSE THE CHANGE BY THE TOPIC FIRST, AND ONLY THEN FOR VARIETY.

A change that takes the sentence outside what this game is practising is a wasted square and a confusing one. If the topic is the PAST TENSE, "En futuro:" asks for the one thing the lesson is not about. If it is COMMANDS, a change of person may not even be a sentence any more. If it is the SECOND CONDITIONAL, "En pasado:" has nowhere to go, because the tense is already fixed by the structure. Work out which changes leave the sentence inside the topic, and use only those.

Then vary them across the board AS FAR AS THE TOPIC ALLOWS. Five squares all saying "En negativo:" is one square written five times. But two changes used two or three times each is better than five changes where three of them pull the class off the lesson — do not reach for variety you have to break the topic to get.

These are worth asking, in roughly this order of usefulness:
- "En plural:" — everything agrees at once: the article, the noun, the verb, any adjective. "El doctor trabaja en el hospital" becomes "Los doctores trabajan en el hospital". The best of them, because one instruction exercises four agreements.
- "En femenino:" — the same chain through gender. "Un profesor alto" becomes "Una profesora alta".
- "Con «nosotros»:" or another person — the verb moves and the rest follows.
- "En pasado:" or "En futuro:" — ONLY where the topic is not already about a tense. A game about the pretérito does not ask for the future, and a game about a structure that fixes its own tenses does not ask for either.
- "En negativo:" — the cheapest, since it moves one word, but it fits almost any topic and is always a safe choice where the others do not apply.
Do NOT ask for a question. Spanish turns a statement into a question with punctuation and intonation far more often than with word order, so "Como pregunta:" asks a class to add two marks and change nothing — it looks like grammar and is not.

MORE THAN ONE TRANSLATION IS OFTEN RIGHT, and the board cannot judge that. Write the English so the spread is as small as you can make it, and remember the teacher decides. Never write a clue whose English could reasonably produce a sentence that is NOT the one on the list, because then the answer shown is wrong rather than merely one of several.`,
};

const DECK_TYPES = {
  [VOCABULARIO.id]: VOCABULARIO,
  [FORMA.id]: FORMA,
  [PALABRA.id]: PALABRA,
  [FRASE.id]: FRASE,
};

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
