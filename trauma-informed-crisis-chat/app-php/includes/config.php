<?php
/**
 * Stillwater - single configuration file.
 *
 * Defaults are set for XAMPP with zero setup: PDO + SQLite, a database file
 * created next to the code on first run. Switch driver to 'mysql' if you would
 * rather demonstrate it in phpMyAdmin (database/schema-mysql.sql).
 */

return [
    'app_name'      => 'Stillwater',
    'tagline'       => 'A quiet place to be heard.',
    'version'       => '1.0.0',

    // 'sqlite' (no setup) or 'mysql' (XAMPP / phpMyAdmin)
    'driver'        => 'sqlite',
    'sqlite_path'   => __DIR__ . '/../storage/stillwater.sqlite',

    'mysql' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'stillwater',
        'user'     => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // Passphrase for observer.php. Change it before you submit.
    'observer_key'  => 'stillwater-demo',

    // Data minimisation: a new session stores NO message text unless the user
    // turns "keep my words" on. See docs/05-anonymity-policy.md
    'keep_words_default' => false,

    // Seconds before a session is considered abandoned (chat page only).
    'session_idle_seconds' => 7200,

    'helplines' => [
        [
            'name'    => 'Tele-MANAS (Govt. of India)',
            'number'  => '14416',
            'dial'    => '14416',
            'hours'   => '24 x 7',
            'note'    => 'Free. Available in multiple Indian languages.',
            'region'  => 'India',
        ],
        [
            'name'    => 'KIRAN Mental Health Helpline',
            'number'  => '1800-599-0019',
            'dial'    => '18005990019',
            'hours'   => '24 x 7',
            'note'    => 'Free. 13 Indian languages.',
            'region'  => 'India',
        ],
        [
            'name'    => 'AASRA',
            'number'  => '+91 98204 66726',
            'dial'    => '+919820466726',
            'hours'   => '24 x 7',
            'note'    => 'Confidential, English and Hindi.',
            'region'  => 'India',
        ],
        [
            'name'    => 'iCall (TISS)',
            'number'  => '+91 91529 87821',
            'dial'    => '+919152987821',
            'hours'   => 'Mon to Sat, 10am - 8pm',
            'note'    => 'Free counselling by trained volunteers.',
            'region'  => 'India',
        ],
        [
            'name'    => 'Emergency services',
            'number'  => '112',
            'dial'    => '112',
            'hours'   => '24 x 7',
            'note'    => 'If you or someone near you is in immediate danger.',
            'region'  => 'India',
        ],
    ],
];
