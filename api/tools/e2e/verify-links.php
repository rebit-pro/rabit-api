<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Loader;
use Morefoto\Media\Domain\Gallery\Service\GalleryAvailability;
use Morefoto\Organization\Application\Calendar\Contract\CalendarClockInterface;
use Morefoto\Organization\Application\Calendar\Service\GroupCalendar;
use Morefoto\Organization\Domain\Calendar\Repository\GroupCalendarRepository;
use Morefoto\Organization\Domain\Institution\Repository\InstitutionOperationRepository;
use Morefoto\Organization\Infrastructure\Handoff\GroupDirectory;
use Ramsey\Uuid\Uuid;
use Rebit\Share\Contracts\Organization\Dto\CalendarCommandInputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\Dto\LinkSentInputDto;
use Rebit\Share\Shared\Exception\HttpException;

// F2: real MySQL trail of the browser scenario plus calendar rules under a controlled clock.
if ('test' !== getenv('APP_ENV') || !is_dir('/runtime/public/bitrix')) {
    throw new RuntimeException('Link verification requires the disposable test runtime.');
}
$_SERVER['DOCUMENT_ROOT'] = '/runtime/public';
require '/runtime/public/bitrix/modules/main/include/prolog_before.php';
set_exception_handler(static function(Throwable $error): never {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});
foreach (['morefoto.organization', 'morefoto.media', 'morefoto.handoff', 'morefoto.commerce'] as $module) {
    if (!Loader::includeModule($module)) {
        throw new RuntimeException('Missing verification module.');
    }
}
$connection = Application::getConnection();
$services = ServiceLocator::getInstance();
$check = static function(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('F2 verification failed: ' . $message);
    }
};
$column = static function(string $sql) use ($connection): array {
    $values = [];
    $result = $connection->query($sql);
    while (false !== ($row = $result->fetch())) {
        $values[] = (string)reset($row);
    }

    return $values;
};

// 1. The browser scenario left one consistent trail in every owner.
$group = $connection->query("SELECT ID,UF_PUBLIC_ID,DATE_FORMAT(UF_SENT_AT,'%Y-%m-%d %H:%i:%s') AS SENT_AT,DATE_FORMAT(UF_CLOSES_AT,'%Y-%m-%d %H:%i:%s') AS CLOSES_AT,
    DATE_FORMAT(UF_DELIVERY_DUE_AT,'%Y-%m-%d %H:%i:%s') AS DELIVERY_DUE_AT FROM b_hlbd_mf_group WHERE UF_NAME LIKE 'F2 Ромашки%' ORDER BY ID DESC LIMIT 1")->fetch();
$check(false !== $group, 'browser group is missing');
$id = (int)$group['ID'];
$publicId = (string)$group['UF_PUBLIC_ID'];
$check(['prepared', 'prepared', 'transmitted', 'corrected'] === $column("SELECT KIND FROM mf_group_link_history WHERE GROUP_ID={$id} ORDER BY ID"), 'history kinds');
$check(['5'] === $column("SELECT REVISION FROM mf_group_link WHERE GROUP_ID={$id}"), 'link revision after two preparations, delivery and correction');
$sent = new DateTimeImmutable((string)$group['SENT_AT'], new DateTimeZone('UTC'));
$check((string)$group['CLOSES_AT'] === $sent->modify('+7 days')->format('Y-m-d H:i:s'), 'close is seven days after the corrected delivery');
$check((string)$group['DELIVERY_DUE_AT'] === $sent->modify('+14 days')->format('Y-m-d H:i:s'), 'delivery is seven days after the close');
$keys = $connection->query("SELECT TOKEN,TOKEN_HASH FROM mf_gallery_capability WHERE GROUP_PUBLIC_ID='{$publicId}' AND REVOKED=0")->fetchAll();
$check(1 === count($keys) && hash('sha256', (string)$keys[0]['TOKEN']) === (string)$keys[0]['TOKEN_HASH'], 'single active key whose hash still resolves buyers');
$actions = $column("SELECT JSON_UNQUOTE(JSON_EXTRACT(UF_DELTA,'$.action')) FROM b_hlbd_mf_organization_change WHERE UF_AGGREGATE_TYPE='group' AND UF_AGGREGATE_ID={$id} ORDER BY ID");
$check(['recordLinkSent', 'correctLinkSent'] === array_values(array_intersect($actions, ['recordLinkSent', 'correctLinkSent'])), 'organization journal of the delivery');
// Two preparations, the delivery, the repeat with a new key and the correction; replays and rejected commands store nothing.
$check(5 === (int)$connection->query("SELECT COUNT(*) AS TOTAL FROM mf_group_link_idempotency WHERE RESOURCE_KEY LIKE '/groups/{$publicId}/link-%'")->fetch()['TOTAL'], 'idempotency records');

// 2. Calendar rules on MySQL with a controlled clock: explicit delivery, repeat, kept extension and rejected moments.
$actor = (int)$connection->query("SELECT ID FROM b_user WHERE LOGIN='organizer@example.invalid'")->fetch()['ID'];
$clock = new class implements CalendarClockInterface {
    public DateTimeImmutable $now;

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
};
$fixture = static function(string $name) use ($connection): string {
    $institution = Uuid::uuid4()->toString();
    $connection->queryExecute("INSERT INTO b_hlbd_mf_institution(UF_PUBLIC_ID,UF_NAME,UF_ADDRESS,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('{$institution}','F2 verifier','Тест',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $institutionId = (int)$connection->getInsertedId();
    $shoot = Uuid::uuid4()->toString();
    $connection->queryExecute("INSERT INTO b_hlbd_mf_shoot(UF_PUBLIC_ID,UF_INSTITUTION_ID,UF_NAME,UF_DATE,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT) VALUES('{$shoot}',{$institutionId},'F2 verifier',NULL,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $shootId = (int)$connection->getInsertedId();
    $group = Uuid::uuid4()->toString();
    $connection->queryExecute("INSERT INTO b_hlbd_mf_group(UF_PUBLIC_ID,UF_SHOOT_ID,UF_NAME,UF_KIND,UF_TIMEZONE,UF_SENT_AT,UF_CLOSES_AT,UF_DELIVERY_DUE_AT,UF_REVISION,UF_CREATED_AT,UF_UPDATED_AT)
        VALUES('{$group}',{$shootId},'{$name}','regular','Europe/Moscow',NULL,NULL,NULL,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");

    return $group;
};
$calendar = new GroupCalendar($services->get(GroupCalendarRepository::class), $services->get(InstitutionOperationRepository::class), $clock);
$rejects = static function(string $code, callable $operation) use ($check): void {
    try {
        $operation();
        $check(false, $code . ' was accepted');
    } catch (HttpException $error) {
        $check($code === $error->getMessage(), $code . ' expected, got ' . $error->getMessage());
    }
};
$groupId = $fixture('F2 verifier calendar');
$fresh = $fixture('F2 verifier fresh');
$connection->startTransaction();
try {
    $clock->now = new DateTimeImmutable('2026-09-12T00:00:00+03:00');
    $calendar->lock($groupId);
    $first = $calendar->recordLinkSent(new LinkSentInputDto($groupId, $actor, Uuid::uuid4()->toString(), new DateTimeImmutable('2026-09-11T14:30:00+03:00'), null));
    $check(2 === $first->revision && '2026-09-18T14:30:00+03:00' === $first->calendar->closesAt && '2026-09-25T14:30:00+03:00' === $first->calendar->deliveryDueAt, 'recorded delivery +7/+7 in Moscow');
    $repeat = $calendar->recordLinkSent(new LinkSentInputDto($groupId, $actor, Uuid::uuid4()->toString(), new DateTimeImmutable('2026-09-11T20:00:00+03:00'), null));
    $check(2 === $repeat->revision && $first->calendar == $repeat->calendar, 'repeated delivery keeps every deadline');
    $extended = $calendar->extend(new CalendarCommandInputDto($groupId, $actor, 2, str_repeat('e', 32), 'Продление по согласованию'), new DateTimeImmutable('2026-09-21T12:00:00+03:00'));
    $check(3 === $extended->revision && '2026-09-21T12:00:00+03:00' === $extended->calendar->closesAt, 'internal extension');
    $clock->now = new DateTimeImmutable('2026-09-15T00:00:00+03:00');
    $corrected = $calendar->correctLinkSent(new LinkSentInputDto($groupId, $actor, Uuid::uuid4()->toString(), new DateTimeImmutable('2026-09-10T09:00:00+03:00'), 'Ошибка в дате'));
    $check(4 === $corrected->revision && '2026-09-10T09:00:00+03:00' === $corrected->calendar->sentAt, 'correction revision and moment');
    $check('2026-09-21T12:00:00+03:00' === $corrected->calendar->closesAt && '2026-09-28T12:00:00+03:00' === $corrected->calendar->deliveryDueAt, 'correction keeps the later extension');
    $calendar->lock($fresh);
    $rejects('LINK_NOT_SENT', static fn(): mixed => $calendar->correctLinkSent(new LinkSentInputDto($fresh, $actor, Uuid::uuid4()->toString(), new DateTimeImmutable('2026-09-14T09:00:00+03:00'), 'Ошибка в дате')));
    $rejects('SENT_AT_IN_FUTURE', static fn(): mixed => $calendar->recordLinkSent(new LinkSentInputDto($fresh, $actor, Uuid::uuid4()->toString(), new DateTimeImmutable('2026-09-15T00:00:01+03:00'), null)));
    $connection->commitTransaction();
} catch (Throwable $error) {
    $connection->rollbackTransaction();

    throw $error;
}
$check(['recordLinkSent', 'extendCalendar', 'correctLinkSent'] === $column("SELECT JSON_UNQUOTE(JSON_EXTRACT(c.UF_DELTA,'$.action')) FROM b_hlbd_mf_organization_change c
    JOIN b_hlbd_mf_group g ON g.ID=c.UF_AGGREGATE_ID WHERE c.UF_AGGREGATE_TYPE='group' AND g.UF_PUBLIC_ID='{$groupId}' ORDER BY c.ID"), 'journal of the controlled calendar');

// 3. The close boundary is the same in Organization and in the buyer gallery: now == closesAt is closed.
$directory = new GroupDirectory($clock);
$nativeId = (int)$connection->query("SELECT ID FROM b_hlbd_mf_group WHERE UF_PUBLIC_ID='{$groupId}'")->fetch()['ID'];
$states = static fn(): array => array_map(
    static fn(string $state): int => $directory->page(new GroupDirectoryQueryInputDto(null, [$nativeId], null, null, $state, 1, 25))->total,
    ['open' => 'open', 'closed' => 'closed'],
);
$clock->now = new DateTimeImmutable('2026-09-21T11:59:59+03:00');
$check('open' === $directory->find($groupId)?->calendar->status && ['open' => 1, 'closed' => 0] === $states(), 'one second before the close is open');
$check('open' === (new GalleryAvailability())->state('2026-09-10 06:00:00', '2026-09-21 09:00:00', $clock->now), 'gallery is open one second before the close');
$clock->now = new DateTimeImmutable('2026-09-21T12:00:00+03:00');
$check('closed' === $directory->find($groupId)?->calendar->status && ['open' => 0, 'closed' => 1] === $states(), 'the close moment itself is closed');
$check('closed' === (new GalleryAvailability())->state('2026-09-10 06:00:00', '2026-09-21 09:00:00', $clock->now), 'gallery closes at the same moment');

echo "F2 integration passed\n";
