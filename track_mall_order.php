<?php
// ========================================================================
// Syriazzle Mall - Track Order (النسخة النهائية - التصميم الأحمر والتتبع)
// ========================================================================
require_once 'php/db_connect.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id === 0) die("خطأ: الطلب غير محدد.");

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = "track_mall_order.php?order_id=" . $order_id;
    header('Location: login.php');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

try {
    $stmt_order = $pdo->prepare("SELECT * FROM mall_orders WHERE id = ?");
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order || $order['user_id'] !== $current_user_id) {
        die("خطأ: لا تملك صلاحية الوصول لهذا الطلب.");
    }
} catch (PDOException $e) { 
    die("Error: " . $e->getMessage()); 
}

// تعريف المراحل والأيقونات المناسبة
$all_statuses = [
    'pending_approval' => ['text' => 'تم إرسال الطلب', 'icon' => 'fa-clipboard-list'],
    'preparing'        => ['text' => 'قيد التحضير', 'icon' => 'fa-box-open'],
    'out_for_delivery' => ['text' => 'في الطريق إليك', 'icon' => 'fa-motorcycle'],
    'delivered'        => ['text' => 'تم التوصيل', 'icon' => 'fa-check'],
];
$status_keys = array_keys($all_statuses);
$is_canceled = ($order['status'] === 'canceled');
$page_title = 'تتبع الطلب #' . $order_id;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?> - Syriazzle</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="css/lib/leaflet.css"/>
    <link rel="stylesheet" href="css/lib/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Cairo', sans-serif; padding-bottom: 50px; }
        .tracking-container { max-width: 600px; margin: 20px auto; padding: 0 15px; }

        /* --- خريطة التوصيل --- */
        .map-wrapper {
            position: relative;
            height: 350px;
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: none; 
            border: 2px solid #fff;
        }

        .map-wrapper.fullscreen-mode {
            position: fixed !important; top: 0; left: 0; right: 0; bottom: 0;
            width: 100% !important; height: 100% !important; z-index: 99999;
            border-radius: 0; border: none; margin: 0;
        }

        #tracking-map { height: 100%; width: 100%; }

        /* زر التكبير */
        .fullscreen-btn {
            position: absolute; top: 20px; right: 20px;
            width: 40px; height: 40px; background: #fff;
            border-radius: 8px; border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000; cursor: pointer; color: #333;
            display: flex; align-items: center; justify-content: center;
        }

        /* تنسيق أيقونة السيارة على الخريطة (مهم جداً للظهور) */
        .driver-car-marker {
            display: flex; justify-content: center; align-items: center;
        }
        .driver-car-marker i {
            font-size: 28px;
            color: #007bff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
            background: rgba(255,255,255,0.8);
            border-radius: 50%;
            padding: 5px;
        }

        /* بطاقة السائق العائمة */
        .driver-overlay-card {
            position: absolute; bottom: 20px; left: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(5px);
            padding: 15px 20px; border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15); z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
        }
        .btn-call-driver {
            width: 45px; height: 45px; background: #28a745; color: #fff;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            text-decoration: none; font-size: 1.2rem;
        }

        /* --- تنسيقات المراحل (Timeline الأحمر) --- */
        .status-card {
            background: #fff; border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            padding: 30px 20px; margin-bottom: 20px;
        }
        
        .timeline-title { font-size: 1.1rem; font-weight: 700; color: #333; margin-bottom: 30px; text-align: center; }

        .timeline { position: relative; padding: 0; margin: 0; list-style: none; }
        .timeline::before {
            content: ''; position: absolute; top: 10px; bottom: 30px; left: 20px;
            width: 2px; background: #e9ecef; z-index: 0;
        }

        .timeline-item {
            position: relative; margin-bottom: 35px;
            display: flex; align-items: center; justify-content: flex-end;
        }
        .timeline-item:last-child { margin-bottom: 0; }

        .timeline-text {
            font-size: 1rem; font-weight: 600; color: #adb5bd;
            margin-left: 50px; text-align: right; width: 100%; transition: color 0.3s;
        }

        .timeline-icon {
            position: absolute; left: 8px; width: 28px; height: 28px;
            background: #fff; border: 2px solid #e9ecef; border-radius: 50%;
            z-index: 1; display: flex; align-items: center; justify-content: center;
            color: #adb5bd; font-size: 0.9rem; transition: all 0.3s;
        }

        /* الحالة المكتملة (أحمر) */
        .timeline-item.completed .timeline-text { color: #333; }
        .timeline-item.completed .timeline-icon {
            background: #dc3545; /* الأحمر المطلوب */
            border-color: #dc3545;
            color: #fff;
        }

        /* الحالة الحالية (توهج ونبض) */
        .timeline-item.active .timeline-text { color: #dc3545; font-weight: 800; }
        .timeline-item.active .timeline-icon {
            background: #dc3545;
            border-color: #dc3545;
            color: #fff;
            box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.2); /* هالة حمراء */
            animation: pulse-red 1.5s infinite;
        }

        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        /* صندوق الدعم */
        .support-card {
            background: #fff; border-radius: 12px; padding: 20px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .support-btn {
            background: #dc3545; color: #fff; padding: 10px 20px;
            border-radius: 50px; text-decoration: none; font-weight: bold;
            font-size: 0.9rem; display: flex; align-items: center; gap: 8px;
        }
        .leaflet-routing-container { display: none !important; }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    
    <div class="tracking-container">
        
        <!-- قسم الخريطة -->
        <div id="live-map-wrapper" class="map-wrapper">
            <div id="tracking-map"></div>
            <button id="fullscreen-btn" class="fullscreen-btn" type="button"><i class="fas fa-expand"></i></button>
            
            <div id="driver-card" class="driver-overlay-card" style="display: none;">
                <div class="driver-info">
                    <h4>الكابتن: <span id="driver-name-display">...</span></h4>
                    <p>في طريقه إليك الآن</p>
                </div>
                <a href="#" id="call-driver-btn" class="btn-call-driver"><i class="fas fa-phone-alt"></i></a>
            </div>
        </div>

        <!-- قسم المراحل (Timeline) -->
        <div class="status-card">
            <h2 class="timeline-title">مراحل توصيل طلبك رقم #<?php echo $order_id; ?></h2>
            
            <?php if ($is_canceled): ?>
                <div style="text-align:center; color:#dc3545; padding:20px;">
                    <i class="fas fa-times-circle" style="font-size:3rem; margin-bottom:10px;"></i>
                    <h3>تم إلغاء الطلب</h3>
                </div>
            <?php else: ?>
                <ul class="timeline">
                    <?php foreach ($all_statuses as $key => $val): ?>
                    <li class="timeline-item" id="item-<?php echo $key; ?>" data-key="<?php echo $key; ?>">
                        <span class="timeline-text"><?php echo $val['text']; ?></span>
                        <div class="timeline-icon">
                            <i class="fas <?php echo $val['icon']; ?>"></i>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- صندوق الدعم -->
        <div class="support-card">
            <div style="display:flex; align-items:center;">
                <div style="font-size:1.8rem; color:#dc3545; margin-left:15px;"><i class="fas fa-headset"></i></div>
                <div>
                    <h4 style="margin:0 0 5px; font-size:1rem;">هل تواجه مشكلة؟</h4>
                    <p style="margin:0; font-size:0.8rem; color:#666;">فريق الدعم جاهز لمساعدتك</p>
                </div>
            </div>
            <a href="https://wa.me/963952430683" class="support-btn" target="_blank">
                تواصل معنا <i class="fab fa-whatsapp"></i>
            </a>
        </div>
    </div>

        <script src="js/lib/leaflet.js"></script>
    <script src="js/lib/leaflet-routing-machine.js"></script>
    
    <script>
        const TRACKING_DATA = {
            orderId: <?php echo $order_id; ?>,
            customerLat: <?php echo $order['latitude'] ?? 0; ?>,
            customerLon: <?php echo $order['longitude'] ?? 0; ?>,
            currentStatus: "<?php echo $order['status']; ?>"
        };
    </script>
    <script src="js/track_mall_order.js"></script>
</body>
</html>