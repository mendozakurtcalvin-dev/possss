<?php
// DATABASE CLASSES
// ============================================

class CategoryManager {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public function getAllCategories() {
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function addCategory($name, $description = '') {
        $stmt = $this->pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $result = $stmt->execute([$name, $description]);
        if ($result) {
            logActivity('add_category', "Added category: $name");
        }
        return $result;
    }
    
    public function updateCategory($id, $name, $description = '') {
        $stmt = $this->pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        $result = $stmt->execute([$name, $description, $id]);
        if ($result) {
            logActivity('update_category', "Updated category: $name (ID: $id)");
        }
        return $result;
    }
    
    public function deleteCategory($id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        if ($count > 0) {
            return ['success' => false, 'message' => 'Category is being used by products'];
        }
        
        $stmt = $this->pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        
        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result && $cat) {
            logActivity('delete_category', "Deleted category: {$cat['name']} (ID: $id)");
        }
        return ['success' => $result];
    }
}

class ProductManager {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public function getAllProducts($includeArchived = false) {
        $sql = "SELECT p.*, c.name as category_name FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id";
        if (!$includeArchived) {
            $sql .= " WHERE p.archived = 0";
        }
        $sql .= " ORDER BY p.name";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getArchivedProducts($search = '') {
        $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.archived = 1";
        $params = [];
        if (!empty($search)) {
            $sql .= " AND p.name LIKE ?";
            $params[] = '%' . $search . '%';
        }
        $sql .= " ORDER BY p.name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function addProduct($name, $category_id, $price, $stock, $unit = 'pc', $barcode = null, $image = null) {
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND archived = 0");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("INSERT INTO products (name, category_id, selling_price, stock_quantity, unit, barcode, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$name, $category_id, $price, $stock, $unit, $barcode, $image]);
        if ($result) {
            logActivity('add_product', "Added product: $name (Price: $price / $unit, Stock: $stock)");
        }
        return $result;
    }
    
    public function updateProduct($id, $name, $category_id, $price, $stock, $unit = 'pc', $image = null) {
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE LOWER(name) = LOWER(?) AND id != ? AND archived = 0");
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("UPDATE products SET name=?, category_id=?, selling_price=?, stock_quantity=?, unit=?, image=? WHERE id=?");
        $result = $stmt->execute([$name, $category_id, $price, $stock, $unit, $image, $id]);
        if ($result) {
            logActivity('update_product', "Updated product: $name (ID: $id)");
        }
        return $result;
    }
    
    public function archiveProduct($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            $stmt = $this->pdo->prepare("UPDATE products SET archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
            
            $stmt = $this->pdo->prepare("INSERT INTO archive_history (product_id, product_name, action, archived_by) VALUES (?, ?, 'archived', ?)");
            $stmt->execute([$id, $product['name'], $_SESSION['user_id']]);
            
            logActivity('archive_product', "Archived product: {$product['name']} (ID: $id)");
            
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function restoreProduct($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            
            $stmt = $this->pdo->prepare("UPDATE products SET archived = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            $stmt = $this->pdo->prepare("INSERT INTO archive_history (product_id, product_name, action, restored_by, restored_date) VALUES (?, ?, 'restored', ?, NOW())");
            $stmt->execute([$id, $product['name'], $_SESSION['user_id']]);
            
            logActivity('restore_product', "Restored product: {$product['name']} (ID: $id)");
            
            return true;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function deleteProduct($id) {
        $stmt = $this->pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();
        
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result && $product) {
            logActivity('delete_product', "Deleted product: {$product['name']} (ID: $id)");
        }
        return $result;
    }
    
    public function updateStock($id, $quantity) {
        $stmt = $this->pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        return $stmt->execute([$quantity, $id]);
    }
    
    public function getArchiveHistory($search = '') {
        try {
            $sql = "
                SELECT ah.*, u1.full_name as archived_by_name, u2.full_name as restored_by_name 
                FROM archive_history ah
                LEFT JOIN users u1 ON ah.archived_by = u1.id
                LEFT JOIN users u2 ON ah.restored_by = u2.id
            ";
            $params = [];
            if (!empty($search)) {
                $sql .= " WHERE ah.product_name LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $sql .= " ORDER BY ah.archived_date DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getSalesByDate($start_date, $end_date) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.full_name as cashier, c.name as customer 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                LEFT JOIN customers c ON s.customer_id = c.id 
                WHERE DATE(s.sale_date) BETWEEN ? AND ?
                ORDER BY s.sale_date DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getSalesByCustomer($customer_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.full_name as cashier, c.name as customer 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                LEFT JOIN customers c ON s.customer_id = c.id 
                WHERE s.customer_id = ?
                ORDER BY s.sale_date DESC
            ");
            $stmt->execute([$customer_id]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
}

class CustomerManager {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public function getAllCustomers() {
        $stmt = $this->pdo->query("SELECT * FROM customers ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getCustomer($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function addCustomer($name, $email, $phone) {
        $stmt = $this->pdo->prepare("INSERT INTO customers (name, email, phone, loyalty_points) VALUES (?, ?, ?, 0)");
        $result = $stmt->execute([$name, $email, $phone]);
        if ($result) {
            logActivity('add_customer', "Added customer: $name");
        }
        return $result;
    }
    
    public function updateCustomer($id, $name, $email, $phone) {
        try {
            $stmt = $this->pdo->prepare("UPDATE customers SET name = ?, email = ?, phone = ? WHERE id = ?");
            $result = $stmt->execute([$name, $email, $phone, $id]);
            if ($result) {
                logActivity('update_customer', "Updated customer: $name (ID: $id)");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'Update failed'];
        } catch(PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function deleteCustomer($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM customers WHERE id = ?");
            $result = $stmt->execute([$id]);
            if ($result) {
                logActivity('delete_customer', "Deleted customer (ID: $id)");
                return ['success' => true];
            }
            return ['success' => false, 'message' => 'Delete failed'];
        } catch(PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function addLoyaltyPoints($customer_id, $points) {
        try {
            if (!$customer_id || $points <= 0) return false;
            $stmt = $this->pdo->prepare("UPDATE customers SET loyalty_points = COALESCE(loyalty_points, 0) + ? WHERE id = ?");
            $result = $stmt->execute([$points, $customer_id]);
            if ($result) {
                logActivity('add_loyalty_points', "Added $points loyalty points to customer ID: $customer_id");
            }
            return $result;
        } catch(PDOException $e) {
            return false;
        }
    }
    
    public function getCustomerPurchaseHistory($customer_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, u.full_name as cashier 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                WHERE s.customer_id = ? 
                ORDER BY s.sale_date DESC
            ");
            $stmt->execute([$customer_id]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getCustomerStats($customer_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(s.id) as total_orders,
                    COALESCE(SUM(s.total_amount), 0) as total_spent,
                    COALESCE(AVG(s.total_amount), 0) as average_order,
                    MAX(s.sale_date) as last_purchase,
                    MIN(s.sale_date) as first_purchase
                FROM sales s
                WHERE s.customer_id = ?
            ");
            $stmt->execute([$customer_id]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            return [
                'total_orders' => 0,
                'total_spent' => 0,
                'average_order' => 0,
                'last_purchase' => null,
                'first_purchase' => null
            ];
        }
    }
    
    public function getCustomerSpendingReport() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    c.id,
                    c.name,
                    c.email,
                    c.phone,
                    c.loyalty_points,
                    COUNT(s.id) as total_orders,
                    COALESCE(SUM(s.total_amount), 0) as total_spent,
                    COALESCE(AVG(s.total_amount), 0) as average_order,
                    MAX(s.sale_date) as last_purchase,
                    MIN(s.sale_date) as first_purchase
                FROM customers c
                LEFT JOIN sales s ON c.id = s.customer_id
                GROUP BY c.id
                ORDER BY total_spent DESC
            ");
            $result = $stmt->fetchAll();
            
            $totalRevenueStmt = $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales");
            $totalRevenue = $totalRevenueStmt->fetch()['total'];
            
            return [
                'customers' => $result,
                'total_revenue' => $totalRevenue
            ];
        } catch(PDOException $e) {
            return ['customers' => [], 'total_revenue' => 0];
        }
    }
}

class SaleManager {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public function createSale($items, $total, $customer_id = null, $amount_paid = 0, $change = 0, $discount = 0, $loyalty_points_used = 0, $payment_method = 'cash', $payment_token_id = null) {
        global $tax_rate;
        try {
            $this->pdo->beginTransaction();
            $invoice = 'SM-' . date('Ymd') . '-' . rand(1000, 9999);
            
            $gross_total = max(0, round(floatval($total), 2));
            $discount_amount = min($gross_total, max(0, round(floatval($discount), 2)));
            $total_with_discount = round($gross_total - $discount_amount, 2);
            
            // Displayed product prices include VAT, so extract VAT from the final gross total.
            $vat = round($total_with_discount * ($tax_rate / (1 + $tax_rate)), 2);
            $subtotal_ex_vat = round($total_with_discount - $vat, 2);
            $total_rounded = round($total_with_discount, 2);
            $amount_paid_rounded = round(floatval($amount_paid), 2);
            $change_rounded = round(floatval($change), 2);
            
            $payment_method = in_array($payment_method, ['cash', 'card', 'gcash', 'other'], true) ? $payment_method : 'cash';
            $stmt = $this->pdo->prepare("INSERT INTO sales (invoice_number, user_id, customer_id, subtotal, tax, total_amount, payment_method, amount_paid, change_amount, discount_amount, loyalty_points_used, payment_token_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$invoice, $_SESSION['user_id'], $customer_id, $subtotal_ex_vat, $vat, $total_rounded, $payment_method, $amount_paid_rounded, $change_rounded, $discount_amount, $loyalty_points_used, $payment_token_id]);
            $saleId = $this->pdo->lastInsertId();
            
            // Add loyalty points if customer - FIXED: Points properly calculated
            if ($customer_id) {
                $points = floor($total_rounded / 100); // 1 point per 100 spent
                if ($points > 0) {
                    $customerManager = new CustomerManager($this->pdo);
                    $customerManager->addLoyaltyPoints($customer_id, $points);
                }
            }
            
            // Make sure we actually have the stock before we touch anything
            foreach ($items as $item) {
                $stmt = $this->pdo->prepare("SELECT name, stock_quantity FROM products WHERE id = ?");
                $stmt->execute([$item['id']]);
                $product = $stmt->fetch();
                if (!$product) {
                    throw new Exception("Product not found for item");
                }
                if (intval($item['qty']) > intval($product['stock_quantity'])) {
                    throw new Exception("Not enough stock for {$product['name']} (only {$product['stock_quantity']} left)");
                }
            }

            foreach ($items as $item) {
                $stmt = $this->pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$saleId, $item['id'], $item['qty'], $item['price'], round($item['qty'] * $item['price'], 2)]);
                $stmt = $this->pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                $stmt->execute([$item['qty'], $item['id']]);

                $stockMove = $this->pdo->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, quantity_before, quantity_after, reason, reference_id, reference_type, user_id) SELECT ?, 'sale', ?, stock_quantity + ?, stock_quantity, CONCAT('Sale ', ?), ?, 'sale', ? FROM products WHERE id = ?");
                $stockMove->execute([$item['id'], -$item['qty'], $item['qty'], $invoice, $saleId, $_SESSION['user_id'], $item['id']]);
            }
            $this->pdo->commit();
            
            logActivity('create_sale', "Created sale: $invoice (Total: $total_rounded, VAT: $vat, Discount: $discount_amount, Loyalty: $loyalty_points_used)");
            
            return ['success' => true, 'invoice' => $invoice, 'sale_id' => $saleId];
        } catch(Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getSales($limit = 100) {
        try {
            $limit = (int)$limit;
            $stmt = $this->pdo->query("
                SELECT s.*, u.full_name as cashier, c.name as customer 
                FROM sales s 
                LEFT JOIN users u ON s.user_id = u.id 
                LEFT JOIN customers c ON s.customer_id = c.id 
                ORDER BY s.sale_date DESC 
                LIMIT $limit
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getSale($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT s.*, u.full_name as cashier, c.name as customer FROM sales s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN customers c ON s.customer_id = c.id WHERE s.id = ?");
            $stmt->execute([$id]);
            $sale = $stmt->fetch();
            if ($sale) {
                $stmt = $this->pdo->prepare("SELECT si.*, p.name as product_name, p.unit FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
                $stmt->execute([$id]);
                $sale['items'] = $stmt->fetchAll();
            }
            return $sale;
        } catch(PDOException $e) {
            return null;
        }
    }
}

class ReportManager {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public function getStats() {
        try {
            $stmt = $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) as today FROM sales WHERE DATE(sale_date) = CURDATE()");
            $today = $stmt->fetch()['today'];
            
            $stmt = $this->pdo->query("
                SELECT COALESCE(SUM(total_amount), 0) as month 
                FROM sales 
                WHERE MONTH(sale_date) = MONTH(CURDATE()) 
                AND YEAR(sale_date) = YEAR(CURDATE())
            ");
            $month = $stmt->fetch()['month'];
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM sales WHERE DATE(sale_date) = CURDATE()");
            $count = $stmt->fetch()['count'];
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as low FROM products WHERE stock_quantity <= 5 AND archived = 0");
            $low = $stmt->fetch()['low'];
            
            $stmt = $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales");
            $totalSales = $stmt->fetch()['total'];
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as total_transactions FROM sales");
            $totalTransactions = $stmt->fetch()['total_transactions'];
            
            return [
                'today' => $today, 
                'month' => $month, 
                'count' => $count, 
                'low' => $low, 
                'total_sales' => $totalSales,
                'total_transactions' => $totalTransactions
            ];
        } catch(PDOException $e) {
            return [
                'today' => 0, 
                'month' => 0, 
                'count' => 0, 
                'low' => 0, 
                'total_sales' => 0,
                'total_transactions' => 0
            ];
        }
    }
    
    public function getChartData($days = 7) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DATE(sale_date) as date, COALESCE(SUM(total_amount), 0) as total 
                FROM sales 
                WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                GROUP BY DATE(sale_date) 
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getTopProducts($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.name, 
                    p.unit,
                    c.name as category,
                    p.selling_price as price,
                    SUM(si.quantity) as sold, 
                    SUM(si.total_price) as revenue
                FROM sale_items si 
                JOIN products p ON si.product_id = p.id 
                LEFT JOIN categories c ON p.category_id = c.id
                GROUP BY p.id 
                ORDER BY sold DESC 
                LIMIT ?
            ");
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll();
            
            if (empty($result)) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        p.id,
                        p.name, 
                        p.unit,
                        NULL as category,
                        p.selling_price as price,
                        0 as sold, 
                        0 as revenue
                    FROM products p
                    WHERE p.archived = 0
                    ORDER BY p.name
                    LIMIT ?
                ");
                $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetchAll();
            }
            
            return $result;
        } catch(PDOException $e) {
            return [];
        }
    }
}

class UserManager {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public function getAllUsers() {
        try {
            $stmt = $this->pdo->query("SELECT id, username, full_name, role, created_at, last_activity FROM users ORDER BY id");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function addUser($username, $password, $full_name, $role = 'cashier') {
        $roles = array_values(array_intersect(getAssignableRoleKeys(), getUserRoles($role)));
        if (empty($roles)) return false;
        $role = implode(',', $roles);
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute([$username, $hashed, $full_name, $role]);
        if ($result) {
            logActivity('add_user', "Added user: $username ($role)");
        }
        return $result;
    }

    public function updateRoles($id, $roles) {
        if ((int)$id === 1) return false;
        $roles = array_values(array_intersect(getAssignableRoleKeys(), getUserRoles($roles)));
        if (empty($roles)) return false;
        $role = implode(',', $roles);
        $stmt = $this->pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $result = $stmt->execute([$role, $id]);
        if ($result) {
            logActivity('update_user_roles', "Updated roles for user ID $id: $role");
        }
        return $result;
    }

    public function saveCustomRole($roleName, $roleKey, $permissions) {
        global $pdo;
        $roleName = trim((string)$roleName);
        if ($roleName === '' || strlen($roleName) > 80) {
            return ['success' => false, 'message' => 'Role name must be between 1 and 80 characters.'];
        }
        $normalizedName = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $roleName), '_'));
        if (in_array($normalizedName, ['admin', 'cashier', 'inventory', 'hr', 'finance'], true)) {
            return ['success' => false, 'message' => 'Custom roles cannot use a built-in role name.'];
        }

        $isUpdate = $roleKey !== '';
        if ($roleKey === '') {
            $roleKey = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $roleName), '_'));
            $roleKey = substr($roleKey, 0, 50);
            if ($roleKey === '' || in_array($roleKey, ['admin', 'cashier', 'inventory', 'hr', 'finance'], true)) {
                return ['success' => false, 'message' => 'Choose a role name that is different from the built-in roles.'];
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM custom_roles WHERE role_key = ?");
            $stmt->execute([$roleKey]);
            if ((int)$stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'A role with that name already exists.'];
            }
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM custom_roles WHERE role_key = ?");
            $stmt->execute([$roleKey]);
            if ((int)$stmt->fetchColumn() === 0) {
                return ['success' => false, 'message' => 'That custom role no longer exists.'];
            }
        }

        $permissionKeys = array_values(array_intersect(array_keys(getRolePermissionOptions()), (array)$permissions));
        $encodedPermissions = json_encode($permissionKeys);
        if ($isUpdate) {
            $stmt = $pdo->prepare("UPDATE custom_roles SET role_name = ?, permissions = ? WHERE role_key = ?");
            $saved = $stmt->execute([$roleName, $encodedPermissions, $roleKey]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO custom_roles (role_key, role_name, permissions) VALUES (?, ?, ?)");
            $saved = $stmt->execute([$roleKey, $roleName, $encodedPermissions]);
        }
        if ($saved) {
            logActivity('save_custom_role', "Saved custom role: $roleName ($roleKey)");
            return ['success' => true, 'role_key' => $roleKey];
        }
        return ['success' => false, 'message' => 'Unable to save this role.'];
    }

    public function deleteCustomRole($roleKey) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, role FROM users");
        $stmt->execute();
        foreach ($stmt->fetchAll() as $user) {
            if (in_array($roleKey, getUserRoles($user['role']), true)) {
                return ['success' => false, 'message' => 'This role is assigned to a user. Reassign those users before deleting it.'];
            }
        }
        $stmt = $pdo->prepare("DELETE FROM custom_roles WHERE role_key = ?");
        $stmt->execute([$roleKey]);
        if ($stmt->rowCount() > 0) {
            logActivity('delete_custom_role', "Deleted custom role: $roleKey");
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'That custom role no longer exists.'];
    }
    
    public function updatePassword($username, $new_password) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        $result = $stmt->execute([$hashed, $username]);
        if ($result) {
            logActivity('reset_password', "Reset password for user: $username");
        }
        return $result;
    }
    
    public function deleteUser($id) {
        if ($id == 1) return false;
        
        $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result && $user) {
            logActivity('delete_user', "Deleted user: {$user['username']} (ID: $id)");
        }
        return $result;
    }
}

class ActivityLogManager {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public function getActivityLog($limit = 100) {
        try {
            $limit = (int)$limit;
            $stmt = $this->pdo->query("
                SELECT 
                    al.*,
                    u.full_name as user_full_name
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT $limit
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getRecentActivities($limit = 20) {
        try {
            $limit = (int)$limit;
            $stmt = $this->pdo->query("
                SELECT 
                    al.*,
                    u.full_name as user_full_name
                FROM activity_log al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT $limit
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            return [];
        }
    }
    
    public function getActivityStats() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM activity_log");
            $total = $stmt->fetch()['total'];
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as today FROM activity_log WHERE DATE(created_at) = CURDATE()");
            $today = $stmt->fetch()['today'];
            
            $stmt = $this->pdo->query("
                SELECT action, COUNT(*) as count 
                FROM activity_log 
                GROUP BY action 
                ORDER BY count DESC 
                LIMIT 10
            ");
            $actions = $stmt->fetchAll();
            
            return [
                'total' => $total,
                'today' => $today,
                'actions' => $actions
            ];
        } catch(PDOException $e) {
            return ['total' => 0, 'today' => 0, 'actions' => []];
        }
    }
}

// ============================================
// HR management class
// ============================================

// ============================================
// HR management class
// ============================================

// ============================================
// HR management class
// ============================================

class HRManager {
    private $pdo;
    
    public function __construct($pdo) { 
        $this->pdo = $pdo; 
    }
    
    private function createTablesIfNeeded() {
        try {
            // Check if employees table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'employees'");
            if (!$stmt->fetch()) {
                $sql = "CREATE TABLE IF NOT EXISTS employees (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id VARCHAR(50) UNIQUE NOT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    email VARCHAR(100),
                    phone VARCHAR(20),
                    address TEXT,
                    position VARCHAR(100),
                    department VARCHAR(100),
                    salary DECIMAL(12,2) DEFAULT 0,
                    salary_type ENUM('monthly', 'semi_monthly', 'daily', 'hourly') DEFAULT 'semi_monthly',
                    daily_rate DECIMAL(10,2) DEFAULT 0,
                    hourly_rate DECIMAL(10,2) DEFAULT 0,
                    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
                    role VARCHAR(50),
                    emergency_contact_name VARCHAR(100),
                    emergency_contact_phone VARCHAR(20),
                    start_date DATE,
                    contract_end_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $this->pdo->exec($sql);
            }
            
            // Check if leave_requests table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'leave_requests'");
            if (!$stmt->fetch()) {
                $sql = "CREATE TABLE IF NOT EXISTS leave_requests (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    leave_type VARCHAR(50) NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    reason TEXT,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    INDEX idx_employee (employee_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $this->pdo->exec($sql);
            }
            
            // Check if attendance table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'attendance'");
            if (!$stmt->fetch()) {
                $sql = "CREATE TABLE IF NOT EXISTS attendance (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    date DATE NOT NULL,
                    time_in DATETIME,
                    time_out DATETIME,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    INDEX idx_employee_date (employee_id, date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $this->pdo->exec($sql);
            }
            
            // Check if payroll table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'payroll'");
            if (!$stmt->fetch()) {
                $sql = "CREATE TABLE IF NOT EXISTS payroll (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    amount DECIMAL(12,2) NOT NULL,
                    period_start DATE NOT NULL,
                    period_end DATE NOT NULL,
                    payroll_type ENUM('monthly', 'semi_monthly', 'daily', 'hourly') DEFAULT 'semi_monthly',
                    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    INDEX idx_employee (employee_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $this->pdo->exec($sql);
            }
            
            // Check if attendance_hours table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'attendance_hours'");
            if (!$stmt->fetch()) {
                $sql = "CREATE TABLE IF NOT EXISTS attendance_hours (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    date DATE NOT NULL,
                    hours_worked DECIMAL(5,2) DEFAULT 0,
                    overtime_hours DECIMAL(5,2) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    INDEX idx_employee_date (employee_id, date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $this->pdo->exec($sql);
            }
            
            // Check if payroll_notifications table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'payroll_notifications'");
            if (!$stmt->fetch()) {
                $sql = "CREATE TABLE IF NOT EXISTS payroll_notifications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    employee_id INT NOT NULL,
                    payroll_id INT NOT NULL,
                    notification_type ENUM('email', 'system') DEFAULT 'system',
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                    message TEXT,
                    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
                    FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE CASCADE,
                    INDEX idx_employee (employee_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $this->pdo->exec($sql);
            }
            
            return true;
        } catch(PDOException $e) {
            error_log("HRManager::createTablesIfNeeded error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getEmployees() {
        try {
            $this->createTablesIfNeeded();
            $stmt = $this->pdo->query("SELECT * FROM employees ORDER BY employee_id");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("HRManager::getEmployees error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getEmployee($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("HRManager::getEmployee error: " . $e->getMessage());
            return null;
        }
    }
    
    public function saveEmployee($data) {
        try {
            $this->createTablesIfNeeded();

            // Friendly duplicate check before hitting the unique constraints
            $email = !empty($data['email']) ? $data['email'] : null;
            $excludeId = (isset($data['id']) && $data['id']) ? intval($data['id']) : 0;
            if (!empty($data['employee_id'])) {
                $chk = $this->pdo->prepare("SELECT id FROM employees WHERE employee_id = ? AND id <> ?");
                $chk->execute([$data['employee_id'], $excludeId]);
                if ($chk->fetch()) { error_log("HRManager::saveEmployee: duplicate employee_id {$data['employee_id']}"); return false; }
            }
            if ($email) {
                $chk = $this->pdo->prepare("SELECT id FROM employees WHERE email = ? AND id <> ?");
                $chk->execute([$email, $excludeId]);
                if ($chk->fetch()) { error_log("HRManager::saveEmployee: duplicate email $email"); return false; }
            }

            if (isset($data['id']) && $data['id']) {
                $stmt = $this->pdo->prepare("
                    UPDATE employees SET 
                        employee_id = ?, 
                        first_name = ?, 
                        last_name = ?, 
                        email = ?, 
                        phone = ?, 
                        address = ?, 
                        position = ?, 
                        department = ?, 
                        salary = ?, 
                        salary_type = ?, 
                        daily_rate = ?,
                        hourly_rate = ?,
                        status = ?, 
                        start_date = ?, 
                        contract_end_date = ?, 
                        role = ?, 
                        emergency_contact_name = ?, 
                        emergency_contact_phone = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $data['employee_id'],
                    $data['first_name'],
                    $data['last_name'],
                    !empty($data['email']) ? $data['email'] : null,
                    $data['phone'] ?? '',
                    $data['address'] ?? '',
                    $data['position'] ?? '',
                    $data['department'] ?? '',
                    $data['salary'] ?? 0,
                    $data['salary_type'] ?? 'semi_monthly',
                    $data['daily_rate'] ?? 0,
                    $data['hourly_rate'] ?? 0,
                    $data['status'] ?? 'active',
                    (!empty($data['start_date'])) ? $data['start_date'] : null,
                    (!empty($data['contract_end_date'])) ? $data['contract_end_date'] : null,
                    $data['role'] ?? null,
                    $data['emergency_name'] ?? '',
                    $data['emergency_phone'] ?? '',
                    $data['id']
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO employees (
                        employee_id, first_name, last_name, email, phone, address, 
                        position, department, salary, salary_type, daily_rate, hourly_rate,
                        status, start_date, contract_end_date, role, 
                        emergency_contact_name, emergency_contact_phone
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                return $stmt->execute([
                    $data['employee_id'],
                    $data['first_name'],
                    $data['last_name'],
                    !empty($data['email']) ? $data['email'] : null,
                    $data['phone'] ?? '',
                    $data['address'] ?? '',
                    $data['position'] ?? '',
                    $data['department'] ?? '',
                    $data['salary'] ?? 0,
                    $data['salary_type'] ?? 'semi_monthly',
                    $data['daily_rate'] ?? 0,
                    $data['hourly_rate'] ?? 0,
                    $data['status'] ?? 'active',
                    $data['start_date'] ?? null,
                    $data['contract_end_date'] ?? null,
                    $data['role'] ?? null,
                    $data['emergency_name'] ?? '',
                    $data['emergency_phone'] ?? ''
                ]);
            }
        } catch(PDOException $e) {
            error_log("HRManager::saveEmployee error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteEmployee($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM employees WHERE id = ?");
            return $stmt->execute([$id]);
        } catch(PDOException $e) {
            error_log("HRManager::deleteEmployee error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAttendance($date = null) {
        try {
            $this->createTablesIfNeeded();
            if (!$date) $date = date('Y-m-d');
            $stmt = $this->pdo->prepare("
                SELECT a.*, e.first_name, e.last_name, e.employee_id 
                FROM attendance a 
                LEFT JOIN employees e ON a.employee_id = e.id 
                WHERE a.date = ? 
                ORDER BY a.id DESC
            ");
            $stmt->execute([$date]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("HRManager::getAttendance error: " . $e->getMessage());
            return [];
        }
    }
    
    public function clockIn($employee_id) {
        try {
            $this->createTablesIfNeeded();
            
            // Check if employee exists
            $stmt = $this->pdo->prepare("SELECT id FROM employees WHERE id = ?");
            $stmt->execute([$employee_id]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Employee not found'];
            }
            
            // Check if already clocked in today (not clocked out yet)
            $stmt = $this->pdo->prepare("SELECT id, time_in FROM attendance WHERE employee_id = ? AND date = CURDATE() AND time_out IS NULL");
            $stmt->execute([$employee_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                return ['success' => false, 'message' => 'Already clocked in at ' . date('H:i', strtotime($existing['time_in']))];
            }
            
            // Check if already has attendance today (clocked out already)
            $stmt = $this->pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = CURDATE()");
            $stmt->execute([$employee_id]);
            if ($stmt->fetch()) {
                // Already has attendance today, create new entry for new shift
                $stmt = $this->pdo->prepare("INSERT INTO attendance (employee_id, date, time_in) VALUES (?, CURDATE(), NOW())");
                $result = $stmt->execute([$employee_id]);
                if ($result) {
                    return ['success' => true, 'message' => 'Clocked in successfully'];
                }
                return ['success' => false, 'message' => 'Failed to clock in'];
            }
            
            // First clock in today
            $stmt = $this->pdo->prepare("INSERT INTO attendance (employee_id, date, time_in) VALUES (?, CURDATE(), NOW())");
            $result = $stmt->execute([$employee_id]);
            if ($result) {
                return ['success' => true, 'message' => 'Clocked in successfully'];
            }
            return ['success' => false, 'message' => 'Failed to clock in'];
            
        } catch(PDOException $e) {
            error_log("HRManager::clockIn error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function clockOut($employee_id) {
        try {
            $this->createTablesIfNeeded();
            
            // Check if employee exists
            $stmt = $this->pdo->prepare("SELECT id FROM employees WHERE id = ?");
            $stmt->execute([$employee_id]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Employee not found'];
            }
            
            // Check if clocked in today
            $stmt = $this->pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = CURDATE() AND time_out IS NULL");
            $stmt->execute([$employee_id]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Not clocked in today'];
            }
            
            $stmt = $this->pdo->prepare("UPDATE attendance SET time_out = NOW() WHERE employee_id = ? AND date = CURDATE() AND time_out IS NULL");
            $result = $stmt->execute([$employee_id]);
            if ($result) {
                return ['success' => true, 'message' => 'Clocked out successfully'];
            }
            return ['success' => false, 'message' => 'Failed to clock out'];
            
        } catch(PDOException $e) {
            error_log("HRManager::clockOut error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getLeaveRequests($status = null) {
        try {
            $this->createTablesIfNeeded();
            $sql = "SELECT l.*, e.first_name, e.last_name, e.employee_id FROM leave_requests l JOIN employees e ON l.employee_id = e.id";
            if ($status) {
                $sql .= " WHERE l.status = ?";
            }
            $sql .= " ORDER BY l.created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            if ($status) {
                $stmt->execute([$status]);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("HRManager::getLeaveRequests error: " . $e->getMessage());
            return [];
        }
    }
    
    public function createLeaveRequest($employee_id, $type, $start_date, $end_date, $reason) {
        try {
            $this->createTablesIfNeeded();
            
            // Check if employee exists
            $stmt = $this->pdo->prepare("SELECT id FROM employees WHERE id = ?");
            $stmt->execute([$employee_id]);
            if (!$stmt->fetch()) {
                error_log("HRManager::createLeaveRequest - Employee not found: $employee_id");
                return false;
            }
            
            // Validate dates
            if (empty($start_date) || empty($end_date)) {
                error_log("HRManager::createLeaveRequest - Invalid dates: start=$start_date, end=$end_date");
                return false;
            }
            
            // Check for overlapping leave requests
            $stmt = $this->pdo->prepare("
                SELECT id FROM leave_requests 
                WHERE employee_id = ? 
                AND status != 'rejected'
                AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))
            ");
            $stmt->execute([$employee_id, $end_date, $start_date, $start_date, $end_date]);
            if ($stmt->fetch()) {
                error_log("HRManager::createLeaveRequest - Overlapping leave request for employee: $employee_id");
                return false;
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            return $stmt->execute([$employee_id, $type, $start_date, $end_date, $reason]);
            
        } catch(PDOException $e) {
            error_log("HRManager::createLeaveRequest error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateLeaveStatus($id, $status) {
        try {
            $this->createTablesIfNeeded();
            $stmt = $this->pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch(PDOException $e) {
            error_log("HRManager::updateLeaveStatus error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getPayroll($status = null) {
        try {
            $this->createTablesIfNeeded();
            $sql = "SELECT p.*, e.first_name, e.last_name, e.employee_id FROM payroll p JOIN employees e ON p.employee_id = e.id";
            if ($status) {
                $sql .= " WHERE p.status = ?";
            }
            $sql .= " ORDER BY p.created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            if ($status) {
                $stmt->execute([$status]);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("HRManager::getPayroll error: " . $e->getMessage());
            return [];
        }
    }
    
    public function createPayroll($employee_id, $amount, $period_start, $period_end, $type = 'semi_monthly') {
        try {
            $this->createTablesIfNeeded();
            $stmt = $this->pdo->prepare("INSERT INTO payroll (employee_id, amount, period_start, period_end, payroll_type, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            return $stmt->execute([$employee_id, $amount, $period_start, $period_end, $type]);
        } catch(PDOException $e) {
            error_log("HRManager::createPayroll error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updatePayrollStatus($id, $status) {
        try {
            $this->createTablesIfNeeded();
            $stmt = $this->pdo->prepare("UPDATE payroll SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch(PDOException $e) {
            error_log("HRManager::updatePayrollStatus error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getHRStats() {
        try {
            $this->createTablesIfNeeded();
            $stats = [
                'total' => 0,
                'active' => 0,
                'attendance_today' => 0,
                'pending_leaves' => 0,
                'pending_payroll' => 0
            ];
            
            // Get total employees
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM employees");
            $stats['total'] = $stmt->fetch()['total'] ?? 0;
            
            // Get active employees
            $stmt = $this->pdo->query("SELECT COUNT(*) as active FROM employees WHERE status = 'active'");
            $stats['active'] = $stmt->fetch()['active'] ?? 0;
            
            // Get attendance today
            $stmt = $this->pdo->query("SELECT COUNT(*) as today FROM attendance WHERE date = CURDATE()");
            $result = $stmt->fetch();
            $stats['attendance_today'] = $result ? (int)$result['today'] : 0;
            
            // Get pending leaves
            $stmt = $this->pdo->query("SELECT COUNT(*) as pending_leaves FROM leave_requests WHERE status = 'pending'");
            $result = $stmt->fetch();
            $stats['pending_leaves'] = $result ? (int)$result['pending_leaves'] : 0;
            
            // Get pending payroll
            $stmt = $this->pdo->query("SELECT COUNT(*) as pending_payroll FROM payroll WHERE status = 'pending'");
            $result = $stmt->fetch();
            $stats['pending_payroll'] = $result ? (int)$result['pending_payroll'] : 0;
            
            return $stats;
        } catch(PDOException $e) {
            error_log("HRManager::getHRStats error: " . $e->getMessage());
            return [
                'total' => 0,
                'active' => 0,
                'attendance_today' => 0,
                'pending_leaves' => 0,
                'pending_payroll' => 0
            ];
        }
    }
    
    // ============================================
    // AUTO PAYROLL METHODS
    // ============================================
    
    public function checkAndRunAutoPayroll() {
        $today = date('Y-m-d');
        $day = date('d');
        $is_15th = ($day == 15);
        $is_end_of_month = ($day == date('t'));
        
        if ($is_15th || $is_end_of_month) {
            // Check if already run today
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count FROM activity_log 
                WHERE action = 'auto_payroll' 
                AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute();
            $existing = $stmt->fetch();
            
            if ($existing['count'] == 0) {
                return $this->processAutoPayroll($today);
            }
        }
        
        return ['success' => false, 'message' => 'Not a payroll day or already processed'];
    }
    
    public function processAutoPayroll($process_date = null) {
        if (!$process_date) {
            $process_date = date('Y-m-d');
        }
        
        $day = date('d', strtotime($process_date));
        $is_15th = ($day == 15);
        $is_end_of_month = ($day == date('t', strtotime($process_date)));
        
        if (!$is_15th && !$is_end_of_month) {
            return [
                'success' => false, 
                'message' => 'Auto payroll only runs on 15th and end of month.'
            ];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Get all active employees
            $stmt = $this->pdo->prepare("
                SELECT * FROM employees 
                WHERE status = 'active' 
                AND salary > 0
            ");
            $stmt->execute();
            $employees = $stmt->fetchAll();
            
            if (empty($employees)) {
                return ['success' => false, 'message' => 'No active employees with salary found'];
            }
            
            $processed_count = 0;
            $total_amount = 0;
            $summary = [
                'monthly' => 0,
                'semi_monthly' => 0,
                'daily' => 0,
                'hourly' => 0
            ];
            
            foreach ($employees as $employee) {
                // Calculate salary based on employee type
                $calc_result = $this->calculateEmployeeSalary($employee, $process_date);
                
                if (!$calc_result['success'] || $calc_result['amount'] <= 0) {
                    continue;
                }
                
                // Check if already processed for this period
                if ($this->payrollExists($employee['id'], $calc_result['period_start'], $calc_result['period_end'])) {
                    continue;
                }
                
                // Create payroll record
                $stmt = $this->pdo->prepare("
                    INSERT INTO payroll 
                    (employee_id, amount, period_start, period_end, payroll_type, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'paid', NOW())
                ");
                $stmt->execute([
                    $employee['id'],
                    $calc_result['amount'],
                    $calc_result['period_start'],
                    $calc_result['period_end'],
                    $calc_result['payroll_type']
                ]);
                
                $payroll_id = $this->pdo->lastInsertId();
                $processed_count++;
                $total_amount += $calc_result['amount'];
                
                // Track by type
                $type = $calc_result['payroll_type'];
                if (isset($summary[$type])) {
                    $summary[$type]++;
                }
                
                // Create notification
                $this->createPayrollNotification(
                    $employee['id'],
                    $payroll_id,
                    "Payroll for {$calc_result['period_label']}: ₱" . number_format($calc_result['amount'], 2)
                );
            }
            
            $this->pdo->commit();
            
            $summary_message = [];
            foreach ($summary as $type => $count) {
                if ($count > 0) {
                    $summary_message[] = "$count $type";
                }
            }
            
            logActivity('auto_payroll', "Auto payroll processed: $processed_count employees, Total: ₱$total_amount");
            
            return [
                'success' => true,
                'processed' => $processed_count,
                'total_amount' => $total_amount,
                'summary' => $summary,
                'message' => "✅ Payroll processed for $processed_count employees (" . implode(', ', $summary_message) . "). Total: ₱" . number_format($total_amount, 2)
            ];
            
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            error_log("HRManager::processAutoPayroll error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function calculateEmployeeSalary($employee, $process_date) {
        $salary_type = $employee['salary_type'] ?? 'semi_monthly';
        $amount = 0;
        $period_start = '';
        $period_end = '';
        $period_label = '';
        $day = date('d', strtotime($process_date));
        
        switch ($salary_type) {
            case 'monthly':
                // Only pay at end of month
                if ($day == date('t', strtotime($process_date))) {
                    $amount = floatval($employee['salary']);
                    $period_start = date('Y-m-01', strtotime($process_date));
                    $period_end = date('Y-m-t', strtotime($process_date));
                    $period_label = 'Monthly - ' . date('F Y', strtotime($process_date));
                }
                break;
                
            case 'semi_monthly':
                $monthly_salary = floatval($employee['salary']);
                if ($day == 15) {
                    $amount = $monthly_salary / 2;
                    $period_start = date('Y-m-01', strtotime($process_date));
                    $period_end = date('Y-m-15', strtotime($process_date));
                    $period_label = '1st Half - ' . date('F Y', strtotime($process_date));
                } elseif ($day == date('t', strtotime($process_date))) {
                    $amount = $monthly_salary / 2;
                    $period_start = date('Y-m-16', strtotime($process_date));
                    $period_end = date('Y-m-t', strtotime($process_date));
                    $period_label = '2nd Half - ' . date('F Y', strtotime($process_date));
                }
                break;
                
            case 'daily':
                $daily_rate = floatval($employee['daily_rate'] ?? 0);
                if ($daily_rate > 0) {
                    // Get attendance for today
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) as days_worked 
                        FROM attendance 
                        WHERE employee_id = ? 
                        AND date = ?
                        AND time_in IS NOT NULL
                        AND time_out IS NOT NULL
                    ");
                    $stmt->execute([$employee['id'], $process_date]);
                    $attendance = $stmt->fetch();
                    
                    if ($attendance['days_worked'] > 0) {
                        $amount = $daily_rate;
                        $period_start = $process_date;
                        $period_end = $process_date;
                        $period_label = 'Daily - ' . date('M d, Y', strtotime($process_date));
                    }
                }
                break;
                
            case 'hourly':
                $hourly_rate = floatval($employee['hourly_rate'] ?? 0);
                if ($hourly_rate > 0) {
                    // Get hours worked today
                    $stmt = $this->pdo->prepare("
                        SELECT COALESCE(SUM(hours_worked), 0) as total_hours 
                        FROM attendance_hours 
                        WHERE employee_id = ? 
                        AND date = ?
                    ");
                    $stmt->execute([$employee['id'], $process_date]);
                    $hours = $stmt->fetch();
                    
                    if ($hours['total_hours'] > 0) {
                        $amount = $hourly_rate * $hours['total_hours'];
                        $period_start = $process_date;
                        $period_end = $process_date;
                        $period_label = 'Hourly - ' . date('M d, Y', strtotime($process_date));
                    }
                }
                break;
                
            default:
                return ['success' => false, 'message' => 'Unknown salary type: ' . $salary_type];
        }
        
        if ($amount > 0) {
            return [
                'success' => true,
                'amount' => $amount,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'payroll_type' => $salary_type,
                'period_label' => $period_label,
                'employee_id' => $employee['id'],
                'employee_name' => $employee['first_name'] . ' ' . $employee['last_name']
            ];
        }
        
        return ['success' => false, 'message' => 'No salary to process'];
    }
    
    public function payrollExists($employee_id, $period_start, $period_end) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM payroll 
            WHERE employee_id = ? 
            AND period_start = ? 
            AND period_end = ?
        ");
        $stmt->execute([$employee_id, $period_start, $period_end]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    public function createPayrollNotification($employee_id, $payroll_id, $message) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO payroll_notifications 
                (employee_id, payroll_id, notification_type, message, status, sent_at) 
                VALUES (?, ?, 'system', ?, 'sent', NOW())
            ");
            return $stmt->execute([$employee_id, $payroll_id, $message]);
        } catch(PDOException $e) {
            error_log("HRManager::createPayrollNotification error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getPayrollNotifications($employee_id, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT pn.*, p.amount, p.period_start, p.period_end
                FROM payroll_notifications pn
                JOIN payroll p ON pn.payroll_id = p.id
                WHERE pn.employee_id = ?
                ORDER BY pn.sent_at DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $employee_id);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("HRManager::getPayrollNotifications error: " . $e->getMessage());
            return [];
        }
    }
    
}

// ============================================
// FINANCE CLASS
// ============================================

class FinanceManager {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    
    public function getDashboardStats() {
        try {
            $stmt = $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) as today FROM sales WHERE DATE(sale_date) = CURDATE()");
            $today = $stmt->fetch()['today'];
            
            $stmt = $this->pdo->query("
                SELECT COALESCE(SUM(total_amount), 0) as month 
                FROM sales 
                WHERE MONTH(sale_date) = MONTH(CURDATE()) 
                AND YEAR(sale_date) = YEAR(CURDATE())
            ");
            $month = $stmt->fetch()['month'];
            
            $stmt = $this->pdo->query("
                SELECT COALESCE(SUM(total_amount), 0) as year 
                FROM sales 
                WHERE YEAR(sale_date) = YEAR(CURDATE())
            ");
            $year = $stmt->fetch()['year'];
            
            $stmt = $this->pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales");
            $total = $stmt->fetch()['total'];
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM sales");
            $transactions = $stmt->fetch()['count'];
            
            $stmt = $this->pdo->query("SELECT COUNT(*) as customers FROM customers");
            $customers = $stmt->fetch()['customers'];
            
            return [
                'today' => $today,
                'month' => $month,
                'year' => $year,
                'total' => $total,
                'transactions' => $transactions,
                'customers' => $customers
            ];
        } catch(PDOException $e) {
            error_log("FinanceManager::getDashboardStats error: " . $e->getMessage());
            return [
                'today' => 0,
                'month' => 0,
                'year' => 0,
                'total' => 0,
                'transactions' => 0,
                'customers' => 0
            ];
        }
    }
    
    public function getMonthlyRevenue($year = null) {
        try {
            if (!$year) $year = date('Y');
            $stmt = $this->pdo->prepare("
                SELECT 
                    MONTH(sale_date) as month,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COUNT(*) as transactions
                FROM sales 
                WHERE YEAR(sale_date) = ?
                GROUP BY MONTH(sale_date)
                ORDER BY month ASC
            ");
            $stmt->execute([$year]);
            $results = $stmt->fetchAll();
            
            // Fill in missing months with zero
            $monthlyData = [];
            for ($i = 1; $i <= 12; $i++) {
                $monthlyData[$i] = ['revenue' => 0, 'transactions' => 0];
            }
            foreach ($results as $row) {
                $monthlyData[(int)$row['month']] = [
                    'revenue' => (float)$row['revenue'],
                    'transactions' => (int)$row['transactions']
                ];
            }
            return $monthlyData;
        } catch(PDOException $e) {
            error_log("FinanceManager::getMonthlyRevenue error: " . $e->getMessage());
            return [];
        }
    }
    
        public function getTopCustomers($limit = 10) {
    try {
        $stmt = $this->pdo->prepare("
            SELECT 
                c.id,
                c.name,
                c.email,
                c.phone,
                c.loyalty_points,
                COUNT(s.id) as orders,
                COALESCE(SUM(s.total_amount), 0) as total_spent
            FROM customers c
            LEFT JOIN sales s ON c.id = s.customer_id
            GROUP BY c.id
            ORDER BY total_spent DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        // If no results with sales, return customers with 0 orders
        if (empty($result)) {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    name,
                    email,
                    phone,
                    loyalty_points,
                    0 as orders,
                    0 as total_spent
                FROM customers
                ORDER BY name
                LIMIT " . (int)$limit . "
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        }
        
        return $result;
    } catch(PDOException $e) {
        error_log("FinanceManager::getTopCustomers error: " . $e->getMessage());
        return [];
    }
}
    
    public function getRevenueByDay($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(sale_date) as date,
                    COALESCE(SUM(total_amount), 0) as revenue,
                    COUNT(*) as transactions
                FROM sales 
                WHERE DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY DATE(sale_date)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("FinanceManager::getRevenueByDay error: " . $e->getMessage());
            return [];
        }
    }
}

// ============================================
// RETURNS MANAGER CLASS
// ============================================

class ReturnManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Get all returns
    public function getAllReturns() {
        try {
            $stmt = $this->pdo->query("
                SELECT r.*, 
                       s.invoice_number as original_invoice,
                       u.full_name as created_by_name,
                       a.full_name as approved_by_name,
                       c.name as customer_name
                FROM returns r
                LEFT JOIN sales s ON r.original_sale_id = s.id
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN users a ON r.approved_by = a.id
                LEFT JOIN customers c ON s.customer_id = c.id
                ORDER BY r.created_at DESC
            ");
            return $stmt->fetchAll();
        } catch(PDOException $e) {
            error_log("ReturnManager::getAllReturns error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get return by ID
    public function getReturn($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.*, 
                       s.invoice_number as original_invoice,
                       u.full_name as created_by_name,
                       a.full_name as approved_by_name
                FROM returns r
                LEFT JOIN sales s ON r.original_sale_id = s.id
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN users a ON r.approved_by = a.id
                WHERE r.id = ?
            ");
            $stmt->execute([$id]);
            $return = $stmt->fetch();
            
            if ($return) {
                // Get return items
                $stmt = $this->pdo->prepare("
                    SELECT ri.*, p.name as product_name, p.unit
                    FROM return_items ri
                    JOIN products p ON ri.product_id = p.id
                    WHERE ri.return_id = ?
                ");
                $stmt->execute([$id]);
                $return['items'] = $stmt->fetchAll();
            }
            return $return;
        } catch(PDOException $e) {
            error_log("ReturnManager::getReturn error: " . $e->getMessage());
            return null;
        }
    }
    
    public function createReturn($sale_id, $items, $reason, $notes, $user_id) {
    try {
        $this->pdo->beginTransaction();
        
        // Check if sale exists
        $stmt = $this->pdo->prepare("SELECT * FROM sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $sale = $stmt->fetch();
        
        if (!$sale) {
            return ['success' => false, 'message' => 'Sale not found'];
        }

                // ============================================
        // Check for previous returns on this sale
        // ============================================
        // Check if this sale already has a return
        // Check if this sale already has ANY return (including rejected)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as return_count 
                FROM returns 
                WHERE original_sale_id = ?
            ");
            $stmt->execute([$sale_id]);
            $existing = $stmt->fetch();

            if ($existing['return_count'] > 0) {
                return [
                    'success' => false, 
                    'message' => '❌ This sale has already been processed for a return. You cannot return items from this sale again.'
                ];
            }

        // Check if any of the items have been returned before (including rejected)
        foreach ($items as $item) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as returned_count 
                FROM return_items ri
                JOIN returns r ON ri.return_id = r.id
                WHERE ri.product_id = ? 
                AND r.original_sale_id = ?
            ");
            $stmt->execute([$item['product_id'], $sale_id]);
            $existing = $stmt->fetch();
            
            if ($existing['returned_count'] > 0) {
                $stmt = $this->pdo->prepare("SELECT name FROM products WHERE id = ?");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();
                
                return [
                    'success' => false, 
                    'message' => '❌ Product "' . $product['name'] . '" has already been returned from this sale.'
                ];
            }
        }
// ============================================
// END OF DOUBLE RETURN CHECK
// ============================================

        // ============================================
        // Enforce the configured return time limit
        // ============================================
        $return_hours = getSetting('return_hours', 20);
        
        // Check if returns are allowed (0 = no returns)
        if ($return_hours == 0) {
            return ['success' => false, 'message' => 'Returns are not allowed.'];
        }
        
        // Calculate hours since purchase
        $sale_date = new DateTime($sale['sale_date']);
        $now = new DateTime();
        $hours_diff = $sale_date->diff($now)->h + ($sale_date->diff($now)->days * 24);
        
        // Check if within return period
        if ($hours_diff >= $return_hours) {
            return [
                'success' => false, 
                'message' => "⏰ Return period has expired! You can only return items within $return_hours hours of purchase. (This sale is $hours_diff hours old)"
            ];
        }
        

        // Generate return number
        $return_number = 'RET-' . date('Ymd') . '-' . rand(1000, 9999);
        
        // Calculate total refund - JUST ADD UP THE ITEMS
        $total_refund = 0;
        foreach ($items as $item) {
            if (isset($item['refund_amount']) && $item['refund_amount'] !== '') {
                $total_refund += floatval($item['refund_amount']);
            } else {
                $unitPrice = isset($item['unit_price']) ? floatval($item['unit_price']) : 0;
                $total_refund += floatval($item['quantity']) * $unitPrice;
                $itemsById[$item['product_id']] = $unitPrice;
            }
        }
        
        // Round to 2 decimal places
        $total_refund = round($total_refund, 2);
        
        // ============================================
        // FIX: NO TAX CALCULATION - Use exact amount
        // ============================================
        // subtotal = total_refund (exact amount)
        // tax = 0 (we don't calculate tax separately)
        // total_refund = exact amount customer paid
        // ============================================
        
        // Insert return record - ALL THREE AMOUNTS ARE THE SAME
        $stmt = $this->pdo->prepare("
            INSERT INTO returns 
            (return_number, original_sale_id, return_date, subtotal, tax, total_refund, reason, notes, status, user_id) 
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([
            $return_number,
            $sale_id,
            $total_refund,      // subtotal = exact refund amount
            0,                  // tax = 0 (no separate tax)
            $total_refund,      // total_refund = exact amount
            $reason,
            $notes,
            $user_id
        ]);
        
        $return_id = $this->pdo->lastInsertId();
        
        // Insert return items
        foreach ($items as $item) {
            $unitPrice = isset($item['unit_price']) ? floatval($item['unit_price']) : 0;
            if ($unitPrice <= 0) {
                $stmt = $this->pdo->prepare("SELECT unit_price FROM sale_items WHERE sale_id = ? AND product_id = ? LIMIT 1");
                $stmt->execute([$sale_id, $item['product_id']]);
                $row = $stmt->fetch();
                $unitPrice = $row ? floatval($row['unit_price']) : 0;
            }
            $refund_amount = isset($item['refund_amount']) && $item['refund_amount'] !== ''
                ? round(floatval($item['refund_amount']), 2)
                : round(floatval($item['quantity']) * $unitPrice, 2);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO return_items 
                (return_id, product_id, quantity, refund_amount, item_reason) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $return_id,
                $item['product_id'],
                $item['quantity'],
                $refund_amount,
                $item['item_reason'] ?? $reason
            ]);
        }
        
        $this->pdo->commit();
        
        logActivity('create_return', "Created return: $return_number for sale: $sale_id (Total Refund: ₱$total_refund)");
        
        return ['success' => true, 'return_number' => $return_number, 'return_id' => $return_id];
        
    } catch(Exception $e) {
        $this->pdo->rollBack();
        error_log("ReturnManager::createReturn error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
    
    // Approve return
    public function approveReturn($return_id, $approved_by) {
        try {
            // Get return details
            $stmt = $this->pdo->prepare("SELECT * FROM returns WHERE id = ?");
            $stmt->execute([$return_id]);
            $return = $stmt->fetch();
            
            if (!$return) {
                return ['success' => false, 'message' => 'Return not found'];
            }

            if ($return['status'] !== 'pending') {
                return ['success' => false, 'message' => 'This return has already been ' . $return['status'] . '.'];
            }

            $this->pdo->beginTransaction();

            // Update return status
            $stmt = $this->pdo->prepare("
                UPDATE returns
                SET status = 'approved', approved_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$approved_by, $return_id]);
            
            // Restore inventory for returned items
            $stmt = $this->pdo->prepare("
                SELECT product_id, quantity FROM return_items WHERE return_id = ?
            ");
            $stmt->execute([$return_id]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item) {
                $stmt = $this->pdo->prepare("
                    UPDATE products
                    SET stock_quantity = stock_quantity + ?
                    WHERE id = ?
                ");
                $stmt->execute([$item['quantity'], $item['product_id']]);

                $stmt = $this->pdo->prepare("
                    INSERT INTO stock_movements
                    (product_id, movement_type, quantity, quantity_before, quantity_after, reason, reference_id, reference_type, user_id)
                    SELECT ?, 'return', ?, stock_quantity - ?, stock_quantity, CONCAT('Return ', ?), ?, 'return', ?
                    FROM products WHERE id = ?
                ");
                $stmt->execute([$item['product_id'], $item['quantity'], $item['quantity'], $return['return_number'], $return_id, $approved_by, $item['product_id']]);
            }
            
            // Mark as completed after approval
            $stmt = $this->pdo->prepare("
                UPDATE returns SET status = 'completed' WHERE id = ?
            ");
            $stmt->execute([$return_id]);
            
            $this->pdo->commit();
            
            logActivity('approve_return', "Approved return: {$return['return_number']}");
            
            return ['success' => true];
            
        } catch(Exception $e) {
            $this->pdo->rollBack();
            error_log("ReturnManager::approveReturn error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Reject return
    public function rejectReturn($return_id, $reason) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE returns 
                SET status = 'rejected', notes = CONCAT(notes, '\nRejected: ', ?), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$reason, $return_id]);
            
            logActivity('reject_return', "Rejected return ID: $return_id");
            
            return ['success' => true];
        } catch(PDOException $e) {
            error_log("ReturnManager::rejectReturn error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    // Get return statistics
    public function getReturnStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    COALESCE(SUM(total_refund), 0) as total_refunded,
                    COALESCE(AVG(total_refund), 0) as avg_refund
                FROM returns
            ");
            return $stmt->fetch();
        } catch(PDOException $e) {
            error_log("ReturnManager::getReturnStats error: " . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'completed' => 0,
                'rejected' => 0,
                'total_refunded' => 0,
                'avg_refund' => 0
            ];
        }
    }
}

// ============================================
