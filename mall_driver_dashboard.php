<?php
// ========================================================================
// Syriazzle Mall - Driver Dashboard (النسخة النهائية مع التصميم الفاخر)
// ========================================================================
require_once 'php/db_connect.php';
$page_title = 'لوحة السائق - Syriazzle';

// التحقق الأمني
if (!isset($_SESSION['driver_id']) || !isset($_SESSION['driver_type']) || $_SESSION['driver_type'] !== 'in_house') {
    if (isset($_SESSION['driver_type']) && $_SESSION['driver_type'] === 'marketplace') {
        header('Location: driver_dashboard.php');
    } else {
        header('Location: driver_login.php');
    }
    exit;
}
$driver_id = (int)$_SESSION['driver_id'];

try {
    // جلب بيانات السائق
    $stmt_driver = $pdo->prepare("SELECT full_name, phone FROM drivers WHERE id = ?");
    $stmt_driver->execute([$driver_id]);
    $driver_data = $stmt_driver->fetch(PDO::FETCH_ASSOC);

    // جلب المهام النشطة (قيد التحضير أو مع السائق)
    $stmt_tasks = $pdo->prepare("
        SELECT id, customer_name, customer_phone, address_details, total_price, status, created_at 
        FROM mall_orders 
        WHERE assigned_driver_id = ? AND status IN ('preparing', 'out_for_delivery')
        ORDER BY created_at ASC
    ");
    $stmt_tasks->execute([$driver_id]);
    $tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

    // إحصائية بسيطة لليوم
    $stmt_stats = $pdo->prepare("SELECT COUNT(*) FROM mall_orders WHERE assigned_driver_id = ? AND status = 'delivered' AND DATE(status_last_updated) = CURDATE()");
    $stmt_stats->execute([$driver_id]);
    $completed_today = $stmt_stats->fetchColumn();

} catch (PDOException $e) {
    die("خطأ النظام: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    
    <style>
        :root {
            --primary: #007bff;
            --primary-dark: #0056b3;
            --secondary: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --bg-light: #f4f6f9;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0,0,0,0.05);
            --radius: 16px;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background-color: var(--bg-light);
            margin: 0;
            padding: 0;
            color: #333;
            -webkit-tap-highlight-color: transparent;
        }

        /* رأس الصفحة الجميل */
        .driver-app-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 25px 20px 40px; /* مسافة إضافية من الأسفل للتداخل */
            border-bottom-left-radius: 30px;
            border-bottom-right-radius: 30px;
            box-shadow: 0 10px 20px rgba(0, 123, 255, 0.2);
            position: relative;
            z-index: 1;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .driver-info h1 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 700;
        }

        .driver-info span {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: var(--white);
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            backdrop-filter: blur(5px);
            transition: 0.3s;
        }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }

        /* بطاقة الإحصائيات العائمة */
        .stats-summary {
            background: var(--white);
            width: 85%;
            margin: -30px auto 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 15px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .stat-item { text-align: center; }
        .stat-val { display: block; font-size: 1.4rem; font-weight: 800; color: var(--primary); }
        .stat-label { font-size: 0.8rem; color: var(--secondary); font-weight: 600; }

        /* حاوية المهام */
        .tasks-container {
            padding: 10px 20px 80px; /* مسافة من الأسفل للتمرير */
            max-width: 600px;
            margin: 0 auto;
        }

        .section-title {
            font-size: 1.1rem;
            color: #495057;
            margin-bottom: 15px;
            padding-right: 5px;
            border-right: 4px solid var(--primary);
            padding-right: 10px;
        }

        /* تصميم بطاقة الطلب */
        .task-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.03);
            transition: transform 0.2s;
        }
        .task-card:active { transform: scale(0.98); }

        .card-header {
            background: #fafbfc;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-id { font-weight: 800; color: #333; font-size: 1rem; }
        .order-price { 
            background: #e7f5ff; color: var(--primary); 
            padding: 5px 12px; border-radius: 20px; 
            font-weight: 700; font-size: 0.9rem; 
        }

        .card-body { padding: 15px; }
        .info-row { display: flex; align-items: flex-start; margin-bottom: 12px; }
        .info-row:last-child { margin-bottom: 0; }
        .info-icon { 
            width: 35px; height: 35px; 
            background: #f8f9fa; border-radius: 50%; 
            display: flex; justify-content: center; align-items: center;
            color: var(--secondary); margin-left: 12px; flex-shrink: 0;
        }
        .info-content label { display: block; font-size: 0.75rem; color: var(--secondary); }
        .info-content span { display: block; font-weight: 600; color: #333; line-height: 1.4; }

        /* الأزرار والإجراءات */
        .card-footer {
            padding: 15px;
            display: grid;
            grid-template-columns: 1fr 1.5fr; /* الزر الرئيسي أكبر */
            gap: 10px;
        }

        .btn {
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: flex; justify-content: center; align-items: center; gap: 8px;
            transition: opacity 0.2s;
        }
        .btn:active { opacity: 0.8; }

        .btn-map {
            background: #e9ecef; color: #333;
        }
        .btn-action {
            color: var(--white);
        }
        .btn-action.prepare { background: var(--warning); color: #333; }
        .btn-action.deliver { background: var(--success); }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--secondary);
        }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }

        /* نبض للحالة النشطة */
        .pulse-dot {
            width: 10px; height: 10px; background: var(--success);
            border-radius: 50%; display: inline-block; margin-right: 5px;
            box-shadow: 0 0 0 rgba(40, 167, 69, 0.4);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
    </style>
</head>
<body>

    <!-- الرأس الجميل -->
    <header class="driver-app-header">
        <div class="header-top">
            <div class="driver-info">
                <h1>أهلاً، <?php echo htmlspecialchars($driver_data['full_name']); ?></h1>
                <span><span class="pulse-dot"></span> متصل - متاح للتوصيل</span>
            </div>
            <a href="php/driver_logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> خروج</a>
        </div>
    </header>

    <!-- ملخص سريع -->
    <div class="stats-summary">
        <div class="stat-item">
            <span class="stat-val"><?php echo count($tasks); ?></span>
            <span class="stat-label">مهام نشطة</span>
        </div>
        <div class="stat-item" style="border-right: 1px solid #eee;">
            <span class="stat-val"><?php echo $completed_today; ?></span>
            <span class="stat-label">مكتملة اليوم</span>
        </div>
    </div>

    <!-- قائمة المهام -->
    <div class="tasks-container">
        <h2 class="section-title">قائمة الطلبات الحالية</h2>
        
        <div id="tasks-list">
            <?php if (empty($tasks)): ?>
                <div class="empty-state">
                    <i class="fas fa-motorcycle"></i>
                    <h3>لا توجد طلبات معينة حالياً</h3>
                    <p>استمتع بوقتك! ستظهر الطلبات الجديدة هنا فور تعيينها لك.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card" id="task-<?php echo $task['id']; ?>">
                        <div class="card-header">
                            <span class="order-id">طلب #<?php echo $task['id']; ?></span>
                            <span class="order-price"><?php echo number_format($task['total_price']); ?> ل.س</span>
                        </div>
                        
                        <div class="card-body">
                            <div class="info-row">
                                <div class="info-icon"><i class="fas fa-user"></i></div>
                                <div class="info-content">
                                    <label>اسم الزبون</label>
                                    <span><?php echo htmlspecialchars($task['customer_name']); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-row">
                                <div class="info-icon"><i class="fas fa-map-marker-alt" style="color: #dc3545;"></i></div>
                                <div class="info-content">
                                    <label>عنوان التوصيل</label>
                                    <span><?php echo htmlspecialchars($task['address_details']); ?></span>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-icon"><i class="fas fa-phone-alt" style="color: #28a745;"></i></div>
                                <div class="info-content">
                                    <label>رقم الهاتف</label>
                                    <span><a href="tel:<?php echo htmlspecialchars($task['customer_phone']); ?>" style="text-decoration:none; color:inherit;"><?php echo htmlspecialchars($task['customer_phone']); ?></a></span>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <!-- زر الخريطة الجميل -->
                            <a href="mall_delivery_map.php?order_id=<?php echo $task['id']; ?>" class="btn btn-map">
                                <i class="fas fa-map-marked-alt"></i> الخريطة
                            </a>

                            <!-- زر الإجراء (يتغير حسب الحالة) -->
                            <?php if ($task['status'] === 'preparing'): ?>
                                <button class="btn btn-action prepare" onclick="updateOrderStatus(<?php echo $task['id']; ?>, 'out_for_delivery', this)">
                                    <i class="fas fa-box"></i> استلام الطلب
                                </button>
                            <?php elseif ($task['status'] === 'out_for_delivery'): ?>
                                <button class="btn btn-action deliver" onclick="updateOrderStatus(<?php echo $task['id']; ?>, 'delivered', this)">
                                    <i class="fas fa-check-circle"></i> تم التسليم
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- كود الجافاسكريبت المدمج لضمان العمل -->
    <script>
        // إرسال الموقع في الخلفية
        function updateLocation() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const fd = new FormData();
                    fd.append('latitude', position.coords.latitude);
                    fd.append('longitude', position.coords.longitude);
                    fetch('php/update_inhouse_driver_location.php', { method: 'POST', body: fd }).catch(()=>{});
                },
                null, { enableHighAccuracy: true }
            );
        }
        setInterval(updateLocation, 30000); // كل 30 ثانية
        updateLocation(); // فوراً عند الفتح

        // دالة تحديث الحالة
        async function updateOrderStatus(orderId, newStatus, btn) {
            let message = newStatus === 'delivered' 
                ? 'هل قمت بتسليم الطلب للزبون واستلام المبلغ كاملاً؟' 
                : 'هل أنت متأكد من استلام الطلب من المستودع؟';
            
            if (!confirm(message)) return;

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحديث...';

            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('status', newStatus);

            try {
                const response = await fetch('php/update_mall_order_status.php', { method: 'POST', body: fd });
                const result = await response.json();
                
                if (result.success) {
                    // تأثير بصري بسيط قبل إعادة التحميل
                    btn.innerHTML = '<i class="fas fa-check"></i> تم!';
                    btn.style.background = '#28a745';
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    alert('خطأ: ' + result.message);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (error) {
                alert('فشل الاتصال بالخادم، يرجى التحقق من الإنترنت.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>
</body>
</html>