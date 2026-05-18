// ملف: js/dashboard.js

document.addEventListener('DOMContentLoaded', () => {

    // --- التحقق الأولي: هل المستخدم مسجل دخوله أصلاً؟ ---
    const userToken = localStorage.getItem('userToken');
    if (!userToken) {
        // إذا لم يكن هناك توكن، لا تضيع وقت المستخدم، وجهه فورًا للدخول
        alert('يجب عليك تسجيل الدخول أولاً لعرض هذه الصفحة.');
        // احفظ هذه الصفحة كوجهة للعودة إليها بعد الدخول
        localStorage.setItem('redirectAfterLogin', window.location.href);
        window.location.href = 'login.html';
        return; // أوقف تنفيذ بقية الكود
    }

    // --- منطق التفاعلية للصفحة ---
    const filterTabs = document.querySelectorAll('.tab-btn');
    const adCards = document.querySelectorAll('.ad-card');
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const noAdsMessage = document.querySelector('.no-ads-message');

    // 1. وظيفة الفرز بالنقر على التابات (نشط، مباع...)
    filterTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // إزالة الكلاس النشط من كل التابات وإضافته للتاب المختار
            filterTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const filterValue = tab.getAttribute('data-filter');
            let visibleAdsCount = 0;

            // المرور على كل بطاقات الإعلانات وإظهار/إخفاء المناسب منها
            adCards.forEach(card => {
                const cardStatus = card.getAttribute('data-status');
                if (filterValue === 'all' || cardStatus === filterValue) {
                    card.style.display = 'flex';
                    visibleAdsCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // إظهار رسالة "لا توجد إعلانات" إذا لم تكن هناك نتائج
            if (visibleAdsCount === 0) {
                noAdsMessage.style.display = 'block';
            } else {
                noAdsMessage.style.display = 'none';
            }
        });
    });

    // 2. وظيفة زر الحذف مع رسالة تأكيد
    deleteButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            // جد البطاقة الأب للزر الذي تم النقر عليه
            const adCardToDelete = e.target.closest('.ad-card');
            
            // استخدم مكتبة confirm المدمجة في المتصفح لسؤال المستخدم
            const userIsSure = confirm('هل أنت متأكد من أنك تريد حذف هذا الإعلان بشكل نهائي؟');

            if (userIsSure) {
                // إذا أكد المستخدم، قم بحذف البطاقة من الواجهة
                console.log('User confirmed deletion. Sending request to backend...');
                adCardToDelete.style.transition = 'opacity 0.5s';
                adCardToDelete.style.opacity = '0';
                
                // بعد انتهاء التأثير، احذف العنصر تمامًا
                setTimeout(() => {
                    adCardToDelete.remove();
                    // هنا لاحقًا، ستضع كود fetch لإرسال طلب الحذف للباك إند
                }, 500);

            } else {
                // إذا ضغط المستخدم "إلغاء"، لا تفعل شيئًا
                console.log('User canceled deletion.');
            }
        });
    });

});