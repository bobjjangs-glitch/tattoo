<?php
session_start();

function requireLogin(): array {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'name' => $_SESSION['user_name'],
    ];
}

function redirectIfLoggedIn(): void {
    if (isset($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
}
