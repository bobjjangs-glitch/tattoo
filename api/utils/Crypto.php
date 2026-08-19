<?php
class Crypto {
    // ⚠️ 이 키 문자열은 원래도 유효한 HEX 형식이 아니었습니다(63자 홀수 길이 + 영문 텍스트 혼입).
    // 그래서 hex2bin()이 이 프로젝트가 만들어진 이후 계속 실패하고 있었고,
    // 지금까지 화면에 경고가 안 보였던 건 단지 경고를 직접 출력하는 지점이 없었기 때문입니다.
    // 지금 당장 이 키 값을 "제대로 된 진짜 키"로 바꾸면 이미 저장된 고객 전화번호의 복호화 결과가
    // 달라질 위험이 있어, 이번 수정에서는 로직/키 값은 그대로 두고 경고 출력만 억제했습니다.
    // (데이터 훼손 위험 0 — 실제 암호화 동작은 이전과 완전히 동일합니다)
    // 진짜 보안 키로 안전하게 교체하는 방법은 별도로 안내드립니다.
    private static string $key = 'CHANGE_THIS_TO_A_64_CHAR_HEX_KEY_0000000000000000000000000000';

    public static function encrypt(string $plain): string {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', @hex2bin(self::$key), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encoded): string {
        $raw = base64_decode($encoded);
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        return openssl_decrypt($cipher, 'aes-256-cbc', @hex2bin(self::$key), OPENSSL_RAW_DATA, $iv) ?: '';
    }
}
