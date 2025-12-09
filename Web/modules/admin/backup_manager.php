<?php
require_once '../../config/db.php';

// Security Check - Admins Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit;
}

$msg = "";

// --- HANDLE BACKUP GENERATION ---
if (isset($_POST['action']) && $_POST['action'] == 'download_backup') {
    
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $sqlScript = "-- Delight ERP Database Backup\n";
    $sqlScript .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Structure
        $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
        $sqlScript .= "\n\n" . $row2[1] . ";\n\n";

        // Data
        $result = $conn->query("SELECT * FROM $table");
        $num_fields = $result->field_count;

        for ($i = 0; $i < $num_fields; $i++) {
            while ($row = $result->fetch_row()) {
                $sqlScript .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $num_fields; $j++) {
                    $row[$j] = $conn->real_escape_string($row[$j]);
                    if (isset($row[$j])) {
                        $sqlScript .= '"' . $row[$j] . '"';
                    } else {
                        $sqlScript .= '""';
                    }
                    if ($j < ($num_fields - 1)) {
                        $sqlScript .= ',';
                    }
                }
                $sqlScript .= ");\n";
            }
        }
    }
    
    $sqlScript .= "\nSET FOREIGN_KEY_CHECKS=1;";

    // Log the action
    log_audit($conn, $_SESSION['user_id'], 'BACKUP_DB', 'Downloaded full system backup');

    // Force Download
    $backup_name = "backup_delight_erp_" . date('Y-m-d_H-i') . ".sql";
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $backup_name . "\"");
    echo $sqlScript;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Backup Manager | Delight Dairy</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .backup-card { 
            background: white; 
            padding: 40px; 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); 
            text-align: center; 
            max-width: 500px; 
            margin: 50px auto; 
        }
        .backup-icon { font-size: 60px; color: #3498db; margin-bottom: 20px; }
        .btn-download { 
            background: #27ae60; 
            color: white; 
            padding: 15px 30px; 
            font-size: 18px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            text-decoration: none; 
            display: inline-block; 
            margin-top: 20px;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-download:hover { background: #219150; transform: translateY(-2px); }
        .warning-text { color: #e74c3c; font-size: 13px; margin-top: 15px; }
    </style>
</head>
<body>
    <?php include '../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar" style="margin-bottom:20px;">
            <h2>System Backup</h2>
        </div>

        <div class="backup-card">
            <i class="fas fa-database backup-icon"></i>
            <h2>Backup Database</h2>
            <p style="color:#666;">
                Create a full backup of the ERP system including all products, sales, inventory, and user data.
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="download_backup">
                <button type="submit" class="btn-download">
                    <i class="fas fa-download"></i> Download .SQL File
                </button>
            </form>

            <div class="warning-text">
                <i class="fas fa-shield-alt"></i> Store this file securely. It contains confidential company data.
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>