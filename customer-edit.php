<?php
/**
 * customer-edit.php
 * 고객 정보(이름/전화번호/성별/메모) 수정 화면
 * 대표/관리자/일반 직원 모두 접근 가능 (requireStoreAccess 기준) — 수정은 삭제와 달리 되돌릴 수 있는 작업이라 직원도 허용
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/staff_auth.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/utils/Crypto.php';
require_once __DIR__ . '/api/utils/Mask.php';

$pdo = getDbConnection();

$storeId    = $_GET['id'] ?? ($_POST['id'] ?? '');
$customerId = $_GET['customer_id'] ?? ($_POST['customer_id'] ?? '');

if ($storeId === '' || $customerId === '') {
    http_response_code(400);
    die('필수 파라미터(id, customer_id)가 없습니다.');
}

$actor = requireStoreAccess($pdo, $storeId);

$stmt = $pdo->prepare('SELECT id, name FROM ss_stores WHERE id = ?');
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) { http_response_code(404); die('매장을 찾을 수 없거나 접근 권한이 없습니다.'); }

$stmt = $pdo->prepare('SELECT * FROM ss_customers WHERE id = ? AND store_id = ? LIMIT 1');
$stmt->execute([$customerId, $storeId]);
$customer = $stmt->fetch();
if (!$customer) { http_response_code(404); die('고객 정보를 찾을 수 없습니다.'); }

$errorMsg = '';
$fieldErrors = [];

// 최초 진입 시 폼에 채워줄 값 (POST 실패 후에는 입력했던 값 유지)
$formName   = $customer['name'];
$formPhone  = Crypto::decrypt($customer['phone_encrypted'] ?? '');
$formGender = $customer['gender'] ?? 'unknown';
$formMemo   = $customer['memo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formName   = trim($_POST['name'] ?? '');
    $formPhone  = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $formGender = $_POST['gender'] ?? 'unknown';
    $memoRaw    = $_POST['memo'] ?? '';
    $formMemo   = strip_tags($memoRaw, '<b><i><u><s><ul><ol><li><br><p><div><span>');

    if (!$formName || mb_strlen($formName) > 100) {
        $fieldErrors['name'] = '이름을 정확히 입력해주세요.';
    }
    if (strlen($formPhone) < 9 || strlen($formPhone) > 11) {
        $fieldErrors['phone'] = '휴대폰 번호 형식이 올바르지 않습니다.';
    }
    if (!in_array($formGender, ['female', 'male', 'unknown'], true)) {
        $fieldErrors['gender'] = '성별을 선택해주세요.';
    }

    if (!$fieldErrors) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE ss_customers
                 SET name = ?, phone_encrypted = ?, phone_masked = ?, gender = ?, memo = ?
                 WHERE id = ? AND store_id = ?'
            );
            $stmt->execute([
                $formName, Crypto::encrypt($formPhone), Mask::phone($formPhone), $formGender, $formMemo,
                $customerId, $storeId,
            ]);
            logAccess($pdo, $storeId, $actor, 'update_customer', 'customer', $customerId, $formName);
            header('Location: store.php?id=' . urlencode($storeId) . '&updated=1');
            exit;
        } catch (Throwable $e) {
            error_log('[customer edit] ' . $e->getMessage());
            $errorMsg = '고객 정보 수정 중 오류가 발생했습니다.';
        }
    }
}

$actorRole = $actor['role'];
$activePage = 'customers';
$pageTitle = '고객 정보 수정';
require_once __DIR__ . '/includes/layout_head.php';
?>
<div class="dashboard-layout">
  <?php require __DIR__ . '/includes/store_sidebar.php'; ?>
  <main class="main-content">
    <header class="dashboard-header">
      <span><?php echo htmlspecialchars($actor['actor_name'] ?? ''); ?>님</span>
    </header>
    <div class="page-content">
      <div class="page-header"><h1 class="page-title">고객 정보 수정</h1></div>

      <?php if ($errorMsg): ?><div class="alert-error"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

      <div class="form-page-card">
        <form method="post" id="customerForm">
          <input type="hidden" name="id" value="<?php echo htmlspecialchars($storeId); ?>">
          <input type="hidden" name="customer_id" value="<?php echo htmlspecialchars($customerId); ?>">

          <div class="form-group">
            <label>이름 *</label>
            <input type="text" name="name" placeholder="고객 이름" required
                   value="<?php echo htmlspecialchars($formName); ?>">
            <?php if (!empty($fieldErrors['name'])): ?><p style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($fieldErrors['name']); ?></p><?php endif; ?>
          </div>

          <div class="form-group">
            <label>휴대폰 번호 *</label>
            <input type="text" name="phone" placeholder="010-1234-5678" required
                   value="<?php echo htmlspecialchars($formPhone); ?>">
            <?php if (!empty($fieldErrors['phone'])): ?><p style="color:var(--danger);font-size:12px;margin-top:4px;"><?php echo htmlspecialchars($fieldErrors['phone']); ?></p><?php endif; ?>
          </div>

          <div class="form-group">
            <label>성별 *</label>
            <div class="gender-toggle">
              <button type="button" class="gender-btn <?php echo $formGender === 'male' ? 'selected' : ''; ?>" data-value="male" onclick="selectGender(this)">남자</button>
              <button type="button" class="gender-btn <?php echo $formGender === 'female' ? 'selected' : ''; ?>" data-value="female" onclick="selectGender(this)">여자</button>
            </div>
            <input type="hidden" name="gender" id="genderInput" value="<?php echo htmlspecialchars($formGender); ?>">
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
            <div class="editor-body" id="memoEditor" contenteditable="true" data-placeholder="고객 관련 메모를 입력해주세요"><?php echo $formMemo; ?></div>
            <input type="hidden" name="memo" id="memoInput">
          </div>

          <div class="form-actions">
            <button type="button" class="btn-secondary" onclick="location.href='store.php?id=<?php echo urlencode($storeId); ?>'">취소</button>
            <button type="submit" class="btn-primary" onclick="return syncMemo()">수정 완료</button>
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
