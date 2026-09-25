<?php

declare(strict_types=1);

namespace Morefoto\Payment\Application\Payment\Contract;

use Morefoto\Payment\Application\Payment\Dto\CreateProviderPaymentInputDto;
use Morefoto\Payment\Application\Payment\Dto\ProviderPaymentOutputDto;
use Morefoto\Payment\Application\Payment\Exception\ProviderRejectedException;
use Morefoto\Payment\Application\Payment\Exception\ProviderUnavailableException;

interface PaymentProviderInterface
{
    /** Код провайдера в маршруте уведомлений и хранилище, например yookassa. */
    public function code(): string;

    /** Магазин, от имени которого создаются платежи; ответ провайдера сверяется с ним. */
    public function shopId(): string;

    /**
     * Повтор с тем же ключом идемпотентности в пределах срока гарантии возвращает тот же платёж.
     *
     * @throws ProviderRejectedException    провайдер окончательно отказал
     * @throws ProviderUnavailableException исход не известен
     */
    public function create(CreateProviderPaymentInputDto $input): ProviderPaymentOutputDto;

    /**
     * @throws ProviderRejectedException
     * @throws ProviderUnavailableException
     */
    public function find(string $providerPaymentId): ProviderPaymentOutputDto;
}
