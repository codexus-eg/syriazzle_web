<?php
// ========================================================================
// Syriazzle Admin - Order Management API (Full Notification Integration)
// ========================================================================

// 1. استدعاء الملفات الأساسية وحارس البوابة
require_once '../../php/db_connect.php'; // العودة لمجلد php الرئيسي للاتصال
require_once '../../php/NotificationManager.php'; // نظام الإشعارات الموحد
require_once '../auth_guard.php'; // التحقق من جلسة الأدمن وصلاحياته

header('Content-Type: application/json; charset=utf-8');

// 2. حارس الصلاحيات: هل هذا الأدمن مسموح له بتعديل الطلبات؟
if (!hasPermission('edit_order')) {
    echo json_encode(['success' => false, 'message' => 'عذراً، لا تملك صلاحية تعديل الطلبات.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مدعومة.']);
    exit;
}

// استقبال البيانات الأساسية
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$order_id || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'بيانات الطلب غير مكتملة.']);
    exit;
}

try {
    // 3. التحقق من وجود الطلب وجلب بيانات الزبون والمتجر لإرسال الإشعارات
    $stmt_order = $pdo->prepare("
        SELECT o.status, o.user_id as customer_id, o.driver_id, b.name as business_name 
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        WHERE o.id = ?
    ");
    $stmt_order->execute([$order_id]);
    $order_info = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order_info) {
        throw new Exception("الطلب غير موجود في القاعدة.");
    }

    // --- معالجة الإجراءات ---

    if ($action === 'change_status') {
        // أ. تغيير حالة الطلب يدوياً من قبل الأدمن
        $new_status = $_POST['new_status'] ?? '';
        $allowed_statuses = ['pending_approval', 'preparing', 'ready_for_pickup', 'accepted', 'picked_up', 'delivered', 'canceled'];

        if (!in_array($new_status, $allowed_statuses)) {
            throw new Exception("الحالة المختارة غير صالحة.");
        }

        $pdo->beginTransaction();
        
        $stmt_upd = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt_upd->execute([$new_status, $order_id]);

        $pdo->commit();

        // إشعار للزبون بتحديث الحالة من الإدارة
        $status_text_ar = [
            'preparing' => 'قيد التحضير 👨‍🍳',
            'ready_for_pickup' => 'جاهز للاستلام ✅',
            'delivered' => 'تم التوصيل بنجاح 🎉',
            'canceled' => 'تم الإلغاء من الإدارة ❌'
        ];
        
        if (isset($status_text_ar[$new_status])) {
            NotificationManager::sendNotification(
                (int)$order_info['customer_id'],
                'user',
                "تحديث من الإدارة: " . $status_text_ar[$new_status],
                "تم تحديث حالة طلبك من {$order_info['business_name']} إلى {$status_text_ar[$new_status]}",
                "track_order.php?order_id=$order_id"
            );
        }

        $res_message = "تم تحديث الحالة بنجاح.";

    } elseif ($action === 'assign_driver') {
        // ب. تعيين سائق يدوياً (Manual Dispatch)
        $driver_id = filter_input(INPUT_POST, 'driver_id', FILTER_VALIDATE_INT);
        
        if (!$driver_id) {
            throw new Exception("يرجى اختيار سائق بشكل صحيح.");
        }

        // التحقق من حالة السائق المختقير
        $stmt_drv = $pdo->prepare("SELECT full_name, status, is_available FROM drivers WHERE id = ?");
        $stmt_drv->execute([$driver_id]);
        $driver_data = $stmt_drv->fetch(PDO::FETCH_ASSOC);

        if (!$driver_data || $driver_data['status'] !== 'approved') {
            throw new Exception("هذا السائق غير متاح أو حسابه غير مفعل.");
        }

        $pdo->beginTransaction();

        // 1. ربط السائق بالطلب وتغيير الحالة إلى 'accepted' فوراً
        $stmt_assign = $pdo->prepare("
            UPDATE orders 
            SET driver_id = ?, status = 'accepted', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt_assign->execute([$driver_id, $order_id]);

        // 2. تحديث حالة السائق ليصبح مشغولاً (خارج الخدمة لغيره)
        $stmt_busy = $pdo->prepare("UPDATE drivers SET is_available = 0 WHERE id = ?");
        $stmt_busy->execute([$driver_id]);

        $pdo->commit();

        // --- سلسلة الإشعارات عند التعيين اليدوي ---

        // 1. إشعار فوري للسائق (بصوت التنبيه القوي)
        NotificationManager::sendNotification(
            $driver_id,
            'driver',
            "مهمة جديدة مسندة! 🛵",
            "قام الأدمن بتعيينك لتوصيل الطلب #$order_id. يرجى التوجه للمتجر حالاً.",
            "driver_dashboard.php"
        );

        // 2. إشعار للزبون
        NotificationManager::sendNotification(
            (int)$order_info['customer_id'],
            'user',
            "تم تعيين كابتن لطلبك 🛰️",
            "الكابتن {$driver_data['full_name']} في طريقه لاستلام طلبك من {$order_info['business_name']}.",
            "track_order.php?order_id=$order_id"
        );

        $res_message = "تم تعيين السائق {$driver_data['full_name']} بنجاح وإشعاره بالمهمة.";

    } else {
        throw new Exception("إجراء الإدارة غير معروف.");
    }

    echo json_encode(['success' => true, 'message' => $res_message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Admin Update Order Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}