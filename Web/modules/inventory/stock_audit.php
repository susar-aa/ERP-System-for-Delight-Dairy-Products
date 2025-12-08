<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";
$mode = 'list'; // 'list', 'new', 'entry'
$active_audit_id = 0;

// --- 1. HANDLE ACTIONS ---

// START NEW AUDIT
if (isset($_POST['action']) && $_POST['action'] == 'start_audit') {
    $notes = $_POST['notes'];
    $auditor = $_SESSION['user_id'];
    $date = date('Y-m-d');

    $conn->begin_transaction();
    try {
        // Create Header
        $stmt = $conn->prepare("INSERT INTO stock_audits (audit_date, auditor_id, notes, status) VALUES (?, ?, ?, 'draft')");
        $stmt->bind_param("sis", $date, $auditor, $notes);
        $stmt->execute();
        $audit_id = $stmt->insert_id;

        // Snapshot Current Stock for All Materials
        // We aggregate batch quantities to get total system stock per material
        $sql_snap = "
            INSERT INTO stock_audit_items (audit_id, material_id, system_qty, physical_qty, variance_qty)
            SELECT 
                $audit_id, 
                m.id, 
                COALESCE(SUM(b.quantity_current), 0), 
                0, 
                0
            FROM raw_materials m
            LEFT JOIN material_batches b ON m.id = b.material_id AND b.status = 'active'
            GROUP BY m.id
        ";
        $conn->query($sql_snap);

        $conn->commit();
        header("Location: stock_audit.php?id=$audit_id&mode=entry");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div class='alert error'>Error starting audit: " . $e->getMessage() . "</div>";
    }
}

// SAVE AUDIT COUNTS
if (isset($_POST['action']) && $_POST['action'] == 'save_counts') {
    $audit_id = intval($_POST['audit_id']);
    
    if (isset($_POST['items'])) {
        foreach ($_POST['items'] as $item_id => $phy_qty) {
            $phy_qty = floatval($phy_qty);
            // Calculate Variance immediately
            // We fetch system_qty first to ensure accuracy
            $curr = $conn->query("SELECT system_qty FROM stock_audit_items WHERE id=$item_id")->fetch_assoc();
            $sys = $curr['system_qty'];
            $var = $phy_qty - $sys;

            $upd = $conn->prepare("UPDATE stock_audit_items SET physical_qty=?, variance_qty=? WHERE id=?");
            $upd->bind_param("ddi", $phy_qty, $var, $item_id);
            $upd->execute();
        }
        $msg = "<div class='alert success'>Counts Saved Successfully!</div>";
    }
}

// FINALIZE AUDIT
if (isset($_POST['action']) && $_POST['action'] == 'finalize') {
    $audit_id = intval($_POST['audit_id']);
    $conn->query("UPDATE stock_audits SET status='finalized' WHERE id=$audit_id");
    $msg = "<div class='alert success'>Audit #$audit_id Finalized. Inventory Logs updated.</div>";
    // NOTE: In a full system, this would also trigger auto-adjustment transactions 
    // to correct the 'material_batches' table. For safety, we keep it as a report here.
}

// --- 2. DETERMINE VIEW MODE ---
if (isset($_GET['mode']) && $_GET['mode'] == 'new') {
    $mode = 'new';
} elseif (isset($_GET['id']) && isset($_GET['mode']) && $_GET['mode'] == 'entry') {
    $mode = 'entry';
    $active_audit_id = intval($_GET['id']);
    
    // Fetch Header
    $header = $conn->query("SELECT a.*, u.full_name FROM stock_audits a JOIN users u ON a.auditor_id = u.id WHERE a.id = $active_audit_id")->fetch_assoc();
    
    // Fetch Items
    $items = $conn->query("
        SELECT i.*, m.name, m.unit_measure 
        FROM stock_audit_items i 
        JOIN raw_materials m ON i.material_id = m.id 
        WHERE i.audit_id = $active_audit_id 
        ORDER BY m.name
    ");
} else {
    // List Mode
    $audits = $conn->query("SELECT a.*, u.full_name, (SELECT COUNT(*) FROM stock_audit_items WHERE audit_id=a.id) as item_count FROM stock_audits a JOIN users u ON a.auditor_id = u.id ORDER BY a.id DESC");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Audit | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-new { background: #2980b9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #34495e; color: white; padding: 12px; text-align: left; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .data-table tr:hover { background: #f9f9f9; }

        .audit-card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #f39c12; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .audit-info { display: flex; gap: 30px; margin-top: 10px; color: #555; }
        
        .input-qty { width: 100px; padding: 8px; border: 1px solid #3498db; background: #f0f8ff; border-radius: 4px; text-align: right; font-weight: bold; }
        .var-pos { color: #27ae60; font-weight: bold; } /* Gain */
        .var-neg { color: #c0392b; font-weight: bold; } /* Loss */
        .var-zero { color: #ccc; }

        .form-box { background: white; padding: 30px; max-width: 500px; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .status-draft { background: #f39c12; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; }
        .status-finalized { background: #27ae60; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <!-- MODE: NEW AUDIT FORM -->
        <?php if ($mode == 'new'): ?>
            <div class="top-bar">
                <h2>Start New Stock Take</h2>
                <a href="stock_audit.php" style="color:#777; text-decoration:none;">Cancel</a>
            </div>
            <div class="form-box">
                <form method="POST">
                    <input type="hidden" name="action" value="start_audit">
                    <div class="form-group">
                        <label>Audit Notes / Reference</label>
                        <textarea name="notes" rows="3" placeholder="e.g. Q3 End of Month Count" required></textarea>
                    </div>
                    <div class="alert" style="background:#e3f2fd; color:#0d47a1; border-color:#90caf9;">
                        <i class="fas fa-info-circle"></i> This will capture the current "System Stock" for all raw materials as a snapshot.
                    </div>
                    <button type="submit" class="btn-new" style="width:100%; border:none; cursor:pointer;">Create & Start Counting</button>
                </form>
            </div>

        <!-- MODE: DATA ENTRY -->
        <?php elseif ($mode == 'entry'): ?>
            <div class="top-bar">
                <h2>Audit Entry: #<?php echo $active_audit_id; ?></h2>
                <a href="stock_audit.php" style="color:#3498db;">&larr; Back to List</a>
            </div>
            
            <?php echo $msg; ?>

            <div class="audit-card">
                <h3 style="margin:0;"><?php echo htmlspecialchars($header['notes']); ?></h3>
                <div class="audit-info">
                    <span><i class="fas fa-calendar"></i> <?php echo $header['audit_date']; ?></span>
                    <span><i class="fas fa-user"></i> <?php echo $header['full_name']; ?></span>
                    <span>Status: <span class="status-<?php echo $header['status']; ?>"><?php echo strtoupper($header['status']); ?></span></span>
                </div>
            </div>

            <form method="POST" id="auditForm">
                <input type="hidden" name="action" value="save_counts">
                <input type="hidden" name="audit_id" value="<?php echo $active_audit_id; ?>">

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th style="width:150px; text-align:right;">System Qty</th>
                            <th style="width:150px; text-align:right;">Physical Count</th>
                            <th style="width:150px; text-align:right;">Variance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $is_final = ($header['status'] == 'finalized');
                        while($row = $items->fetch_assoc()): 
                            $var_class = 'var-zero';
                            if($row['variance_qty'] > 0) $var_class = 'var-pos';
                            if($row['variance_qty'] < 0) $var_class = 'var-neg';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                <span style="font-size:12px; color:#999;"><?php echo $row['unit_measure']; ?></span>
                            </td>
                            <td style="text-align:right; color:#777;"><?php echo $row['system_qty']; ?></td>
                            <td style="text-align:right;">
                                <?php if($is_final): ?>
                                    <?php echo $row['physical_qty']; ?>
                                <?php else: ?>
                                    <input type="number" step="0.01" name="items[<?php echo $row['id']; ?>]" 
                                           value="<?php echo $row['physical_qty']; ?>" class="input-qty">
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;" class="<?php echo $var_class; ?>">
                                <?php echo ($row['variance_qty'] > 0 ? '+' : '') . $row['variance_qty']; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php if(!$is_final): ?>
                <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                    <button type="submit" class="btn-new" style="background:#2ecc71; border:none; cursor:pointer;">Save Progress</button>
                    <!-- Separate Finalize Button -->
                    <button type="button" onclick="confirmFinalize()" class="btn-new" style="background:#2c3e50; border:none; cursor:pointer;">Finalize Audit</button>
                </div>
                <?php endif; ?>
            </form>
            
            <!-- Hidden Form for Finalize Action -->
            <form method="POST" id="finalizeForm">
                <input type="hidden" name="action" value="finalize">
                <input type="hidden" name="audit_id" value="<?php echo $active_audit_id; ?>">
            </form>

        <!-- MODE: LIST ALL -->
        <?php else: ?>
            <div class="top-bar">
                <h2>Stock Audits</h2>
                <a href="stock_audit.php?mode=new" class="btn-new"><i class="fas fa-plus"></i> Start New Audit</a>
            </div>
            
            <?php echo $msg; ?>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Auditor</th>
                        <th>Reference / Notes</th>
                        <th>Items Counted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($audits->num_rows > 0): ?>
                        <?php while($row = $audits->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['audit_date']; ?></td>
                            <td><?php echo $row['full_name']; ?></td>
                            <td><?php echo htmlspecialchars($row['notes']); ?></td>
                            <td><?php echo $row['item_count']; ?></td>
                            <td><span class="status-<?php echo $row['status']; ?>"><?php echo strtoupper($row['status']); ?></span></td>
                            <td>
                                <a href="stock_audit.php?mode=entry&id=<?php echo $row['id']; ?>" style="color:#3498db; text-decoration:none; font-weight:bold;">
                                    <?php echo ($row['status']=='draft') ? 'Continue' : 'View Report'; ?>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:20px;">No audits found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function confirmFinalize() {
            if(confirm("Are you sure you want to FINALIZE this audit?\n\nThis will lock the records and cannot be edited. Ensure all physical counts are entered correctly.")) {
                document.getElementById('finalizeForm').submit();
            }
        }
    </script>
</body>
</html>