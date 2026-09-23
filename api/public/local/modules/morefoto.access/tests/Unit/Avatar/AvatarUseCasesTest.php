<?php

declare(strict_types=1);

namespace Morefoto\Access\Tests\Unit\Avatar;

use Bitrix\Main\ORM\Query\Result;
use Morefoto\Access\Application\Authorization\Service\StaffAuthorization;
use Morefoto\Access\Application\Avatar\Dto\SavedAvatarOutputDto;
use Morefoto\Access\Application\Avatar\Dto\UploadedAvatarInputDto;
use Morefoto\Access\Application\Avatar\Mapper\AvatarOutputMapper;
use Morefoto\Access\Application\Avatar\Service\AvatarAccess;
use Morefoto\Access\Application\Avatar\UseCase\DeleteStaffAvatarUseCase;
use Morefoto\Access\Application\Avatar\UseCase\GetStaffAvatarUseCase;
use Morefoto\Access\Application\Avatar\UseCase\SaveStaffAvatarUseCase;
use Morefoto\Access\Domain\Assignment\Repository\GroupAssignmentRepository;
use Morefoto\Access\Domain\Assignment\Repository\InstitutionAssignmentRepository;
use Morefoto\Access\Domain\Avatar\Enum\AvatarVariantEnum;
use Morefoto\Access\Domain\Staff\Repository\StaffProfileRepository;
use Morefoto\Access\Domain\Staff\Service\PermissionPolicy;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Application\Contract\Auth\Dto\IdentityOutputDto;
use Rebit\Share\Application\Contract\Auth\IdentityGatewayInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/AvatarFakes.php';

/**
 * @internal
 */
final class AvatarUseCasesTest extends TestCase
{
    private const int ORGANIZER = 1;
    private const int TEACHER = 21;
    private const int CURATOR = 7;

    private InMemoryStaffAvatars $avatars;
    private InMemoryAvatarFiles $files;

    protected function setUp(): void
    {
        $this->avatars = new InMemoryStaffAvatars([self::ORGANIZER, self::TEACHER, self::CURATOR]);
        $this->files = new InMemoryAvatarFiles();
    }

    public function testTheSameFileKeepsTheVersion(): void
    {
        $first = $this->save(self::ORGANIZER, self::CURATOR, 'photo-a');
        $again = $this->save(self::ORGANIZER, self::CURATOR, 'photo-a');

        self::assertSame(1, $first->avatar->version);
        self::assertSame(1, $again->avatar->version);
        self::assertSame(1, $this->files->writes);
        self::assertSame('/api/v1/users/7/avatar/64?v=1', $again->avatar->thumbUrl);
        self::assertSame('/api/v1/users/7/avatar/256?v=1', $again->avatar->fullUrl);
    }

    public function testANewFileIsTheNextVersionAndOldFilesGo(): void
    {
        $this->save(self::ORGANIZER, self::CURATOR, 'photo-a');

        $saved = $this->save(self::ORGANIZER, self::CURATOR, 'photo-b');

        self::assertSame(2, $saved->avatar->version);
        self::assertSame(['7/2-256', '7/2-64'], array_keys($this->files->files));
        self::assertSame(self::CURATOR, $saved->userId);
    }

    public function testOnlyStaffManagersChangeColleagues(): void
    {
        $this->assertHttpError(403, fn() => $this->save(self::TEACHER, self::CURATOR, 'photo-a'));
        $this->assertHttpError(403, fn() => $this->delete()->execute(self::TEACHER, self::CURATOR));

        self::assertSame(1, $this->save(self::TEACHER, self::TEACHER, 'selfie')->avatar->version);
        self::assertSame([], array_filter(array_keys($this->files->files), static fn(string $key): bool => str_starts_with($key, '7/')));
    }

    public function testUnknownEmployeeIsNotFound(): void
    {
        $this->assertHttpError(404, fn() => $this->save(self::ORGANIZER, 99, 'photo-a'));
    }

    public function testOnlyTheCurrentVersionIsServed(): void
    {
        $this->save(self::ORGANIZER, self::CURATOR, 'photo-a');
        $this->save(self::ORGANIZER, self::CURATOR, 'photo-b');

        $image = $this->get()->execute(self::TEACHER, self::CURATOR, AvatarVariantEnum::THUMB, 2);

        self::assertSame('thumb:photo-b', $image->content);
        self::assertSame('image/webp', $image->mimeType);
        self::assertSame('7-2-64', $image->etag);
        $this->assertHttpError(404, fn() => $this->get()->execute(self::TEACHER, self::CURATOR, AvatarVariantEnum::THUMB, 1));
        $this->assertHttpError(404, fn() => $this->get()->execute(self::TEACHER, self::ORGANIZER, AvatarVariantEnum::FULL, 1));
    }

    public function testDeletingReturnsToInitials(): void
    {
        $this->save(self::ORGANIZER, self::CURATOR, 'photo-a');

        $this->delete()->execute(self::ORGANIZER, self::CURATOR);
        $this->delete()->execute(self::ORGANIZER, self::CURATOR);

        self::assertNull($this->avatars->find(self::CURATOR));
        self::assertSame([], $this->files->files);
        $this->assertHttpError(404, fn() => $this->get()->execute(self::ORGANIZER, self::CURATOR, AvatarVariantEnum::FULL, 1));
    }

    private function save(int $actor, int $userId, string $content): SavedAvatarOutputDto
    {
        return (new SaveStaffAvatarUseCase(
            $this->access(),
            $this->avatars,
            new PathFingerprintInspector(),
            new LabelRenderer(),
            $this->files,
            new AvatarOutputMapper(),
        ))->execute($actor, $userId, new UploadedAvatarInputDto($content, 100));
    }

    private function delete(): DeleteStaffAvatarUseCase
    {
        return new DeleteStaffAvatarUseCase($this->access(), $this->avatars, $this->files);
    }

    private function get(): GetStaffAvatarUseCase
    {
        return new GetStaffAvatarUseCase($this->access(), $this->avatars, $this->files);
    }

    private function access(): AvatarAccess
    {
        $roles = [self::ORGANIZER => 'organizer', self::TEACHER => 'teacher', self::CURATOR => 'curator'];
        $profiles = $this->createStub(StaffProfileRepository::class);
        $profiles->method('findByUserId')->willReturnCallback(function(int $userId) use ($roles): Result {
            $result = $this->createStub(Result::class);
            $result->method('fetch')->willReturn(isset($roles[$userId])
                ? ['UF_USER_ID' => $userId, 'UF_ROLE' => $roles[$userId], 'UF_ACTIVE' => 1, 'UF_REVISION' => 1, 'UF_ACCESS_REVISION' => 1]
                : false);

            return $result;
        });
        $identities = $this->createStub(IdentityGatewayInterface::class);
        $identities->method('findActive')->willReturnCallback(static fn(int $userId): IdentityOutputDto => new IdentityOutputDto($userId, 'Staff', 'staff@example.invalid'));

        return new AvatarAccess(new StaffAuthorization(
            $profiles,
            $identities,
            new PermissionPolicy(),
            $this->createStub(InstitutionAssignmentRepository::class),
            $this->createStub(GroupAssignmentRepository::class),
        ));
    }

    private function assertHttpError(int $status, callable $call): void
    {
        try {
            $call();
            self::fail('Expected HTTP ' . $status);
        } catch (HttpException $error) {
            self::assertSame($status, $error->getCode());
        }
    }
}
