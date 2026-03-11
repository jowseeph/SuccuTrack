<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
require 'config.php';

$msg = ""; $status = ""; $humidity = null;

if ($_POST && isset($_POST['humidity'])) {
    $humidity = floatval($_POST['humidity']);
    if ($humidity < 20)      $status = "Dry";
    elseif ($humidity <= 60) $status = "Ideal";
    else                     $status = "Humid";

    $pdo->prepare("INSERT INTO humidity (humidity_percent, status) VALUES (?,?)")
        ->execute([$humidity, $status]);
    $hid = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO user_logs (user_id, humidity_id) VALUES (?,?)")
        ->execute([$_SESSION['user_id'], $hid]);
    $msg = "Reading recorded successfully!";
}

// Fetch recent logs
$logs = $pdo->prepare("SELECT h.humidity_percent, h.status, h.recorded_at
    FROM user_logs ul JOIN humidity h ON ul.humidity_id = h.humidity_id
    WHERE ul.user_id = ? ORDER BY h.recorded_at DESC LIMIT 10");
$logs->execute([$_SESSION['user_id']]);
$records = $logs->fetchAll();

// Latest reading for the "last reading" card
$latest = $records[0] ?? null;

// Trend: compare last two readings
$trend = null;
if (count($records) >= 2) {
    $diff = $records[0]['humidity_percent'] - $records[1]['humidity_percent'];
    if ($diff > 1)       $trend = 'up';
    elseif ($diff < -1)  $trend = 'down';
    else                 $trend = 'stable';
}

// Care tips per status
$tips = [
    'Dry' => [
        ['icon' => '💧', 'title' => 'Water Immediately',       'desc' => 'Give a thorough soak, letting water drain fully from the pot.'],
        ['icon' => '☀️', 'title' => 'Check Sun Exposure',      'desc' => 'Too much direct sun speeds up soil drying. Consider partial shade.'],
        ['icon' => '🪴', 'title' => 'Inspect the Soil',        'desc' => 'Bone-dry soil pulls away from pot edges — a sure sign it needs water.'],
        ['icon' => '🌡️', 'title' => 'Monitor Temperature',     'desc' => 'Heat above 35°C accelerates moisture loss. Move indoors if needed.'],
    ],
    'Ideal' => [
        ['icon' => '✅', 'title' => 'Keep It Up',               'desc' => 'Conditions are perfect. Maintain your current watering schedule.'],
        ['icon' => '🌿', 'title' => 'Fertilise Lightly',        'desc' => 'During growing season, a diluted succulent fertiliser boosts growth.'],
        ['icon' => '🔄', 'title' => 'Rotate the Pot',           'desc' => 'Turn the pot a quarter every week for even, balanced sun exposure.'],
        ['icon' => '👁️', 'title' => 'Watch for Pests',         'desc' => 'Healthy moisture levels can attract mealybugs. Check leaves regularly.'],
    ],
    'Humid' => [
        ['icon' => '🚫', 'title' => 'Pause Watering',           'desc' => 'Do not water until the top 2 cm of soil is completely dry.'],
        ['icon' => '💨', 'title' => 'Improve Air Circulation',  'desc' => 'Move the plant to a well-ventilated spot to reduce excess moisture.'],
        ['icon' => '🪨', 'title' => 'Check Drainage',           'desc' => 'Ensure your pot has drainage holes. Standing water causes root rot.'],
        ['icon' => '🌫️', 'title' => 'Avoid Misting',           'desc' => 'Succulents do not need misting — it raises humidity further.'],
    ],
];

$current_tips  = $status ? $tips[$status] : ($latest ? $tips[$latest['status']] : null);
$display_status = $status ?: ($latest ? $latest['status'] : null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – SuccuTrack</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
  <div class="nav-brand">🌵 SuccuTrack</div>
  <div class="nav-links">
    <span>Hi, <?= htmlspecialchars($_SESSION['username']) ?></span>
    <a href="logout.php" class="btn btn-sm">Logout</a>
  </div>
</nav>

<div class="container">

  <!-- Top Row: Input + Last Reading -->
  <div class="two-col">

    <!-- Input Card -->
    <div class="card input-card">
      <h2>Log Humidity</h2>
      <p class="subtitle">Enter current humidity % for your succulent</p>
      <?php if ($msg): ?>
        <div class="alert alert-success"><?= $msg ?></div>
      <?php endif; ?>
      <form method="POST" class="inline-form">
        <input type="number" name="humidity" min="0" max="100" step="0.01"
               placeholder="e.g. 45.00" required class="big-input"
               value="<?= $humidity !== null ? $humidity : '' ?>">
        <button type="submit" class="btn btn-primary">Check</button>
      </form>

      <?php if ($status): ?>
      <div class="status-result status-<?= strtolower($status) ?>">
        <div class="status-icon">
          <?= $status === 'Dry' ? '🏜️' : ($status === 'Ideal' ? '✅' : '💧') ?>
        </div>
        <div>
          <div class="status-label"><?= $status ?></div>
          <div class="status-detail">
            <?= $status === 'Dry'   ? 'Below 20% — Water your succulent soon!'
              : ($status === 'Ideal' ? '20–60% — Perfect conditions!'
              : 'Above 60% — Reduce watering frequency.') ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Legend -->
      <div class="legend-row" style="margin-top:20px; margin-bottom:0;">
        <div class="legend-item legend-dry">🏜️ Dry &lt;20%</div>
        <div class="legend-item legend-ideal">✅ Ideal 20–60%</div>
        <div class="legend-item legend-humid">💧 Humid &gt;60%</div>
      </div>
    </div>

    <!-- Last Reading Card -->
    <div class="card last-reading-card <?= $display_status ? 'last-' . strtolower($display_status) : '' ?>">
      <h2>Last Reading</h2>
      <?php if ($latest): ?>
        <div class="last-reading-value">
          <?= $latest['humidity_percent'] ?><span class="last-unit">%</span>
        </div>
        <span class="badge badge-<?= strtolower($latest['status']) ?> badge-lg">
          <?= $latest['status'] ?>
        </span>
        <?php if ($trend): ?>
        <div class="trend trend-<?= $trend ?>">
          <?= $trend === 'up' ? '↑ Rising' : ($trend === 'down' ? '↓ Falling' : '→ Stable') ?>
          <span>since last reading</span>
        </div>
        <?php endif; ?>
        <div class="last-time">🕐 <?= date('M d, Y  H:i', strtotime($latest['recorded_at'])) ?></div>
      <?php else: ?>
        <p class="empty-msg" style="padding:32px 0;">No readings yet.<br>Log your first one!</p>
      <?php endif; ?>
    </div>

  </div>

  <!-- Care Tips -->
  <?php if ($current_tips): ?>
  <div class="card care-card">
    <h2>
      <?= $display_status === 'Dry'   ? '🏜️ Care Tips — Your Succulent is Dry'
        : ($display_status === 'Ideal' ? '✅ Care Tips — Conditions are Ideal'
        : '💧 Care Tips — Your Succulent is Humid') ?>
    </h2>
    <p class="subtitle">Here's how to keep your plant healthy right now</p>
    <div class="tips-grid">
      <?php foreach ($current_tips as $tip): ?>
      <div class="tip-card tip-<?= strtolower($display_status) ?>">
        <div class="tip-icon"><?= $tip['icon'] ?></div>
        <div class="tip-body">
          <div class="tip-title"><?= $tip['title'] ?></div>
          <div class="tip-desc"><?= $tip['desc'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- History Table -->
  <div class="card">
    <h2>Recent Readings</h2>
    <?php if (empty($records)): ?>
      <p class="empty-msg">No readings yet. Add your first one above!</p>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Humidity %</th><th>Status</th><th>Trend</th><th>Recorded At</th></tr>
      </thead>
      <tbody>
        <?php foreach ($records as $i => $r):
          $row_trend = '';
          if ($i < count($records) - 1) {
              $d = $r['humidity_percent'] - $records[$i+1]['humidity_percent'];
              $row_trend = $d > 1 ? '<span class="trend-up-sm">↑</span>'
                         : ($d < -1 ? '<span class="trend-down-sm">↓</span>'
                         : '<span class="trend-stable-sm">→</span>');
          }
        ?>
        <tr>
          <td style="color:var(--text-3)"><?= $i + 1 ?></td>
          <td><strong><?= $r['humidity_percent'] ?>%</strong></td>
          <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
          <td><?= $row_trend ?: '—' ?></td>
          <td><?= date('M d, Y  H:i', strtotime($r['recorded_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
