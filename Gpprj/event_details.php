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

// Fetch event details
$event = [];
if (isset($_GET['event_id'])) {
    $event_id = $db->real_escape_string($_GET['event_id']);
    $query = "SELECT e.*, v.name as venue_name, v.address, v.venue_id, tp.ticket_price, tp.set_by_type 
              FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              LEFT JOIN ticket_prices tp ON e.event_id = tp.event_id 
              WHERE e.event_id = '$event_id' AND e.status = 'approved' AND e.start_datetime > NOW()";
    $result = $db->query($query);
    if ($result && $result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $result->free();
    }
}

if (empty($event)) {
    header("Location: index.php");
    exit;
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

// Fetch unread notification count for admins
$unreadAdminCount = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['system_admin', 'venue_admin'])) {
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    if ($user_type === 'venue_admin') {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE venue_admin_id = ? AND read_status = 0 AND sender_type = 'system_admin'";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $unreadAdminCount = $row['count'];
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
            $unreadAdminCount = $row['count'];
        }
        $stmt->close();
    }
}

// Fetch unread enquiries count for system_admin
$unreadEnquiryCount = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'system_admin') {
    $enquiryQuery = "SELECT COUNT(*) as unread_count FROM contact_inquiries WHERE status = 'unread'";
    $result = $db->query($enquiryQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $unreadEnquiryCount = $row['unread_count'];
    } else {
        error_log("Enquiry query failed: " . $db->error);
    }
}

// Base URL for sharing
$base_url = "http://localhost/ITProject/Gpprj/event_details.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Details - EventHub</title>
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
            max-width: 1400px;
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
            max-width: 1400px;
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

        .content {
            flex: 1;
            padding: 4rem 0;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 3rem;
            padding: 0 30px;
            position: relative;
        }

        .event-details {
            flex: 2;
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 2.5rem;
            max-width: 70%;
            animation: slideUp 1s ease-out;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .event-details img {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .event-details img:hover {
            transform: scale(1.05);
        }

        .event-details h2 {
            color: var(--light-color);
            font-size: 2.5rem;
            margin-bottom: 2rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-align: center;
        }

        .detail-card {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            background: rgba(255, 255, 255, 0.08);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid var(--primary-color);
            width: 100%;
        }

        .detail-card:hover {
            transform: translateX(10px);
            box-shadow: 0 8px 25px rgba(107, 72, 255, 0.4);
        }

        .detail-card i {
            font-size: 2rem;
            color: var(--primary-color);
            transition: color 0.3s ease;
        }

        .detail-card:hover i {
            color: var(--accent-color);
        }

        .detail-card p {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.2rem;
            margin: 0;
            flex: 1;
        }

        .detail-card .map-btn {
            background: var(--accent-color);
            color: white;
            padding: 0.7rem 1.2rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s ease, transform 0.2s ease;
            border: none;
        }

        .detail-card .map-btn:hover {
            background: #e02855;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 46, 99, 0.5);
        }

        .detail-card .map-btn i {
            animation: pulseMap 2s infinite;
        }

        @keyframes pulseMap {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .highlight-box {
            background: rgba(107, 72, 255, 0.1);
            padding: 1.5rem;
            border-radius: 15px;
            margin-top: 2rem;
            border-left: 5px solid var(--accent-color);
            width: 100%;
        }

        .highlight-box h3 {
            color: var(--light-color);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .highlight-box p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .button-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
            width: 100%;
            align-items: center;
        }

        .ticket-btn {
            background: linear-gradient(45deg, #6b48ff, #ff2e63);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
            max-width: 200px;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .ticket-btn::after {
            content: '';
            position: absolute;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            left: 0;
            top: 0;
            transition: width 0.3s ease;
        }

        .ticket-btn:hover::after {
            width: 100%;
        }

        .ticket-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(107, 72, 255, 0.6);
        }

        .ticket-btn:disabled {
            background: var(--disabled-color);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .ticket-btn:disabled:hover::after {
            width: 0;
        }

        .share-btn {
            background: var(--accent-color);
            color: var(--light-color);
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.3s ease, transform 0.2s ease;
            cursor: pointer;
            width: 100%;
            max-width: 200px;
            justify-content: center;
        }

        .share-btn i {
            margin-left: 0.5rem;
            font-size: 1.2rem;
        }

        .share-btn:hover {
            background: #e02855;
            transform: translateY(-2px);
        }

        .closed-message {
            font-size: 1rem;
            color: var(--light-color);
            text-align: center;
            max-width: 200px;
        }

        .closed-message a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
        }

        .closed-message a:hover {
            color: #e02855;
            text-decoration: underline;
        }

        .share-modal {
            display: none;
            position: fixed;
            background: rgba(0, 0, 0, 0.7);
            z-index: 2000;
            padding: 10px;
            border-radius: 15px;
        }

        .share-modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border-radius: 15px;
            padding: 1.5rem;
            width: 300px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideUp 0.3s ease-out;
        }

        .share-modal-content h3 {
            color: var(--light-color);
            font-size: 1.3rem;
            margin-bottom: 1rem;
        }

        .share-modal-content .social-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .share-modal-content .social-links a {
            color: var(--light-color);
            font-size: 1.8rem;
            transition: color 0.3s ease, transform 0.2s ease;
        }

        .share-modal-content .social-links a:hover {
            color: var(--primary-color);
            transform: scale(1.2);
        }

        .close-modal {
            position: absolute;
            top: 5px;
            right: 10px;
            font-size: 1.3rem;
            color: var(--light-color);
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-modal:hover {
            color: var(--accent-color);
        }

        .ticket-section {
            display: none; /* Removed from layout since button is moved */
        }

        .ar-section {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 1.5rem;
            text-align: center;
            margin: 3rem auto 0;
            max-width: 400px;
            animation: slideUp 1s ease-out 0.2s backwards;
        }

        .ar-section h2 {
            color: var(--light-color);
            font-size: 1.5rem;
            margin-bottom: 0.8rem;
        }

        .ar-section p {
            font-size: 0.9rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .ar-btn {
            background: linear-gradient(45deg, #6b48ff, #ff2e63);
            color: var(--light-color);
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
            max-width: 200px;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .ar-btn::after {
            content: '';
            position: absolute;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            left: 0;
            top: 0;
            transition: width 0.3s ease;
        }

        .ar-btn:hover::after {
            width: 100%;
        }

        .ar-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(107, 72, 255, 0.6);
        }

        footer {
            background: var(--secondary-color);
            padding: 2rem 0;
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            gap: 2rem;
        }

        .footer-links a {
            color: var(--light-color);
            text-decoration: none;
            font-weight: 500;
            margin: 0 1.5rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        .social-links a {
            color: var(--light-color);
            margin: 0 1rem;
            font-size: 1.5rem;
            transition: color 0.3s ease, transform 0.2s ease;
        }

        .social-links a:hover {
            color: var(--primary-color);
            transform: scale(1.2);
        }

        @media (max-width: 1024px) {
            .content {
                flex-direction: column;
                align-items: center;
                gap: 2rem;
            }

            .event-details, .ticket-section {
                max-width: 100%;
            }

            .event-details {
                padding: 2rem;
            }

            .ticket-section {
                padding: 2rem;
            }

            .event-details img {
                max-height: 300px;
            }

            .button-container {
                flex-direction: column;
                align-items: center;
            }

            .closed-message {
                font-size: 0.9rem;
                max-width: 100%;
            }
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

            .notification-icon {
                font-size: 1.1rem;
            }

            .notification-icon .unread-count {
                font-size: 0.65rem;
                padding: 1px 5px;
            }

            .content {
                padding: 2rem 20px;
            }

            .event-details img {
                max-height: 250px;
            }

            .event-details h2 {
                font-size: 2rem;
            }

            .detail-card {
                padding: 1rem;
                gap: 1rem;
            }

            .detail-card p {
                font-size: 1rem;
            }

            .detail-card i {
                font-size: 1.5rem;
            }

            .ticket-btn, .share-btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
                max-width: 180px;
            }

            .share-btn i {
                font-size: 1.1rem;
            }

            .share-modal-content {
                width: 250px;
            }

            .ar-section {
                padding: 1rem;
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
                gap: 1.5rem;
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

            .message-btn .unread-count, .enquiry-btn .unread-count, .notification-icon .unread-count {
                font-size: 0.6rem;
                padding: 1px 4px;
                top: -6px;
                right: -6px;
            }

            .quick-actions {
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
            }

            .event-details img {
                max-height: 200px;
            }

            .event-details h2 {
                font-size: 1.7rem;
            }

            .detail-card p {
                font-size: 0.9rem;
            }

            .detail-card i {
                font-size: 1.3rem;
            }

            .ticket-btn, .share-btn {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }

            .closed-message {
                font-size: 0.85rem;
            }

            .share-modal-content {
                width: 200px;
            }

            .share-modal-content h3 {
                font-size: 1.1rem;
            }

            .share-modal-content .social-links a {
                font-size: 1.6rem;
            }

            .ar-btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }

            .footer-links a {
                margin: 0 1rem;
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
        document.addEventListener("DOMContentLoaded", function() {
            const ticketBtn = document.querySelector('.ticket-btn');
            if (ticketBtn) {
                ticketBtn.addEventListener('click', function(event) {
                    const isDisabled = ticketBtn.hasAttribute('disabled');
                    const ticketPriceNotSet = <?= is_null($event['ticket_price']) ? 'true' : 'false' ?>;
                    const bookingStatusClosed = '<?= $event['booking_status'] === 'closed' ? 'true' : 'false' ?>';
                    if (isDisabled && ticketPriceNotSet && !bookingStatusClosed) {
                        event.preventDefault();
                        alert("Dear Customer, We are fixing the best price for this event, hold on, check back after a few minutes.");
                    }
                });
            }

            // Share modal functionality with precise positioning
            function openShareModal(eventId, eventTitle, e) {
                const shareBtn = e.target.closest('.share-btn');
                const modal = document.getElementById('shareModal');
                const shareLinks = document.getElementById('shareLinks');
                if (!shareBtn || !modal || !shareLinks) return;

                const rect = shareBtn.getBoundingClientRect();
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
                const modalWidth = 300; // Default width
                const modalHeight = 200; // Approximate height

                // Center the modal below the share button
                let topOffset = rect.bottom + scrollTop + 10; // 10px below the button
                let leftOffset = rect.left + scrollLeft + (rect.width / 2) - (modalWidth / 2); // Center horizontally

                // Adjust for viewport boundaries
                const viewportWidth = window.innerWidth;
                if (leftOffset + modalWidth > viewportWidth) {
                    leftOffset = viewportWidth - modalWidth - 10; // 10px from right edge
                }
                if (leftOffset < 0) leftOffset = 10; // 10px from left edge

                const viewportHeight = window.innerHeight;
                if (topOffset + modalHeight > viewportHeight + scrollTop) {
                    topOffset = rect.top + scrollTop - modalHeight - 10; // Move above if it overflows
                }
                if (topOffset < scrollTop) topOffset = scrollTop + 10; // 10px from top

                // Apply positioning
                modal.style.top = `${topOffset}px`;
                modal.style.left = `${leftOffset}px`;
                modal.style.width = `${modalWidth}px`;

                const eventUrl = encodeURIComponent('<?= $base_url ?>?event_id=' + eventId);
                const eventText = encodeURIComponent(`Check out this event: ${eventTitle} - ${'<?= $base_url ?>?event_id=' + eventId}`);

                shareLinks.innerHTML = `
                    <a href="https://www.facebook.com/sharer/sharer.php?u=${eventUrl}" target="_blank" title="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/share?url=${eventUrl}" target="_blank" title="Share on Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://api.whatsapp.com/send?text=${eventText}" target="_blank" title="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                `;
                modal.style.display = 'block';

                // Adjust modal width on smaller screens
                if (window.innerWidth <= 768) {
                    modal.style.width = '250px';
                    modal.style.left = `${Math.max(10, (viewportWidth - 250) / 2)}px`; // Recenter
                }
                if (window.innerWidth <= 480) {
                    modal.style.width = '200px';
                    modal.style.left = `${Math.max(10, (viewportWidth - 200) / 2)}px`; // Recenter
                }
            }

            function closeShareModal() {
                const modal = document.getElementById('shareModal');
                if (modal) modal.style.display = 'none';
            }

            window.onclick = function(event) {
                const modal = document.getElementById('shareModal');
                if (modal && event.target === modal) closeShareModal();
            };

            // Quick actions toggle
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
                    const data = 'user_id=<?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '' ?>&user_type=<?= isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '' ?>';
                    xhr.send(data);
                }
            });

            // Expose functions to global scope for inline onclick
            window.openShareModal = openShareModal;
            window.closeShareModal = closeShareModal;
        });
    </script>
</head>
<body>
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
                    <?php if ($unreadAdminCount > 0): ?>
                        <span class="quick-actions-badge"><?= $unreadAdminCount ?></span>
                    <?php endif; ?>
                </a>
                <div class="quick-actions-dropdown" id="quickActionsDropdown">
                    <!-- Notification content would be populated dynamically if needed -->
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="content">
        <div class="event-details">
            <img src="<?= htmlspecialchars($event['image_path'] ?? 'images/default-event.jpg') ?>" 
                 alt="<?= htmlspecialchars($event['title']) ?>" 
                 onerror="this.src='images/default-event.jpg';">
            <h2><?= htmlspecialchars($event['title']) ?></h2>
            <div class="detail-card">
                <i class="fas fa-align-left"></i>
                <p><strong>Description:</strong> <?= htmlspecialchars($event['description'] ?? 'No description available') ?></p>
            </div>
            <div class="detail-card">
                <i class="fas fa-map-marker-alt"></i>
                <p><strong>Venue:</strong> <?= htmlspecialchars($event['venue_name']) ?></p>
            </div>
            <div class="detail-card">
                <i class="fas fa-map-signs"></i>
                <p><strong>Address:</strong> <?= htmlspecialchars($event['address']) ?> 
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($event['address']) ?>" 
                       target="_blank" class="map-btn">
                        <i class="fas fa-map-pin"></i> Navigate
                    </a>
                </p>
            </div>
            <div class="detail-card">
                <i class="fas fa-calendar-alt"></i>
                <p><strong>Date & Time:</strong> <?= date('M j, Y g:i A', strtotime($event['start_datetime'])) ?></p>
            </div>
            <div class="detail-card">
                <i class="fas fa-ticket-alt"></i>
                <?php if (!is_null($event['ticket_price']) && in_array($event['set_by_type'], ['system_admin', 'venue_admin'])): ?>
                    <p><strong>Ticket Price:</strong> $<?= number_format($event['ticket_price'], 2) ?> per adult</p>
                <?php else: ?>
                    <p><strong>Ticket Price:</strong> Not yet set</p>
                <?php endif; ?>
            </div>
            <div class="detail-card">
                <i class="fas fa-info-circle"></i>
                <p><strong>Booking Status:</strong> <?= ucfirst($event['booking_status']) ?></p>
            </div>
            <div class="highlight-box">
                <h3>Event Highlights</h3>
                <p>Experience an unforgettable night with live performances, exclusive VIP access, and stunning visuals. Don’t miss out!</p>
            </div>
            <div class="button-container">
                <?php if ($event['booking_status'] === 'closed'): ?>
                    <p class="closed-message">
                        Ticket has been closed for this event. Contact admin here: 
                        <a href="contact.php">Contact here</a>
                    </p>
                <?php else: ?>
                    <a href="book.php?event_id=<?= htmlspecialchars($event['event_id']) ?>" class="ticket-btn"
                       <?php if (is_null($event['ticket_price']) || !in_array($event['set_by_type'], ['system_admin', 'venue_admin'])): ?>
                           disabled
                           title="Ticket price not set or not available."
                       <?php endif; ?>>
                        <i class="fas fa-ticket-alt"></i> Get Ticket Now
                    </a>
                <?php endif; ?>
                <button class="share-btn" onclick="openShareModal('<?= htmlspecialchars($event['event_id']) ?>', '<?= htmlspecialchars($event['title']) ?>', event)">
                    Share this Event <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <section class="ar-section">
        <h2>Explore with AR Navigation</h2>
        <p>Navigate <?= htmlspecialchars($event['venue_name']) ?> in real-time using Augmented Reality! Find event stages, facilities, and more with ease.</p>
        <a href="ar_navigation.php?venue_id=<?= htmlspecialchars($event['venue_id']) ?>" class="ar-btn">
            <i class="fas fa-camera-ar"></i> Launch AR Navigation
        </a>
    </section>

    <div class="share-modal" id="shareModal">
        <div class="share-modal-content">
            <span class="close-modal" onclick="closeShareModal()">×</span>
            <h3>Share This Event</h3>
            <div class="social-links" id="shareLinks">
                <!-- Social links will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>© <?= date('Y') ?> EventHub. All Rights Reserved.</p>
            <div class="footer-links">
                <a href="booking.php?category=related">See Other Related Events</a>
                <a href="terms_conditions">Terms and conditions</a>
            </div>
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