<?php
// mall_driver_login.php (النسخة النهائية مع منطق تسجيل الدخول)
require_once 'php/db_connect.php';

$error_message = '';

// إذا كان السائق مسجلاً دخوله بالفعل، وجهه إلى الداشبورد
if (isset($_SESSION['driver_logged_in']) && $_SESSION['driver_logged_in'] === true) {
    header('Location: mall_driver_dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // role_id الخاص بسائقي المول (تأكد من أنه الرقم الصحيح من جدول roles)
    $mall_driver_role_id = 5; 

    if (empty($username) || empty($password)) {
        $error_message = 'الرجاء إدخال اسم المستخدم وكلمة المرور.';
    } else {
        try {
            // جلب السائق الذي يطابق اسم المستخدم ودور "سائق المول"
            $stmt = $pdo->prepare("SELECT id, full_name, password FROM drivers WHERE username = ? AND role_id = ? AND is_active = 1");
            $stmt->execute([$username, $mall_driver_role_id]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($driver && password_verify($password, $driver['password'])) {
                // نجح تسجيل الدخول، قم بإنشاء الجلسة
                session_regenerate_id(true);
                $_SESSION['driver_logged_in'] = true;
                $_SESSION['driver_id'] = (int)$driver['id'];
                $_SESSION['driver_name'] = $driver['full_name'];
                
                session_write_close();
                header('Location: mall_driver_dashboard.php');
                exit;
            } else {
                $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة، أو الحساب غير نشط.';
            }
        } catch (PDOException $e) {
            $error_message = 'حدث خطأ في الخادم.';
            error_log('Mall Driver Login Error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة سائقي المول - Syriazzle</title>
    <link rel="stylesheet" href="css/mall_driver.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>بوابة سائقي المول</h1>
            <p>الرجاء تسجيل الدخول للمتابعة</p>
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <form method="POST" action="mall_driver_login.php">
                <div class="form-group">
                    <label for="username">اسم المستخدم</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-submit">تسجيل الدخول</button>
            </form>
        </div>
    </div>
</body>
</html>