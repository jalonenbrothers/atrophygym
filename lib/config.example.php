<?php
declare(strict_types=1);

return [
    // --- database (required) ---
    'db' => [
        'host'    => 'localhost',   // Hostinger preset; rarely changes
        'port'    => 3306,
        'name'    => 'u123456789_atrophygym',
        'user'    => 'u123456789_appuser',
        'pass'    => 'CHANGE_ME',
    ],

    // --- app (optional, one line) ---
    // 'app_url' => 'https://myapp7mvp.earth-turtle.com', // omit for apps that don't need absolute URLs

    // --- debug (always present) ---
    'debug' => false, // true = display errors, no .htaccess-internal blocking in logs
];
