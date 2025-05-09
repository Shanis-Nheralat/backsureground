<?php
/**
 * Forgot Password Page
 * 
 * Handles password reset requests.
 */

// Include required files
require_once 'shared/db.php';
require_once 'shared/csrf/csrf-functions.php';

// Initialize variables
$success_message = '';
$error_message = '';
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !csrf_validate($_POST['csrf_token'])) {
        $error_message = 'Invalid form submission. Please try again.';
    } else {
        // Get form data
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } else {
            // Check if email exists in database
            $user = db_query_row("SELECT id, username, email, name FROM users WHERE email = ? AND status = 'active'", [$email]);
            
            if ($user) {
                // Generate password reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                db_insert('password_resets', [
                    'email' => $email,
                    'token' => $token,
                    'expires_at' => $expires_at
                ]);
                
                // Log the password reset request
                if (function_exists('log_action')) {
                    log_action('password_reset_request', "Password reset requested for email: $email");
                }
                
                // TODO: Send email with reset link
                // This will be implemented in Phase 2
                
                // Show success message
                $success_message = 'If an account exists with that email, a password reset link has been sent.';
                $email = ''; // Clear the form
            } else {
                // Don't reveal that the email doesn't exist (security against email enumeration)
                $success_message = 'If an account exists with that email, a password reset link has been sent.';
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
    <title>Forgot Password | Backsure Global Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .forgot-password-container {
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
    <div class="forgot-password-container">
        <div class="card">
            <div class="card-header">
                <img src="assets/img/logo.png" alt="Backsure Global Support" class="logo">
                <h4 class="mb-0">Forgot Password</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-primary">Back to Login</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                    <?php endif; ?>
                    
                    <p>Enter your email address and we'll send you a link to reset your password.</p>
                    
                    <form method="post" action="">
                        <?php echo csrf_field(); ?>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Send Reset Link</button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <a href="login.php">Back to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>