<?php
// 플로팅 문의하기 위젯
$supportPhone = '010-0000-0000';
$supportEmail = 'support@salonform.club';
?>
<div class="floating-chat">
    <button type="button" class="floating-chat-btn" onclick="document.getElementById('floatingChatPanel').classList.toggle('open')">
        💬 문의하기
    </button>
    <div class="floating-chat-panel" id="floatingChatPanel">
        <div class="floating-chat-header">
            <span>무엇을 도와드릴까요?</span>
            <button type="button" class="floating-chat-close" onclick="document.getElementById('floatingChatPanel').classList.remove('open')">✕</button>
        </div>
        <div class="floating-chat-body">
            <a href="tel:<?= htmlspecialchars($supportPhone) ?>" class="floating-chat-item">
                📞 전화 문의<br><span class="muted"><?= htmlspecialchars($supportPhone) ?></span>
            </a>
            <a href="mailto:<?= htmlspecialchars($supportEmail) ?>" class="floating-chat-item">
                ✉️ 이메일 문의<br><span class="muted"><?= htmlspecialchars($supportEmail) ?></span>
            </a>
        </div>
    </div>
</div>
