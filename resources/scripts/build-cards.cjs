/* Builds a game's bingo cards, from the builder's own card code.
 *
 * The cards are made by public/game/shared/bingoCards.js — the packet
 * builder's file, synced here — and this is how PHP reaches it. Porting the
 * algorithm to PHP would mean two ways of dealing a card, and a set printed by
 * one would not match a set the editor deals with the other.
 *
 * Reads {"gameId": "...", "faces": [...], "sets": [...]} on stdin and writes
 * the cards on stdout.
 *
 * .cjs because this package is "type": "module", and the shared files are
 * plain scripts that hang themselves on a global.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const shared = path.join(__dirname, '..', '..', 'public', 'game', 'shared');

const context = vm.createContext({ console });
context.self = context;
for (const file of ['rng.js', 'bingoCards.js']) {
  vm.runInContext(fs.readFileSync(path.join(shared, file), 'utf8'), context);
}

const request = JSON.parse(fs.readFileSync(0, 'utf8'));

const cards = context.SharedBingoCards.generateCardSets({
  faces: request.faces,
  sets: request.sets,
  // Seeded by the game's id, so the same words always deal the same cards: a
  // teacher who prints set B in March must be able to run it against the set
  // they printed in January.
  rng: context.SharedRng.makeRng(request.gameId),
});

process.stdout.write(JSON.stringify(cards));
