<?php
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_lifetime', 2592000);

// بدء الجلسة
session_start(); // ابدأ الجلسة للوصول إلى متغيرات الجلسة

header('Content-Type: application/json'); // تحديد نوع المحتوى كـ JSON

$response = ['success' => false, 'userName' => null, 'error' => ''];

// التحقق من وجود اسم المستخدم في الجلسة باستخدام المفتاح 'username'
if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    $response['success'] = true;
    $response['userName'] = htmlspecialchars($_SESSION['username']);
} else {
    $response['error'] = 'No user logged in or session expired.';
}

echo json_encode($response);
?>