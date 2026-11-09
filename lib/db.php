<?php
declare(strict_types=1);

/**
 * AtrophyGym — DB loader + PDO factory (stack-standard shape).
 * Reads lib/config.php; throws a clear setup message if missing.
 */

function db_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit("Setup needed: copy lib/config.example.php to lib/config.php and fill in your database credentials.");
        }
        $cfg = require $file;
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = db_config();
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['db']['host'], (int)$c['db']['port'], $c['db']['name']);
        $pdo = new PDO($dsn, $c['db']['user'], $c['db']['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** Today's date as Y-m-d. All streak/due math uses this single source. */
function today(): string
{
    return date('Y-m-d'); // server-local naive date (set PHP date.timezone; see migrations/README.md)
}

function db_debug(): bool
{
    $c = db_config();
    return (bool)($c['debug'] ?? false);
}
