Title: bottle bank system 



Introduction 

Small retail stores, especially sari-sari stores, rely heavily on returnable bottles as part 
of their daily product distribution. Each time a customer purchases a bottled product, a 
corresponding deposit is added, which is later withdrawn or refunded when the bottle is 
returned. Although simple in concept, this deposit–withdrawal cycle is difficult to track 
accurately when stores rely only on manual logs, notebooks, or memory. Mistakes such as 
forgotten entries, unclear handwriting, double-counted transactions, and misplaced records 
often result in incorrect balances, disputes over deposits, and an unreliable understanding 
of how many bottles are currently with customers. Without a clear system, store owners 
struggle to monitor which customers still have outstanding bottle deposits, how many bottles 
are in circulation, and how much money is owed or due for refunds.

To solve these issues, this study introduces BottleBank, a web-based system built to   
strengthen the monitoring of bottle deposit and withdrawal transactions. The system focuses 
on recording each deposit and refund in real time, ensuring that every bottle given out or 
returned is properly documented and assigned to the correct customer. BottleBank provides 
organized transaction histories and ensures every transaction is recorded. By centralizing 
transaction data, the system eliminates the confusion and inconsistencies commonly seen in 
manual recording. More importantly, it improves accuracy, promotes transparency, and gives 
store owners a reliable tool to understand the exact flow of bottles and deposits at any 
moment. With BottleBank, small stores can maintain better financial control, avoid losses 
from unmonitored bottles, and build greater trust with customers through clear and 
accountable bottle deposit/withdrawal monitoring.


BACKGROUND OF THE STUDY

For many years, sari-sari stores and small retailers have relied on handwritten logs to 
manage bottle deposits and refunds. While this method is simple, it becomes unreliable as 
transactions increase. Store owners often experience difficulties such as incorrect entries, 
lost records, mixed-up customer deposits, and inaccurate bottle counts. These challenges 
make it harder to monitor inventory, ensure fair refunds, and maintain smooth store 
operations.

With the growing availability of digital tools, many businesses have already shifted to 
automated inventory and tracking systems. However, most available solutions are designed for 
large supermarkets—not small stores that deal with returnable bottles. Because of this gap, 
there is a need for a simpler, more accessible system tailored specifically to the 
bottle-deposit cycle.

BottleBank was developed to fill this need. It focuses on essential features such as 
accurate logging, transaction history, refunds, and user accountability. By understanding the common issues faced by small retailers, BottleBank 
offers a solution that improves efficiency and record-keeping without requiring advanced 
technical skills.


Purpose of the Study

The purpose of this Project is to design, develop, and evaluate BottleBank, a web-based 
system that improves the accuracy, transparency, and efficiency of managing bottle deposits, 
returns, refunds, and stock records in small retail stores.

General Objective
To create a digital system that replaces manual bottle handling processes and supports 
accurate and efficient store operations.

Specific Objectives

To build a user-friendly system for recording bottle deposits, returns, and refunds.
To provide accurate real time transaction logs for bottle deposits, returns, and customer records.
To reduce human errors and lost records through digitized tracking.
To improve transparency and accountability through clear documentation and accessible 
history.
To assess the effectiveness of BottleBank in improving accuracy and efficiency in bottle 
handling.


USER DESCRIPTION

Admin (Store Owner)

Target User Group:

Store owner responsible for managing store operations and supervising employees.

Technical Skills:
Basic to intermediate computer literacy.

Domain Knowledge:
Understands bottle deposit systems and customer tracking.

User Needs:

Manage employee accounts and system access.
Record and monitor bottle deposits, returns, and refunds.
Ensure transparency and accountability.
Pain Points:

Manual logs are error-prone and time-consuming.
Difficulty tracking bottles without automation.
Needs organized, real-time data.
User (Store Employee).
Target User Group:
Store employee responsible for daily bottle transactions.

Technical Skills:
Basic computer literacy, familiar with simple data entry.

Domain Knowledge:
Understands basic bottle deposit and return processes.

User Needs:

Record bottle deposits during product sales.
Record bottle returns and refunds.
Update customer transactions accurately.
Use simple forms for fast and easy data entry.
Pain Points:

Manual recording is confusing and time-consuming.
Needs simple, user-friendly system.
Prefers clear transaction history.


SYSTEM DESCRIPTION

This BottleBank system automates the recording and monitoring of bottle deposits, returns, 
and refunds in small retail stores. Designed for both store owners (admins) and employees, 
the system provides real-time transaction recording with secure login and role-based access control. 
The system improves accuracy, reduces manual errors, and maintains complete transaction histories 
with timestamps and audit trails. It helps store owners and employees manage transactions efficiently, 
maintain organized records, and ensure data integrity through validation and accountability. 
BottleBank comes with pre-configured bottle types (Coke, Sprite, Royal, Pepsi) and comprehensive 
operational manuals for all system functions. Complete support documentation is provided including 
step-by-step guides for login, deposits, returns, refunds, employee management, and password changes.

Components:

Transaction Module
Manages all bottle deposit, return, and refund records. Employees enter customer name, 
bottle type, and quantity. Records are automatically timestamped and stored.

Inventory Management Module
Maintains transaction logs for bottle deposits and returns. Records all bottle movements 
and transaction details for audit and reference.

User Management Module
Stores and manages employee accounts. Controls login access and user roles. Allows admins to 
create, edit, reset, or delete accounts.

Security and Audit Module
Records user activities such as logins, edits, and transactions. Protects system data 
through role based access and secure authentication.

Features:

Real time recording of deposits, returns, and refunds.
Role based access control for admin and employee accounts.
Organized transaction history with timestamps and user records.
Simple interface designed for basic computer skills.
Secure login system with password protection.
Data validation to reduce input errors.
Operations:

Admins use the system to manage employee accounts, monitor all transactions, and update bottle 
types.
Employees use the system to record bottle deposits, returns, refunds, and view their 
transaction history.
The system automatically records timestamps and stores transaction logs after each action.
Benefits:

Increased accuracy in bottle tracking and financial records.
Reduced manual errors and lost information.
Improved transaction history and record organization.
Faster transaction processing for employees.
Enhanced transparency and accountability in store operations.

Operational Manual for Catalog Module: Admin (Add Bottle Type)

This manual will walk you through the process of adding new bottle types to the BottleBank system. You'll be able to add essential information about each bottle type, making it easily categorized and accessible for recording deposits, returns, and refunds.

Prerequisites:
- You must have admin access to the catalog management system.
- You should have the bottle type's details readily available, including the bottle type name.

Screenshot: (Image of the "Add Bottle Type" page in the admin panel will be inserted here once you provide the specific admin interface you are using.)

Steps:

1. Access the "Add Bottle Type" Function:
   - Locate the "Add Bottle Type" function within the system's menu. This might be under a section labeled "Bottle Types," "Catalog," or "Inventory Management."
   - Click on the "Add Bottle Type" button or menu option.

2. Enter Bottle Type Information:
   - A form will appear with a field for entering bottle type details.
   - Fill in the required field:
     a. Type Name: The name of the bottle type (e.g., "Coke 1.5L", "Sprite 500mL").

3. View Existing Bottle Types:
   - All bottle types are displayed in a table or list format within the catalog.
   - Review the table to find relevant and available bottle types for reference.

4. Edit Bottle Type Information:
   - Select the bottle type you wish to modify from the list.
   - Click the "Edit" or "Update" button.
   - Modify the fields as needed for accuracy.
   - Click the "Save" or "Confirm Changes" button.

5. Delete Bottle Type (Admin Only):
   - Select the bottle type you wish to remove from the list.
   - Click the "Delete" button and confirm the action.
   - Carefully review before deletion as this cannot be undone.
   - Note: A bottle type should not be deleted if active transactions exist for it.

Success Confirmation:
- The system will typically display a confirmation message indicating a successful bottle type addition or modification.
- The new bottle type should now be searchable and accessible in the bottle type catalog.


SYSTEM REQUIREMENTS

Minimum System Requirements:
- Web Browser: Chrome, Firefox, Safari, or Edge (latest versions)
- Internet Connection: Stable internet connection required
- Display: Minimum resolution of 1024x768 pixels
- Server: Apache/Nginx with PHP 7.4 or higher
- Database: MySQL 5.7 or MariaDB 10.3 or higher
- Storage: Minimum 100MB free disk space

Recommended System Requirements:
- Web Browser: Latest version of Chrome or Firefox
- Internet Connection: Broadband or fiber connection
- Display: 1366x768 or higher resolution
- Server: Apache/Nginx with PHP 8.0 or higher
- Database: MySQL 8.0 or MariaDB 10.5 or higher
- Storage: 500MB free disk space


GETTING STARTED: LOGIN GUIDE

Operational Manual for User Authentication: Login

This manual will walk you through the process of logging into the BottleBank system for the first time.

Prerequisites:
- You must have a valid username and password provided by your administrator.
- You must have access to the BottleBank web portal URL.

Steps:

1. Access the Login Page:
   - Open your web browser and navigate to the BottleBank login URL.
   - The login page will display fields for Username and Password.

2. Enter Your Credentials:
   - Type your username in the "Username" field.
   - Type your password in the "Password" field.

3. Submit Login:
   - Click the "Login" button.
   - The system will verify your credentials and authenticate your access.

4. Proceed to Dashboard:
   - Upon successful login, you will be directed to your dashboard.
   - Admins will see the admin panel with employee management options.
   - Employees will see the transaction entry interface for deposits, returns, and refunds.

Success Confirmation:
- You will see a welcome message or your user name displayed on the screen.
- The system will show your role (Admin or Employee) in the dashboard.


OPERATIONAL MANUAL FOR DEPOSIT TRANSACTION: EMPLOYEE (Record Deposit)

This manual will walk you through the process of recording a bottle deposit transaction in the BottleBank system.

Prerequisites:
- You must have employee access to the system with a valid login account.
- You should have the customer name, bottle type, and quantity information readily available.

Steps:

1. Access the Deposit Function:
   - Log in to the BottleBank system using your credentials.
   - Navigate to the "Deposit" option from the main menu or dashboard.
   - Click on "Record Deposit" or the Deposit button.

2. Enter Customer Information:
   - A form will appear with fields for customer details.
   - Enter the customer name in the "Customer Name" field.
   - Select the bottle type from the dropdown menu (e.g., Coke, Sprite, Royal, Pepsi).

3. Enter Transaction Details:
   - Input the quantity of bottles in the "Quantity" field.
   - Specify whether the bottles come with cases by selecting "With Case" if applicable.
   - If applicable, enter the case quantity.

4. Review Transaction:
   - Carefully review all entered information for accuracy.
   - Verify the customer name, bottle type, and quantity are correct.

5. Submit Deposit:
   - Click the "Submit" or "Record Deposit" button.
   - The system will process the transaction and assign a timestamp.

Success Confirmation:
- The system will display a confirmation message indicating successful deposit recording.
- The transaction will appear in your transaction history with a timestamp and user record.
- The deposit record is now stored in the system for audit and reference.


OPERATIONAL MANUAL FOR RETURN TRANSACTION: EMPLOYEE (Record Return)

This manual will walk you through the process of recording a bottle return transaction in the BottleBank system.

Prerequisites:
- You must have employee access to the system with a valid login account.
- You should have the customer name, bottle type, and quantity information readily available.

Steps:

1. Access the Return Function:
   - Log in to the BottleBank system using your credentials.
   - Navigate to the "Return" or "Returns" option from the main menu or dashboard.
   - Click on "Record Return" or the Returns button.

2. Enter Customer Information:
   - A form will appear with fields for customer details.
   - Enter the customer name in the "Customer Name" field.
   - Select the bottle type from the dropdown menu (e.g., Coke, Sprite, Royal, Pepsi).

3. Enter Transaction Details:
   - Input the quantity of bottles being returned in the "Quantity" field.
   - Specify whether the bottles come with cases by selecting "With Case" if applicable.
   - If applicable, enter the case quantity.

4. Review Transaction:
   - Carefully review all entered information for accuracy.
   - Verify the customer name, bottle type, and quantity are correct.

5. Submit Return:
   - Click the "Submit" or "Record Return" button.
   - The system will process the transaction and assign a timestamp.

Success Confirmation:
- The system will display a confirmation message indicating successful return recording.
- The transaction will appear in your transaction history with a timestamp and user record.
- The return record is now stored in the system for audit and reference.


OPERATIONAL MANUAL FOR REFUND PROCESSING: ADMIN (Process Refund)

This manual will walk you through the process of processing a refund transaction in the BottleBank system.

Prerequisites:
- You must have admin access to the system with a valid login account.
- You should have the customer name and refund amount information readily available.

Steps:

1. Access the Refund Function:
   - Log in to the BottleBank system using your admin credentials.
   - Navigate to the "Refund" or "Refunds" option from the admin menu.
   - Click on "Process Refund" or the Refunds button.

2. Enter Customer Information:
   - A form will appear with fields for refund details.
   - Enter the customer name in the "Customer Name" field.

3. Enter Refund Amount:
   - Input the refund amount in the "Amount" field.
   - Verify the amount is correct before submission.

4. Review Refund Details:
   - Carefully review all entered information for accuracy.
   - Verify the customer name and refund amount.

5. Submit Refund:
   - Click the "Submit" or "Process Refund" button.
   - The system will process the refund and assign a timestamp.

Success Confirmation:
- The system will display a confirmation message indicating successful refund processing.
- The refund record will appear in the transaction history with a timestamp and admin user record.
- The refund is now recorded in the system for audit and financial tracking.


OPERATIONAL MANUAL FOR EMPLOYEE MANAGEMENT: ADMIN (Manage Employee Accounts)

This manual will walk you through the process of managing employee accounts in the BottleBank system.

Prerequisites:
- You must have admin access to the system with a valid login account.

Steps:

1. Access Employee Management:
   - Log in to the BottleBank system using your admin credentials.
   - Navigate to the "Admin Panel" or "Employee Management" from the admin menu.
   - Click on "Manage Employees" or "User Management".

2. View Existing Employees:
   - A list of all existing employees will be displayed in a table format.
   - The list shows employee ID, username, email, role, and creation date.
   - Review the list to find the employee you wish to manage.

3. Create New Employee Account:
   - Click the "Create New Employee" or "Add User" button.
   - Enter the new employee's username, email, and initial password.
   - Set the user role (typically "user" for regular employees).
   - Click "Create Account" to add the new employee.

4. Edit Employee Information:
   - Select the employee you wish to modify from the list.
   - Click the "Edit" button.
   - Modify the fields as needed (email, username).
   - Click "Save Changes" to update the employee record.

5. Reset Employee Password:
   - Select the employee whose password needs to be reset.
   - Click the "Reset Password" button.
   - A temporary password will be generated for the employee.
   - Provide the temporary password to the employee securely.

6. Delete Employee Account:
   - Select the employee account to delete.
   - Click the "Delete" button and confirm the action.
   - The employee account will be removed from the system.

Success Confirmation:
- The system will display a confirmation message indicating successful employee account changes.
- Changes to employee accounts will appear immediately in the employee list.
- Affected employees will have access updated according to their new role or status.


OPERATIONAL MANUAL FOR PASSWORD MANAGEMENT: ALL USERS (Change Password)

This manual will walk you through the process of changing your password in the BottleBank system.

Prerequisites:
- You must have a valid login account in the BottleBank system.
- You must know your current password.

Steps:

1. Access Password Change:
   - Log in to the BottleBank system using your credentials.
   - Look for your user profile or account settings (usually in the top right corner).
   - Click on "Account Settings," "Profile," or your username.
   - Select "Change Password" from the menu options.

2. Enter Current Password:
   - A form will appear with password fields.
   - Enter your current password in the "Current Password" field for verification.

3. Enter New Password:
   - Enter your new password in the "New Password" field.
   - Ensure your password is strong (mix of letters, numbers, and special characters).
   - Enter the new password again in the "Confirm New Password" field to verify it.

4. Review Password Requirements:
   - Verify that your new password meets security requirements.
   - Passwords should be at least 8 characters long.

5. Submit Password Change:
   - Click the "Change Password" or "Update Password" button.
   - The system will verify and save your new password.

Success Confirmation:
- The system will display a confirmation message indicating successful password change.
- Your new password is now active and must be used for future logins.
- Any sessions will remain active; you will use the new password for your next login.


TROUBLESHOOTING GUIDE

Common Issues and Solutions:

Issue: Cannot Log In
- Solution: Verify your username and password are entered correctly. Check for CAPS LOCK. Contact your administrator if you have forgotten your password.

Issue: "Access Denied" Error
- Solution: This means your user account does not have permission to access that function. Contact your administrator to request appropriate access.

Issue: Transaction Not Saving
- Solution: Ensure all required fields are filled in. Check your internet connection. Try refreshing the page and resubmitting.

Issue: System Running Slow
- Solution: Clear your browser cache and cookies. Try using a different browser. Check your internet connection speed.

Issue: Cannot Find a Bottle Type
- Solution: Verify the exact spelling of the bottle type. Ask an admin to check if the bottle type exists in the system. A new bottle type may need to be added.

Issue: Employee Account Not Created
- Solution: Verify all required fields are completed. Ensure the username is unique and not already in use. Check for any error messages displayed.

Issue: "Database Connection Error"
- Solution: The server may be temporarily unavailable. Wait a few minutes and try again. Contact your administrator if the error persists.


FREQUENTLY ASKED QUESTIONS (FAQ)

Q: How do I reset my password if I forget it?
A: Contact your administrator. They can reset your password and provide you with a temporary one.

Q: Can I delete a bottle type?
A: Only admins can delete bottle types. A bottle type cannot be deleted if active transactions exist for it.

Q: How long are transaction records kept?
A: All transaction records are permanently stored in the system for audit and reference purposes.

Q: Can I edit a transaction after it's recorded?
A: Transactions are permanent records and cannot be edited. Contact an administrator if a correction is needed.

Q: What happens when I log out?
A: Your session will end. You will need to log in again to access the system. All recorded transactions are saved.

Q: Can multiple users record transactions simultaneously?
A: Yes, multiple employees can work in the system at the same time. Each transaction is timestamped and logged separately.

Q: Is my data secure?
A: Yes, the system uses role-based access control and secure password protection. All user activities are logged for security auditing.

Q: How do I view my transaction history?
A: Log in, navigate to your transaction history or reports section, and you will see all transactions you have recorded with timestamps.


DATA SECURITY AND PRIVACY POLICY

Security Measures:
- Secure Login: All users must authenticate with a username and password.
- Role-Based Access Control: Users can only access functions appropriate to their role (Admin or Employee).
- Secure Password Storage: Passwords are encrypted and never displayed in plain text.
- Session Management: Inactive sessions automatically expire after a specified period.
- Data Encryption: All sensitive data is encrypted during transmission and storage.

User Accountability:
- Audit Trail: All user activities (logins, transactions, edits) are recorded with timestamps.
- User Records: Every transaction includes the user ID and timestamp for accountability.
- Access Logging: All login attempts are logged for security monitoring.

Data Privacy:
- Data Protection: Personal and transaction data is protected and not shared with unauthorized parties.
- Limited Access: Only authorized admins can view and manage user and transaction data.
- Confidentiality: Employee and customer information is kept confidential within the system.

Data Backup and Recovery:
- Regular Backups: System data is regularly backed up to prevent data loss.
- Disaster Recovery: In case of system failure, data can be recovered from backups.
- Data Integrity: All transactions are validated to ensure data accuracy and integrity.


GLOSSARY OF TERMS

Deposit: A record of bottles given to a customer or received from a supplier.
Return: A record of bottles returned by a customer.
Refund: A monetary payment made to a customer for returned bottles.
Transaction: A recorded deposit, return, or refund event in the system.
Bottle Type: A category of bottles (e.g., Coke, Sprite) with specific characteristics.
Timestamp: The automatic date and time recorded for each transaction.
User Role: The type of user account (Admin or Employee) that determines system access.
Admin: A store owner or manager with full system access and employee management capabilities.
Employee: A store staff member with access to record deposits, returns, and refunds.
Audit Trail: A complete record of all system activities and transactions for security and accountability.
Session: The period during which a user is logged into the system.
Authentication: The process of verifying a user's identity through login credentials.


OPERATIONAL MANUAL FOR USER LOGOUT: ALL USERS (Logout/Exit)

This manual will walk you through the process of securely logging out of the BottleBank system.

Prerequisites:
- You must be currently logged into the BottleBank system.

Steps:

1. Locate the Logout Option:
   - Look for the user profile menu or account settings in the top right corner of the screen.
   - Click on your username or the menu icon (usually three horizontal lines or a dropdown arrow).

2. Select Logout:
   - From the menu options, click on "Logout," "Sign Out," or "Exit."

3. Confirm Logout:
   - The system may display a confirmation message asking if you want to logout.
   - Click "Yes" or "Confirm" to proceed with logging out.

4. Return to Login Page:
   - You will be redirected to the BottleBank login page.
   - Your session has ended and you are no longer logged in.

Success Confirmation:
- You will see the login page displayed on your screen.
- Your session data is cleared for security purposes.
- You can now close the browser window or log in again with different credentials.

Security Note:
- Always logout when finished using the system, especially on shared computers.
- Do not leave the system unattended while logged in.
- Your account and data are secure once you have logged out.


PRE-LOADED BOTTLE TYPES

The BottleBank system comes with the following pre-loaded bottle types ready for use:

1. Coke - Standard cola bottle
2. Sprite - Lemon-lime flavored bottle
3. Royal - Cola alternative bottle
4. Pepsi - Cola product bottle

These bottle types are available immediately upon system startup and can be used for:
- Recording deposits
- Recording returns
- Processing transactions

Admins can add additional bottle types as needed using the "Add Bottle Type" function in the Catalog Module.


SUPPORT AND CONTACT INFORMATION

For Technical Support:
- Contact your system administrator for assistance with system access or technical issues
- Provide your username and a description of the issue you are experiencing

For System Maintenance:
- The BottleBank system is maintained by your IT department or system administrator
- Report any system downtime or errors to your administrator immediately

For Documentation:
- Refer to the operational manuals provided in this documentation
- Visit the Glossary section for definitions of technical terms
- Check the Troubleshooting Guide for common issues and solutions

For Feature Requests:
- Submit feature requests or improvement suggestions to your system administrator
- Provide specific details about the requested functionality


CONCLUSION

BottleBank is a comprehensive web-based solution designed specifically for small retail stores to efficiently manage bottle deposit and withdrawal transactions. By automating the recording process and maintaining secure, organized transaction histories, BottleBank eliminates the confusion and errors associated with manual record-keeping.

The system provides:
- Real-time transaction recording with automatic timestamping
- Role-based access control for secure and appropriate user permissions
- Complete transaction histories for audit and financial tracking
- User-friendly interfaces for both administrators and employees
- Data security through encryption and secure authentication

With its intuitive design and robust features, BottleBank empowers store owners to maintain better financial control, ensure fair customer transactions, and build greater trust through transparent and accountable bottle deposit/withdrawal monitoring.

For support or questions about using BottleBank, contact your system administrator or refer to the operational manuals provided in this documentation.

Version: 1.0
Last Updated: March 7, 2026