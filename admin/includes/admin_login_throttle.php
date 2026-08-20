<?php
const ADMIN_LOGIN_MAX_ATTEMPTS   = 5;
const ADMIN_LOGIN_WINDOW_MINUTES = 15;
const ADMIN_LOGIN_LOCK_MINUTES   = 15;

function isAdminLoginLocked(PDO $pdo, string $email, string $ip): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ss_admin_login_attempts
         WHERE email = ? AND success = 0
           AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$email, ADMIN_LOGIN_WINDOW_MINUTES]);
    $failByEmail = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ss_admin_login_attempts
         WHERE ip_address = ? AND success = 0
           AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$ip, ADMIN_LOGIN_WINDOW_MINUTES]);
    $failByIp = (int) $stmt->fetchColumn();

    return $failByEmail >= ADMIN_LOGIN_MAX_ATTEMPTS || $failByIp >= (ADMIN_LOGIN_MAX_ATTEMPTS * 3);
}

function recordAdminLoginAttempt(PDO $pdo, string $email, string $ip, bool $success): void {
    $stmt = $pdo->prepare(
        'INSERT INTO ss_admin_login_attempts (email, ip_address, success) VALUES (?, ?, ?)'
    );
    $stmt->execute([$email, $ip, $success ? 1 : 0]);
}
