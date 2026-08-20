<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/utils/Uuid.php';

$admin = requireAdminLogin();
$pdo = getDbConnection();

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_admin') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pw = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($pw) < 8) {
        $errorMsg = '이름, 이메일, 8자 이상 비밀번호를 모두 입력해주세요.';
    } else {
        try {
            $dup = $pdo->prepare('SELECT id FROM ss_admin_users WHERE email = ?');
            $dup->execute([$email]);
            if ($dup->fetch()) {
                $errorMsg = '이미 등록된 이메일입니다.';
            } else {
                $id = Uuid::v4();
                $pdo->prepare('INSERT INTO ss_admin_users (id, email, password_hash, name, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())')
                    ->execute([$id, $email, password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), $name]);
                $successMsg = '관리자 계정이 추가되었습니다.';
            }
        } catch (Throwable $e) {
            $errorMsg = '관리자 테이블이 준비되지 않았습니다. SQL을 먼저 실행해주세요.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_admin') {
    $targetId = $_POST['admin_id'] ?? '';
    if ($targetId === $admin['id']) {
        $errorMsg = '본인 계정은 여기서 비활성화할 수 없습니다.';
    } else {
        try {
            $pdo->prepare('UPDATE ss_admin_users SET is_active = 1 - is_active WHERE id = ?')->execute([$targetId]);
        } catch (Throwable $e) { $errorMsg = '상태 변경에 실패했습니다.'; }
    }
}

$adminList = [];
try {
    $stmt = $pdo->prepare('SELECT id, name, email, is_active, created_at, last_login_at FROM ss_admin_users ORDER BY created_at ASC');
    $stmt->execute();
    $adminList = $stmt->fetchAll();
} catch (Throwable $e) {}

$activePage = 'admins';
$pageTitle = '관리자 계정';
require_once __DIR__ . '/includes/admin_layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/admin_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($admin['name']); ?>님 (최고관리자)</span></header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">관리자 계정</h1></div>

      <?php if ($successMsg): ?><div class="alert-success"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
      <?php if ($errorMsg): ?><div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

      <table class="data-table" style="margin-bottom:20px;">
        <thead><tr><th>이름</th><th>이메일</th><th>상태</th><th>마지막 로그인</th><th>관리</th></tr></thead>
        <tbody>
          <?php foreach ($adminList as $a): ?>
            <tr>
              <td><?php echo htmlspecialchars($a['name']); ?><?php echo $a['id'] === $admin['id'] ? ' (본인)' : ''; ?></td>
              <td><?php echo htmlspecialchars($a['email']); ?></td>
              <td><span class="badge <?php echo $a['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $a['is_active'] ? '활성' : '비활성'; ?></span></td>
              <td><?php echo $a['last_login_at'] ? htmlspecialchars(substr($a['last_login_at'], 0, 16)) : '-'; ?></td>
              <td>
                <?php if ($a['id'] !== $admin['id']): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_admin">
                  <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($a['id']); ?>">
                  <button type="submit" class="btn-mini"><?php echo $a['is_active'] ? '비활성화' : '활성화'; ?></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="settings-section" style="max-width:420px;">
        <h2>관리자 추가</h2>
        <p class="section-desc">플랫폼 최고관리자 권한을 가진 계정을 추가합니다. 신중하게 부여해주세요.</p>
        <form method="post">
          <input type="hidden" name="action" value="add_admin">
          <div class="form-group"><label>이름 *</label><input type="text" name="name" required></div>
          <div class="form-group"><label>이메일 *</label><input type="email" name="email" required></div>
          <div class="form-group"><label>비밀번호 (8자 이상) *</label><input type="password" name="password" minlength="8" required></div>
          <button type="submit" class="btn-primary" style="width:auto;padding:12px 28px;">관리자 추가</button>
        </form>
      </div>
    </div>
  </main>
</div>
<?php require_once __DIR__ . '/includes/admin_layout_foot.php'; ?>
