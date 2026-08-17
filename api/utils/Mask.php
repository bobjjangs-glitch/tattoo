<?php
class Mask {
    public static function phone(string $phone): string {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 7) return $phone;
        return substr($digits, 0, 3) . '-****-' . substr($digits, -4);
    }
}
