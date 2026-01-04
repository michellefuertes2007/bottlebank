<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$username = htmlspecialchars($_SESSION['username'] ?? 'User');

// ======================
// Helper function to count entries per user
// ======================
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

// ======================
// Get counts for dashboard
// ======================
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
}
.app { max-width:1200px; margin:0 auto; padding:20px; }

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
.menu-wrap { position: relative; }
.menu-btn { background:#2d6a6a; color:white; border:none; padding:10px 16px; border-radius:8px; cursor:pointer; font-weight:600; }
.menu-panel {
    display:none;
    position:absolute;
    right:0;
    top:50px;
    background:white;
    border-radius:12px;
    box-shadow:0 4px 15px rgba(0,0,0,0.1);
    overflow:hidden;
}
.menu-panel a {
    display:block;
    padding:12px 20px;
    color:#333;
    text-decoration:none;
    transition:0.2s;
}
.menu-panel a:hover { background:#e0f2f1; color:#00796b; }

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
<div class="app">

    <!-- Topbar -->
    <div class="topbar">
        <div class="brand">
            <h1>BottleBank</h1>
        </div>
        <div class="menu-wrap">
            <span>Signed in as <strong><?= $username ?></strong></span>
            <button class="menu-btn" onclick="toggleMenu()">Menu</button>
            <div class="menu-panel" id="menuPanel">
                <a href="deposit.php">➕ Deposit</a>
                <a href="returns.php">🔁 Return</a>
                <a href="refund.php">💸 Refund</a>
                <a href="stock_log.php">📦 Stock Log</a>
                <a href="logout.php" style="color:#d64545">⎋ Logout</a>
            </div>
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
                    $details = htmlspecialchars($row['details']);
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

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="deposit.php"><button class="primary">➕ New Deposit</button></a>
        <a href="returns.php"><button class="ghost">🔁 Log Return</button></a>
        <a href="refund.php"><button class="ghost">💸 Issue Refund</button></a>
    </div>

    <!-- Footer -->
    <div class="footer">© <?=date('Y')?> BottleBank — Built with care</div>
</div>

<script>
function toggleMenu(){
    const panel = document.getElementById('menuPanel');
    if(panel.style.display==='block'){ panel.style.display='none'; }
    else { panel.style.display='block'; }
}
document.addEventListener('click', function(e){
    const panel = document.getElementById('menuPanel');
    const btn = document.querySelector('.menu-btn');
    if(!panel) return;
    if(!panel.contains(e.target) && !btn.contains(e.target)) panel.style.display='none';
});
</script>
</body>
</html>
