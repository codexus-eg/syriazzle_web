document.addEventListener('DOMContentLoaded', () => {
    const categoriesContainer = document.getElementById('categories-container');
    const productsView = document.getElementById('products-view');
    const categoryGrid = document.getElementById('category-grid');
    const productsContainer = document.getElementById('products-container');
    const filterSidebar = document.getElementById('filter-sidebar');
    const categoryTitle = document.getElementById('category-title');
    const backBtn = document.getElementById('back-to-categories');
    const noResultsMsg = document.getElementById('no-results');
    
    let currentCategoryId = null;
    let currentPage = 1;

    async function loadCategoryData(categoryId, categoryName) {
        currentCategoryId = categoryId;
        currentPage = 1;
        categoriesContainer.classList.add('hidden');
        productsView.classList.remove('hidden');
        categoryTitle.textContent = categoryName;
        productsContainer.innerHTML = '<p class="loading-message">جاري تحميل المنتجات...</p>';
        filterSidebar.innerHTML = '<p class="loading-message">جاري تحميل الفلاتر...</p>';
        await fetchFiltersAndProducts();
    }
    
    const fetchFiltersAndProducts = async () => {
        productsContainer.innerHTML = '<p class="loading-message">جاري تحديث المنتجات...</p>';
        const selectedSizes = Array.from(filterSidebar.querySelectorAll('input[name="size"]:checked')).map(el => el.value);
        const selectedColors = Array.from(filterSidebar.querySelectorAll('.color-swatch.selected')).map(el => el.dataset.colorId);
        const priceRangeInput = filterSidebar.querySelector('#price-range');
        const maxPrice = priceRangeInput ? priceRangeInput.value : 10000;
        const filterData = { categoryId: currentCategoryId, sizes: selectedSizes, colors: selectedColors, maxPrice: maxPrice, page: currentPage };
        try {
            const response = await fetch('api_filter_products.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(filterData) });
            const data = await response.json();
            if (!data.success) throw new Error(data.message);
            if (filterSidebar.querySelector('.loading-message')) displayFilters(data.filters);
            displayProducts(data.products);
        } catch (error) {
            productsContainer.innerHTML = `<p class="no-results-message">حدث خطأ: ${error.message}</p>`;
        }
    };

    function displayProducts(products) {
        productsContainer.innerHTML = '';
        if (!products || products.length === 0) {
            noResultsMsg.classList.remove('hidden');
            return;
        }
        noResultsMsg.classList.add('hidden');
        products.forEach(product => {
            const productCard = document.createElement('div');
            productCard.className = 'product-card';
            productCard.dataset.href = `product_details.php?id=${product.id}`;
            const colorsHTML = (product.available_colors && product.available_colors.length > 0) ? `<div class="product-colors">${product.available_colors.slice(0, 5).map(c => `<span class="color-swatch" style="background-color: ${c.hex_code};" title="${c.name}"></span>`).join('')}</div>` : '';
            productCard.innerHTML = `<div class="product-image-container"><img src="${product.main_image || ''}" alt="${product.name}" class="main-product-image"></div><div class="product-info">${colorsHTML}<h4>${product.name}</h4><p class="price">${parseFloat(product.price).toFixed(2)} $</p></div>`;
            productsContainer.appendChild(productCard);
        });
    }

    function displayFilters(filtersData) {
        if (!filtersData) { filterSidebar.innerHTML = ''; return; }
        let fHTML = '<h3>الفلاتر</h3>';
        fHTML += `<div class="filter-group"><h4>السعر ($)</h4><div><input type="range" id="price-range" min="0" max="1000" value="1000" step="10"><span id="price-value">1000</span></div></div>`;
        if (filtersData.sizes?.length) fHTML += `<div class="filter-group"><h4>المقاس</h4>${filtersData.sizes.map(s => `<label><input type='checkbox' name='size' value='${s.id}'> ${s.name}</label>`).join('')}</div>`;
        if (filtersData.colors?.length) fHTML += `<div class="filter-group"><h4>اللون</h4><div class="color-filters">${filtersData.colors.map(c => `<span class='color-swatch' data-color-id='${c.id}' style='background-color: ${c.hex_code};'></span>`).join('')}</div></div>`;
        filterSidebar.innerHTML = fHTML;
    }

    // -- MODIFIED: تم تعديل هذا الجزء --

    // المستمع الخاص بشبكة التصنيفات (للموبايل)
    categoryGrid?.addEventListener('click', e => {
        const card = e.target.closest('.category-card');
        if (card) {
            loadCategoryData(card.dataset.categoryId, card.dataset.categoryName);
        }
    });

    // -- NEW: إضافة مستمع جديد للصورة التفاعلية (للشاشات الكبيرة) --
    document.querySelectorAll('.map-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault(); // منع الرابط من تحديث الصفحة
            const categoryId = this.dataset.categoryId;
            const categoryName = this.dataset.categoryName;
            // التحقق من وجود البيانات قبل استدعاء الدالة
            if (categoryId && categoryName) {
                loadCategoryData(categoryId, categoryName);
            }
        });
    });
    
    // باقي المستمعين كما هم
    backBtn?.addEventListener('click', () => {
        productsView.classList.add('hidden');
        categoriesContainer.classList.remove('hidden');
    });

    productsContainer?.addEventListener('click', e => {
        const card = e.target.closest('.product-card');
        if (card?.dataset.href) {
            if (typeof navigateTo === 'function') {
                e.preventDefault();
                navigateTo(card.dataset.href);
            } else {
                window.location.href = card.dataset.href;
            }
        }
    });
    
    filterSidebar?.addEventListener('click', e => {
        if (e.target.matches('.color-swatch')) {
            e.target.classList.toggle('selected');
            fetchFiltersAndProducts();
        }
    });
    filterSidebar?.addEventListener('change', e => {
        if (e.target.matches('input')) fetchFiltersAndProducts();
    });
});