<?php
/**
 * Inventory API Endpoint
 * Provides full CRUD operations for inventory management
 * URL: /api/inventory.php
 */

require_once 'helpers.php';

setApiHeaders();

$method = getRequestMethod();
$path = parsePath();
$item_id = isset($path[2]) ? (int)$path[2] : null;

// Authenticate user
$user = authenticateApiRequest();

// Check permissions
$allowed_roles = ['admin', 'inventory_manager', 'manager'];
requireRole($user, $allowed_roles);

switch ($method) {
    case 'GET':
        if ($item_id) {
            getSingleItem($item_id, $user);
        } else {
            getAllItems($user);
        }
        break;
    case 'POST':
        createItem($user);
        break;
    case 'PUT':
        if (!$item_id) {
            errorResponse('Item ID required for update', 400);
        }
        updateItem($item_id, $user);
        break;
    case 'DELETE':
        if (!$item_id) {
            errorResponse('Item ID required for deletion', 400);
        }
        deleteItem($item_id, $user);
        break;
    default:
        errorResponse('Method not allowed', 405);
}

/**
 * Get all inventory items with filtering and alerts
 * GET /api/inventory.php?low_stock=true&search=widget&limit=50
 */
function getAllItems($user) {
    global $pdo;
    $endpoint = 'inventory';
    
    // Build query with filters
    $where_conditions = [];
    $params = [];
    
    // Low stock filter
    if (isset($_GET['low_stock']) && $_GET['low_stock'] === 'true') {
        $where_conditions[] = "quantity < reorder_level";
    }
    
    // Search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where_conditions[] = "(product_name LIKE ? OR sku LIKE ?)";
        $search_term = '%' . $_GET['search'] . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Supplier filter
    if (isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id'])) {
        $where_conditions[] = "supplier_id = ?";
        $params[] = (int)$_GET['supplier_id'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Pagination
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    try {
        // Get total count
        $count_query = "SELECT COUNT(*) FROM inventory $where_clause";
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total_count = $stmt->fetchColumn();
        
        // Get inventory items with supplier info
        $query = "SELECT i.*, s.name as supplier_name, s.contact_email as supplier_email
                  FROM inventory i 
                  LEFT JOIN suppliers s ON i.supplier_id = s.id 
                  $where_clause 
                  ORDER BY i.product_name ASC 
                  LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        // Get low stock alerts count
        $low_stock_count = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity < reorder_level")->fetchColumn();
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        
        successResponse([
            'items' => sanitizeOutput($items),
            'pagination' => [
                'total' => $total_count,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total_count
            ],
            'alerts' => [
                'low_stock_count' => $low_stock_count
            ]
        ]);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch inventory', 500);
    }
}

/**
 * Get a single inventory item by ID
 * GET /api/inventory.php/123
 */
function getSingleItem($item_id, $user) {
    global $pdo;
    $endpoint = 'inventory';
    
    try {
        $query = "SELECT i.*, s.name as supplier_name, s.contact_email as supplier_email,
                         s.phone as supplier_phone, s.address as supplier_address
                  FROM inventory i 
                  LEFT JOIN suppliers s ON i.supplier_id = s.id 
                  WHERE i.id = ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            errorResponse('Item not found', 404);
        }
        
        // Get recent transactions for this item
        $transactions_query = "SELECT t.*, u.username 
                              FROM inventory_transactions t 
                              LEFT JOIN users u ON t.user_id = u.id 
                              WHERE t.item_id = ? 
                              ORDER BY t.created_at DESC 
                              LIMIT 10";
        
        $stmt = $pdo->prepare($transactions_query);
        $stmt->execute([$item_id]);
        $transactions = $stmt->fetchAll();
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        
        successResponse([
            'item' => sanitizeOutput($item),
            'recent_transactions' => sanitizeOutput($transactions)
        ]);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch item', 500);
    }
}

/**
 * Create a new inventory item
 * POST /api/inventory.php
 */
function createItem($user) {
    global $pdo;
    $endpoint = 'inventory';
    
    $data = getJsonInput();
    validateRequired($data, ['product_name', 'sku', 'quantity', 'price']);
    
    // Validate numeric fields
    if (!is_numeric($data['quantity']) || $data['quantity'] < 0) {
        errorResponse('Quantity must be a non-negative number', 400);
    }
    
    if (!is_numeric($data['price']) || $data['price'] < 0) {
        errorResponse('Price must be a non-negative number', 400);
    }
    
    // Check for duplicate SKU
    $stmt = $pdo->prepare("SELECT id FROM inventory WHERE sku = ?");
    $stmt->execute([$data['sku']]);
    if ($stmt->fetch()) {
        errorResponse('Item with this SKU already exists', 409);
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO inventory (product_name, sku, quantity, reorder_level, price, supplier_id, last_updated) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $data['product_name'],
            $data['sku'],
            (int)$data['quantity'],
            isset($data['reorder_level']) ? (int)$data['reorder_level'] : 10,
            (float)$data['price'],
            isset($data['supplier_id']) ? (int)$data['supplier_id'] : null
        ]);
        
        $item_id = $pdo->lastInsertId();
        
        // Create initial transaction record
        $pdo->prepare("INSERT INTO inventory_transactions (item_id, user_id, quantity, price, type, reason, created_at) 
                      VALUES (?, ?, ?, ?, 'initial', 'Initial stock entry', NOW())")
            ->execute([$item_id, $user['id'], (int)$data['quantity'], (float)$data['price']]);
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'POST', $user['id'], 'success');
        successResponse(['item_id' => $item_id], 'Item created successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'POST', $user['id'], 'error');
        errorResponse('Failed to create item', 500);
    }
}

/**
 * Update an existing inventory item
 * PUT /api/inventory.php/123
 */
function updateItem($item_id, $user) {
    global $pdo;
    $endpoint = 'inventory';
    
    $data = getJsonInput();
    
    // Check if item exists
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$item_id]);
    $existing_item = $stmt->fetch();
    
    if (!$existing_item) {
        errorResponse('Item not found', 404);
    }
    
    // Validate numeric fields if provided
    if (isset($data['quantity']) && (!is_numeric($data['quantity']) || $data['quantity'] < 0)) {
        errorResponse('Quantity must be a non-negative number', 400);
    }
    
    if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
        errorResponse('Price must be a non-negative number', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Build update query dynamically
        $update_fields = [];
        $update_params = [];
        
        $allowed_fields = ['product_name', 'sku', 'quantity', 'reorder_level', 'price', 'supplier_id'];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_fields[] = "$field = ?";
                $update_params[] = $data[$field];
            }
        }
        
        if (!empty($update_fields)) {
            $update_fields[] = "last_updated = NOW()";
            $update_params[] = $item_id;
            
            $update_query = "UPDATE inventory SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($update_query);
            $stmt->execute($update_params);
            
            // If quantity was updated, create transaction record
            if (isset($data['quantity'])) {
                $quantity_diff = (int)$data['quantity'] - (int)$existing_item['quantity'];
                if ($quantity_diff != 0) {
                    $transaction_type = $quantity_diff > 0 ? 'adjustment_increase' : 'adjustment_decrease';
                    $reason = isset($data['adjustment_reason']) ? $data['adjustment_reason'] : 'Manual adjustment via API';
                    
                    $pdo->prepare("INSERT INTO inventory_transactions (item_id, user_id, quantity, price, type, reason, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())")
                        ->execute([$item_id, $user['id'], abs($quantity_diff), $existing_item['price'], $transaction_type, $reason]);
                }
            }
        }
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'PUT', $user['id'], 'success');
        successResponse([], 'Item updated successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'PUT', $user['id'], 'error');
        errorResponse('Failed to update item', 500);
    }
}

/**
 * Delete an inventory item
 * DELETE /api/inventory.php/123
 */
function deleteItem($item_id, $user) {
    global $pdo;
    $endpoint = 'inventory';
    
    // Only admin can delete items
    requireRole($user, ['admin']);
    
    try {
        $pdo->beginTransaction();
        
        // Check if item exists
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            errorResponse('Item not found', 404);
        }
        
        // Delete related transactions first
        $pdo->prepare("DELETE FROM inventory_transactions WHERE item_id = ?")
            ->execute([$item_id]);
        
        // Delete the item
        $pdo->prepare("DELETE FROM inventory WHERE id = ?")
            ->execute([$item_id]);
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'DELETE', $user['id'], 'success');
        successResponse([], 'Item deleted successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'DELETE', $user['id'], 'error');
        errorResponse('Failed to delete item', 500);
    }
}
?>