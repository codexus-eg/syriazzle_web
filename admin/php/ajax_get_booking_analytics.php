<?php
// ========================================================================
// Syriazzle Admin API - محرك التحليلات لنظام الحجوزات (النسخة النهائية 2.0 - الدقيقة)
// ========================================================================
require_once '../auth_guard.php';

header('Content-Type: application/json; charset=UTF-8');

// --- حارس الصلاحيات ---
if (!hasPermission('view_booking_analytics')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'وصول غير مصرح به.']);
    exit;
}

try {
    // --- إعدادات الفلترة الجغرافية (تطبق على كل الاستعلامات) ---
    $where_clause_for_bookings = '';
    $where_clause_for_transactions = '';
    $params = [];
    if (!$is_super_admin && isset($_SESSION['admin_governorate_id'])) {
        $where_clause_for_bookings = 'WHERE biz.governorate_id = ?';
        $where_clause_for_transactions = 'WHERE b.governorate_id = ?';
        $params[] = $_SESSION['admin_governorate_id'];
    }

    // --- 1. مؤشرات الأداء الرئيسية (KPIs) - حسابات دقيقة ---
    $kpi_sql = "
        SELECT
            -- كل الحجوزات التي تم إنشاؤها
            COUNT(b.id) as total_bookings,
            -- فقط الحجوزات التي حالتها confirmed
            SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            -- فقط الحجوزات التي حالتها pending_confirmation
            SUM(CASE WHEN b.status = 'pending_confirmation' THEN 1 ELSE 0 END) as pending_bookings,
            -- فقط الحجوزات الملغاة (بكل أنواعها)
            SUM(CASE WHEN b.status LIKE 'cancelled_%' THEN 1 ELSE 0 END) as cancelled_bookings,
            -- الإيرادات تحسب من الحجوزات المؤكدة فقط
            SUM(CASE WHEN b.status = 'confirmed' THEN b.total_price ELSE 0 END) as total_revenue
        FROM bookings b
        LEFT JOIN business_services s ON b.service_id = s.id
        LEFT JOIN businesses biz ON s.business_id = biz.id
        {$where_clause_for_bookings}
    ";
    $kpi_stmt = $pdo->prepare($kpi_sql);
    $kpi_stmt->execute($params);
    $kpis = $kpi_stmt->fetch(PDO::FETCH_ASSOC);

    // حساب إجمالي العمولات بشكل منفصل ودقيق من جدول transactions
    $commissions_sql = "
        SELECT SUM(t.amount) as total_commissions
        FROM transactions t
        JOIN businesses b ON t.user_id = b.id AND t.user_type = 'business'
        {$where_clause_for_transactions}
        AND t.transaction_type = 'commission'
        AND t.order_id IS NOT NULL AND t.order_id IN (SELECT id FROM bookings)
    ";
    $commissions_stmt = $pdo->prepare($commissions_sql);
    $commissions_stmt->execute($params);
    // العمولات مسجلة كقيم سالبة، لذا نستخدم abs() لعرضها كرقم موجب
    $kpis['total_commissions'] = abs($commissions_stmt->fetchColumn() ?: 0);


    // --- 2. بيانات الرسم البياني (آخر 30 يومًا) ---
    $chart_where_bookings = $where_clause_for_bookings ? $where_clause_for_bookings . " AND b.created_at >= CURDATE() - INTERVAL 30 DAY" : "WHERE b.created_at >= CURDATE() - INTERVAL 30 DAY";
    $chart_sql = "
        SELECT
            DATE(b.created_at) as date,
            COUNT(b.id) as count
        FROM bookings b
        LEFT JOIN business_services s ON b.service_id = s.id
        LEFT JOIN businesses biz ON s.business_id = biz.id
        {$chart_where_bookings}
        GROUP BY DATE(b.created_at)
        ORDER BY date ASC
    ";
    $chart_stmt = $pdo->prepare($chart_sql);
    $chart_stmt->execute($params);
    $chart_data_raw = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

    // معالجة بيانات الرسم البياني لملء الأيام الفارغة بالصفر
    $dates = [];
    $counts = [];
    $period = new DatePeriod(new DateTime('-29 days'), new DateInterval('P1D'), new DateTime('+1 day'));
    $data_map = array_column($chart_data_raw, 'count', 'date');
    foreach ($period as $value) {
        $date_key = $value->format('Y-m-d');
        $dates[] = $date_key;
        $counts[] = $data_map[$date_key] ?? 0;
    }
    
    // --- 3. قوائم الأفضل أداءً (Top 5) - للحجوزات المؤكدة فقط ---
    $top_where_bookings = $where_clause_for_bookings ? $where_clause_for_bookings . " AND b.status = 'confirmed'" : "WHERE b.status = 'confirmed'";
    // أفضل الأنشطة حسب الإيرادات
    $top_revenue_sql = "
        SELECT biz.name, SUM(b.total_price) as revenue
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        JOIN businesses biz ON s.business_id = biz.id
        {$top_where_bookings}
        GROUP BY biz.id, biz.name
        ORDER BY revenue DESC
        LIMIT 5
    ";
    $top_revenue_stmt = $pdo->prepare($top_revenue_sql);
    $top_revenue_stmt->execute($params);
    $top_by_revenue = $top_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

    // أفضل الأنشطة حسب عدد الحجوزات
    $top_bookings_sql = "
        SELECT biz.name, COUNT(b.id) as bookings_count
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        JOIN businesses biz ON s.business_id = biz.id
        {$top_where_bookings}
        GROUP BY biz.id, biz.name
        ORDER BY bookings_count DESC
        LIMIT 5
    ";
    $top_bookings_stmt = $pdo->prepare($top_bookings_sql);
    $top_bookings_stmt->execute($params);
    $top_by_bookings = $top_bookings_stmt->fetchAll(PDO::FETCH_ASSOC);


    // --- تجميع كل البيانات في رد واحد ---
    $response = [
        'success' => true,
        'kpis' => $kpis,
        'chart' => [
            'labels' => $dates,
            'data' => $counts,
        ],
        'top_lists' => [
            'by_revenue' => $top_by_revenue,
            'by_bookings' => $top_by_bookings
        ]
    ];

    echo json_encode($response, JSON_NUMERIC_CHECK); // JSON_NUMERIC_CHECK يضمن أن الأرقام تبقى أرقاماً

} catch (Exception $e) {
    error_log("Booking Analytics Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء جلب البيانات التحليلية.']);
}
?>