EventHub - Smart Event Management System (SEMS)
Overview
EventHub is a web-based platform designed to streamline event management through its Smart Event Management System (SEMS) module. SEMS facilitates ticket scanning, user management, and event administration for various user roles, including guest users, venue admins, and system admins. The system offers a user-friendly interface with features like QR code scanning, event details, and administrative controls.
Key Features

Ticket Scanning:

QR code scanning with visual feedback and zoom controls.
Displays booking details for 3 seconds after a successful scan, then restarts automatically.
Plays a success sound after check-in.


Time-Based Validation:

Prevents check-ins for events more than 4 hours in the future or on different dates with specific error messages.


User Roles:

Guest User: Access to home, bookings, ticket lookup, contact, login, register, forgot password, event details, order summary, and payment pages.
Venue Admin: Home page, control panel, event management (add/edit), venue management, ticket/revenue tracking, and attendee lookup.
System Admin: Homepage, system control, price management, event approval/rejection, user management, feedback, enquiries, and event notifications.


Responsive Design:

Mobile-friendly interface with glassmorphism styling and animations.



Main Interface
The EventHub interface is role-specific, with distinct pages for guest users, venue admins, and system admins. Below are screenshots of the key web pages:
Guest User Pages

Home Page:
Bookings:
Find Ticket:
Contact Page:
Login Page:
Register Page:
Forgot Password Page:
Event Details Page:
Order Summary Page:
Payment Details Page:

Venue Admin Pages

Home Page:
Control Panel:
Add Event Pop-up:
Manage Venues:
Current Venues Display Pop-up:
Add New Venue Pop-up:
Manage Venue (Edit):
Ticket Volume and Revenue Tracking:
Look Up Attendee For Events:
Scanning Tickets:

System Admin Pages

Home Page:
System Control:
Set/Update Prices:
Approve/Reject Events:
Manage Users:
Feedback from Users:
Enquiries:
Event Push Notifications:

Prerequisites

Server Environment:
XAMPP:
Download and install XAMPP from https://www.apachefriends.org/index.html.
Start Apache and MySQL modules from the XAMPP Control Panel.
Place the EventHub files in the htdocs folder (e.g., C:\xampp\htdocs\eventhub on Windows).
Access the application at http://localhost/eventhub/scan_tickets.php.


WAMP Server:
Download and install WAMP from https://www.wampserver.com/en/.
Start the WAMP server by clicking the tray icon and ensuring Apache and MySQL are running.
Place the EventHub files in the www folder (e.g., C:\wamp64\www\eventhub on Windows).
Access the application at http://localhost/eventhub/scan_tickets.php.




Database: Uses SQL file with database name "event_db"
Browser: Modern browser with camera access (e.g., Chrome, Firefox).
Libraries:
ZXing library (loaded via CDN: https://unpkg.com/@zxing/library@latest).
Font Awesome (loaded via CDN: https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css).



Setup Instructions

Install Server:

Choose either XAMPP or WAMP and follow the installation steps above.


Deploy Files:

Copy all EventHub files (including scan_tickets.php, process_scan.php, sounds/, and media/) to the server’s web directory (e.g., htdocs for XAMPP or www for WAMP).


Configure Environment:

Ensure the server is running and the event_db database is accessible


Access the Application:

Open a browser and navigate to http://localhost/eventhub/scan_tickets.php.
Log in as a system_admin, venue_admin, or use guest user features as needed.



Usage

Log In:

Use the Login Page (./media/image6.png) to access admin features.
Guest users can explore without logging in.


Navigate Interfaces:

Guest Users: Use Home, Bookings, and Event Details pages.
Venue Admins: Manage events and scan tickets via the Control Panel.
System Admins: Oversee the system, approve events, and manage users.


Scan Tickets:

Start the scanner on the ticket scanning page (./media/image22.png, ./media/image23.png).
Follow on-screen instructions for successful check-ins.



Troubleshooting

Server Not Starting:
Check if Apache and MySQL are running in XAMPP/WAMP.


QR Code Not Detected:
Adjust lighting or use the zoom slider on the scanning page.


Access Issues:
Ensure the event_db is correctly configured.



Future Improvements

Add session-specific check-in times.
Enhance user profile management.
Integrate more payment options.

License
This project is licensed under the MIT License. See the LICENSE file for details (not included).
Contact
For support, contact the EventHub team at support@eventhub.com.
