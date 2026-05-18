// ========================================================================
// Syriazzle Mall - Main Logic (With Filters)
// ========================================================================

document.addEventListener("DOMContentLoaded", () => {
    
    // 1. السلايدر
    if (document.getElementById("hero-slider")) {
        try {
            new Swiper(".swiper-container", {
                loop: true, autoplay: { delay: 4000, disableOnInteraction: false },
                pagination: { el: ".swiper-pagination", clickable: true },
                effect: 'fade', fadeEffect: { crossFade: true }
            });
        } catch (e) { console.warn(e); }
    }

    // 2. البحث الفوري
    const searchInput = document.getElementById('search-input');
    let searchTimeout = null; // متغير للتحكم في وقت الكتابة

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.trim();
            const mallContent = document.getElementById('mall-content');
            
            const sliderContainer = document.querySelector('.hero-slider-container');
            const loadMoreContainer = document.getElementById('load-more-container');
            const loadMoreBtn = document.getElementById('load-more-btn');

            // إيقاف أي بحث سابق لم يكتمل
            if (searchTimeout) clearTimeout(searchTimeout);

            if (term.length === 0) {
                window.location.reload(); 
                return;
            }

            // الانتظار نصف ثانية بعد التوقف عن الكتابة قبل الإرسال للسيرفر
            searchTimeout = setTimeout(() => {
                if (sliderContainer) sliderContainer.style.display = 'none';
                if (loadMoreContainer) loadMoreContainer.style.display = 'none';
                if (loadMoreBtn) loadMoreBtn.style.display = 'none';

                if (mallContent) {
                    mallContent.innerHTML = '<div style="text-align:center; padding:50px; font-size:1.2rem; color:#666;"><i class="fas fa-circle-notch fa-spin"></i> جاري البحث...</div>';
                    
                    // 3. طلب البيانات من ملف PHP الجديد
                    fetch(`php/ajax_search.php?term=${encodeURIComponent(term)}`)
                        .then(response => {
                            if (!response.ok) throw new Error("Network response was not ok");
                            return response.text();
                        })
                        .then(html => {
                            mallContent.innerHTML = html;
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            mallContent.innerHTML = '<div style="text-align:center; color:red;">حدث خطأ أثناء البحث، يرجى المحاولة لاحقاً.</div>';
                        });
                }
            }, 500); 
        });
    }

    // 3. زر "عرض المزيد"
    const loadMoreBtn = document.getElementById('load-more-btn');
    const mallContent = document.getElementById('mall-content');
    let currentOffset = (typeof INITIAL_OFFSET !== 'undefined') ? INITIAL_OFFSET : 5;

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', async () => {
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = 'جاري التحميل...';
            try {
                const response = await fetch(`php/ajax_load_categories.php?offset=${currentOffset}`);
                if (!response.ok) throw new Error("Error");
                const data = await response.json();

                if (data.html && data.html.trim() !== '') {
                    mallContent.insertAdjacentHTML('beforeend', data.html);
                    currentOffset += 5;
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = 'عرض المزيد';
                    
                    // --- هام: إعادة تهيئة الفلاتر للمحتوى الجديد ---
                    initFilters(); 
                }
                if (!data.has_more) loadMoreBtn.style.display = 'none';
            } catch (error) {
                console.error(error);
                loadMoreBtn.textContent = 'خطأ';
                loadMoreBtn.disabled = false;
            }
        });
    }

    // 4. القائمة الجانبية والسلة
    const menu = document.getElementById('off-canvas-menu');
    const overlay = document.getElementById('off-canvas-overlay');
    const openBtns = document.querySelectorAll('#open-canvas-btn');
    const closeBtn = document.getElementById('close-canvas-btn');

    function toggleMenu(show) {
        if (!menu) return;
        if (show) { menu.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow='hidden'; }
        else { menu.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow=''; }
    }
    openBtns.forEach(btn => btn.addEventListener('click', (e)=>{ e.preventDefault(); toggleMenu(true); }));
    if(closeBtn) closeBtn.onclick = () => toggleMenu(false);
    if(overlay) overlay.onclick = () => toggleMenu(false);

    document.querySelectorAll('.canvas-category-group').forEach(group => {
        const mainLink = group.querySelector('.main-cat');
        const subList = group.querySelector('.canvas-subcategory-list');
        const icon = mainLink ? mainLink.querySelector('i') : null;
        if (mainLink && subList) {
            mainLink.addEventListener('click', () => {
                const isHidden = window.getComputedStyle(subList).display === 'none';
                subList.style.display = isHidden ? 'block' : 'none';
                if(icon) icon.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
            });
        }
    });

    // تهيئة الفلاتر عند البدء
    initFilters();
    initCart();
});

// --- 5. منطق فلترة الأصناف (Client-Side Filtering) ---
function initFilters() {
    // نستخدم تفويض الأحداث (Event Delegation) أو نربط الكل
    // هنا سنربط الكل لتجنب التعقيد، وبما أن الدالة تستدعى بعد التحميل، ستعمل
    const chips = document.querySelectorAll('.filter-chip');
    
    chips.forEach(chip => {
        // إزالة المستمعين القدامى لتجنب التكرار (عن طريق الاستبدال)
        const newChip = chip.cloneNode(true);
        chip.parentNode.replaceChild(newChip, chip);
        
        newChip.addEventListener('click', function() {
            const targetId = this.dataset.filter;
            const wrapper = this.closest('.dept-section');
            
            // تحديث الأزرار النشطة
            wrapper.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            this.classList.add('active');

            // إخفاء/إظهار الحاويات
            wrapper.querySelectorAll('.cat-container').forEach(container => {
                if (targetId === 'all' || container.dataset.catId === targetId) {
                    container.style.display = 'block';
                    // إعادة تفعيل الأنيميشن أو الظهور إن وجد
                    container.style.animation = 'fadeIn 0.3s ease'; 
                } else {
                    container.style.display = 'none';
                }
            });
        });
    });
}

// --- 6. دوال السلة ---
let cart = [];
try { cart = JSON.parse(localStorage.getItem("mall_cart")) || []; } catch (e) { cart = []; }

function initCart() {
    if (!document.getElementById('cart-panel')) {
        const panelHTML = `<div id="cart-panel" class="cart-panel"><div class="cart-header"><h3>سلة المشتريات</h3><button id="close-cart-panel">&times;</button></div><div class="cart-body" id="cart-items-container"></div><div class="cart-footer"><div class="cart-total"><span>المجموع:</span><span id="cart-total-display">0</span></div><button id="checkout-btn">إتمام الطلب <i class="fas fa-arrow-left"></i></button></div></div><div id="cart-overlay" class="cart-overlay"></div>`;
        document.body.insertAdjacentHTML('beforeend', panelHTML);
        const st = document.createElement('style');
        st.innerHTML = `.cart-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:998; display:none; opacity:0; transition:0.3s; } .cart-overlay.open { display:block; opacity:1; } @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }`;
        document.head.appendChild(st);
    }
    const cClose = document.getElementById('close-cart-panel');
    const cOverlay = document.getElementById('cart-overlay');
    const cCheck = document.getElementById('checkout-btn');
    if(cClose) cClose.onclick = () => toggleCart(false);
    if(cOverlay) cOverlay.onclick = () => toggleCart(false);
    if(cCheck) cCheck.onclick = () => { if(cart.length>0) window.location.href='mall_checkout.php'; else alert('السلة فارغة'); };
    updateCartUI();
}

function toggleCart(open) {
    const p = document.getElementById('cart-panel');
    const o = document.getElementById('cart-overlay');
    if(open) { p.classList.add('open'); if(o) o.classList.add('open'); document.body.style.overflow='hidden'; }
    else { p.classList.remove('open'); if(o) o.classList.remove('open'); document.body.style.overflow=''; }
}
function addToCart(p, q=1) {
    const x = cart.find(i => i.id === p.id);
    if(x) x.quantity += q; else cart.push({id:p.id, name:p.name, price:parseFloat(p.price), image:p.image, quantity:q});
    saveCart(); toggleCart(true);
}
function removeFromCart(id) { cart = cart.filter(i => i.id !== id); saveCart(); }
function saveCart() { localStorage.setItem("mall_cart", JSON.stringify(cart)); updateCartUI(); }
function updateCartUI() {
    const cnt = cart.reduce((s,i)=>s+i.quantity,0);
    const tot = cart.reduce((s,i)=>s+(i.price*i.quantity),0);
    const fab = document.getElementById('cart-fab');
    if(fab) { fab.querySelector('span').textContent = cnt; fab.style.display = cnt>0?'flex':'none';fab.onclick = () => toggleCart(true); if(cnt>0){fab.style.transform='scale(1.1)'; setTimeout(()=>fab.style.transform='scale(1)',200);} }
    const cont = document.getElementById('cart-items-container');
    const totD = document.getElementById('cart-total-display');
    const btn = document.getElementById('checkout-btn');
    if(cont && totD) {
        totD.textContent = tot.toLocaleString('en-US') + ' ل.س';
        if(cart.length===0) {
            cont.innerHTML = '<div style="text-align:center;padding:30px;color:#999">السلة فارغة</div>';
            if(btn) btn.disabled = true;
        } else {
            if(btn) btn.disabled = false;
            cont.innerHTML = cart.map(i => `<div class="cart-item" style="display:flex; gap:10px; padding:10px; border-bottom:1px solid #eee; align-items:center;"><img src="${i.image}" style="width:50px;height:50px;object-fit:cover;border-radius:8px;"><div style="flex:1;"><div style="font-weight:bold;font-size:0.9rem;">${i.name}</div><div style="font-size:0.8rem;color:#777;">${i.quantity} x ${i.price.toLocaleString()}</div></div><div style="color:#e60000;font-weight:bold;">${(i.price*i.quantity).toLocaleString()}</div><button onclick="removeFromCart(${i.id})" style="border:none;background:none;color:#999;cursor:pointer;">&times;</button></div>`).join('');
        }
    }
}