<?php
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../index.php"); exit; }

$msg = "";

// ADD / EDIT CUSTOMER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $name = $_POST['shop_name'];
    $owner = $_POST['owner_name'];
    $phone = $_POST['phone'];
    $addr = $_POST['address'];
    $route = intval($_POST['route_id']);
    
    if ($_POST['action'] == 'add') {
        $stmt = $conn->prepare("INSERT INTO customers (shop_name, owner_name, phone, address, route_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $owner, $phone, $addr, $route);
        if ($stmt->execute()) $msg = "<div class='alert success'>Customer Added!</div>";
        else $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
    } elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['cust_id']);
        $stmt = $conn->prepare("UPDATE customers SET shop_name=?, owner_name=?, phone=?, address=?, route_id=? WHERE id=?");
        $stmt->bind_param("ssssii", $name, $owner, $phone, $addr, $route, $id);
        if ($stmt->execute()) $msg = "<div class='alert success'>Customer Updated!</div>";
    }
}

// DELETE
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM customers WHERE id=$id");
    $msg = "<div class='alert success'>Customer Deleted.</div>";
}

$routes = $conn->query("SELECT * FROM routes");
$route_list = []; while($r = $routes->fetch_assoc()) $route_list[] = $r;

// FILTER
$search = $_GET['search'] ?? '';
$sql = "SELECT c.*, r.route_name FROM customers c LEFT JOIN routes r ON c.route_id = r.id WHERE c.shop_name LIKE '%$search%' OR c.phone LIKE '%$search%' ORDER BY c.shop_name";
$customers = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Customers | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add { background: #16a085; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; }
        .search-box { padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #34495e; color: white; padding: 12px; text-align: left; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 500px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing:border-box; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar">
            <h2>Customer Database (Shops)</h2>
            <div style="display:flex; gap:10px;">
                <form method="GET" style="display:flex;">
                    <input type="text" name="search" class="search-box" placeholder="Search Shop Name or Phone..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" style="background:#34495e; color:white; border:none; padding:0 15px; cursor:pointer;"><i class="fas fa-search"></i></button>
                </form>
                <button onclick="openModal('add')" class="btn-add"><i class="fas fa-plus"></i> Add Customer</button>
            </div>
        </div>
        <?php echo $msg; ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Shop Name</th>
                    <th>Owner</th>
                    <th>Contact</th>
                    <th>Assigned Route</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $customers->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['shop_name']); ?></strong><br><small style="color:#777;"><?php echo htmlspecialchars($row['address']); ?></small></td>
                    <td><?php echo htmlspecialchars($row['owner_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                    <td><?php echo htmlspecialchars($row['route_name']); ?></td>
                    <td>
                        <button onclick='openModal("edit", <?php echo json_encode($row); ?>)' style="border:none; background:none; color:#f39c12; cursor:pointer;"><i class="fas fa-edit"></i></button>
                        <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Delete customer?')" style="color:#c0392b; margin-left:10px;"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="custModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Add Customer</h3>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="cust_id" id="custId">
                
                <div class="form-group">
                    <label>Shop Name</label>
                    <input type="text" name="shop_name" id="shopName" required>
                </div>
                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" id="ownerName">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Phone</label>
                        <input type="text" name="phone" id="phone">
                    </div>
                </div>
                <div class="form-group">
                    <label>Route</label>
                    <select name="route_id" id="routeId" required>
                        <option value="">-- Select Route --</option>
                        <?php foreach($route_list as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo $r['route_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="address" rows="2"></textarea>
                </div>
                
                <button type="submit" class="btn-add" style="width:100%;">Save</button>
                <button type="button" onclick="document.getElementById('custModal').style.display='none'" style="width:100%; margin-top:5px; background:#ccc; border:none; padding:10px; border-radius:5px; cursor:pointer;">Cancel</button>
            </form>
        </div>
    </div>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function openModal(mode, data=null) {
            document.getElementById('custModal').style.display = 'flex';
            if(mode == 'edit') {
                document.getElementById('modalTitle').innerText = 'Edit Customer';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('custId').value = data.id;
                document.getElementById('shopName').value = data.shop_name;
                document.getElementById('ownerName').value = data.owner_name;
                document.getElementById('phone').value = data.phone;
                document.getElementById('routeId').value = data.route_id;
                document.getElementById('address').value = data.address;
            } else {
                document.getElementById('modalTitle').innerText = 'Add Customer';
                document.getElementById('formAction').value = 'add';
                document.getElementById('shopName').value = '';
                document.getElementById('ownerName').value = '';
                document.getElementById('phone').value = '';
                document.getElementById('address').value = '';
            }
        }
    </script>
</body>
</html>