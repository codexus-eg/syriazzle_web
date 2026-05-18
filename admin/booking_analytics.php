<?php
$page_title = 'تحليلات الحجوزات';
require_once 'header.php';

// --- حارس الصلاحيات ---
if (!hasPermission('view_booking_analytics')) {
    echo "<div class='container'><h2><i class='fas fa-exclamation-triangle'></i> وصول مرفوض</h2></div>";
    require_once 'footer.php';
    exit;
}
?>
<!-- تضمين مكتبة الرسوم البيانية Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    /* تنسيقات مخصصة لهذه الصفحة لضمان المظهر الاحترافي */
    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .kpi-card {
        background-color: var(--card-bg);
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        text-align: center;
        border-top: 4px solid var(--primary-blue);
    }
    .kpi-card .icon {
        font-size: 2rem;
        color: var(--primary-blue);
        margin-bottom: 1rem;
        opacity: 0.8;
    }
    .kpi-card .value {
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--sidebar-bg);
        letter-spacing: -1px;
    }
    .kpi-card .label {
        font-size: 1rem;
        color: #6c757d;
        font-weight: 600;
    }
    .kpi-card.pending { border-top-color: #ffc107; }
    .kpi-card.pending .icon { color: #ffc107; }
    .kpi-card.cancelled { border-top-color: #dc3545; }
    .kpi-card.cancelled .icon { color: #dc3545; }
    .kpi-card.confirmed { border-top-color: #198754; }
    .kpi-card.confirmed .icon { color: #198754; }

    .chart-container {
        grid-column: 1 / -1;
        background-color: var(--card-bg);
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.07);
        height: 400px;
    }
    .top-lists-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
        grid-column: 1 / -1;
        margin-top:40px;
    }
    .top-list-card {
        background-color: var(--card-bg);
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.07);
    }
    .top-list-card h3 {
        margin-top: 0;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0.75rem;
        margin-bottom: 1rem;
    }
    .top-list ol {
        list-style-type: none;
        padding: 0;
        margin: 0;
    }
    .top-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0.5rem;
        border-radius: 6px;
    }
    .top-list li:nth-child(odd) {
        background-color: #f8f9fa;
    }
    .top-list li .name { font-weight: 600; }
    .top-list li .stat { font-weight: 700; color: var(--primary-blue); white-space: nowrap; }

    .loading-overlay {
        position: absolute; inset: 0; background: rgba(255,255,255,0.7);
        display: flex; align-items: center; justify-content: center;
        font-size: 2rem; color: var(--primary-blue); border-radius: 12px; z-index: 10;
    }
</style>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> تحليلات نظام الحجوزات</h1>
        <p>نظرة شاملة على أداء ومؤشرات الحجوزات في المنصة.</p>
    </div>

    <div class="analytics-grid" id="analytics-container" style="position: relative;">
        <div class="loading-overlay" id="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
        </div>

        <div class="kpi-card">
            <div class="icon"><i class="fas fa-calendar-check"></i></div>
            <div class="value" id="kpi-total-bookings">0</div>
            <div class="label">إجمالي الحجوزات</div>
        </div>
        <div class="kpi-card confirmed">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <div class="value" id="kpi-confirmed-bookings">0</div>
            <div class="label">الحجوزات المؤكدة</div>
        </div>
        <div class="kpi-card pending">
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="value" id="kpi-pending-bookings">0</div>
            <div class="label">بانتظار المراجعة</div>
        </div>
        <div class="kpi-card cancelled">
            <div class="icon"><i class="fas fa-ban"></i></div>
            <div class="value" id="kpi-cancelled-bookings">0</div>
            <div class="label">الحجوزات الملغاة</div>
        </div>
        
        <div class="kpi-card">
            <div class="icon"><i class="fas fa-wallet"></i></div>
            <div class="value" id="kpi-total-revenue">0</div>
            <div class="label">إجمالي الإيرادات (ل.س)</div>
        </div>
        <div class="kpi-card confirmed">
            <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="value" id="kpi-total-commissions">0</div>
            <div class="label">إجمالي العمولات (ل.س)</div>
        </div>

        <div class="chart-container">
            <h3>الحجوزات الجديدة (آخر 30 يومًا)</h3>
            <canvas id="bookingsChart"></canvas>
        </div>

        <div class="top-lists-grid">
            <div class="top-list-card">
                <h3><i class="fas fa-trophy"></i> الأعلى إيرادات (من الحجوزات المؤكدة)</h3>
                <div class="top-list" id="top-revenue-list"></div>
            </div>
            <div class="top-list-card">
                <h3><i class="fas fa-star"></i> الأكثر حجوزات (من الحجوزات المؤكدة)</h3>
                <div class="top-list" id="top-bookings-list"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async function() {
    const loadingSpinner = document.getElementById('loading-spinner');
    
    // دالة لتنسيق الأرقام بدون فواصل عشرية
    const formatInteger = (num) => Number(num || 0).toLocaleString('en-US');

    try {
        const response = await fetch('php/ajax_get_booking_analytics.php');
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'فشل جلب البيانات من الخادم.');
        }
        const data = await response.json();

        if (data.success) {
            populateKPIs(data.kpis);
            renderChart(data.chart);
            populateTopLists(data.top_lists);
        } else {
            throw new Error(data.message || 'حدث خطأ غير معروف.');
        }

    } catch (error) {
        document.getElementById('analytics-container').innerHTML = `<p style="color:red; text-align:center;">${error.message}</p>`;
    } finally {
        if (loadingSpinner) {
            loadingSpinner.style.display = 'none';
        }
    }

    function populateKPIs(kpis) {
        document.getElementById('kpi-total-bookings').textContent = formatInteger(kpis.total_bookings);
        document.getElementById('kpi-confirmed-bookings').textContent = formatInteger(kpis.confirmed_bookings);
        document.getElementById('kpi-pending-bookings').textContent = formatInteger(kpis.pending_bookings);
        document.getElementById('kpi-cancelled-bookings').textContent = formatInteger(kpis.cancelled_bookings);
        document.getElementById('kpi-total-revenue').textContent = formatInteger(kpis.total_revenue);
        document.getElementById('kpi-total-commissions').textContent = formatInteger(kpis.total_commissions);
    }

    function renderChart(chartData) {
        const ctx = document.getElementById('bookingsChart');
        if (!ctx) return;
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'عدد الحجوزات',
                    data: chartData.data,
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    function populateTopLists(topLists) {
        const revenueList = document.getElementById('top-revenue-list');
        const bookingsList = document.getElementById('top-bookings-list');
        
        let revenueHtml = '<ol>';
        if (topLists.by_revenue.length > 0) {
            topLists.by_revenue.forEach(item => {
                revenueHtml += `<li><span class="name">${item.name}</span><span class="stat">${formatInteger(item.revenue)} ل.س</span></li>`;
            });
        } else {
            revenueHtml += '<p>لا توجد بيانات كافية.</p>';
        }
        revenueHtml += '</ol>';
        revenueList.innerHTML = revenueHtml;

        let bookingsHtml = '<ol>';
        if (topLists.by_bookings.length > 0) {
            topLists.by_bookings.forEach(item => {
                bookingsHtml += `<li><span class="name">${item.name}</span><span class="stat">${formatInteger(item.bookings_count)} حجز</span></li>`;
            });
        } else {
            bookingsHtml += '<p>لا توجد بيانات كافية.</p>';
        }
        bookingsHtml += '</ol>';
        bookingsList.innerHTML = bookingsHtml;
    }
});
</script>

<?php require_once 'footer.php'; ?>