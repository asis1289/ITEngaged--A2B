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

// Handle Add Event Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $title = $db->real_escape_string($_POST['title']);
    $description = $db->real_escape_string($_POST['description']);
    $venue_id = (int)$_POST['venue_id'];
    $start_datetime = $db->real_escape_string($_POST['start_datetime']);
    $image_path = $_FILES['image']['name'] ? 'images/' . $_FILES['image']['name'] : null;
    $created_by_type = $user_type;
    $created_by_id = $user_id;
    $status = $user_type === 'system_admin' ? 'approved' : 'pending';

    if ($image_path && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "images/";
        $target_file = $target_dir . basename($_FILES['image']['name']);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['png', 'jpg', 'jpeg'];
        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $query = "INSERT INTO events (title, description, venue_id, start_datetime, image_path, created_by_type, created_by_id, status, created_at) 
                          VALUES ('$title', '$description', $venue_id, '$start_datetime', '$image_path', '$created_by_type', $created_by_id, '$status', NOW())";
                if ($db->query($query)) {
                    $message = "Event added successfully! " . ($status === 'pending' ? "Awaiting approval." : "");
                    $message_type = 'success';
                } else {
                    $message = "Error adding event: " . $db->error;
                    $message_type = 'error';
                }
            } else {
                $message = "Error uploading image.";
                $message_type = 'error';
            }
        } else {
            $message = "Only PNG, JPG, and JPEG files are allowed.";
            $message_type = 'error';
        }
    } else {
        $message = "Image is required for adding an event.";
        $message_type = 'error';
    }
}

// Fetch Venues for Dropdown
$venues = [];
$result = $db->query("SELECT venue_id, name FROM venues");
if ($result) while ($row = $result->fetch_assoc()) $venues[] = $row;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event - EventHub</title>
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
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            display: flex; /* Show modal by default */
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
            border: none;
            padding: 0.8rem 0;
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

        small {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            display: block;
            margin: 0.5rem 0;
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
            const dateInput = document.querySelector('input[type="datetime-local"]');
            if (dateInput) {
                const today = new Date().toISOString().slice(0, 16);
                dateInput.min = today;
            }

            const modal = document.getElementById('addEventModal');
            const closeModalBtn = document.getElementById('closeModalBtn');

            closeModalBtn.addEventListener('click', function() {
                window.location.href = 'admin.php';
            });

            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    window.location.href = 'admin.php';
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <!-- Modal for Add Event Form -->
        <div class="modal" id="addEventModal">
            <div class="modal-content">
                <a href="admin.php" class="close-modal" id="closeModalBtn">×</a>
                <h2>Add New Event</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="text" name="title" placeholder="Event Title" required>
                    <textarea name="description" placeholder="Description" required></textarea>
                    <select name="venue_id" required>
                        <option value="">Select Venue</option>
                        <?php foreach ($venues as $venue): ?>
                            <option value="<?= $venue['venue_id'] ?>"><?= htmlspecialchars($venue['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="datetime-local" name="start_datetime" required>
                    <input type="file" name="image" accept="image/png,image/jpg,image/jpeg" required>
                    <small>Hint: Save images to path: images/(image_name).(png/jpg/jpeg)</small>
                    <button type="submit" name="add_event">Add Event</button>
                </form>
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