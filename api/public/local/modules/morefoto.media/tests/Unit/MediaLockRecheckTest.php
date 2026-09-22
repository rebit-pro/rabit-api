<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Photo\Dto\AssignPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\SetCoverInputDto;
use Morefoto\Media\Application\Photo\UseCase\AssignPhotosUseCase;
use Morefoto\Media\Application\Photo\UseCase\SetGroupCoverUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\ValueObject\IdempotencyKey;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupReferenceOutputDto;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\GroupReferenceInterface;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class MediaLockRecheckTest extends TestCase
{
    private const string SHOOT = '12345678-abcd-4abc-8abc-123456789abc';
    private const string GROUP = '22345678-abcd-4abc-8abc-123456789abc';

    public function testAssignmentWaitingForTheLinkDeliveryIsRejectedAfterTheLock(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::once())->method('lockRevision')->with(2)->willReturn(4);
        $media->expects(self::never())->method('assign');
        $useCase = new AssignPhotosUseCase($this->transaction(), $media, $this->scopes(), $this->createStub(AccessGuardInterface::class));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_LOCKED');
        $useCase->execute(4, self::GROUP, new IdempotencyKey(str_repeat('a', 32)), new AssignPhotosInputDto(self::SHOOT, 4, ['32345678-abcd-4abc-8abc-123456789abc'], 'A'));
    }

    public function testCoverWaitingForTheLinkDeliveryIsRejectedAfterTheLock(): void
    {
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::once())->method('lockRevision')->with(2)->willReturn(4);
        $media->expects(self::never())->method('setCover');
        $groups = $this->createStub(GroupReferenceInterface::class);
        $groups->method('get')->willReturn(new GroupReferenceOutputDto(3, self::GROUP, self::SHOOT, 'regular'));
        $useCase = new SetGroupCoverUseCase($this->transaction(), $media, $groups, $this->scopes(), $this->createStub(AccessGuardInterface::class));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_LOCKED');
        $useCase->execute(4, self::GROUP, new IdempotencyKey(str_repeat('b', 32)), new SetCoverInputDto(4, '32345678-abcd-4abc-8abc-123456789abc'));
    }

    /** The first read happens before the lock, the second one after the delivery committed. */
    private function scopes(): MediaScopeInterface
    {
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturnOnConsecutiveCalls($this->scope(true), $this->scope(false));

        return $scopes;
    }

    private function scope(bool $editable): MediaScopeOutputDto
    {
        return new MediaScopeOutputDto(institutionId: 1, shootId: 2, shootPublicId: self::SHOOT, groupId: 3, groupPublicId: self::GROUP, groupEditable: $editable);
    }

    private function transaction(): MediaTransactionInterface
    {
        return new class implements MediaTransactionInterface {
            public function execute(callable $operation): mixed
            {
                return $operation();
            }
        };
    }
}
