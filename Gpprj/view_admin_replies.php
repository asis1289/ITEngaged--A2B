<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once 'Connection/sql_auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch admin replies for the logged-in user
$replies = [];
$query = "SELECT ar.*, u.email AS admin_email 
          FROM admin_replies ar 
          JOIN users u ON ar.system_admin_id = u.user_id 
          WHERE ar.user_id = ? 
          ORDER BY ar.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result === false) {
    error_log("Admin replies query failed: " . $db->error);
} else {
    $replies = $result->fetch_all(MYSQLI_ASSOC);
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventHub - Messages</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --unread-bg: rgba(40, 167, 69, 0.2); /* Greenish background for unread messages */
            --unread-border: #28a745;
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
            flex-direction: column;
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
            margin: 2rem auto;
            text-align: center;
        }

        h2 {
            color: var(--light-color);
            margin-bottom: 2rem;
            font-size: 2rem;
            font-weight: 600;
        }

        .reply-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .reply-card.unread {
            background: var(--unread-bg);
            border: 2px solid var(--unread-border);
        }

        .reply-card h3 {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .reply-card p {
            color: var(--light-color);
            font-size: 1rem;
            margin-bottom: 0.3rem;
        }

        .reply-card .sent-at {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .reply-card .admin-email {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .back-btn {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.7rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-top: 1rem;
        }

        .back-btn:hover {
            background: #5a3de6;
            transform: translateY(-2px);
        }

        .no-data-message {
            font-style: italic;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }

            .reply-card {
                padding: 1rem;
            }

            .reply-card h3 {
                font-size: 1.1rem;
            }

            .reply-card p, .reply-card .sent-at, .reply-card .admin-email {
                font-size: 0.9rem;
            }

            .back-btn {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Use IntersectionObserver to detect when reply cards are viewed
            const replyCards = document.querySelectorAll('.reply-card');
            const observerOptions = {
                root: null, // Use the viewport as the root
                rootMargin: '0px',
                threshold: 0.5 // Trigger when 50% of the card is visible
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const replyCard = entry.target;
                        const replyId = replyCard.getAttribute('data-reply-id');
                        const isUnread = replyCard.classList.contains('unread');

                        if (isUnread) {
                            // Mark the message as read via AJAX
                            const xhr = new XMLHttpRequest();
                            xhr.open('POST', 'read_admin_message.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    try {
                                        const response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            replyCard.classList.remove('unread');
                                            console.log(`Marked reply ${replyId} as read`);
                                        } else {
                                            console.error('Failed to mark reply as read:', response.message);
                                        }
                                    } catch (e) {
                                        console.error('Failed to parse JSON response:', xhr.responseText, e);
                                    }
                                }
                            };
                            xhr.onerror = function() {
                                console.error('AJAX request failed for reply:', replyId);
                            };
                            xhr.send(`reply_id=${replyId}`);
                        }

                        // Stop observing this card after it's been viewed
                        observer.unobserve(replyCard);
                    }
                });
            }, observerOptions);

            replyCards.forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <h2>Messages</h2>
        <?php if (empty($replies)): ?>
            <p class="no-data-message">No messages from admin at this time.</p>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <div class="reply-card <?= $reply['read_status'] == 0 ? 'unread' : '' ?>" data-reply-id="<?= $reply['reply_id'] ?>">
                    <h3><?= $reply['reference_type'] === 'enquiry' ? 'Enquiry Reply' : 'Feedback Reply' ?></h3>
                    <p><?= htmlspecialchars($reply['message']) ?></p>
                    <p class="sent-at">Sent at: <?= date('M j, Y g:i A', strtotime($reply['created_at'])) ?></p>
                    <p class="admin-email">Admin Email: <?= htmlspecialchars($reply['admin_email']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <a href="index.php" class="back-btn">Back to Home</a>
    </div>
</body>
</html>
<?php $db->close(); ?>