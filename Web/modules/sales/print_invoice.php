<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

if (!isset($_GET['id'])) {
    die("Invalid Invoice ID");
}

$order_id = intval($_GET['id']);

// 1. Fetch Header Info
$sql_head = "SELECT o.*, c.shop_name, c.address, c.phone, c.owner_name, u.full_name as rep_name 
             FROM mobile_orders o 
             JOIN customers c ON o.customer_id = c.id 
             JOIN sales_reps sr ON o.rep_id = sr.id 
             JOIN users u ON sr.user_id = u.id 
             WHERE o.id = $order_id";
$head_res = $conn->query($sql_head);

if ($head_res->num_rows == 0) {
    die("Invoice Not Found");
}

$inv = $head_res->fetch_assoc();

// 2. Fetch Items
$sql_items = "SELECT i.*, p.product_name 
              FROM mobile_order_items i 
              JOIN products p ON i.product_id = p.id 
              WHERE i.mobile_order_id = $order_id";
$items_res = $conn->query($sql_items);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $inv['order_ref']; ?></title>
    <style>
        body { font-family: 'Helvetica', Arial, sans-serif; font-size: 14px; color: #333; padding: 20px; max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #eee; padding-bottom: 20px; }
        .header h1 { margin: 0; color: #2c3e50; }
        .header p { margin: 5px 0; color: #777; }
        
        .inv-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .box { width: 48%; }
        .box h3 { border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px; color: #555; font-size: 16px; }
        .box p { margin: 5px 0; line-height: 1.5; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; font-weight: bold; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }

        .totals { width: 40%; margin-left: auto; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; }
        .grand-total { font-weight: bold; font-size: 18px; border-top: 2px solid #333; padding-top: 10px; margin-top: 5px; }

        .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #aaa; border-top: 1px solid #eee; padding-top: 20px; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #2980b9; color: white; border: none; cursor: pointer; border-radius: 4px;">Print Invoice</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #7f8c8d; color: white; border: none; cursor: pointer; border-radius: 4px;">Close</button>
    </div>

    <div class="header">
        <h1>DELIGHT DAIRY PRODUCTS</h1>
        <p>123, Industrial Zone, Colombo, Sri Lanka</p>
        <p>Tel: 011-2345678 | Email: sales@delightdairy.lk</p>
    </div>

    <div class="inv-info">
        <div class="box">
            <h3>Bill To:</h3>
            <p><strong><?php echo htmlspecialchars($inv['shop_name']); ?></strong></p>
            <p><?php echo htmlspecialchars($inv['owner_name']); ?></p>
            <p><?php echo nl2br(htmlspecialchars($inv['address'])); ?></p>
            <p>Tel: <?php echo htmlspecialchars($inv['phone']); ?></p>
        </div>
        <div class="box text-right">
            <h3>Invoice Details:</h3>
            <p><strong>Invoice #:</strong> <?php echo $inv['order_ref']; ?></p>
            <p><strong>Date:</strong> <?php echo date("d-M-Y", strtotime($inv['order_date'])); ?></p>
            <p><strong>Sales Rep:</strong> <?php echo $inv['rep_name']; ?></p>
            <p><strong>Status:</strong> <span style="text-transform:uppercase; font-weight:bold;"><?php echo $inv['payment_status']; ?></span></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Description</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Total (Rs)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 1;
            $grand_total = 0;
            if($items_res->num_rows > 0):
                while($item = $items_res->fetch_assoc()): 
                    $grand_total += $item['line_total'];
            ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td class="text-right"><?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="text-right"><?php echo $item['quantity']; ?></td>
                <td class="text-right"><?php echo number_format($item['line_total'], 2); ?></td>
            </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span>Subtotal:</span>
            <span><?php echo number_format($grand_total, 2); ?></span>
        </div>
        <div class="totals-row grand-total">
            <span>Total Amount:</span>
            <span>Rs <?php echo number_format($grand_total, 2); ?></span>
        </div>
    </div>

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>System Generated Invoice - Delight Dairy ERP</p>
    </div>

</body>
</html>