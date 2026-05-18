<?php
// ========================================================================
// Syriazzle Mall - Ajax Category Products Loader
// ========================================================================
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = 12;

// الإعدادات المالية
$exchange_rate = 15000;
$active_discount = 0;
try {
    $stmt_rate = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    if($r = $stmt_rate->fetchColumn()) $exchange_rate = (float)$r;
    $stmt_disc = $pdo->query("SELECT discount_percentage FROM mall_discounts WHERE is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY id DESC LIMIT 1");
    if($d = $stmt_disc->fetchColumn()) $active_discount = (float)$d;
} catch (Exception $e) {}

$html = '';
$has_more = false;

try {
    $sql = "SELECT p.id, p.name AS item_name, p.image_path, p.description, 
                   p.price_usd, p.fixed_price_syp,
                   b.name AS brand_name, b.logo_path AS brand_logo
            FROM mall_products p
            LEFT JOIN mall_brands b ON p.brand_id = b.id
            WHERE p.category_id = ?
            ORDER BY p.id DESC LIMIT ? OFFSET ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $cat_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($products)) {
        if (count($products) == $limit) $has_more = true; // ربما يوجد المزيد

        foreach ($products as $prod) {
            // الحساب المالي
            $base = (!empty($prod['fixed_price_syp']) && $prod['fixed_price_syp'] > 0) ? (float)$prod['fixed_price_syp'] : (float)$prod['price_usd'] * $exchange_rate;
            $final = ($active_discount > 0) ? $base * (1 - $active_discount/100) : $base;
            
            $price_final = ceil($final / 100) * 100;
            $price_base = ceil($base / 100) * 100;
            $is_disc = ($active_discount > 0);

            // بناء HTML البطاقة (نفس تصميم mall.php)
            $html .= '<a href="mall_product_view.php?id='.$prod['id'].'" class="product-card-new">';
            
            // الصورة والشعارات
            $html .= '<div class="img-wrap">';
            $html .= '<img src="'.htmlspecialchars($prod['image_path'] ?? 'image/default.png').'" loading="lazy">';
            if(!empty($prod['brand_logo'])) {
                $html .= '<div class="brand-overlay"><img src="'.htmlspecialchars($prod['brand_logo']).'"></div>';
            }
            if($is_disc) {
                $html .= '<div class="discount-tag">خصم %'.$active_discount.'</div>';
            }
            $html .= '</div>';
            
            // المعلومات
            $html .= '<div class="info-wrap">';
            $html .= '<h4 class="title">'.htmlspecialchars($prod['item_name']).'</h4>';
            $html .= '<span class="short-desc">'.mb_strimwidth(strip_tags($prod['description'] ?? ''), 0, 40, '...').'</span>';
            $html .= '<div class="price-row">';
            $html .= '<span class="curr">'.number_format($price_final).' ل.س</span>';
            if($is_disc) {
                $html .= '<span class="old">'.number_format($price_base).'</span>';
            }
            $html .= '</div></div></a>';
        }
    }
} catch (Exception $e) {}

echo json_encode(['html' => $html, 'has_more' => $has_more]);
?>