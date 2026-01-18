# BottleBank - A Web-Based Returnable Container Deposit and Refund Tracking System for Small Retail Stores

This is my capstone project for senior high school. It's a web-based system designed for small retail stores to track deposits and refunds for returnable containers (like bottles and cans), helping manage inventory and customer transactions efficiently.

## What the System Does

- **User Registration & Login**: People can create accounts and log in
- **Deposit Management**: Record when customers bring bottles to deposit
- **Returns Management**: Handle when customers want their bottles back
- **Stock Logging**: Keep track of all bottle movements
- **Admin Panel**: For managing the system and correcting records
- **API Endpoints**: For mobile app integration (future feature)

## Project Files

### Main Pages
- `index.php` - Main dashboard after login
- `login.php` - Login page
- `register.php` - Sign up page
- `deposit.php` - Add new deposits
- `returns.php` - Record bottle returns
- `refund.php` - Handle refunds
- `stock_log.php` - View all transactions

### Admin Stuff
- `admin/admin_panel.php` - Admin dashboard
- `admin/set_admin.php` - Setup admin account

### API (for future mobile app)
- `api/deposit.php`
- `api/login.php`
- `api/refund.php`
- `api/returns.php`

### Other Files
- `includes/db_connect.php` - Database connection
- `database/bottlbankdb1.sql` - Database setup
- `asset/style.css` - Website styling
- `utils/` - Some utility scripts I made

## How to Set It Up

1. First, import the database file `database/bottlbankdb1.sql` into MySQL
2. Make sure the database connection in `includes/db_connect.php` is correct
3. Put all files in your web server's htdocs folder
4. Go to `index.php` in your browser to start

## Database Tables

- `user` - User accounts
- `deposit` - Bottle deposits
- `returns` - Bottle returns
- `refund` - Money refunds
- `stock_log` - All transactions log

## Technologies Used

- PHP for the backend
- MySQL for the database
- HTML/CSS for the frontend
- JavaScript for some interactions

This project helped me learn a lot about web development and database management. I built it from scratch for my capstone defense.
- Stock logging and reporting
- Admin panel for corrections
- API endpoints for external integrations