<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';

$pdo = getDbConnection();
$storeId = $_GET['id'] ?? '';
$actor = requireStoreAccess($pdo, $storeId);
requireAdminRole($actor); // 매장 설정은 오너/관리자 권한 직원만 접근 가능

$stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ?');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) { http_response_code(404); die('매장을 찾을 수 없습니다.'); }

$pwError = ''; $pwSuccess = ''; $certError = ''; $certSuccess = '';
$staffError = ''; $staffSuccess = '';

// ── 관리자 비밀번호 변경 (기존 기능 유지) ──
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
        $pdo->prepare('UPDATE ss_stores SET admin_password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]), $storeId]);
        $pwSuccess = '비밀번호가 변경되었습니다.';
        logAccess($pdo, $storeId, $actor, 'change_admin_password');
        $stmt = $pdo->prepare('SELECT * FROM ss_stores WHERE id = ?');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();
    }
}

// ── 사업자등록증 업로드 (기존 기능 유지) ──
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
                $pdo->prepare('UPDATE ss_stores SET business_cert_path = ? WHERE id = ?')->execute([$newRelPath, $storeId]);
                $store['business_cert_path'] = $newRelPath;
                $certSuccess = '사업자등록증이 등록되었습니다.';
                logAccess($pdo, $storeId, $actor, 'upload_business_cert');
            } else {
                $certError = '파일 저장에 실패했습니다.';
            }
        }
    }
}

// ── 직원 추가 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_staff') {
    $sName = trim($_POST['staff_name'] ?? '');
    $sEmail = trim($_POST['staff_email'] ?? '');
    $sPw = $_POST['staff_password'] ?? '';
    $sRole = ($_POST['staff_role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';

    if (!$sName || !$sEmail || strlen($sPw) < 4) {
        $staffError = '이름, 이메일, 4자리 이상 비밀번호를 모두 입력해주세요.';
    } else {
        $dup = $pdo->prepare('SELECT id FROM ss_store_staff WHERE store_id = ? AND email = ?');
        $dup->execute([$storeId, $sEmail]);
        if ($dup->fetch()) {
            $staffError = '이미 등록된 이메일입니다.';
        } else {
            $pdo->prepare(
                'INSERT INTO ss_store_staff (id, store_id, name, email, password_hash, role, is_active, created_at)
                 VALUES (UUID(), ?, ?, ?, ?, ?, 1, NOW())'
            )->execute([$storeId, $sName, $sEmail, password_hash($sPw, PASSWORD_BCRYPT, ['cost' => 12]), $sRole]);
            $staffSuccess = '직원 계정이 등록되었습니다.';
            logAccess($pdo, $storeId, $actor, 'add_staff', 'staff', null, $sEmail);
        }
    }
}

// ── 직원 활성/비활성 토글 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_staff') {
    $targetId = $_POST['staff_id'] ?? '';
    $stmt = $pdo->prepare('SELECT is_active FROM ss_store_staff WHERE id = ? AND store_id = ?');
    $stmt->execute([$targetId, $storeId]);
    $row = $stmt->fetch();
    if ($row) {
        $newActive = $row['is_active'] ? 0 : 1;
        $pdo->prepare('UPDATE ss_store_staff SET is_active = ? WHERE id = ? AND store_id = ?')
            ->execute([$newActive, $targetId, $storeId]);
        logAccess($pdo, $storeId, $actor, $newActive ? 'activate_staff' : 'deactivate_staff', 'staff', $targetId);
        $staffSuccess = $newActive ? '직원 계정을 활성화했습니다.' : '직원 계정을 비활성화했습니다.';
    }
}

// ── 매장 삭제 (기존 기능 유지, 오너만 가능) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_store') {
    if ($actor['role'] !== 'owner') {
        $pwError = '매장 삭제는 매장 대표만 가능합니다.';
    } else {
        $confirmPw = $_POST['delete_password'] ?? '';
        if (!password_verify($confirmPw, $store['admin_password_hash'])) {
            $pwError = '매장 삭제를 위해 관리자 비밀번호를 정확히 입력해주세요.';
        } else {
            $pdo->prepare('DELETE FROM ss_stores WHERE id = ? AND owner_id = ?')->execute([$storeId, $actor['actor_id']]);
            header('Location: dashboard.php');
            exit;
        }
    }
}

$hasCert = !empty($store['business_cert_path']);
$certExt = $hasCert ? strtolower(pathinfo($store['business_cert_path'], PATHINFO_EXTENSION)) : '';
$certIsImage = in_array($certExt, ['jpg', 'jpeg', 'png'], true);

// 직원 목록 조회
$staffList = $pdo->prepare('SELECT * FROM ss_store_staff WHERE store_id = ? ORDER BY created_at DESC');
$staffList->execute([$storeId]);
$staffList = $staffList->fetchAll();

// 접근 로그 최근 50건 조회
$logStmt = $pdo->prepare('SELECT * FROM ss_access_logs WHERE store_id = ? ORDER BY created_at DESC LIMIT 50');
$logStmt->execute([$storeId]);
$accessLogs = $logStmt->fetchAll();

$actionLabels = [
    'view_customer_list'     => '고객 목록 조회',
    'change_admin_password'  => '관리자 비밀번호 변경',
    'upload_business_cert'   => '사업자등록증 업로드',
    'add_staff'               => '직원 계정 등록',
    'activate_staff'          => '직원 계정 활성화',
    'deactivate_staff'        => '직원 계정 비활성화',
];

$activePage = 'settings';
$actorRole = $actor['role'];
$pageTitle = htmlspecialchars($store['name']) . ' 매장 설정';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/store_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header"><span><?php echo htmlspecialchars($actor['actor_name']); ?>님</span></header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">매장 설정</h1></div>

      <div class="settings-section">
        <h2>관리자 비밀번호</h2>
        <p class="section-desc">관리자 비밀번호를 설정하면 고객 정보와 동의서 파일을 보호합니다.</p>
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
              <div class="upload-icon">📤</div>
              <div class="upload-text">클릭하거나 파일을 여기에 놓으세요</div>
              <div class="upload-sub">이미지 또는 PDF (최대 10MB)</div>
              <input type="file" name="cert" id="certInput" accept=".jpg,.jpeg,.png,.pdf" onchange="this.form.submit()">
            </label>
          </form>
        <?php endif; ?>
      </div>

      <div class="settings-section">
        <h2>직원 관리</h2>
        <p class="section-desc">직원별 개별 계정을 발급하면 누가 어떤 작업을 했는지 구분해 관리할 수 있습니다. 관리자 권한 직원은 매장 설정까지 접근할 수 있고, 일반 직원은 고객·동의서 관리만 가능합니다.</p>
        <?php if ($staffError): ?><div class="alert-error"><?php echo htmlspecialchars($staffError); ?></div><?php endif; ?>
        <?php if ($staffSuccess): ?><div class="alert-success"><?php echo htmlspecialchars($staffSuccess); ?></div><?php endif; ?>

        <?php if (empty($staffList)): ?>
          <p class="muted" style="margin-bottom:16px;">등록된 직원 계정이 없습니다.</p>
        <?php else: ?>
          <table class="data-table" style="margin-bottom:16px;">
            <thead><tr><th>이름</th><th>이메일</th><th>권한</th><th>상태</th><th>최근 로그인</th><th>관리</th></tr></thead>
            <tbody>
              <?php foreach ($staffList as $s): ?>
                <tr>
                  <td><?php echo htmlspecialchars($s['name']); ?></td>
                  <td><?php echo htmlspecialchars($s['email']); ?></td>
                  <td><?php echo $s['role'] === 'admin' ? '관리자' : '일반 직원'; ?></td>
                  <td>
                    <span class="status-badge <?php echo $s['is_active'] ? 'active' : 'suspended'; ?>">
                      <?php echo $s['is_active'] ? '활성' : '비활성'; ?>
                    </span>
                  </td>
                  <td><?php echo $s['last_login_at'] ? htmlspecialchars(date('Y.n.j H:i', strtotime($s['last_login_at']))) : '-'; ?></td>
                  <td>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="action" value="toggle_staff">
                      <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($s['id']); ?>">
                      <button type="submit" class="btn-mini <?php echo $s['is_active'] ? 'danger' : ''; ?>">
                        <?php echo $s['is_active'] ? '비활성화' : '활성화'; ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <button type="button" class="btn-secondary" style="width:auto;padding:10px 20px;"
          onclick="document.getElementById('addStaffModal').style.display='flex'">+ 직원 추가</button>
      </div>

      <div class="settings-section">
        <h2>접근 로그</h2>
        <p class="section-desc">최근 50건의 접속·작업 기록입니다. 누가 언제 어떤 작업을 했는지 확인할 수 있습니다.</p>
        <?php if (empty($accessLogs)): ?>
          <p class="muted">아직 기록된 로그가 없습니다.</p>
        <?php else: ?>
          <table class="data-table">
            <thead><tr><th>일시</th><th>작업자</th><th>구분</th><th>작업 내용</th><th>비고</th></tr></thead>
            <tbody>
              <?php foreach ($accessLogs as $log): ?>
                <tr>
                  <td><?php echo htmlspecialchars(date('Y.n.j H:i:s', strtotime($log['created_at']))); ?></td>
                  <td><?php echo htmlspecialchars($log['actor_name']); ?></td>
                  <td><?php echo $log['actor_type'] === 'owner' ? '대표' : '직원'; ?></td>
                  <td><?php echo htmlspecialchars($actionLabels[$log['action']] ?? $log['action']); ?></td>
                  <td><?php echo htmlspecialchars($log['detail'] ?? ''); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <?php if ($actor['role'] === 'owner'): ?>
      <div class="settings-section danger-zone">
        <h2>매장 삭제</h2>
        <p class="section-desc">매장 삭제 시 고객 정보, 동의서, 캘린더 등 모든 데이터가 삭제됩니다.<br>이 작업은 되돌릴 수 없습니다.</p>
        <button type="button" class="btn-danger-outline" onclick="document.getElementById('deleteModal').style.display='flex'">매장 삭제</button>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<div class="modal-overlay" id="addStaffModal" style="display:none;">
  <div class="modal-box">
    <h2 class="modal-title">직원 계정 추가</h2>
    <form method="post">
      <input type="hidden" name="action" value="add_staff">
      <div class="form-group"><label>이름 *</label><input type="text" name="staff_name" required></div>
      <div class="form-group"><label>이메일 *</label><input type="email" name="staff_email" required></div>
      <div class="form-group"><label>비밀번호 *</label><input type="password" name="staff_password" placeholder="4자리 이상" required></div>
      <div class="form-group">
        <label>권한</label>
        <select name="staff_role">
          <option value="staff">일반 직원 (고객·동의서 관리만 가능)</option>
          <option value="admin">관리자 (매장 설정까지 접근 가능)</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="document.getElementById('addStaffModal').style.display='none'">취소</button>
        <button type="submit" class="btn-primary">등록</button>
      </div>
    </form>
  </div>
</div>

<?php if ($actor['role'] === 'owner'): ?>
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
<?php endif; ?>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
