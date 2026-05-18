<!-- verify_reset_otp.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور</title>
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dubizzle-inspired.css">
        <link rel="stylesheet" href="css/main_header.css" />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="auth-container">
        <div class="auth-box">
            <h2>إعادة تعيين كلمة المرور</h2>
            <p class="auth-subtitle">أدخل الرمز الذي وصلك وكلمة المرور الجديدة.</p>
            <div id="message-box" class="error-message-box" style="display: none;"></div>
            
            <form id="reset-password-form" method="post" action="php/reset_password.php">
                <div class="form-group">
                    <input type="text" id="otp-input" name="otp_input" required placeholder=" " inputmode="numeric" pattern="[0-9]{6}" maxlength="6">
                    <label for="otp-input">رمز التحقق (OTP)</label>
                </div>
                <div class="form-group">
                    <input type="password" id="password" name="password" required placeholder=" ">
                    <label for="password">كلمة المرور الجديدة</label>
                </div>
                <div class="form-group">
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder=" ">
                    <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                </div>
                <button type="submit" class="submit-btn" id="reset-btn">تحديث كلمة المرور</button>
            </form>
        </div>
    </div>

    <script>
        // كود جافاسكريبت مشابه لصفحة التحقق من التسجيل
        const form = document.getElementById('reset-password-form');
        const messageBox = document.getElementById('message-box');
        const resetBtn = document.getElementById('reset-btn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                // عرض خطأ
                return;
            }

            resetBtn.disabled = true;
            resetBtn.textContent = 'جاري التحديث...';

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!response.ok) throw new Error(result.message);

                // نجح التحديث
                // عرض رسالة نجاح
                setTimeout(() => {
                    window.location.href = 'login.php?status=reset_success';
                }, 2000);

            } catch (error) {
                // عرض رسالة خطأ
                resetBtn.disabled = false;
                resetBtn.textContent = 'تحديث كلمة المرور';
            }
        });
    </script>
</body>
</html>