<?php
/**
 * ss_platform_settings 키/값 설정 조회·저장 헬퍼.
 * store 쪽, admin 쪽 양쪽에서 공통으로 사용한다.
 */
function getPlatformSetting(PDO $pdo, string $key, $default = null) {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM ss_platform_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $value = $row ? $row['setting_value'] : $default;
    $cache[$key] = $value;
    return $value;
}

function setPlatformSetting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare(
        'INSERT INTO ss_platform_settings (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = ?'
    );
    $stmt->execute([$key, $value, $value]);
}
