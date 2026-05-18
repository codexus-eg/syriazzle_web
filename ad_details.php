<?php

// 1. بدء الجلسة هو أهم خطوة ويجب أن تكون أولاً

session_start();



require_once 'php/db_connect.php'; 



// 2. استخراج معرف المستخدم الحالي والإعلان المطلوب بأمان

$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$ad_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$ad = null;



if ($ad_id > 0) {

    // 3. جلب بيانات الإعلان مع اسم المستخدم

    $stmt = $pdo->prepare("

        SELECT fs.*, u.username 

        FROM form_submissions fs

        LEFT JOIN users u ON fs.user_id = u.id

        WHERE fs.id = ?

    ");

    $stmt->execute([$ad_id]);

    $ad_data_db = $stmt->fetch(PDO::FETCH_ASSOC);



    if ($ad_data_db) {

        // 4. زيادة عدد المشاهدات (مرة واحدة لكل تحميل صفحة)

        $update_stmt = $pdo->prepare("UPDATE form_submissions SET views = views + 1 WHERE id = ?");

        $update_stmt->execute([$ad_id]);



        // 5. معالجة بيانات الإعلان للعرض

        $ad = json_decode($ad_data_db['json_data'], true);

        if (json_last_error() === JSON_ERROR_NONE) {

            // دمج البيانات من جدول قاعدة البيانات مع بيانات JSON

            $ad['id'] = (int)$ad_data_db['id'];

            $ad['submitted_at'] = $ad_data_db['submitted_at'];

            $ad['user_id'] = (int)$ad_data_db['user_id'];

            $ad['username'] = $ad_data_db['username'] ?? 'مستخدم غير معروف';

            $ad['category'] = $ad_data_db['category'];

            $ad['sub'] = $ad_data_db['sub'];

            $ad['subsub'] = $ad_data_db['subsub'];

            $ad['subsubsub'] = $ad_data_db['subsubsub'] ?? null;

            $ad['السعر'] = $ad['السعر'] ?? 'غير محدد';

            $ad['مشاهدات'] = (int)$ad_data_db['views'] + 1; // إضافة المشاهدة الحالية للعرض

            

            // استخراج مسارات الصور من قاعدة البيانات وهو المصدر الموثوق

            $ad['images'] = json_decode($ad_data_db['images_paths'], true) ?? [];

            

            // 6. منطق توليد العنوان

            $display_title_value = $ad['العنوان'] ?? $ad['subsubsub'] ?? $ad['subsub'] ?? $ad['sub'] ?? $ad['category'] ?? 'تفاصيل الإعلان';

            $fuel_type_value = $ad['نوع الوقود'] ?? '';



        } else {

            // في حال كان JSON تالفًا

            $ad = null;

        }

    }

}



// 7. إذا لم يتم العثور على الإعلان، يتم توجيه المستخدم لصفحة الخطأ

if (!$ad) {

    header("Location: error_page.php?code=404"); 

    exit();

}



// 8. التحقق مما إذا كان المستخدم الحالي هو مالك الإعلان

$is_owner = ($current_user_id > 0 && $current_user_id === $ad['user_id']);



// 9. تجهيز رابط ورسالة الواتساب

$whatsapp_number_sanitized = '';

$encoded_whatsapp_message = '';

if (!empty($ad['رقم الواتس'])) {

    $whatsapp_number_sanitized = preg_replace('/[^0-9+]/', '', $ad['رقم الواتس']);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";

    $ad_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    $whatsapp_message = "أهلاً، شاهدت إعلانك '" . htmlspecialchars($display_title_value) . "' على موقعكم وهذا رابطه: " . $ad_link;

    $encoded_whatsapp_message = urlencode($whatsapp_message);

}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($display_title_value); ?></title>

    <link rel="icon" href="image/favicon.png" type="image/png">

    <link rel="stylesheet" href="css/style.css"> 

    <link rel="stylesheet" href="css/all.min.css">

    <link rel="stylesheet" href="css/main_header.css">

    <link rel="stylesheet" href="css/dubizzle-inspired.css">

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>

        /* كامل كود الـ CSS الذي قدمته يتم وضعه هنا */

        body { background-color: #f4f5f7; font-family: "Cairo", sans-serif; margin: 0; padding: 0; direction: rtl; text-align: right; }

        .ad-details-main { flex: 3; background-color: #fff; margin-top: 30px; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }

        .ad-details-sidebar { flex: 1; min-width: 300px; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); align-self: flex-start; }

        .ad-title-price { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 15px; flex-wrap: wrap; }

        .ad-title-price h1 { font-size: 2.2rem; color: #333; margin: 0; flex-basis: 70%; }

        .ad-title-price .price { font-size: 20px; color: #ee570c; font-weight: bold; flex-basis: 28%; text-align: left; }

        .image-carousel { position: relative; width: 100%; height: 450px; background-color: #f0f0f0; display: flex; justify-content: center; align-items: center; overflow: hidden; border-radius: 8px; margin-bottom: 15px; }

        .image-carousel img { max-width: 100%; max-height: 100%; object-fit: contain; display: none; }

        .image-carousel img.active { display: block; }

        .nav-arrow { position: absolute; top: 50%; transform: translateY(-50%); background-color: rgba(0, 0, 0, 0.5); color: #fff; border: none; padding: 10px 15px; cursor: pointer; z-index: 10; border-radius: 50%; font-size: 1.5rem; }

        .nav-arrow.left { right: 10px; }

        .nav-arrow.right { left: 10px; }

        .image-count { position: absolute; bottom: 10px; left: 10px; background-color: rgba(0, 0, 0, 0.6); color: #fff; padding: 5px 10px; border-radius: 22px; font-size: 0.9rem; }

        .thumbnails-gallery { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; justify-content: center; }

        .thumbnails-gallery img { width: 80px; height: 60px; object-fit: cover; border: 2px solid transparent; border-radius: 4px; cursor: pointer; transition: border-color 0.3s; }

        .thumbnails-gallery img.active, .thumbnails-gallery img:hover { border-color: #ec6212; }

        .ad-section { background-color: #f9f9f9; border-radius: 8px; padding: 15px; margin-bottom: 20px; border: 1px solid #eee; }

        .ad-section h2 { margin-top: 0; color: #333; font-size: 1.5rem; border-bottom: 2px solid #e55b17; padding-bottom: 10px; margin-bottom: 15px; }

        .ad-section h2 i { margin-left: 10px; color: #e07d27; }

        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px 20px; }

        .detail-item { display: flex; justify-content: space-between; gap: 5px; font-size: 1rem; background-color: white; padding: 12px; box-shadow: 0 4px 10px #ddd; border-radius: 10px; }

        .detail-item span:first-child { color: #0008ff; font-weight: bold; }

        .detail-item span:last-child { color: #333; }

        .description-content { line-height: 1.6; color: #555; white-space: pre-wrap; }

        .tags-container { display: flex; flex-wrap: wrap; gap: 8px; }

        .tag { background-color: #ee7e29; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.9rem; }

        .contact-box { text-align: center; }

        .contact-box .owner-info { margin-bottom: 15px; font-size: 1.1rem; color: #333; }

        .btn-contact { display: flex; align-items: center; justify-content: center; padding: 12px 15px; margin-bottom: 10px; border-radius: 20px; border: none; text-decoration: none; font-weight: bold; font-size: 1.1rem; transition: background-color 0.3s ease; gap: 10px; }

        .btn-call { background-color: #007bff; color: #fff; }

        .btn-call:hover { background-color: #0056b3; }

        .btn-whatsapp { background-color: #25d366; color: #fff; }

        .btn-whatsapp:hover { background-color: #1da851; }

        .btn-message { background-color: #007bff; color: #fff; }

        .btn-message:hover { background-color: #0056b3; }

        .owner-actions { margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; text-align: center; display: flex; flex-direction: column; gap: 10px;}

        .owner-actions .owner-btn { color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 1rem; transition: background-color 0.3s ease; text-decoration: none; display: inline-block;}

        .owner-actions .edit-btn { background-color: #f0ad4e; }

        .owner-actions .edit-btn:hover { background-color: #ec971f; }

        .owner-actions .delete-btn { background-color: #dc3545; }

        .owner-actions .delete-btn:hover { background-color: #c82333; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); justify-content: center; align-items: center; padding: 10px; }

        .modal-content { background-color: #fefefe; border-radius: 10px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); position: relative; animation-name: animatetop; animation-duration: 0.4s; display: flex; flex-direction: column; overflow: hidden; max-height: 95vh; width: 95%; max-width: 650px; }

        @keyframes animatetop { from { top: -300px; opacity: 0; } to { top: 0; opacity: 1; } }

        /* ... باقي كود CSS للمودال ... */

    </style>

</head>

<body>

    <?php include 'header_store.php'; ?>

    <div class="container" style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">

        <div class="ad-details-main">

            <div class="ad-title-price">

                <h1><?php echo htmlspecialchars($display_title_value); ?></h1>

                <span class="price"><?php echo htmlspecialchars($ad['السعر']); ?></span>

            </div>

            

            <div class="image-carousel">

                <?php if (!empty($ad['images'])): ?>

                    <?php foreach ($ad['images'] as $index => $image_path): ?>

                        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="صورة الإعلان" class="<?php echo $index === 0 ? 'active' : ''; ?>">

                    <?php endforeach; ?>

                    <button class="nav-arrow right" id="image-next"><i class="fas fa-chevron-right"></i></button>

                    <button class="nav-arrow left" id="image-prev"><i class="fas fa-chevron-left"></i></button>

                    <span class="image-count" id="image-count">1/<?php echo count($ad['images']); ?></span>

                <?php else: ?>

                    <img src="image/placeholder.png" alt="لا توجد صور" class="active">

                <?php endif; ?>

            </div>



            <?php if (!empty($ad['images']) && count($ad['images']) > 1): ?>

            <div class="thumbnails-gallery" id="thumbnails-gallery">

                <?php foreach ($ad['images'] as $index => $image_path): ?>

                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="مصغر" class="<?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>">

                <?php endforeach; ?>

            </div>

            <?php endif; ?>



            <div class="ad-section">

                <h2><i class="fas fa-info-circle"></i> تفاصيل الإعلان</h2>

                <div class="details-grid">

                    <?php

                    // الحقول التي لا نريد عرضها في هذا القسم

    $excluded_fields = ['id', 'user_id', 'username', 'submitted_at', 'images', 'الوصف', 'الميزات', 'category', 'sub', 'subsub', 'subsubsub', 'السعر', 'رقم الهاتف', 'رقم الواتس', 'العنوان', 'مشاهدات', 'path'];

                    foreach ($ad as $key => $value) {

                        if (!in_array($key, $excluded_fields) && !empty($value) && !is_array($value)) {

                            echo '<div class="detail-item"><span>' . htmlspecialchars($key) . '</span><span>' . htmlspecialchars($value) . '</span></div>';

                        }

                    }

                    ?>

                </div>

            </div>



            <?php if (!empty($ad['الوصف'])): ?>

            <div class="ad-section">

                <h2><i class="fas fa-file-alt"></i> الوصف</h2>

                <p class="description-content"><?php echo nl2br(htmlspecialchars($ad['الوصف'])); ?></p>

            </div>

            <?php endif; ?>

        </div> 



        <div class="ad-details-sidebar">

            <div class="contact-box">

                <div class="owner-info">

                    <a href="php/fetch_user_ads.php?user_id=<?php echo $ad['user_id']; ?>" style="text-decoration:none; color: inherit;">

                        <strong>الناشر:</strong> <?php echo htmlspecialchars($ad['username']); ?>

                    </a>

                </div>

                

                <?php if ($is_owner): ?>

                    <p style="color: #007bff; font-weight: bold; margin-bottom: 15px;">هذا إعلانك!</p>

                    <div class="owner-actions">

                        <!-- ✨✨ التعديل الرئيسي هنا ✨✨ -->

                        <a href="form.php?edit_id=<?php echo $ad['id']; ?>" class="owner-btn edit-btn">

                            <i class="fas fa-edit"></i> تعديل الإعلان

                        </a>

                        <button class="owner-btn delete-btn" onclick="deleteAd(<?php echo $ad['id']; ?>)">

                            <i class="fas fa-trash-alt"></i> حذف الإعلان

                        </button>

                    </div>

                <?php else: ?>

                    <?php if (!empty($ad['رقم الهاتف'])): ?>

                        <a href="tel:<?php echo htmlspecialchars($ad['رقم الهاتف']); ?>" class="btn-contact btn-call"><i class="fas fa-phone-alt"></i> إظهار الرقم</a>

                    <?php endif; ?>

                    <?php if (!empty($whatsapp_number_sanitized)): ?>

                        <a href="https://wa.me/<?php echo $whatsapp_number_sanitized; ?>?text=<?php echo $encoded_whatsapp_message; ?>" target="_blank" class="btn-contact btn-whatsapp"><i class="fab fa-whatsapp"></i> مراسلة واتساب</a>

                    <?php endif; ?>

                    <?php if ($current_user_id > 0): ?>

                        <button class="btn-contact btn-message" id="message-owner-btn"><i class="fas fa-comments"></i> مراسلة عبر الموقع</button>

                    <?php else: ?>

                        <p style="font-size: 0.9rem; color: #888; margin-top: 15px;">

                            <a href="login.php">سجّل الدخول</a> للمراسلة أو رؤية رقم الهاتف.

                        </p>

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        </div>

    </div> 



    <!-- مودال المراسلة -->

    <div id="messageModal" class="modal">

        <!-- ... محتوى المودال ... -->

    </div>

    <div class="overlay" id="overlay"></div>



    <script>

    // تمرير المتغيرات من PHP إلى JavaScript بأمان

    const currentLoggedInUserId = <?php echo json_encode($current_user_id); ?>; 

    const adId = <?php echo json_encode($ad_id); ?>;

    const adOwnerId = <?php echo json_encode($ad['user_id']); ?>;

    const adTitle = <?php echo json_encode($display_title_value); ?>;



    document.addEventListener('DOMContentLoaded', () => {

        // --- منطق عرض الصور (Carousel) ---

        const images = document.querySelectorAll('.image-carousel img');

        const thumbnails = document.querySelectorAll('.thumbnails-gallery img');

        const prevButton = document.getElementById('image-prev');

        const nextButton = document.getElementById('image-next');

        const imageCountSpan = document.getElementById('image-count');

        let currentIndex = 0;



        function showImage(index) {

            if (images.length === 0) return;

            images.forEach((img, i) => img.classList.toggle('active', i === index));

            thumbnails.forEach((thumb, i) => thumb.classList.toggle('active', i === index));

            if(imageCountSpan) imageCountSpan.textContent = `${index + 1}/${images.length}`;

            currentIndex = index;

        }



        if (images.length > 1) {

            if (prevButton) prevButton.addEventListener('click', () => showImage((currentIndex - 1 + images.length) % images.length));

            if (nextButton) nextButton.addEventListener('click', () => showImage((currentIndex + 1) % images.length));

            thumbnails.forEach(thumb => thumb.addEventListener('click', (e) => showImage(parseInt(e.target.dataset.index))));

        } else {

            if (prevButton) prevButton.style.display = 'none';

            if (nextButton) nextButton.style.display = 'none';

        }

        

        showImage(0);



        // --- منطق المراسلة ---

        const messageOwnerBtn = document.getElementById('message-owner-btn');

        if (messageOwnerBtn) {

            messageOwnerBtn.addEventListener('click', () => {

                if (currentLoggedInUserId === 0) {

                    alert('يجب تسجيل الدخول لتتمكن من مراسلة صاحب الإعلان.');

                    window.location.href = 'login.php';

                    return;

                }

                // هنا تضع كود فتح مودال المراسلة

                // openMessageModal(adId, adOwnerId, adTitle);

                alert("سيتم فتح نافذة المراسلة هنا.");

            });

        }

    });



    // --- دالة حذف الإعلان ---

    async function deleteAd(adIdToDelete) {

        if (!confirm('هل أنت متأكد أنك تريد حذف هذا الإعلان؟ لا يمكن التراجع عن هذا الإجراء.')) {

            return;

        }

        try {

            const response = await fetch('php/delete_ad.php', {

                method: 'POST',

                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },

                body: `ad_id=${adIdToDelete}` // ✨ تم التصحيح: إرسال ad_id

            });

            const data = await response.json();

            if (data.success) {

                alert(data.message || 'تم حذف الإعلان بنجاح.');

                window.location.href = 'my-ads.php'; 

            } else {

                alert('فشل حذف الإعلان: ' + (data.error || 'خطأ غير معروف.'));

            }

        } catch (error) {

            alert('حدث خطأ غير متوقع أثناء محاولة حذف الإعلان.');

            console.error('Error deleting ad:', error);

        }

    }

    </script>

</body>

</html>