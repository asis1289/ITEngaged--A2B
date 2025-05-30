<?php
ob_start();
session_start();

// Database connection
require_once 'Connection/sql_auth.php';

// Retrieve event and ticket details from session or GET
$event_id = isset($_SESSION['event_id']) && is_numeric($_SESSION['event_id']) ? (int)$_SESSION['event_id'] : (isset($_GET['event_id']) && is_numeric($_GET['event_id']) ? (int)$_GET['event_id'] : 0);
$ticket_qty = isset($_SESSION['ticket_qty']) && is_numeric($_SESSION['ticket_qty']) ? (int)$_SESSION['ticket_qty'] : (isset($_GET['ticket_qty']) && is_numeric($_GET['ticket_qty']) ? (int)$_GET['ticket_qty'] : 0);

// Only redirect to index.php if no valid data is provided on initial load
if ($event_id <= 0 || $ticket_qty <= 0) {
    if (!isset($_GET['action']) || $_GET['action'] !== 'cancel') {
        error_log("No valid event_id=$event_id or ticket_qty=$ticket_qty on initial load");
        header("Location: index.php");
        exit;
    }
}

// Fetch event details
$query = "SELECT e.*, v.name as venue_name, tp.ticket_price 
          FROM events e 
          JOIN venues v ON e.venue_id = v.venue_id 
          LEFT JOIN ticket_prices tp ON e.event_id = tp.event_id 
          WHERE e.event_id = ? AND e.start_datetime > NOW()";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("Event not found or event has passed: event_id=$event_id");
    if (!isset($_GET['action']) || $_GET['action'] !== 'cancel') {
        header("Location: index.php");
        exit;
    }
}
$event = $result->fetch_assoc();
$total_amount = $event['ticket_price'] * $ticket_qty;
$stmt->close();

// Auto-fill first name and last name if user is logged in
$first_name = '';
$last_name = '';
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $query = "SELECT full_name FROM users WHERE user_id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $full_name = trim($user['full_name']);
        $name_parts = explode(' ', $full_name, 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';
        error_log("Auto-filled name for user_id=$user_id: first_name=$first_name, last_name=$last_name");
    } else {
        error_log("User not found for user_id=$user_id");
    }
    $stmt->close();
} else {
    error_log("No user logged in, session user_id not set");
}

// Clear session data if canceling, but allow return to book.php
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && $event_id > 0 && $ticket_qty > 0) {
    header("Location: book.php?event_id=$event_id&ticket_qty=$ticket_qty");
    exit;
} elseif (isset($_GET['action']) && $_GET['action'] === 'cancel') {
    unset($_SESSION['event_id']);
    unset($_SESSION['ticket_qty']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - EventHub</title>
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
        }

        body {
            background: linear-gradient(135deg, var(--secondary-color), #2a2a40);
            color: var(--light-color);
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            background: var(--glass-bg);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            display: flex;
            gap: 2rem;
        }

        .close-bar {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: var(--light-color);
            background: none;
            border: none;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-bar:hover {
            color: var(--error-color);
        }

        .payment-details, .order-summary {
            flex: 1;
        }

        .payment-details {
            padding-right: 1rem;
        }

        .payment-details h2, .order-summary h2 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--light-color);
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: block;
            color: rgba(255, 255, 255, 0.8);
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.05);
            color: var(--light-color);
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .error {
            color: var(--error-color);
            font-size: 0.9rem;
            margin-top: 0.3rem;
            display: none;
        }

        .error.visible {
            display: block;
        }

        .payment-methods {
            display: flex;
            justify-content: space-between;
            margin: 1.5rem 0;
        }

        .payment-method {
            cursor: pointer;
            padding: 0.5rem;
            border: 2px solid transparent;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .payment-method img {
            width: 60px;
            height: 40px;
            object-fit: contain;
        }

        .payment-method.selected {
            border-color: var(--primary-color);
            background: rgba(107, 72, 255, 0.1);
        }

        .card-details, .paypal-details {
            display: none;
        }

        .cvc-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cvc-help {
            font-size: 1.2rem;
            color: var(--primary-color);
            cursor: pointer;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: var(--glass-bg);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }

        .modal-content img {
            max-width: 200px;
            max-height: 200px;
        }

        .modal-close {
            color: var(--error-color);
            font-size: 1.5rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }

        .consent-group {
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .consent-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-color);
        }

        .button-group {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
        }

        .book-btn, .cancel-btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .book-btn {
            background: var(--primary-color);
            color: var(--light-color);
        }

        .book-btn:hover {
            background: #5a3de6;
        }

        .book-btn:disabled {
            background: #666;
            cursor: not-allowed;
        }

        .cancel-btn {
            background: var(--error-color);
            color: var(--light-color);
        }

        .cancel-btn:hover {
            background: #c82333;
        }

        .order-summary img {
            width: 100%;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .order-summary p {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .order-summary .order-total {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--light-color);
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .payment-details {
                padding-right: 0;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <button class="close-bar" onclick="window.location.href='book.php?action=cancel&event_id=<?= $event_id ?>&ticket_qty=<?= $ticket_qty ?>'">×</button>
        <div class="payment-details">
            <h2>Payment Details</h2>
            <div id="error-messages"></div>
            <form id="payment-form" enctype="multipart/form-data">
                <input type="hidden" name="event_id" value="<?= $event_id ?>">
                <input type="hidden" name="ticket_qty" value="<?= $ticket_qty ?>">
                <input type="hidden" name="action" value="book">
                <input type="hidden" name="payment_method" id="payment_method" value="mastercard">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" name="first_name" id="first_name" pattern="[A-Za-z]+" title="Only letters allowed (A-Z, a-z)" value="<?= htmlspecialchars($first_name) ?>" required>
                    <p class="error" id="first_name_error">First name can only contain letters (A-Z, a-z) with no spaces or special characters.</p>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" name="last_name" id="last_name" pattern="[A-Za-z]+" title="Only letters allowed (A-Z, a-z)" value="<?= htmlspecialchars($last_name) ?>" required>
                    <p class="error" id="last_name_error">Last name can only contain letters (A-Z, a-z) with no spaces or special characters.</p>
                </div>
                <div class="form-group">
                    <label for="address_line1">Address Line 1 *</label>
                    <input type="text" name="address_line1" id="address_line1" required>
                    <p class="error" id="address_line1_error">Address Line 1 is required.</p>
                </div>
                <div class="form-group">
                    <label for="address_line2">Address Line 2 (Optional)</label>
                    <input type="text" name="address_line2" id="address_line2">
                </div>
                <h3>Select Payment Method</h3>
                <div class="payment-methods">
                    <div class="payment-method selected" data-method="mastercard" onclick="selectPayment('mastercard')">
                        <img src="images/mastercard.png" alt="Mastercard">
                    </div>
                    <div class="payment-method" data-method="visa" onclick="selectPayment('visa')">
                        <img src="images/visa.png" alt="Visa">
                    </div>
                    <div class="payment-method" data-method="paypal" onclick="selectPayment('paypal')">
                        <img src="images/paypal.png" alt="PayPal">
                    </div>
                </div>
                <div class="card-details" id="card-details">
                    <div class="form-group">
                        <label for="cardholder_name">Cardholder Name *</label>
                        <input type="text" name="cardholder_name" id="cardholder_name" required>
                        <p class="error" id="cardholder_name_error">Cardholder name is required.</p>
                    </div>
                    <div class="form-group">
                        <label for="card_number">Card Number (16 digits) *</label>
                        <input type="text" name="card_number" id="card_number" title="16 digits, no characters" required>
                        <p class="error" id="card_number_error">Card number must be 16 digits, no characters.</p>
                    </div>
                    <div class="form-group cvc-container">
                        <label for="cvc">CVC (3 digits) *</label>
                        <input type="text" name="cvc" id="cvc" maxlength="3" title="Exactly 3 digits, no characters" required>
                        <span class="cvc-help" onclick="document.getElementById('cvcModal').style.display='flex'">?</span>
                        <p class="error" id="cvc_error">CVC must be exactly 3 digits, no characters.</p>
                    </div>
                    <div class="form-group">
                        <label for="exp_date">Expiration Date (MM/YY) *</label>
                        <input type="text" name="exp_date" id="exp_date" maxlength="5" placeholder="MM/YY" title="MM/YY, year ≥ 25, month ≥ current month (05)" required>
                        <p class="error" id="exp_date_error">Expiration date must be in MM/YY format, year ≥ 25, month ≥ current month (05).</p>
                    </div>
                </div>
                <div class="paypal-details" id="paypal-details" style="display: none;">
                    <p>Scan below to make payment of $<?= number_format($total_amount, 2) ?>:<br>Please upload the receipt for successful booking</p>
                    <img src="images/paypalticket.jpg" alt="PayPal QR Code" style="max-width: 200px; margin: 1rem 0;">
                    <div class="form-group">
                        <label for="payment_proof">Upload Receipt *</label>
                        <input type="file" name="payment_proof" id="payment_proof" accept="image/*" required>
                        <p class="error" id="payment_proof_error">Receipt upload is required for PayPal.</p>
                    </div>
                </div>
                <div class="consent-group">
                    <input type="checkbox" name="consent_terms" id="consent_terms">
                    <label for="consent_terms">I agree to the terms of use *</label>
                </div>
                <div class="consent-group">
                    <input type="checkbox" name="consent_payment" id="consent_payment">
                    <label for="consent_payment">I agree to the payment terms *</label>
                </div>
                <div class="button-group">
                    <button type="button" class="book-btn" id="book-btn" disabled onclick="submitForm()">Book Now</button>
                    <button type="button" class="cancel-btn" onclick="window.location.href='book.php?action=cancel&event_id=<?= $event_id ?>&ticket_qty=<?= $ticket_qty ?>'">Cancel</button>
                </div>
            </form>
        </div>
        <div class="order-summary">
            <h2>Order Summary</h2>
            <?php if (!empty($event['image_path'])): ?>
                <img src="<?= htmlspecialchars($event['image_path']) ?>" alt="Event Image">
            <?php endif; ?>
            <p><?= htmlspecialchars($ticket_qty) ?> x $<?= number_format($event['ticket_price'] ?? 0, 2) ?>: $<?= number_format($total_amount, 2) ?></p>
            <p class="order-total">Order Total: $<?= number_format($total_amount, 2) ?></p>
        </div>
    </div>

    <div id="cvcModal" class="modal">
        <div class="modal-content">
            <img src="images/cardcvc.png" alt="CVC Guide">
            <div class="modal-close" onclick="document.getElementById('cvcModal').style.display='none'">×</div>
        </div>
    </div>

    <script>
        let selectedMethod = 'mastercard';
        const form = document.getElementById('payment-form');
        const bookBtn = document.getElementById('book-btn');
        const errorMessages = document.getElementById('error-messages');

        function selectPayment(method) {
            document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
            event.target.closest('.payment-method').classList.add('selected');
            selectedMethod = method;
            document.getElementById('payment_method').value = method;
            document.getElementById('card-details').style.display = ['mastercard', 'visa'].includes(method) ? 'block' : 'none';
            document.getElementById('paypal-details').style.display = method === 'paypal' ? 'block' : 'none';
            updateBookButton();
        }

        function validateField(field, pattern, errorElement, errorMessage) {
            const value = field.value;
            const isValid = pattern.test(value);
            errorElement.classList.toggle('visible', !isValid && value !== '');
            errorElement.textContent = errorMessage;
            return isValid || value === '';
        }

        function updateBookButton() {
            let isValid = true;
            const termsChecked = document.getElementById('consent_terms').checked;
            const paymentChecked = document.getElementById('consent_payment').checked;

            // Validate personal details
            isValid &= validateField(
                document.getElementById('first_name'),
                /^[A-Za-z]+$/,
                document.getElementById('first_name_error'),
                "First name can only contain letters (A-Z, a-z) with no spaces or special characters."
            );
            isValid &= validateField(
                document.getElementById('last_name'),
                /^[A-Za-z]+$/,
                document.getElementById('last_name_error'),
                "Last name can only contain letters (A-Z, a-z) with no spaces or special characters."
            );
            isValid &= document.getElementById('address_line1').value.trim() !== '';

            if (['mastercard', 'visa'].includes(selectedMethod)) {
                isValid &= document.getElementById('cardholder_name').value.trim() !== '';
                const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                isValid &= validateField(
                    { value: cardNumber },
                    /^\d{16}$/,
                    document.getElementById('card_number_error'),
                    "Card number must be 16 digits, no characters."
                );
                isValid &= validateField(
                    document.getElementById('cvc'),
                    /^\d{3}$/,
                    document.getElementById('cvc_error'),
                    "CVC must be exactly 3 digits, no characters."
                );
                const expDate = document.getElementById('exp_date').value.split('/');
                const currentMonth = 5; // May (current month, May 23, 2025)
                const currentYear = 25; // 2025
                const month = parseInt(expDate[0]) || 0;
                const year = parseInt(expDate[1]) || 0;
                isValid &= validateField(
                    document.getElementById('exp_date'),
                    /^(0[5-9]|1[0-2])\/([0-9][0-9])$/,
                    document.getElementById('exp_date_error'),
                    "Expiration date must be in MM/YY format, year ≥ 25, month ≥ current month (05)."
                ) && (year >= currentYear && (year > currentYear || (year === currentYear && month >= currentMonth)));
            } else if (selectedMethod === 'paypal') {
                const fileInput = document.getElementById('payment_proof');
                const fileError = document.getElementById('payment_proof_error');
                const hasFile = fileInput.files.length > 0;
                fileError.classList.toggle('visible', !hasFile && selectedMethod === 'paypal');
                isValid &= hasFile;
            }

            bookBtn.disabled = !(isValid && termsChecked && paymentChecked);
        }

        function submitForm() {
            const formData = new FormData(form);
            console.log('Submitting form with data:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }

            fetch('submit_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    console.log('Redirecting to:', data.redirect_url);
                    window.location.href = data.redirect_url;
                } else {
                    console.error('Server error:', data.message);
                    errorMessages.innerHTML = `<p class="error visible">${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                errorMessages.innerHTML = `<p class="error visible">An error occurred. Please try again.</p>`;
            });
        }

        // Real-time validation
        ['first_name', 'last_name', 'cardholder_name', 'card_number', 'cvc', 'exp_date'].forEach(id => {
            const field = document.getElementById(id);
            if (field) {
                field.addEventListener('input', updateBookButton);
            }
        });

        document.getElementById('address_line1').addEventListener('input', updateBookButton);
        document.getElementById('payment_proof').addEventListener('change', updateBookButton);
        document.getElementById('consent_terms').addEventListener('change', updateBookButton);
        document.getElementById('consent_payment').addEventListener('change', updateBookButton);

        // First name formatting and restriction
        document.getElementById('first_name').addEventListener('input', function(e) {
            let value = this.value.replace(/[^A-Za-z]/g, ''); // Only letters
            this.value = value;
            updateBookButton();
        });

        // Last name formatting and restriction
        document.getElementById('last_name').addEventListener('input', function(e) {
            let value = this.value.replace(/[^A-Za-z]/g, ''); // Only letters
            this.value = value;
            updateBookButton();
        });

        // Card number formatting
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, ''); // Only digits
            if (value.length > 16) value = value.slice(0, 16);
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            this.value = formatted;
            updateBookButton();
        });

        // CVC formatting and restriction
        document.getElementById('cvc').addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, ''); // Only digits
            if (value.length > 3) value = value.slice(0, 3);
            this.value = value;
            updateBookButton();
        });

        // Expiration date formatting and deletion
        document.getElementById('exp_date').addEventListener('input', function(e) {
            let value = this.value.replace(/[^0-9\/]/g, ''); // Only digits and /
            let parts = value.split('/');
            let month = parts[0] || '';
            let year = parts[1] || '';

            // Allow deletion by checking if input is empty or backspace/delete
            if (e.inputType === 'deleteContentBackward' || e.inputType === 'deleteContentForward' || !value) {
                this.value = '';
                return;
            }

            month = month.replace(/\D/g, '').slice(0, 2);
            if (month.length === 1 && parseInt(month) > 1) month = '0' + month;
            if (month.length === 2 && parseInt(month) > 12) month = '12';
            if (month.length === 2 && !value.includes('/') && month) value = month + '/';
            else value = month;

            if (parts.length > 1) {
                year = year.replace(/\D/g, '').slice(0, 2);
                value = month + '/' + year;
            }

            if (value.length > 5) value = value.slice(0, 5);
            this.value = value;
            updateBookButton();
        });

        // Initialize form state
        updateBookButton();
    </script>
</body>
</html>

<?php
$db->close();
ob_end_flush();
?>