<?php
// ========================================================================
// Syriazzle Mall - Live Navigation Map (النسخة الاحترافية - ملاحة حية)
// ========================================================================
require_once 'php/db_connect.php';

// 1. التحقق من صلاحية السائق
if (!isset($_SESSION['driver_id'])) {
    header('Location: driver_login.php');
    exit;
}
$driver_id = (int)$_SESSION['driver_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id === 0) die('رقم الطلب غير صحيح.');

try {
    // 2. جلب بيانات الزبون وموقعه (نقطة الوصول الثابتة)
    $stmt_order = $pdo->prepare("
        SELECT customer_name, customer_phone, latitude, longitude, address_details 
        FROM mall_orders 
        WHERE id = ? AND assigned_driver_id = ?
    ");
    $stmt_order->execute([$order_id, $driver_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die('عذراً: الطلب غير موجود أو غير مخصص لك.');
    }

    if (empty($order['latitude']) || empty($order['longitude'])) {
        die('عذراً: لا توجد إحداثيات مسجلة لهذا الطلب.');
    }

} catch (PDOException $e) { 
    die("خطأ في قاعدة البيانات: " . $e->getMessage()); 
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ملاحة حية - طلب #<?php echo $order_id; ?></title>
    
    <link rel="stylesheet" href="css/lib/leaflet.css" />
    <link rel="stylesheet" href="css/lib/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="css/all.min.css">
    
    <style>
        body, html { margin: 0; padding: 0; height: 100%; width: 100%; font-family: 'Cairo', sans-serif; overflow: hidden; background: #eee; }
        
        #map { height: 100%; width: 100%; z-index: 0; }
        
        /* شاشة انتظار الـ GPS */
        #gps-loader {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.9); z-index: 2000;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center;
        }
        .spinner {
            width: 50px; height: 50px; border: 5px solid #007bff; border-top-color: transparent;
            border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* البطاقة العائمة */
        .info-card {
            position: absolute; bottom: 20px; left: 20px; right: 20px;
            background: #fff; padding: 15px; border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15); z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
        }
        .customer-info h3 { margin: 0; font-size: 1.1rem; }
        .customer-info p { margin: 2px 0 0; font-size: 0.9rem; color: #666; }
        .btn-action {
            width: 45px; height: 45px; border-radius: 50%; border: none;
            display: flex; justify-content: center; align-items: center;
            font-size: 1.2rem; color: #fff; text-decoration: none;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
        }
        .btn-call { background: #28a745; margin-left: 10px; }
        .btn-back { background: #dc3545; }
        
        /* إخفاء تعليمات التوجيه النصية */
        .leaflet-routing-container { display: none !important; }
        
        /* زر إعادة التمركز */
        .recenter-btn {
            position: absolute; top: 20px; right: 20px; z-index: 1000;
            background: #fff; border: none; padding: 10px; border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); cursor: pointer; font-size: 1.2rem; color: #007bff;
        }
    </style>
</head>
<body>

    <!-- شاشة التحميل -->
    <div id="gps-loader">
        <div class="spinner"></div>
        <h3>جاري تحديد موقعك بدقة...</h3>
        <p>يرجى السماح باستخدام الموقع لتفعيل الملاحة</p>
    </div>

    <!-- زر إعادة التمركز -->
    <button class="recenter-btn" onclick="recenterMap()"><i class="fas fa-crosshairs"></i></button>

    <div id="map"></div>

    <div class="info-card">
        <div class="customer-info">
            <h3>الزبون: <?php echo htmlspecialchars($order['customer_name']); ?></h3>
            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['address_details']); ?></p>
        </div>
        <div style="display: flex;">
            <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" class="btn-action btn-call"><i class="fas fa-phone"></i></a>
            <a href="mall_driver_dashboard.php" class="btn-action btn-back"><i class="fas fa-times"></i></a>
        </div>
    </div>

        <script src="js/lib/leaflet.js"></script>
    <script src="js/lib/leaflet-routing-machine.js"></script>
    <script>
        // إحداثيات الزبون (الهدف)
        const custLat = <?php echo $order['latitude']; ?>;
        const custLon = <?php echo $order['longitude']; ?>;
        const customerLatLng = L.latLng(custLat, custLon);

        // متغيرات عامة
        let map, driverMarker, routingControl;
        let isFirstLocation = true;
        let currentDriverLatLng = null;

        // تهيئة الخريطة
        map = L.map('map', { zoomControl: false }).setView([custLat, custLon], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        // أيقونة الزبون
        const iconCustomer = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34]
        });
        L.marker(customerLatLng, {icon: iconCustomer}).addTo(map).bindPopup("نقطة التسليم");

        // أيقونة السيارة (السائق)
        const iconCar = L.divIcon({
            html: '<div style="font-size: 24px; color: #007bff; text-shadow: 0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-car-side"></i></div>',
            className: 'custom-car-icon',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        // دالة تحديث المسار
        function updateRoute(driverLatLng) {
            // إذا كان التوجيه موجوداً، قم بتحديث النقاط فقط
            if (routingControl) {
                routingControl.setWaypoints([
                    driverLatLng,
                    customerLatLng
                ]);
            } else {
                // إنشاء التوجيه لأول مرة
                routingControl = L.Routing.control({
                    waypoints: [
                        driverLatLng,
                        customerLatLng
                    ],
                    router: L.Routing.osrmv1({
                        serviceUrl: 'https://router.project-osrm.org/route/v1',
                        language: 'ar',
                        profile: 'driving'
                    }),
                    lineOptions: {
                        styles: [{color: '#007bff', opacity: 0.8, weight: 6}] // الخط الأزرق
                    },
                    createMarker: function() { return null; }, // لا تنشئ علامات إضافية، لدينا علاماتنا الخاصة
                    addWaypoints: false,
                    draggableWaypoints: false,
                    fitSelectedRoutes: false, // نتحكم نحن بالزوم
                    show: false
                }).addTo(map);
            }
        }

        // دالة مراقبة الموقع (GPS)
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    currentDriverLatLng = L.latLng(lat, lng);

                    // إخفاء شاشة التحميل عند أول التقاط
                    document.getElementById('gps-loader').style.display = 'none';

                    // تحديث أو إنشاء علامة السائق
                    if (!driverMarker) {
                        driverMarker = L.marker(currentDriverLatLng, {icon: iconCar}).addTo(map);
                    } else {
                        driverMarker.setLatLng(currentDriverLatLng);
                    }

                    // تحديث المسار ليبدأ من الموقع الجديد
                    updateRoute(currentDriverLatLng);

                    // تحريك الخريطة لتتبع السائق (في المرة الأولى فقط أو عند الطلب)
                    if (isFirstLocation) {
                        map.setView(currentDriverLatLng, 17);
                        isFirstLocation = false;
                    }
                },
                (error) => {
                    alert('تعذر تحديد الموقع: ' + error.message);
                    document.getElementById('gps-loader').innerHTML = '<h3 style="color:red">فشل تحديد الموقع</h3><p>تأكد من تفعيل GPS</p>';
                },
                {
                    enableHighAccuracy: true,
                    maximumAge: 0,
                    timeout: 10000
                }
            );
        } else {
            alert("المتصفح لا يدعم تحديد الموقع");
        }

        // دالة إعادة التمركز على السائق
        function recenterMap() {
            if (currentDriverLatLng) {
                map.setView(currentDriverLatLng, 17);
            } else {
                alert("جاري انتظار إشارة GPS...");
            }
        }
    </script>
</body>
</html>