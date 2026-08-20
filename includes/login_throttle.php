<?php
/**
 * 로그인 시도 제한(Brute-force 방어) 공통 로직
 * - 매장 직원 로그인: ss_login_attempts (store_id 기준)
 * - 대표자 로그인: ss_owner_login_attempts (email 기준)
 *
 * 정책: 최근 LOGIN_WINDOW_MINUTES 분 안에 같은 대상으로
 *       LOGIN_MAX_ATTEMPTS 회 이상 로그인 실패 시 LOGIN_LOCK_MINUTES 분간 잠금.
 */

const LOGIN_MAX_ATTEMPTS   = 5;   // 허용 실패 횟수
const LOGIN_WINDOW_MINUTES = 15;  // 실패 횟수를 세는 기준 시간
const LOGIN_LOCK_MINUTES   = 15;  // 잠금 유지 시간

/**
 * 클라이언트 IP를 반환한다.
 * 리버스 프록시(로드밸런서) 뒤에 있는 경우 X-Forwarded-For를 신뢰하도록
 * 인프라 구성에 맞춰 별도로 조정이 필요할 수 있다.
 */
function getClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * 매장 직원 로그인이 잠겨 있는지 확인한다.
 */
function isStoreLoginLocked(PDO $pdo, string $storeId, string $ip): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ss_login_attempts
         WHERE store_id = ? AND success = 0
           AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$storeId, LOGIN_WINDOW_MINUTES]);
    $failByStore = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ss_login_attempts
         WHERE ip_address = ? AND success = 0
           AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$ip, LOGIN_WINDOW_MINUTES]);
    $failByIp = (int) $stmt->fetchColumn();

    // 계정 기준 또는 IP 기준(여러 계정을 훑는 크리덴셜 스터핑 방어) 중 하나라도 초과하면 잠금
    return $failByStore >= LOGIN_MAX_ATTEMPTS || $failByIp >= (LOGIN_MAX_ATTEMPTS * 3);
}

/**
 * 매장 직원 로그인 시도를 기록한다. (성공/실패 모두 기록)
 */
function recordStoreLoginAttempt(PDO $pdo, string $storeId, string $ip, bool $success): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO ss_login_attempts (store_id, ip_address, success)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$storeId, $ip, $success ? 1 : 0]);
}

/**
 * 대표자(오너) 로그인이 잠겨 있는지 확인한다.
 */
function isOwnerLoginLocked(PDO $pdo, string $email, string $ip): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ss_owner_login_attempts
         WHERE email = ? AND success = 0
           AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$email, LOGIN_WINDOW_MINUTES]);
    $failByEmail = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ss_owner_login_attempts
         WHERE ip_address = ? AND success = 0
           AND attempted_at >= (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$ip, LOGIN_WINDOW_MINUTES]);
    $failByIp = (int) $stmt->fetchColumn();

    return $failByEmail >= LOGIN_MAX_ATTEMPTS || $failByIp >= (LOGIN_MAX_ATTEMPTS * 3);
}

/**
 * 대표자 로그인 시도를 기록한다. (성공/실패 모두 기록)
 */
function recordOwnerLoginAttempt(PDO $pdo, string $email, string $ip, bool $success): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO ss_owner_login_attempts (email, ip_address, success)
         VALUES (?, ?, ?)'
    );
    $stmt->execute([$email, $ip, $success ? 1 : 0]);
}
