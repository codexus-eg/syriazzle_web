<?php
// ========================================================================
// Syriazzle - Ultimate Live Tracking Portal (V5.0 - Production Master)
// ========================================================================
require_once 'php/db_connect.php';

// 1. التحقق من المعاملات والصلاحيات الأمنية
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id === 0) { 
    die("<div style='text-align:center; padding:50px; font-family:Cairo;'>خطأ: لم يتم تحديد رقم الطلب.</div>"); 
}

// حماية: يجب أن يكون المستخدم صاحب الطلب
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = "track_order.php?order_id=" . $order_id;
    header('Location: login.php');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

try {
    // 2. جلب بيانات الطلب، المتجر، والسائق
    $sql_order = "
        SELECT 
            o.id, o.status, o.total_price, o.currency, o.user_id,
            o.customer_latitude, o.customer_longitude,
            b.name as business_name, b.latitude as business_latitude, b.longitude as business_longitude,
            d.full_name as driver_name, d.phone as driver_phone
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        LEFT JOIN drivers d ON o.driver_id = d.id
        WHERE o.id = ? AND o.user_id = ?
        LIMIT 1
    ";
    $stmt_order = $pdo->prepare($sql_order);
    $stmt_order->execute([$order_id, $current_user_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("<div style='text-align:center; padding:50px; font-family:Cairo;'>عذراً، الطلب غير موجود أو لا تملك صلاحية الوصول إليه.</div>");
    }

    // تنسيق عرض المبالغ المالية
    $currency_code = $order['currency'] ?? 'SYP';
    function formatOrderPrice($amount, $currency) {
        if ($currency === 'USD') return '$' . number_format($amount, 2);
        return number_format($amount) . ' ل.س';
    }

} catch (PDOException $e) {
    error_log("Tracking Page DB Error: " . $e->getMessage());
    die("حدث خطأ فني، يرجى المحاولة لاحقاً.");
}

// 3. مصفوفة الحالات للتايم لاين
$all_statuses = [
    'pending_approval' => ['icon' => 'fa-paper-plane', 'text' => 'تم استلام الطلب'],
    'preparing'        => ['icon' => 'fa-utensils', 'text' => 'المتجر يحضر طلبك'],
    'ready_for_pickup' => ['icon' => 'fa-box-open', 'text' => 'طلبك جاهز تماماً'],
    'accepted'         => ['icon' => 'fa-motorcycle', 'text' => 'الكابتن يتجه للمتجر'], 
    'picked_up'        => ['icon' => 'fa-route', 'text' => 'الكابتن في الطريق إليك'],
    'delivered'        => ['icon' => 'fa-check-double', 'text' => 'تم التسليم بنجاح'],
];

$status_keys = array_keys($all_statuses);
$is_canceled = ($order['status'] === 'canceled');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تتبع طلبك #<?php echo $order_id; ?> - Syriazzle</title>
    
    <!-- استدعاء المكتبات الضرورية -->
    <link rel="stylesheet" href="css/lib/leaflet.css"/>    
    <link rel="stylesheet" href="css/lib/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">

    <style>
        :root { --sy-red: #e60000; --sy-blue: #007bff; --sy-green: #28a745; }
        body { background: #f8f9fa; font-family: 'Cairo', sans-serif; margin: 0; padding: 0; }

        .tracking-main-content { max-width: 600px; margin: 0 auto; padding: 15px; }

        /* حاوية الخريطة المتطورة */
        #map-outer-wrap { 
            position: relative; width: 100%; height: 320px; 
            border-radius: 24px; overflow: hidden; 
            box-shadow: 0 12px 30px rgba(0,0,0,0.12); background: #f0f0f0;
            margin-bottom: 20px; border: 1px solid #fff;
        }
        #tracking-map { width: 100%; height: 100%; z-index: 1; }

        /* أزرار التحكم الطافية */
        .map-float-btn {
            position: absolute; z-index: 1000; background: #fff; border: none;
            width: 46px; height: 46px; border-radius: 14px; cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15); display: flex; align-items: center; 
            justify-content: center; font-size: 1.2rem; color: #333; transition: 0.2s;
        }
        .map-float-btn:active { transform: scale(0.9); background: #f8f8f8; }
        .btn-fs { top: 15px; left: 15px; }
        .btn-rc { top: 72px; left: 15px; }

        /* وضع ملء الشاشة الكامل */
        .fullscreen-active { 
            position: fixed !important; top: 0; left: 0; width: 100vw !important; 
            height: 100vh !important; z-index: 99999 !important; border-radius: 0 !important; 
        }

        /* تنبيه Heartbeat */
        #driver-offline-alert {
            position: absolute; top: 15px; right: 15px; z-index: 1000;
            background: rgba(230, 0, 0, 0.9); color: #fff; padding: 10px 18px;
            border-radius: 30px; font-size: 0.8rem; font-weight: bold; display: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2); animation: sy-pulse 2s infinite;
        }
        @keyframes sy-pulse { 0% { transform: scale(1); } 50% { transform: scale(0.98); opacity: 0.8; } 100% { transform: scale(1); } }

        /* كرت حالة الطلب */
        .status-display-card { background: #fff; border-radius: 28px; padding: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.05); border: 1px solid #f0f0f0; }
        .status-top-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px; }
        .badge-live-status { background: #e8f5e9; color: var(--sy-green); padding: 6px 16px; border-radius: 30px; font-weight: 800; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; }
        .order-total-txt { color: var(--sy-red); font-weight: 900; font-size: 1.5rem; margin: 5px 0 0; }

        /* التايم لاين (الخط الزمني) */
        .sy-stepper-v2 { position: relative; list-style: none; padding: 0; margin: 0; }
        .sy-stepper-v2::before { content: ''; position: absolute; right: 19px; top: 0; bottom: 0; width: 2px; background: #f4f4f4; }
        
        .step-row { position: relative; padding: 0 45px 32px 0; transition: 0.4s; }
        .step-point { 
            position: absolute; right: 0; top: 0; width: 40px; height: 40px; 
            background: #fff; border: 2px solid #eee; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; z-index: 2; color: #ccc;
            transition: 0.3s ease;
        }
        .step-row.active .step-point { border-color: var(--sy-green); color: var(--sy-green); transform: scale(1.2); box-shadow: 0 0 15px rgba(40,167,69,0.3); }
        .step-row.done .step-point { background: var(--sy-green); border-color: var(--sy-green); color: #fff; }
        .step-row h4 { margin: 0; font-size: 1.05rem; color: #444; }
        .step-row.active h4 { color: var(--sy-green); font-weight: 900; }
        .step-row.done h4 { color: #888; }

        /* كرت السائق المحسن */
        .driver-info-pnl {
            margin-top: 25px; background: #f9f9f9; padding: 18px; border-radius: 22px;
            display: flex; align-items: center; gap: 15px; border: 1px solid #eee;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .drv-img-box { width: 55px; height: 55px; border-radius: 50%; background: #fff; padding: 2px; border: 1px solid #ddd; }
        .drv-img-box img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .btn-call-captain { background: var(--sy-blue); color: #fff; padding: 10px 18px; border-radius: 14px; text-decoration: none; font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 8px; }

        .canceled-container { text-align: center; color: var(--sy-red); padding: 50px 20px; }
        .leaflet-routing-container { display: none !important; }
    </style>
</head>
<body>

    <?php include 'header_store.php'; ?>

    <main class="tracking-main-content">
        
        <!-- منطقة الخريطة والتتبع المباشر -->
        <div id="map-outer-wrap">
            <div id="driver-offline-alert">
                <i class="fas fa-wifi-slash"></i> إشارة الكابتن ضعيفة، جاري التحديث..
            </div>
            
            <button class="map-float-btn btn-fs" id="toggle-fullscreen">
                <i class="fas fa-expand-arrows-alt"></i>
            </button>
            <button class="map-float-btn btn-rc" id="recenter-driver">
                <i class="fas fa-street-view"></i>
            </button>
            
            <div id="tracking-map"></div>
            
            <!-- طبقة البحث عن سائق -->
            <?php if (!$order['driver_id']): ?>
                <div id="no-driver-overlay" style="position:absolute; inset:0; background:rgba(255,255,255,0.92); z-index:2000; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:30px;">
                    <i class="fas fa-motorcycle fa-beat" style="font-size:3.5rem; color:var(--sy-blue); margin-bottom:20px;"></i>
                    <h3 style="color:#333; margin:0;">نبحث عن أقرب كابتن..</h3>
                    <p style="color:#777; font-size:0.9rem; margin-top:10px;">سيتم تحديث الخريطة فور قبول المهمة من قبل أحد السائقين.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- كرت تفاصيل الحالة -->
        <div class="status-display-card">
            <?php if ($is_canceled): ?>
                <div class="canceled-container">
                    <i class="fas fa-exclamation-triangle" style="font-size:4rem; margin-bottom:20px; opacity:0.3;"></i>
                    <h2>الطلب ملغي</h2>
                    <p style="color:#666;"><?php echo htmlspecialchars($order['cancellation_reason'] ?? 'تم إلغاء الطلب من قبل النظام أو المتجر.'); ?></p>
                    <a href="index.php" style="display:inline-block; margin-top:20px; color:var(--sy-blue); text-decoration:none; font-weight:bold;">العودة للرئيسية</a>
                </div>
            <?php else: ?>
                <div class="status-top-row">
                    <div>
                        <span style="color:#aaa; font-size:0.75rem; font-weight:700;">طلب رقم #<?php echo $order_id; ?></span>
                        <h3 class="order-total-txt"><?php echo formatOrderPrice($order['total_price'], $currency_code); ?></h3>
                    </div>
                    <div id="badge-wrap">
                        <span class="badge-live-status" id="live-status-badge">
                            <i class="fas fa-dot-circle fa-fade"></i> <?php echo $all_statuses[$order['status']]['text']; ?>
                        </span>
                    </div>
                </div>

                <!-- التايم لاين التفاعلي -->
                <div class="sy-stepper-v2" id="main-stepper">
                    <?php foreach ($all_statuses as $key => $info): 
                        $is_active = ($order['status'] === $key);
                    ?>
                        <div class="step-row <?php echo $is_active ? 'active' : ''; ?>" data-status-key="<?php echo $key; ?>">
                            <div class="step-point"><i class="fas <?php echo $info['icon']; ?>"></i></div>
                            <div class="step-info">
                                <h4><?php echo $info['text']; ?></h4>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- لوحة السائق (تظهر وتختفي برمجياً عبر JS) -->
                <div id="driver-info-panel" style="<?php echo $order['driver_name'] ? 'display:flex;' : 'display:none;'; ?>" class="driver-info-pnl">
                    <div class="drv-img-box">
                        <img src="image/driver_marker.png" id="drv-avatar-img" alt="Driver">
                    </div>
                    <div style="flex-grow:1;">
                        <h5 style="margin:0; font-size:0.95rem;" id="drv-name-txt"><?php echo htmlspecialchars($order['driver_name'] ?? ''); ?></h5>
                        <p style="margin:3px 0 0; font-size:0.75rem; color:#888;">كابتن التوصيل الخاص بك</p>
                    </div>
                    <a href="tel:<?php echo $order['driver_phone']; ?>" class="btn-call-captain" id="drv-call-link">
                        <i class="fas fa-phone-alt"></i> اتصال
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- المساعدة والدعم -->
        <div style="margin-top:30px; text-align:center; padding-bottom:30px;">
            <p style="color:#999; font-size:0.8rem;">تواجه مشكلة فنية أو تأخير؟</p>
            <a href="https://wa.me/963952430683" target="_blank" style="display:inline-flex; align-items:center; gap:8px; color:var(--sy-green); text-decoration:none; font-weight:900; font-size:1rem; margin-top:5px;">
                <i class="fab fa-whatsapp" style="font-size:1.3rem;"></i> تحدث مع الدعم الفني
            </a>
        </div>
    </main>

    <!-- استدعاء ملفات الخرائط الموثوقة -->
    <script src="js/lib/leaflet.js"></script>
    <script src="js/lib/leaflet-routing-machine.js"></script>

    <!-- تمرير بيانات الطلب للمحرك الذكي (tracking.js) -->
    <script>
        const TRACKING_DATA = {
            orderId: <?php echo $order_id; ?>,
            statusKeys: <?php echo json_encode($status_keys); ?>,
            businessLat: <?php echo (float)$order['business_latitude']; ?>,
            businessLng: <?php echo (float)$order['business_longitude']; ?>,
            customerLat: <?php echo (float)$order['customer_latitude']; ?>,
            customerLng: <?php echo (float)$order['customer_longitude']; ?>,
            initialStatus: '<?php echo $order['status']; ?>'
        };
    </script>
    
    <!-- استدعاء المحرك المطور (Cinematic Live Tracking) -->
    <script src="js/tracking.js?v=9.0"></script>

</body>
</html>