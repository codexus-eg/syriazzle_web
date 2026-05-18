<?php
session_start();
require_once 'db_connect.php';


header('Content-Type: text/html; charset=UTF-8'); 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../settings.php?status=profile_error&message=' . urlencode('الطلب غير مسموح.'));
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../login.html?message=' . urlencode('يرجى تسجيل الدخول لتحديث ملفك الشخصي.'));
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$whatsapp_number = trim($_POST['whatsapp_number'] ?? '');

if (empty($username) || empty($email)) {
    header('Location: ../settings.php?status=profile_error&message=' . urlencode('اسم المستخدم والبريد الإلكتروني مطلوبان.'));
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../settings.php?status=profile_error&message=' . urlencode('صيغة البريد الإلكتروني غير صالحة.'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        header('Location: ../settings.php?status=profile_error&message=' . urlencode('هذا البريد الإلكتروني مستخدم بالفعل لحساب آخر.'));
        exit;
    }
    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone_number = ?, whatsapp_number = ? WHERE id = ?");
    $stmt->execute([$username, $email, $phone_number, $whatsapp_number, $user_id]);
    header('Location: ../settings.php?status=profile_success&message=' . urlencode('تم تحديث بيانات الملف الشخصي بنجاح!'));
    exit;

} catch (PDOException $e) {
    error_log("Database error updating user profile: " . $e->getMessage());
    header('Location: ../settings.php?status=profile_error&message=' . urlencode('حدث خطأ في قاعدة البيانات أثناء تحديث بياناتك. يرجى المحاولة لاحقاً.'));
    exit;
}