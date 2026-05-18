<?php
require_once 'db_connect.php'; // يبدأ الجلسة تلقائياً

header('Content-Type: application/json; charset=UTF-8');

// --- حارس البوابة 1: التحقق من نوع الطلب وهوية المستخدم ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموح بها.']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'جلسة المستخدم غير صالحة أو منتهية.']);
    exit;
}

// --- **المنطق الجديد: استقبال والتحقق من كلمة المرور** ---
$password = $_POST['password'] ?? '';

if (empty($password)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'كلمة المرور مطلوبة لتأكيد الحذف.']);
    exit;
}

try {
    // جلب كلمة المرور المشفرة (hash) من قاعدة البيانات
    $stmt_pass = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt_pass->execute([$user_id]);
    $hashed_password = $stmt_pass->fetchColumn();

    if (!$hashed_password) {
        // هذه الحالة لا يجب أن تحدث لمستخدم مسجل دخوله، لكنها حماية إضافية
        throw new Exception("لم يتم العثور على المستخدم.");
    }

    // التحقق من تطابق كلمة المرور المدخلة مع الـ hash المخزن
    if (!password_verify($password, $hashed_password)) {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'كلمة المرور التي أدخلتها غير صحيحة.']);
        exit;
    }

} catch (PDOException $e) {
    error_log("Password check failed during account deletion for user_id {$user_id}: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء التحقق من بياناتك.']);
    exit;
}


// --- إذا كانت كلمة المرور صحيحة، نكمل عملية الحذف ---
try {
    // --- حارس البوابة 2: التأكد من أن المستخدم ليس محذوفًا بالفعل ---
    $stmt_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt_check->execute([$user_id]);
    if ($stmt_check->fetchColumn() === false) {
        echo json_encode(['success' => false, 'message' => 'الحساب تم حذفه بالفعل.']);
        exit;
    }

    // --- بدء عملية الحذف الناعم ---
    // 1. تطبيق الحذف الناعم (Soft Delete) على حساب المستخدم
    $stmt_soft_delete = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
    $stmt_soft_delete->execute([$user_id]);

    // 2. (إجراء وقائي) إلغاء أي طلبات توصيل نشطة لهذا المستخدم
    $cancel_orders_stmt = $pdo->prepare("
        UPDATE orders 
        SET status = 'cancelled_by_user', cancellation_reason = 'قام المستخدم بحذف حسابه' 
        WHERE user_id = ? AND status IN ('pending_approval', 'ready_for_pickup', 'accepted', 'picked_up')
    ");
    $cancel_orders_stmt->execute([$user_id]);
    
    // 3. (إجراء وقائي) إلغاء أي حجوزات مستقبلية نشطة لهذا المستخدم
    $cancel_bookings_stmt = $pdo->prepare("
        UPDATE bookings
        SET status = 'cancelled_by_user', cancellation_reason = 'قام المستخدم بحذف حسابه'
        WHERE user_id = ? AND status IN ('pending_payment', 'pending_confirmation', 'confirmed') AND start_datetime > NOW()
    ");
    $cancel_bookings_stmt->execute([$user_id]);


    // 4. تدمير الجلسة لتسجيل خروج المستخدم
    session_unset();
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'تم حذف حسابك بنجاح. سيتم الآن تسجيل خروجك.']);
    exit;

} catch (PDOException $e) {
    error_log("Database error during user soft-deletion for user_id {$user_id}: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'حدث خطأ فني أثناء محاولة حذف الحساب.']);
    exit;
}
?>