<?php
require_once __DIR__ . '/proc_common.php';
if (!canAccess('procurement')) {
    echo procAccessDenied();
    return;
}

// ---- Live statistics (real database data, never hardcoded) ----
$statusCounts = array_fill_keys(['draft', 'pending_approval', 'revision_requested', 'approved', 'rejected', 'cancelled'], 0);
foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM purchase_requests GROUP BY status") as $row) {
    $statusCounts[$row['status']] = (int) $row['c'];
}
$totalRequests = array_sum($statusCounts);
$awaitingDelivery = (int) $pdo->query("SELECT COUNT(*) FROM procurement_purchase_orders WHERE status IN ('ordered','sent','acknowledged')")->fetchColumn();
$partiallyReceived = (int) $pdo->query("SELECT COUNT(*) FROM procurement_purchase_orders WHERE status = 'partially_received'")->fetchColumn();
$recentCompleted = (int) $pdo->query("SELECT COUNT(*) FROM procurement_purchase_orders WHERE status IN ('received','closed') AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$lowStockCount = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE archived = 0 AND active = 1 AND stock_quantity <= low_stock_threshold")->fetchColumn();

// ---- Filters for the recent purchase requests table ----
$fltQ = trim($_GET['q'] ?? '');
$fltStatus = $_GET['status'] ?? '';
if (!array_key_exists($fltStatus, $statusCounts)) { $fltStatus = ''; }
$fltSupplier = intval($_GET['supplier_id'] ?? 0);
$fltFrom = $_GET['date_from'] ?? '';
$fltTo = $_GET['date_to'] ?? '';
$procPage = max(1, intval($_GET['page'] ?? 1));
$procPerPage = 15;

$where = [];
$params = [];
if ($fltQ !== '') {
    $where[] = '(pr.request_number LIKE ? OR u.full_name LIKE ? OR s.name LIKE ? OR pr.reason LIKE ?)';
    $like = '%' . $fltQ . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($fltStatus !== '') { $where[] = 'pr.status = ?'; $params[] = $fltStatus; }
if ($fltSupplier > 0) { $where[] = 'pr.supplier_id = ?'; $params[] = $fltSupplier; }
$df = DateTime::createFromFormat('Y-m-d', (string) $fltFrom);
if (!$df || $df->format('Y-m-d') !== $fltFrom) { $fltFrom = ''; }
$dt = DateTime::createFromFormat('Y-m-d', (string) $fltTo);
if (!$dt || $dt->format('Y-m-d') !== $fltTo) { $fltTo = ''; }
if ($fltFrom !== '') { $where[] = 'pr.request_date >= ?'; $params[] = $fltFrom; }
if ($fltTo !== '') { $where[] = 'pr.request_date <= ?'; $params[] = $fltTo; }
$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';
$joinSql = ' FROM purchase_requests pr
    LEFT JOIN suppliers s ON pr.supplier_id = s.id
    LEFT JOIN users u ON pr.requester_id = u.id';

$countStmt = $pdo->prepare('SELECT COUNT(*)' . $joinSql . $whereSql);
$countStmt->execute($params);
$procTotal = (int) $countStmt->fetchColumn();
$procOffset = ($procPage - 1) * $procPerPage;
$listStmt = $pdo->prepare('SELECT pr.*, s.name AS supplier_name, u.full_name AS requester_name' . $joinSql . $whereSql . ' ORDER BY pr.created_at DESC LIMIT ' . $procOffset . ', ' . $procPerPage);
$listStmt->execute($params);
$recentRequests = $listStmt->fetchAll();

$filterBaseUrl = '?page=proc_dashboard';
$queryParts = [];
if ($fltQ !== '') { $queryParts['q'] = $fltQ; }
if ($fltStatus !== '') { $queryParts['status'] = $fltStatus; }
if ($fltSupplier > 0) { $queryParts['supplier_id'] = $fltSupplier; }
if ($fltFrom !== '') { $queryParts['date_from'] = $fltFrom; }
if ($fltTo !== '') { $queryParts['date_to'] = $fltTo; }
if (count($queryParts)) { $filterBaseUrl .= '&' . http_build_query($queryParts); }

$filterSuppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

$openPos = $pdo->query("
    SELECT po.*, s.name AS supplier_name
    FROM procurement_purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.status IN ('draft','ordered','sent','acknowledged','partially_received')
    ORDER BY po.created_at DESC LIMIT 8
")->fetchAll();

$lowStockProducts = $pdo->query("
    SELECT id, name, unit, stock_quantity, low_stock_threshold
    FROM products
    WHERE archived = 0 AND active = 1 AND stock_quantity <= low_stock_threshold
    ORDER BY (stock_quantity - low_stock_threshold) ASC, name ASC
    LIMIT 8
")->fetchAll();
?>
<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title"><span class="inv-page-icon">&#128203;</span> Procurement Dashboard</h2>
        <p class="inv-page-subtitle">Purchase requests, orders, deliveries and stock alerts in one place.</p>
    </div>
    <?php if (canAccess('procurement_manage')): ?>
    <a href="?page=purchase_requests&amp;tab=create" class="inv-btn-primary" style="text-decoration:none;">+ Create Purchase Request</a>
    <?php endif; ?>
</div>

<div class="inv-stats-grid">
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#6366F1,#4F46E5);">&#128221;</div></div>
        <div class="inv-stat-label">Total Requests</div>
        <div class="inv-stat-value primary"><?php echo number_format($totalRequests); ?></div>
        <div class="inv-stat-footer">All purchase requests</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#94A3B8,#64748B);">&#9998;</div></div>
        <div class="inv-stat-label">Draft</div>
        <div class="inv-stat-value"><?php echo number_format($statusCounts['draft']); ?></div>
        <div class="inv-stat-footer">Not yet submitted</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#F59E0B,#D97706);">&#9203;</div></div>
        <div class="inv-stat-label">Pending Approvals</div>
        <div class="inv-stat-value warning"><?php echo number_format($statusCounts['pending_approval']); ?></div>
        <div class="inv-stat-footer">Awaiting admin decision</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#10B981,#059669);">&#9989;</div></div>
        <div class="inv-stat-label">Approved</div>
        <div class="inv-stat-value success"><?php echo number_format($statusCounts['approved']); ?></div>
        <div class="inv-stat-footer">Ready for purchase orders</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#EF4444,#DC2626);">&#10060;</div></div>
        <div class="inv-stat-label">Rejected</div>
        <div class="inv-stat-value danger"><?php echo number_format($statusCounts['rejected']); ?></div>
        <div class="inv-stat-footer">Declined by admin</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#0EA5E9,#0284C7);">&#128666;</div></div>
        <div class="inv-stat-label">Awaiting Delivery</div>
        <div class="inv-stat-value"><?php echo number_format($awaitingDelivery); ?></div>
        <div class="inv-stat-footer">Ordered purchase orders</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#F59E0B,#EA580C);">&#128230;</div></div>
        <div class="inv-stat-label">Partially Received</div>
        <div class="inv-stat-value warning"><?php echo number_format($partiallyReceived); ?></div>
        <div class="inv-stat-footer">Deliveries in progress</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#22C55E,#16A34A);">&#9989;</div></div>
        <div class="inv-stat-label">Completed (30 days)</div>
        <div class="inv-stat-value success"><?php echo number_format($recentCompleted); ?></div>
        <div class="inv-stat-footer">Recently completed purchases</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#EF4444,#B91C1C);">&#9888;</div></div>
        <div class="inv-stat-label">Low-Stock Products</div>
        <div class="inv-stat-value danger"><?php echo number_format($lowStockCount); ?></div>
        <div class="inv-stat-footer">Need replenishment</div>
    </div>
</div>
<div class="inv-card" style="margin-top:18px;">
    <div class="inv-card-header">
        <div>
            <div class="inv-card-title">&#128221; Purchase Requests</div>
            <div class="inv-card-subtitle"><strong><?php echo number_format($procTotal); ?></strong> matching request(s)</div>
        </div>
        <?php if (canAccess('procurement_manage')): ?>
        <a href="?page=purchase_requests" class="inv-btn-secondary" style="text-decoration:none;">View All</a>
        <?php endif; ?>
    </div>

    <form method="get" action="index.php" class="inv-filter-bar" id="procDashFilter">
        <input type="hidden" name="page" value="proc_dashboard">
        <div class="inv-search-wrap">
            <input type="text" name="q" value="<?php echo htmlspecialchars($fltQ); ?>" placeholder="Search request #, requester, supplier..." class="inv-search-input">
        </div>
        <select name="status" class="inv-filter-select">
            <option value="">All Statuses</option>
            <option value="draft" <?php echo $fltStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
            <option value="pending_approval" <?php echo $fltStatus === 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
            <option value="revision_requested" <?php echo $fltStatus === 'revision_requested' ? 'selected' : ''; ?>>Revision Requested</option>
            <option value="approved" <?php echo $fltStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $fltStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            <option value="cancelled" <?php echo $fltStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        </select>
        <select name="supplier_id" class="inv-filter-select">
            <option value="0">All Suppliers</option>
            <?php foreach ($filterSuppliers as $fs): ?>
            <option value="<?php echo (int) $fs['id']; ?>" <?php echo $fltSupplier === (int) $fs['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($fs['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($fltFrom); ?>" class="inv-filter-select" title="From date">
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($fltTo); ?>" class="inv-filter-select" title="To date">
        <button type="submit" class="inv-btn-primary" id="procDashFilterBtn">Apply Filters</button>
        <a href="?page=proc_dashboard" class="inv-clear-filter-btn" style="text-decoration:none;">Clear</a>
    </form>

    <?php if (count($recentRequests) === 0): ?>
        <?php echo procEmptyState('No purchase requests found', 'Try adjusting the filters, or create a new purchase request.'); ?>
    <?php else: ?>
    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>Request #</th>
                    <th>Request Date</th>
                    <th>Requester</th>
                    <th>Supplier</th>
                    <th>Estimated Total</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="no-print" style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRequests as $req): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($req['request_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($req['request_date']))); ?></td>
                    <td><?php echo htmlspecialchars($req['requester_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($req['supplier_name'] ?? '—'); ?></td>
                    <td><?php echo procPeso($req['grand_total']); ?></td>
                    <td><?php echo procStatusBadge($req['status']); ?><?php echo (int) $req['over_limit'] === 1 ? ' <span class="proc-flag proc-flag-warn">Over limit</span>' : ''; ?><?php echo (int) $req['duplicate_flag'] === 1 ? ' <span class="proc-flag proc-flag-danger">Possible duplicate</span>' : ''; ?></td>
                    <td><?php echo $req['submitted_at'] ? htmlspecialchars(date('M j, Y g:i A', strtotime($req['submitted_at']))) : '—'; ?></td>
                    <td class="no-print" style="text-align:right;white-space:nowrap;">
                        <button type="button" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;" onclick="viewPurchaseRequest(<?php echo (int) $req['id']; ?>)">View</button>
                        <?php if ((int) $req['requester_id'] === (int) $_SESSION['user_id'] && in_array($req['status'], ['draft', 'revision_requested'], true)): ?>
                        <a href="?page=purchase_requests&amp;tab=create&amp;id=<?php echo (int) $req['id']; ?>" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;text-decoration:none;">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php echo procPagination($procTotal, $procPage, $procPerPage, $filterBaseUrl); ?>
    <?php endif; ?>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:18px;margin-top:18px;">
    <div class="inv-card">
        <div class="inv-card-header">
            <div>
                <div class="inv-card-title">&#128666; Open Purchase Orders</div>
                <div class="inv-card-subtitle">Draft, ordered and partially received</div>
            </div>
            <a href="?page=purchase_orders" class="inv-btn-secondary" style="text-decoration:none;">View All</a>
        </div>
        <?php if (count($openPos) === 0): ?>
            <?php echo procEmptyState('No open purchase orders', 'Approved purchase requests can be converted into purchase orders.'); ?>
        <?php else: ?>
        <div class="inv-table-wrap">
            <table class="inv-table">
                <thead><tr><th>PO Number</th><th>Supplier</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($openPos as $po): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($po['po_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($po['supplier_name'] ?? '—'); ?></td>
                        <td><?php echo procPeso($po['total_amount']); ?></td>
                        <td><?php echo procPoStatusBadge($po['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="inv-card">
        <div class="inv-card-header">
            <div>
                <div class="inv-card-title">&#9888; Low-Stock Products</div>
                <div class="inv-card-subtitle">At or below the reorder threshold</div>
            </div>
            <a href="?page=inventory_reports&amp;type=low" class="inv-btn-secondary" style="text-decoration:none;">Full Report</a>
        </div>
        <?php if (count($lowStockProducts) === 0): ?>
            <?php echo procEmptyState('All products are stocked', 'No products are currently below their low-stock threshold.'); ?>
        <?php else: ?>
        <div class="inv-table-wrap">
            <table class="inv-table">
                <thead><tr><th>Product</th><th>On Hand</th><th>Threshold</th><th>Unit</th></tr></thead>
                <tbody>
                    <?php foreach ($lowStockProducts as $lp): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($lp['name']); ?></strong></td>
                        <td><span class="badge badge-danger"><?php echo (int) $lp['stock_quantity']; ?></span></td>
                        <td><?php echo (int) $lp['low_stock_threshold']; ?></td>
                        <td><?php echo htmlspecialchars($lp['unit']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/proc_details_modal.php'; ?>

