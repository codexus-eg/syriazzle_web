<?php
$page_title = 'إدارة مناطق الخدمة';
include 'header.php'; 

if (!hasPermission('manage_zones')) {
    // إذا لم يكن المستخدم يملك الصلاحية، أوقف التنفيذ واعرض رسالة خطأ واضحة.
    echo "<div style='text-align:center; padding: 40px;'>
            <h2 style='color: #dc3545;'>خطأ: وصول غير مصرح به</h2>
            <p>ليس لديك الصلاحية الكافية للوصول إلى هذه الصفحة.</p>
          </div>";
    include 'footer.php'; // تضمين الفوتر لإغلاق الصفحة بشكل سليم
    exit; // إنهاء تنفيذ الكود بالكامل
}

try {
    $stmt = $pdo->query("SELECT * FROM delivery_zones ORDER BY city_name, zone_type");
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب بيانات المناطق: " . $e->getMessage());
}
?>

<!-- CSS Libraries for this page only -->
    <link rel="stylesheet" href="../css/lib/leaflet.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>

<style>
    /* Re-using styles from your manage_users.php for consistency */
    .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .data-table { width: 100%; border-collapse: collapse; background-color: #fff; box-shadow: 0 5px 20px rgba(0,0,0,0.07); border-radius: 12px; overflow: hidden; }
    .data-table th, .data-table td { padding: 16px; text-align: right; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .data-table thead th { background-color: #f8f9fa; font-weight: 700; color: #495057; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background-color: #f1f3f5; }
    .actions a, .actions button { color: #fff; text-decoration: none; font-weight: 600; padding: 8px 15px; border-radius: 6px; cursor: pointer; border: none; font-family: 'Cairo', sans-serif; }
    .actions .edit-btn { background-color: #0d6efd; }
    .zone-type-badge { padding: 5px 10px; border-radius: 20px; font-weight: 600; color: #fff; }
    .badge-standard { background-color: #198754; }
    .badge-extended { background-color: #ffc107; color: #212529; }
    
    /* Styles for the Map Modal */
    .map-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; display: none; justify-content: center; align-items: center; backdrop-filter: blur(5px); }
    .map-modal-content { 
        background: #fff; 
        border-radius: 15px; 
        width: 95%; /* أصبح أعرض */
        max-width: 1200px; /* الحد الأقصى للعرض أصبح أكبر */
        height: 90vh; /* أصبحت أطول */
        display: flex; 
        flex-direction: column; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
    }
    .map-modal-header { padding: 15px 20px; border-bottom: 1px solid #e9ecef; }
    .map-modal-header h4 { margin: 0; font-size: 20px; }
    .map-modal-body { flex-grow: 1; position: relative; }
    #mapEditor { width: 100%; height: 100%; background-color: #f0f0f0; }
    .map-modal-footer { padding: 15px; text-align: left; border-top: 1px solid #e9ecef; }
    .map-modal-btn { padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; }
    .btn-save-map { background-color: #28a745; color: #fff; }
    .btn-save-map:disabled { background-color: #ccc; }
    .btn-cancel-map { background-color: #6c757d; color: #fff; margin-left: 10px; }
    #system-message-container { position: fixed; top: 90px; left: 50%; transform: translateX(-50%); z-index: 2000; width: auto; }
    .system-message { padding: 15px 25px; border-radius: 6px; font-weight: 600; box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
    .system-message.success { background-color: #d4edda; color: #155724; }
        .map-modal-content { 
        background: #fff; 
        border-radius: 8px; 
        width: 98%;       
        max-width: 1600px; 
        height: 95vh;
        display: flex; 
        flex-direction: column; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.3); 
    }
</style>

<div id="system-message-container"></div>

<div class="dashboard-header">
    <h1>إدارة مناطق الخدمة</h1>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>اسم المدينة</th>
            <th>اسم المنطقة</th>
            <th>النوع</th>
            <th>الرسوم الإضافية</th>
            <th>الحالة</th>
            <th>إجراءات</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($zones as $zone): ?>
        <tr>
            <td><?php echo htmlspecialchars($zone['id']); ?></td>
            <td><strong><?php echo htmlspecialchars($zone['city_name']); ?></strong></td>
            <td><?php echo htmlspecialchars($zone['zone_name']); ?></td>
            <td>
                <?php if ($zone['zone_type'] == 'standard'): ?>
                    <span class="zone-type-badge badge-standard">أساسية</span>
                <?php else: ?>
                    <span class="zone-type-badge badge-extended">ممتدة</span>
                <?php endif; ?>
            </td>
            <td><?php echo number_format($zone['surcharge_fee']); ?> ل.س</td>
            <td><?php echo $zone['is_active'] ? 'فعالة' : 'معطلة'; ?></td>
            <td class="actions">
                <button class="edit-btn edit-zone-btn" 
                        data-zone-id="<?php echo $zone['id']; ?>"
                        data-zone-name="<?php echo htmlspecialchars($zone['zone_name']); ?>"
                        data-polygon='<?php echo $zone['zone_polygon']; ?>'
                        data-center-lat="<?php echo $zone['center_latitude']; ?>"  
                        data-center-lng="<?php echo $zone['center_longitude']; ?>"
                        >
                    <i class="fas fa-map-edit"></i> تعديل الخريطة
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Map Editor Modal -->
<div class="map-modal-overlay" id="mapModal">
    <div class="map-modal-content">
        <div class="map-modal-header">
            <h4 id="mapModalTitle">تعديل خريطة المنطقة</h4>
        </div>
        <div class="map-modal-body">
            <div id="mapEditor"></div>
        </div>
        <div class="map-modal-footer">
            <button class="map-modal-btn btn-cancel-map" id="cancelMapBtn">إلغاء</button>
            <button class="map-modal-btn btn-save-map" id="saveMapBtn" disabled>حفظ التغييرات</button>
        </div>
    </div>
</div>

<!-- JS Libraries for this page only -->
    <script src="../js/lib/leaflet.js"></script>
    <!-- مكتبة الرسم Draw -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>

<!-- Pass PHP data to our JS file -->
<script>
    const ZONES_DATA = <?php echo json_encode($zones, JSON_NUMERIC_CHECK); ?>;
</script>

<!-- Our custom JS for this page -->
<script src="js/zone-manager.js"></script>

<?php include 'footer.php'; ?>