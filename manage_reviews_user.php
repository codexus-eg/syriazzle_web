<?php
require_once 'php/db_connect.php';
$page_title = 'إدارة المراجعات - Syriazzle';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'المستخدم';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    // 1. جلب قائمة المتاجر الخاصة بالمستخدم أولاً
    $stmt_businesses = $pdo->prepare("SELECT id, name FROM businesses WHERE user_id = ? ORDER BY name ASC");
    $stmt_businesses->execute([$current_user_id]);
    $user_businesses = $stmt_businesses->fetchAll(PDO::FETCH_ASSOC);

    // 2. جلب كل المراجعات على كل المتاجر التي يملكها المستخدم
    $sql = "
        SELECT 
            r.id as review_id,
            r.business_id,
            r.rating,
            r.review_text,
            r.reply_text,
            r.created_at,
            b.name as business_name,
            u.username as reviewer_name
        FROM business_reviews r
        JOIN businesses b ON r.business_id = b.id
        JOIN users u ON r.user_id = u.id
        WHERE b.user_id = ?
        ORDER BY r.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب المراجعات: ". $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المراجعات - Syriazzle</title>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #e60000; 
            --secondary-color: #007bff;
            --bg-light: #f0f2f5; 
            --card-bg: #fff; 
            --text-dark: #212529;
            --text-light: #6c757d;
        }
        body { font-family: 'Cairo', sans-serif; background-color: var(--bg-light); margin: 0; }
        .dashboard-nav {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 10px;
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .dashboard-nav a {
            flex-grow: 1; /* جعل العناصر تتمدد لتملأ المساحة */
            text-align: center;
            text-decoration: none;
            color: var(--text-light);
            padding: 12px 0px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .dashboard-nav a:hover:not(.active1) {
            background-color: #f0f2f5;
            color: var(--text-dark);
        }
        
        .dashboard-nav a.active1 {
            background-color: var(--secondary-color);
            color: #fff;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
            transform: translateY(-2px); /* تأثير الرفع قليلاً */
        }
        .dashboard-container { max-width: 900px; margin: 30px auto; padding: 10px;}
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #ddd; }
        .dashboard-header h1 { font-size: 28px; margin: 0; color: var(--text-dark); }

        /* --- **الجديد:** تصميم قسم الفلترة --- */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: var(--card-bg);
            border-radius: 8px;
        }
        .filter-btn {
            background-color: #e9ecef;
            color: #495057;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, color 0.2s;
        }
        .filter-btn:hover { background-color: #ced4da; }
        .filter-btn.active { background-color: var(--secondary-color); color: #fff; }
        .review-card {
            background-color: var(--card-bg);
            border: 1px solid #e9ecef;
            border-right: 4px solid var(--secondary-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none; /* مخفية بشكل افتراضي */
        }
        .review-card.visible {
            display: block; /* ستظهر فقط عند اختيار الفلتر */
        }
        .review-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; gap: 10px;}
        .review-info .reviewer-name { font-weight: 700; }
        .review-info .review-date { font-size: 13px; color: var(--text-light); }
        .rating-stars { color: #ffc107; font-size: 14px; }
        .review-body { margin-bottom: 20px; line-height: 1.7; }

        .reply-section .existing-reply {
            background-color: #f0f2f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .reply-section .existing-reply .reply-label { font-weight: 700; color: var(--text-dark); display: block; margin-bottom: 5px; }

        .reply-form textarea { width: 100%; min-height: 70px; padding: 10px; border-radius: 6px; border: 1px solid #ced4da; font-family: 'Cairo', sans-serif; margin-bottom: 10px; }
        .reply-form button { background-color: var(--secondary-color); color: #fff; border: none; padding: 8px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; }

    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="dashboard-container">
        <div class="dashboard-header"><h1>إدارة المراجعات</h1></div>
        <div class="dashboard-nav">
            <a href="business_dashboard.php" class="active1"><i class="fas fa-chart-line"></i> لوحة القيادة</a>
            <a href="manage_orders.php"><i class="fas fa-receipt"></i> إدارة الطلبات</a>
            <a href="manage_reviews_user.php"><i class="fas fa-star"></i> إدارة المراجعات</a>
        </div>

        <?php if (empty($reviews)): ?>
            <div style="text-align: center; padding: 50px; background-color: #fff; border-radius: 12px;"><h2>لا توجد أي مراجعات بعد.</h2></div>
        <?php else: ?>
            <!-- شريط الفلترة -->
            <div class="filter-bar">
                <button class="filter-btn active" data-business-id="all">عرض الكل</button>
                <?php foreach ($user_businesses as $business): ?>
                    <button class="filter-btn" data-business-id="<?php echo $business['id']; ?>">
                        <?php echo htmlspecialchars($business['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- حاوية المراجعات -->
            <div id="reviews-container">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card" data-business-id="<?php echo $review['business_id']; ?>">
                        <div class="review-header">
                            <div class="review-info">
                                <span class="reviewer-name">مراجعة من: <?php echo htmlspecialchars($review['reviewer_name']); ?></span>
                                <div class="review-date"><?php echo date('Y-m-d', strtotime($review['created_at'])); ?></div>
                            </div>
                            <div class="rating-stars">
                                <?php for($i = 0; $i < 5; $i++): ?><i class="fas fa-star <?php echo ($i < $review['rating']) ? '' : 'far'; ?>"></i><?php endfor; ?>
                            </div>
                        </div>
                        <p class="review-body"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                        <div class="reply-section">
                            <?php if (!empty($review['reply_text'])): ?>
                                <div class="existing-reply">
                                    <span class="reply-label"><i class="fas fa-reply"></i> ردك:</span>
                                    <p><?php echo nl2br(htmlspecialchars($review['reply_text'])); ?></p>
                                </div>
                            <?php else: ?>
                                <form action="php/submit_reply.php" method="POST" class="reply-form">
                                    <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <textarea name="reply_text" placeholder="اكتب ردك هنا..." required></textarea>
                                    <button type="submit">إرسال الرد</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                 <div id="no-reviews-message" style="display: none; text-align: center; padding: 50px; background-color: #fff; border-radius: 12px;"><h2>لا توجد مراجعات لهذا النشاط التجاري.</h2></div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const filterButtons = document.querySelectorAll('.filter-btn');
        const reviewCards = document.querySelectorAll('.review-card');
        const noReviewsMessage = document.getElementById('no-reviews-message');

        function filterReviews(businessId) {
            let visibleCount = 0;
            reviewCards.forEach(card => {
                if (businessId === 'all' || card.dataset.businessId === businessId) {
                    card.classList.add('visible');
                    visibleCount++;
                } else {
                    card.classList.remove('visible');
                }
            });

            if (noReviewsMessage) {
                 noReviewsMessage.style.display = (visibleCount === 0 && businessId !== 'all') ? 'block' : 'none';
            }
        }

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                // تحديث حالة الأزرار
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                const businessId = button.dataset.businessId;
                filterReviews(businessId);
            });
        });

        // عرض الكل بشكل افتراضي عند تحميل الصفحة
        filterReviews('all');
    });
    </script>
</body>
</html>