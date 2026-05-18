<?php
// get_chat_list.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مسجل']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // ✅ الحل الجذري: استخدام أسماء متغيرات مختلفة (:uid1, :uid2) لتجنب خطأ التكرار
    // واستخدام LEAST/GREATEST في التجميع لضمان عدم الحاجة لمتغيرات داخل الـ GROUP BY
    $sql = "
        SELECT 
            m.id as message_id,
            m.ad_id,
            m.sender_id,
            m.receiver_id,
            m.message_text as last_message_text,
            m.sent_at as last_message_time,
            fs.subsubsub,
            fs.category,
            fs.sub,
            fs.json_data,
            fs.images_paths,
            fs.user_id as ad_owner_id,
            u_sender.username as sender_name,
            u_receiver.username as receiver_name
        FROM messages m
        JOIN form_submissions fs ON m.ad_id = fs.id
        LEFT JOIN users u_sender ON m.sender_id = u_sender.id
        LEFT JOIN users u_receiver ON m.receiver_id = u_receiver.id
        WHERE m.id IN (
            SELECT MAX(id)
            FROM messages
            WHERE sender_id = :uid1 OR receiver_id = :uid2
            GROUP BY ad_id, LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id)
        )
        ORDER BY m.sent_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    // نمرر القيمة مرتين، مرة لكل متغير في الاستعلام
    $stmt->execute([
        ':uid1' => $user_id,
        ':uid2' => $user_id
    ]);
    
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $processed_chats = [];
    
    foreach ($chats as $chat) {
        // منطق تحديد الطرف الآخر
        if ($chat['sender_id'] == $user_id) {
            $other_user_id = $chat['receiver_id'];
            $other_username = $chat['receiver_name'];
        } else {
            $other_user_id = $chat['sender_id'];
            $other_username = $chat['sender_name'];
        }

        // إصلاح الآيدي الصفري
        if ($other_user_id == 0) {
            $other_user_id = $chat['ad_owner_id'];
            $stmtUser = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmtUser->execute([$other_user_id]);
            $other_username = $stmtUser->fetchColumn();
        }

        if (empty($other_username)) {
            $other_username = "مستخدم";
        }

        $images = json_decode($chat['images_paths'] ?? '[]', true);
        $image_url = !empty($images) ? '../' . $images[0] : 'https://via.placeholder.com/50';

        $title = $chat['subsubsub'];
        if (empty($title)) $title = $chat['sub'] ?? $chat['category'];
        if (empty($title)) {
            $jsonData = json_decode($chat['json_data'], true);
            $title = $jsonData['العنوان'] ?? 'إعلان';
        }

        // حساب غير المقروء
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE ad_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmtCount->execute([$chat['ad_id'], $other_user_id, $user_id]);
        $unread_count = $stmtCount->fetchColumn();

        $processed_chats[] = [
            'ad_id' => $chat['ad_id'],
            'other_user_id' => $other_user_id,
            'other_username' => $other_username,
            'ad_title' => $title,
            'ad_image_url' => $image_url,
            'last_message_text' => $chat['last_message_text'],
            'last_message_time' => $chat['last_message_time'],
            'unread_count' => $unread_count
        ];
    }

    echo json_encode(['success' => true, 'chats' => $processed_chats]);

} catch (PDOException $e) {
    error_log("Chat List DB Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطأ SQL: ' . $e->getMessage()]);
}
?>