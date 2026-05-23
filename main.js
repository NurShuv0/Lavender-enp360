/**
 * LAVENDER’S GLAM STUDIO — Luxury Makeup Artist Booking & Portfolio Website
 * Core Frontend Interactive Logic & State Management
 */

// 1. Service Catalog Definition
const SERVICES_CATALOG = [
    {
        id: "bridal-couture",
        name: "Bridal Editorial Couture",
        tag: "Bridal Signature",
        desc: "The ultimate luxury bridal look designed for modern couture brides. Includes pre-wedding consult, premium 3D silk lashes, absolute high-definition airbrushing, skin-prep detailing, and a bespoke long-lasting glow formula.",
        basePrice: 15000,
        duration: "3 Hours",
        image: "https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=600&auto=format&fit=crop",
        features: ["Bespoke Skin-prep", "HD Airbrush finish", "Premium 3D Silk Lashes", "Veil & Jewelry placement assistance"]
    },
    {
        id: "red-carpet-glam",
        name: "Celebrity & Red Carpet Glam",
        tag: "Signature Glam",
        desc: "Camera-ready, flawless red carpet look optimized for photographic studio flash and film. Features custom eye artistry, facial contour sculpting, high-end radiant finish, and custom lip designs using ultra-premium couture cosmetics.",
        basePrice: 10000,
        duration: "2 Hours",
        image: "https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?q=80&w=600&auto=format&fit=crop",
        features: ["Premium Contour Sculpting", "Waterproof Eye Artistry", "Lash clusters", "Bespoke lip combo"]
    },
    {
        id: "fashion-editorial",
        name: "Fashion Editorial & Runway",
        tag: "Editorial",
        desc: "High-concept, trendsetting makeup designs for fashion publications, runways, and conceptual shoots. Adapts from clean high-fashion skin glow to avant-garde pigment sprays according to director guidelines.",
        basePrice: 12000,
        duration: "2.5 Hours",
        image: "https://images.unsplash.com/photo-1512496015851-a90fb38ba796?q=80&w=600&auto=format&fit=crop",
        features: ["High-Concept Styling", "Ultra-thin glow foundations", "Avant-garde coloring", "Director-aligned look adapting"]
    },
    {
        id: "luxury-evening",
        name: "Luxury Evening Soirée",
        tag: "Evening Glam",
        desc: "Elegant and sophisticated evening glam perfect for formal galas, private dinners, and luxury parties. Combines classy soft smokey eyes with clean skin and premium lashes to make you stand out beautifully.",
        basePrice: 8000,
        duration: "1.5 Hours",
        image: "https://images.unsplash.com/photo-1526047932273-341f2a7631f9?q=80&w=600&auto=format&fit=crop",
        features: ["Soft smokey eyes", "Premium foundation mapping", "Silk lashes", "12-hour lock spray"]
    },
    {
        id: "one-on-one-masterclass",
        name: "1-on-1 Personal Glam Masterclass",
        tag: "Private Education",
        desc: "Bespoke private education session where you sit 1-on-1 with the master artist. Master correct facial mappings, personalized color theories, custom eyebrow structures, and transitioning from clean day skin to evening party glow.",
        basePrice: 18000,
        duration: "4 Hours",
        image: "https://images.unsplash.com/photo-1596462502278-27bfdc403348?q=80&w=600&auto=format&fit=crop",
        features: ["Personalized facial mapping", "Cosmetics kit analysis", "Hands-on application practice", "Take-home face-chart guide"]
    }
];

// 2. Global State Object
window.BookingApp = {
    bookings: [],
    currentYear: new Date().getFullYear(),
    currentMonth: new Date().getMonth(), // 0-indexed (Jan = 0)
    selectedDate: null,
    selectedTimeSlot: null,
    selectedLocation: "studio", // "studio" or "location"
    travelFee: 2000,
    activeService: null, // Service object currently selected in modal
    
    // Core Functions
    init() {
        this.loadBookingsFromStorage();
        this.bindEvents();
        this.renderServices();
        this.updateBadgeCount();
        this.renderBookingAgenda();
    },

    loadBookingsFromStorage() {
        const stored = localStorage.getItem("lavender_bookings");
        if (stored) {
            try {
                this.bookings = JSON.parse(stored);
            } catch (e) {
                this.bookings = [];
            }
        }
    },

    saveBookingsToStorage() {
        localStorage.setItem("lavender_bookings", JSON.stringify(this.bookings));
    },

    bindEvents() {
        // Sliding Drawer Open/Close
        document.getElementById("agenda-trigger-btn").addEventListener("click", () => this.openDrawer());
        document.getElementById("drawer-close-btn").addEventListener("click", () => this.closeDrawer());
        document.getElementById("drawer-overlay").addEventListener("click", () => this.closeDrawer());
        document.getElementById("drawer-continue-shopping").addEventListener("click", () => this.closeDrawer());
        
        // Checkout Section Show/Hide
        document.getElementById("proceed-checkout-btn").addEventListener("click", () => {
            this.closeDrawer();
            this.showCheckoutSection();
        });
        
        // Modals Close
        document.querySelectorAll(".modal-close-btn").forEach(btn => {
            btn.addEventListener("click", (e) => {
                const modal = e.target.closest(".modal-overlay");
                if (modal) modal.classList.remove("active");
            });
        });

        // FAQ accordion toggle
        document.querySelectorAll(".faq-trigger").forEach(btn => {
            btn.addEventListener("click", (e) => {
                const item = e.target.closest(".faq-item");
                const content = item.querySelector(".faq-content");
                const isActive = item.classList.contains("active");
                
                // Close other items
                document.querySelectorAll(".faq-item").forEach(other => {
                    other.classList.remove("active");
                    other.querySelector(".faq-content").style.maxHeight = null;
                });
                
                if (!isActive) {
                    item.classList.add("active");
                    content.style.maxHeight = content.scrollHeight + "px";
                }
            });
        });

        // Portfolio filtering
        document.querySelectorAll(".filter-btn").forEach(btn => {
            btn.addEventListener("click", (e) => {
                const filter = e.target.getAttribute("data-filter");
                
                document.querySelectorAll(".filter-btn").forEach(b => b.classList.remove("active"));
                e.target.classList.add("active");
                
                document.querySelectorAll(".portfolio-item").forEach(item => {
                    if (filter === "all" || item.getAttribute("data-cat") === filter) {
                        item.style.display = "block";
                    } else {
                        item.style.display = "none";
                    }
                });
            });
        });

        // Location form check logic: toggle event address field dynamically in checkout
        document.getElementById("checkout-form").addEventListener("submit", (e) => {
            e.preventDefault();
            this.submitInquiry();
        });
    },

    // 3. Render Services Catalog
    renderServices() {
        const grid = document.getElementById("services-grid");
        grid.innerHTML = "";

        SERVICES_CATALOG.forEach(service => {
            const card = document.createElement("div");
            card.className = "service-card";
            card.innerHTML = `
                <div class="service-media">
                    <img src="${service.image}" alt="${service.name}">
                    <span class="service-tag">${service.tag}</span>
                </div>
                <div class="service-body">
                    <h3 class="service-name">${service.name}</h3>
                    <p class="service-desc">${service.desc.substring(0, 130)}...</p>
                    <div class="service-meta">
                        <div class="meta-item">
                            <p>Duration</p>
                            <h5>${service.duration}</h5>
                        </div>
                        <div class="meta-item">
                            <p>Base Investment</p>
                            <h5 class="price-amt">${service.basePrice.toLocaleString()} BDT</h5>
                        </div>
                    </div>
                    <button class="service-card-btn" onclick="BookingApp.openServiceModal('${service.id}')">Book & Customize</button>
                </div>
            `;
            grid.appendChild(card);
        });
    },

    // 4. Open Custom Booking Modal
    openServiceModal(serviceId) {
        const service = SERVICES_CATALOG.find(s => s.id === serviceId);
        if (!service) return;

        this.activeService = service;
        this.selectedDate = null;
        this.selectedTimeSlot = null;
        this.selectedLocation = "studio";

        // Fill modal details
        document.getElementById("modal-service-name").textContent = service.name;
        document.getElementById("modal-service-tag").textContent = service.tag;
        document.getElementById("modal-service-desc").textContent = service.desc;
        document.getElementById("modal-service-duration").textContent = service.duration;
        document.getElementById("modal-service-price").textContent = `${service.basePrice.toLocaleString()} BDT`;
        document.getElementById("modal-service-image").src = service.image;

        // Reset month tracker to current month
        this.currentYear = new Date().getFullYear();
        this.currentMonth = new Date().getMonth();

        // Render Widget elements
        this.renderCalendar();
        this.renderTimeSlots();
        this.updateModalPricing();

        // Show Modal
        document.getElementById("booking-modal").classList.add("active");
    },

    // 5. Calendar Generation Grid
    renderCalendar() {
        const container = document.getElementById("calendar-grid-container");
        container.innerHTML = "";

        const monthNames = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        // Update calendar header text
        document.getElementById("calendar-month-name").textContent = `${monthNames[this.currentMonth]} ${this.currentYear}`;

        // Add weekdays headers
        const weekdays = ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"];
        weekdays.forEach(day => {
            const el = document.createElement("div");
            el.className = "calendar-weekday";
            el.textContent = day;
            container.appendChild(el);
        });

        // Determine date logic
        const firstDayIndex = new Date(this.currentYear, this.currentMonth, 1).getDay();
        const lastDay = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        
        // Today details for disabling past dates
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        // Fill empty starting grid days
        for (let i = 0; i < firstDayIndex; i++) {
            const emptyEl = document.createElement("div");
            emptyEl.className = "calendar-day empty";
            container.appendChild(emptyEl);
        }

        // Render month days
        for (let day = 1; day <= lastDay; day++) {
            const dayButton = document.createElement("button");
            dayButton.className = "calendar-day";
            dayButton.textContent = day;
            dayButton.type = "button";

            const cellDate = new Date(this.currentYear, this.currentMonth, day);
            cellDate.setHours(0, 0, 0, 0);

            // Disable past dates
            if (cellDate < today) {
                dayButton.classList.add("disabled");
                dayButton.disabled = true;
            } else {
                // If this cell matches the selected date state, highlight it
                if (this.selectedDate && 
                    this.selectedDate.getFullYear() === this.currentYear &&
                    this.selectedDate.getMonth() === this.currentMonth &&
                    this.selectedDate.getDate() === day) {
                    dayButton.classList.add("selected");
                }

                dayButton.addEventListener("click", () => {
                    this.selectedDate = new Date(this.currentYear, this.currentMonth, day);
                    
                    // Toggle selections visual class
                    document.querySelectorAll(".calendar-day").forEach(btn => btn.classList.remove("selected"));
                    dayButton.classList.add("selected");
                    
                    this.showToast(`Selected date: ${this.formatDatePretty(this.selectedDate)}`, "success");
                });
            }
            container.appendChild(dayButton);
        }

        // Disable "Previous Month" navigation if looking at current month
        const prevBtn = document.getElementById("calendar-prev-btn");
        const currentMonthToday = new Date().getMonth();
        const currentYearToday = new Date().getFullYear();
        
        if (this.currentYear === currentYearToday && this.currentMonth === currentMonthToday) {
            prevBtn.disabled = true;
        } else {
            prevBtn.disabled = false;
        }
    },

    changeMonth(direction) {
        this.currentMonth += direction;
        if (this.currentMonth < 0) {
            this.currentMonth = 11;
            this.currentYear -= 1;
        } else if (this.currentMonth > 11) {
            this.currentMonth = 0;
            this.currentYear += 1;
        }
        this.renderCalendar();
    },

    // 6. Time Slot Rendering
    renderTimeSlots() {
        const slotsGrid = document.getElementById("slots-grid-container");
        slotsGrid.innerHTML = "";

        const availableSlots = [
            "08:30 AM", "11:00 AM", "01:30 PM", "04:00 PM", "06:30 PM", "09:00 PM"
        ];

        availableSlots.forEach(slot => {
            const btn = document.createElement("button");
            btn.className = "slot-chip";
            btn.type = "button";
            btn.textContent = slot;

            if (this.selectedTimeSlot === slot) {
                btn.classList.add("active");
            }

            btn.addEventListener("click", () => {
                this.selectedTimeSlot = slot;
                
                // Toggle active visual class
                document.querySelectorAll(".slot-chip").forEach(c => c.classList.remove("active"));
                btn.classList.add("active");
                
                this.showToast(`Time slot selected: ${slot}`, "success");
            });

            slotsGrid.appendChild(btn);
        });
    },

    // 7. Travel Location Fee Toggles
    selectLocation(type) {
        this.selectedLocation = type;
        
        document.querySelectorAll(".location-card").forEach(card => card.classList.remove("active"));
        document.getElementById(`loc-${type}`).classList.add("active");

        this.updateModalPricing();
        this.showToast(`Location set to: ${type === "studio" ? "Studio Atelier" : "On-Location Guest Service"}`, "success");
    },

    updateModalPricing() {
        if (!this.activeService) return;

        let total = this.activeService.basePrice;
        const feeContainer = document.getElementById("widget-fee-row");
        const widgetTotal = document.getElementById("widget-total-amt");

        if (this.selectedLocation === "location") {
            total += this.travelFee;
            feeContainer.style.display = "flex";
            document.getElementById("widget-travel-fee").textContent = `+${this.travelFee.toLocaleString()} BDT`;
        } else {
            feeContainer.style.display = "none";
        }

        widgetTotal.textContent = `${total.toLocaleString()} BDT`;
    },

    // 8. Add Bookings to local state object
    addActiveBooking() {
        if (!this.activeService) return;

        // Validation
        if (!this.selectedDate) {
            this.showToast("Please choose an appointment date first.", "error");
            return;
        }
        if (!this.selectedTimeSlot) {
            this.showToast("Please select a valid time slot.", "error");
            return;
        }

        const price = this.activeService.basePrice + (this.selectedLocation === "location" ? this.travelFee : 0);
        
        const bookingItem = {
            id: Date.now().toString(36) + Math.random().toString(36).substr(2, 5),
            serviceId: this.activeService.id,
            serviceName: this.activeService.name,
            serviceTag: this.activeService.tag,
            price: price,
            basePrice: this.activeService.basePrice,
            date: this.selectedDate.toISOString(),
            timeSlot: this.selectedTimeSlot,
            location: this.selectedLocation,
            duration: this.activeService.duration
        };

        this.bookings.push(bookingItem);
        this.saveBookingsToStorage();
        
        // Sync & Update UI
        this.updateBadgeCount();
        this.renderBookingAgenda();
        
        // Close modal and notify
        document.getElementById("booking-modal").classList.remove("active");
        this.showToast("Appointment successfully reserved in Agenda!", "success");

        // Slide open the booking drawer immediately
        setTimeout(() => this.openDrawer(), 500);
    },

    removeBooking(id) {
        this.bookings = this.bookings.filter(item => item.id !== id);
        this.saveBookingsToStorage();
        
        this.updateBadgeCount();
        this.renderBookingAgenda();
        this.showToast("Reservation removed.", "success");

        // Sync with checkout review panels if active
        if (document.getElementById("checkout-section").classList.contains("active")) {
            this.renderCheckoutSummary();
        }
    },

    // 9. Sliding Drawer Syncing & Rendering
    openDrawer() {
        document.getElementById("drawer-overlay").classList.add("active");
        document.getElementById("agenda-drawer").classList.add("active");
    },

    closeDrawer() {
        document.getElementById("drawer-overlay").classList.remove("active");
        document.getElementById("agenda-drawer").classList.remove("active");
    },

    updateBadgeCount() {
        const badges = document.querySelectorAll(".agenda-badge");
        badges.forEach(b => {
            b.textContent = this.bookings.length;
            b.style.display = this.bookings.length > 0 ? "flex" : "none";
        });
    },

    renderBookingAgenda() {
        const container = document.getElementById("agenda-items-container");
        container.innerHTML = "";

        const footerPricing = document.getElementById("drawer-footer-pricing");
        const actionBtn = document.getElementById("proceed-checkout-btn");

        if (this.bookings.length === 0) {
            container.innerHTML = `
                <div class="drawer-empty-state">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <h4>Your Booking Agenda is Empty</h4>
                    <p>Select from our luxurious signature packages and schedule your elite session.</p>
                </div>
            `;
            footerPricing.style.display = "none";
            actionBtn.disabled = true;
            actionBtn.style.opacity = "0.5";
            actionBtn.style.cursor = "not-allowed";
            return;
        }

        footerPricing.style.display = "block";
        actionBtn.disabled = false;
        actionBtn.style.opacity = "1";
        actionBtn.style.cursor = "pointer";

        let subtotal = 0;
        let totalTravelFees = 0;

        this.bookings.forEach(item => {
            subtotal += item.basePrice;
            const isLocation = item.location === "location";
            if (isLocation) {
                totalTravelFees += this.travelFee;
            }

            const itemEl = document.createElement("div");
            itemEl.className = "agenda-item";
            itemEl.innerHTML = `
                <div class="agenda-item-body">
                    <h4 class="agenda-item-name">${item.serviceName}</h4>
                    <div class="agenda-item-detail">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <span>${item.timeSlot} — ${this.formatDatePretty(new Date(item.date))}</span>
                    </div>
                    <div class="agenda-item-detail">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <span>${isLocation ? "On-Location Guest Service" : "Studio Atelier (In-Studio)"}</span>
                    </div>
                    <h5 class="agenda-item-price">${item.price.toLocaleString()} BDT</h5>
                </div>
                <button class="agenda-item-remove" onclick="BookingApp.removeBooking('${item.id}')" title="Remove Session">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            `;
            container.appendChild(itemEl);
        });

        const grandTotal = subtotal + totalTravelFees;

        document.getElementById("drawer-subtotal").textContent = `${subtotal.toLocaleString()} BDT`;
        document.getElementById("drawer-travel").textContent = totalTravelFees > 0 ? `+${totalTravelFees.toLocaleString()} BDT` : "0 BDT";
        document.getElementById("drawer-total").textContent = `${grandTotal.toLocaleString()} BDT`;
    },

    // 10. Checkout Inquiry Transitions & Submissions
    showCheckoutSection() {
        if (this.bookings.length === 0) {
            this.showToast("Your booking agenda is empty.", "error");
            return;
        }

        const section = document.getElementById("checkout-section");
        section.classList.add("active");
        
        // Sync fields
        const hasLocation = this.bookings.some(b => b.location === "location");
        const addressGroup = document.getElementById("field-address-group");
        const addressInput = document.getElementById("client-address");
        
        if (hasLocation) {
            addressGroup.style.display = "flex";
            addressInput.required = true;
            this.showToast("On-location session selected. Event address is required.", "success");
        } else {
            addressGroup.style.display = "none";
            addressInput.required = false;
        }

        this.renderCheckoutSummary();

        // Scroll to checkout smoothly
        setTimeout(() => {
            section.scrollIntoView({ behavior: "smooth" });
        }, 100);
    },

    renderCheckoutSummary() {
        const summaryList = document.getElementById("checkout-summary-list");
        summaryList.innerHTML = "";

        if (this.bookings.length === 0) {
            document.getElementById("checkout-section").classList.remove("active");
            return;
        }

        let subtotal = 0;
        let totalTravel = 0;

        this.bookings.forEach(item => {
            subtotal += item.basePrice;
            const isLocation = item.location === "location";
            if (isLocation) totalTravel += this.travelFee;

            const el = document.createElement("div");
            el.className = "summary-item";
            el.innerHTML = `
                <div class="summary-item-info">
                    <h6>${item.serviceName}</h6>
                    <p>
                        <span>📅 ${this.formatDatePretty(new Date(item.date))} @ ${item.timeSlot}</span>
                        <span>•</span>
                        <span>📍 ${isLocation ? "On-Location" : "In-Studio"}</span>
                    </p>
                </div>
                <div class="summary-item-price">${item.price.toLocaleString()} BDT</div>
            `;
            summaryList.appendChild(el);
        });

        const total = subtotal + totalTravel;

        document.getElementById("checkout-subtotal").textContent = `${subtotal.toLocaleString()} BDT`;
        document.getElementById("checkout-travel").textContent = totalTravel > 0 ? `+${totalTravel.toLocaleString()} BDT` : "0 BDT";
        document.getElementById("checkout-total").textContent = `${total.toLocaleString()} BDT`;
    },

    submitInquiry() {
        const fullName = document.getElementById("client-name").value.trim();
        const email = document.getElementById("client-email").value.trim();
        const whatsapp = document.getElementById("client-phone").value.trim();
        const address = document.getElementById("client-address").value.trim();
        const skinType = document.getElementById("client-skin").value;
        const preference = document.getElementById("client-preferences").value.trim();
        
        // Compile receipt rows for receipt modal
        const receiptCard = document.getElementById("receipt-detail-card");
        receiptCard.innerHTML = "";

        let subtotal = 0;
        let travelTotal = 0;
        let whatsappContent = `🌸 *LAVENDER’S GLAM STUDIO — Bespoke Luxury Makeup Booking* 🌸\n\n`;
        whatsappContent += `*Client Details:*\n`;
        whatsappContent += `• *Name:* ${fullName}\n`;
        whatsappContent += `• *WhatsApp/Phone:* ${whatsapp}\n`;
        whatsappContent += `• *Email:* ${email}\n`;
        if (skinType) whatsappContent += `• *Skin Type:* ${skinType}\n`;
        if (preference) whatsappContent += `• *Makeup Preferences:* ${preference}\n`;
        if (address) whatsappContent += `• *Event Address:* ${address}\n`;
        
        whatsappContent += `\n*Reserved Sessions:*\n`;

        this.bookings.forEach((item, index) => {
            subtotal += item.basePrice;
            const isLocation = item.location === "location";
            if (isLocation) travelTotal += this.travelFee;

            const prettyDate = this.formatDatePretty(new Date(item.date));
            const locationStr = isLocation ? `On-Location (Event)` : `Studio Atelier (In-Studio)`;

            whatsappContent += `${index + 1}. *${item.serviceName}*\n`;
            whatsappContent += `   • *Schedule:* ${prettyDate} @ ${item.timeSlot}\n`;
            whatsappContent += `   • *Location:* ${locationStr}\n`;
            whatsappContent += `   • *Investment:* ${item.price.toLocaleString()} BDT\n\n`;

            // Receipt Modal rendering
            const line = document.createElement("div");
            line.className = "receipt-line";
            line.innerHTML = `
                <span>${item.serviceName} (${item.timeSlot})</span>
                <span class="receipt-price">${item.price.toLocaleString()} BDT</span>
            `;
            receiptCard.appendChild(line);
        });

        const grandTotal = subtotal + travelTotal;

        whatsappContent += `*Financial Breakdown:*\n`;
        whatsappContent += `• *Studio Services Subtotal:* ${subtotal.toLocaleString()} BDT\n`;
        if (travelTotal > 0) whatsappContent += `• *On-Location Travel Surcharge:* ${travelTotal.toLocaleString()} BDT\n`;
        whatsappContent += `• *Total Investment:* *${grandTotal.toLocaleString()} BDT*\n\n`;
        whatsappContent += `🌸 Please confirm my slot reservations. Thank you!`;

        // Add pricing totals to Receipt Modal
        if (travelTotal > 0) {
            const travelLine = document.createElement("div");
            travelLine.className = "receipt-line";
            travelLine.innerHTML = `
                <span>Travel/Location Surcharge</span>
                <span class="receipt-price">+${travelTotal.toLocaleString()} BDT</span>
            `;
            receiptCard.appendChild(travelLine);
        }

        const totalLine = document.createElement("div");
        totalLine.className = "receipt-line";
        totalLine.innerHTML = `
            <span>Bespoke Total Investment</span>
            <span class="receipt-price">${grandTotal.toLocaleString()} BDT</span>
        `;
        receiptCard.appendChild(totalLine);

        // Bind redirect action buttons
        const waNumber = "+8801974424264"; // Premium Studio WhatsApp Contact
        const waUrl = `https://api.whatsapp.com/send?phone=${encodeURIComponent(waNumber)}&text=${encodeURIComponent(whatsappContent)}`;
        document.getElementById("btn-submit-wa").onclick = () => {
            window.open(waUrl, "_blank");
        };

        // Mailto fallbacks
        const mailtoUrl = `mailto:appointments@lavendersglam.com?subject=Elite Makeup Artist Booking Inquiry&body=${encodeURIComponent(whatsappContent.replace(/\*/g, ""))}`;
        document.getElementById("btn-submit-mail").onclick = () => {
            window.location.href = mailtoUrl;
        };

        // Clear local storage and state upon booking success
        this.bookings = [];
        this.saveBookingsToStorage();
        this.updateBadgeCount();
        this.renderBookingAgenda();

        // Clear form
        document.getElementById("checkout-form").reset();

        // Close details modal if open
        document.getElementById("booking-modal").classList.remove("active");

        // Trigger Receipt Modal Overlay
        document.getElementById("receipt-modal").classList.add("active");
        this.showToast("Inquiry compiled! Open receipt and select submission.", "success");
    },

    // 11. Helper Systems (Pretty Dates & Custom Toasts)
    formatDatePretty(dateObj) {
        const days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        
        const dName = days[dateObj.getDay()];
        const mName = months[dateObj.getMonth()];
        const date = dateObj.getDate();
        const year = dateObj.getFullYear();
        
        return `${dName}, ${mName} ${date}, ${year}`;
    },

    showToast(message, type = "success") {
        const container = document.getElementById("toast-container");
        const toast = document.createElement("div");
        toast.className = `toast toast-${type}`;
        
        const svgCode = type === "success" 
            ? `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`
            : `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`;

        toast.innerHTML = `
            ${svgCode}
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        
        // Auto remove toast after 3 seconds
        setTimeout(() => {
            toast.classList.add("removing");
            toast.addEventListener("animationend", () => toast.remove());
        }, 3000);
    }
};

// Start application on DOM Ready
document.addEventListener("DOMContentLoaded", () => {
    window.BookingApp.init();
});

