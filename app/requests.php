<?php
// INITIALIZE
// ============================================

function getPostLoginRedirect() {
    $roleEntryRequirements = [
        'admin.php' => 'admin',
        'hr.php' => 'hr',
        'inventory.php' => 'inventory',
        'finance.php' => 'finance'
    ];
    $pendingEntry = $_SESSION['pending_role_entry'] ?? '';
    if (isset($roleEntryRequirements[$pendingEntry]) && hasRole($roleEntryRequirements[$pendingEntry])) {
        unset($_SESSION['pending_role_entry']);
        return $pendingEntry;
    }
    unset($_SESSION['pending_role_entry']);
    foreach ($roleEntryRequirements as $entry => $role) {
        if (hasRole($role)) {
            return $entry;
        }
    }
    return 'index.php';
}

$categoryManager = new CategoryManager($pdo);
$productManager = new ProductManager($pdo);
$customerManager = new CustomerManager($pdo);
$saleManager = new SaleManager($pdo);
$reportManager = new ReportManager($pdo);
$userManager = new UserManager($pdo);
$activityLogManager = new ActivityLogManager($pdo);
$hrManager = new HRManager($pdo);
$financeManager = new FinanceManager($pdo);
$returnManager = new ReturnManager($pdo); 

// Check remember me token
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_token'])) {
    checkRememberToken();
}

// Handle login
if (isset($_POST['switch_saved_account'])) {
    $result = loginWithSavedAccount($_POST['switch_saved_account']);
    if ($result['success']) {
        if (!empty($result['requires_2fa'])) {
            $_SESSION['2fa_user_id'] = $_SESSION['user_id'];
            header('Location: login.php?2fa=1');
            exit();
        }
        header('Location: ' . getPostLoginRedirect());
        exit();
    }
    $error = $result['message'];
}

if (isset($_POST['login'])) {
    $result = login($_POST['username'], $_POST['password'], isset($_POST['remember']));
    if ($result['success']) {
        if (isset($result['requires_2fa']) && $result['requires_2fa']) {
            $_SESSION['2fa_required'] = true;
            $_SESSION['2fa_user_id'] = $result['user_id'];
            header('Location: login.php?2fa=1');
            exit();
        }
        header('Location: ' . getPostLoginRedirect());
        exit();
    } else {
        $error = $result['message'];
    }
}

// Handle 2FA verification
if (isset($_POST['verify_2fa'])) {
    if (verify2FA($_POST['2fa_code'])) {
        if (!empty($_SESSION['remember_after_2fa']) && isset($_SESSION['user_id'])) {
            saveLoginAccountForDevice($_SESSION['user_id']);
        }
        unset($_SESSION['remember_after_2fa']);
        header('Location: ' . getPostLoginRedirect());
        exit();
    } else {
        $error = 'Invalid 2FA code';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

function generatePoNumber() {
    global $pdo;
    do {
        $po_number = 'PO-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT id FROM procurement_purchase_orders WHERE po_number = ?");
        $stmt->execute([$po_number]);
    } while ($stmt->fetch());

    return $po_number;
}

function recordInventoryTransaction($productId, $transactionType, $quantity, $previousStock, $newStock, $referenceType, $referenceId, $createdBy) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, previous_stock, new_stock, reference_type, reference_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$productId, $transactionType, $quantity, $previousStock, $newStock, $referenceType, $referenceId, $createdBy]);
}

// ============================================
// PROCUREMENT WORKFLOW HELPERS
// ============================================

function generatePurchaseRequestNumber() {
    global $pdo;
    $prefix = 'PR-' . date('Y') . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_requests WHERE request_number LIKE ?");
    $stmt->execute([$prefix . '%']);
    $sequence = (int) $stmt->fetchColumn() + 1;
    do {
        $number = $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $check = $pdo->prepare("SELECT id FROM purchase_requests WHERE request_number = ?");
        $check->execute([$number]);
        if (!$check->fetch()) {
            return $number;
        }
        $sequence++;
    } while (true);
}

function generateGoodsReceiptNumber() {
    global $pdo;
    do {
        $number = 'GRN-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT id FROM procurement_grns WHERE grn_number = ?");
        $stmt->execute([$number]);
    } while ($stmt->fetch());
    return $number;
}
// PROCUREMENT_VALIDATOR_ANCHOR

/**
 * Server-side validation for purchase request payloads.
 * Returns an array of human-readable errors (empty when valid) and,
 * on success, fills $payload with normalized values and server-calculated totals.
 */
function validatePurchaseRequestPayload(array $post, array &$payload) {
    global $pdo;
    $errors = [];
    $payload = [];

    $supplierId = intval($post['supplier_id'] ?? 0);
    if ($supplierId <= 0) {
        $errors[] = 'Please select a supplier.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = ? AND status = 'active'");
        $stmt->execute([$supplierId]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'The selected supplier does not exist or is not active.';
        }
    }

    $requestDate = trim((string) ($post['request_date'] ?? ''));
    if ($requestDate === '') {
        $requestDate = date('Y-m-d');
    }
    $dateCheck = DateTime::createFromFormat('Y-m-d', $requestDate);
    if (!$dateCheck || $dateCheck->format('Y-m-d') !== $requestDate) {
        $errors[] = 'Enter a valid request date (YYYY-MM-DD).';
    }

    $reason = trim((string) ($post['reason'] ?? ''));
    if ($reason === '') {
        $errors[] = 'Reason for purchase is required.';
    } elseif (mb_strlen($reason) > 1000) {
        $errors[] = 'Reason for purchase must be 1,000 characters or fewer.';
    }

    $remarks = trim((string) ($post['remarks'] ?? ''));
    if (mb_strlen($remarks) > 2000) {
        $errors[] = 'Remarks must be 2,000 characters or fewer.';
    }

    $itemsRaw = $post['items'] ?? [];
    if (is_string($itemsRaw)) {
        $decoded = json_decode($itemsRaw, true);
        $itemsRaw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($itemsRaw) || count($itemsRaw) === 0) {
        $errors[] = 'Add at least one product to the purchase request.';
        $itemsRaw = [];
    }
    $allowFree = getSetting('procurement_allow_free_items', '0') === '1';
    $items = [];
    $subtotal = 0.0;
    $seenProducts = [];
    foreach ($itemsRaw as $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $productId = intval($raw['product_id'] ?? 0);
        $quantity = filter_var($raw['quantity'] ?? null, FILTER_VALIDATE_INT);
        $unitPrice = filter_var($raw['estimated_unit_price'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($productId <= 0) {
            $errors[] = 'Every product row must have a product selected.';
            continue;
        }
        if ($quantity === false || $quantity === null || $quantity < 1) {
            $errors[] = 'Quantity must be a positive number for every product row.';
            continue;
        }
        if ($unitPrice === false || $unitPrice === null || $unitPrice < 0) {
            $errors[] = 'Estimated unit price must be a valid non-negative amount.';
            continue;
        }
        if ($unitPrice <= 0 && !$allowFree) {
            $errors[] = 'Estimated unit price must be greater than zero (free-of-charge items are not authorized).';
            continue;
        }
        if (isset($seenProducts[$productId])) {
            $errors[] = 'The same product appears more than once. Combine the quantities into a single row.';
            continue;
        }

        $stmt = $pdo->prepare("SELECT id, name, unit, archived, active FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            $errors[] = 'A selected product no longer exists in the catalog.';
            continue;
        }
        if ((int) $product['archived'] === 1 || (int) $product['active'] !== 1) {
            $errors[] = 'Product "' . $product['name'] . '" is not eligible for purchase.';
            continue;
        }

        $seenProducts[$productId] = true;
        $lineTotal = round($quantity * $unitPrice, 2);
        $subtotal += $lineTotal;
        $items[] = [
            'product_id' => $productId,
            'item_name' => $product['name'],
            'unit' => $product['unit'] ?: 'pc',
            'quantity' => $quantity,
            'estimated_unit_price' => round($unitPrice, 2),
            'line_total' => $lineTotal,
        ];
    }
    $subtotal = round($subtotal, 2);

    // Configured purchasing limits / approval policy
    $hardLimit = floatval(getSetting('procurement_max_amount', 0));
    $approvalLimit = floatval(getSetting('procurement_approval_limit', 0));
    $overLimit = 0;
    if ($hardLimit > 0 && $subtotal > $hardLimit) {
        $errors[] = 'The estimated total of ₱' . number_format($subtotal, 2) . ' exceeds the configured purchasing limit of ₱' . number_format($hardLimit, 2) . '.';
    }
    if ($approvalLimit > 0 && $subtotal > $approvalLimit) {
        $overLimit = 1;
    }

    $payload = [
        'supplier_id' => $supplierId,
        'request_date' => $requestDate,
        'reason' => $reason,
        'remarks' => $remarks,
        'items' => $items,
        'subtotal' => $subtotal,
        'grand_total' => $subtotal,
        'over_limit' => $overLimit,
    ];
    return $errors;
}
/**
 * Flags possible duplicate or unnecessary purchases so requesters and
 * approvers can see stock levels, pending requests and open orders.
 * Returns a list of warning strings (empty when nothing notable).
 */
function computePurchaseRequestWarnings($items, $excludeRequestId = 0) {
    global $pdo;
    $warnings = [];
    foreach ($items as $item) {
        $productId = intval($item['product_id'] ?? 0);
        if ($productId <= 0) {
            continue;
        }
        $stmt = $pdo->prepare("SELECT name, stock_quantity, low_stock_threshold FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            continue;
        }
        $stock = (int) $product['stock_quantity'];
        $threshold = (int) $product['low_stock_threshold'];
        $label = $product['name'];

        $pendingStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(pri.quantity), 0) AS qty
             FROM purchase_request_items pri
             JOIN purchase_requests pr ON pr.id = pri.request_id
             WHERE pri.product_id = ? AND pr.status IN ('pending_approval','approved')" .
             ($excludeRequestId ? " AND pr.id <> ?" : "")
        );
        $pendingStmt->execute($excludeRequestId ? [$productId, $excludeRequestId] : [$productId]);
        $pendingQty = (int) $pendingStmt->fetchColumn();

        $openPoStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(pi.quantity - pi.quantity_received), 0) AS qty
             FROM procurement_po_items pi
             JOIN procurement_purchase_orders po ON po.id = pi.po_id
             WHERE pi.product_id = ? AND po.status IN ('draft','ordered','sent','acknowledged','partially_received')"
        );
        $openPoStmt->execute([$productId]);
        $openPoQty = (int) $openPoStmt->fetchColumn();

        if ($pendingQty > 0) {
            $warnings[] = $label . ': ' . $pendingQty . ' unit(s) already in other pending/approved purchase requests — possible duplicate purchase.';
        }
        if ($openPoQty > 0) {
            $warnings[] = $label . ': ' . $openPoQty . ' unit(s) already on open purchase orders — possible duplicate purchase.';
        }
        if ($stock > $threshold && $pendingQty === 0 && $openPoQty === 0) {
            $warnings[] = $label . ': current stock (' . $stock . ') is above the low-stock threshold (' . $threshold . ') — confirm this purchase is still needed.';
        }
    }
    return $warnings;
}


// ============================================
// AJAX request handlers
// ============================================

if (isset($_GET['action'])) {
    // CSRF mitigation: for state-changing POST actions, the request must originate from our own pages
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $ok = false;
        foreach ([$origin, $referer] as $src) {
            if ($src && $host && stripos($src, $host) !== false) { $ok = true; break; }
        }
        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Invalid request origin']);
            exit();
        }
    }
    header('Content-Type: application/json; charset=utf-8');

    // Enforce CSRF token on procurement & job posting write actions
    $csrfActions = ['save_procurement', 'update_procurement_status', 'set_budget_status', 'save_rfq', 'update_rfq_status', 'save_quotation', 'select_quotation', 'create_po', 'update_po_status', 'save_grn', 'save_invoice', 'approve_invoice', 'pay_invoice_paymongo', 'mark_invoice_paid', 'save_supplier_payment', 'confirm_payment', 'rate_supplier', 'save_job_posting', 'update_job_posting_status', 'save_employee', 'save_product_evaluation', 'pr_save', 'pr_submit', 'pr_cancel', 'pr_decide', 'po_from_request', 'po_issue', 'receipt_save', 'record_supplier_payment'];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_GET['action'] ?? '', $csrfActions, true)) {
        verifyCsrf();
    }

    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    $adminActions = ['add_product', 'update_product', 'delete_product', 'archive_product', 'restore_product', 'add_user', 'delete_user', 'add_category', 'update_category', 'delete_category', 'save_settings', 'get_settings'];
    
    if (in_array($_GET['action'], $adminActions) && !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin only']);
        exit();
    }
    
    $hrActions = ['save_employee', 'get_employee', 'delete_employee', 'save_attendance', 'save_leave', 'save_payroll', 'clock_in', 'clock_out', 'get_attendance'];
    if (in_array($_GET['action'], $hrActions) && !hasRole('hr') && !isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized: HR only']);
        exit();
    }
    
    // --- PRODUCT AJAX HANDLERS ---
    
    if ($_GET['action'] == 'add_product' || $_GET['action'] == 'update_product') {
        try {
            $id = isset($_POST['id']) && $_POST['id'] ? $_POST['id'] : null;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $category_id = isset($_POST['category_id']) && $_POST['category_id'] ? $_POST['category_id'] : null;
            $price = isset($_POST['price']) ? $_POST['price'] : 0;
            $stock = isset($_POST['stock']) ? $_POST['stock'] : 0;
            $unit = isset($_POST['unit']) ? $_POST['unit'] : 'pc';
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Product name is required']);
                exit();
            }
            
            if (!$id) {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND archived = 0");
                $stmt->execute([$name]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    echo json_encode(['success' => false, 'message' => 'A product with this name already exists!']);
                    exit();
                }
            } else {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND id != ? AND archived = 0");
                $stmt->execute([$name, $id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    echo json_encode(['success' => false, 'message' => 'Another product with this name already exists!']);
                    exit();
                }
            }
            
            $imagePath = null;
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/products/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                if ($_FILES['image']['size'] > 10 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'File is too large. Maximum 10MB allowed.']);
                    exit();
                }
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mimeType, $allowedTypes)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type. Please upload JPEG, PNG, GIF, or WebP.']);
                    exit();
                }
                
                $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = $targetPath;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
                    exit();
                }
            }
            
            if ($id && !$imagePath) {
                $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $imagePath = $existing['image'];
                }
            }
            
            if ($id) {
                $result = $productManager->updateProduct($id, $name, $category_id, $price, $stock, $unit, $imagePath);
            } else {
                $result = $productManager->addProduct($name, $category_id, $price, $stock, $unit, null, $imagePath);
            }
            
            // 🔔 Notify inventory staff (only if changed by non-inventory user)
            if ($result) {
                $actionWord = $id ? 'edited' : 'added';
                notifyInventory(
                    $id ? 'product_edited' : 'product_added',
                    '📦 Product ' . ucfirst($actionWord),
                    "Product \"{$name}\" was {$actionWord} by " . ($_SESSION['full_name'] ?? 'Admin') . ".",
                    $id ?: $pdo->lastInsertId()
                );
            }

            echo json_encode(['success' => $result]);
            exit();
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
    }
    
    if ($_GET['action'] == 'get_products') {
        echo json_encode($productManager->getAllProducts(false));
        exit();
    }
    
    if ($_GET['action'] == 'get_categories') {
        echo json_encode($categoryManager->getAllCategories());
        exit();
    }
    
    if ($_GET['action'] == 'add_category') {
        $result = $categoryManager->addCategory($_POST['name'], $_POST['description']);
        echo json_encode(['success' => $result]);
        exit();
    }
    
    if ($_GET['action'] == 'update_category') {
        $result = $categoryManager->updateCategory($_POST['id'], $_POST['name'], $_POST['description']);
        echo json_encode(['success' => $result]);
        exit();
    }
    
    if ($_GET['action'] == 'delete_category') {
        $result = $categoryManager->deleteCategory($_GET['id']);
        echo json_encode($result);
        exit();
    }
    
    if ($_GET['action'] == 'get_archived_products') {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        echo json_encode($productManager->getArchivedProducts($search));
        exit();
    }
    
    if ($_GET['action'] == 'archive_product') {
        $productId = $_GET['id'];
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $archivedName = $stmt->fetch()['name'] ?? 'Unknown';
        
        $result = $productManager->archiveProduct($productId);
        
        if ($result) {
            notifyInventory(
                'product_archived',
                '📁 Product Archived',
                "Product \"{$archivedName}\" was archived by " . ($_SESSION['full_name'] ?? 'Admin') . ".",
                $productId
            );
        }
        
        echo json_encode(['success' => $result]);
        exit();
    }
    
    if ($_GET['action'] == 'restore_product') {
        $productId = $_GET['id'];
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $restoredName = $stmt->fetch()['name'] ?? 'Unknown';
        
        $result = $productManager->restoreProduct($productId);
        
        if ($result) {
            notifyInventory(
                'product_restored',
                '♻️ Product Restored',
                "Product \"{$restoredName}\" was restored by " . ($_SESSION['full_name'] ?? 'Admin') . ".",
                $productId
            );
        }
        
        echo json_encode(['success' => $result]);
        exit();
    }
    
    // --- CUSTOMER AJAX HANDLERS ---
    
    if ($_GET['action'] == 'get_customers') {
        echo json_encode($customerManager->getAllCustomers());
        exit();
    }
    
    if ($_GET['action'] == 'get_customer') {
        $customer = $customerManager->getCustomer($_GET['id']);
        $stats = $customerManager->getCustomerStats($_GET['id']);
        $history = $customerManager->getCustomerPurchaseHistory($_GET['id']);
        echo json_encode([
            'customer' => $customer,
            'stats' => $stats,
            'history' => $history
        ]);
        exit();
    }
    
    if ($_GET['action'] == 'add_customer') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Name is required']);
            exit();
        }
        
        $result = $customerManager->addCustomer($name, $email, $phone);
        echo json_encode(['success' => $result]);
        exit();
    }
    
    if ($_GET['action'] == 'update_customer') {
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        
        if (!$id || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit();
        }
        
        $result = $customerManager->updateCustomer($id, $name, $email, $phone);
        echo json_encode($result);
        exit();
    }
    
    if ($_GET['action'] == 'delete_customer') {
        $result = $customerManager->deleteCustomer($_GET['id']);
        echo json_encode($result);
        exit();
    }
    
    // --- SALE AJAX HANDLERS ---
    
    if ($_GET['action'] == 'create_sale' && isset($_POST['items'])) {
        $items = json_decode($_POST['items'], true);
        $total = floatval($_POST['total']);
        $customer_id = isset($_POST['customer_id']) && $_POST['customer_id'] ? $_POST['customer_id'] : null;
        $amount_paid = isset($_POST['amount_paid']) ? floatval($_POST['amount_paid']) : $total;
        $change = isset($_POST['change']) ? floatval($_POST['change']) : 0;
        $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
        $loyalty_points_used = isset($_POST['loyalty_points_used']) ? intval($_POST['loyalty_points_used']) : 0;
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_token_id = !empty($_POST['payment_token_id']) ? intval($_POST['payment_token_id']) : null;

        $result = $saleManager->createSale($items, $total, $customer_id, $amount_paid, $change, $discount, $loyalty_points_used, $payment_method, $payment_token_id);
        echo json_encode($result);
        exit();
    }

                // ============================================
            // Sales list endpoint
            // ============================================

            if ($_GET['action'] == 'get_sales') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        try {
            // FIX: Use a simple query without parameter binding for LIMIT
            $stmt = $pdo->query("
                SELECT s.*, u.full_name as cashier, c.name as customer 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                LEFT JOIN customers c ON s.customer_id = c.id 
                ORDER BY s.sale_date DESC 
                LIMIT $limit
            ");
            $sales = $stmt->fetchAll();
            echo json_encode($sales);
        } catch(PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }

// ============================================

// ============================================
    
    if ($_GET['action'] == 'get_sale' && isset($_GET['id'])) {
        echo json_encode($saleManager->getSale($_GET['id']));
        exit();
    }
    
    if ($_GET['action'] == 'delete_product') {
        $productId = $_GET['id'];
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $deletedName = $stmt->fetch()['name'] ?? 'Unknown';

        $result = $productManager->deleteProduct($productId);

        if ($result) {
            notifyInventory(
                'product_deleted',
                '🗑️ Product Deleted',
                "Product \"{$deletedName}\" was deleted by " . ($_SESSION['full_name'] ?? 'Admin') . ".",
                $productId
            );
        }

        echo json_encode(['success' => $result]);
        exit();
    }
    
    if ($_GET['action'] == 'get_product') {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        echo json_encode($stmt->fetch());
        exit();
    }
    
    // --- USER AJAX HANDLERS ---

    if ($_GET['action'] == 'save_custom_role') {
        $roleKey = $_POST['role_key'] ?? '';
        if (!isAdmin() && !hasRole('hr')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        $result = $userManager->saveCustomRole(
            $_POST['role_name'] ?? '',
            $roleKey,
            $_POST['permissions'] ?? []
        );
        echo json_encode($result);
        exit();
    }

    if ($_GET['action'] == 'delete_custom_role') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        $result = $userManager->deleteCustomRole($_POST['role_key'] ?? '');
        echo json_encode($result);
        exit();
    }
    
    if ($_GET['action'] == 'add_user') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        $result = $userManager->addUser($_POST['username'], $_POST['password'], $_POST['full_name'], $_POST['role'] ?? []);
        echo json_encode(['success' => $result]);
        exit();
    }

    if ($_GET['action'] == 'update_user_roles') {
        if (!isAdmin() && !hasRole('hr')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        $targetId = (int)($_POST['id'] ?? 0);
        $requestedRoles = getUserRoles($_POST['roles'] ?? []);
        if (!isAdmin()) {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $targetUser = $stmt->fetch();
            if (!$targetUser || in_array('admin', $requestedRoles, true) || in_array('admin', getUserRoles($targetUser['role']), true)) {
                echo json_encode(['success' => false, 'message' => 'HR cannot assign or change admin access.']);
                exit();
            }
        }
        $result = $userManager->updateRoles($targetId, $requestedRoles);
        if ($result && (int)($_POST['id'] ?? 0) === (int)($_SESSION['user_id'] ?? 0)) {
            $_SESSION['role'] = implode(',', array_values(array_intersect(getAssignableRoleKeys(), getUserRoles($_POST['roles'] ?? []))));
        }
        echo json_encode(['success' => $result, 'message' => $result ? '' : 'Select at least one role.']);
        exit();
    }
    
    if ($_GET['action'] == 'delete_user') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        $result = $userManager->deleteUser($_GET['id']);
        echo json_encode(['success' => $result]);
        exit();
    }
    
    if ($_GET['action'] == 'get_archive_history') {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        echo json_encode($productManager->getArchiveHistory($search));
        exit();
    }
    
    if ($_GET['action'] == 'get_chart_data') {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
        echo json_encode($reportManager->getChartData($days));
        exit();
    }
    
    if ($_GET['action'] == 'get_customer_spending') {
        echo json_encode($customerManager->getCustomerSpendingReport());
        exit();
    }
    
    if ($_GET['action'] == 'check_user') {
        $username = isset($_GET['username']) ? $_GET['username'] : '';
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $exists = $stmt->fetch() ? true : false;
        echo json_encode(['exists' => $exists]);
        exit();
    }
    
    if ($_GET['action'] == 'reset_password') {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        
        if (empty($username) || empty($new_password)) {
            echo json_encode(['success' => false, 'message' => 'Username and new password required']);
            exit();
        }
        
        $result = $userManager->updatePassword($username, $new_password);
        echo json_encode(['success' => $result]);
        exit();
    }
    
    if ($_GET['action'] == 'get_activity_log') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        echo json_encode($activityLogManager->getActivityLog($limit));
        exit();
    }
    
    if ($_GET['action'] == 'get_sales_by_date') {
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        echo json_encode($productManager->getSalesByDate($start_date, $end_date));
        exit();
    }
    
    if ($_GET['action'] == 'get_sales_by_customer') {
        $customer_id = isset($_GET['customer_id']) ? $_GET['customer_id'] : null;
        if ($customer_id) {
            echo json_encode($productManager->getSalesByCustomer($customer_id));
        } else {
            echo json_encode([]);
        }
        exit();
    }
    
    if ($_GET['action'] == 'save_settings') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        if (isset($_POST['store_name'])) setSetting('store_name', $_POST['store_name']);
        if (isset($_POST['tax_rate'])) setSetting('tax_rate', $_POST['tax_rate']);
        if (isset($_POST['store_address'])) setSetting('store_address', $_POST['store_address']);
        if (isset($_POST['store_contact'])) setSetting('store_contact', $_POST['store_contact']);
        if (isset($_POST['vat_reg_number'])) setSetting('vat_reg_number', $_POST['vat_reg_number']);
        if (isset($_POST['mail_enabled'])) setSetting('mail_enabled', !empty($_POST['mail_enabled']) ? '1' : '0');
        if (isset($_POST['smtp_host'])) setSetting('smtp_host', trim($_POST['smtp_host']));
        if (isset($_POST['smtp_port'])) setSetting('smtp_port', trim($_POST['smtp_port']));
        if (isset($_POST['smtp_encryption'])) setSetting('smtp_encryption', trim($_POST['smtp_encryption']));
        if (isset($_POST['smtp_username'])) setSetting('smtp_username', trim($_POST['smtp_username']));
        if (isset($_POST['smtp_password'])) setSetting('smtp_password', trim($_POST['smtp_password']));
        if (isset($_POST['mail_from_name'])) setSetting('mail_from_name', trim($_POST['mail_from_name']));
        if (isset($_POST['mail_from_address'])) setSetting('mail_from_address', trim($_POST['mail_from_address']));
        
        if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/logo/';
            
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
                    exit();
                }
            }
            
            if ($_FILES['store_logo']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'Logo file too large. Max 5MB.']);
                exit();
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $_FILES['store_logo']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type for logo.']);
                exit();
            }
            
            $oldLogo = getSetting('store_logo', '');
            if (!empty($oldLogo) && file_exists($oldLogo)) {
                @unlink($oldLogo);
            }
            
            $fileExtension = pathinfo($_FILES['store_logo']['name'], PATHINFO_EXTENSION);
            $fileName = 'logo_' . time() . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $targetPath)) {
                setSetting('store_logo', $targetPath);
                echo json_encode(['success' => true, 'message' => 'Settings saved with logo']);
                exit();
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload logo - check directory permissions']);
                exit();
            }
        }
        
        logActivity('update_settings', "Updated settings");
        echo json_encode(['success' => true, 'message' => 'Settings saved']);
        exit();
    }
    
    if ($_GET['action'] == 'get_settings') {
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        echo json_encode([
            'store_name' => getSetting('store_name', 'Smart Market'),
            'tax_rate' => getSetting('tax_rate', 12),
            'store_address' => getSetting('store_address', ''),
            'store_contact' => getSetting('store_contact', ''),
            'vat_reg_number' => getSetting('vat_reg_number', ''),
            'store_logo' => getSetting('store_logo', ''),
            'mail_enabled' => getSetting('mail_enabled', false),
            'smtp_host' => getSetting('smtp_host', 'localhost'),
            'smtp_port' => getSetting('smtp_port', 587),
            'smtp_encryption' => getSetting('smtp_encryption', 'tls'),
            'smtp_username' => getSetting('smtp_username', ''),
            'smtp_password' => getSetting('smtp_password', ''),
            'mail_from_name' => getSetting('mail_from_name', 'Smart Market POS'),
            'mail_from_address' => getSetting('mail_from_address', 'noreply@localhost')
        ]);
        exit();
    }
    
    if ($_GET['action'] == 'get_customer_loyalty') {
        $customer_id = isset($_GET['id']) ? $_GET['id'] : null;
        if ($customer_id) {
            $stmt = $pdo->prepare("SELECT loyalty_points FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $points = $stmt->fetch();
            echo json_encode(['points' => $points ? intval($points['loyalty_points']) : 0]);
        } else {
            echo json_encode(['points' => 0]);
        }
        exit();
    }
    
    // --- HR AJAX HANDLERS ---
    
    if ($_GET['action'] == 'save_employee') {
        try {
            $data = [
                'id' => (!empty($_POST['id'])) ? $_POST['id'] : null,
                'employee_id' => $_POST['employee_id'] ?? '',
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'email' => $_POST['email'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'address' => $_POST['address'] ?? null,
                'position' => $_POST['position'] ?? null,
                'department' => $_POST['department'] ?? null,
                'salary' => $_POST['salary'] ?? 0,
                'salary_type' => $_POST['salary_type'] ?? 'monthly',
                'status' => $_POST['status'] ?? 'active',
                'role' => $_POST['role'] ?? null,
                'emergency_name' => $_POST['emergency_name'] ?? null,
                'emergency_phone' => $_POST['emergency_phone'] ?? null,
                'daily_rate' => $_POST['daily_rate'] ?? 0,
                'hourly_rate' => $_POST['hourly_rate'] ?? 0,
                'start_date' => (!empty($_POST['start_date'])) ? $_POST['start_date'] : null,
                'contract_end_date' => (!empty($_POST['contract_end_date'])) ? $_POST['contract_end_date'] : null,
            ];

            // Validate required fields
            if (empty($data['employee_id'])) {
                echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
                exit();
            }
            if (empty($data['first_name']) || empty($data['last_name'])) {
                echo json_encode(['success' => false, 'message' => 'First and Last name are required']);
                exit();
            }
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Enter a valid employee email address']);
                exit();
            }

            $isNewEmployee = empty($data['id']);
            $result = $hrManager->saveEmployee($data);
            
            if ($result) {
                logActivity('save_employee', "Saved employee: {$data['first_name']} {$data['last_name']} ({$data['employee_id']})");
                $emailResult = null;
                if ($isNewEmployee && !empty($data['email'])) {
                    $emailResult = sendEmployeeWelcomeEmail($data);
                    if (!$emailResult['success']) {
                        error_log('Employee welcome email was not sent for '.$data['employee_id'].': '.$emailResult['message']);
                    }
                }
                echo json_encode([
                    'success' => true,
                    'email_sent' => $emailResult === null ? null : $emailResult['success'],
                    'email_message' => $emailResult['message'] ?? null
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error - check error.log']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['action'] == 'get_employee') {
        echo json_encode($hrManager->getEmployee($_GET['id']));
        exit();
    }
    
    if ($_GET['action'] == 'delete_employee') {
        $result = $hrManager->deleteEmployee($_GET['id']);
        echo json_encode(['success' => $result]);
        exit();
    }
    
    if ($_GET['action'] == 'clock_in') {
        $employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : null;
        if ($employee_id) {
            $result = $hrManager->clockIn($employee_id);
            if ($result['success']) {
                logActivity('clock_in', "Employee clocked in (ID: $employee_id)");
                echo json_encode(['success' => true, 'message' => $result['message']]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Employee ID required']);
        }
        exit();
    }
    
    
    if ($_GET['action'] == 'clock_out') {
        $employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : null;
        if ($employee_id) {
            $result = $hrManager->clockOut($employee_id);
            if ($result['success']) {
                logActivity('clock_out', "Employee clocked out (ID: $employee_id)");
                echo json_encode(['success' => true, 'message' => $result['message']]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Employee ID required']);
        }
        exit();
    }
    
   if ($_GET['action'] == 'get_employee_attendance') {
    $employee_id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($employee_id) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM attendance 
                WHERE employee_id = ? 
                ORDER BY date DESC, time_in DESC 
                LIMIT 50
            ");
            $stmt->execute([$employee_id]);
            $result = $stmt->fetchAll();
            echo json_encode($result);
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        echo json_encode([]);
    }
    exit();
}
    if ($_GET['action'] == 'get_employee_stats') {
        try {
            // Get all employees with their stats
            $stmt = $pdo->query("
                SELECT 
                    e.id,
                    e.first_name,
                    e.last_name,
                    e.employee_id,
                    e.start_date,
                    e.contract_end_date,
                    e.status,
                    COUNT(DISTINCT a.id) as shifts,
                    COUNT(DISTINCT a.date) as days_worked,
                    COALESCE(SUM(
                        CASE 
                            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
                            THEN TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                            ELSE 0 
                        END
                    ), 0) as total_minutes
                FROM employees e
                LEFT JOIN attendance a ON e.id = a.employee_id
                GROUP BY e.id
                ORDER BY e.first_name
            ");
            $employees = $stmt->fetchAll();
            
            $totalEmployees = count($employees);
            $activeEmployees = 0;
            $totalShifts = 0;
            $totalMinutes = 0;
            $employeeData = [];
            
            foreach ($employees as $emp) {
                if ($emp['status'] == 'active') $activeEmployees++;
                $totalShifts += $emp['shifts'];
                $totalMinutes += $emp['total_minutes'];
                
                // Calculate days since start
                $daysSinceStart = 0;
                if ($emp['start_date']) {
                    $start = new DateTime($emp['start_date']);
                    $now = new DateTime();
                    $daysSinceStart = $start->diff($now)->days;
                }
                
                // Format hours
                $hours = floor($emp['total_minutes'] / 60) . 'h ' . ($emp['total_minutes'] % 60) . 'm';
                
                $employeeData[] = [
                    'name' => $emp['first_name'] . ' ' . $emp['last_name'],
                    'days_since_start' => $daysSinceStart,
                    'contract_end_date' => $emp['contract_end_date'] ? date('M d, Y', strtotime($emp['contract_end_date'])) : 'N/A',
                    'shifts' => $emp['shifts'],
                    'days_worked' => $emp['days_worked'],
                    'hours' => $hours
                ];
            }
            
            $totalHours = floor($totalMinutes / 60) . 'h ' . ($totalMinutes % 60) . 'm';
            
            echo json_encode([
                'total_employees' => $totalEmployees,
                'active_employees' => $activeEmployees,
                'total_shifts' => $totalShifts,
                'total_hours' => $totalHours,
                'employees' => $employeeData
            ]);
        } catch(PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['action'] == 'save_leave') {
        $employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : null;
        $type = isset($_POST['leave_type']) ? $_POST['leave_type'] : null;
        $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : null;
        $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
        
        if (empty($employee_id) || empty($type) || empty($start_date) || empty($end_date)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit();
        }
        
        if (strtotime($start_date) > strtotime($end_date)) {
            echo json_encode(['success' => false, 'message' => 'Start date cannot be after end date']);
            exit();
        }
        
        try {
            $result = $hrManager->createLeaveRequest($employee_id, $type, $start_date, $end_date, $reason);
            
            if ($result) {
                logActivity('create_leave', "Created leave request for employee ID: $employee_id");
                echo json_encode(['success' => true, 'message' => 'Leave request submitted successfully']);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
                $stmt->execute([$employee_id]);
                if (!$stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Employee not found']);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT id FROM leave_requests 
                        WHERE employee_id = ? 
                        AND status != 'rejected'
                        AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))
                    ");
                    $stmt->execute([$employee_id, $end_date, $start_date, $start_date, $end_date]);
                    if ($stmt->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Employee already has a leave request for this period']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to create leave request - database error']);
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("save_leave PDO Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        } catch (Exception $e) {
            error_log("save_leave Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['action'] == 'get_leaves') {
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        echo json_encode($hrManager->getLeaveRequests($status));
        exit();
    }
    
    if ($_GET['action'] == 'update_leave_status') {
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        $status = isset($_POST['status']) ? $_POST['status'] : null;
        
        if (empty($id) || empty($status)) {
            echo json_encode(['success' => false, 'message' => 'ID and status required']);
            exit();
        }
        
        $result = $hrManager->updateLeaveStatus($id, $status);
        if ($result) {
            logActivity('update_leave', "Updated leave request $id to $status");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update leave status']);
        }
        exit();
    }
    
    if ($_GET['action'] == 'save_payroll') {
        $employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : null;
        $amount = isset($_POST['amount']) ? $_POST['amount'] : null;
        $period_start = isset($_POST['period_start']) ? $_POST['period_start'] : null;
        $period_end = isset($_POST['period_end']) ? $_POST['period_end'] : null;
        $type = isset($_POST['payroll_type']) ? $_POST['payroll_type'] : 'monthly';
        
        if (empty($employee_id) || empty($amount) || empty($period_start) || empty($period_end)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit();
        }
        
        $result = $hrManager->createPayroll($employee_id, $amount, $period_start, $period_end, $type);
        if ($result) {
            logActivity('create_payroll', "Created payroll for employee ID: $employee_id");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create payroll']);
        }
        exit();
    }
    
    if ($_GET['action'] == 'get_payroll') {
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        echo json_encode($hrManager->getPayroll($status));
        exit();
    }
    
    if ($_GET['action'] == 'update_payroll_status') {
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        $status = isset($_POST['status']) ? $_POST['status'] : null;
        
        if (empty($id) || empty($status)) {
            echo json_encode(['success' => false, 'message' => 'ID and status required']);
            exit();
        }
        
        $result = $hrManager->updatePayrollStatus($id, $status);
        if ($result) {
            logActivity('update_payroll', "Updated payroll $id to $status");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update payroll status']);
        }
        exit();
    }
    
    if ($_GET['action'] == 'get_hr_stats') {
        echo json_encode($hrManager->getHRStats());
        exit();
    }
    if ($_GET['action'] == 'process_payroll') {
    if (!isAdmin() && !hasRole('hr')) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    try {
        // Check if there are any active employees with salary
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employees WHERE status = 'active' AND salary > 0");
        $stmt->execute();
        $activeCount = $stmt->fetch()['count'];
        
        if ($activeCount == 0) {
            echo json_encode(['success' => false, 'message' => 'No active employees with salary found']);
            exit();
        }
        
        // Determine the current payroll period
        $currentPeriodStart = date('Y-m-01'); // First day of current month
        $currentPeriodEnd = date('Y-m-t');   // Last day of current month
        $currentPeriodType = 'monthly';
        
        // Check if payroll has already been processed for this period
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM payroll 
            WHERE period_start = ? 
            AND period_end = ? 
            AND status = 'paid'
        ");
        $stmt->execute([$currentPeriodStart, $currentPeriodEnd]);
        $alreadyPaidCount = $stmt->fetch()['count'];
        
        if ($alreadyPaidCount > 0) {
            // Get details of already paid payroll
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    e.first_name,
                    e.last_name,
                    e.employee_id
                FROM payroll p
                JOIN employees e ON p.employee_id = e.id
                WHERE p.period_start = ? 
                AND p.period_end = ? 
                AND p.status = 'paid'
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$currentPeriodStart, $currentPeriodEnd]);
            $alreadyPaid = $stmt->fetchAll();
            
            $employeeNames = array();
            foreach ($alreadyPaid as $pay) {
                $employeeNames[] = $pay['first_name'] . ' ' . $pay['last_name'] . ' (₱' . number_format($pay['amount'], 2) . ')';
            }
            
            $message = "⚠️ Payroll for this period (" . date('F Y', strtotime($currentPeriodStart)) . ") has already been processed!\n\n";
            $message .= "Already paid employees:\n" . implode("\n", $employeeNames);
            $message .= "\n\nDo you want to process payroll for the next period?";
            
            echo json_encode([
                'success' => false, 
                'message' => $message,
                'already_paid' => true,
                'period' => date('F Y', strtotime($currentPeriodStart))
            ]);
            exit();
        }
        
        // Check if there are any pending payroll records for this period
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM payroll 
            WHERE period_start = ? 
            AND period_end = ? 
            AND status = 'pending'
        ");
        $stmt->execute([$currentPeriodStart, $currentPeriodEnd]);
        $pendingCount = $stmt->fetch()['count'];
        
        if ($pendingCount > 0) {
            // Mark pending payroll as paid
            $stmt = $pdo->prepare("
                UPDATE payroll 
                SET status = 'paid', updated_at = NOW() 
                WHERE period_start = ? 
                AND period_end = ? 
                AND status = 'pending'
            ");
            $stmt->execute([$currentPeriodStart, $currentPeriodEnd]);
            $paidCount = $stmt->rowCount();
            
            logActivity('process_payroll', "Processed payroll for $paidCount employees for period " . date('F Y', strtotime($currentPeriodStart)));
            echo json_encode([
                'success' => true, 
                'message' => "✅ Payroll processed for $paidCount employees for " . date('F Y', strtotime($currentPeriodStart))
            ]);
            exit();
        }
        
        // No payroll exists for this period, create new payroll
        $stmt = $pdo->prepare("
            INSERT INTO payroll (employee_id, amount, period_start, period_end, payroll_type, status, created_at)
            SELECT 
                id,
                salary,
                ?,
                ?,
                salary_type,
                'pending',
                NOW()
            FROM employees 
            WHERE status = 'active' AND salary > 0
        ");
        $stmt->execute([$currentPeriodStart, $currentPeriodEnd]);
        $createdCount = $stmt->rowCount();
        
        if ($createdCount == 0) {
            echo json_encode(['success' => false, 'message' => 'No active employees with salary found']);
            exit();
        }
        
        // Mark them as paid
        $stmt = $pdo->prepare("
            UPDATE payroll 
            SET status = 'paid', updated_at = NOW() 
            WHERE period_start = ? 
            AND period_end = ? 
            AND status = 'pending'
        ");
        $stmt->execute([$currentPeriodStart, $currentPeriodEnd]);
        $paidCount = $stmt->rowCount();
        
        logActivity('process_payroll', "Created and processed payroll for $paidCount employees for " . date('F Y', strtotime($currentPeriodStart)));
        echo json_encode([
            'success' => true, 
            'message' => "✅ Payroll created and processed for $paidCount employees for " . date('F Y', strtotime($currentPeriodStart))
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

        // ============================================
        // RECORD HOURS WORKED FOR HOURLY EMPLOYEES
        // ============================================

        if ($_GET['action'] == 'record_hours') {
            $employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : null;
            $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
            $hours_worked = isset($_POST['hours_worked']) ? $_POST['hours_worked'] : 0;
            
            if (!$employee_id) {
                echo json_encode(['success' => false, 'message' => 'Employee ID required']);
                exit();
            }
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_hours 
                    (employee_id, date, hours_worked) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE hours_worked = hours_worked + ?
                ");
                $stmt->execute([$employee_id, $date, $hours_worked, $hours_worked]);
                
                echo json_encode(['success' => true, 'message' => 'Hours recorded']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
        }
    
    // --- FINANCE AJAX HANDLERS ---
    
    if ($_GET['action'] == 'get_finance_stats') {
        echo json_encode($financeManager->getDashboardStats());
        exit();
    }
    
    if ($_GET['action'] == 'get_monthly_revenue') {
        $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        echo json_encode($financeManager->getMonthlyRevenue($year));
        exit();
    }
    
    if ($_GET['action'] == 'get_top_customers') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        echo json_encode($financeManager->getTopCustomers($limit));
        exit();
    }
    
    if ($_GET['action'] == 'get_revenue_by_day') {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
        echo json_encode($financeManager->getRevenueByDay($days));
        exit();
    }


    // ============================================
    // INVENTORY AJAX HANDLERS
    // ============================================

    // ---- ADD STOCK ----
    if ($_GET['action'] == 'add_stock') {
        if (!canAccess('stock') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        try {
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            $reason = $_POST['reason'] ?? 'Stock added';
            $notes = $_POST['notes'] ?? '';
            
            if ($quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Quantity must be positive']);
                exit();
            }
            
            // Get current stock
            $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $row = $stmt->fetch();
            
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }
            
            $before = intval($row['stock_quantity']);
            $after = $before + $quantity;
            
            // Update stock
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$after, $product_id]);
            
            // Log movement
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements 
                (product_id, movement_type, quantity, quantity_before, quantity_after, reason, user_id) 
                VALUES (?, 'adjustment', ?, ?, ?, ?, ?)
            ");
            $fullReason = $reason . ($notes ? ' — ' . $notes : '');
            $stmt->execute([$product_id, $quantity, $before, $after, $fullReason, $_SESSION['user_id']]);
            
            logActivity('add_stock', "Added $quantity stock to product ID $product_id (new: $after)");
            
            // 🔔 Notify inventory (only if changed by non-inventory)
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product_name = $stmt->fetch()['name'] ?? 'Product';

            notifyInventory(
                'stock_added',
                '📊 Stock Added',
                "Stock for \"{$product_name}\" increased by {$quantity}. Reason: {$reason}. By " . ($_SESSION['full_name'] ?? 'Admin') . ".",
                $product_id,
                $after
            );

            echo json_encode(['success' => true, 'new_stock' => $after]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- ADJUST STOCK ----
    if ($_GET['action'] == 'adjust_stock') {
        if (!canAccess('stock') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        try {
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            $type = $_POST['type'] ?? 'adjustment';
            $notes = $_POST['notes'] ?? '';
            
            if ($quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Quantity must be positive']);
                exit();
            }
            
            // Get current stock
            $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $row = $stmt->fetch();
            
            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }
            
            $before = intval($row['stock_quantity']);
            $after = max(0, $before - $quantity); // Don't go below 0
            
            // Update stock
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$after, $product_id]);
            
            // Log movement (negative quantity)
            $movement_type = in_array($type, ['damage', 'lost', 'expired', 'correction']) ? $type : 'adjustment';
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements 
                (product_id, movement_type, quantity, quantity_before, quantity_after, reason, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $fullReason = ucfirst($type) . ($notes ? ' — ' . $notes : '');
            $stmt->execute([$product_id, $movement_type, -$quantity, $before, $after, $fullReason, $_SESSION['user_id']]);
            
            logActivity('adjust_stock', "Adjusted stock for product ID $product_id: -$quantity ($type)");
            
            // 🔔 Notify inventory
            $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product_name = $stmt->fetch()['name'] ?? 'Product';

            notifyInventory(
                'stock_adjusted',
                '⚙️ Stock Adjusted',
                "Stock for \"{$product_name}\" decreased by {$quantity}. Reason: {$type}. By " . ($_SESSION['full_name'] ?? 'Admin') . ".",
                $product_id,
                $after
            );

            echo json_encode(['success' => true, 'new_stock' => $after]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- GET STOCK HISTORY ----
    if ($_GET['action'] == 'get_stock_history') {
        if (!canAccess('stock') && !isAdmin()) {
            echo json_encode([]);
            exit();
        }
        
        try {
            $product_id = intval($_GET['product_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT sm.*, u.full_name as user_name
                FROM stock_movements sm
                LEFT JOIN users u ON sm.user_id = u.id
                WHERE sm.product_id = ?
                ORDER BY sm.created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$product_id]);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            echo json_encode([]);
        }
        exit();
    }

    // ---- GET PROCUREMENT REQUESTS ----
    if ($_GET['action'] == 'get_procurement') {
        if (!canAccess('procurement') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $stmt = $pdo->query("
                SELECT r.*, s.name AS supplier_name, p.stock_quantity AS current_stock,
                    p.low_stock_threshold AS minimum_stock, u.full_name AS requested_by_name,
                    a.full_name AS approved_by_name
                FROM procurement_requests r
                LEFT JOIN suppliers s ON r.supplier_id = s.id
                LEFT JOIN products p ON r.product_id = p.id
                LEFT JOIN users u ON r.requested_by = u.id
                LEFT JOIN users a ON r.approved_by = a.id
                ORDER BY r.created_at DESC LIMIT 200
            ");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    // ---- PROCUREMENT SOURCING ANALYSIS ----
    if ($_GET['action'] == 'get_procurement_analysis') {
        if (!canAccess('procurement') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $stmt = $pdo->query("
                SELECT e.*, p.name AS product_name, p.stock_quantity,
                    p.low_stock_threshold AS minimum_stock, p.unit,
                    s.name AS supplier_name, s.supplier_type,
                    COALESCE(demand.units_sold_90d, 0) AS units_sold_90d,
                    performance.avg_performance
                FROM procurement_product_evaluations e
                JOIN products p ON p.id = e.product_id
                JOIN suppliers s ON s.id = e.supplier_id
                LEFT JOIN (
                    SELECT si.product_id, SUM(si.quantity) AS units_sold_90d
                    FROM sale_items si
                    JOIN sales sa ON sa.id = si.sale_id
                    WHERE sa.sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    GROUP BY si.product_id
                ) demand ON demand.product_id = p.id
                LEFT JOIN (
                    SELECT supplier_id,
                        AVG((COALESCE(otif_score, 0) + COALESCE(quality_score, 0) + COALESCE(responsiveness_score, 0)) / 3) AS avg_performance
                    FROM procurement_supplier_ratings
                    GROUP BY supplier_id
                ) performance ON performance.supplier_id = s.id
                ORDER BY e.product_id, e.unit_price ASC, e.updated_at DESC
            ");
            $evaluations = $stmt->fetchAll();
            foreach ($evaluations as &$evaluation) {
                $performance = $evaluation['avg_performance'] !== null ? (float)$evaluation['avg_performance'] : null;
                $quality = (int)$evaluation['quality_score'];
                $availability = (int)$evaluation['availability_score'];
                $demandPerMonth = (float)$evaluation['units_sold_90d'] / 3;
                if ($quality <= 2 || ($performance !== null && $performance <= 2)) {
                    $evaluation['recommendation'] = 'not_recommended';
                } elseif ($quality >= 4 && $availability >= 4 && $demandPerMonth >= 1 && $performance !== null && $performance >= 4) {
                    $evaluation['recommendation'] = 'long_term';
                } elseif ($quality >= 3 && $availability >= 3 && $demandPerMonth > 0) {
                    $evaluation['recommendation'] = 'short_term';
                } else {
                    $evaluation['recommendation'] = 'needs_review';
                }
                $evaluation['recommendation_reason'] = $evaluation['recommendation'] === 'long_term'
                    ? 'Strong demand, good quality and availability, and consistently high supplier performance.'
                    : ($evaluation['recommendation'] === 'short_term'
                        ? 'Suitable for short-term purchasing, but review demand or supplier performance before committing long term.'
                        : ($evaluation['recommendation'] === 'not_recommended'
                            ? 'Low quality or supplier performance score makes this a poor sourcing choice.'
                            : 'More demand or supplier performance history is needed before making a long-term decision.'));
            }
            unset($evaluation);
            echo json_encode($evaluations);
        } catch (Exception $e) {
            error_log('Procurement analysis failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not load procurement analysis.']);
        }
        exit();
    }

    if ($_GET['action'] == 'save_product_evaluation') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $productId = (int)($_POST['product_id'] ?? 0);
            $supplierId = (int)($_POST['supplier_id'] ?? 0);
            $quality = (int)($_POST['quality_score'] ?? 0);
            $availability = (int)($_POST['availability_score'] ?? 0);
            $unitPrice = filter_var($_POST['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
            $leadTime = filter_var($_POST['lead_time_days'] ?? null, FILTER_VALIDATE_INT);
            $minimumOrder = filter_var($_POST['minimum_order_quantity'] ?? null, FILTER_VALIDATE_INT);
            if (!$productId || !$supplierId || $quality < 1 || $quality > 5 || $availability < 1 || $availability > 5 ||
                $unitPrice === false || $unitPrice < 0 || $leadTime === false || $leadTime < 0 ||
                $minimumOrder === false || $minimumOrder < 1) {
                echo json_encode(['success' => false, 'message' => 'Enter a product, supplier, scores from 1–5, non-negative price/lead time, and a minimum order quantity of at least 1.']);
                exit();
            }
            $stmt = $pdo->prepare("
                INSERT INTO procurement_product_evaluations
                    (product_id, supplier_id, quality_score, availability_score, unit_price, lead_time_days, minimum_order_quantity, evaluation_notes, evaluated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quality_score = VALUES(quality_score),
                    availability_score = VALUES(availability_score), unit_price = VALUES(unit_price),
                    lead_time_days = VALUES(lead_time_days), minimum_order_quantity = VALUES(minimum_order_quantity),
                    evaluation_notes = VALUES(evaluation_notes), evaluated_by = VALUES(evaluated_by), updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $productId, $supplierId, $quality, $availability, $unitPrice, $leadTime,
                $minimumOrder, trim($_POST['evaluation_notes'] ?? ''), $_SESSION['user_id']
            ]);
            logActivity('procurement_product_evaluation', "Evaluated product #$productId from supplier #$supplierId");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log('Product sourcing evaluation save failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not save this evaluation.']);
        }
        exit();
    }

    // ---- SAVE PROCUREMENT REQUEST ----
    if ($_GET['action'] == 'save_procurement') {
        if (!canAccess('procurement') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $item_name = trim($_POST['item_name'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 0);
            if ($item_name === '' || $quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Item name and quantity are required']);
                exit();
            }
            $req_number = 'PR-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("
                INSERT INTO procurement_requests
                (req_number, item_name, product_id, quantity, estimated_unit_cost, supplier_id, department, cost_centre, notes, requested_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $req_number, $item_name,
                !empty($_POST['product_id']) ? intval($_POST['product_id']) : null,
                $quantity,
                floatval($_POST['estimated_unit_cost'] ?? 0),
                !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null,
                $_POST['department'] ?? '',
                $_POST['cost_centre'] ?? '',
                $_POST['notes'] ?? '',
                $_SESSION['user_id']
            ]);
            logActivity('procurement_request', "New request $req_number: $quantity x $item_name");
            notifyInventory('procurement', 'Budget check needed', "New requisition $req_number ($quantity x $item_name) needs a finance budget check.", !empty($_POST['product_id']) ? intval($_POST['product_id']) : null);
            echo json_encode(['success' => true, 'req_number' => $req_number]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- UPDATE PROCUREMENT STATUS (approve/reject/order/receive) ----
    if ($_GET['action'] == 'update_procurement_status') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['pending', 'approved', 'rejected', 'ordered', 'received'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                exit();
            }
            $pdo->beginTransaction();
            $row = $pdo->prepare("SELECT * FROM procurement_requests WHERE id = ? FOR UPDATE");
            $row->execute([$id]);
            $req = $row->fetch();
            if (!$req) {
                throw new Exception('Purchase request not found.');
            }
            $requestTransitions = [
                'pending' => ['approved', 'rejected'],
                'approved' => ['ordered', 'rejected'],
                'ordered' => ['received', 'rejected'],
            ];
            if (!in_array($status, $requestTransitions[$req['status']] ?? [], true)) {
                throw new Exception($req['status'] === 'received' && $status === 'received'
                    ? 'This request has already been received.'
                    : 'This purchase request status transition is not allowed.');
            }

            // Approving or rejecting is admin-only and self-approval is forbidden.
            if (in_array($status, ['approved', 'rejected'], true)) {
                if (!hasPermission('procurement_approve')) {
                    throw new Exception('Only an admin account with approval permission can approve or reject purchase requests.');
                }
                if ((int) $req['requested_by'] === (int) $_SESSION['user_id']) {
                    throw new Exception('You cannot approve or reject your own purchase request.');
                }
            }

            if ($status === 'received' && !empty($req['product_id'])) {
                $s = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
                $s->execute([$req['product_id']]);
                $prod = $s->fetch();
                if (!$prod) {
                    throw new Exception('Linked product was not found.');
                }
                $before = (int)$prod['stock_quantity'];
                $quantity = (int)$req['quantity'];
                $after = $before + $quantity;
                $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")->execute([$after, $req['product_id']]);
                $pdo->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, quantity_before, quantity_after, reason, reference_id, reference_type, user_id) VALUES (?, 'purchase', ?, ?, ?, ?, ?, 'purchase', ?)")
                    ->execute([$req['product_id'], $quantity, $before, $after, "Received via procurement {$req['req_number']}", $id, $_SESSION['user_id']]);
                recordInventoryTransaction($req['product_id'], 'PURCHASE', $quantity, $before, $after, 'procurement_request', $id, $_SESSION['user_id']);

                $unitCost = (float)$req['estimated_unit_cost'];
                $subtotal = $unitCost * $quantity;
                $taxAmount = $subtotal * (floatval(getSetting('tax_rate', 12)) / 100);
                $totalAmount = $subtotal + $taxAmount;
                $poNumber = 'PR-' . date('Ymd') . '-' . bin2hex(random_bytes(4));
                $pdo->prepare("INSERT INTO purchases (po_number, supplier_id, purchase_date, subtotal, tax_amount, total_amount, payment_status, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, 'unpaid', ?, ?)")
                    ->execute([$poNumber, $req['supplier_id'] ?: null, date('Y-m-d'), $subtotal, $taxAmount, $totalAmount, "Received via procurement request {$req['req_number']}", $_SESSION['user_id']]);
                $purchaseId = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$purchaseId, $req['product_id'], $quantity, $unitCost, $subtotal]);
            }
            $stmt = $pdo->prepare("UPDATE procurement_requests SET status = ?, approved_by = ? WHERE id = ?");
            $stmt->execute([$status, $_SESSION['user_id'], $id]);
            $pdo->commit();

            if (in_array($status, ['approved', 'received', 'rejected'], true)) {
                $emailResult = sendProcurementStatusEmail($id, $status);
                if (!$emailResult['success']) {
                    error_log('Procurement email notification failed for #'.$id.': '.$emailResult['message']);
                }
            }
            logActivity('procurement_status', "Request #$id marked $status");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- BUDGET CHECK (finance) ----
    if ($_GET['action'] == 'set_budget_status') {
        if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized (finance role required)']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $status = $_POST['budget_status'] ?? '';
            if (!in_array($status, ['approved', 'rejected'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid budget status']); exit();
            }
            $stmt = $pdo->prepare("UPDATE procurement_requests SET budget_status = ?, budget_checked_by = ? WHERE id = ?");
            $stmt->execute([$status, $_SESSION['user_id'], $id]);
            logActivity('budget_check', "Requisition #$id budget $status");
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- RFQ LIST ----
    if ($_GET['action'] == 'get_rfqs') {
        try {
            $stmt = $pdo->query("SELECT r.*, u.full_name AS created_by_name FROM procurement_rfqs r LEFT JOIN users u ON r.created_by = u.id ORDER BY r.created_at DESC LIMIT 200");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    // ---- CREATE RFQ ----
    if ($_GET['action'] == 'save_rfq') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') { echo json_encode(['success' => false, 'message' => 'Title required']); exit(); }
            $rfq_number = 'RFQ-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO procurement_rfqs (rfq_number, request_id, title, item_name, quantity, estimated_value, deadline, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', ?, ?)");
            $stmt->execute([
                $rfq_number,
                !empty($_POST['request_id']) ? intval($_POST['request_id']) : null,
                $title,
                $_POST['item_name'] ?? '',
                max(1, intval($_POST['quantity'] ?? 1)),
                floatval($_POST['estimated_value'] ?? 0),
                !empty($_POST['deadline']) ? $_POST['deadline'] : null,
                $_POST['notes'] ?? '',
                $_SESSION['user_id']
            ]);
            logActivity('rfq_created', "RFQ $rfq_number: $title");
            echo json_encode(['success' => true, 'rfq_number' => $rfq_number]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- RFQ STATUS ----
    if ($_GET['action'] == 'update_rfq_status') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (!in_array($status, ['draft','sent','evaluating','awarded','cancelled'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']); exit();
            }
            $pdo->prepare("UPDATE procurement_rfqs SET status = ? WHERE id = ?")->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- QUOTATIONS ----
    if ($_GET['action'] == 'get_quotations') {
        try {
            $rfq_id = intval($_GET['rfq_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT q.*, s.name AS supplier_name, s.supplier_type,
                    (SELECT AVG((COALESCE(r.otif_score, 0) + COALESCE(r.quality_score, 0) + COALESCE(r.responsiveness_score, 0)) / 3)
                     FROM procurement_supplier_ratings r WHERE r.supplier_id = s.id) AS supplier_performance
                FROM procurement_quotations q
                LEFT JOIN suppliers s ON q.supplier_id = s.id
                WHERE q.rfq_id = ? ORDER BY q.total_price ASC
            ");
            $stmt->execute([$rfq_id]);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    if ($_GET['action'] == 'get_procurement_history') {
        if (!canAccess('procurement') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $stmt = $pdo->query("
                SELECT i.item_name AS product_name, s.name AS supplier_name,
                    COUNT(DISTINCT po.id) AS order_count,
                    SUM(i.quantity) AS units_ordered,
                    MAX(po.order_date) AS last_purchase,
                    AVG(i.unit_cost) AS average_unit_cost
                FROM procurement_po_items i
                JOIN procurement_purchase_orders po ON po.id = i.po_id
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                WHERE po.status IN ('approved','ordered','sent','acknowledged','partially_received','delivered','received','closed')
                GROUP BY i.item_name, po.supplier_id, s.name
                ORDER BY order_count DESC, units_ordered DESC
            ");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            error_log('Procurement history failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not load procurement history.']);
        }
        exit();
    }

    if ($_GET['action'] == 'save_quotation') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $rfq_id = intval($_POST['rfq_id'] ?? 0);
            $supplier_id = intval($_POST['supplier_id'] ?? 0);
            if (!$rfq_id || !$supplier_id) { echo json_encode(['success' => false, 'message' => 'RFQ and supplier required']); exit(); }
            $qty = max(1, intval($_POST['quantity'] ?? 1));
            $unit = floatval($_POST['unit_price'] ?? 0);
            $stmt = $pdo->prepare("INSERT INTO procurement_quotations (rfq_id, supplier_id, unit_price, total_price, lead_time_days, payment_terms, notes, negotiation_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$rfq_id, $supplier_id, $unit, $unit * $qty, intval($_POST['lead_time_days'] ?? 0), $_POST['payment_terms'] ?? '', $_POST['notes'] ?? '', $_POST['negotiation_notes'] ?? '']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    if ($_GET['action'] == 'select_quotation') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $q = $pdo->prepare("SELECT * FROM procurement_quotations WHERE id = ?"); $q->execute([$id]);
            $row = $q->fetch();
            if (!$row) { echo json_encode(['success' => false, 'message' => 'Quotation not found']); exit(); }
            $pdo->prepare("UPDATE procurement_quotations SET is_selected = 0 WHERE rfq_id = ?")->execute([$row['rfq_id']]);
            $pdo->prepare("UPDATE procurement_quotations SET is_selected = 1 WHERE id = ?")->execute([$id]);
            $pdo->prepare("UPDATE procurement_rfqs SET status = 'awarded' WHERE id = ?")->execute([$row['rfq_id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- PURCHASE ORDERS ----
    if ($_GET['action'] == 'get_pos') {
        try {
            $stmt = $pdo->query("SELECT p.*, s.name AS supplier_name, u.full_name AS created_by_name FROM procurement_purchase_orders p LEFT JOIN suppliers s ON p.supplier_id = s.id LEFT JOIN users u ON p.created_by = u.id ORDER BY p.created_at DESC");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    if ($_GET['action'] == 'po_items') {
        if (!canAccess('procurement')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $poId = intval($_GET['po_id'] ?? 0);
            $poStmt = $pdo->prepare("
                SELECT po.*, s.name AS supplier_name, pr.request_number
                FROM procurement_purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN purchase_requests pr ON pr.id = po.purchase_request_id
                WHERE po.id = ?
            ");
            $poStmt->execute([$poId]);
            $poRow = $poStmt->fetch();
            if (!$poRow) {
                echo json_encode(['success' => false, 'message' => 'Purchase order not found.']);
                exit();
            }
            $itemStmt = $pdo->prepare("
                SELECT i.*, (i.quantity - i.quantity_received) AS outstanding
                FROM procurement_po_items i
                WHERE i.po_id = ?
                ORDER BY i.id
            ");
            $itemStmt->execute([$poId]);
            echo json_encode(['success' => true, 'po' => $poRow, 'items' => $itemStmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Could not load purchase order items.']);
        }
        exit();
    }

    if ($_GET['action'] == 'create_po') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $supplier_id = intval($_POST['supplier_id'] ?? 0);
            $items = json_decode($_POST['items'] ?? '', true);
            if (!is_array($items) || !count($items)) {
                $item_name = trim($_POST['item_name'] ?? '');
                if ($item_name !== '') {
                    $items = [[
                        'item_name' => $item_name,
                        'quantity' => max(1, intval($_POST['quantity'] ?? 1)),
                        'unit_cost' => floatval($_POST['unit_cost'] ?? 0),
                        'product_id' => !empty($_POST['product_id']) ? intval($_POST['product_id']) : null
                    ]];
                }
            }
            if (!$supplier_id || empty($items)) {
                echo json_encode(['success' => false, 'message' => 'Supplier and at least one item required']);
                exit();
            }

            $supplierCheck = $pdo->prepare("SELECT id FROM suppliers WHERE id = ? AND status = 'active'");
            $supplierCheck->execute([$supplier_id]);
            if (!$supplierCheck->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Select an active supplier']);
                exit();
            }
            $subtotal = 0;
            foreach ($items as &$it) {
                $itemName = trim((string)($it['item_name'] ?? ''));
                $quantity = filter_var($it['quantity'] ?? null, FILTER_VALIDATE_INT);
                $cost = filter_var($it['unit_cost'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($itemName === '' || $quantity === false || $quantity < 1 || $cost === false || $cost < 0) {
                    echo json_encode(['success' => false, 'message' => 'Every PO item needs a name, positive quantity, and non-negative unit price.']);
                    exit();
                }
                if (!empty($it['product_id'])) {
                    $productCheck = $pdo->prepare("SELECT name FROM products WHERE id = ? AND archived = 0");
                    $productCheck->execute([(int)$it['product_id']]);
                    $productName = $productCheck->fetchColumn();
                    if ($productName === false) {
                        echo json_encode(['success' => false, 'message' => 'A selected PO product is no longer available.']);
                        exit();
                    }
                    $it['item_name'] = $productName;
                }
                $subtotal += $cost * $quantity;
            }
            unset($it);
            $taxRate = floatval(getSetting('tax_rate', 12)) / 100;
            $taxAmount = $subtotal * $taxRate;
            $totalAmount = $subtotal + $taxAmount;
            $po_number = generatePoNumber();

            $status = 'draft';
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO procurement_purchase_orders (po_number, request_id, rfq_id, supplier_id, order_date, expected_delivery, subtotal, tax_amount, total_amount, payment_terms, status, notes, created_by) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $po_number,
                !empty($_POST['request_id']) ? intval($_POST['request_id']) : null,
                !empty($_POST['rfq_id']) ? intval($_POST['rfq_id']) : null,
                $supplier_id,
                !empty($_POST['expected_delivery']) ? $_POST['expected_delivery'] : null,
                $subtotal, $taxAmount, $totalAmount,
                $_POST['payment_terms'] ?? 'Net 30',
                $status,
                $_POST['notes'] ?? '',
                $_SESSION['user_id']
            ]);
            $poId = $pdo->lastInsertId();

            foreach ($items as $it) {
                $qty = max(1, intval($it['quantity'] ?? 1));
                $cost = floatval($it['unit_cost'] ?? 0);
                $pdo->prepare("INSERT INTO procurement_po_items (po_id, product_id, item_name, quantity, quantity_received, quantity_remaining, unit_cost, total_cost) VALUES (?, ?, ?, ?, 0, ?, ?, ?)")
                    ->execute([$poId, !empty($it['product_id']) ? intval($it['product_id']) : null, $it['item_name'] ?? 'Item', $qty, $qty, $cost, $cost * $qty]);
            }

            logActivity('po_created', "PO $po_number created with " . count($items) . " line item(s), total $totalAmount");
            $pdo->commit();
            echo json_encode(['success' => true, 'po_number' => $po_number, 'id' => $poId]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Purchase order creation failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($_GET['action'] == 'update_po_status') {
        if (!canAccess('procurement_manage') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $allowed = ['pending','approved','ordered','sent','acknowledged','partially_received','delivered','received','closed','cancelled','finance_pending','draft'];
            if (!in_array($status, $allowed, true)) { echo json_encode(['success' => false, 'message' => 'Invalid status']); exit(); }
            $currentStmt = $pdo->prepare("SELECT status FROM procurement_purchase_orders WHERE id = ?");
            $currentStmt->execute([$id]);
            $currentStatus = $currentStmt->fetchColumn();
            if ($currentStatus === false) { echo json_encode(['success' => false, 'message' => 'Purchase order not found']); exit(); }
            $transitions = [
                'draft' => ['pending', 'ordered', 'cancelled'],
                'pending' => ['approved', 'finance_pending', 'cancelled'],
                'finance_pending' => ['approved', 'cancelled'],
                'approved' => ['ordered', 'sent', 'acknowledged', 'cancelled'],
                'ordered' => ['sent', 'acknowledged', 'cancelled'],
                'sent' => ['acknowledged', 'partially_received', 'delivered', 'received'],
                'acknowledged' => ['ordered', 'partially_received', 'delivered', 'received'],
                'partially_received' => ['partially_received', 'received'],
                'delivered' => ['closed', 'received'],
            ];
            if (!in_array($status, $transitions[$currentStatus] ?? [], true)) {
                echo json_encode(['success' => false, 'message' => 'This purchase order status transition is not allowed.']);
                exit();
            }
            if ($status === 'approved') {
                if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) { echo json_encode(['success' => false, 'message' => 'Finance approval required']); exit(); }
                $pdo->prepare("UPDATE procurement_purchase_orders SET status = 'approved', finance_approved_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $id]);
            } elseif ($status === 'acknowledged') {
                $pdo->prepare("UPDATE procurement_purchase_orders SET status = 'acknowledged', supplier_ack = 1 WHERE id = ?")->execute([$id]);
            } else {
                $pdo->prepare("UPDATE procurement_purchase_orders SET status = ? WHERE id = ?")->execute([$status, $id]);
            }
            logActivity('po_status', "PO #$id -> $status");
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- GOODS RECEIPT ----
    if ($_GET['action'] == 'get_grns') {
        try {
            $stmt = $pdo->query("SELECT g.*, p.po_number, s.name AS supplier_name FROM procurement_grns g LEFT JOIN procurement_purchase_orders p ON g.po_id = p.id LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY g.created_at DESC");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    if ($_GET['action'] == 'get_po_receivable_items') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $poId = (int)($_GET['po_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT id, item_name, quantity, quantity_received,
                    GREATEST(quantity - quantity_received, 0) AS quantity_remaining
                FROM procurement_po_items
                WHERE po_id = ?
                ORDER BY id
            ");
            $stmt->execute([$poId]);
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            error_log('Could not load receivable PO items: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not load purchase order items.']);
        }
        exit();
    }

    if ($_GET['action'] == 'save_grn') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $po_id = intval($_POST['po_id'] ?? 0);
            if (!$po_id) {
                echo json_encode(['success' => false, 'message' => 'PO required']);
                exit();
            }

            $receivedDate = $_POST['received_date'] ?? date('Y-m-d');
            $dateCheck = DateTime::createFromFormat('Y-m-d', $receivedDate);
            if (!$dateCheck || $dateCheck->format('Y-m-d') !== $receivedDate) {
                echo json_encode(['success' => false, 'message' => 'Enter a valid receiving date.']);
                exit();
            }

            $pdo->beginTransaction();
            $po = $pdo->prepare("SELECT * FROM procurement_purchase_orders WHERE id = ? FOR UPDATE");
            $po->execute([$po_id]);
            $poRow = $po->fetch();
            if (!$poRow) {
                throw new Exception('Purchase order not found.');
            }
            if (!in_array($poRow['status'], ['ordered', 'sent', 'acknowledged', 'partially_received', 'delivered'], true)) {
                throw new Exception('Only an ordered purchase order can receive goods.');
            }

            $receivedItems = json_decode($_POST['received_items'] ?? '[]', true);
            if (!is_array($receivedItems) || empty($receivedItems)) {
                $receivedItems = [[
                    'po_item_id' => intval($_POST['po_item_id'] ?? 0),
                    'quantity' => max(0, intval($_POST['quantity'] ?? 0))
                ]];
            }
            $grn_number = 'GRN-' . date('Ymd') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $totalReceived = 0;

            foreach ($receivedItems as $entry) {
                $poItemId = intval($entry['po_item_id'] ?? 0);
                $receivedQty = max(0, intval($entry['quantity'] ?? 0));
                if ($poItemId <= 0) {
                    throw new Exception('Invalid purchase order item.');
                }
                if ($receivedQty === 0) {
                    continue;
                }

                $itemStmt = $pdo->prepare("SELECT * FROM procurement_po_items WHERE id = ? AND po_id = ? FOR UPDATE");
                $itemStmt->execute([$poItemId, $po_id]);
                $item = $itemStmt->fetch();
                if (!$item) {
                    throw new Exception('A received item does not belong to this purchase order.');
                }

                $remaining = max(0, intval($item['quantity']) - intval($item['quantity_received']));
                if ($receivedQty > $remaining) {
                    throw new Exception('Received quantity for ' . $item['item_name'] . ' cannot exceed its remaining quantity of ' . $remaining . '.');
                }
                $acceptedQty = $receivedQty;
                if ($acceptedQty <= 0) {
                    continue;
                }

                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $beforeStock = 0;
                $newStock = 0;

                if ($productId) {
                    $stockStmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
                    $stockStmt->execute([$productId]);
                    $product = $stockStmt->fetch();
                    if (!$product) {
                        throw new Exception('Product not found for PO item.');
                    }
                    $beforeStock = intval($product['stock_quantity']);
                    $newStock = $beforeStock + $acceptedQty;
                    $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")->execute([$newStock, $productId]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, quantity_before, quantity_after, reason, reference_id, reference_type, user_id) VALUES (?, 'purchase', ?, ?, ?, ?, ?, 'purchase_order', ?)")
                        ->execute([$productId, $acceptedQty, $beforeStock, $newStock, "Received via GRN $grn_number", $po_id, $_SESSION['user_id']]);
                    recordInventoryTransaction($productId, 'PURCHASE', $acceptedQty, $beforeStock, $newStock, 'purchase_order', $po_id, $_SESSION['user_id']);
                }

                $newReceived = intval($item['quantity_received']) + $acceptedQty;
                $newRemaining = max(0, intval($item['quantity']) - $newReceived);
                $pdo->prepare("UPDATE procurement_po_items SET quantity_received = ?, quantity_remaining = ?, received_at = COALESCE(received_at, NOW()), inventory_updated_at = NOW() WHERE id = ?")
                    ->execute([$newReceived, $newRemaining, $poItemId]);

                $totalReceived += $acceptedQty;
            }

            if ($totalReceived <= 0) {
                throw new Exception('No valid receiving quantity was submitted for this PO.');
            }

            $status = 'partial';
            $allReceived = $pdo->prepare("SELECT COUNT(*) as count FROM procurement_po_items WHERE po_id = ? AND quantity_received < quantity");
            $allReceived->execute([$po_id]);
            if ((int) $allReceived->fetch()['count'] === 0) {
                $status = 'received';
            }

            $outstandingStmt = $pdo->prepare("
                SELECT item_name, GREATEST(quantity - quantity_received, 0) AS outstanding
                FROM procurement_po_items
                WHERE po_id = ? AND quantity_received < quantity
            ");
            $outstandingStmt->execute([$po_id]);
            $outstandingItems = [];
            foreach ($outstandingStmt->fetchAll() as $outstandingItem) {
                $outstandingItems[] = $outstandingItem['item_name'] . ': ' . $outstandingItem['outstanding'] . ' outstanding';
            }
            $discrepancies = trim($_POST['discrepancies'] ?? '');
            if ($outstandingItems) {
                $outstandingNote = 'Outstanding quantities: ' . implode('; ', $outstandingItems);
                $discrepancies = $discrepancies === '' ? $outstandingNote : $discrepancies . "\n" . $outstandingNote;
            }
            $pdo->prepare("INSERT INTO procurement_grns (grn_number, po_id, received_date, received_by, discrepancies, status) VALUES (?, ?, ?, ?, ?, ?)")->execute([$grn_number, $po_id, $receivedDate, $_SESSION['user_id'], $discrepancies, $status]);
            $pdo->prepare("UPDATE procurement_purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status === 'received' ? 'received' : 'partially_received', $po_id]);

            logActivity('grn_created', "GRN $grn_number for PO #$po_id");
            $pdo->commit();
            echo json_encode(['success' => true, 'grn_number' => $grn_number, 'status' => $status === 'received' ? 'received' : 'partially_received']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- INVOICES & 3-WAY MATCH ----
    if ($_GET['action'] == 'get_invoices') {
        try {
            $stmt = $pdo->query("SELECT i.*, p.po_number, s.name AS supplier_name FROM procurement_invoices i LEFT JOIN procurement_purchase_orders p ON i.po_id = p.id LEFT JOIN suppliers s ON i.supplier_id = s.id ORDER BY i.created_at DESC LIMIT 200");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    if ($_GET['action'] == 'save_invoice') {
        if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Finance role required to record supplier invoices.']);
            exit();
        }
        try {
            $po_id = intval($_POST['po_id'] ?? 0);
            $invoice_number = trim($_POST['invoice_number'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            if (!$po_id || $invoice_number === '' || $amount <= 0) { echo json_encode(['success' => false, 'message' => 'PO, invoice number and amount required']); exit(); }
            $po = $pdo->prepare("SELECT * FROM procurement_purchase_orders WHERE id = ?"); $po->execute([$po_id]);
            $poRow = $po->fetch();
            if (!$poRow) { echo json_encode(['success' => false, 'message' => 'Purchase order not found']); exit(); }
            $dupe = $pdo->prepare("SELECT id FROM procurement_invoices WHERE invoice_number = ?");
            $dupe->execute([$invoice_number]);
            if ($dupe->fetchColumn()) { echo json_encode(['success' => false, 'message' => 'An invoice with this invoice number already exists.']); exit(); }
            $grn = $pdo->prepare("SELECT * FROM procurement_grns WHERE po_id = ? ORDER BY created_at DESC LIMIT 1"); $grn->execute([$po_id]);
            $grnRow = $grn->fetch();
            // 3-way match: invoice amount vs PO total, GRN exists
            $match = 'pending';
            if ($grnRow && $poRow) {
                $poTotal = floatval($poRow['total_amount']);
                $match = (abs($poTotal - $amount) < 0.51) ? 'matched' : 'mismatch';
            } elseif (!$grnRow) {
                $match = 'mismatch';
            }
            $stmt = $pdo->prepare("INSERT INTO procurement_invoices (invoice_number, po_id, grn_id, supplier_id, invoice_date, amount, match_status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$invoice_number, $po_id, $grnRow ? $grnRow['id'] : null, $poRow ? $poRow['supplier_id'] : null, !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : date('Y-m-d'), $amount, $match, $_POST['notes'] ?? '', $_SESSION['user_id']]);
            logActivity('invoice_recorded', "Invoice $invoice_number (₱" . number_format($amount, 2) . ") recorded for PO #$po_id");
            echo json_encode(['success' => true, 'match_status' => $match]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Could not save the invoice.']); }
        exit();
    }

    if ($_GET['action'] == 'approve_invoice') {
        if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Finance role required.']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE procurement_invoices SET match_status = 'approved' WHERE id = ? AND match_status IN ('matched','approved')")->execute([$id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- PAYMONGO PAYMENT ----
    if ($_GET['action'] == 'pay_invoice_paymongo') {
        if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Finance role required.']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $inv = $pdo->prepare("SELECT * FROM procurement_invoices WHERE id = ?"); $inv->execute([$id]);
            $invoice = $inv->fetch();
            if (!$invoice) { echo json_encode(['success' => false, 'message' => 'Invoice not found']); exit(); }
            if ($invoice['payment_status'] === 'paid') { echo json_encode(['success' => false, 'message' => 'Already paid']); exit(); }
            $secretKey = getSetting('paymongo_secret_key', '');
            $amount = floatval($invoice['amount']);
            if ($secretKey === '') {
                // No key configured: use a realistic simulated PayMongo checkout so the flow works end-to-end
                $linkId = 'plink_sim_' . bin2hex(random_bytes(6));
                $checkoutUrl = '?action=paymongo_sim_checkout&invoice_id=' . $id . '&ref=' . $linkId;
                $pdo->prepare("UPDATE procurement_invoices SET paymongo_link_id = ?, paymongo_checkout_url = ? WHERE id = ?")->execute([$linkId, $checkoutUrl, $id]);
                $pdo->prepare("INSERT INTO procurement_payments (invoice_id, provider, reference, amount, status) VALUES (?, 'paymongo', ?, ?, 'pending')")
                    ->execute([$id, $linkId, $amount]);
                echo json_encode(['success' => true, 'message' => '(Simulated) PayMongo checkout link created. Add a real secret key in Settings to use the live API.', 'checkout_url' => $checkoutUrl]);
                exit();
            }
            $ch = curl_init('https://api.paymongo.com/v1/links');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_USERPWD => $secretKey . ':',
                CURLOPT_POSTFIELDS => json_encode(['data' => ['attributes' => [
                    'amount' => (int) round($amount * 100),
                    'description' => 'Invoice ' . $invoice['invoice_number'],
                    'remarks' => 'Procurement invoice payment'
                ]]])
            ]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = json_decode($resp, true);
            if ($http >= 200 && $http < 300 && !empty($data['data']['id'])) {
                $linkId = $data['data']['id'];
                $checkoutUrl = $data['data']['attributes']['checkout_url'] ?? '';
                $pdo->prepare("UPDATE procurement_invoices SET paymongo_link_id = ?, paymongo_checkout_url = ? WHERE id = ?")->execute([$linkId, $checkoutUrl, $id]);
                $pdo->prepare("INSERT INTO procurement_payments (invoice_id, provider, reference, amount, status) VALUES (?, 'paymongo', ?, ?, 'pending')")->execute([$id, $linkId, $amount]);
                echo json_encode(['success' => true, 'checkout_url' => $checkoutUrl]);
            } else {
                $msg = $data['errors'][0]['detail'] ?? 'PayMongo request failed (HTTP ' . $http . ')';
                echo json_encode(['success' => false, 'message' => $msg]);
            }
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    if ($_GET['action'] == 'mark_invoice_paid') {
        if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Finance role required.']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $pdo->prepare("UPDATE procurement_invoices SET payment_status = 'paid', paid_at = NOW(), paid_amount = amount WHERE id = ?")->execute([$id]);
            $pdo->prepare("UPDATE procurement_payments SET status = 'paid', paid_at = NOW() WHERE invoice_id = ? AND status = 'pending'")->execute([$id]);
            $inv = $pdo->prepare("SELECT po_id FROM procurement_invoices WHERE id = ?"); $inv->execute([$id]);
            $row = $inv->fetch();
            if ($row && $row['po_id']) {
                $pdo->prepare("UPDATE procurement_purchase_orders SET status = 'closed' WHERE id = ? AND status IN ('delivered','acknowledged')")->execute([$row['po_id']]);
            }
            logActivity('invoice_paid', "Invoice #$id marked paid");
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- SIMULATED PAYMONGO CHECKOUT (used when no API key is configured) ----
    if ($_GET['action'] == 'paymongo_sim_checkout') {
        header('Content-Type: text/html; charset=utf-8');
        $id = intval($_GET['invoice_id'] ?? 0);
        $inv = $pdo->prepare("SELECT * FROM procurement_invoices WHERE id = ?"); $inv->execute([$id]);
        $invoice = $inv->fetch();
        if (!$invoice) { echo 'Invoice not found'; exit(); }
        if ($invoice['payment_status'] === 'paid') {
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Already Paid</title>
            <style>body{font-family:Arial;background:#0f172a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center}
            .card{background:#fff;color:#111827;border-radius:16px;padding:40px;max-width:400px}</style></head>
            <body><div class="card"><h1 style="color:#10b981">✓ Already Paid</h1>
            <p>Invoice ' . htmlspecialchars($invoice['invoice_number']) . ' was paid on ' . htmlspecialchars($invoice['paid_at']) . '.</p>
            <p><a href="?page=procurement">Back to Procurement</a></p></div></body></html>';
            exit();
        }
        $amount = number_format(floatval($invoice['amount']), 2);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PayMongo Checkout</title>
        <style>
        *{box-sizing:border-box}
        body{font-family:Inter,-apple-system,"Segoe UI",Arial,sans-serif;background:#0f172a;margin:0;min-height:100vh;display:flex;flex-direction:column}
        .top{background:#0b1220;color:#fff;padding:18px 24px;text-align:center;border-bottom:1px solid #1e293b}
        .top .logo{font-weight:800;font-size:22px;letter-spacing:.5px}
        .top .logo span{color:#38bdf8}
        .wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);padding:28px;width:440px;max-width:94vw}
        .brand{font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:1px}
        .amt{font-size:32px;font-weight:800;color:#111827;margin:6px 0 2px}
        .inv{font-size:13px;color:#6b7280;margin-bottom:18px}
        .label{font-size:13px;font-weight:700;color:#374151;margin:14px 0 8px}
        .methods{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .m{border:1.5px solid #e5e7eb;border-radius:10px;padding:12px;cursor:pointer;display:flex;align-items:center;gap:8px;font-weight:600;color:#374151;font-size:14px}
        .m:hover{border-color:#3b82f6}
        .m.sel{border-color:#3b82f6;background:#eff6ff;color:#1d4ed8}
        .m .dot{width:34px;height:22px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:800}
        .gcash{background:#007dfe}.maya{background:#0aa44f}.grabpay{background:#00b14f}.cardbg{background:#111827}
        .field{margin:8px 0}
        .field input{width:100%;padding:12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px}
        .row{display:flex;gap:8px}.row .field{flex:1}
        button{width:100%;margin-top:18px;padding:14px;border:none;border-radius:10px;background:#3b82f6;color:#fff;font-weight:700;font-size:15px;cursor:pointer}
        button:hover{background:#2563eb}
        .note{font-size:11px;color:#9ca3af;margin-top:12px;text-align:center}
        </style></head><body>
        <div class="top"><div class="logo">Pay<span>Mongo</span></div></div>
        <div class="wrap"><div class="card">
        <div class="brand">' . htmlspecialchars(getSetting('store_name', 'Store')) . '</div>
        <div class="amt">₱' . $amount . '</div>
        <div class="inv">Invoice ' . htmlspecialchars($invoice['invoice_number']) . ' · Procurement payment</div>
        <form method="POST" action="?action=paymongo_sim_pay">
        <input type="hidden" name="invoice_id" value="' . $id . '">
        <div class="label">Select payment method</div>
        <div class="methods">
          <div class="m sel" onclick="sel(this,\'gcash\')"><span class="dot gcash">G</span>GCash</div>
          <div class="m" onclick="sel(this,\'maya\')"><span class="dot maya">M</span>Maya</div>
          <div class="m" onclick="sel(this,\'grabpay\')"><span class="dot grabpay">GP</span>GrabPay</div>
          <div class="m" onclick="sel(this,\'card\')"><span class="dot cardbg">CARD</span>Card</div>
        </div>
        <div id="cardFields" style="display:none">
          <div class="field"><input placeholder="Card number (4242 4242 4242 4242)"></div>
          <div class="row">
            <div class="field"><input placeholder="MM / YY"></div>
            <div class="field"><input placeholder="CVC"></div>
          </div>
        </div>
        <div id="ewalletFields">
          <div class="field"><input placeholder="09XX XXX XXXX"></div>
        </div>
        <input type="hidden" name="method" id="method" value="gcash">
        <button type="submit">Pay ₱' . $amount . '</button>
        </form>
        <p class="note">Test checkout · No real charge · Simulated PayMongo page</p>
        </div></div>
        <script>function sel(el,m){document.querySelectorAll(".m").forEach(function(x){x.classList.remove("sel")});el.classList.add("sel");document.getElementById("method").value=m;
        document.getElementById("cardFields").style.display = (m==="card") ? "block" : "none";
        document.getElementById("ewalletFields").style.display = (m==="card") ? "none" : "block";}</script>
        </body></html>';
        exit();
    }

    if ($_GET['action'] == 'paymongo_sim_pay') {
        header('Content-Type: text/html; charset=utf-8');
        $id = intval($_POST['invoice_id'] ?? 0);
        $method = $_POST['method'] ?? 'gcash';
        $pdo->prepare("UPDATE procurement_invoices SET payment_status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$id]);
        $pdo->prepare("UPDATE procurement_payments SET status = 'paid', paid_at = NOW(), provider = ? WHERE invoice_id = ? AND status = 'pending'")->execute(['paymongo_' . $method, $id]);
        $inv = $pdo->prepare("SELECT po_id FROM procurement_invoices WHERE id = ?"); $inv->execute([$id]);
        $poRow = $inv->fetch();
        if ($poRow && $poRow['po_id']) {
            $pdo->prepare("UPDATE procurement_purchase_orders SET status = 'closed' WHERE id = ? AND status IN ('delivered','acknowledged')")->execute([$poRow['po_id']]);
        }
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Payment Successful</title></head><body style="font-family:Arial;text-align:center;padding-top:80px">
        <h1 style="color:#10b981">Payment Successful</h1>
        <p>Paid via ' . htmlspecialchars(strtoupper($method)) . ' (simulated).</p>
        <p><a href="?page=procurement">Back to Procurement</a></p></body></html>';
        exit();
    }

    // ---- SUPPLIER PAYMENT (real-world: bank transfer / check) ----
    if ($_GET['action'] == 'save_supplier_payment') {
        if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Finance role required.']);
            exit();
        }
        try {
            $id = intval($_POST['invoice_id'] ?? 0);
            $method = $_POST['method'] ?? 'bank_transfer';
            if (!in_array($method, ['bank_transfer', 'check', 'cash'], true)) $method = 'bank_transfer';
            $inv = $pdo->prepare("SELECT * FROM procurement_invoices WHERE id = ?"); $inv->execute([$id]);
            $invoice = $inv->fetch();
            if (!$invoice) { echo json_encode(['success' => false, 'message' => 'Invoice not found']); exit(); }
            if ($invoice['payment_status'] === 'paid') { echo json_encode(['success' => false, 'message' => 'Already paid']); exit(); }
            $reference = trim($_POST['reference'] ?? '');
            if ($reference !== '') {
                $dupe = $pdo->prepare("SELECT id FROM procurement_payments WHERE invoice_id = ? AND reference = ?");
                $dupe->execute([$id, $reference]);
                if ($dupe->fetchColumn()) { echo json_encode(['success' => false, 'message' => 'A payment with this reference has already been recorded for this invoice.']); exit(); }
            }
            $stmt = $pdo->prepare("INSERT INTO procurement_payments (invoice_id, provider, reference, amount, status, payment_date, created_by) VALUES (?, ?, ?, ?, 'pending', ?, ?)");
            $stmt->execute([$id, $method, $reference !== '' ? $reference : null, floatval($invoice['amount']), date('Y-m-d'), $_SESSION['user_id']]);
            logActivity('supplier_payment', "$method payment recorded for invoice #$id, ref: " . ($_POST['reference'] ?? ''));
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => (int) $e->getCode() === 23000 ? 'A payment with this reference already exists for this invoice.' : 'Could not record the payment.']);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    if ($_GET['action'] == 'confirm_payment') {
        if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Finance role required.']);
            exit();
        }
        try {
            $id = intval($_POST['invoice_id'] ?? 0);
            $pdo->prepare("UPDATE procurement_invoices SET payment_status = 'paid', paid_at = NOW(), paid_amount = amount WHERE id = ? AND payment_status <> 'paid'")->execute([$id]);
            $pdo->prepare("UPDATE procurement_payments SET status = 'paid', paid_at = NOW() WHERE invoice_id = ? AND status = 'pending'")->execute([$id]);
            $inv = $pdo->prepare("SELECT po_id FROM procurement_invoices WHERE id = ?"); $inv->execute([$id]);
            $row = $inv->fetch();
            if ($row && $row['po_id']) {
                $pdo->prepare("UPDATE procurement_purchase_orders SET status = 'closed' WHERE id = ? AND status IN ('delivered','acknowledged')")->execute([$row['po_id']]);
            }
            logActivity('payment_confirmed', "Payment confirmed for invoice #$id");
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- PURCHASE REQUEST WORKFLOW (multi-product) ----

    // ---- GET PURCHASE REQUEST DETAILS (items + approval history + linked PO) ----
    if ($_GET['action'] == 'pr_get') {
        if (!canAccess('procurement') && !hasPermission('procurement_approve')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT pr.*, s.name AS supplier_name, s.email AS supplier_email,
                    u.full_name AS requester_name, u.username AS requester_username,
                    d.full_name AS decided_by_name
                FROM purchase_requests pr
                LEFT JOIN suppliers s ON pr.supplier_id = s.id
                LEFT JOIN users u ON pr.requester_id = u.id
                LEFT JOIN users d ON pr.decided_by = d.id
                WHERE pr.id = ?
            ");
            $stmt->execute([$id]);
            $request = $stmt->fetch();
            if (!$request) {
                echo json_encode(['success' => false, 'message' => 'Purchase request not found.']);
                exit();
            }
            // Ownership / permitted access check
            $isApprover = hasPermission('procurement_approve');
            $isManager = canAccess('procurement_manage') || isAdmin();
            $isOwner = (int) $request['requester_id'] === (int) ($_SESSION['user_id'] ?? 0);
            if (!$isApprover && !$isManager && !$isOwner && $request['status'] !== 'approved') {
                echo json_encode(['success' => false, 'message' => 'You are not allowed to view this purchase request.']);
                exit();
            }

            $itemsStmt = $pdo->prepare("
                SELECT i.*, p.stock_quantity, p.low_stock_threshold, p.unit AS catalog_unit
                FROM purchase_request_items i
                LEFT JOIN products p ON p.id = i.product_id
                WHERE i.request_id = ?
                ORDER BY i.id
            ");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll();

            $logStmt = $pdo->prepare("
                SELECT l.*, u.full_name AS actor_name
                FROM procurement_approval_logs l
                LEFT JOIN users u ON l.actor_id = u.id
                WHERE l.request_id = ?
                ORDER BY l.created_at DESC, l.id DESC
            ");
            $logStmt->execute([$id]);
            $logs = $logStmt->fetchAll();

            $poStmt = $pdo->prepare("
                SELECT id, po_number, status, total_amount, created_at
                FROM procurement_purchase_orders
                WHERE purchase_request_id = ?
                ORDER BY id DESC LIMIT 1
            ");
            $poStmt->execute([$id]);
            $po = $poStmt->fetch() ?: null;

            echo json_encode([
                'success' => true,
                'request' => $request,
                'items' => $items,
                'logs' => $logs,
                'po' => $po,
                'warnings' => computePurchaseRequestWarnings($items, $id),
                'can_decide' => $isApprover && $request['status'] === 'pending_approval' && !$isOwner,
                'can_edit' => $isOwner && in_array($request['status'], ['draft', 'revision_requested'], true),
            ]);
        } catch (Exception $e) {
            error_log('pr_get failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not load the purchase request.']);
        }
        exit();
    }

    // ---- CREATE / UPDATE PURCHASE REQUEST (draft) ----
    if ($_GET['action'] == 'pr_save') {
        if (!canAccess('procurement')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $payload = [];
            $errors = validatePurchaseRequestPayload($_POST, $payload);
            if (count($errors) > 0) {
                echo json_encode(['success' => false, 'message' => implode(' ', array_slice($errors, 0, 3)), 'errors' => $errors]);
                exit();
            }

            // Optional supplier quotation attachment (same security rules as product images)
            $attachmentPath = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
                    echo json_encode(['success' => false, 'message' => 'Attachment is too large. Maximum 5MB allowed.']);
                    exit();
                }
                $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['attachment']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mimeType, $allowedTypes, true)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid attachment type. Please upload a PDF, JPEG, or PNG file.']);
                    exit();
                }
                $extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid attachment extension.']);
                    exit();
                }
                $uploadDir = 'uploads/quotations/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $fileName = time() . '_' . uniqid() . '.' . $extension;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
                    $attachmentPath = $uploadDir . $fileName;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to store the attachment file.']);
                    exit();
                }
            }

            $pdo->beginTransaction();
            $warnings = computePurchaseRequestWarnings($payload['items'], $id);
            $duplicateFlag = count(array_filter($warnings, function ($w) { return stripos($w, 'duplicate') !== false; })) > 0 ? 1 : 0;
            if ($id > 0) {
                $rowStmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ? FOR UPDATE");
                $rowStmt->execute([$id]);
                $existing = $rowStmt->fetch();
                if (!$existing) {
                    throw new Exception('Purchase request not found.');
                }
                $isOwner = (int) $existing['requester_id'] === (int) $_SESSION['user_id'];
                if (!$isOwner && !isAdmin()) {
                    throw new Exception('You can only edit your own purchase requests.');
                }
                if (!in_array($existing['status'], ['draft', 'revision_requested'], true)) {
                    throw new Exception('This request has already been submitted and can no longer be edited.');
                }
                $stmt = $pdo->prepare("
                    UPDATE purchase_requests
                    SET supplier_id = ?, request_date = ?, reason = ?, remarks = ?,
                        subtotal = ?, grand_total = ?, over_limit = ?, duplicate_flag = ?, duplicate_notes = ?
                    WHERE id = ? AND status IN ('draft','revision_requested')
                ");
                $stmt->execute([
                    $payload['supplier_id'], $payload['request_date'], $payload['reason'], $payload['remarks'],
                    $payload['subtotal'], $payload['grand_total'], $payload['over_limit'],
                    $duplicateFlag, implode("\n", $warnings), $id
                ]);
                $check = $pdo->prepare("SELECT status FROM purchase_requests WHERE id = ?");
                $check->execute([$id]);
                if (!in_array($check->fetchColumn(), ['draft', 'revision_requested'], true)) {
                    throw new Exception('This request has already been submitted and can no longer be edited.');
                }
                if ($attachmentPath !== null) {
                    $pdo->prepare("UPDATE purchase_requests SET attachment_path = ? WHERE id = ?")->execute([$attachmentPath, $id]);
                }
                $pdo->prepare("DELETE FROM purchase_request_items WHERE request_id = ?")->execute([$id]);
                $logAction = 'edited';
                $logComment = 'Purchase request updated.';
            } else {
                $requestNumber = generatePurchaseRequestNumber();
                $stmt = $pdo->prepare("
                    INSERT INTO purchase_requests
                    (request_number, supplier_id, request_date, requester_id, reason, remarks, attachment_path,
                     status, subtotal, grand_total, over_limit, duplicate_flag, duplicate_notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $requestNumber, $payload['supplier_id'], $payload['request_date'], $_SESSION['user_id'],
                    $payload['reason'], $payload['remarks'], $attachmentPath,
                    $payload['subtotal'], $payload['grand_total'], $payload['over_limit'],
                    $duplicateFlag, implode("\n", $warnings)
                ]);
                $id = intval($pdo->lastInsertId());
                $logAction = 'created';
                $logComment = 'Purchase request created as draft.';
            }

            $itemStmt = $pdo->prepare("
                INSERT INTO purchase_request_items
                (request_id, product_id, item_name, unit, quantity, estimated_unit_price, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($payload['items'] as $item) {
                $itemStmt->execute([
                    $id, $item['product_id'], $item['item_name'], $item['unit'],
                    $item['quantity'], $item['estimated_unit_price'], $item['line_total']
                ]);
            }

            $pdo->prepare("
                INSERT INTO procurement_approval_logs (request_id, action, previous_status, new_status, actor_id, actor_role, comments)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$id, $logAction, 'draft', 'draft', $_SESSION['user_id'], $_SESSION['role'] ?? '', $logComment]);

            $pdo->commit();
            logActivity('purchase_request_saved', "Purchase request #$id saved as draft");
            $numberStmt = $pdo->prepare("SELECT request_number FROM purchase_requests WHERE id = ?");
            $numberStmt->execute([$id]);
            echo json_encode([
                'success' => true,
                'id' => $id,
                'request_number' => $numberStmt->fetchColumn(),
                'status' => 'draft',
                'warnings' => $warnings
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('pr_save failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => (int) $e->getCode() === 23000 ? 'That request number already exists. Please try again.' : 'Could not save the purchase request.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- SUBMIT PURCHASE REQUEST FOR APPROVAL ----
    if ($_GET['action'] == 'pr_submit') {
        if (!canAccess('procurement')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('Purchase request not found.');
            }
            $pdo->beginTransaction();
            $rowStmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ? FOR UPDATE");
            $rowStmt->execute([$id]);
            $request = $rowStmt->fetch();
            if (!$request) {
                throw new Exception('Purchase request not found.');
            }
            $isOwner = (int) $request['requester_id'] === (int) $_SESSION['user_id'];
            if (!$isOwner && !isAdmin()) {
                throw new Exception('You can only submit your own purchase requests.');
            }
            if (!in_array($request['status'], ['draft', 'revision_requested'], true)) {
                throw new Exception('Only drafts or requests marked "Revision Requested" can be submitted.');
            }

            // Full server-side re-validation at submit time
            $itemsStmt = $pdo->prepare("SELECT * FROM purchase_request_items WHERE request_id = ? ORDER BY id");
            $itemsStmt->execute([$id]);
            $storedItems = $itemsStmt->fetchAll();
            if (count($storedItems) === 0) {
                throw new Exception('Add at least one product to the purchase request.');
            }
            $revalidatePost = [
                'supplier_id' => $request['supplier_id'],
                'request_date' => $request['request_date'],
                'reason' => $request['reason'],
                'remarks' => $request['remarks'],
                'items' => array_map(function ($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'estimated_unit_price' => $item['estimated_unit_price'],
                    ];
                }, $storedItems),
            ];
            $payload = [];
            $errors = validatePurchaseRequestPayload($revalidatePost, $payload);
            if (count($errors) > 0) {
                throw new Exception(implode(' ', $errors));
            }

            $warnings = computePurchaseRequestWarnings($payload['items'], $id);
            $duplicateFlag = count(array_filter($warnings, function ($w) { return stripos($w, 'duplicate') !== false; })) > 0 ? 1 : 0;

            $newStatus = 'pending_approval';
            $stmt = $pdo->prepare("
                UPDATE purchase_requests
                SET status = ?, submitted_at = NOW(), subtotal = ?, grand_total = ?, over_limit = ?,
                    duplicate_flag = ?, duplicate_notes = ?
                WHERE id = ? AND status IN ('draft','revision_requested')
            ");
            $stmt->execute([
                $newStatus, $payload['subtotal'], $payload['grand_total'], $payload['over_limit'],
                $duplicateFlag, implode("\n", $warnings), $id
            ]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('This request was changed by another user. Refresh and try again.');
            }

            $previousStatus = $request['status'];
            $logAction = $previousStatus === 'revision_requested' ? 'resubmitted' : 'submitted';
            $pdo->prepare("
                INSERT INTO procurement_approval_logs (request_id, action, previous_status, new_status, actor_id, actor_role, comments)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$id, $logAction, $previousStatus, $newStatus, $_SESSION['user_id'], $_SESSION['role'] ?? '', 'Submitted for admin approval.']);

            notifyProcurement('Purchase request pending approval',
                $request['request_number'] . ' (' . number_format($payload['grand_total'], 2) . ') is waiting for admin approval.',
                $id, $storedItems[0]['product_id'] ?? null);

            $pdo->commit();
            logActivity('purchase_request_submitted', "Purchase request {$request['request_number']} submitted for approval");
            echo json_encode([
                'success' => true,
                'id' => $id,
                'request_number' => $request['request_number'],
                'status' => $newStatus,
                'warnings' => $warnings
            ]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- CANCEL PURCHASE REQUEST ----
    if ($_GET['action'] == 'pr_cancel') {
        if (!canAccess('procurement')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            $pdo->beginTransaction();
            $rowStmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ? FOR UPDATE");
            $rowStmt->execute([$id]);
            $request = $rowStmt->fetch();
            if (!$request) {
                throw new Exception('Purchase request not found.');
            }
            $isOwner = (int) $request['requester_id'] === (int) $_SESSION['user_id'];
            if (!$isOwner && !isAdmin()) {
                throw new Exception('You can only cancel your own purchase requests.');
            }
            if (!in_array($request['status'], ['draft', 'pending_approval', 'revision_requested'], true)) {
                throw new Exception('Only draft, pending, or revision-requested purchases can be cancelled.');
            }
            $stmt = $pdo->prepare("UPDATE purchase_requests SET status = 'cancelled' WHERE id = ? AND status IN ('draft','pending_approval','revision_requested')");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('This request can no longer be cancelled.');
            }
            $pdo->prepare("
                INSERT INTO procurement_approval_logs (request_id, action, previous_status, new_status, actor_id, actor_role, comments)
                VALUES (?, 'cancelled', ?, 'cancelled', ?, ?, ?)
            ")->execute([$id, $request['status'], $_SESSION['user_id'], $_SESSION['role'] ?? '', $comment !== '' ? $comment : 'Cancelled by requester.']);
            $pdo->commit();
            logActivity('purchase_request_cancelled', "Purchase request {$request['request_number']} cancelled");
            echo json_encode(['success' => true, 'id' => $id, 'status' => 'cancelled']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- ADMIN DECISION: APPROVE / REJECT / REQUEST REVISION ----
    if ($_GET['action'] == 'pr_decide') {
        if (!hasPermission('procurement_approve')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: only admin accounts with approval permission can decide on purchase requests.']);
            exit();
        }
        try {
            $id = intval($_POST['id'] ?? 0);
            $decision = $_POST['decision'] ?? '';
            $comments = trim($_POST['comments'] ?? '');
            if (!in_array($decision, ['approve', 'reject', 'revision'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid decision.']);
                exit();
            }
            if ($decision === 'reject' && $comments === '') {
                echo json_encode(['success' => false, 'message' => 'A rejection reason is required.']);
                exit();
            }
            if ($decision === 'revision' && $comments === '') {
                echo json_encode(['success' => false, 'message' => 'A revision comment is required.']);
                exit();
            }

            $pdo->beginTransaction();
            $rowStmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ? FOR UPDATE");
            $rowStmt->execute([$id]);
            $request = $rowStmt->fetch();
            if (!$request) {
                throw new Exception('Purchase request not found.');
            }
            // Self-approval prevention (backend enforced)
            if ((int) $request['requester_id'] === (int) $_SESSION['user_id']) {
                throw new Exception('You cannot approve or reject your own purchase request.');
            }
            // Duplicate / concurrent decision prevention
            if ($request['status'] !== 'pending_approval') {
                throw new Exception('This purchase request has already been processed (current status: ' . ucfirst(str_replace('_', ' ', $request['status'])) . ').');
            }

            if ($decision === 'approve') {
                // Approve only requests that pass validation
                $itemsStmt = $pdo->prepare("SELECT * FROM purchase_request_items WHERE request_id = ?");
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll();
                if (count($items) === 0) {
                    throw new Exception('Cannot approve: this request has no product lines.');
                }
                $supplierStmt = $pdo->prepare("SELECT status FROM suppliers WHERE id = ?");
                $supplierStmt->execute([$request['supplier_id']]);
                $supplierStatus = $supplierStmt->fetchColumn();
                if (!$supplierStatus || $supplierStatus !== 'active') {
                    throw new Exception('Cannot approve: the selected supplier is missing or inactive.');
                }
                if (floatval($request['grand_total']) <= 0) {
                    throw new Exception('Cannot approve: the estimated total must be greater than zero.');
                }
                $newStatus = 'approved';
                $logAction = 'approved';
            } elseif ($decision === 'reject') {
                $newStatus = 'rejected';
                $logAction = 'rejected';
            } else {
                $newStatus = 'revision_requested';
                $logAction = 'revision_requested';
            }

            $stmt = $pdo->prepare("
                UPDATE purchase_requests
                SET status = ?, decided_by = ?, decided_at = NOW(), admin_comment = ?
                WHERE id = ? AND status = 'pending_approval'
            ");
            $stmt->execute([$newStatus, $_SESSION['user_id'], $comments !== '' ? $comments : null, $id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('This purchase request was just processed by another user.');
            }

            $pdo->prepare("
                INSERT INTO procurement_approval_logs (request_id, action, previous_status, new_status, actor_id, actor_role, comments)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$id, $logAction, 'pending_approval', $newStatus, $_SESSION['user_id'], $_SESSION['role'] ?? '', $comments]);

            $feedback = [
                'approved' => 'has been approved.',
                'rejected' => 'was rejected' . ($comments !== '' ? ': ' . $comments : '.'),
                'revision_requested' => 'needs revisions' . ($comments !== '' ? ': ' . $comments : '.'),
            ];
            notifyProcurement('Purchase request ' . ucfirst(str_replace('_', ' ', $newStatus)),
                $request['request_number'] . ' ' . $feedback[$newStatus],
                $id);

            $pdo->commit();
            logActivity('purchase_request_' . $logAction, "Purchase request {$request['request_number']} {$logAction} by " . ($_SESSION['username'] ?? ''));
            echo json_encode(['success' => true, 'id' => $id, 'status' => $newStatus]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- CREATE PURCHASE ORDER FROM AN APPROVED REQUEST ----
    if ($_GET['action'] == 'po_from_request') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $requestId = intval($_POST['request_id'] ?? 0);
            if ($requestId <= 0) {
                throw new Exception('Purchase request not found.');
            }
            $pdo->beginTransaction();
            $reqStmt = $pdo->prepare("SELECT * FROM purchase_requests WHERE id = ? FOR UPDATE");
            $reqStmt->execute([$requestId]);
            $request = $reqStmt->fetch();
            if (!$request) {
                throw new Exception('Purchase request not found.');
            }
            if ($request['status'] !== 'approved') {
                throw new Exception('A purchase order can only be created from an approved purchase request.');
            }
            $existingPo = $pdo->prepare("SELECT po_number FROM procurement_purchase_orders WHERE purchase_request_id = ?");
            $existingPo->execute([$requestId]);
            $existingPoNumber = $existingPo->fetchColumn();
            if ($existingPoNumber) {
                throw new Exception('Purchase order ' . $existingPoNumber . ' has already been created for this request.');
            }
            $supplierStmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = ? AND status = 'active'");
            $supplierStmt->execute([$request['supplier_id']]);
            if (!$supplierStmt->fetchColumn()) {
                throw new Exception('The supplier for this request is no longer active.');
            }

            $itemsStmt = $pdo->prepare("SELECT * FROM purchase_request_items WHERE request_id = ? ORDER BY id");
            $itemsStmt->execute([$requestId]);
            $items = $itemsStmt->fetchAll();
            if (count($items) === 0) {
                throw new Exception('The approved request has no product lines.');
            }

            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += floatval($item['line_total']);
            }
            $subtotal = round($subtotal, 2);
            $taxAmount = round($subtotal * (floatval(getSetting('tax_rate', 12)) / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);
            $poNumber = generatePoNumber();
            $expectedDelivery = !empty($_POST['expected_delivery']) ? $_POST['expected_delivery'] : null;
            if ($expectedDelivery !== null) {
                $dateCheck = DateTime::createFromFormat('Y-m-d', $expectedDelivery);
                if (!$dateCheck || $dateCheck->format('Y-m-d') !== $expectedDelivery) {
                    throw new Exception('Enter a valid expected delivery date.');
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO procurement_purchase_orders
                (po_number, purchase_request_id, supplier_id, order_date, expected_delivery, subtotal, tax_amount,
                 total_amount, payment_terms, status, notes, created_by)
                VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'draft', ?, ?)
            ");
            $stmt->execute([
                $poNumber, $requestId, $request['supplier_id'], $expectedDelivery,
                $subtotal, $taxAmount, $totalAmount,
                $_POST['payment_terms'] ?? 'Net 30',
                trim($_POST['notes'] ?? '') !== '' ? $_POST['notes'] : ('Generated from approved request ' . $request['request_number']),
                $_SESSION['user_id']
            ]);
            $poId = intval($pdo->lastInsertId());

            $itemInsert = $pdo->prepare("
                INSERT INTO procurement_po_items (po_id, product_id, item_name, quantity, quantity_remaining, unit_cost, total_cost)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $itemInsert->execute([
                    $poId, $item['product_id'], $item['item_name'], $item['quantity'], $item['quantity'],
                    $item['estimated_unit_price'], $item['line_total']
                ]);
            }

            $pdo->prepare("
                INSERT INTO procurement_approval_logs (request_id, action, previous_status, new_status, actor_id, actor_role, comments)
                VALUES (?, 'po_created', 'approved', 'approved', ?, ?, ?)
            ")->execute([$requestId, $_SESSION['user_id'], $_SESSION['role'] ?? '', 'Purchase order ' . $poNumber . ' created (draft).']);

            $pdo->commit();
            logActivity('po_created', "PO $poNumber created from {$request['request_number']}");
            echo json_encode(['success' => true, 'po_id' => $poId, 'po_number' => $poNumber, 'status' => 'draft']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- ISSUE / MARK PURCHASE ORDER AS ORDERED ----
    if ($_GET['action'] == 'po_issue') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $poId = intval($_POST['po_id'] ?? 0);
            if ($poId <= 0) {
                throw new Exception('Purchase order not found.');
            }
            $pdo->beginTransaction();
            $poStmt = $pdo->prepare("SELECT * FROM procurement_purchase_orders WHERE id = ? FOR UPDATE");
            $poStmt->execute([$poId]);
            $po = $poStmt->fetch();
            if (!$po) {
                throw new Exception('Purchase order not found.');
            }
            if ($po['status'] !== 'draft') {
                throw new Exception('Only a draft purchase order can be issued. Current status: ' . ucfirst(str_replace('_', ' ', $po['status'])) . '.');
            }
            $stmt = $pdo->prepare("
                UPDATE procurement_purchase_orders
                SET status = 'ordered', ordered_at = NOW(), issued_by = ?
                WHERE id = ? AND status = 'draft'
            ");
            $stmt->execute([$_SESSION['user_id'], $poId]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('This purchase order was just updated by another user.');
            }
            if (!empty($po['purchase_request_id'])) {
                $pdo->prepare("
                    INSERT INTO procurement_approval_logs (request_id, action, previous_status, new_status, actor_id, actor_role, comments)
                    VALUES (?, 'po_ordered', 'approved', 'approved', ?, ?, ?)
                ")->execute([$po['purchase_request_id'], $_SESSION['user_id'], $_SESSION['role'] ?? '', 'Purchase order ' . $po['po_number'] . ' issued (Ordered).']);
            }
            $pdo->commit();
            logActivity('po_issued', "PO {$po['po_number']} marked Ordered");
            echo json_encode(['success' => true, 'po_id' => $poId, 'status' => 'ordered']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- RECEIVE DELIVERY (goods receipt + stock update in one transaction) ----
    if ($_GET['action'] == 'receipt_save') {
        if (!canAccess('procurement_manage') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $poId = intval($_POST['po_id'] ?? 0);
            if ($poId <= 0) {
                throw new Exception('Purchase order not found.');
            }
            $receivedDate = $_POST['received_date'] ?? date('Y-m-d');
            $dateCheck = DateTime::createFromFormat('Y-m-d', $receivedDate);
            if (!$dateCheck || $dateCheck->format('Y-m-d') !== $receivedDate) {
                throw new Exception('Enter a valid delivery date (YYYY-MM-DD).');
            }
            $deliveryReference = trim($_POST['delivery_reference'] ?? '');
            $invoiceReference = trim($_POST['invoice_reference'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $clientToken = trim($_POST['client_token'] ?? '');

            $receivedItems = json_decode($_POST['received_items'] ?? '[]', true);
            if (!is_array($receivedItems) || count($receivedItems) === 0) {
                throw new Exception('Enter the delivered quantities for at least one item.');
            }

            $pdo->beginTransaction();
            $poStmt = $pdo->prepare("SELECT * FROM procurement_purchase_orders WHERE id = ? FOR UPDATE");
            $poStmt->execute([$poId]);
            $po = $poStmt->fetch();
            if (!$po) {
                throw new Exception('Purchase order not found.');
            }
            if (!in_array($po['status'], ['ordered', 'sent', 'acknowledged', 'partially_received'], true)) {
                throw new Exception('Only an issued (Ordered) purchase order can receive goods. Current status: ' . ucfirst(str_replace('_', ' ', $po['status'])) . '.');
            }

            // Idempotency guard: the same submission token can only ever post once
            if ($clientToken !== '') {
                $tokenStmt = $pdo->prepare("SELECT id, grn_number FROM procurement_grns WHERE client_token = ?");
                $tokenStmt->execute([$clientToken]);
                $existingGrn = $tokenStmt->fetch();
                if ($existingGrn) {
                    throw new Exception('This delivery was already recorded as receipt ' . $existingGrn['grn_number'] . '.');
                }
            }

            $grnNumber = generateGoodsReceiptNumber();
            $totalAccepted = 0;
            $grnItemRows = [];
            $grnItemStmt = $pdo->prepare("
                INSERT INTO procurement_grn_items
                (grn_id, po_item_id, product_id, item_name, ordered_qty, previously_received,
                 delivered_qty, accepted_qty, damaged_qty, missing_qty, batch_number, expiry_date, remarks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($receivedItems as $entry) {
                $poItemId = intval($entry['po_item_id'] ?? 0);
                $delivered = filter_var($entry['delivered'] ?? null, FILTER_VALIDATE_INT);
                $accepted = filter_var($entry['accepted'] ?? null, FILTER_VALIDATE_INT);
                $damaged = filter_var($entry['damaged'] ?? null, FILTER_VALIDATE_INT);
                if ($poItemId <= 0) {
                    throw new Exception('Invalid purchase order item.');
                }
                if ($delivered === false || $delivered === null || $delivered < 0 ||
                    $accepted === false || $accepted === null || $accepted < 0 ||
                    $damaged === false || $damaged === null || $damaged < 0) {
                    throw new Exception('Delivered, accepted, and damaged quantities must be zero or positive numbers.');
                }
                if ($delivered === 0 && $accepted === 0 && $damaged === 0) {
                    continue;
                }
                if ($accepted + $damaged > $delivered) {
                    throw new Exception('Accepted plus damaged quantity cannot exceed the delivered quantity.');
                }

                $itemStmt = $pdo->prepare("SELECT * FROM procurement_po_items WHERE id = ? AND po_id = ? FOR UPDATE");
                $itemStmt->execute([$poItemId, $poId]);
                $item = $itemStmt->fetch();
                if (!$item) {
                    throw new Exception('A received item does not belong to this purchase order.');
                }
                $remaining = max(0, intval($item['quantity']) - intval($item['quantity_received']));
                if ($delivered > $remaining) {
                    throw new Exception('Delivered quantity for ' . $item['item_name'] . ' (' . $delivered . ') exceeds its remaining order quantity of ' . $remaining . '.');
                }

                $productId = !empty($item['product_id']) ? intval($item['product_id']) : null;
                $missing = $delivered - $accepted - $damaged;

                // Stock increases only for accepted quantities, using the existing stock movement mechanism
                if ($productId && $accepted > 0) {
                    $stockStmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
                    $stockStmt->execute([$productId]);
                    $product = $stockStmt->fetch();
                    if (!$product) {
                        throw new Exception('Linked product for ' . $item['item_name'] . ' was not found.');
                    }
                    $beforeStock = (int) $product['stock_quantity'];
                    $afterStock = $beforeStock + $accepted;
                    $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")->execute([$afterStock, $productId]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, quantity_before, quantity_after, reason, reference_id, reference_type, user_id) VALUES (?, 'purchase', ?, ?, ?, ?, ?, 'purchase_order', ?)")
                        ->execute([$productId, $accepted, $beforeStock, $afterStock, 'Received via ' . $po['po_number'] . ' (GRN ' . $grnNumber . ')', $poId, $_SESSION['user_id']]);
                    recordInventoryTransaction($productId, 'PURCHASE', $accepted, $beforeStock, $afterStock, 'purchase_order', $poId, $_SESSION['user_id']);
                }

                $newReceived = intval($item['quantity_received']) + $accepted;
                $newRemaining = max(0, intval($item['quantity']) - $newReceived);
                $pdo->prepare("UPDATE procurement_po_items SET quantity_received = ?, quantity_remaining = ?, received_at = COALESCE(received_at, NOW()), inventory_updated_at = NOW() WHERE id = ?")
                    ->execute([$newReceived, $newRemaining, $poItemId]);

                $grnItemRows[] = [
                    'po_item_id' => $poItemId,
                    'product_id' => $productId,
                    'item_name' => $item['item_name'],
                    'ordered' => intval($item['quantity']),
                    'previous' => intval($item['quantity_received']),
                    'delivered' => $delivered,
                    'accepted' => $accepted,
                    'damaged' => $damaged,
                    'missing' => $missing,
                    'batch' => trim((string) ($entry['batch_number'] ?? '')) ?: null,
                    'expiry' => trim((string) ($entry['expiry_date'] ?? '')) ?: null,
                    'remarks' => trim((string) ($entry['remarks'] ?? '')) ?: null,
                ];
                $totalAccepted += $accepted;
            }

            if (count($grnItemRows) === 0) {
                throw new Exception('Enter the delivered quantities for at least one item.');
            }
            // Receipt header
            $grnStmt = $pdo->prepare("
                INSERT INTO procurement_grns (grn_number, po_id, received_date, received_by, discrepancies, status, delivery_reference, invoice_reference, remarks, client_token)
                VALUES (?, ?, ?, ?, ?, 'partial', ?, ?, ?, ?)
            ");
            $grnStmt->execute([
                $grnNumber, $poId, $receivedDate, $_SESSION['user_id'], $remarks,
                $deliveryReference !== '' ? $deliveryReference : null,
                $invoiceReference !== '' ? $invoiceReference : null,
                $remarks !== '' ? $remarks : null,
                $clientToken !== '' ? $clientToken : null
            ]);
            $grnId = intval($pdo->lastInsertId());

            foreach ($grnItemRows as $row) {
                $grnItemStmt->execute([
                    $grnId, $row['po_item_id'], $row['product_id'], $row['item_name'],
                    $row['ordered'], $row['previous'], $row['delivered'], $row['accepted'],
                    $row['damaged'], $row['missing'], $row['batch'], $row['expiry'], $row['remarks']
                ]);
            }

            // Update PO status: Partially Received vs Received (accepted quantities only)
            $outstandingStmt = $pdo->prepare("SELECT COUNT(*) FROM procurement_po_items WHERE po_id = ? AND quantity_received < quantity");
            $outstandingStmt->execute([$poId]);
            $fullyReceived = (int) $outstandingStmt->fetchColumn() === 0;
            $poStatus = $fullyReceived ? 'received' : 'partially_received';
            $pdo->prepare("UPDATE procurement_purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$poStatus, $poId]);

            if (!empty($po['purchase_request_id'])) {
                $pdo->prepare("
                    INSERT INTO procurement_approval_logs (request_id, action, previous_status, new_status, actor_id, actor_role, comments)
                    VALUES (?, 'goods_received', ?, ?, ?, ?, ?)
                ")->execute([
                    $po['purchase_request_id'], $poStatus, $poStatus, $_SESSION['user_id'], $_SESSION['role'] ?? '',
                    'Receipt ' . $grnNumber . ' recorded for ' . $po['po_number'] . ' (' . $totalAccepted . ' accepted).'
                ]);
            }

            $pdo->commit();
            logActivity('goods_receipt', "GRN $grnNumber for PO {$po['po_number']} ({$totalAccepted} accepted)");
            echo json_encode([
                'success' => true,
                'grn_number' => $grnNumber,
                'po_status' => $poStatus,
                'accepted_total' => $totalAccepted
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('receipt_save failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => (int) $e->getCode() === 23000 ? 'This delivery was already submitted. Please refresh the page.' : 'Could not record the delivery.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- RECORD SUPPLIER PAYMENT (supports partial payments) ----
    if ($_GET['action'] == 'record_supplier_payment') {
        if (!canAccess('finance') && !hasRole('finance') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Finance role required to record supplier payments.']);
            exit();
        }
        try {
            $invoiceId = intval($_POST['invoice_id'] ?? 0);
            $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
            $method = $_POST['method'] ?? 'bank_transfer';
            $paymentDate = trim($_POST['payment_date'] ?? '');
            $reference = trim($_POST['reference'] ?? '');
            if (!in_array($method, ['bank_transfer', 'check', 'cash', 'gcash', 'card'], true)) {
                $method = 'bank_transfer';
            }
            if ($paymentDate === '') {
                $paymentDate = date('Y-m-d');
            }
            $dateCheck = DateTime::createFromFormat('Y-m-d', $paymentDate);
            if (!$dateCheck || $dateCheck->format('Y-m-d') !== $paymentDate) {
                throw new Exception('Enter a valid payment date (YYYY-MM-DD).');
            }
            if ($invoiceId <= 0) {
                throw new Exception('Supplier invoice not found.');
            }
            if ($amount === false || $amount === null || $amount <= 0) {
                throw new Exception('Payment amount must be greater than zero.');
            }

            $pdo->beginTransaction();
            $invStmt = $pdo->prepare("SELECT * FROM procurement_invoices WHERE id = ? FOR UPDATE");
            $invStmt->execute([$invoiceId]);
            $invoice = $invStmt->fetch();
            if (!$invoice) {
                throw new Exception('Supplier invoice not found.');
            }
            if ($invoice['payment_status'] === 'void') {
                throw new Exception('This invoice has been voided and cannot be paid.');
            }

            $invoiceAmount = floatval($invoice['amount']);
            $paidAmount = floatval($invoice['paid_amount']);
            $outstanding = round($invoiceAmount - $paidAmount, 2);
            if ($outstanding <= 0) {
                throw new Exception('This invoice is already fully paid.');
            }
            $amount = round($amount, 2);
            if ($amount > $outstanding + 0.009) {
                throw new Exception('Payment of ₱' . number_format($amount, 2) . ' exceeds the outstanding balance of ₱' . number_format($outstanding, 2) . '.');
            }

            // Duplicate payment prevention (same invoice + reference)
            if ($reference !== '') {
                $dupe = $pdo->prepare("SELECT id FROM procurement_payments WHERE invoice_id = ? AND reference = ?");
                $dupe->execute([$invoiceId, $reference]);
                if ($dupe->fetchColumn()) {
                    throw new Exception('A payment with this reference has already been recorded for this invoice.');
                }
            }

            $payStmt = $pdo->prepare("
                INSERT INTO procurement_payments (invoice_id, provider, reference, amount, status, payment_date, paid_at, created_by)
                VALUES (?, ?, ?, ?, 'paid', ?, NOW(), ?)
            ");
            $payStmt->execute([
                $invoiceId, $method, $reference !== '' ? $reference : null, $amount,
                $paymentDate, $_SESSION['user_id']
            ]);

            $newPaid = round($paidAmount + $amount, 2);
            $newStatus = $newPaid >= $invoiceAmount - 0.009 ? 'paid' : 'partial';
            $updStmt = $pdo->prepare("
                UPDATE procurement_invoices
                SET paid_amount = ?, payment_status = ?, paid_at = CASE WHEN ? = 'paid' THEN NOW() ELSE paid_at END
                WHERE id = ?
            ");
            $updStmt->execute([$newPaid, $newStatus, $newStatus, $invoiceId]);

            if ($newStatus === 'paid' && $invoice['po_id']) {
                $pdo->prepare("UPDATE procurement_purchase_orders SET status = 'closed' WHERE id = ? AND status IN ('delivered','acknowledged')")->execute([$invoice['po_id']]);
            }

            $pdo->commit();
            logActivity('supplier_payment_recorded', "Payment of ₱" . number_format($amount, 2) . " recorded for invoice {$invoice['invoice_number']}");
            echo json_encode([
                'success' => true,
                'payment_status' => $newStatus,
                'paid_amount' => $newPaid,
                'outstanding' => round($invoiceAmount - $newPaid, 2)
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('record_supplier_payment failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => (int) $e->getCode() === 23000 ? 'A payment with this reference already exists for this invoice.' : 'Could not record the payment.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- JOB POSTINGS ----
    if ($_GET['action'] == 'get_job_postings') {
        try {
            $stmt = $pdo->query("SELECT j.*, u.full_name AS created_by_name FROM job_postings j LEFT JOIN users u ON j.created_by = u.id ORDER BY j.created_at DESC LIMIT 200");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    if ($_GET['action'] == 'save_job_posting') {
        if (!hasRole('hr') && !isAdmin()) { echo json_encode(['success' => false, 'message' => 'HR role required']); exit(); }
        try {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') { echo json_encode(['success' => false, 'message' => 'Title required']); exit(); }
            $stmt = $pdo->prepare("INSERT INTO job_postings (title, department, location, type, salary_min, salary_max, description, requirements, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $_POST['department'] ?? '',
                $_POST['location'] ?? '',
                in_array($_POST['type'] ?? '', ['full_time','part_time','contract','internship'], true) ? $_POST['type'] : 'full_time',
                floatval($_POST['salary_min'] ?? 0) ?: null,
                floatval($_POST['salary_max'] ?? 0) ?: null,
                $_POST['description'] ?? '',
                $_POST['requirements'] ?? '',
                $_SESSION['user_id']
            ]);
            logActivity('job_posting', "Posted job: $title");
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    if ($_GET['action'] == 'update_job_posting_status') {
        if (!hasRole('hr') && !isAdmin()) { echo json_encode(['success' => false, 'message' => 'HR role required']); exit(); }
        try {
            $id = intval($_POST['id'] ?? 0);
            $status = in_array($_POST['status'] ?? '', ['open', 'closed'], true) ? $_POST['status'] : 'open';
            $pdo->prepare("UPDATE job_postings SET status = ? WHERE id = ?")->execute([$status, $id]);
            logActivity('job_posting_status', "Job posting #$id marked $status");
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    // ---- SUPPLIER RATINGS ----
    if ($_GET['action'] == 'rate_supplier') {
        try {
            $supplier_id = intval($_POST['supplier_id'] ?? 0);
            if (!$supplier_id) { echo json_encode(['success' => false, 'message' => 'Supplier required']); exit(); }
            $stmt = $pdo->prepare("INSERT INTO procurement_supplier_ratings (supplier_id, po_id, otif_score, quality_score, responsiveness_score, comments, rated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $supplier_id,
                !empty($_POST['po_id']) ? intval($_POST['po_id']) : null,
                max(1, min(5, intval($_POST['otif_score'] ?? 0))),
                max(1, min(5, intval($_POST['quality_score'] ?? 0))),
                max(1, min(5, intval($_POST['responsiveness_score'] ?? 0))),
                $_POST['comments'] ?? '',
                $_SESSION['user_id']
            ]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) { echo json_encode(['success' => false, 'message' => $e->getMessage()]); }
        exit();
    }

    if ($_GET['action'] == 'get_ratings') {
        try {
            $stmt = $pdo->query("SELECT r.*, s.name AS supplier_name FROM procurement_supplier_ratings r LEFT JOIN suppliers s ON r.supplier_id = s.id ORDER BY r.created_at DESC LIMIT 200");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    // ---- TOKENIZE CARD (never stores the full card number) ----
    if ($_GET['action'] == 'tokenize_card') {
        if (!canAccess('tokenization') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
            if (strlen($number) < 13 || strlen($number) > 19) {
                echo json_encode(['success' => false, 'message' => 'Invalid card number']);
                exit();
            }
            $brand = 'unknown';
            if (preg_match('/^4/', $number)) $brand = 'visa';
            elseif (preg_match('/^(5[1-5]|2[2-7])/', $number)) $brand = 'mastercard';
            elseif (preg_match('/^3[47]/', $number)) $brand = 'amex';
            $last4 = substr($number, -4);
            $token = bin2hex(random_bytes(24)); // the safe stand-in for the card number

            $stmt = $pdo->prepare("
                INSERT INTO payment_tokens (customer_id, token, card_brand, last4, expiry_month, expiry_year, cardholder_name, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null,
                $token, $brand, $last4,
                !empty($_POST['expiry_month']) ? intval($_POST['expiry_month']) : null,
                !empty($_POST['expiry_year']) ? intval($_POST['expiry_year']) : null,
                $_POST['cardholder_name'] ?? '',
                $_SESSION['user_id']
            ]);
            logActivity('tokenize_card', "Tokenized $brand card ending $last4");
            echo json_encode(['success' => true, 'token_id' => $pdo->lastInsertId(), 'last4' => $last4, 'brand' => $brand]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- GET PAYMENT TOKENS ----
    if ($_GET['action'] == 'get_payment_tokens') {
        try {
            $stmt = $pdo->query("
                SELECT t.id, t.card_brand, t.last4, t.expiry_month, t.expiry_year, t.cardholder_name,
                       t.created_at, t.active, c.name AS customer_name
                FROM payment_tokens t
                LEFT JOIN customers c ON t.customer_id = c.id
                ORDER BY t.created_at DESC
            ");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
        exit();
    }

    // ---- DELETE (DEACTIVATE) TOKEN ----
    if ($_GET['action'] == 'delete_payment_token') {
        if (!canAccess('tokenization') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        try {
            $id = intval($_GET['id'] ?? 0);
            $pdo->prepare("UPDATE payment_tokens SET active = 0 WHERE id = ?")->execute([$id]);
            logActivity('delete_payment_token', "Deactivated payment token #$id");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- SAVE SUPPLIER ----
    if ($_GET['action'] == 'save_supplier') {
        if (!canAccess('suppliers') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        try {
            $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
            $data = [
                $_POST['name'] ?? '',
                $_POST['contact_person'] ?? '',
                $_POST['phone'] ?? '',
                $_POST['email'] ?? '',
                $_POST['address'] ?? '',
                $_POST['notes'] ?? '',
                $_POST['status'] ?? 'active',
                $_POST['supplier_type'] ?? 'other_source'
            ];
            
            if (empty($data[0])) {
                echo json_encode(['success' => false, 'message' => 'Supplier name is required']);
                exit();
            }
            if (!in_array($data[7], ['direct_supplier', 'distributor', 'wholesaler', 'other_source'], true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid supplier type']);
                exit();
            }
            
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE suppliers SET 
                        name = ?, contact_person = ?, phone = ?, email = ?, 
                        address = ?, notes = ?, status = ?, supplier_type = ?
                    WHERE id = ?
                ");
                $data[] = $id;
                $stmt->execute($data);
                logActivity('update_supplier', "Updated supplier: {$data[0]}");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO suppliers 
                    (name, contact_person, phone, email, address, notes, status, supplier_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute($data);
                logActivity('add_supplier', "Added supplier: {$data[0]}");
            }
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- DELETE SUPPLIER ----
    if ($_GET['action'] == 'delete_supplier') {
        if (!canAccess('suppliers') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        try {
            $id = intval($_GET['id'] ?? 0);
            
            // Check if supplier has purchases
            $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM purchases WHERE supplier_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()['c'] > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete — supplier has purchase history']);
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            
            logActivity('delete_supplier', "Deleted supplier ID: $id");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- GET SUPPLIERS (for dropdown) ----
    if ($_GET['action'] == 'get_suppliers') {
        try {
            $stmt = $pdo->query("SELECT id, name, supplier_type FROM suppliers WHERE status = 'active' ORDER BY name");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) {
            echo json_encode([]);
        }
        exit();
    }

    // ---- SAVE PURCHASE ----
    if ($_GET['action'] == 'save_purchase') {
        if (!canAccess('purchases_create') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
            $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
            $notes = $_POST['notes'] ?? '';
            $payment_status = $_POST['payment_status'] ?? 'unpaid';
            $items = json_decode($_POST['items'] ?? '[]', true);
            
            if (empty($items)) {
                echo json_encode(['success' => false, 'message' => 'At least one item required']);
                exit();
            }
            
            // Generate PO number
            $po_number = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Calculate totals
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += floatval($item['quantity']) * floatval($item['unit_cost']);
            }
            $tax_rate = floatval(getSetting('tax_rate', 12)) / 100;
            $tax_amount = $subtotal * $tax_rate;
            $total_amount = $subtotal + $tax_amount;
            
            // Insert purchase
            $stmt = $pdo->prepare("
                INSERT INTO purchases 
                (po_number, supplier_id, purchase_date, subtotal, tax_amount, total_amount, payment_status, notes, recorded_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $po_number, $supplier_id, $purchase_date,
                $subtotal, $tax_amount, $total_amount,
                $payment_status, $notes, $_SESSION['user_id']
            ]);
            $purchase_id = $pdo->lastInsertId();
            
            // Insert items + update stock + log movements
            foreach ($items as $item) {
                $product_id = intval($item['product_id']);
                $quantity = intval($item['quantity']);
                $unit_cost = floatval($item['unit_cost']);
                $total_cost = $quantity * $unit_cost;
                
                // Get current stock
                $stmt = $pdo->prepare("SELECT stock_quantity, cost_price FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $prod = $stmt->fetch();
                $before = intval($prod['stock_quantity']);
                $after = $before + $quantity;
                
                // Insert purchase item
                $stmt = $pdo->prepare("
                    INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, total_cost) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$purchase_id, $product_id, $quantity, $unit_cost, $total_cost]);
                
                // Update product stock + cost
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ?, cost_price = ? WHERE id = ?");
                $stmt->execute([$after, $unit_cost, $product_id]);
                
                // Log movement
                $stmt = $pdo->prepare("
                    INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, quantity_before, quantity_after, reason, reference_id, reference_type, user_id) 
                    VALUES (?, 'purchase', ?, ?, ?, ?, ?, 'purchase', ?)
                ");
                $stmt->execute([
                    $product_id, $quantity, $before, $after,
                    "Purchase from supplier (PO: $po_number)",
                    $purchase_id, $_SESSION['user_id']
                ]);
            }
            
            $pdo->commit();
            
            logActivity('create_purchase', "Created purchase: $po_number (₱$total_amount)");
            
            echo json_encode(['success' => true, 'po_number' => $po_number, 'purchase_id' => $purchase_id]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- GET PURCHASE ----
    if ($_GET['action'] == 'get_purchase') {
        if (!canAccess('purchases') && !isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        try {
            $id = intval($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT p.*, s.name as supplier_name, s.phone as supplier_phone, s.email as supplier_email,
                    u.full_name as recorded_by_name
                FROM purchases p
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                LEFT JOIN users u ON p.recorded_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $purchase = $stmt->fetch();
            
            if (!$purchase) {
                echo json_encode(['success' => false, 'message' => 'Purchase not found']);
                exit();
            }
            
            $stmt = $pdo->prepare("
                SELECT pi.*, p.name as product_name, p.unit
                FROM purchase_items pi
                JOIN products p ON pi.product_id = p.id
                WHERE pi.purchase_id = ?
            ");
            $stmt->execute([$id]);
            $purchase['items'] = $stmt->fetchAll();
            
            echo json_encode($purchase);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // ---- EXPORT INVENTORY REPORT ----
    if ($_GET['action'] == 'export_inventory_report') {
        if (!canAccess('inventory_reports') && !isAdmin()) {
            http_response_code(403);
            exit('Unauthorized');
        }
        
        $type = $_GET['type'] ?? 'current';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory_' . $type . '_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM
        
        fputcsv($output, ['Inventory Report: ' . strtoupper($type)]);
        fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        if ($type == 'current' || $type == 'valuation') {
            fputcsv($output, ['Product', 'SKU', 'Category', 'Stock', 'Unit', 'Cost', 'Retail', 'Stock Value (Cost)', 'Stock Value (Retail)']);
            $stmt = $pdo->query("
                SELECT p.name, p.sku, c.name as category, p.stock_quantity, p.unit, 
                    p.cost_price, p.selling_price
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.archived = 0
                ORDER BY p.name
            ");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $r['cost_value'] = $r['stock_quantity'] * ($r['cost_price'] ?? 0);
                $r['retail_value'] = $r['stock_quantity'] * $r['selling_price'];
                fputcsv($output, $r);
            }
        } elseif ($type == 'low') {
            fputcsv($output, ['Product', 'Category', 'Stock', 'Threshold', 'Unit']);
            $stmt = $pdo->query("
                SELECT p.name, c.name as category, p.stock_quantity, p.low_stock_threshold, p.unit
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.archived = 0 
                AND p.stock_quantity > 0 
                AND p.stock_quantity <= COALESCE(p.low_stock_threshold, 5)
                ORDER BY p.stock_quantity ASC
            ");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $r);
            }
        } elseif ($type == 'out') {
            fputcsv($output, ['Product', 'Category', 'Unit', 'Cost', 'Retail']);
            $stmt = $pdo->query("
                SELECT p.name, c.name as category, p.unit, p.cost_price, p.selling_price
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.archived = 0 AND p.stock_quantity = 0
                ORDER BY p.name
            ");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $r);
            }
        } elseif ($type == 'movement') {
            fputcsv($output, ['Date', 'Product', 'Type', 'Quantity', 'Before', 'After', 'Reason', 'User']);
            $stmt = $pdo->query("
                SELECT sm.created_at, p.name as product, sm.movement_type, sm.quantity, 
                    sm.quantity_before, sm.quantity_after, sm.reason, u.full_name as user
                FROM stock_movements sm
                LEFT JOIN products p ON sm.product_id = p.id
                LEFT JOIN users u ON sm.user_id = u.id
                ORDER BY sm.created_at DESC
                LIMIT 500
            ");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $r);
            }
        } elseif ($type == 'purchase') {
            fputcsv($output, ['PO Number', 'Date', 'Supplier', 'Status', 'Total']);
            $stmt = $pdo->query("
                SELECT p.po_number, p.purchase_date, s.name as supplier, p.payment_status, p.total_amount
                FROM purchases p
                LEFT JOIN suppliers s ON p.supplier_id = s.id
                ORDER BY p.purchase_date DESC
            ");
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, $r);
            }
        }
        
        fclose($output);
        exit();
    }

            // ============================================
        // Returns endpoints
        // ============================================

        if ($_GET['action'] == 'create_return') {
            if (!canAccess('returns_create') && !isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            
            $sale_id = isset($_POST['sale_id']) ? $_POST['sale_id'] : null;
            $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
            $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            $user_id = $_SESSION['user_id'];
            
            if (!$sale_id || empty($items) || !$reason) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                exit();
            }
            
            $result = $returnManager->createReturn($sale_id, $items, $reason, $notes, $user_id);
            echo json_encode($result);
            exit();
        }

        if ($_GET['action'] == 'approve_return') {
            if (!canAccess('returns_approve') && !isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            
            $return_id = isset($_POST['return_id']) ? $_POST['return_id'] : null;
            $user_id = $_SESSION['user_id'];
            
            if (!$return_id) {
                echo json_encode(['success' => false, 'message' => 'Return ID required']);
                exit();
            }
            
            $result = $returnManager->approveReturn($return_id, $user_id);
            echo json_encode($result);
            exit();
        }

        if ($_GET['action'] == 'reject_return') {
            if (!canAccess('returns_approve') && !isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            
            $return_id = isset($_POST['return_id']) ? $_POST['return_id'] : null;
            $reason = isset($_POST['reason']) ? $_POST['reason'] : 'No reason provided';
            
            if (!$return_id) {
                echo json_encode(['success' => false, 'message' => 'Return ID required']);
                exit();
            }
            
            $result = $returnManager->rejectReturn($return_id, $reason);
            echo json_encode($result);
            exit();
        }

        if ($_GET['action'] == 'get_return') {
            if (!canAccess('returns') && !canAccess('returns_view') && !canAccess('returns_create') && !isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Return ID required']);
                exit();
            }
            
            $result = $returnManager->getReturn($id);
            echo json_encode($result);
            exit();
        }

        if ($_GET['action'] == 'get_return_stats') {
            if (!canAccess('returns') && !canAccess('returns_view') && !isAdmin()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit();
            }
            
            echo json_encode($returnManager->getReturnStats());
            exit();
        }

        // ============================================
        // CHECK IF SALE ALREADY HAS A RETURN
        // ============================================

        if ($_GET['action'] == 'check_sale_returned') {
            $sale_id = isset($_GET['sale_id']) ? $_GET['sale_id'] : null;
            
            if (!$sale_id) {
                echo json_encode(['returned' => false, 'error' => 'No sale ID provided']);
                exit();
            }
            
            try {
                $stmt = $pdo->prepare("
                SELECT COUNT(*) as return_count 
                FROM returns 
                WHERE original_sale_id = ?
            ");
                $stmt->execute([$sale_id]);
                $result = $stmt->fetch();
                
                echo json_encode([
                    'returned' => $result['return_count'] > 0,
                    'count' => $result['return_count']
                ]);
            } catch(PDOException $e) {
                echo json_encode(['returned' => false, 'error' => $e->getMessage()]);
            }
            exit();
        }

            // ============================================
            // SAVE EXPENSE
            // ============================================
            if ($_GET['action'] == 'save_expense') {
                if (!isAdmin() && !hasRole('finance')) {
                    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                    exit();
                }
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO expenses (expense_date, category, description, amount, recorded_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['expense_date'] ?? date('Y-m-d'),
                        $_POST['category'] ?? 'Other',
                        $_POST['description'] ?? '',
                        floatval($_POST['amount'] ?? 0),
                        $_SESSION['user_id']
                    ]);
                    
                    logActivity('save_expense', "Recorded expense: {$_POST['category']} - ₱{$_POST['amount']}");
                    
                    echo json_encode(['success' => true]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit();
            }

            // ============================================
            // EXPORT REPORT TO CSV
            // ============================================
            if ($_GET['action'] == 'export_report') {
                if (!isAdmin() && !hasRole('finance')) {
                    http_response_code(403);
                    exit('Unauthorized');
                }
                
                $type = $_GET['type'] ?? 'revenue';
                $start = $_GET['start_date'] ?? date('Y-m-01');
                $end = $_GET['end_date'] ?? date('Y-m-t');
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="finance_' . $type . '_' . $start . '_to_' . $end . '.csv"');
                
                $output = fopen('php://output', 'w');
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
                
                // Header info
                fputcsv($output, ['Finance Report: ' . strtoupper($type)]);
                fputcsv($output, ['Period: ' . $start . ' to ' . $end]);
                fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
                fputcsv($output, []);
                
                if ($type == 'revenue' || $type == 'profit') {
                    fputcsv($output, ['Date', 'Transactions', 'Discounts', 'VAT', 'Revenue']);
                    $stmt = $pdo->prepare("
                        SELECT DATE(sale_date) as date, COUNT(*) as txn, 
                            SUM(discount_amount) as discount, SUM(tax) as vat, SUM(total_amount) as revenue
                        FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?
                        GROUP BY DATE(sale_date) ORDER BY date
                    ");
                    $stmt->execute([$start, $end]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        fputcsv($output, $row);
                    }
                } elseif ($type == 'expense') {
                    fputcsv($output, ['Date', 'Category', 'Description', 'Amount']);
                    $stmt = $pdo->prepare("SELECT expense_date, category, description, amount FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date");
                    $stmt->execute([$start, $end]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        fputcsv($output, $row);
                    }
                } elseif ($type == 'sales') {
                    fputcsv($output, ['Invoice', 'Date', 'Total', 'VAT', 'Discount']);
                    $stmt = $pdo->prepare("SELECT invoice_number, sale_date, total_amount, tax, discount_amount FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? ORDER BY sale_date DESC");
                    $stmt->execute([$start, $end]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        fputcsv($output, $row);
                    }
                } elseif ($type == 'refund') {
                    fputcsv($output, ['Return #', 'Date', 'Reason', 'Status', 'Amount']);
                    $stmt = $pdo->prepare("SELECT return_number, created_at, reason, status, total_refund FROM returns WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
                    $stmt->execute([$start, $end]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        fputcsv($output, $row);
                    }
                } elseif ($type == 'payment') {
                    fputcsv($output, ['Method', 'Count', 'Total']);
                    $stmt = $pdo->prepare("SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? GROUP BY payment_method");
                    $stmt->execute([$start, $end]);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        fputcsv($output, $row);
                    }
                }
                
                fclose($output);
                exit();
            }

    }
