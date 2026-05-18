<?php
// ========================================================================
// Syriazzle Admin - Centralized Notifications Log (V4.0 - Premium UI)
// ========================================================================

$page_title = 'سجل الإشعارات الإدارية';
require_once 'header.php'; // يتضمن auth_guard و db_connect وتنسيقات الهيدر

// 1. التحقق من الصلاحية (نستخدم صلاحية عرض الحجوزات أو الإدارة العامة)
if (!hasPermission('view_bookings')) {
    echo "<div class='status-message error'><p>عذراً، لا تملك صلاحية الوصول لهذه الصفحة.</p></div>";
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];

// 2. معالجة الإجراءات (تحديد كقروء أو حذف)
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'mark_all_read') {
        $pdo->prepare("UPDATE site_notifications SET is_read = 1 WHERE user_id = ? AND user_type = 'user'")
            ->execute([$admin_id]);
        header("Location: notifications.php?msg=marked");
        exit;
    } elseif ($_GET['action'] === 'clear_all') {
        $pdo->prepare("DELETE FROM site_notifications WHERE user_id = ? AND user_type = 'user'")
            ->execute([$admin_id]);
        header("Location: notifications.php?msg=cleared");
        exit;
    }
}

try {
    // ============================================================
    // 3. تطبيق "نمط الاستعلام القياسي" (Standard Query Pattern)
    // ============================================================
    // ملاحظة: الفلترة الجغرافية تمت برمجياً عند إرسال الإشعار لـ user_id محدد
    // هنا نجلب كافة الإشعارات الخاصة بالأدمن الحالي فقط لضمان الخصوصية
    $sql = "
        SELECT *, 
            CASE 
                WHEN TIMESTAMPDIFF(SECOND, created_at, NOW()) < 60 THEN 'الآن'
                WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 60 THEN CONCAT('منذ ', TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' دقيقة')
                WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN CONCAT('منذ ', TIMESTAMPDIFF(HOUR, created_at, NOW()), ' ساعة')
                WHEN TIMESTAMPDIFF(DAY, created_at, NOW()) < 7 THEN CONCAT('منذ ', TIMESTAMPDIFF(DAY, created_at, NOW()), ' أيام')
                ELSE DATE_FORMAT(created_at, '%Y/%m/%d %H:%i')
            END as time_ago 
        FROM site_notifications 
        WHERE user_id = ? AND user_type = 'user' 
        ORDER BY created_at DESC 
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$admin_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Notifications Page Error: " . $e->getMessage());
    $notifications = [];
}
?>

<style>
    /* تنسيقات صفحة الإشعارات الاحترافية */
    .notif-page-card {
        background: #fff;
        border-radius: 25px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        padding: 30px;
        border: 1px solid #edf2f7;
    }

    .notif-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f8f9fa;
    }

    .notif-page-header h2 { margin: 0; font-size: 1.6rem; font-weight: 900; color: var(--sy-red-dark); }

    .header-btns { display: flex; gap: 10px; }
    .btn-action-outline {
        padding: 10px 20px;
        border-radius: 12px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 800;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-mark { color: var(--primary-blue); background: #f0f7ff; border: 1px solid #d0e7ff; }
    .btn-mark:hover { background: var(--primary-blue); color: #fff; }
    .btn-clear { color: var(--danger-red); background: #fff1f1; border: 1px solid #ffdada; }
    .btn-clear:hover { background: var(--danger-red); color: #fff; }

    /* سجل الإشعارات */
    .notif-full-list { display: flex; flex-direction: column; gap: 15px; }
    .notif-row-item {
        display: flex;
        align-items: flex-start;
        gap: 20px;
        padding: 20px;
        background: #fff;
        border-radius: 20px;
        border: 1px solid #f1f4f8;
        transition: 0.3s;
        text-decoration: none;
        color: inherit;
        position: relative;
    }
    .notif-row-item:hover { transform: translateY(-3px); box-shadow: 0 5px 20px rgba(0,0,0,0.05); border-color: #d0e7ff; }
    .notif-row-item.unread { background: #fffcfc; border-right: 5px solid var(--sy-red-main); }
    
    .notif-icon-box {
        width: 50px; height: 50px; border-radius: 15px; background: #f8f9fa;
        display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        flex-shrink: 0; color: #aaa;
    }
    .unread .notif-icon-box { background: #fff1f1; color: var(--sy-red-main); }

    .notif-content-box { flex-grow: 1; }
    .notif-content-box h4 { margin: 0 0 5px 0; font-size: 1.05rem; font-weight: 800; color: #2d3436; }
    .notif-content-box p { margin: 0; color: #636e72; line-height: 1.6; font-size: 0.95rem; }
    .notif-time-tag { margin-top: 10px; font-size: 0.75rem; color: #b2bec3; display: flex; align-items: center; gap: 5px; }

    .notif-badge-new {
        background: var(--sy-red-main); color: #fff; padding: 2px 8px;
        border-radius: 6px; font-size: 10px; font-weight: 900; margin-right: 10px;
    }

    .empty-state { text-align: center; padding: 100px 20px; color: #ccc; }
    .empty-state i { font-size: 5rem; margin-bottom: 20px; opacity: 0.2; }
</style>

<div class="notif-page-card">
    <div class="notif-page-header">
        <div>
            <h2>إشعارات النظام</h2>
            <p>لديك <?php echo $unread_notifications_count; ?> إشعار جديد يحتاج للمراجعة</p>
        </div>
        <div class="header-btns">
            <?php if (!empty($notifications)): ?>
                <a href="?action=mark_all_read" class="btn-action-outline btn-mark">
                    <i class="fas fa-check-double"></i> تحديد الكل كمقروء
                </a>
                <a href="?action=clear_all" class="btn-action-outline btn-clear" onclick="return confirm('هل أنت متأكد من حذف كافة الإشعارات؟')">
                    <i class="fas fa-trash-alt"></i> مسح السجل
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h3>لا توجد إشعارات حالياً</h3>
            <p>سيتم إعلامك هنا عند حدوث أي نشاط جديد يتطلب انتباهك.</p>
        </div>
    <?php else: ?>
        <div class="notif-full-list">
            <?php foreach ($notifications as $n): 
                // تحديد الأيقونة بناءً على نوع الإشعار (مثال بسيط)
                $icon = 'fa-bell';
                if (strpos($n['title'], 'تسوية') !== false) $icon = 'fa-money-bill-wave';
                if (strpos($n['title'], 'طلب') !== false) $icon = 'fa-shopping-cart';
            ?>
                <a href="<?php echo $n['link'] ? $n['link'] : '#'; ?>" class="notif-row-item <?php echo $n['is_read'] == 0 ? 'unread' : ''; ?>">
                    <div class="notif-icon-box">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="notif-content-box">
                        <h4>
                            <?php echo htmlspecialchars($n['title']); ?>
                            <?php if ($n['is_read'] == 0): ?>
                                <span class="notif-badge-new">جديد</span>
                            <?php endif; ?>
                        </h4>
                        <p><?php echo htmlspecialchars($n['message']); ?></p>
                        <div class="notif-time-tag">
                            <i class="far fa-clock"></i>
                            <?php echo $n['time_ago']; ?>
                        </div>
                    </div>
                    <div class="notif-arrow">
                        <i class="fas fa-chevron-left" style="color:#eee;"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>