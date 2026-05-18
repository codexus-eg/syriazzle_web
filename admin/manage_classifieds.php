<?php
$page_title = 'إدارة الإعلانات المبوبة';
// --- header.php هو المسؤول عن بدء الجلسة وتعريف الصلاحيات ---
include 'header.php'; 
// db_connect.php أصبح يتم استدعاؤه داخل header.php، لا داعي لتكراره

// --- حارس البوابة: تأكد من أن المستخدم لديه صلاحية عرض الإعلانات ---
if (!hasPermission('view_classifieds')) {
    echo "<h2>وصول غير مصرح به.</h2>"; include 'footer.php'; exit;
}

try {
    // --- 1. بناء شرط المحافظة الديناميكي ---
    $governorate_where_clause = '';
    $params = [];
    
    // **الإصلاح الحاسم هنا:** نستخدم دالة hasPermission للتحقق إذا كان سوبر أدمن
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        // أولاً، جلب الاسم النصي للمحافظة من جدول governorates
        $gov_stmt = $pdo->prepare("SELECT name FROM governorates WHERE id = ?");
        $gov_stmt->execute([$admin_governorate_id]);
        $governorate_name = $gov_stmt->fetchColumn();

        if ($governorate_name) {
            // استخدام دالة JSON_EXTRACT للبحث داخل عمود json_data
            $governorate_where_clause = "WHERE JSON_UNQUOTE(JSON_EXTRACT(fs.json_data, '$.المحافظة')) = ?";
            $params[] = $governorate_name;
        }
    }

    // --- 2. الاستعلام الرئيسي المحدث والآمن ---
    $sql = "
        SELECT 
            fs.id, fs.category, fs.sub, fs.submitted_at, 
            u.username 
        FROM form_submissions fs
        LEFT JOIN users u ON fs.user_id = u.id
        $governorate_where_clause
        ORDER BY fs.submitted_at DESC
        LIMIT 200 -- من الأفضل دائماً وضع حد أقصى
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classifieds = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("خطأ في جلب بيانات الإعلانات: " . $e->getMessage());
}
?>
<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-bottom: 30px; }
    .stat-card { background-color: #fff; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.07); border: 1px solid #e9ecef; }
    .stat-card .icon-circle { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; display: flex; justify-content: center; align-items: center; font-size: 32px; color: #fff; }
    .stat-card .info .number { font-size: 32px; font-weight: 800; color: #212529; }
    .stat-card .info .label { font-size: 16px; color: #6c757d; font-weight: 600; }
    .stat-card.blue .icon-circle { background: linear-gradient(135deg, #0d6efd, #0b5ed7); }
    .stat-card.green .icon-circle { background: linear-gradient(135deg, #198754, #146c43); }
    .stat-card.yellow .icon-circle { background: linear-gradient(135deg, #ffc107, #d39e00); }
    .stat-card.purple .icon-circle { background: linear-gradient(135deg, #6f42c1, #5a32a3); }
    
    .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .add-new-btn { background-color: #28a745; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
    
    .data-table { width: 100%; border-collapse: collapse; background-color: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.07); border-radius: 12px; overflow: hidden; }
    .data-table th, .data-table td { padding: 16px; text-align: right; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .data-table thead th { background-color: #f8f9fa; font-weight: 700; color: #495057; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background-color: #f1f3f5; }
    .actions a { color: #007bff; text-decoration: none; margin-left: 15px; font-weight: 600; }
    .actions a.delete { color: #dc3545; }
    .status-select { padding: 5px 8px; border-radius: 6px; border: 1px solid #ccc; font-family: 'Cairo', sans-serif; }
    .no-data-message { text-align: center; padding: 50px; background-color:#fff; border-radius:12px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); }
    .system-message { padding: 15px; margin-bottom: 20px; border-radius: 6px; border: 1px solid transparent; }
    .system-message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .system-message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .stat-card.red .icon-circle { background: linear-gradient(135deg, #dc3545, #b02a37); }
    .stat-card.cyan .icon-circle { background: linear-gradient(135deg, #0dcaf0, #0aa3c2); }
</style>

<div class="dashboard-header">
    <h1>الإعلانات المبوبة (الإجمالي: <?php echo count($classifieds); ?>)</h1>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>العنوان (الفئة)</th>
            <th>صاحب الإعلان</th>
            <th>تاريخ النشر</th>
            <th>إجراءات</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($classifieds as $ad): ?>
        <tr>
            <td><?php echo htmlspecialchars($ad['id']); ?></td>
            <td><strong><?php echo htmlspecialchars($ad['sub'] ?: $ad['category']); ?></strong></td>
            <td><?php echo htmlspecialchars($ad['username'] ?? '<em>غير معروف</em>'); ?></td>
            <td><?php echo date('Y-m-d', strtotime($ad['submitted_at'])); ?></td>
            <td class="actions">
                <a href="../ad_details.php?id=<?php echo $ad['id']; ?>" target="_blank" title="عرض الإعلان"><i class="fas fa-eye"></i> عرض</a>
                <?php if (hasPermission('delete_classified')): ?>
                    <a href="delete_classified.php?id=<?php echo $ad['id']; ?>" class="delete" onclick="return confirm('هل أنت متأكد من حذف هذا الإعلان؟');" title="حذف الإعلان">
                        <i class="fas fa-trash-alt"></i> حذف
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include 'footer.php'; ?>