<?php
session_start();

// Database connection
require_once 'Connection/sql_auth.php';

// Get parameters from query string
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$ticket_qty = isset($_GET['ticket_qty']) ? (int)$_GET['ticket_qty'] : 0;

// Generate unique transaction ID
$transaction_id = 'TXN_' . time() . '_' . uniqid();

// Update database
if ($booking_id && $payment_id) {
    $db->begin_transaction();
    try {
        // Update bookings status to 'confirmed'
        $stmt = $db->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->close();

        // Update payments status to 'completed' and add transaction_id
        $stmt = $db->prepare("UPDATE payments SET status = 'completed', transaction_id = ?, payment_date = NOW() WHERE payment_id = ?");
        $stmt->bind_param("si", $transaction_id, $payment_id);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        error_log("Database update failed: " . $e->getMessage());
    }
}

$db->close();

// Determine if the user is registered
$is_registered = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing - A2B Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #635bff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-color: #34c759;
            --text-color: #333;
            --header-height: 60px; /* Adjust this value based on header height */
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--secondary-color), #2a2a40);
            color: var(--light-color);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            padding-top: calc(var(--header-height) + 120px); /* Adjusted for header + booking ID container */
            position: relative;
        }

        /* Booking ID Container for All Users */
        .booking-id-container {
            position: fixed;
            top: calc(var(--header-height) + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 1rem 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            z-index: 1000;
            width: 90%;
            max-width: 600px;
            display: none; /* Initially hidden, shown via JS */
        }

        .booking-id-container p {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .booking-id-container p span {
            color: var(--success-color);
            font-size: 1.8rem;
            font-weight: 700;
        }

        .booking-id-container .advice {
            font-size: 1.1rem;
            color: var(--light-color);
            font-weight: 400;
        }

        .booking-id-container .advice span {
            animation: flicker 1.5s infinite;
        }

        .booking-id-container .profile-advice {
            font-size: 1rem;
            color: var(--light-color);
            margin-top: 0.5rem;
        }

        .booking-id-container .profile-advice a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .booking-id-container .profile-advice a:hover {
            text-decoration: underline;
        }

        @keyframes flicker {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        /* Header Styles */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(30, 30, 47, 0.9);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            height: var(--header-height);
        }

        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px; /* Increased width to accommodate logo */
            margin: 0 auto;
            height: 100%;
        }

        .header-nav .logo {
            display: flex;
            align-items: center;
        }

        .header-nav .logo img {
            width: 120px; /* Slightly larger logo */
            height: auto;
            transition: transform 0.3s ease;
        }

        .header-nav .logo img:hover {
            transform: scale(1.05);
        }

        .header-nav .nav-links {
            display: flex;
            gap: 1.5rem;
        }

        .header-nav a {
            color: var(--light-color);
            text-decoration: none;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .header-nav a:hover {
            color: var(--primary-color);
        }

        /* Main Content Styles */
        .container {
            max-width: 800px;
            background: var(--glass-bg);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            position: relative;
            margin: 0 auto;
            transition: opacity 0.5s ease, transform 0.5s ease;
            margin-top: 20px; /* Adjusted for booking ID container */
        }

        .container.fade-out {
            opacity: 0;
            transform: translateY(20px);
        }

        .container.fade-in {
            opacity: 1;
            transform: translateY(0);
        }

        /* Loader Styles (Enhanced and Floating) */
        .loader-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            display: flex;
            justify-content: center;
            align-items: center;
            animation: float 2s ease-in-out infinite;
        }

        .loader {
            width: 100%;
            height: 100%;
            border: 10px solid rgba(255, 255, 255, 0.2);
            border-top: 10px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1.5s linear infinite;
            box-shadow: 0 0 20px var(--primary-color), 0 0 40px var(--primary-color, 0.5);
        }

        .checkmark {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100px;
            height: 100px;
            transform: translate(-50%, -50%) scale(0);
            animation: scaleIn 0.5s ease forwards 0.5s;
        }

        .checkmark::before {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            font-size: 4rem;
            color: var(--success-color);
            text-shadow: 0 0 10px var(--success-color);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes scaleIn {
            to { transform: translate(-50%, -50%) scale(1); }
        }

        @keyframes float {
            0%, 100% { transform: translate(-50%, -50%) translateY(0); }
            50% { transform: translate(-50%, -50%) translateY(-10px); }
        }

        /* Stage Styles */
        .stage {
            font-size: 1.3rem;
            margin: 1.5rem 0;
            color: var(--light-color);
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .stage.active {
            opacity: 1;
        }

        .success-message {
            display: none;
            color: var(--success-color);
            font-size: 1.8rem;
            margin-top: 1.5rem;
        }

        .success-message.active {
            display: block;
        }

        /* Instructions Styles */
        .instructions {
            display: none;
            margin-top: 2rem;
            font-size: 1.2rem;
            color: var(--light-color);
            animation: fadeIn 0.5s ease forwards;
        }

        .instructions p {
            margin-bottom: 1.5rem;
        }

        .instructions .profile-advice {
            font-size: 1rem;
            color: var(--light-color);
            margin-top: 0.5rem;
        }

        .instructions .profile-advice a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .instructions .profile-advice a:hover {
            text-decoration: underline;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            color: var(--light-color);
            transition: transform 0.3s ease, background 0.3s ease;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: var(--primary-color);
        }

        .btn-primary:hover {
            background: #5a3de6;
            transform: translateY(-3px);
        }

        .btn-secondary {
            background: var(--accent-color);
        }

        .btn-secondary:hover {
            background: #e61e5a;
            transform: translateY(-3px);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Footer Styles */
        footer {
            background: rgba(30, 30, 47, 0.9);
            backdrop-filter: blur(10px);
            padding: 1rem 2rem;
            text-align: center;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
            margin-top: auto;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
        }

        .footer-links a {
            color: var(--light-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            body {
                padding-top: calc(var(--header-height) + 160px); /* Adjusted for smaller screens */
            }

            .booking-id-container {
                padding: 0.8rem 1.5rem;
            }

            .booking-id-container p {
                font-size: 1.2rem;
            }

            .booking-id-container p span {
                font-size: 1.5rem;
            }

            .booking-id-container .advice {
                font-size: 0.9rem;
            }

            .container {
                padding: 2rem;
                width: 90%;
            }

            .loader-container {
                width: 150px;
                height: 150px;
            }

            .checkmark {
                width: 80px;
                height: 80px;
            }

            .checkmark::before {
                font-size: 3rem;
            }

            .header-nav {
                flex-direction: column;
                gap: 1rem;
            }

            .header-nav .nav-links {
                gap: 1rem;
            }

            .footer-links {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header>
        <nav class="header-nav">
            <a href="index.php" class="logo">
                <img src="images/a2b.png" alt="A2B Events Logo">
            </a>
            <div class="nav-links">
                <a href="index.php">Home</a>
                <a href="booking.php">Bookings</a>
                <a href="services.php">Services</a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <div class="container" id="main-container">
        <h2>Secure Payment Processing</h2>
        <div class="loader-container" id="loader-container">
            <div class="loader" id="loader"></div>
            <div class="checkmark" id="checkmark"></div>
        </div>
        <div class="stages" id="stages">
            <?php if (in_array($payment_method, ['mastercard', 'visa'])): ?>
                <div class="stage" data-step="1">Verifying card details...</div>
                <div class="stage" data-step="2">Connecting to payment gateway...</div>
                <div class="stage" data-step="3">Authorizing payment...</div>
                <div class="stage success-message" data-step="4">Payment Successful!</div>
                <div class="stage success-message" data-step="5">Booking Confirmed!</div>
            <?php else: ?>
                <div class="stage" data-step="1">Analyzing receipt upload...</div>
                <div class="stage" data-step="2">Verifying payment details...</div>
                <div class="stage" data-step="3">Processing PayPal transaction...</div>
                <div class="stage success-message" data-step="4">Payment Verified!</div>
                <div class="stage success-message" data-step="5">Booking Confirmed!</div>
            <?php endif; ?>
        </div>
        <div class="instructions" id="instructions">
            <?php if ($is_registered): ?>
                <p>You can find your ticket here:</p>
                <a href="find_ticket.php" class="btn btn-primary">Find Ticket</a>
                <p class="profile-advice">You can access it later from your profile as well: <a href="user_profile.php">Go to your profile</a></p>
            <?php else: ?>
                <p>Find your ticket here:</p>
                <a href="find_ticket.php" class="btn btn-primary">View Your Ticket</a>
            <?php endif; ?>
            <p>Need help?</p>
            <a href="contact.php" class="btn btn-secondary">Get Help</a>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-links">
            <a href="services.php">Services</a>
            <a href="terms_conditions.php">Terms and Conditions</a>
        </div>
    </footer>

    <script>
        const stages = document.querySelectorAll('.stage');
        const mainContainer = document.getElementById('main-container');
        const loaderContainer = document.getElementById('loader-container');
        const loader = document.getElementById('loader');
        const checkmark = document.getElementById('checkmark');
        const stagesContainer = document.getElementById('stages');
        const instructions = document.getElementById('instructions');
        let currentStep = 0;

        function nextStage() {
            if (currentStep < stages.length) {
                stages.forEach((stage, index) => {
                    stage.classList.remove('active');
                    if (index === currentStep) {
                        stage.classList.add('active');
                    }
                });
                currentStep++;
                if (currentStep < stages.length - 1) {
                    setTimeout(nextStage, 2000); // 2 seconds for most stages
                } else if (currentStep === stages.length - 1) {
                    setTimeout(nextStage, 3000); // 3 seconds before success
                } else {
                    // Show checkmark and transition to instructions
                    loader.style.display = 'none';
                    checkmark.style.display = 'block';
                    setTimeout(() => {
                        mainContainer.classList.add('fade-out');
                        setTimeout(() => {
                            document.querySelector('h2').style.display = 'none'; // Hide heading
                            stagesContainer.style.display = 'none';
                            loaderContainer.style.display = 'none';
                            instructions.style.display = 'block';
                            mainContainer.classList.remove('fade-out');
                            mainContainer.classList.add('fade-in');

                            // Display booking ID after process completion
                            if (<?php echo $booking_id ?>) {
                                const bookingIdContainer = document.createElement('div');
                                bookingIdContainer.className = 'booking-id-container';
                                <?php if ($is_registered): ?>
                                    bookingIdContainer.innerHTML = `
                                        <p>Your Booking ID is: <span>${<?php echo $booking_id ?>}</span></p>
                                        <p class="advice">Dear user, please remember the booking ID to view the ticket. In order to view your ticket click Find Ticket <span class="flicker-arrow">↓</span></p>
                                    `;
                                <?php else: ?>
                                    bookingIdContainer.innerHTML = `
                                        <p>Your Booking ID: <span>${<?php echo $booking_id ?>}</span></p>
                                        <p class="advice">Dear user, please remember the booking ID to view the ticket. In order to view your ticket click View Your Ticket <span class="flicker-arrow">↓</span></p>
                                    `;
                                <?php endif; ?>
                                document.body.insertBefore(bookingIdContainer, mainContainer);
                                bookingIdContainer.style.display = 'block';
                            }
                        }, 500);
                    }, 1000);
                }
            }
        }

        // Start the animation
        nextStage();
    </script>
</body>
</html>