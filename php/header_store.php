<?php
$isUserLoggedIn_header = isset($_SESSION['user_id']);
$username_header = $_SESSION['username'] ?? 'زائر';
$profile_image_header = $_SESSION['profile_image'] ?? null;
?>
<!-- Firebase SDKs -->
<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js"></script>
<div id="preloader">
    <div class="loader-logo">
        <img src="../image/logo1.png" alt="Syriazzle Logo">
    </div>
    <div class="loader-dots">
        <div class="dot"></div>
        <div class="dot"></div>
        <div class="dot"></div>
    </div>
</div>
<header class="main-site-header">
    <div class="header-container">
        <a href="../index.php" class="header-logo">
            <img src="../image/logo1.png" alt="Syriazzle Logo">
        </a>
        <nav class="main-nav">
            <a href="../index.php" class="nav-link">الرئيسية</a>
            <a href="../listings.php" class="nav-link">الأنشطة التجارية</a>
        </nav>
        <div class="header-actions">
            <?php if ($isUserLoggedIn_header): ?>
                <button type="button" class="notification-bell-btn" id="enable-notifications-btn" title="تفعيل إشعارات المتصفح">
                    <i class="fas fa-bell-slash"></i>
                </button>
                <div class="user-menu-container">
                    <a href="#" class="user-menu-trigger" id="user-menu-trigger" title="<?php echo htmlspecialchars($username_header); ?>">
                        <?php if ($profile_image_header): ?>
                            <img src="<?php echo htmlspecialchars($profile_image_header); ?>" alt="الصورة الشخصية" class="user-avatar">
                        <?php else: ?>
                            <div class="user-avatar-placeholder"><?php echo mb_strtoupper(mb_substr($username_header, 0, 1)); ?></div>
                        <?php endif; ?>
                        <span class="username"><?php echo htmlspecialchars($username_header); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                    <div class="user-menu-dropdown1" id="user-menu-dropdown">
                        <ul>
                            <li><a href="../account.php"><i class="fas fa-user-cog"></i> حسابي</a></li>
                            <li><a href="..,my_orders.php"><i class="fas fa-receipt"></i> طلباتي (توصيل)</a></li>
                            <li><a href="../my_bookings.php"><i class="fas fa-calendar-check"></i> حجوزاتي</a></li>
                            <li class="dropdown-divider"></li>
                            <li><a href="../business_dashboard.php"><i class="fas fa-store"></i> لوحة تحكم التوصيل</a></li>
                            <li><a href="../booking_dashboard.php"><i class="fas fa-concierge-bell"></i> لوحة تحكم الحجوزات</a></li>
                            <li class="dropdown-divider"></li>
                            <!-- **الإصلاح هنا: تمت إزالة الرابط من داخل li وجعل li نفسه هو الرابط** -->
                            <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a></li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <a href="../login.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>تسجيل الدخول</span>
                </a>
            <?php endif; ?>
            
            <div class="add-new-container">
                <button class="add-new-btn" id="add-new-btn">
                    <i class="fas fa-plus"></i>
                    <span>إضافة</span>
                </button>
                <div class="add-new-dropdown" id="add-new-dropdown">
                    <ul>
                        <li>
                            <a href="../ads_new.php">
                                <i class="fas fa-ad"></i>
                                <div class="item-text">
                                    <h4>إعلان مبوب</h4>
                                    <p>سيارات، عقارات، هواتف...</p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="../add_business_user.php">
                                <i class="fas fa-shipping-fast"></i>
                                <div class="item-text">
                                    <h4>أضف متجرك</h4>
                                    <p>محلات أكل، سوبرماركت، صيدليات...</p>
                                </div>
                            </a>
                        </li>
                        <li>
                            <a href="../add_booking_business.php">
                                <i class="fas fa-concierge-bell"></i>
                                <div class="item-text">
                                    <h4>نشاط حجز</h4>
                                    <p>فنادق، عيادات، صالات...</p>
                                </div>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-overlay-custom" id="notification-permission-modal">
        <div class="modal-content-custom">
            <div class="modal-icon"><i class="fas fa-bell"></i></div>
            <h3>تفعيل الإشعارات</h3>
            <p>هل تسمح لمنصة Syriazzle بإرسال إشعارات إليك بآخر تحديثات طلباتك وعروضنا الحصرية؟</p>
            <div class="modal-buttons">
                <button class="btn-secondary-custom" id="decline-notifications-btn">لاحقًا</button>
                <button class="btn-primary-custom" id="accept-notifications-btn">نعم، أوافق</button>
            </div>
        </div>
    </div>
</header>
<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js"></script>


<script>
    const IS_USER_LOGGED_IN = <?php echo json_encode($isUserLoggedIn_header); ?>;
</script>
<script src="js/notifications_init.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        function setupDropdown(triggerId, dropdownId) {
            const trigger = document.getElementById(triggerId);
            const dropdown = document.getElementById(dropdownId);

            if (trigger && dropdown) {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    
                    document.querySelectorAll('.user-menu-dropdown.visible, .add-new-dropdown.visible').forEach(d => {
                        if (d !== dropdown) d.classList.remove('visible');
                    });
                    
                    dropdown.classList.toggle('visible');
                });
            }
        }

        setupDropdown('user-menu-trigger', 'user-menu-dropdown');
        setupDropdown('add-new-btn', 'add-new-dropdown');

        document.addEventListener('click', (event) => {
            const userMenu = document.getElementById('user-menu-dropdown');
            const addNewMenu = document.getElementById('add-new-dropdown');

            if (userMenu && !userMenu.parentElement.contains(event.target)) {
                userMenu.classList.remove('visible');
            }
            // إذا كان النقر خارج حاوية قائمة الإضافة، أغلقها
            if (addNewMenu && !addNewMenu.parentElement.contains(event.target)) {
                addNewMenu.classList.remove('visible');
            }
        });
    });
</script>
<script>
    const preloader = document.getElementById('preloader');
    function navigateTo(url) {
        if (!preloader || !url) return;

        preloader.classList.add('visible');
        setTimeout(() => {
            preloader.classList.add('fade-in');
        }, 10); 
        setTimeout(() => {
            window.location.href = url;
        }, 300);
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!preloader) return;

        document.body.addEventListener('click', function(event) {
            const link = event.target.closest('a');
            
            if (!link) return; 

            const href = link.getAttribute('href');

            if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.getAttribute('target') === '_blank') {
                return;
            }
            const fileExtensions = ['.pdf', '.jpg', '.png', '.zip', '.mp4'];
            if (fileExtensions.some(ext => href.toLowerCase().endsWith(ext))) {
                return;
            }
            event.preventDefault();
            navigateTo(href);
        });
        document.body.addEventListener('submit', function(event) {
            const form = event.target.closest('form');
            if (form) {
                preloader.classList.add('visible');
                preloader.classList.add('fade-in');
            }
        });

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                preloader.classList.remove('fade-in');
                preloader.classList.remove('visible');
            }
        });
    });
</script>