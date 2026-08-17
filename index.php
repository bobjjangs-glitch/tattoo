<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';

redirectIfLoggedIn();

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errorMsg = '이메일과 비밀번호를 입력해주세요.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('SELECT id, email, password_hash, name, is_active FROM ss_users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errorMsg = '이메일 또는 비밀번호가 일치하지 않습니다.';
            } elseif (!$user['is_active']) {
                $errorMsg = '비활성화된 계정입니다. 고객센터에 문의해주세요.';
            } else {
                $pdo->prepare('UPDATE ss_users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

                // 세션 고정(fixation) 공격 방지를 위해 로그인 성공 시 세션ID를 재발급한다.
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['name'];

                header('Location: dashboard.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[login] ' . $e->getMessage());
            $errorMsg = '서버 오류로 로그인에 실패했습니다. 잠시 후 다시 시도해주세요.';
        }
    }
}

$pageTitle = '로그인 | SalonForm';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="login-page">
  <div class="login-box">
    <h1 class="logo-text">SalonForm</h1>
    <p class="login-desc">뷰티 매장 전자동의서 관리</p>
    <form method="post" class="login-form">
      <div class="form-group">
        <label>이메일</label>
        <input type="email" name="email" placeholder="you@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>비밀번호</label>
        <input type="password" name="password" placeholder="비밀번호" required>
      </div>
      <?php if ($errorMsg): ?>
        <p class="error-text"><?= htmlspecialchars($errorMsg) ?></p>
      <?php endif; ?>
      <button type="submit" class="btn-primary btn-full">로그인</button>
    </form>
    <p class="login-desc" style="margin-top:16px;">
      계정이 없으신가요? <a href="signup.php">회원가입</a>
    </p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
