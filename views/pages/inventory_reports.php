<?php
switch ('inventory_reports'):
case 'inventory_reports':

                            if (!canAccess('inventory_reports')) {
                                echo '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;">
                                    <div style="font-size:4rem;margin-bottom:1rem;">⛔</div>
                                    <h2 style="color:#111827;">Access Denied</h2>
                                    <a href="?page=dashboard" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Go to Dashboard</a>
                                </div>';
                                break;
                            }
                            
                            $report_type = isset($_GET['type']) ? $_GET['type'] : 'current';
                            
                            // Current stock data
                            $stmt = $pdo->query("
                                SELECT p.*, c.name as category_name
                                FROM products p
                                LEFT JOIN categories c ON p.category_id = c.id
                                WHERE p.archived = 0
                                ORDER BY p.name
                            ");
                            $allProducts = $stmt->fetchAll();
                            
                            // Low stock
                            $lowStock = array_filter($allProducts, function($p) {
                                $threshold = $p['low_stock_threshold'] ?? 5;
                                return $p['stock_quantity'] > 0 && $p['stock_quantity'] <= $threshold;
                            });
                            
                            // Out of stock
                            $outOfStock = array_filter($allProducts, function($p) {
                                return $p['stock_quantity'] == 0;
                            });
                            
                            // Inventory valuation
                            $totalCost = 0;
                            $totalRetail = 0;
                            foreach ($allProducts as $p) {
                                $totalCost += $p['stock_quantity'] * ($p['cost_price'] ?? 0);
                                $totalRetail += $p['stock_quantity'] * $p['selling_price'];
                            }
                            $potentialProfit = $totalRetail - $totalCost;
                            
                            // Stock movements
                            $recentMovements = $pdo->query("
                                SELECT sm.*, p.name as product_name, u.full_name as user_name
                                FROM stock_movements sm
                                LEFT JOIN products p ON sm.product_id = p.id
                                LEFT JOIN users u ON sm.user_id = u.id
                                ORDER BY sm.created_at DESC
                                LIMIT 100
                            ")->fetchAll();
                            
                            // Purchase report
                            $purchases = $pdo->query("
                                SELECT p.*, s.name as supplier_name
                                FROM purchases p
                                LEFT JOIN suppliers s ON p.supplier_id = s.id
                                ORDER BY p.purchase_date DESC
                                LIMIT 100
                            ")->fetchAll();
                            ?>
                            
                            <!-- Page Header -->
                            <div class="inv-page-header">
                                <div class="inv-page-header-left">
                                    <h2 class="inv-page-title">
                                        <span class="inv-page-icon">📈</span>
                                        Inventory Reports
                                    </h2>
                                    <p class="inv-page-subtitle">Analyze stock levels, movements, and valuation</p>
                                </div>
                                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                    <button onclick="exportInventoryReport()" class="inv-btn-secondary">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" y1="15" x2="12" y2="3"></line>
                                        </svg>
                                        Export CSV
                                    </button>
                                    <button onclick="window.print()" class="inv-btn-secondary">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                            <rect x="6" y="14" width="12" height="8"></rect>
                                        </svg>
                                        Print
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Report Type Tabs -->
                            <div class="fin-tabs">
                                <a href="?page=inventory_reports&type=current" class="fin-tab <?php echo $report_type == 'current' ? 'active' : ''; ?>">📦 Current Stock</a>
                                <a href="?page=inventory_reports&type=low" class="fin-tab <?php echo $report_type == 'low' ? 'active' : ''; ?>">⚠️ Low Stock</a>
                                <a href="?page=inventory_reports&type=out" class="fin-tab <?php echo $report_type == 'out' ? 'active' : ''; ?>">🚫 Out of Stock</a>
                                <a href="?page=inventory_reports&type=movement" class="fin-tab <?php echo $report_type == 'movement' ? 'active' : ''; ?>">🔄 Movements</a>
                                <a href="?page=inventory_reports&type=purchase" class="fin-tab <?php echo $report_type == 'purchase' ? 'active' : ''; ?>">🛒 Purchases</a>
                                <a href="?page=inventory_reports&type=valuation" class="fin-tab <?php echo $report_type == 'valuation' ? 'active' : ''; ?>">💰 Valuation</a>
                        <a href="?page=inventory_reports&type=procurement" class="fin-tab <?php echo $report_type == 'procurement' ? 'active' : ''; ?>">📦 Procurement</a>
                            </div>
                            
                            <!-- Summary KPI Cards -->
                            <div class="inv-stats-grid">
                                <div class="inv-stat-card">
                                    <div class="inv-stat-header">
                                        <div class="inv-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">📦</div>
                                    </div>
                                    <div class="inv-stat-label">Total Products</div>
                                    <div class="inv-stat-value primary"><?php echo count($allProducts); ?></div>
                                    <div class="inv-stat-footer">Active products</div>
                                </div>
                                
                                <div class="inv-stat-card">
                                    <div class="inv-stat-header">
                                        <div class="inv-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">⚠️</div>
                                    </div>
                                    <div class="inv-stat-label">Low Stock</div>
                                    <div class="inv-stat-value warning"><?php echo count($lowStock); ?></div>
                                    <div class="inv-stat-footer">Need reorder</div>
                                </div>
                                
                                <div class="inv-stat-card">
                                    <div class="inv-stat-header">
                                        <div class="inv-stat-icon" style="background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);">🚫</div>
                                    </div>
                                    <div class="inv-stat-label">Out of Stock</div>
                                    <div class="inv-stat-value danger"><?php echo count($outOfStock); ?></div>
                                    <div class="inv-stat-footer">Unavailable</div>
                                </div>
                                
                                <div class="inv-stat-card">
                                    <div class="inv-stat-header">
                                        <div class="inv-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">💰</div>
                                    </div>
                                    <div class="inv-stat-label">Retail Value</div>
                                    <div class="inv-stat-value success">₱<?php echo number_format($totalRetail, 2); ?></div>
                                    <div class="inv-stat-footer">At selling price</div>
                                </div>
                            </div>
                            
                            <!-- ===== CURRENT STOCK REPORT ===== -->
                            <?php if ($report_type == 'current'): ?>
                            <div class="inv-card">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">📦 Current Stock Report</div>
                                        <div class="inv-card-subtitle">All products with their current stock levels</div>
                                    </div>
                                </div>
                                <div class="inv-table-wrap">
                                    <table class="inv-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Category</th>
                                                <th>Stock</th>
                                                <th>Cost</th>
                                                <th>Retail</th>
                                                <th style="text-align:right;">Stock Value (Cost)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allProducts as $p): 
                                                $value = $p['stock_quantity'] * ($p['cost_price'] ?? 0);
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                                <td><span class="inv-product-sku"><?php echo htmlspecialchars($p['sku'] ?? '—'); ?></span></td>
                                                <td><span class="inv-category-badge"><?php echo htmlspecialchars($p['category_name'] ?? '—'); ?></span></td>
                                                <td><span class="inv-stock-number"><?php echo $p['stock_quantity']; ?></span> <span style="font-size:11.5px;color:#9CA3AF;"><?php echo $p['unit'] ?? 'pc'; ?></span></td>
                                                <td>₱<?php echo number_format($p['cost_price'] ?? 0, 2); ?></td>
                                                <td>₱<?php echo number_format($p['selling_price'], 2); ?></td>
                                                <td style="text-align:right;"><strong class="inv-money">₱<?php echo number_format($value, 2); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot style="background:#F9FAFB;font-weight:800;">
                                            <tr>
                                                <td colspan="6" style="padding:16px;text-align:right;">TOTAL INVENTORY COST VALUE</td>
                                                <td style="text-align:right;color:#4F46E5;font-size:16px;">₱<?php echo number_format($totalCost, 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- ===== LOW STOCK REPORT ===== -->
                            <?php if ($report_type == 'low'): ?>
                            <div class="inv-card">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">⚠️ Low Stock Report</div>
                                        <div class="inv-card-subtitle">Products below their threshold — reorder soon</div>
                                    </div>
                                </div>
                                <?php if (!empty($lowStock)): ?>
                                <div class="inv-table-wrap">
                                    <table class="inv-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Category</th>
                                                <th>Current Stock</th>
                                                <th>Threshold</th>
                                                <th>Suggested Reorder</th>
                                                <th style="text-align:right;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lowStock as $p): 
                                                $threshold = $p['low_stock_threshold'] ?? 5;
                                                $suggested = max(20, $threshold * 4);
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                                <td><span class="inv-category-badge"><?php echo htmlspecialchars($p['category_name'] ?? '—'); ?></span></td>
                                                <td><span class="inv-stock-badge low"><?php echo $p['stock_quantity']; ?> <?php echo $p['unit'] ?? 'pc'; ?></span></td>
                                                <td><span class="inv-money-muted"><?php echo $threshold; ?></span></td>
                                                <td><strong class="inv-money"><?php echo $suggested; ?> <?php echo $p['unit'] ?? 'pc'; ?></strong></td>
                                                <td style="text-align:right;">
                                                    <a href="?page=purchases" class="inv-btn-secondary" style="padding:6px 12px;font-size:12px;">Order</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="inv-empty-state">
                                    <div class="inv-empty-icon">✅</div>
                                    <div class="inv-empty-title">All Good!</div>
                                    <div class="inv-empty-text">No products are below their stock threshold</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- ===== OUT OF STOCK REPORT ===== -->
                            <?php if ($report_type == 'out'): ?>
                            <div class="inv-card">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">🚫 Out of Stock Report</div>
                                        <div class="inv-card-subtitle">Products currently unavailable for sale</div>
                                    </div>
                                </div>
                                <?php if (!empty($outOfStock)): ?>
                                <div class="inv-table-wrap">
                                    <table class="inv-table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Category</th>
                                                <th>Last Known Cost</th>
                                                <th>Retail Price</th>
                                                <th style="text-align:right;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($outOfStock as $p): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                                <td><span class="inv-category-badge"><?php echo htmlspecialchars($p['category_name'] ?? '—'); ?></span></td>
                                                <td>₱<?php echo number_format($p['cost_price'] ?? 0, 2); ?></td>
                                                <td>₱<?php echo number_format($p['selling_price'], 2); ?></td>
                                                <td style="text-align:right;">
                                                    <a href="?page=purchases" class="inv-btn-secondary" style="padding:6px 12px;font-size:12px;">Restock</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="inv-empty-state">
                                    <div class="inv-empty-icon">✅</div>
                                    <div class="inv-empty-title">No Out-of-Stock Products</div>
                                    <div class="inv-empty-text">All products are available</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- ===== MOVEMENT REPORT ===== -->
                            <?php if ($report_type == 'movement'): ?>
                            <div class="inv-card">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">🔄 Stock Movements</div>
                                        <div class="inv-card-subtitle">Latest <?php echo count($recentMovements); ?> movements</div>
                                    </div>
                                </div>
                                <?php if (!empty($recentMovements)): ?>
                                <div class="inv-table-wrap">
                                    <table class="inv-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Product</th>
                                                <th>Type</th>
                                                <th>Quantity</th>
                                                <th>Before</th>
                                                <th>After</th>
                                                <th>Reason</th>
                                                <th>User</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentMovements as $m): 
                                                $qtyClass = $m['quantity'] > 0 ? 'ok' : 'out';
                                                $qtySign = $m['quantity'] > 0 ? '+' : '';
                                            ?>
                                            <tr>
                                                <td><span style="font-size:12.5px;color:#6B7280;"><?php echo date('M d, H:i', strtotime($m['created_at'])); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($m['product_name'] ?? '—'); ?></strong></td>
                                                <td><span class="inv-category-badge"><?php echo ucfirst($m['movement_type']); ?></span></td>
                                                <td><span class="inv-stock-badge <?php echo $qtyClass; ?>"><?php echo $qtySign . $m['quantity']; ?></span></td>
                                                <td><?php echo $m['quantity_before']; ?></td>
                                                <td><strong><?php echo $m['quantity_after']; ?></strong></td>
                                                <td><span style="font-size:12px;color:#6B7280;"><?php echo htmlspecialchars($m['reason'] ?? '—'); ?></span></td>
                                                <td><span style="font-size:12px;color:#6B7280;"><?php echo htmlspecialchars($m['user_name'] ?? 'System'); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="inv-empty-state">
                                    <div class="inv-empty-icon">📋</div>
                                    <div class="inv-empty-title">No movements yet</div>
                                    <div class="inv-empty-text">Stock changes will appear here</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- ===== PURCHASE REPORT ===== -->
                            <?php if ($report_type == 'purchase'): ?>
                            <div class="inv-card">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">🛒 Purchase Report</div>
                                        <div class="inv-card-subtitle"><?php echo count($purchases); ?> purchase records</div>
                                    </div>
                                </div>
                                <?php if (!empty($purchases)): ?>
                                <div class="inv-table-wrap">
                                    <table class="inv-table">
                                        <thead>
                                            <tr>
                                                <th>PO Number</th>
                                                <th>Date</th>
                                                <th>Supplier</th>
                                                <th>Status</th>
                                                <th style="text-align:right;">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $totalPurchaseAmount = 0; foreach ($purchases as $p): 
                                                $totalPurchaseAmount += $p['total_amount'];
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($p['po_number']); ?></strong></td>
                                                <td><?php echo date('M d, Y', strtotime($p['purchase_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($p['supplier_name'] ?? '—'); ?></td>
                                                <td><span class="inv-stock-badge <?php echo $p['payment_status'] == 'paid' ? 'ok' : ($p['payment_status'] == 'partial' ? 'low' : 'out'); ?>"><?php echo ucfirst($p['payment_status']); ?></span></td>
                                                <td style="text-align:right;"><strong class="inv-money">₱<?php echo number_format($p['total_amount'], 2); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot style="background:#F9FAFB;font-weight:800;">
                                            <tr>
                                                <td colspan="4" style="padding:16px;text-align:right;">TOTAL PURCHASES</td>
                                                <td style="text-align:right;color:#4F46E5;font-size:16px;">₱<?php echo number_format($totalPurchaseAmount, 2); ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="inv-empty-state">
                                    <div class="inv-empty-icon">🛒</div>
                                    <div class="inv-empty-title">No purchases yet</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- ===== VALUATION REPORT ===== -->
                            <?php if ($report_type == 'valuation'): ?>
                            <div class="inv-card">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">💰 Inventory Valuation</div>
                                        <div class="inv-card-subtitle">Cost vs. retail value of your inventory</div>
                                    </div>
                                </div>
                                
                                <div style="padding:24px;">
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
                                        <div style="padding:24px;background:linear-gradient(135deg,#EEF2FF 0%,#E0E7FF 100%);border:1px solid #C7D2FE;border-radius:16px;">
                                            <div style="font-size:11.5px;font-weight:700;color:#4F46E5;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;">Total Cost Value</div>
                                            <div style="font-size:32px;font-weight:800;color:#4F46E5;letter-spacing:-0.03em;">₱<?php echo number_format($totalCost, 2); ?></div>
                                            <div style="font-size:12.5px;color:#6366F1;margin-top:6px;">What you paid for the inventory</div>
                                        </div>
                                        <div style="padding:24px;background:linear-gradient(135deg,#ECFDF5 0%,#D1FAE5 100%);border:1px solid #A7F3D0;border-radius:16px;">
                                            <div style="font-size:11.5px;font-weight:700;color:#059669;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;">Total Retail Value</div>
                                            <div style="font-size:32px;font-weight:800;color:#059669;letter-spacing:-0.03em;">₱<?php echo number_format($totalRetail, 2); ?></div>
                                            <div style="font-size:12.5px;color:#10B981;margin-top:6px;">What it's worth at selling price</div>
                                        </div>
                                    </div>
                                    
                                    <div style="padding:24px;background:linear-gradient(135deg,#FEF3C7 0%,#FDE68A 100%);border:1px solid #FCD34D;border-radius:16px;margin-bottom:24px;">
                                        <div style="font-size:11.5px;font-weight:700;color:#92400E;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;">Potential Profit</div>
                                        <div style="font-size:36px;font-weight:800;color:#92400E;letter-spacing:-0.03em;">₱<?php echo number_format($potentialProfit, 2); ?></div>
                                        <div style="font-size:12.5px;color:#B45309;margin-top:6px;">Profit if all stock is sold at retail price</div>
                                    </div>
                                    
                                    <h3 style="font-size:14px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:12px;">Top 10 Products by Stock Value</h3>
                                    <div class="inv-table-wrap">
                                        <table class="inv-table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Stock</th>
                                                    <th>Cost</th>
                                                    <th>Retail</th>
                                                    <th>Cost Value</th>
                                                    <th>Retail Value</th>
                                                    <th>Margin</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                usort($allProducts, function($a, $b) {
                                                    return ($b['stock_quantity'] * ($b['cost_price'] ?? 0)) - ($a['stock_quantity'] * ($a['cost_price'] ?? 0));
                                                });
                                                foreach (array_slice($allProducts, 0, 10) as $p): 
                                                    $cost = $p['stock_quantity'] * ($p['cost_price'] ?? 0);
                                                    $retail = $p['stock_quantity'] * $p['selling_price'];
                                                    $margin = $retail - $cost;
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                                                    <td><?php echo $p['stock_quantity']; ?></td>
                                                    <td>₱<?php echo number_format($p['cost_price'] ?? 0, 2); ?></td>
                                                    <td>₱<?php echo number_format($p['selling_price'], 2); ?></td>
                                                    <td>₱<?php echo number_format($cost, 2); ?></td>
                                                    <td>₱<?php echo number_format($retail, 2); ?></td>
                                                    <td><strong style="color:#059669;">₱<?php echo number_format($margin, 2); ?></strong></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- ===== PROCUREMENT REPORT ===== -->
                            <?php if ($report_type == 'procurement'): ?>
                            <?php
                            require_once __DIR__ . '/proc_common.php';
                            $prStatusCounts = array_fill_keys(['draft', 'pending_approval', 'revision_requested', 'approved', 'rejected', 'cancelled'], 0);
                            foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM purchase_requests GROUP BY status") as $prow) {
                                if (isset($prStatusCounts[$prow['status']])) { $prStatusCounts[$prow['status']] = (int) $prow['c']; }
                            }
                            $prRecentRows = $pdo->query("
                                SELECT pr.*, s.name AS supplier_name, u.full_name AS requester_name
                                FROM purchase_requests pr
                                LEFT JOIN suppliers s ON pr.supplier_id = s.id
                                LEFT JOIN users u ON pr.requester_id = u.id
                                ORDER BY pr.created_at DESC LIMIT 15
                            ")->fetchAll();
                            $procPoRows = $pdo->query("
                                SELECT po.*, s.name AS supplier_name,
                                       (SELECT COALESCE(SUM(quantity_received), 0) FROM procurement_po_items WHERE po_id = po.id) AS received_qty,
                                       (SELECT COALESCE(SUM(quantity), 0) FROM procurement_po_items WHERE po_id = po.id) AS ordered_qty
                                FROM procurement_purchase_orders po
                                LEFT JOIN suppliers s ON po.supplier_id = s.id
                                ORDER BY po.created_at DESC LIMIT 15
                            ")->fetchAll();
                            ?>
                            <div class="inv-card" style="margin-bottom:16px;">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">📦 Procurement Report</div>
                                        <div class="inv-card-subtitle">Purchase request status summary</div>
                                    </div>
                                </div>
                                <div class="inv-stats-grid">
                                    <div class="inv-stat-card"><div class="inv-stat-label">Total Requests</div><div class="inv-stat-value primary"><?php echo number_format(array_sum($prStatusCounts)); ?></div></div>
                                    <div class="inv-stat-card"><div class="inv-stat-label">Pending Approval</div><div class="inv-stat-value warning"><?php echo number_format($prStatusCounts['pending_approval']); ?></div></div>
                                    <div class="inv-stat-card"><div class="inv-stat-label">Approved</div><div class="inv-stat-value success"><?php echo number_format($prStatusCounts['approved']); ?></div></div>
                                    <div class="inv-stat-card"><div class="inv-stat-label">Rejected</div><div class="inv-stat-value danger"><?php echo number_format($prStatusCounts['rejected']); ?></div></div>
                                </div>
                            </div>
                            <div class="inv-card" style="margin-bottom:16px;">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">🛒 Purchase Orders</div>
                                        <div class="inv-card-subtitle">Latest <?php echo count($procPoRows); ?> purchase order(s)</div>
                                    </div>
                                </div>
                                <?php if (empty($procPoRows)): ?>
                                <div class="inv-empty-state"><div class="inv-empty-icon">🛒</div><div class="inv-empty-title">No purchase orders yet</div></div>
                                <?php else: ?>
                                <div class="inv-table-wrap">
                                    <table class="inv-table">
                                        <thead><tr><th>PO Number</th><th>Supplier</th><th>Date</th><th>Received</th><th>Status</th><th style="text-align:right;">Total</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($procPoRows as $ppRow): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($ppRow['po_number']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($ppRow['supplier_name'] ?? '—'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($ppRow['order_date'])); ?></td>
                                                <td><?php echo (int) $ppRow['received_qty']; ?> / <?php echo (int) $ppRow['ordered_qty']; ?></td>
                                                <td><?php echo procPoStatusBadge($ppRow['status']); ?></td>
                                                <td style="text-align:right;"><strong>₱<?php echo number_format($ppRow['total_amount'], 2); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="inv-card">
                                <div class="inv-card-header">
                                    <div class="inv-card-title-wrap">
                                        <div class="inv-card-title">📝 Recent Purchase Requests</div>
                                        <div class="inv-card-subtitle">Latest <?php echo count($prRecentRows); ?> request(s)</div>
                                    </div>
                                </div>
                                <?php if (empty($prRecentRows)): ?>
                                <div class="inv-empty-state"><div class="inv-empty-icon">📝</div><div class="inv-empty-title">No purchase requests yet</div></div>
                                <?php else: ?>
                                <div class="inv-table-wrap">
                                    <table class="inv-table">
                                        <thead><tr><th>Request #</th><th>Date</th><th>Requester</th><th>Supplier</th><th>Status</th><th style="text-align:right;">Estimated Total</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($prRecentRows as $prr): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($prr['request_number']); ?></strong></td>
                                                <td><?php echo date('M d, Y', strtotime($prr['request_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($prr['requester_name'] ?? '—'); ?></td>
                                                <td><?php echo htmlspecialchars($prr['supplier_name'] ?? '—'); ?></td>
                                                <td><?php echo procStatusBadge($prr['status']); ?></td>
                                                <td style="text-align:right;"><strong>₱<?php echo number_format($prr['grand_total'], 2); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php endif; ?>

                            <script>
                            function exportInventoryReport() {
                                var type = '<?php echo $report_type; ?>';
                                window.location.href = '?action=export_inventory_report&type=' + type;
                            }
                            </script>
                            
                            <?php
                            

                            // ============================================
                            // INVENTORY NOTIFICATIONS PAGE
                            // ============================================
break;
endswitch;
