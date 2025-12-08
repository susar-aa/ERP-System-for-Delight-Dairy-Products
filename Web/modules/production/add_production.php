<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";
$mode = 'plan'; // 'plan' or 'confirm'
$requirements = [];
$can_produce = true;

// --- HANDLE ACTIONS ---

// 1. CALCULATE REQUIREMENTS (Step 1)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'calculate') {
    $prod_id = intval($_POST['product_id']);
    $qty_to_make = floatval($_POST['quantity']);
    $mfd = $_POST['mfd_date'];
    $exp = $_POST['exp_date'];
    
    // Fetch Product Info
    $prod_info = $conn->query("SELECT * FROM products WHERE id = $prod_id")->fetch_assoc();
    
    // Fetch Recipe (BOM)
    $recipe_q = $conn->query("
        SELECT r.*, m.name, m.unit_measure,
        (SELECT SUM(quantity_current) FROM material_batches WHERE material_id = m.id AND status='active') as current_stock
        FROM product_recipes r 
        JOIN raw_materials m ON r.raw_material_id = m.id 
        WHERE r.product_id = $prod_id
    ");

    if ($recipe_q->num_rows == 0) {
        $msg = "<div class='alert error'>Error: No recipe defined for this product. <a href='manage_recipes.php?product_id=$prod_id'>Define Recipe</a></div>";
    } else {
        $mode = 'confirm';
        while($row = $recipe_q->fetch_assoc()) {
            $needed = $row['quantity_needed'] * $qty_to_make;
            $stock = $row['current_stock'] ?? 0;
            
            $row['total_needed'] = $needed;
            $row['sufficient'] = ($stock >= $needed);
            
            if (!$row['sufficient']) $can_produce = false;
            
            $requirements[] = $row;
        }
    }
}

// 2. CONFIRM PRODUCTION (Step 2)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'confirm') {
    $prod_id = intval($_POST['product_id']);
    $qty_to_make = floatval($_POST['quantity']);
    $mfd = $_POST['mfd_date'];
    $exp = $_POST['exp_date'];
    $batch_code = "PROD-" . date('ymd') . "-" . rand(100, 999);
    $user_id = $_SESSION['user_id'];

    // Fetch Product Name for Log
    $p_name = $conn->query("SELECT product_name FROM products WHERE id=$prod_id")->fetch_assoc()['product_name'];

    $conn->begin_transaction();
    try {
        // Double Check Stock & Deduct (FIFO)
        $recipe_q = $conn->query("SELECT * FROM product_recipes WHERE product_id = $prod_id");
        
        while($ing = $recipe_q->fetch_assoc()) {
            $mat_id = $ing['raw_material_id'];
            $needed = $ing['quantity_needed'] * $qty_to_make;
            
            // Get Batches (FIFO: Oldest Expiry First)
            $batches = $conn->query("SELECT id, quantity_current, batch_code FROM material_batches WHERE material_id = $mat_id AND status='active' AND quantity_current > 0 ORDER BY exp_date ASC");
            
            $rem_needed = $needed;
            
            while($batch = $batches->fetch_assoc()) {
                if($rem_needed <= 0) break;
                
                $take = min($rem_needed, $batch['quantity_current']);
                $new_qty = $batch['quantity_current'] - $take;
                $status = ($new_qty <= 0) ? 'exhausted' : 'active';
                
                // 1. Update Batch Stock
                $conn->query("UPDATE material_batches SET quantity_current = $new_qty, status = '$status' WHERE id = {$batch['id']}");
                
                // 2. LOG THE DEDUCTION (Bin Card Update)
                // Calculate total balance for this material after deduction
                $bal_q = $conn->query("SELECT SUM(quantity_current) as total FROM material_batches WHERE material_id = $mat_id");
                $new_bal = $bal_q->fetch_assoc()['total'] ?? 0;
                
                $log_ref = "Used for $p_name ($batch_code)";
                $neg_qty = -1 * $take;
                
                $stmt_log = $conn->prepare("INSERT INTO inventory_logs (log_date, material_id, batch_id, transaction_type, qty_change, balance_after, reference_info, user_id) VALUES (NOW(), ?, ?, 'PRODUCTION', ?, ?, ?, ?)");
                $stmt_log->bind_param("iiddds", $mat_id, $batch['id'], $neg_qty, $new_bal, $log_ref, $user_id);
                $stmt_log->execute();
                
                $rem_needed -= $take;
            }
            
            if($rem_needed > 0.001) {
                throw new Exception("Stock changed during transaction. Insufficient material ID: $mat_id");
            }
        }

        // Add Finished Goods to Stock
        // 1. Record Run
        $stmt = $conn->prepare("INSERT INTO production_runs (product_id, batch_code, quantity_produced, mfd_date, exp_date, status) VALUES (?, ?, ?, ?, ?, 'completed')");
        $stmt->bind_param("isiss", $prod_id, $batch_code, $qty_to_make, $mfd, $exp);
        $stmt->execute();
        $run_id = $stmt->insert_id;

        // 2. Add to Inventory
        $stmt_stock = $conn->prepare("INSERT INTO product_stock (production_run_id, product_id, quantity_available, exp_date) VALUES (?, ?, ?, ?)");
        $stmt_stock->bind_param("iiis", $run_id, $prod_id, $qty_to_make, $exp);
        $stmt_stock->execute();

        $conn->commit();
        $msg = "<div class='alert success'>Production Successful! Generated Batch: <strong>$batch_code</strong></div>";
        $mode = 'plan'; // Reset

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div class='alert error'>Production Failed: " . $e->getMessage() . "</div>";
        $mode = 'plan';
    }
}

// --- FETCH DATA ---
$products = $conn->query("SELECT * FROM products ORDER BY product_name");

// Recent History
$history = $conn->query("
    SELECT r.*, p.product_name 
    FROM production_runs r 
    JOIN products p ON r.product_id = p.id 
    ORDER BY r.created_at DESC LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Run Production | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-container { display: flex; gap: 20px; }
        .form-panel { flex: 1; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .history-panel { width: 350px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .req-table { width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #eee; }
        .req-table th { background: #f8f9fa; padding: 10px; text-align: left; font-size: 13px; border-bottom: 1px solid #ddd; }
        .req-table td { padding: 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        
        .status-ok { color: #27ae60; font-weight: bold; }
        .status-low { color: #c0392b; font-weight: bold; }
        
        .btn-action { width: 100%; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .btn-calc { background: #3498db; color: white; }
        .btn-confirm { background: #27ae60; color: white; }
        .btn-cancel { background: #95a5a6; color: white; margin-top: 10px; }

        .hist-item { padding: 15px 0; border-bottom: 1px solid #eee; }
        .hist-item:last-child { border-bottom: none; }
        .hist-title { font-weight: bold; color: #2c3e50; display: flex; justify-content: space-between; }
        .hist-meta { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div style="margin-bottom: 20px;">
            <h2 style="margin:0;">Production Run</h2>
            <p style="color:#777; margin:5px 0 0 0;">Convert raw materials into finished goods.</p>
        </div>

        <?php echo $msg; ?>

        <div class="page-container">
            
            <!-- LEFT: PRODUCTION FORM -->
            <div class="form-panel">
                <form method="POST">
                    
                    <?php if ($mode == 'plan'): ?>
                        <input type="hidden" name="action" value="calculate">
                        
                        <div class="form-group">
                            <label>Select Finished Product</label>
                            <select name="product_id" required>
                                <option value="">-- Choose Product --</option>
                                <?php 
                                $products->data_seek(0);
                                while($p = $products->fetch_assoc()): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Quantity to Produce (Units)</label>
                            <input type="number" name="quantity" required placeholder="e.g. 500" min="1">
                        </div>

                        <div style="display:flex; gap:20px;">
                            <div class="form-group" style="flex:1;">
                                <label>Manufacture Date</label>
                                <input type="date" name="mfd_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>Expiry Date</label>
                                <input type="date" name="exp_date" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-action btn-calc">Calculate Requirements <i class="fas fa-calculator"></i></button>

                    <?php elseif ($mode == 'confirm'): ?>
                        <input type="hidden" name="action" value="confirm">
                        <input type="hidden" name="product_id" value="<?php echo $_POST['product_id']; ?>">
                        <input type="hidden" name="quantity" value="<?php echo $_POST['quantity']; ?>">
                        <input type="hidden" name="mfd_date" value="<?php echo $_POST['mfd_date']; ?>">
                        <input type="hidden" name="exp_date" value="<?php echo $_POST['exp_date']; ?>">

                        <div style="background:#f8f9fa; padding:15px; border-radius:5px; margin-bottom:20px;">
                            <h3 style="margin-top:0; color:#2c3e50;">Production Summary</h3>
                            <p><strong>Product:</strong> <?php echo $prod_info['product_name']; ?></p>
                            <p><strong>Target Quantity:</strong> <?php echo $_POST['quantity']; ?> Units</p>
                        </div>

                        <h4 style="margin-bottom:10px;">Material Requirements</h4>
                        <table class="req-table">
                            <thead>
                                <tr>
                                    <th>Ingredient</th>
                                    <th>Required</th>
                                    <th>Available Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($requirements as $req): ?>
                                <tr>
                                    <td><?php echo $req['name']; ?></td>
                                    <td><?php echo number_format($req['total_needed'], 2) . " " . $req['unit_measure']; ?></td>
                                    <td><?php echo number_format($req['current_stock'], 2) . " " . $req['unit_measure']; ?></td>
                                    <td>
                                        <?php if($req['sufficient']): ?>
                                            <span class="status-ok"><i class="fas fa-check"></i> OK</span>
                                        <?php else: ?>
                                            <span class="status-low"><i class="fas fa-times"></i> Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <?php if($can_produce): ?>
                            <button type="submit" class="btn-action btn-confirm" onclick="return confirm('Confirm production? Stock will be deducted immediately.')">
                                Confirm & Produce <i class="fas fa-check-circle"></i>
                            </button>
                        <?php else: ?>
                            <div class="alert error" style="margin-top:20px;">
                                <i class="fas fa-exclamation-triangle"></i> Cannot proceed. Insufficient raw materials.
                            </div>
                        <?php endif; ?>
                        
                        <a href="add_production.php" class="btn-action btn-cancel" style="display:block; text-align:center; text-decoration:none;">Cancel</a>

                    <?php endif; ?>

                </form>
            </div>

            <!-- RIGHT: HISTORY -->
            <div class="history-panel">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Recent Runs</h3>
                
                <?php if($history->num_rows > 0): ?>
                    <?php while($h = $history->fetch_assoc()): ?>
                    <div class="hist-item">
                        <div class="hist-title">
                            <span><?php echo htmlspecialchars($h['product_name']); ?></span>
                            <span style="color:#27ae60;"><?php echo $h['quantity_produced']; ?> Units</span>
                        </div>
                        <div class="hist-meta">
                            Batch: <?php echo $h['batch_code']; ?> <br>
                            Date: <?php echo date('M d, Y', strtotime($h['mfd_date'])); ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color:#aaa; text-align:center; margin-top:20px;">No production history yet.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>