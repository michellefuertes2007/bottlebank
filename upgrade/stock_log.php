<?php
include 'includes/db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = (int)$_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Pagination and filtering params
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10,20]) ? (int)$_GET['per_page'] : 10;
$filter_type = $_GET['filter_type'] ?? '';
$filter_value = $_GET['filter_value'] ?? '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause and parameters safely
$where_sql = "WHERE 1=1";
$params = [];
$types = '';

// For regular users, show only their logs; for admins, show all
if (!$is_admin) {
  $where_sql .= " AND user_id = ?";
  $types .= 'i';
  $params[] = $user_id;
} elseif ($search_query) {
  // Search across multiple columns: customer name, bottle type, action type, and numeric fields
  $where_sql .= " AND (customer_name LIKE ? OR bottle_type LIKE ? OR action_type LIKE ? OR CAST(quantity AS CHAR) LIKE ? OR CAST(amount AS CHAR) LIKE ?)";
  $types .= 'sssss';
  $search_param = '%' . $search_query . '%';
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
}

if ($filter_type === 'day' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_value)) {
    $where_sql .= " AND DATE(date_logged) = ?";
    $types .= 's';
    $params[] = $filter_value;
} elseif ($filter_type === 'month' && preg_match('/^\d{4}-\d{2}$/', $filter_value)) {
    $where_sql .= " AND DATE_FORMAT(date_logged, '%Y-%m') = ?";
    $types .= 's';
    $params[] = $filter_value;
} elseif ($filter_type === 'year' && preg_match('/^\d{4}$/', $filter_value)) {
    $where_sql .= " AND YEAR(date_logged) = ?";
    $types .= 's';
    $params[] = $filter_value;
}

// Get total count
$count_sql = "SELECT COUNT(*) AS cnt FROM stock_log " . $where_sql;
$stmt = $conn->prepare($count_sql);
// bind dynamically
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$total = 0;
if ($res) {
    $r = $res->fetch_assoc();
    $total = (int)($r['cnt'] ?? 0);
}
$stmt->close();

// Calc pages
$total_pages = ($total > 0) ? ceil($total / $per_page) : 1;
if ($page > $total_pages) $page = $total_pages;

// Get records with pagination
$offset = ($page - 1) * $per_page;
$data_sql = "SELECT log_id, action_type, customer_name, bottle_type, quantity, amount, with_case, case_quantity, date_logged FROM stock_log " . $where_sql . " ORDER BY date_logged DESC LIMIT ?, ?";
$stmt = $conn->prepare($data_sql);

// Bind with offset and limit
$types_with_limit = $types . 'ii';
$offset_param = $offset;
$limit_param = $per_page;
$all_params = array_merge($params, [$offset_param, $limit_param]);

$stmt->bind_param($types_with_limit, ...$all_params);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Stock Log • BottleBank</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="asset/style.css">
  <style>
    .topbar .logo { width:40px; height:40px; background:#26a69a; color:white; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:700; margin-right:10px; }
    .kv { color:#26a69a; text-decoration:none; }
    .kv:hover { text-decoration:underline; }
    .controls { background:white; padding:15px; border-radius:6px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    .controls form { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .controls select, .controls input { padding:8px; border:1px solid #ddd; border-radius:6px; font-size:13px; }
    .controls button { padding:8px 15px; background:#26a69a; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600; }
    .controls button:hover { background:#2e7d7d; }
    table { width:100%; border-collapse:collapse; background:white; border-radius:6px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    th { background:#e0f2f1; color:#00796b; padding:12px; text-align:left; font-weight:600; }
    td { padding:12px; border-bottom:1px solid #eee; }
    tr:hover { background:#f5f5f5; }
    .toggle-sidebar { background:none; border:none; font-size:18px; cursor:pointer; color:#2d6a6a; font-weight:600; display:none; transition:0.3s; }
    @media (max-width:768px) { .toggle-sidebar { display:block; } }
  </style>
</head>
<body>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar">
    <div class="brand">
        <h1>BB</h1>
    </div>
    <nav class="sidebar-nav">
        <a href="index.php">Dashboard</a>
        <a href="deposit.php">Deposit</a>
        <a href="returns.php">Returns</a>
        <a href="refund.php">Refund</a>
        <a href="stock_log.php" class="active">Stock Log</a>
        <?php if($is_admin): ?>
        <a href="admin/admin_panel.php">Admin Panel</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</div>

<div class="app">
  <div class="topbar">
    <div class="brand"><button class="toggle-sidebar" onclick="toggleSidebar()">Menu</button><div class="logo">BB</div><div><h1>Stock Log</h1><p class="kv">View all transaction logs</p></div></div>
    <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>

  <div class="controls">
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <?php if($is_admin): ?>
      <input type="text" name="search" placeholder="Search by Customer, Action, Bottle, Quantity, or Amount..." value="<?= htmlspecialchars($search_query) ?>" style="flex:1;min-width:250px;">
      <?php endif; ?>

      <label>Period:</label>
      <select name="filter_type" id="filter_type">
                <option value="" <?php if($filter_type==='') echo 'selected'; ?>>All</option>
                <option value="day" <?php if($filter_type==='day') echo 'selected'; ?>>Day</option>
                <option value="month" <?php if($filter_type==='month') echo 'selected'; ?>>Month</option>
                <option value="year" <?php if($filter_type==='year') echo 'selected'; ?>>Year</option>
            </select>
            <span id="filter_inputs">
                <input type="date" name="filter_value_day" id="filter_value_day" style="display:none">
                <input type="month" name="filter_value_month" id="filter_value_month" style="display:none">
                <input type="number" name="filter_value_year" id="filter_value_year" min="1900" max="2100" placeholder="YYYY" style="display:none;width:90px">
            </span>
            <input type="hidden" name="filter_value" id="filter_value">
            
            <label>Per page</label>
            <select name="per_page">
                <option value="10" <?php if($per_page==10) echo 'selected'; ?>>10</option>
                <option value="20" <?php if($per_page==20) echo 'selected'; ?>>20</option>
            </select>
            <button type="submit">Search</button>
            <a href="stock_log.php"><button type="button">Clear</button></a>
        </form>
        <script>
            // show appropriate input for selected filter type and populate hidden value
            const ft = document.getElementById('filter_type');
            const day = document.getElementById('filter_value_day');
            const month = document.getElementById('filter_value_month');
            const year = document.getElementById('filter_value_year');
            const hidden = document.getElementById('filter_value');

            function updateVisibility(){
                day.style.display = month.style.display = year.style.display = 'none';
                if(ft.value==='day'){
                    day.style.display='inline-block';
                    day.value = '<?php echo ($filter_type==='day' ? $filter_value : ''); ?>';
                    hidden.value = day.value;
                } else if(ft.value==='month'){
                    month.style.display='inline-block';
                    month.value = '<?php echo ($filter_type==='month' ? $filter_value : ''); ?>';
                    hidden.value = month.value;
                } else if(ft.value==='year'){
                    year.style.display='inline-block';
                    year.value = '<?php echo ($filter_type==='year' ? $filter_value : ''); ?>';
                    hidden.value = year.value;
                } else {
                    hidden.value = '';
                }
            }
            ft.addEventListener('change', updateVisibility);
            day.addEventListener('change', ()=>hidden.value = day.value);
            month.addEventListener('change', ()=>hidden.value = month.value);
            year.addEventListener('change', ()=>hidden.value = year.value);
            updateVisibility();
        </script>
    </div>

    <table>
        <thead>
            <tr><th>ID</th><th>Action</th><th>Customer</th><th>Bottle</th><th>Qty</th><th>With Case</th><th>Cases</th><th>Amount</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if($result && $result->num_rows): while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['log_id']) ?></td>
                <td><?= htmlspecialchars($row['action_type']) ?></td>
                <td><?= htmlspecialchars(!empty($row['customer_name']) ? $row['customer_name'] : 'N/A') ?></td>
                <td><?= htmlspecialchars(!empty($row['bottle_type']) ? $row['bottle_type'] : 'N/A') ?></td>
                <td><?= htmlspecialchars(!empty($row['quantity']) ? $row['quantity'] : 'N/A') ?></td>
                <td><?= ($row['with_case'] ? '✓ Yes' : '✗ No') ?></td>
                <td><?= htmlspecialchars(!empty($row['case_quantity']) ? $row['case_quantity'] : '0') ?></td>
                <td><?= htmlspecialchars(!empty($row['amount']) ? '₱' . $row['amount'] : 'N/A') ?></td>
                <td><?= htmlspecialchars($row['date_logged']) ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="9">No records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
        <?php if($page>1): ?>
            <a class="kv" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1])); ?>">← Prev</a>
        <?php endif; ?>
        <span>Page <?= $page ?> of <?= $total_pages ?></span>
        <?php if($page<$total_pages): ?>
            <a class="kv" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1])); ?>">Next →</a>
        <?php endif; ?>
    </div>

</div>

<script>
function toggleSidebar(){
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  sidebar.classList.toggle('active');
  overlay.classList.toggle('active');
}

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
