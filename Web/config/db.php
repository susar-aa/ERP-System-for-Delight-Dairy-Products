<?php
/*
 * Database Connection for Delight Dairy Products ERP
 * Database: MySQL via XAMPP
 */

$host = 'localhost';
$user = 'root';      // Default XAMPP user
$pass = '';          // Default XAMPP password is empty
$dbname = 'delight_erp';

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set charset to handle special characters correctly
$conn->set_charset("utf8");

// Start Session globally here so we don't forget it on other pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Global Helper: Log User Activity
 * Usage: log_audit($conn, $user_id, 'ACTION_NAME', 'Details of action');
 */
function log_audit($conn, $user_id, $action, $details = '') {
    // If the audit_logs table doesn't exist yet, this prevents a crash
    $table_check = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    if($table_check->num_rows == 0) return;

    $ip = $_SERVER['REMOTE_ADDR'];
    // Use try-catch to prevent crashing main app if logging fails
    try {
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $action, $details, $ip);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        // Silently fail logging to not disrupt user flow
        error_log("Audit Log Error: " . $e->getMessage());
    }
}
?>