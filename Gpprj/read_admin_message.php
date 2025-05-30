<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once 'Connection/sql_auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get the reply_id from the POST data
if (!isset($_POST['reply_id']) || !is_numeric($_POST['reply_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid reply ID.']);
    exit;
}

$reply_id = (int)$_POST['reply_id'];

// Verify the reply belongs to the user or is a general reply (user_id IS NULL)
$checkQuery = "SELECT user_id, read_status FROM admin_replies WHERE reply_id = ?";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bind_param("i", $reply_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Reply not found.']);
    $checkStmt->close();
    $db->close();
    exit;
}

$reply = $checkResult->fetch_assoc();
if ($reply['user_id'] != $user_id && !is_null($reply['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access to this reply.']);
    $checkStmt->close();
    $db->close();
    exit;
}

// Check if the message is already read
if ($reply['read_status'] == 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Message already read.']);
    $checkStmt->close();
    $db->close();
    exit;
}

$checkStmt->close();

// Update the read_status to 1
$updateQuery = "UPDATE admin_replies SET read_status = 1 WHERE reply_id = ?";
$updateStmt = $db->prepare($updateQuery);
$updateStmt->bind_param("i", $reply_id);
if ($updateStmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Message marked as read.']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to mark message as read: ' . htmlspecialchars($db->error)]);
}

$updateStmt->close();
$db->close();
exit;
?>