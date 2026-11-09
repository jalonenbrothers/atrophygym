<?php
declare(strict_types=1);
/**
 * AtrophyGym unit tests — lab-blog tests/run.php shape.
 * Pure functions only (no DB): streak/due/validation math + SVG curve.
 * Run: php tests/run.php  → "X passed" / non-zero exit on failure.
 */
require __DIR__ . '/../src/Streak.php';
require __DIR__ . '/../src/Curve.php';

$pass = 0; $fail = 0;
function t(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; }
    else { $fail++; echo "FAIL: $label\n"; }
}

// --- streak_current: gaps, grace window 2x cadence ---
t('empty reps → 0', streak_current([], 7) === 0);
t('one rep → 1', streak_current(['2026-11-01'], 7) === 1);
t('3 consecutive weekly reps → 3', streak_current(['2026-10-20', '2026-10-27', '2026-11-03'], 7) === 3);
t('gap 8d within 14d grace → 2', streak_current(['2026-10-27', '2026-11-04'], 7) === 2);
t('gap 14d exactly at grace → 2', streak_current(['2026-10-20', '2026-11-03'], 7) === 2);
t('gap 15d beyond grace → 1', streak_current(['2026-10-20', '2026-11-04'], 7) === 1);
t('unordered input ok', streak_current(['2026-11-03', '2026-10-20', '2026-10-27'], 7) === 3);
t('same-day reps collapse, streak 3', streak_current(['2026-10-20', '2026-10-27', '2026-10-27', '2026-11-03'], 7) === 4);
t('break mid-chain keeps recent run', streak_current(['2026-01-01', '2026-10-27', '2026-11-03'], 7) === 2);
t('daily cadence grace 2d', streak_current(['2026-11-07', '2026-11-08', '2026-11-09'], 1) === 3);
t('daily cadence gap 3d breaks', streak_current(['2026-11-06', '2026-11-09'], 1) === 1);

// --- streak_longest ---
t('longest picks best historical chain', streak_longest(['2026-01-01', '2026-01-08', '2026-01-15', '2026-06-01', '2026-06-08'], 7) === 3);
t('longest empty → 0', streak_longest([], 7) === 0);
t('longest single → 1', streak_longest(['2026-01-01'], 7) === 1);
t('longest all chained → n', streak_longest(['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'], 7) === 4);

// --- global_streak (weakest muscle) ---
t('global = MIN of per-skill streaks', global_streak([5, 12, 3]) === 3);
t('global no skills → 0', global_streak([]) === 0);
t('global one skill → its streak', global_streak([9]) === 9);

// --- due_state ---
$d = due_state(null, 7, '2026-11-09');
t('no reps → due (first rep now)', $d['state'] === 'due' && $d['due_date'] === '2026-11-09');
$d = due_state('2026-11-02', 7, '2026-11-09');
t('today == due date → due', $d['state'] === 'due' && $d['due_date'] === '2026-11-09');
$d = due_state('2026-11-01', 7, '2026-11-09');
t('past due, within grace → due', $d['state'] === 'due');
$d = due_state('2026-10-25', 7, '2026-11-09');
t('past grace → overdue', $d['state'] === 'overdue' && $d['due_date'] === '2026-11-01');
$d = due_state('2026-11-08', 7, '2026-11-09');
t('fresh rep → scheduled', $d['state'] === 'scheduled' && $d['due_date'] === '2026-11-15');
$d = due_state('2026-11-09', 7, '2026-11-09');
t('rep today → scheduled, due in cadence', $d['state'] === 'scheduled' && $d['due_date'] === '2026-11-16');
$d = due_state('2026-10-26', 7, '2026-11-09'); // due 11-02, grace end 11-09
t('today == grace end (within grace) → due not overdue', $d['state'] === 'due');
$d = due_state('2026-10-25', 7, '2026-11-09'); // grace end 11-08
t('today just past grace → overdue', $d['state'] === 'overdue');
$d = due_state('2026-11-05', 1, '2026-11-09'); // daily: grace end 11-07
t('daily past grace → overdue', $d['state'] === 'overdue');

// --- validation ---
t('name empty → error', validate_name('') !== null);
t('name spaces → error', validate_name('   ') !== null);
t('name ok → null', validate_name('Writing first drafts') === null);
t('cadence 0 → error', validate_cadence(0) !== null);
t('cadence 91 → error', validate_cadence(91) !== null);
t('cadence 1 → ok', validate_cadence(1) === null);
t('cadence 90 → ok', validate_cadence(90) === null);
t('cadence "abc" → error', validate_cadence('abc') !== null);
t('cadence default 7 → ok', validate_cadence(7) === null);

// --- SVG curve ---
t('svg empty → empty string', render_confidence_svg([]) === '');
$s1 = render_confidence_svg([3]);
t('svg one point: single r=5 circle', substr_count($s1, '<circle') === 1 && strpos($s1, 'r="5"') !== false);
$s5 = render_confidence_svg([1, 2, 3, 4, 5]);
t('svg five points: 5 circles', substr_count($s5, '<circle') === 5);
t('svg last point r=5, others r=3', substr_count($s5, 'r="3"') === 4 && strpos($s5, 'r="5"') !== false);
t('svg polyline path present', strpos($s5, '<path') !== false);
t('svg gridlines 5', substr_count($s5, '<line') === 5);
t('svg ratings within viewBox', strpos($s5, 'viewBox="0 0 720 180"') !== false);
$s2 = render_confidence_svg([5, 1]);
t('svg descending pair path has L', strpos($s2, ' L') !== false);

// --- fmt helpers ---
t('fmt_short Nov 4', fmt_short('2026-11-04') === 'Nov 4');
t('fmt_long Nov 9, 2026', fmt_long('2026-11-09') === 'Nov 9, 2026');

echo $fail === 0 ? "OK: $pass passed, 0 failed\n" : "FAILURES: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
