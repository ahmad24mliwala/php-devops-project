<?php
/**
 * Database Connection File
 * Supports both $pdo and $conn variables
 * Ensures UTF-8 charset, secure PDO config, and consistent timezone
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// 🔧 Database Configuration (Hostinger)
// ==========================================
define('DB_HOST', 'localhost'); 
define('DB_PORT', '3306');
define('DB_NAME', 'u304855427_picklehub');
define('DB_USER', 'u304855427_avojifoods');
define('DB_PASS', 'Avojifoods@1');   // <-- If connection fails, reset password from Hostinger

// ==========================================
// ⚙️ PDO Connection Options
// ==========================================
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false
];

try {
    // ======================================
    // ✅ Create PDO Connection
    // ======================================
    $pdo = new PDO(
        "mysql:host=" . DB_HOST .
        ";port=" . DB_PORT .
        ";dbname=" . DB_NAME .
        ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $options
    );

    // Alias
    $conn = $pdo;

    // Set timezone
    $pdo->exec("SET time_zone = '+05:30'");
    date_default_timezone_set('Asia/Kolkata');

    // Debugging Enabled (Safe)
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

} catch (PDOException $e) {

    // ======================================
    // ❌ Connection Failure Handling
    // ======================================
    $error_message = "❌ Database connection failed: " . $e->getMessage();

    error_log($error_message);

    die("<pre style='color:red; font-family:monospace;'>$error_message</pre>");
}
?>
