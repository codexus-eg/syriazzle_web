<?php
require_once '../auth_guard.php';
header('Content-Type: application/json');

try {
    $where_geo = '';
    $params = [];
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $where_geo = 'AND b.governorate_id = ?';
        $params[] = $admin_governorate_id;
    }

    // استعلام يجلب عمولات التوصيل والحجوزات معًا
    $sql = "
        SELECT 
            DATE(t.created_at) as date,
            SUM(CASE WHEN b.business_type IN ('delivery', 'hybrid') THEN ABS(t.amount) ELSE 0 END) as delivery_comm,
            SUM(CASE WHEN b.business_type IN ('booking', 'hybrid') THEN ABS(t.amount) ELSE 0 END) as booking_comm
        FROM transactions t
        JOIN businesses b ON t.user_id = b.id AND t.user_type = 'business'
        WHERE t.transaction_type = 'commission'
        AND t.created_at >= CURDATE() - INTERVAL 30 DAY
        {$where_geo}
        GROUP BY DATE(t.created_at)
        ORDER BY date ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // معالجة البيانات لملء الأيام الفارغة
    $labels = [];
    $delivery_data = [];
    $booking_data = [];
    $period = new DatePeriod(new DateTime('-29 days'), new DateInterval('P1D'), new DateTime('+1 day'));
    $data_map = array_column($data_raw, null, 'date');

    foreach ($period as $value) {
        $date_key = $value->format('Y-m-d');
        $labels[] = $date_key;
        $delivery_data[] = $data_map[$date_key]['delivery_comm'] ?? 0;
        $booking_data[] = $data_map[$date_key]['booking_comm'] ?? 0;
    }
    
    echo json_encode([
        'labels' => $labels,
        'delivery_commissions' => $delivery_data,
        'booking_commissions' => $booking_data
    ], JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>