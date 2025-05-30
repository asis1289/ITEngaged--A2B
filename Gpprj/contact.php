<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once 'Connection/sql_auth.php';

// Initialize notification preference if not set
if (!isset($_SESSION['notification_preference'])) {
    $_SESSION['notification_preference'] = 'opt-in'; // Default to opt-in
}

// Fetch user details if not already in session
if (isset($_SESSION['user_id']) && (!isset($_SESSION['full_name']) || !isset($_SESSION['email']) || !isset($_SESSION['phone_num']))) {
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT full_name, email, phone_num FROM users WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['phone_num'] = $user['phone_num'];
        }
        $stmt->close();
    }
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

// Fetch notification count for new approved events since last notifications_viewed
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
}

$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phoneno = trim($_POST['phoneno'] ?? '');
    $enquiry = trim($_POST['enquiry'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($phoneno) || empty($enquiry)) {
        $message = "All fields are required!";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format!";
        $message_type = "error";
    } elseif (!preg_match("/^[0-9]{10}$/", $phoneno)) {
        $message = "Phone number must be exactly 10 digits!";
        $message_type = "error";
    } else {
        // Insert into contact_inquiries table
        $stmt = $db->prepare("INSERT INTO contact_inquiries (name, email, phoneno, enquiry) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            $message = "Database prepare error: " . $db->error;
            $message_type = "error";
            error_log("Database prepare error: " . $db->error);
        } else {
            $stmt->bind_param("ssss", $name, $email, $phoneno, $enquiry);
            if ($stmt->execute()) {
                $message = "Enquiry submitted successfully! We'll get back to you soon.";
                $message_type = "success";
                $name = '';
                $email = '';
                $phoneno = '';
                $enquiry = '';
            } else {
                $message = "Failed to submit enquiry: " . $stmt->error;
                $message_type = "error";
                error_log("Failed to submit enquiry: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}

// Optional auto-fill from session if logged in
$autoFillName = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : '';
$autoFillEmail = isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '';
$autoFillPhone = isset($_SESSION['phone_num']) ? htmlspecialchars($_SESSION['phone_num']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-color: #28a745;
            --error-color: #dc3545;
            --disabled-color: #666;
            --success-bg: #ffffff;
            --success-border: #00cc66;
            --notification-bg: #dc3545;
            --message-color: #28a745;
            --notification-color: #ff9500;
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
            min-height: 120px; /* Ensure header has enough height for quick actions */
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            position: relative; /* For positioning quick-actions */
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
            gap: 0.3rem; /* Ensure consistent spacing */
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
            line-height: normal; /* Ensure consistent line height */
        }

        .search-bar input[type="text"]::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .search-bar button {
            background: var(--primary-color);
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 0.85rem;
            display: flex; /* Ensure button content is centered */
            align-items: center;
            justify-content: center;
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

        .btn, .profile-btn, .enquiry-btn, .message-btn {
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

        .btn {
            background: var(--primary-color);
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

        .btn:hover, .profile-btn:hover, .enquiry-btn:hover, .message-btn:hover {
            transform: translateY(-2px);
        }

        .btn:hover {
            background: #5a3de6;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            max-width: 600px;
            margin: 0 auto;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        label {
            display: block;
            margin: 0.5rem 0;
            color: var(--light-color);
            font-weight: 500;
        }

        .auto-fill-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        input, textarea {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(255, 255, 255, 0.2);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .message-box {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 25px;
            border-radius: 8px;
            font-size: 0.9rem;
            text-align: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
            box-shadow: var(--shadow);
        }

        .message-success {
            color: var(--success-color);
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .message-error {
            color: var(--error-color);
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .map-container {
            margin-top: 2rem;
            text-align: center;
        }

        .map-container h3 {
            color: var(--light-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .map-toggle-btn {
            background: var(--secondary-color);
            color: var(--light-color);
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .map-toggle-btn:hover {
            background: #2a2a3f;
            transform: translateY(-2px);
        }

        .map-content {
            display: none;
            margin-top: 1rem;
        }

        .map-content.active {
            display: block;
        }

        .map-content iframe {
            width: 100%;
            height: 300px;
            border: 0;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }

        .map-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .map-link:hover {
            text-decoration: underline;
        }

        footer {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            margin-top: auto;
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
                order: 2;
                flex-direction: column;
                width: 100%;
                margin-top: 1rem;
            }

            .nav-links li {
                margin: 0.5rem 0;
            }

            .user-actions {
                order: 3;
                align-items: flex-start;
                margin-top: 1rem;
                width: 100%;
            }

            .user-actions .welcome-message {
                margin-bottom: 0.5rem;
                font-size: 0.9rem;
            }

            .user-actions a:not(.btn):not(.profile-btn):not(.enquiry-btn):not(.message-btn) {
                margin-bottom: 0.5rem;
            }

            .message-btn, .profile-btn, .enquiry-btn {
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

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .social-links {
                margin-top: 1rem;
            }

            .map-content iframe {
                height: 250px;
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

            .message-btn, .profile-btn, .enquiry-btn {
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
        }
    </style>
    <script>
        function autoFillDetails(checkbox) {
            const nameInput = document.querySelector('input[name="name"]');
            const emailInput = document.querySelector('input[name="email"]');
            const phoneInput = document.querySelector('input[name="phoneno"]');
            
            if (checkbox.checked) {
                nameInput.value = '<?= $autoFillName ?>';
                emailInput.value = '<?= $autoFillEmail ?>';
                phoneInput.value = '<?= $autoFillPhone ?>';
                localStorage.setItem('autoFillChecked', 'true');
            } else {
                nameInput.value = '';
                emailInput.value = '';
                phoneInput.value = '';
                localStorage.setItem('autoFillChecked', 'false');
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const messageBox = document.querySelector(".message-box");
            if (messageBox) {
                setTimeout(() => {
                    messageBox.style.transition = "opacity 0.5s ease-out";
                    messageBox.style.opacity = "0";
                    setTimeout(() => messageBox.remove(), 500);
                }, 3000);
            }

            if (document.querySelector(".message-success")) {
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 3500);
            }

            const toggleBtn = document.getElementById("map-toggle-btn");
            const mapContent = document.getElementById("map-content");
            if (toggleBtn && mapContent) {
                toggleBtn.addEventListener("click", function() {
                    mapContent.classList.toggle("active");
                    toggleBtn.textContent = mapContent.classList.contains("active") ? "Hide Map" : "Show Map";
                });
            }

            // Restrict phone number input to digits only
            const phoneInput = document.querySelector('input[name="phoneno"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                });
            }

            // Auto-fill checkbox state on load
            const autoFillCheckbox = document.getElementById('auto-fill');
            if (autoFillCheckbox) {
                if (localStorage.getItem('autoFillChecked') === 'true') {
                    autoFillCheckbox.checked = true;
                    autoFillDetails(autoFillCheckbox);
                }
            }

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
        });
    </script>
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

    <!-- Contact Section -->
    <section class="container">
        <h2>Contact Us</h2>
        <div class="card">
            <form method="POST" action="contact.php">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <label class="auto-fill-label">
                        <input type="checkbox" id="auto-fill" name="auto-fill" onchange="autoFillDetails(this)">
                        Auto-fill with my details (optional)
                    </label>
                <?php endif; ?>
                <label>Name: <input type="text" name="name" value="<?= isset($name) ? htmlspecialchars($name) : '' ?>" required></label>
                <label>Email: <input type="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required></label>
                <label>Phone Number: <input type="text" name="phoneno" pattern="[0-9]*" maxlength="10" value="<?= isset($phoneno) ? htmlspecialchars($phoneno) : '' ?>" required title="Please enter only digits (max 10)"></label>
                <label>Enquiry: <textarea name="enquiry" rows="5" required><?= isset($enquiry) ? htmlspecialchars($enquiry) : '' ?></textarea></label>
                <button type="submit" class="btn">Submit Enquiry</button>
            </form>
        </div>
        <?php if (!empty($message)): ?>
            <div class="message-box <?= $message_type === 'success' ? 'message-success' : 'message-error' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <div class="map-container">
            <h3>Our Location - Strathfield, NSW</h3>
            <button id="map-toggle-btn" class="map-toggle-btn">Show Map</button>
            <div id="map-content" class="map-content">
                <iframe width="100%" height="300" frameborder="0" style="border:0" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3313.614071589677!2d151.0858473152093!3d-33.87098798065728!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x6b12b9f6b7b7b7b7%3A0x5017d681632b9a0!2s22%20The%20Boulevarde%2C%20Strathfield%20NSW%202135%2C%20Australia!5e0!3m2!1sen!2sus!4v1698765432123" allowfullscreen loading="lazy"></iframe>
                <p>View on Google Maps: <a href="https://www.google.com/maps/place/Level+1+and+2/22+The+Boulevarde,Strathfield+NSW+2135" target="_blank" class="map-link">Level 1 and 2/22 The Boulevarde, Strathfield NSW 2135</a></p>
            </div>
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
</body>
</html>
<?php $db->close(); ?>