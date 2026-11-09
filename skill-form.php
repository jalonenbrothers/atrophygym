<?php
declare(strict_types=1);

/* E4 POST skill-form.php — Add skill
 * E5 POST skill-form.php?id=N — Edit skill
 * E6 POST skill-form.php?id=N&action=archive — Archive (two-step confirm)
 * GET: renders the form (add or edit). */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/gym.php';
require_once __DIR__ . '/lib/flash.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editing = $id > 0;
$skill = null;

if ($editing) {
    $st = db()->prepare('SELECT * FROM skills WHERE id = ?');
    $st->execute([$id]);
    $skill = $st->fetch();
    if (!$skill) {
        http_response_code(404);
        echo pageShell('Skill not found — AtrophyGym', '<div class="not-found"><h1>Skill not found.</h1><p><a class="breadcrumb" href="index.php">&larr; Dashboard</a></p></div>');
        exit;
    }
}

$action = $_GET['action'] ?? null;

/* ---- E6: Archive (POST, two-step inline confirm — no JS confirm dialogs) ---- */
if ($action === 'archive' && $_SERVER['REQUEST_METHOD'] === 'POST' && $editing) {
    $confirm = ($_POST['confirm'] ?? '0') === '1';
    if (!$confirm) {
        // re-render edit form with the button in "Really archive?" state
        // (handled below via $confirmPending flag)
        $confirmPending = true;
    } else {
        $u = db()->prepare('UPDATE skills SET archived = 1 WHERE id = ?');
        $u->execute([$id]);
        flash('Archived. Its reps are kept.');
        header('Location: index.php', true, 303);
        exit;
    }
} else {
    $confirmPending = false;
}

/* ---- E4/E5: Save (POST) ---- */
$errors = [];
$values = ['name' => '', 'delegate_desc' => '', 'cadence_days' => '7'];
$old = ['name' => '', 'delegate_desc' => '', 'cadence_days' => '7'];
if ($skill) {
    $old = [
        'name' => (string)$skill['name'],
        'delegate_desc' => (string)($skill['delegate_desc'] ?? ''),
        'cadence_days' => (string)$skill['cadence_days'],
    ];
}
$values = $old;
$submitted = false;

if ($action === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;
    $values = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'delegate_desc' => trim((string)($_POST['delegate_desc'] ?? '')),
        'cadence_days' => (string)($_POST['cadence_days'] ?? '7'),
    ];
    $cadence = (int)$values['cadence_days'];
    if ($values['name'] === '' || mb_strlen($values['name']) > 120) {
        $errors['name'] = 'Give the skill a name.';
    }
    if (!ctype_digit(ltrim($values['cadence_days'], '-')) || $cadence < 1 || $cadence > 90) {
        $errors['cadence_days'] = 'Cadence must be between 1 and 90 days.';
    }
    if (!$errors) {
        try {
            if ($editing) {
                $u = db()->prepare('UPDATE skills SET name = ?, delegate_desc = ?, cadence_days = ? WHERE id = ?');
                $u->execute([$values['name'], $values['delegate_desc'] !== '' ? $values['delegate_desc'] : null, $cadence, $id]);
            } else {
                $u = db()->prepare('INSERT INTO skills (name, delegate_desc, cadence_days) VALUES (?, ?, ?)');
                $u->execute([$values['name'], $values['delegate_desc'] !== '' ? $values['delegate_desc'] : null, $cadence]);
                $id = (int)db()->lastInsertId();
            }
        } catch (PDOException $ex) {
            http_response_code(500);
            echo pageShell('AtrophyGym', '<div class="flash flash-err" role="alert">Something broke on our side. Try again.</div>');
            exit;
        }
        flash('Skill saved.');
        header('Location: skill.php?id=' . $id, true, 303);
        exit;
    }
}
if ($confirmPending) {
    // keep old values on the re-rendered confirm form
    $values = $old;
}

/* ---- Render form ---- */
$heading = $editing ? 'Edit skill' : 'New skill';
$body = '<p><a class="breadcrumb" href="' . ($editing ? 'skill.php?id=' . $id : 'index.php') . '">&larr; ' . ($editing ? 'Back to skill' : 'Dashboard') . '</a></p>';
$body .= '<h1>' . $heading . '</h1><form class="card-form" method="post" action="' . ($editing ? 'skill-form.php?id=' . $id : 'skill-form.php') . '" novalidate>';

$errName = isset($errors['name']) ? '<div class="field-error">' . e($errors['name']) . '</div>' : '';
$errCad = isset($errors['cadence_days']) ? '<div class="field-error">' . e($errors['cadence_days']) . '</div>' : '';

$body .= '<div class="form-field">
  <label for="name">Name</label>
  <input type="text" id="name" name="name" maxlength="120" required placeholder="e.g. Writing first drafts" value="' . e($values['name']) . '">
  ' . $errName . '
</div>
<div class="form-field">
  <label for="delegate_desc">What do you delegate to AI?</label>
  <textarea id="delegate_desc" name="delegate_desc" placeholder="e.g. I let AI draft every email and post &mdash; I only edit.">' . e($values['delegate_desc']) . '</textarea>
</div>
<div class="form-field">
  <label for="cadence_days">Cadence (days)</label>
  <input type="number" id="cadence_days" name="cadence_days" min="1" max="90" step="1" value="' . e($values['cadence_days'] !== '' ? $values['cadence_days'] : '7') . '">
  <div class="helper">How often you want to practice without AI, in days. Default weekly.</div>
  ' . $errCad . '
</div>
<button type="submit" class="btn btn-primary">Save skill</button>
</form>';

// Archive zone — edit only
if ($editing) {
    $body .= '<div class="archive-zone">
  <p>Archive this skill &mdash; hides it from the dashboard, keeps all reps.</p>';
    if ($confirmPending) {
        $body .= '<form method="post" action="skill-form.php?id=' . $id . '&action=archive">
    <input type="hidden" name="confirm" value="1">
    <button type="submit" class="btn btn-danger-adjacent">Really archive? Tap again</button>
  </form>';
    } else {
        $body .= '<form method="post" action="skill-form.php?id=' . $id . '&action=archive">
    <input type="hidden" name="confirm" value="0">
    <button type="submit" class="btn btn-danger-adjacent">Archive</button>
  </form>';
    }
    $body .= '</div>';
}

echo pageShell($heading . ' — AtrophyGym', $body);
