<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'Connection/sql_auth.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$query = "SELECT user_type FROM users WHERE user_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user['user_type'] !== 'system_admin' && $user['user_type'] !== 'venue_admin') {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient permissions']);
    exit;
}

if (!isset($_POST['booking_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Booking ID not provided']);
    exit;
}

// Extract numeric booking_id from the input
$booking_id_input = $_POST['booking_id'];
$booking_id = null;

if (is_numeric($booking_id_input)) {
    $booking_id = (int)$booking_id_input;
} else {
    preg_match('/\d+/', $booking_id_input, $matches);
    if (!empty($matches)) {
        $booking_id = (int)$matches[0];
    }
}

if ($booking_id === null) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID format: ' . htmlspecialchars($booking_id_input)]);
    exit;
}

$query = "SELECT b.check_in_status, b.status, b.ticket_quantity, b.user_id, b.unreg_user_id, 
                 e.title AS event_title, e.start_datetime, e.venue_id,
                 COALESCE(u.full_name, CONCAT(ur.first_name, ' ', ur.last_name)) AS attendee_name
          FROM bookings b
          LEFT JOIN events e ON b.event_id = e.event_id
          LEFT JOIN users u ON b.user_id = u.user_id
          LEFT JOIN unregisterusers ur ON b.unreg_user_id = ur.unreg_user_id
          WHERE b.booking_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Booking not found for ID: ' . $booking_id]);
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

error_log("Event venue_id for booking {$booking_id}: " . $booking['venue_id']);

if ($booking['status'] !== 'confirmed') {
    echo json_encode(['status' => 'error', 'message' => 'Booking not confirmed']);
    exit;
}

if ($booking['check_in_status'] == 1) {
    echo json_encode([
        'status' => 'error',
        'message' => "Ticket already checked in for {$booking['attendee_name']} ({$booking['ticket_quantity']} tickets) at {$booking['event_title']}"
    ]);
    exit;
}

$current_time = time();
$event_time = strtotime($booking['start_datetime']);
$time_diff_hours = ($current_time - $event_time) / 3600;
$current_date = date('Y-m-d');
$event_date = date('Y-m-d', $event_time);

if ($event_date > $current_date) {
    echo json_encode(['status' => 'error', 'message' => 'This event not happening today!!']);
    exit;
}

if ($event_date === $current_date && $time_diff_hours > -4) {
    echo json_encode(['status' => 'error', 'message' => 'The check-in for this has not been started, please come back later!!!']);
    exit;
}

if ($time_diff_hours > 24) {
    echo json_encode(['status' => 'error', 'message' => 'Event has ended']);
    exit;
}

$query = "UPDATE bookings SET check_in_status = 1 WHERE booking_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $booking_id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => "Checked in: {$booking['attendee_name']} ({$booking['ticket_quantity']} tickets) for {$booking['event_title']}"
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to check in: ' . $db->error]);
}

$stmt->close();
$db->close();
?>