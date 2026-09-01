<?php
class Token {

    public static function generate(): void {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }

    public static function create(): void {
        if (!self::get()) {
            self::generate();
        }
        echo '<input type="hidden" name="token" value="' . self::get() . '">';
    }

    public static function get(): string|false {
        return $_SESSION['token'] ?? false;
    }

    public static function isValid(?string $post): bool {
        if (!config('use_token')) {
            return true;
        }

        if (!$post || !self::get()) {
            return false;
        }

        $valid = hash_equals($_SESSION['token'], $post);

        // 🔐 IMPORTANT: token usage unique
        self::_reset();

        return $valid;
    }

    protected static function _reset(): void {
        unset($_SESSION['token']);
    }
}
?>
