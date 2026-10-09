<?php
switch ('stock'):
case 'stock':

                    if (!canAccess('stock')) {
                        echo '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;">
                            <div style="font-size:4rem;margin-bottom:1rem;">⛔</div>
                            <h2 style="color:#111827;">Access Denied</h2>
                            <p style="color:#6B7280;">You do not have permission to access this page.</p>
                            <a href="?page=dashboard" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Go to Dashboard</a>
                        </div>';
                        break;
                    }
                    
                    // Fetch all products with stock info
                    $stmt = $pdo->query("
                        SELECT p.*, c.name as category_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.archived = 0
                        ORDER BY p.name
                    ");
                    $stockProducts = $stmt->fetchAll();
                    
                    // Stats
                    $inStock = 0;
                    $lowStock = 0;
                    $outOfStock = 0;
                    $totalValue = 0;
                    
                    foreach ($stockProducts as $p) {
                        $threshold = $p['low_stock_threshold'] ?? 5;
                        if ($p['stock_quantity'] == 0) $outOfStock++;
                        elseif ($p['stock_quantity'] <= $threshold) $lowStock++;
                        else $inStock++;
                        $totalValue += $p['stock_quantity'] * ($p['cost_price'] ?? 0);
                    }
                    ?>
                    
                    <!-- Page Header -->
                    <div class="inv-page-header">
                        <div class="inv-page-header-left">
                            <h2 class="inv-page-title">
                                <span class="inv-page-icon">📊</span>
                                Stock Management
                            </h2>
                            <p class="inv-page-subtitle">Monitor, add, and adjust inventory levels</p>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <a href="?page=inventory_reports" class="inv-btn-secondary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                                View Reports
                            </a>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="inv-stats-grid">
                        
                        <div class="inv-stat-card">
                            <div class="inv-stat-header">
                                <div class="inv-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                </div>
                                <div style="background:#ECFDF5;color:#059669;padding:4px 10px;border-radius:20px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;">HEALTHY</div>
                            </div>
                            <div class="inv-stat-label">In Stock</div>
                            <div class="inv-stat-value success"><?php echo $inStock; ?></div>
                            <div class="inv-stat-footer">Products available for sale</div>
                        </div>
                        
                        <div class="inv-stat-card">
                            <div class="inv-stat-header">
                                <div class="inv-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                        <line x1="12" y1="9" x2="12" y2="13"></line>
                                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                    </svg>
                                </div>
                                <div style="background:#FFFBEB;color:#D97706;padding:4px 10px;border-radius:20px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;">REORDER</div>
                            </div>
                            <div class="inv-stat-label">Low Stock</div>
                            <div class="inv-stat-value warning"><?php echo $lowStock; ?></div>
                            <div class="inv-stat-footer">Below threshold level</div>
                        </div>
                        
                        <div class="inv-stat-card">
                            <div class="inv-stat-header">
                                <div class="inv-stat-icon" style="background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                </div>
                                <div style="background:#FEF2F2;color:#DC2626;padding:4px 10px;border-radius:20px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;">URGENT</div>
                            </div>
                            <div class="inv-stat-label">Out of Stock</div>
                            <div class="inv-stat-value danger"><?php echo $outOfStock; ?></div>
                            <div class="inv-stat-footer">Unavailable for sale</div>
                        </div>
                        
                        <div class="inv-stat-card">
                            <div class="inv-stat-header">
                                <div class="inv-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                    </svg>
                                </div>
                                <div style="background:#EEF2FF;color:#4F46E5;padding:4px 10px;border-radius:20px;font-size:10.5px;font-weight:700;letter-spacing:0.04em;">AT COST</div>
                            </div>
                            <div class="inv-stat-label">Inventory Value</div>
                            <div class="inv-stat-value primary">₱<?php echo number_format($totalValue, 2); ?></div>
                            <div class="inv-stat-footer">Total stock value</div>
                        </div>
                        
                    </div>
                    
                    <!-- Products Table -->
                    <div class="inv-card">
                        
                        <div class="inv-card-header">
                            <div class="inv-card-title-wrap">
                                <div class="inv-card-title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    </svg>
                                    All Products
                                </div>
                                <div class="inv-card-subtitle">
                                    <strong id="stockCount"><?php echo count($stockProducts); ?></strong> products in inventory
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filters -->
                        <div class="inv-filter-bar">
                            <!-- Search input -->
                            <div class="inv-search-wrap">
                                <span class="inv-search-icon">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                </span>
                                <input type="text" id="stockSearch" placeholder="Search products..." onkeyup="filterStock()" class="inv-search-input">
                            </div>
                            
                            <!-- ⭐ NEW: Category Filter -->
                            <select id="stockCategoryFilter" onchange="filterStock()" class="inv-filter-select">
                                <option value="all">📁 All Categories</option>
                                <?php 
                                $catList = $pdo->query("SELECT id, name FROM categories ORDER BY name");
                                foreach ($catList as $c): 
                                ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <!-- Status filter -->
                            <select id="stockFilter" onchange="filterStock()" class="inv-filter-select">
                                <option value="all">All Status</option>
                                <option value="in">✅ In Stock</option>
                                <option value="low">⚠️ Low Stock</option>
                                <option value="out">🚫 Out of Stock</option>
                            </select>
                            
                            <!-- Clear button -->
                            <button type="button" onclick="clearStockFilters()" class="inv-clear-filter-btn" title="Clear all filters">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                                Clear
                            </button>
                        </div>
                        
                        <!-- Table -->
                        <?php if (!empty($stockProducts)): ?>
                        <div class="inv-table-wrap">
                            <table class="inv-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Stock Level</th>
                                        <th>Status</th>
                                        <th>Cost</th>
                                        <th>Value</th>
                                        <th class="no-print" style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="stockTableBody">
                                    <?php foreach ($stockProducts as $p): 
                                        $threshold = $p['low_stock_threshold'] ?? 5;
                                        $status = 'in';
                                        $statusLabel = 'In Stock';
                                        $statusClass = 'ok';
                                        
                                        if ($p['stock_quantity'] == 0) {
                                            $status = 'out';
                                            $statusLabel = 'Out of Stock';
                                            $statusClass = 'out';
                                        } elseif ($p['stock_quantity'] <= $threshold) {
                                            $status = 'low';
                                            $statusLabel = 'Low Stock';
                                            $statusClass = 'low';
                                        }
                                        
                                        $value = $p['stock_quantity'] * ($p['cost_price'] ?? 0);
                                    ?>
                                    <tr data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>" 
                                        data-stock="<?php echo $p['stock_quantity']; ?>"
                                        data-status="<?php echo $status; ?>"
                                        data-category="<?php echo $p['category_id']; ?>">
                                        <td>
                                            <div class="inv-product-cell">
                                                <div class="inv-product-image">
                                                    <?php if (!empty($p['image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="">
                                                    <?php else: ?>
                                                        📦
                                                    <?php endif; ?>
                                                </div>
                                                <div class="inv-product-info">
                                                    <div class="inv-product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                                    <?php if (!empty($p['sku'])): ?>
                                                        <div class="inv-product-sku">SKU: <?php echo htmlspecialchars($p['sku']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="inv-category-badge"><?php echo htmlspecialchars($p['category_name'] ?? 'Uncategorized'); ?></span>
                                        </td>
                                        <td>
                                            <span class="inv-stock-number"><?php echo number_format($p['stock_quantity']); ?></span>
                                            <span style="color:#9CA3AF;font-size:11.5px;margin-left:4px;"><?php echo htmlspecialchars($p['unit'] ?? 'pc'); ?></span>
                                        </td>
                                        <td>
                                            <span class="inv-stock-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusLabel; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="inv-money <?php echo ($p['cost_price'] ?? 0) == 0 ? 'inv-money-muted' : ''; ?>">
                                                ₱<?php echo number_format($p['cost_price'] ?? 0, 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="inv-money">₱<?php echo number_format($value, 2); ?></span>
                                        </td>
                                        <td class="no-print" style="text-align:right;">
                                            <div class="inv-actions">
                                                <button class="inv-action-btn inv-action-add" onclick="showAddStock(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['stock_quantity']; ?>)" title="Add Stock">➕</button>
                                                <button class="inv-action-btn inv-action-adjust" onclick="showAdjustStock(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['stock_quantity']; ?>)" title="Adjust Stock">⚙️</button>
                                                <button class="inv-action-btn inv-action-view" onclick="viewStockHistory(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>')" title="History">📋</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="inv-empty-state">
                            <div class="inv-empty-icon">📦</div>
                            <div class="inv-empty-title">No products yet</div>
                            <div class="inv-empty-text">Add products from the Products page to start tracking stock</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ===== ADD STOCK MODAL ===== -->
                    <div class="modal" id="addStockModal">
                        <div class="modal-content inv-modal">
                            <div class="inv-modal-header">
                                <div class="inv-modal-header-icon green">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                </div>
                                <div class="inv-modal-header-text">
                                    <h2>Add Stock</h2>
                                    <p>Increase stock for this product</p>
                                </div>
                                <button class="inv-modal-close" onclick="closeModal('addStockModal')">&times;</button>
                            </div>
                            
                            <form onsubmit="saveStockAddition(event)">
                                <input type="hidden" id="addStockProductId">
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Product</label>
                                    <input type="text" id="addStockProductName" disabled class="inv-form-input">
                                </div>
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Current Stock</label>
                                    <input type="text" id="addStockCurrent" disabled class="inv-form-input">
                                </div>
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Quantity to Add *</label>
                                    <input type="number" id="addStockQuantity" min="1" required placeholder="e.g., 10" class="inv-form-input">
                                </div>
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Reason</label>
                                    <select id="addStockReason" class="inv-form-select">
                                        <option value="Purchase from supplier">Purchase from supplier</option>
                                        <option value="Customer return">Customer return</option>
                                        <option value="Found extra stock">Found extra stock</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Notes (Optional)</label>
                                    <textarea id="addStockNotes" rows="2" placeholder="e.g., Reference number, remarks..." class="inv-form-textarea"></textarea>
                                </div>
                                
                                <div class="inv-modal-actions">
                                    <button type="button" class="inv-btn-cancel" onclick="closeModal('addStockModal')">Cancel</button>
                                    <button type="submit" class="inv-btn-submit green">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        Add Stock
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- ===== ADJUST STOCK MODAL ===== -->
                    <div class="modal" id="adjustStockModal">
                        <div class="modal-content inv-modal">
                            <div class="inv-modal-header">
                                <div class="inv-modal-header-icon amber">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                    </svg>
                                </div>
                                <div class="inv-modal-header-text">
                                    <h2>Adjust Stock</h2>
                                    <p>Remove or correct stock quantity</p>
                                </div>
                                <button class="inv-modal-close" onclick="closeModal('adjustStockModal')">&times;</button>
                            </div>
                            
                            <form onsubmit="saveStockAdjustment(event)">
                                <input type="hidden" id="adjustStockProductId">
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Product</label>
                                    <input type="text" id="adjustStockProductName" disabled class="inv-form-input">
                                </div>
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Current Stock</label>
                                    <input type="text" id="adjustStockCurrent" disabled class="inv-form-input">
                                </div>
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Adjustment Type</label>
                                    <select id="adjustStockType" class="inv-form-select">
                                        <option value="damage">Damage / Broken</option>
                                        <option value="lost">Lost / Missing</option>
                                        <option value="correction">Correction (wrong count)</option>
                                        <option value="expired">Expired</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Quantity to Remove *</label>
                                    <input type="number" id="adjustStockQuantity" min="1" required placeholder="e.g., 3" class="inv-form-input">
                                </div>
                                
                                <div class="inv-form-group">
                                    <label class="inv-form-label">Notes</label>
                                    <textarea id="adjustStockNotes" rows="2" placeholder="Reason details..." class="inv-form-textarea"></textarea>
                                </div>
                                
                                <div class="inv-modal-actions">
                                    <button type="button" class="inv-btn-cancel" onclick="closeModal('adjustStockModal')">Cancel</button>
                                    <button type="submit" class="inv-btn-submit amber">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        Apply Adjustment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- ===== STOCK HISTORY MODAL ===== -->
                    <div class="modal" id="stockHistoryModal">
                        <div class="modal-content inv-modal" style="max-width:800px;">
                            <div class="inv-modal-header">
                                <div class="inv-modal-header-icon purple">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                </div>
                                <div class="inv-modal-header-text">
                                    <h2 id="stockHistoryTitle">Stock History</h2>
                                    <p>All movements for this product</p>
                                </div>
                                <button class="inv-modal-close" onclick="closeModal('stockHistoryModal')">&times;</button>
                            </div>
                            
                            <div id="stockHistoryContent">
                                <div style="text-align:center;padding:2rem;color:#9CA3AF;">
                                    <div style="display:inline-block;width:36px;height:36px;border:3px solid #E5E7EB;border-top-color:#6366F1;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                                    <div style="margin-top:12px;font-size:13px;font-weight:600;">Loading...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                    function filterStock() {
                        var search = (document.getElementById('stockSearch').value || '').toLowerCase();
                        var statusFilter = document.getElementById('stockFilter').value;
                        var categoryFilter = document.getElementById('stockCategoryFilter').value;
                        var rows = document.querySelectorAll('#stockTableBody tr');
                        
                        var visibleCount = 0;
                        
                        rows.forEach(function(row) {
                            var name = row.getAttribute('data-name') || '';
                            var status = row.getAttribute('data-status') || '';
                            var category = row.getAttribute('data-category') || '';
                            
                            var matchesSearch = search === '' || name.indexOf(search) !== -1;
                            var matchesStatus = statusFilter === 'all' || status === statusFilter;
                            var matchesCategory = categoryFilter === 'all' || category === categoryFilter;
                            
                            var show = matchesSearch && matchesStatus && matchesCategory;
                            row.style.display = show ? '' : 'none';
                            if (show) visibleCount++;
                        });
                        
                        // Update the count shown in the header
                        var countEl = document.getElementById('stockCount');
                        if (countEl) {
                            countEl.textContent = visibleCount;
                        }
                    }

                    function clearStockFilters() {
                        document.getElementById('stockSearch').value = '';
                        document.getElementById('stockFilter').value = 'all';
                        document.getElementById('stockCategoryFilter').value = 'all';
                        filterStock();
                    }
                    
                    function showAddStock(id, name, current) {
                        document.getElementById('addStockProductId').value = id;
                        document.getElementById('addStockProductName').value = name;
                        document.getElementById('addStockCurrent').value = current;
                        document.getElementById('addStockQuantity').value = '';
                        document.getElementById('addStockNotes').value = '';
                        document.getElementById('addStockModal').classList.add('show');
                        setTimeout(function(){ document.getElementById('addStockQuantity').focus(); }, 200);
                    }
                    
                    function showAdjustStock(id, name, current) {
                        document.getElementById('adjustStockProductId').value = id;
                        document.getElementById('adjustStockProductName').value = name;
                        document.getElementById('adjustStockCurrent').value = current;
                        document.getElementById('adjustStockQuantity').value = '';
                        document.getElementById('adjustStockNotes').value = '';
                        document.getElementById('adjustStockModal').classList.add('show');
                        setTimeout(function(){ document.getElementById('adjustStockQuantity').focus(); }, 200);
                    }
                    
                    function saveStockAddition(e) {
                        e.preventDefault();
                        var data = new FormData();
                        data.append('product_id', document.getElementById('addStockProductId').value);
                        data.append('quantity', document.getElementById('addStockQuantity').value);
                        data.append('reason', document.getElementById('addStockReason').value);
                        data.append('notes', document.getElementById('addStockNotes').value);
                        
                        fetch('?action=add_stock', { method: 'POST', body: data })
                        .then(function(res) { return res.json(); })
                        .then(function(result) {
                            if (result.success) {
                                if (window.showToast) showToast('success', 'Stock Added', 'New stock: ' + result.new_stock, 3000);
                                closeModal('addStockModal');
                                setTimeout(function(){ location.reload(); }, 800);
                            } else {
                                alert('❌ ' + result.message);
                            }
                        });
                    }
                    
                    function saveStockAdjustment(e) {
                        e.preventDefault();
                        var data = new FormData();
                        data.append('product_id', document.getElementById('adjustStockProductId').value);
                        data.append('quantity', document.getElementById('adjustStockQuantity').value);
                        data.append('type', document.getElementById('adjustStockType').value);
                        data.append('notes', document.getElementById('adjustStockNotes').value);
                        
                        fetch('?action=adjust_stock', { method: 'POST', body: data })
                        .then(function(res) { return res.json(); })
                        .then(function(result) {
                            if (result.success) {
                                if (window.showToast) showToast('warning', 'Stock Adjusted', 'New stock: ' + result.new_stock, 3000);
                                closeModal('adjustStockModal');
                                setTimeout(function(){ location.reload(); }, 800);
                            } else {
                                alert('❌ ' + result.message);
                            }
                        });
                    }
                    
                    function viewStockHistory(id, name) {
                        document.getElementById('stockHistoryTitle').textContent = 'Stock History';
                        document.getElementById('stockHistoryContent').innerHTML = '<div style="text-align:center;padding:2rem;color:#9CA3AF;"><div style="display:inline-block;width:36px;height:36px;border:3px solid #E5E7EB;border-top-color:#6366F1;border-radius:50%;animation:spin 0.8s linear infinite;"></div><div style="margin-top:12px;font-size:13px;font-weight:600;">Loading...</div></div>';
                        document.getElementById('stockHistoryModal').classList.add('show');
                        
                        fetch('?action=get_stock_history&product_id=' + id)
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            if (!data || data.length === 0) {
                                document.getElementById('stockHistoryContent').innerHTML = '<div class="inv-empty-state" style="padding:40px;"><div class="inv-empty-icon" style="font-size:42px;">📋</div><div class="inv-empty-title" style="font-size:15px;">No movements yet</div><div class="inv-empty-text" style="font-size:12.5px;">Stock changes will appear here</div></div>';
                                return;
                            }
                            
                            var html = '<div class="inv-table-wrap"><table class="inv-table"><thead><tr>';
                            html += '<th>Date</th><th>Type</th><th>Qty</th><th>Before</th><th>After</th><th>Reason</th>';
                            html += '</tr></thead><tbody>';
                            
                            data.forEach(function(m) {
                                var qtyClass = m.quantity > 0 ? 'inv-stock-badge ok' : 'inv-stock-badge out';
                                var qtySign = m.quantity > 0 ? '+' : '';
                                var typeLabel = m.movement_type.charAt(0).toUpperCase() + m.movement_type.slice(1);
                                
                                html += '<tr>';
                                html += '<td><span style="font-size:12.5px;color:#6B7280;">' + new Date(m.created_at).toLocaleString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit'}) + '</span></td>';
                                html += '<td><span class="inv-category-badge">' + typeLabel + '</span></td>';
                                html += '<td><span class="' + qtyClass + '">' + qtySign + m.quantity + '</span></td>';
                                html += '<td><span style="font-weight:600;color:#6B7280;">' + m.quantity_before + '</span></td>';
                                html += '<td><span style="font-weight:800;color:#111827;">' + m.quantity_after + '</span></td>';
                                html += '<td><span style="font-size:12px;color:#6B7280;">' + (m.reason || '—') + '</span></td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table></div>';
                            document.getElementById('stockHistoryContent').innerHTML = html;
                        });
                    }
                    
                    function closeModal(id) {
                        document.getElementById(id).classList.remove('show');
                    }
                    </script>
                    
                    <?php if (!empty($_GET['low'])): ?>
                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var stockFilterEl = document.getElementById('stockFilter');
                        if (stockFilterEl) {
                            stockFilterEl.value = 'low';
                            if (typeof filterStock === 'function') { filterStock(); }
                        }
                    });
                    </script>
                    <?php endif; ?>
                    
                    <?php
                    // ============================================
                    // SUPPLIERS PAGE
                    // ============================================
break;
endswitch;
