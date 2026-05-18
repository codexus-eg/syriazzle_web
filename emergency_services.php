<?php require_once 'php/db_connect.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خدمات الطوارئ 24/7 - Syriazzle</title>
    <link rel="icon" href="image/favicon.png" type="image/png">

    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    
    <!-- Page Stylesheets -->
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/emergency_page.css">
</head>
<body>

    <?php include 'header_store.php'; ?>

    <main class="emergency-wrapper">
        <div class="emergency-hero">
            <div class="hero-overlay"></div>
            <div class="container hero-content">
                <i class="fas fa-shipping-fast hero-icon"></i>
                <h1>تحتاج مساعدة فورية؟</h1>
                <p>فريق Syriazzle للطوارئ جاهز لخدمتك على مدار 24 ساعة، 7 أيام في الأسبوع.</p>
            </div>
        </div>

        <div class="services-overview">
            <div class="container">
                <h2>نحن هنا من أجلك في كل الظروف</h2>
                <p class="section-subtitle">سواء كنت بحاجة إلى توصيل دواء عاجل، أو توصيل لمستشفى، أو أي غرض ضروري في وقت متأخر، نحن على بعد مكالمة واحدة.</p>
                <div class="services-grid">
                    <div class="service-item">
                        <i class="fas fa-pills"></i>
                        <h3>توصيل صيدلية</h3>
                        <p>توصيل الأدوية ومستلزمات الأطفال في أي وقت من الليل.</p>
                    </div>
                    <div class="service-item">
                        <i class="fas fa-car-side"></i>
                        <h3>مساعدة على الطريق</h3>
                        <p>مقطوع في الطريق؟ يمكننا إيصال الوقود أو المساعدة في الحالات البسيطة.</p>
                    </div>
                    <div class="service-item">
                        <i class="fas fa-utensils"></i>
                        <h3>طلبات متأخرة</h3>
                        <p>هل شعرت بالجوع في وقت متأخر؟ سنحضر لك ما تحتاجه من الأماكن المتاحة.</p>
                    </div>
                     <div class="service-item">
                        <i class="fas fa-first-aid"></i>
                        <h3>حالات طارئة</h3>
                        <p>توصيل سريع للمستشفيات أو تلبية أي احتياج عاجل آخر.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="contact-section">
            <div class="container">
                <h2>لا تتردد، اتصل بنا الآن!</h2>
                <p class="section-subtitle">فريقنا مدرب للتعامل مع الحالات المستعجلة بأولوية وسرعة قصوى.</p>
                <div class="contact-cards">
                    
                    <a href="tel:+963933863625" class="contact-card whatsapp">
                        <div class="contact-icon"><i class="fab fa-whatsapp"></i></div>
                        <div class="contact-info">
                            <h3>فريق الطوارئ (واتساب)</h3>
                            <span>0933863625</span>
                        </div>
                        <div class="action-arrow"><i class="fas fa-arrow-left"></i></div>
                    </a>

                    <a href="tel:+963958237170" class="contact-card call">
                        <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                        <div class="contact-info">
                            <h3>خط الاتصال المباشر</h3>
                            <span>0958237170</span>
                        </div>
                        <div class="action-arrow"><i class="fas fa-arrow-left"></i></div>
                    </a>

                    <a href="tel:+963933863625" class="contact-card support">
                        <div class="contact-icon"><i class="fas fa-headset"></i></div>
                        <div class="contact-info">
                            <h3>الدعم الفني والشكاوى</h3>
                            <span>0933863625</span>
                        </div>
                        <div class="action-arrow"><i class="fas fa-arrow-left"></i></div>
                    </a>

                </div>
            </div>
        </div>
    </main>

</body>
</html>