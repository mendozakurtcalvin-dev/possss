<?php
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
switch ($page):
case 'dashboard':
    require __DIR__ . '/pages/dashboard.php';
    break;
case 'cart':
    require __DIR__ . '/pages/cart.php';
    break;
case 'products':
    require __DIR__ . '/pages/products.php';
    break;
case 'hr':
    require __DIR__ . '/pages/hr.php';
    break;
case 'finance_dashboard':
    require __DIR__ . '/pages/finance_dashboard.php';
    break;
case 'finance_reports':
    require __DIR__ . '/pages/finance_reports.php';
    break;
case 'returns':
    require __DIR__ . '/pages/returns.php';
    break;
case 'archive':
    require __DIR__ . '/pages/archive.php';
    break;
case 'categories':
    require __DIR__ . '/pages/categories.php';
    break;
case 'customers':
    require __DIR__ . '/pages/customers.php';
    break;
case 'sales':
    require __DIR__ . '/pages/sales.php';
    break;
case 'reports':
    require __DIR__ . '/pages/reports.php';
    break;
case 'customer_reports':
    require __DIR__ . '/pages/customer_reports.php';
    break;
case 'users':
    require __DIR__ . '/pages/users.php';
    break;
case 'activity':
    require __DIR__ . '/pages/activity.php';
    break;
case 'settings':
    require __DIR__ . '/pages/settings.php';
    break;
case 'stock':
    require __DIR__ . '/pages/stock.php';
    break;
case 'suppliers':
    require __DIR__ . '/pages/suppliers.php';
    break;
case 'procurement':
    require __DIR__ . '/pages/procurement.php';
    break;
case 'tokenization':
    require __DIR__ . '/pages/tokenization.php';
    break;
case 'purchases':
    require __DIR__ . '/pages/purchases.php';
    break;
case 'inventory_reports':
    require __DIR__ . '/pages/inventory_reports.php';
    break;
case 'notifications':
    require __DIR__ . '/pages/notifications.php';
    break;
default:
    require __DIR__ . '/pages/default.php';
    break;
endswitch;

