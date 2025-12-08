<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

// --- 1. KPI DATA ---
// Total Stock Value
$val_sql = "SELECT SUM(quantity_current * cost_per_unit) as val FROM material_batches WHERE status = 'active'";
$stock_value = $conn->query($val_sql)->fetch_assoc()['val'] ?? 0;

// Total Active Batches
$batch_sql = "SELECT COUNT(*) as c FROM material_batches WHERE status = 'active'";
$active_batches = $conn->query($batch_sql)->fetch_assoc()['c'];

// Expiring Soon (Next 7 Days)
$exp_sql = "SELECT COUNT(*) as c FROM material_batches WHERE status = 'active' AND exp_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
$expiring_soon = $conn->query($exp_sql)->fetch_assoc()['c'];

// Low Stock Items (Below Reorder Level)
$low_sql = "
    SELECT m.name, m.unit_measure, m.reorder_level, COALESCE(SUM(b.quantity_current), 0) as current_qty
    FROM raw_materials m
    LEFT JOIN material_batches b ON m.id = b.material_id AND b.status = 'active'
    GROUP BY m.id
    HAVING current_qty <= m.reorder_level
";
$low_stock_res = $conn->query($low_sql);
$low_stock_count = $low_stock_res->num_rows;

// --- 2. CHART DATA PREPARATION ---

// Chart 1: Top 5 Materials by Value
$top_mat_sql = "
    SELECT m.name, SUM(b.quantity_current * b.cost_per_unit) as total_val
    FROM material_batches b
    JOIN raw_materials m ON b.material_id = m.id
    WHERE b.status = 'active'
    GROUP BY m.id
    ORDER BY total_val DESC
    LIMIT 5
";
$top_res = $conn->query($top_mat_sql);
$chart_labels = [];
$chart_data = [];
while($row = $top_res->fetch_assoc()) {
    $chart_labels[] = $row['name'];
    $chart_data[] = $row['total_val'];
}

// Chart 2: Inventory Status Distribution
$status_sql = "
    SELECT 
        SUM(CASE WHEN exp_date < CURDATE() THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN exp_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring,
        SUM(CASE WHEN exp_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY) OR exp_date IS NULL THEN 1 ELSE 0 END) as healthy
    FROM material_batches
    WHERE quantity_current > 0
";
$status_counts = $conn->query($status_sql)->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Overview | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .grid-container { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: space-between; border-left: 4px solid #3498db; }
        .kpi-card h3 { margin: 0 0 5px 0; font-size: 14px; color: #7f8c8d; text-transform: uppercase; }
        .kpi-card .value { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .kpi-card i { font-size: 30px; color: #ecf0f1; }
        
        .kpi-green { border-color: #27ae60; } .kpi-green i { color: #d5f5e3; }
        .kpi-orange { border-color: #f39c12; } .kpi-orange i { color: #fdebd0; }
        .kpi-red { border-color: #c0392b; } .kpi-red i { color: #fadbd8; }

        /* Quick Links - UPDATED GRID */
        .quick-links { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px; }
        .q-btn { background: white; padding: 15px; border-radius: 8px; text-align: center; text-decoration: none; color: #34495e; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; border: 1px solid transparent; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .q-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-color: #3498db; color: #2980b9; }
        .q-btn i { font-size: 24px; margin-bottom: 10px; color: #3498db; }
        .q-btn span { font-weight: 600; font-size: 13px; }

        /* Charts & Tables Area */
        .dashboard-row { display: flex; gap: 20px; }
        .chart-box { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); min-height: 300px; }
        .chart-header { font-weight: bold; font-size: 16px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; }

        .alert-table { width: 100%; border-collapse: collapse; }
        .alert-table th { text-align: left; padding: 10px; border-bottom: 2px solid #eee; color: #7f8c8d; font-size: 12px; }
        .alert-table td { padding: 10px; border-bottom: 1px solid #f9f9f9; font-size: 14px; }
        .badge-low { background: #fadbd8; color: #c0392b; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <h2 style="margin:0;">Inventory Overview</h2>
            <p style="color:#777; margin:5px 0 0 0;">Welcome back, here is what's happening in your stock.</p>
        </div>

        <!-- 1. QUICK LINKS (UPDATED) -->
        <div class="quick-links">
            <a href="manage_materials.php" class="q-btn">
                <i class="fas fa-cubes"></i>
                <span>Raw Materials</span>
            </a>
            <a href="manage_suppliers.php" class="q-btn">
                <i class="fas fa-truck"></i>
                <span>Suppliers</span>
            </a>
            <a href="add_stock.php" class="q-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Add Stock (GRN)</span>
            </a>
            <a href="stock_history.php" class="q-btn">
                <i class="fas fa-history"></i>
                <span>Bin Card (History)</span>
            </a>
            <a href="stock_audit.php" class="q-btn">
                <i class="fas fa-clipboard-check"></i>
                <span>Stock Audit</span>
            </a>
        </div>

        <!-- 2. KPI CARDS -->
        <div class="grid-container">
            <div class="kpi-card kpi-green">
                <div>
                    <h3>Total Inventory Value</h3>
                    <div class="value">Rs <?php echo number_format($stock_value / 1000, 1); ?>k</div>
                </div>
                <i class="fas fa-wallet" style="color:#27ae60;"></i>
            </div>
            
            <div class="kpi-card">
                <div>
                    <h3>Active Batches</h3>
                    <div class="value"><?php echo $active_batches; ?></div>
                </div>
                <i class="fas fa-layer-group" style="color:#3498db;"></i>
            </div>

            <div class="kpi-card kpi-orange">
                <div>
                    <h3>Expiring (7 Days)</h3>
                    <div class="value"><?php echo $expiring_soon; ?></div>
                </div>
                <i class="fas fa-hourglass-half" style="color:#f39c12;"></i>
            </div>

            <div class="kpi-card kpi-red">
                <div>
                    <h3>Low Stock Alerts</h3>
                    <div class="value"><?php echo $low_stock_count; ?></div>
                </div>
                <i class="fas fa-bell" style="color:#c0392b;"></i>
            </div>
        </div>

        <!-- 3. CHARTS & ALERTS -->
        <div class="dashboard-row">
            
            <!-- Chart: Top Materials -->
            <div class="chart-box" style="flex: 1.5;">
                <div class="chart-header">Top 5 Materials by Value</div>
                <div style="position: relative; height: 250px; width: 100%;">
                    <canvas id="valueChart"></canvas>
                </div>
            </div>

            <!-- Chart: Health & Low Stock -->
            <div class="chart-box" style="flex: 1; display:flex; flex-direction:column;">
                <div class="chart-header">Low Stock Alerts</div>
                
                <?php if($low_stock_count > 0): ?>
                    <div style="flex-grow:1; overflow-y:auto; max-height:250px;">
                        <table class="alert-table">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Current</th>
                                    <th>Reorder Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $low_stock_res->data_seek(0); 
                                while($row = $low_stock_res->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><span class="badge-low"><?php echo $row['current_qty'] . ' ' . $row['unit_measure']; ?></span></td>
                                    <td style="color:#777;"><?php echo $row['reorder_level'] . ' ' . $row['unit_measure']; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:40px; color:#27ae60;">
                        <i class="fas fa-check-circle" style="font-size:30px; margin-bottom:10px;"></i><br>
                        Stock levels are healthy.
                    </div>
                <?php endif; ?>
            </div>

        </div>
        
        <!-- Row 2: Pie Chart -->
        <div class="dashboard-row" style="margin-top:20px;">
             <div class="chart-box" style="height:300px;">
                <div class="chart-header">Stock Health Overview</div>
                <div style="height:220px; width:100%; display:flex; justify-content:center;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- Include Dashboard JS for Sidebar Toggle -->
    <script src="../../assets/js/dashboard.js"></script>

    <!-- Chart Config -->
    <script>
        // 1. Bar Chart: Value
        const ctxVal = document.getElementById('valueChart').getContext('2d');
        new Chart(ctxVal, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Stock Value (Rs)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
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

        // 2. Doughnut Chart: Status
        const ctxStat = document.getElementById('statusChart').getContext('2d');
        new Chart(ctxStat, {
            type: 'doughnut',
            data: {
                labels: ['Healthy', 'Expiring Soon', 'Expired'],
                datasets: [{
                    data: [
                        <?php echo $status_counts['healthy'] ?? 0; ?>, 
                        <?php echo $status_counts['expiring'] ?? 0; ?>, 
                        <?php echo $status_counts['expired'] ?? 0; ?>
                    ],
                    backgroundColor: [
                        '#2ecc71', // Green
                        '#f39c12', // Orange
                        '#e74c3c'  // Red
                    ],
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