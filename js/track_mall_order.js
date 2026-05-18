// ========================================================================
// Syriazzle Mall - JS Tracking Logic (النسخة النهائية مع التوهج والخط)
// ========================================================================

document.addEventListener('DOMContentLoaded', () => {
    const { orderId, customerLat, customerLon, currentStatus } = TRACKING_DATA;
    
    // العناصر
    const mapWrapper = document.getElementById('live-map-wrapper');
    const fullscreenBtn = document.getElementById('fullscreen-btn');
    const driverCard = document.getElementById('driver-card');
    const driverNameDisplay = document.getElementById('driver-name-display');
    const callDriverBtn = document.getElementById('call-driver-btn');
    
    let map = null;
    let driverMarker = null;
    let routingControl = null;
    let trackingInterval = null;
    let isMapInitialized = false;

    // تشغيل فوري لتلوين المراحل
    updateTimelineVisuals(currentStatus);
    
    // إذا كان الطلب في الطريق
    if (currentStatus === 'out_for_delivery') {
        initMap();
        startTracking();
    } else if (currentStatus !== 'delivered' && currentStatus !== 'canceled') {
        setInterval(fetchOrderStatus, 20000); // مراقبة بطيئة
    }

    // --- دوال الخريطة ---
    function initMap() {
        if (isMapInitialized || customerLat === 0 || customerLon === 0) return;

        mapWrapper.style.display = 'block';

        // إعداد الخريطة
        map = L.map('tracking-map', { zoomControl: false }).setView([customerLat, customerLon], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        // أيقونة المنزل
        const homeIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34]
        });
        L.marker([customerLat, customerLon], {icon: homeIcon}).addTo(map).bindPopup("منزلك");

        isMapInitialized = true;

        // زر التكبير
        fullscreenBtn.addEventListener('click', () => {
            mapWrapper.classList.toggle('fullscreen-mode');
            const icon = fullscreenBtn.querySelector('i');
            if (mapWrapper.classList.contains('fullscreen-mode')) {
                icon.classList.replace('fa-expand', 'fa-compress');
                document.body.style.overflow = 'hidden';
            } else {
                icon.classList.replace('fa-compress', 'fa-expand');
                document.body.style.overflow = '';
            }
            setTimeout(() => map.invalidateSize(), 200);
        });
    }

    function updateMapPosition(driverLat, driverLon) {
        if (!isMapInitialized) initMap();

        const driverLatLng = L.latLng(driverLat, driverLon);
        const customerLatLng = L.latLng(customerLat, customerLon);

        // 1. أيقونة السيارة (Driver)
        // نستخدم DivIcon لنضع أيقونة FontAwesome
        const carIcon = L.divIcon({
            html: '<i class="fas fa-car-side"></i>',
            className: 'driver-car-marker', // معرفة في CSS الملف السابق
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });

        if (!driverMarker) {
            driverMarker = L.marker(driverLatLng, {icon: carIcon}).addTo(map).bindPopup("الكابتن");
        } else {
            driverMarker.setLatLng(driverLatLng);
        }

        // 2. رسم الخط (Routing)
        if (routingControl) {
            // تحديث نقطة البداية (السائق) والنهاية (الزبون)
            routingControl.setWaypoints([driverLatLng, customerLatLng]);
        } else {
            // إنشاء المسار لأول مرة
            routingControl = L.Routing.control({
                waypoints: [driverLatLng, customerLatLng],
                router: L.Routing.osrmv1({
                    serviceUrl: 'https://router.project-osrm.org/route/v1',
                    language: 'ar',
                    profile: 'driving'
                }),
                lineOptions: {
                    styles: [{color: '#007bff', opacity: 0.8, weight: 6}]
                },
                createMarker: function() { return null; }, // لا تنشئ علامات إضافية
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: true,
                show: false // إخفاء القائمة النصية
            }).addTo(map);
        }
    }

    // --- الاتصال بالسيرفر ---
    function startTracking() {
        fetchOrderStatus();
        trackingInterval = setInterval(fetchOrderStatus, 10000); // تحديث كل 10 ثواني
    }

    async function fetchOrderStatus() {
        try {
            const response = await fetch(`php/ajax_get_mall_order_status.php?order_id=${orderId}`);
            const result = await response.json();

            if (result.success) {
                const data = result.data;
                updateTimelineVisuals(data.status);

                if (data.status === 'out_for_delivery') {
                    // إظهار بطاقة السائق
                    if (data.driver_name) {
                        driverCard.style.display = 'flex';
                        driverNameDisplay.textContent = data.driver_name;
                        callDriverBtn.href = `tel:${data.driver_phone}`;
                    }
                    
                    // تحديث الخريطة
                    if (data.driver_latitude && data.driver_longitude) {
                        updateMapPosition(data.driver_latitude, data.driver_longitude);
                    } else if (!isMapInitialized) {
                        initMap(); // إظهار الخريطة حتى لو لم يتحرك السائق
                    }
                } 
                else if (data.status === 'delivered') {
                    if (trackingInterval) clearInterval(trackingInterval);
                    alert("تم توصيل الطلب!");
                    window.location.reload();
                }
            }
        } catch (e) { console.error(e); }
    }

    function updateTimelineVisuals(status) {
        const statusOrder = ['pending_approval', 'preparing', 'out_for_delivery', 'delivered'];
        const currentIdx = statusOrder.indexOf(status);

        statusOrder.forEach((key, index) => {
            const el = document.getElementById(`item-${key}`);
            if (!el) return;

            el.classList.remove('completed', 'active');
            
            if (index < currentIdx) {
                el.classList.add('completed'); 
            } else if (index === currentIdx) {
                el.classList.add('active');
            }
        });
    }
});