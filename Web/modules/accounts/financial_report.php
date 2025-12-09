<?php
require_once '../../config/db.php';

// Check Auth
if (!isset($_SESSION['user_id'])) { header("Location: ../../index.php"); exit; }

$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');

// 1. GET SALES REVENUE
$sales_q = $conn->query("SELECT SUM(total_amount) as val FROM mobile_orders WHERE order_date BETWEEN '$start_date' AND '$end_date'");
$sales_total = $sales_q->fetch_assoc()['val'] ?? 0;

// 2. GET OTHER TRANSACTIONS
$sql = "SELECT type, category, SUM(amount) as total 
        FROM transactions 
        WHERE trans_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        GROUP BY type, category";
$result = $conn->query($sql);

$other_income = [];
$expenses = [];
$total_other_inc = 0;
$total_expense = 0;

while($row = $result->fetch_assoc()) {
    if($row['type'] == 'income') {
        $other_income[] = $row;
        $total_other_inc += $row['total'];
    } else {
        $expenses[] = $row;
        $total_expense += $row['total'];
    }
}

$grand_income = $sales_total + $total_other_inc;
$net_profit = $grand_income - $total_expense;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .filter-section { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .report-sheet { background: white; padding: 40px; max-width: 800px; margin: 0 auto; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .sheet-header { text-align: center; border-bottom: 2px solid #333; margin-bottom: 20px; padding-bottom: 20px; }
        
        .section-title { font-weight: bold; font-size: 16px; margin-top: 20px; margin-bottom: 10px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #eee; font-size: 14px; }
        .row.total { font-weight: bold; border-top: 1px solid #999; border-bottom: none; margin-top: 5px; padding-top: 10px; font-size: 15px; }
        
        .profit-box { background: #f9f9f9; padding: 20px; text-align: center; margin-top: 30px; border: 1px solid #ddd; }
        .profit-val { font-size: 24px; font-weight: bold; color: #27ae60; }
        .loss-val { font-size: 24px; font-weight: bold; color: #c0392b; }
        
        @media print {
            .sidebar, .filter-section { display: none; }
            .main-content { margin: 0; padding: 0; }
            body { background: white; }
            .report-sheet { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="filter-section">
            <form method="GET" style="display:flex; gap:10px; align-items:center;">
                <label>From:</label>
                <input type="date" name="start" value="<?php echo $start_date; ?>" required style="padding:5px;">
                <label>To:</label>
                <input type="date" name="end" value="<?php echo $end_date; ?>" required style="padding:5px;">
                <button type="submit" style="background:#34495e; color:white; border:none; padding:8px 15px; cursor:pointer;">Generate Report</button>
                <button type="button" onclick="window.print()" style="background:#7f8c8d; color:white; border:none; padding:8px 15px; cursor:pointer; margin-left:auto;">Print</button>
            </form>
        </div>

        <div class="report-sheet">
            <div class="sheet-header">
                <h2 style="margin:0;">PROFIT & LOSS STATEMENT</h2>
                <p style="margin:5px 0;">Delight Dairy Products</p>
                <p style="font-size:12px; color:#555;">Period: <?php echo date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)); ?></p>
            </div>

            <!-- INCOME -->
            <div class="section-title">REVENUE / INCOME</div>
            
            <div class="row">
                <span>Sales Revenue (Invoiced)</span>
                <span><?php echo number_format($sales_total, 2); ?></span>
            </div>
            
            <?php foreach($other_income as $row): ?>
            <div class="row">
                <span>Other: <?php echo $row['category']; ?></span>
                <span><?php echo number_format($row['total'], 2); ?></span>
            </div>
            <?php endforeach; ?>
            
            <div class="row total">
                <span>TOTAL REVENUE</span>
                <span><?php echo number_format($grand_income, 2); ?></span>
            </div>

            <!-- EXPENSES -->
            <div class="section-title">OPERATING EXPENSES</div>
            
            <?php if(empty($expenses)): ?>
                <div class="row" style="color:#aaa;">No expenses recorded for this period.</div>
            <?php else: ?>
                <?php foreach($expenses as $row): ?>
                <div class="row">
                    <span><?php echo $row['category']; ?></span>
                    <span>(<?php echo number_format($row['total'], 2); ?>)</span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="row total">
                <span>TOTAL EXPENSES</span>
                <span>(<?php echo number_format($total_expense, 2); ?>)</span>
            </div>

            <!-- NET RESULT -->
            <div class="profit-box">
                <div>NET PROFIT / (LOSS)</div>
                <div class="<?php echo $net_profit >= 0 ? 'profit-val' : 'loss-val'; ?>">
                    Rs <?php echo number_format($net_profit, 2); ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>