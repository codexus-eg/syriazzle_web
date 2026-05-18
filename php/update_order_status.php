<?php
// ========================================================================
// Syriazzle - Update Order Status (Merchant Portal - Reliability Version)
// ========================================================================

// 1. إعدادات البيئة
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/NotificationManager.php';

// منع أي مخرجات نصية قبل الـ JSON لضمان عدم حدوث خطأ في الـ Fetch
ob_start();
header('Content-Type: application/json; charset=utf-8');

// 2. التحقق من طريقة الطلب والجلسة
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مدعومة.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'انتهت الجلسة، يرجى تسجيل الدخول.']);
    exit;
}

// التحقق من رمز CSRF لضمان أمان العملية
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'خطأ أمني: رمز الحماية غير صالح.']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$new_status = trim($_POST['new_status'] ?? '');
$cancel_reason = trim($_POST['cancellation_reason'] ?? '');

// الحالات المسموح للتاجر بتغييرها
$merchant_allowed = ['preparing', 'ready_for_pickup', 'canceled'];

if ($order_id === 0 || !in_array($new_status, $merchant_allowed)) {
    echo json_encode(['success' => false, 'message' => 'بيانات الطلب أو الحالة غير صالحة.']);
    exit;
}

try {
    // 3. التحقق من الملكية والقفل البرمجي (FOR UPDATE) لمنع تضارب البيانات
    $stmt_check = $pdo->prepare("
        SELECT 
            o.status as current_status, 
            o.user_id as customer_id, 
            o.driver_id,
            b.name as business_name 
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        WHERE o.id = ? AND b.user_id = ?
        FOR UPDATE
    ");
    $stmt_check->execute([$order_id, $current_user_id]);
    $order_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$order_data) {
        throw new Exception("الطلب غير موجود أو لا تملك صلاحية تعديله.");
    }

    // 4. تطبيق قواعد العمل الصارمة
    // منع الإلغاء إذا كان السائق قد قبل المهمة (حماية للسائق من ضياع وقته)
    if ($new_status === 'canceled' && !is_null($order_data['driver_id'])) {
        throw new Exception("عذراً، لا يمكن إلغاء الطلب حالياً لأن الكابتن وافق على توصيله.");
    }

    // 5. تنفيذ التحديث في قاعدة البيانات أولاً (العملية الأهم)
    $pdo->beginTransaction();

    $sql_upd = "UPDATE orders SET status = ?, updated_at = NOW()";
    $params = [$new_status];

    if ($new_status === 'canceled') {
        $sql_upd .= ", cancellation_reason = ?";
        $params[] = $cancel_reason;
    }

    $sql_upd .= " WHERE id = ?";
    $params[] = $order_id;

    $stmt_exec = $pdo->prepare($sql_upd);
    $stmt_exec->execute($params);

    $pdo->commit();

    // 6. إرسال الرد للتاجر فوراً (اختياري، لكن هنا سنكمل للإشعارات)
    // لإرسال الإشعارات دون تعطيل التاجر، سنقوم بالعملية في Try-Catch معزول
    
    $notif_title = "";
    $notif_body = "";
    
    switch ($new_status) {
        case 'preparing':
            $notif_title = "بدأ التحضير 👨‍🍳";
            $notif_body = "المتجر بدأ بتحضير طلبك من {$order_data['business_name']}.";
            break;
            
        case 'ready_for_pickup':
            $notif_title = "طلبك جاهز ✅";
            $notif_body = "انتهى المتجر من تجهيز طلبك، نحن نبحث عن كابتن لتوصيله إليك الآن.";
            break;
            
        case 'canceled':
            $notif_title = "تم إلغاء الطلب ❌";
            $notif_body = "قام المتجر بإلغاء طلبك." . ($cancel_reason ? " السبب: $cancel_reason" : "");
            break;
    }

    if (!empty($notif_title)) {
        try {
            // استخدام الإشعار الموحد (الخارجي والداخلي)
            NotificationManager::sendNotification(
                (int)$order_data['customer_id'], 
                'user', 
                $notif_title, 
                $notif_body, 
                "track_order.php?order_id=$order_id"
            );
        } catch (Throwable $e_notif) {
            // تسجيل الخطأ في السيرفر فقط لكي لا يراه المستخدم
            error_log("FCM Notification Delay/Error: " . $e_notif->getMessage());
        }
    }

    // النجاح النهائي
    ob_end_clean();
    echo json_encode([
        'success' => true, 
        'message' => 'تم تحديث حالة الطلب وإشعار الزبون.'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

exit;