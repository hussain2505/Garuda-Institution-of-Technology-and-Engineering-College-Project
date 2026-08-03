<?php
/**
 * Database connection (PDO / MySQL via XAMPP)
 * Garuda Institute of Technology & Engineering College - ERP
 */

$DB_HOST = "localhost";
$DB_NAME = "gitec_erp";
$DB_USER = "root";
$DB_PASS = ""; // default XAMPP root password is blank; change for production

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements -> SQL injection protection
        ]
    );
} catch (PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die("Database connection failed. Please check config/database.php and make sure MySQL is running in XAMPP.");
}
