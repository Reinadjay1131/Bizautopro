-- Receipts Table for Inventory Deductions
-- Stores permanent records of all inventory deductions (sales, internal, damaged)

CREATE TABLE IF NOT EXISTS receipts (
    receipt_id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    transaction_type ENUM('sale', 'internal', 'damaged') NOT NULL,
    inventory_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(100),
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    stock_before INT NOT NULL,
    stock_after INT NOT NULL,
    reason TEXT,
    deducted_by INT NOT NULL,
    deducted_by_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    receipt_data JSON,
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_inventory_id (inventory_id),
    INDEX idx_deducted_by (deducted_by),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (deducted_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
