<?php
$page_title = 'إدارة مول Syriazzle';
require_once 'header.php';

// التحقق من صلاحيات المستخدم
if (!hasPermission('manage_mall')) { 
    echo "<div style='text-align:center; padding:50px; color:red;'><h2>وصول غير مصرح به.</h2></div>"; 
    include 'footer.php'; 
    exit; 
}
?>
<link rel="stylesheet" href="css/manage_mall.css">

<style>
    /* تنسيقات خاصة بمعاينة الصور */
    .image-preview-box {
        margin-top: 10px;
        width: 100%;
        height: 220px;
        border: 2px dashed #ccc;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f9f9f9;
        overflow: hidden;
        position: relative;
    }
    .image-preview-box img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        display: none; /* مخفية افتراضياً */
    }
    .image-preview-box span {
        color: #aaa;
        font-size: 0.9rem;
        font-weight: 600;
    }
</style>

<div class="mall-manager-container">
    <header class="mall-manager-header">
        <h1><i class="fas fa-store-alt"></i> لوحة تحكم المول</h1>
        <p>إدارة شاملة للمنتجات، التصنيفات، الخصومات، والإعدادات.</p>
    </header>

    <!-- التبويبات الرئيسية -->
    <div class="tabs-nav">
        <button class="tab-link active" data-tab="products-management-tab"><i class="fas fa-box-open"></i> المنتجات والتصنيفات</button>
        <button class="tab-link" data-tab="discounts-tab"><i class="fas fa-percent"></i> الخصومات</button>
        <button class="tab-link" data-tab="settings-tab"><i class="fas fa-cog"></i> الإعدادات</button>
    </div>

    <!-- ======================= 1. تبويب المنتجات والتصنيفات ======================= -->
    <div class="tab-content active" id="products-management-tab">
        
        <!-- التبويبات الفرعية -->
        <div class="sub-tabs-nav">
            <button class="sub-tab-link active" data-subtab="products">المنتجات</button>
            <button class="sub-tab-link" data-subtab="categories">الأصناف</button>
            <button class="sub-tab-link" data-subtab="departments">الأقسام</button>
            <button class="sub-tab-link" data-subtab="brands">الماركات</button>
        </div>

        <!-- A. قسم المنتجات -->
        <div class="sub-tab-content active" data-subtab-content="products">
            <div class="view-header">
                <h2>قائمة المنتجات</h2>
                <button class="btn btn-primary" id="add-product-btn"><i class="fas fa-plus"></i> إضافة منتج جديد</button>
            </div>
            <div class="table-responsive-wrapper card">
                <table class="data-table" id="products-table">
                    <thead>
                        <tr>
                            <th>صورة</th>
                            <th>اسم المنتج</th>
                            <th>القسم</th>
                            <th>الصنف</th>
                            <th>الماركة</th>
                            <th>السعر</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="products-table-body"></tbody>
                </table>
                <!-- زر تحميل المزيد يضاف هنا عبر JS -->
            </div>
        </div>

        <!-- B. قسم الأصناف -->
        <div class="sub-tab-content" data-subtab-content="categories">
            <div class="view-header">
                <h2>قائمة الأصناف (Categories)</h2>
                <button class="btn btn-primary" id="add-category-btn"><i class="fas fa-plus"></i> إضافة صنف جديد</button>
            </div>
            <div class="table-responsive-wrapper card">
                <table class="data-table" id="categories-table">
                    <thead>
                        <tr>
                            <th>اسم الصنف</th>
                            <th>يتبع لقسم</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="categories-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- C. قسم الأقسام -->
        <div class="sub-tab-content" data-subtab-content="departments">
            <div class="view-header">
                <h2>قائمة الأقسام الرئيسية (Departments)</h2>
                <button class="btn btn-primary" id="add-department-btn"><i class="fas fa-plus"></i> إضافة قسم جديد</button>
            </div>
            <div class="table-responsive-wrapper card">
                <table class="data-table" id="departments-table">
                    <thead>
                        <tr>
                            <th>اسم القسم</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="departments-table-body"></tbody>
                </table>
            </div>
        </div>

        <!-- D. قسم الماركات -->
        <div class="sub-tab-content" data-subtab-content="brands">
            <div class="view-header">
                <h2>قائمة الماركات (Brands)</h2>
                <button class="btn btn-primary" id="add-brand-btn"><i class="fas fa-plus"></i> إضافة ماركة جديدة</button>
            </div>
            <div class="table-responsive-wrapper card">
                <table class="data-table" id="brands-table">
                    <thead>
                        <tr>
                            <th>الشعار</th>
                            <th>اسم الماركة</th>
                            <th>إجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="brands-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- ======================= 2. تبويب الخصومات ======================= -->
    <div class="tab-content" id="discounts-tab">
        <div class="view-header">
            <h2>حملات الخصومات العامة</h2>
            <button class="btn btn-primary" id="add-discount-btn"><i class="fas fa-plus"></i> إضافة حملة خصم</button>
        </div>
        <div class="table-responsive-wrapper card">
            <table class="data-table" id="discounts-table">
                <thead>
                    <tr>
                        <th>اسم الحملة</th>
                        <th>نسبة الخصم</th>
                        <th>الحالة</th>
                        <th>تاريخ البدء</th>
                        <th>تاريخ الانتهاء</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="discounts-table-body"></tbody>
            </table>
        </div>
    </div>
    
    <!-- ======================= 3. تبويب الإعدادات ======================= -->
    <div class="tab-content" id="settings-tab">
        <div class="view-header">
            <h2>الإعدادات المالية للمول</h2>
        </div>
        <div class="card" style="padding: 30px; max-width: 600px;">
            <div style="background: #e3f2fd; color: #0d47a1; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;">
                <i class="fas fa-info-circle"></i> <strong>ملاحظة:</strong> هذا السعر يؤثر فوراً على جميع المنتجات المسعرة بالدولار في واجهة المول.
            </div>
            
            <form id="settings-form">
                <!-- سيتم التعامل مع الاكشن عبر JS -->
                <div class="form-group">
                    <label for="exchange-rate" style="font-weight:bold;">سعر صرف الدولار الحالي (مقابل الليرة السورية)</label>
                    <div style="display:flex; align-items:center;">
                        <input type="number" id="exchange-rate" name="mall_usd_exchange_rate" class="form-control" step="any" required style="flex:1;">
                        <span style="padding: 10px; background: #eee; border: 1px solid #ccc; border-right: none; border-radius: 5px 0 0 5px;">ل.س</span>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 15px;">حفظ الإعدادات</button>
            </form>
        </div>
    </div>
</div>

<!-- ======================= النوافذ المنبثقة (Modals) ======================= -->

<!-- 1. Modal المنتجات -->
<div class="mall-modal-overlay" id="product-modal">
    <div class="mall-modal-content">
        <div class="mall-modal-header">
            <h4 id="product-modal-title">إضافة منتج جديد</h4>
            <button class="mall-modal-close-btn">&times;</button>
        </div>
        <form id="product-form" enctype="multipart/form-data">
            <input type="hidden" id="product-id" name="product_id" value="0">
            <input type="hidden" id="existing-image-path" name="existing_image_path">
            
            <div class="form-group">
                <label for="product-name">اسم المنتج *</label>
                <input type="text" id="product-name" name="name" required>
            </div>
            
            <fieldset>
                <legend>التسعير</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="product-price-usd">السعر بالدولار ($) *</label>
                        <input type="number" id="product-price-usd" name="price_usd" step="any" required>
                        <!-- سيتم إضافة نص السعر المقابل بالسوري هنا عبر JS -->
                    </div>
                    <div class="form-group">
                        <label for="product-old-price-usd">السعر القديم ($) <small>(اختياري)</small></label>
                        <input type="number" id="product-old-price-usd" name="old_price_usd" step="any">
                    </div>
                </div>
                <div class="form-group">
                    <label for="product-fixed-price">سعر ثابت بالليرة السورية <small>(إذا وضع، يلغي الدولار)</small></label>
                    <input type="number" id="product-fixed-price" name="fixed_price_syp">
                </div>
            </fieldset>

            <fieldset>
                <legend>التصنيف</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="department-select">القسم *</label>
                        <select id="department-select" name="department_id" required>
                            <option value="">-- اختر القسم --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category-select">الصنف *</label>
                        <select id="category-select" name="category_id" required disabled>
                            <option value="">-- اختر القسم أولاً --</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="brand-select">الماركة</label>
                    <select id="brand-select" name="brand_id">
                        <option value="">-- بدون ماركة --</option>
                    </select>
                </div>
            </fieldset>
            
            <div class="form-group">
                <label for="product-description">وصف المنتج</label>
                <textarea id="product-description" name="description" rows="3"></textarea>
            </div>

            <!-- منطقة رفع الصورة مع المعاينة -->
            <div class="form-group">
                <label for="product-image">صورة المنتج</label>
                <input type="file" id="product-image" name="image" accept="image/*">
                
                <div class="image-preview-box">
                    <span id="preview-placeholder">لا توجد صورة مختارة</span>
                    <img id="img-preview" src="" alt="Product Preview">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="submit" class="btn-submit">حفظ المنتج</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Modal الأقسام -->
<div class="mall-modal-overlay" id="department-modal">
    <div class="mall-modal-content small">
        <div class="mall-modal-header">
            <h4 id="department-modal-title">إضافة قسم جديد</h4>
            <button class="mall-modal-close-btn">&times;</button>
        </div>
        <form id="department-form">
            <input type="hidden" id="department-id" name="department_id" value="0">
            <div class="form-group">
                <label for="department-name">اسم القسم *</label>
                <input type="text" id="department-name" name="name" required>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-submit">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Modal الأصناف -->
<div class="mall-modal-overlay" id="category-modal">
    <div class="mall-modal-content small">
        <div class="mall-modal-header">
            <h4 id="category-modal-title">إضافة صنف جديد</h4>
            <button class="mall-modal-close-btn">&times;</button>
        </div>
        <form id="category-form">
            <input type="hidden" id="category-id" name="category_id" value="0">
            <div class="form-group">
                <label for="category-name">اسم الصنف *</label>
                <input type="text" id="category-name" name="name" required>
            </div>
            <div class="form-group">
                <label for="category-department-select">تابع للقسم *</label>
                <select id="category-department-select" name="department_id" required></select>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-submit">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Modal الماركات -->
<div class="mall-modal-overlay" id="brand-modal">
    <div class="mall-modal-content small">
        <div class="mall-modal-header">
            <h4 id="brand-modal-title">إضافة ماركة جديدة</h4>
            <button class="mall-modal-close-btn">&times;</button>
        </div>
        <form id="brand-form" enctype="multipart/form-data">
            <input type="hidden" id="brand-id" name="brand_id" value="0">
            <input type="hidden" id="existing-brand-logo-path" name="existing_logo_path">
            <div class="form-group">
                <label for="brand-name">اسم الماركة *</label>
                <input type="text" id="brand-name" name="name" required>
            </div>
            <div class="form-group">
                <label for="brand-logo">شعار الماركة</label>
                <input type="file" id="brand-logo" name="logo" accept="image/*">
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn-submit">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Modal الخصومات -->
<div class="mall-modal-overlay" id="discount-modal">
    <div class="mall-modal-content">
        <div class="mall-modal-header">
            <h4 id="discount-modal-title">إضافة حملة خصم</h4>
            <button class="mall-modal-close-btn">&times;</button>
        </div>
        <form id="discount-form">
            <input type="hidden" id="discount-id" name="discount_id" value="0">
            <div class="form-group">
                <label for="discount-name">اسم الحملة *</label>
                <input type="text" id="discount-name" name="name" required placeholder="مثال: عروض الصيف">
            </div>
            <div class="form-group">
                <label for="discount-percentage">نسبة الخصم (%) *</label>
                <input type="number" id="discount-percentage" name="discount_percentage" step="any" min="1" max="100" required>
            </div>
            <fieldset>
                <legend>الفترة الزمنية (اختياري)</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="start-date">تاريخ البدء</label>
                        <input type="datetime-local" id="start-date" name="start_date">
                    </div>
                    <div class="form-group">
                        <label for="end-date">تاريخ الانتهاء</label>
                        <input type="datetime-local" id="end-date" name="end_date">
                    </div>
                </div>
            </fieldset>
            <div class="modal-footer">
                <button type="submit" class="btn-submit">حفظ الحملة</button>
            </div>
        </form>
    </div>
</div>

<!-- استدعاء ملف الجافاسكريبت في النهاية -->
<script src="js/manage_mall.js"></script>

<?php include 'footer.php'; ?>