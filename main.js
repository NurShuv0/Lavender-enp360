/**
 * LAVENDER’S GLAM STUDIO — Luxury Makeup Artist Booking & Portfolio Website
 * Core Frontend Interactive Logic & State Management (Asynchronous DB-Integrated)
 */

// 1. Static Service Catalog Fallback (kept for offline/API failure resilience)
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

// 2. Global State Object & Controller
window.BookingApp = {
    bookings: [], // Synced directly from PHP session agenda
    servicesCatalog: [], // Database-driven dynamic service packages
    currentYear: new Date().getFullYear(),
    currentMonth: new Date().getMonth(), // 0-indexed (Jan = 0)
    selectedDate: null,
    selectedTimeSlot: null,
    selectedLocation: "studio", // "studio" or "location"
    travelFee: 2000,
    activeService: null, // Service object currently selected in modal
    
    // Core Functions
    async init() {
        this.bindEvents();
        await this.loadServices();
        await this.fetchAgenda();
    },

    // Kept as no-ops for backward compatibility and clean execution
    loadBookingsFromStorage() {},
    saveBookingsToStorage() {},

    // Fetch dynamic catalog from DB endpoint
    async loadServices() {
        try {
            const res = await fetch('index.php?action=get-services');
            if (!res.ok) throw new Error("Catalog fetch failed");
            const data = await res.json();
            if (data && Array.isArray(data)) {
                this.servicesCatalog = data.map(item => ({
                    id: item.id,
                    name: item.title,
                    tag: item.tag,
                    desc: item.description,
                    basePrice: parseFloat(item.base_price),
                    duration: item.duration_minutes + " Minutes",
                    image: item.image_path
                }));
                this.renderServices();
                return;
            }
        } catch (e) {
            console.warn("Could not load dynamic database services, using static catalog fallback:", e);
        }
        
        // Fallback to static catalog if API is unavailable
        this.servicesCatalog = SERVICES_CATALOG.map(s => ({
            id: s.id,
            name: s.name,
            tag: s.tag,
            desc: s.desc,
            basePrice: s.basePrice,
            duration: s.duration,
            image: s.image
        }));
        this.renderServices();
    },

    // Fetch PHP session agenda and synchronize front-end state
    async fetchAgenda() {
        try {
            const res = await fetch('index.php?action=get-agenda');
            const data = await res.json();
            this.bookings = data.items || [];
            
            this.updateBadgeCount();
            this.renderBookingAgenda();
            
            // Sync pricing parameters
            document.getElementById("drawer-subtotal").textContent = `${data.subtotal.toLocaleString()} BDT`;
            document.getElementById("drawer-travel").textContent = data.travel > 0 ? `+${data.travel.toLocaleString()} BDT` : "0 BDT";
            document.getElementById("drawer-total").textContent = `${data.total.toLocaleString()} BDT`;
            
            if (document.getElementById("checkout-section").classList.contains("active")) {
                this.renderCheckoutSummary();
            }
        } catch (e) {
            console.error("Failed to fetch session agenda:", e);
        }
    },

    bindEvents() {
        // Sliding Drawer Open/Close
        document.getElementById("agenda-trigger-btn").addEventListener("click", () => this.openDrawer());
        document.getElementById("drawer-close-btn").addEventListener("click", () => this.closeDrawer());
        document.getElementById("drawer-overlay").addEventListener("click", () => this.closeDrawer());
        document.getElementById("drawer-continue-shopping").addEventListener("click", () => this.closeDrawer());
        
        // Mobile Menu Drawer Open/Close
        document.getElementById("mobile-menu-trigger").addEventListener("click", () => this.openMobileMenu());
        document.getElementById("mobile-nav-close-btn").addEventListener("click", () => this.closeDrawer());
        document.querySelectorAll(".mobile-nav-link").forEach(link => {
            link.addEventListener("click", () => this.closeDrawer());
        });
        
        // Checkout Section Show/Hide
        document.getElementById("proceed-checkout-btn").addEventListener("click", () => {
            this.closeDrawer();
            this.showCheckoutSection();
        });
        
        // Modals Close
        document.querySelectorAll(".modal-close-btn").forEach(btn => {
            btn.addEventListener("click", (e) => {
                const modal = e.target.closest(".modal-overlay") || e.target.closest(".admin-modal-overlay") || e.target.closest(".receipt-wrapper")?.closest(".modal-overlay");
                if (modal) modal.classList.remove("active");
                // Receipt close fallback
                const recModal = document.getElementById("receipt-modal");
                if (recModal && e.target.closest("#receipt-modal")) {
                    recModal.classList.remove("active");
                }
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

        // Checkout inquiry submission handler
        document.getElementById("checkout-form").addEventListener("submit", (e) => {
            e.preventDefault();
            this.submitInquiry();
        });
    },

    // Render Services Catalog Grid
    renderServices() {
        const grid = document.getElementById("services-grid");
        if (!grid) return;
        grid.innerHTML = "";

        this.servicesCatalog.forEach(service => {
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

    // Open Custom Booking Modal & Fetch Busy Agenda Slots
    openServiceModal(serviceId) {
        const service = this.servicesCatalog.find(s => s.id === serviceId);
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
        this.renderTimeSlots([]); // Initially show clean slots grid
        this.updateModalPricing();

        // Show Modal
        document.getElementById("booking-modal").classList.add("active");
    },

    // Fetch Booked Slots Availability dynamically from PHP API
    async fetchTimeSlotAvailability(date) {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;

        try {
            const res = await fetch(`index.php?action=check-availability&date=${dateStr}`);
            const busySlots = await res.json();
            this.renderTimeSlots(busySlots);
        } catch (e) {
            console.error("Failed to load time slot availability:", e);
            this.renderTimeSlots([]);
        }
    },

    // Calendar Generation Grid
    renderCalendar() {
        const container = document.getElementById("calendar-grid-container");
        if (!container) return;
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
                // Highlight selected cell
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
                    
                    // Query busy slots from PHP PDO backend
                    this.fetchTimeSlotAvailability(this.selectedDate);
                });
            }
            container.appendChild(dayButton);
        }

        // Disable "Previous Month" navigation if looking at current month
        const prevBtn = document.getElementById("calendar-prev-btn");
        const currentMonthToday = new Date().getMonth();
        const currentYearToday = new Date().getFullYear();
        
        if (prevBtn) {
            if (this.currentYear === currentYearToday && this.currentMonth === currentMonthToday) {
                prevBtn.disabled = true;
            } else {
                prevBtn.disabled = false;
            }
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

    // Render Time Slots & Check against occupied slots returned by database
    renderTimeSlots(busySlots = []) {
        const slotsGrid = document.getElementById("slots-grid-container");
        if (!slotsGrid) return;
        slotsGrid.innerHTML = "";

        const availableSlots = [
            "08:30 AM", "11:00 AM", "01:30 PM", "04:00 PM", "06:30 PM", "09:00 PM"
        ];

        availableSlots.forEach(slot => {
            const btn = document.createElement("button");
            btn.className = "slot-chip";
            btn.type = "button";
            btn.textContent = slot;

            // Match slot ignoring leading zeros (e.g. "08:30 AM" -> "8:30 AM")
            const normalizedSlot = slot.replace(/^0/, '');
            const isBooked = busySlots.includes(normalizedSlot);

            if (isBooked) {
                btn.className = "slot-chip disabled";
                btn.disabled = true;
                btn.title = "This slot is already booked.";
            } else {
                if (this.selectedTimeSlot === slot) {
                    btn.classList.add("active");
                }

                btn.addEventListener("click", () => {
                    this.selectedTimeSlot = slot;
                    
                    document.querySelectorAll(".slot-chip").forEach(c => c.classList.remove("active"));
                    btn.classList.add("active");
                    
                    this.showToast(`Time slot selected: ${slot}`, "success");
                });
            }

            slotsGrid.appendChild(btn);
        });
    },

    // Travel Location Fee Toggles
    selectLocation(type) {
        this.selectedLocation = type;
        
        document.querySelectorAll(".location-card").forEach(card => card.classList.remove("active"));
        const locCard = document.getElementById(`loc-${type}`);
        if (locCard) locCard.classList.add("active");

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
            if (feeContainer) {
                feeContainer.style.display = "flex";
                document.getElementById("widget-travel-fee").textContent = `+${this.travelFee.toLocaleString()} BDT`;
            }
        } else {
            if (feeContainer) feeContainer.style.display = "none";
        }

        if (widgetTotal) widgetTotal.textContent = `${total.toLocaleString()} BDT`;
    },

    // Dispatch AJAX request to add slot inquiry to the PHP session agenda
    async addActiveBooking() {
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

        const yyyy = this.selectedDate.getFullYear();
        const mm = String(this.selectedDate.getMonth() + 1).padStart(2, '0');
        const dd = String(this.selectedDate.getDate()).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;

        const payload = {
            serviceId: this.activeService.id,
            date: dateStr,
            timeSlot: this.selectedTimeSlot,
            location: this.selectedLocation
        };

        try {
            const res = await fetch('index.php?action=add-to-agenda', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const result = await res.json();

            if (result.status === 'success') {
                await this.fetchAgenda();
                
                // Close modal and notify
                document.getElementById("booking-modal").classList.remove("active");
                this.showToast("Slot successfully reserved in your Agenda!", "success");

                // Slide open the booking drawer immediately
                setTimeout(() => this.openDrawer(), 500);
            } else {
                this.showToast(result.message || "Failed to add slot to agenda.", "error");
            }
        } catch (e) {
            console.error("Error adding active booking:", e);
            this.showToast("Network error. Failed to add booking.", "error");
        }
    },

    // Dispatch AJAX request to remove item from session agenda
    async removeBooking(key) {
        try {
            const res = await fetch('index.php?action=remove-from-agenda', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ key: key })
            });
            const result = await res.json();

            if (result.status === 'success') {
                await this.fetchAgenda();
                this.showToast("Reservation removed.", "success");
            } else {
                this.showToast(result.message || "Failed to remove reservation.", "error");
            }
        } catch (e) {
            console.error("Error removing booking:", e);
            this.showToast("Network error. Failed to remove reservation.", "error");
        }
    },

    // Sliding Drawer Syncing & Rendering
    openDrawer() {
        const overlay = document.getElementById("drawer-overlay");
        const drawer = document.getElementById("agenda-drawer");
        if (overlay) overlay.classList.add("active");
        if (drawer) drawer.classList.add("active");
    },

    openMobileMenu() {
        const overlay = document.getElementById("drawer-overlay");
        const mobileNav = document.getElementById("mobile-nav-drawer");
        if (overlay) overlay.classList.add("active");
        if (mobileNav) mobileNav.style.transform = "translateX(0)";
    },

    closeDrawer() {
        const overlay = document.getElementById("drawer-overlay");
        const drawer = document.getElementById("agenda-drawer");
        const mobileNav = document.getElementById("mobile-nav-drawer");
        
        if (overlay) overlay.classList.remove("active");
        if (drawer) drawer.classList.remove("active");
        if (mobileNav) mobileNav.style.transform = "translateX(-100%)";
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
        if (!container) return;
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
            if (footerPricing) footerPricing.style.display = "none";
            if (actionBtn) {
                actionBtn.disabled = true;
                actionBtn.style.opacity = "0.5";
                actionBtn.style.cursor = "not-allowed";
            }
            return;
        }

        if (footerPricing) footerPricing.style.display = "block";
        if (actionBtn) {
            actionBtn.disabled = false;
            actionBtn.style.opacity = "1";
            actionBtn.style.cursor = "pointer";
        }

        this.bookings.forEach(item => {
            const isLocation = item.location === "location";
            
            // Render local timezone safe parsed date
            const parsedDate = new Date(item.date.replace(/-/g, "/"));

            const itemEl = document.createElement("div");
            itemEl.className = "agenda-item";
            itemEl.innerHTML = `
                <div class="agenda-item-body">
                    <h4 class="agenda-item-name">${item.serviceName}</h4>
                    <div class="agenda-item-detail">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <span>${item.timeSlot} — ${this.formatDatePretty(parsedDate)}</span>
                    </div>
                    <div class="agenda-item-detail">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <span>${isLocation ? "On-Location Guest Service" : "Studio Atelier (In-Studio)"}</span>
                    </div>
                    <h5 class="agenda-item-price">${item.price.toLocaleString()} BDT</h5>
                </div>
                <button class="agenda-item-remove" onclick="BookingApp.removeBooking('${item.key}')" title="Remove Session">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            `;
            container.appendChild(itemEl);
        });
    },

    // Checkout Inquiry Transitions & Submissions
    showCheckoutSection() {
        if (this.bookings.length === 0) {
            this.showToast("Your booking agenda is empty.", "error");
            return;
        }

        const section = document.getElementById("checkout-section");
        if (!section) return;
        section.classList.add("active");
        
        // Dynamic location address fields
        const hasLocation = this.bookings.some(b => b.location === "location");
        const addressGroup = document.getElementById("field-address-group");
        const addressInput = document.getElementById("client-address");
        
        if (addressGroup && addressInput) {
            if (hasLocation) {
                addressGroup.style.display = "flex";
                addressInput.required = true;
                this.showToast("On-location session selected. Event address is required.", "success");
            } else {
                addressGroup.style.display = "none";
                addressInput.required = false;
            }
        }

        this.renderCheckoutSummary();

        // Scroll to checkout smoothly
        setTimeout(() => {
            section.scrollIntoView({ behavior: "smooth" });
        }, 100);
    },

    renderCheckoutSummary() {
        const summaryList = document.getElementById("checkout-summary-list");
        if (!summaryList) return;
        summaryList.innerHTML = "";

        if (this.bookings.length === 0) {
            const checkoutSect = document.getElementById("checkout-section");
            if (checkoutSect) checkoutSect.classList.remove("active");
            return;
        }

        let subtotal = 0;
        let totalTravel = 0;

        this.bookings.forEach(item => {
            subtotal += item.basePrice;
            const isLocation = item.location === "location";
            if (isLocation) totalTravel += this.travelFee;

            const parsedDate = new Date(item.date.replace(/-/g, "/"));

            const el = document.createElement("div");
            el.className = "summary-item";
            el.innerHTML = `
                <div class="summary-item-info">
                    <h6>${item.serviceName}</h6>
                    <p>
                        <span>📅 ${this.formatDatePretty(parsedDate)} @ ${item.timeSlot}</span>
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

    // Transaction-secure client submission to backend PDO endpoint
    async submitInquiry() {
        const fullName = document.getElementById("client-name").value.trim();
        const email = document.getElementById("client-email").value.trim();
        const whatsapp = document.getElementById("client-phone").value.trim();
        const address = document.getElementById("client-address").value.trim();
        const skinType = document.getElementById("client-skin").value;
        const preference = document.getElementById("client-preferences").value.trim();

        const fd = new FormData();
        fd.append('name', fullName);
        fd.append('email', email);
        fd.append('phone', whatsapp);
        fd.append('address', address);
        fd.append('skin', skinType);
        fd.append('preferences', preference);

        try {
            const res = await fetch('index.php?action=submit-booking', {
                method: 'POST',
                body: fd
            });
            const result = await res.json();

            if (result.status === 'success') {
                // Reset local states
                this.bookings = [];
                this.updateBadgeCount();
                this.renderBookingAgenda();

                // Reset forms
                document.getElementById("checkout-form").reset();

                // Populate dynamic compiled receipt
                const receiptCard = document.getElementById("receipt-detail-card");
                if (receiptCard) {
                    receiptCard.innerHTML = "";
                    result.receipt_lines.forEach(line => {
                        const el = document.createElement("div");
                        el.className = "receipt-line";
                        if (line.label.includes("Bespoke Total Investment")) {
                            el.innerHTML = `
                                <strong>${line.label}</strong>
                                <strong class="receipt-price">${line.val}</strong>
                            `;
                        } else {
                            el.innerHTML = `
                                <span>${line.label}</span>
                                <span class="receipt-price">${line.val}</span>
                            `;
                        }
                        receiptCard.appendChild(el);
                    });
                }

                // Bind click redirects to server compiled links
                const waBtn = document.getElementById("btn-submit-wa");
                const mailBtn = document.getElementById("btn-submit-mail");
                
                if (waBtn) {
                    waBtn.onclick = () => {
                        window.open(result.whatsapp_url, "_blank");
                    };
                }
                if (mailBtn) {
                    mailBtn.onclick = () => {
                        window.location.href = result.mailto_url;
                    };
                }

                // Hide customizer modal & trigger compiling success overlay
                const bookingMdl = document.getElementById("booking-modal");
                if (bookingMdl) bookingMdl.classList.remove("active");

                const receiptMdl = document.getElementById("receipt-modal");
                if (receiptMdl) receiptMdl.classList.add("active");

                this.showToast(`Bespoke inquiry compiled! Reference: ${result.booking_ref}`, "success");
            } else {
                this.showToast(result.message || "Failed to submit booking inquiry.", "error");
            }
        } catch (e) {
            console.error("Booking inquiry transaction error:", e);
            this.showToast("Network error. Failed to compile your reservation request.", "error");
        }
    },

    // Helper Systems (Pretty Dates & Custom Toasts)
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
        if (!container) return;
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
        
        // Auto-fadeout toast anims
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
