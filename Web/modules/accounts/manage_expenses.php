<?php
require_once '../../config/db.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $desc = $_POST['description'];
    $date = $_POST['date'];

    $stmt = $conn->prepare("INSERT INTO transactions (trans_date, type, category, amount, description) VALUES (?, 'expense', ?, ?, ?)");
    $stmt->bind_param("ssds", $date, $category, $amount, $desc);

    if ($stmt->execute()) {
        $msg = "<p style='color:green;'>Expense Recorded Successfully!</p>";
    } else {
        $msg = "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Expenses | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .form-box { background: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; }
        input, select, textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Record Operational Expense</h2>
            <a href="index.php" style="color:#3498db;">&larr; Back</a>
        </div>

        <div class="form-box">
            <?php echo $msg; ?>
            <form method="POST">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>

                <label>Category</label>
                <select name="category">
                    <option value="Utilities">Utilities (Electricity/Water)</option>
                    <option value="Transport">Transport / Fuel</option>
                    <option value="Maintenance">Maintenance & Repairs</option>
                    <option value="Sales Return">Sales Return (Refund)</option>
                    <option value="Payroll">Salary Payment</option>
                    <option value="Petty Cash">Petty Cash / Sundry</option>
                </select>

                <label>Amount (Rs)</label>
                <input type="number" step="0.01" name="amount" placeholder="0.00" required>

                <label>Description / Note</label>
                <textarea name="description" placeholder="e.g. Paid for machine repair"></textarea>

                <button type="submit" style="width:100%; background:#c0392b; color:white; padding:12px; border:none; cursor:pointer;">Save Expense</button>
            </form>
        </div>
    </div>
</body>
</html>