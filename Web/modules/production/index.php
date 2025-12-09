<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

// --- 1. KPI DATA ---

// Total Production Value (This Month)
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$val_sql = "
    SELECT SUM(r.quantity_produced * p.selling_price) as val 
    FROM production_runs r 
    JOIN products p ON r.product_id = p.id 
    WHERE r.status = 'completed' AND r.mfd_date BETWEEN '$month_start' AND '$month_end'
";
$prod_value = $conn->query($val_sql)->fetch_assoc()['val'] ?? 0;

// Units Produced (Today)
$today = date('Y-m-d');
$units_today_sql = "SELECT SUM(quantity_produced) as c FROM production_runs WHERE mfd_date = '$today' AND status = 'completed'";
$units_today = $conn->query($units_today_sql)->fetch_assoc()['c'] ?? 0;

// Planned / In Progress Jobs
$pending_sql = "SELECT COUNT(*) as c FROM production_runs WHERE status IN ('planned', 'in_progress')";
$pending_count = $conn->query($pending_sql)->fetch_assoc()['c'];

// Total Products Defined
$prod_def_sql = "SELECT COUNT(*) as c FROM products";
$prod_count = $conn->query($prod_def_sql)->fetch_assoc()['c'];

// --- 2. CHART DATA PREPARATION ---

// Chart 1: Top 5 Products Produced (All Time or This Month)
$top_prod_sql = "
    SELECT p.product_name, SUM(r.quantity_produced) as total_qty
    FROM production_runs r
    JOIN products p ON r.product_id = p.id
    WHERE r.status = 'completed'
    GROUP BY p.id
    ORDER BY total_qty DESC
    LIMIT 5
";
$top_res = $conn->query($top_prod_sql);
$chart_labels = [];
$chart_data = [];
while($row = $top_res->fetch_assoc()) {
    $chart_labels[] = $row['product_name'];
    $chart_data[] = $row['total_qty'];
}

// Chart 2: Production Status Distribution
$status_sql = "
    SELECT 
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'qc_failed' THEN 1 ELSE 0 END) as failed
    FROM production_runs
";
$status_counts = $conn->query($status_sql)->fetch_assoc();

// Recent Activity for Table
$recent_sql = "
    SELECT r.*, p.product_name 
    FROM production_runs r 
    JOIN products p ON r.product_id = p.id 
    ORDER BY r.created_at DESC 
    LIMIT 5
";
$recent_runs = $conn->query($recent_sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Production Overview | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .grid-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #3498db; }
        .kpi-card h3 { margin: 0 0 5px 0; font-size: 13px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-card .value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .kpi-card i { font-size: 30px; color: #ecf0f1; }
        
        .kpi-purple { border-color: #9b59b6; } .kpi-purple i { color: #f5eef8; }
        .kpi-blue { border-color: #3498db; } .kpi-blue i { color: #ebf5fb; }
        .kpi-green { border-color: #27ae60; } .kpi-green i { color: #d5f5e3; }
        .kpi-orange { border-color: #e67e22; } .kpi-orange i { color: #fae5d3; }

        /* Quick Links */
        .quick-links { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .q-btn { background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #34495e; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; border: 1px solid transparent; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .q-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: #9b59b6; color: #8e44ad; }
        .q-btn i { font-size: 24px; margin-bottom: 10px; color: #9b59b6; }
        .q-btn span { font-weight: 600; font-size: 13px; }

        /* Charts & Tables Area */
        .dashboard-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .chart-box { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); min-height: 300px; display: flex; flex-direction: column; }
        .chart-header { font-weight: bold; font-size: 16px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; display:flex; justify-content:space-between; align-items:center; }

        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 10px; border-bottom: 2px solid #eee; color: #7f8c8d; font-size: 12px; text-transform: uppercase; }
        .data-table td { padding: 12px 10px; border-bottom: 1px solid #f9f9f9; font-size: 14px; }
        .status-badge { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .st-completed { background: #d4edda; color: #155724; }
        .st-planned { background: #e2e6ea; color: #495057; }
        .st-in_progress { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <h2 style="margin:0;">Production Overview</h2>
            <p style="color:#777; margin:5px 0 0 0;">Manufacturing performance and daily output.</p>
        </div>

        <!-- 1. QUICK LINKS -->
        <div class="quick-links">
            <a href="manage_products.php" class="q-btn">
                <i class="fas fa-box-open"></i>
                <span>Manage Products</span>
            </a>
            <a href="manage_recipes.php" class="q-btn">
                <i class="fas fa-flask"></i>
                <span>Manage Recipes</span>
            </a>
            <a href="add_production.php" class="q-btn">
                <i class="fas fa-cogs"></i>
                <span>Run Production</span>
            </a>
            <a href="#" class="q-btn" onclick="alert('Feature coming soon: Quality Control reports')">
                <i class="fas fa-clipboard-check"></i>
                <span>Quality Control</span>
            </a>
        </div>

        <!-- 2. KPI CARDS -->
        <div class="grid-container">
            <div class="kpi-card kpi-purple">
                <div>
                    <h3>Value Produced (Month)</h3>
                    <div class="value">Rs <?php echo number_format($prod_value / 1000, 1); ?>k</div>
                </div>
                <i class="fas fa-money-bill-wave" style="color:#9b59b6;"></i>
            </div>
            
            <div class="kpi-card kpi-green">
                <div>
                    <h3>Units Made (Today)</h3>
                    <div class="value"><?php echo $units_today; ?></div>
                </div>
                <i class="fas fa-check-circle" style="color:#27ae60;"></i>
            </div>

            <div class="kpi-card kpi-orange">
                <div>
                    <h3>Pending / Planned</h3>
                    <div class="value"><?php echo $pending_count; ?></div>
                </div>
                <i class="fas fa-clock" style="color:#e67e22;"></i>
            </div>

            <div class="kpi-card kpi-blue">
                <div>
                    <h3>Active Products</h3>
                    <div class="value"><?php echo $prod_count; ?></div>
                </div>
                <i class="fas fa-tags" style="color:#3498db;"></i>
            </div>
        </div>

        <!-- 3. CHARTS & DATA -->
        <div class="dashboard-row">
            
            <!-- Chart: Top Products -->
            <div class="chart-box" style="flex: 1.5;">
                <div class="chart-header">Top 5 Products (By Output Qty)</div>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="prodChart"></canvas>
                </div>
            </div>

            <!-- Recent Runs Table -->
            <div class="chart-box" style="flex: 1;">
                <div class="chart-header">
                    <span>Recent Production</span>
                    <a href="#" style="font-size:12px; text-decoration:none; color:#3498db;">View All</a>
                </div>
                
                <div style="flex-grow:1; overflow-y:auto; max-height:250px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if($recent_runs->num_rows > 0):
                                while($row = $recent_runs->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?php echo date('M d', strtotime($row['mfd_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><strong><?php echo $row['quantity_produced']; ?></strong></td>
                                <td><span class="status-badge st-<?php echo $row['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="4" style="text-align:center; padding:20px; color:#aaa;">No recent activity.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        
        <!-- Row 2: Status Chart -->
        <div class="dashboard-row">
             <div class="chart-box" style="height:350px;">
                <div class="chart-header">Production Status Overview</div>
                <div style="height:250px; width:100%; display:flex; justify-content:center;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Include Dashboard JS for Sidebar Toggle -->
    <script src="../../assets/js/dashboard.js"></script>

    <!-- Chart Config -->
    <script>
        // 1. Bar Chart: Production Output
        const ctxProd = document.getElementById('prodChart').getContext('2d');
        new Chart(ctxProd, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Units Produced',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(155, 89, 182, 0.7)', // Purple
                    borderColor: 'rgba(155, 89, 182, 1)',
                    borderWidth: 1,
                    borderRadius: 4
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

        // 2. Doughnut Chart: Status
        const ctxStat = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStat, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Planned', 'In Progress', 'QC Failed'],
                datasets: [{
                    data: [
                        <?php echo $status_counts['completed'] ?? 0; ?>, 
                        <?php echo $status_counts['planned'] ?? 0; ?>, 
                        <?php echo $status_counts['in_progress'] ?? 0; ?>,
                        <?php echo $status_counts['failed'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#27ae60', // Green
                        '#95a5a6', // Gray
                        '#f39c12', // Orange
                        '#c0392b'  // Red
                    ],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                },
                layout: {
                    padding: 20
                }
            }
        });
    </script>
</body>
</html>