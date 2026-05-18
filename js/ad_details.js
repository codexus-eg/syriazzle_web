document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const adId = urlParams.get('id');

    const loadingMessage = document.getElementById('loadingMessage');
    const errorMessage = document.getElementById('errorMessage');
    const adContent = document.getElementById('adContent');
    const dynamicAdDetailsContainer = document.getElementById('dynamicAdDetails');

    // تحقق من وجود جميع عناصر DOM المطلوبة قبل البدء
    if (!loadingMessage || !errorMessage || !adContent || !dynamicAdDetailsContainer) {
        console.error("خطأ: أحد عناصر DOM المطلوبة (loadingMessage, errorMessage, adContent, dynamicAdDetails) غير موجود في الصفحة.");
        // يمكنك إظهار رسالة خطأ عامة للمستخدم هنا
        return;
    }

    // إخفاء رسائل الخطأ والتحميل مبدئيًا، وإظهار المحتوى عند الحاجة
    loadingMessage.style.display = 'block'; // أظهره عند البدء
    errorMessage.style.display = 'none';
    adContent.style.display = 'none';

    if (!adId) {
        loadingMessage.style.display = 'none';
        errorMessage.style.display = 'block';
        errorMessage.textContent = "لم يتم تحديد معرف الإعلان في الرابط.";
        console.error("خطأ: معرف الإعلان (ID) غير موجود في URL.");
        return;
    }

    async function fetchAdAndRender() {
        try {
          
            const adResponse = await fetch(`../php/get_specific_ad.php?id=${adId}`); 
            
            if (!adResponse.ok) {
                const errorDetail = await adResponse.text(); 
                console.error(`خطأ HTTP عند جلب تفاصيل الإعلان: Status ${adResponse.status}`, errorDetail);
                throw new Error(`خطأ في الخادم (${adResponse.status}). يرجى المحاولة لاحقاً.`);
            }
            
            const adResult = await adResponse.json();

            if (!adResult.success || !adResult.data) {
                errorMessage.textContent = adResult.error || 'فشل في تحميل تفاصيل الإعلان. البيانات غير صالحة.';
                errorMessage.style.display = 'block';
                console.error('خطأ API في جلب الإعلان:', adResult.error || 'لا توجد بيانات إعلان مستلمة.');
                return;
            }

            const ad = adResult.data;

            // المسار إلى ملف JSON للفئات
            // افتراض أن ملف الـ JSON الرئيسي لجميع الفئات اسمه 'categories.json'
            // وموجود في Souq_Syria/json/
            const categoriesResponse = await fetch('../json/categories.json'); 
            
            if (!categoriesResponse.ok) {
                const errorDetail = await categoriesResponse.text();
                console.error(`فشل في تحميل ملف JSON للفئات: Status ${categoriesResponse.status}`, errorDetail);
                throw new Error(`فشل في تحميل بيانات الفئات (${categoriesResponse.status}).`);
            }
            const categoriesData = await categoriesResponse.json();

            // إذا وصلت هنا، كل شيء تم تحميله بنجاح
            loadingMessage.style.display = 'none';
            adContent.style.display = 'block';

            // عرض التفاصيل الثابتة والديناميكية
            renderStaticAdDetails(ad);
            renderDynamicAdDetails(ad, categoriesData);

        } catch (error) {
            loadingMessage.style.display = 'none';
            errorMessage.style.display = 'block';
            errorMessage.textContent = `حدث خطأ أثناء تحميل البيانات: ${error.message}`;
            console.error('خطأ عام في جلب وعرض الإعلان:', error);
        }
    }

    function renderStaticAdDetails(ad) {
        // تحديث العنوان بناءً على الماركة أو العنوان الافتراضي
        document.getElementById('adTitle').textContent = ad['الماركة'] || ad.title || 'إعلان';

        // عرض التاريخ
        if (ad.submitted_at) {
            try {
                // التأكد من أن التنسيق يطابق التاريخ المخزن
                // إذا كان `submitted_at` بتنسيق `YYYY-MM-DD HH:MM:SS`
                const dateOptions = { year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false };
                document.getElementById('adDate').textContent = new Date(ad.submitted_at).toLocaleDateString('ar-SY', dateOptions);
            } catch (e) {
                console.error("خطأ في تحليل التاريخ:", ad.submitted_at, e);
                document.getElementById('adDate').textContent = ad.submitted_at; // عرض القيمة الخام إذا فشل التحليل
            }
        } else {
            document.getElementById('adDate').textContent = 'تاريخ غير متوفر';
        }
        
        document.getElementById('adLocation').textContent = ad['الموقع'] || 'غير محدد';

        document.getElementById('adCategory').textContent = ad.category || 'غير محددة';
        document.getElementById('adSub').textContent = ad.sub || 'غير محدد';
        document.getElementById('adSubsub').textContent = ad.subsub || 'غير محدد';
        document.getElementById('adSubsubsub').textContent = ad.subsubsub || 'غير محدد';

        document.getElementById('adDescription').textContent = ad['الوصف_الإضافي'] || 'لا يوجد وصف إضافي.';
        document.getElementById('adPhoneNumber').textContent = ad['رقم_الهاتف'] || 'غير متوفر';
        document.getElementById('adWhatsappNumber').textContent = ad['رقم_الواتس'] || 'غير متوفر';

        // عرض الصور
        const mainAdImage = document.getElementById('mainAdImage');
        const adThumbnails = document.getElementById('adThumbnails');
        adThumbnails.innerHTML = ''; // مسح الصور المصغرة السابقة

        if (ad.images && ad.images.length > 0) {
            // المسار إلى الصور المحملة: Souq_Syria/uploads/
            // بما أن ad_details.js في Souq_Syria/js/، المسار النسبي هو ../uploads/
            mainAdImage.src = `../uploads/${ad.images[0]}`; 
            mainAdImage.alt = ad.title || 'صورة الإعلان الرئيسية';

            ad.images.forEach((imagePath, index) => {
                const imgThumb = document.createElement('img');
                imgThumb.src = `../uploads/${imagePath}`; // نفس المسار للصور المصغرة
                imgThumb.alt = `صورة مصغرة ${index + 1}`;
                imgThumb.classList.add('thumbnail');
                if (index === 0) imgThumb.classList.add('active'); // تفعيل الصورة الأولى افتراضيا

                imgThumb.addEventListener('click', () => {
                    mainAdImage.src = `../uploads/${imagePath}`;
                    // إزالة فئة 'active' من كل الصور المصغرة ثم إضافتها للصور المحددة
                    document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
                    imgThumb.classList.add('active');
                });
                adThumbnails.appendChild(imgThumb);
            });
        } else {
            // مسار الصورة الافتراضية إذا لم توجد صور
            mainAdImage.src = '../image/default-ad-image.png'; // تأكد من وجود هذا الملف في Souq_Syria/image/
            mainAdImage.alt = 'لا توجد صور لهذا الإعلان';
        }
    }

    function renderDynamicAdDetails(ad, categoriesJson) {
        dynamicAdDetailsContainer.innerHTML = ''; // مسح أي محتوى ديناميكي سابق

        const category = ad.category;
        const sub = ad.sub;
        const subsub = ad.subsub;
        const subsubsub = ad.subsubsub;

        let fieldsToRender = [];

        const categoryData = categoriesJson[category];
        if (categoryData) {
            let currentLevel = categoryData;

            // تحديد الحقول بناءً على أعمق مستوى موجود
            if (sub && subsub && subsubsub && currentLevel.subcategories?.[sub]?.subsubcategories?.[subsub]?.subsubsubcategories?.[subsubsub]) {
                fieldsToRender = currentLevel.subcategories[sub].subsubcategories[subsub].subsubsubcategories[subsubsub].fields || [];
            } else if (sub && subsub && currentLevel.subcategories?.[sub]?.subsubcategories?.[subsub]) {
                fieldsToRender = currentLevel.subcategories[sub].subsubcategories[subsub].fields || [];
            } else if (sub && currentLevel.subcategories?.[sub]) {
                fieldsToRender = currentLevel.subcategories[sub].fields || [];
            } else {
                fieldsToRender = currentLevel.fields || [];
            }
        }

        // الحقول الأساسية التي قد لا تكون في الـ JSON ولكن نريد عرضها
        // هذه الحقول يجب أن تأتي من json_data في قاعدة البيانات أو الأعمدة الأساسية
        const essentialFields = [
            { name: "السعر", key: "السعر", type: "number", suffix: " ل.س" },
            { name: "عدد الكيلومترات", key: "الكيلومترات", type: "number", suffix: " كم" },
            { name: "الماركة", key: "الماركة" },
            { name: "الموديل", key: "الموديل" },
            { name: "السنة", key: "السنة" },
            { name: "الحالة", key: "الحالة" },
            { name: "نوع المحرك", key: "نوع_المحرك" },
            { name: "اللون", key: "اللون" },
            // أضف أي حقول أخرى تتوقعها في json_data وتريد عرضها
        ];

        // دمج الحقول الديناميكية من JSON مع الحقول الأساسية
        // تجنب التكرار: إذا كان الحقل موجودًا في fieldsToRender بالفعل، لا تضفه من essentialFields
        const allFields = [...fieldsToRender];
        essentialFields.forEach(ef => {
            if (!allFields.some(f => f.key === ef.key || f.name === ef.name)) {
                allFields.push(ef);
            }
        });

        allFields.forEach(field => {
            const fieldKey = field.key || field.name; 
            let fieldValue = ad[fieldKey]; // محاولة جلب القيمة مباشرة من كائن الإعلان

            // إذا لم يتم العثور على القيمة مباشرة، حاول جلبها من ad.json_data إذا كانت موجودة
            if ((fieldValue === null || fieldValue === undefined || fieldValue === '') && ad.json_data_decoded) {
                // ad.json_data_decoded هي نتيجة json_decode في PHP
                fieldValue = ad.json_data_decoded[fieldKey];
            } else if ((fieldValue === null || fieldValue === undefined || fieldValue === '') && ad.json_data && typeof ad.json_data === 'string') {
                 // في حالة أن json_data لم يتم فك ترميزه في PHP (لسبب ما)
                try {
                    const parsedJsonData = JSON.parse(ad.json_data);
                    fieldValue = parsedJsonData[fieldKey];
                } catch (e) {
                    console.warn(`تحذير: لم يتمكن من تحليل json_data أو العثور على المفتاح '${fieldKey}' في json_data الخام.`, e);
                }
            }


            // تطبيق التنسيق الخاص (مثل إضافة "ل.س" أو "كم")
            if (field.type === 'number' && typeof fieldValue === 'number') {
                if (field.suffix) {
                    fieldValue = `${fieldValue} ${field.suffix}`;
                }
            } else if (field.type === 'text' || field.type === 'textarea') {
                // قد ترغب في معالجة الأسطر الجديدة أو المسافات البيضاء هنا
                fieldValue = fieldValue ? String(fieldValue).trim() : '';
            }
            
            // لا تعرض الحقل إذا كانت قيمته فارغة بعد كل المعالجة
            if (fieldValue === null || fieldValue === undefined || fieldValue === '') {
                return; 
            }

            const detailItem = document.createElement('div');
            detailItem.classList.add('detail-item');
            detailItem.innerHTML = `<strong>${field.name}:</strong> <span>${fieldValue}</span>`;
            dynamicAdDetailsContainer.appendChild(detailItem);
        });
    }
    

    fetchAdAndRender();
});