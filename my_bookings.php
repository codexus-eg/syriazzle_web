<?php
// ========================================================================
// Syriazzle - صفحة حجوزاتي (النسخة 3.0 - تدعم الأصول والخدمات)
// ========================================================================
require_once 'php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect_url=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
$status_filter = $_GET['status'] ?? 'all';

$sql_where_clause = '';
$params = [$current_user_id];

if ($status_filter !== 'all') {
    $sql_where_clause = 'AND b.status = ?';
    $params[] = $status_filter;
}

try {
    // **الاستعلام الشامل والمطور لجلب بيانات الحجز بشكل صحيح**
    $stmt = $pdo->prepare("
        SELECT 
            b.id, 
            b.status, 
            b.start_datetime, 
            b.total_price, 
            b.created_at, 
            biz.name as business_name, 
            biz.logo_image,
            -- استخدام COALESCE لاختيار اسم الأصل أولاً، ثم اسم الخدمة
            COALESCE(r.name, s.name) as item_name
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        JOIN businesses biz ON s.business_id = biz.id
        -- استخدام LEFT JOIN هنا لأن الحجوزات القائمة على المواعيد ليس لها أصل
        LEFT JOIN business_resources r ON b.resource_id = r.id
        WHERE b.user_id = ? {$sql_where_clause} 
        ORDER BY b.created_at DESC
    ");
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) { 
    die("حدث خطأ أثناء تحميل قائمة حجوزاتك: " . $e->getMessage()); 
}

function get_status_details($status) {
    switch ($status) {
        case 'confirmed': return ['text' => 'مؤكد', 'class' => 'confirmed'];
        case 'pending_confirmation': return ['text' => 'بانتظار المراجعة', 'class' => 'pending'];
        case 'pending_payment': return ['text' => 'بانتظار الدفع', 'class' => 'pending'];
        case 'cancelled_by_user': return ['text' => 'ملغي', 'class' => 'cancelled'];
        case 'cancelled_by_system': return ['text' => 'ملغي (النظام)', 'class' => 'cancelled'];
        case 'cancelled_by_admin': return ['text' => 'ملغي (الإدارة)', 'class' => 'cancelled'];
        default: return ['text' => ucfirst($status), 'class' => 'default'];
    }
}

$page_title = 'حجوزاتي';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Syriazzle</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/my_bookings.css">
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="bookings-container">
        <h1><i class="fas fa-receipt"></i> حجوزاتي</h1>
        <p>هنا يمكنك متابعة حالة كل حجوزاتك الحالية والسابقة.</p>
        <div class="bookings-list">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings-card">
                    <i class="fas fa-calendar-times"></i>
                    <h2>لا يوجد لديك أي حجوزات بعد</h2>
                    <p>يبدو أنك لم تقم بأي عملية حجز حتى الآن. ابدأ باستكشاف خدماتنا!</p>
                    <a href="index.html" class="btn btn-primary">استكشف الخدمات الآن</a>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): 
                    $status_details = get_status_details($booking['status']);
                ?>
                    <div class="booking-card">
                        <div class="card-main-info">
                            <img src="<?php echo htmlspecialchars($booking['logo_image'] ?? 'image/default_logo.png'); ?>" alt="شعار المتجر" class="business-logo">
                            <div class="booking-summary">
                                <h3><?php echo htmlspecialchars($booking['business_name']); ?></h3>
                                <!-- **استخدام item_name الجديد** -->
                                <p><?php echo htmlspecialchars($booking['item_name']); ?></p>
                                <div class="booking-meta">
                                    <!-- **عرض التاريخ والوقت معًا بشكل منسق** -->
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo (new DateTime($booking['start_datetime']))->format('Y-m-d'); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo (new DateTime($booking['start_datetime']))->format('h:i A'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-details-info">
                            <div class="detail-item"><span class="label">الحالة:</span><span class="status-badge <?php echo $status_details['class']; ?>"><?php echo $status_details['text']; ?></span></div>
                            <div class="detail-item"><span class="label">الإجمالي:</span><strong class="price"><?php echo number_format($booking['total_price']); ?> ل.س</strong></div>
                             <div class="detail-item"><span class="label">رقم الحجز:</span><strong>#<?php echo $booking['id']; ?></strong></div>
                        </div>
                        <div class="card-actions">
                            <!-- **التغيير هنا:** الزر الآن يشغل دالة JavaScript بدلاً من الانتقال لرابط -->
                            <button class="btn btn-secondary view-details-btn" data-booking-id="<?php echo $booking['id']; ?>">عرض التفاصيل</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- =========== هيكل النافذة المنبثقة (Modal) - جديد =========== -->
    <div class="modal-overlay" id="details-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">تفاصيل الحجز</h3>
                <button class="modal-close-btn" id="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body" id="modal-body-content">
                <!-- سيتم ملء المحتوى هنا بواسطة JavaScript -->
                <div class="spinner-container"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
            <div class="modal-footer" id="modal-footer-content">
                 <!-- يمكن إضافة أزرار هنا لاحقاً، مثل زر "إلغاء الحجز" -->
            </div>
        </div>
    </div>

    <!-- =========== كود JavaScript لتشغيل النافذة - جديد =========== -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('details-modal');
        const modalTitle = document.getElementById('modal-title');
        const modalBody = document.getElementById('modal-body-content');
        const closeBtn = document.getElementById('modal-close-btn');
        const detailsButtons = document.querySelectorAll('.view-details-btn');

        // دالة لفتح النافذة
        function openModal() {
            modal.classList.add('active');
        }

        // دالة لإغلاق النافذة
        function closeModal() {
            modal.classList.remove('active');
            // تفريغ المحتوى عند الإغلاق
            modalBody.innerHTML = '<div class="spinner-container"><i class="fas fa-spinner fa-spin"></i></div>';
        }

        // ربط الأحداث
        detailsButtons.forEach(button => {
            button.addEventListener('click', function() {
                const bookingId = this.dataset.bookingId;
                openModal();
                
                // جلب البيانات من الخادم
                fetch('php/ajax_get_booking_details.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ booking_id: bookingId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalTitle.textContent = `تفاصيل الحجز رقم #${data.details.id}`;
                        modalBody.innerHTML = data.html;
                    } else {
                        modalBody.innerHTML = `<p style="color:red;">${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    modalBody.innerHTML = '<p style="color:red;">حدث خطأ أثناء الاتصال بالخادم.</p>';
                });
            });
        });

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    });
    </script>
</body>
</html>