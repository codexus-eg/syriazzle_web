document.addEventListener("DOMContentLoaded", function() {
    const loginForm = document.getElementById('login-form');
    const phoneGroup = document.getElementById('phone-group');
    const emailGroup = document.getElementById('email-group');
    const phoneInput = document.getElementById('phone');
    const emailInput = document.getElementById('email');
    const loginMethodRadios = document.querySelectorAll('input[name="login_method"]');
    const passwordInput = document.getElementById('password');
    const errorMessageDiv = document.getElementById('error-message');
    const loginBtn = document.getElementById('login-btn');
    let iti = null;

    function initIntlTel() {
        if (window.intlTelInput) {
            iti = window.intlTelInput(phoneInput, {
                initialCountry: "sy",
                separateDialCode: true,
                utilsScript: "js/libs/utils.js",
                placeholderNumberType: "MOBILE",
            });
        } else {
            setTimeout(initIntlTel, 100);
        }
    }
    
    function switchInputFields(method) {
        if (method === 'phone') {
            phoneGroup.style.display = 'block';
            emailGroup.style.display = 'none';
        } else {
            phoneGroup.style.display = 'none';
            emailGroup.style.display = 'block';
        }
    }

    loginMethodRadios.forEach(radio => {
        radio.addEventListener('change', (e) => switchInputFields(e.target.value));
    });

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        loginBtn.disabled = true;
        loginBtn.textContent = 'جار التحقق...';
        errorMessageDiv.style.display = 'none';

        const formData = new FormData();
        const selectedMethod = document.querySelector('input[name="login_method"]:checked').value;

        let identifier;
        if (selectedMethod === 'phone') {
            if (!iti || !iti.isValidNumber()) {
                showError("رقم الهاتف الذي أدخلته غير صحيح.");
                return;
            }
            identifier = iti.getNumber();
        } else {
            identifier = emailInput.value;
            if (!identifier) {
                showError("يرجى إدخال البريد الإلكتروني.");
                return;
            }
        }

        formData.append('email_or_phone', identifier);
        formData.append('password', passwordInput.value);
        
        const rememberMeCheckbox = document.getElementById('remember_me');
        formData.append('remember_me', rememberMeCheckbox.checked ? '1' : '0');

        try {
            const response = await fetch('login_1.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'فشل تسجيل الدخول.');
            }
            
            alert(data.message);
            
            localStorage.setItem('userToken', 'user_is_logged_in'); 

            const redirectUrl = localStorage.getItem('redirectAfterLogin');
            if (redirectUrl) {
                localStorage.removeItem('redirectAfterLogin');
                window.location.href = redirectUrl;
            } else {
                window.location.href = 'index.php';
            }

        } catch (error) {
            showError(error.message);
        }
    });

    function showError(message) {
        errorMessageDiv.textContent = message;
        errorMessageDiv.style.display = 'block';
        loginBtn.disabled = false;
        loginBtn.textContent = 'تسجيل الدخول';
    }

    initIntlTel();
    switchInputFields('phone');
});