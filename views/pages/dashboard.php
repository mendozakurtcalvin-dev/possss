<?php
switch ('dashboard'):
case 'dashboard':

                $stats = $reportManager->getStats();
                $recentSales = $saleManager->getSales(5);
                $recentActivities = $activityLogManager->getRecentActivities(5);
                $totalRevenue = $stats['total_sales'];
                $monthlyRevenue = $stats['month'];
                $totalTransactions = $stats['total_transactions'];
                ?>
                <!-- ============================================ -->
            <!-- DASHBOARD - PROFESSIONAL ADMIN VIEW -->
            <!-- ============================================ -->

            <!-- Welcome Hero -->
            <div class="dash-hero">
                <div class="dash-hero-content">
                    <div class="dash-hero-text">
                        <div class="dash-greeting">Welcome back, <strong><?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></strong></div>
                        <h1 class="dash-title">Admin Dashboard</h1>
                        <p class="dash-subtitle"><?php echo date('l, F j, Y'); ?> &middot; Here's what's happening today</p>
                    </div>
                    <?php if (!hasRole('inventory')): ?>
                        <div class="dash-hero-actions">
                            <a href="?page=cart" class="dash-hero-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                                New Sale
                            </a>
                            <a href="?page=reports" class="dash-hero-btn dash-hero-btn-outline">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                                View Reports
                            </a>
                        </div>
                        <?php endif; ?>
                </div>
            </div>

            <!-- KPI Stats Grid -->
            <!-- ============================================ -->
                <!-- ROLE-AWARE KPI STATS GRID                    -->
                <!-- Shows different cards based on user role     -->
                <!-- ============================================ -->
                <?php 
                $userRole = getUserRoles()[0] ?? 'cashier';

                // --- Fetch role-specific data ---
                $todaySales    = $stats['today'];
                $monthRevenue  = $stats['month'];
                $totalTxns     = $stats['total_transactions'];
                $lowStock      = $stats['low'];
                $totalProducts = $pdo->query("SELECT COUNT(*) as c FROM products WHERE archived = 0")->fetch()['c'];
                $totalCustomers= $pdo->query("SELECT COUNT(*) as c FROM customers")->fetch()['c'];
                $totalEmployees= $pdo->query("SELECT COUNT(*) as c FROM employees WHERE status = 'active'")->fetch()['c'];
                $todayAttend   = $pdo->query("SELECT COUNT(*) as c FROM attendance WHERE date = CURDATE()")->fetch()['c'];
                $pendingLeaves = $pdo->query("SELECT COUNT(*) as c FROM leave_requests WHERE status = 'pending'")->fetch()['c'];
                $pendingPOs    = $pdo->query("SELECT COUNT(*) as c FROM purchases WHERE payment_status = 'unpaid'")->fetch()['c'];
                $totalSuppliers= $pdo->query("SELECT COUNT(*) as c FROM suppliers WHERE status = 'active'")->fetch()['c'];
                $totalReturns  = $pdo->query("SELECT COUNT(*) as c FROM returns WHERE status = 'pending'")->fetch()['c'];
                ?>

                <div class="dash-stats">
                    
                    <?php if ($userRole == 'admin'): ?>
                    
                    <!-- ===== ADMIN: High-level overview ===== -->
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend dash-trend-up">Today</div>
                        </div>
                        <div class="dash-stat-label">Today's Sales</div>
                        <div class="dash-stat-value">₱<?php echo number_format($todaySales, 2); ?></div>
                        <div class="dash-stat-footer"><?php echo $stats['count']; ?> transactions today</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                            </div>
                            <div class="dash-stat-trend"><?php echo date('M Y'); ?></div>
                        </div>
                        <div class="dash-stat-label">Monthly Revenue</div>
                        <div class="dash-stat-value">₱<?php echo number_format($monthRevenue, 2); ?></div>
                        <div class="dash-stat-footer">Current month total</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="17 1 21 5 17 9"></polyline>
                                    <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                                    <polyline points="7 23 3 19 7 15"></polyline>
                                    <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">All Time</div>
                        </div>
                        <div class="dash-stat-label">Total Transactions</div>
                        <div class="dash-stat-value"><?php echo number_format($totalTxns); ?></div>
                        <div class="dash-stat-footer">Across all cashiers</div>
                    </div>
                    
                    <div class="dash-stat <?php echo $lowStock > 0 ? 'dash-stat-warning' : ''; ?>">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                    <line x1="12" y1="9" x2="12" y2="13"></line>
                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                </svg>
                            </div>
                            <div class="dash-stat-trend <?php echo $lowStock > 0 ? 'dash-trend-warning' : ''; ?>"><?php echo $lowStock > 0 ? 'Action needed' : 'All good'; ?></div>
                        </div>
                        <div class="dash-stat-label">Low Stock Items</div>
                        <div class="dash-stat-value"><?php echo $lowStock; ?></div>
                        <div class="dash-stat-footer">Products at stock ≤ 5</div>
                    </div>
                    
                    <?php elseif ($userRole == 'cashier'): ?>
                    
                    <!-- ===== CASHIER: Sales-focused ===== -->
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend dash-trend-up">Today</div>
                        </div>
                        <div class="dash-stat-label">Today's Sales</div>
                        <div class="dash-stat-value">₱<?php echo number_format($todaySales, 2); ?></div>
                        <div class="dash-stat-footer"><?php echo $stats['count']; ?> transactions today</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">Today</div>
                        </div>
                        <div class="dash-stat-label">Transactions</div>
                        <div class="dash-stat-value"><?php echo $stats['count']; ?></div>
                        <div class="dash-stat-footer">Sales handled today</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">Customers</div>
                        </div>
                        <div class="dash-stat-label">Total Customers</div>
                        <div class="dash-stat-value"><?php echo number_format($totalCustomers); ?></div>
                        <div class="dash-stat-footer">Registered customers</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 1 10 7 10"></polyline>
                                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">Pending</div>
                        </div>
                        <div class="dash-stat-label">Pending Returns</div>
                        <div class="dash-stat-value"><?php echo $totalReturns; ?></div>
                        <div class="dash-stat-footer">Awaiting approval</div>
                    </div>
                    
                    <?php elseif ($userRole == 'inventory'): ?>
                    
                    <!-- ===== INVENTORY: Stock-focused ===== -->
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">Products</div>
                        </div>
                        <div class="dash-stat-label">Total Products</div>
                        <div class="dash-stat-value"><?php echo number_format($totalProducts); ?></div>
                        <div class="dash-stat-footer">Active products</div>
                    </div>
                    
                    <div class="dash-stat <?php echo $lowStock > 0 ? 'dash-stat-warning' : ''; ?>">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                    <line x1="12" y1="9" x2="12" y2="13"></line>
                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                </svg>
                            </div>
                            <div class="dash-stat-trend <?php echo $lowStock > 0 ? 'dash-trend-warning' : ''; ?>"><?php echo $lowStock > 0 ? 'Action needed' : 'All good'; ?></div>
                        </div>
                        <div class="dash-stat-label">Low Stock</div>
                        <div class="dash-stat-value"><?php echo $lowStock; ?></div>
                        <div class="dash-stat-footer">Need reorder</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="21" r="1"></circle>
                                    <circle cx="20" cy="21" r="1"></circle>
                                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">POs</div>
                        </div>
                        <div class="dash-stat-label">Unpaid POs</div>
                        <div class="dash-stat-value"><?php echo $pendingPOs; ?></div>
                        <div class="dash-stat-footer">Pending payments</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">Active</div>
                        </div>
                        <div class="dash-stat-label">Suppliers</div>
                        <div class="dash-stat-value"><?php echo $totalSuppliers; ?></div>
                        <div class="dash-stat-footer">Active suppliers</div>
                    </div>
                    
                    <?php elseif ($userRole == 'hr'): ?>
                    
                    <!-- ===== HR: Employee-focused ===== -->
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">Active</div>
                        </div>
                        <div class="dash-stat-label">Total Employees</div>
                        <div class="dash-stat-value"><?php echo $totalEmployees; ?></div>
                        <div class="dash-stat-footer">Currently active</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                            <div class="dash-stat-trend dash-trend-up">Today</div>
                        </div>
                        <div class="dash-stat-label">Today's Attendance</div>
                        <div class="dash-stat-value"><?php echo $todayAttend; ?></div>
                        <div class="dash-stat-footer">Clocked in today</div>
                    </div>
                    
                    <div class="dash-stat <?php echo $pendingLeaves > 0 ? 'dash-stat-warning' : ''; ?>">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                            <div class="dash-stat-trend <?php echo $pendingLeaves > 0 ? 'dash-trend-warning' : ''; ?>">Pending</div>
                        </div>
                        <div class="dash-stat-label">Leave Requests</div>
                        <div class="dash-stat-value"><?php echo $pendingLeaves; ?></div>
                        <div class="dash-stat-footer">Awaiting approval</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">This Month</div>
                        </div>
                        <div class="dash-stat-label">Payroll Processed</div>
                        <div class="dash-stat-value">₱0.00</div>
                        <div class="dash-stat-footer">Total salaries this month</div>
                    </div>
                    
                    <?php elseif ($userRole == 'finance'): ?>
                    
                    <!-- ===== FINANCE: Revenue-focused ===== -->
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend dash-trend-up">Today</div>
                        </div>
                        <div class="dash-stat-label">Today's Revenue</div>
                        <div class="dash-stat-value">₱<?php echo number_format($todaySales, 2); ?></div>
                        <div class="dash-stat-footer"><?php echo $stats['count']; ?> transactions</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                            </div>
                            <div class="dash-stat-trend"><?php echo date('M Y'); ?></div>
                        </div>
                        <div class="dash-stat-label">Monthly Revenue</div>
                        <div class="dash-stat-value">₱<?php echo number_format($monthRevenue, 2); ?></div>
                        <div class="dash-stat-footer">Current month total</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="17 1 21 5 17 9"></polyline>
                                    <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">All Time</div>
                        </div>
                        <div class="dash-stat-label">Total Transactions</div>
                        <div class="dash-stat-value"><?php echo number_format($totalTxns); ?></div>
                        <div class="dash-stat-footer">Across all cashiers</div>
                    </div>
                    
                    <div class="dash-stat">
                        <div class="dash-stat-header">
                            <div class="dash-stat-icon" style="background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 1 10 7 10"></polyline>
                                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                </svg>
                            </div>
                            <div class="dash-stat-trend">Pending</div>
                        </div>
                        <div class="dash-stat-label">Pending Returns</div>
                        <div class="dash-stat-value"><?php echo $totalReturns; ?></div>
                        <div class="dash-stat-footer">Awaiting approval</div>
                    </div>
                    
                    <?php endif; ?>
                    
                </div>

            <!-- Total Revenue Banner -->
            <div class="dash-revenue-banner">
                <div class="dash-revenue-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                        <path d="M2 17l10 5 10-5"></path>
                        <path d="M2 12l10 5 10-5"></path>
                    </svg>
                </div>
                <div class="dash-revenue-content">
                    <div class="dash-revenue-label">Total Revenue — All Time</div>
                    <div class="dash-revenue-value">₱<?php echo number_format($totalRevenue, 2); ?></div>
                    <div class="dash-revenue-sub">
                        <?php echo $totalTransactions > 0 
                            ? 'Calculated from ' . number_format($totalTransactions) . ' total transactions' 
                            : 'No sales recorded yet'; ?>
                    </div>
                </div>
                <div class="dash-revenue-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                        <polyline points="17 6 23 6 23 12"></polyline>
                    </svg>
                    Live
                </div>
            </div>

            <!-- Chart + Quick Actions Grid -->
            <div class="dash-grid-2col">
                
                <!-- Sales Chart -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <div class="dash-card-title" id="chartTitle">📈 Sales Overview</div>
                            <div class="dash-card-subtitle">Compare performance across weeks</div>
                        </div>
                        <div class="dash-chart-filters">
                            <button class="dash-filter-btn" onclick="updateChart('1week', this)">1W</button>
                            <button class="dash-filter-btn" onclick="updateChart('2weeks', this)">2W</button>
                            <button class="dash-filter-btn" onclick="updateChart('3weeks', this)">3W</button>
                            <button class="dash-filter-btn active" onclick="updateChart('4weeks', this)">4W</button>
                        </div>
                    </div>
                    <div class="dash-chart-wrapper">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                                <!-- ============================================ -->
                <!-- QUICK ACTIONS - ROLE-AWARE -->
                <!-- ============================================ -->
                <?php
                $userRole = getUserRoles()[0] ?? 'cashier';

                // Define quick actions per role
                $quickActions = [
                    'admin' => [
                        ['label' => 'New Sale', 'sub' => 'Open POS terminal', 'href' => '?page=cart', 'color' => 'linear-gradient(135deg, #6366F1 0%, #4F46E5 100%)', 'icon' => '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>'],
                        ['label' => 'Add Product', 'sub' => 'Create new item', 'href' => '?page=products', 'color' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)', 'icon' => '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>'],
                        ['label' => 'View Reports', 'sub' => 'Analyze sales', 'href' => '?page=reports', 'color' => 'linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%)', 'icon' => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>'],
                        ['label' => 'Manage Users', 'sub' => 'Staff & roles', 'href' => '?page=users', 'color' => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
                    ],
                    'cashier' => [
                        ['label' => 'New Sale', 'sub' => 'Start transaction', 'href' => '?page=cart', 'color' => 'linear-gradient(135deg, #6366F1 0%, #4F46E5 100%)', 'icon' => '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>'],
                        ['label' => 'Sales History', 'sub' => 'View transactions', 'href' => '?page=sales', 'color' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)', 'icon' => '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>'],
                        ['label' => 'Add Customer', 'sub' => 'Register new', 'href' => '?page=customers', 'color' => 'linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%)', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line>'],
                        ['label' => 'Returns', 'sub' => 'Process refunds', 'href' => '?page=returns', 'color' => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)', 'icon' => '<polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>'],
                    ],
                    'inventory' => [
                        ['label' => 'Products', 'sub' => 'Manage inventory', 'href' => '?page=products', 'color' => 'linear-gradient(135deg, #6366F1 0%, #4F46E5 100%)', 'icon' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>'],
                        ['label' => 'Categories', 'sub' => 'Organize products', 'href' => '?page=categories', 'color' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)', 'icon' => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line>'],
                        ['label' => 'Archive', 'sub' => 'View archived', 'href' => '?page=archive', 'color' => 'linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%)', 'icon' => '<path d="M21 8v13H3V8"></path><path d="M1 3h22v5H1z"></path><line x1="10" y1="12" x2="14" y2="12"></line>'],
                        ['label' => 'Returns', 'sub' => 'View returns', 'href' => '?page=returns', 'color' => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)', 'icon' => '<polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>'],
                    ],
                    'finance' => [
                        ['label' => 'Finance Dashboard', 'sub' => 'Revenue overview', 'href' => '?page=finance_dashboard', 'color' => 'linear-gradient(135deg, #6366F1 0%, #4F46E5 100%)', 'icon' => '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>'],
                        ['label' => 'Finance Reports', 'sub' => 'Financial analytics', 'href' => '?page=finance_reports', 'color' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)', 'icon' => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>'],
                        ['label' => 'Customer Reports', 'sub' => 'Spending insights', 'href' => '?page=customer_reports', 'color' => 'linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%)', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle>'],
                        ['label' => 'Returns', 'sub' => 'Approve refunds', 'href' => '?page=returns', 'color' => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)', 'icon' => '<polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>'],
                    ],
                    'hr' => [
                        ['label' => 'HR Dashboard', 'sub' => 'Employee overview', 'href' => '?page=hr', 'color' => 'linear-gradient(135deg, #6366F1 0%, #4F46E5 100%)', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
                        ['label' => 'Add Employee', 'sub' => 'Register staff', 'href' => '?page=hr&open=add_employee', 'color' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line>'],
                        ['label' => 'Attendance', 'sub' => 'Clock in/out', 'href' => '?page=hr&open=attendance', 'color' => 'linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%)', 'icon' => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>'],
                        ['label' => 'Payroll', 'sub' => 'Process salaries', 'href' => '?page=hr', 'color' => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)', 'icon' => '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>'],
                    ],
                ];

                // Fallback to admin if role not found
                if (!isset($quickActions[$userRole])) {
                    $quickActions[$userRole] = $quickActions['admin'];
                }

                $actions = $quickActions[$userRole];
                $roleIcon = [
                    'admin' => '👨‍💼',
                    'cashier' => '🛒',
                    'inventory' => '📦',
                    'finance' => '💰',
                    'hr' => '👥'
                ][$userRole] ?? '👤';

                $roleLabel = [
                    'admin' => 'Admin',
                    'cashier' => 'Cashier',
                    'inventory' => 'Inventory',
                    'finance' => 'Finance',
                    'hr' => 'HR'
                ][$userRole] ?? ucfirst($userRole);
                ?>

                <div class="dash-card">
                    <div class="dash-card-header">
                        <div>
                            <div class="dash-card-title">⚡ Quick Actions</div>
                            <div class="dash-card-subtitle"><?php echo $roleIcon; ?> <?php echo $roleLabel; ?> shortcuts</div>
                        </div>
                    </div>
                    <div class="dash-quick-actions">
                        <?php foreach ($actions as $action): ?>
                        <a href="<?php echo $action['href']; ?>" class="dash-quick-action">
                            <div class="dash-quick-icon" style="background: <?php echo $action['color']; ?>;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <?php echo $action['icon']; ?>
                                </svg>
                            </div>
                            <div class="dash-quick-text">
                                <strong><?php echo $action['label']; ?></strong>
                                <span><?php echo $action['sub']; ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            </div>

            <!-- Recent Sales -->
            <!-- Recent Sales - Hidden for Inventory role -->
            <?php if (!hasRole('inventory')): ?>
            <div class="dash-card">
                <div class="dash-card-header">
                    <div>
                        <div class="dash-card-title">🕐 Recent Sales</div>
                        <div class="dash-card-subtitle">Latest 5 transactions</div>
                    </div>
                    <a href="?page=sales" class="dash-view-all">
                        View All
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                </div>
                
                <?php if (!empty($recentSales)): ?>
                <div class="dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Cashier</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Change</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><span class="dash-invoice"><?php echo htmlspecialchars($sale['invoice_number']); ?></span></td>
                                <td>
                                    <div class="dash-user-cell">
                                        <div class="dash-user-avatar"><?php echo strtoupper(substr($sale['cashier'] ?? 'U', 0, 1)); ?></div>
                                        <span><?php echo htmlspecialchars($sale['cashier'] ?? 'N/A'); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($sale['customer'] ?? 'Walk-in'); ?></td>
                                <td><strong class="dash-amount">₱<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                <td>₱<?php echo number_format($sale['change_amount'] ?? 0, 2); ?></td>
                                <td class="dash-time"><?php echo date('M d, H:i', strtotime($sale['sale_date'])); ?></td>
                                <td><span class="dash-badge dash-badge-success">Paid</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="dash-empty">
                    <div class="dash-empty-icon">🧾</div>
                    <div class="dash-empty-title">No sales yet</div>
                    <div class="dash-empty-text">Start making sales to see them here</div>
                </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <!-- Recent Activity -->
            

            <!-- Detail Modal (kept for stat card clicks) -->
            <div class="modal" id="detailModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="detailTitle">Details</h2>
                        <button class="close" onclick="closeModal('detailModal')">&times;</button>
                    </div>
                    <div style="text-align:center;padding:1rem 0;">
                        <div style="font-size:3rem;font-weight:700;color:var(--primary-dark);" id="detailValue">-</div>
                        <div style="color:var(--text-light);" id="detailDesc">-</div>
                    </div>
                </div>
            </div>

            <!-- Dashboard JS (kept from before) -->
            <script>
                var chart = null;
                var currentView = '4weeks';
                
                // ============================================
                // CHART LOADING FUNCTIONS (kept from before)
                // ============================================
                
                function loadSingleWeek() {
                    var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    fetch('?action=get_chart_data&days=7')
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            if (data.length === 0) { showEmptyChart(); return; }
                            var weekTotals = data.map(function(d) { return parseFloat(d.total) || 0; });
                            while (weekTotals.length < 7) weekTotals.push(0);
                            var weekTotal = weekTotals.reduce(function(a, b) { return a + b; }, 0);
                            var ctx = document.getElementById('salesChart').getContext('2d');
                            if (chart) chart.destroy();
                            document.getElementById('chartTitle').textContent = '📈 This Week · ₱' + weekTotal.toFixed(2);
                            chart = new Chart(ctx, {
                                type: 'line',
                                data: { 
                                    labels: days, 
                                    datasets: [{ 
                                        label: 'This Week', 
                                        data: weekTotals, 
                                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                        borderColor: '#6366F1',
                                        borderWidth: 3,
                                        pointBackgroundColor: '#6366F1',
                                        pointBorderColor: '#FFFFFF',
                                        pointBorderWidth: 3,
                                        pointRadius: 5,
                                        pointHoverRadius: 7,
                                        tension: 0.4,
                                        fill: true
                                    }] 
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            backgroundColor: '#1E1B4B',
                                            padding: 12,
                                            titleFont: { size: 13, weight: 'bold' },
                                            bodyFont: { size: 13 },
                                            cornerRadius: 8,
                                            callbacks: {
                                                label: function(context) { return '₱' + context.parsed.y.toFixed(2); }
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
                                                callback: function(value) {
                                                    if (value >= 1000) return '₱' + (value / 1000).toFixed(1) + 'k';
                                                    return '₱' + value;
                                                }
                                            }
                                        },
                                        x: {
                                            grid: { display: false },
                                            ticks: { font: { size: 11 }, color: '#9CA3AF' }
                                        }
                                    }
                                }
                            });
                        })
                        ['catch'](function(error) { console.error(error); showEmptyChart(); });
                }
                
                function loadTwoWeeks() {
                    var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    fetch('?action=get_chart_data&days=14')
                        .then(function(res) { return res.json(); })
                        .then(function(allData) {
                            if (allData.length === 0) { showEmptyChart(); return; }
                            var datasets = [];
                            var colors = [
                                { bg: 'rgba(99, 102, 241, 0.1)', border: '#6366F1' },
                                { bg: 'rgba(139, 92, 246, 0.1)', border: '#8B5CF6' }
                            ];
                            for (var w = 0; w < 2; w++) {
                                var start = w * 7;
                                var end = Math.min(start + 7, allData.length);
                                var weekData = allData.slice(start, end);
                                var weekTotals = weekData.map(function(d) { return parseFloat(d.total) || 0; });
                                while (weekTotals.length < 7) weekTotals.push(0);
                                var weekTotal = weekTotals.reduce(function(a, b) { return a + b; }, 0);
                                datasets.push({ 
                                    label: 'Week ' + (w + 1) + ' · ₱' + weekTotal.toFixed(2), 
                                    data: weekTotals, 
                                    backgroundColor: colors[w].bg,
                                    borderColor: colors[w].border,
                                    borderWidth: 3,
                                    pointBackgroundColor: colors[w].border,
                                    pointBorderColor: '#FFFFFF',
                                    pointBorderWidth: 3,
                                    pointRadius: 5,
                                    tension: 0.4,
                                    fill: true
                                });
                            }
                            renderMultiChart(days, datasets, '📈 2-Week Comparison');
                        })
                        ['catch'](function(error) { console.error(error); showEmptyChart(); });
                }
                
                function loadThreeWeeks() {
                    var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    fetch('?action=get_chart_data&days=21')
                        .then(function(res) { return res.json(); })
                        .then(function(allData) {
                            if (allData.length === 0) { showEmptyChart(); return; }
                            var datasets = [];
                            var colors = [
                                { bg: 'rgba(99, 102, 241, 0.1)', border: '#6366F1' },
                                { bg: 'rgba(139, 92, 246, 0.1)', border: '#8B5CF6' },
                                { bg: 'rgba(16, 185, 129, 0.1)', border: '#10B981' }
                            ];
                            for (var w = 0; w < 3; w++) {
                                var start = w * 7;
                                var end = Math.min(start + 7, allData.length);
                                var weekData = allData.slice(start, end);
                                var weekTotals = weekData.map(function(d) { return parseFloat(d.total) || 0; });
                                while (weekTotals.length < 7) weekTotals.push(0);
                                var weekTotal = weekTotals.reduce(function(a, b) { return a + b; }, 0);
                                datasets.push({ 
                                    label: 'Week ' + (w + 1) + ' · ₱' + weekTotal.toFixed(2), 
                                    data: weekTotals, 
                                    backgroundColor: colors[w].bg,
                                    borderColor: colors[w].border,
                                    borderWidth: 3,
                                    pointBackgroundColor: colors[w].border,
                                    pointBorderColor: '#FFFFFF',
                                    pointBorderWidth: 3,
                                    pointRadius: 5,
                                    tension: 0.4,
                                    fill: true
                                });
                            }
                            renderMultiChart(days, datasets, '📈 3-Week Comparison');
                        })
                        ['catch'](function(error) { console.error(error); showEmptyChart(); });
                }
                
                function loadFourWeeks() {
                    var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                    fetch('?action=get_chart_data&days=28')
                        .then(function(res) { return res.json(); })
                        .then(function(allData) {
                            if (allData.length === 0) { showEmptyChart(); return; }
                            var datasets = [];
                            var colors = [
                                { bg: 'rgba(99, 102, 241, 0.1)', border: '#6366F1' },
                                { bg: 'rgba(139, 92, 246, 0.1)', border: '#8B5CF6' },
                                { bg: 'rgba(16, 185, 129, 0.1)', border: '#10B981' },
                                { bg: 'rgba(245, 158, 11, 0.1)', border: '#F59E0B' }
                            ];
                            for (var w = 0; w < 4; w++) {
                                var start = w * 7;
                                var end = Math.min(start + 7, allData.length);
                                var weekData = allData.slice(start, end);
                                var weekTotals = weekData.map(function(d) { return parseFloat(d.total) || 0; });
                                while (weekTotals.length < 7) weekTotals.push(0);
                                var weekTotal = weekTotals.reduce(function(a, b) { return a + b; }, 0);
                                datasets.push({ 
                                    label: 'Week ' + (w + 1) + ' · ₱' + weekTotal.toFixed(2), 
                                    data: weekTotals, 
                                    backgroundColor: colors[w].bg,
                                    borderColor: colors[w].border,
                                    borderWidth: 3,
                                    pointBackgroundColor: colors[w].border,
                                    pointBorderColor: '#FFFFFF',
                                    pointBorderWidth: 3,
                                    pointRadius: 5,
                                    tension: 0.4,
                                    fill: true
                                });
                            }
                            renderMultiChart(days, datasets, '📈 4-Week Comparison');
                        })
                        ['catch'](function(error) { console.error(error); showEmptyChart(); });
                }
                
                function renderMultiChart(days, datasets, title) {
                    var ctx = document.getElementById('salesChart').getContext('2d');
                    if (chart) chart.destroy();
                    document.getElementById('chartTitle').textContent = title;
                    chart = new Chart(ctx, {
                        type: 'line',
                        data: { labels: days, datasets: datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    align: 'end',
                                    labels: {
                                        font: { size: 11, weight: '600' },
                                        color: '#4B5563',
                                        padding: 12,
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        boxWidth: 8
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#1E1B4B',
                                    padding: 12,
                                    titleFont: { size: 13, weight: 'bold' },
                                    bodyFont: { size: 13 },
                                    cornerRadius: 8
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#F3F4F6', drawBorder: false },
                                    ticks: {
                                        font: { size: 11 },
                                        color: '#9CA3AF',
                                        callback: function(value) {
                                            if (value >= 1000) return '₱' + (value / 1000).toFixed(1) + 'k';
                                            return '₱' + value;
                                        }
                                    }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { size: 11 }, color: '#9CA3AF' }
                                }
                            }
                        }
                    });
                }
                
                function showEmptyChart() {
                    var ctx = document.getElementById('salesChart').getContext('2d');
                    if (chart) chart.destroy();
                    document.getElementById('chartTitle').textContent = '📈 No Data Available';
                    chart = new Chart(ctx, {
                        type: 'line',
                        data: { 
                            labels: ['No Data'], 
                            datasets: [{ 
                                label: 'No sales yet',
                                data: [0], 
                                borderColor: '#E5E7EB',
                                backgroundColor: 'rgba(229, 231, 235, 0.2)',
                                borderWidth: 3,
                                pointRadius: 0,
                                fill: true
                            }] 
                        },
                        options: { 
                            responsive: true, 
                            maintainAspectRatio: false, 
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#F3F4F6' }, ticks: { color: '#9CA3AF' } },
                                x: { grid: { display: false }, ticks: { color: '#9CA3AF' } }
                            }
                        }
                    });
                }
                
                function updateChart(mode, btn) {
                    document.querySelectorAll('.dash-filter-btn').forEach(function(b) { b.classList.remove('active'); });
                    if (btn) btn.classList.add('active');
                    currentView = mode;
                    if (mode === '1week') loadSingleWeek();
                    else if (mode === '2weeks') loadTwoWeeks();
                    else if (mode === '3weeks') loadThreeWeeks();
                    else if (mode === '4weeks') loadFourWeeks();
                }
                
                // Init chart
                setTimeout(function() { loadFourWeeks(); }, 100);
                
                // Stat detail modal
                function showStatDetail(title, value, description) {
                    document.getElementById('detailTitle').textContent = title;
                    document.getElementById('detailValue').textContent = value;
                    document.getElementById('detailDesc').textContent = description;
                    document.getElementById('detailModal').classList.add('show');
                }
                
                function closeModal(id) { document.getElementById(id).classList.remove('show'); }
            </script>
                <?php
break;
endswitch;
