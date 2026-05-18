<?php
$page_title = 'سجل الدفعات المستلمة';
// --- header.php هو المسؤول عن بدء الجلسة وتعريف الصلاحيات ---
include 'header.php';

// --- حارس البوابة: تأكد من أن المستخدم لديه صلاحية عرض المالية ---
// نستخدم صلاحية 'view_financials' لأن هذه الصفحة جزء من النظام المالي
if (!hasPermission('view_financials')) {
    echo "<h2>وصول غير مصرح به.</h2>"; include 'footer.php'; exit;
}

// --- 1. إعدادات ترقيم الصفحات (Pagination) ---
$limit = 20; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- 2. منطق الفلترة والبحث ---
$search_query = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? 'all';
$where_conditions = ["(t.transaction_type = 'payout' OR t.transaction_type = 'adjustment')"];
$params = [];
$stats_params = []; // Params منفصلة للإحصائيات

// --- 3. **فلترة الصلاحيات حسب المحافظة (باستخدام hasPermission)** ---
if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
    // شرط لجداول البيانات الرئيسية
    $where_conditions[] = "((t.user_type = 'business' AND b.governorate_id = ?) OR (t.user_type = 'driver' AND d.governorate_id = ?))";
    $params[] = $admin_governorate_id;
    $params[] = $admin_governorate_id;

    // شرط منفصل للإحصائيات (لأنها لا تحتوي على join)
    $stats_where_clause = "AND ( (user_type = 'business' AND user_id IN (SELECT id FROM businesses WHERE governorate_id = ?)) OR (user_type = 'driver' AND user_id IN (SELECT id FROM drivers WHERE governorate_id = ?)) )";
    $stats_params = [$admin_governorate_id, $admin_governorate_id];
} else {
    $stats_where_clause = "";
}

// --- 4. بقية منطق الفلترة ---
if ($filter_type !== 'all' && ($filter_type === 'business' || $filter_type === 'driver')) {
    $where_conditions[] = "t.user_type = ?";
    $params[] = $filter_type;
}
if (!empty($search_query)) {
    $where_conditions[] = "(b.name LIKE ? OR d.full_name LIKE ?)";
    $search_param = "%$search_query%";
    array_push($params, $search_param, $search_param);
}

$where_clause = "WHERE " . implode(' AND ', $where_conditions);

try {
    // --- 5. تحديث الاستعلامات لتكون آمنة وديناميكية ---
    $sql = "
        SELECT t.id, t.created_at, t.amount, t.description, t.user_type, t.user_id, t.transaction_type,
               COALESCE(b.name, d.full_name) as user_name
        FROM transactions t
        LEFT JOIN businesses b ON t.user_id = b.id AND t.user_type = 'business'
        LEFT JOIN drivers d ON t.user_id = d.id AND t.user_type = 'driver'
        $where_clause ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_sql = "SELECT COUNT(t.id) FROM transactions t LEFT JOIN businesses b ON t.user_id = b.id AND t.user_type = 'business' LEFT JOIN drivers d ON t.user_id = d.id AND t.user_type = 'driver' $where_clause";
    $total_stmt = $pdo->prepare($total_sql);
    $total_stmt->execute($params);
    $total_records = $total_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // تنفيذ استعلامات الإحصائيات الآمنة
    $today_payouts_stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE transaction_type = 'payout' AND DATE(created_at) = CURDATE() $stats_where_clause");
    $today_payouts_stmt->execute($stats_params);
    $today_payouts = $today_payouts_stmt->fetchColumn() ?? 0;

    $week_payouts_stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE transaction_type = 'payout' AND created_at >= CURDATE() - INTERVAL 7 DAY $stats_where_clause");
    $week_payouts_stmt->execute($stats_params);
    $week_payouts = $week_payouts_stmt->fetchColumn() ?? 0;

    $month_payouts_stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE transaction_type = 'payout' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) $stats_where_clause");
    $month_payouts_stmt->execute($stats_params);
    $month_payouts = $month_payouts_stmt->fetchColumn() ?? 0;
    
} catch (PDOException $e) { die("خطأ في جلب سجل الدفعات: " . $e->getMessage()); }

function format_syp_payout($number) { return number_format($number, 0, '.', ',') . ' ل.س'; }
?>
<link rel="stylesheet" href="css/admin_payout_history.css">

<div class="dashboard-header">
    <h1>سجل التسويات المالية</h1>
</div>

<!-- بطاقات إحصائية -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="label">إجمالي المستلم اليوم</div>
        <div class="value"><?php echo format_syp_payout($today_payouts); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">المستلم هذا الأسبوع</div>
        <div class="value"><?php echo format_syp_payout($week_payouts); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">المستلم هذا الشهر</div>
        <div class="value"><?php echo format_syp_payout($month_payouts); ?></div>
    </div>
</div>

<!-- شريط البحث والفلترة -->
<div class="filter-bar">
    <form action="payout_history.php" method="get" style="display: contents; flex-grow: 1;">
        <div class="filter-group">
            <label for="search-input">بحث بالاسم</label>
            <input type="text" id="search-input" name="search" placeholder="ابحث عن متجر أو سائق..." value="<?php echo htmlspecialchars($search_query); ?>">
        </div>
        <div class="filter-group">
            <label for="type-filter">فلترة حسب النوع</label>
            <select id="type-filter" name="type">
                <option value="all" <?php if($filter_type === 'all') echo 'selected'; ?>>الكل</option>
                <option value="business" <?php if($filter_type === 'business') echo 'selected'; ?>>المتاجر فقط</option>
                <option value="driver" <?php if($filter_type === 'driver') echo 'selected'; ?>>السائقين فقط</option>
            </select>
        </div>
        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> تطبيق</button>
    </form>
</div>

<!-- جدول عرض الدفعات -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th><th>التاريخ والوقت</th><th>المستفيد</th><th>النوع</th><th>الوصف</th><th>المبلغ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payouts)): ?>
                <tr><td colspan="6" style="text-align:center; padding: 40px;">لا توجد تسويات تطابق بحثك.</td></tr>
            <?php else: ?>
                <?php foreach ($payouts as $index => $payout): ?>
                    <tr>
                        <td><?php echo $offset + $index + 1; ?></td>
                        <td><?php echo date('Y-m-d H:i A', strtotime($payout['created_at'])); ?></td>
                        <td><a href="financial_profile.php?type=<?php echo $payout['user_type']; ?>&id=<?php echo $payout['user_id']; ?>"><strong><?php echo htmlspecialchars($payout['user_name'] ?? 'مستخدم محذوف'); ?></strong></a></td>
                        <td>
                            <span class="user-type-badge <?php echo $payout['user_type'] === 'business' ? 'badge-business' : 'badge-driver'; ?>">
                                <?php echo $payout['user_type'] === 'business' ? 'متجر' : 'سائق'; ?>
                            </span>
                        </td>
                        <td><em><?php echo htmlspecialchars($payout['description']); ?></em></td>
                        <td class="payout-amount" style="color: <?php echo $payout['amount'] >= 0 ? '#198754' : '#dc3545'; ?>;">
                            <?php echo ($payout['amount'] > 0 ? '+' : '') . format_syp_payout($payout['amount']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- أزرار ترقيم الصفحات -->
<div class="pagination">
    <?php if ($total_pages > 1): ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&type=<?php echo urlencode($filter_type); ?>" class="<?php if ($page == $i) echo 'active'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>