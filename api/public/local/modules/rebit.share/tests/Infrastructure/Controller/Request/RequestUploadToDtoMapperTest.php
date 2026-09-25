<?php

declare(strict_types=1);

namespace Rebit\Share\Tests\Infrastructure\Controller\Request;

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Type\ParameterDictionary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\FormField;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\MultipartFile;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\RequestParameterFactory;
use Rebit\Share\Infrastructure\Controller\Request\RequestUploadToDtoMapper;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Interface\RequestUploadDtoInterface;

/**
 * @internal
 */
final class RequestUploadToDtoMapperTest extends TestCase
{
    private const string ID = '12345678-abcd-4abc-8abc-123456789abc';
    private const string GROUP = '22345678-abcd-4abc-8abc-123456789abc';

    protected function setUp(): void
    {
        Application::$routeParameters = ['shoot_id' => self::ID];
    }

    protected function tearDown(): void
    {
        Application::$routeParameters = null;
    }

    public function testMultipartBecomesTheRequestDto(): void
    {
        $dto = $this->map(fields: ['groupId' => self::GROUP, 'extra' => 'ignored']);

        self::assertEquals(new UploadFixtureRequestDto(
            shootId: self::ID,
            tmpName: '/tmp/php-upload',
            filename: 'IMG_0001.jpg',
            bytes: 2048,
            groupId: self::GROUP,
            fingerprint: null,
        ), $dto);
        self::assertSame('ABC', $this->map(fields: ['groupId' => self::GROUP, 'fingerprint' => 'ABC'])->fingerprint);
    }

    public function testFactoryRoutesUploadDtoToTheMapper(): void
    {
        self::assertContains(RequestUploadToDtoMapper::class, (new \ReflectionClassConstant(RequestParameterFactory::class, 'MAPPER_CLASSES'))->getValue());
    }

    /**
     * @param array<array-key, mixed> $fields
     * @param array<array-key, mixed> $files
     */
    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsKeepTheirCodes(string $code, int $status, string $contentType, array $fields, array $files, ?string $route = self::ID): void
    {
        Application::$routeParameters = null === $route ? null : ['shoot_id' => $route];
        try {
            $this->map($contentType, $fields, $files);
            self::fail($code . ' expected.');
        } catch (HttpException $error) {
            self::assertSame([$code, $status], [$error->getMessage(), $error->getCode()]);
        }
    }

    /** @return iterable<string, array{0: string, 1: int, 2: string, 3: array<array-key, mixed>, 4: array<array-key, mixed>, 5?: ?string}> */
    public static function invalidRequests(): iterable
    {
        $file = ['name' => 'IMG_0001.jpg', 'tmp_name' => '/tmp/php-upload', 'size' => 2048, 'error' => UPLOAD_ERR_OK];
        $form = ['groupId' => self::GROUP];
        $multipart = 'multipart/form-data; boundary=x';

        yield 'json instead of multipart' => ['MULTIPART_REQUIRED', 400, 'application/json', $form, ['file' => $file]];
        yield 'no file' => ['ONE_FILE_TEST', 422, $multipart, $form, []];
        yield 'two files' => ['ONE_FILE_TEST', 422, $multipart, $form, ['file' => $file, 'other' => $file]];
        yield 'file under another field' => ['ONE_FILE_TEST', 422, $multipart, $form, ['photo' => $file]];
        yield 'php upload error' => ['FILE_FAILED_TEST', 422, $multipart, $form, ['file' => ['error' => UPLOAD_ERR_PARTIAL] + $file]];
        yield 'file array field' => ['FILE_FAILED_TEST', 422, $multipart, $form, ['file' => ['name' => ['a'], 'tmp_name' => ['/tmp/a'], 'size' => [1], 'error' => [0]]]];
        yield 'group missing' => ['GROUP_TEST', 422, $multipart, [], ['file' => $file]];
        yield 'group is not an id' => ['GROUP_TEST', 422, $multipart, ['groupId' => 'group-1'], ['file' => $file]];
        yield 'group array' => ['GROUP_TEST', 422, $multipart, ['groupId' => [self::GROUP]], ['file' => $file]];
        yield 'group checked before fingerprint' => ['GROUP_TEST', 422, $multipart, ['groupId' => 'x', 'fingerprint' => ['a']], ['file' => $file]];
        yield 'fingerprint array' => ['FINGERPRINT_TEST', 422, $multipart, ['groupId' => self::GROUP, 'fingerprint' => ['a']], ['file' => $file]];
        yield 'bad route after form' => ['INVALID_ROUTE', 400, $multipart, $form, ['file' => $file], 'shoot-1'];
    }

    /**
     * @param array<array-key, mixed>      $fields
     * @param null|array<array-key, mixed> $files
     */
    private function map(string $contentType = 'multipart/form-data; boundary=x', array $fields = [], ?array $files = null): UploadFixtureRequestDto
    {
        $files ??= ['file' => ['name' => 'IMG_0001.jpg', 'tmp_name' => '/tmp/php-upload', 'size' => 2048, 'error' => UPLOAD_ERR_OK]];
        $dto = (new RequestUploadToDtoMapper($this->request($contentType, $fields, $files)))->map(UploadFixtureRequestDto::class);
        self::assertInstanceOf(UploadFixtureRequestDto::class, $dto);

        return $dto;
    }

    /**
     * @param array<array-key, mixed> $fields
     * @param array<array-key, mixed> $files
     */
    private function request(string $contentType = 'multipart/form-data', array $fields = [], array $files = []): HttpRequest
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getHeader')->willReturnCallback(static fn(string $name): ?string => 'Content-Type' === $name ? $contentType : null);
        $request->method('getRequestMethod')->willReturn('POST');
        $request->method('getPostList')->willReturn(new ParameterDictionary($fields));
        $request->method('getFileList')->willReturn(new ParameterDictionary($files));

        return $request;
    }
}

/** @internal */
#[MultipartFile(missingCode: 'ONE_FILE_TEST', failedCode: 'FILE_FAILED_TEST')]
final readonly class UploadFixtureRequestDto implements RequestUploadDtoInterface
{
    public function __construct(
        #[RouteParameter(name: 'shoot_id', pattern: '/^[a-f0-9-]{36}$/D')]
        public string $shootId,
        public string $tmpName,
        public string $filename,
        public int $bytes,
        #[FormField(errorCode: 'GROUP_TEST', pattern: '/^[a-f0-9-]{36}$/D')]
        public string $groupId,
        #[FormField(errorCode: 'FINGERPRINT_TEST')]
        public ?string $fingerprint,
    ) {}
}
