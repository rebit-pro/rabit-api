<?php

declare(strict_types=1);

namespace Morefoto\Organization\Presentation\Request;

use Bitrix\Main\HttpRequest;
use Morefoto\Organization\Application\Institution\Dto\InstitutionMutationInputDto;
use Morefoto\Organization\Application\Institution\Dto\ListInstitutionsInputDto;
use Morefoto\Organization\Domain\Institution\ValueObject\InstitutionDetails;
use Morefoto\Organization\Presentation\Dto\InstitutionRequestDto;
use Rebit\Share\Shared\Exception\HttpException;

final readonly class InstitutionRequestFactory
{
    public function mutation(HttpRequest $request, bool $create): InstitutionRequestDto
    {
        if (1 !== preg_match('/^application\/json(?:\s*;|$)/i', (string)$request->getHeader('Content-Type'))) {
            throw new HttpException('JSON_REQUIRED', 400);
        }
        $key = (string)$request->getHeader('Idempotency-Key');
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/D', $key)) {
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
        $allowed = ['name', 'address', 'curatorId', 'headId', 'assignmentSignature', 'replaceAssignments'];
        if (!$create) {
            $allowed[] = 'revision';
        }
        if ([] !== array_diff(array_keys($data), $allowed) || [] !== $this->queryParameters($request)) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }
        foreach (['name', 'address', 'assignmentSignature'] as $field) {
            if (array_key_exists($field, $data) && !is_string($data[$field])) {
                throw new HttpException('INVALID_FIELD', 422);
            }
        }
        foreach (['curatorId', 'headId'] as $field) {
            if (array_key_exists($field, $data) && null !== $data[$field] && (!is_int($data[$field]) || 1 > $data[$field] || 2147483647 < $data[$field])) {
                throw new HttpException('INVALID_ASSIGNEE', 422);
            }
        }
        if (array_key_exists('replaceAssignments', $data) && !is_bool($data['replaceAssignments'])) {
            throw new HttpException('INVALID_FIELD', 422);
        }
        if (!$create && (!isset($data['revision']) || !is_int($data['revision']) || 1 > $data['revision'] || 2147483646 < $data['revision'])) {
            throw new HttpException('REVISION_REQUIRED', 422);
        }
        if ($create && !isset($data['name'])) {
            throw new HttpException('NAME_REQUIRED', 422);
        }
        if (!$create && [] === array_intersect(array_keys($data), ['name', 'address', 'curatorId', 'headId'])) {
            throw new HttpException('EMPTY_PATCH', 422);
        }
        if (isset($data['assignmentSignature']) && 1 !== preg_match('/^a[1-9][0-9]{0,18}$/D', $data['assignmentSignature'])) {
            throw new HttpException('INVALID_SIGNATURE', 422);
        }
        $validated = new InstitutionDetails($data['name'] ?? 'validation', $data['address'] ?? '');

        return new InstitutionRequestDto(new InstitutionMutationInputDto(
            key: strtolower($key),
            name: isset($data['name']) ? $validated->name : null,
            address: isset($data['address']) ? $validated->address : null,
            revision: $data['revision'] ?? null,
            curatorProvided: array_key_exists('curatorId', $data),
            curatorId: $data['curatorId'] ?? null,
            headProvided: array_key_exists('headId', $data),
            headId: $data['headId'] ?? null,
            assignmentSignature: $data['assignmentSignature'] ?? null,
            replaceAssignments: $data['replaceAssignments'] ?? false,
        ));
    }

    public function listing(HttpRequest $request): ListInstitutionsInputDto
    {
        $data = $this->queryParameters($request);
        if ([] !== array_diff(array_keys($data), ['q', 'page', 'pageSize'])) {
            throw new HttpException('UNKNOWN_FIELD', 422);
        }
        foreach (['page', 'pageSize'] as $field) {
            if (isset($data[$field]) && (!is_scalar($data[$field]) || 1 !== preg_match('/^[1-9][0-9]{0,6}$/D', (string)$data[$field]))) {
                throw new HttpException('INVALID_PAGE', 422);
            }
        }
        if (array_key_exists('q', $data) && !is_string($data['q'])) {
            throw new HttpException('INVALID_QUERY', 422);
        }

        return new ListInstitutionsInputDto(
            query: $data['q'] ?? '',
            page: (int)($data['page'] ?? 1),
            pageSize: (int)($data['pageSize'] ?? 50),
        );
    }

    private function queryParameters(HttpRequest $request): array
    {
        // Bitrix adds matched path parameters to GET; only the original URI contains client query fields.
        $query = [];
        parse_str((string)parse_url((string)$request->getRequestUri(), PHP_URL_QUERY), $query);

        return $query;
    }
}
