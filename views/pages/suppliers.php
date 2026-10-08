<?php
switch ('suppliers'):
case 'suppliers':

                        if (!canAccess('suppliers')) {
                            echo '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;">
                                <div style="font-size:4rem;margin-bottom:1rem;">⛔</div>
                                <h2 style="color:#111827;">Access Denied</h2>
                                <a href="?page=dashboard" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Go to Dashboard</a>
                            </div>';
                            break;
                        }
                        
                        $suppliers = $pdo->query("
                            SELECT s.*, 
                                (SELECT COUNT(*) FROM purchases WHERE supplier_id = s.id) +
                                (SELECT COUNT(*) FROM procurement_purchase_orders po
                                 WHERE po.supplier_id = s.id
                                 AND po.status IN ('ordered','sent','acknowledged','partially_received','delivered','received','closed')) as purchase_count,
                                (SELECT COALESCE(SUM(total_amount), 0) FROM purchases WHERE supplier_id = s.id) +
                                (SELECT COALESCE(SUM(
                                    CASE
                                        WHEN po.status IN ('received','closed','delivered') THEN po.total_amount
                                        WHEN po.status = 'partially_received' THEN po.total_amount * (
                                            SELECT COALESCE(
                                                SUM(pi.quantity_received * pi.unit_cost) / NULLIF(SUM(pi.quantity * pi.unit_cost), 0),
                                                0
                                            )
                                            FROM procurement_po_items pi WHERE pi.po_id = po.id
                                        )
                                        ELSE 0
                                    END
                                ), 0)
                                 FROM procurement_purchase_orders po
                                 WHERE po.supplier_id = s.id) as total_spent
                            FROM suppliers s 
                            ORDER BY s.name
                        ")->fetchAll();
                        
                        $activeSuppliers = 0;
                        $totalPurchases = 0;
                        $totalSpent = 0;
                        foreach ($suppliers as $s) {
                            if ($s['status'] == 'active') $activeSuppliers++;
                            $totalPurchases += $s['purchase_count'];
                            $totalSpent += $s['total_spent'];
                        }
                        ?>
                        
                        <!-- Page Header -->
                        <div class="inv-page-header">
                            <div class="inv-page-header-left">
                                <h2 class="inv-page-title">
                                    <span class="inv-page-icon">🏢</span>
                                    Suppliers
                                </h2>
                                <p class="inv-page-subtitle">Manage vendors and their contact information</p>
                            </div>
                            <button class="inv-btn-primary" onclick="showAddSupplier()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Add Supplier
                            </button>
                        </div>
                        
                        <!-- Stats -->
                        <div class="inv-stats-grid">
                            <div class="inv-stat-card">
                                <div class="inv-stat-header">
                                    <div class="inv-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                </div>
                                <div class="inv-stat-label">Total Suppliers</div>
                                <div class="inv-stat-value primary"><?php echo count($suppliers); ?></div>
                                <div class="inv-stat-footer">In database</div>
                            </div>
                            
                            <div class="inv-stat-card">
                                <div class="inv-stat-header">
                                    <div class="inv-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                        </svg>
                                    </div>
                                </div>
                                <div class="inv-stat-label">Active</div>
                                <div class="inv-stat-value success"><?php echo $activeSuppliers; ?></div>
                                <div class="inv-stat-footer">Currently active</div>
                            </div>
                            
                            <div class="inv-stat-card">
                                <div class="inv-stat-header">
                                    <div class="inv-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="9" cy="21" r="1"></circle>
                                            <circle cx="20" cy="21" r="1"></circle>
                                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="inv-stat-label">Total Purchases</div>
                                <div class="inv-stat-value warning"><?php echo number_format($totalPurchases); ?></div>
                                <div class="inv-stat-footer">All-time POs</div>
                            </div>
                            
                            <div class="inv-stat-card">
                                <div class="inv-stat-header">
                                    <div class="inv-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="12" y1="1" x2="12" y2="23"></line>
                                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="inv-stat-label">Total Spent</div>
                                <div class="inv-stat-value primary">₱<?php echo number_format($totalSpent, 2); ?></div>
                                <div class="inv-stat-footer">On purchases</div>
                            </div>
                        </div>
                        
                        <!-- Suppliers Table -->
                        <div class="inv-card">
                            
                            <div class="inv-card-header">
                                <div class="inv-card-title-wrap">
                                    <div class="inv-card-title">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                        </svg>
                                        All Suppliers
                                    </div>
                                    <div class="inv-card-subtitle">
                                        <strong><?php echo count($suppliers); ?></strong> suppliers
                                    </div>
                                </div>
                            </div>
                            
                            <div class="inv-filter-bar">
                                <div class="inv-search-wrap">
                                    <span class="inv-search-icon">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                    </span>
                                    <input type="text" id="supplierSearch" placeholder="Search suppliers..." onkeyup="filterSuppliers()" class="inv-search-input">
                                </div>
                                <select id="supplierFilter" onchange="filterSuppliers()" class="inv-filter-select">
                                    <option value="all">All Status</option>
                                    <option value="active">✅ Active</option>
                                    <option value="inactive">❌ Inactive</option>
                                </select>
                            </div>
                            
                            <?php if (!empty($suppliers)): ?>
                            <div class="inv-table-wrap">
                                <table class="inv-table">
                                    <thead>
                                        <tr>
                                            <th>Supplier</th>
                                            <th>Source Type</th>
                                            <th>Contact Person</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Purchases</th>
                                            <th>Total Spent</th>
                                            <th>Status</th>
                                            <th class="no-print" style="text-align:right;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="supplierTableBody">
                                        <?php foreach ($suppliers as $s): ?>
                                        <tr data-name="<?php echo strtolower(htmlspecialchars($s['name'])); ?>"
                                            data-status="<?php echo $s['status']; ?>">
                                            <td>
                                                <div class="inv-product-cell">
                                                    <div class="inv-product-image" style="background:linear-gradient(135deg,#EEF2FF,#E0E7FF);color:#4F46E5;font-weight:800;font-size:16px;">
                                                        <?php echo strtoupper(substr($s['name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="inv-product-info">
                                                        <div class="inv-product-name"><?php echo htmlspecialchars($s['name']); ?></div>
                                                        <?php if (!empty($s['address'])): ?>
                                                            <div class="inv-product-sku"><?php echo htmlspecialchars(substr($s['address'], 0, 40)) . (strlen($s['address']) > 40 ? '...' : ''); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $s['supplier_type'] ?? 'other_source'))); ?></td>
                                            <td><?php echo htmlspecialchars($s['contact_person'] ?? '—'); ?></td>
                                            <td>
                                                <?php if (!empty($s['phone'])): ?>
                                                    <span class="inv-money"><?php echo htmlspecialchars($s['phone']); ?></span>
                                                <?php else: ?>
                                                    <span class="inv-money-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($s['email'])): ?>
                                                    <span style="font-size:12.5px;color:#4F46E5;font-weight:500;"><?php echo htmlspecialchars($s['email']); ?></span>
                                                <?php else: ?>
                                                    <span class="inv-money-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="inv-stock-number"><?php echo $s['purchase_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="inv-money">₱<?php echo number_format($s['total_spent'], 2); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($s['status'] == 'active'): ?>
                                                    <span class="inv-stock-badge ok">Active</span>
                                                <?php else: ?>
                                                    <span class="inv-stock-badge out">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="no-print" style="text-align:right;">
                                                <div class="inv-actions">
                                                    <button class="inv-action-btn inv-action-edit" onclick='editSupplier(<?php echo json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Edit">✏️</button>
                                                    <button class="inv-action-btn inv-action-delete" onclick="deleteSupplier(<?php echo $s['id']; ?>)" title="Delete">🗑️</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="inv-empty-state">
                                <div class="inv-empty-icon">🏢</div>
                                <div class="inv-empty-title">No suppliers yet</div>
                                <div class="inv-empty-text">Click "Add Supplier" to register your first vendor</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ===== ADD/EDIT SUPPLIER MODAL ===== -->
                        <div class="modal" id="supplierModal">
                            <div class="modal-content inv-modal">
                                <div class="inv-modal-header">
                                    <div class="inv-modal-header-icon purple">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                        </svg>
                                    </div>
                                    <div class="inv-modal-header-text">
                                        <h2 id="supplierModalTitle">Add Supplier</h2>
                                        <p>Supplier information and contact</p>
                                    </div>
                                    <button class="inv-modal-close" onclick="closeModal('supplierModal')">&times;</button>
                                </div>
                                
                                <form onsubmit="saveSupplier(event)">
                                    <input type="hidden" id="supplierId">
                                    
                                    <div class="inv-form-group">
                                        <label class="inv-form-label">Supplier Name *</label>
                                        <input type="text" id="supplierName" required placeholder="e.g., ABC Trading Corp." class="inv-form-input">
                                    </div>
                                    
                                    <div class="inv-form-group">
                                        <label class="inv-form-label">Supplier Type</label>
                                        <select id="supplierType" class="inv-form-select">
                                            <option value="direct_supplier">Direct Supplier</option>
                                            <option value="distributor">Distributor</option>
                                            <option value="wholesaler">Wholesaler</option>
                                            <option value="other_source">Other Source</option>
                                        </select>
                                    </div>

                                    <div class="inv-form-group">
                                        <label class="inv-form-label">Contact Person</label>
                                        <input type="text" id="supplierContact" placeholder="Full name" class="inv-form-input">
                                    </div>
                                    
                                    <div class="inv-form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                        <div>
                                            <label class="inv-form-label">Phone</label>
                                            <input type="text" id="supplierPhone" placeholder="09123456789" class="inv-form-input">
                                        </div>
                                        <div>
                                            <label class="inv-form-label">Email</label>
                                            <input type="email" id="supplierEmail" placeholder="contact@supplier.com" class="inv-form-input">
                                        </div>
                                    </div>
                                    
                                    <div class="inv-form-group">
                                        <label class="inv-form-label">Address</label>
                                        <textarea id="supplierAddress" rows="2" placeholder="Complete address..." class="inv-form-textarea"></textarea>
                                    </div>
                                    
                                    <div class="inv-form-group">
                                        <label class="inv-form-label">Status</label>
                                        <select id="supplierStatus" class="inv-form-select">
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="inv-form-group">
                                        <label class="inv-form-label">Notes</label>
                                        <textarea id="supplierNotes" rows="2" placeholder="Additional info..." class="inv-form-textarea"></textarea>
                                    </div>
                                    
                                    <div class="inv-modal-actions">
                                        <button type="button" class="inv-btn-cancel" onclick="closeModal('supplierModal')">Cancel</button>
                                        <button type="submit" class="inv-btn-submit">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            Save Supplier
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <script>
                        function filterSuppliers() {
                            var search = document.getElementById('supplierSearch').value.toLowerCase();
                            var filter = document.getElementById('supplierFilter').value;
                            var rows = document.querySelectorAll('#supplierTableBody tr');
                            rows.forEach(function(row) {
                                var name = row.getAttribute('data-name') || '';
                                var status = row.getAttribute('data-status') || '';
                                var show = (search === '' || name.indexOf(search) !== -1) && (filter === 'all' || filter === status);
                                row.style.display = show ? '' : 'none';
                            });
                        }
                        
                        function showAddSupplier() {
                            document.getElementById('supplierModalTitle').textContent = 'Add Supplier';
                            document.getElementById('supplierId').value = '';
                            document.getElementById('supplierName').value = '';
                            document.getElementById('supplierContact').value = '';
                            document.getElementById('supplierPhone').value = '';
                            document.getElementById('supplierEmail').value = '';
                            document.getElementById('supplierAddress').value = '';
                            document.getElementById('supplierNotes').value = '';
                            document.getElementById('supplierStatus').value = 'active';
                            document.getElementById('supplierType').value = 'other_source';
                            document.getElementById('supplierModal').classList.add('show');
                        }
                        
                        function editSupplier(s) {
                            document.getElementById('supplierModalTitle').textContent = 'Edit Supplier';
                            document.getElementById('supplierId').value = s.id;
                            document.getElementById('supplierName').value = s.name || '';
                            document.getElementById('supplierContact').value = s.contact_person || '';
                            document.getElementById('supplierPhone').value = s.phone || '';
                            document.getElementById('supplierEmail').value = s.email || '';
                            document.getElementById('supplierAddress').value = s.address || '';
                            document.getElementById('supplierNotes').value = s.notes || '';
                            document.getElementById('supplierStatus').value = s.status || 'active';
                            document.getElementById('supplierType').value = s.supplier_type || 'other_source';
                            document.getElementById('supplierModal').classList.add('show');
                        }
                        
                        function saveSupplier(e) {
                            e.preventDefault();
                            var data = new FormData();
                            data.append('id', document.getElementById('supplierId').value);
                            data.append('name', document.getElementById('supplierName').value);
                            data.append('contact_person', document.getElementById('supplierContact').value);
                            data.append('phone', document.getElementById('supplierPhone').value);
                            data.append('email', document.getElementById('supplierEmail').value);
                            data.append('address', document.getElementById('supplierAddress').value);
                            data.append('notes', document.getElementById('supplierNotes').value);
                            data.append('status', document.getElementById('supplierStatus').value);
                            data.append('supplier_type', document.getElementById('supplierType').value);
                            
                            fetch('?action=save_supplier', { method: 'POST', body: data })
                            .then(function(res) { return res.json(); })
                            .then(function(result) {
                                if (result.success) {
                                    if (window.showToast) showToast('success', 'Supplier Saved', 'The supplier has been saved', 3000);
                                    closeModal('supplierModal');
                                    setTimeout(function(){ location.reload(); }, 800);
                                } else {
                                    alert('❌ ' + result.message);
                                }
                            });
                        }
                        
                        function deleteSupplier(id) {
                            customConfirm('Delete this supplier? This cannot be undone.', function() {
                                fetch('?action=delete_supplier&id=' + id)
                                .then(function(res) { return res.json(); })
                                .then(function(result) {
                                    if (result.success) {
                                        if (window.showToast) showToast('success', 'Supplier Deleted', '', 2500);
                                        setTimeout(function(){ location.reload(); }, 800);
                                    } else {
                                        alert('❌ ' + result.message);
                                    }
                                });
                            }, 'danger');
                        }
                        </script>
                        
                        <?php
                        

                        // ============================================
                        // PURCHASES PAGE
                        // ============================================
break;
endswitch;
