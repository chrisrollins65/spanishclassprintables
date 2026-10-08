/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/ai/prompts.js (commit 74c9a5a) by its
 * scripts/sync-site-shared.js. Edit it there and run that script again; an
 * edit made here is lost the next time anyone does.
 */
const SOURCE_TYPE_LABELS = {
  general: 'topic',
  song: 'song',
  movie: 'movie',
  tv: 'TV series',
  book: 'book',
  game: 'video game',
};

// The creative half lives in src/shared/wordsPrompt.js because the browser
// shows the same text in step 1's editable preview; keeping one copy is what
// stops the two from drifting apart again.
const { buildCreativeWordsPrompt } = require('../shared/wordsPrompt');

function buildWordsPrompt(topic, count = 20, sourceType = 'general', lyrics = null) {
  return wrapWithJsonFormat(buildCreativeWordsPrompt(topic, count, sourceType, lyrics), 'words');
}

function buildSentencesPrompt(theme, words, sourceType = 'general', lyrics = null) {
  const wordList = words.map(w => `${w.es} (${w.en})`).join(', ');

  if (sourceType === 'song') {
    let lyricsSection = '';
    if (lyrics && lyrics.es) lyricsSection += `\nSpanish lyrics:\n${lyrics.es}\n`;
    if (lyrics && lyrics.en) lyricsSection += `\nEnglish translation:\n${lyrics.en}\n`;

    return `You are a Spanish language teacher creating fill-in-the-blank sentences from a song for children ages 8-13 (grades 3-7).

The song is: "${theme}"
${lyricsSection}
The vocabulary words are: ${wordList}

Select the best lines from the lyrics that contain the vocabulary words. Mark ALL vocabulary words in each line with [square brackets].

Requirements:
- Choose lines that collectively cover as many vocabulary words as possible
- A single line CAN have multiple vocabulary words in [brackets] - that is encouraged, but each vocabulary word should only be placed in brackets once in the all of the sentences.
- The "es" field should be the primary vocabulary word for that line
- Include the English translation of each line
- If a vocabulary word appears in conjugated form in the lyrics, use the conjugated form in brackets
- Do not list the same sentence twice. If it has two vocabulary words, just put them both in brackets and list it once.
- If a line has less than 4 words, combine it with the next line or omit it if that would result in a very long line.

Return your response as JSON in EXACTLY this format:
{
  "sentences": [
    {
      "es": "Primary Spanish word",
      "en": "Primary English word",
      "sentence": "El [Sol] y la [Luna] brillan en el cielo.",
      "sentenceEn": "The sun and moon shine in the sky."
    }
  ]
}

Only return valid JSON, nothing else.`;
  }

  if (['movie', 'tv', 'book', 'game'].includes(sourceType)) {
    const typeLabel = SOURCE_TYPE_LABELS[sourceType];

    return `You are a Spanish language teacher creating fill-in-the-blank sentences for children ages 8-13 (grades 3-7).

The ${typeLabel} is: "${theme}"
The vocabulary words are: ${wordList}

Create one simple Spanish sentence for EACH word. The sentences should describe parts of the story, characters, settings, or themes of "${theme}" using the vocabulary words. In each sentence, the vocabulary word should be replaced with [the word in square brackets] to mark where the blank goes. Also provide an English translation of the full sentence.

Requirements:
- Sentences should be simple enough for children ages 8-13 (grades 3-7)
- Each sentence must use exactly one vocabulary word from the list
- Mark the vocabulary word in the sentence with square brackets, e.g. [Sol]
- The Spanish word inside brackets should match the word exactly (including accents)
- Sentences should reference the story, characters, or themes of "${theme}"
- Include the English translation

Return your response as JSON in EXACTLY this format:
{
  "sentences": [
    {
      "es": "Spanish word",
      "en": "English word",
      "sentence": "El [Sol] brilla mucho hoy en el cielo.",
      "sentenceEn": "The sun shines a lot today in the sky."
    }
  ]
}

Return exactly ${words.length} sentences (one per word). Only return valid JSON, nothing else.`;
  }

  // general — original prompt
  return `You are a Spanish language teacher creating fill-in-the-blank sentences for children ages 8-13 (grades 3-7).

The topic is: "${theme}"
The vocabulary words are: ${wordList}

Create one simple Spanish sentence for EACH word. In each sentence, the vocabulary word should be replaced with [the word in square brackets] to mark where the blank goes. Also provide an English translation of the full sentence.

Requirements:
- Sentences should be simple enough for children ages 8-13 (grades 3-7)
- Each sentence must use exactly one vocabulary word from the list
- Mark the vocabulary word in the sentence with square brackets, e.g. [Sol]
- The Spanish word inside brackets should match the word exactly (including accents)
- Sentences should be natural and related to the topic when possible
- Include the English translation

Return your response as JSON in EXACTLY this format:
{
  "sentences": [
    {
      "es": "Spanish word",
      "en": "English word",
      "sentence": "El [Sol] brilla mucho hoy en el cielo.",
      "sentenceEn": "The sun shines a lot today in the sky."
    }
  ]
}

Example:
{
  "sentences": [
    {
      "es": "Sol",
      "en": "Sun",
      "sentence": "El [Sol] brilla mucho hoy en el cielo.",
      "sentenceEn": "The sun shines a lot today in the sky."
    },
    {
      "es": "Flor",
      "en": "Flower",
      "sentence": "Esta [Flor] roja es muy bonita.",
      "sentenceEn": "This red flower is very pretty."
    }
  ]
}

Return exactly ${words.length} sentences (one per word). Only return valid JSON, nothing else.`;
}

/* What the teacher has already been shown, so asking again is not asking twice.
 *
 * A model asked the same question a second time returns much the same five
 * jokes, which makes a "more" button that grows the list without giving
 * anything new to choose from. Both halves of each one are sent, not just the
 * setup: a joke repeats by its punchline as often as by its question, and
 * "What do you call a SLEEPY kangaroo? / A pouch potato" is the same joke
 * arriving under a new name.
 *
 * Takes jokes, messages, or plain strings — the secret-message step generates
 * both kinds through the same button.
 */
function avoidBlock(avoid) {
  const lines = (avoid || [])
    .map(a => (typeof a === 'string' ? a
      : [a.question || a.title, a.answer || a.message].filter(Boolean).join(' / ')))
    .map(s => String(s).trim())
    .filter(Boolean);
  if (!lines.length) return '';
  return `The teacher has already seen these and is asking for DIFFERENT ones. Do not repeat any of them, and do not return a reworded version of one — a new setup with the same punchline counts as a repeat:
${lines.map(l => `- ${l}`).join('\n')}`;
}

function buildJokesPrompt(topic, count = 5, avoid = []) {
  const already = avoidBlock(avoid);
  return `Give me a list of ${count} hilarious question-answer jokes in English for 8-13 year-olds related to "${topic}". The answer should not be longer than 8 words.

Return your response as JSON in EXACTLY this format:
{
  "jokes": [
    {
      "question": "The joke question?",
      "answer": "The funny answer"
    }
  ]
}

Example:
{
  "jokes": [
    {
      "question": "Does February like March?",
      "answer": "No, but April May"
    },
    {
      "question": "What do you call a lazy kangaroo?",
      "answer": "A pouch potato"
    }
  ]
}
${already ? '\n' + already + '\n' : ''}
Return exactly ${count} jokes. Only return valid JSON, nothing else.`;
}

function buildHiddenMessagePrompt(topic, sourceType = 'general', count = 5, avoid = []) {
  const typeLabel = SOURCE_TYPE_LABELS[sourceType] || 'topic';
  const already = avoidBlock(avoid);

  return `Based on the ${typeLabel} "${topic}", generate ${count} suggestions for a hidden message that captures a main theme, moral, or key message.

Each suggestion should have:
- A title: a short, engaging label for the hidden message section on a worksheet (e.g. "Descubre el Mensaje" or "El Mensaje Oculto")
- A message: the actual hidden message — must be 8 words or fewer

Requirements:
- Messages should be meaningful and thought-provoking
- Appropriate for children ages 8-13 (grades 3-7)
- Each message should capture a different theme or perspective
- Keep messages concise (max 8 words)
- They must be in English.

Return your response as JSON in EXACTLY this format:
{
  "messages": [
    {
      "title": "Descubre el Mensaje",
      "message": "Love conquers all obstacles"
    }
  ]
}
${already ? '\n' + already + '\n' : ''}
Return exactly ${count} suggestions. Only return valid JSON, nothing else.`;
}

function buildProcessLyricsPrompt(topic, rawLyrics, words) {
  const wordList = words.map(w => `${w.es} (${w.en})`).join(', ');

  return `You are a Spanish language teacher preparing song lyrics for a vocabulary worksheet.

The song is: "${topic}"

Raw lyrics (may contain both Spanish and English interleaved line by line, or just Spanish):
${rawLyrics}

The vocabulary words are: ${wordList}

Analyze these lyrics and return processed versions. You must:

1. **Split languages**: If the raw lyrics contain both Spanish and English lines interleaved (alternating line by line, or in any mixed format), separate them into pure Spanish lyrics and pure English translation. If only Spanish is present, provide the English translation yourself.

2. **Identify and compact repeated sections**: Find parts of the song that repeat (chorus, pre-chorus, bridge, etc.). Label the first occurrence with a section marker like [Coro], [Pre-coro], [Puente], [Verso 1], [Verso 2], etc. on its own line before the section. For subsequent repetitions, replace the entire repeated section with JUST the label on its own line (e.g. just "[Coro]" by itself with no lyrics after it). ALL verses should also get labels like [Verso 1], [Verso 2], etc.

3. **Use language-appropriate section labels**: In the Spanish lyrics, use Spanish labels: [Coro], [Pre-coro], [Puente], [Verso 1], [Verso 2], [Outro], [Intro], etc. In the English lyrics, use the English equivalents: [Chorus], [Pre-chorus], [Bridge], [Verse 1], [Verse 2], [Outro], [Intro], etc. Both outputs must have the same number of sections in the same order.

4. **Preserve section structure**: Separate sections with blank lines. Each section should start with its label on its own line, followed by the content lines.

Format rules:
- Section labels go on their own line: [Coro]
- A repeated section that was already shown in full should appear as JUST the label with no content lines
- Separate sections with blank lines
- Both ES and EN outputs must have matching section structure (same labels, same number of sections)
- Remove any artist/title headers that aren't part of the lyrics
- Remove any annotations or parenthetical comments that aren't part of the lyrics

Return your response as JSON in EXACTLY this format:
{
  "lyricsEs": "processed Spanish lyrics with section labels",
  "lyricsEn": "processed English translation with matching section labels"
}

Only return valid JSON, nothing else.`;
}

function buildEditPrompt(type, currentData, userRequest) {
  if (type === 'words') {
    return `You are a Spanish language teacher. A user has the following vocabulary word list and wants changes.

Current words:
${JSON.stringify(currentData, null, 2)}

User's request: "${userRequest}"

Apply the user's requested changes and return the updated list. Keep words the user didn't ask to change.

Return your response as JSON in EXACTLY this format:
{
  "words": [
    { "en": "English word", "es": "Spanish word" }
  ]
}

Only return valid JSON, nothing else.`;
  }

  if (type === 'sentences') {
    return `You are a Spanish language teacher. A user has the following sentence list and wants changes.

Current sentences:
${JSON.stringify(currentData, null, 2)}

User's request: "${userRequest}"

Apply the user's requested changes. Mark vocabulary words in square brackets [like this]. Keep sentences the user didn't ask to change.

Return your response as JSON in EXACTLY this format:
{
  "sentences": [
    {
      "es": "Spanish word",
      "en": "English word",
      "sentence": "El [Sol] brilla mucho hoy.",
      "sentenceEn": "The sun shines a lot today."
    }
  ]
}

Only return valid JSON, nothing else.`;
  }

  if (type === 'jokes') {
    return `A user has the following joke list and wants changes.

Current jokes:
${JSON.stringify(currentData, null, 2)}

User's request: "${userRequest}"

Apply the user's requested changes. Keep jokes the user didn't ask to change.

Return your response as JSON in EXACTLY this format:
{
  "jokes": [
    {
      "question": "The joke question?",
      "answer": "The funny answer (max 8 words)"
    }
  ]
}

Only return valid JSON, nothing else.`;
  }

  if (type === 'messages') {
    return `A user has the following hidden message suggestions and wants changes.

Current messages:
${JSON.stringify(currentData, null, 2)}

User's request: "${userRequest}"

Apply the user's requested changes. Keep messages the user didn't ask to change.

Return your response as JSON in EXACTLY this format:
{
  "messages": [
    {
      "title": "Title for the hidden message section",
      "message": "The hidden message (max 8 words)"
    }
  ]
}

Only return valid JSON, nothing else.`;
  }

  throw new Error(`Unknown edit type: ${type}`);
}

// JSON format wrappers for custom prompts
const JSON_FORMATS = {
  words: `Return your response as JSON in this format:
{"theme": "El Tema en Español", "words": [{"en": "English word", "es": "Spanish word"}]}
Only return valid JSON, nothing else.`,
  sentences: `Return your response as JSON in this format:
{"sentences": [{"es": "Spanish word", "en": "English word", "sentence": "El [Sol] brilla.", "sentenceEn": "The sun shines."}]}
Only return valid JSON, nothing else.`,
  jokes: `Return your response as JSON in this format:
{"jokes": [{"question": "...", "answer": "..."}]}
Only return valid JSON, nothing else.`,
  messages: `Return your response as JSON in this format:
{"messages": [{"title": "...", "message": "..."}]}
Only return valid JSON, nothing else.`,
};

// `avoid` is carried here too: the teacher who edited the prompt is the one
// most likely to press "more", and a hand-written prompt has no list of its
// own to exclude.
function wrapWithJsonFormat(creativePrompt, type, avoid = []) {
  const format = JSON_FORMATS[type];
  if (!format) throw new Error(`Unknown format type: ${type}`);
  const already = avoidBlock(avoid);
  return `${creativePrompt}${already ? '\n\n' + already : ''}\n\n${format}`;
}

// Every categorisation field on the TpT upload form is a fixed list, so the
// listing has to be written against the real vocabulary or the values silently
// fail to attach. See src/tptVocab.js for where these came from.
const { LIMITS, SUBJECT_AREAS, TAGS, CUSTOM_CATEGORIES } = require('../tptVocab');

// Handed to the model as a grouped menu; the grouping is what stops it from
// spending all six tag slots on themes and none on what the resource is.
const TAG_MENU = Object.entries(TAGS)
  .map(([group, values]) => `  ${group}: ${values.join(', ')}`)
  .join('\n');

// Human-readable page descriptions, keyed by the slug getPageNames() produces.
// The listing should say what each page does, not repeat our internal names.
const PAGE_DESCRIPTIONS = {
  vocabulario: 'Vocabulary reference page listing every Spanish word with its English translation',
  sopa_de_letras: 'Word search puzzle over the Spanish words, with a numbered list of the English words and a blank beside each one for the student to write the Spanish before hunting it in the grid',
  crucigrama: 'Crossword puzzle whose Across and Down clues are simply the English words - the student writes the Spanish translation into the grid. There are no sentence clues and no written definitions on this page',
  mensaje_secreto: 'Secret message puzzle that decodes to a hidden payoff',
  letra_1: 'Fill-in-the-blank song lyrics page (part 1)',
  letra_2: 'Fill-in-the-blank song lyrics page (part 2)',
  respuestas: 'Complete answer key for every puzzle',

  // Game packs. The script and the caller sheet are answer keys by another
  // name, so their descriptions say so — the listing must never imply a buyer
  // gets them as a student handout.
  como_jugar: 'One page of teacher directions: how to play with the website, and how to play on paper without it',
  referencia: 'Reference page listing every word the games use, for going over with the class before playing',
  tablero: 'The game board, laid out the same way it appears on the projected screen so students can find the square the teacher calls',
  hoja_del_equipo: 'Student answer sheet matching the game board square for square, with a line to write each answer on',
  guion_del_maestro: 'Teacher script with every question and its answer, for running the game without a projector',
  puntuacion: 'Score sheet for up to eight teams',
  cartones_4x4: 'Forty-eight different 4x4 bingo cards, four to a page with cut lines',
  cartones_3x3: 'Forty-eight different 3x3 bingo cards, four to a page with cut lines, for shorter games or younger classes',
  hoja_del_cantor: 'Caller sheet listing every word with all of its clues, already in a random calling order, with tick columns for four games; followed by optional cut-apart calling slips, one per word, for random draws and for more than four games',
};

/* The listing rules every TpT product in this store shares, worksheet or game.
 *
 * Lifted out of the worksheet listing verbatim when the game packs got listings
 * of their own: these are the rules about what TpT's editor accepts and how
 * this store's descriptions look, and a second copy of them would drift the
 * first time either one was edited.
 */
const LISTING_HTML_RULES = `  EMOJI: use them as small section icons only - one at the start of each section
  header, each <li>, and the review line, and nowhere else. Never two in a row,
  never mid-sentence, never in the title. Pick ones that match the theme and the
  page (🍎 for a food pack, 🔍 for the word search, ✏️ for the crossword,
  🔑 for the answer key). They are decoration, not vocabulary.
  TpT's editor supports ONLY these tags: <p> <strong> <em> <u> <ul> <ol> <li>. It has no headings, no font sizes and no colours, so never use <h1>-<h6>, <span>, style attributes, markdown or asterisks.
  Bold section labels and key phrases only - bolding everything defeats the purpose.
  Do NOT add blank paragraphs, <br> tags or &nbsp; to create spacing. Write the
  blocks back to back; the builder inserts the blank lines between them.`;

function customCategoryRule(customCategory) {
  return customCategory
    ? `- customCategories: exactly ["${customCategory}"] and nothing else. This one
  was chosen deliberately for this packet, so do not substitute or add to it.`
    : `- customCategories: 0-${LIMITS.customCategories} of the seller's own store sections, and 0 is the
  normal answer. Pick one ONLY if this packet's theme plainly belongs in it -
  a shopper browsing that section would expect to find this packet there.
  A loose thematic association is not enough: a fruit packet does not belong
  under Festividades or Las estaciones del ano just because fruit is seasonal.
  If none of them fit, return an empty array. That is correct, not a failure.
  Copy the emoji too - they are part of the name: ${CUSTOM_CATEGORIES.join(' | ')}`;
}

function buildListingPrompt(worksheet) {
  const {
    theme, words = [], joke, sourceType = 'general', pageNames = [], pageCount,
    secretMessageType = 'joke', customCategory = '',
  } = worksheet;
  const typeLabel = SOURCE_TYPE_LABELS[sourceType] || 'topic';
  const vocabList = words.map(w => `${w.es} (${w.en})`).join(', ');

  // The secret-message page decodes to a joke on some packets and a themed
  // message on others. For a message packet, joke.question holds the section
  // heading and joke.answer holds the message itself.
  const isMessage = secretMessageType === 'message';
  const secretDescription = isMessage
    ? 'Secret message puzzle that decodes to a short inspiring message about the theme'
    : 'Secret message puzzle that decodes to a joke';
  const describePage = (name) => {
    const slug = name.replace(/^\d+_/, '');
    if (slug === 'mensaje_secreto') return secretDescription;
    return PAGE_DESCRIPTIONS[slug] || name.replace(/_/g, ' ');
  };
  const pages = pageNames
    .map((n, i) => `  ${i + 1}. ${describePage(n)}`)
    .join('\n');

  let secretNote = '';
  if (joke && isMessage) {
    secretNote = `\nThe secret-message page is headed "${joke.question}" and decodes to this message: "${joke.answer}". Do NOT reproduce the message itself in the listing.`;
  } else if (joke) {
    secretNote = `\nThe secret-message page decodes to this joke: "${joke.question}" / "${joke.answer}". Do NOT reproduce the joke in the listing.`;
  }

  // The store has only five sections, and most topics belong to none of them.
  // Left to itself the model always fills the field, which is how a fruit packet
  // ended up under "Festividades". A category is either handed to us (from the
  // Trello card) or the honest answer is an empty list.
  const categoryRule = customCategoryRule(customCategory);

  return `You are writing a Teachers Pay Teachers product listing for a printable Spanish vocabulary worksheet packet.

PACKET DETAILS
Theme: "${theme}" (based on a ${typeLabel})
Total pages: ${pageCount}
Pages included:
${pages}

Vocabulary covered (${words.length} words): ${vocabList}
${secretNote}

The packet is in Spanish with English translations, aimed at Spanish-language learners in US classrooms. It is print-and-go: no prep, answer key included.

WRITE THE LISTING
- title: ${LIMITS.titleChars} characters at the very most - TpT's Title field stops
  accepting input there, so anything longer gets cut off mid-word. Count the
  spaces and the pipes before you answer.

  Build it from these parts, in this order:
    "<English topic> | <English format words> | <Spanish topic> | <Spanish format words>"

  For example:
    "Hispanic Heritage Month Spanish | Word Search & Crossword | Sopa de Letras"
    "Day of the Dead Spanish | Word Search, Crossword | Día de los Muertos"
    "Spanish Spring Break | Word Search & Crossword | Vacaciones de Primavera"

  Two rules decide the wording, and both come from the store's own search data:

  ENGLISH FIRST, AND SAY "WORD SEARCH". Teachers search TpT in English far more
  than in Spanish - "word search" draws about 29,500 searches a month against
  680 for "sopa de letras", and the store's own top search term, "sopa de
  letras", has converted 0% of the visits it brought. So the English words earn
  their place on volume and the Spanish words earn theirs on precision. Always
  include the literal phrase "Word Search". Include "Crossword" whenever it
  fits. Never let the Spanish puzzle names be the only ones present.

  WHEN IT WON'T FIT, DROP WHOLE PARTS FROM THE BACK - the Spanish format words
  first, then the Spanish topic - because that is the order the builder's own
  safety trim uses, and a title you fit yourself always reads better than one it
  cuts for you. If only one English format word fits, keep "Word Search" and
  drop "Crossword". Never abbreviate a topic name to make room for a puzzle
  name, and never leave a part as a stub.

  Skip filler. "Activities" is worth keeping only when it is part of how
  teachers name the occasion ("Back to School Activities"); otherwise the
  characters are better spent on a format word. Never pad with "Printable",
  "Fun", "Engaging", "No-Prep" or "TPT" - none of those are searched for, and
  every one of them costs room the format words need.
- descriptionHtml: 250-400 words of HTML, written to be SCANNED not read. Follow
  the structure this store already uses, in this order:
    1. A bold bilingual headline as its own paragraph, opening with one emoji:
       <p><strong>✨ Celebrate <theme in English> in Spanish Class! | <theme in Spanish></strong></p>
       Vary the opening verb to fit the theme (Celebrate, Explore, Practise...)
       and pick an emoji that matches the theme rather than always the same one.
    2. A <p> hook: what the pack is, who it suits, and when to use it. Name the
       real-world date or observance if there is one (for example September 16th,
       or Hispanic Heritage Month). Bold <strong>no-prep</strong> in it.
    3. A <p> saying what makes it work for novice learners - the puzzles use
       direct English-to-Spanish translation rather than written definitions, so
       students can complete every page independently. Bold the payoff phrase.
    4. <p><strong>📚 What's Included?</strong></p> then a <ul>, one <li> per page.
       Each <li> opens with one emoji, then the page name in <strong>, giving BOTH
       languages where the store does - <strong>Word Search (Sopa de Letras)</strong>,
       <strong>Crossword (Crucigrama)</strong> - then a colon and a short
       description. Use CONCRETE COUNTS, not vague ones: say how many vocabulary
       words, and how many sentences on the pages that actually have sentences.
       Never print our internal page slugs.
       Describe every page the way "Pages included" above describes it. In
       particular the crossword and the word search carry NO sentence clues and NO
       definitions: the clue is the English word and the student writes the
       Spanish. Never say or imply otherwise.
    5. In the vocabulary list item, name 5-7 real example words from the packet.
    NEVER print what the secret-message page decodes to. Giving away the
       punchline, or the message itself, spoils the one page students are
       working towards. Say only that correct answers unlock it, and describe it
       the way the packet details above do - a hidden joke on some packets, a
       short inspiring message about the theme on others. Do not call it a joke
       when it is a message.
    6. <p><strong>⭐ Why Teachers Love It:</strong></p> then a <ul> of exactly three
       <li> benefits, each opening with one emoji and then a bolded label and a
       colon, in the shape of <strong>100% No-Prep</strong>,
       <strong>High Engagement</strong>, <strong>Versatile Use</strong>. Say why,
       concretely - "ideal for cultural mini-lessons, fast finishers, sub plans,
       or homework".
    7. A closing <p> asking for a review, written in a warm voice and varied per
       listing rather than copied word for word. Open it with an emoji, bold the
       ask, and say why it helps the teacher - along the lines of
       "💛 <strong>Enjoyed this resource?</strong> Please leave a review! Your
       feedback helps other teachers find it and earns you TpT credits toward
       your next purchase."
${LISTING_HTML_RULES}
- description: leave this field out entirely. The builder derives the plain-text
  fallback from descriptionHtml, so the two can never drift apart.
- gradeLevels: leave this field out entirely. Every packet in this store is
  listed for the same grades and the builder stamps them.

EVERY FIELD BELOW IS A FIXED MENU. TpT rejects anything that is not on its list,
so copy the wording verbatim - no rephrasing, no pluralising, no inventing. Pick
fewer than the maximum rather than forcing a poor fit.

- subjectAreas: always exactly "Spanish" and "Vocabulary", which is what every
  listing in this store uses. Other valid values: ${SUBJECT_AREAS.join(' | ')}
- tags: exactly ${LIMITS.tags}. This one field carries audience, language, resource
  type and theme, so spend all six deliberately, in this priority order:
    1. "En español" from Language - always, the pages are in Spanish.
    2. One from Holiday or Seasonal when the theme genuinely is one
       (Hispanic Heritage Month, Back to School, Autumn...). Skip if it is not.
    3. Two saying what the resource IS, from Student Practice, Hands-on
       Activities or Instruction - "Worksheets", "Activities", "Printables".
       These are how teachers filter search results, so never drop them.
    4. Fill the rest from Audience - "Homeschool", "Parents",
       "Staff & Administrators".
  Choose from:
${TAG_MENU}
${categoryRule}
- suggestedPrice: always the string "5.00". That is this store's standard price
  for a packet of this size; do not discount it.

Return your response as JSON in EXACTLY this format:
{
  "title": "...",
  "descriptionHtml": "<p>...</p>",
  "subjectAreas": ["..."],
  "tags": ["..."],
  "customCategories": ["..."],
  "suggestedPrice": "5.00"
}

Only return valid JSON, nothing else.`;
}


/* ---------------- Game packs ---------------- */

/* What the pack tests — the item shape, its clue rules and the board's ladder.
 *
 * Every game prompt below takes a `type` and defaults it to vocabulary, so a
 * caller that does not know about types yet still gets the wording it always
 * got. See src/deckTypes.js for why the registry is shapes and not grammar
 * points. */
const { deckType, DECK_TYPES, NOT_A_QUESTION_TYPE } = require('../deckTypes');

/* The item bank both games are built from.
 *
 * Everything is written against one list so the quiz and the bingo deck drill
 * the same words — that shared bank is the reason a pack hangs together rather
 * than being two unrelated products in one folder.
 *
 * The count matters: at twenty faces every 4x4 card holds sixteen of the same
 * twenty and simultaneous winners are constant. Twenty-eight to thirty is what
 * makes the bigger card worth printing.
 */
/* Who the clues are written for, shared by the bank and the board.
 *
 * Both prompts used to say "for children ages 8-13" and stop, so the model
 * wrote for 8-13-year-olds who SPEAK Spanish, in the register of a dictionary:
 * "el casco" came back as "Objeto duro que se pone para no lastimarse el
 * cráneo". A child learning Spanish who knows "casco" does not know "cráneo" or
 * "lastimarse", so the clue was harder than its answer and useless for finding
 * it. Nearly every definition in that bank opened on a category word — objeto,
 * calzado, prenda, terreno, tejido — which was usually the hardest word in it.
 *
 * The swap test is the other half, and it answers a different failure: a clue
 * that is easy, concrete and still fits half the pack. "Mi ___ cocina arroz con
 * frijoles para toda la familia" reads like a finished clue for "la abuela" —
 * but the grandfather, the cousin, the neighbour and the friend all cook too,
 * so a bingo player holding two of them has no way to choose and a quiz team,
 * with no card to eliminate against, has nothing at all. The rule above it is
 * about the WORDS being too vague; this one is about the SENTENCE describing a
 * scene rather than the answer. Both passed every string check we have, because
 * the ambiguity is semantic — only the audit prompts and a reader can see it.
 *
 * The examples here are deliberately off-topic for any one pack, so the model
 * copies the pattern rather than the words.
 */
const LEARNER_AUDIENCE = `WHO THIS IS FOR
The children are English speakers in US classrooms who are LEARNING Spanish, grades 2 to 8, most of them beginners. They are not Spanish speakers. Write every clue for a child who knows the answer word and a few hundred everyday Spanish words — not for a Spanish-speaking child of the same age.`;

/* The audience is shared; what makes a good CLUE is not.
 *
 * Who the child is never changes — an English-speaking beginner in a US
 * classroom — so that paragraph is one constant every type is held to. The
 * rules under it are instructions about a particular kind of clue, and they do
 * not survive a change of kind: "describe it, do not define it" has nothing to
 * say about a verb form, and a swap test phrased as "what it is made of, what
 * it does" is the wrong question to ask about «hablé», where the only thing
 * separating it from «hablas» is person and tense.
 *
 * Keeping them shared meant a forma prompt carried two swap tests that
 * disagreed, which is worse than either alone. So each type states its own,
 * and each one restates the two principles that really are universal: a clue
 * must be easier than its answer, and easy words must still point at one
 * answer. See src/deckTypes.js.
 */
function learnerRules(type = deckType()) {
  return `${LEARNER_AUDIENCE}

${type.clueRules}`;
}

/* The vocabulary-bank rules, without the JSON envelope.
 *
 * Split out because the same rules have to appear in two prompts: the one this
 * app sends when it generates a bank, and the one a person copies out to have a
 * bank written for them somewhere else and pasted back in. Two copies of these
 * rules would drift, and the drift would only show up as a pack that fails
 * validation for reasons the prompt no longer mentions.
 */
function gameItemsBody(topic, count, type = deckType()) {
  return `${type.itemsIntro(topic, count)}

${learnerRules(type)}

${type.itemsTask(topic, count)}

${type.itemRules}`;
}

/* Which KIND of bank this game needs, from what the teacher said.
 *
 * The website used to ask outright, with a card per deck type. That put our own
 * taxonomy in front of a teacher who has no reason to know it: "verb and word
 * forms" versus "little words" is a distinction about how a pack is BUILT, and
 * the person making a bingo game for Tuesday should not have to learn it. So
 * they describe the lesson instead and this picks the shape.
 *
 * The menu is generated from the registry, so a deck type added there is
 * classifiable the moment it exists — nothing here, on the form, or in PHP has
 * to be told about it.
 *
 * Measured on a first draft before it shipped: 12/12 on ordinary topics and
 * 10/12 on deliberately awkward ones, with both misses on input that is genuinely
 * two things at once. The two rules below are what those misses taught —
 * the description outranks the topic, and a mixed lesson is judged on what the
 * child is actually made to produce. Vague input ("Unit 3", "Repaso") correctly
 * fell back to vocabulary every time, which is why that fallback is stated
 * rather than left to the model's judgement.
 *
 * A wrong answer is cheap but not free: it is one call, and the site shows what
 * was chosen and lets the teacher change it. It must never be a hard error.
 */
function buildDeckTypePrompt(topic, describe = '', kind = '') {
  /* Only the kinds this game can actually be.
   *
   * A bingo face is printed in a small square, so a deck of whole sentences is
   * not a choice a bingo game has. Offering it anyway would let a teacher's
   * description pick a type their game cannot build — and they chose the game
   * first, on the screen before this. With no kind given, everything is on the
   * menu. */
  const menu = Object.values(DECK_TYPES)
    .filter(t => !kind || !t.games || t.games.includes(kind))
    .map(t => `- "${t.id}": ${t.blurb}`)
    .join('\n');

  return `A Spanish teacher is making a classroom game. Decide which KIND of bank it needs.

THE TOPIC THEY TYPED: "${topic}"
WHAT THEY SAID IT IS FOR: "${describe || '(they did not say)'}"

The kinds:
${menu}

Decide by what goes ON A CARD — the thing the child has to produce:
- a WORD they are learning the meaning of → "vocabulario"
- an inflected FORM of a word: a conjugated verb, a plural, an adjective made to agree, a command → "forma"
- a small word that fills a slot: a preposition, a question word, an object pronoun → "palabra"

Three rules, in this order:
1. Where the topic and the description disagree, follow the DESCRIPTION. The topic is a label typed in a hurry; the description is the teacher saying what the lesson is actually for.
2. Where a lesson is two things at once — "family members and possessive adjectives" — choose the one the child spends most of the game PRODUCING, not the one mentioned first.
3. Where nothing clearly points at a form or a small word, answer "vocabulario". It is the most general kind and what most games are. A topic you cannot place is a vocabulary topic.

Return JSON only: {"type": "...", "why": "a short line a teacher would understand, naming what the game will ask them to produce"}`;
}

function buildGameItemsPrompt(topic, count = 30, type = deckType()) {
  return `${gameItemsBody(topic, count, type)}

Return your response as JSON in this format:
{"items": [${type.itemShape}]${type.itemsAlso || ''}}
Return exactly ${count} items. Only return valid JSON, nothing else.`;
}

/* Does each gap actually have one answer?
 *
 * Ambiguity here is semantic, not textual: "Yo ___ todos los días" and "Yo ___
 * en la escuela" are different strings, so every string-based check passes them
 * both, and both are wide open. It only bites when the bank is semantically
 * uniform — a deck of verb forms above all, where any of them fits any frame —
 * so the check has to ask what could actually fill the gap, which means asking
 * a model.
 */
function buildGapAuditPrompt(items, type = deckType()) {
  const bank = items.map(i => i.face).join(', ');
  const entries = auditEntries(items);
  const numbered = entries
    .map((e, n) => `${n + 1}. [${e.kind === 'gap' ? 'gap' : 'description'}] ${e.text}`)
    .join('\n');

  return `You are checking the clues of a Spanish classroom game for children who are LEARNING Spanish, most of them beginners.

These are the only words a child can choose from, all printed together on one card:
${bank}

Below are the clues. A [gap] clue is a sentence with the answer replaced by ___. A [description] clue describes the answer without naming it.

${numbered}

For EACH clue answer THREE separate questions.

1. "fits": which words from the list above the clue could point at — for a gap, which could fill it; for a description, which it could describe. Judge only against the list. A clue is fine when exactly one does; two or more means a child holding a card has two right answers and no way to choose.

2. ${type.auditSpecific}

3. "hard": any word in the clue, other than the answer, that a beginner learning Spanish is unlikely to know AND that is harder than the answer itself. A clue harder than its answer cannot lead a child to it: "Objeto duro que se pone para no lastimarse el cráneo" for "casco" is hard because of "cráneo" and "lastimarse". Most clues should have none; do not list ordinary words.

Return your response as JSON in this format:
{"checks": [{"n": 1, "fits": ["luna"], "specific": true, "hard": []}, {"n": 2, "fits": ["casco"], "specific": true, "hard": ["cráneo", "lastimarse"]}]}
Include every clue. Only return valid JSON, nothing else.`;
}

/* Does each form actually match its own formula?
 *
 * The one check a deck of FORMS needs and a deck of words has no use for, and
 * nothing in bingoCards.js or jeopardyBoard.js can do it: it is a fact about
 * Spanish, not about the data. A real generated bank came back with
 * "comparamos" under the formula "nosotros + comprar (pretérito)" — the
 * pretérito of comparar, not comprar — and every string check passed it,
 * because as DATA it is a perfectly good entry: a unique face, a formula, an
 * English, a sentence that fits.
 *
 * It is the worst defect this product can ship. An ambiguous clue costs a
 * disputed square; a wrong conjugation printed across 96 bingo cards teaches
 * thirty children the wrong form and is the thing a grammar pack is sold to
 * prevent. The bank audit judges clues one at a time and has no question this
 * fits under, so it gets a call of its own, run only by types that declare it.
 */
function buildFormAuditPrompt(items) {
  const numbered = items
    .map((i, n) => `${n + 1}. ${i.face}  —  formula: ${i.prompt || '(none)'}  —  English: ${i.en || '(none)'}`)
    .join('\n');

  return `You are checking a Spanish grammar bank written for a classroom game. Each entry is a FORM, with the formula that describes it and its English.

Your only job is to say whether the form is RIGHT. Ignore style, ignore the sentences, ignore whether the English is elegant.

${numbered}

For EACH entry, work the formula out yourself and compare it to the form that is written.

- "ok": true when the written form is exactly what the formula produces — the right verb, the right person, the right tense or mood, spelled correctly, accents included.
- "ok": false when it is not. The commonest failure is a form built from a DIFFERENT but similar-looking word: "comparamos" (comparar) written under "nosotros + comprar", or "sentamos" (sentar) under "sentir". A missing or wrong accent is also false — "hablo" and "habló" are different tenses.
- "should": only when ok is false — the form the formula actually produces.
- "why": only when ok is false — a few words saying what went wrong, in English.

Also mark ok:false when the ENGLISH does not match the form, since that is the other half of what a child is taught.

Return your response as JSON in this format:
{"checks": [{"n": 1, "ok": true}, {"n": 7, "ok": false, "should": "compramos", "why": "comparamos is from comparar, not comprar"}]}
Include every entry. Only return valid JSON, nothing else.`;
}

/* The clues the audit judges, in the order it numbers them.
 *
 * Exported so the handler can map a check's number back to the clue it is
 * about without re-deriving the order and getting it wrong by one.
 */
function auditEntries(items) {
  const entries = [];
  items.forEach(item => { if (item && item.sentence) entries.push({ item, kind: 'gap', text: item.sentence }); });
  items.forEach(item => { if (item && item.definition) entries.push({ item, kind: 'description', text: item.definition }); });
  return entries;
}

/* The quiz board.
 *
 * Categories are THEMES, and the dollar value carries the difficulty. The first
 * version let a category be a clue type instead, which failed twice over: the
 * "topical" categories all collapsed into "define this word", so three columns
 * of five were definitions; and nothing tied a category's answers to its own
 * name, so a category called "people of the story" ended up holding "the drum"
 * and "the victory".
 *
 * Tying the clue FORM to the row instead fixes both. It also makes the value
 * mean something: every team knows a $100 is a translation and a $500 is heard,
 * not read, which is the promise a quiz board is supposed to make.
 */
/* The quiz-board rules, without the JSON envelope.
 *
 * `bank` is text rather than items for one reason: in the combined prompt the
 * board is written in the same breath as the vocabulary, so there is no list to
 * paste in yet — it says "the words you just wrote" instead. Same rules either
 * way, which is the point of the split.
 */
function jeopardyBody(topic, bank, categoryCount, cluesPerCategory, type = deckType()) {
  return `You are a Spanish teacher building a ${categoryCount}-category quiz board about "${topic}" for English-speaking children who are learning Spanish.

${learnerRules(type)}

${type.boardBankIntro}
${bank}

${type.boardGrouping(categoryCount, 'Invent')}

${boardRules(categoryCount, cluesPerCategory, type)}

${finalRules(`$${cluesPerCategory * 100}`, type)}`;
}

/* Everything a board must meet once its categories exist. Shared with the
 * board edit prompt, for the same reason a type's item rules are shared with
 * the bank's. The ladder comes from the deck type: what makes a $500 harder
 * than a $100 is a property of what the deck tests, not of the board. */
function boardRules(categoryCount, cluesPerCategory, type = deckType()) {
  const rungs = type.ladder.slice(0, cluesPerCategory);
  const ladder = rungs
    .map((rung, i) => `- $${(i + 1) * 100} — ${rung.text}`)
    .join('\n');
  const audioValues = rungs
    .map((rung, i) => (rung.audio ? `$${(i + 1) * 100}` : null))
    .filter(Boolean);

  return `Every answer in a category must genuinely belong to it. If a category is about people, every one of its five answers must be a person. A category whose answers do not fit its own name is the worst thing you can hand back.

Category names are printed in a narrow column heading: at most 22 characters, including spaces.

DIFFICULTY COMES FROM THE VALUE, NOT THE CATEGORY.
Within every category the clue at each value takes this exact form:
${ladder}

${type.boardRowSummary(categoryCount)}

For each clue give:
- "value": the dollar amount
- "prompt": the clue itself, in the form its row requires
- "answer": ${type.boardAnswerField}
${type.promptEnField}
- "audio": true on the ${audioValues.join(' and ')} rows only, so the screen reads those aloud instead of showing them.

${type.boardClueRules()}

No two clues anywhere on the board may share an answer, and a clue must never contain the word it is asking for.`;
}

/* The last clue of the game, which every team bets on.
 *
 * It is the one clue the whole class answers at once, after the board is empty
 * and after each team has staked part of its score on it, so it carries more
 * weight than any square and has to be written differently:
 *
 * - Its CATEGORY is announced before anyone bets, and is the only thing a team
 *   has to bet on. That is the whole decision, so the name has to say something
 *   real about what is coming — "La comida de la fiesta", not "Palabras". It is
 *   squeezed from both sides, which is why the rule states both: too vague and
 *   the bet is a coin toss, too narrow and it hands over the answer. "Los
 *   símbolos del país" over "bandera" is the second failure — it reads like a
 *   category and is really the clue, so every team bets the maximum and the
 *   round stops being a wager at all. The round exists so the last-place team
 *   can still win, and that needs the other teams to carry real risk.
 * - It is SHOWN, never heard. The teams are writing one answer with everything
 *   they have staked on it; a clue that cannot be re-read turns the round into
 *   a listening test at the exact moment it should be a thinking one.
 * - Its answer is a bank word no square used. A team that has already seen the
 *   answer this game has been handed the round.
 *
 * A gap sentence rather than a definition, because this is the rung the board
 * spends the least time on (one row of five) and it is the one that asks a
 * child to read a whole line of Spanish — the right shape for the clue everyone
 * gets to look at for a full minute.
 */
const { MAX_FINAL_CATEGORY_CHARS } = require('../jeopardyBoard');

function finalRules(topValue, type = deckType()) {
  return `THE FINAL WAGER — "La Apuesta Final".
After the board is empty, every team bets some of its money on ONE last clue. They see the category first, bet, and only then see the clue; a right answer wins the bet and a wrong one loses it.

Write that one clue, as "final":
- "category": what the clue is about, announced before anyone bets. This is all a team has to go on, so name something real: "La comida de la fiesta" tells a team whether it knows this corner of the topic; "Vocabulario" tells it nothing. At most ${MAX_FINAL_CATEGORY_CHARS} characters.
  But it must NOT give the answer away. The teams bet before they see the clue, so a category that names the answer turns the bet into free money — every team stakes everything and the round decides nothing.
  The test: count how many words of the vocabulary list could be the answer to this category. Fewer than three or four and it is too narrow — name the AREA, not the THING.
  Too narrow: "El instrumento de cuerdas" (only one word on the list can be that).
  Better: "La música de la fiesta" — a team knows whether it studied this ground, and several words still fit.
${type.finalPromptField}
${type.finalPromptEnField}
- "answer": ${type.finalAnswerField}

Its answer must be a word NO square on the board uses. Every team has just played through the board, so an answer already seen there is one they have been handed.

This is the last and biggest clue of the game. It may ask a little more than the ${topValue} squares — the teams can see it, they get longer to think, and they have all bet on it — but it must still be reachable by a beginner under the rules above. A final nobody can get is a round where everybody loses their money at once.`;
}

/* What the board may take from the bank, and what it must write itself.
 *
 * This was once a flat ban on reusing any clue, which backfired. The model
 * wrote its best definition into the bank and then a deliberately different —
 * therefore second-best — one onto the board, and that is the wrong way round:
 * a bingo player has the card in front of them and can eliminate the other
 * twenty-four words, while a quiz team has nothing to eliminate against. The
 * board is the half that can least afford the weaker clue.
 *
 * It also fought the learner rules. For a beginner bank of concrete nouns there
 * are only so many ways to describe "el perro" in words a beginner knows, so
 * demanding a second one pushed it into the two failure modes those rules
 * exist to stop: too vague, or a word harder than the answer.
 *
 * Sentences are the other case. A gap sentence has room to vary that a
 * ten-word definition does not — another situation, another detail — so
 * keeping those distinct costs nothing and is still required.
 */
function freshWriting(bank) {
  return `WHAT THE BOARD TAKES FROM ${bank.toUpperCase()}, AND WHAT IT WRITES ITSELF.
The board takes its ANSWERS from the vocabulary bank. Its clues are written for the board.

- A gap sentence must be different from ${bank}'s. A sentence has plenty of room to vary, so there is no reason to repeat one.
- A definition is the other way round. Write the BEST definition for the child; if the best one turns out to be the one ${bank} already uses, use it anyway. Never make a definition vaguer, and never reach for a harder word, only to avoid repeating one. A quiz team has no card in front of them to eliminate against, so a weakened clue costs them far more than a repeated one ever could.`;
}

/* A quiz written from nothing but a topic, for the website.
 *
 * The builder writes a bank first and a board over it, because one bank serves
 * two products. A teacher buying a single quiz on the website has no second
 * product, so the board IS the pack: its answers are the vocabulary, and the
 * class reviews them from the board itself.
 *
 * So this asks for both halves in one reply — the words, and the board over
 * them — which is one call instead of two and keeps every rule the split
 * version applies. Each word still needs its article and its English, because
 * the class sees "la camisa (the shirt)" in the review list, and the board's
 * answers stay bare.
 */
function buildQuizGamePrompt(topic, categoryCount = 5, cluesPerCategory = 5, type = deckType()) {
  /* Only a type that can write its own bank from nothing but a topic.
   *
   * This prompt's first half is the bank rules inlined rather than taken from
   * the type, because a quiz-only pack needs a reduced entry — a face, an
   * article and an English, with no gap sentence or definition. Handing it a
   * type it does not have the wording for would produce one half in the type's
   * ladder and the other in vocabulary's, which reads plausible and is wrong.
   * The website is the only caller, and it does not pick a type yet. */
  if (!type.quizFromTopic) {
    throw new Error(`deck type "${type.id}" cannot write a quiz from a topic alone; generate a bank first and use buildJeopardyPrompt`);
  }
  const squares = categoryCount * cluesPerCategory;

  return `You are a Spanish teacher building a ${categoryCount}-category quiz board about "${topic}" for English-speaking children who are learning Spanish.

${learnerRules(type)}

FIRST CHOOSE THE VOCABULARY.
Pick ${squares + 1} Spanish words for this topic that a beginner should learn: ${squares} for the squares and one more for the final wager, whose answer may not appear on the board. Concrete, everyday words a child can picture. Give each one its article and its English — the class reviews this list on screen before playing, and a word without its article teaches the wrong thing.

The article is its OWN field. "face" is the bare word — "camisa", never "la camisa"; "zapatos", never "los zapatos" — and "article" holds el, la, los or las beside it. The screen puts them back together; a face with its article glued on comes out as "la la camisa". A word that takes no article at all ("verde", "correr") leaves "article" empty.

CATEGORIES ARE THEMES, NOT QUESTION TYPES.
Group those words into ${categoryCount} categories by meaning — people, places, objects, food, feelings, events, whatever this topic actually contains. ${NOT_A_QUESTION_TYPE}

${boardRules(categoryCount, cluesPerCategory, type)}

${finalRules(`$${cluesPerCategory * 100}`, type)}

Every answer on the board must be one of the words you chose, and every word you chose must be used exactly once — on a square, or as the final wager's answer.

Return your response as JSON in this format:
{"items": [{"face": "camisa", "article": "la", "en": "the shirt"}], "categories": [${type.categoryShape}], "final": ${type.finalShape}}
Only return valid JSON, nothing else.`;
}

function buildJeopardyPrompt(topic, items, categoryCount = 5, cluesPerCategory = 5, type = deckType()) {
  return `${jeopardyBody(topic, type.bankListing(items), categoryCount, cluesPerCategory, type)}

Return your response as JSON in this format:
{"categories": [${type.categoryShape}], "final": ${type.finalShape}}
Only return valid JSON, nothing else.`;
}

/* "Ask AI" on the game pack's bank and board.
 *
 * The worksheet's edit prompt hands back the whole list. Here that would mean
 * thirty entries of seven fields rewritten to change one, which is slow, and a
 * model asked to return a whole bank "improves" entries nobody asked about —
 * quietly undoing clues that passed the audit. So only the changes come back,
 * by number, and the server merges them in; everything else is untouched by
 * construction rather than by the model's restraint.
 */
const EDIT_ONLY_WHAT_WAS_ASKED = `Make the change the teacher asked for, and only that change. Everything the request does not touch stays exactly as it is — do not tidy, improve or reword anything you were not asked to. If the request cannot be done as asked, change nothing and say why in "note".`;

/* What the note has to say, so the NEXT request can lean on it.
 *
 * "Changed #12" is enough for the teacher reading it, and useless as memory: a
 * follow-up of "change it again" needs to know which word was there before, or
 * the model hands back the very word that was just rejected.
 */
function noteRule(example) {
  return `- "note": one short sentence, in English, telling the teacher what you changed. Say WHICH entry you changed and what it used to be — "${example}" — because the teacher's next request may refer back to it, and a note that only says "done" leaves them with nothing to point at.`;
}

/* The last few exchanges, so a follow-up has something to point at.
 *
 * Each request is otherwise answered on its own, against the bank as it stands
 * — which is what keeps an edit cheap and stops a stale transcript arguing
 * with a hand edit. But "I still don't like it, change it again" has no
 * meaning on its own: by then the word the teacher is complaining about is
 * gone from the bank, and "it" points at nothing.
 *
 * So a short window of what was asked and what was done travels with the
 * request. Bounded on purpose — a handful of one-line notes, never the old
 * banks — so the tenth tweak costs what the first one did. That is the whole
 * reason this is not a chat.
 */
function editHistory(history) {
  const turns = (Array.isArray(history) ? history : []).filter(t => t && t.request);
  if (!turns.length) return '';

  const lines = turns.map(t => `- The teacher asked: "${t.request}"\n  You did: ${t.note || 'not recorded'}`).join('\n');

  return `
EARLIER IN THIS EDITING SESSION, oldest first. The bank above already includes these changes; this is here so you can understand a request that refers back ("change it again", "still too hard"). Do not redo them, and do not go back to a word or a clue the teacher has already turned down:
${lines}
`;
}

function buildGameItemsEditPrompt(topic, items, request, history, type = deckType()) {
  const numbered = items.map((item, i) => `${i + 1}. ${JSON.stringify(item)}`).join('\n');

  return `You are a Spanish teacher editing the vocabulary bank of a classroom game pack about "${topic}", for English-speaking children who are learning Spanish. The same bank feeds a bingo game and a quiz game.

${learnerRules(type)}

THE BANK AS IT STANDS, numbered:
${numbered}
${editHistory(history)}
THE TEACHER'S REQUEST:
"${request}"

${EDIT_ONLY_WHAT_WAS_ASKED}

Every entry you write or rewrite must meet the rules below, checked against the WHOLE bank, not only the entries you touched:

${type.itemRules}

Return ONLY what changed, as JSON in this format:
{"changes": [{"n": 3, ${type.itemShape.slice(1)}], "add": [${type.itemShape}], "remove": [7], "note": "Made the definition for #3 simpler."}

- "changes": each entry you rewrote, by its number above, written out IN FULL — every field, not only the ones you changed. Leave "article" out of a word that does not take one. To swap a word for a different one, rewrite its entry here.
- "add": new entries, in full, only when the request asks for more words.
- "remove": the numbers of entries to delete, only when the request asks for fewer.
${noteRule("Changed #12 from 'el queso' to 'la mantequilla'.")}
Leave out any list with nothing in it. Only return valid JSON, nothing else.`;
}

function buildBoardEditPrompt(topic, items, categories, final, request, history, type = deckType()) {
  const categoryCount = categories.length;
  const cluesPerCategory = Math.max(0, ...categories.map(c => (c.clues || []).length));
  const board = categories.map((cat, ci) => {
    const clues = (cat.clues || []).map(q => {
      const text = { prompt: q.prompt, promptEn: q.promptEn, answer: q.answer };
      return `  $${q.value}${q.audio ? ' (heard)' : ''}: ${JSON.stringify(text)}`;
    });
    return [`Category ${ci + 1}: ${JSON.stringify(cat.name || '')}`, ...clues].join('\n');
  }).join('\n\n');
  // The final wager clue, shown the same way the squares are, so a request that
  // names it ("make the final easier") has something to point at.
  const finalBlock = final && final.prompt
    ? `\n\nTHE FINAL WAGER, as it stands:\n${JSON.stringify({
      category: final.category, prompt: final.prompt, promptEn: final.promptEn, answer: final.answer,
    })}`
    : '\n\nTHE FINAL WAGER: this board has none yet. Write one only if the teacher asks for it.';
  // The bingo clues, so the board can be kept clear of them. Only an edit sees
  // these: a board generated from scratch is written before anyone asks.
  const bingoClues = items
    .flatMap(i => [i.sentence, i.definition])
    .filter(Boolean)
    .map(t => `- ${t}`)
    .join('\n');

  return `You are a Spanish teacher editing a ${categoryCount}-category quiz board about "${topic}" for English-speaking children who are learning Spanish.

${learnerRules(type)}

${type.boardBankIntro}
${type.bankListing(items)}

THE BOARD AS IT STANDS:
${board}${finalBlock}
${editHistory(history)}
THE TEACHER'S REQUEST:
"${request}"

${EDIT_ONLY_WHAT_WAS_ASKED}

The board is always ${categoryCount} categories of ${cluesPerCategory} clues: nothing is added or removed, only rewritten. Everything you write must meet the board's rules:

${type.boardGroupingShort}

${boardRules(categoryCount, cluesPerCategory, type)}

${finalRules(`$${cluesPerCategory * 100}`, type)}

${freshWriting('the bingo pack')}

These are the bingo pack's clues, so you can tell which sentences are already taken:
${bingoClues}

Return ONLY what changed, as JSON in this format:
{"names": [{"category": 2, "name": "Gente de la historia"}], "clues": [{"category": 2, "value": 300, "prompt": "...", "promptEn": "...", "answer": "..."}], "final": ${type.finalShape}, "note": "Renamed category 2 and rewrote its $300 clue."}

- "names": each category you renamed, by its number above.
- "clues": each clue you rewrote, by its category number and value, IN FULL — prompt, answer, and promptEn (the $100 row has no promptEn). A clue's value and whether it is heard belong to its row, so they never change.
- "final": the final wager clue, IN FULL, and ONLY if the request is about it. Leave it out entirely for a request about the squares.
${noteRule("Rewrote the $300 clue in category 2; its answer used to be 'el casco'.")}
Leave out any list with nothing in it. Only return valid JSON, nothing else.`;
}


/* The same audit, over the quiz board.
 *
 * The board was the one piece of writing in a pack that nothing checked, and
 * it is the piece that can least afford it. Its clues cannot be the bank's
 * gap sentences, so they are written fresh and then never looked at again;
 * meanwhile a quiz team has no card to eliminate against, which is exactly
 * the support the bank's clues are allowed to lean on. A Christmas board
 * shipped "Son cables con bombillas de colores" for "luces" - three words
 * harder than the answer - and passed every check there was, because there
 * were none.
 *
 * Same three questions as buildGapAuditPrompt, weighted differently:
 * specificity is the main one here rather than the second, since there is no
 * list to fall back on.
 */
function buildBoardAuditPrompt(categories, final, type = deckType()) {
  const entries = boardAuditEntries(categories, final, type);
  const answers = [...new Set((categories || [])
    .flatMap(c => (c.clues || []).map(q => q.answer))
    .concat(final && final.answer ? [final.answer] : [])
    .filter(Boolean))].join(', ');
  const numbered = entries
    .map((e, n) => `${n + 1}. [${e.kind}]${e.audio ? ' [heard, not shown]' : ''} ${e.text}`)
    .join('\n');

  return `You are checking the clues of a Spanish quiz game for children who are LEARNING Spanish, most of them beginners.

Teams hear or read one clue at a time and say the answer aloud. THEY HAVE NO WORD LIST IN FRONT OF THEM. There is nothing to eliminate against, so every clue has to reach its answer on its own.

These are the answers used on the board. The teams never see them; they are here only so you can spot a clue that points at more than one:
${answers}

Below are the clues. A [gap] clue is a sentence with the answer replaced by ___. A [description] clue describes the answer without naming it. A clue marked [heard, not shown] is read aloud and never appears on screen, so a child cannot go back and re-read it.

${numbered}

For EACH clue answer THREE separate questions.

1. "fits": which answers from the list above the clue could point at. Exactly one is fine. Two or more means the screen will mark a team wrong for an answer that was just as good as the intended one.

2. "specific": could someone who has NEVER SEEN that list still land on the intended answer, from the clue alone? Judge this without the list. This is the question that matters most here, because the teams really do have no list: a clue that only works by elimination does not work at all. "Es un animal que vive en casa" is not specific - a dog, a cat and a fish all fit. "Vive en casa, dice «guau» y mueve la cola" is.

3. "hard": any word in the clue, other than the answer, that a beginner learning Spanish is unlikely to know AND that is harder than the answer itself. A clue harder than its answer cannot lead a child to it: "Son cables con bombillas de colores que brillan por la noche" for "luces" is hard because of "cables" and "bombillas". Judge a [heard, not shown] clue more strictly - there is no text to puzzle over, so a single unknown word can sink the whole sentence. Most clues should have none; do not list ordinary words.

Return your response as JSON in this format:
{"checks": [{"n": 1, "fits": ["árbol"], "specific": true, "hard": []}, {"n": 2, "fits": ["luces"], "specific": true, "hard": ["cables", "bombillas"]}]}
Include every clue. Only return valid JSON, nothing else.`;
}

/* The board clues the audit judges, in the order it numbers them.
 *
 * Rows whose rung is metalanguage are left out — see the comment inside. For
 * vocabulary that is the cheapest row alone, which asks how to say an English
 * word: already English, naming what it wants, with no Spanish to outrun the
 * answer. Every other row is a Spanish clue that has to stand alone.
 *
 * Exported for the same reason auditEntries is - so the handler can map a
 * check's number back to the clue without re-deriving the order.
 */
function boardAuditEntries(categories, final, type = deckType()) {
  const cats = Array.isArray(categories) ? categories : [];
  /* Which rows the audit reads at all.
   *
   * A row is skipped when its rung is not Spanish to be understood but
   * METALANGUAGE naming the parts of an answer. Vocabulary has one such row, the
   * cheapest, which asks how to say an English word; a deck of forms has two, the
   * formula and the transformation, and they are the same case for the same
   * reason — there is no Spanish in them that could outrun the answer.
   *
   * By rung rather than by value, because that is where the fact lives. Judging
   * the transformation row cost five false "hard word" flags per board, every one
   * of them «tiempo» in "de «hablaron» a nosotros, mismo tiempo" — and an audit
   * that cries wolf on a fifth of the board is an audit nobody reads.
   */
  const ladderValues = [...new Set(cats.flatMap(c => (c.clues || []).map(q => Number(q.value) || 0)))]
    .sort((a, b) => a - b);
  const skip = new Set(ladderValues.filter((value, i) => {
    const rung = type.ladder[i];
    return rung ? rung.audited === false : i === 0;
  }));

  const entries = [];
  cats.forEach(cat => {
    (cat.clues || []).forEach(clue => {
      if (!clue || !clue.prompt) return;
      if (skip.has(Number(clue.value) || 0)) return;
      entries.push({
        category: cat.name || '',
        value: clue.value,
        answer: clue.answer || '',
        // The row decides this, but reading the clue is one less thing to
        // keep in step with CLUE_LADDER if the board is ever reshaped.
        kind: /___/.test(clue.prompt) ? 'gap' : 'description',
        text: clue.prompt,
        audio: !!clue.audio,
      });
    });
  });
  /* The final wager goes last, which is also where it is played.
   *
   * It is the one clue a team stakes its score on, so it is the last one that
   * should go out unread — and it is written apart from the squares, which is
   * exactly how a clue ends up never being checked against anything.
   */
  if (final && final.prompt) {
    entries.push({
      category: final.category || 'La Apuesta Final',
      value: 'final',
      answer: final.answer || '',
      kind: /___/.test(final.prompt) ? 'gap' : 'description',
      text: final.prompt,
      audio: false,
    });
  }
  return entries;
}

/* Both games in one prompt, for pasting a finished pack straight in.
 *
 * The app can generate these itself, but that is two model calls and a wait;
 * this is the text to hand to a chat window instead, and what comes back is
 * pasted into the builder whole. The bank and the board are written together,
 * so the board is told to draw its answers from the vocabulary in the same
 * reply rather than from a list supplied up front.
 *
 * The two halves stay separate documents on purpose. The board writes its own
 * gap sentences rather than repeating part 1's, because the bingo pack and the
 * quiz pack are sold separately and a buyer of both should not find the same
 * sentence in each. Definitions are the exception — see freshWriting.
 */
function buildGamePackPrompt(topic, count = 30, categoryCount = 5, cluesPerCategory = 5, type = deckType()) {
  return `Write a complete Spanish game pack about "${topic}". It has two parts, and you are writing both in one reply.

=== PART 1 — THE VOCABULARY BANK ===

${gameItemsBody(topic, count, type)}

=== PART 2 — THE QUIZ BOARD ===

${jeopardyBody(topic, `the ${count} words you wrote in part 1, and nothing else`, categoryCount, cluesPerCategory, type)}

${freshWriting('part 1')}

=== WHAT TO RETURN ===

One JSON object holding both parts, plus the store section it belongs in:
{"theme": "${topic}", "category": "", "items": [${type.itemShape}], "categories": [${type.categoryShape}], "final": ${type.finalShape}}

"category" is the seller's TpT store section for this pack's THEME, chosen from
this list and copied exactly, emoji included: ${CUSTOM_CATEGORIES.join(' | ')}
Leave it "" unless the theme plainly belongs in one — a shopper browsing that
section would expect to find this pack there; a loose association is not enough,
and "" is the normal answer. It has nothing to do with "categories", which is
the quiz board.

Exactly ${count} items, exactly ${categoryCount} categories of ${cluesPerCategory} clues, and one "final". Only return valid JSON, nothing else.`;
}

/* The TpT listing for a game pack.
 *
 * Built on the worksheet listing — same title discipline, same fixed menus,
 * same HTML rules (LISTING_HTML_RULES) — but a game is sold on a different
 * promise, so the sections differ: how the online half runs a class, where to
 * try it before buying, and what is printed.
 *
 * Everything the listing may claim is passed in as FACTS, counted from the pack
 * or checked against the site's code. The model writes the copy; it does not
 * decide what the product contains. "Up to eight teams" or "96 cards" is only true
 * because the pack or the site says so, and a listing that promises a feature
 * the site lacks is a refund.
 *
 * The pack's own room code is never passed in. The listing is public, and the
 * code opens every answer; the demo room is the only link a listing may carry.
 */
/* How each rung reads in a sentence a shopper understands.
 *
 * The listing has to say what the board actually asks, and that is no longer one
 * fixed sentence: a vocabulary board climbs translation to heard gap, a board of
 * verb forms climbs formula to transformation. A listing that promises the wrong
 * ladder describes a product the buyer does not receive, which this repo counts
 * as a refund — so the fact is BUILT from the deck type's ladder rather than
 * written out, and a new type cannot forget to update it.
 */
const FORM_PHRASES = {
  translation: 'a translation',
  definition: 'a Spanish definition',
  gap: 'a sentence with a gap',
  formula: 'a formula to build the form from',
  english: 'the English to put into Spanish',
  transformation: 'one form to turn into another',
  'two-sentence': 'two sentences that share one word',
  function: 'what the little word does',
  comprehension: 'a Spanish sentence to say the meaning of',
  'translation-change': 'an English sentence to put into Spanish with one change applied',
  /* A dictation is normally in the HEARD half of the sentence below and never
   * reaches this map — but a teacher can now switch that row to shown in the
   * editor, and a rung with no phrase here is advertised as "a clue". */
  dictation: 'a Spanish sentence to write down word for word',
};

function ladderFact(type) {
  const rungs = type.ladder.map((rung, i) => ({ ...rung, value: (i + 1) * 100 }));
  // "is" on the first only, so the sentence reads as a list rather than
  // repeating the verb five times.
  const read = rungs.filter(r => !r.audio)
    .map((r, i) => `$${r.value} ${i === 0 ? 'is ' : ''}${FORM_PHRASES[r.form] || 'a clue'}`);
  const heardRungs = rungs.filter(r => r.audio);
  const heard = heardRungs.map(r => `$${r.value}`);
  const many = heard.length > 1;
  /* Name what a heard row ASKS when the shown rows do not already ask it.
   *
   * On a deck of words the heard rows are the $200 and $300 again without the
   * reading, so naming their form would only repeat the sentence — "a LISTENING
   * clue" is the whole of what is new about them. A deck of sentences is the
   * other case: its heard row is a DICTATION, a task that appears nowhere else
   * on the ladder, and left unnamed the listing sold a board of translation and
   * dictation as a board of translation. Which it is depends on the type, so it
   * is read off the ladder rather than written per type.
   */
  const shownForms = new Set(rungs.filter(r => !r.audio).map(r => r.form));
  const fresh = [...new Set(heardRungs
    .filter(r => !shownForms.has(r.form) && FORM_PHRASES[r.form])
    .map(r => FORM_PHRASES[r.form]))];
  const asks = fresh.length ? ` — ${fresh.join(', and ')}` : '';
  const listen = heard.length
    ? ` ${heard.join(' and ')} ${many ? 'are LISTENING clues' : 'is a LISTENING clue'} the site reads aloud without showing the text${asks}, at an adjustable speed, and it can repeat ${many ? 'them' : 'it'}.`
    : '';
  return `Difficulty rises with the value in every category: ${read.join(', ')}.${listen}`;
}

const GAME_LISTING_FACTS = {
  quiz: [
    'Plays in any web browser on the classroom projector or smartboard: open spanishclassprintables.com and type the code from the Play Online sheet that comes with the download. Nothing to install and no student accounts or devices — students answer on paper.',
    'The website runs the whole game: 2 to 8 teams, whose turn it is, the score for every team, a writing timer the teacher starts at 30 or 45 seconds, and a Daily Double hidden on the board.',
    'The whole word list goes up on screen to review before the game, with the English beside each word and every word said aloud in Spanish when it is tapped, so the class can go through it together without printing the word list. The same list opens again at any moment during the game as a quick reminder, with the English one tap away, and can be set to close itself after thirty seconds so that a look at the words does not become a break in the game.',
    'The review also runs as flashcards, one word at a time: a card starts in Spanish (hear it, say it back) or in English (the class says the Spanish before the card turns), in list order or shuffled. It can run by itself, saying each word and pausing for the class to repeat it, so the teacher is free to walk the room. The automatic voice can be switched off, and any word can still be heard with one tap.',
    'Made to feel like a game show: every team is dealt an animal mascot with a Spanish team name (Los Tigres, Las Ranas…), and the board has sound effects, confetti for every right answer, a countdown ring for the timer, and a full-screen Daily Double reveal that shows the value doubling. One button turns the sound effects off.',
    'An optional rule for a class that is still learning the words, switched on before the game starts and off unless the teacher asks for it: a team that is stuck may ask to see the word list, and wins half that square instead of all of it. Any other team that answers after them still wins the square in full, and each team may do it twice a game. The website does the arithmetic and shows every team what it has left.',
    'Ends on a final wager round, La Apuesta Final: the teams see the category, each bets as much of its money as it dares, and then one last clue decides the game. A team with little or nothing left may still bet up to the value of the biggest square, so every team goes into the last question with a way back — and no team can finish below zero. The website takes the bets, checks each one against what the team can afford, and does the adding and subtracting itself.',
    'The questions can be changed online to suit the class: add this game at spanishclassprintables.com with the code from the download, then edit any clue, answer or word, and choose which rows are read aloud instead of shown — turn the listening clues off for a room with no speakers, or switch more of them on to drill listening. The website plays your version from then on, and the printed pages in this download stay exactly as they are.',
    'The teacher stays in charge of the scoreboard: tap any team to fix its score — for a point awarded to the wrong team, an answer argued out afterwards, or a bonus the game has no square for.',
    'Keeps every team in the race: a crown marks the team in the lead, the screen announces "¡Nuevo líder!" when the lead changes hands, a flame marks a team on a streak of three right answers, and the game ends on a podium that names every team\'s place.',
    'Every team writes an answer to every clue on its own answer sheet, so the whole class works on every question, not just the team that picked it.',
    'Printed pages, named EXACTLY as they are printed: Play Online, one page for the teacher with the game code and a QR code to start it on the website; Cómo Jugar (How to Play), one page of teacher directions for playing with the website and without it; Lista de Palabras (Word List), to study before playing; Hoja del Equipo (Team Answer Sheet), with a place to write the final bet; Guion del Maestro (Teacher Script), with every clue and answer, and the final wager clue at the end; Hoja de Puntuación (Score Sheet), with a row for the final bet; and Tablero de Juego (Game Board), a printed board — so the whole game, final wager and all, can also be run on paper with no projector at all.',
  ],
  bingo: [
    'Plays in any web browser on the classroom projector or smartboard: open spanishclassprintables.com and type the code from the Play Online sheet that comes with the download. Nothing to install and no student accounts or devices — students play on printed cards.',
    'The website is the caller: it draws the words in a random order and shows each one as a clue, never the Spanish word itself. The teacher chooses the clue: the English word, a Spanish sentence with a gap, a Spanish definition, or a mix — easier to harder.',
    'It can read each clue aloud, at an adjustable speed, and repeat it.',
    'The whole word list goes up on screen to review before the game, with the English beside each word and every word said aloud in Spanish when it is tapped, so the class can go through it together without printing the word list. The same list opens again at any moment during the game as a quick reminder, with the English one tap away, and can be set to close itself after thirty seconds so that a look at the words does not become a break in the game.',
    'The review also runs as flashcards, one word at a time: a card starts in Spanish (hear it, say it back) or in English (the class says the Spanish before the card turns), in list order or shuffled. It can run by itself, saying each word and pausing for the class to repeat it, so the teacher is free to walk the room. The automatic voice can be switched off, and any word can still be heard with one tap.',
    'Each word is drawn as a numbered bingo ball that rolls onto the screen with the rattle of a bingo drum, and a card that really wins gets a fanfare and confetti. One button turns the sound effects off.',
    'Win by a line (row, column or diagonal) or by a full card. The teacher checks a winning card by typing its number, and the site says whether it really wins.',
    'Every card is different and numbered.',
    'The words can be changed online to suit the class: add this game at spanishclassprintables.com with the code from the download, then edit any word or its clues. The website calls your version from then on, the printed pages in this download stay as they are, and a fresh set of cards for the new words can be printed from the site.',
    'Printed pages, named EXACTLY as they are printed: Play Online, one page for the teacher with the game code and a QR code to start it on the website; Cómo Jugar (How to Play), one page of teacher directions for playing with the website and without it; Lista de Palabras (Word List), to study before playing; Cartones de Bingo (Bingo Cards); and Hoja del Cantor (Caller Sheet), with every word and its clues in a ready-shuffled order and tick columns for four games, followed by optional cut-apart calling slips (one per word, with all its clues) for drawing words at random and for playing more than four games — so the game can also be called on paper with no projector at all.',
  ],
};

function buildGameListingPrompt(pack) {
  const {
    kind, theme, words = [], pageCount, customCategory = '', demoUrl = '',
    cardCount = 0, cardSizes = '', cardPages = 0, clueCount = 0, categoryNames = [],
  } = pack;
  const isQuiz = kind === 'quiz';
  /* The ladder fact is generated from the pack's own deck type and goes first,
   * where the hardcoded one used to sit. */
  const type = deckType(pack.type);
  /* A deck whose answers are open says so in the listing.
   *
   * A buyer who expects the screen to mark every answer right or wrong, and
   * finds a game where the teacher judges, has been mis-sold — and the judging
   * is a selling point rather than a caveat, so it is stated as one. */
  const marking = type.answerIsOpen
    ? ['Some sentences can be translated more than one way, and the teacher decides: the screen shows one correct answer and tells the class another may count, which turns a near miss into a moment to explain why a different wording works. The game keeps the score either way.']
    : [];
  const factList = isQuiz
    ? [ladderFact(type), ...marking, ...(GAME_LISTING_FACTS[kind] || [])]
    : (GAME_LISTING_FACTS[kind] || []);
  const facts = factList.map(f => `- ${f}`).join('\n');
  const vocabList = words.map(w => `${w.es} (${w.en})`).join(', ');
  const counts = isQuiz
    ? `Board: ${categoryNames.length} categories (${categoryNames.join(', ')}), ${clueCount} clues.`
    : `Cards: ${cardCount} different cards, in ${cardSizes} sizes, on ${cardPages} of the ${pageCount} pages. The larger size is the full game; the smaller gives quicker games for younger classes.`;
  const format = isQuiz ? 'Jeopardy Style Review Game' : 'Bingo Game';
  const demoLine = demoUrl
    ? `    3. A <p> inviting the teacher to try it first, opening with one emoji, bolding the invitation, and giving this exact address as plain text: ${demoUrl} . Say it is a free demo with sample words, not the words in this pack.`
    : `    3. (No demo is available — skip this section.)`;
  const trademark = isQuiz
    ? ` "Jeopardy Style" is how teachers search for this kind of game, and it describes the format; never write "Jeopardy" on its own, "Jeopardy!", or anything that reads as the name of the TV show — this is not affiliated with it.`
    : '';

  return `You are writing a Teachers Pay Teachers product listing for a Spanish classroom GAME that the teacher runs on the projector from a website, with printed pages that go with it.

GAME DETAILS
Game: ${isQuiz ? 'a Jeopardy style team quiz' : 'bingo'}
Theme: "${theme}"
Total pages: ${pageCount}
${counts}
Vocabulary (${words.length} words): ${vocabList}

WHAT IT DOES — these are the ONLY features you may describe. Do not add any
other feature, and do not exaggerate these.
${facts}

The children are English speakers learning Spanish, grades 2 to 8.

WRITE THE LISTING
- title: ${LIMITS.titleChars} characters at the very most - TpT's Title field stops
  accepting input there, so anything longer gets cut off mid-word. Count the
  spaces and the pipes before you answer.

  Build it from these parts, in this order:
    "<English topic> Spanish | ${format} | <Spanish topic>"

  For example:
    "Halloween Spanish | ${format} | Día de las Brujas"
    "Sports in Spanish | ${format} | Los Deportes"

  Keep "${format}" exactly as written.${trademark}
  When it will not fit, drop the Spanish topic first. Never abbreviate the topic,
  and never pad with "Printable", "Fun", "Engaging", "No-Prep" or "TPT".

- descriptionHtml: 250-400 words of HTML, written to be SCANNED not read. In this order:
    1. A bold bilingual headline as its own paragraph, opening with one emoji:
       <p><strong>🎉 <an inviting line about playing ${theme} in Spanish class> | <the theme in Spanish></strong></p>
    2. A <p> hook: what the game is, that the whole class plays together from the
       projector, and when to use it. Name the real-world date or occasion if the
       theme has one. Bold <strong>no-prep</strong> in it.
${demoLine}
    4. <p><strong>🖥️ How It Works</strong></p> then a <ul> of the online features
       from WHAT IT DOES, one <li> each, each opening with one emoji and a bolded
       label and a colon.
    5. <p><strong>📚 What's Included?</strong></p> then a <ul>, one <li> per printed
       page type and one for the online game, each opening with one emoji, then
       the name in <strong> in BOTH languages - <strong>Word List (Lista de
       Palabras)</strong> - then a colon and a short description. Use the page
       names EXACTLY as WHAT IT DOES gives them: a buyer looks for these names in
       the PDF, so a translated or reworded name is a page they cannot find. Use
       the CONCRETE COUNTS from GAME DETAILS. In the word list item, name 5-7 real
       example words from the vocabulary.
    6. <p><strong>⭐ Why Teachers Love It:</strong></p> then a <ul> of exactly three
       <li> benefits, each opening with one emoji and a bolded label and a colon.
       Concrete: review days, sub plans, the last day before a break.
    7. A closing <p> asking for a review, warm and varied rather than copied,
       opening with an emoji, bolding the ask, and saying why it helps the
       teacher - it earns them TpT credits and helps other teachers find it.
  NEVER print a room code, and never give any web address except
  spanishclassprintables.com${demoUrl ? ' and the demo address above' : ''}. The code for this pack is
  printed only on the teacher pages, because it opens every answer.
${LISTING_HTML_RULES}
- description: leave this field out entirely. The builder derives it from descriptionHtml.
- gradeLevels: leave this field out entirely. The builder stamps the store's grades.

EVERY FIELD BELOW IS A FIXED MENU. TpT rejects anything that is not on its list,
so copy the wording verbatim - no rephrasing, no pluralising, no inventing.

- subjectAreas: always exactly "Spanish" and "Vocabulary".
- tags: exactly ${LIMITS.tags}, in this priority order:
    1. "En español" - always.
    2. One from Holiday or Seasonal when the theme genuinely is one. Skip if not.
    3. "Games" - always; it is how teachers filter for this. Then "Activities".
    4. Fill the rest from Audience - "Homeschool", "Parents".
  Choose from:
${TAG_MENU}
${customCategoryRule(customCategory)}
- suggestedPrice: always the string "5.00".

Return your response as JSON in EXACTLY this format:
{
  "title": "...",
  "descriptionHtml": "<p>...</p>",
  "subjectAreas": ["..."],
  "tags": ["..."],
  "customCategories": ["..."],
  "suggestedPrice": "5.00"
}

Only return valid JSON, nothing else.`;
}

module.exports = {
  buildGameListingPrompt,
  buildQuizGamePrompt,
  buildGameItemsPrompt,
  buildDeckTypePrompt,
  buildGamePackPrompt,
  buildGapAuditPrompt,
  buildFormAuditPrompt,
  auditEntries,
  buildBoardAuditPrompt,
  boardAuditEntries,
  buildJeopardyPrompt,
  buildGameItemsEditPrompt,
  buildBoardEditPrompt,
  buildWordsPrompt,
  buildSentencesPrompt,
  buildJokesPrompt,
  buildHiddenMessagePrompt,
  buildProcessLyricsPrompt,
  buildEditPrompt,
  wrapWithJsonFormat,
  buildListingPrompt,
  SOURCE_TYPE_LABELS,
  PAGE_DESCRIPTIONS,
  // What each rung asks for, in a few words. Exported for /api/deck-types, so
  // the builder's own steps describe the deck in front of the seller instead
  // of hardcoding vocabulary's ladder; it is the same wording that goes in the
  // public listing (see ladderFact), not prompt text.
  FORM_PHRASES,
};
