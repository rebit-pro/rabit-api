<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Application\Request\Dto\StaffRequestListOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestOutputDto;
use Morefoto\Handoff\Application\Request\Mapper\StaffRequestOutputMapper;
use Morefoto\Handoff\Presentation\Result\StaffRequestResultMapper;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;

/**
 * @internal
 *
 * @phpstan-import-type StaffRequestView from StaffRequestOutputDto
 */
final class StaffRequestResultContractTest extends TestCase
{
    public function testListAndDetailKeepThePublicContractWithTheCommonSerializer(): void
    {
        $view = $this->view();
        $output = StaffRequestOutputMapper::fromView($view);
        $serializer = CommonSerializer::createDefault();
        self::assertSame($view, json_decode($serializer->serialize((new StaffRequestResultMapper())->detail($output)), true, 64, JSON_THROW_ON_ERROR));

        $scope = ['role' => 'teacher', 'institutions' => [], 'shoots' => [], 'groups' => []];
        $outputList = new StaffRequestListOutputDto(
            items: [$output],
            scope: $scope,
            page: 2,
            pageSize: 10,
            total: 11,
            totalPages: 2,
        );
        $list = (new StaffRequestResultMapper())->list($outputList);
        $envelope = json_decode($serializer->serialize(['data' => $list, 'meta' => (new StaffRequestResultMapper())->meta($outputList)]), true, 64, JSON_THROW_ON_ERROR);
        self::assertSame([
            'data' => ['items' => [$view], 'scope' => $scope],
            'meta' => ['page' => 2, 'pageSize' => 10, 'total' => 11, 'totalPages' => 2],
        ], $envelope);
    }

    public function testEmptyListIsAnArrayAndMutationKeepsLocationOutsideTheBody(): void
    {
        $scope = ['role' => 'organizer', 'institutions' => [], 'shoots' => [], 'groups' => []];
        $outputList = new StaffRequestListOutputDto(
            items: [],
            scope: $scope,
            page: 1,
            pageSize: 20,
            total: 0,
            totalPages: 0,
        );
        $list = (new StaffRequestResultMapper())->list($outputList);
        $serializer = CommonSerializer::createDefault();
        self::assertSame(['items' => [], 'scope' => $scope], json_decode($serializer->serialize($list), true, 64, JSON_THROW_ON_ERROR));
        $output = new StaffRequestMutationOutputDto(id: 'request-id', revision: 3, status: 'submitted');
        $result = (new StaffRequestResultMapper())->mutation($output);
        self::assertSame('/api/v1/staff-requests/request-id', (new StaffRequestResultMapper())->location($output));
        self::assertSame(
            ['id' => 'request-id', 'revision' => 3, 'status' => 'submitted'],
            json_decode($serializer->serialize($result), true, 64, JSON_THROW_ON_ERROR),
        );
    }

    /** @return StaffRequestView */
    private function view(): array
    {
        return [
            'id' => 'request-id', 'institutionId' => 'institution-id', 'shootId' => 'shoot-id',
            'createdBy' => 7, 'createdByName' => 'Сотрудник', 'createdAt' => '2026-09-21T06:00:00Z',
            'revision' => 3, 'status' => 'submitted',
            'rows' => [['id' => 'row-id', 'groupId' => 'group-id', 'code' => 'A001', 'childCode' => 'A', 'photoIds' => ['photo-id']]],
            'comment' => 'Уточнено',
            'history' => [['kind' => 'submitted', 'actorId' => 7, 'actorName' => 'Сотрудник', 'at' => '2026-09-21T06:00:00Z', 'comment' => '', 'confirmed' => false]],
            'staffEligibility' => ['eligible' => true, 'source' => 'verified_staff_assignment', 'verifiedAt' => '2026-09-21T06:00:00Z'],
        ];
    }
}
