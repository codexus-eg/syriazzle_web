<?php require_once 'php/db_connect.php';?>



<!DOCTYPE html>

<html lang="ar" dir="rtl">

  <head>

    <meta charset="UTF-8" />



    <meta name="viewport" content="width=device-width, initial-scale=1.0" />



    <title>Syriazzle</title>



    <link rel="icon" href="image/favicon.png" type="image/png" />

    <link rel="stylesheet" href="css/normalize.css" />

    <link rel="stylesheet" href="css/style.css" />

    <link rel="stylesheet" href="css/all.min.css" />



    <!-- Google Fonts -->

    <link rel="preconnect" href="https://fonts.googleapis.com" />

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <link

      href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap"

      rel="stylesheet"

    />

    

    <link rel="stylesheet" href="css/dubizzle-inspired.css" />

    <link rel="stylesheet" href="css/main_header.css" />

    <style>

      .directory-promo-section {

        padding: 60px 0;

        background-color: #f8f9fa;

        border-top: 1px solid #e9ecef;

        border-bottom: 1px solid #e9ecef;

      }



      .promo-box {

        display: flex;

        align-items: center;

        background-color: #ffffff;

        border-radius: 16px;

        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);

        overflow: hidden;

      }



      .promo-text {

        flex: 1;

        padding: 40px 50px;

      }



      .promo-badge {

        display: inline-block;

        background-color: #1976d2;

        color: #fff;

        padding: 6px 15px;

        border-radius: 20px;

        font-size: 14px;

        font-weight: 700;

        margin-bottom: 20px;

      }



      .promo-text h2 {

        font-size: 32px;

        font-weight: 800;

        color: #212121;

        margin: 0 0 15px 0;

        line-height: 1.3;

      }



      .promo-text p {

        font-size: 16px;

        color: #6c757d;

        line-height: 1.7;

        margin-bottom: 30px;

      }



      .promo-actions {

        display: flex;

        align-items: center;

        gap: 15px;

      }



      .btn-promo-primary,

      .btn-promo-secondary {

        text-decoration: none;

        padding: 12px 25px;

        border-radius: 8px;

        font-weight: 700;

        font-size: 16px;

        transition: all 0.3s ease;

        display: inline-flex;

        align-items: center;

        gap: 8px;

      }



      .btn-promo-primary {

        background-color: #ff7700;

        color: #fff;

      }

      .btn-promo-primary:hover {

        background-color: #b7a21c;

        transform: translateY(-2px);

        box-shadow: 0 4px 15px rgba(211, 47, 47, 0.3);

      }



      .btn-promo-secondary {

        background-color: transparent;

        color: #333;

        font-weight: 600;

      }

      .btn-promo-secondary:hover {

        color: #000;

        text-decoration: underline;

      }



      .promo-image {

        flex: 1;

        display: flex;

        align-items: center;

        justify-content: center;

        padding-right: 30px;

      }



      .promo-image img {

        max-width: 100%;

        height: auto;

      }



      @media (max-width: 992px) {

        .promo-box {

          flex-direction: column;

          text-align: center;

        }

        .promo-image {

          order: -1;

          padding: 30px 30px 0 30px;

        }

        .promo-text {

          padding: 30px;

        }

        .promo-actions {

          flex-direction: column;

          align-items: stretch;

        }

      }

      @media (max-width: 768px) {

        .promo-text h2 {

          font-size: 26px;

        }

      }

    </style>

  </head>



  <body>

    <?php include 'header_store.php'; ?>

    <div class="menu-icon-wrapper" id="menu-icon">

      <i class="fas fa-bars"></i>

    </div>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <nav class="sidebar" id="sidebar">

      <div class="sidebar-header">

        <h3>القائمة</h3>

        <button class="close-sidebar-btn" id="close-sidebar-btn">

          &times;

        </button>

      </div>



      <ul class="sidebar-links">

        <li>

          <a href="https://www.facebook.com/share/19wiAcnkry/" target="_blank">

            <i class="fab fa-facebook"></i>

            <span>صفحتنا على فيسبوك</span>

          </a>

        </li>

        <li>

          <a href="https://www.instagram.com/your-profile" target="_blank">

            <i class="fab fa-instagram"></i>

            <span>حسابنا على انستغرام</span>

          </a>

        </li>

        <li>

          <a href="https://t.me/your-channel" target="_blank">

            <i class="fab fa-telegram"></i>

            <span>قناتنا على تليجرام</span>

          </a>

        </li>

        <hr />

        <li>

          <a href="notes.html">

            <i class="fas fa-file-alt"></i>

            <span>ملاحظات هامة</span>

          </a>

        </li>

        <li class="rating-section">

          <div class="rating-trigger" id="rating-trigger">

            <i class="fas fa-star"></i>

            <span>تقييم التطبيق</span>

            <i class="fas fa-chevron-down arrow-indicator"></i>

          </div>

          <div class="rating-stars-container" id="rating-stars-container">

            <div class="stars">

              <i class="far fa-star" data-value="1"></i>

              <i class="far fa-star" data-value="2"></i>

              <i class="far fa-star" data-value="3"></i>

              <i class="far fa-star" data-value="4"></i>

              <i class="far fa-star" data-value="5"></i>

            </div>

          </div>

        </li>

      </ul>

    </nav>

    <!-- ======================= نهاية القائمة الجانبية  ======================= -->



    <a href="https://wa.me/963992679030">

      <div class="help-box">

        <i class="fas fa-circle-question"></i>



        <span>مساعدة</span>

      </div>

    </a>



    <main class="main-content">

      <section class="hero-section">

        <div class="container">

          <div class="slider-container">

            <div class="slider">

              <a href="php/fetch_user_ads.php?user_id=58">

                <img

                  src="image/img1.png"

                  class="slide active"

                  alt="عرض إعلانات المستخدم"

                />

              </a>



              <img src="image/image2.jpg" class="slide" alt="" />



              <img src="image/image3.jpeg" class="slide" alt="" />



              <img src="image/image5.jpeg" class="slide" alt="" />



              <img src="image/image6.png" class="slide" alt="" />

            </div>

          </div>

        </div>



        <div class="searchchmain">

          <div class="container">

            <div class="search-container">

              <form

                class="search-bar-wrapper"

                id="main-search-form"

                action="php/search_results.php"

                method="GET"

              >

                <input

                  type="text"

                  id="main-search-input"

                  name="q"

                  placeholder="ابحث عن سيارات، شقق، هواتف..."

                  autocomplete="off"

                />

                <!-- <button type="submit" class="search-button">

                  <i class="fas fa-search"></i>

                </button> -->
<button type="submit" class="search-button" style="background-color: #FFA500 !important; color: white; border: none; padding: 10px; cursor: pointer;">
  <i class="fas fa-search"></i>
</button>


                <!-- حاوية لعرض اقتراحات البحث الأخيرة -->



                <div class="search-suggestions" id="search-suggestions"></div>

              </form>

            </div>

          </div>

        </div>

      </section>



      <section class="landing-content-section">

        <div class="landing">

          <div class="container">

            <div class="landing-content">

              <!-- <div class="box">

                <div class="icon-text">

                  <a href="php/fetch_ads.php?category=مركبات" class="cat-img">

                    <img src="image/car.svg" title="قسم السيارات" alt="" />

                  </a>



                  <a href="php/fetch_ads.php?category=مركبات" class="cat"

                    >مركبات</a

                  >

                </div>

              </div> -->
<div class="box">
  <div class="icon-text">
    <a href="php/fetch_ads.php?category=مركبات" class="cat-img">
      <svg
        width="60"
        height="60"
        viewBox="0 0 24 24"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        style="display: block; margin: 0 auto;"
      >
        <path
          d="M19 17H5V11L7.17071 5.57322C7.35904 5.10238 7.8149 4.8 8.32298 4.8H15.677C16.1851 4.8 16.641 5.10238 16.8293 5.57322L19 11V17Z"
          stroke="#FF8C00"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <path
          d="M7 17V19C7 20.1046 7.89543 21 9 21V21C10.1046 21 11 20.1046 11 19V17"
          stroke="#FF8C00"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <path
          d="M13 17V19C13 20.1046 13.8954 21 15 21V21C16.1046 21 17 20.1046 17 19V17"
          stroke="#FF8C00"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <circle cx="7" cy="11" r="1" fill="#FF8C00" />
        <circle cx="17" cy="11" r="1" fill="#FF8C00" />
        <path
          d="M5 11H19"
          stroke="#FF8C00"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </a>

    <a
      href="php/fetch_ads.php?category=مركبات"
      class="cat"
      style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold; text-align: center;"
    >
      مركبات
    </a>
  </div>
</div>


              <!-- <div class="box">

                <div class="icon-text">

                  <a href="php/fetch_ads.php?category=عقارات" class="cat-img">

                    <img src="image/real.svg" title="قسم العقارات" alt="" />

                  </a>



                  <a href="php/fetch_ads.php?category=عقارات" class="cat"

                    >عقارات</a

                  >

                </div>

              </div> -->

              <div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=عقارات" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 9.5L12 3L21 9.5V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V9.5Z" stroke="#FF8C00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9 21V12H15V21" stroke="#FF8C00" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>

        <a href="php/fetch_ads.php?category=عقارات" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">
            عقارات
        </a>
    </div>
</div>
              <!-- <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=أجهزة معادن"

                    class="cat-img"

                  >

                    <img src="image/detector1.svg" title="أجهزة معادن" alt="" />

                  </a>

                  <a href="php/fetch_ads.php?category=أجهزة_كشف_المعادن" class="cat"

                    >كشف المعادن</a

                  >

                </div>

              </div> -->
<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=أجهزة_كشف_المعادن" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="11" cy="11" r="8" stroke="#FF8C00" stroke-width="2"/>
                <path d="M21 21L16.65 16.65" stroke="#FF8C00" stroke-width="2" stroke-linecap="round"/>
                <path d="M11 8V11L13 13" stroke="#FF8C00" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </a>

        <a href="php/fetch_ads.php?category=أجهزة_كشف_المعادن" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">
            كشف المعادن
        </a>
    </div>
</div>


              <!-- <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=هواتف_وإكسسوارات"

                    class="cat-img"

                  >

                    <img

                      src="image/phone.svg"

                      title="قسم الهواتف والإكسسوارات"

                      alt=""

                    />

                  </a>



                  <a

                    href="php/fetch_ads.php?category=هواتف_وإكسسوارات"

                    class="cat"

                    >هواتف وإكسسوارات</a

                  >

                </div>

              </div>



              <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=أثاث والديكور"

                    class="cat-img"

                  >

                    <img src="image/Furniture.svg" title="" alt="" />

                  </a>



                  <a href="php/fetch_ads.php?category=أثاث والديكور" class="cat"

                    >مفروشات وديكور</a

                  >

                </div>

              </div>



              <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=الموضة_والجمال"

                    class="cat-img"

                  >

                    <img src="image/fashion.svg" title="" alt="" />

                  </a>



                  <a

                    href="php/fetch_ads.php?category=الموضة_والجمال"

                    class="cat"

                    >الموضة والجمال</a

                  >

                </div>

              </div>



              <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=مستلزمات_الأطفال"

                    class="cat-img"

                  >

                    <img src="image/children.svg" title="" alt="" />

                  </a>



                  <a

                    href="php/fetch_ads.php?category=مستلزمات_الأطفال"

                    class="cat"

                    >مستلزمات الأطفال</a

                  >

                </div>

              </div>



              <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=أجهزة_إلكترونية"

                    class="cat-img"

                  >

                    <img src="image/device.svg" title="" alt="" />

                  </a>



                  <a

                    href="php/fetch_ads.php?category=أجهزة_إلكترونية"

                    class="cat"

                    >إلكترونيات</a

                  >

                </div>

              </div>



              <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=تجارة_وصناعة"

                    class="cat-img"

                  >

                    <img src="image/industry.svg" title="" alt="" />

                  </a>



                  <a href="php/fetch_ads.php?category=تجارة_وصناعة" class="cat"

                    >تجارة وصناعة</a

                  >

                </div>

              </div>



              <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=مستلزمات_الرياضة"

                    class="cat-img"

                  >

                    <img src="image/sports.svg" title="" alt="" />

                  </a>



                  <a

                    href="php/fetch_ads.php?category=مستلزمات_الرياضة"

                    class="cat"

                    >رياضة</a

                  >

                </div>

              </div>
 -->
<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=هواتف_وإكسسوارات" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="5" y="2" width="14" height="20" rx="2" stroke="#FF8C00" stroke-width="2"/>
                <path d="M12 18H12.01" stroke="#FF8C00" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=هواتف_وإكسسوارات" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">هواتف وإكسسوارات</a>
    </div>
</div>

<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=أثاث والديكور" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 18V9C4 8.44772 4.44772 8 5 8H19C19.5523 8 20 8.44772 20 9V18" stroke="#FF8C00" stroke-width="2"/>
                <path d="M2 20H22" stroke="#FF8C00" stroke-width="2" stroke-linecap="round"/>
                <path d="M7 8V5C7 4.44772 7.44772 4 8 4H16C16.5523 4 17 4.44772 17 5V8" stroke="#FF8C00" stroke-width="2"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=أثاث والديكور" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">مفروشات وديكور</a>
    </div>
</div>

<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=الموضة_والجمال" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 20V10L12 4L18 10V20H6Z" stroke="#FF8C00" stroke-width="2" stroke-linejoin="round"/>
                <path d="M9 10H15" stroke="#FF8C00" stroke-width="2"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=الموضة_والجمال" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">الموضة والجمال</a>
    </div>
</div>

<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=مستلزمات_الأطفال" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="8" r="5" stroke="#FF8C00" stroke-width="2"/>
                <path d="M7 15C7 15 2 17 2 22H22C22 17 17 15 17 15" stroke="#FF8C00" stroke-width="2"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=مستلزمات_الأطفال" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">مستلزمات الأطفال</a>
    </div>
</div>

<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=أجهزة_إلكترونية" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="2" y="4" width="20" height="13" rx="2" stroke="#FF8C00" stroke-width="2"/>
                <path d="M8 20H16" stroke="#FF8C00" stroke-width="2" stroke-linecap="round"/>
                <path d="M12 17V20" stroke="#FF8C00" stroke-width="2"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=أجهزة_إلكترونية" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">إلكترونيات</a>
    </div>
</div>

<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=تجارة_وصناعة" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 20V10L6 4H18L22 10V20H2Z" stroke="#FF8C00" stroke-width="2" stroke-linejoin="round"/>
                <path d="M7 20V15H17V20" stroke="#FF8C00" stroke-width="2"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=تجارة_وصناعة" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">تجارة وصناعة</a>
    </div>
</div>

<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=مستلزمات_الرياضة" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="9" stroke="#FF8C00" stroke-width="2"/>
                <path d="M6.7 6.7L17.3 17.3" stroke="#FF8C00" stroke-width="2"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=مستلزمات_الرياضة" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">رياضة</a>
    </div>
</div>

              <!-- <div class="box">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=حيوانات_أليفة"

                    class="cat-img"

                  >

                    <img src="image/animal.svg" title="" alt="" />

                  </a>



                  <a href="php/fetch_ads.php?category=حيوانات_أليفة" class="cat"

                    >حيوانات أليفة</a

                  >

                </div>

              </div>



              <div class="box">

                <div class="icon-text">

                  <a href="php/fetch_ads.php?category=هوايات" class="cat-img">

                    <img src="image/hobbies.svg" title="" alt="" />

                  </a>



                  <a href="php/fetch_ads.php?category=هوايات" class="cat"

                    >ترفيه وهوايات</a

                  >

                </div>

              </div>



              <div class="box">

                <div class="icon-text">

                  <a href="php/fetch_ads.php?category=خدمات" class="cat-img">

                    <img src="image/serveces.svg" title="" alt="" />

                  </a>



                  <a href="php/fetch_ads.php?category=خدمات" class="cat"

                    >خدمات</a

                  >

                </div>

              </div>

              <div class="box jobs">

                <div class="icon-text">

                  <a href="php/fetch_ads.php?category=التوظيف" class="cat-img">

                    <img src="image/Jobs.svg" title="" alt="" />

                  </a>

                  <a href="php/fetch_ads.php?category=التوظيف" class="cat"

                    >الوظائف</a

                  >

                </div>

              </div>

              <div class="box tourism">

                <div class="icon-text">

                  <a

                    href="php/fetch_ads.php?category=سياحة وسفر"

                    class="cat-img"

                  >

                    <img src="image/syklvg3jklvg3jklv.svg" title="" alt="" />

                  </a>



                  <a href="php/fetch_ads.php?category=سياحة وسفر" class="cat"

                    >سياحة وسفر</a

                  >

                </div>

              </div> -->
<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=حيوانات_أليفة" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 10C13.6569 10 15 8.65685 15 7C15 5.34315 13.6569 4 12 4C10.3431 4 9 5.34315 9 7C9 8.65685 10.3431 10 12 10Z" stroke="#FF8C00" stroke-width="2"/>
                <path d="M4 14C4 14 2 15 2 18V20H22V18C22 15 20 14 20 14" stroke="#FF8C00" stroke-width="2"/>
                <circle cx="17" cy="7" r="2" stroke="#FF8C00" stroke-width="2"/>
                <circle cx="7" cy="7" r="2" stroke="#FF8C00" stroke-width="2"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=حيوانات_أليفة" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">حيوانات أليفة</a>
    </div>
</div>

<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=هوايات" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 21C16.9706 21 21 16.9706 21 12C21 7.02944 16.9706 3 12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21Z" stroke="#FF8C00" stroke-width="2"/>
                <path d="M12 8V16M8 12H16" stroke="#FF8C00" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=هوايات" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">ترفيه وهوايات</a>
    </div>
</div>

<div class="box">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=خدمات" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M14.7 6.3C14.7 6.3 15 4 12 4C9 4 9.3 6.3 9.3 6.3C9.3 6.3 7 6.6 7 9.6C7 12.6 9.3 12.9 9.3 12.9C9.3 12.9 9 15.2 12 15.2C15 15.2 14.7 12.9 14.7 12.9C14.7 12.9 17 12.6 17 9.6C17 6.6 14.7 6.3 14.7 6.3Z" stroke="#FF8C00" stroke-width="2"/>
                <path d="M12 15V20M8 20H16" stroke="#FF8C00" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=خدمات" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">خدمات</a>
    </div>
</div>

<div class="box jobs">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=التوظيف" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="7" width="18" height="12" rx="2" stroke="#FF8C00" stroke-width="2"/>
                <path d="M9 7V5C9 4.44772 9.44772 4 10 4H14C14.5523 4 15 4.44772 15 5V7" stroke="#FF8C00" stroke-width="2"/>
                <path d="M12 12V14" stroke="#FF8C00" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=التوظيف" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">الوظائف</a>
    </div>
</div>

<div class="box tourism">
    <div class="icon-text">
        <a href="php/fetch_ads.php?category=سياحة وسفر" class="cat-img">
            <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#FF8C00" stroke-width="2"/>
                <path d="M12 2V22M2 12H22" stroke="#FF8C00" stroke-width="2" stroke-opacity="0.3"/>
                <path d="M20 12L4 12L12 3L20 12Z" stroke="#FF8C00" stroke-width="2" stroke-linejoin="round"/>
            </svg>
        </a>
        <a href="php/fetch_ads.php?category=سياحة وسفر" class="cat" style="color: #FF8C00; text-decoration: none; display: block; margin-top: 5px; font-weight: bold;">سياحة وسفر</a>
    </div>
</div>
            </div>

          </div>

        </div>

      </section>

      

      <section class="recent-ads-section">

        <div class="container">

          <h2 class="section-title">أحدث الإعلانات</h2>



          <div class="ads-grid" id="dynamic-ad-categories-sliders"></div>

        </div>

      </section>

    </main>



    <!-- ======================= START FOOTER NAV (للجوال - تصميم Dubizzle) ======================= -->



    <footer class="mobile-footer-nav">

      <a href="ads.php" class="nav-item">

        <i class="fas fa-home"></i>

        <span>الرئيسية</span>

        <div class="nav-loader"></div>

      </a>

      <a href="my-ads.php" class="nav-item protected-link">

        <i class="fas fa-layer-group"></i>

        <span>إعلاناتي</span>

        <div class="nav-loader"></div>

      </a>

      <a href="ads_new.php" class="nav-item add-ad-button protected-link">

        <i class="fas fa-plus-circle"></i>

        <span>أضف إعلان</span>

        <div class="nav-loader"></div>

      </a>

      <a href="php/favorite.php" class="nav-item protected-link">

        <i class="fas fa-heart"></i>

        <span>المفضلة</span>

        <div class="nav-loader"></div>

      </a>

      <a href="account.php" class="nav-item" id="account-link-mobile">

        <!-- JS -->

        <div class="nav-loader"></div>

      </a>

    </footer>



    <script src="js/main.js"></script>

  </body>

</html>

