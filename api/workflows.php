<?php
/**
 * Workflows API Endpoint
 * Provides workflow management and status tracking
 * URL: /api/workflows.php
 */

require_once 'helpers.php';

setApiHeaders();

$method = getRequestMethod();
$path = parsePath();
$workflow_id = isset($path[2]) ? (int)$path[2] : null;

// Authenticate user
$user = authenticateApiRequest();

// All authenticated users can access workflows (role-based filtering applied in functions)
$allowed_roles = ['admin', 'manager', 'employee'];
requireRole($user, $allowed_roles);

switch ($method) {
    case 'GET':
        if ($workflow_id) {
            getSingleWorkflow($workflow_id, $user);
        } else {
            getAllWorkflows($user);
        }
        break;
    case 'POST':
        createWorkflow($user);
        break;
    case 'PUT':
        if (!$workflow_id) {
            errorResponse('Workflow ID required for update', 400);
        }
        updateWorkflow($workflow_id, $user);
        break;
    case 'DELETE':
        if (!$workflow_id) {
            errorResponse('Workflow ID required for deletion', 400);
        }
        deleteWorkflow($workflow_id, $user);
        break;
    default:
        errorResponse('Method not allowed', 405);
}

/**
 * Get all workflows with role-based filtering
 * GET /api/workflows.php?status=pending&assigned_to=123&limit=50
 */
function getAllWorkflows($user) {
    global $pdo;
    $endpoint = 'workflows';
    
    // Build query based on user role and filters
    $where_conditions = [];
    $params = [];
    
    // Role-based filtering
    if ($user['role'] === 'employee') {
        $where_conditions[] = "(w.assigned_to = ? OR w.created_by = ?)";
        $params[] = $user['id'];
        $params[] = $user['id'];
    }
    
    // Status filtering
    $valid_statuses = ['pending', 'approved', 'rejected', 'completed', 'cancelled'];
    if (isset($_GET['status']) && in_array($_GET['status'], $valid_statuses)) {
        $where_conditions[] = "w.status = ?";
        $params[] = $_GET['status'];
    }
    
    // Assigned to filtering
    if (isset($_GET['assigned_to']) && is_numeric($_GET['assigned_to'])) {
        $where_conditions[] = "w.assigned_to = ?";
        $params[] = (int)$_GET['assigned_to'];
    }
    
    // Created by filtering
    if (isset($_GET['created_by']) && is_numeric($_GET['created_by'])) {
        $where_conditions[] = "w.created_by = ?";
        $params[] = (int)$_GET['created_by'];
    }
    
    // Date range filtering
    if (isset($_GET['start_date'])) {
        $where_conditions[] = "DATE(w.created_at) >= ?";
        $params[] = $_GET['start_date'];
    }
    
    if (isset($_GET['end_date'])) {
        $where_conditions[] = "DATE(w.created_at) <= ?";
        $params[] = $_GET['end_date'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Pagination
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    try {
        // Get total count
        $count_query = "SELECT COUNT(*) FROM workflows w $where_clause";
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total_count = $stmt->fetchColumn();
        
        // Get workflows with user information
        $query = "SELECT w.*, 
                         u1.username as creator_name, 
                         u2.username as assignee_name,
                         u2.role as assignee_role
                  FROM workflows w
                  LEFT JOIN users u1 ON w.created_by = u1.id
                  LEFT JOIN users u2 ON w.assigned_to = u2.id
                  $where_clause 
                  ORDER BY w.created_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $workflows = $stmt->fetchAll();
        
        // Get status summary
        $summary_query = "SELECT status, COUNT(*) as count 
                         FROM workflows w 
                         " . ($user['role'] === 'employee' ? 
                            "WHERE (w.assigned_to = {$user['id']} OR w.created_by = {$user['id']})" : '') . "
                         GROUP BY status";
        
        $status_summary = $pdo->query($summary_query)->fetchAll();
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        
        successResponse([
            'workflows' => sanitizeOutput($workflows),
            'pagination' => [
                'total' => $total_count,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total_count
            ],
            'status_summary' => sanitizeOutput($status_summary)
        ]);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch workflows', 500);
    }
}

/**
 * Get a single workflow with history
 * GET /api/workflows.php/123
 */
function getSingleWorkflow($workflow_id, $user) {
    global $pdo;
    $endpoint = 'workflows';
    
    try {
        $query = "SELECT w.*, 
                         u1.username as creator_name, 
                         u2.username as assignee_name,
                         u2.role as assignee_role
                  FROM workflows w
                  LEFT JOIN users u1 ON w.created_by = u1.id
                  LEFT JOIN users u2 ON w.assigned_to = u2.id
                  WHERE w.id = ?";
        
        $params = [$workflow_id];
        
        // Role-based access control
        if ($user['role'] === 'employee') {
            $query .= " AND (w.assigned_to = ? OR w.created_by = ?)";
            $params[] = $user['id'];
            $params[] = $user['id'];
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $workflow = $stmt->fetch();
        
        if (!$workflow) {
            errorResponse('Workflow not found or access denied', 404);
        }
        
        // Get workflow history
        $history_query = "SELECT h.*, u.username 
                         FROM workflow_history h
                         LEFT JOIN users u ON h.user_id = u.id
                         WHERE h.workflow_id = ?
                         ORDER BY h.timestamp DESC";
        
        $stmt = $pdo->prepare($history_query);
        $stmt->execute([$workflow_id]);
        $history = $stmt->fetchAll();
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        
        successResponse([
            'workflow' => sanitizeOutput($workflow),
            'history' => sanitizeOutput($history)
        ]);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch workflow', 500);
    }
}

/**
 * Create a new workflow
 * POST /api/workflows.php
 */
function createWorkflow($user) {
    global $pdo;
    $endpoint = 'workflows';
    
    $data = getJsonInput();
    validateRequired($data, ['title', 'description']);
    
    // Validate assigned_to user if provided
    if (isset($data['assigned_to'])) {
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$data['assigned_to']]);
        if (!$stmt->fetch()) {
            errorResponse('Invalid assigned user', 400);
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO workflows (title, description, assigned_to, created_by, status, priority, due_date, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['assigned_to'] ?? null,
            $user['id'],
            $data['status'] ?? 'pending',
            $data['priority'] ?? 'medium',
            $data['due_date'] ?? null
        ]);
        
        $workflow_id = $pdo->lastInsertId();
        
        // Log workflow creation
        $pdo->prepare("INSERT INTO workflow_history (workflow_id, user_id, action, timestamp) 
                      VALUES (?, ?, 'created', NOW())")
            ->execute([$workflow_id, $user['id']]);
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'POST', $user['id'], 'success');
        successResponse(['workflow_id' => $workflow_id], 'Workflow created successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'POST', $user['id'], 'error');
        errorResponse('Failed to create workflow', 500);
    }
}

/**
 * Update workflow status or assignment
 * PUT /api/workflows.php/123
 */
function updateWorkflow($workflow_id, $user) {
    global $pdo;
    $endpoint = 'workflows';
    
    $data = getJsonInput();
    
    // Check if workflow exists and user has permission
    $query = "SELECT * FROM workflows WHERE id = ?";
    $params = [$workflow_id];
    
    if ($user['role'] === 'employee') {
        $query .= " AND (assigned_to = ? OR created_by = ?)";
        $params[] = $user['id'];
        $params[] = $user['id'];
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $existing_workflow = $stmt->fetch();
    
    if (!$existing_workflow) {
        errorResponse('Workflow not found or access denied', 404);
    }
    
    // Validate permissions for specific actions
    if (isset($data['status'])) {
        $new_status = $data['status'];
        $allowed = false;
        
        if ($user['role'] === 'admin') {
            $allowed = true;
        } elseif ($existing_workflow['assigned_to'] == $user['id'] && 
                 in_array($new_status, ['completed', 'rejected'])) {
            $allowed = true;
        } elseif ($existing_workflow['created_by'] == $user['id'] && 
                 $new_status === 'cancelled') {
            $allowed = true;
        }
        
        if (!$allowed) {
            errorResponse('Insufficient permissions for this status change', 403);
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Build update query dynamically
        $update_fields = [];
        $update_params = [];
        $history_actions = [];
        
        $allowed_fields = ['title', 'description', 'status', 'priority', 'due_date', 'assigned_to'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $update_params[] = $data[$field];
                
                // Track significant changes for history
                if ($field === 'status' && $data[$field] !== $existing_workflow[$field]) {
                    $history_actions[] = $data[$field];
                }
                if ($field === 'assigned_to' && $data[$field] !== $existing_workflow[$field]) {
                    $history_actions[] = 'reassigned';
                }
            }
        }
        
        if (!empty($update_fields)) {
            $update_fields[] = "updated_at = NOW()";
            $update_params[] = $workflow_id;
            
            $update_query = "UPDATE workflows SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute($update_params);
            
            // Log history for significant changes
            foreach ($history_actions as $action) {
                $pdo->prepare("INSERT INTO workflow_history (workflow_id, user_id, action, timestamp) 
                              VALUES (?, ?, ?, NOW())")
                    ->execute([$workflow_id, $user['id'], $action]);
            }
        }
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'PUT', $user['id'], 'success');
        successResponse([], 'Workflow updated successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'PUT', $user['id'], 'error');
        errorResponse('Failed to update workflow', 500);
    }
}

/**
 * Delete a workflow (admin only)
 * DELETE /api/workflows.php/123
 */
function deleteWorkflow($workflow_id, $user) {
    global $pdo;
    $endpoint = 'workflows';
    
    // Only admin can delete workflows
    requireRole($user, ['admin']);
    
    try {
        $pdo->beginTransaction();
        
        // Delete workflow history first
        $pdo->prepare("DELETE FROM workflow_history WHERE workflow_id = ?")
            ->execute([$workflow_id]);
        
        // Delete the workflow
        $stmt = $pdo->prepare("DELETE FROM workflows WHERE id = ?");
        $stmt->execute([$workflow_id]);
        
        if ($stmt->rowCount() === 0) {
            errorResponse('Workflow not found', 404);
        }
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'DELETE', $user['id'], 'success');
        successResponse([], 'Workflow deleted successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'DELETE', $user['id'], 'error');
        errorResponse('Failed to delete workflow', 500);
    }
}
?>