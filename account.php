<?php
require_once 'php/db_connect.php';
$page_title = 'حسابي - Syriazzle';
// التحقق من تسجيل الدخول، هذه الصفحة للمستخدمين المسجلين فقط
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect_url=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'المستخدم';

// جلب البيانات الديناميكية للبطاقات (عدد الطلبات، المتاجر، إلخ)
try {
    // التحقق مما إذا كان المستخدم يملك متاجر
    $stmt_stores = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE user_id = ?");
    $stmt_stores->execute([$current_user_id]);
    $user_has_stores = $stmt_stores->fetchColumn() > 0;

    // التحقق مما إذا كان المستخدم مسجل كسائق
    $stmt_driver = $pdo->prepare("SELECT COUNT(*) FROM drivers WHERE phone = (SELECT phone FROM users WHERE id = ?)");
    $stmt_driver->execute([$current_user_id]);
    $is_user_a_driver = $stmt_driver->fetchColumn() > 0;

    // جلب عدد الطلبات الحالية للمستخدم
    $stmt_orders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status NOT IN ('delivered', 'canceled')");
    $stmt_orders->execute([$current_user_id]);
    $active_orders_count = $stmt_orders->fetchColumn();
    
} catch (PDOException $e) {
    // في حالة حدوث خطأ، استخدم قيماً افتراضية آمنة
    $user_has_stores = false;
    $is_user_a_driver = false;
    $active_orders_count = 0;
    // يمكنك تسجيل الخطأ هنا للمراجعة لاحقاً
    // error_log('Account page DB Error: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حسابي - Syriazzle</title>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #e60000; --secondary-color: #007bff; --bg-light: #f0f2f5; --card-bg: #fff; 
            --text-dark: #212529; --text-light: #6c757d; --border-color: #e9ecef; --cta-bg: #e7f3ff;
        }
        body { font-family: 'Cairo', sans-serif; background-color: var(--bg-light); margin: 0; }
        .page-container { max-width: 900px; margin: 30px auto; padding: 20px; }
        
        /* تصميم الهيدر الجديد */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #a70000 100%);
            color: #fff;
            padding: 40px 20px;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 10px 20px rgba(230,0,0,0.2);
        }
        .page-header h1 { font-size: 36px; margin: 0 0 5px 0; font-weight: 800; }
        .page-header p { font-size: 18px; margin: 0; opacity: 0.9; }

        /* تصميم الشبكة والبطاقات الجديد */
        .account-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; }
        .account-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.07);
            padding: 25px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        .account-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }
        .account-card h2 {
            font-size: 20px;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-dark);
        }
        .account-card h2 i { color: var(--secondary-color); font-size: 24px; }
        
        /* تصميم الروابط الداخلية */
        .account-links a {
            display: flex;
            align-items: center;
            padding: 12px 0;
            text-decoration: none;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border-color);
            transition: color 0.2s;
        }
        .account-links a:last-child { border-bottom: none; }
        .account-links a:hover { color: var(--secondary-color); }
        .account-links a .link-text { flex-grow: 1; font-weight: 600; font-size: 16px; }
        .account-links a .badge { background-color: var(--primary-color); color: #fff; font-size: 12px; padding: 3px 8px; border-radius: 10px; }
        
        /* تصميم الروابط التشجيعية (CTA) */
        .cta-card {
            background: var(--cta-bg);
            text-align: center;
            padding: 30px;
        }
        .cta-card .icon { font-size: 40px; color: var(--secondary-color); margin-bottom: 15px; }
        .cta-card h3 { font-size: 18px; margin: 0 0 10px 0; }
        .cta-card p { font-size: 14px; color: var(--text-light); margin: 0 0 20px 0; }
        .cta-btn {
            background: var(--secondary-color);
            color: #fff;
            padding: 10px 25px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="page-container">
        <div class="page-header">
            <h1>أهلاً بعودتك، <?php echo htmlspecialchars($username); ?>!</h1>
            <p>هنا مركز التحكم الخاص بك.</p>
        </div>

        <div class="account-grid">
            <!-- بطاقة إدارة الحساب -->
            <div class="account-card">
                <h2><i class="fas fa-user-cog"></i> حسابك</h2>
                <div class="account-links">
                    <a href="settings.php">
                        <span class="link-text">الإعدادات الشخصية</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="my_addresses.php"> <!-- رابط للصفحة الجديدة -->
                        <span class="link-text">دفتر العناوين</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="php/logout.php">
                        <span class="link-text" style="color: var(--primary-color);">تسجيل الخروج</span>
                        <i class="fas fa-sign-out-alt" style="color: var(--primary-color);"></i>
                    </a>
                </div>
            </div>

            <!-- بطاقة نشاطاتك -->
            <div class="account-card">
                <h2><i class="fas fa-history"></i> نشاطاتك</h2>
                <div class="account-links">
                    <a href="my_orders.php">
                        <span class="link-text">طلباتي</span>
                        <?php if ($active_orders_count > 0): ?>
                            <span class="badge"><?php echo $active_orders_count; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="#">
                        <span class="link-text">المتاجر التي أتابعها</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </div>
            </div>

            <!-- بطاقة أعمالك معنا -->
            <?php if (!$user_has_stores): ?>
                <div class="account-card cta-card">
                    <div class="icon"><i class="fas fa-store-alt"></i></div>
                    <h3>هل تملك نشاطاً تجارياً؟</h3>
                    <p>قم بعرض منتجاتك وخدماتك لآلاف العملاء على منصتنا.</p>
                    <a href="add_business_user.php" class="cta-btn">أضف نشاطك مجاناً</a>
                </div>
            <?php else: ?>
                <div class="account-card">
                    <h2><i class="fas fa-briefcase"></i> أعمالك معنا</h2>
                    <div class="account-links">
                        <a href="business_dashboard.php">
                            <span class="link-text">لوحة تحكم متاجري</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$is_user_a_driver): ?>
                <div class="account-card cta-card">
                    <div class="icon"><i class="fas fa-shipping-fast"></i></div>
                    <h3>هل تبحث عن دخل إضافي؟</h3>
                    <p>انضم إلى أسطول الكباتن لدينا وكن جزءاً من نجاحنا.</p>
                    <a href="driver_register.php" class="cta-btn">انضم إلينا كسائق</a>
                </div>
            <?php else: ?>
                <div class="account-card">
                    <h2><i class="fas fa-motorcycle"></i> بوابة السائقين</h2>
                    <div class="account-links">
                        <a href="driver_dashboard.php">
                            <span class="link-text">لوحة تحكم السائق</span>
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>