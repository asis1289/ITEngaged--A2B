<?php
session_start();

// Enable error reporting for debugging (commented out for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection 
require_once 'Connection/sql_auth.php';

// Set timezone to AEST
date_default_timezone_set('Australia/Sydney');

// Initialize notification preference if not set
if (!isset($_SESSION['notification_preference'])) {
    $_SESSION['notification_preference'] = 'opt-in'; // Default to opt-in
}

// Fetch all approved events with ticket prices set by system_admin or venue_admin
$featuredEvents = [];
$query = "SELECT e.*, e.booking_status, v.name as venue_name, v.address, tp.ticket_price, tp.set_by_type 
          FROM events e 
          JOIN venues v ON e.venue_id = v.venue_id 
          JOIN ticket_prices tp ON e.event_id = tp.event_id 
          WHERE e.start_datetime > NOW() 
          AND e.status = 'approved' 
          AND tp.ticket_price IS NOT NULL 
          AND tp.set_by_type IN ('system_admin', 'venue_admin')
          ORDER BY e.start_datetime";
$result = $db->query($query);
if ($result === false) {
    error_log("Featured events query failed: " . $db->error);
} else {
    $featuredEvents = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}

// Fetch all venues for the "Explore Our Venues" section
$venues = [];
$venueQuery = "SELECT * FROM venues ORDER BY name";
$venueResult = $db->query($venueQuery);
if ($venueResult) {
    $venues = $venueResult->fetch_all(MYSQLI_ASSOC);
    $venueResult->free();
}

// Fetch unread message count for the logged-in user
$unreadMessageCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $unreadQuery = "SELECT COUNT(*) as unread_count 
                    FROM admin_replies 
                    WHERE (user_id = ? OR user_id IS NULL) 
                    AND read_status = 0";
    $stmt = $db->prepare($unreadQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unreadResult = $stmt->get_result();
    if ($unreadResult) {
        $unreadData = $unreadResult->fetch_assoc();
        $unreadMessageCount = $unreadData['unread_count'];
    }
    $stmt->close();
}

// Fetch unread notification count for new approved events since last notification_view
$unreadNotificationCount = 0;
if (isset($_SESSION['user_id']) && $_SESSION['notification_preference'] === 'opt-in') {
    $user_id = $_SESSION['user_id'];
    $notificationQuery = "SELECT COUNT(*) as notification_count 
                         FROM events e 
                         WHERE e.start_datetime > NOW() 
                         AND e.status = 'approved' 
                         AND e.created_by_type IN ('system_admin', 'venue_admin') 
                         AND e.created_at > (SELECT COALESCE(notifications_viewed, '1970-01-01 00:00:00') 
                                             FROM users 
                                             WHERE user_id = ?)";
    $stmt = $db->prepare($notificationQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notificationResult = $stmt->get_result();
    if ($notificationResult) {
        $notificationData = $notificationResult->fetch_assoc();
        $unreadNotificationCount = $notificationData['notification_count'];
    }
    $stmt->close();
}

// Fetch unread enquiries count for system_admin
$unreadEnquiryCount = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'system_admin') {
    $user_id = $_SESSION['user_id'];
    $enquiryQuery = "SELECT COUNT(*) as unread_count 
                     FROM contact_inquiries 
                     WHERE status = 'unread'";
    $result = $db->query($enquiryQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $unreadEnquiryCount = $row['unread_count'];
    } else {
        error_log("Enquiry query failed: " . $db->error);
    }
}

// Fetch notifications for system_admin and venue_admin
$unread_notifications = [];
$unread_count = 0;
$past_notifications = [];
$pending_events = [];
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['system_admin', 'venue_admin'])) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];

    // Fetch count of pending events (for system admin)
    $pending_count = 0;
    if ($user_type === 'system_admin') {
        $query = "SELECT COUNT(*) as count FROM events WHERE status = 'pending' AND created_by_type = 'venue_admin'";
        $result = $db->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            $pending_count = $row['count'];
        }
    }

    // Fetch notifications
    if ($user_type === 'venue_admin') {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE venue_admin_id = ? AND read_status = 0 AND sender_type = 'system_admin'";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $unread_count = $row['count'];
        }
        $stmt->close();

        $query = "SELECT message, created_at FROM notifications WHERE venue_admin_id = ? AND read_status = 0 AND sender_type = 'system_admin' ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $unread_notifications[] = $row['message'] . ' (Received: ' . date('F j, Y g:i A', strtotime($row['created_at'])) . ')';
        }
        $stmt->close();

        $query = "SELECT message, created_at FROM notifications WHERE venue_admin_id = ? AND read_status = 1 AND sender_type = 'system_admin' ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $past_notifications[] = $row['message'] . ' (Received: ' . date('F j, Y g:i A', strtotime($row['created_at'])) . ')';
        }
        $stmt->close();
    } elseif ($user_type === 'system_admin') {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE system_admin_id = ? AND read_status = 0 AND sender_type = 'venue_admin'";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $unread_count = $row['count'];
        }
        $stmt->close();

        $query = "SELECT n.message, n.created_at, n.venue_admin_id, u.full_name, u.email, u.phone_num 
                  FROM notifications n 
                  JOIN users u ON n.venue_admin_id = u.user_id 
                  WHERE n.system_admin_id = ? AND n.read_status = 0 AND n.sender_type = 'venue_admin' 
                  ORDER BY n.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notification_text = $row['message'] . ' (From: ' . htmlspecialchars($row['full_name']) . ', Received: ' . date('F j, Y g:i A', strtotime($row['created_at'])) . ')';
            $notification_text .= '<br>Email: ' . htmlspecialchars($row['email']) . ', Phone: ' . htmlspecialchars($row['phone_num'] ?? 'N/A');
            $unread_notifications[] = $notification_text;
        }
        $stmt->close();

        $query = "SELECT n.message, n.created_at, n.venue_admin_id, u.full_name, u.email, u.phone_num 
                  FROM notifications n 
                  JOIN users u ON n.venue_admin_id = u.user_id 
                  WHERE n.system_admin_id = ? AND n.read_status = 1 AND n.sender_type = 'venue_admin' 
                  ORDER BY n.created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $notification_text = $row['message'] . ' (From: ' . htmlspecialchars($row['full_name']) . ', Received: ' . date('F j, Y g:i A', strtotime($row['created_at'])) . ')';
            $notification_text .= '<br>Email: ' . htmlspecialchars($row['email']) . ', Phone: ' . htmlspecialchars($row['phone_num'] ?? 'N/A');
            $past_notifications[] = $notification_text;
        }
        $stmt->close();

        if ($pending_count > 0) {
            $query = "SELECT e.event_id, e.title as event_name, e.created_at, u.full_name, u.email, u.phone_num
                      FROM events e 
                      JOIN users u ON e.created_by_id = u.user_id 
                      WHERE e.status = 'pending' AND e.created_by_type = 'venue_admin'";
            $result = $db->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $pending_events[] = 'Event: ' . htmlspecialchars($row['event_name']) . ' (Created by: ' . htmlspecialchars($row['full_name']) . ', On: ' . date('F j, Y g:i A', strtotime($row['created_at'])) . ')'
                        . '<br>Email: ' . htmlspecialchars($row['email']) . ', Phone: ' . htmlspecialchars($row['phone_num'] ?? 'N/A');
                }
            }
        }
    }
}

// Check for events that have started for feedback (for attendees only)
$recentEventForFeedback = null;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'attendee') {
    $user_id = $_SESSION['user_id'];
    $current_time = date('Y-m-d H:i:s', strtotime('Australia/Sydney')); // 03:45 PM AEST, May 23, 2025
    $query = "SELECT e.event_id, e.title, e.start_datetime 
              FROM bookings b 
              JOIN events e ON b.event_id = e.event_id 
              WHERE b.user_id = ? 
              AND b.status = 'confirmed' 
              AND e.start_datetime <= ? 
              AND NOT EXISTS (
                  SELECT 1 
                  FROM feedback f 
                  WHERE f.user_id = b.user_id 
                  AND f.event_id = b.event_id
                  AND (f.rating IS NOT NULL OR f.comment IS NOT NULL OR f.is_ignored = 1)
              )
              AND e.event_id NOT IN (
                  SELECT event_id 
                  FROM feedback 
                  WHERE user_id = ? 
                  AND is_ignored = 1
              )
              ORDER BY e.start_datetime DESC 
              LIMIT 1";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->bind_param("isi", $user_id, $current_time, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $recentEventForFeedback = $result->fetch_assoc();
            error_log("Feedback popup shown for event_id: {$recentEventForFeedback['event_id']}, title: {$recentEventForFeedback['title']}");
        } else {
            // Debug: Check all feedback entries for this user
            $debugQuery = "SELECT f.event_id, f.rating, f.comment, f.is_ignored, e.title 
                           FROM feedback f 
                           LEFT JOIN events e ON f.event_id = e.event_id 
                           WHERE f.user_id = ? 
                           AND f.event_id IN (SELECT event_id FROM bookings WHERE user_id = ? AND status = 'confirmed')";
            $debugStmt = $db->prepare($debugQuery);
            $debugStmt->bind_param("ii", $user_id, $user_id);
            $debugStmt->execute();
            $debugResult = $debugStmt->get_result();
            if ($debugResult->num_rows > 0) {
                error_log("Feedback entries for user_id $user_id:");
                while ($row = $debugResult->fetch_assoc()) {
                    error_log("event_id: {$row['event_id']}, title: {$row['title']}, rating: {$row['rating']}, comment: {$row['comment']}, is_ignored: {$row['is_ignored']}");
                }
            } else {
                error_log("No feedback entries found for user_id $user_id");
            }
            $debugStmt->close();
        }
        $stmt->close();
    } else {
        error_log("Query preparation failed: " . $db->error);
    }
}

// Get search term from URL for venue highlighting
$venueSearchTerm = isset($_GET['venue_search']) ? trim($_GET['venue_search']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventHub - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --message-color: #28a745;
            --notification-color: #ff9500;
            --disabled-color: #666;
            --messenger-blue: #0084FF;
            --notification-bg: #dc3545;
            --highlight-bg: #ffeb3b;
            --highlight-color: #000;
            --star-color: #ffd700;
            --success-color: #28a745;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('images/concert.jpg');
            background-size: cover;
            background-position: center;
            backdrop-filter: blur(5px);
            color: var(--light-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        header {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 120px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            position: relative;
        }

        .logo img {
            height: 120px;
            max-width: 100%;
            vertical-align: middle;
            transition: transform 0.2s ease;
        }

        .logo img:hover {
            transform: scale(1.15);
        }

        .search-bar {
            position: absolute;
            top: 10px;
            right: 200px;
            flex: 1 1 auto;
            min-width: 150px;
            max-width: 180px;
            display: flex;
            align-items: center;
        }

        .search-bar input[type="text"] {
            width: 100%;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            border: none;
            outline: none;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.2);
            color: var(--light-color);
        }

        .search-bar input[type="text"]::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .search-bar button {
            background: var(--primary-color);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            margin-left: 0.3rem;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 0.85rem;
        }

        .search-bar button i {
            color: white;
            font-size: 0.9rem;
        }

        .search-bar button:hover {
            background: #5a3de6;
        }

        .nav-links {
            display: flex;
            list-style: none;
            align-items: center;
        }

        .nav-links li {
            margin-left: 1.5rem;
            position: relative;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .notification-icon {
            color: white;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }

        .notification-icon:hover {
            color: var(--notification-color);
        }

        .notification-icon .unread-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--accent-color);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            border: 1px solid white;
        }

        .user-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-left: 1rem;
        }

        .user-actions a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .user-actions a:hover {
            color: var(--primary-color);
        }

        .user-actions .welcome-message {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .btn, .profile-btn, .enquiry-btn, .message-btn, .feedback-btn {
            display: inline-block;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            position: relative;
            margin-bottom: 0.5rem;
        }

        .profile-btn {
            background: var(--primary-color);
        }

        .enquiry-btn {
            background: var(--notification-color);
        }

        .message-btn {
            background: var(--message-color);
        }

        .feedback-btn {
            background: #17a2b8;
        }

        .enquiry-btn .unread-count, .message-btn .unread-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--accent-color);
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            border: 1px solid white;
        }

        .btn:hover, .profile-btn:hover, .enquiry-btn:hover, .message-btn:hover, .feedback-btn:hover {
            transform: translateY(-2px);
        }

        .profile-btn:hover {
            background: #5a3de6;
        }

        .enquiry-btn:hover {
            background: #e68a00;
        }

        .message-btn:hover {
            background: #219653;
        }

        .feedback-btn:hover {
            background: #138496;
        }

        .btn:disabled, .btn.disabled {
            background: var(--disabled-color);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .user-dropdown {
            position: relative;
            display: inline-block;
        }

        .user-dropdown-btn {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-dropdown-btn:hover {
            background: #5a3de6;
            transform: translateY(-2px);
        }

        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: var(--secondary-color);
            min-width: 120px;
            box-shadow: var(--shadow);
            z-index: 1;
            border-radius: 4px;
            margin-top: 0.2rem;
        }

        .user-dropdown-content a {
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            display: block;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .user-dropdown-content a:hover {
            background: var(--primary-color);
        }

        .user-dropdown:hover .user-dropdown-content {
            display: block;
        }

        .quick-actions {
            position: absolute;
            top: 20px;
            right: -50px;
            background: var(--messenger-blue);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 1001;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .quick-actions:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.4);
        }

        .quick-actions i {
            color: white;
            font-size: 1.8rem;
        }

        .quick-actions-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--notification-bg);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            border: 2px solid white;
        }

        .quick-actions-dropdown {
            position: absolute;
            top: 90px;
            right: -40px;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            color: #333;
            max-width: 300px;
            width: 100%;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        .quick-actions-dropdown.show {
            display: block;
        }

        .quick-actions-dropdown p {
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            color: #444;
        }

        .quick-actions-dropdown p strong {
            color: #222;
        }

        .quick-actions-dropdown p:last-child {
            border-bottom: none;
        }

        /* Feedback Popup Styles */
        .feedback-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .feedback-modal.show {
            display: flex;
        }

        .feedback-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--light-color);
        }

        .feedback-content h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
        }

        .rating {
            margin: 15px 0;
        }

        .rating .star {
            font-size: 2rem;
            color: #ccc;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .rating .star.filled {
            color: var(--star-color);
        }

        .feedback-content textarea {
            width: 100%;
            padding: 0.6rem;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            resize: none;
        }

        .feedback-content textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(74, 144, 226, 0.5);
        }

        .feedback-content .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: var(--light-color);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .feedback-content .close-btn:hover {
            color: var(--accent-color);
        }

        .not-interested-btn {
            background: #dc3545;
            color: var(--light-color);
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-top: 10px;
        }

        .not-interested-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .success-message {
            display: none;
            color: var(--success-color);
            font-weight: 600;
            margin-top: 15px;
            font-size: 1rem;
        }

        .success-message.show {
            display: block;
        }

        section {
            padding: 3rem 0;
            flex-grow: 1;
        }

        h2 {
            color: var(--light-color);
            margin-bottom: 2rem;
            text-align: center;
            font-size: 2.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .carousel {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
            overflow: hidden;
            padding: 1rem 0;
        }

        .carousel-container {
            display: flex;
            transition: transform 0.5s ease;
            gap: 1rem;
        }

        .carousel-card {
            flex: 0 0 300px;
            height: 300px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .carousel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
        }

        .carousel-card.highlight {
            border: 2px solid var(--highlight-bg);
            box-shadow: 0 0 10px var(--highlight-bg);
        }

        .carousel-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 0.5rem;
        }

        .carousel-card h3 {
            color: var(--light-color);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            overflow-wrap: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        .carousel-card p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            overflow-wrap: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        .highlight-text {
            background: var(--highlight-bg);
            color: var(--highlight-color);
            padding: 0 0.2rem;
            border-radius: 3px;
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            font-size: 1.2rem;
            z-index: 10;
        }

        .carousel-btn.prev {
            left: 0;
        }

        .carousel-btn.next {
            right: 0;
        }

        .carousel-btn:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        footer {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .social-links a {
            color: white;
            margin: 0 0.5rem;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: var(--primary-color);
        }

        .no-data-message {
            text-align: center;
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.7);
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
                min-height: auto;
            }

            .logo img {
                height: 80px;
            }

            .search-bar {
                top: 10px;
                right: 100px;
                max-width: 150px;
            }

            .search-bar input[type="text"] {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }

            .search-bar button {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }

            .nav-links {
                flex-direction: column;
                width: 100%;
                margin-top: 1rem;
            }

            .nav-links li {
                margin: 0.5rem 0;
            }

            .user-actions {
                align-items: flex-start;
                margin-top: 1rem;
                width: 100%;
            }

            .user-actions .welcome-message {
                margin-bottom: 0.5rem;
                font-size: 0.9rem;
            }

            .user-actions a:not(.btn):not(.profile-btn):not(.enquiry-btn):not(.message-btn):not(.feedback-btn) {
                margin-bottom: 0.5rem;
            }

            .message-btn, .profile-btn, .enquiry-btn, .feedback-btn {
                margin-left: 0;
                margin-bottom: 0.5rem;
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
                width: 100%;
                justify-content: flex-start;
            }

            .quick-actions {
                position: absolute;
                top: 10px;
                right: -30px;
                width: 50px;
                height: 50px;
            }

            .quick-actions i {
                font-size: 1.5rem;
            }

            .quick-actions-badge {
                width: 20px;
                height: 20px;
                font-size: 0.8rem;
            }

            .quick-actions-dropdown {
                top: 70px;
                right: -20px;
                padding: 1rem;
                font-size: 0.95rem;
                line-height: 1.4;
            }

            .notification-icon {
                font-size: 1.1rem;
            }

            .notification-icon .unread-count {
                font-size: 0.65rem;
                padding: 1px 5px;
            }

            .carousel-card {
                flex: 0 0 250px;
                height: 250px;
            }

            .carousel-card img {
                height: 120px;
            }

            .carousel-card h3 {
                font-size: 1rem;
            }

            .carousel-card p {
                font-size: 0.8rem;
            }

            .user-dropdown-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
                width: 100%;
                justify-content: flex-start;
            }

            .user-dropdown-content {
                right: auto;
                left: 0;
                min-width: 100%;
            }

            .user-dropdown-content a {
                font-size: 0.85rem;
                padding: 0.4rem 0.8rem;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .social-links {
                margin-top: 1rem;
            }

            .feedback-content {
                padding: 15px;
                max-width: 90%;
            }

            .feedback-content h3 {
                font-size: 1.1rem;
            }

            .rating .star {
                font-size: 1.5rem;
            }

            .feedback-content textarea {
                padding: 0.5rem;
                font-size: 0.9rem;
            }

            .feedback-content .close-btn {
                top: 8px;
                right: 8px;
                font-size: 1.3rem;
            }

            .feedback-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .not-interested-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .success-message {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .logo img {
                height: 60px;
            }

            .search-bar {
                top: 5px;
                right: 50px;
                max-width: 120px;
            }

            .search-bar input[type="text"] {
                padding: 0.2rem 0.4rem;
                font-size: 0.75rem;
            }

            .search-bar button {
                padding: 0.2rem 0.4rem;
                font-size: 0.75rem;
            }

            .nav-links li {
                margin: 0.3rem 0;
            }

            .nav-links a {
                font-size: 0.9rem;
            }

            .user-actions .welcome-message {
                font-size: 0.85rem;
            }

            .message-btn, .profile-btn, .enquiry-btn, .feedback-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .enquiry-btn .unread-count, .message-btn .unread-count, .notification-icon .unread-count {
                font-size: 0.6rem;
                padding: 1px 4px;
                top: -6px;
                right: -6px;
            }

            .quick-actions {
                position: absolute;
                top: 5px;
                right: -10px;
                width: 40px;
                height: 40px;
            }

            .quick-actions i {
                font-size: 1.2rem;
            }

            .quick-actions-badge {
                width: 18px;
                height: 18px;
                font-size: 0.7rem;
                border: 1px solid white;
            }

            .quick-actions-dropdown {
                top: 50px;
                right: 0px;
                padding: 1rem;
                font-size: 0.95rem;
                line-height: 1.4;
            }

            .user-dropdown-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .user-dropdown-content a {
                font-size: 0.8rem;
                padding: 0.3rem 0.6rem;
            }

            .carousel-card {
                flex: 0 0 200px;
                height: 200px;
            }

            .carousel-card img {
                height: 100px;
            }

            .carousel-card h3 {
                font-size: 0.9rem;
            }

            .carousel-card p {
                font-size: 0.7rem;
            }

            .feedback-content {
                padding: 10px;
            }

            .feedback-content h3 {
                font-size: 1rem;
            }

            .rating .star {
                font-size: 1.2rem;
            }

            .feedback-content textarea {
                padding: 0.4rem;
                font-size: 0.8rem;
            }

            .feedback-content .close-btn {
                top: 6px;
                right: 6px;
                font-size: 1.2rem;
            }

            .feedback-btn {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }

            .not-interested-btn {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }

            .success-message {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <img src="images/a2b.png" alt="EventHub Logo" loading="lazy" onerror="this.src=''; this.alt='EventHub';">
            </a>
            <form action="search_results.php" method="GET" class="search-bar">
                <input type="text" name="search" placeholder="Search for an event or a place" required>
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="booking.php">Bookings</a></li>
                <li><a href="find_ticket.php">Find My Ticket</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="about.php">About</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li>
                        <a href="view_notification_event.php" class="notification-icon">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadNotificationCount > 0 && $_SESSION['notification_preference'] === 'opt-in'): ?>
                                <span class="unread-count"><?= $unreadNotificationCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) && in_array($_SESSION['user_type'], ['system_admin', 'venue_admin'])): ?>
                    <li><a href="admin.php">Admin Panel</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="welcome-message">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                    <?php if ($_SESSION['user_type'] === 'system_admin'): ?>
                        <a href="enquiries.php" class="enquiry-btn">
                            <i class="fas fa-question-circle"></i> Enquiries
                            <?php if ($unreadEnquiryCount > 0): ?>
                                <span class="unread-count"><?= $unreadEnquiryCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <a href="view_admin_replies.php" class="message-btn">
                            <i class="fas fa-envelope"></i> Messages
                            <?php if ($unreadMessageCount > 0): ?>
                                <span class="unread-count"><?= $unreadMessageCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <a href="user_profile.php" class="profile-btn"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <div class="user-dropdown">
                        <button class="user-dropdown-btn"><i class="fas fa-user"></i> User</button>
                        <div class="user-dropdown-content">
                            <a href="login.php">Login</a>
                            <a href="register.php">Register</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['system_admin', 'venue_admin'])): ?>
                <a href="#" class="quick-actions" id="quickActions">
                    <i class="fas fa-comment"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="quick-actions-badge"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <div class="quick-actions-dropdown" id="quickActionsDropdown">
                    <?php if (!empty($unread_notifications)): ?>
                        <p><strong>Unread Notifications (<?= $unread_count ?>):</strong></p>
                        <?php foreach ($unread_notifications as $notification): ?>
                            <p><?= $notification ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($past_notifications)): ?>
                        <p><strong>Past Notifications:</strong></p>
                        <?php foreach ($past_notifications as $notification): ?>
                            <p><?= $notification ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'system_admin' && !empty($pending_events)): ?>
                        <p><strong>Pending Events:</strong></p>
                        <?php foreach ($pending_events as $event): ?>
                            <p><?= $event ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Feedback Popup -->
    <?php if ($recentEventForFeedback): ?>
        <div class="feedback-modal" id="feedbackModal">
            <div class="feedback-content">
                <a href="#" class="close-btn" onclick="closeFeedbackModal(event)">×</a>
                <h3>How was your experience with "<?= htmlspecialchars($recentEventForFeedback['title']) ?>"?</h3>
                <p>Leave us a feedback, so that we can guarantee improvements next time!</p>
                <form id="feedbackForm" method="POST" action="submit_feedback.php">
                    <input type="hidden" name="event_id" value="<?= htmlspecialchars($recentEventForFeedback['event_id']) ?>">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($_SESSION['user_id']) ?>">
                    <input type="hidden" name="rating" id="ratingInput" value="0">
                    <div class="rating">
                        <i class="far fa-star star" data-value="1"></i>
                        <i class="far fa-star star" data-value="2"></i>
                        <i class="far fa-star star" data-value="3"></i>
                        <i class="far fa-star star" data-value="4"></i>
                        <i class="far fa-star star" data-value="5"></i>
                    </div>
                    <textarea name="comment" placeholder="Your feedback (optional)" rows="4"></textarea>
                    <button type="submit" class="feedback-btn">Submit Feedback</button>
                </form>
                <form id="ignoreFeedbackForm" method="POST" action="ignore_feedback.php" style="display: inline;">
                    <input type="hidden" name="event_id" value="<?= htmlspecialchars($recentEventForFeedback['event_id']) ?>">
                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($_SESSION['user_id']) ?>">
                    <button type="submit" class="not-interested-btn">Not Interested</button>
                </form>
                <div class="success-message" id="successMessage">Successfully Saved your Preferences</div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Featured Events Carousel -->
    <section>
        <div class="container">
            <h2>Featured Events</h2>
            <?php if (empty($featuredEvents)): ?>
                <p class="no-data-message">No approved upcoming events with set prices available at this time.</p>
            <?php else: ?>
                <div class="carousel">
                    <button class="carousel-btn prev" onclick="moveCarousel(-1, 'eventsCarousel')">❮</button>
                    <div class="carousel-container" id="eventsCarousel">
                        <?php foreach ($featuredEvents as $event): ?>
                            <a href="event_details.php?event_id=<?= htmlspecialchars($event['event_id']) ?>" class="carousel-card">
                                <img src="<?= htmlspecialchars($event['image_path'] ?? 'images/default-event.jpg') ?>" 
                                     alt="<?= htmlspecialchars($event['title']) ?>" 
                                     onerror="this.src='images/default-event.jpg';">
                                <h3><?= htmlspecialchars($event['title']) ?></h3>
                                <p><?= htmlspecialchars($event['description'] ?? 'No description available') ?></p>
                                <p><?= date('M j, Y', strtotime($event['start_datetime'])) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-btn next" onclick="moveCarousel(1, 'eventsCarousel')">❯</button>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Explore Our Venues Carousel -->
    <section id="venues-section">
        <div class="container">
            <h2>Explore Our Venues</h2>
            <?php if (empty($venues)): ?>
                <p class="no-data-message">No venues available at this time.</p>
            <?php else: ?>
                <div class="carousel">
                    <button class="carousel-btn prev" onclick="moveCarousel(-1, 'venuesCarousel')">❮</button>
                    <div class="carousel-container" id="venuesCarousel">
                        <?php foreach ($venues as $venue): ?>
                            <a href="venue_details.php?venue_id=<?= htmlspecialchars($venue['venue_id']) ?>" class="carousel-card" data-venue-id="<?= htmlspecialchars($venue['venue_id']) ?>">
                                <img src="<?= htmlspecialchars($venue['image_path'] ?? 'images/venues/default-venue.jpg') ?>" 
                                     alt="<?= htmlspecialchars($venue['name']) ?>" 
                                     onerror="this.src='images/default-venue.jpg';">
                                <h3 class="venue-name"><?= htmlspecialchars($venue['name']) ?></h3>
                                <p class="venue-address"><?= htmlspecialchars($venue['address']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <button class="carousel-btn next" onclick="moveCarousel(1, 'venuesCarousel')">❯</button>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container footer-content">
            <p>© <?= date('Y') ?> EventHub. All Rights Reserved.</p>
            <a href="terms_conditions"> Terms and conditions</a>

            <div class="social-links">

                <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a>
                <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://whatsapp.com" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>

    <script>
        // Carousel functionality
        function setupCarousel(carouselId) {
            let currentIndex = 0;
            const container = document.getElementById(carouselId);
            const cards = container.getElementsByClassName('carousel-card');
            const cardWidth = 300;
            const visibleCards = Math.min(3, cards.length);
            let autoSlide;

            function updateCarousel() {
                const maxIndex = cards.length - visibleCards;
                if (currentIndex > maxIndex) currentIndex = maxIndex;
                if (currentIndex < 0) currentIndex = 0;
                container.style.transform = `translateX(-${currentIndex * cardWidth}px)`;
            }

            function move(direction) {
                currentIndex += direction;
                updateCarousel();
            }

            function startAutoSlide() {
                autoSlide = setInterval(() => {
                    currentIndex = (currentIndex + 1) % (cards.length - visibleCards + 1);
                    updateCarousel();
                }, 3000);
            }

            function stopAutoSlide() {
                clearInterval(autoSlide);
            }

            startAutoSlide();
            container.addEventListener('mouseenter', stopAutoSlide);
            container.addEventListener('mouseleave', startAutoSlide);
            window.addEventListener('resize', updateCarousel);
            updateCarousel();

            return move;
        }

        // Initialize carousels
        const moveEventsCarousel = setupCarousel('eventsCarousel');
        const moveVenuesCarousel = setupCarousel('venuesCarousel');

        function moveCarousel(direction, carouselId) {
            if (carouselId === 'eventsCarousel') {
                moveEventsCarousel(direction);
            } else if (carouselId === 'venuesCarousel') {
                moveVenuesCarousel(direction);
            }
        }

        // Highlight search term in venues
        const searchTerm = <?= json_encode($venueSearchTerm) ?>;
        if (searchTerm) {
            const venueCards = document.querySelectorAll('#venuesCarousel .carousel-card');
            venueCards.forEach(card => {
                const name = card.querySelector('.venue-name').textContent.toLowerCase();
                const address = card.querySelector('.venue-address').textContent.toLowerCase();
                if (name.includes(searchTerm.toLowerCase()) || address.includes(searchTerm.toLowerCase())) {
                    card.classList.add('highlight');

                    // Highlight text in name
                    const nameElement = card.querySelector('.venue-name');
                    const nameText = nameElement.textContent;
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    nameElement.innerHTML = nameText.replace(regex, '<span class="highlight-text">$1</span>');

                    // Highlight text in address
                    const addressElement = card.querySelector('.venue-address');
                    const addressText = addressElement.textContent;
                    addressElement.innerHTML = addressText.replace(regex, '<span class="highlight-text">$1</span>');

                    // Scroll to the card
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }

        // Quick Actions Dropdown
        document.addEventListener('DOMContentLoaded', () => {
            const actionsButton = document.getElementById('quickActions');
            const dropdown = document.getElementById('quickActionsDropdown');
            let isDropdownOpen = false;

            if (actionsButton) {
                actionsButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    isDropdownOpen = !isDropdownOpen;
                    dropdown.classList.toggle('show');
                });
            }

            window.addEventListener('click', (event) => {
                if (!actionsButton.contains(event.target) && !dropdown.contains(event.target) && isDropdownOpen) {
                    dropdown.classList.remove('show');
                    isDropdownOpen = false;

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'mark_notifications_read.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            const badge = actionsButton.querySelector('.quick-actions-badge');
                            if (badge) badge.remove();
                        }
                    };
                    const data = 'user_id=<?= isset($user_id) ? $user_id : '' ?>&user_type=<?= isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '' ?>';
                    xhr.send(data);
                }
            });

            // Feedback Popup Logic
            const feedbackModal = document.getElementById('feedbackModal');
            if (feedbackModal) {
                feedbackModal.classList.add('show');
            }

            const stars = document.querySelectorAll('.rating .star');
            const ratingInput = document.getElementById('ratingInput');

            if (stars && ratingInput) {
                stars.forEach(star => {
                    star.addEventListener('click', () => {
                        const value = star.getAttribute('data-value');
                        ratingInput.value = value;

                        // Reset all stars
                        stars.forEach(s => {
                            s.classList.remove('filled');
                            s.classList.remove('fas');
                            s.classList.add('far');
                        });

                        // Fill stars up to the clicked one
                        for (let i = 0; i < value; i++) {
                            stars[i].classList.add('filled');
                            stars[i].classList.remove('far');
                            stars[i].classList.add('fas');
                        }
                    });
                });
            }

            // Close feedback modal when clicking the close button
            const closeBtn = document.querySelector('.feedback-content .close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    closeFeedbackModal(event);
                });
            }

            // Function to close feedback modal
            function closeFeedbackModal(event) {
                event.preventDefault();
                const modal = document.getElementById('feedbackModal');
                if (modal) {
                    modal.classList.remove('show');
                }
            }

            // Handle "Not Interested" button with AJAX
            const ignoreFeedbackForm = document.getElementById('ignoreFeedbackForm');
            const successMessage = document.getElementById('successMessage');
            if (ignoreFeedbackForm) {
                ignoreFeedbackForm.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const formData = new FormData(this);

                    fetch('ignore_feedback.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Hide the forms and show success message
                            document.getElementById('feedbackForm').style.display = 'none';
                            ignoreFeedbackForm.style.display = 'none';
                            successMessage.classList.add('show');

                            // Close the modal after a delay
                            setTimeout(() => {
                                closeFeedbackModal(event);
                            }, 1500);
                        } else {
                            console.error('Error:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                    });
                });
            }
        });
    </script>
</body>
</html>
<?php $db->close(); ?>