-- View all admins
SELECT * FROM users WHERE role = 'admin';

-- View all managers
SELECT * FROM users WHERE role = 'manager';

-- View pending approvals
SELECT * FROM users WHERE status = 'pending';