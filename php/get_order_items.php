<?php
// ========================================================================
// Syriazzle - Get Order Items API (Universal & Secure)
// يستخدم من قبل: لوحة تحكم التاجر + صفحة "طلباتي" للزبون
// ========================================================================

require_once 'db_connect.php';

// تفعيل تخزين المخرجات لمنع أي مسافات بيضاء من إفساد ملف JSON
ob_start();

header('Content-Type: application/json; charset=utf-8');

// 1. التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'يجب تسجيل الدخول.']);
    ob_end_flush();
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];

// 2. التحقق من صحة رقم الطلب
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'رقم الطلب غير صالح.']);
    ob_end_flush();
    exit;
}

try {
    // 3. التحقق الأمني الصارم (Permission Check)
    // نسمح بالعرض في حالتين:
    // أ. المستخدم هو صاحب الطلب (الزبون)
    // ب. المستخدم هو صاحب المتجر (التاجر)
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM orders o 
        JOIN businesses b ON o.business_id = b.id 
        WHERE o.id = ? AND (o.user_id = ? OR b.user_id = ?)
    ");
    $stmt_check->execute([$order_id, $current_user_id, $current_user_id]);
    
    if ($stmt_check->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'غير مصرح لك بعرض تفاصيل هذا الطلب.']);
        ob_end_flush();
        exit;
    }

    // 4. جلب عناصر الطلب
    // ملاحظة: السعر هنا يعود كما خُزن وقت الطلب (بالدولار أو بالليرة)
    // التعامل مع الرمز ($ أو ل.س) يتم في الواجهة الأمامية (JavaScript)
    $stmt = $pdo->prepare("
        SELECT item_name, quantity, price_per_item, special_requests 
        FROM order_items 
        WHERE order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنظيف أي مخرجات سابقة وإرسال JSON النظيف
    ob_end_clean();
    echo json_encode($items, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    error_log("Get Items Error: " . $e->getMessage());
    echo json_encode(['error' => 'حدث خطأ في الخادم أثناء جلب التفاصيل.']);
}
exit;
?>