// ========================================================================
// Syriazzle - Advanced Listings & Filtering Engine (v1.0 - Final)
// ========================================================================

document.addEventListener('DOMContentLoaded', () => {

    // --- 1. تعريف العناصر الأساسية ---
    const filterModal = document.getElementById('filter-modal');
    const filterToggleButton = document.getElementById('filter-toggle-btn');
    const closeFilterButton = document.getElementById('close-filter-btn');
    
    const searchText = document.getElementById('search-text');
    const governorateSelect = document.getElementById('governorate-select');
    const distanceSlider = document.getElementById('distance-slider');
    const distanceValue = document.getElementById('distance-value');
    const nearbySearchButton = document.getElementById('nearby-search-btn');

    const resultsGrid = document.getElementById('results-grid');
    const noResultsMessage = document.getElementById('no-results');

    // --- 2. إدارة حالة الفلاتر ---
    const state = {
        userLat: null,
        userLng: null,
        governorates: [] // لتخزين المحافظات بعد جلبها
    };

    // --- 3. إدارة واجهة الفلاتر (UI) ---
    if (filterToggleButton) {
        filterToggleButton.addEventListener('click', () => filterModal.classList.add('visible'));
    }
    if (closeFilterButton) {
        closeFilterButton.addEventListener('click', () => filterModal.classList.remove('visible'));
    }
    if (filterModal) {
        filterModal.addEventListener('click', (e) => {
            // تحقق إذا كان النقر على الخلفية الرمادية نفسها وليس على المحتوى
            if (e.target === filterModal) {
                filterModal.classList.remove('visible');
            }
        });
    }
    if (distanceSlider && distanceValue) {
        distanceSlider.addEventListener('input', () => {
            distanceValue.textContent = distanceSlider.value;
        });
    }

    // --- 4. منطق "البحث قربي" ---
    if (nearbySearchButton) {
        nearbySearchButton.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('عذرًا، متصفحك لا يدعم خدمة تحديد المواقع.');
                return;
            }

            const buttonText = nearbySearchButton.querySelector('span');
            buttonText.textContent = 'جاري تحديد موقعك...';
            nearbySearchButton.disabled = true;

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    state.userLat = position.coords.latitude;
                    state.userLng = position.coords.longitude;
                    
                    // بعد الحصول على الموقع، أغلق الفلتر وابدأ البحث فورًا
                    filterModal.classList.remove('visible');
                    fetchAndRenderBusinesses();
                },
                (error) => {
                    alert('فشل الحصول على الموقع. يرجى التأكد من تفعيل خدمة GPS والسماح للموقع بالوصول إليه.');
                    buttonText.textContent = 'استخدم موقعي الحالي';
                    nearbySearchButton.disabled = false;
                }
            );
        });
    }
    
    // --- 5. منطق الفلترة عند تغيير أي حقل ---
    [searchText, governorateSelect, distanceSlider].forEach(element => {
        if (element) {
            element.addEventListener('change', () => {
                // عند تغيير أي فلتر، قم بالبحث (إلا في حالة البحث قربي الذي يتطلب نقرة)
                if(element !== distanceSlider){
                    state.userLat = null; // إعادة تعيين البحث قربي عند اختيار محافظة أو كتابة نص
                    state.userLng = null;
                }
                fetchAndRenderBusinesses();
            });
        }
    });

    // --- 6. الدالة الرئيسية لجلب البيانات وعرضها ---
    async function fetchAndRenderBusinesses() {
        // إظهار حالة التحميل
        resultsGrid.innerHTML = '<div class="placeholder-card">جاري البحث...</div>';
        noResultsMessage.classList.add('hidden');

        // بناء رابط الطلب بناءً على حالة الفلاتر
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type') || 'delivery';
        const category = urlParams.get('category');
        
        let apiUrl = `php/ajax_filter_businesses.php?type=${type}`;
        if (category) apiUrl += `&category=${category}`;
        if (searchText.value) apiUrl += `&search=${searchText.value}`;
        if (governorateSelect.value) apiUrl += `&governorate=${governorateSelect.value}`;
        if (state.userLat && state.userLng) {
            apiUrl += `&lat=${state.userLat}&lng=${state.userLng}&dist=${distanceSlider.value}`;
        }
        
        try {
            const response = await fetch(apiUrl);
            const result = await response.json();

            if (result.success) {
                renderResults(result.data);
            } else {
                throw new Error(result.message || 'فشل جلب البيانات.');
            }
        } catch (error) {
            console.error('Fetch error:', error);
            resultsGrid.innerHTML = '';
            noResultsMessage.querySelector('p').textContent = 'حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة مرة أخرى.';
            noResultsMessage.classList.remove('hidden');
        }
    }

    function renderResults(businesses) {
        if (businesses.length === 0) {
            resultsGrid.innerHTML = '';
            noResultsMessage.querySelector('p').textContent = 'عذرًا، لم نتمكن من العثور على أي نتائج تطابق معايير بحثك الحالية.';
            noResultsMessage.classList.remove('hidden');
        } else {
            resultsGrid.innerHTML = businesses.map(business => {
                // تجميع العنوان بشكل ذكي (مدينة، محافظة)
                const locationParts = [business.city, business.governorate_name].filter(Boolean); // يزيل القيم الفارغة
                const location = locationParts.join(', ');

                return `
                <a href="profile.php?id=${business.id}" class="business-card">
                    <img src="${business.logo_image || 'image/logo1.png'}" alt="${business.name}" class="logo">
                    <div class="business-info">
                        <h3>${business.name}</h3>
                        <div class="category">${business.category || business.booking_category}</div>
                        ${location ? `<div class="location"><i class="fas fa-map-marker-alt"></i> ${location}</div>` : ''}
                    </div>
                    ${(business.distance && business.distance >= 0) ? `<div class="distance-badge"><i class="fas fa-route"></i> ~${parseFloat(business.distance).toFixed(1)} كم</div>` : ''}
                </a>
            `;
            }).join('');
            noResultsMessage.classList.add('hidden');
        }
    }
    
    async function fetchGovernorates() {
        try {
            const response = await fetch('php/get_governorates.php');
            const result = await response.json();
            if (result.success && governorateSelect) {
                state.governorates = result.data;
                governorateSelect.innerHTML += state.governorates.map(gov => `<option value="${gov.id}">${gov.name}</option>`).join('');
            }
        } catch (error) {
            console.error("Could not fetch governorates", error);
        }
    }

    // --- 8. التشغيل الأولي ---
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('category')) {
        fetchAndRenderBusinesses(); // إذا كنا في صفحة نتائج، ابدأ البحث فورًا
        fetchGovernorates(); // واجلب المحافظات للفلتر
    }
});