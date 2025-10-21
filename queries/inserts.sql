-- Sample user
INSERT INTO `users` (`username`, `password`, `email`, `role`, `status`) 
VALUES ('admin', '$2y$10$hashedpassword', 'admin@example.com', 'admin', 'approved');

-- Sample supplier
INSERT INTO `suppliers` (`name`, `contact_email`, `phone`, `address`) 
VALUES ('TechGadgets Inc', 'sales@techgadgets.com', '+1-555-123-4567', '123 Tech Street, Silicon Valley');

-- Sample inventory item
INSERT INTO `inventory` (`product_name`, `sku`, `quantity`, `reorder_level`, `price`, `supplier_id`) 
VALUES ('Laptop', 'LP1001', 50, 10, 999.99, 1);

-- Sample lead
INSERT INTO `leads` (`name`, `email`, `phone`, `company`, `source`, `status`) 
VALUES ('John Doe', 'john@example.com', '+15551234567', 'Acme Corp', 'website', 'new');