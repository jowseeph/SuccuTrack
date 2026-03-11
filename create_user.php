<?php
session_start();
$error = $success = "";
if ($_POST) {
    require 'config.php';
    $u = trim($_POST['username']);
    $e = trim($_POST['email']);
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $r = $_POST['role'] ?? 'user';
    try {
        $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?,?,?,?)")
            ->execute([$u, $e, $p, $r]);
        $success = "Account created! <a href='index.php'>Login here</a>";
    } catch (PDOException $ex) {
        $error = "Username or email already exists.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register – SuccuTrack</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<div class="auth-card">
  <div class="brand">
    <span class="leaf-icon">🌵</span>
    <h1>Create Account</h1>
    <p>Join SuccuTrack today</p>
  </div>
  <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
  <form method="POST">
    <div class="form-group">
      <label>Username</label>
      <input type="text" name="username" required placeholder="Choose a username">
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required placeholder="your@email.com">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="Create a password">
    </div>
    <div class="form-group">
      <label>Role</label>
      <select name="role">
        <option value="user">User</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-full">Register</button>
  </form>
  <p class="auth-footer">Already have an account? <a href="index.php">Login</a></p>
</div>
</body>
</html>
