<?php
require_once 'config/db.php';

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user = $conn->query("SELECT u.*, e.designation, e.phone, e.nic_number FROM users u LEFT JOIN employees e ON u.employee_id = e.id WHERE u.id = $user_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile | Delight Dairy</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-card {
            background: white;
            max-width: 600px;
            margin: 50px auto;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .p-header {
            background: #34495e;
            padding: 30px;
            text-align: center;
            color: white;
        }
        .p-avatar {
            font-size: 80px;
            color: #ecf0f1;
            margin-bottom: 10px;
        }
        .p-body { padding: 30px; }
        .p-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .p-label { font-weight: bold; color: #7f8c8d; }
        .p-val { font-weight: bold; color: #2c3e50; }
        
        .p-role-badge {
            background: #f39c12;
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="profile-card">
            <div class="p-header">
                <i class="fas fa-user-circle p-avatar"></i>
                <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <span class="p-role-badge"><?php echo $user['role']; ?></span>
            </div>
            <div class="p-body">
                <div class="p-row">
                    <span class="p-label">Username</span>
                    <span class="p-val"><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="p-row">
                    <span class="p-label">Designation</span>
                    <span class="p-val"><?php echo htmlspecialchars($user['designation'] ?? 'N/A'); ?></span>
                </div>
                <div class="p-row">
                    <span class="p-label">Phone</span>
                    <span class="p-val"><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></span>
                </div>
                <div class="p-row">
                    <span class="p-label">NIC Number</span>
                    <span class="p-val"><?php echo htmlspecialchars($user['nic_number'] ?? '-'); ?></span>
                </div>
                <div class="p-row">
                    <span class="p-label">Account Status</span>
                    <span class="p-val" style="color:green;">Active</span>
                </div>
                
                <div style="margin-top:30px; text-align:center;">
                    <a href="dashboard.php" style="color:#3498db; text-decoration:none;">&larr; Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>