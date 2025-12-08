<?php
require_once 'config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'];

// --- Quick Data Fetching for Dashboard Summary ---

// 1. Get Total Active Employees
$emp_query = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status='active'");
// Add error handling for employees query
$emp_count = ($emp_query) ? $emp_query->fetch_assoc()['count'] : 0;

// 2. Get Low Stock Items (Reorder Warning)
// FIXED: Linked to 'material_batches' and compares against 'reorder_level'
$sql_stock = "SELECT COUNT(*) as count 
              FROM raw_materials rm
              LEFT JOIN (
                  SELECT material_id, SUM(quantity_current) as total_qty 
                  FROM material_batches 
                  WHERE status = 'active' 
                  GROUP BY material_id
              ) stocks ON rm.id = stocks.material_id
              WHERE COALESCE(stocks.total_qty, 0) < rm.reorder_level";

$stock_query = $conn->query($sql_stock);

if ($stock_query) {
    $low_stock_count = $stock_query->fetch_assoc()['count'];
} else {
    $low_stock_count = 0; // Default to 0 if query fails
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Delight Dairy ERP</title>
    
    <!-- Link to Separate CSS File -->
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

    <!-- Include Separate Sidepanel -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        
        <!-- Top Header -->
        <div class="header-bar">
            <h2>Overview</h2>
            <div class="user-info">
                <span>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
                <span class="role-badge"><?php echo ucfirst($user_role); ?></span>
            </div>
        </div>

        <!-- Dashboard Widgets -->
        <div class="kpi-grid">
            
            <div class="card">
                <h4>System Date & Time</h4>
                <div id="current-date" class="value" style="font-size: 1rem;">Loading...</div>
            </div>

            <div class="card">
                <h4>Active Staff</h4>
                <div class="value"><?php echo $emp_count; ?></div>
            </div>

            <div class="card">
                <h4>Low Stock Alerts</h4>
                <div class="value" style="<?php echo $low_stock_count > 0 ? 'color:red;' : ''; ?>">
                    <?php echo $low_stock_count; ?> Items
                </div>
            </div>

            <!-- Example of Currency Usage -->
            <div class="card">
                <h4>Total Sales (Today)</h4>
                <div class="value currency">Rs: 0.00</div> 
                <!-- Logic to be connected to Sales Module later -->
            </div>

        </div>

    </div>

    <!-- Link to Separate JS File -->
    <script src="assets/js/dashboard.js"></script>
</body>
</html>