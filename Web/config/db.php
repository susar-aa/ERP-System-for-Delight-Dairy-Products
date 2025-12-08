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
?>