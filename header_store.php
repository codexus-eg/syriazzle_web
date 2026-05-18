<?php

// ========================================================================

// Syriazzle - Ultimate Fixed Header (Production Ready - V7.0)

// ========================================================================



// 1. تحديد بيانات الجلسة ونوع المستخدم بدقة عالية

$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['driver_id']) || isset($_SESSION['business_id']);



$h_uid = 0;

$h_uname = 'زائر';

$h_utype = 'guest';

$h_avatar = null;



if ($isLoggedIn) {

    if (isset($_SESSION['driver_id'])) {

        $h_uid = (int)$_SESSION['driver_id'];

        $h_uname = $_SESSION['driver_name'] ?? 'كابتن';

        $h_utype = 'driver';

    } elseif (isset($_SESSION['business_id'])) {

        $h_uid = (int)$_SESSION['business_id'];

        $h_uname = $_SESSION['business_name'] ?? 'متجر';

        $h_utype = 'business';

    } else {

        $h_uid = (int)$_SESSION['user_id'];

        $h_uname = $_SESSION['username'] ?? 'مستخدم';

        $h_utype = 'user';

    }

    $h_avatar = $_SESSION['profile_image'] ?? null;

}



// 2. جلب عدد الإشعارات غير المقروءة لحظياً

$h_unread_count = 0;

if ($isLoggedIn) {

    try {

        $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM site_notifications WHERE user_id = ? AND user_type = ? AND is_read = 0");

        $stmt_c->execute([$h_uid, $h_utype]);

        $h_unread_count = (int)$stmt_c->fetchColumn();

    } catch (Exception $e) { $h_unread_count = 0; }

}

?>



<!-- استدعاء الخط العربي والمكتبات -->

<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">



<style>

    /* إلغاء تأخير اللمس وجعل الموقع يستجيب كأنه Native App */

    html, body {

        touch-action: manipulation;

        -webkit-tap-highlight-color: transparent;

    }

    /* إعطاء تلميح بصرى فوري عند اللمس */

    a:active, button:active {

        opacity: 0.7;

        transition: opacity 0.1s;

    }

    :root {

        --sy-red: #e67f00;

        --sy-blue: #007bff;

        --sy-dark: #2d3436;

        --sy-light: #f1f2f6;

        --h-height: 70px;

    }



    /* إلغاء تأخير اللمس لسرعة التطبيق */

    a, button, .pill-trigger, .icon-circle-btn {

        touch-action: manipulation;

        -webkit-tap-highlight-color: rgba(0,123,255,0.05);

    }



    .sy-global-header {

        position: fixed; top: 0; left: 0; right: 0; height: var(--h-height);

        background: #ffffff; z-index: 99999; direction: rtl;

        box-shadow: 0 2px 15px rgba(0,0,0,0.08); display: flex; align-items: center;

    }



    /* دفع المحتوى للأسفل لضمان عدم الاختفاء خلف الهيدر */

    body { padding-top: var(--h-height); background-color: #f8f9fa; font-family: 'Cairo', sans-serif; }



    .h-content-wrap {

        width: 100%; max-width: 1300px; margin: 0 auto; padding: 0 12px;

        display: flex; justify-content: space-between; align-items: center;

    }



    /* اليمين: زر الرجوع + اللوغو */

    .h-group-right { display: flex; align-items: center; gap: 12px; }

    .icon-circle-btn {

        width: 40px; height: 40px; background: var(--sy-light); border: none;

        border-radius: 50%; color: var(--sy-dark); cursor: pointer;

        display: flex; align-items: center; justify-content: center; transition: 0.2s;

    }

    .icon-circle-btn:active { background: #dfe4ea; transform: scale(0.92); }

    .h-logo-img { height: 42px; width: auto; display: block; }



    /* اليسار: الإجراءات */

    .h-group-left { display: flex; align-items: center; gap: 10px; }



    /* الإشعارات */

    .notif-box { position: relative; }

    .notif-badge-pill {

        position: absolute; top: -3px; right: -3px; background: var(--sy-red);

        color: #fff; font-size: 9px; min-width: 17px; height: 17px;

        border-radius: 10px; display: flex; align-items: center; justify-content: center;

        border: 2px solid #fff; font-weight: 800;

    }



    /* كبسولة المستخدم */

    .pill-trigger {

        display: flex; align-items: center; gap: 8px; background: var(--sy-light);

        padding: 5px 12px 5px 6px; border-radius: 25px; cursor: pointer;

        border: 1px solid transparent; transition: 0.2s;

    }

    .pill-trigger:hover { background: #fff; border-color: #ddd; }

    .avatar-sm-h {

        width: 30px; height: 30px; border-radius: 50%; object-fit: cover;

        background: var(--sy-blue); color: #fff; display: flex;

        align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem;

    }

    .uname-h { font-size: 0.85rem; font-weight: 700; color: var(--sy-dark); }



    /* زر إضافة المحسن */

    .btn-add-sy {

        background: linear-gradient(135deg, var(--sy-blue), #0056b3);

        color: #fff; border: none; padding: 10px 16px; border-radius: 12px;

        font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 8px;

        box-shadow: 0 4px 10px rgba(0,123,255,0.25); transition: 0.2s; font-size: 0.85rem;

    }



    /* القوائم المنسدلة الاحترافية */

    .sy-nav-drop {

        position: absolute; top: 55px; background: #fff; border-radius: 18px;

        box-shadow: 0 15px 45px rgba(0,0,0,0.18); display: none; overflow: hidden;

        border: 1px solid #f1f1f1; z-index: 100000; min-width: 230px;

        animation: dropSlide 0.25s ease-out;

    }

    @keyframes dropSlide { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    .sy-nav-drop.open { display: block; }



    /* التموضع الذكي */

    .notif-drop-pos { left: 0; width: 310px; max-width: calc(100vw - 24px); }

    .user-drop-pos { left: 40px; width: 220px; }

    .add-drop-pos { left: 0; width: 260px; }



    .drop-h-label { padding: 14px 18px; background: #fcfcfc; border-bottom: 1px solid #eee; font-weight: 800; font-size: 0.8rem; color: #666; display: flex; justify-content: space-between; }

    .drop-body-scroll { max-height: 380px; overflow-y: auto; }

    

    .drop-link-item {

        display: flex; align-items: center; gap: 12px; padding: 14px 18px;

        text-decoration: none; color: var(--sy-dark); font-size: 0.88rem;

        transition: 0.2s; border-bottom: 1px solid #f9f9f9;

    }

    .drop-link-item:hover { background: #f0f7ff; color: var(--sy-blue); }

    .drop-link-item i { width: 20px; color: var(--sy-blue); text-align: center; }

    .drop-link-item.unread-notif { background: #fff6f6; border-right: 4px solid var(--sy-red); }

    .drop-link-item.danger-link { color: var(--sy-red); }

    .drop-link-item.danger-link i { color: var(--sy-red); }



    /* استجابة الموبايل */

    @media (max-width: 600px) {

        .uname-h, .btn-add-sy span { display: none; }

        .btn-add-sy { padding: 10px; border-radius: 50%; }

        .sy-nav-drop { position: fixed; top: 75px; left: 12px !important; right: 12px !important; width: auto !important; }

        .h-logo-img { height: 36px; }

    }

</style>



<header class="sy-global-header">

    <div class="h-content-wrap">

        

        <!-- القسم الأيمن (ناقلات الحركة) -->

        <div class="h-group-right">

            <button class="icon-circle-btn" onclick="handleSmartBack()">

                <i class="fas fa-arrow-right"></i>

            </button>

            <a href="index.php">

                <img src="image/logo1.png" class="h-logo-img" alt="Syriazzle">

            </a>

        </div>



        <!-- القسم الأيسر (الأدوات) -->

        <div class="h-group-left">

            

            <?php if ($isLoggedIn): ?>

                <!-- إشعارات الهاتف والموقع -->

                <div class="notif-box">

                    <button class="icon-circle-btn" id="trigger-notif-btn">

                        <i class="far fa-bell"></i>

                        <?php if ($h_unread_count > 0): ?>

                            <span class="notif-badge-pill"><?php echo $h_unread_count; ?></span>

                        <?php endif; ?>

                    </button>

                    <div class="sy-nav-drop notif-drop-pos" id="drop-notif-menu">

                        <div class="drop-h-label">

                            <span><i class="fas fa-satellite-dish"></i> التنبيهات</span>

                            <span onclick="markAllAsRead()" style="color:var(--sy-blue); cursor:pointer;">تحديد كقروء</span>

                        </div>

                        <div class="drop-body-scroll" id="h-notif-loader">

                            <div style="padding:40px; text-align:center; color:#ccc;"><i class="fas fa-circle-notch fa-spin"></i></div>

                        </div>

                    </div>

                </div>



                <!-- حساب الشخص -->

                <div style="position: relative;">

                    <div class="pill-trigger" id="trigger-user-btn">

                        <div class="avatar-sm-h">

                            <?php if ($h_avatar): ?>

                                <img src="<?php echo $h_avatar; ?>" style="width:100%; height:100%; border-radius:50%;">

                            <?php else: ?>

                                <?php echo mb_substr($h_uname, 0, 1); ?>

                            <?php endif; ?>

                        </div>

                        <span class="uname-h"><?php echo $h_uname; ?></span>

                        <i class="fas fa-chevron-down" style="font-size:0.6rem; opacity:0.4;"></i>

                    </div>

                    <div class="sy-nav-drop user-drop-pos" id="drop-user-menu">

                        <?php if ($h_utype === 'driver'): ?>

                            <a href="driver_dashboard.php" class="drop-link-item"><i class="fas fa-motorcycle"></i> لوحة الكابتن</a>

                        <?php elseif ($h_utype === 'business'): ?>

                            <a href="business_dashboard.php" class="drop-link-item"><i class="fas fa-store"></i> لوحة المتجر</a>

                        <?php endif; ?>

                        <a href="account.php" class="drop-link-item"><i class="fas fa-user-shield"></i> حسابي الشخصي</a>

                        <a href="my_orders.php" class="drop-link-item"><i class="fas fa-receipt"></i> طلباتي</a>

                        <a href="php/logout.php" class="drop-link-item danger-link"><i class="fas fa-power-off"></i> تسجيل الخروج</a>

                    </div>

                </div>

            <?php else: ?>

                <a href="login.php" class="icon-circle-btn"><i class="fas fa-sign-in-alt"></i></a>

            <?php endif; ?>



            <!-- إضافة جديدة -->

            <div style="position: relative;">

                <button class="btn-add-sy" id="trigger-add-btn">

                    <i class="fas fa-plus-circle"></i>

                    <span>إضافة</span>

                    <i class="fas fa-chevron-down" style="font-size:0.6rem; opacity:0.6;"></i>

                </button>

                <div class="sy-nav-drop add-drop-pos" id="drop-add-menu">

                    <div class="drop-h-label">بماذا تود البدء؟</div>

                    <a href="ads_new.php" class="drop-link-item">

                        <i class="fas fa-bullhorn"></i>

                        <div><strong>إعلان تجاري</strong><p style="margin:0; font-size:0.7rem; color:#888;">بيع سيارة، عقار، أجهزة...</p></div>

                    </a>

                    <a href="add_business_user.php" class="drop-link-item">

                        <i class="fas fa-store-alt"></i>

                        <div><strong>متجر جديد</strong><p style="margin:0; font-size:0.7rem; color:#888;">مطعم، صيدلية، سوبرماركت...</p></div>

                    </a>

                    <!--<a href="add_booking_business.php" class="drop-link-item">-->

                    <!--    <i class="fas fa-calendar-check"></i>-->

                    <!--    <div><strong>نشاط حجوزات</strong><p style="margin:0; font-size:0.7rem; color:#888;">عيادات، فنادق، صالات أفراح...</p></div>-->

                    <!--</a>-->

                </div>

            </div>



        </div>

    </div>

</header>



<script>

// 1. منطق الرجوع الذكي للتكامل مع الأندرويد

function handleSmartBack() {

    const path = window.location.pathname;

    const search = window.location.search;



    // إذا كنا في الصفحة الرئيسية، نرسل إشارة للأندرويد لإظهار خروج

    if (path.endsWith('index.php') || path === '/' || path === '' || (path.includes('index.php') && search === '')) {

        return "SHOULD_EXIT"; 

    }



    // صفحات تتبع الطلبات تعود لقائمة الطلبات

    if (path.includes('track_order.php') || path.includes('order_details.php')) {

        window.location.href = 'my_orders.php';

        return "HANDLED";

    } 



    // لوحات التحكم تعود للرئيسية

    if (path.includes('_dashboard.php')) {

        window.location.href = 'index.php';

        return "HANDLED";

    }



    // العودة العادية في التاريخ

    if (window.history.length > 1) {

        window.history.back();

        return "HANDLED";

    }



    window.location.href = 'index.php';

    return "HANDLED";

}



// 2. إدارة فتح وإغلاق القوائم بلمسة واحدة

function setupHeaderDropdown(btnId, menuId) {

    const btn = document.getElementById(btnId);

    const menu = document.getElementById(menuId);

    if (!btn || !menu) return;



    btn.onclick = (e) => {

        e.stopPropagation();

        const isOpen = menu.classList.contains('open');

        // إغلاق أي قائمة مفتوحة أخرى

        document.querySelectorAll('.sy-nav-drop').forEach(m => m.classList.remove('open'));

        

        if (!isOpen) {

            menu.classList.add('open');

            if (menuId === 'drop-notif-menu') fetchNotifsHeader();

        }

    };

}



setupHeaderDropdown('trigger-notif-btn', 'drop-notif-menu');

setupHeaderDropdown('trigger-user-btn', 'drop-user-menu');

setupHeaderDropdown('trigger-add-btn', 'drop-add-menu');



document.addEventListener('click', () => {

    document.querySelectorAll('.sy-nav-drop').forEach(m => m.classList.remove('open'));

});



// 3. جلب الإشعارات

async function fetchNotifsHeader() {

    const container = document.getElementById('h-notif-loader');

    try {

        const res = await fetch('php/fetch_site_notifications.php');

        const data = await res.json();

        container.innerHTML = '';

        if (data.length === 0) {

            container.innerHTML = '<div style="padding:40px; text-align:center; color:#999; font-size:0.8rem;">لا توجد إشعارات حالياً</div>';

            return;

        }

        data.forEach(n => {

            const item = document.createElement('a');

            item.href = n.link || '#';

            item.className = `drop-link-item ${n.is_read == 0 ? 'unread-notif' : ''}`;

            item.innerHTML = `<div><strong>${n.title}</strong><p style="margin:3px 0; color:#666;">${n.message}</p><small style="color:#aaa; font-size:0.65rem;">${n.time_ago}</small></div>`;

            container.appendChild(item);

        });

    } catch(e) { container.innerHTML = '<div style="padding:20px; text-align:center; color:#e60000;">فشل الاتصال</div>'; }

}



async function markAllAsRead() {

    try {

        const res = await fetch('php/mark_notifications_read.php');

        const d = await res.json();

        if (d.success) {

            const badge = document.querySelector('.notif-badge-pill'); if(badge) badge.remove();

            fetchNotifsHeader();

        }

    } catch(e) {}

}



// 4. جسر الأندرويد لاستقبال التوكن

window.saveFCMTokenFromAndroid = function(token) {

    if (!token) return;

    const fd = new FormData();

    fd.append('fcm_token', token);

    fd.append('type', '<?php echo $h_utype; ?>');

    fetch('php/save_fcm_token.php', { method: 'POST', body: fd });

};



// 5. إظهار شريط التحميل فور الضغط على أي رابط (لإعطاء إيحاء بالسرعة)

document.addEventListener('click', function(e) {

    const a = e.target.closest('a');

    if (a && a.href && a.href.includes('syriazzle.sy') && !a.target && !a.href.includes('#')) {

        // إذا كنت تستخدم ProgressBar في الـ MainActivity، الـ WebViewClient سيتكفل بالباقي

    }

});

</script>