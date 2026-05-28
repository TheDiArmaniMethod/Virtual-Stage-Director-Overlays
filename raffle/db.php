<?php
// ============================================================
// Virtual Stage Director — Raffle Registration
// Database Configuration
//
// SETUP INSTRUCTIONS:
//   1. Copy config.php.example → config.php in this directory.
//   2. Fill in your Hostinger DB credentials and admin password.
//   3. Create a MySQL database in your Hostinger control panel.
//   4. Create a database user with SELECT, INSERT, DELETE privileges.
//   5. Upload all files in this folder to your server.
//      config.php is gitignored and must never be committed.
// ============================================================

// Credentials live in config.php (gitignored, not in source control).
require_once __DIR__ . '/config.php';

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }
    return $pdo;
}

function initDB() {
    $pdo = getDB();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS registrations (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            name         VARCHAR(255) NOT NULL,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_config (
            id               INT PRIMARY KEY DEFAULT 1,
            is_open          TINYINT(1)   DEFAULT 0,
            org_name         VARCHAR(255) DEFAULT '',
            event_name       VARCHAR(255) DEFAULT '',
            logo_url         TEXT         DEFAULT '',
            color_primary    VARCHAR(7)   DEFAULT '#432818',
            color_secondary  VARCHAR(7)   DEFAULT '#345830',
            color_accent     VARCHAR(7)   DEFAULT '#ffe6a7',
            color_highlight  VARCHAR(7)   DEFAULT '#941b0c',
            confirmation_msg VARCHAR(500) DEFAULT 'Congratulations! You''re Entered to Win!',
            opened_at        TIMESTAMP    NULL,
            closed_at        TIMESTAMP    NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Ensure the single config row exists
    $pdo->exec("INSERT IGNORE INTO event_config (id) VALUES (1)");
}
?>
