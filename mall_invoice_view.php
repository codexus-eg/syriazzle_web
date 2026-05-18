<?php
// ========================================================================
// Syriazzle Mall - Customer Invoice (النسخة النهائية 2.0 - تطابق الأسعار)
// ========================================================================

require_once 'php/db_connect.php';

// --- 1. التحقق من تسجيل دخول المستخدم ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id === 0) {
    die('خطأ: رقم الطلب غير محدد.');
}

try {
    // --- 2. جلب بيانات الطلب (مصدر الحقيقة للسعر) ---
    // نقوم بجلب total_price و delivery_fee و promo_discount من الجدول مباشرة
    $stmt_order = $pdo->prepare("SELECT * FROM mall_orders WHERE id = ? AND user_id = ?");
    $stmt_order->execute([$order_id, $current_user_id]);
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if(!$order) {
        die('خطأ: لا يمكنك الوصول إلى تفاصيل هذا الطلب.');
    }

    // جلب المنتجات
    $stmt_items = $pdo->prepare("SELECT * FROM mall_order_items WHERE mall_order_id = ?");
    $stmt_items->execute([$order_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) { 
    error_log("Invoice Error: " . $e->getMessage());
    die('حدث خطأ أثناء جلب بيانات الفاتورة.'); 
}

// حساب المجموع الفرعي (لأغراض العرض فقط)
$subtotal_display = 0;
foreach($items as $item) {
    $subtotal_display += $item['price_per_item'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة طلب رقم #<?php echo $order['id']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Cairo', sans-serif; 
            background-color: #f4f7f6; 
            color: #333; 
            margin: 0; 
            padding: 20px; 
            -webkit-print-color-adjust: exact; 
        }
        .invoice-box { 
            max-width: 800px; 
            margin: auto; 
            padding: 30px; 
            border: 1px solid #eee; 
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); 
            background-color: #fff; 
            border-radius: 8px;
        }
        .header { 
            text-align: center; 
            border-bottom: 2px solid #eee; 
            padding-bottom: 20px; 
            margin-bottom: 20px; 
        }
        .header h1 { margin: 0; color: #333; font-size: 2.5em; font-weight: 700; }
        .header p { margin: 5px 0 0 0; font-size: 1.2em; color: #555; }
        
        .customer-details { 
            margin-bottom: 30px; 
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .customer-details p { margin: 8px 0; font-size: 1.1em; line-height: 1.6; }
        
        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .invoice-table thead tr { background-color: #007bff; color: #fff; }
        .invoice-table th, .invoice-table td { border: 1px solid #ddd; padding: 12px; text-align: right; }
        .invoice-table th { font-weight: 700; }
        .invoice-table .item-name { font-weight: 600; }
        .invoice-table .number { font-family: 'Arial', sans-serif; direction: ltr; text-align: left; }
        
        .summary-wrapper { display: flex; justify-content: flex-end; margin-top: 20px; }
        .summary-table { width: 50%; max-width: 350px; border-collapse: collapse; }
        .summary-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .summary-table .label { font-weight: 600; color: #555; }
        .summary-table .number { font-family: 'Arial', sans-serif; direction: ltr; text-align: left; }
        
        .summary-table .total-row td { 
            border-top: 2px solid #333; 
            font-weight: 800; 
            font-size: 1.4em; 
            color: #000;
            background-color: #f8f9fa;
        }

        .print-button-container { text-align: center; margin-top: 30px; }
        .print-button { 
            padding: 12px 30px; background-color: #28a745; color: white; border: none; 
            border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: 600;
        }
        .print-button:hover { background-color: #218838; }
        
        @media print { 
            body { padding: 0; background-color: #fff; }
            .print-button-container { display: none; } 
            .invoice-box { box-shadow: none; border: none; width: 100%; max-width: 100%; padding: 0; }
        }
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
            <p><strong>تاريخ الطلب:</strong> <span class="number"><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></span></p>
            <p><strong>اسم المستلم:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
            <p><strong>رقم الهاتف:</strong> <span class="number"><?php echo htmlspecialchars($order['customer_phone']); ?></span></p>
            <p><strong>عنوان التوصيل:</strong> <?php echo htmlspecialchars($order['address_details']); ?></p>
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
        
        <div class="summary-wrapper">
            <table class="summary-table">
                <tr>
                    <td class="label">مجموع المنتجات</td>
                    <td class="number"><?php echo number_format($subtotal_display); ?> ل.س</td>
                </tr>
                <?php if ($order['promo_discount'] > 0): ?>
                <tr>
                    <td class="label">الخصم (<?php echo htmlspecialchars($order['promo_code'] ?? 'Promo'); ?>)</td>
                    <td class="number" style="color: #e74c3c;">-<?php echo number_format($order['promo_discount']); ?> ل.س</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="label">رسوم التوصيل</td>
                    <td class="number"><?php echo number_format($order['delivery_fee']); ?> ل.س</td>
                </tr>
                <tr class="total-row">
                    <td class="label">المجموع النهائي</td>
                    <!-- هنا نعرض القيمة من قاعدة البيانات مباشرة لضمان التطابق -->
                    <td class="number"><?php echo number_format($order['total_price']); ?> ل.س</td>
                </tr>
            </table>
        </div>
        <div style="clear:both;"></div>
    </div>
    
    <div class="print-button-container">
        <button class="print-button" onclick="window.print()">طباعة الفاتورة</button>
    </div>
</body>
</html>