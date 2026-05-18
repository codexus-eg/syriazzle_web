    <?php
    echo "<h1>فحص إعدادات الخادم</h1>";

    echo "<h3>مسار Document Root:</h3>";
    echo "<p>" . $_SERVER['DOCUMENT_ROOT'] . "</p>";

    $uploadDirectory = $_SERVER['DOCUMENT_ROOT'] . '/uploads/businesses/';
    echo "<h3>المسار المستهدف للرفع:</h3>";
    echo "<p>" . $uploadDirectory . "</p>";

    echo "<h3>فحص المجلد:</h3>";
    if (file_exists($uploadDirectory)) {
        echo "<p style='color:green;'>المجلد موجود.</p>";
        if (is_writable($uploadDirectory)) {
            echo "<p style='color:green;'>المجلد قابل للكتابة (الأذونات صحيحة).</p>";
        } else {
            echo "<p style='color:red;'>خطأ: المجلد غير قابل للكتابة! الرجاء تعديل الأذونات إلى 775.</p>";
        }
    } else {
        echo "<p style='color:red;'>خطأ: المجلد غير موجود بالمسار المحدد!</p>";
    }
    
    echo "<h3>فحص امتدادات PHP الضرورية:</h3>";
    if (extension_loaded('fileinfo')) {
        echo "<p style='color:green;'>امتداد 'fileinfo' مفعل وجاهز للعمل.</p>";
    } else {
        echo "<p style='color:red;'>خطأ فادح: امتداد 'fileinfo' غير مفعل! هذا هو سبب المشكلة غالبًا. يرجى تفعيله من لوحة تحكم الاستضافة (cPanel).</p>";
    }
    ?>