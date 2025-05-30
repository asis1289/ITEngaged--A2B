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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-color: #28a745;
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

        /* Notification Box */
        .notification-box {
            display: <?php echo !isset($_SESSION['user_id']) ? 'block' : 'none'; ?>;
            width: 300px;
            background: var(--notification-color);
            color: var(--light-color);
            text-align: center;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            opacity: 0;
            animation: fadeInOut 4s ease forwards; /* Adjusted to 4 seconds */
            margin: 0 auto;
            margin-top: 2rem; /* A few inches below the header */
        }

        .notification-box p {
            margin: 0;
            font-size: 0.9rem;
        }

        .notification-box a {
            color: var(--light-color);
            text-decoration: underline;
            font-weight: 600;
            margin-left: 0.3rem;
        }

        .notification-box a:hover {
            color: #fff;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-10px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-10px); }
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
        }

        .about-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 2rem;
        }

        .about-content p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .feature-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            color: var(--light-color);
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .feature-card p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
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

            h2 {
                font-size: 2rem;
            }

            .about-content p {
                font-size: 1rem;
            }

            .feature-card i {
                font-size: 2rem;
            }

            .feature-card h3 {
                font-size: 1.3rem;
            }

            .feature-card p {
                font-size: 0.9rem;
            }

            .notification-box {
                width: 250px;
                padding: 0.6rem 0.8rem;
                margin-top: 1.5rem;
            }

            .notification-box p {
                font-size: 0.85rem;
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

            .feature-card i {
                font-size: 1.8rem;
            }

            .feature-card h3 {
                font-size: 1.2rem;
            }

            .feature-card p {
                font-size: 0.85rem;
            }

            .notification-box {
                width: 200px;
                padding: 0.5rem 0.7rem;
                margin-top: 1rem;
            }

            .notification-box p {
                font-size: 0.8rem;
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

    <!-- About Section -->
    <section class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="notification-box">
                <p>Unlock more features! <a href="register.php">Sign up now</a></p>
            </div>
        <?php endif; ?>
        <h2>About EventHub</h2>
        <div class="about-content">
            <p>EventHub is the premier Smart Event Management System (SEMS), crafted to transform the way events are planned, booked, and experienced. Our mission is to bridge people through unforgettable moments, whether it’s a high-stakes corporate conference, a dream wedding, an electrifying concert, or an innovative exhibition.</p>
            <p>Built on a foundation of innovation, EventHub leverages advanced technology—secure cloud infrastructure, real-time data processing, and intuitive interfaces—to streamline event management. We collaborate with top-tier venues, organizers, and service providers to deliver seamless, high-quality experiences for attendees, planners, and administrators.</p>
            <p>Our platform supports diverse user roles, including attendees, venue administrators, and system admins, with tailored tools for each. From discovering nearby events to managing complex logistics, EventHub scales effortlessly to handle events of any size. Integrated analytics, secure payment gateways, and API support for third-party tools make it a versatile solution for modern event management.</p>
            <p>At EventHub, we prioritize reliability, accessibility, and customer satisfaction, ensuring every event is a success. Join us in creating moments that matter.</p>
        </div>

        <!-- Features Section -->
        <h2>SEMS Features</h2>
        <div class="features-grid">
            <div class="feature-card">
                <i class="fas fa-ticket-alt"></i>
                <h3>Easy Ticketing</h3>
                <p>Securely purchase and manage event tickets online with instant confirmations, QR code scanning, and real-time availability updates.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-map-marker-alt"></i>
                <h3>Venue Management</h3>
                <p>Browse and book from a curated list of premium venues, with detailed information on capacity, amenities, and availability.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-user-shield"></i>
                <h3>Admin Dashboard</h3>
                <p>System and venue administrators can manage events, bookings, and users through a powerful, intuitive dashboard with analytics and reporting tools.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-mobile-alt"></i>
                <h3>Mobile Accessibility</h3>
                <p>Access EventHub on the go with our responsive platform, optimized for seamless browsing, booking, and management on mobile devices.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-shield-alt"></i>
                <h3>Secure Payments</h3>
                <p>Enjoy peace of mind with our encrypted payment gateway, supporting multiple payment methods for safe and convenient transactions.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-bell"></i>
                <h3>Real-Time Notifications</h3>
                <p>Stay informed with instant updates on event schedules, booking confirmations, and last-minute changes via email or in-app alerts.</p>
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

    <script>
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
        });
    </script>
</body>
</html>
<?php $db->close(); ?>