<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include QR code library
require_once 'lib/phpqrcode/qrlib.php';

// Database connection
require_once 'Connection/sql_auth.php';

$error = '';
$success = '';
$events = [];
$bookingDetails = null;

// Fetch user details if not set in session
if (isset($_SESSION['user_id']) && (!isset($_SESSION['username']) || !isset($_SESSION['email']) || !isset($_SESSION['full_name']) || !isset($_SESSION['phone_num']))) {
    $stmt = $db->prepare("SELECT username, email, full_name, phone_num FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['phone_num'] = $user['phone_num'];
    }
    $stmt->close();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['find_tickets'])) {
        $search_term = trim($_POST['search_term'] ?? '');
        if (!empty($search_term)) {
            $stmt = $db->prepare("SELECT e.*, v.name as venue_name 
                                FROM events e 
                                JOIN venues v ON e.venue_id = v.venue_id 
                                WHERE e.title LIKE ? OR e.description LIKE ? OR v.name LIKE ? AND e.status = 'approved'");
            $like_term = "%$search_term%";
            $stmt->bind_param("sss", $like_term, $like_term, $like_term);
            $stmt->execute();
            $event_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $event_ids = array_column($event_results, 'event_id');
            if (!empty($event_ids)) {
                $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
                $stmt = $db->prepare("SELECT b.*, e.title, e.start_datetime, v.name as venue_name 
                                    FROM bookings b 
                                    JOIN events e ON b.event_id = e.event_id 
                                    JOIN venues v ON e.venue_id = v.venue_id 
                                    WHERE b.user_id = ? AND b.event_id IN ($placeholders) AND b.status = 'confirmed'");
                $types = 'i' . str_repeat('i', count($event_ids));
                $params = array_merge([$types], [$_SESSION['user_id']], $event_ids);
                $stmt->bind_param(...$params);
                $stmt->execute();
                $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                if (empty($events)) {
                    $error = "No confirmed bookings found for '$search_term'.";
                } else {
                    $success = "Found " . count($events) . " booking(s) for '$search_term'.";
                }
            } else {
                $error = "No matching events found for '$search_term'.";
            }
        } else {
            $error = "Please enter a search term.";
        }
    } elseif (isset($_POST['clear_search'])) {
        $events = [];
        $success = "Search cleared successfully.";
    } elseif (isset($_POST['view_booking']) && isset($_POST['booking_id'])) {
        $booking_id = $_POST['booking_id'];
        $stmt = $db->prepare("SELECT b.*, e.title, e.start_datetime, e.description, v.name as venue_name, v.address 
                            FROM bookings b 
                            JOIN events e ON b.event_id = e.event_id 
                            JOIN venues v ON e.venue_id = v.venue_id 
                            WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'confirmed'");
        $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
        $stmt->execute();
        $bookingDetails = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($bookingDetails) {
            $success = "Booking details loaded for Booking ID: " . htmlspecialchars($booking_id) . ".";
        } else {
            $error = "No booking found for Booking ID: " . htmlspecialchars($booking_id) . ".";
        }
    } elseif (isset($_POST['close_booking'])) {
        $bookingDetails = null;
        $success = "Booking details closed.";
    } elseif (isset($_POST['update_details'])) {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_num = trim($_POST['phone_num'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (empty($username) || empty($email) || empty($full_name) || empty($phone_num)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (!preg_match('/^[0-9]{10}$/', $phone_num)) {
            $error = "Phone number must be exactly 10 digits.";
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (!empty($new_password) && strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            $hashed_password = !empty($new_password) ? password_hash($new_password, PASSWORD_DEFAULT) : null;
            $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone_num = ?, password = COALESCE(?, password) WHERE user_id = ?");
            $stmt->bind_param("sssssi", $username, $email, $full_name, $phone_num, $hashed_password, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['phone_num'] = $phone_num;
                $success = "Profile updated successfully for User ID: " . $_SESSION['user_id'] . "!";
            } else {
                $error = "Update failed: " . $db->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_account']) && isset($_POST['reason'])) {
        $reason = $_POST['reason'];
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        if ($stmt->execute()) {
            session_destroy();
            header('Location: login.php');
            exit;
        } else {
            $error = "Deletion failed: " . $db->error;
        }
        $stmt->close();
    }
}

// Fetch all bookings for the user, using booking_date instead of created_at
$stmt = $db->prepare("SELECT b.*, e.title, e.start_datetime, b.booking_date 
                     FROM bookings b 
                     JOIN events e ON b.event_id = e.event_id 
                     WHERE b.user_id = ? ORDER BY e.start_datetime DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(30, 30, 46, 0.7);
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            --success-color: #28a745;
            --error-color: #dc3545;
            --border-radius: 10px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: var(--light-color);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 1rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        header {
            background: var(--secondary-color);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo img {
            height: 120px;
            max-width: 100%;
            transition: transform 0.2s ease;
        }

        .logo img:hover {
            transform: scale(1.15);
        }

        .nav-menu {
            display: flex;
            align-items: center;
            flex: 1;
            justify-content: center;
        }

        .nav-links {
            list-style: none;
            display: flex;
            align-items: center;
            margin: 0;
            padding: 0;
            flex-wrap: wrap;
        }

        .nav-links li {
            margin-left: 1rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-actions .welcome-message {
            font-size: 1rem;
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

        .btn {
            background: var(--primary-color);
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            cursor: pointer;
            display: inline-block;
        }

        .btn:hover {
            background: #5a3de6;
            transform: translateY(-2px);
        }

        .cancel-btn {
            background: var(--error-color);
        }

        .cancel-btn:hover {
            background: #c82333;
        }

        .delete-btn {
            background: #dc3545;
            margin-top: 1rem;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .view-ticket-btn, .clear-search-btn {
            background: var(--accent-color);
        }

        .view-ticket-btn:hover, .clear-search-btn:hover {
            background: #e61e50;
        }

        .section {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .section:hover {
            transform: translateY(-5px);
        }

        .section h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--light-color);
        }

        .find-tickets {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .find-tickets form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .find-tickets input {
            flex: 1 1 70%;
            padding: 0.75rem;
            border: none;
            border-radius: var(--border-radius);
            background: rgba(40, 40, 60, 0.7);
            color: var(--light-color);
            font-size: 1rem;
        }

        .find-tickets button[type="submit"] {
            flex: 1 1 25%;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background 0.3s ease;
            white-space: nowrap;
        }

        .find-tickets button[type="submit"]:hover {
            background: #5a3de6;
        }

        .find-tickets .clear-btn {
            padding: 0.5rem 1rem;
            width: fit-content;
            align-self: flex-start;
            background: var(--accent-color);
        }

        .event-list {
            margin-top: 1rem;
        }

        .event-list button {
            margin: 0.5rem 0;
            padding: 0.5rem 1rem;
            width: 100%;
            text-align: left;
            border: none;
            background: rgba(40, 40, 60, 0.7);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .event-list button:hover {
            background: rgba(60, 60, 80, 0.9);
        }

        .booking-details {
            position: relative;
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(40, 40, 60, 0.7);
            border-radius: var(--border-radius);
        }

        .booking-details .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: var(--error-color);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .booking-details .close-btn:hover {
            color: #ff0000;
        }

        .booking-details p {
            margin: 0.5rem 0;
            font-size: 1rem;
        }

        .booking-details .download-btn {
            margin-top: 1rem;
        }

        .booking-table table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .booking-table th, .booking-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(60, 60, 80, 0.5);
            font-size: 0.9rem;
        }

        .booking-table th {
            background: rgba(40, 40, 60, 0.9);
            color: var(--light-color);
        }

        .personal-details {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .personal-details p {
            margin: 0.5rem 0;
            font-size: 1rem;
        }

        .personal-details .edit-btn {
            width: fit-content;
            padding: 0.5rem 1rem;
        }

        .edit-form {
            display: none;
            flex-direction: column;
            gap: 1rem;
        }

        .edit-form label {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .edit-form input {
            padding: 0.5rem;
            border: none;
            border-radius: var(--border-radius);
            background: rgba(40, 40, 60, 0.7);
            color: var(--light-color);
            font-size: 1rem;
        }

        .edit-form .password-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .edit-form button {
            width: fit-content;
            padding: 0.6rem 1.2rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .edit-form button:hover {
            background: #5a3de6;
        }

        .delete-section {
            display: none;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1rem;
            position: relative;
            background: rgba(40, 40, 60, 0.7);
            padding: 1rem;
            border-radius: var(--border-radius);
        }

        .delete-section select {
            padding: 0.5rem;
            border-radius: var(--border-radius);
            background: rgba(60, 60, 80, 0.9);
            color: var(--light-color);
            border: none;
            font-size: 1rem;
        }

        .delete-section .confirm-section {
            display: none;
            flex-direction: column;
            gap: 1rem;
        }

        .delete-section .confirm-section p {
            font-size: 1rem;
        }

        .delete-section .confirm-buttons {
            display: flex;
            gap: 1rem;
        }

        .delete-section button {
            width: fit-content;
            padding: 0.6rem 1.2rem;
        }

        .error, .success {
            text-align: center;
            margin: 1rem 0;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }

        .error {
            color: var(--error-color);
            background: rgba(255, 99, 71, 0.2);
        }

        .success {
            color: var(--success-color);
            background: rgba(40, 167, 69, 0.2);
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .success.fade-out {
            opacity: 0;
        }

        @media (max-width: 1024px) {
            .logo img {
                height: 100px;
            }

            .header-container {
                flex-wrap: wrap;
            }

            .nav-menu {
                justify-content: center;
            }

            .nav-links li {
                margin-left: 0.8rem;
            }
        }

        @media (max-width: 768px) {
            .find-tickets form {
                flex-direction: column;
                gap: 0.5rem;
            }

            .find-tickets input, .find-tickets button[type="submit"] {
                width: 100%;
            }

            .find-tickets .clear-btn {
                width: 100%;
            }

            .section {
                padding: 1rem;
            }

            .section h2 {
                font-size: 1.3rem;
            }

            .header-container {
                flex-direction: column;
                text-align: center;
            }

            .user-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .booking-details .download-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0.5rem;
            }

            .logo img {
                height: 80px;
            }

            .find-tickets input {
                flex: 1 1 100%;
            }

            .find-tickets button[type="submit"] {
                flex: 1 1 100%;
            }

            .booking-table th, .booking-table td {
                font-size: 0.8rem;
                padding: 0.5rem;
            }

            .edit-form .password-group {
                flex-direction: column;
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const editBtn = document.querySelector('.edit-btn');
            const editForm = document.querySelector('.edit-form');
            const cancelBtn = document.querySelector('.cancel-btn');
            const deleteBtn = document.querySelector('.delete-btn');
            const deleteSection = document.querySelector('.delete-section');
            const deleteReason = document.querySelector('#delete-reason');
            const confirmSection = document.querySelector('.confirm-section');
            const confirmDeleteBtn = document.querySelector('#confirm-delete-btn');
            const cancelDeleteBtn = document.querySelector('#cancel-delete-btn');
            const success = document.querySelector('.success');

            // Store original values for personal details from session
            let originalValues = {
                username: '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>',
                email: '<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>',
                full_name: '<?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>',
                phone_num: '<?php echo htmlspecialchars($_SESSION['phone_num'] ?? ''); ?>'
            };

            if (editBtn && editForm && cancelBtn) {
                editBtn.addEventListener('click', () => {
                    editForm.querySelector('input[name="username"]').value = originalValues.username;
                    editForm.querySelector('input[name="email"]').value = originalValues.email;
                    editForm.querySelector('input[name="full_name"]').value = originalValues.full_name;
                    editForm.querySelector('input[name="phone_num"]').value = originalValues.phone_num;
                    editForm.style.display = 'flex';
                    editBtn.style.display = 'none';
                });

                cancelBtn.addEventListener('click', () => {
                    editForm.querySelector('input[name="username"]').value = originalValues.username;
                    editForm.querySelector('input[name="email"]').value = originalValues.email;
                    editForm.querySelector('input[name="full_name"]').value = originalValues.full_name;
                    editForm.querySelector('input[name="phone_num"]').value = originalValues.phone_num;
                    editForm.style.display = 'none';
                    editBtn.style.display = 'block';
                });
            }

            if (deleteBtn && deleteSection) {
                deleteBtn.addEventListener('click', () => {
                    deleteSection.style.display = 'flex';
                });

                document.addEventListener('click', (e) => {
                    if (!deleteSection.contains(e.target) && e.target !== deleteBtn) {
                        deleteSection.style.display = 'none';
                        deleteReason.value = '';
                        confirmSection.style.display = 'none';
                    }
                });

                deleteReason.addEventListener('change', () => {
                    if (deleteReason.value) {
                        confirmSection.style.display = 'flex';
                        document.getElementById('reason-input').value = deleteReason.value;
                    } else {
                        confirmSection.style.display = 'none';
                    }
                });

                if (cancelDeleteBtn) {
                    cancelDeleteBtn.addEventListener('click', () => {
                        deleteSection.style.display = 'none';
                        deleteReason.value = '';
                        confirmSection.style.display = 'none';
                    });
                }
            }

            if (success) {
                setTimeout(() => {
                    success.classList.add('fade-out');
                    setTimeout(() => success.remove(), 500);
                }, 3000);
            }
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
            <div class="nav-menu">
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="services.php">Services</a></li>
                    <li><a href="booking.php">Bookings</a></li>
                </ul>
            </div>
            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="welcome-message">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                    <a href="logout.php" class="btn">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <?php if (!empty($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <!-- Find Tickets Section -->
        <div class="section find-tickets">
            <h2>Find Tickets</h2>
            <form method="POST">
                <input type="hidden" name="find_tickets">
                <input type="text" name="search_term" placeholder="Enter event title, description, or venue" required>
                <button type="submit">Search</button>
            </form>
            <?php if (!empty($events)): ?>
                <div class="event-list">
                    <?php foreach ($events as $event): ?>
                        <form method="POST" style="margin: 0.5rem 0;">
                            <input type="hidden" name="view_booking" value="1">
                            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($event['booking_id']) ?>">
                            <button type="submit" class="view-ticket-btn">View Ticket (Event: <?= htmlspecialchars($event['title']) ?>)</button>
                        </form>
                    <?php endforeach; ?>
                    <form method="POST">
                        <input type="hidden" name="clear_search">
                        <button type="submit" class="clear-search-btn btn">Clear Search</button>
                    </form>
                </div>
            <?php endif; ?>
            <?php if ($bookingDetails): ?>
                <div class="booking-details">
                    <form method="POST">
                        <input type="hidden" name="close_booking">
                        <button type="submit" class="close-btn">×</button>
                    </form>
                    <h3>Booking Details</h3>
                    <p><strong>Booking ID:</strong> <?= htmlspecialchars($bookingDetails['booking_id']) ?></p>
                    <p><strong>Event:</strong> <?= htmlspecialchars($bookingDetails['title']) ?></p>
                    <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($bookingDetails['start_datetime'])) ?></p>
                    <p><strong>Venue:</strong> <?= htmlspecialchars($bookingDetails['venue_name']) ?></p>
                    <p><strong>Address:</strong> <?= htmlspecialchars($bookingDetails['address']) ?></p>
                    <p><strong>Quantity:</strong> <?= htmlspecialchars($bookingDetails['ticket_quantity']) ?></p>
                    <p><strong>Status:</strong> <?= htmlspecialchars($bookingDetails['status']) ?></p>
                    <?php
                    // Generate QR code with flat text details
                    $qrContent = 'Booking ID: ' . $bookingDetails['booking_id'] . 
                                ' | Event: ' . $bookingDetails['title'] . 
                                ' | Date: ' . date('M d, Y h:i A', strtotime($bookingDetails['start_datetime'])) . 
                                ' | Venue: ' . $bookingDetails['venue_name'] . 
                                ' | Address: ' . $bookingDetails['address'] . 
                                ' | Quantity: ' . $bookingDetails['ticket_quantity'] . 
                                ' | Status: ' . $bookingDetails['status'] . 
                                ' | Attendee: ' . ($_SESSION['full_name'] ?? 'Unknown');
                    $qrPath = 'tickets/qr_' . $bookingDetails['booking_id'] . '.png';
                    $qrGenerated = false;
                    try {
                        if (!is_dir('tickets')) {
                            mkdir('tickets', 0777, true);
                        }
                        if (is_writable('tickets')) {
                            QRcode::png($qrContent, $qrPath, QR_ECLEVEL_L, 5);
                            if (file_exists($qrPath)) {
                                $qrGenerated = true;
                            } else {
                                $error = "Failed to generate QR code: File not created.";
                            }
                        } else {
                            $error = "tickets directory is not writable.";
                        }
                    } catch (Exception $e) {
                        $error = "QR code generation failed: " . $e->getMessage();
                    }
                    ?>
                    <?php if ($qrGenerated): ?>
                        <img src="<?= $qrPath ?>" alt="QR Code" style="max-width: 150px;">
                    <?php else: ?>
                        <p style="color: var(--error-color);">QR Code generation failed. Please try again or contact support.</p>
                    <?php endif; ?>
                    <form method="POST" class="download-btn">
                        <input type="hidden" name="booking_id" value="<?= htmlspecialchars($bookingDetails['booking_id']) ?>">
                        <button type="submit" class="btn" formaction="download_ticket.php">Download Ticket</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Booking History Section -->
        <div class="section">
            <h2>Booking History</h2>
            <?php if (empty($bookings)): ?>
                <p>No bookings found.</p>
            <?php else: ?>
                <div class="booking-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date</th>
                                <th>Booking Date & Time</th>
                                <th>Status</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?= htmlspecialchars($booking['title'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($booking['start_datetime']))) ?></td>
                                    <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($booking['booking_date']))) ?></td>
                                    <td><?= htmlspecialchars($booking['status'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($booking['ticket_quantity'] ?? '1') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Personal Details Section -->
        <div class="section">
            <h2>Personal Details</h2>
            <div class="personal-details">
                <p><strong>Username:</strong> <?= htmlspecialchars($_SESSION['username'] ?? 'Not set') ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($_SESSION['email'] ?? 'Not set') ?></p>
                <p><strong>Full Name:</strong> <?= htmlspecialchars($_SESSION['full_name'] ?? 'Not set') ?></p>
                <p><strong>Phone Number:</strong> <?= htmlspecialchars($_SESSION['phone_num'] ?? 'Not set') ?></p>
                <button class="btn edit-btn">Edit</button>
                <button class="btn delete-btn">Delete Account</button>
            </div>
            <form class="edit-form" method="POST">
                <input type="hidden" name="update_details">
                <label>
                    Username
                    <input type="text" name="username" value="" required>
                </label>
                <label>
                    Email
                    <input type="email" name="email" value="" required>
                </label>
                <label>
                    Full Name
                    <input type="text" name="full_name" value="" required>
                </label>
                <label>
                    Phone Number
                    <input type="tel" name="phone_num" pattern="[0-9]{10}" value="" required oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                </label>
                <div class="password-group">
                    <label>
                        New Password
                        <input type="password" name="new_password" placeholder="Leave blank to keep current">
                    </label>
                    <label>
                        Confirm Password
                        <input type="password" name="confirm_password" placeholder="Confirm new password">
                    </label>
                </div>
                <button type="submit" class="btn">Save Changes</button>
                <button type="button" class="cancel-btn btn">Cancel</button>
            </form>
            <div class="delete-section">
                <h3>Why are you deleting your account?</h3>
                <select id="delete-reason" name="reason">
                    <option value="">Select a reason</option>
                    <option value="1">Don't want to use any services from the company</option>
                    <option value="2">Don't want to be a member with us</option>
                    <option value="3">Found another service provider</option>
                    <option value="4">Don't want to say</option>
                </select>
                <div class="confirm-section">
                    <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                    <div class="confirm-buttons">
                        <form method="POST">
                            <input type="hidden" name="delete_account" value="1">
                            <input type="hidden" name="reason" id="reason-input">
                            <button type="submit" id="confirm-delete-btn" class="btn delete-btn">Yes</button>
                        </form>
                        <button type="button" id="cancel-delete-btn" class="btn cancel-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return to Home Button -->
        <div style="text-align: center; margin-top: 1rem;">
            <a href="index.php" class="btn">Return to Home</a>
        </div>
    </div>
</body>
</html>
<?php $db->close(); ?>