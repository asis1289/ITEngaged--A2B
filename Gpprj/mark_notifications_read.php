<?php
// Start session
session_start();

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Database connection
require_once 'Connection/sql_auth.php';

// Mark notifications as read based on user type
if ($user_type === 'venue_admin') {
    $query = "UPDATE notifications SET read_status = 1 WHERE venue_admin_id = ? AND read_status = 0 AND sender_type = 'system_admin'";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
} elseif ($user_type === 'system_admin') {
    $query = "UPDATE notifications SET read_status = 1 WHERE system_admin_id = ? AND read_status = 0 AND sender_type = 'venue_admin'";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

$db->close();
echo "Notifications marked as read";
?>