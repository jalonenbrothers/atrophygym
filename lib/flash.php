<?php
declare(strict_types=1);

/* Session flash messages (no JS) + shared page shell. */

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['msg' => $message, 'type' => $type];
}

function renderFlash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $cls = $f['type'] === 'err' ? 'flash-err' : 'flash-ok';
    return '<div class="flash ' . $cls . '" role="status">' . htmlspecialchars($f['msg']) . '</div>';
}

/**
 * Escape helper for all dynamic output.
 */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function pageShell(string $title, string $body): string
{
    $flash = renderFlash();
    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . e($title) . '</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
  <a class="wordmark" href="index.php">AtrophyGym</a>
  <a class="btn btn-primary" href="skill-form.php">+ New skill</a>
</header>
<main>
' . $flash . $body . '
</main>
<footer class="site-footer">
  A gym for the skills you offloaded to AI.<br>
  No accounts. No tracking. Just honest reps.
</footer>
</body>
</html>';
}
