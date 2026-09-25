<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsMutationOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsInputValidator;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsRevisionConflictException;
use Morefoto\Commerce\Domain\Conditions\Exception\InvalidConditionsException;
use Morefoto\Commerce\Domain\Conditions\Repository\SalesConditionsRepository;

/**
 * Сохраняет условия группы: наследование общих или собственные цены, доступность и подарок. Сверяет версии
 * общих условий, каталога и группы; политику учёта расходов группа не задаёт — она общая для продавца.
 */
final readonly class SaveGroupConditionsUseCase
{
    public function __construct(
        private SalesConditionsRepository $conditions,
        private CatalogRepository $catalogue,
        private CatalogTransactionInterface $transaction,
        private ConditionsProducts $products,
        private ConditionsInputValidator $validator,
    ) {}

    public function execute(int $groupId, SaveConditionsInputDto $input): ConditionsMutationOutputDto
    {
        return $this->transaction->execute(fn(): ConditionsMutationOutputDto => $this->executeWithinTransaction($groupId, $input));
    }

    public function executeWithinTransaction(int $groupId, SaveConditionsInputDto $input): ConditionsMutationOutputDto
    {
        $this->validator->validate($input);
        if (null !== $input->paymentCosts) {
            throw new InvalidConditionsException('The payment cost policy belongs to global sales conditions.');
        }
        $global = $this->conditions->global(false);
        if ($input->conditionsRevision !== (int)$global['REVISION']) {
            throw new ConditionsRevisionConflictException('Global sales conditions changed; reload the group conditions.');
        }
        $catalogRevision = $this->catalogue->lockRevision(false);
        if ($input->catalogRevision !== $catalogRevision) {
            throw new CatalogRevisionConflictException('Catalogue changed; reload the group conditions.');
        }
        $group = $this->conditions->group($groupId, true);
        $revision = false === $group ? 0 : (int)$group['REVISION'];
        if ($input->revision !== $revision) {
            throw new ConditionsRevisionConflictException('Group sales conditions changed; reload before saving.');
        }
        $catalogue = $this->products->read($this->conditions->globalProducts());
        $products = $input->inherit ? [] : $this->products->validate($input, $catalogue, true);
        $this->conditions->replaceGroupProducts($groupId, $products);
        $revision = $this->conditions->saveGroup(
            groupId: $groupId,
            current: $revision,
            inherit: $input->inherit,
            threshold: $input->inherit ? 0 : $this->validator->giftThreshold($input),
            giftForStaff: !$input->inherit && $input->giftForStaff,
        );

        return new ConditionsMutationOutputDto($revision, $catalogRevision, (int)$global['REVISION']);
    }
}
