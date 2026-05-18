<?php

require_once 'db_connect.php';
require_once 'auth_check.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    echo "رقم المستخدم غير صالح.";
    exit;
}

// --- ✅ تعديل 1: إضافة حقل `images_paths` إلى استعلام SQL ---
$stmt = $pdo->prepare("
    SELECT fs.*, u.username, fs.images_paths 
    FROM form_submissions fs
    LEFT JOIN users u ON fs.user_id = u.id
    WHERE fs.user_id = ?
    ORDER BY fs.submitted_at DESC
");

$stmt->execute([$user_id]);
$ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
$user_id = $_SESSION['user_id'] ?? null;
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$ads_for_js = [];
foreach ($ads as $ad) { 
    $data = json_decode($ad['json_data'], true);
    if (json_last_error() !== JSON_ERROR_NONE) continue;

     // 1. احصل على نص JSON من حقل الصور، وافترض أنه مصفوفة فارغة '[]' إذا كان فارغاً
    $imagePathsJson = $ad['images_paths'] ?? '[]';

    // 2. قم بفك تشفير نص الـ JSON لتحويله إلى مصفوفة PHP حقيقية
    $decodedImages = json_decode($imagePathsJson, true);

    // 3. تحقق من أن الناتج هو مصفوفة (لتجنب الأخطاء)، ثم قم بتعيينها
    if (is_array($decodedImages)) {
        $data['images'] = $decodedImages;
    } else {
        // في حال وجود بيانات غير صالحة، أرسل مصفوفة فارغة
        $data['images'] = [];
    }-

    $data['id'] = $ad['id'];
    $data['submitted_at'] = $ad['submitted_at']; // مهم للترتيب
    $data['user_id'] = (int)$ad['user_id'];
    $data['category'] = $ad['category'];
    $data['sub'] = $ad['sub'];
    $data['subsub'] = $ad['subsub'];
    $data['subsubsub'] = $ad['subsubsub'] ?? null; // التأكد من وجودها إذا لم تكن في json_data
    $ad['السعر'] = $ad['السعر']  ?? null;

     // فحص هل الإعلان مفضل من قبل المستخدم الحالي
    if ($user_id) {
        try {
            $stmt_fav = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :user_id AND ad_id = :ad_id");
            $stmt_fav->execute([
                ':user_id' => $user_id,
                ':ad_id' => $ad['id']
            ]);
            $data['is_favorited'] = (bool)$stmt_fav->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database Error checking favorite status: " . $e->getMessage());
            $data['is_favorited'] = false; // Default to not favorited on error
        }
    } else {
        $data['is_favorited'] = false;
    }

    // --- Start of cardTitle generation logic ---
    $excludedForFirstPart = [
        'id', 'submitted_at', 'category', 'sub', 'subsub', 'subsubsub',
        'الصورة', 'السعر', 'المحافظة', 'رقم الهاتف', 'رقم الواتس',
        'الوصف', 'الميزات', 'images', 'is_favorited', 'user_id',
        'التصنيف'
    ];
    $mainTitlePart = '';
    $subSubPart = '';
    $cardTitle = '';

    foreach ($data as $key => $value) {
        if (!in_array($key, $excludedForFirstPart) &&
            $value !== null && trim((string)$value) !== '' &&
            !is_numeric($value)) {
            $mainTitlePart = (string)$value;
            break;
        }
    }

    if (isset($data['subsub']) && trim((string)$data['subsub']) !== '') {
        $subSubPart = (string)$data['subsub'];
    }

    if ($mainTitlePart && $subSubPart) {
        $cardTitle = $mainTitlePart . ' ' . $subSubPart;
    } elseif ($mainTitlePart) {
        $cardTitle = $mainTitlePart;
    } elseif ($subSubPart) {
        $cardTitle = $subSubPart;
    } else {
        if (isset($data['subsubsub']) && trim((string)$data['subsubsub']) !== '') {
            $cardTitle = (string)$data['subsubsub'];
        } elseif (isset($data['subsub']) && trim((string)$data['subsub']) !== '') {
            $cardTitle = (string)$data['subsub'];
        } elseif (isset($data['sub']) && trim((string)$data['sub']) !== '') {
            $cardTitle = (string)$data['sub'];
        } elseif (isset($data['category']) && trim((string)$data['category']) !== '') {
            $cardTitle = (string)$data['category'];
        } else {
            $cardTitle = 'إعلان بدون عنوان';
        }
    }

    if (isset($data['category']) && $data['category'] === 'مركبات' && isset($data['نوع الوقود']) && trim((string)$data['نوع الوقود']) !== '') {
        $cardTitle .= ' - ' . (string)$data['نوع الوقود'];
    }
    $data['card_title'] = $cardTitle;
    $ads_for_js[] = $data;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعلانات <?php echo htmlspecialchars($category ?? ''); ?></title>
     <link rel="stylesheet" href="../css/fetch_ads.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/syriazzle.css">
    <link rel="stylesheet" href="../css/normalize.css">
    <link rel="stylesheet" href="../css/all.min.css">
    <link rel="stylesheet" href="../css/main_header.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header_store.php'; ?>
       <?php
        $username = htmlspecialchars($ads[0]['username'] ?? 'غير معروف');
        
        echo "<h2 style='background-color: #000000;
    padding: 15px;
    box-shadow: 2px 2px 14px white inset;
    border-radius: 15px;
    margin: 20px 20px;' class='ads-title'>";
        echo "<span style='margin-bottom: 15px;
    font-size: 19px;
    font-weight: bold !important;' class='red-part'>متجر</span>";
        echo "<span style='font-size: 20px;
    margin-bottom: 15px;
    display: block;
}' class='black-part neon-effect'>" . $username . "</span>";
        echo "</h2>";
        ?>
    <div class="sub-categories-bar" id="sub-categories-bar"></div>
    <div class="parent" id="ads-container">
    </div>
    <button class="filter-fab" id="filter-fab"><i class="fas fa-filter"></i> فلترة</button>
    <div class="overlay" id="overlay"></div>
    <div class="filter-drawer" id="filter-drawer">
        <div class="drawer-header">
            الفلاتر
            <span class="close-btn" id="close-drawer-btn">×</span>
        </div>
        <div class="drawer-content" id="drawer-content"></div>
        <div class="drawer-footer">
            <button id="reset-filters-btn">إعادة تعيين</button>
            <button id="apply-filters-btn">عرض النتائج</button>
        </div>
    </div>
    <div id="adDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header-actions">
                <span class="modal-close">&times;</span>
                </div>
            <div class="modal-image-carousel">
                <img id="modalMainImage" src="" alt="صورة الإعلان الرئيسية">
                <button class="nav-arrow left" id="modalImagePrev"><i class="fas fa-chevron-left"></i></button>
                <button class="nav-arrow right" id="modalImageNext"><i class="fas fa-chevron-right"></i></button>
                <span class="image-count" id="modalImageCount">1/1</span>
            </div>
            <div id="modalThumbnailsGallery" class="modal-thumbnails-gallery">
                </div>
            <div class="modal-scrollable-content">
                <h2 class="modal-ad-title" id="modalTitle">تفاصيل الإعلان</h2>
                <p class="modal-price" id="modalPrice">غير محدد</p>
                <div class="modal-section">
                    <h4><i class="fas fa-info-circle"></i> معلومات الإعلان</h4>
                    <div id="modalDetails">
                        </div>
                </div>
                <div class="modal-section" id="modalDescriptionSection" style="display:none;">
                    <h4><i class="fas fa-file-alt"></i> الوصف</h4>
                    <p class="modal-description" id="modalDescription"></p>
                </div>
                <div class="modal-section" id="modalFeaturesSection" style="display:none;">
                    <h4><i class="fas fa-star"></i> الميزات</h4>
                    <div id="modalFeatures" class="modal-tags-container">
                        </div>
                </div>
                <div class="modal-section" id="modalCategoriesSection" style="display:none;">
                    <h4><i class="fas fa-tags"></i> التصنيفات</h4>
                    <div id="modalCategories" class="modal-tags-container">
                        </div>
                </div>
                </div>
            <div class="modal-contact-buttons-fixed">
                <a id="modalCallBtn" href="#" class="btn-call"><i class="fas fa-phone-alt"></i> اتصال مباشر</a>
                <a id="modalWhatsappBtn" href="#" target="_blank" class="btn-whatsapp"><i class="fab fa-whatsapp"></i> تواصل عبر واتساب</a>
            </div>
        </div>
    </div>
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="chatAdTitle">مراسلة بخصوص الإعلان</h2>
                <span class="modal-close" id="closeMessageModalBtn">&times;</span>
            </div>
            <div class="modal-body">
                <div class="messages-display" id="messagesDisplay">
                    </div>
                <div class="message-input-container">
                    <textarea id="messageInput" placeholder="اكتب رسالتك هنا..."></textarea>
                    <button id="sendMessageBtn"><i class="fas fa-paper-plane"></i> إرسال</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    const allAdsData = <?php echo json_encode($ads_for_js, JSON_UNESCAPED_UNICODE); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const currentLoggedInUserId = <?php echo json_encode($current_user_id); ?>;
        const adsContainer = document.getElementById('ads-container');
        const subCategoryChipsContainer = document.getElementById('sub-categories-bar');
        const filterFab = document.getElementById('filter-fab');
        const filterDrawer = document.getElementById('filter-drawer');
        const drawerContent = document.getElementById('drawer-content');
        const overlay = document.getElementById('overlay');
        const messageModal = document.getElementById('messageModal');
        const closeMessageModalBtn = document.getElementById('closeMessageModalBtn');
        const chatAdTitle = document.getElementById('chatAdTitle');
        const messagesDisplay = document.getElementById('messagesDisplay');
        const messageInput = document.getElementById('messageInput');
        const sendMessageBtn = document.getElementById('sendMessageBtn');
        let currentChatAdId = null;
        let currentChatOtherUserId = null; 
        let messagePollingInterval = null; 
        let lastFetchedMessageCount = 0; 
        const adDetailsModal = document.getElementById('adDetailsModal');
        let currentFilters = { selectedSubCategory: 'all' };

        function renderAds(ads) {
            adsContainer.innerHTML = '';
            if (ads.length === 0) {
                adsContainer.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; font-size: 1.2rem; padding: 40px;">لا توجد إعلانات تطابق بحثك.</p>';
                return;
            }
            ads.forEach(adData => {
                const images = adData.images || [];
                const imageUrl = images.length > 0 ? `../${images[0]}` : 'https://via.placeholder.com/300x200/f4f5f7/ccc?text=No+Image';
                const whatsappNumber = (adData['رقم الواتس'] || '').replace(/[^0-9]/g, '');
                const adCardTitle = adData.card_title || 'إعلان بدون عنوان';

                // --- ✅ تعديل 3: تم تصحيح وسم `<img>` بإضافة `>` في نهايته ---
                const adCardHTML = `
                    <div class="child" data-ad-id="${adData.id}">
                        <img src="${imageUrl}" alt="صورة الإعلان">
                        <h3>${adCardTitle}</h3>
                        <div class="info-row">
                        <p class="price">${adData['السعر']}</p>
                            <p class="location">${adData['المحافظة'] || ''}</p>
                        </div>
                        <div class="actions-row">
                            <a href="tel:${adData['رقم الهاتف'] || ''}" class="btn-call">اتصال</a>
                            <a href="https://wa.me/${whatsappNumber}?text=${encodeURIComponent(' لقد قرأت اعلانك على موقع Syriazzle بخصوص ' + adCardTitle + 'رابط الاعلان الخاص بك' + '\n' + window.location.origin + '/ad_details.php?id=' + adData.id)}" target="_blank" class="btn-whatsapp">واتساب</a>
                            <button class="btn-message" data-ad-id="${adData.id}" data-owner-id="${adData.user_id}">
                                <i class="fas fa-comments"></i> مراسلة
                            </button>
                            <button class="favorite-btn ${adData.is_favorited ? 'is-favorite' : ''}" data-ad-id="${adData.id}">
                                <i class="${adData.is_favorited ? 'fas' : 'far'} fa-heart"></i>
                            </button>
                        </div>
                    </div>`;
                adsContainer.innerHTML += adCardHTML;
            });

            document.querySelectorAll('.child').forEach(card => {
                card.addEventListener('click', (event) => {
                    if (event.target.closest('.actions-row')) {
                        return;
                    }
                    const adId = parseInt(card.dataset.adId);
                    window.location.href = `../ad_details.php?id=${adId}`;
                });
            });
        }

        adsContainer.addEventListener('click', (event) => {
            const favoriteBtn = event.target.closest('.favorite-btn');
            if (favoriteBtn) {
                const adId = parseInt(favoriteBtn.dataset.adId);
                if (adId) {
                    const adToUpdate = allAdsData.find(ad => ad.id === adId);
                    if (adToUpdate) {
                        adToUpdate.is_favorited = !adToUpdate.is_favorited; 
                        const icon = favoriteBtn.querySelector('i');
                        if (adToUpdate.is_favorited) {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            favoriteBtn.classList.add('is-favorite');
                        } else {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            favoriteBtn.classList.remove('is-favorite');
                        }

                        fetch('toggle_favorite.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `ad_id=${adId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                adToUpdate.is_favorited = !adToUpdate.is_favorited;
                                if (adToUpdate.is_favorited) {
                                    icon.classList.remove('far'); icon.classList.add('fas'); favoriteBtn.classList.add('is-favorite');
                                } else {
                                    icon.classList.remove('fas'); icon.classList.add('far'); favoriteBtn.classList.remove('is-favorite');
                                }
                                console.error('Failed to toggle favorite:', data.message);
                                alert('حدث خطأ: ' + (data.message || 'فشل تحديث المفضلة.'));
                            }
                        })
                        .catch(error => {
                            adToUpdate.is_favorited = !adToUpdate.is_favorited;
                            if (adToUpdate.is_favorited) {
                                icon.classList.remove('far'); icon.classList.add('fas'); favoriteBtn.classList.add('is-favorite');
                            } else {
                                icon.classList.remove('fas'); icon.classList.add('far'); favoriteBtn.classList.remove('is-favorite');
                            }
                            console.error('Network error toggling favorite:', error);
                            alert('حدث خطأ في الاتصال بالخادم أثناء تحديث المفضلة.');
                        });
                    }
                }
            } else if (event.target.closest('.btn-message')) { 
                const messageBtn = event.target.closest('.btn-message');
                const adId = parseInt(messageBtn.dataset.adId);
                const ownerId = parseInt(messageBtn.dataset.ownerId);

                if (currentLoggedInUserId === 0) {
                    alert('يجب تسجيل الدخول لتتمكن من مراسلة صاحب الإعلان.');
                    return;
                }
                const adTitleText = messageBtn.closest('.child').querySelector('h3').textContent;
                openMessageModal(adId, ownerId, adTitleText);
            }
        });

        function openMessageModal(adId, ownerId, adTitle) {
            currentChatAdId = adId;
            currentChatOtherUserId = ownerId;
            chatAdTitle.textContent = `مراسلة بخصوص: ${adTitle}`;
            messagesDisplay.innerHTML = ''; 
            messageInput.value = ''; 
            lastFetchedMessageCount = 0; 
            fetchMessages(adId, ownerId); 
            if (messagePollingInterval) { clearInterval(messagePollingInterval); }
            messagePollingInterval = setInterval(() => { fetchMessages(adId, ownerId); }, 3000); 
            messageModal.style.display = 'block';
            adDetailsModal.style.display = 'none';
            overlay.classList.add('open'); 
        }

        closeMessageModalBtn.addEventListener('click', () => {
            messageModal.style.display = 'none';
            overlay.classList.remove('open');
            if (messagePollingInterval) { clearInterval(messagePollingInterval); messagePollingInterval = null; }
            currentChatAdId = null;
            currentChatOtherUserId = null;
        });

        overlay.addEventListener('click', () => {
            if (messageModal.style.display === 'block') {
                closeMessageModalBtn.click();
            } else if (adDetailsModal.style.display === 'block') {
                adDetailsModal.style.display = 'none';
                overlay.classList.remove('open');
            } else if (filterDrawer.classList.contains('open')) {
                toggleDrawer(false);
            }
        });

        async function fetchMessages(adId, otherUserId) {
            try {
                const response = await fetch(`get_messages.php?ad_id=${adId}&other_user_id=${otherUserId}`);
                const data = await response.json();
                if (data.success) {
                    if (data.messages.length > lastFetchedMessageCount || lastFetchedMessageCount === 0) {
                        displayMessages(data.messages);
                        lastFetchedMessageCount = data.messages.length;
                    }
                } else {
                    console.error('Failed to fetch messages:', data.message);
                    if (messagesDisplay.innerHTML === '' || messagesDisplay.innerHTML.includes('error-message')) {
                         messagesDisplay.innerHTML = `<p class="error-message">لم يتمكن من جلب الرسائل: ${data.message}</p>`;
                    }
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
                if (messagesDisplay.innerHTML === '' || messagesDisplay.innerHTML.includes('error-message')) {
                    messagesDisplay.innerHTML = `<p class="error-message">حدث خطأ في الاتصال أثناء جلب الرسائل.</p>`;
                }
            }
        }

        function displayMessages(messages) {
            const isScrolledToBottom = messagesDisplay.scrollHeight - messagesDisplay.clientHeight <= messagesDisplay.scrollTop + 1;
            messagesDisplay.innerHTML = ''; 
            if (messages.length === 0) {
                messagesDisplay.innerHTML = '<p class="no-messages">لا توجد رسائل سابقة في هذه المحادثة. ابدأ بمراسلة صاحب الإعلان.</p>';
                return;
            }
            messages.forEach(msg => {
                const messageClass = msg.sender_id === currentLoggedInUserId ? 'sent' : 'received';
                const senderName = msg.sender_id === currentLoggedInUserId ? 'أنت' : msg.sender_username;
                const messageHtml = `
                    <div class="message-bubble ${messageClass}">
                        <span class="message-sender">${senderName}</span>
                        <p class="message-text">${msg.message_text}</p>
                        <span class="message-time">${new Date(msg.sent_at).toLocaleString('ar-SY')}</span>
                    </div>
                `;
                messagesDisplay.innerHTML += messageHtml;
            });
            if (isScrolledToBottom || messages.length > lastFetchedMessageCount) {
                messagesDisplay.scrollTop = messagesDisplay.scrollHeight;
            }
        }

        sendMessageBtn.addEventListener('click', async () => {
            const messageText = messageInput.value.trim();
            if (!messageText) return;
            if (!currentChatAdId || !currentChatOtherUserId) return;

            const formData = new FormData();
            formData.append('ad_id', currentChatAdId);
            formData.append('message_text', messageText);

            try {
                const response = await fetch('send_message.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    messageInput.value = ''; 
                    fetchMessages(currentChatAdId, currentChatOtherUserId);
                } else {
                    alert('فشل إرسال الرسالة: ' + (data.message || 'خطأ غير معروف.'));
                }
            } catch (error) {
                alert('حدث خطأ في الاتصال أثناء إرسال الرسالة.');
            }
        });

        function populateSubCategoryChips() {
            const allSubCategories = new Set();
            allAdsData.forEach(ad => {
                if (ad.sub && String(ad.sub).trim() !== '') allSubCategories.add(ad.sub);
                if (ad.subsub && String(ad.subsub).trim() !== '') allSubCategories.add(ad.subsub);
                if (ad.subsubsub && String(ad.subsubsub).trim() !== '') allSubCategories.add(ad.subsubsub);
            });
            const sortedSubCategories = [...allSubCategories].sort();
            let chipsHTML = `<span class="sub-category-chip active" data-sub="all">الكل</span>`;
            sortedSubCategories.forEach(sub => {
                chipsHTML += `<span class="sub-category-chip" data-sub="${sub}">${sub}</span>`;
            });
            subCategoryChipsContainer.innerHTML = chipsHTML;

            document.querySelectorAll('.sub-category-chip').forEach(chip => {
                chip.addEventListener('click', () => {
                    document.querySelector('.sub-category-chip.active')?.classList.remove('active');
                    chip.classList.add('active');
                    currentFilters.selectedSubCategory = chip.dataset.sub;
                    applyAndRender();
                });
            });
        }

        function buildFilterDrawer() {
            const adsForDrawer = currentFilters.selectedSubCategory === 'all'
                ? allAdsData
                : allAdsData.filter(ad =>
                    ad.sub === currentFilters.selectedSubCategory ||
                    ad.subsub === currentFilters.selectedSubCategory ||
                    ad.subsubsub === currentFilters.selectedSubCategory
                );
            const brands = [...new Set(adsForDrawer.map(ad => ad['الماركة']).filter(Boolean))].sort();
            const locations = [...new Set(adsForDrawer.map(ad => ad['المحافظة']).filter(Boolean))].sort();
            let html = `<div class="filter-section"><h4>الترتيب</h4><select id="sort-by"><option value="date-desc">الأحدث أولاً</option><option value="price-desc">من الأغلى للأرخص</option><option value="price-asc">من الأرخص للأغلى</option></select></div><div class="filter-section"><h4>السعر</h4><div class="price-inputs"><input type="number" id="price-from" placeholder="من"><input type="number" id="price-to" placeholder="إلى"></div></div>`;
            if (locations.length > 0) html += `<div class="filter-section"><h4>المحافظة</h4><select id="filter-location"><option value="all">كل المواقع</option>${locations.map(l => `<option value="${l}">${l}</option>`).join('')}</select></div>`;
            if (brands.length > 0) html += `<div class="filter-section"><h4>الماركة</h4><select id="filter-brand"><option value="all">كل الماركات</option>${brands.map(b => `<option value="${b}">${b}</option>`).join('')}</select></div>`;
            drawerContent.innerHTML = html;
        }

        function applyAndRender() {
            let processedAds = [...allAdsData];
            if (currentFilters.selectedSubCategory && currentFilters.selectedSubCategory !== 'all') {
                processedAds = processedAds.filter(ad =>
                    ad.sub === currentFilters.selectedSubCategory ||
                    ad.subsub === currentFilters.selectedSubCategory ||
                    ad.subsubsub === currentFilters.selectedSubCategory
                );
            }
            if (currentFilters.location && currentFilters.location !== 'all') processedAds = processedAds.filter(ad => ad['المحافظة'] === currentFilters.location);
            if (currentFilters.brand && currentFilters.brand !== 'all') processedAds = processedAds.filter(ad => ad['الماركة'] === currentFilters.brand);
            const priceFrom = parseInt(currentFilters.price_from);
            const priceTo = parseInt(currentFilters.price_to);
            if (!isNaN(priceFrom) && priceFrom > 0) processedAds = processedAds.filter(ad => (ad['السعر'] || 0) >= priceFrom);
            if (!isNaN(priceTo) && priceTo > 0) processedAds = processedAds.filter(ad => (ad['السعر'] || 0) <= priceTo);
            const sortBy = currentFilters.sort || 'date-desc';
            if (sortBy === 'price-desc') processedAds.sort((a, b) => (b['السعر'] || 0) - (a['السعر'] || 0));
            else if (sortBy === 'price-asc') processedAds.sort((a, b) => (a['السعر'] || 0) - (b['السعر'] || 0));
            else processedAds.sort((a, b) => new Date(b.submitted_at || 0) - new Date(a.submitted_at || 0));
            renderAds(processedAds);
        }

        const toggleDrawer = (open) => {
            filterDrawer.classList.toggle('open', open);
            overlay.classList.toggle('open', open);
        };

        filterFab.addEventListener('click', () => {
            buildFilterDrawer(); 
            document.getElementById('sort-by').value = currentFilters.sort || 'date-desc';
            document.getElementById('price-from').value = currentFilters.price_from || '';
            document.getElementById('price-to').value = currentFilters.price_to || '';
            const locationSelect = document.getElementById('filter-location');
            if(locationSelect) locationSelect.value = currentFilters.location || 'all';
            const brandSelect = document.getElementById('filter-brand');
            if(brandSelect) brandSelect.value = currentFilters.brand || 'all';
            toggleDrawer(true);
        });

        document.getElementById('close-drawer-btn').addEventListener('click', () => toggleDrawer(false));
        document.getElementById('apply-filters-btn').addEventListener('click', () => {
            currentFilters.sort = document.getElementById('sort-by').value;
            currentFilters.price_from = document.getElementById('price-from').value;
            currentFilters.price_to = document.getElementById('price-to').value;
            const locationSelect = document.getElementById('filter-location');
            if(locationSelect) currentFilters.location = locationSelect.value;
            const brandSelect = document.getElementById('filter-brand');
            if(brandSelect) currentFilters.brand = brandSelect.value;
            applyAndRender();
            toggleDrawer(false);
        });

        document.getElementById('reset-filters-btn').addEventListener('click', () => {
            const selectedSubCategory = currentFilters.selectedSubCategory; 
            currentFilters = { selectedSubCategory: selectedSubCategory }; 
            applyAndRender();
            toggleDrawer(false);
        });

        populateSubCategoryChips();
        applyAndRender();
    });
    </script>
</body>
</html>