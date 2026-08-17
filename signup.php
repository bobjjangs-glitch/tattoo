<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Validator.php';

redirectIfLoggedIn();

$errorMsg = '';
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (!$email || !Validator::isValidEmail($email)) {
        $fieldErrors['email'] = '올바른 이메일 형식이 아닙니다.';
    }
    if (strlen($password) < 8) {
        $fieldErrors['password'] = '비밀번호는 8자리 이상이어야 합니다.';
    } elseif ($password !== $passwordConfirm) {
        $fieldErrors['password_confirm'] = '비밀번호가 일치하지 않습니다.';
    }
    if (!$name) {
        $fieldErrors['name'] = '이름을 입력해주세요.';
    }

    if (!$fieldErrors) {
        try {
            $pdo = getDbConnection();

            $stmt = $pdo->prepare('SELECT id FROM ss_users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $fieldErrors['email'] = '이미 가입된 이메일입니다.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                // id는 auto_increment이므로 INSERT 목록에서 절대 넣지 않는다.
                $stmt = $pdo->prepare(
                    'INSERT INTO ss_users (email, password_hash, name, phone, created_at) VALUES (?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$email, $passwordHash, $name, $phone ?: null]);

                header('Location: index.php?signup=success');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[signup] ' . $e->getMessage());
            $errorMsg = '서버 오류로 회원가입에 실패했습니다. 잠시 후 다시 시도해주세요.';
        }
    }
}

$pageTitle = '회원가입 | SalonForm';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="login-page">
  <div class="login-box">
    <h1 class="logo-text">SalonForm</h1>
    <p class="login-desc">회원가입</p>
    <form method="post" class="login-form">
      <div class="form-group">
        <label>이름</label>
        <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        <?php if (!empty($fieldErrors['name'])): ?><p class="error-text"><?= htmlspecialchars($fieldErrors['name']) ?></p><?php endif; ?>
      </div>
      <div class="form-group">
        <label>이메일</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        <?php if (!empty($fieldErrors['email'])): ?><p class="error-text"><?= htmlspecialchars($fieldErrors['email']) ?></p><?php endif; ?>
      </div>
      <div class="form-group">
        <label>전화번호 (선택)</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>비밀번호 (8자리 이상)</label>
        <input type="password" name="password" required>
        <?php if (!empty($fieldErrors['password'])): ?><p class="error-text"><?= htmlspecialchars($fieldErrors['password']) ?></p><?php endif; ?>
      </div>
      <div class="form-group">
        <label>비밀번호 확인</label>
        <input type="password" name="password_confirm" required>
        <?php if (!empty($fieldErrors['password_confirm'])): ?><p class="error-text"><?= htmlspecialchars($fieldErrors['password_confirm']) ?></p><?php endif; ?>
      </div>
      <?php if ($errorMsg): ?><p class="error-text"><?= htmlspecialchars($errorMsg) ?></p><?php endif; ?>
      <button type="submit" class="btn-primary btn-full">가입하기</button>
    </form>
    <p class="login-desc" style="margin-top:16px;">
      이미 계정이 있으신가요? <a href="index.php">로그인</a>
    </p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
