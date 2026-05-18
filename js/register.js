// js/register.js
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('register-form');
    const phoneInput = document.getElementById('phone');
    const errorMessageDiv = document.getElementById('error-message');
    const registerBtn = document.getElementById('register-btn');

    // 1. تهيئة مكتبة أرقام الهواتف
    const iti = window.intlTelInput(phoneInput, {
        initialCountry: "sy",
        separateDialCode: true,
        utilsScript: "js/libs/utils.js", 
    });

    function displayError(message) {
        errorMessageDiv.textContent = message;
        errorMessageDiv.style.display = 'block';
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault(); // منع الإرسال

        // التحقق من صحة البيانات الأساسية
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (password !== confirmPassword) {
            displayError('كلمتا المرور غير متطابقتين.');
            return;
        }

        if (!iti.isValidNumber()) {
            displayError('الرجاء إدخال رقم هاتف صحيح.');
            return;
        }

        registerBtn.disabled = true;
        registerBtn.textContent = 'جاري إرسال الرمز...';

        // ⭐️ الخطوة 1: إرسال رقم الهاتف فقط للحصول على OTP
        const fullPhoneNumber = iti.getNumber();
        const otpFormData = new FormData();
        otpFormData.append('phone', fullPhoneNumber);

        try {
            const response = await fetch('php/send_otp.php', {
                method: 'POST',
                body: otpFormData
            });

            const result = await response.json();

            if (result.success) {
                // ⭐️ الخطوة 2: نجح الإرسال، الآن نخزن بيانات المستخدم مؤقتاً
                const userData = {
                    username: document.getElementById('username').value,
                    email: document.getElementById('email').value,
                    phone: fullPhoneNumber, // الرقم الدولي الكامل
                    password: password,
                };
                
                // نستخدم sessionStorage لأنها تنتهي بإغلاق التبويب
                sessionStorage.setItem('tempUserData', JSON.stringify(userData));

                // ⭐️ الخطوة 3: نوجه المستخدم إلى صفحة التحقق
                window.location.href = 'verify_otp.php';

            } else {
                displayError(result.message || 'حدث خطأ غير متوقع.');
                registerBtn.disabled = false;
                registerBtn.textContent = 'إنشاء حساب والمتابعة';
            }
        } catch (error) {
            displayError('فشل الاتصال بالخادم. يرجى التحقق من اتصالك بالإنترنت.');
            registerBtn.disabled = false;
            registerBtn.textContent = 'إنشاء حساب والمتابعة';
        }
    });
});