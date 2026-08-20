<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_login_throttle.php';
require_once __DIR__ . '/../api/config/database.php';

redirectIfAdminLoggedIn();

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (!$email || !$password) {
        $errorMsg = '이메일과 비밀번호를 입력해주세요.';
    } else {
        try {
            $pdo = getDbConnection();
            if (isAdminLoginLocked($pdo, $email, $ip)) {
                $errorMsg = '로그인 시도가 너무 많습니다. ' . ADMIN_LOGIN_LOCK_MINUTES . '분 후 다시 시도해주세요.';
            } else {
                $stmt = $pdo->prepare('SELECT id, email, password_hash, name, is_active FROM ss_admin_users WHERE email = ?');
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if (!$admin || !password_verify($password, $admin['password_hash'])) {
                    recordAdminLoginAttempt($pdo, $email, $ip, false);
                    $errorMsg = '이메일 또는 비밀번호가 일치하지 않습니다.';
                } elseif (!$admin['is_active']) {
                    recordAdminLoginAttempt($pdo, $email, $ip, false);
                    $errorMsg = '비활성화된 관리자 계정입니다.';
                } else {
                    recordAdminLoginAttempt($pdo, $email, $ip, true);
                    $pdo->prepare('UPDATE ss_admin_users SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name'] = $admin['name'];
                    header('Location: dashboard.php');
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('[admin login] ' . $e->getMessage());
            $errorMsg = '일시적인 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>관리자 로그인 - CareForm Admin</title>
<link rel="stylesheet" href="/tattoo/assets/css/common.css">
<link rel="stylesheet" href="/tattoo/assets/css/theme-brand.css">
<link rel="stylesheet" href="/tattoo/assets/css/admin-theme.css">
</head>
<body>
<div class="auth-page admin-auth-page">
  <div class="auth-card">
    <div class="auth-logo">CareForm<span class="admin-tag">Admin</span></div>
    <p class="auth-subtitle">플랫폼 최고관리자 계정으로 로그인하세요</p>
    <?php if ($errorMsg): ?>
      <div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
    <form method="POST" action="index.php">
      <div class="form-group">
        <label>이메일</label>
        <input type="email" name="email" required autocomplete="username">
      </div>
      <div class="form-group">
        <label>비밀번호</label>
        <input type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-primary">로그인</button>
    </form>
  </div>
</div>
</body>
</html>
