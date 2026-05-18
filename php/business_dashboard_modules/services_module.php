<?php // واجهة إدارة الخدمات والأسعار (النسخة النهائية 1.0) ?>
<div class="view-header">
    <h2>إدارة الخدمات والأسعار</h2>
    <p>من هنا يمكنك تحديد "عروض الأسعار" لكل خدمة أو أصل تقدمه.</p>
    <button class="btn btn-primary" id="add-new-service-btn">
        <i class="fas fa-plus"></i> إضافة خدمة / سعر جديد
    </button>
</div>
<div class="table-responsive-wrapper">
    <table class="data-table" id="services-table">
        <thead>
            <tr>
                <th>اسم الخدمة / عرض السعر</th>
                <th>السعر</th>
                <th>الحالة</th>
                <th>مرتبطة بـ (الأصل)</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <!-- سيتم ملء هذا الجدول بواسطة JavaScript -->
            <!-- مثال على صف فارغ -->
            <tr>
                <td colspan="5" class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>