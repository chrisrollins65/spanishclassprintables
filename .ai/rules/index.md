# Rules

Committed, area-grouped rules: settled decisions, non-obvious traps, standing
constraints. Read every file whose globs cover the paths you are about to
touch, and `grep -rin '<keyword>' .ai/rules` for what a path match alone misses.

| Rules | Globs |
| --- | --- |
| [repeated-presses.md](repeated-presses.md) | `app/Http/Controllers/**`, `resources/views/**`, `public/site/**`, `public/game/**` |
