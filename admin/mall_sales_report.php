<?php
$page_title = 'تقارير مبيعات المول';
require_once 'header.php';

if (!hasPermission('view_financials')) {
    echo "<h2>وصول غير مصرح به.</h2>";
    include 'footer.php';
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-01'); 
$end_date = $_GET['end_date'] ?? date('Y-m-t');

try {
    $sql_stats = "
        SELECT
            COUNT(id) as total_orders,
            SUM(total_price) as total_revenue,
            SUM(delivery_fee) as total_delivery_fees,
            SUM(promo_discount) as total_discounts
        FROM mall_orders
        WHERE status = 'delivered'
        AND created_at BETWEEN ? AND ?
    ";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // 2. جلب المنتجات الأكثر مبيعًا في الفترة المحددة
    $sql_top_products = "
        SELECT
            mi.product_name,
            SUM(mi.quantity) as total_sold
        FROM mall_order_items mi
        JOIN mall_orders mo ON mi.mall_order_id = mo.id
        WHERE mo.status = 'delivered'
        AND mo.created_at BETWEEN ? AND ?
        GROUP BY mi.product_name
        ORDER BY total_sold DESC
        LIMIT 10
    ";
    $stmt_top_products = $pdo->prepare($sql_top_products);
    $stmt_top_products->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $top_products = $stmt_top_products->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("خطأ في جلب بيانات التقارير: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="css/admin_dashboard.css">
<style>
    .filter-card { background-color: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 20px; align-items: center; }
    .filter-group { display: flex; flex-direction: column; gap: 5px; }
    .filter-group label { font-weight: 600; font-size: 14px; color: #555; }
    .filter-group input { padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-family: inherit; }
    .stats-grid { margin-bottom: 30px; } /* إعادة استخدام تنسيق بطاقات الإحصائيات */
    .report-section { background-color: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .report-section h2 { margin-top: 0; }
</style>

<div class="dashboard-header">
    <h1>تقارير مبيعات المول</h1>
</div>

<!-- شريط الفلترة حسب التاريخ -->
<div class="filter-card">
    <form method="GET" style="display: flex; gap: 20px; align-items: flex-end;">
        <div class="filter-group">
            <label for="start_date">من تاريخ</label>
            <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
        </div>
        <div class="filter-group">
            <label for="end_date">إلى تاريخ</label>
            <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
        </div>
        <button type="submit" class="btn-submit">تطبيق الفلتر</button>
    </form>
</div>

<!-- بطاقات الإحصائيات -->
<div class="stats-grid">
    <div class="stat-card blue"><div class="info"><div class="number"><?php echo number_format($stats['total_revenue'] ?? 0); ?> ل.س</div><div class="label">إجمالي الإيرادات</div></div></div>
    <div class="stat-card green"><div class="info"><div class="number"><?php echo (int)($stats['total_orders'] ?? 0); ?></div><div class="label">عدد الطلبات المكتملة</div></div></div>
    <div class="stat-card yellow"><div class="info"><div class="number"><?php echo number_format($stats['total_delivery_fees'] ?? 0); ?> ل.س</div><div class="label">إجمالي رسوم التوصيل</div></div></div>
    <div class="stat-card red"><div class="info"><div class="number"><?php echo number_format($stats['total_discounts'] ?? 0); ?> ل.س</div><div class="label">إجمالي الخصومات</div></div></div>
</div>

<!-- تقرير المنتجات الأكثر مبيعًا -->
<div class="report-section">
    <h2>المنتجات الأكثر مبيعًا (من <?php echo $start_date; ?> إلى <?php echo $end_date; ?>)</h2>
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>اسم المنتج</th>
                    <th>الكمية المباعة</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($top_products)): ?>
                    <tr><td colspan="3" style="text-align: center;">لا توجد مبيعات في هذه الفترة.</td></tr>
                <?php else: ?>
                    <?php foreach ($top_products as $index => $product): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><strong><?php echo $product['total_sold']; ?></strong> قطعة</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>