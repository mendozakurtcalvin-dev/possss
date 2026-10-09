<?php
require_once __DIR__ . '/proc_common.php';
if (!canAccess('procurement_manage') && !isAdmin()) {
    echo procAccessDenied();
    return;
}

// Purchase orders that can still receive goods
$receivablePos = $pdo->query("
    SELECT po.id, po.po_number, po.status, po.expected_delivery, s.name AS supplier_name,
           (SELECT COALESCE(SUM(quantity), 0) FROM procurement_po_items WHERE po_id = po.id) AS total_qty,
           (SELECT COALESCE(SUM(quantity_received), 0) FROM procurement_po_items WHERE po_id = po.id) AS received_qty
    FROM procurement_purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.status IN ('ordered','sent','acknowledged','partially_received')
    ORDER BY po.order_date DESC
")->fetchAll();

$selectedPoId = intval($_GET['po'] ?? 0);
$selectedPo = null;
$poItems = [];
if ($selectedPoId > 0) {
    $selStmt = $pdo->prepare("
        SELECT po.*, s.name AS supplier_name, pr.request_number
        FROM procurement_purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
        WHERE po.id = ? AND po.status IN ('ordered','sent','acknowledged','partially_received')
    ");
    $selStmt->execute([$selectedPoId]);
    $selectedPo = $selStmt->fetch();
    if ($selectedPo) {
        $itemStmt = $pdo->prepare("
            SELECT i.*, (i.quantity - i.quantity_received) AS outstanding
            FROM procurement_po_items i
            WHERE i.po_id = ?
            ORDER BY i.id
        ");
        $itemStmt->execute([$selectedPoId]);
        $poItems = $itemStmt->fetchAll();
    }
}

// Recent goods receipts with their line items
$recentReceipts = $pdo->query("
    SELECT g.*, po.po_number, s.name AS supplier_name, u.full_name AS received_by_name
    FROM procurement_grns g
    LEFT JOIN procurement_purchase_orders po ON g.po_id = po.id
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN users u ON g.received_by = u.id
    ORDER BY g.created_at DESC
    LIMIT 10
")->fetchAll();
$receiptItems = [];
if (count($recentReceipts)) {
    $grnIds = array_map(function ($g) { return (int) $g['id']; }, $recentReceipts);
    $ph = implode(',', array_fill(0, count($grnIds), '?'));
    $riStmt = $pdo->prepare("SELECT * FROM procurement_grn_items WHERE grn_id IN ($ph) ORDER BY id");
    $riStmt->execute($grnIds);
    foreach ($riStmt->fetchAll() as $ri) {
        $receiptItems[$ri['grn_id']][] = $ri;
    }
}
$receiptToken = bin2hex(random_bytes(16));
?>
<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title"><span class="inv-page-icon">&#128230;</span> Receive Deliveries</h2>
        <p class="inv-page-subtitle">Inspect incoming goods, record damaged or missing items, and confirm receipt to update stock.</p>
    </div>
    <a href="?page=purchase_orders" class="inv-btn-secondary" style="text-decoration:none;">&larr; Purchase Orders</a>
</div>

<div class="inv-card">
    <div class="inv-card-header">
        <div>
            <div class="inv-card-title">&#128666; Orders Awaiting Delivery</div>
            <div class="inv-card-subtitle"><?php echo count($receivablePos); ?> order(s) open for receiving</div>
        </div>
    </div>

    <?php if (count($receivablePos) === 0): ?>
        <?php echo procEmptyState('No orders awaiting delivery', 'Issue a purchase order first — only Ordered (or later) purchase orders can receive goods.'); ?>
    <?php else: ?>
    <form method="get" action="index.php" class="inv-filter-bar">
        <input type="hidden" name="page" value="receive_deliveries">
        <select name="po" class="inv-filter-select" style="min-width:320px;" onchange="this.form.submit()">
            <option value="0">— Select a purchase order —</option>
            <?php foreach ($receivablePos as $rp): ?>
            <option value="<?php echo (int) $rp['id']; ?>" <?php echo $selectedPoId === (int) $rp['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($rp['po_number'] . ' — ' . ($rp['supplier_name'] ?? 'No supplier') . ' (' . $rp['received_qty'] . '/' . $rp['total_qty'] . ' received)'); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="inv-btn-primary">Load Order</button>
    </form>
    <?php endif; ?>

    <?php if ($selectedPo): ?>
    <div style="margin-top:18px;padding:14px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;">
        <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-weight:800;color:#111827;font-size:15px;"><?php echo htmlspecialchars($selectedPo['po_number']); ?> <?php echo procPoStatusBadge($selectedPo['status']); ?></div>
                <div style="font-size:13px;color:#6b7280;margin-top:4px;">
                    Supplier: <strong><?php echo htmlspecialchars($selectedPo['supplier_name'] ?? '—'); ?></strong>
                    <?php if ($selectedPo['request_number']): ?> &middot; Request: <strong><?php echo htmlspecialchars($selectedPo['request_number']); ?></strong><?php endif; ?>
                    <?php if ($selectedPo['expected_delivery']): ?> &middot; Expected: <strong><?php echo htmlspecialchars(date('M j, Y', strtotime($selectedPo['expected_delivery']))); ?></strong><?php endif; ?>
                </div>
            </div>
        </div>

        <div id="receiptErrors" style="display:none;padding:10px 12px;margin-top:12px;border-radius:10px;background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;font-size:13px;font-weight:600;white-space:pre-line;"></div>

        <form id="receiptForm" style="margin-top:14px;">
            <input type="hidden" name="po_id" value="<?php echo (int) $selectedPo['id']; ?>">
            <input type="hidden" name="client_token" value="<?php echo htmlspecialchars($receiptToken); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="inv-form-group">
                    <label class="inv-form-label">Delivery Date *</label>
                    <input type="date" name="received_date" class="inv-form-input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="inv-form-group">
                    <label class="inv-form-label">Delivery Reference</label>
                    <input type="text" name="delivery_reference" class="inv-form-input" placeholder="DR / waybill no.">
                </div>
                <div class="inv-form-group">
                    <label class="inv-form-label">Supplier Invoice Reference</label>
                    <input type="text" name="invoice_reference" class="inv-form-input" placeholder="Invoice no.">
                </div>
                <div class="inv-form-group">
                    <label class="inv-form-label">Remarks</label>
                    <input type="text" name="remarks" class="inv-form-input" placeholder="Optional notes">
                </div>
            </div>
            <div class="inv-table-wrap" style="border:1px solid #e5e7eb;border-radius:10px;margin-top:12px;overflow-x:auto;">
                <table class="inv-table" style="min-width:900px;">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Ordered</th>
                            <th>Previously Received</th>
                            <th>Remaining</th>
                            <th style="width:110px;">Delivered Now *</th>
                            <th style="width:110px;">Accepted *</th>
                            <th style="width:110px;">Damaged</th>
                            <th style="width:120px;">Batch #</th>
                            <th style="width:140px;">Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($poItems as $item): ?>
                        <?php $remaining = max(0, (int) $item['quantity'] - (int) $item['quantity_received']); ?>
                        <tr data-po-item="<?php echo (int) $item['id']; ?>" data-remaining="<?php echo $remaining; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                <?php if (!$item['product_id']): ?><div style="font-size:11px;color:#9ca3af;">No catalog link — stock will not update</div><?php endif; ?>
                            </td>
                            <td><?php echo (int) $item['quantity']; ?></td>
                            <td><?php echo (int) $item['quantity_received']; ?></td>
                            <td><span class="badge <?php echo $remaining > 0 ? 'badge-warning' : 'badge-success'; ?>"><?php echo $remaining; ?></span></td>
                            <td><input type="number" class="inv-form-input rc-delivered" min="0" max="<?php echo $remaining; ?>" step="1" value="<?php echo $remaining; ?>" oninput="recalcReceiptRow(this)"></td>
                            <td><input type="number" class="inv-form-input rc-accepted" min="0" max="<?php echo $remaining; ?>" step="1" value="<?php echo $remaining; ?>" oninput="recalcReceiptRow(this)"></td>
                            <td><input type="number" class="inv-form-input rc-damaged" min="0" step="1" value="0" oninput="recalcReceiptRow(this)"></td>
                            <td><input type="text" class="inv-form-input rc-batch" placeholder="Optional"></td>
                            <td><input type="date" class="inv-form-input rc-expiry"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p style="font-size:12px;color:#6b7280;margin-top:8px;">
                Stock increases <strong>only for accepted quantities</strong> when the receipt is confirmed.
                Delivered = Accepted + Damaged. You may receive less than the remaining quantity (partial delivery) but never more.
            </p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;align-items:center;">
                <button type="button" class="inv-btn-primary" id="confirmReceiptBtn" onclick="confirmReceipt()">Confirm Receipt &amp; Update Stock</button>
                <a href="?page=receive_deliveries" class="inv-btn-cancel" style="text-decoration:none;line-height:34px;">Reset</a>
            </div>
            <div id="receiptLoading" class="proc-loading" style="display:none;padding:12px 0;"><span class="proc-loading-spinner"></span> Recording delivery and updating stock...</div>

        </form>
    </div>
    <?php endif; ?>
</div>
<div class="inv-card" style="margin-top:18px;">
    <div class="inv-card-header">
        <div>
            <div class="inv-card-title">&#128230; Recent Delivery Receipts</div>
            <div class="inv-card-subtitle">Last <?php echo count($recentReceipts); ?> goods receipt(s) with accepted, damaged and missing quantities</div>
        </div>
    </div>
    <?php if (count($recentReceipts) === 0): ?>
        <?php echo procEmptyState('No deliveries recorded yet', 'Confirmed receipts will appear here with their line-item details.'); ?>
    <?php else: ?>
    <div class="inv-table-wrap">
        <table class="inv-table">
            <thead>
                <tr><th>GRN #</th><th>PO</th><th>Supplier</th><th>Date</th><th>Received By</th><th>Reference</th><th>Items</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($recentReceipts as $grn): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($grn['grn_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($grn['po_number'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($grn['supplier_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars(date('M j, Y', strtotime($grn['received_date']))); ?></td>
                    <td><?php echo htmlspecialchars($grn['received_by_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($grn['delivery_reference'] ?? '—'); ?></td>
                    <td>
                        <?php foreach (($receiptItems[$grn['id']] ?? []) as $ri): ?>
                        <div style="font-size:12px;white-space:nowrap;">
                            <?php echo htmlspecialchars($ri['item_name']); ?>:
                            <strong>+<?php echo (int) $ri['accepted_qty']; ?></strong> accepted
                            <?php if ((int) $ri['damaged_qty'] > 0): ?>, <span style="color:#B91C1C;"><?php echo (int) $ri['damaged_qty']; ?> damaged</span><?php endif; ?>
                            <?php if ((int) $ri['missing_qty'] > 0): ?>, <span style="color:#B45309;"><?php echo (int) $ri['missing_qty']; ?> missing</span><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </td>
                    <td><span class="badge <?php echo $grn['status'] === 'received' ? 'badge-success' : 'badge-warning'; ?>"><?php echo htmlspecialchars(ucfirst($grn['status'])); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<script>
function recalcReceiptRow(input) {
    var row = input.closest('tr');
    var delivered = Number(row.querySelector('.rc-delivered').value || 0);
    var accepted = Number(row.querySelector('.rc-accepted').value || 0);
    var damaged = Number(row.querySelector('.rc-damaged').value || 0);
    var remaining = Number(row.getAttribute('data-remaining') || 0);
    if (delivered > remaining) { delivered = remaining; row.querySelector('.rc-delivered').value = remaining; }
    if (accepted + damaged > delivered) {
        if (input.classList.contains('rc-accepted')) {
            row.querySelector('.rc-damaged').value = Math.max(0, delivered - accepted);
        } else {
            accepted = Math.max(0, delivered - damaged);
            row.querySelector('.rc-accepted').value = accepted;
        }
    }
}

function showReceiptErrors(errors) {
    var box = document.getElementById('receiptErrors');
    if (!box) { return; }
    if (!errors || !errors.length) { box.style.display = 'none'; box.textContent = ''; return; }
    box.style.display = 'block';
    box.textContent = errors.map(function (e) { return '• ' + e; }).join('\n');
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function confirmReceipt() {
    var form = document.getElementById('receiptForm');
    if (!form) { return; }
    var items = [];
    var errors = [];
    document.querySelectorAll('#receiptForm tr[data-po-item]').forEach(function (row) {
        var delivered = Number(row.querySelector('.rc-delivered').value || 0);
        var accepted = Number(row.querySelector('.rc-accepted').value || 0);
        var damaged = Number(row.querySelector('.rc-damaged').value || 0);
        var remaining = Number(row.getAttribute('data-remaining') || 0);
        if (delivered === 0 && accepted === 0 && damaged === 0) { return; }
        var name = row.querySelector('strong').textContent;
        if (accepted + damaged > delivered) {
            errors.push('Accepted + damaged cannot exceed delivered for ' + name + '.');
        }
        if (delivered > remaining) {
            errors.push('Delivered quantity exceeds the remaining order quantity for ' + name + '.');
        }
        items.push({
            po_item_id: Number(row.getAttribute('data-po-item')),
            delivered: delivered, accepted: accepted, damaged: damaged,
            batch_number: row.querySelector('.rc-batch').value,
            expiry_date: row.querySelector('.rc-expiry').value
        });
    });
    if (items.length === 0) { errors.push('Enter the delivered quantities for at least one item.'); }
    if (errors.length > 0) {
        showReceiptErrors(errors);
        showToast('error', 'Check the delivery', errors[0]);
        return;
    }
    showReceiptErrors([]);
    if (!window.confirm('Confirm this receipt? Accepted quantities will increase stock and cannot be undone.')) { return; }
    var btn = document.getElementById('confirmReceiptBtn');
    btn.disabled = true;
    document.getElementById('receiptLoading').style.display = 'flex';

    var fd = new FormData(form);
    fd.append('received_items', JSON.stringify(items));
    fetch('?action=receipt_save', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                showToast('success', 'Delivery recorded', d.grn_number + ' saved. PO status: ' + String(d.po_status).replace(/_/g, ' ') + '.');
                setTimeout(function () { window.location.reload(); }, 1000);
            } else {
                showReceiptErrors([d.message || 'Could not record the delivery.']);
                showToast('error', 'Receipt not saved', d.message || '');
                btn.disabled = false;
                document.getElementById('receiptLoading').style.display = 'none';
            }
        })
        .catch(function () {
            showReceiptErrors(['Connection error — please try again.']);
            showToast('error', 'Connection error', 'Please try again.');
            btn.disabled = false;
            document.getElementById('receiptLoading').style.display = 'none';
        });

}
</script>



