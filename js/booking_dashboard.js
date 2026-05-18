// ========================================================================
// Syriazzle - Dynamic Dashboard Engine (النسخة 6.0 النهائية - هيكل كامل)
// ========================================================================

class BookingDashboardApp {
    constructor(config) {
        this.config = config;
        this.state = {
            services: [],
            resources: [],
            customers: [],
            bookings: [],
            businessDetails: config.businessData || {},
            currentView: null,
            editingServiceId: null,
            editingResourceId: null,
            editingCustomerId: null,
            currentBookingCategory: config.businessData?.booking_category || null,
        };
        this.dom = this.cacheDOMElements();
        this.api = new API(config.businessId);
        this.calendar = null;
        this.map = null;
        this.init();
    }

    cacheDOMElements() {
        return {
            sidebar: document.getElementById('sidebar'),
            openSidebarBtn: document.getElementById('open-sidebar-btn'),
            closeSidebarBtn: document.getElementById('close-sidebar-btn'),
            navLinks: document.querySelectorAll('.nav-link'),
            viewTitle: document.getElementById('view-title'),
            serviceModal: document.getElementById('service-modal'),
            serviceModalTitle: document.getElementById('service-modal-title'),
            serviceModalBody: document.querySelector('#service-modal .modal-body'),
            serviceModalCloseBtn: document.getElementById('service-modal-close-btn'),
            serviceForm: document.getElementById('service-form'),
            settingsForm: document.getElementById('settings-form'),
            bookingCategoryInput: document.getElementById('booking_category_input'),
            settingsNav: document.querySelector('.settings-nav'),
            settingsPanes: document.querySelectorAll('.settings-pane'),
            categoryGrid: document.querySelector('#category-settings .category-selector-grid'),
            mapContainer: document.getElementById('settings-map'),
            latitudeInput: document.getElementById('latitude_input'),
            longitudeInput: document.getElementById('longitude_input'),
            bookingDetailsModal: document.getElementById('booking-details-modal'),
            bookingModalTitle: document.getElementById('booking-modal-title'),
            bookingModalBody: document.getElementById('booking-modal-body'),
            bookingModalCloseBtn: document.getElementById('booking-modal-close-btn'),
            moduleContainer: document.getElementById('module-content-area'),
            settingsView: document.getElementById('settings-view'),
            dynamicManageLinkText: document.getElementById('dynamic-manage-link-text'),
        };
    }

    async init() {
        this.bindEventListeners();
        this.updateDynamicLink();
        try {
            const initialData = await this.api.getInitialData();
            this.state.businessDetails = initialData.businessDetails || {};
            this.state.services = initialData.services || [];
            this.state.bookings = initialData.bookings || [];
            
            if (['hotel', 'restaurant', 'event'].includes(this.state.currentBookingCategory)) {
                this.state.resources = await this.api.getResources();
            }

            this.ui.renderSettings();
            
            const urlParams = new URL(window.location).searchParams;
            const viewParam = urlParams.get('view');
            
            if (viewParam && document.querySelector(`.nav-link[data-view="${viewParam}"]`)) {
                this.navigateTo(viewParam);
            } else if (!this.state.currentBookingCategory) {
                this.navigateTo('settings');
            } else {
                this.navigateTo('overview');
            }
        } catch (error) {
            console.error("Initialization failed:", error);
            this.loadModuleContent('<p class="empty-state-message">فشل تحميل البيانات الأولية. يرجى تحديث الصفحة.</p>');
        }
    }

    bindEventListeners() {
        this.dom.openSidebarBtn.addEventListener('click', () => this.ui.toggleSidebar(true));
        this.dom.closeSidebarBtn.addEventListener('click', () => this.ui.toggleSidebar(false));

        this.dom.navLinks.forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                this.navigateTo(link.dataset.view);
                if (window.innerWidth <= 768) this.ui.toggleSidebar(false);
            });
        });

        this.dom.serviceModal.addEventListener('click', e => { if (e.target === this.dom.serviceModal) this.ui.toggleServiceModal(false); });
        this.dom.serviceModalCloseBtn.addEventListener('click', () => this.ui.toggleServiceModal(false));
        this.dom.serviceForm.addEventListener('submit', (e) => this.dispatchFormSubmit(e));
        
        this.dom.moduleContainer.addEventListener('click', e => {
            const addServiceBtn = e.target.closest('#add-new-service-btn');
            const addResourceBtn = e.target.closest('#add-new-resource-btn');
            const editServiceBtn = e.target.closest('.edit-service-btn');
            const editResourceBtn = e.target.closest('.edit-resource-btn');
            const viewNotesBtn = e.target.closest('.view-notes-btn');

            if (addServiceBtn) { this.ui.buildServiceForm(null); this.ui.toggleServiceModal(true); }
            if (addResourceBtn) { this.ui.buildResourceForm(null); this.ui.toggleServiceModal(true); }
            if (editServiceBtn) this.handleEditService(editServiceBtn.dataset.serviceId);
            if (editResourceBtn) this.handleEditResource(editResourceBtn.dataset.resourceId);
            if (viewNotesBtn) this.handleViewNotes(viewNotesBtn.dataset.customerId);
        });

        if (this.dom.settingsForm) this.dom.settingsForm.addEventListener('submit', e => this.handleSaveSettings(e));
        
        if (this.dom.settingsNav) {
            this.dom.settingsNav.addEventListener('click', e => {
                e.preventDefault();
                const link = e.target.closest('.settings-nav-link');
                if (link) this.ui.switchSettingsPane(link.getAttribute('href').substring(1));
            });
        }

        if (this.dom.categoryGrid) this.dom.categoryGrid.addEventListener('click', e => {
            const card = e.target.closest('.category-card');
            if (card) {
                this.dom.categoryGrid.querySelectorAll('.category-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                this.dom.bookingCategoryInput.value = card.dataset.categoryKey;
            }
        });
        
        this.dom.bookingDetailsModal.addEventListener('click', e => { if (e.target === this.dom.bookingDetailsModal) this.ui.toggleBookingModal(false); });
        this.dom.bookingModalCloseBtn.addEventListener('click', () => this.ui.toggleBookingModal(false));
    }

    dispatchFormSubmit(event) {
        event.preventDefault();
        const formType = event.target.dataset.formType;
        const submitButton = event.target.querySelector('button[type="submit"]');
        this.ui.setButtonLoading(submitButton, true);

        const handlerMap = {
            'service-form': this.handleSaveService,
            'resource-form': this.handleSaveResource,
            'customer-note-form': this.handleSaveCustomerNote,
            'availability-form': this.handleSaveAvailability,
        };

        const handler = handlerMap[formType];
        if (handler) {
            handler.call(this, event).finally(() => this.ui.setButtonLoading(submitButton, false));
        } else {
            console.error('Unknown form type:', formType);
            this.ui.setButtonLoading(submitButton, false);
        }
    }
    
    updateDynamicLink() {
        const map = {
            'hotel': 'إدارة الغرف', 'restaurant': 'إدارة الطاولات', 'event': 'إدارة القاعات',
            'clinic': 'إدارة الأصول الطبية', 'consulting': 'إدارة أنواع الجلسات', 'tourism': 'إدارة برامج الرحلات',
            'default': 'إدارة الأصول'
        };
        const linkText = map[this.state.currentBookingCategory] || map['default'];
        if (this.dom.dynamicManageLinkText) {
            this.dom.dynamicManageLinkText.textContent = linkText;
        }
    }

    navigateTo(viewName) {
        if (this.state.currentView === viewName && viewName !== 'settings') return;
        
        this.ui.showViewTransition();
        this.state.currentView = viewName;
        this.dom.navLinks.forEach(l => l.classList.remove('active'));
        const activeLink = document.querySelector(`.nav-link[data-view="${viewName}"]`);
        if(activeLink) activeLink.classList.add('active');

        this.dom.viewTitle.textContent = activeLink ? activeLink.querySelector('span').textContent : 'Syriazzle';
        window.history.pushState({view: viewName}, '', `?business_id=${this.config.businessId}&view=${viewName}`);
        
        this.dom.settingsView.style.display = 'none';

        const viewHandlers = {
            'overview': this.fetchAndRenderOverview,
            'calendar': this.renderCalendarView,
            'manage_resources': () => this.loadModuleFromServer('manage_resources'),
            'manage_services': () => this.loadModuleFromServer('manage_services'),
            'manage_availability': () => this.loadModuleFromServer('manage_availability'),
            'customers': this.fetchAndRenderCustomers,
            'settings': this.loadSettingsView,
        };

        const handler = viewHandlers[viewName] || (() => this.loadModuleContent('<p class="empty-state-message">صفحة غير معروفة.</p>'));
        handler.call(this);
    }

    loadModuleContent(html) {
        this.dom.moduleContainer.innerHTML = html;
        this.dom.moduleContainer.style.display = 'block';
    }

    loadSettingsView() {
        this.dom.moduleContainer.style.display = 'none';
        this.dom.settingsView.style.display = 'block';
        this.ui.switchSettingsPane('category-settings');
    }

    async loadModuleFromServer(viewType) {
        const category = this.state.currentBookingCategory;
        if (!category) {
            this.loadModuleContent('<div class="empty-state-message"><h3>يرجى تحديد فئة النشاط</h3><p>اذهب إلى <a href="#" onclick="event.preventDefault(); document.querySelector(`[data-view=settings]`).click()">الإعدادات</a> لتحديد نوع نشاطك أولاً.</p></div>');
            return;
        }
        
        this.loadModuleContent('<div class="spinner"><i class="fas fa-spinner fa-spin"></i></div>');
        
        let moduleName, dataFetchFunction;
        
        if (viewType === 'manage_resources') {
            moduleName = `${category}_module.php`;
            dataFetchFunction = this.fetchAndRenderResources;
        } else if (viewType === 'manage_services') {
            moduleName = 'services_module.php';
            dataFetchFunction = this.fetchAndRenderServices;
        } else if (viewType === 'manage_availability') {
            moduleName = 'availability_module.php';
            dataFetchFunction = this.fetchAndRenderAvailability;
        } else {
            this.loadModuleContent(`<p class="empty-state-message" style="color:red;">خطأ: عرض غير معروف.</p>`);
            return;
        }
        
        try {
            const response = await fetch(`php/business_dashboard_modules/${moduleName}`);
            if (!response.ok) throw new Error(`ملف الوحدة النمطية ${moduleName} غير موجود.`);
            
            this.loadModuleContent(await response.text());
            if (dataFetchFunction) dataFetchFunction.call(this);

        } catch (error) { 
            console.error(error);
            this.loadModuleContent(`<p class="empty-state-message" style="color:red;">خطأ: فشل تحميل وحدة الإدارة.</p>`); 
        }
    }

    async fetchAndRenderServices() {
        try {
            this.state.services = await this.api.getServices();
            this.ui.renderServicesTable(this.state.services);
        } catch(e) { /* handled by api */ }
    }

    async fetchAndRenderResources() {
        try {
            this.state.resources = await this.api.getResources();
            this.ui.renderResourcesTable(this.state.resources);
        } catch (e) { /* handled by api */ }
    }
    
    async fetchAndRenderAvailability() {
        try {
            if (this.state.services.length === 0) {
                this.state.services = await this.api.getServices();
            }
            this.ui.renderAvailabilityForm(this.state.services);
        } catch(e) {
            this.loadModuleContent('<p class="empty-state-message" style="color:red;">فشل تحميل بيانات التوافر.</p>');
        }
    }

    async fetchAndRenderCustomers() {
        this.loadModuleContent('<div class="spinner"><i class="fas fa-spinner fa-spin"></i></div>');
        try {
            this.state.customers = await this.api.getCustomers();
            const moduleHtml = await (await fetch('php/business_dashboard_modules/customers_module.php')).text();
            this.loadModuleContent(moduleHtml);
            this.ui.renderCustomersTable(this.state.customers);
        } catch (e) { /* handled by api */ }
    }
    
    async fetchAndRenderOverview() {
        this.loadModuleContent('<div class="spinner"><i class="fas fa-spinner fa-spin"></i></div>');
        try {
            const stats = await this.api.getOverviewStats();
            const moduleHtml = await (await fetch('php/business_dashboard_modules/overview_module.php')).text();
            this.loadModuleContent(moduleHtml);
            this.ui.renderOverviewStats(stats);
        } catch (e) { /* handled by api */ }
    }

    renderCalendarView() {
        this.loadModuleContent('<div class="calendar-wrapper"><div id="dynamic-calendar"></div></div>');
        this.ui.renderCalendar(document.getElementById('dynamic-calendar'));
    }

    handleEditService(serviceId) {
        const service = this.state.services.find(s => s.id == serviceId);
        if (service) { this.ui.buildServiceForm(service); this.ui.toggleServiceModal(true); }
    }

    handleEditResource(resourceId) {
        const resource = this.state.resources.find(r => r.id == resourceId);
        if (resource) { this.ui.buildResourceForm(resource); this.ui.toggleServiceModal(true); }
    }
    
    async handleViewNotes(customerId) {
        this.ui.buildCustomerNoteForm(null);
        this.ui.toggleServiceModal(true);
        try {
            if (!this.state.customers.length) {
                this.state.customers = await this.api.getCustomers();
            }
            const customer = this.state.customers.find(c => c.id == customerId);
            if (customer) this.ui.buildCustomerNoteForm(customer);
            else throw new Error("Customer not found");
        } catch(e) { this.ui.toggleServiceModal(false); }
    }
    
    async handleSaveService(event) {
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData.entries());
        data.is_active = formData.has('is_active') ? 1 : 0;
        data.service_id = this.state.editingServiceId;
        try {
            await this.api.saveService(data);
            this.ui.toggleServiceModal(false);
            this.loadModuleFromServer('manage_services');
        } catch(e) { /* handled by api */ }
    }

    async handleSaveResource(event) {
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData.entries());
        data.resource_id = this.state.editingResourceId;
        data.resource_type = this.state.currentBookingCategory;
        try {
            await this.api.saveResource(data);
            this.ui.toggleServiceModal(false);
            this.loadModuleFromServer('manage_resources');
        } catch(e) { /* handled by api */ }
    }

    async handleSaveCustomerNote(event) {
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData.entries());
        try {
            await this.api.saveCustomerNote(data);
            this.ui.toggleServiceModal(false);
        } catch(e) { /* handled by api */ }
    }

    async handleSaveSettings(event) {
        event.preventDefault();
        const submitButton = event.target.querySelector('button[type="submit"]');
        this.ui.setButtonLoading(submitButton, true, "جار حفظ الإعدادات...");
        const formData = new FormData(event.target);
        formData.append('business_id', this.config.businessId);
        try {
            const result = await this.api.saveSettingsWithFiles(formData);
            if(result.success) {
                alert("تم تحديث الإعدادات بنجاح! سيتم تحديث الصفحة لتطبيق التغييرات.");
                window.location.reload();
            }
        } catch(e) { /* Handled by API */ }
        finally {
            this.ui.setButtonLoading(submitButton, false, "حفظ الإعدادات");
        }
    }

    async handleSaveAvailability(event) {
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData.entries()); // FormData does not handle nested structure well
        // We need to process the form data into a structured object
        const structuredData = {};
        for (const [key, value] of formData.entries()) {
            const match = key.match(/availability\[(\d+)\]\[days\]\[(\d+)\]\[(start|end)\]/);
            if (match && value) {
                const [, serviceId, dayIndex, type] = match;
                if (!structuredData[serviceId]) structuredData[serviceId] = [];
                // Check if the day object exists
                let dayObj = structuredData[serviceId].find(d => d.day_of_week === dayIndex);
                if (!dayObj) {
                    dayObj = { day_of_week: dayIndex };
                    structuredData[serviceId].push(dayObj);
                }
                dayObj[type + '_time'] = value;
            }
        }
        
        try {
            const result = await this.api.saveAvailability(structuredData);
            alert(result.message);
        } catch(e) { /* handled by api */ }
    }
    
    get ui() {
        const self = this; 
        return {
            setButtonLoading(button, isLoading, loadingText = "جاري الحفظ...") {
                if (!button) return;
                if (!button.dataset.defaultText) {
                    button.dataset.defaultText = button.innerHTML;
                }
                if (isLoading) {
                    button.disabled = true;
                    button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${loadingText}`;
                } else {
                    button.disabled = false;
                    button.innerHTML = button.dataset.defaultText;
                }
            },

            showViewTransition() {
                self.dom.moduleContainer.classList.add('fade-out');
                self.dom.settingsView.classList.add('fade-out');
                setTimeout(() => {
                    self.dom.moduleContainer.classList.remove('fade-out');
                    self.dom.settingsView.classList.remove('fade-out');
                }, 250);
            },

            renderOverviewStats(stats) {
                const container = document.getElementById('overview-stats-container');
                const recentBody = document.querySelector('#recent-bookings-table tbody');
                if (!container || !recentBody) return;

                container.innerHTML = `
                    <div class="stat-card"><h3>حجوزات الشهر</h3><p>${stats.confirmed_this_month}</p></div>
                    <div class="stat-card"><h3>إيرادات الشهر</h3><p>${stats.revenue_this_month} ل.س</p></div>
                    <div class="stat-card"><h3>عملاء جدد</h3><p>${stats.new_customers_this_month}</p></div>
                `;
                recentBody.innerHTML = stats.recent_bookings.length ? stats.recent_bookings.map(b => `
                    <tr>
                        <td>${b.username}</td>
                        <td>${b.item_name || b.service_name}</td>
                        <td>${new Date(b.start_datetime).toLocaleString('ar-SY', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })}</td>
                        <td><span class="status-badge status-${b.status}">${b.status}</span></td>
                    </tr>
                `).join('') : '<tr><td colspan="4" class="text-center">لا توجد حجوزات حديثة.</td></tr>';
            },

            renderServicesTable(services) {
                const tableBody = document.querySelector('#services-table tbody');
                if (!tableBody) return;
                tableBody.innerHTML = services.length ? services.map(s => {
                    const linkedResourceName = s.resource_id ? (self.state.resources.find(r => r.id == s.resource_id)?.name || '<span style="color:red;">غير مربوط</span>') : '<em>لا ينطبق</em>';
                    return `
                    <tr>
                        <td>${s.name}</td>
                        <td>${parseInt(s.price).toLocaleString()} ل.س</td>
                        <td><span class="status-badge ${s.is_active == 1 ? 'active' : ''}">${s.is_active == 1 ? 'نشط' : 'غير نشط'}</span></td>
                        <td>${linkedResourceName}</td>
                        <td><button class="btn btn-secondary btn-sm edit-service-btn" data-service-id="${s.id}"><i class="fas fa-edit"></i> تعديل</button></td>
                    </tr>`;
                }).join('') : `<tr><td colspan="5" class="text-center">لم تقم بإضافة أي خدمات بعد.</td></tr>`;
            },

            renderResourcesTable(resources) {
                const tableBody = document.querySelector('#resources-table tbody');
                if (!tableBody) return;
                const headers = tableBody.closest('table').querySelector('thead tr');
                
                const category = self.state.currentBookingCategory;
                if (category === 'restaurant') { headers.innerHTML = '<th>اسم الطاولة</th><th>الحالة</th><th>السعة (أشخاص)</th><th>الموقع</th><th>إجراءات</th>'; }
                else if (category === 'event') { headers.innerHTML = '<th>اسم القاعة</th><th>الحالة</th><th>السعة (أشخاص)</th><th>إجراءات</th>'; }
                else { headers.innerHTML = '<th>اسم الغرفة/الجناح</th><th>الحالة</th><th>سعة (بالغين/أطفال)</th><th>تفاصيل</th><th>إجراءات</th>'; }

                tableBody.innerHTML = resources.length ? resources.map(r => {
                    const meta = r.meta_data ? JSON.parse(r.meta_data) : {};
                    let detailsHtml = '';
                    if (category === 'hotel') { detailsHtml = `<td>${meta.capacity_adults || '-'}/${meta.capacity_children !== undefined ? meta.capacity_children : '-'}</td><td>${meta.view || meta.floor || '-'}</td>`; }
                    else if (category === 'restaurant') { detailsHtml = `<td>${meta.capacity || meta.capacity_adults || '-'}</td><td>${meta.location || '-'}</td>`; }
                    else if (category === 'event') { detailsHtml = `<td>${meta.capacity || meta.capacity_adults || '-'}</td>`; }
                    
                    return `
                    <tr>
                        <td>${r.name}</td>
                        <td><span class="status-badge status-${r.status}">${r.status}</span></td>
                        ${detailsHtml}
                        <td><button class="btn btn-secondary btn-sm edit-resource-btn" data-resource-id="${r.id}"><i class="fas fa-edit"></i> تعديل</button></td>
                    </tr>`;
                }).join('') : `<tr><td colspan="5" class="text-center">لم تقم بإضافة أي أصول بعد.</td></tr>`;
            },
            
            renderAvailabilityForm(services) {
                const container = document.getElementById('availability-form-container');
                if (!container) return;
                
                const timeSlotServices = services.filter(s => s.booking_model === 'time_slot');
                if (timeSlotServices.length === 0) {
                    container.innerHTML = '<p class="empty-state-message">لا توجد خدمات تعمل بنظام المواعيد. يرجى إضافة خدمة أولاً من تبويب "الخدمات والأسعار".</p>';
                    return;
                }
    
                let formHtml = '<form id="availability-form" data-form-type="availability-form">';
                timeSlotServices.forEach(service => {
                    formHtml += `
                        <div class="availability-card">
                            <h4>${service.name}</h4>
                            <div class="days-grid">
                    `;
                    const days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                    for (let i = 0; i < 7; i++) {
                        formHtml += `
                            <div class="day-row">
                                <label>${days[i]}</label>
                                <input type="time" name="availability[${service.id}][days][${i}][start]">
                                <input type="time" name="availability[${service.id}][days][${i}][end]">
                            </div>
                        `;
                    }
                    formHtml += '</div></div>';
                });
                formHtml += '<div class="form-footer"><button type="submit" class="btn btn-primary">حفظ جداول التوافر</button></div></form>';
                container.innerHTML = formHtml;
            },

            renderCustomersTable(customers) {
                const tableBody = document.querySelector('#customers-table tbody');
                if (!tableBody) return;
                tableBody.innerHTML = customers.length ? customers.map(c => `
                    <tr><td>${c.username}</td><td>${c.phone || '-'}</td><td>${c.total_bookings_count}</td><td>${new Date(c.first_booking_date).toLocaleDateString('ar-SY')}</td>
                    <td><button class="btn btn-secondary btn-sm view-notes-btn" data-customer-id="${c.id}"><i class="fas fa-sticky-note"></i> الملاحظات</button></td>
                    </tr>`).join('') : '<tr><td colspan="5" class="text-center">لا يوجد عملاء بعد. سيبدأ الجدول بالامتلاء مع أول حجز مؤكد.</td></tr>';
            },
            renderCalendar(container) {
                if (self.calendar) self.calendar.destroy();
                self.calendar = new FullCalendar.Calendar(container, {
                    locale: 'ar',
                    headerToolbar: {left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay'}, // إضافة عرض اليوم
                    events: self.state.bookings,
                    eventTimeFormat: { // تنسيق الوقت داخل الحدث
                        hour: '2-digit',
                        minute: '2-digit',
                        meridiem: 'short'
                    },
                    eventClick: (info) => {
                        const event = info.event;
                        const props = event.extendedProps;
                        
                        // دالة مساعدة لتنسيق التاريخ والوقت
                        const formatFullDateTime = (date) => {
                            if (!date) return 'غير محدد';
                            return new Date(date).toLocaleString('ar-SY', {
                                year: 'numeric', month: 'long', day: 'numeric',
                                hour: '2-digit', minute: '2-digit'
                            });
                        };

                        this.toggleBookingModal(true);
                        self.dom.bookingModalTitle.textContent = `تفاصيل الحجز #${event.id}`;
                        
                        // **عرض تفاصيل كاملة ودقيقة في النافذة المنبثقة**
                        self.dom.bookingModalBody.innerHTML = `
                            <div class="booking-details-list">
                                <div class="detail-row"><span>الخدمة/الغرفة:</span><strong>${event.title}</strong></div>
                                <div class="detail-row"><span>الحالة:</span><strong><span class="status-badge status-${props.status}">${props.status}</span></strong></div>
                                <div class="detail-row"><span>بداية الحجز:</span><strong>${formatFullDateTime(event.start)}</strong></div>
                                <div class="detail-row"><span>نهاية الحجز:</span><strong>${formatFullDateTime(event.end)}</strong></div>
                            </div>
                        `;
                    }
                });
                setTimeout(() => self.calendar.render(), 50);
            },
            
            buildServiceForm(data) {
                self.state.editingServiceId = data ? data.id : null;
                self.dom.serviceForm.dataset.formType = 'service-form';
                self.dom.serviceModalTitle.textContent = data ? `تعديل الخدمة: ${data.name}` : 'إضافة خدمة/سعر جديد';
                const category = self.state.currentBookingCategory;

                let resourceOptions = '';
                if (['hotel', 'restaurant', 'event'].includes(category)) {
                    resourceOptions += `<div class="form-group"><label for="resource_id">ربط الأصل (الغرفة/الطاولة) *</label><select name="resource_id" required>`;
                    resourceOptions += '<option value="">-- اختر الأصل الذي ينطبق عليه هذا السعر --</option>';
                    self.state.resources.forEach(r => {
                        resourceOptions += `<option value="${r.id}" ${data?.resource_id == r.id ? 'selected' : ''}>${r.name}</option>`;
                    });
                    resourceOptions += '</select></div>';
                }

                const modelOptions = (['clinic', 'consulting', 'tourism'].includes(category)) ? `<option value="time_slot" selected>نظام مواعيد</option>` : `<option value="asset" selected>نظام أصول</option>`;
                
                const formBody = `
                    <div class="modal-body">
                        <fieldset>
                            <legend>المعلومات الأساسية</legend>
                            <div class="form-group"><label>اسم الخدمة/عرض السعر *</label><input name="name" value="${data?.name || ''}" required></div>
                            ${resourceOptions}
                            <div class="form-group"><label>الوصف</label><textarea name="description" rows="3">${data?.description || ''}</textarea></div>
                        </fieldset>
                        <fieldset>
                            <legend>التسعير والنظام</legend>
                            <div class="form-row">
                                <div class="form-group" style="display:none;"><label>نظام الحجز</label><select name="booking_model">${modelOptions}</select></div>
                                <div class="form-group"><label>نوع السعر</label><select name="price_type"><option value="fixed" ${data?.price_type === 'fixed' ? 'selected' : ''}>سعر ثابت</option><option value="per_night" ${data?.price_type === 'per_night' ? 'selected' : ''}>بالليلة</option><option value="per_hour" ${data?.price_type === 'per_hour' ? 'selected' : ''}>بالساعة</option></select></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>السعر (ل.س) *</label><input name="price" type="number" value="${data?.price || ''}" required></div>
                                <div class="form-group"><label>نسبة العربون المطلوب (%)</label><input name="deposit_required_percentage" type="number" value="${data?.deposit_required_percentage || '0'}"></div>
                            </div>
                            ${(['clinic', 'consulting'].includes(category)) ? `<div class="form-group"><label>مدة الجلسة (دقائق)</label><input name="duration_minutes" type="number" value="${data?.duration_minutes || '30'}"></div>` : ''}
                        </fieldset>
                        <div class="form-group-checkbox"><label><input type="checkbox" name="is_active" ${data ? (data.is_active == 1 ? 'checked' : '') : 'checked'}> تفعيل الخدمة ليتمكن العملاء من حجزها</label></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="service-modal-cancel-btn-inner">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ</button>
                    </div>
                `;
                
                self.dom.serviceForm.innerHTML = formBody;
                self.dom.serviceForm.querySelector('#service-modal-cancel-btn-inner').addEventListener('click', () => this.toggleServiceModal(false));
            },
            
            buildResourceForm(data) {
                self.state.editingResourceId = data ? data.id : null;
                self.dom.serviceForm.dataset.formType = 'resource-form';
                self.dom.serviceModalTitle.textContent = data ? `تعديل: ${data.name}` : 'إضافة أصل جديد';
                const meta = data ? JSON.parse(data.meta_data || '{}') : {};
                self.dom.serviceModalBody.innerHTML = `
                    <div class="form-group"><label>الاسم *</label><input name="name" value="${data?.name || ''}" required></div>
                    <div class="form-group"><label>الحالة</label><select name="status"><option value="available" ${data?.status === 'available' ? 'selected':''}>متاح</option><option value="maintenance" ${data?.status === 'maintenance' ? 'selected':''}>صيانة</option><option value="unavailable" ${data?.status === 'unavailable' ? 'selected':''}>غير متاح</option></select></div>
                    <div class="form-row"><div class="form-group"><label>السعة (أشخاص/بالغين)</label><input name="capacity_adults" type="number" value="${meta.capacity_adults || '2'}"></div><div class="form-group"><label>سعة الأطفال (إن وجد)</label><input name="capacity_children" type="number" value="${meta.capacity_children || '0'}"></div></div>
                    <div class="form-group"><label>تفاصيل إضافية (الموقع/الإطلالة/الطابق)</label><input name="view" value="${meta.view || meta.location || meta.floor || ''}"></div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" id="service-modal-cancel-btn-inner">إلغاء</button><button type="submit" class="btn btn-primary" data-default-text="حفظ"><i class="fas fa-save"></i> حفظ</button></div>
                `;
                self.dom.serviceModalBody.querySelector('#service-modal-cancel-btn-inner').addEventListener('click', () => this.toggleServiceModal(false));
            },
            buildCustomerNoteForm(data) {
                self.dom.serviceForm.dataset.formType = 'customer-note-form';
                self.dom.serviceModalTitle.textContent = data ? `ملاحظات حول: ${data.username}` : 'تحميل...';
                self.dom.serviceModalBody.innerHTML = data ? `
                    <input type="hidden" name="customer_id" value="${data.id}">
                    <div class="form-group"><label>الملاحظات المسجلة</label><textarea name="notes" rows="6" placeholder="مثال: يفضل الطاولة بجانب النافذة، يعاني من حساسية...">${data.notes || ''}</textarea></div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" id="service-modal-cancel-btn-inner">إلغاء</button><button type="submit" class="btn btn-primary" data-default-text="حفظ"><i class="fas fa-save"></i> حفظ</button></div>
                ` : '<div class="spinner"><i class="fas fa-spinner fa-spin"></i></div>';
                if(data) self.dom.serviceModalBody.querySelector('#service-modal-cancel-btn-inner').addEventListener('click', () => this.toggleServiceModal(false));
            },

            toggleSidebar(open) { self.dom.sidebar.classList.toggle('open', open); },
            toggleServiceModal(open) { self.dom.serviceModal.classList.toggle('active', open); },
            toggleBookingModal(open) { self.dom.bookingDetailsModal.classList.toggle('active', open); },

            switchSettingsPane(paneId) {
                self.dom.settingsPanes.forEach(p => p.classList.remove('active'));
                self.dom.settingsNav.querySelectorAll('.settings-nav-link').forEach(l => l.classList.remove('active'));
                const pane = document.getElementById(paneId);
                const link = document.querySelector(`.settings-nav-link[href="#${paneId}"]`);
                if(pane) pane.classList.add('active');
                if(link) link.classList.add('active');
                if (paneId === 'location-settings') setTimeout(() => this.initializeMap(), 50);
            },
            initializeMap() {
                if (self.map) { self.map.invalidateSize(); return; }
                if (!self.dom.mapContainer) return;
                const lat = parseFloat(self.state.businessDetails.latitude) || 33.5138; const lng = parseFloat(self.state.businessDetails.longitude) || 36.2765;
                self.map = L.map(self.dom.mapContainer).setView([lat, lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(self.map);
                const search = new GeoSearch.GeoSearchControl({ provider: new GeoSearch.OpenStreetMapProvider(), style: 'bar', showMarker: false, autoClose: true });
                self.map.addControl(search);
                self.map.on('move', () => { const center = self.map.getCenter(); self.dom.latitudeInput.value = center.lat.toFixed(8); self.dom.longitudeInput.value = center.lng.toFixed(8); });
                setTimeout(() => self.map.invalidateSize(), 100);
            },
            renderSettings() {
                const categories = [ { key: 'hotel', name: 'فندق/شاليه', icon: 'fa-hotel' }, { key: 'restaurant', name: 'مطعم (طاولات)', icon: 'fa-utensils' }, { key: 'clinic', name: 'عيادة/طبيب', icon: 'fa-user-doctor' }, { key: 'consulting', name: 'استشارات/خدمات', icon: 'fa-handshake' }, { key: 'tourism', name: 'سياحة وسفر', icon: 'fa-plane' }, { key: 'event', name: 'مناسبات/صالات', icon: 'fa-calendar-check' }];
                self.dom.categoryGrid.innerHTML = categories.map(cat => `<div class="category-card ${self.state.currentBookingCategory === cat.key ? 'selected' : ''}" data-category-key="${cat.key}"><i class="fas ${cat.icon}"></i><span>${cat.name}</span></div>`).join('');
                self.dom.bookingCategoryInput.value = self.state.currentBookingCategory || '';
                const d = self.state.businessDetails;
                document.getElementById('business_name').value = d.name || ''; document.getElementById('business_description').value = d.description || ''; document.getElementById('business_phone').value = d.phone || ''; document.getElementById('business_whatsapp').value = d.whatsapp || ''; self.dom.latitudeInput.value = d.latitude || ''; self.dom.longitudeInput.value = d.longitude || '';
                this.createImageUploader('logo-uploader', 'logo_image', d.logo_image); this.createImageUploader('cover-uploader', 'cover_image', d.cover_image); this.createGalleryUploader('gallery-uploader', 'gallery_images', d.gallery_images || []);
                const p = d.payment_details ? (typeof d.payment_details === 'string' ? JSON.parse(d.payment_details) : d.payment_details) : {};
                document.getElementById('syriatel_cash_number').value = p.syriatel_cash || ''; document.getElementById('mtn_cash_number').value = p.mtn_cash || ''; document.getElementById('sham_cash_number').value = p.sham_cash || '';
            },
            createImageUploader(containerId, inputName, imageUrl) {
                const container = document.getElementById(containerId); if (!container) return;
                const hasImage = imageUrl && imageUrl.length > 0;
                container.innerHTML = `<img src="${imageUrl}" class="preview" style="display:${hasImage ? 'block' : 'none'}"><div class="upload-ui"><i class="fas fa-camera"></i><span>${hasImage ? 'تغيير الصورة' : 'رفع صورة'}</span></div><input type="file" name="${inputName}" accept="image/*" style="display:none"><button type="button" class="delete-btn" style="display: ${hasImage ? 'flex' : 'none'};">&times;</button><input type="hidden" name="delete_${inputName}" value="0">`;
                container.addEventListener('click', (e) => { if (e.target.classList.contains('delete-btn')) return; container.querySelector('input[type="file"]').click() });
                container.querySelector('input[type="file"]').addEventListener('change', (event) => { const file = event.target.files[0]; if (!file) return; const reader = new FileReader(); reader.onload = (e) => { let preview = container.querySelector('.preview'); if (!preview) { preview = document.createElement('img'); preview.className = 'preview'; container.prepend(preview); } preview.src = e.target.result; preview.style.display = 'block'; container.querySelector('.delete-btn').style.display = 'flex'; container.querySelector(`input[name="delete_${inputName}"]`).value = '0'; }; reader.readAsDataURL(file); });
                container.querySelector('.delete-btn').addEventListener('click', (e) => { e.stopPropagation(); container.querySelector(`input[name="delete_${inputName}"]`).value = '1'; const preview = container.querySelector('.preview'); if (preview) preview.remove(); container.querySelector('input[type="file"]').value = ''; e.target.style.display = 'none'; });
            },
            createGalleryUploader(containerId, inputName, images) {
                const container = document.getElementById(containerId); if (!container) return; container.innerHTML = '';
                images.forEach(image => { const item = document.createElement('div'); item.className = 'gallery-item'; item.innerHTML = `<img src="${image.image_path}" alt="Gallery image"><button type="button" class="delete-btn" data-id="${image.id}">&times;</button>`; container.appendChild(item); });
                container.addEventListener('click', function(e) { if (e.target.classList.contains('delete-btn')) { const item = e.target.closest('.gallery-item'); const imageId = e.target.dataset.id; const hiddenInput = document.createElement('input'); hiddenInput.type = 'hidden'; hiddenInput.name = 'delete_gallery_ids[]'; hiddenInput.value = imageId; self.dom.settingsForm.appendChild(hiddenInput); item.style.opacity = '0.4'; e.target.remove(); } });
                const addButton = document.createElement('div'); addButton.className = 'gallery-add-btn'; addButton.innerHTML = `<i class="fas fa-plus"></i><span>إضافة صورة</span><input type="file" name="${inputName}[]" multiple accept="image/*" style="display:none;">`; container.appendChild(addButton); const fileInput = addButton.querySelector('input');
                addButton.addEventListener('click', () => fileInput.click());
                fileInput.addEventListener('change', (event) => { Array.from(event.target.files).forEach(file => { const reader = new FileReader(); reader.onload = (e) => { const newImagePreview = document.createElement('div'); newImagePreview.className = 'gallery-item new-preview'; newImagePreview.innerHTML = `<img src="${e.target.result}" alt="New image preview">`; container.insertBefore(newImagePreview, addButton); }; reader.readAsDataURL(file); }); });
            },
        };
    }
}


class API {
    constructor(businessId) { this.businessId = businessId; }
    
    async fetchData(endpoint, body) {
        try {
            const isFormData = body instanceof FormData;
            const options = { method: 'POST', body: isFormData ? body : JSON.stringify(body), headers: isFormData ? {} : { 'Content-Type': 'application/json' } };
            const response = await fetch(endpoint, options);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ message: 'خطأ غير معروف في الخادم.' }));
                throw new Error(errorData.message || 'فشل الاتصال بالخادم.');
            }
            const result = await response.json();
            if (result.success === false) { 
                throw new Error(result.message || 'حدث خطأ غير متوقع.');
            }
            return result;
        } catch (error) {
            console.error(`API Error (${endpoint}):`, error);
            alert(`فشل الإجراء: ${error.message}`);
            throw error;
        }
    }
    
    async getInitialData() { const res = await this.fetchData('php/ajax_get_booking_data.php', { business_id: this.businessId, type: 'initial' }); return res.data; }
    async getOverviewStats() { const res = await this.fetchData('php/ajax_get_booking_data.php', { business_id: this.businessId, type: 'overview_stats' }); return res.data; }
    async getServices() { const res = await this.fetchData('php/ajax_get_booking_data.php', { business_id: this.businessId, type: 'services' }); return res.data; }
    async getResources() { const res = await this.fetchData('php/ajax_get_booking_data.php', { business_id: this.businessId, type: 'resources' }); return res.data; }
    async getCustomers() { const res = await this.fetchData('php/ajax_get_booking_data.php', { business_id: this.businessId, type: 'customers' }); return res.data; }
    async saveService(data) { data.business_id = this.businessId; return await this.fetchData('php/ajax_manage_service.php', data); }
    async saveResource(data) { data.business_id = this.businessId; return await this.fetchData('php/ajax_manage_resource.php', data); }
    async saveCustomerNote(data) { data.business_id = this.businessId; return await this.fetchData('php/ajax_manage_customer_note.php', data); }
    async saveSettingsWithFiles(formData) { return await this.fetchData('php/ajax_save_booking_settings.php', formData); }
    async saveAvailability(data) { return await this.fetchData('php/ajax_manage_availability.php', { business_id: this.businessId, availability: data });}
}


document.addEventListener('DOMContentLoaded', () => {
    if (typeof BUSINESS_DATA !== 'undefined' && typeof CURRENT_BUSINESS_ID !== 'undefined') {
        new BookingDashboardApp({ businessId: CURRENT_BUSINESS_ID, businessData: BUSINESS_DATA });
    } else {
        const noBusinessContainer = document.querySelector('.no-booking-business-found');
        if (!noBusinessContainer) {
            const container = document.querySelector('.dashboard-container') || document.body;
            container.innerHTML = '<p class="empty-state-message">خطأ حرج: لم يتم تحميل بيانات النشاط التجاري.</p>';
        }
    }
});