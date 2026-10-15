<?php

declare(strict_types=1);

namespace Morefoto\Production\Tests\Unit\Support;

use Morefoto\Production\Application\Archive\Contract\ProductionAccessInterface;
use Morefoto\Production\Application\Archive\Dto\ProductionScopeOutputDto;
use Morefoto\Production\Application\Archive\Service\ArchiveBuilder;
use Morefoto\Production\Application\Archive\Service\ProductionLookup;
use Morefoto\Production\Application\Archive\Service\VersionSnapshot;
use Morefoto\Production\Application\Archive\Service\VersionView;
use Morefoto\Production\Application\Archive\UseCase\CreateProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\ExportProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\GetGroupProductionUseCase;
use Morefoto\Production\Application\Archive\UseCase\GetProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\ListProductionGroupsUseCase;
use Morefoto\Production\Application\Archive\UseCase\OpenProductionArchiveUseCase;
use Morefoto\Production\Application\Archive\UseCase\RenewProductionVersionUseCase;
use Morefoto\Production\Application\Archive\UseCase\RunProductionUseCase;
use Morefoto\Production\Domain\Archive\Service\ArchiveLayout;
use Morefoto\Production\Domain\Archive\Service\ArchivePolicy;
use Morefoto\Production\Domain\Archive\Service\ProductionStatePolicy;
use Morefoto\Production\Infrastructure\File\CsvManifestWriter;
use Morefoto\Production\Infrastructure\File\LocalArchiveStorage;
use Morefoto\Production\Infrastructure\File\ZipArchiveWriter;
use Morefoto\Production\Infrastructure\Id\ProductionIdGenerator;
use Morefoto\Production\Infrastructure\Security\HmacArchiveLink;
use Psr\Log\NullLogger;
use Rebit\Share\Application\Contract\Clock\ClockInterface;
use Rebit\Share\Contracts\Commerce\Dto\PaidPrintLineOutputDto;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;
use Rebit\Share\Contracts\Media\Dto\OriginalFileOutputDto;
use Rebit\Share\Contracts\Media\OriginalFilesInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryPageOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectorySummaryOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Contracts\Organization\PrintClosedGroupsInterface;
use Rebit\Share\Infrastructure\File\AtomicZipWriter;
use Rebit\Share\Shared\Exception\HttpException;
use Morefoto\Production\Application\Archive\Dto\ProductionRunOutputDto;

/** Собирает UseCase'ы L1 на настоящих правилах, файлах и ZIP; Commerce, Organization, Media и Access подменены. */
final class ProductionFixture implements ClockInterface, GroupPrintOrdersInterface, GroupDirectoryInterface, PrintClosedGroupsInterface, OriginalFilesInterface, ProductionAccessInterface
{
    public const int ORGANIZER = 1;
    public const int CURATOR = 2;
    public const int FOREIGN_CURATOR = 3;
    public const int TEACHER = 4;
    public const string GROUP = '11111111-1111-4111-8111-111111111111';
    public const string OTHER_GROUP = '22222222-2222-4222-8222-222222222222';

    public \DateTimeImmutable $now;
    public InMemoryArchives $archives;
    public string $root;
    /** @var array<string, GroupDirectoryItemOutputDto> */
    public array $groups = [];
    /** @var array<string, list<PaidPrintLineOutputDto>> */
    public array $paid = [];
    /** @var array<string, int> */
    public array $waiting = [];
    /** @var array<string, OriginalFileOutputDto> */
    public array $originalFiles = [];
    public HmacArchiveLink $links;
    /** Чтения справочника групп: контракт помечен impure, каждое чтение видит текущее состояние. */
    public int $groupReads = 0;

    public function __construct(string $since = '2026-10-15 09:00:00')
    {
        $this->now = new \DateTimeImmutable($since, new \DateTimeZone('UTC'));
        $this->archives = new InMemoryArchives($this->now);
        $this->root = sys_get_temp_dir() . '/l1-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/files', 0700, true);
        mkdir($this->root . '/media', 0700, true);
        $this->links = new HmacArchiveLink(str_repeat('k', 40));
    }

    public function __destruct()
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /** Группа с переданной ссылкой и сроком печати (UTC). */
    public function group(string $id, int $nativeId, int $institutionId, ?string $closesAt): void
    {
        $closes = null === $closesAt ? null : new \DateTimeImmutable($closesAt, new \DateTimeZone('UTC'))->setTimezone(new \DateTimeZone('Europe/Moscow'))->format(\DateTimeInterface::ATOM);
        $this->groups[$id] = new GroupDirectoryItemOutputDto(
            nativeId: $nativeId,
            id: $id,
            name: 'Звёздочки',
            kind: 'regular',
            institutionNativeId: $institutionId,
            institutionId: '33333333-3333-4333-8333-33333333333' . $institutionId,
            institutionName: 'Солнышко',
            shootNativeId: 5,
            shootId: '44444444-4444-4444-8444-444444444444',
            shootName: 'Осень',
            calendar: new GroupCalendarOutputDto('Europe/Moscow', null === $closes ? null : '2026-10-01T10:00:00+03:00', $closes, null, null, 'open'),
        );
    }

    /** Оплаченная печатная строка с настоящим файлом оригинала. */
    public function paidLine(string $groupId, string $lineId, string $photoCode, string $productId, string $productName, int $quantity, int $printCount = 1, string $paidAt = '2026-10-10 10:00:00', string $audience = 'regular'): void
    {
        $photoId = sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012d', crc32($photoCode));
        if (!isset($this->originalFiles[$photoId])) {
            $path = $this->root . '/media/' . $photoCode . '.jpg';
            file_put_contents($path, 'JPEG:' . $photoCode);
            $this->originalFiles[$photoId] = new OriginalFileOutputDto($photoId, 'image/jpeg', (int)filesize($path), 'shoot/' . $photoCode . '.jpg', $path);
        }
        $this->paid[$groupId][] = new PaidPrintLineOutputDto(
            lineId: $lineId,
            orderId: sprintf('bbbbbbbb-bbbb-4bbb-8bbb-%012d', crc32($lineId)),
            orderNumber: 'MF-' . strtoupper(substr($lineId, 0, 4)),
            paidAt: $paidAt,
            audience: $audience,
            childCode: substr($photoCode, 0, 1),
            photoId: $photoId,
            photoCode: $photoCode,
            productId: $productId,
            productName: $productName,
            productFormat: '10×15',
            printCount: $printCount,
            quantity: $quantity,
        );
    }

    public function at(string $moment): void
    {
        $this->now = new \DateTimeImmutable($moment, new \DateTimeZone('UTC'));
    }

    public function run(int $limit = 20): ProductionRunOutputDto
    {
        return new RunProductionUseCase($this->archives, $this, $this->lookup(), $this->snapshot(), $this->builder(), $this->storage(), new ArchivePolicy(), $this, new NullLogger())->execute($limit);
    }

    public function create(): CreateProductionVersionUseCase
    {
        return new CreateProductionVersionUseCase($this, $this->lookup(), $this->snapshot(), $this->archives, $this->view(), new ProductionStatePolicy(), $this);
    }

    public function renew(): RenewProductionVersionUseCase
    {
        return new RenewProductionVersionUseCase($this, $this->lookup(), $this->archives, $this->links, $this->view(), $this);
    }

    public function groupView(): GetGroupProductionUseCase
    {
        return new GetGroupProductionUseCase($this, $this->lookup(), $this->archives, $this, $this->view(), new ProductionStatePolicy(), new ArchivePolicy(), $this);
    }

    public function list(): ListProductionGroupsUseCase
    {
        return new ListProductionGroupsUseCase($this, $this, $this->lookup(), $this->archives, new ProductionStatePolicy(), $this);
    }

    public function version(): GetProductionVersionUseCase
    {
        return new GetProductionVersionUseCase($this, $this->lookup(), $this->view(), $this);
    }

    public function export(): ExportProductionVersionUseCase
    {
        return new ExportProductionVersionUseCase($this, $this->lookup(), $this->archives, $this, new CsvManifestWriter());
    }

    public function open(): OpenProductionArchiveUseCase
    {
        return new OpenProductionArchiveUseCase($this->archives, $this->links, $this->storage(), $this);
    }

    public function storage(): LocalArchiveStorage
    {
        return new LocalArchiveStorage($this->root . '/files');
    }

    public function builder(): ArchiveBuilder
    {
        return new ArchiveBuilder($this->archives, $this, new ZipArchiveWriter(new AtomicZipWriter()), new CsvManifestWriter(), $this->storage(), $this->links, new ArchivePolicy(), $this, new NullLogger());
    }

    private function lookup(): ProductionLookup
    {
        return new ProductionLookup($this, $this->archives);
    }

    private function snapshot(): VersionSnapshot
    {
        return new VersionSnapshot($this->archives, $this, new ArchiveLayout(), $this->storage(), new ProductionIdGenerator());
    }

    private function view(): VersionView
    {
        return new VersionView($this->archives, $this->links);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function paidPrintLines(string $groupId, \DateTimeImmutable $deadline): array
    {
        return array_values(array_filter(
            $this->paid[$groupId] ?? [],
            static fn(PaidPrintLineOutputDto $line): bool => new \DateTimeImmutable($line->paidAt, new \DateTimeZone('UTC')) < $deadline,
        ));
    }

    public function waitingPrintOrders(string $groupId): int
    {
        return $this->waiting[$groupId] ?? 0;
    }

    public function page(GroupDirectoryQueryInputDto $query): GroupDirectoryPageOutputDto
    {
        throw new \LogicException('Not used by L1.');
    }

    public function find(string $groupId): ?GroupDirectoryItemOutputDto
    {
        ++$this->groupReads;

        return $this->groups[$groupId] ?? null;
    }

    public function summary(GroupDirectoryQueryInputDto $query, int $closingWithinHours): GroupDirectorySummaryOutputDto
    {
        throw new \LogicException('Not used by L1.');
    }

    public function nativeIds(GroupDirectoryQueryInputDto $query): array
    {
        throw new \LogicException('Not used by L1.');
    }

    public function closedBetween(\DateTimeImmutable $after, int $afterGroupId, \DateTimeImmutable $until, int $limit): array
    {
        $items = [];
        foreach ($this->groups as $group) {
            if (null === $group->calendar->closesAt) {
                continue;
            }
            $closes = new \DateTimeImmutable($group->calendar->closesAt);
            if ($closes <= $until && ($closes > $after || ($closes == $after && $group->nativeId > $afterGroupId))) {
                $items[] = $group;
            }
        }
        usort($items, static fn($a, $b): int => [new \DateTimeImmutable($a->calendar->closesAt), $a->nativeId] <=> [new \DateTimeImmutable($b->calendar->closesAt), $b->nativeId]);

        return array_slice($items, 0, $limit);
    }

    public function originals(array $photoIds): array
    {
        return array_intersect_key($this->originalFiles, array_flip($photoIds));
    }

    public function scope(int $actorId): ProductionScopeOutputDto
    {
        return match ($actorId) {
            self::ORGANIZER => new ProductionScopeOutputDto('organizer', null, true),
            self::CURATOR => new ProductionScopeOutputDto('curator', [7], false),
            self::FOREIGN_CURATOR => new ProductionScopeOutputDto('curator', [8], false),
            default => throw new HttpException('FORBIDDEN', 403),
        };
    }
}
