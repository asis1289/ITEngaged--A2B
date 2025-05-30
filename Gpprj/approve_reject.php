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
$message = '';
$message_type = '';

// Database connection
require_once 'Connection/sql_auth.php';

// Handle Approve/Reject Events
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id']) && isset($_POST['status'])) {
    $event_id = (int)$_POST['event_id'];
    $status = $db->real_escape_string($_POST['status']);
    
    // Fetch the event to get the created_by_id (venue_admin_id) and title
    $event_query = "SELECT created_by_id, title FROM events WHERE event_id = ? AND created_by_type = 'venue_admin'";
    $stmt = $db->prepare($event_query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $event_result = $stmt->get_result();
    $event = $event_result->fetch_assoc();
    $stmt->close();

    if ($event) {
        $venue_admin_id = $event['created_by_id'];
        $event_title = $event['title'];

        // Update event status
        $update_query = "UPDATE events SET status = ? WHERE event_id = ? AND created_by_type != 'system_admin'";
        $stmt = $db->prepare($update_query);
        $stmt->bind_param("si", $status, $event_id);
        if ($stmt->execute()) {
            // Set success/reject message with event title
            if ($status === 'approved') {
                $message = "Event '$event_title' Successfully approved";
                $message_type = 'success';
            } else {
                $message = "Event '$event_title' rejected";
                $message_type = 'error';
            }

            // Insert notification into the notifications table with event title
            $system_admin_id = ($user_type === 'system_admin') ? $user_id : null;
            $sender_type = 'system_admin'; // Default as per table structure
            $notification_query = "INSERT INTO notifications (venue_admin_id, system_admin_id, sender_type, message, created_at, read_status) 
                                  VALUES (?, ?, ?, ?, NOW(), 0)";
            $stmt = $db->prepare($notification_query);
            $stmt->bind_param("iiss", $venue_admin_id, $system_admin_id, $sender_type, $message);
            if (!$stmt->execute()) {
                error_log("Failed to insert notification: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $message = "Error updating event status: " . $stmt->error;
            $message_type = 'error';
        }
        
    } else {
        $message = "Event not found or not created by a venue admin.";
        $message_type = 'error';
    }
}

// Fetch Pending Events for Review (created by venue admins)
$pendingEvents = [];
$query = "SELECT e.event_id, e.title, e.description, e.start_datetime, e.image_path, e.created_by_type, e.created_by_id, 
          v.name AS venue_name, v.address, u.full_name AS created_by_name 
          FROM events e 
          JOIN venues v ON e.venue_id = v.venue_id 
          JOIN users u ON e.created_by_id = u.user_id 
          WHERE e.status = 'pending' AND e.created_by_type = 'venue_admin'";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingEvents[] = $row;
    }
}
$result->free();

// Count pending events for notification
$pendingCount = $db->query("SELECT COUNT(*) as count FROM events WHERE status = 'pending' AND created_by_type = 'venue_admin'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Events - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff; /* Vibrant purple - Matches other files */
            --secondary-color: #1e1e2f; /* Dark navy - Matches other files */
            --accent-color: #ff2e63; /* Bright pink - Matches other files */
            --light-color: #f5f5f5; /* Off-white - Matches other files */
            --glass-bg: rgba(255, 255, 255, 0.1); /* Matches other files */
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2); /* Matches other files */
            --success-bg: #28a745; /* Green for success - Matches other files */
            --error-bg: #ff2e63; /* Red for error - Matches other files */
            --danger-color: #dc3545; /* Red for reject - Matches other files */
            --notification-color: #ff4444; /* Matches the notification style */
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
            max-width: 900px;
            text-align: center;
            max-height: 80vh; /* Fixed height for scrolling */
            overflow-y: auto; /* Enable vertical scrolling */
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

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary-color);
            color: var(--light-color);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: var(--shadow);
            font-weight: 600;
            z-index: 1000;
            animation: slideIn 0.5s ease-out;
        }

        .notification .count {
            background: var(--notification-color);
            color: var(--light-color);
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }

        @keyframes slideIn {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--light-color);
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            padding: 1rem;
        }

        .event-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: left;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .event-image {
            flex-shrink: 0;
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            background: #333;
        }

        .event-details {
            flex-grow: 1;
        }

        .event-card h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--light-color);
        }

        .event-card p {
            font-size: 1rem;
            margin: 0.3rem 0;
            color: rgba(255, 255, 255, 0.8);
        }

        .event-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
        }

        .action-btn {
            background: var(--primary-color);
            color: var(--light-color);
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 1rem;
        }

        .action-btn.reject {
            background: var(--danger-color);
        }

        .action-btn:hover {
            background: #5a3de6; /* Darker purple to match other files */
        }

        .action-btn.reject:hover {
            background: #c82333; /* Darker red to match other files */
        }

        .no-data-message {
            font-style: italic;
            margin-top: 1rem;
            font-size: 1.1rem;
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
            background: #16182a; /* Matches other files */
        }

        .message-box {
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
            max-width: 90%;
        }

        .message-success {
            background: var(--success-bg);
        }

        .message-error {
            background: var(--error-bg);
        }

        @media (max-width: 768px) {
            .container {
                width: 95%;
                padding: 1.5rem;
            }

            .notification {
                top: 10px;
                right: 10px;
                padding: 0.8rem 1.2rem;
                font-size: 0.9rem;
            }

            .notification .count {
                width: 18px;
                height: 18px;
                font-size: 0.8rem;
            }

            .events-list {
                padding: 0.5rem;
            }

            .event-card {
                flex-direction: column;
                align-items: stretch;
                padding: 1rem;
            }

            .event-image {
                width: 100%;
                height: 120px;
                margin-bottom: 1rem;
            }

            .event-details {
                text-align: center;
            }

            .event-actions {
                flex-direction: column;
                gap: 0.8rem;
            }

            .action-btn {
                width: 100%;
                padding: 0.7rem;
                font-size: 0.9rem;
            }

            .message-box {
                font-size: 0.9rem;
                padding: 0.8rem 1.5rem;
                max-width: 90%;
            }
        }

        @media (max-width: 480px) {
            h2 {
                font-size: 1.8rem;
            }

            .event-card h3 {
                font-size: 1.3rem;
            }

            .event-card p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php if ($pendingCount > 0): ?>
        <div class="notification">
            Pending Events <span class="count"><?= $pendingCount ?></span>
        </div>
    <?php endif; ?>

    <div class="container">
        <a href="admin.php" class="close-container" id="closeContainerBtn">×</a>
        <h2>Review Events</h2>
        <div class="events-list">
            <?php if (empty($pendingEvents)): ?>
                <p class="no-data-message">No pending events to review.</p>
            <?php else: ?>
                <?php foreach ($pendingEvents as $event): ?>
                    <div class="event-card">
                        <img src="<?= htmlspecialchars($event['image_path'] ?? 'images/default-event.jpg') ?>" 
                             alt="<?= htmlspecialchars($event['title']) ?>" 
                             class="event-image" 
                             onerror="this.src='images/default-event.jpg';">
                        <div class="event-details">
                            <h3><?= htmlspecialchars($event['title']) ?></h3>
                            <p><strong>Description:</strong> <?= htmlspecialchars($event['description'] ?? 'No description') ?></p>
                            <p><strong>Venue:</strong> <?= htmlspecialchars($event['venue_name']) ?> (<?= htmlspecialchars($event['address']) ?>)</p>
                            <p><strong>Date & Time:</strong> <?= date('M j, Y g:i A', strtotime($event['start_datetime'])) ?></p>
                            <p><strong>Created By:</strong> <?= htmlspecialchars($event['created_by_name']) ?> (<?= htmlspecialchars($event['created_by_type']) ?>)</p>
                            <div class="event-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="action-btn">Approve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="action-btn reject">Reject</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="admin.php" class="back-btn">Back to Admin Panel</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message-box <?= $message_type === 'success' ? 'message-success' : 'message-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <script>
            // Refresh the page after 2 seconds
            setTimeout(function() {
                window.location.href = 'approve_reject.php';
            }, 2000);
        </script>
    <?php endif; ?>

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
        }
    </script>
</body>
</html>
<?php $db->close(); ?>