<?php
/**
 * tool-consent-form.php — 무료 시술동의서 생성기 (별도 경로, 공통 헤더/푸터 재사용)
 * 위치: html/tattoo/tool-consent-form.php
 */
$pageTitle       = '무료 시술동의서 생성기 | 회원가입 없이 PDF 다운로드 - CareForm';
$pageDescription = '미용실, 네일샵, 피부관리실, 왁싱샵을 위한 시술동의서 양식을 무료로 만들어 보세요. 회원가입 없이 A4 PDF로 바로 다운로드할 수 있습니다.';
$activeNav       = 'tool';

require_once __DIR__ . '/includes/landing_header.php';
?>

<section class="tool-hero">
  <div class="wrap">
    <p class="meta">회원가입 없이 · A4 PDF 다운로드 · 문구 수정 가능</p>
    <h1>무료 시술동의서 생성기</h1>
    <p>미용실, 네일샵, 피부관리실, 왁싱샵, 속눈썹샵을 위한 시술동의서 양식을 무료로 만들어 보세요. 매장명·시술명·체크리스트를 입력하면 오른쪽 미리보기가 바로 바뀌고, A4 PDF로 다운로드할 수 있습니다.</p>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="tool-grid">
      <div class="tool-form">
        <div class="form-group">
          <label>매장명</label>
          <input type="text" id="toolStoreName" placeholder="예) 케어폼 뷰티샵" value="케어폼 뷰티샵">
        </div>
        <div class="form-group">
          <label>시술명</label>
          <input type="text" id="toolProcedureName" placeholder="예) 헤어 시술" value="헤어 시술">
        </div>
        <div class="form-group">
          <label>고객명</label>
          <input type="text" id="toolCustomerName" placeholder="예) 김서연" value="김서연">
        </div>

        <div class="form-group">
          <label>시술 전 확인사항 <span style="font-weight:400;color:var(--text-sub);">(체크 해제하면 미리보기에서 빠집니다)</span></label>
          <div class="tool-checklist" id="toolChecklist">
            <label><input type="checkbox" class="tool-check-item" checked value="알레르기 반응을 경험한 적이 있습니다">알레르기 반응을 경험한 적이 있습니다</label>
            <label><input type="checkbox" class="tool-check-item" checked value="피부 질환 또는 건강상 특이사항이 있습니다">피부 질환 또는 건강상 특이사항이 있습니다</label>
            <label><input type="checkbox" class="tool-check-item" checked value="임신 중이거나 수유 중입니다">임신 중이거나 수유 중입니다</label>
            <label><input type="checkbox" class="tool-check-item" checked value="최근 관련 부위에 병원·피부과 시술을 받은 적이 있습니다">최근 관련 부위에 병원·피부과 시술을 받은 적이 있습니다</label>
            <label><input type="checkbox" class="tool-check-item" checked value="복용 중인 약물이 있습니다">복용 중인 약물이 있습니다</label>
          </div>
        </div>

        <div class="form-group">
          <label>안내 및 동의 문구 <span style="font-weight:400;color:var(--text-sub);">(자유롭게 수정 가능)</span></label>
          <textarea id="toolAgreeText" rows="4">개인의 상태와 체질에 따라 시술 효과와 결과가 다를 수 있음을 확인했습니다.
시술 후 일시적인 불편감이 나타날 수 있으며 대부분 자연 회복됨을 안내받았습니다.
담당자가 안내한 사후 관리 방법을 따를 것이며, 이상 반응이 지속되면 매장에 연락하겠습니다.
위 내용을 확인하였으며, 시술 진행에 동의합니다.</textarea>
        </div>

        <button type="button" id="toolDownloadBtn" class="btn-primary-lg" style="width:100%;">📄 PDF 다운로드</button>
        <p class="tool-note">입력한 내용은 브라우저에서만 처리되며 서버에 저장되지 않습니다. 다운로드가 되지 않으면 인터넷 연결 상태를 확인해 주세요.</p>
      </div>

      <div class="tool-preview-wrap">
        <div class="tool-preview-label">미리보기 <span>실제 PDF와 동일한 A4 배치입니다</span></div>
        <div id="toolPreview" class="tool-preview-a4">
          <dl>
            <div><dt>매장명</dt><dd id="pvStoreName">케어폼 뷰티샵</dd></div>
            <div><dt>고객명</dt><dd id="pvCustomerName">김서연</dd></div>
          </dl>
          <h3 id="pvProcedureName">헤어 시술 동의서</h3>
          <p class="pv-date" id="pvDate"></p>
          <h4>시술 전 확인사항</h4>
          <ul id="pvChecklist"></ul>
          <h4>시술 안내 및 동의사항</h4>
          <div class="pv-agree" id="pvAgreeText"></div>
          <div class="pv-sign"><span>작성일 ____년 __월 __일</span><span>고객 성명(서명) ______________</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="content-section">
  <h2>시술 전 동의서, 왜 받아야 하나요</h2>
  <p>시술 결과에 대한 분쟁은 대부분 "그런 설명은 못 들었다"에서 시작됩니다. 시술 전에 고객의 상태(시술 이력, 알레르기, 피부·모발 상태)를 확인하고, 결과가 달라질 수 있는 가능성과 주의사항을 안내한 뒤 고객의 확인 서명을 받아 두면, 무엇을 언제 안내했는지가 기록으로 남습니다.</p>
  <p>동의서는 고객을 압박하기 위한 문서가 아니라 상담 내용을 함께 확인하는 절차입니다. 항목을 하나씩 짚어 가며 안내하는 과정 자체가 상담 품질을 높이고, 고객 입장에서도 시술에 대한 이해가 깊어진 상태에서 시술을 받게 됩니다.</p>
</div>

<div class="content-section">
  <h2>종이 동의서와 전자 동의서의 차이</h2>
  <p>이 생성기로 만든 PDF는 출력해서 바로 쓸 수 있는 종이 동의서 양식입니다. 종이로 시작해도 충분하지만, 고객이 늘어나면 서명 기록을 찾고 보관하는 일이 점점 부담이 됩니다. 종이는 매장에 보관하는 동안 분실·훼손될 수 있고, 특정 고객의 서명을 다시 찾으려면 파일철을 직접 뒤져야 합니다.</p>
  <p>고객이 늘어나 종이 보관이 부담스러워지면, CareForm에 매장을 등록해 태블릿·휴대폰에서 바로 서명받고 고객별로 자동 보관하는 방식으로 전환할 수 있습니다. 카드 등록 없이 무료체험으로 먼저 사용해볼 수 있습니다.</p>
</div>

<section id="faq" class="faq-section">
  <div class="wrap">
    <h2 class="section-title">이 도구에 대해<br>자주 묻는 질문</h2>
    <div class="faq-list">
      <div class="faq-item is-open">
        <button type="button" class="faq-question" aria-expanded="true"><span>이 도구는 회원가입 없이 계속 무료로 쓸 수 있나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">네. 이 생성기는 별도의 회원가입이나 로그인 없이 누구나 무료로 사용할 수 있으며, 입력한 내용은 서버에 저장되지 않고 브라우저에서만 처리됩니다.</div></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false"><span>이 양식은 법적으로 효력이 있나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">이 생성기와 양식은 상담 내용을 문서로 남기는 일반적인 정보 제공과 매장 운영 기록 관리를 돕기 위한 것으로, 법률 자문을 대체하지 않으며 모든 상황에서의 법적 보호를 보장하지는 않습니다. 구체적인 법적 사안은 전문가와 상담하시기 바랍니다.</div></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false"><span>만든 PDF를 수정하거나 다시 만들 수 있나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">네. 이 페이지에서 값을 다시 입력한 뒤 PDF 다운로드 버튼을 누르면 원하는 만큼 다시 생성할 수 있습니다. 별도로 저장되는 이력은 없으므로 필요할 때마다 이 페이지에서 새로 만들면 됩니다.</div></div>
      </div>
      <div class="faq-item">
        <button type="button" class="faq-question" aria-expanded="false"><span>종이 대신 전자서명으로 받고 싶으면 어떻게 하나요?</span><span class="faq-toggle"><span></span><span></span></span></button>
        <div class="faq-answer-wrap"><div class="faq-answer">CareForm에 매장을 등록하면 업종별 동의서 템플릿이 준비되어 있고, 태블릿이나 휴대폰에서 고객에게 바로 서명받아 고객별로 자동 보관할 수 있습니다. 카드 등록 없이 무료체험 기간 동안 사용해 볼 수 있습니다.</div></div>
      </div>
    </div>
  </div>
</section>

<div class="content-disclaimer">
  본 생성기와 양식은 일반적인 정보 제공과 매장 운영 기록 관리를 돕기 위한 것으로, 법률 자문을 대체하지 않습니다. 구체적인 법적 사안은 전문 법률가와 상담하시기 바랍니다.
</div>

<section>
  <div class="final-cta">
    <h2>서명 기록을 매장 계정에 자동으로 남기고 싶다면</h2>
    <p>카드 등록 없이 무료체험으로 먼저 사용해볼 수 있습니다.</p>
    <a href="signup.php" class="btn-primary-lg">무료로 시작하기 →</a>
  </div>
</section>

<!-- PDF 생성용 라이브러리 (CDN, 클라이언트에서만 동작 · 서버로 데이터 전송 없음) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
var storeNameInput = document.getElementById('toolStoreName');
var procedureInput = document.getElementById('toolProcedureName');
var customerInput  = document.getElementById('toolCustomerName');
var agreeTextarea  = document.getElementById('toolAgreeText');
var checkItems     = document.querySelectorAll('.tool-check-item');

var pvStoreName      = document.getElementById('pvStoreName');
var pvCustomerName   = document.getElementById('pvCustomerName');
var pvProcedureName  = document.getElementById('pvProcedureName');
var pvDate           = document.getElementById('pvDate');
var pvChecklist      = document.getElementById('pvChecklist');
var pvAgreeText      = document.getElementById('pvAgreeText');

function todayLabel() {
  var d = new Date();
  return '작성일 ' + d.getFullYear() + '. ' + String(d.getMonth() + 1).padStart(2, '0') + '. ' + String(d.getDate()).padStart(2, '0') + '.';
}

function renderPreview() {
  pvStoreName.textContent     = storeNameInput.value || '-';
  pvCustomerName.textContent  = customerInput.value || '-';
  pvProcedureName.textContent = (procedureInput.value || '시술') + ' 동의서';
  pvDate.textContent          = todayLabel();

  pvChecklist.innerHTML = '';
  checkItems.forEach(function (cb) {
    if (cb.checked) {
      var li = document.createElement('li');
      li.textContent = cb.value;
      pvChecklist.appendChild(li);
    }
  });

  pvAgreeText.textContent = agreeTextarea.value;
}

[storeNameInput, procedureInput, customerInput, agreeTextarea].forEach(function (el) {
  el.addEventListener('input', renderPreview);
});
checkItems.forEach(function (cb) {
  cb.addEventListener('change', renderPreview);
});
renderPreview();

document.getElementById('toolDownloadBtn').addEventListener('click', function () {
  var btn = this;
  var originalText = btn.textContent;
  btn.textContent = '생성 중...';
  btn.disabled = true;

  var target = document.getElementById('toolPreview');
  html2canvas(target, { scale: 2, backgroundColor: '#ffffff' }).then(function (canvas) {
    var imgData = canvas.toDataURL('image/png');
    var pdf = new window.jspdf.jsPDF('p', 'mm', 'a4');
    var pageWidth = pdf.internal.pageSize.getWidth();
    var pageHeight = (canvas.height * pageWidth) / canvas.width;
    pdf.addImage(imgData, 'PNG', 0, 0, pageWidth, Math.min(pageHeight, pdf.internal.pageSize.getHeight()));
    var fileName = (storeNameInput.value || 'CareForm') + '_' + (procedureInput.value || '동의서') + '.pdf';
    pdf.save(fileName.replace(/[\\/:*?"<>|]/g, ''));
    btn.textContent = originalText;
    btn.disabled = false;
  }).catch(function (err) {
    console.error(err);
    alert('PDF 생성 중 문제가 발생했습니다. 인터넷 연결을 확인하고 다시 시도해주세요.');
    btn.textContent = originalText;
    btn.disabled = false;
  });
});
</script>

<?php require_once __DIR__ . '/includes/landing_footer.php'; ?>
