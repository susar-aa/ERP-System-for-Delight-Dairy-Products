<?php
require_once '../../config/db.php';

$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');

// Fetch Summary
$sql = "SELECT type, category, SUM(amount) as total 
        FROM transactions 
        WHERE trans_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        GROUP BY type, category";
$result = $conn->query($sql);

$summary = [];
$total_income = 0;
$total_expense = 0;

while($row = $result->fetch_assoc()) {
    $summary[] = $row;
    if($row['type'] == 'income') $total_income += $row['total'];
    else $total_expense += $row['total'];
}

$net_profit = $total_income - $total_expense;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Report | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .filter-section { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .report-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .report-card { background: white; padding: 20px; border-radius: 8px; }
        .summary-row { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 8px 0; }
        .final-total { font-size: 1.2rem; font-weight: bold; padding-top: 15px; text-align: right; border-top: 2px solid #ddd; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Financial Statement</h2>
            <a href="index.php" style="color:#3498db;">&larr; Back</a>
        </div>

        <div class="filter-section">
            <form method="GET" style="display:flex; gap:10px; align-items:center;">
                <label>From:</label>
                <input type="date" name="start" value="<?php echo $start_date; ?>" required>
                <label>To:</label>
                <input type="date" name="end" value="<?php echo $end_date; ?>" required>
                <button type="submit" style="background:#34495e; color:white; border:none; padding:8px 15px; cursor:pointer;">Generate Report</button>
            </form>
        </div>

        <div class="report-grid">
            <!-- Income Section -->
            <div class="report-card">
                <h3 style="color:#27ae60; margin-top:0;">Income</h3>
                <?php foreach($summary as $row): ?>
                    <?php if($row['type'] == 'income'): ?>
                        <div class="summary-row">
                            <span><?php echo $row['category']; ?></span>
                            <span>Rs <?php echo number_format($row['total'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="final-total" style="color:#27ae60;">
                    Total Income: Rs <?php echo number_format($total_income, 2); ?>
                </div>
            </div>

            <!-- Expense Section -->
            <div class="report-card">
                <h3 style="color:#c0392b; margin-top:0;">Expenses</h3>
                <?php foreach($summary as $row): ?>
                    <?php if($row['type'] == 'expense'): ?>
                        <div class="summary-row">
                            <span><?php echo $row['category']; ?></span>
                            <span>Rs <?php echo number_format($row['total'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="final-total" style="color:#c0392b;">
                    Total Expenses: Rs <?php echo number_format($total_expense, 2); ?>
                </div>
            </div>
        </div>

        <div style="background:white; padding:20px; margin-top:20px; border-radius:8px; text-align:center;">
            <h3>Net Profit / (Loss)</h3>
            <h1 style="color:<?php echo $net_profit >= 0 ? 'green' : 'red'; ?>;">
                Rs: <?php echo number_format($net_profit, 2); ?>
            </h1>
        </div>

    </div>
</body>
</html>