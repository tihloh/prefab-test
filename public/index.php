<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/database.php';
require dirname(__DIR__) . '/bootstrap/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect_home(): never
{
    header('Location: /', true, 303);
    exit;
}

$appName = trim((string) prefab_env('APP_NAME', 'Prefab Test')) ?: 'Prefab Test';
$today = prefab_datetime('now')->format('date');
$example = (string) val('  hello prefab  ')->trim()->upper()->value();
$page = (string) ($_GET['page'] ?? 'home');
if (!in_array($page, ['home', 'login', 'register'], true)) {
    http_response_code(404);
    $page = 'home';
}
$error = null;
$notice = null;
$records = [];
$values = ['name' => '', 'email' => '', 'amount' => ''];
$database = null;
$users = null;
$auth = null;

try {
    [$users, $auth] = app_auth();
    $database = app_database();
} catch (Throwable $exception) {
    error_log('Prefab initialization failed: ' . $exception->getMessage());
    http_response_code(503);
    $error = 'The application is temporarily unavailable. Check the PHP SQLite extension, Composer dependencies, and storage permissions.';
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($auth !== null && $auth->check() && $auth->user() === null) {
    $auth->logout();
}
$currentUser = $auth?->user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth !== null && $users !== null && $database !== null) {
    $action = (string) ($_POST['action'] ?? '');
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    foreach ($values as $key => $value) {
        $values[$key] = is_string($_POST[$key] ?? null) ? (string) $_POST[$key] : '';
    }

    if (!hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        http_response_code(400);
        $error = 'Invalid form token. Reload the page and try again.';
    } elseif ($action === 'logout') {
        $auth->logout();
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        redirect_home();
    } elseif ($action === 'register' && $currentUser === null) {
        $name = trim($values['name']);
        $email = strtolower(trim($values['email']));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $page = 'register';
        if ($name === '' || strlen($name) > 100) {
            $error = 'Enter a name between 1 and 100 characters.';
        } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 12 || strlen($password) > 1024) {
            $error = 'Use a password of at least 12 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif ($users->findByEmail($email) !== null) {
            $error = 'This email address is already registered.';
        } else {
            try {
                $created = $users->create([
                    'name' => $name,
                    'email' => $email,
                    'active' => 1,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                session_regenerate_id(true);
                $result = $auth->login($created->data->id);
                if (!$result->success) {
                    throw new RuntimeException('Could not authenticate registered account.');
                }
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                redirect_home();
            } catch (Throwable $exception) {
                error_log('Registration failed: ' . $exception->getMessage());
                $error = 'Could not register. Confirm the email is unused and try again.';
            }
        }
    } elseif ($action === 'login' && $currentUser === null) {
        $page = 'login';
        $email = strtolower(trim($values['email']));
        $password = (string) ($_POST['password'] ?? '');
        $attempts = (array) ($_SESSION['login_attempts'] ?? []);
        $attempts = array_values(array_filter($attempts, static fn ($at): bool => is_int($at) && $at > time() - 900));
        if (count($attempts) >= 5) {
            $error = 'Too many attempts. Try again in 15 minutes.';
        } else {
            try {
                $result = $auth->attempt($email, $password, [
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
                if ($result->success) {
                    session_regenerate_id(true);
                    unset($_SESSION['login_attempts']);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    redirect_home();
                }
                $attempts[] = time();
                $_SESSION['login_attempts'] = $attempts;
                $error = 'Invalid email or password.';
            } catch (Throwable $exception) {
                error_log('Login failed: ' . $exception->getMessage());
                $error = 'Sign-in is temporarily unavailable.';
            }
        }
    } elseif ($action === 'save' && $currentUser !== null) {
        $page = 'home';
        $name = trim($values['name']);
        $amountText = trim($values['amount']);
        if ($name === '' || strlen($name) > 100) {
            $error = 'Enter a name between 1 and 100 characters.';
        } elseif ($amountText === '' || !is_numeric($amountText) || !is_finite((float) $amountText)) {
            $error = 'Enter a valid amount.';
        } else {
            try {
                $amount = (float) val($amountText)->toFloat()->value();
                $database->statement(
                    'INSERT INTO submissions (user_id, name, amount) VALUES (:user_id, :name, :amount)',
                    ['user_id' => (int) $auth->id(), 'name' => $name, 'amount' => $amount]
                );
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['flash'] = 'Submission saved.';
                redirect_home();
            } catch (Throwable $exception) {
                error_log('Submission failed: ' . $exception->getMessage());
                $error = 'Could not save your record.';
            }
        }
    } else {
        http_response_code(403);
        $error = 'This action is not permitted.';
    }
}

$currentUser = $auth?->user();
if ($currentUser !== null && ($page === 'login' || $page === 'register')) {
    redirect_home();
}
if ($currentUser !== null && $database !== null) {
    try {
        $records = $database->select(
            'SELECT id, name, amount, created_at FROM submissions WHERE user_id = :user_id ORDER BY id DESC LIMIT 10',
            ['user_id' => (int) $currentUser->authId()]
        );
    } catch (Throwable $exception) {
        error_log('Could not read submissions: ' . $exception->getMessage());
        $error = 'Unable to load your submissions.';
    }
}
if (isset($_SESSION['flash'])) {
    $notice = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Prefab Core, Users and Auth SQLite demo.">
    <title><?= e($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar bg-body border-bottom">
        <div class="container" style="max-width: 960px">
            <a class="navbar-brand fw-semibold" href="/"><?= e($appName) ?></a>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($currentUser !== null): ?>
                    <span class="small text-secondary"><?= e((string) ($currentUser->name ?? 'User')) ?></span>
                    <form method="post" action="/">
                        <input type="hidden" name="action" value="logout">
                        <input type="hidden" name="csrf_token" value="<?= e((string) $_SESSION['csrf_token']) ?>">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Log out</button>
                    </form>
                <?php else: ?>
                    <a href="/?page=login" class="btn btn-outline-secondary btn-sm">Log in</a>
                    <a href="/?page=register" class="btn btn-primary btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container py-5" style="max-width: 960px">
        <header class="mb-4">
            <p class="text-secondary text-uppercase fw-semibold small mb-2">Prefab PHP demo</p>
            <h1 class="display-6 fw-semibold"><?= $page === 'login' ? 'Log in' : ($page === 'register' ? 'Create an account' : 'Build simply with Prefab.') ?></h1>
            <p class="text-secondary">Prefab Core + Users + Auth backed by SQLite.</p>
        </header>
        <?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($notice !== null): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif; ?>
        <?php if ($page === 'login' || $page === 'register'): ?>
            <section class="card shadow-sm" style="max-width: 480px">
                <div class="card-body p-4">
                    <form method="post" action="/?page=<?= e($page) ?>">
                        <input type="hidden" name="csrf_token" value="<?= e((string) $_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="<?= e($page) ?>">
                        <?php if ($page === 'register'): ?>
                            <div class="mb-3">
                                <label class="form-label" for="name">Name</label>
                                <input class="form-control" id="name" name="name" required maxlength="100" autocomplete="name" value="<?= e($values['name']) ?>">
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" name="email" type="email" required maxlength="254" autocomplete="email" value="<?= e($values['email']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" id="password" name="password" type="password" required autocomplete="<?= $page === 'register' ? 'new-password' : 'current-password' ?>" <?= $page === 'register' ? 'minlength="12"' : '' ?>>
                            <?php if ($page === 'register'): ?><div class="form-text">Use at least 12 characters.</div><?php endif; ?>
                        </div>
                        <?php if ($page === 'register'): ?>
                            <div class="mb-3">
                                <label class="form-label" for="confirm_password">Confirm password</label>
                                <input class="form-control" id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password" minlength="12">
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary" <?= $auth === null ? 'disabled' : '' ?>><?= $page === 'register' ? 'Create account' : 'Log in' ?></button>
                    </form>
                    <p class="small mt-3 mb-0">
                        <?php if ($page === 'register'): ?>Already registered? <a href="/?page=login">Log in</a><?php else: ?>Need an account? <a href="/?page=register">Register</a><?php endif; ?>
                    </p>
                </div>
            </section>
        <?php elseif ($currentUser === null): ?>
            <section class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5">Your submissions are private</h2>
                    <p class="text-secondary">Create an account or log in to store and view your SQLite records.</p>
                    <a class="btn btn-primary" href="/?page=register">Get started</a>
                </div>
            </section>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-md-7">
                    <section class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h2 class="h5">New submission</h2>
                            <p class="small text-secondary">Stored in SQLite under your account.</p>
                            <form method="post" action="/">
                                <input type="hidden" name="csrf_token" value="<?= e((string) $_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="save">
                                <div class="mb-3">
                                    <label class="form-label" for="name">Name</label>
                                    <input class="form-control" id="name" name="name" required maxlength="100" value="<?= e($values['name']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="amount">Amount (PHP)</label>
                                    <input class="form-control" id="amount" name="amount" type="number" step="any" required value="<?= e($values['amount']) ?>">
                                </div>
                                <button class="btn btn-primary" type="submit">Save to SQLite</button>
                            </form>
                        </div>
                    </section>
                </div>
                <div class="col-md-5">
                    <aside class="card shadow-sm h-100">
                        <div class="card-body p-4">
                            <h2 class="h5">Core examples</h2>
                            <p class="small text-secondary mb-1">Fluent value</p>
                            <p class="font-monospace"><?= e($example) ?></p>
                            <hr>
                            <p class="small text-secondary mb-1">Server date</p>
                            <p><?= e((string) $today) ?></p>
                            <hr>
                            <p class="small text-secondary mb-1">Environment</p>
                            <p><?= e((string) prefab_env('APP_ENV', 'development')) ?></p>
                        </div>
                    </aside>
                </div>
            </div>
            <section class="card shadow-sm mt-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Your recent submissions</h2>
                    <?php if ($records === []): ?>
                        <p class="text-secondary mb-0">No submissions yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead><tr><th>#</th><th>Name</th><th class="text-end">Amount (PHP)</th><th>Created (UTC)</th></tr></thead>
                                <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><?= (int) $record['id'] ?></td>
                                        <td><?= e((string) $record['name']) ?></td>
                                        <td class="text-end"><?= e(number_format((float) $record['amount'], 2)) ?></td>
                                        <td><?= e((string) $record['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
        <footer class="text-secondary text-center small mt-5">Powered by PHP, Prefab Core, Prefab Users, Prefab Auth, and SQLite.</footer>
    </main>
</body>
</html>
