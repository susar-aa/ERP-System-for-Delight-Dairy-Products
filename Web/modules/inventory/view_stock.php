<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- HANDLE STOCK ADJUSTMENT (WRITE-OFF) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'adjust_stock') {
    $batch_id = intval($_POST['batch_id']);
    $reduce_qty = floatval($_POST['reduce_qty']);
    $reason = trim($_POST['reason']);

    // Check current stock first
    $check = $conn->query("SELECT quantity_current FROM material_batches WHERE id = $batch_id");
    if($check->num_rows > 0) {
        $curr = $check->fetch_assoc()['quantity_current'];
        
        if($reduce_qty > $curr) {
            $msg = "<div class='alert error'>Error: Cannot remove more than current stock ($curr).</div>";
        } else {
            $new_qty = $curr - $reduce_qty;
            $status = ($new_qty <= 0) ? 'exhausted' : 'active';
            
            // Update Batch
            $stmt = $conn->prepare("UPDATE material_batches SET quantity_current = ?, status = ? WHERE id = ?");
            $stmt->bind_param("dsi", $new_qty, $status, $batch_id);
            
            if($stmt->execute()) {
                // Ideally, record this in a separate 'inventory_log' table here
                $msg = "<div class='alert success'>Stock Adjusted Successfully! New Qty: $new_qty</div>";
            } else {
                $msg = "<div class='alert error'>Database Error: " . $conn->error . "</div>";
            }
        }
    }
}

// --- FILTERS & QUERY ---
$filter_mat = isset($_GET['material_id']) ? intval($_GET['material_id']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Base Query
$sql = "SELECT b.*, m.name as material_name, m.unit_measure 
        FROM material_batches b 
        JOIN raw_materials m ON b.material_id = m.id 
        WHERE b.quantity_current > 0"; // Only show items with stock

if (!empty($filter_mat)) {
    $sql .= " AND b.material_id = $filter_mat";
}

if ($filter_status == 'expired') {
    $sql .= " AND b.exp_date < CURDATE() AND b.exp_date IS NOT NULL";
} elseif ($filter_status == 'active') {
    $sql .= " AND (b.exp_date >= CURDATE() OR b.exp_date IS NULL)";
}

$sql .= " ORDER BY b.exp_date ASC"; // Show expiring items first

$stock = $conn->query($sql);
$materials = $conn->query("SELECT * FROM raw_materials ORDER BY name");

// --- KPI CALCULATIONS ---
// Total Value of Active Stock
$kpi_val = $conn->query("SELECT SUM(quantity_current * cost_per_unit) as val FROM material_batches WHERE quantity_current > 0 AND status='active'")->fetch_assoc()['val'];

// Count of Batches Expiring in 7 Days
$kpi_warn = $conn->query("SELECT COUNT(*) as c FROM material_batches WHERE quantity_current > 0 AND exp_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'];

// Count of Already Expired
$kpi_exp = $conn->query("SELECT COUNT(*) as c FROM material_batches WHERE quantity_current > 0 AND exp_date < CURDATE()")->fetch_assoc()['c'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Stock | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* KPI Cards */
        .kpi-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .kpi-card { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .kpi-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; }
        .bg-green { background: #27ae60; }
        .bg-orange { background: #f39c12; }
        .bg-red { background: #c0392b; }
        .kpi-info h4 { margin: 0 0 5px 0; color: #7f8c8d; font-size: 13px; text-transform: uppercase; }
        .kpi-info span { font-size: 20px; font-weight: bold; color: #2c3e50; }

        /* Filter Bar */
        .filter-bar { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .filter-bar select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px; }
        .btn-filter { background: #34495e; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-print { background: #7f8c8d; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; margin-left: auto; text-decoration: none; display: inline-block; font-size: 13px; }

        /* Table */
        .stock-table { width: 100%; background: white; border-collapse: collapse; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stock-table th { background: #2c3e50; color: white; padding: 12px; text-align: left; font-size: 13px; }
        .stock-table td { padding: 12px; border-bottom: 1px solid #ecf0f1; font-size: 14px; }
        .stock-table tr:hover { background: #f9f9f9; }

        /* Status & Badges */
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .st-active { background: #d4edda; color: #155724; }
        .st-expired { background: #fadbd8; color: #721c24; }
        .st-warning { background: #fcf3cf; color: #856404; }
        .st-stable { background: #d1ecf1; color: #0c5460; }

        .btn-adjust { background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; }
        .modal-header { font-weight: bold; font-size: 18px; margin-bottom: 15px; display: flex; justify-content: space-between; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 12px; }
        .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media print {
            .sidebar, .filter-bar, .btn-adjust, .modal, .top-header { display: none !important; }
            .main-content { margin-left: 0; padding: 0; width: 100%; }
            .kpi-row { display: none; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="top-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div>
                <h2 style="margin:0;">Stock Levels</h2>
                <p style="margin:0; color:#777; font-size:14px;">Real-time inventory view</p>
            </div>
            <a href="add_stock.php" style="background:#27ae60; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; font-weight:bold;">
                <i class="fas fa-plus"></i> Add Stock
            </a>
        </div>

        <?php echo $msg; ?>

        <!-- KPI CARDS -->
        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-icon bg-green"><i class="fas fa-dollar-sign"></i></div>
                <div class="kpi-info">
                    <h4>Total Stock Value</h4>
                    <span>Rs <?php echo number_format($kpi_val, 2); ?></span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon bg-orange"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="kpi-info">
                    <h4>Expiring (7 Days)</h4>
                    <span><?php echo $kpi_warn; ?> Batches</span>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon bg-red"><i class="fas fa-times-circle"></i></div>
                <div class="kpi-info">
                    <h4>Expired Items</h4>
                    <span><?php echo $kpi_exp; ?> Batches</span>
                </div>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <select name="material_id">
                <option value="">All Materials</option>
                <?php while($m = $materials->fetch_assoc()): ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo $filter_mat == $m['id'] ? 'selected' : ''; ?>>
                        <?php echo $m['name']; ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <select name="status">
                <option value="">All Statuses</option>
                <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active Only</option>
                <option value="expired" <?php echo $filter_status == 'expired' ? 'selected' : ''; ?>>Expired Only</option>
            </select>

            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
            <a href="view_stock.php" style="color:#c0392b; font-size:13px; text-decoration:none;">Reset</a>
            
            <button type="button" onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Print List</button>
        </form>

        <!-- DATA TABLE -->
        <table class="stock-table">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>Material</th>
                    <th>Qty Available</th>
                    <th>Unit Cost</th>
                    <th>Value (Rs)</th>
                    <th>MFD</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th class="no-print">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($stock->num_rows > 0): ?>
                    <?php while($row = $stock->fetch_assoc()): 
                        // Logic for status display
                        $status_class = 'st-active';
                        $status_text = 'Active';
                        $is_expired = false;

                        if($row['exp_date'] && $row['exp_date'] < date('Y-m-d')) {
                            $status_class = 'st-expired';
                            $status_text = 'Expired';
                            $is_expired = true;
                        } elseif ($row['exp_date'] && $row['exp_date'] < date('Y-m-d', strtotime('+7 days'))) {
                            $status_class = 'st-warning';
                            $status_text = 'Expiring Soon';
                        } elseif (!$row['exp_date']) {
                            $status_class = 'st-stable';
                            $status_text = 'Stable';
                        }
                    ?>
                    <tr>
                        <td style="font-weight:bold; color:#2980b9;"><?php echo $row['batch_code']; ?></td>
                        <td><?php echo $row['material_name']; ?></td>
                        <td>
                            <strong><?php echo $row['quantity_current']; ?></strong> 
                            <span style="color:#777; font-size:12px;"><?php echo $row['unit_measure']; ?></span>
                        </td>
                        <td><?php echo number_format($row['cost_per_unit'], 2); ?></td>
                        <td><?php echo number_format($row['quantity_current'] * $row['cost_per_unit'], 2); ?></td>
                        <td><?php echo $row['mfd_date'] ? $row['mfd_date'] : '-'; ?></td>
                        <td>
                            <?php echo $row['exp_date'] ? $row['exp_date'] : '<span style="color:#999;">N/A</span>'; ?>
                        </td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                        <td class="no-print">
                            <button class="btn-adjust" onclick='openAdjustModal(<?php echo json_encode($row); ?>)'>
                                <i class="fas fa-minus-circle"></i> Adjust
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" style="text-align:center; padding:20px;">No active stock found matching criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ADJUSTMENT MODAL -->
    <div id="adjustModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>Stock Write-off / Adjustment</span>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="adjust_stock">
                <input type="hidden" name="batch_id" id="adjBatchId">
                
                <div style="background:#f8f9fa; padding:10px; border-radius:4px; margin-bottom:15px; font-size:13px;">
                    <strong>Batch:</strong> <span id="adjBatchCode"></span><br>
                    <strong>Current Qty:</strong> <span id="adjCurrQty"></span>
                </div>

                <div class="form-group">
                    <label>Quantity to Remove (Write-off)</label>
                    <input type="number" name="reduce_qty" step="0.01" required placeholder="Amount to remove">
                </div>
                
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" rows="2" required placeholder="e.g. Spillage, Damaged, Theft"></textarea>
                </div>

                <button type="submit" style="width:100%; background:#c0392b; color:white; padding:10px; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">Confirm Adjustment</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const modal = document.getElementById('adjustModal');
        
        function openAdjustModal(data) {
            document.getElementById('adjBatchId').value = data.id;
            document.getElementById('adjBatchCode').innerText = data.batch_code;
            document.getElementById('adjCurrQty').innerText = data.quantity_current + ' ' + data.unit_measure;
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>