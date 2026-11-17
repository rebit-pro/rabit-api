<?php

declare(strict_types=1);

namespace Morefoto\Files\Tests\Unit;

use Morefoto\Files\Presentation\Institution\DigitalArchiveResultMapper;
use Morefoto\Files\Presentation\Institution\Request\Dto\CreateInstitutionDigitalArchiveRequestDto;
use Morefoto\Files\Presentation\Institution\Request\Dto\InstitutionDigitalArchiveRequestDto;
use Morefoto\Files\Presentation\Institution\Request\Dto\InstitutionDigitalArchiveTicketRequestDto;
use Morefoto\Files\Presentation\Institution\Request\Dto\InstitutionDigitalArchivesRequestDto;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RequestHeader;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\RouteParameter;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;
use Rebit\Share\Infrastructure\Controller\Request\Attribute\StrictRequest;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class InstitutionDigitalArchiveArchitectureTest extends TestCase
{
    public function testControllersUseOnlyPresentationDtosMappersAndApplicationScenarios(): void
    {
        foreach (['InstitutionDigitalArchiveController', 'InstitutionDigitalArchiveContentController'] as $controller) {
            $source = (string)file_get_contents(__DIR__ . '/../../lib/Presentation/Controller/' . $controller . '.php');
            foreach (token_get_all($source) as $token) {
                if (!is_array($token) || !in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }
                $name = ltrim($token[1], '\\');
                self::assertTrue(
                    'Morefoto\Files\Presentation\Controller' === $name
                    || str_starts_with($name, 'Morefoto\Files\Application\Institution\UseCase\\')
                    || str_starts_with($name, 'Morefoto\Files\Presentation\Institution\Request\Dto\\')
                    || DigitalArchiveResultMapper::class === $name
                    || in_array($name, [
                        'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                        'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
                        'Rebit\Share\Infrastructure\Controller\PrivateApiJsonController',
                        'Rebit\Share\Infrastructure\Controller\Responses\TextFileResponse',
                        'Rebit\Share\Infrastructure\Controller\Responses\ProtectedFileResponse',
                    ], true),
                    $name,
                );
            }
            foreach (['ServiceLocator', 'getRequest(', 'HttpException', 'json_decode', 'try {', 'configureActions', 'LoggerFilter', 'addHeader'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
            self::assertSame(0, preg_match('/Action\([^)]*,[^)]*\)/', $source));
        }
    }

    public function testRequestDtosAreStrictDataWithRealRouteAndHeaderAttributes(): void
    {
        foreach ([InstitutionDigitalArchivesRequestDto::class, CreateInstitutionDigitalArchiveRequestDto::class,
            InstitutionDigitalArchiveRequestDto::class, InstitutionDigitalArchiveTicketRequestDto::class] as $class) {
            $reflection = new \ReflectionClass($class);
            // BypassFinals намеренно снимает readonly в рантайме PHPUnit; проверяем границу данных и атрибуты.
            self::assertNotEmpty($reflection->getAttributes(StrictRequest::class));
            foreach ($reflection->getProperties() as $property) {
                self::assertTrue($property->isPublic());
                self::assertNotNull($property->getType());
            }
            self::assertCount(1, $reflection->getMethods());
            $parameters = $reflection->getConstructor()?->getParameters() ?? [];
            self::assertCount(1, $parameters[0]->getAttributes(RouteParameter::class));
        }
        $header = (new \ReflectionClass(CreateInstitutionDigitalArchiveRequestDto::class))->getConstructor()?->getParameters()[1];
        self::assertNotNull($header);
        self::assertCount(1, $header->getAttributes(RequestHeader::class));
        $input = ['institutionId' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'idempotencyKey' => str_repeat('k', 32)];
        $dto = ArrayToDtoMapper::map(StrictRequestValues::normalize($input, CreateInstitutionDigitalArchiveRequestDto::class, true), CreateInstitutionDigitalArchiveRequestDto::class);
        self::assertSame($input['institutionId'], $dto->institutionId);
        self::assertSame($input['idempotencyKey'], $dto->idempotencyKey);
    }

    public function testFilesDoNotImportPrivateProductionOrCommerceAndTicketsAreNotLogged(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../lib', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $source = (string)file_get_contents($file->getPathname());
            self::assertStringNotContainsString('Morefoto\Production\\', $source);
            self::assertStringNotContainsString('Morefoto\Commerce\\', $source);
            self::assertStringNotContainsString('mf_order_line', $source);
        }
        $nginx = (string)file_get_contents(dirname(__DIR__, 6) . '/docker/common/nginx/conf.d/default.conf');
        self::assertStringContainsString('~^/api/v1/institution-digital-archives/content/ 0;', $nginx);
    }
}
