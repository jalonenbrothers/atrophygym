<?php
declare(strict_types=1);
/**
 * AtrophyGym — E1: Dashboard (GET index.php, optional ?archived=1).
 * Streak strip, skill cards ordered Overdue > Due > Scheduled (oldest due-date
 * first within each), archived section when ?archived=1.
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/src/Streak.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start(); // before ANY output (flash)
$showArchived = ($_GET['archived'] ?? '') === '1';
$today = today();

// Live skills with last rep date (single query, LEFT JOIN).
$skills = db()->query(
    'SELECT s.id, s.name, s.cadence_days, (SELECT MAX(r.logged_at) FROM reps r WHERE r.skill_id = s.id) AS last_rep
     FROM skills s WHERE s.archived = 0 ORDER BY s.created_at ASC'
)->fetchAll();

$archived = db()->query(
    'SELECT s.id, s.name, s.cadence_days FROM skills s WHERE s.archived = 1 ORDER BY s.created_at ASC'
)->fetchAll();

// Compute per-skill streaks + due state; sort Overdue > Due > Scheduled, oldest due-date first.
$cards = [];
$streaks = [];
foreach ($skills as $s) {
    $dates = skill_rep_dates((int)$s['id']);
    $cur = streak_current($dates, (int)$s['cadence_days']);
    $streaks[] = $cur;
    $due = due_state($s['last_rep'], (int)$s['cadence_days'], $today);
    $cards[] = [
        'id' => (int)$s['id'],
        'name' => $s['name'],
        'cadence' => (int)$s['cadence_days'],
        'last_rep' => $s['last_rep'],
        'streak' => $cur,
        'due' => $due,
    ];
}
usort($cards, function ($a, $b) {
    $so = DUE_ORDER[$a['due']['state']] <=> DUE_ORDER[$b['due']['state']];
    if ($so !== 0) return $so;
    return strcmp((string)$a['due']['due_date'], (string)$b['due']['due_date']);
});

$globalCurrent = global_streak($streaks);
$bestGlobal = count($streaks) ? max($streaks) : 0; // headline "Best streak" = best per-skill current streak
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AtrophyGym</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
<?= flash_get() ?>
<header class="top">
  <div class="wordmark">AtrophyGym</div>
  <a class="btn btn-primary" href="skill-form.php">+ New skill</a>
</header>

<?php if (count($cards) === 0 && !$showArchived): ?>
  <section class="empty">
    <h1>What have you delegated to AI?</h1>
    <p>Name a skill you now let AI do — writing, coding, math, remembering.
       AtrophyGym schedules short no-AI practice reps so the skill doesn't quietly die.</p>
    <a class="btn btn-primary" href="skill-form.php">Add your first skill</a>
  </section>
<?php else: ?>
  <section class="streak-strip" aria-label="Streaks">
    <div class="tile"><div class="tile-label">Current streak</div><div class="tile-num"><?= $globalCurrent ?> days</div></div>
    <div class="tile"><div class="tile-label">Best streak</div><div class="tile-num"><?= $bestGlobal ?> days</div></div>
    <p class="muted small">A streak breaks when a skill's practice window (2× its cadence) passes without a rep.</p>
  </section>

  <section class="skill-list" aria-label="Skills">
  <?php foreach ($cards as $c): ?>
    <a class="skill-card" href="skill.php?id=<?= $c['id'] ?>" data-skill-id="<?= $c['id'] ?>">
      <div class="row1">
        <span class="skill-name"><?= h($c['name']) ?></span>
        <span class="badge badge-<?= $c['due']['state'] ?>">
          <?php if ($c['due']['state'] === 'overdue'): ?>Overdue — practice today
          <?php elseif ($c['due']['state'] === 'due'): ?>Due
          <?php else: ?>Scheduled · next rep <?= fmt_short($c['due']['due_date']) ?><?php endif ?>
        </span>
      </div>
      <div class="meta muted">
        <span class="chip">every <?= $c['cadence'] ?> days</span>
        · streak <?= $c['streak'] ?>
        · <?= $c['last_rep'] ? 'last rep ' . fmt_short($c['last_rep']) : 'no reps yet' ?>
      </div>
    </a>
  <?php endforeach ?>
  </section>
<?php endif ?>

<?php if (!$showArchived && count($archived) > 0): ?>
  <p class="muted"><a href="index.php?archived=1" class="quiet-link">Archived skills (<?= count($archived) ?>)</a></p>
<?php endif ?>

<?php if ($showArchived): ?>
  <h2>Archived skills</h2>
  <?php if (count($archived) === 0): ?><p class="muted">No archived skills.</p><?php endif ?>
  <?php foreach ($archived as $a): ?>
    <a class="skill-card" href="skill.php?id=<?= (int)$a['id'] ?>" data-skill-id="<?= (int)$a['id'] ?>">
      <div class="row1"><span class="skill-name"><?= h($a['name']) ?></span><span class="badge badge-archived">Archived</span></div>
      <div class="meta muted">every <?= (int)$a['cadence_days'] ?> days</div>
    </a>
  <?php endforeach ?>
<?php endif ?>

<footer class="footer muted small">
  A gym for the skills you offloaded to AI.<br>
  No accounts. No tracking. Just honest reps.
</footer>
</div>
</body>
</html>
