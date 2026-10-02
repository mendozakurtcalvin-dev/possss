<?php
switch ('activity'):
case 'activity':

                if (!isAdmin()) {
                    echo '<div class="alert alert-danger">⛔ Access Denied. Admin only.</div>';
                    break;
                }
                $activityLog = $activityLogManager->getActivityLog(20);
                $activityStats = $activityLogManager->getActivityStats();
                $actionCounts = [];
                foreach ($activityLog as $act) { 
                    $action = $act['action']; 
                    if (!isset($actionCounts[$action])) { 
                        $actionCounts[$action] = 0; 
                    } 
                    $actionCounts[$action]++; 
                }
                ?>
                
                <!-- ============================================ -->
                <!-- ACTIVITY LOG PAGE - MODERN INTERFACE -->
                <!-- ============================================ -->
                
                <!-- Page Header -->
                <div class="act-page-header">
                    <div class="act-page-header-left">
                        <h2 class="act-page-title">
                            <span class="act-page-icon">📋</span>
                            Activity Log
                        </h2>
                        <p class="act-page-subtitle">Track all user actions and system events</p>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="act-stats-grid">
                    
                    <div class="act-stat-card">
                        <div class="act-stat-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                            </svg>
                        </div>
                        <div class="act-stat-label">Total Activities</div>
                        <div class="act-stat-value"><?php echo number_format($activityStats['total']); ?></div>
                        <div class="act-stat-footer">All-time logged actions</div>
                    </div>
                    
                    <div class="act-stat-card">
                        <div class="act-stat-icon" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="act-stat-label">Today's Activities</div>
                        <div class="act-stat-value"><?php echo number_format($activityStats['today']); ?></div>
                        <div class="act-stat-footer">Actions in the last 24 hours</div>
                    </div>
                    
                    <div class="act-stat-card">
                        <div class="act-stat-icon" style="background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                            </svg>
                        </div>
                        <div class="act-stat-label">Most Common Action</div>
                        <div class="act-stat-value act-stat-value-name">
                            <?php echo !empty($activityStats['actions']) ? ucfirst(str_replace('_', ' ', $activityStats['actions'][0]['action'] ?? 'N/A')) : 'N/A'; ?>
                        </div>
                        <div class="act-stat-footer">
                            <?php echo !empty($activityStats['actions']) ? number_format($activityStats['actions'][0]['count'] ?? 0) . ' times' : 'No data'; ?>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Chart + Activity Table Grid -->
                <div class="act-grid-2col">
                    
                    <!-- Chart Card -->
                    <?php if (!empty($activityStats['actions'])): ?>
                    <div class="act-card">
                        <div class="act-card-header">
                            <div class="act-card-title-wrap">
                                <div class="act-card-title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                                        <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                                    </svg>
                                    Activity Breakdown
                                </div>
                                <div class="act-card-subtitle">Distribution by action type</div>
                            </div>
                        </div>
                        <div class="act-chart-wrapper">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Top Actions Card -->
                    <?php if (!empty($activityStats['actions'])): ?>
                    <div class="act-card">
                        <div class="act-card-header">
                            <div class="act-card-title-wrap">
                                <div class="act-card-title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                        <polyline points="17 6 23 6 23 12"></polyline>
                                    </svg>
                                    Top 10 Actions
                                </div>
                                <div class="act-card-subtitle">Most frequent events</div>
                            </div>
                        </div>
                        <div class="act-actions-list">
                            <?php 
                            $maxCount = !empty($activityStats['actions']) ? max(array_column($activityStats['actions'], 'count')) : 1;
                            foreach ($activityStats['actions'] as $action): 
                                $percentage = $maxCount > 0 ? ($action['count'] / $maxCount) * 100 : 0;
                            ?>
                            <div class="act-action-row">
                                <div class="act-action-info">
                                    <span class="act-action-name"><?php echo ucfirst(str_replace('_', ' ', $action['action'])); ?></span>
                                    <span class="act-action-count"><?php echo number_format($action['count']); ?>×</span>
                                </div>
                                <div class="act-action-bar">
                                    <div class="act-action-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                
                <!-- Recent Activity History Card -->
                <div class="act-card">
                    
                    <div class="act-card-header">
                        <div class="act-card-title-wrap">
                            <div class="act-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                Recent Activity
                            </div>
                            <div class="act-card-subtitle">
                                Showing <strong><?php echo count($activityLog); ?></strong> most recent activities
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($activityLog)): ?>
                    <div class="act-table-wrap">
                        <table class="act-table">
                            <thead>
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                    <th style="text-align:right;">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $idx = 1;
                                foreach ($activityLog as $act): 
                                    // Determine action category for color
                                    $actionType = $act['action'];
                                    $actionClass = 'act-badge-default';
                                    
                                    if (strpos($actionType, 'login') !== false) $actionClass = 'act-badge-login';
                                    elseif (strpos($actionType, 'logout') !== false) $actionClass = 'act-badge-logout';
                                    elseif (strpos($actionType, 'delete') !== false) $actionClass = 'act-badge-delete';
                                    elseif (strpos($actionType, 'add') !== false || strpos($actionType, 'create') !== false) $actionClass = 'act-badge-add';
                                    elseif (strpos($actionType, 'update') !== false || strpos($actionType, 'edit') !== false) $actionClass = 'act-badge-update';
                                    elseif (strpos($actionType, 'archive') !== false) $actionClass = 'act-badge-archive';
                                    elseif (strpos($actionType, 'restore') !== false) $actionClass = 'act-badge-restore';
                                    elseif (strpos($actionType, 'sale') !== false) $actionClass = 'act-badge-sale';
                                    elseif (strpos($actionType, 'approve') !== false) $actionClass = 'act-badge-approve';
                                    elseif (strpos($actionType, 'reject') !== false) $actionClass = 'act-badge-reject';
                                ?>
                                <tr>
                                    <td>
                                        <span class="act-row-num"><?php echo $idx++; ?></span>
                                    </td>
                                    <td>
                                        <div class="act-user-cell">
                                            <div class="act-user-avatar"><?php echo strtoupper(substr($act['username'], 0, 1)); ?></div>
                                            <div class="act-user-info">
                                                <div class="act-user-name"><?php echo htmlspecialchars($act['username']); ?></div>
                                                <?php if (!empty($act['user_full_name']) && $act['user_full_name'] !== $act['username']): ?>
                                                <div class="act-user-fullname"><?php echo htmlspecialchars($act['user_full_name']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="act-badge <?php echo $actionClass; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $act['action'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="act-details"><?php echo htmlspecialchars($act['details'] ?? '—'); ?></span>
                                    </td>
                                    <td>
                                        <span class="act-ip"><?php echo htmlspecialchars($act['ip_address'] ?? '—'); ?></span>
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="act-time">
                                            <div class="act-time-date"><?php echo date('M d, Y', strtotime($act['created_at'])); ?></div>
                                            <div class="act-time-clock"><?php echo date('h:i:s A', strtotime($act['created_at'])); ?></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="act-empty-state">
                        <div class="act-empty-icon">📋</div>
                        <div class="act-empty-title">No activity logged yet</div>
                        <div class="act-empty-text">Activities will appear here as users interact with the system</div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                
                <!-- Chart Script -->
                <?php if (!empty($activityStats['actions'])): ?>
                <script>
                    var actionLabels = <?php echo json_encode(array_keys($actionCounts)); ?>;
                    var actionData = <?php echo json_encode(array_values($actionCounts)); ?>;
                    var formattedLabels = actionLabels.map(function(label) { 
                        return label.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }); 
                    });
                    
                    // Color palette for chart
                    var chartColors = [
                        '#6366F1', '#8B5CF6', '#10B981', '#F59E0B', '#EF4444', 
                        '#3B82F6', '#14B8A6', '#A855F7', '#F97316', '#EC4899'
                    ];
                    
                    var ctx = document.getElementById('activityChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: formattedLabels,
                            datasets: [{
                                data: actionData,
                                backgroundColor: chartColors.slice(0, actionData.length),
                                borderWidth: 3,
                                borderColor: '#FFFFFF',
                                hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '65%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        font: { size: 11, weight: '600', family: 'Inter' },
                                        padding: 12,
                                        usePointStyle: true,
                                        pointStyle: 'circle',
                                        boxWidth: 8,
                                        color: '#4B5563'
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#1E1B4B',
                                    padding: 12,
                                    titleFont: { size: 13, weight: 'bold', family: 'Inter' },
                                    bodyFont: { size: 12, family: 'Inter' },
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function(context) {
                                            var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                            var percentage = ((context.parsed / total) * 100).toFixed(1);
                                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                </script>
                <?php endif; ?>
                
                <?php
break;
endswitch;
