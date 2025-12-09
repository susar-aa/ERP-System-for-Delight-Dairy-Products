<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

// --- 1. KPI DATA ---

// Date Ranges
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

// Total Revenue (This Month)
$rev_sql = "SELECT SUM(total_amount) as val FROM mobile_orders WHERE order_date BETWEEN '$month_start' AND '$month_end'";
$revenue = $conn->query($rev_sql)->fetch_assoc()['val'] ?? 0;

// Total Outstanding (Credit)
// Anything NOT 'paid', 'cash', or 'bank_transfer' is considered credit/outstanding
$out_sql = "SELECT SUM(total_amount) as val FROM mobile_orders WHERE payment_status NOT IN ('paid', 'cash', 'bank_transfer')";
$outstanding = $conn->query($out_sql)->fetch_assoc()['val'] ?? 0;

// Active Shops (Total Customers)
$cust_sql = "SELECT COUNT(*) as c FROM customers";
$total_shops = $conn->query($cust_sql)->fetch_assoc()['c'];

// Invoice Count (Today)
$today = date('Y-m-d');
$inv_today_sql = "SELECT COUNT(*) as c FROM mobile_orders WHERE order_date = '$today'";
$inv_today = $conn->query($inv_today_sql)->fetch_assoc()['c'];

// --- 2. CHART DATA PREPARATION ---

// Chart 1: Sales Trend (Last 6 Months)
$trend_sql = "
    SELECT DATE_FORMAT(order_date, '%Y-%m') as m, SUM(total_amount) as total
    FROM mobile_orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY m
    ORDER BY m ASC
";
$trend_res = $conn->query($trend_sql);
$months = [];
$sales_data = [];
while($row = $trend_res->fetch_assoc()) {
    $months[] = date('M Y', strtotime($row['m'] . '-01'));
    $sales_data[] = $row['total'];
}

// Chart 2: Payment Status Distribution (All Time)
$pay_sql = "
    SELECT payment_status, COUNT(*) as c 
    FROM mobile_orders 
    GROUP BY payment_status
";
$pay_res = $conn->query($pay_sql);
$pay_labels = [];
$pay_data = [];
$pay_colors = [];

// Color mapping
$colors = [
    'paid' => '#27ae60', 'cash' => '#2ecc71',
    'credit' => '#e74c3c', 
    'cheque' => '#f39c12', 
    'bank_transfer' => '#3498db'
];

while($row = $pay_res->fetch_assoc()) {
    $st = strtolower($row['payment_status']);
    $pay_labels[] = ucfirst(str_replace('_', ' ', $st));
    $pay_data[] = $row['c'];
    $pay_colors[] = $colors[$st] ?? '#95a5a6';
}

// Recent Invoices
$recent_sql = "
    SELECT o.*, c.shop_name 
    FROM mobile_orders o 
    JOIN customers c ON o.customer_id = c.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
";
$recent_orders = $conn->query($recent_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Overview | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .grid-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #3498db; }
        .kpi-card h3 { margin: 0 0 5px 0; font-size: 13px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-card .value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .kpi-card i { font-size: 30px; color: #ecf0f1; }
        
        .kpi-green { border-color: #27ae60; } .kpi-green i { color: #d5f5e3; }
        .kpi-red { border-color: #c0392b; } .kpi-red i { color: #fadbd8; }
        .kpi-orange { border-color: #f39c12; } .kpi-orange i { color: #fdebd0; }
        .kpi-blue { border-color: #3498db; } .kpi-blue i { color: #ebf5fb; }

        /* Quick Links */
        .quick-links { display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-bottom: 30px; }
        .q-btn { background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #34495e; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; border: 1px solid transparent; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .q-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: #3498db; color: #2980b9; }
        .q-btn i { font-size: 24px; margin-bottom: 10px; color: #3498db; }
        .q-btn span { font-weight: 600; font-size: 13px; }

        /* Charts & Tables Area */
        .dashboard-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .chart-box { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); min-height: 300px; display: flex; flex-direction: column; }
        .chart-header { font-weight: bold; font-size: 16px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; display:flex; justify-content:space-between; align-items:center; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 10px; border-bottom: 2px solid #eee; color: #7f8c8d; font-size: 12px; text-transform: uppercase; }
        .data-table td { padding: 12px 10px; border-bottom: 1px solid #f9f9f9; font-size: 14px; }
        
        .status-badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .st-paid, .st-cash { background: #d4edda; color: #155724; }
        .st-credit { background: #fadbd8; color: #721c24; }
        .st-cheque { background: #fff3cd; color: #856404; }
        .st-bank_transfer { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <h2 style="margin:0;">Sales & Distribution Overview</h2>
            <p style="color:#777; margin:5px 0 0 0;">Performance metrics and distribution channels.</p>
        </div>

        <!-- 1. QUICK LINKS -->
        <div class="quick-links">
            <a href="invoice_panel.php" class="q-btn">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Invoicing Panel</span>
            </a>
            <a href="sales_returns.php" class="q-btn">
                <i class="fas fa-undo-alt" style="color:#e74c3c;"></i>
                <span style="color:#e74c3c;">Sales Returns</span>
            </a>
            <a href="sales_targets.php" class="q-btn">
                <i class="fas fa-bullseye" style="color:#f39c12;"></i>
                <span style="color:#f39c12;">Targets</span>
            </a>
            <a href="manage_customers.php" class="q-btn">
                <i class="fas fa-store"></i>
                <span>Customers</span>
            </a>
            <a href="manage_routes.php" class="q-btn">
                <i class="fas fa-route"></i>
                <span>Routes</span>
            </a>
            <a href="manage_reps.php" class="q-btn">
                <i class="fas fa-user-tie"></i>
                <span>Sales Reps</span>
            </a>
        </div>

        <!-- 2. KPI CARDS -->
        <div class="grid-container">
            <div class="kpi-card kpi-green">
                <div>
                    <h3>Revenue (This Month)</h3>
                    <div class="value">Rs <?php echo number_format($revenue / 1000, 1); ?>k</div>
                </div>
                <i class="fas fa-chart-line" style="color:#27ae60;"></i>
            </div>
            
            <div class="kpi-card kpi-red">
                <div>
                    <h3>Outstanding Balance</h3>
                    <div class="value">Rs <?php echo number_format($outstanding / 1000, 1); ?>k</div>
                </div>
                <i class="fas fa-exclamation-circle" style="color:#c0392b;"></i>
            </div>

            <div class="kpi-card kpi-blue">
                <div>
                    <h3>Active Shops</h3>
                    <div class="value"><?php echo $total_shops; ?></div>
                </div>
                <i class="fas fa-store-alt" style="color:#3498db;"></i>
            </div>

            <div class="kpi-card kpi-orange">
                <div>
                    <h3>Invoices Today</h3>
                    <div class="value"><?php echo $inv_today; ?></div>
                </div>
                <i class="fas fa-receipt" style="color:#f39c12;"></i>
            </div>
        </div>

        <!-- 3. CHARTS & DATA -->
        <div class="dashboard-row">
            
            <!-- Chart: Sales Trend -->
            <div class="chart-box" style="flex: 2;">
                <div class="chart-header">Sales Growth (Last 6 Months)</div>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Chart: Payment Status -->
            <div class="chart-box" style="flex: 1;">
                <div class="chart-header">Payment Methods</div>
                <div style="height:220px; width:100%; display:flex; justify-content:center;">
                    <canvas id="payChart"></canvas>
                </div>
            </div>

        </div>
        
        <!-- Recent Activity -->
        <div class="dashboard-row">
             <div class="chart-box" style="height:auto;">
                <div class="chart-header">
                    <span>Recent Invoices</span>
                    <a href="view_orders.php" style="font-size:12px; text-decoration:none; color:#3498db;">View All</a>
                </div>
                
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if($recent_orders->num_rows > 0):
                                while($row = $recent_orders->fetch_assoc()): 
                                $st = strtolower(str_replace(' ', '_', $row['payment_status']));
                            ?>
                            <tr>
                                <td><?php echo date('M d', strtotime($row['order_date'])); ?></td>
                                <td style="font-weight:bold; color:#2980b9;"><?php echo $row['order_ref']; ?></td>
                                <td><?php echo htmlspecialchars($row['shop_name']); ?></td>
                                <td><strong>Rs <?php echo number_format($row['total_amount'], 2); ?></strong></td>
                                <td><span class="status-badge st-<?php echo $st; ?>"><?php echo ucfirst(str_replace('_', ' ', $st)); ?></span></td>
                                <td><a href="invoice_panel.php?id=<?php echo $row['id']; ?>&mode=view" style="color:#3498db; text-decoration:none; font-weight:bold;">View</a></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:20px; color:#aaa;">No recent invoices.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>

    <!-- Chart Config -->
    <script>
        // 1. Line Chart: Sales Trend
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Sales Revenue (Rs)',
                    data: <?php echo json_encode($sales_data); ?>,
                    borderColor: '#2980b9',
                    backgroundColor: 'rgba(41, 128, 185, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2, 4] } },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // 2. Doughnut Chart: Payments
        const ctxPay = document.getElementById('payChart').getContext('2d');
        new Chart(ctxPay, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($pay_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($pay_data); ?>,
                    backgroundColor: <?php echo json_encode($pay_colors); ?>,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });
    </script>
</body>
</html>