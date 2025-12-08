<?php
require_once '../../config/db.php';

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category = "Sales";
    $amount = $_POST['amount'];
    $desc = $_POST['description']; // Could be Bill Number
    $date = $_POST['date'];

    $stmt = $conn->prepare("INSERT INTO transactions (trans_date, type, category, amount, description) VALUES (?, 'income', ?, ?, ?)");
    $stmt->bind_param("ssds", $date, $category, $amount, $desc);

    if ($stmt->execute()) {
        $msg = "<p style='color:green;'>Income Recorded Successfully!</p>";
    } else {
        $msg = "<p style='color:red;'>Error: " . $conn->error . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Income | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .form-box { background: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; }
        input, textarea { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Record Sales Income</h2>
            <a href="index.php" style="color:#3498db;">&larr; Back</a>
        </div>

        <div class="form-box">
            <?php echo $msg; ?>
            <form method="POST">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>

                <label>Reference / Bill No</label>
                <input type="text" name="description" placeholder="e.g. INV-2023-001 or Daily Route 1 Collection" required>

                <label>Total Amount Received (Rs)</label>
                <input type="number" step="0.01" name="amount" placeholder="0.00" required>

                <button type="submit" style="width:100%; background:#27ae60; color:white; padding:12px; border:none; cursor:pointer;">Record Income</button>
            </form>
        </div>
    </div>
</body>
</html>