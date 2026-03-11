<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php"); exit;
}
require 'config.php';

$msg = ""; $status = ""; $humidity_val = null;

// Admin can also log a reading
if ($_POST && isset($_POST['humidity'])) {
    $humidity_val = floatval($_POST['humidity']);
    if ($humidity_val < 20)      $status = "Dry";
    elseif ($humidity_val <= 60) $status = "Ideal";
    else                         $status = "Humid";

    $pdo->prepare("INSERT INTO humidity (humidity_percent, status) VALUES (?,?)")
        ->execute([$humidity_val, $status]);
    $hid = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO user_logs (user_id, humidity_id) VALUES (?,?)")
        ->execute([$_SESSION['user_id'], $hid]);
    $msg = "Reading recorded!";
}

// Delete a log record
if (($_GET['action'] ?? '') === 'delete_log' && isset($_GET['log_id'], $_GET['humidity_id'])) {
    $pdo->prepare("DELETE FROM user_logs WHERE log_id = ?")->execute([$_GET['log_id']]);
    $pdo->prepare("DELETE FROM humidity WHERE humidity_id = ?")->execute([$_GET['humidity_id']]);
    header("Location: admin_dashboard.php?deleted=1"); exit;
}
if (isset($_GET['deleted'])) $msg = "Record deleted successfully.";

// Fetch data
$users  = $pdo->query("SELECT user_id, username, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$recent = $pdo->query("
    SELECT ul.log_id, ul.humidity_id, u.username, h.humidity_percent, h.status, h.recorded_at
    FROM user_logs ul
    JOIN users u ON ul.user_id = u.user_id
    JOIN humidity h ON ul.humidity_id = h.humidity_id
    ORDER BY h.recorded_at DESC LIMIT 50")->fetchAll();
$counts = $pdo->query("SELECT status, COUNT(*) as total FROM humidity GROUP BY status")->fetchAll();
$stats  = array_column($counts, 'total', 'status');
$total  = array_sum(array_column($counts, 'total'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin – SuccuTrack</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
  <div class="nav-brand">🌵 SuccuTrack <span class="admin-badge">Admin</span></div>
  <div class="nav-links">
    <span>Hi, <?= htmlspecialchars($_SESSION['username']) ?></span>
    <a href="manage_users.php" class="btn btn-sm">Manage Users</a>
    <a href="logout.php" class="btn btn-sm">Logout</a>
  </div>
</nav>

<div class="container">

  <?php if ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card stat-dry">
      <div class="stat-num"><?= $stats['Dry'] ?? 0 ?></div>
      <div class="stat-label">🏜️ Dry Readings</div>
    </div>
    <div class="stat-card stat-ideal">
      <div class="stat-num"><?= $stats['Ideal'] ?? 0 ?></div>
      <div class="stat-label">✅ Ideal Readings</div>
    </div>
    <div class="stat-card stat-humid">
      <div class="stat-num"><?= $stats['Humid'] ?? 0 ?></div>
      <div class="stat-label">💧 Humid Readings</div>
    </div>
    <div class="stat-card stat-users">
      <div class="stat-num"><?= count($users) ?></div>
      <div class="stat-label">👤 Total Users</div>
    </div>
  </div>

  <!-- All Readings -->
  <div class="card">
    <h2>All Readings <span class="user-count"><?= $total ?></span></h2>
    <?php if (empty($recent)): ?>
      <p class="empty-msg">No readings yet — log one above to get started.</p>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th>User</th><th>Humidity %</th><th>Status</th><th>Recorded At</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['username']) ?></td>
          <td><?= $r['humidity_percent'] ?>%</td>
          <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
          <td><?= $r['recorded_at'] ?></td>
          <td>
            <a href="admin_dashboard.php?action=delete_log&log_id=<?= $r['log_id'] ?>&humidity_id=<?= $r['humidity_id'] ?>"
               class="btn btn-sm btn-danger"
               onclick="return confirm('Delete this record?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Registered Users -->
  <div class="card">
    <h2>Registered Users <span class="user-count"><?= count($users) ?></span></h2>
    <table class="data-table">
      <thead>
        <tr><th>Username</th><th>Email</th><th>Role</th><th>Joined</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
          <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <a href="delete_user.php?id=<?= $u['user_id'] ?>"
               class="btn btn-sm btn-danger"
               onclick="return confirm('Delete <?= htmlspecialchars($u['username']) ?>?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
