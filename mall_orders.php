<?php
require_once 'php/db_connect.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect_url=mall_orders.php');
    exit;
}
$page_title = 'طلباتي من المول - Syriazzle';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/mall_orders.css"> <!-- ملف تصميم جديد -->
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="orders-container">
        <h1><i class="fas fa-receipt"></i> طلباتي من المول</h1>
        <div class="tabs">
            <button class="tab-btn active" data-status="active">الطلبات الحالية</button>
            <button class="tab-btn" data-status="completed">الطلبات المكتملة</button>
        </div>
        <div id="orders-list-container">
            <div class="loader">جاري تحميل الطلبات...</div>
        </div>
    </div>
    <script src="js/mall_orders.js"></script> <!-- ملف جافاسكريبت جديد -->
</body>
</html>