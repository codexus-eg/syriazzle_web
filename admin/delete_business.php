<?php
// --- الخطوة 1: تضمين العقل المدبر (header.php) ---
// هذا السطر يحل محل session_start() و db_connect.php ويفعل حارس البوابة المتقدم تلقائياً.
require_once 'auth_guard.php';

// --- الخطوة 2: تطبيق حارس بوابة الصلاحيات ---
// التحقق من أن الموظف يملك صلاحية "حذف متجر"
if (!hasPermission('delete_business')) {
    // إذا لم يكن لديه الصلاحية، قم بتسجيل رسالة خطأ وإعادة التوجيه.
    $_SESSION['admin_message'] = "ليس لديك الصلاحية اللازمة لحذف المتاجر.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: dashboard.php');
    exit;
}

// --- الخطوة 3: التحقق من معرّف المتجر ---
$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($business_id === 0) {
    $_SESSION['admin_message'] = "خطأ: لم يتم تحديد معرّف النشاط التجاري.";
    $_SESSION['admin_message_type'] = 'error';
    header('Location: dashboard.php');
    exit;
}


try {
    // --- الخطوة 4: حارس بوابة المحافظة (الأهم) ---
    // جلب بيانات المتجر المستهدف للتأكد من أنه موجود ولم يتم حذفه بالفعل، وللتحقق من محافظته.
    $stmt = $pdo->prepare("SELECT name, governorate_id, deleted_at FROM businesses WHERE id = ?");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);

    // التحقق إذا كان المتجر موجوداً
    if (!$business) {
        $_SESSION['admin_message'] = "خطأ: النشاط التجاري غير موجود.";
        $_SESSION['admin_message_type'] = 'error';
        header('Location: dashboard.php');
        exit;
    }
    
    // التحقق إذا كان المتجر محذوفاً بالفعل
    if ($business['deleted_at'] !== null) {
        $_SESSION['admin_message'] = "هذا النشاط التجاري محذوف بالفعل.";
        $_SESSION['admin_message_type'] = 'error';
        header('Location: dashboard.php');
        exit;
    }

    // تطبيق فلتر المحافظة (إذا لم يكن المستخدم سوبر أدمن)
    if (!hasPermission('super_admin_access_all') && $business['governorate_id'] !== $_SESSION['admin_governorate_id']) {
        $_SESSION['admin_message'] = "وصول غير مصرح به. لا يمكنك إدارة بيانات خارج محافظتك.";
        $_SESSION['admin_message_type'] = 'error';
        header('Location: dashboard.php');
        exit;
    }

    // --- الخطوة 5: بدء عملية الحذف الآمنة (Transaction) ---
    $pdo->beginTransaction();

    // 5.1: تنفيذ "الحذف الناعم" بدلاً من الحذف الفعلي.
    $soft_delete_stmt = $pdo->prepare("UPDATE businesses SET deleted_at = NOW() WHERE id = ?");
    $soft_delete_stmt->execute([$business_id]);

    // 5.2: (إجراء وقائي) إلغاء جميع الطلبات النشطة المرتبطة بهذا المتجر.
    // هذا يمنع وجود طلبات "يتيمة" في النظام لا يمكن إكمالها أبداً.
    $cancel_orders_stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'cancelled_by_admin', cancellation_reason = 'تم إغلاق المتجر من قبل الإدارة' 
        WHERE business_id = ? AND status IN ('pending_approval', 'ready_for_pickup', 'accepted', 'picked_up')
    ");
    $cancelled_count = $cancel_orders_stmt->execute([$business_id]);

    // 5.3: إتمام العملية
    $pdo->commit();

    // إعداد رسالة النجاح
    $_SESSION['admin_message'] = "تم حذف النشاط التجاري '" . htmlspecialchars($business['name']) . "' بنجاح. " . ($cancelled_count > 0 ? "وتم إلغاء الطلبات النشطة المرتبطة به." : "");
    $_SESSION['admin_message_type'] = 'success';
    
} catch (PDOException $e) {
    // في حال حدوث أي خطأ، تراجع عن كل التغييرات
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['admin_message'] = "فشل حذف النشاط التجاري. خطأ: " . $e->getMessage();
    $_SESSION['admin_message_type'] = 'error';
    // لا تقم بإظهار أخطاء قاعدة البيانات للمستخدم النهائي في بيئة الإنتاج
    // error_log("Business Deletion Error: " . $e->getMessage()); 
}

// --- الخطوة 6: إعادة التوجيه إلى الداشبورد ---
header('Location: dashboard.php');
exit;
?>