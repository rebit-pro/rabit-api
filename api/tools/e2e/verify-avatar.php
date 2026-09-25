<?php

declare(strict_types=1);

use Bitrix\Main\Application;

// B3: MySQL and file trail of the avatar scenarios — one row and only the current version's private WebP files.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Avatar verification requires the disposable test runtime.');
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
        throw new RuntimeException('B3 verification failed: ' . $message);
    }
};
$userId = static function(string $email) use ($connection): int {
    $row = $connection->query(sprintf("SELECT ID FROM b_user WHERE EMAIL = '%s'", $connection->getSqlHelper()->forSql($email)))->fetchRaw();

    return false === $row ? throw new RuntimeException('Missing identity ' . $email) : (int)$row['ID'];
};
$root = rtrim((string)getenv('MOREFOTO_PRIVATE_MEDIA_PATH'), '/') . '/avatars';

$teacher = $userId('teacher@example.invalid');
$row = $connection->query('SELECT VERSION, FINGERPRINT, MIME, WIDTH, HEIGHT, UPDATED_BY FROM mf_staff_avatar WHERE USER_ID = ' . $teacher)->fetchRaw();
$check(false !== $row, 'the organizer set the teacher avatar');
$check(1 === (int)$row['VERSION'] && 'image/png' === $row['MIME'] && 1200 === (int)$row['WIDTH'] && 800 === (int)$row['HEIGHT'], 'version 1 of the 1200 x 800 portrait is stored');
$check(1 === preg_match('/^[a-f0-9]{64}$/D', (string)$row['FINGERPRINT']), 'fingerprint is sha256 of the upload');
$check($userId('organizer@example.invalid') === (int)$row['UPDATED_BY'], 'the change is attributed to the organizer');
$files = array_values(array_diff((array)scandir($root . '/' . $teacher), ['.', '..']));
sort($files);
$check(['1-256.webp', '1-64.webp'] === $files, 'only the current version is on disk');
foreach ([256 => '1-256.webp', 64 => '1-64.webp'] as $size => $name) {
    $path = $root . '/' . $teacher . '/' . $name;
    $info = getimagesize($path);
    $content = (string)file_get_contents($path);
    $check(is_array($info) && IMAGETYPE_WEBP === $info[2] && $size === $info[0] && $size === $info[1], $name . ' is a square WebP');
    $check(!str_contains($content, 'EXIF') && !str_contains($content, 'Exif'), $name . ' carries no EXIF');
    $check('0600' === substr(sprintf('%o', fileperms($path)), -4), $name . ' is private');
}

$organizer = $userId('organizer@example.invalid');
$check(false === $connection->query('SELECT USER_ID FROM mf_staff_avatar WHERE USER_ID = ' . $organizer)->fetchRaw(), 'the removed avatar has no row');
$check(!is_dir($root . '/' . $organizer), 'the removed avatar has no files');

echo "B3 avatar integration passed\n";
