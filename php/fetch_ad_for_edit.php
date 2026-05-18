<?php
header('Content-Type: application/json');

require_once 'db_connect.php'; // تأكد من المسار الصحيح لملف الاتصال بقاعدة البيانات
require_once 'auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'الطلب غير مسموح.']);
    exit;
}

$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ad_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف الإعلان غير صالح.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM form_submissions WHERE id = ?");
    $stmt->execute([$ad_id]);
    $ad_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ad_row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'الإعلان غير موجود.']);
        exit;
    }

    $logged_in_user_id = $_SESSION['user_id'] ?? null;

    // تأمين: التأكد من أن المستخدم المالك فقط يمكنه التعديل
    if ($logged_in_user_id !== null && (int)$ad_row['user_id'] !== (int)$logged_in_user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية لتعديل هذا الإعلان.']);
        exit;
    }

    $ad_data = json_decode($ad_row['json_data'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        error_log("Error decoding JSON for ad ID " . $ad_id . ": " . json_last_error_msg());
        echo json_encode(['success' => false, 'message' => 'حدث خطأ في معالجة بيانات الإعلان.']);
        exit;
    }

    $response_data = array_merge($ad_data, [
        'id' => (int)$ad_row['id'],
        'category' => $ad_row['category'],
        'sub' => $ad_row['sub'],
        'subsub' => $ad_row['subsub'],
        'subsubsub' => $ad_row['subsubsub'],
        'user_id' => (int)$ad_row['user_id'],
        'submitted_at' => $ad_row['submitted_at']
    ]);

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $response_data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error in fetch_ad_for_edit.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات. يرجى المحاولة لاحقاً.']);
}
?>