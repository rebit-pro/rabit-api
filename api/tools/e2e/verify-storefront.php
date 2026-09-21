<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Commerce\Application\Storefront\Dto\QuoteLineInputDto;
use Morefoto\Commerce\Application\Storefront\UseCase\CreateQuoteUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\GetStorefrontCatalogUseCase;
use Morefoto\Commerce\Application\Storefront\UseCase\ValidateQuoteUseCase;
use Morefoto\Media\Application\Gallery\Service\GalleryCapabilityLifecycle;
use Morefoto\Media\Application\Gallery\UseCase\GetGalleryPreviewUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Rebit\Share\Contracts\Media\GalleryAccessInterface;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Contracts\Handoff\StaffEligibilityInterface;
use Sprint\Migration\Version20260921130001;

if ('test' !== getenv('APP_ENV') || !is_file('/runtime/e4-fixture.json')) {
    throw new RuntimeException('Storefront verification requires the disposable fixture.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
Loader::includeModule('morefoto.commerce');
Loader::includeModule('sprint.migration');
$services = ServiceLocator::getInstance();
$connection = Application::getConnection();
$fixture = json_decode((string)file_get_contents('/runtime/e4-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
$token = $fixture['open']['token'];
$access = $services->get(GalleryAccessInterface::class);
$gallery = $access->resolve($token);
$catalog = $services->get(GetStorefrontCatalogUseCase::class)->execute($token);
$product = null;
foreach ($catalog->products as $candidate) {
    if ('physical' === $candidate->kind->value) {
        $product = $candidate;
        break;
    }
}
if (null === $product || [] === $gallery->assignments) {
    throw new RuntimeException('Browser fixture must provide an active physical product and assignments.');
}
$assignment = $gallery->assignments[0];
$lines = [new QuoteLineInputDto($assignment->assignmentId, $product->id, 1)];
$created = $services->get(CreateQuoteUseCase::class)->execute($token, $lines);
$validator = $services->get(ValidateQuoteUseCase::class);
$validated = $validator->execute($token, $created->quoteToken, $lines);
if ($created->quote !== $validated->quote) {
    throw new RuntimeException('Fresh quote changed without a mutation.');
}
$quoteHash = hash('sha256', $created->quoteToken);
$row = $connection->query("SELECT SNAPSHOT_JSON FROM mf_cart_quote WHERE TOKEN_HASH='{$quoteHash}'")->fetch();
if (false === $row || str_contains((string)$row['SNAPSHOT_JSON'], $token) || str_contains((string)$row['SNAPSHOT_JSON'], $created->quoteToken)) {
    throw new RuntimeException('Quote snapshot must not contain raw capability tokens.');
}
$expect = static function(string $code, callable $operation): void {
    try {
        $operation();
    } catch (HttpException $error) {
        if ($code === $error->getMessage()) {
            return;
        }
        throw new RuntimeException('Unexpected domain rejection: ' . $error->getMessage());
    }
    throw new RuntimeException('Expected rejection: ' . $code);
};
$expect('QUOTE_STALE', static fn() => $validator->execute($fixture['closed']['token'], $created->quoteToken, $lines));
$expect('QUOTE_STALE', static fn() => $validator->execute($token, $created->quoteToken, [new QuoteLineInputDto($assignment->assignmentId, $product->id, 2)]));
$cases = [
    'price' => ["UPDATE mf_group_product_condition SET PRICE=PRICE+1 WHERE GROUP_ID={$gallery->group->id} AND PRODUCT_UUID='{$product->id}'", 'QUOTE_STALE'],
    'photo revision' => ["UPDATE b_hlbd_mf_photo SET UF_REVISION=UF_REVISION+1 WHERE UF_PUBLIC_ID='{$assignment->photoId}'", 'QUOTE_STALE'],
    'assignment' => ["DELETE FROM mf_photo_assignment WHERE PUBLIC_ID='{$assignment->assignmentId}'", 'INVALID_CART'],
    'expiry' => ["UPDATE mf_cart_quote SET EXPIRES_AT=UTC_TIMESTAMP() WHERE TOKEN_HASH='{$quoteHash}'", 'QUOTE_EXPIRED'],
];
foreach ($cases as [$sql, $code]) {
    $connection->startTransaction();
    try {
        $connection->queryExecute($sql);
        $expect($code, static fn() => $validator->executeWithinTransaction($token, $created->quoteToken, $lines));
    } finally {
        $connection->rollbackTransaction();
    }
}
$connection->startTransaction();
try {
    $services->get(GalleryCapabilityLifecycle::class)->revoke($token, $gallery->capabilityRevision);
    $expect('GALLERY_NOT_FOUND', static fn() => $access->resolve($token));
    $expect('GALLERY_NOT_FOUND', static fn() => $services->get(GetGalleryPreviewUseCase::class)->execute($token, $assignment->assignmentId, 'thumb'));
    $expect('GALLERY_NOT_FOUND', static fn() => $validator->executeWithinTransaction($token, $created->quoteToken, $lines));
} finally {
    $connection->rollbackTransaction();
}
$photo = $connection->query("SELECT ID FROM b_hlbd_mf_photo WHERE UF_PUBLIC_ID='{$assignment->photoId}'")->fetch();
if (false === $photo || $services->get(MediaMutationRepository::class)->assign($assignment->nativeChildId, [(int)$photo['ID']])) {
    throw new RuntimeException('Repeated assignment must be idempotent.');
}
require '/app/public/local/php_interface/migrations.foundation/Version20260921130001.php';
(new Version20260921130001())->up();
$after = $access->resolve($token);
if ($after->assignments[0]->assignmentId !== $assignment->assignmentId) {
    throw new RuntimeException('Assignment ID changed on migration replay.');
}
// F1 browser scenario creates the trusted snapshot through its public workflow.
$staffRow = $connection->query('SELECT request.SHOOT_ID,line.CHILD_ID FROM mf_staff_request request '
    . 'INNER JOIN mf_staff_request_row line ON line.REQUEST_ID=request.ID WHERE request.STAFF_ELIGIBLE=1 LIMIT 1')->fetch();
if (false === $staffRow) {
    throw new RuntimeException('F1 browser scenario did not provide a trusted eligibility snapshot.');
}
$staff = $services->get(StaffEligibilityInterface::class);
$childId = (int)$staffRow['CHILD_ID'];
$shootId = (int)$staffRow['SHOOT_ID'];
if (true !== ($staff->confirmed($shootId, [$childId])[$childId] ?? false)
    || [] !== $staff->confirmed($gallery->group->shootId, [$childId])) {
    throw new RuntimeException('Trusted staff eligibility must be limited to its own shoot.');
}
echo "E4 F1 trusted eligibility integration passed.\n";

echo "E4 integration passed: fresh quote, snapshot privacy, scope, composition, price, photo revision, assignment, expiry, revocation, idempotency and migration replay.\n";
