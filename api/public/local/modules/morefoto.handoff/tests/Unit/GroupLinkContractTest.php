<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Tests\Unit;

use Morefoto\Handoff\Application\Link\Dto\GroupLinkOutputDto;
use Morefoto\Handoff\Application\Link\Dto\GroupLinkPageOutputDto;
use Morefoto\Handoff\Application\Link\Dto\GroupLinkSummaryOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkCalendarOutputDto;
use Morefoto\Handoff\Application\Link\Dto\LinkEventOutputDto;
use Morefoto\Handoff\Presentation\Link\GroupLinkInputMapper;
use Morefoto\Handoff\Presentation\Link\GroupLinkResultMapper;
use Morefoto\Handoff\Presentation\Link\Request\Dto\CorrectGroupLinkDateRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\GroupLinkListRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\PrepareGroupLinkRequestDto;
use Morefoto\Handoff\Presentation\Link\Request\Dto\TransmitGroupLinkRequestDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rebit\Share\Infrastructure\Controller\Request\StrictRequestValues;
use Rebit\Share\Infrastructure\Controller\Serializers\CommonSerializer;
use Rebit\Share\Shared\Exception\HttpException;
use Rebit\Share\Shared\Helper\ArrayToDtoMapper;

require_once __DIR__ . '/../bootstrap.php';

/** @internal */
final class GroupLinkContractTest extends TestCase
{
    private const string UUID = '22345678-abcd-4abc-8abc-123456789abc';

    public function testTransmissionKeepsTheExplicitOffsetOfTheReportedMoment(): void
    {
        $input = (new GroupLinkInputMapper())->transmission($this->request(TransmitGroupLinkRequestDto::class, $this->transmission()));
        self::assertSame(2, $input->revision);
        self::assertSame('2026-09-22T10:15:00+03:00', $input->sentAt->format(\DateTimeInterface::ATOM));
    }

    #[DataProvider('invalidMoments')]
    public function testImpossibleOrAmbiguousMomentsAreRejected(string $sentAt): void
    {
        $this->assertCode('INVALID_SENT_AT', fn(): object => (new GroupLinkInputMapper())->transmission(
            $this->request(TransmitGroupLinkRequestDto::class, ['sentAt' => $sentAt] + $this->transmission()),
        ));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMoments(): iterable
    {
        yield 'no offset' => ['2026-09-22T10:15:00'];
        yield 'nonexistent day' => ['2026-02-30T10:15:00+03:00'];
        yield 'fraction' => ['2026-09-22T10:15:00.5+03:00'];
        yield 'minutes only' => ['2026-09-22T10:15+03:00'];
        yield 'date only' => ['2026-09-22'];
        yield 'free text' => ['вчера'];
    }

    public function testCommandsRequireExplicitConfirmationAndValidReason(): void
    {
        $mapper = new GroupLinkInputMapper();
        foreach (['photosReviewed', 'conditionsReviewed', 'staffReviewed', 'confirmed'] as $flag) {
            $this->assertCode('REVIEW_REQUIRED', fn(): object => $mapper->preparation($this->request(PrepareGroupLinkRequestDto::class, [$flag => false] + $this->preparation())));
        }
        $this->assertCode('CONFIRMATION_REQUIRED', fn(): object => $mapper->transmission($this->request(TransmitGroupLinkRequestDto::class, ['confirmed' => false] + $this->transmission())));
        foreach (['   крат  ', str_repeat('я', 501)] as $reason) {
            $this->assertCode('INVALID_REASON', fn(): object => $mapper->correction($this->request(CorrectGroupLinkDateRequestDto::class, ['reason' => $reason] + $this->correction())));
        }
        self::assertSame('Ошибка в дате', $mapper->correction($this->request(CorrectGroupLinkDateRequestDto::class, ['reason' => '  Ошибка в дате '] + $this->correction()))->reason);
        $this->assertCode('VALIDATION_FAILED', fn(): object => $mapper->preparation($this->request(PrepareGroupLinkRequestDto::class, ['signature' => strtoupper(str_repeat('a', 64))] + $this->preparation())));
        $this->assertCode('VALIDATION_FAILED', fn(): object => $mapper->preparation($this->request(PrepareGroupLinkRequestDto::class, ['revision' => 0] + $this->preparation())));
    }

    public function testStrictJsonRejectsCoercionAndUnknownFields(): void
    {
        foreach (['revision' => '2', 'confirmed' => 'true', 'sentAt' => 1700000000, 'extra' => true] as $name => $value) {
            $values = $this->transmission();
            $values[$name] = $value;
            $this->assertCode(null, static fn(): array => StrictRequestValues::normalize($values, TransmitGroupLinkRequestDto::class, true));
        }
    }

    public function testListFiltersAreValidated(): void
    {
        $mapper = new GroupLinkInputMapper();
        $request = ArrayToDtoMapper::map(StrictRequestValues::normalize(['state' => 'open', 'page' => '2', 'shootId' => self::UUID], GroupLinkListRequestDto::class, false), GroupLinkListRequestDto::class);
        self::assertInstanceOf(GroupLinkListRequestDto::class, $request);
        $input = $mapper->list($request);
        self::assertSame(['open', 2, 25, self::UUID], [$input->state, $input->page, $input->pageSize, $input->shootId]);
        $this->assertCode('INVALID_STATE', static fn(): object => $mapper->list(new GroupLinkListRequestDto(state: 'sent')));
        $this->assertCode('INVALID_PAGE', static fn(): object => $mapper->list(new GroupLinkListRequestDto(pageSize: 101)));
        $this->assertCode('INVALID_FILTER', static fn(): object => $mapper->list(new GroupLinkListRequestDto(institutionId: 'not-a-uuid')));
    }

    public function testResponsesKeepThePublicShape(): void
    {
        $serializer = CommonSerializer::createDefault();
        $mapper = new GroupLinkResultMapper();
        $summary = ['groupId' => self::UUID, 'name' => 'Солнышко', 'kind' => 'regular', 'institutionId' => self::UUID, 'institutionName' => 'Сад', 'shootId' => self::UUID,
            'shootName' => 'Осень', 'revision' => 3, 'signature' => str_repeat('a', 64), 'prepared' => true, 'problems' => [], 'state' => 'open', 'timezone' => 'Europe/Moscow',
            'sentAt' => '2026-09-22T10:15:00+03:00', 'closesAt' => '2026-09-29T10:15:00+03:00', 'deliveryAt' => '2026-10-06T10:15:00+03:00', 'photoCount' => 12, 'childCount' => 4];
        $event = ['kind' => 'corrected', 'actorId' => 5, 'actorName' => 'curator', 'at' => '2026-09-22T11:00:00+03:00', 'sentAt' => '2026-09-22T10:15:00+03:00',
            'closesAt' => '2026-09-29T10:15:00+03:00', 'deliveryAt' => '2026-10-06T10:15:00+03:00', 'previousSentAt' => '2026-09-22T10:45:00+03:00',
            'previousClosesAt' => '2026-09-29T10:45:00+03:00', 'previousDeliveryAt' => '2026-10-06T10:45:00+03:00', 'reason' => 'Ошибка в дате'];
        $detail = new GroupLinkOutputDto(...$summary, galleryToken: str_repeat('f', 64), referenceNow: '2026-09-22T08:00:00+00:00', history: [new LinkEventOutputDto(...$event)]);
        self::assertSame(
            $summary + ['galleryToken' => str_repeat('f', 64), 'referenceNow' => '2026-09-22T08:00:00+00:00', 'history' => [$event]],
            json_decode($serializer->serialize($mapper->detail($detail)), true, 16, JSON_THROW_ON_ERROR),
        );
        $page = new GroupLinkPageOutputDto([new GroupLinkSummaryOutputDto(...$summary)], 1, 25, 1, 1);
        self::assertSame(
            ['data' => ['items' => [$summary]], 'meta' => ['page' => 1, 'pageSize' => 25, 'total' => 1, 'totalPages' => 1]],
            json_decode($serializer->serialize(['data' => $mapper->list($page), 'meta' => $mapper->meta($page)]), true, 16, JSON_THROW_ON_ERROR),
        );
        self::assertArrayNotHasKey('galleryToken', $summary);
        self::assertSame(
            ['revision' => 4, 'sentAt' => 's', 'closesAt' => 'c', 'deliveryAt' => 'd'],
            json_decode($serializer->serialize($mapper->calendar(new LinkCalendarOutputDto(4, 's', 'c', 'd'))), true, 4, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $values
     *
     * @return T
     */
    private function request(string $class, array $values): object
    {
        $request = ArrayToDtoMapper::map(StrictRequestValues::normalize($values + ['groupId' => self::UUID, 'idempotencyKey' => str_repeat('a', 32)], $class, true), $class);
        self::assertInstanceOf($class, $request);

        return $request;
    }

    /** @return array<string, mixed> */
    private function preparation(): array
    {
        return ['revision' => 1, 'signature' => str_repeat('a', 64), 'photosReviewed' => true, 'conditionsReviewed' => true, 'staffReviewed' => true, 'confirmed' => true];
    }

    /** @return array<string, mixed> */
    private function transmission(): array
    {
        return ['revision' => 2, 'signature' => str_repeat('a', 64), 'sentAt' => '2026-09-22T10:15:00+03:00', 'confirmed' => true];
    }

    /** @return array<string, mixed> */
    private function correction(): array
    {
        return ['reason' => 'Ошибка в дате', 'confirmed' => true] + $this->transmission();
    }

    private function assertCode(?string $code, callable $operation): void
    {
        try {
            $operation();
            self::fail('Rejected input was accepted.');
        } catch (HttpException $exception) {
            self::assertSame(422, $exception->getCode());
            if (null !== $code) {
                self::assertSame($code, $exception->getMessage());
            }
        }
    }
}
