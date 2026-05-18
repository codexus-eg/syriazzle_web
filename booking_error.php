<?php
session_start();
$error_message = $_SESSION['booking_error_message'] ?? 'حدث خطأ غير معروف.';
unset($_SESSION['booking_error_message']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <title>خطأ في الحجز</title>
    <link rel="stylesheet" href="css/main_header.css">
    <style>body{font-family: Cairo, sans-serif; text-align: center; padding: 50px;} .error-box{border: 1px solid #dc3545; color: #dc3545; background: #f8d7da; padding: 20px; border-radius: 8px; max-width: 600px; margin: auto;}</style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="error-box">
        <h2>عذرًا، حدث خطأ أثناء معالجة حجزك</h2>
        <p><?php echo htmlspecialchars($error_message); ?></p>
        <a href="index.html">العودة إلى الصفحة الرئيسية</a>
    </div>
</body>
</html>