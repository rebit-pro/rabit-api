<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Receipt\UseCase;

use Morefoto\Payment\Application\Payment\Contract\PaymentStaffAccessInterface;
use Morefoto\Payment\Application\Receipt\Dto\PrintHandoversOutputDto;
use Morefoto\Payment\Application\Receipt\Service\PrintHandoverView;

/** Показывает организатору или куратору выдачу оплаченной печати группы (PAY-12): какие заказы ждут отметки выдачи,
 * какие выданы и чем закончился их закрывающий чек. Чужая группа неотличима от отсутствующей.
 */
final readonly class GetPrintHandoversUseCase
{
    public function __construct(
        private PaymentStaffAccessInterface $access,
        private PrintHandoverView $view,
    ) {}

    public function execute(int $actorId, string $groupId): PrintHandoversOutputDto
    {
        $scope = $this->access->scope($actorId);
        $view = $this->view->build($this->view->group($scope, $groupId))['view'];
        $this->access->assertUnchanged($actorId, $scope);

        return $view;
    }
}
