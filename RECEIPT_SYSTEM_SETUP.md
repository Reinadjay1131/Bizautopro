# Receipt System Implementation - Setup Instructions

## Database Setup

**To create the receipts table, import the SQL file via phpMyAdmin:**

1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select the `bizautopro` database
3. Click on the "Import" tab
4. Choose file: `queries/receipts_table.sql`
5. Click "Go" to execute

**Or copy and paste the SQL directly:**

Open phpMyAdmin SQL tab and run:

```sql
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
```

## How It Works

### Automatic Receipt Generation

Every time an inventory deduction is submitted through `module_deduction.php`:

1. **Deduction is processed** (existing functionality - unchanged)
2. **Receipt is automatically created** with:
   - Unique receipt number (format: `RCP-YYYYMMDD-TYPE-ITEMID-TIMESTAMP`)
   - All transaction details
   - Before/after stock levels
   - User information
   - Timestamp

3. **No changes to existing workflow** - receipts are generated silently in the background

### Accessing Receipts

**View All Receipts:**
- Navigate to: `receipts.php`
- Or add link in navigation menu

**Features:**
- Search by receipt number, product, SKU, or user
- Filter by transaction type (sale, damaged, internal)
- Filter by date range
- View summary statistics
- Export to CSV
- View detailed receipt
- Print receipt

**Actions for Each Receipt:**
- 👁️ View - Opens detailed modal view
- 🖨️ Print - Opens print-friendly version in new window

## Files Created/Modified

### New Files:
1. `queries/receipts_table.sql` - Database schema
2. `receipts.php` - Main receipts viewing page
3. `api/get_receipt.php` - API to fetch receipt details
4. `print_receipt.php` - Print-friendly receipt page
5. `export_receipts.php` - CSV export functionality

### Modified Files:
1. `module_deduction.php` - Added automatic receipt generation (lines 47-113)
   - Captures stock before deduction
   - Generates unique receipt number
   - Stores complete receipt record
   - No changes to existing deduction logic

## Testing

After setting up the database:

1. Go to `module_deduction.php`
2. Process any inventory deduction (sale, damaged, or internal)
3. Navigate to `receipts.php`
4. Verify the receipt was automatically created
5. Test viewing and printing the receipt

## Receipt Number Format

`RCP-[DATE]-[TYPE]-[ITEMID]-[TIMESTAMP]`

Example: `RCP-20251111-SAL-00042-1731369600`

- DATE: YYYYMMDD format
- TYPE: SAL (sale), DAM (damaged), INT (internal)
- ITEMID: 5-digit padded inventory item ID
- TIMESTAMP: Unix timestamp for uniqueness

## Access Control

All users with deduction permissions (admin, manager, employee) can:
- View receipts
- Print receipts
- Export receipts

The system maintains audit trails showing who performed each deduction.
