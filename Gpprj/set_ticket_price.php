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

// Initialize message from session, if set
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : '';
unset($_SESSION['message']); // Clear after retrieving to avoid repeated display
unset($_SESSION['message_type']);

// Database connection
require_once 'Connection/sql_auth.php';

// Handle Update Ticket Price
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket']) && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $ticket_price = floatval($_POST['ticket_price']);
    if ($ticket_price < 0) {
        $message = "Ticket price cannot be negative.";
        $message_type = 'error';
    } else {
        // Fetch the event name for the success message
        $event_query = "SELECT title FROM events WHERE event_id = $event_id";
        $event_result = $db->query($event_query);
        $event_name = $event_result->fetch_assoc()['title'] ?? 'Unknown Event';

        // Check if ticket price already exists
        $check_query = "SELECT price_id FROM ticket_prices WHERE event_id = $event_id";
        $result = $db->query($check_query);
        if ($result->num_rows > 0) {
            $query = "UPDATE ticket_prices SET ticket_price = $ticket_price, set_by_type = '$user_type', set_by_id = $user_id, set_at = CURRENT_TIMESTAMP WHERE event_id = $event_id";
        } else {
            $query = "INSERT INTO ticket_prices (event_id, ticket_price, set_by_type, set_by_id) VALUES ($event_id, $ticket_price, '$user_type', $user_id)";
        }
        if ($db->query($query)) {
            // Store success message in session
            $_SESSION['message'] = "Ticket Price for " . htmlspecialchars($event_name) . " has been updated successfully";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error updating ticket price: " . $db->error;
            $_SESSION['message_type'] = 'error';
        }
        // Redirect back to set_ticket_price.php
        header("Location: set_ticket_price.php");
        exit;
    }
}

// Fetch Events with Venue Details
$events = [];
$query = "SELECT e.event_id, e.title, e.status, e.description, e.start_datetime, tp.ticket_price, v.name AS venue_name, v.address 
          FROM events e 
          LEFT JOIN ticket_prices tp ON e.event_id = tp.event_id 
          LEFT JOIN venues v ON e.venue_id = v.venue_id 
          WHERE e.status = 'approved'";
if ($user_type === 'venue_admin') {
    $query .= " AND e.created_by_type = 'venue_admin' AND e.created_by_id = $user_id";
}
$result = $db->query($query);
if ($result) while ($row = $result->fetch_assoc()) $events[] = $row;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Ticket Price - EventHub</title>
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
            --error-bg: #ff2e63;
            --danger-color: #dc3545;
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
            max-width: 800px;
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
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
            color: var(--light-color);
        }

        .event-card p {
            margin: 0.5rem 0;
        }

        .event-card input {
            padding: 0.8rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .event-card small {
            color: rgba(245, 245, 245, 0.7);
            font-size: 0.9rem;
            font-style: italic;
            display: block;
            margin-top: 0.5rem;
        }

        .event-card .update-btn {
            background: var(--primary-color);
            color: var(--light-color);
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .event-card .update-btn:hover {
            background: #5a3de6;
        }

        .no-data-message {
            font-style: italic;
            margin-top: 1rem;
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

            .modal-content {
                padding: 1.5rem;
            }

            .modal-content p {
                font-size: 1rem;
            }

            .modal-btn {
                padding: 0.5rem 1rem;
            }

            .message-box {
                font-size: 0.9rem;
                padding: 0.8rem 1.5rem;
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php" class="close-container" id="closeContainerBtn">×</a>
        <h2>Set Ticket Price</h2>
        <?php if (empty($events)): ?>
            <p class="no-data-message">No approved events available to set ticket prices.</p>
        <?php else: ?>
            <div class="event-cards">
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <h4><?= htmlspecialchars($event['title']) ?></h4>
                        <p>Venue: <?= htmlspecialchars($event['venue_name']) ?></p>
                        <p>Address: <?= htmlspecialchars($event['address']) ?></p>
                        <p>Date: <?= date('F j, Y g:i A', strtotime($event['start_datetime'])) ?></p>
                        <p>Current Price: <?= $event['ticket_price'] ? '$' . number_format($event['ticket_price'], 2) : 'Not Set' ?></p>
                        <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); showTicketModal(<?= htmlspecialchars($event['event_id']) ?>, this.ticket_price.value, '<?= htmlspecialchars($event['title']) ?>')">
                            <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                            <input type="number" name="ticket_price" step="0.01" placeholder="Ticket Price" required min="0">
                            <small>Enter ticket price in AUD</small>
                            <button type="submit" name="update_ticket" class="update-btn">Update Price</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="admin.php" class="back-btn">Back to Admin Panel</a>
    </div>

    <!-- Modal for Confirmation (Update Ticket Price) -->
    <div class="modal" id="ticketModal">
        <div class="modal-content">
            <p id="ticketModalMessage">Are you sure you want to change the ticket price?</p>
            <div class="modal-buttons">
                <form method="POST" style="display: inline;" id="ticketForm">
                    <input type="hidden" name="event_id" id="ticketEventId">
                    <input type="hidden" name="ticket_price" id="ticketPrice">
                    <button type="submit" name="update_ticket" class="modal-btn confirm">Yes</button>
                </form>
                <a href="set_ticket_price.php" class="modal-btn cancel">No</a>
            </div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="message-box <?= $message_type === 'success' ? 'message-success' : 'message-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <script>
        function showTicketModal(eventId, ticketPrice, eventTitle) {
            const modal = document.getElementById('ticketModal');
            const message = document.getElementById('ticketModalMessage');
            const eventIdInput = document.getElementById('ticketEventId');
            const ticketPriceInput = document.getElementById('ticketPrice');

            eventIdInput.value = eventId;
            ticketPriceInput.value = ticketPrice;
            message.textContent = `Are you sure you want to change the ticket price for event '${eventTitle}'?`;
            modal.style.display = 'flex';
        }

        // Close container with close button
        document.getElementById('closeContainerBtn').addEventListener('click', function() {
            window.location.href = 'admin.php';
        });

        // Close modals or container if clicking outside
        window.onclick = function(event) {
            const ticketModal = document.getElementById('ticketModal');
            const container = document.querySelector('.container');
            if (event.target === ticketModal) {
                window.location.href = 'set_ticket_price.php';
            } else if (event.target === container) {
                window.location.href = 'admin.php';
            }
        }
    </script>
</body>
</html>
<?php $db->close(); ?>