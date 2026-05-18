<?php require_once 'php/db_connect.php';?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>My ads</title>

    <link rel="icon" href="image/favicon.png" type="image/png">

    <link rel="stylesheet" href="css/all.min.css" />

    <link rel="stylesheet" href="css/dubizzle-inspired.css" />

    <link rel="stylesheet" href="css/main_header.css" />

    <link rel="stylesheet" href="css/style.css" />

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">



    <style>

      /* ===================

         المتغيرات الرئيسية

      =================== */

      :root {

        --primary-color: #007bff; /* لون أساسي جديد وأكثر حيوية */

        --primary-hover: #0056b3;

        --danger-color: #dc3545;

        --danger-hover: #c82333;

        --success-color: #28a745;

        --warning-color: #ffc107;

        --light-gray: #f8f9fa;

        --medium-gray: #e9ecef;

        --dark-gray: #6c757d;

        --text-dark: #343a40;

        --card-bg: #ffffff;

        --border-radius-md: 12px;

        --border-radius-sm: 8px;

        --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06);

        --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);

      }



      body {

        font-family: "Cairo", sans-serif;

        background-color: var(--light-gray);

        color: var(--text-dark);

        margin: 0;

      }



      .container {

        max-width: 1200px;

        margin: 0 auto;

        padding: 20px;

      }



      /* ===================

         بطاقة الترحيب والإحصائيات

      =================== */

      .welcome-card {

        background: linear-gradient(135deg, var(--text-dark), #495057);

        color: #fff;

        padding: 30px;

        border-radius: var(--border-radius-md);

        margin-bottom: 30px;

        display: flex;

        justify-content: space-between;

        align-items: center;

        flex-wrap: wrap;

        gap: 20px;

      }



      .welcome-text h1 {

        margin: 0 0 10px 0;

        font-size: 26px;

        font-weight: 700;

      }



      .welcome-text p {

        margin: 0;

        font-size: 16px;

        opacity: 0.9;

      }



      .stats-grid {

        display: flex;

        gap: 30px;

        text-align: center;

      }



      .stat-item .stat-number {

        font-size: 28px;

        font-weight: 700;

      }



      .stat-item .stat-label {

        font-size: 14px;

        opacity: 0.9;

      }



      /* ===================

         رأس لوحة التحكم

      =================== */

      .dashboard-header {

        display: flex;

        justify-content: space-between;

        align-items: center;

        margin-bottom: 25px;

        flex-wrap: wrap;

        gap: 15px;

      }



      .filter-tabs {

        display: flex;

        gap: 8px;

        background-color: var(--medium-gray);

        padding: 6px;

        border-radius: 50px;

      }



      .filter-tabs .tab {

        background: none;

        border: none;

        padding: 8px 20px;

        border-radius: 50px;

        font-weight: 600;

        cursor: pointer;

        transition: all 0.3s ease;

        color: var(--text-dark);

      }



      .filter-tabs .tab.active {

        background-color: #fff;

        color: var(--primary-color);

        box-shadow: var(--shadow-sm);

      }

      

      .action-buttons {

          display: flex;

          gap: 15px;

      }



      .add-new-btn, .your_message {

        color: #fff;

        text-decoration: none;

        padding: 10px 20px;

        border-radius: var(--border-radius-sm);

        font-weight: 700;

        display: inline-flex;

        align-items: center;

        gap: 8px;

        transition: background-color 0.3s ease;

        border: none;

      }

      

      .add-new-btn {

        background-color: var(--success-color);

      }

      

      .add-new-btn:hover {

        background-color: #218838;

      }

      

      .your_message {

          background-color: var(--primary-color);

      }

      

      .your_message:hover {

          background-color: var(--primary-hover);

      }



      /* ========================

         بطاقة الإعلان (التصميم الجديد)

      ======================== */

      .my-ad-card {

        background-color: var(--card-bg);

        border-radius: var(--border-radius-md);

        box-shadow: var(--shadow-sm);

        display: flex;

        gap: 20px;

        padding: 20px;

        margin-bottom: 20px;

        border: 1px solid #e9ecef;

        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;

      }

      

      .my-ad-card:hover {

          transform: translateY(-5px);

          box-shadow: var(--shadow-md);

      }



      .my-ad-card img {

        width: 150px;

        height: 150px;

        object-fit: cover;

        border-radius: var(--border-radius-sm);

        flex-shrink: 0;

      }



      .my-ad-info {

        flex-grow: 1;

        display: flex;

        flex-direction: column;

      }

      

      .my-ad-info .ad-details {

          flex-grow: 1;

      }



      .my-ad-info h3 {

        margin: 0 0 12px 0;

        font-size: 20px;

        font-weight: 700;

        color: var(--text-dark);

        cursor: pointer;

      }

      

      .my-ad-info h3:hover {

          color: var(--primary-color);

      }



      .ad-meta-data {

        display: flex;

        gap: 25px;

        font-size: 15px;

        color: var(--dark-gray);

        margin-bottom: 15px;

        flex-wrap: wrap;

        align-items: center;

      }



      .ad-meta-data span {

        display: flex;

        align-items: center;

        gap: 8px;

      }

      .ad-meta-data span i {

        color: var(--primary-color);

      }



      /* ===================

         حالة الإعلان

      =================== */

      .ad-status {

        font-weight: 700;

        padding: 4px 12px;

        border-radius: 50px;

        font-size: 12px;

        text-transform: uppercase;

        letter-spacing: 0.5px;

        float: left; /* لجعلها على يسار العنوان */

        margin-right: 15px;

      }



      .ad-status.active {

        color: var(--success-color);

        background-color: rgba(40, 167, 69, 0.1);

      }



      .ad-status.pending {

        color: #b58900;

        background-color: rgba(255, 193, 7, 0.1);

      }



      .ad-status.expired {

        color: var(--dark-gray);

        background-color: rgba(108, 117, 125, 0.1);

      }



      /* ===================

         أزرار الإجراءات داخل البطاقة

      =================== */

      .ad-actions {

        display: flex;

        gap: 12px;

        margin-top: 15px; 

      }



      .ad-actions .btn {

        text-decoration: none;

        padding: 8px 16px;

        border-radius: var(--border-radius-sm);

        font-weight: 600;

        font-size: 14px;

        border: 1px solid var(--medium-gray);

        background-color: transparent;

        color: var(--text-dark);

        cursor: pointer;

        transition: all 0.2s ease;

        display: inline-flex;

        align-items: center;

        gap: 6px;

      }



      .ad-actions .btn:hover {

          border-color: var(--text-dark);

          background-color: var(--light-gray);

      }

      

      .ad-actions .btn-premium {

          background-color: var(--warning-color);

          color: #fff;

          border-color: var(--warning-color);

      }

      

      .ad-actions .btn-premium:hover {

          background-color: #e0a800;

          border-color: #e0a800;

          color: #fff;

      }

      

      .ad-actions .btn-edit {

          color: var(--primary-color);

          /* border-color: var(--primary-color); */

      }

      .ad-actions .btn-edit:hover {

          background-color: var(--primary-color);

          border-color: var(--primary-color);

          color: #fff;

      }



      .ad-actions .btn-delete {

          color: var(--danger-color);

          /* border-color: var(--danger-color); */

      }

      .ad-actions .btn-delete:hover {

          background-color: var(--danger-color);

          border-color: var(--danger-color);

          color: #fff;

      }



      /* ===================

         رسائل وحالات التحميل

      =================== */

      .loading-message,

      .no-ads-message {

        text-align: center;

        padding: 40px 20px;

        font-size: 1.2em;

        color: var(--dark-gray);

        background-color: var(--card-bg);

        border-radius: var(--border-radius-md);

        border: 1px dashed var(--medium-gray);

      }



      .notification-message {

        padding: 15px;

        margin-bottom: 20px;

        border-radius: var(--border-radius-sm);

        text-align: center;

        font-weight: 600;

        display: none; /* يبدأ مخفيًا */

      }



      .notification-message.success {

        background-color: #d4edda;

        color: #155724;

        border: 1px solid #c3e6cb;

      }



      .notification-message.error {

        background-color: #f8d7da;

        color: #721c24;

        border: 1px solid #f5c6cb;

      }

      

      /* ===================

         التوافقية مع الشاشات الصغيرة

      =================== */

      @media (max-width: 768px) {

        .my-ad-card {

            flex-direction: column;

        }

        .my-ad-card img {

            width: 100%;

            height: 180px; /* ارتفاع ثابت للصورة على الموبايل */

        }

        .dashboard-header {

            flex-direction: column;

            align-items: stretch;

        }

        .filter-tabs {

            justify-content: center;

        }

        .action-buttons {

            flex-direction: column;

            width: 100%;

        }

        .add-new-btn, .your_message {

            justify-content: center;

        }

        .ad-status {

            float: none;

            display: inline-block;

            margin-bottom: 10px;

        }

      }

    </style>

  </head>

  <body>

    <?php include 'header_store.php'; ?>

    <div class="container">

      <div class="welcome-card">

        <div class="welcome-text">

          <h1>أهلاً بك</h1>

          <p>إليك ملخص إعلاناتك على المنصة.</p>

        </div>

        <div class="stats-grid">

          <div class="stat-item">

            <div class="stat-number" id="active-ads-count">0</div>

            <div class="stat-label">إعلانات نشطة</div>

          </div>

          <div class="stat-item">

            <div class="stat-number" id="total-views-count">0</div>

            <div class="stat-label">إجمالي المشاهدات</div>

          </div>

        </div>

      </div>

      <div class="dashboard-header">

        <div class="filter-tabs">

          <button class="tab active" data-filter="all"> الكل (<span id="all-ads-count">0</span>) </button>

          <button class="tab" data-filter="active"> نشط (<span id="active-filter-count">0</span>) </button>

          <button class="tab" data-filter="expired"> منتهي (<span id="expired-filter-count">0</span>) </button>

        </div>

        <div class="action-buttons">

            <a href="php/my_messages.php" class="your_message"> <i class="fas fa-envelope"></i> رسائلك </a>

            <a href="ads_new.php" class="add-new-btn"> <i class="fas fa-plus"></i> نشر إعلان جديد </a>

        </div>

      </div>

      <div class="ads-list" id="ads-list-container">

        <div id="notification-message"></div>

        <div id="ads-container">

          <p class="loading-message">جاري تحميل إعلاناتك...</p>

        </div>

      </div>

    </div>

    

    <!-- ======================= START FOOTER NAV ======================= -->

    <footer class="mobile-footer-nav">

      <a href="ads.php" class="nav-item"> <i class="fas fa-home"></i> <span>الرئيسية</span> <div class="nav-loader"></div> </a>

      <a href="my-ads.html" class="nav-item protected-link"> <i class="fas fa-layer-group"></i> <span>إعلاناتي</span> <div class="nav-loader"></div> </a>

      <a href="ads_new.php" class="nav-item add-ad-button protected-link"> <i class="fas fa-plus-circle"></i> <span>أضف إعلان</span> <div class="nav-loader"></div> </a>

      <a href="php/favorite.php" class="nav-item protected-link"> <i class="fas fa-heart"></i> <span>المفضلة</span> <div class="nav-loader"></div> </a>

      <a href="account.php" class="nav-item" id="account-link-mobile"> <div class="nav-loader"></div> </a>

    </footer>

    

    <script src="js/main.js"></script>

    <script>

      function updateWelcomeUserName(userName) {

        const welcomeTextH1 = document.querySelector(".welcome-text h1");

        if (welcomeTextH1) {

          welcomeTextH1.innerHTML = `أهلاً بك، ${userName}!`;

        }

      }



      async function fetchUserName() {

        try {

          const response = await fetch("php/get_logged_in_user.php");

          const result = await response.json();

          if (result.success && result.userName) {

            updateWelcomeUserName(result.userName);

          } else {

            console.warn("Failed to fetch user name:", result.error || "No user logged in or unknown error.");

            updateWelcomeUserName("زائر");

          }

        } catch (error) {

          console.error("Error fetching user name:", error);

          updateWelcomeUserName("زائر");

        }

      }



      const adsContainer = document.getElementById("ads-container");

      const notificationMessage = document.getElementById("notification-message");

      const filterTabs = document.querySelectorAll(".filter-tabs .tab");

      let allAdsData = [];



      function showNotification(message, type) {

        notificationMessage.textContent = message;

        notificationMessage.className = "notification-message";

        notificationMessage.classList.add(type);

        notificationMessage.style.display = "block";

        setTimeout(() => {

          notificationMessage.style.display = "none";

        }, 4000);

      }



      async function fetchMyAds() {

        adsContainer.innerHTML = '<p class="loading-message">جاري تحميل إعلاناتك...</p>';

        try {

          const response = await fetch("php/fetch_my_ads.php");

          const text = await response.text();

          let result;

          try {

            result = JSON.parse(text);

          } catch (e) {

            console.error("JSON parsing error:", e);

            adsContainer.innerHTML = `<p class="no-ads-message" style="color: red;">حدث خطأ: استجابة غير صالحة من الخادم.</p><pre style="white-space: pre-wrap; word-break: break-all; text-align: left; direction: ltr; font-size: 0.8em; background-color: #ffebeb; padding: 10px; border-radius: 5px;">${text}</pre>`;

            showNotification(`خطأ: استجابة غير صالحة من الخادم. يرجى مراجعة سجل الأخطاء.`, "error");

            return;

          }



          if (result.success) {

            allAdsData = result.ads;

            updateStatsAndFilters(allAdsData);

            if (allAdsData.length > 0) {

              renderAds(allAdsData);

            } else {

              adsContainer.innerHTML = '<p class="no-ads-message">لم تقم بنشر أي إعلانات بعد.</p>';

            }

          } else {

            window.location.href = "login.php";

          }

        } catch (error) {

          adsContainer.innerHTML = `<p class="no-ads-message" style="color: red;">حدث خطأ غير متوقع: ${error.message}</p>`;

          showNotification(`خطأ غير متوقع: ${error.message}`, "error");

          console.error("Error fetching ads:", error);

        }

      }



      function updateStatsAndFilters(ads) {

        const activeAds = ads.filter((ad) => ad["status1"] && ad["status1"].trim().normalize("NFC") === "نشط".normalize("NFC")).length;

        const expiredAds = ads.filter((ad) => ad["status1"] === "منتهي" || ad["status1"] === "Expired").length;

        const totalViews = ads.reduce((sum, ad) => sum + parseInt(ad["مشاهدات"] || 0), 0);



        document.getElementById("active-ads-count").textContent = activeAds;

        document.getElementById("total-views-count").textContent = totalViews.toLocaleString("EG");

        document.getElementById("all-ads-count").textContent = ads.length;

        document.getElementById("active-filter-count").textContent = activeAds;

        document.getElementById("expired-filter-count").textContent = expiredAds;

      }



      function renderAds(adsToRender) {

        adsContainer.innerHTML = "";

        if (adsToRender.length === 0) {

          adsContainer.innerHTML = '<p class="no-ads-message">لا توجد إعلانات مطابقة للمرشح.</p>';

          return;

        }



        adsToRender.forEach((ad) => {

          const rawImageUrl = ad.images_uploaded && ad.images_uploaded.length > 0 ? ad.images_uploaded[0] : "";

          const imageUrl = rawImageUrl ? (rawImageUrl.startsWith("uploads/") ? rawImageUrl : "uploads/" + rawImageUrl) : "https://via.placeholder.com/150?text=No+Image";

          

          let adCardTitle = "";

          // --- نفس منطق تحديد العنوان الذي كتبته أنت ---

          if (ad.category === "عقارات") {if (ad.subsubsub && String(ad.subsubsub).trim() !== "") {adCardTitle = ad.subsubsub;if (ad.sub && String(ad.sub).trim() !== "") {adCardTitle = `${ad.sub} - ${adCardTitle}`;}} else if (ad.subsub && String(ad.subsub).trim() !== "") {adCardTitle = ad.subsub;if (ad.sub && String(ad.sub).trim() !== "") {adCardTitle = `${ad.sub} - ${adCardTitle}`;}} else if (ad.sub && String(ad.sub).trim() !== "") {adCardTitle = ad.sub;} else {adCardTitle = ad.category || "إعلان عقاري";}} else if (ad.category === "مركبات") {const excludedForFirstPart = ["id","created_at","category","sub","subsub","subsubsub","الصورة","السعر","الموقع","رقم الهاتف","رقم الواتس","الوصف","الميزات","images","العنوان","db_id","form_id","status1","مشاهدات","submitted_at","ad_duration","images_uploaded","user_id",];for (const key in ad) {if (ad.hasOwnProperty(key) &&!excludedForFirstPart.includes(key) &&ad[key] &&String(ad[key]).trim() !== "" &&isNaN(Number(ad[key]))) {adCardTitle = ad[key];break;}}if (ad["نوع الوقود"] && String(ad["نوع الوقود"]).trim() !== "") {if (adCardTitle) {adCardTitle = `${adCardTitle} - ${ad["نوع الوقود"]}`;} else {adCardTitle = ad["نوع الوقود"];}}if (!adCardTitle) {if (ad.subsubsub && String(ad.subsubsub).trim() !== "") {adCardTitle = ad.subsubsub;} else if (ad.subsub && String(ad.subsub).trim() !== "") {adCardTitle = ad.subsub;} else if (ad.sub && String(ad.sub).trim() !== "") {adCardTitle = ad.sub;} else if (ad.category && String(ad.category).trim() !== "") {adCardTitle = ad.category;} else {adCardTitle = "إعلان مركبة بدون عنوان";}}} else {if (ad.subsubsub && String(ad.subsubsub).trim() !== "") {adCardTitle = ad.subsubsub;if (ad.sub && String(ad.sub).trim() !== "") {adCardTitle = `${ad.sub} - ${adCardTitle}`;}} else if (ad.subsub && String(ad.subsub).trim() !== "") {adCardTitle = ad.subsub;if (ad.sub && String(ad.sub).trim() !== "") {adCardTitle = `${ad.sub} - ${adCardTitle}`;}} else if (ad.sub && String(ad.sub).trim() !== "") {adCardTitle = ad.sub;} else if (ad.category && String(ad.category).trim() !== "") {adCardTitle = ad.category;} else {adCardTitle = "إعلان بدون عنوان";}}

          // --- نهاية منطق العنوان ---



          const adId = ad.db_id || ad.form_id;

          const price = ad["السعر"] ? `${parseInt(ad["السعر"]).toLocaleString('EG')} $` : "السعر عند الطلب";

          const views = ad["مشاهدات"] ? parseInt(ad["مشاهدات"]).toLocaleString("EG") : "0";

          const status = ad["status1"] || "غير محدد";

          

          let statusClass = "pending";

          let statusText = "قيد المراجعة";

          if (status === "نشط" || status === "Active") {

            statusClass = "active";

            statusText = "نشط";

          } else if (status === "منتهي" || status === "Expired") {

            statusClass = "expired";

            statusText = "منتهي الصلاحية";

          }



          let expiryInfo = "";

          if (ad["submitted_at"] && ad["مدة الإعلان"]) {

            const submittedDate = new Date(ad["submitted_at"]);

            const durationInDays = parseInt(ad["مدة الإعلان"]);

            if (!isNaN(durationInDays) && durationInDays > 0) {

              const expiryDate = new Date(submittedDate.getTime() + durationInDays * 24 * 60 * 60 * 1000);

              const now = new Date();

              const timeLeft = expiryDate.getTime() - now.getTime();

              if (timeLeft > 0) {

                const daysLeft = Math.ceil(timeLeft / (1000 * 60 * 60 * 24));

                expiryInfo = `ينتهي بعد ${daysLeft} يوم`;

              } else {

                expiryInfo = `منتهي الصلاحية`;

                if (statusClass !== "expired") {

                  statusClass = "expired";

                  statusText = "منتهي الصلاحية";

                }

              }

            }

          } else if (statusClass === "expired") {

            expiryInfo = "منتهي الصلاحية";

          }

          

          // ** القالب الجديد والمُحسّن لبطاقة الإعلان **

          const adCardHTML = `

            <div class="my-ad-card" data-ad-id="${adId}">

                <img src="${imageUrl}" alt="صورة الإعلان">

                <div class="my-ad-info">

                    <div class="ad-details">

                        <span class="ad-status ${statusClass}">${statusText}</span>

                        <h3>${adCardTitle}</h3>

                        <div class="ad-meta-data">

                            <span><i class="fas fa-tag"></i> ${price}</span>

                            <span><i class="fas fa-eye"></i> ${views} مشاهدة</span>

                            ${expiryInfo ? `<span><i class="far fa-clock"></i> ${expiryInfo}</span>` : ""}

                        </div>

                    </div>

                    <div class="ad-actions">

                        <a href="https://wa.me/963952430683" class="btn btn-premium"><i class="fas fa-star"></i> اعلان مميز</a>

                        <a class="btn btn-edit" href="form.php?edit_id=${adId}"><i class="fas fa-edit"></i> تعديل</a>

                        <button class="btn btn-delete delete-ad-btn" data-ad-id="${adId}"><i class="fas fa-trash-alt"></i> حذف</button>

                    </div>

                </div>

            </div>`;



          adsContainer.insertAdjacentHTML("beforeend", adCardHTML);

        });



        adsContainer.querySelectorAll(".delete-ad-btn").forEach((button) => {

          button.addEventListener("click", (event) => {

            event.stopPropagation();

            confirmDeleteAd(event.target.closest('.delete-ad-btn').dataset.adId);

          });

        });



        adsContainer.querySelectorAll(".my-ad-card").forEach((adCard) => {

          adCard.addEventListener("click", (event) => {

            // تجنب فتح التفاصيل عند الضغط على زر

            if (event.target.closest('.btn')) {

                return;

            }

            const adId = adCard.dataset.adId;

            const selectedAd = allAdsData.find((ad) => (ad.db_id || ad.form_id) == adId);

            if (selectedAd) {

              // هنا يمكنك إضافة دالة عرض تفاصيل الإعلان إذا أردت

              // showAdDetails(selectedAd);

              console.log("Card clicked for ad ID:", adId);

            }

          });

        });

      }



      async function confirmDeleteAd(adId) {

        if (confirm("هل أنت متأكد أنك تريد حذف هذا الإعلان؟")) {

          deleteAd(adId);

        }

      }



      async function deleteAd(adId) {

        try {

          const response = await fetch("php/delete_ad.php", {

            method: "POST",

            headers: {

              "Content-Type": "application/x-www-form-urlencoded",

            },

            body: `ad_id=${adId}`,

          });



          const text = await response.text();

          let result;

          try {

            result = JSON.parse(text);

          } catch (e) {

            console.error("فشل في تحويل رد الحذف إلى JSON", e);

            showNotification("البيانات المستلمة بعد الحذف ليست بصيغة JSON", "error");

            return;

          }



          if (result.success) {

            showNotification("تم حذف الإعلان بنجاح.", "success");

            fetchMyAds();

          } else {

            showNotification(`خطأ في حذف الإعلان: ${result.error}`, "error");

          }

        } catch (error) {

          showNotification(`حدث خطأ غير متوقع أثناء الحذف: ${error.message}`, "error");

          console.error("Error deleting ad:", error);

        }

      }



      filterTabs.forEach((tab) => {

        tab.addEventListener("click", () => {

          filterTabs.forEach((t) => t.classList.remove("active"));

          tab.classList.add("active");

          const filter = tab.dataset.filter;

          let filteredAds = [];

          if (filter === "all") {

            filteredAds = allAdsData;

          } else if (filter === "active") {

            filteredAds = allAdsData.filter((ad) => ad["status1"] && ad["status1"].trim().normalize("NFC") === "نشط".normalize("NFC"));

          } else if (filter === "expired") {

            filteredAds = allAdsData.filter((ad) => ad["status1"] === "منتهي" || ad["status1"] === "Expired");

          }

          renderAds(filteredAds);

        });

      });

      

      document.addEventListener("DOMContentLoaded", () => {

        fetchMyAds();

        fetchUserName();

      });

    </script>

  </body>

</html>