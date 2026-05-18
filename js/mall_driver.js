// ========================================================================
// Syriazzle Mall - In-House Driver Logic (النسخة النهائية 1.0)
// ========================================================================

document.addEventListener('DOMContentLoaded', () => {
    // --- 1. تحديث الموقع الجغرافي في الخلفية ---
    function updateLocation() {
        if (!navigator.geolocation) {
            console.warn("Geolocation is not supported by this browser.");
            return;
        }
        // طلب صلاحيات الموقع
        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const { latitude, longitude } = position.coords;
                const formData = new FormData();
                formData.append('latitude', latitude);
                formData.append('longitude', longitude);
                
                try {
                    // إرسال الموقع إلى الملف المخصص لسائقي المول
                    await fetch('php/update_inhouse_driver_location.php', {
                        method: 'POST',
                        body: formData
                    });
                    console.log(`Location updated: ${latitude}, ${longitude}`);
                } catch (error) {
                    console.error('Failed to send location:', error);
                }
            },
            (error) => { 
                // عرض رسالة للمستخدم إذا رفض صلاحية الموقع
                if(error.code === error.PERMISSION_DENIED) {
                    console.error("User denied Geolocation, tracking will not work.");
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 } // إعدادات دقيقة
        );
    }

    // قم بتحديث الموقع فورًا عند فتح الصفحة، ثم كل 30 ثانية
    updateLocation();
    const locationInterval = setInterval(updateLocation, 30000); // 30 seconds

    // --- 2. التعامل مع أزرار تحديث حالة الطلب ---
    const tasksList = document.getElementById('tasks-list');
    if (tasksList) {
        tasksList.addEventListener('click', async (e) => {
            const actionButton = e.target.closest('.btn-action, .btn-delivered');
            if (!actionButton) return;

            const orderId = actionButton.dataset.orderId;
            const newStatus = actionButton.dataset.action;
            const confirmMessage = newStatus === 'delivered'
                ? `هل أنت متأكد من أنك قمت بتسليم الطلب رقم #${orderId} بنجاح؟`
                : `هل أنت متأكد من استلامك للطلب رقم #${orderId} من المكتب؟`;

            if (!confirm(confirmMessage)) {
                return;
            }

            actionButton.disabled = true;
            actionButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('status', newStatus);

            try {
                const response = await fetch('php/update_mall_order_status.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    // تحديث الواجهة أو إعادة تحميل الصفحة لعرض الحالة الجديدة
                    alert(result.message);
                    window.location.reload(); 
                } else {
                    alert('فشل تحديث الطلب: ' + result.message);
                    actionButton.disabled = false;
                    // إعادة النص الأصلي للزر
                    if (newStatus === 'delivered') {
                        actionButton.innerHTML = '<i class="fas fa-check-circle"></i> تم التوصيل بنجاح';
                    } else {
                        actionButton.innerHTML = '<i class="fas fa-motorcycle"></i> أنا استلمت الطلب';
                    }
                }
            } catch (error) {
                alert('فشل الاتصال بالخادم.');
                actionButton.disabled = false;
                // إعادة النص الأصلي
            }
        });
    }
});