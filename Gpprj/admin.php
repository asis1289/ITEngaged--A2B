<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session for user authentication
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit;
}

// Check user type and redirect if not authorized
$user_type = $_SESSION['user_type'];
if (!in_array($user_type, ['system_admin', 'venue_admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection
require_once 'Connection/sql_auth.php';

// Fetch count of pending events created by venue admins (for system admin notification)
$pending_count = 0;
if ($user_type === 'system_admin') {
    $query = "SELECT COUNT(*) as count FROM events WHERE status = 'pending' AND created_by_type = 'venue_admin'";
    $result = $db->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $pending_count = $row['count'];
    }
}

// Fetch unread message count for the logged-in user (admin-specific messages)
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

// Fetch unread notification count for the logged-in user
$unreadNotificationCount = 0;
if (isset($_SESSION['user_id']) && ($_SESSION['notification_preference'] ?? 'opt-in') === 'opt-in') {
    $user_id = $_SESSION['user_id'];
    $notificationQuery = "SELECT COUNT(*) as notification_count 
                         FROM events e 
                         JOIN venues v ON e.venue_id = v.venue_id 
                         WHERE e.start_datetime > NOW() 
                         AND e.status = 'approved' 
                         AND e.created_by_type IN ('system_admin', 'venue_admin')";
    $notificationResult = $db->query($notificationQuery);
    if ($notificationResult) {
        $notificationData = $notificationResult->fetch_assoc();
        $unreadNotificationCount = $notificationData['notification_count'];

        $viewedQuery = "SELECT notifications_viewed FROM users WHERE user_id = ?";
        $viewedStmt = $db->prepare($viewedQuery);
        $viewedStmt->bind_param("i", $user_id);
        $viewedStmt->execute();
        $viewedResult = $viewedStmt->get_result();
        if ($viewedResult && $viewedResult->num_rows > 0) {
            $viewedData = $viewedResult->fetch_assoc();
            if ($viewedData['notifications_viewed'] !== null) {
                $unreadNotificationCount = 0;
            }
        }
        $viewedStmt->close();
        $notificationResult->free();
    }
}

// Fetch count of unread enquiries for system_admin
$unread_enquiries_count = 0;
if ($user_type === 'system_admin') {
    $query = "SELECT COUNT(*) as count FROM contact_inquiries WHERE status = 'unread'";
    $result = $db->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $unread_enquiries_count = $row['count'];
    }
}

// Fetch count of unread feedback
$unread_feedback_count = 0;
if ($user_type === 'system_admin') {
    $query = "SELECT COUNT(*) as count FROM feedback WHERE status = 'unread'";
    $result = $db->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $unread_feedback_count = $row['count'];
    }
}

// Check for new enquiry notification
$new_enquiry_alert = false;
if ($user_type === 'system_admin' && isset($_SESSION['new_enquiry']) && $_SESSION['new_enquiry']) {
    $new_enquiry_alert = true;
    unset($_SESSION['new_enquiry']);
}

// Check for new feedback notification
$new_feedback_alert = false;
if ($user_type === 'system_admin' && isset($_SESSION['new_feedback']) && $_SESSION['new_feedback']) {
    $new_feedback_alert = true;
    unset($_SESSION['new_feedback']);
}

// Check for event action message
$event_message = '';
$event_action = '';
if (isset($_SESSION['event_action']) && isset($_SESSION['event_message'])) {
    $event_action = $_SESSION['event_action'];
    $event_message = $_SESSION['event_message'];
    unset($_SESSION['event_action']);
    unset($_SESSION['event_message']);
}

// Fetch notifications
$unread_notifications = [];
$unread_count = 0;
$past_notifications = [];
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

    $pending_events = [];
    if ($pending_count > 0) {
        $query = "SELECT e.event_id, e.event_name, e.created_at, u.full_name, u.email, u.phone_num
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --notification-bg: #dc3545;
            --success-bg: #28a745;
            --notification-dropdown-bg: rgba(245, 245, 245, 0.95);
            --message-color: #28a745;
            --notification-color: #ff9500;
            --disabled-color: #666;
            --messenger-blue: #0084FF;
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
            line-height: 1.2;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 60px);
        }

        header {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 120px; /* Ensure header has enough height for quick actions */
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            position: relative; /* For positioning quick-actions and search bar */
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
            top: 20px;
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

        .nav-links a.active {
            color: var(--primary-color);
            font-weight: 600;
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

        .enquiry-btn, .message-btn {
            display: inline-block;
            background: var(--notification-color);
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

        .message-btn {
            background: var(--message-color);
        }

        .enquiry-btn:hover, .message-btn:hover {
            transform: translateY(-2px);
        }

        .enquiry-btn:hover {
            background: #e68a00;
        }

        .message-btn:hover {
            background: #219653;
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

        .btn, .profile-btn {
            display: inline-block;
            background: var(--primary-color);
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
            margin-bottom: 0.5rem;
        }

        .profile-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn:hover, .profile-btn:hover {
            transform: translateY(-2px);
        }

        .profile-btn:hover {
            background: #5a3de6;
        }

        .btn:disabled, .btn.disabled {
            background: var(--disabled-color);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .sidebar {
            width: 100%;
            max-width: 200px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 10px 0;
            box-shadow: var(--shadow);
            margin-top: 20px;
            flex: 0 0 auto;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            padding: 8px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar ul li:first-child {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar ul li a {
            color: var(--light-color);
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s ease;
        }

        .sidebar ul li a:hover {
            color: var(--primary-color);
        }

        .sidebar ul li a .badge {
            background: var(--notification-bg);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: auto;
        }

        .refresh-line {
            cursor: pointer;
            padding: 5px 15px;
            color: var(--light-color);
            font-size: 1.5rem;
            text-align: center;
            transition: color 0.3s ease;
        }

        .refresh-line:hover {
            color: var(--primary-color);
        }

        .main-content {
            flex: 1;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            width: 100%;
        }

        .default-view {
            text-align: center;
            color: var(--light-color);
        }

        .default-view h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 2px;
            animation: fadeIn 2s infinite alternate;
        }

        .default-view p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        .default-view .event-icon {
            font-size: 4rem;
            color: var(--primary-color);
            animation: spin 4s infinite linear;
        }

        @keyframes fadeIn {
            from { opacity: 0.6; }
            to { opacity: 1; }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .notification, .event-message {
            position: fixed;
            padding: 0.8rem;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            box-shadow: var(--shadow);
            opacity: 1;
            transition: opacity 0.5s ease;
            z-index: 30;
        }

        .notification {
            top: 50px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--notification-bg);
        }

        .event-message {
            top: 20%;
            right: 20px;
            max-width: 250px;
            background: var(--notification-bg);
        }

        .event-message.approved {
            background: var(--success-bg);
        }

        .event-message.rejected {
            background: var(--notification-bg);
        }

        .notification.hidden, .event-message.hidden {
            opacity: 0;
            visibility: hidden;
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

        footer {
            background: var(--secondary-color);
            color: white;
            padding: 0.8rem 0;
            margin-top: auto;
            width: 100%;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            font-size: 0.9rem;
        }

        .social-links a {
            color: white;
            margin: 0 0.4rem;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: var(--primary-color);
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
            }

            .user-actions .welcome-message {
                margin-bottom: 0.5rem;
            }

            .enquiry-btn, .profile-btn, .message-btn {
                margin-left: 0;
                margin-bottom: 0.5rem;
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
                width: 100%;
                justify-content: flex-start;
            }

            .notification-icon {
                font-size: 1.1rem;
            }

            .notification-icon .unread-count {
                font-size: 0.65rem;
                padding: 1px 5px;
            }

            .sidebar {
                max-width: 180px;
                margin-left: auto;
                margin-right: auto;
            }

            .sidebar ul li {
                padding: 6px 12px;
            }

            .sidebar ul li a {
                font-size: 0.85rem;
            }

            .refresh-line {
                padding: 4px 12px;
                font-size: 1.5rem;
            }

            .main-content {
                padding: 15px;
                width: 100%;
            }

            .notification {
                top: 40px;
                padding: 0.6rem;
            }

            .event-message {
                right: 10px;
                padding: 0.6rem;
                font-size: 0.8rem;
                max-width: 200px;
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
                padding: 0.8rem;
                font-size: 0.9rem;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .social-links {
                margin-top: 1rem;
            }
        }

        @media (max-width: 480px) {
            .logo img {
                height: 80px;
            }

            .header-container {
                flex-direction: column;
                align-items: flex-start;
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
                margin-left: 0.8rem;
            }

            .nav-links a {
                font-size: 0.8rem;
            }

            .sidebar {
                max-width: 160px;
                margin-left: auto;
                margin-right: auto;
            }

            .sidebar ul li {
                padding: 5px 10px;
            }

            .sidebar ul li a {
                font-size: 0.8rem;
            }

            .refresh-line {
                padding: 3px 10px;
                font-size: 1.5rem;
            }

            .main-content {
                padding: 10px;
            }

            .enquiry-btn, .profile-btn, .message-btn {
                padding: 0.2rem 0.5rem;
                font-size: 0.8rem;
            }

            .enquiry-btn .unread-count, .message-btn .unread-count, .notification-icon .unread-count {
                font-size: 0.55rem;
                padding: 1px 3px;
                top: -5px;
                right: -5px;
            }

            .user-actions .welcome-message {
                font-size: 0.8rem;
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
                padding: 0.6rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
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
                            <?php if ($unreadNotificationCount > 0 && ($_SESSION['notification_preference'] ?? 'opt-in') === 'opt-in'): ?>
                                <span class="unread-count"><?= $unreadNotificationCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id']) && in_array($_SESSION['user_type'], ['system_admin', 'venue_admin'])): ?>
                    <li><a href="admin.php" class="active">Admin Panel</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="welcome-message">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?> (<?= $user_type === 'system_admin' ? 'System Admin' : 'Venue Admin' ?>)</span>
                    <?php if ($user_type === 'system_admin'): ?>
                        <a href="enquiries.php" class="enquiry-btn">
                            <i class="fas fa-question-circle"></i>  Enquiries
                            <?php if ($unread_enquiries_count > 0): ?>
                                <span class="unread-count"><?= $unread_enquiries_count ?></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
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
                    <a href="login.php" class="btn">Login</a>
                    <a href="register.php" class="btn">Register</a>
                <?php endif; ?>
            </div>
            <?php if ($user_type === 'venue_admin' || $user_type === 'system_admin'): ?>
                <a href="quick_actions.php" class="quick-actions" id="quickActions">
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
                    <?php if ($user_type === 'system_admin' && !empty($pending_events)): ?>
                        <p><strong>Pending Events:</strong></p>
                        <?php foreach ($pending_events as $event): ?>
                            <p><?= $event ?></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <div class="sidebar" id="sidebar">
            <div class="refresh-line" onclick="location.reload();">≡</div>
            <ul>
                <?php if ($user_type === 'system_admin' || $user_type === 'venue_admin'): ?>
                    <li><a href="add_event.php"><i class="fas fa-plus"></i> Add Event</a></li>
                    <li><a href="manage_venues.php"><i class="fas fa-building"></i> Manage Venues</a></li>
                    <li><a href="event_list.php"><i class="fas fa-list"></i> Manage Events</a></li>
                    <li><a href="view_ticket_volume.php"><i class="fas fa-chart-bar"></i> View Ticket Volume</a></li>
                    <li><a href="set_ticket_price.php"><i class="fas fa-ticket"></i> Set Ticket Price</a></li>
                    <li><a href="lookup_attendee_for_event.php"><i class="fas fa-users"></i> Lookup Attendees</a></li>
                    <li><a href="scan_ticket.php"><i class="fas fa-ticket-alt"></i> Scan Tickets</a></li>
                <?php endif; ?>
                <?php if ($user_type === 'system_admin'): ?>
                    <li><a href="approve_reject.php"><i class="fas fa-check"></i> Approve/Reject Events<span class="badge" style="display: <?= $pending_count > 0 ? 'flex' : 'none' ?>"><?= $pending_count ?></span></a></li>
                    <li><a href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
                    <li><a href="feedback.php"><i class="fas fa-comment"></i> Event Feedback<span class="badge" style="display: <?= $unread_feedback_count > 0 ? 'flex' : 'none' ?>"><?= $unread_feedback_count ?></span></a></li>
                    <li><a href="message_between_admins.php"><i class="fas fa-bullhorn"></i> Send Message</a></li>
                <?php endif; ?>
                <?php if ($user_type === 'venue_admin'): ?>
                    <li><a href="message_between_admins.php"><i class="fas fa-paper-plane"></i> Send Message</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="main-content">
            <?php if ($user_type === 'system_admin' && $pending_count > 0): ?>
                <div class="notification" id="notification">
                    You have a new event, please review.
                </div>
                <script>
                    setTimeout(() => {
                        const notification = document.getElementById('notification');
                        notification.classList.add('hidden');
                    }, 5000);
                </script>
            <?php endif; ?>
            <?php if ($user_type === 'system_admin' && $new_enquiry_alert): ?>
                <div class="notification" id="enquiryNotification">
                    You have a new enquiry, please review.
                </div>
                <script>
                    setTimeout(() => {
                        const notification = document.getElementById('enquiryNotification');
                        notification.classList.add('hidden');
                    }, 5000);
                </script>
            <?php endif; ?>
            <?php if ($user_type === 'system_admin' && $new_feedback_alert): ?>
                <div class="notification" id="feedbackNotification">
                    You have new feedback, please review.
                </div>
                <script>
                    setTimeout(() => {
                        const notification = document.getElementById('feedbackNotification');
                        notification.classList.add('hidden');
                    }, 5000);
                </script>
            <?php endif; ?>
            <?php if (!empty($event_message)): ?>
                <div class="event-message <?= $event_action ?>" id="event-message">
                    <?= htmlspecialchars($event_message) ?>
                </div>
                <script>
                    setTimeout(() => {
                        const eventMessage = document.getElementById('event-message');
                        eventMessage.classList.add('hidden');
                    }, 5000);
                </script>
            <?php endif; ?>
            <div class="default-view">
                <i class="fas fa-calendar-alt event-icon"></i>
                <h1>Welcome to Admin Panel</h1>
                <p>Explore and manage events with ease. Click an option from the left to begin!</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
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
        function toggleMenu() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const actionsButton = document.getElementById('quickActions');
            const dropdown = document.getElementById('quickActionsDropdown');
            let isDropdownOpen = false;

            if (actionsButton) {
                actionsButton.addEventListener('click', (event) => {
                    event.preventDefault(); // Prevent default navigation for now
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
                    const data = 'user_id=<?= $user_id ?>&user_type=<?= $user_type ?>';
                    xhr.send(data);
                }
            });
        });
    </script>
</body>
</html>
<?php $db->close(); ?>