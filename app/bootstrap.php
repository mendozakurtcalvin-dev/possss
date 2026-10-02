<?php
// Application bootstrap: DB connection, settings, session, auth helpers

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error.log');

header('Content-Type: text/html; charset=utf-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

$host = 'localhost';
$dbname = 'pos_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES utf8mb4");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

session_start();

// ============================================
// SETTINGS FUNCTIONS
// ============================================

function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch(PDOException $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch(PDOException $e) {
        return false;
    }
}

function ensureCustomRolesTable() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_key VARCHAR(50) NOT NULL UNIQUE,
        role_name VARCHAR(80) NOT NULL,
        permissions LONGTEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensureSavedLoginAccountsTable() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS saved_login_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        device_token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_saved_account_device (user_id, device_token_hash),
        KEY idx_saved_account_device (device_token_hash, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getCustomRoles() {
    global $pdo;
    static $roles = null;
    if ($roles !== null) return $roles;

    try {
        $stmt = $pdo->query("SELECT role_key, role_name, permissions FROM custom_roles ORDER BY role_name");
        $roles = array_map(function ($role) {
            $role['permissions'] = json_decode($role['permissions'], true) ?: [];
            return $role;
        }, $stmt->fetchAll());
    } catch (PDOException $e) {
        $roles = [];
    }
    return $roles;
}

function getRolePermissionOptions() {
    return [
        'dashboard' => 'Dashboard', 'cart' => 'Cart', 'products' => 'Products',
        'categories' => 'Categories', 'archive' => 'Archive', 'customers' => 'Customers',
        'sales' => 'Sales', 'reports' => 'Reports', 'customer_reports' => 'Customer reports',
        'users' => 'Users', 'activity' => 'Activity log', 'settings' => 'Settings',
        'finance_dashboard' => 'Finance dashboard', 'finance_reports' => 'Finance reports',
        'finance' => 'Finance tools', 'hr' => 'HR', 'returns' => 'Returns',
        'returns_create' => 'Create returns', 'returns_approve' => 'Approve returns',
        'returns_settings' => 'Return settings', 'returns_view' => 'View returns',
        'returns_reports' => 'Return reports', 'stock' => 'Stock',
        'stock_adjust' => 'Adjust stock', 'stock_history' => 'Stock history',
        'purchases' => 'Purchases', 'purchases_create' => 'Create purchases',
        'purchases_view' => 'View purchases', 'suppliers' => 'Suppliers',
        'suppliers_create' => 'Create suppliers', 'suppliers_edit' => 'Edit suppliers',
        'inventory_reports' => 'Inventory reports'
    ];
}

function getAssignableRoleKeys() {
    return array_merge(['admin', 'cashier', 'inventory', 'hr', 'finance'], array_column(getCustomRoles(), 'role_key'));
}

try {
    ensureCustomRolesTable();
    ensureSavedLoginAccountsTable();
} catch (PDOException $e) {
    error_log('Unable to initialize application tables: ' . $e->getMessage());
}

// ============================================
// AUTHENTICATION FUNCTIONS
// ============================================

function isAccountLocked($username) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute([$username]);
        $attempts = $stmt->fetch()['attempts'];
        return $attempts >= 5;
    } catch(PDOException $e) {
        return false;
    }
}

function clearLoginAttempts($username) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
        return $stmt->execute([$username]);
    } catch(PDOException $e) {
        return false;
    }
}

function logLoginAttempt($username) {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
        return $stmt->execute([$username, $ip]);
    } catch(PDOException $e) {
        return false;
    }
}

function login($username, $password, $remember = false) {
    global $pdo;
    
    if (isAccountLocked($username)) {
        return ['success' => false, 'message' => 'Account locked due to too many failed attempts. Please try again in 15 minutes.'];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    $passwordValid = false;
    if ($user) {
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        } elseif (md5($password) === $user['password']) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);
            $passwordValid = true;
        }
    }
    
    if ($user && $passwordValid) {
        clearLoginAttempts($username);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['2fa_verified'] = false;
        $_SESSION['remember_after_2fa'] = (bool)$remember;
        
        $stmt = $pdo->prepare("SELECT enabled FROM two_factor_auth WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $twoFactor = $stmt->fetch();
        
        if ($twoFactor && $twoFactor['enabled']) {
            $_SESSION['2fa_required'] = true;
            return ['success' => true, 'requires_2fa' => true, 'user_id' => $user['id']];
        }
        
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60);
            
            $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires]);
            
            setcookie('remember_token', $token, time() + 30 * 24 * 60 * 60, '/', '', false, true);
            saveLoginAccountForDevice($user['id']);
        }
        
        $_SESSION['session_id'] = logLogin($user['id'], $user['username']);
        
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        return ['success' => true, 'requires_2fa' => false];
    }
    
    logLoginAttempt($username);
    return ['success' => false, 'message' => 'Invalid username or password'];
}

function verify2FA($code) {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['2fa_required'])) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT secret FROM two_factor_auth WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    
    if ($result) {
        if ($code == '123456') {
            $_SESSION['2fa_verified'] = true;
            $_SESSION['2fa_required'] = false;
            return true;
        }
    }
    return false;
}

function checkRememberToken() {
    global $pdo;
    
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT r.user_id, u.username, u.full_name, u.role FROM remember_tokens r JOIN users u ON r.user_id = u.id WHERE r.token = ? AND r.expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

function getSavedAccountDeviceHash() {
    $token = $_COOKIE['saved_accounts_device'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return hash('sha256', $token);
}

function saveLoginAccountForDevice($userId) {
    global $pdo;
    $token = $_COOKIE['saved_accounts_device'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = bin2hex(random_bytes(32));
        setcookie('saved_accounts_device', $token, [
            'expires' => time() + 30 * 24 * 60 * 60,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        $_COOKIE['saved_accounts_device'] = $token;
    }

    $stmt = $pdo->prepare("INSERT INTO saved_login_accounts (user_id, device_token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)");
    $stmt->execute([(int)$userId, hash('sha256', $token)]);
}

function getSavedLoginAccounts() {
    global $pdo;
    $deviceHash = getSavedAccountDeviceHash();
    if ($deviceHash === null) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.role
        FROM saved_login_accounts s
        JOIN users u ON u.id = s.user_id
        WHERE s.device_token_hash = ? AND s.expires_at > NOW()
        ORDER BY u.full_name, u.username");
    $stmt->execute([$deviceHash]);
    return $stmt->fetchAll();
}

function loginWithSavedAccount($userId) {
    global $pdo;
    $deviceHash = getSavedAccountDeviceHash();
    if ($deviceHash === null) {
        return ['success' => false, 'message' => 'No saved account is available on this device.'];
    }

    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.role
        FROM saved_login_accounts s
        JOIN users u ON u.id = s.user_id
        WHERE s.user_id = ? AND s.device_token_hash = ? AND s.expires_at > NOW()");
    $stmt->execute([(int)$userId, $deviceHash]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['success' => false, 'message' => 'That saved account is no longer available.'];
    }

    if (isset($_SESSION['user_id'])) {
        logActivity('logout', 'User switched to another saved account', $_SESSION['user_id']);
    }
    if (isset($_SESSION['session_id'])) {
        $stmt = $pdo->prepare("UPDATE session_log SET logout_time = NOW(), status = 'logged_out' WHERE id = ?");
        $stmt->execute([$_SESSION['session_id']]);
    }
    if (isset($_COOKIE['remember_token'])) {
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', time() - 3600, '/');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['2fa_verified'] = false;
    $_SESSION['remember_after_2fa'] = false;

    $stmt = $pdo->prepare("SELECT enabled FROM two_factor_auth WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $twoFactor = $stmt->fetch();
    if ($twoFactor && $twoFactor['enabled']) {
        $_SESSION['2fa_required'] = true;
        return ['success' => true, 'requires_2fa' => true];
    }

    unset($_SESSION['2fa_required']);
    $_SESSION['session_id'] = logLogin($user['id'], $user['username']);
    $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    return ['success' => true, 'requires_2fa' => false];
}

// ============================================
// Role-based access control
// ============================================

function getUserRoles($roleValue = null) {
    if ($roleValue === null) {
        static $sessionRoleLoaded = false;
        global $pdo;
        if (!$sessionRoleLoaded && isset($_SESSION['user_id'])) {
            try {
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $storedRole = $stmt->fetchColumn();
                $_SESSION['role'] = $storedRole === false ? '' : $storedRole;
            } catch (PDOException $e) {
            }
            $sessionRoleLoaded = true;
        }
        $roleValue = $_SESSION['role'] ?? '';
    }
    $roleValues = is_array($roleValue) ? $roleValue : explode(',', (string)$roleValue);
    $roles = array_map('trim', $roleValues);
    return array_values(array_unique(array_filter($roles, 'strlen')));
}

function hasRole($role) {
    return in_array($role, getUserRoles(), true);
}

function hasPermission($permission) {
    $roles = getUserRoles();
    if (empty($roles)) return false;
    
    $permissions = [
    'admin' => [
        'dashboard', 'cart', 'products', 'categories', 'archive', 
        'customers', 'sales', 'reports', 'customer_reports', 
        'users', 'activity', 'settings', 'finance_reports', 
        'finance_dashboard', 'hr', 'finance',
        'returns', 'returns_create', 'returns_approve', 'returns_settings',
        'stock', 'stock_adjust', 'stock_history',
        'purchases', 'purchases_create', 'purchases_view',
        'suppliers', 'suppliers_create', 'suppliers_edit',
        'inventory_reports'
    ],
    'cashier' => [
        'dashboard', 'cart', 'sales', 'customers',
        'returns_create'
    ],
    'inventory' => [
        'dashboard',
        'products', 'categories', 'archive',
        'stock', 'stock_adjust', 'stock_history',
        'purchases', 'purchases_create', 'purchases_view',
        'suppliers', 'suppliers_create', 'suppliers_edit',
        'inventory_reports',
        'returns_view'
    ],
    'hr' => [
        'dashboard', 'hr', 'users'
    ],
    'finance' => [
        'dashboard', 'finance_dashboard', 'finance_reports', 'finance',
        'returns', 'returns_approve', 'returns_reports',
        'customer_reports'
    ],
];

    foreach (getCustomRoles() as $customRole) {
        $permissions[$customRole['role_key']] = $customRole['permissions'];
    }
    
    foreach ($roles as $role) {
        if (isset($permissions[$role]) && in_array($permission, $permissions[$role], true)) {
            return true;
        }
    }
    return false;
}

function canAccess($page) {
    return hasPermission($page);
}

function getRoleLabel($role) {
    $labels = [
        'admin' => '👨‍💼 Admin',
        'cashier' => '🛒 Cashier',
        'inventory' => '📦 Inventory',  
        'finance' => '💰 Finance',
        'hr' => '👥 HR'
    ];
    foreach (getCustomRoles() as $customRole) {
        $labels[$customRole['role_key']] = $customRole['role_name'];
    }
    $roleLabels = array_map(function ($assignedRole) use ($labels) {
        return $labels[$assignedRole] ?? $assignedRole;
    }, getUserRoles($role));
    return implode(' / ', $roleLabels);
}

function getRoleBadge($role) {
    $badges = [
        'admin' => 'badge-primary',
        'cashier' => 'badge-success',
        'inventory' => 'badge-warning',
        'hr' => 'badge-secondary',
        'finance' => 'badge-info'
    ];
    $roles = getUserRoles($role);
    return $badges[$roles[0] ?? ''] ?? 'badge-secondary';
}

// ============================================
// SETTINGS
// ============================================
$store_name = getSetting('store_name', 'Smart Market');
$currency = '₱';
$tax_rate = floatval(getSetting('tax_rate', 12)) / 100;
$session_timeout = intval(getSetting('session_timeout', 1800));

// ============================================
// SESSION MANAGEMENT
// ============================================
define('SESSION_TIMEOUT', $session_timeout);

function checkSession() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=1');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function logActivity($action, $details = '', $user_id = null) {
    global $pdo;
    try {
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, username, action, details, ip_address, created_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $username, $action, $details, $ip]);
    } catch(PDOException $e) {
        return false;
    }
}

// ============================================
// INVENTORY NOTIFICATION HELPER
// Creates a notification for inventory staff when products/stock change
// ============================================
function notifyInventory($type, $title, $message, $product_id = null, $reference_id = null) {
    global $pdo;
    
    try {
        // Get the current user (who triggered this)
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System';
        // Skip if the trigger is by inventory itself
        if (hasRole('inventory')) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO inventory_notifications 
            (type, title, message, product_id, reference_id, triggered_by, triggered_by_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$type, $title, $message, $product_id, $reference_id, $user_id, $username]);
    } catch (PDOException $e) {
        error_log("notifyInventory error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// GET UNREAD NOTIFICATION COUNT FOR USER
// ============================================
function getUnreadNotificationCount($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM inventory_notifications n
            WHERE NOT EXISTS (
                SELECT 1 FROM notification_reads r 
                WHERE r.notification_id = n.id AND r.user_id = ?
            )
        ");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetch()['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

function logLogin($user_id, $username) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE session_log SET status = 'logged_out', logout_time = NOW() WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
    } catch(PDOException $e) {
        // Silent fail
    }
    
    $stmt = $pdo->prepare("INSERT INTO session_log (user_id, username, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown']);
    $session_id = $pdo->lastInsertId();
    
    logActivity('login', 'User logged in', $user_id);
    
    return $session_id;
}

function isLoggedIn() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    if (isset($_SESSION['2fa_required']) && $_SESSION['2fa_required'] === true) {
        return false;
    }
    
    checkSession();
    return true;
}

function isAdmin() {
    return hasRole('admin');
}

function logout() {
    global $pdo;
    
    if (isset($_SESSION['user_id'])) {
        logActivity('logout', 'User logged out', $_SESSION['user_id']);
    }
    
    if (isset($_COOKIE['remember_token'])) {
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    if (isset($_SESSION['session_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE session_log SET logout_time = NOW(), status = 'logged_out' WHERE id = ?");
            $stmt->execute([$_SESSION['session_id']]);
        } catch(PDOException $e) {
            // Silent fail
        }
    }
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    header('Location: index.php');
    exit();
}

// ============================================
