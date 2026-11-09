# DEPLOY.md — AtrophyGym on Hostinger

Target subdomain: **myapp6mvp.earth-turtle.com**
Model: repo root = web root. Git push = deploy (operator's auto-deploy).
No build steps, no containers, no Composer — plain PHP 8+ on shared
hosting, per the MVP Lab stack standard.

Migration below was verified locally on MySQL 8 (fresh apply and
idempotent re-run); the Hostinger steps are the operator's to run.

## 1. Create the subdomain (hPanel)

1. hPanel → **Websites → Dashboard → Subdomains** (or Domains →
   Subdomains, depending on panel version).
2. Subdomain: `myapp6mvp` — domain: `earth-turtle.com`.
3. Note the document root hPanel creates, typically
   `/home/uXXXXX/domains/myapp6mvp.earth-turtle.com/public_html`
   — this is where the repo files must end up (hPanel's Git deploy
   feature can target it directly; if the auto-deploy watches the
   parent `domains/<sub>/` dir, the repo root maps onto
   `public_html`).
4. Free SSL: hPanel → Security → SSL. Install and force HTTPS.

## 2. PHP version & settings

1. hPanel → Websites → myapp6mvp.earth-turtle.com → **PHP
   Configuration**: select **PHP 8.2 or 8.3** (anything 8.1+ works;
   8.3 recommended, matches the tested dev container).
2. The app needs only the bundled `pdo_mysql` extension — enabled by
   default. Nothing else to toggle.
3. Set PHP `date.timezone` (and ideally MySQL time_zone) to the
   single user's timezone, e.g. Europe/Helsinki — the app computes
   "today", streaks and due dates from the server date. (Dev used
   UTC; any consistent zone is fine as long as it matches the user's.)

## 3. MySQL database

1. hPanel → **Databases → MySQL Databases** → create:
   - Database: e.g. `uXXXXX_atrophygym`
   - User: e.g. `uXXXXX_atrophygym` (hPanel prefixes both)
   - Password: generate & save it
2. Note host `localhost` (Hostinger default for PHP on the same
   server), port 3306.

## 4. Deploy the code

Repo: `~/projects/atrophygym`, master branch. Either use the
operator's git auto-deploy pointing at the subdomain's public_html,
or upload the repo (all files at repo root = web root):

```
index.php  skill.php  skill-form.php  .htaccess
lib/  migrations/  assets/  tests/
```

Nothing is excluded — `.htaccess` already 404s `/lib/`, `/tests/`,
`/migrations/` over HTTP. `lib/config.php` never exists in the repo
(it's gitignored), so create it on the server (step 5).

## 5. Configure on the server

In the web root, create `lib/config.php` (copy of
`lib/config.example.php`) — via hPanel File Manager:

```php
<?php
declare(strict_types=1);

return [
    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'uXXXXX_atrophygym',   // from step 3
        'user'    => 'uXXXXX_atrophygym',
        'pass'    => '<the password you saved>',
    ],
    'debug' => false,   // production: never true
];
```

This is the only secret in the app. No external API keys, $0 cost.

## 6. Apply migrations (operator, in Hostinger)

phpMyAdmin (hPanel → Databases → phpMyAdmin for the new database):

1. Select the `uXXXXX_atrophygym` database in the left sidebar.
2. **Import** tab → choose `migrations/001_create_tables.sql` from
   the deployed repo (or paste its contents into the SQL tab) → Go.
3. Creates `skills` + `reps` with FK, rating CHECK 1–5, and
   `idx_reps_skill`. Safe to re-run (verified locally on MySQL 8:
   second run reports "index exists, skipping"), so a double-import
   is harmless.

CLI alternative (if SSH is enabled):
`mysql -u uXXXXX_atrophygym -p uXXXXX_atrophygym < migrations/001_create_tables.sql`

## 7. Post-deploy smoke test (run in a browser)

1. `https://myapp6mvp.earth-turtle.com/` loads — "What have you
   delegated to AI?" empty state with "Add your first skill".
2. Add a skill (e.g. "Writing first drafts", cadence 7) → redirects
   to the skill page; "Current streak 0".
3. Log a rep with rating 3 → rep appears in history, curve shows one
   point, streak 1.
4. Dashboard shows the skill under "Scheduled".
5. Edit the skill (skill-form.php) → save works.
6. Archive: two-step inline confirm on the edit page → skill moves
   to `/?archived=1`; archived view is read-only (no log-rep form);
   unarchive works.
7. `https://myapp6mvp.earth-turtle.com/skill.php?id=9999` → 404.
8. `https://myapp6mvp.earth-turtle.com/lib/db.php` and
   `/migrations/001_create_tables.sql` → 404 (htaccess blocking
   works on Hostinger's Apache/LiteSpeed — RedirectMatch is
   supported on both).
9. No PHP warnings/errors anywhere in the HTML (View Source) —
   debug is false.
10. HTTPS: padlock OK, http:// redirects to https://.

## Cron jobs

None needed. The app is stateless per-request; streaks are computed
live from the reps table on every page load.

## Rollback

Auto-deploy keeps history via git: revert to the previous commit and
push. The schema (001) is additive-only, so old code runs fine on
the new schema.
