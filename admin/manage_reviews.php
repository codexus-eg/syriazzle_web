<?php
$page_title = 'إدارة المراجعات';
// --- header.php هو المسؤول عن بدء الجلسة وتعريف الصلاحيات ---
include 'header.php';

// --- حارس البوابة: تأكد من أن المستخدم لديه صلاحية عرض المراجعات ---
if (!hasPermission('view_reviews')) {
    echo "<h2>وصول غير مصرح به.</h2>"; include 'footer.php'; exit;
}

$reviews_by_business = [];
try {
    // --- 1. بناء شرط المحافظة الديناميكي ---
    $governorate_where_clause = '';
    $params = [];
    // **الإصلاح الحاسم هنا:** نستخدم دالة hasPermission للتحقق
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id) {
        $governorate_where_clause = 'WHERE b.governorate_id = ?';
        $params[] = $admin_governorate_id;
    }

    // --- 2. تحديث الاستعلام الرئيسي ليكون ديناميكياً وآمناً ---
    $stmt = $pdo->prepare("
        SELECT 
            r.id, r.review_text, r.rating, r.created_at,
            u.username,
            b.id AS business_id,
            b.name AS business_name
        FROM 
            business_reviews r
        JOIN 
            users u ON r.user_id = u.id
        JOIN 
            businesses b ON r.business_id = b.id
        $governorate_where_clause
        ORDER BY 
            b.name ASC, r.created_at DESC
    ");
    $stmt->execute($params);
    $all_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- (بقية الكود يبقى كما هو) ---
    foreach ($all_reviews as $review) {
        $reviews_by_business[$review['business_id']]['details']['name'] = $review['business_name'];
        $reviews_by_business[$review['business_id']]['reviews'][] = $review;
    }

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>فشل في جلب المراجعات: " . $e->getMessage() . "</div>";
}
?>

<style>
    .card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 8px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    
    .accordion-container {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
    }
    .accordion-item {
        border-bottom: 1px solid var(--border-color);
    }
    .accordion-item:last-child {
        border-bottom: none;
    }
    .accordion-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background-color: #f8f9fa;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .accordion-header:hover {
        background-color: #e9ecef;
    }
    .accordion-header h3 {
        margin: 0;
        font-size: 18px;
        color: var(--primary-blue);
    }
    .header-meta {
        display: flex;
        align-items: center;
        gap: 20px;
        color: #6c757d;
        font-weight: 600;
    }
    .review-count-badge {
        background-color: #6c757d;
        color: white;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 14px;
    }
    .accordion-arrow {
        font-size: 16px;
        transition: transform 0.3s ease-in-out;
    }
    .accordion-item.active .accordion-arrow {
        transform: rotate(180deg);
    }
    .accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out, padding 0.3s ease-in-out;
        background-color: #fff;
        padding: 0 20px;
    }
    .inner-reviews-table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
    }
    .inner-reviews-table th, .inner-reviews-table td {
        padding: 12px 10px;
        border: 1px solid #e9ecef;
        text-align: right;
    }
    .inner-reviews-table th { background-color: #f8f9fa; }
    .btn-delete { background-color: var(--danger-red); color: white; border: none; padding: 6px 10px; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
    .rating-stars { color: #ffc107; font-size: 14px; letter-spacing: 1px; }
    .no-reviews-message { text-align: center; padding: 40px; background-color: #f8f9fa; border: 1px dashed var(--border-color); border-radius: 8px; }
    tr.fading-out { opacity: 0; transition: opacity 0.5s; }
</style>

<div class="card">
    <p>هنا يمكنك الإشراف على المراجعات مجمّعة حسب كل نشاط تجاري. انقر على اسم النشاط لعرض مراجعاته.</p>

    <?php if (empty($reviews_by_business)): ?>
        <div class="no-reviews-message">
            <h3>لا توجد أي مراجعات في النظام حالياً.</h3>
        </div>
    <?php else: ?>
        <div class="accordion-container" id="reviewsAccordion">
            <?php foreach ($reviews_by_business as $business_id => $data): ?>
                <div class="accordion-item" id="business-item-<?php echo $business_id; ?>">
                    <div class="accordion-header">
                        <h3><?php echo htmlspecialchars($data['details']['name']); ?></h3>
                        <div class="header-meta">
                            <span class="review-count-badge">
                                <?php echo count($data['reviews']); ?> مراجعة
                            </span>
                            <i class="fas fa-chevron-down accordion-arrow"></i>
                        </div>
                    </div>
                    <div class="accordion-content">
                        <table class="inner-reviews-table">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>التقييم</th>
                                    <th style="width: 50%;">نص المراجعة</th>
                                    <th>التاريخ</th>
                                    <th>إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['reviews'] as $review): ?>
                                    <tr id="review-row-<?php echo $review['id']; ?>">
                                        <td><?php echo htmlspecialchars($review['username']); ?></td>
                                        <td class="rating-stars" dir="ltr" style="text-align:left;"><?php echo str_repeat('★', $review['rating']) . str_repeat('☆', 5 - $review['rating']); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($review['created_at'])); ?></td>
                                        <td>
                                            <?php if (hasPermission('delete_review')): ?>
                                                <button class="btn-delete" data-review-id="<?php echo $review['id']; ?>" data-business-id="<?php echo $business_id; ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const accordionContainer = document.getElementById('reviewsAccordion');
    if (!accordionContainer) return;

    accordionContainer.addEventListener('click', (e) => {
        const header = e.target.closest('.accordion-header');
        if (!header) return;

        const item = header.parentElement;
        const content = header.nextElementSibling;

        accordionContainer.querySelectorAll('.accordion-item').forEach(otherItem => {
            if (otherItem !== item && otherItem.classList.contains('active')) {
                otherItem.classList.remove('active');
                otherItem.querySelector('.accordion-content').style.maxHeight = null;
                otherItem.querySelector('.accordion-content').style.padding = '0 20px';
            }
        });
        
        item.classList.toggle('active');
        if (item.classList.contains('active')) {
            content.style.maxHeight = content.scrollHeight + "px";
            content.style.padding = '1px 20px'; 
        } else {
            content.style.maxHeight = null;
            content.style.padding = '0 20px';
        }
    });

    accordionContainer.addEventListener('click', async (e) => {
        const deleteButton = e.target.closest('.btn-delete');
        if (!deleteButton) return;

        const reviewId = deleteButton.dataset.reviewId;
        const businessId = deleteButton.dataset.businessId;
        const reviewRow = document.getElementById(`review-row-${reviewId}`);
        const businessItem = document.getElementById(`business-item-${businessId}`);

        if (!confirm('هل أنت متأكد من أنك تريد حذف هذه المراجعة نهائياً؟')) return;

        try {
            deleteButton.disabled = true;

            const formData = new FormData();
            formData.append('review_id', reviewId);
            formData.append('action', 'delete');
            
            const response = await fetch('php/process_review_action.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                reviewRow.classList.add('fading-out');
                setTimeout(() => {
                    reviewRow.remove();

                    const remainingReviews = businessItem.querySelectorAll('tbody tr').length;
                    const badge = businessItem.querySelector('.review-count-badge');
                    
                    if (remainingReviews > 0) {
                        badge.textContent = `${remainingReviews} مراجعة`;
                        const content = businessItem.querySelector('.accordion-content');
                        if (businessItem.classList.contains('active')) {
                            content.style.maxHeight = content.scrollHeight + "px";
                        }
                    } else {
                        businessItem.remove();
                    }
                }, 500);
            } else {
                alert('فشل الحذف: ' + (result.message || 'خطأ غير معروف.'));
                deleteButton.disabled = false;
            }
        } catch (error) {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال بالخادم.');
            deleteButton.disabled = false;
        }
    });
});
</script>

<?php require_once 'footer.php';?>