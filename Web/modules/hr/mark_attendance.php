<?php
require_once '../../config/db.php';

$msg = "";
$date = date('Y-m-d'); // Default to today

// 1. Handle Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['attendance_date'];
    $attendance_data = $_POST['status']; // Array of [emp_id => status]

    $conn->begin_transaction();
    try {
        // First, clear existing attendance for this date to avoid duplicates
        $conn->query("DELETE FROM attendance WHERE date = '$date'");

        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, status, check_in, check_out) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($attendance_data as $emp_id => $status) {
            // Default times for now (can be made editable later)
            $in_time = ($status == 'present') ? '08:00:00' : NULL;
            $out_time = ($status == 'present') ? '17:00:00' : NULL;

            $stmt->bind_param("issss", $emp_id, $date, $status, $in_time, $out_time);
            $stmt->execute();
        }

        $conn->commit();
        $msg = "<div style='background:#d4edda; color:#155724; padding:10px;'>Attendance Saved for $date!</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div style='background:#f8d7da; color:#721c24; padding:10px;'>Error: " . $e->getMessage() . "</div>";
    }
}

// 2. Fetch Active Employees
$employees = $conn->query("SELECT id, full_name, designation FROM employees WHERE status='active'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .attendance-table { width: 100%; background: white; border-collapse: collapse; }
        .attendance-table th { background: #34495e; color: white; padding: 10px; text-align: left; }
        .attendance-table td { padding: 10px; border-bottom: 1px solid #ddd; }
        
        /* Radio Button Styling */
        .status-options label { margin-right: 15px; cursor: pointer; }
        .radio-present { color: green; font-weight: bold; }
        .radio-absent { color: red; font-weight: bold; }
        .radio-leave { color: orange; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Daily Attendance Marking</h2>
            <a href="index.php" style="color:#3498db;">&larr; Back</a>
        </div>

        <div style="background:white; padding:20px; border-radius:8px;">
            <?php echo $msg; ?>
            
            <form method="POST">
                <div style="margin-bottom:20px;">
                    <label style="font-weight:bold;">Select Date:</label>
                    <input type="date" name="attendance_date" value="<?php echo $date; ?>" required style="padding:8px; border-radius:4px; border:1px solid #ddd;">
                </div>

                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Designation</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($emp = $employees->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                            <td class="status-options">
                                <label class="radio-present">
                                    <input type="radio" name="status[<?php echo $emp['id']; ?>]" value="present" checked> Present
                                </label>
                                <label class="radio-absent">
                                    <input type="radio" name="status[<?php echo $emp['id']; ?>]" value="absent"> Absent
                                </label>
                                <label class="radio-leave">
                                    <input type="radio" name="status[<?php echo $emp['id']; ?>]" value="leave"> Leave
                                </label>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <div style="margin-top:20px; text-align:right;">
                    <button type="submit" style="background:#27ae60; color:white; padding:12px 30px; border:none; border-radius:4px; font-size:16px; cursor:pointer;">
                        Save Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>