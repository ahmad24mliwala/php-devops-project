<?php
require __DIR__ . '/includes/db.php';
echo "<h2>✅ Connected Successfully to Database: " . DB_NAME . "</h2>";

$stmt = $pdo->query("SHOW TABLES");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
echo "</pre>";
?>
