/* GENERATED — do not edit here.
 *
 * Copied from the packet builder's src/rng.js (commit 74c9a5a) by its
 * scripts/sync-site-shared.js. Edit it there and run that script again; an
 * edit made here is lost the next time anyone does.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.SharedRng = factory();
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';
  var module = { exports: {} };

// Deterministic randomness for the packet build.
//
// Clipart is placed by eye on the page previews, so the Generate that follows
// has to lay the pages out exactly the way those previews did - same words in
// the same order, same word-search grid, same secret-message sentences.
// Every generator therefore draws from a seeded stream instead of Math.random,
// and the seed travels with the worksheet so a saved packet with saved
// artwork still rebuilds page for page.

// Short and URL-safe; it ends up in the worksheet JSON and the CSV.
function makeSeed() {
  return Math.random().toString(36).slice(2, 12);
}

// FNV-1a over the seed string, then mulberry32. Tiny, dependency-free, and
// stable across Node versions - unlike anything built on Math.random.
function makeRng(seed) {
  const text = String(seed);
  let h = 2166136261 >>> 0;
  for (let i = 0; i < text.length; i++) {
    h ^= text.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  let state = h >>> 0;
  return function rng() {
    state = (state + 0x6d2b79f5) | 0;
    let t = Math.imul(state ^ (state >>> 15), 1 | state);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

// Fisher-Yates on a copy, so callers keep their input intact.
function shuffle(list, rng) {
  const out = [...list];
  for (let i = out.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    [out[i], out[j]] = [out[j], out[i]];
  }
  return out;
}

module.exports = { makeSeed, makeRng, shuffle };

  return module.exports;
}));
