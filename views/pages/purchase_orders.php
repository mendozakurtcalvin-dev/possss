<?php
require_once __DIR__ . '/proc_common.php';
if (!canAccess('procurement')) {
    echo procAccessDenied();
    return;
}
$canManagePo = canAccess('procurement_manage') || isAdmin();

// ---- Approved requests that still need a purchase order ----
$awaitingPo = [];
if ($canManagePo) {
    $awaitingPo = $pdo->query("
        SELECT pr.id, pr.request_number, pr.grand_total, pr.request_date, pr.supplier_id,
               s.name AS supplier_name, u.full_name AS requester_name,
               (SELECT COUNT(*) FROM purchase_request_items i WHERE i.request_id = pr.id) AS item_count
        FROM purchase_requests pr
        LEFT JOIN suppliers s ON pr.supplier_id = s.id
        LEFT JOIN users u ON pr.requester_id = u.id
        WHERE pr.status = 'approved'
          AND NOT EXISTS (SELECT 1 FROM procurement_purchase_orders po WHERE po.purchase_request_id = pr.id)
        ORDER BY pr.decided_at DESC
        LIMIT 20
    ")->fetchAll();
}

// ---- Filters for the purchase order list ----
$fltQ = trim($_GET['q'] ?? '');
$fltStatus = $_GET['status'] ?? '';
$poStatuses = ['draft', 'pending', 'approved', 'ordered', 'sent', 'acknowledged', 'partially_received', 'delivered', 'received', 'closed', 'cancelled'];
if (!in_array($fltStatus, $poStatuses, true)) { $fltStatus = ''; }
$fltSupplier = intval($_GET['supplier_id'] ?? 0);
$fltFrom = $_GET['date_from'] ?? '';
$fltTo = $_GET['date_to'] ?? '';
$poPageNum = max(1, intval($_GET['page'] ?? 1));
$poPerPage = 15;

$where = [];
$params = [];
if ($fltQ !== '') {
    $where[] = '(po.po_number LIKE ? OR s.name LIKE ? OR pr.request_number LIKE ?)';
    $like = '%' . $fltQ . '%';
    array_push($params, $like, $like, $like);
}
if ($fltStatus !== '') { $where[] = 'po.status = ?'; $params[] = $fltStatus; }
if ($fltSupplier > 0) { $where[] = 'po.supplier_id = ?'; $params[] = $fltSupplier; }
$df = DateTime::createFromFormat('Y-m-d', (string) $fltFrom);
if (!$df || $df->format('Y-m-d') !== $fltFrom) { $fltFrom = ''; }
$dt = DateTime::createFromFormat('Y-m-d', (string) $fltTo);
if (!$dt || $dt->format('Y-m-d') !== $fltTo) { $fltTo = ''; }
if ($fltFrom !== '') { $where[] = 'po.order_date >= ?'; $params[] = $fltFrom; }
if ($fltTo !== '') { $where[] = 'po.order_date <= ?'; $params[] = $fltTo; }
$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';
$joinSql = ' FROM procurement_purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id';

$countStmt = $pdo->prepare('SELECT COUNT(*)' . $joinSql . $whereSql);
$countStmt->execute($params);
$poTotal = (int) $countStmt->fetchColumn();
$poOffset = ($poPageNum - 1) * $poPerPage;
$listStmt = $pdo->prepare('SELECT po.*, s.name AS supplier_name, pr.request_number' . $joinSql . $whereSql . ' ORDER BY po.created_at DESC LIMIT ' . $poOffset . ', ' . $poPerPage);
$listStmt->execute($params);
$poRows = $listStmt->fetchAll();

$poBaseUrl = '?page=purchase_orders';
$qp = [];
if ($fltQ !== '') { $qp['q'] = $fltQ; }
if ($fltStatus !== '') { $qp['status'] = $fltStatus; }
if ($fltSupplier > 0) { $qp['supplier_id'] = $fltSupplier; }
if ($fltFrom !== '') { $qp['date_from'] = $fltFrom; }
if ($fltTo !== '') { $qp['date_to'] = $fltTo; }
if (count($qp)) { $poBaseUrl .= '&' . http_build_query($qp); }

$poSuppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

// received progress per PO on this page
$poProgress = [];
if (count($poRows)) {
    $ids = array_map(function ($r) { return (int) $r['id']; }, $poRows);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $progStmt = $pdo->prepare("SELECT po_id, SUM(quantity) AS ordered, SUM(quantity_received) AS received FROM procurement_po_items WHERE po_id IN ($ph) GROUP BY po_id");
    $progStmt->execute($ids);
    foreach ($progStmt->fetchAll() as $p) { $poProgress[$p['po_id']] = $p; }
}
?>
<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title"><span class="inv-page-icon">&#128230;</span> Purchase Orders</h2>
        <p class="inv-page-subtitle">Generate purchase orders from approved requests, verify details, then issue them as Ordered.</p>
    </div>
    <a href="?page=receive_deliveries" class="inv-btn-secondary" style="text-decoration:none;">Receive Deliveries &rarr;</a>
</div>

<?php if ($canManagePo): ?>
<div class="inv-card">
    <div class="inv-card-header">
        <div>
            <div class="inv-card-title">&#9989; Approved Requests Awaiting Purchase Order</div>
            <div class="inv-card-subtitle"><?php echo count($awaitingPo); ?> approved request(s) with no purchase order yet</div>
        </div>
    </div>
    <?php if (count($awaitingPo) === 0): ?>
        <?php echo procEmptyState('No approved requests waiting', 'When the admin approves a purchase request it appears here so you can generate a purchase order.'); ?>
    <?php else: ?>
    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead>
                <tr><th>Request #</th><th>Requester</th><th>Supplier</th><th>Items</th><th>Estimated Total</th><th>Approved</th><th class="no-print" style="text-align:right;">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($awaitingPo as $apr): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($apr['request_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($apr['requester_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($apr['supplier_name'] ?? '—'); ?></td>
                    <td><?php echo (int) $apr['item_count']; ?></td>
                    <td><?php echo procPeso($apr['grand_total']); ?></td>
                    <td><?php echo $apr['request_date'] ? htmlspecialchars(date('M j, Y', strtotime($apr['request_date']))) : '—'; ?></td>
                    <td class="no-print" style="text-align:right;white-space:nowrap;">
                        <button type="button" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;" onclick="viewPurchaseRequest(<?php echo (int) $apr['id']; ?>)">View</button>
                        <button type="button" class="inv-btn-primary" style="padding:5px 10px;font-size:12px;" onclick="openGeneratePo(<?php echo (int) $apr['id']; ?>, '<?php echo htmlspecialchars($apr['request_number'], ENT_QUOTES); ?>')">Generate PO</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="inv-card" style="margin-top:18px;">
    <div class="inv-card-header">
        <div>
            <div class="inv-card-title">&#128230; All Purchase Orders</div>
            <div class="inv-card-subtitle"><strong><?php echo number_format($poTotal); ?></strong> matching order(s)</div>
        </div>
    </div>
    <form method="get" action="index.php" class="inv-filter-bar">
        <input type="hidden" name="page" value="purchase_orders">
        <div class="inv-search-wrap">
            <input type="text" name="q" value="<?php echo htmlspecialchars($fltQ); ?>" placeholder="Search PO #, supplier, request #..." class="inv-search-input">
        </div>
        <select name="status" class="inv-filter-select">
            <option value="">All Statuses</option>
            <?php foreach ($poStatuses as $ps): ?>
            <option value="<?php echo $ps; ?>" <?php echo $fltStatus === $ps ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $ps)); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="supplier_id" class="inv-filter-select">
            <option value="0">All Suppliers</option>
            <?php foreach ($poSuppliers as $psup): ?>
            <option value="<?php echo (int) $psup['id']; ?>" <?php echo $fltSupplier === (int) $psup['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($psup['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($fltFrom); ?>" class="inv-filter-select" title="From date">
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($fltTo); ?>" class="inv-filter-select" title="To date">
        <button type="submit" class="inv-btn-primary">Apply Filters</button>
        <a href="?page=purchase_orders" class="inv-clear-filter-btn" style="text-decoration:none;">Clear</a>
    </form>

    <?php if (count($poRows) === 0): ?>
        <?php echo procEmptyState('No purchase orders found', 'Generate a purchase order from an approved request, or adjust the filters.'); ?>
    <?php else: ?>
    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Source Request</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Expected</th>
                    <th>Total</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th class="no-print" style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($poRows as $poRow): ?>
                <?php $prog = $poProgress[$poRow['id']] ?? null; ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($poRow['po_number']); ?></strong></td>
                    <td><?php echo $poRow['request_number'] ? htmlspecialchars($poRow['request_number']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($poRow['supplier_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($poRow['order_date']))); ?></td>
                    <td><?php echo $poRow['expected_delivery'] ? htmlspecialchars(date('M j, Y', strtotime($poRow['expected_delivery']))) : '—'; ?></td>
                    <td><?php echo procPeso($poRow['total_amount']); ?></td>
                    <td><?php echo $prog ? ((int) $prog['received'] . ' / ' . (int) $prog['ordered']) : '—'; ?></td>
                    <td><?php echo procPoStatusBadge($poRow['status']); ?></td>
                    <td class="no-print" style="text-align:right;white-space:nowrap;">
                        <button type="button" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;" onclick="viewPoDetails(<?php echo (int) $poRow['id']; ?>)">View</button>
                        <?php if ($canManagePo && $poRow['status'] === 'draft'): ?>
                        <button type="button" class="inv-btn-primary" style="padding:5px 10px;font-size:12px;" onclick="issuePurchaseOrder(<?php echo (int) $poRow['id']; ?>, '<?php echo htmlspecialchars($poRow['po_number'], ENT_QUOTES); ?>')">Issue (Ordered)</button>
                        <?php endif; ?>
                        <?php if ($canManagePo && in_array($poRow['status'], ['ordered', 'sent', 'acknowledged', 'partially_received'], true)): ?>
                        <a href="?page=receive_deliveries&amp;po=<?php echo (int) $poRow['id']; ?>" class="inv-btn-primary" style="padding:5px 10px;font-size:12px;text-decoration:none;">Receive</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php echo procPagination($poTotal, $poPageNum, $poPerPage, $poBaseUrl); ?>
    <?php endif; ?>
</div>
<!-- Generate PO modal -->
<div id="generatePoModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:480px;">
        <h3>Generate Purchase Order</h3>
        <p style="font-size:13px;color:#6b7280;" id="genPoRequestLabel">Review the details, then generate the draft purchase order.</p>
        <form id="generatePoForm">
            <input type="hidden" name="request_id" id="genPoRequestId">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <label>Expected Delivery Date</label>
            <input type="date" name="expected_delivery" style="width:100%;">
            <label>Payment Terms</label>
            <input name="payment_terms" value="Net 30" style="width:100%;">
            <label>Notes</label>
            <textarea name="notes" style="width:100%;" rows="2" placeholder="Optional notes for this order"></textarea>
            <p style="font-size:12px;color:#6b7280;margin-top:10px;">The purchase order is saved as a <strong>Draft</strong>. You can verify the details and then issue it as <strong>Ordered</strong>.</p>
            <div style="margin-top:1rem;display:flex;gap:8px;">
                <button type="submit" class="inv-btn-primary">Generate Draft PO</button>
                <button type="button" class="inv-btn-cancel" onclick="closeModal('generatePoModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- PO details modal -->
<div id="poDetailsModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:760px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <h3 id="poDetailTitle">Purchase Order</h3>
            <button type="button" class="inv-btn-cancel" onclick="closeModal('poDetailsModal')">Close</button>
        </div>
        <div id="poDetailsBody">
            <div class="proc-loading"><span class="proc-loading-spinner"></span> Loading purchase order...</div>
        </div>
    </div>
</div>
<script>
(function () {
    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function peso(v) {
        return '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    window.openGeneratePo = function (requestId, requestNumber) {
        document.getElementById('genPoRequestId').value = requestId;
        document.getElementById('genPoRequestLabel').textContent = 'Generating a draft purchase order for ' + requestNumber + '. Nothing is sent to the supplier automatically.';
        document.getElementById('generatePoModal').style.display = 'flex';
    };

    document.getElementById('generatePoForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(this);
        fetch('?action=po_from_request', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    closeModal('generatePoModal');
                    showToast('success', 'Purchase order created', d.po_number + ' was created as a draft. Verify and issue it when ready.');
                    setTimeout(function () { window.location.reload(); }, 900);
                } else {
                    showToast('error', 'Could not create PO', d.message || '');
                }
            })
            .catch(function () { showToast('error', 'Connection error', 'Please try again.'); });
    });

    window.issuePurchaseOrder = function (poId, poNumber) {
        if (!window.confirm('Issue ' + poNumber + ' and mark it as Ordered? Verify the details first — this confirms the order for receiving.')) { return; }
        var fd = new FormData();
        fd.append('po_id', poId);
        fetch('?action=po_issue', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showToast('success', 'Order issued', poNumber + ' is now marked as Ordered.');
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    showToast('error', 'Could not issue order', d.message || '');
                }
            })
            .catch(function () { showToast('error', 'Connection error', 'Please try again.'); });
    };
    window.viewPoDetails = function (poId) {
        var modal = document.getElementById('poDetailsModal');
        var body = document.getElementById('poDetailsBody');
        modal.style.display = 'flex';
        body.innerHTML = '<div class="proc-loading"><span class="proc-loading-spinner"></span> Loading purchase order...</div>';
        fetch('?action=po_items&po_id=' + parseInt(poId, 10))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) {
                    body.innerHTML = '<div class="inv-empty-state"><div class="inv-empty-icon">⚠️</div><div class="inv-empty-title">Could not load order</div><div class="inv-empty-text">' + esc(d.message) + '</div></div>';
                    return;
                }
                var po = d.po;
                document.getElementById('poDetailTitle').textContent = 'Purchase Order ' + po.po_number;
                var html = '';
                html += '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px;">';
                html += '<span class="badge badge-secondary">' + esc(String(po.status).replace(/_/g, ' ')) + '</span>';
                if (po.request_number) { html += '<span style="font-size:12.5px;color:#6b7280;">Source request: <strong>' + esc(po.request_number) + '</strong></span>'; }
                html += '</div>';
                html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;font-size:13px;margin-bottom:12px;">';
                html += '<div><div style="color:#6b7280;font-weight:600;">Supplier</div><div>' + esc(po.supplier_name || '—') + '</div></div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Order Date</div><div>' + esc(po.order_date) + '</div></div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Expected Delivery</div><div>' + esc(po.expected_delivery || '—') + '</div></div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Payment Terms</div><div>' + esc(po.payment_terms || '—') + '</div></div>';
                html += '</div>';
                html += '<table class="inv-table"><thead><tr><th>Product</th><th>Qty Ordered</th><th>Received</th><th>Outstanding</th><th>Unit Cost</th><th>Total</th></tr></thead><tbody>';
                d.items.forEach(function (it) {
                    html += '<tr><td>' + esc(it.item_name) + '</td><td>' + esc(it.quantity) + '</td><td>' + esc(it.quantity_received) + '</td><td>' + esc(it.outstanding) + '</td><td>' + peso(it.unit_cost) + '</td><td>' + peso(it.total_cost) + '</td></tr>';
                });
                html += '</tbody><tfoot><tr><th colspan="5" style="text-align:right;">Subtotal</th><th>' + peso(po.subtotal) + '</th></tr>';
                html += '<tr><th colspan="5" style="text-align:right;">Tax</th><th>' + peso(po.tax_amount) + '</th></tr>';
                html += '<tr><th colspan="5" style="text-align:right;">Total</th><th>' + peso(po.total_amount) + '</th></tr></tfoot></table>';
                if (po.notes) {
                    html += '<div style="font-size:13px;margin-top:10px;"><div style="color:#6b7280;font-weight:600;">Notes</div><div>' + esc(po.notes) + '</div></div>';
                }
                body.innerHTML = html;
            })
            .catch(function () {
                body.innerHTML = '<div class="inv-empty-state"><div class="inv-empty-icon">⚠️</div><div class="inv-empty-title">Connection error</div><div class="inv-empty-text">Please try again.</div></div>';
            });
    };

})();
</script>
<?php require_once __DIR__ . '/proc_details_modal.php'; ?>




