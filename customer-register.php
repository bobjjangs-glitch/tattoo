<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Uuid.php';
require_once __DIR__ . '/api/utils/Crypto.php';
require_once __DIR__ . '/api/utils/Mask.php';
require_once __DIR__ . '/includes/plan_guard.php';

$pdo = getDbConnection();

$storeId = $_GET['id'] ?? '';
if ($storeId === '') {
    header('Location: dashboard.php');
    exit;
}

$actor = requireStoreAccess($pdo, $storeId);

$stmt = $pdo->prepare('SELECT id, name, plan_status, trial_ends_at FROM ss_stores WHERE id = ?');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) { http_response_code(404); die('매장을 찾을 수 없거나 접근 권한이 없습니다.'); }

enforcePlanAccess($store);

$errorMsg = '';
$fieldErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? 'unknown';
    $memoRaw = $_POST['memo'] ?? '';
    $memo = strip_tags($memoRaw, '<b><i><u><s><ul><ol><li><br><p><div><span>');

    if (!$name || mb_strlen($name) > 100) {
        $fieldErrors['name'] = '이름을 정확히 입력해주세요.';
    }
    if (strlen($phone) < 9 || strlen($phone) > 11) {
        $fieldErrors['phone'] = '휴대폰 번호 형식이 올바르지 않습니다.';
    }
    if (!in_array($gender, ['female', 'male', 'unknown'], true)) {
        $fieldErrors['gender'] = '성별을 선택해주세요.';
    }

    if (!$fieldErrors) {
        try {
            $id = Uuid::v4();
            $stmt = $pdo->prepare(
                'INSERT INTO ss_customers (id, store_id, name, phone_encrypted, phone_masked, gender, memo, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$id, $storeId, $name, Crypto::encrypt($phone), Mask::phone($phone), $gender, $memo]);
            logAccess($pdo, $storeId, $actor, 'register_customer', 'customer', $id, $name);
            header('Location: store.php?id=' . urlencode($storeId) . '&created=1');
            exit;
        } catch (Throwable $e) {
            error_log('[customer register] ' . $e->getMessage());
            $errorMsg = '고객 등록 중 오류가 발생했습니다.';
        }
    }
}

$actorRole = $actor['role'];
$activePage = 'customers';
$pageTitle = '고객 등록';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/store_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header">
      <span><?php echo htmlspecialchars($actor['actor_name'] ?? ''); ?>님</span>
    </header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">고객 등록</h1></div>

      <?php if ($errorMsg): ?><div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

      <div class="form-page-card">
        <form method="post" id="customerForm">
          <div class="form-group">
            <label>이름 *</label>
            <input type="text" name="name" placeholder="고객 이름" required
                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            <?php if (!empty($fieldErrors['name'])): ?><p style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($fieldErrors['name']); ?></p><?php endif; ?>
          </div>

          <div class="form-group">
            <label>휴대폰 번호 *</label>
            <input type="text" name="phone" placeholder="010-1234-5678" required
                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            <?php if (!empty($fieldErrors['phone'])): ?><p style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($fieldErrors['phone']); ?></p><?php endif; ?>
          </div>

          <div class="form-group">
            <label>성별 *</label>
            <div class="gender-toggle">
              <button type="button" class="gender-btn" data-value="male" onclick="selectGender(this)">남자</button>
              <button type="button" class="gender-btn" data-value="female" onclick="selectGender(this)">여자</button>
            </div>
            <input type="hidden" name="gender" id="genderInput" value="unknown">
            <?php if (!empty($fieldErrors['gender'])): ?><p style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($fieldErrors['gender']); ?></p><?php endif; ?>
          </div>

          <div class="form-group">
            <label>메모</label>
            <div class="editor-toolbar">
              <button type="button" onclick="ex('bold')"><b>B</b></button>
              <button type="button" onclick="ex('italic')"><i>I</i></button>
              <button type="button" onclick="ex('underline')"><u>U</u></button>
              <button type="button" onclick="ex('strikeThrough')"><s>S</s></button>
              <button type="button" onclick="ex('justifyLeft')">≡</button>
              <button type="button" onclick="ex('justifyCenter')">≣</button>
              <button type="button" onclick="ex('insertUnorderedList')">• List</button>
              <button type="button" onclick="ex('undo')">↺</button>
              <button type="button" onclick="ex('redo')">↻</button>
            </div>
            <div class="editor-body" id="memoEditor" contenteditable="true" data-placeholder="고객 관련 메모를 입력해주세요"></div>
            <input type="hidden" name="memo" id="memoInput">
          </div>

          <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="location.href='store.php?id=<?php echo urlencode($storeId); ?>'">취소</button>
            <button type="submit" class="btn-primary" onclick="return syncMemo()">고객 등록</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>
<script>
function selectGender(btn) {
  document.querySelectorAll('.gender-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('genderInput').value = btn.dataset.value;
}
function ex(cmd) { document.execCommand(cmd, false, null); document.getElementById('memoEditor').focus(); }
function syncMemo() {
  document.getElementById('memoInput').value = document.getElementById('memoEditor').innerHTML;
  return true;
}
</script>
<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
