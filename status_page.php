<?php
// استدعاء الهيدر إن وجد لعرض الشريط العلوي
if (file_exists('header_store.php')) {
    require_once 'php/db_connect.php'; // header_store قد يحتاج للاتصال
    include 'header_store.php';
}

// تحديد الرسالة بناءً على البارامتر المرسل في الرابط
$status = $_GET['status'] ?? 'generic_error';
$title = "حالة الطلب";
$message = "حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.";
$icon = "fas fa-exclamation-triangle";
$icon_color = "#dc3545"; // أحمر للخطر

switch ($status) {
    case 'pending':
        $title = "الطلب قيد المراجعة";
        $message = "لقد استلمنا طلبك بنجاح، ويعمل فريقنا على مراجعته. سيتم إعلامك فور تفعيله على المنصة.";
        $icon = "fas fa-hourglass-half";
        $icon_color = "#ffc107"; // أصفر للتنبيه
        break;
    case 'rejected':
        $title = "تم رفض الطلب";
        $message = "نعتذر، ولكن بعد المراجعة، تبين أن طلبك لا يطابق معايير النشر الحالية. يرجى التواصل مع الدعم الفني لمعرفة المزيد.";
        $icon = "fas fa-times-circle";
        $icon_color = "#dc3545"; // أحمر
        break;
    case 'not_found':
        $title = "غير موجود";
        $message = "عذرًا، النشاط التجاري الذي تبحث عنه غير موجود أو تم حذفه.";
        $icon = "fas fa-question-circle";
        $icon_color = "#6c757d"; // رمادي
        break;
    case 'unauthorized':
        $title = "وصول غير مصرح به";
        $message = "أنت لا تملك الصلاحية للوصول إلى هذه الصفحة. قد يكون هذا النشاط التجاري لا يخصك.";
        $icon = "fas fa-shield-alt";
        $icon_color = "#dc3545"; // أحمر
        break;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Syriazzle</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css"> 
    <link rel="stylesheet" href="css/main_header.css">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .status-container {
            text-align: center;
            padding: 40px 20px;
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .status-icon {
            font-size: 5rem;
            margin-bottom: 25px;
            animation: pop-in 0.5s ease-out;
        }
        h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 15px 0;
        }
        p {
            font-size: 1.1rem;
            color: #6c757d;
            line-height: 1.7;
            margin-bottom: 30px;
        }
        .btn-home {
            display: inline-block;
            padding: 12px 30px;
            background-color: #0d6efd;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.2s;
        }
        .btn-home:hover {
            background-color: #0b5ed7;
            transform: translateY(-2px);
        }

        @keyframes pop-in {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* لضمان أن الهيدر لا يلتصق بالصندوق في الشاشات الصغيرة */
        header { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="status-container">
        <div class="status-icon" style="color: <?php echo $icon_color; ?>;">
            <i class="<?php echo $icon; ?>"></i>
        </div>
        <h1><?php echo htmlspecialchars($title); ?></h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="index.html" class="btn-home">العودة إلى الصفحة الرئيسية</a>
    </div>
</body>
</html>