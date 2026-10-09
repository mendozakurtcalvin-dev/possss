<?php
require_once __DIR__ . '/proc_common.php';
if (!canAccess('procurement')) {
    echo procAccessDenied();
    return;
}

$histType = $_GET['type'] ?? '';
if (!in_array($histType, ['requests', 'orders', 'receipts', 'payments'], true)) { $histType = ''; }
$histQ = trim($_GET['q'] ?? '');
$histFrom = $_GET['date_from'] ?? '';
$histTo = $_GET['date_to'] ?? '';
$df = DateTime::createFromFormat('Y-m-d', (string) $histFrom);
if (!$df || $df->format('Y-m-d') !== $histFrom) { $histFrom = ''; }
$dt = DateTime::createFromFormat('Y-m-d', (string) $histTo);
if (!$dt || $dt->format('Y-m-d') !== $histTo) { $histTo = ''; }
$histPage = max(1, intval($_GET['page'] ?? 1));
$histPerPage = 20;

$branches = [];
$branchParams = [];

if ($histType === '' || $histType === 'requests') {
    $w = [];
    $p = [];
    if ($histFrom !== '') { $w[] = 'pr.request_date >= ?'; $p[] = $histFrom; }
    if ($histTo !== '') { $w[] = 'pr.request_date <= ?'; $p[] = $histTo; }
    if ($histQ !== '') { $w[] = '(pr.request_number LIKE ? OR s.name LIKE ? OR u.full_name LIKE ?)'; $like = '%' . $histQ . '%'; array_push($p, $like, $like, $like); }
    $branches[] = "
        SELECT pr.created_at AS event_date, 'Purchase Request' AS record_type, pr.request_number AS reference_no,
               CONCAT(COALESCE(s.name, '—'), ' — ', (SELECT COUNT(*) FROM purchase_request_items i WHERE i.request_id = pr.id), ' item(s)') AS description,
               pr.grand_total AS amount, pr.status AS status, COALESCE(u.full_name, '—') AS actor, pr.id AS record_id
        FROM purchase_requests pr
        LEFT JOIN suppliers s ON pr.supplier_id = s.id
        LEFT JOIN users u ON pr.requester_id = u.id
        " . (count($w) ? (' WHERE ' . implode(' AND ', $w)) : '');
    array_push($branchParams, ...$p);
}

if ($histType === '' || $histType === 'orders') {
    $w = [];
    $p = [];
    if ($histFrom !== '') { $w[] = 'po.order_date >= ?'; $p[] = $histFrom; }
    if ($histTo !== '') { $w[] = 'po.order_date <= ?'; $p[] = $histTo; }
    if ($histQ !== '') { $w[] = '(po.po_number LIKE ? OR s.name LIKE ?)'; $like = '%' . $histQ . '%'; array_push($p, $like, $like); }
    $branches[] = "
        SELECT po.created_at AS event_date, 'Purchase Order' AS record_type, po.po_number AS reference_no,
               CONCAT(COALESCE(s.name, '—'), ' — ', COALESCE(pr.request_number, 'no linked request')) AS description,
               po.total_amount AS amount, po.status AS status, COALESCE(u.full_name, '—') AS actor, po.id AS record_id
        FROM procurement_purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        LEFT JOIN users u ON po.created_by = u.id
        " . (count($w) ? (' WHERE ' . implode(' AND ', $w)) : '');
    array_push($branchParams, ...$p);
}
if ($histType === '' || $histType === 'receipts') {
    $w = [];
    $p = [];
    if ($histFrom !== '') { $w[] = 'g.received_date >= ?'; $p[] = $histFrom; }
    if ($histTo !== '') { $w[] = 'g.received_date <= ?'; $p[] = $histTo; }
    if ($histQ !== '') { $w[] = '(g.grn_number LIKE ? OR po.po_number LIKE ? OR s.name LIKE ?)'; $like = '%' . $histQ . '%'; array_push($p, $like, $like, $like); }
    $branches[] = "
        SELECT g.created_at AS event_date, 'Goods Receipt' AS record_type, g.grn_number AS reference_no,
               CONCAT(COALESCE(po.po_number, '—'), ' — ', COALESCE(s.name, '—'),
                      ' (accepted: ', COALESCE((SELECT SUM(accepted_qty) FROM procurement_grn_items gi WHERE gi.grn_id = g.id), 0), ')') AS description,
               COALESCE((SELECT SUM(gi.accepted_qty * pi.unit_cost)
                         FROM procurement_grn_items gi
                         JOIN procurement_po_items pi ON pi.id = gi.po_item_id
                         WHERE gi.grn_id = g.id), 0) AS amount,
               g.status AS status, COALESCE(u.full_name, '—') AS actor, g.id AS record_id
        FROM procurement_grns g
        LEFT JOIN procurement_purchase_orders po ON g.po_id = po.id
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u ON g.received_by = u.id
        " . (count($w) ? (' WHERE ' . implode(' AND ', $w)) : '');
    array_push($branchParams, ...$p);
}

if ($histType === '' || $histType === 'payments') {
    $w = [];
    $p = [];
    if ($histFrom !== '') { $w[] = 'DATE(COALESCE(p.payment_date, p.created_at)) >= ?'; $p[] = $histFrom; }
    if ($histTo !== '') { $w[] = 'DATE(COALESCE(p.payment_date, p.created_at)) <= ?'; $p[] = $histTo; }
    if ($histQ !== '') { $w[] = '(i.invoice_number LIKE ? OR COALESCE(p.reference, "") LIKE ? OR s.name LIKE ?)'; $like = '%' . $histQ . '%'; array_push($p, $like, $like, $like); }
    $branches[] = "
        SELECT COALESCE(p.payment_date, p.created_at) AS event_date, 'Supplier Payment' AS record_type,
               CONCAT(i.invoice_number, COALESCE(CONCAT(' / ', p.reference), '')) AS reference_no,
               CONCAT(COALESCE(s.name, '—'), ' — ', p.provider, ' payment') AS description,
               p.amount AS amount, p.status AS status, COALESCE(u.full_name, '—') AS actor, i.id AS record_id
        FROM procurement_payments p
        JOIN procurement_invoices i ON i.id = p.invoice_id
        LEFT JOIN procurement_purchase_orders po ON i.po_id = po.id
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        LEFT JOIN users u ON p.created_by = u.id
        " . (count($w) ? (' WHERE ' . implode(' AND ', $w)) : '');
    array_push($branchParams, ...$p);
}

if (count($branches) === 0) {
    echo procAccessDenied();
    return;
}

$unionSql = implode(' UNION ALL ', $branches);
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM (' . $unionSql . ') t');
$countStmt->execute($branchParams);
$histTotal = (int) $countStmt->fetchColumn();

$histOffset = ($histPage - 1) * $histPerPage;
$histStmt = $pdo->prepare('SELECT * FROM (' . $unionSql . ') t ORDER BY event_date DESC LIMIT ' . $histOffset . ', ' . $histPerPage);
$histStmt->execute($branchParams);
$historyRows = $histStmt->fetchAll();

$histBaseUrl = '?page=proc_history';
$qp = [];
if ($histType !== '') { $qp['type'] = $histType; }
if ($histQ !== '') { $qp['q'] = $histQ; }
if ($histFrom !== '') { $qp['date_from'] = $histFrom; }
if ($histTo !== '') { $qp['date_to'] = $histTo; }
if (count($qp)) { $histBaseUrl .= '&' . http_build_query($qp); }
?>
<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title"><span class="inv-page-icon">&#128337;</span> Procurement History</h2>
        <p class="inv-page-subtitle">Complete audit trail of purchase requests, purchase orders, deliveries and supplier payments.</p>
    </div>
</div>

<div class="inv-card">
    <div class="inv-card-header">
        <div>
            <div class="inv-card-title">History</div>
            <div class="inv-card-subtitle"><strong><?php echo number_format($histTotal); ?></strong> record(s)</div>
        </div>
    </div>

    <form method="get" action="index.php" class="inv-filter-bar">
        <input type="hidden" name="page" value="proc_history">
        <div class="inv-search-wrap">
            <input type="text" name="q" value="<?php echo htmlspecialchars($histQ); ?>" placeholder="Search reference #, supplier, user..." class="inv-search-input">
        </div>
        <select name="type" class="inv-filter-select">
            <option value="">All Record Types</option>
            <option value="requests" <?php echo $histType === 'requests' ? 'selected' : ''; ?>>Purchase Requests</option>
            <option value="orders" <?php echo $histType === 'orders' ? 'selected' : ''; ?>>Purchase Orders</option>
            <option value="receipts" <?php echo $histType === 'receipts' ? 'selected' : ''; ?>>Goods Receipts</option>
            <option value="payments" <?php echo $histType === 'payments' ? 'selected' : ''; ?>>Supplier Payments</option>
        </select>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($histFrom); ?>" class="inv-filter-select" title="From date">
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($histTo); ?>" class="inv-filter-select" title="To date">
        <button type="submit" class="inv-btn-primary">Apply Filters</button>
        <a href="?page=proc_history" class="inv-clear-filter-btn" style="text-decoration:none;">Clear</a>
    </form>

    <?php if (count($historyRows) === 0): ?>
        <?php echo procEmptyState('No history records found', 'Adjust the filters to see procurement activity.'); ?>
    <?php else: ?>
    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead>
                <tr><th>Date &amp; Time</th><th>Type</th><th>Reference</th><th>Description</th><th>Amount</th><th>Status</th><th>By</th><th class="no-print" style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($historyRows as $hr): ?>
                <tr>
                    <td style="white-space:nowrap;"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($hr['event_date']))); ?></td>
                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($hr['record_type']); ?></span></td>
                    <td><strong><?php echo htmlspecialchars($hr['reference_no']); ?></strong></td>
                    <td style="max-width:300px;"><?php echo htmlspecialchars($hr['description']); ?></td>
                    <td><?php echo $hr['amount'] !== null ? procPeso($hr['amount']) : '—'; ?></td>
                    <td><?php
                        $st = (string) $hr['status'];
                        if (in_array($st, ['approved', 'received', 'paid', 'closed'], true)) { echo '<span class="badge badge-success">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $st))) . '</span>'; }
                        elseif (in_array($st, ['pending_approval', 'pending', 'partially_received', 'partial', 'draft'], true)) { echo '<span class="badge badge-warning">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $st))) . '</span>'; }
                        elseif (in_array($st, ['rejected', 'cancelled', 'failed'], true)) { echo '<span class="badge badge-danger">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $st))) . '</span>'; }
                        else { echo '<span class="badge badge-info">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $st))) . '</span>'; }
                    ?></td>
                    <td><?php echo htmlspecialchars($hr['actor']); ?></td>
                    <td class="no-print" style="text-align:right;">
                        <?php if ($hr['record_type'] === 'Purchase Request'): ?>
                        <button type="button" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;" onclick="viewPurchaseRequest(<?php echo (int) $hr['record_id']; ?>)">View</button>
                        <?php elseif ($hr['record_type'] === 'Purchase Order'): ?>
                        <a href="?page=purchase_orders&amp;q=<?php echo urlencode($hr['reference_no']); ?>" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;text-decoration:none;">View</a>
                        <?php else: ?>
                        <span style="color:#9ca3af;font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php echo procPagination($histTotal, $histPage, $histPerPage, $histBaseUrl); ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/proc_details_modal.php'; ?>


