// ========================================================================
// Syriazzle - Firebase Service Worker (النسخة النهائية 3.1 - متوافقة)
// ========================================================================

// استخدام importScripts المتوافقة مع النسخة 9.0.0
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

// --- بيانات الإعداد الخاصة بك ---
const firebaseConfig = {
  apiKey: "AIzaSyDWVoTU0pFLo279Gu1_IY740Fxxh5MwbzQ",
  authDomain: "syriazzle-a90cb.firebaseapp.com",
  projectId: "syriazzle-a90cb",
  storageBucket: "syriazzle-a90cb.appspot.com",
  messagingSenderId: "613319682047",
  appId: "1:613319682047:web:4f865224e7a605895169ca"
};

// تهيئة Firebase
firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();

// معالج الرسائل في الخلفية
messaging.onBackgroundMessage(function(payload) {
    console.log('[firebase-messaging-sw.js] Received background message: ', payload);

    const notificationTitle = payload.notification.title;
    const notificationOptions = {
        body: payload.notification.body,
        icon: payload.notification.icon || '/image/logo1.png',
        badge: '/image/favicon1.png'
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});