<?php require_once 'php/db_connect.php';?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an account</title>
    <link rel="icon" href="image/favicon.png" type="image/png">
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dubizzle-inspired.css">
    <link rel="stylesheet" href="css/main_header.css" />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/auth.css">
    <!-- 1. إضافة ملف CSS الخاص بمكتبة أرقام الهواتف -->
    <link rel="stylesheet" href="css/libs/intlTelInput.css">
    <style>
        /* 2. تنسيقات بسيطة لمواءمة ألوان المكتبة مع تصميمك */
        .iti { width: 100%; direction: ltr; }
        .form-group .iti__tel-input { 
            direction: rtl; 
            padding-right: 52px !important; 
            padding-left: 6px !important; 
        }
        /* عند التركيز، اجعل الحدود بلون موقعك الرئيسي */
        .iti__input:focus, .iti--allow-dropdown .iti__flag-container:focus {
            border-color: var(--primary-red, #e60000) !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="auth-container">
        <div class="auth-box">
            <h2>إنشاء حساب جديد</h2>
            <p class="auth-subtitle">انضم إلينا وابدأ بنشر إعلاناتك مجانًا.</p>
            <div id="error-message" class="error-message-box" style="display: none;"></div>
            <form id="register-form" method="post" action="#">
                <div class="form-group">
                    <input type="text" id="username" name="username" required placeholder=" ">
                    <label for="username">اسم المستخدم</label>
                </div>
                <div class="form-group">
                    <input type="email" id="email" name="email" placeholder=" ">
                    <label for="email">البريد الإلكتروني (اختياري)</label>
                </div>
                <div class="form-group">
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <input type="password" id="password" name="password" required placeholder=" ">
                    <label for="password">كلمة المرور</label>
                </div>
                <div class="form-group">
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder=" ">
                    <label for="confirm_password">تأكيد كلمة المرور</label>
                </div>
                

                <button type="submit" class="submit-btn" id="register-btn">إنشاء حساب والمتابعة</button>
            </form>
            <div class="switch-auth">
                <p>لديك حساب بالفعل؟ <a href="login.php">سجل الدخول</a></p>
            </div>
        </div>
    </div>
    
    <script src="js/libs/intlTelInput.min.js" defer></script>
    <script src="js/register.js"></script>
</body>
</html>