-- Add role column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('admin', 'manager', 'employee') NOT NULL DEFAULT 'employee';

-- Add status column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved';