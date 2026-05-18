<?php
// ========================================================================
// Syriazzle Admin - Process Settlement (Corrected Logic V9.0)
// المنطق الجديد: التسوية تقوم بإنقاص الرصيد (طرح) لتصفية الدين
// ========================================================================

require_once '../auth_guard.php';
header('Content-Type: application/json; charset=UTF-8');

// 1. حارس الصلاحيات
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hasPermission('process_payouts')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'وصول غير مصرح به.']);
    exit;
}

// 2. استقبال البيانات
$user_type = $_POST['user_type'] ?? '';
$user_id = (int)($_POST['user_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$description = trim($_POST['description'] ?? '');
$currency = $_POST['currency'] ?? 'SYP';

if (empty($user_type) || $user_id === 0 || $amount <= 0 || empty($description)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'البيانات غير مكتملة. المبلغ يجب أن يكون أكبر من صفر.']);
    exit;
}

$pdo->beginTransaction();
try {
    $table = ($user_type === 'business') ? 'businesses' : 'drivers';
    
    // 3. تحديد نوع الرصيد المستهدف (دين أم مستحقات؟)
    // نحن هنا نفترض أن الأدمن يرى الرصيد أمامه ويقرر التسوية
    // ولكن يجب أن نحدد أي عمود سنحدث
    
    $target_column = 'commission_balance'; // الافتراضي (ديون)
    $transaction_type = 'payment'; // الافتراضي (قبض من الشريك)

    // جلب الأرصدة الحالية لتحديد النوع بدقة
    $stmt_check = $pdo->prepare("SELECT commission_balance " . ($user_type == 'business' ? ", payouts_balance" : "") . " FROM $table WHERE id = ?");
    $stmt_check->execute([$user_id]);
    $current = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$current) throw new Exception("الشريك غير موجود.");

    // منطق ذكي لتحديد العملية بناءً على الأرصدة الحالية
    if ($user_type == 'business' && $current['payouts_balance'] > 0) {
        // المتجر له مستحقات -> المنصة تدفع للمتجر -> ننقص رصيد المستحقات
        $target_column = 'payouts_balance';
        $transaction_type = 'payout';
    } else {
        // السائق أو المتجر عليه ديون -> الشريك يدفع للمنصة -> ننقص رصيد الديون
        $target_column = 'commission_balance';
        $transaction_type = 'payment';
    }

    // 4. تحديث الرصيد (طرح المبلغ)
    // سواء كان ديناً أو مستحقات، التسوية تعني إنقاص الرقم ليصل إلى الصفر
    $stmt_update = $pdo->prepare("UPDATE $table SET $target_column = $target_column - ? WHERE id = ?");
    $stmt_update->execute([$amount, $user_id]);

    // 5. توثيق المعاملة
    // في السجل، نجعل المبلغ موجباً إذا كان قبض (payment)، وسالباً إذا كان دفع (payout) للتمييز
    $log_amount = ($transaction_type == 'payment') ? $amount : -$amount;
    $final_desc = $description . " ($currency)";

    $stmt_log = $pdo->prepare("
        INSERT INTO transactions (user_id, user_type, transaction_type, amount, description, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt_log->execute([$user_id, $user_type, $transaction_type, $log_amount, $final_desc]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'تمت عملية التسوية بنجاح وتم تحديث الرصيد.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Settlement Error: " . $e->getMessage());
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
?>