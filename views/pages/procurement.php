<?php
if (!canAccess('procurement')) {
    echo '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;">
        <h2 style="color:#111827;">Access Denied</h2>
        <a href="?page=dashboard" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Go to Dashboard</a>
    </div>';
    return;
}
$canManageProcurement = canAccess('procurement_manage') || isAdmin();
?>

<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title">Procurement</h2>
        <p class="inv-page-subtitle">Request, approve, and receive stock from suppliers before it reaches the shelves</p>
    </div>
    <button class="inv-btn-primary" onclick="showNewProcurement()">+ New Request</button>
</div>

<div class="inv-card">
    <div class="inv-card-header"><div class="inv-card-title">Procurement Requests</div></div>
    <table class="inv-table" style="width:100%;">
        <thead>
            <tr>
                <th>Request #</th><th>Item</th><th>Qty</th><th>Est. Cost</th><th>Supplier</th>
                <th>Requested By</th><th>Status</th><th>Date</th><?php if ($canManageProcurement) echo '<th>Actions</th>'; ?>
            </tr>
        </thead>
        <tbody id="procurementBody"><tr><td colspan="9">Loading...</td></tr></tbody>
    </table>
</div>

<div id="procurementModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>New Procurement Request</h3>
        <form id="procurementForm">
            <label>Item name *</label><input name="item_name" required style="width:100%;">
            <label>Linked product (stock will increase on receive)</label>
            <select name="product_id" id="procProduct" style="width:100%;"><option value="">-- None --</option></select>
            <label>Quantity *</label><input name="quantity" type="number" min="1" required style="width:100%;">
            <label>Estimated unit cost</label><input name="estimated_unit_cost" type="number" step="0.01" style="width:100%;">
            <label>Supplier</label><select name="supplier_id" id="procSupplier" style="width:100%;"><option value="">--</option></select>
            <label>Department</label><input name="department" style="width:100%;">
            <label>Notes</label><textarea name="notes" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Submit</button>
                <button type="button" onclick="document.getElementById('procurementModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
var canManageProcurement = <?php echo $canManageProcurement ? 'true' : 'false'; ?>;

function showNewProcurement() {
    document.getElementById('procurementModal').style.display = 'flex';
}

function loadProcurement() {
    fetch('?action=get_procurement').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        rows.forEach(function(r){
            html += '<tr>' +
                '<td>' + r.req_number + '</td>' +
                '<td>' + r.item_name + '</td>' +
                '<td>' + r.quantity + '</td>' +
                '<td>' + parseFloat(r.estimated_unit_cost).toFixed(2) + '</td>' +
                '<td>' + (r.supplier_name || '-') + '</td>' +
                '<td>' + (r.requested_by_name || '-') + '</td>' +
                '<td><span class="badge">' + r.status + '</span></td>' +
                '<td>' + r.created_at + '</td>';
            if (canManageProcurement) {
                html += '<td>';
                if (r.status === 'pending') {
                    html += '<button onclick="setProcStatus(' + r.id + ',\'approved\')">Approve</button> ';
                    html += '<button onclick="setProcStatus(' + r.id + ',\'rejected\')">Reject</button> ';
                }
                if (r.status === 'approved') html += '<button onclick="setProcStatus(' + r.id + ',\'ordered\')">Mark Ordered</button> ';
                if (r.status === 'ordered') html += '<button onclick="setProcStatus(' + r.id + ',\'received\')">Mark Received</button> ';
                html += '</td>';
            }
            html += '</tr>';
        });
        if (!rows.length) html = '<tr><td colspan="9">No requests yet</td></tr>';
        document.getElementById('procurementBody').innerHTML = html;
    });
}

function setProcStatus(id, status) {
    var fd = new FormData();
    fd.append('id', id); fd.append('status', status);
    fetch('?action=update_procurement_status', {method:'POST', body:fd})
        .then(function(r){return r.json();}).then(function(d){
            if (d.success) loadProcurement(); else alert(d.message);
        });
}

document.getElementById('procurementForm').addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(this);
    fetch('?action=save_procurement', {method:'POST', body:fd})
        .then(function(r){return r.json();}).then(function(d){
            if (d.success) {
                document.getElementById('procurementModal').style.display = 'none';
                document.getElementById('procurementForm').reset();
                loadProcurement();
            } else alert(d.message);
        });
});

fetch('?action=get_suppliers').then(function(r){return r.json();}).then(function(rows){
    var sel = document.getElementById('procSupplier');
    rows.forEach(function(s){ var o = document.createElement('option'); o.value = s.id; o.textContent = s.name; sel.appendChild(o); });
});
fetch('?action=get_products').then(function(r){return r.json();}).then(function(rows){
    var sel = document.getElementById('procProduct');
    (rows || []).forEach(function(p){
        var o = document.createElement('option');
        o.value = p.id;
        o.textContent = p.name + ' (stock: ' + (p.stock_quantity ?? p.stock ?? 0) + ')';
        sel.appendChild(o);
    });
});
loadProcurement();
</script>
