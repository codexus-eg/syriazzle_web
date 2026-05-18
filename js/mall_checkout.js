// ========================================================================
// Syriazzle Mall - Checkout Logic (Fixed & Secure)
// ========================================================================

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. التحقق من السلة
    let mallCart = [];
    try {
        mallCart = JSON.parse(localStorage.getItem('mall_cart')) || [];
    } catch (e) { mallCart = []; }

    // إذا كانت فارغة، أظهر رسالة وتوقف
    if (mallCart.length === 0) {
        document.querySelector('.checkout-container').innerHTML = 
            '<div style="text-align:center; padding:50px; color:#777;">' +
            '<i class="fas fa-shopping-basket" style="font-size:4rem; margin-bottom:15px;"></i>' +
            '<h3>سلتك فارغة!</h3>' +
            '<a href="mall.php" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#e60000; color:#fff; text-decoration:none; border-radius:5px;">العودة للتسوق</a></div>';
        document.querySelector('.checkout-footer').style.display = 'none';
        return;
    }

    // 2. تعريف العناصر (DOM Elements)
    const dom = {
        form: document.getElementById('mall-checkout-form'),
        summaryContainer: document.getElementById('order-summary-container'),
        itemsPriceEl: document.getElementById('summary-items-price'),
        deliveryFeeEl: document.getElementById('summary-delivery-fee'),
        totalPriceEl: document.getElementById('summary-total-price'),
        promoLine: document.getElementById('promo-line'),
        promoDiscountEl: document.getElementById('summary-promo-discount'),
        promoInput: document.getElementById('promo-input'),
        promoBtn: document.getElementById('apply-promo-btn'),
        promoFeedback: document.getElementById('promo-feedback'),
        submitBtn: document.getElementById('submit-order-btn'),
        addressDisplay: document.getElementById('address-display'),
        // عناصر الخريطة
        mapModal: document.getElementById('map-modal'),
        closeMapBtn: document.getElementById('close-map-btn'),
        confirmAddressBtn: document.getElementById('confirm-address-btn'),
        modalAddressText: document.getElementById('modal-address-text'),
        myLocationBtn: document.getElementById('my-location-btn')
    };

    const state = {
        itemsTotalPrice: 0,
        deliveryFee: 0,
        promoDiscount: 0,
        appliedPromoCode: '',
        selectedLocation: null,
        isLocationInService: false,
        isCalculating: false
    };
    
    let map = null;
    let mapTimeout;

    // 3. بدء التشغيل
    initPage();

    function initPage() {
        // حساب السعر الأولي للمنتجات
        calculateInitialPrice();
        
        // التحقق مما إذا كان هناك عنوان محفوظ
        if (CHECKOUT_DATA.userAddresses && CHECKOUT_DATA.userAddresses.length > 0) {
            // نأخذ العنوان الأول (الافتراضي أو الأحدث)
            const savedAddr = CHECKOUT_DATA.userAddresses[0];
            
            // نملأ البيانات فوراً لكي لا يظهر البوكس فارغاً
            state.selectedLocation = {
                lat: parseFloat(savedAddr.latitude),
                lon: parseFloat(savedAddr.longitude),
                details: savedAddr.address_details
            };
            
            // نعرض البطاقة (بدون سعر مؤقتاً)
            renderAddressDisplay();
            
            // نحسب السعر في الخلفية
            checkDeliveryFee(state.selectedLocation.lat, state.selectedLocation.lon);
            
        } else {
            // لا يوجد عنوان -> اعرض زر "أضف عنوان" فوراً
            renderAddressDisplay();
        }
    }

    // --- الدوال الأساسية ---

    function calculateInitialPrice() {
        state.itemsTotalPrice = mallCart.reduce((total, item) => total + (item.price * item.quantity), 0);
        
        // رسم قائمة المنتجات
        dom.summaryContainer.innerHTML = mallCart.map(item => `
            <div class="summary-line" style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem; color:#555;">
                <span>${item.quantity} x ${item.name}</span>
                <span style="font-weight:bold;">${(item.price * item.quantity).toLocaleString('en-US')}</span>
            </div>
        `).join('');
        
        updateInvoice();
    }

    function updateInvoice() {
        const finalTotal = state.itemsTotalPrice - state.promoDiscount + state.deliveryFee;
        
        dom.itemsPriceEl.textContent = `${state.itemsTotalPrice.toLocaleString('en-US')} ل.س`;
        
        // حالة التوصيل
        if (state.isCalculating) {
            dom.deliveryFeeEl.innerHTML = '<span style="color:#888;">جاري الحساب...</span>';
            dom.totalPriceEl.innerHTML = '---';
            dom.submitBtn.disabled = true;
            dom.submitBtn.textContent = 'انتظر قليلاً...';
        } else {
            dom.deliveryFeeEl.textContent = state.isLocationInService ? `${state.deliveryFee.toLocaleString('en-US')} ل.س` : '---';
            dom.totalPriceEl.textContent = `${finalTotal.toLocaleString('en-US')} ل.س`;
            
            // تحديث الزر
            if (state.selectedLocation && state.isLocationInService) {
                dom.submitBtn.disabled = false;
                dom.submitBtn.textContent = 'تأكيد الطلب';
                dom.submitBtn.style.opacity = '1';
                dom.submitBtn.style.cursor = 'pointer';
            } else {
                dom.submitBtn.disabled = true;
                dom.submitBtn.textContent = state.selectedLocation ? 'خارج منطقة الخدمة' : 'الرجاء تحديد الموقع';
                dom.submitBtn.style.opacity = '0.6';
                dom.submitBtn.style.cursor = 'not-allowed';
            }
        }

        // الخصم
        if (state.promoDiscount > 0) {
            dom.promoDiscountEl.textContent = `- ${state.promoDiscount.toLocaleString('en-US')} ل.س`;
            dom.promoLine.style.display = 'flex';
        } else {
            dom.promoLine.style.display = 'none';
        }
    }

    function renderAddressDisplay() {
        if (state.selectedLocation) {
            // تم تحديد عنوان
            dom.addressDisplay.innerHTML = `
                <div class="address-card selected" style="border:1px solid #28a745; background:#f9fff9; padding:15px; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px; color:#28a745; margin-bottom:5px;">
                            <i class="fas fa-map-marker-alt"></i> 
                            <h4 style="margin:0; font-size:1rem;">موقع محدد</h4>
                        </div>
                        <p style="margin:0; font-size:0.85rem; color:#666;">${state.selectedLocation.details}</p>
                        <small style="color:#888;">التوصيل: ${state.deliveryFee > 0 ? state.deliveryFee.toLocaleString() + ' ل.س' : 'جاري الحساب...'}</small>
                    </div>
                    <button type="button" class="change-address-btn" style="background:#fff; border:1px solid #ddd; padding:8px 15px; border-radius:20px; cursor:pointer; font-size:0.85rem;">تغيير</button>
                </div>`;
        } else {
            // لا يوجد عنوان
            dom.addressDisplay.innerHTML = `
                <button type="button" class="add-address-btn" style="width:100%; padding:20px; border:2px dashed #ccc; background:#fafafa; border-radius:10px; color:#666; cursor:pointer; font-size:1rem; display:flex; flex-direction:column; align-items:center; gap:10px;">
                    <i class="fas fa-map-marked-alt" style="font-size:1.5rem;"></i>
                    <span>اضغط هنا لتحديد موقع التوصيل</span>
                </button>`;
        }
        
        // إعادة ربط زر الفتح
        const btn = dom.addressDisplay.querySelector('button');
        if(btn) btn.addEventListener('click', openMapModal);
    }

    // --- الخريطة (OpenStreetMap) ---
    function openMapModal() {
        dom.mapModal.style.display = 'flex';
        setTimeout(() => {
            if (!map) initMap();
            map.invalidateSize();
        }, 100);
    }

    function initMap() {
        // إحداثيات دمشق الافتراضية
        const defaultLat = 33.5138;
        const defaultLng = 36.2765;

        map = L.map('map-container').setView([defaultLat, defaultLng], 13);
        
        // استخدام OSM المجانية
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // إضافة البحث
        const provider = new GeoSearch.OpenStreetMapProvider({ params: { countrycodes: 'sy' } });
        const searchControl = new GeoSearch.GeoSearchControl({
            provider: provider,
            style: 'bar',
            showMarker: false, // الماركر الثابت هو الأساس
            autoClose: true,
            searchLabel: 'ابحث عن منطقتك...'
        });
        map.addControl(searchControl);

        // عند البحث
        map.on('geosearch/showlocation', (result) => {
            map.setView([result.location.y, result.location.x], 16);
        });

        // عند التحريك (تحديث السعر)
        map.on('moveend', () => {
            clearTimeout(mapTimeout);
            mapTimeout = setTimeout(async () => {
                const center = map.getCenter();
                dom.modalAddressText.textContent = 'جاري التحقق من المنطقة...';
                dom.confirmAddressBtn.disabled = true;
                
                const result = await getFeeFromServer(center.lat, center.lng);
                
                if (result.success) {
                    dom.modalAddressText.innerHTML = `التوصيل: <b style="color:#28a745">${result.delivery_fee.toLocaleString()} ل.س</b> (${result.zone_name})`;
                    dom.confirmAddressBtn.disabled = false;
                    dom.confirmAddressBtn.dataset.fee = result.delivery_fee;
                } else {
                    dom.modalAddressText.innerHTML = `<span style="color:red">${result.message || 'خارج منطقة الخدمة'}</span>`;
                    dom.confirmAddressBtn.disabled = true;
                }
            }, 600);
        });
        
        // محاولة جلب الموقع الحالي للمستخدم
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                map.flyTo([pos.coords.latitude, pos.coords.longitude], 16);
            });
        }
    }

    // دالة الاتصال بالسيرفر لحساب السعر
    async function getFeeFromServer(lat, lon) {
        try {
            const res = await fetch('php/ajax_calculate_delivery_fee.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ latitude: lat, longitude: lon })
            });
            return await res.json();
        } catch(e) { return { success: false, message: 'خطأ اتصال' }; }
    }

    // دالة مساعدة للتحقق من السعر في الخلفية (عند تحميل عنوان محفوظ)
    async function checkDeliveryFee(lat, lon) {
        state.isCalculating = true;
        updateInvoice();
        
        const result = await getFeeFromServer(lat, lon);
        
        state.isCalculating = false;
        if (result.success) {
            state.deliveryFee = result.delivery_fee;
            state.isLocationInService = true;
        } else {
            state.deliveryFee = 0;
            state.isLocationInService = false;
        }
        
        renderAddressDisplay(); // لتحديث السعر الظاهر في البطاقة
        updateInvoice();
    }

    // --- الأزرار والأحداث ---

    dom.closeMapBtn.addEventListener('click', () => dom.mapModal.style.display = 'none');
    
    dom.myLocationBtn.addEventListener('click', () => {
        if(navigator.geolocation) navigator.geolocation.getCurrentPosition(p => map.flyTo([p.coords.latitude, p.coords.longitude], 16));
    });

    // زر تأكيد الموقع في الخريطة
    dom.confirmAddressBtn.addEventListener('click', () => {
        const center = map.getCenter();
        const fee = parseFloat(dom.confirmAddressBtn.dataset.fee || 0);
        
        const details = prompt("أدخل تفاصيل العنوان (رقم البناء، الطابق، علامة مميزة):");
        if (!details) return;

        state.selectedLocation = { lat: center.lat, lon: center.lng, details: details };
        state.deliveryFee = fee;
        state.isLocationInService = true;
        
        dom.mapModal.style.display = 'none';
        renderAddressDisplay();
        updateInvoice();
    });

    // كود الحسم
    dom.promoBtn.addEventListener('click', async () => {
        const code = dom.promoInput.value.trim();
        if (!code) return;
        
        dom.promoBtn.disabled = true;
        dom.promoFeedback.textContent = 'جاري التحقق...';
        dom.promoFeedback.className = 'promo-feedback';
        
        try {
            const res = await fetch('php/ajax_validate_promo.php', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ promo_code: code, items_total: state.itemsTotalPrice })
            });
            const data = await res.json();
            
            if(data.success) {
                state.promoDiscount = data.data.discount_amount;
                state.appliedPromoCode = code;
                dom.promoFeedback.textContent = data.message;
                dom.promoFeedback.classList.add('success');
                dom.promoInput.disabled = true;
            } else {
                state.promoDiscount = 0;
                dom.promoFeedback.textContent = data.message;
                dom.promoFeedback.classList.add('error');
            }
        } catch(e) {
            dom.promoFeedback.textContent = 'خطأ في الاتصال';
        }
        dom.promoBtn.disabled = false;
        updateInvoice();
    });

    // إرسال الطلب
    dom.form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (dom.submitBtn.disabled) return;

        dom.submitBtn.disabled = true;
        dom.submitBtn.textContent = 'جاري الإرسال...';

        const fd = new FormData();
        fd.append('customer_name', document.getElementById('customer_name').value);
        fd.append('customer_phone', document.getElementById('customer_phone').value);
        fd.append('address_details', state.selectedLocation.details);
        fd.append('latitude', state.selectedLocation.lat);
        fd.append('longitude', state.selectedLocation.lon);
        fd.append('promo_code', state.appliedPromoCode);
        fd.append('mall_cart', JSON.stringify(mallCart));

        try {
            const res = await fetch('php/place_mall_order.php', { method:'POST', body:fd });
            const data = await res.json();
            
            if(data.success) {
                localStorage.removeItem('mall_cart');
                // نجاح! توجه لصفحة الطلبات
                window.location.href = 'mall_orders.php';
            } else {
                alert("عذراً: " + data.message);
                dom.submitBtn.disabled = false;
                dom.submitBtn.textContent = 'تأكيد الطلب';
            }
        } catch(e) {
            alert('خطأ في الاتصال بالخادم');
            dom.submitBtn.disabled = false;
            dom.submitBtn.textContent = 'تأكيد الطلب';
        }
    });

});