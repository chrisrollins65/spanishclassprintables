/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/shared/wordsPrompt.js (commit 74c9a5a) by its
 * scripts/sync-site-shared.js. Edit it there and run that script again; an
 * edit made here is lost the next time anyone does.
 */
// The vocabulary-generation prompt, in one place.
//
// It used to exist twice: src/ai/prompts.js built the real one, and
// src/public/app.js built a copy for the editable preview on step 1. They
// drifted - the preview told the model that a song's words had to be single
// words and the server's copy never did, so a song pack could come back with
// "la guitarra" and the puzzle grids laid it out as LAGUITARRA. Both sides now
// load this file: the server wraps it with the JSON schema, the browser shows
// the same text in the preview box.

(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.WordsPrompt = factory();
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var SOURCE_TYPE_LABELS = {
    general: 'topic',
    song: 'song',
    movie: 'movie',
    tv: 'TV series',
    book: 'book',
    game: 'video game',
  };

  var AUDIENCE = 'children ages 8-13 (grades 3-7)';

  // What the puzzle pages need from every word, whatever the source. These are
  // constraints, not preferences: normalizeWord() in wordSearch.js strips
  // everything but letters before the grids are built, and the crossword clue
  // is the bare English word - there are no sentence clues and no definitions
  // anywhere in the packet.
  var WORD_RULES = [
    '- ONE Spanish word per entry, with NO article: "helado", never "el helado". The grids strip spaces, so an article is laid out as part of the word (ELHELADO) and the puzzle is wrong.',
    '- 4-15 letters. Shorter than 4 is hard to hide in a word search. Long words are welcome - "espantapajaros" is a good entry, not a problem - but past 15 the word search grid has to widen to hold them.',
    '- Mostly concrete nouns. A few high-frequency verbs or adjectives are fine, but both puzzles work best with nouns.',
    '- Write proper accents ("jardín"), but do not include two words that collide once accents are stripped and the case is flattened - the word search grid is unaccented uppercase.',
    '- The English side is the ENTIRE crossword clue, so it has to identify its own Spanish word with nothing else to go on: "drum" works, "instrument" does not. Never use the same English word for two entries.',
    '- Appropriate for ' + AUDIENCE + ', with a mix of easy and moderate difficulty.',
  ].join('\n');

  function buildCreativeWordsPrompt(topic, count, sourceType, lyrics) {
    count = count || 20;
    sourceType = sourceType || 'general';
    var intro = 'You are a Spanish language teacher creating printable vocabulary puzzle worksheets for ' + AUDIENCE + '.';

    if (sourceType === 'song') {
      var lyricsSection = '';
      if (lyrics && lyrics.es) lyricsSection += '\nSpanish lyrics:\n' + lyrics.es + '\n';
      if (lyrics && lyrics.en) lyricsSection += '\nEnglish translation:\n' + lyrics.en + '\n';

      var songExtras = '';
      if (!lyrics || !lyrics.es) songExtras += '\n- If Spanish lyrics are not provided, use your knowledge of the song to identify key vocabulary';
      if (!lyrics || !lyrics.en) songExtras += '\n- If English translations are not provided, determine the English meaning from context';

      return intro + '\n\nThe student is learning vocabulary from the song: "' + topic + '"\n' + lyricsSection +
        '\nGenerate a list of ' + count + ' Spanish vocabulary words from this song.\n\nRequirements:\n' +
        '- Take the words from the lyrics wherever you can. A lyric gives you the word in context, so it is the best source - but when a word from the lyrics cannot meet the rules below, pick a different word from the song rather than bending them.\n' +
        WORD_RULES + '\n- Include a proper title for this song' + songExtras +
        '\n\nReturn exactly ' + count + ' words.';
    }

    if (['movie', 'tv', 'book', 'game'].indexOf(sourceType) !== -1) {
      var typeLabel = SOURCE_TYPE_LABELS[sourceType];
      return intro + '\n\nGenerate a list of ' + count + ' Spanish vocabulary words related to the ' +
        typeLabel + ': "' + topic + '"\n\nRequirements:\n' +
        '- Words should be relevant to the story, characters, settings, or themes of "' + topic + '"\n' +
        WORD_RULES + '\n- Include a proper Spanish theme/title' +
        '\n\nReturn exactly ' + count + ' words.';
    }

    return intro + '\n\nGenerate a list of ' + count + ' Spanish vocabulary words related to the topic: "' +
      topic + '"\n\nRequirements:\n' + WORD_RULES +
      '\n- Include a proper Spanish theme/title for the topic\n\n' +
      'Example for the topic "Spring":\n' +
      '{\n' +
      '  "theme": "La Primavera",\n' +
      '  "words": [\n' +
      '    { "en": "Flower", "es": "Flor" },\n' +
      '    { "en": "Garden", "es": "Jardín" },\n' +
      '    { "en": "Rain", "es": "Lluvia" },\n' +
      '    { "en": "Butterfly", "es": "Mariposa" }\n' +
      '  ]\n' +
      '}\n\n' +
      'Return exactly ' + count + ' words.';
  }

  return { buildCreativeWordsPrompt: buildCreativeWordsPrompt, SOURCE_TYPE_LABELS: SOURCE_TYPE_LABELS, WORD_RULES: WORD_RULES };
}));
