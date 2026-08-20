<?php
// 운영 환경에서는 화면에 오류를 노출하지 않고 로그로만 남긴다.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/includes/login_throttle.php';

redirectIfLoggedIn();

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = getClientIp();

    if (!$email || !$password) {
        $errorMsg = '이메일과 비밀번호를 입력해주세요.';
    } else {
        try {
            $pdo = getDbConnection();

            if (isOwnerLoginLocked($pdo, $email, $ip)) {
                $errorMsg = '로그인 시도가 너무 많습니다. ' . LOGIN_LOCK_MINUTES . '분 후 다시 시도해주세요.';
            } else {
                $stmt = $pdo->prepare('SELECT id, email, password_hash, name, is_active FROM ss_users WHERE email = ?');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    recordOwnerLoginAttempt($pdo, $email, $ip, false);
                    $errorMsg = '이메일 또는 비밀번호가 일치하지 않습니다.';
                } elseif (!$user['is_active']) {
                    recordOwnerLoginAttempt($pdo, $email, $ip, false);
                    $errorMsg = '비활성화된 계정입니다. 고객센터에 문의해주세요.';
                } else {
                    recordOwnerLoginAttempt($pdo, $email, $ip, true);

                    $pdo->prepare('UPDATE ss_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

                    // ⚠ 핵심 수정: 이전에 남아있을 수 있는 직원(staff) 세션 값을 반드시 제거
                    unset($_SESSION['staff_id'], $_SESSION['staff_store_id'], $_SESSION['staff_name']);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['name'];

                    header('Location: dashboard.php');
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('[login] ' . $e->getMessage());
            $errorMsg = '일시적인 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
        }
    }
}

$pageTitle = '로그인';
include __DIR__ . '/includes/layout_head.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">CareForm</div>
    <p class="auth-subtitle">이메일로 로그인하세요</p>

    <?php if ($errorMsg): ?>
      <div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php">
      <div class="form-group">
        <label>이메일</label>
        <input type="email" name="email" required autocomplete="email">
      </div>
      <div class="form-group">
        <label>비밀번호</label>
        <input type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-primary">로그인</button>
    </form>

    <div class="auth-links">
      아직 계정이 없으신가요? <a href="signup.php">회원가입</a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/layout_foot.php'; ?>
