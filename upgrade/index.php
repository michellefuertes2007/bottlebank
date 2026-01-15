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
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
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
    background: linear-gradient(135deg, #2d6a6a 0%, #1e4a4a 100%);
    color: white;
    padding: 30px 0;
    min-height: 100vh;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    position: fixed;
    left: 0;
    top: 0;
}
.sidebar .brand {
    padding: 0 20px;
    margin-bottom: 40px;
    border-bottom: 2px solid rgba(255,255,255,0.1);
    padding-bottom: 20px;
}
.sidebar .brand h1 {
    margin: 0;
    font-size: 24px;
    color: white;
}
.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    color: white;
    text-decoration: none;
    transition: 0.3s;
    font-weight: 500;
}
.sidebar-nav a:hover {
    background: rgba(255,255,255,0.1);
    border-left: 4px solid #80cbc4;
    padding-left: 16px;
}
.sidebar-nav a.active {
    background: rgba(128,203,196,0.2);
    border-left: 4px solid #80cbc4;
    padding-left: 16px;
}
.sidebar-nav .logout {
    margin-top: auto;
    border-top: 2px solid rgba(255,255,255,0.1);
    padding-top: 20px;
    margin-left: 0;
    margin-right: 0;
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
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
    margin-bottom:20px;
}
.brand h1 { margin:0; color:#2d6a6a; }
.brand p { margin:0; color:#6c7a89; font-size:14px; }
.menu-wrap { display: flex; align-items: center; gap: 15px; }

/* Dashboard Cards */
.grid {
    display:grid;
    grid-template-columns: repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
}
.card {
    background:white;
    border-radius:12px;
    padding:20px;
    box-shadow:0 4px 15px rgba(0,0,0,0.05);
    text-align:center;
    transition:0.3s;
}
.card:hover { transform: translateY(-5px); box-shadow:0 8px 20px rgba(0,0,0,0.08); }
.card .label { font-weight:600; color:#6c7a89; }
.card .value { font-size:28px; font-weight:700; margin:10px 0; color:#2d6a6a; }
.card .link { color:#00796b; text-decoration:none; font-weight:500; }
.card .link:hover { text-decoration:underline; }

/* Recent Activity Table */
.panel {
    background:white;
    border-radius:12px;
    padding:20px;
    margin-top:20px;
    box-shadow:0 4px 15px rgba(0,0,0,0.05);
}
.panel h3 { margin-top:0; color:#2d6a6a; }
.panel table { width:100%; border-collapse:collapse; margin-top:10px; }
.panel th, .panel td { text-align:left; padding:12px; }
.panel th { background:#e0f2f1; color:#00796b; font-weight:600; position:sticky; top:0; }
.panel tr { border-bottom:1px solid #eee; }
.panel tr:last-child { border-bottom:none; }

/* Quick Actions */
.quick-actions { display:flex; gap:12px; margin-top:20px; flex-wrap:wrap; }
.quick-actions button {
    flex:1;
    min-width:140px;
    padding:12px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    color:white;
    transition:0.3s;
}
.quick-actions .primary { background:#26a69a; }
.quick-actions .primary:hover { background:#2e7d7d; }
.quick-actions .ghost { background:#80cbc4; color:#004d40; }
.quick-actions .ghost:hover { background:#4db6ac; }

/* Footer */
.footer { text-align:center; margin-top:30px; color:#6c7a89; font-size:14px; }
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <h1>BottleBank</h1>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php" class="active">🏠 Dashboard</a>
        <a href="deposit.php">💰 Deposit</a>
        <a href="returns.php">🔁 Returns</a>
        <a href="refund.php">💸 Refund</a>
        <a href="stock_log.php">📦 Stock Log</a>
        <?php if($is_admin): ?>
        <a href="admin/admin_panel.php">⚙️ Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">🚪 Logout</a>
    </nav>
</div>

<div class="app">

    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
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
                    $icon = match($type) {
                        'Deposit'=>'💰','Return'=>'🔁','Refund'=>'💸','Stock Log'=>'📦', default=>'📋'
                    };
                    $color = match($type) {
                        'Deposit'=>'#26a69a','Return'=>'#29b6f6','Refund'=>'#ef5350','Stock Log'=>'#8e24aa', default=>'#555'
                    };
                ?>
                <tr>
                    <td style="color:<?= $color ?>; font-weight:600;"><?= $icon ?> <?= $type ?></td>
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

</body>
</html>
