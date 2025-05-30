<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session for user authentication
session_start();

// Redirect to login if not authenticated or not a system admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'system_admin') {
    http_response_code(403); // Forbidden
    exit;
}

// Database connection
require_once 'Connection/sql_auth.php';

// Update all unread enquiries to read
$query = "UPDATE contact_inquiries SET status = 'read' WHERE status = 'unread'";
if ($db->query($query) === FALSE) {
    http_response_code(500); // Internal Server Error
    echo "Error updating enquiries: " . $db->error;
    exit;
}

$db->close();
?>