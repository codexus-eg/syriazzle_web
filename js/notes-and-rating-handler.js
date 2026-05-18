// js/notes-handler.js

document.addEventListener('DOMContentLoaded', () => {
    const submitNotesBtn = document.getElementById('submit-notes-btn');
    const importantNotesInput = document.getElementById('important-notes-input');

    if (submitNotesBtn && importantNotesInput) {
        submitNotesBtn.addEventListener('click', async () => {
            const notes = importantNotesInput.value.trim(); // احصل على قيمة الإدخال وأزل المسافات البيضاء الزائدة

            if (notes) {
                console.log('جارٍ إرسال الملاحظات:', notes);
 ---
                try {
                    const response = await fetch('https://syriazzle.sy/php/submit_feedback.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ note_content: notes }), // إرسال الملاحظات كـ JSON
                    });

                    if (response.ok) {
                        const result = await response.json();
                        console.log('تم إرسال الملاحظات بنجاح:', result);
                        alert('تم إرسال ملاحظاتك بنجاح!'); // رسالة نجاح للمستخدم
                        importantNotesInput.value = ''; // مسح حقل الإدخال بعد الإرسال
                    } else {
                        const errorData = await response.json();
                        console.error('فشل إرسال الملاحظات:', response.status, errorData);
                        alert('حدث خطأ أثناء إرسال الملاحظات. يرجى المحاولة مرة أخرى.'); // رسالة خطأ للمستخدم
                    }
                } catch (error) {
                    console.error('خطأ في إرسال الملاحظات:', error);
                    alert('حدث خطأ في الاتصال بالخادم. يرجى التحقق من اتصالك بالإنترنت والمحاولة مرة أخرى.'); // خطأ في الشبكة
                }
                // --- انتهاء: إرسال البيانات إلى الخادم (Backend) ---

            } else {
                alert('الرجاء كتابة ملاحظاتك قبل الإرسال.'); // تنبيه المستخدم إذا كان الحقل فارغًا
            }
        });
    } else {
        console.warn('لم يتم العثور على زر الإرسال أو حقل الإدخال للملاحظات.');
    }
});