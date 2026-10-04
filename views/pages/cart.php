<?php
switch ('cart'):
case 'cart':

    $categories = $categoryManager->getAllCategories();
    ?>
    
    <!-- ============================================ -->
    <!-- CART PAGE - MODERN POS INTERFACE -->
    <!-- ============================================ -->
    
    <!-- Page Header -->
    <div class="pos-page-header">
        <div class="pos-page-header-left">
            <h2 class="pos-page-title">
                <span class="pos-page-icon">🛒</span>
                Shopping Cart
            </h2>
            <p class="pos-page-subtitle">Add products and complete the sale</p>
        </div>
        <button class="pos-checkout-btn-top no-print" onclick="showPaymentModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                <line x1="1" y1="10" x2="23" y2="10"></line>
            </svg>
            Complete Sale
        </button>
    </div>
    
    <!-- POS Grid -->
    <div class="pos-grid-modern">
        
        <!-- ============================================ -->
        <!-- LEFT PANEL - PRODUCTS -->
        <!-- ============================================ -->
        <div class="pos-products-panel">
            
            <!-- Search Bar -->
            <div class="pos-search-wrap">
                <span class="pos-search-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </span>
                <input type="text" id="searchProduct" placeholder="Search products..." onkeyup="searchProducts()" class="pos-search-input">
                <input type="text" id="barcodeInput" placeholder="Scan / type barcode + Enter" onkeydown="if(event.key==='Enter'){scanBarcode();event.preventDefault();}" class="pos-search-input" style="max-width:220px;">
                <button type="button" class="pos-search-clear" onclick="clearSearch()" title="Clear search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            
            <!-- Category Filter -->
            <div class="pos-category-filter" id="categoryFilter">
                <button class="pos-cat-btn active" onclick="filterByCategory(0, this)">All Products</button>
                <?php foreach ($categories as $cat): ?>
                <button class="pos-cat-btn" onclick="filterByCategory(<?php echo $cat['id']; ?>, this)"><?php echo htmlspecialchars($cat['name']); ?></button>
                <?php endforeach; ?>
            </div>
            
            <!-- Product List -->
            <div class="pos-products-list" id="productList"></div>
        </div>
        
        <!-- ============================================ -->
        <!-- RIGHT PANEL - CART -->
        <!-- ============================================ -->
        <div class="pos-cart-panel">
            <div class="pos-cart-card">
                
                <!-- Cart Header -->
                <div class="pos-cart-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        Cart
                    </h3>
                    <span class="pos-cart-count" id="cartCount">0 items</span>
                </div>
                
                <!-- Customer Select -->
                <div class="pos-customer-select">
                    <label class="pos-customer-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Customer
                    </label>
                    <div class="pos-customer-input-group">
                        <select id="customerSelect" onchange="checkLoyaltyPoints()">
                            <option value="">Walk-in Customer</option>
                        </select>
                        <button type="button" class="pos-customer-add" onclick="showAddCustomer()" title="Add new customer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Discount Section -->
                <div class="pos-discount-section">
                    <div class="pos-section-header">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                            <line x1="7" y1="7" x2="7.01" y2="7"></line>
                        </svg>
                        Discount
                    </div>
                    <div class="pos-discount-inputs">
                        <input type="number" id="discountAmount" placeholder="0.00" step="0.01" min="0" class="pos-discount-value">
                        <select id="discountType" class="pos-discount-type">
                            <option value="fixed">₱ Fixed</option>
                            <option value="percent">% Percent</option>
                        </select>
                        <button type="button" class="pos-discount-apply" onclick="applyDiscount()">Apply</button>
                    </div>
                    <div id="discountDisplay" class="pos-discount-display"></div>
                </div>
                
                <!-- Loyalty Section -->
                <div class="pos-loyalty-section">
                    <div class="pos-loyalty-row">
                        <div class="pos-loyalty-info">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            <span>Loyalty Points</span>
                        </div>
                        <div class="pos-loyalty-points">
                            <span id="loyaltyPointsDisplay">0</span>
                            <span class="pos-loyalty-label">points</span>
                        </div>
                    </div>
                    <button type="button" class="pos-loyalty-btn" onclick="useLoyaltyPoints()" id="useLoyaltyBtn" disabled>
                        Use Points
                    </button>
                    <div class="pos-loyalty-hint">1 point = ₱1 discount · 1 point per ₱100 spent</div>
                </div>
                
                <!-- Cart Items -->
                <div class="pos-cart-items" id="cartItems"></div>
                
                <!-- Cart Totals -->
                <div class="pos-cart-totals">
                    <div class="pos-total-row">
                        <span class="pos-total-label">Subtotal (VAT incl.)</span>
                        <span class="pos-total-value" id="subtotal">₱0.00</span>
                    </div>
                    <div class="pos-total-row pos-total-row-discount">
                        <span class="pos-total-label">Discount</span>
                        <span class="pos-total-value" id="discountTotal">-₱0.00</span>
                    </div>
                    <div class="pos-total-row">
                        <span class="pos-total-label">VAT included (<?php echo htmlspecialchars(rtrim(rtrim(number_format((float)getSetting('tax_rate', 12), 2, '.', ''), '0'), '.')); ?>%)</span>
                        <span class="pos-total-value" id="tax">₱0.00</span>
                    </div>
                    <div class="pos-total-row pos-total-grand">
                        <span class="pos-total-label-grand">Total</span>
                        <span class="pos-total-value-grand" id="total">₱0.00</span>
                    </div>
                </div>
                
                <!-- Complete Sale Button -->
                <button class="pos-checkout-btn no-print" onclick="showPaymentModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                    Complete Sale
                </button>
                
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- PAYMENT MODAL -->
    <!-- ============================================ -->
    <div class="modal" id="paymentModal">
        <div class="modal-content pos-payment-modal">
            
            <!-- Modal Header -->
            <div class="pos-modal-header">
                <div class="pos-modal-header-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                </div>
                <div class="pos-modal-header-text">
                    <h2>Payment</h2>
                    <p>Enter amount received from customer</p>
                </div>
                <button class="pos-modal-close" onclick="closeModal('paymentModal')">&times;</button>
            </div>
            
            <!-- Payment Summary -->
            <div class="pos-payment-summary">
                <div class="pos-payment-row">
                    <span>Subtotal (VAT incl.)</span>
                    <span id="paySubtotal">₱0.00</span>
                </div>
                <div class="pos-payment-row pos-payment-row-discount">
                    <span>Discount</span>
                    <span id="payDiscount">-₱0.00</span>
                </div>
                <div class="pos-payment-row">
                    <span>VAT included (<?php echo htmlspecialchars(rtrim(rtrim(number_format((float)getSetting('tax_rate', 12), 2, '.', ''), '0'), '.')); ?>%)</span>
                    <span id="payTax">₱0.00</span>
                </div>
                <div class="pos-payment-row">
                    <span>Loyalty Points Used</span>
                    <span id="payLoyalty">0 pts</span>
                </div>
                <div class="pos-payment-row pos-payment-row-total">
                    <span>TOTAL DUE</span>
                    <span id="payTotal">₱0.00</span>
                </div>
            </div>
            
            <!-- Payment Method -->
            <div class="pos-amount-section">
                <label class="pos-amount-label">Payment Method</label>
                <select id="paymentMethod" class="pos-amount-input" onchange="toggleCardTokens()" style="height:auto;padding:0.6rem;">
                    <option value="cash">Cash</option>
                    <option value="card">Card (tokenized)</option>
                    <option value="gcash">GCash</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="pos-amount-section" id="cardTokenRow" style="display:none;">
                <label class="pos-amount-label">Saved Card Token</label>
                <select id="cardTokenSelect" class="pos-amount-input" style="height:auto;padding:0.6rem;"></select>
            </div>

            <!-- Amount Input -->
            <div class="pos-amount-section">
                <label class="pos-amount-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    Amount Received
                </label>
                <div class="pos-amount-input-wrap">
                    <span class="pos-amount-currency">₱</span>
                    <input type="number" id="amountReceived" step="0.01" min="0" placeholder="0.00" oninput="calculateChange()" class="pos-amount-input" autocomplete="off">
                </div>
            </div>
            
            <!-- Quick Amounts -->
            <div class="pos-quick-amounts">
                <button type="button" class="pos-quick-btn" onclick="quickAmount(100)">₱100</button>
                <button type="button" class="pos-quick-btn" onclick="quickAmount(200)">₱200</button>
                <button type="button" class="pos-quick-btn" onclick="quickAmount(500)">₱500</button>
                <button type="button" class="pos-quick-btn" onclick="quickAmount(1000)">₱1,000</button>
                <button type="button" class="pos-quick-btn" onclick="quickAmount(2000)">₱2,000</button>
                <button type="button" class="pos-quick-btn pos-quick-btn-exact" onclick="quickExact()">Exact</button>
            </div>
            
            <!-- Change Display -->
            <div class="pos-change-display" id="changeDisplay">
                <div class="pos-change-label">Change</div>
                <div class="pos-change-amount" id="changeAmount">₱0.00</div>
            </div>
            
            <!-- Action Buttons -->
            <div class="pos-modal-actions">
                <button type="button" class="pos-btn-cancel" onclick="closeModal('paymentModal')">
                    Cancel
                </button>
                <button type="button" class="pos-btn-confirm" onclick="processPayment()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Confirm Payment
                </button>
            </div>
            
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- ADD CUSTOMER MODAL -->
    <!-- ============================================ -->
    <div class="modal" id="addCustomerModal">
        <div class="modal-content" style="max-width: 500px;">
            
            <div class="pos-modal-header">
                <div class="pos-modal-header-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="8.5" cy="7" r="4"></circle>
                        <line x1="20" y1="8" x2="20" y2="14"></line>
                        <line x1="23" y1="11" x2="17" y2="11"></line>
                    </svg>
                </div>
                <div class="pos-modal-header-text">
                    <h2>Add New Customer</h2>
                    <p>Quickly add a customer for loyalty tracking</p>
                </div>
                <button class="pos-modal-close" onclick="closeModal('addCustomerModal')">&times;</button>
            </div>
            
            <form onsubmit="saveCustomer(event)">
                <div class="pos-form-group">
                    <label class="pos-form-label">Full Name *</label>
                    <input type="text" id="custName" placeholder="Enter customer name" required class="pos-form-input">
                </div>
                
                <div class="pos-form-group">
                    <label class="pos-form-label">Email Address</label>
                    <input type="email" id="custEmail" placeholder="customer@email.com" class="pos-form-input">
                </div>
                
                <div class="pos-form-group">
                    <label class="pos-form-label">Phone Number (11 digits)</label>
                    <input type="text" id="custPhone" placeholder="09123456789" maxlength="11" oninput="validateCustomerPhone(this)" class="pos-form-input">
                    <div id="custPhoneError" class="pos-form-hint"></div>
                </div>
                
                <div class="pos-modal-actions" style="grid-template-columns: 1fr 1fr;">
                    <button type="button" class="pos-btn-cancel" onclick="closeModal('addCustomerModal')">
                        Cancel
                    </button>
                    <button type="submit" class="pos-btn-confirm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- RECEIPT MODAL -->
    <!-- ============================================ -->
    <div class="modal" id="receiptModal">
        <div class="modal-content pos-receipt-modal">
            
            <div class="pos-receipt-header-actions no-print">
                <div class="pos-receipt-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1z"></path>
                        <path d="M8 7h8"></path>
                        <path d="M8 11h8"></path>
                        <path d="M8 15h5"></path>
                    </svg>
                    Receipt
                </div>
                <button class="pos-modal-close" onclick="closeModal('receiptModal')">&times;</button>
            </div>
            
            <div id="receiptContent"></div>
            
            <div class="pos-receipt-actions no-print">
                <button class="pos-btn-cancel" onclick="closeModal('receiptModal')">
                    Close
                </button>
                <button class="pos-btn-confirm" onclick="window.print()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    Print Receipt
                </button>
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- CART JAVASCRIPT (kept from before — same functions) -->
    <!-- ============================================ -->
    <script>
        // All existing cart JavaScript functions remain the same
        // (validateCustomerPhone, loadProducts, loadCustomers, checkLoyaltyPoints, 
        //  useLoyaltyPoints, filterByCategory, displayProducts, searchProducts, 
        //  addToCart, removeFromCart, updateQty, applyDiscount, updateCartTotals, 
        //  showPaymentModal, calculateChange, quickAmount, quickExact, processPayment, 
        //  showReceipt, showAddCustomer, saveCustomer, closeModal)
        
        // Note: I'm NOT changing the JS logic — only the HTML structure was updated
        
        function validateCustomerPhone(input) {
            var phone = input.value.replace(/\D/g, '');
            var errorDiv = document.getElementById('custPhoneError');
            if (phone.length > 0 && phone.length !== 11) {
                errorDiv.textContent = '⚠️ Phone must be exactly 11 digits';
                errorDiv.style.color = '#EF4444';
                input.style.borderColor = '#EF4444';
            } else if (phone.length === 11) {
                errorDiv.textContent = '✅ Valid phone number';
                errorDiv.style.color = '#10B981';
                input.style.borderColor = '#10B981';
            } else {
                errorDiv.textContent = '';
                input.style.borderColor = '';
            }
        }
        
        // NEW: Clear search function
        function clearSearch() {
            document.getElementById('searchProduct').value = '';
            searchProducts();
        }
        
        var cart = [];
        var products = [];
        var customers = [];
        var currentCategory = 0;
        var currentTotal = 0;
        var currentSubtotal = 0;
        var currentTax = 0;
        var currentDiscount = 0;
        var currentLoyaltyPointsUsed = 0;
        var currentDiscountType = 'fixed';
        var selectedCustomerId = null;
        
        function loadProducts() {
            fetch('?action=get_products')
                .then(function(res) { return res.json(); })
                .then(function(data){
                    products = data;
                    if (currentCategory > 0) { 
                        filterByCategory(currentCategory); 
                    } else { 
                        displayProducts(products); 
                    }
                })
                .catch(function(error) {
                    console.error('Error loading products:', error);
                });
        }
        
        function loadCustomers() {
            fetch('?action=get_customers').then(function(res) { return res.json(); }).then(function(data){
                customers = data;
                var select = document.getElementById('customerSelect');
                select.innerHTML = '<option value="">Walk-in Customer</option>';
                data.forEach(function(c){
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name + (c.phone ? ' (' + c.phone + ')' : '');
                    select.appendChild(opt);
                });
            });
        }
        
        function checkLoyaltyPoints() {
            var customerId = document.getElementById('customerSelect').value;
            if (customerId) {
                fetch('?action=get_customer_loyalty&id=' + customerId)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        document.getElementById('loyaltyPointsDisplay').textContent = data.points;
                        var btn = document.getElementById('useLoyaltyBtn');
                        if (data.points > 0 && currentTotal > 0) {
                            btn.disabled = false;
                        } else {
                            btn.disabled = true;
                        }
                    });
            } else {
                document.getElementById('loyaltyPointsDisplay').textContent = '0';
                document.getElementById('useLoyaltyBtn').disabled = true;
            }
        }
        
        function useLoyaltyPoints() {
            var customerId = document.getElementById('customerSelect').value;
            if (!customerId) return;
            
            fetch('?action=get_customer_loyalty&id=' + customerId)
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    var points = data.points;
                    if (points <= 0) { alert('No loyalty points available.'); return; }
                    
                    var maxDiscount = Math.min(points * 1, currentTotal * 0.5);
                    var usePoints = prompt('Available points: ' + points + '\n1 point = ₱1 discount\nMax discount: ₱' + maxDiscount.toFixed(2) + '\n\nHow many points to use?');
                    if (usePoints === null) return;
                    usePoints = parseInt(usePoints);
                    if (isNaN(usePoints) || usePoints <= 0) { alert('Invalid number of points.'); return; }
                    if (usePoints > points) { alert('Not enough points. Available: ' + points); return; }
                    if (usePoints > currentTotal) { alert('Cannot use more points than total amount.'); return; }
                    
                    currentLoyaltyPointsUsed = usePoints;
                    currentDiscount = usePoints;
                    document.getElementById('discountDisplay').innerHTML = '⭐ Loyalty discount: ₱' + usePoints.toFixed(2) + ' (' + usePoints + ' points)';
                    updateCartTotals();
                    document.getElementById('useLoyaltyBtn').disabled = true;
                    alert('✅ ' + usePoints + ' loyalty points used. Discount: ₱' + usePoints.toFixed(2));
                });
        }
        
        function filterByCategory(categoryId, btn) {
            currentCategory = categoryId;
            document.querySelectorAll('#categoryFilter .pos-cat-btn').forEach(function(b) { b.classList.remove('active'); });
            if (btn) btn.classList.add('active');
            if (categoryId === 0) { displayProducts(products); } 
            else { 
                var filtered = products.filter(function(p) { return p.category_id == categoryId && p.stock_quantity > 0; }); 
                displayProducts(filtered); 
            }
        }
        
        function displayProducts(list) {
            var container = document.getElementById('productList');
            container.innerHTML = '';
            
            if (list.length === 0) {
                container.innerHTML = '<div class="pos-empty-products"><div class="pos-empty-icon">📦</div><div class="pos-empty-title">No products found</div><div class="pos-empty-text">Try a different search or category</div></div>';
                return;
            }
            
            list.forEach(function(p){
                if(p.stock_quantity > 0){
                    var div = document.createElement('div');
                    div.className = 'pos-product-card';
                    
                    var imageHtml = p.image 
                        ? '<div class="pos-product-image"><img src="' + p.image + '" alt="' + p.name + '" loading="lazy"></div>' 
                        : '<div class="pos-product-image pos-product-placeholder"><span>📦</span></div>';
                    
                    var stockClass = p.stock_quantity <= 5 ? 'pos-stock-low' : '';
                    var stockText = p.stock_quantity <= 5 ? 'Low stock · ' + p.stock_quantity : 'Stock: ' + p.stock_quantity;
                    
                    div.innerHTML = 
                        imageHtml +
                        '<div class="pos-product-info">' +
                            '<div class="pos-product-name">' + p.name + '</div>' +
                            (p.category_name ? '<div class="pos-product-category">' + p.category_name + '</div>' : '') +
                            '<div class="pos-product-stock ' + stockClass + '">' + stockText + '</div>' +
                        '</div>' +
                        '<div class="pos-product-price">' +
                            '<div class="pos-price-main">₱' + parseFloat(p.selling_price).toFixed(2) + '</div>' +
                            '<div class="pos-price-unit">per ' + (p.unit || 'pc') + '</div>' +
                        '</div>';
                    
                    div.onclick = function() { addToCart(p); };
                    container.appendChild(div);
                }
            });
        }
        
        function scanBarcode(){
            var term = document.getElementById('barcodeInput').value.trim();
            if (!term) return;
            var match = (products || []).find(function(p){ return p.barcode && String(p.barcode) === term; });
            if (match) {
                addToCart(match);
                document.getElementById('barcodeInput').value = '';
                if (typeof showToast === 'function') showToast('success', 'Added', match.name + ' added via barcode', 2000);
            } else {
                alert('No product found with barcode: ' + term);
            }
        }

        function searchProducts(){
            var term = document.getElementById('searchProduct').value.toLowerCase();
            var filtered = products.filter(function(p) { return p.name.toLowerCase().indexOf(term) !== -1 && p.stock_quantity > 0; });
            displayProducts(filtered);
        }
        
        function addToCart(product){
            var existing = cart.find(function(item) { return item.id === product.id; });
            if(existing){
                if(existing.qty < product.stock_quantity){ 
                    existing.qty++; 
                } else { 
                    alert('Not enough stock!'); 
                    return; 
                }
            } else {
                cart.push({
                    id: product.id, 
                    name: product.name, 
                    price: parseFloat(product.selling_price), 
                    qty: 1, 
                    unit: product.unit || 'pc',
                    stock: product.stock_quantity
                });
            }
            updateCartTotals();
            document.getElementById('searchProduct').value = '';
            if (currentCategory > 0) { 
                filterByCategory(currentCategory); 
            } else { 
                loadProducts(); 
            }
        }
        
        function removeFromCart(id){ 
            cart = cart.filter(function(item) { return item.id !== id; }); 
            updateCartTotals(); 
        }
        
        function updateQty(id, change){ 
            var item = cart.find(function(i) { return i.id === id; }); 
            if(item){ 
                item.qty += change; 
                if(item.qty <= 0){ 
                    removeFromCart(id); 
                    return; 
                } 
                updateCartTotals(); 
            } 
        }
        
        function applyDiscount() {
            var amount = parseFloat(document.getElementById('discountAmount').value);
            var type = document.getElementById('discountType').value;
            if (!amount || amount <= 0) { 
                currentDiscount = 0;
                document.getElementById('discountDisplay').textContent = '';
                updateCartTotals();
                return;
            }
            
            var subtotal = 0;
            cart.forEach(function(item){ subtotal += item.price * item.qty; });
            
            if (type === 'percent') {
                currentDiscount = (amount / 100) * subtotal;
                if (currentDiscount > subtotal) currentDiscount = subtotal;
                document.getElementById('discountDisplay').textContent = '🏷️ ' + amount + '% discount applied (₱' + currentDiscount.toFixed(2) + ')';
            } else {
                currentDiscount = Math.min(amount, subtotal);
                document.getElementById('discountDisplay').textContent = '🏷️ ₱' + amount.toFixed(2) + ' discount applied';
            }
            currentDiscountType = type;
            updateCartTotals();
        }
        
        function updateCartTotals(){
            var container = document.getElementById('cartItems');
            container.innerHTML = '';
            var subtotal = 0, count = 0;
            
            cart.forEach(function(item){
                var itemTotal = item.price * item.qty;
                subtotal += itemTotal;
                count += item.qty;
                var div = document.createElement('div');
                div.className = 'pos-cart-item';
                div.innerHTML = 
                    '<div class="pos-cart-item-info">' +
                        '<div class="pos-cart-item-name">' + item.name + '</div>' +
                        '<div class="pos-cart-item-price">₱' + item.price.toFixed(2) + ' / ' + item.unit + '</div>' +
                    '</div>' +
                    '<div class="pos-cart-item-controls">' +
                        '<div class="pos-qty-control">' +
                            '<button type="button" onclick="updateQty(' + item.id + ',-1)">−</button>' +
                            '<span>' + item.qty + '</span>' +
                            '<button type="button" onclick="updateQty(' + item.id + ',1)">+</button>' +
                        '</div>' +
                        '<div class="pos-cart-item-total">₱' + itemTotal.toFixed(2) + '</div>' +
                        '<button type="button" class="pos-cart-item-remove" onclick="removeFromCart(' + item.id + ')" title="Remove">✕</button>' +
                    '</div>';
                container.appendChild(div);
            });
            
            if(cart.length === 0) {
                container.innerHTML = '<div class="pos-cart-empty"><div class="pos-cart-empty-icon">🛒</div><div class="pos-cart-empty-title">Cart is empty</div><div class="pos-cart-empty-text">Click products to add them</div></div>';
            }
            
            var effectiveDiscount = currentDiscount;
            var totalAfterDiscount = Math.max(0, Math.round((subtotal - effectiveDiscount) * 100) / 100);
            
            var taxRate = <?php echo getSetting('tax_rate', 12); ?>;
            var tax = Math.round((totalAfterDiscount * ((taxRate / 100) / (1 + taxRate / 100))) * 100) / 100;
            var total = totalAfterDiscount;
            
            currentSubtotal = subtotal;
            currentTax = tax;
            currentTotal = total;
            
            document.getElementById('cartCount').textContent = count + (count === 1 ? ' item' : ' items');
            document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
            document.getElementById('discountTotal').textContent = '-₱' + effectiveDiscount.toFixed(2);
            document.getElementById('tax').textContent = '₱' + tax.toFixed(2);
            document.getElementById('total').textContent = '₱' + total.toFixed(2);
            
            checkLoyaltyPoints();
        }
        
        function showPaymentModal(){
            currentTotal = Math.round(currentTotal * 100) / 100;
            if(cart.length === 0){ alert('Cart is empty!'); return; }
            document.getElementById('paySubtotal').textContent = '₱' + currentSubtotal.toFixed(2);
            var effectiveDiscount = currentDiscount;
            document.getElementById('payDiscount').textContent = '-₱' + effectiveDiscount.toFixed(2);
            document.getElementById('payTax').textContent = '₱' + currentTax.toFixed(2);
            document.getElementById('payLoyalty').textContent = currentLoyaltyPointsUsed + ' pts';
            document.getElementById('payTotal').textContent = '₱' + currentTotal.toFixed(2);
            document.getElementById('amountReceived').value = '';
            document.getElementById('changeAmount').textContent = '₱0.00';
            document.getElementById('changeDisplay').className = 'pos-change-display';
            document.getElementById('paymentModal').classList.add('show');
            setTimeout(function(){ document.getElementById('amountReceived').focus(); }, 300);
        }
        
        function calculateChange() {
            var amountReceived = parseFloat(document.getElementById('amountReceived').value) || 0;
            var total = parseFloat(currentTotal.toFixed(2));
            var change = Math.round((amountReceived - total) * 100) / 100;
            var changeDisplay = document.getElementById('changeDisplay');
            var changeAmount = document.getElementById('changeAmount');
            
            if (amountReceived === 0) {
                changeDisplay.className = 'pos-change-display';
                changeAmount.textContent = '₱0.00';
                return;
            }
            
            if (change >= 0) {
                changeDisplay.className = 'pos-change-display pos-change-positive';
                changeAmount.textContent = '₱' + change.toFixed(2);
            } else {
                changeDisplay.className = 'pos-change-display pos-change-negative';
                changeAmount.textContent = 'Short: ₱' + Math.abs(change).toFixed(2);
            }
        }
        
        function quickAmount(amount){
            document.getElementById('amountReceived').value = amount;
            calculateChange();
        }
        
        function quickExact(){
            document.getElementById('amountReceived').value = currentTotal;
            calculateChange();
        }
        
        function toggleCardTokens(){
            var method = document.getElementById('paymentMethod').value;
            document.getElementById('cardTokenRow').style.display = (method === 'card') ? 'block' : 'none';
            if (method === 'card') loadCardTokens();
        }

        function loadCardTokens(){
            fetch('?action=get_payment_tokens').then(function(r){return r.json();}).then(function(rows){
                var sel = document.getElementById('cardTokenSelect');
                sel.innerHTML = '';
                rows.filter(function(t){ return t.active == 1; }).forEach(function(t){
                    var o = document.createElement('option');
                    o.value = t.id;
                    o.textContent = t.card_brand + ' ****' + t.last4 + (t.customer_name ? ' (' + t.customer_name + ')' : '');
                    sel.appendChild(o);
                });
                if (!sel.options.length) {
                    var o = document.createElement('option');
                    o.value = ''; o.textContent = 'No saved tokens - tokenize a card first';
                    sel.appendChild(o);
                }
            });
        }

        function processPayment(){
            var paymentMethod = document.getElementById('paymentMethod') ? document.getElementById('paymentMethod').value : 'cash';
            var paymentTokenId = (paymentMethod === 'card' && document.getElementById('cardTokenSelect')) ? document.getElementById('cardTokenSelect').value : '';
            var amountReceived = Math.round((parseFloat(document.getElementById('amountReceived').value) || 0) * 100) / 100;
            var total = currentTotal;

            if (paymentMethod === 'card') { amountReceived = total; }

            // Validation
            if(paymentMethod === 'card' && !paymentTokenId){
                showToast('warning', 'No Card Token', 'Save a card token on the Card Tokens page first.', 3000);
                return;
            }
            if(amountReceived === 0 && paymentMethod !== 'card'){
                showToast('warning', 'Missing Amount', 'Please enter the amount received.', 3000);
                return;
            }
            if(amountReceived < total && paymentMethod !== 'card'){
                showToast('error', 'Insufficient Payment', 'Amount received is less than the total due.', 3000);
                return;
            }

            var change = Math.round((amountReceived - total) * 100) / 100;
            var customerId = document.getElementById('customerSelect').value;
            var effectiveDiscount = currentDiscount;
            
            // Build a beautiful confirmation modal
            showConfirm({
                title: 'Confirm Payment',
                message: 'Process this transaction?\n\nAmount: ₱' + amountReceived.toFixed(2) + '\nChange: ₱' + change.toFixed(2),
                confirmText: 'Confirm Payment',
                variant: 'success',
                onConfirm: function() {
                    // Move the actual fetch logic inside the callback
                    var data = new FormData();
                    data.append('items', JSON.stringify(cart));
                    data.append('total', Math.round(currentSubtotal * 100) / 100);
                    data.append('customer_id', customerId);
                    data.append('amount_paid', amountReceived);
                    data.append('change', change);
                    data.append('discount', effectiveDiscount);
                    data.append('loyalty_points_used', currentLoyaltyPointsUsed);
                    data.append('payment_method', paymentMethod);
                    if (paymentTokenId) data.append('payment_token_id', paymentTokenId);

                    var btn = document.querySelector('.pos-btn-confirm');
                    var originalText = btn.innerHTML;
                    btn.innerHTML = 'Processing...';
                    btn.disabled = true;
                    
                    fetch('?action=create_sale', { method: 'POST', body: data })
                    .then(function(res) { return res.json(); })
                    .then(function(result){
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        if(result.success){
                            closeModal('paymentModal');
                            showReceipt(result.sale_id);
                            cart = [];
                            currentDiscount = 0;
                            currentLoyaltyPointsUsed = 0;
                            document.getElementById('discountDisplay').textContent = '';
                            document.getElementById('discountAmount').value = '';
                            updateCartTotals();
                            loadProducts();
                            loadCustomers();
                        } else {
                            showToast('error', 'Sale Failed', result.message, 4000);
                        }
                    })
                    .catch(function(error){
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        showToast('error', 'Network Error', error.message, 4000);
                    });
                }
            });
        }
        
        function showReceipt(saleId){
            fetch('?action=get_sale&id=' + saleId)
                .then(function(res) { return res.json(); })
                .then(function(sale){
                    var container = document.getElementById('receiptContent');
                    var itemsHtml = sale.items.map(function(item) { 
                        return '<div class="pos-receipt-item">' +
                            '<div class="pos-receipt-item-name">' + item.product_name + '</div>' +
                            '<div class="pos-receipt-item-row">' +
                                '<span>' + item.quantity + ' × ₱' + parseFloat(item.unit_price).toFixed(2) + '</span>' +
                                '<span>₱' + parseFloat(item.total_price).toFixed(2) + '</span>' +
                            '</div>' +
                        '</div>';
                    }).join('');
                    
                    var taxRate = <?php echo getSetting('tax_rate', 12); ?>;
                    var vatAmount = parseFloat(sale.tax).toFixed(2);
                    var vatableSales = parseFloat(sale.subtotal).toFixed(2);
                    var totalAmount = parseFloat(sale.total_amount).toFixed(2);
                    var grossSales = sale.items.reduce(function(sum, item) {
                        return sum + parseFloat(item.total_price || 0);
                    }, 0);
                    var amountPaid = parseFloat(sale.amount_paid || sale.total_amount).toFixed(2);
                    var changeAmount = parseFloat(sale.change_amount || 0).toFixed(2);
                    var discountAmount = parseFloat(sale.discount_amount || 0).toFixed(2);
                    var loyaltyPoints = parseFloat(sale.loyalty_points_used || 0).toFixed(0);
                    var storeAddress = '<?php echo addslashes(getSetting('store_address', '')); ?>';
                    var storeContact = '<?php echo addslashes(getSetting('store_contact', '')); ?>';
                    var vatReg = '<?php echo addslashes(getSetting('vat_reg_number', '')); ?>';
                    var storeName = '<?php echo addslashes($store_name); ?>';
                    
                    container.innerHTML = 
                        '<div class="pos-receipt" id="printArea">' +
                            '<div class="pos-receipt-store">' + storeName + '</div>' +
                            (storeAddress ? '<div class="pos-receipt-address">' + storeAddress + '</div>' : '') +
                            (storeContact ? '<div class="pos-receipt-contact">📞 ' + storeContact + '</div>' : '') +
                            '<div class="pos-receipt-divider"></div>' +
                            '<div class="pos-receipt-meta">' +
                                '<div>' + new Date().toLocaleString() + '</div>' +
                                '<div>Invoice: ' + sale.invoice_number + '</div>' +
                            '</div>' +
                            '<div class="pos-receipt-divider"></div>' +
                            '<div class="pos-receipt-items">' + itemsHtml + '</div>' +
                            '<div class="pos-receipt-divider"></div>' +
                            '<div class="pos-receipt-row"><span>Subtotal (VAT inclusive)</span><span>₱' + grossSales.toFixed(2) + '</span></div>' +
                            (discountAmount > 0 ? '<div class="pos-receipt-row"><span>Less: discount</span><span>-₱' + discountAmount + '</span></div>' : '') +
                            (loyaltyPoints > 0 ? '<div class="pos-receipt-row"><span>Loyalty points used</span><span>' + loyaltyPoints + ' pts</span></div>' : '') +
                            '<div class="pos-receipt-row pos-receipt-total"><span>TOTAL DUE</span><span>₱' + totalAmount + '</span></div>' +
                            '<div class="pos-receipt-divider"></div>' +
                            '<div class="pos-receipt-vat-summary">' +
                                '<div class="pos-receipt-vat-heading">VAT SUMMARY</div>' +
                                '<table><tbody>' +
                                    '<tr><td>VATable sales</td><td colspan="2">₱' + vatableSales + '</td></tr>' +
                                    '<tr><td>VAT (' + taxRate + '% included)</td><td colspan="2">₱' + vatAmount + '</td></tr>' +
                                    '<tr><td>Total sales</td><td colspan="2">₱' + totalAmount + '</td></tr>' +
                                '</tbody></table>' +
                                (vatReg ? '<div class="pos-receipt-vat-number">VAT REG. NO.: ' + vatReg + '</div>' : '') +
                            '</div>' +
                            '<div class="pos-receipt-divider"></div>' +
                            '<div class="pos-receipt-row"><span>Amount Paid</span><span>₱' + amountPaid + '</span></div>' +
                            '<div class="pos-receipt-row"><span>Change</span><span>₱' + changeAmount + '</span></div>' +
                            '<div class="pos-receipt-divider"></div>' +
                            '<div class="pos-receipt-footer-meta">' +
                                'Cashier: ' + sale.cashier +
                                (sale.customer ? '<br>Customer: ' + sale.customer : '') +
                            '</div>' +
                            '<div class="pos-receipt-footer">Thank you for shopping!</div>' +
                        '</div>';
                    document.getElementById('receiptModal').classList.add('show');
                });
        }
        
        function showAddCustomer(){ document.getElementById('addCustomerModal').classList.add('show'); }
        
        function saveCustomer(e){
            e.preventDefault();
            var name = document.getElementById('custName').value.trim();
            var email = document.getElementById('custEmail').value;
            var phone = document.getElementById('custPhone').value.replace(/\D/g, '');
            if(!name) return alert('Name is required');
            if (phone.length > 0 && phone.length !== 11) { alert('⚠️ Phone number must be exactly 11 digits!'); return; }
            var data = new FormData();
            data.append('name', name);
            data.append('email', email);
            data.append('phone', phone);
            fetch('?action=add_customer', { method: 'POST', body: data })
            .then(function(res) { return res.json(); })
            .then(function(result){
                if(result.success){
                    alert('Customer added!');
                    closeModal('addCustomerModal');
                    document.getElementById('custName').value = '';
                    document.getElementById('custEmail').value = '';
                    document.getElementById('custPhone').value = '';
                    document.getElementById('custPhoneError').textContent = '';
                    loadCustomers();
                }
            });
        }
        
        function closeModal(id){ document.getElementById(id).classList.remove('show'); }
        
        loadProducts();
        loadCustomers();
        
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Enter' && document.getElementById('paymentModal').classList.contains('show')){
                if(document.activeElement === document.getElementById('amountReceived')){
                    processPayment();
                }
            }
        });
    </script>
    
    <?php
break;
endswitch;
