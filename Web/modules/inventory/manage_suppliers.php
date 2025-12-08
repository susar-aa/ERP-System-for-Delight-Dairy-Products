<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- HANDLE ACTIONS ---

// DELETE
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // Check usage in batches
    $check = $conn->query("SELECT COUNT(*) as c FROM material_batches WHERE supplier_id = $del_id");
    if($check->fetch_assoc()['c'] > 0) {
        $msg = "<div class='alert error'>Cannot delete: This supplier is linked to existing stock batches.</div>";
    } else {
        $conn->query("DELETE FROM suppliers WHERE id = $del_id");
        $msg = "<div class='alert success'>Supplier Deleted Successfully!</div>";
    }
}

// ADD / UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $company = $_POST['company_name'];
    $contact = $_POST['contact_person'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $addr = $_POST['address'];
    
    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO suppliers (company_name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $company, $contact, $phone, $email, $addr);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Supplier Added Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    } 
    elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['supplier_id']);
        $stmt = $conn->prepare("UPDATE suppliers SET company_name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?");
        $stmt->bind_param("sssssi", $company, $contact, $phone, $email, $addr, $id);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Supplier Updated Successfully!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    }
}

// Fetch Suppliers
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY company_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Suppliers | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Table Styles */
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .data-table th { background: #34495e; color: white; padding: 12px 15px; text-align: left; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        .data-table tr:hover { background: #f9f9f9; }

        /* Action Buttons */
        .action-btn { border: none; background: none; cursor: pointer; font-size: 14px; margin-right: 5px; }
        .btn-edit { color: #f39c12; }
        .btn-delete { color: #c0392b; }

        /* Top Bar */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add-new { background: #2980b9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; font-size: 14px; }
        .search-box { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 250px; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 500px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; animation: slideDown 0.3s ease; }
        .modal-header { font-weight: bold; font-size: 18px; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #777; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; color: #555; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .btn-save { width: 100%; background: #27ae60; color: white; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-top: 10px; }
        
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
                <h2>Supplier Database</h2>
                <p style="color:#777; font-size:14px; margin:0;">Manage vendors for raw materials.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <input type="text" id="searchInput" class="search-box" placeholder="Search suppliers..." onkeyup="filterTable()">
                <button onclick="openModal('add')" class="btn-add-new">
                    <i class="fas fa-plus"></i> Add Supplier
                </button>
            </div>
        </div>

        <?php echo $msg; ?>

        <table class="data-table" id="suppTable">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Contact Person</th>
                    <th>Phone / Email</th>
                    <th>Address</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($suppliers->num_rows > 0): ?>
                    <?php while($row = $suppliers->fetch_assoc()): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['company_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['contact_person']); ?></td>
                        <td>
                            <i class="fas fa-phone" style="font-size:10px; color:#777;"></i> <?php echo htmlspecialchars($row['phone']); ?><br>
                            <span style="font-size:12px; color:#777;"><?php echo htmlspecialchars($row['email']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($row['address']); ?></td>
                        <td style="text-align:center;">
                            <button class="action-btn btn-edit" onclick='openModal("edit", <?php echo json_encode($row); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo $row['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">No suppliers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="suppModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Supplier</span>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="supplier_id" id="suppId">

                <div class="form-group">
                    <label>Company Name *</label>
                    <input type="text" name="company_name" id="compName" required placeholder="e.g. Fonterra Brands">
                </div>

                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" id="contPerson" placeholder="e.g. Mr. Perera">
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Phone Number *</label>
                        <input type="text" name="phone" id="phone" required placeholder="077...">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Email Address</label>
                        <input type="email" name="email" id="email" placeholder="info@company.com">
                    </div>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="address" rows="2" placeholder="Office / Warehouse Address"></textarea>
                </div>

                <button type="submit" class="btn-save" id="btnSave">Save Supplier</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const modal = document.getElementById('suppModal');
        
        function openModal(mode, data = null) {
            modal.style.display = 'flex';
            if (mode === 'edit' && data) {
                document.getElementById('modalTitle').innerText = 'Edit Supplier';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('btnSave').innerText = 'Update Supplier';
                
                document.getElementById('suppId').value = data.id;
                document.getElementById('compName').value = data.company_name;
                document.getElementById('contPerson').value = data.contact_person;
                document.getElementById('phone').value = data.phone;
                document.getElementById('email').value = data.email;
                document.getElementById('address').value = data.address;
            } else {
                document.getElementById('modalTitle').innerText = 'Add Supplier';
                document.getElementById('formAction').value = 'add';
                document.getElementById('btnSave').innerText = 'Save Supplier';
                
                document.getElementById('suppId').value = '';
                document.getElementById('compName').value = '';
                document.getElementById('contPerson').value = '';
                document.getElementById('phone').value = '';
                document.getElementById('email').value = '';
                document.getElementById('address').value = '';
            }
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function confirmDelete(id) {
            if(confirm("Are you sure you want to delete this supplier?")) {
                window.location.href = `manage_suppliers.php?delete_id=${id}`;
            }
        }

        // Filter Table
        function filterTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toUpperCase();
            const table = document.getElementById("suppTable");
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                const tdCompany = tr[i].getElementsByTagName("td")[0];
                const tdContact = tr[i].getElementsByTagName("td")[1];
                if (tdCompany || tdContact) {
                    const txtValue = (tdCompany.textContent || tdCompany.innerText) + (tdContact.textContent || tdContact.innerText);
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>