/**
 * KARTLY E-Commerce - Main JavaScript
 * Pure Vanilla JS - No Framework Dependencies
 */

// =============================================
// DOM Ready
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    initMobileMenu();
    initHeroSlider();
    initDealTimers();
    initSearch();
    initProductCards();
    initProductGallery();
    initCart();
    initWishlist();
    initNewsletter();
    initModals();
    initTabs();
});

// =============================================
// Mobile Menu
// =============================================
function initMobileMenu() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const mobileNav = document.querySelector('.mobile-nav');
    const overlay = document.querySelector('.mobile-nav-overlay');
    const closeBtn = document.querySelector('.mobile-nav-close');
    
    if (!menuBtn || !mobileNav) return;
    
    function openMenu() {
        mobileNav.classList.add('active');
        overlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeMenu() {
        mobileNav.classList.remove('active');
        overlay?.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    menuBtn.addEventListener('click', openMenu);
    closeBtn?.addEventListener('click', closeMenu);
    overlay?.addEventListener('click', closeMenu);
    
    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileNav.classList.contains('active')) {
            closeMenu();
        }
    });
}

// =============================================
// Deal Timers
// =============================================
function initDealTimers() {
    const timerElements = document.querySelectorAll('.deal-timer[data-deal-end-ts]');
    if (!timerElements.length) return;

    const pad = (value) => String(value).padStart(2, '0');

    function formatCountdown(seconds) {
        const total = Math.max(0, Math.floor(seconds));
        const days = Math.floor(total / 86400);
        const hours = Math.floor((total % 86400) / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const secs = total % 60;

        if (days > 0) {
            return `${days}d ${pad(hours)}:${pad(minutes)}:${pad(secs)}`;
        }

        return `${pad(hours)}:${pad(minutes)}:${pad(secs)}`;
    }

    function tick() {
        const now = Date.now();
        timerElements.forEach((timerElement) => {
            const endTs = Number(timerElement.getAttribute('data-deal-end-ts') || 0);
            if (!endTs) {
                return;
            }

            const remainingSeconds = (endTs * 1000 - now) / 1000;
            const timerValueElement = timerElement.querySelector('.deal-timer-value');
            if (timerValueElement) {
                timerValueElement.textContent = formatCountdown(remainingSeconds);
            }
        });
    }

    tick();
    setInterval(tick, 1000);
}

// =============================================
// Hero Slider
// =============================================
function initHeroSlider() {
    const slider = document.querySelector('.hero-slider');
    if (!slider) return;
    
    const slides = slider.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.hero-dot');
    const prevBtn = document.querySelector('.hero-nav-prev button');
    const nextBtn = document.querySelector('.hero-nav-next button');
    
    let currentSlide = 0;
    let interval;
    
    function showSlide(index) {
        slides.forEach((slide, i) => {
            slide.classList.toggle('active', i === index);
        });
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
        });
        currentSlide = index;
    }
    
    function nextSlide() {
        showSlide((currentSlide + 1) % slides.length);
    }
    
    function prevSlide() {
        showSlide((currentSlide - 1 + slides.length) % slides.length);
    }
    
    function startAutoPlay() {
        interval = setInterval(nextSlide, 6000);
    }
    
    function stopAutoPlay() {
        clearInterval(interval);
    }
    
    // Event listeners
    prevBtn?.addEventListener('click', () => {
        stopAutoPlay();
        prevSlide();
        startAutoPlay();
    });
    
    nextBtn?.addEventListener('click', () => {
        stopAutoPlay();
        nextSlide();
        startAutoPlay();
    });
    
    dots.forEach((dot, index) => {
        dot.addEventListener('click', () => {
            stopAutoPlay();
            showSlide(index);
            startAutoPlay();
        });
    });
    
    // Start autoplay
    startAutoPlay();
}

// =============================================
// Search
// =============================================
function initSearch() {
    const searchToggle = document.querySelector('.search-toggle');
    const mobileSearch = document.querySelector('.mobile-search');

    searchToggle?.addEventListener('click', () => {
        mobileSearch?.classList.toggle('active');
        if (mobileSearch?.classList.contains('active')) {
            mobileSearch.querySelector('input')?.focus();
        }
    });

    // The desktop and mobile search forms submit natively via GET;
    // only intercept if the input is empty to prevent blank search.
    const searchForms = document.querySelectorAll('.header-search-form, .mobile-search-form');
    searchForms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const query = form.querySelector('input[name="search"]')?.value.trim();
            if (!query) {
                e.preventDefault(); // don't submit empty search
            }
        });
    });
}

// =============================================
// Product Cards
// =============================================
function initProductCards() {
    // Quick add to cart
    const addToCartBtns = document.querySelectorAll('.product-add-cart');
    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const productId = btn.dataset.productId;
            await addToCart(productId, 1);
        });
    });
    
    // Quick view
    const quickViewBtns = document.querySelectorAll('.product-quick-view');
    quickViewBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const productId = btn.dataset.productId;
            openQuickView(productId);
        });
    });
}

function initProductGallery() {
    const mainImage = document.getElementById('product-main-image');
    const thumbButtons = document.querySelectorAll('.product-thumb-btn');

    if (!mainImage || !thumbButtons.length) {
        return;
    }

    thumbButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const src = button.dataset.imageSrc;
            const alt = button.dataset.imageAlt || mainImage.alt;
            if (!src) {
                return;
            }

            mainImage.src = src;
            mainImage.alt = alt;

            thumbButtons.forEach((thumb) => thumb.classList.remove('active'));
            button.classList.add('active');
        });
    });
}

// =============================================
// Cart Functions
// =============================================
let cart = JSON.parse(localStorage.getItem('kartly_cart')) || [];

function initCart() {
    updateCartBadge();
    refreshCartCountFromServer();
    
    // Add to cart form
    const addToCartForm = document.querySelector('.add-to-cart-form');
    addToCartForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const productId = addToCartForm.dataset.productId;
        const quantity = parseInt(addToCartForm.querySelector('input[name="quantity"]')?.value || 1);
        await addToCart(productId, quantity);
    });

    const buyNowBtn = addToCartForm?.querySelector('.buy-now-btn');
    buyNowBtn?.addEventListener('click', async (e) => {
        e.preventDefault();
        if (buyNowBtn.disabled) {
            return;
        }

        const productId = addToCartForm.dataset.productId;
        const quantity = parseInt(addToCartForm.querySelector('input[name="quantity"]')?.value || 1);

        buyNowBtn.disabled = true;
        const originalLabel = buyNowBtn.textContent;
        buyNowBtn.textContent = 'Processing...';

        const result = await addToCart(productId, quantity);

        buyNowBtn.textContent = originalLabel;
        buyNowBtn.disabled = false;

        if (result && result.success) {
            window.location.href = 'checkout.php';
        }
    });

    initCartPageControls();
}

async function addToCart(productId, quantity = 1) {
    try {
        const data = await apiRequest('api/cart.php', {
            action: 'add',
            product_id: productId,
            quantity: quantity
        });
        
        if (data.success) {
            showToast('Product added to cart!', 'success');
            updateCartBadge(data.cart_count);
        } else {
            showToast(data.message || 'Error adding to cart', 'error');
        }
        return data;
    } catch (error) {
        // Fallback to local storage
        const existingItem = cart.find(item => item.product_id === productId);
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            cart.push({ product_id: productId, quantity: quantity });
        }
        localStorage.setItem('kartly_cart', JSON.stringify(cart));
        updateCartBadge();
        showToast('Product added to cart!', 'success');
        return { success: true, fallback: true };
    }
}

async function refreshCartCountFromServer() {
    const data = await apiRequest('api/cart.php', { action: 'count' }, 'GET');
    if (data.success) {
        updateCartBadge(data.count);
    }
}

function initCartPageControls() {
    const cartItems = document.querySelectorAll('.cart-item');
    if (!cartItems.length) return;

    const quantityInputs = document.querySelectorAll('input[data-cart-id]');
    quantityInputs.forEach((input) => {
        input.addEventListener('change', async () => {
            const cartId = input.dataset.cartId;
            const min = parseInt(input.min || '1', 10);
            const max = parseInt(input.max || '999', 10);
            const value = Math.max(min, Math.min(max, parseInt(input.value || String(min), 10)));
            input.value = String(value);
            await updateCartItem(cartId, value);
        });
    });

    const removeButtons = document.querySelectorAll('.cart-remove');
    removeButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const cartId = button.dataset.cartId;
            await removeCartItem(cartId);
        });
    });
}

async function updateCartItem(cartId, quantity) {
    const data = await apiRequest('api/cart.php', {
        action: 'update',
        cart_id: cartId,
        quantity: quantity
    });

    if (!data.success) {
        showToast(data.message || 'Unable to update cart', 'error');
        return;
    }

    updateCartBadge(data.cart_count);
    window.location.reload();
}

async function removeCartItem(cartId) {
    const data = await apiRequest('api/cart.php', {
        action: 'remove',
        cart_id: cartId
    });

    if (!data.success) {
        showToast(data.message || 'Unable to remove cart item', 'error');
        return;
    }

    updateCartBadge(data.cart_count);
    window.location.reload();
}

function updateCartBadge(count) {
    const badges = document.querySelectorAll('.cart-badge');
    let resolvedCount = count;

    if (resolvedCount === undefined) {
        resolvedCount = cart.reduce((sum, item) => sum + item.quantity, 0);
    }

    const shouldShow = Number(resolvedCount) > 0;
    if (count !== undefined) {
        badges.forEach((badge) => {
            badge.textContent = String(resolvedCount);
            badge.classList.toggle('hidden', !shouldShow);
        });
        return;
    }

    badges.forEach((badge) => {
        badge.textContent = String(resolvedCount);
        badge.classList.toggle('hidden', !shouldShow);
    });
}

// =============================================
// Wishlist Functions
// =============================================
let wishlist = (JSON.parse(localStorage.getItem('kartly_wishlist')) || []).map(String);

function initWishlist() {
    updateWishlistBadge();
    refreshWishlistCountFromServer();
    syncWishlistButtonsFromServer();
    
    // Wishlist toggle
    const wishlistBtns = document.querySelectorAll('.product-wishlist');
    wishlistBtns.forEach(btn => {
        const productId = btn.dataset.productId;
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            await toggleWishlist(productId, btn);
        });
    });
}

async function toggleWishlist(productId, btn) {
    try {
        const data = await apiRequest('api/wishlist.php', {
            action: 'toggle',
            product_id: productId
        });
        
        if (data.success) {
            if (typeof data.active === 'boolean') {
                btn.classList.toggle('active', data.active);
                if (data.active) {
                    if (!wishlist.includes(String(productId))) {
                        wishlist.push(String(productId));
                    }
                } else {
                    wishlist = wishlist.filter((id) => id !== String(productId));
                }
                localStorage.setItem('kartly_wishlist', JSON.stringify(wishlist));
            } else {
                btn.classList.toggle('active');
            }
            showToast(
                btn.classList.contains('active') 
                    ? 'Added to wishlist!' 
                    : 'Removed from wishlist',
                'success'
            );
            updateWishlistBadge(data.wishlist_count);
        } else {
            showToast(data.message || 'Unable to update wishlist', 'error');
        }
    } catch (error) {
        // Fallback to local storage
        const index = wishlist.indexOf(productId);
        if (index > -1) {
            wishlist.splice(index, 1);
            btn.classList.remove('active');
            showToast('Removed from wishlist', 'success');
        } else {
            wishlist.push(productId);
            btn.classList.add('active');
            showToast('Added to wishlist!', 'success');
        }
        localStorage.setItem('kartly_wishlist', JSON.stringify(wishlist));
        updateWishlistBadge();
    }
}

async function syncWishlistButtonsFromServer() {
    const data = await apiRequest('api/wishlist.php', { action: 'get' }, 'GET');
    if (!data.success || !Array.isArray(data.items)) {
        return;
    }

    const ids = new Set(data.items.map((item) => String(item.product_id)));
    wishlist = Array.from(ids);
    localStorage.setItem('kartly_wishlist', JSON.stringify(wishlist));

    document.querySelectorAll('.product-wishlist').forEach((btn) => {
        btn.classList.toggle('active', ids.has(String(btn.dataset.productId)));
    });
}

async function refreshWishlistCountFromServer() {
    const data = await apiRequest('api/wishlist.php', { action: 'count' }, 'GET');
    if (data.success) {
        updateWishlistBadge(data.count);
    }
}

function updateWishlistBadge(count) {
    const badges = document.querySelectorAll('.wishlist-badge');
    let resolvedCount = count;

    if (count !== undefined) {
        resolvedCount = count;
    } else {
        resolvedCount = wishlist.length;
    }

    const shouldShow = Number(resolvedCount) > 0;
    badges.forEach((badge) => {
        badge.textContent = String(resolvedCount);
        badge.classList.toggle('hidden', !shouldShow);
    });
}

// =============================================
// Newsletter
// =============================================
function initNewsletter() {
    const form = document.querySelector('.newsletter-form');
    
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = form.querySelector('input[type="email"]').value;
        
        if (!validateEmail(email)) {
            showToast('Please enter a valid email address', 'error');
            return;
        }
        
        try {
            const data = await apiRequest('api/newsletter.php', { email: email });
            
            if (data.success) {
                showToast('Thank you for subscribing!', 'success');
                form.reset();
            } else {
                showToast(data.message || 'Error subscribing', 'error');
            }
        } catch (error) {
            showToast('Thank you for subscribing!', 'success');
            form.reset();
        }
    });
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// =============================================
// Modals
// =============================================
function initModals() {
    // Close modal on overlay click
    const overlays = document.querySelectorAll('.modal-overlay');
    overlays.forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });
    });
    
    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) closeModal(activeModal);
        }
    });
    
    // Close buttons
    const closeButtons = document.querySelectorAll('.modal-close');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            closeModal(modal);
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    if (typeof modal === 'string') {
        modal = document.getElementById(modal);
    }
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function openQuickView(productId) {
    // Load product data and show in modal
    apiRequest('api/product.php', { id: productId }, 'GET')
        .then((data) => {
            if (!data.success) {
                showToast(data.message || 'Unable to load product details', 'error');
                return;
            }
            const modal = document.getElementById('quick-view-modal');
            if (!modal) {
                showToast('Quick view is not available on this page', 'warning');
                return;
            }
            openModal('quick-view-modal');
        })
        .catch(() => {
            showToast('Unable to load product details', 'error');
        });
}

// =============================================
// Tabs
// =============================================
function initTabs() {
    const tabGroups = document.querySelectorAll('.tabs');
    
    tabGroups.forEach(group => {
        const tabs = group.querySelectorAll('.tab');
        const panels = group.querySelectorAll('.tab-panel');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetId = tab.dataset.tab;
                
                // Update tabs
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Update panels
                panels.forEach(panel => {
                    panel.classList.toggle('active', panel.id === targetId);
                });
            });
        });
    });
}

// =============================================
// Toast Notifications
// =============================================
function showToast(message, type = 'success') {
    const container = document.querySelector('.toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            ${type === 'success' 
                ? '<path d="M20 6L9 17l-5-5"/>' 
                : '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>'}
        </svg>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// =============================================
// Utility Functions
// =============================================
function formatPrice(price) {
    return '$' + parseFloat(price).toFixed(2);
}

async function apiRequest(url, payload = {}, method = 'POST') {
    const upperMethod = method.toUpperCase();
    const options = { method: upperMethod, headers: {} };
    let requestUrl = url;

    // Resolve relative URL using BASE_URL
    if (!requestUrl.startsWith('http') && !requestUrl.startsWith('/')) {
        requestUrl = (window.BASE_URL || '') + '/' + requestUrl;
    }

    if (upperMethod === 'GET') {
        const query = new URLSearchParams(payload).toString();
        if (query) {
            requestUrl += (requestUrl.includes('?') ? '&' : '?') + query;
        }
    } else {
        options.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
        options.body = new URLSearchParams(payload).toString();
    }

    const response = await fetch(requestUrl, options);
    const text = await response.text();

    let data = {};
    if (text) {
        try {
            data = JSON.parse(text);
        } catch (error) {
            data = { success: false, message: 'Invalid server response' };
        }
    }

    if (!response.ok && typeof data.success === 'undefined') {
        return { success: false, message: `Request failed (${response.status})` };
    }

    if (!response.ok) {
        return {
            ...data,
            success: false,
            message: data.message || `Request failed (${response.status})`
        };
    }

    return data;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// =============================================
// Form Validation
// =============================================
function validateForm(form) {
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        const value = input.value.trim();
        const errorMsg = input.parentElement.querySelector('.error-message');
        
        if (!value) {
            input.classList.add('error');
            if (errorMsg) errorMsg.textContent = 'This field is required';
            isValid = false;
        } else if (input.type === 'email' && !validateEmail(value)) {
            input.classList.add('error');
            if (errorMsg) errorMsg.textContent = 'Please enter a valid email';
            isValid = false;
        } else {
            input.classList.remove('error');
            if (errorMsg) errorMsg.textContent = '';
        }
    });
    
    return isValid;
}

// =============================================
// Quantity Controls
// =============================================
function initQuantityControls() {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    
    quantityInputs.forEach(container => {
        const input = container.querySelector('input');
        const minusBtn = container.querySelector('.quantity-minus');
        const plusBtn = container.querySelector('.quantity-plus');
        const max = parseInt(input.max) || 99;
        const min = parseInt(input.min) || 1;
        
        minusBtn?.addEventListener('click', () => {
            const value = parseInt(input.value) || 1;
            if (value > min) {
                input.value = value - 1;
                input.dispatchEvent(new Event('change'));
            }
        });
        
        plusBtn?.addEventListener('click', () => {
            const value = parseInt(input.value) || 1;
            if (value < max) {
                input.value = value + 1;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
}

// Initialize quantity controls
document.addEventListener('DOMContentLoaded', initQuantityControls);
