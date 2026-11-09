<?php
declare(strict_types=1);

/**
 * AtrophyGym — streak + due-state math (E1/E2 shared semantics, pinned by UX plan).
 * Pure functions: no DB access — callers pass rep date lists + cadence + today.
 * The SAME functions feed the dashboard and the skill page (no drift possible).
 */

/** Convert 'Y-m-d' to a Unix day number (integer). */
function to_day(string $ymd): int
{
    $t = strtotime($ymd);
    if ($t === false) throw new InvalidArgumentException("bad date: $ymd");
    return (int)floor($t / 86400);
}

/**
 * Per-skill current streak: count consecutive reps, walking BACKWARD from the
 * latest rep, while each gap between consecutive reps is <= 2× cadence days
 * (grace window = 2× cadence, per lock F3). A gap > 2× cadence breaks the chain.
 * Reps on the same day are collapsed (gap 0) and never break the chain.
 * 0 reps → 0.
 */
function streak_current(array $repDates /* Y-m-d strings, any order */, int $cadenceDays): int
{
    $n = count($repDates);
    if ($n === 0) return 0;
    $grace = 2 * max(1, $cadenceDays);
    $days = array_map('to_day', $repDates);
    sort($days);
    $streak = 1;
    for ($i = $n - 1; $i > 0; $i--) {
        $gap = $days[$i] - $days[$i - 1];
        if ($gap > $grace) break;
        $streak++;
    }
    return $streak;
}

/** Per-skill longest streak: max historical chain by the same rule. */
function streak_longest(array $repDates, int $cadenceDays): int
{
    $n = count($repDates);
    if ($n === 0) return 0;
    $grace = 2 * max(1, $cadenceDays);
    $days = array_map('to_day', $repDates);
    sort($days);
    $best = 1; $run = 1;
    for ($i = 1; $i < $n; $i++) {
        $gap = $days[$i] - $days[$i - 1];
        if ($gap > $grace) { $run = 1; } else { $run++; }
        if ($run > $best) $best = $run;
    }
    return $best;
}

/** Dashboard headline streak = MIN per-skill current streak (weakest muscle). 0 skills → 0. */
function global_streak(array $perSkillCurrentStreaks): int
{
    if (count($perSkillCurrentStreaks) === 0) return 0;
    return min($perSkillCurrentStreaks);
}

/**
 * Due state (F6, pinned):
 *   last_rep + cadence = due_date.
 *   today > due_date AND today > last_rep + 2×cadence → 'overdue'
 *   today >= due_date (within grace) → 'due'
 *   else → 'scheduled'
 *   0 reps → 'due' (first rep should happen now).
 * Returns ['state' => 'overdue'|'due'|'scheduled', 'due_date' => 'Y-m-d'|null, 'overdue_note' => bool].
 */
function due_state(?string $lastRep /* Y-m-d or null */, int $cadenceDays, string $today): array
{
    $cad = max(1, $cadenceDays);
    if ($lastRep === null) {
        return ['state' => 'due', 'due_date' => $today, 'overdue_note' => false];
    }
    $dueDate = date('Y-m-d', strtotime($lastRep . " +{$cad} days"));
    $graceEnd = date('Y-m-d', strtotime($lastRep . " +" . (2 * $cad) . " days"));
    if ($today > $dueDate && $today > $graceEnd) {
        return ['state' => 'overdue', 'due_date' => $dueDate, 'overdue_note' => true];
    }
    if ($today >= $dueDate) {
        return ['state' => 'due', 'due_date' => $dueDate, 'overdue_note' => false];
    }
    return ['state' => 'scheduled', 'due_date' => $dueDate, 'overdue_note' => false];
}

/** Order weight for dashboard: Overdue first, then Due, then Scheduled. */
const DUE_ORDER = ['overdue' => 0, 'due' => 1, 'scheduled' => 2];

/** Validation for cadence input; returns error string or null. */
function validate_cadence($raw): ?string
{
    if ($raw === null || $raw === '' || !is_numeric($raw) || (int)$raw < 1 || (int)$raw > 90) {
        return 'Cadence must be between 1 and 90 days.';
    }
    return null;
}

/** Validation for skill name; returns error string or null. */
function validate_name($raw): ?string
{
    if (!is_string($raw) || trim($raw) === '' || mb_strlen(trim($raw)) > 120) {
        return 'Give the skill a name.';
    }
    return null;
}
