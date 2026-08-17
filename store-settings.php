<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';

$user = requireLogin();
$pdo = getDbConnection();

$storeId = $_GET['id'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ? AND owner_id = ?');
$stmt->execute([$storeId, $user['id']]);
$store = $stmt->fetch();
if (!$store) { http_response_code(404); die('매장을 찾을 수 없거나 접근 권한이 없습니다.'); }

$pwError = '';
$pwSuccess = '';
$certError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPw = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPw, $store['admin_password_hash'])) {
        $pwError = '현재 비밀번호가 일치하지 않습니다.';
    } elseif (strlen($newPw) < 4) {
        $pwError = '새 비밀번호는 4자리 이상이어야 합니다.';
    } elseif ($newPw !== $confirmPw) {
        $pwError = '새 비밀번호가 일치하지 않습니다.';
    } else {
        $stmt = $pdo->prepare('UPDATE ss_stores SET admin_password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]), $storeId]);
        $pwSuccess = '비밀번호가 변경되었습니다.';
        $stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ?');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_cert' && !empty($_FILES['cert']['name'])) {
    $file = $_FILES['cert'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        $certError = 'jpg, png, pdf 파일만 업로드할 수 있습니다.';
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $certError = '파일 크기는 10MB 이하만 가능합니다.';
    } else {
        $uploadDir = __DIR__ . '/uploads/business-certs/' . $storeId . '/';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            $certError = '업로드 폴더 권한이 없습니다.';
        } else {
            $newName = 'cert_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                $stmt = $pdo->prepare('UPDATE ss_stores SET business_cert_path = ? WHERE id = ?');
                $stmt->execute(['uploads/business-certs/' . $storeId . '/' . $newName, $storeId]);
                $store['business_cert_path'] = 'uploads/business-certs/' . $storeId . '/' . $newName;
            } else {
                $certError = '파일 저장에 실패했습니다.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_store') {
    $confirmPw = $_POST['delete_password'] ?? '';
    if (!password_verify($confirmPw, $store['admin_password_hash'])) {
        $pwError = '매장 삭제를 위해 관리자 비밀번호를 정확히 입력해주세요.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM ss_stores WHERE id = ? AND owner_id = ?');
        $stmt->execute([$storeId, $user['id']]);
        header('Location: dashboard.php');
        exit;
    }
}

$activePage = 'settings';
$pageTitle = htmlspecialchars($store['name']) . ' 매장 설정';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/store_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($user['name'] ?? ''); ?>님</span></header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">매장 설정</h1></div>

      <div class="settings-section">
        <h2>관리자 비밀번호</h2>
        <p class="section-desc">관리자 비밀번호를 설정하면 고객 신뢰번호와 동의서 파일을 보호합니다. 비밀번호를 입력해야만 전체 데이터를 열람할 수 있습니다.</p>
        <?php if ($pwError): ?><div class="alert-error"><?php echo htmlspecialchars($pwError); ?></div><?php endif; ?>
        <?php if ($pwSuccess): ?><div class="alert-success"><?php echo htmlspecialchars($pwSuccess); ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="change_password">
          <div class="form-group"><label>현재 비밀번호 *</label><input type="password" name="current_password" required></div>
          <div class="form-group"><label>새 비밀번호 *</label><input type="password" name="new_password" placeholder="4자리 이상" required></div>
          <div class="form-group"><label>비밀번호 확인 *</label><input type="password" name="confirm_password" required></div>
          <button type="submit" class="btn-primary" style="width:auto;padding:12px 28px;">비밀번호 변경</button>
        </form>
      </div>

      <div class="settings-section">
        <h2>사업자등록증</h2>
        <p class="section-desc">사업자등록증을 관리할 수 있습니다.</p>
        <?php if ($certError): ?><div class="alert-error"><?php echo htmlspecialchars($certError); ?></div><?php endif; ?>
        <?php if (!empty($store['business_cert_path'])): ?>
          <p style="margin-bottom:14px;"><a href="<?php echo htmlspecialchars($store['business_cert_path']); ?>" target="_blank" class="btn-secondary" style="display:inline-block;">📄 등록된 파일 보기</a></p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload_cert">
          <label class="upload-box" for="certInput">
            <div class="upload-icon">📤</div>
            <div class="upload-text">클릭하거나 파일을 여기에 놓으세요</div>
            <div class="upload-sub">이미지 또는 PDF (최대 10MB)</div>
            <input type="file" name="cert" id="certInput" accept=".jpg,.jpeg,.png,.pdf" onchange="this.form.submit()">
          </label>
        </form>
      </div>

      <div class="settings-section danger-zone">
        <h2>매장 삭제</h2>
        <p class="section-desc">매장 삭제 시 고객 정보, 동의서, 캘린더 등 모든 데이터가 삭제됩니다.<br>이 작업은 되돌릴 수 없습니다.</p>
        <button type="button" class="btn-danger-outline" onclick="document.getElementById('deleteModal').style.display='flex'">매장 삭제</button>
      </div>
    </div>
  </main>
</div>

<div class="modal-overlay" id="deleteModal" style="display:none;">
  <div class="modal-box">
    <h2 class="modal-title" style="color:var(--danger);">정말 매장을 삭제하시겠습니까?</h2>
    <p style="font-size:13px;color:var(--text-sub);margin-bottom:16px;">삭제를 진행하려면 관리자 비밀번호를 입력해주세요. 이 작업은 되돌릴 수 없습니다.</p>
    <form method="post">
      <input type="hidden" name="action" value="delete_store">
      <div class="form-group"><label>관리자 비밀번호</label><input type="password" name="delete_password" required></div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('deleteModal').style.display='none'">취소</button>
        <button type="submit" class="btn-danger-outline" style="flex:1;">삭제 확정</button>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
