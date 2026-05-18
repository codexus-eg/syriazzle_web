<?php
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = 5; 

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
    $sql_cats = "SELECT c.id, c.name, c.department_id, d.name as dept_name 
                 FROM mall_categories c
                 LEFT JOIN mall_departments d ON c.department_id = d.id
                 ORDER BY d.id ASC, c.id ASC 
                 LIMIT ? OFFSET ?";
                 
    $stmt = $pdo->prepare($sql_cats);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cat_ids = array_column($categories, 'id');

    if (!empty($cat_ids)) {
        $has_more = true;
        $placeholders = implode(',', array_fill(0, count($cat_ids), '?'));
        
        $sql = "SELECT p.id, p.name AS item_name, p.image_path, p.description, p.price_usd, p.fixed_price_syp,
                       c.name AS category_name, c.id AS category_id, 
                       d.name AS department_name, d.id AS dept_id,
                       b.name AS brand_name, b.logo_path AS brand_logo
                FROM mall_products p
                LEFT JOIN mall_categories c ON p.category_id = c.id
                LEFT JOIN mall_departments d ON c.department_id = d.id
                LEFT JOIN mall_brands b ON p.brand_id = b.id
                WHERE c.id IN ($placeholders) 
                ORDER BY d.id ASC, c.id ASC, p.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($cat_ids);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $structured = [];
        $counters = [];

        foreach ($items as $item) {
            $dept = $item['department_name'];
            $cat = $item['category_name'];
            $cat_id = $item['category_id'];

            if (!isset($counters[$cat_id])) $counters[$cat_id] = 0;
            if ($counters[$cat_id] >= 5) continue; 

            $base = (!empty($item['fixed_price_syp']) && $item['fixed_price_syp'] > 0) ? (float)$item['fixed_price_syp'] : (float)$item['price_usd'] * $exchange_rate;
            $final = ($active_discount > 0) ? $base * (1 - $active_discount/100) : $base;
            
            $item['price_final'] = ceil($final / 100) * 100;
            $item['price_base'] = ceil($base / 100) * 100;
            $item['is_disc'] = ($active_discount > 0);
            
            $structured[$dept]['categories'][$cat]['id'] = $cat_id;
            $structured[$dept]['categories'][$cat]['items'][] = $item;
            $counters[$cat_id]++;
        }

        foreach ($structured as $dept => $dept_data) {
            $html .= '<section class="dept-section">';
            $html .= '<h2 class="dept-title">'.htmlspecialchars($dept).'</h2>';
            
            // --- إضافة شريط الفلترة هنا أيضاً ---
            if (count($dept_data['categories']) > 1) {
                $html .= '<div class="filters-scroll-wrapper">';
                $html .= '<button class="filter-chip active" data-filter="all">الكل</button>';
                foreach ($dept_data['categories'] as $cat_name => $cat_data) {
                    $html .= '<button class="filter-chip" data-filter="'.$cat_data['id'].'">'.htmlspecialchars($cat_name).'</button>';
                }
                $html .= '</div>';
            }
            // -----------------------------------

            foreach ($dept_data['categories'] as $cat_name => $cat_data) {
                if(empty($cat_data['items'])) continue;
                
                $html .= '<div class="cat-container" data-cat-id="'.$cat_data['id'].'">';
                $html .= '<div class="cat-header">';
                $html .= '<h3>'.htmlspecialchars($cat_name).'</h3>';
                $html .= '<a href="mall_category.php?id='.$cat_data['id'].'" class="see-all">المزيد <i class="fas fa-angle-left"></i></a>';
                $html .= '</div>';
                
                $html .= '<div class="products-scroll">';
                foreach ($cat_data['items'] as $prod) {
                    $html .= '<a href="mall_product_view.php?id='.$prod['id'].'" class="product-card-new">';
                    $html .= '<div class="img-wrap"><img src="'.htmlspecialchars($prod['image_path'] ?? 'image/default.png').'" loading="lazy">';
                    if(!empty($prod['brand_logo'])) $html .= '<div class="brand-overlay"><img src="'.htmlspecialchars($prod['brand_logo']).'"></div>';
                    if($prod['is_disc']) $html .= '<div class="discount-tag">خصم %'.$active_discount.'</div>';
                    $html .= '</div>';
                    $html .= '<div class="info-wrap"><h4 class="title">'.htmlspecialchars($prod['item_name']).'</h4>';
                    $html .= '<span class="short-desc">'.mb_strimwidth(strip_tags($prod['description'] ?? ''), 0, 40, '...').'</span>';
                    $html .= '<div class="price-row"><span class="curr">'.number_format($prod['price_final']).' ل.س</span>';
                    if($prod['is_disc']) $html .= '<span class="old">'.number_format($prod['price_base']).'</span>';
                    $html .= '</div></div></a>';
                }
                $html .= '</div></div>';
            }
            $html .= '</section>';
        }
    }
} catch (Exception $e) {}

echo json_encode(['html' => $html, 'has_more' => $has_more]);
?>