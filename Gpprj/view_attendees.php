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

// Validate event_id
$selected_event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$event_title = 'Unknown Event';
if ($selected_event_id > 0) {
    $query = "SELECT title FROM events WHERE event_id = ? AND status = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $selected_event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $event_title = $row['title'];
    } else {
        $selected_event_id = 0; // Invalid event
    }
    $stmt->close();
}

// Fetch attendees for the selected event
$attendees = [];
if ($selected_event_id > 0) {
    $query = "
        SELECT 
            b.booking_id,
            COALESCE(u.full_name, CONCAT(unreg.first_name, ' ', unreg.last_name)) AS attendee_name,
            COALESCE(u.email, '') AS email,
            b.ticket_quantity,
            b.status AS booking_status,
            p.status AS payment_status,
            b.check_in_status
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        LEFT JOIN unregisterusers unreg ON b.unreg_user_id = unreg.unreg_user_id
        LEFT JOIN payments p ON b.booking_id = p.booking_id
        WHERE b.event_id = ?
        ORDER BY b.booking_date DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $selected_event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendees[] = $row;
    }
    $stmt->close();
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Attendees - <?= htmlspecialchars($event_title) ?> - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-bg: #28a745;
            --error-bg: #dc3545;
            --highlight-bg: #ffeb3b;
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
            color: var(--light-color);
        }

        .search-bar {
            margin-bottom: 1.5rem;
        }

        .search-bar input {
            padding: 0.8rem;
            width: 100%;
            max-width: 300px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            box-sizing: border-box;
        }

        .search-bar input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .attendee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }

        .attendee-table th,
        .attendee-table td {
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: left;
            font-size: 0.95rem;
        }

        .attendee-table th {
            background: rgba(255, 255, 255, 0.2);
            font-weight: bold;
        }

        .attendee-table tr {
            background: var(--glass-bg);
            transition: transform 0.3s ease;
        }

        .attendee-table tr.highlight {
            background: var(--highlight-bg);
            font-weight: bold;
            animation: highlightFade 2s ease-out forwards;
        }

        @keyframes highlightFade {
            0% { background: var(--highlight-bg); }
            100% { background: var(--glass-bg); }
        }

        .attendee-table tr:hover {
            transform: translateY(-2px);
        }

        .check-in-checked {
            color: var(--success-bg);
            font-weight: bold;
            text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
        }

        .check-in-not-checked {
            color: var(--error-bg);
            font-weight: bold;
            text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
        }

        .no-data-message {
            font-style: italic;
            margin-top: 1rem;
            text-align: center;
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
            .attendee-table th,
            .attendee-table td {
                font-size: 0.9rem;
                padding: 0.6rem;
            }

            .search-bar input {
                max-width: 100%;
            }

            .container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .attendee-table {
                font-size: 0.8rem;
            }

            .attendee-table th,
            .attendee-table td {
                padding: 0.5rem;
            }

            h2 {
                font-size: 1.5rem;
            }

            .search-bar input {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="lookup_attendee_for_event.php" class="close-container" id="closeContainerBtn">×</a>
        <h2>View Attendees - <?= htmlspecialchars($event_title) ?></h2>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search by name or email..." onkeyup="searchAttendees()">
        </div>
        <div class="attendee-list" id="attendeeList">
            <?php if ($selected_event_id == 0): ?>
                <p class="no-data-message">Invalid or unapproved event selected.</p>
            <?php elseif (empty($attendees)): ?>
                <p class="no-data-message">No attendees found for this event.</p>
            <?php else: ?>
                <table class="attendee-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Name of Attendee</th>
                            <th>Ticket Quantity</th>
                            <th>Booking Status</th>
                            <th>Payment Status</th>
                            <th>Check-in Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendees as $attendee): ?>
                            <tr data-email="<?= htmlspecialchars($attendee['email']) ?>">
                                <td><?= htmlspecialchars($attendee['booking_id']) ?></td>
                                <td><?= htmlspecialchars($attendee['attendee_name']) ?></td>
                                <td><?= htmlspecialchars($attendee['ticket_quantity']) ?></td>
                                <td><?= htmlspecialchars($attendee['booking_status']) ?></td>
                                <td><?= htmlspecialchars($attendee['payment_status'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="<?= $attendee['check_in_status'] ? 'check-in-checked' : 'check-in-not-checked' ?>">
                                        <?= $attendee['check_in_status'] ? 'Checked In' : 'Not Checked In' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <a href="lookup_attendee_for_event.php" class="back-btn">Back to Event Lookup</a>
    </div>

    <script>
        // Close container with close button
        document.getElementById('closeContainerBtn').addEventListener('click', function() {
            window.location.href = 'lookup_attendee_for_event.php';
        });

        // Close container if clicking outside
        window.onclick = function(event) {
            const container = document.querySelector('.container');
            if (event.target === container) {
                window.location.href = 'lookup_attendee_for_event.php';
            }
        };

        function searchAttendees() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const attendeeRows = document.querySelectorAll('.attendee-table tbody tr');
            let found = false;

            attendeeRows.forEach(row => {
                const attendeeName = row.cells[1].textContent.toLowerCase();
                const email = row.dataset.email.toLowerCase();

                if (attendeeName.includes(searchTerm) || email.includes(searchTerm)) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('highlight');
                    found = true;
                    setTimeout(() => row.classList.remove('highlight'), 2000);
                } else {
                    row.classList.remove('highlight');
                }
            });

            if (!found && searchTerm) {
                alert(`No attendee found matching "${searchTerm}".`);
            }
        }
    </script>
</body>
</html>