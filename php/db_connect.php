<?php
// ========================================================================
// Syriazzle - Global Secure Database & Session Manager
// ========================================================================

// 1. إعدادات الجلسة (يجب أن تكون قبل session_start)
$lifetime = 31536000; // سنة كاملة ثانية
ini_set('session.gc_maxlifetime', $lifetime);
ini_set('session.cookie_lifetime', $lifetime);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '.syriazzle.sy', // تأكد من وجود النقطة قبل الدومين ليعمل في كل الأماكن
        'secure' => true, 
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 2. إعدادات الاتصال بقاعدة البيانات
$host = 'localhost';
$db   = 'syriazzle_online';
$user = 'syriazzle_user';
$pass = 'Drj$,iEVQ_Bg';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("الموقع قيد الصيانة حالياً.");
}