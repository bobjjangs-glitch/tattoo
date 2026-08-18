<?php
/**
 * consent-select.php
 * 고객관리 > 동의서 작성 > 템플릿 선택 화면
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/api/config/database.php';

$user = requireLogin();
$pdo  = getDbConnection();

$storeId    = $_GET['id'] ?? '';
$customerId = $_GET['customer_id'] ?? '';

if ($storeId === '' || $customerId === '') {
    http_response_code(400);
    die('필수 파라미터(id, customer_id)가 없습니다.');
}

// 매장 확인
$stmt = $pdo->prepare("SELECT * FROM ss_stores WHERE id = ? LIMIT 1");
$stmt->execute([$storeId]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$store) {
    http_response_code(404);
    die('매장 정보를 찾을 수 없습니다.');
}

// 고객 확인
$stmt = $pdo->prepare("SELECT * FROM ss_customers WHERE id = ? AND store_id = ? LIMIT 1");
$stmt->execute([$customerId, $storeId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    http_response_code(404);
    die('고객 정보를 찾을 수 없습니다.');
}

// 활성화된 동의서 템플릿 목록
$stmt = $pdo->prepare("
    SELECT id, title, industry, version
    FROM ss_consent_templates
    WHERE store_id = ? AND is_active = 1
    ORDER BY created_at ASC
");
$stmt->execute([$storeId]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

function industryBadge($industry) {
    $map = [
        'tattoo'   => '타투',
        'common'   => '공통',
        'perm'     => '반영구',
        'piercing' => '피어싱',
    ];
    return $map[$industry] ?? $industry;
}

$pageTitle = '동의서 작성';
require_once __DIR__ . '/includes/flow_head.php';
?>
<div class="consent-flow-page">
    <div class="consent-flow-topbar">
        <a href="store.php?id=<?= urlencode($storeId) ?>" class="btn-back">‹ 위로</a>
        <div class="consent-flow-topbar-right">
            <button type="button" class="btn-info"
                onclick="alert('작성 중인 동의서는 저장되지 않으며, 서명 완료 후에만 고객 기록에 남습니다.')">
                🔒 정보
            </button>
            <a href="store.php?id=<?= urlencode($storeId) ?>" class="btn-exit">↪ 나가기</a>
        </div>
    </div>

    <div class="consent-flow-body">
        <h1 class="consent-flow-title">동의서 작성</h1>
        <p class="consent-flow-subtitle">동의서를 선택해주세요</p>

        <?php if (empty($templates)): ?>
            <div class="alert alert-error">
                등록된 동의서 템플릿이 없습니다. 먼저 [동의서 관리]에서 템플릿을 생성해주세요.
            </div>
        <?php else: ?>
            <div class="consent-select-list">
                <?php foreach ($templates as $tpl): ?>
                    <a class="consent-select-card"
                       href="consent-sign.php?id=<?= urlencode($storeId) ?>&customer_id=<?= urlencode($customerId) ?>&template_id=<?= urlencode($tpl['id']) ?>">
                        <span class="consent-select-title">
                            <?= htmlspecialchars($tpl['title']) ?>
                            <span class="badge badge-industry"><?= htmlspecialchars(industryBadge($tpl['industry'])) ?></span>
                        </span>
                        <span class="consent-select-arrow">›</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/flow_foot.php'; ?>
