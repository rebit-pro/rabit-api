<?php

declare(strict_types=1);

namespace Morefoto\Commerce\Application\Conditions\UseCase;

use Morefoto\Commerce\Application\Catalog\Contract\CatalogTransactionInterface;
use Morefoto\Commerce\Application\Conditions\Dto\ConditionsMutationOutputDto;
use Morefoto\Commerce\Application\Conditions\Dto\SaveConditionsInputDto;
use Morefoto\Commerce\Application\Conditions\Service\ConditionsProducts;
use Morefoto\Commerce\Domain\Catalog\Exception\CatalogRevisionConflictException;
use Morefoto\Commerce\Domain\Catalog\Repository\CatalogRepository;
use Morefoto\Commerce\Domain\Conditions\Exception\ConditionsRevisionConflictException;
use Morefoto\Commerce\Domain\Conditions\Repository\SalesConditionsRepository;

final readonly class SaveGlobalConditionsUseCase
{
    public function __construct(private SalesConditionsRepository $conditions, private CatalogRepository $catalogue, private CatalogTransactionInterface $transaction, private ConditionsProducts $products) {}

    public function execute(SaveConditionsInputDto $input): ConditionsMutationOutputDto
    {
        return $this->transaction->execute(fn(): ConditionsMutationOutputDto => $this->executeWithinTransaction($input));
    }

    public function executeWithinTransaction(SaveConditionsInputDto $input): ConditionsMutationOutputDto
    {
        $state = $this->conditions->global(true);
        if ($input->revision !== (int)$state['REVISION']) {
            throw new ConditionsRevisionConflictException('Global sales conditions changed; reload before saving.');
        }
        $catalogRevision = $this->catalogue->lockRevision(true);
        if ($input->catalogRevision !== $catalogRevision) {
            throw new CatalogRevisionConflictException('Catalogue changed; reload conditions before saving.');
        }
        $catalogue = $this->products->read($this->conditions->globalProducts());
        foreach ($this->products->validate($input, $catalogue, false) as $product) {
            $this->conditions->updateGlobalProduct($product);
        }
        $catalogRevision = $this->catalogue->advanceRevision($catalogRevision);
        $revision = $this->conditions->advanceGlobal($input->revision, $input->effectiveGiftThreshold(), $input->giftForStaff);

        return new ConditionsMutationOutputDto($revision, $catalogRevision, $revision);
    }
}
