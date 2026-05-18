<?php
// ========================================================================
// Syriazzle Admin - محرك إلغاء الحجز الإداري (النسخة 2.1 - مع الإشعارات)
// ========================================================================
require_once '../auth_guard.php';

// --- إعدادات الأمان الأساسية ---
header('Content-Type: application/json; charset=UTF-8');

// --- حارس الصلاحيات: يجب أن يملك صلاحية تعديل حالة الحجز ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hasPermission('edit_booking_status')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'وصول غير مصرح به.']);
    exit;
}

// --- استقبال البيانات والتحقق منها ---
$booking_id = (int)($_POST['booking_id'] ?? 0);
$cancellation_reason = trim($_POST['reason'] ?? '');

if ($booking_id === 0 || empty($cancellation_reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات المرسلة غير مكتملة. سبب الإلغاء إلزامي.']);
    exit;
}

$pdo->beginTransaction();
try {
    // --- 1. جلب بيانات الحجز والنشاط مع قفل الصف (مع إضافة user_id) ---
    $stmt = $pdo->prepare("
        SELECT b.status, b.user_id, biz.id as business_id
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        JOIN businesses biz ON s.business_id = biz.id
        WHERE b.id = ? FOR UPDATE
    ");
    $stmt->execute([$booking_id]);
    $booking_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- 2. التحقق من أن الحجز في حالة تسمح بالإلغاء ---
    if (!$booking_info) {
        throw new Exception("الحجز غير موجود.");
    }
    if ($booking_info['status'] !== 'confirmed') {
        throw new Exception("يمكن فقط إلغاء الحجوزات المؤكدة. هذا الحجز حالته: " . $booking_info['status']);
    }
    
    // تخزين ID المستخدم لإرسال الإشعار لاحقًا
    $customer_user_id = $booking_info['user_id'];

    // --- 3. تحديث حالة الحجز ---
    $update_booking_stmt = $pdo->prepare(
        "UPDATE bookings SET status = 'cancelled_by_admin', cancellation_reason = ? WHERE id = ?"
    );
    $update_booking_stmt->execute(["تم الإلغاء من قبل الإدارة. السبب: " . htmlspecialchars($cancellation_reason), $booking_id]);

    // --- 4. عكس المعاملات المالية (المنطق الكامل) ---
    $original_transactions_stmt = $pdo->prepare("SELECT transaction_type, amount FROM transactions WHERE order_id = ?");
    $original_transactions_stmt->execute([$booking_id]);
    $original_transactions = $original_transactions_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_commission_to_refund = 0;
    $total_payout_due_to_refund = 0;

    foreach ($original_transactions as $tx) {
        $reversed_amount = -$tx['amount'];
        $new_tx_type = $tx['transaction_type'] . '_refund';
        $refund_desc = "عكس معاملة ({$tx['transaction_type']}) للحجز الملغي #{$booking_id}";

        $refund_stmt = $pdo->prepare(
            "INSERT INTO transactions (order_id, user_id, user_type, transaction_type, amount, description, created_at) 
             VALUES (?, ?, 'business', ?, ?, ?, NOW())"
        );
        $refund_stmt->execute([$booking_id, $booking_info['business_id'], $new_tx_type, $reversed_amount, $refund_desc]);

        if ($tx['transaction_type'] === 'commission') {
            $total_commission_to_refund += $reversed_amount;
        } elseif ($tx['transaction_type'] === 'payout_due') {
            $total_payout_due_to_refund += $reversed_amount;
        }
    }

    if ($total_commission_to_refund != 0 || $total_payout_due_to_refund != 0) {
        $update_balance_stmt = $pdo->prepare(
            "UPDATE businesses SET commission_balance = commission_balance + ?, payouts_balance = payouts_balance + ? WHERE id = ?"
        );
        $update_balance_stmt->execute([$total_commission_to_refund, $total_payout_due_to_refund, $booking_info['business_id']]);
    }
    
    // --- 5. إنهاء المعاملة ---
    $pdo->commit();

    // **الإضافة النهائية هنا: إرسال الإشعار بعد نجاح كل شيء**
    if (isset($customer_user_id)) {
        require_once __DIR__ . '/../../php/NotificationManager.php';
        NotificationManager::sendNotification(
            (int)$customer_user_id,
            "❌ تم إلغاء حجزك",
            "نعتذر، لقد تم إلغاء حجزك رقم #{$booking_id} من قبل الإدارة. السبب: " . htmlspecialchars($cancellation_reason),
            "my_bookings.php"
        );
    }
    
    echo json_encode(['success' => true, 'message' => 'تم إلغاء الحجز بنجاح وعكس جميع المعاملات المالية المرتبطة.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Admin Booking Cancellation Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>