<?php
// --- **استدعاء حارس البوابة الموحد** ---
require_once '../auth_guard.php';
header('Content-Type: application/json');

// --- حارس البوابة 1: التحقق من صلاحية "حذف مراجعة" ---
if (!hasPermission('delete_review')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لحذف المراجعات.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الوصول مرفوض.']);
    exit;
}

$review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
$action = $_POST['action'] ?? '';

if ($review_id === 0 || $action !== 'delete') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'بيانات ناقصة أو إجراء غير صالح.']);
    exit;
}

try {
    // --- حارس البوابة 2: التحقق من صلاحية المحافظة (لغير السوبر أدمن) ---
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        
        // استعلام للتحقق من أن المراجعة تتبع لمتجر في محافظة الأدمن
        $stmt_check = $pdo->prepare("
            SELECT COUNT(*) 
            FROM business_reviews r
            JOIN businesses b ON r.business_id = b.id
            WHERE r.id = ? AND b.governorate_id = ?
        ");
        $stmt_check->execute([$review_id, $admin_governorate_id]);

        // إذا كان عدد الصفوف المطابقة صفر، فهذا يعني أن المراجعة لا تتبع لهذه المحافظة
        if ($stmt_check->fetchColumn() == 0) {
            throw new Exception("لا يمكنك حذف هذه المراجعة لأنها لا تتبع لمحافظتك.");
        }
    }

    // --- إذا تم تجاوز كل التحققات، قم بتنفيذ عملية الحذف ---
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("DELETE FROM business_reviews WHERE id = ?");
    $stmt->execute([$review_id]);
    $rowCount = $stmt->rowCount();
    $pdo->commit();

    if ($rowCount > 0) {
        echo json_encode(['success' => true, 'message' => 'تم حذف المراجعة بنجاح.']);
    } else {
        throw new Exception("لم يتم العثور على المراجعة ليتم حذفها.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request (قد يكون السبب بيانات خاطئة)
    error_log("Review Deletion Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>