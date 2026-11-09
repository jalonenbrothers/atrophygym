<?php
declare(strict_types=1);

/* E1 — Dashboard. GET index.php (?archived=1 adds archived section). */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/gym.php';
require_once __DIR__ . '/lib/flash.php';
session_start();

$skills = db()->query('SELECT * FROM skills WHERE archived = 0')->fetchAll();

// Enrich each skill: last rep, streak, due state
$cards = [];
foreach ($skills as $s) {
    $reps = repsForSkill((int)$s['id']);
    $last = $reps ? $reps[count($reps) - 1]['logged_at'] : null;
    [$cur, $best] = streakForSkill($reps, (int)$s['cadence_days']);
    $cards[] = [
        'skill'   => $s,
        'last'    => $last,
        'streak'  => $cur,
        'due'     => dueState($last, (int)$s['cadence_days']),
    ];
}

// Ordering: Overdue > Due > Scheduled; within each, oldest due-date first
usort($cards, function (array $a, array $b): int {
    $ra = dueRank($a['due']['state']);
    $rb = dueRank($b['due']['state']);
    if ($ra !== $rb) {
        return $ra <=> $rb;
    }
    return strcmp($a['due']['due_date'], $b['due']['due_date']);
});

[$globalCur, $globalBest] = globalStreak();

$body = '';

// Streak strip (F3)
$body .= '<section class="streak-strip" aria-label="Streaks">
  <div class="streak-grid">
    <div class="stat-tile"><div class="label">Current streak</div><div class="num">' . $globalCur . ' days</div></div>
    <div class="stat-tile"><div class="label">Best streak</div><div class="num">' . $globalBest . ' days</div></div>
  </div>
  <p class="streak-note">A streak breaks when a skill&rsquo;s practice window (2&times; its cadence) passes without a rep.</p>
</section>';

// Skill list / empty state
if ($cards === []) {
    $body .= '<section class="empty-state">
  <h2>What have you delegated to AI?</h2>
  <p>Name a skill you now let AI do &mdash; writing, coding, math, remembering. AtrophyGym schedules short no-AI practice reps so the skill doesn&rsquo;t quietly die.</p>
  <a class="btn btn-primary" href="skill-form.php">Add your first skill</a>
</section>';
} else {
    $body .= '<h2>Skills</h2><div class="skill-list">';
    foreach ($cards as $c) {
        $s = $c['skill'];
        $name = e($s['name']);
        $cadence = (int)$s['cadence_days'];
        $meta = [];
        $meta[] = 'streak ' . (int)$c['streak'];
        $meta[] = $c['last'] !== null ? 'last rep ' . e(shortDate($c['last'])) : 'no reps yet';
        $body .= '<a class="skill-card" href="skill.php?id=' . (int)$s['id'] . '" data-skill-id="' . (int)$s['id'] . '">
  <div class="row-top">
    <span class="skill-name">' . $name . '</span>
    <span class="cadence-chip">every ' . $cadence . ' days</span>
  </div>
  <div class="row-top"><span class="skill-meta">' . implode(' · ', $meta) . '</span>'
            . badgeHtml($c['due']['state'], $c['due']['due_date']) . '</div>
</a>';
    }
    $body .= '</div>';
}

// Archived section
$archived = db()->query('SELECT * FROM skills WHERE archived = 1 ORDER BY name')->fetchAll();
if ($archived !== []) {
    $body .= '<a class="archived-link" href="index.php?archived=1">Archived skills (' . count($archived) . ')</a>';
}
if (isset($_GET['archived']) && $_GET['archived'] === '1') {
    $body .= '<section class="archived-section"><h2>Archived skills</h2><div class="skill-list">';
    foreach ($archived as $s) {
        $body .= '<div class="skill-card" data-skill-id="' . (int)$s['id'] . '">
  <div class="unarchive-row">
    <span class="skill-name">' . e($s['name']) . '</span>
    <span class="badge badge-archived">Archived</span>
  </div>
  <div class="unarchive-row" style="margin-top:8px;">
    <a class="btn" href="skill.php?id=' . (int)$s['id'] . '">View</a>
  </div>
</div>';
    }
    $body .= '</div></section>';
}

echo pageShell('AtrophyGym', $body);
