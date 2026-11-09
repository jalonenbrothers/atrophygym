<?php
declare(strict_types=1);
/**
 * AtrophyGym — E2 + E3 + E7: Skill detail page.
 *  GET  skill.php?id=N            → detail (curve, history, log form) | 404
 *  POST skill.php?id=N&action=log  → log rep (rating 1–5, note ≤2000)
 *  POST skill.php?id=N&action=unarchive → restore archived skill
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/src/Streak.php';
require __DIR__ . '/src/Curve.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start(); // before ANY output (flash/404)

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$skill = $id ? skill_find($id) : null;
if (!$skill) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Skill not found — AtrophyGym</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="wrap"><p class="top"><a href="index.php" class="quiet-link">← Dashboard</a></p><h1>Skill not found.</h1></div></body></html><?php
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$today = today();
$logError = '';

// --- E7: unarchive (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'unarchive') {
    $st = db()->prepare('UPDATE skills SET archived = 0 WHERE id = ?');
    $st->execute([$id]);
    flash_set('Skill restored.');
    redirect('skill.php?id=' . $id);
}

// --- E3: log a rep (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'log') {
    $rating = $_POST['rating'] ?? null;
    $note = $_POST['note'] ?? '';
    if (!is_scalar($rating) || !ctype_digit((string)$rating) || (int)$rating < 1 || (int)$rating > 5) {
        $logError = 'Pick a confidence rating first.';
    } else {
        $note = is_scalar($note) ? mb_substr(trim((string)$note), 0, 2000) : '';
        $st = db()->prepare('INSERT INTO reps (skill_id, rating, note, logged_at) VALUES (?, ?, ?, ?)');
        $st->execute([$id, (int)$rating, $note, $today]);
        $newStreak = streak_current(skill_rep_dates($id), (int)$skill['cadence_days']);
        flash_set("Rep logged. Streak {$newStreak}.");
        redirect('skill.php?id=' . $id);
    }
}

// --- E2 render ---
$dates = skill_rep_dates($id);
$ratings = skill_ratings($id);
$curStreak = streak_current($dates, (int)$skill['cadence_days']);
$bestStreak = streak_longest($dates, (int)$skill['cadence_days']);
$due = due_state($dates ? $dates[count($dates) - 1] : null, (int)$skill['cadence_days'], $today);
$history = skill_history($id, 50);
$archived = (int)$skill['archived'] === 1;
$svg = render_confidence_svg($ratings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($skill['name']) ?> — AtrophyGym</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
<?= flash_get() ?>
<p><a href="index.php" class="quiet-link">← Dashboard</a></p>

<header>
  <h1><?= h($skill['name']) ?></h1>
  <p class="muted">practice every <?= (int)$skill['cadence_days'] ?> days</p>
  <?php if (trim((string)$skill['delegate_desc']) !== ''): ?>
    <p class="desc italic"><?= h($skill['delegate_desc']) ?></p>
  <?php endif ?>
  <p class="muted">Current streak <?= $curStreak ?> · Best <?= $bestStreak ?></p>
  <p><span class="badge badge-<?= $due['state'] ?>">
    <?php if ($due['state'] === 'overdue'): ?>Overdue — practice today
    <?php elseif ($due['state'] === 'due'): ?>Due
    <?php else: ?>Scheduled · next rep <?= fmt_short($due['due_date']) ?><?php endif ?>
  </span></p>
</header>

<?php if ($archived): ?>
  <div class="archived-box">
    <p>This skill is archived.</p>
    <form method="post" action="skill.php?id=<?= $id ?>&amp;action=unarchive">
      <input type="hidden" name="action" value="unarchive">
      <button type="submit" class="btn">Unarchive</button>
    </form>
  </div>
<?php else: ?>
  <section class="log-form" id="log">
    <h2>Log a no-AI rep</h2>
    <p class="muted">You just practiced this without AI. How confident did you feel?</p>
    <?php if ($logError !== ''): ?><div class="flash flash-error"><?= h($logError) ?></div><?php endif ?>
    <form method="post" action="skill.php?id=<?= $id ?>&amp;action=log">
      <input type="hidden" name="action" value="log">
      <?php for ($r = 1; $r <= 5; $r++): ?>
        <label class="radio-row">
          <input type="radio" name="rating" value="<?= $r ?>">
          <span><?= $r ?> — <?= rating_label($r) ?></span>
        </label>
      <?php endfor ?>
      <label class="field">
        <span class="label">What was hard without AI? (optional)</span>
        <textarea name="note" rows="3" maxlength="2000" placeholder="e.g. Couldn't remember how to start the intro paragraph."></textarea>
      </label>
      <button type="submit" class="btn btn-primary">Log rep</button>
    </form>
  </section>
<?php endif ?>

<section class="curve">
  <h2>Confidence over time</h2>
  <?php if (count($ratings) === 0): ?>
    <p class="muted">No reps yet. Log your first rep above.</p>
  <?php elseif (count($ratings) === 1): ?>
    <?= $svg ?>
    <p class="muted small">One rep — the curve starts with the second.</p>
  <?php else: ?>
    <?= $svg ?>
  <?php endif ?>
</section>

<section class="history">
  <h2>Rep history</h2>
  <?php if (count($history) === 0): ?>
    <p class="muted">No reps yet. Your first rep starts the streak.</p>
  <?php else: ?>
    <?php if (count($history) === 50): ?><p class="muted small">Showing last 50 reps</p><?php endif ?>
    <?php foreach ($history as $rep): ?>
      <div class="rep-row">
        <div><?= fmt_long($rep['logged_at']) ?> — <strong><?= (int)$rep['rating'] ?>/5</strong></div>
        <?php if (trim((string)($rep['note'] ?? '')) !== ''): ?>
          <div class="muted italic note"><?= h($rep['note']) ?></div>
        <?php endif ?>
      </div>
    <?php endforeach ?>
  <?php endif ?>
</section>

<?php if (!$archived): ?>
  <p><a href="skill-form.php?id=<?= $id ?>" class="quiet-link">Edit skill</a></p>
<?php endif ?>

<footer class="footer muted small">
  A gym for the skills you offloaded to AI.<br>
  No accounts. No tracking. Just honest reps.
</footer>
</div>
</body>
</html>
