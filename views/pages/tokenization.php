<?php
if (!canAccess('tokenization')) {
    echo '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;">
        <h2 style="color:#111827;">Access Denied</h2>
        <a href="?page=dashboard" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Go to Dashboard</a>
    </div>';
    return;
}
?>

<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title">Payment Tokenization</h2>
        <p class="inv-page-subtitle">Save card payments safely — only a token and the last 4 digits are stored, never the full card number</p>
    </div>
    <button class="inv-btn-primary" onclick="document.getElementById('tokenModal').style.display='flex'">+ Tokenize Card</button>
</div>

<div class="inv-card">
    <div class="inv-card-header"><div class="inv-card-title">Saved Payment Tokens</div></div>
    <table class="inv-table" style="width:100%;">
        <thead><tr><th>Brand</th><th>Card</th><th>Expiry</th><th>Cardholder</th><th>Customer</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="tokenBody"><tr><td colspan="7">Loading...</td></tr></tbody>
    </table>
</div>

<div id="tokenModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:520px;">
        <h3>Tokenize a Card</h3>
        <form id="tokenForm">
            <label>Cardholder name</label><input name="cardholder_name" style="width:100%;">
            <label>Card number *</label><input name="card_number" placeholder="4242 4242 4242 4242" required style="width:100%;">
            <label>Expiry month *</label><input name="expiry_month" type="number" min="1" max="12" required style="width:100%;">
            <label>Expiry year *</label><input name="expiry_year" type="number" min="2026" max="2100" required style="width:100%;">
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Create Token</button>
                <button type="button" onclick="document.getElementById('tokenModal').style.display='none'">Cancel</button>
            </div>
            <p style="font-size:0.8rem;color:#6b7280;margin-top:0.5rem;">The full number is immediately replaced by a random token. Only brand, last 4 digits, and expiry are kept.</p>
        </form>
    </div>
</div>

<script>
function loadTokens() {
    fetch('?action=get_payment_tokens').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        rows.forEach(function(t){
            html += '<tr>' +
                '<td>' + t.card_brand + '</td>' +
                '<td>**** **** **** ' + t.last4 + '</td>' +
                '<td>' + (t.expiry_month || '-') + '/' + (t.expiry_year || '-') + '</td>' +
                '<td>' + (t.cardholder_name || '-') + '</td>' +
                '<td>' + (t.customer_name || '-') + '</td>' +
                '<td>' + (t.active == 1 ? 'active' : 'inactive') + '</td>' +
                '<td>' + (t.active == 1 ? '<button onclick="deleteToken(' + t.id + ')">Deactivate</button>' : '') + '</td>' +
                '</tr>';
        });
        if (!rows.length) html = '<tr><td colspan="7">No tokens yet</td></tr>';
        document.getElementById('tokenBody').innerHTML = html;
    });
}
function deleteToken(id) {
    if (!confirm('Deactivate this saved card token?')) return;
    fetch('?action=delete_payment_token&id=' + id).then(function(r){return r.json();}).then(function(d){
        if (d.success) loadTokens(); else alert(d.message);
    });
}
document.getElementById('tokenForm').addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(this);
    fetch('?action=tokenize_card', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) {
            document.getElementById('tokenModal').style.display = 'none';
            document.getElementById('tokenForm').reset();
            loadTokens();
            alert('Card tokenized! Saved as ' + d.brand + ' ****' + d.last4);
        } else alert(d.message);
    });
});
loadTokens();
</script>
