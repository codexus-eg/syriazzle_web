// ========================================================================
// Syriazzle - Checkout Logic (Multi-Currency Support - Final v1.0)
// ========================================================================

(() => {
    // التحقق من وجود البيانات الأساسية من PHP
    if (typeof CHECKOUT_DATA === 'undefined') {
        console.error("Checkout data missing.");
        return;
    }

    // استخراج البيانات بما فيها العملة
    const { businessId, userAddresses, businessLocation, currencyCode, currencySymbol } = CHECKOUT_DATA;
    
    // مفتاح API للخرائط (يمكن تغييره لاحقاً)
    const ORS_API_KEY = 'eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImViYzQxYzEyYjM0MzQxYzViODYxM2Q1YjNlMWRkZjI4IiwiaCI6Im11cm11cjY0In0=';
    
    // جلب السلة
    let cart = [];
    try { cart = JSON.parse(localStorage.getItem(`cart_${businessId}`)) || []; } catch(e) { cart = []; }
    
    // حالة الصفحة
    const state = {
        itemsTotal: 0,       // بعملة المتجر (دولار أو ليرة)
        deliveryFee: 0,      // دائماً بالليرة السورية
        tipAmount: 0,        // دائماً بالليرة السورية
        promoDiscount: 0,    // بعملة المتجر
        appliedPromoCode: null,
        selectedAddress: null,
        isCalculatingFee: false,
        deliveryTimePref: 'asap',
        scheduledTime: null,
        paymentMethod: 'cash'
    };
    
    // عناصر الواجهة
    const dom = {
        pageContainer: document.querySelector('.checkout-container'),
        pageFooter: document.querySelector('.checkout-footer'),
        summaryItemsPrice: document.getElementById('summary-items-price'),
        summaryDeliveryFee: document.getElementById('summary-delivery-fee'),
        summaryTipAmount: document.getElementById('summary-tip-amount'),
        summaryPromoDiscount: document.getElementById('summary-promo-discount'),
        promoLine: document.getElementById('promo-line'),
        summaryTotalPrice: document.getElementById('summary-total-price'),
        footerTotalPrice: document.getElementById('footer-total-price'),
        addressDisplay: document.getElementById('address-display'),
        promoInput: document.getElementById('promo-input'),
        applyPromoBtn: document.getElementById('apply-promo-btn'),
        promoFeedback: document.getElementById('promo-feedback'),
        submitBtn: document.getElementById('submit-order-btn'),
        tipOptions: document.getElementById('tip-options'),
        paymentOptions: document.querySelector('.payment-options'),
        timeOptions: document.querySelector('.delivery-time-options'),
        scheduledTimeDisplay: document.getElementById('scheduled-time-display'),
        // Map Elements
        mapModal: document.getElementById('map-modal'),
        closeMapBtn: document.getElementById('close-map-btn'),
        confirmAddressBtn: document.getElementById('confirm-address-btn'),
        modalAddressText: document.getElementById('modal-address-text'),
        myLocationBtn: document.getElementById('my-location-btn'),
    };
    
    let map = null;
    let mapUpdateTimeout = null;

    // --- دالة تنسيق الأرقام ---
    function formatPrice(number, isSYP = false) {
        const num = parseFloat(number);
        if (isNaN(num)) return '0';
        
        // إذا كان المبلغ بالليرة، نستخدم تنسيق بدون كسور عادة
        // إذا كان بالدولار، نستخدم كسرين عشريين
        const decimals = (isSYP || currencyCode === 'SYP') ? 0 : 2;
        
        return num.toLocaleString('en-US', { 
            minimumFractionDigits: decimals, 
            maximumFractionDigits: decimals 
        });
    }

    // --- 1. الحساب والعرض (المنطق الذكي للعملات) ---

    function updateInvoice() {
        // 1. صافي قيمة المنتجات (بعملة المتجر)
        const netItemsTotal = Math.max(0, state.itemsTotal - state.promoDiscount);
        
        // 2. مجموع الخدمات (توصيل + إكرامية) - دائماً بالليرة السورية
        const servicesTotalSYP = state.deliveryFee + state.tipAmount;

        // تحديث واجهة المجموع الفرعي
        dom.summaryItemsPrice.textContent = `${formatPrice(state.itemsTotal)} ${currencySymbol}`;
        
        // تحديث واجهة الخصم
        if (state.promoDiscount > 0) {
            dom.promoLine.style.display = 'flex';
            dom.summaryPromoDiscount.textContent = `- ${formatPrice(state.promoDiscount)} ${currencySymbol}`;
        } else {
            dom.promoLine.style.display = 'none';
        }

        // تحديث واجهة رسوم التوصيل (دائماً ل.س)
        if (state.isCalculatingFee) {
            dom.summaryDeliveryFee.innerHTML = '<div class="skeleton skeleton-text"></div>';
            // في حالة الحساب، نخفي المجموع النهائي مؤقتاً
            const skeletonHTML = '<div class="skeleton skeleton-text" style="width:120px;"></div>';
            dom.summaryTotalPrice.innerHTML = skeletonHTML;
            dom.footerTotalPrice.innerHTML = skeletonHTML;
            dom.submitBtn.disabled = true;
            dom.submitBtn.textContent = 'جاري حساب التوصيل...';
            return; 
        } else {
            const deliveryText = state.deliveryFee > 0 ? `${state.deliveryFee.toLocaleString()} ل.س` : '0 ل.س';
            dom.summaryDeliveryFee.textContent = deliveryText;
        }

        // تحديث واجهة الإكرامية (دائماً ل.س)
        dom.summaryTipAmount.textContent = `${state.tipAmount.toLocaleString()} ل.س`;

        // === المنطق الجوهري للمجموع النهائي ===
        let grandTotalHTML = '';

        if (currencyCode === 'USD') {
            // الحالة: المتجر بالدولار، والخدمات بالليرة
            // النتيجة: عرض منفصل (مثلاً: 20 $ + 5000 ل.س)
            const usdPart = `${formatPrice(netItemsTotal)} ${currencySymbol}`;
            
            if (servicesTotalSYP > 0) {
                // إذا كان هناك رسوم توصيل أو إكرامية
                const sypPart = `${servicesTotalSYP.toLocaleString()} ل.س`;
                grandTotalHTML = `<span style="font-size: 1.1em;">${usdPart}</span> <span style="font-size: 0.8em; color: #555;">+ ${sypPart} (خدمات)</span>`;
            } else {
                // فقط سعر المنتجات
                grandTotalHTML = usdPart;
            }
        } else {
            // الحالة: المتجر بالليرة، والخدمات بالليرة
            // النتيجة: جمع مباشر (مثلاً: 15000 ل.س)
            const totalSYP = netItemsTotal + servicesTotalSYP;
            grandTotalHTML = `${totalSYP.toLocaleString()} ل.س`;
        }

        dom.summaryTotalPrice.innerHTML = grandTotalHTML;
        dom.footerTotalPrice.innerHTML = grandTotalHTML;
        
        // إعادة تفعيل الزر إذا كان كل شيء جاهزاً
        if (state.selectedAddress && !state.isCalculatingFee && state.deliveryFee >= 0) {
             // نتحقق إذا كان خارج النطاق (deliveryFee == 0 قد يعني مجاني أو خطأ، يعتمد على الرد)
             // لكن الزر يتم التحكم به في updateDeliveryDetails أيضاً
        }
    }

    async function updateDeliveryDetails(address) {
        state.selectedAddress = address;
        renderSelectedAddress(address);
        
        if (!address) {
            state.deliveryFee = 0;
            updateInvoice();
            dom.submitBtn.disabled = true;
            dom.submitBtn.textContent = 'اختر عنواناً أولاً';
            return;
        }

        state.isCalculatingFee = true;
        updateInvoice(); // ليظهر السكيلتون

        const customerCoords = { lat: parseFloat(address.latitude), lon: parseFloat(address.longitude) };
        const details = await getDeliveryDetailsFromServer(businessLocation, customerCoords);

        if (details && details.status === 'in_service') {
            state.deliveryFee = details.total_fee;
            dom.submitBtn.disabled = false;
            dom.submitBtn.textContent = 'إرسال الطلب';
        } else { 
            state.deliveryFee = 0;
            dom.submitBtn.disabled = true;
            dom.submitBtn.textContent = (details && details.message) ? details.message : 'خارج منطقة الخدمة'; 
        }
        
        state.isCalculatingFee = false;
        updateInvoice();
    }
    
    function renderSelectedAddress(address) {
        if (!address) {
            dom.addressDisplay.innerHTML = `
                <div id="address-display-placeholder" style="cursor:pointer; border:2px dashed #ccc; padding:20px; text-align:center; border-radius:12px; background:#f9f9f9;">
                    <h4 style="margin:0 0 5px; color:#555;"><i class="fas fa-plus-circle"></i> أضف عنوان توصيل</h4>
                    <p style="margin:0; font-size:0.9rem; color:#888;">اضغط هنا لتحديد موقعك على الخريطة</p>
                </div>`;
            dom.addressDisplay.querySelector('#address-display-placeholder').addEventListener('click', openMapModal);
        } else {
            dom.addressDisplay.innerHTML = `
                <div class="address-card selected">
                    <div class="map-thumb"><i class="fas fa-map-pin"></i></div>
                    <div class="address-info">
                        <h4>${address.address_name}</h4>
                        <p>${address.address_details}</p>
                    </div>
                    <button type="button" class="change-address-btn">تغيير</button>
                </div>`;
            dom.addressDisplay.querySelector('.change-address-btn').addEventListener('click', openMapModal);
        }
    }

    // --- 2. الخريطة وحساب التوصيل ---
    
    async function getDeliveryDetailsFromServer(start, end) {
        try {
            const fd = new FormData();
            fd.append('start_lat', start.lat); fd.append('start_lon', start.lon);
            fd.append('end_lat', end.lat); fd.append('end_lon', end.lon);
            
            const res = await fetch('php/calculate_delivery.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (!data.success || data.status === 'out_of_service') {
                return { status: 'out_of_service', total_fee: 0, message: data.message || 'خارج المنطقة' };
            }
            return { status: 'in_service', total_fee: parseFloat(data.total_fee), message: 'داخل الخدمة' };
        } catch (e) { 
            return { status: 'error', total_fee: 0, message: 'فشل الاتصال' }; 
        }
    }

    function reverseGeocode(coords) {
        return new Promise((resolve) => {
            const timer = setTimeout(() => resolve(null), 2500);
            const url = `https://api.openrouteservice.org/geocode/reverse?api_key=${ORS_API_KEY}&point.lon=${coords.lng}&point.lat=${coords.lat}`;
            fetch(url).then(r => r.json()).then(d => {
                clearTimeout(timer);
                resolve(d.features?.[0]?.properties?.label);
            }).catch(() => { clearTimeout(timer); resolve(null); });
        });
    }

    function initMap(coords) {
        const removeLoader = () => {
            const loader = document.getElementById('map-loader-overlay');
            if(loader) loader.classList.add('hidden');
        };

        if (map) {
            map.setView(coords, 17);
            setTimeout(() => { map.invalidateSize(); removeLoader(); }, 300);
            return;
        }
        
        map = L.map('map-container', { zoomControl: false }).setView(coords, 17);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM', maxZoom: 19 }).addTo(map);

        map.on('moveend', () => {
            dom.confirmAddressBtn.disabled = false;
            dom.modalAddressText.textContent = "جاري جلب اسم الشارع...";
            
            clearTimeout(mapUpdateTimeout);
            mapUpdateTimeout = setTimeout(async () => {
                const center = map.getCenter();
                const name = await reverseGeocode(center);
                dom.modalAddressText.textContent = name || "موقع محدد على الخريطة";
            }, 100);
        });

        const provider = new GeoSearch.OpenStreetMapProvider({ params: { 'accept-language': 'ar', countrycodes: 'sy' } });
        const searchControl = new GeoSearch.GeoSearchControl({
            provider: provider, style: 'bar', showMarker: false, autoClose: true, searchLabel: 'بحث...'
        });
        map.addControl(searchControl);
        setTimeout(removeLoader, 500);
    }

    async function openMapModal() {
        dom.mapModal.style.display = 'flex';
        
        let loader = document.getElementById('map-loader-overlay');
        if(!loader) {
            loader = document.createElement('div'); loader.id = 'map-loader-overlay'; loader.className = 'map-loader-overlay';
            loader.innerHTML = `<div class="loader-spinner"></div><p style="margin-top:10px;font-weight:600;color:#555">جاري التحميل...</p>`;
            document.getElementById('map-container').appendChild(loader);
        }
        loader.classList.remove('hidden');

        let initialCoords = { lat: 33.5138, lng: 36.2765 }; 

        if (state.selectedAddress) {
            initialCoords = { lat: state.selectedAddress.latitude, lng: state.selectedAddress.longitude };
            initMap(initialCoords);
        } else {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        initialCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                        initMap(initialCoords);
                    },
                    () => { initMap(initialCoords); },
                    { timeout: 3000 }
                );
            } else { initMap(initialCoords); }
        }
        dom.confirmAddressBtn.disabled = false;
    }

    // --- 3. التفاعلات (أكواد الحسم والطلب) ---

    async function applyPromoCode() {
        const code = dom.promoInput.value.trim();
        if (!code) return;
        dom.promoFeedback.textContent = '...';
        dom.applyPromoBtn.disabled = true;

        const formData = new FormData();
        formData.append('promo_code', code);
        formData.append('items_total', state.itemsTotal);
        // نمرر العملة للباك إند للتحقق (اختياري لكن جيد)
        formData.append('currency', currencyCode);

        try {
            const response = await fetch('php/apply_promo.php', { method: 'POST', body: formData });
            const data = await response.json();
            dom.promoFeedback.textContent = data.message;
            dom.promoFeedback.classList.add(data.success ? 'success' : 'error');
            if (data.success) {
                state.promoDiscount = parseFloat(data.discount_amount);
                state.appliedPromoCode = data.code;
                dom.promoInput.disabled = true;
                dom.applyPromoBtn.textContent = 'تم';
            } else {
                state.promoDiscount = 0;
                state.appliedPromoCode = null;
                dom.applyPromoBtn.disabled = false;
            }
            updateInvoice();
        } catch(e) {
            dom.promoFeedback.textContent = 'خطأ';
            dom.applyPromoBtn.disabled = false;
        }
    }

    function setupEventListeners() {
        dom.tipOptions.addEventListener('click', e => {
            if (e.target.matches('.tip-btn')) {
                dom.tipOptions.querySelectorAll('.tip-btn').forEach(btn => btn.classList.remove('selected'));
                e.target.classList.add('selected');
                // الإكرامية دائماً بالليرة، لذا نأخذ القيمة كما هي
                state.tipAmount = parseFloat(e.target.dataset.tip);
                updateInvoice();
            }
        });

        dom.paymentOptions.addEventListener('click', e => {
            const opt = e.target.closest('.option-box:not([disabled])');
            if (opt) {
                dom.paymentOptions.querySelectorAll('.option-box').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                state.paymentMethod = opt.dataset.method;
            }
        });

        dom.confirmAddressBtn.addEventListener('click', () => {
            const finalCoords = map.getCenter();
            let details = dom.modalAddressText.textContent;
            if (details.includes("جاري")) details = "موقع محدد على الخريطة";

            const newAddress = {
                address_name: 'موقع محدد',
                address_details: details,
                latitude: finalCoords.lat,
                longitude: finalCoords.lng,
            };
            updateDeliveryDetails(newAddress);
            dom.mapModal.style.display = 'none';
        });

        dom.closeMapBtn.addEventListener('click', () => dom.mapModal.style.display = 'none');
        
        dom.myLocationBtn.addEventListener('click', () => {
            if(navigator.geolocation) {
                const loader = document.getElementById('map-loader-overlay');
                if(loader) loader.classList.remove('hidden');
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                        if(loader) loader.classList.add('hidden');
                    },
                    () => { 
                        if(loader) loader.classList.add('hidden'); 
                        alert("يرجى تفعيل الموقع"); 
                    }
                );
            }
        });
        
        dom.applyPromoBtn.addEventListener('click', applyPromoCode);
        dom.submitBtn.addEventListener('click', placeOrder);
        setupTimePicker();
    }

    function setupTimePicker() {
        const fpInstance = flatpickr(dom.timeOptions, {
            enableTime: true, minDate: "today", minuteIncrement: 15, locale: "ar", disableMobile: "true",
            onOpen: (sel, str, inst) => {
                const now = new Date();
                const isToday = (inst.selectedDates.length > 0 && inst.selectedDates[0].toDateString() === now.toDateString()) || inst.selectedDates.length === 0;
                if (isToday) {
                    const min = new Date(now.getTime() + 60*60000);
                    inst.set('minTime', `${min.getHours()}:${min.getMinutes()}`);
                } else inst.set('minTime', null);
            },
            onClose: (sel) => {
                if (sel.length > 0) {
                    state.scheduledTime = sel[0];
                    dom.scheduledTimeDisplay.textContent = `الموعد: ${state.scheduledTime.toLocaleString('ar-EG')}`;
                    dom.scheduledTimeDisplay.style.display = 'block';
                }
            }
        });

        dom.timeOptions.addEventListener('click', e => {
            if (e.target.matches('.time-option-btn')) {
                dom.timeOptions.querySelectorAll('.time-option-btn').forEach(b => b.classList.remove('selected'));
                e.target.classList.add('selected');
                state.deliveryTimePref = e.target.dataset.timePref;
                if (state.deliveryTimePref === 'scheduled') fpInstance.open();
                else { dom.scheduledTimeDisplay.style.display = 'none'; state.scheduledTime = null; }
            }
        });
    }
    
    async function placeOrder() {
        if (dom.submitBtn.disabled) return;
        dom.submitBtn.disabled = true;
        dom.submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';

        const formData = new FormData();
        formData.append('business_id', businessId);
        formData.append('cart_data', JSON.stringify(cart));
        // نرسل القيم منفصلة للباك إند
        formData.append('delivery_fee', state.deliveryFee); // ليرة
        formData.append('tip_amount', state.tipAmount); // ليرة
        formData.append('payment_method', state.paymentMethod);
        formData.append('latitude', state.selectedAddress.latitude);
        formData.append('longitude', state.selectedAddress.longitude);
        formData.append('delivery_address_details', state.selectedAddress.address_details);
        formData.append('delivery_time_preference', state.deliveryTimePref);
        formData.append('promo_code', state.appliedPromoCode || '');
        
        // العملة مهمة جداً للباك إند ليعرف كيف يخزن القيم
        formData.append('currency', currencyCode);
        
        if (state.deliveryTimePref === 'scheduled' && state.scheduledTime) {
            const d = state.scheduledTime;
            const iso = new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 19).replace('T', ' ');
            formData.append('scheduled_delivery_time', iso);
        }
        
        try {
            const response = await fetch('php/place_order.php', { method: 'POST', body: formData });
            const data = await response.json();
            if(data.success) {
                localStorage.removeItem(`cart_${businessId}`);
                window.location.href = `track_order.php?order_id=${data.order_id}`;
            } else {
                alert('عذراً: ' + data.message);
                dom.submitBtn.disabled = false;
                dom.submitBtn.textContent = 'إرسال الطلب';
            }
        } catch (error) {
            alert('خطأ في الاتصال');
            dom.submitBtn.disabled = false;
            dom.submitBtn.textContent = 'إرسال الطلب';
        }
    }

    // --- 4. التشغيل ---
    async function initializePage() {
        if (cart.length === 0) {
            dom.pageContainer.innerHTML = `<div style="text-align:center; padding:50px;"><h3>السلة فارغة</h3><a href="profile.php?id=${businessId}" class="submit-btn">العودة للمتجر</a></div>`;
            dom.pageFooter.style.display = 'none';
            return;
        }

        // حساب مجموع السلة بناءً على العملة
        state.itemsTotal = cart.reduce((t, i) => t + i.price * i.quantity, 0);
        
        setupEventListeners();
        
        const defaultAddress = userAddresses.find(a => a.is_default === "1") || userAddresses[0] || null;
        await updateDeliveryDetails(defaultAddress);
        updateInvoice();
    }

    initializePage();
})();