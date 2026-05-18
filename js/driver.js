// ========================================================================
// Syriazzle - Marketplace Driver Logic (Final Stable Production v10.0)
// ========================================================================

(() => {
    // 1. التحقق من وجود البيانات الأساسية
    if (typeof DRIVER_DATA === 'undefined') {
        console.error("%c Syriazzle Error: DRIVER_DATA is not defined! ", "background: red; color: white;");
        return;
    }

    // 2. الإعدادات والثوابت
    const SETTINGS = {
        FETCH_INTERVAL: 7000,       // فحص كل 7 ثوانٍ لسرعة قصوى
        LOCATION_INTERVAL: 15000,   // تحديث الموقع كل 15 ثانية
        MODAL_TIMEOUT: 45,          // 45 ثانية لقبول الطلب
        ALERT_SOUND: 'assets/sounds/alert.mp3'
    };

    let { driverId, isInitiallyOnline, hasActiveTask, activeTask } = DRIVER_DATA;
    let isOnline = isInitiallyOnline;
    let isModalOpen = false;
    let ignoredOrders = new Set();
    let modalCountdown = null;
    let tasksTimer = null;
    let locationTimer = null;

    const alertAudio = new Audio(SETTINGS.ALERT_SOUND);
    alertAudio.loop = true;

    // --- 3. حقن التنسيقات (Injected CSS) لضمان التمركز المطلق والجمالية ---
    const injectStyles = () => {
        if (document.getElementById('syriazzle-driver-v10-css')) return;
        const style = document.createElement('style');
        style.id = 'syriazzle-driver-v10-css';
        style.innerHTML = `
            .sy-overlay {
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px);
                display: flex; align-items: center; justify-content: center;
                z-index: 9999999; padding: 20px; box-sizing: border-box;
            }
            .sy-modal {
                background: #ffffff; border-radius: 30px; width: 100%; max-width: 400px;
                padding: 30px; position: relative; direction: rtl; text-align: right;
                box-shadow: 0 30px 70px rgba(0,0,0,0.6);
                animation: syPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            @keyframes syPop { from { transform: scale(0.7); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            
            .m-header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
            .m-timer-badge {
                width: 50px; height: 50px; border: 4px solid #e60000; border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                font-weight: 900; color: #e60000; font-size: 1.3rem;
            }
            .m-details-card { background: #f8f9fa; border-radius: 20px; padding: 20px; margin-bottom: 25px; border: 1px solid #eee; }
            .m-info-line { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; font-size: 1rem; color: #333; }
            .m-info-line i { color: #007bff; width: 20px; text-align: center; }
            
            .m-price-section { border-top: 1px solid #eee; padding-top: 15px; margin-top: 10px; }
            .m-price-flex { display: flex; justify-content: space-between; margin-bottom: 5px; font-weight: bold; }
            .m-profit-highlight { color: #28a745; font-size: 1.3rem; }

            .m-input-group { margin-top: 20px; text-align: center; }
            .m-input-group label { display: block; font-size: 0.85rem; color: #777; margin-bottom: 10px; }
            .m-input-group input {
                width: 100%; padding: 15px; border: 2px solid #ddd; border-radius: 15px;
                text-align: center; font-size: 1.6rem; font-weight: 900; color: #2d3436; outline: none; transition: 0.3s;
            }
            .m-input-group input:focus { border-color: #007bff; background: #f0f7ff; }

            .m-footer-actions { display: flex; gap: 15px; margin-top: 30px; }
            .m-sy-btn {
                flex: 1; padding: 18px; border: none; border-radius: 18px;
                font-weight: 800; font-size: 1.15rem; cursor: pointer;
                font-family: 'Cairo', sans-serif; transition: 0.2s;
            }
            .m-sy-btn.accept { background: #28a745; color: #fff; box-shadow: 0 5px 15px rgba(40,167,69,0.3); }
            .m-sy-btn.ignore { background: #636e72; color: #fff; }
            .m-sy-btn:active { transform: scale(0.95); }
        `;
        document.head.appendChild(style);
    };

    // --- 4. محرك إدارة المهام (The Dispatcher Engine) ---

    async function fetchTasks() {
        // حماية: لا نبحث عن مهام إذا كان السائق غير متصل، أو لديه مهمة، أو النافذة مفتوحة
        if (!isOnline || hasActiveTask || isModalOpen) {
            return;
        }

        try {
            const timestamp = new Date().getTime();
            const response = await fetch(`php/fetch_available_tasks.php?t=${timestamp}`);
            const tasks = await response.json();

            if (Array.isArray(tasks) && tasks.length > 0) {
                console.log("%c Syriazzle: New Task Found! ", "background: #28a745; color: white;", tasks[0]);
                showOrderModal(tasks[0]);
            }
        } catch (error) {
            console.error("Syriazzle: Task polling failed.");
        }
    }

    function showOrderModal(task) {
        if (isModalOpen || ignoredOrders.has(task.order_id)) return;
        isModalOpen = true;
        
        injectStyles();
        alertAudio.play().catch(() => console.warn("Audio interaction required"));

        const overlay = document.createElement('div');
        overlay.className = 'sy-overlay';
        overlay.id = 'sy-modal-container';

        const profit = (parseFloat(task.delivery_fee) || 0) + (parseFloat(task.tip_amount) || 0);

        overlay.innerHTML = `
            <div class="sy-modal">
                <div class="m-header-box">
                    <h3 style="margin:0; font-weight:900; color:#2d3436;">طلب جديد متاح!</h3>
                    <div class="m-timer-badge" id="m-timer-display">${SETTINGS.MODAL_TIMEOUT}</div>
                </div>
                <div class="m-details-card">
                    <div class="m-info-line"><i class="fas fa-store"></i> <span>${task.business_name}</span></div>
                    <div class="m-info-line"><i class="fas fa-map-marker-alt"></i> <span>يبعد عنك ${task.distance_to_business} كم</span></div>
                    
                    <div class="m-price-section">
                        <div class="m-price-flex">
                            <span style="color:#666;">مطلوب تحصيله:</span>
                            <strong style="color:#e60000;">${parseInt(task.total_price).toLocaleString()} ل.س</strong>
                        </div>
                        <div class="m-price-flex m-profit-highlight">
                            <span>ربحك الصافي:</span>
                            <span>${profit.toLocaleString()} ل.س</span>
                        </div>
                    </div>
                </div>

                <div class="m-input-group">
                    <label>كم دقيقة تحتاج للوصول للمتجر؟</label>
                    <input type="number" id="arrival-mins-input" value="15" min="1" max="60" onfocus="this.select()">
                </div>

                <div class="m-footer-actions">
                    <button id="sy-accept-btn" class="m-sy-btn accept">قبول المهمة</button>
                    <button id="sy-ignore-btn" class="m-sy-btn ignore">تجاهل</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        let timeLeft = SETTINGS.MODAL_TIMEOUT;
        modalCountdown = setInterval(() => {
            timeLeft--;
            const el = document.getElementById('m-timer-display');
            if (el) el.textContent = timeLeft;
            if (timeLeft <= 0) {
                executeRejection(task.order_id, 'timed_out');
                closeModal();
            }
        }, 1000);

        document.getElementById('sy-btn-ignore')?.addEventListener('click', () => {
            ignoredOrders.add(task.order_id);
            executeRejection(task.order_id, 'rejected');
            closeModal();
        });

        // إصلاح زر الرفض في حال كان الاسم مختلفاً
        const ignoreBtn = document.getElementById('sy-ignore-btn');
        if(ignoreBtn) {
            ignoreBtn.onclick = () => {
                ignoredOrders.add(task.order_id);
                executeRejection(task.order_id, 'rejected');
                closeModal();
            };
        }

        document.getElementById('sy-accept-btn').onclick = () => {
            const mins = document.getElementById('arrival-mins-input').value;
            executeAcceptance(task.order_id, mins);
        };
    }

    function closeModal() {
        isModalOpen = false;
        clearInterval(modalCountdown);
        alertAudio.pause();
        alertAudio.currentTime = 0;
        document.getElementById('sy-modal-container')?.remove();
    }

    // --- 5. العمليات مع السيرفر (Server Communication) ---

    async function executeAcceptance(id, mins) {
        if (!mins || mins <= 0) { alert("يرجى تحديد وقت الوصول."); return; }
        const btn = document.getElementById('sy-accept-btn');
        btn.disabled = true; btn.textContent = "جاري الحجز...";

        try {
            const fd = new FormData();
            fd.append('order_id', id);
            fd.append('estimated_time', mins);
            const res = await fetch('php/accept_task.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                window.location.reload(); 
            } else {
                alert(data.message);
                closeModal();
            }
        } catch (e) {
            alert("خطأ في الاتصال بالشبكة.");
            btn.disabled = false; btn.textContent = "قبول المهمة";
        }
    }

    async function executeRejection(id, reason) {
        const fd = new FormData();
        fd.append('order_id', id);
        fd.append('reason', reason);
        fetch('php/reject_task.php', { method: 'POST', body: fd });
    }

    window.updateOrderStatus = async (id, status, btn) => {
        const msg = status === 'delivered' ? 'تأكيد التسليم وتحصيل المبلغ؟' : 'تأكيد استلام الطلب من المتجر؟';
        if (!confirm(msg)) return;

        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحديث...';

        try {
            const fd = new FormData();
            fd.append('order_id', id);
            fd.append('new_status', status);
            const res = await fetch('php/update_delivery_status.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) window.location.reload();
            else { alert(data.message); btn.disabled = false; btn.innerHTML = originalHtml; }
        } catch (e) { alert("فشل الاتصال."); btn.disabled = false; btn.innerHTML = originalHtml; }
    };

    // --- 6. الموقع الجغرافي (Geolocation Tracking) ---

    async function syncLocation(lat, lng) {
        try {
            const fd = new FormData();
            fd.append('latitude', lat);
            fd.append('longitude', lng);
            await fetch('php/update_driver_location.php', { method: 'POST', body: fd });
        } catch (e) {}
    }

    function startTracking() {
        if (!navigator.geolocation) return;

        navigator.geolocation.watchPosition(
            (pos) => {
                currentLatLng = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                if (hasActiveTask && map) updateMap(currentLatLng.lat, currentLatLng.lng);
            },
            null, { enableHighAccuracy: true }
        );

        locationTimer = setInterval(() => {
            if (currentLatLng) syncLocation(currentLatLng.lat, currentLatLng.lng);
        }, SETTINGS.LOCATION_INTERVAL);
    }

    // --- 7. جسر الأندرويد لـ FCM ---
    window.saveFCMTokenFromAndroid = function(token) {
        if (!token) return;
        const fd = new FormData();
        fd.append('fcm_token', token);
        fd.append('type', 'driver');
        fetch('php/save_fcm_token.php', { method: 'POST', body: fd });
    };

    // --- 8. الإقلاع (Startup) ---
    document.addEventListener('DOMContentLoaded', () => {
        if (hasActiveTask) {
            startTracking();
            // دالة initMap و updateMap تفترض وجود Leaflet (كما في النسخة السابقة)
        } else if (isOnline) {
            startTracking();
            fetchTasks();
            tasksTimer = setInterval(fetchTasks, SETTINGS.FETCH_INTERVAL);
        }

        const tgl = document.getElementById('toggle-online-btn');
        if (tgl) {
            tgl.onclick = async () => {
                tgl.disabled = true;
                tgl.style.opacity = "0.5";
                const newState = isOnline ? 0 : 1;
                const fd = new FormData();
                fd.append('is_available', newState);
                try {
                    const res = await fetch('php/update_driver_availability.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) window.location.reload();
                    else { alert(data.message); tgl.disabled = false; tgl.style.opacity = "1"; }
                } catch (e) { window.location.reload(); }
            };
        }
    });

// جسر التواصل مع الأندرويد (يجب أن يكون Global)
window.saveFCMTokenFromAndroid = function(token) {
    if (!token) return;
    console.log("FCM Token Received:", token);
    const fd = new FormData();
    fd.append('fcm_token', token);
    fd.append('type', 'driver');
    fetch('php/save_fcm_token.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if(d.success) console.log("Token saved to DB");
    }).catch(e => console.error("Bridge error"));
};
})();