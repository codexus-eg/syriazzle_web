<?php
// النسخة النهائية والمحسنة - تضمن بدء الجلسة بأمان

// التأكد من أن الجلسة لم تبدأ من قبل
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once 'db_connect.php';

// دالة موحدة لإرسال الرد
function send_json_response($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    send_json_response(['success' => false, 'message' => 'Method Not Allowed.']);
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً للقيام بهذه العملية.']);
}

$user_id = (int)$_SESSION['user_id'];
$business_id = isset($_POST['business_id']) ? (int)$_POST['business_id'] : 0;

if ($business_id === 0) {
    send_json_response(['success' => false, 'message' => 'معرف النشاط التجاري غير صالح.']);
}

try {
    // التحقق مما إذا كان المستخدم يتابع النشاط بالفعل
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM business_followers WHERE user_id = ? AND business_id = ?");
    $stmt_check->execute([$user_id, $business_id]);
    $is_following = (bool)$stmt_check->fetchColumn();

    if ($is_following) {
        // إذا كان يتابعه، قم بإلغاء المتابعة
        $stmt_unfollow = $pdo->prepare("DELETE FROM business_followers WHERE user_id = ? AND business_id = ?");
        $stmt_unfollow->execute([$user_id, $business_id]);
        $new_status_text = 'متابعة';
        $is_now_following = false; 
    } else {
        // إذا لم يكن يتابعه، قم بالمتابعة
        $stmt_follow = $pdo->prepare("INSERT INTO business_followers (user_id, business_id) VALUES (?, ?)");
        $stmt_follow->execute([$user_id, $business_id]);
        $new_status_text = 'إلغاء المتابعة';
        $is_now_following = true; 
    }

    // جلب العدد الجديد للمتابعين
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM business_followers WHERE business_id = ?");
    $stmt_count->execute([$business_id]);
    $new_follower_count = $stmt_count->fetchColumn();

    send_json_response([
        'success' => true, 
        'is_following' => $is_now_following, 
        'new_status_text' => $new_status_text,
        'follower_count' => $new_follower_count
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Follow toggle error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات.']);
}
?>