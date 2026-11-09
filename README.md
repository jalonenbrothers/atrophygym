# AtrophyGym

A private, single-user gym for skills you've offloaded to AI — inventory
them, log no-AI practice reps with a confidence self-rating, and watch
your streaks and confidence curves instead of losing the skill silently.

MVP Lab Sprint 6 (MVP-7). Stack: PHP 8+ (no frameworks), MySQL via PDO,
vanilla HTML5/CSS — no build steps, no JS required.

## Pages
- `index.php` — Dashboard: streak strip, skill cards ordered
  Overdue > Due > Scheduled, archived section via `?archived=1`
- `skill.php?id=N` — Skill detail: log a rep, confidence curve (SVG,
  server-rendered), rep history (last 50)
- `skill-form.php` (`?id=N` to edit) — add/edit skill; archive lives here
  (two-step inline confirm, no JS dialogs)

## Deploy (Hostinger subdomain)
1. Clone/upload repo to the subdomain's web root
2. cp lib/config.example.php lib/config.php — fill db creds from hPanel
3. Run migrations/ per migrations/README.md
4. Done — no build steps

## API usage / costs

None. Zero external APIs, $0.

## Tests

- `php tests/run.php` — unit tests (streak/due/validation math, no DB
  needed). 26 passed.
- `tests/e2e.sh` — end-to-end vs real Apache + MySQL 8 (requires the dev
  containers at 127.0.0.1:18090 and an empty skills table; run from repo
  root with bash). Covers E1–E7, 404s, XSS escaping, internal-path 404s,
  CHECK constraint, two-step archive. 69 checks passed.

## Dev container
```
docker run -d --name ag-web -p 18090:80 -v $PWD:/var/www/html php:8.3-apache
docker run -d --name ag-mysql -e MYSQL_DATABASE=atrophygym \
  -e MYSQL_USER=atrophygym -e MYSQL_PASSWORD=dev -e MYSQL_ROOT_PASSWORD=root mysql:8
```
Point lib/config.php's db host at the MySQL container (e.g. host.docker.internal
or the compose network), then apply migrations/001_create_tables.sql.
