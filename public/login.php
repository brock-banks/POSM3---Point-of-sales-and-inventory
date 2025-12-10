<?php
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/auth.php';

if (auth_user()) {
    header('Location: /POSM3/public/index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        if (auth_login($username, $password)) {
            header('Location: /POSM3/public/index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>POSM3 Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb; /* blue-600 */
            --primary-dark: #1d4ed8;
            --bg-gradient: linear-gradient(135deg, #0f172a, #1e293b, #0f766e);
        }

        body {
            min-height: 100vh;
            background: var(--bg-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 1.5rem;
        }

        .login-card {
            background: rgba(15, 23, 42, 0.9); /* slate-900 with opacity */
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
            color: #e5e7eb; /* gray-200 */
            backdrop-filter: blur(12px);
        }

        .login-card-header {
            padding: 1.5rem 1.5rem 0.75rem;
        }

        .login-card-body {
            padding: 0.75rem 1.5rem 1.5rem;
        }

        .brand-badge {
            width: 42px;
            height: 42px;
            border-radius: 0.9rem;
            background: radial-gradient(circle at 30% 30%, #38bdf8, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f172a;
            font-weight: 700;
            font-size: 1.1rem;
            margin-right: 0.75rem;
        }

        .form-control {
            background-color: #020617;
            border-color: #1f2937;
            color: #e5e7eb;
        }

        .form-control:focus {
            background-color: #020617;
            border-color: var(--primary-color);
            color: #f9fafb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }

        .form-label {
            font-size: 0.9rem;
            color: #9ca3af;
        }

        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), #22c55e);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, var(--primary-dark), #16a34a);
        }

        .text-muted-small {
            font-size: 0.8rem;
            color: #6b7280 !important;
        }

        .error-alert {
            background: rgba(220, 38, 38, 0.12);
            border: 1px solid rgba(220, 38, 38, 0.5);
            color: #fecaca;
            font-size: 0.86rem;
        }

        @media (max-width: 575.98px) {
            body {
                padding: 1rem;
            }
            .login-wrapper {
                padding: 0;
            }
            .login-card {
                border-radius: 0.75rem;
            }
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-card-header d-flex align-items-center">
            <div class="brand-badge">
                POS
            </div>
            <div>
                <h5 class="mb-0">Welcome back</h5>
                <small class="text-muted-small">Sign in to continue to POSM3</small>
            </div>
        </div>
        <div class="login-card-body">
            <?php if ($error): ?>
                <div class="alert error-alert py-2 px-3 mb-3">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="post" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        id="username"
                        name="username"
                        autocomplete="username"
                        required
                        value="<?= isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : '' ?>"
                    >
                </div>

                <div class="mb-2">
                    <label for="password" class="form-label d-flex justify-content-between">
                        <span>Password</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                            Show
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check form-check-sm">
                        <input class="form-check-input" type="checkbox" value="1" id="rememberMe" disabled>
                        <label class="form-check-label text-muted-small" for="rememberMe">
                            Remember me (coming soon)
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-sm py-2">
                    Sign in
                </button>

                <p class="text-center text-muted-small mt-3 mb-0">
                    POSM3 &middot; Secure access only <a link href="https://www.linkedin.com/in/babiker-mohammed/" class="text-decoration-none">Brotecx.dev</a>
                </p>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const input = document.getElementById('password');
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    this.textContent = isPassword ? 'Hide' : 'Show';
});
</script>
</body>
</html>