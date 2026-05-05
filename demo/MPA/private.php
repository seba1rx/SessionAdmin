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

            <div class="p-4 mb-4 rounded text-white <?= $_SESSION['sessionadmin']['urlIsAllowedToLoad'] ? 'bg-success' : 'bg-danger' ?>">
                <h4 class="mb-1">private.php — Private page</h4>
                <?php if ($_SESSION['sessionadmin']['urlIsAllowedToLoad']): ?>
                    <p class="mb-0 small">You are authenticated — welcome!</p>
                <?php else: ?>
                    <p class="mb-0 small">Access denied. Authorization check failed (or disabled in required.php).</p>
                <?php endif; ?>
            </div>

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
</body>
</html>
