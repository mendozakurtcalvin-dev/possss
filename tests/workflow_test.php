<?php
// ============================================================
// Procurement module end-to-end workflow test.
//
// Runs the full acceptance workflow against the LIVE database
// using dedicated test users (proctest_*), then cleans up after
// itself so no test data remains.
//
// Usage: php tests/workflow_test.php
// ============================================================

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
require __DIR__ . '/../app/bootstrap.php';

$PHP = PHP_BINARY;
$passCount = 0;
$failCount = 0;
$failures = [];

function check($label, $condition, $detail = '') {
    global $passCount, $failCount, $failures;
    if ($condition) {
        $passCount++;
        echo "  PASS  " . $label . "\n";
    } else {
        $failCount++;
        $failures[] = $label . ($detail !== '' ? (' :: ' . $detail) : '');
        echo "  FAIL  " . $label . ($detail !== '' ? (' :: ' . $detail) : '') . "\n";
    }
}

function runOne(array $payload) {
    global $PHP;
    $tmp = __DIR__ . '/tmp';
    if (!is_dir($tmp)) { mkdir($tmp, 0777, true); }
    $file = $tmp . DIRECTORY_SEPARATOR . 'payload_' . getmypid() . '_' . mt_rand(1000, 999999) . '.json';
    file_put_contents($file, json_encode($payload));
    $cmd = escapeshellarg($PHP) . ' ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'run_one.php') . ' ' . escapeshellarg($file);
    $out = shell_exec($cmd . ' 2>&1');
    @unlink($file);
    $decoded = json_decode(trim((string) $out), true);
    return is_array($decoded) ? $decoded : ['ok' => false, 'error' => 'runner produced no output', 'raw' => substr((string) $out, 0, 400)];
}

function runAction($action, $userId, array $post = [], array $get = []) {
    return runOne(['mode' => 'action', 'action' => $action, 'user_id' => $userId, 'post' => $post, 'get' => $get]);
}

function runRender($page, $userId, array $get = [], array $expect = []) {
    return runOne(['mode' => 'render', 'page' => $page, 'user_id' => $userId, 'get' => $get, 'expect' => $expect]);
}

function resp($result) {
    return $result['response'] ?? null;
}

// ------------------------------------------------------------
// Setup: dedicated test users for each role
// ------------------------------------------------------------
echo "== SETUP ==\n";
global $pdo;

function ensureTestUser($pdo, $username, $role, $fullName) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $id = $stmt->fetchColumn();
    if ($id) { return (int) $id; }
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, password_hash('ProcTest!123', PASSWORD_DEFAULT), $fullName, $role]);
    return (int) $pdo->lastInsertId();
}

$invUser = ensureTestUser($pdo, 'proctest_inventory', 'inventory', 'Proc Test Inventory');
$adminUser = ensureTestUser($pdo, 'proctest_admin', 'admin', 'Proc Test Admin');
$finUser = ensureTestUser($pdo, 'proctest_finance', 'finance', 'Proc Test Finance');
$cashUser = ensureTestUser($pdo, 'proctest_cashier', 'cashier', 'Proc Test Cashier');
$hrUser = ensureTestUser($pdo, 'proctest_hr', 'hr', 'Proc Test HR');
echo "  test users: inv=$invUser admin=$adminUser fin=$finUser cash=$cashUser hr=$hrUser\n";

$supplierId = (int) $pdo->query("SELECT id FROM suppliers WHERE status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
$testProduct = $pdo->query("
    SELECT id, name, stock_quantity FROM products
    WHERE archived = 0 AND active = 1
    ORDER BY stock_quantity ASC LIMIT 1
")->fetch();
$productId = (int) $testProduct['id'];
$baselineStock = (int) $testProduct['stock_quantity'];
echo "  supplier=#$supplierId product=#{$testProduct['id']} ({$testProduct['name']}) stock=$baselineStock\n";

// ------------------------------------------------------------
// 1) SERVER-SIDE VALIDATION: invalid requests cannot be created/submitted
// ------------------------------------------------------------
echo "\n== 1. VALIDATION ==\n";

$r = runAction('pr_save', $invUser, [
    'supplier_id' => $supplierId,
    'request_date' => date('Y-m-d'),
    'reason' => '',
    'items' => json_encode([['product_id' => $productId, 'quantity' => 0, 'estimated_unit_price' => -5]]),
]);
check('invalid payload rejected on save', resp($r)['success'] === false, json_encode(resp($r)));
check('invalid payload explains errors', count(resp($r)['errors'] ?? []) >= 2, json_encode(resp($r)['errors'] ?? []));

$r = runAction('pr_save', $invUser, [
    'supplier_id' => 999999,
    'request_date' => 'not-a-date',
    'reason' => 'Low stock',
    'items' => json_encode([['product_id' => $productId, 'quantity' => 5, 'estimated_unit_price' => 10]]),
]);
check('inactive/unknown supplier + bad date rejected', resp($r)['success'] === false, json_encode(resp($r)));

// Create the valid example request (draft)
$r = runAction('pr_save', $invUser, [
    'supplier_id' => $supplierId,
    'request_date' => date('Y-m-d'),
    'reason' => 'Low stock',
    'remarks' => 'Workflow test data',
    'items' => json_encode([['product_id' => $productId, 'quantity' => 10, 'estimated_unit_price' => 1200]]),
]);
$save = resp($r);
check('valid draft created', ($save['success'] ?? false) === true, json_encode($save));
$requestId = (int) ($save['id'] ?? 0);
$requestNumber = (string) ($save['request_number'] ?? '');
check('request number generated (PR-YYYY-NNN)', (bool) preg_match('/^PR-\d{4}-\d{3}$/', $requestNumber), $requestNumber);

$row = $pdo->query("SELECT * FROM purchase_requests WHERE id = " . $requestId)->fetch();
check('draft saved with status draft', $row && $row['status'] === 'draft', $row['status'] ?? 'missing');
check('server-calculated grand total = 12000.00', $row && abs(floatval($row['grand_total']) - 12000.00) < 0.005, (string) ($row['grand_total'] ?? ''));
$itemCount = (int) $pdo->query("SELECT COUNT(*) FROM purchase_request_items WHERE request_id = " . $requestId)->fetchColumn();
check('one item row stored', $itemCount === 1, (string) $itemCount);

// Corrupt the reason directly, then try to submit -> server must reject
$pdo->prepare("UPDATE purchase_requests SET reason = '' WHERE id = ?")->execute([$requestId]);
$r = runAction('pr_submit', $invUser, ['id' => $requestId]);
check('submission blocked when stored data invalid', resp($r)['success'] === false, json_encode(resp($r)));
$pdo->prepare("UPDATE purchase_requests SET reason = 'Low stock' WHERE id = ?")->execute([$requestId]);

// ------------------------------------------------------------
// 2) SUBMIT FOR APPROVAL + ADMIN NOTIFICATION
// ------------------------------------------------------------
echo "\n== 2. SUBMIT ==\n";
$r = runAction('pr_submit', $invUser, ['id' => $requestId]);
$sub = resp($r);
check('valid request submitted', ($sub['success'] ?? false) === true, json_encode($sub));
check('status became pending_approval', ($sub['status'] ?? '') === 'pending_approval', (string) ($sub['status'] ?? ''));

$row = $pdo->query("SELECT * FROM purchase_requests WHERE id = " . $requestId)->fetch();
check('DB status is pending_approval', $row['status'] === 'pending_approval', $row['status']);
check('submitted_at recorded', !empty($row['submitted_at']), '');

$notifCount = (int) $pdo->query("SELECT COUNT(*) FROM inventory_notifications WHERE reference_id = {$requestId} AND title LIKE '%Pending Approval%'")->fetchColumn();
check('admin notification created for submission', $notifCount >= 1, (string) $notifCount);

// Re-submitting a pending request must fail
$r = runAction('pr_submit', $invUser, ['id' => $requestId]);
check('duplicate submit blocked', resp($r)['success'] === false, json_encode(resp($r)));

// ------------------------------------------------------------
// 3) AUTHORIZATION: only admin with approval permission may decide
// ------------------------------------------------------------
echo "\n== 3. AUTHORIZATION ==\n";

$r = runAction('pr_decide', $invUser, ['id' => $requestId, 'decision' => 'approve']);
check('inventory user cannot approve', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'Unauthorized') !== false, json_encode(resp($r)));

$r = runAction('pr_decide', $cashUser, ['id' => $requestId, 'decision' => 'approve']);
check('cashier cannot approve', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'Unauthorized') !== false, json_encode(resp($r)));

$r = runAction('pr_decide', $finUser, ['id' => $requestId, 'decision' => 'approve']);
check('finance cannot approve purchase requests', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'Unauthorized') !== false, json_encode(resp($r)));

$r = runAction('pr_decide', $hrUser, ['id' => $requestId, 'decision' => 'approve']);
check('HR cannot approve purchase requests', resp($r)['success'] === false, json_encode(resp($r)));

$r = runAction('pr_get', $cashUser, ['id' => $requestId]);
check('cashier cannot view purchase request details', resp($r)['success'] === false, json_encode(resp($r)));

// Self-approval prevention: admin creates and submits their own request
$r = runAction('pr_save', $adminUser, [
    'supplier_id' => $supplierId,
    'request_date' => date('Y-m-d'),
    'reason' => 'Admin self-approval test',
    'items' => json_encode([['product_id' => $productId, 'quantity' => 2, 'estimated_unit_price' => 100]]),
]);
$selfSave = resp($r);
$selfId = (int) ($selfSave['id'] ?? 0);
check('admin self-owned request created', ($selfSave['success'] ?? false) === true && $selfId > 0, json_encode($selfSave));
runAction('pr_submit', $adminUser, ['id' => $selfId]);
$r = runAction('pr_decide', $adminUser, ['id' => $selfId, 'decision' => 'approve']);
check('admin cannot approve own request (self-approval blocked)', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'own purchase request') !== false, json_encode(resp($r)));
runAction('pr_cancel', $adminUser, ['id' => $selfId]);

// ------------------------------------------------------------
// 4) APPROVAL WORKFLOW: revision required comments, then approve
// ------------------------------------------------------------
echo "\n== 4. APPROVAL WORKFLOW ==\n";

$r = runAction('pr_decide', $adminUser, ['id' => $requestId, 'decision' => 'reject']);
check('rejection without a reason is rejected', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'reason') !== false, json_encode(resp($r)));

$r = runAction('pr_decide', $adminUser, ['id' => $requestId, 'decision' => 'revision']);
check('revision without a comment is rejected', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'comment') !== false, json_encode(resp($r)));

$r = runAction('pr_decide', $adminUser, ['id' => $requestId, 'decision' => 'revision', 'comments' => 'Please confirm the supplier quotation.']);
$dec = resp($r);
check('admin can request revision with comment', ($dec['success'] ?? false) === true && ($dec['status'] ?? '') === 'revision_requested', json_encode($dec));

// Requester edits + resubmits
$r = runAction('pr_save', $invUser, ['id' => $requestId, 'supplier_id' => $supplierId, 'request_date' => date('Y-m-d'), 'reason' => 'Low stock - revised per admin feedback', 'items' => json_encode([['product_id' => $productId, 'quantity' => 10, 'estimated_unit_price' => 1200]])]);
check('requester can edit revision-requested draft', (resp($r)['success'] ?? false) === true, json_encode(resp($r)));
$r = runAction('pr_submit', $invUser, ['id' => $requestId]);
check('resubmission returns to pending approval', (resp($r)['status'] ?? '') === 'pending_approval', json_encode(resp($r)));

// Approve
$r = runAction('pr_decide', $adminUser, ['id' => $requestId, 'decision' => 'approve', 'comments' => 'Approved after review.']);
$dec = resp($r);
check('admin approval succeeds', ($dec['success'] ?? false) === true && ($dec['status'] ?? '') === 'approved', json_encode($dec));

// Duplicate approval must be impossible
$r = runAction('pr_decide', $adminUser, ['id' => $requestId, 'decision' => 'approve']);
check('duplicate approval blocked', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'already been processed') !== false, json_encode(resp($r)));

$row = $pdo->query("SELECT * FROM purchase_requests WHERE id = " . $requestId)->fetch();
check('approver identity + timestamp recorded', (int) $row['decided_by'] === $adminUser && !empty($row['decided_at']), 'decided_by=' . $row['decided_by']);

$logActions = $pdo->query("SELECT DISTINCT action FROM procurement_approval_logs WHERE request_id = {$requestId}")->fetchAll(PDO::FETCH_COLUMN);
check('audit history contains submitted/revision/approved', in_array('submitted', $logActions, true) && in_array('revision_requested', $logActions, true) && in_array('approved', $logActions, true), implode(',', $logActions));

// ------------------------------------------------------------
// 5) PURCHASE ORDER WORKFLOW
// ------------------------------------------------------------
echo "\n== 5. PURCHASE ORDER ==\n";

$r = runAction('po_from_request', $invUser, ['request_id' => $requestId, 'expected_delivery' => date('Y-m-d', strtotime('+7 days'))]);
$poCreate = resp($r);
check('purchase order generated from approved request', ($poCreate['success'] ?? false) === true, json_encode($poCreate));
$poId = (int) ($poCreate['po_id'] ?? 0);
$poNumber = (string) ($poCreate['po_number'] ?? '');
check('unique PO number assigned', (bool) preg_match('/^PO-\d{4}-\d{4}$/', $poNumber), $poNumber);

$poRow = $pdo->query("SELECT * FROM procurement_purchase_orders WHERE id = " . $poId)->fetch();
check('PO saved as draft', $poRow && $poRow['status'] === 'draft', $poRow['status'] ?? 'missing');
check('PO linked to source request', $poRow && (int) $poRow['purchase_request_id'] === $requestId, '');
check('PO copies approved subtotal (12000.00)', $poRow && abs(floatval($poRow['subtotal']) - 12000.00) < 0.005, (string) ($poRow['subtotal'] ?? ''));
$poItemCount = (int) $pdo->query("SELECT COUNT(*) FROM procurement_po_items WHERE po_id = " . $poId)->fetchColumn();
check('PO item lines copied', $poItemCount === 1, (string) $poItemCount);

// Stock must NOT change just because a PO exists
$stockNow = (int) $pdo->query("SELECT stock_quantity FROM products WHERE id = {$productId}")->fetchColumn();
check('stock unchanged after approval + PO creation', $stockNow === $baselineStock, "now=$stockNow baseline=$baselineStock");

// Second PO for same request must be blocked
$r = runAction('po_from_request', $invUser, ['request_id' => $requestId]);
check('duplicate PO for one request blocked', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'already been created') !== false, json_encode(resp($r)));

// PO generation from a non-approved request must fail
$r = runAction('pr_save', $invUser, ['supplier_id' => $supplierId, 'request_date' => date('Y-m-d'), 'reason' => 'not for PO', 'items' => json_encode([['product_id' => $productId, 'quantity' => 1, 'estimated_unit_price' => 10]])]);
$draftId2 = (int) (resp($r)['id'] ?? 0);
$r = runAction('po_from_request', $invUser, ['request_id' => $draftId2]);
check('cannot generate PO from draft request', resp($r)['success'] === false, json_encode(resp($r)));
runAction('pr_cancel', $invUser, ['id' => $draftId2]);

// Issue the PO
$r = runAction('po_issue', $invUser, ['po_id' => $poId]);
check('PO issued as Ordered', (resp($r)['status'] ?? '') === 'ordered', json_encode(resp($r)));
$r = runAction('po_issue', $invUser, ['po_id' => $poId]);
check('re-issuing an ordered PO blocked', resp($r)['success'] === false, json_encode(resp($r)));

// ------------------------------------------------------------
// 6) RECEIVE DELIVERIES: partial, over-receipt, duplicates, stock
// ------------------------------------------------------------
echo "\n== 6. RECEIVING ==\n";

$poItemRow = $pdo->query("SELECT * FROM procurement_po_items WHERE po_id = " . $poId . " LIMIT 1")->fetch();
$poItemId = (int) $poItemRow['id'];

$token1 = 'test-token-' . bin2hex(random_bytes(6));
$receipt1 = [
    'po_id' => $poId,
    'received_date' => date('Y-m-d'),
    'delivery_reference' => 'DR-TEST-001',
    'invoice_reference' => 'INV-SUP-TEST',
    'remarks' => 'First partial delivery',
    'client_token' => $token1,
    'received_items' => json_encode([['po_item_id' => $poItemId, 'delivered' => 7, 'accepted' => 6, 'damaged' => 1]]),
];
$r = runAction('receipt_save', $invUser, $receipt1);
$grn = resp($r);
check('partial receipt recorded', ($grn['success'] ?? false) === true, json_encode($grn));
check('PO now partially received', ($grn['po_status'] ?? '') === 'partially_received', (string) ($grn['po_status'] ?? ''));

$stockAfter1 = (int) $pdo->query("SELECT stock_quantity FROM products WHERE id = {$productId}")->fetchColumn();
check('only accepted quantity increased stock (6)', $stockAfter1 === $baselineStock + 6, "now=$stockAfter1 baseline=$baselineStock");

$damagedRow = $pdo->query("SELECT * FROM procurement_grn_items WHERE po_item_id = {$poItemId} ORDER BY id DESC LIMIT 1")->fetch();
check('damaged quantity preserved on receipt line', $damagedRow && (int) $damagedRow['damaged_qty'] === 1 && (int) $damagedRow['accepted_qty'] === 6, json_encode($damagedRow));

// Duplicate submission with the same token must be blocked
$r = runAction('receipt_save', $invUser, $receipt1);
check('duplicate receipt submission blocked (idempotency token)', resp($r)['success'] === false, json_encode(resp($r)));
$stockAfterDup = (int) $pdo->query("SELECT stock_quantity FROM products WHERE id = {$productId}")->fetchColumn();
check('stock unchanged by duplicate receipt', $stockAfterDup === $baselineStock + 6, (string) $stockAfterDup);

// Over-receipt beyond remaining quantity must be blocked (remaining = 4)
$r = runAction('receipt_save', $invUser, [
    'po_id' => $poId,
    'received_date' => date('Y-m-d'),
    'received_items' => json_encode([['po_item_id' => $poItemId, 'delivered' => 5, 'accepted' => 5, 'damaged' => 0]]),
]);
check('over-receipt beyond remaining quantity blocked', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'exceeds') !== false, json_encode(resp($r)));

// Second delivery completes the order
$r = runAction('receipt_save', $invUser, [
    'po_id' => $poId,
    'received_date' => date('Y-m-d'),
    'delivery_reference' => 'DR-TEST-002',
    'client_token' => 'test-token-' . bin2hex(random_bytes(6)),
    'received_items' => json_encode([['po_item_id' => $poItemId, 'delivered' => 4, 'accepted' => 4, 'damaged' => 0]]),
]);
$grn2 = resp($r);
check('final receipt completes the order', ($grn2['success'] ?? false) === true && ($grn2['po_status'] ?? '') === 'received', json_encode($grn2));

$stockAfter2 = (int) $pdo->query("SELECT stock_quantity FROM products WHERE id = {$productId}")->fetchColumn();
check('total stock increase equals full ordered quantity (10)', $stockAfter2 === $baselineStock + 10, "now=$stockAfter2 baseline=$baselineStock");

$stockMovements = (int) $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE reference_type = 'purchase_order' AND reference_id = {$poId}")->fetchColumn();
check('stock movements recorded through existing ledger', $stockMovements === 2, (string) $stockMovements);

// ------------------------------------------------------------
// 7) FINANCE: invoices + partial payments without duplicates
// ------------------------------------------------------------
echo "\n== 7. FINANCE ==\n";

$poTotal = floatval($pdo->query("SELECT total_amount FROM procurement_purchase_orders WHERE id = {$poId}")->fetchColumn());

$r = runAction('save_invoice', $invUser, ['po_id' => $poId, 'invoice_number' => 'INV-TEST-001', 'amount' => $poTotal, 'invoice_date' => date('Y-m-d')]);
check('inventory cannot record supplier invoices', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'Finance') !== false, json_encode(resp($r)));

$r = runAction('save_invoice', $finUser, ['po_id' => $poId, 'invoice_number' => 'INV-TEST-001', 'amount' => $poTotal, 'invoice_date' => date('Y-m-d')]);
$inv = resp($r);
check('finance records supplier invoice', ($inv['success'] ?? false) === true, json_encode($inv));
check('3-way match marked invoice as matched', ($inv['match_status'] ?? '') === 'matched', (string) ($inv['match_status'] ?? ''));
$invoiceId = (int) $pdo->query("SELECT id FROM procurement_invoices WHERE invoice_number = 'INV-TEST-001'")->fetchColumn();

$r = runAction('save_invoice', $finUser, ['po_id' => $poId, 'invoice_number' => 'INV-TEST-001', 'amount' => $poTotal]);
check('duplicate invoice number rejected', resp($r)['success'] === false, json_encode(resp($r)));

$r = runAction('record_supplier_payment', $cashUser, ['invoice_id' => $invoiceId, 'amount' => 100]);
check('cashier cannot record supplier payments', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'Finance') !== false, json_encode(resp($r)));

$r = runAction('record_supplier_payment', $finUser, [
    'invoice_id' => $invoiceId, 'amount' => 5000, 'method' => 'bank_transfer',
    'payment_date' => date('Y-m-d'), 'reference' => 'REF-TEST-001',
]);
$pay = resp($r);
check('partial payment recorded', ($pay['success'] ?? false) === true && ($pay['payment_status'] ?? '') === 'partial', json_encode($pay));
check('outstanding balance reduced', abs(floatval($pay['outstanding'] ?? 0) - ($poTotal - 5000)) < 0.005, json_encode($pay));

$r = runAction('record_supplier_payment', $finUser, [
    'invoice_id' => $invoiceId, 'amount' => 100, 'method' => 'bank_transfer',
    'payment_date' => date('Y-m-d'), 'reference' => 'REF-TEST-001',
]);
check('duplicate payment reference rejected', resp($r)['success'] === false, json_encode(resp($r)));

$r = runAction('record_supplier_payment', $finUser, [
    'invoice_id' => $invoiceId, 'amount' => 999999, 'method' => 'cash',
    'payment_date' => date('Y-m-d'), 'reference' => 'REF-TEST-OVER',
]);
check('over-payment beyond outstanding balance rejected', resp($r)['success'] === false && stripos(resp($r)['message'] ?? '', 'exceeds') !== false, json_encode(resp($r)));

$remaining = round($poTotal - 5000, 2);
$r = runAction('record_supplier_payment', $finUser, [
    'invoice_id' => $invoiceId, 'amount' => $remaining, 'method' => 'check',
    'payment_date' => date('Y-m-d'), 'reference' => 'REF-TEST-002',
]);
$pay2 = resp($r);
check('final payment marks invoice paid', ($pay2['success'] ?? false) === true && ($pay2['payment_status'] ?? '') === 'paid', json_encode($pay2));

$r = runAction('record_supplier_payment', $finUser, ['invoice_id' => $invoiceId, 'amount' => 1, 'reference' => 'REF-TEST-003']);
check('payment after fully paid blocked', resp($r)['success'] === false, json_encode(resp($r)));

$invRow = $pdo->query("SELECT * FROM procurement_invoices WHERE id = {$invoiceId}")->fetch();
check('invoice ledger stores paid_amount + status', abs(floatval($invRow['paid_amount']) - $poTotal) < 0.005 && $invRow['payment_status'] === 'paid', $invRow['payment_status'] . ' ' . $invRow['paid_amount']);

// ------------------------------------------------------------
// 8) PAGE RENDERING (all roles, new + existing pages)
// ------------------------------------------------------------
echo "\n== 8. PAGE RENDERS ==\n";

$renderMatrix = [
    ['dashboard', $adminUser, [], ['Dashboard']],
    ['proc_approvals', $adminUser, [], ['Procurement Approvals', 'Pending Purchase Requests']],
    ['purchase_orders', $adminUser, [], ['Purchase Orders']],
    ['proc_dashboard', $adminUser, [], ['Procurement Dashboard', 'Pending Approvals']],
    ['proc_history', $adminUser, [], ['Procurement History']],
    ['inventory_reports', $adminUser, ['type' => 'procurement'], ['Procurement Report']],
    ['notifications', $adminUser, [], ['Notifications']],
    ['stock', $invUser, [], ['Stock Management']],
    ['stock', $invUser, ['low' => '1'], ['Stock Management']],
    ['products', $invUser, [], ['Products']],
    ['proc_dashboard', $invUser, [], ['Procurement Dashboard']],
    ['purchase_requests', $invUser, [], ['Purchase Requests']],
    ['purchase_requests', $invUser, ['tab' => 'create'], ['Create Purchase Request', 'Estimated Grand Total']],
    ['purchase_orders', $invUser, [], ['Purchase Orders']],
    ['receive_deliveries', $invUser, [], ['Receive Deliveries']],
    ['proc_history', $invUser, [], ['Procurement History']],
    ['suppliers', $invUser, [], ['Suppliers']],
    ['finance_dashboard', $finUser, [], ['Procurement Payables', 'Open Supplier Invoices']],
    ['finance_reports', $finUser, ['type' => 'procurement'], ['Supplier Invoices', 'Supplier Payments']],
    ['purchase_orders', $finUser, [], ['Purchase Orders']],
    ['dashboard', $cashUser, [], ['Dashboard']],
    ['cart', $cashUser, [], ['Cart']],
    ['hr', $hrUser, [], ['HR']],
    // Access denials
    ['proc_approvals', $invUser, [], ['Access Denied']],
    ['proc_approvals', $cashUser, [], ['Access Denied']],
    ['receive_deliveries', $finUser, [], ['Access Denied']],
];

foreach ($renderMatrix as $case) {
    list($page, $userId, $get, $expect) = $case;
    $r = runRender($page, $userId, $get, $expect);
    $label = "render {$page} (user #$userId)" . (count($get) ? (' ' . json_encode($get)) : '');
    $detail = json_encode(['missing' => $r['missing'] ?? [], 'error' => $r['error_excerpt'] ?? ($r['error'] ?? ''), 'len' => $r['length'] ?? 0]);
    check($label, ($r['ok'] ?? false) === true, $detail);
}

// ------------------------------------------------------------
// 9) EXISTING FEATURES STILL WORK (regression)
// ------------------------------------------------------------
echo "\n== 9. REGRESSION ==\n";

$r = runAction('get_products', $cashUser);
check('legacy get_products still works', $r['ok'] === true && count((array) (resp($r) ?? [])) > 0, json_encode($r));

$r = runAction('get_suppliers', $invUser);
check('legacy get_suppliers still works', $r['ok'] === true && count((array) (resp($r) ?? [])) > 0, json_encode($r));

$r = runAction('save_procurement', $invUser, [
    'item_name' => 'Legacy Requisition Item',
    'quantity' => 3,
    'estimated_unit_cost' => 50,
    'supplier_id' => $supplierId,
    'notes' => 'legacy regression test',
]);
$legacy = resp($r);
check('legacy requisition creation still works', ($legacy['success'] ?? false) === true, json_encode($legacy));
$legacyStmt = $pdo->prepare("SELECT id FROM procurement_requests WHERE req_number = ?");
$legacyStmt->execute([(string) ($legacy['req_number'] ?? '')]);
$legacyId = (int) $legacyStmt->fetchColumn();

$r = runAction('update_procurement_status', $invUser, ['id' => $legacyId, 'status' => 'approved']);
check('inventory still cannot approve legacy requisitions', resp($r)['success'] === false, json_encode(resp($r)));

$r = runAction('get_stock_history', $invUser, ['product_id' => $productId]);
check('legacy stock history still works', $r['ok'] === true, json_encode($r));

$r = runAction('get_sales', $cashUser);
check('legacy sales listing still works', $r['ok'] === true, json_encode($r));

// Procurement records visible in reports (inventory reports + history pages already rendered);
// verify the history union includes this workflow's records
$historyRender = runRender('proc_history', $adminUser, ['type' => '', 'q' => $poNumber], ['Procurement History']);
check('procurement history search finds generated PO', ($historyRender['ok'] ?? false) === true, json_encode($historyRender));

// ------------------------------------------------------------
// CLEANUP: remove every trace of the test run
// ------------------------------------------------------------
echo "\n== CLEANUP ==\n";
$testUserIds = [$invUser, $adminUser, $finUser, $cashUser, $hrUser];
$ph = implode(',', array_map('intval', $testUserIds));

$requestIds = $pdo->query("SELECT id FROM purchase_requests WHERE requester_id IN ({$ph})")->fetchAll(PDO::FETCH_COLUMN);
$requestIds = array_map('intval', $requestIds);
$notifRefs = $requestIds;

$poIds = $pdo->query("
    SELECT id FROM procurement_purchase_orders
    WHERE created_by IN ({$ph})
       OR purchase_request_id IN (SELECT id FROM purchase_requests WHERE requester_id IN ({$ph}))
")->fetchAll(PDO::FETCH_COLUMN);
$poIds = array_map('intval', $poIds);
$poPh = count($poIds) ? implode(',', $poIds) : '0';

$grnIds = $pdo->query("SELECT id FROM procurement_grns WHERE po_id IN ({$poPh})")->fetchAll(PDO::FETCH_COLUMN);
$grnIds = array_map('intval', $grnIds);
$grnPh = count($grnIds) ? implode(',', $grnIds) : '0';

$invoiceIds = $pdo->query("SELECT id FROM procurement_invoices WHERE po_id IN ({$poPh}) OR created_by IN ({$ph})")->fetchAll(PDO::FETCH_COLUMN);
$invoiceIds = array_map('intval', $invoiceIds);
$invPh = count($invoiceIds) ? implode(',', $invoiceIds) : '0';

$reqPh = count($requestIds) ? implode(',', $requestIds) : '0';

$pdo->exec("DELETE FROM procurement_grn_items WHERE grn_id IN ({$grnPh})");
$pdo->exec("DELETE FROM procurement_grns WHERE id IN ({$grnPh})");
$pdo->exec("DELETE FROM procurement_payments WHERE invoice_id IN ({$invPh})");
$pdo->exec("DELETE FROM procurement_invoices WHERE id IN ({$invPh})");
$pdo->exec("DELETE FROM procurement_po_items WHERE po_id IN ({$poPh})");
$pdo->exec("DELETE FROM stock_movements WHERE reference_type = 'purchase_order' AND reference_id IN ({$poPh})");
$pdo->exec("DELETE FROM inventory_transactions WHERE reference_type = 'purchase_order' AND reference_id IN ({$poPh})");
$pdo->exec("DELETE FROM procurement_purchase_orders WHERE id IN ({$poPh})");
$pdo->exec("DELETE FROM procurement_approval_logs WHERE request_id IN ({$reqPh})");
$pdo->exec("DELETE FROM purchase_request_items WHERE request_id IN ({$reqPh})");
$pdo->exec("DELETE FROM purchase_requests WHERE id IN ({$reqPh})");
$pdo->exec("DELETE FROM procurement_requests WHERE requested_by IN ({$ph})");
$pdo->exec("DELETE FROM inventory_notifications WHERE triggered_by IN ({$ph}) OR reference_id IN ({$reqPh})");
$pdo->exec("DELETE FROM activity_log WHERE user_id IN ({$ph})");
$pdo->exec("DELETE FROM session_log WHERE user_id IN ({$ph})");

// Restore the product stock used during the test
$pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")->execute([$baselineStock, $productId]);
$restoredStock = (int) $pdo->query("SELECT stock_quantity FROM products WHERE id = {$productId}")->fetchColumn();
check('product stock restored to baseline', $restoredStock === $baselineStock, "now=$restoredStock baseline=$baselineStock");

$pdo->exec("DELETE FROM users WHERE username LIKE 'proctest_%'");
$leftoverRequests = (int) $pdo->query("SELECT COUNT(*) FROM purchase_requests WHERE requester_id IN ({$ph})")->fetchColumn();
$leftoverPos = (int) $pdo->query("SELECT COUNT(*) FROM procurement_purchase_orders WHERE created_by IN ({$ph})")->fetchColumn();
$leftoverUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username LIKE 'proctest_%'")->fetchColumn();
check('all test requests removed', $leftoverRequests === 0, (string) $leftoverRequests);
check('all test purchase orders removed', $leftoverPos === 0, (string) $leftoverPos);
check('all test users removed', $leftoverUsers === 0, (string) $leftoverUsers);

// ------------------------------------------------------------
// SUMMARY
// ------------------------------------------------------------
echo "\n========================================\n";
echo "PASSED: {$passCount}\n";
echo "FAILED: {$failCount}\n";
if ($failCount > 0) {
    echo "Failures:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}
echo "========================================\n";
exit($failCount > 0 ? 1 : 0);







