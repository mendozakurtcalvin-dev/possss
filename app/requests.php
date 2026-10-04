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
        if (!isAdmin() && $roleKey !== '') {
            echo json_encode(['success' => false, 'message' => 'HR can create custom roles but cannot edit existing roles.']);
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
            'store_logo' => getSetting('store_logo', '')
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

            $result = $hrManager->saveEmployee($data);
            
            if ($result) {
                logActivity('save_employee', "Saved employee: {$data['first_name']} {$data['last_name']} ({$data['employee_id']})");
                echo json_encode(['success' => true]);
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
                SELECT r.*, s.name AS supplier_name, u.full_name AS requested_by_name, a.full_name AS approved_by_name
                FROM procurement_requests r
                LEFT JOIN suppliers s ON r.supplier_id = s.id
                LEFT JOIN users u ON r.requested_by = u.id
                LEFT JOIN users a ON r.approved_by = a.id
                ORDER BY r.created_at DESC LIMIT 200
            ");
            echo json_encode($stmt->fetchAll());
        } catch (Exception $e) { echo json_encode([]); }
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
                (req_number, item_name, product_id, quantity, estimated_unit_cost, supplier_id, department, notes, requested_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $req_number, $item_name,
                !empty($_POST['product_id']) ? intval($_POST['product_id']) : null,
                $quantity,
                floatval($_POST['estimated_unit_cost'] ?? 0),
                !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null,
                $_POST['department'] ?? '',
                $_POST['notes'] ?? '',
                $_SESSION['user_id']
            ]);
            logActivity('procurement_request', "New request $req_number: $quantity x $item_name");
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
            $stmt = $pdo->prepare("UPDATE procurement_requests SET status = ?, approved_by = ? WHERE id = ?");
            $stmt->execute([$status, $_SESSION['user_id'], $id]);

            // When received and linked to a product, add stock back in
            if ($status === 'received') {
                $row = $pdo->prepare("SELECT * FROM procurement_requests WHERE id = ?");
                $row->execute([$id]);
                $req = $row->fetch();
                if ($req && $req['product_id']) {
                    $s = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                    $s->execute([$req['product_id']]);
                    $prod = $s->fetch();
                    if ($prod) {
                        $before = intval($prod['stock_quantity']);
                        $after = $before + intval($req['quantity']);
                        $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?")->execute([$after, $req['product_id']]);
                        $pdo->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, quantity_before, quantity_after, reason, reference_id, reference_type, user_id) VALUES (?, 'purchase', ?, ?, ?, ?, ?, 'purchase', ?)")
                            ->execute([$req['product_id'], intval($req['quantity']), $before, $after, "Received via procurement {$req['req_number']}", $id, $_SESSION['user_id']]);

                        // Also record it as a purchase so finance sees the expense
                        $unitCost = floatval($req['estimated_unit_cost']);
                        $subtotal = $unitCost * intval($req['quantity']);
                        $taxRate = floatval(getSetting('tax_rate', 12)) / 100;
                        $taxAmount = $subtotal * $taxRate;
                        $totalAmount = $subtotal + $taxAmount;
                        $poNumber = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        $pdo->prepare("INSERT INTO purchases (po_number, supplier_id, purchase_date, subtotal, tax_amount, total_amount, payment_status, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, 'unpaid', ?, ?)")
                            ->execute([$poNumber, $req['supplier_id'] ?: null, date('Y-m-d'), $subtotal, $taxAmount, $totalAmount, "Received via procurement request {$req['req_number']}", $_SESSION['user_id']]);
                        $purchaseId = $pdo->lastInsertId();
                        $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$purchaseId, $req['product_id'], intval($req['quantity']), $unitCost, $subtotal]);
                    }
                }
            }
            logActivity('procurement_status', "Request #$id marked $status");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
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
                $_POST['status'] ?? 'active'
            ];
            
            if (empty($data[0])) {
                echo json_encode(['success' => false, 'message' => 'Supplier name is required']);
                exit();
            }
            
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE suppliers SET 
                        name = ?, contact_person = ?, phone = ?, email = ?, 
                        address = ?, notes = ?, status = ?
                    WHERE id = ?
                ");
                $data[] = $id;
                $stmt->execute($data);
                logActivity('update_supplier', "Updated supplier: {$data[0]}");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO suppliers 
                    (name, contact_person, phone, email, address, notes, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
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
            $stmt = $pdo->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name");
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
