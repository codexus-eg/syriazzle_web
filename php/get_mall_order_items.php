<?php
require_once 'db_connect.php';
header('Content-Type: application/json');
if(!isset($_SESSION['user_id'])) exit;

$id = (int)$_GET['order_id'];
$stmt = $pdo->prepare("SELECT product_name as item_name, quantity, price_per_item FROM mall_order_items WHERE mall_order_id = ?");
$stmt->execute([$id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>