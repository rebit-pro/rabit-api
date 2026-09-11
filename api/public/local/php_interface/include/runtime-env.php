<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

$setEnvironmentVariable = static function(string $name, string $value): void {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
};

$loadSecret = static function(string $name, string $path) use ($setEnvironmentVariable): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $secretValue = trim((string)file_get_contents($path));

    if ('' === $secretValue) {
        return;
    }

    $setEnvironmentVariable($name, $secretValue);
};

$envPath = dirname(__DIR__, 3) . '/.env';

if (is_file($envPath)) {
    (new Dotenv())
        ->usePutenv(true)
        ->loadEnv($envPath)
    ;
}

$loadSecret('REBIT_ENCRYPTION_KEY', '/run/secrets/rebit_encryption_key');
$loadSecret('REBIT_GEETEST_CAPTCHA_KEY', '/run/secrets/rebit_geetest_captcha_key');
$loadSecret('REBIT_NOTIFICATION_TELEGRAM_BOT_TOKEN', '/run/secrets/rebit_telegram_bot_token');
