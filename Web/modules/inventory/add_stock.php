<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_grn') {
    $material_id = intval($_POST['material_id']);
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
    $batch_code = trim($_POST['batch_code']);
    $qty = floatval($_POST['quantity']);
    $cost = floatval($_POST['cost']);
    
    // Handle Optional Dates
    $mfd = !empty($_POST['mfd_date']) ? $_POST['mfd_date'] : null;
    $exp = !empty($_POST['exp_date']) ? $_POST['exp_date'] : null;
    
    if(empty($batch_code)) {
        $batch_code = "GRN-" . date("ymd") . "-" . rand(100, 999);
    }

    // Insert with Supplier ID
    $stmt = $conn->prepare("INSERT INTO material_batches (material_id, supplier_id, batch_code, quantity_initial, quantity_current, mfd_date, exp_date, cost_per_unit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("iisddssd", $material_id, $supplier_id, $batch_code, $qty, $qty, $mfd, $exp, $cost);

    if ($stmt->execute()) {
        $msg = "<div class='alert success'>Stock Added Successfully! Batch: <strong>$batch_code</strong></div>";
        
        // OPTIONAL: Create Log Entry (If Log Table Exists from Step 1)
        // $log_sql = "INSERT INTO inventory_logs (material_id, batch_id, transaction_type, qty_change, reference_info) VALUES ($material_id, {$stmt->insert_id}, 'GRN', $qty, '$batch_code')";
        // $conn->query($log_sql);
    } else {
        $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
    }
}

// --- FETCH DATA ---
$materials = $conn->query("SELECT * FROM raw_materials ORDER BY name");
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY company_name");

// Recent History with Supplier Name
$history = $conn->query("
    SELECT b.*, m.name as material_name, m.unit_measure, s.company_name 
    FROM material_batches b 
    JOIN raw_materials m ON b.material_id = m.id 
    LEFT JOIN suppliers s ON b.supplier_id = s.id
    ORDER BY b.received_date DESC 
    LIMIT 20
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Stock (GRN) | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Styles from previous version preserved */
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .data-table th { background: #34495e; color: white; padding: 12px 15px; text-align: left; font-size: 13px; text-transform: uppercase; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 14px; }
        .data-table tr:hover { background: #f9f9f9; }

        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add-new { background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; font-size: 14px; font-weight: bold; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-expired { background: #fadbd8; color: #721c24; }
        .badge-exhausted { background: #e2e3e5; color: #383d41; }
        .badge-stable { background: #d1ecf1; color: #0c5460; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 500px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; animation: slideDown 0.3s ease; }
        .modal-header { font-weight: bold; font-size: 18px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #777; }
        
        .form-row { display: flex; gap: 15px; }
        .form-group { margin-bottom: 15px; flex: 1; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .btn-save { width: 100%; background: #2980b9; color: white; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-top: 10px; font-weight: bold; }
        
        .checkbox-wrapper { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; background: #f8f9fa; padding: 10px; border-radius: 4px; border: 1px solid #eee; }
        .checkbox-wrapper label { margin: 0; cursor: pointer; }

        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h2>Goods Received Notes (GRN)</h2>
                <p style="color:#777; font-size:14px; margin:0;">Record new stock arrivals.</p>
            </div>
            <button onclick="openModal()" class="btn-add-new">
                <i class="fas fa-plus-circle"></i> Add New Stock
            </button>
        </div>

        <?php echo $msg; ?>

        <div style="margin-bottom: 10px; font-weight: bold; color: #555;">Recent Arrivals</div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Batch Code</th>
                    <th>Supplier</th>
                    <th>Material</th>
                    <th>Initial Qty</th>
                    <th>Current</th>
                    <th>Expiry</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if($history->num_rows > 0): ?>
                    <?php while($row = $history->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('Y-m-d', strtotime($row['received_date'])); ?></td>
                        <td style="font-weight:bold; color:#2980b9;"><?php echo htmlspecialchars($row['batch_code']); ?></td>
                        <td><?php echo $row['company_name'] ? htmlspecialchars($row['company_name']) : '<span style="color:#aaa;">-</span>'; ?></td>
                        <td><?php echo htmlspecialchars($row['material_name']); ?></td>
                        <td><?php echo $row['quantity_initial'] . ' ' . $row['unit_measure']; ?></td>
                        <td><strong><?php echo $row['quantity_current']; ?></strong></td>
                        <td>
                            <?php 
                                if($row['exp_date']) {
                                    $style = ($row['exp_date'] < date('Y-m-d')) ? 'color:red; font-weight:bold;' : '';
                                    echo "<span style='$style'>{$row['exp_date']}</span>";
                                } else {
                                    echo "<span style='color:#7f8c8d;'>N/A</span>";
                                }
                            ?>
                        </td>
                        <td>
                            <?php 
                                $badge = 'badge-active';
                                if($row['status'] == 'expired') $badge = 'badge-expired';
                                if(!$row['exp_date']) $badge = 'badge-stable';
                                if($row['quantity_current'] <= 0) { $row['status'] = 'Exhausted'; $badge = 'badge-exhausted'; }
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo ucfirst($row['status']); ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center; padding:20px;">No stock history found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ADD GRN MODAL -->
    <div id="grnModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span>Receive New Stock (GRN)</span>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_grn">

                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplier_id">
                        <option value="">-- Unknown / Cash --</option>
                        <?php 
                        if ($suppliers->num_rows > 0) {
                            $suppliers->data_seek(0);
                            while($s = $suppliers->fetch_assoc()) {
                                echo "<option value='{$s['id']}'>{$s['company_name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Raw Material</label>
                    <select name="material_id" required>
                        <option value="">-- Choose Material --</option>
                        <?php while($m = $materials->fetch_assoc()): ?>
                            <option value="<?php echo $m['id']; ?>">
                                <?php echo htmlspecialchars($m['name']); ?> (<?php echo $m['unit_measure']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Batch Code</label>
                        <input type="text" name="batch_code" placeholder="Auto-generated if empty">
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" step="0.01" name="quantity" required placeholder="0.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Unit Cost (Rs)</label>
                        <input type="number" step="0.01" name="cost" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Total Value</label>
                        <input type="text" id="totalVal" readonly style="background:#f9f9f9; color:#777;" placeholder="0.00">
                    </div>
                </div>

                <div class="checkbox-wrapper">
                    <input type="checkbox" id="noExpiryCheck" onchange="toggleDates()">
                    <label for="noExpiryCheck">Non-expiring Item</label>
                </div>

                <div class="form-row" id="dateSection">
                    <div class="form-group">
                        <label>MFD Date</label>
                        <input type="date" name="mfd_date" id="mfdDate" required max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" name="exp_date" id="expDate" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <button type="submit" class="btn-save">Confirm & Add</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const modal = document.getElementById('grnModal');
        const qtyInput = document.querySelector('input[name="quantity"]');
        const costInput = document.querySelector('input[name="cost"]');
        const totalDisplay = document.getElementById('totalVal');

        function openModal() { modal.style.display = 'flex'; }
        function closeModal() { modal.style.display = 'none'; }

        function toggleDates() {
            const isNoExpiry = document.getElementById('noExpiryCheck').checked;
            const mfd = document.getElementById('mfdDate');
            const exp = document.getElementById('expDate');
            const section = document.getElementById('dateSection');

            if (isNoExpiry) {
                mfd.disabled = true; exp.disabled = true;
                mfd.value = ''; exp.value = '';
                mfd.required = false; exp.required = false;
                section.style.opacity = '0.5';
            } else {
                mfd.disabled = false; exp.disabled = false;
                mfd.required = true; exp.required = true;
                section.style.opacity = '1';
            }
        }

        function calcTotal() {
            const q = parseFloat(qtyInput.value) || 0;
            const c = parseFloat(costInput.value) || 0;
            totalDisplay.value = (q * c).toFixed(2);
        }

        qtyInput.addEventListener('input', calcTotal);
        costInput.addEventListener('input', calcTotal);
        window.onclick = function(event) { if (event.target == modal) closeModal(); }
    </script>
</body>
</html>