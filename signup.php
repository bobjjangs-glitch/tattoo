<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Validator.php';

redirectIfLoggedIn();

$errorMsg = '';
$fieldErrors = [];
$old = ['email' => '', 'name' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $old = ['email' => $email, 'name' => $name, 'phone' => $phone];

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
                // is_active는 DB 컬럼 기본값(DEFAULT 1)에 의존한다. 컬럼에 기본값이 없다면
                // 반드시 아래처럼 명시적으로 값을 넣어야 신규 계정이 로그인 단계에서 막히지 않는다.
                $stmt = $pdo->prepare(
                    'INSERT INTO ss_users (email, password_hash, name, phone, is_active, created_at)
                     VALUES (?, ?, ?, ?, 1, NOW())'
                );
                $stmt->execute([$email, $passwordHash, $name, $phone ?: null]);

                header('Location: index.php?signup=success');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[signup] ' . $e->getMessage());
            $errorMsg = '가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
        }
    }
}

$pageTitle = '회원가입';
include __DIR__ . '/includes/layout_head.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">CareForm</div>
    <p class="auth-subtitle">10초 만에 시작하세요</p>

    <?php if ($errorMsg): ?>
      <div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <form method="POST" action="signup.php">
      <div class="form-group">
        <label>이름</label>
        <input type="text" name="name" required value="<?php echo htmlspecialchars($old['name']); ?>">
        <?php if (!empty($fieldErrors['name'])): ?>
          <div class="field-error"><?php echo htmlspecialchars($fieldErrors['name']); ?></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>이메일</label>
        <input type="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($old['email']); ?>">
        <?php if (!empty($fieldErrors['email'])): ?>
          <div class="field-error"><?php echo htmlspecialchars($fieldErrors['email']); ?></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>비밀번호 (8자 이상)</label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password">
        <?php if (!empty($fieldErrors['password'])): ?>
          <div class="field-error"><?php echo htmlspecialchars($fieldErrors['password']); ?></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>비밀번호 확인</label>
        <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
        <?php if (!empty($fieldErrors['password_confirm'])): ?>
          <div class="field-error"><?php echo htmlspecialchars($fieldErrors['password_confirm']); ?></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label>전화번호</label>
        <input type="tel" name="phone" placeholder="선택 입력" value="<?php echo htmlspecialchars($old['phone']); ?>">
      </div>
      <button type="submit" class="btn-primary">회원가입</button>
    </form>

    <div class="auth-links">
      이미 계정이 있으신가요? <a href="index.php">로그인</a>
    </div>
  </div>
</div>
<style>
.field-error { color: var(--danger, #e02424); font-size: 12px; margin-top: 4px; }
</style>
<?php include __DIR__ . '/includes/layout_foot.php'; ?>
