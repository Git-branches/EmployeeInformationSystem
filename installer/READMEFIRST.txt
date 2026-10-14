EMPLOYEE INFORMATION SYSTEM  1.3.0

Employee and applicant records, requirements monitoring, reports and backups.

WHAT IS NEW IN 1.3.0
--------------------
  * New Settings page, under the menu on the left. Your own account
    details live here: full name, username, position/designation and
    e-mail address. The password form has moved here too, beside them.
  * Reports are now signed by whoever generated them. The signature at
    the foot of a report carries the name and designation saved in
    Settings, instead of the fixed word "Administrator". Change them in
    Settings and the next report you generate shows the new details -
    on screen, on paper, in the PDF and in the Excel file.
  * A report with every column ticked now fits the printed sheet. Before,
    the columns on the right ran off the edge of the paper and were lost.
  * The Excel export now carries the signature as well; previously it
    ended at the last record.
  * A wide report on screen is easier to move around: click the table and
    use the arrow keys. The reminder that there are more columns to the
    right now stays in view until every column has been seen.

WHAT WAS NEW IN 1.2.2
---------------------
  * New "Clear Form" button on Add Employee, so a half-filled entry you
    walked away from can be thrown out in one go instead of field by
    field. On Edit it reads "Undo Changes" and puts back the saved
    details without touching the record.
  * The emergency contact number is now formatted like the employee's
    own CP number: 0994-800-7500.
  * The printed Employee Information Form is one upright (portrait)
    sheet again, instead of printing sideways across two pages. The
    requirements checklist stays on screen and on its own page under
    Requirements.
  * After an update the browser now picks up the new screens by itself;
    previously it could keep showing the previous version until its
    cache was cleared by hand.

WHAT WAS NEW IN 1.2.1
---------------------
  * The employee list now filters as you type - no need to press Enter
    or the search button. It also searches the middle name, the employee
    number and the position, in any order.
  * New report options: an Overall report that groups every employee
    under their department with a summary of the department sizes, or a
    report for one department only. Tick which information to include
    (22 items, with Select all); only ticked items appear in the report,
    the printout and the PDF/Excel exports.
  * The menu and the top bar now stay in place while the page scrolls,
    so they are always within reach on long lists and reports.

WHAT WAS NEW IN 1.2.0
---------------------
  * Employee name now has Surname, First Name, Middle Name (with a
    "No middle name" box) and Extension, and is shown everywhere as
    SURNAME, FIRST M. EXT. - for example ROMERO, RHON J. JR.
  * CP Number is formatted automatically as 0994-800-7500 and must be a
    valid mobile number.
  * Address is split into Purok / Street (typed) and Province, City /
    Municipality and Barangay (chosen from lists).
  * New GSIS No. field beside the TIN.
  * New Positions and Employment Status pages in the menu. The employee
    form now picks Position and Employment Status from these lists.
  * Monthly Salary is shown with commas and two decimals, e.g. 1,000.09.

WHAT WAS NEW IN 1.1.0
---------------------
  * Names, addresses and other information-form entries are now typed and
    saved in UPPER CASE automatically. Email addresses, contact numbers and
    ID numbers are left exactly as entered.
  * New Monthly Salary field on the employee form, with the Daily Salary
    computed automatically as Monthly Salary / 22 working days. Both amounts
    appear on the profile, the printed information form and the PDF.
  * The administrator can now change their own password from inside the
    system: click your name at the top right, then "Change Password".

Installing over an existing copy keeps your records: the database is updated
in place, never replaced.

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

IMPORTANT: change this password immediately after the first login. Click
your name at the top right of the screen, then "Change Password". The
system holds personal information about your employees, so it must not be
left on the password printed above.

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
