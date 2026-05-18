<?php
// fetch_messages.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

// نتحقق من الجلسة (موجودة أصلاً في db_connect ولكن للضمان)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]); exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$ad_id = isset($_GET['ad_id']) ? (int)$_GET['ad_id'] : 0;
$other_user_id = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;

if ($ad_id === 0 || $other_user_id === 0) {
    echo json_encode([]); exit;
}

try {
    // التعديل الجذري: استخدام أسماء فريدة لكل متغير (:c1, :o1, :c2, :o2)
    // لأن سيرفرك يرفض تكرار الاسم بسبب ATTR_EMULATE_PREPARES => false
    $sql = "
        SELECT 
            m.id, 
            m.sender_id, 
            m.receiver_id, 
            m.message_text as message, 
            m.sent_at as created_at,
            u.username as sender_username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE 
            m.ad_id = :aid 
            AND (
                (m.sender_id = :c1 AND m.receiver_id = :o1)
                OR 
                (m.sender_id = :o2 AND m.receiver_id = :c2)
            )
        ORDER BY m.sent_at ASC 
    ";

    $stmt = $pdo->prepare($sql);
    
    // نربط كل اسم فريد بقيمته حتى لو كانت القيمة متكررة
    $stmt->execute([
        ':aid' => $ad_id,
        ':c1'  => $current_user_id,
        ':o1'  => $other_user_id,
        ':o2'  => $other_user_id,
        ':c2'  => $current_user_id
    ]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تحديث الرسائل لتصبح مقروءة (بأسماء فريدة أيضاً)
    $updateSql = "UPDATE messages SET is_read = 1 
                  WHERE ad_id = :uaid 
                  AND sender_id = :u_other 
                  AND receiver_id = :u_current 
                  AND is_read = 0";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([
        ':uaid'     => $ad_id,
        ':u_other'   => $other_user_id,
        ':u_current' => $current_user_id
    ]);

    echo json_encode($messages);

} catch (PDOException $e) {
    // إرجاع الخطأ الحقيقي إذا فشل شيء ما
    echo json_encode(['error' => $e->getMessage()]);
}
?>