<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../../vendor/autoload.php';

use App\MySession;

// ── Bootstrap session ─────────────────────────────────────────────────────────

$session = new MySession();
$session->activateSession();

// ── Handle form actions ───────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'login' && !empty($_POST['email'])) {
        // Simulate a successful DB lookup and create the user session.
        // Pass any scalar ID (int, string) to createUserSession().
        $session->createUserSession(42);
        $_SESSION['user'] = [
            'name'  => 'Demo User',
            'email' => htmlspecialchars($_POST['email'], ENT_QUOTES),
        ];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'logout') {
        // terminate() wipes the session and opens a fresh guest session.
        // In SPA mode (default) it does NOT redirect — we do it manually here.
        $session->terminate();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ── View helpers ──────────────────────────────────────────────────────────────

$isUser = $_SESSION['sessionadmin']['isUser'] ?? false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SessionAdmin — Basic Demo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 860px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">SessionAdmin <span class="text-muted fs-5">— Basic Demo</span></h2>
        <?php if ($isUser): ?>
            <form method="POST" class="mb-0">
                <input type="hidden" name="action" value="logout">
                <button class="btn btn-outline-danger btn-sm">Log out</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($isUser): ?>

        <div class="alert alert-success mb-4">
            Welcome, <strong><?= htmlspecialchars($_SESSION['user']['name']) ?></strong>!
            You are logged in as user ID <strong><?= $_SESSION['sessionadmin']['id_user'] ?></strong>.
        </div>

    <?php else: ?>

        <div class="card mb-4" style="max-width: 380px;">
            <div class="card-body">
                <h5 class="card-title mb-3">Log in</h5>
                <p class="text-muted small">Any email and password will work in this demo.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="demo@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" value="secret">
                    </div>
                    <button class="btn btn-primary w-100">Log in</button>
                </form>
            </div>
        </div>

    <?php endif; ?>

    <h6 class="text-muted">Session contents (<code>$_SESSION</code>)</h6>
    <pre class="bg-white border rounded p-3 small"><?= json_encode($_SESSION, JSON_PRETTY_PRINT) ?></pre>

</div>
</body>
</html>
