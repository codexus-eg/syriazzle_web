<?php require_once 'php/db_connect.php';?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in</title>
    <link rel="icon" href="image/favicon.png" type="image/png">
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/dubizzle-inspired.css">
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/main_header.css" />
    <link rel="stylesheet" href="css/libs/intlTelInput.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="auth-container">
        <div class="auth-box">
            <h2>تسجيل الدخول</h2>
            <p class="auth-subtitle">مرحبًا بعودتك!</p>
            <div id="error-message" class="error-message-box" style="display: none;"></div>
            
            <form id="login-form">
                <div class="login-method-switcher">
                    <input type="radio" id="login-with-phone" name="login_method" value="phone" checked>
                    <label for="login-with-phone">رقم الهاتف</label>

                    <input type="radio" id="login-with-email" name="login_method" value="email">
                    <label for="login-with-email">البريد الإلكتروني</label>
                </div>
                
                <div class="form-group" id="phone-group">
                    <!-- <label for="phone">رقم الهاتف</label> -->
                    <input type="tel" id="phone" name="phone">
                </div>

                <div class="form-group topplace" id="email-group" style="display: none;">
                    <label for="email">البريد الإلكتروني</label>
                    <input type="email" id="email" name="email">
                </div>
                
                <div class="form-group topplace">
                    <label for="password">كلمة المرور</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="forgot-password-link">
                    <a href="forgot_password.php">هل نسيت كلمة المرور؟</a>
                </div>

                <div class="form-group remember-me-group">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label for="remember_me">تذكرني</label>
                </div>
                
                <button type="submit" class="submit-btn" id="login-btn">تسجيل الدخول</button>
            </form>

            <div class="switch-auth">
                <p>ليس لديك حساب؟ <a href="register.php">أنشئ حسابك الآن</a></p>
            </div>
        </div>
    </div>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const redirectUrl = params.get('redirect_url');
            
            if (redirectUrl) {
                // احفظه بالاسم الذي يفهمه js/login.js
                localStorage.setItem('redirectAfterLogin', redirectUrl);
            }
        });
    </script>
    <script src="js/libs/intlTelInput.min.js" defer></script>
    <script src="js/login.js" defer></script>
</body>
</html>