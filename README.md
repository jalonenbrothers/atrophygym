# AtrophyGym

A private, single-user gym for skills you've offloaded to AI: inventory
them, log no-AI practice reps with a confidence self-rating, and watch your
streaks and confidence curves instead of losing the skill silently.

MVP Lab Sprint 6 (MVP-7). LAMP stack: PHP 8+ (no frameworks), MySQL via PDO,
vanilla HTML/CSS — no build steps, no JS required, no external APIs ($0).

## Deploy (Hostinger subdomain)
1. Clone/upload repo to the subdomain's web root
2. cp lib/config.example.php lib/config.php — fill db creds from hPanel
3. Run migrations/ per migrations/README.md
4. Done — no build steps

## Endpoints (pinned by the UX plan, E1–E7)

| Endpoint | Purpose |
|---|---|
| GET `index.php` (optional `?archived=1`) | E1 Dashboard: streak strip, skill cards ordered Overdue → Due → Scheduled |
| GET `skill.php?id=N` | E2 Skill detail: header, log form, SVG curve, history (last 50); 404 if unknown |
| POST `skill.php?id=N&action=log` | E3 Log a rep (`rating` 1–5 required, `note` ≤2000 optional) → 303 + flash |
| POST `skill-form.php` | E4 Add skill (`name` ≤120 required, `delegate_desc` optional, `cadence_days` 1–90 default 7) |
| POST `skill-form.php?id=N` | E5 Edit skill (same fields) |
| POST `skill-form.php?id=N&action=archive` | E6 Two-step archive (`confirm=0` → re-render "Really archive? Tap again"; `confirm=1` → archive) |
| POST `skill.php?id=N&action=unarchive` | E7 Restore archived skill |

Every success is a 303 redirect + session flash; validation errors re-render
with inline field errors (200); unknown ids → 404. No JSON endpoints, no
fetch() — plain HTML forms; the server renders every number and the SVG curve.

## Tests

`php tests/run.php` — unit tests for streak/due/validation math and the SVG
curve (no DB needed).

## API usage / costs

None. Zero external APIs, $0.
