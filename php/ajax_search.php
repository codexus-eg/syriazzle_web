<?php
require_once 'db_connect.php';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($term)) { echo ''; exit; }

// إعدادات العملة والخصم
$exchange_rate = 15000;
$active_discount = 0;
try {
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'usd_to_syp_rate'");
    if($r = $stmt->fetchColumn()) $exchange_rate = (float)$r;
    $stmt = $pdo->query("SELECT discount_percentage FROM mall_discounts WHERE is_active=1 LIMIT 1");
    if($d = $stmt->fetchColumn()) $active_discount = (float)$d;
} catch (Exception $e) {}

// البحث في الاسم، الوصف، والماركة
$sql = "SELECT p.*, b.name as brand_name, b.logo_path as brand_logo 
        FROM mall_products p 
        LEFT JOIN mall_brands b ON p.brand_id = b.id
        WHERE p.name LIKE ? OR p.description LIKE ? OR b.name LIKE ?
        ORDER BY p.id DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$s = "%$term%";
$stmt->execute([$s, $s, $s]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo '<div style="text-align:center;padding:50px;color:#777;">لا توجد منتجات تطابق بحثك</div>';
} else {
    echo '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap:15px; padding:10px;">';
    foreach ($results as $prod) {
        // حساب السعر
        $base = (!empty($prod['fixed_price_syp'])) ? $prod['fixed_price_syp'] : $prod['price_usd'] * $exchange_rate;
        $final = ($active_discount > 0) ? $base * (1 - $active_discount/100) : $base;
        
        echo '<a href="mall_product_view.php?id='.$prod['id'].'" class="product-card-new" style="display:block;">';
        echo '<div class="img-wrap"><img src="'.($prod['image_path'] ?? 'image/default.png').'">';
        if($active_discount > 0) echo '<div class="discount-tag">خصم %'.$active_discount.'</div>';
        echo '</div>';
        echo '<div class="info-wrap"><h4 class="title">'.$prod['name'].'</h4>';
        echo '<div class="price-row"><span class="curr">'.number_format($final).' ل.س</span></div>';
        echo '</div></a>';
    }
    echo '</div>';
}
?>