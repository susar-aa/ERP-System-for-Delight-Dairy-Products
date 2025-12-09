<?php
require_once '../../config/db.php';

// Security Check - Only Admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit;
}

// --- AUTO-UPDATE DB SCHEMA FOR STATUS ---
$check_col = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN status ENUM('active', 'blocked') DEFAULT 'active' AFTER role");
}

$msg = "";

// --- HANDLE ACTIONS ---

// DELETE USER
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    if ($id != $_SESSION['user_id']) { // Prevent self-delete
        $conn->query("DELETE FROM users WHERE id=$id");
        $msg = "<div class='alert success'>User Deleted Successfully.</div>";
    } else {
        $msg = "<div class='alert error'>You cannot delete your own account!</div>";
    }
}

// ADD / UPDATE USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $role = $_POST['role'];
    $status = $_POST['status']; // New Field
    $emp_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;
    
    // Process Permissions (Array to JSON)
    $perms = isset($_POST['perms']) ? $_POST['perms'] : [];
    $perm_json = json_encode($perms);

    if ($_POST['action'] == 'add') {
        $password = $_POST['password'];
        
        // Check username uniqueness
        $check = $conn->query("SELECT id FROM users WHERE username='$username'");
        if ($check->num_rows > 0) {
            $msg = "<div class='alert error'>Username '$username' already exists.</div>";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, role, status, employee_id, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssis", $username, $hash, $fullname, $role, $status, $emp_id, $perm_json);
            
            if ($stmt->execute()) {
                $msg = "<div class='alert success'>User Created Successfully!</div>";
            } else {
                $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
            }
        }
    } 
    elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['user_id']);
        
        // Update basic info
        $sql = "UPDATE users SET full_name=?, role=?, status=?, employee_id=?, permissions=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisi", $fullname, $role, $status, $emp_id, $perm_json, $id);
        
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>User Updated Successfully!</div>";
            
            // Update password only if provided
            if (!empty($_POST['password'])) {
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password_hash='$hash' WHERE id=$id");
            }
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    }
}

// --- FETCH DATA ---
$users = $conn->query("
    SELECT u.*, e.nic_number, e.designation 
    FROM users u 
    LEFT JOIN employees e ON u.employee_id = e.id 
    ORDER BY u.username
");

$employees = $conn->query("SELECT id, full_name, nic_number FROM employees WHERE status='active' ORDER BY full_name");
$emp_list = []; while($e = $employees->fetch_assoc()) $emp_list[] = $e;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; }
        
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #34495e; color: white; padding: 12px; text-align: left; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; }
        
        .perm-tag { background: #e8f6f3; color: #16a085; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-right: 2px; display: inline-block; margin-bottom: 2px; }
        .role-badge { background: #3498db; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; text-transform: uppercase; }
        .role-admin { background: #c0392b; }
        
        .status-blocked { color: white; background: #e74c3c; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .status-active { color: #27ae60; font-weight: bold; font-size: 12px; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 600px; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-weight: bold; font-size: 18px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; }
        
        .form-row { display: flex; gap: 15px; }
        .form-group { margin-bottom: 15px; flex: 1; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .perm-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; background: #f9f9f9; padding: 15px; border-radius: 5px; border: 1px solid #eee; }
        .perm-item { display: flex; align-items: center; gap: 8px; }
        .perm-item input { width: auto; cursor: pointer; }
        .perm-item label { margin: 0; font-weight: normal; cursor: pointer; font-size: 13px; }

        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
        
        .linked-emp { font-size: 12px; color: #27ae60; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h2>System Users</h2>
                <p style="color:#777; margin:0;">Manage login access, status, and permissions.</p>
            </div>
            <button onclick="openModal('add')" class="btn-add"><i class="fas fa-user-plus"></i> Create User</button>
        </div>

        <?php echo $msg; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Linked Employee</th>
                    <th>Role & Status</th>
                    <th>Module Access</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $users->fetch_assoc()): 
                    $p_list = json_decode($row['permissions'], true) ?? [];
                    $active_perms = array_keys(array_filter($p_list, function($v){ return $v == '1'; }));
                    $u_status = $row['status'] ?? 'active';
                ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($row['username']); ?></strong><br>
                        <small style="color:#777;"><?php echo htmlspecialchars($row['full_name']); ?></small>
                    </td>
                    <td>
                        <?php if($row['employee_id']): ?>
                            <span class="linked-emp"><i class="fas fa-link"></i> <?php echo htmlspecialchars($row['full_name']); ?></span><br>
                            <span style="font-size:11px; color:#aaa;"><?php echo htmlspecialchars($row['designation']); ?></span>
                        <?php else: ?>
                            <span style="color:#ccc;">- Not Linked -</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="role-badge <?php echo $row['role']=='admin' ? 'role-admin':''; ?>">
                            <?php echo ucfirst($row['role']); ?>
                        </span>
                        <?php if($u_status == 'blocked'): ?>
                            <span class="status-blocked">BLOCKED</span>
                        <?php else: ?>
                            <span class="status-active"><i class="fas fa-check-circle"></i> Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if(empty($active_perms)) echo "<span style='color:#ccc; font-size:11px;'>No specific access</span>";
                        foreach($active_perms as $p) {
                            echo "<span class='perm-tag'>" . ucfirst($p) . "</span>";
                        }
                        ?>
                    </td>
                    <td>
                        <button onclick='openModal("edit", <?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)' style="border:none; background:none; cursor:pointer; color:#f39c12;"><i class="fas fa-edit"></i></button>
                        <?php if($row['id'] != $_SESSION['user_id']): ?>
                            <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this user?')" style="color:#c0392b; margin-left:10px;"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Create User</span>
                <span onclick="closeModal()" style="cursor:pointer;">&times;</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="user_id" id="userId">

                <div class="form-row">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" id="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" id="password" placeholder="Leave blank to keep current">
                    </div>
                </div>

                <div class="form-group">
                    <label>Full Name (Display Name)</label>
                    <input type="text" name="full_name" id="fullName" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>System Role</label>
                        <select name="role" id="roleSelect">
                            <option value="staff">Staff (Restricted)</option>
                            <option value="hr">HR Manager</option>
                            <option value="store_keeper">Store Keeper</option>
                            <option value="production_manager">Production Manager</option>
                            <option value="accountant">Accountant</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Login Status</label>
                        <select name="status" id="statusSelect">
                            <option value="active">Active</option>
                            <option value="blocked">Blocked (No Access)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Link to Employee (HR)</label>
                    <select name="employee_id" id="empSelect">
                        <option value="">-- Select Employee --</option>
                        <?php foreach($emp_list as $e): ?>
                            <option value="<?php echo $e['id']; ?>"><?php echo $e['full_name']; ?> (<?php echo $e['nic_number']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Module Access Permissions</label>
                    <div class="perm-grid">
                        <div class="perm-item">
                            <input type="checkbox" name="perms[hr]" value="1" id="perm_hr">
                            <label for="perm_hr">HR & Payroll</label>
                        </div>
                        <div class="perm-item">
                            <input type="checkbox" name="perms[inventory]" value="1" id="perm_inventory">
                            <label for="perm_inventory">Inventory</label>
                        </div>
                        <div class="perm-item">
                            <input type="checkbox" name="perms[production]" value="1" id="perm_production">
                            <label for="perm_production">Production</label>
                        </div>
                        <div class="perm-item">
                            <input type="checkbox" name="perms[sales]" value="1" id="perm_sales">
                            <label for="perm_sales">Sales & Billing</label>
                        </div>
                        <div class="perm-item">
                            <input type="checkbox" name="perms[accounts]" value="1" id="perm_accounts">
                            <label for="perm_accounts">Accounts</label>
                        </div>
                        <div class="perm-item">
                            <input type="checkbox" name="perms[admin]" value="1" id="perm_admin">
                            <label for="perm_admin">System Admin</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-add" id="btnSave" style="width:100%;">Create User</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        const modal = document.getElementById('userModal');
        
        function openModal(mode, data=null) {
            modal.style.display = 'flex';
            // Reset Checkboxes
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);

            if(mode == 'edit') {
                document.getElementById('modalTitle').innerText = 'Edit User';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('btnSave').innerText = 'Update User';
                
                document.getElementById('userId').value = data.id;
                document.getElementById('username').value = data.username;
                document.getElementById('username').readOnly = true; 
                document.getElementById('fullName').value = data.full_name;
                document.getElementById('roleSelect').value = data.role;
                document.getElementById('statusSelect').value = data.status || 'active'; // Load Status
                document.getElementById('empSelect').value = data.employee_id;
                document.getElementById('password').required = false;

                // Load Permissions safely
                if(data.permissions) {
                    try {
                        const perms = typeof data.permissions === 'string' ? JSON.parse(data.permissions) : data.permissions;
                        if (perms) {
                            for (const [key, value] of Object.entries(perms)) {
                                if(value == '1') {
                                    const cb = document.getElementById('perm_' + key);
                                    if(cb) cb.checked = true;
                                }
                            }
                        }
                    } catch (e) {
                        console.error("Error parsing permissions", e);
                    }
                }
            } else {
                document.getElementById('modalTitle').innerText = 'Create User';
                document.getElementById('formAction').value = 'add';
                document.getElementById('btnSave').innerText = 'Create User';
                
                document.getElementById('userId').value = '';
                document.getElementById('username').value = '';
                document.getElementById('username').readOnly = false;
                document.getElementById('fullName').value = '';
                document.getElementById('password').value = '';
                document.getElementById('password').required = true;
                document.getElementById('roleSelect').value = 'staff';
                document.getElementById('statusSelect').value = 'active';
                document.getElementById('empSelect').value = '';
            }
        }

        function closeModal() { modal.style.display = 'none'; }
        window.onclick = function(e) { if(e.target == modal) closeModal(); }
    </script>
</body>
</html>