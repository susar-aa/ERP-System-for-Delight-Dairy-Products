<?php
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../index.php"); exit; }

$msg = "";

// --- HANDLE ACTIONS ---

// 1. ADD REQUEST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'request_advance') {
    $emp_id = intval($_POST['employee_id']);
    $amount = floatval($_POST['amount']);
    $date = date('Y-m-d');
    $deduct_month = $_POST['deduction_month'];
    $reason = trim($_POST['reason']);

    // Basic Validation: Check if amount > Basic Salary? (Optional, skipped for flexibility)
    
    $stmt = $conn->prepare("INSERT INTO salary_advances (employee_id, amount, request_date, deduction_month, reason, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("idsss", $emp_id, $amount, $date, $deduct_month, $reason);
    
    if ($stmt->execute()) {
        $msg = "<div class='alert success'>Advance Request Submitted Successfully!</div>";
    } else {
        $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
    }
}

// 2. APPROVE / REJECT
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action']; // 'approve' or 'reject'
    $status = ($action == 'approve') ? 'approved' : 'rejected';
    $user = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE salary_advances SET status=?, approved_by=? WHERE id=?");
    $stmt->bind_param("sii", $status, $user, $id);
    
    if ($stmt->execute()) {
        $msg = "<div class='alert success'>Request " . ucfirst($status) . "!</div>";
    }
}

// --- DATA FETCHING ---
$employees = $conn->query("SELECT id, full_name, nic_number FROM employees WHERE status='active' ORDER BY full_name");

// List Advances (Pending First, Then Approved)
$advances = $conn->query("
    SELECT sa.*, e.full_name, e.designation, u.username as approver
    FROM salary_advances sa
    JOIN employees e ON sa.employee_id = e.id
    LEFT JOIN users u ON sa.approved_by = u.id
    ORDER BY FIELD(sa.status, 'pending', 'approved', 'deducted', 'rejected'), sa.request_date DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Salary Advances | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add { background: #8e44ad; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; }
        
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #34495e; color: white; padding: 12px; text-align: left; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .data-table tr:hover { background: #f9f9f9; }

        .st-badge { padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .st-pending { background: #f39c12; color: white; }
        .st-approved { background: #27ae60; color: white; }
        .st-rejected { background: #c0392b; color: white; }
        .st-deducted { background: #3498db; color: white; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 450px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 13px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .action-btn { text-decoration: none; padding: 5px 10px; border-radius: 3px; font-size: 12px; color: white; margin-right: 5px; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h2>Salary Advances</h2>
                <p style="color:#777; margin:0;">Manage employee cash advances.</p>
            </div>
            <button onclick="openModal()" class="btn-add"><i class="fas fa-hand-holding-usd"></i> New Request</button>
        </div>

        <?php echo $msg; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Amount (Rs)</th>
                    <th>Deduct Month</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($advances->num_rows > 0): ?>
                    <?php while($row = $advances->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['request_date']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                            <small style="color:#777;"><?php echo htmlspecialchars($row['designation']); ?></small>
                        </td>
                        <td style="font-weight:bold; color:#2c3e50;"><?php echo number_format($row['amount'], 2); ?></td>
                        <td><?php echo date('M Y', strtotime($row['deduction_month'])); ?></td>
                        <td><?php echo htmlspecialchars($row['reason']); ?></td>
                        <td><span class="st-badge st-<?php echo $row['status']; ?>"><?php echo strtoupper($row['status']); ?></span></td>
                        <td>
                            <?php if($row['status'] == 'pending'): ?>
                                <a href="?action=approve&id=<?php echo $row['id']; ?>" class="action-btn" style="background:#27ae60;" onclick="return confirm('Approve this advance?')">Approve</a>
                                <a href="?action=reject&id=<?php echo $row['id']; ?>" class="action-btn" style="background:#c0392b;" onclick="return confirm('Reject this request?')">Reject</a>
                            <?php elseif($row['status'] == 'approved'): ?>
                                <span style="font-size:11px; color:#27ae60;">Approved by <?php echo $row['approver']; ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding:20px; color:#aaa;">No advance requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="reqModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-top:0;">Request Salary Advance</h3>
            <form method="POST">
                <input type="hidden" name="action" value="request_advance">
                
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" required>
                        <option value="">-- Select Employee --</option>
                        <?php 
                        if ($employees->num_rows > 0) {
                            $employees->data_seek(0);
                            while($e = $employees->fetch_assoc()) {
                                echo "<option value='{$e['id']}'>{$e['full_name']} ({$e['nic_number']})</option>";
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (Rs)</label>
                    <input type="number" name="amount" step="0.01" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label>Deduction Month (Payroll)</label>
                    <input type="month" name="deduction_month" value="<?php echo date('Y-m'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" rows="2" placeholder="e.g. Medical emergency"></textarea>
                </div>

                <button type="submit" class="btn-add" style="width:100%;">Submit Request</button>
                <button type="button" onclick="document.getElementById('reqModal').style.display='none'" style="width:100%; margin-top:5px; background:#bdc3c7; border:none; padding:10px; border-radius:5px; cursor:pointer;">Cancel</button>
            </form>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function openModal() { document.getElementById('reqModal').style.display = 'flex'; }
        window.onclick = function(e) { if(e.target == document.getElementById('reqModal')) document.getElementById('reqModal').style.display = 'none'; }
    </script>
</body>
</html>