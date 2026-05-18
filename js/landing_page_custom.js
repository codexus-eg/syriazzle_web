/**
 * ========================================================================
 * Syriazzle - Landing Page Logic (Custom Version)
 * v2.0 - Smart Intro, Slider & Touch Interactions
 * ========================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    
    
    // ============================================================
    // 2. منطق الهيرو سلايدر (Hero Slider)
    // ============================================================
    const sliderSection = document.getElementById('hero-slider');
    
    if (sliderSection) {
        const slides = sliderSection.querySelectorAll('.hero-slide');
        let currentSlideIndex = 0;
        const slideIntervalTime = 4000; // تغيير الصورة كل 4 ثواني

        if (slides.length > 1) {
            // دالة تغيير الشريحة
            const changeSlide = () => {
                // إزالة كلاس active من الشريحة الحالية
                slides[currentSlideIndex].classList.remove('active');
                
                // الانتقال للشريحة التالية (دائرياً)
                currentSlideIndex = (currentSlideIndex + 1) % slides.length;
                
                // إضافة كلاس active للشريحة الجديدة
                slides[currentSlideIndex].classList.add('active');
            };

            // تشغيل المؤقت
            setInterval(changeSlide, slideIntervalTime);
        }
    }

    // ============================================================
    // 3. تحسين التمرير الأفقي بالماوس (Drag to Scroll)
    // هذه الميزة تجعل القوائم قابلة للسحب بالماوس على الكمبيوتر مثل الموبايل
    // ============================================================
    const scrollContainers = document.querySelectorAll('.scroll-track-container');

    scrollContainers.forEach(slider => {
        let isDown = false;
        let startX;
        let scrollLeft;

        slider.addEventListener('mousedown', (e) => {
            isDown = true;
            slider.classList.add('active'); // يمكن استخدامه لتغيير شكل المؤشر في CSS
            startX = e.pageX - slider.offsetLeft;
            scrollLeft = slider.scrollLeft;
            // منع تحديد النصوص أثناء السحب
            slider.style.cursor = 'grabbing'; 
            slider.style.userSelect = 'none';
        });

        slider.addEventListener('mouseleave', () => {
            isDown = false;
            slider.style.cursor = 'default';
            slider.style.userSelect = 'auto';
        });

        slider.addEventListener('mouseup', () => {
            isDown = false;
            slider.style.cursor = 'default';
            slider.style.userSelect = 'auto';
        });

        slider.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - slider.offsetLeft;
            const walk = (x - startX) * 2; // سرعة السحب (x2)
            slider.scrollLeft = scrollLeft - walk;
        });
    });

});