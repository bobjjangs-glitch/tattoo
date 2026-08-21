<?php
$title = isset($_GET['title']) ? trim((string)$_GET['title']) : '이 페이지';
if ($title === '' || mb_strlen($title) > 60) {
    $title = '이 페이지';
}

$pageTitle       = htmlspecialchars($title) . ' - CareForm';
$pageDescription = 'CareForm 콘텐츠 준비 중 안내';
$activeNav       = '';
require_once __DIR__ . '/includes/landing_header.php';
?>
<div class="wrap">
  <div class="content-section" style="text-align:center;padding:80px 0;border-top:none;">
    <h2 style="font-size:22px;margin-top:0;"><?php echo htmlspecialchars($title); ?>는 준비 중입니다</h2>
    <p style="color:var(--text-sub);">더 도움이 되는 내용으로 채워서 곧 공개하겠습니다.</p>
    <a href="landing.php" class="btn-primary-lg" style="margin-top:20px;display:inline-block;">홈으로 돌아가기 →</a>
  </div>
</div>
<?php require_once __DIR__ . '/includes/landing_footer.php'; ?>
