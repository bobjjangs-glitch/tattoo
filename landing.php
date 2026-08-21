<?php
/**
 * landing.php — CareForm 홈 랜딩페이지
 * 위치: html/tattoo/landing.php
 */
$monthlyFee = 5900;
$trialDays  = 14;

try {
    require_once __DIR__ . '/api/config/database.php';
    require_once __DIR__ . '/includes/platform_settings.php';
    $pdo = getDbConnection();
    $monthlyFee = (int) getPlatformSetting($pdo, 'monthly_fee', 5900);
    $trialDays  = (int) getPlatformSetting($pdo, 'trial_days', 14);
} catch (Throwable $e) {
    error_log('[landing.php] 요금 설정 조회 실패, 기본값 사용: ' . $e->getMessage());
}

$pageTitle       = 'CareForm — 뷰티·타투 매장을 위한 전자동의서';
$pageDescription = '업종에 맞는 전자동의서를 태블릿·휴대폰으로 작성하고, 서명 기록과 고객 이력을 매장별로 관리하세요. 카드 등록 없이 무료체험을 시작할 수 있습니다.';
$activeNav       = 'home';

require_once __DIR__ . '/includes/landing_header.php';
?>

<section class="hero">
  <span class="hero-blob b1"></span>
  <span class="hero-blob b2"></span>
  <div class="wrap">
    <span class="hero-eyebrow">뷰티·타투 매장 전용 전자동의서</span>
    <h1>바쁜 매장에서도,<br><span>동의와 서명 기록</span>은 빠짐없이.</h1>
    <p class="lead">업종에 맞는 전자동의서를 태블릿이나 휴대폰으로 바로 작성하고, 서명 기록과 시술 자료를 고객별 이력으로 남기세요.</p>
    <a href="signup.php" class="btn-primary-lg">무료로 시작하기 →</a>
    <div class="hero-badges">
      <span>카드 등록 없이 <?php echo (int)$trialDays; ?>일 무료체험</span>
      <span>업종별 동의서 템플릿</span>
      <span>고객별 서명·이력 관리</span>
    </div>
  </div>
</section>

<section id="problem" class="reveal" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="wrap">
    <span class="section-eyebrow">Why CareForm</span>
    <h2 class="section-title">동의서는 받을 때보다<br>필요할 때 잘 찾아져야 합니다</h2>
    <p class="section-sub">종이를 디지털로 바꾸는 것만으로는 부족합니다. 시술 전 확인부터 나중의 조회까지 한 흐름으로 이어져야 합니다.</p>
    <div class="problem-grid">
      <div class="problem-card"><div class="num">01</div><h3>예약이 몰려도 절차는 빠뜨리지 않게</h3><p>업종에 맞게 미리 만들어둔 동의서 양식을 그대로 열어 태블릿에서 확인과 서명을 이어갑니다.</p></div>
      <div class="problem-card"><div class="num">02</div><h3>컴플레인이 오면 고객 이름으로 바로</h3><p>서명된 동의서와 서명 시각, 당시 체크리스트 응답을 고객 이력에서 즉시 확인할 수 있습니다.</p></div>
      <div class="problem-card"><div class="num">03</div><h3>직원이 바뀌어도 기록은 매장에 남게</h3><p>담당자 개인 메모가 아니라 매장 계정 기준으로 고객별 동의 이력이 정리됩니다.</p></div>
    </div>
  </div>
</section>

<section id="features" class="reveal">
  <div class="wrap">
    <span class="section-eyebrow">Features</span>
    <h2 class="section-title">CareForm이 실제로 하는 일</h2>
    <p class="section-sub">헤어·피부·네일·왁싱·속눈썹·타투까지, 매장 유형에 맞춰 동작합니다.</p>
    <div class="feature-grid">
      <div class="feature-card"><div class="icon">📄</div><h3>업종별 동의서 템플릿</h3><p>기본 양식을 제공하고, 제목·본문·체크리스트·환불 정책까지 매장에서 직접 편집할 수 있습니다.</p></div>
      <div class="feature-card"><div class="icon">🖊️</div><h3>태블릿 서명 & 고객 이력</h3><p>고객이 화면에서 직접 서명하면 서명 시각과 함께 고객별 이력에 자동으로 정리됩니다.</p></div>
      <div class="feature-card"><div class="icon">🧍</div><h3>시술 부위 도해 표시</h3><p>얼굴·두피·손발·전신 도해에서 시술 부위를 최대 6곳까지 표시해 기록으로 남길 수 있습니다.</p></div>
      <div class="feature-card"><div class="icon">🏬</div><h3>매장별 데이터 분리</h3><p>여러 매장을 운영해도 매장별로 고객·동의서·직원 데이터가 완전히 구분되어 관리됩니다.</p></div>
      <div class="feature-card"><div class="icon">👥</div><h3>대표·직원 권한 구분</h3><p>매장 대표와 직원의 로그인을 분리해, 직원은 필요한 화면만 접근하도록 제한할 수 있습니다.</p></div>
      <div class="feature-card"><div class="icon">💰</div><h3>매출 관리</h3><p>동의서뿐 아니라 매장의 매출 기록도 함께 관리할 수 있습니다.</p></div>
    </div>
  </div>
</section>

<section class="trust-section reveal">
  <div class="wrap">
    <span class="section-eyebrow">Trust & Security</span>
    <h2 class="section-title">보이지 않는 곳도 신경 쓰고 있습니다</h2>
    <div class="trust-list">
      <div class="trust-item"><span class="check">✓</span><div><h4>비밀번호 암호화 저장</h4><p>모든 비밀번호는 복호화가 불가능한 방식(bcrypt)으로 저장됩니다.</p></div></div>
      <div class="trust-item"><span class="check">✓</span><div><h4>고객 연락처 마스킹</h4><p>고객 목록에서는 전화번호 뒷자리를 가려 노출을 최소화합니다.</p></div></div>
      <div class="trust-item"><span class="check">✓</span><div><h4>무차별 로그인 시도 방어</h4><p>짧은 시간 안에 로그인 실패가 반복되면 자동으로 일정 시간 잠금 처리됩니다.</p></div></div>
      <div class="trust-item"><span class="check">✓</span><div><h4>매장 단위 접근 제어</h4><p>다른 매장의 고객·동의서 데이터는 애초에 조회 대상에 포함되지 않습니다.</p></div></div>
    </div>
  </div>
</section>

<section id="tool" class="reveal">
  <div class="wrap">
    <div class="tool-teaser">
      <div class="tool-teaser-copy">
        <h3>무료 시술동의서 생성기</h3>
        <p>회원가입 없이 매장명·시술명·체크리스트를 입력해 A4 PDF로 바로 다운로드할 수 있는 도구입니다. CareForm 서비스와 별개로 누구나 사용할 수 있습니다.</p>
        <div class="tool-teaser-badges">
          <span>회원가입 없이</span>
          <span>A4 PDF 다운로드</span>
          <span>입력값 서버 저장 안 함</span>
        </div>
        <a href="tool-consent-form.php" class="btn-primary-lg">무료 도구 사용해보기 →</a>
      </div>
      <div class="mock-a4">
        <h4>헤어 시술 동의서</h4>
        <ul>
          <li>알레르기 반응을 경험한 적이 있습니다</li>
          <li>피부 질환 또는 건강상 특이사항이 있습니다</li>
          <li>임신 중이거나 수유 중입니다</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section id="pricing" class="reveal">
  <div class="wrap">
    <span class="section-eyebrow">Pricing</span>
    <h2 class="section-title">카드 등록 없이 시작</h2>
    <p class="section-sub">먼저 사용해보고, 필요할 때 결제를 진행하세요.</p>
    <div class="pricing-box">
      <div class="ribbon">추천</div>
      <div class="plan-name">베이직 플랜</div>
      <div class="price">월 <?php echo number_format($monthlyFee); ?>원 <span>· VAT 포함</span></div>
      <div class="vat"><?php echo (int)$trialDays; ?>일 무료체험 · 이후 결제 전환</div>
      <ul>
        <li>동의서 생성·서명 횟수 제한 없음</li>
        <li>업종별 기본 템플릿 제공</li>
        <li>고객별 동의 이력 관리</li>
        <li>여러 매장 등록 및 매장별 데이터 관리</li>
      </ul>
      <a href="signup.php" class="btn-primary-lg" style="width:100%;display:block;">무료로 시작하기 →</a>
      <p class="pricing-note">무료체험 중에는 결제 정보를 입력하지 않아도 됩니다. 체험 종료 후에는 결제를 완료해야 계속 이용할 수 있습니다.</p>
    </div>
  </div>
</section>

<section id="faq" class="faq-section reveal">
  <div class="wrap">
    <span class="section-eyebrow">FAQ</span>
    <h2 class="section-title">시작하기 전에<br>확인해 보세요.</h2>
    <div class="faq-list">
      <div class="faq-item is-open">
        <button type="button" class="faq-question" aria-expanded="true"><span>무료로 시작하려면 카드 등록이 필요한가요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">아니요. 매장을 등록할 때 카드 정보를 입력하지 않아도 무료체험을 바로 시작할 수 있습니다.</div></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false"><span>매장을 등록하면 바로 사용할 수 있나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">네. 별도의 심사나 승인 대기 없이 매장 등록과 동시에 사용할 수 있으며, 등록 즉시 <?php echo (int)$trialDays; ?>일 무료체험이 시작됩니다.</div></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false"><span>동의서 양식은 직접 만들어야 하나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">헤어, 네일, 피부관리, 왁싱, 속눈썹, 타투 등 업종별 기본 템플릿을 제공합니다. 기본 양식을 그대로 사용하거나 매장 상황에 맞게 문구와 체크리스트를 직접 편집할 수 있습니다.</div></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false"><span>고객 데이터는 어떻게 관리되나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">고객 전화번호는 목록 화면에서 기본적으로 뒷자리가 마스킹되어 표시됩니다. 또한 매장별로 데이터가 분리되어 있어 다른 매장의 고객·동의서 정보는 조회 대상에 포함되지 않습니다.</div></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false"><span>여러 매장을 함께 운영할 수 있나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">네. 대표 계정 하나로 여러 매장을 등록하고, 매장별로 고객·동의서·매출 데이터를 구분해 관리할 수 있습니다.</div></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false"><span>결제는 어떻게 진행되나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">무료체험 종료 안내를 받은 뒤 결제 관리 화면에서 결제를 진행하면 계속 이용할 수 있습니다. 결제하지 않으면 별도 위약금 없이 서비스 이용만 일시 중지되며, 해지 관련 문의는 고객센터로 접수해 주세요.</div></div>
      </div>
    </div>
  </div>
</section>

<section class="reveal">
  <div class="final-cta">
    <h2>지금 바로 시작해보세요</h2>
    <p>가입에는 10초도 걸리지 않습니다.</p>
    <a href="signup.php" class="btn-primary-lg">무료로 시작하기 →</a>
  </div>
</section>

<?php require_once __DIR__ . '/includes/landing_footer.php'; ?>
