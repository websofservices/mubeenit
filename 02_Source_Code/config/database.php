<?php
/**
 * MTNMANGOES Database Configuration
 * Default: SQLite (zero-config). Change to MySQL when deploying on XAMPP.
 */

// --- SQLite (default for easy demo) ---
define('DB_DRIVER', 'sqlite');
define('DB_PATH', __DIR__ . '/../database/mtnmangoes.db');

// --- MySQL (uncomment & configure for XAMPP production) ---
// define('DB_DRIVER', 'mysql');
// define('DB_HOST', '127.0.0.1');
// define('DB_NAME', 'mtnmangoes');
// define('DB_USER', 'root');
// define('DB_PASS', '');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        if (DB_DRIVER === 'sqlite') {
            $pdo = new PDO('sqlite:' . DB_PATH);
        } else {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if (DB_DRIVER === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON;');
        }
    }
    return $pdo;
}
