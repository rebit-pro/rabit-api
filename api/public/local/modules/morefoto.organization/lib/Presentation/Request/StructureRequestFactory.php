<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Request;

use Bitrix\Main\HttpRequest;
use Morefoto\Organization\Application\Structure\Dto\ShootMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\GroupMutationInputDto;
use Morefoto\Organization\Application\Structure\Dto\StructurePageInputDto;
use Morefoto\Organization\Presentation\Dto\ShootRequestDto;
use Morefoto\Organization\Presentation\Dto\GroupRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class StructureRequestFactory
{
    public function shoot(HttpRequest $request, bool $create): ShootRequestDto
    {
        $data = $this->body($request, $create ? ['name', 'date'] : ['name', 'date', 'revision']);
        $this->string($data, 'name');
        if (array_key_exists('date', $data) && null !== $data['date'] && !is_string($data['date'])) {
            throw new HttpException('INVALID_FIELD', 422);
        }
        if ($create && !isset($data['name'])) {
            throw new HttpException('NAME_REQUIRED', 422);
        }
        if (!$create && !array_key_exists('name', $data) && !array_key_exists('date', $data)) {
            throw new HttpException('EMPTY_PATCH', 422);
        }

        return new ShootRequestDto(new ShootMutationInputDto(
            key: $this->key($request),
            name: $data['name'] ?? null,
            dateProvided: array_key_exists('date', $data),
            date: $data['date'] ?? null,
            revision: $this->revision($data, $create),
        ));
    }

    public function group(HttpRequest $request, bool $create): GroupRequestDto
    {
        $allowed = ['name', 'teacherId', 'assignmentSignature', 'replaceAssignments', 'reason'];
        $allowed[] = $create ? 'groupKind' : 'revision';
        $data = $this->body($request, $allowed);
        foreach (['name', 'groupKind', 'assignmentSignature'] as $field) {
            $this->string($data, $field);
        }
        if (array_key_exists('reason', $data) && null !== $data['reason'] && !is_string($data['reason'])) {
            throw new HttpException('INVALID_FIELD', 422);
        }
        if (array_key_exists('teacherId', $data) && null !== $data['teacherId'] && !is_int($data['teacherId'])) {
            throw new HttpException('INVALID_ASSIGNEE', 422);
        }
        if (array_key_exists('replaceAssignments', $data) && !is_bool($data['replaceAssignments'])) {
            throw new HttpException('INVALID_FIELD', 422);
        }
        if ($create && (!isset($data['name']) || !isset($data['groupKind']))) {
            throw new HttpException('REQUIRED_FIELD', 422);
        }
        if (!$create && !array_key_exists('name', $data) && !array_key_exists('teacherId', $data)) {
            throw new HttpException('EMPTY_PATCH', 422);
        }

        return new GroupRequestDto(new GroupMutationInputDto(
            key: $this->key($request),
            name: $data['name'] ?? null,
            groupKind: $data['groupKind'] ?? null,
            revision: $this->revision($data, $create),
            teacherProvided: array_key_exists('teacherId', $data),
            teacherId: $data['teacherId'] ?? null,
            assignmentSignature: $data['assignmentSignature'] ?? null,
            replaceAssignments: $data['replaceAssignments'] ?? false,
            reason: $data['reason'] ?? null,
        ));
    }

    public function listing(HttpRequest $request): StructurePageInputDto
    {
        $data = $request->getQueryList()->getValues();
        if ([] !== array_diff(array_keys($data), ['page', 'pageSize'])) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }
        foreach (['page', 'pageSize'] as $field) {
            if (array_key_exists($field, $data) && (!is_scalar($data[$field]) || 1 !== preg_match('/^[1-9][0-9]{0,6}$/D', (string)$data[$field]))) {
                throw new HttpException('INVALID_PAGE', 422);
            }
        }

        return new StructurePageInputDto((int)($data['page'] ?? 1), (int)($data['pageSize'] ?? 50));
    }

    /** @param list<string> $allowed
     * @return array<string,mixed>
     */
    private function body(HttpRequest $request, array $allowed): array
    {
        if (1 !== preg_match('/^application\/json(?:\s*;|$)/i', (string)$request->getHeader('Content-Type'))) {
            throw new HttpException('JSON_REQUIRED', 400);
        }
        $raw = (string)$request->getInput();
        if (32768 < strlen($raw)) {
            throw new HttpException('BODY_TOO_LARGE', 400);
        }
        try {
            $object = json_decode($raw, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new HttpException('INVALID_JSON', 400);
        }
        if (!$object instanceof \stdClass) {
            throw new HttpException('JSON_OBJECT_REQUIRED', 400);
        }
        $data = get_object_vars($object);
        if ([] !== array_diff(array_keys($data), $allowed) || [] !== $request->getQueryList()->getValues()) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }

        return $data;
    }

    private function key(HttpRequest $request): string
    {
        $key = (string)$request->getHeader('Idempotency-Key');
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/D', $key)) {
            throw new HttpException('INVALID_IDEMPOTENCY_KEY', 422);
        }

        return strtolower($key);
    }

    /** @param array<string,mixed> $data */
    private function string(array $data, string $field): void
    {
        if (array_key_exists($field, $data) && !is_string($data[$field])) {
            throw new HttpException('INVALID_FIELD', 422);
        }
    }

    /** @param array<string,mixed> $data */
    private function revision(array $data, bool $create): ?int
    {
        if ($create) {
            return null;
        }
        if (!isset($data['revision']) || !is_int($data['revision']) || 1 > $data['revision'] || 2147483646 < $data['revision']) {
            throw new HttpException('REVISION_REQUIRED', 422);
        }

        return $data['revision'];
    }
}
