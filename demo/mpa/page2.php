<?php require 'AppFiles/required.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SessionAdmin — MPA Demo</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="assets/seba1rx_sessionAdmin.js"></script>
</head>
<body class="bg-light">
<div class="container py-5">

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="p-4 mb-4 rounded text-white <?= $_SESSION['sessionadmin']['urlIsAllowedToLoad'] ? 'bg-success' : 'bg-secondary' ?>">
                <h4 class="mb-1">page2.php — Public page</h4>
                <p class="mb-0 small">Everyone can access this page.</p>
            </div>

            <?php if (!$_SESSION['sessionadmin']['isUser']): ?>
            <div class="card mb-4" style="max-width: 420px;">
                <div class="card-body">
                    <h5 class="card-title">Log in</h5>
                    <p class="text-muted small">Any email and password will work in this demo.</p>
                    <form id="loginForm">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="useremail" value="demo@mail.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="userpassword" value="your_password_here">
                        </div>
                        <button type="button" class="btn btn-primary w-100" onclick="tryToAuthenticate()">Authenticate</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-md-6">
                    <h6>Navigation</h6>
                    <ul class="list-unstyled">
                        <li><a href="index.php">index.php</a> — public</li>
                        <li><a href="page2.php">page2.php</a> — public (login here)</li>
                        <li><a href="private.php">private.php</a> — private (requires login)</li>
                    </ul>
                    <?php if ($_SESSION['sessionadmin']['isUser']): ?>
                        <a href="exit.php" class="btn btn-outline-danger btn-sm mt-2">Log out</a>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h6>Session data</h6>
                    <pre class="bg-white border rounded p-2 small"><?= json_encode($_SESSION, JSON_PRETTY_PRINT) ?></pre>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
    async function tryToAuthenticate() {
        const form   = document.getElementById('loginForm');
        const params = new URLSearchParams(new FormData(form));
        const res    = await fetch('AppFiles/authentication.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    params,
        });
        const data = await res.json();
        if (data.ok) {
            alert(data.msg);
            location.reload();
        } else {
            alert(data.msg);
        }
    }
</script>
</body>
</html>
