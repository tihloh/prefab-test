<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$appName = (string) prefab_env('APP_NAME', 'Prefab Test');
$appName = trim($appName) !== '' ? $appName : 'Prefab Test';
$today = prefab_datetime('now')->format('date');
$example = (string) val('  hello prefab  ')->trim()->upper()->value();
$submittedName = '';
$submittedAmount = '';
$message = null;
$error = null;

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedName = (string) ($_POST['name'] ?? '');
    $submittedAmount = (string) ($_POST['amount'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals((string) $_SESSION['csrf_token'], $token)) {
        $error = 'Invalid form token. Reload the page and try again.';
    } else {
        $name = (string) val($submittedName)->trim()->value();
        $rawAmount = trim($submittedAmount);

        if ($name === '' || strlen($name) > 100) {
            $error = 'Enter a name between 1 and 100 characters.';
        } elseif ($rawAmount === '' || !is_numeric($rawAmount) || !is_finite((float) $rawAmount)) {
            $error = 'Enter a valid amount.';
        } else {
            $amount = (float) val($rawAmount)->toFloat()->value();
            $message = 'Hello, ' . $name . '! Your amount is PHP ' . number_format($amount, 2) . '.';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A lightweight PHP website using Prefab Core.">
    <title><?= e($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar navbar-expand bg-body border-bottom">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="/"><?= e($appName) ?></a>
            <span class="badge text-bg-success">Prefab Core</span>
        </div>
    </nav>
    <main class="container py-5" style="max-width: 960px">
        <div class="mb-5">
            <p class="text-secondary text-uppercase fw-semibold small mb-2">PHP starter website</p>
            <h1 class="display-5 fw-semibold">Build simply with Prefab.</h1>
            <p class="lead text-secondary">A minimal working example of Prefab Core helpers, rendered with PHP and Bootstrap.</p>
            <a class="btn btn-dark" href="https://github.com/tihloh/prefab-core" target="_blank" rel="noopener noreferrer">Explore Prefab Core</a>
        </div>

        <div class="row g-4">
            <section class="col-md-7">
                <div class="card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Try a Prefab-powered form</h2>
                        <p class="text-secondary small">The server trims your name and converts your amount using <code>val()</code>.</p>
                        <?php if ($error !== null): ?>
                            <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                        <?php endif; ?>
                        <?php if ($message !== null): ?>
                            <div class="alert alert-success" role="status"><?= e($message) ?></div>
                        <?php endif; ?>
                        <form method="post" action="/">
                            <input type="hidden" name="csrf_token" value="<?= e((string) $_SESSION['csrf_token']) ?>">
                            <div class="mb-3">
                                <label class="form-label" for="name">Your name</label>
                                <input class="form-control" id="name" name="name" maxlength="100" required value="<?= e($submittedName) ?>" placeholder="Enter your name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="amount">Sample amount (PHP)</label>
                                <input class="form-control" id="amount" name="amount" type="number" step="any" required value="<?= e($submittedAmount) ?>" placeholder="1250.50">
                            </div>
                            <button class="btn btn-primary" type="submit">Process with Prefab</button>
                        </form>
                    </div>
                </div>
            </section>
            <aside class="col-md-5">
                <div class="card shadow-sm h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Core examples</h2>
                        <p class="text-secondary small mb-1">Fluent value</p>
                        <p class="font-monospace fw-medium"><?= e($example) ?></p>
                        <hr>
                        <p class="text-secondary small mb-1">Server date</p>
                        <p class="fw-medium"><?= e((string) $today) ?></p>
                        <hr>
                        <p class="text-secondary small mb-1">Environment</p>
                        <p class="fw-medium mb-0"><?= e((string) prefab_env('APP_ENV', 'development')) ?></p>
                    </div>
                </div>
            </aside>
        </div>
        <footer class="text-center text-secondary small mt-5">Powered by PHP, Prefab Core, and Bootstrap.</footer>
    </main>
</body>
</html>
