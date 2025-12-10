<?php
require_once __DIR__ . '/db.php';

function auth_login(string $username, string $password): bool
{
    $pdo = db();

    // DEBUG (you can remove later)
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    error_log("AUTH DEBUG: DB=" . $dbName . " username_try=" . $username);

    $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, role, is_active
                           FROM users
                           WHERE username = :username
                           LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        error_log("AUTH DEBUG: user not found");
        return false;
    }

    if ((int)$user['is_active'] !== 1) {
        error_log("AUTH DEBUG: user inactive id=" . $user['id']);
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        error_log("AUTH DEBUG: password mismatch for id=" . $user['id']);
        return false;
    }

    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
        ->execute([':id' => $user['id']]);

    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ];

    error_log("AUTH DEBUG: login success id=" . $user['id']);

    return true;
}

function auth_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function auth_require_login(): void
{
    if (!auth_user()) {
        header('Location: /POSM3/public/login.php');
        exit;
    }
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function auth_require_role(string $role): void
{
    $user = auth_user();
    if (!$user) {
        header('Location: /POSM3/public/login.php');
        exit;
    }
    if (strtoupper($user['role']) !== strtoupper($role)) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
}