<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- HANDLE ACTIONS ---

// 1. ADD / UPDATE EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['full_name']);
    $nic = trim($_POST['nic']);
    $phone = trim($_POST['phone']);
    $dept = intval($_POST['department_id']);
    $desig = trim($_POST['designation']);
    $salary = floatval($_POST['basic_salary']);
    $joined = $_POST['joined_date'];
    $status = $_POST['status'];

    if ($_POST['action'] == 'add') {
        // Check Duplicate NIC
        $check = $conn->query("SELECT id FROM employees WHERE nic_number = '$nic'");
        if ($check->num_rows > 0) {
            $msg = "<div class='alert error'>Error: An employee with NIC $nic already exists.</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO employees (full_name, nic_number, phone, department_id, designation, basic_salary, joined_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssisdss", $name, $nic, $phone, $dept, $desig, $salary, $joined, $status);
            if ($stmt->execute()) {
                $msg = "<div class='alert success'>Employee Added Successfully!</div>";
            } else {
                $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
            }
        }
    } 
    elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['emp_id']);
        $stmt = $conn->prepare("UPDATE employees SET full_name=?, nic_number=?, phone=?, department_id=?, designation=?, basic_salary=?, joined_date=?, status=? WHERE id=?");
        $stmt->bind_param("sssisdssi", $name, $nic, $phone, $dept, $desig, $salary, $joined, $status, $id);
        if ($stmt->execute()) {
            $msg = "<div class='alert success'>Employee Details Updated!</div>";
        } else {
            $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    }
}

// 2. DELETE EMPLOYEE
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    
    // Check usage in Attendance or Payroll
    $check_att = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE employee_id = $del_id")->fetch_assoc()['c'];
    $check_pay = $conn->query("SELECT COUNT(*) as c FROM payroll WHERE employee_id = $del_id")->fetch_assoc()['c'];
    
    if ($check_att > 0 || $check_pay > 0) {
        $msg = "<div class='alert error'>Cannot delete: Employee has attendance or payroll records. Mark as 'Resigned' instead.</div>";
    } else {
        $conn->query("DELETE FROM employees WHERE id = $del_id");
        $msg = "<div class='alert success'>Employee Record Deleted.</div>";
    }
}

// --- FETCH DATA ---
$departments = $conn->query("SELECT * FROM departments ORDER BY name");
$dept_list = []; while($d = $departments->fetch_assoc()) $dept_list[] = $d;

$employees = $conn->query("
    SELECT e.*, d.name as dept_name 
    FROM employees e 
    LEFT JOIN departments d ON e.department_id = d.id 
    ORDER BY e.status ASC, e.full_name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Employees | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Top Bar */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add-new { background: #2980b9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; font-size: 14px; font-weight: bold; }
        .search-box { padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }

        /* Table */
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .data-table th { background: #34495e; color: white; padding: 12px 15px; text-align: left; font-size: 13px; text-transform: uppercase; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 14px; }
        .data-table tr:hover { background: #f9f9f9; }

        /* Badges */
        .status-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .st-active { background: #d4edda; color: #155724; }
        .st-resigned { background: #fadbd8; color: #721c24; }
        .st-terminated { background: #343a40; color: #fff; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 600px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); position: relative; animation: slideIn 0.3s ease; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-weight: bold; font-size: 18px; margin-bottom: 20px; color: #2c3e50; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #777; }
        
        .form-row { display: flex; gap: 15px; }
        .form-group { margin-bottom: 15px; flex: 1; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .btn-save { width: 100%; background: #27ae60; color: white; padding: 12px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; margin-top: 10px; font-weight: bold; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .action-btn { border: none; background: none; cursor: pointer; margin-right: 5px; font-size: 14px; }

        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="top-bar">
            <div>
                <h2>Employee Management</h2>
                <p style="color:#777; font-size:14px; margin:0;">Directory of all staff members.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <input type="text" id="searchInput" class="search-box" placeholder="Search Name, NIC, or Dept..." onkeyup="filterTable()">
                <button onclick="openModal('add')" class="btn-add-new">
                    <i class="fas fa-user-plus"></i> Add Employee
                </button>
            </div>
        </div>

        <?php echo $msg; ?>

        <table class="data-table" id="empTable">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Full Name</th>
                    <th>NIC / Phone</th>
                    <th>Department & Role</th>
                    <th>Basic Salary</th>
                    <th>Status</th>
                    <th style="width: 100px; text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($employees->num_rows > 0): ?>
                    <?php while($row = $employees->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                            <small style="color:#777;">Joined: <?php echo $row['joined_date']; ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['nic_number']); ?><br>
                            <span style="font-size:12px; color:#555;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['phone']); ?></span>
                        </td>
                        <td>
                            <span style="background:#e8f4fd; color:#0c5460; padding:2px 6px; border-radius:4px; font-size:11px;"><?php echo htmlspecialchars($row['dept_name'] ?? 'Unassigned'); ?></span><br>
                            <?php echo htmlspecialchars($row['designation']); ?>
                        </td>
                        <td><strong>Rs <?php echo number_format($row['basic_salary'], 2); ?></strong></td>
                        <td>
                            <span class="status-badge st-<?php echo $row['status']; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button class="action-btn" style="color:#f39c12;" onclick='openModal("edit", <?php echo json_encode($row); ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete_id=<?php echo $row['id']; ?>" class="action-btn" style="color:#c0392b;" onclick="return confirm('Delete this employee record?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding:20px;">No employees found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="empModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add Employee</span>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="emp_id" id="empId">

                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="fullName" required placeholder="e.g. Kasun Perera">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>NIC Number *</label>
                        <input type="text" name="nic" id="nic" required placeholder="National ID">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" id="phone" placeholder="07...">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" id="deptId" required>
                            <option value="">-- Select --</option>
                            <?php foreach($dept_list as $d): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo $d['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" name="designation" id="designation" required placeholder="e.g. Machine Operator">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Basic Salary (Rs) *</label>
                        <input type="number" step="0.01" name="basic_salary" id="salary" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Joined Date</label>
                        <input type="date" name="joined_date" id="joinDate" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="status">
                        <option value="active">Active</option>
                        <option value="resigned">Resigned</option>
                        <option value="terminated">Terminated</option>
                    </select>
                </div>

                <button type="submit" class="btn-save" id="btnSave">Save Employee</button>
            </form>
        </div>
    </div>

    <!-- Dashboard JS for Sidebar -->
    <script src="../../assets/js/dashboard.js"></script>

    <script>
        const modal = document.getElementById('empModal');
        
        function openModal(mode, data = null) {
            modal.style.display = 'flex';
            if (mode === 'edit') {
                document.getElementById('modalTitle').innerText = 'Edit Employee';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('btnSave').innerText = 'Update Employee';
                
                document.getElementById('empId').value = data.id;
                document.getElementById('fullName').value = data.full_name;
                document.getElementById('nic').value = data.nic_number;
                document.getElementById('phone').value = data.phone;
                document.getElementById('deptId').value = data.department_id;
                document.getElementById('designation').value = data.designation;
                document.getElementById('salary').value = data.basic_salary;
                document.getElementById('joinDate').value = data.joined_date;
                document.getElementById('status').value = data.status;
            } else {
                document.getElementById('modalTitle').innerText = 'Add Employee';
                document.getElementById('formAction').value = 'add';
                document.getElementById('btnSave').innerText = 'Save Employee';
                
                // Clear fields
                document.getElementById('empId').value = '';
                document.getElementById('fullName').value = '';
                document.getElementById('nic').value = '';
                document.getElementById('phone').value = '';
                document.getElementById('deptId').value = '';
                document.getElementById('designation').value = '';
                document.getElementById('salary').value = '';
                document.getElementById('joinDate').value = new Date().toISOString().split('T')[0]; // Today
                document.getElementById('status').value = 'active';
            }
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Filter Function
        function filterTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toUpperCase();
            const table = document.getElementById("empTable");
            const tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                const tdName = tr[i].getElementsByTagName("td")[1];
                const tdNic = tr[i].getElementsByTagName("td")[2];
                const tdDept = tr[i].getElementsByTagName("td")[3];
                
                if (tdName || tdNic || tdDept) {
                    const txtValue = (tdName.textContent + tdNic.textContent + tdDept.textContent).toUpperCase();
                    if (txtValue.indexOf(filter) > -1) {
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