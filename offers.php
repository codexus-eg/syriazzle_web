<?php require_once 'php/db_connect.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عروض Syriazzle الحصرية</title>
    <link rel="icon" href="image/favicon.png" type="image/png">

    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    
    <!-- Page Stylesheets -->
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/offers_page.css"> <!-- ملف التصميم الجديد -->
</head>
<body>

    <?php include 'header_store.php'; ?>

    <main class="offers-wrapper">
        <div class="container">
            <div class="coming-soon-content">
                <div class="icon-container">
                    <i class="fas fa-tags"></i>
                </div>
                <h1>عروض Syriazzle الحصرية... قريبًا!</h1>
                <p>نعمل حاليًا على تجهيز باقة من أقوى العروض والخصومات التي لم تشهدها من قبل، بالتعاون مع أفضل شركائنا في سوريا.</p>
                <div class="countdown-container">
                    <h3>ترقبوا الإطلاق الكبير</h3>
                    <div id="countdown" class="countdown-timer">
                        <div class="timer-box"><span id="days">00</span>أيام</div>
                        <div class="timer-box"><span id="hours">00</span>ساعات</div>
                        <div class="timer-box"><span id="minutes">00</span>دقائق</div>
                        <div class="timer-box"><span id="seconds">00</span>ثواني</div>
                    </div>
                </div>
                <div class="subscribe-form">
                    <p>كن أول من يعلم! أدخل بريدك الإلكتروني وسنرسل لك إشعارًا فور إطلاق العروض.</p>
                    <form action="#" method="POST">
                        <input type="email" placeholder="أدخل بريدك الإلكتروني هنا..." required>
                        <button type="submit">أعلمني!</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Set the date we're counting down to
        const countDownDate = new Date("Dec 1, 2025 15:37:25").getTime();

        const x = setInterval(function() {
            const now = new Date().getTime();
            const distance = countDownDate - now;

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("days").innerText = days.toString().padStart(2, '0');
            document.getElementById("hours").innerText = hours.toString().padStart(2, '0');
            document.getElementById("minutes").innerText = minutes.toString().padStart(2, '0');
            document.getElementById("seconds").innerText = seconds.toString().padStart(2, '0');

            if (distance < 0) {
                clearInterval(x);
                document.getElementById("countdown").innerHTML = "لقد تم إطلاق العروض!";
            }
        }, 1000);
    </script>
</body>
</html>