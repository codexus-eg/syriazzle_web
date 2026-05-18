<?php
// --- **استدعاء حارس البوابة الموحد** ---
require_once '../auth_guard.php';
header('Content-Type: application/json');

// --- حارس البوابة 1: التحقق من صلاحية "معالجة الدفعات" ---
if (!hasPermission('process_payouts')) {
    echo json_encode(['success' => false, 'message' => 'ليس لديك الصلاحية لتنفيذ إجراءات مالية.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طلب غير صالح.']);
    exit;
}

$user_type = $_POST['user_type'] ?? '';
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$action_type = $_POST['action_type'] ?? 'payout';
$description = htmlspecialchars($_POST['description'] ?? '');

if (empty($user_type) || !$user_id || $amount === null) {
    echo json_encode(['success' => false, 'message' => 'بيانات ناقصة.']);
    exit;
}

try {
    $table_name = ($user_type === 'business') ? 'businesses' : 'drivers';
    $balance_column = 'commission_balance';

    // --- حارس البوابة 2: التحقق من صلاحية المحافظة (لغير السوبر أدمن) ---
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM $table_name WHERE id = ? AND governorate_id = ?");
        $stmt_check->execute([$user_id, $admin_governorate_id]);
        if ($stmt_check->fetchColumn() == 0) {
            throw new Exception("لا يمكنك تنفيذ إجراء مالي لهذا المستخدم لأنه لا يتبع لمحافظتك.");
        }
    }

    $pdo->beginTransaction();
    
    $transaction_type = '';
    $final_amount = 0;
    $desc_prefix = '';

    if ($action_type === 'payout') {
        if ($amount <= 0) throw new Exception('مبلغ الدفعة يجب أن يكون أكبر من صفر.');
        $transaction_type = 'payout';
        $final_amount = $amount; // الدفعة هي مبلغ موجب يعادل الدين السالب
        $desc_prefix = 'تسجيل دفعة مستلمة';
    } elseif ($action_type === 'adjustment') {
        $transaction_type = 'adjustment';
        $final_amount = $amount; // التسوية يمكن أن تكون موجبة أو سالبة
        $desc_prefix = 'تسوية يدوية';
    } else {
        throw new Exception('إجراء غير صالح.');
    }

    // تحديث رصيد العمولة للمستخدم
    $stmt_update = $pdo->prepare("UPDATE $table_name SET $balance_column = $balance_column + ? WHERE id = ?");
    $stmt_update->execute([$final_amount, $user_id]);

    // تسجيل المعاملة
    $final_description = $desc_prefix . ($description ? ': ' . $description : '');
    $stmt_transaction = $pdo->prepare(
        "INSERT INTO transactions (order_id, user_id, user_type, transaction_type, amount, description) 
         VALUES (NULL, ?, ?, ?, ?, ?)"
    );
    $stmt_transaction->execute([$user_id, $user_type, $transaction_type, $final_amount, $final_description]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'تم تنفيذ الإجراء المالي بنجاح.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'فشل الإجراء: ' . $e->getMessage()]);
}
?>