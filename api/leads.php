<?php
/**
 * Leads API Endpoint
 * Provides full CRUD operations for lead management
 * URL: /api/leads.php
 */

require_once 'helpers.php';

setApiHeaders();

$method = getRequestMethod();
$path = parsePath();
$endpoint = 'leads';

// Extract lead ID from path if present
$lead_id = isset($path[2]) ? (int)$path[2] : null;

// Authenticate user
$user = authenticateApiRequest();

// Check permissions (admin and manager can access all leads, employee has limited access)
$allowed_roles = ['admin', 'manager', 'employee'];
requireRole($user, $allowed_roles);

switch ($method) {
    case 'GET':
        if ($lead_id) {
            getSingleLead($lead_id, $user);
        } else {
            getAllLeads($user);
        }
        break;
    case 'POST':
        createLead($user);
        break;
    case 'PUT':
        if (!$lead_id) {
            errorResponse('Lead ID required for update', 400);
        }
        updateLead($lead_id, $user);
        break;
    case 'DELETE':
        if (!$lead_id) {
            errorResponse('Lead ID required for deletion', 400);
        }
        deleteLead($lead_id, $user);
        break;
    default:
        errorResponse('Method not allowed', 405);
}

/**
 * Get all leads with optional filtering
 * GET /api/leads.php?status=new&limit=50&offset=0
 */
function getAllLeads($user) {
    global $pdo;
    $endpoint = 'leads';
    
    // Build query based on user role and filters
    $where_conditions = [];
    $params = [];
    
    // Role-based filtering
    if ($user['role'] === 'employee') {
        $where_conditions[] = "assigned_to = ?";
        $params[] = $user['id'];
    }
    
    // Status filtering
    if (isset($_GET['status']) && in_array($_GET['status'], ['new', 'contacted', 'qualified', 'lost'])) {
        $where_conditions[] = "status = ?";
        $params[] = $_GET['status'];
    }
    
    // Search filtering
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where_conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_term = '%' . $_GET['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Build WHERE clause
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Pagination
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    try {
        // Get total count
        $count_query = "SELECT COUNT(*) FROM leads $where_clause";
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total_count = $stmt->fetchColumn();
        
        // Get leads
        $query = "SELECT l.*, u.username as assigned_to_name 
                  FROM leads l 
                  LEFT JOIN users u ON l.assigned_to = u.id 
                  $where_clause 
                  ORDER BY l.created_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $leads = $stmt->fetchAll();
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        
        successResponse([
            'leads' => sanitizeOutput($leads),
            'pagination' => [
                'total' => $total_count,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total_count
            ]
        ]);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch leads', 500);
    }
}

/**
 * Get a single lead by ID
 * GET /api/leads.php/123
 */
function getSingleLead($lead_id, $user) {
    global $pdo;
    $endpoint = 'leads';
    
    try {
        $query = "SELECT l.*, u.username as assigned_to_name 
                  FROM leads l 
                  LEFT JOIN users u ON l.assigned_to = u.id 
                  WHERE l.id = ?";
        
        $params = [$lead_id];
        
        // Role-based access control
        if ($user['role'] === 'employee') {
            $query .= " AND l.assigned_to = ?";
            $params[] = $user['id'];
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $lead = $stmt->fetch();
        
        if (!$lead) {
            errorResponse('Lead not found', 404);
        }
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        successResponse(['lead' => sanitizeOutput($lead)]);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch lead', 500);
    }
}

/**
 * Create a new lead
 * POST /api/leads.php
 */
function createLead($user) {
    global $pdo;
    $endpoint = 'leads';
    
    // Only admin and manager can create leads
    requireRole($user, ['admin', 'manager']);
    
    $data = getJsonInput();
    validateRequired($data, ['name', 'email', 'phone']);
    
    // Validate email format
    if (!validateEmail($data['email'])) {
        errorResponse('Invalid email format', 400);
    }
    
    // Check for duplicate email
    $stmt = $pdo->prepare("SELECT id FROM leads WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        errorResponse('Lead with this email already exists', 409);
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO leads (name, email, phone, company, status, score, source, notes, assigned_to, created_by, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['company'] ?? null,
            $data['status'] ?? 'new',
            $data['score'] ?? 0,
            $data['source'] ?? null,
            $data['notes'] ?? null,
            $data['assigned_to'] ?? null,
            $user['id']
        ]);
        
        $lead_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'POST', $user['id'], 'success');
        successResponse(['lead_id' => $lead_id], 'Lead created successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'POST', $user['id'], 'error');
        errorResponse('Failed to create lead', 500);
    }
}

/**
 * Update an existing lead
 * PUT /api/leads.php/123
 */
function updateLead($lead_id, $user) {
    global $pdo;
    $endpoint = 'leads';
    
    $data = getJsonInput();
    
    // Check if lead exists and user has permission
    $query = "SELECT * FROM leads WHERE id = ?";
    $params = [$lead_id];
    
    if ($user['role'] === 'employee') {
        $query .= " AND assigned_to = ?";
        $params[] = $user['id'];
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $existing_lead = $stmt->fetch();
    
    if (!$existing_lead) {
        errorResponse('Lead not found or access denied', 404);
    }
    
    // Validate email if provided
    if (isset($data['email']) && !validateEmail($data['email'])) {
        errorResponse('Invalid email format', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Build update query dynamically
        $update_fields = [];
        $update_params = [];
        
        $allowed_fields = ['name', 'email', 'phone', 'company', 'status', 'score', 'source', 'notes', 'assigned_to'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $update_params[] = $data[$field];
            }
        }
        
        if (!empty($update_fields)) {
            $update_params[] = $lead_id;
            
            $update_query = "UPDATE leads SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute($update_params);
        }
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'PUT', $user['id'], 'success');
        successResponse([], 'Lead updated successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'PUT', $user['id'], 'error');
        errorResponse('Failed to update lead', 500);
    }
}

/**
 * Delete a lead
 * DELETE /api/leads.php/123
 */
function deleteLead($lead_id, $user) {
    global $pdo;
    $endpoint = 'leads';
    
    // Only admin can delete leads
    requireRole($user, ['admin']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
        $stmt->execute([$lead_id]);
        
        if ($stmt->rowCount() === 0) {
            errorResponse('Lead not found', 404);
        }
        
        logApiRequest($endpoint, 'DELETE', $user['id'], 'success');
        successResponse([], 'Lead deleted successfully');
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'DELETE', $user['id'], 'error');
        errorResponse('Failed to delete lead', 500);
    }
}
?>