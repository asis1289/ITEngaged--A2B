<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - EventHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #ffffff; /* White background */
            color: #000000; /* Black text */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
        }

        /* Navigation Bar */
        header {
            background: #1e1e2f; /* Same as book.php */
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
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: #6b48ff; /* Primary color from book.php */
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 15px;
            flex: 1;
        }

        .section {
            margin-bottom: 2rem;
        }

        .section h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #6b48ff;
            padding-bottom: 0.5rem;
        }

        .section p, .section ul {
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .section ul {
            padding-left: 20px;
        }

        .section li {
            margin-bottom: 0.5rem;
        }

        /* Footer */
        footer {
            background: #1e1e2f;
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
            color: #6b48ff;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
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
                <li><a href="booking.php">Bookings</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="contact.php">Need Help?</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <h1>Terms and Conditions</h1>
        <p>Last Updated: May 24, 2025</p>

        <div class="section">
            <h2>1. Introduction</h2>
            <p>Welcome to EventHub, an event management system designed to help you discover, book, and manage events seamlessly. By accessing or using our website, you agree to comply with and be bound by these Terms and Conditions. If you do not agree with these terms, please do not use our services.</p>
        </div>

        <div class="section">
            <h2>2. User Accounts</h2>
            <p>EventHub allows users to register for an account to access additional features such as viewing bookings and managing profiles.</p>
            <ul>
                <li><strong>Registration</strong>: To register, you must provide accurate information, including your full name. You may also be assigned a user type (e.g., system admin, venue admin).</li>
                <li><strong>Account Security</strong>: You are responsible for maintaining the confidentiality of your account credentials. Notify us immediately if you suspect unauthorized access to your account.</li>
            </ul>
        </div>

        <div class="section">
            <h2>3. Data Collection and Privacy (GDPR Compliance)</h2>
            <p>We are committed to protecting your privacy in accordance with the General Data Protection Regulation (GDPR) and other applicable laws.</p>
            <h3>3.1 Information Collected</h3>
            <ul>
                <li><strong>Registered Users</strong>: We collect your full name during registration. When booking, we may collect additional details such as payment information (cardholder name, card number, CVC, expiration date) or PayPal payment proof (uploaded receipt).</li>
                <li><strong>Unregistered Users</strong>: To book an event, you must provide your first name, last name, and address (address line 1 and optional address line 2). Payment details are also collected as described above.</li>
                <li><strong>Usage Data</strong>: We may collect information about your interactions with our website, such as pages visited and events booked, to improve our services.</li>
            </ul>
            <h3>3.2 Purpose of Data Collection</h3>
            <ul>
                <li>Process event bookings and payments.</li>
                <li>Communicate with you regarding bookings or admin replies (e.g., notifications, messages).</li>
                <li>Improve our platform and ensure security.</li>
            </ul>
            <h3>3.3 Data Storage and Security</h3>
            <ul>
                <li>Your data is stored securely in our database with restricted access.</li>
                <li>We use HTTPS encryption for data transmission.</li>
                <li>Payment data is handled in compliance with PCI DSS standards.</li>
            </ul>
            <h3>3.4 GDPR Rights</h3>
            <p>As a user, you have the following rights under GDPR:</p>
            <ul>
                <li><strong>Access</strong>: Request a copy of your personal data.</li>
                <li><strong>Rectification</strong>: Correct inaccurate data.</li>
                <li><strong>Deletion</strong>: Request deletion of your data (subject to legal obligations).</li>
                <li><strong>Objection</strong>: Object to data processing for specific purposes.</li>
                <li>Contact us at [support@eventhub.com](mailto:support@eventhub.com) to exercise these rights.</li>
            </ul>
        </div>

        <div class="section">
            <h2>4. Booking and Payment</h2>
            <ul>
                <li><strong>Ticket Purchases</strong>: You may book up to 10 tickets per event. Ticket prices are displayed during the booking process.</li>
                <li><strong>Payment Security</strong>: We accept payments via Mastercard, Visa, and PayPal. All transactions are encrypted and comply with PCI DSS standards.</li>
                <li><strong>Refunds and Cancellations</strong>: Refunds are subject to the event organizer’s policy. You may cancel your booking on the payment details page, but cancellation does not guarantee a refund.</li>
            </ul>
        </div>

        <div class="section">
            <h2>5. Unregistered Users</h2>
            <p>Unregistered users may book events but must provide the following mandatory information:</p>
            <ul>
                <li>First Name and Last Name (letters only, no spaces or special characters).</li>
                <li>Address Line 1 (required) and Address Line 2 (optional).</li>
            </ul>
            <p>By providing this information, you consent to its use for booking and communication purposes. You may request deletion of your data after the event by contacting us.</p>
        </div>

        <div class="section">
            <h2>6. Security Protocols</h2>
            <p>We take security seriously and implement the following measures:</p>
            <ul>
                <li><strong>Encryption</strong>: All data transmitted between your browser and our servers is encrypted using HTTPS.</li>
                <li><strong>Compliance</strong>: We comply with GDPR for data protection and PCI DSS for payment security.</li>
                <li><strong>Access Controls</strong>: Database access is restricted to authorized personnel only.</li>
            </ul>
        </div>

        <div class="section">
            <h2>7. User Conduct</h2>
            <p>You agree not to:</p>
            <ul>
                <li>Use the platform for fraudulent activities, such as providing false payment information.</li>
                <li>Attempt to gain unauthorized access to our systems or other users’ accounts.</li>
                <li>Misuse the platform in any way that violates applicable laws.</li>
            </ul>
            <p>Violation of these terms may result in account suspension or termination.</p>
        </div>

        <div class="section">
            <h2>8. Liability and Disclaimers</h2>
            <p>EventHub is not liable for:</p>
            <ul>
                <li>Event cancellations or changes by organizers.</li>
                <li>Technical issues that prevent access to the platform.</li>
                <li>Losses incurred due to unauthorized access to your account (if caused by your failure to secure your credentials).</li>
            </ul>
            <p>We strive to ensure the accuracy of event details but are not responsible for errors provided by event organizers.</p>
        </div>

        <div class="section">
            <h2>9. Governing Law</h2>
            <p>These Terms and Conditions are governed by the laws of the United States. Any disputes arising from your use of EventHub will be resolved in the courts of [Your Jurisdiction, e.g., California, USA].</p>
        </div>

        <div class="section">
            <h2>10. Changes to Terms</h2>
            <p>We reserve the right to update these Terms and Conditions at any time. Changes will be posted on this page with an updated "Last Updated" date. We encourage you to review this page periodically.</p>
        </div>

        <div class="section">
            <h2>11. Contact Us</h2>
            <p>If you have any questions about these Terms and Conditions, please contact us at:</p>
            <ul>
                <li>Email: <a href="mailto:support@eventhub.com">support@eventhub.com</a></li>
                <li>Phone:+61 420760276 </li>
                <li>Address: Level 1 and 2/22 The Boulevarde, Strathfield NSW 2135</li>
            </ul>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <p>© <?= date('Y') ?> EventHub. All Rights Reserved.</p>
            <div class="social-links">
                <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a>
                <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                <a href="https://whatsapp.com" target="_blank"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
    </footer>
</body>
</html>