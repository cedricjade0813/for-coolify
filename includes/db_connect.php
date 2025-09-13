<?php
// Database connection for all includes
try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=clinic_management_system;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}
?>
