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

function znote_csrf_field(): string {
    if (!Token::get()) {
        Token::generate();
    }

    return '<input type="hidden" name="token" value="' . htmlspecialchars(Token::get(), ENT_QUOTES, 'UTF-8') . '">';
}

function znote_csrf_validate_post(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return true;
    }

    $posted = $_POST['token'] ?? null;
    $session = Token::get();
    if (!is_string($posted) || $posted === '' || !is_string($session) || $session === '') {
        return false;
    }

    $valid = hash_equals($session, $posted);
    if ($valid) {
        Token::generate();
    }

    return $valid;
}

function znote_csrf_protect_public_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !znote_csrf_validate_post()) {
        http_response_code(400);
        die('Invalid or expired form token. Please go back, refresh the page and try again.');
    }

    ob_start(static function (string $html): string {
        if (stripos($html, '<form') === false || stripos($html, 'method') === false) {
            return $html;
        }

        return preg_replace_callback(
            '~<form\b(?=[^>]*\bmethod\s*=\s*["\']?post["\']?)[^>]*>~i',
            static function (array $match): string {
                if (stripos($match[0], 'name="token"') !== false || stripos($match[0], "name='token'") !== false) {
                    return $match[0];
                }

                return $match[0] . "\n" . znote_csrf_field();
            },
            $html
        ) ?? $html;
    });
}
?>
