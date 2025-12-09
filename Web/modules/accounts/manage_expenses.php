<?php
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../index.php"); exit; }

$msg = "";

// --- HANDLE ACTIONS ---

// DELETE
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM transactions WHERE id=$id AND type='expense'");
    $msg = "<div class='alert success'>Expense Record Deleted.</div>";
}

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $date = $_POST['date'];
    $category = $_POST['category'];
    $amount = floatval($_POST['amount']);
    $desc = $_POST['description'];
    
    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO transactions (trans_date, type, category, amount, description) VALUES (?, 'expense', ?, ?, ?)");
        $stmt->bind_param("ssds", $date, $category, $amount, $desc);
        if ($stmt->execute()) $msg = "<div class='alert success'>Expense Recorded Successfully!</div>";
        else $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
    } 
    elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['trans_id']);
        $stmt = $conn->prepare("UPDATE transactions SET trans_date=?, category=?, amount=?, description=? WHERE id=?");
        $stmt->bind_param("ssdsi", $date, $category, $amount, $desc, $id);
        if ($stmt->execute()) $msg = "<div class='alert success'>Expense Updated!</div>";
    }
}

// FETCH DATA
$expenses = $conn->query("SELECT * FROM transactions WHERE type='expense' ORDER BY trans_date DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Expenses | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add { background: #c0392b; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; font-weight: bold; }
        
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #34495e; color: white; padding: 12px; text-align: left; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .data-table tr:hover { background: #f9f9f9; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 450px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h2>Expense Management</h2>
                <p style="color:#777; margin:0;">Record operational costs (Fuel, Electricity, Repairs).</p>
            </div>
            <button onclick="openModal('add')" class="btn-add"><i class="fas fa-minus-circle"></i> Add Expense</button>
        </div>

        <?php echo $msg; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th style="text-align:right;">Amount (Rs)</th>
                    <th style="text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $expenses->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['trans_date']; ?></td>
                    <td><span style="background:#fdedec; color:#c0392b; padding:3px 8px; border-radius:4px; font-size:12px;"><?php echo $row['category']; ?></span></td>
                    <td><?php echo htmlspecialchars($row['description']); ?></td>
                    <td style="text-align:right; font-weight:bold;"><?php echo number_format($row['amount'], 2); ?></td>
                    <td style="text-align:center;">
                        <button onclick='openModal("edit", <?php echo json_encode($row); ?>)' style="border:none; background:none; cursor:pointer; color:#f39c12;"><i class="fas fa-edit"></i></button>
                        <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this record?')" style="color:#c0392b; margin-left:10px;"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="expModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle" style="margin-top:0;">Record Expense</h3>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="trans_id" id="transId">

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" id="date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="category">
                        <option value="Utilities">Utilities (Electricity/Water)</option>
                        <option value="Transport">Transport / Fuel</option>
                        <option value="Maintenance">Maintenance & Repairs</option>
                        <option value="Rent">Rent</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Petty Cash">Petty Cash / Sundry</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (Rs)</label>
                    <input type="number" step="0.01" name="amount" id="amount" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description" rows="2" placeholder="Details..."></textarea>
                </div>

                <button type="submit" class="btn-add" id="btnSave" style="width:100%;">Save Expense</button>
                <button type="button" onclick="document.getElementById('expModal').style.display='none'" style="width:100%; margin-top:5px; background:#ccc; border:none; padding:10px; border-radius:5px; cursor:pointer;">Cancel</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function openModal(mode, data=null) {
            document.getElementById('expModal').style.display = 'flex';
            if(mode == 'edit') {
                document.getElementById('modalTitle').innerText = 'Edit Expense';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('btnSave').innerText = 'Update Record';
                
                document.getElementById('transId').value = data.id;
                document.getElementById('date').value = data.trans_date;
                document.getElementById('category').value = data.category;
                document.getElementById('amount').value = data.amount;
                document.getElementById('description').value = data.description;
            } else {
                document.getElementById('modalTitle').innerText = 'Record Expense';
                document.getElementById('formAction').value = 'add';
                document.getElementById('btnSave').innerText = 'Save Expense';
                document.getElementById('amount').value = '';
                document.getElementById('description').value = '';
            }
        }
    </script>
</body>
</html>