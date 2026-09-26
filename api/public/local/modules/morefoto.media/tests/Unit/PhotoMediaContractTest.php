<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Photo\Contract\MediaPublisherInterface;
use Morefoto\Media\Application\Photo\Dto\AssignmentMutationOutputDto;
use Morefoto\Media\Application\Photo\Dto\AssignPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\CoverMutationOutputDto;
use Morefoto\Media\Application\Photo\Dto\DeletePhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\DeletionMutationOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoAssignmentOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoOutputDto;
use Morefoto\Media\Application\Photo\Dto\SetCoverInputDto;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoInputDto;
use Morefoto\Media\Application\Photo\Dto\UploadPhotoOutputDto;
use Morefoto\Media\Application\Photo\UseCase\UploadPhotoUseCase;
use Morefoto\Media\Presentation\Photo\Dto\AssignPhotosRequestDto;
use Morefoto\Media\Presentation\Photo\Dto\DeleteGroupPhotosRequestDto;
use Morefoto\Media\Presentation\Photo\Dto\SetGroupCoverRequestDto;
use Morefoto\Media\Presentation\Photo\Dto\UploadPhotoRequestDto;
use Morefoto\Media\Presentation\Photo\PhotoInputMapper;
use Morefoto\Media\Presentation\Photo\PhotoResultMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class PhotoMediaContractTest extends TestCase
{
    private const string SHOOT = '12345678-abcd-4abc-8abc-123456789abc';
    private const string GROUP = '22345678-abcd-4abc-8abc-123456789abc';
    private const string PHOTO = '32345678-abcd-4abc-8abc-123456789abc';
    private const string OTHER = '42345678-abcd-4abc-8abc-123456789abc';
    private const string KEY = 'abcdefabcdefabcdefabcdefabcdefab';

    public function testUploadRequestBecomesTheScenarioInput(): void
    {
        $mapper = new PhotoInputMapper();

        self::assertEquals(
            new UploadPhotoInputDto(self::SHOOT, self::GROUP, '/tmp/php-upload', 'IMG_0001.jpg', 2048, str_repeat('a', 64)),
            $mapper->upload($this->upload(str_repeat('A', 64))),
        );
        self::assertNull($mapper->upload($this->upload(''))->clientFingerprint);
        self::assertNull($mapper->upload($this->upload(null))->clientFingerprint);
    }

    public function testAssignmentBodyBecomesTheScenarioInput(): void
    {
        self::assertEquals(
            new AssignPhotosInputDto(self::SHOOT, 3, [self::PHOTO, self::OTHER], 'AB'),
            (new PhotoInputMapper())->assignment($this->assignment(photoIds: [self::PHOTO, self::OTHER], childCode: 'AB')),
        );
    }

    /** @param list<mixed> $photoIds */
    #[DataProvider('invalidAssignments')]
    public function testInvalidAssignmentsKeepTheirCodes(string $code, string $shootId, int $revision, array $photoIds, string $childCode): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        $this->expectExceptionCode(422);

        (new PhotoInputMapper())->assignment($this->assignment($shootId, $revision, $photoIds, $childCode));
    }

    /** @return iterable<string, array{0: string, 1: string, 2: int, 3: list<mixed>, 4: string}> */
    public static function invalidAssignments(): iterable
    {
        yield 'shoot is not an id' => ['VALIDATION_FAILED', 'shoot-1', 3, [self::PHOTO], 'A'];
        yield 'zero revision' => ['VALIDATION_FAILED', self::SHOOT, 0, [self::PHOTO], 'A'];
        yield 'empty set' => ['VALIDATION_FAILED', self::SHOOT, 3, [], 'A'];
        yield 'set above limit' => ['VALIDATION_FAILED', self::SHOOT, 3, array_fill(0, 101, self::PHOTO), 'A'];
        yield 'frame code instead of child code' => ['VALIDATION_FAILED', self::SHOOT, 3, [self::PHOTO], 'A001'];
        yield 'duplicate photo' => ['INVALID_PHOTO_IDS', self::SHOOT, 3, [self::PHOTO, self::PHOTO], 'A'];
        yield 'photo is not an id' => ['INVALID_PHOTO_IDS', self::SHOOT, 3, ['photo-1'], 'A'];
        yield 'photo is not a string' => ['INVALID_PHOTO_IDS', self::SHOOT, 3, [42], 'A'];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('assignmentBodies')]
    public function testStrictAssignmentBodyKeepsItsCodes(string $code, array $body): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        $this->expectExceptionCode(422);

        $values = StrictRequestValues::normalize($body + ['groupId' => self::GROUP, 'idempotencyKey' => self::KEY], AssignPhotosRequestDto::class, true);
        (new PhotoInputMapper())->assignment(ArrayToDtoMapper::map($values, AssignPhotosRequestDto::class));
    }

    /** @return iterable<string, array{0: string, 1: array<string, mixed>}> */
    public static function assignmentBodies(): iterable
    {
        $body = ['shootId' => self::SHOOT, 'revision' => 3, 'photoIds' => [self::PHOTO], 'childCode' => 'A'];

        yield 'photo id is a number' => ['INVALID_PHOTO_IDS', ['photoIds' => [42]] + $body];
        yield 'photo ids are an object' => ['VALIDATION_FAILED', ['photoIds' => new \stdClass()] + $body];
        yield 'revision is a string' => ['VALIDATION_FAILED', ['revision' => '3'] + $body];
        yield 'extra field' => ['UNKNOWN_FIELD', $body + ['extra' => true]];
        yield 'missing field' => ['UNKNOWN_FIELD', array_diff_key($body, ['childCode' => true])];
    }

    public function testCoverBodyBecomesTheScenarioInput(): void
    {
        self::assertEquals(new SetCoverInputDto(4, self::PHOTO), (new PhotoInputMapper())->cover($this->cover(4, self::PHOTO)));
    }

    #[DataProvider('invalidCovers')]
    public function testInvalidCoversAreRejected(int $revision, string $photoId): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('VALIDATION_FAILED');
        $this->expectExceptionCode(422);

        (new PhotoInputMapper())->cover($this->cover($revision, $photoId));
    }

    /** @return iterable<string, array{0: int, 1: string}> */
    public static function invalidCovers(): iterable
    {
        yield 'zero revision' => [0, self::PHOTO];
        yield 'photo is not an id' => [4, 'photo-1'];
    }

    public function testDeletionBodyBecomesTheScenarioInput(): void
    {
        self::assertEquals(
            new DeletePhotosInputDto(5, [self::PHOTO, self::OTHER]),
            (new PhotoInputMapper())->deletion(new DeleteGroupPhotosRequestDto(5, [self::PHOTO, self::OTHER], self::GROUP, self::KEY)),
        );
    }

    /** @param list<mixed> $photoIds */
    #[DataProvider('invalidDeletions')]
    public function testInvalidDeletionsAreRejected(string $code, int $revision, array $photoIds): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        $this->expectExceptionCode(422);

        (new PhotoInputMapper())->deletion(new DeleteGroupPhotosRequestDto($revision, $photoIds, self::GROUP, self::KEY));
    }

    /** @return iterable<string, array{0: string, 1: int, 2: list<mixed>}> */
    public static function invalidDeletions(): iterable
    {
        yield 'zero revision' => ['VALIDATION_FAILED', 0, [self::PHOTO]];
        yield 'empty set' => ['VALIDATION_FAILED', 3, []];
        yield 'set above limit' => ['VALIDATION_FAILED', 3, array_fill(0, 101, self::PHOTO)];
        yield 'duplicate photo' => ['INVALID_PHOTO_IDS', 3, [self::PHOTO, self::PHOTO]];
        yield 'photo is not an id' => ['INVALID_PHOTO_IDS', 3, ['photo-1']];
    }

    public function testStrictDeletionBodyRejectsUnknownFields(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('UNKNOWN_FIELD');

        StrictRequestValues::normalize(
            ['revision' => 3, 'photoIds' => [self::PHOTO], 'shootId' => self::SHOOT, 'groupId' => self::GROUP, 'idempotencyKey' => self::KEY],
            DeleteGroupPhotosRequestDto::class,
            true,
        );
    }

    public function testIdempotencyKeyIsValidated(): void
    {
        self::assertSame(self::KEY, (new PhotoInputMapper())->key(strtoupper(self::KEY))->value);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('INVALID_IDEMPOTENCY_KEY');
        (new PhotoInputMapper())->key('');
    }

    public function testResultsKeepThePreviousResponseBodies(): void
    {
        $mapper = new PhotoResultMapper();
        $photo = new PhotoOutputDto(
            id: self::PHOTO,
            status: 'ready',
            shootId: self::SHOOT,
            groupId: self::GROUP,
            originalGroupId: self::GROUP,
            childCode: 'A',
            code: 'A001',
            sequence: 1,
            assignments: [new PhotoAssignmentOutputDto(self::OTHER, 'A', 1, 'A001')],
            filename: 'IMG_0001.jpg',
            bytes: 1024,
            width: 640,
            height: 480,
            fingerprint: str_repeat('a', 64),
            revision: 2,
            thumbSrc: '/api/v1/photos/' . self::PHOTO . '/thumb',
            previewSrc: '/api/v1/photos/' . self::PHOTO . '/preview',
        );
        $failed = new PhotoOutputDto(self::PHOTO, 'failed', self::SHOOT, self::GROUP, self::GROUP, null, null, null, [], 'x.jpg', 1, 1, 1, str_repeat('b', 64), 1, error: 'CORRUPTED_PHOTO');
        $uploaded = new UploadPhotoOutputDto(self::PHOTO, 'processing', 1);
        $duplicate = new UploadPhotoOutputDto(self::PHOTO, 'duplicate', 1, self::OTHER);
        $assigned = new AssignmentMutationOutputDto([self::PHOTO], 'B', self::OTHER, 3);
        $cover = new CoverMutationOutputDto(self::PHOTO, 4);
        $deleted = new DeletionMutationOutputDto(2, 5);

        self::assertSame($this->json($photo), $this->json($mapper->photo($photo)));
        self::assertSame($this->json($failed), $this->json($mapper->photo($failed)));
        self::assertSame($this->json($uploaded), $this->json($mapper->upload($uploaded)));
        self::assertSame($this->json($duplicate), $this->json($mapper->upload($duplicate)));
        self::assertSame($this->json($assigned), $this->json($mapper->assignment($assigned)));
        self::assertSame($this->json($cover), $this->json($mapper->cover($cover)));
        self::assertSame(['deleted' => 2, 'revision' => 5], $this->json($mapper->deletion($deleted)));
        self::assertSame(['id', 'status', 'revision', 'existingPhotoId'], array_keys($this->json($mapper->upload($uploaded))));
    }

    /** @param list<string> $useCases */
    #[DataProvider('controllers')]
    public function testControllersKeepTheCleanBoundary(string $controller, array $useCases): void
    {
        $path = dirname(__DIR__, 2) . '/lib/Presentation/Controller/' . $controller . '.php';
        self::assertSame([], CleanControllerSource::violations(
            $path,
            [
                'Morefoto\Media\Presentation\Controller',
                'Morefoto\Media\Presentation\Photo\PhotoInputMapper',
                'Morefoto\Media\Presentation\Photo\PhotoResultMapper',
                'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
            ],
            [
                ['Morefoto\Media\Application\Photo\UseCase\\', 'UseCase'],
                ['Morefoto\Media\Presentation\Photo\Dto\\', 'RequestDto'],
            ],
        ));
        $source = (string)file_get_contents($path);
        self::assertStringNotContainsString('setStatus', $source);
        foreach ($useCases as $useCase) {
            self::assertStringContainsString('use Morefoto\Media\Application\Photo\UseCase\\' . $useCase . ';', $source);
        }
        self::assertSame(LogChannelEnum::media, LogChannelEnum::resolveFromClassName('Morefoto\Media\Presentation\Controller\\' . $controller));
    }

    /** @return iterable<string, array{0: string, 1: list<string>}> */
    public static function controllers(): iterable
    {
        yield 'MED-03 upload' => ['PhotoUploadController', ['UploadPhotoUseCase']];
        yield 'MED-04 detail' => ['PhotoDetailController', ['GetPhotoUseCase']];
        yield 'MED-05/06 grouping' => ['GroupMediaController', ['AssignPhotosUseCase', 'SetGroupCoverUseCase', 'DeleteGroupPhotosUseCase']];
    }

    #[DataProvider('messageFreeControllers')]
    public function testReadingAndGroupingDoNotNeedTheMessageTransport(string $controller): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/Presentation/Controller/' . $controller . '.php');
        self::assertGreaterThan(1, (int)preg_match_all('/^use (Morefoto\\\Media\\\(?:Application|Presentation\\\Photo)\\\[^;]+);$/m', $source, $matches));
        foreach ($matches[1] as $dependency) {
            self::assertNotSame(UploadPhotoUseCase::class, $dependency);
            foreach ((new \ReflectionClass($dependency))->getConstructor()?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();
                self::assertInstanceOf(\ReflectionNamedType::class, $type);
                self::assertNotContains($type->getName(), [UploadPhotoUseCase::class, MediaPublisherInterface::class], $dependency);
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function messageFreeControllers(): iterable
    {
        yield 'MED-04 detail' => ['PhotoDetailController'];
        yield 'MED-05/06 grouping' => ['GroupMediaController'];
    }

    private function upload(?string $fingerprint): UploadPhotoRequestDto
    {
        return new UploadPhotoRequestDto(
            shootId: self::SHOOT,
            tmpName: '/tmp/php-upload',
            filename: 'IMG_0001.jpg',
            bytes: 2048,
            groupId: self::GROUP,
            fingerprint: $fingerprint,
        );
    }

    /** @param list<mixed> $photoIds */
    private function assignment(string $shootId = self::SHOOT, int $revision = 3, array $photoIds = [self::PHOTO], string $childCode = 'A'): AssignPhotosRequestDto
    {
        return new AssignPhotosRequestDto(
            shootId: $shootId,
            revision: $revision,
            photoIds: $photoIds,
            childCode: $childCode,
            groupId: self::GROUP,
            idempotencyKey: self::KEY,
        );
    }

    private function cover(int $revision, string $photoId): SetGroupCoverRequestDto
    {
        return new SetGroupCoverRequestDto(revision: $revision, photoId: $photoId, groupId: self::GROUP, idempotencyKey: self::KEY);
    }

    /** @return array<string, mixed> */
    private function json(object $result): array
    {
        $decoded = json_decode(CommonSerializer::createDefault()->serialize($result), true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
