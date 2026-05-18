<?php
// ========================================================================
// Syriazzle - Admin Login Page (النسخة 3.0 النهائية - كاملة ومصححة)
// ========================================================================

require_once '../php/db_connect.php';

// إذا كان الأدمن مسجلاً دخوله بالفعل، وجهه إلى الداشبورد
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error_message = '';
// عرض رسالة خطأ واضحة للمستخدم بناءً على الخطأ في الرابط
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized' || $_GET['error'] === 'session_expired') {
        $error_message = 'جلستك غير صالحة أو انتهت صلاحيتها. يرجى تسجيل الدخول مرة أخرى.';
    } elseif ($_GET['error'] === 'session_hijacked') {
         $error_message = 'تم تسجيل خروجك لأسباب أمنية. يرجى تسجيل الدخول مرة أخرى.';
    }
}

// التحقق إذا تم إرسال الفورم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_username = $_POST['username'] ?? '';
    $entered_password = $_POST['password'] ?? '';

    if (empty($entered_username) || empty($entered_password)) {
        $error_message = 'الرجاء إدخال اسم المستخدم وكلمة المرور.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$entered_username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($entered_password, $admin['password']) && $admin['is_active'] == 1) {
                
                $permissions_stmt = $pdo->prepare("SELECT p.name FROM permissions p JOIN role_permissions rp ON p.id = rp.permission_id WHERE rp.role_id = ?");
                $permissions_stmt->execute([$admin['role_id']]);
                $permissions = $permissions_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                // إعادة إنشاء معرّف الجلسة لمنع هجمات تثبيت الجلسة
                session_regenerate_id(true); 
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = (int)$admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_full_name'] = $admin['full_name'];
                $_SESSION['admin_role_id'] = (int)$admin['role_id'];
                $_SESSION['admin_permissions'] = $permissions;
                $_SESSION['admin_governorate_id'] = $admin['governorate_id'] ? (int)$admin['governorate_id'] : null;

                // تم حذف السطر الخاص بإنشاء البصمة نهائياً من هنا
                
                // الحل الحاسم: إجبار الخادم على حفظ بيانات الجلسة قبل التوجيه
                session_write_close(); 
                
                // استخدام مسار مطلق للتوجيه لضمان عدم حدوث أخطاء
                header('Location: /admin/dashboard.php');
                exit;

            } elseif ($admin && $admin['is_active'] == 0) {
                $error_message = 'هذا الحساب معطل. يرجى مراجعة المدير.';
            } else {
                $error_message = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
            }

        } catch (PDOException $e) {
            $error_message = "خطأ في الاتصال بقاعدة البيانات.";
            error_log("Admin Login Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول لوحة التحكم - Syriazzle</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/all.min.css">
   <style>
        body { 
            font-family: 'Cairo', sans-serif; 
            margin: 0;
            height: 100vh;
            background-image: url('../image/photo-1502082553048-f009c37129b9.avif');
            background-size: cover;
            background-position: center;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-wrapper {
            width: 900px;
            height: 550px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            border: 1px solid rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        .login-info {
            color: #fff;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        .login-info .logo { font-size: 32px; text-align: center; font-weight: 900; margin-bottom: 20px; }
        .login-info h2 { font-size: 42px; text-align: center; margin: 0 0 15px; font-weight: 700; line-height: 1.2; }
        .login-info p { font-size: 16px; opacity: 0.9; line-height: 1.7; text-align: center; }

        .login-form-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-form-container h1 { color: #212529; text-align: center; margin-bottom: 30px; }
        .form-group { position: relative; margin-bottom: 25px; }
        .form-group input { 
            width: 100%;
            box-sizing: border-box;
            padding: 14px 40px 14px 14px; 
            border: 1px solid #ced4da; border-radius: 8px; font-size: 16px;
            background: #f8f9fa;
        }
        .form-group .icon { position: absolute; top: 50%; right: 15px; transform: translateY(-50%); color: #6c757d; }
        .submit-btn { 
            width: 100%; padding: 14px; background: linear-gradient(45deg, #0d6efd, #0a58ca);
            color: #fff; border: none; border-radius: 8px; font-size: 18px; 
            font-weight: 700; cursor: pointer;
            transition: transform 0.2s;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
        }
        .error-message { 
            background-color: #f8d7da;
            color: #721c24; 
            padding: 12px; 
            margin-bottom: 20px; 
            text-align: center;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
        }
        @media (max-width: 900px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                width: 90%;
                max-width: 450px;
                height: auto;
            }
            .login-info {
                display: none;
            }
            .login-form-container {
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-info">
            <div class="logo"><img src="../image/logo1.png" alt="Syriazzle Logo" style="max-width: 150px;"></div>
            <h2>مركز عمليات<br>الشركاء</h2>
            <p>مرحباً بك في لوحة التحكم المركزية. الرجاء تسجيل الدخول باستخدام حساب الموظف الخاص بك للمتابعة.</p>
        </div>
        <div class="login-form-container">
            <h1>تسجيل الدخول</h1>
            <?php if (!empty($error_message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <form method="POST" action="index.php">
                <div class="form-group">
                    <i class="fas fa-user icon"></i>
                    <input type="text" name="username" placeholder="اسم المستخدم" required>
                </div>
                <div class="form-group">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" name="password" placeholder="كلمة المرور" required>
                </div>
                <button type="submit" class="submit-btn">دخول</button>
            </form>
        </div>
    </div>
</body>
</html>