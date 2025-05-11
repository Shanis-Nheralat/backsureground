<?php
// Secure user management page for Admins and Super Admins
session_start();

require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/csrf/csrf-functions.php';
require_once __DIR__ . '/../shared/auth/admin-auth.php';

require_auth();
require_role(['admin', 'superadmin']);
csrf_verify();

global $pdo;

$message = '';
$messageType = '';
$editingUser = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $name = trim($_POST['name'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($email) || empty($role)) {
        $message = "Username, email, and role are required.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
        $messageType = "error";
    } else {
        if ($action === 'add') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn()) {
                $message = "Username or email already exists.";
                $messageType = "error";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, name, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $email, $hashedPassword, $name, $role, $status]);
                $message = "User added successfully.";
                $messageType = "success";
            }
        } elseif ($action === 'edit' && isset($_POST['user_id'])) {
            $userId = (int) $_POST['user_id'];
            $updateFields = [$username, $email, $name, $role, $status];
            $sql = "UPDATE users SET username = ?, email = ?, name = ?, role = ?, status = ?";
            if (!empty($password)) {
                $sql .= ", password = ?";
                $updateFields[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= ", updated_at = NOW() WHERE id = ?";
            $updateFields[] = $userId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateFields);
            $message = "User updated successfully.";
            $messageType = "success";
        }
    }
}

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();

// Your HTML rendering logic should go here (table, forms, etc.)
// Example: echo $message, and loop $users to display rows
?>
