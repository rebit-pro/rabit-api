<?php

declare(strict_types=1);

namespace Morefoto\Media\Tests\Unit;

use Morefoto\Media\Application\Transfer\Dto\TransferChildOutputDto;
use Morefoto\Media\Presentation\Transfer\ChildTransferInputMapper;
use Morefoto\Media\Presentation\Transfer\ChildTransferResultMapper;
use Morefoto\Media\Presentation\Transfer\Dto\TransferChildRequestDto;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Enum\LogChannelEnum;
use Rebit\Share\Shared\Exception\HttpException;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class ChildTransferContractTest extends TestCase
{
    private const string SHOOT = '12345678-abcd-4abc-8abc-123456789abc';
    private const string FROM = '22345678-abcd-4abc-8abc-123456789abc';
    private const string TO = '32345678-abcd-4abc-8abc-123456789abc';
    private const string PHOTO = '42345678-abcd-4abc-8abc-123456789abc';

    public function testValidBodyBecomesTheScenarioInput(): void
    {
        $input = (new ChildTransferInputMapper())->transfer($this->request());

        self::assertSame(self::SHOOT, $input->shootId);
        self::assertSame([self::PHOTO], $input->expectedPhotoIds);
        self::assertSame('B', $input->targetCode);
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidBodies')]
    public function testInvalidBodiesAreRejected(string $code, array $override): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage($code);
        (new ChildTransferInputMapper())->transfer($this->request($override));
    }

    /** @return iterable<string, array{0: string, 1: array<string, mixed>}> */
    public static function invalidBodies(): iterable
    {
        yield 'same group' => ['VALIDATION_FAILED', ['toGroupId' => self::FROM]];
        yield 'lower-case code' => ['VALIDATION_FAILED', ['targetCode' => 'b']];
        yield 'frame code instead of child code' => ['VALIDATION_FAILED', ['childCode' => 'A001']];
        yield 'zero revision' => ['VALIDATION_FAILED', ['revision' => 0]];
        yield 'duplicate photo' => ['INVALID_PHOTO_IDS', ['expectedPhotoIds' => [self::PHOTO, self::PHOTO]]];
        yield 'empty set' => ['INVALID_PHOTO_IDS', ['expectedPhotoIds' => []]];
    }

    public function testResultKeepsThePublicFieldOrder(): void
    {
        $result = (new ChildTransferResultMapper())->transfer(new TransferChildOutputDto([self::PHOTO], self::FROM, self::TO, 'B', 6));

        self::assertSame(
            ['photoIds' => [self::PHOTO], 'fromGroupId' => self::FROM, 'toGroupId' => self::TO, 'childCode' => 'B', 'revision' => 6],
            json_decode(CommonSerializer::createDefault()->serialize($result), true, 8, JSON_THROW_ON_ERROR),
        );
    }

    public function testControllerHasNoTechnicalAssembly(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/lib/Presentation/Controller/ChildTransferController.php');
        self::assertIsString($source);
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = ltrim($token[1], '\\');
                self::assertTrue(
                    'Morefoto\Media\Presentation\Controller' === $name
                    || (str_starts_with($name, 'Morefoto\Media\Application\Transfer\UseCase\\') && str_ends_with($name, 'UseCase'))
                    || (str_starts_with($name, 'Morefoto\Media\Presentation\Transfer\Dto\\') && str_ends_with($name, 'RequestDto'))
                    || in_array($name, [
                        'Morefoto\Media\Presentation\Transfer\ChildTransferInputMapper',
                        'Morefoto\Media\Presentation\Transfer\ChildTransferResultMapper',
                        'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                        'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                    ], true),
                    'Недопустимая зависимость concrete controller: ' . $name,
                );
            }
        }
        foreach (['use Bitrix\\', 'ServiceLocator', 'getRequest()', 'HttpRequest', 'getExceptionResponse', '$this->json(['] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        self::assertSame(LogChannelEnum::media, LogChannelEnum::resolveFromClassName('Morefoto\Media\Presentation\Controller\ChildTransferController'));
    }

    /** @param array<string, mixed> $override */
    private function request(array $override = []): TransferChildRequestDto
    {
        return new TransferChildRequestDto(
            fromGroupId: $override['fromGroupId'] ?? self::FROM,
            toGroupId: $override['toGroupId'] ?? self::TO,
            childCode: $override['childCode'] ?? 'A',
            targetCode: $override['targetCode'] ?? 'B',
            expectedPhotoIds: $override['expectedPhotoIds'] ?? [self::PHOTO],
            revision: $override['revision'] ?? 5,
            shootId: self::SHOOT,
            idempotencyKey: str_repeat('d', 32),
        );
    }
}
