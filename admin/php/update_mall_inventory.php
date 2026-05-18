<?php
require_once '../../php/db_connect.php';
require_once '../auth_guard.php';
header('Content-Type: application/json');

if (!hasPermission('manage_mall')) {
    echo json_encode(['success' => false, 'message' => 'وصول غير مصرح به.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['stock'])) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة.']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // تحضير الاستعلامات مرة واحدة خارج الحلقة للأداء الأفضل
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM mall_product_inventory WHERE product_id = ?");
    $stmt_update = $pdo->prepare("UPDATE mall_product_inventory SET stock_quantity = ? WHERE product_id = ?");
    $stmt_insert = $pdo->prepare("INSERT INTO mall_product_inventory (product_id, stock_quantity) VALUES (?, ?)");

    foreach ($_POST['stock'] as $product_id => $quantity) {
        $pid = (int)$product_id;
        $qty = (int)$quantity;

        // 1. تحقق مما إذا كان المنتج موجودًا بالفعل في جدول المخزون
        $stmt_check->execute([$pid]);
        $exists = $stmt_check->fetchColumn();

        if ($exists) {
            // 2. إذا كان موجودًا، قم بتحديث الكمية
            $stmt_update->execute([$qty, $pid]);
        } else {
            // 3. إذا لم يكن موجودًا، قم بإدراجه
            $stmt_insert->execute([$pid, $qty]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'تم تحديث المخزون بنجاح!']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Mall Inventory Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'فشل تحديث المخزون. حدث خطأ في قاعدة البيانات.']);
}
?>