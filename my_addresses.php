<?php
require_once 'php/db_connect.php';
$page_title = 'دفتر العناوين - Syriazzle';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// رسائل النظام
$msg = ''; $msg_type = '';
if (isset($_SESSION['flash_message'])) {
    $msg = $_SESSION['flash_message'];
    $msg_type = $_SESSION['flash_type'];
    unset($_SESSION['flash_message']); unset($_SESSION['flash_type']);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $stmt->execute([$current_user_id]);
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Error"); }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>دفتر العناوين</title>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/lib/leaflet.css"/>
    <link rel="stylesheet" href="css/lib/geosearch.css"/>
    <style>
        body { background-color: #f8f9fa; font-family: 'Cairo', sans-serif; padding-bottom: 40px; }
        .page-container { max-width: 800px; margin: 20px auto; padding: 0 15px; }
        
        /* تصميم الهيدر */
        .page-header { 
            display: flex; justify-content: space-between; align-items: center; 
            margin-bottom: 20px; background: #fff; padding: 15px; 
            border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .page-header h1 { font-size: 1.2rem; margin: 0; color: #333; }
        
        .add-btn {
            background: #e60000; color: #fff; text-decoration: none;
            padding: 10px 20px; border-radius: 50px; font-weight: 700; font-size: 0.9rem;
            box-shadow: 0 4px 10px rgba(230, 0, 0, 0.2);
        }

        /* كرت العنوان */
        .address-card {
            background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #eee; position: relative;
        }
        .address-card.default { border: 2px solid #28a745; background: #f9fff9; }
        
        .card-top { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 15px; }
        .icon-box { 
            width: 40px; height: 40px; background: #f0f2f5; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; color: #555;
            font-size: 1.2rem;
        }
        .addr-info h3 { margin: 0 0 5px; font-size: 1.1rem; }
        .addr-info p { margin: 0; color: #666; font-size: 0.9rem; line-height: 1.5; }
        .badge { background: #28a745; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; }

        .card-actions {
            display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #eee; padding-top: 10px;
        }
        .btn-action {
            background: none; border: none; font-family: inherit; cursor: pointer;
            font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 5px;
        }
        .edit { color: #007bff; text-decoration: none; } /* رابط */
        .delete { color: #dc3545; }
        .star { color: #ffc107; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>

    <div class="page-container">
        <?php if ($msg): ?>
            <div class="alert <?php echo $msg_type == 'error' ? 'error' : ''; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="page-header">
            <h1>عناويني</h1>
            <!-- الرابط يذهب لصفحة جديدة -->
            <a href="address_form.php?action=add" class="add-btn"><i class="fas fa-plus"></i> إضافة جديد</a>
        </div>

        <?php if (empty($addresses)): ?>
            <div style="text-align:center; padding:50px; color:#777;">
                <i class="fas fa-map-marker-alt" style="font-size:3rem; margin-bottom:15px;"></i>
                <p>لا توجد عناوين محفوظة</p>
            </div>
        <?php else: ?>
            <?php foreach ($addresses as $addr): ?>
                <div class="address-card <?php echo $addr['is_default'] ? 'default' : ''; ?>">
                    <div class="card-top">
                        <div class="icon-box"><i class="fas fa-home"></i></div>
                        <div class="addr-info">
                            <h3><?php echo htmlspecialchars($addr['address_name']); ?> <?php if($addr['is_default']) echo '<span class="badge">الافتراضي</span>'; ?></h3>
                            <p><?php echo htmlspecialchars($addr['address_details']); ?></p>
                        </div>
                    </div>
                    <div class="card-actions">
                        <?php if(!$addr['is_default']): ?>
                            <form action="php/manage_address.php" method="POST">
                                <input type="hidden" name="action" value="set_default">
                                <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                <button class="btn-action star"><i class="far fa-star"></i> تعيين كافتراضي</button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- زر التعديل يذهب لصفحة منفصلة -->
                        <a href="address_form.php?action=edit&id=<?php echo $addr['id']; ?>" class="btn-action edit">
                            <i class="fas fa-pen"></i> تعديل
                        </a>

                        <form action="php/manage_address.php" method="POST" onsubmit="return confirm('حذف العنوان؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                            <button class="btn-action delete"><i class="fas fa-trash"></i> حذف</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script src="js/lib/leaflet.js"></script>
    <script src="js/lib/geosearch.js"></script>
</body>
</html>