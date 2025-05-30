<?php
session_start();

// Database connection
require_once 'Connection/sql_auth.php';

// Check if event_id is provided
if (!isset($_GET['event_id'])) {
    header("Location: index.php");
    exit;
}

$event_id = (int)$_GET['event_id'];
$success_message = isset($_GET['success_message']) ? htmlspecialchars($_GET['success_message']) : '';

// Fetch event details
$query = "SELECT e.*, v.name as venue_name, v.address as venue_address, tp.ticket_price 
          FROM events e 
          JOIN venues v ON e.venue_id = v.venue_id 
          LEFT JOIN ticket_prices tp ON e.event_id = tp.event_id 
          WHERE e.event_id = ? AND e.start_datetime > NOW()";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: index.php");
    exit;
}
$event = $result->fetch_assoc();
$stmt->close();

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

$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : null;
$is_admin = $user_type && in_array($user_type, ['system_admin', 'venue_admin']);

// Store event_id and ticket_qty in session when proceeding to payment
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ticket_qty']) && is_numeric($_GET['ticket_qty']) && $_GET['ticket_qty'] > 0) {
    $_SESSION['event_id'] = $event_id;
    $_SESSION['ticket_qty'] = (int)$_GET['ticket_qty'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['title']) ?> - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.15);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-color: #28a745;
            --error-color: #dc3545;
            --notification-color: #ff9500;
            --glow-color: rgba(107, 72, 255, 0.7);
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
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
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
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

        .btn, .profile-btn, .message-btn {
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

        .profile-btn, .message-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .profile-btn {
            background: var(--primary-color);
        }

        .message-btn {
            background: var(--success-color);
            position: relative;
        }

        .message-btn .unread-count {
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

        .btn:hover, .profile-btn:hover, .message-btn:hover {
            transform: translateY(-2px);
        }

        .message-btn:hover {
            background: #219653;
        }

        .profile-btn:hover {
            background: #5a3de6;
        }

        .btn:disabled, .btn.disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            gap: 2rem;
            flex: 1;
            margin: 2rem;
        }

        .ticket-selection, .order-summary {
            padding: 1.5rem;
        }

        .ticket-selection {
            flex: 2;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .event-details h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            text-align: center;
            color: var(--light-color);
            text-transform: uppercase;
        }

        .event-details p {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .event-details i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .event-details strong {
            color: var(--light-color);
        }

        .ticket-selector {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .ticket-selector label {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--light-color);
        }

        .ticket-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .ticket-controls button {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 1.5rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }

        .ticket-controls button:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(107, 72, 255, 0.4);
        }

        .ticket-controls button:disabled {
            background: #666;
            cursor: not-allowed;
            opacity: 0.6;
        }

        #ticket_qty {
            width: 60px;
            text-align: center;
            padding: 0.5rem;
            border: none;
            background: transparent;
            color: var(--light-color);
            font-size: 1.5rem;
            font-weight: 600;
            outline: none;
        }

        .proceed-btn {
            padding: 1rem 2rem;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.2rem;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            width: 100%;
            max-width: 300px;
            margin-top: 1rem;
        }

        .proceed-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(107, 72, 255, 0.4);
        }

        .proceed-btn:disabled {
            background: #666;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .order-summary {
            flex: 1;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .order-summary h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            text-align: center;
            color: var(--light-color);
        }

        .order-summary img {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .order-summary p {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
        }

        .order-summary .order-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--light-color);
            margin-top: 1rem;
            text-align: center;
        }

        .success-message {
            color: var(--success-color);
            font-size: 1.2rem;
            margin-bottom: 1rem;
            text-align: center;
            padding: 0.5rem;
            background: rgba(40, 167, 69, 0.1);
            border-radius: 5px;
            width: 100%;
        }

        footer {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .social-links a {
            color: white;
            margin: 0 0.5rem;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                gap: 1.5rem;
                margin: 1rem;
            }

            .ticket-selection, .order-summary {
                width: 100%;
            }

            .ticket-controls {
                justify-content: center;
            }

            .header-container {
                flex-direction: column;
                align-items: flex-start;
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
            }

            .user-actions .welcome-message {
                margin-bottom: 0.5rem;
            }

            .message-btn, .profile-btn {
                margin-left: 0;
                margin-bottom: 0.5rem;
            }

            .notification-icon {
                font-size: 1.1rem;
            }

            .notification-icon .unread-count {
                font-size: 0.65rem;
                padding: 1px 5px;
            }
        }

        @media (max-width: 480px) {
            .event-details h2 {
                font-size: 1.5rem;
            }

            .event-details p {
                font-size: 0.9rem;
            }

            .ticket-controls button {
                width: 30px;
                height: 30px;
                font-size: 1.2rem;
            }

            #ticket_qty {
                font-size: 1.2rem;
            }

            .proceed-btn {
                padding: 0.8rem 1.5rem;
                font-size: 1rem;
            }

            .order-summary h2 {
                font-size: 1.5rem;
            }

            .order-summary p {
                font-size: 1rem;
            }

            .order-summary .order-total {
                font-size: 1.2rem;
            }

            .message-btn, .profile-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.85rem;
            }

            .message-btn .unread-count, .notification-icon .unread-count {
                font-size: 0.6rem;
                padding: 1px 4px;
                top: -6px;
                right: -6px;
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
                            <?php if ($unreadMessageCount > 0): ?>
                                <span class="unread-count"><?= $unreadMessageCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($is_admin): ?>
                    <li><a href="admin.php">Admin Panel</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="welcome-message">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                    <a href="view_admin_replies.php" class="message-btn">
                        <i class="fas fa-envelope"></i> Messages
                        <?php if ($unreadMessageCount > 0): ?>
                            <span class="unread-count"><?= $unreadMessageCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="user_profile.php" class="profile-btn"><i class="fas fa-user"></i> Profile</a>
                    <a href="logout.php">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn">Login</a>
                    <a href="register.php" class="btn">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="ticket-selection">
            <div class="event-details">
                <h2><?= htmlspecialchars($event['title']) ?></h2>
                <p><i class="fas fa-align-left"></i> <strong>Description:</strong> <?= htmlspecialchars($event['description']) ?></p>
                <p><i class="fas fa-map-marker-alt"></i> <strong>Venue:</strong> <?= htmlspecialchars($event['venue_name']) ?>, <?= htmlspecialchars($event['venue_address']) ?></p>
                <p><i class="fas fa-calendar-alt"></i> <strong>Date & Time:</strong> <?= date('F j, Y, g:i A', strtotime($event['start_datetime'])) ?></p>
                <p><i class="fas fa-ticket-alt"></i> <strong>Ticket Price:</strong> $<?= !is_null($event['ticket_price']) ? number_format($event['ticket_price'], 2) : '0.00' ?></p>
            </div>
            <form action="payment_details.php" method="GET" id="ticket-form">
                <input type="hidden" name="event_id" value="<?= htmlspecialchars($event_id) ?>">
                <div class="ticket-selector">
                    <label for="ticket_qty">Select Number of Tickets</label>
                    <div class="ticket-controls">
                        <button type="button" id="decrease">-</button>
                        <input type="number" id="ticket_qty" name="ticket_qty" value="0" min="0" max="10" readonly>
                        <button type="button" id="increase">+</button>
                    </div>
                    <button type="submit" class="proceed-btn" id="proceed-btn" disabled>Proceed to Checkout</button>
                </div>
            </form>
            <?php if ($success_message): ?>
                <p class="success-message"><?= $success_message ?></p>
            <?php endif; ?>
        </div>
        <div class="order-summary">
            <h2>Order Summary</h2>
            <?php if (!empty($event['image_path'])): ?>
                <img src="<?= htmlspecialchars($event['image_path']) ?>" alt="Event Image">
            <?php endif; ?>
            <p>Quantity: <span id="order-quantity">0</span> x $<?= !is_null($event['ticket_price']) ? number_format($event['ticket_price'], 2) : '0.00' ?>: $<span id="order-breakdown-total">0.00</span></p>
            <p class="order-total">Order Total: $<span id="order-total">0.00</span></p>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <p>© <?= date('Y') ?> EventHub. All Rights Reserved.</p>
            <a href="terms_conditions"> Terms and conditions</a>
            <div class="social-links">
                <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a>
                <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://whatsapp.com" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>

    <script>
        const ticketQtyInput = document.getElementById('ticket_qty');
        const proceedBtn = document.getElementById('proceed-btn');
        const decreaseBtn = document.getElementById('decrease');
        const increaseBtn = document.getElementById('increase');
        const orderQuantity = document.getElementById('order-quantity');
        const orderBreakdownTotal = document.getElementById('order-breakdown-total');
        const orderTotal = document.getElementById('order-total');
        const ticketPrice = <?= !is_null($event['ticket_price']) ? floatval($event['ticket_price']) : 0 ?>;
        let qty = 0;

        function updateOrderSummary() {
            orderQuantity.textContent = qty;
            const total = qty * ticketPrice;
            orderBreakdownTotal.textContent = total.toFixed(2);
            orderTotal.textContent = total.toFixed(2);
            proceedBtn.disabled = qty <= 0;
            ticketQtyInput.value = qty;
            decreaseBtn.disabled = qty <= 0;
            increaseBtn.disabled = qty >= 10;
        }

        decreaseBtn.addEventListener('click', () => {
            if (qty > 0) {
                qty--;
                updateOrderSummary();
            }
        });

        increaseBtn.addEventListener('click', () => {
            if (qty < 10) {
                qty++;
                updateOrderSummary();
            }
        });

        updateOrderSummary(); // Initialize on page load
    </script>
</body>
</html>

<?php
$db->close();
?>