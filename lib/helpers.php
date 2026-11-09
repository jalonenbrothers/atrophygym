<?php
declare(strict_types=1);

/**
 * AtrophyGym — shared request/session helpers.
 * Session is used ONLY for flash messages (no login — single-user app, OUT list).
 */

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $msg, string $kind = 'success'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'] = ['msg' => $msg, 'kind' => $kind];
}

/** Returns [html, kind] flash markup (escaped) and clears it. Empty string if none. */
function flash_get(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['flash'])) return '';
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $cls = $f['kind'] === 'error' ? 'flash flash-error' : 'flash flash-success';
    return '<div class="' . $cls . '">' . h($f['msg']) . '</div>';
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

/** Fetch one skill row by id or null. */
function skill_find(int $id): ?array
{
    $st = db()->prepare('SELECT id, name, delegate_desc, cadence_days, archived, created_at FROM skills WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

/** Chronological rep dates for a skill (Y-m-d strings). */
function skill_rep_dates(int $skillId): array
{
    $st = db()->prepare('SELECT logged_at FROM reps WHERE skill_id = ? ORDER BY logged_at ASC, id ASC');
    $st->execute([$skillId]);
    return array_map(fn($r) => $r['logged_at'], $st->fetchAll());
}

/** Chronological ratings for a skill. */
function skill_ratings(int $skillId): array
{
    $st = db()->prepare('SELECT rating FROM reps WHERE skill_id = ? ORDER BY logged_at ASC, id ASC');
    $st->execute([$skillId]);
    return array_map(fn($r) => (int)$r['rating'], $st->fetchAll());
}

/** Last N reps reverse-chron for history (F5). */
function skill_history(int $skillId, int $limit = 50): array
{
    $st = db()->prepare('SELECT logged_at, rating, note FROM reps WHERE skill_id = ? ORDER BY logged_at DESC, id DESC LIMIT ' . (int)$limit);
    $st->execute([$skillId]);
    return $st->fetchAll();
}

function rating_label(int $r): string
{
    $labels = [1 => 'Fumbling', 2 => 'Shaky', 3 => 'Okay', 4 => 'Solid', 5 => 'Fluent'];
    return $labels[$r] ?? (string)$r;
}
