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
// 🔧 Database Configuration
// ==========================================
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307'); // ⚠️ Confirm MySQL port in XAMPP or production
define('DB_NAME', 'picklehub');
define('DB_USER', 'root');
define('DB_PASS', '');

// ==========================================
// ⚙️ PDO Connection Options
// ==========================================
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // Throw exceptions on SQL errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return associative arrays
    PDO::ATTR_EMULATE_PREPARES => false,              // Use real prepared statements
    PDO::ATTR_PERSISTENT => false                     // Set true for long-lived connections if needed
];

try {
    // ======================================
    // ✅ Create PDO Connection
    // ======================================
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        $options
    );

    // Alias for backward compatibility
    $conn = $pdo;

    // Set consistent timezone
    $pdo->exec("SET time_zone = '+05:30'");
    date_default_timezone_set('Asia/Kolkata');

    // Enable error reporting during development
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

} catch (PDOException $e) {
    // ======================================
    // ❌ Connection Failure Handling
    // ======================================
    $error_message = "❌ Database connection failed: " . $e->getMessage();
    
    // Log error (helpful for production)
    error_log($error_message);
    
    // Show friendly message for users
    if (ini_get('display_errors')) {
        die("<pre style='color:red; font-family:monospace;'>$error_message</pre>");
    } else {
        die("Database connection failed. Please try again later.");
    }
}
?>
