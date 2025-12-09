<?php
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../index.php"); exit; }

$type = $_GET['type'] ?? 'sales_by_rep';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

$title = "";
$headers = [];
$data = [];

// --- REPORT LOGIC SWITCH ---
switch($type) {
    case 'sales_by_rep':
        $title = "Sales Performance by Representative";
        $headers = ['Sales Rep', 'Total Revenue', 'Invoice Count', 'Cash Collected', 'Credit Sales'];
        $sql = "
            SELECT 
                u.full_name,
                SUM(m.total_amount) as total,
                COUNT(m.id) as count,
                SUM(CASE WHEN m.payment_status IN ('paid','cash') THEN m.total_amount ELSE 0 END) as cash,
                SUM(CASE WHEN m.payment_status = 'credit' THEN m.total_amount ELSE 0 END) as credit
            FROM mobile_orders m
            JOIN sales_reps sr ON m.rep_id = sr.id
            JOIN users u ON sr.user_id = u.id
            WHERE m.order_date BETWEEN '$start_date' AND '$end_date'
            GROUP BY u.id
            ORDER BY total DESC
        ";
        break;

    case 'sales_by_product':
        $title = "Sales Analysis by Product";
        $headers = ['Product Name', 'Qty Sold', 'Total Revenue', 'Avg Price'];
        $sql = "
            SELECT 
                p.product_name,
                SUM(i.quantity) as qty,
                SUM(i.line_total) as total,
                AVG(i.unit_price) as avg_price
            FROM mobile_order_items i
            JOIN mobile_orders o ON i.mobile_order_id = o.id
            JOIN products p ON i.product_id = p.id
            WHERE o.order_date BETWEEN '$start_date' AND '$end_date'
            GROUP BY p.id
            ORDER BY total DESC
        ";
        break;

    case 'customer_outstanding':
        $title = "Customer Outstanding Balance";
        $headers = ['Shop Name', 'Owner', 'Phone', 'Total Invoiced (Credit)', 'Returns', 'Net Outstanding'];
        // Note: Date filter applies to invoice date here to see aging in period, or ignore dates for full list
        $sql = "
            SELECT 
                c.shop_name, c.owner_name, c.phone,
                (SELECT COALESCE(SUM(total_amount), 0) FROM mobile_orders WHERE customer_id = c.id AND payment_status='credit') as inv,
                (SELECT COALESCE(SUM(total_refund), 0) FROM sales_returns WHERE customer_id = c.id) as ret
            FROM customers c
            HAVING (inv - ret) > 0
            ORDER BY inv DESC
        ";
        break;

    case 'stock_valuation':
        $title = "Current Stock Valuation";
        $headers = ['Material', 'Current Qty', 'Unit Cost', 'Total Value', 'Status'];
        $sql = "
            SELECT 
                m.name,
                SUM(b.quantity_current) as qty,
                AVG(b.cost_per_unit) as cost,
                SUM(b.quantity_current * b.cost_per_unit) as total,
                'Active' as status
            FROM material_batches b
            JOIN raw_materials m ON b.material_id = m.id
            WHERE b.status = 'active'
            GROUP BY m.id
            ORDER BY total DESC
        ";
        break;

    case 'expense_summary':
        $title = "Operational Expense Summary";
        $headers = ['Category', 'Total Amount', 'Transaction Count'];
        $sql = "
            SELECT category, SUM(amount) as total, COUNT(*) as cnt
            FROM transactions
            WHERE type='expense' AND trans_date BETWEEN '$start_date' AND '$end_date'
            GROUP BY category
            ORDER BY total DESC
        ";
        break;
        
    case 'production_yield':
        $title = "Production Yield Report";
        $headers = ['Date', 'Batch', 'Product', 'Qty Produced'];
        $sql = "
            SELECT r.mfd_date, r.batch_code, p.product_name, r.quantity_produced
            FROM production_runs r
            JOIN products p ON r.product_id = p.id
            WHERE r.mfd_date BETWEEN '$start_date' AND '$end_date'
            ORDER BY r.mfd_date DESC
        ";
        break;
}

// Execute
$result = $conn->query($sql);
while($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// --- EXPORT HANDLER ---
if(isset($_POST['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$type.'_report.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($data as $row) fputcsv($output, $row);
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports Center | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .report-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .filter-form { display: flex; gap: 10px; align-items: center; }
        .filter-form input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-go { background: #34495e; color: white; border: none; padding: 9px 15px; border-radius: 4px; cursor: pointer; }
        .btn-export { background: #27ae60; color: white; border: none; padding: 9px 15px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        
        .report-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .report-table th { background: #2c3e50; color: white; padding: 12px; text-align: left; font-size: 13px; text-transform: uppercase; }
        .report-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .report-table tr:hover { background: #f9f9f9; }
        
        .no-data { text-align: center; padding: 40px; color: #7f8c8d; font-style: italic; background: white; border-radius: 8px; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="report-header">
            <div>
                <h2 style="margin:0;"><?php echo $title; ?></h2>
                <p style="color:#777; margin:5px 0 0 0;">Generated on <?php echo date('Y-m-d H:i'); ?></p>
            </div>
            
            <form method="GET" class="filter-form">
                <input type="hidden" name="type" value="<?php echo $type; ?>">
                
                <?php if($type != 'stock_valuation' && $type != 'customer_outstanding'): ?>
                <label>From:</label>
                <input type="date" name="start" value="<?php echo $start_date; ?>">
                <label>To:</label>
                <input type="date" name="end" value="<?php echo $end_date; ?>">
                <button type="submit" class="btn-go"><i class="fas fa-filter"></i> Filter</button>
                <?php endif; ?>
                
                <button type="submit" form="exportForm" class="btn-export"><i class="fas fa-file-csv"></i> Export CSV</button>
            </form>
            
            <!-- Hidden form for export POST request -->
            <form method="POST" id="exportForm" style="display:none;">
                <input type="hidden" name="export" value="1">
            </form>
        </div>

        <?php if(!empty($data)): ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <?php foreach($headers as $h): ?>
                            <th><?php echo $h; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($data as $row): ?>
                        <tr>
                            <?php foreach($row as $key => $cell): ?>
                                <td>
                                    <?php 
                                    // Simple formatting logic
                                    if(is_numeric($cell) && (strpos($key, 'total') !== false || strpos($key, 'revenue') !== false || strpos($key, 'cost') !== false)) {
                                        echo 'Rs ' . number_format($cell, 2);
                                    } elseif(is_numeric($cell) && strpos($key, 'id') === false) {
                                        echo number_format($cell, 2); // Quantities / Counts
                                    } else {
                                        echo htmlspecialchars($cell);
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">
                <i class="fas fa-search" style="font-size:30px; margin-bottom:10px;"></i><br>
                No records found for the selected criteria.
            </div>
        <?php endif; ?>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>