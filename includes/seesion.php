<?php
/**
 * 세션 시작 전에 쿠키 보안 옵션을 지정한다.
 * - httponly: JS(document.cookie)로 세션 쿠키를 읽지 못하게 막음 → XSS로 인한 세션 탈취 방어
 * - secure  : HTTPS 연결에서만 쿠키를 전송 → 평문(HTTP) 통신 스니핑 방어
 * - samesite: 다른 사이트에서 유발된 요청에는 쿠키를 안 보냄 → CSRF 방어
 *
 * 주의: session_start()가 호출되기 전에만 설정이 적용된다.
 *       이 파일은 반드시 각 페이지에서 다른 세션 관련 코드보다 먼저 include 되어야 한다.
 */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    session_set_cookie_params([
        'lifetime' => 0,          // 브라우저를 닫으면 만료 (필요 시 조정)
        'path'     => '/',        // 기존 경로 범위 유지 (좁히면 로그인 유지 범위가 깨질 수 있음)
        'domain'   => '',         // 현재 도메인 기준 그대로 사용
        'secure'   => $isHttps,   // HTTPS일 때만 true. 로컬 HTTP 테스트 환경도 자동 대응.
        'httponly' => true,
        'samesite' => 'Lax',      // 일반 링크 이동은 허용하되 외부發 POST 요청은 차단
    ]);

    session_start();
}

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
