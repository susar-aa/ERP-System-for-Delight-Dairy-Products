<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- HANDLE ACTIONS ---

// 1. ADD / UPDATE ROUTE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['route_name']);
    $desc = trim($_POST['area_description']);
    
    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO routes (route_name, area_description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $desc);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Route Created Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    } 
    elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['route_id']);
        $stmt = $conn->prepare("UPDATE routes SET route_name=?, area_description=? WHERE id=?");
        $stmt->bind_param("ssi", $name, $desc, $id);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Route Updated Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    }
}

// 2. DELETE ROUTE
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    
    // Check Dependencies
    $shop_count = $conn->query("SELECT COUNT(*) as c FROM customers WHERE route_id = $del_id")->fetch_assoc()['c'];
    $rep_count = $conn->query("SELECT COUNT(*) as c FROM sales_reps WHERE assigned_route_id = $del_id")->fetch_assoc()['c'];
    
    if ($shop_count > 0 || $rep_count > 0) {
        $msg = "<div class='alert error'>
                    <strong>Cannot Delete:</strong> This route is currently assigned to:
                    <ul>
                        <li>$shop_count Shops</li>
                        <li>$rep_count Sales Reps</li>
                    </ul>
                    Please reassign them first.
                </div>";
    } else {
        $conn->query("DELETE FROM routes WHERE id = $del_id");
        $msg = "<div class='alert success'>Route Deleted Successfully.</div>";
    }
}

// --- FETCH DATA ---
$sql = "
    SELECT r.*,
    (SELECT COUNT(*) FROM customers WHERE route_id = r.id) as total_shops,
    (SELECT COUNT(*) FROM sales_reps WHERE assigned_route_id = r.id) as total_reps
    FROM routes r
    ORDER BY r.route_name ASC
";
$routes = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Routes | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .data-table th { background: #34495e; color: white; padding: 12px 15px; text-align: left; font-size: 13px; text-transform: uppercase; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 14px; }
        .data-table tr:hover { background: #f9f9f9; }

        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add-new { background: #2980b9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; font-size: 14px; font-weight: bold; }
        .btn-add-new:hover { background: #2471a3; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .action-btn { border: none; background: none; cursor: pointer; font-size: 14px; margin-right: 8px; }
        
        .stat-badge { background: #ecf0f1; padding: 4px 8px; border-radius: 4px; font-size: 12px; color: #555; display: inline-block; margin-right: 5px; }
        .stat-badge i { color: #7f8c8d; margin-right: 4px; }
        .badge-active { background: #d4edda; color: #155724; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 450px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; animation: slideIn 0.3s ease; }
        .modal-header { font-weight: bold; font-size: 18px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #777; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; color: #555; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-save { width: 100%; background: #27ae60; color: white; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-top: 10px; font-weight: bold; }

        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="top-bar">
            <div>
                <h2>Route Management</h2>
                <p style="color:#777; font-size:14px; margin:0;">Define distribution territories.</p>
            </div>
            
            <div style="display:flex; gap:10px;">
                <a href="manage_customers.php" class="btn-add-new" style="background:#16a085;">
                    <i class="fas fa-store"></i> Customers
                </a>
                <a href="manage_reps.php" class="btn-add-new" style="background:#8e44ad;">
                    <i class="fas fa-user-tie"></i> Sales Reps
                </a>
                <button onclick="openModal('add')" class="btn-add-new">
                    <i class="fas fa-plus"></i> Add Route
                </button>
            </div>
        </div>

        <?php echo $msg; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Route Name</th>
                    <th>Area Description</th>
                    <th>Assigned Stats</th>
                    <th style="width: 120px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($routes->num_rows > 0): ?>
                    <?php while($row = $routes->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['route_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['area_description']); ?></td>
                        <td>
                            <span class="stat-badge <?php echo $row['total_shops'] > 0 ? 'badge-active' : ''; ?>">
                                <i class="fas fa-store"></i> <?php echo $row['total_shops']; ?> Shops
                            </span>
                            <span class="stat-badge">
                                <i class="fas fa-user-tie"></i> <?php echo $row['total_reps']; ?> Reps
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button class="action-btn" style="color:#f39c12;" onclick='openModal("edit", <?php echo json_encode($row); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete_id=<?php echo $row['id']; ?>" class="action-btn" style="color:#c0392b;" onclick="return confirm('Delete this route?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px; color:#aaa;">No routes defined.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="routeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Route</span>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="route_id" id="routeId">

                <div class="form-group">
                    <label>Route Name</label>
                    <input type="text" name="route_name" id="routeName" required placeholder="e.g. Route A - Colombo North">
                </div>

                <div class="form-group">
                    <label>Area Description</label>
                    <textarea name="area_description" id="routeDesc" rows="3" placeholder="Major towns/streets..."></textarea>
                </div>

                <button type="submit" class="btn-save" id="btnSave">Save Route</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const modal = document.getElementById('routeModal');
        
        function openModal(mode, data = null) {
            modal.style.display = 'flex';
            if (mode === 'edit') {
                document.getElementById('modalTitle').innerText = 'Edit Route';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('btnSave').innerText = 'Update Route';
                document.getElementById('routeId').value = data.id;
                document.getElementById('routeName').value = data.route_name;
                document.getElementById('routeDesc').value = data.area_description;
            } else {
                document.getElementById('modalTitle').innerText = 'Add Route';
                document.getElementById('formAction').value = 'add';
                document.getElementById('btnSave').innerText = 'Save Route';
                document.getElementById('routeId').value = '';
                document.getElementById('routeName').value = '';
                document.getElementById('routeDesc').value = '';
            }
        }

        function closeModal() { modal.style.display = 'none'; }
        window.onclick = function(e) { if (e.target == modal) closeModal(); }
    </script>
</body>
</html>