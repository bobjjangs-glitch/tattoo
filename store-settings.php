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
$certSuccess = '';
$staffError = '';
$staffSuccess = '';

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
            if (!empty($store['business_cert_path'])) {
                $oldPath = __DIR__ . '/' . $store['business_cert_path'];
                if (is_file($oldPath)) { @unlink($oldPath); }
            }
            $newName = 'cert_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                $newRelPath = 'uploads/business-certs/' . $storeId . '/' . $newName;
                $stmt = $pdo->prepare('UPDATE ss_stores SET business_cert_path = ? WHERE id = ?');
                $stmt->execute([$newRelPath, $storeId]);
                $store['business_cert_path'] = $newRelPath;
                $certSuccess = '사업자등록증이 등록되었습니다.';
            } else {
                $certError = '파일 저장에 실패했습니다.';
            }
        }
    }
}

// ── 직원 추가 ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_staff') {
    $name = trim($_POST['staff_name'] ?? '');
    $email = trim($_POST['staff_email'] ?? '');
    $pw = $_POST['staff_password'] ?? '';
    $role = ($_POST['staff_role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';

    if ($name === '' || $email === '' || strlen($pw) < 4) {
        $staffError = '이름, 이메일, 4자리 이상 비밀번호를 모두 입력해주세요.';
    } else {
        try {
            $dupStmt = $pdo->prepare('SELECT id FROM ss_store_staff WHERE store_id = ? AND email = ?');
            $dupStmt->execute([$storeId, $email]);
            if ($dupStmt->fetch()) {
                $staffError = '이미 등록된 이메일입니다.';
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO ss_store_staff (id, store_id, name, email, password_hash, role, is_active, created_at)
                     VALUES (UUID(), ?, ?, ?, ?, ?, 1, NOW())'
                );
                $ins->execute([$storeId, $name, $email, password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]), $role]);
                $staffSuccess = '직원이 추가되었습니다.';
            }
        } catch (Throwable $e) {
            $staffError = '직원 테이블이 아직 준비되지 않았습니다. SQL을 먼저 실행해주세요.';
        }
    }
}

// ── 직원 활성/비활성 토글 ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_staff') {
    $staffId = $_POST['staff_id'] ?? '';
    try {
        $stmt = $pdo->prepare('UPDATE ss_store_staff SET is_active = 1 - is_active WHERE id = ? AND store_id = ?');
        $stmt->execute([$staffId, $storeId]);
    } catch (Throwable $e) {
        $staffError = '상태 변경에 실패했습니다.';
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

// ── 조회: 직원 목록 / 접근 로그 (테이블 없어도 화면이 죽지 않게 방어) ──
function safeFetchAllLocal(PDO $pdo, string $sql, array $params): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
$staffList = safeFetchAllLocal($pdo,
    'SELECT id, name, email, role, is_active, created_at FROM ss_store_staff WHERE store_id = ? ORDER BY created_at DESC',
    [$storeId]);
$accessLogs = safeFetchAllLocal($pdo,
    'SELECT actor_name, actor_type, action, target_type, detail, created_at FROM ss_access_logs WHERE store_id = ? ORDER BY created_at DESC LIMIT 50',
    [$storeId]);

$hasCert = !empty($store['business_cert_path']);
$certExt = $hasCert ? strtolower(pathinfo($store['business_cert_path'], PATHINFO_EXTENSION)) : '';
$certIsImage = in_array($certExt, ['jpg', 'jpeg', 'png'], true);

$activePage = 'settings';
$actorRole = 'owner';
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
        <p class="section-desc">사업자등록증 이미지 또는 PDF 파일을 등록해 관리할 수 있습니다.</p>
        <?php if ($certError): ?><div class="alert-error"><?php echo htmlspecialchars($certError); ?></div><?php endif; ?>
        <?php if ($certSuccess): ?><div class="alert-success"><?php echo htmlspecialchars($certSuccess); ?></div><?php endif; ?>

        <?php if ($hasCert): ?>
          <div class="cert-card">
            <div class="cert-card-icon"><?php echo $certIsImage ? '🖼️' : '📄'; ?></div>
            <div class="cert-card-info">
              <span class="cert-status-badge">등록완료</span>
              <span class="cert-card-filename"><?php echo htmlspecialchars(basename($store['business_cert_path'])); ?></span>
            </div>
            <div class="cert-card-actions">
              <a href="<?php echo htmlspecialchars($store['business_cert_path']); ?>" target="_blank" class="btn-mini">파일 보기</a>
              <label class="btn-mini" for="certInput" style="cursor:pointer;">다시 업로드</label>
            </div>
          </div>
          <form method="post" enctype="multipart/form-data" style="display:none;">
            <input type="hidden" name="action" value="upload_cert">
            <input type="file" name="cert" id="certInput" accept=".jpg,.jpeg,.png,.pdf" onchange="this.form.submit()">
          </form>
        <?php else: ?>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_cert">
            <label class="upload-box" for="certInput">
              <div class="upload-icon" aria-hidden="true">📤</div>
              <div class="upload-text">클릭하거나 파일을 여기에 놓으세요</div>
              <div class="upload-sub">이미지 또는 PDF (최대 10MB)</div>
              <input type="file" name="cert" id="certInput" accept=".jpg,.jpeg,.png,.pdf" onchange="this.form.submit()">
            </label>
          </form>
        <?php endif; ?>
      </div>

      <div class="settings-section">
        <h2>직원 관리</h2>
        <p class="section-desc">직원 계정을 추가하면 대표자 비밀번호 없이도 고객 관리 화면에 로그인할 수 있습니다. "관리자" 권한은 동의서 관리까지 접근 가능하니 신중히 부여해주세요. 각 직원의 "링크 복사" 버튼을 눌러 로그인 주소를 카카오톡이나 문자로 전달해주세요.</p>
        <?php if ($staffError): ?><div class="alert-error"><?php echo htmlspecialchars($staffError); ?></div><?php endif; ?>
        <?php if ($staffSuccess): ?><div class="alert-success"><?php echo htmlspecialchars($staffSuccess); ?></div><?php endif; ?>

        <?php if (empty($staffList)): ?>
          <div class="empty-state" style="padding:30px 20px;">등록된 직원이 없습니다.</div>
        <?php else: ?>
          <table class="data-table" style="margin-bottom:18px;">
            <thead><tr><th>이름</th><th>이메일</th><th>권한</th><th>상태</th><th>로그인 링크</th><th>관리</th></tr></thead>
            <tbody>
              <?php foreach ($staffList as $s): ?>
                <?php $staffLoginUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/tattoo/staff-login.php?store_id=' . urlencode($storeId); ?>
                <tr>
                  <td><?php echo htmlspecialchars($s['name']); ?></td>
                  <td><?php echo htmlspecialchars($s['email']); ?></td>
                  <td><?php echo $s['role'] === 'admin' ? '관리자' : '일반 직원'; ?></td>
                  <td><span class="badge <?php echo $s['is_active'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $s['is_active'] ? '활성' : '비활성'; ?></span></td>
                  <td>
                    <button type="button" class="btn-mini" onclick="copyStaffLink('<?php echo htmlspecialchars($staffLoginUrl, ENT_QUOTES); ?>', this)">링크 복사</button>
                  </td>
                  <td>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="toggle_staff">
                      <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($s['id']); ?>">
                      <button type="submit" class="btn-mini"><?php echo $s['is_active'] ? '비활성화' : '활성화'; ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <form method="post" style="max-width:420px;">
          <input type="hidden" name="action" value="add_staff">
          <div class="form-group"><label>이름 *</label><input type="text" name="staff_name" required></div>
          <div class="form-group"><label>이메일 *</label><input type="email" name="staff_email" required></div>
          <div class="form-group"><label>비밀번호 *</label><input type="password" name="staff_password" placeholder="4자리 이상" required></div>
          <div class="form-group">
            <label>권한 *</label>
            <select name="staff_role">
              <option value="staff">일반 직원 (고객 관리만 가능)</option>
              <option value="admin">관리자 (동의서 관리까지 가능)</option>
            </select>
          </div>
          <button type="submit" class="btn-primary" style="width:auto;padding:12px 28px;">직원 추가</button>
        </form>
      </div>

      <div class="settings-section">
        <h2>접근 로그</h2>
        <p class="section-desc">최근 50건의 접근·처리 기록입니다.</p>
        <?php if (empty($accessLogs)): ?>
          <div class="empty-state" style="padding:30px 20px;">아직 기록이 없습니다.</div>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>일시</th><th>수행자</th><th>구분</th><th>동작</th></tr></thead>
            <tbody>
              <?php foreach ($accessLogs as $log): ?>
                <tr>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($log['created_at']))); ?></td>
                  <td><?php echo htmlspecialchars($log['actor_name']); ?></td>
                  <td><?php echo $log['actor_type'] === 'owner' ? '대표' : '직원'; ?></td>
                  <td><?php echo htmlspecialchars($log['action']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
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

<script>
function copyStaffLink(url, btn) {
    navigator.clipboard.writeText(url).then(function () {
        const original = btn.textContent;
        btn.textContent = '복사됨!';
        setTimeout(function () { btn.textContent = original; }, 1500);
    });
}
</script>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
