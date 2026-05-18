<?php
// ========================================================================
// Syriazzle - Professional Driver Dashboard (V9.0 - World-Class UI)
// ========================================================================
require_once 'php/db_connect.php';

// 1. التحقق من الجلسة
if (!isset($_SESSION['driver_id'])) {
    header('Location: driver_login.php');
    exit;
}

$driver_id = (int)$_SESSION['driver_id'];
$page_title = 'لوحة تحكم الكابتن';

try {
    // 2. جلب بيانات السائق المالية والحالية
    $stmt_driver = $pdo->prepare("SELECT full_name, status, is_available, commission_balance, credit_limit, vehicle_type FROM drivers WHERE id = ?");
    $stmt_driver->execute([$driver_id]);
    $driver = $stmt_driver->fetch(PDO::FETCH_ASSOC);

    if (!$driver || $driver['status'] !== 'approved') {
        session_unset(); session_destroy();
        header('Location: driver_login.php?error=inactive');
        exit;
    }

    // 3. جلب المهمة النشطة
    $active_task = null;
    $stmt_task = $pdo->prepare("
        SELECT 
            o.id as order_id, o.status, o.delivery_fee, o.tip_amount, o.total_price, o.currency,
            o.customer_name, o.customer_phone, o.customer_address, o.customer_latitude, o.customer_longitude,
            b.name as business_name, b.address as business_address, b.latitude as business_latitude, b.longitude as business_longitude
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        WHERE o.driver_id = ? AND o.status IN ('accepted', 'picked_up', 'out_for_delivery')
        LIMIT 1
    ");
    $stmt_task->execute([$driver_id]);
    $active_task = $stmt_task->fetch(PDO::FETCH_ASSOC);

    function formatDashMoney($amount, $currency) {
        return ($currency === 'USD' ? '$' : '') . number_format($amount) . ($currency === 'SYP' ? ' ل.س' : '');
    }

} catch (PDOException $e) {
    die("خطأ في الاتصال بالخادم.");
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    
    <!-- الملحقات -->
    <link rel="stylesheet" href="css/lib/leaflet.css" />
    <link rel="stylesheet" href="css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --sy-red: #e60000;
            --sy-blue: #007bff;
            --sy-green: #28a745;
            --sy-dark: #2d3436;
            --sy-bg: #f0f2f5;
        }

        body { 
            background-color: var(--sy-bg); 
            margin: 0; padding: 0;
            overscroll-behavior-y: none;
            -webkit-font-smoothing: antialiased;
        }

        .driver-app-container {
            max-width: 550px;
            margin: 0 auto;
            padding: 15px;
        }

        /* 1. كرت الإحصائيات السريعة (Stats) */
        .quick-stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            padding: 15px;
            border-radius: 22px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            gap: 5px;
            border: 1px solid #fff;
        }

        .stat-card i { font-size: 1.2rem; color: var(--sy-blue); margin-bottom: 5px; }
        .stat-label { font-size: 0.75rem; color: #888; font-weight: 600; }
        .stat-value { font-size: 1rem; font-weight: 800; color: var(--sy-dark); }
        .stat-value.debt { color: var(--sy-red); }

        /* 2. مفتاح التشغيل (Online/Offline Toggle) */
        .availability-card {
            background: #fff;
            padding: 20px;
            border-radius: 25px;
            margin-bottom: 25px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #fff;
        }

        .status-header-ui {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .status-dot-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 0.9rem;
        }

        .dot { width: 10px; height: 10px; border-radius: 50%; }
        .online .dot { background: var(--sy-green); box-shadow: 0 0 10px var(--sy-green); }
        .offline .dot { background: #adb5bd; }

        .toggle-service-btn {
            width: 100%;
            padding: 16px;
            border-radius: 18px;
            border: none;
            font-weight: 900;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s ease;
            font-family: 'Cairo';
        }

        .btn-go-online { background: var(--sy-green); color: #fff; box-shadow: 0 5px 15px rgba(40,167,69,0.3); }
        .btn-go-offline { background: #fff; color: var(--sy-red); border: 2px solid var(--sy-red); }

        /* 3. كرت المهمة النشطة (Active Task) */
        .active-order-wrapper {
            background: #fff;
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .map-section-h { height: 260px; width: 100%; background: #e9ecef; position: relative; }
        #task-map { height: 100%; width: 100%; }

        .order-content-h { padding: 25px; }
        .order-id-badge { background: #f1f2f6; color: #555; padding: 4px 12px; border-radius: 10px; font-size: 0.7rem; font-weight: bold; }
        
        .price-row-h {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0 20px;
        }
        .main-price { font-size: 1.6rem; font-weight: 900; color: var(--sy-red); }
        .earnings-tag { color: var(--sy-green); font-weight: 800; font-size: 0.95rem; }

        .loc-step-h { display: flex; gap: 15px; margin-bottom: 20px; position: relative; }
        .loc-step-h::after { content: ''; position: absolute; top: 35px; right: 17px; bottom: -10px; width: 2px; background: #eee; }
        .loc-step-h.last::after { display: none; }

        .step-circle-h {
            width: 36px; height: 36px; background: #f0f7ff; color: var(--sy-blue);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1rem; z-index: 2;
        }

        .step-txt-h h4 { margin: 0; font-size: 0.8rem; color: #999; }
        .step-txt-h p { margin: 3px 0 0; font-weight: 700; color: #333; font-size: 1rem; }

        .action-swipe-btn {
            width: 100%; padding: 18px; border-radius: 20px; border: none;
            font-size: 1.15rem; font-weight: 900; color: #fff; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15); margin-top: 10px;
            font-family: 'Cairo';
        }
        .btn-warning { background: linear-gradient(135deg, #ffc107, #ff9800); color: #212529; }
        .btn-success { background: linear-gradient(135deg, #28a745, #1e7e34); }

        /* منطقة البحث عن طلبات */
        .searching-loader {
            text-align: center; padding: 60px 20px; color: #bbb;
        }
        .searching-loader i { font-size: 3rem; margin-bottom: 15px; display: block; }
        
        /* زر المحفظة (تم إعادته للظهور) */
        .wallet-btn-float {
            position: fixed; bottom: 25px; left: 25px; 
            background: var(--sy-dark); color: #fff; width: 60px; height: 60px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3); z-index: 1000; text-decoration: none;
        }
    </style>
</head>
<body>

    <!-- الهيدر الموحد -->
    <?php include 'header_store.php'; ?>

    <main class="driver-app-container">

        <?php if (!$active_task): ?>
            <!-- 1. واجهة الإحصائيات والبحث -->
            <div class="quick-stats-grid">
                <a href="driver_wallet.php" style="text-decoration:none;" class="stat-card">
                    <i class="fas fa-wallet"></i>
                    <span class="stat-label">رصيد مستحقاتك</span>
                    <span class="stat-value"><?php echo number_format($driver['commission_balance']); ?> ل.س</span>
                </a>
                <div class="stat-card">
                    <i class="fas fa-shield-alt"></i>
                    <span class="stat-label">الحد الائتماني</span>
                    <span class="stat-value debt"><?php echo number_format($driver['credit_limit']); ?> ل.س</span>
                </div>
            </div>

            <div class="availability-card">
                <div class="status-header-ui <?php echo $driver['is_available'] ? 'online' : 'offline'; ?>">
                    <div class="status-dot-label">
                        <div class="dot"></div>
                        <span>حالتك الآن: <?php echo $driver['is_available'] ? 'متصل' : 'غير متصل'; ?></span>
                    </div>
                    <span style="font-size: 0.75rem; color: #999;">مركبة: <?php echo $driver['vehicle_type']; ?></span>
                </div>
                
                <button id="toggle-online-btn" class="toggle-service-btn <?php echo $driver['is_available'] ? 'btn-go-offline' : 'btn-go-online'; ?>">
                    <i class="fas fa-power-off"></i>
                    <span id="toggle-online-btn-text">
                        <?php echo $driver['is_available'] ? 'إيقاف استقبال الطلبات' : 'بدء استقبال الطلبات'; ?>
                    </span>
                </button>
            </div>

            <div id="idle-view">
                <div id="available-tasks-list">
                    <?php if ($driver['is_available']): ?>
                        <div class="searching-loader">
                            <i class="fas fa-radar fa-spin"></i>
                            <p>جاري المسح الجغرافي عن طلبات قريبة...</p>
                        </div>
                    <?php else: ?>
                        <div class="searching-loader">
                            <i class="fas fa-moon"></i>
                            <p>أنت في وضع الاستراحة، لن تظهر لك طلبات.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- 2. واجهة المهمة النشطة (Pro Tracking) -->
            <div class="active-order-wrapper">
                <div class="map-section-h">
                    <div id="task-map"></div>
                </div>

                <div class="order-content-h">
                    <div class="task-header-h" style="display:flex; justify-content:space-between; align-items:center;">
                        <span class="order-id-badge">طلب #<?php echo $active_task['order_id']; ?></span>
                        <div class="earnings-tag">ربحك: <?php echo number_format($active_task['delivery_fee'] + $active_task['tip_amount']); ?> ل.س</div>
                    </div>

                    <div class="price-row-h">
                        <h3 class="main-price"><?php echo formatDashMoney($active_task['total_price'], $active_task['currency']); ?></h3>
                        <span style="font-size:0.75rem; color:#888;">المبلغ المطلوب تحصيله</span>
                    </div>

                    <div class="loc-step-h">
                        <div class="step-circle-h"><i class="fas fa-store"></i></div>
                        <div class="step-txt-h">
                            <h4>الاستلام من:</h4>
                            <p><?php echo htmlspecialchars($active_task['business_name']); ?></p>
                        </div>
                    </div>

                    <div class="loc-step-h last">
                        <div class="step-circle-h" style="background:#e8f5e9; color:var(--sy-green);"><i class="fas fa-user-pin"></i></div>
                        <div class="step-txt-h">
                            <h4>التسليم إلى:</h4>
                            <p><?php echo htmlspecialchars($active_task['customer_name']); ?></p>
                            <small style="color:#666;"><?php echo htmlspecialchars($active_task['customer_address']); ?></small>
                        </div>
                    </div>

                    <div class="task-actions-ui">
                        <?php if ($active_task['status'] === 'accepted'): ?>
                            <button class="action-swipe-btn btn-warning" onclick="updateOrderStatus(<?php echo $active_task['order_id']; ?>, 'picked_up', this)">
                                <i class="fas fa-box-open"></i> وصلت واستلمت الطلب
                            </button>
                        <?php else: ?>
                            <button class="action-swipe-btn btn-success" onclick="updateOrderStatus(<?php echo $active_task['order_id']; ?>, 'delivered', this)">
                                <i class="fas fa-check-circle"></i> تم التسليم وتحصيل المبلغ
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- زر المحفظة العائم (Floating Action Button) -->
    <a href="driver_wallet.php" class="wallet-btn-float" title="المحفظة">
        <i class="fas fa-wallet" style="font-size:1.5rem;"></i>
    </a>

    <!-- Scripts -->
    <script src="js/lib/leaflet.js"></script>
    <script src="js/lib/leaflet-routing-machine.js"></script>
    <script>
        const DRIVER_DATA = {
            driverId: <?php echo $driver_id; ?>,
            isInitiallyOnline: <?php echo json_encode((bool)$driver['is_available']); ?>,
            hasActiveTask: <?php echo json_encode((bool)$active_task); ?>,
            activeTask: <?php echo json_encode($active_task); ?>
        };
    </script>
    <script src="js/driver.js?v=<?php echo time(); ?>"></script>

</body>
</html>