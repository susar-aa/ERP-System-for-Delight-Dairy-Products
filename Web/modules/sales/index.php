<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

// 1. KPI Stats
$total_shops = $conn->query("SELECT COUNT(*) as c FROM customers")->fetch_assoc()['c'];
$total_routes = $conn->query("SELECT COUNT(*) as c FROM routes")->fetch_assoc()['c'];
$today_sales = $conn->query("SELECT SUM(total_amount) as total FROM mobile_orders WHERE order_date = CURRENT_DATE")->fetch_assoc()['total'] ?? 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales & Distribution | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .action-bar { margin-bottom: 20px; display: flex; gap: 10px; }
        .btn { padding: 10px 20px; text-decoration: none; color: white; border-radius: 5px; font-size: 14px; }
        .btn-orange { background-color: #e67e22; }
        .btn-teal { background-color: #16a085; }
        .btn-blue { background-color: #2980b9; }
    </style>
</head>
<body>

    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Sales & Distribution</h2>
        </div>

        <!-- Quick Actions -->
        <div class="action-bar">
            <a href="manage_routes.php" class="btn btn-orange">1. Manage Routes</a>
            <a href="manage_customers.php" class="btn btn-teal">2. Manage Customers (Shops)</a>
            <a href="view_orders.php" class="btn btn-blue">3. View Invoices</a>
            <a href="invoice_panel.php" class="btn" style="background:#8e44ad;">+ New Invoice</a>
        </div>

        <div class="kpi-grid">
            <div class="card">
                <h4>Total Shops</h4>
                <div class="value"><?php echo $total_shops; ?></div>
            </div>

            <div class="card">
                <h4>Active Routes</h4>
                <div class="value"><?php echo $total_routes; ?></div>
            </div>

            <div class="card">
                <h4>Sales (Today)</h4>
                <div class="value currency">Rs: <?php echo number_format($today_sales, 2); ?></div>
            </div>
        </div>

        <h3>Recent Invoices (Live Sync)</h3>
        <table style="width:100%; background:white; border-collapse:collapse; margin-top:10px;">
            <tr style="background:#ecf0f1; text-align:left;">
                <th style="padding:10px;">Date</th>
                <th style="padding:10px;">Inv Ref</th>
                <th style="padding:10px;">Customer</th>
                <th style="padding:10px;">Total (Rs)</th>
                <th style="padding:10px;">Status</th>
                <th style="padding:10px;">Action</th>
            </tr>
            <?php
            $sql = "SELECT o.*, c.shop_name 
                    FROM mobile_orders o 
                    JOIN customers c ON o.customer_id = c.id 
                    ORDER BY o.id DESC LIMIT 5";
            $res = $conn->query($sql);
            
            if($res->num_rows > 0) {
                while($row = $res->fetch_assoc()){
                    echo "<tr>
                            <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['order_date']}</td>
                            <td style='padding:10px; border-bottom:1px solid #ddd; font-weight:bold;'>{$row['order_ref']}</td>
                            <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['shop_name']}</td>
                            <td style='padding:10px; border-bottom:1px solid #ddd;'>".number_format($row['total_amount'], 2)."</td>
                            <td style='padding:10px; border-bottom:1px solid #ddd;'>
                                <span style='padding:3px 8px; border-radius:10px; background:".($row['payment_status']=='paid'?'#d4edda':'#fcf3cf')."'>
                                    {$row['payment_status']}
                                </span>
                            </td>
                            <td style='padding:10px; border-bottom:1px solid #ddd;'>
                                <a href='invoice_panel.php?id={$row['id']}' style='color:#3498db; text-decoration:none; font-weight:bold;'>Open</a>
                            </td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='6' style='padding:20px; text-align:center; color:#777;'>No invoices found yet.</td></tr>";
            }
            ?>
        </table>

    </div>
</body>
</html>