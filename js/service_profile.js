// ========================================================================
// Syriazzle Services - Service Profile Logic (النسخة 9.0 النهائية - تدعم الأصول والخدمات)
// ========================================================================

class ServiceProfileApp {
    constructor(businessData, servicesData, existingBookings) {
        this.business = businessData;
        this.services = servicesData; // الآن قد تحتوي على بيانات الأصول
        this.bookings = existingBookings;
        this.state = {
            selectedServiceId: null,
            checkinDate: null,
            checkoutDate: null,
            appointmentDate: null,
            appointmentTime: null, // سيخزن دائماً بنظام 24 ساعة
        };
        this.dom = this.cacheDOMElements();
        this.pickers = {};
        this.init();
    }

    cacheDOMElements() {
        return {
            tabLinks: document.querySelectorAll('.tab-link'),
            tabPanes: document.querySelectorAll('.tab-pane'),
            bookingInterfaceContainer: document.getElementById('booking-interface-container'),
            bookingSummaryContainer: document.getElementById('booking-summary-container'),
            checkoutButton: document.getElementById('checkout-button'),
            checkoutForm: document.getElementById('checkout-form'),
            checkoutFormInput: document.getElementById('booking-data-input'),
            servicesContainer: document.getElementById('services-list-container'),
            mapContainer: document.getElementById('map'),
        };
    }

    init() {
        this.bindEventListeners();
        this.ui.renderInitialBookingUI();
        this.ui.renderServicesList(); // هذه الدالة أصبحت الآن ذكية
        this.ui.initializeMap();
        // تفعيل التبويب الأول عند التحميل
        if (this.dom.tabLinks.length > 0) {
            this.handleTabChange(this.dom.tabLinks[0].dataset.tab);
        }
    }

    bindEventListeners() {
        this.dom.tabLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleTabChange(link.dataset.tab);
            });
        });

        this.dom.servicesContainer.addEventListener('click', e => {
            const card = e.target.closest('.service-card');
            if (card && !card.classList.contains('disabled')) {
                const selectBtn = card.querySelector('.select-service-btn');
                if(selectBtn) this.handleServiceSelect(selectBtn.dataset.serviceId);
            }
        });

        this.dom.bookingInterfaceContainer.addEventListener('click', e => {
            const timeSlot = e.target.closest('.time-slot');
            if (timeSlot && !timeSlot.classList.contains('disabled')) {
                this.handleTimeSlotSelect(timeSlot);
            }
        });

        this.dom.checkoutButton.addEventListener('click', () => this.handleCheckout());
    }
    
    handleTabChange(tabId) {
        this.dom.tabPanes.forEach(pane => pane.classList.toggle('active', pane.id === tabId));
        this.dom.tabLinks.forEach(link => link.classList.toggle('active', link.dataset.tab === tabId));
    }

    handleServiceSelect(serviceId) {
        this.state.selectedServiceId = this.state.selectedServiceId === parseInt(serviceId) ? null : parseInt(serviceId);
        
        if (['clinic', 'consulting', 'tourism'].includes(this.business.booking_category)) {
            this.state.appointmentTime = null; 
            this.ui.renderTimeSlots(); 
        }
        
        this.ui.updateSelectedServiceCard();
        this.ui.updateBookingSummary();
    }
    
    handleTimeSlotSelect(target) {
        this.dom.bookingInterfaceContainer.querySelectorAll('.time-slot').forEach(slot => slot.classList.remove('selected'));
        target.classList.add('selected');
        this.state.appointmentTime = target.dataset.time; 
        
        const timeSlotServices = this.services.filter(s => s.booking_model === 'time_slot');
        if (timeSlotServices.length === 1 && !this.state.selectedServiceId) {
            this.state.selectedServiceId = timeSlotServices[0].id;
            this.ui.updateSelectedServiceCard();
        }
        this.ui.updateBookingSummary();
    }

    async handleCheckout() {
        const checkoutBtn = this.dom.checkoutButton;
        checkoutBtn.disabled = true;
        checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جار التحقق من التوافر...';

        const bookingData = { business_id: this.business.id };
        const selectedService = this.services.find(s => s.id === this.state.selectedServiceId);

        if (['hotel', 'restaurant', 'event', 'tourism'].includes(this.business.booking_category)) {
            // دالة مساعدة لتحويل التاريخ والوقت إلى الصيغة الصحيحة
            const formatDateTimeForServer = (date) => {
                return date.toISOString().slice(0, 19).replace('T', ' ');
            };

            const nights = Math.max(1, Math.ceil((this.state.checkoutDate - this.state.checkinDate) / (1000 * 60 * 60 * 24)));
            bookingData.total_price = nights * selectedService.price;
            
            // **التعديل الحاسم هنا: إرسال التاريخ والوقت معًا**
            bookingData.start_datetime = formatDateTimeForServer(this.state.checkinDate);
            bookingData.end_datetime = formatDateTimeForServer(this.state.checkoutDate);
            
            bookingData.details = { 
                nights, 
                service_name: selectedService.resource_name,
                checkin_time: this.state.checkinDate.toTimeString().slice(0, 5),
                checkout_time: this.state.checkoutDate.toTimeString().slice(0, 5)
            };
            bookingData.resource_id = selectedService.resource_id_fk;
        } else {
            const dateStr = this.state.appointmentDate.toLocaleDateString('en-CA');
            bookingData.total_price = selectedService.price;
            bookingData.start_datetime = `${dateStr} ${this.state.appointmentTime}`;
            const duration = selectedService.duration_minutes || 30;
            const startDate = new Date(bookingData.start_datetime);
            const endDate = new Date(startDate.getTime() + duration * 60000);
            bookingData.end_datetime = endDate.toISOString().slice(0, 19).replace('T', ' ');
            bookingData.details = { date: dateStr, time: this.state.appointmentTime, service_name: selectedService.name };
        }
        bookingData.service_id = selectedService.id;

        try {
            const response = await fetch('php/ajax_check_realtime_availability.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    service_id: bookingData.service_id,
                    resource_id: bookingData.resource_id || null,
                    start_datetime: bookingData.start_datetime,
                    end_datetime: bookingData.end_datetime,
                })
            });

            if (!response.ok) { throw new Error('خطأ في الاتصال بالخادم.'); }
            
            const result = await response.json();

            if (result.available) {
                checkoutBtn.innerHTML = '<i class="fas fa-check"></i> تم التأكيد! جار التحضير...';
                this.dom.checkoutFormInput.value = JSON.stringify(bookingData);
                setTimeout(() => { this.dom.checkoutForm.submit(); }, 500);
            } else {
                alert("عذرًا! هذا الخيار أصبح غير متاح للتو. يرجى اختيار وقت آخر أو تحديث الصفحة.");
                checkoutBtn.disabled = false;
                checkoutBtn.textContent = "إتمام الحجز";
            }
        } catch (error) {
            console.error("Availability Check Failed:", error);
            alert("حدث خطأ أثناء التحقق من التوافر. يرجى المحاولة مرة أخرى.");
            checkoutBtn.disabled = false;
            checkoutBtn.textContent = "إتمام الحجز";
        }
    }

    get ui() {
        const self = this;
        return {
            _formatTime12Hour(time24) {
                if (!time24) return '';
                const [hours, minutes] = time24.split(':');
                const h = parseInt(hours, 10);
                const period = h >= 12 ? 'مساءً' : 'صباحًا';
                let h12 = h % 12;
                if (h12 === 0) h12 = 12;
                return `${h12.toString().padStart(2, '0')}:${minutes} ${period}`;
            },

            renderTimeSlots() {
                const container = document.getElementById('time-slots-container');
                if (!container) return;
                if (!self.state.appointmentDate) {
                    container.innerHTML = `<p class="placeholder-text">اختر يوماً لعرض المواعيد</p>`;
                    return;
                }
                
                const selectedService = self.services.find(s => s.id === self.state.selectedServiceId);
                if (self.services.filter(s => s.booking_model === 'time_slot').length > 0 && !selectedService) {
                    container.innerHTML = `<p class="placeholder-text">الرجاء اختيار خدمة أولاً لعرض مواعيدها.</p>`;
                    return;
                }
                if (!selectedService || selectedService.booking_model !== 'time_slot') {
                    container.innerHTML = ``;
                    return;
                }

                const selectedDay = self.state.appointmentDate.getDay();
                let schedule;
                try {
                    schedule = selectedService.availability_schedule ? JSON.parse(selectedService.availability_schedule) : [];
                } catch (e) {
                    container.innerHTML = `<p class="placeholder-text" style="color:red;">خطأ ببيانات التوافر.</p>`;
                    return;
                }
                
                const daySchedule = Array.isArray(schedule) ? schedule.find(d => d.day_of_week == selectedDay) : null;
                if (!daySchedule || !daySchedule.start_time || !daySchedule.end_time) {
                    container.innerHTML = `<p class="placeholder-text">لا توجد مواعيد متاحة بهذا اليوم.</p>`; 
                    return;
                }

                const slots = this.generateTimeSlots(daySchedule.start_time, daySchedule.end_time, selectedService.duration_minutes);
                if(slots.length === 0){
                    container.innerHTML = `<p class="placeholder-text">لم يتم العثور على مواعيد متاحة.</p>`; 
                    return;
                }

                container.innerHTML = `<h5>المواعيد المتاحة</h5>`;
                const grid = document.createElement('div');
                grid.className = 'time-slots-grid';

                const dateString = self.state.appointmentDate.toLocaleDateString('en-CA');
                const bookedSlots = self.bookings
                    .filter(b => b.service_id == selectedService.id && b.start_datetime.startsWith(dateString))
                    .map(b => b.start_datetime.slice(11, 16));

                slots.forEach(time => {
                    const slot = document.createElement('div');
                    slot.className = 'time-slot';
                    slot.dataset.time = time;
                    slot.textContent = this._formatTime12Hour(time);
                    if (bookedSlots.includes(time)) {
                        slot.classList.add('disabled');
                        slot.title = 'هذا الموعد محجوز';
                    }
                    if (time === self.state.appointmentTime) {
                        slot.classList.add('selected');
                    }
                    grid.appendChild(slot);
                });
                container.appendChild(grid);
            },
            
            generateTimeSlots(start, end, duration) {
                const slots = [];
                const DURATION = parseInt(duration) || 60;
                let currentTime = new Date(`1970-01-01T${start}`);
                const endTime = new Date(`1970-01-01T${end}`);
                
                if (isNaN(currentTime) || isNaN(endTime) || currentTime >= endTime) {
                     return [];
                }

                while (currentTime < endTime) {
                    slots.push(currentTime.toTimeString().slice(0, 5));
                    currentTime.setMinutes(currentTime.getMinutes() + DURATION);
                }
                return slots;
            },
            
            updateBookingSummary() {
                const container = self.dom.bookingSummaryContainer;
                const button = self.dom.checkoutButton;
                const service = self.services.find(s => s.id === self.state.selectedServiceId);
                let html = '';
                let isValid = false;
                let totalPrice = 0;

                if (service) {
                    if (['hotel', 'restaurant', 'event'].includes(self.business.booking_category) && self.state.checkinDate && self.state.checkoutDate) {
                        const nights = Math.max(1, Math.round((self.state.checkoutDate - self.state.checkinDate) / (1000 * 60 * 60 * 24)));
                        if (nights > 0) {
                            totalPrice = nights * service.price;
                            html = `<div class="summary-item"><span>${service.resource_name} (${nights} ليالي)</span> <strong>${totalPrice.toLocaleString()} ل.س</strong></div>`;
                            isValid = true;
                        }
                    } else if (['clinic', 'consulting', 'tourism'].includes(self.business.booking_category) && self.state.appointmentDate && self.state.appointmentTime) {
                        totalPrice = service.price;
                        html = `<div class="summary-item"><span>${service.name}</span> <strong>${totalPrice.toLocaleString()} ل.س</strong></div>
                                <div class="summary-item"><small>${self.state.appointmentDate.toLocaleDateString('ar-EG-u-nu-latn')} - ${this._formatTime12Hour(self.state.appointmentTime)}</small></div>`;
                        isValid = true;
                    }
                }

                if (isValid) {
                    container.innerHTML = html + `<div class="summary-item summary-total"><span>الإجمالي:</span> <strong>${totalPrice.toLocaleString()} ل.س</strong></div>`;
                    container.style.display = 'block';
                    button.disabled = false;
                    button.textContent = "إتمام الحجز";
                } else {
                    container.style.display = 'none';
                    button.disabled = true;
                    button.textContent = "أكمل اختياراتك للمتابعة";
                }
            },
                        
            initializeMap() {
                const lat = parseFloat(self.business.latitude);
                const lng = parseFloat(self.business.longitude);
                if (!lat || !lng || !self.dom.mapContainer) return;
                
                try {
                    const map = L.map(self.dom.mapContainer).setView([lat, lng], 15);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap'
                    }).addTo(map);
                    L.marker([lat, lng]).addTo(map).bindPopup(`<b>${self.business.name}</b>`).openPopup();
                } catch(e) {
                    console.error("Could not initialize map:", e);
                    self.dom.mapContainer.innerHTML = "لا يمكن عرض الخريطة حالياً.";
                }
            },

            renderInitialBookingUI() {
                let html = '<h4>اختر تفاصيل حجزك</h4>';
                switch (self.business.booking_category) {
                    case 'hotel':
                    case 'restaurant':
                    case 'event':
                    case 'tourism':
                        html += `
                        <div class="form-row">
                            <div class="form-group datetime-picker-group">
                                <label>الوصول</label>
                                <input type="text" id="checkin-date" placeholder="اختر تاريخ ووقت الوصول...">
                                <i class="icon fas fa-calendar-alt"></i>
                            </div>
                            <div class="form-group datetime-picker-group">
                                <label>المغادرة</label>
                                <input type="text" id="checkout-date" placeholder="اختر تاريخ ووقت المغادرة..." disabled>
                                <i class="icon fas fa-calendar-alt"></i>
                            </div>
                        </div>`;
                        break;
                    default:
                        html += `<p class="placeholder-text">نظام الحجز غير مهيأ لهذا النوع من النشاط.</p>`;
                }
                self.dom.bookingInterfaceContainer.innerHTML = html;
                this.initializePickers();
            },
            
            initializePickers() {
                const checkinInput = document.getElementById('checkin-date');
                const checkoutInput = document.getElementById('checkout-date');
                const appointmentInput = document.getElementById('appointment-date');

                if (checkinInput) {
                    const commonOptions = {
                        locale: "ar",
                        enableTime: true, // **تفعيل اختيار الوقت**
                        time_24hr: true,
                        minuteIncrement: 30,
                    };

                    self.pickers.checkout = flatpickr(checkoutInput, {
                        ...commonOptions,
                        defaultDate: "12:00", // وقت المغادرة الافتراضي
                        onChange: (selectedDates) => {
                            self.state.checkoutDate = selectedDates[0];
                            this.filterAvailableServices();
                            this.updateBookingSummary();
                        }
                    });
                    
                    self.pickers.checkin = flatpickr(checkinInput, {
                        ...commonOptions,
                        minDate: "today",
                        defaultDate: new Date().setHours(14, 0, 0, 0), // وقت الوصول الافتراضي 2:00 PM
                        onChange: (selectedDates) => {
                            if (!selectedDates[0]) return;
                            self.state.checkinDate = selectedDates[0];
                            self.state.checkoutDate = null;
                            checkoutInput.disabled = false;
                            self.pickers.checkout.clear();
                            // يجب أن يكون تاريخ المغادرة على الأقل بعد يوم واحد
                            let minCheckoutDate = new Date(self.state.checkinDate);
                            minCheckoutDate.setDate(minCheckoutDate.getDate() + 1);
                            minCheckoutDate.setHours(12, 0, 0, 0); // ضبط الوقت الافتراضي للمغادرة
                            self.pickers.checkout.set('minDate', minCheckoutDate);
                            this.filterAvailableServices();
                            this.updateBookingSummary();
                        }
                    });
                }

                if (appointmentInput) {
                    self.pickers.appointment = flatpickr(appointmentInput, {
                        locale: "ar", minDate: "today",
                        onChange: (selectedDates) => {
                            self.state.appointmentDate = selectedDates[0];
                            self.state.appointmentTime = null;
                            this.renderTimeSlots();
                            this.updateBookingSummary();
                        }
                    });
                }
            },
            
            renderServicesList() {
                self.dom.servicesContainer.innerHTML = '';
                if (!self.services || self.services.length === 0) {
                    self.dom.servicesContainer.innerHTML = `<p class="placeholder-text">لا توجد خيارات متاحة للحجز حاليًا.</p>`;
                    return;
                }

                const category = self.business.booking_category;

                self.services.forEach(service => {
                    const card = document.createElement('div');
                    card.className = 'service-card';
                    card.dataset.serviceId = service.id;

                    if (['hotel', 'restaurant', 'event'].includes(category)) {
                        const meta = service.meta_data ? JSON.parse(service.meta_data) : {};
                        let detailsHtml = '';
                        if (category === 'hotel') {
                            detailsHtml = `<div class="meta-item"><i class="fas fa-users"></i> ${meta.capacity_adults || '-'} بالغين, ${meta.capacity_children !== undefined ? meta.capacity_children : '-'} أطفال</div>`;
                            if (meta.view) detailsHtml += `<div class="meta-item"><i class="fas fa-eye"></i> ${meta.view}</div>`;
                            if (meta.floor) detailsHtml += `<div class="meta-item"><i class="fas fa-building"></i> الطابق: ${meta.floor}</div>`;
                        } else if (category === 'restaurant') {
                            detailsHtml = `<div class="meta-item"><i class="fas fa-users"></i> ${meta.capacity || meta.capacity_adults || '-'} أشخاص</div>`;
                            if (meta.location) detailsHtml += `<div class="meta-item"><i class="fas fa-map-marker-alt"></i> ${meta.location}</div>`;
                        }

                        card.innerHTML = `
                            <div class="service-card-header">
                                <h4>${service.resource_name}</h4>
                                <div class="price">${parseInt(service.price).toLocaleString()} <small>ل.س / ${service.price_type === 'per_night' ? 'الليلة' : 'الحجز'}</small></div>
                            </div>
                            <div class="service-meta-details">${detailsHtml}</div>
                            <p class="service-description-small">${service.description || ''}</p>
                            <div class="service-card-actions">
                                <button class="btn select-service-btn" data-service-id="${service.id}">اختر</button>
                            </div>
                        `;
                    } else {
                        card.innerHTML = `
                            <div class="service-card-header">
                                <h4>${service.name}</h4>
                                <div class="price">${parseInt(service.price).toLocaleString()} <small>ل.س / ${service.price_type === 'per_night' ? 'الليلة' : 'الجلسة'}</small></div>
                            </div>
                            <p class="service-card-details">${service.description || 'لا يوجد وصف متاح.'}</p>
                            <div class="service-card-actions">
                                <button class="btn select-service-btn" data-service-id="${service.id}">اختر</button>
                            </div>
                        `;
                    }
                    self.dom.servicesContainer.appendChild(card);
                });
            },

            filterAvailableServices() {
                if (!self.state.checkinDate || !self.state.checkoutDate) return;

                const checkin = self.state.checkinDate;
                const checkout = self.state.checkoutDate;

                self.services.forEach(service => {
                    const card = self.dom.servicesContainer.querySelector(`.service-card[data-service-id="${service.id}"]`);
                    if (!card) return;

                    const resourceId = service.resource_id_fk;
                    if (!resourceId) return;

                    const isBooked = self.bookings.some(booking => {
                        if (booking.resource_id != resourceId) return false;
                        const bookingStart = new Date(booking.start_datetime);
                        const bookingEnd = new Date(booking.end_datetime);
                        return checkin < bookingEnd && checkout > bookingStart;
                    });
                    
                    card.classList.toggle('disabled', isBooked);
                    if(isBooked && self.state.selectedServiceId == service.id){
                        self.state.selectedServiceId = null;
                        this.updateSelectedServiceCard();
                    }
                });
            },

            updateSelectedServiceCard() {
                document.querySelectorAll('.service-card').forEach(card => {
                    const isSelected = parseInt(card.dataset.serviceId) === self.state.selectedServiceId;
                    card.classList.toggle('selected', isSelected);
                    const btn = card.querySelector('.select-service-btn');
                    if (btn) btn.innerHTML = isSelected ? '<i class="fas fa-check"></i> تم الاختيار' : 'اختر';
                });
            }
        };
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof BUSINESS_DATA !== 'undefined' && typeof SERVICES_DATA !== 'undefined' && typeof EXISTING_BOOKINGS !== 'undefined') {
        new ServiceProfileApp(BUSINESS_DATA, SERVICES_DATA, EXISTING_BOOKINGS);
    } else {
        const container = document.querySelector('.profile-layout') || document.body;
        container.innerHTML = '<p class="placeholder-text" style="color:red; margin: 2rem;">خطأ حرج: فشل تحميل البيانات الأساسية للصفحة.</p>';
    }
});