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

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch event data from URL parameters
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if ($event_id <= 0) {
    header("Location: event_list.php");
    exit;
}

// Fetch event details with venue_admin_id
$query = "SELECT e.event_id, e.title, e.description, e.image_path, e.status, e.created_by_type, e.created_by_id, e.booking_status, e.start_datetime, v.venue_id, v.venue_admin_id 
          FROM events e 
          LEFT JOIN venues v ON e.venue_id = v.venue_id 
          WHERE e.event_id = $event_id";
if ($user_type === 'venue_admin') {
    $query .= " AND ( (e.created_by_type = 'venue_admin' AND e.created_by_id = $user_id) OR e.created_by_type = 'system_admin' )";
}
$result = $db->query($query);
if ($result && $result->num_rows > 0) {
    $event = $result->fetch_assoc();
} else {
    $message = "Event not found or you do not have permission to edit this event.";
    $message_type = 'error';
    $event = null;
}

// Fetch venue admins (only for system_admin)
$venue_admins = [];
if ($user_type === 'system_admin') {
    $admin_query = "SELECT va.venue_admin_id, va.full_name 
                    FROM venue_admins va 
                    JOIN users u ON va.username = u.username AND u.user_type = 'venue_admin'";
    $admin_result = $db->query($admin_query);
    if ($admin_result) {
        while ($row = $admin_result->fetch_assoc()) {
            $venue_admins[] = $row;
        }
    } else {
        $message = "Error fetching venue admins: " . $db->error;
        $message_type = 'error';
    }
}

// Handle Edit Event Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event']) && $event) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid CSRF token.";
        $message_type = 'error';
    } else {
        $title = $db->real_escape_string($_POST['title']);
        $description = $db->real_escape_string($_POST['description']);
        $status = $event['status']; // Retain existing status for venue_admin
        $booking_status = $event['booking_status']; // Retain existing booking_status for venue_admin
        if ($user_type === 'system_admin') {
            $status = $db->real_escape_string($_POST['status']);
            $booking_status = $db->real_escape_string($_POST['booking_status']);
        }
        $start_datetime = $db->real_escape_string($_POST['start_datetime']);
        $venue_id = (int)$_POST['venue_id'];
        $venue_admin_id = $user_type === 'system_admin' ? (int)$_POST['venue_admin_id'] : $event['venue_admin_id'];

        // Handle image path
        $image_path = $event['image_path']; // Default to existing image path
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'images/';
            $original_name = basename($_FILES['image']['name']);
            $image_file_type = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($image_file_type, $allowed_types)) {
                // Construct the image path assuming the file is already in images/
                $image_path = $upload_dir . $original_name;
            } else {
                $message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
                $message_type = 'error';
            }
        }

        // Validate start_datetime
        $start_datetime_obj = DateTime::createFromFormat('Y-m-d\TH:i', $start_datetime);
        $current_datetime = new DateTime();
        if (!$start_datetime_obj || $start_datetime_obj < $current_datetime) {
            $message = "Start date and time must be in the future.";
            $message_type = 'error';
        } else {
            // Update the event and venue association
            $db->begin_transaction();
            try {
                $venue_update = $user_type === 'system_admin' ? ", venue_admin_id = $venue_admin_id" : "";
                $update_query = "UPDATE events e 
                              LEFT JOIN venues v ON e.venue_id = v.venue_id 
                              SET e.title='$title', e.description='$description', e.image_path='$image_path', 
                                  e.status='$status', e.booking_status='$booking_status', e.start_datetime='$start_datetime', 
                                  e.venue_id=$venue_id $venue_update 
                              WHERE e.event_id=$event_id";
                if ($db->query($update_query)) {
                    $db->commit();
                    $message = "Event updated successfully!";
                    $message_type = 'success';
                    // Re-fetch updated event data
                    $select_query = "SELECT e.event_id, e.title, e.description, e.image_path, e.status, e.created_by_type, e.created_by_id, e.booking_status, e.start_datetime, v.venue_id, v.venue_admin_id 
                                   FROM events e 
                                   LEFT JOIN venues v ON e.venue_id = v.venue_id 
                                   WHERE e.event_id = $event_id";
                    if ($user_type === 'venue_admin') {
                        $select_query .= " AND ( (e.created_by_type = 'venue_admin' AND e.created_by_id = $user_id) OR e.created_by_type = 'system_admin' )";
                    }
                    $result = $db->query($select_query);
                    if ($result && $result->num_rows > 0) {
                        $event = $result->fetch_assoc();
                    }
                } else {
                    throw new Exception("Error updating event: " . $db->error);
                }
            } catch (Exception $e) {
                $db->rollback();
                $message = $e->getMessage();
                $message_type = 'error';
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
    <title>Edit Event - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff; /* Vibrant purple */
            --secondary-color: #1e1e2f; /* Dark navy */
            --accent-color: #ff2e63; /* Bright pink */
            --light-color: #f5f5f5; /* Off-white */
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-bg: #28a745; /* Green for success */
            --error-bg: #ff2e63; /* Red for error */
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
            text-align: center;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7); /* Matches add_event.php */
            z-index: 1000;
            justify-content: center;
            align-items: center;
            display: flex;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 90%;
            max-width: 600px;
            text-align: center;
            position: relative;
            color: var(--light-color);
            max-height: 80vh; /* Fixed height for scrolling */
            overflow-y: auto; /* Enable vertical scrolling */
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            color: var(--light-color);
            cursor: pointer;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .close-modal:hover {
            color: var(--accent-color);
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--light-color);
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        input, select, textarea {
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            width: 100%;
            box-sizing: border-box;
        }

        input[type="file"] {
            padding: 0.5rem;
            background: transparent;
            border: none;
            color: var(--light-color);
        }

        input::placeholder, textarea::placeholder, select {
            color: rgba(255, 255, 255, 0.7);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        select option {
            background: var(--secondary-color);
            color: var(--light-color);
        }

        button {
            background: var(--primary-color);
            color: var(--light-color);
            padding: 0.9rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
            font-size: 1.1rem;
        }

        button:hover {
            background: #5a3de6;
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
            animation: fadeInOut 3s ease-in-out;
            max-width: 90%;
        }

        .message-success {
            background: var(--success-bg);
        }

        .message-error {
            background: var(--error-bg);
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; }
        }

        @media (max-width: 768px) {
            .modal-content {
                padding: 1.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            input, select, textarea, button {
                font-size: 0.9rem;
                padding: 0.7rem;
            }

            .message-box {
                font-size: 0.9rem;
                padding: 0.8rem 1.5rem;
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.querySelector('.modal');
            const closeModalBtn = document.getElementById('closeModalBtn');

            // Show modal on load
            modal.style.display = 'flex';

            // Close modal with cross sign
            closeModalBtn.addEventListener('click', function() {
                window.location.href = 'event_list.php';
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    window.location.href = 'event_list.php';
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="modal">
            <div class="modal-content">
                <a href="event_list.php" class="close-modal" id="closeModalBtn">×</a>
                <h2>Edit Event</h2>
                <?php if ($event): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                        <input type="text" name="title" value="<?= htmlspecialchars($event['title']) ?>" placeholder="Event Title" required>
                        <textarea name="description" placeholder="Description" required><?= htmlspecialchars($event['description']) ?></textarea>
                        <input type="file" name="image" accept="image/*">
                        <p>Current Image: <a href="<?= htmlspecialchars($event['image_path']) ?>" target="_blank"><?= htmlspecialchars(basename($event['image_path'])) ?></a></p>
                        <?php if ($user_type === 'system_admin'): ?>
                            <select name="status" required>
                                <option value="pending" <?= $event['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $event['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $event['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <select name="booking_status" required>
                                <option value="open" <?= $event['booking_status'] === 'open' ? 'selected' : '' ?>>Open</option>
                                <option value="closed" <?= $event['booking_status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($event['status']) ?>">
                            <input type="hidden" name="booking_status" value="<?= htmlspecialchars($event['booking_status']) ?>">
                            <p>Status: <?= htmlspecialchars($event['status']) ?></p>
                            <p>Booking Status: <?= htmlspecialchars($event['booking_status']) ?></p>
                        <?php endif; ?>
                        <input type="datetime-local" name="start_datetime" value="<?= date('Y-m-d\TH:i', strtotime($event['start_datetime'])) ?>" required>
                        <select name="venue_id" required>
                            <option value="">Select Venue</option>
                            <?php 
                            $venue_query = "SELECT venue_id, name FROM venues";
                            if ($user_type === 'venue_admin') {
                                $venue_query .= " WHERE venue_admin_id = $user_id";
                            }
                            $venue_result = $db->query($venue_query);
                            if ($venue_result) {
                                while ($row = $venue_result->fetch_assoc()) {
                                    $selected = $event['venue_id'] == $row['venue_id'] ? 'selected' : '';
                                    echo "<option value='" . $row['venue_id'] . "' $selected>" . htmlspecialchars($row['name']) . " (ID: " . $row['venue_id'] . ")</option>";
                                }
                            }
                            ?>
                        </select>
                        <?php if ($user_type === 'system_admin' && !empty($venue_admins)): ?>
                            <select name="venue_admin_id" required>
                                <option value="">Select Venue Admin</option>
                                <?php foreach ($venue_admins as $admin): ?>
                                    <option value="<?= $admin['venue_admin_id'] ?>" <?= $event['venue_admin_id'] == $admin['venue_admin_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($admin['full_name']) ?> (ID: <?= $admin['venue_admin_id'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <button type="submit" name="edit_event">Update Event</button>
                    </form>
                <?php else: ?>
                    <p class="message-error"><?= htmlspecialchars($message) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message-box <?= $message_type === 'success' ? 'message-success' : 'message-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
</body>
</html>
<?php $db->close(); ?>