<?php
switch ('finance_reports'):
case 'finance_reports':

                if (!canAccess('finance_reports')) {
                    echo '<div class="alert alert-danger" style="margin:2rem;text-align:center;"><div style="font-size:4rem;margin-bottom:1rem;">⛔</div><h2>Access Denied</h2><p>You do not have permission to access this page.</p><a href="?page=dashboard" class="btn btn-primary" style="margin-top:1rem;">Go to Dashboard</a></div>';
                    break;  
                }
                
                    // ============================================
                    // FINANCE REPORTS — FULL SYSTEM
                    // ============================================
                    $period = isset($_GET['period']) ? $_GET['period'] : 'year';
                    $report_type = isset($_GET['type']) ? $_GET['type'] : 'revenue';
                    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
                    $end_date   = isset($_GET['end_date'])   ? $_GET['end_date']   : date('Y-m-t');
                    
                    // Adjust range based on preset
                    switch ($period) {
                        case 'today':  $start_date = $end_date = date('Y-m-d'); break;
                        case 'week':   $start_date = date('Y-m-d', strtotime('monday this week')); $end_date = date('Y-m-d', strtotime('sunday this week')); break;
                        case 'month':  $start_date = date('Y-m-01'); $end_date = date('Y-m-t'); break;
                        case 'year':   $start_date = date('Y-01-01'); $end_date = date('Y-12-31'); break;
                    }
                    
                    // ============================================
                    // COMMON METRICS (used in all reports)
                    // ============================================
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as txn_count,
                            COALESCE(SUM(total_amount), 0) as gross_revenue,
                            COALESCE(SUM(tax), 0) as vat_collected,
                            COALESCE(SUM(subtotal), 0) as net_sales,
                            COALESCE(SUM(discount_amount), 0) as total_discounts,
                            COALESCE(AVG(total_amount), 0) as avg_order
                        FROM sales 
                        WHERE DATE(sale_date) BETWEEN ? AND ?
                        AND total_amount > 0
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $rev = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as refund_count, COALESCE(SUM(total_refund), 0) as total_refunded
                        FROM returns 
                        WHERE DATE(created_at) BETWEEN ? AND ?
                        AND status IN ('approved', 'completed')
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $refunds = $stmt->fetch();
                    
                    // Expenses
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as expense_count, COALESCE(SUM(amount), 0) as total_expenses
                        FROM expenses 
                        WHERE expense_date BETWEEN ? AND ?
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $expenses = $stmt->fetch();
                    
                    $net_revenue = $rev['gross_revenue'] - $refunds['total_refunded'];
                    $gross_profit = $net_revenue - $expenses['total_expenses'];
                    $profit_margin = $net_revenue > 0 ? ($gross_profit / $net_revenue) * 100 : 0;
                    
                    // ============================================
                    // REPORT-SPECIFIC DATA
                    // ============================================
                    
                    // DAILY BREAKDOWN (Revenue + Sales reports)
                    $stmt = $pdo->prepare("
                        SELECT DATE(sale_date) as date, COUNT(*) as txn,
                            COALESCE(SUM(total_amount), 0) as revenue,
                            COALESCE(SUM(tax), 0) as vat,
                            COALESCE(SUM(discount_amount), 0) as discount
                        FROM sales 
                        WHERE DATE(sale_date) BETWEEN ? AND ?
                        AND total_amount > 0
                        GROUP BY DATE(sale_date) 
                        ORDER BY date ASC
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $daily = $stmt->fetchAll();
                    
                    // TOP PRODUCTS — ALL-TIME, sorted by units sold (highest first)
                    $stmt = $pdo->prepare("
                        SELECT 
                            p.name, 
                            p.unit, 
                            SUM(si.quantity) as sold, 
                            SUM(si.total_price) as revenue,
                            COALESCE(AVG(si.unit_price), 0) as avg_price
                        FROM sale_items si
                        JOIN sales s ON si.sale_id = s.id
                        JOIN products p ON si.product_id = p.id
                        WHERE s.total_amount > 0
                        AND si.quantity > 0
                        GROUP BY p.id 
                        ORDER BY sold DESC, revenue DESC
                        LIMIT 10
                    ");
                    $stmt->execute();
                    $top_products = $stmt->fetchAll();
                    
                    // TOP CUSTOMERS — ALL-TIME, Top 5 by total spending
                    $stmt = $pdo->prepare("
                        SELECT 
                            c.name, 
                            c.email,
                            COUNT(s.id) as orders, 
                            COALESCE(SUM(s.total_amount), 0) as spent,
                            COALESCE(AVG(s.total_amount), 0) as avg_order,
                            MAX(s.sale_date) as last_purchase
                        FROM customers c 
                        JOIN sales s ON c.id = s.customer_id
                        WHERE s.total_amount > 0
                        GROUP BY c.id 
                        HAVING spent > 0
                        ORDER BY spent DESC 
                        LIMIT 5
                    ");
                    $stmt->execute();
                    $top_customers = $stmt->fetchAll();
                    
                    // PAYMENT METHODS
                    // PAYMENT METHODS (excludes negative/refund sales)
                    $stmt = $pdo->prepare("
                        SELECT COALESCE(payment_method, 'cash') as method,
                            COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
                        FROM sales 
                        WHERE DATE(sale_date) BETWEEN ? AND ?
                        AND total_amount > 0
                        GROUP BY payment_method 
                        ORDER BY total DESC
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $payment_methods = $stmt->fetchAll();
                    
                    // REFUNDS DETAIL
                    $stmt = $pdo->prepare("
                        SELECT r.*, s.invoice_number as original_invoice,
                            u.full_name as created_by_name, a.full_name as approved_by_name
                        FROM returns r
                        LEFT JOIN sales s ON r.original_sale_id = s.id
                        LEFT JOIN users u ON r.user_id = u.id
                        LEFT JOIN users a ON r.approved_by = a.id
                        WHERE DATE(r.created_at) BETWEEN ? AND ?
                        ORDER BY r.created_at DESC
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $refunds_detail = $stmt->fetchAll();
                    
                    // EXPENSES DETAIL
                    $stmt = $pdo->prepare("
                        SELECT e.*, u.full_name as recorder_name
                        FROM expenses e
                        LEFT JOIN users u ON e.recorded_by = u.id
                        WHERE e.expense_date BETWEEN ? AND ?
                        ORDER BY e.expense_date DESC
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $expenses_detail = $stmt->fetchAll();
                    
                    // EXPENSES BY CATEGORY
                    $stmt = $pdo->prepare("
                        SELECT category, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
                        FROM expenses WHERE expense_date BETWEEN ? AND ?
                        GROUP BY category ORDER BY total DESC
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $expenses_by_category = $stmt->fetchAll();
                    
                    // SALES DETAIL LIST
                    $stmt = $pdo->prepare("
                        SELECT s.*, u.full_name as cashier, c.name as customer
                        FROM sales s
                        LEFT JOIN users u ON s.user_id = u.id
                        LEFT JOIN customers c ON s.customer_id = c.id
                        WHERE DATE(s.sale_date) BETWEEN ? AND ?
                        AND s.total_amount > 0
                        ORDER BY s.sale_date DESC
                        LIMIT 100
                    ");
                    $stmt->execute([$start_date, $end_date]);
                    $sales_detail = $stmt->fetchAll();
                    ?>
                    
                    <!-- ============================================ -->
                    <!-- FINANCE REPORTS — MASTER PAGE -->
                    <!-- ============================================ -->
                    
                    <!-- Page Header -->
                    <div class="fin-page-header">
                        <div>
                            <h2 class="fin-page-title">
                                <span class="fin-page-icon">📊</span>
                                Finance Reports
                            </h2>
                            <p class="fin-page-subtitle">
                                <?php echo date('M d, Y', strtotime($start_date)); ?> — <?php echo date('M d, Y', strtotime($end_date)); ?>
                            </p>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button onclick="exportCurrentReport()" class="fin-action-btn fin-action-export">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                <span>Export CSV</span>
                            </button>
                            <button onclick="window.print()" class="fin-action-btn fin-action-print">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                    <rect x="6" y="14" width="12" height="8"></rect>
                                </svg>
                                <span>Print</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- REPORT TYPE TABS -->
                    <!-- ============================================ -->
                    <div class="fin-tabs">
                        <a href="?page=finance_reports&type=revenue&period=<?php echo $period; ?>" class="fin-tab <?php echo $report_type == 'revenue' ? 'active' : ''; ?>">
                            💰 Revenue
                        </a>
                        <a href="?page=finance_reports&type=expense&period=<?php echo $period; ?>" class="fin-tab <?php echo $report_type == 'expense' ? 'active' : ''; ?>">
                            💸 Expenses
                        </a>
                        <a href="?page=finance_reports&type=sales&period=<?php echo $period; ?>" class="fin-tab <?php echo $report_type == 'sales' ? 'active' : ''; ?>">
                            🛒 Sales
                        </a>
                        <a href="?page=finance_reports&type=refund&period=<?php echo $period; ?>" class="fin-tab <?php echo $report_type == 'refund' ? 'active' : ''; ?>">
                            🔄 Refunds
                        </a>
                        <a href="?page=finance_reports&type=profit&period=<?php echo $period; ?>" class="fin-tab <?php echo $report_type == 'profit' ? 'active' : ''; ?>">
                            📈 Profit
                        </a>
                        <a href="?page=finance_reports&type=payment&period=<?php echo $period; ?>" class="fin-tab <?php echo $report_type == 'payment' ? 'active' : ''; ?>">
                            💳 Payments
                        </a>
                    </div>
                    
                    <!-- Period Filter Bar -->
                    <div class="fin-filter-bar">
                        <!-- Left: Period presets -->
                        <div class="fin-filter-group">
                            <div class="fin-filter-label">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                Period
                            </div>
                            <div class="fin-period-pills">
                                <a href="?page=finance_reports&type=<?php echo $report_type; ?>&period=today" class="fin-pill <?php echo $period == 'today' ? 'active' : ''; ?>">
                                    <span class="fin-pill-dot"></span>Today
                                </a>
                                <a href="?page=finance_reports&type=<?php echo $report_type; ?>&period=week" class="fin-pill <?php echo $period == 'week' ? 'active' : ''; ?>">
                                    <span class="fin-pill-dot"></span>Week
                                </a>
                                <a href="?page=finance_reports&type=<?php echo $report_type; ?>&period=month" class="fin-pill <?php echo $period == 'month' ? 'active' : ''; ?>">
                                    <span class="fin-pill-dot"></span>Month
                                </a>
                                <a href="?page=finance_reports&type=<?php echo $report_type; ?>&period=year" class="fin-pill <?php echo $period == 'year' ? 'active' : ''; ?>">
                                    <span class="fin-pill-dot"></span>Year
                                </a>
                            </div>
                        </div>
                        
                        <!-- Divider -->
                        <div class="fin-filter-divider"></div>
                        
                        <!-- Right: Custom date range -->
                        <form method="GET" class="fin-date-range">
                            <input type="hidden" name="page" value="finance_reports">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                            <input type="hidden" name="period" value="custom">
                            
                            <div class="fin-filter-label">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Custom Range
                            </div>
                            
                            <div class="fin-date-inputs">
                                <div class="fin-date-field">
                                    <span class="fin-date-prefix">From</span>
                                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="fin-date-field-input">
                                </div>
                                <div class="fin-date-arrow">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </div>
                                <div class="fin-date-field">
                                    <span class="fin-date-prefix">To</span>
                                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="fin-date-field-input">
                                </div>
                                
                                <button type="submit" class="fin-apply-btn">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    Apply
                                </button>
                                
                                <!-- ⭐ NEW: Clear Filters button -->
                                <a href="?page=finance_reports&type=<?php echo $report_type; ?>&period=month" class="fin-clear-btn" title="Reset to this month">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                    Clear
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- SUMMARY KPI CARDS (always shown) -->
                    <!-- ============================================ -->
                    <div class="fin-stats-grid" style="grid-template-columns:repeat(4,1fr);">
                        <div class="fin-stat-card">
                            <div class="fin-stat-header">
                                <div class="fin-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                    </svg>
                                </div>
                                <div class="fin-stat-badge fin-stat-badge-green">Gross</div>
                            </div>
                            <div class="fin-stat-label">Revenue</div>
                            <div class="fin-stat-value fin-stat-value-green">₱<?php echo number_format($rev['gross_revenue'], 2); ?></div>
                            <div class="fin-stat-footer"><?php echo number_format($rev['txn_count']); ?> transactions</div>
                        </div>
                        
                        <div class="fin-stat-card">
                            <div class="fin-stat-header">
                                <div class="fin-stat-icon" style="background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="1 4 1 10 7 10"></polyline>
                                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                    </svg>
                                </div>
                                <div class="fin-stat-badge" style="background:#FEF2F2;color:#DC2626;">Refunds</div>
                            </div>
                            <div class="fin-stat-label">Refunded</div>
                            <div class="fin-stat-value" style="color:#DC2626;">-₱<?php echo number_format($refunds['total_refunded'], 2); ?></div>
                            <div class="fin-stat-footer"><?php echo number_format($refunds['refund_count']); ?> returns</div>
                        </div>
                        
                        <div class="fin-stat-card">
                            <div class="fin-stat-header">
                                <div class="fin-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path>
                                        <path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path>
                                        <path d="M18 12a2 2 0 0 0 0 4h4v-4z"></path>
                                    </svg>
                                </div>
                                <div class="fin-stat-badge fin-stat-badge-amber">Expenses</div>
                            </div>
                            <div class="fin-stat-label">Expenses</div>
                            <div class="fin-stat-value" style="color:#D97706;">₱<?php echo number_format($expenses['total_expenses'], 2); ?></div>
                            <div class="fin-stat-footer"><?php echo number_format($expenses['expense_count']); ?> entries</div>
                        </div>
                        
                        <div class="fin-stat-card">
                            <div class="fin-stat-header">
                                <div class="fin-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                        <polyline points="17 6 23 6 23 12"></polyline>
                                    </svg>
                                </div>
                                <div class="fin-stat-badge fin-stat-badge-violet">Profit</div>
                            </div>
                            <div class="fin-stat-label">Net Profit</div>
                            <div class="fin-stat-value" style="color:#4F46E5;">₱<?php echo number_format($gross_profit, 2); ?></div>
                            <div class="fin-stat-footer"><?php echo number_format($profit_margin, 1); ?>% margin</div>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- TAB CONTENT: REVENUE REPORT -->
                    <!-- ============================================ -->
                    <?php if ($report_type == 'revenue'): ?>
                    
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">💰 Revenue Trend</div>
                                    <div class="fin-card-subtitle">Daily breakdown for selected period</div>
                                </div>
                            </div>
                            <div class="fin-chart-wrapper">
                                <canvas id="reportChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Detailed Table -->
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">Daily Revenue Details</div>
                                    <div class="fin-card-subtitle"><?php echo count($daily); ?> days with activity</div>
                                </div>
                            </div>
                            <div class="fin-table-wrap">
                                <table class="fin-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Transactions</th>
                                            <th>Discounts</th>
                                            <th>VAT</th>
                                            <th style="text-align:right;">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($daily)): foreach ($daily as $d): ?>
                                        <tr>
                                            <td><strong><?php echo date('M d, Y (D)', strtotime($d['date'])); ?></strong></td>
                                            <td><?php echo number_format($d['txn']); ?></td>
                                            <td style="color:#D97706;">₱<?php echo number_format($d['discount'], 2); ?></td>
                                            <td style="color:#D97706;">₱<?php echo number_format($d['vat'], 2); ?></td>
                                            <td style="text-align:right;"><strong style="color:#059669;">₱<?php echo number_format($d['revenue'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="5" style="text-align:center;padding:40px;color:#9CA3AF;">No data for this period</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot style="background:#F9FAFB;font-weight:800;">
                                        <tr>
                                            <td>TOTAL</td>
                                            <td><?php echo number_format($rev['txn_count']); ?></td>
                                            <td style="color:#D97706;">₱<?php echo number_format($rev['total_discounts'], 2); ?></td>
                                            <td style="color:#D97706;">₱<?php echo number_format($rev['vat_collected'], 2); ?></td>
                                            <td style="text-align:right;color:#059669;">₱<?php echo number_format($rev['gross_revenue'], 2); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    
                    <!-- ============================================ -->
                    <!-- TAB CONTENT: EXPENSE REPORT -->
                    <!-- ============================================ -->
                    <?php elseif ($report_type == 'expense'): ?>
                    
                        <!-- Add Expense Button -->
                        <div style="margin-bottom:20px;">
                            <button onclick="showAddExpense()" class="fin-add-expense-btn">
                                <span class="fin-add-expense-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                </span>
                                <span class="fin-add-expense-text">
                                    <strong>Add Expense</strong>
                                    <small>Record a new business expense</small>
                                </span>
                                <svg class="fin-add-expense-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- By Category -->
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">💸 Expenses by Category</div>
                                    <div class="fin-card-subtitle">Total: ₱<?php echo number_format($expenses['total_expenses'], 2); ?></div>
                                </div>
                            </div>
                            <?php if (!empty($expenses_by_category)): ?>
                            <div class="fin-report-list">
                                <?php foreach ($expenses_by_category as $cat): ?>
                                <div class="fin-report-row">
                                    <div class="fin-customer-avatar" style="background:linear-gradient(135deg,#F59E0B,#D97706);"><?php echo strtoupper(substr($cat['category'], 0, 1)); ?></div>
                                    <div class="fin-report-info">
                                        <div class="fin-report-name"><?php echo htmlspecialchars($cat['category']); ?></div>
                                        <div class="fin-report-meta"><?php echo $cat['count']; ?> entries</div>
                                    </div>
                                    <div class="fin-report-value" style="color:#D97706;">₱<?php echo number_format($cat['total'], 2); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="fin-empty">
                                <div class="fin-empty-icon">💸</div>
                                <div class="fin-empty-title">No expenses recorded</div>
                                <div class="fin-empty-text">Click "Add Expense" to start tracking</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Expense Detail Table -->
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">Expense Records</div>
                                    <div class="fin-card-subtitle"><?php echo count($expenses_detail); ?> entries</div>
                                </div>
                            </div>
                            <div class="fin-table-wrap">
                                <table class="fin-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>Description</th>
                                            <th>Recorded By</th>
                                            <th style="text-align:right;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($expenses_detail)): foreach ($expenses_detail as $e): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($e['expense_date'])); ?></td>
                                            <td><span class="fin-badge"><?php echo htmlspecialchars($e['category']); ?></span></td>
                                            <td><?php echo htmlspecialchars($e['description'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($e['recorder_name'] ?? 'N/A'); ?></td>
                                            <td style="text-align:right;"><strong style="color:#D97706;">₱<?php echo number_format($e['amount'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="5" style="text-align:center;padding:40px;color:#9CA3AF;">No expenses</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    
                    <!-- ============================================ -->
                    <!-- TAB CONTENT: SALES REPORT -->
                    <!-- ============================================ -->
                    <?php elseif ($report_type == 'sales'): ?>
                    
                        <!-- Top Products + Top Customers -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
                            <div class="fin-card" style="margin-bottom:0;">
                                <div class="fin-card-header">
                                    <div class="fin-card-title-wrap">
                                        <div class="fin-card-title">🏆 Top Products</div>
                                        <div class="fin-card-subtitle">All-time · by units sold</div>
                                    </div>
                                </div>
                                <?php if (!empty($top_products)): ?>
                                <div class="fin-report-list">
                                    <?php $rank = 1; foreach ($top_products as $p): $rc = $rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : ($rank == 3 ? 'bronze' : '')); ?>
                                    <div class="fin-report-row">
                                        <div class="fin-customer-rank <?php echo $rc; ?>"><?php echo $rank++; ?></div>
                                        <div class="fin-report-info">
                                            <div class="fin-report-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                            <div class="fin-report-meta">
                                                <strong><?php echo number_format($p['sold']); ?></strong> <?php echo htmlspecialchars($p['unit'] ?? 'pcs'); ?> sold
                                            </div>
                                        </div>
                                        <div class="fin-report-value" style="display:flex;flex-direction:column;align-items:flex-end;gap:2px;">
                                            <span style="font-size:14.5px;font-weight:800;color:#059669;">₱<?php echo number_format($p['revenue'], 2); ?></span>
                                            <span style="font-size:10.5px;font-weight:600;color:#9CA3AF;">₱<?php echo number_format($p['avg_price'], 2); ?> / unit</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="fin-empty"><div class="fin-empty-icon">📦</div><div class="fin-empty-title">No sales</div></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="fin-card" style="margin-bottom:0;">
                                <div class="fin-card-header">
                                    <div class="fin-card-title-wrap">
                                        <div class="fin-card-title">👥 Top Customers</div>
                                        <div class="fin-card-subtitle">All-time · Top 5 by spending</div>
                                    </div>
                                </div>
                                <?php if (!empty($top_customers)): ?>
                                <div class="fin-report-list">
                                    <?php $rank = 1; foreach ($top_customers as $c): $rc = $rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : ($rank == 3 ? 'bronze' : '')); ?>
                                    <div class="fin-report-row">
                                        <div class="fin-customer-rank <?php echo $rc; ?>"><?php echo $rank++; ?></div>
                                        <div class="fin-customer-avatar"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></div>
                                        <div class="fin-report-info">
                                            <div class="fin-report-name"><?php echo htmlspecialchars($c['name']); ?></div>
                                            <div class="fin-report-meta">
                                                <strong><?php echo (int)$c['orders']; ?></strong> orders · avg ₱<?php echo number_format($c['avg_order'], 2); ?>
                                            </div>
                                        </div>
                                        <div class="fin-report-value" style="display:flex;flex-direction:column;align-items:flex-end;gap:2px;">
                                            <span style="font-size:14.5px;font-weight:800;color:#059669;">₱<?php echo number_format($c['spent'], 2); ?></span>
                                            <span style="font-size:10.5px;font-weight:600;color:#9CA3AF;">Total spent</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="fin-empty"><div class="fin-empty-icon">👥</div><div class="fin-empty-title">No customers</div></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sales Transaction List -->
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">📋 Transactions</div>
                                    <div class="fin-card-subtitle">Showing up to 100 most recent</div>
                                </div>
                            </div>
                            <div class="fin-table-wrap">
                                <table class="fin-table">
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th>Date</th>
                                            <th>Cashier</th>
                                            <th>Customer</th>
                                            <th style="text-align:right;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($sales_detail)): foreach ($sales_detail as $s): ?>
                                        <tr>
                                            <td><span class="fin-badge fin-badge-primary"><?php echo htmlspecialchars($s['invoice_number']); ?></span></td>
                                            <td><?php echo date('M d, H:i', strtotime($s['sale_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($s['cashier'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($s['customer'] ?? 'Walk-in'); ?></td>
                                            <td style="text-align:right;"><strong>₱<?php echo number_format($s['total_amount'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="5" style="text-align:center;padding:40px;color:#9CA3AF;">No sales in period</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    
                    <!-- ============================================ -->
                    <!-- TAB CONTENT: REFUND REPORT -->
                    <!-- ============================================ -->
                    <?php elseif ($report_type == 'refund'): ?>
                    
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">🔄 Refund Records</div>
                                    <div class="fin-card-subtitle"><?php echo count($refunds_detail); ?> returns in this period</div>
                                </div>
                            </div>
                            <div class="fin-table-wrap">
                                <table class="fin-table">
                                    <thead>
                                        <tr>
                                            <th>Return #</th>
                                            <th>Original Invoice</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Created By</th>
                                            <th>Date</th>
                                            <th style="text-align:right;">Refunded</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($refunds_detail)): foreach ($refunds_detail as $r): 
                                            $statusColors = ['pending' => '#D97706', 'approved' => '#059669', 'completed' => '#059669', 'rejected' => '#DC2626'];
                                            $sc = $statusColors[$r['status']] ?? '#6B7280';
                                        ?>
                                        <tr>
                                            <td><span class="fin-badge fin-badge-primary"><?php echo htmlspecialchars($r['return_number']); ?></span></td>
                                            <td><?php echo htmlspecialchars($r['original_invoice'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($r['reason']); ?></td>
                                            <td><span class="fin-badge" style="background:<?php echo $sc; ?>20;color:<?php echo $sc; ?>;"><?php echo ucfirst($r['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($r['created_by_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                            <td style="text-align:right;"><strong style="color:#DC2626;">₱<?php echo number_format($r['total_refund'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; else: ?>
                                        <tr><td colspan="7" style="text-align:center;padding:40px;color:#9CA3AF;">No refunds in this period</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot style="background:#FEF2F2;font-weight:800;">
                                        <tr>
                                            <td colspan="6">TOTAL REFUNDED</td>
                                            <td style="text-align:right;color:#DC2626;">₱<?php echo number_format($refunds['total_refunded'], 2); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    
                    <!-- ============================================ -->
                    <!-- TAB CONTENT: PROFIT REPORT -->
                    <!-- ============================================ -->
                    <?php elseif ($report_type == 'profit'): ?>
                    
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">📈 Profit & Loss Statement</div>
                                    <div class="fin-card-subtitle">For period <?php echo date('M d', strtotime($start_date)); ?> — <?php echo date('M d, Y', strtotime($end_date)); ?></div>
                                </div>
                            </div>
                            <div style="padding:24px;">
                                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                                    <tr style="border-bottom:1px solid #F3F4F6;">
                                        <td style="padding:14px 0;color:#374151;font-weight:600;">Gross Revenue (Sales)</td>
                                        <td style="padding:14px 0;text-align:right;font-weight:700;color:#059669;">₱<?php echo number_format($rev['gross_revenue'], 2); ?></td>
                                    </tr>
                                    <tr style="border-bottom:1px solid #F3F4F6;">
                                        <td style="padding:14px 0;color:#374151;font-weight:600;padding-left:20px;">Less: Refunds</td>
                                        <td style="padding:14px 0;text-align:right;font-weight:700;color:#DC2626;">-₱<?php echo number_format($refunds['total_refunded'], 2); ?></td>
                                    </tr>
                                    <tr style="border-bottom:1px solid #F3F4F6;background:#F9FAFB;">
                                        <td style="padding:14px 0;color:#111827;font-weight:800;">Net Revenue</td>
                                        <td style="padding:14px 0;text-align:right;font-weight:800;color:#4F46E5;">₱<?php echo number_format($net_revenue, 2); ?></td>
                                    </tr>
                                    <tr style="border-bottom:1px solid #F3F4F6;">
                                        <td style="padding:14px 0;color:#374151;font-weight:600;">Less: VAT Collected (for BIR)</td>
                                        <td style="padding:14px 0;text-align:right;font-weight:700;color:#D97706;">-₱<?php echo number_format($rev['vat_collected'], 2); ?></td>
                                    </tr>
                                    <tr style="border-bottom:1px solid #F3F4F6;">
                                        <td style="padding:14px 0;color:#374151;font-weight:600;">Less: Operating Expenses</td>
                                        <td style="padding:14px 0;text-align:right;font-weight:700;color:#D97706;">-₱<?php echo number_format($expenses['total_expenses'], 2); ?></td>
                                    </tr>
                                    <tr style="border-top:3px solid #111827;background:#EEF2FF;">
                                        <td style="padding:20px 0;color:#111827;font-weight:800;font-size:16px;">NET PROFIT</td>
                                        <td style="padding:20px 0;text-align:right;font-weight:800;font-size:22px;color:#4F46E5;">₱<?php echo number_format($gross_profit, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding:12px 0;text-align:center;color:#6B7280;font-size:13px;">
                                            Profit Margin: <strong style="color:#4F46E5;"><?php echo number_format($profit_margin, 2); ?>%</strong> 
                                            &nbsp;·&nbsp; 
                                            Average Order: <strong>₱<?php echo number_format($rev['avg_order'], 2); ?></strong>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Profit Trend Chart -->
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">Daily Profit Trend</div>
                                    <div class="fin-card-subtitle">Revenue vs Expenses by day</div>
                                </div>
                            </div>
                            <div class="fin-chart-wrapper">
                                <canvas id="reportChart"></canvas>
                            </div>
                        </div>
                    
                    <!-- ============================================ -->
                    <!-- TAB CONTENT: PAYMENT REPORT -->
                    <!-- ============================================ -->
                    <?php elseif ($report_type == 'payment'): ?>
                    
                        <div class="fin-card">
                            <div class="fin-card-header">
                                <div class="fin-card-title-wrap">
                                    <div class="fin-card-title">💳 Payment Methods</div>
                                    <div class="fin-card-subtitle">Breakdown by payment type</div>
                                </div>
                            </div>
                            
                            <?php if (!empty($payment_methods)): ?>
                            <div class="fin-report-list">
                                <?php 
                                $total_payments = array_sum(array_column($payment_methods, 'total'));
                                foreach ($payment_methods as $pm): 
                                    $pct = $total_payments > 0 ? ($pm['total'] / $total_payments) * 100 : 0;
                                    $icons = ['cash' => '💵', 'card' => '💳', 'gcash' => '📱', 'maya' => '📲'];
                                    $icon = $icons[strtolower($pm['method'])] ?? '💰';
                                ?>
                                <div class="fin-report-row" style="flex-direction:column;align-items:stretch;gap:10px;">
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <div style="font-size:24px;"><?php echo $icon; ?></div>
                                        <div class="fin-report-info">
                                            <div class="fin-report-name"><?php echo ucfirst($pm['method']); ?></div>
                                            <div class="fin-report-meta"><?php echo number_format($pm['count']); ?> transactions · <?php echo number_format($pct, 1); ?>%</div>
                                        </div>
                                        <div class="fin-report-value">₱<?php echo number_format($pm['total'], 2); ?></div>
                                    </div>
                                    <div style="height:6px;background:#F3F4F6;border-radius:999px;overflow:hidden;">
                                        <div style="height:100%;width:<?php echo $pct; ?>%;background:linear-gradient(90deg,#6366F1,#8B5CF6);border-radius:999px;"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="fin-empty"><div class="fin-empty-icon">💳</div><div class="fin-empty-title">No payment data</div></div>
                            <?php endif; ?>
                        </div>
                    
                    <?php endif; ?>
                    
                    <!-- ============================================ -->
                    <!-- ADD EXPENSE MODAL (hidden by default) -->
                    <!-- ============================================ -->
                    <div class="modal" id="addExpenseModal">
                        <div class="modal-content" style="max-width:480px;">
                            <div style="display:flex;align-items:center;gap:14px;padding-bottom:18px;margin-bottom:20px;border-bottom:1px solid #F3F4F6;">
                                <div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#F59E0B 0%,#D97706 100%);display:flex;align-items:center;justify-content:center;color:white;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                </div>
                                <div style="flex:1;">
                                    <h2 style="font-size:20px;font-weight:800;color:#111827;margin:0;">Add Expense</h2>
                                    <p style="font-size:13px;color:#6B7280;margin:2px 0 0;">Record a new business expense</p>
                                </div>
                                <button onclick="closeModal('addExpenseModal')" style="width:32px;height:32px;border:none;background:transparent;color:#9CA3AF;font-size:22px;cursor:pointer;border-radius:8px;">&times;</button>
                            </div>
                            
                            <form onsubmit="saveExpense(event)">
                                <div style="margin-bottom:16px;">
                                    <label style="display:block;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.04em;">Date</label>
                                    <input type="date" id="expenseDate" value="<?php echo date('Y-m-d'); ?>" required style="width:100%;padding:13px 16px;border:2px solid #E5E7EB;border-radius:12px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box;">
                                </div>
                                <div style="margin-bottom:16px;">
                                    <label style="display:block;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.04em;">Category</label>
                                    <select id="expenseCategory" required style="width:100%;padding:13px 16px;border:2px solid #E5E7EB;border-radius:12px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box;background:white;">
                                        <option value="Payroll">Payroll & Salaries</option>
                                        <option value="Rent">Rent & Utilities</option>
                                        <option value="Supplies">Store Supplies</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Transportation">Transportation</option>
                                        <option value="Taxes">Taxes & Fees</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div style="margin-bottom:16px;">
                                    <label style="display:block;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.04em;">Amount (₱)</label>
                                    <input type="number" step="0.01" id="expenseAmount" required min="0" style="width:100%;padding:13px 16px;border:2px solid #E5E7EB;border-radius:12px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box;">
                                </div>
                                <div style="margin-bottom:20px;">
                                    <label style="display:block;font-size:12.5px;font-weight:700;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.04em;">Description</label>
                                    <textarea id="expenseDescription" rows="3" style="width:100%;padding:13px 16px;border:2px solid #E5E7EB;border-radius:12px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box;resize:vertical;"></textarea>
                                </div>
                                
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;padding-top:16px;border-top:1px solid #F3F4F6;">
                                    <button type="button" onclick="closeModal('addExpenseModal')" style="padding:14px;background:#F3F4F6;color:#4B5563;border:1.5px solid #E5E7EB;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;">Cancel</button>
                                    <button type="submit" style="padding:14px;background:linear-gradient(135deg,#F59E0B,#D97706);color:white;border:none;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;box-shadow:0 8px 20px rgba(245,158,11,0.3);">Save Expense</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Chart Script -->
                    <script>
                        (function() {
                            var canvas = document.getElementById('reportChart');
                            if (!canvas) return;
                            
                            var labels = <?php echo json_encode(array_map(function($d) { return date('M d', strtotime($d['date'])); }, $daily)); ?>;
                            var revenueData = <?php echo json_encode(array_map(function($d) { return (float)$d['revenue']; }, $daily)); ?>;
                            var vatData = <?php echo json_encode(array_map(function($d) { return (float)$d['vat']; }, $daily)); ?>;
                            
                            var ctx = canvas.getContext('2d');
                            var gradient = ctx.createLinearGradient(0, 0, 0, 340);
                            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
                            gradient.addColorStop(1, 'rgba(99, 102, 241, 0.02)');
                            
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Revenue (₱)',
                                        data: revenueData,
                                        backgroundColor: gradient,
                                        borderColor: '#6366F1',
                                        borderWidth: 3,
                                        pointBackgroundColor: '#6366F1',
                                        pointBorderColor: '#FFFFFF',
                                        pointBorderWidth: 3,
                                        pointRadius: 4,
                                        pointHoverRadius: 7,
                                        tension: 0.4,
                                        fill: true
                                    }, {
                                        label: 'VAT (₱)',
                                        data: vatData,
                                        borderColor: '#F59E0B',
                                        borderWidth: 2,
                                        borderDash: [5, 5],
                                        pointBackgroundColor: '#F59E0B',
                                        pointRadius: 3,
                                        tension: 0.4,
                                        fill: false
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                            align: 'end',
                                            labels: { font: { size: 12, weight: '600', family: 'Inter' }, padding: 14, usePointStyle: true, boxWidth: 8 }
                                        },
                                        tooltip: {
                                            backgroundColor: '#1E1B4B',
                                            padding: 14,
                                            cornerRadius: 10,
                                            callbacks: {
                                                label: function(c) {
                                                    return '  ' + c.dataset.label + ': ₱' + c.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            grid: { color: '#F3F4F6', drawBorder: false },
                                            ticks: {
                                                font: { size: 11 },
                                                color: '#9CA3AF',
                                                callback: function(v) { return v >= 1000 ? '₱' + (v/1000).toFixed(0) + 'k' : '₱' + v; }
                                            }
                                        },
                                        x: { grid: { display: false }, ticks: { font: { size: 11, weight: '600' }, color: '#6B7280' } }
                                    }
                                }
                            });
                        })();
                        
                        // ============================================
                        // EXPENSE MODAL FUNCTIONS
                        // ============================================
                        function showAddExpense() {
                            document.getElementById('addExpenseModal').classList.add('show');
                        }
                        
                        function saveExpense(e) {
                            e.preventDefault();
                            var data = new FormData();
                            data.append('expense_date', document.getElementById('expenseDate').value);
                            data.append('category', document.getElementById('expenseCategory').value);
                            data.append('amount', document.getElementById('expenseAmount').value);
                            data.append('description', document.getElementById('expenseDescription').value);
                            
                            fetch('?action=save_expense', { method: 'POST', body: data })
                            .then(function(res) { return res.json(); })
                            .then(function(result) {
                                if (result.success) {
                                    if (window.showToast) showToast('success', 'Expense Saved', 'The expense has been recorded', 3000);
                                    closeModal('addExpenseModal');
                                    setTimeout(function() { location.reload(); }, 800);
                                } else {
                                    alert('Error: ' + (result.message || 'Failed to save'));
                                }
                            });
                        }
                        
                        // ============================================
                        // EXPORT CURRENT REPORT TO CSV
                        // ============================================
                        function exportCurrentReport() {
                            var type = '<?php echo $report_type; ?>';
                            var start = '<?php echo $start_date; ?>';
                            var end = '<?php echo $end_date; ?>';
                            window.location.href = '?action=export_report&type=' + type + '&start_date=' + start + '&end_date=' + end;
                        }
                        
                        function closeModal(id) {
                            document.getElementById(id).classList.remove('show');
                        }
                    </script>

                    <?php
                


                // ============================================
// RETURNS PAGE
                // ============================================
break;
endswitch;
