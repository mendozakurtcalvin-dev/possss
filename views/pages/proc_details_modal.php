<?php
// Shared purchase request details modal + loader. require_once by pages that
// expose a "View" action (dashboard, purchase requests, history, approvals).
if (defined('PROC_DETAILS_MODAL_LOADED')) {
    return;
}
define('PROC_DETAILS_MODAL_LOADED', true);
?>
<div id="prDetailsModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:780px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
            <h3 id="prdTitle">Purchase Request</h3>
            <button type="button" class="inv-btn-cancel" onclick="closeModal('prDetailsModal')">Close</button>
        </div>
        <div id="prDetailsBody">
            <div class="proc-loading"><span class="proc-loading-spinner"></span> Loading purchase request...</div>
        </div>
    </div>
</div>

<?php if (!defined('PROC_USER_ID_EMITTED')): define('PROC_USER_ID_EMITTED', true); ?>
<script>window.PROC_USER_ID = <?php echo (int) ($_SESSION['user_id'] ?? 0); ?>;</script>
<?php endif; ?>
<script>
(function () {
    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function peso(value) {
        return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    var statusLabels = {
        draft: 'Draft', pending_approval: 'Pending Approval', revision_requested: 'Revision Requested',
        approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled'
    };
    var statusClasses = {
        draft: 'badge-secondary', pending_approval: 'badge-warning', revision_requested: 'badge-info',
        approved: 'badge-success', rejected: 'badge-danger', cancelled: 'badge-secondary'
    };
    window.viewPurchaseRequest = function (id) {
        var modal = document.getElementById('prDetailsModal');
        var body = document.getElementById('prDetailsBody');
        modal.style.display = 'flex';
        body.innerHTML = '<div class="proc-loading"><span class="proc-loading-spinner"></span> Loading purchase request...</div>';

        var fd = new FormData();
        fd.append('id', id);
        fetch('?action=pr_get', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) {
                    body.innerHTML = '<div class="inv-empty-state"><div class="inv-empty-icon">⚠️</div><div class="inv-empty-title">Could not load request</div><div class="inv-empty-text">' + esc(d.message) + '</div></div>';
                    return;
                }
                var r = d.request;
                document.getElementById('prdTitle').textContent = 'Purchase Request ' + r.request_number;
                var html = '';
                html += '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px;">';
                html += '<span class="badge ' + (statusClasses[r.status] || 'badge-secondary') + '">' + esc(statusLabels[r.status] || r.status) + '</span>';
                if (Number(r.over_limit) === 1) { html += '<span class="proc-flag proc-flag-warn">Over approval limit</span>'; }
                if (Number(r.duplicate_flag) === 1) { html += '<span class="proc-flag proc-flag-danger">Possible duplicate purchase</span>'; }
                html += '</div>';
                html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;font-size:13px;margin-bottom:12px;">';
                html += '<div><div style="color:#6b7280;font-weight:600;">Requester</div><div>' + esc(r.requester_name || '—') + '</div></div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Supplier</div><div>' + esc(r.supplier_name || '—') + '</div></div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Request Date</div><div>' + esc(r.request_date) + '</div></div>';
                html += '<div><div style="color:#6b7280;font-weight:600;">Estimated Total</div><div style="font-weight:700;">' + peso(r.grand_total) + '</div></div>';
                html += '</div>';
                html += '<div style="font-size:13px;margin-bottom:10px;"><div style="color:#6b7280;font-weight:600;">Reason for Purchase</div><div>' + esc(r.reason) + '</div></div>';
                if (r.remarks) { html += '<div style="font-size:13px;margin-bottom:10px;"><div style="color:#6b7280;font-weight:600;">Remarks</div><div>' + esc(r.remarks) + '</div></div>'; }
                if (r.admin_comment) { html += '<div style="font-size:13px;margin-bottom:10px;padding:10px;border-radius:10px;background:#FEF2F2;border:1px solid #FECACA;"><div style="color:#B91C1C;font-weight:700;">Admin Feedback</div><div>' + esc(r.admin_comment) + '</div></div>'; }
                if (r.duplicate_notes) {
                    html += '<div style="font-size:13px;margin-bottom:10px;padding:10px;border-radius:10px;background:#FFFBEB;border:1px solid #FDE68A;"><div style="color:#B45309;font-weight:700;">Stock / Duplicate Warnings</div><div style="white-space:pre-line;">' + esc(r.duplicate_notes) + '</div></div>';
                }
                html += '<table class="inv-table" style="margin-top:6px;"><thead><tr><th>Product</th><th>Unit</th><th>Qty</th><th>Est. Unit Price</th><th>Line Total</th><th>Stock</th></tr></thead><tbody>';
                d.items.forEach(function (it) {
                    html += '<tr><td>' + esc(it.item_name) + '</td><td>' + esc(it.unit) + '</td><td>' + esc(it.quantity) + '</td><td>' + peso(it.estimated_unit_price) + '</td><td>' + peso(it.line_total) + '</td><td>' +
                        (it.stock_quantity === null || it.stock_quantity === undefined ? '—' : (esc(it.stock_quantity) + ' / min ' + esc(it.low_stock_threshold))) + '</td></tr>';
                });
                html += '</tbody><tfoot><tr><th colspan="4" style="text-align:right;">Estimated Grand Total</th><th colspan="2">' + peso(r.grand_total) + '</th></tr></tfoot></table>';

                html += '<div style="margin-top:14px;"><div style="color:#6b7280;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Approval History</div>';
                if (!d.logs || d.logs.length === 0) {
                    html += '<div style="font-size:13px;color:#6b7280;">No activity recorded yet.</div>';
                } else {
                    html += '<div style="max-height:220px;overflow:auto;border:1px solid #eef0f3;border-radius:10px;padding:10px;">';
                    d.logs.forEach(function (log) {
                        html += '<div style="display:flex;gap:10px;padding:6px 0;border-bottom:1px dashed #eef0f3;font-size:12.5px;">' +
                            '<div style="flex:0 0 auto;color:#9ca3af;white-space:nowrap;">' + esc(log.created_at) + '</div>' +
                            '<div><strong>' + esc(log.actor_name || 'System') + '</strong> — ' + esc(String(log.action).replace(/_/g, ' ')) +
                            (log.comments ? '<div style="color:#6b7280;">' + esc(log.comments) + '</div>' : '') + '</div></div>';
                    });
                    html += '</div>';
                }
                html += '</div>';

                if (d.po) {
                    html += '<div style="margin-top:12px;font-size:13px;">Linked Purchase Order: <a href="?page=purchase_orders" style="font-weight:700;color:#2563eb;text-decoration:none;">' + esc(d.po.po_number) + '</a></div>';
                }
                var actions = '';
                if (d.can_edit) {
                    actions += '<a class="inv-btn-secondary" style="text-decoration:none;" href="?page=purchase_requests&tab=create&id=' + parseInt(r.id, 10) + '">Edit Request</a>';
                }
                if (Number(r.requester_id) === Number(window.PROC_USER_ID) && ['draft', 'pending_approval', 'revision_requested'].indexOf(r.status) !== -1) {
                    actions += '<button type="button" class="inv-btn-cancel" onclick="cancelPurchaseRequest(' + parseInt(r.id, 10) + ')">Cancel Request</button>';
                }
                if (actions) {
                    html += '<div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">' + actions + '</div>';
                }

                body.innerHTML = html;
            })
            .catch(function () {
                body.innerHTML = '<div class="inv-empty-state"><div class="inv-empty-icon">⚠️</div><div class="inv-empty-title">Connection error</div><div class="inv-empty-text">Please try again.</div></div>';
            });
    };
    window.cancelPurchaseRequest = function (id) {
        if (!window.confirm('Cancel this purchase request? This cannot be undone.')) { return; }
        var fd = new FormData();
        fd.append('id', id);
        fetch('?action=pr_cancel', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showToast('success', 'Request cancelled', 'The purchase request was cancelled.');
                    closeModal('prDetailsModal');
                    setTimeout(function () { window.location.reload(); }, 700);
                } else {
                    showToast('error', 'Could not cancel', d.message || '');
                }
            })
            .catch(function () { showToast('error', 'Connection error', 'Please try again.'); });
    };

    // Show a loading state while filter forms are submitting
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.classList && form.classList.contains('inv-filter-bar')) {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Loading...';
            }
        }
    });
})();
</script>
