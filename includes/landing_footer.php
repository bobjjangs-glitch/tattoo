<?php
/**
 * includes/landing_footer.php
 * 랜딩 계열 공통 푸터. 사업자 정보는 ss_platform_settings 테이블에서 조회하며,
 * 값이 없으면(관리자가 아직 입력 안 함) 해당 항목은 화면에 표시하지 않는다.
 * 호출 페이지가 이미 $pdo 를 만들어둔 경우 재사용하고, 없으면 자체적으로 연결을 시도한다.
 */
$footerBiz = [
    'company_name'    => 'CareForm',
    'ceo_name'        => '',
    'biz_reg_no'      => '',
    'mail_order_no'   => '',
    'company_email'   => '',
    'company_address' => '',
];

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../api/config/database.php';
        $pdo = getDbConnection();
    }
    require_once __DIR__ . '/platform_settings.php';
    $footerBiz['company_name']    = getPlatformSetting($pdo, 'company_name', 'CareForm');
    $footerBiz['ceo_name']        = getPlatformSetting($pdo, 'ceo_name', '');
    $footerBiz['biz_reg_no']      = getPlatformSetting($pdo, 'biz_reg_no', '');
    $footerBiz['mail_order_no']   = getPlatformSetting($pdo, 'mail_order_no', '');
    $footerBiz['company_email']   = getPlatformSetting($pdo, 'company_email', '');
    $footerBiz['company_address'] = getPlatformSetting($pdo, 'company_address', '');
} catch (Throwable $e) {
    error_log('[landing_footer.php] 사업자 정보 조회 실패, 기본값 사용: ' . $e->getMessage());
}
?>
<footer class="site-footer">
  <div class="wrap footer-top">
    <div class="footer-brand">
      <div class="footer-logo"><span class="logo-mark">C</span><?php echo htmlspecialchars($footerBiz['company_name']); ?></div>
      <p>뷰티 매장 전용 전자동의서 관리 시스템</p>
    </div>
    <div class="footer-col">
      <h4>서비스</h4>
      <ul>
        <li><a href="tool-consent-form.php">무료 시술동의서 생성기</a></li>
        <li><a href="coming-soon.php?title=<?php echo urlencode('블로그'); ?>">블로그</a></li>
        <li><a href="coming-soon.php?title=<?php echo urlencode('CareForm 가이드'); ?>">CareForm 가이드</a></li>
        <li><a href="coming-soon.php?title=<?php echo urlencode('동의서 양식 가이드'); ?>">동의서 양식 가이드</a></li>
        <li><a href="coming-soon.php?title=<?php echo urlencode('환불 분쟁 대응 가이드'); ?>">환불 분쟁 대응 가이드</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>정책</h4>
      <ul>
        <li><a href="terms.php">이용약관</a></li>
        <li><a href="privacy.php">개인정보처리방침</a></li>
      </ul>
    </div>
  </div>

  <?php
    $hasBizInfo = $footerBiz['biz_reg_no'] || $footerBiz['mail_order_no'] || $footerBiz['ceo_name'] || $footerBiz['company_email'] || $footerBiz['company_address'];
  ?>
  <?php if ($hasBizInfo): ?>
  <div class="wrap footer-bizinfo">
    <?php if ($footerBiz['biz_reg_no']): ?><span><strong>사업자등록번호</strong> <?php echo htmlspecialchars($footerBiz['biz_reg_no']); ?></span><?php endif; ?>
    <?php if ($footerBiz['mail_order_no']): ?><span><strong>통신판매업신고</strong> <?php echo htmlspecialchars($footerBiz['mail_order_no']); ?></span><?php endif; ?>
    <?php if ($footerBiz['ceo_name']): ?><span><strong>대표자</strong> <?php echo htmlspecialchars($footerBiz['ceo_name']); ?></span><?php endif; ?>
    <?php if ($footerBiz['company_email']): ?><span><strong>이메일</strong> <?php echo htmlspecialchars($footerBiz['company_email']); ?></span><?php endif; ?>
    <?php if ($footerBiz['company_address']): ?><span><strong>주소</strong> <?php echo htmlspecialchars($footerBiz['company_address']); ?></span><?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="wrap footer-bottom">
    © <?php echo date('Y'); ?> <?php echo htmlspecialchars($footerBiz['company_name']); ?>. All rights reserved.
  </div>
</footer>

<script>
document.querySelectorAll('.faq-question').forEach(function (btn) {
  var item = btn.closest('.faq-item');
  var wrap = item.querySelector('.faq-answer-wrap');
  if (item.classList.contains('is-open')) {
    wrap.style.maxHeight = wrap.scrollHeight + 'px';
  }
  btn.addEventListener('click', function () {
    var isOpen = item.classList.contains('is-open');
    if (isOpen) {
      item.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
      wrap.style.maxHeight = '0px';
    } else {
      item.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
      wrap.style.maxHeight = wrap.scrollHeight + 'px';
    }
  });
});

(function () {
  var toggle = document.getElementById('navToggle');
  var panel = document.getElementById('mobilePanel');
  if (!toggle || !panel) return;
  toggle.addEventListener('click', function () {
    var isOpen = panel.classList.toggle('is-open');
    toggle.classList.toggle('is-open', isOpen);
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });
  panel.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () {
      panel.classList.remove('is-open');
      toggle.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
    });
  });
})();

(function () {
  var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var targets = document.querySelectorAll('.reveal');
  if (prefersReduced || !('IntersectionObserver' in window)) {
    targets.forEach(function (el) { el.classList.add('is-visible'); });
    return;
  }
  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  targets.forEach(function (el) { observer.observe(el); });
})();
</script>
</body>
</html>
