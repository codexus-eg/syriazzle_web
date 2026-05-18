<?php
session_start();
// إذا كان السائق مسجل دخوله بالفعل، قم بتوجيهه مباشرة إلى لوحة التحكم
if (isset($_SESSION['driver_id'])) {
    header('Location: driver_dashboard.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/main_header.css">
    <title>تسجيل دخول السائقين - Syriazzle</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #e60000; --secondary-color: #007bff; --bg-light: #f0f2f5; }
        body { font-family: 'Cairo', sans-serif; background-color: var(--bg-light); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: var(--primary-color); }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-family: 'Cairo', sans-serif; box-sizing: border-box; }
        .submit-btn { width: 100%; padding: 15px; background-color: var(--secondary-color); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center;}
        .register-link { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="login-container">
        <h1>بوابة السائقين</h1>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>
        <form action="php/process_driver_login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label for="phone">رقم الهاتف</label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="submit-btn">تسجيل الدخول</button>
        </form>
        <div class="register-link">
            <p>لا تملك حساباً؟ <a href="driver_register.php">سجل الآن</a></p>
        </div>
    </div>
</body>
</html>