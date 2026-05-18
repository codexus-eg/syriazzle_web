<?php
require_once 'php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect_url=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
$current_user_id = (int)$_SESSION['user_id'];

try {
    $governorates = $pdo->query("SELECT id, name FROM governorates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $governorates = [];
}

$page_title = 'أضف نشاطك للحجوزات - Syriazzle';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/add_booking_business.css">
</head>
<body>
    <?php include 'header_store.php'; ?>
    <div class="add-business-container">
        <div class="form-wizard-container">
            <div class="form-wizard-header">
                <h1>أضف نشاطك للحجوزات</h1>
                <p>اتبع الخطوات التالية لإدراج نشاطك على منصتنا والبدء باستقبال الحجوزات.</p>
            </div>
            
            <div class="form-wizard-steps">
                <div class="step-item active" data-step="1">
                    <div class="step-icon"><i class="fas fa-briefcase"></i></div>
                    <span>نوع النشاط</span>
                </div>
                <div class="step-item" data-step="2">
                    <div class="step-icon"><i class="fas fa-id-card"></i></div>
                    <span>المعلومات الأساسية</span>
                </div>
                <div class="step-item" data-step="3">
                    <div class="step-icon"><i class="fas fa-images"></i></div>
                    <span>الصور</span>
                </div>
            </div>

            <form id="add-business-form" action="php/save_booking_business.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="business_type" value="booking">
                <input type="hidden" name="is_booking_enabled" value="1">
                
                <div class="form-step active" data-step-content="1">
                    <h2>ما هو نوع نشاطك التجاري؟</h2>
                    <p>هذا الاختيار سيساعدنا في تخصيص تجربتك وتجربة زبائنك.</p>
                    <div class="category-selector-grid">
                    </div>
                    <input type="hidden" name="booking_category" id="booking_category_input" required>
                </div>

                <div class="form-step" data-step-content="2">
                    <h2>أخبرنا المزيد عن نشاطك</h2>
                    <div class="form-group">
                        <label for="name">اسم النشاط التجاري *</label>
                        <input type="text" id="name" name="name" required placeholder="مثال: فندق الشام">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="governorate_id">المحافظة *</label>
                            <select id="governorate_id" name="governorate_id" required>
                                <option value="" disabled selected>-- اختر محافظة --</option>
                                <?php foreach ($governorates as $gov): ?>
                                    <option value="<?php echo $gov['id']; ?>"><?php echo htmlspecialchars($gov['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="city">المدينة / المنطقة *</label>
                            <input type="text" id="city" name="city" required placeholder="مثال: الصالحية">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">وصف قصير وجذاب عن المكان</label>
                        <textarea id="description" name="description" rows="4" placeholder="اكتب هنا ما يميز نشاطك..."></textarea>
                    </div>
                </div>

                <div class="form-step" data-step-content="3">
                    <h2>أضف صورًا لنشاطك</h2>
                    <p>الصور عالية الجودة تجذب المزيد من الزبائن.</p>
                    <div class="image-upload-grid">
                        <div class="form-group">
                            <label>الشعار (Logo)</label>
                            <div class="image-uploader-box" id="logo-uploader">
                                <input type="file" name="logo_image" accept="image/*">
                                <div class="upload-ui">
                                    <i class="fas fa-portrait"></i>
                                    <span>اختر الشعار</span>
                                </div>
                                <img class="image-preview" src="#" alt="معاينة الشعار">
                                <button type="button" class="remove-image-btn">&times;</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>صورة الغلاف</label>
                            <div class="image-uploader-box" id="cover-uploader">
                                <input type="file" name="cover_image" accept="image/*">
                                <div class="upload-ui">
                                    <i class="fas fa-image"></i>
                                    <span>اختر صورة الغلاف</span>
                                </div>
                                <img class="image-preview" src="#" alt="معاينة الغلاف">
                                <button type="button" class="remove-image-btn">&times;</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-wizard-footer">
                    <button type="button" class="btn btn-secondary" id="prev-btn" style="display: none;">السابق</button>
                    <button type="button" class="btn btn-primary" id="next-btn">التالي</button>
                    <button type="submit" class="btn btn-primary" id="submit-btn" style="display: none;"><i class="fas fa-check-circle"></i> إنشاء النشاط</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/add_booking_business.js"></script>
</body>
</html>