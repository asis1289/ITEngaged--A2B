<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/your/error.log');

// Log incoming request details
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request Headers: " . print_r(apache_request_headers(), true));
error_log("Raw POST Data: " . file_get_contents('php://input'));
error_log("$_POST: " . print_r($_POST, true));
error_log("$_FILES: " . print_r($_FILES, true));

// Database connection
require_once 'Connection/sql_auth.php';


// Get form data
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'book') {
    $errorMsg = "Invalid request: Not a POST request or action not set to 'book'";
    error_log($errorMsg . " - POST: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

// Validate required fields
$required_fields = ['event_id', 'ticket_qty', 'first_name', 'last_name', 'address_line1', 'payment_method'];
$errors = [];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $errors[] = "Missing or empty required field: $field";
    }
}

if ($errors) {
    $errorMsg = "Form validation errors: " . implode(", ", $errors);
    error_log($errorMsg);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$event_id = (int)$_POST['event_id'];
$ticket_qty = (int)$_POST['ticket_qty'];
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$address_line1 = $_POST['address_line1'];
$address_line2 = $_POST['address_line2'] ?? '';
$address = $address_line1 . ($address_line2 ? ", " . $address_line2 : '');
$payment_method = $_POST['payment_method'];
$cardholder_name = $_POST['cardholder_name'] ?? '';
$card_number = str_replace(' ', '', $_POST['card_number'] ?? '');
$cvc = $_POST['cvc'] ?? '';
$exp_date = $_POST['exp_date'] ?? '';
$payment_proof = $_FILES['payment_proof'] ?? null;
$consent_terms = isset($_POST['consent_terms']);
$consent_payment = isset($_POST['consent_payment']);

// Additional validation for payment-specific fields
if (in_array($payment_method, ['mastercard', 'visa'])) {
    if (empty($cardholder_name)) $errors[] = "Cardholder name is required for $payment_method";
    if (strlen($card_number) !== 16 || !ctype_digit($card_number)) $errors[] = "Card number must be 16 digits";
    if (!preg_match('/^\d{3}$/', $cvc)) $errors[] = "CVC must be 3 digits";
    if (!preg_match('/^(0[1-9]|1[0-2])\/([2-9][0-9])$/', $exp_date)) $errors[] = "Expiration date must be in MM/YY format";
} elseif ($payment_method === 'paypal') {
    if (!$payment_proof || $payment_proof['error'] !== UPLOAD_ERR_OK) $errors[] = "Payment proof upload is required for PayPal";
}

if (!$consent_terms || !$consent_payment) {
    $errors[] = "Both consents are required";
}

if ($errors) {
    $errorMsg = "Validation errors: " . implode(", ", $errors);
    error_log($errorMsg);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

error_log("Form data validated: event_id=$event_id, ticket_qty=$ticket_qty, first_name=$first_name, last_name=$last_name, address=$address, payment_method=$payment_method");

// Fetch event details to calculate total amount
$query = "SELECT e.*, v.name as venue_name, tp.ticket_price 
          FROM events e 
          JOIN venues v ON e.venue_id = v.venue_id 
          LEFT JOIN ticket_prices tp ON e.event_id = tp.event_id 
          WHERE e.event_id = ? AND e.start_datetime > NOW()";
$stmt = $db->prepare($query);
if (!$stmt) {
    $errorMsg = "Prepare failed for event query: " . $db->error;
    error_log($errorMsg);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $errorMsg = "Event not found or event has passed: event_id=$event_id";
    error_log($errorMsg);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}
$event = $result->fetch_assoc();
$total_amount = $event['ticket_price'] * $ticket_qty;
$stmt->close();
error_log("Event fetched successfully: event_id=$event_id, total_amount=$total_amount");

// Session handling: Validate user_id
$user_id = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    error_log("User ID found in session: $user_id");
} else {
    error_log("No user ID in session, treating as unregistered");
}

// Start transaction
$db->begin_transaction();
try {
    // 1. Insert into unregisterusers (if user is not logged in)
    $unreg_user_id = null;
    if (!$user_id) {
        $stmt = $db->prepare("INSERT INTO unregisterusers (first_name, last_name, address) VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed for unregisterusers: " . $db->error);
        }
        error_log("Preparing to insert into unregisterusers: first_name=$first_name, last_name=$last_name, address=$address");
        $stmt->bind_param("sss", $first_name, $last_name, $address);
        if (!$stmt->execute()) {
            throw new Exception("Insert failed for unregisterusers: " . $stmt->error);
        }
        $unreg_user_id = $stmt->insert_id;
        $stmt->close();
        error_log("Inserted into unregisterusers: unreg_user_id=$unreg_user_id");
    }

    // 2. Insert into bookings
    $stmt = $db->prepare("INSERT INTO bookings (user_id, unreg_user_id, event_id, booking_date, status, ticket_quantity) VALUES (?, ?, ?, NOW(), 'pending', ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed for bookings: " . $db->error);
    }
    error_log("Preparing to insert into bookings: user_id=" . ($user_id ?? 'NULL') . ", unreg_user_id=" . ($unreg_user_id ?? 'NULL') . ", event_id=$event_id, ticket_qty=$ticket_qty");
    $stmt->bind_param("iiid", $user_id, $unreg_user_id, $event_id, $ticket_qty);
    if (!$stmt->execute()) {
        throw new Exception("Insert failed for bookings: " . $stmt->error);
    }
    $booking_id = $stmt->insert_id;
    $stmt->close();
    error_log("Inserted into bookings: booking_id=$booking_id");

    // 3. Handle PayPal receipt upload and insert into payments
    $receipt_path = null;
    if ($payment_method === 'paypal' && $payment_proof && $payment_proof['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                throw new Exception("Failed to create uploads directory: " . $target_dir);
            }
            error_log("Created uploads directory: $target_dir");
        }
        $unique_filename = time() . '_' . basename($payment_proof['name']);
        $target_file = $target_dir . $unique_filename;
        if (!move_uploaded_file($payment_proof['tmp_name'], $target_file)) {
            throw new Exception("Failed to upload receipt to $target_file. Check permissions.");
        }
        $receipt_path = $target_file;
        error_log("PayPal receipt uploaded: $receipt_path");
    } else {
        error_log("No receipt upload required: payment_method=$payment_method");
    }

    // Insert into payments
    $stmt = $db->prepare("INSERT INTO payments (booking_id, user_id, amount, payment_method, transaction_id, receipt_path, status, payment_date) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
    if (!$stmt) {
        throw new Exception("Prepare failed for payments: " . $db->error);
    }
    $transaction_id = null; // As per schema, transaction_id is NULL for now
    $bind_amount = (float)$total_amount;
    error_log("Preparing to insert into payments: booking_id=$booking_id, user_id=" . ($user_id ?? 'NULL') . ", amount=$bind_amount, payment_method=$payment_method, receipt_path=" . ($receipt_path ?? 'NULL'));
    $stmt->bind_param("iidsds", $booking_id, $user_id, $bind_amount, $payment_method, $transaction_id, $receipt_path);
    if (!$stmt->execute()) {
        throw new Exception("Insert failed for payments: " . $stmt->error);
    }
    $payment_id = $stmt->insert_id;
    $stmt->close();
    error_log("Inserted into payments: payment_id=$payment_id");

    // Commit the transaction
    $db->commit();
    error_log("Transaction committed successfully");

    // Prepare redirect URL
    $redirect_url = "mock_payment.php?event_id=$event_id&booking_id=$booking_id&payment_id=$payment_id&payment_method=" . urlencode($payment_method) . "&ticket_qty=$ticket_qty";
    echo json_encode(['success' => true, 'redirect_url' => $redirect_url]);
    exit;
} catch (Exception $e) {
    $db->rollback();
    $errorMsg = "Database error: " . $e->getMessage();
    error_log($errorMsg);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

$db->close();
?>