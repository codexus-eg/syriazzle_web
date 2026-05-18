<?php
// ========================================================================
// ملف API - النسخة النهائية (مع إصلاح توافق إصدار MySQL القديم)
// ========================================================================

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../php/db_connect.php';

try {
    // 1. استقبال البيانات والتحقق منها
    $json_data = json_decode(file_get_contents('php://input'), true);
    $categoryId = filter_var($json_data['categoryId'] ?? 0, FILTER_VALIDATE_INT);
    if (!$categoryId) {
        throw new Exception("معرف الصنف مطلوب.");
    }

    $sizes = $json_data['sizes'] ?? [];
    $colors = $json_data['colors'] ?? [];
    $maxPrice = filter_var($json_data['maxPrice'] ?? 10000, FILTER_VALIDATE_FLOAT);
    $page = filter_var($json_data['page'] ?? 1, FILTER_VALIDATE_INT);
    $products_per_page = 20;
    $offset = ($page - 1) * $products_per_page;
    
    // 2. بناء الاستعلام الديناميكي بناءً على الفلاتر
    $params = [];
    $where_conditions = ["p.category_id = ?"];
    $params[] = $categoryId;
    $where_conditions[] = "p.price <= ?";
    $params[] = $maxPrice;
    
    if (!empty($sizes) && is_array($sizes)) {
        $safe_sizes = array_filter($sizes, 'is_numeric');
        if (!empty($safe_sizes)) {
            $placeholders = implode(',', array_fill(0, count($safe_sizes), '?'));
            $where_conditions[] = "p.original_handle IN (SELECT p2.original_handle FROM shn_products p2 JOIN shn_product_sizes ps ON p2.id=ps.product_id WHERE ps.size_id IN ($placeholders))";
            $params = array_merge($params, $safe_sizes);
        }
    }
     if (!empty($colors) && is_array($colors)) {
        $safe_colors = array_filter($colors, 'is_numeric');
        if(!empty($safe_colors)) {
            $placeholders = implode(',', array_fill(0, count($safe_colors), '?'));
            $where_conditions[] = "p.original_handle IN (SELECT p3.original_handle FROM shn_products p3 JOIN shn_product_colors pc ON p3.id=pc.product_id WHERE pc.color_id IN ($placeholders))";
            $params = array_merge($params, $safe_colors);
        }
    }
    
    $where_clause = "WHERE " . implode(' AND ', $where_conditions);

    // 3. استعلام جلب المنتجات المجمّعة (مع استبدال ANY_VALUE)
    // 3. *** الاستعلام الرئيسي المحدّث والمبسط ***
    // الخطوة الأولى: جلب قائمة بالمنتجات المجمعة فقط
    $base_products_sql = "
        SELECT 
            p.original_handle,
            MIN(p.id) as representative_id,
            MIN(p.name) as name,
            MIN(p.price) as price
        FROM shn_products p
        " . $where_clause . "
        GROUP BY p.original_handle
        ORDER BY representative_id DESC
        LIMIT ? OFFSET ?
    ";

    $product_params = $params;
    $product_params[] = $products_per_page;
    $product_params[] = $offset;

    $stmt_products = $pdo->prepare($base_products_sql);
    foreach($product_params as $key => $value) {
        $stmt_products->bindValue($key + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt_products->execute();
    $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

    // 4. *** جلب التفاصيل (الألوان والصور) للمنتجات التي تم العثور عليها ***
    if (!empty($products)) {
        $product_handles = array_column($products, 'original_handle');
        $placeholders = implode(',', array_fill(0, count($product_handles), '?'));

        // جلب كل تنويعات الألوان والصور للمنتجات المجمعة
        $variants_sql = "
            SELECT 
                p.original_handle,
                c.id as color_id, c.name as color_name, c.hex_code,
                (SELECT image_url FROM shn_product_images WHERE product_id = p.id ORDER BY is_main DESC, id ASC LIMIT 1) as image_url
            FROM shn_products p
            LEFT JOIN shn_product_colors pc ON p.id = pc.product_id
            LEFT JOIN shn_colors c ON pc.color_id = c.id
            WHERE p.original_handle IN ($placeholders)
        ";
        $variants_stmt = $pdo->prepare($variants_sql);
        $variants_stmt->execute($product_handles);
        $variants_data = $variants_stmt->fetchAll(PDO::FETCH_GROUP); // تجميع النتائج حسب original_handle

        // دمج التفاصيل مع قائمة المنتجات الرئيسية
        foreach ($products as $key => $product) {
            $handle = $product['original_handle'];
            $products[$key]['id'] = $product['representative_id']; // استخدام ID المنتج الممثل
            $products[$key]['name'] = preg_replace('/ - .*/', '', $product['name']); // تنظيف الاسم

            // الصورة الرئيسية هي صورة أول لون
            $products[$key]['main_image'] = $variants_data[$handle][0]['image_url'] ?? '';

            $colors_array = [];
            if (isset($variants_data[$handle])) {
                foreach ($variants_data[$handle] as $variant) {
                    $colors_array[] = [
                        'id' => $variant['color_id'],
                        'name' => $variant['color_name'],
                        'hex_code' => $variant['hex_code'],
                        'image_url' => $variant['image_url']
                    ];
                }
            }
            $products[$key]['available_colors'] = $colors_array;
        }
    }

    // 5. جلب الفلاتر (لا تغيير هنا)
    $filters = [];
    $stmt_sizes = $pdo->prepare("SELECT DISTINCT s.id, s.name FROM shn_sizes s JOIN shn_product_sizes ps ON s.id = ps.size_id JOIN shn_products p ON ps.product_id = p.id WHERE p.category_id = ? ORDER BY s.id ASC");
    $stmt_sizes->execute([$categoryId]);
    $filters['sizes'] = $stmt_sizes->fetchAll(PDO::FETCH_ASSOC);
    $stmt_colors = $pdo->prepare("SELECT DISTINCT c.id, c.name, c.hex_code FROM shn_colors c JOIN shn_product_colors pc ON c.id = pc.color_id JOIN shn_products p ON pc.product_id = p.id WHERE p.category_id = ? ORDER BY c.id ASC");
    $stmt_colors->execute([$categoryId]);
    $filters['colors'] = $stmt_colors->fetchAll(PDO::FETCH_ASSOC);

    // 6. إرجاع كل البيانات كـ JSON
    echo json_encode([
        'success' => true,
        'products' => $products,
        'filters' => $filters
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
?>