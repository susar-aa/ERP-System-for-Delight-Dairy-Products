<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

// 1. Total Active Staff
$staff_count = $conn->query("SELECT COUNT(*) as c FROM employees WHERE status='active'")->fetch_assoc()['c'];

// 2. Attendance Today
$today = date('Y-m-d');
$present_today = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date = '$today' AND status = 'present'")->fetch_assoc()['c'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Dashboard | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .action-bar { margin-bottom: 20px; display: flex; gap: 10px; }
        .btn { padding: 10px 20px; text-decoration: none; color: white; border-radius: 5px; font-size: 14px; }
        .btn-blue { background-color: #3498db; }
        .btn-green { background-color: #27ae60; }
        .btn-purple { background-color: #9b59b6; }
    </style>
</head>
<body>

    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>HR & Payroll Management</h2>
        </div>

        <!-- Workflow Actions -->
        <div class="action-bar">
            <a href="manage_employees.php" class="btn btn-blue">1. Manage Employees</a>
            <a href="mark_attendance.php" class="btn btn-green">2. Daily Attendance</a>
            <a href="payroll.php" class="btn btn-purple">3. Generate Payroll</a>
        </div>

        <div class="kpi-grid">
            <div class="card">
                <h4>Total Active Staff</h4>
                <div class="value"><?php echo $staff_count; ?></div>
            </div>

            <div class="card">
                <h4>Present Today</h4>
                <div class="value"><?php echo $present_today; ?></div>
                <div style="font-size:12px; color:#aaa;"><?php echo $today; ?></div>
            </div>
        </div>

    </div>
</body>
</html>