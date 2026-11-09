# Migrations — AtrophyGym

Apply in order (phpMyAdmin "Import" or CLI `mysql -u user -p dbname < 001_create_tables.sql`).

## 001_create_tables.sql
Creates the `skills` and `reps` tables (FK reps→skills, rating CHECK 1..5).
Safe to re-run: yes (`CREATE TABLE IF NOT EXISTS`; note the CHECK constraint is
only enforced on first creation).

## Date/timezone choice
All dates are naive DATEs. "Today" is computed as the MySQL/PHP server date
in the server's configured timezone (UTC in the dev container; set PHP
date.timezone / MySQL time_zone on the host to match the single user's zone).
Streak and due-date math use this same "today" everywhere (lib/gym.php).
