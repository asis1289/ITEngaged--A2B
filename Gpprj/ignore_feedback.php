<?php
session_start();

// Check if user_id and event_id are set
if (!isset($_SESSION['user_id']) || !isset($_POST['event_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'];

// Database connection
require_once 'Connection/sql_auth.php';

// Insert a new row with NULL values and is_ignored = 1
$insertQuery = "INSERT INTO feedback (user_id, event_id, rating, comment, is_ignored, submitted_at) VALUES (?, ?, NULL, NULL, 1, NOW())";
$stmt = $db->prepare($insertQuery);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Insert prepare failed: ' . $db->error]);
    $db->close();
    exit;
}
$stmt->bind_param("ii", $user_id, $event_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Execute failed: ' . $stmt->error]);
}

$stmt->close();
$db->close();
?>