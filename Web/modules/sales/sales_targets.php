<?php
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../index.php"); exit; }

$msg = "";
$current_month = date('Y-m'); // Default to current month

// --- 1. HANDLE ACTIONS ---

// DELETE TARGET
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    if($conn->query("DELETE FROM sales_targets WHERE id=$id")) {
        $msg = "<div class='alert success'>Target Removed Successfully!</div>";
    } else {
        $msg = "<div class='alert error'>Error deleting target: " . $conn->error . "</div>";
    }
}

// SET / UPDATE TARGET
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'set_target') {
    $rep_id = intval($_POST['rep_id']);
    $month = $_POST['month'];
    $amount = floatval($_POST['amount']);
    $comm = floatval($_POST['commission']);

    // Check if target exists for this month
    $check = $conn->query("SELECT id FROM sales_targets WHERE rep_id=$rep_id AND target_month='$month'");
    
    if ($check->num_rows > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE sales_targets SET target_amount=?, commission_rate=? WHERE rep_id=? AND target_month=?");
        $stmt->bind_param("ddis", $amount, $comm, $rep_id, $month);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO sales_targets (rep_id, target_month, target_amount, commission_rate) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isdd", $rep_id, $month, $amount, $comm);
    }
    
    if ($stmt->execute()) $msg = "<div class='alert success'>Target Saved Successfully!</div>";
    else $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
}

// --- 2. RECALCULATE ACHIEVEMENTS ---
// This updates the 'achieved_amount' based on actual sales for the selected month
$update_sql = "
    UPDATE sales_targets t
    SET achieved_amount = (
        SELECT COALESCE(SUM(total_amount), 0) 
        FROM mobile_orders 
        WHERE rep_id = t.rep_id 
        AND DATE_FORMAT(order_date, '%Y-%m') = t.target_month
        AND payment_status IN ('paid', 'cash', 'cheque', 'bank_transfer') -- Only count valid sales
    )
    WHERE target_month = '$current_month'
";
$conn->query($update_sql);

// --- 3. FETCH DATA ---
$reps = $conn->query("SELECT sr.id, u.full_name FROM sales_reps sr JOIN users u ON sr.user_id = u.id");

$targets = $conn->query("
    SELECT t.*, u.full_name, sr.vehicle_no 
    FROM sales_targets t 
    JOIN sales_reps sr ON t.rep_id = sr.id 
    JOIN users u ON sr.user_id = u.id 
    WHERE t.target_month = '$current_month'
    ORDER BY t.achieved_amount DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Targets | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add { background: #8e44ad; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; }
        
        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        
        .target-card { background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); padding: 20px; position: relative; overflow: hidden; transition: transform 0.2s; }
        .target-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        
        .card-header { display: flex; justify-content: space-between; margin-bottom: 15px; padding-right: 60px; /* Space for actions */ }
        .rep-name { font-weight: bold; font-size: 16px; color: #2c3e50; }
        .rep-veh { font-size: 12px; color: #7f8c8d; }
        
        .progress-container { background: #ecf0f1; border-radius: 10px; height: 10px; width: 100%; margin: 15px 0; overflow: hidden; }
        .progress-bar { height: 100%; background: #2ecc71; width: 0%; transition: width 1s; }
        
        .stats-row { display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-bottom: 5px; }
        .money { font-weight: bold; color: #2c3e50; }
        
        /* Actions & Badge */
        .comm-badge { background: #f39c12; color: white; padding: 2px 8px; font-size: 11px; border-radius: 4px; font-weight: bold; display: inline-block; margin-top: 5px; }
        
        .card-actions { position: absolute; top: 15px; right: 15px; display: flex; gap: 5px; }
        .action-icon { width: 30px; height: 30px; border-radius: 50%; background: #f8f9fa; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #555; border: 1px solid #eee; text-decoration: none; transition: 0.2s; }
        .action-icon:hover { background: #3498db; color: white; border-color: #3498db; }
        .action-icon.delete:hover { background: #c0392b; border-color: #c0392b; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 400px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing:border-box; }
        
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h2>Sales Targets & Commission</h2>
                <p style="color:#777; margin:0;">Month: <strong><?php echo date('F Y', strtotime($current_month)); ?></strong></p>
            </div>
            <button onclick="openModal()" class="btn-add"><i class="fas fa-plus"></i> Set New Target</button>
        </div>

        <?php echo $msg; ?>

        <div class="grid-container">
            <?php if ($targets->num_rows > 0): ?>
                <?php while ($row = $targets->fetch_assoc()): 
                    $percent = ($row['target_amount'] > 0) ? ($row['achieved_amount'] / $row['target_amount']) * 100 : 0;
                    $percent_display = min(100, $percent); 
                    $est_comm = ($row['achieved_amount'] * $row['commission_rate']) / 100;
                ?>
                <div class="target-card">
                    <div class="card-actions">
                        <button onclick='openModal(<?php echo json_encode($row); ?>)' class="action-icon" title="Edit"><i class="fas fa-pen"></i></button>
                        <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this target?')" class="action-icon delete" title="Remove"><i class="fas fa-trash"></i></a>
                    </div>
                    
                    <div class="card-header">
                        <div>
                            <div class="rep-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                            <div class="rep-veh"><i class="fas fa-truck"></i> <?php echo htmlspecialchars($row['vehicle_no']); ?></div>
                            <div class="comm-badge"><?php echo $row['commission_rate']; ?>% Comm.</div>
                        </div>
                    </div>

                    <div class="stats-row">
                        <span>Achieved:</span>
                        <span class="money" style="color:<?php echo $percent >= 100 ? '#27ae60' : '#2c3e50'; ?>">Rs <?php echo number_format($row['achieved_amount']); ?></span>
                    </div>
                    <div class="stats-row">
                        <span>Target:</span>
                        <span class="money">Rs <?php echo number_format($row['target_amount']); ?></span>
                    </div>

                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo $percent_display; ?>%; background:<?php echo $percent >= 100 ? '#27ae60' : '#f39c12'; ?>"></div>
                    </div>

                    <div class="stats-row" style="margin-top:10px; border-top:1px solid #eee; padding-top:10px;">
                        <span style="color:#27ae60; font-weight:bold;">Est. Commission:</span>
                        <span style="color:#27ae60; font-weight:bold;">Rs <?php echo number_format($est_comm, 2); ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column:1/-1; text-align:center; padding:40px; color:#aaa; border: 2px dashed #ddd; border-radius:8px;">
                    <i class="fas fa-chart-line" style="font-size:40px; margin-bottom:10px;"></i><br>
                    No targets set for <?php echo date('F Y'); ?>. Click "Set New Target" to begin.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL -->
    <div id="targetModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0;" id="modalTitle">Set Sales Target</h3>
            <form method="POST">
                <input type="hidden" name="action" value="set_target">
                
                <div class="form-group">
                    <label>Select Month</label>
                    <input type="month" name="month" id="tMonth" value="<?php echo $current_month; ?>" required>
                </div>

                <div class="form-group">
                    <label>Select Sales Rep</label>
                    <select name="rep_id" id="tRep" required>
                        <option value="">-- Choose Rep --</option>
                        <?php 
                        if ($reps->num_rows > 0) {
                            $reps->data_seek(0);
                            while ($r = $reps->fetch_assoc()) {
                                echo "<option value='{$r['id']}'>{$r['full_name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Target Amount (Rs)</label>
                    <input type="number" name="amount" id="tAmount" required placeholder="e.g. 500000">
                </div>

                <div class="form-group">
                    <label>Commission Rate (%)</label>
                    <input type="number" name="commission" id="tComm" step="0.1" required placeholder="e.g. 2.5">
                </div>

                <button type="submit" class="btn-add" style="width:100%;" id="btnSave">Save Target</button>
                <button type="button" onclick="document.getElementById('targetModal').style.display='none'" style="width:100%; margin-top:5px; background:#bdc3c7; border:none; padding:10px; border-radius:5px; cursor:pointer;">Cancel</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const modal = document.getElementById('targetModal');
        
        function openModal(data = null) {
            modal.style.display = 'flex';
            if (data) {
                // Edit Mode
                document.getElementById('modalTitle').innerText = 'Edit Target';
                document.getElementById('tMonth').value = data.target_month;
                document.getElementById('tRep').value = data.rep_id;
                document.getElementById('tAmount').value = data.target_amount;
                document.getElementById('tComm').value = data.commission_rate;
                document.getElementById('btnSave').innerText = 'Update Target';
            } else {
                // New Mode
                document.getElementById('modalTitle').innerText = 'Set New Target';
                document.getElementById('tMonth').value = '<?php echo $current_month; ?>';
                document.getElementById('tRep').value = '';
                document.getElementById('tAmount').value = '';
                document.getElementById('tComm').value = '';
                document.getElementById('btnSave').innerText = 'Save Target';
            }
        }
        
        window.onclick = function(e) { if(e.target == modal) modal.style.display = 'none'; }
    </script>
</body>
</html>