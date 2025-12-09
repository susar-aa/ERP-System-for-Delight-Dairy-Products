<?php
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../index.php"); exit; }

$msg = "";

// ADD / EDIT REP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $user_id = intval($_POST['user_id']);
    $vehicle = $_POST['vehicle_no'];
    $route = intval($_POST['route_id']);
    
    if ($_POST['action'] == 'add') {
        // Ensure user isn't already a rep
        $check = $conn->query("SELECT id FROM sales_reps WHERE user_id = $user_id");
        if ($check->num_rows > 0) {
            $msg = "<div class='alert error'>This user is already a Sales Rep.</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO sales_reps (user_id, vehicle_no, assigned_route_id) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $user_id, $vehicle, $route);
            if ($stmt->execute()) $msg = "<div class='alert success'>Sales Rep Added!</div>";
            else $msg = "<div class='alert error'>Error: " . $conn->error . "</div>";
        }
    } elseif ($_POST['action'] == 'edit') {
        $id = intval($_POST['rep_id']);
        $stmt = $conn->prepare("UPDATE sales_reps SET vehicle_no=?, assigned_route_id=? WHERE id=?");
        $stmt->bind_param("sii", $vehicle, $route, $id);
        if ($stmt->execute()) $msg = "<div class='alert success'>Rep Updated!</div>";
    }
}

// DELETE REP
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM sales_reps WHERE id=$id");
    $msg = "<div class='alert success'>Rep Removed.</div>";
}

// FETCH DATA
$reps = $conn->query("SELECT sr.*, u.full_name, u.username, r.route_name FROM sales_reps sr JOIN users u ON sr.user_id = u.id LEFT JOIN routes r ON sr.assigned_route_id = r.id");
$users = $conn->query("SELECT id, full_name, username FROM users WHERE id NOT IN (SELECT user_id FROM sales_reps)"); // Available users
$routes = $conn->query("SELECT * FROM routes");
$route_list = []; while($r = $routes->fetch_assoc()) $route_list[] = $r;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Reps | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-add { background: #8e44ad; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; }
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #34495e; color: white; padding: 12px; text-align: left; }
        .data-table td { padding: 12px; border-bottom: 1px solid #eee; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 25px; border-radius: 8px; width: 450px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar">
            <h2>Sales Representatives</h2>
            <div style="display:flex; gap:10px;">
                <a href="manage_routes.php" class="btn-add" style="background:#95a5a6;">Back to Routes</a>
                <button onclick="openModal('add')" class="btn-add"><i class="fas fa-user-plus"></i> Assign New Rep</button>
            </div>
        </div>
        <?php echo $msg; ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Vehicle No</th>
                    <th>Assigned Route</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $reps->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['vehicle_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['route_name']); ?></td>
                    <td>
                        <button onclick='openModal("edit", <?php echo json_encode($row); ?>)' style="border:none; background:none; color:#f39c12; cursor:pointer;"><i class="fas fa-edit"></i></button>
                        <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('Remove rep?')" style="color:#c0392b; margin-left:10px;"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- MODAL -->
    <div id="repModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Assign Sales Rep</h3>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="rep_id" id="repId">
                <div class="form-group" id="userSelectGroup">
                    <label>Select User (Staff)</label>
                    <select name="user_id" id="userId" required>
                        <option value="">-- Choose User --</option>
                        <?php while($u = $users->fetch_assoc()): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo $u['full_name']; ?> (<?php echo $u['username']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Vehicle Number</label>
                    <input type="text" name="vehicle_no" id="vehicle" placeholder="e.g. WP CA-1234">
                </div>
                <div class="form-group">
                    <label>Default Route</label>
                    <select name="route_id" id="routeId" required>
                        <option value="">-- Select Route --</option>
                        <?php foreach($route_list as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo $r['route_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-add" style="width:100%;">Save</button>
                <button type="button" onclick="document.getElementById('repModal').style.display='none'" style="width:100%; margin-top:5px; background:#ccc; border:none; padding:10px; border-radius:5px; cursor:pointer;">Cancel</button>
            </form>
        </div>
    </div>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function openModal(mode, data=null) {
            document.getElementById('repModal').style.display = 'flex';
            if(mode == 'edit') {
                document.getElementById('modalTitle').innerText = 'Edit Rep Details';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('repId').value = data.id;
                document.getElementById('vehicle').value = data.vehicle_no;
                document.getElementById('routeId').value = data.assigned_route_id;
                document.getElementById('userSelectGroup').style.display = 'none'; // Can't change user once assigned
            } else {
                document.getElementById('modalTitle').innerText = 'Assign New Rep';
                document.getElementById('formAction').value = 'add';
                document.getElementById('vehicle').value = '';
                document.getElementById('routeId').value = '';
                document.getElementById('userSelectGroup').style.display = 'block';
            }
        }
    </script>
</body>
</html>