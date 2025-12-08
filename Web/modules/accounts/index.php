<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// 1. Total Income (This Month)
$inc_q = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type='income' AND trans_date BETWEEN '$month_start' AND '$month_end'");
$income = $inc_q->fetch_assoc()['total'] ?? 0;

// 2. Total Expenses (This Month)
$exp_q = $conn->query("SELECT SUM(amount) as total FROM transactions WHERE type='expense' AND trans_date BETWEEN '$month_start' AND '$month_end'");
$expense = $exp_q->fetch_assoc()['total'] ?? 0;

// 3. Net Profit
$profit = $income - $expense;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accounts | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .action-bar { margin-bottom: 20px; display: flex; gap: 10px; }
        .btn { padding: 10px 20px; text-decoration: none; color: white; border-radius: 5px; font-size: 14px; }
        .btn-green { background-color: #27ae60; }
        .btn-red { background-color: #c0392b; }
        .btn-blue { background-color: #2980b9; }
    </style>
</head>
<body>

    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Accounts & Operations</h2>
        </div>

        <div class="action-bar">
            <a href="record_income.php" class="btn btn-green">1. Record Sales (Income)</a>
            <a href="manage_expenses.php" class="btn btn-red">2. Record Expenses</a>
            <a href="financial_report.php" class="btn btn-blue">3. Financial Reports</a>
        </div>

        <div class="kpi-grid">
            <div class="card">
                <h4>Income (This Month)</h4>
                <div class="value currency" style="color:#27ae60;">Rs: <?php echo number_format($income, 2); ?></div>
            </div>

            <div class="card">
                <h4>Expenses (This Month)</h4>
                <div class="value currency" style="color:#c0392b;">Rs: <?php echo number_format($expense, 2); ?></div>
            </div>

            <div class="card">
                <h4>Net Profit</h4>
                <div class="value currency" style="color:<?php echo $profit >= 0 ? 'green' : 'red'; ?>;">
                    Rs: <?php echo number_format($profit, 2); ?>
                </div>
            </div>
        </div>

        <h3>Recent Transactions</h3>
        <table style="width:100%; background:white; border-collapse:collapse; margin-top:10px;">
            <tr style="background:#ecf0f1; text-align:left;">
                <th style="padding:10px;">Date</th>
                <th style="padding:10px;">Type</th>
                <th style="padding:10px;">Category</th>
                <th style="padding:10px;">Description</th>
                <th style="padding:10px;">Amount (Rs)</th>
            </tr>
            <?php
            $recents = $conn->query("SELECT * FROM transactions ORDER BY trans_date DESC LIMIT 5");
            while($row = $recents->fetch_assoc()){
                $color = $row['type'] == 'income' ? 'green' : 'red';
                echo "<tr>
                        <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['trans_date']}</td>
                        <td style='padding:10px; border-bottom:1px solid #ddd; font-weight:bold; color:$color;'>".ucfirst($row['type'])."</td>
                        <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['category']}</td>
                        <td style='padding:10px; border-bottom:1px solid #ddd;'>{$row['description']}</td>
                        <td style='padding:10px; border-bottom:1px solid #ddd; font-weight:bold;'>".number_format($row['amount'], 2)."</td>
                      </tr>";
            }
            ?>
        </table>

    </div>
</body>
</html>