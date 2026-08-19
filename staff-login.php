<?php
ob_start();

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';

$pdo = getDbConnection();

$storeId = $_GET['store_id'] ?? ($_POST['store_id'] ?? '');
$loginError = '';

if ($storeId === '') {
    $loginError = '잘못된 접속 경로입니다. 매장 대표에게 전달받은 직원 로그인 링크로 다시 접속해주세요.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $storeId !== '') {
    $storeId = $_POST['store_id'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($storeId === '') {
        $loginError = '잘못된 접속 경로입니다. 매장 대표에게 전달받은 직원 로그인 링크로 다시 접속해주세요.';
    } elseif ($email === '' || $password === '') {
        $loginError = '이메일과 비밀번호를 모두 입력해주세요.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, password_hash, role, is_active FROM ss_store_staff WHERE store_id = ? AND LOWER(email) = LOWER(?)');
        $stmt->execute([$storeId, $email]);
        $staff = $stmt->fetch();

        if (!$staff) {
            $loginError = '해당 매장에 등록된 이메일이 아닙니다.';
        } elseif (!password_verify($password, $staff['password_hash'])) {
            $loginError = '비밀번호가 일치하지 않습니다.';
        } elseif (!$staff['is_active']) {
            $loginError = '비활성화된 계정입니다. 매장 대표에게 문의해주세요.';
        } else {
            // ★ 핵심: 직원으로 로그인하는 순간, 같은 브라우저에 남아있던 대표(owner) 세션을 반드시 제거.
            //   이게 없으면 requireStoreAccess()가 대표 세션을 먼저 검사해버려서
            //   방금 로그인한 직원 정보가 무시되고 대표로 처리되는 버그가 발생한다.
            unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name']);

            session_regenerate_id(true);
            $_SESSION['staff_id'] = $staff['id'];
            $_SESSION['staff_store_id'] = $storeId;
            $_SESSION['staff_name'] = $staff['name'];
            ob_end_clean();
            header('Location: store.php?id=' . urlencode($storeId));
            exit;
        }
    }
}

$pageTitle = '직원 로그인';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>직원 로그인 - SalonForm</title>
<link rel="stylesheet" href="/tattoo/assets/css/common.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">SalonForm</div>
    <p class="auth-subtitle">직원 계정으로 로그인하세요</p>
    <?php if ($loginError): ?>
      <div class="alert-error"><?php echo htmlspecialchars($loginError); ?></div>
    <?php endif; ?>
    <?php if ($storeId !== ''): ?>
    <form method="POST">
      <input type="hidden" name="store_id" value="<?php echo htmlspecialchars($storeId); ?>">
      <div class="form-group">
        <label>이메일</label>
        <input type="email" name="email" required autocomplete="email"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
      <div class="form-group">
        <label>비밀번호</label>
        <input type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-primary">로그인</button>
    </form>
    <?php endif; ?>
    <div class="auth-links">매장 대표이신가요? <a href="index.php">대표자 로그인</a></div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/floating_chat.php'; ?>
</body>
</html>
