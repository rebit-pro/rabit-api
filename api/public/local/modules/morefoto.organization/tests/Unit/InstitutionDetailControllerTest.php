<?php

declare(strict_types=1);

namespace Morefoto\Organization\Tests\Unit;

use Morefoto\Organization\Application\Institution\Dto\InstitutionDetailOutputDto;
use Morefoto\Organization\Application\Structure\Dto\StructurePageOutputDto;
use Morefoto\Organization\Presentation\Institution\Dto\InstitutionDetailRequestDto;
use Morefoto\Organization\Presentation\Institution\InstitutionDetailInputMapper;
use Morefoto\Organization\Presentation\Institution\Result\InstitutionDetailResultMapper;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Tests\Support\CleanControllerSource;

require_once __DIR__ . '/../bootstrap.php';

/**
 * @internal
 */
final class InstitutionDetailControllerTest extends TestCase
{
    private const string UUID = '12345678-abcd-4abc-8abc-123456789abc';

    public function testControllerKeepsTheCleanBoundary(): void
    {
        self::assertSame([], CleanControllerSource::violations(
            dirname(__DIR__, 2) . '/lib/Presentation/Controller/InstitutionDetailController.php',
            [
                'Morefoto\Organization\Presentation\Controller',
                'Morefoto\Organization\Presentation\Institution\InstitutionDetailInputMapper',
                'Morefoto\Organization\Presentation\Institution\Result\InstitutionDetailResultMapper',
                'Rebit\Share\Infrastructure\Bitrix\ControllerJson',
                'Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController',
            ],
            [
                ['Morefoto\Organization\Application\Institution\UseCase\\', 'UseCase'],
                ['Morefoto\Organization\Presentation\Institution\Dto\\', 'RequestDto'],
            ],
        ));
    }

    public function testRequestKeepsPagesAndTheBearer(): void
    {
        $mapper = new InstitutionDetailInputMapper();
        $request = new InstitutionDetailRequestDto(self::UUID, 'Bearer token-value', '2', null, '25');

        self::assertSame(self::UUID, $mapper->institutionId($request)->value);
        self::assertSame('token-value', $mapper->bearer($request));
        $pages = $mapper->pages($request);
        self::assertSame([2, 1, 25], [$pages->shoots->page, $pages->groups->page, $pages->groups->pageSize]);
        foreach ([['0', null, null], [null, '1.5', null], [null, null, '101']] as [$shoots, $groups, $size]) {
            try {
                $mapper->pages(new InstitutionDetailRequestDto(self::UUID, 'Bearer x', $shoots, $groups, $size));
                self::fail('Expected INVALID_PAGE');
            } catch (HttpException $error) {
                self::assertSame(['INVALID_PAGE', 422], [$error->getMessage(), $error->getCode()]);
            }
        }
    }

    public function testResultNamesTheResponsibleAndOmitsTheSignatureForOthers(): void
    {
        $page = new StructurePageOutputDto([], ['page' => 1, 'pageSize' => 50, 'total' => 0, 'totalPages' => 0]);
        $output = new InstitutionDetailOutputDto(self::UUID, 'Сад', 'Улица', 3, 7, null, 'Анна Куратор', null, $page, $page, null);

        $json = json_decode(CommonSerializer::createDefault()->serialize((new InstitutionDetailResultMapper())->detail($output)), true, 16, JSON_THROW_ON_ERROR);

        self::assertSame(['Анна Куратор', null, 7], [$json['curatorName'], $json['headName'], $json['curatorId']]);
        self::assertSame(['availability' => 'unavailable', 'reason' => 'dependenciesNotReady'], $json['summary']);
        self::assertArrayNotHasKey('assignmentSignature', $json);
    }
}
