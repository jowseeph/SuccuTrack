<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: index.php"); exit; }
require 'config.php';
if (isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$_GET['id']]);
}
header("Location: admin_dashboard.php"); exit;