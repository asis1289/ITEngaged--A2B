<?php
// Start session for user authentication
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

// Check user type and redirect if not authorized
$user_type = $_SESSION['user_type'];
if (!in_array($user_type, ['system_admin', 'venue_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Database connection
require_once 'Connection/sql_auth.php';

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Validate input
$venue_id = filter_input(INPUT_POST, 'venue_id', FILTER_VALIDATE_INT);
if (!$venue_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid venue ID']);
    exit;
}

// Prepare and execute the delete query
$stmt = $db->prepare("DELETE FROM venues WHERE venue_id = ?");
$stmt->bind_param("i", $venue_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Venue deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Venue not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete venue: ' . $db->error]);
}

$stmt->close();
$db->close();
?>