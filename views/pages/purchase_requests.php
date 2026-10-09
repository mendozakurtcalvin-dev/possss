<?php
require_once __DIR__ . '/proc_common.php';
if (!canAccess('procurement')) {
    echo procAccessDenied();
    return;
}

$prTab = $_GET['tab'] ?? 'list';

if ($prTab === 'create'):
    // ==== CREATE / EDIT PURCHASE REQUEST FORM ====
    $editId = intval($_GET['id'] ?? 0);
    $editRequest = null;
    $editItems = [];
    if ($editId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ?");
        $stmt->execute([$editId]);
        $editRequest = $stmt->fetch();
        if (!$editRequest) { echo procAccessDenied(); return; }
        $isOwner = (int) $editRequest['requester_id'] === (int) $_SESSION['user_id'];
        if (!$isOwner && !isAdmin()) { echo procAccessDenied(); return; }
        if (!in_array($editRequest['status'], ['draft', 'revision_requested'], true)) {
            echo '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;"><h2 style="color:#111827;">Request Already Submitted</h2><p style="color:#6b7280;">Only drafts or requests marked "Revision Requested" can be edited.</p><a href="?page=purchase_requests" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Back to Purchase Requests</a></div>';
            return;
        }
        $itemsStmt = $pdo->prepare("SELECT * FROM purchase_request_items WHERE request_id = ? ORDER BY id");
        $itemsStmt->execute([$editId]);
        $editItems = $itemsStmt->fetchAll();
    }

    $formSuppliers = $pdo->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name")->fetchAll();
    $formProducts = $pdo->query("
        SELECT id, name, unit, cost_price, stock_quantity, low_stock_threshold
        FROM products
        WHERE archived = 0 AND active = 1
        ORDER BY name
    ")->fetchAll();
    $previewRequestNumber = $editRequest ? $editRequest['request_number'] : generatePurchaseRequestNumber();
    $formDefaults = $editRequest ?: [
        'request_date' => date('Y-m-d'),
        'supplier_id' => 0,
        'reason' => '',
        'remarks' => '',
        'status' => 'draft',
    ];
    $defaultItems = $editItems ?: [['product_id' => 0, 'quantity' => 1, 'estimated_unit_price' => 0]];
    ?>

    <div class="inv-page-header">
        <div class="inv-page-header-left">
            <h2 class="inv-page-title"><span class="inv-page-icon">&#128221;</span> <?php echo $editRequest ? 'Edit Purchase Request' : 'Create Purchase Request'; ?></h2>
            <p class="inv-page-subtitle">Select a supplier and products, enter quantities and estimated prices, then save as draft or submit for approval.</p>
        </div>
        <a href="?page=purchase_requests" class="inv-btn-secondary" style="text-decoration:none;">&larr; Back to List</a>
    </div>

    <div class="inv-card">
        <div class="inv-card-header">
            <div>
                <div class="inv-card-title"><?php echo $editRequest ? htmlspecialchars($editRequest['request_number']) : 'New Request'; ?></div>
                <div class="inv-card-subtitle">Status: <?php echo procStatusBadge($editRequest['status'] ?? 'draft'); ?></div>
            </div>
        </div>

        <div id="prFormErrors" style="display:none;padding:12px 14px;margin-bottom:14px;border-radius:10px;background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;font-size:13px;font-weight:600;white-space:pre-line;"></div>
        <div id="prFormWarnings" style="display:none;padding:12px 14px;margin-bottom:14px;border-radius:10px;background:#FFFBEB;border:1px solid #FDE68A;color:#B45309;font-size:13px;font-weight:600;white-space:pre-line;"></div>

        <form id="purchaseRequestForm" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="id" id="prFormId" value="<?php echo $editRequest ? (int) $editRequest['id'] : 0; ?>">
            <?php echo procCsrfField(); ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;">
                <div class="inv-form-group">
                    <label class="inv-form-label">Request ID (auto-generated)</label>
                    <input type="text" class="inv-form-input" value="<?php echo htmlspecialchars($previewRequestNumber); ?>" readonly style="background:#f9fafb;font-weight:700;">
                </div>
                <div class="inv-form-group">
                    <label class="inv-form-label">Request Date *</label>
                    <input type="date" name="request_date" id="prRequestDate" class="inv-form-input" value="<?php echo htmlspecialchars($formDefaults['request_date']); ?>" required>
                </div>
                <div class="inv-form-group">
                    <label class="inv-form-label">Requester Account</label>
                    <input type="text" class="inv-form-input" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? ''); ?>" readonly style="background:#f9fafb;">
                </div>
                <div class="inv-form-group">
                    <label class="inv-form-label">Supplier *</label>
                    <select name="supplier_id" id="prSupplier" class="inv-form-select" required>
                        <option value="">— Select supplier —</option>
                        <?php foreach ($formSuppliers as $fs): ?>
                        <option value="<?php echo (int) $fs['id']; ?>" <?php echo (int) $formDefaults['supplier_id'] === (int) $fs['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($fs['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="inv-form-group" style="margin-top:16px;">
                <label class="inv-form-label">Products *</label>
                <div class="inv-table-wrap" style="border:1px solid #e5e7eb;border-radius:10px;">
                    <table class="inv-table" id="prItemsTable">
                        <thead>
                            <tr>
                                <th style="width:36%;">Product</th>
                                <th>Unit</th>
                                <th style="width:110px;">Quantity *</th>
                                <th style="width:150px;">Est. Unit Price (₱) *</th>
                                <th style="width:130px;">Line Total</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="prItemsBody"></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" style="text-align:right;">Estimated Grand Total</th>
                                <th id="prGrandTotal" style="font-size:15px;">₱0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <button type="button" class="inv-btn-secondary" style="margin-top:10px;" onclick="addPrItemRow()">+ Add Product Row</button>
            </div>

            <div class="inv-form-group" style="margin-top:14px;">
                <label class="inv-form-label">Reason for Purchase *</label>
                <textarea name="reason" id="prReason" class="inv-form-textarea" rows="2" maxlength="1000" placeholder="e.g. Low stock" required><?php echo htmlspecialchars($formDefaults['reason']); ?></textarea>
            </div>
            <div class="inv-form-group" style="margin-top:14px;">
                <label class="inv-form-label">Remarks</label>
                <textarea name="remarks" id="prRemarks" class="inv-form-textarea" rows="2" maxlength="2000" placeholder="Optional notes for the approver"><?php echo htmlspecialchars($formDefaults['remarks']); ?></textarea>
            </div>
            <div class="inv-form-group" style="margin-top:14px;">
                <label class="inv-form-label">Supplier Quotation Attachment (optional — PDF/JPEG/PNG, max 5MB)</label>
                <input type="file" name="attachment" id="prAttachment" class="inv-form-input" accept=".pdf,.jpg,.jpeg,.png">
                <?php if (!empty($editRequest['attachment_path'])): ?>
                <div style="font-size:12.5px;margin-top:6px;color:#6b7280;">Current attachment: <a href="<?php echo htmlspecialchars($editRequest['attachment_path']); ?>" target="_blank" rel="noopener">view file</a></div>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;">
                <button type="button" class="inv-btn-secondary" id="prSaveDraftBtn" onclick="submitPurchaseRequest('draft')">Save as Draft</button>
                <button type="button" class="inv-btn-primary" id="prSubmitBtn" onclick="submitPurchaseRequest('submit')">Submit for Approval</button>
                <a href="?page=purchase_requests" class="inv-btn-cancel" style="text-decoration:none;line-height:34px;">Cancel</a>
            </div>
            <div id="prFormLoading" class="proc-loading" style="display:none;padding:14px 0;"><span class="proc-loading-spinner"></span> Saving purchase request...</div>

        </form>
    </div>
    <script>
    var PR_PRODUCTS = <?php echo json_encode($formProducts); ?>;
    var PR_DEFAULT_ITEMS = <?php echo json_encode(array_map(function ($it) {
        return ['product_id' => (int) $it['product_id'], 'quantity' => (int) $it['quantity'], 'estimated_unit_price' => (float) $it['estimated_unit_price']];
    }, $defaultItems)); ?>;

    function prProductSelect(selectedId) {
        var html = '<select class="inv-form-select pr-product" onchange="onPrProductChange(this)" required>';
        html += '<option value="">— Select product —</option>';
        PR_PRODUCTS.forEach(function (p) {
            html += '<option value="' + p.id + '"' + (Number(p.id) === Number(selectedId) ? ' selected' : '') + '>' +
                String(p.name).replace(/</g, '&lt;') + ' (stock: ' + p.stock_quantity + ')</option>';
        });
        html += '</select>';
        return html;
    }

    function findPrProduct(id) {
        for (var i = 0; i < PR_PRODUCTS.length; i++) {
            if (Number(PR_PRODUCTS[i].id) === Number(id)) return PR_PRODUCTS[i];
        }
        return null;
    }

    function addPrItemRow(item) {
        item = item || { product_id: 0, quantity: 1, estimated_unit_price: 0 };
        var tbody = document.getElementById('prItemsBody');
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + prProductSelect(item.product_id) + '</td>' +
            '<td class="pr-unit">—</td>' +
            '<td><input type="number" class="inv-form-input pr-qty" min="1" step="1" value="' + (item.quantity || 1) + '" oninput="recalcPrTotals()" required></td>' +
            '<td><input type="number" class="inv-form-input pr-price" min="0" step="0.01" value="' + (item.estimated_unit_price || 0) + '" oninput="recalcPrTotals()" required></td>' +
            '<td class="pr-line" style="font-weight:700;white-space:nowrap;">₱0.00</td>' +
            '<td><button type="button" class="inv-btn-cancel" style="padding:4px 8px;" onclick="this.closest(\'tr\').remove(); recalcPrTotals();" title="Remove row">&times;</button></td>';
        tbody.appendChild(tr);
        if (item.product_id) { onPrProductChange(tr.querySelector('.pr-product')); }
        recalcPrTotals();
    }

    function onPrProductChange(sel) {
        var p = findPrProduct(sel.value);
        var row = sel.closest('tr');
        row.querySelector('.pr-unit').textContent = p ? p.unit : '—';
        var priceInput = row.querySelector('.pr-price');
        if (p && (!priceInput.value || Number(priceInput.value) === 0)) {
            priceInput.value = Number(p.cost_price || 0).toFixed(2);
        }
        recalcPrTotals();
    }

    function recalcPrTotals() {
        var total = 0;
        document.querySelectorAll('#prItemsBody tr').forEach(function (row) {
            var qty = Number(row.querySelector('.pr-qty').value || 0);
            var price = Number(row.querySelector('.pr-price').value || 0);
            var line = qty * price;
            row.querySelector('.pr-line').textContent = '₱' + line.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            total += line;
        });
        document.getElementById('prGrandTotal').textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return total;
    }

    function collectPrItems() {
        var items = [];
        document.querySelectorAll('#prItemsBody tr').forEach(function (row) {
            items.push({
                product_id: Number(row.querySelector('.pr-product').value || 0),
                quantity: row.querySelector('.pr-qty').value,
                estimated_unit_price: row.querySelector('.pr-price').value
            });
        });
        return items;
    }
    function prShowFormErrors(errors) {
        var box = document.getElementById('prFormErrors');
        if (!errors || errors.length === 0) { box.style.display = 'none'; box.textContent = ''; return; }
        box.style.display = 'block';
        box.textContent = errors.map(function (e) { return '• ' + e; }).join('\n');
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function prValidateForm(items) {
        var errors = [];
        if (!document.getElementById('prSupplier').value) { errors.push('Please select a supplier.'); }
        if (!document.getElementById('prReason').value.trim()) { errors.push('Reason for purchase is required.'); }
        if (!document.getElementById('prRequestDate').value) { errors.push('Request date is required.'); }
        if (items.length === 0) { errors.push('Add at least one product row.'); }
        var seen = {};
        items.forEach(function (it) {
            if (!it.product_id) { errors.push('Every product row needs a product selection.'); return; }
            if (seen[it.product_id]) { errors.push('A product appears more than once — combine the quantities.'); }
            seen[it.product_id] = true;
            if (!(Number(it.quantity) > 0)) { errors.push('Quantity must be a positive number for every row.'); }
            if (it.estimated_unit_price === '' || isNaN(Number(it.estimated_unit_price)) || Number(it.estimated_unit_price) < 0) {
                errors.push('Estimated unit price must be a valid non-negative amount.');
            }
        });
        return errors;
    }

    function submitPurchaseRequest(mode) {
        var items = collectPrItems();
        var errors = prValidateForm(items);
        if (errors.length > 0) {
            prShowFormErrors(errors);
            showToast('error', 'Check the form', errors[0]);
            return;
        }
        prShowFormErrors([]);
        document.getElementById('prFormLoading').style.display = 'flex';
        document.getElementById('prSaveDraftBtn').disabled = true;
        document.getElementById('prSubmitBtn').disabled = true;

        var form = document.getElementById('purchaseRequestForm');
        var fd = new FormData(form);
        fd.append('items', JSON.stringify(items));

        fetch('?action=pr_save', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (saveResult) {
                if (!saveResult.success) {
                    throw saveResult;
                }
                if (saveResult.warnings && saveResult.warnings.length) {
                    var wBox = document.getElementById('prFormWarnings');
                    wBox.style.display = 'block';
                    wBox.textContent = saveResult.warnings.map(function (w) { return '• ' + w; }).join('\n');
                }
                if (mode !== 'submit') {
                    showToast('success', 'Draft saved', 'Purchase request ' + (saveResult.request_number || '') + ' was saved as a draft.');
                    setTimeout(function () { window.location.href = '?page=purchase_requests'; }, 800);
                    return;
                }
                var submitFd = new FormData();
                submitFd.append('id', saveResult.id);
                submitFd.append('csrf_token', form.querySelector('input[name="csrf_token"]').value);
                return fetch('?action=pr_submit', { method: 'POST', body: submitFd })
                    .then(function (r) { return r.json(); })
                    .then(function (submitResult) {
                        if (!submitResult.success) { throw submitResult; }
                        showToast('success', 'Submitted for approval', submitResult.request_number + ' is now pending admin approval.');
                        setTimeout(function () { window.location.href = '?page=purchase_requests'; }, 900);
                    });
            })
            .catch(function (err) {
                prShowFormErrors(err && err.errors ? err.errors : [ (err && err.message) || 'Could not save the purchase request.' ]);
                showToast('error', 'Not saved', (err && err.message) || 'Could not save the purchase request.');
                document.getElementById('prFormLoading').style.display = 'none';
                document.getElementById('prSaveDraftBtn').disabled = false;
                document.getElementById('prSubmitBtn').disabled = false;
            });
    }

    PR_DEFAULT_ITEMS.forEach(function (item) { addPrItemRow(item); });
    recalcPrTotals();

    </script>

<?php
else:
    // ==== PURCHASE REQUEST LIST ====
    $fltQ = trim($_GET['q'] ?? '');
    $fltStatus = $_GET['status'] ?? '';
    $validStatuses = ['draft', 'pending_approval', 'revision_requested', 'approved', 'rejected', 'cancelled'];
    if (!in_array($fltStatus, $validStatuses, true)) { $fltStatus = ''; }
    $fltSupplier = intval($_GET['supplier_id'] ?? 0);
    $fltFrom = $_GET['date_from'] ?? '';
    $fltTo = $_GET['date_to'] ?? '';
    $prPage = max(1, intval($_GET['page'] ?? 1));
    $prPerPage = 15;

    $where = [];
    $params = [];
    // Visibility scope: managers/approvers/finance see everything;
    // other users see their own requests plus approved ones.
    if (!$isManager) {
        $where[] = "(pr.requester_id = ? OR pr.status = 'approved')";
        $params[] = (int) $_SESSION['user_id'];
    }
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
    $prTotal = (int) $countStmt->fetchColumn();
    $prOffset = ($prPage - 1) * $prPerPage;
    $rowsStmt = $pdo->prepare('SELECT pr.*, s.name AS supplier_name, u.full_name AS requester_name' . $joinSql . $whereSql . ' ORDER BY pr.created_at DESC LIMIT ' . $prOffset . ', ' . $prPerPage);
    $rowsStmt->execute($params);
    $prRows = $rowsStmt->fetchAll();

    $itemCounts = [];
    if (count($prRows)) {
        $ids = array_map(function ($r) { return (int) $r['id']; }, $prRows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $cntStmt = $pdo->prepare("SELECT request_id, COUNT(*) AS c FROM purchase_request_items WHERE request_id IN ($placeholders) GROUP BY request_id");
        $cntStmt->execute($ids);
        foreach ($cntStmt->fetchAll() as $c) { $itemCounts[$c['request_id']] = (int) $c['c']; }
    }

    $prBaseUrl = '?page=purchase_requests';
    $qp = [];
    if ($fltQ !== '') { $qp['q'] = $fltQ; }
    if ($fltStatus !== '') { $qp['status'] = $fltStatus; }
    if ($fltSupplier > 0) { $qp['supplier_id'] = $fltSupplier; }
    if ($fltFrom !== '') { $qp['date_from'] = $fltFrom; }
    if ($fltTo !== '') { $qp['date_to'] = $fltTo; }
    if (count($qp)) { $prBaseUrl .= '&' . http_build_query($qp); }

    $listSuppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
    ?>
    <div class="inv-page-header">
        <div class="inv-page-header-left">
            <h2 class="inv-page-title"><span class="inv-page-icon">&#128221;</span> Purchase Requests</h2>
            <p class="inv-page-subtitle">Create, submit and track purchase requests and admin feedback.</p>
        </div>
        <?php if (canAccess('procurement_manage')): ?>
        <a href="?page=purchase_requests&amp;tab=create" class="inv-btn-primary" style="text-decoration:none;">+ Create Purchase Request</a>
        <?php endif; ?>
    </div>

    <div class="inv-card">
        <div class="inv-card-header">
            <div>
                <div class="inv-card-title">All Requests</div>
                <div class="inv-card-subtitle"><strong><?php echo number_format($prTotal); ?></strong> matching request(s)</div>
            </div>
        </div>
        <form method="get" action="index.php" class="inv-filter-bar">
            <input type="hidden" name="page" value="purchase_requests">
            <div class="inv-search-wrap">
                <input type="text" name="q" value="<?php echo htmlspecialchars($fltQ); ?>" placeholder="Search request #, requester, supplier..." class="inv-search-input">
            </div>
            <select name="status" class="inv-filter-select">
                <option value="">All Statuses</option>
                <?php foreach ($validStatuses as $vs): ?>
                <option value="<?php echo $vs; ?>" <?php echo $fltStatus === $vs ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $vs)); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="supplier_id" class="inv-filter-select">
                <option value="0">All Suppliers</option>
                <?php foreach ($listSuppliers as $ls): ?>
                <option value="<?php echo (int) $ls['id']; ?>" <?php echo $fltSupplier === (int) $ls['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ls['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($fltFrom); ?>" class="inv-filter-select" title="From date">
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($fltTo); ?>" class="inv-filter-select" title="To date">
            <button type="submit" class="inv-btn-primary">Apply Filters</button>
            <a href="?page=purchase_requests" class="inv-clear-filter-btn" style="text-decoration:none;">Clear</a>
        </form>

        <?php if (count($prRows) === 0): ?>
            <?php echo procEmptyState('No purchase requests found', 'Create a purchase request or adjust the filters.'); ?>
        <?php else: ?>
        <div class="inv-table-wrap">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Date</th>
                        <th>Requester</th>
                        <th>Supplier</th>
                        <th>Items</th>
                        <th>Estimated Total</th>
                        <th>Status</th>
                        <th class="no-print" style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prRows as $req): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($req['request_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars(date('M j, Y', strtotime($req['request_date']))); ?></td>
                        <td><?php echo htmlspecialchars($req['requester_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($req['supplier_name'] ?? '—'); ?></td>
                        <td><?php echo (int) ($itemCounts[$req['id']] ?? 0); ?></td>
                        <td><?php echo procPeso($req['grand_total']); ?></td>
                        <td>
                            <?php echo procStatusBadge($req['status']); ?>
                            <?php if ((int) $req['over_limit'] === 1): ?><span class="proc-flag proc-flag-warn">Over limit</span><?php endif; ?>
                            <?php if ((int) $req['duplicate_flag'] === 1): ?><span class="proc-flag proc-flag-danger">Possible duplicate</span><?php endif; ?>
                        </td>
                        <td class="no-print" style="text-align:right;white-space:nowrap;">
                            <button type="button" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;" onclick="viewPurchaseRequest(<?php echo (int) $req['id']; ?>)">View</button>
                            <?php if ((int) $req['requester_id'] === (int) $_SESSION['user_id'] && in_array($req['status'], ['draft', 'revision_requested'], true)): ?>
                            <a href="?page=purchase_requests&amp;tab=create&amp;id=<?php echo (int) $req['id']; ?>" class="inv-btn-secondary" style="padding:5px 10px;font-size:12px;text-decoration:none;">Edit</a>
                            <?php endif; ?>
                            <?php if ((int) $req['requester_id'] === (int) $_SESSION['user_id'] && $req['status'] === 'draft'): ?>
                            <button type="button" class="inv-btn-primary" style="padding:5px 10px;font-size:12px;" onclick="submitExistingDraft(<?php echo (int) $req['id']; ?>)">Submit</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php echo procPagination($prTotal, $prPage, $prPerPage, $prBaseUrl); ?>
        <?php endif; ?>
    </div>
    <script>
    function submitExistingDraft(id) {
        if (!window.confirm('Submit this purchase request for admin approval?')) { return; }
        var fd = new FormData();
        fd.append('id', id);
        fetch('?action=pr_submit', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showToast('success', 'Submitted for approval', (d.request_number || 'Request') + ' is now pending admin approval.');
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    showToast('error', 'Could not submit', d.message || '');
                }
            })
            .catch(function () { showToast('error', 'Connection error', 'Please try again.'); });
    }
    </script>
    <?php require_once __DIR__ . '/proc_details_modal.php'; ?>

<?php
endif;
?>

