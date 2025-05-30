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
    header("Location: login.php");
    exit;
}

// Initialize notification preference if not set
if (!isset($_SESSION['notification_preference'])) {
    $_SESSION['notification_preference'] = 'opt-in'; // Default to opt-in
}

// Mark notifications as viewed in the database
$user_id = $_SESSION['user_id'];
$updateQuery = "UPDATE users SET notifications_viewed = NOW() WHERE user_id = ?";
$updateStmt = $db->prepare($updateQuery);
$updateStmt->bind_param("i", $user_id);
$updateStmt->execute();
$updateStmt->close();

// Fetch notifications (events created by venue_admin or system_admin)
$notifications = [];
if ($_SESSION['notification_preference'] === 'opt-in') {
    $query = "SELECT e.*, v.name as venue_name, v.address 
              FROM events e 
              JOIN venues v ON e.venue_id = v.venue_id 
              WHERE e.start_datetime > NOW() 
              AND e.status = 'approved' 
              AND e.created_by_type IN ('system_admin', 'venue_admin') 
              ORDER BY e.created_at DESC";
    $result = $db->query($query);
    if ($result) {
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    }
}

// Handle notification preference toggle
$successMessage = '';
if (isset($_POST['toggle_notifications'])) {
    $_SESSION['notification_preference'] = $_SESSION['notification_preference'] === 'opt-in' ? 'opt-out' : 'opt-in';
    $successMessage = "Notification preference updated successfully to '" . $_SESSION['notification_preference'] . "'!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Notifications - EventHub</title>
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

        header {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            justify-content: center;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .logo img {
            height: 80px;
            vertical-align: middle;
            transition: transform 0.2s ease;
        }

        .logo img:hover {
            transform: scale(1.15);
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .popup-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: var(--accent-color);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #ff5c7a;
        }

        .toggle-section {
            margin-bottom: 2rem;
            text-align: center;
        }

        .toggle-btn {
            display: inline-block;
            background: <?= $_SESSION['notification_preference'] === 'opt-in' ? '#ff9500' : '#666' ?>;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .toggle-btn:hover {
            background: <?= $_SESSION['notification_preference'] === 'opt-in' ? '#e08600' : '#555' ?>;
            transform: translateY(-2px);
        }

        .success {
            color: var(--success-color);
            background: rgba(40, 167, 69, 0.2);
            backdrop-filter: blur(5px);
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .success.fade-out {
            opacity: 0;
        }

        h2 {
            color: var(--light-color);
            margin-bottom: 2rem;
            font-size: 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        .notification {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .notification img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }

        .notification-content {
            flex: 1;
        }

        .notification-content h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .notification-content p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.3rem;
        }

        .book-btn {
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
        }

        .book-btn:hover {
            background: #5a3de6;
            transform: translateY(-2px);
        }

        .no-data-message {
            font-style: italic;
            font-size: 1rem;
            color: var(--light-color);
            text-align: center;
        }

        @media (max-width: 768px) {
            .notification {
                flex-direction: column;
                text-align: center;
            }

            .notification img {
                width: 80px;
                height: 80px;
            }

            .notification-content h3 {
                font-size: 1.1rem;
            }

            .notification-content p {
                font-size: 0.85rem;
            }

            .toggle-btn, .book-btn {
                font-size: 0.9rem;
                padding: 0.4rem 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <a href="index.php" class="logo">
                <img src="images/a2b.png" alt="EventHub Logo" onerror="this.src=''; this.alt='EventHub';">
            </a>
        </div>
    </header>

    <!-- Notification Popup -->
    <div class="popup-overlay">
        <div class="popup-content">
            <a href="index.php" class="close-btn" title="Close Notifications">×</a>
            <div class="toggle-section">
                <form method="POST">
                    <button type="submit" name="toggle_notifications" class="toggle-btn">
                        Notifications: <?= $_SESSION['notification_preference'] === 'opt-in' ? 'On' : 'Off' ?>
                    </button>
                </form>
                <?php if ($successMessage): ?>
                    <p class="success" id="success-message"><?= htmlspecialchars($successMessage) ?></p>
                    <script>
                        if (document.getElementById('success-message')) {
                            setTimeout(() => {
                                document.getElementById('success-message').classList.add('fade-out');
                            }, 2000);
                        }
                    </script>
                <?php endif; ?>
            </div>
            <h2>Event Notifications</h2>
            <?php if (empty($notifications) || $_SESSION['notification_preference'] === 'opt-out'): ?>
                <p class="no-data-message">
                    <?php if ($_SESSION['notification_preference'] === 'opt-out'): ?>
                        Notifications are turned off. Click the button above to enable them.
                    <?php else: ?>
                        No new event notifications available.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification">
                        <img src="<?= htmlspecialchars($notification['image_path'] ?? 'images/default-event.jpg') ?>" 
                             alt="<?= htmlspecialchars($notification['title']) ?>" 
                             onerror="this.src='images/default-event.jpg';">
                        <div class="notification-content">
                            <h3><?= htmlspecialchars($notification['title']) ?></h3>
                            <p><strong>Details:</strong> <?= htmlspecialchars($notification['description'] ?? 'No description available') ?></p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($notification['venue_name']) ?>, <?= htmlspecialchars($notification['address']) ?></p>
                            <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($notification['start_datetime'])) ?></p>
                            <a href="event_details.php?event_id=<?= htmlspecialchars($notification['event_id']) ?>" class="book-btn">Book</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $db->close(); ?>