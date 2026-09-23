<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Transfer\Dto\TransferChildInputDto;
use Morefoto\Media\Application\Transfer\Service\ChildTransfers;
use Morefoto\Media\Application\Transfer\UseCase\TransferChildUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use Morefoto\Media\Domain\Transfer\Repository\ChildTransferRepository;
use Morefoto\Media\Domain\Transfer\Service\ChildTransferPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Commerce\ChildOrdersInterface;
use Rebit\Share\Contracts\Media\Dto\ChildMoveInputDto;
use Rebit\Share\Contracts\Media\Dto\ChildSetOutputDto;
use Rebit\Share\Contracts\Media\Dto\ChildSetPhotoOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class TransferChildUseCaseTest extends TestCase
{
    private const string SHOOT = '12345678-abcd-4abc-8abc-123456789abc';
    private const string FROM = '22345678-abcd-4abc-8abc-123456789abc';
    private const string TO = '32345678-abcd-4abc-8abc-123456789abc';
    private const string PHOTO_A = '42345678-abcd-4abc-8abc-123456789abc';
    private const string PHOTO_B = '52345678-abcd-4abc-8abc-123456789abc';

    public function testMovesTheCompleteSetKeepingIdsAndRemembersTheResult(): void
    {
        $children = $this->createMock(ChildTransfers::class);
        $children->method('lockSets')->willReturn([11 => $this->set()]);
        $children->expects(self::once())->method('move')->with(2, [new ChildMoveInputDto(11, 4, 'B')])->willReturn(6);
        $media = $this->createMock(MediaMutationRepository::class);
        $this->configureMedia($media, false);
        $media->expects(self::once())->method('saveIdempotency')->with(9, '/shoots/' . self::SHOOT . '/child-transfers', self::anything(), self::anything(), self::callback(static fn(string $json): bool => str_contains($json, '"childCode":"B"')));

        $output = $this->useCase(media: $media, children: $children)->execute(9, $this->key(), $this->input());

        self::assertSame([self::PHOTO_A, self::PHOTO_B], $output->photoIds);
        self::assertSame('B', $output->childCode);
        self::assertSame(6, $output->revision);
        self::assertSame(self::FROM, $output->fromGroupId);
        self::assertSame(self::TO, $output->toGroupId);
    }

    public function testTheSameKeyReplaysAndAnotherBodyConflicts(): void
    {
        $input = $this->input();
        $hash = hash('sha256', json_encode([
            'fromGroupId' => $input->fromGroupId,
            'toGroupId' => $input->toGroupId,
            'childCode' => $input->childCode,
            'targetCode' => $input->targetCode,
            'expectedPhotoIds' => $input->expectedPhotoIds,
            'revision' => $input->revision,
        ], JSON_THROW_ON_ERROR));
        $stored = ['PAYLOAD_HASH' => $hash, 'RESULT_JSON' => json_encode(['photoIds' => [self::PHOTO_A], 'fromGroupId' => self::FROM, 'toGroupId' => self::TO, 'childCode' => 'B', 'revision' => 6])];
        $children = $this->createMock(ChildTransfers::class);
        $children->expects(self::never())->method('move');

        $output = $this->useCase(media: $this->media($stored), children: $children)->execute(9, $this->key(), $input);
        self::assertSame(6, $output->revision);

        $this->expectExceptionMessage('IDEMPOTENCY_CONFLICT');
        $this->useCase(media: $this->media(['PAYLOAD_HASH' => str_repeat('0', 64), 'RESULT_JSON' => '{}']), children: $children)->execute(9, $this->key(), $input);
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('refusals')]
    public function testRefusalsChangeNothing(string $code, array $case): void
    {
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturnCallback(fn(string $shoot, ?string $group): MediaScopeOutputDto => self::FROM === $group
            ? $this->scope(3, self::FROM, true, 'regular')
            : $this->scope(4, self::TO, $case['toEditable'] ?? true, $case['toKind'] ?? 'regular'));
        $transfers = $this->createStub(ChildTransferRepository::class);
        $transfers->method('childId')->willReturn(array_key_exists('childId', $case) ? $case['childId'] : 11);
        $transfers->method('codes')->willReturn($case['codes'] ?? ['A']);
        $children = $this->createMock(ChildTransfers::class);
        $children->method('lockSets')->willReturn([11 => $this->set($case['shared'] ?? [])]);
        $children->expects(self::never())->method('move');
        $orders = $this->createStub(ChildOrdersInterface::class);
        $orders->method('withOrders')->willReturn($case['orders'] ?? []);
        $input = new TransferChildInputDto(self::SHOOT, self::FROM, self::TO, 'A', 'B', $case['expected'] ?? [self::PHOTO_B, self::PHOTO_A], $case['revision'] ?? 5);

        try {
            $this->useCase(transfers: $transfers, children: $children, scopes: $scopes, orders: $orders)->execute(9, $this->key(), $input);
            self::fail('Expected ' . $code);
        } catch (HttpException $error) {
            self::assertSame($code, $error->getMessage());
            self::assertSame('SHARED_PHOTO' === $code ? ['photoCodes' => ['A002']] : [], $error->getDetails());
        }
    }

    /** @return iterable<string, array{0: string, 1: array<string, mixed>}> */
    public static function refusals(): iterable
    {
        yield 'another group kind' => ['GROUP_KIND_MISMATCH', ['toKind' => 'staff']];
        yield 'opened group' => ['GROUP_LOCKED', ['toEditable' => false]];
        yield 'stale revision' => ['REVISION_CONFLICT', ['revision' => 4]];
        yield 'child left the group' => ['SET_CHANGED', ['childId' => null]];
        yield 'set grew after reading' => ['SET_CHANGED', ['expected' => [self::PHOTO_A]]];
        yield 'shared frame' => ['SHARED_PHOTO', ['shared' => ['A002']]];
        yield 'target code taken' => ['TARGET_CODE_TAKEN', ['codes' => ['B']]];
        yield 'purchased frames' => ['CHILD_HAS_ORDERS', ['orders' => [11 => true]]];
    }

    public function testAccessRefusalsAreReportedWithCodes(): void
    {
        $access = $this->createStub(AccessGuardInterface::class);
        $access->method('assertCan')->willThrowException(new HttpException('Action is forbidden.', 403));
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::never())->method('lockRevision');

        $this->expectExceptionMessage('FORBIDDEN');
        $this->useCase(media: $media, access: $access)->execute(9, $this->key(), $this->input());
    }

    private function useCase(
        ?MediaMutationRepository $media = null,
        ?ChildTransferRepository $transfers = null,
        ?ChildTransfers $children = null,
        ?MediaScopeInterface $scopes = null,
        ?AccessGuardInterface $access = null,
        ?ChildOrdersInterface $orders = null,
    ): TransferChildUseCase {
        if (null === $transfers) {
            $transfers = $this->createStub(ChildTransferRepository::class);
            $transfers->method('childId')->willReturn(11);
            $transfers->method('codes')->willReturn(['A']);
        }
        if (null === $scopes) {
            $scopes = $this->createStub(MediaScopeInterface::class);
            $scopes->method('resolve')->willReturnCallback(fn(string $shoot, ?string $group): MediaScopeOutputDto => self::FROM === $group
                ? $this->scope(3, self::FROM, true, 'regular')
                : $this->scope(4, self::TO, true, 'regular'));
        }

        return new TransferChildUseCase(
            new class implements MediaTransactionInterface {
                public function execute(callable $operation): mixed
                {
                    return $operation();
                }
            },
            $media ?? $this->media(),
            $transfers,
            $children ?? $this->children($this->set()),
            new ChildTransferPolicy(),
            $scopes,
            $access ?? $this->createStub(AccessGuardInterface::class),
            $orders ?? $this->createStub(ChildOrdersInterface::class),
        );
    }

    /** @param array{PAYLOAD_HASH: string, RESULT_JSON: string}|false $stored */
    private function media(array|false $stored = false): MediaMutationRepository
    {
        $media = $this->createStub(MediaMutationRepository::class);
        $this->configureMedia($media, $stored);

        return $media;
    }

    /** @param array{PAYLOAD_HASH: string, RESULT_JSON: string}|false $stored */
    private function configureMedia(Stub $media, array|false $stored): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn($stored);
        $media->method('lockRevision')->willReturn(5);
        $media->method('idempotency')->willReturn($result);
    }

    /** @param list<string> $shared */
    private function set(array $shared = []): ChildSetOutputDto
    {
        return new ChildSetOutputDto(11, 'child-11', 3, 'A', [
            new ChildSetPhotoOutputDto(101, self::PHOTO_A, 'A001', 1, 'ready'),
            new ChildSetPhotoOutputDto(102, self::PHOTO_B, 'A002', 1, 'ready'),
        ], $shared);
    }

    private function children(ChildSetOutputDto $set): ChildTransfers
    {
        $children = $this->createStub(ChildTransfers::class);
        $children->method('lockSets')->willReturn([11 => $set]);

        return $children;
    }

    private function scope(int $groupId, string $groupPublicId, bool $editable, string $kind): MediaScopeOutputDto
    {
        return new MediaScopeOutputDto(1, 2, self::SHOOT, $groupId, $groupPublicId, $editable, null, $kind);
    }

    private function input(): TransferChildInputDto
    {
        return new TransferChildInputDto(self::SHOOT, self::FROM, self::TO, 'A', 'B', [self::PHOTO_B, self::PHOTO_A], 5);
    }

    private function key(): IdempotencyKey
    {
        return new IdempotencyKey(str_repeat('c', 32));
    }
}
