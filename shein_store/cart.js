// ===============================================
// ==     ملف إدارة السلة المركزي (cart.js)     ==
// ===============================================

// الوصول للعناصر قد يتم في أي صفحة، لذا نتحقق من وجودها
const cartModal = document.getElementById('cart-modal');
const cartItemsContainer = document.getElementById('cart-items');
const cartItemCountEl = document.getElementById('cart-item-count');
const cartTotalEl = document.getElementById('cart-total');
const openCartBtn = document.querySelector('.toggle-cart-btn');
const closeCartBtn = document.getElementById('close-modal-btn');

// تحميل السلة من التخزين المحلي
let cart = JSON.parse(localStorage.getItem('shn_cart')) || [];

function updateCartUI() {
    renderCartItems();
    updateCartSummary();
    localStorage.setItem('shn_cart', JSON.stringify(cart));
}

function renderCartItems() {
    if (!cartItemsContainer) return;
    cartItemsContainer.innerHTML = '';
    if (cart.length === 0) {
        cartItemsContainer.innerHTML = '<p>سلة المشتريات فارغة.</p>';
        return;
    }
    cart.forEach(item => {
        const itemIdentifier = `${item.id}-${item.size}-${item.color}`;
        const cartItemDiv = document.createElement('div');
        cartItemDiv.className = 'cart-item';
        cartItemDiv.innerHTML = `
            <img src="${item.image}" alt="${item.name}">
            <div class="cart-item-info">
                <p><strong>${item.name}</strong></p>
                <p>${item.price.toFixed(2)} $</p>
                <small>المقاس: ${item.size || '-'} | اللون: ${item.color || '-'}</small>
            </div>
            <div class="quantity-controls">
                <button class="quantity-change" data-identifier="${itemIdentifier}" data-change="-1">-</button>
                <span>${item.quantity}</span>
                <button class="quantity-change" data-identifier="${itemIdentifier}" data-change="1">+</button>
            </div>
            <button class="remove-item" data-identifier="${itemIdentifier}">×</button>
        `;
        cartItemsContainer.appendChild(cartItemDiv);
    });
}

function updateCartSummary() {
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    const totalPrice = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    if (cartItemCountEl) cartItemCountEl.textContent = totalItems;
    if (cartTotalEl) cartTotalEl.textContent = `${totalPrice.toFixed(2)} $`;
}

function addToCart(product) {
    const itemIdentifier = `${product.id}-${product.size}-${product.color}`;
    const existingItem = cart.find(item => `${item.id}-${item.size}-${item.color}` === itemIdentifier);
    
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            image: product.image,
            quantity: 1,
            size: product.size,
            color: product.color
        });
    }
    updateCartUI();
}

// --- إعداد المستمعين للأحداث الخاصة بالسلة ---
openCartBtn?.addEventListener('click', () => cartModal?.classList.remove('hidden'));
closeCartBtn?.addEventListener('click', () => cartModal?.classList.add('hidden'));
cartModal?.addEventListener('click', e => { if (e.target === cartModal) cartModal.classList.add('hidden'); });

cartItemsContainer?.addEventListener('click', e => {
    const target = e.target;
    const identifier = target.dataset.identifier;
    if (!identifier) return;

    const itemIndex = cart.findIndex(item => `${item.id}-${item.size}-${item.color}` === identifier);
    if (itemIndex === -1) return;

    if (target.classList.contains('quantity-change')) {
        const change = parseInt(target.dataset.change);
        cart[itemIndex].quantity += change;
        if (cart[itemIndex].quantity <= 0) {
            cart.splice(itemIndex, 1);
        }
    } else if (target.classList.contains('remove-item')) {
        cart.splice(itemIndex, 1);
    }
    updateCartUI();
});

// استدعاء فوري لتحديث واجهة المستخدم عند تحميل أي صفحة
document.addEventListener('DOMContentLoaded', updateCartUI);