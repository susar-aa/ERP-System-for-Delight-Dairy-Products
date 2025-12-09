<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// --- HANDLE SAVE ATTENDANCE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_attendance') {
    $save_date = $_POST['attendance_date'];
    $statuses = $_POST['status'] ?? [];
    $check_ins = $_POST['check_in'] ?? [];
    $check_outs = $_POST['check_out'] ?? [];

    $conn->begin_transaction();
    try {
        // 1. Delete existing records for this date to prevent duplicates
        $conn->query("DELETE FROM attendance WHERE date = '$save_date'");

        // 2. Insert new records
        $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, status, check_in, check_out) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($statuses as $emp_id => $status) {
            $in_time = !empty($check_ins[$emp_id]) ? $check_ins[$emp_id] : null;
            $out_time = !empty($check_outs[$emp_id]) ? $check_outs[$emp_id] : null;
            
            // Auto-fill times if present but empty
            if ($status == 'present') {
                if (!$in_time) $in_time = '08:00:00';
                if (!$out_time) $out_time = '17:00:00';
            } else {
                $in_time = null;
                $out_time = null;
            }

            $stmt->bind_param("issss", $emp_id, $save_date, $status, $in_time, $out_time);
            $stmt->execute();
        }

        $conn->commit();
        $msg = "<div class='alert success'>Attendance Record Saved for $save_date!</div>";
        $date = $save_date; // Keep view on saved date

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- FETCH DATA ---
// 1. Get Employees + Attendance for Selected Date
$sql = "
    SELECT e.id, e.full_name, e.designation, 
           a.status, a.check_in, a.check_out
    FROM employees e 
    LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = '$date'
    WHERE e.status = 'active'
    ORDER BY e.full_name ASC
";
$result = $conn->query($sql);

$employees = [];
$stats = ['present' => 0, 'absent' => 0, 'leave' => 0, 'total' => 0];

while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
    $stats['total']++;
    if ($row['status']) {
        $stats[$row['status']]++;
    }
}

// If viewing a future date or no data, stats might be 0.
// Is this a new entry? (No attendance records found)
$is_new = ($stats['present'] + $stats['absent'] + $stats['leave']) == 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mark Attendance | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        
        .filter-area { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; }
        .filter-area input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .btn-go { background: #3498db; color: white; border: none; padding: 9px 15px; border-radius: 4px; cursor: pointer; }

        /* Stats Cards */
        .stats-row { display: flex; gap: 15px; margin-bottom: 20px; }
        .stat-card { flex: 1; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); text-align: center; border-bottom: 3px solid #ddd; }
        .stat-card h4 { margin: 0 0 5px 0; font-size: 12px; color: #777; text-transform: uppercase; }
        .stat-card .val { font-size: 20px; font-weight: bold; color: #333; }
        .border-green { border-color: #27ae60; } .text-green { color: #27ae60; }
        .border-red { border-color: #c0392b; } .text-red { color: #c0392b; }
        .border-orange { border-color: #f39c12; } .text-orange { color: #f39c12; }

        /* Attendance Table */
        .att-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden; }
        .att-table th { background: #2c3e50; color: white; padding: 12px; text-align: left; font-size: 13px; text-transform: uppercase; }
        .att-table td { padding: 10px 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .att-table tr:hover { background: #f9f9f9; }

        /* Status Radios */
        .status-group { display: flex; gap: 5px; }
        .radio-btn { display: none; }
        .radio-label { 
            padding: 5px 12px; border: 1px solid #ddd; border-radius: 20px; cursor: pointer; font-size: 12px; font-weight: bold; color: #777; transition: 0.2s; 
            display: flex; align-items: center; gap: 5px;
        }
        
        /* Checked States */
        .radio-btn:checked + .lbl-present { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .radio-btn:checked + .lbl-absent { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .radio-btn:checked + .lbl-leave { background: #fff3cd; color: #856404; border-color: #ffeeba; }

        .time-input { width: 90px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; text-align: center; }
        
        .btn-save { background: #27ae60; color: white; padding: 12px 30px; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; float: right; margin-top: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-save:hover { background: #219150; }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <div class="top-bar">
            <div>
                <h2>Daily Attendance</h2>
                <p style="color:#777; margin:0;">Mark or view staff attendance.</p>
            </div>
            
            <form method="GET" class="filter-area">
                <label><strong>Date:</strong></label>
                <input type="date" name="date" value="<?php echo $date; ?>" max="<?php echo date('Y-m-d'); ?>">
                <button type="submit" class="btn-go"><i class="fas fa-search"></i> Load</button>
            </form>
        </div>

        <?php echo $msg; ?>

        <!-- Stats Summary -->
        <div class="stats-row">
            <div class="stat-card border-green">
                <h4>Present</h4>
                <div class="val text-green"><?php echo $stats['present']; ?></div>
            </div>
            <div class="stat-card border-red">
                <h4>Absent</h4>
                <div class="val text-red"><?php echo $stats['absent']; ?></div>
            </div>
            <div class="stat-card border-orange">
                <h4>On Leave</h4>
                <div class="val text-orange"><?php echo $stats['leave']; ?></div>
            </div>
            <div class="stat-card">
                <h4>Total Staff</h4>
                <div class="val"><?php echo $stats['total']; ?></div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="save_attendance">
            <input type="hidden" name="attendance_date" value="<?php echo $date; ?>">

            <div style="margin-bottom:10px; text-align:right;">
                <button type="button" onclick="markAll('present')" style="font-size:12px; color:#27ae60; background:none; border:none; cursor:pointer; text-decoration:underline;">Mark All Present</button>
                <span style="color:#ddd;">|</span>
                <button type="button" onclick="markAll('absent')" style="font-size:12px; color:#c0392b; background:none; border:none; cursor:pointer; text-decoration:underline;">Mark All Absent</button>
            </div>

            <table class="att-table">
                <thead>
                    <tr>
                        <th>Employee Details</th>
                        <th style="width: 300px;">Status</th>
                        <th style="width: 120px;">Check In</th>
                        <th style="width: 120px;">Check Out</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $emp): 
                            // Default to present if new entry, otherwise use DB value
                            $st = $is_new ? 'present' : ($emp['status'] ?? 'absent');
                            $in = $emp['check_in'] ?? '08:00';
                            $out = $emp['check_out'] ?? '17:00';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong><br>
                                <small style="color:#777;"><?php echo htmlspecialchars($emp['designation']); ?></small>
                            </td>
                            <td>
                                <div class="status-group">
                                    <label>
                                        <input type="radio" name="status[<?php echo $emp['id']; ?>]" value="present" class="radio-btn" <?php echo $st=='present'?'checked':''; ?>>
                                        <span class="radio-label lbl-present"><i class="fas fa-check"></i> Present</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="status[<?php echo $emp['id']; ?>]" value="absent" class="radio-btn" <?php echo $st=='absent'?'checked':''; ?>>
                                        <span class="radio-label lbl-absent"><i class="fas fa-times"></i> Absent</span>
                                    </label>
                                    <label>
                                        <input type="radio" name="status[<?php echo $emp['id']; ?>]" value="leave" class="radio-btn" <?php echo $st=='leave'?'checked':''; ?>>
                                        <span class="radio-label lbl-leave"><i class="fas fa-plane"></i> Leave</span>
                                    </label>
                                </div>
                            </td>
                            <td>
                                <input type="time" name="check_in[<?php echo $emp['id']; ?>]" value="<?php echo $in; ?>" class="time-input">
                            </td>
                            <td>
                                <input type="time" name="check_out[<?php echo $emp['id']; ?>]" value="<?php echo $out; ?>" class="time-input">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding:20px;">No active employees found to mark.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (count($employees) > 0): ?>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Attendance
                </button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function markAll(status) {
            const radios = document.querySelectorAll(`input[value="${status}"]`);
            radios.forEach(r => r.checked = true);
        }
    </script>
</body>
</html>