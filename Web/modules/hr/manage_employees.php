<?php
require_once '../../config/db.php';

$msg = "";

// Handle Add Employee
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['full_name'];
    $nic = $_POST['nic'];
    $phone = $_POST['phone'];
    $dept = $_POST['department_id'];
    $desig = $_POST['designation'];
    $salary = $_POST['basic_salary'];
    $joined = $_POST['joined_date'];

    $stmt = $conn->prepare("INSERT INTO employees (full_name, nic_number, phone, department_id, designation, basic_salary, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssisds", $name, $nic, $phone, $dept, $desig, $salary, $joined);
    
    if ($stmt->execute()) {
        $msg = "<p style='color:green;'>Employee Added Successfully!</p>";
    } else {
        $msg = "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}

// Fetch Data
$departments = $conn->query("SELECT * FROM departments");
$employees = $conn->query("SELECT e.*, d.name as dept_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id ORDER BY e.id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Employees | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .form-container { background: white; padding: 20px; border-radius: 8px; max-width: 700px; margin-bottom:20px; }
        .form-row { display: flex; gap: 15px; }
        .form-group { flex: 1; margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        table { width: 100%; background: white; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 10px; border-bottom: 1px solid #eee; text-align: left; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Employee Master File</h2>
            <a href="index.php" style="color:#3498db;">&larr; Back</a>
        </div>

        <div class="form-container">
            <h3>Add New Staff Member</h3>
            <?php echo $msg; ?>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>NIC Number</label>
                        <input type="text" name="nic" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id">
                            <?php while($d = $departments->fetch_assoc()): ?>
                                <option value="<?php echo $d['id']; ?>"><?php echo $d['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" name="designation" placeholder="e.g. Machine Operator">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Basic Salary (Rs)</label>
                        <input type="number" step="0.01" name="basic_salary" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Joined Date</label>
                    <input type="date" name="joined_date" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <button type="submit" style="background:#3498db; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer;">Save Employee</button>
            </form>
        </div>

        <h3>Staff List</h3>
        <table>
            <tr style="background:#f8f9fa;">
                <th>ID</th>
                <th>Name</th>
                <th>NIC</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Basic Salary</th>
                <th>Status</th>
            </tr>
            <?php while($row = $employees->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['nic_number']); ?></td>
                <td><?php echo htmlspecialchars($row['dept_name']); ?></td>
                <td><?php echo htmlspecialchars($row['designation']); ?></td>
                <td>Rs: <?php echo number_format($row['basic_salary'], 2); ?></td>
                <td><span style="padding:2px 6px; border-radius:4px; background:<?php echo $row['status']=='active'?'#d4edda':'#f8d7da'; ?>"><?php echo $row['status']; ?></span></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>