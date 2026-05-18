<?php
// إعداد الجلسة بشكل آمن قبل أي شيء
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// 1. التحقق من رمز CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../driver_login.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = "انتهت الجلسة، يرجى المحاولة مرة أخرى.";
    header('Location: ../driver_login.php');
    exit;
}

// 2. استقبال وتنظيف البيانات
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($phone) || empty($password)) {
    $_SESSION['error_message'] = "الرجاء إدخال رقم الهاتف وكلمة المرور.";
    header('Location: ../driver_login.php');
    exit;
}

try {
    // 3. تحسين البحث عن رقم الهاتف
    // نقوم بتنظيف الرقم المدخل من أي رموز غير رقمية
    $cleaned_phone = preg_replace('/[^0-9]/', '', $phone);
    
    // سنبحث عن الرقم كما هو، أو عن آخر 9 أرقام منه لضمان المرونة مع الصفر الدولي والمحلي
    $phone_last_digits = substr($cleaned_phone, -9);

    $stmt = $pdo->prepare("
        SELECT id, full_name, password, status, driver_type 
        FROM drivers 
        WHERE phone = ? OR phone LIKE ? 
        LIMIT 1
    ");
    $stmt->execute([$cleaned_phone, '%' . $phone_last_digits]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. التحقق من الهوية
    if ($driver && password_verify($password, $driver['password'])) {
        
        // التحقق من حالة الحساب
        if ($driver['status'] === 'approved') {
            
            // تثبيت بيانات الجلسة
            $_SESSION['driver_id'] = (int)$driver['id'];
            $_SESSION['driver_name'] = $driver['full_name'];
            $_SESSION['driver_type'] = $driver['driver_type'];

            // ملاحظة للمهندس: قمنا بتأجيل session_regenerate_id أو استدعائه بدون تدمير القديم فوراً
            // لضمان ثبات الجلسة في تطبيقات الـ WebView
            session_write_close(); // إغلاق الجلسة لحفظ البيانات فوراً قبل التوجيه

            header('Location: ../driver_dashboard.php');
            exit;

        } elseif ($driver['status'] === 'pending') {
            $_SESSION['error_message'] = "حسابك قيد المراجعة حالياً، سنقوم بتفعيله قريباً.";
        } elseif ($driver['status'] === 'blocked') {
            $_SESSION['error_message'] = "هذا الحساب محظور. يرجى مراجعة الإدارة.";
        }
    } else {
        $_SESSION['error_message'] = "رقم الهاتف أو كلمة المرور غير صحيحة.";
    }

    // في حال الفشل نعود لصفحة التسجيل
    header('Location: ../driver_login.php');
    exit;

} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage());
    $_SESSION['error_message'] = "حدث خطأ فني في الخادم، يرجى المحاولة لاحقاً.";
    header('Location: ../driver_login.php');
    exit;
}