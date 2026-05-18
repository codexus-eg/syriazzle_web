<?php
require_once 'php/db_connect.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$current_user_id = (int)$_SESSION['user_id'];
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$status_from_url = $_GET['status'] ?? '';

try {
    $stmt = $pdo->prepare("SELECT b.id, b.start_datetime, b.total_price, b.status, s.name as service_name, biz.name as business_name FROM bookings b JOIN business_services s ON b.service_id = s.id JOIN businesses biz ON s.business_id = biz.id WHERE b.id = ? AND b.user_id = ?");
    $stmt->execute([$booking_id, $current_user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) { header('Location: index.html'); exit; }

    switch ($booking['status']) {
        case 'confirmed':
            $page_title = 'تم تأكيد حجزك بنجاح!';
            $icon = 'fa-check-circle';
            $icon_color = '#198754';
            $main_message = 'تهانينا! تم تأكيد حجزك بنجاح.';
            $sub_message = 'لقد أرسلنا تفاصيل الحجز إلى بريدك الإلكتروني. يمكنك أيضًا عرض كل حجوزاتك في صفحة حسابك.';
            break;
        case 'pending_confirmation':
            $page_title = 'بانتظار تأكيد الدفع';
            $icon = 'fa-hourglass-half';
            $icon_color = '#0d6efd';
            $main_message = 'تم استلام طلبك بنجاح!';
            $sub_message = 'سيقوم فريقنا بمراجعة إثبات الدفع وتأكيد حجزك في أقرب وقت ممكن. ستتلقى إشعارًا عند اكتمال المراجعة.';
            break;
        default:
             $page_title = 'حالة الحجز';
             $icon = 'fa-info-circle';
             $icon_color = '#6c757d';
             $main_message = 'تفاصيل حجزك';
             $sub_message = 'هذه هي التفاصيل الحالية لحجزك رقم #' . $booking_id;
    }
} catch (PDOException $e) { die("حدث خطأ أثناء تحميل تفاصيل حجزك."); }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #f8f9fa; text-align: center; }
        .success-container { max-width: 600px; margin: 4rem auto; padding: 3rem; background: #fff; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .success-icon { font-size: 5rem; line-height: 1; margin-bottom: 1.5rem; animation: popIn 0.5s ease-out; }
        @keyframes popIn { 0% { transform: scale(0.5); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        h1 { font-size: 2rem; margin-bottom: 1rem; }
        .lead { font-size: 1.1rem; color: #6c757d; margin-bottom: 2rem; }
        .booking-details { text-align: right; border: 1px solid #dee2e6; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem; }
        .detail-item { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #f1f1f1; }
        .detail-item:last-child { border-bottom: none; }
        .actions { display: flex; justify-content: center; gap: 1rem; margin-top: 2rem; }
        .btn { padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; transition: all 0.2s; }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-secondary { background: #e9ecef; color: #212529; }
        .btn-secondary{
        }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="success-container">
        <div class="success-icon" style="color: <?php echo $icon_color; ?>;"><i class="fas <?php echo $icon; ?>"></i></div>
        <h1><?php echo $main_message; ?></h1>
        <p class="lead"><?php echo $sub_message; ?></p>
        <div class="booking-details">
            <div class="detail-item"><span>رقم الحجز:</span> <strong>#<?php echo htmlspecialchars($booking['id']); ?></strong></div>
            <div class="detail-item"><span>النشاط التجاري:</span> <strong><?php echo htmlspecialchars($booking['business_name']); ?></strong></div>
            <div class="detail-item"><span>الخدمة:</span> <strong><?php echo htmlspecialchars($booking['service_name']); ?></strong></div>
            <div class="detail-item"><span>موعد الحجز:</span> <strong><?php echo date('Y-m-d \ا\ل\س\ا\ع\ة H:i', strtotime($booking['start_datetime'])); ?></strong></div>
            <div class="detail-item"><span>الإجمالي:</span> <strong><?php echo number_format($booking['total_price']); ?> ل.س</strong></div>
        </div>
        <div class="actions">
            <a href="my_bookings.php" class="btn btn-primary">عرض كل حجوزاتي</a>
            <a href="index.html" class="btn btn-secondary">العودة للرئيسية</a>
        </div>
    </div>
</body>
</html>