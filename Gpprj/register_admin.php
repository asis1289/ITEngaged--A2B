<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
require_once 'Connection/sql_auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = 'system_admin'; // Fixed role to system_admin
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $country_code = trim($_POST['country_code'] ?? '');
    $phone_num = trim($_POST['phone_num'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($country_code) || empty($phone_num)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone_num)) {
        $error = "Phone number must be exactly 10 digits.";
    } else {
        // Check for duplicate username or email
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        if ($stmt === false) {
            $error = "Database prepare error: " . $db->error;
        } else {
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Username or email already exists.";
            }
            $stmt->close();
        }

        if (empty($error)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $db->begin_transaction();

            try {
                // Insert into users table
                $stmt = $db->prepare("INSERT INTO users (username, email, password, full_name, phone_num, user_type, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                if ($stmt === false) {
                    throw new Exception("Database prepare error: " . $db->error);
                }
                $stmt->bind_param("ssssss", $username, $email, $password_hash, $full_name, $phone_num, $role);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert into users: " . $stmt->error);
                }
                $user_id = $stmt->insert_id;
                $stmt->close();

                // Insert into system_admins table
                $stmt = $db->prepare("INSERT INTO system_admins (admin_id, username, email, password, full_name, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
                if ($stmt === false) {
                    throw new Exception("Database prepare error: " . $db->error);
                }
                $stmt->bind_param("issss", $user_id, $username, $email, $password_hash, $full_name);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert into system_admins: " . $stmt->error);
                }
                $stmt->close();

                $db->commit();
                $success = "Registration successful! Redirecting to login...";
            } catch (Exception $e) {
                $db->rollback();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Admin - EventHub</title>
    <!-- Font Awesome 6.0.0-beta3 (matching services.php) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Fallback Local Font Awesome -->
    <!-- <link rel="stylesheet" href="assets/fontawesome/css/all.min.css"> -->
    <style>
        :root {
            --primary-color: #6b48ff;
            --secondary-color: #1e1e2f;
            --accent-color: #ff2e63;
            --light-color: #f5f5f5;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --success-color: #28a745;
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
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        header {
            background: var(--secondary-color);
            color: white;
            padding: 2rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo img {
            height: 80px;
            max-width: 100%;
            vertical-align: middle;
            transition: transform 0.2s ease;
        }

        .logo img:hover {
            transform: scale(1.15);
        }

        section {
            padding: 3rem 0;
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .form-container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 2.5rem;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        h2 {
            color: var(--light-color);
            margin-bottom: 1.5rem;
            font-size: 2.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        label {
            display: flex;
            flex-direction: column;
            text-align: left;
            color: var(--light-color);
            font-size: 0.9rem;
        }

        input, select {
            margin-top: 0.5rem;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-size: 1rem;
            transition: background 0.3s ease;
            max-width: 100%;
            box-sizing: border-box;
        }

        select {
            background: rgba(255, 255, 255, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        select option {
            background: var(--secondary-color);
            color: #ffffff;
        }

        input:focus, select:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.3);
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .phone-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            padding: 0.5rem 0;
            margin: 0 auto;
        }

        .phone-group select, .phone-group input {
            box-sizing: border-box;
        }

        .phone-group select {
            flex: 0 0 100px;
        }

        .phone-group input {
            flex: 1;
            min-width: 0;
        }

        .btn {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .btn:hover {
            background: #5a3de6;
            transform: translateY(-2px);
        }

        .error {
            color: var(--accent-color);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .success {
            color: var(--success-color);
            background: rgba(40, 167, 69, 0.2);
            backdrop-filter: blur(5px);
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .success.fade-out {
            opacity: 0;
        }

        .login-link {
            margin-top: 1.5rem;
            color: var(--light-color);
            font-size: 0.9rem;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        footer {
            background: var(--secondary-color);
            color: white;
            padding: 1rem 0;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
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
            .form-container {
                padding: 1.5rem;
                max-width: 95%;
            }

            h2 {
                font-size: 1.5rem;
            }

            input, select, .btn {
                font-size: 0.9rem;
            }

            .phone-group {
                flex-direction: column;
                gap: 1rem;
            }

            .phone-group select, .phone-group input {
                flex: 1;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .social-links {
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <img src="images/a2b.png" alt="EventHub Logo" loading="lazy" onerror="this.src=''; this.alt='EventHub';">
            </a>
        </div>
    </header>

    <!-- Register Section -->
    <section class="container">
        <div class="form-container">
            <h2>Register Admin - EventHub</h2>
            <?php if (!empty($error)): ?>
                <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <p class="success" id="success-message"><?= htmlspecialchars($success) ?></p>
                <script>
                    if (document.getElementById('success-message')) {
                        setTimeout(() => {
                            document.getElementById('success-message').classList.add('fade-out');
                            setTimeout(() => {
                                window.location.href = 'login.php';
                            }, 500); // Wait for fade-out
                        }, 2500); // 2.5s display + 0.5s fade
                    }
                </script>
            <?php endif; ?>
            <form method="POST">
                <label>
                    Username
                    <input type="text" name="username" placeholder="Enter your username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </label>
                <label>
                    Email
                    <input type="email" name="email" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </label>
                <label>
                    Password
                    <input type="password" name="password" placeholder="Enter your password" pattern=".{8,}" title="Password must be at least 8 characters" required>
                </label>
                <label>
                    Full Name
                    <input type="text" name="full_name" placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </label>
                <label>
                    Phone Number
                    <div class="phone-group">
                        <select name="country_code" required>
                            <option value="+61" selected>Australia (+61)</option>
                            <option value="+1">United States (+1)</option>
                            <option value="+44">United Kingdom (+44)</option>
                            <option value="+91">India (+91)</option>
                            <option value="+81">Japan (+81)</option>
                            <option value="+86">China (+86)</option>
                            <option value="+33">France (+33)</option>
                            <option value="+49">Germany (+49)</option>
                            <option value="+55">Brazil (+55)</option>
                            <option value="+27">South Africa (+27)</option>
                        </select>
                        <input type="tel" name="phone_num" placeholder="Enter 10-digit phone number" pattern="[0-9]{10}" value="<?= htmlspecialchars($_POST['phone_num'] ?? '') ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)">
                    </div>
                </label>
                <button type="submit" class="btn">Register Admin</button>
            </form>
            <p class="login-link">Already have an account? <a href="login.php">Login</a></p>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <!-- Debug: Footer Start -->
        <div class="container footer-content">
            <p>© <?= date('Y') ?> EventHub. All Rights Reserved.</p>
            <div class="social-links">
                <a href="https://facebook.com" target="_blank" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="https://instagram.com" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="https://whatsapp.com" target="_blank" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
            </div>
        </div>
        <!-- Debug: Footer End -->
    </footer>
</body>
</html>
<?php $db->close(); ?>