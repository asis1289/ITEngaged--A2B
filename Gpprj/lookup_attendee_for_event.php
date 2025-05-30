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

// Fetch all approved events for display
$events = [];
$query = "SELECT event_id, title, start_datetime, image_path FROM events WHERE status = 'approved' ORDER BY start_datetime DESC";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lookup Events - EventHub</title>
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
            max-width: 1200px;
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
            margin-bottom: 2rem;
            color: var(--light-color);
        }

        .search-bar {
            margin-bottom: 1.5rem;
        }

        .search-bar input {
            padding: 0.8rem;
            width: 100%;
            max-width: 300px;
            border: 1px solid rgba(255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            box-sizing: border-box;
        }

        .search-bar input::placeholder {
            color: rgba(255,255, 255, 0.7);
        }

        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            padding: 1rem;
        }

        .event-card {
            background: var(--glass-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-bg);
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-decoration: none;
            color: var(--light-color);
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        .event-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }

        .event-card .event-info {
            padding: 1rem;
        }

        .event-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--light-color);
        }

        .event-card p {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
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
            .event-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }

            .event-card img {
                height: 120px;
            }

            .event-card h3 {
                font-size: 1.1rem;
            }

            .event-card p {
                font-size: 0.85rem;
            }

            .search-bar input {
                max-width: 100%;
            }

            .container {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .event-grid {
                grid-template-columns: 1fr;
            }

            .event-card img {
                height: 100px;
            }

            .event-card h3 {
                font-size: 1rem;
            }

            .event-card p {
                font-size: 0.8rem;
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
        <h2>Lookup Events</h2>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Search events by title..." onkeyup="searchEvents()">
        </div>
        <div class="event-grid" id="eventGrid">
            <?php if (empty($events)): ?>
                <p class="no-data-message">No approved events found.</p>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <a href="view_attendees.php?event_id=<?= $event['event_id'] ?>" class="event-card">
                        <img src="<?= htmlspecialchars($event['image_path'] ?: 'images/default_event.jpg') ?>" alt="<?= htmlspecialchars($event['title']) ?>">
                        <div class="event-info">
                            <h3><?= htmlspecialchars($event['title']) ?></h3>
                            <p><?= date('F j, Y g:i A', strtotime($event['start_datetime'])) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
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

        function searchEvents() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const eventCards = document.querySelectorAll('.event-card');
            let found = false;

            eventCards.forEach(card => {
                const eventTitle = card.querySelector('h3').textContent.toLowerCase();
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