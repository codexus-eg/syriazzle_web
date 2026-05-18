<?php
// =======================================================
// 1. الإعدادات ومنطق PHP لاستخراج الحقول من JSON
// =======================================================
require_once 'php/db_connect.php'; // سنحتاج للاتصال بقاعدة البيانات هنا
$jsonFilePath = 'json/json-all.json'; 
$fieldsArray = [];
$errorMessage = '';
$editMode = false;
$adDataToEdit = null; // سيحتوي على بيانات الإعلان للتعديل
$imagesToEdit = [];   // سيحتوي على صور الإعلان للتعديل

// جلب المسار ومعالجته
$path = $_GET['path'] ?? null; 
$pathSegments = $path ? explode('/', $path) : [];
$categoryKey = $pathSegments[0] ?? null; 
$subCategoryKey = end($pathSegments); 

// --- >> [ تعديل جديد: التحقق من وضع التعديل ] << ---
$ad_id_to_edit = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : null;

if ($ad_id_to_edit) {
    $editMode = true;
    try {
        $stmt = $pdo->prepare("SELECT * FROM form_submissions WHERE id = :id");
        $stmt->execute([':id' => $ad_id_to_edit]);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ad) {
            $adDataToEdit = json_decode($ad['json_data'], true);
            $imagesToEdit = json_decode($ad['images_paths'], true);
            // التأكد من أن المسار في الرابط يطابق مسار الإعلان (حماية إضافية)
            if (empty($path)) {
                $path = $adDataToEdit['path'] ?? '';
                $pathSegments = $path ? explode('/', $path) : [];
                $categoryKey = $pathSegments[0] ?? null; 
                $subCategoryKey = end($pathSegments); 
            }
        } else {
            $errorMessage = "الإعلان المطلوب غير موجود.";
        }
    } catch (Exception $e) {
        $errorMessage = "خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage();
    }
}
// --- >> [ نهاية التعديل ] << ---

// دالة جلب العقدة النهائية (مرنة للمفاتيح: subcategories/subsubcategories)
function getFinalNode(array $fullData, array $segments): ?array {
    // ... (هذه الدالة تبقى كما هي، لا تغيير) ...
    $current = $fullData;
    $mainCategoryKey = $segments[0] ?? null; 
    if (!$mainCategoryKey || !isset($current[$mainCategoryKey])) return null;
    $current = $current[$mainCategoryKey]; 
    for ($i = 1; $i < count($segments); $i++) {
        $segment = $segments[$i];
        if (isset($current['subcategories'][$segment])) {
            $current = $current['subcategories'][$segment];
        } elseif (isset($current['subsubcategories'][$segment])) {
            $current = $current['subsubcategories'][$segment];
        } else {
            return null; 
        }
    }
    return $current; 
}


if (empty($errorMessage)) { // نستكمل فقط إذا لم يكن هناك خطأ
    if (!$path || count($pathSegments) < 1) { 
        $errorMessage = 'يرجى اختيار التصنيف الصحيح لإنشاء الإعلان.';
    } elseif (!file_exists($jsonFilePath)) {
        $errorMessage = "ملف الـ JSON غير موجود.";
    } else {
        $jsonString = file_get_contents($jsonFilePath);
        $data = json_decode($jsonString, true);
        $finalNode = getFinalNode($data, $pathSegments);
        
        if ($finalNode !== null && isset($finalNode['fields'])) {
            $fieldsArray = $finalNode['fields'];
        } else {
            $errorMessage = "لم يتم العثور على حقول للإعلان في التصنيف: " . str_replace('_', ' ', $subCategoryKey) . ".";
        }
    }
}

// حساب متغيرات العرض والعودة
$categoryDisplayName = str_replace('_', ' ', $categoryKey);
$subCategoryDisplayName = str_replace('_', ' ', $subCategoryKey);
$backSegments = $pathSegments;
array_pop($backSegments); 
$backPath = implode('/', $backSegments);
$backLink = $backPath ? "subcategories.php?path=" . urlencode($backPath) : "index.php";
$previousCategoryName = str_replace('_', ' ', end($backSegments) ?: 'الرئيسية');

?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- >> تعديل جديد: تغيير العنوان في وضع التعديل << -->
    <title><?= $editMode ? 'تعديل الإعلان' : 'نشر إعلان' ?></title>
    <!-- ... باقي الـ <head> يبقى كما هو ... -->
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="css/normalize.css" />
    <link rel="stylesheet" href="css/main_header.css">
    <link rel="stylesheet" href="css/dubizzle-inspired.css">
    <link rel="stylesheet" href="css/all.min.css" />
    <link rel="stylesheet" href="css/formStyle.css">
    <link rel="stylesheet" href="css/libs/intlTelInput.css" />
</head>
<body>
    <!-- ... الهيدر يبقى كما هو ... -->
    <div id="loading-overlay">
        <div class="spinner"></div>
        <p>جاري رفع الإعلان، يرجى الانتظار...</p>
    </div>
    <?php include 'header_store.php'; ?>
    <div class="container">
      <div class="pages-link">
          <a class="link-post" href="ads_new.php"><i class="fa-solid fa-house"></i></a>
          <a class="link-post" href="<?= htmlspecialchars($backLink) ?>"><span> / </span><?= htmlspecialchars($previousCategoryName) ?></a>
          <span class="link-post"><span> / </span><?= htmlspecialchars($subCategoryDisplayName) ?></span>
      </div>
    </div>
    <!-- >> تعديل جديد: تغيير العنوان في وضع التعديل << -->
    <h1>📝 <?= $editMode ? 'تعديل الإعلان' : 'إنشاء إعلان' ?></h1>

    <?php if (!empty($errorMessage)): ?>
        <div style="color: red; text-align: center; padding: 15px; border: 1px solid red; margin: 20px auto; width: 80%;">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php else: ?>
        <form id="ad-form" action="php/submit_form.php" method="POST" enctype="multipart/form-data">
            <!-- >> تعديل جديد: إضافة حقل مخفي لمعرّف الإعلان في وضع التعديل << -->
            <?php if ($editMode && $ad_id_to_edit): ?>
                <input type="hidden" name="ad_id" id="ad_id" value="<?= htmlspecialchars($ad_id_to_edit) ?>">
            <?php endif; ?>
        </form>
    <?php endif; ?>
    
    <!-- >> تعديل جديد: تغيير نص الزر في وضع التعديل << -->
    <button type="button" class="submit-btn" id="submit-ad-btn"><?= $editMode ? '💾 تحديث الإعلان' : '📤 إرسال الإعلان' ?></button>
    
    <input type="hidden" id="php-path" value="<?= htmlspecialchars($path) ?>">
    <input type="hidden" id="php-category-key" value="<?= htmlspecialchars($categoryKey) ?>">
    <input type="hidden" id="php-sub-key" value="<?= htmlspecialchars($subCategoryKey) ?>">
    
    <script src="js/libs/browser-image-compression.js" defer></script>
    <script src="js/libs/intlTelInput.min.js" defer></script>
    <script>
    // =======================================================
    // ⚠️ دمج الحقول والبيانات من PHP في JavaScript
    // =======================================================
    const DYNAMIC_FIELDS_JSON = <?= json_encode($fieldsArray); ?>;
    // --- >> [ تعديل جديد: تمرير بيانات الإعلان الحالي ] << ---
    const EXISTING_AD_DATA = <?= json_encode($adDataToEdit); ?>;
    const EXISTING_IMAGES = <?= json_encode($imagesToEdit); ?>;
    // =======================================================
    
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById("ad-form");
        const submitBtn = document.getElementById("submit-ad-btn");
        const loadingOverlay = document.getElementById("loading-overlay");

        let selectedFiles = []; // الصور الجديدة
        let existingImagePaths = [...(EXISTING_IMAGES || [])]; // الصور القديمة
        let phoneInputInstances = {};
        const MAX_FILES = 6;
      
        if(submitBtn) {
            submitBtn.addEventListener('click', submitAd);
        }

        async function loadCategories() {
            const dynamicFields = DYNAMIC_FIELDS_JSON || [];
            
            // الثوابت (تبقى كما هي)
            const fixed = [
                { name: "المحافظة", type: "select", options: ["دمشق", "ريف دمشق", "حلب", "حمص", "اللاذقية", "طرطوس", "حماة", "درعا", "السويداء", "إدلب", "الرقة", "دير الزور", "الحسكة", "القنيطرة"] },
                { name: "العنوان بالتفصيل", type: "text"},                    
                { name: "السعر" , min: 100, max: 9999999 },
                { name: "رقم الهاتف", type: "tel" },
                { name: "رقم الواتس", type: "tel" },
                { name: "الصور", type: "file", multiple: true },
                { name: "الوصف الإضافي", type: "textarea", maxLength: 300, placeholder: "اكتب وصفًا دقيقًا...", required: false },
            ];
            renderFields([...dynamicFields, ...fixed]);
            renderImages(); // لعرض الصور الحالية عند تحميل الصفحة
        }

        function renderFields(fields) {
            form.innerHTML = "";
            phoneInputInstances = {};

            fields.forEach((field) => {
                const wrapper = document.createElement("div");
                wrapper.className = "form-group";
                const label = document.createElement("label");
                label.textContent = field.name;
                wrapper.appendChild(label);
                
                // --- >> [ تعديل جديد: جلب القيمة الحالية من بيانات الإعلان ] << ---
                const existingValue = EXISTING_AD_DATA ? EXISTING_AD_DATA[field.name] : null;

                if (field.name === "السعر") {
                    // ... (المنطق هنا يبقى كما هو، لكن سنقوم بتعبئة القيم)
                    const priceGroup = document.createElement("div");
                    priceGroup.className = "price-input-group";
                    const priceInput = document.createElement("input");
                    priceInput.type = "number";
                    priceInput.name = "السعر";
                    priceInput.required = true;
                    if (field.min !== undefined) priceInput.min = field.min;
                    if (field.max !== undefined) priceInput.max = field.max;
                    
                    const currencySelect = document.createElement("select");
                    currencySelect.name = "العملة";
                    currencySelect.innerHTML = `<option value="ل.س">ل.س</option><option value="$">$</option>`;

                    // تعبئة القيمة الحالية للسعر والعملة
                    if (existingValue) {
                        const parts = existingValue.split(' ');
                        priceInput.value = parts[0] || '';
                        if (parts[1]) {
                            currencySelect.value = parts[1];
                        }
                    }
                    
                    priceGroup.appendChild(priceInput);
                    priceGroup.appendChild(currencySelect); 
                    wrapper.appendChild(priceGroup);

                } else if (field.name === "رقم الهاتف" || field.name === "رقم الواتس") {
                    const input = document.createElement("input");
                    input.id = `tel-${field.name.replace(/\s+/g, "-")}`;
                    input.type = "tel";
                    input.name = field.name;
                    input.required = field.required !== false;
                    
                    wrapper.appendChild(input); // يجب إضافته قبل تهيئة intlTelInput

                    if (window.intlTelInput) {
                        const iti = window.intlTelInput(input, {
                            initialCountry: "sy",
                            separateDialCode: true,
                            utilsScript: "js/libs/utils.js",
                            preferredCountries: ["sy", "lb", "jo", "iq", "eg", "sa", "ae"],
                            placeholderNumberType: "MOBILE",
                        });
                        phoneInputInstances[input.id] = iti;
                        // تعبئة رقم الهاتف الحالي
                        if (existingValue) {
                           iti.setNumber(existingValue);
                        }
                    } else {
                         input.value = existingValue || '';
                    }

                } else if (field.type === "file" && field.multiple) {
                    // ... (منطق حقل الصور يبقى كما هو) ...
                     const uploaderContainer = document.createElement("div");
                    uploaderContainer.className = "custom-image-uploader";
                    const fileInput = document.createElement("input");
                    fileInput.id = "image-upload-input";
                    fileInput.type = "file";
                    fileInput.multiple = true;
                    fileInput.accept = "image/*";
                    fileInput.style.display = "none";
                    fileInput.addEventListener("change", handleFileSelect);
                    const uploadLabel = document.createElement("label");
                    uploadLabel.htmlFor = "image-upload-input";
                    uploadLabel.className = "upload-btn-label";
                    uploadLabel.textContent = "أضف المزيد من الصور";
                    const instructions = document.createElement("div");
                    instructions.className = "upload-instructions";
                    instructions.textContent = `الحد الأقصى ${MAX_FILES} صور`;
                    uploaderContainer.appendChild(fileInput);
                    uploaderContainer.appendChild(uploadLabel);
                    uploaderContainer.appendChild(instructions);
                    wrapper.appendChild(uploaderContainer);
                } else {
                    let input;
                    if (field.type === "select") {
                        input = document.createElement("select");
                        // ... (منطق إنشاء الخيارات يبقى كما هو)
                        const placeholder = document.createElement("option");
                        placeholder.value = "";
                        placeholder.disabled = true;
                        placeholder.selected = true;
                        placeholder.hidden = true;
                        placeholder.textContent = "اختر قيمة..."; 
                        input.appendChild(placeholder);
                        if (field.options && Array.isArray(field.options)) {
                            field.options.forEach((opt) => {
                                const o = document.createElement("option");
                                o.value = opt;
                                o.textContent = opt;
                                input.appendChild(o);
                            });
                        }
                    } else {
                        // ... (منطق إنشاء الحقول الأخرى يبقى كما هو)
                         input = document.createElement(field.type === 'textarea' ? 'textarea' : 'input');
                        if(field.type !== 'textarea') input.type = field.type || "text";
                        if (field.maxLength) input.maxLength = field.maxLength || 300;
                        if (field.placeholder) input.placeholder = field.placeholder || "";
                        if (field.min !== undefined) input.min = field.min;
                        if (field.max !== undefined) input.max = field.max;
                    }
                    input.name = field.name;
                    input.required = field.required !== false;
                     // --- >> [ تعديل جديد: تعبئة القيمة الحالية ] << ---
                    if (existingValue) {
                        input.value = existingValue;
                    }
                    wrapper.appendChild(input);
                }
                form.appendChild(wrapper);
            });
            const previewsContainer = document.createElement("div");
            previewsContainer.id = "previews-container";
            form.appendChild(previewsContainer);
            
            // --- >> [ تعديل جديد: إضافة حقل مخفي لمعرف الإعلان إذا لم يكن موجوداً ] << ---
            if (EXISTING_AD_DATA && !form.querySelector('#ad_id')) {
                const adIdInput = document.createElement('input');
                adIdInput.type = 'hidden';
                adIdInput.name = 'ad_id';
                adIdInput.id = 'ad_id';
                adIdInput.value = new URLSearchParams(window.location.search).get('edit_id');
                form.appendChild(adIdInput);
            }
        }
        
        // --- >> [ تعديل جديد: تحديث دالة التعامل مع الصور ] << ---
        function handleFileSelect(event) {
            const newFiles = Array.from(event.target.files);
            const totalImages = selectedFiles.length + existingImagePaths.length;
            
            newFiles.forEach((file) => {
                if (totalImages >= MAX_FILES) { 
                    showNotification(`لا يمكن تحميل أكثر من ${MAX_FILES} صور.`, 'error'); return; 
                }
                const isDuplicate = selectedFiles.some(f => f.name === file.name && f.size === file.size) ||
                                    existingImagePaths.some(p => p.endsWith(file.name));
                if (isDuplicate) { 
                    showNotification(`الصورة "${file.name}" مضافة بالفعل.`, 'warning'); return; 
                }
                selectedFiles.push(file);
            });
            renderImages();
            event.target.value = "";
        }

        function renderImages() {
            const previewsContainer = document.getElementById("previews-container");
            if (!previewsContainer) return;
            previewsContainer.innerHTML = "";

            // عرض الصور الحالية (القديمة)
            existingImagePaths.forEach((imagePath, index) => {
                const wrapper = document.createElement("div");
                wrapper.className = "image-preview-wrapper";
                const img = document.createElement("img");
                img.src = imagePath; // المسار الكامل للصورة
                img.className = "preview-img";
                const removeBtn = document.createElement("button");
                removeBtn.type = "button";
                removeBtn.innerHTML = "×";
                removeBtn.className = "delete-btn";
                removeBtn.addEventListener("click", () => {
                    existingImagePaths.splice(index, 1); // حذف من مصفوفة الصور القديمة
                    renderImages();
                });
                wrapper.appendChild(img);
                wrapper.appendChild(removeBtn);
                previewsContainer.appendChild(wrapper);
            });

            // عرض الصور الجديدة التي تم اختيارها
            selectedFiles.forEach((file, index) => {
                const wrapper = document.createElement("div");
                wrapper.className = "image-preview-wrapper";
                const img = document.createElement("img");
                img.src = URL.createObjectURL(file);
                img.className = "preview-img";
                const removeBtn = document.createElement("button");
                removeBtn.type = "button";
                removeBtn.innerHTML = "×";
                removeBtn.className = "delete-btn";
                removeBtn.addEventListener("click", () => {
                    selectedFiles.splice(index, 1); // حذف من مصفوفة الصور الجديدة
                    renderImages();
                });
                wrapper.appendChild(img);
                wrapper.appendChild(removeBtn);
                previewsContainer.appendChild(wrapper);
            });
        }
        
        function validateForm() {
            // ... (هذه الدالة تبقى كما هي، لا تغيير) ...
            const textData = {};
            const forbiddenWords = ["سياسة", "جنس", "إباحية", "دعارة", "إرهاب", "قتل", "كراهية"];
            let valid = true;

            const allInputs = form.querySelectorAll("input:not([type=file]), textarea, select");

            allInputs.forEach(input => {
                if (input.name === 'ad_id') return; // تجاهل حقل معرّف الإعلان
                const wrapper = input.closest('.form-group');
                if (!wrapper) return;

                wrapper.classList.remove("has-error");
                const err = wrapper.querySelector(".error-message");
                if (err) err.remove();

                if (input.name === "العملة") return;

                //التعامل مع حقل الهاتف
                if (phoneInputInstances[input.id]) {
                    const iti = phoneInputInstances[input.id];
                    if (input.required && !input.value.trim()) {
                        valid = false;
                        showValidationError(wrapper, "⚠️ يرجى تعبئة هذا الحقل.");
                    } else if (input.value.trim() && !iti.isValidNumber()) {
                        valid = false;
                        showValidationError(wrapper, "⚠️ رقم الهاتف الذي أدخلته غير صحيح لهذه الدولة.");
                    } else {
                        textData[input.name] = iti.getNumber() || '';
                    }
                } 
                // التعامل مع حقل السعر
                else if (input.name === "السعر") {
                    const priceValue = input.value.trim();
                    const currencySelect = form.querySelector('select[name="العملة"]');
                    if (input.required && !priceValue) {
                        valid = false;
                        showValidationError(wrapper, "⚠️ يرجى تعبئة هذا الحقل.");
                    } else if (priceValue && currencySelect) {
                        textData[input.name] = `${priceValue} ${currencySelect.value}`;
                    } else {
                        textData[input.name] = priceValue;
                    }
                }
                // التعامل مع كل الحقول الأخرى
                else {
                    const value = input.value.trim();
                    if (input.required && !value) {
                        valid = false;
                        showValidationError(wrapper, "⚠️ يرجى تعبئة هذا الحقل.");
                    } else if (forbiddenWords.some(w => value.includes(w))) {
                        valid = false;
                        showValidationError(wrapper, "⚠️ يحتوي على كلمات غير لائقة.");
                    } else {
                        textData[input.name] = value;
                    }
                }
            });
            // --- >> [ تعديل جديد: التحقق من وجود صور قديمة أو جديدة ] << ---
            if (selectedFiles.length === 0 && existingImagePaths.length === 0) {
                showNotification("يرجى اختيار صورة واحدة على الأقل للإعلان.", 'error');
                valid = false;
            }
            return { valid, textData };
        }

        async function submitAd() {
            const { valid, textData } = validateForm();
            if (!valid) return;
            showLoading(true);
            try {
                const finalFormData = new FormData();
                
                const path = document.getElementById("php-path").value;
                const category = document.getElementById("php-category-key").value;
                const sub = document.getElementById("php-sub-key").value;
                
                textData["path"] = path;
                textData["category"] = category;
                textData["sub"] = sub;
                delete textData["subsub"]; 
                delete textData["subsubsub"];
                
                finalFormData.append("json_data", JSON.stringify(textData));

                // --- >> [ تعديل جديد: إضافة معرف الإعلان والصور المتبقية ] << ---
                const adIdInput = document.getElementById('ad_id');
                if (adIdInput) {
                    finalFormData.append('ad_id', adIdInput.value);
                }
                finalFormData.append('existing_images', JSON.stringify(existingImagePaths));
                // --- >> [ نهاية التعديل ] << ---

                for (const file of selectedFiles) {
                    const options = { maxSizeMB: 1, maxWidthOrHeight: 1920, useWebWorker: true };
                    const compressedFile = await imageCompression(file, options);
                    finalFormData.append("images[]", compressedFile, compressedFile.name);
                }
                const response = await fetch("php/submit_form.php", {
                    method: "POST", body: finalFormData
                });
                const result = await response.json();
                if (result.success) {
                    showNotification(result.message || 'تمت العملية بنجاح!', 'success');
                    setTimeout(handleSuccess, 1500);
                } else {
                    showNotification(result.error || "فشل غير معروف في العملية.", 'error');
                }
            } catch (error) {
                console.error("خطأ في إرسال النموذج:", error);
                showNotification("حدث خطأ غير متوقع أثناء الإرسال: " + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }
        
        function showValidationError(wrapper, message) {
            const err = document.createElement("div");
            err.className = "error-message";
            err.textContent = message;
            wrapper.appendChild(err);
            wrapper.classList.add("has-error");
        }
        function showLoading(isLoading) {
            const actionText = document.getElementById('ad_id') ? 'التحديث' : 'الإرسال';
            if (isLoading) {
            loadingOverlay.style.display = "flex";
            submitBtn.disabled = true;
            submitBtn.textContent = `جاري ${actionText}...`;
            } else {
            loadingOverlay.style.display = "none";
            submitBtn.disabled = false;
            submitBtn.textContent = document.getElementById('ad_id') ? '💾 تحديث الإعلان' : '📤 إرسال الإعلان';
            }
        }
        function showNotification(message, type) {
            const notificationDiv = document.createElement("div");
            notificationDiv.textContent = message;
            notificationDiv.style.cssText = `
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            padding: 15px 25px; border-radius: 8px; color: white;
            font-weight: bold; z-index: 10000; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: fadeOut 4s forwards;
            background-color: ${
            type === "success"
                ? "#28a745"
                : type === "warning"
                ? "#ffc107"
                : "#dc3545"
            };
            color: ${type === "warning" ? "#333" : "white"};
        `;
            document.body.appendChild(notificationDiv);
            const style = document.createElement("style");
            style.innerHTML = `@keyframes fadeOut { 0%, 90% { opacity: 1; } 100% { opacity: 0; } }`;
            document.head.appendChild(style);
            setTimeout(() => {
            notificationDiv.remove();
            style.remove();
            }, 3000);
        }
        function handleSuccess() {
            window.location.href = "my-ads.php";
        }
        loadCategories();
    });
    </script>
</body>
</html>