<?php
/**
 * Logout Page
 * 
 * Handles user logout and session termination.
 */

// Include required files
require_once 'shared/db.php';
require_once 'shared/auth/admin-auth.php';
require_once 'shared/csrf/csrf-functions.php';

// Verify CSRF token if this is a POST request (for logout form)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        die('Invalid request');
    }
}

// Perform logout action
logout();

// Note: The logout() function in admin-auth.php already handles:
// 1. Logging the logout action
// 2. Destroying the session
// 3. Redirecting to the login page