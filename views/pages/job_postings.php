<?php
if (!canAccess('hr') && !isAdmin()) {
    echo '<div class="inv-card" style="margin:2rem;text-align:center;padding:40px;">
        <h2 style="color:#111827;">Access Denied</h2>
        <a href="?page=dashboard" class="inv-btn-primary" style="margin-top:1rem;text-decoration:none;">Go to Dashboard</a>
    </div>';
    return;
}
$canPostJobs = hasRole('hr') || isAdmin();
?>

<style>
.inv-card { border-radius:14px !important; box-shadow:0 1px 3px rgba(0,0,0,.06) !important; border:1px solid #eef0f3 !important; }
.inv-table th { background:#f9fafb !important; color:#6b7280 !important; text-transform:uppercase; font-size:12px !important; letter-spacing:.5px; }
.inv-table tbody tr:hover { background:#f9fafb; }
.inv-table td button { border:none; background:#e0ecff; color:#1d4ed8; font-weight:600; font-size:12px; padding:6px 12px; border-radius:20px; cursor:pointer; }
.inv-table td button:hover { background:#2563eb; color:#fff; }
.inv-modal-content { border-radius:16px !important; }
.inv-modal-content h3 { margin-top:0; }
.inv-modal-content label { display:block; margin-top:10px; font-size:13px; font-weight:600; color:#374151; }
.inv-modal-content input, .inv-modal-content select, .inv-modal-content textarea { margin-top:4px; padding:9px 12px; border:1.5px solid #e5e7eb; border-radius:10px; width:100%; }
</style>

<div class="inv-page-header">
    <div class="inv-page-header-left">
        <h2 class="inv-page-title">Job Postings</h2>
        <p class="inv-page-subtitle">Create and manage openings — candidates can apply once published</p>
    </div>
    <?php if ($canPostJobs): ?>
    <button class="inv-btn-primary" onclick="document.getElementById('jobModal').style.display='flex'">+ Post a Job</button>
    <?php endif; ?>
</div>

<div class="inv-card">
    <div class="inv-card-header"><div class="inv-card-title">All Postings</div></div>
    <table class="inv-table" style="width:100%;">
        <thead><tr><th>Title</th><th>Department</th><th>Location</th><th>Type</th><th>Salary Range</th><th>Status</th><th>Posted</th><?php if ($canPostJobs) echo '<th>Actions</th>'; ?></tr></thead>
        <tbody id="jobBody"><tr><td colspan="8">Loading...</td></tr></tbody>
    </table>
</div>

<div id="jobModal" class="inv-modal" style="display:none;">
    <div class="inv-modal-content" style="max-width:560px;">
        <h3>Post a Job</h3>
        <form id="jobForm">
            <label>Title *</label><input name="title" required style="width:100%;">
            <label>Department</label><input name="department" style="width:100%;">
            <label>Location</label><input name="location" style="width:100%;">
            <label>Type</label>
            <select name="type" style="width:100%;">
                <option value="full_time">Full-time</option>
                <option value="part_time">Part-time</option>
                <option value="contract">Contract</option>
                <option value="internship">Internship</option>
            </select>
            <label>Salary min</label><input name="salary_min" type="number" step="0.01" style="width:100%;">
            <label>Salary max</label><input name="salary_max" type="number" step="0.01" style="width:100%;">
            <label>Description</label><textarea name="description" style="width:100%;"></textarea>
            <label>Requirements</label><textarea name="requirements" style="width:100%;"></textarea>
            <div style="margin-top:1rem;">
                <button type="submit" class="inv-btn-primary">Publish</button>
                <button type="button" onclick="document.getElementById('jobModal').style.display='none'">Cancel</button>
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
        if (/open/.test(s)) { b.style.background = '#d1fae5'; b.style.color = '#065f46'; }
        else { b.style.background = '#fee2e2'; b.style.color = '#991b1b'; }
    });
}

var canPostJobs = <?php echo $canPostJobs ? 'true' : 'false'; ?>;

function loadJobs() {
    fetch('?action=get_job_postings').then(function(r){return r.json();}).then(function(rows){
        var html = '';
        (rows || []).forEach(function(j){
            var salary = (j.salary_min || j.salary_max) ? ('₱' + parseFloat(j.salary_min || 0).toFixed(0) + ' – ₱' + parseFloat(j.salary_max || 0).toFixed(0)) : '-';
            html += '<tr><td>' + j.title + '</td><td>' + (j.department || '-') + '</td><td>' + (j.location || '-') + '</td>' +
                '<td>' + j.type.replace('_', ' ') + '</td><td>' + salary + '</td>' +
                '<td><span class="badge">' + j.status + '</span></td><td>' + j.created_at + '</td>';
            if (canPostJobs) {
                html += '<td>' + (j.status === 'open'
                    ? '<button onclick="setJobStatus(' + j.id + ',\'closed\')">Close</button>'
                    : '<button onclick="setJobStatus(' + j.id + ',\'open\')">Reopen</button>') + '</td>';
            }
            html += '</tr>';
        });
        if (!rows || !rows.length) html = '<tr><td colspan="8">No job postings yet</td></tr>';
        document.getElementById('jobBody').innerHTML = html; styleBadges();
    });
}

function setJobStatus(id, status) {
    var fd = new FormData(); fd.append('id', id); fd.append('status', status);
    fetch('?action=update_job_posting_status', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) loadJobs(); else alert(d.message);
    });
}

document.getElementById('jobForm').addEventListener('submit', function(e){
    e.preventDefault();
    var fd = new FormData(this);
    fetch('?action=save_job_posting', {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(d){
        if (d.success) { document.getElementById('jobModal').style.display = 'none'; this.reset(); loadJobs(); }
        else alert(d.message);
    }.bind(this));
});
loadJobs();
</script>
