<?php
// send_message.php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sender_id = (int)$_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$ad_id = isset($_POST['ad_id']) ? (int)$_POST['ad_id'] : 0;
$message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
// استقبال معرف الرسالة المردود عليها (إذا وجد)
$reply_to_id = isset($_POST['reply_to_id']) && !empty($_POST['reply_to_id']) ? (int)$_POST['reply_to_id'] : null;

if (empty($message_text) || $receiver_id === 0 || $ad_id === 0) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

try {
    $sql = "INSERT INTO messages (ad_id, sender_id, receiver_id, message_text, reply_to_message_id, sent_at) 
            VALUES (:ad_id, :sender_id, :receiver_id, :msg, :reply_id, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ad_id'     => $ad_id,
        ':sender_id' => $sender_id,
        ':receiver_id' => $receiver_id,
        ':msg'       => $message_text,
        ':reply_id'  => $reply_to_id // سيتم حفظه كـ NULL إذا لم يكن هناك رد
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>