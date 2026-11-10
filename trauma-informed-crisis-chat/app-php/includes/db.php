<?php
/**
 * Thin PDO wrapper. Works with SQLite (default) and MySQL (XAMPP/phpMyAdmin)
 * from the same code path, so the same schema can be shown in either tool.
 */

function sw_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function sw_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = sw_config();

    if ($cfg['driver'] === 'mysql') {
        $m = $cfg['mysql'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $m['host'], $m['port'], $m['database'], $m['charset']);
        $pdo = new PDO($dsn, $m['user'], $m['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        sw_create_tables($pdo, 'mysql');
    } else {
        $path = $cfg['sqlite_path'];
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        sw_create_tables($pdo, 'sqlite');
    }

    return $pdo;
}

/**
 * Schema is created on first run so the project needs no import step.
 * database/schema-mysql.sql and database/schema-sqlite.sql mirror this exactly
 * for the report / phpMyAdmin.
 */
function sw_create_tables(PDO $pdo, string $driver): void
{
    $id  = $driver === 'mysql'
        ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
        : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    // MySQL needs a length on keyed string columns; SQLite does not care.
    $key = $driver === 'mysql' ? 'VARCHAR(64)' : 'TEXT';
    $txt = $driver === 'mysql' ? 'VARCHAR(255)' : 'TEXT';
    $long = 'TEXT';

    $statements = [
        "CREATE TABLE IF NOT EXISTS sessions (
            id $key NOT NULL PRIMARY KEY,
            alias $txt NOT NULL,
            consent INTEGER NOT NULL DEFAULT 1,
            keep_words INTEGER NOT NULL DEFAULT 0,
            tier_max INTEGER NOT NULL DEFAULT 0,
            msg_count INTEGER NOT NULL DEFAULT 0,
            recent $long NULL,
            created_at DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            ended_at DATETIME NULL,
            deleted_at DATETIME NULL
        )",

        "CREATE TABLE IF NOT EXISTS messages (
            id $id,
            session_id $key NOT NULL,
            role VARCHAR(16) NOT NULL,
            body $long NULL,
            category $txt NULL,
            tier INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS risk_events (
            id $id,
            session_id $key NOT NULL,
            category TEXT NOT NULL,
            tier INTEGER NOT NULL,
            created_at TEXT NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS escalations (
            id $id,
            session_id $key NOT NULL,
            tier INTEGER NOT NULL,
            shown_at DATETIME NOT NULL,
            accepted INTEGER NULL,
            resolved_at TEXT NULL
        )",

        "CREATE TABLE IF NOT EXISTS grounding_runs (
            id $id,
            session_id $key NOT NULL,
            kind $txt NOT NULL,
            rounds INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        )",
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }

    foreach ([
        'CREATE INDEX idx_messages_session ON messages (session_id)',
        'CREATE INDEX idx_risk_session ON risk_events (session_id)',
        'CREATE INDEX idx_esc_session ON escalations (session_id)',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // index already exists - harmless
        }
    }
}

function sw_now(): string
{
    return gmdate('Y-m-d H:i:s');
}
