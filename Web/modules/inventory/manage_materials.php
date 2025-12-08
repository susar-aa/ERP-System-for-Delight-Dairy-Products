<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- 1. HANDLE ACTIONS ---

// DELETE
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    // Check if used in batches first to prevent orphans
    $check = $conn->query("SELECT COUNT(*) as c FROM material_batches WHERE material_id = $id");
    if($check->fetch_assoc()['c'] > 0) {
        $msg = "<div class='alert error'>Cannot delete: Material is used in existing stock batches.</div>";
    } else {
        $conn->query("DELETE FROM raw_materials WHERE id = $id");
        $msg = "<div class='alert success'>Material Deleted Successfully!</div>";
    }
}

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $name = $_POST['name'];
    $unit = $_POST['unit'];
    $reorder = $_POST['reorder_level'];
    
    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO raw_materials (name, unit_measure, reorder_level) VALUES (?, ?, ?)");
        $stmt->bind_param("ssd", $name, $unit, $reorder);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Material Added Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    } 
    elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['material_id']);
        $stmt = $conn->prepare("UPDATE raw_materials SET name=?, unit_measure=?, reorder_level=? WHERE id=?");
        $stmt->bind_param("ssdi", $name, $unit, $reorder, $id);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Material Updated Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    }
}

// Fetch existing materials
$materials = $conn->query("SELECT * FROM raw_materials ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Materials | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Table Styles */
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .data-table th { background: #34495e; color: white; padding: 12px 15px; text-align: left; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover { background: #f9f9f9; }

        /* Action Buttons */
        .action-btn { padding: 5px 10px; border-radius: 4px; color: white; text-decoration: none; font-size: 12px; margin-right: 5px; cursor: pointer; border: none; }
        .btn-edit { background: #f39c12; }
        .btn-delete { background: #c0392b; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; animation: slideDown 0.3s ease; }
        .modal-header { font-weight: bold; font-size: 18px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #777; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .btn-save { width: 100%; background: #27ae60; color: white; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-top: 10px; }
        .btn-save:hover { background: #219150; }

        /* Top Bar */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add-new { background: #2980b9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; font-size: 14px; }
        
        /* Alerts */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="top-bar">
            <div>
                <h2>Raw Material Definitions</h2>
                <p style="color:#777; font-size:14px; margin:0;">Define ingredients used in production (e.g., Milk, Sugar).</p>
            </div>
            <button onclick="openModal('add')" class="btn-add-new">
                <i class="fas fa-plus"></i> Add New Material
            </button>
        </div>

        <?php echo $msg; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Material Name</th>
                    <th>Unit</th>
                    <th>Reorder Level</th>
                    <th style="width: 150px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($materials->num_rows > 0): ?>
                    <?php while($row = $materials->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                        <td><?php echo $row['unit_measure']; ?></td>
                        <td><span style="background:#eee; padding:2px 6px; border-radius:3px;"><?php echo $row['reorder_level']; ?></span></td>
                        <td style="text-align:center;">
                            <button class="action-btn btn-edit" 
                                onclick='openModal("edit", <?php echo json_encode($row); ?>)'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="action-btn btn-delete" 
                                onclick="confirmDelete(<?php echo $row['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">No materials defined yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL POPUP -->
    <div id="materialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Material</span>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="material_id" id="materialId">

                <div class="form-group">
                    <label>Material Name</label>
                    <input type="text" name="name" id="matName" required placeholder="e.g. Raw Milk">
                </div>

                <div class="form-group">
                    <label>Unit of Measure</label>
                    <select name="unit" id="matUnit">
                        <option value="Liters">Liters</option>
                        <option value="Kg">Kg</option>
                        <option value="Pcs">Pieces</option>
                        <option value="Meters">Meters</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Reorder Level (Low Stock Alert)</label>
                    <input type="number" step="0.01" name="reorder_level" id="matReorder" required placeholder="10.00">
                </div>

                <button type="submit" class="btn-save" id="btnSave">Save Material</button>
            </form>
        </div>
    </div>

    <!-- Include Dashboard JS for Sidebar Toggle -->
    <script src="../../assets/js/dashboard.js"></script>

    <script>
        const modal = document.getElementById('materialModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const materialId = document.getElementById('materialId');
        const matName = document.getElementById('matName');
        const matUnit = document.getElementById('matUnit');
        const matReorder = document.getElementById('matReorder');
        const btnSave = document.getElementById('btnSave');

        function openModal(mode, data = null) {
            modal.style.display = 'flex';
            if (mode === 'edit' && data) {
                modalTitle.innerText = 'Edit Material';
                formAction.value = 'edit';
                btnSave.innerText = 'Update Material';
                
                // Populate fields
                materialId.value = data.id;
                matName.value = data.name;
                matUnit.value = data.unit_measure;
                matReorder.value = data.reorder_level;
            } else {
                modalTitle.innerText = 'Add New Material';
                formAction.value = 'add';
                btnSave.innerText = 'Save Material';
                
                // Clear fields
                materialId.value = '';
                matName.value = '';
                matUnit.value = 'Liters'; // Default
                matReorder.value = '';
            }
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function confirmDelete(id) {
            if(confirm("Are you sure you want to delete this material? This might affect stock records.")) {
                window.location.href = `manage_materials.php?delete_id=${id}`;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>