<?php
declare(strict_types=1);

/* Shared streak / due-date semantics — per UX plan §5 E1. The SAME
 * functions feed the dashboard and the skill page (no drift possible). */

const RATING_LABELS = [1 => 'Fumbling', 2 => 'Shaky', 3 => 'Okay', 4 => 'Solid', 5 => 'Fluent'];

function today(): string
{
    return date('Y-m-d');
}

/** @return array<int,array{id:int,logged_at:string,rating:int,note:?string}> ascending */
function repsForSkill(int $skillId): array
{
    $st = db()->prepare('SELECT id, logged_at, rating, note FROM reps WHERE skill_id = ? ORDER BY logged_at ASC, id ASC');
    $st->execute([$skillId]);
    return $st->fetchAll();
}

/**
 * Current streak: consecutive reps (counting back from the latest) where
 * the gap to the previous rep is <= 2 x cadence (grace window). Only the
 * latest rep per date counts; multiple reps on one date count as one.
 */
function streakForSkill(array $reps, int $cadenceDays): array // [current, longest]
{
    // one rep per date (latest rating that date)
    $byDate = [];
    foreach ($reps as $r) {
        $byDate[$r['logged_at']] = $r;
    }
    $dates = array_keys($byDate); // ascending
    if ($dates === []) {
        return [0, 0];
    }
    $grace = max(1, 2 * $cadenceDays);

    // longest chain
    $longest = 1;
    $run = 1;
    for ($i = 1; $i < count($dates); $i++) {
        $gap = daysBetween($dates[$i - 1], $dates[$i]);
        $run = ($gap <= $grace) ? $run + 1 : 1;
        if ($run > $longest) {
            $longest = $run;
        }
    }

    // current chain: count back from the latest rep; also the latest rep
    // must still be within its grace window vs today, else streak is broken
    $last = $dates[count($dates) - 1];
    $current = (daysBetween($last, today()) > $grace) ? 0 : 1;
    if ($current > 0) {
        for ($i = count($dates) - 1; $i > 0; $i--) {
            if (daysBetween($dates[$i - 1], $dates[$i]) <= $grace) {
                $current++;
            } else {
                break;
            }
        }
    }
    return [$current, max($longest, $current)];
}

function daysBetween(string $a, string $b): int
{
    $ta = new DateTimeImmutable($a);
    $tb = new DateTimeImmutable($b);
    return (int)$tb->diff($ta)->format('%a');
}

/**
 * Due state per plan: due_date = last_rep + cadence.
 * Overdue if today > due_date AND today > last_rep + 2xcadence;
 * Due if today >= due_date (within grace); else Scheduled.
 * A skill with 0 reps created today -> Due.
 * @return array{state:'overdue'|'due'|'scheduled', due_date:?string}
 */
function dueState(?string $lastRepDate, int $cadenceDays): array
{
    $today = today();
    if ($lastRepDate === null) {
        return ['state' => 'due', 'due_date' => $today];
    }
    $due = (new DateTimeImmutable($lastRepDate))->modify("+{$cadenceDays} days")->format('Y-m-d');
    $graceEnd = (new DateTimeImmutable($lastRepDate))->modify('+' . (2 * $cadenceDays) . ' days')->format('Y-m-d');
    if ($today > $due && $today > $graceEnd) {
        return ['state' => 'overdue', 'due_date' => $due];
    }
    if ($today >= $due) {
        return ['state' => 'due', 'due_date' => $due];
    }
    return ['state' => 'scheduled', 'due_date' => $due];
}

/** Dashboard headline streak = MIN per-skill current streak among non-archived skills. */
function globalStreak(): array // [current(min), best(max of per-skill longest? see below)]
{
    $skills = db()->query('SELECT id, cadence_days FROM skills WHERE archived = 0')->fetchAll();
    if ($skills === []) {
        return [0, 0];
    }
    $minCur = null;
    $best = 0;
    foreach ($skills as $s) {
        [$cur, $long] = streakForSkill(repsForSkill((int)$s['id']), (int)$s['cadence_days']);
        $minCur = ($minCur === null) ? $cur : min($minCur, $cur);
        $best = max($best, $long);
    }
    return [$minCur, $best];
}

function dueRank(string $state): int
{
    return match ($state) { 'overdue' => 0, 'due' => 1, default => 2 };
}

/** "Nov 16" style date label. */
function shortDate(string $ymd): string
{
    return (new DateTimeImmutable($ymd))->format('M j');
}

/** "Nov 9, 2026" style date label. */
function longDate(string $ymd): string
{
    return (new DateTimeImmutable($ymd))->format('M j, Y');
}

function badgeHtml(string $state, ?string $dueDate): string
{
    return match ($state) {
        'overdue' => '<span class="badge badge-overdue">Overdue — practice today</span>',
        'due' => '<span class="badge badge-due">Due</span>',
        default => '<span class="badge badge-scheduled">Scheduled · next rep ' . htmlspecialchars(shortDate($dueDate ?? today())) . '</span>',
    };
}
