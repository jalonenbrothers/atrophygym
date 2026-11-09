<?php
declare(strict_types=1);

/* E2 GET skill.php?id=N — Skill detail (curve, history, log form)
 * E3 POST skill.php?id=N&action=log — Log a rep
 * E7 POST skill.php?id=N&action=unarchive — Unarchive */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/gym.php';
require_once __DIR__ . '/lib/flash.php';
session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    echo pageShell('Skill not found — AtrophyGym', '<div class="not-found"><h1>Skill not found.</h1><p><a class="breadcrumb" href="index.php">&larr; Dashboard</a></p></div>');
    exit;
}

$st = db()->prepare('SELECT * FROM skills WHERE id = ?');
$st->execute([$id]);
$skill = $st->fetch();
if (!$skill) {
    http_response_code(404);
    echo pageShell('Skill not found — AtrophyGym', '<div class="not-found"><h1>Skill not found.</h1><p><a class="breadcrumb" href="index.php">&larr; Dashboard</a></p></div>');
    exit;
}

$action = $_GET['action'] ?? null;

/* ---- E7: Unarchive (POST) ---- */
if ($action === 'unarchive' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = db()->prepare('UPDATE skills SET archived = 0 WHERE id = ?');
    $u->execute([$id]);
    flash('Skill restored.');
    header('Location: skill.php?id=' . $id, true, 303);
    exit;
}

/* ---- E3: Log a rep (POST) ---- */
$logError = null;
if ($action === 'log' && $_SERVER['REQUEST_METHOD'] === 'POST' && !(int)$skill['archived']) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $note = trim((string)($_POST['note'] ?? ''));
    if ($rating < 1 || $rating > 5) {
        $logError = 'Pick a confidence rating first.';
    } else {
        $note = mb_substr($note, 0, 2000);
        try {
            $ins = db()->prepare('INSERT INTO reps (skill_id, rating, note, logged_at) VALUES (?, ?, ?, ?)');
            $ins->execute([$id, $rating, $note !== '' ? $note : null, today()]);
        } catch (PDOException $ex) {
            http_response_code(500);
            echo pageShell('AtrophyGym', '<div class="flash flash-err" role="alert">Something broke on our side. Try again.</div><p><a class="breadcrumb" href="skill.php?id=' . $id . '">&larr; Dashboard</a></p>');
            exit;
        }
        // compute new streak for flash text (same functions as render — no drift)
        [$cur] = streakForSkill(repsForSkill($id), (int)$skill['cadence_days']);
        flash('Rep logged. Streak ' . $cur . '.');
        header('Location: skill.php?id=' . $id, true, 303);
        exit;
    }
}

/* ---- E2: Render skill detail ---- */
$cadence = (int)$skill['cadence_days'];
$reps = repsForSkill($id);
$last = $reps ? $reps[count($reps) - 1]['logged_at'] : null;
[$curStreak, $bestStreak] = streakForSkill($reps, $cadence);
$due = dueState($last, $cadence);
$archived = (int)$skill['archived'] === 1;

$body = '<p><a class="breadcrumb" href="index.php">&larr; Dashboard</a></p>';

// Skill header
$body .= '<section class="skill-header">
  <div class="row-top"><span class="skill-name">' . e($skill['name']) . '</span></div>'
  . ($skill['delegate_desc'] !== null && $skill['delegate_desc'] !== '' ? '<p class="delegate-desc">' . e($skill['delegate_desc']) . '</p>' : '')
  . '<p class="skill-meta">practice every ' . $cadence . ' days</p>
  <p class="streak-line skill-meta">Current streak ' . $curStreak . ' &middot; Best ' . $bestStreak . '</p>
  <div class="badge-row">' . badgeHtml($due['state'], $due['due_date']) . '</div>
</section>';

// Log-a-rep form (F2) — primary action at top; hidden when archived
if ($archived) {
    $body .= '<section><p>This skill is archived.</p>
  <form method="post" action="skill.php?id=' . $id . '&action=unarchive">
    <button type="submit" class="btn">Unarchive</button>
  </form></section>';
} else {
    $body .= '<section class="log-section">
  <h2>Log a no-AI rep</h2>
  <p>You just practiced this without AI. How confident did you feel?</p>
  <form method="post" action="skill.php?id=' . $id . '&action=log" novalidate>';
    if ($logError !== null) {
        $body .= '<p class="form-error" role="alert">' . e($logError) . '</p>';
    }
    $body .= '<div class="form-field"><div class="rating-list">';
    foreach (RATING_LABELS as $val => $label) {
        $body .= '<label><input type="radio" name="rating" value="' . $val . '" required'
            . ($logError !== null && isset($_POST['rating']) && (int)$_POST['rating'] === $val ? ' checked' : '')
            . '> <span>' . $val . ' &mdash; ' . e($label) . '</span></label>';
    }
    $body .= '</div></div>
  <div class="form-field">
    <label for="note">What was hard without AI? (optional)</label>
    <textarea id="note" name="note" placeholder="">' . e(trim((string)($_POST['note'] ?? ''))) . '</textarea>
  </div>
  <button type="submit" class="btn btn-primary">Log rep</button>
  </form></section>';
}

// Confidence curve (F4) — server-generated inline SVG
$body .= '<h2>Confidence over time</h2>';
$n = count($reps);
if ($n === 0) {
    $body .= '<p class="curve-note empty-curve">' . ($archived ? 'No reps logged.' : 'No reps yet. Log your first rep above.') . '</p>';
} else {
    $w = 640; $h = 180; $padL = 28; $padR = 12; $padT = 12; $padB = 20;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;
    $y = fn(int $r): float => $padT + $plotH - (($r - 1) / 4) * $plotH;
    $svg = '<svg class="curve-svg" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Confidence over time, ' . $n . ' reps">';
    // gridlines 1..5 with tiny labels
    for ($r = 1; $r <= 5; $r++) {
        $yy = $y($r);
        $svg .= '<line x1="' . $padL . '" y1="' . $yy . '" x2="' . ($w - $padR) . '" y2="' . $yy . '" stroke="#E8E6E0" stroke-width="1"/>';
        $svg .= '<text x="' . ($padL - 6) . '" y="' . ($yy + 4) . '" font-size="10" fill="#6B6B66" text-anchor="end">' . $r . '</text>';
    }
    if ($n === 1) {
        $cx = $padL + $plotW / 2;
        $cy = $y((int)$reps[0]['rating']);
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="5" fill="#2E5E4E"/>';
    } else {
        $x = fn(int $i): float => $padL + ($n === 1 ? 0 : ($i / ($n - 1)) * $plotW);
        $pts = [];
        foreach ($reps as $i => $r) {
            $pts[] = [$x($i), $y((int)$r['rating'])];
        }
        $poly = implode(' ', array_map(fn(array $p) => $p[0] . ',' . $p[1], $pts));
        $svg .= '<polyline points="' . $poly . '" fill="none" stroke="#2E5E4E" stroke-width="2"/>';
        foreach ($pts as $i => $p) {
            $r = $i === count($pts) - 1 ? 5 : 3;
            $svg .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="' . $r . '" fill="#2E5E4E"/>';
        }
    }
    $svg .= '</svg>';
    $body .= $svg;
    if ($n === 1) {
        $body .= '<p class="curve-note">One rep &mdash; the curve starts with the second.</p>';
    }
}

// Rep history (F5) — last 50, reverse-chron, stacked divs
$body .= '<h2>Rep history</h2>';
if ($n === 0) {
    $body .= '<p class="history-note">' . ($archived ? 'No reps logged.' : 'No reps yet. Your first rep starts the streak.') . '</p>';
} else {
    $slice = array_slice(array_reverse($reps), 0, 50);
    $body .= '<div class="rep-history">';
    foreach ($slice as $r) {
        $body .= '<div class="rep-row">
  <div class="rep-top">
    <span class="rep-date">' . e(longDate($r['logged_at'])) . '</span>
    <span class="rep-rating">' . (int)$r['rating'] . '/5</span>
  </div>'
      . (!empty($r['note']) ? '<div class="rep-note">' . e($r['note']) . '</div>' : '')
      . '</div>';
    }
    $body .= '</div>';
    if ($n > 50) {
        $body .= '<p class="history-note">Showing last 50 reps</p>';
    }
}

// Edit link (hidden for archived)
if (!$archived) {
    $body .= '<p style="margin-top:24px;"><a class="btn-plain" style="display:inline-block" href="skill-form.php?id=' . $id . '">Edit skill</a></p>';
}

echo pageShell(e($skill['name']) . ' — AtrophyGym', $body);
