document.addEventListener('DOMContentLoaded', () => {
    const mainImage = document.getElementById('details-main-img');
    const thumbnailsContainer = document.querySelector('.details-gallery-vertical');
    const colorOptionsContainer = document.querySelector('.details-colors');
    const sizeOptionsContainer = document.querySelector('.details-sizes');
    const addToCartBtn = document.getElementById('add-to-cart-page-btn');
    const productContainer = document.querySelector('.product-page-container');

    thumbnailsContainer?.addEventListener('mouseover', e => {
        if (e.target.classList.contains('thumb')) {
            mainImage.src = e.target.src;
            thumbnailsContainer.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
        }
    });

    colorOptionsContainer?.addEventListener('click', e => {
        const colorOption = e.target.closest('.color-option');
        if (colorOption) {
            mainImage.src = colorOption.dataset.imageUrl;
            document.getElementById('selected-color-name').textContent = colorOption.dataset.colorName;
            colorOptionsContainer.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
            colorOption.classList.add('selected');
        }
    });

    sizeOptionsContainer?.addEventListener('click', e => {
        if (e.target.classList.contains('size-btn')) {
            sizeOptionsContainer.querySelectorAll('.size-btn').forEach(b => b.classList.remove('selected'));
            e.target.classList.add('selected');
        }
    });

    addToCartBtn?.addEventListener('click', () => {
        const selectedSizeEl = sizeOptionsContainer.querySelector('.size-btn.selected');
        const selectedColorEl = colorOptionsContainer.querySelector('.color-option.selected');

        if (!selectedSizeEl) { alert('الرجاء اختيار المقاس.'); return; }
        if (!selectedColorEl) { alert('الرجاء اختيار اللون.'); return; }
        
        const product = {
            id: productContainer.dataset.productId,
            name: productContainer.dataset.productName,
            price: parseFloat(productContainer.dataset.productPrice),
            image: productContainer.dataset.productImage,
            size: selectedSizeEl.dataset.sizeName,
            color: selectedColorEl.dataset.colorName
        };

        // استدعاء دالة إضافة للسلة من ملف cart.js
        addToCart(product);

        addToCartBtn.textContent = 'تمت الإضافة بنجاح!';
        setTimeout(() => { addToCartBtn.textContent = 'أضف إلى السلة'; }, 2000);
    });
});