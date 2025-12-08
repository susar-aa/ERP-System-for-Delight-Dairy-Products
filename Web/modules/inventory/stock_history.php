<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$selected_mat = isset($_GET['material_id']) ? intval($_GET['material_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Fetch Materials for Dropdown
$materials = $conn->query("SELECT * FROM raw_materials ORDER BY name");

// Fetch Logs if Material Selected
$logs = null;
$current_stock = 0;
$mat_info = null;

if ($selected_mat > 0) {
    // Get Material Info & Current Stock
    $mat_q = $conn->query("
        SELECT m.*, 
        (SELECT SUM(quantity_current) FROM material_batches WHERE material_id = m.id) as curr_qty
        FROM raw_materials m WHERE id = $selected_mat
    ");
    $mat_info = $mat_q->fetch_assoc();
    $current_stock = $mat_info['curr_qty'] ?? 0;

    // Fetch History Logs
    // Note: Since we just added the log table, this might be empty initially.
    // We will show GRNs from the batches table as 'Receives' to populate history if logs are missing.
    
    $sql = "
        SELECT 
            'LOG' as source,
            log_date as date, 
            transaction_type as type, 
            reference_info as ref, 
            qty_change, 
            balance_after,
            u.username as user
        FROM inventory_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE material_id = $selected_mat 
        AND log_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        
        UNION ALL
        
        -- Fallback: Show GRNs from batch table if not logged yet
        SELECT 
            'BATCH' as source,
            received_date as date, 
            'GRN' as type, 
            batch_code as ref, 
            quantity_initial as qty_change, 
            0 as balance_after, -- Calculated locally
            'System' as user
        FROM material_batches
        WHERE material_id = $selected_mat
        AND received_date BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
        AND id NOT IN (SELECT batch_id FROM inventory_logs WHERE transaction_type='GRN') -- Avoid duplicates if logged
        
        ORDER BY date DESC
    ";
    
    $logs = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bin Card / Stock History | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; gap: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; flex: 1; }
        .filter-group label { font-size: 12px; font-weight: bold; color: #555; }
        .filter-group select, .filter-group input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        
        .btn-go { background: #3498db; color: white; border: none; padding: 10px 25px; border-radius: 4px; cursor: pointer; height: 38px; font-weight: bold; }
        
        .stock-card { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .stock-info h2 { margin: 0; font-size: 24px; }
        .stock-info p { margin: 5px 0 0; opacity: 0.8; }
        .stock-big-num { font-size: 40px; font-weight: bold; }
        
        .log-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .log-table th { background: #ecf0f1; padding: 12px 15px; text-align: left; color: #2c3e50; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #bdc3c7; }
        .log-table td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 14px; }
        .log-table tr:hover { background: #f9f9f9; }
        
        /* Type Badges */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .type-GRN { background: #d4edda; color: #155724; }
        .type-PRODUCTION { background: #f8d7da; color: #721c24; }
        .type-ADJUSTMENT { background: #fff3cd; color: #856404; }
        .type-AUDIT { background: #d1ecf1; color: #0c5460; }
        
        .qty-plus { color: #27ae60; font-weight: bold; }
        .qty-minus { color: #c0392b; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <h2 style="margin:0;">Bin Card History</h2>
            <p style="color:#777; margin:5px 0 0 0;">Track stock movements for any item.</p>
        </div>

        <form method="GET" class="filter-box">
            <div class="filter-group">
                <label>Select Material</label>
                <select name="material_id" required>
                    <option value="">-- Choose Material --</option>
                    <?php while($m = $materials->fetch_assoc()): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $selected_mat == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo $m['name']; ?> (<?php echo $m['unit_measure']; ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            
            <button type="submit" class="btn-go"><i class="fas fa-search"></i> Load History</button>
        </form>

        <?php if ($selected_mat > 0 && $mat_info): ?>
            
            <div class="stock-card">
                <div class="stock-info">
                    <h2><?php echo htmlspecialchars($mat_info['name']); ?></h2>
                    <p>Reorder Level: <?php echo $mat_info['reorder_level'] . ' ' . $mat_info['unit_measure']; ?></p>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px;">Current Stock</div>
                    <div class="stock-big-num">
                        <?php echo number_format($current_stock, 2); ?> 
                        <span style="font-size:16px; font-weight:normal;"><?php echo $mat_info['unit_measure']; ?></span>
                    </div>
                </div>
            </div>

            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:150px;">Date & Time</th>
                        <th style="width:120px;">Type</th>
                        <th>Reference</th>
                        <th style="text-align:right;">In / Out</th>
                        <th style="text-align:right;">Balance</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($logs && $logs->num_rows > 0): 
                        // Note: To show running balance correctly in a DESC list, 
                        // typically you calculate forward from opening, or just rely on 'balance_after' stored in DB.
                        // Here we display what we have.
                        while($row = $logs->fetch_assoc()): 
                            $qty_class = $row['qty_change'] >= 0 ? 'qty-plus' : 'qty-minus';
                            $sign = $row['qty_change'] >= 0 ? '+' : '';
                    ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($row['date'])); ?></td>
                        <td><span class="badge type-<?php echo $row['type']; ?>"><?php echo $row['type']; ?></span></td>
                        <td><?php echo htmlspecialchars($row['ref']); ?></td>
                        <td style="text-align:right;" class="<?php echo $qty_class; ?>">
                            <?php echo $sign . number_format($row['qty_change'], 2); ?>
                        </td>
                        <td style="text-align:right; font-weight:bold; color:#555;">
                            <?php echo ($row['source'] == 'LOG') ? number_format($row['balance_after'], 2) : '-'; ?>
                        </td>
                        <td><span style="color:#777; font-size:12px;"><?php echo $row['user']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">No movement records found for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($selected_mat == 0): ?>
            <div style="text-align:center; padding:50px; color:#aaa; border:2px dashed #ddd; border-radius:8px;">
                <i class="fas fa-arrow-up" style="font-size:30px; margin-bottom:10px;"></i><br>
                Please select a material above to view its history.
            </div>
        <?php endif; ?>

    </div>
    
    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>