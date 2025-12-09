<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// 1. TOTAL SALES REVENUE (From Sales Module)
$sales_sql = "SELECT SUM(total_amount) as val FROM mobile_orders WHERE order_date BETWEEN '$month_start' AND '$month_end'";
$sales_revenue = $conn->query($sales_sql)->fetch_assoc()['val'] ?? 0;

// 2. OTHER INCOME (From Transactions Table)
$other_inc_sql = "SELECT SUM(amount) as val FROM transactions WHERE type='income' AND trans_date BETWEEN '$month_start' AND '$month_end'";
$other_income = $conn->query($other_inc_sql)->fetch_assoc()['val'] ?? 0;

$total_revenue = $sales_revenue + $other_income;

// 3. OPERATIONAL EXPENSES
$exp_sql = "SELECT SUM(amount) as val FROM transactions WHERE type='expense' AND trans_date BETWEEN '$month_start' AND '$month_end'";
$total_expense = $conn->query($exp_sql)->fetch_assoc()['val'] ?? 0;

// 4. NET PROFIT
$net_profit = $total_revenue - $total_expense;

// --- CHART DATA (Last 6 Months Profit Trend) ---
$trend_sql = "
    SELECT 
        DATE_FORMAT(date_col, '%Y-%m') as m,
        SUM(income) as inc,
        SUM(expense) as exp
    FROM (
        -- Sales Income
        SELECT order_date as date_col, total_amount as income, 0 as expense FROM mobile_orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        UNION ALL
        -- Other Income
        SELECT trans_date, amount, 0 FROM transactions WHERE type='income' AND trans_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        UNION ALL
        -- Expenses
        SELECT trans_date, 0, amount FROM transactions WHERE type='expense' AND trans_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    ) as combined
    GROUP BY m
    ORDER BY m ASC
";
$trend_res = $conn->query($trend_sql);
$chart_labels = [];
$chart_profit = [];
while($row = $trend_res->fetch_assoc()) {
    $chart_labels[] = date('M Y', strtotime($row['m'] . '-01'));
    $chart_profit[] = $row['inc'] - $row['exp'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accounts Overview | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .grid-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #3498db; }
        .kpi-card h3 { margin: 0 0 5px 0; font-size: 13px; color: #7f8c8d; text-transform: uppercase; }
        .kpi-card .value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .kpi-card i { font-size: 30px; opacity: 0.2; }
        
        .kpi-green { border-color: #27ae60; color: #27ae60; }
        .kpi-red { border-color: #c0392b; color: #c0392b; }
        .kpi-blue { border-color: #3498db; color: #3498db; }
        .kpi-orange { border-color: #f39c12; color: #f39c12; }

        .quick-links { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 30px; }
        .q-btn { background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #34495e; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s; display:flex; flex-direction:column; align-items:center; }
        .q-btn:hover { transform: translateY(-3px); color: #3498db; }
        .q-btn i { font-size: 24px; margin-bottom: 10px; }

        .chart-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <h2 style="margin:0;">Financial Overview</h2>
            <p style="color:#777; margin:5px 0 0 0;">Profit & Loss snapshot for <?php echo date('F Y'); ?>.</p>
        </div>

        <!-- QUICK LINKS -->
        <div class="quick-links">
            <a href="manage_expenses.php" class="q-btn">
                <i class="fas fa-file-invoice-dollar" style="color:#c0392b;"></i>
                <span>Manage Expenses</span>
            </a>
            <a href="record_income.php" class="q-btn">
                <i class="fas fa-hand-holding-usd" style="color:#27ae60;"></i>
                <span>Other Revenue</span>
            </a>
            <a href="financial_report.php" class="q-btn">
                <i class="fas fa-chart-pie" style="color:#3498db;"></i>
                <span>Financial Reports</span>
            </a>
        </div>

        <!-- KPI CARDS -->
        <div class="grid-container">
            <div class="kpi-card kpi-blue">
                <div>
                    <h3>Total Revenue</h3>
                    <div class="value">Rs <?php echo number_format($total_revenue / 1000, 1); ?>k</div>
                </div>
                <i class="fas fa-coins kpi-blue"></i>
            </div>
            
            <div class="kpi-card kpi-red">
                <div>
                    <h3>Total Expenses</h3>
                    <div class="value">Rs <?php echo number_format($total_expense / 1000, 1); ?>k</div>
                </div>
                <i class="fas fa-wallet kpi-red"></i>
            </div>

            <div class="kpi-card kpi-green">
                <div>
                    <h3>Net Profit</h3>
                    <div class="value">Rs <?php echo number_format($net_profit / 1000, 1); ?>k</div>
                </div>
                <i class="fas fa-chart-line kpi-green"></i>
            </div>

            <div class="kpi-card kpi-orange">
                <div>
                    <h3>Margin</h3>
                    <div class="value">
                        <?php echo ($total_revenue > 0) ? round(($net_profit / $total_revenue) * 100, 1) : 0; ?>%
                    </div>
                </div>
                <i class="fas fa-percent kpi-orange"></i>
            </div>
        </div>

        <!-- PROFIT CHART -->
        <div class="chart-box">
            <h4 style="margin-top:0; color:#555;">Net Profit Trend (Last 6 Months)</h4>
            <div style="height: 300px;">
                <canvas id="profitChart"></canvas>
            </div>
        </div>

    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>

    <script>
        const ctx = document.getElementById('profitChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Net Profit (Rs)',
                    data: <?php echo json_encode($chart_profit); ?>,
                    borderColor: '#2ecc71',
                    backgroundColor: 'rgba(46, 204, 113, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>