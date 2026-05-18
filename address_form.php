<?php
require_once 'php/db_connect.php';

// حماية الصفحة
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$action = $_GET['action'] ?? 'add';
$address_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$data = null;

// جلب البيانات في حالة التعديل
if ($action === 'edit' && $address_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$address_id, $_SESSION['user_id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) { header('Location: my_addresses.php'); exit; }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <!-- إعدادات العرض للموبايل مهمة جداً -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $action === 'add' ? 'عنوان جديد' : 'تعديل العنوان'; ?></title>
    
        <link rel="stylesheet" href="css/lib/leaflet.css" />
    <link rel="stylesheet" href="css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* منع التحديث عند السحب في كامل الصفحة */
        html, body {
            margin: 0; padding: 0; height: 100%; width: 100%;
            font-family: 'Cairo', sans-serif; background: #fff;
            overscroll-behavior-y: none; /* الحل السحري لمنع التحديث */
            display: flex; flex-direction: column;
            position: fixed; /* تثبيت الصفحة لمنع الاهتزاز */
        }

        /* 1. حاوية الخريطة */
        .map-wrapper {
            flex-grow: 1; /* تأخذ كل المساحة المتاحة */
            position: relative;
            width: 100%;
            z-index: 1;
        }

        #map {
            width: 100%; height: 100%;
            /* منع متصفح الهاتف من التعامل مع اللمس، وترك التحكم للخريطة */
            touch-action: none; 
            z-index: 1;
        }

        /* 2. الدبوس الثابت (CSS Pin) - الحل الجذري للاختفاء */
        .center-pin {
            position: absolute;
            top: 50%; left: 50%;
            /* تحريكه للأعلى بنسبة 100% ليكون رأس الدبوس هو المركز */
            transform: translate(-50%, -100%); 
            z-index: 1000; /* طبقة عالية جداً */
            pointer-events: none; /* السماح بلمس الخريطة تحته */
            
            /* تصميم الدبوس */
            color: #e60000; /* اللون الأحمر */
            font-size: 3rem; /* حجم كبير */
            text-shadow: 0 5px 15px rgba(0,0,0,0.3); /* ظل ليكون واضحاً */
            filter: drop-shadow(0 2px 2px rgba(255,255,255,0.5)); /* حدود بيضاء خفيفة */
            
            display: flex; flex-direction: column; align-items: center;
        }
        
        /* نقطة الارتكاز أسفل الدبوس */
        .center-pin::after {
            content: '';
            width: 8px; height: 8px;
            background: rgba(0,0,0,0.3);
            border-radius: 50%;
            position: absolute; bottom: 2px;
            box-shadow: 0 0 5px rgba(0,0,0,0.5);
        }

        /* 3. زر العودة العائم */
        .back-btn {
            position: absolute; top: 20px; right: 20px; z-index: 1100;
            background: #fff; width: 45px; height: 45px; border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15); display: flex;
            align-items: center; justify-content: center; color: #333;
            text-decoration: none; font-size: 1.2rem;
        }

        /* 4. صندوق العنوان السفلي (Bottom Sheet Style) */
        .address-form-sheet {
            background: #fff;
            padding: 25px 20px;
            border-top-left-radius: 25px;
            border-top-right-radius: 25px;
            box-shadow: 0 -5px 30px rgba(0,0,0,0.1);
            z-index: 1100;
            position: relative;
            transition: transform 0.3s ease;
        }

        .sheet-handle {
            width: 50px; height: 5px; background: #e0e0e0;
            border-radius: 10px; margin: 0 auto 20px auto;
        }

        .form-title {
            text-align: center; font-weight: 700; margin-bottom: 20px;
            font-size: 1.1rem; color: #333;
        }

        .input-group { margin-bottom: 15px; }
        .input-group input {
            width: 100%; padding: 14px 15px;
            border: 1px solid #eee; background: #f9f9f9;
            border-radius: 12px; font-family: inherit; font-size: 1rem;
            box-sizing: border-box; transition: 0.3s;
        }
        .input-group input:focus {
            background: #fff; border-color: #007bff; outline: none;
        }

        .save-btn {
            width: 100%; background: #e60000; color: #fff; border: none;
            padding: 15px; border-radius: 12px; font-size: 1.1rem; font-weight: 700;
            cursor: pointer; box-shadow: 0 4px 15px rgba(230, 0, 0, 0.3);
        }

        /* زر تحديد الموقع */
        .locate-me-fab {
            position: absolute; bottom: 20px; right: 20px; z-index: 1000;
            background: #fff; width: 50px; height: 50px; border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); border: none;
            color: #333; font-size: 1.4rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        
        /* رسالة التحميل */
        .loading-overlay {
            position: absolute; top:0; left:0; width:100%; height:100%;
            background: rgba(255,255,255,0.8); z-index: 2000;
            display: flex; align-items: center; justify-content: center;
            flex-direction: column;
        }
    </style>
</head>
<body>

    <!-- زر العودة -->
    <a href="my_addresses.php" class="back-btn"><i class="fas fa-arrow-right"></i></a>

    <!-- الخريطة -->
    <div class="map-wrapper">
        <div id="map"></div>
        
        <!-- الدبوس الثابت (CSS + FontAwesome) -->
        <div class="center-pin">
            <i class="fas fa-map-marker-alt"></i>
        </div>

        <!-- زر تحديد الموقع -->
        <button class="locate-me-fab" id="locate-btn" title="موقعي الحالي">
            <i class="fas fa-crosshairs"></i>
        </button>
        
        <!-- شاشة تحميل أولية -->
        <div id="map-loader" class="loading-overlay">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #e60000;"></i>
            <p style="margin-top:10px; font-weight:600;">جاري تحديد موقعك...</p>
        </div>
    </div>

    <!-- نموذج البيانات -->
    <div class="address-form-sheet">
        <div class="sheet-handle"></div>
        <h3 class="form-title">تأكيد الموقع</h3>
        
        <form action="php/manage_address.php" method="POST">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <input type="hidden" name="address_id" value="<?php echo $address_id; ?>">
            <input type="hidden" name="latitude" id="lat" value="<?php echo $data['latitude'] ?? ''; ?>">
            <input type="hidden" name="longitude" id="lng" value="<?php echo $data['longitude'] ?? ''; ?>">
            
            <div class="input-group">
                <input type="text" name="address_name" value="<?php echo htmlspecialchars($data['address_name'] ?? ''); ?>" placeholder="اسم العنوان (المنزل، العمل...)" required>
            </div>
            
            <div class="input-group">
                <input type="text" name="address_details" value="<?php echo htmlspecialchars($data['address_details'] ?? ''); ?>" placeholder="تفاصيل (شارع، بناء، طابق)" required>
            </div>
            
            <button type="submit" class="save-btn">حفظ واستخدام هذا الموقع</button>
        </form>
    </div>

    <script src="js/lib/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // الإحداثيات الافتراضية (دمشق)
            let currentLat = <?php echo $data['latitude'] ?? 33.5138; ?>;
            let currentLng = <?php echo $data['longitude'] ?? 36.2765; ?>;
            const isEditMode = <?php echo $action === 'edit' ? 'true' : 'false'; ?>;

            // تهيئة الخريطة
            const map = L.map('map', { 
                zoomControl: false, // إخفاء أزرار الزوم لتوفير المساحة
                attributionControl: false // إخفاء الحقوق لتنظيف الواجهة
            }).setView([currentLat, currentLng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

            // تحديث الحقول المخفية
            function updateInputs() {
                const center = map.getCenter();
                document.getElementById('lat').value = center.lat.toFixed(7);
                document.getElementById('lng').value = center.lng.toFixed(7);
            }

            map.on('move', updateInputs);
            map.on('moveend', updateInputs);

            function locateUser() {
                const loader = document.getElementById('map-loader');
                if(loader) loader.style.display = 'flex';

                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            const { latitude, longitude } = pos.coords;
                            map.setView([latitude, longitude], 17);
                            updateInputs();
                            if(loader) loader.style.display = 'none';
                        },
                        (err) => {
                            if(loader) loader.style.display = 'none';
                        },
                        { enableHighAccuracy: true, timeout: 5000 }
                    );
                } else {
                    if(loader) loader.style.display = 'none';
                }
            }

            if (!isEditMode) {
                locateUser();
            } else {
                document.getElementById('map-loader').style.display = 'none';
            }
            document.getElementById('locate-btn').addEventListener('click', locateUser);

            // تحديث أولي
            updateInputs();
        });
    </script>
</body>
</html>