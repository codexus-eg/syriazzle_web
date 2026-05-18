document.addEventListener('DOMContentLoaded', function() {
    // --- Step 1: Wizard Navigation Logic ---
    const steps = document.querySelectorAll('.step-item');
    const stepContents = document.querySelectorAll('.form-step');
    const nextBtn = document.getElementById('next-btn');
    const prevBtn = document.getElementById('prev-btn');
    const submitBtn = document.getElementById('submit-btn');
    let currentStep = 1;

    function updateWizard() {
        steps.forEach((step, index) => {
            if (index < currentStep) {
                step.classList.add('completed');
                step.classList.remove('active');
            } else if (index === currentStep - 1) {
                step.classList.add('active');
                step.classList.remove('completed');
            } else {
                step.classList.remove('active', 'completed');
            }
        });

        stepContents.forEach(content => {
            content.classList.toggle('active', parseInt(content.dataset.stepContent) === currentStep);
        });

        prevBtn.style.display = (currentStep > 1) ? 'inline-block' : 'none';
        nextBtn.style.display = (currentStep < steps.length) ? 'inline-block' : 'none';
        submitBtn.style.display = (currentStep === steps.length) ? 'inline-block' : 'none';
    }

    function validateStep(stepNumber) {
        const stepContent = document.querySelector(`.form-step[data-step-content="${stepNumber}"]`);
        const inputs = stepContent.querySelectorAll('[required]');
        for (let input of inputs) {
            if (!input.value) {
                alert('الرجاء ملء جميع الحقول المطلوبة (*)');
                input.focus();
                return false;
            }
        }
        return true;
    }

    nextBtn.addEventListener('click', () => {
        if (validateStep(currentStep) && currentStep < steps.length) {
            currentStep++;
            updateWizard();
        }
    });

    prevBtn.addEventListener('click', () => {
        if (currentStep > 1) {
            currentStep--;
            updateWizard();
        }
    });

    // --- Step 2: Category Selector Logic ---
    const categoryGrid = document.querySelector('.category-selector-grid');
    const categoryInput = document.getElementById('booking_category_input');
    const categories = [
        { key: 'hotel', name: 'فندق/شاليه', icon: 'fa-hotel' },
        { key: 'restaurant', name: 'مطعم (طاولات)', icon: 'fa-utensils' },
        { key: 'clinic', name: 'عيادة/طبيب', icon: 'fa-user-doctor' },
        { key: 'consulting', name: 'استشارات/خدمات', icon: 'fa-handshake' },
        { key: 'tourism', name: 'سياحة وسفر', icon: 'fa-plane' },
        { key: 'event', name: 'مناسبات/صالات', icon: 'fa-calendar-check' },
    ];

    categories.forEach(cat => {
        const card = document.createElement('div');
        card.className = 'category-card';
        card.dataset.categoryKey = cat.key;
        card.innerHTML = `<i class="fas ${cat.icon}"></i><span>${cat.name}</span>`;
        categoryGrid.appendChild(card);
    });

    categoryGrid.addEventListener('click', e => {
        const card = e.target.closest('.category-card');
        if (card) {
            categoryGrid.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            categoryInput.value = card.dataset.categoryKey;
        }
    });

    // --- Step 3: Image Uploader with Preview ---
    function setupImageUploader(containerId) {
        const container = document.getElementById(containerId);
        const fileInput = container.querySelector('input[type="file"]');
        const preview = container.querySelector('.image-preview');
        const removeBtn = container.querySelector('.remove-image-btn');
        const uploadUI = container.querySelector('.upload-ui');

        container.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    removeBtn.style.display = 'block';
                    uploadUI.style.display = 'none';
                }
                reader.readAsDataURL(file);
            }
        });
        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation(); // منع فتح نافذة اختيار الملف
            fileInput.value = ''; // حذف الملف المختار
            preview.src = '#';
            preview.style.display = 'none';
            removeBtn.style.display = 'none';
            uploadUI.style.display = 'flex';
        });
    }

    setupImageUploader('logo-uploader');
    setupImageUploader('cover-uploader');
});