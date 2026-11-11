<?php
session_start();
require_once "../config.php";
$pageTitle = 'Access Denied';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>403 - Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="text-center p-5 bg-white rounded shadow-sm" style="max-width: 450px;">
        <div class="mb-3 text-danger" style="font-size: 3.5rem;">
            <i class="bi bi-shield-exclamation"></i>
        </div>
        <h2 class="fw-bold text-dark">Access Denied</h2>
        <p class="text-muted mb-4">You do not have the required permissions to access this module or perform this action.</p>
        <a href="../index.php" class="btn btn-dark w-100 py-2">Return to Dashboard</a>
    </div>
</body>
</html>