<?php

declare(strict_types=1);

namespace Morefoto\Legal\Domain\Document\Service;

use Morefoto\Legal\Domain\Document\Enum\LegalDocumentEnum;
use Rebit\Share\Application\Contract\Consent\Enum\ConsentContextEnum;

/** Определяет, какие документы человек принимает в каждом сценарии.
 * Покупатель до заказа даёт отдельное согласие на обработку ПДн и отдельно принимает оферту; сотрудник при входе в
 * кабинет даёт своё согласие. Политика не принимается, а публикуется: на неё ссылаются согласия.
 */
final readonly class ConsentRequirementPolicy
{
    /** @return list<LegalDocumentEnum> */
    public function documents(ConsentContextEnum $context): array
    {
        return match ($context) {
            ConsentContextEnum::ORDER => [LegalDocumentEnum::BUYER_CONSENT, LegalDocumentEnum::OFFER],
            ConsentContextEnum::STAFF => [LegalDocumentEnum::STAFF_CONSENT],
        };
    }
}
