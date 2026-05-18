<?php
// ========================================================================
// Syriazzle Admin - مركز التحكم بالحجوزات (النسخة النهائية 3.0 - مع الإلغاء)
// ========================================================================
$page_title = 'إدارة الحجوزات';
require_once 'header.php'; // يستدعي auth_guard.php تلقائياً ويرسم الواجهة

// --- حارس الصلاحيات: التحقق من صلاحية عرض الحجوزات ---
if (!hasPermission('view_bookings')) {
    echo "<div class='container'><h2><i class='fas fa-exclamation-triangle'></i> وصول مرفوض</h2><p>أنت لا تملك الصلاحيات اللازمة لعرض هذه الصفحة.</p></div>";
    require_once 'footer.php';
    exit;
}

// --- إعدادات الفلترة والبحث ---
$filter_status = $_GET['status'] ?? 'all'; // الافتراضي هو عرض "الكل"
$search_query = trim($_GET['search'] ?? '');

$pdo_params = [];
$where_clauses = [];

// الفلترة الجغرافية التلقائية بناءً على جلسة الموظف
if (!$is_super_admin && isset($_SESSION['admin_governorate_id'])) {
    $where_clauses[] = "biz.governorate_id = ?";
    $pdo_params[] = $_SESSION['admin_governorate_id'];
}

if (!empty($filter_status) && $filter_status !== 'all') {
    $where_clauses[] = "b.status = ?";
    $pdo_params[] = $filter_status;
}

if (!empty($search_query)) {
    $where_clauses[] = "(b.id LIKE ? OR u.username LIKE ? OR biz.name LIKE ?)";
    $search_param = "%{$search_query}%";
    array_push($pdo_params, $search_param, $search_param, $search_param);
}

$sql_where = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// --- جلب البيانات من قاعدة البيانات ---
try {
    // استخدام LEFT JOIN لضمان ظهور كل الحجوزات
    $stmt = $pdo->prepare("
        SELECT 
            b.id, b.status, b.created_at, b.total_price,
            u.username as user_name,
            biz.name as business_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN business_services s ON b.service_id = s.id
        LEFT JOIN businesses biz ON s.business_id = biz.id
        {$sql_where}
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($pdo_params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}

function getStatusBadge($status) {
    $map = [
        'pending_confirmation' => ['text' => 'بانتظار المراجعة', 'class' => 'pending'],
        'confirmed' => ['text' => 'مؤكد', 'class' => 'confirmed'],
        'cancelled_by_user' => ['text' => 'ملغي (المستخدم)', 'class' => 'cancelled'],
        'cancelled_by_system' => ['text' => 'ملغي (النظام)', 'class' => 'cancelled'],
        'cancelled_by_admin' => ['text' => 'ملغي (الإدارة)', 'class' => 'cancelled'],
        'pending_payment' => ['text' => 'بانتظار الدفع', 'class' => 'pending'],
    ];
    return $map[$status] ?? ['text' => ucfirst($status ?: 'غير محدد'), 'class' => 'default'];
}
?>

<link rel="stylesheet" href="css/manage_bookings.css">

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-receipt"></i> إدارة الحجوزات</h1>
        <p>مراجعة وتأكيد حجوزات الأنشطة التجارية وتتبع حالتها.</p>
    </div>

    <!-- بطاقة الفلاتر -->
    <div class="filter-card">
        <form action="manage_bookings.php" method="GET">
            <div class="filter-group">
                <label for="status-filter">الحالة:</label>
                <select id="status-filter" name="status">
                    <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>الكل</option>
                    <option value="pending_confirmation" <?php echo ($filter_status == 'pending_confirmation') ? 'selected' : ''; ?>>بانتظار المراجعة</option>
                    <option value="confirmed" <?php echo ($filter_status == 'confirmed') ? 'selected' : ''; ?>>مؤكد</option>
                    <option value="cancelled_by_admin" <?php echo ($filter_status == 'cancelled_by_admin') ? 'selected' : ''; ?>>ملغي (الإدارة)</option>
                    <option value="cancelled_by_system" <?php echo ($filter_status == 'cancelled_by_system') ? 'selected' : ''; ?>>ملغي (النظام)</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="search-input">بحث:</label>
                <input type="text" id="search-input" name="search" placeholder="رقم الحجز، اسم الزبون، اسم النشاط..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> تطبيق</button>
        </form>
    </div>

    <!-- جدول عرض الحجوزات -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>رقم الحجز</th>
                    <th>الزبون</th>
                    <th>النشاط التجاري</th>
                    <th>تاريخ الطلب</th>
                    <th>الإجمالي</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="7" class="no-data">لا توجد حجوزات تطابق معايير البحث الحالية.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): 
                        $status_info = getStatusBadge($booking['status']);
                    ?>
                        <tr>
                            <td><strong>#<?php echo $booking['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($booking['user_name'] ?? 'مستخدم محذوف'); ?></td>
                            <td><?php echo htmlspecialchars($booking['business_name'] ?? 'نشاط محذوف'); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($booking['created_at'])); ?></td>
                            <td><?php echo number_format($booking['total_price']); ?> ل.س</td>
                            <td><span class="status-badge <?php echo $status_info['class']; ?>"><?php echo $status_info['text']; ?></span></td>
                            <td class="actions">
                                <?php if ($booking['status'] == 'pending_confirmation' && hasPermission('confirm_booking_payment')): ?>
                                    <button class="btn btn-action review-btn" data-booking-id="<?php echo $booking['id']; ?>" data-action="review">
                                        <i class="fas fa-check-double"></i> مراجعة
                                    </button>
                                <?php elseif ($booking['status'] == 'confirmed' && hasPermission('edit_booking_status')): ?>
                                    <button class="btn btn-danger cancel-btn" data-booking-id="<?php echo $booking['id']; ?>" data-action="cancel">
                                        <i class="fas fa-ban"></i> إلغاء
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary view-btn" data-booking-id="<?php echo $booking['id']; ?>" data-action="view">
                                        <i class="fas fa-eye"></i> عرض
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- نافذة المراجعة والعرض -->
<div class="modal-overlay" id="details-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">مراجعة الحجز #<span id="modal-booking-id-details"></span></h3>
            <button class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" id="modal-body-content-details">
            <div class="spinner-container"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
        <div class="modal-footer" id="modal-footer-content-details">
            <form id="confirmation-form">
                <input type="hidden" name="booking_id" id="form_booking_id">
                <div id="rejection-reason-container" style="display: none; margin-bottom: 1rem;">
                    <label for="rejection_reason">سبب الرفض (إلزامي):</label>
                    <textarea name="rejection_reason" id="rejection_reason" placeholder="مثال: إثبات الدفع غير واضح أو غير صحيح."></textarea>
                </div>
                <div class="modal-actions">
                     <button type="button" class="btn btn-danger" id="reject-btn"><i class="fas fa-times"></i> رفض</button>
                     <button type="button" class="btn btn-success" id="approve-btn"><i class="fas fa-check"></i> موافقة وتأكيد</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تأكيد الإلغاء -->
<div class="modal-overlay" id="cancel-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>إلغاء الحجز #<span id="cancel-booking-id"></span></h3>
            <button class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body">
            <p><strong>تحذير:</strong> أنت على وشك إلغاء هذا الحجز. سيتم عكس أي معاملات مالية مرتبطة به. لا يمكن التراجع عن هذا الإجراء.</p>
            <form id="cancel-form">
                <input type="hidden" name="booking_id" id="form_cancel_booking_id">
                <div class="form-group">
                    <label for="cancel-reason">سبب الإلغاء (إلزامي، سيظهر لصاحب النشاط)</label>
                    <textarea id="cancel-reason" name="reason" rows="3" required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close-btn">تراجع</button>
            <button type="button" class="btn btn-danger" id="confirm-cancel-btn">تأكيد الإلغاء</button>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailsModal = document.getElementById('details-modal');
    const cancelModal = document.getElementById('cancel-modal');
    
    // --- منطق فتح النوافذ ---
    document.querySelector('.table-container').addEventListener('click', function(e) {
        const btn = e.target.closest('.review-btn, .view-btn, .cancel-btn');
        if (!btn) return;

        const bookingId = btn.dataset.bookingId;
        const action = btn.dataset.action;

        if (action === 'review' || action === 'view') {
            openDetailsModal(bookingId, action);
        } else if (action === 'cancel') {
            openCancelModal(bookingId);
        }
    });

    // --- إدارة نافذة التفاصيل/المراجعة ---
    function openDetailsModal(bookingId, action) {
        detailsModal.classList.add('active');
        const titleText = (action === 'review') ? 'مراجعة الحجز #' : 'تفاصيل الحجز #';
        detailsModal.querySelector('#modal-title').innerHTML = titleText + '<span id="modal-booking-id-details">' + bookingId + '</span>';
        detailsModal.querySelector('#form_booking_id').value = bookingId;
        
        detailsModal.querySelector('#modal-footer-content-details').style.display = (action === 'review') ? 'block' : 'none';
        
        const modalBody = detailsModal.querySelector('#modal-body-content-details');
        modalBody.innerHTML = '<div class="spinner-container"><i class="fas fa-spinner fa-spin"></i></div>';
        
        fetch('php/ajax_get_booking_review_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ booking_id: bookingId, action: action })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modalBody.innerHTML = data.html;
            } else {
                modalBody.innerHTML = `<p class="error-message">${data.message || 'حدث خطأ غير متوقع.'}</p>`;
            }
        })
        .catch(err => {
             modalBody.innerHTML = `<p class="error-message">فشل الاتصال بالخادم. يرجى التحقق من اتصالك بالإنترنت.</p>`;
        });
    }

    // --- إدارة نافذة الإلغاء الجديدة ---
    function openCancelModal(bookingId) {
        cancelModal.classList.add('active');
        cancelModal.querySelector('#cancel-booking-id').textContent = bookingId;
        cancelModal.querySelector('#form_cancel_booking_id').value = bookingId;
        cancelModal.querySelector('#cancel-reason').value = ''; // تفريغ الحقل
    }

    // --- منطق إغلاق النوافذ ---
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay') || e.target.classList.contains('modal-close-btn')) {
                modal.classList.remove('active');
            }
        });
    });
    
    // --- منطق تأكيد المراجعة (موافقة/رفض) ---
    const approveBtn = document.getElementById('approve-btn');
    const rejectBtn = document.getElementById('reject-btn');

    rejectBtn.addEventListener('click', function() {
        const rejectionContainer = document.getElementById('rejection-reason-container');
        if (rejectionContainer.style.display === 'block') {
            const reasonTextarea = document.getElementById('rejection_reason');
            if (reasonTextarea.value.trim() === '') {
                alert('الرجاء كتابة سبب الرفض.');
                reasonTextarea.focus();
                return;
            }
            submitConfirmation('reject', reasonTextarea.value);
        } else {
            rejectionContainer.style.display = 'block';
        }
    });

    approveBtn.addEventListener('click', function() {
        submitConfirmation('approve');
    });

    function submitConfirmation(action, reason = '') {
        const form = document.getElementById('confirmation-form');
        const formData = new FormData(form);
        formData.append('action', action);
        if (reason) formData.append('rejection_reason', reason);

        approveBtn.disabled = true;
        rejectBtn.disabled = true;
        approveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جار المعالجة...';

        fetch('php/process_booking_confirmation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) return response.json().then(err => { throw new Error(err.message) });
            return response.json();
        })
        .then(data => {
            alert(data.message);
            if (data.success) window.location.reload();
        })
        .catch(err => {
            alert('فشل الإجراء: ' + err.message);
            approveBtn.disabled = false;
            rejectBtn.disabled = false;
            approveBtn.innerHTML = '<i class="fas fa-check"></i> موافقة وتأكيد';
        });
    }

    // --- منطق تأكيد الإلغاء ---
    document.getElementById('confirm-cancel-btn').addEventListener('click', async function() {
        const form = document.getElementById('cancel-form');
        const formData = new FormData(form);
        const reason = formData.get('reason').trim();
        const thisBtn = this;

        if (reason === '') {
            alert('الرجاء إدخال سبب الإلغاء.');
            return;
        }

        thisBtn.disabled = true;
        thisBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جار الإلغاء...';

        try {
            const response = await fetch('php/cancel_booking_admin.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message);
            
            alert(result.message);
            window.location.reload();

        } catch (error) {
            alert('فشل الإجراء: ' + error.message);
            thisBtn.disabled = false;
            thisBtn.innerHTML = 'تأكيد الإلغاء';
        }
    });

});
</script>

<?php require_once 'footer.php'; ?>