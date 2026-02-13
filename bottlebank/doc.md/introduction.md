introduction
Small retail stores, especially sari-sari stores, rely heavily on returnable bottles as part of their daily product distribution. Each time a customer pusrchases a bottled product, a corresponding deposit is added, which is later withdrawn or refunded when the bottle is returned. Although simple in concept, this deposit–withdrawal cycle is difficult to track accurately when stores rely only on manual logs, notebooks, or memory. Mistakes such as forgotten entries, unclear handwriting, double-counted transactions, and misplaced records often result in incorrect balances, disputes over deposits, and an unreliable understanding of how many bottles are currently with customers. Without a clear system, store owners struggle to monitor which customers still have outstanding bottle deposits, how many bottles are in circulation, and how much money is owed or due for refunds.

To solve these issues, this study introduces BottleBank, a web-based system built to strengthen the monitoring of bottle deposit and withdrawal transactions. The system focuses on recording each deposit and refund in real time, ensuring that every bottle given out or returned is properly documented and assigned to the correct customer. BottleBank provides organized transaction histories, automated calculations of deposit balances, alerts for unreturned bottles, and updated tracking of total bottle inventory. By centralizing transaction data, the system eliminates the confusion and inconsistencies commonly seen in manual recording. More importantly, it improves accuracy, promotes transparency, and gives store owners a reliable tool to understand the exact flow of bottles and deposits at any moment. With BottleBank, small stores can maintain better financial control, avoid losses from unmonitored bottles, and build greater trust with customers through clear and accountable bottle deposit/withdrawal monitoring.

Purpose of the Study
The purpose of this Project is to design, develop, and evaluate BottleBank, a web-based system that improves the accuracy, transparency, and efficiency of managing bottle deposits, returns, refunds, and stock records in small retail stores.

General Objective
To create a digital system that replaces manual bottle handling processes and supports accurate and efficient store operations.

Specific Objectives

To build a user-friendly system for recording bottle deposits, returns, and refunds.
To provide accurate real time transaction logs for bottle inventory and customer deposits.
To reduce human errors and lost records through digitized tracking.
To improve transparency and accountability through clear documentation and accessible history.
To produce organized summary reports for daily store decisions.
To assess the effectiveness of BottleBank in improving accuracy and efficiency in bottle handling.

BACKGROUND OF THE STUDY

For many years, sari-sari stores and small retailers have relied on handwritten logs to manage bottle deposits and refunds. While this method is simple, it becomes unreliable as transactions increase. Store owners often experience difficulties such as incorrect entries, lost records, mixed-up customer deposits, and inaccurate bottle counts. These challenges make it harder to monitor inventory, ensure fair refunds, and maintain smooth store operations.

With the growing availability of digital tools, many businesses have already shifted to automated inventory and tracking systems. However, most available solutions are designed for large supermarkets—not small stores that deal with returnable bottles. Because of this gap, there is a need for a simpler, more accessible system tailored specifically to the bottle-deposit cycle.

BottleBank was developed to fill this need. It focuses on essential features such as accurate logging, inventory monitoring, transaction history, refunds, and user accountability. By understanding the common issues faced by small retailers, BottleBank offers a solution that improves efficiency and record-keeping without requiring advanced technical skills.


user and system description

USER DESCRIPTION

Admin (Store Owner/Manager)

Target User Group:

Store owners, managers, or supervisory personnel responsible for overall store operations and staff management

Technical Skills:

Intermediate computer literacy; comfortable with system administration tasks, user management, and accessing multiple modules

Domain Knowledge:

Understands store operations, bottle deposit systems, inventory management, customer relationships, and business reporting

Responsibilities / User Needs:

- Create, view, edit, and delete employee user accounts
- Reset employee passwords and force password changes
- Manage bottle types (add, edit, delete)
- Monitor ALL bottle deposit, return, and refund transactions (not just their own)
- Edit and correct employee transaction records
- View complete transaction history and logs
- Access admin panel for account management
- Generate reports from transaction data
- Ensure data accuracy and system security

Pain Points:

- Difficulty managing multiple employees without a central system
- Inability to correct erroneous entries made by staff
- Lack of visibility into all store transactions
- Manual password management and account access control
- No audit trail for administrative actions

User (Store Employee/Staff)

Target User Group:

Store staff responsible for daily customer-facing transactions and bottle handling

Technical Skills:

Basic computer literacy; familiar with simple data entry, forms, and reading dashboards; minimal technical knowledge required

Domain Knowledge:

Understands basic bottle deposit and return processes, customer interactions, and day-to-day store operations

Responsibilities / User Needs:

- Record bottle deposits when customers purchase bottled products
- Record bottle returns when customers bring back bottles
- Record refunds for returned bottles
- Enter customer names and bottle types accurately
- Input transaction quantities and amounts
- View own transaction history for reference
- Change personal password when prompted
- Access simple, easy-to-use forms for fast data entry

Pain Points:

- Manual entry is time-consuming and error-prone
- Cannot access previous records to verify customer information
- Frustration with complex systems or unclear instructions
- Difficulty remembering bottle types and customer details
- No automated validation or calculations

SYSTEM DESCRIPTION

Admin Operations:

**User Account Management**
- View all employees with pagination and search capability
- Create new employee accounts during registration
- Edit existing employee accounts (username, email, role, password)
- Reset employee passwords and force password change at next login
- Delete employee accounts if needed
- Assign roles (admin/user) to accounts
- Search and filter employees

**Transaction Management & Corrections**
- View ALL transaction records across all employees (not just their own)
- Edit deposit transactions (customer name, bottle type, quantity)
- Edit return transactions (customer name, bottle type, quantity)
- Edit refund transactions (customer name, amount)
- Delete transactions if necessary
- Add correction notes to transaction log
- Filter transactions by various criteria

**Bottle Type Management**
- Add new bottle types to the system
- Edit existing bottle type names
- Delete unused bottle types
- View complete bottle type inventory
- Prevent duplicate bottle types

**Data Access & Reporting**
- Access complete stock log with all transactions
- View transaction history with full details
- Filter by day, month, or year
- Search transactions by customer name, bottle type, action type, quantity, or amount
- Access password change logs
- Access authentication/access attempt logs
- Export or review all transaction data

**System Security**
- Monitor unauthorized access attempts (logged in auth.log)
- Control who can access what
- Audit password changes
- Enforce security policies

Employee Operations:

**Record Deposits**
- Add new bottle deposit records
- Enter customer name
- Select bottle type from dropdown
- Enter quantity
- Enter deposit amount (optional)
- Specify if bottles come with cases
- Submit deposit records to database
- Receive confirmation of successful entry

**Record Returns**
- Add new bottle return records
- Enter customer name
- Select bottle type from dropdown
- Enter return quantity
- Specify if cases are included
- Submit return records
- Receive confirmation

**Record Refunds**
- Add new refund records
- Enter customer name
- Enter refund amount
- Submit refund records
- Receive confirmation

**View Personal Data**
- View dashboard showing personal transaction counts
- Access own transaction history only
- View stock log filtered to only their records
- Access personal password change page

**Account Management**
- Change personal password
- Complete forced password changes at login
- Update account information through dashboard

System Features (Common to Both):

- Real-time transaction updates with automatic timestamps
- Automatic calculation of transaction totals, customer balances, and inventory levels
- Complete transaction history with dates, times, users, amounts, and action types
- Role-based access control for security and data protection
- User-friendly interface with simple forms and clear navigation
- Responsive design for desktop, tablet, and mobile devices
- Dashboard overview showing transaction counts and key metrics
- Secure authentication with password hashing and session management
- Complete audit trail with user ID, timestamp, and action details
- Secure password management with change capability
- Data validation to ensure accurate input
- Clear error messages to help users correct mistakes
- Transaction logging for all activities (deposits, returns, refunds, corrections)

Benefits:

**Admin Benefits:**
- Complete oversight and control of all store operations
- Ability to correct mistakes immediately
- Comprehensive transaction reports for decision-making
- Employee accountability through detailed logs
- Reduced financial losses from errors
- Professional audit trail for compliance

**Employee Benefits:**
- Fast, simple data entry process
- Reduced errors through system validation
- Clear confirmation of successful entries
- Automatic calculations eliminate manual math errors
- Access to transaction history for reference
- Easy-to-use interface requires minimal training

**Both Users:**
- Accurate records of all transactions
- Improved operational efficiency
- Reduced confusion and disputes
- Transparent, organized documentation
- Better customer trust through accountability
- Professional system vs. manual logs