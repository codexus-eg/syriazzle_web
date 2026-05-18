<!-- forgot_password.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور</title>
    <!-- قم بتضمين نفس ملفات CSS التي تستخدمها في صفحات التسجيل -->
    <link rel="stylesheet" href="css/normalize.css">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dubizzle-inspired.css">
    <link rel="stylesheet" href="css/main_header.css" />
    <link rel="stylesheet" href="css/auth.css">
    <link rel="stylesheet" href="css/libs/intlTelInput.css">
    <style>.iti { width: 100%; direction: ltr; }</style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="auth-container">
        <div class="auth-box">
            <h2>هل نسيت كلمة المرور؟</h2>
            <p class="auth-subtitle">لا تقلق. أدخل رقم هاتفك المسجل وسنرسل لك رمز تحقق.</p>
            <div id="message-box" class="error-message-box" style="display: none;"></div>
            
            <form id="forgot-password-form">
                <div class="form-group">
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <button type="submit" class="submit-btn" id="send-btn">إرسال رمز التحقق</button>
            </form>
            <div class="switch-auth">
                <p>تذكرت كلمة المرور؟ <a href="login.php">سجل الدخول</a></p>
            </div>
        </div>
    </div>

    <script src="js/libs/intlTelInput.min.js"></script>
    <script src="js/libs/utils.js"></script>
    <script>
        const phoneInput = document.getElementById('phone');
        const form = document.getElementById('forgot-password-form');
        const messageBox = document.getElementById('message-box');
        const sendBtn = document.getElementById('send-btn');

        const iti = window.intlTelInput(phoneInput, {
            initialCountry: "sy",
            separateDialCode: true,
            utilsScript: "js/libs/utils.js",
        });

        const displayMessage = (message, isError = true) => {
            messageBox.textContent = message;
            messageBox.className = isError ? 'error-message-box error' : 'error-message-box success';
            messageBox.style.display = 'block';
        };

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!iti.isValidNumber()) {
                displayMessage('الرجاء إدخال رقم هاتف صحيح.');
                return;
            }

            sendBtn.disabled = true;
            sendBtn.textContent = 'جاري الإرسال...';
            messageBox.style.display = 'none';

            const formData = new FormData();
            formData.append('phone', iti.getNumber()); // إرسال الرقم بالصيغة الدولية

            try {
                const response = await fetch('php/send_reset_otp.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'حدث خطأ ما.');
                }
                
                // نجح الإرسال، نوجه المستخدم لصفحة التحقق
                window.location.href = 'verify_reset_otp.php';

            } catch (error) {
                displayMessage(error.message, true);
                sendBtn.disabled = false;
                sendBtn.textContent = 'إرسال رمز التحقق';
            }
        });
    </script>
</body>
</html>