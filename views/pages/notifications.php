<?php
switch ('notifications'):
case 'notifications':

                                if (!hasRole('inventory') && !isAdmin()) {
                                    echo '<div class="alert alert-danger">⛔ Access Denied</div>';
                                    break;
                                }
                                
                                $user_id = $_SESSION['user_id'];
                                
                                // Mark all as read if requested
                                // Mark all as read if requested (no redirect — just process)
                                $justMarked = false;
                                if (isset($_GET['mark_read'])) {
                                    $stmt = $pdo->prepare("
                                        INSERT IGNORE INTO notification_reads (notification_id, user_id)
                                        SELECT id, ? FROM inventory_notifications
                                    ");
                                    $stmt->execute([$user_id]);
                                    $justMarked = true;
                                    
                                    // Clean redirect after 1 second (via JS so header() isn't needed)
                                    // This reloads without mark_read=1 so the sidebar badge recalculates
                                    // (Handled below in the <script> tag)
                                }
                                
                                // Fetch notifications
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        n.*,
                                        CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END as is_read
                                    FROM inventory_notifications n
                                    LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.user_id = ?
                                    ORDER BY n.created_at DESC
                                    LIMIT 100
                                ");
                                $stmt->execute([$user_id]);
                                $notifications = $stmt->fetchAll();
                                
                                $unreadCount = getUnreadNotificationCount($user_id);
                                ?>
                                
                                <div class="inv-page-header">
                                    <div class="inv-page-header-left">
                                        <h2 class="inv-page-title">
                                            <span class="inv-page-icon">🔔</span>
                                            Notifications
                                        </h2>
                                        <p class="inv-page-subtitle">
                                            <?php echo $unreadCount > 0 ? $unreadCount . ' unread notifications' : 'All caught up!'; ?>
                                        </p>
                                    </div>
                                    <?php if ($unreadCount > 0): ?>
                                    <div>
                                        <a href="?page=notifications&mark_read=1" class="inv-btn-primary">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            Mark All as Read
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                    <?php if ($justMarked): ?>
                                        <div id="markReadAlert" style="background:linear-gradient(135deg,#ECFDF5 0%,#D1FAE5 100%);border:1px solid #A7F3D0;border-radius:14px;padding:14px 18px;margin-bottom:20px;color:#059669;font-weight:600;display:flex;align-items:center;gap:10px;transition:opacity 0.5s ease, transform 0.5s ease, margin 0.5s ease, padding 0.5s ease, max-height 0.5s ease;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            All notifications marked as read!
                                        </div>
                                        <script>
                                            (function() {
                                                var alert = document.getElementById('markReadAlert');
                                                if (!alert) return;
                                                
                                                // Wait 3 seconds, then fade out
                                                setTimeout(function() {
                                                    alert.style.opacity = '0';
                                                    alert.style.transform = 'translateY(-10px)';
                                                    alert.style.marginBottom = '0';
                                                    alert.style.paddingTop = '0';
                                                    alert.style.paddingBottom = '0';
                                                    alert.style.maxHeight = '0';
                                                    alert.style.overflow = 'hidden';
                                                    
                                                    // Remove from DOM after transition completes
                                                    setTimeout(function() {
                                                        if (alert.parentNode) alert.parentNode.removeChild(alert);
                                                    }, 600);
                                                }, 3000);  // 👈 Change this number to adjust timing (3000 = 3 seconds)
                                            })();
                                        </script>
                                    <?php endif; ?>
                                
                                <div class="inv-card">
                                    <?php if (!empty($notifications)): ?>
                                    <div style="padding:12px 16px;">
                                        <?php foreach ($notifications as $n): ?>
                                        <div style="display:flex;gap:14px;padding:16px;border-radius:12px;margin-bottom:8px;background:<?php echo $n['is_read'] ? '#F9FAFB' : 'linear-gradient(135deg,#EEF2FF 0%,#E0E7FF 100%)'; ?>;border:1px solid <?php echo $n['is_read'] ? '#F3F4F6' : '#C7D2FE'; ?>;">
                                            <div style="font-size:24px;flex-shrink:0;width:48px;height:48px;display:flex;align-items:center;justify-content:center;background:white;border-radius:12px;border:1px solid #F3F4F6;">
                                                <?php 
                                                $icons = [
                                                    'product_added' => '📦',
                                                    'product_edited' => '✏️',
                                                    'product_deleted' => '🗑️',
                                                    'product_archived' => '📁',
                                                    'product_restored' => '♻️',
                                                    'stock_added' => '📊',
                                                    'stock_adjusted' => '⚙️'
                                                ];
                                                echo $icons[$n['type']] ?? '🔔';
                                                ?>
                                            </div>
                                            <div style="flex:1;min-width:0;">
                                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;flex-wrap:wrap;">
                                                    <strong style="font-size:14px;color:#111827;"><?php echo htmlspecialchars($n['title']); ?></strong>
                                                    <?php if (!$n['is_read']): ?>
                                                    <span style="background:#EF4444;color:white;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:800;">NEW</span>
                                                    <?php endif; ?>
                                                    <span style="margin-left:auto;font-size:12px;color:#9CA3AF;font-weight:500;"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></span>
                                                </div>
                                                <div style="font-size:13px;color:#4B5563;line-height:1.5;"><?php echo htmlspecialchars($n['message']); ?></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="inv-empty-state">
                                        <div class="inv-empty-icon">🔔</div>
                                        <div class="inv-empty-title">No notifications yet</div>
                                        <div class="inv-empty-text">Changes made by other staff will appear here</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                    <?php if ($justMarked): ?>
                                        <script>
                                            setTimeout(function() {
                                                var badge = document.querySelector('.notif-badge');
                                                if (badge) {
                                                    badge.style.transition = 'all 0.3s ease';
                                                    badge.style.transform = 'scale(0)';
                                                    badge.style.opacity = '0';
                                                    setTimeout(function() {
                                                        if (badge.parentNode) badge.parentNode.removeChild(badge);
                                                    }, 300);
                                                }
                                            }, 400);
                                        </script>
                                    <?php endif; ?>

                                <?php
break;
endswitch;
