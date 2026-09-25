<?php

declare(strict_types=1);

namespace Morefoto\Legal\Presentation\Controller;

use Morefoto\Legal\Application\Consent\UseCase\AcceptStaffConsentsUseCase;
use Morefoto\Legal\Application\Consent\UseCase\GetPendingStaffConsentsUseCase;
use Morefoto\Legal\Presentation\Consent\Dto\AcceptConsentsRequestDto;
use Morefoto\Legal\Presentation\Consent\Dto\PendingConsentsRequestDto;
use Morefoto\Legal\Presentation\LegalResultMapper;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Presentation\Consent\AcceptedDocumentInputMapper;

/** Согласия сотрудника в кабинете: что осталось принять и принятие действующих редакций. */
final class StaffConsentController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly GetPendingStaffConsentsUseCase $pending,
        private readonly AcceptStaffConsentsUseCase $accept,
        private readonly AcceptedDocumentInputMapper $input,
        private readonly LegalResultMapper $result,
    ) {
        parent::__construct();
    }

    public function pendingAction(PendingConsentsRequestDto $request): ControllerJson
    {
        return $this->json($this->result->pending($this->pending->execute($this->getAuthUserId())));
    }

    public function acceptAction(AcceptConsentsRequestDto $request): ControllerJson
    {
        return $this->json($this->result->pending($this->accept->execute($this->getAuthUserId(), $this->input->documents($request->consents))));
    }
}
