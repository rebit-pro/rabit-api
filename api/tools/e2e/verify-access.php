<?php

declare(strict_types=1);

use Bitrix\Main\Application;

// B4: MySQL trail of the access scenarios — only token hashes are stored, links are one-time, letters carry the link.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Access verification requires the disposable test runtime.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
$connection = Application::getConnection();
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('B4 verification failed: ' . $message);
    }
};
$user = static function(string $email) use ($connection): array {
    $row = $connection->query(sprintf(
        "SELECT u.ID, u.ACTIVE, COALESCE(uf.UF_AUTH_REGISTRATION_PENDING, 0) AS PENDING FROM b_user u LEFT JOIN b_uts_user uf ON uf.VALUE_ID = u.ID WHERE u.EMAIL = '%s'",
        $connection->getSqlHelper()->forSql($email),
    ))->fetchRaw();

    return false === $row ? throw new RuntimeException('Missing identity ' . $email) : $row;
};
$link = static function(int $userId, string $purpose) use ($connection): array {
    $row = $connection->query(sprintf(
        "SELECT TOKEN_HASH, USED_AT FROM rebit_auth_access_link WHERE USER_ID = %d AND PURPOSE = '%s'",
        $userId,
        $purpose,
    ))->fetchRaw();

    return false === $row ? throw new RuntimeException('Missing access link') : $row;
};

$inviteToken = 'b4InviteFixtureToken' . str_repeat('A', 23);
$resetToken = 'b4ResetFixtureToken' . str_repeat('B', 24);
$plain = $connection->query(sprintf(
    "SELECT COUNT(*) AS C FROM rebit_auth_access_link WHERE TOKEN_HASH IN ('%s', '%s') OR LENGTH(TOKEN_HASH) <> 64",
    $inviteToken,
    $resetToken,
))->fetchRaw();
$check(0 === (int)$plain['C'], 'tokens are stored only as SHA-256');

$invited = $user('b4-invited@example.invalid');
$check('Y' === $invited['ACTIVE'] && 0 === (int)$invited['PENDING'], 'accepted invitation activates the identity');
$check(null !== $link((int)$invited['ID'], 'invite')['USED_AT'], 'accepted invitation link is spent');

$reset = $user('b4-reset@example.invalid');
$check(null !== $link((int)$reset['ID'], 'reset')['USED_AT'], 'reset link is spent after the new password');

// The B2 browser scenario creates a pending teacher: the letter carries the link whose hash is the stored one.
$teacher = $user('b2-teacher@example.invalid');
$check('N' === $teacher['ACTIVE'] && 1 === (int)$teacher['PENDING'], 'new staff stays pending until the password is set');
$operation = $connection->query(
    "SELECT BODY, BODY_HTML FROM b_rebit_notification_operation WHERE CONSUMER_KEY = 'auth-invite' AND RECIPIENT = 'b2-teacher@example.invalid' ORDER BY CREATED_AT DESC, ID DESC LIMIT 1",
)->fetchRaw();
$check(false !== $operation, 'invitation letter is queued through H1');
$check(1 === preg_match('~/access/invite/([A-Za-z0-9_-]{43})~', (string)$operation['BODY'], $match), 'letter body carries the personal link');
$check(hash('sha256', $match[1]) === $link((int)$teacher['ID'], 'invite')['TOKEN_HASH'], 'the latest letter holds the live token');
$check(str_contains((string)$operation['BODY_HTML'], 'href=') && !str_contains((string)$operation['BODY_HTML'], '<script'), 'HTML letter has the button and no raw markup from data');

$pending = $user('b4-pending@example.invalid');
$resend = $connection->query(sprintf(
    "SELECT COUNT(*) AS C FROM b_rebit_notification_operation WHERE CONSUMER_KEY = 'auth-invite' AND RECIPIENT = 'b4-pending@example.invalid'",
))->fetchRaw();
$check(1 <= (int)$resend['C'] && 1 === (int)$pending['PENDING'], 'password recovery of a pending account resends the invitation');

echo "B4 access integration passed\n";
