<?php
require '../includes/db.php';
$id=intval($_POST['id']);
$pdo->exec("UPDATE products SET price_enabled=1-price_enabled WHERE id=$id");
echo json_encode(['status'=>'success']);
