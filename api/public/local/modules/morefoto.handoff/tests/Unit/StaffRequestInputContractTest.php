<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Presentation\Request\Dto\ClarifyStaffRequestRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\CreateStaffRequestRequestDto;
use Morefoto\Handoff\Presentation\Request\Dto\StaffRequestListRequestDto;
use Morefoto\Handoff\Presentation\Request\StaffRequestInputMapper;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

/**
 * @internal
 */
final class StaffRequestInputContractTest extends TestCase
{
    private const string UUID = '12345678-1234-4234-8234-123456789abc';

    public function testValidJsonHydratesNestedRows(): void
    {
        $values = StrictRequestValues::normalize($this->createValues(), CreateStaffRequestRequestDto::class, true);
        $request = ArrayToDtoMapper::map($values, CreateStaffRequestRequestDto::class);
        self::assertInstanceOf(CreateStaffRequestRequestDto::class, $request);
        self::assertSame('A001', (new StaffRequestInputMapper())->create($request)->rows[0]['code']);
    }

    public function testUnknownAndWronglyTypedCreateFieldsAreRejected(): void
    {
        foreach (['comment' => 12, 'institutionId' => false, 'rows' => (object)[], 'extra' => 'value'] as $name => $value) {
            $values = $this->createValues();
            $values[$name] = $value;
            $this->assertRejected($values, CreateStaffRequestRequestDto::class);
        }
        $values = $this->createValues();
        $values['rows'][0]->extra = 'value';
        $this->assertRejected($values, CreateStaffRequestRequestDto::class);
        $values = $this->createValues();
        unset($values['comment']);
        $this->assertRejected($values, CreateStaffRequestRequestDto::class);
    }

    public function testClarificationDoesNotCoerceJsonScalars(): void
    {
        $values = [
            'revision' => 2,
            'comment' => 'Уточните номер снимка',
            'confirmed' => true,
            'requestId' => self::UUID,
            'idempotencyKey' => 'f1-contract-key',
        ];
        self::assertSame($values, StrictRequestValues::normalize($values, ClarifyStaffRequestRequestDto::class, true));
        foreach (['revision' => '2', 'confirmed' => 'true', 'comment' => 100] as $name => $value) {
            $invalid = $values;
            $invalid[$name] = $value;
            $this->assertRejected($invalid, ClarifyStaffRequestRequestDto::class);
        }
    }

    public function testQueryPaginationAcceptsDecimalStringsOnly(): void
    {
        $values = StrictRequestValues::normalize(['page' => '2', 'pageSize' => '10'], StaffRequestListRequestDto::class, false);
        $request = ArrayToDtoMapper::map($values, StaffRequestListRequestDto::class);
        self::assertSame(2, (new StaffRequestInputMapper())->list($request)->page);
        foreach (['1.2', '1e2', '01', ['1']] as $value) {
            try {
                StrictRequestValues::normalize(['page' => $value], StaffRequestListRequestDto::class, false);
                self::fail('Malformed query page was accepted.');
            } catch (HttpException $exception) {
                self::assertSame(422, $exception->getCode());
            }
        }
    }

    /** @return array<string, mixed> */
    private function createValues(): array
    {
        return [
            'institutionId' => self::UUID,
            'shootId' => self::UUID,
            'rows' => [(object)['id' => self::UUID, 'groupId' => self::UUID, 'code' => 'a001']],
            'comment' => '',
            'idempotencyKey' => 'f1-contract-key',
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param class-string         $className
     */
    private function assertRejected(array $values, string $className): void
    {
        try {
            StrictRequestValues::normalize($values, $className, true);
            self::fail('Malformed JSON input was accepted.');
        } catch (HttpException $exception) {
            self::assertSame(422, $exception->getCode());
        }
    }
}
