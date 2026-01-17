<?php
// Run this file on the server (php create_admin_hash.php) to print a bcrypt hash for Admin123
$password = 'Admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Admin password hash (use this in setup.sql):\n";
echo $hash . "\n";
?>