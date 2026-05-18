<?php
$page_title = 'تعديل النشاط التجاري (إداري)';
include 'header.php';

// --- 1. حارس البوابة (Permissions) ---
if (!hasPermission('edit_business')) {
    echo "<div class='container' style='padding:50px; text-align:center; color:red;'><h2>عذراً، ليس لديك صلاحية للوصول.</h2></div>";
    include 'footer.php';
    exit;
}

$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($business_id === 0) die("خطأ: لم يتم تحديد النشاط التجاري.");

try {
    // --- 2. جلب بيانات المتجر ---
    $stmt = $pdo->prepare("SELECT * FROM businesses WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$business) die("خطأ: النشاط التجاري غير موجود.");

    // التحقق من صلاحية المحافظة (للأدمن العادي)
    if (!hasPermission('super_admin_access_all') && $admin_governorate_id !== (int)$business['governorate_id']) {
        echo "<div class='container' style='padding:50px; text-align:center; color:red;'><h2>هذا النشاط لا يتبع لمحافظتك.</h2></div>"; 
        include 'footer.php'; exit;
    }
    
    // --- 3. جلب البيانات المساعدة ---
    $governorates = $pdo->query("SELECT id, name FROM governorates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    // جلب المستخدمين (لتغيير المالك إن لزم الأمر) - المفعلين فقط
    $users = $pdo->query("SELECT id, username, phone FROM users WHERE is_verified=1 AND deleted_at IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. جلب التفاصيل والعلاقات ---
    $details_from_db = $pdo->prepare("SELECT detail_key, detail_value FROM business_details WHERE business_id = ?");
    $details_from_db->execute([$business_id]);
    $details_from_db = $details_from_db->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $gallery_images_from_db = $pdo->prepare("SELECT id, image_path FROM business_gallery WHERE business_id = ?");
    $gallery_images_from_db->execute([$business_id]);
    $gallery_images_from_db = $gallery_images_from_db->fetchAll(PDO::FETCH_ASSOC);

    $menu_items_from_db = $pdo->prepare("SELECT id, category_name, item_name, description, price, image_path FROM business_menu_items WHERE business_id = ? ORDER BY id ASC");
    $menu_items_from_db->execute([$business_id]);
    $menu_items_from_db = $menu_items_from_db->fetchAll(PDO::FETCH_ASSOC);

    $deals_from_db = $pdo->prepare("SELECT id, category_name, deal_name, description, old_price, new_price, image_path FROM business_deals WHERE business_id = ? ORDER BY id ASC");
    $deals_from_db->execute([$business_id]);
    $deals_from_db = $deals_from_db->fetchAll(PDO::FETCH_ASSOC);

    $offer_images = $pdo->prepare("SELECT id, image_path FROM business_offers WHERE business_id = ? ORDER BY display_order ASC");
    $offer_images->execute([$business_id]);
    $offer_images = $offer_images->fetchAll(PDO::FETCH_ASSOC);

    $opening_hours_decoded = json_decode($business['opening_hours'] ?? '[]', true);

} catch (Exception $e) { die("فشل جلب البيانات: " . $e->getMessage()); }

// --- 5. إعدادات الحقول والقوائم ---
$dynamic_fields_config = [
    // 'مطعم' => [
    //     ['name' => 'نوع المطبخ', 'type' => 'text', 'placeholder' => 'مثال: شرقي، إيطالي، وجبات سريعة'],
    //     ['name' => 'مناسب للعائلات', 'type' => 'select', 'options' => ['نعم', 'لا', 'أماكن مخصصة']],
    //     ['name' => 'يقدم أركيلة', 'type' => 'select', 'options' => ['نعم', 'لا']],
    //     ['name' => 'يوجد واي فاي', 'type' => 'select', 'options' => ['نعم', 'لا']],
    // ],
    // 'فندق' => [
    //     ['name' => 'تصنيف النجوم', 'type' => 'select', 'options' => ['5 نجوم', '4 نجوم', '3 نجوم', 'شقق فندقية', 'غير مصنف']],
    //     ['name' => 'مسبح', 'type' => 'select', 'options' => ['متوفر', 'غير متوفر']],
    //     ['name' => 'مواقف سيارات', 'type' => 'select', 'options' => ['متوفرة', 'غير متوفرة']],
    //     ['name' => 'يوجد واي فاي', 'type' => 'select', 'options' => ['نعم', 'لا']],
    // ],
    'عصائر وكوكتيلات' => [
        ['name' => 'نوع المشروب', 'type' => 'select', 'options' => ['كوكتيلات فواكه طبيعية', 'عصائر طازجة', 'سموثي وميلك شيك', 'مشروبات ساخنة (قهوة/شاي)', 'مشروبات غازية ومثلجة', 'سلطات فواكه', 'وافل وكريب', 'شامل جميع الأنواع']],
        ['name' => 'طبيعة الجلسة', 'type' => 'select', 'options' => ['تيك أوي (سفري) فقط', 'جلسات داخلية محدودة', 'صالة واسعة', 'تراس خارجي', 'جلسات عائلية']],
        ['name' => 'خدمات إضافية', 'type' => 'select', 'options' => ['يوجد واي فاي مجاني', 'شاشات عرض مباريات', 'مكان للمدخنين', 'لا يوجد']],
    ],
    'محلات أكل' => [
        ['name' => 'تخصص المحل', 'type' => 'select', 'options' => ['سوبر ماركت شامل', 'ميني ماركت', 'خضار وفواكه', 'لحوم وأسماك (ملحمة)', 'ألبان وأجبان', 'محمصة ومكسرات', 'بهارات وعطارة', 'منتجات ريفية ومونة', 'مخبز آلي']],
        ['name' => 'خدمة التوصيل', 'type' => 'select', 'options' => ['نعم، توصيل شامل', 'نعم، للمناطق القريبة فقط', 'لا يوجد توصيل']],
        ['name' => 'طرق الدفع', 'type' => 'select', 'options' => ['كاش فقط', 'كاش + سيريتل/إم تي إن كاش', 'تحويل بنكي']],
    ],
    'حلويات' => [
        ['name' => 'نوع الحلويات', 'type' => 'select', 'options' => ['حلويات عربية وشرقية', 'كاتو وحلويات غربية', 'بوظة ومثلجات', 'شوكولا وضيافة مناسبات', 'نابلسية ومدلوقة', 'كريب ووافل', 'شامل']],
        ['name' => 'توصية خاصة', 'type' => 'select', 'options' => ['يوجد تفصيل قوالب كاتو', 'تجهيز بوفيهات أعراس', 'ضيافة ولادات', 'لا يوجد']],
        ['name' => 'جلسات بالمحل', 'type' => 'select', 'options' => ['نعم، يوجد طاولات', 'لا، سفري فقط']],
    ],
    'معجنات' => [
        ['name' => 'أصناف المعجنات', 'type' => 'select', 'options' => ['مناقيش وصفيحة', 'بيتزا إيطالية', 'فطائر وكرواسون', 'خبز وصمون', 'شاورما ومعجنات', 'شامل']],
        ['name' => 'طريقة البيع', 'type' => 'select', 'options' => ['بالقطعة', 'بالكيلو', 'تجهيز ولائم']],
        ['name' => 'الفرن', 'type' => 'select', 'options' => ['فرن حجري', 'فرن آلي', 'فرن غاز']],
    ],
    'بقالة' => [
        ['name' => 'حجم البقالة', 'type' => 'select', 'options' => ['دكان حي صغير', 'ميني ماركت', 'سوبر ماركت متوسط', 'هايبر ماركت']],
        ['name' => 'خدمات متوفرة', 'type' => 'select', 'options' => ['بيع جملة ومفرق', 'مفرق فقط', 'عروض أسبوعية']],
        ['name' => 'دوام البقالة', 'type' => 'select', 'options' => ['دوام عادي', 'حتى ساعة متأخرة', '24 ساعة']],
    ],

    // --- قطاع الملابس والموضة ---
    'متجر ملابس' => [
        ['name' => 'الفئة المستهدفة', 'type' => 'select', 'options' => ['نسائي فقط', 'رجالي فقط', 'أطفال ومواليد', 'ملابس رياضية', 'عائلي شامل', 'لانجري وملابس نوم', 'عبايات وألبسة شرعية']],
        ['name' => 'نمط الملابس', 'type' => 'select', 'options' => ['كاجوال ويومي', 'رسمي وبدلات', 'فساتين سهرة ومناسبات', 'ملابس منزلية', 'ملابس عمل ويونيفورم', 'ماركات عالمية (أوريجينال)', 'صناعة وطنية فاخرة']],
        ['name' => 'خدمات القياس', 'type' => 'select', 'options' => ['يوجد غرف قياس', 'لا يوجد غرف قياس', 'يوجد خياط للتعديل']],
    ],
    'متجر أحذية وحقائب' => [
        ['name' => 'تخصص المتجر', 'type' => 'select', 'options' => ['أحذية نسائية وحقائب', 'أحذية رجالية رسمية ورياضية', 'أحذية أطفال', 'حقائب سفر ومدرسية', 'منتجات جلدية طبيعية', 'شامل لجميع الفئات']],
        ['name' => 'نوع البضاعة', 'type' => 'select', 'options' => ['ماركات عالمية', 'صناعة وطنية نخب أول', 'بضاعة مستوردة', 'شعبي وتجاري']],
        ['name' => 'أحذية طبية', 'type' => 'select', 'options' => ['متوفر تشكيلة واسعة', 'غير متوفر']],
    ],
    'مكياجات وعطور' => [
        ['name' => 'التخصص الدقيق', 'type' => 'select', 'options' => ['مكياج ومستحضرات تجميل', 'عطورات وبخور', 'عناية بالبشرة والشعر', 'عدسات تجميلية', 'شامل (كوزمتك)']],
        ['name' => 'نوع الماركات', 'type' => 'select', 'options' => ['ماركات عالمية (أوريجينال)', 'ماركات كورية', 'ماركات محلية (وطني)', 'تعبئة وتركيب (عطور)', 'هاي كوبي (High Copy)', 'متنوع']],
        ['name' => 'خدمة التجربة', 'type' => 'select', 'options' => ['يوجد تستر (Tester)', 'لا يوجد']],
    ],
    'هدايا وإكسسوارات' => [
        ['name' => 'نوع المنتجات', 'type' => 'select', 'options' => ['هدايا وتذكارات', 'إكسسوارات نسائية وفضة', 'ساعات ونظارات', 'ألعاب أطفال ودمى', 'تحف وديكور منزلي', 'ورود وشوكولا', 'شامل']],
        ['name' => 'خدمات التغليف', 'type' => 'select', 'options' => ['تغليف هدايا احترافي', 'تغليف بسيط', 'بوكسات ومفاجآت', 'لا يوجد']],
        ['name' => 'تفصيل حسب الطلب', 'type' => 'select', 'options' => ['طباعة على الأكواب والتيشرتات', 'حفر أسماء (ليزر)', 'تفصيل سلاسل', 'لا يوجد']],
    ],

    // --- قطاع الإلكترونيات والتقنية ---
    'هواتف وإكسسوارات' => [
        ['name' => 'نوع النشاط الرئيسي', 'type' => 'select', 'options' => ['بيع أجهزة جديدة ومستعملة', 'بيع إكسسوارات فقط', 'مركز صيانة متخصص', 'برمجة وسوفت وير', 'شامل (بيع وصيانة)']],
        ['name' => 'الماركات المتوفرة', 'type' => 'select', 'options' => ['Samsung & Apple', 'Xiaomi & Infinix & Realme', 'جميع الماركات العالمية', 'إكسسوارات لجميع الماركات']],
        ['name' => 'خدمات التقسيط', 'type' => 'select', 'options' => ['لا يوجد', 'يوجد بالتعاون مع البنك', 'يوجد تقسيط شخصي']],
        ['name' => 'كفالة الأجهزة', 'type' => 'select', 'options' => ['كفالة الشركة الوكيلة', 'كفالة المحل', 'بدون كفالة (مستعمل)']],
    ],
    'إلكترونيات' => [
        ['name' => 'نوع المنتجات', 'type' => 'select', 'options' => ['أجهزة منزلية كبيرة (برادات/غسالات)', 'أدوات مطبخ كهربائية', 'شاشات وأنظمة صوت', 'لابتوبات وكمبيوترات', 'كاميرات ومراقبة', 'قطع غيار إلكترونية', 'شامل']],
        ['name' => 'حالة الأجهزة', 'type' => 'select', 'options' => ['جديد فقط', 'مستعمل (بالة أوروبية)', 'جديد ومستعمل']],
        ['name' => 'خدمة الصيانة', 'type' => 'select', 'options' => ['ورشة صيانة معتمدة', 'صيانة فورية', 'لا يوجد']],
    ],
    'أجهزة كشف معادن' => [
        ['name' => 'نوع النشاط', 'type' => 'select', 'options' => ['بيع أجهزة جديدة', 'بيع أجهزة مستعملة', 'تأجير أجهزة', 'بيع وتأجير', 'صيانة ومعايرة']],
        ['name' => 'نظام الأجهزة', 'type' => 'select', 'options' => ['نظام صوتي (VLF)', 'نظام تصويري 3D', 'نظام استشعاري (بعيد المدى)', 'نظام حث نبضي', 'شامل جميع الأنظمة']],
        ['name' => 'التدريب والكفالة', 'type' => 'select', 'options' => ['يوجد تدريب ميداني وكفالة', 'كفالة فقط', 'بدون تدريب']],
    ],

    // --- قطاع الخدمات والسيارات ---
    'سيارات' => [
        ['name' => 'نوع النشاط', 'type' => 'select', 'options' => ['معرض بيع وشراء سيارات', 'مكتب تأجير سيارات', 'مركز صيانة وميكانيك', 'زينة وإكسسوارات سيارات', 'قطع غيار (جديد/مستعمل)', 'غسيل وتشحيم (مغسل)', 'فحص فني']],
        ['name' => 'اختصاص الماركات', 'type' => 'select', 'options' => ['جميع الماركات', 'كوري (كيا/هيونداي)', 'أوروبي (مرسيدس/BMW)', 'ياباني (تويوتا/نيسان)', 'قطع غيار فقط']],
        ['name' => 'خدمات إضافية', 'type' => 'select', 'options' => ['تأمين سيارات', 'تخليص معاملات', 'خدمة طريق (ونش)', 'لا يوجد']],
    ],
    'سياحة' => [
        ['name' => 'الخدمات المقدمة', 'type' => 'select', 'options' => ['حجز تذاكر طيران', 'حجز فنادق ومنتجعات', 'رحلات سياحية داخلية', 'رحلات خارجية (روبات)', 'تأشيرات وفيزا', 'خدمات حج وعمرة', 'شامل']],
        ['name' => 'دوام المكتب', 'type' => 'select', 'options' => ['دوام إداري', 'خدمة أونلاين 24/7', 'حسب الموعد']],
    ],
    'خدمات طبية' => [
        ['name' => 'نوع المنشأة', 'type' => 'select', 'options' => ['صيدلية', 'عيادة أسنان', 'عيادة تجميل وليزر', 'مخبر تحاليل طبية', 'مركز أشعة وتصوير', 'عيادة طبية تخصصية', 'مركز علاج فيزيائي']],
        ['name' => 'نظام الحجز', 'type' => 'select', 'options' => ['بالموعد المسبق', 'حسب الدور (Walk-in)', 'طوارئ واستقبال فوري']],
        ['name' => 'دوام الطوارئ', 'type' => 'select', 'options' => ['متوفر 24 ساعة', 'غير متوفر']],
    ],

    // --- قطاعات متنوعة ---
    'بصريات ونظارات' => [
        ['name' => 'المنتجات والخدمات', 'type' => 'select', 'options' => ['نظارات طبية وشمسية', 'عدسات لاصقة (طبية/ملونة)', 'فحص نظر وتجهيز نظارات', 'إكسسوارات نظارات', 'شامل']],
        ['name' => 'فحص النظر', 'type' => 'select', 'options' => ['يوجد طبيب/فاحص مختص', 'يوجد جهاز فحص كمبيوتر', 'لا يوجد فحص']],
        ['name' => 'تجهيز فوري', 'type' => 'select', 'options' => ['نعم، خلال ساعة', 'لا، التسليم لاحقاً']],
    ],
    'مكتبة وقرطاسية' => [
        ['name' => 'التخصص', 'type' => 'select', 'options' => ['قرطاسية مدرسية ومكتبية', 'كتب وروايات ثقافية', 'مركز خدمات (طباعة/تصوير)', 'مستلزمات فنية وهندسية', 'حقائب وألعاب تعليمية', 'شامل']],
        ['name' => 'خدمات الطباعة', 'type' => 'select', 'options' => ['طباعة ملونة وليزرية', 'تجليد وتغليف', 'طباعة مخططات هندسية', 'لا يوجد']],
        ['name' => 'توصيل للمدارس', 'type' => 'select', 'options' => ['نعم، متوفر', 'لا يوجد']],
    ],
    'زهور ونباتات' => [
        ['name' => 'نوع المعروضات', 'type' => 'select', 'options' => ['زهور طبيعية وباقات', 'نباتات زينة داخلية', 'زهور صناعية', 'أحواض وفازات', 'شتول وبذور زراعية', 'شامل']],
        ['name' => 'تنسيق المناسبات', 'type' => 'select', 'options' => ['تنسيق أعراس وحفلات', 'تزيين سيارات', 'تنسيق هدايا وشوكولا', 'لا يوجد']],
        ['name' => 'توصيل هدايا', 'type' => 'select', 'options' => ['نعم، مع كرت إهداء', 'لا يوجد']],
    ],
    'مفروشات وديكور' => [
        ['name' => 'نوع المفروشات', 'type' => 'select', 'options' => ['أثاث منزلي (كنب/غرف نوم)', 'سجاد وموكيت', 'ستائر وأقمشة', 'أثاث مكتبي', 'تحف وإكسسوارات ديكور', 'شامل']],
        ['name' => 'الخدمات', 'type' => 'select', 'options' => ['بيع جاهز فقط', 'تفصيل حسب الطلب', 'تنجيد وتجديد', 'تركيب وتوصيل']],
        ['name' => 'بلد المنشأ', 'type' => 'select', 'options' => ['صناعة وطنية', 'مستورد', 'متنوع']],
    ],
    'أراجيل ودخان' => [
        ['name' => 'نوع النشاط', 'type' => 'select', 'options' => ['بيع مستلزمات فقط', 'توصيل أراجيل جاهزة (دليفري)', 'بيع وتوصيل شامل', 'تعهيد حفلات ومناسبات']]
    ]
];

$suggested_menu_categories = [
    // 'مطعم' => ['مقبلات', 'وجبات رئيسية', 'سلطات', 'مشروبات', 'حلويات'],
    // 'فندق' => ['غرفة مفردة', 'غرفة مزدوجة', 'جناح', 'شقق فندقية', 'خدمات إضافية'],
    'عصائر وكوكتيلات' => ['مشروبات ساخنة', 'مشروبات باردة', 'كوكتيلات وعصائر'],
    'محلات أكل' => ['لحوم', 'أجبان', 'خضار وفواكه', 'بهارات', 'منتجات معلبة'],
    'متجر ملابس' => ['رجالي', 'نسائي', 'أطفال', 'إكسسوارات', 'أحذية'],
    'متجر أحذية وحقائب' => ['أحذية رجالية', 'أحذية نسائية', 'أطفال', 'حقائب', 'إكسسوارات'],
    'أجهزة كشف معادن' => ['جهاز صوتي','جهاز تصويري','حثي نبضي','استشعاري','صوتي وتصويري','أسياخ'],
    'مولات' => ['محلات تجارية', 'مطاعم', 'مقاهي', 'منطقة ألعاب', 'سوبرماركت'],
    'صالات أفراح' => ['حجز القاعة', 'ضيافة', 'تصوير', 'دي جي', 'تزيين'],
    'نادي رياضة' => ['اشتراك شهري', 'تدريب شخصي', 'كارديو', 'أوزان', 'مسبح'],
    'مراكز تعليمية' => ['لغات', 'حاسوب', 'دورات مهنية', 'دروس خصوصية', 'شهادات معتمدة'],
    'حفلات عامة' => ['حفلة موسيقية', 'مهرجان', 'معرض', 'مسرحية', 'سينما'],
    'خدمات طبية' => ['عيادات', 'مستشفيات', 'صيدليات', 'مختبرات', 'طوارئ'],
    'سياحة' => ['حجز فنادق', 'رحلات سياحية', 'حجوزات طيران', 'برامج سياحية'],
    'سيارات' => ['بيع سيارات', 'إيجار سيارات', 'صيانة', 'قطع غيار', 'إكسسوارات'],
    'إلكترونيات' => ['هواتف', 'لابتوبات', 'أجهزة منزلية', 'ملحقات', 'صيانة'],
    'بقالة' => ['مواد غذائية', 'مشروبات', 'معلبات', 'منظفات', 'خدمة توصيل'],
    'حلويات' => ['شرقية', 'غربية', 'شوكولا', 'كيك', 'بوظة'],
    'معجنات' => ['بيتزا', 'فطائر', 'سندويش', 'مناقيش', 'كرواسون'],
    'هدايا وإكسسوارات' => ['هدايا', 'مجوهرات', 'إكسسوارات منزلية', 'تغليف هدايا', 'ألعاب'],
    'مكياجات وعطور' => ['عطور رجالية', 'عطور نسائية', 'مكياج عيون', 'أحمر شفاه', 'كريمات عناية', 'بخور وعود'],
    'هواتف وإكسسوارات' => ['أجهزة جديدة', 'أجهزة مستعملة', 'سماعات', 'كفرات وحماية', 'شواحن وكابلات', 'ساعات ذكية'],
    'بصريات ونظارات' => ['نظارات شمسية رجالي', 'نظارات شمسية نسائي', 'إطارات طبية', 'عدسات ملونة', 'عدسات طبية', 'محاليل'],
    'مكتبة وقرطاسية' => ['دفاتر وكراسات', 'أقلام وألوان', 'كتب تعليمية', 'روايات', 'حقائب مدرسية', 'خدمات طباعة'],
    'زهور ونباتات' => ['باقات ورد طبيعي', 'نباتات داخلية', 'فازات وأحواض', 'بوكيهات مناسبات', 'شوكولا وهدايا'],
    'مفروشات وديكور' => ['غرف جلوس', 'غرف نوم', 'طاولات', 'سجاد', 'إضاءة', 'إكسسوارات منزلية'],
    'أراجيل ودخان' => ['معسل ونكهات', 'فحم ومشعلات', 'أراجيل كاملة', 'رؤوس ونباربيج', 'إكسسوارات وملاقط', 'أراجيل جاهزة (توصيل)', 'سجائر إلكترونية (Vape)'],
];

$suggested_deal_categories = [
    'محلات أكل' => ['عروض الغداء', 'وجبات عائلية', 'خصم نهاية الأسبوع'],
    'أجهزة كشف معادن' => ['جهاز صوتي','جهاز تصويري','حثي نبضي','استشعاري','صوتي وتصويري','أسياخ'],
    'عصائر وكوكتيلات' => ['قهوة + قطعة حلوى', 'مشاريب السهرة'],
    'متجر ملابس' => ['تخفيضات نهاية الموسم', 'اشترِ قطعة واحصل على الثانية مجاناً'],
    'مكياجات وعطور' => ['بوكسات هدايا', 'عروض العرايس', 'خصومات الجمعة'],
    'هواتف وإكسسوارات' => ['باكج الحماية المتكامل', 'عروض الاستبدال', 'تصفيات الإكسسوارات'],
    'بصريات ونظارات' => ['اشترِ إطار واحصل على عدسات مجاناً', 'عروض النظارات الشمسية'],
    'زهور ونباتات' => ['عروض يوم الأم', 'تنسيقات التخرج', 'باقات الجمعة'],
];


if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];
?>

<!-- استدعاء المكتبات -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.css" />
<link rel="stylesheet" href="../css/add_business_form.css"> 

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.umd.js"></script>

<style>
    /* تنسيقات إضافية خاصة بالأدمن والتحسينات المطلوبة */
    .tab-pane { display: none; }
    .tab-pane.active { display: block; animation: fadeInUp 0.4s ease-out; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    /* تحسينات العرض (Modern Cards CSS Fixes) */
    .items-toolbar {
        background: #fff; border: 1px solid #e0e0e0;
        padding: 15px; border-radius: 12px; margin-bottom: 20px;
        display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .search-box-wrapper { flex-grow: 1; position: relative; }
    .search-box-wrapper input {
        width: 100%; padding: 10px 15px 10px 35px; border-radius: 8px;
        border: 1px solid #ced4da; outline: none; box-sizing: border-box; font-family: 'Cairo', sans-serif;
    }
    .search-box-wrapper::after {
        content: '\f002'; font-family: "Font Awesome 5 Free"; font-weight: 900;
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #adb5bd;
    }
    .toggle-all-btn {
        background: #f8f9fa; border: 1px solid #ced4da; padding: 10px 15px;
        border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 13px; color: #555; white-space: nowrap;
    }

    /* حالة البطاقة "المطوية" */
    .menu-item-entry.collapsed {
        padding: 10px 15px; cursor: pointer;
        display: flex; align-items: center; gap: 15px;
        background: #fff; border: 1px solid #eee; border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 10px;
        min-height: 70px;
    }
    .menu-item-entry.collapsed .menu-item-category-manager,
    .menu-item-entry.collapsed .menu-item-fields,
    .menu-item-entry.collapsed .remove-btn-wrapper,
    .menu-item-entry.collapsed .delete-existing-item { display: none !important; }

    /* الملخص عند الطي */
    .collapsed-summary { display: none; width: 100%; align-items: center; gap: 15px; }
    .menu-item-entry.collapsed .collapsed-summary { display: flex; }

    .summary-img {
        width: 50px; height: 50px; border-radius: 8px; object-fit: cover;
        border: 1px solid #eee; background: #f9f9f9; flex-shrink: 0;
    }
    .summary-info { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }
    .summary-name { font-weight: 700; font-size: 15px; color: #333; }
    .summary-price { font-size: 13px; color: #198754; font-weight: 600; margin-top: 2px; }
    .expand-icon { color: #adb5bd; transition: 0.3s; margin-left: 10px; }
    .menu-item-entry:not(.collapsed) .expand-icon { transform: rotate(180deg); }

    /* إصلاح حجم الصورة عند الفتح */
    .menu-item-fields { display: grid; grid-template-columns: 130px 1fr; gap: 20px; align-items: start; }
    .image-upload-wrapper { width: 100%; height: 130px; max-width: 130px; border-radius: 10px; overflow: hidden; position: relative; border: 2px dashed #ddd; background: #fafafa; }
    .image-upload-wrapper img { width: 100%; height: 100%; object-fit: cover; }

    @media (max-width: 768px) {
        .menu-item-fields { grid-template-columns: 1fr; justify-items: center; text-align: center; }
        .image-upload-wrapper { margin: 0 auto 15px auto; }
        .items-toolbar { flex-direction: column; align-items: stretch; }
    }
</style>

<div class="form-container" style="padding-top: 20px;">
    <!-- عرض الرسائل -->
    <?php if (isset($_SESSION['admin_message'])): ?>
        <div style="padding:15px; margin-bottom:20px; border-radius:8px; font-weight:bold;
            background: <?php echo $_SESSION['admin_message_type'] == 'success' ? '#d4edda' : '#f8d7da'; ?>;
            color: <?php echo $_SESSION['admin_message_type'] == 'success' ? '#155724' : '#721c24'; ?>;
            border: 1px solid <?php echo $_SESSION['admin_message_type'] == 'success' ? '#c3e6cb' : '#f5c6cb'; ?>;">
            <?php echo $_SESSION['admin_message']; unset($_SESSION['admin_message'], $_SESSION['admin_message_type']); ?>
        </div>
    <?php endif; ?>

    <!-- الفورم -->
    <form id="edit-business-form" action="../php/update_business_user.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="business_id" value="<?php echo $business_id; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="is_admin_action" value="1">
        
        <!-- شريط الخطوات -->
        <div class="form-wizard-nav">
            <div class="nav-tab active" data-step="0">1. المعلومات</div>
            <div class="nav-tab" data-step="1">2. التفاصيل</div>
            <div class="nav-tab" data-step="2">3. الصور</div>
            <div class="nav-tab" data-step="3">4. سلايدر العروض</div>
            <div class="nav-tab" data-step="4">5. العروض</div>
            <div class="nav-tab" data-step="5">6. المنيو</div>
            <div class="nav-tab" data-step="6">7. الدوام</div>
        </div>

        <!-- الخطوة 1: المعلومات -->
        <div class="tab-pane active" id="step-0">
            <div class="form-section">
                <h2><i class="fas fa-store"></i> المعلومات الأساسية والموقع</h2>
                <div class="form-grid">
                    <div class="form-group full-width"><label>اسم النشاط <span style="color:red">*</span></label><input type="text" id="name" name="name" value="<?php echo htmlspecialchars($business['name']); ?>" required></div>
                    
                    <!-- حقل تغيير المالك -->
                    <div class="form-group">
                        <label>مالك المتجر (تغيير الملكية)</label>
                        <select name="new_owner_id" style="background:#fff3cd;">
                            <option value="">-- اترك فارغاً للإبقاء على المالك الحالي --</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php if($business['user_id'] == $u['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($u['username']) . " (" . htmlspecialchars($u['phone']) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group"><label>الفئة الرئيسية <span style="color:red">*</span></label>
                        <select id="category" name="category" required>
                            <?php foreach (array_keys($dynamic_fields_config) as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php if($business['category'] == $cat) echo 'selected'; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group"><label>المحافظة <span style="color:red">*</span></label>
                        <select id="governorate_id" name="governorate_id" required <?php if(!hasPermission('super_admin_access_all')) echo 'disabled'; ?>>
                            <?php foreach ($governorates as $gov): ?>
                                <option value="<?php echo $gov['id']; ?>" <?php if($business['governorate_id'] == $gov['id']) echo 'selected'; ?>><?php echo htmlspecialchars($gov['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(!hasPermission('super_admin_access_all')): ?><input type="hidden" name="governorate_id" value="<?php echo $business['governorate_id']; ?>"><?php endif; ?>
                    </div>

                    <div class="form-group"><label>المدينة</label><input type="text" name="city" value="<?php echo htmlspecialchars($business['city']); ?>" required></div>

                    <!-- === الإضافة الجديدة: حقل العملة === -->
                                        <!-- === تحسين حقل العملة === -->
                    <div class="form-group" style="background-color: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #dee2e6;">
                        <label style="color: #0d6efd; font-weight: bold;">عملة المتجر <span style="color:red">*</span></label>
                        <select name="currency" required style="border: 2px solid #0d6efd;">
                            <option value="SYP" <?php if(($business['currency'] ?? 'SYP') === 'SYP') echo 'selected'; ?>>ليرة سورية (SYP)</option>
                            <option value="USD" <?php if(($business['currency'] ?? 'SYP') === 'USD') echo 'selected'; ?>>دولار أمريكي (USD)</option>
                        </select>
                        <small style="color: #dc3545; font-size: 12px; display: block; margin-top: 5px;">
                            <i class="fas fa-exclamation-triangle"></i> تنبيه: تغيير العملة لا يقوم بتحويل أرقام الأسعار تلقائياً. يرجى تعديل أسعار المنتجات يدوياً بعد التغيير.
                        </small>
                    </div>
                    <!-- ======================== -->
                    <!-- ================================== -->

                    <div class="form-group full-width"><label>العنوان</label><input type="text" name="address" value="<?php echo htmlspecialchars($business['address'] ?? ''); ?>"></div>
                     <div class="form-group"><label>هاتف</label><input type="text" name="phone" value="<?php echo htmlspecialchars($business['phone'] ?? ''); ?>"></div> 
                     <div class="form-group"><label>واتساب</label><input type="text" name="whatsapp" value="<?php echo htmlspecialchars($business['whatsapp'] ?? ''); ?>"></div> 
                     <div class="form-group"><label>الموقع الإلكتروني</label><input type="url" name="website_url" value="<?php echo htmlspecialchars($business['website_url'] ?? ''); ?>"></div> 
                     <div class="form-group"><label>فيسبوك</label><input type="url" name="facebook_url" value="<?php echo htmlspecialchars($business['facebook_url'] ?? ''); ?>"></div> 
                     <div class="form-group"><label>إنستغرام</label><input type="url" name="instagram_url" value="<?php echo htmlspecialchars($business['instagram_url'] ?? ''); ?>"></div> 
                    <div class="form-group"><label>رابط فيديو</label><input type="url" name="video_url" value="<?php echo htmlspecialchars($business['video_url'] ?? ''); ?>" placeholder="https://..."></div>
                </div>
                <div class="form-group full-width" id="map-section"><label>الموقع</label><div id="map-container"></div>
                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($business['latitude'] ?? ''); ?>">
                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($business['longitude'] ?? ''); ?>">
                </div>
                <div class="form-group full-width"><label>وصف</label><textarea name="description"><?php echo htmlspecialchars($business['description'] ?? ''); ?></textarea></div>
            </div>
        </div>

        <!-- الخطوة 2 -->
        <div class="tab-pane" id="step-1">
            <div id="dynamic-fields-container">
                <?php foreach ($dynamic_fields_config as $cat => $fields): ?>
                    <div id="wrapper-<?php echo str_replace(' ', '_', $cat); ?>" class="dynamic-fields-wrapper form-section">
                        <h2>تفاصيل <?php echo htmlspecialchars($cat); ?></h2>
                        <div class="form-grid">
                            <?php foreach ($fields as $field): $val = $details_from_db[$field['name']] ?? ''; ?>
                                <div class="form-group"><label><?php echo htmlspecialchars($field['name']); ?></label>
                                <?php if ($field['type'] === 'select'): ?>
                                    <select name="details[<?php echo htmlspecialchars($field['name']); ?>]"><option value="">-- اختر --</option>
                                        <?php foreach ($field['options'] as $opt): ?><option value="<?php echo htmlspecialchars($opt); ?>" <?php if($val == $opt) echo 'selected'; ?>><?php echo htmlspecialchars($opt); ?></option><?php endforeach; ?>
                                    </select>
                                <?php else: ?><input type="<?php echo $field['type']; ?>" name="details[<?php echo htmlspecialchars($field['name']); ?>]" value="<?php echo htmlspecialchars($val); ?>"><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- الخطوة 3 -->
        <div class="tab-pane" id="step-2">
            <div class="form-section">
                <h2>الصور الرئيسية</h2>
                <div class="form-grid">
                    <div class="form-group"><label>الشعار</label><div class="image-uploader-group"><div class="image-uploader-box"><input type="file" id="logo_image_input" name="logo_image" accept="image/*"><div class="upload-content"><i class="fas fa-portrait upload-icon"></i><div class="upload-text">تغيير</div></div></div><div class="image-preview-container" id="logo-preview-container"></div><input type="hidden" name="delete_logo" id="delete_logo_input" value="0"></div></div>
                    <div class="form-group"><label>الغلاف</label><div class="image-uploader-group"><div class="image-uploader-box"><input type="file" id="cover_image_input" name="cover_image" accept="image/*"><div class="upload-content"><i class="fas fa-image upload-icon"></i><div class="upload-text">تغيير</div></div></div><div class="image-preview-container" id="cover-preview-container"></div><input type="hidden" name="delete_cover" id="delete_cover_input" value="0"></div></div>
                </div>
            </div>
            <div class="form-section"><h2>معرض الصور</h2><div class="image-uploader-group"><div class="image-uploader-box"><input type="file" id="gallery_images_new_input" name="gallery_images_new[]" multiple accept="image/*"><div class="upload-content"><i class="fas fa-photo-video upload-icon"></i><div class="upload-text">إضافة صور</div></div></div><div class="image-preview-container" id="gallery-previews-container"></div><div id="delete-gallery-container"></div></div></div>
        </div>

        <!-- الخطوة 4 -->
        <div class="tab-pane" id="step-3">
            <div class="form-section"><h2>سلايدر العروض</h2><div class="image-uploader-group"><div class="image-uploader-box"><input type="file" id="offer_images_new_input" name="offer_images_new[]" multiple accept="image/*"><div class="upload-content"><i class="fas fa-photo-video upload-icon"></i><div class="upload-text">إضافة صور</div></div></div><div class="image-preview-container" id="offers-previews-container"></div><div id="delete-offers-container"></div></div></div>
        </div>

        <!-- الخطوة 5 -->
        <div class="tab-pane" id="step-4">
            <div class="form-section"><h2>العروض والصفقات</h2><div id="deal-items-container"></div><button type="button" id="add-deal-item-btn" class="btn-add-item"><i class="fas fa-plus"></i> إضافة عرض</button></div>
        </div>

        <!-- الخطوة 6 -->
        <div class="tab-pane" id="step-5">
            <div class="form-section"><h2>قائمة الأسعار</h2><div id="menu-items-container"></div><button type="button" id="add-menu-item-btn"><i class="fas fa-plus"></i> إضافة عنصر</button></div>
        </div>

        <!-- الخطوة 7 -->
        <div class="tab-pane" id="step-6">
            <div class="form-section">
                <h2>ساعات العمل</h2>
                <div class="form-grid-hours">
                    <?php 
                    $days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة']; 
                    foreach($days as $day): 
                        $stored_val = $opening_hours_decoded[$day] ?? 'closed';
                        $clean_val = trim(strtolower($stored_val));
                        $is_closed = ($clean_val === 'closed' || $clean_val === 'مغلق' || $clean_val === '');
                        $is_24 = ($clean_val === '24 hours' || $clean_val === '24/7');
                        $start_time_val = '09:00'; $end_time_val = '22:00';   
                        if (!$is_closed && !$is_24) {
                            $parts = explode('-', $stored_val); if (count($parts) >= 2) { $start_time_val = trim($parts[0]); $end_time_val = trim($parts[1]); }
                        }
                    ?>

                        <div class="hours-row" id="row-<?php echo $day; ?>">
                            <div class="day-label"><?php echo $day; ?></div>
                            <div class="status-toggle"><label><input type="checkbox" class="day-status-cb" <?php echo !$is_closed ? 'checked' : ''; ?> onchange="toggleHours('<?php echo $day; ?>')"><span>مفتوح</span></label></div>
                            <div class="time-inputs-group <?php echo $is_closed ? 'disabled' : ''; ?>" id="group-<?php echo $day; ?>">
                                <div style="display: flex; flex-direction: column;"><span>من</span><input type="time" class="time-input start-time" value="<?php echo htmlspecialchars($is_24 ? '00:00' : $start_time_val); ?>" <?php echo $is_24 ? 'disabled' : ''; ?> onchange="updateHiddenInput('<?php echo $day; ?>')"></div>
                                <span style="font-weight: bold; color: #888;">-</span>
                                <div style="display: flex; flex-direction: column;"><span>إلى</span><input type="time" class="time-input end-time" value="<?php echo htmlspecialchars($is_24 ? '23:59' : $end_time_val); ?>" <?php echo $is_24 ? 'disabled' : ''; ?> onchange="updateHiddenInput('<?php echo $day; ?>')"></div>
                                <label style="font-size: 12px; margin-right: 10px; display: flex; align-items: center; gap: 4px;"><input type="checkbox" class="all-day-cb" <?php echo $is_24 ? 'checked' : ''; ?> onchange="toggle24Hours('<?php echo $day; ?>')"> 24 ساعة</label>
                            </div>
                            <input type="hidden" name="opening_hours[<?php echo $day; ?>]" id="input-<?php echo $day; ?>" value="<?php echo htmlspecialchars($stored_val); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- أزرار التنقل -->
        <div class="form-navigation">
            <button type="button" class="btn-nav btn-prev" id="prevBtn" onclick="nextPrev(-1)">السابق</button>
            <button type="button" class="btn-nav btn-next" id="nextBtn" onclick="nextPrev(1)">التالي</button>
            <button type="button" class="btn-nav btn-submit" id="submitBtn" onclick="nextPrev(1)">حفظ التعديلات</button>
        </div>
    </form>
</div>

<script>
    const existingData = {
        logo: <?php echo json_encode($business['logo_image'] ?? null); ?>,
        cover: <?php echo json_encode($business['cover_image'] ?? null); ?>,
        gallery: <?php echo json_encode($gallery_images_from_db); ?>,
        offers: <?php echo json_encode($offer_images); ?>,
        menuItems: <?php echo json_encode($menu_items_from_db); ?>,
        deals: <?php echo json_encode($deals_from_db); ?>
    };
    const suggestedMenuCategories = <?php echo json_encode($suggested_menu_categories); ?>;
    const suggestedDealCategories = <?php echo json_encode($suggested_deal_categories); ?>;

    let currentTab = 0;
    showTab(currentTab);

    function showTab(n) {
        const x = document.getElementsByClassName("tab-pane");
        const navTabs = document.getElementsByClassName("nav-tab");
        for (let i = 0; i < x.length; i++) { x[i].style.display = "none"; x[i].classList.remove("active"); navTabs[i].classList.remove("active"); }
        x[n].style.display = "block"; setTimeout(() => x[n].classList.add("active"), 10); navTabs[n].classList.add("active");
        document.getElementById("prevBtn").style.display = n == 0 ? "none" : "inline";
        if (n == (x.length - 1)) { document.getElementById("nextBtn").style.display = "none"; document.getElementById("submitBtn").style.display = "inline"; } 
        else { document.getElementById("nextBtn").style.display = "inline"; document.getElementById("nextBtn").innerHTML = "التالي"; document.getElementById("submitBtn").style.display = "none"; }
        if (n == 0 && typeof window.map !== 'undefined') setTimeout(() => { window.map.invalidateSize(); }, 200);
        window.scrollTo(0, 0);
    }

    function nextPrev(n) {
        const x = document.getElementsByClassName("tab-pane");
        if (n == 1 && !validateForm()) return false;
        const nextStep = currentTab + n;
        if (nextStep >= x.length) {
            document.getElementById("edit-business-form").submit();
            document.getElementById("submitBtn").disabled = true; document.getElementById("submitBtn").innerHTML = "جارٍ الحفظ...";
            return false;
        }
        currentTab = nextStep; showTab(currentTab);
    }

    function validateForm() {
        const x = document.getElementsByClassName("tab-pane");
        const currentTabDiv = x[currentTab];
        const currentInputs = currentTabDiv.querySelectorAll("input, select, textarea");
        let valid = true;
        for (let i = 0; i < currentInputs.length; i++) {
            const input = currentInputs[i];
            if (input.hasAttribute("required") && input.offsetParent !== null) {
                if (input.value.trim() === "") {
                    const parentEntry = input.closest('.menu-item-entry');
                    if (parentEntry && parentEntry.classList.contains('collapsed')) parentEntry.classList.remove('collapsed');
                    input.style.borderColor = "red"; input.reportValidity(); valid = false; return false;
                } else { input.style.borderColor = ""; }
            }
        }
        return valid;
    }

    document.addEventListener('DOMContentLoaded', () => {
        function safehtmlspecialchars(str) {
            if (str === null || typeof str === 'undefined') return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return str.toString().replace(/[&<>"']/g, m => map[m]);
        }

        const categorySelect = document.getElementById('category');
        const dynamicFieldsContainer = document.getElementById('dynamic-fields-container');
        const detailsPlaceholder = document.getElementById('details-placeholder-message');
        function handleCategoryChange() {
            if (detailsPlaceholder) detailsPlaceholder.style.display = 'none';
            dynamicFieldsContainer.querySelectorAll('.dynamic-fields-wrapper').forEach(w => w.style.display = 'none');
            const selectedWrapper = document.getElementById('wrapper-' + categorySelect.value.replace(/ /g, '_'));
            if (selectedWrapper) selectedWrapper.style.display = 'block';
        }
        categorySelect.addEventListener('change', handleCategoryChange);
        handleCategoryChange();

        function setupImageManager(config) {
            const input = document.getElementById(config.inputId);
            const previewContainer = document.getElementById(config.previewContainerId);
            const deleteContainer = config.deleteContainerId ? document.getElementById(config.deleteContainerId) : null;
            const deleteSingleInput = config.deleteSingleInputId ? document.getElementById(config.deleteSingleInputId) : null;
            let newFiles = new DataTransfer();
            let existingFileCount = 0;
            function createPreview(fileOrUrl, id = null, initialLoad = false) {
                const isExisting = typeof fileOrUrl === 'string';
                const src = isExisting ? `../${fileOrUrl}` : URL.createObjectURL(fileOrUrl);
                if (config.maxFiles && (newFiles.files.length + existingFileCount >= config.maxFiles) && !isExisting && !initialLoad) { alert(`لا يمكن رفع أكثر من ${config.maxFiles} صور.`); return null; }
                const wrapper = document.createElement('div'); wrapper.className = 'image-preview-item';
                wrapper.innerHTML = `<img src="${src}"><button type="button" class="delete-btn">&times;</button>`;
                previewContainer.appendChild(wrapper);
                wrapper.querySelector('.delete-btn').addEventListener('click', () => {
                    if (isExisting) {
                        if (deleteContainer) { const hiddenInput = document.createElement('input'); hiddenInput.type = 'hidden'; hiddenInput.name = config.deleteHiddenInputName; hiddenInput.value = id; deleteContainer.appendChild(hiddenInput); }
                        if (deleteSingleInput) { deleteSingleInput.value = '1'; }
                        existingFileCount--;
                    } else {
                        const updatedFiles = Array.from(newFiles.files).filter(f => f !== fileOrUrl);
                        const dt = new DataTransfer(); updatedFiles.forEach(f => dt.items.add(f)); newFiles = dt; input.files = newFiles.files;
                    }
                    wrapper.remove();
                });
                return wrapper;
            }
            if (config.existing) {
                const arr = Array.isArray(config.existing) ? config.existing : [config.existing];
                arr.forEach(img => { if(img) { createPreview(img.image_path || img, img.id, true); existingFileCount++; } });
            }
            input.addEventListener('change', (e) => {
                if (!config.isMultiple) { previewContainer.innerHTML = ''; if (deleteSingleInput) deleteSingleInput.value = '0'; newFiles = new DataTransfer(); existingFileCount = 0; }
                for (const file of e.target.files) { newFiles.items.add(file); createPreview(file); }
                input.files = newFiles.files; 
            });
        }
        setupImageManager({ inputId: 'logo_image_input', previewContainerId: 'logo-preview-container', deleteSingleInputId: 'delete_logo_input', existing: existingData.logo, isMultiple: false, deleteHiddenInputName: 'delete_logo' });
        setupImageManager({ inputId: 'cover_image_input', previewContainerId: 'cover-preview-container', deleteSingleInputId: 'delete_cover_input', existing: existingData.cover, isMultiple: false, deleteHiddenInputName: 'delete_cover' });
        setupImageManager({ inputId: 'gallery_images_new_input', previewContainerId: 'gallery-previews-container', deleteContainerId: 'delete-gallery-container', existing: existingData.gallery, isMultiple: true, maxFiles: 10, deleteHiddenInputName: 'delete_gallery_ids[]' });
        setupImageManager({ inputId: 'offer_images_new_input', previewContainerId: 'offers-previews-container', deleteContainerId: 'delete-offers-container', existing: existingData.offers, isMultiple: true, maxFiles: 5, deleteHiddenInputName: 'delete_offer_ids[]' });

        const latInput = document.getElementById('latitude'); const lonInput = document.getElementById('longitude');
        const savedLat = parseFloat(latInput.value) || 33.5138; const savedLon = parseFloat(lonInput.value) || 36.2765;
        window.map = L.map('map-container').setView([savedLat, savedLon], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(window.map);
        const marker = L.marker([savedLat, savedLon], { draggable: true }).addTo(window.map);
        marker.on('dragend', () => { latInput.value = marker.getLatLng().lat.toFixed(8); lonInput.value = marker.getLatLng().lng.toFixed(8); });
        window.map.addControl(new GeoSearch.GeoSearchControl({ provider: new GeoSearch.OpenStreetMapProvider(), style: 'bar', showMarker: false, autoClose: true }));
        window.map.on('geosearch/showlocation', (r) => { const latlng = { lat: r.location.y, lng: r.location.x }; marker.setLatLng(latlng); window.map.panTo(latlng); marker.fire('dragend'); });

        const menuManager = (() => {
            const addMenuItemBtn = document.getElementById('add-menu-item-btn');
            const menuItemsContainer = document.getElementById('menu-items-container');
            let newItemCounter = Date.now();
            const userCategories = new Set(); 

            const toolbarHTML = `<div class="items-toolbar"><div class="search-box-wrapper"><input type="text" id="menu-search" placeholder="ابحث في القائمة..."></div><button type="button" class="toggle-all-btn" id="toggle-menu-btn">فتح/إغلاق الكل</button></div>`;
            menuItemsContainer.parentNode.insertBefore(document.createRange().createContextualFragment(toolbarHTML), menuItemsContainer);
            document.getElementById('menu-search').addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('#menu-items-container .menu-item-entry').forEach(entry => {
                    const nameVal = entry.querySelector('.name-live-update').value.toLowerCase();
                    entry.style.display = nameVal.includes(term) ? (entry.classList.contains('collapsed') ? 'flex' : 'block') : 'none';
                });
            });
            let allExpanded = false;
            document.getElementById('toggle-menu-btn').addEventListener('click', () => {
                allExpanded = !allExpanded;
                document.querySelectorAll('#menu-items-container .menu-item-entry').forEach(entry => { if (allExpanded) entry.classList.remove('collapsed'); else entry.classList.add('collapsed'); });
            });

            function updateCategoryTags(entry) {
                const tagsContainer = entry.querySelector('.category-tags'); if (!tagsContainer) return;
                const mainCategory = categorySelect.value; const hiddenInput = entry.querySelector('.hidden-category-input'); const currentSelected = hiddenInput.value;
                const allTags = new Set([...(suggestedMenuCategories[mainCategory] || []), ...userCategories]);
                tagsContainer.innerHTML = '';
                allTags.forEach(category => {
                    const tag = document.createElement('div'); tag.className = 'category-tag'; tag.textContent = category; tag.dataset.category = category;
                    if (category === currentSelected) tag.classList.add('selected');
                    tag.onclick = (e) => { e.stopPropagation(); entry.querySelector('.hidden-category-input').value = category; entry.querySelector('.category-input').value = category; entry.querySelectorAll('.category-tag').forEach(t => t.classList.remove('selected')); tag.classList.add('selected'); };
                    tagsContainer.appendChild(tag);
                });
            }

            function createItemEntry(itemData = null) {
                const isExisting = itemData !== null && itemData.id !== undefined; const id = isExisting ? itemData.id : `new_${newItemCounter++}`;
                const itemName = safehtmlspecialchars(itemData?.item_name || ''); const itemPrice = safehtmlspecialchars(itemData?.price || '');
                const itemImg = (isExisting && itemData.image_path) ? `../${safehtmlspecialchars(itemData.image_path)}` : '../image/default_logo.webp';
                
                const entryDiv = document.createElement('div'); entryDiv.className = `menu-item-entry ${isExisting ? 'collapsed' : ''}`;
                entryDiv.innerHTML = `
                    <div class="collapsed-summary"><img src="${itemImg}" class="summary-img"><div class="summary-info"><span class="summary-name">${itemName || 'عنصر جديد'}</span><span class="summary-price">${itemPrice ? itemPrice + ' ل.س' : ''}</span></div><i class="fas fa-chevron-down expand-icon"></i></div>
                    <div class="remove-btn-wrapper"><button type="button" class="btn-danger"><i class="fas fa-trash-alt"></i></button></div>
                    <div class="menu-item-category-manager"><label>فئة العنصر</label><div class="category-input-group"><input type="text" class="category-input" value="${safehtmlspecialchars(itemData?.category_name || '')}"><button type="button" class="add-category-btn">إضافة</button></div><div class="category-tags"></div><input type="hidden" name="${isExisting ? `menu_items[${id}][category]` : `menu_items_new[${id}][category]`}" class="hidden-category-input" value="${safehtmlspecialchars(itemData?.category_name || '')}"></div>
                    <div class="menu-item-fields">
                        <div class="image-upload-wrapper"><img src="${(isExisting && itemData.image_path) ? `../${safehtmlspecialchars(itemData.image_path)}` : ''}" class="image-preview ${ (isExisting && itemData.image_path) ? 'has-image' : ''}"><input type="file" name="${isExisting ? `menu_items_images[${id}]` : `menu_items_new_images[${id}]`}" accept="image/*" class="menu-image-input"><i class="fas fa-camera upload-icon"></i></div>
                        <div class="fields-grid">
                            <div class="form-group"><label>الاسم</label><input type="text" name="${isExisting ? `menu_items[${id}][name]` : `menu_items_new[${id}][name]`}" class="name-live-update" value="${itemName}" required></div>
                            <div class="form-group"><label>السعر</label><input type="text" name="${isExisting ? `menu_items[${id}][price]` : `menu_items_new[${id}][price]`}" class="price-input price-live-update" value="${itemPrice}" required></div>
                            <div class="form-group full-width"><label>وصف</label><textarea name="${isExisting ? `menu_items[${id}][desc]` : `menu_items_new[${id}][desc]`}">${safehtmlspecialchars(itemData?.description || '')}</textarea></div>
                            ${isExisting ? `<input type="hidden" name="menu_items[${id}][id]" value="${id}">` : ''}
                        </div>
                    </div>
                    ${isExisting ? `<label class="delete-existing-item" style="display:none"><input type="checkbox" name="delete_menu_items[]" value="${id}"></label>` : ''}
                `;
                menuItemsContainer.appendChild(entryDiv);
                updateCategoryTags(entryDiv);
                entryDiv.addEventListener('click', function(e) { if(['INPUT','TEXTAREA','BUTTON'].includes(e.target.tagName) || e.target.closest('.category-tag') || e.target.closest('.image-upload-wrapper')) return; this.classList.toggle('collapsed'); });
                const nameIn = entryDiv.querySelector('.name-live-update'); const priceIn = entryDiv.querySelector('.price-live-update');
                nameIn.addEventListener('input', () => entryDiv.querySelector('.summary-name').textContent = nameIn.value || 'عنصر جديد');
                priceIn.addEventListener('input', () => entryDiv.querySelector('.summary-price').textContent = priceIn.value + ' ل.س');
                entryDiv.querySelector('.btn-danger').addEventListener('click', (e) => { e.stopPropagation(); if(isExisting) { if(confirm('حذف نهائي؟')) { entryDiv.querySelector('input[name="delete_menu_items[]"]').checked = true; entryDiv.style.display = 'none'; } } else { entryDiv.remove(); } });
                entryDiv.querySelector('.menu-image-input').addEventListener('change', (e) => { if (e.target.files[0]) { const reader = new FileReader(); reader.onload = ev => { const res = ev.target.result; entryDiv.querySelector('.image-preview').src = res; entryDiv.querySelector('.image-preview').classList.add('has-image'); entryDiv.querySelector('.summary-img').src = res; }; reader.readAsDataURL(e.target.files[0]); } });
                entryDiv.querySelector('.add-category-btn').addEventListener('click', (e) => { e.stopPropagation(); const val = entryDiv.querySelector('.category-input').value.trim(); if(val) { userCategories.add(val); entryDiv.querySelector('.hidden-category-input').value = val; document.querySelectorAll('#menu-items-container .menu-item-entry').forEach(updateCategoryTags); } });
            }
            addMenuItemBtn.addEventListener('click', () => { if (menuItemsContainer.querySelectorAll('.menu-item-entry:not(.collapsed)').length >= 20) { alert("⚠️ الحد الأقصى 20 عنصر."); return; } createItemEntry(null); });
            existingData.menuItems.forEach(item => { if(item.category_name) userCategories.add(item.category_name); createItemEntry(item); });
            if(existingData.menuItems.length === 0) createItemEntry(null);
        })();

        const dealManager = (() => {
            const addDealItemBtn = document.getElementById('add-deal-item-btn');
            const dealItemsContainer = document.getElementById('deal-items-container');
            let newDealItemCounter = Date.now(); 
            const userDealCategories = new Set();
            const toolbarHTML = `<div class="items-toolbar"><div class="search-box-wrapper"><input type="text" id="deal-search" placeholder="ابحث في العروض..."></div><button type="button" class="toggle-all-btn" id="toggle-deal-btn">فتح/إغلاق الكل</button></div>`;
            dealItemsContainer.parentNode.insertBefore(document.createRange().createContextualFragment(toolbarHTML), dealItemsContainer);
            document.getElementById('deal-search').addEventListener('input', (e) => { const term = e.target.value.toLowerCase(); document.querySelectorAll('#deal-items-container .menu-item-entry').forEach(entry => { const nameVal = entry.querySelector('.name-live-update').value.toLowerCase(); entry.style.display = nameVal.includes(term) ? (entry.classList.contains('collapsed') ? 'flex' : 'block') : 'none'; }); });
            document.getElementById('toggle-deal-btn').addEventListener('click', () => { const allExpanded = !document.querySelectorAll('#deal-items-container .menu-item-entry.collapsed').length; document.querySelectorAll('#deal-items-container .menu-item-entry').forEach(entry => { if (allExpanded) entry.classList.add('collapsed'); else entry.classList.remove('collapsed'); }); });

            function updateDealCategoryTags(entry) {
                const tagsContainer = entry.querySelector('.category-tags'); if (!tagsContainer) return;
                const mainCategory = categorySelect.value; const hiddenInput = entry.querySelector('.hidden-category-input'); const currentSelected = hiddenInput.value;
                const allTags = new Set([...(suggestedDealCategories[mainCategory] || []), ...userDealCategories]);
                tagsContainer.innerHTML = '';
                allTags.forEach(category => {
                    const tag = document.createElement('div'); tag.className = 'category-tag'; tag.textContent = category; tag.dataset.category = category;
                    if (category === currentSelected) tag.classList.add('selected');
                    tag.onclick = (e) => { e.stopPropagation(); entry.querySelector('.hidden-category-input').value = category; entry.querySelector('.category-input').value = category; entry.querySelectorAll('.category-tag').forEach(t => t.classList.remove('selected')); tag.classList.add('selected'); };
                    tagsContainer.appendChild(tag);
                });
            }

            function createDealEntry(dealData = null) {
                const isExisting = dealData !== null && dealData.id !== undefined; const id = isExisting ? dealData.id : `new_${newDealItemCounter++}`;
                const dealName = safehtmlspecialchars(dealData?.deal_name || ''); const newPrice = safehtmlspecialchars(dealData?.new_price || '');
                const dealImg = (isExisting && dealData.image_path) ? `../${safehtmlspecialchars(dealData.image_path)}` : '../image/default_logo.webp';
                
                const entryDiv = document.createElement('div'); entryDiv.className = `menu-item-entry deal-entry ${isExisting ? 'collapsed' : ''}`;
                entryDiv.innerHTML = `
                    <div class="collapsed-summary"><img src="${dealImg}" class="summary-img"><div class="summary-info"><span class="summary-name">${dealName || 'عرض جديد'}</span><span class="summary-price">${newPrice ? newPrice : '0'}</span></div><i class="fas fa-chevron-down expand-icon"></i></div>
                    <div class="remove-btn-wrapper"><button type="button" class="btn-danger"><i class="fas fa-trash-alt"></i></button></div>
                    <div class="menu-item-category-manager"><label>فئة العرض</label><div class="category-input-group"><input type="text" class="category-input" value="${safehtmlspecialchars(dealData?.category_name || '')}"><button type="button" class="add-category-btn">إضافة</button></div><div class="category-tags"></div><input type="hidden" name="${isExisting ? `deals[${id}][category_name]` : `deals_new[${id}][category_name]`}" class="hidden-category-input" value="${safehtmlspecialchars(dealData?.category_name || '')}"></div>
                    <div class="menu-item-fields deal-fields">
                        <div class="image-upload-wrapper"><img src="${(isExisting && dealData.image_path) ? `../${safehtmlspecialchars(dealData.image_path)}` : ''}" class="image-preview ${ (isExisting && dealData.image_path) ? 'has-image' : ''}"><input type="file" name="${isExisting ? `deals_images[${id}]` : `deals_new_images[${id}]`}" accept="image/*" class="menu-image-input"><i class="fas fa-camera upload-icon"></i></div>
                        <div class="fields-grid">
                            <div class="form-group"><label>اسم العرض</label><input type="text" name="${isExisting ? `deals[${id}][deal_name]` : `deals_new[${id}][deal_name]`}" class="name-live-update" value="${dealName}" required></div>
                            <div class="form-group"><label>السعر الجديد</label><input type="text" name="${isExisting ? `deals[${id}][new_price]` : `deals_new[${id}][new_price]`}" class="price-input price-live-update" value="${newPrice}" required></div>
                            <div class="form-group"><label>السعر القديم</label><input type="text" name="${isExisting ? `deals[${id}][old_price]` : `deals_new[${id}][old_price]`}" class="price-input" value="${dealData?.old_price || ''}"></div>
                            <div class="form-group full-width"><label>وصف</label><textarea name="${isExisting ? `deals[${id}][description]` : `deals_new[${id}][description]`}">${safehtmlspecialchars(dealData?.description || '')}</textarea></div>
                            ${isExisting ? `<input type="hidden" name="deals[${id}][id]" value="${id}">` : ''}
                        </div>
                    </div>
                    ${isExisting ? `<label class="delete-existing-item" style="display:none"><input type="checkbox" name="delete_deals[]" value="${id}"></label>` : ''}
                `;
                dealItemsContainer.appendChild(entryDiv);
                updateDealCategoryTags(entryDiv);

                entryDiv.addEventListener('click', function(e) {
                    if(['INPUT','TEXTAREA','BUTTON'].includes(e.target.tagName) || e.target.closest('.category-tag') || e.target.closest('.image-upload-wrapper')) return;
                    this.classList.toggle('collapsed');
                });
                const nameIn = entryDiv.querySelector('.name-live-update'); const priceIn = entryDiv.querySelector('.price-live-update');
                nameIn.addEventListener('input', () => entryDiv.querySelector('.summary-name').textContent = nameIn.value || 'عرض جديد');
                priceIn.addEventListener('input', () => entryDiv.querySelector('.summary-price').textContent = priceIn.value);
                entryDiv.querySelector('.btn-danger').addEventListener('click', (e) => { e.stopPropagation(); if(isExisting) { if(confirm('حذف نهائي؟')) { entryDiv.querySelector('input[name="delete_deals[]"]').checked = true; entryDiv.style.display = 'none'; } } else { entryDiv.remove(); } });
                entryDiv.querySelector('.menu-image-input').addEventListener('change', (e) => { if (e.target.files[0]) { const reader = new FileReader(); reader.onload = ev => { const res = ev.target.result; entryDiv.querySelector('.image-preview').src = res; entryDiv.querySelector('.image-preview').classList.add('has-image'); entryDiv.querySelector('.summary-img').src = res; }; reader.readAsDataURL(e.target.files[0]); } });
                entryDiv.querySelector('.add-category-btn').addEventListener('click', (e) => { e.stopPropagation(); const val = entryDiv.querySelector('.category-input').value.trim(); if(val) { userDealCategories.add(val); entryDiv.querySelector('.hidden-category-input').value = val; document.querySelectorAll('#deal-items-container .menu-item-entry').forEach(updateDealCategoryTags); } });
            }
            addDealItemBtn.addEventListener('click', () => { if (dealItemsContainer.querySelectorAll('.menu-item-entry:not(.collapsed)').length >= 20) { alert("⚠️ الحد الأقصى 20 عرض."); return; } createDealEntry(null); });
            existingData.deals.forEach(deal => { if(deal.category_name) userDealCategories.add(deal.category_name); createDealEntry(deal); });
            if(existingData.deals.length === 0) createDealEntry(null);
        })();
        window.updateHiddenInput = function(day) { const row = document.getElementById('row-' + day); const statusCb = row.querySelector('.day-status-cb'); const allDayCb = row.querySelector('.all-day-cb'); const startTime = row.querySelector('.start-time').value; const endTime = row.querySelector('.end-time').value; const hiddenInput = document.getElementById('input-' + day); if (!statusCb.checked) hiddenInput.value = 'closed'; else if (allDayCb.checked) hiddenInput.value = '24 hours'; else hiddenInput.value = `${startTime} - ${endTime}`; };
        window.toggleHours = function(day) { const row = document.getElementById('row-' + day); const inputsGroup = document.getElementById('group-' + day); if (row.querySelector('.day-status-cb').checked) { inputsGroup.classList.remove('disabled'); updateHiddenInput(day); } else { inputsGroup.classList.add('disabled'); document.getElementById('input-' + day).value = 'closed'; } };
        window.toggle24Hours = function(day) { const row = document.getElementById('row-' + day); const startInput = row.querySelector('.start-time'); const endInput = row.querySelector('.end-time'); if (row.querySelector('.all-day-cb').checked) { startInput.disabled = true; endInput.disabled = true; startInput.value = '00:00'; endInput.value = '23:59'; } else { startInput.disabled = false; endInput.disabled = false; startInput.value = '09:00'; endInput.value = '22:00'; } updateHiddenInput(day); };
        const days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة']; days.forEach(day => updateHiddenInput(day));
    });
</script>

<?php include 'footer.php'; ?>