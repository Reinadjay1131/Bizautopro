INSERT INTO users (username, email, password, role, status)
VALUES (
    'employee_sarah', 
    'sarah.employee@bizautopro.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password hashed with PASSWORD_DEFAULT
    'employee', 
    'approved'
);