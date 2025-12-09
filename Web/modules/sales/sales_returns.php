<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";
$invoice_data = null;
$invoice_items = [];

// --- 1. HANDLE RETURN CREATION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'process_return') {
    $inv_id = intval($_POST['invoice_id']);
    $cust_id = intval($_POST['customer_id']);
    $date = date('Y-m-d');
    $ref = "CN-" . date('ymd') . "-" . rand(100,999);
    
    $total_refund = 0;
    
    // 1. Calculate Total First
    foreach ($_POST['return_qty'] as $item_id => $qty) {
        if ($qty > 0) {
            $price = floatval($_POST['price'][$item_id]);
            $total_refund += ($qty * $price);
        }
    }

    if ($total_refund > 0) {
        $conn->begin_transaction();
        try {
            // Header
            $stmt = $conn->prepare("INSERT INTO sales_returns (return_ref, original_invoice_id, customer_id, return_date, total_refund, reason) VALUES (?, ?, ?, ?, ?, ?)");
            $reason = $_POST['reason_global'];
            $stmt->bind_param("siisds", $ref, $inv_id, $cust_id, $date, $total_refund, $reason);
            $stmt->execute();
            $ret_id = $stmt->insert_id;

            // Items
            foreach ($_POST['return_qty'] as $item_id => $qty) {
                if ($qty > 0) {
                    $pid = intval($_POST['prod_id'][$item_id]);
                    $price = floatval($_POST['price'][$item_id]);
                    $line = $qty * $price;
                    $cond = $_POST['condition'][$item_id];

                    // Save Return Item
                    $stmt_item = $conn->prepare("INSERT INTO sales_return_items (return_id, product_id, quantity, unit_price, line_total, condition_status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_item->bind_param("iiidds", $ret_id, $pid, $qty, $price, $line, $cond);
                    $stmt_item->execute();

                    // Update Stock (Add to Damaged/Returned Stock)
                    // Note: If condition is 'good', one might want to add back to main stock. 
                    // For now, we put everything in 'damaged_stock' table for review.
                    $stmt_dmg = $conn->prepare("INSERT INTO damaged_stock (product_id, quantity, return_ref_id, reason, reported_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt_dmg->bind_param("iiiss", $pid, $qty, $ret_id, $cond, $date);
                    $stmt_dmg->execute();
                }
            }

            $conn->commit();
            $msg = "<div class='alert success'>Credit Note <strong>$ref</strong> Created Successfully!</div>";
            $invoice_data = null; // Reset form

        } catch (Exception $e) {
            $conn->rollback();
            $msg = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>No items selected for return or quantity is zero.</div>";
    }
}

// --- 2. SEARCH INVOICE ---
if (isset($_GET['search_term'])) {
    $term = $conn->real_escape_string($_GET['search_term']);
    $sql = "SELECT o.*, c.shop_name 
            FROM mobile_orders o 
            JOIN customers c ON o.customer_id = c.id 
            WHERE o.order_ref LIKE '%$term%' OR c.shop_name LIKE '%$term%'
            LIMIT 1"; // Get first match
    $res = $conn->query($sql);
    
    if ($res->num_rows > 0) {
        $invoice_data = $res->fetch_assoc();
        // Fetch Invoice Items
        $items_sql = "SELECT i.*, p.product_name 
                      FROM mobile_order_items i 
                      JOIN products p ON i.product_id = p.id 
                      WHERE i.mobile_order_id = " . $invoice_data['id'];
        $i_res = $conn->query($items_sql);
        while($r = $i_res->fetch_assoc()) { $invoice_items[] = $r; }
    } else {
        $msg = "<div class='alert error'>No invoice found matching '$term'</div>";
    }
}

// --- 3. FETCH RECENT RETURNS ---
$history = $conn->query("
    SELECT r.*, c.shop_name, o.order_ref 
    FROM sales_returns r 
    JOIN customers c ON r.customer_id = c.id 
    JOIN mobile_orders o ON r.original_invoice_id = o.id 
    ORDER BY r.return_date DESC, r.id DESC LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Returns | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { margin-bottom: 20px; }
        .search-area { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; gap: 10px; align-items: center; }
        .search-input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        .btn-search { background: #3498db; color: white; border: none; padding: 10px 25px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        
        .return-form { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-top: 20px; border-left: 5px solid #e74c3c; }
        .inv-header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th { background: #f8f9fa; text-align: left; padding: 10px; font-size: 13px; color: #555; border-bottom: 2px solid #ddd; }
        .data-table td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .qty-input { width: 80px; padding: 5px; text-align: center; border: 1px solid #3498db; border-radius: 4px; }
        .cond-select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; }
        
        .btn-process { background: #e74c3c; color: white; border: none; padding: 12px 25px; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 20px; float: right; }
        
        .history-section { margin-top: 40px; }
        .hist-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .hist-table th { background: #34495e; color: white; padding: 12px; text-align: left; }
        .hist-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 600px; max-height: 80vh; overflow-y: auto; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h2>Sales Returns (Credit Notes)</h2>
            <p style="color:#777; margin:0;">Process returns and manage credit notes.</p>
        </div>

        <?php echo $msg; ?>

        <!-- SEARCH SECTION -->
        <form method="GET" class="search-area">
            <i class="fas fa-search" style="color:#aaa;"></i>
            <input type="text" name="search_term" class="search-input" placeholder="Enter Invoice Number (INV-...) or Shop Name" required>
            <button type="submit" class="btn-search">Find Invoice</button>
        </form>

        <!-- RETURN FORM SECTION -->
        <?php if ($invoice_data): ?>
            <div class="return-form">
                <div class="inv-header">
                    <div>
                        <h3 style="margin:0; color:#2c3e50;">Invoice: <?php echo $invoice_data['order_ref']; ?></h3>
                        <div style="color:#777; font-size:14px; margin-top:5px;">
                            Customer: <strong><?php echo htmlspecialchars($invoice_data['shop_name']); ?></strong> | 
                            Date: <?php echo $invoice_data['order_date']; ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:12px; text-transform:uppercase;">Invoice Total</div>
                        <div style="font-size:20px; font-weight:bold; color:#27ae60;">Rs <?php echo number_format($invoice_data['total_amount'], 2); ?></div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="process_return">
                    <input type="hidden" name="invoice_id" value="<?php echo $invoice_data['id']; ?>">
                    <input type="hidden" name="customer_id" value="<?php echo $invoice_data['customer_id']; ?>">

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product Item</th>
                                <th>Sold Qty</th>
                                <th>Unit Price</th>
                                <th>Return Qty</th>
                                <th>Return Condition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoice_items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    <input type="hidden" name="prod_id[<?php echo $item['id']; ?>]" value="<?php echo $item['product_id']; ?>">
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>
                                    Rs <?php echo number_format($item['unit_price'], 2); ?>
                                    <input type="hidden" name="price[<?php echo $item['id']; ?>]" value="<?php echo $item['unit_price']; ?>">
                                </td>
                                <td>
                                    <input type="number" name="return_qty[<?php echo $item['id']; ?>]" class="qty-input" min="0" max="<?php echo $item['quantity']; ?>" value="0" step="0.01">
                                </td>
                                <td>
                                    <select name="condition[<?php echo $item['id']; ?>]" class="cond-select">
                                        <option value="expired">Expired</option>
                                        <option value="damaged">Damaged</option>
                                        <option value="good">Good (Restock)</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="margin-top:20px;">
                        <label style="font-weight:bold;">Reason / Notes:</label>
                        <input type="text" name="reason_global" style="width:100%; padding:10px; border:1px solid #ddd; margin-top:5px;" placeholder="e.g. Expired items from shelf display">
                    </div>

                    <div style="overflow:hidden;">
                        <button type="submit" class="btn-process" onclick="return confirm('Create Credit Note? This will deduct from customer balance.')">
                            <i class="fas fa-file-invoice"></i> Generate Credit Note
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- HISTORY SECTION -->
        <div class="history-section">
            <h3 style="color:#555;">Recent Credit Notes</h3>
            <table class="hist-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>CN Ref</th>
                        <th>Original Inv</th>
                        <th>Customer</th>
                        <th>Reason</th>
                        <th style="text-align:right;">Refund Amount</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($history->num_rows > 0): ?>
                        <?php while($row = $history->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['return_date']; ?></td>
                            <td style="font-weight:bold; color:#e74c3c;"><?php echo $row['return_ref']; ?></td>
                            <td><?php echo $row['order_ref']; ?></td>
                            <td><?php echo htmlspecialchars($row['shop_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td style="text-align:right; font-weight:bold;">Rs <?php echo number_format($row['total_refund'], 2); ?></td>
                            <td style="text-align:center;">
                                <button onclick="viewDetails(<?php echo $row['id']; ?>)" style="background:none; border:none; color:#3498db; cursor:pointer; font-weight:bold;">View Items</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:20px; color:#aaa;">No return history found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- DETAILS MODAL -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                <h3 style="margin:0;">Return Details</h3>
                <span onclick="document.getElementById('detailModal').style.display='none'" style="cursor:pointer; font-size:20px;">&times;</span>
            </div>
            <div id="modalBody">Loading...</div>
        </div>
    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function viewDetails(id) {
            document.getElementById('detailModal').style.display = 'flex';
            document.getElementById('modalBody').innerHTML = 'Loading...';
            
            // Simple AJAX to fetch details
            // Note: In a real app, make a dedicated endpoint. 
            // For now, we will simulate or you can create a 'get_return_details.php' file.
            // I will inject a small inline PHP script capability if needed, or better, 
            // create a helper file. Since I can't create multiple files in one go easily here without context,
            // I will prompt to create 'get_return_details.php' next or inline the logic if possible.
            // For this single file mandate, I'll use a fetch to self with a param.
            
            fetch(`sales_returns.php?ajax_details=${id}`)
                .then(res => res.text())
                .then(data => {
                    document.getElementById('modalBody').innerHTML = data;
                });
        }
        
        window.onclick = function(e) {
            if(e.target == document.getElementById('detailModal')) document.getElementById('detailModal').style.display = 'none';
        }
    </script>
</body>
</html>

<?php
// --- APPEND AJAX HANDLER AT END OF FILE (Self-contained) ---
if (isset($_GET['ajax_details'])) {
    // Clean buffer to output only the HTML fragment
    ob_clean(); 
    $rid = intval($_GET['ajax_details']);
    $q = $conn->query("SELECT i.*, p.product_name FROM sales_return_items i JOIN products p ON i.product_id = p.id WHERE i.return_id = $rid");
    
    echo '<table style="width:100%; border-collapse:collapse;">';
    echo '<tr style="background:#f0f0f0;"><th style="padding:8px; text-align:left;">Product</th><th style="padding:8px;">Qty</th><th style="padding:8px;">Condition</th><th style="padding:8px; text-align:right;">Total</th></tr>';
    while($r = $q->fetch_assoc()) {
        echo "<tr>";
        echo "<td style='padding:8px; border-bottom:1px solid #eee;'>{$r['product_name']}</td>";
        echo "<td style='padding:8px; border-bottom:1px solid #eee; text-align:center;'>{$r['quantity']}</td>";
        echo "<td style='padding:8px; border-bottom:1px solid #eee; text-align:center;'>{$r['condition_status']}</td>";
        echo "<td style='padding:8px; border-bottom:1px solid #eee; text-align:right;'>".number_format($r['line_total'],2)."</td>";
        echo "</tr>";
    }
    echo '</table>';
    exit; // Stop executing the rest of the page
}
?>