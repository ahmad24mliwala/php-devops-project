<?php
require '../includes/db.php';
header('Content-Type: application/json');

$data = [];
$data['total_orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('completed','cancelled') AND DATE(created_at)=CURDATE()")->fetchColumn();
$data['total_revenue'] = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('completed','cancelled') AND DATE(created_at)=CURDATE()")->fetchColumn() ?: 0;
$data['total_products'] = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$data['total_customers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$data['total_visits'] = $pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visited_at)=CURDATE()")->fetchColumn();

$revenue=$orders=$visits=[];
for ($i=6;$i>=0;$i--){
  $d=date('Y-m-d',strtotime("-$i days"));
  $revenue[]=$pdo->query("SELECT SUM(total_amount) FROM orders WHERE DATE(created_at)='$d' AND status NOT IN ('completed','cancelled')")->fetchColumn() ?: 0;
  $orders[]=$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)='$d' AND status NOT IN ('completed','cancelled')")->fetchColumn() ?: 0;
  $visits[]=$pdo->query("SELECT COUNT(*) FROM visits WHERE DATE(visited_at)='$d'")->fetchColumn() ?: 0;
}
$data['revenue']=$revenue;
$data['orders']=$orders;
$data['visits']=$visits;
echo json_encode($data);
