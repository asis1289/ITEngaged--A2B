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

// Fetch list of venue admins for system admin message form
$venue_admins = [];
$show_venue_admin_list = false;
$message_to_send = '';
if ($user_type === 'system_admin') {
    $query = "SELECT user_id, full_name FROM users WHERE user_type = 'venue_admin'";
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $venue_admins[] = $row;
        }
    }
}

// Handle initial message submission for system admin to show venue admin list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $user_type === 'system_admin') {
    if (!empty($_POST['message'])) {
        $message_to_send = $db->real_escape_string($_POST['message']);
        $show_venue_admin_list = true;
    } else {
        $_SESSION['event_message'] = "Please enter a message.";
        $_SESSION['event_action'] = 'rejected';
        header("Location: message_between_admins.php");
        exit;
    }
}

// Handle final message submission to a specific venue admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to']) && $user_type === 'system_admin') {
    $message = $db->real_escape_string($_POST['message']);
    $venue_admin_id = (int)$_POST['venue_admin_id'];
    $system_admin_id = $user_id;
    $sender_type = 'system_admin';

    $query = "INSERT INTO notifications (venue_admin_id, system_admin_id, sender_type, message) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("iiss", $venue_admin_id, $system_admin_id, $sender_type, $message);
    if ($stmt->execute()) {
        $_SESSION['event_message'] = "Message sent successfully!";
        $_SESSION['event_action'] = 'approved';
    } else {
        $_SESSION['event_message'] = "Error sending message: " . $db->error;
        $_SESSION['event_action'] = 'rejected';
    }
    $stmt->close();
    $show_venue_admin_list = false;
    header("Location: message_between_admins.php");
    exit;
}

// Handle message submission to all venue admins
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_all']) && $user_type === 'system_admin') {
    $message = $db->real_escape_string($_POST['message']);
    $system_admin_id = $user_id;
    $sender_type = 'system_admin';
    $success = true;

    foreach ($venue_admins as $admin) {
        $venue_admin_id = $admin['user_id'];
        $query = "INSERT INTO notifications (venue_admin_id, system_admin_id, sender_type, message) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->bind_param("iiss", $venue_admin_id, $system_admin_id, $sender_type, $message);
        if (!$stmt->execute()) {
            $success = false;
            break;
        }
        $stmt->close();
    }

    if ($success) {
        $_SESSION['event_message'] = "Message sent to all venue admins successfully!";
        $_SESSION['event_action'] = 'approved';
    } else {
        $_SESSION['event_message'] = "Error sending message: " . $db->error;
        $_SESSION['event_action'] = 'rejected';
    }
    $show_venue_admin_list = false;
    header("Location: message_between_admins.php");
    exit;
}

// Handle message submission for venue admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $user_type === 'venue_admin') {
    $message = $db->real_escape_string($_POST['message']);
    $recipient_ids = [];
    $query = "SELECT user_id FROM users WHERE user_type = 'system_admin'";
    $result = $db->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recipient_ids[] = $row['user_id'];
        }
    }
    $sender_type = 'venue_admin';
    $system_admin_id = null;
    $venue_admin_id = $user_id;

    $success = true;
    foreach ($recipient_ids as $recipient_id) {
        $query = "INSERT INTO notifications (venue_admin_id, system_admin_id, sender_type, message) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->bind_param("iiss", $venue_admin_id, $recipient_id, $sender_type, $message);
        if (!$stmt->execute()) {
            $success = false;
            break;
        }
        $stmt->close();
    }

    if ($success) {
        $_SESSION['event_message'] = "Message sent successfully!";
        $_SESSION['event_action'] = 'approved';
    } else {
        $_SESSION['event_message'] = "Error sending message: " . $db->error;
        $_SESSION['event_action'] = 'rejected';
    }
    header("Location: message_between_admins.php");
    exit;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Message - EventHub</title>
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
            justify-content: center;
            align-items: center;
        }

        .container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 90%;
            max-width: 600px;
            text-align: center;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }

        .close-container {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            color: var(--light-color);
            cursor: pointer;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .close-container:hover {
            color: var(--accent-color);
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }

        .message-form {
            width: 100%;
            text-align: center;
        }

        .message-form textarea {
            width: 100%;
            height: 150px;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(0, 0, 0, 0.2);
            color: var(--light-color);
            font-size: 1rem;
            resize: vertical;
        }

        .message-form button {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .message-form button:hover {
            background: #5a3de6;
            transform: translateY(-2px);
        }

        .recipient-list {
            margin-top: 20px;
            text-align: left;
        }

        .recipient-list p {
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .recipient-list button {
            background: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .recipient-list button:hover {
            background: #5a3de6;
        }

        .send-all-container {
            margin-top: 10px;
            text-align: center;
        }

        .message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            text-align: center;
            z-index: 1002;
            box-shadow: var(--shadow);
            color: white;
            animation: fadeInOut 3s ease-in-out forwards;
            max-width: 90%;
        }

        .message.success {
            background: var(--success-bg);
        }

        .message.error {
            background: var(--notification-bg);
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }

        .back-btn {
            display: inline-block;
            margin-top: 2rem;
            background: var(--secondary-color);
            color: var(--light-color);
            padding: 0.9rem 2rem;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: #16182a;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .message-form textarea {
                height: 120px;
                font-size: 0.9rem;
            }

            .message-form button {
                padding: 8px 16px;
            }

            .recipient-list button {
                padding: 4px 8px;
            }

            .message {
                font-size: 0.9rem;
                padding: 0.8rem 1.5rem;
                max-width: 90%;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 1rem;
            }

            h2 {
                font-size: 1.2rem;
            }

            .message-form textarea {
                height: 100px;
                font-size: 0.85rem;
            }

            .message-form button {
                padding: 6px 12px;
            }

            .recipient-list button {
                padding: 3px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php" class="close-container" id="closeContainerBtn">×</a>
        <?php if ($user_type === 'venue_admin'): ?>
            <h2>Send Message to System Admin</h2>
            <div class="message-form">
                <form method="POST">
                    <textarea name="message" placeholder="Type a message to system admin..." required></textarea>
                    <button type="submit" name="send_message">Send</button>
                </form>
            </div>
        <?php elseif ($user_type === 'system_admin'): ?>
            <h2>Send Message to Venue Admins</h2>
            <div class="message-form">
                <?php if (!$show_venue_admin_list): ?>
                    <form method="POST">
                        <textarea name="message" placeholder="Type a message to venue admins..." required></textarea>
                        <button type="submit" name="send_message">Next: Select Recipients</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <textarea name="message" required><?= htmlspecialchars($message_to_send) ?></textarea>
                        <div class="recipient-list">
                            <p><strong>Select a Venue Admin:</strong></p>
                            <?php foreach ($venue_admins as $admin): ?>
                                <p>
                                    <?= htmlspecialchars($admin['full_name']) ?>
                                    <button type="submit" name="send_to" value="<?= $admin['user_id'] ?>">Send</button>
                                    <input type="hidden" name="venue_admin_id" value="<?= $admin['user_id'] ?>">
                                </p>
                            <?php endforeach; ?>
                            <div class="send-all-container">
                                <button type="submit" name="send_to_all">Send to All</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($event_message)): ?>
            <div class="message <?= $event_action === 'approved' ? 'success' : 'error' ?>" id="event-message">
                <?= htmlspecialchars($event_message) ?>
            </div>
        <?php endif; ?>
        <a href="admin.php" class="back-btn">Back to Admin Panel</a>
    </div>

    <script>
        // Close container with close button
        document.getElementById('closeContainerBtn').addEventListener('click', function() {
            window.location.href = 'admin.php';
        });

        // Close container if clicking outside
        window.onclick = function(event) {
            const container = document.querySelector('.container');
            if (event.target === container) {
                window.location.href = 'admin.php';
            }
        };
    </script>
</body>
</html>
<?php $db->close(); ?>