EMPLOYEE INFORMATION SYSTEM
Jollibee Tupi - National Highway, Brgy. Poblacion, Tupi
BSIT Capstone Project

WHAT THIS INSTALLER DOES
------------------------
Everything the system needs is included. No separate download of Apache,
PHP, MySQL, Laragon or XAMPP is required.

  * Apache web server (port 8080)
  * PHP with all required extensions
  * MariaDB / MySQL database (port 3307)
  * The Employee Information System itself
  * The database is created and imported automatically

The ports 8080 and 3307 are used on purpose so the system never conflicts
with an existing Laragon, XAMPP or WAMP installation on the same computer.

AFTER INSTALLING
----------------
Open the "Employee Information System" shortcut on your Desktop or in the
Start Menu. The services start automatically and your browser opens at:

    http://localhost:8080/

Default administrator account:

    Username: admin
    Password: admin123

IMPORTANT: change this password after the first login.

STOPPING THE SYSTEM
-------------------
The web server and database keep running in the background after you close
the browser. To stop them, use:

    Start Menu > Employee Information System > Stop EIS Services

WHERE YOUR DATA IS
------------------
  Database         : <install folder>\stack\mariadb\data
  Uploaded files   : <install folder>\www\uploads
  Local backups    : <install folder>\www\backups

Use the Backup module inside the system to create backups and to sync them
to Google Drive.

TROUBLESHOOTING
---------------
The browser shows "can't connect"
    Another program may be using port 8080. Close Laragon/XAMPP, then open
    the shortcut again.

"Database connection failed"
    Open Start Menu > Stop EIS Services, then start the system again. If it
    persists, check install.log in the install folder.

Windows Firewall asks for permission the first time
    Choose "Allow access" for private networks so the system can run.

UNINSTALLING
------------
Use Windows Settings > Apps, or the Start Menu uninstall shortcut. You will
be asked whether to keep or delete the employee records.
