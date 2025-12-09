<?php
require_once '../../config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$msg = "";
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'history'; // 'history', 'generate', 'view'
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// --- ACTION: SAVE PAYROLL ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_payroll') {
    $month = $_POST['month_year'];
    
    $conn->begin_transaction();
    try {
        // Clear existing data for this month (Overwrite)
        $conn->query("DELETE FROM payroll WHERE month_year = '$month'");
        
        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, month_year, basic_salary, total_days_worked, ot_hours, ot_amount, deductions, net_salary, generated_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        foreach ($_POST['emp_id'] as $id) {
            $basic = floatval($_POST['basic'][$id]);
            $days = floatval($_POST['days'][$id]);
            $ot_hrs = floatval($_POST['ot_hours'][$id]);
            $deduct = floatval($_POST['deductions'][$id]);
            
            // CALCULATION LOGIC
            // 1. Basic Earned = (Basic / 30) * Days Worked (Pro-rated)
            // Note: If worked 26+ days (standard month), usually full basic is given. Here we use pro-rata for simplicity.
            // Adjust logic: If days >= 26, give full basic? Let's stick to pro-rata or fixed.
            // Let's use strict pro-rata: Daily Rate = Basic / 30.
            $daily_rate = $basic / 30;
            $earned_basic = $daily_rate * $days;
            
            // 2. OT Amount = (Basic / 240 hours) * 1.5 * OT Hours
            $hourly_rate = $basic / 240;
            $ot_amt = $hourly_rate * 1.5 * $ot_hrs;
            
            // 3. Net
            $net = $earned_basic + $ot_amt - $deduct;
            
            $stmt->bind_param("isdddddd", $id, $month, $basic, $days, $ot_hrs, $ot_amt, $deduct, $net);
            $stmt->execute();
        }
        
        $conn->commit();
        $msg = "<div class='alert success'>Payroll for <strong>$month</strong> generated successfully!</div>";
        $mode = 'history'; // Go back to list
        
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "<div class='alert error'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- DATA FETCHING ---

// 1. History List
$history_q = $conn->query("
    SELECT month_year, COUNT(*) as emp_count, SUM(net_salary) as total_payout, MAX(generated_date) as gen_date 
    FROM payroll 
    GROUP BY month_year 
    ORDER BY month_year DESC
");

// 2. Generate Data (Employees + Attendance Stats)
$gen_data = [];
if ($mode == 'generate') {
    $start_date = "$selected_month-01";
    $end_date = date("Y-m-t", strtotime($start_date));
    
    // Check if payroll already exists
    $check = $conn->query("SELECT id FROM payroll WHERE month_year = '$selected_month' LIMIT 1");
    if($check->num_rows > 0) {
        $msg = "<div class='alert' style='background:#fff3cd; color:#856404; border-color:#ffeeba;'>
                <i class='fas fa-exclamation-triangle'></i> Warning: Payroll for <strong>$selected_month</strong> already exists. Saving will overwrite it.
                </div>";
    }

    $sql = "
        SELECT e.id, e.full_name, e.designation, e.basic_salary, e.joined_date,
        (SELECT COUNT(*) FROM attendance WHERE employee_id = e.id AND date BETWEEN '$start_date' AND '$end_date' AND status='present') as days_present
        FROM employees e
        WHERE e.status = 'active'
        ORDER BY e.full_name
    ";
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()) $gen_data[] = $row;
}

// 3. View Details (Payslips)
$view_data = [];
if ($mode == 'view' && isset($_GET['view_month'])) {
    $vm = $_GET['view_month'];
    $v_sql = "
        SELECT p.*, e.full_name, e.designation, e.nic_number, e.joined_date
        FROM payroll p 
        JOIN employees e ON p.employee_id = e.id 
        WHERE p.month_year = '$vm'
        ORDER BY e.full_name
    ";
    $v_res = $conn->query($v_sql);
    while($row = $v_res->fetch_assoc()) $view_data[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Management | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-new { background: #8e44ad; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .btn-view { background: #3498db; color: white; padding: 5px 10px; border-radius: 3px; font-size: 12px; text-decoration: none; }
        
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #34495e; color: white; padding: 12px; text-align: left; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .data-table tr:hover { background: #f9f9f9; }

        .gen-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .form-row { display: flex; align-items: flex-end; gap: 15px; margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; font-size: 13px; color: #555; margin-bottom: 5px; }
        .form-group input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        
        .inp-sm { width: 80px; padding: 5px; border: 1px solid #ccc; text-align: right; }
        .val-disp { font-weight: bold; color: #555; }
        .net-disp { font-weight: bold; color: #27ae60; }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Payslip Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 0; border-radius: 5px; width: 700px; max-height: 90vh; overflow-y: auto; }
        .slip-header { background: #34495e; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; }
        .slip-body { padding: 30px; font-family: 'Courier New', Courier, monospace; }
        
        .slip-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; border-bottom: 2px dashed #ccc; padding-bottom: 20px; }
        .slip-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .slip-total { border-top: 2px solid #333; border-bottom: 2px solid #333; padding: 10px 0; font-weight: bold; font-size: 18px; margin-top: 20px; }
        
        @media print {
            body * { visibility: hidden; }
            #payslipArea, #payslipArea * { visibility: visible; }
            #payslipArea { position: absolute; left: 0; top: 0; width: 100%; }
            .modal { position: absolute; background: white; }
            .close-btn, .print-btn { display: none; }
        }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        
        <?php echo $msg; ?>

        <!-- MODE: HISTORY LIST -->
        <?php if ($mode == 'history'): ?>
            <div class="top-bar">
                <div>
                    <h2>Payroll History</h2>
                    <p style="color:#777; margin:0;">View past payrolls and generate new ones.</p>
                </div>
                <form method="GET" action="payroll.php">
                    <input type="hidden" name="mode" value="generate">
                    <div style="display:flex; gap:10px;">
                        <input type="month" name="month" value="<?php echo date('Y-m'); ?>" required style="padding:10px; border:1px solid #ddd; border-radius:5px;">
                        <button type="submit" class="btn-new"><i class="fas fa-calculator"></i> Generate Payroll</button>
                    </div>
                </form>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Employees Processed</th>
                        <th>Total Payout (Rs)</th>
                        <th>Generated On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($history_q->num_rows > 0): ?>
                        <?php while($row = $history_q->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:bold;"><?php echo date('F Y', strtotime($row['month_year'])); ?></td>
                            <td><?php echo $row['emp_count']; ?></td>
                            <td style="color:#27ae60; font-weight:bold;">Rs <?php echo number_format($row['total_payout'], 2); ?></td>
                            <td><?php echo $row['gen_date']; ?></td>
                            <td>
                                <a href="?mode=view&view_month=<?php echo $row['month_year']; ?>" class="btn-view"><i class="fas fa-eye"></i> View Payslips</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:20px;">No payroll records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <!-- MODE: GENERATE FORM -->
        <?php elseif ($mode == 'generate'): ?>
            <div class="top-bar">
                <h2>Generate Payroll: <?php echo date('F Y', strtotime($selected_month)); ?></h2>
                <a href="payroll.php" style="color:#c0392b; text-decoration:none;">Cancel</a>
            </div>

            <form method="POST" class="gen-box">
                <input type="hidden" name="action" value="save_payroll">
                <input type="hidden" name="month_year" value="<?php echo $selected_month; ?>">

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th style="text-align:right;">Basic Salary</th>
                            <th style="text-align:center;">Days Worked</th>
                            <th style="text-align:center;">OT Hours</th>
                            <th style="text-align:right;">Deductions</th>
                            <th style="text-align:right;">Net Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($gen_data as $emp): 
                            $daily_rate = $emp['basic_salary'] / 30;
                            $default_pay = round($daily_rate * $emp['days_present'], 2);
                        ?>
                        <tr oninput="calcRow(this)">
                            <td>
                                <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong><br>
                                <span style="font-size:12px; color:#777;"><?php echo $emp['designation']; ?></span>
                                <input type="hidden" name="emp_id[]" value="<?php echo $emp['id']; ?>">
                            </td>
                            <td style="text-align:right;">
                                <input type="hidden" name="basic[<?php echo $emp['id']; ?>]" class="basic-val" value="<?php echo $emp['basic_salary']; ?>">
                                <?php echo number_format($emp['basic_salary'], 2); ?>
                            </td>
                            <td style="text-align:center;">
                                <input type="number" name="days[<?php echo $emp['id']; ?>]" class="inp-sm days-val" value="<?php echo $emp['days_present']; ?>" min="0" max="31">
                            </td>
                            <td style="text-align:center;">
                                <input type="number" name="ot_hours[<?php echo $emp['id']; ?>]" class="inp-sm ot-val" value="0" min="0" step="0.5">
                            </td>
                            <td style="text-align:right;">
                                <input type="number" name="deductions[<?php echo $emp['id']; ?>]" class="inp-sm ded-val" value="0" min="0" step="100">
                            </td>
                            <td style="text-align:right;" class="net-disp">
                                Rs <span class="row-net"><?php echo number_format($default_pay, 2); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top:20px; text-align:right;">
                    <button type="submit" class="btn-new" onclick="return confirm('Process and Save Payroll? This will update the records.')">
                        <i class="fas fa-save"></i> Save & Finalize
                    </button>
                </div>
            </form>

        <!-- MODE: VIEW / PAYSLIPS -->
        <?php elseif ($mode == 'view'): ?>
            <div class="top-bar">
                <h2>Payroll Details: <?php echo date('F Y', strtotime($_GET['view_month'])); ?></h2>
                <a href="payroll.php" style="color:#c0392b; text-decoration:none;">&larr; Back to History</a>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Basic</th>
                        <th>Days</th>
                        <th>OT (Rs)</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($view_data as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo number_format($row['basic_salary'], 2); ?></td>
                        <td><?php echo $row['total_days_worked']; ?></td>
                        <td><?php echo number_format($row['ot_amount'], 2); ?> <small class="text-muted">(<?php echo $row['ot_hours']; ?>h)</small></td>
                        <td style="color:#c0392b;"><?php echo number_format($row['deductions'], 2); ?></td>
                        <td style="font-weight:bold; color:#27ae60;"><?php echo number_format($row['net_salary'], 2); ?></td>
                        <td>
                            <button onclick='printSlip(<?php echo json_encode($row); ?>)' class="btn-view" style="border:none; cursor:pointer;">
                                <i class="fas fa-print"></i> Slip
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

    <!-- PAYSLIP MODAL -->
    <div id="slipModal" class="modal">
        <div class="modal-content" id="payslipArea">
            <div class="slip-header">
                <span style="font-size:18px; font-weight:bold;">PAYSLIP</span>
                <span onclick="document.getElementById('slipModal').style.display='none'" class="close-btn" style="cursor:pointer; font-size:20px;">&times;</span>
            </div>
            <div class="slip-body">
                <div style="text-align:center; margin-bottom:20px;">
                    <h2 style="margin:0;">DELIGHT DAIRY PRODUCTS</h2>
                    <p style="margin:0;">Payslip for <span id="slipMonth"></span></p>
                </div>
                
                <div class="slip-grid">
                    <div>
                        <strong>Name:</strong> <span id="slipName"></span><br>
                        <strong>Designation:</strong> <span id="slipDesig"></span><br>
                        <strong>NIC:</strong> <span id="slipNic"></span>
                    </div>
                    <div style="text-align:right;">
                        <strong>Generated:</strong> <?php echo date('Y-m-d'); ?>
                    </div>
                </div>

                <div style="border-top:1px solid #ccc; padding-top:10px;">
                    <div class="slip-row">
                        <span>Basic Salary</span>
                        <span id="slipBasic"></span>
                    </div>
                    <div class="slip-row">
                        <span>Days Worked</span>
                        <span id="slipDays"></span>
                    </div>
                    <div class="slip-row">
                        <span>OT Amount</span>
                        <span id="slipOT"></span>
                    </div>
                    <div class="slip-row" style="color:#c0392b;">
                        <span>Deductions</span>
                        <span id="slipDed"></span>
                    </div>
                    
                    <div class="slip-total slip-row">
                        <span>NET PAYABLE</span>
                        <span id="slipNet"></span>
                    </div>
                </div>
                
                <div style="margin-top:40px; text-align:center; font-size:12px;">
                    This is a computer generated payslip.
                </div>
            </div>
            <div style="padding:15px; text-align:right; border-top:1px solid #eee;">
                <button onclick="window.print()" class="btn-new print-btn"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>

    <!-- Sidebar JS -->
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function calcRow(tr) {
            const basic = parseFloat(tr.querySelector('.basic-val').value) || 0;
            const days = parseFloat(tr.querySelector('.days-val').value) || 0;
            const ot = parseFloat(tr.querySelector('.ot-val').value) || 0;
            const ded = parseFloat(tr.querySelector('.ded-val').value) || 0;

            const earned = (basic / 30) * days;
            const ot_rate = (basic / 240) * 1.5;
            const ot_amt = ot_rate * ot;
            const net = earned + ot_amt - ded;

            tr.querySelector('.row-net').innerText = net.toFixed(2);
        }

        function printSlip(data) {
            document.getElementById('slipModal').style.display = 'flex';
            document.getElementById('slipMonth').innerText = data.month_year;
            document.getElementById('slipName').innerText = data.full_name;
            document.getElementById('slipDesig').innerText = data.designation;
            document.getElementById('slipNic').innerText = data.nic_number;
            
            document.getElementById('slipBasic').innerText = parseFloat(data.basic_salary).toFixed(2);
            document.getElementById('slipDays').innerText = data.total_days_worked;
            document.getElementById('slipOT').innerText = parseFloat(data.ot_amount).toFixed(2);
            document.getElementById('slipDed').innerText = '-' + parseFloat(data.deductions).toFixed(2);
            document.getElementById('slipNet').innerText = 'Rs ' + parseFloat(data.net_salary).toFixed(2);
        }
        
        window.onclick = function(e) { if(e.target == document.getElementById('slipModal')) document.getElementById('slipModal').style.display = 'none'; }
    </script>
</body>
</html>