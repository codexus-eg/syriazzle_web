<?php
// --- **استدعاء حارس البوابة الموحد** ---
require_once '../auth_guard.php';
header('Content-Type: application/json');

// --- حارس البوابة 1: التحقق من صلاحية "تعديل متجر" ---
if (!hasPermission('edit_business')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتعديل إعدادات المتاجر.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح.']);
    exit;
}

try {
    // --- استقبال وتنقية البيانات ---
    $business_id = filter_input(INPUT_POST, 'business_id', FILTER_VALIDATE_INT);
    $commission_rate = filter_input(INPUT_POST, 'commission_rate', FILTER_VALIDATE_FLOAT);
    $credit_limit = filter_input(INPUT_POST, 'credit_limit', FILTER_VALIDATE_FLOAT);
    
    if (!$business_id || $commission_rate === false || $credit_limit === false) {
        throw new Exception('بيانات غير صالحة.');
    }

    // --- حارس البوابة 2: التحقق من صلاحية المحافظة (لغير السوبر أدمن) ---
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE id = ? AND governorate_id = ?");
        $stmt_check->execute([$business_id, $admin_governorate_id]);
        if ($stmt_check->fetchColumn() == 0) {
            // إذا كان المتجر لا يتبع لمحافظة المدير، امنعه
            throw new Exception("لا يمكنك تعديل إعدادات هذا المتجر لأنه لا يتبع لمحافظتك.");
        }
    }

    // --- إذا تم تجاوز كل التحققات، قم بتنفيذ عملية التحديث ---
    $stmt = $pdo->prepare("UPDATE businesses SET commission_rate = ?, credit_limit = ? WHERE id = ?");
    $stmt->execute([$commission_rate, $credit_limit, $business_id]);
    
    echo json_encode(['success' => true, 'message' => "تم تحديث إعدادات المتجر بنجاح."]);

} catch (Exception $e) {
    http_response_code(400); // Bad Request (قد يكون السبب بيانات خاطئة أو خطأ صلاحية)
    echo json_encode(['success' => false, 'message' => 'فشل التحديث: ' . $e->getMessage()]);
}
?>