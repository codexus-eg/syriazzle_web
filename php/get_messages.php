<?php
// تفعيل عرض الأخطاء للتصحيح (يجب تعطيله في بيئة الإنتاج)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json'); // لضمان إرجاع JSON صحيح

require_once 'db_connect.php'; // تأكد من أن هذا الملف موجود

// التحقق من أن المستخدم مسجل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];

// قراءة البيانات من JSON أو من GET
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ad_id = isset($input['ad_id']) ? (int)$input['ad_id'] : 0;
    $other_user_id = isset($input['other_user_id']) ? (int)$input['other_user_id'] : 0;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ad_id = isset($_GET['ad_id']) ? (int)$_GET['ad_id'] : 0;
    $other_user_id = isset($_GET['other_user_id']) ? (int)$_GET['other_user_id'] : 0;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if ($ad_id <= 0 || $other_user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing ad ID or other user ID.']);
    exit;
}

try {
    // جلب الرسائل بين المستخدمين حول إعلان محدد
    $stmt = $pdo->prepare(
        "SELECT m.*, u.username AS sender_username
        FROM messages m
        LEFT JOIN users u ON u.id = m.sender_id
        WHERE m.ad_id = :ad_id AND (
            (m.sender_id = :sender1 AND m.receiver_id = :receiver1)
            OR
            (m.sender_id = :sender2 AND m.receiver_id = :receiver2)
        )
        ORDER BY m.sent_at ASC"
    );
    $stmt->execute([
        ':ad_id' => $ad_id,
        ':sender1' => $current_user_id,
        ':receiver1' => $other_user_id,
        ':sender2' => $other_user_id,
        ':receiver2' => $current_user_id
    ]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تحديث الرسائل كـ "مقروءة"
    $stmt_mark_read = $pdo->prepare(
        "UPDATE messages SET is_read = TRUE 
         WHERE receiver_id = :current_user AND sender_id = :other_user 
         AND ad_id = :ad_id AND is_read = FALSE"
    );
    $stmt_mark_read->execute([
        ':current_user' => $current_user_id,
        ':other_user' => $other_user_id,
        ':ad_id' => $ad_id
    ]);

    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
