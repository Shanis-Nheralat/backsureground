<?php
/**
 * CSRF Protection Functions
 * 
 * Functions for generating and validating CSRF tokens to prevent cross-site request forgery.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a CSRF token and store it in the session
 * 
 * @return string The generated CSRF token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Create a CSRF token field to include in forms
 * 
 * @return string HTML input field with CSRF token
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Validate a submitted CSRF token
 * 
 * @param string $token The token to validate
 * @return bool True if token is valid, false otherwise
 */
function csrf_validate($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token in a POST request
 * Will terminate execution if token is invalid
 */
function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !csrf_validate($_POST['csrf_token'])) {
            // Log this as a potential CSRF attack
            if (function_exists('log_action')) {
                log_action('csrf_failure', 'Invalid CSRF token');
            }
            
            // Terminate the request
            http_response_code(403);
            die('Invalid request. Please try again.');
        }
    }
}

/**
 * Regenerate CSRF token
 * Useful after processing a form to prevent token reuse
 */
function csrf_regenerate() {
    unset($_SESSION['csrf_token']);
    return csrf_token();
}
