<?php
// Start session
session_start();

// Include FPDF and QR code libraries
require_once 'lib/fpdf.php';
require_once 'lib/phpqrcode/qrlib.php';

// Extend FPDF to add a header and footer for the ticket with modern styling
class PDF extends FPDF {
    function Header() {
        // Gradient header background
        $this->SetFillColor(44, 62, 80);
        $this->Rect(0, 0, 210, 50, 'F');
        
        // Logo (with fallback)
        if (file_exists('images/a2b.png')) {
            $this->Image('images/a2b.png', 10, 10, 40);
        } else {
            $this->SetFont('Helvetica', 'B', 24);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY(10, 15);
            $this->Cell(0, 10, 'EventHub', 0, 1, 'L');
        }
        
        // Title with modern styling
        $this->SetFont('Helvetica', 'B', 22);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(60, 15);
        $this->Cell(0, 10, 'EventHub Ticket', 0, 1, 'L');
        
        // Subtitle with gradient effect
        $this->SetFont('Helvetica', 'I', 14);
        $this->SetTextColor(200, 200, 200);
        $this->SetXY(60, 30);
        $this->Cell(0, 10, 'Your Gateway to Unforgettable Events', 0, 1, 'L');
    }

    function Footer() {
        // Gradient footer background
        $this->SetFillColor(44, 62, 80);
        $this->Rect(0, 250, 210, 50, 'F');
        
        // Terms of Use with modern alignment
        $this->SetFont('Helvetica', 'I', 10);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 255);
        $this->MultiCell(190, 5, "Terms of Use: Non-transferable & non-refundable. Arrive 15 mins early. EventHub is not liable for lost tickets. Support: support@eventhub.com", 0, 'C');
    }
}

// Database connection
require_once 'Connection/sql_auth.php';

// Validate booking_id
$booking_id = $_POST['booking_id'] ?? '';
if (empty($booking_id) || !ctype_digit($booking_id)) {
    die("No booking ID provided or invalid format.");
}

// Determine user type and fetch booking details
$bookingDetails = null;
$attendee_name = "Unknown";
$is_registered = isset($_SESSION['user_id']);

if ($is_registered) {
    // Registered user: Validate booking against user_id
    $stmt = $db->prepare("
        SELECT b.*, e.title, e.start_datetime, v.name as venue_name, v.address 
        FROM bookings b 
        JOIN events e ON b.event_id = e.event_id 
        JOIN venues v ON e.venue_id = v.venue_id 
        WHERE b.booking_id = ? AND b.user_id = ? AND b.status = 'confirmed'
    ");
    $stmt->bind_param("ii", $booking_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookingDetails = $result->fetch_assoc();
    $stmt->close();
    
    if ($bookingDetails) {
        $attendee_name = htmlspecialchars($_SESSION['full_name'] ?? 'Unknown');
    }
} else {
    // Guest user: Fetch details via unreg_user_id
    $stmt = $db->prepare("
        SELECT b.*, e.title, e.start_datetime, v.name as venue_name, v.address, u.first_name, u.last_name 
        FROM bookings b 
        JOIN events e ON b.event_id = e.event_id 
        JOIN venues v ON e.venue_id = v.venue_id 
        JOIN unregisterusers u ON b.unreg_user_id = u.unreg_user_id 
        WHERE b.booking_id = ? AND b.status = 'confirmed'
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookingDetails = $result->fetch_assoc();
    $stmt->close();
    
    if ($bookingDetails) {
        $attendee_name = htmlspecialchars($bookingDetails['first_name'] . ' ' . $bookingDetails['last_name']);
    }
}

$db->close();

// Generate PDF if booking exists
if ($bookingDetails) {
    // Generate QR code with flat text details
    $qrContent = 'Booking ID: ' . $bookingDetails['booking_id'] . 
                 ' | Event: ' . $bookingDetails['title'] . 
                 ' | Date: ' . date('M d, Y h:i A', strtotime($bookingDetails['start_datetime'])) . 
                 ' | Venue: ' . $bookingDetails['venue_name'] . 
                 ' | Address: ' . $bookingDetails['address'] . 
                 ' | Quantity: ' . $bookingDetails['ticket_quantity'] . 
                 ' | Status: ' . $bookingDetails['status'] . 
                 ' | Attendee: ' . $attendee_name;
    $qrPath = 'tickets/qr_' . $bookingDetails['booking_id'] . '.png';
    if (!is_dir('tickets')) {
        mkdir('tickets', 0777, true);
    }
    if (is_writable('tickets')) {
        QRcode::png($qrContent, $qrPath, QR_ECLEVEL_L, 15,2);
    } else {
        die("tickets directory is not writable. Please check permissions.");
    }

    // Create PDF
    $pdf = new PDF('P', 'mm', 'A4');
    $fontDir = 'lib/font/';
    $fontFiles = [
        'helvetica.php',
        'helveticab.php',
        'helveticai.php',
        'helveticabi.php'
    ];
    foreach ($fontFiles as $fontFile) {
        $fullPath = $fontDir . $fontFile;
        if (file_exists($fullPath)) {
            $fontName = str_replace('.php', '', $fontFile);
            $style = '';
            if (strpos($fontName, 'b') !== false) $style .= 'B';
            if (strpos($fontName, 'i') !== false) $style .= 'I';
            $pdf->AddFont('Helvetica', $style, $fontFile, $fontDir);
        } else {
            error_log("Font file not found: $fullPath");
        }
    }
    $pdf->AddPage();

    // Modern ticket body with standard Rect
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetDrawColor(107, 72, 255);
    $pdf->SetLineWidth(1);
    $pdf->Rect(10, 50, 190, 190, 'FD');

    // Add a subtle shadow effect
    $pdf->SetFillColor(150, 150, 150, 0.5);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.5);
    $pdf->Rect(12, 52, 190, 190, 'FD');

    // Reset for ticket content
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetDrawColor(107, 72, 255);
    $pdf->SetLineWidth(1);
    $pdf->Rect(10, 50, 190, 190, 'FD');

    // Ticket details with modern spacing and styling
    $pdf->SetTextColor(30, 30, 46);
    $pdf->SetFont('Helvetica', 'B', 18);
    $pdf->SetXY(20, 60);
    $pdf->Cell(0, 12, 'Event: ' . $bookingDetails['title'], 0, 1);

    $pdf->SetFont('Helvetica', '', 14);
    $pdf->SetXY(20, 80);
    $pdf->Cell(0, 10, 'Date: ' . date('M d, Y h:i A', strtotime($bookingDetails['start_datetime'])), 0, 1);
    $pdf->SetXY(20, 95);
    $pdf->Cell(0, 10, 'Venue: ' . $bookingDetails['venue_name'], 0, 1);
    $pdf->SetXY(20, 110);
    $pdf->MultiCell(160, 8, 'Address: ' . $bookingDetails['address'], 0, 'L');
    $pdf->SetXY(20, 130);
    $pdf->Cell(0, 10, 'Booking ID: ' . $bookingDetails['booking_id'], 0, 1);
    $pdf->SetXY(20, 145);
    $pdf->Cell(0, 10, 'Attendee: ' . $attendee_name, 0, 1);
    $pdf->SetXY(20, 160);
    $pdf->Cell(0, 10, 'Quantity: ' . $bookingDetails['ticket_quantity'], 0, 1);
    $pdf->SetXY(20, 175);
    $pdf->Cell(0, 10, 'Status: ' . $bookingDetails['status'], 0, 1);

    // QR Code with modern positioning
    if (file_exists($qrPath)) {
        $pdf->Image($qrPath, 140, 120, 50, 50);
    } else {
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetXY(140, 120);
        $pdf->Cell(50, 10, 'QR Code Missing', 0, 1, 'C');
    }

    // Modern barcode-like design with gradient
    $pdf->SetFillColor(107, 72, 255, 0.3);
    for ($i = 0; $i < 20; $i++) {
        $width = rand(2, 5);
        $height = rand(10, 20);
        $pdf->Rect(20 + ($i * 9), 200, $width, $height, 'F');
    }
    $pdf->SetFont('Helvetica', 'I', 12);
    $pdf->SetTextColor(107, 72, 255);
    $pdf->SetXY(20, 225);
    $pdf->Cell(0, 10, 'Scan QR or use Booking ID at entry', 0, 1);

    // Output PDF for download
    $pdf->Output('D', 'ticket_' . $bookingDetails['booking_id'] . '.pdf');
} else {
    echo "Ticket not found or access denied.";
}
?>