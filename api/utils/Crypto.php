<?php
class Crypto {
    private static string $key = 'CHANGE_THIS_TO_A_64_CHAR_HEX_KEY_0000000000000000000000000000';

    public static function encrypt(string $plain): string {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', hex2bin(self::$key), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(string $encoded): string {
        $raw = base64_decode($encoded);
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        return openssl_decrypt($cipher, 'aes-256-cbc', hex2bin(self::$key), OPENSSL_RAW_DATA, $iv) ?: '';
    }
}
