// ========================================================================
// Syriazzle - Firebase Notifications Initializer (النسخة النهائية 7.0 - المضمونة)
// ========================================================================

(function() {
    const firebaseConfig = {
      apiKey: "AIzaSyDWVoTU0pFLo279Gu1_IY740Fxxh5MwbzQ",
      authDomain: "syriazzle-a90cb.firebaseapp.com",
      projectId: "syriazzle-a90cb",
      storageBucket: "syriazzle-a90cb.firebasestorage.app",
      messagingSenderId: "613319682047",
      appId: "1:613319682047:web:4f865224e7a605895169ca"
    };
    const vapidKey = "BNzkxgYhAJVbsVde2NRbnRfbN0vYiYX-wcy8FuC_XvRTNoD1LpZOXhJCuw2JRXfYZsKNvMJFrCQH_l5azwmmsdI";

    function sendTokenToServer(token) {
        const sentToken = localStorage.getItem('sentFCMToken');
        if (sentToken === token) {
            console.log("Token already sent to server.");
            return;
        }
        fetch('php/save_fcm_token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: token }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Token saved to server.');
                localStorage.setItem('sentFCMToken', token);
            }
        })
        .catch(console.error);
    }
    
    // --- المنطق الرئيسي الذي يتم تشغيله بعد تحميل الصفحة بالكامل ---
    function main() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.log('Push messaging is not supported.');
            return;
        }

        // **الإصلاح الحاسم هنا: نبحث عن العناصر بعد تحميل الصفحة**
        const enableBtn = document.getElementById('enable-notifications-btn');
        const permissionModal = document.getElementById('notification-permission-modal');
        const acceptBtn = document.getElementById('accept-notifications-btn');
        const declineBtn = document.getElementById('decline-notifications-btn');

        // إذا لم تكن الأزرار موجودة في هذه الصفحة، لا تفعل شيئًا
        if (!enableBtn || !permissionModal || !acceptBtn || !declineBtn) {
            return;
        }
        
        // تسجيل عامل الخدمة بهدوء في الخلفية
        navigator.serviceWorker.register('/firebase-messaging-sw.js').catch(err => {
            console.error('Service Worker registration failed: ', err);
        });

        // دالة لتحديث شكل الزر بناءً على الحالة الحالية
        function updateButtonState() {
            if (Notification.permission === 'granted') {
                enableBtn.classList.add('enabled');
                enableBtn.innerHTML = '<i class="fas fa-bell"></i>';
                enableBtn.title = 'تم تفعيل الإشعارات';
                enableBtn.disabled = true; // نجعله غير قابل للنقر بعد التفعيل
            } else {
                enableBtn.classList.remove('enabled');
                enableBtn.innerHTML = '<i class="fas fa-bell-slash"></i>';
                enableBtn.title = 'تفعيل إشعارات المتصفح';
                enableBtn.disabled = false;
            }
        }
        
        // دالة لطلب الإذن والحصول على الرمز
        async function askForPermissionAndGetToken() {
            permissionModal.classList.remove('visible');
            enableBtn.disabled = true;
            enableBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
                const messaging = firebase.messaging();
                
                // اطلب الإذن الآن
                const permission = await Notification.requestPermission();
                
                if (permission === 'granted') {
                    const registration = await navigator.serviceWorker.ready;
                    const currentToken = await messaging.getToken({ vapidKey, serviceWorkerRegistration: registration });
                    
                    if (currentToken) {
                        sendTokenToServer(currentToken);
                    }
                } else {
                    console.log('User denied the permission prompt.');
                }
                // تحديث شكل الزر في كل الحالات (سواء وافق أو رفض)
                updateButtonState();

            } catch (err) {
                console.error('An error occurred during permission request: ', err);
                updateButtonState(); // أعد الزر إلى حالته الأصلية عند حدوث خطأ
            }
        }

        // **الإصلاح الحاسم هنا: ربط الأحداث بالأزرار**
        enableBtn.addEventListener('click', () => {
            // تحقق أولاً، إذا كان الإذن محظورًا تمامًا، اعرض رسالة مساعدة
            if (Notification.permission === 'denied') {
                alert('لقد قمت بحظر الإشعارات سابقًا. يرجى تفعيلها يدويًا من إعدادات المتصفح لهذا الموقع.');
                return;
            }
            permissionModal.classList.add('visible');
        });

        declineBtn.addEventListener('click', () => {
            permissionModal.classList.remove('visible');
        });

        acceptBtn.addEventListener('click', askForPermissionAndGetToken);
        
        // التحقق من حالة الزر عند تحميل الصفحة
        updateButtonState();
    }

    // **التأكد من تشغيل كل شيء فقط بعد تحميل الصفحة بالكامل**
    window.addEventListener('load', main);

})();