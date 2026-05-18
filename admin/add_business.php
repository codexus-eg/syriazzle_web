<?php
$page_title = 'إضافة نشاط تجاري (إداري)';
include 'header.php';

// --- 1. حارس البوابة ---
if (!hasPermission('add_business')) {
    echo "<div class='container' style='padding:50px; text-align:center; color:red;'><h2>عذراً، ليس لديك صلاحية للوصول.</h2></div>";
    include 'footer.php';
    exit;
}

// --- 2. جلب البيانات الضرورية ---
try {
    // المحافظات
    $governorates = $pdo->query("SELECT id, name FROM governorates ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // المستخدمين (لربط المتجر بصاحبه)
    $users = $pdo->query("SELECT id, username, phone FROM users WHERE is_verified = 1 AND deleted_at IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $governorates = []; $users = [];
}

// --- 3. إعدادات الحقول الديناميكية ---
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


// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!-- استدعاء المكتبات (محلية) -->
<link rel="stylesheet" href="../css/lib/leaflet.css" />
<link rel="stylesheet" href="../css/lib/geosearch.css" />
<link rel="stylesheet" href="../css/libs/intlTelInput.css"> <!-- مكتبة الهواتف -->
<link rel="stylesheet" href="../css/add_business_form.css"> <!-- ملف التصميم الموحد -->

<script src="../js/lib/leaflet.js"></script>
<script src="../js/lib/geosearch.js"></script>

<style>
    /* تنسيقات إضافية خاصة بصفحة الأدمن والطي والبحث */
    
    /* 1. قسم اختيار المستخدم */
    .user-select-wrapper { display: flex; gap: 10px; align-items: flex-end; }
    .user-select-wrapper select { flex-grow: 1; }
    .btn-create-user {
        background-color: #0d6efd; color: #fff; padding: 12px 18px; border: none; border-radius: 6px;
        cursor: pointer; font-weight: bold; white-space: nowrap; transition: 0.2s; display: flex; align-items: center; gap: 5px;
    }
    .btn-create-user:hover { background-color: #0b5ed7; }

    /* 2. شريط البحث والطي (للمنتجات) */
    .items-toolbar {
        background: #fff; border: 1px solid #e0e0e0;
        padding: 15px; border-radius: 12px; margin-bottom: 20px;
        display: flex; gap: 10px; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .search-box-wrapper { flex-grow: 1; position: relative; }
    .search-box-wrapper input {
        width: 100%; padding: 10px 15px 10px 35px; border-radius: 8px;
        border: 1px solid #ced4da; outline: none; box-sizing: border-box;
    }
    .search-box-wrapper::after {
        content: '\f002'; font-family: "Font Awesome 5 Free"; font-weight: 900;
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #adb5bd;
    }
    .toggle-all-btn {
        background: #f8f9fa; border: 1px solid #ced4da; padding: 10px 15px;
        border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 13px; color: #555; white-space: nowrap;
    }

    /* 3. حالة البطاقة "المطوية" (Collapsed) */
    .menu-item-entry.collapsed {
        padding: 10px 15px; cursor: pointer;
        display: flex; align-items: center; gap: 15px;
        background: #fff; border: 1px solid #eee; border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 10px;
        min-height: 70px;
    }
    /* إخفاء العناصر عند الطي */
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

    /* 4. تنسيق المودال (إضافة مستخدم) */
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.6); z-index: 2000; display: none;
        justify-content: center; align-items: center; backdrop-filter: blur(3px);
    }
    .modal-content {
        background: #fff; width: 95%; max-width: 450px; padding: 30px;
        border-radius: 12px; position: relative; box-shadow: 0 15px 40px rgba(0,0,0,0.2);
    }
    .modal-close { position: absolute; top: 20px; left: 20px; font-size: 24px; cursor: pointer; color: #999; }
    .iti { width: 100%; }
    
    /* 5. الرسوم المتحركة */
    .tab-pane { display: none; }
    .tab-pane.active { display: block; animation: fadeInUp 0.4s ease-out; }
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="form-container" style="padding-top: 20px;">
    <form id="add-business-form" action="../php/save_business_user.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="is_admin_action" value="1">
        
        <!-- شريط الخطوات (Wizard) -->
        <div class="form-wizard-nav">
            <div class="nav-tab active" data-step="0">1. المعلومات</div>
            <div class="nav-tab" data-step="1">2. التفاصيل</div>
            <div class="nav-tab" data-step="2">3. الصور</div>
            <div class="nav-tab" data-step="3">4. سلايدر العروض</div>
            <div class="nav-tab" data-step="4">5. العروض</div>
            <div class="nav-tab" data-step="5">6. المنيو</div>
            <div class="nav-tab" data-step="6">7. الدوام</div>
        </div>

        <!-- الخطوة 1: المعلومات الأساسية -->
        <div class="tab-pane active" id="step-0">
            <div class="form-section">
                <h2><i class="fas fa-store"></i> المعلومات الأساسية والموقع</h2>
                <div class="form-grid">
                    
                    <!-- حقل اختيار المستخدم (للأدمن) -->
                    <div class="form-group full-width">
                        <label>صاحب المتجر (المستخدم) <span style="color:red">*</span></label>
                        <div class="user-select-wrapper">
                            <select name="user_id" id="user_id_select" required>
                                <option value="" disabled selected>-- اختر مستخدم --</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']) . " (" . htmlspecialchars($u['phone']) . ")"; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-create-user" onclick="openUserModal()"><i class="fas fa-user-plus"></i> جديد</button>
                        </div>
                    </div>

                    <div class="form-group full-width"><label>اسم النشاط التجاري <span style="color:red">*</span></label><input type="text" id="name" name="name" required></div>
                    
                    <div class="form-group">
                        <label>الفئة الرئيسية <span style="color:red">*</span></label>
                        <select id="category" name="category" required>
                            <option value="" disabled selected>-- اختر الفئة --</option>
                            <?php foreach (array_keys($dynamic_fields_config) as $category_name): ?>
                                <option value="<?php echo htmlspecialchars($category_name); ?>"><?php echo htmlspecialchars($category_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>المحافظة <span style="color:red">*</span></label>
                        <select id="governorate_id" name="governorate_id" required <?php if (!hasPermission('super_admin_access_all')) echo 'disabled'; ?>>
                            <?php if (hasPermission('super_admin_access_all')): ?>
                                <option value="" disabled selected>-- اختر محافظة --</option>
                                <?php foreach ($governorates as $gov): ?>
                                    <option value="<?php echo $gov['id']; ?>"><?php echo htmlspecialchars($gov['name']); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($governorates as $gov): if ($gov['id'] == $admin_governorate_id): ?>
                                    <option value="<?php echo $gov['id']; ?>" selected><?php echo htmlspecialchars($gov['name']); ?></option>
                                <?php endif; endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (!hasPermission('super_admin_access_all')): ?>
                            <input type="hidden" name="governorate_id" value="<?php echo $admin_governorate_id; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group"><label>المدينة <span style="color:red">*</span></label><input type="text" id="city" name="city" required></div>
                    <div class="form-group">
                        <label>عملة المتجر <span style="color:red">*</span></label>
                        <select name="currency" required>
                            <option value="SYP" selected>ليرة سورية (SYP)</option>
                            <option value="USD">دولار أمريكي (USD)</option>
                        </select>
                        <small style="color: #6c757d; font-size: 12px;">سيتم اعتماد هذه العملة لجميع منتجات المتجر.</small>
                    </div>
                    <div class="form-group full-width"><label>العنوان التفصيلي</label><input type="text" id="address" name="address"></div>
                     <div class="form-group"><label>هاتف المتجر</label><input type="text" name="phone"></div> 
                     <div class="form-group"><label>واتساب</label><input type="text" name="whatsapp"></div> 
                     <div class="form-group"><label>الموقع الإلكتروني</label><input type="url" name="website_url"></div> 
                     <div class="form-group"><label>رابط فيسبوك</label><input type="url" name="facebook_url"></div> 
                     <div class="form-group"><label>رابط إنستغرام</label><input type="url" name="instagram_url"></div> 
                    <div class="form-group"><label>رابط فيديو ترويجي</label><input type="url" id="video_url" name="video_url" placeholder="https://..."></div>
                </div>

                <div class="form-group full-width" id="map-section"><label>الموقع على الخريطة</label><div id="map-container"></div>
                    <input type="hidden" id="latitude" name="latitude"><input type="hidden" id="longitude" name="longitude">
                </div>
                <div class="form-group full-width"><label>وصف قصير</label><textarea id="description" name="description"></textarea></div>
            </div>
        </div>

        <!-- الخطوة 2: التفاصيل -->
        <div class="tab-pane" id="step-1">
            <p id="details-placeholder-message" style="text-align: center; color: #6c757d; padding: 40px; border: 2px dashed #eee; border-radius: 8px;">اختر الفئة أولاً لعرض التفاصيل.</p>
            <div id="dynamic-fields-container">
                <?php foreach ($dynamic_fields_config as $category_name => $fields): ?>
                    <div id="wrapper-<?php echo str_replace(' ', '_', $category_name); ?>" class="dynamic-fields-wrapper form-section">
                        <h2><i class="fas fa-info-circle"></i> تفاصيل <?php echo htmlspecialchars($category_name); ?></h2>
                        <div class="form-grid">
                            <?php foreach ($fields as $field): ?>
                                <div class="form-group">
                                    <label><?php echo htmlspecialchars($field['name']); ?></label>
                                    <?php if ($field['type'] === 'select'): ?>
                                        <select name="details[<?php echo htmlspecialchars($field['name']); ?>]"><option value="">-- اختر --</option>
                                            <?php foreach ($field['options'] as $option): ?><option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option><?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="<?php echo $field['type']; ?>" name="details[<?php echo htmlspecialchars($field['name']); ?>]" placeholder="<?php echo $field['placeholder'] ?? ''; ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- الخطوة 3: الصور -->
        <div class="tab-pane" id="step-2">
            <div class="form-section">
                <h2><i class="fas fa-image"></i> الصور الرئيسية</h2>
                <div class="form-grid">
                    <div class="form-group"><label>الشعار</label>
                        <div class="image-uploader-group">
                            <div class="image-uploader-box" id="logo-uploader-box">
                                <input type="file" id="logo_image" name="logo_image" accept="image/*">
                                <div class="upload-content"><i class="fas fa-portrait upload-icon"></i><div class="upload-text">اختر الشعار</div></div>
                            </div>
                            <div class="image-preview-container" id="logo-preview-container"></div>
                        </div>
                    </div>
                    <div class="form-group"><label>الغلاف</label>
                        <div class="image-uploader-group">
                            <div class="image-uploader-box" id="cover-uploader-box">
                                <input type="file" id="cover_image" name="cover_image" accept="image/*">
                                <div class="upload-content"><i class="fas fa-image upload-icon"></i><div class="upload-text">اختر الغلاف</div></div>
                            </div>
                            <div class="image-preview-container" id="cover-preview-container"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-section">
                <h2><i class="fas fa-images"></i> معرض الصور</h2>
                <div class="image-uploader-group">
                    <div class="image-uploader-box" id="gallery-uploader-box">
                        <input type="file" id="gallery_images" name="gallery_images[]" multiple accept="image/*">
                        <div class="upload-content"><i class="fas fa-photo-video upload-icon"></i><div class="upload-text">إضافة صور (متعدد)</div></div>
                    </div>
                    <div class="image-preview-container" id="gallery-previews-container"></div>
                </div>
            </div>
        </div>

        <!-- الخطوة 4: سلايدر العروض -->
        <div class="tab-pane" id="step-3">
            <div class="form-section">
                <h2><i class="fas fa-images"></i> سلايدر العروض</h2>
                <p style="color:#666;">(5 صور كحد أقصى)</p>
                <div class="image-uploader-group">
                    <div class="image-uploader-box" id="offers-uploader-box">
                        <input type="file" id="offer_images" name="offer_images[]" multiple accept="image/*">
                        <div class="upload-content"><i class="fas fa-photo-video upload-icon"></i><div class="upload-text">اختر الصور</div></div>
                    </div>
                    <div class="image-preview-container" id="offers-previews-container"></div>
                </div>
            </div>
        </div>

        <!-- الخطوة 5: العروض (Deals) -->
        <div class="tab-pane" id="step-4">
            <div class="form-section">
                <h2><i class="fas fa-tags"></i> العروض والصفقات</h2>
                <div id="deal-items-container"></div>
                <button type="button" id="add-deal-item-btn" class="btn-add-item"><i class="fas fa-plus"></i> إضافة عرض جديد</button>
            </div>
        </div>

        <!-- الخطوة 6: المنيو (Menu) -->
        <div class="tab-pane" id="step-5">
            <div class="form-section">
                <h2><i class="fas fa-clipboard-list"></i> قائمة الأسعار</h2>
                <div id="menu-items-container"></div>
                <button type="button" id="add-menu-item-btn"><i class="fas fa-plus"></i> إضافة عنصر جديد</button>
            </div>
        </div>

        <!-- الخطوة 7: الدوام -->
        <div class="tab-pane" id="step-6">
            <div class="form-section">
                <h2><i class="fas fa-clock"></i> ساعات العمل <span style="color:red; font-size:0.8em">(مطلوب)</span></h2>
                <div class="form-grid-hours">
                    <?php 
                    $days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة']; 
                    foreach($days as $day): 
                    ?>
                        <div class="hours-row" id="row-<?php echo $day; ?>">
                            <div class="day-label"><?php echo $day; ?></div>
                            <div class="status-toggle">
                                <label><input type="checkbox" class="day-status-cb" checked onchange="toggleHours('<?php echo $day; ?>')"><span>مفتوح</span></label>
                            </div>
                            <div class="time-inputs-group" id="group-<?php echo $day; ?>">
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-size: 11px; color: #888;">من</span>
                                    <input type="time" class="time-input start-time" value="09:00" onchange="updateHiddenInput('<?php echo $day; ?>')">
                                </div>
                                <span style="font-weight: bold; color: #888;">-</span>
                                <div style="display: flex; flex-direction: column;">
                                    <span style="font-size: 11px; color: #888;">إلى</span>
                                    <input type="time" class="time-input end-time" value="22:00" onchange="updateHiddenInput('<?php echo $day; ?>')">
                                </div>
                                <label style="font-size: 12px; margin-right: 10px; display: flex; align-items: center; gap: 4px;">
                                    <input type="checkbox" class="all-day-cb" onchange="toggle24Hours('<?php echo $day; ?>')"> 24 ساعة
                                </label>
                            </div>
                            <input type="hidden" name="opening_hours[<?php echo $day; ?>]" id="input-<?php echo $day; ?>" value="09:00 - 22:00">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- أزرار التنقل -->
        <div class="form-navigation">
            <button type="button" class="btn-nav btn-prev" id="prevBtn" onclick="nextPrev(-1)">السابق</button>
            <button type="button" class="btn-nav btn-next" id="nextBtn" onclick="nextPrev(1)">التالي</button>
            <button type="button" class="btn-nav btn-submit" id="submitBtn" onclick="nextPrev(1)">إنشاء المتجر</button>
        </div>
    </form>
</div>

<!-- Modal إضافة مستخدم سريع -->
<div class="modal-overlay" id="quickUserModal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeUserModal()">&times;</span>
        <h2 style="margin-top:0; text-align:center; margin-bottom:20px; color:#333;">إضافة مستخدم جديد</h2>
        <form id="quickUserForm">
            <div class="form-group">
                <label>اسم المستخدم الكامل</label>
                <input type="text" name="username" required placeholder="مثال: محمد أحمد">
            </div>
            <div class="form-group">
                <label>رقم الهاتف</label>
                <input type="tel" id="modal_phone" class="form-control" required style="width:100%;">
                <input type="hidden" name="phone" id="full_phone_modal">
            </div>
            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" required placeholder="******" minlength="8">
            </div>
            <div style="margin-top:20px;">
                <button type="submit" class="btn-create-user" style="width:100%; justify-content:center;">إنشاء الحساب واختياره</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/libs/intlTelInput.min.js"></script>

<script>
    // --- 1. إعدادات المودال وإنشاء المستخدم ---
    const userModal = document.getElementById('quickUserModal');
    const userSelect = document.getElementById('user_id_select');
    const phoneInput = document.querySelector("#modal_phone");
    let iti;

    if (phoneInput) {
        iti = window.intlTelInput(phoneInput, {
            initialCountry: "sy",
            preferredCountries: ["sy", "ae", "sa"],
            utilsScript: "../js/libs/utils.js",
            separateDialCode: true,
        });
    }

    function openUserModal() { userModal.style.display = 'flex'; }
    function closeUserModal() { userModal.style.display = 'none'; }
    window.onclick = function(e) { if(e.target == userModal) closeUserModal(); }

    document.getElementById('quickUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fullPhone = iti.getNumber();
        document.getElementById('full_phone_modal').value = fullPhone;

        const formData = new FormData(this);

        fetch('save_new_user_ajax.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const option = new Option(data.username + " (" + fullPhone + ")", data.id, true, true);
                userSelect.add(option);
                alert("تم إنشاء المستخدم واختياره بنجاح!");
                closeUserModal();
                this.reset();
            } else {
                alert("خطأ: " + data.message);
            }
        })
        .catch(error => alert("حدث خطأ في الاتصال."));
    });

    // --- 2. منطق المعالج (Wizard) والتحقق الذكي ---
    let currentTab = 0;
    showTab(currentTab);

    function showTab(n) {
        const x = document.getElementsByClassName("tab-pane");
        const navTabs = document.getElementsByClassName("nav-tab");
        for (let i = 0; i < x.length; i++) {
            x[i].style.display = "none";
            x[i].classList.remove("active");
            navTabs[i].classList.remove("active");
        }
        x[n].style.display = "block";
        setTimeout(() => x[n].classList.add("active"), 10);
        navTabs[n].classList.add("active");

        document.getElementById("prevBtn").style.display = n == 0 ? "none" : "inline";
        if (n == (x.length - 1)) {
            document.getElementById("nextBtn").style.display = "none";
            document.getElementById("submitBtn").style.display = "inline";
        } else {
            document.getElementById("nextBtn").style.display = "inline";
            document.getElementById("nextBtn").innerHTML = "التالي";
            document.getElementById("submitBtn").style.display = "none";
        }
        if (n == 0 && typeof window.map !== 'undefined') setTimeout(() => { window.map.invalidateSize(); }, 200);
        window.scrollTo(0, 0);
    }

    function nextPrev(n) {
        const x = document.getElementsByClassName("tab-pane");
        if (n == 1 && !validateForm()) return false;
        const nextStep = currentTab + n;
        if (nextStep >= x.length) {
            document.getElementById("add-business-form").submit();
            document.getElementById("submitBtn").disabled = true;
            document.getElementById("submitBtn").innerHTML = "جارٍ الإنشاء...";
            return false;
        }
        currentTab = nextStep;
        showTab(currentTab);
    }

    // 🔥 دالة التحقق الذكية (تفتح البطاقات المطوية)
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
                    input.style.borderColor = "red";
                    input.reportValidity();
                    valid = false;
                    return false;
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
        categorySelect.addEventListener('change', () => {
            if (detailsPlaceholder) detailsPlaceholder.style.display = 'none';
            dynamicFieldsContainer.querySelectorAll('.dynamic-fields-wrapper').forEach(w => w.style.display = 'none');
            const selectedWrapper = document.getElementById('wrapper-' + categorySelect.value.replace(/ /g, '_'));
            if (selectedWrapper) selectedWrapper.style.display = 'block';
        });

        // رفع الصور
        function setupImageUploader(inputId, previewContainerId, isMultiple = false, maxFiles = 1) {
            const input = document.getElementById(inputId);
            const previewContainer = document.getElementById(previewContainerId);
            let filesDataTransfer = new DataTransfer();
            input.addEventListener('change', () => {
                if (!isMultiple) filesDataTransfer = new DataTransfer();
                for (const file of input.files) {
                    if (isMultiple && filesDataTransfer.files.length >= maxFiles) { alert(`الحد الأقصى ${maxFiles} صور.`); break; }
                    filesDataTransfer.items.add(file);
                }
                input.files = filesDataTransfer.files;
                renderPreviews();
            });
            function renderPreviews() {
                previewContainer.innerHTML = '';
                Array.from(filesDataTransfer.files).forEach((file, index) => {
                    const wrapper = document.createElement('div'); wrapper.className = 'image-preview-item';
                    const img = document.createElement('img'); img.src = URL.createObjectURL(file);
                    const del = document.createElement('button'); del.className = 'delete-btn'; del.innerHTML = '&times;';
                    del.onclick = (e) => { e.preventDefault(); filesDataTransfer.items.remove(index); input.files = filesDataTransfer.files; renderPreviews(); };
                    wrapper.appendChild(img); wrapper.appendChild(del); previewContainer.appendChild(wrapper);
                });
            }
        }
        setupImageUploader('logo_image', 'logo-preview-container', false, 1);
        setupImageUploader('cover_image', 'cover-preview-container', false, 1);
        setupImageUploader('gallery_images', 'gallery-previews-container', true, 10);
        setupImageUploader('offer_images', 'offers-previews-container', true, 5);

        // الخريطة
        const latInput = document.getElementById('latitude'); const lonInput = document.getElementById('longitude');
        const defaultPosition = [33.5138, 36.2765];
        window.map = L.map('map-container').setView(defaultPosition, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(window.map);
        const marker = L.marker(defaultPosition, { draggable: true }).addTo(window.map);
        function updateInputs(latlng) { latInput.value = latlng.lat.toFixed(8); lonInput.value = latlng.lng.toFixed(8); }
        marker.on('dragend', () => updateInputs(marker.getLatLng())); updateInputs(marker.getLatLng());
        window.map.addControl(new GeoSearch.GeoSearchControl({ provider: new GeoSearch.OpenStreetMapProvider(), style: 'bar', showMarker: false, autoClose: true }));
        window.map.on('geosearch/showlocation', (r) => { const latlng = { lat: r.location.y, lng: r.location.x }; marker.setLatLng(latlng); window.map.panTo(latlng); updateInputs(latlng); });

        // --- المنيو (مع البحث والطي) ---
        const addMenuItemBtn = document.getElementById('add-menu-item-btn');
        const menuItemsContainer = document.getElementById('menu-items-container');
        let menuItemCounter = 0;
        const userCreatedCategories = new Set();
        const suggestedCategories = <?php echo json_encode($suggested_menu_categories); ?>;

        const toolbarHTML = `
            <div class="items-toolbar">
                <div class="search-box-wrapper"><input type="text" id="menu-search" placeholder="ابحث في القائمة..."></div>
                <button type="button" class="toggle-all-btn" id="toggle-menu-btn">فتح/إغلاق الكل</button>
            </div>
        `;
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
            document.querySelectorAll('#menu-items-container .menu-item-entry').forEach(entry => {
                if (allExpanded) entry.classList.remove('collapsed'); else entry.classList.add('collapsed');
            });
        });

        function updateCategoryTags(entry) {
            const tagsContainer = entry.querySelector('.category-tags');
            if (!tagsContainer) return;
            const mainCategory = categorySelect.value;
            const hiddenInput = entry.querySelector('.hidden-category-input');
            const currentSelected = hiddenInput.value;
            const allTags = new Set([...(suggestedCategories[mainCategory] || []), ...userCreatedCategories]);
            tagsContainer.innerHTML = '';
            allTags.forEach(category => {
                const tag = document.createElement('div'); tag.className = 'category-tag'; tag.textContent = category; tag.dataset.category = category;
                if (category === currentSelected) tag.classList.add('selected');
                tag.onclick = (e) => {
                    e.stopPropagation(); entry.querySelector('.hidden-category-input').value = category; entry.querySelector('.category-input').value = category;
                    entry.querySelectorAll('.category-tag').forEach(t => t.classList.remove('selected')); tag.classList.add('selected');
                };
                tagsContainer.appendChild(tag);
            });
        }

        function addMenuItemEntry() {
            if (menuItemsContainer.children.length >= 20) { alert("⚠️ الحد الأقصى 20 عنصر في هذه المرحلة."); return; }
            const entryDiv = document.createElement('div');
            entryDiv.className = 'menu-item-entry'; 
            const idx = menuItemCounter++;
            
            entryDiv.innerHTML = `
                <div class="collapsed-summary">
                    <img src="../image/default_logo.webp" class="summary-img" alt="">
                    <div class="summary-info"><span class="summary-name">عنصر جديد</span><span class="summary-price">0 ل.س</span></div>
                    <i class="fas fa-chevron-down expand-icon"></i>
                </div>
                <div class="remove-btn-wrapper"><button type="button" class="btn-danger"><i class="fas fa-trash-alt"></i></button></div>
                <div class="menu-item-category-manager">
                    <label>فئة العنصر</label>
                    <div class="category-input-group"><input type="text" class="category-input" placeholder="اكتب أو اختر..."><button type="button" class="add-category-btn">إضافة</button></div>
                    <div class="category-tags"></div>
                    <input type="hidden" name="menu_items[${idx}][category]" class="hidden-category-input">
                </div>
                <div class="menu-item-fields">
                    <div class="image-upload-wrapper">
                        <img src="" class="image-preview">
                        <input type="file" name="menu_items[${idx}][image]" accept="image/*" class="menu-image-input">
                        <i class="fas fa-camera upload-icon"></i>
                    </div>
                    <div class="fields-grid">
                        <div class="form-group"><label>الاسم*</label><input type="text" name="menu_items[${idx}][name]" class="form-control name-live-update" required></div>
                        <div class="form-group"><label>السعر*</label><input type="number" name="menu_items[${idx}][price]" placeholder="بالليرة السورية" class="form-control price-input price-live-update" required></div>
                        <div class="form-group full-width"><label>وصف</label><textarea name="menu_items[${idx}][desc]" class="form-control"></textarea></div>
                    </div>
                </div>
            `;
            menuItemsContainer.appendChild(entryDiv);
            updateCategoryTags(entryDiv);

            entryDiv.addEventListener('click', function(e) {
                if(['INPUT','TEXTAREA','BUTTON'].includes(e.target.tagName) || e.target.closest('.category-tag') || e.target.closest('.image-upload-wrapper')) return;
                this.classList.toggle('collapsed');
            });
            const nameIn = entryDiv.querySelector('.name-live-update');
            const priceIn = entryDiv.querySelector('.price-live-update');
            nameIn.addEventListener('input', () => entryDiv.querySelector('.summary-name').textContent = nameIn.value || 'عنصر جديد');
            priceIn.addEventListener('input', () => entryDiv.querySelector('.summary-price').textContent = priceIn.value + ' ل.س');
            
            entryDiv.querySelector('.btn-danger').addEventListener('click', (e) => { e.stopPropagation(); entryDiv.remove(); });
            entryDiv.querySelector('.menu-image-input').addEventListener('change', (e) => {
                if (e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = ev => { entryDiv.querySelector('.image-preview').src = ev.target.result; entryDiv.querySelector('.image-preview').classList.add('has-image'); entryDiv.querySelector('.summary-img').src = ev.target.result; };
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
            entryDiv.querySelector('.add-category-btn').addEventListener('click', (e) => {
                e.stopPropagation(); const val = entryDiv.querySelector('.category-input').value.trim();
                if(val) { userCreatedCategories.add(val); entryDiv.querySelector('.hidden-category-input').value = val; document.querySelectorAll('#menu-items-container .menu-item-entry').forEach(updateCategoryTags); }
            });
        }
        addMenuItemBtn.addEventListener('click', addMenuItemEntry);
        categorySelect.addEventListener('change', () => document.querySelectorAll('#menu-items-container .menu-item-entry').forEach(updateCategoryTags));
        addMenuItemEntry(); // واحد افتراضي

        // --- العروض (بنفس النظام) ---
        const addDealItemBtn = document.getElementById('add-deal-item-btn');
        const dealItemsContainer = document.getElementById('deal-items-container');
        let dealItemCounter = 0;
        const userCreatedDealCategories = new Set();
        const suggestedDealCategories = <?php echo json_encode($suggested_deal_categories); ?>;

        function updateDealCategoryTags(entry) {
            const tagsContainer = entry.querySelector('.category-tags');
            if (!tagsContainer) return;
            const mainCategory = document.getElementById('category').value;
            const hiddenInput = entry.querySelector('.hidden-category-input');
            const currentSelected = hiddenInput.value;
            const allTags = new Set([...(suggestedDealCategories[mainCategory] || []), ...userCreatedDealCategories]);
            tagsContainer.innerHTML = '';
            allTags.forEach(category => {
                const tag = document.createElement('div'); tag.className = 'category-tag'; tag.dataset.category = category;
                if (category === currentSelected) tag.classList.add('selected');
                tag.onclick = (e) => {
                    e.stopPropagation(); entry.querySelector('.hidden-category-input').value = category; entry.querySelector('.category-input').value = category;
                    entry.querySelectorAll('.category-tag').forEach(t => t.classList.remove('selected')); tag.classList.add('selected');
                };
                tagsContainer.appendChild(tag);
            });
        }

        function addDealItemEntry() {
            if (dealItemsContainer.children.length >= 20) { alert("⚠️ الحد الأقصى 20 عرض."); return; }
            const entryDiv = document.createElement('div'); entryDiv.className = 'menu-item-entry'; 
            const idx = dealItemCounter++;
            
            entryDiv.innerHTML = `
                <div class="collapsed-summary"><img src="../image/default_logo.webp" class="summary-img" alt=""><div class="summary-info"><span class="summary-name">عرض جديد</span><span class="summary-price">0 ل.س</span></div><i class="fas fa-chevron-down expand-icon"></i></div>
                <div class="remove-btn-wrapper"><button type="button" class="btn-danger"><i class="fas fa-trash-alt"></i></button></div>
                <div class="menu-item-category-manager">
                    <label>فئة العرض</label>
                    <div class="category-input-group"><input type="text" class="category-input" placeholder="اكتب أو اختر..."><button type="button" class="add-category-btn">إضافة</button></div>
                    <div class="category-tags"></div>
                    <input type="hidden" name="deals[${idx}][category_name]" class="hidden-category-input" value="عروض عامة">
                </div>
                <div class="menu-item-fields">
                    <div class="image-upload-wrapper">
                        <img src="" class="image-preview">
                        <input type="file" name="deals_images[${idx}]" accept="image/*" class="menu-image-input">
                        <i class="fas fa-camera upload-icon"></i>
                    </div>
                    <div class="fields-grid">
                        <div class="form-group"><label>اسم العرض*</label><input type="text" name="deals[${idx}][deal_name]" class="form-control name-live-update" required></div>
                        <div class="form-group"><label>السعر الجديد*</label><input type="number" name="deals[${idx}][new_price]" placeholder="بالليرة السورية" class="form-control price-input price-live-update" required></div>
                        <div class="form-group"><label>السعر القديم</label><input type="number" name="deals[${idx}][old_price]" placeholder="بالليرة السورية" class="form-control price-input"></div>
                        <div class="form-group full-width"><label>وصف</label><textarea name="deals[${idx}][description]" class="form-control"></textarea></div>
                    </div>
                </div>
            `;
            dealItemsContainer.appendChild(entryDiv);
            updateDealCategoryTags(entryDiv);

            entryDiv.addEventListener('click', function(e) {
                if(['INPUT','TEXTAREA','BUTTON'].includes(e.target.tagName) || e.target.closest('.category-tag') || e.target.closest('.image-upload-wrapper')) return;
                this.classList.toggle('collapsed');
            });
            const nameIn = entryDiv.querySelector('.name-live-update');
            const priceIn = entryDiv.querySelector('.price-live-update');
            nameIn.addEventListener('input', () => entryDiv.querySelector('.summary-name').textContent = nameIn.value || 'عرض جديد');
            priceIn.addEventListener('input', () => entryDiv.querySelector('.summary-price').textContent = priceIn.value + ' ل.س');

            entryDiv.querySelector('.btn-danger').addEventListener('click', (e) => { e.stopPropagation(); entryDiv.remove(); });
            entryDiv.querySelector('.menu-image-input').addEventListener('change', (e) => {
                if (e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = ev => { entryDiv.querySelector('.image-preview').src = ev.target.result; entryDiv.querySelector('.image-preview').classList.add('has-image'); entryDiv.querySelector('.summary-img').src = ev.target.result; };
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
            entryDiv.querySelector('.add-category-btn').addEventListener('click', (e) => {
                e.stopPropagation(); const val = entryDiv.querySelector('.category-input').value.trim();
                if(val) { userCreatedDealCategories.add(val); entryDiv.querySelector('.hidden-category-input').value = val; document.querySelectorAll('#deal-items-container .menu-item-entry').forEach(updateDealCategoryTags); }
            });
        }
        addDealItemBtn.addEventListener('click', addDealItemEntry);
        categorySelect.addEventListener('change', () => document.querySelectorAll('#deal-items-container .menu-item-entry').forEach(updateDealCategoryTags));
        addDealItemEntry();

        // الساعات
        window.updateHiddenInput = function(day) {
            const row = document.getElementById('row-' + day);
            const statusCb = row.querySelector('.day-status-cb');
            const allDayCb = row.querySelector('.all-day-cb');
            const startTime = row.querySelector('.start-time').value;
            const endTime = row.querySelector('.end-time').value;
            const hiddenInput = document.getElementById('input-' + day);
            if (!statusCb.checked) hiddenInput.value = 'closed';
            else if (allDayCb.checked) hiddenInput.value = '24 hours';
            else hiddenInput.value = `${startTime} - ${endTime}`;
        };
        window.toggleHours = function(day) {
            const row = document.getElementById('row-' + day);
            const inputsGroup = document.getElementById('group-' + day);
            if (row.querySelector('.day-status-cb').checked) { inputsGroup.classList.remove('disabled'); updateHiddenInput(day); }
            else { inputsGroup.classList.add('disabled'); document.getElementById('input-' + day).value = 'closed'; }
        };
        window.toggle24Hours = function(day) {
            const row = document.getElementById('row-' + day);
            const startInput = row.querySelector('.start-time');
            const endInput = row.querySelector('.end-time');
            if (row.querySelector('.all-day-cb').checked) { startInput.disabled = true; endInput.disabled = true; startInput.value = '00:00'; endInput.value = '23:59'; }
            else { startInput.disabled = false; endInput.disabled = false; startInput.value = '09:00'; endInput.value = '22:00'; }
            updateHiddenInput(day);
        };
        const days = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
        days.forEach(day => updateHiddenInput(day));
    });
</script>
<?php include 'footer.php'; ?>