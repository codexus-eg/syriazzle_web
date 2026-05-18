<?php
require_once 'php/db_connect.php'; 
$user_id = $_SESSION['user_id'] ?? null;
$username = '';
$email = '';
$phone_number = '';
$profile_error = '';
$profile_success = '';
$password_error = '';
$password_success = '';

// فحص رسائل الحالة المرسلة عبر URL
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'profile_success') {
        $profile_success = $_GET['message'] ?? 'تم تحديث بيانات الملف الشخصي بنجاح!';
    } elseif ($_GET['status'] == 'profile_error') {
        $profile_error = $_GET['message'] ?? 'فشل تحديث بيانات الملف الشخصي.';
    } elseif ($_GET['status'] == 'password_success') {
        $password_success = $_GET['message'] ?? 'تم تغيير كلمة المرور بنجاح!';
    } elseif ($_GET['status'] == 'password_error') {
        $password_error = $_GET['message'] ?? 'فشل تغيير كلمة المرور.';
    }
}

// جلب بيانات المستخدم لملء النموذج مسبقاً
if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT username, email, phone FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $username = htmlspecialchars($user['username']);
            $email = htmlspecialchars($user['email']);
            $phone_number = htmlspecialchars($user['phone'] ?? '');
        } else {
            // المستخدم غير موجود في قاعدة البيانات
            session_destroy();
            header('Location: login.php?message=' . urlencode('جلسة غير صالحة. يرجى تسجيل الدخول مرة أخرى.'));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error fetching user profile: " . $e->getMessage());
        $profile_error = "حدث خطأ في قاعدة البيانات أثناء جلب بياناتك.";
    }
} else {
    // المستخدم غير مسجل دخول
    header('Location: login.php?message=' . urlencode('يرجى تسجيل الدخول للوصول إلى إعدادات الحساب'));
    exit;
}
$page_title = 'إعدادات الحساب';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" href="image/favicon.png" type="image/png">
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/main_header.css">
    <!-- <link rel="stylesheet" href="css/dubizzle-inspired.css"> -->
    <link rel="stylesheet" href="css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/libs/intlTelInput.css">
        
    <style>
        :root {

            --primary-red: #4400ffff;

            --primary-dark: #212121;

            --light-gray: #f5f5f5;

            --text-color: #424242;

            --border-color: #e0e0e0;

            --success-color: #28a745;

            --danger-color: #ff0019ff;

            --border-radius: 12px;

        }

        body { 

            font-family: 'Cairo', sans-serif;

            background-color: var(--light-gray);

            line-height: 1.6;

            color: var(--text-color);

        }

        .container {

            max-width: 900px;

            margin: 40px auto;

            padding: 20px; 

            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        @media(max-width:767px){
            .container{
                margin:40px 15px;
            }
        }
            h1 {

                text-align: center;

                color: var(--primary-dark);

                margin-bottom: 30px;

            }

            .settings-section {

                margin-bottom: 30px;

                padding-bottom: 20px;

                border-bottom: 1px solid var(--border-color);

            }

            .settings-section:last-child {

                border-bottom: none;

            }

            .settings-section h2 {

                color: var(--primary-red);

                margin-bottom: 20px;

                text-align: center;

                font-size: 1.5em;

            }

            .form-group {

                margin-bottom: 20px;

            }

            .form-group label {
                display: block;
                z-index: 9999;
                font-weight: 600;
                font-size:12px;
                color: var(--text-color);
            }
            .form-group input[type="text"],
            .form-group input[type="email"],
            .form-group input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                font-size: 1em;
                box-sizing: border-box;
            }

            .form-group input[type="text"]:focus,

            .form-group input[type="email"]:focus,

            .form-group input[type="password"]:focus {

                border-color: var(--primary-red);

                outline: none;

                box-shadow: 0 0 0 3px rgba(192,0,0,0.2);

            }

            .btn-primary, .btn-danger {

                display: block;

                width: 100%;

                padding: 12px 20px;

                border: none;

                border-radius: 8px;

                font-size: 1.1em;

                font-weight: 700;

                cursor: pointer;

                transition: background-color 0.3s ease;

                text-align: center;

                color: #fff;

            }

            .btn-primary {

                background-color: var(--primary-red);

            }

            .btn-primary:hover {

                background-color: #a00000;

            }

            .btn-danger {

                background-color: var(--danger-color);

                margin-top: 20px;

            }

            .btn-danger:hover {

                background-color: #c82333;

            }

            .message-box {

                padding: 12px;

                margin-bottom: 20px;

                border-radius: 8px;

                text-align: center;

                font-weight: 600;

            }

            .message-box.success {

                background-color: #d4edda;

                color: #155724;

                border: 1px solid #c3e6cb;

            }

            .message-box.error {

                background-color: #f8d7da;

                color: #721c24;

                border: 1px solid #f5c6cb;

            }


            /* Modal for delete confirmation */

            .modal {

                display: none;

                position: fixed;

                z-index: 1000;

                left: 0;

                top: 0;

                width: 100%;

                height: 100%;

                overflow: auto;

                background-color: rgba(0,0,0,0.5);

                justify-content: center;

                align-items: center;

            }

            .modal-content {

                background-color: #fefefe;

                margin: auto;

                padding: 30px;

                border-radius: 10px;

                width: 80%;

                max-width: 500px;

                text-align: center;

                box-shadow: 0 5px 15px rgba(0,0,0,0.3);

            }

            .modal-content h3 {

                color: var(--primary-dark);

                margin-bottom: 20px;

            }

            .modal-content p {

                margin-bottom: 25px;

                color: var(--text-color);

            }

            .modal-buttons {

                display: flex;

                justify-content: center;

                gap: 15px;

            }

            .modal-buttons button {

                padding: 10px 25px;

                border: none;

                border-radius: 5px;

                cursor: pointer;

                font-size: 1em;

                font-weight: 600;

                transition: background-color 0.3s ease;

            }

            .modal-buttons .confirm-delete {

                background-color: var(--danger-color);

                color: white;

            }

            .modal-buttons .confirm-delete:hover {

                background-color: #c82333;

            }

            .modal-buttons .cancel-delete {

                background-color: #e0e0e0;

                color: var(--primary-dark);

            }

            .modal-buttons .cancel-delete:hover {

                background-color: #d0d0d0;

            }
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1001; 
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #888;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            text-align: center;
        }
        .modal-content .form-group { 
            text-align: right; 
            margin-top: 20px; 
            margin-bottom: 20px;
        }
        .modal-content label { 
            font-weight: 600; 
            margin-bottom: 8px; 
            display: block; 
        }
        .modal-content input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            direction: ltr;
            font-size: 16px;
        }
        .modal-error-message {
            color: #dc3545;
            font-weight: bold;
            margin-top: 10px;
            display: none; /* مخفي بشكل افتراضي */
            text-align: right;
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <main>
        <div class="container">
            <h1>إعدادات الحساب</h1>
            <?php if ($profile_success): ?><div class="message-box success"><?php echo $profile_success; ?></div><?php endif; ?>
            <?php if ($profile_error): ?><div class="message-box error"><?php echo $profile_error; ?></div><?php endif; ?>
            <?php if ($password_success): ?><div class="message-box success"><?php echo $password_success; ?></div><?php endif; ?>
            <?php if ($password_error): ?><div class="message-box error"><?php echo $password_error; ?></div><?php endif; ?>
            
            <section class="settings-section">
                <h2>تعديل معلومات الملف الشخصي</h2>
                <form id="user-info-form" action="php/update_profile.php" method="POST">
                    <div class="form-group">
                        <label for="username">اسم المستخدم:</label>
                        <input type="text" id="username" name="username" value="<?php echo $username; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">البريد الإلكتروني:</label>
                        <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">رقم الهاتف:</label>
                        <input type="tel" id="phone_number" name="phone_number" value="<?php echo $phone_number; ?>">
                    </div>
                    <button type="submit" class="btn-primary">حفظ التعديلات</button>
                </form>
            </section>

            <section class="settings-section">
                <h2>تغيير كلمة السر</h2>
                <form id="password-change-form" action="php/change_password.php" method="POST">
                    <div class="form-group">
                        <label for="current-password">كلمة السر الحالية:</label>
                        <input type="password" id="current-password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new-password">كلمة السر الجديدة:</label>
                        <input type="password" id="new-password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm-new-password">تأكيد كلمة السر الجديدة:</label>
                        <input type="password" id="confirm-new-password" name="confirm_new_password" required>
                    </div>
                    <button type="submit" class="btn-primary">تغيير كلمة السر</button>
                </form>
            </section>

            <section class="settings-section">
                <h2>حذف الحساب</h2>
                <p>سيؤدي حذف حسابك إلى إزالة جميع بياناتك وإعلاناتك بشكل دائم. هذا الإجراء يتطلب إدخال كلمة المرور.</p>
                <button type="button" class="btn-danger" id="delete-account-btn">حذف الحساب</button>
            </section>
        </div>
    </main>
    
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <h3>تأكيد حذف الحساب</h3>
            <p>للتأكيد، يرجى إدخال كلمة المرور الخاصة بك. لا يمكن التراجع عن هذا الإجراء.</p>
            
            <div class="form-group">
                <label for="delete-password">كلمة المرور:</label>
                <input type="password" id="delete-password" name="password" required placeholder="ادخل كلمة المرور هنا">
                <p id="delete-error-message" class="modal-error-message"></p>
            </div>

            <div class="modal-buttons">
                <button class="btn-secondary cancel-delete" id="cancel-delete-btn">إلغاء</button>
                <button class="btn-danger confirm-delete" id="confirm-delete-btn">نعم، احذف حسابي</button>
            </div>
        </div>
    </div>

    <script src="js/libs/intlTelInput.min.js"></script>
    <script src="js/main.js"></script>
    <script>
         document.addEventListener('DOMContentLoaded', () => {
            const phoneInput = document.getElementById("phone_number");
            let phoneIti;
            if (phoneInput) {
                phoneIti = window.intlTelInput(phoneInput, {
                    utilsScript: "js/libs/utils.js",
                    initialCountry: "auto",
                    geoIpLookup: function(callback) {
                        fetch("https://ipapi.co/json")
                            .then(res => res.json())
                            .then(data => callback(data.country_code))
                            .catch(() => callback("us"));
                    },
                    preferredCountries: ['sy', 'sa', 'ae', 'eg', 'jo'],
                    separateDialCode: true,
                });
                if (phoneInput.value) {
                    phoneIti.setNumber(phoneInput.value);
                }
            }
            
            const userInfoForm = document.getElementById('user-info-form');
            if (userInfoForm) {
                userInfoForm.addEventListener('submit', (e) => {
                    const hiddenInput = document.querySelector('input[name="phone"]');
                    if (phoneIti) {
                         // تحديث قيمة الحقل المخفي بالرقم الكامل قبل الإرسال
                        const fullNumber = phoneIti.getNumber();
                        const phoneInputToSubmit = document.getElementById('phone_number');
                        phoneInputToSubmit.value = fullNumber;
                    }
                });
            }

            const passwordChangeForm = document.getElementById('password-change-form');
            if (passwordChangeForm) {
                passwordChangeForm.addEventListener('submit', (e) => {
                    const newPass = document.getElementById('new-password').value;
                    const confirmPass = document.getElementById('confirm-new-password').value;
                    if (newPass !== confirmPass) {
                        e.preventDefault();
                        alert('كلمة المرور الجديدة وتأكيدها غير متطابقين!');
                    }
                });
            }

            // ================================================================
            // ====== الكود الجديد والمطور بالكامل لمنطق حذف الحساب ======
            // ================================================================
            const deleteBtn = document.getElementById('delete-account-btn');
            const deleteModal = document.getElementById('delete-modal');
            const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
            const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
            const passwordInput = document.getElementById('delete-password');
            const errorMessage = document.getElementById('delete-error-message');

            function openDeleteModal() {
                passwordInput.value = ''; // تفريغ الحقل عند الفتح
                errorMessage.textContent = ''; // إفراغ رسالة الخطأ
                errorMessage.style.display = 'none'; // إخفاء أي رسالة خطأ قديمة
                deleteModal.style.display = 'flex';
                passwordInput.focus();
            }

            function closeDeleteModal() {
                deleteModal.style.display = 'none';
            }
            
            if (deleteBtn) {
                deleteBtn.addEventListener('click', openDeleteModal);
            }

            if (cancelDeleteBtn) {
                cancelDeleteBtn.addEventListener('click', closeDeleteModal);
            }
          
            if (confirmDeleteBtn) {
                confirmDeleteBtn.addEventListener('click', async () => {
                    const password = passwordInput.value;

                    if (password.trim() === '') {
                        errorMessage.textContent = 'الرجاء إدخال كلمة المرور.';
                        errorMessage.style.display = 'block';
                        return;
                    }

                    errorMessage.style.display = 'none';
                    confirmDeleteBtn.disabled = true;
                    confirmDeleteBtn.textContent = 'جاري الحذف...';

                    const formData = new FormData();
                    formData.append('password', password);

                    try {
                        const response = await fetch('php/delete_account.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const result = await response.json();

                        if (result.success) {
                            alert(result.message || 'تم حذف حسابك بنجاح.');
                            window.location.href = 'index.html';
                        } else {
                            errorMessage.textContent = result.message || 'فشل حذف الحساب.';
                            errorMessage.style.display = 'block';
                        }
                    } catch (error) {
                        console.error('Error deleting account:', error);
                        errorMessage.textContent = 'حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.';
                        errorMessage.style.display = 'block';
    
                    } finally {
                        confirmDeleteBtn.disabled = false;
                        confirmDeleteBtn.textContent = 'نعم، احذف حسابي';
                    }
                });
            }
        });
    </script>
</body>
</html>