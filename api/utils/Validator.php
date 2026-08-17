<?php
class Validator {
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isValidBusinessNumber(string $digits): bool {
        if (!preg_match('/^\d{10}$/', $digits)) return false;
        $weights = [1, 3, 7, 1, 3, 7, 1, 3, 5];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$digits[$i] * $weights[$i];
        }
        $sum += intdiv((int)$digits[8] * 5, 10);
        $check = (10 - ($sum % 10)) % 10;
        return $check === (int)$digits[9];
    }

    public static function isValidIndustry(string $industry): bool {
        return in_array($industry, ['hair', 'skin', 'nail', 'waxing', 'lash', 'tattoo'], true);
    }
}
