<?php
/**
 * Inventory Deductions API Endpoint
 * Handles sales, damaged goods, and internal use deductions
 * URL: /api/deductions.php
 */

require_once 'helpers.php';

setApiHeaders();

$method = getRequestMethod();
$path = parsePath();

// Authenticate user
$user = authenticateApiRequest();

// Check permissions
$allowed_roles = ['admin', 'manager', 'employee'];
requireRole($user, $allowed_roles);

switch ($method) {
    case 'GET':
        getDeductionHistory($user);
        break;
    case 'POST':
        processDeduction($user);
        break;
    default:
        errorResponse('Method not allowed', 405);
}

/**
 * Get deduction history with filtering
 * GET /api/deductions.php?type=sales&limit=50&start_date=2023-01-01
 */
function getDeductionHistory($user) {
    global $pdo;
    $endpoint = 'deductions';
    
    // Build query based on filters
    $where_conditions = [];
    $params = [];
    
    // Type filter
    $valid_types = ['sales', 'damaged', 'internal'];
    if (isset($_GET['type']) && in_array($_GET['type'], $valid_types)) {
        $where_conditions[] = "type = ?";
        $params[] = $_GET['type'];
    }
    
    // Date range filter
    if (isset($_GET['start_date'])) {
        $where_conditions[] = "DATE(created_at) >= ?";
        $params[] = $_GET['start_date'];
    }
    
    if (isset($_GET['end_date'])) {
        $where_conditions[] = "DATE(created_at) <= ?";
        $params[] = $_GET['end_date'];
    }
    
    // User filter (employees can only see their own deductions)
    if ($user['role'] === 'employee') {
        $where_conditions[] = "user_id = ?";
        $params[] = $user['id'];
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Pagination
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    try {
        // Get total count
        $count_query = "SELECT COUNT(*) FROM inventory_transactions $where_clause";
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total_count = $stmt->fetchColumn();
        
        // Get transactions with item and user info
        $query = "SELECT t.*, i.product_name, i.sku, u.username 
                  FROM inventory_transactions t
                  LEFT JOIN inventory i ON t.item_id = i.id
                  LEFT JOIN users u ON t.user_id = u.id
                  $where_clause 
                  ORDER BY t.created_at DESC 
                  LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll();
        
        // Get summary statistics
        $stats_query = "SELECT 
                          type,
                          COUNT(*) as count,
                          SUM(quantity) as total_quantity,
                          SUM(price * quantity) as total_value
                        FROM inventory_transactions 
                        WHERE type IN ('sales', 'damaged', 'internal')
                        " . (isset($_GET['start_date']) ? "AND DATE(created_at) >= ?" : "") . "
                        " . (isset($_GET['end_date']) ? "AND DATE(created_at) <= ?" : "") . "
                        GROUP BY type";
        
        $stats_params = [];
        if (isset($_GET['start_date'])) $stats_params[] = $_GET['start_date'];
        if (isset($_GET['end_date'])) $stats_params[] = $_GET['end_date'];
        
        $stmt = $pdo->prepare($stats_query);
        $stmt->execute($stats_params);
        $stats = $stmt->fetchAll();
        
        logApiRequest($endpoint, 'GET', $user['id'], 'success');
        
        successResponse([
            'transactions' => sanitizeOutput($transactions),
            'pagination' => [
                'total' => $total_count,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total_count
            ],
            'statistics' => sanitizeOutput($stats)
        ]);
        
    } catch (Exception $e) {
        logApiRequest($endpoint, 'GET', $user['id'], 'error');
        errorResponse('Failed to fetch deduction history', 500);
    }
}

/**
 * Process inventory deduction
 * POST /api/deductions.php
 */
function processDeduction($user) {
    global $pdo;
    $endpoint = 'deductions';
    
    $data = getJsonInput();
    validateRequired($data, ['deduction_type', 'items']);
    
    $deduction_type = $data['deduction_type'];
    $items = $data['items'];
    
    // Validate deduction type
    if (!in_array($deduction_type, ['sales', 'damaged', 'internal'])) {
        errorResponse('Invalid deduction type', 400);
    }
    
    // Validate items array
    if (!is_array($items) || empty($items)) {
        errorResponse('Items array is required and cannot be empty', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        $processed_items = [];
        $total_value = 0;
        
        foreach ($items as $item) {
            // Validate item structure
            if (!isset($item['id']) || !isset($item['quantity'])) {
                throw new Exception('Each item must have id and quantity');
            }
            
            $item_id = (int)$item['id'];
            $quantity = (int)$item['quantity'];
            $price = isset($item['price']) ? (float)$item['price'] : 0.00;
            
            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }
            
            // Get current inventory item with lock
            $stmt = $pdo->prepare("SELECT quantity, product_name, sku, price as default_price FROM inventory WHERE id = ? FOR UPDATE");
            $stmt->execute([$item_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception("Product not found for ID $item_id");
            }
            
            if (empty($product['sku'])) {
                throw new Exception("Product data incomplete for ID $item_id");
            }
            
            // Check available quantity
            if ($product['quantity'] < $quantity) {
                throw new Exception("Cannot deduct $quantity items of {$product['product_name']}. Only {$product['quantity']} available.");
            }
            
            // For sales, use provided price or default price
            if ($deduction_type === 'sales') {
                $price = $price > 0 ? $price : $product['default_price'];
            }
            
            // Update inventory quantity
            $pdo->prepare("UPDATE inventory SET quantity = quantity - ?, last_updated = NOW() WHERE id = ?")
               ->execute([$quantity, $item_id]);
            
            // Record transaction
            $pdo->prepare("INSERT INTO inventory_transactions 
                          (item_id, user_id, quantity, price, type, reason, created_at)
                          VALUES (?, ?, ?, ?, ?, ?, NOW())")
               ->execute([
                   $item_id, 
                   $user['id'], 
                   $quantity, 
                   $price, 
                   $deduction_type,
                   $data['reason'] ?? "API deduction - $deduction_type"
               ]);

            // Record in specific outbound table
            switch ($deduction_type) {
                case 'sales':
                    $stmt = $pdo->prepare("INSERT INTO outbound_sales 
                                  (item_id, product_name, sku, quantity, price, user_id, deduction_date)
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $price, $user['id']]);
                    break;
                    
                case 'damaged':
                    $stmt = $pdo->prepare("INSERT INTO outbound_damaged 
                                  (item_id, product_name, sku, quantity, user_id, deduction_date, price)
                                  VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $user['id'], $product['default_price']]);
                    break;
                    
                case 'internal':
                    $stmt = $pdo->prepare("INSERT INTO outbound_internal 
                                  (item_id, product_name, sku, quantity, user_id, deduction_date, price)
                                  VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                    $stmt->execute([$item_id, $product['product_name'], $product['sku'], $quantity, $user['id'], $product['default_price']]);
                    break;
            }
            
            if ($stmt->errorCode() !== '00000') {
                throw new Exception("Failed to record $deduction_type: " . implode(", ", $stmt->errorInfo()));
            }
            
            $processed_items[] = [
                'item_id' => $item_id,
                'product_name' => $product['product_name'],
                'sku' => $product['sku'],
                'quantity' => $quantity,
                'price' => $price,
                'line_total' => $price * $quantity
            ];
            
            $total_value += ($price * $quantity);
        }
        
        $pdo->commit();
        
        logApiRequest($endpoint, 'POST', $user['id'], 'success');
        
        successResponse([
            'deduction_type' => $deduction_type,
            'processed_items' => $processed_items,
            'total_items' => count($processed_items),
            'total_quantity' => array_sum(array_column($processed_items, 'quantity')),
            'total_value' => $total_value,
            'processed_by' => $user['username'],
            'processed_at' => date('Y-m-d H:i:s')
        ], 'Deduction processed successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logApiRequest($endpoint, 'POST', $user['id'], 'error');
        errorResponse($e->getMessage(), 400);
    }
}
?>