<?php
require_once '../../config/db.php';

$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shop = $_POST['shop_name'];
    $owner = $_POST['owner_name'];
    $phone = $_POST['phone'];
    $addr = $_POST['address'];
    $rid = $_POST['route_id'];

    $stmt = $conn->prepare("INSERT INTO customers (shop_name, owner_name, phone, address, route_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $shop, $owner, $phone, $addr, $rid);
    
    if ($stmt->execute()) {
        $msg = "<p style='color:green;'>Customer Added Successfully!</p>";
    }
}

$routes = $conn->query("SELECT * FROM routes");
$customers = $conn->query("SELECT c.*, r.route_name FROM customers c LEFT JOIN routes r ON c.route_id = r.id ORDER BY c.id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Customers | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .form-container { background: white; padding: 20px; border-radius: 8px; max-width: 600px; margin-bottom: 20px; }
        .row { display:flex; gap:10px; }
        input, select, textarea { width: 100%; padding: 8px; margin: 5px 0 15px 0; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h2>Customer Database (Shops)</h2>
            <a href="index.php" style="color:#3498db;">&larr; Back</a>
        </div>

        <div class="form-container">
            <h3>Register New Shop</h3>
            <?php echo $msg; ?>
            <form method="POST">
                <label>Shop Name</label>
                <input type="text" name="shop_name" required>

                <div class="row">
                    <div style="flex:1;">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name">
                    </div>
                    <div style="flex:1;">
                        <label>Phone Number</label>
                        <input type="text" name="phone">
                    </div>
                </div>

                <label>Assigned Route</label>
                <select name="route_id" required>
                    <?php while($r = $routes->fetch_assoc()): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo $r['route_name']; ?></option>
                    <?php endwhile; ?>
                </select>

                <label>Address</label>
                <textarea name="address"></textarea>

                <button type="submit" style="background:#16a085; color:white; padding:10px 20px; border:none; cursor:pointer;">Save Customer</button>
            </form>
        </div>

        <h3>Registered Shops</h3>
        <table style="width:100%; background:white; border-collapse:collapse;">
            <tr style="text-align:left; background:#eee;">
                <th style="padding:10px;">Shop Name</th>
                <th style="padding:10px;">Route</th>
                <th style="padding:10px;">Phone</th>
                <th style="padding:10px;">Address</th>
            </tr>
            <?php while($row = $customers->fetch_assoc()): ?>
            <tr>
                <td style="padding:10px; border-bottom:1px solid #ddd; font-weight:bold;"><?php echo htmlspecialchars($row['shop_name']); ?></td>
                <td style="padding:10px; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($row['route_name']); ?></td>
                <td style="padding:10px; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($row['phone']); ?></td>
                <td style="padding:10px; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($row['address']); ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>