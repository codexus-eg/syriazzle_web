<?php
session_start();
require_once 'db_connect.php';

// --- حارس البوابة 1: التحقق من هوية المستخدم والطلب ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); die("Access Denied: User not logged in.");
}
$current_user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../business_dashboard.php'); exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403); die("Invalid CSRF token.");
}

$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;
if ($business_id === 0) {
    $_SESSION['message'] = "خطأ: معرف النشاط التجاري غير صالح.";
    $_SESSION['message_type'] = 'error';
    header('Location: ../business_dashboard.php');
    exit;
}

try {
    // --- حارس البوابة 2: التحقق من ملكية المتجر وأنه ليس محذوفاً ---
    $stmt = $pdo->prepare("SELECT user_id, name FROM businesses WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$business) {
        throw new Exception("هذا النشاط التجاري غير موجود أو تم حذفه بالفعل.");
    }
    if ($business['user_id'] !== $current_user_id) {
        throw new Exception("ليس لديك صلاحية لحذف هذا النشاط التجاري.");
    }

    // --- بدء عملية الحذف الآمنة (Transaction) ---
    $pdo->beginTransaction();

    // 1. **تطبيق الحذف الناعم (Soft Delete)**
    // نحن لا نحذف السجل، بل فقط نضع علامة عليه بأنه محذوف.
    $soft_delete_stmt = $pdo->prepare("UPDATE businesses SET deleted_at = NOW() WHERE id = ?");
    $soft_delete_stmt->execute([$business_id]);

    // 2. **عدم حذف الصور فعلياً!**
    // بما أننا نستخدم الحذف الناعم بهدف إمكانية استرجاع البيانات،
    // فإن حذف الصور من السيرفر يتعارض مع هذا المبدأ. لذلك، تم تعطيل كود الحذف الفعلي للصور.
    /* 
    if ($business['logo_image'] && file_exists('../' . $business['logo_image'])) unlink('../' . $business['logo_image']);
    if ($business['cover_image'] && file_exists('../' . $business['cover_image'])) unlink('../' . $business['cover_image']);
    // ... etc for gallery images ...
    */
    
    // 3. **إلغاء الطلبات النشطة (إجراء وقائي حاسم)**
    // هذا يضمن عدم ترك أي زبون ينتظر طلبًا من متجر أصبح مغلقًا.
    $cancel_orders_stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'cancelled_by_user', cancellation_reason = 'تم إغلاق المتجر من قبل صاحبه' 
        WHERE business_id = ? AND status IN ('pending_approval', 'ready_for_pickup', 'accepted', 'picked_up')
    ");
    $cancel_orders_stmt->execute([$business_id]);

    // 4. إتمام العملية
    $pdo->commit();
    
    $_SESSION['message'] = "تم حذف نشاطك التجاري '" . htmlspecialchars($business['name']) . "' بنجاح.";
    $_SESSION['message_type'] = 'success';

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['message'] = "فشل الحذف: " . $e->getMessage();
    $_SESSION['message_type'] = 'error';
}

// إعادة التوجيه إلى الداشبورد حيث لن يظهر المتجر بعد الآن.
header('Location: ../business_dashboard.php');
exit;
?>