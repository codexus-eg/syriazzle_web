<?php
// منع الوصول المباشر وحماية الملف
// session_start();
// if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['driver_logged_in'])) {
//     die("وصول غير مصرح به.");
// }

require_once 'db_connect.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id === 0) {
    die("خطأ: لم يتم تحديد رقم الطلب.");
}

try {
    // جلب كل بيانات الطلب اللازمة في استعلام واحد
    $stmt = $pdo->prepare("
        SELECT 
            o.id, o.customer_name, o.customer_phone, o.customer_address, 
            o.total_price, o.delivery_fee, o.created_at,
            b.name as business_name, b.logo_image
        FROM orders o
        JOIN businesses b ON o.business_id = b.id
        WHERE o.id = ? AND o.business_id = 1 -- التأكد من أنه طلب من المول فقط
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die("خطأ: الطلب غير موجود أو لا يتبع لمول Syriazzle.");
    }

    // جلب المنتجات الموجودة في الطلب
    $stmt_items = $pdo->prepare("SELECT item_name, quantity, price_per_item FROM order_items WHERE order_id = ?");
    $stmt_items->execute([$order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

$subtotal = $order['total_price'] - $order['delivery_fee'];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة الطلب رقم #<?php echo $order['id']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #f4f7fc; margin: 0; padding: 20px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); font-size: 16px; line-height: 24px; color: #555; background-color: #fff; }
        .invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
        .invoice-header .logo { max-width: 150px; }
        .invoice-header .invoice-details { text-align: left; }
        .invoice-details strong { display: block; }
        .customer-details { margin-bottom: 40px; }
        .items-table { width: 100%; line-height: inherit; text-align: right; border-collapse: collapse; }
        .items-table th { background: #eee; border-bottom: 2px solid #ddd; font-weight: bold; padding: 8px; }
        .items-table td { padding: 8px; border-bottom: 1px solid #eee; }
        .items-table .total-row td { border-bottom: none; border-top: 2px solid #eee; font-weight: bold; }
        .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #777; }
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .invoice-box { box-shadow: none; border: none; margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="invoice-header">
            <div class="brand-info">
                <img src="../<?php echo htmlspecialchars($order['logo_image'] ?? 'image/logo1.png'); ?>" alt="شعار المنصة" class="logo">
                <h3><?php echo htmlspecialchars($order['business_name']); ?></h3>
            </div>
            <div class="invoice-details">
                <strong>فاتورة رقم: #<?php echo $order['id']; ?></strong>
                تاريخ الطلب: <?php echo date('Y-m-d', strtotime($order['created_at'])); ?>
            </div>
        </div>

        <div class="customer-details">
            <h4>فاتورة إلى:</h4>
            <p>
                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                <?php echo htmlspecialchars($order['customer_address']); ?><br>
                <?php echo htmlspecialchars($order['customer_phone']); ?>
            </p>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>الكمية</th>
                    <th>سعر الوحدة</th>
                    <th>الإجمالي الجزئي</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo (int)$item['price_per_item']; ?> ل.س</td>
                    <td><?php echo (int)($item['quantity'] * $item['price_per_item']); ?> ل.س</td>
                </tr>
                <?php endforeach; ?>
                
                <tr class="total-row">
                    <td colspan="3">المجموع الفرعي للمنتجات</td>
                    <td><?php echo (int)$subtotal; ?> ل.س</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3">رسوم التوصيل</td>
                    <td><?php echo (int)$order['delivery_fee']; ?> ل.س</td>
                </tr>
                <tr class="total-row" style="font-size: 1.2em;">
                    <td colspan="3"><strong>المبلغ الإجمالي للدفع</strong></td>
                    <td><strong><?php echo (int)$order['total_price']; ?> ل.س</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            شكراً لتسوقكم من مول Syriazzle.
        </div>
    </div>
</body>
</html>