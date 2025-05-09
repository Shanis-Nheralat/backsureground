<?php
/**
 * Login Page
 * 
 * Handles user authentication and login form.
 */

// Include required files
require_once 'shared/db.php';
require_once 'shared/auth/admin-auth.php';
require_once 'shared/csrf/csrf-functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect_to_dashboard();
    exit;
}

$error_message = '';
$username = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error_message = 'Invalid form submission. Please try again.';
    } else {
        // Get form data
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        // Validate input
        if (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password.';
        } else {
            // Attempt login
            if (login($username, $password)) {
                // Successful login
                
                // Redirect to intended page or dashboard
                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    header("Location: $redirect");
                } else {
                    redirect_to_dashboard();
                }
                exit;
            } else {
                $error_message = 'Invalid username or password.';
            }
        }
    }
}

// Regenerate CSRF token
csrf_regenerate();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Backsure Global Support</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
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
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <img src="assets/img/logo.png" alt="Backsure Global Support" class="logo">
                <h4 class="mb-0">Admin Login</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <?php echo csrf_field(); ?>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Log In</button>
                    </div>
                </form>
                
                <div class="mt-3 text-center">
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>