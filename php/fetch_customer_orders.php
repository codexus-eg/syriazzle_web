<?php
// ========================================================================
// Syriazzle - Unified Orders Fetcher (Multi-Currency Support - Final)
// ========================================================================
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$status_type = $_GET['status'] ?? 'active';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = 5; 
$user_id = (int)$_SESSION['user_id'];

// 1. تحديد الحالات لكل نوع (لأن المول والمتاجر قد تختلف قليلاً)
$market_statuses = [];
$mall_statuses = [];

if ($status_type === 'active') {
    // المتاجر: تشمل كل حالات التحضير والتوصيل
    $market_statuses = ['pending_approval', 'preparing', 'ready_for_pickup', 'accepted', 'picked_up'];
    // المول: يشمل التحضير والتوصيل
    $mall_statuses = ['pending_approval', 'preparing', 'out_for_delivery'];
} elseif ($status_type === 'completed') {
    $market_statuses = ['delivered'];
    $mall_statuses = ['delivered'];
} elseif ($status_type === 'canceled') {
    $market_statuses = ['canceled'];
    $mall_statuses = ['canceled'];
}

try {
    $all_orders = [];

    // --- أ. جلب طلبات المتاجر (Marketplace) ---
    if (!empty($market_statuses)) {
        $placeholders = implode(',', array_fill(0, count($market_statuses), '?'));
        
        // جلب العملة (currency) من جدول الطلبات لضمان الدقة التاريخية
        $sql = "SELECT 
                    o.id, o.total_price, o.status, o.created_at, o.business_id, o.currency,
                    'market' as type,
                    b.name as store_name, b.logo_image as store_logo
                FROM orders o
                JOIN businesses b ON o.business_id = b.id
                WHERE o.user_id = ? AND o.status IN ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$user_id], $market_statuses));
        $all_orders = array_merge($all_orders, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // --- ب. جلب طلبات المول (Mall) ---
    if (!empty($mall_statuses)) {
        $placeholders = implode(',', array_fill(0, count($mall_statuses), '?'));
        
        // المول يعمل افتراضياً بالليرة السورية، لذا نرسل 'SYP' كقيمة ثابتة للعملة
        // ونضع 0 كـ business_id واسم ثابت للمول
        $sql = "SELECT 
                    m.id, m.total_price, m.status, m.created_at, 0 as business_id, 'SYP' as currency,
                    'mall' as type,
                    'Syriazzle Mall' as store_name, 'image/mall_logo.png' as store_logo
                FROM mall_orders m
                WHERE m.user_id = ? AND m.status IN ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$user_id], $mall_statuses));
        $all_orders = array_merge($all_orders, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // --- ج. الترتيب والتقطيع (Sorting & Pagination) ---
    // ترتيب المصفوفة المدمجة حسب التاريخ (الأحدث أولاً)
    usort($all_orders, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // حساب الإجمالي للتحقق من وجود المزيد
    $total_count = count($all_orders);
    
    // اقتطاع الجزء المطلوب للصفحة الحالية
    $paged_orders = array_slice($all_orders, $offset, $limit);

    // --- د. التنسيق النهائي للبيانات ---
    $formatted = [];
    foreach($paged_orders as $o) {
        $formatted[] = [
            'id' => $o['id'],
            'type' => $o['type'], // 'market' or 'mall'
            'business_id' => $o['business_id'],
            'total' => $o['total_price'],
            'currency' => $o['currency'] ?? 'SYP', // إرسال العملة للواجهة الأمامية
            'status' => $o['status'],
            'date' => date('Y/m/d h:i A', strtotime($o['created_at'])),
            'store_name' => $o['store_name'],
            'store_logo' => $o['store_logo']
        ];
    }

    $has_more = ($offset + $limit) < $total_count;

    echo json_encode([
        'success' => true, 
        'data' => $formatted, 
        'has_more' => $has_more
    ]);

} catch (Exception $e) {
    // تسجيل الخطأ وإرسال رد فشل
    error_log("Fetch Orders Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>