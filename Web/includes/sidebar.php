<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'guest';
$current_page = basename($_SERVER['PHP_SELF']);
$base_url = "http://localhost/Delight Dairy Products/"; // Adjust if needed
?>

<div class="sidebar" id="sidebar">
    <div class="brand-section">
        <h3 id="brand-text">Delight ERP</h3>
        <button id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <ul class="nav-links">
        <!-- Dashboard (No Submenu) -->
        <li>
            <a href="<?php echo $base_url; ?>dashboard.php" 
               class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="link-text">Dashboard</span>
            </a>
        </li>
        
        <!-- HR Module -->
        <?php if($role == 'admin' || $role == 'hr'): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text">
                    <i class="fas fa-users"></i>
                    <span class="link-text">HR Management</span>
                </div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/hr/index.php">Overview</a></li>
                <li><a href="<?php echo $base_url; ?>modules/hr/manage_employees.php">Employees</a></li>
                <li><a href="<?php echo $base_url; ?>modules/hr/mark_attendance.php">Attendance</a></li>
                <li><a href="<?php echo $base_url; ?>modules/hr/payroll.php">Payroll</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Inventory Module -->
        <?php if($role == 'admin' || $role == 'store_keeper' || $role == 'production_manager'): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text">
                    <i class="fas fa-boxes"></i>
                    <span class="link-text">Inventory</span>
                </div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/inventory/index.php">Overview</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/manage_materials.php">Raw Materials</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/add_stock.php">Add Stock (GRN)</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/view_stock.php">View Batch Stock</a></li>
                <li><a href="<?php echo $base_url; ?>modules/inventory/manage_suppliers.php">Suppliers</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Production Module -->
        <?php if($role == 'admin' || $role == 'production_manager'): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text">
                    <i class="fas fa-industry"></i>
                    <span class="link-text">Production</span>
                </div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/production/index.php">Overview</a></li>
                <li><a href="<?php echo $base_url; ?>modules/production/manage_products.php">Products</a></li>
                <li><a href="<?php echo $base_url; ?>modules/production/manage_recipes.php">Recipes (BOM)</a></li>
                <li><a href="<?php echo $base_url; ?>modules/production/add_production.php">Run Production</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Sales Module -->
        <?php if($role == 'admin' || $role == 'accountant'): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="link-text">Sales & Billing</span>
                </div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/sales/index.php">Dashboard</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/invoice_panel.php">Invoicing Panel</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/view_orders.php">View Invoices</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/manage_customers.php">Customers</a></li>
                <li><a href="<?php echo $base_url; ?>modules/sales/manage_routes.php">Routes</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Accounts Module -->
        <?php if($role == 'admin' || $role == 'accountant'): ?>
        <li class="dropdown">
            <div class="dropdown-btn" onclick="toggleMenu(this)">
                <div class="icon-text">
                    <i class="fas fa-calculator"></i>
                    <span class="link-text">Accounts</span>
                </div>
                <i class="fas fa-chevron-right arrow"></i>
            </div>
            <ul class="submenu">
                <li><a href="<?php echo $base_url; ?>modules/accounts/index.php">Overview</a></li>
                <li><a href="<?php echo $base_url; ?>modules/accounts/record_income.php">Record Income</a></li>
                <li><a href="<?php echo $base_url; ?>modules/accounts/manage_expenses.php">Record Expenses</a></li>
                <li><a href="<?php echo $base_url; ?>modules/accounts/financial_report.php">Reports</a></li>
            </ul>
        </li>
        <?php endif; ?>
    </ul>

    <div class="logout-section">
        <a href="<?php echo $base_url; ?>logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Logout</span>
        </a>
    </div>
</div>

<!-- Load FontAwesome if not already loaded -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">