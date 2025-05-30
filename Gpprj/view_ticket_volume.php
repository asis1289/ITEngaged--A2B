<?php
session_start();

// Session handling and access control
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['system_admin', 'venue_admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Database connection
require_once 'Connection/sql_auth.php';

// Fetch approved events based on user type
$events = [];
if ($user_type === 'system_admin') {
    // System admin: fetch all approved events
    $query = "SELECT event_id, title AS event_name FROM events WHERE status = 'approved'";
    $stmt = $db->prepare($query);
} else {
    // Venue admin: fetch only events they created
    $query = "SELECT event_id, title AS event_name FROM events WHERE status = 'approved' AND created_by_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
}

if (!$stmt) {
    error_log("Prepare failed: " . $db->error);
    die("An error occurred. Please try again later.");
}
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("An error occurred. Please try again later.");
}
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    if (empty($events)) {
        error_log("No events found for user_id: $user_id, user_type: $user_type");
    }
} else {
    error_log("No result set for user--- user_id: $user_id, user_type: $user_type");
}
$stmt->close();

// Handle selected event and data
$selected_event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$ticket_data = 0;
$revenue_data = 0;
$event_name = '';
if ($selected_event_id > 0) {
    $event_query = "SELECT title FROM events WHERE event_id = ?";
    $event_stmt = $db->prepare($event_query);
    $event_stmt->bind_param("i", $selected_event_id);
    $event_stmt->execute();
    $event_result = $event_stmt->get_result();
    if ($row = $event_result->fetch_assoc()) {
        $event_name = $row['title'];
    }
    $event_stmt->close();

    $price_query = "SELECT ticket_price FROM ticket_prices WHERE event_id = ? ORDER BY set_at DESC LIMIT 1";
    $price_stmt = $db->prepare($price_query);
    $price_stmt->bind_param("i", $selected_event_id);
    $price_stmt->execute();
    $price_result = $price_stmt->get_result();
    $ticket_price = $price_result->fetch_assoc()['ticket_price'] ?? 0;
    $price_stmt->close();

    $volume_query = "SELECT SUM(ticket_quantity) as total_tickets FROM bookings WHERE event_id = ? AND status = 'confirmed'";
    $volume_stmt = $db->prepare($volume_query);
    $volume_stmt->bind_param("i", $selected_event_id);
    $volume_stmt->execute();
    $volume_result = $volume_stmt->get_result();
    if ($row = $volume_result->fetch_assoc()) {
        $ticket_data = $row['total_tickets'] ?? 0;
        $revenue_data = $ticket_data * $ticket_price;
    }
    $volume_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Volume & Revenue - EventHub Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #ffffff;
            --glass-bg: rgba(44, 62, 80, 0.9);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            --button-bg: linear-gradient(45deg, #4a90e2, #e74c3c);
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('images/concert.jpg');
            background-size: cover;
            background-position: center;
            backdrop-filter: blur(5px);
            color: var(--light-color);
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal, .chart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        #eventModal {
            display: flex;
        }

        .modal-content, .chart-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
        }

        .chart-content {
            max-width: 800px;
            padding: 25px;
        }

        .modal-content h3, .chart-content h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--light-color);
        }

        .modal-content select {
            width: 100%;
            padding: 0.6rem;
            margin-bottom: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            font-weight: 500;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="%23ffffff"><path d="M7 10l-5-5 1.41-1.41L7 7.17l4.59-4.58L13 5l-6 6z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            cursor: pointer;
        }

        .modal-content select option {
            background: var(--secondary-color);
            color: var(--light-color);
            font-weight: 500;
        }

        .modal-content select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(74, 144, 226, 0.5);
        }

        .modal-content button:disabled {
            background: #666;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .modal-content button, .chart-content button {
            padding: 0.7rem 1.5rem;
            background: var(--button-bg);
            color: var(--light-color);
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease;
            margin-top: 10px;
        }

        .modal-content button:hover:enabled, .chart-content button:hover {
            transform: scale(1.05);
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: var(--light-color);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: var(--accent-color);
        }

        #ticketChart {
            max-height: 400px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .modal-content, .chart-content {
                padding: 15px;
                max-width: 90%;
            }

            .modal-content h3, .chart-content h3 {
                font-size: 1.1rem;
            }

            .modal-content select {
                padding: 0.5rem;
                font-size: 0.9rem;
            }

            .modal-content button, .chart-content button {
                padding: 0.6rem 1.2rem;
                font-size: 1rem;
            }

            #ticketChart {
                max-height: 300px;
            }
        }

        @media (max-width: 480px) {
            .modal-content, .chart-content {
                padding: 10px;
            }

            .modal-content h3, .chart-content h3 {
                font-size: 1rem;
            }

            .modal-content select {
                padding: 0.4rem;
                font-size: 0.8rem;
            }

            .modal-content button, .chart-content button {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="modal" id="eventModal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeEventModal()">×</button>
            <h3>Select Event</h3>
            <select id="eventSelect" onchange="toggleViewButton()">
                <option value="0">Choose an event...</option>
                <?php 
                if (empty($events)) {
                    echo '<option value="0" disabled>No events available</option>';
                } else {
                    foreach ($events as $event): ?>
                        <option value="<?= htmlspecialchars($event['event_id']) ?>" <?= $selected_event_id == $event['event_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($event['event_name']) ?>
                        </option>
                    <?php endforeach;
                } ?>
            </select>
            <button id="viewAnalyticsBtn" onclick="openChartModal()" disabled>View Analytics</button>
        </div>
    </div>

    <div class="chart-modal" id="chartModal">
        <div class="chart-content">
            <button class="close-btn" onclick="closeChartModal()">×</button>
            <h3><?= htmlspecialchars($event_name) ?> - Ticket and Revenue Analysis</h3>
            <canvas id="ticketChart"></canvas>
        </div>
    </div>

    <script>
        let ticketChart;
        function initChart() {
            const ctx = document.getElementById('ticketChart').getContext('2d');
            if (ticketChart) ticketChart.destroy();
            ticketChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Ticket Volume', 'Revenue ($)'],
                    datasets: [{
                        data: [<?= $ticket_data ?>, <?= $revenue_data ?>],
                        backgroundColor: ['rgba(74, 144, 226, 0.7)', 'rgba(231, 76, 60, 0.7)'],
                        borderColor: ['rgba(74, 144, 226, 1)', 'rgba(231, 76, 60, 1)'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Value',
                                color: '#ffffff'
                            },
                            ticks: {
                                color: '#ffffff'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#ffffff'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        function closeEventModal() {
            window.location.href = 'admin.php';
        }

        function toggleViewButton() {
            const eventSelect = document.getElementById('eventSelect');
            const viewBtn = document.getElementById('viewAnalyticsBtn');
            viewBtn.disabled = eventSelect.value === '0';
        }

        function openChartModal() {
            const eventId = document.getElementById('eventSelect').value;
            if (eventId > 0) {
                window.location.href = `view_ticket_volume.php?event_id=${eventId}`;
            }
        }

        function closeChartModal() {
            document.getElementById('chartModal').style.display = 'none';
            document.getElementById('eventModal').style.display = 'flex';
        }

        // Initialize chart if event is pre-selected
        if (<?= $selected_event_id ?> > 0) {
            document.getElementById('eventModal').style.display = 'none';
            document.getElementById('chartModal').style.display = 'flex';
            initChart();
        }

        // Close modals when clicking outside
        window.addEventListener('click', (event) => {
            const eventModal = document.getElementById('eventModal');
            const chartModal = document.getElementById('chartModal');
            if (event.target === eventModal) closeEventModal();
            if (event.target === chartModal) closeChartModal();
        });
    </script>
</body>
</html>
<?php $db->close(); ?>