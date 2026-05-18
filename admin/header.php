<?php

// ========================================================================

// Syriazzle Admin - Ultra Modern Curved Header (V3.1 - Fixed Notifications)

// ========================================================================



require_once 'auth_guard.php';



// تحديد الصفحة الحالية وعنوانها

$current_page = basename($_SERVER['PHP_SELF']);

$page_title = $page_title ?? 'لوحة التحكم';



// جلب عدد الإشعارات غير المقروءة من الجدول الموحد

$unread_notifications_count = 0;

if (hasPermission('view_bookings')) {

    try {

        // الأدمن يُعتبر دائماً user_id = المأخوذ من الجلسة ونوعه 'user'

        $stmt_notifications = $pdo->prepare("SELECT COUNT(id) FROM site_notifications WHERE user_id = ? AND user_type = 'user' AND is_read = 0");

        $stmt_notifications->execute([$_SESSION['admin_id']]);

        $unread_notifications_count = (int)$stmt_notifications->fetchColumn();

    } catch (PDOException $e) {

        error_log("Failed to fetch admin notifications: " . $e->getMessage());

    }

}

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($page_title); ?> - Syriazzle Admin</title>

    

    <!-- الملحقات الخارجية -->

    <link rel="stylesheet" href="../css/all.min.css"> 

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">

    

    <style>

        :root {

            --sy-red-dark: #f77b0d; /* أحمر اللوحة الجانبية الداكن */

            --sy-red-main: #e67700; /* أحمر الهوية */

            --admin-bg: #f4f7f6;    /* لون خلفية المحتوى */

            --sidebar-w: 270px;

            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

        }



        body { font-family: 'Cairo', sans-serif; background-color: var(--admin-bg); margin: 0; overflow-x: hidden; }

        .admin-wrapper { display: flex; min-height: 100vh; }



        /* --- اللوحة الجانبية الاحترافية --- */

        .sidebar { 

            width: var(--sidebar-w); 

            background-color: var(--sy-red-dark); 

            color: #fff; 

            padding: 20px 0; 

            flex-shrink: 0; 

            display: flex; 

            flex-direction: column;

            position: sticky;

            top: 0;

            height: 100vh;

            z-index: 1000;

        }



        .sidebar-brand { 

            text-align: center; 

            font-size: 22px; 

            font-weight: 900; 

            color: #fff; 

            text-decoration: none; 

            padding: 0 20px 25px; 

            margin-bottom: 10px;

            display: block;

            border-bottom: 1px solid rgba(255,255,255,0.1);

        }



        .sidebar-nav { list-style: none; padding: 0; margin: 0; flex-grow: 1; overflow-y: auto; }

        .sidebar-nav::-webkit-scrollbar { width: 4px; }

        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }



        .sidebar-nav .nav-header { 

            font-size: 11px; 

            font-weight: 800; 

            color: rgba(255,255,255,0.5); 

            text-transform: uppercase; 

            padding: 20px 25px 10px; 

            letter-spacing: 1px;

        }



        .sidebar-nav li { position: relative; padding-right: 15px; }



        .sidebar-nav li a { 

            display: flex; 

            align-items: center; 

            gap: 15px; 

            color: rgba(255,255,255,0.8); 

            text-decoration: none; 

            padding: 14px 20px; 

            border-radius: 30px 0 0 30px; 

            transition: var(--transition);

            font-size: 0.95rem;

            font-weight: 600;

        }



        /* --- تأثير التقوس المتصل المطلب --- */

        .sidebar-nav li.active a { 

            background-color: var(--admin-bg); 

            color: var(--sy-red-dark); 

            font-weight: 800;

            position: relative;

            z-index: 10;

        }



        .sidebar-nav li.active::before {

            content: "";

            position: absolute;

            top: -20px;

            left: 0;

            width: 20px;

            height: 20px;

            background-color: transparent;

            border-bottom-left-radius: 20px;

            box-shadow: -5px 5px 0 5px var(--admin-bg);

            z-index: 1;

        }



        .sidebar-nav li.active::after {

            content: "";

            position: absolute;

            bottom: -20px;

            left: 0;

            width: 20px;

            height: 20px;

            background-color: transparent;

            border-top-left-radius: 20px;

            box-shadow: -5px -5px 0 5px var(--admin-bg);

            z-index: 1;

        }



        .sidebar-nav li a i { width: 20px; text-align: center; font-size: 18px; }

        .sidebar-nav li a:hover:not(.active) { color: #fff; background: rgba(255,255,255,0.05); }



        .sidebar-footer { padding: 20px; text-align: center; font-size: 12px; color: rgba(255,255,255,0.4); border-top: 1px solid rgba(255,255,255,0.1); }



        /* --- محتوى الصفحة الرئيسي --- */

        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; }

        

        .content-header { 

            display: flex; 

            justify-content: space-between; 

            align-items: center; 

            margin-bottom: 35px; 

            padding: 15px 25px;

            background: #fff;

            border-radius: 20px;

            box-shadow: 0 4px 20px rgba(0,0,0,0.03);

            position: relative;

        }



        .content-header h1 { margin: 0; font-size: 1.6rem; font-weight: 900; color: var(--sy-red-dark); }



        /* --- الجرس والإشعارات --- */

        .header-actions { display: flex; align-items: center; gap: 20px; }

        .notifications-wrapper { position: relative; }

        .notifications-bell { 

            cursor: pointer; 

            position: relative; 

            color: #555; 

            font-size: 1.4rem; 

            width: 45px;

            height: 45px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #f8f9fa;

            border-radius: 12px;

            transition: 0.3s;

        }

        .notifications-bell:hover { color: var(--sy-red-main); background: #fff1f1; }

        

        .notification-badge {

            position: absolute; top: -5px; right: -5px; 

            background-color: var(--sy-red-main); color: #fff;

            width: 19px; height: 19px; border-radius: 50%; 

            display: flex; align-items: center; justify-content: center;

            font-size: 10px; font-weight: 900; border: 2px solid #fff;

        }



        .notifications-dropdown {

            display: none; 

            position: absolute; 

            top: 60px; 

            left: 0;

            width: 320px; 

            background-color: #fff; 

            border-radius: 18px;

            box-shadow: 0 15px 50px rgba(0,0,0,0.2); 

            border: 1px solid #eee;

            z-index: 100000; 

            overflow: hidden;

        }

        .notifications-dropdown.show { display: block; animation: syDropIn 0.3s ease; }

        @keyframes syDropIn { from { opacity:0; transform: translateY(-10px); } to { opacity:1; transform: translateY(0); } }

        

        .dropdown-header { padding: 15px; font-weight: 800; background: #fdfdfd; border-bottom: 1px solid #eee; font-size: 0.85rem; }

        .dropdown-body { max-height: 350px; overflow-y: auto; }

        .dropdown-body a {

            display: block; text-decoration: none; color: #333; padding: 15px;

            border-bottom: 1px solid #f9f9f9; transition: 0.2s;

        }

        .dropdown-body a:hover { background-color: #f0f7ff; }

        .dropdown-body p { margin: 0 0 5px 0; font-size: 13.5px; font-weight: 700; line-height: 1.4; }

        .dropdown-body small { color: #aaa; font-size: 11px; }

        

        .dropdown-footer { text-align: center; padding: 12px; border-top: 1px solid #eee; background: #fdfdfd; }

        .dropdown-footer a { color: var(--sy-red-main); font-weight: 800; text-decoration: none; font-size: 0.85rem; }



        .user-info { display: flex; align-items: center; gap: 12px; padding-right: 20px; border-right: 1px solid #eee; }

        .btn-logout-h { color: var(--sy-red-main); text-decoration: none; font-weight: 800; background: #fff1f1; padding: 8px 15px; border-radius: 10px; font-size: 0.85rem; }



        @media (max-width: 992px) {

            .sidebar { width: 70px; }

            .sidebar-brand, .nav-header, .sidebar li a span, .sidebar-footer { display: none; }

            .sidebar li { padding-right: 0; }

            .sidebar li a { border-radius: 0; justify-content: center; padding: 20px 0; }

            .sidebar li.active::before, .sidebar li.active::after { display: none; }

            .notifications-dropdown { position: fixed; left: 15px; right: 15px; width: auto; top: 80px; }

        }

    </style>

</head>

<body>

    <div class="admin-wrapper">

        <aside class="sidebar">

            <a href="dashboard.php" class="sidebar-brand">Syriazzle <small style="display:block; font-size:10px; opacity:0.5;">Admin Panel</small></a>

            

            <ul class="sidebar-nav">

                <!-- إدارة الطلبات -->

                <?php if (hasPermission('view_all_orders')): ?>

                <li class="nav-header">الطلبات</li>

                <li class="<?php echo ($current_page == 'manage_all_orders.php' || $current_page == 'order_details.php') ? 'active' : ''; ?>">

                    <a href="manage_all_orders.php"><i class="fas fa-shopping-basket fa-fw"></i> <span>طلبات المتاجر</span></a>

                </li>

                <?php endif; ?>



                <!-- الحجوزات -->

                <?php if (hasPermission('view_bookings')): ?>

                <li class="nav-header">الحجوزات</li>

                <li class="<?php echo ($current_page == 'manage_bookings.php') ? 'active' : ''; ?>">

                    <a href="manage_bookings.php"><i class="fas fa-calendar-alt fa-fw"></i> <span>إدارة الحجوزات</span></a>

                </li>

                <li class="<?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>">

                    <a href="notifications.php"><i class="fas fa-history fa-fw"></i> <span>سجل الإشعارات</span></a>

                </li>

                <?php endif; ?>



                <!-- المالية -->

                <?php if (hasPermission('view_financials')): ?>

                <li class="nav-header">المالية</li>

                <li class="<?php echo ($current_page == 'financials.php' || $current_page == 'financial_profile.php') ? 'active' : ''; ?>">

                    <a href="financials.php"><i class="fas fa-wallet fa-fw"></i> <span>الداشبورد المالي</span></a>

                </li>

                <li class="<?php echo ($current_page == 'payout_requests.php') ? 'active' : ''; ?>">

                    <a href="payout_requests.php"><i class="fas fa-hand-holding-usd fa-fw"></i> <span>طلبات التسوية</span></a>

                </li>

                <li class="<?php echo ($current_page == 'payout_history.php') ? 'active' : ''; ?>">

                    <a href="payout_history.php"><i class="fas fa-file-invoice-dollar fa-fw"></i> <span>سجل الدفعات</span></a>

                </li>

                <?php endif; ?>



                <?php if (hasPermission('view_booking_analytics')): ?>

                <li class="<?php echo ($current_page == 'booking_analytics.php') ? 'active' : ''; ?>">

                    <a href="booking_analytics.php"><i class="fas fa-chart-line fa-fw"></i> <span>التحليلات</span></a>

                </li>

                <?php endif; ?>



                <!-- المحتوى -->

                <?php if (hasPermission('view_businesses')): ?>

                <li class="nav-header">المحتوى</li>

                <li class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">

                    <a href="dashboard.php"><i class="fas fa-store fa-fw"></i> <span>المتاجر</span></a>

                </li>

                <?php endif; ?>

                

                <?php if (hasPermission('add_business')): ?>

                <li class="<?php echo ($current_page == 'add_business.php') ? 'active' : ''; ?>">

                    <a href="add_business.php"><i class="fas fa-plus-circle fa-fw"></i> <span>إضافة نشاط</span></a>

                </li>

                <?php endif; ?>



                <!-- المول -->

                <?php if (hasPermission('manage_mall')): ?>

                <li class="nav-header">المول</li>

                <li class="<?php echo ($current_page == 'manage_mall_orders.php') ? 'active' : ''; ?>">

                    <a href="manage_mall_orders.php"><i class="fas fa-box-open fa-fw"></i> <span>طلبات المول</span></a>

                </li>

                <li class="<?php echo ($current_page == 'mall_inventory.php') ? 'active' : ''; ?>">

                    <a href="mall_inventory.php"><i class="fas fa-warehouse fa-fw"></i> <span>المخزون</span></a>

                </li>

                <li class="<?php echo ($current_page == 'mall_sales_report.php') ? 'active' : ''; ?>">

                    <a href="mall_sales_report.php"><i class="fas fa-chart-bar fa-fw"></i> <span>المبيعات</span></a>

                </li>

                <li class="<?php echo ($current_page == 'manage_mall.php') ? 'active' : ''; ?>">

                    <a href="manage_mall.php"><i class="fas fa-cog fa-fw"></i> <span>إعدادات المول</span></a>

                </li>

                <?php endif; ?>



                <!-- التفاعل -->

                <li class="nav-header">الجمهور</li>

                <?php if (hasPermission('view_reviews')): ?>

                <li class="<?php echo ($current_page == 'manage_reviews.php') ? 'active' : ''; ?>">

                    <a href="manage_reviews.php"><i class="fas fa-star fa-fw"></i> <span>المراجعات</span></a>

                </li>

                <?php endif; ?>



                <?php if (hasPermission('view_classifieds')): ?>

                <li class="<?php echo ($current_page == 'manage_classifieds.php') ? 'active' : ''; ?>">

                    <a href="manage_classifieds.php"><i class="fas fa-ad fa-fw"></i> <span>الإعلانات</span></a>

                </li>

                <?php endif; ?>



                <!-- الكوادر -->

                <?php if (hasPermission('view_users') || hasPermission('view_drivers')): ?>

                <li class="nav-header">الكوادر</li>

                <?php endif; ?>



                <?php if (hasPermission('view_users')): ?>

                <li class="<?php echo in_array($current_page, ['manage_users.php', 'edit_user.php']) ? 'active' : ''; ?>">

                    <a href="manage_users.php"><i class="fas fa-users fa-fw"></i> <span>الزبائن</span></a>

                </li>

                <?php endif; ?>



                <?php if (hasPermission('view_drivers')): ?>

                <li class="<?php echo in_array($current_page, ['manage_drivers.php', 'edit_driver.php']) ? 'active' : ''; ?>">

                    <a href="manage_drivers.php"><i class="fas fa-motorcycle fa-fw"></i> <span>الكباتن</span></a>

                </li>

                <?php endif; ?>

                

                <!-- إعدادات النظام -->

                <?php if (hasPermission('manage_system_settings') || hasPermission('manage_zones') || hasPermission('manage_staff') || $is_super_admin): ?>

                <li class="nav-header">النظام</li>

                <?php if (hasPermission('manage_system_settings')): ?>

                <li class="<?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">

                    <a href="settings.php"><i class="fas fa-sliders-h fa-fw"></i> <span>الإعدادات</span></a>

                </li>

                <?php endif; ?>

                <?php if (hasPermission('manage_zones')): ?>

                <li class="<?php echo ($current_page == 'manage_zones.php') ? 'active' : ''; ?>">

                    <a href="manage_zones.php"><i class="fas fa-map-marked-alt fa-fw"></i> <span>المناطق الجغرافية</span></a>

                </li>

                <?php endif; ?>

                <?php if (hasPermission('manage_staff')): ?>

                <li class="<?php echo ($current_page == 'manage_staff.php') ? 'active' : ''; ?>">

                    <a href="manage_staff.php"><i class="fas fa-user-shield fa-fw"></i> <span>الموظفين</span></a>

                </li>

                <?php endif; ?>

                <?php if ($is_super_admin): ?>

                <li class="<?php echo ($current_page == 'manage_roles.php') ? 'active' : ''; ?>">

                    <a href="manage_roles.php"><i class="fas fa-user-tag fa-fw"></i> <span>الصلاحيات</span></a>

                </li>

                <li class="<?php echo ($current_page == 'recycling_bin.php') ? 'active' : ''; ?>">

                    <a href="recycling_bin.php"><i class="fas fa-trash-alt fa-fw"></i> <span>سلة المحذوفات</span></a>

                </li>

                <?php endif; ?>

                <?php endif; ?>

            </ul>



            <div class="sidebar-footer">

                <p>&copy; <?php echo date('Y'); ?> Syriazzle</p>

            </div>

        </aside>



        <main class="main-content">

            <header class="content-header">

                <h1><?php echo htmlspecialchars($page_title); ?></h1>

                

                <div class="header-actions">

                    <?php if (hasPermission('view_bookings')): ?>

                    <div class="notifications-wrapper">

                        <div id="sy-notif-bell" class="notifications-bell" title="تنبيهات النظام">

                            <i class="fas fa-bell"></i>

                            <?php if ($unread_notifications_count > 0): ?>

                                <span class="notification-badge" id="sy-badge"><?php echo $unread_notifications_count; ?></span>

                            <?php endif; ?>

                        </div>

                        <div id="sy-notif-dropdown" class="notifications-dropdown">

                            <div class="dropdown-header">آخر التنبيهات</div>

                            <div class="dropdown-body" id="sy-notif-body">

                                <div style="padding:20px; text-align:center; color:#ccc;"><i class="fas fa-circle-notch fa-spin"></i></div>

                            </div>

                            <div class="dropdown-footer">

                                <a href="notifications.php">مشاهدة الكل</a>

                            </div>

                        </div>

                    </div>

                    <?php endif; ?>

                    

                    <div class="user-info">

                        <span><strong><?php echo htmlspecialchars($_SESSION['admin_full_name'] ?? 'المدير'); ?></strong></span>

                        <a href="logout.php" class="btn-logout-h">خروج</a>

                    </div>

                </div>

            </header>



<script>

    // --- إصلاح الجرس والإشعارات ---

    const bell = document.getElementById('sy-notif-bell');

    const dropdown = document.getElementById('sy-notif-dropdown');

    const alertSound = new Audio('../assets/sounds/alert.mp3');



    if (bell) {

        bell.addEventListener('click', function(e) {

            e.stopPropagation();

            dropdown.classList.toggle('show');

            if (dropdown.classList.contains('show')) {

                loadAdminNotifs();

            }

        });

    }



    // إغلاق عند الضغط في أي مكان آخر

    document.addEventListener('click', () => dropdown?.classList.remove('show'));



    async function loadAdminNotifs() {

        const body = document.getElementById('sy-notif-body');

        try {

            const res = await fetch('php/fetch_admin_notifs.php');

            const data = await res.json();

            body.innerHTML = '';

            

            if (data.length === 0) {

                body.innerHTML = '<div style="padding:30px; text-align:center; color:#999;">لا توجد تنبيهات</div>';

                return;

            }



            data.forEach(n => {

                const item = document.createElement('a');

                item.href = n.link || 'notifications.php';

                item.innerHTML = `

                    <p>${n.title}</p>

                    <div style="font-size:12px; color:#666;">${n.message}</div>

                    <small>${n.time_ago}</small>

                `;

                body.appendChild(item);

            });

        } catch (e) {

            body.innerHTML = '<div style="padding:20px; text-align:center; color:red;">خطأ في الاتصال</div>';

        }

    }



    // فحص دوري للعدد لتشغيل الصوت وتحديث البادج

    let currentCount = <?php echo $unread_notifications_count; ?>;

    setInterval(async () => {

        try {

            const res = await fetch('php/get_notif_count.php'); // ملف يرجع {count: x}

            const data = await res.json();

            if (data.count > currentCount) {

                alertSound.play();

                const b = document.getElementById('sy-badge');

                if(b) b.textContent = data.count;

                currentCount = data.count;

            }

        } catch(e) {}

    }, 15000);

</script>