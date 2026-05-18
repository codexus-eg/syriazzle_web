<?php
// ========================================================================
// Syriazzle - Customer Orders Page (النسخة النهائية المتوافقة 100%)
// ========================================================================
require_once 'php/db_connect.php';

$page_title = 'طلباتي - Syriazzle';

// الحماية: يجب أن يكون مسجلاً للدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/main_header.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Cairo', sans-serif; padding-bottom: 60px; margin: 0; }
        
        .container { max-width: 800px; margin: 20px auto; padding: 0 15px; }
        
        .page-header h1 { 
            font-size: 1.5rem; color: #333; font-weight: 800; 
            text-align: center; margin-bottom: 25px; margin-top: 10px;
        }

        /* --- التبويبات (Tabs) --- */
        .tabs-wrapper {
            display: flex; justify-content: center; gap: 8px; margin-bottom: 25px;
            background: #fff; padding: 6px; border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #eee;
        }
        
        .tab-btn {
            border: none; background: transparent; padding: 8px 25px;
            border-radius: 30px; font-weight: 600; color: #666; cursor: pointer;
            transition: all 0.3s ease; font-family: inherit; font-size: 0.9rem;
            flex: 1; max-width: 120px; text-align: center;
        }
        
        .tab-btn.active { background: #e60000; color: #fff; box-shadow: 0 3px 10px rgba(230,0,0,0.2); }

        /* --- تصميم الكرت المدمج (Compact Card) --- */
        .orders-list { display: flex; flex-direction: column; gap: 15px; }

        .order-card-compact {
            background: #fff; border-radius: 16px; padding: 15px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 3px 10px rgba(0,0,0,0.03); border: 1px solid #f0f0f0;
            transition: transform 0.2s; position: relative; overflow: hidden;
            cursor: pointer;
        }
        .order-card-compact:active { transform: scale(0.98); background-color: #fafafa; }

        /* الجزء الأيمن: الصورة والمعلومات */
        .card-start { display: flex; align-items: center; gap: 15px; flex: 1; }
        
        .store-img {
            width: 60px; height: 60px; border-radius: 12px; object-fit: cover;
            border: 1px solid #f5f5f5; box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .order-info h3 { margin: 0 0 5px; font-size: 1rem; font-weight: 700; color: #333; }
        .order-info .meta { font-size: 0.75rem; color: #888; margin-bottom: 5px; display: block; }
        .order-info .price { font-size: 1rem; font-weight: 800; color: #e60000; }

        /* الجزء الأيسر: الحالة والزر */
        .card-end { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
        
        .status-badge {
            font-size: 0.7rem; padding: 4px 12px; border-radius: 20px; font-weight: 700;
            white-space: nowrap;
        }
        
        /* ألوان الحالات (متوافقة مع JS) */
        .st-pending { background: #fff8e1; color: #f57c00; } /* انتظار */
        .st-active { background: #e3f2fd; color: #1976d2; }  /* نشط (تحضير، طريق، قبول) */
        .st-success { background: #e8f5e9; color: #2e7d32; } /* مكتمل */
        .st-cancel { background: #ffebee; color: #c62828; }  /* ملغي */

        .action-icon-btn {
            width: 32px; height: 32px; border-radius: 50%; border: none;
            background: #f8f9fa; color: #aaa; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: 0.2s; font-size: 0.9rem;
        }
        
        /* زر التحميل */
        .load-more-container { text-align: center; margin-top: 30px; display: none; }
        .btn-load {
            background: #fff; border: 1px solid #ddd; padding: 10px 35px;
            border-radius: 30px; font-weight: 600; color: #555; cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .btn-load:hover { background: #f9f9f9; }

        /* مؤشر التحميل */
        #loader { text-align: center; padding: 40px; color: #999; font-size: 1.5rem; display: none; }

        /* --- المودال (نافذة التفاصيل) --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 2000; display: none;
            align-items: center; justify-content: center; backdrop-filter: blur(3px);
        }
        
        .modal-box {
            background: #fff; width: 90%; max-width: 450px; border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25); overflow: hidden;
            animation: slideUp 0.3s ease; display: flex; flex-direction: column;
            max-height: 90vh;
        }
        @keyframes slideUp { from {transform: translateY(50px); opacity: 0;} to {transform: translateY(0); opacity: 1;} }
        
        .modal-header { 
            padding: 15px 20px; border-bottom: 1px solid #f0f0f0; 
            display: flex; justify-content: space-between; align-items: center;
            background: #fff;
        }
        .modal-header h3 { margin: 0; font-size: 1.1rem; font-weight: 700; }
        .close-modal { background: none; border: none; font-size: 1.8rem; color: #999; cursor: pointer; line-height: 1; }
        
        .modal-body { padding: 20px; overflow-y: auto; }
        
        .detail-row { 
            display: flex; justify-content: space-between; margin-bottom: 12px; 
            font-size: 0.95rem; border-bottom: 1px dashed #f5f5f5; padding-bottom: 8px; 
        }
        .detail-row:last-child { border: none; }
        .detail-note { 
            font-size: 0.8rem; color: #d63384; background: #fff0f6; 
            padding: 8px; border-radius: 8px; margin-top: -5px; margin-bottom: 15px; 
        }

        .modal-actions { 
            padding: 15px 20px; background: #f8f9fa; border-top: 1px solid #eee;
            display: flex; gap: 10px; 
        }
        
        .modal-btn { 
            flex: 1; padding: 12px; border: none; border-radius: 12px; 
            font-weight: 700; cursor: pointer; display: flex; 
            justify-content: center; align-items: center; gap: 8px; font-size: 0.95rem; text-decoration: none;
        }
        .btn-track { background: #007bff; color: #fff; } /* أزرق للتتبع */
        .btn-reorder { background: #e60000; color: #fff; } /* أحمر للإعادة */
        .btn-track:hover { background: #0056b3; }
        .btn-reorder:hover { background: #cc0000; }

    </style>
</head>
<body>
    <?php include 'header_store.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1>طلباتي</h1>
        </div>

        <div class="tabs-wrapper">
            <button class="tab-btn active" data-status="active">النشطة</button>
            <button class="tab-btn" data-status="completed">المكتملة</button>
            <button class="tab-btn" data-status="canceled">الملغاة</button>
        </div>

        <!-- قائمة الطلبات -->
        <div id="orders-list" class="orders-list">
            <!-- سيتم تعبئتها بواسطة JS -->
        </div>

        <!-- مؤشر التحميل -->
        <div id="loader">
            <i class="fas fa-spinner fa-spin"></i>
        </div>

        <!-- زر تحميل المزيد -->
        <div id="load-more" class="load-more-container">
            <button class="btn-load" id="btn-load-more">عرض المزيد</button>
        </div>
    </div>

    <!-- نافذة التفاصيل (Modal) -->
    <div id="order-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modal-title">تفاصيل الطلب</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modal-content">
                <!-- محتوى الفاتورة -->
            </div>
            <div class="modal-actions" id="modal-footer">
                <!-- أزرار الإجراءات (تتبع / إعادة طلب) -->
            </div>
        </div>
    </div>

    <!-- استدعاء ملف الجافاسكريبت -->
    <script src="js/my_orders.js"></script>
</body>
</html>