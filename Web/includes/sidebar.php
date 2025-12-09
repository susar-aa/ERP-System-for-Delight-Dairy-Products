<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php'; 

$user_id = $_SESSION['user_id'] ?? 0;
$current_page = basename($_SERVER['PHP_SELF']);
$base_url = "http://localhost/Delight Dairy Products/";

// --- 1. FETCH USER DETAILS ---
$user_info = ['full_name' => 'Guest', 'role' => 'Visitor', 'avatar' => 'default.png'];
$role = 'guest';
$perms = [];

if ($user_id > 0) {
    $u_q = $conn->query("SELECT * FROM users WHERE id = $user_id");
    if ($u_q && $u_q->num_rows > 0) {
        $u_row = $u_q->fetch_assoc();
        $user_info = $u_row;
        $role = $u_row['role'];
        $perms = json_decode($u_row['permissions'], true) ?? [];
    }
}

// --- 2. NOTIFICATION BADGES ---
$badges = ['hr' => 0, 'inventory' => 0];

if ($conn) {
    // HR Pending
    $hr_q = $conn->query("SELECT COUNT(*) as c FROM salary_advances WHERE status = 'pending'");
    if ($hr_q) $badges['hr'] = $hr_q->fetch_assoc()['c'];

    // Low Stock
    $inv_q = $conn->query("SELECT COUNT(*) as c FROM (SELECT m.id FROM raw_materials m LEFT JOIN material_batches b ON m.id = b.material_id AND b.status = 'active' GROUP BY m.id HAVING COALESCE(SUM(b.quantity_current), 0) <= m.reorder_level) as low_stock");
    if ($inv_q) $badges['inventory'] = $inv_q->fetch_assoc()['c'];
}

function has_access($module) {
    global $role, $perms;
    if ($role === 'admin') return true;
    if (isset($perms[$module]) && $perms[$module] == '1') return true;
    return false;
}
?>

<div class="sidebar" id="sidebar">
    
    <div class="brand-section">
        <div class="brand-logo">
            <img src="<?php echo $base_url; ?>assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
            <h3 id="brand-text">Delight ERP</h3>
        </div>
        <button id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="user-profile">
        <div class="profile-img"><i class="fas fa-user-circle"></i></div>
        <div class="profile-info">
            <div class="user-name"><?php echo htmlspecialchars($user_info['full_name']); ?></div>
            <div class="user-role"><?php echo ucfirst($user_info['role']); ?></div>
            <a href="<?php echo $base_url; ?>profile.php" class="view-profile-btn">View Profile</a>
        </div>
    </div>

    <ul class="nav-links">
        <li>
            <a href="<?php echo $base_url; ?>dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <div class="icon-text"><i class="fas fa-tachometer-alt"></i><span class="link-text">Dashboard</span></div>
            </a>
        </li>
        
        <?php if(has_access('hr')): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text"><i class="fas fa-users"></i><span class="link-text">HR Management</span></div>
                <div style="display:flex; align-items:center;">
                    <?php if($badges['hr'] > 0): ?><span class="badge-count"><?php echo $badges['hr']; ?></span><?php endif; ?>
                    <i class="fas fa-chevron-right arrow"></i>
                </div>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/hr/index.php">Overview</a></li>
                <li><a href="<?php echo $base_url; ?>modules/hr/manage_employees.php">Employees</a></li>
                <li><a href="<?php echo $base_url; ?>modules/hr/mark_attendance.php">Attendance</a></li>
                <li><a href="<?php echo $base_url; ?>modules/hr/salary_advance.php">Requests <?php if($badges['hr'] > 0) echo "<span class='sub-badge'>{$badges['hr']}</span>"; ?></a></li>
                <li><a href="<?php echo $base_url; ?>modules/hr/payroll.php">Payroll</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if(has_access('inventory')): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text"><i class="fas fa-boxes"></i><span class="link-text">Inventory</span></div>
                <div style="display:flex; align-items:center;">
                    <?php if($badges['inventory'] > 0): ?><span class="badge-count bg-red"><?php echo $badges['inventory']; ?></span><?php endif; ?>
                    <i class="fas fa-chevron-right arrow"></i>
                </div>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/inventory/index.php">Overview</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/manage_materials.php">Raw Materials</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/add_stock.php">Add Stock (GRN)</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/view_stock.php">View Stock</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/stock_history.php">Bin Card</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/stock_audit.php">Stock Audit</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/manage_suppliers.php">Suppliers</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if(has_access('production')): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text"><i class="fas fa-industry"></i><span class="link-text">Production</span></div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/production/index.php">Overview</a></li>
                <li><a href="<?php echo $base_url; ?>modules/production/manage_products.php">Products</a></li>
                <li><a href="<?php echo $base_url; ?>modules/production/manage_recipes.php">Recipes</a></li>
                <li><a href="<?php echo $base_url; ?>modules/production/add_production.php">Run Production</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if(has_access('sales')): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text"><i class="fas fa-shopping-cart"></i><span class="link-text">Sales & Billing</span></div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/sales/index.php">Dashboard</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/invoice_panel.php">Invoicing Panel</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/view_orders.php">View Invoices</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/sales_returns.php">Sales Returns</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/sales_targets.php">Sales Targets</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/manage_customers.php">Customers</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/manage_routes.php">Routes</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/manage_reps.php">Sales Reps</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if(has_access('accounts')): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text"><i class="fas fa-calculator"></i><span class="link-text">Accounts</span></div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/accounts/index.php">Overview</a></li>
                <li><a href="<?php echo $base_url; ?>modules/accounts/record_income.php">Other Revenue</a></li>
                <li><a href="<?php echo $base_url; ?>modules/accounts/manage_expenses.php">Manage Expenses</a></li>
                <li><a href="<?php echo $base_url; ?>modules/accounts/financial_report.php">P&L Report</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if($role == 'admin' || has_access('accounts')): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text"><i class="fas fa-chart-bar"></i><span class="link-text">Reports Center</span></div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/reports/index.php?type=sales_by_rep">Sales by Rep</a></li>
                <li><a href="<?php echo $base_url; ?>modules/reports/index.php?type=sales_by_product">Sales by Product</a></li>
                <li><a href="<?php echo $base_url; ?>modules/reports/index.php?type=customer_outstanding">Cust. Outstanding</a></li>
                <li><a href="<?php echo $base_url; ?>modules/reports/index.php?type=stock_valuation">Stock Valuation</a></li>
                <li><a href="<?php echo $base_url; ?>modules/reports/index.php?type=expense_summary">Expense Summary</a></li>
                <li><a href="<?php echo $base_url; ?>modules/reports/index.php?type=production_yield">Production Yield</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- SYSTEM ADMIN -->
        <?php if($role == 'admin' || has_access('admin')): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text"><i class="fas fa-cogs"></i><span class="link-text">System Admin</span></div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/admin/manage_users.php">Manage Users</a></li>
                <li><a href="<?php echo $base_url; ?>modules/admin/backup_manager.php">Backup Database</a></li>
                <li><a href="<?php echo $base_url; ?>modules/admin/audit_trail.php">Audit Logs</a></li>
            </ul>
        </li>
        <?php endif; ?>
    </ul>

    <div class="dev-credits">
        <div class="dev-text">Developed by <strong>SuzxLabs</strong></div>
        <div class="dev-icons">
            <a href="https://www.suzxlabs.com" target="_blank" title="Website"><i class="fas fa-globe"></i></a>
            <a href="https://www.instagram.com/susar.aa/" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
        </div>
    </div>

    <div class="logout-section">
        <a href="<?php echo $base_url; ?>logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i><span class="link-text">Logout</span>
        </a>
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">