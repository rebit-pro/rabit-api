<?php

declare(strict_types=1);

namespace Morefoto\Access\Presentation\Request;

use Bitrix\Main\HttpRequest;
use Morefoto\Access\Application\Staff\Dto\StaffMutationInputDto;
use Morefoto\Access\Domain\Staff\Enum\RoleEnum;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class StaffRequestFactory
{
    public function mutation(HttpRequest $request, bool $create): StaffMutationInputDto
    {
        if (1 !== preg_match('/^application\/json(?:\s*;|$)/i', (string)$request->getHeader('Content-Type'))) {
            throw new HttpException('JSON_REQUIRED', 400);
        }
        $key = strtolower((string)$request->getHeader('Idempotency-Key'));
        if (1 !== preg_match('/^[a-f0-9]{32}$/D', $key)) {
            throw new HttpException('INVALID_IDEMPOTENCY_KEY', 422);
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
        $allowed = ['name', 'email', 'role', 'active', 'institutionIds', 'groupIds', 'replaceAssignments', 'assignmentSignature', 'reason'];
        if (!$create) {
            $allowed[] = 'revision';
        }
        if ([] !== array_diff(array_keys($data), $allowed) || [] !== $this->query($request)) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }
        foreach (['name', 'email', 'role'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                throw new HttpException('INVALID_FIELD', 422);
            }
        }
        $name = trim($data['name']);
        $email = mb_strtolower(trim($data['email']));
        $role = RoleEnum::tryFrom($data['role']);
        if ('' === $name || 100 < mb_strlen($name) || false === filter_var($email, FILTER_VALIDATE_EMAIL) || 254 < strlen($email)) {
            throw new HttpException('INVALID_FIELD', 422);
        }
        if (null === $role) {
            throw new HttpException('INVALID_ROLE', 422);
        }
        if (!isset($data['active']) || !is_bool($data['active'])) {
            throw new HttpException('INVALID_ACTIVE', 422);
        }
        if (isset($data['replaceAssignments']) && !is_bool($data['replaceAssignments'])) {
            throw new HttpException('INVALID_FIELD', 422);
        }
        $signature = $data['assignmentSignature'] ?? null;
        $reason = $data['reason'] ?? null;
        if (null !== $signature && (!is_string($signature) || 1 !== preg_match('/^a[1-9][0-9]{0,18}$/D', $signature))) {
            throw new HttpException('INVALID_SIGNATURE', 422);
        }
        if (null !== $reason && (!is_string($reason) || '' === trim($reason) || 500 < mb_strlen(trim($reason)))) {
            throw new HttpException('INVALID_REASON', 422);
        }
        $revision = $data['revision'] ?? null;
        if (!$create && (!is_int($revision) || 1 > $revision || 2147483646 < $revision)) {
            throw new HttpException('REVISION_REQUIRED', 422);
        }

        return new StaffMutationInputDto($key, $name, $email, $role, $data['active'], $this->ids($data['institutionIds'] ?? []), $this->ids($data['groupIds'] ?? []), $data['replaceAssignments'] ?? false, $signature, null === $reason ? null : trim($reason), $revision);
    }

    /** @return list<string> */
    private function ids(mixed $value): array
    {
        if (!is_array($value) || 1000 < count($value)) {
            throw new HttpException('INVALID_ASSIGNMENT', 422);
        }
        foreach ($value as $id) {
            if (!is_string($id) || 1 !== preg_match('/^[0-9a-f-]{36}$/D', $id)) {
                throw new HttpException('INVALID_ASSIGNMENT', 422);
            }
        }

        return array_values(array_unique($value));
    }

    /** @return array<string,mixed> */
    private function query(HttpRequest $request): array
    {
        $query = [];
        parse_str((string)parse_url((string)$request->getRequestUri(), PHP_URL_QUERY), $query);

        return $query;
    }
}
