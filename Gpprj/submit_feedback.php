<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'attendee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Database connection
require_once 'Connection/sql_auth.php';

$user_id = (int)$_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($event_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID or rating']);
    exit;
}

// Check if the user has already submitted feedback for this event
$checkQuery = "SELECT feedback_id FROM feedback WHERE user_id = ? AND event_id = ?";
$checkStmt = $db->prepare($checkQuery);
$checkStmt->bind_param("ii", $user_id, $event_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already submitted feedback for this event']);
    $checkStmt->close();
    $db->close();
    exit;
}
$checkStmt->close();

// Insert feedback into the database
$query = "INSERT INTO feedback (user_id, event_id, rating, comment, submitted_at, status) VALUES (?, ?, ?, ?, NOW(), 'unread')";
$stmt = $db->prepare($query);
$stmt->bind_param("iiis", $user_id, $event_id, $rating, $comment);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit feedback']);
}

$stmt->close();
$db->close();
?>