<?php

require_once 'php/db_connect.php';



// =========================================================================

// 1. استقبال البيانات والتحقق الأمني

// =========================================================================

$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($business_id === 0) {

    die("خطأ: لم يتم تحديد النشاط التجاري.");

}

$current_user_id = $_SESSION['user_id'] ?? null;

$isUserLoggedIn = isset($current_user_id);



// =========================================================================

// 2. جلب كل البيانات المتعلقة بالمتجر فقط

// =========================================================================

try {

    // جلب البيانات الأساسية للمتجر (بما في ذلك الفيديو والعملة)

    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ? AND status = 'approved'");

    $stmt->execute([$business_id]);

    $business = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$business) {

        die("هذا النشاط التجاري غير موجود أو قيد المراجعة حالياً.");

    }



    // === تعديل: تحديد رمز العملة ديناميكياً ===

    $currency_code = $business['currency'] ?? 'SYP'; // القيمة الافتراضية

    $currency_symbol = ($currency_code === 'USD') ? '$' : 'ل.س';

    // ==========================================



    $deals_stmt = $pdo->prepare(

        "SELECT id, category_name, deal_name, description, old_price, new_price, image_path 

         FROM business_deals 

         WHERE business_id = ? AND is_active = 1 

         AND (end_date IS NULL OR end_date >= CURDATE())

         ORDER BY category_name, deal_name"

    );

    $deals_stmt->execute([$business_id]);

    $deals = $deals_stmt->fetchAll(PDO::FETCH_ASSOC);

    $categorized_deals = [];

    foreach ($deals as $deal) {

        $category = !empty($deal['category_name']) ? $deal['category_name'] : 'عروض عامة';

        $categorized_deals[$category][] = $deal;

    }

    // جلب التفاصيل الإضافية

    $stmt_details = $pdo->prepare("SELECT detail_key, detail_value FROM business_details WHERE business_id = ?");

    $stmt_details->execute([$business_id]);

    $details = $stmt_details->fetchAll(PDO::FETCH_KEY_PAIR);



    // جلب صور المعرض

    $stmt_gallery = $pdo->prepare("SELECT image_path FROM business_gallery WHERE business_id = ?");

    $stmt_gallery->execute([$business_id]);

    $gallery_images = $stmt_gallery->fetchAll(PDO::FETCH_COLUMN);

    $all_images_for_lightbox = array_values(array_unique(array_filter(array_merge([$business['cover_image']], [$business['logo_image']], $gallery_images))));



    $stmt_offers = $pdo->prepare("SELECT image_path, title, link_url FROM business_offers WHERE business_id = ? ORDER BY display_order ASC");

    $stmt_offers->execute([$business_id]);

    $offer_images = $stmt_offers->fetchAll(PDO::FETCH_ASSOC);



    // جلب قائمة الأسعار وتصنيفها

    $stmt_menu = $pdo->prepare("SELECT id, category_name, item_name, description, price, image_path FROM business_menu_items WHERE business_id = ? ORDER BY category_name, item_name");

    $stmt_menu->execute([$business_id]);

    $menu_items = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);

    $categorized_menu = [];

    foreach ($menu_items as $item) {

        $category = !empty($item['category_name']) ? $item['category_name'] : 'متفرقات';

        $categorized_menu[$category][] = $item;

    }



    // جلب المراجعات مع الردود

    $stmt_reviews = $pdo->prepare("SELECT r.rating, r.review_text, r.created_at, r.reply_text, r.replied_at, u.username FROM business_reviews r JOIN users u ON r.user_id = u.id WHERE r.business_id = ? ORDER BY r.created_at DESC");

    $stmt_reviews->execute([$business_id]);

    $reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);



    $total_rating = array_sum(array_column($reviews, 'rating'));

    $avg_rating = count($reviews) > 0 ? round($total_rating / count($reviews), 1) : 0;

    

    // جلب إحصائيات المتابعين

    $stmt_followers = $pdo->prepare("SELECT COUNT(*) FROM business_followers WHERE business_id = ?");

    $stmt_followers->execute([$business_id]);

    $follower_count = $stmt_followers->fetchColumn();



    $user_is_following = false;

    if ($current_user_id) {

        $stmt_check_follow = $pdo->prepare("SELECT COUNT(*) FROM business_followers WHERE user_id = ? AND business_id = ?");

        $stmt_check_follow->execute([$current_user_id, $business_id]);

        $user_is_following = (bool)$stmt_check_follow->fetchColumn();

    }

    

    // معالجة ساعات العمل

    $hours = !empty($business['opening_hours']) ? json_decode($business['opening_hours'], true) : null;

    $has_hours = ($hours && json_last_error() === JSON_ERROR_NONE && count(array_filter((array)$hours)) > 0);



    // دالة مساعدة لمعالجة رابط الفيديو

    function get_youtube_embed_url($url) {

        if (preg_match('/(youtube\.com|youtu\.be)\/(watch\?v=|embed\/|v\/|shorts\/)?([a-zA-Z0-9_-]{11})/', $url, $matches)) {

            return 'https://www.youtube.com/embed/' . $matches[3];

        }

        return null;

    }



    function get_facebook_embed_url($url) {

        if (strpos($url, 'facebook.com') !== false || strpos($url, 'fb.watch') !== false) {

            return "https://www.facebook.com/plugins/video.php?height=315&href=" . urlencode($url) . "&show_text=false";

        }

        return null;

    }

    

    // =========================================================================

    // 🔒 نظام الإغلاق الصارم (Strict Security Lock) - توقيت سوريا

    // =========================================================================

    date_default_timezone_set('Asia/Damascus');



    if ($has_hours) {

        $english_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $arabic_days  = ['الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد'];

        

        $current_day_english = date('l'); 

        $current_timestamp = time(); 



        $today_times = null;

        $day_display_name = "";



        if (isset($hours[$current_day_english])) {

            $today_times = $hours[$current_day_english];

            $day_display_name = $arabic_days[array_search($current_day_english, $english_days)];

        } else {

            $day_index = array_search($current_day_english, $english_days);

            if ($day_index !== false) {

                $arabic_key = $arabic_days[$day_index];

                foreach ($hours as $key => $val) {

                    if (strpos($key, 'اثنين') !== false && $day_index == 0) { $today_times = $val; $day_display_name = $key; break; }

                    if ($key == $arabic_key) { $today_times = $val; $day_display_name = $key; break; }

                }

            }

        }



        $is_open_now = false;



        if ($today_times) {

            $clean_time = trim(strtolower($today_times));



            if ($clean_time === 'closed' || $clean_time === 'مغلق') {

                $is_open_now = false;

            } 

            elseif (strpos($clean_time, '24') !== false || $clean_time === 'مفتوح' || $clean_time === 'always open') {

                $is_open_now = true;

            } 

            else {

                $parts = explode('-', $today_times);

                if (count($parts) >= 2) {

                    $start_time = strtotime(trim($parts[0])); 

                    $end_time   = strtotime(trim($parts[1])); 



                    if ($start_time !== false && $end_time !== false) {

                        if ($end_time < $start_time) {

                            if ($current_timestamp >= $start_time || $current_timestamp <= $end_time) {

                                $is_open_now = true;

                            }

                        } else {

                            if ($current_timestamp >= $start_time && $current_timestamp <= $end_time) {

                                $is_open_now = true;

                            }

                        }

                    }

                }

            }

        }



        if (!$is_open_now) {

            while (ob_get_level()) { ob_end_clean(); }

            ?>

            <!DOCTYPE html>

            <html lang="ar" dir="rtl">

            <head>

                <meta charset="UTF-8">

                <meta name="viewport" content="width=device-width, initial-scale=1.0">

                <title>المتجر مغلق - <?php echo htmlspecialchars($business['name']); ?></title>

                <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">

                <style>

                    body {

                        margin: 0; padding: 0; font-family: 'Cairo', sans-serif;

                        background-color: #f8f9fa; height: 100vh;

                        display: flex; flex-direction: column; align-items: center; justify-content: center;

                        text-align: center; color: #333;

                    }

                    .lock-icon { font-size: 80px; margin-bottom: 20px; color: #dc3545; }

                    h1 { font-size: 28px; margin: 0 0 10px; font-weight: 900; }

                    p { color: #666; max-width: 400px; line-height: 1.6; margin-bottom: 30px; padding: 0 20px; }

                    .hours-badge {

                        background: #fff; padding: 15px 30px; border-radius: 50px;

                        box-shadow: 0 5px 20px rgba(0,0,0,0.05); font-weight: bold;

                        border: 1px solid #eee; display: inline-block;

                    }

                    .btn-home {

                        margin-top: 30px; text-decoration: none; background: #333; color: #fff;

                        padding: 12px 30px; border-radius: 8px; transition: 0.3s; display: inline-block;

                    }

                    .btn-home:hover { background: #000; }

                </style>

            </head>

            <body>

                <div class="lock-icon">🔒</div>

                <h1>عذراً، المتجر مغلق الآن</h1>

                <p>أهلاً بك في <strong><?php echo htmlspecialchars($business['name']); ?></strong>.<br>ساعات العمل انتهت لهذا اليوم، تفضل بزيارتنا في أوقات الدوام.</p>

                

                <div class="hours-badge">

                    وقت الدوام اليوم: <span style="color: #dc3545; direction: ltr; display: inline-block;"><?php echo $today_times ? htmlspecialchars($today_times) : 'مغلق'; ?></span>

                </div>



                <br>

                <a href="index.php" class="btn-home">تصفح متاجر أخرى</a>

            </body>

            </html>

            <?php

            exit(); 

        }

    }



} catch (PDOException $e) {

    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());

}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($business['name']); ?> - Syriazzle</title>

    <link rel="stylesheet" href="css/all.min.css">

    <link rel="stylesheet" href="css/profile.css">

    <link rel="stylesheet" href="css/main_header.css">

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-lightbox/2.14.2/simple-lightbox.min.css" />

    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />

    <script src="https://unpkg.com/swiper/swiper-bundle.min.js" defer></script>
    <style>
        /* إخفاء زر السلة العائم */
        #cart-fab { display: none !important; }
    
        /* إخفاء أيقونات الزائد (+) في قائمة الطعام والعروض */
        .deal-add-icon, .menu-item-add-icon { display: none !important; }
    
        /* إخفاء صندوق الطلبات الخاصة داخل النافذة المنبثقة */
        #item-modal .form-group { display: none !important; }
    
        /* إخفاء أزرار الزيادة والنقصان وزر الإضافة للسلة داخل النافذة المنبثقة */
        #item-modal .modal-actions { display: none !important; }
    
        /* إخفاء لوحة السلة الجانبية تماماً */
        #cart-panel { display: none !important; }
    
        /* إخفاء نافذة تسجيل الدخول لإتمام الطلب */
        #login-prompt-modal { display: none !important; }
        
        /* تعديل شكل النافذة المنبثقة لتناسب العرض فقط */
        .modal-content { padding-bottom: 20px !important; }
        
    </style>
</head>

<body>

    <?php include 'header_store.php'; ?>

       

    <div class="profile-container">

        <!-- الهيدر -->

        <header class="profile-header">

            <a href="<?php echo htmlspecialchars($business['cover_image'] ?? 'image/default_cover.jpg'); ?>" class="cover-photo-link profile-gallery">

                <div class="cover-photo" style="background-image: url('<?php echo htmlspecialchars($business['cover_image'] ?? 'image/default_cover.jpg'); ?>');"></div>

            </a>

            <div class="header-content-wrapper">

                <div class="header-content">

                    <img src="<?php echo htmlspecialchars($business['logo_image'] ?? 'image/default_logo.webp'); ?>" alt="شعار <?php echo htmlspecialchars($business['name']); ?>" class="logo-image">

                    <div class="header-info">

                        <div class="header-main-info">

                            <h1><?php echo htmlspecialchars($business['name']); ?></h1>

                            <p class="stats-line">

                                <span><i class="fas fa-user-group"></i> <span id="follower-count"><?php echo $follower_count; ?></span> متابع</span>

                                <?php if ($avg_rating > 0): ?>

                                    <span><i class="fas fa-star"></i> <?php echo $avg_rating; ?> (<?php echo count($reviews); ?> مراجعة)</span>

                                <?php endif; ?>

                            </p>

                        </div>

                        <div class="header-action-buttons">

                            <button type="button" class="action-btn secondary share-button"><i class="fas fa-share-alt"></i> مشاركة</button>

                            <?php if ($current_user_id): ?>

                                <button class="action-btn primary <?php echo $user_is_following ? 'following' : ''; ?>" id="follow-toggle-btn" data-business-id="<?php echo $business_id; ?>">

                                    <i class="fas fa-user-plus"></i> 

                                    <span class="follow-text"><?php echo $user_is_following ? 'إلغاء المتابعة' : 'متابعة'; ?></span>

                                </button>

                            <?php else: ?>

                                <a href="login.php?redirect_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="action-btn primary"><i class="fas fa-user-plus"></i> متابعة</a>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        </header>



        <!-- التبويبات -->

        <nav class="profile-tabs" id="profile-tabs" style="position: sticky; top: 75px; z-index: 100; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">

            <div class="tab-button active" data-tab="main-content">نظرة عامة</div>

            <?php if (!empty($categorized_deals)): ?>

                <div class="tab-button" data-tab="deals-content">العروض والصفقات</div>

            <?php endif; ?>

            <?php if (!empty($categorized_menu)): ?>

                <div class="tab-button" data-tab="menu-content">قائمة الأسعار</div>

            <?php endif; ?>

            <?php if (!empty($details)): ?><div class="tab-button" data-tab="details-content">التفاصيل</div><?php endif; ?>

            <?php if (!empty($all_images_for_lightbox)): ?><div class="tab-button" data-tab="gallery-content">الصور</div><?php endif; ?>

            <div class="tab-button" data-tab="reviews-content">المراجعات</div>

        </nav>



        <!-- جسم الصفحة -->

        <div class="profile-body" style="grid-template-columns: 1fr; display: block;">

            <main class="main-column" id="main-column" style="width: 100%;">

                

                <!-- قسم نظرة عامة -->

                <section id="main-content" class="profile-section active">

                    <?php if (!empty($offer_images)): ?>

                    <div class="offers-container" style="margin-bottom: 25px;">

                        <div class="swiper offers-swiper">

                            <div class="swiper-wrapper">

                                <?php foreach ($offer_images as $offer): ?>

                                    <div class="swiper-slide">

                                        <a href="<?php echo htmlspecialchars($offer['link_url'] ?? '#'); ?>" target="_blank">

                                            <img src="<?php echo htmlspecialchars($offer['image_path']); ?>" alt="<?php echo htmlspecialchars($offer['title'] ?? 'عرض خاص'); ?>" />

                                        </a>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                            <div class="swiper-button-next"></div>

                            <div class="swiper-button-prev"></div>

                            <div class="swiper-pagination"></div>

                        </div>

                    </div>

                    <?php endif; ?>



                    <h2><i class="fas fa-info-circle"></i> عن المكان</h2>

                    <p><?php echo nl2br(htmlspecialchars($business['description'])); ?></p>

                    

                    <?php if (!empty($business['video_url'])): ?>

                        <?php 

                            $embedUrl = null;

                            $youtubeUrl = get_youtube_embed_url($business['video_url']);

                            if ($youtubeUrl) {

                                $embedUrl = $youtubeUrl;

                            } else {

                                if (strpos($business['video_url'], 'facebook.com') !== false) {

                                    $embedUrl = get_facebook_embed_url($business['video_url']);

                                }

                            }

                        ?>

                        <?php if ($embedUrl): ?>

                            <div style="margin-top: 25px;">

                                <h2><i class="fas fa-video"></i> فيديو تعريفي</h2>

                                <div class="video-container">

                                    <iframe src="<?php echo $embedUrl; ?>" frameborder="0" allowfullscreen scrolling="no" allow="encrypted-media"></iframe>

                                </div>

                            </div>

                        <?php else: ?>

                            <div style="margin-top: 25px;">

                                <h2><i class="fas fa-video"></i> فيديو</h2>

                                <a href="<?php echo htmlspecialchars($business['video_url']); ?>" target="_blank" class="action-btn secondary" style="display:inline-flex;">

                                    <i class="fas fa-play"></i> مشاهدة الفيديو

                                </a>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>



                    <div class="overview-actions-mobile" style="display:flex; margin-top:25px; gap: 10px; flex-wrap:wrap;">

                        <a href="#" class="overview-action-link share-button"><i class="fas fa-share-alt"></i> مشاركة الصفحة</a>

                        <?php if (!empty($business['phone'])): ?><a href="tel:<?php echo htmlspecialchars($business['phone']); ?>" class="overview-action-link"><i class="fas fa-phone"></i> اتصال</a><?php endif; ?>

                    </div>



                    <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">

                        <h2><i class="fas fa-map-marker-alt"></i> الموقع</h2>

                        <p><?php echo htmlspecialchars($business['address']); ?></p>

                        <div id="map" style="height: 300px; border-radius: 8px; z-index: 1;"></div>

                    </div>



                    <?php if ($has_hours): ?>

                    <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">

                        <h2><i class="fas fa-clock"></i> ساعات العمل</h2>

                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">

                            <?php foreach ($hours as $day => $time): if(!empty($time)): ?>

                                <div style="padding:10px; background: #f8f9fa; border-radius: 6px; border: 1px solid #eee;">

                                    <strong><?php echo htmlspecialchars($day); ?>:</strong> 

                                    <div style="color: var(--primary-color);"><?php echo htmlspecialchars($time); ?></div>

                                </div>

                            <?php endif; endforeach; ?>

                        </div>

                    </div>

                    <?php endif; ?>

                </section>



                <!-- قسم العروض -->

                <?php if (!empty($categorized_deals)): ?>

                <section id="deals-content" class="profile-section">

                    <div class="menu-header-sticky">

                        <div class="menu-header-top">

                            <h2><i class="fas fa-tags"></i> العروض والصفقات</h2>

                            <input type="text" id="deal-search" placeholder="ابحث في العروض...">

                        </div>

                        <p>اضغط على أي صنف لمشاهدة التفاصيل.</p>

                        <div class="menu-filter-bar" id="deal-filter-bar"></div>

                    </div>

                    <div id="deal-items-container">

                        <?php foreach ($categorized_deals as $category => $deals): ?>

                            <div class="menu-category-group" data-category="<?php echo htmlspecialchars($category); ?>">

                                <h3 class="menu-category-header"><?php echo htmlspecialchars($category); ?></h3>

                                <?php foreach ($deals as $deal): ?>

                                    <div class="deal-card" 

                                        data-item-type="deal"

                                        data-item-id="<?php echo $deal['id']; ?>"

                                        data-item-name="<?php echo htmlspecialchars($deal['deal_name']); ?>"

                                        data-item-price="<?php echo htmlspecialchars($deal['new_price']); ?>"

                                        data-item-desc="<?php echo htmlspecialchars($deal['description']); ?>"

                                        data-item-image="<?php echo htmlspecialchars(!empty($deal['image_path']) ? $deal['image_path'] : 'image/default_logo.webp'); ?>">

                                        

                                        <div class="deal-image-wrapper">

                                            <img src="<?php echo htmlspecialchars(!empty($deal['image_path']) ? $deal['image_path'] : 'image/default_logo.webp'); ?>" class="deal-image" alt="<?php echo htmlspecialchars($deal['deal_name']); ?>">

                                            <?php if (!empty($deal['old_price']) && (float)$deal['old_price'] > (float)$deal['new_price']):

                                                $discount_percentage = round((( (float)$deal['old_price'] - (float)$deal['new_price'] ) / (float)$deal['old_price'] ) * 100);

                                            ?>

                                                <div class="deal-discount-badge">خصم <?php echo $discount_percentage; ?>%</div>

                                            <?php endif; ?>

                                        </div>

                                        

                                        <div class="deal-details">

                                            <h4><?php echo htmlspecialchars($deal['deal_name']); ?></h4>

                                            <?php if (!empty($deal['description'])): ?>

                                                <p class="deal-description"><?php echo htmlspecialchars($deal['description']); ?></p>

                                            <?php endif; ?>



                                            <div class="deal-pricing-footer">

                                                <div class="deal-pricing">

                                                    <!-- تعديل العملة هنا -->

                                                    <span class="deal-new-price"><?php echo htmlspecialchars($deal['new_price']); ?> <?php echo $currency_symbol; ?></span>

                                                    <?php if(!empty($deal['old_price'])): ?>

                                                        <span class="deal-old-price"><?php echo htmlspecialchars($deal['old_price']); ?> <?php echo $currency_symbol; ?></span>

                                                    <?php endif; ?>

                                                </div>

                                                <div class="deal-add-icon"><i class="fas fa-plus-circle"></i></div>

                                            </div>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

                <?php endif; ?>



                <!-- قسم المنيو -->

                <?php if (!empty($categorized_menu)): ?>

                <section id="menu-content" class="profile-section">

                    <div class="menu-header-sticky">

                        <div class="menu-header-top">

                            <h2><i class="fas fa-clipboard-list"></i> قائمة الأسعار</h2>

                            <input type="text" id="menu-search" placeholder="ابحث في القائمة...">

                        </div>

                        <p>اضغط على أي صنف لمشاهدة التفاصيل.</p>

                        <div class="menu-filter-bar" id="menu-filter-bar">

                            <a href="#" class="menu-filter-btn active" data-category="all">عرض الكل</a>

                            <?php foreach (array_keys($categorized_menu) as $category): ?><a href="#menu-cat-<?php echo preg_replace('/[^a-zA-Z0-9]/', '-', $category); ?>" class="menu-filter-btn" data-category="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></a><?php endforeach; ?>

                        </div>

                    </div>

                    <div id="menu-items-container">

                        <?php foreach ($categorized_menu as $category => $items): ?>

                            <div class="menu-category-group" data-category="<?php echo htmlspecialchars($category); ?>">

                                <h3 class="menu-category-header" id="menu-cat-<?php echo preg_replace('/[^a-zA-Z0-9]/', '-', $category); ?>"><?php echo htmlspecialchars($category); ?></h3>

                                <?php foreach ($items as $item): ?>

                                    <div class="menu-item" data-item-id="<?php echo $item['id']; ?>" data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>" data-item-price="<?php echo htmlspecialchars($item['price']); ?>" data-item-desc="<?php echo htmlspecialchars($item['description']); ?>" data-item-image="<?php echo htmlspecialchars(!empty($item['image_path']) ? $item['image_path'] : 'image/default_logo.webp'); ?>">

                                        <div class="menu-item-image-wrapper"><img src="<?php echo htmlspecialchars(!empty($item['image_path']) ? $item['image_path'] : 'image/default_logo.webp'); ?>" class="menu-item-image" alt="<?php echo htmlspecialchars($item['item_name']); ?>"></div>

                                        <div class="menu-item-details">

                                            <div class="menu-item-header">

                                                <h4><?php echo htmlspecialchars($item['item_name']); ?></h4>

                                                <!-- تعديل العملة هنا -->

                                                <span class="menu-item-price"><?php echo htmlspecialchars($item['price']); ?> <?php echo $currency_symbol; ?></span>

                                            </div>

                                            <?php if (!empty($item['description'])): ?><p class="menu-item-description"><?php echo htmlspecialchars($item['description']); ?></p><?php endif; ?>

                                        </div>

                                        <div class="menu-item-add-icon"><i class="fas fa-plus-circle"></i></div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

                <?php endif; ?>

                

                <!-- التفاصيل -->

                <?php if (!empty($details)): ?>

                <section id="details-content" class="profile-section">

                    <h2><i class="fas fa-list-alt"></i> التفاصيل والميزات</h2>

                    <div class="details-grid"><?php foreach ($details as $key => $value): ?><div class="detail-item"><i class="fas fa-check-circle"></i><div><strong><?php echo htmlspecialchars($key); ?>:</strong> <span><?php echo htmlspecialchars($value); ?></span></div></div><?php endforeach; ?></div>

                </section>

                <?php endif; ?>

                

                <!-- الصور -->

                <?php if (!empty($all_images_for_lightbox)): ?>

                <section id="gallery-content" class="profile-section">

                    <h2><i class="fas fa-images"></i> معرض الصور</h2>

                    <div class="gallery-grid profile-gallery"><?php foreach ($all_images_for_lightbox as $image): ?><a href="<?php echo htmlspecialchars($image); ?>"><img src="<?php echo htmlspecialchars($image); ?>" alt="صورة من المعرض"></a><?php endforeach; ?></div>

                </section>

                <?php endif; ?>



                <!-- المراجعات -->

                <section id="reviews-content" class="profile-section">

                    <h2><i class="fas fa-star-half-alt"></i> التقييمات والمراجعات</h2>

                    <div class="review-box-container">

                        <?php if ($current_user_id): ?>

                            <div class="write-review-card">

                                <h4>شاركنا تجربتك</h4>

                                <form id="review-form">

                                    <input type="hidden" name="business_id" value="<?php echo $business_id; ?>">

                                    <div class="rating-select-wrapper">

                                        <span>تقييمك:</span>

                                        <div class="star-rating-input">

                                            <select name="rating" required>

                                                <option value="5">⭐⭐⭐⭐⭐ ممتاز</option>

                                                <option value="4">⭐⭐⭐⭐ جيد جداً</option>

                                                <option value="3">⭐⭐⭐ جيد</option>

                                                <option value="2">⭐⭐ مقبول</option>

                                                <option value="1">⭐ سيء</option>

                                            </select>

                                        </div>

                                    </div>

                                    <textarea name="review_text" id="review-text" placeholder="كيف كانت تجربتك؟ (الخدمة، الجودة، الأسعار...)" required></textarea>

                                    <button type="submit" id="submit-review-btn"><i class="fas fa-paper-plane"></i> نشر المراجعة</button>

                                    <div id="review-msg"></div>

                                </form>

                            </div>

                        <?php else: ?>

                            <div class="login-to-review">

                                <i class="fas fa-lock"></i>

                                <p>يرجى <a href="login.php?redirect_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">تسجيل الدخول</a> لكتابة مراجعة.</p>

                            </div>

                        <?php endif; ?>

                    </div>

                    <div id="reviews-list" class="reviews-grid">

                        <?php if(empty($reviews)): ?>

                            <div class="no-reviews-state">

                                <i class="far fa-comment-dots"></i>

                                <p>لا توجد مراجعات حتى الآن. كن أول من يقيم هذا المكان!</p>

                            </div>

                        <?php else: ?>

                            <?php foreach($reviews as $review): ?>

                                <div class="review-card">

                                    <div class="review-card-header">

                                        <div class="reviewer-avatar">

                                            <?php echo mb_substr($review['username'], 0, 1, 'UTF-8'); ?>

                                        </div>

                                        <div class="reviewer-info">

                                            <strong class="reviewer-name"><?php echo htmlspecialchars($review['username']); ?></strong>

                                            <div class="review-date"><?php echo date('Y/m/d', strtotime($review['created_at'])); ?></div>

                                        </div>

                                        <div class="review-stars">

                                            <?php echo str_repeat('<i class="fas fa-star"></i>', $review['rating']); ?>

                                            <?php echo str_repeat('<i class="far fa-star"></i>', 5 - $review['rating']); ?>

                                        </div>

                                    </div>

                                    <div class="review-body">

                                        <p><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>

                                    </div>

                                    <?php if (!empty($review['reply_text'])): ?>

                                        <div class="owner-reply">

                                            <div class="reply-header">

                                                <i class="fas fa-store"></i> رد من المتجر

                                                <span class="reply-date"><?php echo $review['replied_at'] ? date('Y/m/d', strtotime($review['replied_at'])) : ''; ?></span>

                                            </div>

                                            <p><?php echo nl2br(htmlspecialchars($review['reply_text'])); ?></p>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </section>

            </main>

        </div>

    </div>



    <!-- النوافذ المنبثقة (Modals) -->

    <div class="modal-overlay" id="item-modal">

        <div class="modal-content">

            <button class="modal-close" id="modal-close-btn">&times;</button>

            <img src="" alt="صورة الصنف" id="modal-image" class="modal-image">

            <div class="modal-details">

                <div class="modal-header">

                    <h3 id="modal-title" class="modal-title"></h3>

                    <span id="modal-price" class="modal-price"></span>

                </div>

                <p id="modal-description" class="modal-description"></p>

                <div class="form-group" style="margin-top: 15px;">

                    <label for="special-requests-input" style="font-weight: 600; margin-bottom: 8px; display: block;">طلبات خاصة (اختياري)</label>

                    <textarea id="special-requests-input" placeholder="مثال: بدون بصل، صلصة إضافية..."></textarea>

                </div>

            </div>

             <div class="modal-actions">

                <div class="quantity-selector">

                    <button id="decrease-quantity">-</button>

                    <span id="quantity-display" class="quantity-display">1</span>

                    <button id="increase-quantity">+</button>

                </div>

                <button id="add-to-cart-btn" class="add-to-cart-btn">أضف إلى السلة</button>

            </div> 

        </div>

    </div>

    

    <div class="modal-overlay" id="login-prompt-modal">

        <div class="modal-content" style="max-width: 400px; text-align: center;">

            <button class="modal-close" id="login-prompt-close-btn">&times;</button>

            <div class="modal-details" style="padding-top: 25px;">

                <h3><i class="fas fa-sign-in-alt"></i> يرجى تسجيل الدخول للمتابعة</h3>

                <p>يجب عليك تسجيل الدخول أو إنشاء حساب جديد لإتمام عملية الطلب.</p>

                <div class="modal-actions" style="flex-direction: row; justify-content: center; gap: 15px; border-top: none; background: none; padding-top:10px;">

                    <a href="login.php?redirect_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="add-to-cart-btn">تسجيل الدخول</a>

                    <a href="register.php" class="action-btn secondary" style="padding: 10px 25px;">إنشاء حساب</a>

                </div>

            </div>

        </div>

    </div>



    <div class="cart-fab" id="cart-fab"><i class="fas fa-shopping-cart"></i><span class="cart-count" id="cart-count">0</span></div>

    

    <div class="cart-panel" id="cart-panel">

        <div class="cart-header"><h3>سلة المشتريات</h3><button class="close-cart-btn" id="close-cart-btn">&times;</button></div>

        <div class="cart-body" id="cart-body"></div>

        <div class="cart-footer">

            <div class="cart-total"><span>الإجمالي</span><span id="cart-total-price">0 ل.س</span></div>

            <button class="checkout-btn" id="checkout-btn">إتمام الطلب</button>

        </div>

    </div>



    <!-- Lightbox -->

    <div id="lightbox-modal" class="lightbox-overlay">

        <span class="lightbox-close">&times;</span>

        <img class="lightbox-content" id="lightbox-img">

        <div id="lightbox-caption"></div>

    </div>

    

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/simple-lightbox/2.14.2/simple-lightbox.min.js"></script>

    <script>

        document.addEventListener('DOMContentLoaded', () => {

            const isUserLoggedIn = <?php echo json_encode($isUserLoggedIn); ?>;

            const businessId = <?php echo json_encode($business_id); ?>;

            // === تعديل: تمرير رمز العملة إلى JS ===

            const currencySymbol = <?php echo json_encode($currency_symbol); ?>;



            function formatPrice(number) {

                const num = parseFloat(number);

                if (isNaN(num)) return '0';

                return num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).replace(/\.00$/, '');

            }



            const tabs = document.querySelectorAll('.tab-button');

            if (tabs.length > 0) {

                tabs.forEach(tab => {

                    tab.addEventListener('click', (e) => {

                        e.preventDefault();

                        tabs.forEach(t => t.classList.remove('active'));

                        document.querySelectorAll('.profile-section').forEach(s => s.classList.remove('active'));

                        tab.classList.add('active');

                        const targetId = tab.dataset.tab;

                        const targetElement = document.getElementById(targetId);

                        if (targetElement) {

                            targetElement.classList.add('active');

                            window.scrollTo({ top: 75, behavior: 'smooth' });

                        }

                    });

                });

            }



            function initMap(containerId, lat, lon, popupText) {

                const mapContainer = document.getElementById(containerId);

                if (mapContainer && typeof lat === 'number' && typeof lon === 'number' && !isNaN(lat) && !isNaN(lon)) {

                    try {

                        const map = L.map(containerId).setView([lat, lon], 16);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

                        L.marker([lat, lon]).addTo(map).bindPopup(popupText);

                        setTimeout(() => { map.invalidateSize(); }, 500);

                    } catch(e) { console.error("Map init error:", e); }

                }

            }

            const lat = parseFloat(<?php echo json_encode($business['latitude'] ?? 'null'); ?>);

            const lon = parseFloat(<?php echo json_encode($business['longitude'] ?? 'null'); ?>);

            const businessName = <?php echo json_encode(htmlspecialchars($business['name'])); ?>;

            initMap('map', lat, lon, businessName);



            try { new SimpleLightbox('.profile-gallery a'); } catch(e) { }

            const offersSwiperElement = document.querySelector('.offers-swiper');

            if (offersSwiperElement && typeof Swiper !== 'undefined') {

                try {

                    new Swiper(offersSwiperElement, {

                        effect: 'coverflow', grabCursor: true, centeredSlides: true,

                        slidesPerView: 'auto', loop: true, autoplay: { delay: 4000, disableOnInteraction: false },

                        coverflowEffect: { rotate: 50, stretch: 0, depth: 100, modifier: 1, slideShadows: true },

                        pagination: { el: '.swiper-pagination', clickable: true },

                        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' }

                    });

                } catch(e) { }

            }



            const reviewForm = document.getElementById('review-form');

            if (reviewForm) {

                reviewForm.addEventListener('submit', function(e) {

                    e.preventDefault(); 

                    const submitBtn = document.getElementById('submit-review-btn');

                    const msgDiv = document.getElementById('review-msg');

                    const originalBtnText = submitBtn.textContent;

                    submitBtn.disabled = true; submitBtn.textContent = 'جاري الإرسال...'; msgDiv.style.display = 'none';

                    const formData = new FormData(reviewForm);

                    fetch('php/submit_review.php', { method: 'POST', body: formData })

                    .then(response => { if (response.ok) { return response.text(); } throw new Error('حدث خطأ في الاتصال'); })

                    .then(data => {

                        const rating = formData.get('rating');

                        const text = formData.get('review_text');

                        const stars = '⭐'.repeat(rating);

                        const username = "أنت (الآن)";

                        const newReviewHTML = `<div class="review" style="background-color: #f0f8ff; border: 1px solid #b6d4fe;"><div class="review-header"><strong>${username}</strong><span class="review-rating">${stars}</span></div><p>${text.replace(/\n/g, '<br>')}</p></div>`;

                        const reviewsList = document.getElementById('reviews-list');

                        const noReviewsMsg = document.getElementById('no-reviews-msg');

                        if (noReviewsMsg) noReviewsMsg.remove();

                        reviewsList.insertAdjacentHTML('afterbegin', newReviewHTML);

                        reviewForm.reset();

                        msgDiv.className = 'success-msg'; msgDiv.style.display = 'block'; msgDiv.style.color = 'green'; msgDiv.textContent = 'تم نشر مراجعتك بنجاح!';

                    })

                    .catch(error => { console.error(error); msgDiv.style.display = 'block'; msgDiv.style.color = 'red'; msgDiv.textContent = 'عذراً، حدث خطأ أثناء الإرسال.'; })

                    .finally(() => { submitBtn.disabled = false; submitBtn.textContent = originalBtnText; setTimeout(() => { msgDiv.style.display = 'none'; }, 3000); });

                });

            }



            let cart = JSON.parse(localStorage.getItem(`cart_${businessId}`)) || [];

            const cartFab = document.getElementById('cart-fab');

            const cartCount = document.getElementById('cart-count');

            const cartPanel = document.getElementById('cart-panel');

            const closeCartBtn = document.getElementById('close-cart-btn');

            const cartBody = document.getElementById('cart-body');

            const cartTotalPrice = document.getElementById('cart-total-price');

            const checkoutBtn = document.getElementById('checkout-btn');

            const loginPromptModal = document.getElementById('login-prompt-modal');

            const modal = document.getElementById('item-modal');

            const modalImage = document.getElementById('modal-image');

            const modalTitle = document.getElementById('modal-title');

            const modalPrice = document.getElementById('modal-price');

            const modalDesc = document.getElementById('modal-description');

            const specialRequestsInput = document.getElementById('special-requests-input');

            const decreaseQtyBtn = document.getElementById('decrease-quantity');

            const increaseQtyBtn = document.getElementById('increase-quantity');

            const qtyDisplay = document.getElementById('quantity-display');

            const addToCartBtn = document.getElementById('add-to-cart-btn');

            

            let currentItem = null;

            let currentQuantity = 1;



            function saveCart() { localStorage.setItem(`cart_${businessId}`, JSON.stringify(cart)); updateCartUI(); }

            function updateCartUI() {
                // إضافة return  على التحديث الجديد
                return;
                if (!cartBody) return;

                cartBody.innerHTML = ''; let total = 0; let count = 0;

                cart.forEach(item => {

                    const itemTotal = item.price * item.quantity; total += itemTotal; count += item.quantity;

                    const itemRequestsHTML = item.requests ? `<div class="cart-item-requests">ملاحظات: ${item.requests}</div>` : '';

                    // === تعديل: استخدام العملة الديناميكية في السلة ===

                    const cartItemEl = document.createElement('div'); cartItemEl.className = 'cart-item';

                    cartItemEl.innerHTML = `<img src="${item.image}" alt="${item.name}" class="cart-item-image"><div class="cart-item-details"><div class="cart-item-name">${item.name}</div>${itemRequestsHTML}<div class="cart-item-price">${formatPrice(itemTotal)} ${currencySymbol}</div><div class="cart-item-actions"><div class="quantity-selector"><button class="cart-decrease" data-id="${item.cartId}">-</button><span class="quantity-display">${item.quantity}</span><button class="cart-increase" data-id="${item.cartId}">+</button></div><button class="remove-item-btn" data-id="${item.cartId}">إزالة</button></div></div>`;

                    cartBody.appendChild(cartItemEl);

                });

                cartTotalPrice.textContent = `${formatPrice(total)} ${currencySymbol}`; cartCount.textContent = count;

                if (cartFab) cartFab.classList.toggle('visible', count > 0);

                if (checkoutBtn) checkoutBtn.disabled = count === 0;

            }

            function addToCart() {

                if (!currentItem) return;

                const specialRequests = specialRequestsInput.value.trim();

                const cartItemId = `${currentItem.type}-${currentItem.id}-${specialRequests}`; 

                const existingItem = cart.find(item => item.cartId === cartItemId);

                if (existingItem) { existingItem.quantity += currentQuantity; } else { cart.push({ ...currentItem, cartId: cartItemId, quantity: currentQuantity, requests: specialRequests }); }

                saveCart(); showAddToCartFeedback();

            }

            function showAddToCartFeedback() {

                if (!addToCartBtn) return;

                addToCartBtn.innerHTML = '<i class="fas fa-check"></i> تمت الإضافة'; addToCartBtn.disabled = true;

                setTimeout(() => { modal.classList.remove('visible'); }, 800);

                setTimeout(() => { addToCartBtn.innerHTML = 'أضف إلى السلة'; addToCartBtn.disabled = false; }, 1200);

            }

            function updateQuantityInCart(cartItemId, change) {

                const item = cart.find(i => i.cartId === cartItemId);

                if (item) { item.quantity += change; if (item.quantity <= 0) { cart = cart.filter(i => i.cartId !== cartItemId); } saveCart(); }

            }

            const closeModal = () => { if (modal) modal.classList.remove('visible'); };

            function openItemModal(element) {

                currentItem = { id: element.dataset.itemId, type: element.dataset.itemType, name: element.dataset.itemName, price: parseFloat(element.dataset.itemPrice.replace(/[^0-9.]/g, '')), image: element.dataset.itemImage, desc: element.dataset.itemDesc };

                modalImage.src = currentItem.image; modalTitle.textContent = currentItem.name; 

                // === تعديل: استخدام العملة الديناميكية في المودال ===

                modalPrice.textContent = `${formatPrice(currentItem.price)} ${currencySymbol}`; 

                modalDesc.textContent = currentItem.desc; specialRequestsInput.value = ''; currentQuantity = 1; qtyDisplay.textContent = currentQuantity; modal.classList.add('visible');

            }



            function initializeSearchAndFilter(config) {

                const searchInput = document.getElementById(config.searchInputId);

                const filterBar = document.getElementById(config.filterBarId);

                const itemsContainer = document.getElementById(config.itemsContainerId);

                if (!searchInput || !filterBar || !itemsContainer) return;

                const categories = new Set();

                itemsContainer.querySelectorAll(config.groupSelector).forEach(group => { categories.add(group.dataset.category); });

                if (categories.size > 0) {

                    filterBar.innerHTML = '<a href="#" class="menu-filter-btn active" data-category="all">عرض الكل</a>';

                    categories.forEach(cat => { filterBar.innerHTML += `<a href="#" class="menu-filter-btn" data-category="${cat}">${cat}</a>`; });

                } else { filterBar.style.display = 'none'; }

                searchInput.addEventListener('input', () => {

                    const searchTerm = searchInput.value.toLowerCase().trim();

                    itemsContainer.querySelectorAll(config.groupSelector).forEach(group => {

                        let groupHasVisibleItems = false;

                        group.querySelectorAll(config.itemSelector).forEach(item => {

                            const itemName = item.dataset.itemName.toLowerCase(); const isVisible = itemName.includes(searchTerm);

                            item.style.display = isVisible ? 'flex' : 'none'; if (isVisible) groupHasVisibleItems = true;

                        });

                        group.style.display = groupHasVisibleItems ? 'block' : 'none';

                    });

                });

                filterBar.addEventListener('click', (e) => {

                    if (e.target.matches('.menu-filter-btn')) {

                        e.preventDefault(); filterBar.querySelectorAll('.menu-filter-btn').forEach(l => l.classList.remove('active')); e.target.classList.add('active');

                        const targetCategory = e.target.dataset.category;

                        itemsContainer.querySelectorAll(config.groupSelector).forEach(group => {

                            group.style.display = (targetCategory === 'all' || group.dataset.category === targetCategory) ? 'block' : 'none';

                        });

                    }

                });

            }



            initializeSearchAndFilter({ searchInputId: 'menu-search', filterBarId: 'menu-filter-bar', itemsContainerId: 'menu-items-container', itemSelector: '.menu-item', groupSelector: '.menu-category-group' });
            initializeSearchAndFilter({ searchInputId: 'deal-search', filterBarId: 'deal-filter-bar', itemsContainerId: 'deal-items-container', itemSelector: '.deal-card', groupSelector: '.menu-category-group' });
            document.querySelectorAll('.menu-item, .deal-card').forEach(itemEl => { itemEl.addEventListener('click', () => openItemModal(itemEl)); });

            if (modal) { document.getElementById('modal-close-btn').addEventListener('click', closeModal); modal.addEventListener('click', e => { if (e.target === modal) closeModal(); }); increaseQtyBtn.addEventListener('click', () => qtyDisplay.textContent = ++currentQuantity); decreaseQtyBtn.addEventListener('click', () => { if (currentQuantity > 1) qtyDisplay.textContent = --currentQuantity; }); /*addToCartBtn.addEventListener('click', addToCart);*/} 

            // if (checkoutBtn) { checkoutBtn.addEventListener('click', () => { if (!isUserLoggedIn) { if(loginPromptModal) loginPromptModal.classList.add('visible'); } else { window.location.href = `checkout.php?business_id=${businessId}`; } }); }

            if (cartFab) cartFab.addEventListener('click', () => cartPanel.classList.add('open'));

            if (closeCartBtn) closeCartBtn.addEventListener('click', () => cartPanel.classList.remove('open'));

            if (cartBody) { cartBody.addEventListener('click', e => { const target = e.target.closest('button'); if (!target) return; const id = target.dataset.id; if (target.matches('.cart-increase')) updateQuantityInCart(id, 1); if (target.matches('.cart-decrease')) updateQuantityInCart(id, -1); if (target.matches('.remove-item-btn')) { cart = cart.filter(i => i.cartId !== id); saveCart(); } }); }

            if(loginPromptModal) { document.getElementById('login-prompt-close-btn').addEventListener('click', () => loginPromptModal.classList.remove('visible')); loginPromptModal.addEventListener('click', e => { if (e.target === loginPromptModal) loginPromptModal.classList.remove('visible'); }); }


            const followToggleBtn = document.getElementById('follow-toggle-btn');

            if (followToggleBtn) {

                followToggleBtn.addEventListener('click', async () => {

                    const followTextSpan = followToggleBtn.querySelector('.follow-text');

                    if (!followTextSpan) return;

                    const originalText = followTextSpan.textContent;

                    followTextSpan.textContent = '...'; followToggleBtn.disabled = true;

                    try {

                        const formData = new FormData(); formData.append('business_id', businessId);

                        const response = await fetch('php/toggle_follow.php', { method: 'POST', body: formData });

                        const result = await response.json();

                        if (result.success) {

                            document.getElementById('follower-count').textContent = result.follower_count;

                            followTextSpan.textContent = result.new_status_text;

                            followToggleBtn.classList.toggle('following', result.is_following);

                        } else { alert(result.message || 'حدث خطأ ما.'); followTextSpan.textContent = originalText; }

                    } catch (error) { console.error('Follow error:', error); alert(error.message); followTextSpan.textContent = originalText; } finally { followToggleBtn.disabled = false; }

                });

            }



            document.querySelectorAll('.share-button').forEach(button => {

                button.addEventListener('click', async () => {

                    const shareData = { title: document.title, text: `ألقِ نظرة على "${businessName}" على Syriazzle!`, url: window.location.href };

                    try { if (navigator.share) { await navigator.share(shareData); } else { await navigator.clipboard.writeText(window.location.href); alert('تم نسخ الرابط إلى الحافظة!'); } } catch (err) { console.error("خطأ في المشاركة:", err); alert('فشلت عملية المشاركة.'); }

                });

            });



            // === تعديل: تحديث الأسعار عند التحميل ===

            document.querySelectorAll('.menu-item-price, .deal-new-price, .deal-old-price').forEach(priceEl => {

                const numericValue = parseFloat(priceEl.textContent.replace(/[^0-9.]/g, ''));

                if (!isNaN(numericValue)) { 

                    const suffix = priceEl.classList.contains('deal-old-price') ? '' : ` ${currencySymbol}`;

                    priceEl.textContent = formatPrice(numericValue) + suffix; 

                }

            });

            updateCartUI();



            const lightboxModal = document.getElementById('lightbox-modal');

            const lightboxImg = document.getElementById('lightbox-img');

            const lightboxClose = document.querySelector('.lightbox-close');

            if (lightboxModal && lightboxImg) {

                document.querySelectorAll('.profile-gallery a').forEach(link => {

                    link.addEventListener('click', function(e) {

                        e.preventDefault(); 

                        lightboxModal.style.display = "flex"; lightboxModal.style.flexDirection = "column"; lightboxModal.style.justifyContent = "center";

                        lightboxImg.src = this.href; 

                    });

                });

                lightboxClose.addEventListener('click', () => { lightboxModal.style.display = "none"; });

                lightboxModal.addEventListener('click', (e) => { if (e.target === lightboxModal) { lightboxModal.style.display = "none"; } });

            }

        });

    </script>

</body>

</html>