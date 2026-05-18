<?php // واجهة إدارة الفنادق الكاملة ?>
<div class="view-header">
    <h2>إدارة الغرف والأجنحة</h2>
    <button class="btn btn-primary" id="add-new-resource-btn">
        <i class="fas fa-plus"></i> إضافة غرفة جديدة
    </button>
</div>
<div class="table-responsive-wrapper">
    <table class="data-table" id="resources-table">
        <thead>
            <tr>
                <th>اسم الغرفة/الجناح</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>سعة (بالغين/أطفال)</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <!-- سيتم ملؤها بواسطة JavaScript -->
        </tbody>
    </table>
</div>