<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Controller;

use Morefoto\Access\Application\Avatar\UseCase\DeleteStaffAvatarUseCase;
use Morefoto\Access\Application\Avatar\UseCase\GetStaffAvatarUseCase;
use Morefoto\Access\Application\Avatar\UseCase\SaveStaffAvatarUseCase;
use Morefoto\Access\Presentation\Avatar\AvatarInputMapper;
use Morefoto\Access\Presentation\Avatar\Dto\AvatarImageRequestDto;
use Morefoto\Access\Presentation\Avatar\Dto\SaveMyAvatarRequestDto;
use Morefoto\Access\Presentation\Avatar\Dto\SaveStaffAvatarRequestDto;
use Morefoto\Access\Presentation\Avatar\Dto\StaffAvatarRequestDto;
use Rebit\Share\Infrastructure\Bitrix\ControllerJson;
use Rebit\Share\Infrastructure\Controller\AuthenticatedApiJsonController;
use Rebit\Share\Infrastructure\Controller\Responses\EmptyResponse;
use Rebit\Share\Infrastructure\Controller\Responses\ImageResponse;

final class StaffAvatarController extends AuthenticatedApiJsonController
{
    public function __construct(
        private readonly SaveStaffAvatarUseCase $save,
        private readonly DeleteStaffAvatarUseCase $delete,
        private readonly GetStaffAvatarUseCase $get,
        private readonly AvatarInputMapper $input,
    ) {
        parent::__construct();
    }

    public function saveMineAction(SaveMyAvatarRequestDto $request): ControllerJson
    {
        return $this->json($this->save->execute($this->getAuthUserId(), $this->getAuthUserId(), $this->input->upload($request)));
    }

    public function deleteMineAction(): EmptyResponse
    {
        $this->delete->execute($this->getAuthUserId(), $this->getAuthUserId());

        return $this->noContent();
    }

    public function saveAction(SaveStaffAvatarRequestDto $request): ControllerJson
    {
        return $this->json($this->save->execute($this->getAuthUserId(), $this->input->userId($request), $this->input->upload($request)));
    }

    public function deleteAction(StaffAvatarRequestDto $request): EmptyResponse
    {
        $this->delete->execute($this->getAuthUserId(), $this->input->userId($request));

        return $this->noContent();
    }

    public function imageAction(AvatarImageRequestDto $request): ImageResponse
    {
        return $this->image(
            $this->get->execute(
                $this->getAuthUserId(),
                $this->input->userId($request),
                $this->input->variant($request),
                $this->input->version($request),
            ),
            $request->ifNoneMatch,
        );
    }
}
