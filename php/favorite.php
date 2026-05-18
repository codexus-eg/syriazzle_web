<?php

session_start();

require_once 'db_connect.php'; // يجب أن يكون قبل auth_check إذا كان auth_check يعتمد عليه

require_once 'auth_check.php'; // تم نقل هذا السطر ليتم تنفيذه مبكرًا لإعادة إنشاء الجلسة



if (!isset($_SESSION['user_id'])) {

    header('Location: login.php');

    exit;

}



$user_id = (int)$_SESSION['user_id'];



try {

    // جلب الإعلانات المفضلة

    $stmt = $pdo->prepare("

        SELECT fs.*, f.user_id

        FROM favorites f

        JOIN form_submissions fs ON f.ad_id = fs.id

        WHERE f.user_id = :user_id

        ORDER BY fs.submitted_at DESC

    ");

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    $stmt->execute();

    $ads_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);



    $favorites = [];

    foreach ($ads_from_db as $ad) {

        $data = json_decode($ad['json_data'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            error_log("JSON Decode Error for ad ID: " . $ad['id'] . " - " . json_last_error_msg());

            continue; // تخطي الإعلان إذا كان الـ JSON تالفًا

        }



        $data['id'] = $ad['id'];

        $data['submitted_at'] = $ad['submitted_at'];

        $data['category'] = $ad['category'];

        $data['sub'] = $ad['sub'];

        $data['subsub'] = $ad['subsub'];

        $data['subsubsub'] = $ad['subsubsub'] ?? null;

        $data['السعر'] = $data['السعر']  ?? null;

        $data['is_favorited'] = true;



        // إضافة المسار الرئيسي للصورة من مصفوفة 'images' داخل الـ JSON

        $data['main_image_url'] = $data['images'][0] ?? null; 

        

        // *** بداية منطق إنشاء عنوان البطاقة (cardTitle) من fetch_ads.php ***

        $excludedForFirstPart = [

            'id', 'submitted_at', 'category', 'sub', 'subsub', 'subsubsub',

            'الصورة', 'السعر', 'المحافظة', 'رقم الهاتف', 'رقم الواتس',

            'الوصف', 'الميزات', 'images', 'is_favorited', 'user_id',

            'التصنيف' 

        ];



        $mainTitlePart = '';

        $subSubPart = '';

        $cardTitle = ''; // This will hold the final title



        // 1. Find the first dynamic text field for the first part of the title

        foreach ($data as $key => $value) {

            if (!in_array($key, $excludedForFirstPart) &&

                $value !== null && trim((string)$value) !== '' &&

                !is_numeric($value)) { // Check if it's not numeric

                $mainTitlePart = (string)$value;

                break;

            }

        }



        // 2. Check subsub for the supplementary part

        if (isset($data['subsub']) && trim((string)$data['subsub']) !== '') {

            $subSubPart = (string)$data['subsub'];

        }



        // 3. Build the final title based on the found parts

        if ($mainTitlePart && $subSubPart) {

            $cardTitle = $mainTitlePart . ' ' . $subSubPart;

        } elseif ($mainTitlePart) {

            $cardTitle = $mainTitlePart;

        } elseif ($subSubPart) {

            $cardTitle = $subSubPart;

        } else {

            // 4. If none of the above parts exist, fallback to the category hierarchy as the sole title

            if (isset($data['subsubsub']) && trim((string)$data['subsubsub']) !== '') {

                $cardTitle = (string)$data['subsubsub'];

            } elseif (isset($data['subsub']) && trim((string)$data['subsub']) !== '') {

                $cardTitle = (string)$data['subsub'];

            } elseif (isset($data['sub']) && trim((string)$data['sub']) !== '') {

                $cardTitle = (string)$data['sub'];

            } elseif (isset($data['category']) && trim((string)$data['category']) !== '') {

                $cardTitle = (string)$data['category'];

            } else {

                $cardTitle = 'إعلان بدون عنوان';

            }

        }



        if (isset($data['category']) && $data['category'] === 'مركبات' && isset($data['نوع الوقود']) && trim((string)$data['نوع الوقود']) !== '') {

            $cardTitle .= ' - ' . (string)$data['نوع الوقود'];

        }



        $data['card_title'] = $cardTitle; // إضافة العنوان النهائي إلى مصفوفة البيانات

        // *** نهاية منطق إنشاء عنوان البطاقة ***

        

        $favorites[] = $data;

    }



} catch (PDOException $e) {

    die("خطأ في الاتصال: " . $e->getMessage());

}

?>

<!DOCTYPE html>

<html lang="ar">

    <head>

        <meta charset="UTF-8">

        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>إعلاناتي المفضلة</title>

        <style>

            body { 

                font-family: Arial;

                direction: rtl;

                background: #f9f9f9;

                padding: 20px; 

            }

            div#favorites-container {

                display: grid;

                grid-template-columns: repeat(auto-fit, minmax(154px, 1fr));

                gap: 15px; 

            }

            .child { 

                background: white;

                padding: 10px;

                border-radius: 8px;

                box-shadow: 0 0 5px rgba(0,0,0,0.1);

                position: relative;

            }

            .info-row, .actions-row { 

                margin-top: 10px;

                display: flex;

                justify-content: space-between;

                flex-wrap: wrap; 

            }

            .btn-call, .btn-whatsapp, .favorite-btn {

                padding: 8px 10px;

                border: none;

                border-radius: 4px;

                cursor: pointer;

                font-size: 14px;

            }

            .btn-call { 

                background-color: #28a745;

                color: white;

                text-decoration: none; 

            }

            .btn-whatsapp {

                background-color: #25D366;

                color: white;

                text-decoration: none; 

            }

            .favorite-btn {

                background-color: transparent;

                color: red;

                font-size: 18px; 

            }

            .favorite-btn i.far {

                color: gray;

            }

        </style>

            <link rel="stylesheet" href="../css/fetch_ads.css">

            <link rel="stylesheet" href="../css/normalize.css">

            <link rel="stylesheet" href="../css/dubizzle-inspired.css">

            <link rel="stylesheet" href="../css/style.css">

            <link rel="stylesheet" href="../css/all.min.css">
            <link rel="stylesheet" href="../css/main_header.css">
            <link rel="stylesheet" href="../image/logo1.png">

            <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

    </head>

    <body>

        <?php include 'header_store.php'; ?>

        <div class="container">

            <div class="favorite_style">

                <div class="favoritestyle">

                    <h1>الإعلانات المفضلة</h1>

                    <div id="favorites-container" class="ads-container"></div>

                </div>

            </div>

        </div>
        <footer class="mobile-footer-nav">
            <a href="../ads.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>الرئيسية</span>
                <div class="nav-loader"></div>
            </a>
            <a href="../my-ads.php" class="nav-item protected-link">
                <i class="fas fa-layer-group"></i>
                <span>إعلاناتي</span>
                <div class="nav-loader"></div>
            </a>
            <a href="../ads_new.php" class="nav-item add-ad-button protected-link">
                <i class="fas fa-plus-circle"></i>
                <span>أضف إعلان</span>
                <div class="nav-loader"></div>
            </a>
            <a href="php/favorite.php" class="nav-item protected-link">
                <i class="fas fa-heart"></i>
                <span>المفضلة</span>
                <div class="nav-loader"></div>
            </a>
            <a href="../account.php" class="nav-item" id="account-link-mobile">
                <!-- سيتم تغيير الأيقونة والنص بواسطة JS -->
                <div class="nav-loader"></div>
            </a>
        </footer>
    
    <script src="../js/main.js" ></script>
    
    <script>

        const favoriteAds = <?= json_encode($favorites, JSON_UNESCAPED_UNICODE); ?>;



        const container = document.getElementById('favorites-container');



        if (favoriteAds.length === 0) {

            container.innerHTML = '<p style="text-align:center;">لا يوجد إعلانات مفضلة حالياً.</p>';

        }



        favoriteAds.forEach(adData => {

            let imageUrl;

            // استخدام main_image_url الذي أضفناه في PHP

            const mainImageFromData = adData.main_image_url; 



            if (mainImageFromData) {

                // بناء المسار كمسار مطلق من جذر المحافظة بناءً على تنسيق "uploads/filename.jpg"

                imageUrl = `/${mainImageFromData}`; 

            } else {

                imageUrl = 'placeholder.jpg'; // صورة احتياطية إذا لم تكن هناك صورة

            }

            // *** استخدام card_title الذي تم حسابه في PHP ***

            const adCardTitle = adData.card_title || 'إعلان بدون عنوان';

            const whatsappNumber = adData['رقم الواتس'] || '';

            const adId = adData.id;

            const adLink = `${window.location.origin}/ad_details.php?id=${adId}`;
            const encodedMessage = encodeURIComponent(`لقد قرأت إعلانك على موقع Syriazzle بخصوص '${adCardTitle}'. رابط إعلانك هو:\n ${adLink}`);

             const adCardHTML = `

                <div class="child" data-ad-id="${adData.id}">

                    <img src="${imageUrl}" alt="صورة الإعلان">

                    <h3>${adCardTitle}</h3>

                    <div class="info-row">

                        <p class="price">${adData['السعر']}</p>

                        <p class="location">${adData['المحافظة'] || ''}</p>

                        <p class="date">${adData['submitted_at'] ? new Date(adData['submitted_at']).toLocaleDateString('ar-SY') : ''}</p>

                    </div>

                    <div class="actions-row">

                        <a href="tel:${adData['رقم الهاتف'] || ''}" class="btn-call">اتصال</a>

                        <a href="https://wa.me/${whatsappNumber}?text=${encodedMessage}" target="_blank" class="btn-whatsapp">واتساب</a>

                        

                        <button class="btn-message" data-ad-id="${adData.id}" data-owner-id="${adData.user_id}">

                            <i class="fas fa-comments"></i> مراسلة 

                        </button>

                        

                        <button class="favorite-btn ${adData.is_favorited ? 'is-favorite' : ''}" data-ad-id="${adData.id}">

                            <i class="${adData.is_favorited ? 'fas' : 'far'} fa-heart"></i>

                        </button>

                    </div>

                </div>`;



            // إضافة البطاقة إلى الحاوية

            container.insertAdjacentHTML('beforeend', adCardHTML);



            // *** إضافة مستمع لحدث النقر لبطاقة الإعلان بالكامل ***

            // بما أننا أضفنا HTML للتو، يجب أن نجد العنصر الجديد

            const newAdCard = container.lastElementChild; // هذا سيعطينا أحدث 'child' تم إضافته



            newAdCard.addEventListener('click', function(event) {

                // منع النقر على الأزرار الداخلية من تفعيل فتح صفحة التفاصيل

                if (event.target.closest('.btn-call') || 

                    event.target.closest('.btn-whatsapp') || 

                    event.target.closest('.btn-message') || 

                    event.target.closest('.favorite-btn')) {

                    return; // لا تفعل شيئًا إذا تم النقر على زر

                }

                

                const clickedAdId = this.dataset.adId; // 'this' يشير إلى الـ div.child

                if (clickedAdId) {

                    window.location.href = `../ad_details.php?id=${clickedAdId}`;

                }

            });

        }); // نهاية forEach





        // حدث إزالة من المفضلة

        container.addEventListener('click', function (e) {

            if (e.target.closest('.favorite-btn')) {

                const btn = e.target.closest('.favorite-btn');

                const adId = btn.dataset.adId;



                fetch('toggle_favorite.php', {

                    method: 'POST',

                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },

                    body: 'ad_id=' + encodeURIComponent(adId)

                })

                .then(res => res.json())

                .then(data => {

                    if (data.success && data.action === 'removed') {

                        // إزالة البطاقة من الصفحة

                        const adCard = btn.closest('.child');

                        adCard.remove();



                        // عرض رسالة إذا اختفوا كل الإعلانات

                        if (container.children.length === 0) {

                            container.innerHTML = '<p style="text-align:center;">لا يوجد إعلانات مفضلة حالياً.</p>';

                        }

                    }

                });

            }

        });

    </script>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    </body>

</html>