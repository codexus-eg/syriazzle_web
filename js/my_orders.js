// ========================================================================
// Syriazzle - My Orders Logic (Multi-Currency Support - Final)
// ========================================================================

document.addEventListener('DOMContentLoaded', () => {
    // تعريف العناصر
    const listContainer = document.getElementById('orders-list');
    const loadMoreBtn = document.getElementById('btn-load-more');
    const loadMoreContainer = document.getElementById('load-more');
    const loader = document.getElementById('loader');
    const tabs = document.querySelectorAll('.tab-btn');

    // متغيرات الحالة
    let currentStatus = 'active';
    let offset = 0;
    let isLoading = false;

    // قاموس حالات الطلب (للتلوين والنصوص العربية)
    const statusConfig = {
        'pending_approval': { text: 'بانتظار الموافقة', class: 'st-pending' },
        'preparing': { text: 'قيد التحضير', class: 'st-active' },
        'ready_for_pickup': { text: 'جاهز للاستلام', class: 'st-active' },
        'accepted': { text: 'السائق في الطريق للمتجر', class: 'st-active' },
        'picked_up': { text: 'في الطريق إليك', class: 'st-active' },
        'out_for_delivery': { text: 'في الطريق إليك', class: 'st-active' },
        'delivered': { text: 'تم التوصيل', class: 'st-success' },
        'canceled': { text: 'ملغي', class: 'st-cancel' }
    };

    // 1. دالة جلب الطلبات (AJAX)
    async function loadOrders(reset = false) {
        if (isLoading) return;
        isLoading = true;
        
        if (reset) {
            listContainer.innerHTML = '';
            offset = 0;
            loadMoreContainer.style.display = 'none';
        }
        
        // إظهار اللودر فقط إذا لم يكن هناك محتوى، أو في أسفل القائمة
        if (offset === 0) {
            listContainer.innerHTML = ''; 
        }
        loader.style.display = 'block';

        try {
            // استدعاء ملف PHP الموحد الذي يرجع العملة أيضاً
            const res = await fetch(`php/fetch_customer_orders.php?status=${currentStatus}&offset=${offset}`);
            const result = await res.json();

            loader.style.display = 'none';

            if (result.success) {
                if (result.data.length > 0) {
                    renderOrders(result.data);
                    offset += 5; // زيادة العداد للدفعة القادمة
                    
                    loadMoreContainer.style.display = result.has_more ? 'block' : 'none';
                } else if (reset) {
                    listContainer.innerHTML = `
                        <div style="text-align:center; padding:40px; color:#999;">
                            <i class="fas fa-receipt" style="font-size:3rem; margin-bottom:10px; opacity:0.5;"></i>
                            <p>لا توجد طلبات هنا</p>
                        </div>`;
                } else {
                    loadMoreContainer.style.display = 'none';
                }
            } else {
                console.error("Server Error:", result.message);
            }
        } catch (e) {
            console.error("Network Error:", e);
            loader.style.display = 'none';
            if (reset) {
                listContainer.innerHTML = '<div style="text-align:center; padding:20px; color:red;">خطأ في الاتصال</div>';
            }
        }
        isLoading = false;
    }

    // 2. دالة رسم البطاقات (HTML Rendering)
    function renderOrders(orders) {
        const html = orders.map(o => {
            // تحديد النص واللون حسب الحالة
            const st = statusConfig[o.status] || { text: o.status, class: 'st-pending' };
            
            // تحديد نوع المتجر للعرض (مول / متجر)
            const typeLabel = (o.type === 'mall') ? 'مول Syriazzle' : o.store_name;
            
            // === منطق تنسيق العملة في القائمة الرئيسية ===
            const currencyCode = o.currency || 'SYP';
            let formattedTotal = '';
            
            if (currencyCode === 'USD') {
                // تنسيق الدولار: $20.50
                formattedTotal = '$' + Number(o.total).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                // تنسيق الليرة: 5,000 ل.س
                formattedTotal = Number(o.total).toLocaleString('en-US') + ' ل.س';
            }
            
            // بناء البطاقة مع تمرير العملة لدالة التفاصيل
            return `
                <div class="order-card-compact" onclick="openDetails(${o.id}, '${o.status}', ${o.business_id}, '${o.type}', '${currencyCode}')">
                    <div class="card-start">
                        <img src="${o.store_logo || 'image/default.png'}" class="store-img" onerror="this.src='image/default.png'">
                        <div class="order-info">
                            <h3>${typeLabel}</h3>
                            <div class="meta">#${o.id} &bull; ${o.date}</div>
                            <div class="price" dir="ltr">${formattedTotal}</div>
                        </div>
                    </div>
                    <div class="card-end">
                        <span class="status-badge ${st.class}">${st.text}</span>
                        <button class="action-icon-btn"><i class="fas fa-chevron-left"></i></button>
                    </div>
                </div>
            `;
        }).join('');
        
        listContainer.insertAdjacentHTML('beforeend', html);
    }

    // 3. تفعيل أزرار التبويبات (النشطة، المكتملة، الملغاة)
    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            tabs.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentStatus = btn.dataset.status;
            loadOrders(true); // إعادة تحميل مع تصفير القائمة
        });
    });

    // 4. تفعيل زر "عرض المزيد"
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => loadOrders(false));
    }

    // التشغيل الأولي عند فتح الصفحة
    loadOrders(true);
});

// ========================================================================
// Modal & Actions Functions (Global Scope)
// ========================================================================

const modal = document.getElementById('order-modal');
const modalBody = document.getElementById('modal-content');
const modalFooter = document.getElementById('modal-footer');
const modalTitle = document.getElementById('modal-title');

// إغلاق المودال
function closeModal() { 
    if(modal) modal.style.display = 'none'; 
}
window.onclick = (e) => { 
    if(e.target == modal) closeModal(); 
};

/**
 * فتح نافذة التفاصيل
 * @param {number} orderId - رقم الطلب
 * @param {string} status - حالة الطلب
 * @param {number} businessId - رقم المتجر (0 للمول)
 * @param {string} type - نوع الطلب ('mall' أو 'market')
 * @param {string} currency - عملة الطلب ('USD' أو 'SYP')
 */
async function openDetails(orderId, status, businessId, type, currency) {
    const typeText = (type === 'mall') ? 'طلب مول' : 'طلب متجر';
    modalTitle.textContent = `${typeText} #${orderId}`;
    modalBody.innerHTML = '<div style="text-align:center; padding:30px;"><i class="fas fa-spinner fa-spin" style="font-size:2rem; color:#007bff;"></i></div>';
    modalFooter.innerHTML = '';
    
    if(modal) modal.style.display = 'flex';

    try {
        // تحديد مسار ملف جلب التفاصيل حسب النوع
        const endpoint = (type === 'mall') ? 'php/get_mall_order_items.php' : 'php/get_order_items.php';
        
        const res = await fetch(`${endpoint}?order_id=${orderId}`);
        const items = await res.json();

        // بناء محتوى الفاتورة
        let html = '';
        if (Array.isArray(items) && items.length > 0) {
            items.forEach(item => {
                // توحيد أسماء الأعمدة
                const name = item.item_name || item.product_name || 'منتج';
                const price = parseFloat(item.price_per_item || item.price || 0);
                
                // === تنسيق السعر داخل المودال حسب العملة ===
                let formattedPrice = '';
                if (currency === 'USD') {
                    formattedPrice = '$' + price.toLocaleString('en-US', {minimumFractionDigits: 2});
                } else {
                    formattedPrice = price.toLocaleString('en-US') + ' ل.س';
                }

                html += `
                    <div class="detail-row">
                        <span>${item.quantity}x ${name}</span>
                        <span dir="ltr">${formattedPrice}</span>
                    </div>
                `;
                
                // عرض الملاحظات إن وجدت
                if(item.special_requests) {
                    html += `<div class="detail-note"><i class="fas fa-comment-dots"></i> ${item.special_requests}</div>`;
                }
            });
        } else {
            html = '<p style="text-align:center; color:#777;">لا توجد تفاصيل لهذا الطلب.</p>';
        }
        modalBody.innerHTML = html;

        // بناء أزرار التحكم
        let btns = '';
        
        // 1. زر التتبع (للحالات النشطة)
        const trackableStatuses = ['accepted', 'picked_up', 'out_for_delivery', 'preparing', 'ready_for_pickup'];
        if (trackableStatuses.includes(status)) {
            // توجيه للصفحة المناسبة (المول أو المتاجر)
            const trackPage = (type === 'mall') ? 'track_mall_order.php' : 'track_order.php';
            btns += `<a href="${trackPage}?order_id=${orderId}" class="modal-btn btn-track"><i class="fas fa-map-marker-alt"></i> تتبع الطلب</a>`;
        }
        
        // 2. زر إعادة الطلب (للمتاجر فقط حالياً، وعندما تكون الحالة منتهية)
        if ((status === 'delivered' || status === 'canceled') && type !== 'mall') {
            btns += `<button onclick="reorder(${orderId}, ${businessId})" class="modal-btn btn-reorder"><i class="fas fa-redo"></i> إعادة الطلب</button>`;
        }
        
        modalFooter.innerHTML = btns;

    } catch (e) {
        console.error(e);
        modalBody.innerHTML = '<p style="text-align:center; color:red;">حدث خطأ أثناء تحميل التفاصيل.</p>';
    }
}

/**
 * دالة إعادة الطلب (للمتاجر)
 */
async function reorder(orderId, businessId) {
    if(!confirm("هل تريد إفراغ السلة الحالية وإضافة منتجات هذا الطلب؟")) return;
    
    const btn = document.querySelector('.btn-reorder');
    if(btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المعالجة...';
    }

    try {
        const res = await fetch(`php/get_reorder_data.php?order_id=${orderId}`);
        const data = await res.json();
        
        if(data.success) {
            // مفتاح السلة في التخزين المحلي
            const cartKey = `cart_${businessId}`;
            
            // تحويل البيانات لتناسب هيكلية السلة
            const newCart = data.items.map(item => ({
                id: item.id, 
                name: item.name, 
                price: parseFloat(item.price),
                image: item.image, 
                quantity: parseInt(item.quantity), 
                type: 'menu' // افتراضي للمنيو
            }));
            
            // الحفظ والتوجيه
            localStorage.setItem(cartKey, JSON.stringify(newCart));
            window.location.href = `checkout.php?business_id=${businessId}`;
        } else {
            alert(data.message || "عذراً، المنتجات لم تعد متوفرة.");
            if(btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> إعادة الطلب';
            }
        }
    } catch(e) { 
        alert("حدث خطأ في الاتصال.");
        if(btn) btn.disabled = false;
    }
}