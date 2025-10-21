<?php
/**
 * Authentication API Endpoint
 * Handles login, token generation, and user authentication for API access
 * URL: /api/auth.php
 */

require_once 'helpers.php';

setApiHeaders();

$method = getRequestMethod();
$endpoint = 'auth';

switch ($method) {
    case 'POST':
        handleLogin();
        break;
    case 'GET':
        handleTokenValidation();
        break;
    case 'DELETE':
        handleLogout();
        break;
    default:
        errorResponse('Method not allowed', 405);
}

/**
 * Handle user login and token generation
 * POST /api/auth.php
 */
function handleLogin() {
    global $pdo;
    
    $data = getJsonInput();
    validateRequired($data, ['username', 'password']);
    
    $username = trim($data['username']);
    $password = $data['password'];
    
    try {
        // Find user (using 'approved' status to match your database schema)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'approved'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            logApiRequest('auth', 'POST', null, 'failed_login');
            errorResponse('Invalid credentials', 401);
        }
        
        // Generate API token
        $token = generateApiToken($user['id']);
        
        // Note: Removed last_login update as column doesn't exist in current schema
        
        logApiRequest('auth', 'POST', $user['id'], 'success');
        
        successResponse([
            'token' => $token,
            'expires_in' => 86400, // 24 hours in seconds
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ], 'Login successful');
        
    } catch (Exception $e) {
        logApiRequest('auth', 'POST', null, 'error');
        errorResponse('Authentication failed', 500);
    }
}

/**
 * Validate current token and return user info
 * GET /api/auth.php
 */
function handleTokenValidation() {
    $user = authenticateApiRequest();
    
    logApiRequest('auth', 'GET', $user['id'], 'success');
    
    successResponse([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ],
        'token_valid' => true
    ], 'Token is valid');
}

/**
 * Logout and invalidate token
 * DELETE /api/auth.php
 */
function handleLogout() {
    global $pdo;
    
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = $matches[1];
        }
    }
    
    if (!$token) {
        errorResponse('No token provided', 400);
    }
    
    try {
        // Get user_id before deleting token
        $stmt = $pdo->prepare("SELECT user_id FROM api_sessions WHERE token = ?");
        $stmt->execute([$token]);
        $session = $stmt->fetch();
        
        // Delete the token
        $pdo->prepare("DELETE FROM api_sessions WHERE token = ?")
            ->execute([$token]);
        
        logApiRequest('auth', 'DELETE', $session['user_id'] ?? null, 'success');
        
        successResponse([], 'Logout successful');
        
    } catch (Exception $e) {
        logApiRequest('auth', 'DELETE', null, 'error');
        errorResponse('Logout failed', 500);
    }
}
?>