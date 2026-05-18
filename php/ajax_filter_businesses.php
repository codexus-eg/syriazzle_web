<?php
// ========================================================================
// Syriazzle - Advanced Business Filtering API (النسخة النهائية 2.0 - بيانات كاملة)
// ========================================================================
require_once 'db_connect.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    // --- 1. استقبال وتنقية بيانات الفلترة ---
    $type = $_GET['type'] ?? 'delivery';
    $category = $_GET['category'] ?? null;
    $governorate_id = isset($_GET['governorate']) ? (int)$_GET['governorate'] : null;
    $search_text = isset($_GET['search']) ? trim($_GET['search']) : null;
    $user_lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
    $user_lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
    $distance = isset($_GET['dist']) ? (float)$_GET['dist'] : 10;

    // --- 2. بناء الاستعلام الديناميكي والآمن ---
    $params = [];
    $where_conditions = [];
    
    $where_conditions[] = "b.status = 'approved'";
    $where_conditions[] = "b.deleted_at IS NULL";

    if ($type === 'delivery') {
        $where_conditions[] = "b.business_type IN ('delivery', 'hybrid')";
    } else {
        $where_conditions[] = "b.business_type IN ('booking', 'hybrid')";
    }

    if ($category) {
        $column_name = ($type === 'delivery') ? 'b.category' : 'b.booking_category';
        $where_conditions[] = "{$column_name} = ?";
        $params[] = $category;
    }

    if ($governorate_id) {
        $where_conditions[] = "b.governorate_id = ?";
        $params[] = $governorate_id;
    }

    if ($search_text) {
        $where_conditions[] = "b.name LIKE ?";
        $params[] = "%{$search_text}%";
    }
    
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
    
    // --- 3. تحديد الأعمدة المطلوبة وصيغة هافرساين ---
    // **التعديل هنا: جلب city و governorate_name**
    $select_columns = "
        b.id, 
        b.name, 
        b.logo_image, 
        b.category, 
        b.booking_category,
        b.city,
        g.name as governorate_name
    ";

    $distance_formula = "";
    if ($user_lat !== null && $user_lng !== null) {
        $distance_formula = ", ( 6371 * acos( cos( radians(?) ) * cos( radians( b.latitude ) ) * cos( radians( b.longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( b.latitude ) ) ) ) AS distance";
        array_unshift($params, $user_lat, $user_lng, $user_lat);
    }
    
    // الاستعلام الرئيسي المطور
    $sql = "
        SELECT 
            {$select_columns}
            {$distance_formula}
        FROM businesses b
        LEFT JOIN governorates g ON b.governorate_id = g.id
        {$where_clause}
    ";
    
    if ($user_lat !== null && $user_lng !== null) {
        $sql .= " HAVING distance < ?";
        $params[] = $distance;
        $sql .= " ORDER BY distance ASC";
    } else {
        $sql .= " ORDER BY b.created_at DESC";
    }

    // --- 4. تنفيذ الاستعلام وجلب النتائج ---
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $businesses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $businesses]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Filtering Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في الخادم أثناء جلب البيانات.']);
}
?>