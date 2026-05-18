<?php
$page_title = 'إدارة المستخدمين';
include 'header.php';

// --- 1. حارس البوابة (Security Check) ---
if (!hasPermission('view_users')) {
    echo "<div class='access-denied-container'>
            <i class='fas fa-lock'></i>
            <h2>عذراً، ليس لديك صلاحية للوصول لهذه الصفحة.</h2>
          </div>";
    include 'footer.php';
    exit;
}

try {
    // --- 2. جلب الإحصائيات العامة (للبوكسات العلوية) ---
    $stats_sql = "
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
            (SELECT COUNT(DISTINCT user_id) FROM businesses WHERE deleted_at IS NULL) as business_owners,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_this_month
        FROM users 
        WHERE deleted_at IS NULL
    ";
    $stats = $pdo->query($stats_sql)->fetch(PDO::FETCH_ASSOC);

    // --- 3. جلب قائمة المستخدمين ---
    $users_sql = "
        SELECT 
            u.id, 
            u.username, 
            u.email, 
            u.phone, 
            u.created_at, 
            u.is_verified,
            (SELECT COUNT(*) FROM form_submissions WHERE user_id = u.id) as classified_count,
            (SELECT COUNT(*) FROM businesses WHERE user_id = u.id AND deleted_at IS NULL) as business_count
        FROM users u
        WHERE u.deleted_at IS NULL
        ORDER BY u.created_at DESC
        LIMIT 1000
    ";
    $users = $pdo->query($users_sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<div class='alert-box error'>خطأ في قاعدة البيانات: " . $e->getMessage() . "</div>");
}
?>

<!-- =========================================================================
     استدعاء الملفات الخارجية (CSS & JS Libraries)
     ========================================================================= -->
<!-- مكتبة الهواتف الدولية -->
<link rel="stylesheet" href="../css/libs/intlTelInput.css">
<!-- ملف التنسيق الخاص بهذه الصفحة (سننشئه تالياً) -->
<link rel="stylesheet" href="css/admin_manage_users.css">


<!-- =========================================================================
     عرض رسائل النظام (Feedback Messages)
     ========================================================================= -->
<div class="feedback-container">
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert-box <?php echo $_SESSION['msg_type'] == 'success' ? 'success' : 'error'; ?>">
            <i class="fas <?php echo $_SESSION['msg_type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <span><?php echo $_SESSION['msg']; ?></span>
        </div>
        <?php unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
    <?php endif; ?>
</div>

<!-- =========================================================================
     1. لوحة الإحصائيات العلوية (Dashboard Stats)
     ========================================================================= -->
<div class="stats-dashboard">
    <!-- كرت إجمالي المستخدمين -->
    <div class="stat-card primary">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3>إجمالي المستخدمين</h3>
            <span class="stat-number"><?php echo number_format($stats['total_users']); ?></span>
        </div>
        <div class="stat-wave"></div>
    </div>

    <!-- كرت أصحاب المتاجر -->
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-store"></i></div>
        <div class="stat-info">
            <h3>أصحاب المتاجر</h3>
            <span class="stat-number"><?php echo number_format($stats['business_owners']); ?></span>
        </div>
        <div class="stat-wave"></div>
    </div>

    <!-- كرت الحسابات المفعلة -->
    <div class="stat-card success">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <h3>حسابات مفعلة</h3>
            <span class="stat-number"><?php echo number_format($stats['verified_users']); ?></span>
        </div>
        <div class="stat-wave"></div>
    </div>

    <!-- كرت المشتركين الجدد -->
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
        <div class="stat-info">
            <h3>جديد هذا الشهر</h3>
            <span class="stat-number">+<?php echo number_format($stats['new_this_month']); ?></span>
        </div>
        <div class="stat-wave"></div>
    </div>
</div>

<!-- =========================================================================
     2. شريط التحكم والفلترة (Control Bar)
     ========================================================================= -->
<div class="control-panel">
    <div class="search-wrapper">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="user-search" placeholder="ابحث عن اسم، بريد، أو رقم هاتف...">
    </div>

    <div class="filter-wrapper">
        <button class="filter-btn active" data-filter="all">الكل</button>
        <button class="filter-btn" data-filter="business">أصحاب متاجر</button>
        <button class="filter-btn" data-filter="regular">مستخدم عادي</button>
    </div>

    <button class="btn-main-add" onclick="openModal()">
        <i class="fas fa-plus-circle"></i> مستخدم جديد
    </button>
</div>

<!-- =========================================================================
     3. شبكة عرض المستخدمين (Users Grid)
     ========================================================================= -->
<div class="users-grid-container" id="users-grid">
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <img src="../image/no_data.svg" alt="No Data" class="empty-img">
            <h3>لا يوجد مستخدمين حالياً</h3>
            <p>ابدأ بإضافة مستخدمين جدد للنظام.</p>
        </div>
    <?php else: ?>
        <?php foreach ($users as $user): ?>
            <?php 
                $userType = ($user['business_count'] > 0) ? 'business' : 'regular'; 
                $initial = mb_substr($user['username'], 0, 1, 'UTF-8');
            ?>
            <div class="user-card" 
                 data-username="<?php echo strtolower(htmlspecialchars($user['username'])); ?>"
                 data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>"
                 data-phone="<?php echo htmlspecialchars($user['phone']); ?>"
                 data-type="<?php echo $userType; ?>">

                <div class="card-header">
                    <div class="avatar-circle"><?php echo $initial; ?></div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                        <span class="join-date">
                            <i class="far fa-calendar-alt"></i> 
                            <?php echo date('Y/m/d', strtotime($user['created_at'])); ?>
                        </span>
                    </div>
                    <?php if($userType == 'business'): ?>
                        <div class="role-badge business" title="صاحب متجر"><i class="fas fa-store"></i></div>
                    <?php endif; ?>
                </div>

                <div class="card-body">
                    <div class="contact-row">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($user['email'] ?: 'غير متوفر'); ?></span>
                    </div>
                    <div class="contact-row">
                        <i class="fas fa-phone"></i>
                        <span dir="ltr"><?php echo htmlspecialchars($user['phone']); ?></span>
                    </div>
                    
                    <div class="stats-mini-row">
                        <div class="mini-stat">
                            <span class="val"><?php echo $user['business_count']; ?></span>
                            <span class="lbl">متاجر</span>
                        </div>
                        <div class="mini-stat">
                            <span class="val"><?php echo $user['classified_count']; ?></span>
                            <span class="lbl">إعلانات</span>
                        </div>
                        <div class="mini-stat status">
                            <?php if($user['is_verified']): ?>
                                <span class="active-dot"></span> مفعل
                            <?php else: ?>
                                <span class="inactive-dot"></span> غير مفعل
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-actions">
                    <?php if (hasPermission('edit_user')): ?>
                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn-icon edit" title="تعديل">
                            <i class="fas fa-edit"></i>
                        </a>
                    <?php endif; ?>

                    <?php if (hasPermission('delete_user')): ?>
                        <form action="delete_user.php" method="POST" onsubmit="return confirm('⚠️ هل أنت متأكد من حذف هذا المستخدم نهائياً؟');">
                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="btn-icon delete" title="حذف">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="no-results" id="no-results-msg" style="display:none;">
        <i class="fas fa-search-minus"></i>
        <p>لا توجد نتائج تطابق بحثك.</p>
    </div>
</div>

<!-- =========================================================================
     4. النافذة المنبثقة لإضافة مستخدم (Add User Modal)
     ========================================================================= -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> إضافة مستخدم جديد</h3>
            <button class="modal-close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <p class="modal-hint">قم بإنشاء حساب لصاحب المتجر ليتمكن من إدارة أعماله مباشرة.</p>
            
            <form action="save_new_user.php" method="POST" id="addNewUserForm">
                <div class="form-group">
                    <label>اسم المستخدم الكامل <span class="req">*</span></label>
                    <input type="text" name="username" required placeholder="مثال: محمد الأحمد" autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label>البريد الإلكتروني <span class="opt">(اختياري)</span></label>
                    <input type="email" name="email" placeholder="name@domain.com" autocomplete="off">
                </div>
                
                <div class="form-group phone-group">
                    <label>رقم الهاتف <span class="req">*</span></label>
                    <input type="tel" id="modal_phone" class="phone-input" required>
                    <input type="hidden" name="phone" id="full_phone">
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور <span class="req">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="newPass" required placeholder="******" minlength="8" autocomplete="new-password">
                        <i class="fas fa-eye toggle-pass" onclick="togglePassword('newPass', this)"></i>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">إلغاء</button>
                    <button type="submit" class="btn-save">إنشاء الحساب</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     5. الجافاسكربت (Scripts)
     ========================================================================= -->
<script src="../js/libs/intlTelInput.min.js"></script>
<script>
    // --- إعدادات مكتبة الهواتف ---
    const phoneInput = document.querySelector("#modal_phone");
    const fullPhoneInput = document.querySelector("#full_phone");
    let iti;

    if (phoneInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: "sy",
            preferredCountries: ["sy", "ae", "sa", "jo", "lb", "tr"],
            utilsScript: "../js/libs/utils.js", // تأكد من وجود هذا الملف
            separateDialCode: true,
            formatOnDisplay: true,
        });
    }

    // --- معالجة إرسال النموذج ---
    const form = document.getElementById('addNewUserForm');
    form.addEventListener('submit', function(e) {
        if (iti) {
            if (!iti.isValidNumber()) {
                e.preventDefault();
                // يمكن إضافة كلاس للاهتزاز هنا أو رسالة خطأ
                alert("رقم الهاتف غير صحيح. يرجى التأكد منه.");
                phoneInput.focus();
                return;
            }
            fullPhoneInput.value = iti.getNumber();
        }
    });

    // --- التحكم بالمودال ---
    function openModal() {
        const modal = document.getElementById('addUserModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // منع التمرير الخلفي
    }
    function closeModal() {
        const modal = document.getElementById('addUserModal');
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
        form.reset(); // تنظيف الحقول
    }
    
    // إغلاق المودال عند النقر خارج الصندوق
    window.onclick = function(e) {
        const modal = document.getElementById('addUserModal');
        if (e.target === modal) closeModal();
    }

    // --- إظهار/إخفاء كلمة المرور ---
    function togglePassword(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }

    // --- البحث والفلترة ---
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('user-search');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const usersGrid = document.getElementById('users-grid');
        const allUsers = Array.from(usersGrid.getElementsByClassName('user-card'));
        const noResults = document.getElementById('no-results-msg');

        let currentFilter = 'all';

        function filterAndSearch() {
            const term = searchInput.value.toLowerCase().trim();
            let count = 0;

            allUsers.forEach(card => {
                const type = card.dataset.type;
                const matchFilter = (currentFilter === 'all') || (type === currentFilter);
                
                // البحث في الاسم، الإيميل، والهاتف
                const matchSearch = !term || 
                                    card.dataset.username.includes(term) || 
                                    card.dataset.email.includes(term) || 
                                    card.dataset.phone.includes(term);

                if (matchFilter && matchSearch) {
                    card.style.display = 'flex';
                    count++;
                } else {
                    card.style.display = 'none';
                }
            });

            // إظهار رسالة لا توجد نتائج
            noResults.style.display = (count === 0 && allUsers.length > 0) ? 'block' : 'none';
        }

        // أحداث البحث
        searchInput.addEventListener('keyup', filterAndSearch);

        // أحداث الفلترة
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                filterAndSearch();
            });
        });
    });
</script>

<?php include 'footer.php'; ?>