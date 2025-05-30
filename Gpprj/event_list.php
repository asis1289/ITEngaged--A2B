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

// Handle event removal (for system admins only)
$success = '';
$error = '';
if ($user_type === 'system_admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_event'])) {
    $event_id = (int)$_POST['event_id'];
    
    // Begin a transaction to ensure data consistency
    $db->begin_transaction();
    try {
        // First, delete dependent records in ticket_prices
        $stmt = $db->prepare("DELETE FROM ticket_prices WHERE event_id = ?");
        $stmt->bind_param("i", $event_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete ticket prices: " . $stmt->error);
        }
        $stmt->close();

        // Now, delete the event from events table
        $stmt = $db->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $event_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to remove event: " . $stmt->error);
        }
        $stmt->close();

        // Commit the transaction
        $db->commit();
        $success = "Event removed successfully.";
    } catch (Exception $e) {
        // Rollback the transaction on error
        $db->rollback();
        $error = $e->getMessage();
    }
}

// Handle stop/resume booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_booking'])) {
    if ($user_type !== 'system_admin') {
        $error = "You do not have permission to toggle booking status.";
    } else {
        $event_id = (int)$_POST['event_id'];
        $new_status = $_POST['new_status'];

        // Debug: Log the values being used
        error_log("Toggle Booking - Event ID: $event_id, New Status: $new_status, User ID: $user_id, User Type: $user_type");

        // System admins can toggle any event
        $stmt = $db->prepare("UPDATE events SET booking_status = ? WHERE event_id = ?");
        $stmt->bind_param("si", $new_status, $event_id);

        // Execute and check for errors
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Fetch the event title for the success message
                $titleStmt = $db->prepare("SELECT title FROM events WHERE event_id = ?");
                $titleStmt->bind_param("i", $event_id);
                $titleStmt->execute();
                $titleResult = $titleStmt->get_result()->fetch_assoc();
                $eventTitle = $titleResult['title'] ?? 'Unknown Event';
                $titleStmt->close();

                $action = ($new_status === 'closed') ? 'stopped' : 'resumed';
                $success = "Booking $action successfully for event '$eventTitle'.";
            } else {
                $error = "No rows updated. Check if the event exists.";
            }
        } else {
            $error = "Failed to update booking status: " . $stmt->error;
            error_log("SQL Error: " . $stmt->error);
        }
        $stmt->close();
    }
}

// Fetch Events
$events = [];
$query = "SELECT e.event_id, e.title, e.description, e.image_path, e.booking_status, e.start_datetime, v.name AS venue_name 
          FROM events e 
          LEFT JOIN venues v ON e.venue_id = v.venue_id 
          WHERE e.status = 'approved'";
if ($user_type === 'venue_admin') {
    $query .= " AND ( (e.created_by_type = 'venue_admin' AND e.created_by_id = $user_id) OR e.created_by_type = 'system_admin' )";
}
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
} else {
    $error = "Failed to fetch events: " . $db->error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event List - EventHub</title>
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
            --danger-color: #dc3545;
            --warning-color: #ffca2c;
            --resume-color: #28a745;
            --edit-color: #007bff;
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
            align-items: flex-start;
            padding-top: 2rem;
        }

        .container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 90%;
            max-width: 800px;
            text-align: center;
            max-height: 85vh;
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

        .success {
            color: #fff;
            background: var(--success-color);
            border-radius: 8px;
            padding: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .success.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .event-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .event-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            color: var(--light-color);
            height: 450px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            position: relative;
            transition: background 0.3s ease;
        }

        .event-card.highlight {
            background: var(--highlight-bg);
            animation: highlightFade 2s ease-out forwards;
        }

        @keyframes highlightFade {
            0% { background: var(--highlight-bg); }
            100% { background: var(--glass-bg); }
        }

        .event-card img {
            max-width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .event-card .content {
            flex-grow: 1;
            overflow-y: auto;
            padding: 0 0.5rem;
        }

        .event-card h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .event-card p {
            margin: 0.3rem 0;
            font-size: 0.9rem;
            word-wrap: break-word;
        }

        .action-button {
            font-size: 1.5rem;
            color: var(--light-color);
            cursor: pointer;
            transition: color 0.3s ease;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: none;
            border: none;
            outline: none;
            position: relative;
            width: 100%;
            text-align: center;
        }

        .action-button:hover {
            color: var(--accent-color);
        }

        .action-button:hover + .action-dropdown,
        .action-dropdown:hover {
            display: flex;
        }

        .action-dropdown {
            position: absolute;
            bottom: 2.5rem;
            left: 50%;
            transform: translateX(-50%);
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: none;
            flex-direction: column;
            width: 150px;
            z-index: 10;
            padding: 0.5rem 0;
        }

        .action-dropdown.active {
            display: flex;
        }

        .action-dropdown a, .action-dropdown button {
            padding: 0.7rem 1rem;
            color: var(--light-color);
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: bold;
            transition: background 0.3s ease;
            border: none;
            background: none;
            cursor: pointer;
            text-align: left;
            width: 100%;
        }

        .action-dropdown a:hover, .action-dropdown button:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .action-dropdown a.edit {
            color: var(--edit-color);
        }

        .action-dropdown button.remove {
            color: var(--danger-color);
        }

        .action-dropdown button.booking-toggle.stop {
            color: var(--warning-color);
        }

        .action-dropdown button.booking-toggle.resume {
            color: var(--resume-color);
        }

        .no-data-message {
            font-style: italic;
            margin-top: 1rem;
            font-size: 1rem;
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            color: var(--light-color);
            max-width: 400px;
            width: 90%;
        }

        .modal-content p {
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .modal-buttons {
            display: flex;
            justify-content: space-around;
        }

        .modal-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .modal-btn.confirm {
            background: var(--danger-color);
            color: white;
        }

        .modal-btn.confirm:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .modal-btn.cancel {
            background: var(--secondary-color);
            color: white;
        }

        .modal-btn.cancel:hover {
            background: #16182a;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .event-cards {
                grid-template-columns: 1fr;
            }

            .event-card {
                height: 420px;
            }

            .event-card img {
                height: 120px;
            }

            .search-bar input {
                max-width: 100%;
            }

            .action-dropdown {
                width: 120px;
            }

            .action-dropdown a, .action-dropdown button {
                font-size: 0.95rem;
                padding: 0.5rem 0.8rem;
            }

            .modal-content {
                padding: 1.5rem;
            }

            .modal-content p {
                font-size: 1rem;
            }

            .modal-btn {
                padding: 0.5rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .event-card h4 {
                font-size: 1.1rem;
            }

            .event-card p {
                font-size: 0.85rem;
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
        <a href="admin.php" class="close-container" id="closeContainerBtn">×</a>
        <h2>Event List</h2>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search events by title..." onkeyup="searchEvents()">
        </div>
        <?php if (!empty($success)): ?>
            <p class="success" id="success-message"><?= htmlspecialchars($success) ?></p>
            <script>
                setTimeout(() => {
                    const successMessage = document.getElementById('success-message');
                    successMessage.classList.add('hidden');
                }, 3000);
            </script>
        <?php elseif (!empty($error)): ?>
            <p style="color: #ff2e63; margin-bottom: 1rem;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if (empty($events)): ?>
            <p class="no-data-message">No approved events at this time.</p>
        <?php else: ?>
            <div class="event-cards" id="eventGrid">
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <?php if ($event['image_path']): ?>
                            <img src="<?= htmlspecialchars($event['image_path']) ?>" alt="Event Image">
                        <?php endif; ?>
                        <div class="content">
                            <h4><?= htmlspecialchars($event['title']) ?></h4>
                            <p><?= htmlspecialchars($event['description']) ?></p>
                            <p>Venue: <?= htmlspecialchars($event['venue_name'] ?? 'Not Set') ?></p>
                            <p>Date & Time: <?= date('M j, Y g:i A', strtotime($event['start_datetime'])) ?></p>
                            <p>Booking Status: <?= ucfirst($event['booking_status']) ?></p>
                        </div>
                        <button class="action-button" onclick="toggleDropdown(this)">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="action-dropdown">
                            <a href="edit_event.php?event_id=<?= htmlspecialchars($event['event_id']) ?>" class="edit">Edit</a>
                            <?php if ($user_type === 'system_admin'): ?>
                                <button class="remove" onclick="showModal(<?= htmlspecialchars($event['event_id']) ?>)">Remove</button>
                                <form method="POST" style="display: inline;" onclick="event.preventDefault(); showBookingModal(<?= htmlspecialchars($event['event_id']) ?>, '<?= $event['booking_status'] === 'open' ? 'closed' : 'open' ?>', '<?= htmlspecialchars($event['title']) ?>')">
                                    <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['event_id']) ?>">
                                    <input type="hidden" name="new_status" value="<?= $event['booking_status'] === 'open' ? 'closed' : 'open' ?>">
                                    <button type="button" class="booking-toggle <?= $event['booking_status'] === 'open' ? 'stop' : 'resume' ?>">
                                        <?= $event['booking_status'] === 'open' ? 'Close Tickets' : 'Resume Booking' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="admin.php" class="back-btn">Back to Admin Panel</a>
    </div>

    <!-- Modal for Confirmation (Remove Event) -->
    <div class="modal" id="removeModal">
        <div class="modal-content">
            <p>Are you sure you want to remove this event?</p>
            <div class="modal-buttons">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="event_id" id="modal-event-id">
                    <button type="submit" name="remove_event" class="modal-btn confirm">Remove</button>
                </form>
                <a href="event_list.php" class="modal-btn cancel">Cancel</a>
            </div>
        </div>
    </div>

    <!-- Modal for Confirmation (Toggle Booking) -->
    <div class="modal" id="bookingModal">
        <div class="modal-content">
            <p id="bookingModalMessage">Are you sure you want to stop booking?</p>
            <div class="modal-buttons">
                <form method="POST" style="display: inline;" id="bookingForm">
                    <input type="hidden" name="event_id" id="bookingEventId">
                    <input type="hidden" name="new_status" id="bookingNewStatus">
                    <button type="submit" name="toggle_booking" class="modal-btn confirm">Yes</button>
                </form>
                <a href="event_list.php" class="modal-btn cancel">Cancel</a>
            </div>
        </div>
    </div>

    <script>
        function showModal(eventId) {
            const modal = document.getElementById('removeModal');
            const eventIdInput = document.getElementById('modal-event-id');
            eventIdInput.value = eventId;
            modal.style.display = 'flex';
            closeAllDropdowns();
        }

        function showBookingModal(eventId, newStatus, eventTitle) {
            const modal = document.getElementById('bookingModal');
            const message = document.getElementById('bookingModalMessage');
            const eventIdInput = document.getElementById('bookingEventId');
            const newStatusInput = document.getElementById('bookingNewStatus');

            eventIdInput.value = eventId;
            newStatusInput.value = newStatus;
            message.textContent = `Are you sure you want to ${newStatus === 'closed' ? 'stop' : 'resume'} booking for event '${eventTitle}'?`;
            modal.style.display = 'flex';
            closeAllDropdowns();
        }

        function toggleDropdown(button) {
            const dropdown = button.nextElementSibling;
            const isActive = dropdown.classList.contains('active');

            // Close all other dropdowns
            closeAllDropdowns();

            // Toggle the clicked dropdown
            if (!isActive) {
                dropdown.classList.add('active');
                dropdown.style.left = '50%';
                dropdown.style.transform = 'translateX(-50%)';
            }
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.action-dropdown').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }

        // Close container with close button
        document.getElementById('closeContainerBtn').addEventListener('click', function() {
            window.location.href = 'admin.php';
        });

        // Close modals and dropdowns if clicking outside
        window.onclick = function(event) {
            const removeModal = document.getElementById('removeModal');
            const bookingModal = document.getElementById('bookingModal');
            const container = document.querySelector('.container');
            const dropdowns = document.querySelectorAll('.action-dropdown');
            const actionButtons = document.querySelectorAll('.action-button');

            // Close modals
            if (event.target === removeModal || event.target === bookingModal) {
                window.location.href = 'event_list.php';
            } else if (event.target === container) {
                window.location.href = 'admin.php';
            }

            // Close dropdowns if clicking outside
            if (!Array.from(actionButtons).includes(event.target) && !event.target.closest('.action-dropdown')) {
                closeAllDropdowns();
            }
        }

        function searchEvents() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const eventCards = document.querySelectorAll('.event-card');
            let found = false;

            eventCards.forEach(card => {
                const eventTitle = card.querySelector('h4').textContent.toLowerCase();
                if (eventTitle.includes(searchTerm)) {
                    card.style.display = 'block';
                    card.classList.add('highlight');
                    found = true;
                    setTimeout(() => card.classList.remove('highlight'), 2000);
                } else {
                    card.style.display = 'none';
                }
            });

            if (!found && searchTerm) {
                alert(`No event found matching "${searchTerm}".`);
            }
        }
    </script>
</body>
</html>
<?php $db->close(); ?>