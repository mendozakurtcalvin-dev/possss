<?php
switch ('finance_dashboard'):
case 'finance_dashboard':

        if (!canAccess('finance')) {
            echo '<div class="alert alert-danger" style="margin:2rem;text-align:center;">
                <div style="font-size:4rem;margin-bottom:1rem;"></div>
                <h2>Access Denied</h2>
                <p>You do not have permission to access this page.</p>
                <a href="?page=dashboard" class="btn btn-primary" style="margin-top:1rem;">Go to Dashboard</a>
            </div>';
            break;
        }
        $financeStats = $financeManager->getDashboardStats();
        $monthlyRevenue = $financeManager->getMonthlyRevenue();
        $topCustomers = $financeManager->getTopCustomers(5);
        $revenueByDay = $financeManager->getRevenueByDay(7);
        ?>
        
        <!-- ============================================ -->
        <!-- FINANCE DASHBOARD - MODERN REDESIGN --> 
        <!-- ============================================ -->
        
        <!-- Page Header -->
        <div class="fin-page-header">
            <div>
                <h2 class="fin-page-title">
                    <span class="fin-page-icon"></span>
                    Finance Dashboard
                </h2>
                <p class="fin-page-subtitle">Real-time revenue and financial analytics</p>
            </div>
            <div class="fin-date-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>
        
        <!-- Hero Revenue Banner -->
        <div class="fin-hero">
            <div class="fin-hero-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="fin-hero-content">
                <div class="fin-hero-label">Total Revenue — All Time</div>
                <div class="fin-hero-value">₱<?php echo number_format($financeStats['total'], 2); ?></div>
                <div class="fin-hero-sub">
                    Based on <strong><?php echo number_format($financeStats['transactions']); ?></strong> total transactions
                </div>
            </div>
            <div class="fin-hero-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                    <polyline points="17 6 23 6 23 12"></polyline>
                </svg>
                Live
            </div>
        </div>
        
        <!-- KPI Stats Grid -->
        <div class="fin-stats-grid">
            
            <!-- Today -->
            <div class="fin-stat-card">
                <div class="fin-stat-header">
                    <div class="fin-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <div class="fin-stat-badge fin-stat-badge-green">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                        Today
                    </div>
                </div>
                <div class="fin-stat-label">Today's Revenue</div>
                <div class="fin-stat-value fin-stat-value-green">₱<?php echo number_format($financeStats['today'], 2); ?></div>
                <div class="fin-stat-footer">Revenue collected today</div>
            </div>
            
            <!-- This Month -->
            <div class="fin-stat-card">
                <div class="fin-stat-header">
                    <div class="fin-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <div class="fin-stat-badge"><?php echo date('M Y'); ?></div>
                </div>
                <div class="fin-stat-label">This Month</div>
                <div class="fin-stat-value">₱<?php echo number_format($financeStats['month'], 2); ?></div>
                <div class="fin-stat-footer">Current month revenue</div>
            </div>
            
            <!-- Year to Date -->
            <div class="fin-stat-card">
                <div class="fin-stat-header">
                    <div class="fin-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="17 1 21 5 17 9"></polyline>
                            <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                            <polyline points="7 23 3 19 7 15"></polyline>
                            <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                        </svg>
                    </div>
                    <div class="fin-stat-badge fin-stat-badge-violet"><?php echo date('Y'); ?></div>
                </div>
                <div class="fin-stat-label">Year to Date</div>
                <div class="fin-stat-value">₱<?php echo number_format($financeStats['year'], 2); ?></div>
                <div class="fin-stat-footer">Revenue this year</div>
            </div>
            
            <!-- Total Customers -->
            <div class="fin-stat-card">
                <div class="fin-stat-header">
                    <div class="fin-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="fin-stat-badge">Total</div>
                </div>
                <div class="fin-stat-label">Total Customers</div>
                <div class="fin-stat-value"><?php echo number_format($financeStats['customers']); ?></div>
                <div class="fin-stat-footer">Registered customers</div>
            </div>
            
        </div>
        
        <!-- Monthly Revenue Chart -->
        <div class="fin-card">
            <div class="fin-card-header">
                <div class="fin-card-title-wrap">
                    <div class="fin-card-title">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        Monthly Revenue <?php echo date('Y'); ?>
                    </div>
                    <div class="fin-card-subtitle">Revenue and transaction trends by month</div>
                </div>
            </div>
            <div class="fin-chart-wrapper">
                <canvas id="financeMonthlyChart"></canvas>
            </div>
        </div>
        
        <!-- Two Column Grid: Daily Revenue + Top Customers -->
        <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px;margin-bottom:24px;" class="fin-two-col">
            
            <!-- Daily Revenue Chart -->
            <div class="fin-card" style="margin-bottom:0;">
                <div class="fin-card-header">
                    <div class="fin-card-title-wrap">
                        <div class="fin-card-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                            Daily Revenue
                        </div>
                        <div class="fin-card-subtitle">Last 7 days performance</div>
                    </div>
                </div>
                <div class="fin-chart-wrapper" style="height:300px;">
                    <canvas id="financeDailyChart"></canvas>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="fin-card" style="margin-bottom:0;">
                <div class="fin-card-header">
                    <div class="fin-card-title-wrap">
                        <div class="fin-card-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            Top Customers
                        </div>
                        <div class="fin-card-subtitle">By total spending</div>
                    </div>
                </div>
                
                <?php if (!empty($topCustomers)): ?>
                <div class="fin-customers-list">
                    <?php 
                    $rank = 1;
                    foreach ($topCustomers as $c): 
                        $rankClass = '';
                        if ($rank == 1) $rankClass = 'gold';
                        else if ($rank == 2) $rankClass = 'silver';
                        else if ($rank == 3) $rankClass = 'bronze';
                    ?>
                    <div class="fin-customer-row">
                        <div class="fin-customer-rank <?php echo $rankClass; ?>"><?php echo $rank; ?></div>
                        <div class="fin-customer-avatar"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></div>
                        <div class="fin-customer-info">
                            <div class="fin-customer-name"><?php echo htmlspecialchars($c['name']); ?></div>
                            <div class="fin-customer-meta"><?php echo (int)$c['orders']; ?> orders ·  <?php echo (int)($c['loyalty_points'] ?? 0); ?> pts</div>
                        </div>
                        <div class="fin-customer-amount">
                            <div class="fin-customer-amount-value">₱<?php echo number_format($c['total_spent'], 2); ?></div>
                            <div class="fin-customer-amount-orders">Total spent</div>
                        </div>
                    </div>
                    <?php 
                    $rank++;
                    endforeach; 
                    ?>
                </div>
                <?php else: ?>
                <div class="fin-empty">
                    <div class="fin-empty-icon"></div>
                    <div class="fin-empty-title">No customer data yet</div>
                    <div class="fin-empty-text">Customer spending will appear here</div>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
        
        <script>
            // ============================================
            // FINANCE DASHBOARD CHARTS
            // ============================================
            
            // -------- Monthly Revenue Chart --------
            var monthlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var monthlyData = <?php echo json_encode(array_values(array_map(function($m) { return (float)$m['revenue']; }, $monthlyRevenue))); ?>;
            var transactionData = <?php echo json_encode(array_values(array_map(function($m) { return (int)$m['transactions']; }, $monthlyRevenue))); ?>;
            
            var ctx1 = document.getElementById('financeMonthlyChart').getContext('2d');
            
            // Create gradient for bars
            var gradientPurple = ctx1.createLinearGradient(0, 0, 0, 400);
            gradientPurple.addColorStop(0, 'rgba(139, 92, 246, 0.85)');
            gradientPurple.addColorStop(1, 'rgba(99, 102, 241, 0.35)');
            
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        {
                            label: 'Revenue (₱)',
                            data: monthlyData,
                            backgroundColor: gradientPurple,
                            borderColor: '#6366F1',
                            borderWidth: 0,
                            borderRadius: 8,
                            borderSkipped: false,
                            order: 2,
                            maxBarThickness: 40
                        },
                        {
                            label: 'Transactions',
                            data: transactionData,
                            type: 'line',
                            backgroundColor: 'rgba(245, 158, 11, 0.05)',
                            borderColor: '#F59E0B',
                            borderWidth: 3,
                            pointBackgroundColor: '#F59E0B',
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 3,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            tension: 0.4,
                            order: 1,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                font: { size: 12, weight: '600', family: 'Inter' },
                                padding: 14,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8,
                                color: '#4B5563'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1E1B4B',
                            padding: 14,
                            titleFont: { size: 13, weight: 'bold', family: 'Inter' },
                            bodyFont: { size: 13, family: 'Inter' },
                            cornerRadius: 10,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Revenue (₱)') {
                                        return '  Revenue: ₱' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                    return '  Transactions: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#F3F4F6', drawBorder: false },
                            ticks: {
                                font: { size: 11, family: 'Inter' },
                                color: '#9CA3AF',
                                callback: function(value) {
                                    if (value >= 1000) return '₱' + (value / 1000).toFixed(0) + 'k';
                                    return '₱' + value;
                                }
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false, drawBorder: false },
                            ticks: {
                                font: { size: 11, family: 'Inter' },
                                color: '#9CA3AF',
                                callback: function(value) { return value; }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11, weight: '600', family: 'Inter' },
                                color: '#6B7280'
                            }
                        }
                    }
                }
            });
            
            // -------- Daily Revenue Chart --------
            var dailyLabels = <?php echo json_encode(array_map(function($d) { return date('M d', strtotime($d['date'])); }, $revenueByDay)); ?>;
            var dailyRevenue = <?php echo json_encode(array_map(function($d) { return (float)$d['revenue']; }, $revenueByDay)); ?>;
            var dailyTransactions = <?php echo json_encode(array_map(function($d) { return (int)$d['transactions']; }, $revenueByDay)); ?>;
            
            var ctx2 = document.getElementById('financeDailyChart').getContext('2d');
            
            // Gradient for daily chart
            var gradientEmerald = ctx2.createLinearGradient(0, 0, 0, 300);
            gradientEmerald.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
            gradientEmerald.addColorStop(1, 'rgba(16, 185, 129, 0.02)');
            
            new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [
                        {
                            label: 'Daily Revenue (₱)',
                            data: dailyRevenue,
                            backgroundColor: gradientEmerald,
                            borderColor: '#10B981',
                            borderWidth: 3,
                            pointBackgroundColor: '#10B981',
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 3,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            tension: 0.4,
                            fill: true,
                            order: 2
                        },
                        {
                            label: 'Transactions',
                            data: dailyTransactions,
                            type: 'bar',
                            backgroundColor: 'rgba(99, 102, 241, 0.25)',
                            borderColor: 'rgba(99, 102, 241, 0.5)',
                            borderWidth: 1,
                            borderRadius: 6,
                            borderSkipped: false,
                            order: 1,
                            yAxisID: 'y1',
                            maxBarThickness: 30
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                font: { size: 12, weight: '600', family: 'Inter' },
                                padding: 14,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8,
                                color: '#4B5563'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1E1B4B',
                            padding: 14,
                            titleFont: { size: 13, weight: 'bold', family: 'Inter' },
                            bodyFont: { size: 13, family: 'Inter' },
                            cornerRadius: 10,
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Daily Revenue (₱)') {
                                        return '  Revenue: ₱' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                    return '  Transactions: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#F3F4F6', drawBorder: false },
                            ticks: {
                                font: { size: 11, family: 'Inter' },
                                color: '#9CA3AF',
                                callback: function(value) {
                                    if (value >= 1000) return '₱' + (value / 1000).toFixed(0) + 'k';
                                    return '₱' + value;
                                }
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false, drawBorder: false },
                            ticks: {
                                font: { size: 11, family: 'Inter' },
                                color: '#9CA3AF',
                                callback: function(value) { return value; }
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 11, weight: '600', family: 'Inter' },
                                color: '#6B7280'
                            }
                        }
                    }
                }
            });
        </script>
        
        <?php
        // PROCUREMENT PAYABLES (supplier invoices & payments)
        require_once __DIR__ . '/proc_common.php';
        $payablesOutstanding = 0;
        $openInvoiceCount = 0;
        $awaitingInvoiceCount = 0;
        $openInvoices = [];
        $recentSupplierPayments = [];
        try {
            $payablesOutstanding = floatval($pdo->query("SELECT COALESCE(SUM(amount - paid_amount), 0) FROM procurement_invoices WHERE payment_status IN ('unpaid','partial')")->fetchColumn());
            $openInvoiceCount = (int) $pdo->query("SELECT COUNT(*) FROM procurement_invoices WHERE payment_status IN ('unpaid','partial')")->fetchColumn();
            $awaitingInvoiceCount = (int) $pdo->query("
                SELECT COUNT(*) FROM procurement_purchase_orders po
                WHERE po.status IN ('received','closed','delivered')
                  AND NOT EXISTS (SELECT 1 FROM procurement_invoices i WHERE i.po_id = po.id)
            ")->fetchColumn();
            $openInvoices = $pdo->query("
                SELECT i.*, po.po_number, s.name AS supplier_name
                FROM procurement_invoices i
                LEFT JOIN procurement_purchase_orders po ON i.po_id = po.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.payment_status IN ('unpaid','partial')
                ORDER BY i.created_at DESC
                LIMIT 10
            ")->fetchAll();
            $recentSupplierPayments = $pdo->query("
                SELECT p.*, i.invoice_number, s.name AS supplier_name
                FROM procurement_payments p
                JOIN procurement_invoices i ON i.id = p.invoice_id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                ORDER BY COALESCE(p.payment_date, p.created_at) DESC, p.id DESC
                LIMIT 6
            ")->fetchAll();
        } catch (Exception $e) {
            error_log('Finance payables load failed: ' . $e->getMessage());
        }
        ?>
        <div style="margin-top:26px;">
            <div class="fin-page-header" style="margin-bottom:14px;">
                <div>
                    <h2 class="fin-page-title" style="font-size:20px;">&#128176; Procurement Payables</h2>
                    <p class="fin-page-subtitle">Supplier invoices, outstanding balances and payments recorded by Finance</p>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:18px;">
                <div class="fin-stat-card">
                    <div class="fin-stat-header"><div class="fin-stat-icon" style="background:linear-gradient(135deg,#EF4444,#DC2626);">&#9888;</div></div>
                    <div class="fin-stat-label">Outstanding Payables</div>
                    <div class="fin-stat-value">&#8369;<?php echo number_format($payablesOutstanding, 2); ?></div>
                </div>
                <div class="fin-stat-card">
                    <div class="fin-stat-header"><div class="fin-stat-icon" style="background:linear-gradient(135deg,#F59E0B,#D97706);">&#128221;</div></div>
                    <div class="fin-stat-label">Unpaid / Partial Invoices</div>
                    <div class="fin-stat-value"><?php echo number_format($openInvoiceCount); ?></div>
                </div>
                <div class="fin-stat-card">
                    <div class="fin-stat-header"><div class="fin-stat-icon" style="background:linear-gradient(135deg,#0EA5E9,#0284C7);">&#128230;</div></div>
                    <div class="fin-stat-label">Received POs Awaiting Invoice</div>
                    <div class="fin-stat-value"><?php echo number_format($awaitingInvoiceCount); ?></div>
                </div>
            </div>

            <div class="fin-card" style="margin-bottom:18px;">
                <div class="fin-card-header">
                    <div class="fin-card-title-wrap">
                        <div class="fin-card-title">&#128221; Open Supplier Invoices</div>
                        <div class="fin-card-subtitle">Record payments — partial payments are supported</div>
                    </div>
                </div>
                <?php if (count($openInvoices) === 0): ?>
                <div class="fin-empty"><div class="fin-empty-icon">&#128269;</div><div class="fin-empty-title">No open supplier invoices</div></div>
                <?php else: ?>
                <div class="fin-table-wrap">
                    <table class="fin-table">
                        <thead><tr><th>Invoice #</th><th>PO</th><th>Supplier</th><th>Amount</th><th>Paid</th><th>Outstanding</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($openInvoices as $oi): $oiOutstanding = floatval($oi['amount']) - floatval($oi['paid_amount']); ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($oi['invoice_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($oi['po_number'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($oi['supplier_name'] ?? '—'); ?></td>
                                <td>&#8369;<?php echo number_format($oi['amount'], 2); ?></td>
                                <td>&#8369;<?php echo number_format($oi['paid_amount'], 2); ?></td>
                                <td><strong>&#8369;<?php echo number_format(max(0, $oiOutstanding), 2); ?></strong></td>
                                <td><?php echo procPaymentBadge($oi['payment_status']); ?></td>
                                <td style="text-align:right;">
                                    <button type="button" class="fin-btn-primary" style="padding:6px 12px;font-size:12px;" onclick="openSupplierPayment(<?php echo (int) $oi['id']; ?>, '<?php echo htmlspecialchars($oi['invoice_number'], ENT_QUOTES); ?>', <?php echo max(0, $oiOutstanding); ?>)">Record Payment</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="fin-card">
                <div class="fin-card-header">
                    <div class="fin-card-title-wrap">
                        <div class="fin-card-title">&#128176; Recent Supplier Payments</div>
                        <div class="fin-card-subtitle">Latest payment records</div>
                    </div>
                </div>
                <?php if (count($recentSupplierPayments) === 0): ?>
                <div class="fin-empty"><div class="fin-empty-icon">&#128269;</div><div class="fin-empty-title">No supplier payments recorded yet</div></div>
                <?php else: ?>
                <div class="fin-table-wrap">
                    <table class="fin-table">
                        <thead><tr><th>Date</th><th>Invoice #</th><th>Supplier</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentSupplierPayments as $rsp): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rsp['payment_date'] ?? date('Y-m-d', strtotime($rsp['created_at']))); ?></td>
                                <td><strong><?php echo htmlspecialchars($rsp['invoice_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($rsp['supplier_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars(str_replace('_', ' ', $rsp['provider'])); ?></td>
                                <td><?php echo htmlspecialchars($rsp['reference'] ?? '—'); ?></td>
                                <td>&#8369;<?php echo number_format($rsp['amount'], 2); ?></td>
                                <td><span class="badge <?php echo $rsp['status'] === 'paid' ? 'badge-success' : ($rsp['status'] === 'failed' ? 'badge-danger' : 'badge-warning'); ?>"><?php echo htmlspecialchars(ucfirst($rsp['status'])); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="supplierPaymentModal" class="inv-modal" style="display:none;">
            <div class="inv-modal-content" style="max-width:480px;">
                <h3>Record Supplier Payment</h3>
                <p style="font-size:13px;color:#6b7280;" id="spInvoiceLabel"></p>
                <form id="supplierPaymentForm">
                    <input type="hidden" name="invoice_id" id="spInvoiceId">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                    <label>Payment Amount *</label>
                    <input type="number" name="amount" id="spAmount" step="0.01" min="0.01" required style="width:100%;">
                    <label>Payment Method</label>
                    <select name="method" style="width:100%;">
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="check">Check</option>
                        <option value="cash">Cash</option>
                        <option value="gcash">GCash</option>
                        <option value="card">Card</option>
                    </select>
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" style="width:100%;">
                    <label>Reference / Check #</label>
                    <input name="reference" style="width:100%;" placeholder="Optional but recommended">
                    <p style="font-size:12px;color:#6b7280;margin-top:8px;">Partial payments are allowed. The invoice stays <strong>Partially Paid</strong> until the balance clears. Duplicate references are rejected.</p>
                    <div style="margin-top:1rem;display:flex;gap:8px;">
                        <button type="submit" class="fin-btn-primary">Record Payment</button>
                        <button type="button" class="inv-btn-cancel" onclick="closeModal('supplierPaymentModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openSupplierPayment(invoiceId, invoiceNumber, outstanding) {
            document.getElementById('spInvoiceId').value = invoiceId;
            document.getElementById('spInvoiceLabel').textContent = 'Invoice ' + invoiceNumber + ' — outstanding balance ₱' + Number(outstanding).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            var amountInput = document.getElementById('spAmount');
            amountInput.value = Number(outstanding).toFixed(2);
            amountInput.max = Number(outstanding).toFixed(2);
            document.getElementById('supplierPaymentModal').style.display = 'flex';
        }
        document.getElementById('supplierPaymentForm').addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fetch('?action=record_supplier_payment', { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) {
                        closeModal('supplierPaymentModal');
                        showToast('success', 'Payment recorded', 'Status: ' + String(d.payment_status).replace(/_/g, ' ') + '. Outstanding: ₱' + Number(d.outstanding).toLocaleString('en-PH', { minimumFractionDigits: 2 }));
                        setTimeout(function () { window.location.reload(); }, 1000);
                    } else {
                        showToast('error', 'Payment not recorded', d.message || '');
                    }
                })
                .catch(function () { showToast('error', 'Connection error', 'Please try again.'); });
        });
        </script>


        <?php
        

            // ============================================
            // FINANCE REPORTS PAGE
            // ============================================
break;
endswitch;
