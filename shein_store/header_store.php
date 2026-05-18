<?php
$isUserLoggedIn_header = isset($_SESSION['user_id']);
$username_header = $_SESSION['username'] ?? 'زائر';
$profile_image_header = $_SESSION['profile_image'] ?? null;
?>
<!-- ... (Firebase SDKs, Preloader HTML) ... -->
<div id="preloader"> <!-- ... --> </div>
<!-- =============================================== -->
<!-- ==     CSS الخاص بأيقونة السلة المضافة       == -->
<!-- =============================================== -->
<style>
    .cart-btn-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .toggle-cart-btn {
        background: none;
        border: none;
        color: #333; /* أو اللون المناسب لتصميمك */
        font-size: 1.4rem;
        cursor: pointer;
        padding: 10px;
        transition: color 0.2s;
    }
    .toggle-cart-btn:hover {
        color: #007bff; /* لون عند المرور */
    }
    .cart-badge {
        position: absolute;
        top: 0;
        right: 0;
        background-color: #e74c3c; /* لون أحمر مميز */
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
        border: 2px solid #fff; /* إطار أبيض حول العداد */
        transform: translate(30%, -30%);
    }
     .add-new-dropdown{
                display:none;
            }
</style>

<header class="main-site-header">
    <div class="header-container">
        <a href="../index.php" class="header-logo"><img src="../image/logo1.png" alt="Syriazzle Logo"></a>
        <nav class="main-nav">
            <a href="../index.php" class="nav-link">الرئيسية</a>
            <a href="../listings.php" class="nav-link">الأنشطة التجارية</a>
            <a href="index.php" class="nav-link">المتجر</a>
        </nav>
        <div class="header-actions">
            <div class="cart-btn-wrapper">
                <button type="button" class="toggle-cart-btn" title="عرض السلة"><i class="fas fa-shopping-cart"></i></button>
                <span class="cart-badge" id="cart-item-count">0</span>
            </div>
            <?php if ($isUserLoggedIn_header): ?>
                <!-- ... (User menu HTML) ... -->
            <?php else: ?>
                <a href="../login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i><span>تسجيل الدخول</span></a>
            <?php endif; ?>
            <div class="add-new-container">
                <!-- ... (Add new dropdown HTML) ... -->
            </div>
        </div>
    </div>
    <!-- ... (Notification Modal HTML) ... -->
</header>
<script>
    const IS_USER_LOGGED_IN = <?php echo json_encode($isUserLoggedIn_header); ?>;

    // هذه الدالة ستبقى هنا ليستدعيها سكربت المتجر عند الحاجة
    const preloader = document.getElementById('preloader');
    function navigateTo(url) {
        if (!preloader || !url) {
            window.location.href = url; // انتقال احتياطي
            return;
        }
        preloader.classList.add('visible');
        setTimeout(() => {
            preloader.classList.add('fade-in');
        }, 10); 
        setTimeout(() => {
            window.location.href = url;
        }, 300);
    }

    document.addEventListener('DOMContentLoaded', () => {
        // --- منسدلات الهيدر ---
        function setupDropdown(triggerId, dropdownId) {
            const trigger = document.getElementById(triggerId);
            const dropdown = document.getElementById(dropdownId);
            if(trigger && dropdown) {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    dropdown.classList.toggle('visible');
                });
            }
        }
        setupDropdown('user-menu-trigger', 'user-menu-dropdown');
        setupDropdown('add-new-btn', 'add-new-dropdown');

        // --- إغلاق المنسدلات عند النقر خارجها ---
        document.addEventListener('click', (event) => { /* ... */ });
        
        // --- التعامل مع الروابط العامة (خارج المتجر) ---
        document.body.addEventListener('click', function(event) {
            const link = event.target.closest('a');
            if (!link) return;
            // نتأكد أن الرابط لا ينتمي لمنطقة منتجات المتجر التي لها معالجة خاصة
            if (link.closest('#products-container')) return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#')) return;
            
            event.preventDefault();
            navigateTo(href);
        });
    });
</script>
