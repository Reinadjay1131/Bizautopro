<?php
/**
 * Users API Endpoint
 * Provides user management and role operations
 * URL: /api/users.php
 */

require_once 'helpers.php';

setApiHeaders();

$method = getRequestMethod();
$path = parsePath();
$user_id = isset($path[2]) ? (int)$path[2] : null;

// Authenticate user
$user = authenticateApiRequest();

switch ($method) {
    case 'GET':
        if ($user_id) {
            getSingleUser($user_id, $user);
        } else {
            getAllUsers($user);
        }
        break;
    case 'POST':
        createUser($user);
        break;
    case 'PUT':
        if (!$user_id) {
            errorResponse('User ID required for update', 400);
        }
        updateUser($user_id, $user);
        break;
    case 'DELETE':
        if (!$user_id) {
            errorResponse('User ID required for deletion', 400);
        }
        deleteUser($user_id, $user);
        break;
    default:
        errorResponse('Method not allowed', 405);
}

/**
 * Get all users with role-based filtering
 * GET /api/users.php?status=pending&role=employee&limit=50
 */
function getAllUsers($user) {
    global $pdo;
    $endpoint = 'users';
    
    // Only admin and managers can view user lists
    requireRole($user, ['admin', 'manager']);
    
    // Build query with filters
    $where_conditions = [];
    $params = [];
    
    // Status filtering (using your database schema)
    $valid_statuses = ['approved', 'pending', 'rejected'];
    if (isset($_GET['status']) && in_array($_GET['status'], $valid_statuses)) {
        $where_conditions[] = "status = ?";
        $params[] = $_GET['status'];
    }
    
    // Role filtering
    $valid_roles = ['admin', 'manager', 'employee', 'inventory_manager'];
    if (isset($_GET['role']) && in_array($_GET['role'], $valid_roles)) {
        $where_conditions[] = "role = ?";
        $params[] = $_GET['role'];
    }
    
    // Search filtering
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
        $search_term = '%' . $_GET['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Pagination
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    try {
        // Get total count
        $count_query = "SELECT COUNT(*) FROM users $where_clause";
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total_count = $stmt->fetchColumn();
        
        // Get users (exclude password field)
        $query = "SELECT id, username, email, role, status, _created_at, last_login 
                  FROM users 
                  $where_clause 
                  ORDER BY _created_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        // Get role summary
        $role_summary = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll();
        $status_summary = $pdo->query("SELECT status, COUNT(*) as count FROM users GROUP BY status")->fetchAll();
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        
        successResponse([
            'users' => sanitizeOutput($users),
            'pagination' => [
                'total' => $total_count,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total_count
            ],
            'summaries' => [
                'by_role' => sanitizeOutput($role_summary),
                'by_status' => sanitizeOutput($status_summary)
            ]
        ]);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch users', 500);
    }
}

/**
 * Get a single user by ID
 * GET /api/users.php/123
 */
function getSingleUser($user_id, $user) {
    global $pdo;
    $endpoint = 'users';
    
    // Users can view their own profile, admin/manager can view others
    if ($user_id != $user['id']) {
        requireRole($user, ['admin', 'manager']);
    }
    
    try {
        $query = "SELECT id, username, email, role, status, _created_at, last_login, verification_token
                  FROM users 
                  WHERE id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        $target_user = $stmt->fetch();
        
        if (!$target_user) {
            errorResponse('User not found', 404);
        }
        
        // Get user activity stats (if admin/manager viewing)
        $activity_stats = [];
        if ($user['role'] === 'admin' || $user['role'] === 'manager') {
            $activity_stats = [
                'leads_created' => $pdo->prepare("SELECT COUNT(*) FROM leads WHERE created_by = ?"),
                'workflows_created' => $pdo->prepare("SELECT COUNT(*) FROM workflows WHERE created_by = ?"),
                'workflows_assigned' => $pdo->prepare("SELECT COUNT(*) FROM workflows WHERE assigned_to = ?"),
                'api_requests_24h' => $pdo->prepare("SELECT COUNT(*) FROM api_logs WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")
            ];
            
            foreach ($activity_stats as $key => $stmt) {
                $stmt->execute([$user_id]);
                $activity_stats[$key] = $stmt->fetchColumn();
            }
        }
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        
        $response_data = ['user' => sanitizeOutput($target_user)];
        if (!empty($activity_stats)) {
            $response_data['activity_stats'] = $activity_stats;
        }
        
        successResponse($response_data);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch user', 500);
    }
}

/**
 * Create a new user (admin only)
 * POST /api/users.php
 */
function createUser($user) {
    global $pdo;
    $endpoint = 'users';
    
    // Only admin can create users
    requireRole($user, ['admin']);
    
    $data = getJsonInput();
    validateRequired($data, ['username', 'email', 'password', 'role']);
    
    // Validate email format
    if (!validateEmail($data['email'])) {
        errorResponse('Invalid email format', 400);
    }
    
    // Validate password strength
    if (strlen($data['password']) < 8) {
        errorResponse('Password must be at least 8 characters', 400);
    }
    
    // Validate role
    $valid_roles = ['admin', 'manager', 'employee', 'inventory_manager'];
    if (!in_array($data['role'], $valid_roles)) {
        errorResponse('Invalid role', 400);
    }
    
        // Check for duplicate username/email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$data['username'], $data['email']]);
        if ($stmt->fetch()) {
            errorResponse('Username or email already exists', 409);
        }    try {
        $pdo->beginTransaction();
        
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status, _created_at) 
                              VALUES (?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $data['username'],
            $hashed_password,
            $data['email'],
            $data['role'],
            $data['status'] ?? 'approved'
        ]);
        
        $new_user_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'POST', $user['id'], 'success');
        successResponse(['user_id' => $new_user_id], 'User created successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'POST', $user['id'], 'error');
        errorResponse('Failed to create user', 500);
    }
}

/**
 * Update an existing user
 * PUT /api/users.php/123
 */
function updateUser($user_id, $user) {
    global $pdo;
    $endpoint = 'users';
    
    // Users can update their own profile, admin can update others
    if ($user_id != $user['id']) {
        requireRole($user, ['admin']);
    }
    
    $data = getJsonInput();
    
    // Check if target user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $existing_user = $stmt->fetch();
    
    if (!$existing_user) {
        errorResponse('User not found', 404);
    }
    
    // Validate email if provided
    if (isset($data['email']) && !validateEmail($data['email'])) {
        errorResponse('Invalid email format', 400);
    }
    
    // Validate password if provided
    if (isset($data['password']) && strlen($data['password']) < 8) {
        errorResponse('Password must be at least 8 characters', 400);
    }
    
    // Validate role if provided (admin only)
    if (isset($data['role'])) {
        requireRole($user, ['admin']);
        $valid_roles = ['admin', 'manager', 'employee', 'inventory_manager'];
        if (!in_array($data['role'], $valid_roles)) {
            errorResponse('Invalid role', 400);
        }
    }
    
        // Validate status if provided (admin only)
        if (isset($data['status'])) {
            requireRole($user, ['admin']);
            $valid_statuses = ['approved', 'pending', 'rejected'];
            if (!in_array($data['status'], $valid_statuses)) {
                errorResponse('Invalid status', 400);
            }
        }    try {
        $pdo->beginTransaction();
        
        // Build update query dynamically
        $update_fields = [];
        $update_params = [];
        
        // Fields that users can update about themselves
        $self_allowed_fields = ['email'];
        // Fields that only admin can update
        $admin_only_fields = ['username', 'role', 'status'];
        
        $allowed_fields = ($user['role'] === 'admin') ? 
                         array_merge($self_allowed_fields, $admin_only_fields) : 
                         $self_allowed_fields;
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $update_params[] = $data[$field];
            }
        }
        
        // Handle password separately
        if (isset($data['password'])) {
            $update_fields[] = "password = ?";
            $update_params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (!empty($update_fields)) {
            $update_params[] = $user_id;
            
            $update_query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute($update_params);
        }
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'PUT', $user['id'], 'success');
        successResponse([], 'User updated successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'PUT', $user['id'], 'error');
        errorResponse('Failed to update user', 500);
    }
}

/**
 * Delete a user (admin only)
 * DELETE /api/users.php/123
 */
function deleteUser($user_id, $user) {
    global $pdo;
    $endpoint = 'users';
    
    // Only admin can delete users
    requireRole($user, ['admin']);
    
    // Prevent self-deletion
    if ($user_id == $user['id']) {
        errorResponse('Cannot delete your own account', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $target_user = $stmt->fetch();
        
        if (!$target_user) {
            errorResponse('User not found', 404);
        }
        
        // Update related records to preserve data integrity
        // Set created_by and assigned_to fields to NULL instead of deleting
        $pdo->prepare("UPDATE leads SET created_by = NULL WHERE created_by = ?")
            ->execute([$user_id]);
        $pdo->prepare("UPDATE workflows SET created_by = NULL WHERE created_by = ?")
            ->execute([$user_id]);
        $pdo->prepare("UPDATE workflows SET assigned_to = NULL WHERE assigned_to = ?")
            ->execute([$user_id]);
        
        // Delete user sessions
        $pdo->prepare("DELETE FROM api_sessions WHERE user_id = ?")
            ->execute([$user_id]);
        
        // Delete the user
        $pdo->prepare("DELETE FROM users WHERE id = ?")
            ->execute([$user_id]);
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'DELETE', $user['id'], 'success');
        successResponse([], 'User deleted successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'DELETE', $user['id'], 'error');
        errorResponse('Failed to delete user', 500);
    }
}
?>