<?php
// BottleBank Dashboard - My capstone project
// Made by [Your Name] for senior high school
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// I made this function to count records for each user
function count_for_user($conn, $table, $user_id) {
    $allowed = ['deposit','returns','refund','stock_log'];
    if (!in_array($table, $allowed)) return 0;
    $sql = "SELECT COUNT(*) AS cnt FROM `$table` WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return intval($res['cnt'] ?? 0);
}

// Get the counts for the dashboard
$deposit_count = count_for_user($conn, 'deposit', $user_id);
$return_count  = count_for_user($conn, 'returns', $user_id);
$refund_count  = count_for_user($conn, 'refund', $user_id);
$log_count     = count_for_user($conn, 'stock_log', $user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard • BottleBank</title>
<style>
body {
    font-family: 'Poppins', sans-serif;
    margin:0;
    background: #f0f7f7;
    color: #333;
    display: flex;
}

/* Sidebar */
.sidebar {
    width: 250px;
    background: linear-gradient(180deg, #2d6a6a 0%, #1e4a4a 100%);
    color: white;
    padding: 30px 0;
    min-height: 100vh;
    box-shadow: 4px 0 20px rgba(0,0,0,0.15);
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
}
.sidebar .brand {
    padding: 0 20px;
    margin-bottom: 40px;
    border-bottom: 2px solid rgba(255,255,255,0.15);
    padding-bottom: 20px;
}
.sidebar .brand h1 {
    margin: 0;
    font-size: 24px;
    color: white;
    font-weight: 700;
    letter-spacing: -0.3px;
}
.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 14px 20px;
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
    font-size: 15px;
    border-left: 4px solid transparent;
    margin: 0 8px 0 0;
}
.sidebar-nav a:hover {
    background: rgba(255,255,255,0.1);
    border-left-color: #80cbc4;
    color: #80cbc4;
    padding-left: 20px;
}
.sidebar-nav a.active {
    background: rgba(128,203,196,0.25);
    border-left-color: #80cbc4;
    color: #80cbc4;
    padding-left: 20px;
    font-weight: 600;
}
.sidebar-nav .logout {
    margin-top: auto;
    border-top: 2px solid rgba(255,255,255,0.1);
    padding-top: 20px;
    margin: auto 8px 0 0;
}

.app { 
    margin-left: 250px;
    padding: 20px;
    flex: 1;
}

/* Topbar */
.topbar {
    display:flex;
    justify-content: space-between;
    align-items:center;
    background:linear-gradient(135deg, #ffffff 0%, #f8fbfb 100%);
    padding:24px 28px;
    border-radius:16px;
    box-shadow:0 4px 20px rgba(45,106,106,0.08);
    margin-bottom:28px;
    border:1px solid rgba(128,203,196,0.1);
}
.topbar .brand {
    display:flex;
    align-items:center;
    gap:15px;
}
.topbar .brand h1 { 
    margin:0; 
    color:#2d6a6a;
    font-size:24px;
    font-weight:700;
}
.topbar .toggle-sidebar {
    background:none;
    border:none;
    font-size:18px;
    cursor:pointer;
    color:#2d6a6a;
    transition:all 0.3s;
    padding:8px;
    border-radius:8px;
    font-weight:600;
    display:none;
}
.topbar .toggle-sidebar:hover {
    background:rgba(45,106,106,0.1);
    color:#00796b;
}

/* Show Menu button only on mobile */
@media (max-width: 768px) {
    .topbar .toggle-sidebar {
        display:block;
    }
}
.menu-wrap { 
    display: flex; 
    align-items: center; 
    gap: 15px;
    color:#6c7a89;
    font-size:14px;
}
.menu-wrap strong {
    color:#2d6a6a;
    font-weight:600;
}

/* Dashboard Cards */
.grid {
    display:grid;
    grid-template-columns: repeat(auto-fit,minmax(240px,1fr));
    gap:24px;
    margin-bottom:30px;
}
.card {
    background:linear-gradient(135deg, #ffffff 0%, #f5fffe 100%);
    border-radius:16px;
    padding:28px;
    box-shadow:0 4px 20px rgba(45,106,106,0.08);
    text-align:center;
    transition:all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    border:1px solid rgba(128,203,196,0.15);
    position:relative;
    overflow:hidden;
}
.card::before {
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:0;
    height:4px;
    background:linear-gradient(90deg, #26a69a 0%, #80cbc4 100%);
}
.card:hover { 
    transform: translateY(-8px);
    box-shadow:0 12px 35px rgba(45,106,106,0.15);
    border-color:rgba(128,203,196,0.3);
}
.card .label { 
    font-weight:600; 
    color:#6c7a89;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}
.card .value { 
    font-size:36px; 
    font-weight:800; 
    margin:16px 0 12px 0; 
    color:#2d6a6a;
    line-height:1;
}
.card .link { 
    color:#00796b; 
    text-decoration:none; 
    font-weight:600;
    display:inline-block;
    margin-top:12px;
    padding:6px 0;
    border-bottom:2px solid transparent;
    transition:all 0.3s;
    font-size:13px;
}
.card .link:hover { 
    border-bottom-color:#00796b;
    transform:translateX(3px);
}

/* Recent Activity Table */
.panel {
    background:linear-gradient(135deg, #ffffff 0%, #f5fffe 100%);
    border-radius:16px;
    padding:28px;
    margin-top:28px;
    box-shadow:0 4px 20px rgba(45,106,106,0.08);
    border:1px solid rgba(128,203,196,0.1);
}
.panel h3 { 
    margin-top:0;
    margin-bottom:20px;
    color:#2d6a6a;
    font-size:20px;
    font-weight:700;
    letter-spacing:-0.3px;
}
.panel table { 
    width:100%; 
    border-collapse:collapse; 
    margin-top:12px;
}
.panel th, .panel td { 
    text-align:left; 
    padding:14px 16px;
}
.panel th { 
    background:linear-gradient(135deg, #e0f2f1 0%, #d4edea 100%);
    color:#00796b;
    font-weight:700;
    position:sticky;
    top:0;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}
.panel tr { 
    border-bottom:1px solid rgba(45,106,106,0.08);
    transition:background 0.3s;
}
.panel tr:hover {
    background:rgba(128,203,196,0.08);
}
.panel tr:last-child { 
    border-bottom:none;
}
.panel td {
    color:#4a4a4a;
    font-size:14px;
}
.panel td:first-child {
    font-weight:600;
}

/* Quick Actions */
.quick-actions { 
    display:flex; 
    gap:14px; 
    margin-top:24px; 
    flex-wrap:wrap;
}
.quick-actions button {
    flex:1;
    min-width:140px;
    padding:14px 18px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
    color:white;
    transition:all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    font-size:14px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}
.quick-actions .primary { 
    background:linear-gradient(135deg, #26a69a 0%, #1e8a7f 100%);
    box-shadow:0 4px 15px rgba(38,166,154,0.3);
}
.quick-actions .primary:hover { 
    background:linear-gradient(135deg, #2e7d7d 0%, #1a706d 100%);
    box-shadow:0 8px 25px rgba(38,166,154,0.4);
    transform:translateY(-2px);
}
.quick-actions .ghost { 
    background:rgba(128,203,196,0.2);
    color:#004d40;
    border:2px solid #80cbc4;
}
.quick-actions .ghost:hover { 
    background:linear-gradient(135deg, #80cbc4 0%, #4db6ac 100%);
    color:white;
    box-shadow:0 8px 25px rgba(128,203,196,0.3);
    transform:translateY(-2px);
}

/* Footer */
.footer { 
    text-align:center; 
    margin-top:40px; 
    padding:20px;
    color:#8a9aaa;
    font-size:13px;
    letter-spacing:0.3px;
}

@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        left: -250px;
        z-index: 999;
        transition: left 0.3s;
    }
    
    .sidebar.active {
        left: 0;
    }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 998;
    }
    
    .sidebar-overlay.active {
        display: block;
    }
    
    .app {
        margin-left: 0;
    }
    
    .grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<!-- Sidebar Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <h1>BB</h1>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="active">Dashboard</a>
        <a href="deposit.php">Deposit</a>
        <a href="returns.php">Returns</a>
        <a href="refund.php">Refund</a>
        <a href="stock_log.php">Stock Log</a>
        <?php if($is_admin): ?>
        <a href="admin/admin_panel.php">Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</div>

<div class="app">

    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button>
            <h1>Dashboard</h1>
        </div>
        <div class="menu-wrap">
            <span>Signed in as <strong><?= $username ?></strong></span>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="grid">
        <div class="card">
            <div class="label">Deposits</div>
            <div class="value"><?= $deposit_count ?></div>
            <a class="link" href="deposit.php">Add / View Deposits →</a>
        </div>
        <div class="card">
            <div class="label">Returns</div>
            <div class="value"><?= $return_count ?></div>
            <a class="link" href="returns.php">Add / View Returns →</a>
        </div>
        <div class="card">
            <div class="label">Refunds</div>
            <div class="value"><?= $refund_count ?></div>
            <a class="link" href="refund.php">Add / View Refunds →</a>
        </div>
        <div class="card">
            <div class="label">Stock Log</div>
            <div class="value"><?= $log_count ?></div>
            <a class="link" href="stock_log.php">View Log →</a>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="panel">
        <h3>Recent Activity</h3>
        <?php
        $query = "
            (SELECT 'Deposit' AS type, deposit_date AS date, CONCAT(quantity,' bottles deposited (',bottle_type,')') AS details FROM deposit WHERE user_id=?)
            UNION
            (SELECT 'Return' AS type, return_date AS date, CONCAT(quantity,' bottles returned (',bottle_type,')') AS details FROM returns WHERE user_id=?)
            UNION
            (SELECT 'Refund' AS type, refund_date AS date, CONCAT('Refunded ₱',amount) AS details FROM refund WHERE user_id=?)
            UNION
            (SELECT 'Stock Log' AS type, date_logged AS date, CONCAT(action_type,' — ',quantity,' bottles (₱',amount,')') AS details FROM stock_log WHERE user_id=?)
            ORDER BY date DESC
            LIMIT 10
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        ?>
        <table>
            <thead>
                <tr><th>Type</th><th>Date</th><th>Details</th></tr>
            </thead>
            <tbody>
            <?php if($result->num_rows>0): ?>
                <?php while($row=$result->fetch_assoc()): 
                    $type = htmlspecialchars($row['type']);
                    $date = date("M d, Y — h:i A", strtotime($row['date']));
                    $details = htmlspecialchars(!empty($row['details']) ? $row['details'] : 'N/A');
                    $color = match($type) {
                        'Deposit'=>'#26a69a','Return'=>'#29b6f6','Refund'=>'#ef5350','Stock Log'=>'#8e24aa', default=>'#555'
                    };
                ?>
                <tr>
                    <td style="color:<?= $color ?>; font-weight:600;"><?= $type ?></td>
                    <td><?= $date ?></td>
                    <td><?= $details ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3" style="text-align:center;color:#888;">No recent activities yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <div style="text-align:right;margin-top:10px;">
            <a href="stock_log.php" style="color:#00796b;font-weight:500;text-decoration:none;">View all →</a>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">© <?=date('Y')?> BottleBank — Built with care</div>
</div>

<script>
function toggleSidebar(){
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  sidebar.classList.toggle('active');
  overlay.classList.toggle('active');
}

// Close sidebar when clicking on a navigation link
document.querySelectorAll('.sidebar-nav a').forEach(link => {
  link.addEventListener('click', function(){
    if(window.innerWidth <= 768){
      toggleSidebar();
    }
  });
});
</script>
</body>
</html>
