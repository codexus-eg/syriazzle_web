<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التحقق من الرمز</title>
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
            <h2>التحقق من الحساب</h2>
            <p class="auth-subtitle">تم إرسال رمز تحقق إلى رقمك عبر واتساب. يرجى إدخاله أدناه.</p>
            <div id="error-message" class="error-message-box" style="display: none;"></div>
            
            <form id="verify-form" method="post" action="register_1.php">
                <div class="form-group">
                    <input type="text" id="otp-input" name="otp_input" required placeholder=" " inputmode="numeric" pattern="\d*">
                    <label for="otp-input">رمز التحقق</label>
                </div>
                <button type="submit" class="submit-btn" id="verify-btn">تحقق وإنشاء الحساب</button>
            </form>
        </div>
    </div>

    <script>
        // دالة مساعدة لعرض الرسائل في الواجهة
        const displayMessage = (message, isError = true) => {
            const errorMessageDiv = document.getElementById('error-message');
            errorMessageDiv.textContent = message;
            errorMessageDiv.style.display = 'block';
            errorMessageDiv.style.backgroundColor = isError ? '#fdd' : '#dfd';
            errorMessageDiv.style.color = isError ? '#900' : '#090';
        };

        document.addEventListener('DOMContentLoaded', () => {
            const verifyForm = document.getElementById('verify-form');
            if (!verifyForm) return;

            verifyForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const verifyBtn = document.getElementById('verify-btn');
                verifyBtn.disabled = true;
                verifyBtn.textContent = 'جاري التحقق...';
                document.getElementById('error-message').style.display = 'none';

                const userData = JSON.parse(sessionStorage.getItem('tempUserData'));
                if (!userData) {
                    displayMessage('انتهت الجلسة، يرجى البدء من جديد.', true);
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'تحقق وإنشاء الحساب';
                    return;
                }
                
                // بناء FormData وإرسالها إلى register_1.php
                const formData = new FormData();
                formData.append('username', userData.username);
                formData.append('email', userData.email);
                formData.append('phone', userData.phone);
                formData.append('password', userData.password);
                formData.append('otp_input', document.getElementById('otp-input').value);

                try {
                    const response = await fetch(verifyForm.action, { 
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'فشل التحقق.');
                    }

                    sessionStorage.removeItem('tempUserData');
                    displayMessage('تم إنشاء حسابك بنجاح! سيتم تحويلك إلى صفحة تسجيل الدخول.', false); 
                    
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1000);

                } catch (error) {
                    displayMessage(error.message, true);
                    verifyBtn.disabled = false;
                    verifyBtn.textContent = 'تحقق وإنشاء الحساب';
                }
            });
        });
    </script>
</body>
</html>