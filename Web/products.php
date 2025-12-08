<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==========================================
// ROBUST DATABASE CONNECTION
// ==========================================
$db_file = __DIR__ . '/db.php';
if (file_exists($db_file)) {
    require_once $db_file;
}

// FALLBACK: If $conn is not defined (e.g. db.php issue), connect manually
if (!isset($conn)) {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "manufacturing_db";
    $conn = new mysqli($servername, $username, $password, $dbname);
}

// Verify Connection
if ($conn->connect_error) {
    die("<h2>Database Connection Failed</h2>" . 
        "<p>Error: " . $conn->connect_error . "</p>");
}

// HANDLE FORM SUBMISSION: ADD PRODUCT (FINISHED GOODS ONLY)
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_product') {
    $sku = $_POST['sku'];
    $name = $_POST['name'];
    $type = 'finished_good'; // Hardcoded for this page
    $uom = $_POST['base_uom'];
    // Cost price might be calculated from BOM later, but allowed here for manual entry
    $cost = !empty($_POST['cost_price']) ? $_POST['cost_price'] : 0; 
    $price = !empty($_POST['sales_price']) ? $_POST['sales_price'] : 0;

    $stmt = $conn->prepare("INSERT INTO products (sku, name, type, base_uom, cost_price, sales_price) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssdd", $sku, $name, $type, $uom, $cost, $price);

    if ($stmt->execute()) {
        $message = "<div style='background:#dcfce7; color:#166534; padding:10px; border-radius:4px; margin-bottom:20px;'>✅ Finished Good '$name' added successfully!</div>";
    } else {
        $message = "<div style='background:#fee2e2; color:#991b1b; padding:10px; border-radius:4px; margin-bottom:20px;'>❌ Error: " . $conn->error . "</div>";
    }
    $stmt->close();
}

// HANDLE DELETION
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM products WHERE product_id = $id");
    header("Location: products.php"); // Redirect to clear URL parameters
    exit;
}

// ==========================================
// FETCH ONLY FINISHED GOODS (FIXED QUERY)
// ==========================================
// We now use a direct LEFT JOIN instead of the VIEW to ensure we get all columns (like sales_price)
$sql = "
    SELECT 
        p.*, 
        COALESCE(SUM(t.quantity), 0) as current_stock
    FROM products p
    LEFT JOIN inventory_transactions t ON p.product_id = t.product_id
    WHERE p.type = 'finished_good'
    GROUP BY p.product_id
    ORDER BY p.product_id DESC
";

$products = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finished Goods - Manufacturing ERP</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Page Specific Styles */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .full-width { grid-column: span 2; }
    </style>
</head>
<body>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-bar">
            <h1>Finished Goods Management</h1>
        </div>

        <?php echo $message; ?>

        <div class="grid-split" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            
            <!-- ADD FINISHED GOOD FORM -->
            <div class="card">
                <h2>Add Finished Good</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_product">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>SKU (Unique ID)</label>
                            <input type="text" name="sku" required placeholder="e.g. FG-100" style="width:100%; padding:8px; margin-top:5px;">
                        </div>
                        
                        <!-- Type is hidden/automatic now -->
                        <div class="form-group">
                            <label>Type</label>
                            <input type="text" value="Finished Good" disabled style="width:100%; padding:8px; margin-top:5px; background:#f0f0f0; color:#888;">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Product Name</label>
                            <input type="text" name="name" required placeholder="e.g. Metal Box Container" style="width:100%; padding:8px; margin-top:5px;">
                        </div>

                        <div class="form-group">
                            <label>Unit (UOM)</label>
                            <input type="text" name="base_uom" required placeholder="pcs, box, set" style="width:100%; padding:8px; margin-top:5px;">
                        </div>
                        <div class="form-group">
                            <label>Standard Cost</label>
                            <input type="number" step="0.01" name="cost_price" placeholder="0.00" style="width:100%; padding:8px; margin-top:5px;">
                        </div>
                         <div class="form-group full-width">
                            <label>Selling Price (MSRP)</label>
                            <input type="number" step="0.01" name="sales_price" placeholder="0.00" style="width:100%; padding:8px; margin-top:5px;">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" style="width:100%; margin-top:15px;">Create Finished Good</button>
                </form>
            </div>

            <!-- FINISHED GOODS LIST -->
            <div class="card">
                <h2>Selling Products Registry</h2>
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products && $products->num_rows > 0): ?>
                            <?php while($row = $products->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['sku']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td>$<?php echo number_format((float)$row['sales_price'], 2); ?></td>
                                <td>
                                    <?php 
                                    $stock = $row['current_stock'];
                                    $color = $stock > 0 ? 'green' : 'red';
                                    echo "<span style='color:$color; font-weight:bold;'>" . number_format($stock, 2) . "</span>"; 
                                    ?>
                                </td>
                                <td>
                                    <a href="?delete=<?php echo $row['product_id']; ?>" 
                                       onclick="return confirm('Delete this product?')"
                                       style="color:red; text-decoration:none;">Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No finished goods found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</body>
</html>