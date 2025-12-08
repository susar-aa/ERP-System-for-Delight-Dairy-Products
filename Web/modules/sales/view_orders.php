<?php
require_once '../../config/db.php';

// Fetch orders with shop name and rep details
$sql = "SELECT o.*, c.shop_name, u.full_name as rep_name 
        FROM mobile_orders o 
        JOIN customers c ON o.customer_id = c.id 
        JOIN sales_reps sr ON o.rep_id = sr.id 
        JOIN users u ON sr.user_id = u.id 
        ORDER BY o.order_date DESC";
$orders = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Invoices | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Sales Invoices</h2>
            <div>
                <a href="invoice_panel.php" style="background:#27ae60; color:white; padding:10px 15px; text-decoration:none; border-radius:4px;">+ New Invoice</a>
                <a href="index.php" style="color:#3498db; margin-left:10px;">&larr; Back</a>
            </div>
        </div>

        <table style="width:100%; background:white; border-collapse:collapse; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
            <thead>
                <tr style="background:#34495e; color:white; text-align:left;">
                    <th style="padding:12px;">Date</th>
                    <th style="padding:12px;">Invoice #</th>
                    <th style="padding:12px;">Customer</th>
                    <th style="padding:12px;">Sales Rep</th>
                    <th style="padding:12px;">Amount</th>
                    <th style="padding:12px;">Status</th>
                    <th style="padding:12px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($orders->num_rows > 0): ?>
                    <?php while($row = $orders->fetch_assoc()): ?>
                    <tr style="border-bottom:1px solid #ecf0f1;">
                        <td style="padding:12px;"><?php echo $row['order_date']; ?></td>
                        <td style="padding:12px; font-weight:bold; color:#2980b9;"><?php echo $row['order_ref']; ?></td>
                        <td style="padding:12px;"><?php echo $row['shop_name']; ?></td>
                        <td style="padding:12px;"><?php echo $row['rep_name']; ?></td>
                        <td style="padding:12px;">Rs <?php echo number_format($row['total_amount'], 2); ?></td>
                        <td style="padding:12px;">
                            <span style="padding:4px 8px; border-radius:4px; font-size:12px; background:<?php echo $row['payment_status']=='paid'?'#d4edda':'#fadbd8'; ?>; color:<?php echo $row['payment_status']=='paid'?'#155724':'#721c24'; ?>;">
                                <?php echo strtoupper($row['payment_status']); ?>
                            </span>
                        </td>
                        <td style="padding:12px;">
                            <a href="invoice_panel.php?id=<?php echo $row['id']; ?>" style="background:#3498db; color:white; padding:5px 10px; text-decoration:none; border-radius:4px; font-size:12px;">
                                Edit / View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="padding:20px; text-align:center;">No Invoices Found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>