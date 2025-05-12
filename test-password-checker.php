<?php
require_once __DIR__ . '/shared/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = db_query_row("SELECT * FROM users WHERE username = ?", [$username]);

    if (!$user) {
        $result = "❌ User not found.";
    } elseif (password_verify($password, $user['password'])) {
        $result = "✅ Password matches!";
    } else {
        $result = "❌ Password mismatch.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Password Test</title>
</head>
<body>
  <h2>🔐 Test User Login</h2>
  <form method="post">
    <label>Username: <input type="text" name="username" required></label><br><br>
    <label>Password: <input type="password" name="password" required></label><br><br>
    <button type="submit">Test Login</button>
  </form>

  <?php if (isset($result)) echo "<h3>$result</h3>"; ?>
</body>
</html>
