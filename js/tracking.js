// ========================================================================
// Syriazzle - High-Precision Live Tracking Engine (V9.5 - Smooth Motion)
// ========================================================================

(() => {
    // 1. التحقق من وجود البيانات الأساسية الممرة من PHP
    if (typeof TRACKING_DATA === 'undefined') {
        console.error("Critical Error: TRACKING_DATA is missing.");
        return;
    }

    // 2. استخراج الثوابت والإعدادات
    const { 
        orderId, 
        statusKeys, 
        businessLat, 
        businessLng, 
        customerLat, 
        customerLng,
        initialStatus 
    } = TRACKING_DATA;

    const CONFIG = {
        POLLING_INTERVAL: 6000,    // جلب البيانات كل 6 ثوانٍ
        ANIMATION_DURATION: 5500,  // مدة حركة الانزلاق (أقل قليلاً من وقت الجلب لضمان الاستمرارية)
        DEFAULT_ZOOM: 16
    };

    // 3. متغيرات الحالة (Engine State)
    let map = null;
    let driverMarker = null;
    let storeMarker = null;
    let customerMarker = null;
    let routingControl = null;
    let currentStatus = initialStatus;
    let isFirstPositionSet = false;

    // 4. تعريف الأيقونات المحلية (يجب التأكد من وجودها في مجلد image/)
    const icons = {
        driver: L.icon({
            iconUrl: 'image/driver_marker.png',
            iconSize: [45, 45], iconAnchor: [22, 45], popupAnchor: [0, -40]
        }),
        store: L.icon({
            iconUrl: 'image/store_marker.png',
            iconSize: [38, 38], iconAnchor: [19, 38]
        }),
        customer: L.icon({
            iconUrl: 'image/destination_marker.png',
            iconSize: [38, 38], iconAnchor: [19, 38]
        })
    };

    // --- 5. تهيئة الخريطة الأساسية ---
    function initTrackingMap() {
        if (map) return;

        // إخفاء طبقة الانتظار وإظهار حاوية الخريطة
        const mapContainer = document.getElementById('tracking-map');
        const overlay = document.getElementById('no-driver-overlay');
        if (overlay) overlay.style.display = 'none';
        if (mapContainer) mapContainer.style.display = 'block';

        // إنشاء كائن الخريطة
        map = L.map('tracking-map', { 
            zoomControl: false, 
            attributionControl: false 
        }).setView([businessLat, businessLng], CONFIG.DEFAULT_ZOOM);

        // تحميل مربعات الخريطة (OpenStreetMap)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        // إضافة نقاط المرجع الثابتة (المتجر والزبون)
        storeMarker = L.marker([businessLat, businessLng], {icon: icons.store})
            .addTo(map).bindPopup("<b>المتجر</b>");
            
        customerMarker = L.marker([customerLat, customerLng], {icon: icons.customer})
            .addTo(map).bindPopup("<b>موقع التسليم (أنت هنا)</b>");

        // بدء دورة التحديث اللحظي
        runTrackingEngine();
        setInterval(runTrackingEngine, CONFIG.POLLING_INTERVAL);
    }

    // --- 6. المحرك الرئيسي (The Polling Engine) ---
    async function runTrackingEngine() {
        try {
            // جلب البيانات من الـ API الخفيف الذي برمجناه
            const timestamp = new Date().getTime();
            const res = await fetch(`php/get_order_tracking_data.php?order_id=${orderId}&_t=${timestamp}`);
            const data = await res.json();

            if (data.error) return;

            // أ- تحديث واجهة الحالة (Timeline) فوراً عند التغيير
            if (data.status !== currentStatus) {
                currentStatus = data.status;
                syncUIStatus(data.status);
            }

            // ب- تحريك السائق (Smooth Movement)
            if (data.lat && data.lng) {
                const newPos = L.latLng(data.lat, data.lng);

                if (!driverMarker) {
                    // أول ظهور للسائق على الخريطة
                    driverMarker = L.marker(newPos, {icon: icons.driver, zIndexOffset: 1000})
                        .addTo(map).bindPopup("<b>الكابتن: " + (data.driver_name || "جاري التوصيل") + "</b>");
                    
                    // عمل زوم أولي ليشمل السائق والوجهة
                    autoFitBounds(newPos, data.status);
                } else {
                    // تحريك السائق بسلاسة (Sliding Animation)
                    slideDriverMarker(driverMarker, newPos);
                }

                // ج- تحديث الخط الملاحي (Routing Machine)
                updateMapRouting(newPos, data.status);
            }

            // د- مراقبة حالة الاتصال (Heartbeat)
            const alertBox = document.getElementById('driver-offline-alert');
            if (alertBox) {
                alertBox.style.display = data.is_offline ? 'block' : 'none';
            }

        } catch (e) { console.warn("Tracking Polling Failed:", e); }
    }

    // --- 7. وظائف الرسم والتحريك (Graphics & Animation) ---

    // تحريك الماركر بسلاسة تامة (Interpolation)
    function slideDriverMarker(marker, destinationLatLng) {
        const startLatLng = marker.getLatLng();
        let startTime = null;

        function step(currentTime) {
            if (!startTime) startTime = currentTime;
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / CONFIG.ANIMATION_DURATION, 1);

            const currentLat = startLatLng.lat + (destinationLatLng.lat - startLatLng.lat) * progress;
            const currentLng = startLatLng.lng + (destinationLatLng.lng - startLatLng.lng) * progress;

            marker.setLatLng([currentLat, currentLng]);

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }
        requestAnimationFrame(step);
    }

    // رسم وتحديث المسار الملاحي (Routing)
    function updateMapRouting(driverPos, status) {
        let target;
        // إذا لم يتم الاستلام: الوجهة هي المتجر. إذا تم الاستلام: الوجهة هي الزبون.
        if (['accepted', 'preparing', 'ready_for_pickup'].includes(status)) {
            target = L.latLng(businessLat, businessLng);
        } else if (status === 'picked_up') {
            target = L.latLng(customerLat, customerLng);
        } else {
            if (routingControl) map.removeControl(routingControl);
            return;
        }

        if (!routingControl) {
            routingControl = L.Routing.control({
                waypoints: [driverPos, target],
                router: L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' }),
                lineOptions: { styles: [{color: '#007bff', opacity: 0.7, weight: 6}] },
                createMarker: () => null, // منع الماركرات الافتراضية المزعجة
                addWaypoints: false,
                fitSelectedRoutes: false,
                show: false
            }).addTo(map);
        } else {
            routingControl.setWaypoints([driverPos, target]);
        }
    }

    // ضبط الكاميرا لتشمل السائق والوجهة (Smart Zoom)
    function autoFitBounds(driverPos, status) {
        let target = (status === 'picked_up') ? L.latLng(customerLat, customerLng) : L.latLng(businessLat, businessLng);
        const bounds = L.latLngBounds([driverPos, target]);
        map.fitBounds(bounds, { padding: [70, 70], maxZoom: 16 });
    }

    // --- 8. مزامنة الواجهة (UI Synchronization) ---

    function syncUIStatus(status) {
        const index = statusKeys.indexOf(status);
        if (index === -1) return;

        // تحديث التايم لاين
        const steps = document.querySelectorAll('.step-row');
        steps.forEach((step, i) => {
            step.classList.remove('active', 'done');
            if (i < index) step.classList.add('done');
            else if (i === index) step.classList.add('active');
        });
        
        // تحديث شارة الحالة في الأعلى
        const badge = document.getElementById('live-status-badge');
        if (badge) {
            badge.innerHTML = '<i class="fas fa-sync fa-spin"></i> جاري التحديث..';
        }

        // إظهار بيانات السائق إذا توفرت
        if (['accepted', 'picked_up'].includes(status)) {
            const panel = document.getElementById('driver-info-panel');
            if (panel) panel.style.display = 'flex';
            initTrackingMap(); // تفعيل الخريطة فوراً عند قبول الطلب
        }

        // إذا تم التسليم، نقوم بعمل تحديث نهائي
        if (status === 'delivered') {
            setTimeout(() => { window.location.reload(); }, 3000);
        }
    }

    // --- 9. أزرار التحكم التفاعلية ---

    // تبديل ملء الشاشة
    const fsBtn = document.getElementById('toggle-fullscreen');
    if (fsBtn) {
        fsBtn.onclick = () => {
            const wrap = document.getElementById('map-outer-wrap');
            wrap.classList.toggle('fullscreen-active');
            fsBtn.innerHTML = wrap.classList.contains('fullscreen-active') ? 
                '<i class="fas fa-compress-arrows-alt"></i>' : '<i class="fas fa-expand-arrows-alt"></i>';
            
            // إصلاح رندرة الخريطة بعد تغيير الحجم
            setTimeout(() => { if(map) map.invalidateSize(); }, 300);
        };
    }

    // إعادة التمركز على السائق
    const rcBtn = document.getElementById('recenter-driver');
    if (rcBtn) {
        rcBtn.onclick = () => {
            if (driverMarker) {
                map.flyTo(driverMarker.getLatLng(), CONFIG.DEFAULT_ZOOM + 1, { animate: true, duration: 1.5 });
            }
        };
    }

    // --- 10. التشغيل الابتدائي ---
    document.addEventListener('DOMContentLoaded', () => {
        syncUIStatus(initialStatus);
        
        // إذا كان الطلب مقبولاً مسبقاً، نشغل الخريطة فوراً
        if (['accepted', 'picked_up', 'delivered'].includes(initialStatus)) {
            initTrackingMap();
        } else {
            // إذا كان الطلب قيد الموافقة، نفحص كل 10 ثوانٍ حتى يتغير
            const initialCheck = setInterval(async () => {
                const checkRes = await fetch(`php/get_order_tracking_data.php?order_id=${orderId}`);
                const checkData = await checkRes.json();
                if (checkData.status !== initialStatus) {
                    clearInterval(initialCheck);
                    window.location.reload();
                }
            }, 10000);
        }
    });

})();