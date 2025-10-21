<?php
/**
 * API Helper Functions and Utilities
 * BizAutoPro RESTful API Layer
 */

require_once __DIR__ . '/../config.php';

/**
 * Set common API headers
 */
function setApiHeaders() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Send JSON response
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

/**
 * Send error response
 */
function errorResponse($message, $status_code = 400) {
    jsonResponse(['error' => true, 'message' => $message], $status_code);
}

/**
 * Send success response
 */
function successResponse($data = [], $message = 'Success') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

/**
 * Get JSON input from request body
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    
    // If no input, return empty array
    if (empty($input)) {
        return [];
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        errorResponse('Invalid JSON input: ' . json_last_error_msg(), 400);
    }
    
    return $data ?: [];
}

/**
 * Validate required fields
 */
function validateRequired($data, $required_fields) {
    $missing = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        errorResponse('Missing required fields: ' . implode(', ', $missing), 400);
    }
}

/**
 * Simple API token authentication
 */
function authenticateApiRequest() {
    $headers = getallheaders();
    $token = null;
    
    // Check Authorization header
    if (isset($headers['Authorization'])) {
        $auth_header = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            $token = $matches[1];
        }
    }
    
    // Check token parameter
    if (!$token && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    if (!$token) {
        errorResponse('Authentication token required', 401);
    }
    
    // Validate token and get user
    global $pdo;
    $stmt = $pdo->prepare("SELECT u.*, s.user_id FROM users u 
                          LEFT JOIN api_sessions s ON u.id = s.user_id 
                          WHERE s.token = ? AND s.expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        errorResponse('Invalid or expired token', 401);
    }
    
    return $user;
}

/**
 * Check user role permissions
 */
function requireRole($user, $allowed_roles) {
    if (!in_array($user['role'], $allowed_roles)) {
        errorResponse('Insufficient permissions', 403);
    }
}

/**
 * Generate API token
 */
function generateApiToken($user_id) {
    global $pdo;
    
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Clean old tokens
    $pdo->prepare("DELETE FROM api_sessions WHERE user_id = ? OR expires_at < NOW()")
        ->execute([$user_id]);
    
    // Insert new token
    $pdo->prepare("INSERT INTO api_sessions (user_id, token, expires_at) VALUES (?, ?, ?)")
        ->execute([$user_id, $token, $expires_at]);
    
    return $token;
}

/**
 * Sanitize output data
 */
function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Parse URL path for RESTful routing
 */
function parsePath() {
    $request_uri = $_SERVER['REQUEST_URI'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    
    // Remove script name from URI to get the path
    $path = str_replace(dirname($script_name), '', $request_uri);
    $path = trim($path, '/');
    
    // Remove query string
    if (($pos = strpos($path, '?')) !== false) {
        $path = substr($path, 0, $pos);
    }
    
    return explode('/', $path);
}

/**
 * Get request method
 */
function getRequestMethod() {
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Log API request
 */
function logApiRequest($endpoint, $method, $user_id = null, $status = 'success') {
    global $pdo;
    
    try {
        $pdo->prepare("INSERT INTO api_logs (endpoint, method, user_id, status, ip_address, user_agent, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())")
            ->execute([
                $endpoint,
                $method,
                $user_id,
                $status,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
    } catch (Exception $e) {
        // Silent fail for logging
    }
}

/**
 * Create database tables for API functionality if they don't exist
 */
function initializeApiTables() {
    global $pdo;
    
    // API sessions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) UNIQUE NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // API logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endpoint VARCHAR(255) NOT NULL,
        method VARCHAR(10) NOT NULL,
        user_id INT NULL,
        status VARCHAR(20) NOT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
}

// Initialize API tables
initializeApiTables();
?>