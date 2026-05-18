<?php
require_once 'php/db_connect.php';
$page_title = 'إدارة الطلبات - Syriazzle';
include 'header_store.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) { exit; }
$current_user_id = (int)$_SESSION['user_id'];

// توليد رمز CSRF للأمان إذا لم يكن موجوداً
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

try {
    $sql_orders = "
        SELECT 
            o.id, 
            o.customer_name, 
            o.customer_phone, 
            o.customer_address, 
            o.customer_latitude, 
            o.customer_longitude, 
            o.total_price, 
            o.status, 
            o.created_at, 
            o.currency,
            b.name as business_name
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        WHERE b.user_id = ?
        ORDER BY o.created_at DESC
    ";
    $stmt_orders = $pdo->prepare($sql_orders);
    $stmt_orders->execute([$current_user_id]);
    $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

    // حساب عدد الطلبات لكل حالة
    $status_counts = array_fill_keys(['pending_approval', 'preparing', 'ready_for_pickup', 'accepted', 'picked_up', 'delivered', 'canceled'], 0);
    foreach ($orders as $order) {
        if (array_key_exists($order['status'], $status_counts)) {
            $status_counts[$order['status']]++;
        }
    }

} catch (PDOException $e) { 
    die("خطأ في جلب الطلبات: ". $e->getMessage()); 
}

// تعريف خريطة الحالات (النصوص، الألوان، الأيقونات)
$status_map = [
    'pending_approval' => ['text' => 'طلبات جديدة', 'color' => '#ffc107', 'icon' => 'fa-bell'],
    'preparing'        => ['text' => 'قيد التحضير', 'color' => '#0d6efd', 'icon' => 'fa-cogs'],
    'ready_for_pickup' => ['text' => 'جاهزة (بانتظار السائق)', 'color' => '#17a2b8', 'icon' => 'fa-box-open'],
    'accepted'         => ['text' => 'السائق قادم للاستلام', 'color' => '#6f42c1', 'icon' => 'fa-user-clock'], 
    'picked_up'        => ['text' => 'مع الكابتن', 'color' => '#fd7e14', 'icon' => 'fa-motorcycle'],
    'delivered'        => ['text' => 'مكتملة', 'color' => '#198754', 'icon' => 'fa-check-circle'],
    'canceled'         => ['text' => 'ملغاة', 'color' => '#dc3545', 'icon' => 'fa-times-circle'],
];

// دالة مساعدة لتنسيق السعر مع العملة
function formatPriceWithCurrency($amount, $currencyCode) {
    if ($currencyCode === 'USD') {
        return '$' . number_format($amount, 2);
    }
    return number_format($amount) . ' ل.س';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- مكتبات التنسيق -->
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/lib/leaflet.css" />
    <style>
        :root { 
            --primary-color: #e60000; --secondary-color: #0d6efd; --bg-main: #f0f2f5; 
            --card-bg: rgba(255, 255, 255, 0.9); --card-border: rgba(255, 255, 255, 0.5);
            --text-dark: #212529; --text-light: #5a6472; --border-color: #e9ecef;
            --success-color: #198754; --warning-color: #ffc107;
        }
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            background-attachment: fixed;
            font-family: 'Cairo', sans-serif;
        }
        .dashboard-container { max-width: 1200px; margin: 0 auto; padding: 10px; }
        
        /* شريط التنقل */
        .dashboard-nav {
            background: var(--card-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--card-border); border-radius: 12px; padding: 10px;
            display: flex; justify-content: center; gap: 10px; margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .dashboard-nav a {
            flex-grow: 1; text-align: center; text-decoration: none; color: var(--text-light);
            padding: 12px 0px; border-radius: 8px; font-weight: 700; font-size: 15px;
            transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .dashboard-nav a:hover:not(.active) { background-color: #f0f2f5; color: var(--text-dark); }
        .dashboard-nav a.active {
            background-color: var(--secondary-color); color: #fff;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3); transform: translateY(-2px);
        }

        /* الفلاتر */
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 25px; overflow-x: auto; scrollbar-width: none; padding-bottom: 5px; }
        .filter-tabs::-webkit-scrollbar { display: none; }
        .filter-btn {
            flex-shrink: 0; padding: 10px 20px; font-size: 15px; font-weight: 700;
            background: var(--card-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--card-border); cursor: pointer; color: var(--text-light);
            border-radius: 8px; transition: all 0.3s; display: flex; align-items: center; gap: 8px;
        }
        .filter-btn .badge { background-color: var(--text-light); color: #fff; font-size: 11px; width: 22px; height: 22px; border-radius: 50%; display: flex; justify-content: center; align-items: center; transition: all 0.3s; }
        .filter-btn.active { color: #626c79; }
        .filter-btn.active .badge { background-color: #626c79; }
        
        /* تلوين الأزرار النشطة */
        <?php foreach ($status_map as $status_key => $status_info): ?>
        .filter-btn[data-status="<?php echo $status_key; ?>"].active { background-color: <?php echo $status_info['color']; ?>; box-shadow: 0 4px 15px <?php echo $status_info['color']; ?>55; color: #fff; border-color: <?php echo $status_info['color']; ?>; }
        .filter-btn[data-status="<?php echo $status_key; ?>"].active .badge { color: <?php echo $status_info['color']; ?>; background-color: rgba(255,255,255,0.9); }
        <?php endforeach; ?>

        /* شبكة الطلبات */
        .orders-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 25px; }
        .order-card {
            background: var(--card-bg); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--card-border); border-radius: 15px; box-shadow: 0 8px 32px 0 rgba(0,0,0,0.1);
            display: flex; flex-direction: column; transition: transform 0.2s;
        }
        .order-card:hover { transform: translateY(-5px); }
        
        .order-card-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .order-id { font-weight: 800; font-size: 18px; }
        
        /* شارة الحالة في الكرت */
        .status-badge {
            font-size: 12px; font-weight: 700; padding: 5px 12px; border-radius: 20px;
            display: inline-flex; align-items: center; gap: 6px; color: #fff;
        }

        .order-card-body { padding: 20px; flex-grow: 1; }
        .customer-info-line { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; color: #555; }
        .customer-info-line i { font-size: 16px; color: var(--text-light); width: 20px; text-align: center; }
        .customer-info-line span { font-weight: 600; }
        
        .order-card-footer { padding: 15px 20px; background-color: rgba(0,0,0,0.02); border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .total-price { font-size: 20px; font-weight: 800; color: var(--primary-color); }
        .view-details-btn { background-color: var(--secondary-color); color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        
        .empty-state { text-align: center; padding: 60px; background: var(--card-bg); border-radius: 15px; grid-column: 1 / -1; }

        /* المودال */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1020; display: none; justify-content: center; align-items: center; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
        .modal-overlay.visible { display: flex; }
        .modal-content { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 15px; max-width: 700px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        
        .modal-header { padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); background: #fff; border-radius: 15px 15px 0 0; }
        .modal-header h2 { margin: 0; font-size: 22px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-light); }
        
        .modal-body { padding: 25px; overflow-y: auto; display: grid; grid-template-columns: 1fr 250px; gap: 25px; background: #fff; }
        .modal-footer { padding: 15px 25px; border-top: 1px solid var(--border-color); background-color: #f8f9fa; text-align: left; border-radius: 0 0 15px 15px; }
        
        .order-details-section h4 { margin: 0 0 15px 0; font-size: 16px; color: var(--secondary-color); border-bottom: 1px solid #eee; padding-bottom: 8px; }
        .customer-info p { margin: 0 0 10px 0; font-size: 15px; } 
        .order-items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .order-items-table th, .order-items-table td { text-align: right; padding: 8px; border-bottom: 1px solid #eee; }
        .order-items-table thead th { font-size: 14px; color: var(--text-light); }
        
        #modal-map { height: 220px; border-radius: 8px; width: 100%; border: 1px solid var(--border-color); }
        
        .action-button { border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; margin-right: 10px; }
        .action-button:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-approve { background-color: var(--success-color); color: #fff; }
        .btn-ready { background-color: #17a2b8; color: #fff; }
        .btn-cancel { background-color: #6c757d; color: #fff; }
        
        .order-items-table .item-notes-row td { padding: 8px; font-size: 13px; color: #b95000; background: #fff4e8; border-bottom: 1px solid #ffe8d6; }
        .item-notes-row i { margin-left: 5px; }

        @media (max-width: 768px) { .modal-body { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- ترويسة الصفحة -->
        <div class="dashboard-header"><h1>📦 إدارة الطلبات</h1></div>
        
        <!-- قائمة التنقل -->
        <div class="dashboard-nav">
            <a href="business_dashboard.php"><i class="fas fa-chart-line"></i> لوحة القيادة</a>
            <a href="manage_orders.php" class="active"><i class="fas fa-receipt"></i> إدارة الطلبات</a>
            <a href="manage_reviews_user.php"><i class="fas fa-star"></i> إدارة المراجعات</a>
        </div>

        <!-- أزرار الفلترة حسب الحالة -->
        <div class="filter-tabs" id="filter-tabs">
            <button class="filter-btn active" data-status="all">الكل <span class="badge"><?php echo count($orders); ?></span></button>
            <?php foreach ($status_map as $status_key => $status_info): ?>
                <button class="filter-btn" data-status="<?php echo $status_key; ?>">
                    <i class="fas <?php echo $status_info['icon']; ?>"></i> <?php echo $status_info['text']; ?> 
                    <span class="badge"><?php echo $status_counts[$status_key]; ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- شبكة عرض الطلبات -->
        <div class="orders-grid" id="orders-grid">
            <?php if (empty($orders)): ?>
                <div class="empty-state"><h2>لا توجد طلبات حالياً.</h2></div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php 
                        // تحديد مفتاح الحالة وتنسيقها
                        $st_key = $order['status'];
                        if ($st_key === 'new') $st_key = 'pending_approval';
                        $st_info = $status_map[$st_key] ?? $status_map['pending_approval'];
                        
                        // تحديد رمز العملة لتمريره إلى الجافاسكربت
                        $currencySymbol = ($order['currency'] === 'USD') ? '$' : 'ل.س';
                        
                        // إضافة الرمز للبيانات التي ستخزن في السمة data-order-data
                        $order['currencySymbol'] = $currencySymbol;
                    ?>
                    
                    <!-- بطاقة الطلب -->
                    <div class="order-card" data-status="<?php echo $st_key; ?>" data-order-data='<?php echo json_encode($order, JSON_UNESCAPED_UNICODE); ?>'>
                        <div class="order-card-header">
                            <span class="order-id">طلب #<?php echo $order['id']; ?></span>
                            <span class="status-badge" style="background-color: <?php echo $st_info['color']; ?>">
                                <i class="fas <?php echo $st_info['icon']; ?>"></i> <?php echo $st_info['text']; ?>
                            </span>
                        </div>
                        <div class="order-card-body">
                            <div class="customer-info-line"><i class="fas fa-user"></i> <span><?php echo htmlspecialchars($order['customer_name']); ?></span></div>
                            <div class="customer-info-line"><i class="fas fa-phone"></i> <span><?php echo htmlspecialchars($order['customer_phone']); ?></span></div>
                            <div class="customer-info-line"><i class="fas fa-store"></i> <span><?php echo htmlspecialchars($order['business_name']); ?></span></div>
                        </div>
                        <div class="order-card-footer">
                            <!-- عرض السعر بالعملة الصحيحة في القائمة الخارجية -->
                            <span class="total-price"><?php echo formatPriceWithCurrency($order['total_price'], $order['currency']); ?></span>
                            <button class="view-details-btn">التفاصيل</button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <!-- رسالة عند عدم وجود طلبات في الفلتر المختار -->
                <div id="no-orders-message" class="empty-state" style="display:none;"><h2>لا توجد طلبات بهذه الحالة.</h2></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- النافذة المنبثقة لتفاصيل الطلب (Modal) -->
    <div class="modal-overlay" id="order-details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-order-title"></h2>
                <button class="modal-close" id="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="order-details-main">
                    <div class="order-details-section customer-info">
                        <h4><i class="fas fa-user"></i> معلومات الزبون</h4>
                        <p><strong>الاسم:</strong> <span id="modal-customer-name"></span></p>
                        <p><strong>الهاتف:</strong> <span id="modal-customer-phone"></span></p>
                        <p><strong>العنوان:</strong> <span id="modal-customer-address"></span></p>
                    </div>
                    <div class="order-details-section order-items">
                        <h4><i class="fas fa-shopping-basket"></i> المنتجات المطلوبة</h4>
                        <table class="order-items-table">
                            <thead><tr><th>المنتج</th><th>الكمية</th><th>السعر</th></tr></thead>
                            <tbody id="modal-order-items"></tbody>
                        </table>
                    </div>
                </div>
                <div class="order-details-sidebar">
                    <div class="order-details-section">
                        <h4><i class="fas fa-map-marker-alt"></i> موقع التوصيل</h4>
                        <div id="modal-map" class="map-container"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="modal-footer-actions"></div>
        </div>
    </div>

    <!-- مكتبات الجافاسكربت -->
    <script src="js/lib/leaflet.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('order-details-modal');
        const ordersGrid = document.getElementById('orders-grid');
        let orderMap = null;

        // تفعيل أزرار الفلترة
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const status = btn.dataset.status;
                let visible = 0;
                document.querySelectorAll('.order-card').forEach(card => {
                    if (status === 'all' || card.dataset.status === status) {
                        card.style.display = 'flex'; visible++;
                    } else { card.style.display = 'none'; }
                });
                document.getElementById('no-orders-message').style.display = (visible === 0 && status !== 'all') ? 'block' : 'none';
            });
        });

        // الاستماع للنقر على زر التفاصيل
        ordersGrid.addEventListener('click', (e) => {
            if (e.target.matches('.view-details-btn')) {
                const card = e.target.closest('.order-card');
                const data = JSON.parse(card.dataset.orderData);
                openOrderModal(data);
            }
        });
        
        // دالة فتح المودال وتعبئة البيانات
        function openOrderModal(details) {
            document.getElementById('modal-order-title').textContent = `تفاصيل الطلب #${details.id}`;
            document.getElementById('modal-customer-name').textContent = details.customer_name;
            document.getElementById('modal-customer-phone').innerHTML = `<a href="tel:${details.customer_phone}">${details.customer_phone}</a>`;
            document.getElementById('modal-customer-address').textContent = details.customer_address || 'لا يوجد';
            
            // استدعاء دالة جلب المنتجات مع تمرير رمز العملة
            fetchOrderItems(details.id, details.currencySymbol);
            
            // تحديث أزرار الإجراءات
            updateModalActions(details.id, details.status);
            
            modal.classList.add('visible');
            
            // تهيئة الخريطة
            const lat = parseFloat(details.customer_latitude);
            const lon = parseFloat(details.customer_longitude);
            const mapContainer = document.getElementById('modal-map');
            if (!isNaN(lat) && !isNaN(lon)) {
                mapContainer.style.display = 'block';
                setTimeout(() => {
                    if (!orderMap) {
                        orderMap = L.map('modal-map').setView([lat, lon], 15);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(orderMap);
                    } else { orderMap.setView([lat, lon], 15); }
                    
                    orderMap.eachLayer(layer => { if (layer instanceof L.Marker) layer.remove(); });
                    L.marker([lat, lon]).addTo(orderMap);
                    orderMap.invalidateSize();
                }, 200);
            } else { mapContainer.style.display = 'none'; }
        }
        
        // دالة جلب عناصر الطلب عبر AJAX
        async function fetchOrderItems(orderId, currencySymbol) {
            const tbody = document.getElementById('modal-order-items');
            tbody.innerHTML = '<tr><td colspan="3">جارٍ التحميل...</td></tr>';
            try {
                // يفترض وجود ملف get_order_items.php
                const response = await fetch(`php/get_order_items.php?order_id=${orderId}`);
                const items = await response.json();
                
                if(items.error) throw new Error(items.error);
                
                tbody.innerHTML = '';
                if (items.length === 0) { 
                    tbody.innerHTML = `<tr><td colspan="3">لا توجد منتجات.</td></tr>`; 
                } else { 
                    items.forEach(item => {
                        const note = item.special_requests ? `<tr class="item-notes-row"><td colspan="3"><i class="fas fa-sticky-note"></i> ${item.special_requests}</td></tr>` : '';
                        
                        // تنسيق السعر داخل المودال بناءً على العملة
                        let priceDisplay;
                        if (currencySymbol === '$') {
                             priceDisplay = '$' + parseFloat(item.price_per_item).toLocaleString('en-US', {minimumFractionDigits: 2});
                        } else {
                             priceDisplay = parseFloat(item.price_per_item).toLocaleString('en-US') + ' ل.س';
                        }

                        tbody.innerHTML += `<tr><td>${item.item_name}</td><td>${item.quantity}</td><td>${priceDisplay}</td></tr>${note}`;
                    });
                }
            } catch (error) { tbody.innerHTML = `<tr><td colspan="3">فشل التحميل.</td></tr>`; }
        }
        
        // تحديث الأزرار المتاحة حسب حالة الطلب
        function updateModalActions(orderId, status) {
            const footer = document.getElementById('modal-footer-actions');
            footer.innerHTML = ''; let btns = '';
            
            if (status === 'pending_approval') {
                btns = `<button class="action-button btn-approve" onclick="changeStatus(${orderId}, 'preparing')">قبول الطلب</button>
                        <button class="action-button btn-cancel" onclick="changeStatus(${orderId}, 'canceled')">رفض</button>`;
            } else if (status === 'preparing') {
                btns = `<button class="action-button btn-ready" onclick="changeStatus(${orderId}, 'ready_for_pickup')">جاهز (اطلب سائق)</button>`;
            } else if (status === 'ready_for_pickup') {
                btns = `<span style="color:#17a2b8; font-weight:bold;">بانتظار قبول سائق...</span>`;
            } else if (status === 'accepted') {
                btns = `<span style="color:#6f42c1; font-weight:bold; font-size:1rem;"><i class="fas fa-motorcycle"></i> السائق قادم للاستلام الآن...</span>`;
            } else if (status === 'picked_up') {
                btns = `<span style="color:#fd7e14; font-weight:bold;">الطلب مع السائق</span>`;
            } else if (status === 'delivered') {
                btns = `<span style="color:#198754; font-weight:bold;">تم التوصيل بنجاح</span>`;
            } else if (status === 'canceled') {
                btns = `<span style="color:#dc3545; font-weight:bold;">تم إلغاء الطلب</span>`;
            }
            
            footer.innerHTML = btns;
        }
        
        // دالة تغيير الحالة
        window.changeStatus = async (orderId, newStatus) => {
            let reason = null;
            if (newStatus === 'canceled') {
                reason = prompt("سبب الرفض:");
                if (!reason) return;
            } else {
                if (!confirm("تأكيد الإجراء؟")) return;
            }
            
            const allBtns = document.querySelectorAll('.action-button');
            allBtns.forEach(b => b.disabled = true);

            try {
                const fd = new FormData();
                fd.append('order_id', orderId);
                fd.append('new_status', newStatus);
                fd.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                if(reason) fd.append('cancellation_reason', reason);

                const res = await fetch('php/update_order_status.php', { method: 'POST', body: fd });
                const d = await res.json();
                
                if (d.success) {
                    alert('تم بنجاح!');
                    window.location.reload();
                } else {
                    alert('خطأ: ' + d.message);
                    allBtns.forEach(b => b.disabled = false);
                }
            } catch (e) { alert('خطأ اتصال'); allBtns.forEach(b => b.disabled = false); }
        };
        
        // إغلاق المودال
        document.getElementById('modal-close-btn').onclick = () => modal.classList.remove('visible');
        modal.onclick = (e) => { if(e.target === modal) modal.classList.remove('visible'); };

        // تحديث تلقائي للصفحة كل دقيقة
        setInterval(() => { window.location.reload(); }, 60000);
    });
    </script>
</body>
</html>