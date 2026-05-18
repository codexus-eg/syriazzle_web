document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.tab-btn');
    const container = document.getElementById('orders-list-container');
    const statusTranslations = {
        'pending_approval': 'قيد المراجعة',
        'preparing': 'قيد التحضير',
        'out_for_delivery': 'مع السائق',
        'delivered': 'تم التوصيل',
        'canceled': 'ملغي'
    };

    async function fetchOrders(status = 'active') {
        container.innerHTML = '<div class="loader">جاري تحميل الطلبات...</div>';
        try {
            const response = await fetch(`php/ajax_get_mall_orders.php?status=${status}`);
            const data = await response.json();

            if (data.success) {
                renderOrders(data.orders);
            } else {
                container.innerHTML = `<div class="empty-state">${data.message}</div>`;
            }
        } catch (error) {
            container.innerHTML = '<div class="empty-state">فشل تحميل الطلبات.</div>';
        }
    }

    function renderOrders(orders) {
        if (orders.length === 0) {
            container.innerHTML = '<div class="empty-state">لا توجد طلبات لعرضها في هذا القسم.</div>';
            return;
        }

        // ================== الإصلاح الحاسم هنا ==================
        // جعل كل بطاقة رابطًا لصفحة التتبع
        container.innerHTML = orders.map(order => `
            <a href="track_mall_order.php?order_id=${order.id}" class="order-card-link">
                <div class="order-card">
                    <div class="order-header">
                        <h3>طلب رقم #${order.id}</h3>
                        <span class="status-badge status-${order.status}">${statusTranslations[order.status] || order.status}</span>
                    </div>
                    <div class="order-body">
                        <p><strong>التاريخ:</strong> ${new Date(order.created_at).toLocaleString('ar-SY')}</p>
                        <p><strong>المجموع النهائي:</strong> ${parseFloat(order.total_price).toLocaleString('ar-SY')} ل.س</p>
                    </div>
                    <div class="order-footer">
                        <span>عرض التفاصيل والتتبع المباشر</span>
                        <i class="fas fa-arrow-left"></i>
                    </div>
                </div>
            </a>
        `).join('');
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            fetchOrders(tab.dataset.status);
        });
    });

    fetchOrders('active');
});