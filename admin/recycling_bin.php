<?php
$page_title = 'سلة المحذوفات';
include 'header.php';

if (!hasPermission('super_admin_access_all')) {
    echo "<h2>وصول غير مصرح به. هذه الصفحة مخصصة للمدير العام فقط.</h2>";
    include 'footer.php';
    exit;
}

try {
    // 1. جلب المتاجر المحذوفة
    $stmt_businesses = $pdo->prepare("
        SELECT b.id, b.name, b.category, b.deleted_at, g.name AS governorate_name
        FROM businesses b LEFT JOIN governorates g ON b.governorate_id = g.id
        WHERE b.deleted_at IS NOT NULL ORDER BY b.deleted_at DESC
    ");
    $stmt_businesses->execute();
    $deleted_businesses = $stmt_businesses->fetchAll(PDO::FETCH_ASSOC);

    // 2. جلب السائقين المحذوفين
    $stmt_drivers = $pdo->prepare("
        SELECT d.id, d.full_name, d.phone, d.deleted_at, g.name AS governorate_name
        FROM drivers d LEFT JOIN governorates g ON d.governorate_id = g.id
        WHERE d.deleted_at IS NOT NULL ORDER BY d.deleted_at DESC
    ");
    $stmt_drivers->execute();
    $deleted_drivers = $stmt_drivers->fetchAll(PDO::FETCH_ASSOC);

    // 3. **(جديد)** جلب المستخدمين المحذوفين
    $stmt_users = $pdo->prepare("
        SELECT id, username, email, phone, deleted_at
        FROM users
        WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC
    ");
    $stmt_users->execute();
    $deleted_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("خطأ في جلب البيانات: " . $e->getMessage());
}
?>
<style>
    /* تصميم التبويبات الحديث */
    .tabs-container {
        width: 100%;
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .tab-nav {
        display: flex;
        background-color: #bbbbbb78;
        border-bottom: 1px solid #dee2e6;
    }
    .tab-btn {
        padding: 15px 25px;
        cursor: pointer;
        border: none;
        background-color: transparent;
        font-size: 17px;
        font-weight: 600;
        color: black;
        position: relative;
        transition: color 0.3s, background-color 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tab-btn:hover {
        background-color: #e9ecef;
        color: #212529;
    }
    .tab-btn.active {
        color: #0d6efd;
        background-color: #fff;
    }
    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background-color: #0d6efd;
    }
    .tab-content {
        display: none;
        padding: 25px;
        animation: fadeIn 0.4s ease-in-out;
    }
    .tab-content.active {
        display: block;
    }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .page-header {
        margin-bottom:20px;
    }
    /* تصميم شريط البحث الأنيق */
    .search-bar {
        margin-bottom: 20px;
        position: relative;
    }
    .search-bar input {
        width: 100%;
        max-width: 450px;
        padding: 12px 45px 12px 20px;
        border: 1px solid #ced4da;
        border-radius: 25px; /* شكل بيضاوي */
        font-size: 16px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .search-bar input:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        outline: none;
    }
    .search-bar .fa-search {
        position: absolute;
        top: 50%;
        right: 20px;
        transform: translateY(-50%);
        color: #6c757d;
    }

    /* تصميم زر الاستعادة المحسّن */
    .restore-btn {
        background: linear-gradient(45deg, #198754, #146c43);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .restore-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    
    /* تصميم محسّن للحالة الفارغة */
    .empty-state {
        text-align: center;
        padding: 50px;
        background-color: #f8f9fa;
        border: 2px dashed #e9ecef;
        border-radius: 8px;
    }
    .empty-state i {
        font-size: 48px;
        color: #adb5bd;
        margin-bottom: 15px;
        display: block;
    }
    .empty-state p {
        font-size: 18px;
        font-weight: 600;
        color: #6c757d;
        margin: 0;
    }

    /* تحسينات على الجدول */
    .data-table table {
        border-collapse: separate;
        border-spacing: 0 5px; /* مسافة بين الصفوف */
    }
    .data-table th {
        background-color: #f8f9fa;
        font-weight: 700;
        text-align: right;
    }
    .data-table td, .data-table th {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
    }
    .data-table tbody tr {
        background-color: #fff;
        transition: background-color 0.2s;
    }
    .data-table tbody tr:hover {
        background-color: #f1f3f5;
    }

</style>

<div class="page-header">
    <h1><i class="fas fa-recycle" style="color:#0d6efd;"></i> سلة المحذوفات</h1>
    <p style="color: #6c757d; margin: 0; font-size: 16px;">هنا يمكنك مراجعة واستعادة المتاجر والسائقين الذين تم حذفهم.</p>
</div>

<div class="tabs-container">
    <!-- أزرار التحكم بالتبويبات -->
    <div class="tab-nav">
        <button class="tab-btn active" data-target="businesses-tab">
            <i class="fas fa-store-slash"></i> المتاجر (<?php echo count($deleted_businesses); ?>)
        </button>
        <button class="tab-btn" data-target="drivers-tab">
            <i class="fas fa-motorcycle"></i> السائقون (<?php echo count($deleted_drivers); ?>)
        </button>
        <button class="tab-btn" data-target="users-tab">
            <i class="fas fa-user-slash"></i> المستخدمون (<?php echo count($deleted_users); ?>)
        </button>
    </div>

    <!-- محتوى تبويب المتاجر -->
    <div id="businesses-tab" class="tab-content active">
        <div class="search-bar">
            <input type="text" id="business-search" placeholder="ابحث بالاسم، الفئة، أو المحافظة...">
            <i class="fas fa-search"></i>
        </div>
        <div class="data-table">
            <table id="business-table">
                <thead>
                    <tr>
                        <th>اسم المتجر</th><th>الفئة</th><th>المحافظة</th><th>تاريخ الحذف</th><th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_businesses)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-store-slash"></i><p>سلة محذوفات المتاجر فارغة.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($deleted_businesses as $business): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($business['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($business['category']); ?></td>
                            <td><?php echo htmlspecialchars($business['governorate_name'] ?? 'غير محدد'); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($business['deleted_at'])); ?></td>
                            <td>
                                <form action="restore_item.php" method="POST" onsubmit="return confirm('هل أنت متأكد من استعادة متجر \'<?php echo htmlspecialchars(addslashes($business['name'])); ?>\'؟')">
                                    <input type="hidden" name="item_id" value="<?php echo $business['id']; ?>">
                                    <input type="hidden" name="item_type" value="business">
                                    <button type="submit" class="restore-btn"><i class="fas fa-undo"></i> استعادة</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- محتوى تبويب السائقين -->
    <div id="drivers-tab" class="tab-content">
        <div class="search-bar">
            <input type="text" id="driver-search" placeholder="ابحث بالاسم، الهاتف، أو المحافظة...">
            <i class="fas fa-search"></i>
        </div>
        <div class="data-table">
            <table id="driver-table">
                <thead>
                    <tr>
                        <th>اسم السائق</th><th>الهاتف</th><th>المحافظة</th><th>تاريخ الحذف</th><th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_drivers)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-motorcycle"></i><p>سلة محذوفات السائقين فارغة.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($deleted_drivers as $driver): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($driver['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($driver['phone']); ?></td>
                            <td><?php echo htmlspecialchars($driver['governorate_name'] ?? 'غير محدد'); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($driver['deleted_at'])); ?></td>
                            <td>
                                <form action="restore_item.php" method="POST" onsubmit="return confirm('هل أنت متأكد من استعادة السائق \'<?php echo htmlspecialchars(addslashes($driver['full_name'])); ?>\'؟')">
                                    <input type="hidden" name="item_id" value="<?php echo $driver['id']; ?>">
                                    <input type="hidden" name="item_type" value="driver">
                                    <button type="submit" class="restore-btn"><i class="fas fa-undo"></i> استعادة</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- محتوى تبويب المستخدمين -->
    <div id="users-tab" class="tab-content">
        <div class="search-bar">
            <input type="text" id="user-search" placeholder="ابحث بالاسم، الإيميل، أو الهاتف...">
            <i class="fas fa-search"></i>
        </div>
        <div class="data-table">
            <table id="user-table">
                <thead>
                    <tr>
                        <th>اسم المستخدم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الهاتف</th>
                        <th>تاريخ الحذف</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deleted_users)): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-user-slash"></i><p>سلة محذوفات المستخدمين فارغة.</p></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($deleted_users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email'] ?: '<em>غير محدد</em>'); ?></td>
                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['deleted_at'])); ?></td>
                            <td>
                                <form action="restore_item.php" method="POST" onsubmit="return confirm('هل أنت متأكد من استعادة المستخدم \'<?php echo htmlspecialchars(addslashes($user['username'])); ?>\'؟')">
                                    <input type="hidden" name="item_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="item_type" value="user">
                                    <button type="submit" class="restore-btn"><i class="fas fa-undo"></i> استعادة</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- منطق التحكم بالتبويبات (Tabs) ---
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(button.dataset.target).classList.add('active');
        });
    });

    // --- منطق البحث الفوري (Live Search) ---
    function setupSearchFilter(inputId, tableId) {
        const searchInput = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        const rows = table.querySelectorAll('tbody tr');
        const emptyStateRow = table.querySelector('tbody .empty-state')?.closest('tr');

        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleRows = 0;
            
            rows.forEach(row => {
                // تجاهل سطر "الحالة الفارغة" من البحث
                if (row.contains(emptyStateRow)) return;

                const rowText = row.textContent.toLowerCase();
                if (rowText.includes(searchTerm)) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });

            // إظهار أو إخفاء "الحالة الفارغة" بناءً على نتائج البحث
            if (emptyStateRow) {
                emptyStateRow.style.display = (visibleRows === 0) ? '' : 'none';
            }
        });
    }

    // تفعيل البحث لكلا الجدولين
    setupSearchFilter('business-search', 'business-table');
    setupSearchFilter('driver-search', 'driver-table');
    setupSearchFilter('user-search', 'user-table');
});
</script>

<?php include 'footer.php'; ?>