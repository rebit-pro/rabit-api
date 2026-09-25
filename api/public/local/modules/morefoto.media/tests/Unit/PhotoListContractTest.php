<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Bitrix\Main\DB\Result;
use Morefoto\Media\Application\Photo\Dto\ListPhotosInputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoAssignmentOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoGroupSummaryOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoOutputDto;
use Morefoto\Media\Application\Photo\Dto\PhotoPageOutputDto;
use Morefoto\Media\Application\Photo\Service\PhotoRowMapper;
use Morefoto\Media\Application\Photo\UseCase\ListPhotosUseCase;
use Morefoto\Media\Domain\Photo\Repository\MediaMutationRepository;
use Morefoto\Media\Domain\Photo\Repository\PhotoRepository;
use Morefoto\Media\Presentation\Photo\Dto\ListPhotosRequestDto;
use Morefoto\Media\Presentation\Photo\PhotoListInputMapper;
use Morefoto\Media\Presentation\Photo\PhotoListResultMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Contracts\Access\AccessGuardInterface;
use Rebit\Share\Contracts\Organization\Dto\MediaScopeOutputDto;
use Rebit\Share\Contracts\Organization\MediaScopeInterface;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class PhotoListContractTest extends TestCase
{
    private const string SHOOT = '12345678-abcd-4abc-8abc-123456789abc';
    private const string GROUP = '22345678-abcd-4abc-8abc-123456789abc';
    private const string PHOTO = '32345678-abcd-4abc-8abc-123456789abc';

    public function testValidQueryBecomesTheScenarioInput(): void
    {
        $input = (new PhotoListInputMapper())->list(new ListPhotosRequestDto(
            shootId: self::SHOOT,
            groupId: self::GROUP,
            childCode: 'AB',
            assigned: 'false',
            status: 'ready',
            page: 3,
            pageSize: 48,
        ));

        self::assertEquals(new ListPhotosInputDto(self::GROUP, 3, 48, 'AB', false, 'ready'), $input);
        self::assertEquals(
            new ListPhotosInputDto(null, 1, 50, null, null, null),
            (new PhotoListInputMapper())->list(new ListPhotosRequestDto(shootId: self::SHOOT)),
        );
    }

    #[DataProvider('invalidQueries')]
    public function testInvalidQueriesKeepTheirErrorCodes(string $code, ListPhotosRequestDto $request): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        $this->expectExceptionCode(422);

        (new PhotoListInputMapper())->list($request);
    }

    /** @return iterable<string, array{0: string, 1: ListPhotosRequestDto}> */
    public static function invalidQueries(): iterable
    {
        yield 'group is not an id' => ['INVALID_GROUP', new ListPhotosRequestDto(shootId: self::SHOOT, groupId: 'group-1')];
        yield 'zero page' => ['INVALID_PAGE', new ListPhotosRequestDto(shootId: self::SHOOT, page: 0)];
        yield 'page size above limit' => ['INVALID_PAGE', new ListPhotosRequestDto(shootId: self::SHOOT, pageSize: 101)];
        yield 'frame code instead of child code' => ['INVALID_CHILD_CODE', new ListPhotosRequestDto(shootId: self::SHOOT, childCode: 'A001')];
        yield 'assigned is not a flag' => ['INVALID_ASSIGNED_FILTER', new ListPhotosRequestDto(shootId: self::SHOOT, assigned: 'yes')];
        yield 'unknown status' => ['INVALID_STATUS', new ListPhotosRequestDto(shootId: self::SHOOT, status: 'deleted')];
    }

    public function testGroupPageFiltersByStatusAndSummarisesReadyFrames(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->expects(self::once())->method('photos')->with(2, 3, null, null, 'ready', 48, 48)->willReturn($this->emptyResult());
        $counts = [];
        $photos->method('count')->willReturnCallback(static function(int $shootId, ?int $groupId, ?string $childCode, ?bool $assigned, ?string $status) use (&$counts): int {
            $counts[] = [$shootId, $groupId, $childCode, $assigned, $status];

            return match ($assigned) {
                false => 7,
                default => 60,
            };
        });
        $photos->expects(self::once())->method('childCodes')->with(2, 3)->willReturn(['A', 'AA', 'B']);

        $page = $this->useCase($photos, 3)->execute(4, self::SHOOT, new ListPhotosInputDto(self::GROUP, 2, 48, null, null, 'ready'));

        self::assertSame(['page' => 2, 'pageSize' => 48, 'total' => 60], $page->meta);
        self::assertEquals(new PhotoGroupSummaryOutputDto(photos: 60, unassigned: 7, children: ['A', 'AA', 'B']), $page->summary);
        self::assertSame([
            [2, 3, null, null, 'ready'],
            [2, 3, null, null, 'ready'],
            [2, 3, null, false, 'ready'],
        ], $counts);
    }

    public function testShootPageHasNoGroupSummary(): void
    {
        $photos = $this->createMock(PhotoRepository::class);
        $photos->method('photos')->willReturn($this->emptyResult());
        $photos->method('count')->willReturn(0);
        $photos->expects(self::never())->method('childCodes');

        $page = $this->useCase($photos, null)->execute(4, self::SHOOT, new ListPhotosInputDto(null, 1, 50, null, null, null));

        self::assertNull($page->summary);
    }

    public function testResultKeepsThePublicContractWithSummary(): void
    {
        $mapper = new PhotoListResultMapper();
        $photo = new PhotoOutputDto(
            id: self::PHOTO,
            status: 'ready',
            shootId: self::SHOOT,
            groupId: self::GROUP,
            originalGroupId: self::GROUP,
            childCode: 'A',
            code: 'A001',
            sequence: 1,
            assignments: [new PhotoAssignmentOutputDto('42345678-abcd-4abc-8abc-123456789abc', 'A', 1, 'A001')],
            filename: 'IMG_0001.jpg',
            bytes: 1024,
            width: 640,
            height: 480,
            fingerprint: str_repeat('a', 64),
            revision: 2,
            thumbSrc: '/api/v1/photos/' . self::PHOTO . '/thumb',
            previewSrc: '/api/v1/photos/' . self::PHOTO . '/preview',
        );
        $output = new PhotoPageOutputDto(
            items: [$photo],
            groups: [],
            covers: [self::GROUP => self::PHOTO],
            revision: 5,
            meta: ['page' => 1, 'pageSize' => 48, 'total' => 1],
            summary: new PhotoGroupSummaryOutputDto(photos: 1, unassigned: 0, children: ['A']),
            stats: ['byStatus' => ['processing' => 2, 'ready' => 1, 'failed' => 0, 'duplicate' => 0], 'unassigned' => 0],
        );

        $json = $this->json($mapper->page($output));

        self::assertSame(['items', 'groups', 'covers', 'revision', 'meta', 'summary', 'stats'], array_keys($json));
        self::assertSame(['photos' => 1, 'unassigned' => 0, 'children' => ['A']], $json['summary']);
        self::assertSame($output->stats, $json['stats']);
        self::assertSame(['page' => 1, 'pageSize' => 48, 'total' => 1], $json['meta']);
        self::assertSame([self::GROUP => self::PHOTO], $json['covers']);
        self::assertSame(self::PHOTO, $json['items'][0]['id']);
        self::assertSame('A001', $json['items'][0]['assignments'][0]['code']);
        self::assertNull($this->json($mapper->page(new PhotoPageOutputDto([], [], [], 5, $output->meta, null, $output->stats)))['summary']);
    }

    public function testControllerHasNoTechnicalAssembly(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/Presentation/Controller/PhotoListController.php');
        self::assertIsString($source);
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                self::assertTrue(
                    'Morefoto\Media\Presentation\Controller' === $name
                    || (str_starts_with($name, 'Morefoto\Media\Application\Photo\UseCase\\') && str_ends_with($name, 'UseCase'))
                    || (str_starts_with($name, 'Morefoto\Media\Presentation\Photo\Dto\\') && str_ends_with($name, 'RequestDto'))
                    || in_array($name, [
                        'Morefoto\Media\Presentation\Photo\PhotoListInputMapper',
                        'Morefoto\Media\Presentation\Photo\PhotoListResultMapper',
                        'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                        'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                    ], true),
                    'Недопустимая зависимость concrete controller: ' . $name,
                );
            }
        }
        foreach (['use Bitrix\\', 'ServiceLocator', 'getRequest()', 'HttpRequest', 'MediaRequestFactory', 'configureActions', 'getExceptionResponse', 'finalizeResponse', '$this->json(['] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        self::assertSame(LogChannelEnum::media, LogChannelEnum::resolveFromClassName('Morefoto\Media\Presentation\Controller\PhotoListController'));
    }

    private function useCase(PhotoRepository $photos, ?int $groupId): ListPhotosUseCase
    {
        $scopes = $this->createStub(MediaScopeInterface::class);
        $scopes->method('resolve')->willReturn(new MediaScopeOutputDto(
            institutionId: 1,
            shootId: 2,
            shootPublicId: self::SHOOT,
            groupId: $groupId,
            groupPublicId: null === $groupId ? null : self::GROUP,
            groupEditable: true,
        ));
        $scopes->method('groups')->willReturn([]);
        $media = $this->createStub(MediaMutationRepository::class);
        $media->method('covers')->willReturn([]);
        $media->method('revision')->willReturn(5);

        return new ListPhotosUseCase($photos, $media, new PhotoRowMapper(), $scopes, $this->createStub(AccessGuardInterface::class));
    }

    private function emptyResult(): Result
    {
        $result = $this->createStub(Result::class);
        $result->method('fetch')->willReturn(false);

        return $result;
    }

    /** @return array<string, mixed> */
    private function json(object $result): array
    {
        $decoded = json_decode(CommonSerializer::createDefault()->serialize($result), true, 16, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
