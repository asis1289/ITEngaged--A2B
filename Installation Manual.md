EventHub - Smart Event Management System (SEMS) Installation Manual
Introduction
This manual provides a step-by-step guide to install and configure the EventHub - Smart Event Management System (SEMS) project on your local machine using either WAMP64 (recommended) or XAMPP. Both are local server environments that include Apache, MySQL, and PHP, essential for running the EventHub project. The instructions are tailored for Windows and assume you have administrative privileges. The guide is current as of 12:15 PM AEST, Friday, May 30, 2025.
Recommended Option: WAMP64

WAMP64 is recommended due to its lightweight design and ease of use for Windows-based PHP development. It is specifically optimized for 64-bit Windows systems, aligning with most modern hardware.

Prerequisites

Operating System: Windows 10 or 11 (64-bit).
Disk Space: At least 2.5 GB of free space.
Internet Connection: Required for downloading software and dependencies.
Administrative Privileges: Needed to install and configure the server.

Installation Options
Option 1: Using WAMP64 (Recommended)
Step 1: Download WAMP64

Visit the official WampServer website.
Download the latest 64-bit version of WAMP (e.g., WampServer 3.3.0 or newer).
Save the installer file (e.g., wampserver3.3.0_x64.exe) to your computer.

Step 2: Install WAMP64

Double-click the downloaded installer to start the installation.
Select your preferred language (e.g., English) and click "OK".
Read and accept the license agreement, then click "Next".
Choose the installation path (default is C:\wamp64) and ensure you have at least 2.5 GB of free space. Click "Next".
Select "Create a shortcut in the Start menu" and click "Next".
Uncheck "Donate" if prompted, then click "Next" and "Install".
During installation, you may be prompted to install additional components (e.g., Visual C++ Redistributable). Follow the prompts to install these if required.
Click "Finish" to complete the installation.

Step 3: Start WAMP64

Launch WAMP64 by clicking the desktop shortcut or from the Start menu.
The WAMP64 icon will appear in the system tray (bottom-right corner). It should turn green when Apache and MySQL are running. If it’s orange or red, troubleshoot by ensuring no other services (e.g., Skype) are using port 80.

Step 4: Configure WAMP64

Left-click the WAMP64 tray icon and select "Apache" > "httpd.conf".
Open the file in a text editor (e.g., Notepad) and ensure the following line is set to listen on port 80:Listen 0.0.0.0:80
Listen [::0]:80


Save and close the file.
Restart all services by right-clicking the tray icon and selecting "Restart All Services".

Step 5: Deploy EventHub Project

Locate the www directory (default: C:\wamp64\www).
Extract the EventHub project files (e.g., from a ZIP file) into a new folder, such as C:\wamp64\www\eventhub.
Ensure the folder contains files like index.php, scan_tickets.php, and subdirectories (e.g., sounds, media).

Step 6: Access the Project

Open a web browser and enter http://localhost/eventhub.
If the homepage loads, the installation is successful. Proceed to log in or use guest features.

Troubleshooting (WAMP64)

Apache Not Starting: Ensure no other program (e.g., IIS, Skype) is using port 80. Stop conflicting services via the WAMP64 menu or Windows Services.
PHP Errors: Verify PHP is enabled in php.ini (located in C:\wamp64\bin\php\phpX.X.X).

Option 2: Using XAMPP
Step 1: Download XAMPP

Visit the Apache Friends website.
Download the latest XAMPP installer for Windows (e.g., XAMPP 8.2.12 or newer, 64-bit version).
Save the installer file (e.g., xampp-windows-x64-8.2.12-0-VC15.exe).

Step 2: Install XAMPP

Double-click the installer to begin.
Choose your language and click "OK".
Select components to install (default includes Apache, MySQL, PHP, and phpMyAdmin). Click "Next".
Choose the installation directory (default is C:\xampp) and click "Next".
Uncheck "Learn more about Bitnami" and click "Next".
Click "Install". You may need to allow firewall access for XAMPP modules.
Click "Finish" to complete the installation.

Step 3: Start XAMPP

Launch the XAMPP Control Panel from the Start menu or desktop shortcut.
Start the "Apache" and "MySQL" modules by clicking "Start" next to each.
The control panel will show a green status when both are running.

Step 4: Configure XAMPP

Open the XAMPP Control Panel and click "Config" next to Apache, then edit httpd.conf.
Ensure the following line is set to listen on port 80:Listen 80


Save and close the file.
Restart Apache from the control panel.

Step 5: Deploy EventHub Project

Locate the htdocs directory (default: C:\xampp\htdocs).
Extract the EventHub project files into a new folder, such as C:\xampp\htdocs\eventhub.
Ensure the folder contains necessary files (e.g., index.php, scan_tickets.php, sounds, media).

Step 6: Access the Project

Open a web browser and enter http://localhost/eventhub.
If the homepage loads, the installation is successful. Proceed to log in or use guest features.

Troubleshooting (XAMPP)

Apache Not Starting: Check for port conflicts (e.g., Skype, IIS). Stop conflicting services or change Apache to use port 8080 by editing httpd.conf.
MySQL Issues: Ensure no other MySQL instance is running. Stop it via the XAMPP control panel.

Additional Configuration
Database Setup

The EventHub project requires a MySQL database named event_db.
Using WAMP64:
Left-click the WAMP64 tray icon, select "phpMyAdmin".
In phpMyAdmin, click "New" on the left panel.
Enter event_db as the database name and click "Create".


Using XAMPP:
Open the XAMPP Control Panel, click "Admin" next to MySQL to launch phpMyAdmin.
Click "New" on the left panel.
Enter event_db as the database name and click "Create".


Note: Database schema and data import are not covered here; ensure your project includes the necessary SQL scripts.

Permissions

Ensure the project folder (eventhub) and its subdirectories (e.g., sounds, media) are readable and writable by the web server.
On Windows, right-click the folder, select "Properties" > "Security", and grant full control to the "Users" group if needed.

Testing the Installation

Open a browser and navigate to http://localhost/eventhub.
Test guest features (e.g., Home, Services, Bookings).
Log in as a system_admin or venue_admin to test admin functionalities (e.g., Scan Tickets).
If errors occur, check server logs (WAMP64: C:\wamp64\logs; XAMPP: C:\xampp\logs).

Uninstalling

WAMP64:
Stop all services via the tray icon.
Uninstall via "Control Panel" > "Programs and Features".
Manually delete C:\wamp64 if remnants remain.


XAMPP:
Stop all services via the control panel.
Uninstall via "Control Panel" > "Programs and Features".
Manually delete C:\xampp if remnants remain.



Troubleshooting

Server Not Starting: Ensure no other web server (e.g., IIS) is running. Change ports if needed.
404 Error: Verify the project folder name and URL path.
PHP Errors: Check php.ini settings and ensure required extensions (e.g., gd, mysqli) are enabled.

Contact Support
For assistance, email support@eventhub.com.
