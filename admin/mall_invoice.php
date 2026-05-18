<?php
require_once '../php/db_connect.php';
// يمكنك إضافة حماية الأدمن هنا
// require_once 'auth_guard.php';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id === 0) die('رقم الطلب غير صحيح.');

try {
    $stmt_order = $pdo->prepare("SELECT * FROM mall_orders WHERE id = ?");
    $stmt_order->execute([$order_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    $stmt_items = $pdo->prepare("SELECT * FROM mall_order_items WHERE mall_order_id = ?");
    $stmt_items->execute([$order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    if (!$order) die('لم يتم العثور على الطلب.');

} catch(PDOException $e) { die('خطأ في جلب بيانات الفاتورة: ' . $e->getMessage()); }

$subtotal = 0;
foreach($items as $item) {
    $subtotal += $item['price_per_item'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة طلب مول Syriazzle رقم #<?php echo $order['id']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; -webkit-print-color-adjust: exact; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); background-color: #fff; }
        .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #333; font-size: 2.5em; }
        .customer-details { margin-bottom: 30px; }
        .customer-details p { margin: 5px 0; font-size: 1.1em; line-height: 1.6; }
        .invoice-table { width: 100%; border-collapse: collapse; }
        .invoice-table thead tr { background-color: #f8f8f8; }
        .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 12px; text-align: right; }
        .invoice-table th { font-weight: 700; }
        .invoice-table .item-name { font-weight: 600; }
        .invoice-table .number { font-family: 'Arial', sans-serif; direction: ltr; text-align: left; } /* للأرقام الإنجليزية */
        .summary-table { width: 40%; float: left; margin-top: 20px; }
        .summary-table td { padding: 8px; }
        .summary-table .label { font-weight: 600; }
        .summary-table .total-row td { border-top: 2px solid #333; font-weight: 700; font-size: 1.2em; }
        .print-button { display: block; width: 150px; margin: 30px auto; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; text-align: center; font-size: 16px; }
        @media print { .print-button { display: none; } }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <h1>فاتورة طلب</h1>
            <p>Syriazzle Mall</p>
        </div>
        <div class="customer-details">
            <p><strong>رقم الطلب:</strong> <span class="number">#<?php echo $order['id']; ?></span></p>
            <p><strong>التاريخ:</strong> <span class="number"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></span></p>
            <p><strong>إلى السيد/ة:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
            <p><strong>رقم الهاتف:</strong> <span class="number"><?php echo htmlspecialchars($order['customer_phone']); ?></span></p>
            <p><strong>العنوان:</strong> <?php echo htmlspecialchars($order['address_details']); ?></p>
        </div>
        <table class="invoice-table">
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
                    <td class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="number"><?php echo $item['quantity']; ?></td>
                    <td class="number"><?php echo number_format($item['price_per_item']); ?></td>
                    <td class="number"><?php echo number_format($item['price_per_item'] * $item['quantity']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <table class="summary-table">
            <tr>
                <td class="label">مجموع المنتجات</td>
                <td class="number"><?php echo number_format($subtotal); ?> ل.س</td>
            </tr>
            <?php if ($order['promo_discount'] > 0): ?>
            <tr>
                <td class="label">الخصم (<?php echo htmlspecialchars($order['promo_code']); ?>)</td>
                <td class="number">-<?php echo number_format($order['promo_discount']); ?> ل.س</td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="label">رسوم التوصيل</td>
                <td class="number"><?php echo number_format($order['delivery_fee']); ?> ل.س</td>
            </tr>
            <tr class="total-row">
                <td class="label">المجموع النهائي</td>
                <td class="number"><?php echo number_format($order['total_price']); ?> ل.س</td>
            </tr>
        </table>
        <div style="clear:both;"></div>
    </div>
    <button class="print-button" onclick="window.print()">طباعة الفاتورة</button>
</body>
</html>