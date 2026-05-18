<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die("طلب غير صالح.");
}
unset($_SESSION['csrf_token']);

// 1. استقبال وتنظيف البيانات (مع إضافة governorate_id)
$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$governorate_id = filter_input(INPUT_POST, 'governorate_id', FILTER_VALIDATE_INT); // **(جديد)**
$vehicle_type = trim($_POST['vehicle_type'] ?? 'Motorcycle');

$_SESSION['form_inputs'] = $_POST;

// 2. التحقق من صحة المدخلات (مع إضافة governorate_id)
if (empty($full_name) || empty($phone) || empty($password) || $governorate_id === false || $governorate_id === null) {
    $_SESSION['register_error'] = "الرجاء ملء جميع الحقول المطلوبة، بما في ذلك اختيار المحافظة.";
    header('Location: ../driver_register.php');
    exit;
}
// التحقق من أن نوع المركبة ضمن الخيارات المسموحة
if (!in_array($vehicle_type, ['Motorcycle', 'Car', 'Bicycle'])) {
    $_SESSION['register_error'] = "نوع المركبة المحدد غير صالح.";
    header('Location: ../driver_register.php');
    exit;
}

// 3. التحقق من أن رقم الهاتف غير مستخدم مسبقاً
try {
    $stmt = $pdo->prepare("SELECT id, status FROM drivers WHERE phone = ?");
    $stmt->execute([$phone]);
    $existing_driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_driver) {
        if ($existing_driver['status'] === 'blocked') {
            $_SESSION['register_error'] = "هذا الحساب محظور. لا يمكنك التسجيل بهذا الرقم مرة أخرى.";
        } else {
            $_SESSION['register_error'] = "رقم الهاتف هذا مسجل بالفعل. يرجى استخدام رقم آخر أو تسجيل الدخول.";
        }
        header('Location: ../driver_register.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Driver registration check failed: " . $e->getMessage());
    $_SESSION['register_error'] = "حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.";
    header('Location: ../driver_register.php');
    exit;
}

// 4. تشفير كلمة المرور وإدخال البيانات (مع إضافة governorate_id)
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

try {
    // **(تحديث)** تعديل استعلام INSERT ليشمل governorate_id
    $sql = "INSERT INTO drivers (full_name, phone, password, governorate_id, vehicle_type, status) VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$full_name, $phone, $hashed_password, $governorate_id, $vehicle_type]);
    
    unset($_SESSION['form_inputs']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم استلام طلبك للتسجيل!</title>
    <link rel="stylesheet" href="../css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --success-color: #28a745; --primary-color: #007bff; --bg-light: #f0f2f5; --card-bg: #fff; --text-dark: #212529; --text-light: #6c757d; }
        body { font-family: 'Cairo', sans-serif; background-color: var(--bg-light); margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; padding: 15px; box-sizing: border-box; }
        .success-card { background-color: var(--card-bg); padding: 40px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); text-align: center; max-width: 450px; width: 100%; }
        .success-icon { color: var(--primary-color); font-size: 80px; margin-bottom: 20px; animation: pop 0.5s ease-out; }
        @keyframes pop { 0% { transform: scale(0.5); opacity: 0; } 80% { transform: scale(1.1); } 100% { transform: scale(1); opacity: 1; } }
        h1 { font-size: 28px; color: var(--text-dark); margin: 0 0 10px 0; }
        p { color: var(--text-light); font-size: 16px; line-height: 1.7; margin-bottom: 30px; }
        .back-link { display: inline-block; padding: 12px 30px; background-color: var(--primary-color); color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; transition: background-color 0.2s; }
        .back-link:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-paper-plane"></i>
        </div>
        <h1>تم إرسال طلبك بنجاح!</h1>
        <p>شكراً لتسجيلك. سيقوم فريقنا بمراجعة طلبك. سيتم التواصل معك عبر رقم الهاتف الذي أدخلته لإكمال إجراءات التفعيل.</p>
        <a href="../driver_login.php" class="back-link">العودة لصفحة تسجيل الدخول</a>
    </div>
</body>
</html>
<?php
    exit;

} catch (PDOException $e) {
    error_log("Driver insertion failed: " . $e->getMessage());
    $_SESSION['register_error'] = "فشل إنشاء الحساب بسبب خطأ في النظام.";
    header('Location: ../driver_register.php');
    exit;
}
?>