<?php
if (!canAccess('procurement')) {
    echo '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;">
        <h2 style="color:#111827;">Access Denied</h2>
        <a href="?page=dashboard" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Go to Dashboard</a>
    </div>';
    return;
}
$canManageProcurement = canAccess('procurement_manage') || isAdmin();
$isFinance = hasRole('finance') || isAdmin() || canAccess('finance');
?>

<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title">Procurement</h2>
        <p class="inv-page-subtitle">Source and evaluate suppliers, compare products, make purchasing decisions, then order and receive goods.</p>
    </div>
</div>

<div class="proc-stage-guide" id="procurementStageGuide" aria-live="polite">
    <div class="proc-stage-guide-index" id="procurementStageIndex">01 / 08</div>
    <div class="proc-stage-guide-copy">
        <div class="proc-stage-guide-title" id="procurementStageTitle">Requisition &amp; Budget</div>
        <div class="proc-stage-guide-description" id="procurementStageDescription">Identify the need, submit a request, and confirm budget availability before sourcing.</div>
    </div>
    <div class="proc-stage-guide-roles" id="procurementStageRoles">Operations <span>Finance</span></div>
</div>

<style>
.inv-table th { background:#f9fafb !important; color:#6b7280 !important; font-size:12px !important; text-transform:uppercase; letter-spacing:.5px; }
.inv-table tbody tr:hover { background:#f9fafb; }
.inv-card { border-radius:14px !important; box-shadow:0 1px 3px rgba(0,0,0,.06) !important; border:1px solid #eef0f3 !important; }
.inv-page-title { font-size:24px !important; }
.inv-table td button { border:none; background:#e0ecff; color:#1d4ed8; font-weight:600; font-size:12px; padding:6px 12px; border-radius:20px; cursor:pointer; margin:2px 2px; transition:all .15s ease; }
.inv-table td button:hover { background:#2563eb; color:#fff; }
.inv-modal-content { border-radius:16px !important; }
.inv-modal-content h3 { margin-top:0; font-size:18px; }
.inv-modal-content label { display:block; margin-top:10px; font-size:13px; font-weight:600; color:#374151; }
.inv-modal-content input, .inv-modal-content select, .inv-modal-content textarea { margin-top:4px; padding:9px 12px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:14px; width:100%; }
.inv-modal-content input:focus, .inv-modal-content select:focus, .inv-modal-content textarea:focus { border-color:#2563eb; outline:none; }
.proc-stage-guide { display:flex; align-items:center; gap:14px; margin:0 0 16px; padding:12px 16px; border:1px solid #e8ebf0; border-radius:12px; background:#fff; }
.proc-stage-guide-index { flex:0 0 auto; color:#64748b; font-size:11px; font-weight:700; letter-spacing:.06em; }
.proc-stage-guide-copy { min-width:0; flex:1; }
.proc-stage-guide-title { color:#172033; font-size:13px; font-weight:650; }
.proc-stage-guide-description { margin-top:3px; color:#64748b; font-size:12px; line-height:1.45; }
.proc-stage-guide-roles { display:flex; flex:0 0 auto; flex-wrap:wrap; gap:6px; color:#52627a; font-size:11px; }
.proc-stage-guide-roles span { padding-left:7px; border-left:1px solid #d9dee7; }
#poModal .po-add-item-button { display:inline-flex; align-items:center; gap:6px; margin-top:8px; padding:8px 12px; border:1px solid #dbe5f2; border-radius:8px; background:#f3f7fc; color:#24466f; font-family:inherit; font-size:12px; font-weight:600; cursor:pointer; transition:background .15s ease,border-color .15s ease; }
#poModal .po-add-item-button:hover { border-color:#b8cbe3; background:#eaf1fa; }
#poModal .po-add-item-button:focus-visible { outline:2px solid #7395c6; outline-offset:2px; }
@media (max-width:640px) { .proc-stage-guide { align-items:flex-start; flex-wrap:wrap; gap:8px 12px; } .proc-stage-guide-roles { width:100%; padding-left:46px; } }
</style>

<section class="procurement-main">
<!-- REQUISITIONS -->
<div class="proc-pane" id="pane-requisitions">
    <div class="inv-card">
        <div class="inv-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div class="inv-card-title">Requisition &amp; Budget Check</div>
            <button class="inv-btn-primary" onclick="showNewProcurement()">+ New Request</button>
        </div>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>Request #</th><th>Item</th><th>Stock / Min</th><th>Requested</th><th>Est. Cost</th><th>Supplier</th><th>Dept / Cost Centre</th><th>Budget</th><th>Status</th><th>Date</th><?php if ($canManageProcurement || $isFinance) echo '<th>Actions</th>'; ?></tr></thead>
            <tbody id="procurementBody"><tr><td colspan="11">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- RFQ -->
<div class="proc-pane" id="pane-rfqs" style="display:none;">
    <div class="inv-card">
        <div class="inv-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div class="inv-card-title">RFQs / Sourcing</div>
            <?php if ($canManageProcurement): ?><button class="inv-btn-primary" onclick="document.getElementById('rfqModal').style.display='flex'">+ New RFQ</button><?php endif; ?>
        </div>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>RFQ #</th><th>Title</th><th>Qty</th><th>Est. Value</th><th>Deadline</th><th>Status</th><th></th></tr></thead>
            <tbody id="rfqBody"></tbody>
        </table>
    </div>
    <div class="inv-card" style="margin-top:1rem;">
        <div class="inv-card-title">Quotations</div>
        <div style="margin-bottom:.5rem;">Select an RFQ above to view its quotations.</div>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>Supplier / Type</th><th>Unit Price</th><th>Total</th><th>Lead (days)</th><th>Supplier Performance</th><th>Terms</th><th>Negotiation</th><th>Selected</th><th>Action</th></tr></thead>
            <tbody id="quotationBody"></tbody>
        </table>
        <?php if ($canManageProcurement): ?><button class="inv-btn-primary" style="margin-top:.5rem;" onclick="document.getElementById('quotationModal').style.display='flex'">+ Add Quotation</button><?php endif; ?>
    </div>
</div>

<!-- PROCUREMENT DECISION SUPPORT -->
<div class="proc-pane" id="pane-analysis" style="display:none;">
    <div class="inv-card">
        <div class="inv-card-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <div class="inv-card-title">Supplier Selection &amp; Evaluation</div>
                <div style="font-size:12px;color:#6b7280;margin-top:4px;">Recommendations use quality, availability, 90-day sales demand, and recorded supplier performance.</div>
            </div>
            <?php if ($canManageProcurement): ?><button class="inv-btn-primary" onclick="document.getElementById('evaluationModal').style.display='flex'">+ Evaluate Source</button><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;margin:0 0 10px;flex-wrap:wrap;">
            <input id="analysisSearch" type="search" placeholder="Search product or supplier" oninput="filterProcurementTable('analysisBody','analysisSearch','analysisRecommendation','recommendation')" style="max-width:280px;">
            <select id="analysisRecommendation" onchange="filterProcurementTable('analysisBody','analysisSearch','analysisRecommendation','recommendation')" style="max-width:220px;">
                <option value="">All recommendations</option>
                <option value="long_term">Recommended for Long-Term</option>
                <option value="short_term">Recommended for Short-Term</option>
                <option value="needs_review">Needs Review</option>
                <option value="not_recommended">Not Recommended</option>
            </select>
        </div>
        <div style="overflow-x:auto;">
            <table class="inv-table" style="width:100%;min-width:1120px;">
                <thead><tr><th>Product / Supplier</th><th>Source Type</th><th>Stock / Min</th><th>90d Demand</th><th>Unit Price</th><th>Quality / Availability</th><th>Lead / MOQ</th><th>Supplier Performance</th><th>Recommendation</th><th>Reason</th><?php if ($canManageProcurement) echo '<th>Decision</th>'; ?></tr></thead>
                <tbody id="analysisBody"><tr><td colspan="10">Loading evaluations...</td></tr></tbody>
            </table>
        </div>
    </div>
    <div class="inv-card" style="margin-top:1rem;">
        <div class="inv-card-title">Purchasing History &amp; Sourcing Frequency</div>
        <p style="font-size:12px;color:#6b7280;">Historical orders remain available in Purchase Orders and supplier records; received quantities are recorded in Goods Receipts.</p>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>Product</th><th>Supplier</th><th>Orders</th><th>Units Ordered</th><th>Last Purchase</th><th>Average Unit Cost</th></tr></thead>
            <tbody id="historyBody"><tr><td colspan="6">Loading history...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- PURCHASE ORDERS -->
<div class="proc-pane" id="pane-orders" style="display:none;">
    <div class="inv-card">
        <div class="inv-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div class="inv-card-title">Purchase Orders</div>
            <?php if ($canManageProcurement): ?><button class="inv-btn-primary" onclick="document.getElementById('poModal').style.display='flex'">+ New PO</button><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;margin:0 0 10px;flex-wrap:wrap;">
            <input id="poSearch" type="search" placeholder="Search PO number or supplier" oninput="filterProcurementTable('poBody','poSearch','poStatusFilter','status')" style="max-width:280px;">
            <select id="poStatusFilter" onchange="filterProcurementTable('poBody','poSearch','poStatusFilter','status')" style="max-width:220px;">
                <option value="">All statuses</option>
                <option value="draft">Draft</option><option value="pending">Pending</option>
                <option value="approved">Approved</option><option value="ordered">Ordered</option>
                <option value="partially_received">Partially Received</option><option value="received">Received</option>
                <option value="sent">Sent</option><option value="acknowledged">Acknowledged</option>
                <option value="delivered">Delivered</option><option value="closed">Closed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>PO #</th><th>Supplier</th><th>Total</th><th>Terms</th><th>Status</th><th>Ack</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody id="poBody"></tbody>
        </table>
    </div>
</div>

<!-- DELIVERY -->
<div class="proc-pane" id="pane-delivery" style="display:none;">
    <div class="inv-card">
        <div class="inv-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div class="inv-card-title">Order Fulfilment &amp; Goods Receiving</div>
            <?php if ($canManageProcurement): ?><button class="inv-btn-primary" onclick="document.getElementById('grnModal').style.display='flex'">+ New GRN</button><?php endif; ?>
        </div>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>GRN #</th><th>PO</th><th>Supplier</th><th>Date</th><th>Status</th><th>Discrepancies</th></tr></thead>
            <tbody id="grnBody"></tbody>
        </table>
    </div>
</div>

<!-- INVOICES -->
<div class="proc-pane" id="pane-invoices" style="display:none;">
    <div class="inv-card">
        <div class="inv-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div class="inv-card-title">Supplier Invoices &amp; 3-Way Match</div>
            <button class="inv-btn-primary" onclick="document.getElementById('invoiceModal').style.display='flex'">+ Record Invoice</button>
        </div>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>Invoice #</th><th>PO</th><th>Supplier</th><th>Amount</th><th>Match</th><th>Payment</th><th>Actions</th></tr></thead>
            <tbody id="invoiceBody"></tbody>
        </table>
    </div>
</div>

<!-- PAYMENTS -->
<div class="proc-pane" id="pane-payments" style="display:none;">
    <div class="inv-card">
        <div class="inv-card-title">Payments (PayMongo)</div>
        <p>Pay an approved invoice via PayMongo — a checkout link is generated and the invoice is closed when paid.</p>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>Invoice #</th><th>PO</th><th>Amount</th><th>Payment Status</th><th>Checkout Link</th><th>Actions</th></tr></thead>
            <tbody id="paymentBody"></tbody>
        </table>
    </div>
</div>

<!-- RATINGS -->
<div class="proc-pane" id="pane-ratings" style="display:none;">
    <div class="inv-card">
        <div class="inv-card-header" style="display:flex;justify-content:space-between;align-items:center;">
            <div class="inv-card-title">Supplier Performance</div>
            <?php if ($canManageProcurement): ?><button class="inv-btn-primary" onclick="document.getElementById('ratingModal').style.display='flex'">+ Rate Supplier</button><?php endif; ?>
        </div>
        <table class="inv-table" style="width:100%;">
            <thead><tr><th>Supplier</th><th>OTIF</th><th>Quality</th><th>Responsiveness</th><th>Comments</th><th>Date</th></tr></thead>
            <tbody id="ratingBody"></tbody>
        </table>
    </div>
</div>

<!-- MODALS -->
<div id="procurementModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>New Requisition</h3>
        <form id="procurementForm">
            <label>Item name *</label><input name="item_name" required style="width:100%;">
            <label>Linked product</label><select name="product_id" id="procProduct" style="width:100%;"><option value="">-- None --</option></select>
            <div id="procStockHint" style="font-size:12px;color:#6b7280;margin-top:5px;">Choose a product to see stock and minimum stock level.</div>
            <label>Requested quantity *</label><input name="quantity" type="number" min="1" required style="width:100%;">
            <label>Estimated unit cost</label><input name="estimated_unit_cost" type="number" step="0.01" style="width:100%;">
            <label>Supplier</label><select name="supplier_id" id="procSupplier" style="width:100%;"><option value="">--</option></select>
            <label>Department</label><input name="department" style="width:100%;">
            <label>Cost centre</label><input name="cost_centre" style="width:100%;">
            <label>Notes</label><textarea name="notes" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Submit</button>
                <button type="button" onclick="document.getElementById('procurementModal').style.display='none'">Cancel</button>
            </div>

        </form>
    </div>
</div>

<div id="evaluationModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:560px;">
        <h3>Evaluate Product Source</h3>
        <form id="evaluationForm">
            <label>Product *</label><select name="product_id" id="evaluationProduct" required style="width:100%;"></select>
            <label>Supplier *</label><select name="supplier_id" id="evaluationSupplier" required style="width:100%;"></select>
            <label>Quoted unit price (₱) *</label><input name="unit_price" type="number" min="0" step="0.01" required style="width:100%;">
            <label>Product quality (1–5) *</label><select name="quality_score" required style="width:100%;"><option value="5">5 - Excellent</option><option value="4">4 - Good</option><option value="3">3 - Acceptable</option><option value="2">2 - Poor</option><option value="1">1 - Unacceptable</option></select>
            <label>Availability consistency (1–5) *</label><select name="availability_score" required style="width:100%;"><option value="5">5 - Consistent</option><option value="4">4 - Usually available</option><option value="3">3 - Variable</option><option value="2">2 - Often unavailable</option><option value="1">1 - Rarely available</option></select>
            <label>Delivery lead time (days) *</label><input name="lead_time_days" type="number" min="0" value="1" required style="width:100%;">
            <label>Minimum order quantity *</label><input name="minimum_order_quantity" type="number" min="1" value="1" required style="width:100%;">
            <label>Evaluation notes</label><textarea name="evaluation_notes" rows="3" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Save Evaluation</button>
                <button type="button" onclick="document.getElementById('evaluationModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="rfqModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>New RFQ</h3>
        <form id="rfqForm">
            <label>Title *</label><input name="title" required style="width:100%;">
            <label>Linked requisition</label><select name="request_id" id="rfqRequest" style="width:100%;"><option value="">--</option></select>
            <label>Item</label><input name="item_name" style="width:100%;">
            <label>Quantity</label><input name="quantity" type="number" value="1" style="width:100%;">
            <label>Estimated value</label><input name="estimated_value" type="number" step="0.01" style="width:100%;">
            <label>Supplier deadline</label><input name="deadline" type="date" style="width:100%;">
            <label>Notes</label><textarea name="notes" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Create</button>
                <button type="button" onclick="document.getElementById('rfqModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="quotationModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>Record Quotation</h3>
        <form id="quotationForm">
            <label>RFQ ID *</label><input name="rfq_id" id="quotationRfq" type="number" required style="width:100%;">
            <label>Supplier *</label><select name="supplier_id" id="quotationSupplier" style="width:100%;"></select>
            <label>Unit price</label><input name="unit_price" type="number" step="0.01" style="width:100%;">
            <label>Quantity</label><input name="quantity" type="number" value="1" style="width:100%;">
            <label>Lead time (days)</label><input name="lead_time_days" type="number" style="width:100%;">
            <label>Payment terms</label><input name="payment_terms" style="width:100%;">
            <label>Negotiation notes</label><textarea name="negotiation_notes" style="width:100%;"></textarea>
            <label>Notes</label><textarea name="notes" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Save</button>
                <button type="button" onclick="document.getElementById('quotationModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="poModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>New Purchase Order</h3>
        <form id="poForm">
            <label>Supplier *</label><select name="supplier_id" id="poSupplier" style="width:100%;"></select>
            <label>Items *</label>
            <div id="poItems" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;margin-top:4px;"></div>
            <button type="button" class="po-add-item-button" onclick="addPoItemRow()">+ Add item</button>
            <label>Expected delivery</label><input name="expected_delivery" type="date" style="width:100%;">
            <label>Payment terms</label><input name="payment_terms" value="Net 30" style="width:100%;">
            <label>Notes</label><textarea name="notes" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Create PO</button>
                <button type="button" onclick="document.getElementById('poModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="grnModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>Goods Receipt</h3>
        <form id="grnForm">
            <label>Purchase Order *</label><select name="po_id" id="grnPo" required style="width:100%;"><option value="">Select ordered PO</option></select>
            <label>Receiving date *</label><input name="received_date" type="date" value="<?php echo date('Y-m-d'); ?>" required style="width:100%;">
            <div id="grnItems" style="margin-top:10px;">Select a purchase order to enter received quantities.</div>
            <label>Discrepancies</label><textarea name="discrepancies" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Save GRN</button>
                <button type="button" onclick="document.getElementById('grnModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="supplierPayModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:480px;">
        <h3>Supplier Payment (Bank / Check)</h3>
        <form id="supplierPayForm">
            <input type="hidden" name="invoice_id" id="spInvoiceId">
            <label>Payment method</label>
            <select name="method" style="width:100%;">
                <option value="bank_transfer">Bank Transfer</option>
                <option value="check">Check</option>
                <option value="cash">Cash</option>
            </select>
            <label>Reference / Check #</label><input name="reference" style="width:100%;">
            <label>Notes</label><textarea name="notes" style="width:100%;"></textarea>
            <p style="font-size:12px;color:#6b7280;">Recording this creates a pending payment. Use <b>Confirm Paid</b> after the transfer/check clears.</p>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Record Payment</button>
                <button type="button" onclick="document.getElementById('supplierPayModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="invoiceModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>Record Supplier Invoice</h3>
        <form id="invoiceForm">
            <label>PO ID *</label><input name="po_id" type="number" required style="width:100%;">
            <label>Invoice # *</label><input name="invoice_number" required style="width:100%;">
            <label>Amount *</label><input name="amount" type="number" step="0.01" required style="width:100%;">
            <label>Invoice date</label><input name="invoice_date" type="date" style="width:100%;">
            <label>Notes</label><textarea name="notes" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Save Invoice</button>
                <button type="button" onclick="document.getElementById('invoiceModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div id="ratingModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>Rate Supplier</h3>
        <form id="ratingForm">
            <label>Supplier *</label><select name="supplier_id" id="ratingSupplier" style="width:100%;"></select>
            <label>PO ID</label><input name="po_id" type="number" style="width:100%;">
            <label>OTIF (1-5)</label><input name="otif_score" type="number" min="1" max="5" value="5" style="width:100%;">
            <label>Quality (1-5)</label><input name="quality_score" type="number" min="1" max="5" value="5" style="width:100%;">
            <label>Responsiveness (1-5)</label><input name="responsiveness_score" type="number" min="1" max="5" value="5" style="width:100%;">
            <label>Comments</label><textarea name="comments" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Save</button>
                <button type="button" onclick="document.getElementById('ratingModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-attach CSRF token to every FormData POST
var _FormData = FormData;
FormData = function(form) {
    var fd = form ? new _FormData(form) : new _FormData();
    fd.append('csrf_token', window.CSRF_TOKEN || '');
    return fd;
};

function styleBadges() {
    document.querySelectorAll('.badge').forEach(function(b){
        var s = (b.textContent || '').toLowerCase();
        b.style.padding = '4px 12px'; b.style.borderRadius = '20px';
        b.style.fontSize = '12px'; b.style.fontWeight = '700'; b.style.display = 'inline-block';
        if (/paid$|matched|approved|received|open|sent/.test(s)) { b.style.background = '#d1fae5'; b.style.color = '#065f46'; }
        else if (/unpaid|pending|held/.test(s)) { b.style.background = '#fef3c7'; b.style.color = '#92400e'; }
        else if (/rejected|mismatch|closed|cancelled|discrepancy|inactive/.test(s)) { b.style.background = '#fee2e2'; b.style.color = '#991b1b'; }
        else { b.style.background = '#dbeafe'; b.style.color = '#1e40af'; }
    });
}
var canManageProcurement = <?php echo $canManageProcurement ? 'true' : 'false'; ?>;
var isFinanceUser = <?php echo $isFinance ? 'true' : 'false'; ?>;
var currentRfqId = null;
var procurementStages = {
    requisitions: {
        title: 'Requisition & Budget',
        description: 'Identify the need, submit a request with its cost centre, and confirm budget availability before sourcing.',
        roles: ['Operations', 'Finance']
    },
    rfqs: {
        title: 'Sourcing / RFQ',
        description: 'Request supplier quotations and compare price, quality, availability, delivery time, and commercial terms.',
        roles: ['Operations', 'Suppliers']
    },
    analysis: {
        title: 'Supplier Selection',
        description: 'Evaluate the product and supplier together, review performance and demand, then select a suitable source.',
        roles: ['Operations', 'Finance', 'Suppliers']
    },
    orders: {
        title: 'Purchase Order',
        description: 'Create the formal order, obtain the required approval, and record supplier acknowledgement.',
        roles: ['Operations', 'Finance', 'Suppliers']
    },
    delivery: {
        title: 'Delivery & Receiving',
        description: 'Record delivered quantities, note shortages or discrepancies, and update stock only for goods received.',
        roles: ['Operations', 'Suppliers']
    },
    invoices: {
        title: 'Invoice Match',
        description: 'Match the supplier invoice against the purchase order and goods receipt before approval.',
        roles: ['Finance', 'Operations']
    },
    payments: {
        title: 'Payment',
        description: 'Record and confirm supplier payment after the invoice has been reviewed and approved.',
        roles: ['Finance', 'Suppliers']
    },
    ratings: {
        title: 'Close & Review',
        description: 'Close completed orders and record supplier delivery, quality, and responsiveness for future sourcing.',
        roles: ['Operations', 'Finance', 'Suppliers']
    }
};

function updateProcurementStageGuide(paneKey) {
    var stage = procurementStages[paneKey] || procurementStages.requisitions;
    var stageKeys = Object.keys(procurementStages);
    var stageNumber = stageKeys.indexOf(paneKey);
    document.getElementById('procurementStageIndex').textContent =
        String(stageNumber + 1).padStart(2, '0') + ' / ' + String(stageKeys.length).padStart(2, '0');
    document.getElementById('procurementStageTitle').textContent = stage.title;
    document.getElementById('procurementStageDescription').textContent = stage.description;
    var roles = document.getElementById('procurementStageRoles');
    roles.textContent = '';
    stage.roles.forEach(function(role, index){
        if (index > 0) {
            var separator = document.createElement('span');
            separator.textContent = role;
            roles.appendChild(separator);
        } else {
            roles.appendChild(document.createTextNode(role));
        }
    });
}

function normalizeProcPaneKey(targetKey) {
    var map = {
        budget: 'requisitions',
        rfq: 'rfqs',
        supplier: 'rfqs',
        analysis: 'analysis',
        orders: 'orders',
        delivery: 'delivery',
        invoices: 'invoices',
        payments: 'payments',
        'close-order': 'ratings',
        review: 'ratings'
    };
    return map[targetKey] || targetKey;
}

function activateProcPane(targetKey) {
    var paneKey = normalizeProcPaneKey(targetKey);
    if (!procurementStages[paneKey]) return;
    document.querySelectorAll('.proc-pane').forEach(function(p){
        p.style.display = (p.id === 'pane-' + paneKey) ? 'block' : 'none';
    });
    updateProcurementStageGuide(paneKey);
    document.querySelectorAll('.proc-stage-item').forEach(function(item){
        item.classList.toggle('active', item.dataset.target === targetKey);
    });
    if (window.history && window.history.replaceState) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', paneKey);
        window.history.replaceState({}, '', url.toString());
    }
}

document.querySelectorAll('.proc-stage-item').forEach(function(btn){
    btn.addEventListener('click', function(){
        activateProcPane(btn.dataset.target);
        reloadAll();
    });
});

function setStatusRow(id, status) {
    var fd = new FormData(); fd.append('id', id); fd.append('status', status);
    fetch('?action=update_procurement_status', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) loadProcurement(); else alert(d.message);
    });
}

function showNewProcurement() { document.getElementById('procurementModal').style.display = 'flex'; }

function loadProcurement() {
    fetch('?action=get_procurement').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        (rows || []).forEach(function(r){
            html += '<tr><td>' + r.req_number + '</td><td>' + r.item_name + '</td>' +
                '<td>' + (r.current_stock === null ? '—' : r.current_stock) + ' / ' + (r.minimum_stock === null ? '—' : r.minimum_stock) + '</td>' +
                '<td>' + r.quantity + '</td>' +
                '<td>' + parseFloat(r.estimated_unit_cost).toFixed(2) + '</td><td>' + (r.supplier_name || '-') + '</td>' +
                '<td>' + (r.department || '-') + ' / ' + (r.cost_centre || '-') + '</td>' +
                '<td><span class="badge">' + (r.budget_status || 'pending') + '</span></td>' +
                '<td><span class="badge">' + r.status + '</span></td><td>' + r.created_at + '</td>';
            if (canManageProcurement || isFinanceUser) {
                html += '<td>';
                if (isFinanceUser && r.budget_status !== 'approved' && r.budget_status !== 'rejected') {
                    html += '<button onclick="setBudget(' + r.id + ',\'approved\')">Budget OK</button> ';
                    html += '<button onclick="setBudget(' + r.id + ',\'rejected\')">Budget Reject</button> ';
                }
                if (canManageProcurement) {
                    if (r.status === 'pending') {
                        html += '<button onclick="setStatusRow(' + r.id + ',\'approved\')">Approve</button> ';
                        html += '<button onclick="setStatusRow(' + r.id + ',\'rejected\')">Reject</button> ';
                    }
                    if (r.status === 'approved') html += '<button onclick="setStatusRow(' + r.id + ',\'ordered\')">Mark Ordered</button> ';
                    if (r.status === 'ordered') html += '<button onclick="setStatusRow(' + r.id + ',\'received\')">Mark Received</button> ';
                }
                html += '</td>';
            }
            html += '</tr>';
        });
        if (!rows || !rows.length) html = '<tr><td colspan="11">No requests yet</td></tr>';
        document.getElementById('procurementBody').innerHTML = html; styleBadges();
        var sel = document.getElementById('rfqRequest');
        if (sel && sel.options.length <= 1) {
            rows.forEach(function(r){ var o = document.createElement('option'); o.value = r.id; o.textContent = r.req_number + ' - ' + r.item_name; sel.appendChild(o); });
        }
    });
}

function setBudget(id, s) {
    var fd = new FormData(); fd.append('id', id); fd.append('budget_status', s);
    fetch('?action=set_budget_status', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) loadProcurement(); else alert(d.message);
    });
}

function loadRFQs() {
    fetch('?action=get_rfqs').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        (rows || []).forEach(function(r){
            html += '<tr><td>' + r.rfq_number + '</td><td>' + r.title + '</td><td>' + r.quantity + '</td>' +
                '<td>' + parseFloat(r.estimated_value).toFixed(2) + '</td><td>' + (r.deadline || '-') + '</td>' +
                '<td><span class="badge">' + r.status + '</span></td>' +
                '<td><button onclick="viewQuotations(' + r.id + ')">Quotations</button> ' +
                '<button onclick="setRfqStatus(' + r.id + ',\'evaluating\')">Evaluating</button></td></tr>';
        });
        if (!rows || !rows.length) html = '<tr><td colspan="7">No RFQs yet</td></tr>';
        document.getElementById('rfqBody').innerHTML = html; styleBadges();
    });
}
function setRfqStatus(id, status) {
    var fd = new FormData(); fd.append('id', id); fd.append('status', status);
    fetch('?action=update_rfq_status', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(){ loadRFQs(); });
}
function viewQuotations(rfqId) {
    currentRfqId = rfqId;
    document.getElementById('quotationRfq').value = rfqId;
    fetch('?action=get_quotations&rfq_id=' + rfqId).then(function(r){return r.json();}).then(function(rows){
        var html = '';
        (rows || []).forEach(function(q){
            html += '<tr><td>' + q.supplier_name + ' <small>(' + (q.supplier_type || 'other_source').replace(/_/g, ' ') + ')</small></td><td>₱' + parseFloat(q.unit_price).toFixed(2) + '</td>' +
                '<td>₱' + parseFloat(q.total_price).toFixed(2) + '</td><td>' + (q.lead_time_days || '-') + '</td>' +
                '<td>' + (q.supplier_performance === null ? 'No rating history' : parseFloat(q.supplier_performance).toFixed(1) + ' / 5') + '</td>' +
                '<td>' + (q.payment_terms || '-') + '</td><td>' + (q.negotiation_notes || '-') + '</td>' +
                '<td>' + (q.is_selected == 1 ? 'Yes' : '') + '</td>' +
                '<td>' + (q.is_selected == 1 ? '' : '<button onclick="selectQuotation(' + q.id + ')">Select</button>') + '</td></tr>';
        });
        if (!rows || !rows.length) html = '<tr><td colspan="9">No quotations yet</td></tr>';
        document.getElementById('quotationBody').innerHTML = html; styleBadges();
    });
}
function selectQuotation(id) {
    var fd = new FormData(); fd.append('id', id);
    fetch('?action=select_quotation', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(){
        if (currentRfqId) viewQuotations(currentRfqId); loadRFQs();
    });
}

function loadPOs() {
    fetch('?action=get_pos').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        var grnSelect = document.getElementById('grnPo');
        while (grnSelect.options.length > 1) grnSelect.remove(1);
        (rows || []).forEach(function(p){
            html += '<tr data-status="' + escapeProcHtml(p.status) + '"><td>' + p.po_number + '</td><td>' + escapeProcHtml(p.supplier_name || '') + '</td>' +
                '<td>' + parseFloat(p.total_amount).toFixed(2) + '</td><td>' + (p.payment_terms || '-') + '</td>' +
                '<td><span class="badge">' + p.status + '</span></td><td>' + (p.supplier_ack == 1 ? 'Yes' : 'No') + '</td>' +
                '<td>' + p.created_at + '</td><td>';
            if (p.status === 'draft') html += '<button onclick="setPO(' + p.id + ',\'pending\')">Submit</button> ';
            if ((p.status === 'pending' || p.status === 'finance_pending') && isFinanceUser) html += '<button onclick="setPO(' + p.id + ',\'approved\')">Approve</button> ';
            if (p.status === 'approved') html += '<button onclick="setPO(' + p.id + ',\'ordered\')">Mark Ordered</button> ';
            if (p.status === 'ordered') html += '<button onclick="openReceivePO(' + p.id + ')">Receive Items</button> ';
            if (p.status === 'sent' || p.status === 'acknowledged' || p.status === 'partially_received') html += '<button onclick="openReceivePO(' + p.id + ')">Receive Items</button> ';
            if (p.status === 'delivered') html += '<button onclick="setPO(' + p.id + ',\'closed\')">Close</button> ';
            if (['draft','pending','finance_pending','approved','ordered'].indexOf(p.status) !== -1) html += '<button onclick="setPO(' + p.id + ',\'cancelled\')">Cancel</button> ';
            if (['ordered','sent','acknowledged','partially_received'].indexOf(p.status) !== -1) {
                var grnOption = document.createElement('option');
                grnOption.value = p.id;
                grnOption.textContent = p.po_number + ' — ' + p.supplier_name;
                document.getElementById('grnPo').appendChild(grnOption);
            }
            html += '</td></tr>';
        });
        if (!rows || !rows.length) html = '<tr><td colspan="8">No POs yet</td></tr>';
        document.getElementById('poBody').innerHTML = html; styleBadges();
        filterProcurementTable('poBody','poSearch','poStatusFilter','status');
    });
}
function setPO(id, status) {
    var fd = new FormData(); fd.append('id', id); fd.append('status', status);
    fetch('?action=update_po_status', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) loadPOs(); else alert(d.message);
    });
}

function openReceivePO(poId) {
    document.getElementById('grnPo').value = poId;
    loadReceivableItems(poId);
    document.getElementById('grnModal').style.display = 'flex';
}
function loadReceivableItems(poId) {
    var container = document.getElementById('grnItems');
    if (!poId) { container.textContent = 'Select a purchase order to enter received quantities.'; return; }
    fetch('?action=get_po_receivable_items&po_id=' + encodeURIComponent(poId)).then(function(r){return r.json();}).then(function(rows){
        if (!Array.isArray(rows)) { container.textContent = rows.message || 'Unable to load PO items.'; return; }
        var html = '<table class="inv-table"><thead><tr><th>Item</th><th>Ordered</th><th>Already Received</th><th>Remaining</th><th>Receive Now</th></tr></thead><tbody>';
        rows.forEach(function(item){
            html += '<tr><td>' + escapeProcHtml(item.item_name) + '</td><td>' + item.quantity + '</td><td>' + item.quantity_received + '</td><td>' + item.quantity_remaining + '</td>' +
                '<td><input class="grn-qty" data-item-id="' + item.id + '" type="number" min="0" max="' + item.quantity_remaining + '" value="0" style="width:90px;"></td></tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }).catch(function(){ container.textContent = 'Could not load PO items.'; });
}
document.getElementById('grnPo').addEventListener('change', function(){ loadReceivableItems(this.value); });

function loadGRNs() {
    fetch('?action=get_grns').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        (rows || []).forEach(function(g){
            html += '<tr><td>' + g.grn_number + '</td><td>' + g.po_number + '</td><td>' + (g.supplier_name || '-') + '</td>' +
                '<td>' + g.received_date + '</td><td><span class="badge">' + g.status + '</span></td><td>' + (g.discrepancies || '-') + '</td></tr>';
        });
        if (!rows || !rows.length) html = '<tr><td colspan="6">No receipts yet</td></tr>';
        document.getElementById('grnBody').innerHTML = html; styleBadges();
    });
}

function loadInvoices() {
    fetch('?action=get_invoices').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        (rows || []).forEach(function(i){
            html += '<tr><td>' + i.invoice_number + '</td><td>' + (i.po_number || '-') + '</td><td>' + (i.supplier_name || '-') + '</td>' +
                '<td>' + parseFloat(i.amount).toFixed(2) + '</td><td><span class="badge">' + i.match_status + '</span></td>' +
                '<td><span class="badge">' + i.payment_status + '</span></td>' +
                '<td>' + (i.match_status === 'matched' ? '<button onclick="approveInvoice(' + i.id + ')">Approve</button>' : '') + '</td></tr>';
        });
        if (!rows || !rows.length) html = '<tr><td colspan="7">No invoices yet</td></tr>';
        document.getElementById('invoiceBody').innerHTML = html; styleBadges();

        var html2 = '';
        (rows || []).forEach(function(i){
            html2 += '<tr><td>' + i.invoice_number + '</td><td>' + (i.po_number || '-') + '</td>' +
                '<td>' + parseFloat(i.amount).toFixed(2) + '</td><td><span class="badge">' + i.payment_status + '</span></td>' +
                '<td>' + (i.paymongo_checkout_url ? '<a href="' + i.paymongo_checkout_url + '" target="_blank">Open checkout</a>' : '-') + '</td>' +
                '<td>';
            if (i.payment_status !== 'paid') {
                html2 += '<button onclick="openSupplierPay(' + i.id + ')">Bank / Check</button> ';
                html2 += '<button onclick="payPaymongo(' + i.id + ')">Pay via PayMongo</button> ';
                html2 += '<button onclick="confirmPayment(' + i.id + ')">Confirm Paid</button>';
            }
            html2 += '</td></tr>';
        });
        if (!rows || !rows.length) html2 = '<tr><td colspan="6">No invoices yet</td></tr>';
        document.getElementById('paymentBody').innerHTML = html2; styleBadges();
    });
}
function approveInvoice(id) {
    var fd = new FormData(); fd.append('id', id);
    fetch('?action=approve_invoice', {method:'POST', body:fd}).then(function(){ loadInvoices(); });
}
function payPaymongo(id) {
    var fd = new FormData(); fd.append('id', id);
    fetch('?action=pay_invoice_paymongo', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) { alert(d.message || (d.checkout_url ? 'Checkout link created' : 'Done')); loadInvoices(); if (d.checkout_url) window.open(d.checkout_url, '_blank'); }
        else alert(d.message);
    });
}
function confirmPayment(id) {
    var fd = new FormData(); fd.append('invoice_id', id);
    fetch('?action=confirm_payment', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) loadInvoices(); else alert(d.message);
    });
}
function openSupplierPay(id) {
    document.getElementById('spInvoiceId').value = id;
    document.getElementById('supplierPayModal').style.display = 'flex';
}
function markPaid(id) {
    var fd = new FormData(); fd.append('id', id);
    fetch('?action=mark_invoice_paid', {method:'POST', body:fd}).then(function(){ loadInvoices(); });
}

function loadRatings() {
    fetch('?action=get_ratings').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        (rows || []).forEach(function(r){
            html += '<tr><td>' + r.supplier_name + '</td><td>' + r.otif_score + '</td><td>' + r.quality_score + '</td>' +
                '<td>' + r.responsiveness_score + '</td><td>' + (r.comments || '-') + '</td><td>' + r.created_at + '</td></tr>';
        });
        if (!rows || !rows.length) html = '<tr><td colspan="6">No ratings yet</td></tr>';
        document.getElementById('ratingBody').innerHTML = html; styleBadges();
    });
}

function escapeProcHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch];
    });
}
function filterProcurementTable(bodyId, searchId, statusId, statusAttribute) {
    var query = (document.getElementById(searchId).value || '').trim().toLowerCase();
    var status = document.getElementById(statusId).value;
    var body = document.getElementById(bodyId);
    var matchCount = 0;
    var hasStaticPlaceholder = false;
    body.querySelectorAll('tr').forEach(function(row){
        if (row.classList.contains('proc-filter-empty')) { row.remove(); return; }
        if (!row.hasAttribute('data-' + statusAttribute)) { row.style.display = ''; hasStaticPlaceholder = true; return; }
        var matchesSearch = !query || row.textContent.toLowerCase().indexOf(query) !== -1;
        var matchesStatus = !status || row.getAttribute('data-' + statusAttribute) === status;
        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
        if (matchesSearch && matchesStatus) matchCount++;
    });
    if (matchCount === 0 && !hasStaticPlaceholder) {
        var emptyRow = document.createElement('tr');
        emptyRow.className = 'proc-filter-empty';
        var cell = document.createElement('td');
        cell.colSpan = bodyId === 'analysisBody' ? (canManageProcurement ? 11 : 10) : 8;
        cell.textContent = 'No matching records.';
        emptyRow.appendChild(cell);
        body.appendChild(emptyRow);
    }
}
function loadProcurementAnalysis() {
    fetch('?action=get_procurement_analysis').then(function(r){return r.json();}).then(function(rows){
        if (!Array.isArray(rows)) throw new Error(rows.message || 'Could not load procurement analysis.');
        var html = '';
        window.procEvaluationRows = Array.isArray(rows) ? rows : [];
        window.procEvaluationRows.forEach(function(e){
            var names = escapeProcHtml(e.product_name) + '<br><small>' + escapeProcHtml(e.supplier_name) + '</small>';
            var label = (e.recommendation || 'needs_review').replace(/_/g, ' ');
            html += '<tr data-recommendation="' + escapeProcHtml(e.recommendation || 'needs_review') + '"><td>' + names + '</td><td>' + escapeProcHtml((e.supplier_type || '').replace(/_/g, ' ')) + '</td>' +
                '<td>' + e.stock_quantity + ' / ' + (e.minimum_stock || 0) + '</td><td>' + e.units_sold_90d + '</td>' +
                '<td>₱' + parseFloat(e.unit_price).toFixed(2) + '</td><td>' + e.quality_score + ' / 5 · ' + e.availability_score + ' / 5</td>' +
                '<td>' + e.lead_time_days + 'd / ' + e.minimum_order_quantity + '</td>' +
                '<td>' + (e.avg_performance === null ? 'No ratings' : parseFloat(e.avg_performance).toFixed(1) + ' / 5') + '</td>' +
                '<td><span class="badge">' + escapeProcHtml(label) + '</span></td><td>' + escapeProcHtml(e.recommendation_reason) + '</td>' +
                (canManageProcurement ? '<td><button class="proc-eval-po" data-evaluation-id="' + e.id + '">Create PO</button></td>' : '') + '</tr>';
        });
        if (!html) html = '<tr><td colspan="' + (canManageProcurement ? '11' : '10') + '">No evaluations yet. Add a product evaluation to compare source quality, price, availability, and demand.</td></tr>';
        document.getElementById('analysisBody').innerHTML = html;
        filterProcurementTable('analysisBody','analysisSearch','analysisRecommendation','recommendation');
        document.querySelectorAll('.proc-eval-po').forEach(function(button){
            button.addEventListener('click', function(){
                var evaluation = window.procEvaluationRows.find(function(row){ return String(row.id) === button.getAttribute('data-evaluation-id'); });
                if (evaluation) createPOFromEvaluation(evaluation);
            });
        });
        styleBadges();
    }).catch(function(error){ document.getElementById('analysisBody').innerHTML = '<tr><td colspan="10">' + escapeProcHtml(error.message || 'Unable to load procurement analysis.') + '</td></tr>'; });
}
function createPOFromEvaluation(evaluation) {
    Promise.all([procurementSuppliersReady, procurementProductsReady]).then(function(){
        activateProcPane('orders');
        var supplier = document.getElementById('poSupplier');
        supplier.value = evaluation.supplier_id;
        document.getElementById('poItems').innerHTML = '';
        addPoItemRow();
        var row = document.querySelector('.po-item-row');
        row.querySelector('.po-item-name').value = evaluation.product_name;
        row.querySelector('.po-item-qty').value = Math.max(1, parseInt(evaluation.minimum_order_quantity || 1, 10));
        row.querySelector('.po-item-cost').value = parseFloat(evaluation.unit_price || 0).toFixed(2);
        row.querySelector('.po-item-product').value = evaluation.product_id;
        document.getElementById('poModal').style.display = 'flex';
    }).catch(function(){ alert('Could not load suppliers and products for this purchase order.'); });
}
function loadProcurementHistory() {
    fetch('?action=get_procurement_history').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        (Array.isArray(rows) ? rows : []).forEach(function(row){
            html += '<tr><td>' + escapeProcHtml(row.product_name) + '</td><td>' + escapeProcHtml(row.supplier_name || '—') + '</td>' +
                '<td>' + row.order_count + '</td><td>' + row.units_ordered + '</td><td>' + (row.last_purchase || '—') + '</td>' +
                '<td>₱' + parseFloat(row.average_unit_cost || 0).toFixed(2) + '</td></tr>';
        });
        document.getElementById('historyBody').innerHTML = html || '<tr><td colspan="6">No purchase order history yet.</td></tr>';
    }).catch(function(){ document.getElementById('historyBody').innerHTML = '<tr><td colspan="6">Unable to load purchasing history.</td></tr>'; });
}

function reloadAll() { loadProcurement(); loadRFQs(); loadPOs(); loadGRNs(); loadInvoices(); loadRatings(); loadProcurementAnalysis(); loadProcurementHistory(); }

function bindForm(formId, action, modalId, after) {
    document.getElementById(formId).addEventListener('submit', function(e){
        e.preventDefault();
        var fd = new FormData(this);
        fetch('?action=' + action, {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
            if (d.success) {
                document.getElementById(modalId).style.display = 'none';
                document.getElementById(formId).reset();
                if (after) after(); else reloadAll();
            } else alert(d.message);
        });
    });
}
bindForm('procurementForm', 'save_procurement', 'procurementModal');
bindForm('rfqForm', 'save_rfq', 'rfqModal', loadRFQs);
bindForm('quotationForm', 'save_quotation', 'quotationModal', function(){ if (currentRfqId) viewQuotations(currentRfqId); });
bindForm('grnForm', 'save_grn', 'grnModal', loadGRNs);
bindForm('invoiceForm', 'save_invoice', 'invoiceModal', loadInvoices);
bindForm('supplierPayForm', 'save_supplier_payment', 'supplierPayModal', loadInvoices);
bindForm('ratingForm', 'rate_supplier', 'ratingModal', loadRatings);
bindForm('evaluationForm', 'save_product_evaluation', 'evaluationModal', function(){ loadProcurementAnalysis(); loadProcurementHistory(); });

var procurementSuppliersReady = fetch('?action=get_suppliers').then(function(r){return r.json();}).then(function(rows){
    ['procSupplier','quotationSupplier','poSupplier','ratingSupplier','evaluationSupplier'].forEach(function(id){
        var sel = document.getElementById(id);
        if (!sel) return;
        if (id === 'procSupplier') { var o0 = document.createElement('option'); o0.value=''; o0.textContent='--'; sel.appendChild(o0); }
        (rows || []).forEach(function(s){ var o = document.createElement('option'); o.value = s.id; o.textContent = s.name + ' (' + (s.supplier_type || 'other_source').replace(/_/g, ' ') + ')'; sel.appendChild(o); });
    });
    return rows || [];
});
var procurementProductsReady = fetch('?action=get_products').then(function(r){return r.json();}).then(function(rows){
    window.procProducts = rows || [];
    ['procProduct','evaluationProduct'].forEach(function(id){
        var sel = document.getElementById(id); if (!sel) return;
        (rows || []).forEach(function(p){ var o = document.createElement('option'); o.value = p.id; o.textContent = p.name + ' (stock: ' + (p.stock_quantity ?? p.stock ?? 0) + ')'; sel.appendChild(o); });
    });
    addPoItemRow();
    return rows || [];
});
document.getElementById('procProduct').addEventListener('change', function(){
    var product = (window.procProducts || []).find(function(p){ return String(p.id) === String(this.value); }, this);
    var hint = document.getElementById('procStockHint');
    if (!product) { hint.textContent = 'Choose a product to see stock and minimum stock level.'; return; }
    var stock = parseInt(product.stock_quantity || product.stock || 0, 10);
    var minimum = parseInt(product.low_stock_threshold || 0, 10);
    hint.textContent = 'Current stock: ' + stock + ' · Minimum stock level: ' + minimum + ' · Suggested reorder: ' + Math.max(0, minimum - stock);
    var qty = document.querySelector('#procurementForm [name="quantity"]');
    if (qty && minimum > stock) qty.value = minimum - stock;
    document.querySelector('#procurementForm [name="item_name"]').value = product.name;
});
document.getElementById('grnForm').addEventListener('submit', function(e){
    e.preventDefault();
    var items = [];
    document.querySelectorAll('.grn-qty').forEach(function(input){
        var quantity = parseInt(input.value || '0', 10);
        if (quantity > 0) items.push({po_item_id: parseInt(input.getAttribute('data-item-id'), 10), quantity: quantity});
    });
    if (!items.length) { alert('Enter at least one received quantity.'); return; }
    var fd = new FormData(this);
    fd.append('received_items', JSON.stringify(items));
    fetch('?action=save_grn', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) {
            document.getElementById('grnModal').style.display = 'none';
            document.getElementById('grnForm').reset();
            document.getElementById('grnItems').textContent = 'Select a purchase order to enter received quantities.';
            reloadAll();
        } else alert(d.message);
    }).catch(function(){ alert('Could not save goods receipt. Please try again.'); });
});

function addPoItemRow() {
    var row = document.createElement('div');
    row.className = 'po-item-row';
    row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:6px;margin-bottom:6px;align-items:center;';
    var opts = '<option value="">-- None --</option>';
    (window.procProducts || []).forEach(function(p){ opts += '<option value="' + p.id + '">' + p.name + '</option>'; });
    row.innerHTML = '<input class="po-item-name" placeholder="Item name *" required>' +
        '<input class="po-item-qty" type="number" min="1" value="1" placeholder="Qty">' +
        '<input class="po-item-cost" type="number" step="0.01" placeholder="Unit cost">' +
        '<select class="po-item-product">' + opts + '</select>' +
        '<button type="button" style="border:none;background:#fee2e2;color:#991b1b;border-radius:8px;padding:6px 10px;cursor:pointer;" onclick="this.parentNode.remove()">✕</button>';
    document.getElementById('poItems').appendChild(row);
}

document.getElementById('poForm').addEventListener('submit', function(e){
    e.preventDefault();
    var items = [];
    document.querySelectorAll('.po-item-row').forEach(function(row){
        var name = row.querySelector('.po-item-name').value.trim();
        if (name) items.push({
            item_name: name,
            quantity: parseInt(row.querySelector('.po-item-qty').value || 1),
            unit_cost: parseFloat(row.querySelector('.po-item-cost').value || 0),
            product_id: row.querySelector('.po-item-product').value || null
        });
    });
    if (!items.length) { alert('Add at least one item'); return; }
    var fd = new FormData(this);
    fd.append('items', JSON.stringify(items));
    fetch('?action=create_po', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) { document.getElementById('poModal').style.display = 'none'; document.getElementById('poForm').reset(); document.getElementById('poItems').innerHTML = ''; addPoItemRow(); loadPOs(); }
        else alert(d.message);
    });
});

var requestedProcurementTab = new URLSearchParams(window.location.search).get('tab') || 'requisitions';
if (document.getElementById('pane-' + requestedProcurementTab)) {
    activateProcPane(requestedProcurementTab);
}
reloadAll();
</script>
