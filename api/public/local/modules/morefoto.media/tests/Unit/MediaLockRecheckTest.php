<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Photo\Contract\MediaTransactionInterface;
use Morefoto\Media\Application\Photo\Dto\AssignPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\SetCoverInputDto;
use Morefoto\Media\Application\Photo\Service\GroupMarkupAccess;
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
        $useCase = new AssignPhotosUseCase($this->transaction(), $media, $this->scopes(), new GroupMarkupAccess($this->createStub(AccessGuardInterface::class)));

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
        $useCase = new SetGroupCoverUseCase($this->transaction(), $media, $groups, $this->scopes(), new GroupMarkupAccess($this->createStub(AccessGuardInterface::class)));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_LOCKED');
        $useCase->execute(4, self::GROUP, new IdempotencyKey(str_repeat('b', 32)), new SetCoverInputDto(4, '32345678-abcd-4abc-8abc-123456789abc'));
    }

    public function testCuratorCorrectsATransmittedGroupOnlyWhileItsSalesArePaused(): void
    {
        // D4-T03/T04: during a correction the curator needs media.correct; a finished correction locks the group again.
        $access = $this->createMock(AccessGuardInterface::class);
        $access->expects(self::exactly(2))->method('assertCan')->with(5, 'media.correct', 1, 3);
        $media = $this->createMock(MediaMutationRepository::class);
        $media->expects(self::exactly(2))->method('lockRevision')->with(2)->willReturn(4);
        $media->expects(self::never())->method('assign');
        $media->method('idempotency')->willThrowException(new \LogicException('reached the write'));
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturnOnConsecutiveCalls($this->corrected(true), $this->corrected(true), $this->corrected(true), $this->corrected(false));
        $useCase = new AssignPhotosUseCase($this->transaction(), $media, $scopes, new GroupMarkupAccess($access));
        $input = new AssignPhotosInputDto(self::SHOOT, 4, ['32345678-abcd-4abc-8abc-123456789abc'], 'A');

        try {
            $useCase->execute(5, self::GROUP, new IdempotencyKey(str_repeat('c', 32)), $input);
            self::fail('A paused group must reach the write.');
        } catch (\LogicException $reached) {
            self::assertSame('reached the write', $reached->getMessage());
        }
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_LOCKED');
        $useCase->execute(5, self::GROUP, new IdempotencyKey(str_repeat('d', 32)), $input);
    }

    public function testTransmittedGroupOutsideACorrectionNeedsTheOrganizerAndStaysLocked(): void
    {
        $access = $this->createMock(AccessGuardInterface::class);
        $access->expects(self::once())->method('assertCan')->with(5, 'media.manage', 1, 3);
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturn($this->scope(false));
        $useCase = new AssignPhotosUseCase($this->transaction(), $this->createStub(MediaMutationRepository::class), $scopes, new GroupMarkupAccess($access));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('GROUP_LOCKED');
        $useCase->execute(5, self::GROUP, new IdempotencyKey(str_repeat('e', 32)), new AssignPhotosInputDto(self::SHOOT, 4, ['32345678-abcd-4abc-8abc-123456789abc'], 'A'));
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

    private function corrected(bool $paused): MediaScopeOutputDto
    {
        return new MediaScopeOutputDto(institutionId: 1, shootId: 2, shootPublicId: self::SHOOT, groupId: 3, groupPublicId: self::GROUP, groupEditable: false, groupCorrecting: $paused);
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
