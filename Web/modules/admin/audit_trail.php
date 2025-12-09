<?php
require_once '../../config/db.php';

// Security Check - Admins Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit;
}

// Filters
$search = $_GET['search'] ?? '';
$sql = "
    SELECT a.*, u.username, u.role 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    WHERE u.username LIKE '%$search%' OR a.action LIKE '%$search%'
    ORDER BY a.created_at DESC 
    LIMIT 100
";
$logs = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Trail | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .search-box { padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 300px; }
        .data-table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th { background: #2c3e50; color: white; padding: 12px; text-align: left; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        .data-table tr:hover { background: #f9f9f9; }
        
        .action-tag { padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; background: #e8f4fd; color: #3498db; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div>
                <h2>System Audit Log</h2>
                <p style="color:#777; margin:0;">Track user activities and security events.</p>
            </div>
            <form method="GET">
                <input type="text" name="search" class="search-box" placeholder="Search User or Action..." value="<?php echo htmlspecialchars($search); ?>">
            </form>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if($logs && $logs->num_rows > 0): ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo $row['created_at']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['username'] ?? 'Unknown'); ?></strong><br>
                            <span style="font-size:11px; color:#aaa;"><?php echo htmlspecialchars($row['role'] ?? '-'); ?></span>
                        </td>
                        <td><span class="action-tag"><?php echo htmlspecialchars($row['action']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['details']); ?></td>
                        <td style="color:#777;"><?php echo $row['ip_address']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:20px;">No logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>