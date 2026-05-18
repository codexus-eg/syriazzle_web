<?php

require_once 'php/db_connect.php'; 

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Syriazzle - عالمك بين يديك</title>

    <link rel="icon" href="image/favicon.png" type="image/png">

    <!-- Fonts & Icons -->

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">



    <!-- ملفات التنسيق -->

    <link rel="stylesheet" href="css/main_header.css">

    <link rel="stylesheet" href="css/style_custom.css"> 

</head>

<body>



    <!-- ===================== الهيدر المضمن ===================== -->

    <?php include 'header_store.php'; ?>

    

    <?php

    // --- منطق شريط الطلب النشط (مع دعم العملات) ---

    if (isset($_SESSION['user_id'])) {

        $active_orders = [];

        

        try {

            $u_id = $_SESSION['user_id'];

            

            // 1. جلب طلبات المتاجر النشطة (مع العملة)

            $sql_market = "SELECT id, status, total_price, created_at, currency, 'market' as type 

                           FROM orders 

                           WHERE user_id = ? AND status NOT IN ('delivered', 'canceled')";

            $stmt1 = $pdo->prepare($sql_market);

            $stmt1->execute([$u_id]);

            $market_orders = $stmt1->fetchAll(PDO::FETCH_ASSOC);



            // 2. جلب طلبات المول النشطة (افتراضياً SYP للمول)

            $sql_mall = "SELECT id, status, total_price, created_at, 'SYP' as currency, 'mall' as type 

                         FROM mall_orders 

                         WHERE user_id = ? AND status NOT IN ('delivered', 'canceled')";

            $stmt2 = $pdo->prepare($sql_mall);

            $stmt2->execute([$u_id]);

            $mall_orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);



            // 3. دمج وترتيب (الأحدث أولاً)

            $active_orders = array_merge($market_orders, $mall_orders);

            usort($active_orders, function($a, $b) {

                return strtotime($b['created_at']) - strtotime($a['created_at']);

            });



        } catch (Exception $e) { }



        // إذا وجد طلبات نشطة

        if (!empty($active_orders)) {

            $latest = $active_orders[0]; // أحدث طلب

            $total_count = count($active_orders); // العدد الكلي

            $others_count = $total_count - 1; // عدد الطلبات الأخرى



            // إعدادات العرض لآخر طلب

            $conf = ['text'=>'طلبك قيد المعالجة', 'icon'=>'fa-clock', 'color'=>'#6c757d', 'bg'=>'#f8f9fa'];

            

            // روابط التوجيه الذكية

            $track_link = ($latest['type'] === 'mall') ? "track_mall_order.php?order_id={$latest['id']}" : "track_order.php?order_id={$latest['id']}";

            // زر القائمة يوجه لصفحة "طلباتي" الموحدة الجديدة

            $list_link = ($latest['type'] === 'mall') ? "mall_orders.php" : "my_orders.php";



            switch ($latest['status']) {

                case 'pending_approval': $conf = ['text'=>'بانتظار الموافقة', 'icon'=>'fa-hourglass-half', 'color'=>'#ff9800', 'bg'=>'#fff3e0']; break;

                case 'preparing': $conf = ['text'=>'يتم التحضير 🔥', 'icon'=>'fa-fire', 'color'=>'#e65100', 'bg'=>'#ffe0b2']; break;

                case 'ready_for_pickup': $conf = ['text'=>'بانتظار السائق', 'icon'=>'fa-box', 'color'=>'#0097a7', 'bg'=>'#e0f7fa']; break;

                case 'accepted': $conf = ['text'=>'السائق في الطريق للمتجر', 'icon'=>'fa-car', 'color'=>'#1565c0', 'bg'=>'#e3f2fd']; break;

                case 'picked_up': 

                case 'out_for_delivery': $conf = ['text'=>'طلبك واصل 🛵', 'icon'=>'fa-motorcycle', 'color'=>'#2e7d32', 'bg'=>'#e8f5e9']; break;

            }



            // --- تنسيق السعر بناءً على العملة ---

            $currency_code = $latest['currency'] ?? 'SYP';

            if ($currency_code === 'USD') {

                $price_display = '$' . number_format($latest['total_price'], 2);

            } else {

                $price_display = number_format($latest['total_price']) . ' ل.س';

            }

    ?>

    <style>

        .smart-order-widget {

            background: #fff;

            margin: 15px;

            border-radius: 16px;

            box-shadow: 0 8px 20px rgba(0,0,0,0.08);

            display: flex;

            align-items: stretch;

            overflow: hidden;

            animation: slideDown 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);

            position: relative;

            z-index: 99;

            border: 1px solid #f0f0f0;

        }

        @keyframes slideDown { from {opacity:0; transform:translateY(-15px);} to {opacity:1; transform:translateY(0);} }



        /* القسم الأيمن: التتبع */

        .track-section {

            flex: 1;

            display: flex;

            align-items: center;

            padding: 12px 15px;

            text-decoration: none;

            color: inherit;

            position: relative;

        }

        .track-section:active { background-color: #f9f9f9; }



        .widget-icon {

            width: 45px; height: 45px;

            border-radius: 50%;

            background-color: <?php echo $conf['bg']; ?>;

            color: <?php echo $conf['color']; ?>;

            display: flex; align-items: center; justify-content: center;

            font-size: 1.2rem; margin-left: 12px;

            flex-shrink: 0;

            transition: transform 0.2s;

        }

        /* نبض للأيقونة */

        <?php if(in_array($latest['status'], ['picked_up', 'out_for_delivery'])): ?>

        .widget-icon { animation: pulseW 1.5s infinite; }

        @keyframes pulseW { 0% {box-shadow: 0 0 0 0 <?php echo $conf['color']; ?>50;} 70% {box-shadow: 0 0 0 8px <?php echo $conf['color']; ?>00;} 100% {box-shadow: 0 0 0 0 <?php echo $conf['color']; ?>00;} }

        <?php endif; ?>



        .widget-details { flex: 1; }

        .widget-details h4 { margin: 0 0 2px; font-size: 0.95rem; color: #333; font-weight: 700; }

        .widget-details span { font-size: 0.75rem; color: #666; display: block; }

        

        .more-badge {

            display: inline-block; background: rgb(235, 154, 3); color: #fff;

            font-size: 0.65rem; padding: 1px 6px; border-radius: 10px;

            margin-right: 5px; font-weight: normal; vertical-align: middle;

        }



        /* الخط الفاصل */

        .widget-divider { width: 1px; background-color: #eee; margin: 10px 0; }



        /* القسم الأيسر: زر القائمة */

        .list-section {

            width: 90px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            text-decoration: none;

            background-color: #fcfcfc;

            transition: background 0.2s;

            position: relative;

        }

        .list-section:active { background-color: #f0f0f0; }

        

        .list-icon { font-size: 1.2rem; color: #555; margin-bottom: 3px; }

        .list-text { font-size: 0.75rem; font-weight: 700; color: #555; }

        

        /* عداد الطلبات الكلي */

        .total-badge {

            position: absolute; top: 10px; left: 50%; transform: translateX(-50%);

            background: #e87407; color: #fff; font-size: 0.7rem;

            min-width: 20px; height: 20px; border-radius: 10px; padding: 0 5px;

            display: flex; align-items: center; justify-content: center;

            font-weight: bold; border: 2px solid #fff;

            z-index: 2; margin-left: 15px;

        }

    </style>



    <div class="smart-order-widget">

        <!-- القسم 1: اضغط للتتبع المباشر -->

        <a href="<?php echo $track_link; ?>" class="track-section">

            <div class="widget-icon">

                <i class="fas <?php echo $conf['icon']; ?>"></i>

            </div>

            <div class="widget-details">

                <h4><?php echo $conf['text']; ?></h4>

                <span>

                    <?php if($others_count > 0): ?>

                        <span class="more-badge">+<?php echo $others_count; ?></span>

                    <?php endif; ?>

                    طلب #<?php echo $latest['id']; ?> 

                    &bull; <span dir="ltr"><?php echo $price_display; ?></span>

                </span>

            </div>

            <!-- سهم صغير للدلالة على قابلية الضغط -->

            <i class="fas fa-chevron-left" style="color:#ccc; font-size:0.8rem;"></i>

        </a>



        <div class="widget-divider"></div>



        <!-- القسم 2: اضغط للقائمة الكاملة -->

        <a href="<?php echo $list_link; ?>" class="list-section">

            <?php if($total_count > 1): ?>

                <div class="total-badge"><?php echo $total_count; ?></div>

            <?php endif; ?>

            <i class="fas fa-list-ul list-icon"></i>

            <span class="list-text">طلباتي</span>

        </a>

    </div>

    <?php 

        } // End if !empty

    } // End session check

    ?>

    <!-- ===================== هيرو سلايدر ===================== -->

    <section class="hero-slider-section" id="hero-slider">

        <!-- الصور هنا يجب أن تكون موجودة في المسار المحدد -->

        <div class="hero-slide active" style="background-image: url('image/offers/1.png');"></div>

        <div class="hero-slide" style="background-image: url('image/offers/2.png');"></div>

        <div class="hero-slide" style="background-image: url('image/offers/3.png');"></div>

        

        <div class="hero-overlay"></div>

        <div class="hero-content">

            <h2>اكتشف العروض المميزة</h2>

            <p>كل ما تحتاجه في مكان واحد</p>

        </div>

    </section>



    <!-- ===================== شبكة الخدمات الرئيسية ===================== -->

    <section class="services-grid-section">

        <div class="services-grid">

            <a href="listings.php?type=delivery" class="service-card">

                <!-- <div class="serv-icon red"><i class="fas fa-motorcycle"></i></div> -->
<div class="serv-icon orange" style="background-color: #FF8C00 !important; color: white; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
    
    <i class="fas fa-store" style="font-size: 24px;"></i>

</div>
                <h3 style="color: #FF8C00; font-family: sans-serif;">المتاجر</h3>

            </a>


            <!-- <a href="ads.php" class="service-card">

                <div class="serv-icon green"><i class="fas fa-bullhorn"></i></div>

                <h3>إعلانات</h3>

            </a> -->
<a href="ads.php" class="service-card" style="text-decoration: none; text-align: center; display: block;">
    
    <div class="serv-icon orange" style="
        background-color: #1500ff !important; 
        color: white !important; 
        width: 70px; 
        height: 70px; 
        border-radius: 50%; /* هذا السطر هو المسؤول عن جعلها دائرية */
        display: flex; 
        align-items: center; 
        justify-content: center; 
        margin: 0 auto 10px auto; 
        box-shadow: 0 4px 8px rgba(0,0,0,0.1); /* إضافة ظل خفيف ليعطي طابعاً احترافياً */
    ">
        <i class="fas fa-bullhorn" style="font-size: 28px;"></i>
    </div>

    <h3 style="color: #FF8C00; font-family: sans-serif;">إعلانات</h3>
</a>
        </div>

    </section>

    <!-- 1. قسم محلات الأكل -->

    <section class="scrolling-section">

        <div class="section-header">

            <h2><i class="fas fa-utensils" style="color:#ff6d00;"></i> أشهر المطاعم</h2>

            <a href="listings.php?type=delivery" class="view-all-btn">عرض الكل</a>

        </div>

        

        <div class="scroll-track-container">

            <div class="scroll-track">

                <a href="profile.php?id=43" class="fb-card">

                    <div class="fb-card-cover" style="background-image: url('image/stores/testyfood1.jpg');">

                        <div class="cover-overlay-text">وجبات مميزة</div>

                    </div>

                    <div class="fb-card-body">

                        <div class="fb-profile-img">

                            <img src="image/stores/testyfood.jpg" alt="Logo" onerror="this.src='image/default_store.png'">

                        </div>

                        <div class="fb-info">

                            <h3 class="store-name">TASTY FOOD</h3>

                            <p class="store-desc">أشهى المأكولات الشرقية والغربية</p>

                            <div class="rating-stars">

                                <i class="fas fa-star"></i> 5.0

                            </div>

                        </div>

                    </div>

                </a>



                <a href="profile.php?id=40" class="fb-card">

                    <div class="fb-card-cover" style="background-image: url('image/stores/sef1.jpg');">

                        <div class="cover-overlay-text">أشهى المؤكولات السريعة</div>

                        <!-- <div class="discount-badge">خصم 20%</div> -->

                    </div>

                    <div class="fb-card-body">

                        <div class="fb-profile-img">

                            <img src="image/stores/seflogo.jpg" alt="Logo" onerror="this.src='image/default_store.png'">

                        </div>

                        <div class="fb-info">

                            <h3 class="store-name">السيف</h3>

                            <p class="store-desc">مطعم السيف للوجبات السريعة، نقدم لكم مختلف الوجبات الشهية</p>

                            <div class="rating-stars">

                                <i class="fas fa-star"></i> 5.0

                            </div>

                        </div>

                    </div>

                </a>



                <a href="profile.php?id=32" class="fb-card">

                    <div class="fb-card-cover" style="background-image: url('image/stores/jaj1.jpg');">

                        <div class="discount-badge new">جديد</div>

                    </div>

                    <div class="fb-card-body">

                        <div class="fb-profile-img">

                            <img src="image/stores/jajlogo.jpg" alt="Logo" onerror="this.src='image/default_store.png'">

                        </div>

                        <div class="fb-info">

                            <h3 class="store-name">جاج وتوم</h3>

                            <p class="store-desc">شاورما مميزة وبأسعار منخفضة ولفترة محدودة</p>

                            <div class="rating-stars">

                                <i class="fas fa-star"></i> 4.9

                            </div>

                        </div>

                    </div>

                </a>



                <a href="profile.php?id=38" class="fb-card">

                    <div class="fb-card-cover" style="background-image: url('image/stores/sltan1.jpg');">

                        <div class="cover-overlay-text">المذاق الأول في الحلويات</div>

                        <div class="discount-badge">خصم 20%</div>

                    </div>

                    <div class="fb-card-body">

                        <div class="fb-profile-img">

                            <img src="image/stores/sltanlogo.jpg" alt="Logo" onerror="this.src='image/default_store.png'">

                        </div>

                        <div class="fb-info">

                            <h3 class="store-name">سلطان الحلو فرع 2</h3>

                            <p class="store-desc">حلويات شرقية بمذاق فاخر</p>

                            <div class="rating-stars">

                                <i class="fas fa-star"></i> 4.7

                            </div>

                        </div>

                    </div>

                </a>



                <a href="profile.php?id=32" class="fb-card">

                    <div class="fb-card-cover" style="background-image: url('image/stores/pizza1.png');">

                        <div class="discount-badge new">جديد</div>

                    </div>

                    <div class="fb-card-body">

                        <div class="fb-profile-img">

                            <img src="image/stores/pizzalogo.jpg" alt="Logo" onerror="this.src='image/default_store.png'">

                        </div>

                        <div class="fb-info">

                            <h3 class="store-name">بيتزا ومعجنات عالماشي</h3>

                            <p class="store-desc">الهندسة المتقدمة. تنتج أشهى انواع البيتزا ولمناقيش🍕🍕</p>

                            <div class="rating-stars">

                                <i class="fas fa-star"></i> 5.0

                            </div>

                        </div>

                    </div>

                </a>

            </div>

        </div>

    </section>
    <!-- 3. قسم الإعلانات المبوبة -->

    <section class="scrolling-section">

        <div class="section-header">

            <h2><i class="fas fa-tags" style="color:#4caf50;"></i>إعلانات اليوم (إعلانات)</h2>

            <a href="ads.php" class="view-all-btn">كل الإعلانات</a>

        </div>

        

        <div class="scroll-track-container">

            <div class="scroll-track">

                <a href="ad_details.php?id=41" class="fb-card">

                    <div class="fb-card-cover" style="background-image: url('image/ads/car1.jpg');">

                        <div class="discount-badge blue">للبيع</div>

                    </div>

                    <div class="fb-card-body">

                        <div class="fb-profile-img">

                            <!-- صورة المعلن -->

                            <img src="image/ads/car1.jpg" alt="User" onerror="this.src='image/default_ad.png'">

                        </div>

                        <div class="fb-info">

                            <h3 class="store-name">سيارة مارسيدس E350 - 2012</h3>

                            <p class="store-desc">سيارات - حمص</p>

                            <p class="price-text">12500$</p>

                        </div>

                    </div>

                </a>



                <a href="ad_details.php?id=52" class="fb-card">

                    <div class="fb-card-cover" style="background-image: url('image/ads/minelaplogo.png');">

                        <div class="discount-badge blue">للبيع</div>

                    </div>

                    <div class="fb-card-body">

                        <div class="fb-profile-img">

                            <img src="image/ads/minlap.jpg" alt="User" onerror="this.src='image/default_ad.png'">

                        </div>

                        <div class="fb-info">

                            <h3 class="store-name">Vanquish 540 pro</h3>

                            <p class="store-desc">جهاز كشف الذهب - دمشق</p>

                            <p class="price-text">750$</p>

                        </div>

                    </div>

                </a>



                <a href="ad_details.php?id=3" class="fb-card">

                    <div class="fb-card-cover" style="background-image: url('image/ads/car2.jpg');">

                        <div class="discount-badge blue">للبيع</div>

                    </div>

                    <div class="fb-card-body">

                        <div class="fb-profile-img">

                            <!-- صورة المعلن -->

                            <img src="image/ads/car2.jpg" alt="User" onerror="this.src='image/default_ad.png'">

                        </div>

                        <div class="fb-info">

                            <h3 class="store-name">مارسيدس 2015 E-350</h3>

                            <p class="store-desc">سيارات - حمص</p>

                            <p class="price-text">16000$</p>

                        </div>

                    </div>

                </a>



            </div>

        </div>

    </section>



    <!-- ===================== بنرات الانضمام ===================== -->

    <section class="join-us-container">

        <!-- <a href="driver_register.php" class="join-banner driver">

            <div class="join-text">

                <h3>انضم لفريق التوصيل</h3>

                <p>كن مدير نفسك وحقق دخلاً إضافياً</p>

            </div>

            <i class="fas fa-motorcycle join-icon"></i>

        </a> -->



        <a href="add_business_user.php" class="join-banner partner">

            <div class="join-text">

                <h3>سجل متجرك معنا</h3>

                <p>ضاعف مبيعاتك واوصل لآلاف الزبائن</p>

            </div>

            <i class="fas fa-store join-icon"></i>

        </a>

    </section>



    <!-- ===================== ملف الجافا سكريبت الخارجي ===================== -->

    <script src="js/landing_page_custom.js"></script>



</body>

</html>