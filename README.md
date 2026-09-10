# Spanish Class Printables

This application powers the online side of Spanish Class Printables: printable Spanish class packets whose worksheets
carry a room code and QR code. Scanning the code opens a classroom game room (Jeopardy, Bingo) built from that
packet's content.

Built on [Laravel](https://laravel.com/), with plain HTML and JavaScript for the game pages.

## Environment files

Encrypted environment files are included in this repo. You will need a special key to decrypt them.

```bash
php artisan env:decrypt --key=DECRYPT_KEY_HERE
php artisan env:decrypt --env=development --key=DECRYPT_KEY_HERE
php artisan env:decrypt --env=production --key=DECRYPT_KEY_HERE
```

If you ever change variables in those files, remember to re-encrypt them. With `ENCRYPTION_KEY` set in your `.env`, one
command re-encrypts every environment file that exists:

```bash
php artisan env:encrypt-all
```

Layout of environments:

- `.env` - default environment, for local use without docker or virtual machines or anything
- `.env.development` - for use locally at `spanishclassprintables.local`
- `.env.production` - for use in production at `spanishclassprintables.com`

## How rooms work

A room is one published packet, keyed by the short code printed on its worksheets (e.g. `/j/DEMO1`).

- **Publishing** - the local packet builder pushes a room to `POST /api/internal/room`, authenticated with the
  `X-Room-Secret` header. It must match `ROOM_PUBLISH_SECRET`; publishing is refused outright when that is empty.
  Publishing the same code again replaces the room.
- **Playing** - `/j/{code}` serves the static shell at [public/game/room.html](public/game/room.html), which fetches its
  data from `/j/rooms/{code}.json`. Everything after that runs in the browser with no session or account.

See [RoomController.php](app/Http/Controllers/RoomController.php) for the details.

## Code design

Most of the code here is based on how things are done in Laravel. But here are some design considerations to keep in
mind.

### Rooms are never deleted

A teacher may print a packet today and hand it out years from now, and a dead link on a paid product is a refund and a
bad review. So there is deliberately no way to delete a room anywhere in the application - please keep it that way.

For the same reason, room payloads live in the database (which is backed up) and never under `public/`, which each
release replaces.

### Nothing may exist on disk at `public/j`

The game shell and its assets live in `public/game`, not `public/j`. A real `public/j` directory would be resolved by
the web server before Laravel, and every room URL would 404. Asset paths in the game pages are absolute (`/game/...`)
so a trailing slash on a room URL cannot break them.

### Automated Tests

Tests run against an in-memory SQLite database (see [phpunit.xml](phpunit.xml)), so they will not touch your local
database.

```bash
php artisan test --compact
```

Please write tests for any new code you add.
