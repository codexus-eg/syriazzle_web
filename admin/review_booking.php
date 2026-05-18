<?php
// ========================================================================
// Syriazzle Admin - مراجعة تفاصيل الحجز (النسخة النهائية 1.0)
// ========================================================================
require_once 'header.php';

// --- حارس الصلاحية 1: يجب أن يمتلك صلاحية عرض الحجوزات على الأقل ---
if (!hasPermission('view_bookings')) {
    echo "<div class='auth-error'>عذرًا، أنت لا تملك الصلاحية اللازمة لعرض هذه الصفحة.</div>";
    require_once 'footer.php';
    exit;
}

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($booking_id === 0) {
    die("خطأ: رقم الحجز غير صحيح.");
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            s.name as service_name,
            biz.id as business_id, biz.name as business_name, biz.governorate_id, biz.commission_rate,
            u.username as customer_name, u.phone as customer_phone
        FROM bookings b
        JOIN business_services s ON b.service_id = s.id
        JOIN businesses biz ON s.business_id = biz.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = :booking_id
    ");
    $stmt->execute([':booking_id' => $booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        die("الحجز غير موجود.");
    }

    // --- حارس الصلاحية 2: التحقق من النطاق الجغرافي (منطق حارس البوابة المزدوج) ---
    if (!$is_super_admin && $booking['governorate_id'] != $_SESSION['admin_governorate_id']) {
        echo "<div class='auth-error'>عذرًا، هذا الحجز يقع خارج نطاق صلاحياتك الجغرافية.</div>";
        require_once 'footer.php';
        exit;
    }

} catch (PDOException $e) {
    die("خطأ في قاعدة البيانات: " . $e->getMessage());
}
?>

<div class="page-content">
    <div class="page-header">
        <h1>مراجعة الحجز رقم #<?php echo $booking['id']; ?></h1>
        <p>التفاصيل الكاملة للحجز وإجراءات التأكيد أو الرفض.</p>
    </div>

    <div class="details-grid">
        <!-- قسم تفاصيل الحجز -->
        <div class="details-card">
            <div class="card-header">
                <i class="fas fa-receipt"></i>
                <h3>تفاصيل الحجز</h3>
            </div>
            <div class="card-body">
                <div class="detail-item"><span>الخدمة:</span><strong><?php echo htmlspecialchars($booking['service_name']); ?></strong></div>
                <div class="detail-item"><span>تاريخ البدء:</span><strong><?php echo date('Y-m-d H:i', strtotime($booking['start_datetime'])); ?></strong></div>
                <div class="detail-item"><span>تاريخ الإنشاء:</span><strong><?php echo date('Y-m-d H:i', strtotime($booking['created_at'])); ?></strong></div>
                <div class="detail-item"><span>طريقة الدفع:</span><strong><?php echo htmlspecialchars($booking['payment_method']); ?></strong></div>
            </div>
        </div>
        
        <!-- قسم معلومات الزبون -->
        <div class="details-card">
            <div class="card-header">
                <i class="fas fa-user"></i>
                <h3>معلومات الزبون</h3>
            </div>
            <div class="card-body">
                <div class="detail-item"><span>الاسم:</span><strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong></div>
                <div class="detail-item"><span>رقم الهاتف:</span><strong><?php echo htmlspecialchars($booking['customer_phone'] ?? 'غير متوفر'); ?></strong></div>
            </div>
        </div>

        <!-- قسم المعلومات المالية -->
        <div class="details-card">
            <div class="card-header">
                <i class="fas fa-dollar-sign"></i>
                <h3>المعلومات المالية</h3>
            </div>
            <div class="card-body">
                <div class="detail-item"><span>السعر الإجمالي:</span><strong class="price"><?php echo number_format($booking['total_price']); ?> ل.س</strong></div>
                <div class="detail-item"><span>العربون المطلوب:</span><strong class="price"><?php echo number_format($booking['deposit_amount']); ?> ل.س</strong></div>
                <div class="detail-item"><span>نسبة عمولة المنصة:</span><strong><?php echo htmlspecialchars($booking['commission_rate']); ?>%</strong></div>
            </div>
        </div>

        <!-- قسم إثبات الدفع (الأهم) -->
        <div class="details-card payment-proof-card">
            <div class="card-header">
                <i class="fas fa-shield-alt"></i>
                <h3>إثبات الدفع (من المستخدم)</h3>
            </div>
            <div class="card-body">
                <p class="proof-text"><?php echo htmlspecialchars($booking['cancellation_reason'] ?? 'لم يقدم المستخدم إثباتًا بعد.'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- قسم الإجراءات -->
    <?php if ($booking['status'] === 'pending_confirmation' && hasPermission('confirm_booking_payment')): ?>
    <div class="actions-card">
        <h3>الإجراء المطلوب</h3>
        <p>بعد التحقق من استلام المبلغ في حسابات المنصة، يرجى اتخاذ الإجراء المناسب.</p>
        <form action="php/process_booking_confirmation.php" method="POST" class="actions-form">
            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
            
            <div class="form-group">
                <label for="rejection_reason">سبب الرفض (اختياري)</label>
                <input type="text" name="rejection_reason" id="rejection_reason" placeholder="مثال: إثبات الدفع غير واضح أو خاطئ">
            </div>

            <div class="action-buttons">
                <button type="submit" name="action" value="confirm" class="btn btn-lg btn-success">
                    <i class="fas fa-check-circle"></i> تأكيد استلام الدفعة والموافقة
                </button>
                <button type="submit" name="action" value="reject" class="btn btn-lg btn-danger">
                    <i class="fas fa-times-circle"></i> رفض الحجز
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>