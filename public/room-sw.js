/* Keeps a room playable when the site, or the school's internet, drops.
 *
 * A teacher opens a room once to check it before class; from then on that
 * device holds a copy of the game and of that room, and a dead connection or an
 * outage here mid-lesson costs nothing. It is a fallback, never a second source
 * of truth: while the site answers, every room page and every payload is exactly
 * what the site serves, so a republished room or a new release reaches a
 * classroom the way it would with no worker at all.
 *
 * It lives at the site root rather than in public/game because a worker can only
 * look after pages at or below its own folder, and rooms are under /j. The other
 * way, a Service-Worker-Allowed header, would tie this to the web server's
 * configuration. Nothing may go in public/j (see RoomController::show).
 *
 * Only room pages, room payloads and the /game files are touched; every other
 * request goes to the network as if this did not exist. Browsers only run
 * workers on https and localhost, so the http Laragon test site never has one.
 *
 * To retire it, replace this file with one that unregisters itself. Deleting it
 * is not enough: a device that cannot fetch the new version keeps the old one.
 */
'use strict';

// Changing the name drops everything saved under the old one (see activate).
const CACHE = 'scp-rooms-v1';

// Every room is the same shell, so it is saved once, under a key no room code
// can take (codes are letters and digits), and handed to any room opened offline.
const SHELL_KEY = '/j/_shell';

/* How long a room page or payload may go unanswered before the saved copy is
 * used. Wi-Fi that is connected but has no internet — the usual school failure —
 * does not fail a request, it leaves it hanging for a minute or more, and a class
 * watching "Cargando…" that long has moved on. Only applies when there IS a saved
 * copy; without one, the request waits exactly as long as it would anyway.
 */
const NETWORK_TIMEOUT_MS = 5000;

/* Once the network has failed or timed out, saved copies are served at once for
 * this long rather than each waiting out the timeout again: the page and its
 * payload are two requests, and a class should sit through one wait, not two.
 * The network is still tried behind every one of them, and the first answer
 * that gets through ends it. Kept in memory only, so a restarted worker merely
 * waits once more.
 */
const DOWN_GRACE_MS = 30000;
let networkDownUntil = 0;

const ROOM_PAGE = /^\/j(\/[A-Za-z0-9]{4,8})?\/?$/;
const PAYLOAD = /^\/j\/rooms\/[A-Za-z0-9]{4,8}\.json$/;
const ASSET = /^\/game\/[\w.-]+\.(?:js|css)$/;

// Safe to take over at once: a running game makes no requests after its payload
// has loaded, so there is nothing in flight for a new worker to disturb.
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    for (const name of await caches.keys()) {
      if (name.startsWith('scp-rooms-') && name !== CACHE) await caches.delete(name);
    }
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate' && ROOM_PAGE.test(url.pathname)) {
    event.respondWith(networkFirst(event, SHELL_KEY));
  } else if (PAYLOAD.test(url.pathname)) {
    event.respondWith(networkFirst(event, request.url));
  } else if (isVersionedAsset(url)) {
    event.respondWith(cacheFirst(event));
  }
});

/* A first visit happens before this worker exists, so nothing that page loaded
 * came through here. app.js sends the list once the worker is up, and each is
 * fetched again — mostly from the browser's own cache — and saved.
 */
self.addEventListener('message', event => {
  const { type, urls } = event.data || {};
  if (type !== 'keep' || !Array.isArray(urls)) return;

  const jobs = [keep('/j', SHELL_KEY)];
  for (const href of urls) {
    const url = new URL(href, self.location.href);
    if (url.origin !== self.location.origin) continue;
    if (PAYLOAD.test(url.pathname) || isVersionedAsset(url)) jobs.push(keep(url.href, url.href));
  }
  event.waitUntil(Promise.all(jobs));
});

/* The site's answer whenever it gives one; the saved copy only when it gives
 * none (offline, an outage, a 5xx) or is too slow. A 404 is an answer: a
 * mistyped code has to say so, not open whatever was saved last.
 */
async function networkFirst(event, key) {
  const network = fetch(event.request);
  // Attached before anything reads the body, so the clone is taken in time.
  event.waitUntil(network.then(res => {
    if (res.status < 500) networkDownUntil = 0;
    return save(key, res.clone());
  }).catch(() => {}));

  const saved = await (await caches.open(CACHE)).match(key);
  if (!saved) return network;
  if (Date.now() < networkDownUntil) return saved;

  const fallBack = () => {
    networkDownUntil = Date.now() + DOWN_GRACE_MS;
    return saved;
  };
  let timer;
  return Promise.race([
    network.then(res => {
      clearTimeout(timer);
      return res.status >= 500 ? fallBack() : res;
    }),
    new Promise(resolve => { timer = setTimeout(resolve, NETWORK_TIMEOUT_MS); }).then(fallBack),
  ]).catch(() => {
    clearTimeout(timer);
    return fallBack();
  });
}

/* Cache first, because a versioned /game URL never changes: RoomController
 * stamps each with its file's modified time, so a release arrives as new URLs
 * and can never be answered from here. What this buys offline is that a saved
 * shell loads the very files it was saved with — never last week's ui.js beside
 * this week's bingo.js, which is the mismatch the stamps exist to prevent.
 */
async function cacheFirst(event) {
  const saved = await (await caches.open(CACHE)).match(event.request.url);
  if (saved) return saved;

  const res = await fetch(event.request);
  event.waitUntil(save(event.request.url, res.clone()));
  return res;
}

function isVersionedAsset(url) {
  return ASSET.test(url.pathname) && url.searchParams.has('v');
}

async function keep(url, key) {
  try {
    await save(key, await fetch(url));
  } catch {
    // Offline already; the next visit that gets through will save it.
  }
}

/* Each save of a /game file drops that file's older versions, so a device holds
 * one copy of the game however many releases it has seen.
 */
async function save(key, res) {
  if (!res.ok || res.redirected) return;
  const cache = await caches.open(CACHE);
  const url = new URL(key, self.location.href);
  if (ASSET.test(url.pathname)) {
    for (const old of await cache.keys()) {
      const oldUrl = new URL(old.url);
      if (oldUrl.pathname === url.pathname && oldUrl.href !== url.href) await cache.delete(old);
    }
  }
  await cache.put(key, res);
}
