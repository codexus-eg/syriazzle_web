const form = document.getElementById("ad-form");
      const previewsContainer = document.getElementById("previews-container");
      const submitBtn = document.getElementById("submit-ad-btn");
      const loadingOverlay = document.getElementById("loading-overlay");

      let selectedFiles = [];
      let choicesInstances = [];
      let phoneInputInstances = {};
      const MAX_FILES = 6;

      async function loadCategories() {
        const res = await fetch("baby-supplies.json");
        const data = await res.json();

        const params = new URLSearchParams(window.location.search);
        const category = params.get("category");
        const sub = params.get("sub");
        const subsub = params.get("subsub");

        const categoryData = data[category];
        const subCategory = categoryData?.subcategories?.[sub];
        const fields =
          subCategory?.subsubcategories?.[subsub]?.fields ||
          subCategory?.fields ||
          [];

        const fixed = [
          {
            name: "الموقع",
            type: "select",
            options: [
              "دمشق",
              "ريف دمشق",
              "حلب",
              "حمص",
              "اللاذقية",
              "طرطوس",
              "حماة",
              "درعا",
              "السويداء",
              "إدلب",
              "الرقة",
              "دير الزور",
              "الحسكة",
              "القنيطرة",
            ],
          },
          { name: "السعر", type: "number", min: 100, max: 9999999 }, // يبقى كما هو، سيتم التعامل معه في renderFields
          { name: "رقم الهاتف", type: "tel" },
          { name: "رقم الواتس", type: "tel" },
          { name: "الصور", type: "file", multiple: true },
          {
            name: "الوصف الإضافي",
            type: "textarea",
            maxLength: 300,
            placeholder: "اكتب وصفًا دقيقًا...",
            required: false,
          },
        ];
        renderFields([...fields, ...fixed]);
      }

      // --- 3. تعديل دالة `renderFields` ---
      function renderFields(fields) {
        form.innerHTML = "";
        choicesInstances = [];
        phoneInputInstances = {};
        fields.forEach((field) => {
          const wrapper = document.createElement("div");
          wrapper.className = "form-group";

          const label = document.createElement("label");
          label.textContent = field.name;
          wrapper.appendChild(label);

          if (field.name === "السعر") {
            const priceGroup = document.createElement("div");
            priceGroup.className = "price-input-group";

            const priceInput = document.createElement("input");
            priceInput.type = "number";
            priceInput.name = "السعر";
            priceInput.required = true;
            if (field.min !== undefined) priceInput.min = field.min;
            if (field.max !== undefined) priceInput.max = field.max;
            priceInput.style.flexGrow = "1";
            priceInput.style.borderTopLeftRadius = "0";
            priceInput.style.borderBottomLeftRadius = "0";

            const currencySelect = document.createElement("select");
            currencySelect.name = "العملة";
            currencySelect.style.width = "auto";
            currencySelect.style.borderRight = "none";
            currencySelect.style.borderTopRightRadius = "0";
            currencySelect.style.borderBottomRightRadius = "0";
            currencySelect.innerHTML = `<option value="ل.س" selected>ل.س</option><option value="$">$</option>`;

            priceGroup.appendChild(currencySelect);
            priceGroup.appendChild(priceInput);
            wrapper.appendChild(priceGroup);
          } else if (
            field.name === "رقم الهاتف" ||
            field.name === "رقم الواتس"
          ) {
            const input = document.createElement("input");
            input.id = `tel-${field.name.replace(/\s+/g, "-")}`;
            input.type = "tel";
            input.name = field.name;
            input.required = field.required !== false;
            wrapper.appendChild(input);
            const iti = window.intlTelInput(input, {
              initialCountry: "sy",
              separateDialCode: true,
              utilsScript:
                "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/utils.js",
              preferredCountries: ["sy", "lb", "jo", "iq", "eg", "sa", "ae"],
              i18n: { country_search_label: "ابحث عن بلد" },
              placeholderNumberType: "MOBILE",
            });
            phoneInputInstances[input.id] = iti;
          } else if (field.type === "file" && field.multiple) {
            const uploaderContainer = document.createElement("div");
            uploaderContainer.className = "custom-image-uploader";
            const fileInput = document.createElement("input");
            fileInput.type = "file";
            fileInput.id = "image-upload-input";
            fileInput.multiple = true;
            fileInput.accept = "image/*";
            fileInput.style.display = "none";
            fileInput.addEventListener("change", handleFileSelect);
            const uploadLabel = document.createElement("label");
            uploadLabel.htmlFor = "image-upload-input";
            uploadLabel.className = "upload-btn-label";
            uploadLabel.textContent = "اختر الصور";
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
              const placeholder = document.createElement("option");
              placeholder.disabled = true;
              placeholder.selected = true;
              placeholder.hidden = true;
              input.appendChild(placeholder);
              if (field.options && Array.isArray(field.options)) {
                field.options.forEach((opt) => {
                  const o = document.createElement("option");
                  o.value = opt;
                  o.textContent = opt;
                  input.appendChild(o);
                });
              }
              const choices = new Choices(input, {
                searchEnabled: true,
                noResultsText: "لا توجد نتائج",
                itemSelectText: "",
              });
              choicesInstances.push(choices);
            } else if (field.type === "textarea") {
              input = document.createElement("textarea");
              input.maxLength = field.maxLength || 300;
              input.placeholder = field.placeholder || "";
            } else {
              input = document.createElement("input");
              input.type = field.type || "text";
              if (field.min !== undefined) input.min = field.min;
              if (field.max !== undefined) input.max = field.max;
              input.placeholder = field.placeholder || "";
            }
            input.name = field.name;
            input.required = field.required !== false;
            input.addEventListener("input", () => {
              const wrapper = input.closest(".form-group");
              wrapper.classList.remove("has-error");
              const err = wrapper.querySelector(".error-message");
              if (err) err.remove();
            });
            wrapper.appendChild(input);
          }
          form.appendChild(wrapper);
        });
        form.appendChild(previewsContainer);
      }

      function handleFileSelect(event) {
        const newFiles = Array.from(event.target.files);
        newFiles.forEach((file) => {
          if (selectedFiles.length >= MAX_FILES) {
            showNotification(
              `لا يمكن تحميل أكثر من ${MAX_FILES} صور.`,
              "error"
            );
            return;
          }
          const isDuplicate = selectedFiles.some(
            (existingFile) =>
              existingFile.name === file.name && existingFile.size === file.size
          );
          if (isDuplicate) {
            showNotification(`الصورة "${file.name}" مضافة بالفعل.`, "warning");
            return;
          }
          selectedFiles.push(file);
        });
        renderImages();
        event.target.value = "";
      }

      function renderImages() {
        previewsContainer.innerHTML = "";
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
            selectedFiles.splice(index, 1);
            renderImages();
          });
          wrapper.appendChild(img);
          wrapper.appendChild(removeBtn);
          previewsContainer.appendChild(wrapper);
        });
      }

      function validateForm() {
            const textData = {};
            const forbiddenWords = ["سياسة", "جنس", "إباحية", "دعارة", "إرهاب", "قتل", "كراهية"];
            let valid = true;

            const allInputs = form.querySelectorAll("input:not([type=file]), textarea, select");

            allInputs.forEach(input => {
                const wrapper = input.closest('.form-group');
                if (!wrapper) return;

                wrapper.classList.remove("has-error");
                const err = wrapper.querySelector(".error-message");
                if (err) err.remove();

                // من شان ما إنسى أنا حضرت جنابي  هاد لا تحذفو مثل العادة هاد من شان تجاهل قيمة العملة في البداية
                if (input.name === "العملة") return;

                // التعامل مع حقول الهاتف
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

            if (selectedFiles.length === 0) {
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
          const urlParams = new URLSearchParams(window.location.search);
          textData["category"] = urlParams.get("category") || "";
          textData["sub"] = urlParams.get("sub") || "";
          textData["subsub"] = urlParams.get("subsub") || "";
          textData["subsubsub"] = urlParams.get("subsubsub") || "";
          finalFormData.append("json_data", JSON.stringify(textData));
          for (const file of selectedFiles) {
            const options = {
              maxSizeMB: 1,
              maxWidthOrHeight: 1920,
              useWebWorker: true,
            };
            const compressedFile = await imageCompression(file, options);
            finalFormData.append(
              "images[]",
              compressedFile,
              compressedFile.name
            );
          }

          const response = await fetch("../php/submit_form.php", {
            method: "POST",
            body: finalFormData,
          });

          const result = await response.json();
          if (result.success) {
            showNotification(
              result.message || "تم نشر الإعلان بنجاح!",
              "success"
            );
            setTimeout(handleSuccess, 1500);
          } else {
            showNotification(
              result.error || "فشل غير معروف في نشر الإعلان.",
              "error"
            );
          }
        } catch (error) {
          console.error("خطأ في إرسال الإعلان:", error);
          showNotification(
            "حدث خطأ غير متوقع أثناء الإرسال: " + error.message,
            "error"
          );
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
        if (isLoading) {
          loadingOverlay.style.display = "flex";
          submitBtn.disabled = true;
          submitBtn.textContent = "جاري الإرسال...";
        } else {
          loadingOverlay.style.display = "none";
          submitBtn.disabled = false;
          submitBtn.textContent = "📤 إرسال الإعلان";
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
        }, 4000);
      }

      function handleSuccess() {
        window.location.href = "../my-ads.html";
      }

      loadCategories();