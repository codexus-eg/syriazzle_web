<?php
// ========================================================================
// Syriazzle Admin - Order Details (Final Multi-Currency Version)
// ========================================================================

$page_title = 'تفاصيل الطلب';
include 'header.php';

// --- حارس البوابة: التحقق من الصلاحية ---
if (!hasPermission('edit_order')) {
    echo "<div style='text-align:center; margin-top:50px; color:red;'><h2>وصول غير مصرح به.</h2></div>"; 
    include 'footer.php'; 
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id === 0) { 
    echo "<div class='alert alert-danger'>خطأ: رقم الطلب غير صحيح.</div>"; 
    include 'footer.php'; 
    exit;
}

try {
    // 1. جلب تفاصيل الطلب كاملة مع البيانات المرتبطة (تمت إضافة o.currency)
    $sql = "
        SELECT 
            o.*,
            b.name as business_name, b.phone as business_phone, 
            b.latitude as business_lat, b.longitude as business_lon, 
            b.governorate_id as business_governorate_id,
            u.username as customer_username, u.phone as customer_phone_registered,
            d.full_name as driver_name, d.phone as driver_phone, 
            d.current_latitude as driver_lat, d.current_longitude as driver_lon
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN drivers d ON o.driver_id = d.id
        WHERE o.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) { 
        echo "<div class='alert alert-warning'>الطلب غير موجود.</div>"; 
        include 'footer.php'; 
        exit; 
    }

    // --- حارس البوابة الجغرافي ---
    if (!hasPermission('super_admin_access_all') && isset($admin_governorate_id)) {
        if ($admin_governorate_id !== (int)$order['business_governorate_id']) {
            echo "<div class='alert alert-danger'>لا تملك صلاحية عرض طلبات هذه المحافظة.</div>";
            include 'footer.php';
            exit;
        }
    }
    
    // 2. جلب عناصر الطلب
    $stmt_items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt_items->execute([$order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // 3. جلب السائقين المتاحين
    $available_drivers_stmt = $pdo->prepare("
        SELECT id, full_name 
        FROM drivers 
        WHERE status = 'approved' 
          AND is_available = 1 
          AND governorate_id = ?
    ");
    $available_drivers_stmt->execute([$order['business_governorate_id']]);
    $available_drivers = $available_drivers_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. تعريف الحالات
    $all_statuses = [
        'pending_approval' => ['text' => 'بانتظار الموافقة', 'icon' => 'fa-paper-plane'],
        'preparing'        => ['text' => 'قيد التحضير', 'icon' => 'fa-cogs'],
        'ready_for_pickup' => ['text' => 'جاهز للاستلام', 'icon' => 'fa-box-open'],
        'accepted'         => ['text' => 'السائق قادم', 'icon' => 'fa-user-clock'],
        'picked_up'        => ['text' => 'مع السائق', 'icon' => 'fa-motorcycle'],
        'delivered'        => ['text' => 'تم التوصيل', 'icon' => 'fa-check-circle'],
        'canceled'         => ['text' => 'ملغي', 'icon' => 'fa-times-circle'],
    ];
    
    $status_keys = array_keys($all_statuses);
    $current_status_index = array_search($order['status'], $status_keys);

    // === إعدادات العملة ===
    $currency_code = $order['currency'] ?? 'SYP';
    
    // دالة تنسيق محلية (للعرض فقط داخل هذا الملف)
    function formatMoney($amount, $currency) {
        if ($currency === 'USD') return '$' . number_format((float)$amount, 2);
        return number_format((float)$amount) . ' ل.س';
    }

} catch (PDOException $e) { 
    die("خطأ في النظام: " . $e->getMessage()); 
}
?>

<!-- المكتبات والتنسيق -->
<link rel="stylesheet" href="../css/lib/leaflet.css"/>
<link rel="stylesheet" href="../css/lib/leaflet-routing-machine.css" />
<link rel="stylesheet" href="css/admin_order_details.css">

<div class="page-header">
    <div style="display: flex; align-items: center; gap: 15px;">
        <a href="manage_all_orders.php" class="btn-back"><i class="fas fa-arrow-right"></i></a>
        <h1>تفاصيل الطلب #<?php echo htmlspecialchars($order['id']); ?></h1>
    </div>
    <button class="print-btn" onclick="window.print();">
        <i class="fas fa-print"></i> طباعة الفاتورة
    </button>
</div>

<div class="details-grid">
    
    <!-- 1. بطاقة الخريطة -->
    <div class="card" id="map-card">
        <div class="card-header"><i class="fas fa-map-marked-alt icon"></i><h3>مسار الطلب</h3></div>
        <div class="card-body">
            <div id="order-map" style="height: 350px; width: 100%; border-radius: 8px; z-index: 1;"></div>
        </div>
    </div>

    <!-- 2. بطاقة التحكم والخط الزمني -->
    <div class="card" id="actions-card">
        <div class="card-header"><i class="fas fa-sliders-h icon"></i><h3>حالة الطلب والتحكم</h3></div>
        <div class="card-body">
            <!-- Timeline -->
            <ul class="timeline">
                <?php foreach ($all_statuses as $status_key => $status_info): 
                    if ($status_key === 'canceled' && $order['status'] !== 'canceled') continue;
                    if ($order['status'] === 'canceled' && $status_key !== 'canceled') continue;
                    
                    $status_index = array_search($status_key, $status_keys);
                    $class = '';
                    if ($status_index < $current_status_index) { $class = 'completed'; } 
                    elseif ($status_index === $current_status_index) { $class = 'completed active'; }
                ?>
                <li class="timeline-item <?php echo $class; ?>">
                    <div class="timeline-icon"><i class="fas <?php echo $status_info['icon']; ?>"></i></div>
                    <div class="timeline-content"><h5><?php echo $status_info['text']; ?></h5></div>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <hr style="margin: 25px 0; border-top: 1px dashed #eee;">

            <!-- Actions Form -->
            <form id="actions-form" class="actions-form">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                
                <div class="form-group">
                    <label for="change-status-select">تغيير الحالة (للضرورة فقط)</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="new_status" id="change-status-select" class="form-control">
                            <?php foreach ($all_statuses as $key => $info): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($key === $order['status']) ? 'selected' : ''; ?>><?php echo $info['text']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-submit" onclick="submitAction('change_status')">تحديث</button>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <label for="assign-driver-select">تعيين سائق يدوياً</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="driver_id" id="assign-driver-select" class="form-control" <?php if ($order['status'] !== 'ready_for_pickup')  echo 'disabled'; ?>>
                            <option value="">-- اختر سائقاً --</option>
                            <?php foreach ($available_drivers as $driver): ?>
                                <option value="<?php echo $driver['id']; ?>"><?php echo htmlspecialchars($driver['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn-submit" onclick="submitAction('assign_driver')" <?php if ($order['status'] !== 'ready_for_pickup') echo 'disabled'; ?>>تعيين</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. بطاقة الزبون -->
    <div class="card" id="customer-card">
        <div class="card-header"><i class="fas fa-user icon"></i><h3>الزبون</h3></div>
        <div class="card-body">
            <ul class="info-list">
                <li><span class="label">الاسم:</span> <span class="value"><?php echo htmlspecialchars($order['customer_name']); ?></span></li>
                <li><span class="label">الهاتف:</span> <span class="value"><a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>"><?php echo htmlspecialchars($order['customer_phone']); ?></a></span></li>
                <li><span class="label">العنوان:</span> <span class="value"><?php echo htmlspecialchars($order['customer_address']); ?></span></li>
            </ul>
        </div>
    </div>
    
    <!-- 4. بطاقة السائق -->
    <div class="card" id="driver-card">
        <div class="card-header"><i class="fas fa-motorcycle icon"></i><h3>السائق</h3></div>
        <div class="card-body">
            <?php if ($order['driver_name']): ?>
                <ul class="info-list">
                    <li><span class="label">الاسم:</span> <span class="value"><?php echo htmlspecialchars($order['driver_name']); ?></span></li>
                    <li><span class="label">الهاتف:</span> <span class="value"><a href="tel:<?php echo htmlspecialchars($order['driver_phone']); ?>"><?php echo htmlspecialchars($order['driver_phone']); ?></a></span></li>
                    <?php if(in_array($order['status'], ['accepted', 'picked_up'])): ?>
                        <li><span class="label" style="color: green;">الحالة:</span> <span class="value">نشط حالياً</span></li>
                    <?php endif; ?>
                </ul>
            <?php else: ?>
                <p style="color: #777; text-align: center; padding: 10px;">لم يتم تعيين سائق بعد.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. بطاقة المتجر -->
    <div class="card" id="business-card">
        <div class="card-header"><i class="fas fa-store icon"></i><h3>المتجر</h3></div>
        <div class="card-body">
            <ul class="info-list">
                <li><span class="label">الاسم:</span> <span class="value"><?php echo htmlspecialchars($order['business_name']); ?></span></li>
                <li><span class="label">الهاتف:</span> <span class="value"><a href="tel:<?php echo htmlspecialchars($order['business_phone']); ?>"><?php echo htmlspecialchars($order['business_phone']); ?></a></span></li>
                <li><span class="label">العملة:</span> <span class="value badge badge-info"><?php echo $currency_code; ?></span></li>
            </ul>
        </div>
    </div>

    <!-- 6. بطاقة الفاتورة -->
    <div class="card" id="invoice-card">
        <div class="card-header"><i class="fas fa-receipt icon"></i><h3>تفاصيل الفاتورة</h3></div>
        <div class="card-body">
            <table class="invoice-table">
                <thead><tr><th>المنتج</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($item['item_name']); ?>
                            <?php if (!empty($item['special_requests'])): ?>
                                <br><small style="color: #dc3545;">(ملاحظة: <?php echo htmlspecialchars($item['special_requests']); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo formatMoney($item['price_per_item'], $currency_code); ?></td>
                        <td><?php echo formatMoney($item['price_per_item'] * $item['quantity'], $currency_code); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php 
                        // حساب المجموع الفرعي للمنتجات
                        $subtotal = 0;
                        foreach($items as $item) { $subtotal += $item['price_per_item'] * $item['quantity']; }
                        $discount = (float)$order['promo_discount'];
                        $items_after_discount = $subtotal - $discount;
                        
                        // التوصيل والإكرامية (دائماً بالليرة، لكن نعرضهم للتوضيح)
                        $delivery = (float)$order['delivery_fee'];
                        $tip = (float)$order['tip_amount'];
                        $total_db = (float)$order['total_price'];
                    ?>
                    
                    <tr><td colspan="3">المجموع الفرعي</td><td><?php echo formatMoney($subtotal, $currency_code); ?></td></tr>
                    
                    <?php if ($discount > 0): ?>
                    <tr><td colspan="3" style="color: green;">خصم (<?php echo htmlspecialchars($order['promo_code']); ?>)</td><td style="color: green;">-<?php echo formatMoney($discount, $currency_code); ?></td></tr>
                    <?php endif; ?>

                    <!-- فاصل للتوضيح -->
                    <tr><td colspan="4" style="border-top: 1px dashed #ccc;"></td></tr>

                    <!-- الخدمات (بالليرة السورية دائماً) -->
                    <tr><td colspan="3">رسوم التوصيل</td><td><?php echo number_format($delivery); ?> ل.س</td></tr>
                    <tr><td colspan="3">إكرامية</td><td><?php echo number_format($tip); ?> ل.س</td></tr>

                    <!-- الإجمالي النهائي (معالجة العرض المزدوج إذا لزم الأمر) -->
                    <tr class="final-total">
                        <td colspan="3"><strong>الإجمالي النهائي</strong></td>
                        <td>
                            <strong>
                            <?php 
                                if ($currency_code === 'USD') {
                                    // إذا كان دولار، نعرض التركيبة (دولار + ليرة) للتوضيح
                                    echo formatMoney($items_after_discount, 'USD');
                                    if ($delivery + $tip > 0) {
                                        echo ' + ' . number_format($delivery + $tip) . ' ل.س';
                                    }
                                } else {
                                    // إذا كان ليرة، نعرض المجموع الكامل
                                    echo formatMoney($total_db, 'SYP');
                                }
                            ?>
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script src="../js/lib/leaflet.js"></script>
<script src="../js/lib/leaflet-routing-machine.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderData = <?php echo json_encode($order, JSON_NUMERIC_CHECK); ?>;
    
    if (orderData.business_lat && orderData.customer_latitude) {
        const map = L.map('order-map', { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

        const shopLoc = L.latLng(orderData.business_lat, orderData.business_lon);
        const custLoc = L.latLng(orderData.customer_latitude, orderData.customer_longitude);

        const shopIcon = L.icon({iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png', iconSize: [25, 41], iconAnchor: [12, 41]});
        const custIcon = L.icon({iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png', iconSize: [25, 41], iconAnchor: [12, 41]});
        const driverIcon = L.icon({iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png', iconSize: [25, 41], iconAnchor: [12, 41]});

        const control = L.Routing.control({
            waypoints: [shopLoc, custLoc],
            createMarker: function(i, wp) {
                return L.marker(wp.latLng, {icon: (i===0)?shopIcon:custIcon}).bindPopup((i===0)?'المتجر':'الزبون');
            },
            lineOptions: {styles: [{color: '#007bff', opacity: 0.7, weight: 5}]},
            addWaypoints: false, draggableWaypoints: false, fitSelectedRoutes: true, show: false
        }).addTo(map);

        if (orderData.driver_lat && orderData.driver_lon) {
            const driverLoc = L.latLng(orderData.driver_lat, orderData.driver_lon);
            L.marker(driverLoc, {icon: driverIcon}).addTo(map).bindPopup('<b>موقع السائق الحالي</b>');
        }
    }
});

function submitAction(actionType) {
    if (!confirm('هل أنت متأكد من تنفيذ هذا الإجراء؟')) return;

    const form = document.getElementById('actions-form');
    const formData = new FormData(form);
    formData.append('action', actionType);
    
    if (actionType === 'assign_driver' && formData.get('driver_id') === '') {
        alert('الرجاء اختيار سائق من القائمة.');
        return;
    }
    
    const btns = document.querySelectorAll('.btn-submit');
    btns.forEach(btn => btn.disabled = true);

    fetch('php/update_order_admin.php', { 
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            window.location.reload();
        } else {
            btns.forEach(btn => btn.disabled = false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال بالسيرفر.');
        btns.forEach(btn => btn.disabled = false);
    });
}
</script>
<?php include 'footer.php'; ?>