<?php
/**
 * Reset Password Page
 * 
 * Handles password reset form and processing.
 */

// Include required files
require_once 'shared/db.php';
require_once 'shared/csrf/csrf-functions.php';

// Initialize variables
$success_message = '';
$error_message = '';
$token = '';
$email = '';
$valid_token = false;

// Get token from URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token
    $reset_request = db_query_row(
        "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1", 
        [$token]
    );
    
    if ($reset_request) {
        $valid_token = true;
        $email = $reset_request['email'];
    } else {
        $error_message = 'Invalid or expired reset token. Please request a new password reset link.';
    }
} else {
    $error_message = 'Reset token is missing. Please request a new password reset link.';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !csrf_validate($_POST['csrf_token'])) {
        $error_message = 'Invalid form submission. Please try again.';
    } else {
        // Get form data
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validate passwords
        if (empty($password) || strlen($password) < 8) {
            $error_message = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } else {
            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user's password
            $update_result = db_update('users', 
                ['password' => $hashed_password], 
                'email = ? AND status = ?', 
                [$email, 'active']
            );
            
            if ($update_result) {
                // Delete used token
                db_delete('password_resets', 'email = ? AND token = ?', [$email, $token]);
                
                // Log password reset
                if (function_exists('log_action')) {
                    log_action('password_reset', "Password reset successful for email: $email");
                }
                
                $success_message = 'Your password has been reset successfully. You can now log in with your new password.';
            } else {
                $error_message = 'Failed to update password. Please try again or contact support.';
            }
        }
    }
    
    // Regenerate CSRF token
    csrf_regenerate();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Backsure Global Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-password-container {
            max-width: 400px;
            width: 100%;
            padding: 15px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
            text-align: center;
            padding: 1.5rem 1rem;
        }
        .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="reset-password-container">
        <div class="card">
            <div class="card-header">
                <img src="assets/img/logo.png" alt="Backsure Global Support" class="logo">
                <h4 class="mb-0">Reset Password</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Back to Login</a>
                    </div>
                <?php elseif (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <div class="text-center mt-3">
                        <a href="forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
                    </div>
                <?php elseif ($valid_token): ?>
                    <form method="post" action="">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>