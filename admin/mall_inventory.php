<?php
$page_title = 'إدارة مخزون المول';
require_once 'header.php';

// يمكنك إنشاء صلاحية مخصصة لاحقًا مثل 'manage_mall_inventory'
if (!hasPermission('manage_mall')) {
    echo "<h2>وصول غير مصرح به.</h2>";
    include 'footer.php';
    exit;
}

try {
    // جلب كل المنتجات مع كميات المخزون الخاصة بها
    $sql = "
        SELECT 
            p.id, p.name,
            c.name as category_name,
            inv.stock_quantity,
            inv.last_updated
        FROM mall_products p
        JOIN mall_categories c ON p.category_id = c.id
        LEFT JOIN mall_product_inventory inv ON p.id = inv.product_id
        ORDER BY c.name, p.name
    ";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("خطأ في جلب بيانات المخزون: " . $e->getMessage());
}
?>
<link rel="stylesheet" href="css/admin_dashboard.css">
<style>
    .stock-low { background-color: #fff3cd; color: #664d03; }
    .stock-out { background-color: #f8d7da; color: #58151c; font-weight: bold; }
    .stock-input { width: 80px; text-align: center; padding: 5px; border-radius: 5px; border: 1px solid #ccc; }
</style>

<div class="dashboard-header">
    <h1>إدارة مخزون منتجات المول</h1>
</div>

<div class="data-table">
    <form id="inventory-form">
    <table>
        <thead>
            <tr>
                <th>اسم المنتج</th>
                <th>الصنف</th>
                <th>الكمية الحالية في المخزون</th>
                <th>آخر تحديث</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="4" style="text-align: center;">لم يتم إضافة أي منتجات للمول بعد.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $product): 
                    $quantity = $product['stock_quantity'] ?? 0;
                    $row_class = '';
                    if ($quantity == 0) $row_class = 'stock-out';
                    elseif ($quantity <= 5) $row_class = 'stock-low';
                ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td>
                            <input type="number" name="stock[<?php echo $product['id']; ?>]" value="<?php echo $quantity; ?>" class="stock-input">
                        </td>
                        <td><?php echo $product['last_updated'] ? date('Y-m-d H:i', strtotime($product['last_updated'])) : 'لم يحدد'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <div style="text-align: left; padding: 20px;">
        <button type="submit" class="btn-submit">حفظ كل التغييرات</button>
    </div>
    </form>
</div>

<script>
document.getElementById('inventory-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const saveButton = this.querySelector('.btn-submit');
    saveButton.disabled = true;
    saveButton.textContent = 'جاري الحفظ...';

    try {
        const response = await fetch('php/update_mall_inventory.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        alert(result.message);
        if(result.success) {
            window.location.reload();
        }
    } catch(error) {
        alert('حدث خطأ في الشبكة.');
    } finally {
        saveButton.disabled = false;
        saveButton.textContent = 'حفظ كل التغييرات';
    }
});
</script>

<?php include 'footer.php'; ?>