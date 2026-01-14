<?php
include 'includes/db_connect.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = (int)$_SESSION['user_id'];

// Pagination and filtering params
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10,20]) ? (int)$_GET['per_page'] : 10;
$filter_type = $_GET['filter_type'] ?? '';
$filter_value = $_GET['filter_value'] ?? '';

// Build WHERE clause and parameters safely
$where_sql = "WHERE user_id = ?";
$params = [$user_id];
$types = 'i';

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
} else {
    // shouldn't happen as $types starts with 'i'
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$res = $stmt->get_result();
$total = 0;
if ($res) {
    $r = $res->fetch_assoc();
    $total = (int)($r['cnt'] ?? 0);
}
$stmt->close();

$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Fetch page rows
$data_sql = "SELECT log_id, action_type, customer_name, bottle_type, quantity, amount, date_logged FROM stock_log " . $where_sql . " ORDER BY date_logged DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($data_sql);
// extend types and params for limit/offset
$types2 = $types . 'ii';
$params2 = $params;
$params2[] = $per_page;
$params2[] = $offset;
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Log</title>
    <link rel="stylesheet" href="asset/style.css">
    <style>
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px;border:1px solid #ddd}
    .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
    .kv{display:inline-block;padding:6px 10px;background:#0077cc;color:#fff;border-radius:6px;text-decoration:none}
    </style>
</head>
<body>
<div class="app">
  <div class="topbar">
    <div class="brand"><div class="logo">BB</div><div><h1>Stock Log</h1></div></div>
    <div class="menu-wrap"><a href="index.php" class="kv">← Back to Dashboard</a></div>
  </div>

  <div class="controls">
    <form method="get" style="display:flex;gap:8px;align-items:center">
      <label>Filter:</label>
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
            <button type="submit">Apply</button>
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
            <tr><th>ID</th><th>Action</th><th>Customer</th><th>Bottle</th><th>Quantity</th><th>Amount</th><th>Date</th></tr>
        </thead>
        <tbody>
        <?php if($result && $result->num_rows): while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['log_id']) ?></td>
                <td><?= htmlspecialchars($row['action_type']) ?></td>
                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                <td><?= htmlspecialchars($row['bottle_type']) ?></td>
                <td><?= htmlspecialchars($row['quantity']) ?></td>
                <td><?= htmlspecialchars($row['amount']) ?></td>
                <td><?= htmlspecialchars($row['date_logged']) ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="7">No records found.</td></tr>
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
</body>
</html>
