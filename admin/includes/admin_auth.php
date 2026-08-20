<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function requireAdminLogin(): array {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: index.php');
        exit;
    }
    return [
        'id' => $_SESSION['admin_id'],
        'email' => $_SESSION['admin_email'],
        'name' => $_SESSION['admin_name'],
    ];
}

function redirectIfAdminLoggedIn(): void {
    if (isset($_SESSION['admin_id'])) {
        header('Location: dashboard.php');
        exit;
    }
}
