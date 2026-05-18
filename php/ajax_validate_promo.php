<?php
// ========================================================================
// Syriazzle Mall - Promo Validator (Mall Edition - Secure)
// ========================================================================
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = trim($data['promo_code'] ?? '');
$total = floatval($data['items_total'] ?? 0);

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'أدخل الكود.']);
    exit;
}

try {
    // الشرط الأمني: الكود يجب أن يكون للمول (mall_only) أو للكل (all)
    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1 AND applicable_to IN ('all', 'mall_only')");
    $stmt->execute([$code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'كود غير صحيح أو غير مخصص للمول.']);
        exit;
    }

    if ($promo['expiry_date'] && strtotime($promo['expiry_date']) < time()) {
        echo json_encode(['success' => false, 'message' => 'انتهت صلاحية الكود.']);
        exit;
    }

    if ($promo['max_uses'] !== null && $promo['times_used'] >= $promo['max_uses']) {
        echo json_encode(['success' => false, 'message' => 'تم استهلاك الكود بالكامل.']);
        exit;
    }

    $discount = 0;
    if ($promo['discount_type'] === 'percentage') {
        $discount = $total * ($promo['discount_value'] / 100);
    } else {
        $discount = $promo['discount_value'];
    }
    $discount = min($total, $discount);

    echo json_encode([
        'success' => true,
        'message' => 'تم الخصم!',
        'data' => ['discount_amount' => round($discount, 2)]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ فني.']);
}
?>