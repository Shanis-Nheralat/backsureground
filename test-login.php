<?php
require_once 'shared/db.php';
require_once 'shared/auth/admin-auth.php';

$username = 'shanisbsg';
$password = 'Admin@123';

$user = db_query_row("SELECT * FROM users WHERE username = ?", [$username]);

if (!$user) {
    die('User not found.');
}

if (password_verify($password, $user['password'])) {
    echo "✅ Password match. Login works.";
} else {
    echo "❌ Password mismatch.";
}
