<?php
require_once '../../config/db.php';

$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['route_name'];
    $desc = $_POST['area_description'];

    $stmt = $conn->prepare("INSERT INTO routes (route_name, area_description) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $desc);
    
    if ($stmt->execute()) {
        $msg = "<p style='color:green;'>Route Added Successfully!</p>";
    }
}

$routes = $conn->query("SELECT * FROM routes");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Routes | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .form-container { background: white; padding: 20px; border-radius: 8px; max-width: 500px; margin-bottom: 20px; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0 15px 0; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Route Management</h2>
            <a href="index.php" style="color:#3498db;">&larr; Back</a>
        </div>

        <div class="form-container">
            <h3>Add New Route</h3>
            <?php echo $msg; ?>
            <form method="POST">
                <label>Route Name (e.g. Colombo - Kandy Rd)</label>
                <input type="text" name="route_name" required>

                <label>Area Description</label>
                <textarea name="area_description" placeholder="List main towns covered"></textarea>

                <button type="submit" style="background:#e67e22; color:white; padding:10px 20px; border:none; cursor:pointer;">Save Route</button>
            </form>
        </div>

        <h3>Active Routes</h3>
        <table style="width:100%; background:white; border-collapse:collapse;">
            <tr style="text-align:left; background:#eee;">
                <th style="padding:10px;">ID</th>
                <th style="padding:10px;">Route Name</th>
                <th style="padding:10px;">Description</th>
            </tr>
            <?php while($row = $routes->fetch_assoc()): ?>
            <tr>
                <td style="padding:10px; border-bottom:1px solid #ddd;"><?php echo $row['id']; ?></td>
                <td style="padding:10px; border-bottom:1px solid #ddd; font-weight:bold;"><?php echo htmlspecialchars($row['route_name']); ?></td>
                <td style="padding:10px; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($row['area_description']); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>