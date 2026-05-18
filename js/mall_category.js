document.addEventListener("DOMContentLoaded", () => {
    
    const loadMoreBtn = document.getElementById('load-more-products');
    const grid = document.getElementById('products-grid');
    const searchInput = document.getElementById('cat-search');
    
    let offset = (typeof INITIAL_OFFSET !== 'undefined') ? INITIAL_OFFSET : 12;
    const catId = (typeof CATEGORY_ID !== 'undefined') ? CATEGORY_ID : 0;

    // 1. منطق تحميل المزيد
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', async () => {
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = 'جاري التحميل...';
            
            try {
                const res = await fetch(`php/ajax_load_cat_products.php?cat_id=${catId}&offset=${offset}`);
                const data = await res.json();

                if (data.html && data.html.trim() !== '') {
                    grid.insertAdjacentHTML('beforeend', data.html);
                    offset += 12;
                    
                    // إذا كان هناك بحث نشط، نطبق الفلتر على العناصر الجديدة
                    if (searchInput && searchInput.value.trim() !== '') {
                        searchInput.dispatchEvent(new Event('input'));
                    }

                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = 'عرض المزيد من المنتجات';
                } 
                
                if (!data.has_more || data.html.trim() === '') {
                    loadMoreBtn.style.display = 'none';
                }

            } catch (err) {
                console.error(err);
                loadMoreBtn.textContent = 'خطأ، حاول مرة أخرى';
                loadMoreBtn.disabled = false;
            }
        });
    }

    // 2. البحث الفوري داخل الصفحة
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.product-card-new');
            let found = false;

            cards.forEach(card => {
                const title = card.querySelector('.title').textContent.toLowerCase();
                if (title.includes(term)) {
                    card.style.display = '';
                    found = true;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // إخفاء/إظهار زر التحميل أثناء البحث (لأن الترتيب يختل)
            if (loadMoreBtn) {
                loadMoreBtn.style.display = (term === '' && offset > 0) ? 'inline-block' : 'none';
            }
        });
    }
});