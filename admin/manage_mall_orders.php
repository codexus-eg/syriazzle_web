<?php
// ========================================================================
// Syriazzle Admin - Manage All Marketplace Orders (Final Version)
// ========================================================================

$page_title = 'إدارة كل الطلبات';
// header.php يبدأ الجلسة ويتحقق من تسجيل الدخول الأساسي
include 'header.php'; 

// --- حارس البوابة: التحقق من صلاحية عرض الطلبات ---
if (!hasPermission('view_all_orders')) {
    echo "<div style='text-align:center; margin-top:50px;'>
            <h2 style='color:#dc3545;'>وصول غير مصرح به</h2>
            <p>ليس لديك الصلاحية للوصول إلى هذه الصفحة.</p>
          </div>"; 
    include 'footer.php'; 
    exit;
}

// --- 1. إعداد متغيرات الفلترة والبحث ---
$filter_status = $_GET['status'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');

$where_conditions = [];
$params = [];

// --- 2. فلترة الصلاحيات الجغرافية (للمشرفين غير العموميين) ---
// إذا لم يكن سوبر أدمن، نعرض له فقط طلبات المتاجر التي في محافظته
if (!hasPermission('super_admin_access_all') && isset($admin_governorate_id)) {
    $where_conditions[] = 'b.governorate_id = ?';
    $params[] = $admin_governorate_id;
}

// --- 3. منطق فلترة الحالات ---
if ($filter_status === 'active') {
    // الطلبات النشطة تشمل كل المراحل قبل الوصول النهائي أو الإلغاء
    // هام: تمت إضافة 'accepted' هنا لكي لا تختفي الطلبات التي قبلها السائق
    $where_conditions[] = "o.status IN ('pending_approval', 'preparing', 'ready_for_pickup', 'accepted', 'picked_up')";
} elseif ($filter_status === 'completed') {
    $where_conditions[] = "o.status = 'delivered'";
} elseif ($filter_status === 'canceled') {
    $where_conditions[] = "o.status = 'canceled'";
}

// --- 4. منطق البحث ---
if (!empty($search_query)) {
    if (is_numeric($search_query)) {
        // بحث برقم الطلب المباشر
        $where_conditions[] = "o.id = ?";
        $params[] = (int)$search_query;
    } else {
        // بحث بالنص (اسم الزبون، السائق، أو المتجر)
        $where_conditions[] = "(u.username LIKE ? OR d.full_name LIKE ? OR b.name LIKE ?)";
        $search_term = "%$search_query%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
}

// تجميع شروط WHERE
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
}

try {
    // --- 5. الاستعلام الرئيسي ---
    $sql = "
        SELECT 
            o.id, o.status, o.total_price, o.created_at,
            b.name as business_name, 
            u.username as customer_name, 
            d.full_name as driver_name
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN drivers d ON o.driver_id = d.id
        $where_clause
        ORDER BY o.created_at DESC
        LIMIT 100
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // مصفوفة ترجمة الحالات للعربية
    $status_translations = [
        'pending_approval' => 'بانتظار الموافقة',
        'preparing'        => 'قيد التحضير',
        'ready_for_pickup' => 'جاهز للاستلام',
        'accepted'         => 'السائق قادم', // الحالة الجديدة
        'picked_up'        => 'مع السائق',
        'delivered'        => 'تم التوصيل',
        'canceled'         => 'ملغي',
    ];

} catch (PDOException $e) {
    die("<div class='alert alert-danger'>خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<!-- تنسيقات CSS مدمجة للصفحة -->
<style>
    .dashboard-header { display: flex; flex-direction: column; gap: 20px; margin-bottom: 25px; }
    
    /* الشريط العلوي (العنوان والبحث) */
    .header-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
    .search-form { display: flex; gap: 10px; flex-grow: 1; max-width: 500px; }
    .search-input { 
        flex-grow: 1; padding: 12px 15px; border: 1px solid #ced4da; 
        border-radius: 50px; font-family: inherit; transition: 0.3s;
    }
    .search-input:focus { border-color: #0d6efd; outline: none; box-shadow: 0 0 0 4px rgba(13,110,253,0.1); }
    .search-btn { 
        padding: 0 25px; border: none; background-color: #0d6efd; color: #fff; 
        border-radius: 50px; cursor: pointer; transition: 0.3s;
    }
    .search-btn:hover { background-color: #0b5ed7; }

    /* تبويبات الفلترة */
    .filter-tabs { display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; }
    .filter-tabs::-webkit-scrollbar { height: 4px; }
    .filter-tabs::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
    
    .filter-link { 
        padding: 10px 25px; text-decoration: none; color: #555; font-weight: 700; 
        border-radius: 30px; background-color: #fff; border: 1px solid #e9ecef; 
        white-space: nowrap; transition: all 0.2s; font-size: 0.95rem;
    }
    .filter-link:hover { background-color: #f8f9fa; }
    .filter-link.active { 
        background-color: #0d6efd; color: #fff; border-color: #0d6efd; 
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.3); 
    }

    /* الجدول */
    .data-table { 
        width: 100%; border-collapse: collapse; background-color: #fff; 
        box-shadow: 0 5px 20px rgba(0,0,0,0.05); border-radius: 12px; overflow: hidden; 
    }
    .data-table th { background-color: #f8f9fa; color: #495057; font-weight: 700; padding: 18px 15px; text-align: right; }
    .data-table td { padding: 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; color: #333; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background-color: #fcfcfc; }

    /* شارات الحالة */
    .status-badge {
        padding: 6px 15px; border-radius: 20px; font-weight: 700; font-size: 0.85rem;
        color: #fff; display: inline-block; min-width: 100px; text-align: center;
    }
    .status-delivered { background-color: #198754; } /* أخضر */
    .status-canceled { background-color: #dc3545; }  /* أحمر */
    /* الحالات النشطة (أصفر/برتقالي/أزرق) */
    .status-active { background-color: #fd7e14; }   
    .status-default { background-color: #6c757d; }

    /* أزرار الإجراءات */
    .btn-details {
        color: #0d6efd; text-decoration: none; font-weight: 700; 
        background: #e7f1ff; padding: 6px 15px; border-radius: 6px; 
        transition: 0.2s; display: inline-block;
    }
    .btn-details:hover { background: #0d6efd; color: #fff; }
</style>

<div class="dashboard-header">
    <div class="header-top">
        <h1>إدارة كل الطلبات</h1>
        <form action="manage_all_orders.php" method="get" class="search-form">
            <input type="text" name="search" class="search-input" placeholder="رقم الطلب، الزبون، السائق..." value="<?php echo htmlspecialchars($search_query); ?>">
            <!-- الحفاظ على الفلتر الحالي عند البحث -->
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
        </form>
    </div>
    
    <div class="filter-tabs">
        <a href="manage_all_orders.php?status=all" class="filter-link <?php echo $filter_status === 'all' ? 'active' : ''; ?>">الكل</a>
        <a href="manage_all_orders.php?status=active" class="filter-link <?php echo $filter_status === 'active' ? 'active' : ''; ?>">النشطة</a>
        <a href="manage_all_orders.php?status=completed" class="filter-link <?php echo $filter_status === 'completed' ? 'active' : ''; ?>">المكتملة</a>
        <a href="manage_all_orders.php?status=canceled" class="filter-link <?php echo $filter_status === 'canceled' ? 'active' : ''; ?>">الملغاة</a>
    </div>
</div>

<div style="overflow-x: auto;">
    <table class="data-table">
        <thead>
            <tr>
                <th>رقم الطلب</th>
                <th>المتجر</th>
                <th>الزبون</th>
                <th>السائق</th>
                <th>التاريخ</th>
                <th>الإجمالي</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 50px; color: #777;">
                        <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 10px; display: block;"></i>
                        لا توجد طلبات مطابقة للبحث أو الفلتر.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong>#<?php echo htmlspecialchars($order['id']); ?></strong></td>
                    <td><?php echo htmlspecialchars($order['business_name']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($order['customer_name'] ?? '---'); ?>
                    </td>
                    <td>
                        <?php if ($order['driver_name']): ?>
                            <i class="fas fa-motorcycle" style="color:#666"></i> <?php echo htmlspecialchars($order['driver_name']); ?>
                        <?php else: ?>
                            <span style="color:#999;">--</span>
                        <?php endif; ?>
                    </td>
                    <td style="direction: ltr; text-align: right;">
                        <?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?>
                    </td>
                    <td><strong><?php echo number_format($order['total_price']); ?></strong> ل.س</td>
                    <td>
                        <?php
                            // تحديد لون الشارة
                            $status_class = 'status-default';
                            if ($order['status'] === 'delivered') {
                                $status_class = 'status-delivered';
                            } elseif ($order['status'] === 'canceled') {
                                $status_class = 'status-canceled';
                            } elseif (in_array($order['status'], ['pending_approval', 'preparing', 'ready_for_pickup', 'accepted', 'picked_up'])) {
                                $status_class = 'status-active';
                            }
                            
                            // النص العربي
                            $status_text = $status_translations[$order['status']] ?? $order['status'];
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                    </td>
                    <td class="actions">
                        <?php if (hasPermission('edit_order')): ?>
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-details">
                                <i class="fas fa-eye"></i> عرض
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>