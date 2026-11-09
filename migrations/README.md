# Migrations — AtrophyGym

Plain SQL. Run in order; each file states what it creates and whether it is
safe to re-run. Hostinger: open phpMyAdmin (hPanel → Databases), select the
app database, then use the SQL tab (or Import) per file below.

## 001_create_tables.sql

Creates the two tables (and indexes) of the app:

- `skills` — id, name (≤120 chars), delegate_desc (what AI does for you),
  cadence_days (1–90, default 7), archived (0/1), created_at.
- `reps` — id, skill_id (FK → skills.id), rating (1–5, CHECK constraint),
  note, logged_at (DATE).

**Safe to re-run:** yes — `CREATE TABLE IF NOT EXISTS` and
`CREATE INDEX IF NOT EXISTS` (MySQL 8.0.29+; on older MySQL the index line
may error with "duplicate index" on re-run, which is harmless — the index
already exists).

## Date/timezone convention (pinned)

All streak and due-date math runs on **server-local naive dates** (`PHP
date('Y-m-d')`). On Hostinger set PHP `date.timezone` (hPanel → PHP config)
to YOUR timezone, typically `Europe/Helsinki`, so "today" means your today.
`logged_at` stores a DATE (no time). A streak window is `2 × cadence_days`
per the scope lock; gaps beyond that break the chain.
