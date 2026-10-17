<?php

declare(strict_types=1);

namespace Morefoto\Payment\Tests\Unit;

use Morefoto\Payment\Application\Payment\Contract\PaymentStaffAccessInterface;
use Morefoto\Payment\Application\Payment\Dto\PaymentStaffScopeOutputDto;
use Morefoto\Payment\Application\Payment\Mapper\PaymentOutputMapper;
use Morefoto\Payment\Application\Receipt\Dto\HandOverPrintInputDto;
use Morefoto\Payment\Application\Receipt\Dto\PrintHandoverOrderOutputDto;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptOutputMapper;
use Morefoto\Payment\Application\Receipt\Mapper\ReceiptSnapshotMapper;
use Morefoto\Payment\Application\Receipt\Service\PrintHandoverView;
use Morefoto\Payment\Application\Receipt\Service\ReceiptSettings;
use Morefoto\Payment\Application\Receipt\UseCase\GetOrderReceiptsUseCase;
use Morefoto\Payment\Application\Receipt\UseCase\GetPrintHandoversUseCase;
use Morefoto\Payment\Application\Receipt\UseCase\HandOverPrintUseCase;
use Morefoto\Payment\Domain\Receipt\Enum\PaymentSubjectEnum;
use Morefoto\Payment\Domain\Receipt\ValueObject\ReceiptRules;
use Morefoto\Payment\Presentation\Receipt\ReceiptInputMapper;
use Morefoto\Payment\Presentation\Receipt\Request\Dto\HandOverPrintRequestDto;
use Morefoto\Payment\Tests\Unit\Support\FakeOrders;
use Morefoto\Payment\Tests\Unit\Support\PaymentScenario;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Rebit\Share\Contracts\Commerce\Dto\PaidPrintLineOutputDto;
use Rebit\Share\Contracts\Commerce\GroupPrintOrdersInterface;
use Rebit\Share\Contracts\Organization\Dto\GroupCalendarOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryItemOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryPageOutputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectoryQueryInputDto;
use Rebit\Share\Contracts\Organization\Dto\GroupDirectorySummaryOutputDto;
use Rebit\Share\Contracts\Organization\GroupDirectoryInterface;
use Rebit\Share\Shared\Exception\HttpException;

require_once dirname(__DIR__) . '/bootstrap.php';

/**
 * G2-T07, G2-T09: выдача печати группы (PAY-12/13) и чеки заказа для покупателя (PAY-04).
 *
 * @internal
 */
final class PrintHandoverTest extends TestCase
{
    private const string GROUP = '11111111-1111-4111-8111-111111111111';
    private const string ORDER = '77777777-7777-4777-8777-777777777777';
    private const string NO_RECEIPT = '88888888-8888-4888-8888-888888888888';
    private const string FAILED_RECEIPT = '99999999-9999-4999-8999-999999999999';

    public function testGroupOrdersShowWhatWaitsIsHandedOrCannotBeClosed(): void
    {
        $scenario = $this->scenario();

        $view = $this->handovers($scenario)->execute(1, self::GROUP);

        self::assertSame(['Солнышко', true, 1, 0, 2], [$view->groupName, $view->enabled, $view->waiting, $view->handed, $view->blocked]);
        self::assertSame([
            [self::ORDER, 'waiting', null, ['A'], 4],
            [self::NO_RECEIPT, 'blocked', 'no_payment_receipt', ['B'], 1],
            [self::FAILED_RECEIPT, 'blocked', 'payment_receipt_failed', ['C'], 1],
        ], array_map(static fn(PrintHandoverOrderOutputDto $order): array => [$order->orderId, $order->state, $order->reason, $order->childCodes, $order->prints], $view->orders));
        self::assertSame('2026-09-25T15:00:30+03:00', $view->orders[0]->paidAt);
    }

    public function testHandoverQueuesOneClosingReceiptPerOrderAndIsIrreversible(): void
    {
        $scenario = $this->scenario();
        $handOver = $this->handOver($scenario);

        $view = $handOver->execute(1, new HandOverPrintInputDto(self::GROUP, [self::ORDER], true));
        $again = $handOver->execute(1, new HandOverPrintInputDto(self::GROUP, [self::ORDER], true));

        self::assertSame(['handed', 'pending', '2026-09-25T15:00:00+03:00'], [$view->orders[0]->state, $view->orders[0]->receiptStatus, $view->orders[0]->handedAt]);
        self::assertSame([0, 1], [$again->waiting, $again->handed]);
        self::assertCount(1, array_filter($scenario->receipts->rows, static fn(array $row): bool => 'closing' === $row['KIND']), 'The repeat records nothing.');
    }

    public function testAnOrderOutsideTheWaitingListRejectsTheWholeRequest(): void
    {
        foreach ([[self::ORDER, self::NO_RECEIPT], ['12121212-1212-4212-8212-121212121212']] as $orderIds) {
            $scenario = $this->scenario();
            try {
                $this->handOver($scenario)->execute(1, new HandOverPrintInputDto(self::GROUP, $orderIds, true));
                self::fail('A blocked or foreign order was accepted.');
            } catch (HttpException $error) {
                self::assertSame(['HANDOVER_CONFLICT', 409], [$error->getMessage(), $error->getCode()]);
            }
            self::assertCount(0, array_filter($scenario->receipts->rows, static fn(array $row): bool => 'closing' === $row['KIND']));
        }
    }

    public function testConfirmationReceiptsAndScopeAreChecked(): void
    {
        $cases = [
            'CONFIRMATION_REQUIRED' => [$this->scenario(), [3], false],
            'GROUP_NOT_FOUND' => [$this->scenario(), [4], true],
            'RECEIPTS_DISABLED' => [$this->scenario(false), null, true],
        ];
        foreach ($cases as $code => [$scenario, $institutions, $confirmed]) {
            try {
                $this->handOver($scenario, $institutions)->execute(1, new HandOverPrintInputDto(self::GROUP, [self::ORDER], $confirmed));
                self::fail('Handover passed: ' . $code);
            } catch (HttpException $error) {
                self::assertSame($code, $error->getMessage());
            }
        }
    }

    public function testOrderListOfTheRequestIsValidated(): void
    {
        foreach ([[], [self::ORDER, self::ORDER], ['not-an-id'], array_fill(0, 101, self::ORDER)] as $orderIds) {
            try {
                new ReceiptInputMapper()->handOver(new HandOverPrintRequestDto($orderIds, true, self::GROUP));
                self::fail('An invalid order list was accepted.');
            } catch (HttpException $error) {
                self::assertSame(['INVALID_ORDERS', 422], [$error->getMessage(), $error->getCode()]);
            }
        }
        self::assertSame([self::ORDER], new ReceiptInputMapper()->handOver(new HandOverPrintRequestDto([self::ORDER], true, self::GROUP))->orderIds);
    }

    public function testBuyerSeesTheReceiptsOfTheOrderByItsKey(): void
    {
        $scenario = $this->scenario();
        $this->handOver($scenario)->execute(1, new HandOverPrintInputDto(self::GROUP, [self::ORDER], true));
        $receipts = new GetOrderReceiptsUseCase($scenario->orders, $scenario->receipts, $scenario->receiptSettings(), new ReceiptOutputMapper(new ReceiptSnapshotMapper(), new PaymentOutputMapper()));

        $output = $receipts->execute(FakeOrders::KEY);

        self::assertSame([true, 'email'], [$output->enabled, $output->channel]);
        self::assertSame([['payment', 'sent', 105000, '3986'], ['closing', 'pending', 60000, null]], array_map(static fn($receipt): array => [$receipt->kind, $receipt->status, $receipt->amount, $receipt->fiscalDocumentNumber], $output->receipts));
        self::assertSame(['full_prepayment', 'full_payment'], [$output->receipts[0]->items[0]->paymentMode, $output->receipts[0]->items[1]->paymentMode]);
        self::assertSame('full_payment', $output->receipts[1]->items[0]->paymentMode);

        $this->expectExceptionMessage('ORDER_NOT_FOUND');
        $receipts->execute(str_repeat('b', 64));
    }

    /** Заказ 7 оплачен с зарегистрированным чеком; у двух соседних заказов чека оплаты нет или он отклонён. */
    private function scenario(bool $receipts = true): PaymentScenario
    {
        $scenario = new PaymentScenario(receiptsEnabled: true)->paid('00000000-0000-4000-8000-000000000001', '2026-09-25 12:00:30');
        $scenario->receipts->rows[1]['STATUS'] = 'succeeded';
        $scenario->receipts->rows[1]['FISCAL_DOCUMENT_NUMBER'] = '3986';
        $failed = $scenario->receipts->rows[1];
        $scenario->receipts->rows[2] = ['ID' => 2, 'ATTEMPT_ID' => 9, 'ORDER_ID' => 9, 'ORDER_PUBLIC_ID' => self::FAILED_RECEIPT, 'STATUS' => 'canceled', 'PUBLIC_ID' => 'r-2'] + $failed;
        $scenario->receiptsEnabled = $receipts;

        return $scenario;
    }

    /** @param null|list<int> $institutions */
    private function access(?array $institutions): PaymentStaffAccessInterface
    {
        return new readonly class($institutions) implements PaymentStaffAccessInterface {
            /** @param null|list<int> $institutions */
            public function __construct(private ?array $institutions) {}

            public function scope(int $actorId): PaymentStaffScopeOutputDto
            {
                return new PaymentStaffScopeOutputDto(null === $this->institutions ? 'organizer' : 'curator', 1, $this->institutions);
            }

            public function assertUnchanged(int $actorId, PaymentStaffScopeOutputDto $scope): void {}
        };
    }

    private function view(PaymentScenario $scenario): PrintHandoverView
    {
        $groups = new readonly class implements GroupDirectoryInterface {
            public function page(GroupDirectoryQueryInputDto $query): GroupDirectoryPageOutputDto
            {
                throw new \LogicException('Not used.');
            }

            public function find(string $groupId): GroupDirectoryItemOutputDto
            {
                $calendar = new GroupCalendarOutputDto('Europe/Moscow', '2026-09-20T10:00:00+03:00', '2026-09-27T10:00:00+03:00', null, null, 'open');

                return new GroupDirectoryItemOutputDto(5, $groupId, 'Солнышко', 'regular', 3, 'inst', 'Детский сад', 2, 'shoot', 'Осень', $calendar);
            }

            public function summary(GroupDirectoryQueryInputDto $query, int $closingWithinHours): GroupDirectorySummaryOutputDto
            {
                throw new \LogicException('Not used.');
            }

            public function nativeIds(GroupDirectoryQueryInputDto $query): array
            {
                return [];
            }
        };
        $prints = new readonly class implements GroupPrintOrdersInterface {
            public function paidPrintLines(string $groupId, \DateTimeImmutable $deadline): array
            {
                $line = static fn(string $order, string $child, int $quantity, int $printCount): PaidPrintLineOutputDto => new PaidPrintLineOutputDto(
                    'line-' . $order . $child,
                    $order,
                    'MF-' . $child,
                    '2026-09-25 12:00:30',
                    'regular',
                    $child,
                    'photo',
                    $child . '1',
                    'product',
                    'Печать 10×15',
                    '10x15',
                    $printCount,
                    $quantity,
                );

                return [$line('77777777-7777-4777-8777-777777777777', 'A', 2, 2), $line('88888888-8888-4888-8888-888888888888', 'B', 1, 1), $line('99999999-9999-4999-8999-999999999999', 'C', 1, 1)];
            }

            public function waitingPrintOrders(string $groupId): int
            {
                return 0;
            }
        };
        $settings = new ReceiptSettings($scenario->receiptsEnabled, $scenario->receiptsEnabled ? new ReceiptRules(1, PaymentSubjectEnum::COMMODITY, PaymentSubjectEnum::COMMODITY) : null);

        return new PrintHandoverView($groups, $prints, $scenario->receipts, $settings, new PaymentOutputMapper());
    }

    private function handovers(PaymentScenario $scenario): GetPrintHandoversUseCase
    {
        return new GetPrintHandoversUseCase($this->access(null), $this->view($scenario));
    }

    /** @param null|list<int> $institutions */
    private function handOver(PaymentScenario $scenario, ?array $institutions = null): HandOverPrintUseCase
    {
        return new HandOverPrintUseCase($this->access($institutions), $this->view($scenario), $scenario->registrar(), $scenario->receiptSettings(), $scenario->transaction(), $scenario->clock(), new NullLogger());
    }
}
