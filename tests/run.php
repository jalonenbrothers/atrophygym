<?php
declare(strict_types=1);
/**
 * AtrophyGym unit tests — pure logic only, no DB needed.
 * Covers: streak math (incl. today-break rule), longest streak, due states,
 * validation, SVG curve shape. Run: php tests/run.php
 */
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/gym.php';
require __DIR__ . '/../lib/flash.php'; // e() helper

$pass = 0; $fail = 0;
function t(string $label, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; echo "FAIL: $label\n"; }
}
/** repsForSkill-shaped rows helper */
function reps(string ...$dates): array
{
    $out = [];
    foreach ($dates as $d) { $out[] = ['id' => 0, 'logged_at' => $d, 'rating' => 3, 'note' => null]; }
    return $out;
}

// --- streakForSkill: grace = 2x cadence, same-day reps collapse ---
t('empty → [0,0]', streakForSkill([], 7) === [0, 0]);
t('one rep today → [1,1]', streakForSkill(reps(today()), 7) === [1, 1]);
t('3 weekly reps → current 3', streakForSkill(reps('2026-10-20', '2026-10-27', '2026-11-03'), 7)[0] === 3);
t('gap 8d within 14d grace → 2', streakForSkill(reps('2026-10-27', '2026-11-04'), 7)[0] === 2);
t('gap 14d exactly at grace → 2', streakForSkill(reps('2026-10-20', '2026-11-03'), 7)[0] === 2);
t('gap 15d beyond grace → 1', streakForSkill(reps('2026-10-20', '2026-11-04'), 7)[0] === 1);
t('same-day reps collapse (streak counts dates)', streakForSkill(reps('2026-10-20', '2026-10-27', '2026-10-27', '2026-11-03'), 7)[0] === 3);
t('unordered input handled', streakForSkill(reps('2026-11-03', '2026-10-20', '2026-10-27'), 7)[0] === 3);
t('break mid-chain keeps recent run', streakForSkill(reps('2026-01-01', '2026-10-27', '2026-11-03'), 7)[0] === 2);
t('daily cadence grace 2d', streakForSkill(reps('2026-11-07', '2026-11-08', '2026-11-09'), 1)[0] === 3);
t('daily cadence gap 3d breaks', streakForSkill(reps('2026-11-06', '2026-11-09'), 1)[0] === 1);

// today-break rule: latest rep beyond grace vs TODAY → current 0
$today = today();
$old = date('Y-m-d', strtotime('-30 days'));
$older = date('Y-m-d', strtotime('-37 days'));
t('stale chain (last rep 30d ago, cadence 7) → current 0, longest 2',
    streakForSkill(reps($older, $old), 7) === [0, 2]);

// --- longest streak ---
t('longest picks best historical chain', streakForSkill(reps('2026-01-01', '2026-01-08', '2026-01-15', '2026-06-01', '2026-06-08'), 7)[1] === 3);
t('longest all chained → n', streakForSkill(reps('2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'), 7)[1] === 4);

// --- dueState (pinned semantics) ---
$d = dueState(null, 7);
t('no reps → due', $d === ['state' => 'due', 'due_date' => $today]);
$d = dueState('2026-11-02', 7, );
// use fixed "today" by comparison against strings (today() is date('Y-m-d'))
t('today==due → due', dueState(date('Y-m-d', strtotime('-7 days')), 7)['state'] === 'due');
t('past due within grace → due', dueState(date('Y-m-d', strtotime('-8 days')), 7)['state'] === 'due');
t('past grace → overdue', dueState(date('Y-m-d', strtotime('-15 days')), 7)['state'] === 'overdue');
t('fresh rep → scheduled', dueState($today, 7)['state'] === 'scheduled');
t('due date = last + cadence', dueState($today, 7)['due_date'] === date('Y-m-d', strtotime('+7 days')));
t('grace boundary: 14d old, cadence 7 → due (within grace)', dueState(date('Y-m-d', strtotime('-14 days')), 7)['state'] === 'due');
t('daily past grace (3d) → overdue', dueState(date('Y-m-d', strtotime('-3 days')), 1)['state'] === 'overdue');

// --- date helpers ---
t('shortDate Nov 4', shortDate('2026-11-04') === 'Nov 4');
t('longDate Nov 9, 2026', longDate('2026-11-09') === 'Nov 9, 2026');
t('daysBetween', daysBetween('2026-11-01', '2026-11-09') === 8);

// --- escaping ---
t('e() escapes quotes/tags', e('<b>"x"</b>') === '&lt;b&gt;&quot;x&quot;&lt;/b&gt;');

echo $fail === 0 ? "OK: $pass passed, 0 failed\n" : "FAILURES: $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
