<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', getenv('DB_HOST') ?: 'mysql-db');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'picklehub');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST .
        ";port=" . DB_PORT .
        ";dbname=" . DB_NAME .
        ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $options
    );

    $conn = $pdo;

    $pdo->exec("SET time_zone = '+05:30'");
    date_default_timezone_set('Asia/Kolkata');

} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

