<?php
require_once __DIR__ . '/proc_common.php';
if (!hasPermission('procurement_approve')) {
    echo procAccessDenied();
    return;
}

// ---- Decision statistics ----
$decCounts = ['pending_approval' => 0, 'approved' => 0, 'rejected' => 0, 'revision_requested' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) AS c FROM purchase_requests WHERE status IN ('pending_approval','approved','rejected','revision_requested') GROUP BY status") as $row) {
    $decCounts[$row['status']] = (int) $row['c'];
}

// ---- Pending queue with filters ----
$fltQ = trim($_GET['q'] ?? '');
$fltSupplier = intval($_GET['supplier_id'] ?? 0);
$fltFlag = $_GET['flag'] ?? '';
if (!in_array($fltFlag, ['over_limit', 'duplicate'], true)) { $fltFlag = ''; }
$apPage = max(1, intval($_GET['page'] ?? 1));
$apPerPage = 12;

$where = ["pr.status = 'pending_approval'"];
$params = [];
if ($fltQ !== '') {
    $where[] = '(pr.request_number LIKE ? OR u.full_name LIKE ? OR s.name LIKE ? OR pr.reason LIKE ?)';
    $like = '%' . $fltQ . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($fltSupplier > 0) { $where[] = 'pr.supplier_id = ?'; $params[] = $fltSupplier; }
if ($fltFlag === 'over_limit') { $where[] = 'pr.over_limit = 1'; }
if ($fltFlag === 'duplicate') { $where[] = 'pr.duplicate_flag = 1'; }
$whereSql = ' WHERE ' . implode(' AND ', $where);
$joinSql = ' FROM purchase_requests pr
    LEFT JOIN suppliers s ON pr.supplier_id = s.id
    LEFT JOIN users u ON pr.requester_id = u.id';

$countStmt = $pdo->prepare('SELECT COUNT(*)' . $joinSql . $whereSql);
$countStmt->execute($params);
$apTotal = (int) $countStmt->fetchColumn();
$apOffset = ($apPage - 1) * $apPerPage;
$listStmt = $pdo->prepare('SELECT pr.*, s.name AS supplier_name, u.full_name AS requester_name' . $joinSql . $whereSql . ' ORDER BY pr.submitted_at ASC LIMIT ' . $apOffset . ', ' . $apPerPage);
$listStmt->execute($params);
$pendingRows = $listStmt->fetchAll();

$itemCounts = [];
if (count($pendingRows)) {
    $ids = array_map(function ($r) { return (int) $r['id']; }, $pendingRows);
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $cntStmt = $pdo->prepare("SELECT request_id, COUNT(*) AS c, SUM(quantity) AS qty FROM purchase_request_items WHERE request_id IN ($ph) GROUP BY request_id");
    $cntStmt->execute($ids);
    foreach ($cntStmt->fetchAll() as $c) { $itemCounts[$c['request_id']] = $c; }
}

$apBaseUrl = '?page=proc_approvals';
$qp = [];
if ($fltQ !== '') { $qp['q'] = $fltQ; }
if ($fltSupplier > 0) { $qp['supplier_id'] = $fltSupplier; }
if ($fltFlag !== '') { $qp['flag'] = $fltFlag; }
if (count($qp)) { $apBaseUrl .= '&' . http_build_query($qp); }

$apSuppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

// ---- Recent decisions (approval history) ----
$recentDecisions = $pdo->query("
    SELECT l.*, pr.request_number, u.full_name AS actor_name, ru.full_name AS requester_name
    FROM procurement_approval_logs l
    JOIN purchase_requests pr ON pr.id = l.request_id
    LEFT JOIN users u ON l.actor_id = u.id
    LEFT JOIN users ru ON pr.requester_id = ru.id
    WHERE l.action IN ('approved','rejected','revision_requested','submitted','resubmitted')
    ORDER BY l.created_at DESC
    LIMIT 15
")->fetchAll();
?>
<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title"><span class="inv-page-icon">&#9989;</span> Procurement Approvals</h2>
        <p class="inv-page-subtitle">Review submitted purchase requests, then approve, reject, or request revisions with comments.</p>
    </div>
</div>

<div class="inv-stats-grid">
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#F59E0B,#D97706);">&#9203;</div></div>
        <div class="inv-stat-label">Pending Approval</div>
        <div class="inv-stat-value warning"><?php echo number_format($decCounts['pending_approval']); ?></div>
        <div class="inv-stat-footer">Awaiting your decision</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#10B981,#059669);">&#9989;</div></div>
        <div class="inv-stat-label">Approved</div>
        <div class="inv-stat-value success"><?php echo number_format($decCounts['approved']); ?></div>
        <div class="inv-stat-footer">Ready for purchase orders</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#EF4444,#DC2626);">&#10060;</div></div>
        <div class="inv-stat-label">Rejected</div>
        <div class="inv-stat-value danger"><?php echo number_format($decCounts['rejected']); ?></div>
        <div class="inv-stat-footer">Declined requests</div>
    </div>
    <div class="inv-stat-card">
        <div class="inv-stat-header"><div class="inv-stat-icon" style="background:linear-gradient(135deg,#0EA5E9,#0284C7);">&#9998;</div></div>
        <div class="inv-stat-label">Revision Requested</div>
        <div class="inv-stat-value"><?php echo number_format($decCounts['revision_requested']); ?></div>
        <div class="inv-stat-footer">Waiting on requester</div>
    </div>
</div>
<div class="inv-card" style="margin-top:18px;">
    <div class="inv-card-header">
        <div>
            <div class="inv-card-title">&#128221; Pending Purchase Requests</div>
            <div class="inv-card-subtitle"><strong><?php echo number_format($apTotal); ?></strong> request(s) awaiting decision</div>
        </div>
    </div>

    <form method="get" action="index.php" class="inv-filter-bar">
        <input type="hidden" name="page" value="proc_approvals">
        <div class="inv-search-wrap">
            <input type="text" name="q" value="<?php echo htmlspecialchars($fltQ); ?>" placeholder="Search request #, requester, supplier..." class="inv-search-input">
        </div>
        <select name="supplier_id" class="inv-filter-select">
            <option value="0">All Suppliers</option>
            <?php foreach ($apSuppliers as $asup): ?>
            <option value="<?php echo (int) $asup['id']; ?>" <?php echo $fltSupplier === (int) $asup['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($asup['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="flag" class="inv-filter-select">
            <option value="">All Requests</option>
            <option value="over_limit" <?php echo $fltFlag === 'over_limit' ? 'selected' : ''; ?>>Over approval limit</option>
            <option value="duplicate" <?php echo $fltFlag === 'duplicate' ? 'selected' : ''; ?>>Possible duplicates</option>
        </select>
        <button type="submit" class="inv-btn-primary">Apply Filters</button>
        <a href="?page=proc_approvals" class="inv-clear-filter-btn" style="text-decoration:none;">Clear</a>
    </form>

    <?php if (count($pendingRows) === 0): ?>
        <?php echo procEmptyState('No pending requests', 'All caught up — there are no purchase requests waiting for approval.'); ?>
    <?php else: ?>
    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead>
                <tr>
                    <th>Request #</th>
                    <th>Submitted</th>
                    <th>Requester</th>
                    <th>Supplier</th>
                    <th>Items</th>
                    <th>Estimated Total</th>
                    <th>Reason</th>
                    <th>Flags</th>
                    <th class="no-print" style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingRows as $prw): ?>
                <?php $cnt = $itemCounts[$prw['id']] ?? null; ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($prw['request_number']); ?></strong></td>
                    <td><?php echo $prw['submitted_at'] ? htmlspecialchars(date('M j, Y g:i A', strtotime($prw['submitted_at']))) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($prw['requester_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($prw['supplier_name'] ?? '—'); ?></td>
                    <td><?php echo (int) ($cnt['c'] ?? 0); ?> item(s), <?php echo (int) ($cnt['qty'] ?? 0); ?> unit(s)</td>
                    <td><?php echo procPeso($prw['grand_total']); ?></td>
                    <td style="max-width:180px;"><?php echo htmlspecialchars(mb_strimwidth($prw['reason'], 0, 90, '…')); ?></td>
                    <td>
                        <?php if ((int) $prw['over_limit'] === 1): ?><span class="proc-flag proc-flag-warn">Over limit</span><?php endif; ?>
                        <?php if ((int) $prw['duplicate_flag'] === 1): ?><span class="proc-flag proc-flag-danger">Possible duplicate</span><?php endif; ?>
                        <?php if ((int) $prw['over_limit'] !== 1 && (int) $prw['duplicate_flag'] !== 1): ?><span style="color:#9ca3af;font-size:12px;">—</span><?php endif; ?>
                    </td>
                    <td class="no-print" style="text-align:right;white-space:nowrap;">
                        <button type="button" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;" onclick="viewPurchaseRequest(<?php echo (int) $prw['id']; ?>)">View</button>
                        <?php if ((int) $prw['requester_id'] === (int) $_SESSION['user_id']): ?>
                        <span class="proc-flag proc-flag-warn" title="You cannot decide on your own request">Own request</span>
                        <?php else: ?>
                        <button type="button" class="inv-btn-primary" style="padding:5px 10px;font-size:12px;" onclick="openDecisionModal(<?php echo (int) $prw['id']; ?>, '<?php echo htmlspecialchars($prw['request_number'], ENT_QUOTES); ?>')">Review</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php echo procPagination($apTotal, $apPage, $apPerPage, $apBaseUrl); ?>
    <?php endif; ?>
</div>
<div class="inv-card" style="margin-top:18px;">
    <div class="inv-card-header">
        <div>
            <div class="inv-card-title">&#128337; Approval History</div>
            <div class="inv-card-subtitle">Latest submissions and decisions with timestamps</div>
        </div>
    </div>
    <?php if (count($recentDecisions) === 0): ?>
        <?php echo procEmptyState('No history yet', 'Submissions and approval decisions will be recorded here.'); ?>
    <?php else: ?>
    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead><tr><th>Timestamp</th><th>Request #</th><th>Requester</th><th>Action</th><th>By</th><th>Comments</th></tr></thead>
            <tbody>
                <?php foreach ($recentDecisions as $log): ?>
                <tr>
                    <td style="white-space:nowrap;"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($log['created_at']))); ?></td>
                    <td><strong><?php echo htmlspecialchars($log['request_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($log['requester_name'] ?? '—'); ?></td>
                    <td>
                        <?php
                        $actionMap = [
                            'submitted' => ['badge-warning', 'Submitted'],
                            'resubmitted' => ['badge-warning', 'Resubmitted'],
                            'approved' => ['badge-success', 'Approved'],
                            'rejected' => ['badge-danger', 'Rejected'],
                            'revision_requested' => ['badge-info', 'Revision Requested'],
                        ];
                        $am = $actionMap[$log['action']] ?? ['badge-secondary', ucfirst(str_replace('_', ' ', $log['action']))];
                        ?>
                        <span class="badge <?php echo $am[0]; ?>"><?php echo htmlspecialchars($am[1]); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></td>
                    <td style="max-width:280px;"><?php echo htmlspecialchars(mb_strimwidth((string) $log['comments'], 0, 140, '…')); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Decision modal -->
<div id="decisionModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:680px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <h3 id="decisionTitle">Review Purchase Request</h3>
            <button type="button" class="inv-btn-cancel" onclick="closeModal('decisionModal')">Close</button>
        </div>
        <div id="decisionSummary" style="font-size:13px;color:#374151;margin:6px 0 10px;"></div>
        <div id="decisionItems"></div>
        <label class="inv-form-label" style="margin-top:12px;display:block;">Comments</label>
        <textarea id="decisionComments" class="inv-form-textarea" rows="3" placeholder="Required for Reject and Request Revision; optional for Approve."></textarea>
        <div id="decisionError" style="display:none;padding:10px 12px;margin-top:10px;border-radius:10px;background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;font-size:13px;font-weight:600;"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
            <button type="button" class="inv-btn-primary" onclick="submitDecision('approve')">✔ Approve</button>
            <button type="button" class="inv-btn-cancel" style="line-height:34px;" onclick="submitDecision('reject')">✖ Reject</button>
            <button type="button" class="inv-btn-secondary" onclick="submitDecision('revision')">✎ Request Revision</button>
        </div>
        <p style="font-size:12px;color:#6b7280;margin-top:10px;">Decisions are recorded with your account and a timestamp. Approving is disabled for your own requests.</p>
    </div>
</div>
<script>
(function () {
    var currentDecisionId = null;

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function peso(v) {
        return '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    window.openDecisionModal = function (id, requestNumber) {
        currentDecisionId = id;
        document.getElementById('decisionTitle').textContent = 'Review ' + requestNumber;
        document.getElementById('decisionSummary').innerHTML = '<div class="proc-loading" style="padding:16px 0;"><span class="proc-loading-spinner"></span> Loading request...</div>';
        document.getElementById('decisionItems').innerHTML = '';
        document.getElementById('decisionComments').value = '';
        var err = document.getElementById('decisionError');
        err.style.display = 'none';
        err.textContent = '';
        document.getElementById('decisionModal').style.display = 'flex';

        var fd = new FormData();
        fd.append('id', id);
        fetch('?action=pr_get', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) {
                    document.getElementById('decisionSummary').textContent = d.message || 'Could not load the request.';
                    return;
                }
                if (!d.can_decide) {
                    document.getElementById('decisionSummary').innerHTML = '<span class="proc-flag proc-flag-warn">This request cannot be decided on (it may already be processed, or it is your own request).</span>';
                }
                var r2 = d.request;
                var html = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;">';
                html += '<div><div style="color:#6b7280;font-weight:600;">Requester</div>' + esc(r2.requester_name || '—') + '</div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Supplier</div>' + esc(r2.supplier_name || '—') + '</div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Requested</div>' + esc(r2.request_date) + '</div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Estimated Total</div><strong>' + peso(r2.grand_total) + '</strong></div>';
                html += '</div>';
                html += '<div style="margin-top:8px;"><div style="color:#6b7280;font-weight:600;">Reason</div>' + esc(r2.reason) + '</div>';
                if (Number(r2.over_limit) === 1) { html += '<div style="margin-top:6px;"><span class="proc-flag proc-flag-warn">Over the configured approval limit</span></div>'; }
                document.getElementById('decisionSummary').innerHTML = html;

                var itemsHtml = '<table class="inv-table"><thead><tr><th>Product</th><th>Qty</th><th>Est. Unit Price</th><th>Line Total</th><th>Current Stock</th></tr></thead><tbody>';
                d.items.forEach(function (it) {
                    var low = Number(it.stock_quantity) <= Number(it.low_stock_threshold);
                    itemsHtml += '<tr><td>' + esc(it.item_name) + '</td><td>' + esc(it.quantity) + ' ' + esc(it.unit) + '</td><td>' + peso(it.estimated_unit_price) + '</td><td>' + peso(it.line_total) + '</td><td>' +
                        (it.stock_quantity === null ? '—' : ('<span class="badge ' + (low ? 'badge-danger' : 'badge-success') + '">' + esc(it.stock_quantity) + '</span> / min ' + esc(it.low_stock_threshold))) + '</td></tr>';
                });
                itemsHtml += '</tbody></table>';
                if (d.warnings && d.warnings.length) {
                    itemsHtml += '<div style="margin-top:8px;padding:10px;border-radius:10px;background:#FFFBEB;border:1px solid #FDE68A;color:#B45309;font-size:12.5px;white-space:pre-line;">' + d.warnings.map(function (w) { return '• ' + esc(w); }).join('\n') + '</div>';
                }
                document.getElementById('decisionItems').innerHTML = itemsHtml;
            })
            .catch(function () {
                document.getElementById('decisionSummary').textContent = 'Connection error — please try again.';
            });
    };
    window.submitDecision = function (decision) {
        if (!currentDecisionId) { return; }
        var comments = document.getElementById('decisionComments').value.trim();
        var err = document.getElementById('decisionError');
        if (decision === 'reject' && comments === '') {
            err.style.display = 'block';
            err.textContent = 'A rejection reason is required.';
            return;
        }
        if (decision === 'revision' && comments === '') {
            err.style.display = 'block';
            err.textContent = 'A revision comment is required.';
            return;
        }
        err.style.display = 'none';

        var label = decision === 'approve' ? 'Approve' : (decision === 'reject' ? 'Reject' : 'Request revision for');
        if (!window.confirm(label + ' this purchase request? This decision is recorded with your account.')) { return; }

        var fd = new FormData();
        fd.append('id', currentDecisionId);
        fd.append('decision', decision);
        fd.append('comments', comments);
        fetch('?action=pr_decide', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showToast('success', 'Decision recorded', 'The request is now: ' + String(d.status).replace(/_/g, ' ') + '.');
                    closeModal('decisionModal');
                    setTimeout(function () { window.location.reload(); }, 900);
                } else {
                    err.style.display = 'block';
                    err.textContent = d.message || 'Could not record the decision.';
                    showToast('error', 'Not recorded', d.message || '');
                }
            })
            .catch(function () {
                err.style.display = 'block';
                err.textContent = 'Connection error — please try again.';
            });
    };

})();
</script>
<?php require_once __DIR__ . '/proc_details_modal.php'; ?>




