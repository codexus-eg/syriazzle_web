<?php
require_once 'db_connect.php'; 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$ads = [];
$pageTitle = 'نتائج البحث عن: ' . htmlspecialchars($searchTerm);

if (empty($searchTerm)) {
    $pageTitle = 'الرجاء إدخال مصطلح للبحث';
} else {
    try {        
        $searchQuery = '%' . $searchTerm . '%';
        $sql = "
            SELECT * FROM form_submissions 
            WHERE 
                `category` LIKE :cat_query OR 
                `sub` LIKE :sub_query OR 
                `subsub` LIKE :subsub_query OR 
                `subsubsub` LIKE :subsubsub_query OR 
                `json_data` LIKE :json_query
            ORDER BY submitted_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':cat_query', $searchQuery, PDO::PARAM_STR);
        $stmt->bindValue(':sub_query', $searchQuery, PDO::PARAM_STR);
        $stmt->bindValue(':subsub_query', $searchQuery, PDO::PARAM_STR);
        $stmt->bindValue(':subsubsub_query', $searchQuery, PDO::PARAM_STR);
        $stmt->bindValue(':json_query', $searchQuery, PDO::PARAM_STR);

        $stmt->execute();
        $ads_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ads_from_db as $ad_row) {
            $data = json_decode($ad_row['json_data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) continue;
            
            $data['id'] = $ad_row['id'];
            $data['submitted_at'] = $ad_row['submitted_at'];
            $data['user_id'] = (int)$ad_row['user_id'];
            
            $cardTitle = $data['الماركة'] ?? $data['نوع القطعة'] ?? $ad_row['subsub'] ?? $ad_row['sub'] ?? 'إعلان جديد';
            $data['card_title'] = $cardTitle;
            $data['السعر'] = $data['السعر'] ?? 'غير محدد';
            
            $ads[] = $data;
        }

    } catch (PDOException $e) {
        http_response_code(500);
        die("<h1>خطأ في الخادم</h1><p>حدث خطأ أثناء معالجة طلب البحث. يرجى المحاولة مرة أخرى لاحقًا.</p>");
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../css/fetch_ads.css">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/dubizzle-inspired.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
            <link rel="stylesheet" href="../css/main_header.css" />

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

        <?php include 'header_store.php'; ?>

    <h1><?php echo $pageTitle; ?></h1>
    <div class="parent" id="ads-container">
        <?php if (count($ads) > 0): ?>
            <?php foreach ($ads as $ad_data): ?>
                <?php
                $images = $ad_data['images'] ?? [];
                $imageUrl = !empty($images) ? "../" . htmlspecialchars($images[0]) : 'https://via.placeholder.com/300x200/f4f5f7/ccc?text=No+Image';
                $whatsappNumber = preg_replace('/[^0-9+]/', '', $ad_data['رقم الواتس'] ?? '');
                $ad_details_url = "../ad_details.php?id=" . htmlspecialchars($ad_data['id']);
                $cardTitle = htmlspecialchars($ad_data['card_title']);
                ?>
                <div class="child">
                    <a href="<?php echo $ad_details_url; ?>">
                        <img src="<?php echo $imageUrl; ?>" alt="<?php echo $cardTitle; ?>">
                    </a>
                    <h3><?php echo $cardTitle; ?></h3>
                    <div class="info-row">
                        <p class="price"><?php echo htmlspecialchars($ad_data['السعر']); ?></p>
                        <p class="location"><?php echo htmlspecialchars($ad_data['الموقع'] ?? ''); ?></p>
                        <p class="date"><?php echo htmlspecialchars(date('Y-m-d', strtotime($ad_data['submitted_at']))); ?></p>
                    </div>
                    <div class="actions-row">
                        <a href="tel:<?php echo htmlspecialchars($ad_data['رقم الهاتف'] ?? ''); ?>" class="btn-call">اتصال</a>
                        <a href="https://wa.me/<?php echo $whatsappNumber; ?>?text=<?php echo urlencode('مرحباً، رأيت إعلانك "' . $cardTitle . '" على Syriazzle.'); ?>" target="_blank" class="btn-whatsapp">واتساب</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column: 1 / -1; text-align: center; font-size: 1.2rem; padding: 40px;">
                عذراً، لم يتم العثور على نتائج تطابق بحثك "<?php echo htmlspecialchars($searchTerm); ?>".
            </p>
        <?php endif; ?>
    </div>
</body>
</html>