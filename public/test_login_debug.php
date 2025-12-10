<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';

$pdo = db();

// 1) Show which DB PHP is using
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "<h3>DB Name: " . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . "</h3>";

// 2) Fetch user 123
$stmt = $pdo->prepare('SELECT id, username, role, is_active, password_hash FROM users WHERE username = :u LIMIT 1');
$stmt->execute([':u' => '123']);
$user = $stmt->fetch();

if (!$user) {
    echo "<p style='color:red'>No user with username=123 found in DB.</p>";
    exit;
}

echo "<pre>User row:\n";
print_r([
    'id'        => $user['id'],
    'username'  => $user['username'],
    'role'      => $user['role'],
    'is_active' => $user['is_active'],
    'hash_len'  => strlen($user['password_hash']),
    'hash_start'=> substr($user['password_hash'], 0, 15),
]);
echo "</pre>";

// 3) Test password_verify against '123'
$testPassword = '123';
$ok = password_verify($testPassword, $user['password_hash']);

echo "<p>password_verify('123', stored_hash) = <strong>" . ($ok ? 'TRUE' : 'FALSE') . "</strong></p>";