/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/deckTypes.js (commit 1e0f315) by its
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
};

const DECK_TYPES = { [VOCABULARIO.id]: VOCABULARIO };

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

module.exports = { deckType, DECK_TYPES };
