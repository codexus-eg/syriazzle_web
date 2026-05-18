<?php
// ========================================================================
// Syriazzle - Accept Task API (Exclusive Dispatching Compatible - V6.1)
// ========================================================================

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/NotificationManager.php'; 

header('Content-Type: application/json; charset=utf-8');

// 1. التحقق الأمني من الجلسة
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['driver_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'انتهت الجلسة، يرجى إعادة تسجيل الدخول.']);
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$estimated_time = isset($_POST['estimated_time']) ? (int)$_POST['estimated_time'] : 15;

if ($order_id === 0) {
    echo json_encode(['success' => false, 'message' => 'بيانات الطلب غير مكتملة.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. التحقق من أهلية السائق (Approved & Not Blocked)
    $stmt_drv = $pdo->prepare("SELECT status FROM drivers WHERE id = ? FOR UPDATE");
    $stmt_drv->execute([$driver_id]);
    if ($stmt_drv->fetchColumn() !== 'approved') {
        throw new Exception("حسابك غير نشط حالياً، لا يمكنك قبول طلبات.");
    }

    // 3. منع قبول أكثر من طلب نشط في نفس الوقت
    $stmt_busy = $pdo->prepare("SELECT id FROM orders WHERE driver_id = ? AND status IN ('accepted', 'picked_up', 'out_for_delivery') LIMIT 1");
    $stmt_busy->execute([$driver_id]);
    if ($stmt_busy->fetch()) {
        throw new Exception("لديك طلب نشط بالفعل، يرجى إنهاؤه أولاً.");
    }

    // 4. التحقق من "العرض الحصري" (Exclusive Offer Verification)
    // نتحقق أن السائق لديه عرض لهذا الطلب تحديداً ولم تنتهِ صلاحيته
    $stmt_check_offer = $pdo->prepare("
        SELECT id FROM order_offers 
        WHERE order_id = ? AND driver_id = ? AND status = 'pending' AND expires_at > NOW() 
        FOR UPDATE
    ");
    $stmt_check_offer->execute([$order_id, $driver_id]);
    if (!$stmt_check_offer->fetch()) {
        throw new Exception("عذراً، انتهى الوقت المخصص لك لقبول هذا الطلب.");
    }

    // 5. محاولة قفل الطلب الأصلي (لضمان بقائه متاحاً)
    $stmt_order = $pdo->prepare("
        SELECT id, user_id, business_id 
        FROM orders 
        WHERE id = ? AND driver_id IS NULL AND status IN ('ready_for_pickup', 'pending_driver') 
        FOR UPDATE
    ");
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("عذراً، الطلب لم يعد متاحاً أو تم سحبه.");
    }

    // 6. التنفيذ: تحديث الطلب + تحديث العرض + تحديث حالة السائق
    
    // أ- إسناد الطلب للسائق
    $stmt_upd_order = $pdo->prepare("UPDATE orders SET driver_id = ?, status = 'accepted', updated_at = NOW() WHERE id = ?");
    $stmt_upd_order->execute([$driver_id, $order_id]);

    // ب- إغلاق العرض في جدول order_offers بنجاح
    $stmt_upd_offer = $pdo->prepare("UPDATE order_offers SET status = 'accepted' WHERE order_id = ? AND driver_id = ?");
    $stmt_upd_offer->execute([$order_id, $driver_id]);

    // ج- جعل السائق غير متاح (Busy) لاستقبال عروض أخرى حتى ينهي هذا الطلب
    $pdo->prepare("UPDATE drivers SET is_available = 0 WHERE id = ?")->execute([$driver_id]);

    $pdo->commit();

    // 7. نظام الإشعارات (تنبيه الزبون والمتجر)
    try {
        if (class_exists('NotificationManager')) {
            // إشعار الزبون
            NotificationManager::sendNotification(
                (int)$order['user_id'], 
                'user', 
                "كابتن التوصيل في الطريق! 🛵", 
                "تم قبول طلبك، سيصل الكابتن للمتجر خلال $estimated_time دقيقة لاستلامه.",
                "track_order.php?order_id=$order_id"
            );

            // إشعار المتجر
            NotificationManager::sendNotification(
                (int)$order['business_id'], 
                'business', 
                "سائق قادم لاستلام طلبك", 
                "تم قبول الطلب #$order_id، سيصل السائق إليك خلال $estimated_time دقيقة."
            );
        }
    } catch (Throwable $e) {
        error_log("FCM Notification Error (Accept): " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'تم قبول المهمة! يرجى التوجه للمتجر.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $t) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Critical System Error (Accept): " . $t->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ تقني في معالجة القبول.']);
}