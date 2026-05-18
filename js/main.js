document.addEventListener('DOMContentLoaded', () => {

    // =======================================================================
    // 1. User Authentication and Navigation Links Management
    //    تم تبسيط منطق التحقق من تسجيل الدخول وبناء الواجهة ليتوافق مع أحدث نسخة
    // =======================================================================
    const desktopNavContainer = document.getElementById('desktop-auth-container');
    const mobileFooterNav = document.querySelector('.mobile-footer-nav');

    // تحديد حالة المستخدم مرة واحدة عند تحميل الصفحة
    const userToken = localStorage.getItem('userToken');
    const isLoggedIn = !!userToken;

    // بناء الواجهة الصحيحة (للزائر أو المسجل)
    buildDynamicUI(isLoggedIn);

    // تطبيق حماية الروابط بعد بناء الواجهة (مهم!)
    applyLinkProtection(isLoggedIn);

    // تشغيل الوظائف الإضافية (سلايدر، قوائم، وغيرها)
    initializePageWidgets();
    handleFooterNav();

    // =======================================================================
    // 2. Global Notification Message (for pop-up messages)
    // =======================================================================
    const notificationMessage = document.getElementById('notification-message');
    function showNotification(message, type) {
        if (notificationMessage) {
            notificationMessage.textContent = message;
            notificationMessage.className = ''; // Clear previous classes
            notificationMessage.classList.add(type); // 'success', 'error', 'info'
            notificationMessage.style.display = 'block';
            setTimeout(() => {
                notificationMessage.style.display = 'none';
            }, 4000); // Hide after 4 seconds
        }
    }

    // =======================================================================
    // 3. Ad Details Modal (for showing full ad details)
    // =======================================================================
    const adDetailsModal = document.getElementById('adDetailsModal');
    const closeButton = adDetailsModal ? adDetailsModal.querySelector('.close-button') : null;
    const modalBody = adDetailsModal ? adDetailsModal.querySelector('.modal-body') : null;

    function showAdDetails(ad) {
        if (!adDetailsModal || !modalBody) {
            return;
        }

        modalBody.innerHTML = ''; // Clear previous content

        // Create and append the title
        const titleElement = document.createElement('h2');
        titleElement.textContent = ad['الماركة'] || ad['العنوان'] || 'تفاصيل الإعلان';
        modalBody.appendChild(titleElement);

        // Dynamically add key-value pairs
        const fieldsToDisplay = [
            { key: 'السعر', label: 'السعر', suffix: ' ل.س' },
            { key: 'المحافظة', label: 'المحافظة' },
            { key: 'الحالة', label: 'الحالة' },
            { key: 'اللون', label: 'اللون' },
            { key: 'عدد المقاعد', label: 'عدد المقاعد' },
            { key: 'سنة الصنع', label: 'سنة الصنع' },
            { key: 'ناقل الحركة', label: 'ناقل الحركة' },
            { key: 'الإستهالك', label: 'الاستهلاك' },
            { key: 'السعة cc', label: 'السعة cc' },
            { key: 'كيلو مترات', label: 'كيلو مترات' },
            { key: 'تكييف', label: 'تكييف' },
            { key: 'الجزء الداخلي', label: 'الجزء الداخلي' },
            { key: 'رقم الواتس', label: 'رقم الواتس' },
            { key: 'الوصف الإضافي', label: 'الوصف الإضافي' }
        ];

        fieldsToDisplay.forEach(field => {
            if (ad[field.key]) { // Check if the field exists in the ad data
                const p = document.createElement('p');
                p.innerHTML = `<strong>${field.label}:</strong> ${ad[field.key]}${field.suffix || ''}`;
                modalBody.appendChild(p);
            }
        });

        // Handle images
        if (ad.images_uploaded && ad.images_uploaded.length > 0) {
            const galleryDiv = document.createElement('div');
            galleryDiv.className = 'modal-images-gallery';

            ad.images_uploaded.forEach(imagePath => {
                const img = document.createElement('img');
                img.src = imagePath.startsWith('/') ? imagePath : '/' + imagePath;
                img.alt = 'صورة الإعلان';
                img.addEventListener('click', () => showFullImage(img.src));
                galleryDiv.appendChild(img);
            });
            modalBody.appendChild(galleryDiv);

            const fullImageDiv = document.createElement('div');
            fullImageDiv.className = 'modal-full-image';
            const mainImg = document.createElement('img');
            mainImg.src = ad.images_uploaded[0].startsWith('/') ? ad.images_uploaded[0] : '/' + ad.images_uploaded[0];
            mainImg.alt = 'صورة الإعلان الرئيسية';
            fullImageDiv.appendChild(mainImg);
            modalBody.appendChild(fullImageDiv);
        } else {
            modalBody.innerHTML += '<p style="font-style: italic; color: #888; text-align: center;">لا توجد صور لهذا الإعلان.</p>';
        }

        adDetailsModal.style.display = 'flex';
    }

    function showFullImage(src) {
        const mainImgElement = modalBody.querySelector('.modal-full-image img');
        if (mainImgElement) {
            mainImgElement.src = src;
        }
    }

    if (closeButton) {
        closeButton.addEventListener('click', () => {
            adDetailsModal.style.display = 'none';
        });
    }
    window.addEventListener('click', (event) => {
        if (event.target == adDetailsModal) {
            adDetailsModal.style.display = 'none';
        }
    });

    // =======================================================================
    // 4. Fetch and Render Categorized Ads as Horizontal Sliders
    // =======================================================================
    async function fetchAndRenderCategorizedAdsSliders() {
        
        const dynamicAdCategoriesContainer = document.getElementById('dynamic-ad-categories-sliders');
        if (!dynamicAdCategoriesContainer) {
            console.error("Dynamic ad categories container not found.");
            return;
        }

        dynamicAdCategoriesContainer.innerHTML = '<p class="loading-message" style="text-align: center; padding: 20px;">جاري تحميل أحدث الإعلانات...</p>';

        try {
            const response = await fetch('php/fetch_categories.php');
            const text = await response.text();

            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error("JSON parsing error for categorized ads:", e);
                dynamicAdCategoriesContainer.innerHTML = `<p class="no-ads-message" style="color: red; text-align: center; padding: 20px;">خطأ في تحميل الإعلانات: استجابة غير صالحة من الخادم.</p><pre style="white-space: pre-wrap; word-break: break-all; text-align: left; direction: ltr; font-size: 0.8em; background-color: #ffebeb; padding: 10px; border-radius: 5px; margin: 20px auto; max-width: 800px;">${text}</pre>`;
                showNotification(`خطأ: استجابة غير صالحة لجلب الإعلانات المصنفة.`, 'error');
                return;
            }

            if (result.success) {
                dynamicAdCategoriesContainer.innerHTML = ''; // Clear loading message

                const displayOrder = [
                    'مركبات',
                    'عقارات',
                    'هواتف_وإكسسوارات',
                    'أثاث والديكور',
                    'الموضة_والجمال',
                    "سياحة وسفر",
                    'مستلزمات_الأطفال',
                    'أجهزة_إلكترونية',
                    'تجارة_وصناعة',
                    'مستلزمات_الرياضة',
                    'حيوانات_أليفة',
                    'هوايات',
                    'خدمات',
                    'التوظيف',
                    'أجهزة_كشف_المعادن'
                ];

                let hasAdsToDisplay = false;

                displayOrder.forEach(categoryName => {
                    const ads = result.data[categoryName];
                    if (ads && ads.length > 0) {
                        hasAdsToDisplay = true;

                        const section = document.createElement('section');
                        section.className = 'ads-category-section';

                        const containerDiv = document.createElement('div');
                        containerDiv.className = 'container';

                        const parentElement2 = document.createElement("div");
                        parentElement2.className = "parentEl2";
                        const titleElement = document.createElement('h2');
                        const ele = document.createElement('i');
                        ele.className = "fas fa-arrow-left";
                        
                        titleElement.textContent = categoryName;
                        containerDiv.appendChild(parentElement2);
                        parentElement2.appendChild(titleElement);
                        parentElement2.appendChild(ele);

                        const adsScrollWrapper = document.createElement('div');
                        adsScrollWrapper.className = 'ads-scroll-wrapper';
                        ads.forEach(ad => {
                            const adId = ad.db_id || ad.form_id; 
                            const adCard = document.createElement('a');
                            adCard.href = `ad_details.php?id=${adId}`; 
                            adCard.className = 'ad-card';

                            // Determine main image path
                            const mainImage = (ad.images && ad.images.length > 0) ?
                                `/${ad.images[0]}` : 'image/default-ad-image.png';
                            const hasImage = mainImage !== 'image/default-ad-image.png';

                            // Logic to determine the ad title for the card
                            let cardTitle = '';
                            if (ad.category === 'عقارات') {
                                // For real estate, prioritize subsubsub, then subsub, then sub
                                if (ad.subsubsub && String(ad.subsubsub).trim() !== '') {
                                    cardTitle = ad.subsubsub;
                                    if (ad.sub && String(ad.sub).trim() !== '') {
                                        cardTitle = `${ad.sub} - ${cardTitle}`;
                                    }
                                } else if (ad.subsub && String(ad.subsub).trim() !== '') {
                                    cardTitle = ad.subsub;
                                    if (ad.sub && String(ad.sub).trim() !== '') {
                                        cardTitle = `${ad.sub} - ${cardTitle}`;
                                    }
                                } else if (ad.sub && String(ad.sub).trim() !== '') {
                                    cardTitle = ad.sub;
                                } else {
                                    cardTitle = ad.category || 'إعلان عقاري';
                                }
                            } else {
                                // For other categories, get the first dynamic text field
                                const excludedForFirstPart = [
                                    'id', 'created_at', 'category', 'sub', 'subsub', 'subsubsub', 
                                    'الصورة', 'السعر', 'المحافظة', 'رقم الهاتف', 'رقم الواتس', 
                                    'الوصف', 'الميزات', 'images', 'العنوان' 
                                ];
                                
                                for (const key in ad) {
                                    if (ad.hasOwnProperty(key) && 
                                        !excludedForFirstPart.includes(key) && 
                                        ad[key] && 
                                        String(ad[key]).trim() !== '' &&
                                        isNaN(Number(ad[key])) // Ensure it's a text value
                                    ) {
                                        cardTitle = ad[key];
                                        break;
                                    }
                                }
                                
                                // Fallback to 'العنوان' if it exists and no other dynamic field was found
                                if (!cardTitle && ad['العنوان'] && String(ad['العنوان']).trim() !== '') {
                                    cardTitle = ad['العنوان'];
                                }

                                // Specific logic for 'مركبات' (vehicles)
                                if (ad.category === 'مركبات' && ad['نوع الوقود'] && String(ad['نوع الوقود']).trim() !== '') {
                                    if (cardTitle) {
                                        cardTitle = `${cardTitle} - ${ad['نوع الوقود']}`;
                                    } else {
                                        cardTitle = ad['نوع الوقود'];
                                    }
                                }

                                // General category/subcategory fallback if no specific title is found
                                if (!cardTitle) {
                                    if (ad.subsubsub && String(ad.subsubsub).trim() !== '') {
                                        cardTitle = ad.subsubsub;
                                    } else if (ad.subsub && String(ad.subsub).trim() !== '') {
                                        cardTitle = ad.subsub;
                                    } else if (ad.sub && String(ad.sub).trim() !== '') {
                                        cardTitle = ad.sub;
                                    } else if (ad.category && String(ad.category).trim() !== '') {
                                        cardTitle = ad.category;
                                    } else {
                                        cardTitle = 'إعلان بدون عنوان';
                                    }
                                }
                            }

                            adCard.innerHTML = `
                                <div class="ad-image-wrapper">
                                    ${hasImage ? `<img src="${mainImage}" alt="صورة الإعلان">` : `<div class="no-image-placeholder"><i class="fas fa-image"></i> لا توجد صورة</div>`}
                                </div>
                                <div class="ad-content">
                                    <h4>${cardTitle}</h4>
                                    <p class="ad-price"><i class="fas fa-tag"></i> <strong>السعر:</strong> ${ad['السعر'] || 'غير محدد'}</p>
                                    <p class="ad-location"><i class="fas fa-map-marker-alt"></i> <strong>المحافظة:</strong> ${ad['المحافظة'] || 'غير محدد'}</p>
                                </div>
                            `;
                            adsScrollWrapper.appendChild(adCard);
                        });

                        containerDiv.appendChild(adsScrollWrapper);

                        const viewAllButtonWrapper = document.createElement('div');
                        viewAllButtonWrapper.className = 'view-all-button-wrapper';
                        const viewAllButton = document.createElement('a');
                        viewAllButton.href = `php/fetch_ads.php?category=${encodeURIComponent(categoryName)}`;
                        viewAllButton.className = 'view-all-button';
                        viewAllButton.innerHTML = `عرض كل إعلانات ${categoryName} <i class="fas fa-arrow-right"></i>`;
                        viewAllButtonWrapper.appendChild(viewAllButton);
                        containerDiv.appendChild(viewAllButtonWrapper);

                        section.appendChild(containerDiv);
                        dynamicAdCategoriesContainer.appendChild(section);
                    }
                });

                if (!hasAdsToDisplay) {
                    dynamicAdCategoriesContainer.innerHTML = '<p class="no-ads-message" style="text-align: center; padding: 20px;">لا توجد إعلانات لعرضها في أي فئة حاليًا.</p>';
                }

            } else {
                dynamicAdCategoriesContainer.innerHTML = `<p class="no-ads-message" style="color: red; text-align: center; padding: 20px;">خطأ في جلب الإعلانات: ${result.error}</p>`;
                showNotification(`خطأ: ${result.error}`, 'error');
            }
        } catch (error) {
            dynamicAdCategoriesContainer.innerHTML = `<p class="no-ads-message" style="color: red; text-align: center; padding: 20px;">حدث خطأ غير متوقع: ${error.message}</p>`;
            showNotification(`خطأ غير متوقع: ${error.message}`, 'error');
            console.error('Error fetching categorized ads:', error);
        }
    }

    fetchAndRenderCategorizedAdsSliders();



    function handleFooterNav() {
    const footerNav = document.querySelector('.mobile-footer-nav');
    if (!footerNav) return; 

    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = footerNav.querySelectorAll('.nav-item');

    let isAnyLinkActive = false;
    navLinks.forEach(link => {
        const linkPage = (link.getAttribute('href') || '').split('/').pop();
        link.classList.remove('active'); 
        
        if (currentPage === linkPage || (currentPage === '' && linkPage === 'ads.php')) {
            link.classList.add('active');
            isAnyLinkActive = true;
        }
    });
    
    if (!isAnyLinkActive) {
        const homeLink = footerNav.querySelector('a[href="ads.php"]');
        if (homeLink) homeLink.classList.add('active');
    }

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.classList.contains('active')) {
                e.preventDefault();
                return;
            }
            this.classList.add('loading');
        });
    });
}

    function buildDynamicUI(isLoggedIn) {
        if (desktopNavContainer) {
            let desktopHTML = '';
            if (isLoggedIn) {
                const userName = localStorage.getItem('userName') || 'مستخدم';
                desktopHTML = `
                    <a href="/post.html" class="add-ad-link protected-link">أضف إعلانك</a>
                    <div class="user-menu" id="user-menu">
                        <button class="user-menu-trigger">
                            <img src="/image/avatar-placeholder.png" alt="Avatar">
                            <span class="ClassDN">${userName}</span>
                        </button>
                        <div class="user-menu-dropdown">
                            <a href="/my-ads.html" class="protected-link">إعلاناتي</a>
                            <a href="/php/favorite.php" class="protected-link">المفضلة</a>
                            <a href="/account.html">إعدادات الحساب</a>
                            <a href="#" id="logout-btn">تسجيل الخروج</a>
                        </div>
                    </div>
                `;
            } else {
                desktopHTML = `
                    <a href="/login.php" class ="nav-link">تسجيل الدخول</a>
                    <a href="/post.html" class="add-ad-link protected-link">أضف إعلانك</a>
                `;
            }
            desktopNavContainer.innerHTML = desktopHTML;
        }

        if (mobileFooterNav) {
            let mobileHTML = `
                <a href="/ads.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>الرئيسية</span>
                </a>
                <a href="/my-ads.php" class="nav-item protected-link">
                    <i class="fas fa-layer-group"></i>
                    <span>إعلاناتي</span>
                </a>
                <a href="/ads_new.php" class="nav-item add-ad-button protected-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>أضف إعلان</span>
                </a>
                <a href="/php/favorite.php" class="nav-item protected-link">
                    <i class="fas fa-heart"></i>
                    <span>المفضلة</span>
                </a>
            `;

            if (isLoggedIn) {
                mobileHTML += `
                    <a href="/account.php" class="nav-item">
                        <i class="fas fa-user-cog"></i>
                        <span>حسابي</span>
                    </a>
                `;
            } else {
                mobileHTML += `
                    <a href="/login.php" class="nav-item">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>دخول</span>
                    </a>
                `;
            }
            mobileFooterNav.innerHTML = mobileHTML;
        }
    }



    /**
     * الدالة المسؤولة عن حماية الروابط.
     */
    function applyLinkProtection(isLoggedIn) {
        document.body.addEventListener('click', (event) => {
            const protectedLink = event.target.closest('.protected-link');

            if (protectedLink && !isLoggedIn) {
                event.preventDefault();
                localStorage.setItem('redirectAfterLogin', protectedLink.href);
                window.location.href = 'login.php';
            }
        });
    }

    /**
     * الدالة المسؤولة عن تشغيل الوظائف التفاعلية والإضافية.
     */
    function initializePageWidgets() {
        const userMenu = document.getElementById('user-menu');
        if (userMenu) {
            const trigger = userMenu.querySelector('.user-menu-trigger');
            if (trigger) {
                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userMenu.classList.toggle('active');
                });
            }
        }
        document.addEventListener('click', () => {
            const userMenu = document.getElementById('user-menu');
            if (userMenu && userMenu.classList.contains('active')) {
                userMenu.classList.remove('active');
            }
        });

        document.body.addEventListener('click', (event) => {
            if (event.target.id === 'logout-btn') {
                event.preventDefault();
                localStorage.removeItem('userToken');
                localStorage.removeItem('userName');
                localStorage.removeItem('isLoggedIn');
                showNotification('تم تسجيل الخروج بنجاح.', 'success');
                window.location.reload();
            }
        });

        let heroSlideIndex = 0;
        const heroSlides = document.querySelectorAll('.hero-section .slide');

        function showHeroSlides() {
            if (heroSlides.length === 0) return;

            heroSlides.forEach((slide, index) => {
                slide.classList.remove('active');
            });
            heroSlideIndex++;
            if (heroSlideIndex > heroSlides.length) {
                heroSlideIndex = 1;
            }
            heroSlides[heroSlideIndex - 1].classList.add('active');
            setTimeout(showHeroSlides, 5000); // Change image every 5 seconds
        }

        showHeroSlides();

        function shortenName() {
            const nameElement = document.querySelector('.ClassDN');
            if (!nameElement) return;

            if (!nameElement.dataset.fullName) {
                nameElement.dataset.fullName = nameElement.textContent;
            }
            const fullName = nameElement.dataset.fullName;

            if (window.innerWidth <= 767) {
                nameElement.textContent = fullName.split(" ")[0];
            } else {
                nameElement.textContent = fullName;
            }
        }
        shortenName();
        window.addEventListener('resize', shortenName);
    }


});


// =======================================================================
// 5.  القائمة الجانبية 
// =======================================================================
const menuIcon = document.getElementById('menu-icon');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');
const closeSidebarBtn = document.getElementById('close-sidebar-btn');

// دالة لفتح القائمة
function openSidebar() {
    if (sidebar && sidebarOverlay) {
        sidebar.classList.add('active');
        sidebarOverlay.classList.add('active');
    }
}

// دالة لإغلاق القائمة
function closeSidebar() {
    if (sidebar && sidebarOverlay) {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
    }
}

// ربط الأحداث
if (menuIcon) {
    menuIcon.addEventListener('click', openSidebar);
}
if (closeSidebarBtn) {
    closeSidebarBtn.addEventListener('click', closeSidebar);
}
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
}

  // =======================================================================
// 6. منطق تقييم التطبيق المدمج
// =======================================================================
const ratingTrigger = document.getElementById('rating-trigger');
const ratingSection = ratingTrigger ? ratingTrigger.closest('.rating-section') : null;
const ratingStars = document.querySelectorAll('.rating-stars-container .stars i');

if (ratingTrigger && ratingSection) {
    ratingTrigger.addEventListener('click', () => {
        // فتح وإغلاق قسم النجوم
        ratingSection.classList.toggle('open');
    });
}

ratingStars.forEach(star => {
    star.addEventListener('click', () => {
        const ratingValue = parseInt(star.dataset.value);
        sendRatingViaWhatsApp(ratingValue);
        
        // تلوين النجوم المختارة
        ratingStars.forEach((s, index) => {
            s.classList.toggle('selected', index < ratingValue);
        });
    });
});

function sendRatingViaWhatsApp(starCount) {
    const message = `مرحباً، تقييمي لموقعكم هو ${starCount} نجمة من 5.`;
    const yourPhoneNumber = "963992679030"; 
    const url = `https://wa.me/${yourPhoneNumber}?text=${encodeURIComponent(message)}`;
    
    // افتح الرابط في نافذة جديدة
    window.open(url, '_blank');
    
    // إغلاق قائمة التقييم بعد الاختيار
    setTimeout(() => {
        if (ratingSection) {
            ratingSection.classList.remove('open');
        }
    }, 1000); // تأخير بسيط
}


  const userToken = localStorage.getItem("userToken");
  const isLoggedIn = !!userToken;

  const desktopNavContainer = document.getElementById("desktop-auth-container");
  const mobileFooter = document.querySelector(".mobile-footer-nav");

  if (desktopNavContainer) {
    let desktopHTML = "";
    if (isLoggedIn) {
      desktopHTML = `
                <a href="post.html" class="add-ad-link protected-link">أضف إعلانك</a>
                <div class="user-menu" id="user-menu">
                    <button class="user-menu-trigger">
                        <img src="image/avatar-placeholder.png" alt="Avatar">
                    </button>
                    <div class="user-menu-dropdown">
                        <a href="my-ads.html" class="protected-link">إعلاناتي</a>
                        <a href="php/favorite.php" class="protected-link">المفضلة</a>
                        <div class="divider"></div>
                        <a href="account.html">إعدادات الحساب</a>
                        <a href="#" id="logout-btn">تسجيل الخروج</a>
                    </div>
                </div>
            `;
    } else {
      desktopHTML = `
                <a href="login.php" class="nav-link">تسجيل الدخول</a>
                <a href="post.html" class="add-ad-link protected-link">أضف إعلانك</a>
            `;
    }
    desktopNavContainer.innerHTML = desktopHTML;
  }

  if (mobileFooter) {
    const accountLink = document.getElementById("account-link-mobile");
    if (accountLink) {
      if (isLoggedIn) {
        accountLink.href = "account.html";
        accountLink.innerHTML = `
                    <i class="fas fa-user-circle"></i>
                    <span>حسابي</span>
                `;
      } else {
        accountLink.href = "login.php";
        accountLink.innerHTML = `
                    <i class="fas fa-sign-in-alt"></i>
                    <span>دخول</span>
                `;
      }
    }
    document.querySelectorAll(".protected-link").forEach((link) => {
      if (!isLoggedIn && link.closest(".mobile-footer-nav")) {
        link.addEventListener("click", (e) => {
          e.preventDefault();
          localStorage.setItem("redirectAfterLogin", link.href);
          window.location.href = "login.php";
        });
      }
    });
  }

  document.body.addEventListener("click", (event) => {
    const protectedLink = event.target.closest(".protected-link");
    if (
      protectedLink &&
      !protectedLink.closest(".mobile-footer-nav") &&
      !isLoggedIn
    ) {
      event.preventDefault();
      localStorage.setItem("redirectAfterLogin", protectedLink.href);
      window.location.href = "login.php";
    }
  });

  const userMenu = document.getElementById("user-menu");
  if (userMenu) {
    const trigger = userMenu.querySelector(".user-menu-trigger");
    if (trigger) {
      trigger.addEventListener("click", (e) => {
        e.stopPropagation();
        userMenu.classList.toggle("active");
      });
    }
  }
  document.addEventListener("click", () => {
    const userMenu = document.getElementById("user-menu");
    if (userMenu && userMenu.classList.contains("active")) {
      userMenu.classList.remove("active");
    }
  });

  document.body.addEventListener("click", (event) => {
    if (event.target.id === "logout-btn") {
      event.preventDefault();
      localStorage.removeItem("userToken");
      alert("تم تسجيل الخروج بنجاح.");
      window.location.reload();
    }
  });

  const searchForm = document.getElementById("main-search-form");
  if (searchForm) {
    const searchInput = document.getElementById("main-search-input");
    const suggestionsContainer = document.getElementById("search-suggestions");

    function performSearch(term) {
      if (term.trim()) {
        saveRecentSearch(term.trim());
        window.location.href = `php/search_results.php?q=${encodeURIComponent(
          term.trim()
        )}`;
      }
    }

    searchForm.addEventListener("submit", (e) => {
      e.preventDefault();
      performSearch(searchInput.value);
    });

    function getRecentSearches() {
      try {
        return JSON.parse(localStorage.getItem("recentSearches")) || [];
      } catch (e) {
        return [];
      }
    }

    function saveRecentSearch(term) {
      let searches = getRecentSearches();
      searches = searches.filter((s) => s.toLowerCase() !== term.toLowerCase());
      searches.unshift(term);
      if (searches.length > 6) {
        searches.pop();
      }
      localStorage.setItem("recentSearches", JSON.stringify(searches));
    }

    function renderSuggestions() {
      if (!suggestionsContainer) return;
      const searches = getRecentSearches();

      if (searches.length > 0) {
        suggestionsContainer.innerHTML = "<h6>عمليات البحث الأخيرة</h6>";
        searches.forEach((term) => {
          const item = document.createElement("a");
          item.className = "suggestion-item";
          item.href = `php/search_results.php?q=${encodeURIComponent(term)}`;
          item.innerHTML = `<i class="fas fa-history"></i><span>${term}</span>`;
          suggestionsContainer.appendChild(item);
        });
        suggestionsContainer.style.display = "block";
      } else {
        suggestionsContainer.style.display = "none";
      }
    }

    searchInput.addEventListener("focus", renderSuggestions);

    document.addEventListener("click", (event) => {
      if (suggestionsContainer && !searchForm.contains(event.target)) {
        suggestionsContainer.style.display = "none";
      }
    });
  }
    
 


