# RUN.md — AtrophyGym local development

Every command in this file was executed and verified on 2026-11-09
against master `625f83b` (results noted per step).

## Requirements

- Docker — the app runs in `php:8.3-apache` and `mysql:8` containers.
  No host PHP, no Composer, no build steps: plain PHP 8+ with zero
  dependencies outside the bundled PDO extension.
- bash + curl — for the e2e suite (standard on Linux/macOS).

## 1. Start the dev stack

From the repo root:

```
docker run -d --name ag-web -p 18090:80 -v $PWD:/var/www/html php:8.3-apache
docker run -d --name ag-mysql -e MYSQL_DATABASE=atrophygym \
  -e MYSQL_USER=atrophygym -e MYSQL_PASSWORD=dev \
  -e MYSQL_ROOT_PASSWORD=root mysql:8
```

Both containers land on the default bridge network, so the web
container reaches MySQL at hostname `ag-mysql` (verified: PDO connect
from inside ag-web via host `ag-mysql` succeeded).

## 2. Configure

```
cp lib/config.example.php lib/config.php
```

Edit `lib/config.php`:

```php
<?php
declare(strict_types=1);

return [
    'db' => [
        'host'    => 'ag-mysql',   // container hostname, seen from ag-web
        'port'    => 3306,
        'name'    => 'atrophygym',
        'user'    => 'atrophygym',
        'pass'    => 'dev',
    ],
    'debug' => true,   // show DB errors while developing
];
```

`lib/config.php` is gitignored; `lib/config.example.php` is the
committed template. If config.php is missing, every page shows a clear
"Setup required" message instead of a stack trace.

## 3. Apply migrations

```
docker exec -i ag-mysql mysql -uatrophygym -pdev atrophygym \
  < migrations/001_create_tables.sql
```

Verified: applies cleanly on a fresh database, and re-running is safe
(second run prints `index exists, skipping`). Creates `skills` and
`reps` (FK reps→skills, rating CHECK 1–5, idx_reps_skill). Full
operator instructions, including phpMyAdmin, are in
migrations/README.md.

## 4. Run

Open http://127.0.0.1:18090 — the dashboard. Click "Add your first
skill", then open the skill page to log no-AI reps with a 1–5
confidence rating.

Pages:
- `index.php` — dashboard (Overdue > Due > Scheduled; archived skills
  via `?archived=1`)
- `skill.php?id=N` — detail: log a rep, server-rendered SVG confidence
  curve, last-50 rep history
- `skill-form.php` (`?id=N` to edit) — add/edit skill; archive lives
  here (two-step inline confirm, no JS dialogs)

## 5. Tests

```
# unit tests (pure functions, no DB) — run inside the container
# (host has no php CLI):
docker exec ag-web php /var/www/html/tests/run.php
# → OK: 26 passed, 0 failed   (verified 2026-11-09)

# e2e suite — requires the dev stack running and EMPTY tables.
# Reset first:
docker exec ag-mysql mysql -uatrophygym -pdev atrophygym \
  -e "DELETE FROM reps WHERE id>0; DELETE FROM skills WHERE id>0"
bash tests/e2e.sh
# → E2E RESULT: 69 passed, 0 failed   (verified 2026-11-09)
```

The e2e suite hits http://127.0.0.1:18090 and covers E1–E7, 404s, XSS
escaping, internal-path 404s via .htaccess, the CHECK constraint, and
the two-step archive.

## 6. Teardown

```
docker rm -f ag-web ag-mysql
```

MySQL data lives in an anonymous volume; removing the container and
re-running the `docker run` above gives a blank database — re-apply
migrations (step 3) and you're back up.

## Environment / secrets / costs

- No external APIs, no keys, $0 cost.
- The only secret is `lib/config.php` (gitignored).
- `debug: true` displays DB errors on screen — dev only, never in
  production (the default in config.example.php is `false`).
