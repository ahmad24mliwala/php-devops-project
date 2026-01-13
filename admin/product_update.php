<?php
require '../includes/db.php';
$id = intval($_POST['id']);
$name = trim($_POST['name']);
$price = floatval($_POST['price']);
$weight = trim($_POST['weight']);
$imgPart = "";
if(!empty($_FILES['image']['tmp_name'])){
  $imgName = time().'_'.basename($_FILES['image']['name']);
  move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/".$imgName);
  $imgPart = ", image='$imgName'";
}
$stmt = $pdo->prepare("UPDATE products SET name=?, price=?, weight=? $imgPart WHERE id=?");
$stmt->execute([$name,$price,$weight,$id]);
echo json_encode(['status'=>'success']);
