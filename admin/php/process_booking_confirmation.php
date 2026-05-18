<?php
// ========================================================================
// Syriazzle Admin - معالج تأكيد/رفض الحجز (النسخة 3.1 - نهائية مع الإشعارات)
// ========================================================================
require_once '../auth_guard.php';

// --- إعدادات الأمان الأساسية ---
header('Content-Type: application/json; charset=UTF-8');

// --- حارس الصلاحيات والأمان ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hasPermission('confirm_booking_payment')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'وصول غير مصرح به.']);
    exit;
}

// --- استقبال البيانات ---
$booking_id = (int)($_POST['booking_id'] ?? 0);
$action = $_POST['action'] ?? '';
$rejection_reason = trim($_POST['rejection_reason'] ?? '');

if (empty($booking_id) || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات الطلب غير مكتملة.']);
    exit;
}
if ($action === 'reject' && empty($rejection_reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'سبب الرفض إلزامي.']);
    exit;
}

// --- بدء المعاملة لضمان سلامة البيانات ---
$pdo->beginTransaction();
try {
    // الاستعلام المطور لجلب كل البيانات المطلوبة في مرة واحدة
    $stmt = $pdo->prepare("
        SELECT 
            b.status, b.total_price, b.deposit_amount, b.user_id,
            biz.id as business_id, biz.booking_commission_rate, biz.commission_rate, biz.booking_category
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        JOIN businesses biz ON s.business_id = biz.id
        WHERE b.id = ? FOR UPDATE
    ");
    $stmt->execute([$booking_id]);
    $booking_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking_info) {
        throw new Exception("الحجز رقم #{$booking_id} غير موجود.");
    }
    if ($booking_info['status'] !== 'pending_confirmation') {
        throw new Exception("هذا الإجراء تم تنفيذه مسبقاً على هذا الحجز أو أن حالته لا تسمح بذلك.");
    }
    
    $customer_user_id = $booking_info['user_id'];
    $notification_title = '';
    $notification_body = '';
    $notification_link = "my_bookings.php";

    if ($action === 'approve') {
        $update_booking = $pdo->prepare("UPDATE bookings SET status = 'confirmed', payment_status = 'paid', cancellation_reason = 'تم تأكيد الدفع من قبل الإدارة.' WHERE id = ?");
        $update_booking->execute([$booking_id]);
        
        $commission_rate = (float)$booking_info['booking_commission_rate'];
        $commission_amount = round(((float)$booking_info['total_price'] * $commission_rate) / 100);
        $payout_due_amount = (float)$booking_info['deposit_amount'];

        $trans_stmt_comm = $pdo->prepare("INSERT INTO transactions (order_id, user_id, user_type, transaction_type, amount, description, created_at) VALUES (?, ?, 'business', 'commission', ?, ?, NOW())");
        $trans_stmt_comm->execute([$booking_id, $booking_info['business_id'], -$commission_amount, "عمولة المنصة على الحجز المؤكد #{$booking_id}"]);
        
        $trans_stmt_payout = $pdo->prepare("INSERT INTO transactions (order_id, user_id, user_type, transaction_type, amount, description, created_at) VALUES (?, ?, 'business', 'payout_due', ?, ?, NOW())");
        $trans_stmt_payout->execute([$booking_id, $booking_info['business_id'], $payout_due_amount, "استحقاق عربون الحجز المؤكد #{$booking_id}"]);
        
        $update_balance_stmt = $pdo->prepare("UPDATE businesses SET commission_balance = commission_balance - ?, payouts_balance = payouts_balance + ? WHERE id = ?");
        $update_balance_stmt->execute([$commission_amount, $payout_due_amount, $booking_info['business_id']]);

        $message = "تم تأكيد الحجز بنجاح وتسجيل المعاملات المالية.";
        $notification_title = "✅ تم تأكيد حجزك!";
        $notification_body = "تهانينا! لقد تم تأكيد حجزك رقم #{$booking_id}. يمكنك الآن عرض التفاصيل.";

    } else { // ($action === 'reject')
        $update_booking = $pdo->prepare("UPDATE bookings SET status = 'cancelled_by_admin', payment_status = 'rejected', cancellation_reason = ? WHERE id = ?");
        $update_booking->execute(["تم رفض إثبات الدفع من قبل الإدارة. السبب: " . htmlspecialchars($rejection_reason), $booking_id]);
        
        $message = "تم رفض الحجز بنجاح.";
        $notification_title = "⚠️ تم رفض دفعتك";
        $notification_body = "نعتذر، لم يتم قبول إثبات الدفع الخاص بحجزك رقم #{$booking_id} للسبب التالي: " . htmlspecialchars($rejection_reason);
    }

    $pdo->commit();
    
    // **إرسال الإشعار بعد نجاح كل شيء (خارج الـ transaction)**
    if (!empty($notification_title) && !empty($customer_user_id)) {
        // تضمين محرك الإشعارات
        require_once __DIR__ . '/../../php/NotificationManager.php';
        
        // استدعاء دالة الإرسال
        NotificationManager::sendNotification(
            (int)$customer_user_id,
            $notification_title,
            $notification_body,
            $notification_link
        );
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Booking Confirmation Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>