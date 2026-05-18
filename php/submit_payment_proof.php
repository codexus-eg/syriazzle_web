<?php
// ========================================================================
// Syriazzle Bookings - محرك إثبات الدفع وتنبيه الأدمن (النسخة 3.0)
// ========================================================================
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) { header('Location: ../index.html'); exit; }
$current_user_id = (int)$_SESSION['user_id'];
$booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$transaction_id = trim($_POST['transaction_id'] ?? '');

if (empty($booking_id) || empty($transaction_id)) { die("خطأ: بيانات غير مكتملة."); }

$pdo->beginTransaction();
try {
    // --- التحقق من أن الحجز موجود، يخص المستخدم، وفي الحالة الصحيحة ---
    $stmt = $pdo->prepare("SELECT id, status FROM bookings WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$booking_id, $current_user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) { throw new Exception("الحجز غير موجود أو لا يخصك."); }
    if ($booking['status'] !== 'pending_payment') {
        header("Location: ../booking_success.php?booking_id=$booking_id&status=already_processed"); exit;
    }

    // --- تحديث الحجز وتخزين إثبات الدفع ---
    $update_stmt = $pdo->prepare("UPDATE bookings SET status = 'pending_confirmation', payment_status = 'in_review', cancellation_reason = ? WHERE id = ?");
    $proof_text = "إثبات الدفع من المستخدم: " . htmlspecialchars($transaction_id);
    $update_stmt->execute([$proof_text, $booking_id]);
    
    // --- إنشاء إشعار داخلي للآدمن ---
    $notification_message = "حجز جديد (#{$booking_id}) ينتظر تأكيد استلام العربون من المنصة.";
    $notification_link = "manage_bookings.php?filter=pending_confirmation";
    
    $notify_stmt = $pdo->prepare("INSERT INTO admin_notifications (message, link, is_read) VALUES (?, ?, 0)");
    $notify_stmt->execute([$notification_message, $notification_link]);
    
    $pdo->commit();

    header("Location: ../booking_success.php?booking_id=$booking_id&status=pending_confirmation");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Submit Payment Proof Error: " . $e->getMessage());
    $_SESSION['booking_error_message'] = "حدث خطأ فني أثناء إرسال إثبات الدفع. يرجى التواصل مع الدعم الفني.";
    header("Location: ../booking_error.php");
    exit;
}
?>