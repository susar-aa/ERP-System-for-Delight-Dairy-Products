<?php
require_once '../../config/db.php';

$msg = "";
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m'); // Default YYYY-MM

// Handle Payroll Save
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_payroll'])) {
    $month = $_POST['month'];
    $conn->begin_transaction();
    try {
        // Delete existing payroll for this month to allow re-calculation/updates
        $conn->query("DELETE FROM payroll WHERE month_year = '$month'");

        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, month_year, basic_salary, total_days_worked, ot_hours, ot_amount, deductions, net_salary, generated_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        foreach ($_POST['emp_id'] as $id) {
            $basic = $_POST['basic'][$id];
            $days = $_POST['days'][$id];
            $ot_h = $_POST['ot_hours'][$id];
            $deduct = $_POST['deductions'][$id];
            
            // Calculation Logic
            // 1. Earned Basic = (Basic / 30) * Days Worked
            $earned_basic = ($basic / 30) * $days;

            // 2. OT Amount = (Basic / 240 hours) * 1.5 rate * OT Hours
            // Assuming 30 days * 8 hours = 240 working hours
            $hourly_rate = $basic / 240;
            $ot_amt = $hourly_rate * 1.5 * $ot_h;

            // 3. Net Salary
            $net = $earned_basic + $ot_amt - $deduct;

            $stmt->bind_param("isdddddd", $id, $month, $basic, $days, $ot_h, $ot_amt, $deduct, $net);
            $stmt->execute();
        }

        $conn->commit();
        $msg = "<div style='background:#d4edda; color:#155724; padding:10px;'>Payroll Generated Successfully for $month!</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div style='background:#f8d7da; color:#721c24; padding:10px;'>Error: " . $e->getMessage() . "</div>";
    }
}

// Fetch Data for the Table
// We join Employees with a subquery counting their attendance for the selected month
$sql = "SELECT e.id, e.full_name, e.designation, e.basic_salary, 
        COALESCE(att.days_present, 0) as days_present,
        p.ot_hours as saved_ot, p.deductions as saved_deductions, p.net_salary as saved_net
        FROM employees e
        LEFT JOIN (
            SELECT employee_id, COUNT(*) as days_present 
            FROM attendance 
            WHERE DATE_FORMAT(date, '%Y-%m') = '$selected_month' AND status = 'present'
            GROUP BY employee_id
        ) att ON e.id = att.employee_id
        LEFT JOIN payroll p ON e.id = p.employee_id AND p.month_year = '$selected_month'
        WHERE e.status = 'active'";

$employees = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Payroll | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .filter-box { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        table { width: 100%; background: white; border-collapse: collapse; font-size: 14px; }
        th { background: #2c3e50; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        input[type="number"] { width: 80px; padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
        .net-col { font-weight: bold; color: #27ae60; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Monthly Payroll</h2>
            <a href="index.php" style="color:#3498db;">&larr; Back</a>
        </div>

        <?php echo $msg; ?>

        <!-- Month Selector -->
        <div class="filter-box">
            <form method="GET" style="display:flex; align-items:center; gap:10px;">
                <label><strong>Select Month:</strong></label>
                <input type="month" name="month" value="<?php echo $selected_month; ?>" required>
                <button type="submit" style="padding:6px 15px; background:#3498db; color:white; border:none; cursor:pointer;">Load Data</button>
            </form>
        </div>

        <form method="POST">
            <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
            
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Basic Salary (Rs)</th>
                        <th>Days Worked</th>
                        <th>OT Hours</th>
                        <th>Deductions (Rs)</th>
                        <th>Est. Net Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_payroll = 0;
                    while($row = $employees->fetch_assoc()): 
                        // Logic: If payroll saved, use saved values. If not, use defaults based on attendance.
                        $days = $row['days_present'];
                        $basic = $row['basic_salary'];
                        $ot_h = $row['saved_ot'] ?? 0; // Default 0
                        $deduct = $row['saved_deductions'] ?? 0;
                        
                        // Calculate Est Net for Display
                        $hourly_rate = $basic / 240;
                        $earned = ($basic / 30) * $days;
                        $ot_amt = $hourly_rate * 1.5 * $ot_h;
                        $net = $earned + $ot_amt - $deduct;
                        $total_payroll += $net;
                    ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($row['full_name']); ?><br>
                            <small style="color:#777;"><?php echo $row['designation']; ?></small>
                            <input type="hidden" name="emp_id[]" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="basic[<?php echo $row['id']; ?>]" value="<?php echo $basic; ?>">
                        </td>
                        <td><?php echo number_format($basic, 2); ?></td>
                        <td>
                            <!-- Readonly: Derived from Attendance Module -->
                            <input type="text" name="days[<?php echo $row['id']; ?>]" value="<?php echo $days; ?>" readonly style="background:#eee; width:50px; text-align:center;">
                        </td>
                        <td>
                            <input type="number" step="0.5" name="ot_hours[<?php echo $row['id']; ?>]" value="<?php echo $ot_h; ?>">
                        </td>
                        <td>
                            <input type="number" step="0.01" name="deductions[<?php echo $row['id']; ?>]" value="<?php echo $deduct; ?>">
                        </td>
                        <td class="net-col">
                            Rs: <?php echo number_format($net, 2); ?>
                            <?php if(isset($row['saved_net'])) echo " <span style='font-size:10px; color:blue;'>(Saved)</span>"; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <tr style="background:#f8f9fa; font-weight:bold;">
                        <td colspan="5" style="text-align:right;">Total Payroll Cost:</td>
                        <td style="color:#c0392b;">Rs: <?php echo number_format($total_payroll, 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top:20px; text-align:right;">
                <button type="submit" name="save_payroll" style="background:#27ae60; color:white; padding:15px 30px; border:none; font-size:16px; border-radius:5px; cursor:pointer;">
                    Finalize & Save Payroll
                </button>
            </div>
        </form>
    </div>
</body>
</html>