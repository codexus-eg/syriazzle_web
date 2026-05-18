<?php
// ========================================================================
// Syriazzle - Chart Data API (Final Corrected Version)
// ========================================================================

require_once '../auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

// --- حارس البوابة ---
if (!hasPermission('view_financials')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

try {
    // 1. جلب سعر الصرف للتحويل
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    // تحويل القيمة لـ float لضمان الأمان عند دمجها في الاستعلام
    $usd_rate = (float)($stmt_rate->fetchColumn() ?: 15000);

    // 2. بناء شروط WHERE الديناميكية
    $where_clause = "WHERE t.transaction_type = 'commission' AND t.created_at >= CURDATE() - INTERVAL 30 DAY";
    $params = [];

    // فلترة نوع المستخدمين
    $where_clause .= " AND t.user_type IN ('business', 'driver')";

    // فلترة المحافظة (للمشرفين) - نستخدم الرموز المجهولة (?) هنا
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $where_clause .= " AND (
            (t.user_type = 'business' AND b.governorate_id = ?) OR
            (t.user_type = 'driver' AND d.governorate_id = ?)
        )";
        $params[] = $admin_governorate_id;
        $params[] = $admin_governorate_id;
    }

    // 3. الاستعلام الذكي (تم تصحيح طريقة دمج سعر الصرف)
    // دمجنا $usd_rate مباشرة في النص لأنه رقم (float) آمن، لتجنب خلط البارامترات ؟ مع :name
    $sql = "
        SELECT 
            DATE(t.created_at) as transaction_date, 
            t.user_type,
            SUM(
                CASE 
                    WHEN t.user_type = 'business' AND b.currency = 'USD' THEN ABS(t.amount) * $usd_rate
                    ELSE ABS(t.amount)
                END
            ) as total_earnings_syp
        FROM transactions t
        LEFT JOIN businesses b ON (t.user_type = 'business' AND t.user_id = b.id)
        LEFT JOIN drivers d ON (t.user_type = 'driver' AND t.user_id = d.id)
        $where_clause
        GROUP BY DATE(t.created_at), t.user_type
        ORDER BY transaction_date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params); // الآن نمرر فقط بارامترات الموقع (?)
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. تهيئة المصفوفات
    $labels = [];
    $business_data = [];
    $driver_data = [];
    
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $display_date = date('m/d', strtotime($date)); 
        $labels[] = $display_date;
        $business_data[$date] = 0;
        $driver_data[$date] = 0;
    }

    // 5. تعبئة البيانات
    foreach ($results as $row) {
        $date = $row['transaction_date'];
        $earnings = (float)$row['total_earnings_syp'];

        if (isset($business_data[$date]) && $row['user_type'] === 'business') {
            $business_data[$date] = $earnings;
        }
        if (isset($driver_data[$date]) && $row['user_type'] === 'driver') {
            $driver_data[$date] = $earnings;
        }
    }

    // إرسال البيانات
    echo json_encode([
        'labels' => $labels,
        'business_data' => array_values($business_data),
        'driver_data' => array_values($driver_data)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Chart Data Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>