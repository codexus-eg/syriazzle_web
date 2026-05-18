<?php
// استدعاء ملف الاتصال أولاً لجلب البيانات وبدء الجلسة
require_once 'php/db_connect.php';

// جلب قائمة المحافظات من قاعدة البيانات
try {
    $governorates = $pdo->query("SELECT id, name FROM governorates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // في حال فشل جلب المحافظات، يمكن الاستمرار بدونها أو عرض رسالة خطأ
    $governorates = [];
    error_log("Failed to fetch governorates: " . $e->getMessage());
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$old_inputs = $_SESSION['form_inputs'] ?? [];
unset($_SESSION['form_inputs']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/main_header.css">
    <title>تسجيل سائق جديد - Syriazzle</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #e60000; --secondary-color: #007bff; --bg-light: #f0f2f5; }
        body { font-family: 'Cairo', sans-serif; background-color: var(--bg-light); display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box;}
        .register-container { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h1 { text-align: center; color: var(--primary-color); margin-top: 0; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input, select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-family: 'Cairo', sans-serif; box-sizing: border-box; font-size: 15px; }
        .submit-btn { width: 100%; padding: 15px; background-color: var(--secondary-color); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background-color 0.2s;}
        .submit-btn:hover { background-color: #0056b3; }
        .login-link { text-align: center; margin-top: 20px; font-size: 15px; }
        .login-link a { color: var(--secondary-color); text-decoration: none; font-weight: 600; }
        .error-message {
            background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
            padding: 15px; border-radius: 6px; margin-bottom: 20px;
            text-align: center; font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="register-container">
        <h1>انضم إلينا كسائق</h1>

        <?php if (isset($_SESSION['register_error'])): ?>
        <div class="error-message">
            <?php echo $_SESSION['register_error']; unset($_SESSION['register_error']); ?>
        </div>
        <?php endif; ?>

        <form action="php/save_driver.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="full_name">الاسم الكامل</label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($old_inputs['full_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">رقم الهاتف (سيكون اسم المستخدم)</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($old_inputs['phone'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="governorate_id">المحافظة التي ستعمل بها</label>
                <select id="governorate_id" name="governorate_id" required>
                    <option value="" disabled <?php if(!isset($old_inputs['governorate_id'])) echo 'selected'; ?>>-- اختر محافظتك --</option>
                    <?php foreach ($governorates as $gov): ?>
                        <option value="<?php echo $gov['id']; ?>" <?php if(isset($old_inputs['governorate_id']) && $old_inputs['governorate_id'] == $gov['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($gov['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="vehicle_type">نوع المركبة</label>
                <select id="vehicle_type" name="vehicle_type" required>
                    <option value="Motorcycle" <?php if(isset($old_inputs['vehicle_type']) && $old_inputs['vehicle_type'] == 'Motorcycle') echo 'selected'; ?>>دراجة نارية</option>
                    <option value="Car" <?php if(isset($old_inputs['vehicle_type']) && $old_inputs['vehicle_type'] == 'Car') echo 'selected'; ?>>سيارة</option>
                    <option value="Bicycle" <?php if(isset($old_inputs['vehicle_type']) && $old_inputs['vehicle_type'] == 'Bicycle') echo 'selected'; ?>>دراجة هوائية</option>
                </select>
            </div>
            
            <button type="submit" class="submit-btn">إنشاء حساب</button>
        </form>

        <div class="login-link">
            <p>لديك حساب بالفعل؟ <a href="driver_login.php">سجل الدخول</a></p>
        </div>
    </div>
</body>
</html>