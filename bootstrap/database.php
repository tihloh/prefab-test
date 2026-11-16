<?php
declare(strict_types=1);

use Tihloh\Prefab\Core\Database\DatabaseManager;

/** Obtain the shared Prefab database manager for this request. */
function app_database(): DatabaseManager
{
    static $db = null;
    if ($db instanceof DatabaseManager) {
        $columns = $db->select('PRAGMA table_info(submissions)');
    $hasOwner = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'user_id') {
            $hasOwner = true;
            break;
        }
    }
    if (!$hasOwner) {
        $db->statement('ALTER TABLE submissions ADD COLUMN user_id INTEGER NULL');
    }
    $db->statement('CREATE INDEX IF NOT EXISTS idx_submissions_user_id ON submissions(user_id, id)');

    return $db;
    }

    $directory = dirname(__DIR__) . '/storage';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create database storage directory.');
    }

    $db = new DatabaseManager();
    $db->set('sqlite', [
        'driver' => 'sqlite',
        'database' => $directory . '/prefab.sqlite',
    ])->useDefault('sqlite');

    $db->statement(
        'CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            amount REAL NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    return $db;
}
