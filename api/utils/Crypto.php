<?php
require_once __DIR__ . '/../../includes/env.php';
loadEnv(__DIR__ . '/../../.env');

class Crypto {
    private static function getKey(): string {
        $hex = env('CRYPTO_KEY');
        if (!$hex) {
            throw new RuntimeException('CRYPTO_KEY가 .env에 설정되지 않았습니다.');
        }
        return $hex;
    }

    public static function encrypt(string $plain): string {
        $iv = random_bytes(16);
        // 기존과 동일하게 @로 경고를 억제한다 (키 값 자체는 바꾸지 않았으므로 기존 데이터와의 호환성 유지)
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', @hex2bin(self::getKey()), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encoded): string {
        $raw = base64_decode($encoded);
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        return openssl_decrypt($cipher, 'aes-256-cbc', @hex2bin(self::getKey()), OPENSSL_RAW_DATA, $iv) ?: '';
    }
}
