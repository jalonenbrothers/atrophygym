<?php
declare(strict_types=1);
/**
 * AtrophyGym — E4 + E5 + E6: Add / edit / archive skill.
 *  GET  skill-form.php (?id=N for edit)
 *  POST skill-form.php            → create
 *  POST skill-form.php?id=N       → edit
 *  POST skill-form.php?id=N&action=archive → two-step archive (confirm=0 → confirm=1)
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/src/Streak.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start(); // before ANY output (flash)

$isEdit = false;
$skill = null;
if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    $skill = skill_find((int)$_GET['id']);
    if ($skill) $isEdit = true;
    else {
        http_response_code(404);
        ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Skill not found — AtrophyGym</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="wrap"><p><a href="index.php" class="quiet-link">← Dashboard</a></p><h1>Skill not found.</h1></div></body></html><?php
        exit;
    }
}

// form state (sticky on validation errors)
$name = $skill['name'] ?? '';
$desc = $skill['delegate_desc'] ?? '';
$cadence = (string)($skill['cadence_days'] ?? 7);
$nameError = $cadenceError = '';
$confirmArchive = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- E6: archive (two-step, no JS) ---
    if ($isEdit && $action === 'archive') {
        if (($_POST['confirm'] ?? '0') !== '1') {
            $confirmArchive = true; // re-render edit form with "Really archive? Tap again"
            $name = $_POST['name'] ?? $name;
            $desc = $_POST['delegate_desc'] ?? $desc;
            $cadence = (string)($_POST['cadence_days'] ?? $cadence);
        } else {
            $st = db()->prepare('UPDATE skills SET archived = 1 WHERE id = ?');
            $st->execute([(int)$skill['id']]);
            flash_set('Archived. Its reps are kept.');
            redirect('index.php');
        }
    } else {
        // --- E4/E5: create / edit ---
        $name = is_scalar($_POST['name'] ?? null) ? trim((string)$_POST['name']) : '';
        $desc = is_scalar($_POST['delegate_desc'] ?? null) ? trim((string)$_POST['delegate_desc']) : '';
        $cadence = is_scalar($_POST['cadence_days'] ?? null) ? trim((string)$_POST['cadence_days']) : '7';
        $nameError = validate_name($name);
        $cadenceError = validate_cadence($cadence);
        if ($nameError === null && mb_strlen($name) > 120) $nameError = 'Give the skill a name.';
        if ($nameError === null && $cadenceError === null) {
            $cad = (int)$cadence;
            if ($isEdit) {
                $st = db()->prepare('UPDATE skills SET name = ?, delegate_desc = ?, cadence_days = ? WHERE id = ?');
                $st->execute([$name, $desc, $cad, (int)$skill['id']]);
                flash_set('Skill saved.');
                redirect('skill.php?id=' . (int)$skill['id']);
            } else {
                $st = db()->prepare('INSERT INTO skills (name, delegate_desc, cadence_days, archived, created_at) VALUES (?, ?, ?, 0, NOW())');
                $st->execute([$name, $desc, $cad]);
                $newId = (int)db()->lastInsertId();
                flash_set('Skill saved.');
                redirect('skill.php?id=' . $newId);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isEdit ? 'Edit skill' : 'New skill' ?> — AtrophyGym</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="wrap">
<?= flash_get() ?>
<p><a href="<?= $isEdit ? 'skill.php?id=' . (int)$skill['id'] : 'index.php' ?>" class="quiet-link">← <?= $isEdit ? h($skill['name']) : 'Dashboard' ?></a></p>

<h1><?= $isEdit ? 'Edit skill' : 'New skill' ?></h1>

<form method="post"<?= $isEdit ? ' action="skill-form.php?id=' . (int)$skill['id'] . '"' : ' action="skill-form.php"' ?>>
  <label class="field">
    <span class="label">Name</span>
    <input type="text" name="name" value="<?= h($name) ?>" maxlength="120" placeholder="e.g. Writing first drafts">
    <?php if ($nameError): ?><span class="field-error"><?= h($nameError) ?></span><?php endif ?>
  </label>

  <label class="field">
    <span class="label">What do you delegate to AI?</span>
    <textarea name="delegate_desc" rows="3" placeholder="e.g. I let AI draft every email and post — I only edit."><?= h($desc) ?></textarea>
  </label>

  <label class="field">
    <span class="label">Cadence (days)</span>
    <input type="number" name="cadence_days" value="<?= h($cadence) ?>" min="1" max="90">
    <span class="hint muted small">How often you want to practice without AI, in days. Default weekly.</span>
    <?php if ($cadenceError): ?><span class="field-error"><?= h($cadenceError) ?></span><?php endif ?>
  </label>

  <button type="submit" name="action" value="save" class="btn btn-primary">Save skill</button>

  <?php if ($isEdit): ?>
    <div class="danger-zone">
      <p class="muted">Archive this skill — hides it from the dashboard, keeps all reps.</p>
      <?php if ($confirmArchive): ?>
        <input type="hidden" name="action" value="archive">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="btn btn-danger">Really archive? Tap again</button>
      <?php else: ?>
        <button type="submit" name="action" value="archive" class="btn">Archive</button>
      <?php endif ?>
    </div>
  <?php endif ?>
</form>

<footer class="footer muted small">
  A gym for the skills you offloaded to AI.<br>
  No accounts. No tracking. Just honest reps.
</footer>
</div>
</body>
</html>
