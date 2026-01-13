<?php
require '../includes/db.php';
$id=intval($_POST['id']);
$pdo->exec("UPDATE products SET is_cart_enabled=1-is_cart_enabled WHERE id=$id");
echo json_encode(['status'=>'success']);
