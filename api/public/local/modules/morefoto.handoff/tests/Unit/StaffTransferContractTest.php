<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Application\Request\Dto\StaffTransferOutputDto;
use Morefoto\Handoff\Application\Request\Dto\StaffTransferPreviewOutputDto;
use Morefoto\Handoff\Presentation\Request\Dto\ConfirmStaffTransferRequestDto;
use Morefoto\Handoff\Presentation\Request\StaffRequestInputMapper;
use Morefoto\Handoff\Presentation\Result\StaffRequestResultMapper;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Exception\HttpException;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class StaffTransferContractTest extends TestCase
{
    private const string REQUEST = '11111111-1111-4111-8111-111111111111';

    public function testConfirmationRequiresTheCheckboxAndKeepsATrimmedComment(): void
    {
        $input = (new StaffRequestInputMapper())->transfer($this->request(reason: '  Проверено  '));
        self::assertSame('Проверено', $input->reason);
        self::assertSame(3, $input->revision);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('CONFIRMATION_REQUIRED');
        (new StaffRequestInputMapper())->transfer($this->request(confirmed: false));
    }

    public function testMalformedSignatureOrLongCommentIsRejected(): void
    {
        foreach ([$this->request(signature: 'short'), $this->request(reason: str_repeat('я', 501)), $this->request(revision: 0)] as $request) {
            try {
                (new StaffRequestInputMapper())->transfer($request);
                self::fail('Expected VALIDATION_FAILED');
            } catch (HttpException $error) {
                self::assertSame('VALIDATION_FAILED', $error->getMessage());
            }
        }
    }

    public function testPreviewAndResultKeepThePublicContract(): void
    {
        $serializer = CommonSerializer::createDefault();
        $mapper = new StaffRequestResultMapper();
        $bundle = ['rowId' => 'row-1', 'groupId' => 'group-a', 'childCode' => 'A', 'targetCode' => 'C', 'hasOrders' => true, 'photos' => [['id' => 'photo-1', 'code' => 'A001', 'revision' => 2]]];
        $result = ['rowId' => 'row-1', 'fromGroupId' => 'group-a', 'fromChildCode' => 'A', 'targetGroupId' => 'staff-group', 'targetChildCode' => 'C', 'photoIds' => ['photo-1']];

        self::assertSame(
            ['targetGroupId' => 'staff-group', 'bundles' => [$bundle], 'signature' => str_repeat('a', 64), 'hasOrders' => true, 'revision' => 3],
            json_decode($serializer->serialize($mapper->preview(new StaffTransferPreviewOutputDto('staff-group', [$bundle], str_repeat('a', 64), true, 3))), true, 16, JSON_THROW_ON_ERROR),
        );
        self::assertSame(
            ['id' => self::REQUEST, 'revision' => 4, 'status' => 'transferred', 'results' => [$result]],
            json_decode($serializer->serialize($mapper->transfer(new StaffTransferOutputDto(self::REQUEST, 4, 'transferred', [$result]))), true, 16, JSON_THROW_ON_ERROR),
        );
    }

    private function request(string $reason = '', bool $confirmed = true, int $revision = 3, ?string $signature = null): ConfirmStaffTransferRequestDto
    {
        return new ConfirmStaffTransferRequestDto(
            reason: $reason,
            confirmed: $confirmed,
            revision: $revision,
            signature: $signature ?? str_repeat('a', 64),
            requestId: self::REQUEST,
            idempotencyKey: str_repeat('f', 32),
        );
    }
}
