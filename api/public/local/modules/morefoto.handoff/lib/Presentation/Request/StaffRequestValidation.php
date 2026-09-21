<?php

declare(strict_types=1);

namespace Morefoto\Handoff\Presentation\Request;

use Morefoto\Handoff\Presentation\Request\Dto\StaffRequestRowRequestDto;
use Morefoto\Handoff\Application\Request\Dto\StaffRequestMutationInputDto;
use Rebit\Share\Shared\Exception\HttpException;

/** Валидирует и нормализует presentation DTO до входного контракта сценария заявки. */
final class StaffRequestValidation
{
    public const string UUID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D';

    /**
     * @param list<StaffRequestRowRequestDto> $rows
     *
     * @throws HttpException
     */
    public static function mutation(
        string $institutionId,
        string $shootId,
        array $rows,
        string $comment,
        ?int $revision,
        bool $create,
    ): StaffRequestMutationInputDto {
        if (!self::isUuid($institutionId) || !self::isUuid($shootId)
            || [] === $rows || 30 < count($rows) || 500 < mb_strlen($comment)
            || (!$create && (null === $revision || 1 > $revision))) {
            throw new HttpException('VALIDATION_FAILED', 422);
        }

        $normalized = [];
        $ids = [];
        foreach ($rows as $row) {
            $value = self::row($row);
            if (isset($ids[$value['id']])) {
                throw new HttpException('INVALID_ROW', 422);
            }
            $ids[$value['id']] = true;
            $normalized[] = $value;
        }

        return new StaffRequestMutationInputDto(
            $institutionId,
            $shootId,
            $normalized,
            trim($comment),
            $create ? null : $revision,
        );
    }

    public static function requireUuid(string $value, string $code = 'INVALID_FILTER'): string
    {
        if (!self::isUuid($value)) {
            throw new HttpException($code, 422);
        }

        return $value;
    }

    public static function optionalUuid(?string $value): ?string
    {
        return null === $value ? null : self::requireUuid($value);
    }

    public static function isUuid(string $value): bool
    {
        return 1 === preg_match(self::UUID_PATTERN, $value);
    }

    /**
     * @return array{id:string,groupId:string,code:string}
     *
     * @throws HttpException
     */
    private static function row(StaffRequestRowRequestDto $request): array
    {
        $code = strtoupper(trim($request->code));
        if (!self::isUuid($request->id)
            || !self::isUuid($request->groupId)
            || 1 !== preg_match('/^[A-Z]{1,3}(?:[0-9]{3})?$/D', $code)) {
            throw new HttpException('INVALID_ROW', 422);
        }

        return [
            'id' => $request->id,
            'groupId' => $request->groupId,
            'code' => $code,
        ];
    }
}
