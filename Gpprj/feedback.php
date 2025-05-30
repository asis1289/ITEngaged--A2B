<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session for user authentication
session_start();

// Redirect to login if not authenticated or not a system admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'system_admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection
require_once 'Connection/sql_auth.php';

// Fetch all feedback with user and event details
$feedbacks = [];
$query = "SELECT f.feedback_id, f.user_id, f.event_id, f.rating, f.comment, f.submitted_at, f.status, f.is_ignored, 
                 u.full_name, u.email, e.title as event_title 
          FROM feedback f 
          JOIN users u ON f.user_id = u.user_id 
          JOIN events e ON f.event_id = e.event_id 
          ORDER BY f.submitted_at DESC";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
} else {
    $error = "Failed to fetch feedback: " . $db->error;
}

// Handle reply submission
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $message = $db->real_escape_string($_POST['message']);
    $user_id_to_reply = (int)$_POST['user_id'];
    $system_admin_id = $user_id;

    // Insert the reply into admin_replies
    $query = "INSERT INTO admin_replies (reference_type, reference_id, user_id, system_admin_id, message) VALUES ('feedback', ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param("iiis", $feedback_id, $user_id_to_reply, $system_admin_id, $message);
    if ($stmt->execute()) {
        $success = "Reply sent successfully!";
    } else {
        $error = "Failed to send reply: " . $db->error;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Feedback - EventHub</title>
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
            --star-color: #ffd700;
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
            max-width: 1000px;
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

        .message {
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

        .message.success {
            background: var(--success-bg);
        }

        .message.error {
            background: var(--error-bg);
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; }
        }

        .feedback-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .feedback-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: left;
            color: var(--light-color);
            position: relative;
            transition: transform 0.3s ease;
        }

        .feedback-card:hover {
            transform: translateY(-5px);
        }

        .feedback-card p {
            margin: 0.5rem 0;
            font-size: 1rem;
        }

        .feedback-card .rating {
            color: var(--star-color);
            margin: 0.5rem 0;
        }

        .feedback-card .status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .feedback-card .status.read {
            background: var(--success-bg);
        }

        .feedback-card .status.unread {
            background: var(--accent-color);
        }

        .action-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
            margin-top: 0.5rem;
            cursor: pointer;
        }

        .reply-btn {
            background: var(--primary-color);
            color: white;
        }

        .reply-btn:hover {
            background: #5a3de6;
            transform: translateY(-2px);
        }

        .no-data-message {
            font-style: italic;
            margin-top: 1rem;
            font-size: 1.1rem;
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
            max-width: 500px;
            width: 90%;
        }

        .modal-content p {
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .modal-content form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .modal-content textarea {
            padding: 0.6rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light-color);
            font-size: 1rem;
            resize: vertical;
            min-height: 100px;
        }

        .modal-buttons {
            display: flex;
            justify-content: space-around;
            margin-top: 1rem;
        }

        .modal-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .modal-btn.send {
            background: var(--primary-color);
            color: white;
        }

        .modal-btn.send:hover {
            background: #5a3de6;
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
            .container {
                padding: 1.5rem;
            }

            .feedback-cards {
                grid-template-columns: 1fr;
            }

            .feedback-card p {
                font-size: 0.9rem;
            }

            .feedback-card .status {
                font-size: 0.7rem;
                padding: 0.2rem 0.5rem;
            }

            .modal-content {
                padding: 1.5rem;
            }

            .modal-content p {
                font-size: 1rem;
            }

            .modal-content textarea {
                font-size: 0.9rem;
            }

            .modal-btn {
                padding: 0.5rem 1rem;
            }

            .message {
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
        <h2>Event Feedback</h2>
        <?php if (!empty($success)): ?>
            <div class="message success" id="success-message"><?= htmlspecialchars($success) ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="message error" id="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (empty($feedbacks)): ?>
            <p class="no-data-message">No feedback at this time.</p>
        <?php else: ?>
            <div class="feedback-cards">
                <?php foreach ($feedbacks as $feedback): ?>
                    <div class="feedback-card">
                        <span class="status <?= $feedback['status'] ?>"><?= $feedback['status'] ?></span>
                        <p><strong>User:</strong> <?= htmlspecialchars($feedback['full_name']) ?> (<?= htmlspecialchars($feedback['email']) ?>)</p>
                        <p><strong>Event:</strong> <?= htmlspecialchars($feedback['event_title']) ?></p>
                        <?php if ($feedback['is_ignored'] == 1): ?>
                            <p><strong>Rating:</strong> NULL</p>
                            <p><strong>Comment:</strong> NULL</p>
                            <p><strong>Message:</strong> This user is not interested to give feedback</p>
                        <?php else: ?>
                            <p><strong>Rating:</strong> 
                                <span class="rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa<?= $i <= $feedback['rating'] ? 's' : 'r' ?> fa-star" style="color: <?= $i <= $feedback['rating'] ? 'var(--star-color)' : '#ccc' ?>;"></i>
                                    <?php endfor; ?>
                                </span>
                            </p>
                            <p><strong>Comment:</strong> <?= htmlspecialchars($feedback['comment'] ?? 'No comment') ?></p>
                        <?php endif; ?>
                        <p><strong>Submitted At:</strong> <?= date('F j, Y g:i A', strtotime($feedback['submitted_at'])) ?></p>
                        <button class="action-btn reply-btn" onclick="showReplyModal(<?= $feedback['feedback_id'] ?>, <?= $feedback['user_id'] ?>, '<?= htmlspecialchars($feedback['full_name']) ?>')">Reply</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <a href="admin.php" class="back-btn">Back to Admin Panel</a>
    </div>

    <!-- Modal for Reply -->
    <div class="modal" id="replyModal">
        <div class="modal-content">
            <p id="replyModalMessage">Reply to <span id="replyUserName"></span></p>
            <form method="POST">
                <input type="hidden" name="feedback_id" id="replyFeedbackId">
                <input type="hidden" name="user_id" id="replyUserId">
                <textarea name="message" placeholder="Type your reply (e.g., 'Thank you for your feedback!')..." required></textarea>
                <div class="modal-buttons">
                    <button type="submit" name="send_reply" class="modal-btn send">Send</button>
                    <button type="button" class="modal-btn cancel" onclick="closeReplyModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Close container with close button
        document.getElementById('closeContainerBtn').addEventListener('click', function() {
            window.location.href = 'admin.php';
        });

        // Close container if clicking outside
        window.onclick = function(event) {
            const container = document.querySelector('.container');
            const modal = document.getElementById('replyModal');
            if (event.target === container) {
                window.location.href = 'admin.php';
            }
            if (event.target === modal) {
                closeReplyModal();
            }
        };

        function showReplyModal(feedbackId, userId, name) {
            const modal = document.getElementById('replyModal');
            const feedbackIdInput = document.getElementById('replyFeedbackId');
            const userIdInput = document.getElementById('replyUserId');
            const userNameSpan = document.getElementById('replyUserName');

            feedbackIdInput.value = feedbackId;
            userIdInput.value = userId;
            userNameSpan.textContent = name;
            modal.style.display = 'flex';
        }

        function closeReplyModal() {
            const modal = document.getElementById('replyModal');
            modal.style.display = 'none';
        }

        // Mark feedback as read when exiting the page
        window.addEventListener('beforeunload', function(event) {
            fetch('mark_feedback_read.php', {
                method: 'POST',
                credentials: 'same-origin' // Ensure cookies/session are sent
            }).then(response => {
                if (!response.ok) {
                    console.error('Failed to mark feedback as read:', response.status);
                }
            }).catch(error => {
                console.error('Error marking feedback as read:', error);
            });
        });
    </script>
</body>
</html>
<?php $db->close(); ?>