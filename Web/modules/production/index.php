<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

// 1. Total Products Defined
$prod_count = $conn->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];

// 2. Production Runs Today
$today = date('Y-m-d');
$runs_today = $conn->query("SELECT COUNT(*) as c FROM production_runs WHERE mfd_date = '$today'")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .action-bar { margin-bottom: 20px; display: flex; gap: 10px; }
        .btn { padding: 10px 20px; text-decoration: none; color: white; border-radius: 5px; font-size: 14px; }
        .btn-blue { background-color: #3498db; }
        .btn-purple { background-color: #9b59b6; }
        .btn-green { background-color: #27ae60; }
    </style>
</head>
<body>

    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Production Management</h2>
        </div>

        <!-- Workflow Actions -->
        <div class="action-bar">
            <a href="manage_products.php" class="btn btn-blue">1. Define Products</a>
            <a href="manage_recipes.php" class="btn btn-purple">2. Manage Recipes (BOM)</a>
            <a href="add_production.php" class="btn btn-green">3. Record Production Run</a>
        </div>

        <div class="kpi-grid">
            <div class="card">
                <h4>Products Defined</h4>
                <div class="value"><?php echo $prod_count; ?></div>
            </div>

            <div class="card">
                <h4>Production Runs (Today)</h4>
                <div class="value"><?php echo $runs_today; ?></div>
            </div>
        </div>
        
        <!-- Recent Production History -->
        <h3>Recent Production Runs</h3>
        <table style="width:100%; background:white; border-collapse:collapse;">
            <tr style="background:#ecf0f1; text-align:left;">
                <th style="padding:10px;">Date</th>
                <th style="padding:10px;">Batch</th>
                <th style="padding:10px;">Product</th>
                <th style="padding:10px;">Qty Produced</th>
                <th style="padding:10px;">Status</th>
            </tr>
            <?php
            $hist = $conn->query("SELECT p.product_name, r.* FROM production_runs r JOIN products p ON r.product_id = p.id ORDER BY r.id DESC LIMIT 5");
            while($row = $hist->fetch_assoc()){
                echo "<tr>
                        <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['mfd_date']}</td>
                        <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['batch_code']}</td>
                        <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['product_name']}</td>
                        <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['quantity_produced']}</td>
                        <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['status']}</td>
                      </tr>";
            }
            ?>
        </table>

    </div>
</body>
</html>