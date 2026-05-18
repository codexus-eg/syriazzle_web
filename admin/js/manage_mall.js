// ========================================================================
// Syriazzle - Mall Management Logic (Full Production Version)
// ========================================================================

document.addEventListener('DOMContentLoaded', () => {
    
    // --- المتغيرات العامة ---
    let exchangeRate = 15000; // قيمة أولية، سيتم تحديثها من السيرفر
    
    // حالة الجداول (الصفحة الحالية، هل يوجد المزيد؟)
    const state = {
        products: { page: 1, limit: 10000, hasMore: true }, // تم تغيير الرقم هنا
        categories: { page: 1, limit: 10, hasMore: true },
        departments: { page: 1, limit: 20, hasMore: false },
        brands: { page: 1, limit: 10, hasMore: true },
        discounts: { page: 1, limit: 10, hasMore: true }
    };

    // --- التشغيل الأولي ---
    init();

    async function init() {
        await fetchExchangeRate();        // 1. جلب سعر الصرف
        setupTabs();                      // 2. تهيئة التبويبات
        setupModals();                    // 3. تهيئة النوافذ المنبثقة
        loadData('products');             // 4. تحميل المنتجات افتراضياً
        setupDynamicCurrencyCalculator(); // 5. تفعيل حاسبة السعر
        setupImagePreview();              // 6. تفعيل معاينة الصور
        setupSettingsForm();              // 7. تفعيل نموذج الإعدادات
    }

    // ============================================================
    // 1. منطق الصور (المعاينة الفورية)
    // ============================================================
    function setupImagePreview() {
        const input = document.getElementById('product-image');
        const previewImg = document.getElementById('img-preview');
        const placeholder = document.getElementById('preview-placeholder');
        
        if (input && previewImg) {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        previewImg.style.display = 'block';
                        if(placeholder) placeholder.style.display = 'none';
                    }
                    reader.readAsDataURL(file);
                } else {
                    // في حال إلغاء الاختيار، لا نقوم بمسح الصورة القديمة إذا كانت موجودة
                    // إلا إذا أردنا ذلك. هنا سنتركها كما هي في الذاكرة البصرية
                }
            });
        }
    }

    // ============================================================
    // 2. إدارة الأسعار والديناميكية
    // ============================================================
    async function fetchExchangeRate() {
        try {
            const res = await fetch('php/mall_operations.php?action=get_exchange_rate');
            const data = await res.json();
            if(data.success) {
                exchangeRate = parseFloat(data.data.rate);
                
                // تحديث الحقل في تبويب الإعدادات إذا كان موجوداً
                const settingsInput = document.getElementById('exchange-rate');
                if(settingsInput) settingsInput.value = exchangeRate;
            }
        } catch(e) { 
            console.error("Failed to fetch exchange rate", e); 
        }
    }

    function setupDynamicCurrencyCalculator() {
        const usdInput = document.getElementById('product-price-usd');
        
        // إنشاء عنصر لعرض السعر التقريبي
        const hintSpan = document.createElement('small');
        hintSpan.style.color = '#28a745';
        hintSpan.style.display = 'block';
        hintSpan.style.marginTop = '5px';
        hintSpan.style.fontWeight = 'bold';
        hintSpan.id = 'price-hint-calculator';
        
        if(usdInput && !document.getElementById('price-hint-calculator')) {
            usdInput.parentNode.appendChild(hintSpan);
            
            const updateHint = () => {
                const usdVal = parseFloat(usdInput.value) || 0;
                const sypVal = usdVal * exchangeRate;
                hintSpan.textContent = `≈ ${sypVal.toLocaleString('en-US')} ل.س (حسب سعر الصرف الحالي)`;
            };

            usdInput.addEventListener('input', updateHint);
        }
    }

    // ============================================================
    // 3. إدارة الإعدادات (حفظ سعر الصرف)
    // ============================================================
    function setupSettingsForm() {
        const form = document.getElementById('settings-form');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const btn = form.querySelector('.btn-primary');
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'جاري الحفظ...';

                const formData = new FormData(form);
                // Action موجود بالفعل كـ input hidden في HTML، أو نضيفه هنا للأمان
                formData.append('action', 'save_settings'); 

                try {
                    const res = await fetch('php/mall_operations.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await res.json();
                    
                    alert(result.message);
                    
                    if (result.success) {
                        // تحديث المتغير المحلي فوراً
                        const newVal = document.getElementById('exchange-rate').value;
                        exchangeRate = parseFloat(newVal);
                    }
                } catch (error) {
                    console.error(error);
                    alert('حدث خطأ أثناء حفظ الإعدادات.');
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
        }
    }

    // ============================================================
    // 4. إدارة التبويبات (Tabs Navigation)
    // ============================================================
    function setupTabs() {
        // التبويبات الرئيسية
        document.querySelectorAll('.tab-link').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-link').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                const targetId = btn.dataset.tab;
                document.getElementById(targetId).classList.add('active');
                
                // تحميل بيانات الخصومات عند فتح التبويب لأول مرة
                if(targetId === 'discounts-tab') {
                    resetAndLoad('discounts');
                }
            });
        });

        // التبويبات الفرعية
        document.querySelectorAll('.sub-tab-link').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.sub-tab-link').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.sub-tab-content').forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                const target = btn.dataset.subtab;
                document.querySelector(`.sub-tab-content[data-subtab-content="${target}"]`).classList.add('active');
                
                resetAndLoad(target);
            });
        });
    }

    function resetAndLoad(type) {
        const tbody = document.getElementById(`${type}-table-body`);
        if (tbody) tbody.innerHTML = ''; // تنظيف الجدول
        
        state[type].page = 1;
        state[type].hasMore = true;
        
        // إزالة زر "عرض المزيد" القديم
        const oldBtn = document.getElementById(`load-more-${type}`);
        if(oldBtn) oldBtn.remove();

        loadData(type);
    }

    // ============================================================
    // 5. تحميل البيانات (AJAX)
    // ============================================================
    async function loadData(type) {
        const config = state[type];
        if (!config || !config.hasMore) return;

        try {
            const res = await fetch(`php/mall_operations.php?action=fetch_${type}&page=${config.page}&limit=${config.limit}`);
            const result = await res.json();

            if (result.success) {
                renderData(type, result.data.items);
                
                config.hasMore = result.data.has_more;
                config.page++;

                // إضافة أو تحديث زر "عرض المزيد"
                const tableContainer = document.getElementById(`${type}-table-body`).closest('.table-responsive-wrapper');
                manageLoadMoreButton(type, tableContainer);
            }
        } catch (error) {
            console.error("Load Data Error:", error);
        }
    }

    function renderData(type, items) {
        const tbody = document.getElementById(`${type}-table-body`);
        if (!tbody) return;

        items.forEach(item => {
            const tr = document.createElement('tr');
            
            if (type === 'products') {
                const imgSrc = item.image_path ? `../${item.image_path}` : '../image/default.png';
                tr.innerHTML = `
                    <td><img src="${imgSrc}" style="width:50px;height:50px;object-fit:cover;border-radius:5px;border:1px solid #eee;"></td>
                    <td><strong>${item.name}</strong></td>
                    <td>${item.department_name || '<span style="color:#999">-</span>'}</td>
                    <td>${item.category_name || '<span style="color:#999">-</span>'}</td>
                    <td>${item.brand_name || '<span style="color:#999">-</span>'}</td>
                    <td>
                        <span style="color:#007bff;font-weight:bold">$${item.price_usd}</span><br>
                        <small style="color:#555;">${item.display_price}</small>
                    </td>
                    <td>
                        <button class="btn-edit" onclick='openEditProduct(${JSON.stringify(item)})' title="تعديل"><i class="fas fa-edit"></i></button>
                        <button class="btn-delete" onclick='deleteItem("product", ${item.id})' title="حذف"><i class="fas fa-trash"></i></button>
                    </td>
                `;
            } 
            else if (type === 'categories') {
                tr.innerHTML = `
                    <td><strong>${item.name}</strong></td>
                    <td>${item.department_name || '<span style="color:red;">غير محدد</span>'}</td>
                    <td>
                        <button class="btn-edit" onclick='openEditCategory(${JSON.stringify(item)})'><i class="fas fa-edit"></i></button>
                        <button class="btn-delete" onclick='deleteItem("category", ${item.id})'><i class="fas fa-trash"></i></button>
                    </td>
                `;
            } 
            else if (type === 'departments') {
                tr.innerHTML = `
                    <td><strong>${item.name}</strong></td>
                    <td>
                        <button class="btn-edit" onclick='openEditDepartment(${JSON.stringify(item)})'><i class="fas fa-edit"></i></button>
                        <button class="btn-delete" onclick='deleteItem("department", ${item.id})'><i class="fas fa-trash"></i></button>
                    </td>
                `;
            } 
            else if (type === 'brands') {
                const logoSrc = item.logo_path ? `../${item.logo_path}` : '';
                tr.innerHTML = `
                    <td>${logoSrc ? `<img src="${logoSrc}" style="width:40px;height:40px;object-fit:contain;">` : '-'}</td>
                    <td><strong>${item.name}</strong></td>
                    <td>
                        <button class="btn-edit" onclick='openEditBrand(${JSON.stringify(item)})'><i class="fas fa-edit"></i></button>
                        <button class="btn-delete" onclick='deleteItem("brand", ${item.id})'><i class="fas fa-trash"></i></button>
                    </td>
                `;
            } 
            else if (type === 'discounts') {
                const statusBtn = item.is_active == 1
                    ? `<button onclick="toggleDiscountStatus(${item.id})" style="border:1px solid #28a745; color:#28a745; background:#fff; border-radius:15px; padding:2px 10px; font-size:0.8rem;">نشط</button>`
                    : `<button onclick="toggleDiscountStatus(${item.id})" style="border:1px solid #6c757d; color:#6c757d; background:#fff; border-radius:15px; padding:2px 10px; font-size:0.8rem;">متوقف</button>`;

                tr.innerHTML = `
                    <td><strong>${item.name}</strong></td>
                    <td>%${item.discount_percentage}</td>
                    <td>${statusBtn}</td>
                    <td>${item.start_date || '-'}</td>
                    <td>${item.end_date || '-'}</td>
                    <td>
                        <button class="btn-edit" onclick='openEditDiscount(${JSON.stringify(item)})'><i class="fas fa-edit"></i></button>
                        <button class="btn-delete" onclick='deleteItem("discount", ${item.id})'><i class="fas fa-trash"></i></button>
                    </td>
                `;
            }
            
            tbody.appendChild(tr);
        });
    }

    function manageLoadMoreButton(type, container) {
        // إزالة الزر القديم
        const oldBtn = document.getElementById(`load-more-${type}`);
        if(oldBtn) oldBtn.remove();

        // إذا بقي بيانات
        if (state[type].hasMore) {
            const btnDiv = document.createElement('div');
            btnDiv.id = `load-more-${type}`;
            btnDiv.style.textAlign = 'center';
            btnDiv.style.padding = '15px';
            
            btnDiv.innerHTML = `<button class="btn btn-secondary" style="width:200px;">عرض المزيد (${state[type].limit})</button>`;
            btnDiv.querySelector('button').addEventListener('click', () => loadData(type));
            
            container.appendChild(btnDiv);
        }
    }

    // ============================================================
    // 6. إدارة النوافذ والنماذج (Modals & Forms)
    // ============================================================
    function setupModals() {
        // زر الإغلاق
        document.querySelectorAll('.mall-modal-close-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.target.closest('.mall-modal-overlay').style.display = 'none';
            });
        });

        // أزرار "إضافة جديد"
        const addProdBtn = document.getElementById('add-product-btn');
        if(addProdBtn) addProdBtn.onclick = () => openProductModal();
        
        const addCatBtn = document.getElementById('add-category-btn');
        if(addCatBtn) addCatBtn.onclick = () => openCategoryModal();
        
        const addDeptBtn = document.getElementById('add-department-btn');
        if(addDeptBtn) addDeptBtn.onclick = () => openDepartmentModal();
        
        const addBrandBtn = document.getElementById('add-brand-btn');
        if(addBrandBtn) addBrandBtn.onclick = () => openBrandModal();
        
        const addDiscBtn = document.getElementById('add-discount-btn');
        if(addDiscBtn) addDiscBtn.onclick = () => {
            document.getElementById('discount-form').reset();
            document.getElementById('discount-id').value = 0;
            document.getElementById('discount-modal').style.display = 'flex';
        };

        // ربط النماذج (Forms)
        bindForm('product-form', 'save_product', 'products');
        bindForm('category-form', 'save_category', 'categories');
        bindForm('department-form', 'save_department', 'departments');
        bindForm('brand-form', 'save_brand', 'brands');
        bindForm('discount-form', 'save_discount', 'discounts');
    }

    function bindForm(formId, action, refreshType) {
        const form = document.getElementById(formId);
        if(!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const btn = form.querySelector('.btn-submit');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'جاري الحفظ...';

            const formData = new FormData(e.target);
            
            try {
                const res = await fetch(`php/mall_operations.php?action=${action}`, {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                
                alert(result.message);
                
                if(result.success) {
                    form.closest('.mall-modal-overlay').style.display = 'none';
                    resetAndLoad(refreshType);
                }
            } catch(err) {
                console.error(err);
                alert("حدث خطأ في الاتصال.");
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    }

    // ============================================================
    // 7. دوال فتح المودالات وتعبئة البيانات (Exposed to Global)
    // ============================================================

    // --- المنتجات ---
    window.openProductModal = async () => {
        document.getElementById('product-form').reset();
        document.getElementById('product-id').value = 0;
        document.getElementById('product-modal-title').textContent = 'إضافة منتج جديد';
        
        // إخفاء المعاينة
        const preview = document.getElementById('img-preview');
        if(preview) {
            preview.style.display = 'none';
            preview.src = '';
        }
        const placeholder = document.getElementById('preview-placeholder');
        if(placeholder) placeholder.style.display = 'inline';

        await populateSelects();
        document.getElementById('product-modal').style.display = 'flex';
    };

    window.openEditProduct = async (item) => {
        document.getElementById('product-form').reset();
        document.getElementById('product-id').value = item.id;
        document.getElementById('product-name').value = item.name;
        document.getElementById('product-price-usd').value = item.price_usd;
        document.getElementById('product-old-price-usd').value = item.old_price_usd;
        document.getElementById('product-fixed-price').value = item.fixed_price_syp;
        document.getElementById('product-description').value = item.description;
        document.getElementById('existing-image-path').value = item.image_path;
        
        document.getElementById('product-modal-title').textContent = 'تعديل المنتج';

        // عرض الصورة القديمة
        const preview = document.getElementById('img-preview');
        const placeholder = document.getElementById('preview-placeholder');
        if(preview && placeholder) {
            if(item.image_path) {
                preview.src = '../' + item.image_path;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                preview.style.display = 'none';
                placeholder.style.display = 'inline';
            }
        }

        await populateSelects(item.department_id, item.category_id, item.brand_id);
        
        document.getElementById('product-modal').style.display = 'flex';
        
        // تفعيل الحاسبة
        const usdInput = document.getElementById('product-price-usd');
        if(usdInput && usdInput.value) usdInput.dispatchEvent(new Event('input'));
    };

    // دالة التعبئة الذكية للقوائم
    async function populateSelects(sDept, sCat, sBrand) {
        // جلب الأقسام
        const resDept = await fetch('php/mall_operations.php?action=fetch_departments');
        const dataDept = await resDept.json();
        const deptSelect = document.getElementById('department-select');
        deptSelect.innerHTML = '<option value="">-- اختر القسم --</option>';
        dataDept.data.items.forEach(d => {
            deptSelect.innerHTML += `<option value="${d.id}" ${d.id == sDept ? 'selected' : ''}>${d.name}</option>`;
        });

        // جلب الماركات
        const resBrand = await fetch('php/mall_operations.php?action=fetch_brands');
        const dataBrand = await resBrand.json();
        const brandSelect = document.getElementById('brand-select');
        brandSelect.innerHTML = '<option value="">-- اختر الماركة --</option>';
        dataBrand.data.items.forEach(b => {
            brandSelect.innerHTML += `<option value="${b.id}" ${b.id == sBrand ? 'selected' : ''}>${b.name}</option>`;
        });

        // التعامل مع الأصناف
        const catSelect = document.getElementById('category-select');
        const loadCategories = async (deptId) => {
            catSelect.disabled = true;
            catSelect.innerHTML = '<option>جاري التحميل...</option>';
            const resCat = await fetch(`php/mall_operations.php?action=fetch_categories&dept_id=${deptId}`);
            const dataCat = await resCat.json();
            catSelect.innerHTML = '<option value="">-- اختر الصنف --</option>';
            dataCat.data.items.forEach(c => {
                catSelect.innerHTML += `<option value="${c.id}" ${c.id == sCat ? 'selected' : ''}>${c.name}</option>`;
            });
            catSelect.disabled = false;
        };

        deptSelect.onchange = () => {
            if(deptSelect.value) loadCategories(deptSelect.value);
            else { catSelect.innerHTML = ''; catSelect.disabled = true; }
        };

        if(sDept) await loadCategories(sDept);
    }

    // --- الخصومات ---
    window.openEditDiscount = (item) => {
        document.getElementById('discount-id').value = item.id;
        document.getElementById('discount-name').value = item.name;
        document.getElementById('discount-percentage').value = item.discount_percentage;
        // تنسيق التاريخ ليناسب input type=datetime-local (YYYY-MM-DDTHH:MM)
        document.getElementById('start-date').value = item.start_date ? item.start_date.replace(' ', 'T') : '';
        document.getElementById('end-date').value = item.end_date ? item.end_date.replace(' ', 'T') : '';
        document.getElementById('discount-modal').style.display = 'flex';
    };

    window.toggleDiscountStatus = async (id) => {
        try {
            const fd = new FormData();
            fd.append('id', id);
            const res = await fetch('php/mall_operations.php?action=toggle_discount_status', { method: 'POST', body: fd });
            const r = await res.json();
            if(r.success) resetAndLoad('discounts');
            else alert(r.message);
        } catch(e) { alert('خطأ'); }
    };

    // --- الأصناف (Categories) ---
    window.openEditCategory = async (item) => {
        document.getElementById('category-id').value = item.id;
        document.getElementById('category-name').value = item.name;
        
        const res = await fetch('php/mall_operations.php?action=fetch_departments');
        const data = await res.json();
        const select = document.getElementById('category-department-select');
        select.innerHTML = '';
        data.data.items.forEach(d => {
            select.innerHTML += `<option value="${d.id}" ${d.id == item.department_id ? 'selected' : ''}>${d.name}</option>`;
        });
        document.getElementById('category-modal').style.display = 'flex';
    };

    window.openCategoryModal = async () => {
        document.getElementById('category-form').reset();
        document.getElementById('category-id').value = 0;
        
        const res = await fetch('php/mall_operations.php?action=fetch_departments');
        const data = await res.json();
        const select = document.getElementById('category-department-select');
        select.innerHTML = '<option value="">اختر القسم</option>';
        data.data.items.forEach(d => select.innerHTML += `<option value="${d.id}">${d.name}</option>`);
        document.getElementById('category-modal').style.display = 'flex';
    };

    // --- الأقسام (Departments) ---
    window.openEditDepartment = (item) => {
        document.getElementById('department-id').value = item.id;
        document.getElementById('department-name').value = item.name;
        document.getElementById('department-modal').style.display = 'flex';
    };
    window.openDepartmentModal = () => {
        document.getElementById('department-form').reset();
        document.getElementById('department-id').value = 0;
        document.getElementById('department-modal').style.display = 'flex';
    };

    // --- الماركات (Brands) ---
    window.openEditBrand = (item) => {
        document.getElementById('brand-id').value = item.id;
        document.getElementById('brand-name').value = item.name;
        document.getElementById('existing-brand-logo-path').value = item.logo_path;
        document.getElementById('brand-modal').style.display = 'flex';
    };
    window.openBrandModal = () => {
        document.getElementById('brand-form').reset();
        document.getElementById('brand-id').value = 0;
        document.getElementById('brand-modal').style.display = 'flex';
    };

    // --- الحذف العام ---
    window.deleteItem = async (type, id) => {
        if(!confirm("هل أنت متأكد تماماً من الحذف؟")) return;
        
        try {
            const fd = new FormData();
            fd.append('id', id);
            const res = await fetch(`php/mall_operations.php?action=delete_${type}`, { method: 'POST', body: fd });
            const result = await res.json();
            
            alert(result.message);
            
            if(result.success) {
                // إعادة التحميل (products, categories, etc)
                // نحتاج لإضافة 's' للجمع ما عدا category
                let refreshTarget = type + 's';
                if(type === 'category') refreshTarget = 'categories'; 
                resetAndLoad(refreshTarget);
            }
        } catch(e) { console.error(e); }
    };

});