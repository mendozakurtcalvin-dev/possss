<?php
switch ('hr'):
case 'hr':

                if (!canAccess('hr')) {
                    echo '<div class="alert alert-danger" style="margin:2rem;text-align:center;">
                        <div style="font-size:4rem;margin-bottom:1rem;">⛔</div>
                        <h2>Access Denied</h2>
                        <p>You do not have permission to access this page.</p>
                        <a href="?page=dashboard" class="btn btn-primary" style="margin-top:1rem;">Go to Dashboard</a>
                    </div>';
                    break;
                }

                                // ============================================
                // AUTO-SYNC USERS INTO EMPLOYEES TABLE (v2)
                // Only syncs users that DON'T already have an employee record
                // ============================================
                try {
                    // Get all users with a role
                    $stmt = $pdo->query("
                        SELECT u.id, u.username, u.full_name, u.role 
                        FROM users u
                        WHERE u.role IS NOT NULL AND u.role != ''
                    ");
                    $allUsers = $stmt->fetchAll();
                    
                    foreach ($allUsers as $user) {
                        // Split full name
                        $nameParts = explode(' ', trim($user['full_name']), 2);
                        $firstName = $nameParts[0] ?? $user['username'];
                        $lastName  = $nameParts[1] ?? '';
                        
                        // Check by BOTH: employee_id convention AND name match
                        // This prevents duplicates when a manual EMP- record already exists
                        $checkStmt = $pdo->prepare("
                            SELECT id FROM employees 
                            WHERE employee_id = ?
                            OR (LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?))
                        ");
                        $checkStmt->execute(['USR-' . $user['id'], $firstName, $lastName]);
                        $existing = $checkStmt->fetch();
                        
                        if (!$existing) {
                            // Friendly names by role
                            $positionMap = [
                                'admin'     => 'Administrator',
                                'cashier'   => 'Cashier',
                                'inventory' => 'Inventory Staff',
                                'hr'        => 'HR Staff',
                                'finance'   => 'Finance Staff'
                            ];
                            $deptMap = [
                                'admin'     => 'Management',
                                'cashier'   => 'Store Operations',
                                'inventory' => 'Inventory',
                                'hr'        => 'Human Resources',
                                'finance'   => 'Finance'
                            ];
                            
                            $insertStmt = $pdo->prepare("
                                INSERT INTO employees 
                                (employee_id, first_name, last_name, position, department, role, 
                                status, salary, salary_type, start_date)
                                VALUES (?, ?, ?, ?, ?, ?, 'active', 0, 'monthly', CURDATE())
                            ");
                            
                            $insertStmt->execute([
                                'USR-' . $user['id'],
                                $firstName,
                                $lastName,
                                $positionMap[$user['role']] ?? ucfirst($user['role']),
                                $deptMap[$user['role']]     ?? 'General',
                                $user['role']
                            ]);
                            
                            logActivity('auto_sync_employee', 
                                "Auto-created employee record for user: {$user['username']} ({$user['role']})");
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Employee auto-sync error: " . $e->getMessage());
                }
                // ============================================
                // END AUTO-SYNC
                // ============================================
                // Show only manually-added employees (EMP-*) and inventory staff
                $employees = $pdo->query("
                    SELECT e.*, COALESCE(NULLIF(u.role, ''), e.role, '') AS display_role
                    FROM employees e
                    LEFT JOIN users u ON u.id = e.user_id
                        OR (e.user_id IS NULL AND e.employee_id = CONCAT('USR-', u.id))
                    ORDER BY e.employee_id
                ")->fetchAll();
                $hrStats = $hrManager->getHRStats();
                
                // ============================================
                // AUTO PAYROLL CHECK - Runs when HR page loads
                // ============================================
                // ============================================
                // AUTO PAYROLL DISABLED — Use the "Process Payroll" button
                // ============================================
                // The auto-payroll block has been removed.
                // Payroll now only runs when the user clicks "Process Payroll".
                
                $attendance_today = $hrManager->getAttendance();
                $pending_leaves = $hrManager->getLeaveRequests('pending');
                $payroll_count = $pdo->query("SELECT COUNT(*) as count FROM payroll WHERE status = 'pending'")->fetch()['count'];
                
                
                // Payroll stats for this month
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_payroll,
                        COALESCE(SUM(amount), 0) as total_amount,
                        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
                    FROM payroll 
                    WHERE MONTH(created_at) = MONTH(CURDATE())
                    AND YEAR(created_at) = YEAR(CURDATE())
                ");
                $payrollStats = $stmt->fetch();
                // Auto-open modal based on URL param
                $autoOpen = isset($_GET['open']) ? $_GET['open'] : '';
                ?>
                
                <!-- ============================================ -->
                <!-- HR PAGE - MODERN INTERFACE -->
                <!-- ============================================ -->
                
                <!-- Page Header -->
                <div class="hr-page-header">
                    <div class="hr-page-header-left">
                        <h2 class="hr-page-title">
                            <span class="hr-page-icon">👥</span>
                            HR Management
                        </h2>
                        <p class="hr-page-subtitle">Manage employees, attendance, and payroll</p>
                    </div>
                    <button class="hr-btn-primary no-print" onclick="showAddEmployee()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Employee
                    </button>
                </div>
                
                <!-- Stats Grid -->
                <div class="hr-stats-grid">
                    
                    <div class="hr-stat-card">
                        <div class="hr-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div class="hr-stat-label">Total Employees</div>
                        <div class="hr-stat-value"><?php echo $hrStats['total']; ?></div>
                        <div class="hr-stat-footer">In database</div>
                    </div>
                    
                    <div class="hr-stat-card">
                        <div class="hr-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <div class="hr-stat-label">Active Employees</div>
                        <div class="hr-stat-value hr-stat-value-success"><?php echo $hrStats['active']; ?></div>
                        <div class="hr-stat-footer">Currently working</div>
                    </div>
                    
                    <div class="hr-stat-card" onclick="showTodayAttendance()" style="cursor:pointer;">
                        <div class="hr-stat-icon" style="background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <div class="hr-stat-label">Today's Attendance</div>
                        <div class="hr-stat-value <?php echo $hrStats['attendance_today'] > 0 ? '' : 'hr-stat-value-muted'; ?>"><?php echo $hrStats['attendance_today']; ?></div>
                        <div class="hr-stat-footer">Click to view</div>
                    </div>
                    
                    <div class="hr-stat-card" onclick="showLeaveModal()" style="cursor:pointer;">
                        <div class="hr-stat-icon" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <div class="hr-stat-label">Pending Leaves</div>
                        <div class="hr-stat-value <?php echo $hrStats['pending_leaves'] > 0 ? 'hr-stat-value-warning' : 'hr-stat-value-muted'; ?>"><?php echo $hrStats['pending_leaves']; ?></div>
                        <div class="hr-stat-footer">Click to review</div>
                    </div>
                    
                    <div class="hr-stat-card" onclick="processPayroll()" style="cursor:pointer;">
                        <div class="hr-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                        <div class="hr-stat-label">Pending Payroll</div>
                        <div class="hr-stat-value <?php echo $hrStats['pending_payroll'] > 0 ? 'hr-stat-value-warning' : 'hr-stat-value-muted'; ?>"><?php echo $hrStats['pending_payroll']; ?></div>
                        <div class="hr-stat-footer">Click to process</div>
                    </div>
                    
                    <div class="hr-stat-card hr-stat-card-highlight">
                        <div class="hr-stat-icon" style="background: linear-gradient(135deg, #14B8A6 0%, #0F766E 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path>
                                <path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path>
                                <path d="M18 12a2 2 0 0 0 0 4h4v-4z"></path>
                            </svg>
                        </div>
                        <div class="hr-stat-label">This Month Payroll</div>
                        <div class="hr-stat-value hr-stat-value-teal">₱<?php echo number_format($payrollStats['total_amount'] ?? 0, 2); ?></div>
                        <div class="hr-stat-footer"><?php echo $payrollStats['paid_count'] ?? 0; ?> employees paid</div>
                    </div>
                    
                </div>
                
                <!-- Employee Table Card -->
                <div class="hr-card">
                    
                    <!-- Card Header -->
                    <div class="hr-card-header">
                        <div class="hr-card-title-wrap">
                            <div class="hr-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                All Employees
                            </div>
                            <div class="hr-card-subtitle">
                                <span id="hrEmployeeCount"><?php echo count($employees); ?></span> <?php echo count($employees) === 1 ? 'employee' : 'employees'; ?> registered
                            </div>
                        </div>
                        
                        <!-- Filters -->
                        <div class="hr-filters">
                            <div class="hr-search-wrap">
                                <span class="hr-search-icon">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                </span>
                                <input type="text" id="employeeSearch" placeholder="Search employees..." onkeyup="filterEmployees()" class="hr-search-input">
                            </div>
                            <select id="employeeStatusFilter" onchange="filterEmployees()" class="hr-filter-select">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                            <select id="employeeRoleFilter" onchange="filterEmployees()" class="hr-filter-select">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="cashier">Cashier</option>
                                <option value="inventory">Inventory</option>
                                <option value="hr">HR</option>
                                <option value="finance">Finance</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Employee Table -->
                    <?php if (!empty($employees)): ?>
                    <div class="hr-table-wrap">
                        <table class="hr-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Role</th>
                                    <th>Contact</th>
                                    <th>Salary</th>
                                    <th>Status</th>
                                    <th class="no-print" style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="employeeTableBody">
                                <?php foreach ($employees as $emp): ?>
                                <?php $employeeRoles = getUserRoles($emp['display_role'] ?? $emp['role']); ?>
                                <?php $primaryEmployeeRole = $employeeRoles[0] ?? 'default'; ?>
                                <tr data-name="<?php echo strtolower($emp['first_name'] . ' ' . $emp['last_name']); ?>" data-status="<?php echo $emp['status']; ?>" data-role="<?php echo htmlspecialchars(implode(',', $employeeRoles)); ?>" data-id="<?php echo $emp['id']; ?>">
                                    <td>
                                        <div class="hr-name-cell">
                                            <div class="hr-avatar <?php echo htmlspecialchars($primaryEmployeeRole); ?>">
                                                <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                                            </div>
                                            <div class="hr-name-info">
                                                <div class="hr-fullname"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                                                <div class="hr-empid"><?php echo htmlspecialchars($emp['employee_id']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="hr-position"><?php echo htmlspecialchars($emp['position'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <span class="hr-department"><?php echo htmlspecialchars($emp['department'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($employeeRoles)): ?>
                                            <?php foreach ($employeeRoles as $employeeRole): ?>
                                                <span class="hr-role-badge hr-role-<?php echo htmlspecialchars($employeeRole); ?>">
                                                    <?php echo htmlspecialchars(getRoleLabel($employeeRole)); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="hr-role-badge hr-role-none">No Role</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="hr-phone"><?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <strong class="hr-salary">₱<?php echo number_format($emp['salary'], 2); ?></strong>
                                        <span class="hr-salary-type"><?php echo ucfirst(str_replace('_', '-', $emp['salary_type'] ?? 'monthly')); ?></span>
                                    </td>
                                    <td>
                                        <span class="hr-status-badge hr-status-<?php echo $emp['status']; ?>">
                                            <?php echo ucfirst($emp['status']); ?>
                                        </span>
                                    </td>
                                    <td class="no-print" style="text-align:right;">
                                        <div class="hr-actions">
                                            <button class="hr-action-btn hr-action-edit" onclick="editEmployee(<?php echo $emp['id']; ?>)" title="Edit">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </button>
                                            <button class="hr-action-btn hr-action-clockin" onclick="clockIn(<?php echo $emp['id']; ?>)" title="Clock In">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <polyline points="12 6 12 12 16 14"></polyline>
                                                </svg>
                                            </button>
                                            <button class="hr-action-btn hr-action-clockout" onclick="clockOut(<?php echo $emp['id']; ?>)" title="Clock Out">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <polyline points="12 6 12 12 16 14"></polyline>
                                                </svg>
                                            </button>
                                            <button class="hr-action-btn hr-action-shifts" onclick="viewShiftLogs(<?php echo $emp['id']; ?>)" title="View Shifts">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                    <polyline points="14 2 14 8 20 8"></polyline>
                                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                                </svg>
                                            </button>
                                            <button class="hr-action-btn hr-action-delete" onclick="deleteEmployee(<?php echo $emp['id']; ?>)" title="Delete">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="hr-empty-state">
                        <div class="hr-empty-icon">👥</div>
                        <div class="hr-empty-title">No employees yet</div>
                        <div class="hr-empty-text">Click "Add Employee" to add your first employee</div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                
                <?php
                // Include modals in separate file or continue below
                // I'll add the modals in the next message
                ?>
                
                <?php
                // For now, include all the existing modals + scripts
                ?>
                
                <!-- ===== SHIFT LOGS MODAL ===== -->
                <div class="modal" id="shiftLogsModal">
                    <div class="modal-content hr-modal" style="max-width:1000px;">
                        <div class="hr-modal-header">
                            <div class="hr-modal-header-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                            </div>
                            <div class="hr-modal-header-text">
                                <h2 id="shiftLogsTitle">Shift Logs</h2>
                                <p>Employee attendance history</p>
                            </div>
                            <button class="hr-modal-close" onclick="closeModal('shiftLogsModal')">&times;</button>
                        </div>
                        <div id="shiftLogsContent"></div>
                    </div>
                </div>
                
                <!-- ===== EMPLOYEE STATS MODAL ===== -->
                <div class="modal" id="employeeStatsModal">
                    <div class="modal-content hr-modal" style="max-width:900px;">
                        <div class="hr-modal-header">
                            <div class="hr-modal-header-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                            </div>
                            <div class="hr-modal-header-text">
                                <h2>Employee Statistics</h2>
                                <p>Workforce overview</p>
                            </div>
                            <button class="hr-modal-close" onclick="closeModal('employeeStatsModal')">&times;</button>
                        </div>
                        <div id="employeeStatsContent">
                            <div style="text-align:center;padding:2rem;color:#9CA3AF;">Loading statistics...</div>
                        </div>
                    </div>
                </div>
                
                <!-- ===== EMPLOYEE ADD/EDIT MODAL ===== -->
                <div class="modal" id="employeeModal">
                    <div class="modal-content hr-modal" style="max-width:720px;">
                        <div class="hr-modal-header">
                            <div class="hr-modal-header-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                            </div>
                            <div class="hr-modal-header-text">
                                <h2 id="employeeModalTitle">Add Employee</h2>
                                <p>Fill in the employee details</p>
                            </div>
                            <button class="hr-modal-close" onclick="closeModal('employeeModal')">&times;</button>
                        </div>
                        
                        <form id="employeeForm" onsubmit="saveEmployee(event)">
                            <input type="hidden" id="employeeId">
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Employee ID *</label>
                                    <input type="text" id="empEmployeeId" placeholder="e.g., EMP-2025-001" required class="hr-form-input">
                                </div>
                            </div>
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group">
                                    <label class="hr-form-label">First Name *</label>
                                    <input type="text" id="empFirstName" required class="hr-form-input">
                                </div>
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Last Name *</label>
                                    <input type="text" id="empLastName" required class="hr-form-input">
                                </div>
                            </div>
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Email</label>
                                    <input type="email" id="empEmail" class="hr-form-input">
                                </div>
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Phone (11 digits)</label>
                                    <input type="text" id="empPhone" placeholder="09123456789" maxlength="11" oninput="validateEmployeePhone(this)" class="hr-form-input">
                                    <div id="empPhoneError" class="hr-form-hint"></div>
                                </div>
                            </div>
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group hr-form-full">
                                    <label class="hr-form-label">Address</label>
                                    <textarea id="empAddress" rows="2" class="hr-form-textarea"></textarea>
                                </div>
                            </div>
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Position</label>
                                    <input type="text" id="empPosition" class="hr-form-input">
                                </div>
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Department</label>
                                    <input type="text" id="empDepartment" class="hr-form-input">
                                </div>
                            </div>
                            
                            <div class="hr-form-row hr-form-row-3">
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Salary (₱)</label>
                                    <input type="number" step="0.01" id="empSalary" value="0" class="hr-form-input">
                                </div>
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Salary Type</label>
                                    <select id="empSalaryType" onchange="toggleRateFields()" class="hr-form-select">
                                        <option value="monthly">Monthly</option>
                                        <option value="semi_monthly">Semi-Monthly</option>
                                        <option value="daily">Daily</option>
                                        <option value="hourly">Hourly</option>
                                    </select>
                                </div>
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Status</label>
                                    <select id="empStatus" class="hr-form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="terminated">Terminated</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="dailyRateGroup" style="display:none;" class="hr-form-row">
                                <div class="hr-form-group hr-form-full">
                                    <label class="hr-form-label">Daily Rate (₱)</label>
                                    <input type="number" step="0.01" id="empDailyRate" value="0" class="hr-form-input">
                                    <div class="hr-form-hint">Used for Daily salary type</div>
                                </div>
                            </div>
                            
                            <div id="hourlyRateGroup" style="display:none;" class="hr-form-row">
                                <div class="hr-form-group hr-form-full">
                                    <label class="hr-form-label">Hourly Rate (₱)</label>
                                    <input type="number" step="0.01" id="empHourlyRate" value="0" class="hr-form-input">
                                    <div class="hr-form-hint">Used for Hourly salary type</div>
                                </div>
                            </div>
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Start Date</label>
                                    <input type="date" id="empStartDate" value="<?php echo date('Y-m-d'); ?>" class="hr-form-input">
                                </div>
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Contract End Date</label>
                                    <input type="date" id="empContractEndDate" class="hr-form-input">
                                </div>
                            </div>
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group hr-form-full">
                                    <label class="hr-form-label">System Role</label>
                                    <select id="empRole" onchange="toggleCustomRole(this.value)" class="hr-form-select">
                                        <option value="">No System Access</option>
                                        <option value="admin">👨‍💼 Admin</option>
                                        <option value="cashier">🛒 Cashier</option>
                                        <option value="inventory">📦 Inventory</option>
                                        <option value="finance">💰 Finance</option>
                                        <option value="hr">👥 HR</option>
                                        <option value="custom">✏️ Custom Role</option>
                                    </select>
                                    <div class="role-custom-input" id="customRoleContainer">
                                        <input type="text" id="empCustomRole" placeholder="Enter custom role name..." class="hr-form-input">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Emergency Contact Name</label>
                                    <input type="text" id="empEmergencyName" class="hr-form-input">
                                </div>
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Emergency Contact Phone</label>
                                    <input type="text" id="empEmergencyPhone" class="hr-form-input">
                                </div>
                            </div>
                            
                            <div class="hr-modal-actions">
                                <button type="button" class="hr-btn-cancel" onclick="closeModal('employeeModal')">Cancel</button>
                                <button type="submit" class="hr-btn-submit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    Save Employee
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- ===== ATTENDANCE MODAL ===== -->
                <div class="modal" id="attendanceModal">
                    <div class="modal-content hr-modal" style="max-width:560px;">
                        <div class="hr-modal-header">
                            <div class="hr-modal-header-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                            <div class="hr-modal-header-text">
                                <h2>Take Attendance</h2>
                                <p>Clock in or out an employee</p>
                            </div>
                            <button class="hr-modal-close" onclick="closeModal('attendanceModal')">&times;</button>
                        </div>
                        
                        <div class="hr-form-group">
                            <label class="hr-form-label">Select Employee</label>
                            <select id="attendanceEmployee" class="hr-form-select">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="hr-attendance-buttons">
                            <button class="hr-btn-clockin" onclick="clockInSelected()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Clock In
                            </button>
                            <button class="hr-btn-clockout" onclick="clockOutSelected()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Clock Out
                            </button>
                        </div>
                        
                        <div id="attendanceStatus" style="display:none;margin-top:1rem;"></div>
                        
                        <div class="hr-today-attendance">
                            <div class="hr-today-attendance-title">Today's Attendance</div>
                            <div id="todayAttendanceList" class="hr-today-attendance-list">
                                <?php if (!empty($attendance_today)): ?>
                                    <?php foreach ($attendance_today as $att): ?>
                                    <div class="hr-attendance-row">
                                        <div class="hr-attendance-name">
                                            <div class="hr-attendance-avatar"><?php echo strtoupper(substr($att['first_name'], 0, 1)); ?></div>
                                            <span><?php echo htmlspecialchars($att['first_name'] . ' ' . $att['last_name']); ?></span>
                                        </div>
                                        <span class="hr-attendance-status <?php echo $att['time_out'] ? 'out' : 'in'; ?>">
                                            <?php echo $att['time_out'] ? '↑ Out: ' . date('H:i', strtotime($att['time_out'])) : '↓ In: ' . date('H:i', strtotime($att['time_in'])); ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="hr-attendance-empty">No attendance recorded today</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ===== LEAVE MODAL ===== -->
                <div class="modal" id="leaveModal">
                    <div class="modal-content hr-modal" style="max-width:600px;">
                        <div class="hr-modal-header">
                            <div class="hr-modal-header-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                            <div class="hr-modal-header-text">
                                <h2>Leave Request</h2>
                                <p>Submit a new leave request</p>
                            </div>
                            <button class="hr-modal-close" onclick="closeModal('leaveModal')">&times;</button>
                        </div>
                        
                        <form id="leaveForm" onsubmit="saveLeave(event)">
                            <div class="hr-form-group">
                                <label class="hr-form-label">Employee</label>
                                <select id="leaveEmployee" required class="hr-form-select">
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['employee_id'] . ' - ' . $emp['first_name'] . ' ' . $emp['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="hr-form-group">
                                <label class="hr-form-label">Leave Type</label>
                                <select id="leaveType" required class="hr-form-select">
                                    <option value="vacation">🏖️ Vacation Leave</option>
                                    <option value="sick">🤒 Sick Leave</option>
                                    <option value="emergency">🚨 Emergency Leave</option>
                                    <option value="maternity">👶 Maternity Leave</option>
                                    <option value="paternity">👨 Paternity Leave</option>
                                    <option value="other">📋 Other</option>
                                </select>
                            </div>
                            
                            <div class="hr-form-row">
                                <div class="hr-form-group">
                                    <label class="hr-form-label">Start Date</label>
                                    <input type="date" id="leaveStartDate" required class="hr-form-input">
                                </div>
                                <div class="hr-form-group">
                                    <label class="hr-form-label">End Date</label>
                                    <input type="date" id="leaveEndDate" required class="hr-form-input">
                                </div>
                            </div>
                            
                            <div class="hr-form-group">
                                <label class="hr-form-label">Reason</label>
                                <textarea id="leaveReason" rows="3" placeholder="Please provide reason for leave..." class="hr-form-textarea"></textarea>
                            </div>
                            
                            <div class="hr-modal-actions">
                                <button type="button" class="hr-btn-cancel" onclick="closeModal('leaveModal')">Cancel</button>
                                <button type="submit" class="hr-btn-submit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                    Submit Request
                                </button>
                            </div>
                        </form>
                        
                        <div class="hr-pending-leaves">
                            <div class="hr-pending-leaves-title">Pending Leave Requests</div>
                            <div id="pendingLeavesList" class="hr-pending-leaves-list">
                                <?php if (!empty($pending_leaves)): ?>
                                    <?php foreach ($pending_leaves as $leave): ?>
                                    <div class="hr-pending-leave-row">
                                        <div class="hr-pending-leave-info">
                                            <div class="hr-pending-leave-name"><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></div>
                                            <div class="hr-pending-leave-type"><?php echo ucfirst($leave['leave_type']); ?> · <?php echo date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d', strtotime($leave['end_date'])); ?></div>
                                        </div>
                                        <div class="hr-pending-leave-actions">
                                            <button class="hr-action-btn hr-action-clockin" onclick="updateLeaveStatus(<?php echo $leave['id']; ?>, 'approved')" title="Approve">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                </svg>
                                            </button>
                                            <button class="hr-action-btn hr-action-delete" onclick="updateLeaveStatus(<?php echo $leave['id']; ?>, 'rejected')" title="Reject">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="hr-pending-leaves-empty">No pending leave requests</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ===== KEEP ALL EXISTING SCRIPTS BELOW ===== -->

                <script>
                // ============================================
                // HR page scripts
                // ============================================

                function validateEmployeePhone(input) {
                    var phone = input.value.replace(/\D/g, '');
                    var errorDiv = document.getElementById('empPhoneError');
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

                function filterEmployees() {
                    var searchTerm = (document.getElementById('employeeSearch').value || '').toLowerCase().trim();
                    var statusFilter = document.getElementById('employeeStatusFilter').value;
                    var roleFilter = document.getElementById('employeeRoleFilter').value;
                    var rows = document.querySelectorAll('#employeeTableBody tr');
                    var visibleCount = 0;
                    rows.forEach(function(row) {
                        var name = row.getAttribute('data-name') || '';
                        var status = row.getAttribute('data-status') || '';
                        var role = row.getAttribute('data-role') || '';
                        var show = true;
                        if (searchTerm && name.indexOf(searchTerm) === -1) show = false;
                        if (statusFilter && status !== statusFilter) show = false;
                        if (roleFilter && role.split(',').indexOf(roleFilter) === -1) show = false;
                        row.style.display = show ? '' : 'none';
                        if (show) visibleCount++;
                    });
                    var counter = document.getElementById('hrEmployeeCount');
                    if (counter) counter.textContent = visibleCount;
                }

                function showAddEmployee() {
                    document.getElementById('employeeModalTitle').textContent = 'Add Employee';
                    document.getElementById('employeeId').value = '';
                    document.getElementById('empEmployeeId').value = '';
                    document.getElementById('empFirstName').value = '';
                    document.getElementById('empLastName').value = '';
                    document.getElementById('empEmail').value = '';
                    document.getElementById('empPhone').value = '';
                    document.getElementById('empAddress').value = '';
                    document.getElementById('empPosition').value = '';
                    document.getElementById('empDepartment').value = '';
                    document.getElementById('empSalary').value = '0';
                    document.getElementById('empSalaryType').value = 'monthly';
                    document.getElementById('empStatus').value = 'active';
                    document.getElementById('empDailyRate').value = '0';
                    document.getElementById('empHourlyRate').value = '0';
                    document.getElementById('empStartDate').value = new Date().toISOString().split('T')[0];
                    document.getElementById('empContractEndDate').value = '';
                    document.getElementById('empRole').value = '';
                    document.getElementById('empEmergencyName').value = '';
                    document.getElementById('empEmergencyPhone').value = '';
                    document.getElementById('empPhoneError').textContent = '';
                    document.getElementById('employeeModal').classList.add('show');
                    toggleRateFields();
                }

                function saveEmployee(e) {
                    e.preventDefault();
                    
                    var id = document.getElementById('employeeId').value;
                    var roleSelect = document.getElementById('empRole').value;
                    var customRole = document.getElementById('empCustomRole').value;
                    var finalRole = roleSelect === 'custom' ? customRole : roleSelect;

                    // ===== VALIDATION =====
                    var empCode = document.getElementById('empEmployeeId').value.trim();
                    var firstName = document.getElementById('empFirstName').value.trim();
                    var lastName = document.getElementById('empLastName').value.trim();
                    
                    if (!empCode) { alert('Employee ID is required'); return; }
                    if (!firstName) { alert('First Name is required'); return; }
                    if (!lastName) { alert('Last Name is required'); return; }

                    var phone = document.getElementById('empPhone').value.replace(/\D/g, '');
                    if (phone.length > 0 && phone.length !== 11) {
                        alert('⚠️ Phone number must be exactly 11 digits!');
                        return;
                    }

                    // ===== BUILD FORM DATA =====
                    var data = new FormData();
                    data.append('id', id);
                    data.append('employee_id', empCode);
                    data.append('first_name', firstName);
                    data.append('last_name', lastName);
                    data.append('email', document.getElementById('empEmail').value);
                    data.append('phone', phone);
                    data.append('address', document.getElementById('empAddress').value);
                    data.append('position', document.getElementById('empPosition').value);
                    data.append('department', document.getElementById('empDepartment').value);
                    data.append('salary', document.getElementById('empSalary').value);
                    data.append('salary_type', document.getElementById('empSalaryType').value);
                    data.append('daily_rate', document.getElementById('empDailyRate').value);
                    data.append('hourly_rate', document.getElementById('empHourlyRate').value);
                    data.append('status', document.getElementById('empStatus').value);
                    data.append('start_date', document.getElementById('empStartDate').value);
                    data.append('contract_end_date', document.getElementById('empContractEndDate').value);
                    data.append('role', finalRole);
                    data.append('emergency_name', document.getElementById('empEmergencyName').value);
                    data.append('emergency_phone', document.getElementById('empEmergencyPhone').value);

                    // ===== LOADING STATE =====
                    var btn = e.target.querySelector('button[type="submit"]');
                    var originalText = btn.innerHTML;
                    btn.innerHTML = '⏳ Saving...';
                    btn.disabled = true;

                    // ===== SEND =====
                    fetch('?action=save_employee', { method: 'POST', body: data })
                    .then(function(res) { return res.text(); })
                    .then(function(text) {
                        console.log('📥 Save response:', text);
                        
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        
                        var result;
                        try {
                            result = JSON.parse(text);
                        } catch (err) {
                            alert('❌ Server returned invalid response:\n\n' + text.substring(0, 500));
                            return;
                        }
                        
                        if (result.success) {
                            alert('✅ Employee saved successfully!');
                            closeModal('employeeModal');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + (result.message || 'Unknown error'));
                        }
                    })
                    .catch(function(err) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        alert('❌ Network error: ' + err.message);
                    });
                }

                function editEmployee(id) {
                    fetch('?action=get_employee&id=' + id)
                    .then(function(res) { return res.json(); })
                    .then(function(emp) {
                        document.getElementById('employeeModalTitle').textContent = 'Edit Employee';
                        document.getElementById('employeeId').value = emp.id;
                        document.getElementById('empEmployeeId').value = emp.employee_id;
                        document.getElementById('empFirstName').value = emp.first_name;
                        document.getElementById('empLastName').value = emp.last_name;
                        document.getElementById('empEmail').value = emp.email || '';
                        document.getElementById('empPhone').value = emp.phone || '';
                        document.getElementById('empAddress').value = emp.address || '';
                        document.getElementById('empPosition').value = emp.position || '';
                        document.getElementById('empDepartment').value = emp.department || '';
                        document.getElementById('empSalary').value = emp.salary || 0;
                        document.getElementById('empSalaryType').value = emp.salary_type || 'monthly';
                        document.getElementById('empDailyRate').value = emp.daily_rate || 0;
                        document.getElementById('empHourlyRate').value = emp.hourly_rate || 0;
                        document.getElementById('empStatus').value = emp.status || 'active';
                        document.getElementById('empStartDate').value = emp.start_date || '';
                        document.getElementById('empContractEndDate').value = emp.contract_end_date || '';
                        document.getElementById('empRole').value = emp.role || '';
                        document.getElementById('empEmergencyName').value = emp.emergency_contact_name || '';
                        document.getElementById('empEmergencyPhone').value = emp.emergency_contact_phone || '';
                        toggleRateFields();
                        document.getElementById('employeeModal').classList.add('show');
                    });
                }

                function toggleRateFields() {
                    var type = document.getElementById('empSalaryType').value;
                    document.getElementById('dailyRateGroup').style.display = type === 'daily' ? 'block' : 'none';
                    document.getElementById('hourlyRateGroup').style.display = type === 'hourly' ? 'block' : 'none';
                }

                function toggleCustomRole(value) {
                    var container = document.getElementById('customRoleContainer');
                    if (value === 'custom') {
                        container.classList.add('show');
                    } else {
                        container.classList.remove('show');
                    }
                }

                function deleteEmployee(id) {
                    customConfirm('Delete this employee permanently?', function() {
                        fetch('?action=delete_employee&id=' + id)
                        .then(function(res) { return res.json(); })
                        .then(function(result) {
                            if (result.success) {
                                alert('✅ Employee deleted!');
                                location.reload();
                            } else {
                                alert('❌ Failed to delete employee');
                            }
                        });
                    }, 'danger');
                }

                function viewShiftLogs(id) {
                    document.getElementById('shiftLogsModal').classList.add('show');
                    document.getElementById('shiftLogsTitle').textContent = 'Shift Logs';
                    document.getElementById('shiftLogsContent').innerHTML = '<div style="text-align:center;padding:2rem;color:#9CA3AF;">Loading...</div>';
                    fetch('?action=get_employee_attendance&id=' + id)
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (!data || data.length === 0) {
                            document.getElementById('shiftLogsContent').innerHTML = '<div style="text-align:center;padding:2rem;color:#9CA3AF;">No shift logs found</div>';
                            return;
                        }
                        var html = '<div class="hr-table-wrap"><table class="hr-table"><thead><tr><th>Date</th><th>Time In</th><th>Time Out</th></tr></thead><tbody>';
                        data.forEach(function(a) {
                            html += '<tr><td>' + a.date + '</td>';
                            html += '<td>' + (a.time_in ? new Date(a.time_in).toLocaleTimeString() : '—') + '</td>';
                            html += '<td>' + (a.time_out ? new Date(a.time_out).toLocaleTimeString() : '—') + '</td></tr>';
                        });
                        html += '</tbody></table></div>';
                        document.getElementById('shiftLogsContent').innerHTML = html;
                    });
                }

                function showEmployeeStats() {
                    var modal = document.getElementById('employeeStatsModal');
                    var content = document.getElementById('employeeStatsContent');
                    
                    modal.classList.add('show');
                    
                    // Loading state
                    content.innerHTML = `
                        <div style="padding:60px 20px;text-align:center;">
                            <div style="display:inline-block;width:44px;height:44px;border:3px solid #E5E7EB;border-top-color:#6366F1;border-radius:50%;animation:spin 0.8s linear infinite;"></div>
                            <div style="margin-top:14px;color:#9CA3AF;font-size:13px;font-weight:600;">Loading statistics...</div>
                        </div>
                    `;
                    
                    fetch('?action=get_employee_stats')
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.error) {
                            content.innerHTML = '<div style="padding:40px;text-align:center;color:#EF4444;">' + data.error + '</div>';
                            return;
                        }
                        
                        // Sort employees by shifts (most active first), then by days worked
                        var sortedEmployees = (data.employees || []).slice().sort(function(a, b) {
                            if (b.shifts !== a.shifts) return b.shifts - a.shifts;
                            if (b.days_worked !== a.days_worked) return b.days_worked - a.days_worked;
                            return a.name.localeCompare(b.name);
                        });
                        
                        // Build the HTML
                        var html = '';
                        
                        // ===== TOP KPI CARDS =====
                        html += '<div class="emp-stats-grid">';
                        
                        // Total Employees
                        html += `
                            <div class="emp-stat-card">
                                <div class="emp-stat-icon" style="background:linear-gradient(135deg,#6366F1 0%,#4F46E5 100%);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <div class="emp-stat-label">Total Employees</div>
                                <div class="emp-stat-value">${data.total_employees}</div>
                                <div class="emp-stat-footer">In database</div>
                            </div>
                        `;
                        
                        // Active
                        html += `
                            <div class="emp-stat-card emp-stat-card-success">
                                <div class="emp-stat-icon" style="background:linear-gradient(135deg,#10B981 0%,#059669 100%);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                </div>
                                <div class="emp-stat-label">Active</div>
                                <div class="emp-stat-value" style="color:#059669;">${data.active_employees}</div>
                                <div class="emp-stat-footer">Currently working</div>
                            </div>
                        `;
                        
                        // Total Shifts
                        html += `
                            <div class="emp-stat-card">
                                <div class="emp-stat-icon" style="background:linear-gradient(135deg,#8B5CF6 0%,#7C3AED 100%);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                </div>
                                <div class="emp-stat-label">Total Shifts</div>
                                <div class="emp-stat-value">${data.total_shifts}</div>
                                <div class="emp-stat-footer">All-time records</div>
                            </div>
                        `;
                        
                        // Total Hours
                        html += `
                            <div class="emp-stat-card">
                                <div class="emp-stat-icon" style="background:linear-gradient(135deg,#F59E0B 0%,#D97706 100%);">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                </div>
                                <div class="emp-stat-label">Total Hours</div>
                                <div class="emp-stat-value">${data.total_hours}</div>
                                <div class="emp-stat-footer">Workforce total</div>
                            </div>
                        `;
                        
                        html += '</div>';
                        
                        // ===== EMPLOYEE LIST =====
                        html += `
                            <div class="emp-list-header">
                                <div class="emp-list-title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="8" y1="6" x2="21" y2="6"></line>
                                        <line x1="8" y1="12" x2="21" y2="12"></line>
                                        <line x1="8" y1="18" x2="21" y2="18"></line>
                                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                    </svg>
                                    Employee Activity Breakdown
                                </div>
                                <div class="emp-list-subtitle">Sorted by shift count</div>
                            </div>
                        `;
                        
                        if (sortedEmployees.length === 0) {
                            html += `
                                <div style="padding:40px 20px;text-align:center;color:#9CA3AF;">
                                    <div style="font-size:42px;margin-bottom:12px;opacity:0.5;">📊</div>
                                    <div style="font-size:15px;font-weight:700;color:#4B5563;margin-bottom:4px;">No employee data yet</div>
                                    <div style="font-size:13px;">Add employees and start taking attendance</div>
                                </div>
                            `;
                        } else {
                            // Compute max shifts for progress bar
                            var maxShifts = Math.max(...sortedEmployees.map(function(e) { return e.shifts; }), 1);
                            
                            html += '<div class="emp-list">';
                            
                            sortedEmployees.forEach(function(emp) {
                                var initials = emp.name.split(' ').map(function(w) { return w.charAt(0); }).join('').substring(0, 2).toUpperCase();
                                var shiftPct = (emp.shifts / maxShifts) * 100;
                                
                                // Determine activity level
                                var activityLabel = 'Idle';
                                var activityClass = 'idle';
                                var activityColor = '#9CA3AF';
                                if (emp.shifts >= 5) { activityLabel = 'Highly Active'; activityClass = 'high'; activityColor = '#10B981'; }
                                else if (emp.shifts >= 2) { activityLabel = 'Active'; activityClass = 'medium'; activityColor = '#6366F1'; }
                                else if (emp.shifts >= 1) { activityLabel = 'Low Activity'; activityClass = 'low'; activityColor = '#F59E0B'; }
                                
                                // Check if auto-synced
                                var isSynced = /^USR-/.test(emp.employee_id || '');
                                var syncedBadge = isSynced ? '<span class="emp-synced-badge">SYNCED</span>' : '';
                                
                                html += `
                                    <div class="emp-row ${activityClass}">
                                        <div class="emp-row-left">
                                            <div class="emp-row-avatar" style="background:linear-gradient(135deg, ${activityColor} 0%, ${activityColor}dd 100%);">
                                                ${initials}
                                            </div>
                                            <div class="emp-row-info">
                                                <div class="emp-row-name">
                                                    ${emp.name}
                                                    ${syncedBadge}
                                                </div>
                                                <div class="emp-row-meta">
                                                    <span class="emp-row-badge" style="color:${activityColor};">● ${activityLabel}</span>
                                                    <span class="emp-row-sep">·</span>
                                                    <span>${emp.days_since_start} days since start</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="emp-row-metrics">
                                            <div class="emp-metric">
                                                <div class="emp-metric-value">${emp.shifts}</div>
                                                <div class="emp-metric-label">Shifts</div>
                                            </div>
                                            <div class="emp-metric">
                                                <div class="emp-metric-value">${emp.days_worked}</div>
                                                <div class="emp-metric-label">Days Worked</div>
                                            </div>
                                            <div class="emp-metric">
                                                <div class="emp-metric-value">${emp.hours}</div>
                                                <div class="emp-metric-label">Total Hours</div>
                                            </div>
                                        </div>
                                        
                                        <div class="emp-row-bar-wrap">
                                            <div class="emp-row-bar">
                                                <div class="emp-row-bar-fill" style="width:${shiftPct}%;background:linear-gradient(90deg,${activityColor},${activityColor}cc);"></div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            html += '</div>';
                        }
                        
                        content.innerHTML = html;
                    })
                    .catch(function(err) {
                        content.innerHTML = '<div style="padding:40px;text-align:center;color:#EF4444;">Error: ' + err.message + '</div>';
                    });
                }

                function showAttendanceModal() {
                    document.getElementById('attendanceStatus').style.display = 'none';
                    document.getElementById('attendanceModal').classList.add('show');
                }

                function clockInSelected() {
                    var id = document.getElementById('attendanceEmployee').value;
                    if (!id) { alert('Please select an employee'); return; }
                    clockIn(id);
                }

                function clockOutSelected() {
                    var id = document.getElementById('attendanceEmployee').value;
                    if (!id) { alert('Please select an employee'); return; }
                    clockOut(id);
                }

                function clockIn(id) {
                    var data = new FormData();
                    data.append('employee_id', id);
                    fetch('?action=clock_in', { method: 'POST', body: data })
                    .then(function(res) { return res.json(); })
                    .then(function(result) {
                        var statusDiv = document.getElementById('attendanceStatus');
                        if (statusDiv) {
                            statusDiv.style.display = 'block';
                            statusDiv.className = 'alert ' + (result.success ? 'alert-success' : 'alert-danger');
                            statusDiv.textContent = (result.success ? '✅ ' : '❌ ') + (result.message || '');
                        }
                        if (result.success) {
                            setTimeout(function() { location.reload(); }, 1200);
                        }
                    });
                }

                function clockOut(id) {
                    var data = new FormData();
                    data.append('employee_id', id);
                    fetch('?action=clock_out', { method: 'POST', body: data })
                    .then(function(res) { return res.json(); })
                    .then(function(result) {
                        var statusDiv = document.getElementById('attendanceStatus');
                        if (statusDiv) {
                            statusDiv.style.display = 'block';
                            statusDiv.className = 'alert ' + (result.success ? 'alert-success' : 'alert-danger');
                            statusDiv.textContent = (result.success ? '✅ ' : '❌ ') + (result.message || '');
                        }
                        if (result.success) {
                            setTimeout(function() { location.reload(); }, 1200);
                        }
                    });
                }

                function showTodayAttendance() {
                    showAttendanceModal();
                }

                function showLeaveModal() {
                    document.getElementById('leaveModal').classList.add('show');
                }

                function saveLeave(e) {
                    e.preventDefault();
                    var data = new FormData();
                    data.append('employee_id', document.getElementById('leaveEmployee').value);
                    data.append('leave_type', document.getElementById('leaveType').value);
                    data.append('start_date', document.getElementById('leaveStartDate').value);
                    data.append('end_date', document.getElementById('leaveEndDate').value);
                    data.append('reason', document.getElementById('leaveReason').value);

                    fetch('?action=save_leave', { method: 'POST', body: data })
                    .then(function(res) { return res.json(); })
                    .then(function(result) {
                        if (result.success) {
                            alert('✅ Leave request submitted!');
                            closeModal('leaveModal');
                            location.reload();
                        } else {
                            alert('❌ Error: ' + (result.message || 'Failed to submit'));
                        }
                    });
                }

                function updateLeaveStatus(id, status) {
                    var data = new FormData();
                    data.append('id', id);
                    data.append('status', status);
                    fetch('?action=update_leave_status', { method: 'POST', body: data })
                    .then(function(res) { return res.json(); })
                    .then(function(result) {
                        if (result.success) {
                            alert('✅ Leave ' + status + '!');
                            location.reload();
                        } else {
                            alert('❌ Failed to update');
                        }
                    });
                }

                function processPayroll() {
                    customConfirm(
                        'Process payroll for this month? This will mark all pending payroll as paid and cannot be undone.',
                        function() {
                            // Show loading feedback
                            if (window.showToast) {
                                showToast('info', 'Processing Payroll', 'Please wait...', 3000);
                            }
                            
                            fetch('?action=process_payroll', { method: 'POST' })
                            .then(function(res) { return res.json(); })
                            .then(function(result) {
                                if (result.success) {
                                    if (window.showToast) {
                                        showToast('success', 'Payroll Processed', result.message, 4000);
                                    } else {
                                        alert('✅ ' + result.message);
                                    }
                                    setTimeout(function() { location.reload(); }, 1500);
                                } else {
                                    if (window.showToast) {
                                        showToast('warning', 'Payroll Notice', result.message, 5000);
                                    } else {
                                        alert('⚠️ ' + result.message);
                                    }
                                }
                            })
                            .catch(function(err) {
                                if (window.showToast) {
                                    showToast('error', 'Error', err.message, 5000);
                                } else {
                                    alert('❌ Error: ' + err.message);
                                }
                            });
                        },
                        'warning'
                     );
                }

                function closeModal(id) {
                    document.getElementById(id).classList.remove('show');
                }
            </script>

            <script>
                // Auto-open modal when coming from dashboard quick actions
                document.addEventListener('DOMContentLoaded', function() {
                var autoOpen = '<?php echo htmlspecialchars($autoOpen); ?>';
                
                if (autoOpen === 'add_employee') {
                    if (typeof showAddEmployee === 'function') {
                        setTimeout(showAddEmployee, 200);
                    }
                }
                else if (autoOpen === 'attendance') {
                    if (typeof showAttendanceModal === 'function') {
                        setTimeout(showAttendanceModal, 200);
                    }
                }
                else if (autoOpen === 'leave') {
                    if (typeof showLeaveModal === 'function') {
                        setTimeout(showLeaveModal, 200);
                    }
                }
                else if (autoOpen === 'stats') {
                    if (typeof showEmployeeStats === 'function') {
                        setTimeout(showEmployeeStats, 200);
                    }
                }
                else if (autoOpen === 'payroll') {
                    // Auto-payroll disabled — user must click the button manually
                }
            });
            </script>
                
                <?php
break;
endswitch;
