<?php
session_start();

// Database connection
require_once 'Connection/sql_auth.php';

// Get the search term
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
if (empty($searchTerm)) {
    header("Location: index.php");
    exit;
}

// Search for events
$eventQuery = "SELECT event_id FROM events 
               WHERE (title LIKE ? OR description LIKE ? OR start_datetime LIKE ? OR booking_status LIKE ? OR status LIKE ? OR created_by_type LIKE ?)
               AND start_datetime > NOW() 
               AND status = 'approved'";
$stmt = $db->prepare($eventQuery);
$searchWildcard = "%$searchTerm%";
$stmt->bind_param("ssssss", $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard, $searchWildcard);
$stmt->execute();
$eventResult = $stmt->get_result();
$eventCount = $eventResult->num_rows;
$stmt->close();

// Search for venues
$venueQuery = "SELECT venue_id FROM venues WHERE name LIKE ? OR address LIKE ?";
$stmt = $db->prepare($venueQuery);
$stmt->bind_param("ss", $searchWildcard, $searchWildcard);
$stmt->execute();
$venueResult = $stmt->get_result();
$venueCount = $venueResult->num_rows;
$stmt->close();

// Redirect based on search results
if ($eventCount > 0) {
    // Redirect to booking.php with search term for events
    header("Location: booking.php?search=" . urlencode($searchTerm));
    exit;
} elseif ($venueCount > 0) {
    // Redirect to index.php venues section with search term
    header("Location: index.php?venue_search=" . urlencode($searchTerm) . "#venues-section");
    exit;
} else {
    // No results found, redirect back to index with a message
    header("Location: index.php?message=" . urlencode("No events or venues found for '$searchTerm'"));
    exit;
}

$db->close();
?>