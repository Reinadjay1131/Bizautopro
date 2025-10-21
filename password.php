<?php
require 'config.php';

$users = [
    [
        'username' => 'Fidelia',
        'password' => password_hash('whatwhat567', PASSWORD_BCRYPT),
        'email' => 'nobfundamental101@gmail.com',
        'role' => 'admin',
        'status' => 'approved'
    ],
    [
        'username' => 'Ebuka',
        'password' => password_hash('ebuka123', PASSWORD_BCRYPT),
        'email' => 'ibeachuhenry@gmail.com',
        'role' => 'manager',
        'status' => 'approved'
    ],
    [
        'username' => 'Emeka',
        'password' => password_hash('emeka567', PASSWORD_BCRYPT),
        'email' => 'henryibeachu2008@gmail.com',
        'role' => 'employee',
        'status' => 'approved'
    ]
];

foreach ($users as $user) {
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user['username'], $user['password'], $user['email'], $user['role'], $user['status']]);
}

echo "Users inserted successfully!";
?>